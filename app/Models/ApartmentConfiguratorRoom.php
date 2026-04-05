<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ApartmentConfiguratorRoom extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['apartment_configurator_id', 'name', 'sort_order'];

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(ApartmentConfigurator::class, 'apartment_configurator_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ApartmentConfiguratorCategory::class, 'apartment_configurator_room_id')->orderBy('sort_order');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('glb')->singleFile();
        $this->addMediaCollection('hdri')->singleFile();
        $this->addMediaCollection('preview')->singleFile();
    }
}
