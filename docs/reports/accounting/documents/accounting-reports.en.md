<div dir="ltr">

# Accounting Reports Guide

**[Back to the reports guide](../../README.en.md)**

This guide covers document, journal, general ledger, subledger/detail ledger, and trial balance reports.

## Choose a report

| Question | Report | Next check |
|---|---|---|
| Where is a document with a certain number, date, or title? | Documents | Open its debit and credit lines |
| What are all movements ordered by document and date? | Journal | Check each transaction's document and description |
| What are movements and balance for an account group? | General ledger | Inspect the root account and all descendants |
| What are movements and balance for a subaccount/detail account? | Subledger/detail ledger | Check code, description, and running balance |
| What are opening, movements, and balances at one level? | Trial balance | Drill down or open transactions for an account code |

## Data scope

Reports are limited to the active fiscal year; date and document-number ranges are filtered within it. Start date cannot follow end date, nor start number exceed end number.

Currently these five reports read confirmed **and unconfirmed** documents together. If you need confirmed documents only, check each document's confirmation status separately.

## Documents report

Use **Reports → Accounting → Documents Report** to find and print document content. Filters are Jalali start/end dates, start/end document numbers, and a title search. A one-sided range returns documents from that date/number onward or up to it. Results sort by date, then document number.

- **Preview** passes the same filters to the transaction list.
- **Print** shows each document's transactions, creator, and confirmer.
- **Get Report** downloads or emails a CSV.

Document output can include number, date, title, type and status, root/sub/detail codes, subject name and type, permanent/temporary flag, transaction description, debit, and credit. All these columns are present in the document export.

## Journal, general ledger, and subledger/detail ledger

All three have date, document-number, and title-search filters.

### Journal

No subject selection is required. Matching transactions appear by date and document number. Print columns include document, date, code, subject, debit, and credit.

### General ledger

Only root subjects are offered initially. Results include transactions of the selected subject and all descendants. Codes can use Persian or English digits, with or without separators, but must resolve to an existing subject.

### Subledger/detail ledger

Any subject can be selected; results again include that subject and all descendants. For a specific bank account, customer, or detail account, select its lowest relevant level.

Both ledgers show document number, date, transaction description, debit, credit, and running balance:

```text
new balance = previous balance + credit − debit
```

The balance starts at zero at the first returned transaction. If you set a start date or number, earlier balance is **not** carried in. For a complete balance, use trial balance with an appropriate start-date filter.

## Trial balance

Open **Reports → Accounting → Trial Balance**. It starts at root subjects. Selecting a subject name drills down one level; **Go Up One Level** returns one level or to root. Selecting an account code opens its transactions.

Filters include part of subject name, start/end dates, start/end document numbers, and **Show Two Levels**, which displays the current and one lower level—not every level.

### Opening, movements, and balance

Under the current trial-balance convention:

- Opening documents have numbers 1 and 2.
- When the start-number filter is 2 or less, the report applies a number one lower than that start number.
- Date affects only the movement column.
- Each subject sums its own transactions and all descendants.

Internally, credit is positive and debit is negative:

```text
balance = opening + debit/credit movements
```

The page and print place the absolute amount in the appropriate debit or credit column. With **Show Two Levels**, parent and child amounts appear together; the bottom sum may overlap and must not be treated as an independent total of all accounts.

Print retains the current level and filters. CSV also retains the current level and date/number filters, but exports only total debit, total credit, debit balance, and credit balance for the filtered movements. It has no opening column.

## Print, CSV, and email

**Get Report** downloads or emails the output. The user's email is prefilled, but another valid address can be entered. The email identifies the requester's name and email.

| Task | Permission |
|---|---|
| Open a report | `reports.documents`, `reports.journal`, `reports.ledger`, `reports.sub-ledger`, or `reports.trial-balance` |
| Generate report and document/ledger CSV | `reports.result` |
| Export trial-balance CSV | `reports.trial-balance.export-csv` |
| Email export | `report` and the relevant export permission |

## Common errors

| Symptom | Likely cause | Action |
|---|---|---|
| Empty result | Wrong fiscal year, invalid range, or no documents | Check year and filters |
| Date rejected | Invalid Jalali date or start after end | Correct dates |
| Start number rejected | Greater than end number | Correct document range |
| General/subledger does not run | Missing subject or invalid code | Select a valid subject |
| Ledger balance differs from trial balance | Ledger starts at zero or scope/level differs | Use the same scope and check opening in trial balance |
| Email fails | Invalid address or insufficient permission | Check address, `report`, and export permissions |

## Check a document and balance

1. Search documents by date, number, or part of title.
2. Preview and check debits and credits.
3. Inspect its confirmation status on the document page.
4. Select the subject in the subledger/detail ledger with the same period.
5. Reconcile description, amount, and running balance with the document.

## Check an account level

1. Open trial balance without **Show Two Levels**.
2. Set date and document ranges. Movements start at document 3 by default.
3. Select a subject name to drill down.
4. Check opening, movement, and balance.
5. Select an account code to see its transactions.
6. Export CSV and note that it lacks the opening column.

## Which report for which check?

| Check | Suggested path |
|---|---|
| Customer balance | Customer subledger/detail ledger, then documents and payments |
| Bank balance | Bank-account subledger/detail ledger, then Company Overview |
| Inventory | Warehouse report and costing guide, then inventory subject |
| Sales | Sales invoice list, documents, and revenue subject |
| Expenses | Temporary-subject trial balance and Income and Expense Dashboard |
| Profit | Income and Expense Dashboard, then trial balance and revenue/expense documents |

## Related guides

- [Accounting Documents](../../../accounting/document-list/accounting-documents.en.md)
- [Subjects](../../../management/finance/subjects/subjects.en.md)
- [Company Overview](../../company-overview/company-overview.en.md)
- [Income and Expense Dashboard](../../cost-income/cost-income.en.md)
- [Invoices](../../../invoices/README.en.md)
- [Inventory Costing](../../../warehouse/products/inventory-costing.en.md)
- [Banks](../../../management/finance/banks/banks.en.md) and [Bank Accounts](../../../management/finance/bank-accounts/bank-accounts.en.md)

</div>
