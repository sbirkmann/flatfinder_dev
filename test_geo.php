<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$addressStr = "Kluftenbäume 7, 56337 Arzbach";
echo "Geocoding: " . $addressStr . "\n";

$res = \Illuminate\Support\Facades\Http::withHeaders(['Accept-Language' => 'de', 'User-Agent' => 'AntigravityAgent'])
    ->get('https://nominatim.openstreetmap.org/search', [
        'format' => 'json',
        'q' => $addressStr,
        'limit' => 1
    ]);

echo "NomRes Status: " . $res->status() . "\n";
print_r($res->json());

$addressStrCountry = "Kluftenbäume 7, 56337 Arzbach, Deutschland";
echo "Geocoding with country: " . $addressStrCountry . "\n";

$res2 = \Illuminate\Support\Facades\Http::withHeaders(['Accept-Language' => 'de', 'User-Agent' => 'AntigravityAgent'])
    ->get('https://nominatim.openstreetmap.org/search', [
        'format' => 'json',
        'q' => $addressStrCountry,
        'limit' => 1
    ]);

print_r($res2->json());
