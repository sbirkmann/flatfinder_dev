<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Contact;
use App\Models\Inquiry;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Global search across all entities.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $q = $request->input('q');
        $user = Auth::user();
        $teamId = $user->current_team_id;
        $like = "%{$q}%";

        $results = [];

        // Determine accessible project IDs upfront
        if ($user->is_superadmin) {
            $accessibleProjectIds = null; // null = all projects
        } else {
            $accessibleProjectIds = $user->projects()->pluck('projects.id')->toArray();

            // Also include projects from contact relationship
            if ($user->contact) {
                $contactProjectIds = $user->contact->projects()->pluck('projects.id')->toArray();
                $accessibleProjectIds = array_unique(array_merge($accessibleProjectIds, $contactProjectIds));
            }
        }

        // Projects
        $projectQuery = Project::query();
        if ($accessibleProjectIds !== null) {
            $projectQuery->whereIn('id', $accessibleProjectIds);
        }
        $projects = $projectQuery
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('address', 'like', $like);
            })
            ->take(5)->get();

        foreach ($projects as $p) {
            $results[] = [
                'type' => 'project',
                'type_label' => 'Projekt',
                'id' => $p->id,
                'title' => $p->name,
                'subtitle' => collect([$p->address, $p->zip, $p->city])->filter()->implode(', '),
                'url' => "/projects/{$p->id}",
                'icon' => 'folder',
            ];
        }

        // Apartments
        $apartmentQuery = Apartment::query();
        if ($accessibleProjectIds !== null) {
            $apartmentQuery->whereIn('project_id', $accessibleProjectIds);
        }
        $apartments = $apartmentQuery
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('external_property_id', 'like', $like)
                    ->orWhereHas('project', function ($pq) use ($like) {
                        $pq->where('name', 'like', $like);
                    });
            })
            ->with('project:id,name')
            ->take(5)->get();

        foreach ($apartments as $a) {
            $results[] = [
                'type' => 'apartment',
                'type_label' => 'Wohnung',
                'id' => $a->id,
                'title' => $a->name,
                'subtitle' => collect([
                    $a->project?->name,
                    $a->status ?? 'Frei',
                    $a->sqm ? number_format($a->sqm, 1) . ' m²' : null,
                    $a->rooms ? $a->rooms . ' Zi.' : null,
                ])->filter()->implode(' · '),
                'url' => "/projects/{$a->project_id}?apartment={$a->id}",
                'icon' => 'home',
            ];
        }

        // Contacts - search by team
        $contactQuery = Contact::query();
        if ($teamId) {
            $contactQuery->where('team_id', $teamId);
        } elseif ($accessibleProjectIds !== null) {
            // Fallback: search by project relation if no team
            $contactQuery->whereHas('projects', function ($q) use ($accessibleProjectIds) {
                $q->whereIn('projects.id', $accessibleProjectIds);
            });
        }
        $contacts = $contactQuery
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->take(5)->get();

        foreach ($contacts as $c) {
            $results[] = [
                'type' => 'contact',
                'type_label' => 'Kontakt',
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => $c->email ?: $c->phone,
                'url' => '/contacts',
                'icon' => 'user',
            ];
        }

        // Inquiries - search within accessible projects
        $inquiryQuery = Inquiry::query();
        if ($accessibleProjectIds !== null) {
            $inquiryQuery->whereIn('project_id', $accessibleProjectIds);
        }
        $inquiries = $inquiryQuery
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('message', 'like', $like);
            })
            ->with('project:id,name')
            ->take(5)->get();

        foreach ($inquiries as $i) {
            $results[] = [
                'type' => 'inquiry',
                'type_label' => 'Anfrage',
                'id' => $i->id,
                'title' => $i->name,
                'subtitle' => collect([
                    $i->project?->name,
                    $i->created_at?->format('d.m.Y'),
                ])->filter()->implode(' · '),
                'url' => "/inquiries/{$i->id}",
                'icon' => 'chat',
            ];
        }

        return response()->json(['results' => $results]);
    }
}
