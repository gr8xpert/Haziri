<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $announcement->title }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        body { margin: 0; font-family: system-ui, 'Segoe UI', 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', sans-serif; color: #111; }
        .sheet { max-width: 174mm; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 3px solid #111; padding-bottom: 4mm; }
        .company { font-size: 14pt; font-weight: 700; }
        .kind { font-size: 22pt; font-weight: 800; letter-spacing: .06em; }
        .kind .ur { font-size: 20pt; margin-inline-start: 4mm; }
        .date { margin-top: 3mm; font-size: 11pt; color: #444; }
        h1 { font-size: 24pt; line-height: 1.25; margin: 10mm 0 6mm; }
        .body { font-size: 15pt; line-height: 1.7; white-space: pre-wrap; unicode-bidi: plaintext; }
        .foot { margin-top: 14mm; border-top: 1px solid #999; padding-top: 3mm; font-size: 10pt; color: #555; display: flex; justify-content: space-between; }
        @media print { .no-print { display: none; } }
        .no-print { margin: 6mm auto; max-width: 174mm; }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print"><button onclick="window.print()">{{ __('Print') }}</button></div>
    <div class="sheet">
        <div class="head">
            <div class="company">{{ $company }}</div>
            <div class="kind">NOTICE <span class="ur" lang="ur" dir="rtl">اطلاع</span></div>
        </div>
        <div class="date">
            {{ ($announcement->publish_at ?? $announcement->created_at)->setTimezone(tenant('timezone'))->format('d M Y') }}
            @if ($announcement->expires_at)
                · {{ __('Valid until :date', ['date' => $announcement->expires_at->setTimezone(tenant('timezone'))->format('d M Y')]) }}
            @endif
        </div>
        <h1 dir="auto">{{ $announcement->title }}</h1>
        <div class="body" dir="auto">{{ $announcement->body }}</div>
        <div class="foot">
            <span>{{ __('Management') }} · <span lang="ur" dir="rtl">انتظامیہ</span></span>
            @if ($announcement->requires_ack)
                <span>{{ __('Please confirm on the kiosk after your next punch.') }}</span>
            @endif
        </div>
    </div>
</body>
</html>
