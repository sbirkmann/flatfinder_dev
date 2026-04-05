<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Frame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FrameMapController extends Controller
{
    public function store(Request $request, Project $project, Frame $frame)
    {
        $request->validate([
            'image' => 'required|string',
            'type'  => 'required|in:depth_map,normal_map'
        ]);

        $base64Image = $request->input('image');
        
        // Ensure format is base64 string
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif
            
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                return response()->json(['error' => 'invalid image type'], 400);
            }
            
            $base64Image = str_replace(' ', '+', $base64Image);
            $imageFile = base64_decode($base64Image);
            
            if ($imageFile === false) {
                return response()->json(['error' => 'base64 decode failed'], 400);
            }
            
            $name = $request->input('type') . '_' . $frame->id . '.' . $type;
            $tmpFilePath = sys_get_temp_dir() . '/' . uniqid() . '_' . $name;
            
            file_put_contents($tmpFilePath, $imageFile);
            
            // Re-upload to media library
            $media = $frame->addMedia($tmpFilePath)
                  ->toMediaCollection($request->input('type'));
                  
            if ($request->filled('target_collection')) {
                $media->setCustomProperty('target_collection', $request->input('target_collection'));
                $media->save();
            }
                  
            return response()->json(['success' => true]);
        }
        
        return response()->json(['error' => 'invalid format'], 400);
    }
    public function destroyAll(Project $project)
    {
        $frameIds = $project->views()->with('frames')->get()->pluck('frames')->flatten()->pluck('id');
        
        \Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_type', 'App\\Models\\Frame')
            ->whereIn('model_id', $frameIds)
            ->whereIn('collection_name', ['depth_map', 'normal_map'])
            ->delete();
            
        return response()->json(['success' => true]);
    }
}
