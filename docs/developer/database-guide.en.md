# FreeAmir Database Guide

This guide describes FreeAmir's database structure, table relationships, and important considerations when working with its data. The database is designed around accounting principles and financial-system needs. Schema snippets below summarize the original guide; always check the current migrations before changing code or data.

## 🗃️ Database overview

### Main tables

| Area | Tables and purpose |
| --- | --- |
| User management | `users`, `roles`, `permissions`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, and `fiscal_year_user` (user access by fiscal year) |
| Companies and fiscal years | `companies` (business identity), `fiscal_years` (accounting periods), and fiscal-year-specific `configs` |
| Accounting core | `subjects` (chart of accounts), `documents` (accounting documents), and `transactions` (financial entries) |
| Customers | `customers` and `customer_groups` |
| Products | `products` and `product_groups` |
| Invoices | `invoices` and `invoice_items` |
| Banking | `banks`, `bank_accounts`, `cheques`, and `cheque_histories` |
| Payments | `payments` |

## 🔗 Table relationships

### Simplified ERD

```text
companies ──< fiscal_years ──< subjects, customers, products, documents
users >──< fiscal_years (fiscal_year_user)
documents ──< transactions >── subjects
users >──< roles >──< permissions
invoices ──< invoice_items
invoices ── documents
customers ── subjects (receivables account)
products ── subjects (inventory account)
```

## 📋 Main tables

### 🏢 `companies`

This table stores business identity and company-level details. It does not store a fiscal-year number or closing state. A company can have multiple fiscal years.

### 📅 `fiscal_years`

Each row belongs to one company and represents one accounting period. `year` stores the Jalali year; `(company_id, year)` is unique. Closing state and related document IDs are stored here, including `closed_at`, `closed_by`, `pl_document_id`, `closing_document_id`, and `closing_recalculation_step`.

Records scoped to a period reference `fiscal_years.id` through `fiscal_year_id`. `FiscalYearScope` filters scoped model queries using `getActiveFiscalYear()`. In web requests, the active ID comes from request configuration or the `active-fiscal-year-id` cookie.

The `fiscal_year_user` pivot assigns users to fiscal years. This is separate from roles and permissions: roles authorize operations, while fiscal-year assignment limits the periods whose data the user may access. Access to one fiscal year does not grant access to another year owned by the same company.

```text
fiscal_year_user: fiscal_year_id → fiscal_years.id, user_id → users.id
```

### 📊 `subjects`

The illustrated table has `id`, `code`, `name`, optional self-referencing `parent_id`, `type` (`debtor`, `creditor`, or `both`, default `both`), `fiscal_year_id`, optional polymorphic `subjectable_type` and `subjectable_id`, and timestamps. Deleting a parent cascades to children; deleting a fiscal year cascades to its subjects. `(fiscal_year_id, code)` is unique.

- `parent_id` builds a tree, for example Assets → Current assets → Cash and bank → Cash desk or a specific bank.
- Codes are unique within a fiscal year, not necessarily across years.
- Polymorphic links connect subjects to entities such as customers and products.

### 📄 `documents`

The schema example includes `id`, nullable decimal `number`, nullable `title`, `date`, and `approved_at`, optional `creator_id`, `approver_id`, and `fiscal_year_id`, plus timestamps. Creator and approver reference `users`; `fiscal_year_id` references `fiscal_years` and becomes null if the fiscal year is deleted.

The guide describes the document number as unique within each fiscal year. A document can have many transactions, can be approved by an authorized user, and records its creator.

### 💱 `transactions`

The illustrated columns are `id`, optional `subject_id`, `document_id`, and `user_id`, optional `desc`, required decimal `value(14,2)`, and timestamps. Their foreign keys become null if the related subject, document, or user is deleted.

`value` is signed: a **positive** value is credit and a **negative** value is debit, matching the document service's `credit - debit` calculation. Each entry belongs to a document and a subject. A balanced document has total `value = 0`: a cash sale for 100,000 debits cash by `-100000` and credits sales by `100000`.

### 👤 `customers`

The detailed example stores an ID, name, `subject_id`, customer-group ID, introducer ID, and required `fiscal_year_id`. Contact fields include phone, mobile, fax, address, postal code, email, website, responsible person, and connector. Financial and classification fields include `ecnmcs_code`, `personal_code`, notes, balance, credit, two bank-account name/number/bank triplets, buyer/seller/mate/agent flags, commission, mark/reason, discount rate, and timestamps. References to subject, group, or introducer become null on deletion; deleting a fiscal year cascades to its customers.

Each customer can be associated with a receivables subject and a group. The table also supports extensive contact information, credit limits, opening balances, and role flags.

### 📦 `products`

The schema example includes a fiscal-year-unique `code`, name, optional `group` and `subject_id`, location, `quantity`, optional `quantity_warning`, `oversell`, purchase-price field spelled `purchace_price`, selling price, discount formula, optional VAT rate, description, and `fiscal_year_id`. The group and subject references become null on deletion; deleting a fiscal year cascades to its products.

`(fiscal_year_id, code)` prevents duplicate product codes within a fiscal year. Quantity and warning quantity support inventory and reorder warnings; `oversell` controls sales above stock. `SubjectCreatorService` fills `subject_id` after product creation. `vat` is optional.

### 🧾 `invoices`

The example includes a unique `number`, date, creator/approver/document/fiscal-year/customer references, `addition`, `subtraction`, `vat`, and `cash_payment` totals, shipping date and method, description, `is_sell`, `active`, amount, and timestamps. User, document, and fiscal-year references become null on deletion; deleting a customer cascades to invoices in the shown schema.

The amount fields represent additions, deductions, tax, and cash paid. `fiscal_year_id` links the invoice to its fiscal year and is filtered by `FiscalYearScope`.

### 📝 `invoice_items`

Each line has an ID, optional `invoice_id`, `product_id`, and `transaction_id`, plus required `quantity`, `unit_price`, `unit_discount`, `vat`, and `amount`, an optional description, and timestamps. The three references become null if their related records are deleted.

## 🔐 Access-control tables

### `users`

The sample definition includes `id`, name, unique email, optional verification timestamp, password, optional remember token, and timestamps.

### Roles and permissions

Spatie Permission uses `roles` and `permissions` (each with ID, name, guard, and timestamps). `model_has_roles` links a model to a role, `model_has_permissions` links a model to a permission, and `role_has_permissions` links roles and permissions. Their composite keys prevent duplicate assignments.

## 🗂️ Indexes and optimization

### Important indexes

- `subjects`: unique `(fiscal_year_id, code)` and a `parent_id` reference support the account tree.
- `products`: unique `(fiscal_year_id, code)` plus group and subject foreign keys.
- `configs`: unique `(key, fiscal_year_id)` separates fiscal-year settings.
- `bank_accounts`: unique `(number, fiscal_year_id)` plus a `bank_id` reference.
- `invoices`: number and user, document, fiscal-year, and customer references maintain integrity.
- `fiscal_years`: unique `(company_id, year)` prevents duplicate years within a company.
- `fiscal_year_user`: foreign keys to `fiscal_years` and `users` maintain user access by fiscal year.

## 🔄 Migrations and seeders

### Migration order

Early migrations create translations, users, password-reset tokens, failed jobs, and personal access tokens. The 2024 sequence creates companies, accounting and business tables, permission tables, and the original `company_user` pivot. The fiscal-year refactor migration creates `fiscal_years`, moves period-specific foreign keys to `fiscal_year_id`, and renames the pivot to `fiscal_year_user`. Read the current `database/migrations` directory for the authoritative order and schema before applying migrations.

### Main seeders

`DatabaseSeeder::run(?int $fiscalYearId = null)` temporarily sets the active fiscal-year ID, then calls `CompanySeeder` and the period data seeders. These include warehouse, account, configuration, bank, group, HR-structure, and role/permission seeders. They require an active fiscal year. Because the refactor is still in progress, compare the current `CompanySeeder` implementation and required `FiscalYear` data with the migrations before relying on fresh-database setup or seeding.

### Example subject seeder

A sample `SubjectSeeder` inserts initial accounts using `DB::table('subjects')->insert(...)`: code `010` for banks, `040` for expenses, and `011` for cash holdings, with IDs, parent IDs, account types (`both` or `debtor`), and `fiscal_year_id = 1`. Additional rows form the rest of the base chart.

## 🔒 Database security

### Access control and fiscal-year scope

The guide illustrates a `Document` model installing `FiscalYearScope` in `booted()`. Its `apply(Builder $builder, Model $model)` method adds `where('fiscal_year_id', getActiveFiscalYear())`, automatically narrowing queries to the active fiscal year. Middleware and controllers also check that the user is assigned to the selected year; the global scope does not replace authorization checks.

### Audit trail

The source guide reports no `audit_logs` migration in its snapshot. If an audit trail is needed, add a migration, model, and change-recording logic suitable for the project, or use an existing package such as [Spatie Activitylog](https://github.com/spatie/laravel-activitylog). Verify the current migrations before deciding that an audit trail is absent.
