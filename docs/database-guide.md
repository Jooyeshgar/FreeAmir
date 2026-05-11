<div dir="rtl" align="right">

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
│   └── company_user          # شرکت‌های در دسترس هر کاربر
├── 🏢 مدیریت شرکت‌ها
│   ├── companies             # شرکت‌ها
│   └── configs               # تنظیمات شرکت
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
companies
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
    phone_number VARCHAR(11) NULL,
    fiscal_year INT UNSIGNED NOT NULL
);
```

**نکات مهم:**
- هر شرکت مجموعه‌ای مستقل از داده‌ها دارد.
- جداسازی داده‌ها از طریق ستون `company_id` و اسکوپ سراسری `FiscalYearScope` انجام می‌شود که مقدار `session('active-company-id')` را روی کوئری‌ها اعمال می‌کند.
- ستون `fiscal_year` برای نمایش سال مالی شرکت استفاده می‌شود.
- دسترسی کاربران به شرکت‌ها از طریق جدول میانی `company_user` مدیریت می‌شود و هر کاربر می‌تواند به چند شرکت دسترسی داشته باشد.

### 📅 سال‌های مالی

در پیاده‌سازی فعلی جدول مستقلی با نام `fiscal_years` وجود ندارد. هر رکورد از جدول `companies` نماینده یک سال مالی است و انتخاب سال فعال از طریق شناسه شرکت فعال (ذخیره‌شده در `session('active-company-id')`) انجام می‌شود. اسکوپ `FiscalYearScope` روی مدل‌های وابسته اعمال شده تا به صورت خودکار داده‌ها را بر اساس شرکت فعال فیلتر کند.

### 📊 جدول `subjects` - سرفصل‌های حسابداری

```sql
CREATE TABLE subjects (
    id BIGINT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(60) NOT NULL,
    parent_id BIGINT NULL,
    type ENUM('debtor', 'creditor', 'both') DEFAULT 'both',
    company_id BIGINT NOT NULL,
    subjectable_type VARCHAR(255) NULL, -- Polymorphic
    subjectable_id BIGINT NULL,         -- Polymorphic
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (parent_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_code (company_id, code)
);
```

**نکات مهم:**
- ساختار درختی (Tree Structure) با `parent_id`
- کدینگ منحصربه‌فرد در هر شرکت
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
    company_id BIGINT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
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
    cell VARCHAR(15) NULL DEFAULT '',
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
    company_id BIGINT UNSIGNED NOT NULL,

    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (group_id) REFERENCES customer_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (introducer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
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
    company_id BIGINT UNSIGNED NOT NULL,

    FOREIGN KEY (`group`) REFERENCES product_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_product_code (company_id, code)
);
```

**نکات مهم:**
- کد کالا در سطح هر شرکت یکتا است (ایندکس ترکیبی `company_id + code`).
- ستون‌های `quantity` و `quantity_warning` برای مدیریت موجودی و هشدار کمبود استفاده می‌شوند و `oversell` امکان فروش بیش از موجودی را کنترل می‌کند.
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
    company_id BIGINT UNSIGNED NULL,
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
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
```

**نکات مهم:**
- فیلد `number` برای هر فاکتور یکتا است.
- ستون‌های `addition`، `subtraction`، `vat` و `cash_payment` برای جمع مبالغ جانبی و پرداخت نقدی استفاده می‌شوند.
- ستون `company_id` به جدول `companies` متصل است و با اسکوپ سال مالی فیلتر می‌شود.

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

- `subjects`: ایندکس یکتا روی `(company_id, code)` و کلید خارجی `parent_id` برای مدیریت ساختار درختی و جلوگیری از تکرار کد سرفصل‌ها.
- `products`: ایندکس یکتای `(company_id, code)` به‌همراه کلیدهای خارجی روی `group` و `subject_id` برای اتصال به گروه کالا و سرفصل حسابداری.
- `configs`: ایندکس یکتای `(key, company_id)` برای جداسازی تنظیمات هر شرکت.
- `bank_accounts`: ایندکس یکتای `(number, company_id)` به‌همراه کلید خارجی `bank_id` جهت مدیریت حساب‌های بانکی.
- `invoices`: ایندکس یکتای ستون `number` و کلیدهای خارجی به کاربران، اسناد، شرکت و مشتری برای یکپارچگی داده‌ها.
- `company_user`: کلیدهای خارجی روی `company_id` و `user_id` مسئول نگه‌داری ارتباط کاربران و شرکت‌های مجاز هستند.

## 🔄 مایگریشن‌ها و سیدرها

### ترتیب اجرای مایگریشن‌ها

```bash
1. 2014_04_02_193005_create_translations_table.php
2. 2014_10_12_000000_create_users_table.php
3. 2014_10_12_100000_create_password_reset_tokens_table.php
4. 2019_08_19_000000_create_failed_jobs_table.php
5. 2019_12_14_000001_create_personal_access_tokens_table.php
6. 2024_02_15_102710_create_companies_table.php
7. 2024_03_07_110922_create_banks_table.php
8. 2024_03_07_112403_create_document_table.php
9. 2024_03_07_112600_create_config_table.php
10. 2024_03_07_112610_create_subjects_table.php
11. 2024_03_07_112700_create_cust_groups_table.php
12. 2024_03_07_112852_create_customers_table.php
13. 2024_03_07_113542_create_invoices_table.php
14. 2024_03_07_114328_create_transactions_table.php
15. 2024_03_07_114627_create_payments_table.php
16. 2024_03_07_114800_create_product_groups_table.php
17. 2024_03_07_114819_create_products_table.php
18. 2024_03_07_115800_create_invoice_items_table.php
19. 2024_03_08_111100_create_bank_accounts_table.php
20. 2024_03_08_111150_create_cheques_table.php
21. 2024_03_08_111160_create_cheque_history_table.php
22. 2024_04_18_113959_create_permission_tables.php
23. 2024_08_15_142029_create_company_user_table.php
```

### سیدرهای اصلی

```php
// DatabaseSeeder.php
public function run()
{
    $this->call([
        CompanySeeder::class,             // ایجاد شرکت اولیه
        SubjectSeeder::class,             // سرفصل‌های پایه
        ConfigSeeder::class,              // تنظیمات پیش‌فرض شرکت
        BankSeeder::class,                // بانک‌ها
        CustomerGroupSeeder::class,       // گروه‌های مشتریان
        ProductGroupSeeder::class,        // گروه‌های کالا
        RolesAndPermissionsSeeder::class, // نقش‌ها و مجوزهای پایه
    ]);
}
```

### نمونه سیدر برای سرفصل‌ها

```php
// SubjectSeeder.php
use Illuminate\Support\Facades\DB;

public function run(): void
{
    DB::table('subjects')->insert([
        ['id' => 1, 'code' => '010', 'name' => 'بانکها', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
        ['id' => 2, 'code' => '040', 'name' => 'هزینه ها', 'parent_id' => null, 'type' => 'debtor', 'company_id' => 1],
        ['id' => 3, 'code' => '011', 'name' => 'موجودیهای نقدی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
        // ... ده‌ها سطر دیگر برای سرفصل‌های پایه ...
    ]);
}
```

## 🔒 امنیت دیتابیس

### کنترل دسترسی

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
        $builder->where('company_id', session('active-company-id'));
    }
}
```


### Audit Trail

در حال حاضر در مخزن، مایگریشنی برای ایجاد جدول `audit_logs` وجود ندارد. در صورت نیاز به Audit Trail باید مایگریشن، مدل و منطق مربوط به ثبت تغییرات را متناسب با نیاز پروژه اضافه کنید یا از پکیج‌های آماده (مانند [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog)) بهره ببرید.

</div>
