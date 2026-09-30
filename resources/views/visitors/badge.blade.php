<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $visitor->badge_no }} — {{ $visitor->name }}</title>
    <style>
        @page { size: 86mm 54mm; margin: 0; }
        body { margin: 0; font-family: system-ui, sans-serif; }
        .card { width: 86mm; height: 54mm; box-sizing: border-box; padding: 5mm; border: 1px dashed #999; display: flex; gap: 4mm; }
        .photo { width: 22mm; height: 28mm; object-fit: cover; background: #eee; }
        .co { font-size: 9pt; color: #047857; font-weight: 600; }
        .name { font-size: 14pt; font-weight: 700; margin: 2mm 0 1mm; }
        .meta { font-size: 8pt; color: #444; line-height: 1.4; }
        .badge { font-size: 18pt; font-weight: 800; margin-top: 2mm; }
        @media print { .card { border: 0; } button { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="card">
        @if ($photo)<img class="photo" src="{{ $photo }}" alt="">@endif
        <div>
            <div class="co">{{ $company }} · {{ __('VISITOR') }}</div>
            <div class="name">{{ $visitor->name }}</div>
            <div class="meta">
                {{ $visitor->company }}<br>
                {{ __('Visiting') }}: {{ $visitor->host?->fullName() ?? '—' }}<br>
                {{ $visitor->checked_in_at->setTimezone(tenant('timezone'))->format('d M Y H:i') }}
            </div>
            <div class="badge">{{ $visitor->badge_no }}</div>
        </div>
    </div>
</body>
</html>
