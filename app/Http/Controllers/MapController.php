<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    private $catNames = [
        'supermarket' => 'Einkauf',
        'school' => 'Bildung / Kita',
        'transit' => 'ÖPNV',
        'restaurant' => 'Restaurant / Gastro',
        'park' => 'Natur / Park',
        'pharmacy' => 'Arzt / Apotheke',
        'bank' => 'Bank / Geldautomat',
        'fitness' => 'Fitness / Sport',
        'culture' => 'Kultur / Kino',
        'gas' => 'Tankstelle',
        'bakery' => 'Bäckerei',
        'parking' => 'Parken',
        'playground' => 'Spielplatz',
        'hospital' => 'Krankenhaus',
        'clothing' => 'Bekleidung / Mode',
        'hotel' => 'Hotel / Unterkunft',
        'hairdresser' => 'Friseur / Kosmetik',
    ];


    private function buildOverpassQueryParts($categories, $rad, $latStr, $lonStr)
    {
        $keys = [
            'shop' => [],
            'amenity' => [],
            'leisure' => [],
            'tourism' => [],
            'public_transport' => []
        ];

        foreach ($categories as $c) {
            if ($c === 'supermarket')
                $keys['shop'][] = 'supermarket|convenience';
            elseif ($c === 'school')
                $keys['amenity'][] = 'school|kindergarten|college';
            elseif ($c === 'transit')
                $keys['public_transport'][] = 'stop_position';
            elseif ($c === 'restaurant')
                $keys['amenity'][] = 'restaurant|cafe';
            elseif ($c === 'park')
                $keys['leisure'][] = 'park';
            elseif ($c === 'pharmacy')
                $keys['amenity'][] = 'pharmacy|doctors|clinic';
            elseif ($c === 'bank')
                $keys['amenity'][] = 'bank|atm';
            elseif ($c === 'fitness')
                $keys['leisure'][] = 'fitness_centre|sports_centre';
            elseif ($c === 'culture')
                $keys['amenity'][] = 'cinema|theatre|museum|library';
            elseif ($c === 'gas')
                $keys['amenity'][] = 'fuel';
            elseif ($c === 'bakery')
                $keys['shop'][] = 'bakery';
            elseif ($c === 'parking')
                $keys['amenity'][] = 'parking';
            elseif ($c === 'playground')
                $keys['leisure'][] = 'playground';
            elseif ($c === 'hospital')
                $keys['amenity'][] = 'hospital';
            elseif ($c === 'clothing')
                $keys['shop'][] = 'clothes|shoes|boutique';
            elseif ($c === 'hotel')
                $keys['tourism'][] = 'hotel|guest_house|motel';
            elseif ($c === 'hairdresser')
                $keys['shop'][] = 'hairdresser';
        }

        $queryParts = [];
        foreach ($keys as $k => $values) {
            if (!empty($values)) {
                $regex = implode('|', $values);
                if ($k === 'public_transport') {
                    // specific for transit stop position
                    $queryParts[] = "nwr[\"{$k}\"=\"{$regex}\"](around:{$rad},{$latStr},{$lonStr});";
                    $queryParts[] = "nwr[\"highway\"=\"bus_stop\"](around:{$rad},{$latStr},{$lonStr});";
                    $queryParts[] = "nwr[\"railway\"~\"station|platform\"](around:{$rad},{$latStr},{$lonStr});";
                } else {
                    $queryParts[] = "nwr[\"{$k}\"~\"{$regex}\"](around:{$rad},{$latStr},{$lonStr});";
                }
            }
        }
        return $queryParts;
    }

    private function generateFallbackIsochrone($type, $lat, $lon, $rangeInSeconds)
    {
        // Fallback: Generate a simple GeoJSON circle
        $speedMps = match ($type) {
            'walking' => 1.4, // ~5 km/h
            'cycling' => 4.2, // ~15 km/h
            'driving' => 13.9, // ~50 km/h
            default => 1.4
        };

        $radiusMeters = $speedMps * $rangeInSeconds;
        $points = 32;
        $coordinates = [];

        $earthRadius = 6378137;

        for ($i = 0; $i <= $points; $i++) {
            $angle = ($i * 360 / $points);
            $dx = $radiusMeters * cos(deg2rad($angle));
            $dy = $radiusMeters * sin(deg2rad($angle));

            $dLat = $dy / $earthRadius;
            $dLon = $dx / ($earthRadius * cos(deg2rad($lat)));

            $newLat = $lat + rad2deg($dLat);
            $newLon = $lon + rad2deg($dLon);

            $coordinates[] = [$newLon, $newLat];
        }

        return [
            'id' => "iso_{$type}",
            'type' => $type,
            'minutes' => $rangeInSeconds / 60,
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [$coordinates]
                        ]
                    ]
                ]
            ]
        ];
    }

    public function getMapData(Project $project)
    {
        $settings = $project->poi_settings ?? [];
        if (!($settings['active'] ?? true)) {
            return response()->json(['lat' => null, 'lon' => null, 'addressFound' => false, 'pois' => [], 'isochrones' => []]);
        }

        $settingsHash = md5(json_encode($settings) . $project->address . $project->zip . $project->city);
        $cacheKey = "project_map_data_{$project->id}_{$settingsHash}";

        $data = Cache::get($cacheKey);

        if ($data && !empty($settings['categories']) && (empty($data['pois']) || count($data["pois"]) == 0) && $data['addressFound']) {
            $data = null; // force reload if we have categories but no pois in cache
        }

        if (!$data) {
            $data = $this->fetchMapData($project, $settings);

            // Only cache if there was no fetch error, and either we found POIs (or none were requested) or address wasn't found
            if (!($data['fetchError'] ?? false)) {
                if (!$data['addressFound'] || empty($settings['categories']) || !empty($data['pois'])) {
                    Cache::put($cacheKey, $data, now()->addDays(7));
                }
            }
        }

        unset($data['fetchError']);
        return response()->json($data);
    }

    private function fetchMapData(Project $project, $settings)
    {
        $lat = 51.165691;
        $lon = 10.451526;
        $addressFound = false;

        $addressStr = $project->address . ', ' . $project->zip . ' ' . $project->city . ', Deutschland';

        try {
            $nomRes = Http::withHeaders([
                'Accept-Language' => 'de',
                'User-Agent' => 'Wohnungsfinder-Real-Estate-v1'
            ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $addressStr,
                    'limit' => 1
                ]);

            if ($nomRes->successful() && !empty($nomRes->json())) {
                $lat = (float) $nomRes->json()[0]['lat'];
                $lon = (float) $nomRes->json()[0]['lon'];
                $addressFound = true;
            }
        } catch (\Exception $e) {
            // Ignore error
        }

        if (!$addressFound) {
            return ['lat' => $lat, 'lon' => $lon, 'addressFound' => false, 'pois' => [], 'isochrones' => []];
        }

        // 1. Fetch Isochrones
        $isochrones = [];
        $orsKey = config('services.ors.key') ?? env('ORS_API_KEY');
        if (!empty($settings['isochrones'])) {
            foreach ($settings['isochrones'] as $type => $isoSetting) {
                if (!empty($isoSetting['active']) && !empty($isoSetting['minutes'])) {
                    $profile = match ($type) {
                        'walking' => 'foot-walking',
                        'cycling' => 'cycling-regular',
                        'driving' => 'driving-car',
                        default => null
                    };

                    if ($profile) {
                        $range = $isoSetting['minutes'] * 60; // in seconds

                        if (!$orsKey) {
                            $isochrones[] = $this->generateFallbackIsochrone($type, $lat, $lon, $range);
                            continue;
                        }
                        try {
                            $orsRes = Http::withToken($orsKey)
                                ->post("https://api.openrouteservice.org/v2/isochrones/{$profile}", [
                                    'locations' => [[$lon, $lat]], // json_encode handles floats
                                    'range' => [$range]
                                ]);

                            if ($orsRes->successful()) {
                                $isochrones[] = [
                                    'id' => "iso_{$type}",
                                    'type' => $type,
                                    'minutes' => $isoSetting['minutes'],
                                    'geojson' => $orsRes->json()
                                ];
                            } else {
                                \Illuminate\Support\Facades\Log::error("ORS HTTP Failed: " . $orsRes->status() . " | " . $orsRes->body());
                                $isochrones[] = $this->generateFallbackIsochrone($type, $lat, $lon, $range);
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("ORS Fetch Failed: " . $e->getMessage());
                            $isochrones[] = $this->generateFallbackIsochrone($type, $lat, $lon, $range);
                        }
                    }
                }
            }
        }

        $fetchError = false;

        // 2. Fetch POIs
        $pois = [];
        $categories = $settings['categories'] ?? [];
        if (!empty($categories)) {
            // Determine dynamic max radius based on longest active isochrone!
            $maxRad = 0;
            if (!empty($settings['isochrones'])) {
                if (($settings['isochrones']['driving']['active'] ?? false)) {
                    // assume 500m per minute in city traffic instead of 1000m
                    $maxRad = max($maxRad, (($settings['isochrones']['driving']['minutes'] ?? 0) * 500));
                }
                if (($settings['isochrones']['cycling']['active'] ?? false)) {
                    $maxRad = max($maxRad, (($settings['isochrones']['cycling']['minutes'] ?? 0) * 250)); // ~15km/h = 250m/min
                }
                if (($settings['isochrones']['walking']['active'] ?? false)) {
                    $maxRad = max($maxRad, (($settings['isochrones']['walking']['minutes'] ?? 0) * 80)); // ~5km/h = 80m/min
                }

            }
            if ($maxRad == 0)
                $maxRad = 5000; // fallback
            if ($maxRad > 10000)
                $maxRad = 10000; // Cap at 10km instead of 2km to actually allow driving POIs!

            $rad = $maxRad;


            $latStr = number_format($lat, 6, '.', '');
            $lonStr = number_format($lon, 6, '.', '');
            $queryParts = $this->buildOverpassQueryParts($categories, $rad, $latStr, $lonStr);

            if (!empty($queryParts)) {
                $queryStr = implode("\n", $queryParts);
                $overpassQuery = "[out:json][timeout:45];({$queryStr});out center;";


                try {
                    $opRes = Http::asForm()->post('https://overpass-api.de/api/interpreter', [
                        'data' => $overpassQuery
                    ]);

                    if ($opRes->successful()) {
                        $elements = $opRes->json()['elements'] ?? [];
                        \Illuminate\Support\Facades\Log::info("Overpass Success: " . count($elements) . " elements found for project {$project->id}");
                        foreach ($elements as $el) {
                            $cat = null;
                            $tags = $el['tags'] ?? [];

                            if (preg_match('/supermarket|convenience/', $tags['shop'] ?? ''))
                                $cat = 'supermarket';
                            elseif (preg_match('/school|kindergarten|college/', $tags['amenity'] ?? ''))
                                $cat = 'school';
                            elseif (($tags['public_transport'] ?? '') === 'stop_position' || ($tags['highway'] ?? '') === 'bus_stop' || preg_match('/station|platform/', $tags['railway'] ?? ''))
                                $cat = 'transit';
                            elseif (preg_match('/restaurant|cafe/', $tags['amenity'] ?? ''))
                                $cat = 'restaurant';
                            elseif (($tags['leisure'] ?? '') === 'park')
                                $cat = 'park';
                            elseif (preg_match('/pharmacy|doctors|clinic/', $tags['amenity'] ?? ''))
                                $cat = 'pharmacy';
                            elseif (preg_match('/bank|atm/', $tags['amenity'] ?? ''))
                                $cat = 'bank';
                            elseif (preg_match('/fitness_centre|sports_centre/', $tags['leisure'] ?? ''))
                                $cat = 'fitness';
                            elseif (preg_match('/cinema|theatre|museum|library/', $tags['amenity'] ?? ''))
                                $cat = 'culture';
                            elseif (($tags['amenity'] ?? '') === 'fuel')
                                $cat = 'gas';
                            elseif (($tags['shop'] ?? '') === 'bakery')
                                $cat = 'bakery';
                            elseif (($tags['amenity'] ?? '') === 'parking')
                                $cat = 'parking';
                            elseif (($tags['leisure'] ?? '') === 'playground')
                                $cat = 'playground';
                            elseif (($tags['amenity'] ?? '') === 'hospital')
                                $cat = 'hospital';
                            elseif (preg_match('/clothes|shoes|boutique/', $tags['shop'] ?? ''))
                                $cat = 'clothing';
                            elseif (preg_match('/hotel|guest_house|motel/', $tags['tourism'] ?? ''))
                                $cat = 'hotel';
                            elseif (($tags['shop'] ?? '') === 'hairdresser')
                                $cat = 'hairdresser';

                            if ($cat) {
                                $name = $tags['name'] ?? $this->catNames[$cat] ?? 'POI';
                                $clat = $el['lat'] ?? $el['center']['lat'] ?? null;
                                $clon = $el['lon'] ?? $el['center']['lon'] ?? null;
                                if ($clat && $clon) {
                                    $pois[] = [
                                        'id' => "poi_{$el['id']}",
                                        'lat' => $clat,
                                        'lon' => $clon,
                                        'category' => $cat,
                                        'name' => $name,
                                        'label' => $this->catNames[$cat] ?? ''
                                    ];
                                }
                            }
                        }
                    } else {
                        $fetchError = true;
                        \Illuminate\Support\Facades\Log::error("Overpass HTTP Failed: " . $opRes->status() . " | Body: " . $opRes->body());
                    }
                } catch (\Exception $e) {
                    $fetchError = true;
                    \Illuminate\Support\Facades\Log::error("Overpass Fetch Failed: " . $e->getMessage());
                }
            }
        }

        return [
            'lat' => $lat,
            'lon' => $lon,
            'addressFound' => true,
            'pois' => $pois,
            'isochrones' => $isochrones,
            'settings' => $settings,
            'fetchError' => $fetchError,
        ];
    }
}
