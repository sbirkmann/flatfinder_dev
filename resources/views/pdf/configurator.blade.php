<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Zusammenfassung der Konfiguration - {{ $apartment->name }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; margin: 20px; font-size: 14px; }
        .header { border-bottom: 2px solid #ab715c; padding-bottom: 15px; margin-bottom: 30px; }
        .header img { max-height: 50px; }
        .header-title { float: right; font-size: 24px; font-weight: bold; color: #111827; }
        .project-title { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        
        .room-section { margin-bottom: 40px; page-break-inside: avoid; }
        .room-header { background-color: #f3f4f6; padding: 10px 15px; font-size: 18px; font-weight: bold; margin-bottom: 15px; border-left: 4px solid #ab715c; }
        
        table { w-full; width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; }
        td.price { text-align: right; font-weight: bold; }
        td.free { text-align: right; color: #6b7280; font-size: 12px; }
        
        .total-section { margin-top: 50px; border-top: 2px solid #111827; padding-top: 20px; }
        .total-row { display: table; width: 100%; font-size: 20px; font-weight: bold; }
        .total-label { display: table-cell; text-align: left; }
        .total-value { display: table-cell; text-align: right; color: #111827; }
        
        .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; height: 50px; text-align: center; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    
    <div class="header">
        <span class="header-title">Gewählte Konfiguration</span>
        @if(isset($project->media) && current($project->media->where('collection_name', 'logo')->all()))
            <img src="{{ current($project->media->where('collection_name', 'logo')->all())->getPath() }}" alt="Logo">
        @else
            <h1>{{ $project->name }}</h1>
        @endif
        <div class="project-title">{{ $project->name }} | {{ $apartment->name }}</div>
    </div>

    @foreach($configurator->rooms as $room)
        @php
            // check if room has selections
            $hasSelections = false;
            foreach($room->categories as $cat) {
                if(isset($selections[$cat->id])) $hasSelections = true;
            }
        @endphp

        @if($hasSelections)
            <div class="room-section">
                <div class="room-header">{{ $room->name }}</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40%">Kategorie</th>
                            <th style="width: 40%">Gewählte Option</th>
                            <th style="width: 20%; text-align:right;">Aufpreis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($room->categories as $category)
                            @if(isset($selections[$category->id]))
                                @php
                                    $selectedOptionId = $selections[$category->id];
                                    $option = $category->options->where('id', $selectedOptionId)->first();
                                @endphp
                                @if($option)
                                <tr>
                                    <td>{{ $category->name }}</td>
                                    <td>{{ $option->label }}</td>
                                    @if($option->price_surcharge > 0)
                                        <td class="price">+ {{ number_format($option->price_surcharge, 2, ',', '.') }} {{ env('APP_CURRENCY', 'CHF') }}</td>
                                    @else
                                        <td class="free">inklusive</td>
                                    @endif
                                </tr>
                                @endif
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    <div class="total-section">
        <div class="total-row">
            <div class="total-label">Aufpreis Gesamt aus Konfiguration:</div>
            <div class="total-value">+ {{ number_format($totalSurcharge, 2, ',', '.') }} {{ env('APP_CURRENCY', 'CHF') }}</div>
        </div>
        <p style="color: #6b7280; font-size: 12px; margin-top: 10px;">
            Dieser Wert umfasst lediglich die konfigurierten Zusatzleistungen und Optionen. <br>
            Änderungen und Irrtümer vorbehalten.
        </p>
    </div>

    <div class="footer">
        Generiert am {{ date('d.m.Y H:i') }} | {{ env('APP_NAME') }} Configurator
    </div>

</body>
</html>
