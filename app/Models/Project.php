<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory, InteractsWithMedia;
    protected $fillable = [
        'team_id',
        'name',
        'address',
        'zip',
        'city',
        'description',
        'has_google_map',
        'color_settings',
        'initial_slider_id',
        'floating_bar',
        'comparison_settings',
        'poi_settings',
        'contact_form_config',
        'analytics_settings',
        'calculator_settings',
        'openimmo_settings',
        'legal_settings',
        'pdf_settings',
        'auto_tour_settings',
    ];

    protected $casts = [
        'has_google_map' => 'boolean',
        'color_settings' => 'array',
        'floating_bar' => 'array',
        'comparison_settings' => 'array',
        'poi_settings' => 'array',
        'contact_form_config' => 'array',
        'analytics_settings' => 'array',
        'calculator_settings' => 'array',
        'openimmo_settings' => 'array',
        'legal_settings' => 'array',
        'pdf_settings' => 'array',
        'auto_tour_settings' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(Layer::class)->orderBy('sort_order');
    }

    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }

    public function houses(): HasMany
    {
        return $this->hasMany(House::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    public function apartments(): HasMany
    {
        return $this->hasMany(Apartment::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function infoframes(): HasMany
    {
        return $this->hasMany(Infoframe::class);
    }

    public function sliders(): HasMany
    {
        return $this->hasMany(Slider::class)->orderBy('sort');
    }

    public function projectContacts(): HasMany
    {
        return $this->hasMany(ProjectContact::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_project')
            ->withPivot('notify_on_inquiry', 'sort_order')
            ->withTimestamps()
            ->orderBy('contact_project.sort_order');
    }

    public function virtualTours(): HasMany
    {
        return $this->hasMany(VirtualTour::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }
}
