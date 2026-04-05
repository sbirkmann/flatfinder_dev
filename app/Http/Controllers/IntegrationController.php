<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IntegrationController extends Controller
{
    public function store(Request $request, \App\Models\Project $project)
    {
        $validated = $request->validate([
            'platform_name' => 'required|string',
            'is_active' => 'boolean'
        ]);

        $project->integrations()->create($validated);

        return back()->with('success', 'Anbindung erstellt.');
    }

    public function update(Request $request, Integration $integration)
    {
        $validated = $request->validate([
            'credentials' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        $integration->update($validated);

        return back()->with('success', 'Anbindung aktualisiert.');
    }

    public function destroy(Integration $integration)
    {
        $integration->delete();

        return back()->with('success', 'Anbindung gelöscht.');
    }
}
