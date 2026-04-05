<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Apartment;
use App\Models\Contact;
use App\Models\Inquiry;
use App\Models\Project;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        $teamId = $user->current_team_id;

        if ($user->is_superadmin) {
            $projectIds = Project::pluck('id')->toArray();
            $projectCount = count($projectIds);
            $inquiryCount = Inquiry::count();
            $contactCount = Contact::count();
            $recentProjects = Project::with('media')->latest()->take(4)->get();
            $recentInquiries = Inquiry::with('project:id,name')->latest()->take(6)->get();
        } else {
            $projects = $user->projects;
            $projectIds = $projects->pluck('id')->toArray();

            $contact = $user->contact;
            $contactProjectIds = $contact ? $contact->projects()->pluck('projects.id')->toArray() : [];
            $contactApartmentIds = $contact ? $contact->apartments()->pluck('apartments.id')->toArray() : [];
            $allowedProjectIds = array_unique(array_merge($projectIds, $contactProjectIds));

            $projectCount = count($projectIds);
            $inquiryCount = Inquiry::where(function ($q) use ($allowedProjectIds, $contactApartmentIds) {
                if (!empty($allowedProjectIds)) $q->orWhereIn('project_id', $allowedProjectIds);
                if (!empty($contactApartmentIds)) $q->orWhereIn('apartment_id', $contactApartmentIds);
                if (empty($allowedProjectIds) && empty($contactApartmentIds)) $q->whereRaw('1 = 0');
            })->count();
            $contactCount = Contact::where('team_id', $teamId)->count();
            $recentProjects = $user->projects()->with('media')->latest()->take(4)->get();
            $recentInquiries = Inquiry::where(function ($q) use ($allowedProjectIds, $contactApartmentIds) {
                if (!empty($allowedProjectIds)) $q->orWhereIn('project_id', $allowedProjectIds);
                if (!empty($contactApartmentIds)) $q->orWhereIn('apartment_id', $contactApartmentIds);
                if (empty($allowedProjectIds) && empty($contactApartmentIds)) $q->whereRaw('1 = 0');
            })->with('project:id,name')->latest()->take(6)->get();
        }

        // Apartment stats
        $totalApartments = Apartment::whereIn('project_id', $projectIds)->count();
        $apartmentsByStatus = Apartment::whereIn('project_id', $projectIds)
            ->selectRaw("COALESCE(status, 'Frei') as status_name, count(*) as total")
            ->groupBy('status_name')
            ->pluck('total', 'status_name')
            ->toArray();

        // Visitor stats (last 30 days)
        $visitorCount30d = Visitor::whereIn('project_id', $projectIds)
            ->where('last_visit_at', '>=', now()->subDays(30))
            ->count();
        $totalVisitors = Visitor::whereIn('project_id', $projectIds)->count();

        // Visitor trend (last 7 days)
        $visitorTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $visitorTrend[] = [
                'date' => now()->subDays($i)->format('d.m.'),
                'count' => Visitor::whereIn('project_id', $projectIds)
                    ->whereDate('last_visit_at', $date)->count(),
            ];
        }

        // Inquiry trend (last 7 days)
        $inquiryTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $inquiryTrend[] = [
                'date' => now()->subDays($i)->format('d.m.'),
                'count' => Inquiry::whereIn('project_id', $projectIds)
                    ->whereDate('created_at', $date)->count(),
            ];
        }

        // Hot leads (top 5 by lead score)
        $hotLeads = Visitor::whereIn('project_id', $projectIds)
            ->with(['events', 'project:id,name'])
            ->where('visit_count', '>', 1)
            ->orderByDesc('last_visit_at')
            ->take(20)
            ->get()
            ->sortByDesc('lead_score')
            ->take(5)
            ->values()
            ->map(fn($v) => [
                'id' => $v->id,
                'fingerprint' => substr($v->fingerprint ?? '', 0, 8) . '...',
                'lead_score' => $v->lead_score,
                'lead_label' => $v->lead_label,
                'visit_count' => $v->visit_count,
                'device' => $v->device,
                'browser' => $v->browser,
                'project_name' => $v->project?->name,
                'last_visit' => $v->last_visit_at?->diffForHumans(),
            ]);

        // Inquiry week comparison
        $newInquiriesThisWeek = Inquiry::whereIn('project_id', $projectIds)
            ->where('created_at', '>=', now()->startOfWeek())->count();
        $newInquiriesLastWeek = Inquiry::whereIn('project_id', $projectIds)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->count();

        // Recent activity
        $recentActivity = ActivityLog::where('team_id', $teamId)
            ->with('user:id,name')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'user_name' => $a->user?->name ?? 'System',
                'action_label' => $a->action_label,
                'subject_type_label' => $a->subject_type_label,
                'subject_label' => $a->subject_label,
                'created_at' => $a->created_at->diffForHumans(),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'projects' => $projectCount,
                'inquiries' => $inquiryCount,
                'contacts' => $contactCount,
                'apartments' => $totalApartments,
                'visitors_30d' => $visitorCount30d,
                'visitors_total' => $totalVisitors,
                'new_inquiries_this_week' => $newInquiriesThisWeek,
                'new_inquiries_last_week' => $newInquiriesLastWeek,
            ],
            'apartmentsByStatus' => $apartmentsByStatus,
            'visitorTrend' => $visitorTrend,
            'inquiryTrend' => $inquiryTrend,
            'hotLeads' => $hotLeads,
            'recentProjects' => $recentProjects,
            'recentInquiries' => $recentInquiries,
            'recentActivity' => $recentActivity,
        ]);
    }
}
