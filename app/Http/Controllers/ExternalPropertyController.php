<?php

namespace App\Http\Controllers;

use App\Models\ExternalProperty;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExternalPropertyController extends Controller
{
    public function dispatchSync(Request $request, \App\Models\Project $project)
    {
        \App\Jobs\SyncExternalPropertiesJob::dispatch($project);
        
        return back()->with('success', 'Import der externen Immobilien angestoßen.');
    }
}
