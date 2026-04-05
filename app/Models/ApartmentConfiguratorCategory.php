<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApartmentConfiguratorCategory extends Model
{
    protected $fillable = ['apartment_configurator_room_id', 'name', 'type', 'sort_order'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ApartmentConfiguratorRoom::class, 'apartment_configurator_room_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ApartmentConfiguratorOption::class, 'apartment_configurator_category_id')->orderBy('id'); // or define a sort order
    }
}
