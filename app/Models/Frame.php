<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Frame extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('depth_map');
        $this->addMediaCollection('normal_map');
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('optimized')
            ->width(2600)
            ->format('avif');
            
        $this->addMediaConversion('thumb')
            ->width(400)
            ->format('jpeg');
    }

    protected $fillable = ['view_id', 'index', 'is_stop_frame', 'polygons', 'points', 'is_north'];

    protected $casts = [
        'polygons' => 'array',
        'is_stop_frame' => 'boolean',
        'is_north' => 'boolean',
    ];

    public function view(): BelongsTo
    {
        return $this->belongsTo(View::class);
    }
}
