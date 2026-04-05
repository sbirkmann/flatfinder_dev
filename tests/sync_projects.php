<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$projects = \App\Models\Project::all();
$count = 0;
foreach ($projects as $project) {
    $team = \App\Models\Team::find($project->team_id);
    if ($team && $team->owner) {
        $project->users()->syncWithoutDetaching([$team->owner->id => ['role' => 'admin']]);
        $count++;
    }
}

// Also let's automatically add users when they create a project by updating ProjectController later.
echo "Synced $count projects to their team owners.\n";
