<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Apartment;
use App\Models\ApartmentConfigurator;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
    /**
     * Generates a PDF summary of the user's configurator choices.
     * This is an open endpoint (used from the frontend).
     */
    public function generateConfiguratorPdf(Request $request, Apartment $apartment)
    {
        $validated = $request->validate([
            'selections' => 'required|array',
            'total_surcharge' => 'required|numeric'
        ]);

        $apartment->load('project');
        $project = $apartment->project;
        $configurator = ApartmentConfigurator::with(['rooms.categories.options.media'])
                            ->find($apartment->configurator_id);

        if (!$configurator) {
            abort(404, 'Configurator not found.');
        }

        $pdf = Pdf::loadView('pdf.configurator', [
            'apartment' => $apartment,
            'project' => $project,
            'configurator' => $configurator,
            'selections' => $validated['selections'],
            'totalSurcharge' => $validated['total_surcharge'],
        ]);

        return $pdf->stream('Konfiguration_' . $apartment->name . '.pdf');
    }

    /**
     * Generates the backend exposè PDF for a given project or specific apartment.
     * Accessible by admins/owners in the backend.
     */
    public function generateExposePdf(Request $request, Project $project, Apartment $apartment = null)
    {
        $project->load('media', 'apartments.media');
        if ($apartment) {
            $apartment->load('media', 'floors');
        }

        $pdfSettings = $project->pdf_settings ?? [];
        
        $pdf = Pdf::loadView('pdf.expose', [
            'project' => $project,
            'apartment' => $apartment,
            'settings' => $pdfSettings,
        ]);

        $filename = $apartment 
            ? 'Expose_' . $apartment->name . '.pdf' 
            : 'Expose_Projekt_' . $project->name . '.pdf';

        return $pdf->stream($filename);
    }
}
