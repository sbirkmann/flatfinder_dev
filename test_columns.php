<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

var_dump(\Schema::getColumnListing('project_floors'));
var_dump(\Schema::getColumnListing('project_view_frames'));
