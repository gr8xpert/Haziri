<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td, th { padding: 4px 6px; border: 1px solid #ccc; text-align: left; }
        .amt { text-align: right; }
        .total td { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $company }}</h1>
    <p>{{ $date }}</p>
    <p>{{ __('The Manager') }}<br>{{ $bank ?: __('Our bank') }}</p>
    <p><b>{{ __('Subject: Salary transfer — :month', ['month' => $run->label()]) }}</b></p>
    <p>{{ __('Please debit our account and credit the accounts below with the amounts shown, total Rs :total.', ['total' => number_format($total)]) }}</p>

    <table>
        <tr><th>#</th><th>{{ __('Employee') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Account / IBAN') }}</th><th class="amt">{{ __('Amount (Rs)') }}</th></tr>
        @foreach ($rows as $i => $row)
            <tr><td>{{ $i + 1 }}</td><td>{{ $row['name'] }} ({{ $row['code'] }})</td><td>{{ $row['bank'] }}</td>
                <td>{{ $row['account'] }}</td><td class="amt">{{ number_format($row['amount']) }}</td></tr>
        @endforeach
        <tr class="total"><td colspan="4">{{ __('Total') }}</td><td class="amt">{{ number_format($total) }}</td></tr>
    </table>

    <p style="margin-top:48px">{{ __('Authorised signatory') }}</p>
</body>
</html>
