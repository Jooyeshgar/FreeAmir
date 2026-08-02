<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ __('cheques cheque_number', ['serial' => $cheque->serial]) }}</title>
    <style>
        @page { size: 210mm 95mm; margin: 0; }
        body { margin: 0; font-family: Tahoma, sans-serif; }
        .leaf { position: relative; width: 210mm; height: 95mm; }
        .field { position: absolute; white-space: nowrap; }
        .no-print { position: fixed; top: 8px; left: 8px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">{{ __('cheques print') }}</button>
    <div class="leaf">
        <div class="field" style="right: 160mm; top: 16mm">{{ formatDate($cheque->due_date) }}</div>
        <div class="field" style="right: 60mm; top: 43mm">{{ $cheque->customer?->name }}</div>
        <div class="field" style="right: 25mm; top: 28mm">{{ formatNumber($cheque->amount) }}</div>
        <div class="field" style="right: 35mm; top: 58mm">{{ \App\Helpers\NumberToWordHelper::convert((int) $cheque->amount) }}</div>
        <div class="field" style="right: 35mm; top: 73mm">{{ $cheque->desc }}</div>
    </div>
</body>
</html>
