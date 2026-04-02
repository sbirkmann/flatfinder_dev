<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    protected $fillable = [
        'project_id', 'fingerprint', 'ip', 'country', 'city',
        'browser', 'os', 'device', 'language', 'referrer',
        'screen_resolution', 'first_visit_at', 'last_visit_at', 'visit_count',
    ];

    protected $casts = [
        'first_visit_at' => 'datetime',
        'last_visit_at'  => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitorEvent::class);
    }
}
