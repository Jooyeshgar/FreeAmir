<div dir="ltr" lang="en">

# Getting started: active company and fiscal year

**[Back to the management guide](../../README.en.md)**

**[General fiscal-year concepts](fiscal-year.en.md)**

This guide follows an accountant's path from first sign-in and choosing a fiscal year through checking the home dashboard and closing a fiscal year. A company is the business identity and can own multiple fiscal years. The active fiscal year scopes that period's documents, invoices, accounts, inventory, and reports.

## Before you begin

- Check the company name and active fiscal year at the top of the page before daily work.
- Before copying data or closing a year, keep a backup of the fiscal year outside the application. See the [backup guide](../backups/backups.en.md) for downloading and uploading a backup.
- Your role must have permission for each operation; to select or close a year, you must also have access to that fiscal year.
- Current application behavior treats the fiscal year as running from the first of Farvardin through the end of Esfand of the recorded Jalali year.

### Main permissions

| Task | Permission |
|---|---|
| Open the home dashboard | `home` |
| View financial summary values | `home.summary` plus the relevant domain permission |
| Change active fiscal year | `change-company` |
| View companies | `companies.index` |
| View fiscal years | `fiscal-years.index` |
| Create a company | `companies.create` and `companies.store` |
| Create a fiscal year | `fiscal-years.create` and `fiscal-years.store` |
| View the closing wizard | `companies.closing-wizard` |
| Run closing stages 1 and 3 | `companies.closing-wizard.step1` and `companies.closing-wizard.step3` |
| Restart closing for recalculation | `companies.closing-wizard.recalculate` |
| Create a backup | `backups.create` and `backups.export` |

Visible menu items and buttons depend on permissions, and users may also have access to different fiscal years.

## What is the difference between a company and a fiscal year in Amir?

A company holds the business identity; a fiscal year is a period that belongs to that company. One company can have multiple fiscal years, such as 1404 and 1405. Each fiscal year has its own record, and documents, invoices, accounts, and related data are stored with that fiscal year's ID.

User access is assigned by fiscal year. Access to one year does not grant access to the company's other years. Roles determine which operations a user may perform; fiscal-year assignments determine which period's data the user can select and access.

Keep that distinction in mind for these operations:

- **Copy when creating a year:** Creates new records for selected sections in the destination year; it does not delete the source data.
- **Transfer a document or invoice:** Started from that document's or invoice's page with transfer permission; it creates a copy with a destination number. This is different from copying all base data when creating a year.
- **Import a backup:** Loads data for one fiscal year from a ZIP and creates a new company and fiscal year for that data.
- **Close a year:** Closes the current year, creates the next fiscal year for the same company, and generates closing/opening documents.

## First sign-in and first company

After sign-in, a user who has no fiscal-year access is directed to the **Create your company** form. If email verification is enabled in system settings, the account must first be verified. Submitting the form creates the company and its first fiscal year, assigns the user access to that year, and creates the period's base data.

The first-company form asks only for:

| Field | Current form rule |
|---|---|
| Company name | Required; at most 50 characters; letters, digits, spaces, or underscores |
| Fiscal year | Required four-digit integer |
| Currency | Optional; an empty value is stored as `Rial` |
| Phone number | Required 11-digit mobile number starting with `09` |

After creation, check the company name and fiscal year in the header and confirm that the user has access to the new year. The creation flow also sets up the base data and the creator's role; review any additional permissions needed for daily work.

### Checks after creating a company

1. Check the company name and fiscal year at the top of the page.
2. Review default accounts under **Management → Finance → Accounts**.
3. Check configurations, banks, customer groups, product groups, service groups, and Main Warehouse.
4. Set up the necessary users and permissions.
5. Complete the currency and company identity details before recording real documents.

## Identify and change the active fiscal year

The header shows the active company name and fiscal year. The selector lists fiscal years assigned to the user; choose the year you want from that list.

The `active-fiscal-year-id` cookie stores the active fiscal-year ID, and middleware checks the user's access to it. If there is no valid cookie, the application automatically selects an assigned year whose number matches the current Jalali year. If the user has no such year, select one manually from the header.

Financial and operational models—including documents, invoices, accounts, customers, products, warehouses, banks, cheques, employees, and payroll—are restricted to the active fiscal-year ID. After switching years:

- Document and invoice lists and numbering belong to the destination year.
- Accounts, balances, stock, and base data load from that record.
- Dashboard indicators and most reports are calculated from the new scope.

Changing the active fiscal year does not transfer or merge data. It only changes your current working scope.

## Create another company or fiscal year

The **Create Company** form creates a new company and its first fiscal year. To add another year to an existing company, use **Create Fiscal Year** from that company's page or the **Fiscal Years** area in the management panel. The table below lists the fields on the company-creation form:

| Field | Form rule |
|---|---|
| Company name | Required; at most 50 characters; letters/digits/spaces/underscores |
| Fiscal year | Required numeric value |
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

### Create a company from scratch

Leaving **Copy data from** empty creates a new company and its fiscal year with default base data, including Main Warehouse. Check or enter opening balances and business-specific base data afterward. To create another year for the same company, use the **Create Fiscal Year** form.

### Copy data from an existing year

The **Create Company** form lists fiscal years available to the user under **Previous years**. Selecting a source there copies the chosen sections into the first year of the new company. To copy into another year of the same company, use **Create Fiscal Year**; its source and destination years must belong to that company.

The copy service supports configurations, banks and bank accounts, customers, products, warehouses, services, accounts, documents, document files, invoices, cheques, employees, payroll, official holidays, and tax tables. Monthly budgets are also fetched and copied with the accounts section.

All sections are selected by default, and the interface keeps **Accounts** mandatory. This matters because bank accounts, customers, products, services, and transactions need account mappings in the destination. Other important dependencies include:

- Attach each document file to its document.
- Invoice lines need customers, products/services, and sometimes warehouses.
- A cheque may depend on a bank account, customer, document, and payment.
- Payroll depends on employees and base HR data.

After copying, compare the counts and relationships of accounts, customers, products, banks, documents, invoices, and balances in the destination year.

## Use the home dashboard

After sign-in or changing the active fiscal year, the **Home** dashboard is personalized by user permissions. Accounting, sales, warehouse, services, CRM, and employee roles may see different cards and shortcuts.

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

For accounting review, reconcile each dashboard card with the detailed report for its domain. For example, compare total sales against active-year sales invoices and profit against the ledger of temporary accounts.

## Close a fiscal year with the closing wizard

Open **Management → System → Companies** and select **Close fiscal year** on the open-year row. The wizard operates on that fiscal year. You must have the required permission, access to the year, and select it as the active fiscal year before running the closing stages.

### Before starting the stages

1. Keep a backup outside the application and verify that the ZIP file can be opened.
2. Complete all period postings, corrections, depreciation, taxes, and adjustments.
3. Reconcile ledgers, trial balance, customer balances, banks, cheques, and inventory.
4. Confirm you are in the correct active year and have not accidentally created a duplicate next year.

The wizard performs three automatic checks; stage 1 is unavailable until all three pass:

- No unapproved accounting documents remain.
- No product has negative inventory.
- Numeric document numbers have no gaps.

Before continuing, review the trial balance and confirm that the accounting documents are correct.

### Stage 1: Close temporary accounts

With **Close temporary accounts**, the application reverses balances of accounts whose `is_permanent` flag is false and creates an approved document titled “Current Profit and Loss Summary.” Its balancing side is the account with that name; the application creates it if absent.

After running it:

1. Open the generated document link.
2. Compare each income and expense account balance and its opposing side with the pre-closing trial balance.
3. Until the whole process is complete, do not create another document or invoice that changes temporary accounts.

For a closed year, a user with the closing-recalculation permission can restart the three-stage process from stage 1. After recalculation, review and reconcile the closing and opening documents.

### Stage 2: Manual adjustments

The “Current Profit and Loss Summary” balance must be exactly zero. If a balance remains, select **Create manual document (tax/dividends)** and record taxes, allocable profit, retained earnings, or other necessary items as decided by the responsible accountant.

Choose adjustment accounts and amounts based on the responsible accountant's review of the business's legal obligations. Return to the wizard and verify that the balance is zero.

### Stage 3: Close permanent accounts and open the new year

This stage is enabled only after stage 1 has run and the profit-and-loss summary balance is exactly zero. After final confirmation, the application performs these steps in one database transaction:

1. Reverses all nonzero permanent account balances and creates an approved closing document.
2. Marks the current year closed with its closing time and user.
3. Creates the next fiscal year (“current year + 1”) for the same company.
4. Copies accounts, configurations, banks, customers, products, warehouses, services, and employees to the new year.
5. Assigns the prior users access to the new year too and carries the separate Moadian certificate/key files into it.
6. Creates opening document number 1 in the new year from the reversal of the closing document.
7. Selects the new year as the active scope.

Stage 3 carries permanent-account balances into the new year through the opening document. Add or prepare ordinary documents, invoices, cheques, payroll history, official holidays, and tax tables in the destination year as needed.

Opening transactions are built by matching source and destination account codes. The automatic documents use the operation date; compare the closing and opening dates with your period policy. Then verify that document number 1 and its balances are present in the new year.

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
| “You do not have access to this fiscal year” | The user is not assigned to the destination year; review their fiscal-year access. |
| Companies or Close Year is missing | The role lacks the corresponding `companies.*` permission. |
| No active fiscal year appears | Check the user's access and the `active-fiscal-year-id` cookie. If no assigned year matches the current Jalali year, choose a year from the header selector. |
| Company or fiscal-year creation fails | Check the form validation message and access to the selected company or source year. If the application reports a system error, ask the system administrator to review it. |
| Draft-document check fails | Approve documents without an approval date or delete them if permitted. |
| Negative-inventory check fails | Correct negative product quantities using warehouse records and movements. |
| Document-number sequence check fails | Investigate gaps and correct numbering with the document-ordering tool and accountant review. |
| Recalculate a closed year | Use the closing-recalculation action with the required permission, then review the closing and opening documents. |
| Profit-and-loss summary balance must be zero | Post the stage 2 adjustment and check the balance again. |
| Fiscal year already closed | Inspect the new year and generated documents. |

## Related guides

- [Fiscal-year concepts and general considerations](fiscal-year.en.md)
- [Developer guide: fiscal-year export and import](../../../developer/FiscalYearExportImport.en.md)
- [Accounting basics and debit/credit checks](../../../developer/accounting-basics.en.md)

</div>
