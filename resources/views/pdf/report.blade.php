<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ count($columns) > 20 ? '7px' : '9px' }}; color: #111; }
        h1 { font-size: 14px; margin: 0; }
        .sub { color: #555; margin: 2px 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 2px 3px; text-align: left; }
        th { background: #eef2f0; }
        tr:nth-child(even) td { background: #fafafa; }
        .summary { margin-top: 10px; }
        .summary span { display: inline-block; margin-right: 14px; }
        .foot { margin-top: 8px; color: #777; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $company }} — {{ $report->title() }}</h1>
    <div class="sub">{{ $subtitle }}</div>
    <table>
        <thead>
            <tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach (array_keys($columns) as $key)<td>{{ $row[$key] ?? '' }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">{{ __('No data') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($summary)
        <div class="summary">
            @foreach ($summary as $k => $v)<span><strong>{{ $k }}:</strong> {{ $v }}</span>@endforeach
        </div>
    @endif
    <div class="foot">{{ __('Generated') }} {{ now(tenant('timezone'))->format('d M Y H:i') }} · Hazri</div>
</body>
</html>
