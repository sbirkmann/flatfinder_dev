<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$project = \App\Models\Project::first();
$controller = new \App\Http\Controllers\MapController();
$data = $controller->getMapData($project)->getData(true);

foreach ($data['isochrones'] ?? [] as $iso) {
    echo "Isochrone: {$iso['type']} \n";
    $geomType = $iso['geojson']['features'][0]['geometry']['type'] ?? 'none';
    echo "Geometry Type: {$geomType}\n";
    
    $coordinates = $iso['geojson']['features'][0]['geometry']['coordinates'] ?? [];
    echo "Coordinates structure (levels of nesting): " . getNestingLevel($coordinates) . "\n";
}

function getNestingLevel($arr) {
    if (!is_array($arr)) return 0;
    $maxDepth = 0;
    foreach ($arr as $child) {
        if (is_array($child)) {
            $depth = getNestingLevel($child);
            if ($depth > $maxDepth) $maxDepth = $depth;
        }
    }
    return $maxDepth + 1;
}
