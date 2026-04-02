<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ContactController extends Controller
{
    private function teamId(): int
    {
        return Auth::user()->currentTeam->id;
    }

    public function index()
    {
        $contacts = Contact::where('team_id', $this->teamId())
            ->with('media')
            ->withCount('projects')
            ->orderBy('name')
            ->get();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'nullable|email|max:200',
            'phone'    => 'nullable|string|max:50',
            'position' => 'nullable|string|max:100',
            'notes'    => 'nullable|string|max:2000',
        ]);

        $contact = Contact::create(array_merge($data, ['team_id' => $this->teamId()]));

        return back()->with('success', 'Kontakt angelegt.');
    }

    public function update(Request $request, Contact $contact)
    {
        abort_unless($contact->team_id === $this->teamId(), 403);

        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'nullable|email|max:200',
            'phone'    => 'nullable|string|max:50',
            'position' => 'nullable|string|max:100',
            'notes'    => 'nullable|string|max:2000',
        ]);

        $contact->update($data);

        return back()->with('success', 'Kontakt aktualisiert.');
    }

    public function destroy(Contact $contact)
    {
        abort_unless($contact->team_id === $this->teamId(), 403);
        $contact->delete();
        return back()->with('success', 'Kontakt gelöscht.');
    }

    /** Attach a contact to a project */
    public function attachToProject(Request $request, Project $project)
    {
        $data = $request->validate([
            'contact_id'        => 'required|exists:contacts,id',
            'notify_on_inquiry' => 'boolean',
        ]);

        $contact = Contact::findOrFail($data['contact_id']);
        abort_unless($contact->team_id === $this->teamId(), 403);

        $project->contacts()->syncWithoutDetaching([
            $data['contact_id'] => ['notify_on_inquiry' => $data['notify_on_inquiry'] ?? false],
        ]);

        return back()->with('success', 'Kontakt zugeordnet.');
    }

    /** Detach a contact from a project */
    public function detachFromProject(Request $request, Project $project, Contact $contact)
    {
        abort_unless($contact->team_id === $this->teamId(), 403);
        $project->contacts()->detach($contact->id);
        return back()->with('success', 'Kontakt entfernt.');
    }

    /** Toggle notify flag */
    public function toggleNotify(Request $request, Project $project, Contact $contact)
    {
        abort_unless($contact->team_id === $this->teamId(), 403);

        $pivot = $project->contacts()->where('contact_id', $contact->id)->first()?->pivot;
        if ($pivot) {
            $project->contacts()->updateExistingPivot($contact->id, [
                'notify_on_inquiry' => !$pivot->notify_on_inquiry,
            ]);
        }

        return back();
    }
}
