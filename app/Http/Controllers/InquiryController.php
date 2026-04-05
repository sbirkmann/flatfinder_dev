<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InquiryController extends Controller
{
    private function teamId(): int
    {
        return Auth::user()->currentTeam->id;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Inquiry::where('team_id', $this->teamId())
            ->with(['project:id,name', 'house:id,name', 'apartment:id,name', 'visitor.events'])
            ->latest();

        if (!$user->is_superadmin) {
            $adminProjectIds = $user->projects()->wherePivot('role', 'admin')->pluck('projects.id')->toArray();
            $contact = $user->contact;
            $contactProjectIds = $contact ? $contact->projects()->pluck('projects.id')->toArray() : [];
            $contactApartmentIds = $contact ? $contact->apartments()->pluck('apartments.id')->toArray() : [];

            $allowedProjectIds = array_unique(array_merge($adminProjectIds, $contactProjectIds));

            $query->where(function ($q) use ($allowedProjectIds, $contactApartmentIds) {
                if (!empty($allowedProjectIds)) {
                    $q->orWhereIn('project_id', $allowedProjectIds);
                }
                if (!empty($contactApartmentIds)) {
                    $q->orWhereIn('apartment_id', $contactApartmentIds);
                }
                
                if (empty($allowedProjectIds) && empty($contactApartmentIds)) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('email', 'like', $s)
                  ->orWhere('phone', 'like', $s)
                  ->orWhere('message', 'like', $s);
            });
        }

        $inquiries = $query->paginate(30)->withQueryString();

        // Summary counts per status
        $counts = Inquiry::where('team_id', $this->teamId())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Projects for filter dropdown
        $projects = Project::where('team_id', $this->teamId())
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inquiries/Index', [
            'inquiries' => $inquiries,
            'counts'    => $counts,
            'projects'  => $projects,
            'filters'   => $request->only(['status', 'project_id', 'search']),
        ]);
    }

    /** Update status (and optionally internal notes) */
    public function updateStatus(Request $request, Inquiry $inquiry)
    {
        abort_unless($inquiry->team_id === $this->teamId(), 403);

        $data = $request->validate([
            'status' => 'required|in:new,in_progress,done,rejected',
            'notes'  => 'nullable|string|max:3000',
        ]);

        $inquiry->update($data);

        // Mark as read
        if (!$inquiry->read_at) {
            $inquiry->update(['read_at' => now()]);
        }

        return back();
    }

    /** Create inquiry from public form */
    public function store(Request $request)
    {
        $data = $request->validate([
            'team_id'      => 'required|exists:teams,id',
            'project_id'   => 'nullable|exists:projects,id',
            'house_id'     => 'nullable|exists:houses,id',
            'apartment_id' => 'nullable|exists:apartments,id',
            'name'         => 'required|string|max:120',
            'email'        => 'nullable|email|max:200',
            'phone'        => 'nullable|string|max:50',
            'message'      => 'nullable|string|max:3000',
            'source'       => 'nullable|string|max:50',
        ]);

        Inquiry::create($data);

        return response()->json(['ok' => true]);
    }

    public function storePublic(Request $request, Project $project)
    {
        $fields = $request->input('fields', []);
        
        // 1. Dynamic Validation Rules
        $rules = [];
        if (is_array($project->contact_form_config) && isset($project->contact_form_config['fields'])) {
            foreach($project->contact_form_config['fields'] as $f) {
                if (!empty($f['validation_rule'])) {
                    $rules['fields.'.$f['id']] = $f['validation_rule'];
                } elseif (!empty($f['required'])) {
                    $rules['fields.'.$f['id']] = 'required';
                }
            }
        }
        if (!empty($rules)) {
            $request->validate($rules, [], ['fields.*' => 'Feld']);
        }

        $name = trim(($fields['first_name'] ?? '') . ' ' . ($fields['last_name'] ?? ''));
        if (empty($name) && isset($fields['name'])) $name = $fields['name'];
        if (empty($name)) $name = 'Unbekannt';

        $email = $fields['email'] ?? null;
        $phone = $fields['phone'] ?? null;

        $messageLines = [];
        foreach($fields as $k => $v) {
            if ($k === '_token' || empty($v) || in_array($k, ['first_name', 'last_name', 'name', 'email', 'phone'])) continue;

            $label = ucfirst(str_replace('_', ' ', $k));
            if (is_array($project->contact_form_config) && isset($project->contact_form_config['fields'])) {
                foreach($project->contact_form_config['fields'] as $f) {
                    if ($f['id'] == $k) $label = $f['label'];
                }
            }
            $messageLines[] = "**{$label}:**\n" . (is_array($v) ? implode(', ', $v) : $v);
        }

        $apartmentId = $request->integer('apartment_id') ?: null;

        $inquiry = Inquiry::create([
            'team_id' => $project->team_id,
            'project_id' => $project->id,
            'apartment_id' => $apartmentId,
            'visitor_id' => $request->integer('visitor_id') ?: null,
            'name' => mb_substr($name, 0, 120),
            'email' => $email,
            'phone' => $phone,
            'message' => implode("\n\n", $messageLines),
            'source' => mb_substr($request->input('source', 'Website'), 0, 50),
        ]);

        // 2. Email Notification Resolving
        $emails = [];
        if (!empty($project->contact_form_config['email_recipients'])) {
            $emails = array_map('trim', explode(',', $project->contact_form_config['email_recipients']));
        } else {
            // First check apartment specific contacts
            $apartmentContacts = [];
            if ($apartmentId) {
                $apartment = \App\Models\Apartment::with('contacts')->find($apartmentId);
                if ($apartment && $apartment->contacts->count() > 0) {
                    $apartmentContacts = $apartment->contacts;
                }
            }

            $contactsToNotify = count($apartmentContacts) > 0 ? $apartmentContacts : $project->contacts;

            foreach($contactsToNotify as $contact) {
                if ($contact->pivot && $contact->pivot->notify_on_inquiry && !empty($contact->email)) {
                    $emails[] = $contact->email;
                }
            }
        }

        $emails = array_filter($emails, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        
        if (!empty($emails)) {
            \Illuminate\Support\Facades\Mail::to($emails)->send(new \App\Mail\InquiryReceived($inquiry));
        }

        return back()->with('success', 'Vielen Dank, Ihre Nachricht wurde erfolgreich gesendet!');
    }

    public function destroy(Inquiry $inquiry)
    {
        abort_unless($inquiry->team_id === $this->teamId(), 403);
        $inquiry->delete();
        return back();
    }

    /**
     * Show a single inquiry detail.
     */
    public function show(Inquiry $inquiry)
    {
        abort_unless($inquiry->team_id === $this->teamId(), 403);

        $inquiry->load(['project:id,name', 'house:id,name', 'apartment:id,name', 'visitor.events']);

        // Mark as read
        if (!$inquiry->read_at) {
            $inquiry->update(['read_at' => now()]);
        }

        return Inertia::render('Inquiries/Show', [
            'inquiry' => $inquiry,
        ]);
    }

    /**
     * Reply to an inquiry via email.
     */
    public function reply(Request $request, Inquiry $inquiry)
    {
        abort_unless($inquiry->team_id === $this->teamId(), 403);

        $data = $request->validate([
            'subject' => 'required|string|max:200',
            'body'    => 'required|string|max:10000',
        ]);

        if (!$inquiry->email) {
            return back()->with('error', 'Diese Anfrage hat keine E-Mail-Adresse.');
        }

        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($inquiry, $data) {
            $message->to($inquiry->email, $inquiry->name)
                ->subject($data['subject'])
                ->html(nl2br(e($data['body'])));
        });

        // Update status
        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'in_progress']);
        }

        // Log
        \App\Models\ActivityLog::log('inquiry_replied', $inquiry, [
            'subject' => $data['subject'],
        ], $inquiry->name);

        return back()->with('success', 'Antwort wurde gesendet.');
    }

    /**
     * Bulk actions on inquiries.
     */
    public function bulkAction(Request $request)
    {
        $data = $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'integer|exists:inquiries,id',
            'action' => 'required|in:mark_done,mark_rejected,delete',
        ]);

        $teamId = $this->teamId();
        $query = Inquiry::whereIn('id', $data['ids'])->where('team_id', $teamId);

        match ($data['action']) {
            'mark_done' => $query->update(['status' => 'done']),
            'mark_rejected' => $query->update(['status' => 'rejected']),
            'delete' => $query->delete(),
        };

        return back()->with('success', count($data['ids']) . ' Anfragen aktualisiert.');
    }
}
