<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    private const ALLOWED_MEDIA_MODELS = [
        'project', 'apartment', 'frame', 'slide', 'contact',
        'projectcontact', 'virtualtour', 'virtualtourpoint',
        'slider', 'apartmentimagegroup', 'house', 'floor',
    ];

    public function store(Request $request, $model, $id)
    {
        // Validate model name against whitelist
        if (!in_array(strtolower($model), self::ALLOWED_MEDIA_MODELS)) {
            abort(403, 'Model not allowed for media upload');
        }

        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            $error = $request->hasFile('file') ? $request->file('file')->getErrorMessage() : 'Keine Datei empfangen (evtl. zu groß für php.ini Limit).';
            return response()->json(['message' => 'Upload fehlgeschlagen: ' . $error], 422);
        }

        $request->validate([
            'collection' => 'nullable|string',
            'file' => 'required|file|max:51200|mimes:jpeg,jpg,png,gif,webp,avif,svg,pdf,mp4,webm',
        ]);
        
        $modelClass = '\\App\\Models\\' . \Illuminate\Support\Str::studly($model);
        if (!class_exists($modelClass)) {
            abort(404, 'Model not found');
        }

        $record = $modelClass::findOrFail($id);
        $collectionName = $request->collection ?? 'default';

        // Clear existing media for Frame layers before uploading a new one
        if (strtolower($model) === 'frame' && (str_starts_with($collectionName, 'layer_') || $collectionName === 'default')) {
            $record->clearMediaCollection($collectionName);
        }

        $media = $record->addMediaFromRequest('file')->toMediaCollection($collectionName);
        
        // Auto-delete associated depth maps if this is a frame layer
        if (strtolower($model) === 'frame' && (str_starts_with($collectionName, 'layer_') || $collectionName === 'default')) {
             $record->media()
                  ->where('collection_name', 'depth_map')
                  ->where('custom_properties->target_collection', $collectionName)
                  ->delete();
        }
        
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
        $collectionName = $media->collection_name;
        $modelType = $media->model_type;
        $modelId = $media->model_id;
        
        $media->delete();
        
        // Auto-delete associated depth maps if this is a frame layer
        if ($modelType === 'App\\Models\\Frame' && (str_starts_with($collectionName, 'layer_') || $collectionName === 'default')) {
            \Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_type', $modelType)
                  ->where('model_id', $modelId)
                  ->where('collection_name', 'depth_map')
                  ->where('custom_properties->target_collection', $collectionName)
                  ->delete();
        }
        
        return redirect()->back()->with('success', 'Bild gelöscht');
    }
}
