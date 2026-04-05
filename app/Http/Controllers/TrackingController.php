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
            'campaign'          => 'nullable|string|max:100',
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
                'campaign'          => $validated['campaign'] ?? null,
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

        $apartments = \App\Models\Apartment::where('project_id', $projectId)->get();
        $statusDistribution = $apartments->groupBy(function($ap) {
            return $ap->status ?: 'Frei'; // fallback
        })->map(fn($g) => $g->count());

        $totalVolume = $apartments->sum('price');
        $availableVolume = $apartments->filter(fn($a) => in_array($a->status, ['Frei', null, '']))->sum('price');
        $soldVolume = $apartments->filter(fn($a) => $a->status === 'Verkauft')->sum('price');
        $reservedVolume = $apartments->filter(fn($a) => $a->status === 'Reserviert')->sum('price');

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

        // Leads per day
        $leadsPerDay = $events->where('event_type', 'contact_click')
            ->groupBy(fn($e) => $e->created_at->toDateString())
            ->map(fn($g) => $g->count())
            ->sortKeys();

        // Top apartments viewed
        $topApartments = $events->where('event_type', 'apartment_view')
            ->groupBy('target_id')
            ->map(fn($g) => $g->count())
            ->sortDesc()
            ->take(20);

        // Interaction Density by House (Target type = 'house')
        $houseInteractions = $events->where('target_type', 'house')
            ->groupBy('target_id')
            ->map(fn($g) => $g->count())
            ->sortDesc();

        // Interaction Density by Floor (Target type = 'floor')
        $floorInteractions = $events->where('target_type', 'floor')
            ->groupBy('target_id')
            ->map(fn($g) => $g->count())
            ->sortDesc();

        // Interaction Density by Layer (Target type = 'layer')
        $layerInteractions = $events->where('target_type', 'layer')
            ->groupBy('target_id')
            ->map(fn($g) => $g->count())
            ->sortDesc();

        // Filter Actions Used
        $filterUsage = $events->where('event_type', 'filter_used')
            ->map(fn($e) => $e->meta ?? [])
            ->collapse() // flattens all keys, this might just group meta keys
            ->toArray();
        
        $filterCounts = [];
        if (!empty($filterUsage)) {
            // Count frequency of certain filter keys being used
            foreach ($events->where('event_type', 'filter_used') as $fcEvent) {
                if ($fcEvent->meta) {
                    foreach ($fcEvent->meta as $fk => $fv) {
                        if (!isset($filterCounts[$fk])) $filterCounts[$fk] = 0;
                        $filterCounts[$fk]++;
                    }
                }
            }
        }
        arsort($filterCounts);

        // Browser breakdown
        $browsers = $visitors->groupBy('browser')->map(fn($g) => $g->count())->sortDesc();

        // Device breakdown
        $devices = $visitors->groupBy('device')->map(fn($g) => $g->count())->sortDesc();

        // Country breakdown
        $countries = $visitors->groupBy('country')->map(fn($g) => $g->count())->sortDesc();

        // Language breakdown
        $languages = $visitors->groupBy('language')->map(fn($g) => $g->count())->sortDesc();

        // Referrer Breakdown (Excluding internal traffic)
        $host = $request->getHost();
        $referrers = $visitors->whereNotNull('referrer')
            ->map(function($v) use ($host) {
                $refHost = parse_url($v->referrer, PHP_URL_HOST) ?: $v->referrer;
                // Normalize some common ones
                if (str_contains($refHost, 'google.')) return 'Google Search';
                if (str_contains($refHost, 'facebook.com') || str_contains($refHost, 'fb.me')) return 'Facebook';
                if (str_contains($refHost, 'instagram.com')) return 'Instagram';
                if (str_contains($refHost, 'linkedin.com')) return 'LinkedIn';
                if (str_contains($refHost, 'immowelt.de')) return 'Immowelt';
                if (str_contains($refHost, 'immobilienscout24.de')) return 'ImmoScout24';
                if ($refHost === $host) return null; // Internal
                return $refHost;
            })
            ->filter()
            ->groupBy(fn($r) => $r)
            ->map(fn($g) => $g->count())
            ->sortDesc();

        // Campaign Breakdown
        $campaigns = $visitors->whereNotNull('campaign')
            ->groupBy('campaign')
            ->map(function($group) use ($events) {
                $visitorIds = $group->pluck('id');
                return [
                    'visitors' => count($group),
                    'leads' => $events->where('event_type', 'contact_click')->whereIn('visitor_id', $visitorIds)->count(),
                    'conversion_rate' => count($group) > 0 
                        ? round(($events->where('event_type', 'contact_click')->whereIn('visitor_id', $visitorIds)->count() / count($group)) * 100, 1) 
                        : 0
                ];
            })
            ->sortDesc();

        // Recent visitors (last 50) – with lead scoring & sanitized for JSON
        $recentVisitors = Visitor::where('project_id', $projectId)
            ->with(['events' => fn($q) => $q->orderByDesc('created_at')->limit(100)])
            ->orderByDesc('last_visit_at')
            ->limit(50)
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'fingerprint' => $v->fingerprint,
                'ip' => $v->ip,
                'browser' => $v->browser,
                'device' => $v->device,
                'os' => $v->os,
                'visit_count' => $v->visit_count,
                'last_visit_at' => $v->last_visit_at->toIso8601String(),
                'lead_score' => $v->lead_score,
                'lead_label' => $v->lead_label,
                'events_count' => $v->events->count(),
                'timeline' => $v->activity_timeline, // Already sanitized array
            ]);

        // Recent events (last 100) – sanitized to avoid circular Visitor relation
        $recentEvents = VisitorEvent::where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($ev) => [
                'id' => $ev->id,
                'created_at' => $ev->created_at->toIso8601String(),
                'event_type' => $ev->event_type,
                'target_type' => $ev->target_type,
                'target_id' => $ev->target_id,
                'meta' => $ev->meta,
                'visitor' => [
                    'id' => $ev->visitor_id,
                    'fingerprint' => $ev->visitor?->fingerprint ?? 'Unknown',
                    'device' => $ev->visitor?->device ?? '–',
                    'browser' => $ev->visitor?->browser ?? '–',
                ]
            ]);

        $totalLeads = $events->where('event_type', 'contact_click')->count();

        // Sales Performance Analytics
        $statusLogs = \App\Models\ApartmentStatusLog::whereIn('apartment_id', $apartments->pluck('id'))->get();

        $salesVelocity = $statusLogs->where('new_status', 'Verkauft')
            ->groupBy(fn($l) => $l->created_at->format('Y-m'))
            ->map(fn($g) => $g->count())
            ->sortKeys();

        $cancellations = $statusLogs->where('old_status', 'Reserviert')
            ->where('new_status', 'Frei')
            ->count();

        $soldOrReservedLogs = $statusLogs->whereIn('new_status', ['Verkauft', 'Reserviert']);
        $totalDays = 0;
        $countLogs = 0;
        foreach ($soldOrReservedLogs as $log) {
            $apartment = $apartments->firstWhere('id', $log->apartment_id);
            if ($apartment) {
                $days = $apartment->created_at->diffInDays($log->created_at);
                $totalDays += max($days, 0);
                $countLogs++;
            }
        }
        $avgTimeOnMarket = $countLogs > 0 ? round($totalDays / $countLogs) : 0;

        // --- Lead-Scoring ---
        $allScoredVisitors = Visitor::where('project_id', $projectId)
            ->with('events')
            ->orderByDesc('last_visit_at')
            ->get();

        $topLeads = $allScoredVisitors
            ->sortByDesc('lead_score')
            ->take(20)
            ->map(fn($v) => [
                'id' => $v->id,
                'fingerprint' => $v->fingerprint,
                'ip' => $v->ip,
                'browser' => $v->browser,
                'device' => $v->device,
                'os' => $v->os,
                'visit_count' => $v->visit_count,
                'first_visit_at' => $v->first_visit_at,
                'last_visit_at' => $v->last_visit_at,
                'lead_score' => $v->lead_score,
                'lead_label' => $v->lead_label,
                'events_count' => $v->events->count(),
                'apartments_viewed' => $v->events->where('event_type', 'apartment_view')->pluck('target_id')->unique()->count(),
                'favorites_count' => $v->events->where('event_type', 'favorite')->count(),
                'interests' => $v->interests,
                'budget_summary' => $v->budget_summary,
            ])
            ->values();

        $scoreDistribution = [
            'hot' => $allScoredVisitors->where('lead_score', '>=', 70)->count(),
            'warm' => $allScoredVisitors->where('lead_score', '>=', 45)->where('lead_score', '<', 70)->count(),
            'interested' => $allScoredVisitors->where('lead_score', '>=', 20)->where('lead_score', '<', 45)->count(),
            'cold' => $allScoredVisitors->where('lead_score', '<', 20)->count(),
        ];

        // --- Matchmaker ---
        $matchingService = new \App\Services\MatchingService();
        $matches = $matchingService->findTopMatches($projectId);

        $matchSummary = [
            'total_high_intent' => count($matches),
            'top_performing_unit_id' => collect($matches)->groupBy('apartment_id')->map->count()->sortDesc()->keys()->first(),
            'avg_match_score' => collect($matches)->avg('score'),
            'top_performing_unit_name' => $apartments->find(collect($matches)->groupBy('apartment_id')->map->count()->sortDesc()->keys()->first())?->name ?? 'N/A',
        ];

        return response()->json([
            'matchmaker' => $matches,
            'matchmaker_summary' => $matchSummary,
            'summary' => [
                'total_visitors'    => $visitors->count(),
                'total_events'      => $events->count(),
                'returning_visitors'=> $visitors->where('visit_count', '>', 1)->count(),
                'avg_events_per_visitor' => $visitors->count() ? round($events->count() / $visitors->count(), 1) : 0,
                'total_leads'       => $totalLeads,
                'conversion_rate'   => $visitors->count() ? round(($totalLeads / $visitors->count()) * 100, 1) : 0,
            ],
            'portfolio' => [
                'distribution' => $statusDistribution,
                'volume' => [
                    'total' => $totalVolume,
                    'available' => $availableVolume,
                    'sold' => $soldVolume,
                    'reserved' => $reservedVolume,
                ]
            ],
            'sales_performance' => [
                'sales_velocity' => $salesVelocity,
                'cancellations'   => $cancellations,
                'avg_time_on_market_days' => $avgTimeOnMarket,
            ],
            'lead_scoring' => [
                'top_leads' => $topLeads,
                'distribution' => $scoreDistribution,
            ],
            'events_by_type'   => $eventsByType,
            'events_per_day'   => $eventsPerDay,
            'visitors_per_day' => $visitorsPerDay,
            'leads_per_day'    => $leadsPerDay,
            'top_apartments'   => $topApartments,
            'house_interactions' => $houseInteractions,
            'floor_interactions' => $floorInteractions,
            'layer_interactions' => $layerInteractions,
            'filter_usage'     => collect($filterCounts)->take(10),
            'browsers'         => $browsers,
            'devices'          => $devices,
            'countries'        => $countries,
            'languages'        => $languages,
            'referrers'        => $referrers,
            'campaigns'        => $campaigns,
            'recent_visitors'  => $recentVisitors,
            'recent_events'    => $recentEvents,
        ]);
    }
}
