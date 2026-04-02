<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Apartment extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'project_id', 'house_id', 'floor_id', 'best_view_id', 'best_frame_id',
        'name', 'rooms', 'bathrooms', 'sqm', 'marketing_type', 'status', 'price', 'warm_rent',
        'available_from', 'outdoor_area', 'additional_costs', 'description', 'virtual_tour_url', 'external_contact_url',
        'custom_buttons'
    ];

    protected $casts = [
        'custom_buttons' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function bestView(): BelongsTo
    {
        return $this->belongsTo(View::class, 'best_view_id');
    }

    public function bestFrame(): BelongsTo
    {
        return $this->belongsTo(Frame::class, 'best_frame_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    public function imageGroups(): HasMany
    {
        return $this->hasMany(ApartmentImageGroup::class)->orderBy('sort_order');
    }

    public function roomsList(): HasMany
    {
        return $this->hasMany(Room::class);
    }

}
