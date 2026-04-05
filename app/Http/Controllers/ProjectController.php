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
        $user = Auth::user();
        if ($user->is_superadmin) {
            $projects = Project::with(['media'])->latest()->get();
        } else {
            $projects = $user->projects()->with(['media'])->latest()->get();
        }
        
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
        $projectData['analytics_settings'] = ['active' => false, 'ga_id' => null, 'events' => []];
        $projectData['calculator_settings'] = ['active' => true, 'interest_rate' => 3.5, 'repayment' => 2.0];

        $project = Project::create($projectData);

        if ($request->hasFile('project_image')) {
            $project->addMediaFromRequest('project_image')->toMediaCollection('project_image');
        }

        if ($request->hasFile('preview_image')) {
            $project->addMediaFromRequest('preview_image')->toMediaCollection('preview_image');
        }

        $project->users()->attach($request->user()->id, ['role' => 'admin']);

        return redirect()->route('projects.show', $project)->with('success', 'Projekt erstellt.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        $user = Auth::user();
        if (!$user->is_superadmin) {
            abort_unless($project->users()->where('user_id', $user->id)->exists(), 403);
        }

        $project->load([
            'users',
            'integrations',
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
            'apartments.contacts',
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

        $teamId = $project->team_id;
        $teamContacts = Contact::where('team_id', $teamId)->orderBy('name')->get();
        
        $externalProperties = \App\Models\ExternalProperty::whereHas('integration', function($q) use ($project) {
            $q->where('project_id', $project->id);
        })->with('integration')->orderBy('name')->get();

        $team = \App\Models\Team::find($teamId);
        $teamUsers = collect();
        if ($team) {
            if ($team->owner) $teamUsers->push($team->owner);
            if ($team->users) {
                foreach($team->users as $u) {
                    if (!$teamUsers->contains('id', $u->id)) $teamUsers->push($u);
                }
            }
        }

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'teamContacts' => $teamContacts,
            'externalProperties' => $externalProperties,
            'teamUsers' => $teamUsers,
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

        // Build OpenGraph meta data for social sharing
        $ogImage = $project->getFirstMediaUrl('preview_image') 
            ?: $project->getFirstMediaUrl('project_image') 
            ?: null;

        $ogMeta = [
            'title' => $project->name,
            'description' => \Illuminate\Support\Str::limit(strip_tags($project->description ?? ''), 160),
            'image' => $ogImage ? url($ogImage) : null,
            'url' => url("/p/{$project->id}"),
            'type' => 'website',
            'site_name' => config('app.name', '3D-Wohnungsfinder'),
        ];

        return Inertia::render('Projects/PublicShow', [
            'project' => $project,
            'isAuthenticated' => auth()->check(),
            'ogMeta' => $ogMeta,
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
            'analytics_settings' => 'nullable|array',
            'calculator_settings' => 'nullable|array',
            'openimmo_settings' => 'nullable|array',
            'auto_tour_settings' => 'nullable|array',
        ]);

        $project->update($validated);

        return redirect()->back()->with('success', 'Projekt aktualisiert.');
    }

    public function updateAutoTour(Request $request, Project $project)
    {
        $validated = $request->validate([
            'auto_tour_settings' => 'required|array',
        ]);

        $project->update(['auto_tour_settings' => $validated['auto_tour_settings']]);
        
        return response()->json(['success' => true, 'auto_tour_settings' => $project->auto_tour_settings]);
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

    // Whitelist of models that can be created/updated via the generic relation endpoint
    private const ALLOWED_RELATION_MODELS = [
        'View', 'House', 'Floor', 'Apartment', 'Layer', 'Frame',
        'Feature', 'Infoframe', 'ProjectContact', 'Room', 'ApartmentImageGroup',
    ];

    public function storeRelation(Request $request, Project $project)
    {
        $validated = $request->validate([
            'model' => 'required|string|in:' . implode(',', self::ALLOWED_RELATION_MODELS),
            'payload' => 'required|array',
        ]);

        $modelClass = '\\App\\Models\\' . $validated['model'];
        if (class_exists($modelClass)) {
            $data = $validated['payload'];
            \Illuminate\Support\Facades\Log::info('ProjectController@storeRelation incoming data:', ['data' => $data, 'id' => $request->id, 'model' => $validated['model']]);
            if (in_array('project_id', app($modelClass)->getFillable())) {
                $data['project_id'] = $project->id;
            }
            if ($validated['model'] === 'View' && !empty($data['is_start_view'])) {
                $modelClass::where('project_id', $project->id)->update(['is_start_view' => false]);
            }

            if ($validated['model'] === 'Apartment') {
                $featureIds = $data['features'] ?? null;
                $contactIds = $data['contact_ids'] ?? null;
                unset($data['features']);
                unset($data['contact_ids']);
            }

            if (isset($request->id)) {
                $record = $modelClass::find($request->id);
                if ($record) $record->update($data);
            } else {
                $record = $modelClass::create($data);
            }
            
            if ($validated['model'] === 'Apartment') {
                if (isset($featureIds)) {
                    $record->features()->sync($featureIds);
                }
                if (isset($contactIds)) {
                    $record->contacts()->sync($contactIds);
                }
            }
            
            // Re-load the model to return full relation data
            if ($validated['model'] === 'Apartment') {
                $record->load(['features', 'roomsList', 'contacts']);
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
            'model' => 'required|string|in:' . implode(',', self::ALLOWED_RELATION_MODELS),
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

    public function optimizeFrameMedia(Request $request, Project $project, \App\Models\Frame $frame)
    {
        set_time_limit(180);
        
        $optimizedCount = 0;
        
        foreach ($frame->media as $media) {
            // We only care about visual images (layer_... or default), not processing maps
            if (!str_starts_with($media->collection_name, 'layer_') && $media->collection_name !== 'default') {
                continue;
            }
            
            $path = $media->getPath();
            if (!file_exists($path)) {
                continue;
            }

            $info = @getimagesize($path);
            if (!$info) {
                continue;
            }
            
            $width = $info[0];
            
            // Skip if already AVIF and size is within limits
            if ($media->mime_type === 'image/avif' && $width <= 2500) {
                continue;
            }
            
            try {
                $tempFilename = sys_get_temp_dir() . '/' . uniqid('opt_') . '.avif';
                
                $img = \imagecreatefromstring(file_get_contents($path));
                if ($img) {
                    if ($width > 2500) {
                        $newHeight = (int) ($info[1] * (2500 / $width));
                        $resized = \imagecreatetruecolor(2500, $newHeight);
                        \imagealphablending($resized, false);
                        \imagesavealpha($resized, true);
                        \imagecopyresampled($resized, $img, 0, 0, 0, 0, 2500, $newHeight, $width, $info[1]);
                        \imagedestroy($img);
                        $img = $resized;
                    }
                    
                    \imageavif($img, $tempFilename, 50); // Quality 50 is fine for 2.5k avif
                    \imagedestroy($img);
                    
                    // Keep track of the original collection name
                    $collection = $media->collection_name;
                    
                    // Replace the original media
                    $frame->addMedia($tempFilename)
                          ->toMediaCollection($collection);
                          
                    $media->delete();
                    $optimizedCount++;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Frame optimization failed: " . $e->getMessage());
            }
        }
        
        return response()->json(['success' => true, 'optimized' => $optimizedCount]);
    }

    public function syncUsers(Request $request, Project $project)
    {
        $validated = $request->validate([
            'users' => 'required|array',
            'users.*.id' => 'required|exists:users,id',
            'users.*.role' => 'required|in:admin,member',
        ]);

        $syncData = [];
        foreach ($validated['users'] as $u) {
            $syncData[$u['id']] = ['role' => $u['role']];
        }

        $project->users()->sync($syncData);

        return redirect()->back()->with('success', 'Projekt-Team aktualisiert.');
    }

    public function reorderFloors(Request $request, Project $project)
    {
        $validated = $request->validate([
            'floors' => 'required|array',
            'floors.*.id' => 'required|exists:floors,id',
            'floors.*.index' => 'required|integer',
        ]);
        foreach ($validated['floors'] as $floorData) {
            \App\Models\Floor::where('id', $floorData['id'])->where('project_id', $project->id)->update(['index' => $floorData['index']]);
        }
        return redirect()->back();
    }

    public function reorderRooms(Request $request, Project $project)
    {
        $validated = $request->validate([
            'rooms' => 'required|array',
            'rooms.*.id' => 'required|exists:rooms,id',
            'rooms.*.index' => 'required|integer',
        ]);
        foreach ($validated['rooms'] as $roomData) {
            \App\Models\Room::where('id', $roomData['id'])->update(['sort_order' => $roomData['index']]);
        }
        return redirect()->back();
    }

    public function reorderLayers(Request $request, Project $project)
    {
        $validated = $request->validate([
            'layers' => 'required|array',
            'layers.*.id' => 'required|exists:layers,id',
            'layers.*.index' => 'required|integer',
        ]);
        foreach ($validated['layers'] as $layerData) {
            \App\Models\Layer::where('id', $layerData['id'])->where('project_id', $project->id)->update(['sort_order' => $layerData['index']]);
        }
        return redirect()->back();
    }
}
