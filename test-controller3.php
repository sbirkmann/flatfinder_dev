<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$overpassQuery = '[out:json][timeout:25];(nwr["shop"~"supermarket|convenience"](around:2000,50.3766421,7.7489434););out center;';

$opRes = Illuminate\Support\Facades\Http::asForm()->post('https://overpass-api.de/api/interpreter', [
    'data' => $overpassQuery
]);

echo "HTTP AS FORM STATUS: " . $opRes->status() . "\n";
echo substr($opRes->body(), 0, 200) . "\n";
