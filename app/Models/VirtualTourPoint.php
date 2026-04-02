<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class VirtualTourPoint extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['virtual_tour_id', 'name', 'hotspots', 'sort_index', 'minimap_x', 'minimap_y'];
    
    protected $casts = [
        'hotspots' => 'array',
    ];

    public function virtualTour()
    {
        return $this->belongsTo(VirtualTour::class, 'virtual_tour_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('panorama')->singleFile();
    }
}
