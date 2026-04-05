<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$project = \App\Models\Project::find(1);
echo "Project 1 exists: " . ($project ? "YES" : "NO") . "\n";
if ($project) {
    echo "Name: " . $project->name . "\n";
    echo "Address: " . $project->address . ", " . $project->zip . " " . $project->city . "\n";
}

$routes = Route::getRoutes();
$found = false;
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'map-data')) {
        echo "Found route: " . $route->uri() . " [" . implode(',', $route->methods()) . "]\n";
        $found = true;
    }
}
if (!$found) echo "Map-data route NOT FOUND in current route table!\n";
