<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ApartmentConfiguratorOption extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'apartment_configurator_category_id', 
        'label', 
        'price_surcharge',
        'color_hex', 
        'texture_scale', 
        'mesh_names', 
        'is_default'
    ];

    protected $casts = [
        'mesh_names' => 'array',
        'is_default' => 'boolean',
        'texture_scale' => 'float',
        'price_surcharge' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ApartmentConfiguratorCategory::class, 'apartment_configurator_category_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('preview')->singleFile();
        $this->addMediaCollection('texture')->singleFile();
    }
}
