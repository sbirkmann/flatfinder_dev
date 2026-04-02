<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Project;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = Project::with(['media'])->latest()->get();
        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Projects/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'project_image' => 'nullable|image|max:10240',
            'preview_image' => 'nullable|image|max:10240',
        ]);

        $projectData = collect($validated)->except(['project_image', 'preview_image'])->toArray();
        $projectData['team_id'] = $request->user()->current_team_id;
        $projectData['comparison_settings'] = ['active' => false, 'fields' => ['name', 'price', 'size', 'rooms', 'status']];
        $projectData['poi_settings'] = ['active' => false, 'radius' => 2000, 'categories' => ['supermarket', 'school', 'transit']];

        $project = Project::create($projectData);

        if ($request->hasFile('project_image')) {
            $project->addMediaFromRequest('project_image')->toMediaCollection('project_image');
        }

        if ($request->hasFile('preview_image')) {
            $project->addMediaFromRequest('preview_image')->toMediaCollection('preview_image');
        }

        return redirect()->route('projects.show', $project)->with('success', 'Projekt erstellt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        $project->load([
            'layers',
            'views.layers',
            'views.frames.media',
            'houses',
            'floors',
            'apartments.features',
            'apartments.bestView',
            'apartments.bestFrame',
            'apartments.media',
            'apartments.imageGroups.media',
            'apartments.features',
            'apartments.roomsList',
            'features',
            'infoframes',
            'sliders.slides.media',
            'sliders.slides.infoframe',
            'projectContacts.media',
            'contacts.media',
            'media',
            'virtualTours.media',
            'virtualTours.points.media'
        ]);

        $teamId = Auth::user()->currentTeam->id;
        $teamContacts = Contact::where('team_id', $teamId)->orderBy('name')->get();

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'teamContacts' => $teamContacts,
        ]);
    }

    public function publicShow(Project $project)
    {
        $project->load([
            'views.layers',
            'views.frames.media',
            'houses.media',
            'floors.media',
            'apartments.features',
            'apartments.bestView',
            'apartments.bestFrame',
            'apartments.media',
            'apartments.imageGroups.media',
            'apartments.roomsList',
            'features',
            'infoframes',
            'sliders.slides.media',
            'sliders.slides.infoframe',
            'projectContacts.media',
            'contacts.media',
            'media',
            'virtualTours.media',
            'virtualTours.points.media'
        ]);

        return Inertia::render('Projects/PublicShow', [
            'project' => $project,
            'isAuthenticated' => auth()->check(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'has_google_map' => 'boolean',
            'color_settings' => 'nullable|array',
            'initial_slider_id' => 'nullable|integer|exists:sliders,id',
            'floating_bar' => 'nullable|array',
            'comparison_settings' => 'nullable|array',
            'poi_settings' => 'nullable|array',
            'contact_form_config' => 'nullable|array',
        ]);

        $project->update($validated);

        return redirect()->back()->with('success', 'Projekt aktualisiert.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect()->route('projects.index');
    }

    public function uploadImages(Request $request, Project $project)
    {
        $request->validate([
            'project_image' => 'nullable|image|max:10240',
            'preview_image' => 'nullable|image|max:10240',
            'project_pdf'   => 'nullable|mimes:pdf|max:20480',
            'logo'          => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp,avif|max:5120',
        ]);

        if ($request->hasFile('project_image')) {
            $project->clearMediaCollection('project_image');
            $project->addMediaFromRequest('project_image')->toMediaCollection('project_image');
        }

        if ($request->hasFile('preview_image')) {
            $project->clearMediaCollection('preview_image');
            $project->addMediaFromRequest('preview_image')->toMediaCollection('preview_image');
        }

        if ($request->hasFile('project_pdf')) {
            $project->clearMediaCollection('project_pdf');
            $project->addMediaFromRequest('project_pdf')->toMediaCollection('project_pdf');
        }

        if ($request->hasFile('logo')) {
            $project->clearMediaCollection('logo');
            $project->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return redirect()->back()->with('success', 'Dateien hochgeladen');
    }

    public function storeRelation(Request $request, Project $project)
    {
        $validated = $request->validate([
            'model' => 'required|string',
            'payload' => 'required|array',
        ]);

        $modelClass = '\\App\\Models\\' . $validated['model'];
        if (class_exists($modelClass)) {
            $data = $validated['payload'];
            if (in_array('project_id', app($modelClass)->getFillable())) {
                $data['project_id'] = $project->id;
            }
            if ($validated['model'] === 'View' && !empty($data['is_start_view'])) {
                $modelClass::where('project_id', $project->id)->update(['is_start_view' => false]);
            }

            if ($validated['model'] === 'Apartment') {
                $featureIds = $data['features'] ?? null;
                unset($data['features']);
            }

            if (isset($request->id)) {
                $record = $modelClass::find($request->id);
                if ($record) $record->update($data);
            } else {
                $record = $modelClass::create($data);
            }
            
            if ($validated['model'] === 'Apartment' && isset($featureIds)) {
                $record->features()->sync($featureIds);
            }
            
            // Re-load the model to return full relation data
            if ($validated['model'] === 'Apartment') {
                $record->load(['features', 'roomsList']);
            }
        }

        if ($request->wantsJson()) {
            return response()->json($record);
        }

        return redirect()->back();
    }

    public function deleteRelation(Request $request, Project $project, $id)
    {
        $validated = $request->validate([
            'model' => 'required|string',
        ]);
        
        $modelClass = '\\App\\Models\\' . $validated['model'];
        if (class_exists($modelClass)) {
            $record = $modelClass::find($id);
            if ($record) {
                $record->delete();
            }
        }
        return redirect()->back();
    }

    public function toggleViewLayer(Request $request, Project $project, \App\Models\View $view)
    {
        $validated = $request->validate([
            'layer_id' => 'required|exists:layers,id',
        ]);

        $layerId = $validated['layer_id'];
        $wasAttached = $view->layers()->where('layer_id', $layerId)->exists();

        if ($wasAttached) {
            $view->layers()->detach($layerId);
            
            // Delete associated layer media in ALL frames belonging to this view
            foreach ($view->frames as $frame) {
                $frame->clearMediaCollection('layer_' . $layerId);
            }
        } else {
            $view->layers()->attach($layerId);
        }
        
        return redirect()->back();
    }
}
