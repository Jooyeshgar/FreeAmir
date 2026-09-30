<div dir="ltr">

# Income and Expense Dashboard Guide

**[Back to the reports guide](../README.en.md)**

This dashboard brings together profitability, monthly trends, purchases and sales, and customer balances for the active fiscal year. These metrics are controls, not substitutes for accounting documents or final financial statements.

## Dashboard sections

Under **Reports → Income and Expense Dashboard**, you can see:

- Total revenue, total expenses, net profit, and profit margin
- Monthly actual and forecast revenue and expenses
- Revenue and expenses by subject
- Net sales, net purchases, and sales-versus-purchases margin
- Top debtor and creditor customers

Each month on the chart links to its [monthly forecasting dashboard](../budgets/budgets.en.md).

## Data source and status

| Section | Current status scope |
|---|---|
| Revenue, expense, profit totals and subject breakdown | Confirmed transactions on temporary root subjects and all their descendants |
| Monthly actuals and forecast comparison | Documents with `approved_at` only |
| Top debtors and creditors | All customer-subject transactions, without confirmation filtering |
| Sales, purchases, and margin | All invoices in the active fiscal year, regardless of invoice status |

Purchase/sales figures or customer balances can therefore differ from the confirmed totals at the top. Check document and invoice statuses before drawing conclusions.

## Main totals

Each temporary root subject contributes all its descendants. Positive balances count as revenue; negative balances count as expenses.

```text
net profit = revenue − expenses
profit margin = net profit ÷ revenue × 100
```

The dashboard shows a zero margin when revenue is zero or negative.

## Monthly trends and forecasts

The chart compares actual monthly revenue and expenses with forecasts. A month without a confirmed document is flagged: its actual value is **unavailable**, not zero.

Charts display absolute amounts. Tables and budget calculations retain the accounting sign of expenses; inspect the monthly table to determine direction.

Net profit compares the full-year forecast with actual profit in months that have confirmed documents. Completion percentage is limited to 0–100.

## Sales, purchases, and margin

```text
net sales = sales − sales returns
net purchases = purchases − purchase returns
margin = net sales − net purchases
```

This section uses invoices, not the confirmed temporary-subject totals. Review invoice lists, statuses, and their documents to reconcile purchases and sales.

## Top debtors and creditors

The dashboard sums balances of subjects linked to customers. Negative balances appear among debtors; positive balances among creditors. Open a customer's subject in the subledger/detail ledger to investigate.

## Recording a forecast from this dashboard

A user with budget subject-search and recording permissions can choose one subject and multiple months. A positive amount is revenue; a negative amount is expense. The form updates that subject's existing forecasts in selected months.

Subject rules, manual and system forecasts, previous-month carryover, variance, and achievement percentages are covered in [Monthly Revenue and Expense Forecasting](../budgets/budgets.en.md).

## Profit review

1. Check the active fiscal year.
2. Note total revenue, expenses, and profit.
3. Open the desired month from the chart.
4. Check for confirmed documents and the monthly actual amount.
5. Drill into principal revenue and expense subjects in the trial balance.
6. Inspect related documents with confirmation status in mind.

## Permissions and errors

| Task | Permission |
|---|---|
| View dashboard | `reports.cost-income` |
| View monthly dashboard | `budgets.index` |
| Search forecast subjects | `budgets.search-subjects` |
| Save forecast | `budgets.store` |

| Symptom | Likely cause | Action |
|---|---|---|
| Monthly actual is absent | No confirmed document in that month | Check that month's document statuses |
| Sales differ from revenue | Invoices, documents, or statuses differ | Reconcile invoices, documents, and revenue subjects |
| Customer balance differs | Customer list reads all transactions | Check confirmation status of related documents |
| Forecast form is missing | Save or subject-search permission is missing | Grant the required permission |

## Related guides

- [Monthly Revenue and Expense Forecasting](../budgets/budgets.en.md)
- [Accounting Reports](../accounting/documents/accounting-reports.en.md)
- [Company Overview](../company-overview/company-overview.en.md)
- [Invoices](../../invoices/README.en.md)
- [Accounting Documents](../../accounting/document-list/accounting-documents.en.md)

</div>
