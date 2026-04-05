<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    protected $fillable = [
        'team_id', 'project_id', 'house_id', 'apartment_id', 'visitor_id',
        'name', 'email', 'phone', 'message',
        'status', 'source', 'notes', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function team(): BelongsTo      { return $this->belongsTo(Team::class); }
    public function project(): BelongsTo   { return $this->belongsTo(Project::class); }
    public function house(): BelongsTo     { return $this->belongsTo(House::class); }
    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function visitor(): BelongsTo   { return $this->belongsTo(\App\Models\Visitor::class); }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'new'         => 'Neu',
            'in_progress' => 'In Bearbeitung',
            'done'        => 'Erledigt',
            'rejected'    => 'Abgelehnt',
            default       => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'new'         => 'blue',
            'in_progress' => 'amber',
            'done'        => 'green',
            'rejected'    => 'red',
            default       => 'gray',
        };
    }
}
