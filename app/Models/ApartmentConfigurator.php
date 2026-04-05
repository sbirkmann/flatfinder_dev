<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApartmentConfigurator extends Model
{
    protected $fillable = ['project_id', 'name'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function apartments(): HasMany
    {
        return $this->hasMany(Apartment::class, 'configurator_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ApartmentConfiguratorRoom::class, 'apartment_configurator_id')->orderBy('sort_order');
    }
}
