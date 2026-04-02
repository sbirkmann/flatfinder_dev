<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Slider;
use App\Models\Slide;
use Illuminate\Http\Request;

class SliderController extends Controller
{
    // --- Slider CRUD ---
    public function store(Request $request, Project $project)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $slider = $project->sliders()->create([
            'name' => $request->name,
            'sort' => $project->sliders()->max('sort') + 1,
        ]);
        return back()->with('success', 'Slider erstellt.');
    }

    public function update(Request $request, Slider $slider)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $slider->update(['name' => $request->name]);
        return back()->with('success', 'Slider aktualisiert.');
    }

    public function destroy(Slider $slider)
    {
        $slider->delete();
        return back()->with('success', 'Slider gelöscht.');
    }

    // --- Slide CRUD ---
    public function storeSlide(Request $request, Slider $slider)
    {
        $request->validate([
            'type'         => 'required|in:image,infoframe,iframe,pdf',
            'title'        => 'nullable|string|max:255',
            'infoframe_id' => 'nullable|exists:infoframes,id',
            'iframe_url'   => 'nullable|url|max:1000',
        ]);

        $slide = $slider->slides()->create([
            'type'         => $request->type,
            'title'        => $request->title,
            'infoframe_id' => $request->infoframe_id,
            'iframe_url'   => $request->iframe_url,
            'sort'         => $slider->slides()->max('sort') + 1,
        ]);

        if ($request->hasFile('image')) {
            $slide->addMediaFromRequest('image')->toMediaCollection('slide_image');
        }

        if ($request->hasFile('pdf')) {
            $slide->addMediaFromRequest('pdf')->toMediaCollection('slide_pdf');
        }

        return back()->with('success', 'Slide erstellt.');
    }

    public function updateSlide(Request $request, Slide $slide)
    {
        $request->validate([
            'type'         => 'required|in:image,infoframe,iframe,pdf',
            'title'        => 'nullable|string|max:255',
            'infoframe_id' => 'nullable|exists:infoframes,id',
            'iframe_url'   => 'nullable|url|max:1000',
        ]);

        $slide->update($request->only('type', 'title', 'infoframe_id', 'iframe_url'));

        if ($request->hasFile('image')) {
            $slide->clearMediaCollection('slide_image');
            $slide->addMediaFromRequest('image')->toMediaCollection('slide_image');
        }

        if ($request->hasFile('pdf')) {
            $slide->clearMediaCollection('slide_pdf');
            $slide->addMediaFromRequest('pdf')->toMediaCollection('slide_pdf');
        }

        return back()->with('success', 'Slide aktualisiert.');
    }

    public function destroySlide(Slide $slide)
    {
        $slide->delete();
        return back()->with('success', 'Slide gelöscht.');
    }
}
