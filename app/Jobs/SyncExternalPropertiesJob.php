<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\Project;
use App\Models\ExternalProperty;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncExternalPropertiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Project $project
    ) {}

    public function handle(): void
    {
        $integrations = $this->project->integrations()->where('is_active', true)->get();

        foreach ($integrations as $integration) {
            try {
                if ($integration->platform_name === 'onoffice') {
                    $this->syncOnOffice($integration);
                } elseif ($integration->platform_name === 'flowfact') {
                    $this->syncFlowFact($integration);
                } elseif ($integration->platform_name === 'propstack') {
                    $this->syncPropstack($integration);
                }
            } catch (\Exception $e) {
                Log::error("Failed to sync {$integration->platform_name} for project {$this->project->id}: " . $e->getMessage());
            }
        }
    }

    private function syncOnOffice(Integration $integration)
    {
        // 1. Hole Anmeldedaten $integration->credentials
        // 2. onOffice Enterprise API Call (Search Immobilien)
        Log::info("Syncing onOffice data for Integration {$integration->id}");

        // Placeholder Logik für Testdaten, bis die Echte API angebunden ist
        if (app()->environment('local')) {
            ExternalProperty::updateOrCreate(
                ['external_id' => 'onoffice-'.rand(100, 999)],
                [
                    'integration_id' => $integration->id,
                    'name' => 'Importierte onOffice Wohnung',
                    'rooms' => 3,
                    'sqm' => 90,
                    'status' => 'Frei',
                    'price' => 350000,
                    'raw_data' => ['source' => 'onoffice_api_mock']
                ]
            );
        }
    }

    private function syncFlowFact(Integration $integration)
    {
        // FlowFact API Call 
        Log::info("Syncing FlowFact data for Integration {$integration->id}");
    }

    private function syncPropstack(Integration $integration)
    {
        // Propstack API Call
        Log::info("Syncing Propstack data for Integration {$integration->id}");
    }
}
