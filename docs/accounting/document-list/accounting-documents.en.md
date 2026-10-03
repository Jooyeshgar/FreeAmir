# Accounting Documents Guide

This guide helps accountants find, review, confirm, correct, copy, import/export, and transfer manual or automatic accounting documents in Amir.

> **Reporting warning:** Creating document lines makes them visible in transaction lists and reports now. `approved_at` records confirmation, but current journal, ledger, subledger, trial-balance, and subject-balance reports do not filter transactions by document confirmation. Confirmation checks balance and marks a document final; it is not the condition for appearing in reports. Check status before relying on a report.

## Before you start

- Check the active fiscal year.
- Creation, editing, deletion, confirmation, import/export, and transfer each require their own permission. A missing button may indicate insufficient access.
- If debits and credits are unfamiliar, read [Accounting Basics](../../general/developer/accounting-basics.en.md).

## Manual versus automatic/linked documents

A **manual document** is created under **Accounting → Create Document**. The accountant enters title, number, date, lines, and descriptions. It is not linked to an invoice or other operation and can normally be edited or deleted from the list.

An **automatic/linked document** can be created by an invoice, payment, cheque issue, or cheque receipt. Invoices and cheques use a polymorphic relationship to the source; payments store the generated document ID on the payment record. The technical link therefore varies by operation.

Supported sources such as invoices or ancillary costs show a badge and source link beside document status, sometimes with invoice details below. Payments and some cheque events may lack a direct source link there. Another clear signal is an edit/delete error such as “Document is linked to an operation and cannot be edited.” Titles and line descriptions also indicate origin. Correct the source operation, not the linked document directly. See the [invoice guides](../../invoices/README.en.md).

## Scenario 1: create and confirm a manual document

Suppose you pay IRR 10,000,000 of rent from a bank account.

1. Open **Accounting → Create Document**.
2. Enter a title such as “Shahrivar rent payment.”
3. Enter the document number. The form shows the preceding number as a hint, but you choose the new one. It accepts at most two decimal places and must be unique in the active fiscal year.
4. Enter a Jalali date or select it in the date picker.
5. In the first line, select Rent Expense, enter `10,000,000` under **Debit**, and describe it.
6. In the second line, select the relevant bank subject and enter `10,000,000` under **Credit**.
7. Check the form balance. Internally each transaction is `credit − debit`; a balanced document totals zero.
8. Save. The new document starts unconfirmed.
9. Choose **Confirm** from the list or detail page. Amir rejects a nonzero total.
10. Recheck title, number, date, lines, total debit/credit, and status.

### Form rules

- Date is required. Title and number may be blank under current validation; a supplied title must have 3–255 characters. A supplied number must be unique.
- Every submitted line needs a subject and description. The current request does not independently enforce a minimum line count; enter complete debit and credit lines for a reviewable document.
- Each line needs at least one debit or credit. Although the form normally records one side, current request validation does not explicitly forbid both sides or a zero amount; the accountant must check this.
- Amounts are nonnegative, numeric, and have at most two decimal places.
- Define or correct a missing/invalid subject before posting.
- Saving a manual document does not require balance; confirmation does.

## Balancing in Amir

Each line is `credit − debit`. A document balances when all lines sum exactly to zero, meaning total debits equal total credits.

| Subject | Debit | Credit |
|---|---:|---:|
| Rent Expense | 10,000,000 | — |
| Bank | — | 10,000,000 |
| **Total** | **10,000,000** | **10,000,000** |

With bank credit of 9,500,000, the difference is 500,000. The draft can be saved but cannot be confirmed.

## Status, editing, and deletion

- **Unconfirmed:** a new document can be viewed or printed and, if manual, edited/deleted. Its lines already appear in transaction-based reports.
- **Confirm:** a single confirmation checks balance. **Confirm All** confirms balanced unconfirmed documents and rejects unbalanced ones, reporting both counts.
- **Unconfirm:** clears confirmation time but does not delete lines or remove them from current reports.
- **Edit/delete:** a manual document's details and lines can be changed; deletion also removes lines and attachments. Direct changes to linked documents are rejected—correct their source.

Confirmation alone does not lock a manual document against edit/delete. If your organization's policy treats it as locked, unconfirm first as an internal control before changing it.

## Find documents and inspect transactions

Under **Accounting → Document List**, filter by document number (partial text match), text in title or line descriptions, date range, and confirmed/unconfirmed status. The list sorts by number and shows debit/credit totals. Open detail for lines, files, printing, and actions.

On the create/edit form, selecting a subject requests its **Account Balance**, summing all transactions for that exact subject and showing previous, debit, or credit balance. Current calculation does not filter document confirmation.

Under **Reports → Accounting → Various Reports**, inspect transaction lines with document number, date range, title, line description, and subject filters. This is for viewing and opening details; change transactions through the document or source operation.

## Scenario 2: review an automatic document

1. Find a sales invoice's document in the Document List by approximate number, date, title, or line description.
2. Check debit/credit equality, sales and counterparty subjects, and descriptions.
3. If edit/delete reports that it is linked, stop direct editing.
4. Correct the source invoice or operation, then recheck the document and report.

The same principle applies to payment and cheque issue/receipt documents: correct the source operation.

## Copying a document

**Copy** creates a new document with the original title plus “Copy,” the same date, subjects, amounts, and line descriptions, the next number after the latest document, unconfirmed status, and no copied attachments or source link. Amir opens its edit form. Recheck number, date, title, subjects, and amounts—especially after account structure/fiscal year changes or concurrent document creation.

## Renumber documents

This tool fills numbering gaps; it is not only a display of unused numbers. Renumbering invalidates previously printed documents and may invalidate printed accounting reports. Review document counts first.

1. Under **Management → Finance**, open **Sort Document Numbers**.
2. Select **Start Number Sorting**. Current numbers are temporarily preserved, then consecutive new numbers are assigned by date, prior number, and ID.

Export a CSV backup first and prevent simultaneous document creation until renumbering finishes.

## CSV export

Under **Document List → Get Report**, filter by number range, date range, text in title/line description, and all/confirmed/unconfirmed status. Optional CSV columns can be selected; required ones remain. There is one CSV row per accounting line. Columns can include number, date, title, manual/automatic type, status, subject-code components and properties, debit, credit, and description. A BOM aids Persian display in spreadsheets. Download or send to a recipient email.

## CSV import

Under **Document List → Import Documents**, choose a CSV of at most 50 MB and select a listed format.

### Amir format

The first row is a header. The full format has 14 columns:

```text
doc_number,doc_date,doc_title,doc_type,doc_status,subject_root_code,subject_moein_code,subject_tafsili_code,subject_name,subject_type,subject_is_permanent,transaction_desc,debit,credit
```

Matching Persian/English headers are recognized. Required columns are document number/date, detail subject code, subject name/type, permanent/temporary flag, debit, and credit. Each row is a line; rows sharing number and date form one document. Jalali `YYYY/MM/DD` or `YYYY-MM-DD` and valid Gregorian dates are accepted. Persian digits and common amount separators are normalized. A CSV status of Confirmed requests confirmation after creation, so the document must balance.

### Parsian format

This has a different defined order: document number, date, subject code, subject name, description, debit, and credit. The first row is skipped as a header and that skip is included in the import result.

### Matching/creating subjects

- First, an existing code is sought in the active fiscal year.
- Otherwise, the system attempts to create a subject from code pattern and file data.
- In Amir format, parent-subject information in the file can reconstruct the parent chain; a line is rejected if required parent name/data is neither in the file nor the line.
- In Parsian format, root and sub/detail levels are inferred from codes; some root subjects use preset codes/names and a fallback name is generated when missing.

### Rejected rows and results

Empty rows, headers, invalid number/date, conflicting or unconstructable subjects, and number/date conflicts with existing documents may be skipped/rejected. Results report created/skipped documents, created subjects, mismatched-column rows, and at most the first five errors. Current Amir-format validation does not guarantee every amount error or simultaneous debit/credit is caught before calculation; inspect imported documents.

Import uses one database transaction; an operation error can roll it all back, although format processors may log some document errors and continue with later groups. Parsian creates unconfirmed documents; Amir status can request confirmation. Attachments are not imported. Balance is checked by the import service only when confirmation is requested. Inspect every imported document's status and balance.

## Scenario 3: controlled export, correction, and import

1. Export relevant documents using number, date, and status filters; retain the original.
2. Edit only necessary data in a copy, without changing format, column order, or document numbers.
3. Check numbers are not duplicates in the active year and required subjects/parents exist.
4. Import with **Amir** format.
5. Read the entire result and note rejected row numbers.
6. Find new documents by number and date; check debit/credit totals, descriptions, and subjects.
7. Confirm correct balanced documents.
8. Reconcile sample lines with the transaction list and related report.

## Attachments

On a document detail page, open **Files** to attach a file; attachments are not required when creating the document. Deleting the document also deletes its files.

- Allowed: `jpg`, `jpeg`, `png`, `webp`, `pdf`; at most 5 MB per file.
- Title is optional; files can be viewed or downloaded.
- Editing can change title or replace an old file. Replacing/deleting its record also deletes the stored old file.
- View/edit/delete permissions apply. Invalid IDs or another document's files cannot be managed here.

## Transfer to another fiscal year

**Transfer to Another Fiscal Year** on document detail shows only user-accessible years later than the active fiscal year.

1. Review the source document and select/confirm a destination.
2. Amir checks destination access and that it differs from the active year.
3. It creates document and lines in the destination, then deletes source document, lines, and files. Attachments are **not copied**.
4. Destination number follows that year's latest number and may differ from the source. Date moves to the destination year while retaining month/day; title and line descriptions remain.
5. Every line's subject must resolve through a between-year mapping or subject code. A missing mapping/code errors and rolls the operation back.
6. Destination starts unconfirmed. Switch active year, find it, inspect, and confirm it.

Transfer is a move, not a copy: export a backup first. Resolve same-year, access-denied, missing-subject, or general transfer errors before retrying.

## Report effects

Document/journal reports use documents and lines with number/date/subject filters. General ledger aggregates root-level subject transactions. Subledger/detail ledger shows and aggregates a selected subject. Trial balance and subject balance calculate debit, credit, and balance from transactions. Current code does not require `approved_at` in these reports, so saving, editing, deleting, or importing even unconfirmed documents can change them.

## Accountant's checks

Before saving/confirming: check active year, manual/source identity, unique number and date, meaningful title/descriptions, correct subjects, one debit or credit side per line, equal totals and zero difference, valid attachments if required, and the visibility of unconfirmed lines in reports.

After confirmation: verify Confirmed status, inspect at least one line in transactions/reports, and reconcile a linked document with its invoice/payment/cheque source.

After bulk import: record created and rejected counts, review all rejected rows, ensure no incomplete document remains, inspect automatically created subjects and parents, check each document's balance and status, confirm only reviewed documents, and reconcile source-file totals with destination documents/reports.

## Common errors

| Message/symptom | Likely cause | Action |
|---|---|---|
| Document number already used | Duplicate in active fiscal year | Choose another number |
| Amount must be zero / cannot confirm | Debits and credits do not balance | Correct lines and amounts |
| Document linked to operation | Created by invoice, payment, cheque, etc. | Correct source operation |
| File rejected | Invalid format or over 5 MB | Upload an allowed smaller file |
| CSV row rejected | Invalid format, date, amount, number, or subject | Review row errors, correct, and reimport needed rows |
| Destination subject not found | Missing mapping/code in target year | Complete target subject structure/mapping |
