<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Project;
use App\Models\VirtualTour;
use App\Models\VirtualTourPoint;

class VirtualTourController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $project->virtualTours()->create($validated);
        return back();
    }
    public function update(Request $request, VirtualTour $virtualTour)
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $virtualTour->update($validated);
        return back();
    }

    public function destroy(VirtualTour $virtualTour)
    {
        $virtualTour->delete();
        return back();
    }

    public function storePoint(Request $request, VirtualTour $virtualTour)
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $sortIndex = $virtualTour->points()->max('sort_index') + 1;
        $virtualTour->points()->create([
            'name' => $validated['name'],
            'sort_index' => $sortIndex,
        ]);
        return back();
    }

    public function updatePoint(Request $request, VirtualTourPoint $virtualTourPoint)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'hotspots' => 'nullable|array',
            'minimap_x' => 'nullable|numeric',
            'minimap_y' => 'nullable|numeric'
        ]);
        $virtualTourPoint->update($request->only('name', 'hotspots', 'minimap_x', 'minimap_y'));

        if ($request->wantsJson() || $request->isXmlHttpRequest()) {
            return response()->json(['success' => true]);
        }
        
        return back();
    }

    public function destroyPoint(VirtualTourPoint $virtualTourPoint)
    {
        $virtualTourPoint->delete();
        return back();
    }
}
