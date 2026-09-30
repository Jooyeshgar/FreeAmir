<div dir="ltr">

# Customer Relationship Dashboard

**[Back to the customer guides](../README.en.md)**

Dashboard data is limited to the active fiscal year.

## Metrics

| Metric | Current calculation |
|---|---|
| Sales this month | Sales invoice amounts less sales returns in the current Jalali month; only confirmed, partially paid, or fully paid statuses |
| Payments this month | Sum of all positive customer-subject transactions whose document date is in the current month |
| Debtor customers | Number of customers whose subject balance is negative |
| Sales trend | Net sales for each Jalali month in the fiscal year |
| Sales by customer category | Positive fiscal-year net sales grouped by the customer's current group |
| Top customers this year and month | Five customers with the highest positive net sales in the selected period |
| Latest invoices | Eight most recent sales invoices with confirmed, partially paid, or fully paid status |

“Payments this month” measures only positive transactions on the customer subject. Check the customer ledger to reconcile it.

Dashboard “net sales” means sales less sales returns, using invoice amounts. Draft, pro forma, ready-for-confirmation, unconfirmed, inactive-confirmed, and rejected statuses are excluded.

## Aging of unpaid invoices

Unpaid invoices appear in four ranges: 0–30, 31–60, 61–90, and more than 90 days. The application orders each customer's sales invoices oldest first and allocates all positive transactions for that customer against those invoices in that order. Each invoice's unpaid amount is aged from invoice date to today. Any balance not associated with an unpaid invoice, such as an opening balance, is placed in the more-than-90-days range.

This is not a formal reconciliation of each receipt to a particular invoice. Allocation is calculated on an oldest-invoice-first basis, and sales returns are not counted as unpaid invoices in the aging analysis.

## Differences from customer-group and customer lists

| Area | Invoice type and status |
|---|---|
| Dashboard sales, trend, category, and top customers | Sales and sales returns; confirmed, partially paid, and fully paid |
| Dashboard latest invoices | Sales only; the same three statuses |
| Group total sales, returns, and net sales | Corresponding sales or returns; confirmed, partially paid, and paid |
| Group top customers | Gross amount of accepted sales invoices only; sales returns are not deducted |
| Group latest invoices | Sales and sales returns, without a status filter |
| Customer detail invoices | All invoice types and statuses |

Amounts, counts, or customer order may therefore differ across pages. Before using them in a management report, reconcile the invoice type, status, and period.

## Dashboard checks

1. Check the active fiscal year.
2. For monthly sales, inspect sales and return invoice types and statuses.
3. For monthly payments, inspect positive customer-subject ledger transactions in that month.
4. For unpaid invoices, open debtor customers from the customer list.
5. Reconcile each customer's balance with invoice, receipt, and manual adjustment documents.
6. Review opening balances and receipts not linked to a particular invoice separately in the aging analysis.

</div>
