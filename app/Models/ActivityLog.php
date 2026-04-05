<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'team_id', 'user_id', 'action', 'subject_type', 'subject_id',
        'subject_label', 'properties', 'ip',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Log an activity.
     */
    public static function log(
        string $action,
        $subject = null,
        ?array $properties = null,
        ?string $subjectLabel = null
    ): self {
        $user = auth()->user();

        return static::create([
            'team_id'       => $user?->current_team_id,
            'user_id'       => $user?->id,
            'action'        => $action,
            'subject_type'  => $subject ? get_class($subject) : null,
            'subject_id'    => $subject?->id,
            'subject_label' => $subjectLabel ?? ($subject->name ?? $subject->title ?? null),
            'properties'    => $properties,
            'ip'            => request()?->ip(),
        ]);
    }

    /**
     * Human-readable action label.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'erstellt',
            'updated' => 'aktualisiert',
            'deleted' => 'gelöscht',
            'status_changed' => 'Status geändert',
            'login' => 'angemeldet',
            'inquiry_received' => 'Anfrage erhalten',
            'inquiry_replied' => 'Anfrage beantwortet',
            'export' => 'Export durchgeführt',
            'media_uploaded' => 'Medien hochgeladen',
            'media_deleted' => 'Medien gelöscht',
            default => $this->action,
        };
    }

    /**
     * Human-readable subject type label.
     */
    public function getSubjectTypeLabelAttribute(): string
    {
        return match ($this->subject_type) {
            'App\\Models\\Project' => 'Projekt',
            'App\\Models\\Apartment' => 'Wohnung',
            'App\\Models\\Inquiry' => 'Anfrage',
            'App\\Models\\Contact' => 'Kontakt',
            'App\\Models\\Slider' => 'Slider',
            'App\\Models\\Feature' => 'Ausstattung',
            'App\\Models\\Integration' => 'Integration',
            default => class_basename($this->subject_type ?? ''),
        };
    }
}
