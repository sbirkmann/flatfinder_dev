<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Models\Project::first(); 
$c = App::make(App\Http\Controllers\MapController::class);
$res = $c->getMapData($p)->getData(true);
echo "POIS COUNT: " . count($res['pois']) . "\n";
echo "ISOS COUNT: " . count($res['isochrones']) . "\n";
