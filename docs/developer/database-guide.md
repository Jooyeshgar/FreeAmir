<div dir="rtl">

# راهنمای دیتابیس امیر

این راهنما ساختار پایگاه داده، روابط بین جداول و نکات مهم برای کار با دیتابیس امیر را توضیح می‌دهد.

## 🗃️ ساختار کلی دیتابیس

پایگاه داده امیر بر اساس اصول حسابداری و نیازهای سیستم‌های مالی طراحی شده است.

### جداول اصلی

```
امیر دیتابیس
├── 👥 مدیریت کاربران
│   ├── users                 # کاربران سیستم
│   ├── roles                 # نقش‌ها
│   ├── permissions           # مجوزها
│   ├── model_has_permissions # ارتباط مدل‌-مجوز
│   ├── model_has_roles       # ارتباط مدل‌-نقش
│   ├── role_has_permissions  # اتصال نقش و مجوز
├── 🏢 مدیریت شرکت‌ها
│   ├── companies             # شرکت‌ها
│   ├── fiscal_years          # سال‌های مالی هر شرکت
│   ├── fiscal_year_user      # دسترسی کاربران به سال‌های مالی
│   └── configs               # تنظیمات سال مالی و تنظیمات سراسری
├── 📊 هسته حسابداری
│   ├── subjects              # سرفصل‌های حسابداری
│   ├── documents             # اسناد حسابداری
│   └── transactions          # تراکنش‌های مالی
├── 👤 مدیریت مشتریان
│   ├── customers             # مشتریان
│   └── customer_groups       # گروه‌های مشتری
├── 📦 مدیریت کالا
│   ├── products              # کالاها
│   └── product_groups        # گروه‌های کالا
├── 🧾 فاکتورها
│   ├── invoices              # فاکتورها
│   └── invoice_items         # اقلام فاکتور
├── 🏦 مدیریت بانک
│   ├── banks                 # بانک‌ها
│   ├── bank_accounts         # حساب‌های بانکی
│   ├── cheques               # چک‌ها
│   └── cheque_histories      # تاریخچه چک‌ها
└── 💰 پرداخت‌ها
    └── payments              # پرداخت‌ها
```

## 🔗 روابط بین جداول

### نمودار ERD ساده‌شده

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

customers ──→ subjects (حساب دریافتنی)
products ──→ subjects (حساب موجودی)
```

## 📋 توضیح جداول اصلی

### 🏢 جدول `companies` - شرکت‌ها

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

**نکات مهم:**
- این جدول هویت کسب‌وکار را نگه می‌دارد؛ هر شرکت می‌تواند چند سال مالی داشته باشد.
- سال و وضعیت بستن دوره در جدول `fiscal_years` قرار دارد.

### 📅 جدول `fiscal_years` - سال‌های مالی

هر ردیف به یک شرکت تعلق دارد. ستون `year` سال شمسی را نگه می‌دارد و ترکیب `(company_id, year)` یکتا است. ستون‌های `closed_at` و `closed_by` وضعیت بستن دوره را نشان می‌دهند؛ `pl_document_id`، `closing_document_id` و `closing_recalculation_step` اطلاعات عملیات بستن سال را نگه می‌دارند.

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

رکوردهای مالی با `fiscal_year_id` به جدول `fiscal_years` متصل می‌شوند و `FiscalYearScope` آن‌ها را بر اساس شناسه فعال برمی‌گرداند. شناسه فعال از `getActiveFiscalYear()` گرفته می‌شود؛ در درخواست وب از پیکربندی یا کوکی `active-fiscal-year-id` خوانده می‌شود.

دسترسی کاربر به سال مالی از جدول میانی `fiscal_year_user` برقرار می‌شود. این رابطه مجوزهای نقش را جایگزین نمی‌کند: نقش/مجوز تعیین می‌کند کاربر چه عملی انجام دهد و تخصیص سال مالی تعیین می‌کند روی داده‌های کدام دوره کار کند.

```text
fiscal_year_user: fiscal_year_id → fiscal_years.id, user_id → users.id
```

### 📊 جدول `subjects` - سرفصل‌های حسابداری

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

**نکات مهم:**
- ساختار درختی (Tree Structure) با `parent_id`
- کدینگ منحصربه‌فرد در هر سال مالی
- ارتباط Polymorphic با سایر entities (مشتری، کالا، و...)
- انواع: `debtor` (بدهکار)، `creditor` (بستانکار)، `both` (هردو)

**مثال ساختار سرفصل:**
```
1. دارایی‌ها
   1.1 دارایی‌های جاری
       1.1.1 نقد و بانک
             1.1.1.001 صندوق
             1.1.1.002 بانک ملت
   1.2 دارایی‌های ثابت
       1.2.1 ساختمان
```

### 📄 جدول `documents` - اسناد حسابداری

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

**نکات مهم:**
- شماره سند (`number`) در هر سال مالی یکتا است
- هر سند می‌تواند چندین تراکنش داشته باشد
- امکان تأیید سند توسط کاربر مجاز
- ردگیری کاربر ایجادکننده

### 💱 جدول `transactions` - تراکنش‌های مالی

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

**نکات مهم:**
- مقدار `value` مثبت = بستانکار، منفی = بدهکار (مطابق منطق «بستانکار - بدهکار» در سرویس اسناد)
- هر تراکنش به یک سرفصل و سند تعلق دارد
- مجموع `value` در هر سند باید صفر باشد (موازنه)

**مثال تراکنش فروش:**
```sql
-- سند فروش 100,000 تومان نقدی
INSERT INTO transactions VALUES
(1, 'cash_account_id', 'document_id', 'user_id', 'دریافت نقد', -100000),
(2, 'sales_account_id', 'document_id', 'user_id', 'فروش کالا', 100000);
-- مجموع: -100000 + 100000 = 0 ✓
```

### 👤 جدول `customers` - مشتریان

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

**نکات مهم:**
- هر مشتری به یک سرفصل "حساب‌های دریافتنی" متصل است
- امکان گروه‌بندی مشتریان
- اطلاعات تماس کامل + تنظیمات مالی (سقف اعتبار، مانده اولیه و پرچم‌های نقش خریدار/فروشنده و ...)

### 📦 جدول `products` - کالاها

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

**نکات مهم:**
- کد کالا در هر سال مالی یکتا است (ایندکس ترکیبی `fiscal_year_id + code`).
- ستون‌های `quantity` و `quantity_warning` برای مدیریت موجودی و نقطه سفارش استفاده می‌شوند و `oversell` امکان فروش بیش از موجودی را کنترل می‌کند.
- پس از ایجاد کالا، ستون `subject_id` با استفاده از `SubjectCreatorService` پر می‌شود تا هر کالا سرفصل مرتبط خود را داشته باشد.
- ستون `vat` برای نگهداری نرخ مالیات بر ارزش افزودهٔ کالا استفاده می‌شود و مقدار آن اختیاری است.

### 🧾 جدول `invoices` - فاکتورها

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

**نکات مهم:**
- فیلد `number` برای هر فاکتور یکتا است.
- ستون‌های `addition`، `subtraction`، `vat` و `cash_payment` برای جمع مبالغ جانبی و پرداخت نقدی استفاده می‌شوند.
- ستون `fiscal_year_id` به جدول `fiscal_years` متصل است و با اسکوپ سال مالی فیلتر می‌شود.

### 📝 جدول `invoice_items` - اقلام فاکتور

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

## 🔐 جداول مدیریت دسترسی

### جدول `users` - کاربران

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

### سیستم نقش‌ها و مجوزها

```sql
CREATE TABLE roles (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- مجوزها
CREATE TABLE permissions (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- اختصاص نقش به کاربر
CREATE TABLE model_has_roles (
    role_id BIGINT NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,

    PRIMARY KEY (role_id, model_id, model_type)
);

-- اختصاص مجوز به مدل
CREATE TABLE model_has_permissions (
    permission_id BIGINT NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,

    PRIMARY KEY (permission_id, model_id, model_type)
);

-- ارتباط نقش و مجوز
CREATE TABLE role_has_permissions (
    permission_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,

    PRIMARY KEY (permission_id, role_id)
);
```

## 🗂️ ایندکس‌ها و بهینه‌سازی

### ایندکس‌های مهم

- `subjects`: ایندکس یکتا روی `(fiscal_year_id, code)` و کلید خارجی `parent_id` برای مدیریت ساختار درختی و جلوگیری از تکرار کد سرفصل‌ها.
- `products`: ایندکس یکتای `(fiscal_year_id, code)` به‌همراه کلیدهای خارجی روی `group` و `subject_id` برای اتصال به گروه کالا و سرفصل حسابداری.
- `configs`: ایندکس یکتای `(key, fiscal_year_id)` برای جداسازی تنظیمات هر سال مالی.
- `bank_accounts`: ایندکس یکتای `(number, fiscal_year_id)` به‌همراه کلید خارجی `bank_id` جهت مدیریت حساب‌های بانکی.
- `invoices`: ایندکس یکتای ستون `number` و کلیدهای خارجی به کاربران، اسناد، سال مالی و مشتری برای یکپارچگی داده‌ها.
- `fiscal_years`: یکتایی `(company_id, year)` از ایجاد دوباره یک سال در یک شرکت جلوگیری می‌کند.
- `fiscal_year_user`: کلیدهای خارجی روی `fiscal_year_id` و `user_id` دسترسی کاربر به سال‌های مالی را نگه می‌دارند.

## 🔄 مایگریشن‌ها و سیدرها

### ترتیب اجرای مایگریشن‌ها

مایگریشن‌ها به‌ترتیب زمان اجرا می‌شوند؛ برای فهرست کامل و ساختار مرجع به `database/migrations` رجوع کنید. مایگریشن `2026_10_06_124405_create_fiscal_years_table.php` جدول `fiscal_years` را می‌سازد، ارجاع‌های دوره را از `company_id` به `fiscal_year_id` منتقل می‌کند، pivot را به `fiscal_year_user` تغییر نام می‌دهد و ستون‌های سال و اختتامیه را از `companies` حذف می‌کند. شناسه‌های قبلی حفظ می‌شوند.

### سیدرهای اصلی

`DatabaseSeeder::run(?int $fiscalYearId = null)` شناسه سال مالی فعال را موقتاً در پیکربندی قرار می‌دهد و سپس `CompanySeeder` و سیدرهای داده‌های دوره را اجرا می‌کند؛ ازجمله انبار، سرفصل، تنظیمات، بانک، گروه‌ها، ساختار منابع انسانی و نقش‌ها/مجوزها. این سیدرها به یک سال مالی فعال نیاز دارند. به‌دلیل refactor، پیش از اتکا به راه‌اندازی اولیه یا اجرای سیدرها، پیاده‌سازی فعلی `CompanySeeder` و داده‌های موردنیاز `FiscalYear` را با migrationها تطبیق دهید.

### نمونه سیدر برای سرفصل‌ها

```php
// SubjectSeeder.php
use Illuminate\Support\Facades\DB;

public function run(): void
{
    DB::table('subjects')->insert([
        ['id' => 1, 'code' => '010', 'name' => 'بانکها', 'parent_id' => null, 'type' => 'both', 'fiscal_year_id' => 1],
        ['id' => 2, 'code' => '040', 'name' => 'هزینه ها', 'parent_id' => null, 'type' => 'debtor', 'fiscal_year_id' => 1],
        ['id' => 3, 'code' => '011', 'name' => 'موجودیهای نقدی', 'parent_id' => null, 'type' => 'both', 'fiscal_year_id' => 1],
        // ... ده‌ها سطر دیگر برای سرفصل‌های پایه ...
    ]);
}
```

## 🔒 امنیت دیتابیس

### کنترل دسترسی و دامنه سال مالی

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

این اسکوپ تنها رکوردهای همان سال مالی را برمی‌گرداند. میان‌افزارها و کنترلرها دسترسی کاربر به سال انتخاب‌شده را نیز جداگانه بررسی می‌کنند؛ اسکوپ جایگزین کنترل مجوز یا تخصیص دسترسی نیست.


### Audit Trail

در حال حاضر در مخزن، مایگریشنی برای ایجاد جدول `audit_logs` وجود ندارد. در صورت نیاز به Audit Trail باید مایگریشن، مدل و منطق مربوط به ثبت تغییرات را متناسب با نیاز پروژه اضافه کنید یا از پکیج‌های آماده (مانند [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog)) بهره ببرید.

</div>
