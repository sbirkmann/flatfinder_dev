<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ApartmentController extends Controller
{
    public function syncExternal(Request $request, Apartment $apartment)
    {
        $request->validate([
            'external_property_id' => 'required|exists:external_properties,id'
        ]);

        $apartment->external_property_id = $request->external_property_id;
        $apartment->save();

        $apartment->syncFromExternal();

        return back()->with('success', 'Wohnung erfolgreich synchronisiert.');
    }

    /**
     * Generate and download a high-quality PDF exposé for an apartment.
     */
    public function downloadExpose(Project $project, Apartment $apartment)
    {
        $apartment->load(['features', 'roomsList', 'floor', 'media']);

        // 1. Check if a manual exposé exists (preferred)
        $manualExpose = $apartment->getFirstMedia('expose') ?? $apartment->getFirstMedia('slide_pdf');
        if ($manualExpose) {
            return response()->download($manualExpose->getPath(), $manualExpose->file_name);
        }

        // 2. Resolve settings and template
        $settings = $project->pdf_settings ?? [
            'template' => 'modern',
            'show_features' => true,
            'show_rooms' => true,
            'show_unit_table' => false,
            'footer_text' => '',
        ];

        $template = $settings['template'] ?? 'modern';
        $viewPath = view()->exists("pdf.templates.$template") ? "pdf.templates.$template" : 'pdf.expose';

        // 3. Resolve media
        $logo = null;
        $coverImage = null;
        $logoMedia = $project->getFirstMedia('logo');
        if ($logoMedia && file_exists($logoMedia->getPath())) {
            $logo = 'data:' . $logoMedia->mime_type . ';base64,' . base64_encode(file_get_contents($logoMedia->getPath()));
        }

        $coverMedia = $apartment->getFirstMedia('default') ?? $project->getFirstMedia('project_image');
        if ($coverMedia && file_exists($coverMedia->getPath())) {
            $coverImage = 'data:' . $coverMedia->mime_type . ';base64,' . base64_encode(file_get_contents($coverMedia->getPath()));
        }

        // 4. Resolve units for table if enabled
        $otherUnits = [];
        if ($settings['show_unit_table'] ?? false) {
            $otherUnits = $project->apartments()
                ->where('id', '!=', $apartment->id)
                ->where('status', 'Frei')
                ->orderBy('price')
                ->take(10)
                ->get();
        }

        $primaryColor = $project->color_settings['primary']['base'] ?? '#ab715c';
        $primaryHover = $project->color_settings['primary']['hover'] ?? '#96624f';
        $floorName = $apartment->floor?->name ?? 'EG';
        $contacts = $project->contacts()->get();

        $pdf = Pdf::loadView($viewPath, [
            'project' => $project,
            'apartment' => $apartment,
            'logo' => $logo,
            'coverImage' => $coverImage,
            'primaryColor' => $primaryColor,
            'primaryHover' => $primaryHover,
            'floorName' => $floorName,
            'contacts' => $contacts,
            'settings' => $settings,
            'otherUnits' => $otherUnits,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);

        $filename = str_replace(' ', '_', $project->name) . '_' . str_replace(' ', '_', $apartment->name) . '_Expose.pdf';

        return $pdf->download($filename);
    }
}

