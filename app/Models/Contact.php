<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contact extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['team_id', 'name', 'email', 'phone', 'position', 'notes'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'contact_project')
            ->withPivot('notify_on_inquiry', 'sort_order')
            ->withTimestamps();
    }
}
