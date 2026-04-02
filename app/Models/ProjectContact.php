<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProjectContact extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['project_id', 'name', 'position', 'email', 'phone', 'notes'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
