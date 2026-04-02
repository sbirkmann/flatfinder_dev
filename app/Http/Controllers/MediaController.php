<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    public function store(Request $request, $model, $id)
    {
        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            $error = $request->hasFile('file') ? $request->file('file')->getErrorMessage() : 'Keine Datei empfangen (evtl. zu groß für php.ini Limit).';
            return response()->json(['message' => 'Upload fehlgeschlagen: ' . $error], 422);
        }

        $request->validate([
            'collection' => 'nullable|string',
        ]);
        
        $modelClass = '\\App\\Models\\' . \Illuminate\Support\Str::studly($model);
        if (!class_exists($modelClass)) {
            abort(404, 'Model not found');
        }

        $record = $modelClass::findOrFail($id);
        $collectionName = $request->collection ?? 'default';

        $media = $record->addMediaFromRequest('file')->toMediaCollection($collectionName);
        
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'media' => $media]);
        }
        
        return redirect()->back()->with('success', 'Bild hochgeladen');
    }

    public function update(Request $request, Media $media)
    {
        $request->validate([
            'custom_properties' => 'required|array',
        ]);
        
        $media->custom_properties = $request->custom_properties;
        $media->save();

        return redirect()->back()->with('success', 'Eigenschaften gespeichert');
    }

    public function destroy(Media $media)
    {
        $media->delete();
        return redirect()->back()->with('success', 'Bild gelöscht');
    }
}
