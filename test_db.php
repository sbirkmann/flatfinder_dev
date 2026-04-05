<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/projects/5/relation/store', 'POST', [
    'model' => 'Frame',
    'id' => 15,
    'payload' => [
        'polygons' => [['id' => 'foo', 'points' => []]],
        'points' => []
    ]
]);

$controller = new \App\Http\Controllers\ProjectController();
// Project object is needed for route binding!
$project = \App\Models\Project::find(5);

$response = $controller->storeRelation($request, $project);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Content: " . $response->getContent() . "\n";

$frame = \App\Models\Frame::find(15);
echo "Frame Polygons: ";
print_r($frame->polygons);

