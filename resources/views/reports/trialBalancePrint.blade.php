<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <title>{{ __('Trial Balance') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #111827;
        }

        h1 {
            margin: 0 0 6px 0;
            font-size: 20px;
        }

        .meta {
            font-size: 12px;
            color: #4b5563;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
        }

        .text-left {
            text-align: left;
        }
    </style>
</head>

<body dir="rtl">
    <h1>
        {{ __('Trial Balance') }}
        @if ($currentParent)
            - {{ $currentParent->name }}
        @else
            - {{ __('General Level') }}
        @endif
    </h1>
    <div class="meta">
        {{ __('Date range') }}: {{ $start_date ?: __('All') }} {{ $end_date ? ' to ' . $end_date : '' }} |
        {{ __('Document range') }}: {{ $start_document_number ?: __('All') }}
        {{ $end_document_number ? ' to ' . $end_document_number : '' }} |
        {{ __('Children') }}: {{ $include_children ? __('Included') : __('Excluded') }} |
        {{ __('Columns') }}: {{ $columns_number === 2 ? __('Two') : __('Four') }}
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">{{ __('Code') }}</th>
                <th rowspan="2">{{ __('Name') }}</th>
                @if ($columns_number === 4)
                    <th colspan="2">{{ __('Turnover') }}</th>
                @endif
                <th colspan="2">{{ __('Balance') }}</th>
            </tr>
            <tr>
                @if ($columns_number === 4)
                    <th>{{ __('Debtor') }}</th>
                    <th>{{ __('Creditor') }}</th>
                @endif
                <th>{{ __('Debtor') }}</th>
                <th>{{ __('Creditor') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject->formattedCode() }}</td>
                    <td>{{ $subject->name }}</td>
                    @if ($columns_number === 4)
                        <td>{{ formatNumber(abs($subject->debit)) }}</td>
                        <td>{{ formatNumber($subject->credit) }}</td>
                    @endif
                    <td>{{ $subject->balance < 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                    <td>{{ $subject->balance > 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columns_number === 4 ? 6 : 4 }}">
                        {{ __('No data found for the selected filters.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.print();
    </script>
</body>

</html>
