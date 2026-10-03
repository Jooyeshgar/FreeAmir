<div dir="ltr">

# Company Overview Guide

**[Back to the reports guide](../README.en.md)**

Company Overview summarizes sales, purchases, cash, bank, profit, and inventory for the active fiscal year. Its cards and charts are not accounting documents, final financial statements, or statutory reports. Reconcile significant amounts with the relevant reports and documents.

## Fiscal-year scope

Document-, transaction-, subject-, and invoice-based reports are filtered to the active fiscal year. Some cards and charts run separate queries. The profit-and-loss date filter must fall within the active fiscal year; leaving dates blank means the whole active year. This filter does not affect every Company Overview card. Check the active year before reviewing the page.

## Overview metrics

Depending on the user's permissions, the page shows:

- Total bank balance, revenue, expenses, and profit or loss
- Year-to-date sales, inventory value, and year-to-date purchases
- Cash and bank trends over 3, 6, 9, or 12 months
- Bank-account balances and the trend for a selected account
- Monthly revenue and expenses
- Distribution of purchase and sales amounts across goods and services
- Monthly stock quantities and top-selling items by quantity

## Data scopes

The cards do not all use the same scope:

| Metric | Current status scope |
|---|---|
| Year-to-date purchases, purchase/sales distribution, popular items | Confirmed or settled invoices |
| Year-to-date sales, profit/loss, cash and bank balances, bank-account balance, inventory value | Transaction totals, without filtering by document confirmation |
| Profit-and-loss filter | Transactions on temporary root subjects in the selected period |

A difference between two cards is not necessarily an error; their confirmation states or periods may differ.

## Reconcile metrics

### Purchases and sales

1. Note the card title and period.
2. Check the invoice list and each invoice's status.
3. Open documents for confirmed invoices.
4. Inspect the corresponding revenue, purchase, or return subjects in [Accounting Reports](../accounting/documents/accounting-reports.en.md).

### Cash, banks, and bank accounts

1. Check whether the chart shows cash, bank, or both, and its 3–12-month period.
2. Find the bank account in the page list.
3. Open the subject linked to it in the subledger/detail ledger.
4. Reconcile its balance and transactions with the bank statement.

### Inventory and products

Inventory value, monthly stock quantity, and popular-item counts measure different things. Check monetary value, stock units, and units sold separately. Use the [Inventory Costing](../../warehouse/products/inventory-costing.en.md) guide and warehouse reports for quantities and costs.

### Revenue, expenses, and profit

The date filter restricts only profit and loss on temporary subjects. To inspect its entries:

1. Use the same period in the trial balance.
2. Drill into the revenue or expense subject to the required level.
3. Select the account code to inspect documents.
4. Compare with the [Income and Expense Dashboard](../cost-income/cost-income.en.md), allowing for differences in confirmation status.

## Permissions and errors

Opening Company Overview requires `reports.company-overview`. Each card also depends on its own domain permission. For example, financial cards require `documents.show`, while sales cards depend on product and invoice permissions.

| Symptom | Likely cause | Action |
|---|---|---|
| A card is absent | Missing permission for its data domain | Check the user's role and permissions |
| Date is rejected | Outside fiscal year or start after end | Correct the period within the fiscal year |
| Card differs from a report | Different data source or confirmation status | Check the scope table and related documents |
| Chart is empty | No data in selected scope | Check fiscal year, period, and related postings |

## Related guides

- [Accounting Reports](../accounting/documents/accounting-reports.en.md)
- [Income and Expense Dashboard](../cost-income/cost-income.en.md)
- [Invoices](../../invoices/README.en.md)
- [Bank Accounts](../../management/finance/bank-accounts/bank-accounts.en.md)
- [Inventory Costing](../../warehouse/products/inventory-costing.en.md)
- [Active Fiscal Year](../../management/system/companies/getting-started-fiscal-year.en.md)

</div>
