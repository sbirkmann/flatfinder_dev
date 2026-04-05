<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    protected $fillable = [
        'project_id', 'fingerprint', 'ip', 'country', 'city',
        'browser', 'os', 'device', 'language', 'referrer', 'campaign',
        'screen_resolution', 'first_visit_at', 'last_visit_at', 'visit_count',
        'preferences',
    ];

    protected $casts = [
        'first_visit_at' => 'datetime',
        'last_visit_at'  => 'datetime',
        'preferences'    => 'array',
    ];

    protected $appends = ['lead_score', 'lead_label', 'visit_count_display', 'apartments_viewed_count', 'activity_timeline', 'interests', 'budget_summary'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitorEvent::class);
    }

    /**
     * Calculate lead score (0-100) based on visitor behavior.
     * 
     * Scoring factors:
     * - Visit count (returning visitors = more interest)
     * - Number of apartments viewed
     * - Number of favorites added
     * - Tour interactions
     * - Slider/gallery views
     * - Contact form opens
     * - Map opens
     * - Time recency (recent visits score higher)
     */
    public function getLeadScoreAttribute(): int
    {
        if (!$this->relationLoaded('events')) {
            return 0;
        }

        $score = 0;
        $events = $this->events;

        // Visit frequency (max 20 pts)
        $score += min(20, $this->visit_count * 4);

        // Unique apartments viewed (max 20 pts)
        $apartmentsViewed = $events->where('event_type', 'apartment_view')
            ->pluck('target_id')->unique()->count();
        $score += min(20, $apartmentsViewed * 5);

        // Favorites added (max 15 pts)
        $favorites = $events->where('event_type', 'favorite')->count();
        $score += min(15, $favorites * 5);

        // Tour interactions (max 10 pts)
        $tours = $events->where('event_type', 'tour_open')->count();
        $score += min(10, $tours * 3);

        // Slider / gallery opens (max 10 pts)
        $sliders = $events->where('event_type', 'slider_open')->count();
        $score += min(10, $sliders * 2);

        // Contact form open (max 15 pts)
        $contactOpens = $events->where('event_type', 'contact_click')->count();
        $score += min(15, $contactOpens * 8);

        // Map interaction (max 5 pts)
        $mapOpens = $events->where('event_type', 'map_open')->count();
        $score += min(5, $mapOpens * 3);

        // Recency boost (max 5 pts)
        if ($this->last_visit_at) {
            $daysSinceLastVisit = $this->last_visit_at->diffInDays(now());
            if ($daysSinceLastVisit <= 1) $score += 5;
            elseif ($daysSinceLastVisit <= 3) $score += 3;
            elseif ($daysSinceLastVisit <= 7) $score += 1;
        }

        return min(100, $score);
    }

    /**
     * Human-readable lead label.
     */
    public function getLeadLabelAttribute(): string
    {
        $score = $this->lead_score;

        if ($score >= 70) return '🔥 Heißer Lead';
        if ($score >= 45) return '🟠 Warmer Lead';
        if ($score >= 20) return '🟡 Interessiert';
        return '⚪ Besucher';
    }

    public function getVisitCountDisplayAttribute(): int
    {
        return $this->visit_count;
    }

    public function getApartmentsViewedCountAttribute(): int
    {
        if (!$this->relationLoaded('events')) return 0;
        return $this->events->where('event_type', 'apartment_view')->pluck('target_id')->unique()->count();
    }

    public function getActivityTimelineAttribute(): array
    {
        if (!$this->relationLoaded('events')) return [];

        return $this->events->sortByDesc('created_at')->map(function ($event) {
            $icon = '🔵'; $label = 'Interaktion';
            switch ($event->event_type) {
                case 'apartment_view': $icon = '🏠'; $label = 'Wohnung angesehen'; break;
                case 'favorite': $icon = '❤️'; $label = 'Favorisiert'; break;
                case 'tour_open': $icon = '📸'; $label = '3D-Tour geöffnet'; break;
                case 'contact_click': $icon = '✉️'; $label = 'Kontaktformular geöffnet'; break;
                case 'map_open': $icon = '📍'; $label = 'Karte geöffnet'; break;
                case 'filter_used': $icon = '🔍'; $label = 'Filter genutzt'; break;
                case 'slider_open': $icon = '🖼️'; $label = 'Bildergalerie geöffnet'; break;
            }
            return [
                'icon' => $icon,
                'label' => $label,
                'time' => $event->created_at->format('H:i'),
                'date' => $event->created_at->format('d.m.'),
                'type' => $event->event_type,
            ];
        })->values()->toArray();
    }

    public function getInterestsAttribute(): array
    {
        return $this->preferences['tags'] ?? [];
    }
    
    public function getBudgetSummaryAttribute(): string
    {
        if (!isset($this->preferences['budget_avg'])) return 'Unbekannt';
        return number_format($this->preferences['budget_avg'], 0, ',', '.') . ' €';
    }
}

