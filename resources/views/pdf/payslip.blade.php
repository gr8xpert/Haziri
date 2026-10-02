<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td, th { padding: 5px 6px; border-bottom: 1px solid #ddd; text-align: left; }
        .amt { text-align: right; }
        .total td { font-weight: bold; border-top: 2px solid #111; }
        .grid td { border: 0; padding: 2px 6px 2px 0; }
    </style>
</head>
<body>
    <h1>{{ $company }}</h1>
    <div class="muted">{{ __('Payslip') }} — {{ $month }}</div>

    <table class="grid">
        <tr><td class="muted">{{ __('Employee') }}</td><td>{{ $slip->employee->fullName() }} ({{ $slip->employee->code }})</td>
            <td class="muted">{{ __('Designation') }}</td><td>{{ $slip->employee->designation?->name }}</td></tr>
        <tr><td class="muted">{{ __('Department') }}</td><td>{{ $slip->employee->department?->name }}</td>
            <td class="muted">CNIC</td><td>{{ $slip->employee->cnic }}</td></tr>
    </table>

    <table>
        <tr><th>{{ __('Earnings') }}</th><th class="amt">Rs</th><th>{{ __('Deductions') }}</th><th class="amt">Rs</th></tr>
        @php
            $earnings = array_values(array_filter($slip->lines, fn ($l) => $l['type'] === 'earning'));
            $deductions = array_values(array_filter($slip->lines, fn ($l) => $l['type'] === 'deduction'));
        @endphp
        @for ($i = 0; $i < max(count($earnings), count($deductions)); $i++)
            <tr>
                <td>{{ $earnings[$i]['label'] ?? '' }}</td><td class="amt">{{ isset($earnings[$i]) ? number_format($earnings[$i]['amount']) : '' }}</td>
                <td>{{ $deductions[$i]['label'] ?? '' }}</td><td class="amt">{{ isset($deductions[$i]) ? number_format($deductions[$i]['amount']) : '' }}</td>
            </tr>
        @endfor
        <tr class="total">
            <td>{{ __('Gross') }}</td><td class="amt">{{ number_format($slip->gross) }}</td>
            <td>{{ __('Total deductions') }}</td><td class="amt">{{ number_format($slip->deductions) }}</td>
        </tr>
    </table>

    <h1 style="margin-top:16px">{{ __('Net pay') }}: Rs {{ number_format($slip->net) }}</h1>
    <p class="muted" style="margin-top:24px">{{ __('Computer-generated payslip from :app. Attendance figures are from the locked month.', ['app' => \App\Support\Brand::name()]) }}</p>
</body>
</html>
