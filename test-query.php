<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = App::make(App\Http\Controllers\MapController::class); 
$r = new ReflectionMethod($c, 'buildOverpassQueryParts'); 
$r->setAccessible(true); 
$parts = $r->invoke($c, ["supermarket", "school", "transit", "restaurant", "park", "pharmacy", "bank", "fitness", "culture", "gas", "bakery", "parking", "playground", "hospital", "clothing", "hotel", "hairdresser"], 2000, '50.37', '7.74'); 
print_r($parts);
