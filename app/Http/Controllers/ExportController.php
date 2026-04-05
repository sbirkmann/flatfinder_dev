<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\Contact;
use App\Models\Visitor;
use App\Models\Apartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExportController extends Controller
{
    /**
     * Export inquiries as CSV.
     */
    public function inquiries(Request $request)
    {
        $teamId = Auth::user()->current_team_id;
        
        $query = Inquiry::where('team_id', $teamId)
            ->with(['project:id,name', 'apartment:id,name'])
            ->latest();

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $inquiries = $query->get();

        $csv = $this->generateCsv(
            ['ID', 'Datum', 'Name', 'E-Mail', 'Telefon', 'Projekt', 'Wohnung', 'Status', 'Quelle', 'Nachricht'],
            $inquiries->map(fn($i) => [
                $i->id,
                $i->created_at->format('d.m.Y H:i'),
                $i->name,
                $i->email,
                $i->phone,
                $i->project?->name,
                $i->apartment?->name,
                $i->status_label,
                $i->source,
                str_replace(["\r\n", "\n"], ' ', $i->message ?? ''),
            ])->toArray()
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="anfragen_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Export contacts as CSV.
     */
    public function contacts(Request $request)
    {
        $teamId = Auth::user()->current_team_id;
        $contacts = Contact::where('team_id', $teamId)->orderBy('name')->get();

        $csv = $this->generateCsv(
            ['ID', 'Name', 'E-Mail', 'Telefon', 'Position', 'Notizen'],
            $contacts->map(fn($c) => [
                $c->id,
                $c->name,
                $c->email,
                $c->phone,
                $c->position,
                $c->notes,
            ])->toArray()
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kontakte_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Export visitors as CSV for a project.
     */
    public function visitors(Request $request, int $projectId)
    {
        $visitors = Visitor::where('project_id', $projectId)
            ->with('events')
            ->orderByDesc('last_visit_at')
            ->get();

        $csv = $this->generateCsv(
            ['ID', 'Fingerprint', 'IP', 'Browser', 'OS', 'Gerät', 'Sprache', 'Besuche', 'Lead Score', 'Lead Label', 'Erster Besuch', 'Letzter Besuch', 'Events'],
            $visitors->map(fn($v) => [
                $v->id,
                $v->fingerprint,
                $v->ip,
                $v->browser,
                $v->os,
                $v->device,
                $v->language,
                $v->visit_count,
                $v->lead_score,
                $v->lead_label,
                $v->first_visit_at?->format('d.m.Y H:i'),
                $v->last_visit_at?->format('d.m.Y H:i'),
                $v->events->count(),
            ])->toArray()
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="besucher_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Export apartment list as CSV.
     */
    public function apartments(Request $request, int $projectId)
    {
        $apartments = Apartment::where('project_id', $projectId)
            ->with(['floor:id,name', 'house:id,name', 'features'])
            ->orderBy('name')
            ->get();

        $csv = $this->generateCsv(
            ['ID', 'Name', 'Haus', 'Etage', 'Zimmer', 'Bäder', 'Fläche m²', 'Preis', 'Warmmiete', 'NK', 'Status', 'Marketing', 'Verfügbar ab', 'Außenfläche m²', 'Features'],
            $apartments->map(fn($a) => [
                $a->id,
                $a->name,
                $a->house?->name,
                $a->floor?->name,
                $a->rooms,
                $a->bathrooms,
                $a->sqm,
                $a->price,
                $a->warm_rent,
                $a->additional_costs,
                $a->status ?? 'Frei',
                $a->marketing_type,
                $a->available_from,
                $a->outdoor_area,
                $a->features->pluck('name')->implode(', '),
            ])->toArray()
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wohnungen_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Generate CSV string from headers and rows.
     */
    private function generateCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }
}
