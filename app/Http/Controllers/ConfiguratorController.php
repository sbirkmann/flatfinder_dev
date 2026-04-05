<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ApartmentConfigurator;
use App\Models\ApartmentConfiguratorRoom;
use App\Models\ApartmentConfiguratorCategory;
use App\Models\ApartmentConfiguratorOption;
use Illuminate\Http\Request;

class ConfiguratorController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $configurator = $project->configurators()->create($validated);
        // Load the full tree just in case
        return response()->json($configurator->load('rooms.categories.options.media'));
    }

    public function update(Request $request, Project $project, ApartmentConfigurator $configurator)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $configurator->update($validated);
        return response()->json($configurator->load('rooms.categories.options.media'));
    }

    public function destroy(Project $project, ApartmentConfigurator $configurator)
    {
        $configurator->delete();
        return response()->json(['success' => true]);
    }

    public function storeRoom(Request $request, Project $project, ApartmentConfigurator $configurator)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'glb' => 'nullable|file', // validation for 3D file
            'hdri' => 'nullable|file',
            'preview' => 'nullable|image',
        ]);

        $room = $configurator->rooms()->create([
            'name' => $validated['name'],
            'sort_order' => $configurator->rooms()->count()
        ]);

        if ($request->hasFile('glb')) {
            $room->addMediaFromRequest('glb')->toMediaCollection('glb');
        }
        if ($request->hasFile('hdri')) {
            $room->addMediaFromRequest('hdri')->toMediaCollection('hdri');
        }
        if ($request->hasFile('preview')) {
            $room->addMediaFromRequest('preview')->toMediaCollection('preview');
        }

        return response()->json($room->load('categories.options.media', 'media'));
    }

    public function updateRoom(Request $request, Project $project, ApartmentConfiguratorRoom $room)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'glb' => 'nullable|file',
            'hdri' => 'nullable|file',
            'preview' => 'nullable|image',
        ]);

        $room->update(['name' => $validated['name']]);

        if ($request->hasFile('glb')) {
            $room->clearMediaCollection('glb');
            $room->addMediaFromRequest('glb')->toMediaCollection('glb');
        }
        if ($request->hasFile('hdri')) {
            $room->clearMediaCollection('hdri');
            $room->addMediaFromRequest('hdri')->toMediaCollection('hdri');
        }
        if ($request->hasFile('preview')) {
            $room->clearMediaCollection('preview');
            $room->addMediaFromRequest('preview')->toMediaCollection('preview');
        }

        return response()->json($room->load('categories.options.media', 'media'));
    }

    public function destroyRoom(Project $project, ApartmentConfiguratorRoom $room)
    {
        $room->delete();
        return response()->json(['success' => true]);
    }

    public function storeCategory(Request $request, Project $project, ApartmentConfiguratorRoom $room)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:material,visibility'
        ]);

        $cat = $room->categories()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'sort_order' => $room->categories()->count()
        ]);

        return response()->json($cat->load('options.media'));
    }

    public function updateCategory(Request $request, Project $project, ApartmentConfiguratorCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:material,visibility'
        ]);
        $category->update($validated);
        return response()->json($category->load('options.media'));
    }

    public function destroyCategory(Project $project, ApartmentConfiguratorCategory $category)
    {
        $category->delete();
        return response()->json(['success' => true]);
    }

    public function storeOption(Request $request, Project $project, ApartmentConfiguratorCategory $category)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'price_surcharge' => 'nullable|numeric',
            'color_hex' => 'nullable|string|max:20',
            'texture_scale' => 'nullable|numeric',
            'mesh_names' => 'nullable|json',
            'is_default' => 'nullable|boolean',
            'texture' => 'nullable|image',
            'preview' => 'nullable|image',
        ]);

        $option = $category->options()->create([
            'label' => $validated['label'],
            'price_surcharge' => $validated['price_surcharge'] ?? 0,
            'color_hex' => $validated['color_hex'] ?? null,
            'texture_scale' => $validated['texture_scale'] ?? 1.0,
            'mesh_names' => $validated['mesh_names'] ? json_decode($validated['mesh_names'], true) : [],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        if ($request->hasFile('texture')) {
            $option->addMediaFromRequest('texture')->toMediaCollection('texture');
        }
        if ($request->hasFile('preview')) {
            $option->addMediaFromRequest('preview')->toMediaCollection('preview');
        }

        return response()->json($option->load('media'));
    }

    public function updateOption(Request $request, Project $project, ApartmentConfiguratorOption $option)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'price_surcharge' => 'nullable|numeric',
            'color_hex' => 'nullable|string|max:20',
            'texture_scale' => 'nullable|numeric',
            'mesh_names' => 'nullable|json',
            'is_default' => 'nullable|boolean',
            'texture' => 'nullable|image',
            'preview' => 'nullable|image',
        ]);

        $option->update([
            'label' => $validated['label'],
            'price_surcharge' => $validated['price_surcharge'] ?? 0,
            'color_hex' => $validated['color_hex'] ?? null,
            'texture_scale' => $validated['texture_scale'] ?? 1.0,
            'mesh_names' => $validated['mesh_names'] ? json_decode($validated['mesh_names'], true) : [],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        if ($request->hasFile('texture')) {
            $option->clearMediaCollection('texture');
            $option->addMediaFromRequest('texture')->toMediaCollection('texture');
        }
        if ($request->hasFile('preview')) {
            $option->clearMediaCollection('preview');
            $option->addMediaFromRequest('preview')->toMediaCollection('preview');
        }

        return response()->json($option->load('media'));
    }

    public function destroyOption(Project $project, ApartmentConfiguratorOption $option)
    {
        $option->delete();
        return response()->json(['success' => true]);
    }
}
