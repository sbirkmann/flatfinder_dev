<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
if ($user) {
    echo "Found user: " . $user->email . "\n";
    $projects = App\Models\Project::pluck('id');
    $syncData = [];
    foreach($projects as $p) {
        $syncData[$p] = ['role' => 'admin'];
    }
    $user->projects()->syncWithoutDetaching($syncData);
    echo "Synced " . count($syncData) . " projects to user.\n";
} else {
    echo "No user found.\n";
}
