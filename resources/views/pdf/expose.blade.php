<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Exposé - {{ $apartment ? $apartment->name : $project->name }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 0; font-size: 14px; background: #fff;}
        
        .page { padding: 40px 50px; position: relative; min-height: 100vh; page-break-after: always; box-sizing: border-box; }
        .page-cover { padding: 0; position: relative; height: 100vh; overflow: hidden; page-break-after: always; }
        
        .cover-image { width: 100%; height: 60vh; object-fit: cover; background-color: #f3f4f6; }
        .cover-content { padding: 50px; }
        .cover-logo { max-height: 80px; margin-bottom: 20px; }
        .cover-title { font-size: 38px; font-weight: 900; color: #111827; margin: 0 0 10px 0; }
        .cover-subtitle { font-size: 20px; color: #ab715c; margin: 0; font-weight: normal; }
        .cover-address { font-size: 16px; color: #6b7280; margin-top: 20px; }
        
        .header { border-bottom: 2px solid #ab715c; padding-bottom: 15px; margin-bottom: 30px; overflow: hidden; }
        .header img { max-height: 40px; float: left; }
        .header-title { float: right; font-size: 16px; font-weight: bold; color: #111827; margin-top: 10px;}
        
        h2 { font-size: 24px; color: #111827; border-left: 4px solid #ab715c; padding-left: 15px; margin-top: 0; margin-bottom: 20px; }
        p { line-height: 1.6; color: #4b5563; }
        
        /* Key Facts Array */
        .facts-grid { width: 100%; display: table; border-collapse: collapse; margin-bottom: 40px; }
        .fact-box { display: table-cell; width: 25%; background: #f9fafb; border: 1px solid #e5e7eb; padding: 15px; text-align: center; }
        .fact-label { font-size: 11px; text-transform: uppercase; color: #ab715c; font-weight: bold; letter-spacing: 1px; margin-bottom: 5px; }
        .fact-val { font-size: 18px; font-weight: 900; color: #111827; }
        
        /* Layout Grid */
        table.layout { width: 100%; border-collapse: collapse; }
        table.layout td { vertical-align: top; width: 50%; padding-right: 20px; }
        table.layout td:last-child { padding-right: 0; padding-left: 20px; }
        
        .image-preview { width: 100%; max-height: 250px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; }
        
        /* Details Table */
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.data-table th, table.data-table td { padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: left; }
        table.data-table th { font-weight: bold; color: #6b7280; font-size: 13px; width: 40%; }
        table.data-table td { font-weight: bold; color: #111827; font-size: 14px; }
        
        .footer { position: fixed; bottom: 20px; left: 50px; right: 50px; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; text-align: center; }
    </style>
</head>
<body>

    @php
        // Try getting project image
        $projectImage = null;
        if(isset($project->media)) {
            $m = $project->media->where('collection_name', 'project_image')->first();
            if($m) $projectImage = str_replace(url('/'), public_path(''), $m->getUrl());
        }
        
        // Try getting logo
        $logo = null;
        if(isset($project->media)) {
            $m = $project->media->where('collection_name', 'logo')->first();
            if($m) $logo = str_replace(url('/'), public_path(''), $m->getUrl());
        }

        // Apartment specific
        $aptImage = null;
        $aptFloorplan = null;
        if($apartment && isset($apartment->media)) {
            $m = $apartment->media->where('collection_name', 'preview_image')->first();
            if($m) $aptImage = str_replace(url('/'), public_path(''), $m->getUrl());
            else $aptImage = $projectImage;
            
            $m = $apartment->media->where('collection_name', 'floorplan_image')->first();
            if($m) $aptFloorplan = str_replace(url('/'), public_path(''), $m->getUrl());
        }
    @endphp

    <!-- COVER PAGE -->
    <div class="page-cover">
        @if($apartment && $aptImage)
            <img src="{{ $aptImage }}" class="cover-image" alt="Titelbild">
        @elseif($projectImage)
            <img src="{{ $projectImage }}" class="cover-image" alt="Titelbild">
        @else
            <div class="cover-image"></div>
        @endif
        
        <div class="cover-content">
            @if($logo)
                <img src="{{ $logo }}" class="cover-logo" alt="Logo">
            @endif
            
            <h1 class="cover-title">{{ $apartment ? $apartment->name : $project->name }}</h1>
            <h2 class="cover-subtitle">{{ $apartment ? 'Exposé für Ihre Traumwohnung' : 'Exposé Projektübersicht' }}</h2>
            
            <p class="cover-address">
                <strong>{{ $project->name }}</strong><br>
                {{ $project->address }}<br>
                {{ $project->zip }} {{ $project->city }}
            </p>
        </div>
    </div>
    
    <!-- DETAILS PAGE -->
    <div class="page">
        <div class="header">
            @if($logo)
                <img src="{{ $logo }}" alt="Logo">
            @endif
            <div class="header-title">{{ $apartment ? $apartment->name : $project->name }}</div>
        </div>
        
        @if($apartment)
            <h2>Wohnungsdetails</h2>
            
            <div class="facts-grid">
                <div class="fact-box">
                    <div class="fact-label">Fläche</div>
                    <div class="fact-val">{{ round($apartment->sqm, 1) }} m²</div>
                </div>
                <div class="fact-box">
                    <div class="fact-label">Zimmer</div>
                    <div class="fact-val">{{ $apartment->rooms }}</div>
                </div>
                <div class="fact-box">
                    <div class="fact-label">Geschoss</div>
                    <div class="fact-val">{{ $apartment->floor_id ? ($project->floors->where('id', $apartment->floor_id)->first()->name ?? 'EG') : 'EG' }}</div>
                </div>
                <div class="fact-box">
                    <div class="fact-label">Preis</div>
                    <div class="fact-val">
                        @if($apartment->price)
                            {{ number_format($apartment->price, 0, ',', '.') }} {{ env('APP_CURRENCY', 'CHF') }}
                        @else
                            A.A.
                        @endif
                    </div>
                </div>
            </div>
            
            <table class="layout">
                <tr>
                    <td>
                        <table class="data-table">
                            <tr>
                                <th>Status</th>
                                <td>{{ $apartment->status ?? 'Verfügbar' }}</td>
                            </tr>
                            <tr>
                                <th>Angebotstyp</th>
                                <td>{{ $apartment->marketing_type ?? 'Kauf' }}</td>
                            </tr>
                            <tr>
                                <th>Verfügbar ab</th>
                                <td>{{ $apartment->available_from ?? 'Sofort' }}</td>
                            </tr>
                        </table>
                        
                        @if($settings['show_features'] ?? true)
                            @if(isset($settings['footer_text']) && count($settings) > 0)
                                <h3>Beschreibung</h3>
                                <p>{{ $settings['footer_text'] ?? 'Dieses Exposé enthält grundlegende Informationen zur Immobilie.' }}</p>
                            @endif
                        @endif
                    </td>
                    <td>
                        @if($aptFloorplan)
                            <div style="font-weight:bold; color:#ab715c; margin-bottom: 10px; font-size:12px; text-transform:uppercase;">Grundriss</div>
                            <img src="{{ $aptFloorplan }}" style="width:100%; border:1px solid #eee; padding:10px;">
                        @endif
                    </td>
                </tr>
            </table>

            @if($aptFloorplan)
                <!-- Break and large floorplan if requested -->
                <div style="page-break-before: always;"></div>
                <div class="header">
                    @if($logo)
                        <img src="{{ $logo }}" alt="Logo">
                    @endif
                    <div class="header-title">Detail-Grundriss | {{ $apartment->name }}</div>
                </div>
                <div style="text-align:center; padding: 20px;">
                    <img src="{{ $aptFloorplan }}" style="max-width: 100%; max-height: 70vh;">
                </div>
            @endif

        @else
            <!-- Project Fallback View -->
            <h2>{{ $project->name }}</h2>
            <p>{{ $project->description }}</p>
            
            @if(isset($settings['footer_text']))
                <p>{{ $settings['footer_text'] }}</p>
            @endif

            <!-- Project apartments list -->
            <table class="data-table">
                <tr>
                    <th>Wohnung</th>
                    <th>Fläche</th>
                    <th>Zimmer</th>
                    <th>Preis</th>
                </tr>
                @foreach($project->apartments as $apt)
                <tr>
                    <td>{{ $apt->name }}</td>
                    <td>{{ round($apt->sqm, 1) }} m²</td>
                    <td>{{ $apt->rooms }}</td>
                    <td>{{ $apt->price ? number_format($apt->price, 0, ',', '.') . ' '.env('APP_CURRENCY', 'CHF') : 'Auf Anfrage' }}</td>
                </tr>
                @endforeach
            </table>
        @endif
        
    </div>

    <div class="footer">
        {!! nl2br(e($settings['footer_text'] ?? '')) !!} <br>
        Generiert am {{ date('d.m.Y') }} &copy; {{ env('APP_NAME') }}
    </div>

</body>
</html>
