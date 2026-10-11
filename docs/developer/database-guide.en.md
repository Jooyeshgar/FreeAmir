# FreeAmir Database Guide

This guide explains FreeAmir's database structure, table relationships, and important considerations when working with its data.

## 🗃️ Database overview

FreeAmir's database is designed around accounting principles and the needs of financial systems.

### Main tables

```
FreeAmir database
├── 👥 User management
│   ├── users                 # System users
│   ├── roles                 # Roles
│   ├── permissions           # Permissions
│   ├── model_has_permissions # Model-permission assignments
│   ├── model_has_roles       # Model-role assignments
│   ├── role_has_permissions  # Role-permission assignments
├── 🏢 Company management
│   ├── companies             # Companies
│   ├── fiscal_years          # Each company's fiscal years
│   ├── fiscal_year_user      # User access to fiscal years
│   └── configs               # Fiscal-year and global settings
├── 📊 Accounting core
│   ├── subjects              # Chart of accounts
│   ├── documents             # Accounting documents
│   └── transactions          # Financial entries
├── 👤 Customer management
│   ├── customers             # Customers
│   └── customer_groups       # Customer groups
├── 📦 Product management
│   ├── products              # Products
│   └── product_groups        # Product groups
├── 🧾 Invoices
│   ├── invoices              # Invoices
│   └── invoice_items         # Invoice items
├── 🏦 Bank management
│   ├── banks                 # Banks
│   ├── bank_accounts         # Bank accounts
│   ├── cheques               # Checks
│   └── cheque_histories      # Check histories
└── 💰 Payments
    └── payments              # Payments
```

## 🔗 Table relationships

### Simplified ERD

```
companies (1) ──→ (N) fiscal_years
users (N) ←──→ (N) fiscal_years  [fiscal_year_user]
fiscal_years
    ├─→ (N) subjects
    ├─→ (N) customers
    ├─→ (N) products
    └─→ (N) documents
            │
            └─→ (N) transactions ──→ subjects

users (N) ←──→ (N) roles ←──→ (N) permissions

invoices (1) ──→ (N) invoice_items
    │
    └─→ documents

customers ──→ subjects (receivables account)
products ──→ subjects (inventory account)
```

## 📋 Main tables

### 🏢 `companies` — companies

```sql
CREATE TABLE companies (
    id BIGINT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    logo VARCHAR(255) NULL,
    address VARCHAR(150) NULL,
    economical_code VARCHAR(15) NULL,
    national_code VARCHAR(12) NULL,
    postal_code VARCHAR(255) NULL,
    phone_number VARCHAR(11) NULL
);
```

**Important notes:**
- This table stores business identity; each company can have multiple fiscal years.
- The year and closing state are stored in `fiscal_years`.

### 📅 `fiscal_years` — fiscal years

Each row belongs to a company. The `year` column stores the Jalali year, and `(company_id, year)` is unique. `closed_at` and `closed_by` record the closing state; `pl_document_id`, `closing_document_id`, and `closing_recalculation_step` store details of the year-end closing process.

```sql
CREATE TABLE fiscal_years (
    id BIGINT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    year INT UNSIGNED NOT NULL,
    pl_document_id BIGINT UNSIGNED NULL,
    closing_document_id BIGINT UNSIGNED NULL,
    closing_recalculation_step TINYINT UNSIGNED NULL,
    closed_at TIMESTAMP NULL,
    closed_by BIGINT UNSIGNED NULL,
    UNIQUE KEY fiscal_years_company_id_year_unique (company_id, year),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);
```

Financial records reference `fiscal_years` through `fiscal_year_id`, and `FiscalYearScope` filters them by the active ID. The active ID comes from `getActiveFiscalYear()`; in web requests, it is read from configuration or the `active-fiscal-year-id` cookie.

The `fiscal_year_user` pivot assigns user access to fiscal years. This relationship does not replace role permissions: roles and permissions determine which operations a user can perform, while fiscal-year assignment determines which period's data they can work with.

```text
fiscal_year_user: fiscal_year_id → fiscal_years.id, user_id → users.id
```

### 📊 `subjects` — chart of accounts

```sql
CREATE TABLE subjects (
    id BIGINT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(60) NOT NULL,
    parent_id BIGINT NULL,
    type ENUM('debtor', 'creditor', 'both') DEFAULT 'both',
    fiscal_year_id BIGINT NOT NULL,
    subjectable_type VARCHAR(255) NULL, -- Polymorphic
    subjectable_id BIGINT NULL,         -- Polymorphic
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (parent_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fiscal_year_code (fiscal_year_id, code)
);
```

**Important notes:**
- `parent_id` defines a tree structure.
- Account codes are unique within each fiscal year.
- Polymorphic relationships link accounts to other entities, such as customers and products.
- Types: `debtor` (debit), `creditor` (credit), and `both` (both).

**Example account hierarchy:**
```
1. Assets
   1.1 Current assets
       1.1.1 Cash and bank
             1.1.1.001 Cash desk
             1.1.1.002 Bank Mellat
   1.2 Fixed assets
       1.2.1 Buildings
```

### 📄 `documents` — accounting documents

```sql
CREATE TABLE documents (
    id BIGINT PRIMARY KEY,
    number DECIMAL(16,2) NULL,
    title VARCHAR(255) NULL,
    date DATE NULL,
    approved_at DATE NULL,
    creator_id BIGINT NULL,
    approver_id BIGINT NULL,
    fiscal_year_id BIGINT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE SET NULL
);
```

**Important notes:**
- The document number (`number`) is unique within each fiscal year.
- Each document can have multiple transactions.
- An authorized user can approve a document.
- The document's creator is tracked.

### 💱 `transactions` — financial entries

```sql
CREATE TABLE transactions (
    id BIGINT PRIMARY KEY,
    subject_id BIGINT NULL,
    document_id BIGINT NULL,
    user_id BIGINT NULL,
    desc VARCHAR(255) NULL,
    value DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

**Important notes:**
- A positive `value` means credit; a negative value means debit, matching the document service's “credit - debit” calculation.
- Each transaction belongs to an account and a document.
- The sum of `value` in each document must be zero (balanced).

**Example sale transaction:**
```sql
-- Cash sale document for 100,000 tomans
INSERT INTO transactions VALUES
(1, 'cash_account_id', 'document_id', 'user_id', 'Cash receipt', -100000),
(2, 'sales_account_id', 'document_id', 'user_id', 'Product sale', 100000);
-- Total: -100000 + 100000 = 0 ✓
```

### 👤 `customers` — customers

```sql
CREATE TABLE customers (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject_id BIGINT UNSIGNED NULL,
    phone VARCHAR(15) NULL DEFAULT '',
    mobile VARCHAR(15) NULL DEFAULT '',
    fax VARCHAR(15) NULL DEFAULT '',
    address VARCHAR(100) NULL DEFAULT '',
    postal_code VARCHAR(15) NULL DEFAULT '',
    email VARCHAR(64) NULL DEFAULT '',
    ecnmcs_code VARCHAR(20) NULL DEFAULT '',
    personal_code VARCHAR(15) NULL DEFAULT '',
    web_page VARCHAR(50) NULL DEFAULT '',
    responsible VARCHAR(50) NULL DEFAULT '',
    connector VARCHAR(50) NULL DEFAULT '',
    group_id BIGINT UNSIGNED NULL,
    desc TEXT NULL,
    balance DECIMAL(10,2) NULL DEFAULT 0,
    credit DECIMAL(10,2) NULL DEFAULT 0,
    rep_via_email BOOLEAN NULL DEFAULT FALSE,
    acc_name_1 VARCHAR(50) NULL DEFAULT '',
    acc_no_1 VARCHAR(30) NULL DEFAULT '',
    acc_bank_1 VARCHAR(50) NULL DEFAULT '',
    acc_name_2 VARCHAR(50) NULL DEFAULT '',
    acc_no_2 VARCHAR(30) NULL DEFAULT '',
    acc_bank_2 VARCHAR(50) NULL DEFAULT '',
    type_buyer BOOLEAN NOT NULL DEFAULT FALSE,
    type_seller BOOLEAN NOT NULL DEFAULT FALSE,
    type_mate BOOLEAN NOT NULL DEFAULT FALSE,
    type_agent BOOLEAN NOT NULL DEFAULT FALSE,
    introducer_id BIGINT UNSIGNED NULL,
    commission VARCHAR(15) NOT NULL DEFAULT '0',
    marked BOOLEAN NOT NULL DEFAULT FALSE,
    reason VARCHAR(200) NULL DEFAULT '',
    disc_rate VARCHAR(15) NOT NULL DEFAULT '0',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    fiscal_year_id BIGINT UNSIGNED NOT NULL,

    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (group_id) REFERENCES customer_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (introducer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE CASCADE
);
```

**Important notes:**
- Each customer is linked to an accounts receivable subject.
- Customers can be organized into groups.
- The table includes full contact information and financial settings, such as credit limits, opening balances, and buyer/seller role flags.

### 📦 `products` — products

```sql
CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(60) NOT NULL,
    `group` BIGINT UNSIGNED NULL,
    subject_id BIGINT UNSIGNED NULL,
    location VARCHAR(50) NULL,
    quantity FLOAT NOT NULL,
    quantity_warning FLOAT NULL,
    oversell BOOLEAN NOT NULL DEFAULT FALSE,
    purchace_price DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    discount_formula VARCHAR(100) NULL,
    vat DECIMAL(10,2) NULL,
    description VARCHAR(200) NULL,
    fiscal_year_id BIGINT UNSIGNED NOT NULL,

    FOREIGN KEY (`group`) REFERENCES product_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fiscal_year_product_code (fiscal_year_id, code)
);
```

**Important notes:**
- Product codes are unique within each fiscal year (the composite `fiscal_year_id + code` index).
- `quantity` and `quantity_warning` support inventory management and reorder points; `oversell` controls sales above available stock.
- After a product is created, `SubjectCreatorService` fills `subject_id` so that each product has a related account.
- `vat` stores the product's VAT rate and is optional.

### 🧾 `invoices` — invoices

```sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED PRIMARY KEY,
    number VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    creator_id BIGINT UNSIGNED NULL,
    approver_id BIGINT UNSIGNED NULL,
    document_id BIGINT UNSIGNED NULL,
    fiscal_year_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    addition DECIMAL(16,2) NOT NULL,
    subtraction DECIMAL(16,2) NOT NULL,
    vat DECIMAL(16,2) NOT NULL,
    cash_payment DECIMAL(16,2) NOT NULL,
    ship_date DATE NULL,
    ship_via VARCHAR(100) NULL,
    description TEXT NULL,
    is_sell BOOLEAN NOT NULL,
    active BOOLEAN NOT NULL DEFAULT FALSE,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY invoices_number_unique (number),
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
```

**Important notes:**
- The `number` field is unique for each invoice.
- `addition`, `subtraction`, `vat`, and `cash_payment` store totals for additional amounts, deductions, VAT, and cash payments.
- `fiscal_year_id` references `fiscal_years` and is filtered by the fiscal-year scope.

### 📝 `invoice_items` — invoice items

```sql
CREATE TABLE invoice_items (
    id BIGINT UNSIGNED PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NULL,
    product_id BIGINT UNSIGNED NULL,
    transaction_id BIGINT UNSIGNED NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    unit_discount DECIMAL(10,2) NOT NULL,
    vat DECIMAL(10,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
);
```

## 🔐 Access-control tables

### `users` — users

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Roles and permissions

```sql
CREATE TABLE roles (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Permissions
CREATE TABLE permissions (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Assign roles to users
CREATE TABLE model_has_roles (
    role_id BIGINT NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,

    PRIMARY KEY (role_id, model_id, model_type)
);

-- Assign permissions to models
CREATE TABLE model_has_permissions (
    permission_id BIGINT NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,

    PRIMARY KEY (permission_id, model_id, model_type)
);

-- Link roles and permissions
CREATE TABLE role_has_permissions (
    permission_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,

    PRIMARY KEY (permission_id, role_id)
);
```

## 🗂️ Indexes and optimization

### Important indexes

- `subjects`: unique `(fiscal_year_id, code)` and the `parent_id` foreign key support the account tree and prevent duplicate account codes.
- `products`: unique `(fiscal_year_id, code)`, plus foreign keys on `group` and `subject_id`, link products to product groups and accounting subjects.
- `configs`: unique `(key, fiscal_year_id)` separates settings for each fiscal year.
- `bank_accounts`: unique `(number, fiscal_year_id)`, plus the `bank_id` foreign key, support bank-account management.
- `invoices`: a unique index on `number` and foreign keys to users, documents, fiscal years, and customers maintain data integrity.
- `fiscal_years`: unique `(company_id, year)` prevents duplicate years within a company.
- `fiscal_year_user`: foreign keys on `fiscal_year_id` and `user_id` maintain user access to fiscal years.

## 🔄 Migrations and seeders

### Migration order

Migrations run in timestamp order; see `database/migrations` for the complete list and database structure. `2026_10_06_124405_create_fiscal_years_table.php` creates `fiscal_years`, moves period references from `company_id` to `fiscal_year_id`, renames `company_user` to `fiscal_year_user`, and removes year and closing columns from `companies`. Existing IDs are preserved.

### Main seeders

`DatabaseSeeder::run(?int $fiscalYearId = null)` gets the fiscal-year ID from its argument or `getActiveFiscalYear()`, temporarily stores it in the configuration, and then calls `CompanySeeder` and the period-data seeders. These include warehouse, subject, configuration, bank, group, HR-structure, and role/permission seeders. The previous configuration value is restored after seeding. For ID 1, `CompanySeeder` creates or updates the default company and the current fiscal year; for other IDs, the fiscal year must already exist. The migration that creates `fiscal_years` copies fiscal-year data from existing company rows but does not create a fiscal year on a fresh, empty database. In that case, `CompanySeeder` creates fiscal year ID 1.

### Example subject seeder

```php
// SubjectSeeder.php
use Illuminate\Support\Facades\DB;

public function run(): void
{
    DB::table('subjects')->insert([
        ['id' => 1, 'code' => '010', 'name' => 'بانکها', 'parent_id' => null, 'type' => 'both', 'fiscal_year_id' => 1],
        ['id' => 2, 'code' => '040', 'name' => 'هزینه ها', 'parent_id' => null, 'type' => 'debtor', 'fiscal_year_id' => 1],
        ['id' => 3, 'code' => '011', 'name' => 'موجودیهای نقدی', 'parent_id' => null, 'type' => 'both', 'fiscal_year_id' => 1],
        // ... Dozens of additional rows for the base chart of accounts ...
    ]);
}
```

## 🔒 Database security

### Access control and fiscal-year scope

```php
// Document.php
use App\Models\Scopes\FiscalYearScope;

class Document extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }
}

// FiscalYearScope.php
use Illuminate\Database\Eloquent\{Builder, Model, Scope};

class FiscalYearScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('fiscal_year_id', getActiveFiscalYear());
    }
}
```

This scope returns only records from the active fiscal year. Middleware and controllers separately check user access to the selected year; the scope does not replace permission checks or access assignments.


### Audit Trail

There is currently no migration in the repository that creates an `audit_logs` table. If an audit trail is required, add a migration, model, and change-recording logic appropriate to the project, or use an existing package such as [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog).
