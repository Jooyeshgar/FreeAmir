<div dir="ltr" lang="en">

# Getting started: active company and fiscal year

**[Back to the management guide](../../README.en.md)**

**[General fiscal-year concepts](fiscal-year.en.md)**

This guide follows an accountant's path from first sign-in and choosing a company through checking the home dashboard and closing a fiscal year. In Amir, selecting an active company also determines the fiscal year and the scope of documents, invoices, accounts, inventory, and reports.

## Before you begin

- Always check the company name and fiscal year shown at the top of the page before daily work.
- Before copying data or closing a year, create a backup through **Management → System → Backup** and keep a copy.
- Your role must have permission for each operation; to change or close a year, you must also have access to that company/year record.
- Current application behavior treats the fiscal year as running from the first of Farvardin through the end of Esfand of the recorded Jalali year.

### Main permissions

| Task | Permission |
|---|---|
| Open the home dashboard | `home` |
| View financial summary values | `home.summary` plus the relevant domain permission |
| Change active company/year | `change-company` |
| View companies and years | `companies.index` |
| Create a company/year | `companies.create` and `companies.store` |
| View the closing wizard | `companies.closing-wizard` |
| Run closing stages 1 and 3 | `companies.closing-wizard.step1` and `companies.closing-wizard.step3` |
| Create a backup | `backups.create` and `backups.export` |

Visible menu items and buttons depend on permissions, so two users in the same company may see different dashboards or menus.

## What is the difference between a company and a fiscal year in Amir?

The interface discusses “company” and “fiscal year” separately, but the current application structure has no separate fiscal-year model. Each row in the company list holds both:

- Company details, such as name, identifiers, and currency; and
- One fiscal year, for example 1405.

Thus “Example Company — 1404” and “Example Company — 1405” are two independent records. There is no shared business identifier connecting those rows; their practical relationship comes from a similar name, user access, and copied prior-year data.

Keep that distinction in mind for these operations:

- **Copy when creating a year:** Creates new records for selected sections in the destination year; it does not delete the source data.
- **Transfer a document or invoice:** Started from that document's or invoice's page with transfer permission; it creates a copy with a destination number. This is different from copying all base data when creating a year.
- **Import a backup:** Creates a new company/year record from a ZIP file rather than directly replacing the current record.
- **Close a year:** Marks the current year closed, creates a new record for the next year, and generates closing/opening documents.

## First sign-in and first company

After sign-in, a user without any company is directed to the **Create your company** form. If email verification is enabled in system settings, the account must first be verified.

The first-company form asks only for:

| Field | Current form rule |
|---|---|
| Company name | Required; at most 50 characters; letters, digits, spaces, or underscores |
| Fiscal year | Required four-digit integer |
| Currency | Optional; an empty value is stored as `Rial` |
| Phone number | Required 11-digit mobile number starting with `09` |

After a successful submission, the application automatically:

1. Creates default accounts, configurations, banks, and initial customer, product, and service groups.
2. Creates a warehouse named “Main Warehouse” with code `MAIN`.
3. Associates the creator with the company and assigns the “Manager” role.
4. Saves the new company/year as the active scope and opens the home dashboard.

If initial setup fails, the application displays “Company setup failed. No data was saved” and rolls back the database operation.

### Suggested checks after creating the first company

1. Check the company name and fiscal year at the top of the page.
2. Review default accounts under **Management → Finance → Accounts**.
3. Check configurations, banks, customer groups, product groups, service groups, and Main Warehouse.
4. Set up the necessary users and permissions.
5. Complete the currency and company identity details before recording real documents.

## Identify and change the active company/year

The active-scope selector in the company interface header displays “company name - fiscal year.” To switch:

1. Open the company/year selector.
2. Select one of the rows assigned to you.
3. After returning to the dashboard, check the company name and year in the header again.

The application retains your selection in the browser. If that selection is deleted or no longer accessible, it is discarded. With no valid selection, the application tries to activate an assigned year matching the current Jalali year; otherwise it displays “Please select a company.”

Many application models—including documents, invoices, accounts, customers, products, warehouses, banks, cheques, employees, and payroll—are restricted to the active company/year ID. After a switch:

- Document and invoice lists and numbering belong to the destination year.
- Accounts, balances, stock, and base data load from that record.
- Dashboard indicators and most reports are calculated from the new scope.

Changing the active company does not transfer or merge data. It only changes your current working scope.

## Create another company or fiscal year

The interface path is **Management → System → Companies → Create new company**. In addition to name and year, the full form includes:

| Field | Important restriction |
|---|---|
| Company name | Required; at most 50 characters; letters/digits/spaces/underscores |
| Fiscal year | Required numeric value; unlike the first-company form, this form has no separate four-digit restriction |
| Logo | Optional JPEG/JPG/PNG/SVG image up to 10 MB |
| Currency | Optional, at most 50 characters; empty becomes `Rial` |
| Moadian username and tax ID | Optional, each at most 20 characters |
| SSL certificate | Optional CRT or CER upload containing a valid X.509 certificate |
| Private key | Optional PEM upload with a valid header |
| Address | Optional, at most 150 letters/digits/spaces/underscores |
| Economic code | Optional, at most 15 characters |
| National ID | Optional, at most 12 characters |
| Postal code | Optional integer only |
| Phone number | Optional numeric 11-digit mobile number starting with `09` |

### Create from scratch

If **Copy data from** is empty, selections in the copy table have no effect. The application creates an independent record and the same default base data as for the first company, including Main Warehouse. Check or enter opening balances and business-specific base data afterward.

### Create by copying an existing year

If you have access to existing company/year records, the **Previous years** section appears:

1. Choose a source in **Copy data from**. Only years assigned to you are valid.
2. Select the required sections in the table.
3. Enter the destination name, year, and other company details.
4. Select **Create**, then check the destination year in the header selector.

The current interface can copy configurations, banks and bank accounts, customers, products, warehouses, services, accounts, documents, document files, invoices, cheques, employees, payroll, official holidays, and tax tables. Monthly budgets are also fetched and copied with the accounts section.

All sections are selected by default, and the interface keeps **Accounts** mandatory. This matters because bank accounts, customers, products, services, and transactions need account mappings in the destination. Other important dependencies include:

- A document file cannot be attached without its document.
- Invoice lines need customers, products/services, and sometimes warehouses.
- A cheque may depend on a bank account, customer, document, and payment.
- Payroll depends on employees and base HR data.

If some dependent mappings are absent, current code may reject or skip dependent data. Do not rely only on the success message: reconcile the count and relationships of accounts, customers, products, banks, documents, invoices, and balances in the destination year.

## Use the home dashboard

After sign-in or changing companies, the **Home** dashboard is personalized by user permissions. Accounting, sales, warehouse, services, CRM, and employee roles may see different cards and shortcuts.

### Financial amounts

Sensitive amounts are initially hidden and fetched from the server when you select **Show** on each card. Viewing a value requires `home.summary` and its domain permission.

| Indicator | Current calculation |
|---|---|
| Net profit | Sum of balances of non-permanent root accounts; positive balances are treated as income and negative balances as expenses |
| Total expenses | Sum of absolute negative balances of those non-permanent root accounts |
| Total sales | Sum of sales-type invoice totals in approved, partially paid, or paid status |
| Total purchases | Sum of purchase-type invoice totals in approved, partially paid, or paid status |
| Total inventory value | Sum of inventory account balances for products that have an inventory account |
| Average sale/purchase | Average total of those approved or settled invoices |
| Inventory sales value | Current quantity of each product multiplied by its current sales price |
| Average product cost | Average `average_cost` for products in the active year |
| Average sales price | Average sales price for products in the active year |

Draft, pending, pro forma, rejected, or unapproved invoices are excluded from sales/purchase totals. Return and void types are also excluded because their types are not “sale” or “purchase.” The displayed currency unit comes from the application's active currency setting.

### Shortcuts and recent information

- Accounting shows up to ten recent approved documents and, where possible, links automatic documents to their source invoice, ancillary cost, cheque, or payment.
- Sales shows up to ten recent invoices in approved, partially paid, or paid status.
- Shortcuts such as sales/purchase invoice, create customer, CRM dashboard, create product, warehouse report, create service, and personal portal appear only with the corresponding permissions.

To check a result, reconcile a card with the detailed report for its domain. For example, compare total sales against active-year sales invoices and profit against the ledger of temporary accounts. A dashboard card does not replace an accounting report or period-end reconciliation.

## Close a fiscal year with the closing wizard

Open **Management → System → Companies** and select **Close fiscal year** on the open-year row. The button is available only with the required permission and for a year assigned to you.

### Before starting the stages

1. Keep a backup outside the application and verify that the ZIP file can be opened.
2. Complete all period postings, corrections, depreciation, taxes, and adjustments.
3. Reconcile ledgers, trial balance, customer balances, banks, cheques, and inventory.
4. Confirm you are in the correct active year and have not accidentally created a duplicate next year.

The wizard performs three automatic checks; stage 1 is unavailable until all three pass:

- No unapproved accounting documents remain.
- No product has negative inventory.
- Numeric document numbers have no gaps.

Current wizard behavior does not show balancing of every document as a separate check. Checking the trial balance and document correctness remains the accountant's responsibility.

### Stage 1: Close temporary accounts

With **Close temporary accounts**, the application reverses balances of accounts whose `is_permanent` flag is false and creates an approved document titled “Current Profit and Loss Summary.” Its balancing side is the account with that name; the application creates it if absent.

After running it:

1. Open the generated document link.
2. Compare each income and expense account balance and its opposing side with the pre-closing trial balance.
3. Until the whole process is complete, do not create another document or invoice that changes temporary accounts.

Stage 1 can run only once for a year; the current interface has no rollback or rerun button. If an error is found afterward, do not proceed to stage 3 without technical and accounting review.

### Stage 2: Manual adjustments

The “Current Profit and Loss Summary” balance must be exactly zero. If a balance remains, select **Create manual document (tax/dividends)** and record taxes, allocable profit, retained earnings, or other necessary items as decided by the responsible accountant.

The application does not prescribe fixed accounts or amounts for those adjustments. They require professional review against the business's legal obligations. Return to the wizard and verify that the balance is zero.

### Stage 3: Close permanent accounts and open the new year

This stage is enabled only after stage 1 has run and the profit-and-loss summary balance is exactly zero. After final confirmation, the application performs these steps in one database transaction:

1. Reverses all nonzero permanent account balances and creates an approved closing document.
2. Marks the current year closed with its closing time and user.
3. Creates a new record with the same company details and fiscal year “current year + 1.”
4. Copies accounts, configurations, banks, customers, products, warehouses, services, and employees to the new year.
5. Carries the same users' access and separate Moadian certificate/key files into the new year.
6. Creates opening document number 1 in the new year from the reversal of the closing document.
7. Selects the new year as the active scope.

Ordinary documents, invoices, cheques, payroll history, official holidays, and tax tables are not in stage 3's fixed copy list. Only permanent-account balances carry over through the opening document. Check this before beginning work in the new year.

Opening transactions are built by matching source and destination account codes. The automatic documents' dates currently use the operation date; compare the closing and opening dates with your period policy. If no transactions can be mapped for the opening document, current code may reject its creation. Always verify that document number 1 and its balances exist.

## Checks after closing a year

1. Confirm that the company name with the new year is active in the header selector.
2. In the company list, verify that the prior year is **Closed** and the new year **Open**.
3. Open the prior-year closing and new-year opening documents and reconcile their debit and credit totals.
4. Reconcile permanent-account codes and balances across the two years.
5. Verify copied base data, users, warehouses, banks, customers, products, services, and configurations.
6. Plan how to transfer or enter items that were not copied automatically.
7. Create an initial backup of the new year too.

## Common errors and remedies

| Message or condition | Cause and action |
|---|---|
| “You do not have access to this company” | The user is not assigned to the destination company/year record; review their assignment. |
| Companies or Close Year is missing | The role lacks the corresponding `companies.*` permission. |
| No active company appears | No assigned record matches the current Jalali year; select a valid row from the header selector. |
| Company creation failed and no data was saved | Initial data creation or copying failed; check inputs and error logs, then retry. |
| Draft-document check fails | Approve documents without an approval date or delete them if permitted. |
| Negative-inventory check fails | Correct negative product quantities using warehouse records and movements. |
| Document-number sequence check fails | Investigate gaps and correct numbering with the document-ordering tool and accountant review. |
| Stage 1 already completed | The profit-and-loss summary document is associated with the year and cannot be rerun. |
| Profit-and-loss summary balance must be zero | Post the stage 2 adjustment and check the balance again. |
| Fiscal year already closed | Do not repeat the operation; inspect the new year and generated documents. |

## Important current limitations

- Closing a year does not currently impose a global lock on all create/edit forms. Closed records also remain selectable in the header. After closing, users should avoid new postings in the closed year without access controls and an internal procedure.
- Stage 1 has no rollback or rerun action in the interface.
- The wizard's automatic checks are limited to the three listed above; they do not replace trial balance, document checks, stocktaking, or financial-manager approval.
- The creation form has no visible uniqueness check for company name plus fiscal year; verify that a duplicate record does not exist before submitting.
- The application does not determine legal or tax rules for closing profit and loss; the financial lead must approve them.

## Related guides

- [Fiscal-year concepts and general considerations](fiscal-year.en.md)
- [Developer guide: fiscal-year export and import](../../../developer/FiscalYearExportImport.en.md)
- [Accounting basics and debit/credit checks](../../../developer/accounting-basics.en.md)

</div>
