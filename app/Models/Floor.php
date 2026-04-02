<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Floor extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['project_id', 'house_id', 'name', 'index', 'polygons', 'points'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function apartments(): HasMany
    {
        return $this->hasMany(Apartment::class);
    }
}
