<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slider extends Model
{
    protected $fillable = ['project_id', 'name', 'sort'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class)->orderBy('sort');
    }
}
