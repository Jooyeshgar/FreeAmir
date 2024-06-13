<!DOCTYPE html>
<html dir="rtl">
<head>
    <style>
        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }

        td, th {
            border: 1px solid #dddddd;
            text-align: right;
            padding: 10.4px;
        }

        tr:nth-child(even) {
            background-color: #dddddd;
        }

        table {
            page-break-inside: auto
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto
        }

        thead {
            display: table-header-group
        }

        tfoot {
            display: table-footer-group
        }

        thead {
            display: table-header-group;
        }

        tfoot {
            display: table-footer-group;
        }

        table {
            -fs-table-paginate: paginate;
        }

        @media print {
            table {
                page-break-after: auto
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto
            }

            td {
                page-break-inside: avoid;
                page-break-after: auto
            }

            thead {

                display: table-header-group
            }

            tfoot {
                display: table-footer-group
            }

            thead {
                display: table-header-group;
            }
        }
    </style>
</head>
<body>


    <table>
        <thead>
        <tr>
            <th>شماره سند</th>
            <th>عنوان سند</th>
            <th>عنوان تراکنش</th>
            <th>کد تراکنش</th>
            <th>موضوع تراکنش</th>
            <th>بدهکار</th>
            <th>بستانکار</th>
            <th>تاریخ</th>
        </tr>
        </thead>
        <tbody>
        @foreach($transactions as $transaction)
            <tr>
                <td>{{$transaction->document->number}}</td>
                <td>{{$transaction->document->title}}</td>
                <td>{{$transaction->subject->name}}</td>
                <td>{{$transaction->subject->code}}</td>
                <td>{{$transaction->desc}}</td>
                <td>{{$transaction->value<0?-1*$transaction->value:0}}</td>
                <td>{{$transaction->value>0?$transaction->value:0}}</td>
                <td>{{gregorian_to_jalali_date($transaction->document->date)}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

</body>
</html>

