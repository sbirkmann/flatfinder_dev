<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

// Check all apartments
$apartments = \App\Models\Apartment::all(['id', 'name', 'project_id']);
echo "All Apartments:\n";
foreach ($apartments as $a) {
    echo "  #{$a->id}: '{$a->name}' (project_id={$a->project_id})\n";
}

// Check all users and their project access
$users = \App\Models\User::all();
echo "\nUsers and project access:\n";
foreach ($users as $u) {
    $pids = $u->projects()->pluck('projects.id')->toArray();
    echo "  #{$u->id}: {$u->name} ({$u->email}) -> projects=" . json_encode($pids) . " superadmin=" . ($u->is_superadmin ? 'YES' : 'no') . " (type=" . gettype($u->is_superadmin) . ")\n";
}

// Test apartment search for each user with query "A1"
echo "\nSearch 'A1' for apartments:\n";
$like = '%A1%';
foreach ($users as $u) {
    if ($u->is_superadmin) {
        $accessibleProjectIds = null;
    } else {
        $accessibleProjectIds = $u->projects()->pluck('projects.id')->toArray();
    }

    $q = \App\Models\Apartment::query();
    if ($accessibleProjectIds !== null) {
        $q->whereIn('project_id', $accessibleProjectIds);
    }
    $results = $q->where(function($query) use ($like) {
        $query->where('name', 'like', $like)
              ->orWhere('status', 'like', $like);
    })->get();
    
    echo "  User #{$u->id} ({$u->name}): found " . $results->count() . " apartments\n";
    foreach ($results as $a) {
        echo "    -> #{$a->id}: {$a->name}\n";
    }
}
