<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Models\VisitorEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Register or update a visitor and return visitor_id.
     */
    public function identify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'        => 'required|exists:projects,id',
            'fingerprint'       => 'required|string|max:64',
            'browser'           => 'nullable|string|max:50',
            'os'                => 'nullable|string|max:50',
            'device'            => 'nullable|string|max:20',
            'language'          => 'nullable|string|max:10',
            'referrer'          => 'nullable|string|max:500',
            'screen_resolution' => 'nullable|string|max:20',
        ]);

        $ip = $request->ip();

        // Geo-lookup (simple, based on IP – we skip external API for now)
        $country = null;
        $city    = null;

        $visitor = Visitor::updateOrCreate(
            [
                'project_id'  => $validated['project_id'],
                'fingerprint' => $validated['fingerprint'],
            ],
            [
                'ip'                => $ip,
                'country'           => $country,
                'city'              => $city,
                'browser'           => $validated['browser'] ?? null,
                'os'                => $validated['os'] ?? null,
                'device'            => $validated['device'] ?? null,
                'language'          => $validated['language'] ?? null,
                'referrer'          => $validated['referrer'] ?? null,
                'screen_resolution' => $validated['screen_resolution'] ?? null,
                'last_visit_at'     => now(),
            ]
        );

        // If existing visitor, bump visit count
        if (!$visitor->wasRecentlyCreated) {
            $visitor->increment('visit_count');
        }

        return response()->json(['visitor_id' => $visitor->id]);
    }

    /**
     * Track an event.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id'  => 'required|exists:visitors,id',
            'project_id'  => 'required|exists:projects,id',
            'event_type'  => 'required|string|max:50',
            'target_id'   => 'nullable|integer',
            'target_type' => 'nullable|string|max:50',
            'meta'        => 'nullable|array',
        ]);

        VisitorEvent::create([
            'visitor_id'  => $validated['visitor_id'],
            'project_id'  => $validated['project_id'],
            'event_type'  => $validated['event_type'],
            'target_id'   => $validated['target_id'] ?? null,
            'target_type' => $validated['target_type'] ?? null,
            'meta'        => $validated['meta'] ?? null,
            'created_at'  => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Stats endpoint for authenticated users / backend.
     */
    public function stats(Request $request, int $projectId): JsonResponse
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to', now()->toDateString());

        // Unique visitors
        $visitors = Visitor::where('project_id', $projectId)
            ->whereBetween('last_visit_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get();

        // Events
        $events = VisitorEvent::where('project_id', $projectId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get();

        // --- Aggregations ---

        // Events by type
        $eventsByType = $events->groupBy('event_type')->map(fn($g) => $g->count());

        // Events per day
        $eventsPerDay = $events->groupBy(fn($e) => $e->created_at->toDateString())
            ->map(fn($g) => $g->count())
            ->sortKeys();

        // Visitors per day
        $visitorsPerDay = $visitors->groupBy(fn($v) => $v->first_visit_at->toDateString())
            ->map(fn($g) => $g->count())
            ->sortKeys();

        // Top apartments viewed
        $topApartments = $events->where('event_type', 'apartment_view')
            ->groupBy('target_id')
            ->map(fn($g) => $g->count())
            ->sortDesc()
            ->take(20);

        // Browser breakdown
        $browsers = $visitors->groupBy('browser')->map(fn($g) => $g->count())->sortDesc();

        // Device breakdown
        $devices = $visitors->groupBy('device')->map(fn($g) => $g->count())->sortDesc();

        // Country breakdown
        $countries = $visitors->groupBy('country')->map(fn($g) => $g->count())->sortDesc();

        // Language breakdown
        $languages = $visitors->groupBy('language')->map(fn($g) => $g->count())->sortDesc();

        // Referrer breakdown
        $referrers = $visitors->groupBy(fn($v) => $v->referrer ? parse_url($v->referrer, PHP_URL_HOST) ?? $v->referrer : 'Direkt')
            ->map(fn($g) => $g->count())
            ->sortDesc();

        // Recent visitors (last 50)
        $recentVisitors = Visitor::where('project_id', $projectId)
            ->orderByDesc('last_visit_at')
            ->limit(50)
            ->get();

        // Recent events (last 100)
        $recentEvents = VisitorEvent::where('project_id', $projectId)
            ->with('visitor:id,fingerprint,browser,device,ip')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'summary' => [
                'total_visitors'    => $visitors->count(),
                'total_events'      => $events->count(),
                'returning_visitors'=> $visitors->where('visit_count', '>', 1)->count(),
                'avg_events_per_visitor' => $visitors->count() ? round($events->count() / $visitors->count(), 1) : 0,
            ],
            'events_by_type'   => $eventsByType,
            'events_per_day'   => $eventsPerDay,
            'visitors_per_day' => $visitorsPerDay,
            'top_apartments'   => $topApartments,
            'browsers'         => $browsers,
            'devices'          => $devices,
            'countries'        => $countries,
            'languages'        => $languages,
            'referrers'        => $referrers,
            'recent_visitors'  => $recentVisitors,
            'recent_events'    => $recentEvents,
        ]);
    }
}
