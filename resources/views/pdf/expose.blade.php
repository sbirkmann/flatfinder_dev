<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $project->name }} – {{ $apartment->name }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1a1a1a; background: #fff; font-size: 12px; line-height: 1.5; }

        /* --- Cover Page --- */
        .cover { position: relative; width: 100%; height: 100vh; overflow: hidden; page-break-after: always; }
        .cover-bg { width: 100%; height: 100%; object-fit: cover; }
        .cover-overlay {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.3) 60%, transparent 100%);
            padding: 60px 50px 50px;
            color: #fff;
        }
        .cover-logo { max-height: 40px; max-width: 180px; object-fit: contain; margin-bottom: 20px; }
        .cover-title { font-size: 36px; font-weight: 900; letter-spacing: -1px; margin-bottom: 6px; }
        .cover-subtitle { font-size: 16px; font-weight: 400; opacity: 0.8; margin-bottom: 4px; }
        .cover-badge {
            display: inline-block; background: {{ $primaryColor }}; color: #fff;
            padding: 6px 18px; border-radius: 999px; font-weight: 800; font-size: 12px;
            margin-top: 16px; letter-spacing: 0.5px;
        }

        /* --- Content Pages --- */
        .page { padding: 50px; page-break-after: always; min-height: 100vh; position: relative; }
        .page:last-child { page-break-after: auto; }
        .page-header { border-bottom: 2px solid #eee; padding-bottom: 12px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-header h2 { font-size: 22px; font-weight: 900; color: #111; letter-spacing: -0.5px; }
        .page-header .project-name { font-size: 10px; color: #999; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        /* --- Data Table --- */
        .data-grid { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .data-grid td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .data-grid tr:last-child td { border-bottom: none; }
        .data-grid .label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.5px; width: 40%; }
        .data-grid .value { font-size: 14px; font-weight: 800; color: #222; }

        /* --- Feature Badges --- */
        .features { display: flex; flex-wrap: wrap; gap: 8px; margin: 20px 0; }
        .feature-badge {
            display: inline-block; background: #f5f0ec; color: #7a5a48;
            padding: 5px 14px; border-radius: 999px; font-size: 11px; font-weight: 700;
            border: 1px solid #e8ddd5;
        }

        /* --- Room Table --- */
        .room-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .room-table th { background: #f8f6f4; padding: 10px 14px; text-align: left; font-size: 10px; font-weight: 800; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5; }
        .room-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
        .room-table tr:nth-child(even) { background: #fcfaf9; }
        .room-table .total td { font-weight: 900; border-top: 2px solid #ddd; background: #f5f0ec; }

        /* --- Price Highlight --- */
        .price-box {
            background: linear-gradient(135deg, {{ $primaryColor }}, {{ $primaryHover }});
            color: #fff; border-radius: 12px; padding: 24px 30px; margin: 20px 0;
            text-align: center;
        }
        .price-box .price-label { font-size: 11px; font-weight: 700; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .price-box .price-value { font-size: 32px; font-weight: 900; letter-spacing: -1px; }
        .price-box .price-sub { font-size: 11px; opacity: 0.6; margin-top: 6px; }

        /* --- Footer --- */
        .page-footer {
            position: absolute; bottom: 20px; left: 50px; right: 50px;
            border-top: 1px solid #eee; padding-top: 10px;
            font-size: 9px; color: #bbb; display: flex; justify-content: space-between;
        }

        /* --- Contact Box --- */
        .contact-box { background: #f8f6f4; border: 1px solid #e8ddd5; border-radius: 12px; padding: 20px 24px; margin-top: 30px; }
        .contact-box h4 { font-size: 13px; font-weight: 900; color: {{ $primaryColor }}; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .contact-row { display: flex; gap: 12px; align-items: center; margin-bottom: 8px; }
        .contact-row .contact-label { font-size: 10px; color: #999; font-weight: 700; text-transform: uppercase; width: 60px; }
        .contact-row .contact-value { font-size: 12px; font-weight: 600; color: #333; }

        /* --- Description --- */
        .description { font-size: 13px; line-height: 1.8; color: #444; margin: 20px 0; }

        /* --- Disclaimer --- */
        .disclaimer { font-size: 9px; color: #bbb; line-height: 1.6; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }

        /* Status Badge */
        .status-badge {
            display: inline-block; padding: 4px 14px; border-radius: 999px;
            font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .status-frei { background: #dcf0d5; color: #3f6327; }
        .status-reserviert { background: #fff3cd; color: #856404; }
        .status-verkauft { background: #f8d7da; color: #721c24; }
        .status-vermietet { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    {{-- =================== COVER PAGE =================== --}}
    <div class="cover">
        @if($coverImage)
            <img class="cover-bg" src="{{ $coverImage }}" alt="Cover" />
        @else
            <div style="width:100%;height:100%;background:linear-gradient(135deg, {{ $primaryColor }}, {{ $primaryHover }});"></div>
        @endif
        <div class="cover-overlay">
            @if($logo)
                <img class="cover-logo" src="{{ $logo }}" alt="Logo" />
            @endif
            <div class="cover-title">{{ $apartment->name }}</div>
            <div class="cover-subtitle">{{ $project->name }}</div>
            @if($project->address || $project->city)
                <div class="cover-subtitle">{{ $project->address }} · {{ $project->zip }} {{ $project->city }}</div>
            @endif
            <div class="cover-badge">
                @if($apartment->marketing_type === 'Miete')
                    Zur Miete
                @else
                    Zum Kauf
                @endif
                · {{ $apartment->rooms }} Zimmer · {{ number_format($apartment->sqm, 1, ',', '.') }} m²
            </div>
        </div>
    </div>

    {{-- =================== DATA PAGE =================== --}}
    <div class="page">
        <div class="page-header">
            <h2>Objektübersicht</h2>
            <span class="project-name">{{ $project->name }}</span>
        </div>

        <div class="price-box">
            <div class="price-label">
                {{ $apartment->marketing_type === 'Miete' ? 'Bruttomiete pro Monat' : 'Kaufpreis' }}
            </div>
            <div class="price-value">
                @if($apartment->marketing_type === 'Miete' && $apartment->warm_rent)
                    {{ number_format($apartment->warm_rent, 0, ',', '.') }} €
                @elseif($apartment->price)
                    {{ number_format($apartment->price, 0, ',', '.') }} €
                @else
                    auf Anfrage
                @endif
            </div>
            @if($apartment->marketing_type === 'Miete' && $apartment->price)
                <div class="price-sub">Kaltmiete: {{ number_format($apartment->price, 0, ',', '.') }} € · NK: {{ number_format($apartment->additional_costs ?? 0, 0, ',', '.') }} €</div>
            @endif
        </div>

        <table class="data-grid">
            <tr>
                <td class="label">Bezeichnung</td>
                <td class="value">{{ $apartment->name }}</td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td class="value">
                    <span class="status-badge status-{{ strtolower($apartment->status ?? 'frei') }}">
                        {{ $apartment->status ?? 'Frei' }}
                    </span>
                </td>
            </tr>
            <tr>
                <td class="label">Fläche</td>
                <td class="value">{{ number_format($apartment->sqm, 2, ',', '.') }} m²</td>
            </tr>
            <tr>
                <td class="label">Zimmer</td>
                <td class="value">{{ $apartment->rooms }}</td>
            </tr>
            <tr>
                <td class="label">Geschoss</td>
                <td class="value">{{ $floorName }}</td>
            </tr>
            @if($apartment->available_from)
            <tr>
                <td class="label">Bezugstermin</td>
                <td class="value">{{ $apartment->available_from }}</td>
            </tr>
            @endif
            @if($apartment->marketing_type)
            <tr>
                <td class="label">Vermarktungsart</td>
                <td class="value">{{ $apartment->marketing_type }}</td>
            </tr>
            @endif
        </table>

        @if(($settings['show_features'] ?? true) && $apartment->features && $apartment->features->count())
            <h4 style="font-size: 13px; font-weight: 900; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">Ausstattung</h4>
            <div class="features">
                @foreach($apartment->features as $feature)
                    <span class="feature-badge">
                        @if($feature->icon) {{ $feature->icon }} @endif
                        {{ $feature->name }}
                    </span>
                @endforeach
            </div>
        @endif

        @if(($settings['show_rooms'] ?? true) && $apartment->roomsList && $apartment->roomsList->count())
            <h4 style="font-size: 13px; font-weight: 900; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin: 30px 0 10px;">Raumaufteilung</h4>
            <table class="room-table">
                <thead>
                    <tr><th>Raum</th><th style="text-align:right;">Fläche</th></tr>
                </thead>
                <tbody>
                    @php $totalRoomSqm = 0; @endphp
                    @foreach($apartment->roomsList as $room)
                        <tr>
                            <td>{{ $room->name }}</td>
                            <td style="text-align:right;">{{ $room->sqm ? number_format($room->sqm, 2, ',', '.') . ' m²' : '–' }}</td>
                        </tr>
                        @php $totalRoomSqm += floatval($room->sqm); @endphp
                    @endforeach
                    <tr class="total">
                        <td>Gesamt</td>
                        <td style="text-align:right;">{{ number_format($totalRoomSqm, 2, ',', '.') }} m²</td>
                    </tr>
                </tbody>
            </table>
        @endif

        @if($project->description)
            <h4 style="font-size: 13px; font-weight: 900; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin: 30px 0 10px;">Projekt-Beschreibung</h4>
            <div class="description">{!! nl2br(e($project->description)) !!}</div>
        @endif

        {{-- Unit Table --}}
        @if(($settings['show_unit_table'] ?? false) && count($otherUnits))
            <h4 style="font-size: 13px; font-weight: 900; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin: 40px 0 10px;">Weitere verfügbare Wohnungen</h4>
            <table class="room-table">
                <thead>
                    <tr>
                        <th>Bezeichnung</th>
                        <th style="text-align:center;">Zimmer</th>
                        <th style="text-align:center;">Fläche</th>
                        <th style="text-align:right;">Preis</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($otherUnits as $unit)
                        <tr>
                            <td>{{ $unit->name }}</td>
                            <td style="text-align:center;">{{ $unit->rooms }}</td>
                            <td style="text-align:center;">{{ number_format($unit->sqm, 2, ',', '.') }} m²</td>
                            <td style="text-align:right;">{{ $unit->price ? number_format($unit->price, 0, ',', '.') . ' €' : 'auf Anfrage' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($contacts->count())
            <div class="contact-box">
                <h4>Ihre Ansprechpartner</h4>
                @foreach($contacts as $contact)
                    <div class="contact-row">
                        <span class="contact-label">Name</span>
                        <span class="contact-value">{{ $contact->name }}</span>
                    </div>
                    @if($contact->email)
                    <div class="contact-row">
                        <span class="contact-label">E-Mail</span>
                        <span class="contact-value">{{ $contact->email }}</span>
                    </div>
                    @endif
                    @if($contact->phone)
                    <div class="contact-row">
                        <span class="contact-label">Telefon</span>
                        <span class="contact-value">{{ $contact->phone }}</span>
                    </div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="disclaimer">
            @if(!empty($settings['footer_text']))
                {!! nl2br(e($settings['footer_text'])) !!}
            @else
                Alle Angaben sind ohne Gewähr. Irrtümer und Zwischenverkauf vorbehalten. Grundrisse und Visualisierungen sind nicht maßstabsgetreu und können von der tatsächlichen Ausführung abweichen.
            @endif
            <br>Erstellt am {{ now()->format('d.m.Y') }} · {{ $project->name }}
        </div>

        <div class="page-footer">
            <span>{{ $project->name }} · {{ $apartment->name }}</span>
            <span>Erstellt am {{ now()->format('d.m.Y H:i') }}</span>
        </div>
    </div>

</body>
</html>
