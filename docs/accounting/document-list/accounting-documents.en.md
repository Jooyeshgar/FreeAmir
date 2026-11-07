# Accounting Documents Guide

This guide is for accountants who want to find, review, approve, correct, copy, or transfer manual or automatic entries in Amir.

> **Important note about reports:** In the current version of Amir, creating document entries makes those entries visible in the transaction list and reports. `approved_at` stores the document's approval status, but journal, general ledger, subsidiary ledger, detail ledger, trial balance, and account balance reports do not filter transactions by their document's status. Approval therefore checks balance and marks a document as finalized; it is not a prerequisite for including the document in reports. Check the document's status before relying on a report.

### Before you start

- Check the active fiscal year.
- Creating, editing, deleting, approving, importing/exporting, or transferring requires permission for that operation. A missing button may reflect your access level.
- If you are unfamiliar with debits and credits, first read [Accounting Basics](../../developer/accounting-basics.en.md).

## Manual and automatic documents

### Manual document

Created under **Accounting → Create Document**. The accountant enters the title, number, date, entries, and descriptions. This document is not linked to an invoice or another operation and can be edited or deleted from the document list unless another restriction applies.

### Automatic or linked document

During operations such as recording an invoice or payment, or issuing or receiving a cheque, the relevant service can create a document and its accounting entries. Invoices and cheques use the document's polymorphic relationship to the originating entity; a payment also stores the generated document's ID on its payment record. The technical details of the relationship therefore differ by operation.

On a document's detail page, supported source types such as invoices or ancillary costs display a badge and a link to the source beside the document's status; related invoice details may also appear farther down the page. Some sources, such as payments or cheque events, may not have a direct link on this page. Another clear indication is a message such as “The document is linked to an operation and cannot be edited,” or a similar message, when you choose **Edit** or **Delete**. The title and entry descriptions also usually identify the origin. To correct such a document, return to the source operation because the document cannot be modified directly.

For working with invoices, see the [Invoice Guides](../../invoices/README.en.md).

## Scenario 1: create and approve a manual document

Suppose you want to record a payment of IRR 10,000,000 in rent expenses from a bank account.

1. Open the document form under **Accounting → Create Document**.
2. Enter a **Title**, for example, “Shahrivar rent payment.”
3. Enter the **Document Number**. The form displays the previous number for reference, but you choose the current number yourself. The number can have at most two decimal places and must be unique within the active fiscal year.
4. Enter the **Date** in the Jalali calendar or select it using the date picker.
5. In the first entry, select the Rent Expense account, enter `10,000,000` under **Debit**, and enter a description such as “Shahrivar rent” under **Description**.
6. In the second entry, select the relevant bank account, enter the same amount under **Credit**, and provide an appropriate description.
7. Check the balance at the bottom of the form. Internally, a transaction's value is its credit minus its debit (`credit - debit`); a balanced document must therefore total zero.
8. Save the document. A new document is created as unapproved.
9. Choose **Approve** from the list or the document's detail page. If the entries do not sum to zero, Amir rejects approval.
10. After approval, recheck the title, number, date, entries, total debit and credit, and status.

### Document form rules

- The date is required. Validation allows the title and number to be blank; if supplied, the title must contain 3–255 characters. The form displays the previous number, and a number you enter must be unique.
- Each submitted entry must have an account and a description. The current request does not independently validate a minimum number of entries; enter complete debit and credit entries for a document that can be reviewed and approved.
- Each entry requires at least a debit or a credit. For ordinary accounting entries, the form takes one of the two columns; the current request does not explicitly prohibit filling both columns or entering zero, so the accountant must check this.
- Each entry's amount must be numeric, nonnegative, and have at most two decimal places.
- If the required account is missing or incorrect, define or correct it in the chart of accounts before recording the document.
- Saving a manual document does not require balance; balance is checked during approval.

## Balancing in Amir

In Amir, each entry's final value is calculated as `credit - debit`. A document is balanced when all entries sum to exactly zero, meaning total debits equal total credits.

Example:

| Account | Debit | Credit |
|---|---:|---:|
| Rent Expense | 10,000,000 | — |
| Bank | — | 10,000,000 |
| **Total** | **10,000,000** | **10,000,000** |

If the bank entry is 9,500,000, the difference is 500,000. You can save the document to complete it later, but it cannot be approved. This check prevents finalizing the entry because its two sides are unequal.

## Status lifecycle, editing, and deletion

### Unapproved

A new document is created as unapproved. You can view and print it, and edit or delete it if it is manual. Its entries exist in the database as soon as they are created and, as explained in the warning at the start of this guide, are also visible in transaction-based reports.

### Approval

From the document list, you can approve or unapprove a single document, or choose **Approve All**. Individual approval checks the sum of the entries and rejects an unbalanced document. “Approve All” approves balanced unapproved documents and rejects unbalanced ones; the completion message shows how many were approved and rejected.

### Revoking approval

The same status control lets you revoke approval. Revoking approval clears the approval time but does not delete entries and, in the current implementation, does not by itself exclude them from reports.

### Editing or deletion

Editing a manual document updates its details and entries. Deleting a manual document also deletes its entries and attachments. Direct editing and deletion are rejected for linked documents; corrections must be made through the source operation.

> Approval alone does not prevent editing or deleting a manual document. If your organization's internal policy treats approved documents as locked, revoke approval first and then make corrections as an internal control.

## Finding documents and reviewing transactions

### Document list

Use **Accounting → Document List**. The current filters are:

- Document number; this filter performs a partial text match against the number.
- Search text in the document title or entry descriptions.
- From date and to date.
- **Approved** or **Unapproved** status.

The list is sorted by number and shows each document's total debit and credit. Open a document's detail page to see entries, files, printing, and additional actions.

### An account's balance while entering a document

On the create/edit form, selecting an account triggers an “Account Balance” request that sums all recorded transactions for that exact account and displays the previous balance, debit, or credit balance. In the current version, this calculation does not filter by document approval status.

### Transaction list

Under **Reports → Accounting → Various Reports**, view the transactions (entries) of all documents. Filters include document number, date range, document title, transaction description, and account. This section is for viewing and opening transaction details; corrections must be made through the document or source operation.

## Scenario 2: review an automatic document

Suppose a sales invoice has been recorded and Amir has created its document.

1. Under **Accounting → Document List**, search by approximate number, date, title, or text in transaction descriptions (entries) to find the document.
2. Open it and check that total debits equal total credits, that the sales and counterparty accounts are correct, and that the descriptions are appropriate.
3. If you try to edit or delete it directly and see a message that it is linked to an invoice or operation, do not continue.
4. Return to the source invoice and make the change in that invoice or operation so that the document stays synchronized.
5. After the change, review the document and the relevant report again.

The same principle applies to documents generated by payments or by issuing or receiving cheques: correct a linked document by correcting its originating payment or cheque issue/receipt operation.

## Copying a document

**Copy** creates a new document with:

- The original title followed by “Copy”.
- The same date.
- The same accounts, amounts, and entry descriptions.
- The next number based on the latest recorded document.
- Unapproved status.
- No copied attachments and no link to the source operation.

After copying, Amir opens the new document's edit form. Before saving or approving it, check its number, date, title, accounts, and amounts. In particular, if the chart of accounts or fiscal year has changed, or another user has created a document concurrently, recheck number uniqueness and account correctness.

## Sorting document numbers

This tool fills gaps in numbering; it does more than display unused numbers. Review the document statistics before using it. Changing document numbers invalidates previously printed documents. It may also affect accounting reports and invalidate them. If accounting reports have been printed, those printed reports will also be considered invalid.

1. Under **Management → Finance**, open **Sort Document Numbers**.
2. Choose **Start Number Sorting**. The system temporarily preserves the current numbers, then assigns consecutive new numbers ordered by date, previous number, and ID.

Because this operation changes document numbers, export the documents to CSV before running it and prevent other users from recording documents concurrently until it finishes.

## CSV export

Open the form under **Document List → Get Report**. Export filters are:

- From/to document number.
- From/to document date.
- Text in the document title or entry description.
- All, approved, or unapproved status.

You can also select optional CSV columns; required columns always remain. The CSV contains one row per accounting entry and can include document number, date, title, manual/automatic type and status, account code components and properties, debit, credit, and entry description. The export includes a BOM to improve Persian display in spreadsheet applications. In the download dialog, download the file or enter an email address to send it to a recipient.

## CSV import

Under **Document List → Import Documents**, select a CSV file. Its maximum size is 50 MB, and you must select one of the formats listed in the form.

### Amir format

The first row must contain headers. The full Amir format has 14 columns:

```text
doc_number,doc_date,doc_title,doc_type,doc_status,subject_root_code,subject_moein_code,subject_tafsili_code,subject_name,subject_type,subject_is_permanent,transaction_desc,debit,credit
```

Corresponding Persian and English headers are also recognized. Required columns are document number and date, detail account code, account name and type, permanent/temporary status, debit, and credit. Each row is one accounting entry; rows with the same document number and date are grouped into one document. Jalali dates in `YYYY/MM/DD` or `YYYY-MM-DD` format and valid Gregorian dates are accepted. Persian digits and common amount separators are normalized. If the document status in the CSV is “Approved”, the relevant service attempts to approve it after creation. The resulting document must therefore be balanced.

### Parsian format

The Parsian format has a different column structure and uses document number, date, account code, account name, description, debit, and credit in the order defined by the format. The first row is skipped as a header, and this skip is reported in the import result.

### Matching and creating accounts

- First, an account is looked up by its existing code in the active fiscal year.
- If no account is found, the system attempts to create one from the code pattern and file information.
- In Amir format, if parent account information is provided in the file, the parent chain can be reconstructed. If the parent's name or information is available neither in the file nor in the entry's account information, that account is rejected.
- In Parsian format, general ledger, subsidiary, or detail account levels are created from the codes. Codes and names are predefined for some general ledger accounts, and a fallback name is generated if a name is missing.

### Rejected rows and import results

Empty rows, headers, invalid numbers or dates, conflicting accounts, accounts that cannot be created from the existing data and file information, or documents that conflict with existing document numbers and dates may be rejected or skipped. The result shows counts of created or skipped documents, created accounts, and rows with an incorrect number of columns, and displays at most the first five errors. In the current version, Amir-format validation does not guarantee that every amount error or simultaneous debit and credit will be detected before calculation; an accountant must review the results after import.

Import runs in a single database transaction. An error may roll back the entire import, although the formats record some document errors and continue processing later groups. Parsian creates unapproved documents; in Amir format, the file's status can request approval. Attachments are not imported. The import service checks balance only when approval is requested; review each document's status and balance separately after importing.

## Scenario 3: controlled export, correction, and import

1. Export the relevant documents to CSV using appropriate number, date, and status ranges, and keep the original file.
2. In a copy, correct only the necessary data; do not change the format, column order, or document numbers.
3. Before importing, check that the document numbers are not duplicates in the active fiscal year and that the required account codes or parent accounts exist.
4. Import the file using **Amir** format.
5. Read the complete result message and note the rejected row numbers.
6. In the document list, find the new documents by number and date.
7. Check total debit and credit, descriptions, and accounts.
8. Approve the correct, balanced documents.
9. For assurance, reconcile a few entries with the transaction list and the relevant report.

## Attachments

On the document's detail page, open **Files**. Files are added from this section; a file is not required when creating the document itself. Note that deleting a document also deletes its files.

- Allowed formats: `jpg`, `jpeg`, `png`, `webp`, and `pdf`.
- Maximum size: 5 MB per file.
- The file title is optional.
- Files can be viewed or downloaded.
- When editing, you can change the title; if you select a new file, it replaces the previous file and the previous file's record is removed from the database. Deleting a file record from the database also deletes its stored file.

Viewing, editing, and deleting files require the corresponding permissions. Files belonging to another document or invalid IDs cannot be managed through this route.

## Transferring a document to another fiscal year

On a standalone document's detail page, **Transfer to another Fiscal Year** shows user-accessible destinations that are open and differ from the active fiscal year. The source fiscal year must also be open, and the user must have document-transfer permission. Documents linked to an invoice or another operation must be transferred through that operation.

1. Open the source document and select the required entries in the table. Use “Select all entries” to select the whole document. At least one entry is required.
2. Choose **Transfer to another Fiscal Year** and select the **Target Fiscal Year**.
3. By default, the source document and all its entries remain unchanged. Selecting **Remove selected entries from source** deletes only those entries. If an approved document becomes unbalanced after deletion, its approval is revoked; if it remains balanced, approval is preserved. If no entries remain, the document itself and its source attachments are also deleted.
4. **Transfer attachments** is independent of entry removal. Selecting it copies all the document's attachments to the destination as independent files and records. Without this option, no attachments are transferred. Deleting the source document does not delete the destination copies.
5. Read the financial-impact warning, select **I have reviewed the selected entries and confirm the financial impact**, and choose **Transfer**. A final confirmation message is also displayed. When removing entries from the source, the warning mentions entry deletion and possible approval revocation.
6. The destination document contains only the selected entries and is always unapproved; even a single entry or an unbalanced selection can be transferred. Its number is based on the latest document number in the destination. The document's date and title and the entries' descriptions and amounts are preserved. Accounts are found by code in the destination; missing accounts are created with their parent chains.
7. Switch the active fiscal year to the destination and check the entries, accounts, date, attachments, and total debit and credit. Complete an unbalanced document before approving it. If you selected source removal, also review the source document's entries and status.

> **Financial impact:** Unapproved entries also appear in transaction-based reports. Transferring without source removal keeps the entries in both years; removing them from the source can also change source-year balances and reports. For example, transferring only the debit entry from a balanced document creates an unbalanced destination document; deleting that same entry from the source also revokes the source document's approval.

The request is rejected if no entry is selected, an entry belongs to another document, or financial-impact confirmation is missing. An attachment-copy error also rolls back the transfer; check the source files and access to them. After resolving an error, review the entries and options again.

## How documents affect reports

The current report implementation behaves as follows:

- **Document/journal report:** Uses documents and their entries, with number, date, and account filters.
- **General ledger:** Aggregates transactions for general ledger accounts.
- **Subsidiary and detail ledgers:** Display and aggregate transactions for the selected account at the relevant level.
- **Trial balance and account balance:** Calculate debits, credits, and balances from account transactions.

The current code for these reports does not require `approved_at`. Recording, editing, deleting, or importing a document can therefore change their figures even if the document is still unapproved.

## Accountant's checklist

### Before saving or approving a document

- [ ] The active fiscal year is correct.
- [ ] The document is manual, or its automatic source has been identified.
- [ ] The document number is unique, and the date falls in the correct fiscal year.
- [ ] The title and descriptions are sufficient for later follow-up.
- [ ] Each entry uses the correct account.
- [ ] Each entry has only a debit or only a credit.
- [ ] Total debits equal total credits, and the balance is zero.
- [ ] If internal procedures require an attachment, it has been added in an allowed format and size.
- [ ] You have considered the effect on reports, including the visibility of unapproved entries.

### After approving a document

- [ ] Its status in the list is “Approved”.
- [ ] At least one entry has been checked in the transaction list and relevant report.
- [ ] If the document is linked, its details match the originating invoice, payment, or cheque.

### After importing documents in bulk

- [ ] Counts of created documents and rejected rows have been recorded.
- [ ] Every rejection reason has been reviewed row by row.
- [ ] No incomplete document remains in the list.
- [ ] Automatically created accounts and their parents have been checked.
- [ ] Every document's balance has been checked.
- [ ] Only reviewed documents have been approved.
- [ ] Source-file totals have been reconciled with destination documents and reports.

## Common errors

| Message or symptom | Likely cause | Suggested action |
|---|---|---|
| Document number already used | Duplicate number in the active fiscal year | Choose another number |
| Amount must be zero / document cannot be approved | Debit and credit values do not sum to zero | Correct the entries and debit/credit amounts |
| Document is linked to an operation | Created by an invoice, payment, cheque, or another operation | Correct the source operation |
| File is not accepted | Invalid format or size over 5 MB | Upload a smaller file in an allowed format |
| CSV row rejected | Invalid format, date, amount, number, or account | Review and correct the rejection reasons, then reimport the required rows |
| Destination account not found | Missing account mapping or code in the target fiscal year | Complete the destination account structure or mapping |
