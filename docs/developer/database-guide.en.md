# FreeAmir Database Guide

This guide describes FreeAmir's database structure, table relationships, and important considerations when working with its data. The database is designed around accounting principles and financial-system needs. Schema snippets below summarize the original guide; always check the current migrations before changing code or data.

## 🗃️ Database overview

### Main tables

| Area | Tables and purpose |
| --- | --- |
| User management | `users` (users), `roles`, `permissions`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, and `company_user` (companies available to users) |
| Companies | `companies` and company-specific `configs` |
| Accounting core | `subjects` (chart of accounts), `documents` (accounting documents), and `transactions` (financial entries) |
| Customers | `customers` and `customer_groups` |
| Products | `products` and `product_groups` |
| Invoices | `invoices` and `invoice_items` |
| Banking | `banks`, `bank_accounts`, `cheques`, and `cheque_histories` |
| Payments | `payments` |

## 🔗 Table relationships

### Simplified ERD

```text
companies ──< subjects, customers, products, documents
documents ──< transactions >── subjects
users >──< roles >──< permissions
invoices ──< invoice_items
invoices ── documents
customers ── subjects (receivables account)
products ── subjects (inventory account)
```

## 📋 Main tables

### 🏢 `companies`

The original schema example has an `id`, required `name`, optional `logo`, `address`, `economical_code`, `national_code`, `postal_code`, and `phone_number`, plus a required numeric `fiscal_year`.

- Each company has an independent data set.
- The guide describes isolation through `company_id` and the global `FiscalYearScope`, which applies `session('active-company-id')` to queries.
- `fiscal_year` displays the company's fiscal year.
- `company_user` controls which companies a user may access; one user may access several.

### 📅 Fiscal years

The original guide says there is no separate `fiscal_years` table and each `companies` row represents a fiscal year. It describes active-year selection through `session('active-company-id')` and automatic filtering by `FiscalYearScope`. **This is historical schema guidance; check the current migrations and models**, which may have changed.

### 📊 `subjects`

The illustrated table has `id`, `code`, `name`, optional self-referencing `parent_id`, `type` (`debtor`, `creditor`, or `both`, default `both`), `company_id`, optional polymorphic `subjectable_type` and `subjectable_id`, and timestamps. Deleting a parent cascades to children; deleting a company cascades to its subjects. `(company_id, code)` is unique.

- `parent_id` builds a tree, for example Assets → Current assets → Cash and bank → Cash desk or a specific bank.
- Codes are unique within a company, not necessarily across companies.
- Polymorphic links connect subjects to entities such as customers and products.

### 📄 `documents`

The schema example includes `id`, nullable decimal `number`, nullable `title`, `date`, and `approved_at`, optional `creator_id`, `approver_id`, and `company_id`, plus timestamps. Creator and approver reference `users`; company references `companies`; those references become null when the related row is deleted.

The guide describes the document number as unique within each fiscal year. A document can have many transactions, can be approved by an authorized user, and records its creator.

### 💱 `transactions`

The illustrated columns are `id`, optional `subject_id`, `document_id`, and `user_id`, optional `desc`, required decimal `value(14,2)`, and timestamps. Their foreign keys become null if the related subject, document, or user is deleted.

`value` is signed: a **positive** value is credit and a **negative** value is debit, matching the document service's `credit - debit` calculation. Each entry belongs to a document and a subject. A balanced document has total `value = 0`: a cash sale for 100,000 debits cash by `-100000` and credits sales by `100000`.

### 👤 `customers`

The detailed example stores an ID, name, `subject_id`, customer-group ID, introducer ID, and required company ID. Contact fields include phone, mobile, fax, address, postal code, email, website, responsible person, and connector. Financial and classification fields include `ecnmcs_code`, `personal_code`, notes, balance, credit, two bank-account name/number/bank triplets, buyer/seller/mate/agent flags, commission, mark/reason, discount rate, and timestamps. References to subject, group, or introducer become null on deletion; deleting a company cascades to its customers.

Each customer can be associated with a receivables subject and a group. The table also supports extensive contact information, credit limits, opening balances, and role flags.

### 📦 `products`

The schema example includes a company-unique `code`, name, optional `group` and `subject_id`, location, `quantity`, optional `quantity_warning`, `oversell`, purchase-price field spelled `purchace_price`, selling price, discount formula, optional VAT rate, description, and `company_id`. The group and subject references become null on deletion; deleting a company cascades to its products.

`(company_id, code)` prevents duplicate product codes per company. Quantity and warning quantity support inventory and reorder warnings; `oversell` controls sales above stock. `SubjectCreatorService` fills `subject_id` after product creation. `vat` is optional.

### 🧾 `invoices`

The example includes a unique `number`, date, creator/approver/document/company/customer references, `addition`, `subtraction`, `vat`, and `cash_payment` totals, shipping date and method, description, `is_sell`, `active`, amount, and timestamps. User, document, and company references become null on deletion; deleting a customer cascades to invoices in the shown schema.

The amount fields represent additions, deductions, tax, and cash paid. `company_id` links the invoice to the company and is filtered by the fiscal-year scope described in the source.

### 📝 `invoice_items`

Each line has an ID, optional `invoice_id`, `product_id`, and `transaction_id`, plus required `quantity`, `unit_price`, `unit_discount`, `vat`, and `amount`, an optional description, and timestamps. The three references become null if their related records are deleted.

## 🔐 Access-control tables

### `users`

The sample definition includes `id`, name, unique email, optional verification timestamp, password, optional remember token, and timestamps.

### Roles and permissions

Spatie Permission uses `roles` and `permissions` (each with ID, name, guard, and timestamps). `model_has_roles` links a model to a role, `model_has_permissions` links a model to a permission, and `role_has_permissions` links roles and permissions. Their composite keys prevent duplicate assignments.

## 🗂️ Indexes and optimization

### Important indexes

- `subjects`: unique `(company_id, code)` and a `parent_id` reference support the account tree.
- `products`: unique `(company_id, code)` plus group and subject foreign keys.
- `configs`: unique `(key, company_id)` separates company settings.
- `bank_accounts`: unique `(number, company_id)` plus a `bank_id` reference.
- `invoices`: unique `number` and user, document, company, and customer references maintain integrity.
- `company_user`: company and user foreign keys maintain allowed user/company relationships.

## 🔄 Migrations and seeders

### Migration order

The original guide lists migration filenames in chronological order. Early tables cover translations, users, password-reset tokens, failed jobs, and personal access tokens. The 2024 sequence then creates companies, banks, documents, configs, subjects, customer groups and customers, invoices, transactions, payments, product groups and products, invoice items, bank accounts, checks and check history, permission tables, and `company_user`. Read the current `database/migrations` directory for the authoritative order and schema before applying migrations.

### Main seeders

The illustrated `DatabaseSeeder` calls `CompanySeeder`, `SubjectSeeder`, `ConfigSeeder`, `BankSeeder`, `CustomerGroupSeeder`, `ProductGroupSeeder`, and `RolesAndPermissionsSeeder` to set up an initial company, accounts, settings, banks, groups, roles, and permissions.

### Example subject seeder

A sample `SubjectSeeder` inserts initial accounts using `DB::table('subjects')->insert(...)`: code `010` for banks, `040` for expenses, and `011` for cash holdings, with IDs, parent IDs, account types (`both` or `debtor`), and `company_id = 1`. Additional rows form the rest of the base chart.

## 🔒 Database security

### Access control

The guide illustrates a `Document` model installing `FiscalYearScope` in `booted()`. Its `apply(Builder $builder, Model $model)` method adds `where('company_id', session('active-company-id'))`, automatically narrowing queries to the active company. Check the current scope implementation before relying on this exact snippet.

### Audit trail

The source guide reports no `audit_logs` migration in its snapshot. If an audit trail is needed, add a migration, model, and change-recording logic suitable for the project, or use an existing package such as [Spatie Activitylog](https://github.com/spatie/laravel-activitylog). Verify the current migrations before deciding that an audit trail is absent.
