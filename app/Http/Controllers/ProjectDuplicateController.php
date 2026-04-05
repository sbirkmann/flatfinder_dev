<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ProjectDuplicateController extends Controller
{
    public function duplicate(Request $request, Project $project)
    {
        $user = auth()->user();
        if (!$user->is_superadmin) {
            abort_unless($project->users()->where('user_id', $user->id)->wherePivot('role', 'admin')->exists(), 403);
        }

        // 1. Duplicate project
        $newProject = $project->replicate();
        $newProject->name = $project->name . ' (Kopie)';
        $newProject->save();

        // 2. Attach current user as admin
        $newProject->users()->attach($user->id, ['role' => 'admin']);

        // 3. Duplicate layers
        $layerMap = [];
        foreach ($project->layers as $layer) {
            $newLayer = $layer->replicate();
            $newLayer->project_id = $newProject->id;
            $newLayer->save();
            $layerMap[$layer->id] = $newLayer->id;
        }

        // 4. Duplicate views (with layer pivot)
        $viewMap = [];
        foreach ($project->views as $view) {
            $newView = $view->replicate();
            $newView->project_id = $newProject->id;
            $newView->save();
            $viewMap[$view->id] = $newView->id;

            // Duplicate layer-view pivots
            foreach ($view->layers as $layer) {
                if (isset($layerMap[$layer->id])) {
                    $newView->layers()->attach($layerMap[$layer->id]);
                }
            }
        }

        // 5. Duplicate houses
        $houseMap = [];
        foreach ($project->houses as $house) {
            $newHouse = $house->replicate();
            $newHouse->project_id = $newProject->id;
            $newHouse->save();
            $houseMap[$house->id] = $newHouse->id;
        }

        // 6. Duplicate floors
        $floorMap = [];
        foreach ($project->floors as $floor) {
            $newFloor = $floor->replicate();
            $newFloor->project_id = $newProject->id;
            $newFloor->house_id = $houseMap[$floor->house_id] ?? null;
            $newFloor->save();
            $floorMap[$floor->id] = $newFloor->id;
        }

        // 7. Duplicate features
        $featureMap = [];
        foreach ($project->features as $feature) {
            $newFeature = $feature->replicate();
            $newFeature->project_id = $newProject->id;
            $newFeature->save();
            $featureMap[$feature->id] = $newFeature->id;
        }

        // 8. Duplicate apartments (without media)
        foreach ($project->apartments as $apartment) {
            $newApartment = $apartment->replicate();
            $newApartment->project_id = $newProject->id;
            $newApartment->house_id = $houseMap[$apartment->house_id] ?? null;
            $newApartment->floor_id = $floorMap[$apartment->floor_id] ?? null;
            $newApartment->best_view_id = $viewMap[$apartment->best_view_id] ?? null;
            $newApartment->best_frame_id = null; // frames not duplicated
            $newApartment->external_property_id = null;
            $newApartment->save();

            // Duplicate feature assignments
            $newFeatureIds = $apartment->features->pluck('id')
                ->map(fn($id) => $featureMap[$id] ?? null)
                ->filter()
                ->toArray();
            $newApartment->features()->sync($newFeatureIds);

            // Duplicate rooms
            foreach ($apartment->roomsList as $room) {
                $newRoom = $room->replicate();
                $newRoom->apartment_id = $newApartment->id;
                $newRoom->save();
            }
        }

        // 9. Duplicate infoframes
        foreach ($project->infoframes as $infoframe) {
            $newInfoframe = $infoframe->replicate();
            $newInfoframe->project_id = $newProject->id;
            $newInfoframe->save();
        }

        // 10. Duplicate sliders (without media)
        foreach ($project->sliders as $slider) {
            $newSlider = $slider->replicate();
            $newSlider->project_id = $newProject->id;
            $newSlider->save();

            foreach ($slider->slides as $slide) {
                $newSlide = $slide->replicate();
                $newSlide->slider_id = $newSlider->id;
                $newSlide->save();
            }
        }

        ActivityLog::log('created', $newProject, [
            'source' => 'duplicate',
            'original_project_id' => $project->id,
        ], "Kopie von \"{$project->name}\"");

        return redirect()->route('projects.show', $newProject)->with('success', 'Projekt erfolgreich dupliziert!');
    }
}
