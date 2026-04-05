<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function getOverpassTags($cat)
    {
        switch($cat) {
            case 'supermarket': return '["shop"~"supermarket|convenience"]';
            case 'school': return '["amenity"~"school|kindergarten|college"]';
            case 'transit': return '["public_transport"="stop_position"]';
            case 'restaurant': return '["amenity"~"restaurant|cafe"]';
            case 'park': return '["leisure"="park"]';
            case 'pharmacy': return '["amenity"~"pharmacy|doctors|clinic"]';
            case 'bank': return '["amenity"~"bank|atm"]';
            case 'fitness': return '["leisure"~"fitness_centre|sports_centre"]';
            case 'culture': return '["amenity"~"cinema|theatre|museum|library"]';
            case 'gas': return '["amenity"="fuel"]';
            default: return '';
        }
    }

$lat=50.3766421; $lon=7.7489434; $rad=10000;
$categories = ["supermarket","school","transit","restaurant","park","pharmacy"];
$queryParts = [];
foreach ($categories as $c) {
    if ($tag = getOverpassTags($c)) {
        $queryParts[] = "nwr{$tag}(around:{$rad},{$lat},{$lon});";
    }
}
$queryStr = implode("\n", $queryParts);
$overpassQuery = "[out:json][timeout:25];({$queryStr});out center;";

echo "OVERPASS QUERY:\n$overpassQuery\n\n";

$opRes = Illuminate\Support\Facades\Http::post('https://overpass-api.de/api/interpreter', [
    'data' => $overpassQuery
]);

echo "HTTP STATUS: " . $opRes->status() . "\n";
echo "RESPONSE HEADERS:\n";
print_r($opRes->headers());
echo "RESPONSE BODY (first 200 chars):\n";
echo substr($opRes->body(), 0, 200) . "\n";
