<x-mail::message>
# Neue Kontaktanfrage: {{ $inquiry->project->name }}

Sie haben eine neue Bewerbung / Kontaktanfrage über die Projektwebseite erhalten.

**Kontaktdaten:**
- **Name:** {{ $inquiry->name }}
@if($inquiry->email)
- **E-Mail:** {{ $inquiry->email }}
@endif
@if($inquiry->phone)
- **Telefon:** {{ $inquiry->phone }}
@endif

@if($inquiry->visitor)
**Lead-Analyse:**
- **Potential:** {{ $inquiry->visitor->lead_label }}
- **Lead Score:** {{ $inquiry->visitor->lead_score }} / 100
- **Verhalten:** {{ $inquiry->visitor->visit_count }} Besuche, {{ $inquiry->visitor->apartments_viewed }} Whg. angesehen
@endif

@if($inquiry->apartment)
**Anfrage zu Wohnung:** {{ $inquiry->apartment->name }}
@endif

@if($inquiry->message)
**Weitere Angaben:**
{!! nl2br(e($inquiry->message)) !!}
@endif

<x-mail::button :url="route('inquiries.index', ['project_id' => $inquiry->project_id])">
Im System anzeigen
</x-mail::button>

Viele Grüße,<br>
Ihr System
</x-mail::message>
