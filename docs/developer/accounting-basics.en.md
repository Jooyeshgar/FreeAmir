# Accounting Basics for FreeAmir Developers

This guide introduces the accounting concepts and code structure a contributor to **FreeAmir** needs to understand.

## Why must developers understand accounting?

Without the accounting context, changes can introduce business-logic bugs, incorrect database designs, unbalanced postings, wrong reports, or violations of accounting rules. A small programming error in a financial system can leave accounts out of balance and create serious financial problems.

## Accounting basics

### What are debit and credit?

- **Debit:** a negative `value` in FreeAmir; generally increases an asset or expense, or decreases a liability.
- **Credit:** a positive `value`; generally decreases an asset or expense, or increases a liability or revenue.

For example, a cash purchase of goods for 100,000 tomans debits inventory (`value = -100000`) and credits cash (`value = 100000`). Both entries belong to the same document.

### The golden rule: balance

For a balanced document, total debits equal total credits; equivalently, `transactions->sum('value')` is zero. FreeAmir permits an unbalanced **manual** document while work is in progress. **Automatic** documents, including those generated from invoices and checks, must be balanced, and only balanced documents can receive final approval. Check the total and raise a validation error before approving an unbalanced document.

## Accounting subject hierarchy

### Main subjects

The example chart of accounts includes bank accounts (`010`), cash holdings (`011`, including cash desk `011001` and petty cash `011002`), receivables/payables (`012`, including miscellaneous persons `012001`), expenses (`040`), cost of goods sold (`041001`), revenue (`050`), and sales (`060001`). A child subject is nested below its parent.

### Subject codes

FreeAmir appends three digits at each level: a general subject can have code `010`, a subsidiary `011001`, a detailed subject `011001001`, and a deeper subject `011001001001`. Further detailed levels are supported. A `subjects` record stores its `code`, `name`, `parent_id`, and account `type`; for example, code `011001` can represent the cash desk beneath cash holdings.

### Relationship between groups and subjects

Fiscal-year-specific configuration connects customer and product groups to accounting subjects. Query `configs` with both the key (for example, `customer_default_subject` or `product_inventory_subject`) and the active fiscal-year ID from `getActiveFiscalYear()` to retrieve the corresponding subject.

### Let the model or service generate codes

When creating a child `Subject`, set its `name`, `parent_id`, and `type`, then save it; its code is generated automatically. If an explicit segment is required, call `$subject->generateCode(15)` for a code such as `015`. Do not construct the hierarchy code by hand.

### Displaying codes

Use `$subject->formattedCode()` for a display code such as `011/001`, or `$subject->formattedName()` to include the subject name (for example, `011/001 Cash Desk`).

## Customer and product groups and their subjects

### Grouping concept

Each customer or product group is linked to accounting subjects. These links are defined through configuration and group records.

### Customers

A `CustomerGroup` has a `subject_id` (for example, the miscellaneous-persons subject `012001`). Creating a `Customer` in that group creates its detailed subject, such as `012001001` for a particular person.

### Goods and services

A `ProductGroup` may specify `inventory_subject_id`, `income_subject_id`, and `expense_subject_id` for inventory, sales revenue, and cost of goods sold respectively. Creating a product in the group creates the relevant detailed subjects.

### Unlimited detailed levels

The hierarchy is not limited to one detailed level: `010` → `011001` → `011001001` → `011001001001` and further levels as needed.

## Documents and transactions

### Overall structure

A `Document` holds a number unique within its fiscal year, date, title, approval date, creator, approver, and fiscal-year ID. It has many transactions and belongs to its creator and approver. A `Transaction` records its subject, document, user, description, and signed `value`. The debit accessor displays `-value` when the value is negative; the credit accessor displays a positive value. Date and approval fields are cast to dates.

### Example document in code

For a cash sale of 500,000 tomans, a document contains a 500,000 debit to cash and an equal credit to sales. The form passes `subject_id`, `debit`, `credit`, and `desc` for each line. `DocumentController::store` converts each line to the signed amount `credit - debit`, producing `-500000` for cash and `500000` for sales, then passes the document and transaction arrays to `DocumentService::createDocument(auth()->user(), $documentData, $transactionsData)`.

## Essential FreeAmir services

### `DocumentService`

Use this service for document operations. Its methods include `createDocument($user, $documentData, $transactionsData)`, `updateDocument($document, $newData)`, `approveDocument($document)` (which checks balance), `createTransaction($document, $transactionData)`, `updateDocumentTransactions($documentId, $transactionsData)`, and `deleteDocument($documentId)` (including its transactions). Avoid creating a document directly with `Document::create()` when service behavior is required.

### `SubjectCreatorService`

Use this service to create accounting subjects and generate their codes. Find the parent (for example, `Subject::where('code', '011001')->firstOrFail()`), then pass the new `name`, `parent_id`, and optional `type` to `createSubject()`. The default type is `both`; `debtor` is another example.

### `FiscalYearService`

This service manages fiscal years and data migration. `exportData($fiscalYearId, ['subjects', 'customers', 'products', 'documents'])` exports selected sections, `importData($data, $newFiscalYearData)` imports into a new year, and `getAvailableSections()` lists exportable sections.

## Fiscal years and multiple companies

A `Company` stores business identity and can have multiple `FiscalYear` records. Each `FiscalYear` stores its company ID, year, and closing state. Fiscal-year-scoped records reference it with `fiscal_year_id`. Users are assigned to fiscal years through the `fiscal_year_user` pivot; a user's access to one year does not grant access to the company's other years. Roles and permissions control which actions are available within the years the user can access.

```php
$company = Company::with('fiscalYears')->findOrFail($companyId);
$fiscalYear = $company->fiscalYears()->where('year', 1405)->firstOrFail();
$user->fiscalYears()->syncWithoutDetaching([$fiscalYear->id]);
```

### Data isolation with `FiscalYearScope`

Scoped models add `FiscalYearScope` as a global scope. For exceptional cross-year work, explicitly call `withoutGlobalScope(FiscalYearScope::class)`; ordinary queries should retain the scope.

### Fiscal-year migration and copying

See [Fiscal-Year Export and Import](FiscalYearExportImport.en.md) for details. Export and import operations identify their source and destination by fiscal-year ID.

### Company configuration

The `configs` table stores settings separately for each fiscal year (with global settings represented separately).

## Financial reports

### Journal

The journal lists **all** transactions in date and document-number order, across general, subsidiary, and detailed subjects. Each row is one transaction with debit and credit values. Use it to inspect transaction timing, trace an entry, or verify document sequence. It supports filters such as date, subject, and document type, and includes temporary as well as permanent documents.

### General ledger

Unlike the date-ordered journal, the ledger groups and summarizes transactions by subject. FreeAmir presents general subjects for a broad financial view, subsidiary subjects for more detail, and a selected detailed subject with all of its transactions. These levels are shown in one integrated form. Selecting a parent also includes its children. Use the ledger to calculate account balances, including cumulative balances at a given date.

### Balance sheet

The balance sheet is a snapshot at a specified date: **assets = liabilities + equity**. It includes current assets (cash, receivables, inventory), fixed assets (buildings, machinery, equipment), current liabilities (payables, tax due), long-term liabilities (bank loans), and equity (initial capital and retained earnings). It uses permanent accounts, not temporary income and expense accounts. FreeAmir includes the current year's profit or loss in equity. It differs from the journal and ledger by showing balances at one date rather than individual transactions.

### Income statement

The income statement measures performance **over a period**, not at one date. It includes sales and non-operating revenue, cost of goods sold and other expenses, and net profit or loss. Sales less cost of goods sold gives gross profit; subtract operating expenses for operating profit; then add non-operating income and subtract non-operating expenses for net profit. It uses temporary accounts, whereas the balance sheet uses permanent accounts. The guide notes that income and expense accounts close to retained earnings at year-end, reports can cover monthly, quarterly, or annual periods, and a loss reduces equity.

The reports form a chain: journal (raw transactions) → ledger (subject totals) → balance sheet (permanent-account balances at a date) and income statement (temporary-account performance for a period). Because they use the same accounting data, their figures must reconcile.

## Settings and configuration

### Why use `Config`?

Relationships and settings are stored in `configs` instead of being hard-coded, so users can configure them without changing source code.

### `ConfigLoader` middleware

The guide describes `ConfigLoader` loading database settings into Laravel's `config()` on each HTTP request, accessible as `config('amir.key')`. See `ConfigController` for more information.

### Accessing settings

Use `config('amir.cash_book')` or `config('amir.bank', null)` with a default. For a fiscal-year-specific value, query `Config` with the active `fiscal_year_id` and key (for example, `cash_book`).

## Security and access control

### Roles in a financial system

FreeAmir uses [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6/introduction) for access control. Example permissions are `accounting.documents.create`, `.edit`, and `.delete`, `accounting.reports.view`, and `accounting.settings.manage`.

## Important developer practices

### 1. Check balance for automatic documents

Sum signed transaction values and reject an automatic invoice or check document when the total is not zero. Balance is not mandatory for an unfinished manual document.

### 2. Use database transactions

Wrap multi-step financial operations in `DB::transaction()` so document and transaction changes succeed or fail together.

### 3. Use the provided services

Use `DocumentService::createDocument()` instead of a bare `Document::create()` so the required business behavior is applied.

### 5. Use subject codes correctly

Generate a code with `$subject->generateCode()` and display it with `$subject->formattedCode()` (for example, `001/002/003`).

### 7. Respect fiscal-year scopes

An ordinary `Subject::all()` query uses the global scope for the active fiscal year. Only bypass `FiscalYearScope` explicitly for an operation that really needs records outside the active scope.

Accuracy and adherence to accounting rules matter more than speed in a financial system. Test changes repeatedly and use the project's services.
