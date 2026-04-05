<?php

namespace App\Services;

use App\Models\Visitor;
use App\Models\Apartment;
use Illuminate\Support\Collection;

class MatchingService
{
    /**
     * Get the preference profile for a visitor based on their behavior.
     */
    public function getPreferenceProfile(Visitor $visitor): array
    {
        $events = $visitor->events()
            ->whereIn('event_type', ['apartment_view', 'favorite'])
            ->whereNotNull('target_id')
            ->get();

        if ($events->isEmpty()) {
            return [];
        }

        $apartmentIds = $events->pluck('target_id')->unique();
        $apartments = Apartment::whereIn('id', $apartmentIds)->get();

        if ($apartments->isEmpty()) {
            return [];
        }

        // Favorites weigh more than views (double count)
        $favoriteIds = $events->where('event_type', 'favorite')->pluck('target_id')->toArray();
        
        $weightedApartments = collect();
        foreach ($apartments as $apt) {
            $weightedApartments->push($apt);
            if (in_array($apt->id, $favoriteIds)) {
                $weightedApartments->push($apt); // Double weight for favorites
            }
        }

        return [
            'avg_price' => $weightedApartments->avg('price'),
            'avg_sqm'   => $weightedApartments->avg('sqm'),
            'avg_rooms' => $weightedApartments->avg('rooms'),
            'viewed_ids' => $apartmentIds->toArray(),
        ];
    }

    /**
     * Update the persistent profile of a visitor.
     */
    public function updateVisitorProfile(Visitor $visitor): array
    {
        $profile = $this->getPreferenceProfile($visitor);
        if (empty($profile)) return [];

        $tags = [];
        if ($profile['avg_price'] > 750000) $tags[] = 'Luxus-Suche';
        if ($profile['avg_sqm'] > 120) $tags[] = 'Großraum';
        if ($profile['avg_rooms'] >= 4) $tags[] = 'Familienwohnung';
        if ($profile['avg_rooms'] <= 2 && $profile['avg_price'] < 400000) $tags[] = 'Kompakt/Kapitalanlage';
        
        $visitor->update([
            'preferences' => array_merge($profile, ['tags' => $tags])
        ]);

        return $visitor->preferences;
    }

    /**
     * Calculate a match score (0-100) between a preference profile and an apartment.
     */
    public function calculateMatchScore(array $profile, Apartment $apartment): int
    {
        if (empty($profile)) return 0;

        $scores = [];

        // 1. Price Matching (40% weight)
        if ($profile['avg_price'] > 0 && $apartment->price > 0) {
            $diff = abs($profile['avg_price'] - $apartment->price) / $profile['avg_price'];
            $scores['price'] = max(0, 100 - ($diff * 100));
        }

        // 2. SQM Matching (30% weight)
        if ($profile['avg_sqm'] > 0 && $apartment->sqm > 0) {
            $diff = abs($profile['avg_sqm'] - $apartment->sqm) / $profile['avg_sqm'];
            $scores['sqm'] = max(0, 100 - ($diff * 150)); // Steeper penalty for area mismatch
        }

        // 3. Rooms Matching (30% weight)
        if ($profile['avg_rooms'] > 0 && $apartment->rooms > 0) {
            $diff = abs($profile['avg_rooms'] - $apartment->rooms);
            $scores['rooms'] = max(0, 100 - ($diff * 40)); // High penalty for room count mismatch
        }

        if (empty($scores)) return 0;

        // Weighted Average
        $totalScore = (
            ($scores['price'] ?? 0) * 0.4 +
            ($scores['sqm'] ?? 0) * 0.3 +
            ($scores['rooms'] ?? 0) * 0.3
        );

        return (int) round($totalScore);
    }

    /**
     * Find top matches for a project.
     */
    public function findTopMatches(int $projectId, int $limit = 15): Collection
    {
        $visitors = Visitor::where('project_id', $projectId)
            ->with('events')
            ->get()
            ->filter(fn($v) => $v->lead_score > 30); // Only match somewhat interested visitors

        $vacantApartments = Apartment::where('project_id', $projectId)
            ->whereIn('status', ['Frei', 'Reserviert'])
            ->get();

        $matches = collect();

        foreach ($visitors as $visitor) {
            $profile = !empty($visitor->preferences) ? $visitor->preferences : $this->updateVisitorProfile($visitor);
            if (empty($profile)) continue;

            foreach ($vacantApartments as $apt) {
                // Skip if they already viewed this one significantly? 
                // Actually, viewing it is proof of interest, but let's prioritize new ones.
                $hasViewed = in_array($apt->id, $profile['viewed_ids']);
                
                $score = $this->calculateMatchScore($profile, $apt);
                
                if ($score >= 60) {
                    $matches->push([
                        'visitor_id'    => $visitor->id,
                        'visitor_label' => $visitor->lead_label . " (" . $visitor->browser . ")",
                        'apartment_id'  => $apt->id,
                        'apartment_name'=> $apt->name,
                        'score'         => $score,
                        'reason'        => $this->getMatchReason($profile, $apt),
                        'has_viewed'    => $hasViewed,
                    ]);
                }
            }
        }

        return $matches->sortByDesc('score')->take($limit)->values();
    }

    private function getMatchReason(array $profile, Apartment $apt): string
    {
        $reasons = [];
        if (abs($profile['avg_price'] - $apt->price) < ($profile['avg_price'] * 0.15)) $reasons[] = "Preisniveau passt";
        if (abs($profile['avg_rooms'] - $apt->rooms) < 0.5) $reasons[] = "Zimmeranzahl ideal";
        if (abs($profile['avg_sqm'] - $apt->sqm) < ($profile['avg_sqm'] * 0.2)) $reasons[] = "Größe passt";
        
        return !empty($reasons) ? implode(", ", $reasons) : "Gute allgemeine Übereinstimmung";
    }
}
