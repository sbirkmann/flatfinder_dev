<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class View extends Model
{
    protected $fillable = ['project_id', 'name', 'is_start'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function layers(): BelongsToMany
    {
        return $this->belongsToMany(Layer::class);
    }

    public function frames(): HasMany
    {
        return $this->hasMany(Frame::class)->orderBy('index');
    }
}
