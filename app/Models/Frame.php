<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Frame extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('optimized')
            ->width(2600)
            ->format('avif');
    }

    protected $fillable = ['view_id', 'index', 'is_stop_frame', 'polygons', 'points'];

    protected $casts = [
        'polygons' => 'array',
        'is_stop_frame' => 'boolean',
    ];

    public function view(): BelongsTo
    {
        return $this->belongsTo(View::class);
    }
}
