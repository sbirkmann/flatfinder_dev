<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Integration;
use App\Models\ExternalProperty;

echo "Starting test script...\n";

$onoffice = Integration::firstOrCreate(['platform_name' => 'onoffice'], ['is_active' => true]);

$extProp = ExternalProperty::updateOrCreate(
    ['external_id' => 'EXT-12345'],
    [
        'integration_id' => $onoffice->id,
        'name' => 'Musterwohnung mit Balkon',
        'rooms' => 3,
        'bathrooms' => 1,
        'sqm' => 85.5,
        'marketing_type' => 'Verkauf',
        'status' => 'Frei',
        'price' => 345000,
        'available_from' => 'sofort',
        'description' => 'Wunderschöne helle Wohnung im 2. OG mit großem Südbalkon.',
    ]
);

echo "Created test property: " . $extProp->name . "\n";
