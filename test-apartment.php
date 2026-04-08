<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$apt = \App\Models\Apartment::has('imageGroups')->with('imageGroups.media')->first();
if ($apt) {
    echo json_encode($apt->toArray());
} else {
    echo 'none';
}
