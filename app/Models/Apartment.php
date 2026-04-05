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
        'custom_buttons', 'external_property_id', 'configurator_id'
    ];

    protected $casts = [
        'custom_buttons' => 'array',
    ];

    protected static function booted()
    {
        static::updated(function ($apartment) {
            if ($apartment->isDirty('status')) {
                \App\Models\ApartmentStatusLog::create([
                    'apartment_id' => $apartment->id,
                    'old_status'   => $apartment->getOriginal('status') ?: 'Frei',
                    'new_status'   => $apartment->status,
                ]);

                // Notify project admins about status change
                $project = $apartment->project;
                if ($project) {
                    $admins = $project->users()->wherePivot('role', 'admin')->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new \App\Notifications\ApartmentStatusChangedNotification(
                            $apartment,
                            $apartment->getOriginal('status') ?: 'Frei',
                            $apartment->status
                        ));
                    }
                }

                // Activity log
                \App\Models\ActivityLog::log('status_changed', $apartment, [
                    'old' => $apartment->getOriginal('status') ?: 'Frei',
                    'new' => $apartment->status,
                ], $apartment->name);
            }
        });
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApartmentStatusLog::class)->orderBy('created_at', 'desc');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(ApartmentConfigurator::class, 'configurator_id');
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

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'apartment_contact')
            ->withPivot('notify_on_inquiry', 'sort_order')
            ->withTimestamps();
    }

    public function roomsList(): HasMany
    {
        return $this->hasMany(Room::class)->orderBy('sort_order');
    }

    public function externalProperty(): BelongsTo
    {
        return $this->belongsTo(ExternalProperty::class);
    }

    public function syncFromExternal()
    {
        if (!$this->external_property_id || !$this->externalProperty) {
            return;
        }

        $ext = $this->externalProperty;
        
        $this->update(array_filter([
            'name' => $ext->name,
            'rooms' => $ext->rooms,
            'bathrooms' => $ext->bathrooms,
            'sqm' => $ext->sqm,
            'marketing_type' => $ext->marketing_type,
            'status' => $ext->status,
            'price' => $ext->price,
            'warm_rent' => $ext->warm_rent,
            'available_from' => $ext->available_from,
            'outdoor_area' => $ext->outdoor_area,
            'additional_costs' => $ext->additional_costs,
            'description' => $ext->description,
        ], fn($value) => !is_null($value)));
    }
}
