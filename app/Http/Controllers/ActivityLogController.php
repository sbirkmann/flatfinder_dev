<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $teamId = $user->current_team_id;

        $query = ActivityLog::where('team_id', $teamId)
            ->with('user:id,name,email')
            ->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('subject_type')) {
            $query->where('subject_type', 'App\\Models\\' . $request->subject_type);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('subject_label', 'like', $s)
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', $s));
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'subject_type', 'search']),
        ]);
    }
}
