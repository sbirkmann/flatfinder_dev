<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OpenImmoExportController extends Controller
{
    /**
     * Generate OpenImmo XML export for a project's apartments.
     */
    public function export(Project $project)
    {
        $project->load(['apartments.floor', 'apartments.features', 'apartments.roomsList', 'apartments.media']);

        $settings = $project->openimmo_settings ?? [];

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><openimmo></openimmo>');
        $uebertragung = $xml->addChild('uebertragung');
        $uebertragung->addAttribute('art', 'ONLINE');
        $uebertragung->addAttribute('umfang', 'VOLL');
        $xml->addChild('sender_software', '3D-Wohnungsfinder');
        $xml->addChild('sender_version', '2.0');

        $anbieter = $xml->addChild('anbieter');
        $anbieter->addChild('firma', $this->xmlSafe($settings['firma'] ?? $project->team?->name ?? 'Immobilien'));
        $anbieter->addChild('openimmo_anid', $this->xmlSafe($settings['openimmo_anid'] ?? ('project-' . $project->id)));

        // Anbieter Impressum / Kontakt
        $impressum = $anbieter->addChild('impressum');
        if (!empty($settings['impressum_firmenname'])) {
            $impressum->addChild('firmenname', $this->xmlSafe($settings['impressum_firmenname']));
        }
        if (!empty($settings['impressum_strasse'])) {
            $impressum->addChild('strasse', $this->xmlSafe($settings['impressum_strasse']));
        }
        if (!empty($settings['impressum_plz'])) {
            $impressum->addChild('plz', $this->xmlSafe($settings['impressum_plz']));
        }
        if (!empty($settings['impressum_ort'])) {
            $impressum->addChild('ort', $this->xmlSafe($settings['impressum_ort']));
        }
        if (!empty($settings['impressum_telefon'])) {
            $impressum->addChild('telefon', $this->xmlSafe($settings['impressum_telefon']));
        }
        if (!empty($settings['impressum_email'])) {
            $impressum->addChild('email', $this->xmlSafe($settings['impressum_email']));
        }
        if (!empty($settings['impressum_website'])) {
            $impressum->addChild('website', $this->xmlSafe($settings['impressum_website']));
        }

        foreach ($project->apartments as $apartment) {
            $immobilie = $anbieter->addChild('immobilie');

            // Objektkategorie
            $kategorie = $immobilie->addChild('objektkategorie');
            $nutzungsart = $kategorie->addChild('nutzungsart');
            $nutzungsart->addAttribute('WOHNEN', 'true');

            $vermarktung = $kategorie->addChild('vermarktungsart');
            if ($apartment->marketing_type === 'Miete') {
                $vermarktung->addAttribute('MIETE', 'true');
            } else {
                $vermarktung->addAttribute('KAUF', 'true');
            }

            $objektart = $kategorie->addChild('objektart');
            $wohnung = $objektart->addChild('wohnung');
            $wohnung->addAttribute('wohnungtyp', 'ETAGE');

            // Geo
            $geo = $immobilie->addChild('geo');
            $geo->addChild('strasse', $this->xmlSafe($project->address));
            $geo->addChild('plz', $this->xmlSafe($project->zip));
            $geo->addChild('ort', $this->xmlSafe($project->city));
            $geo->addChild('land')
                ->addAttribute('iso_land', 'DEU');
            if ($apartment->floor) {
                $geo->addChild('etage', (string)($apartment->floor->index ?? 0));
            }

            // Kontaktperson
            $kontakt = $immobilie->addChild('kontaktperson');
            if (!empty($settings['kontakt_anrede'])) {
                $kontakt->addChild('anrede', $this->xmlSafe($settings['kontakt_anrede']));
            }
            if (!empty($settings['kontakt_vorname'])) {
                $kontakt->addChild('vorname', $this->xmlSafe($settings['kontakt_vorname']));
            }
            if (!empty($settings['kontakt_nachname'])) {
                $kontakt->addChild('nachname', $this->xmlSafe($settings['kontakt_nachname']));
            }
            $kontakt->addChild('firma', $this->xmlSafe($settings['firma'] ?? $project->team?->name ?? ''));
            if (!empty($settings['kontakt_telefon'])) {
                $kontakt->addChild('tel_zentrale', $this->xmlSafe($settings['kontakt_telefon']));
            }
            if (!empty($settings['kontakt_email'])) {
                $kontakt->addChild('email_zentrale', $this->xmlSafe($settings['kontakt_email']));
            }
            if (!empty($settings['kontakt_strasse'])) {
                $kontakt->addChild('strasse', $this->xmlSafe($settings['kontakt_strasse']));
            }
            if (!empty($settings['kontakt_plz'])) {
                $kontakt->addChild('plz', $this->xmlSafe($settings['kontakt_plz']));
            }
            if (!empty($settings['kontakt_ort'])) {
                $kontakt->addChild('ort', $this->xmlSafe($settings['kontakt_ort']));
            }

            // Flaechen
            $flaechen = $immobilie->addChild('flaechen');
            if ($apartment->sqm) {
                $flaechen->addChild('wohnflaeche', number_format($apartment->sqm, 2, '.', ''));
            }
            if ($apartment->rooms) {
                $flaechen->addChild('anzahl_zimmer', (string)$apartment->rooms);
            }
            if ($apartment->bathrooms) {
                $flaechen->addChild('anzahl_badezimmer', (string)$apartment->bathrooms);
            }
            if ($apartment->outdoor_area) {
                $flaechen->addChild('balkon_terrasse_flaeche', number_format($apartment->outdoor_area, 2, '.', ''));
            }

            // Zustand Angaben
            $zustand = $immobilie->addChild('zustand_angaben');
            $zustand->addChild('baujahr', $settings['baujahr'] ?? date('Y'));
            $zustand_node = $zustand->addChild('zustand');
            $zustand_node->addAttribute('zustand_art', $settings['zustand_art'] ?? 'ERSTBEZUG');

            // Preise
            $preise = $immobilie->addChild('preise');
            if ($apartment->marketing_type === 'Miete') {
                if ($apartment->price) {
                    $preise->addChild('kaltmiete', number_format($apartment->price, 2, '.', ''));
                }
                if ($apartment->warm_rent) {
                    $preise->addChild('warmmiete', number_format($apartment->warm_rent, 2, '.', ''));
                }
                if ($apartment->additional_costs) {
                    $preise->addChild('nebenkosten', number_format($apartment->additional_costs, 2, '.', ''));
                }
            } else {
                if ($apartment->price) {
                    $preise->addChild('kaufpreis', number_format($apartment->price, 2, '.', ''));
                }
            }
            $waehrung = $preise->addChild('waehrung');
            $waehrung->addAttribute('iso_waehrung', $settings['waehrung'] ?? 'EUR');

            // Verwaltung Objekt
            $verwaltung = $immobilie->addChild('verwaltung_objekt');
            $verwaltung->addChild('objektnr_extern', ($settings['objektnr_prefix'] ?? 'APT-') . $apartment->id);
            $verwaltung->addChild('objektnr_intern', (string)$apartment->id);

            // Status mapping
            $statusMap = [
                'Frei' => 'AKTIV',
                'Reserviert' => 'AKTIV',
                'Verkauft' => 'INAKTIV',
                'Vermietet' => 'INAKTIV',
            ];
            $aktion = $verwaltung->addChild('aktion');
            $aktion->addAttribute('aktionart', $statusMap[$apartment->status ?? 'Frei'] ?? 'AKTIV');

            // Verfügbar ab
            if ($apartment->available_from) {
                $verwaltung->addChild('verfuegbar_ab', $apartment->available_from);
            }

            // Freitexte
            $freitexte = $immobilie->addChild('freitexte');
            $freitexte->addChild('objekttitel', $this->xmlSafe($apartment->name));
            if ($apartment->description) {
                $freitexte->addChild('objektbeschreibung', $this->xmlSafe($apartment->description));
            }

            // Ausstattung
            if ($apartment->features->count()) {
                $ausstattung = $immobilie->addChild('ausstattung');
                foreach ($apartment->features as $feature) {
                    $ausstattung->addChild('sonstiges', $this->xmlSafe($feature->name));
                }
            }

            // Bilder / Anhänge
            if ($apartment->media->count()) {
                $anhaenge = $immobilie->addChild('anhaenge');
                foreach ($apartment->media as $media) {
                    $anhang = $anhaenge->addChild('anhang');
                    $anhang->addAttribute('location', 'EXTERN');
                    $daten = $anhang->addChild('daten');
                    $daten->addChild('pfad', $media->getFullUrl());
                    $anhang->addChild('format', $media->mime_type);
                    $anhang->addChild('anhangtitel', $this->xmlSafe($media->name));
                }
            }
        }

        $xmlString = $xml->asXML();

        $filename = 'openimmo_' . str_replace(' ', '_', $project->name) . '_' . date('Y-m-d') . '.xml';

        return response($xmlString, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function xmlSafe(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
