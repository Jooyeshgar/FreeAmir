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
│   └── model_has_permissions # ارتباط کاربر-مجوز
├── 🏢 مدیریت شرکت‌ها
│   ├── companies             # شرکت‌ها
│   ├── fiscal_years          # سال‌های مالی
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
companies (1) ──→ (N) fiscal_years
    │
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
    name VARCHAR(255) NOT NULL,
    logo VARCHAR(255) NULL,
    address TEXT NULL,
    economical_code VARCHAR(15) NULL,
    national_code VARCHAR(12) NULL,
    postal_code VARCHAR(10) NULL,
    phone_number VARCHAR(15) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**نکات مهم:**
- هر شرکت مجموعه‌ای مستقل از داده‌ها دارد
- جداسازی داده‌ها بر اساس `company_id` انجام می‌شود
- یک کاربر می‌تواند به چند شرکت دسترسی داشته باشد

### 📅 جدول `fiscal_years` - سال‌های مالی

```sql
CREATE TABLE fiscal_years (
    id BIGINT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_closed BOOLEAN DEFAULT FALSE,
    company_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (company_id) REFERENCES companies(id)
);
```

**نکات مهم:**
- هر شرکت چندین سال مالی دارد
- تمام اسناد و تراکنش‌ها به سال مالی مشخصی تعلق دارند
- امکان کلون کردن داده‌ها بین سال‌های مالی وجود دارد

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
- `value` مثبت = بدهکار، منفی = بستانکار
- هر تراکنش به یک سرفصل و سند تعلق دارد
- مجموع `value` در هر سند باید صفر باشد (موازنه)

**مثال تراکنش فروش:**
```sql
-- سند فروش 100,000 تومان نقدی
INSERT INTO transactions VALUES
(1, 'cash_account_id', 'document_id', 'user_id', 'دریافت نقد', 100000),
(2, 'sales_account_id', 'document_id', 'user_id', 'فروش کالا', -100000);
-- مجموع: 100000 + (-100000) = 0 ✓
```

### 👤 جدول `customers` - مشتریان

```sql
CREATE TABLE customers (
    id BIGINT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    tel VARCHAR(20) NULL,
    mobile VARCHAR(15) NULL,
    fax VARCHAR(20) NULL,
    address TEXT NULL,
    email VARCHAR(255) NULL,
    web_page VARCHAR(255) NULL,
    desc TEXT NULL,
    subject_id BIGINT NULL, -- ارتباط با سرفصل
    company_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

**نکات مهم:**
- هر مشتری به یک سرفصل "حساب‌های دریافتنی" متصل است
- امکان گروه‌بندی مشتریان
- اطلاعات تماس کامل

### 📦 جدول `products` - کالاها

```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NULL,
    unit VARCHAR(20) NULL,
    description TEXT NULL,
    buy_price DECIMAL(10,2) NULL,
    sell_price DECIMAL(10,2) NULL,
    subject_id BIGINT NULL, -- حساب موجودی
    company_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 🧾 جدول `invoices` - فاکتورها

```sql
CREATE TABLE invoices (
    id BIGINT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    customer_id BIGINT NOT NULL,
    document_id BIGINT NULL, -- اتصال به سند حسابداری
    total_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    description TEXT NULL,
    company_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 📝 جدول `invoice_items` - اقلام فاکتور

```sql
CREATE TABLE invoice_items (
    id BIGINT PRIMARY KEY,
    invoice_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
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

### سیستم نقش‌ها و مجوزها (Spatie Permission)

```sql
-- نقش‌ها
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
```

## 🗂️ ایندکس‌ها و بهینه‌سازی

### ایندکس‌های مهم

```sql
-- جدول transactions برای کوئری‌های سریع
CREATE INDEX idx_transactions_subject_date ON transactions(subject_id, created_at);
CREATE INDEX idx_transactions_document ON transactions(document_id);

-- جدول subjects برای جستجوی درختی
CREATE INDEX idx_subjects_parent ON subjects(parent_id);
CREATE INDEX idx_subjects_company_code ON subjects(company_id, code);

-- جدول documents
CREATE INDEX idx_documents_company_date ON documents(company_id, date);
CREATE INDEX idx_documents_number ON documents(number);

-- جداسازی شرکت‌ها
CREATE INDEX idx_customers_company ON customers(company_id);
CREATE INDEX idx_products_company ON products(company_id);
```

### کوئری‌های بهینه

```sql
-- محاسبه مانده حساب (بهینه)
SELECT 
    SUM(value) as balance
FROM transactions t
JOIN documents d ON t.document_id = d.id
WHERE t.subject_id = ? 
  AND d.company_id = ?
  AND d.date <= ?;

-- گزارش دفتر کل
SELECT 
    d.date,
    d.number,
    t.desc,
    t.value,
    @running_balance := @running_balance + t.value as balance
FROM transactions t
JOIN documents d ON t.document_id = d.id
CROSS JOIN (SELECT @running_balance := 0) r
WHERE t.subject_id = ?
ORDER BY d.date, d.number;
```

## 🔄 مایگریشن‌ها و Seeder ها

### ترتیب اجرای مایگریشن‌ها

```bash
1. create_users_table
2. create_companies_table
3. create_fiscal_years_table
4. create_subjects_table
5. create_documents_table
6. create_transactions_table
7. create_customers_table
8. create_products_table
9. create_invoices_table
10. create_invoice_items_table
# و بقیه جداول...
```

### Seeder های اصلی

```php
// DatabaseSeeder.php
public function run()
{
    $this->call([
        PermissionSeeder::class,    // مجوزها و نقش‌ها
        CompanySeeder::class,       // شرکت پیش‌فرض
        SubjectSeeder::class,       // سرفصل‌های پایه
        UserSeeder::class,          // کاربر مدیر
        ConfigSeeder::class,        // تنظیمات پیش‌فرض
    ]);
}
```

### نمونه Seeder برای سرفصل‌ها

```php
// SubjectSeeder.php
public function run()
{
    $company = Company::first();
    
    // دارایی‌ها
    $assets = Subject::create([
        'code' => '1',
        'name' => 'دارایی‌ها',
        'company_id' => $company->id,
        'type' => 'debtor'
    ]);
    
    // دارایی‌های جاری
    $currentAssets = Subject::create([
        'code' => '1.1',
        'name' => 'دارایی‌های جاری', 
        'parent_id' => $assets->id,
        'company_id' => $company->id,
        'type' => 'debtor'
    ]);
    
    // نقد و بانک
    Subject::create([
        'code' => '1.1.1',
        'name' => 'نقد و بانک',
        'parent_id' => $currentAssets->id,
        'company_id' => $company->id,
        'type' => 'debtor'
    ]);
}
```

## 🔒 امنیت دیتابیس

### کنترل دسترسی

```php
// در Model ها همیشه فیلتر شرکت اعمال شود
class Document extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (session('active-company-id')) {
                $builder->where('company_id', session('active-company-id'));
            }
        });
    }
}
```

### Soft Delete برای داده‌های مهم

```php
// برای جداول حساس
Schema::table('documents', function (Blueprint $table) {
    $table->softDeletes();
});
```

### Audit Trail

```php
// ردگیری تغییرات
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('table_name');
    $table->bigInteger('record_id');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->string('action'); // create, update, delete
    $table->foreignId('user_id');
    $table->timestamp('created_at');
});
```

## 📊 محاسبات مالی

### قوانین مهم

1. **موازنه اسناد**: مجموع بدهکار = مجموع بستانکار
```sql
SELECT document_id, SUM(value) as balance
FROM transactions 
GROUP BY document_id
HAVING balance != 0; -- اسناد نامتوازن
```

2. **محاسبه مانده حساب**:
```sql
SELECT 
    s.name,
    SUM(t.value) as balance,
    CASE 
        WHEN SUM(t.value) > 0 THEN 'بدهکار'
        WHEN SUM(t.value) < 0 THEN 'بستانکار'
        ELSE 'صفر'
    END as balance_type
FROM subjects s
LEFT JOIN transactions t ON s.id = t.subject_id
GROUP BY s.id, s.name;
```

3. **کنترل یکتایی شماره سند**:
```sql
SELECT number, COUNT(*) 
FROM documents 
WHERE company_id = ?
GROUP BY number 
HAVING COUNT(*) > 1; -- شماره‌های تکراری
```

## 🔧 نکات عملیاتی

### Backup و Restore

```bash
# پشتیبان‌گیری
mysqldump -u user -p amir_db > backup.sql

# بازیابی
mysql -u user -p amir_db < backup.sql
```

### بهینه‌سازی کارایی

```sql
-- آنالیز جداول
ANALYZE TABLE transactions, documents, subjects;

-- بهینه‌سازی جداول
OPTIMIZE TABLE transactions, documents, subjects;

-- نمایش کوئری‌های کند
SHOW FULL PROCESSLIST;
```

### مانیتورینگ

```sql
-- اندازه جداول
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.TABLES 
WHERE table_schema = 'amir_db'
ORDER BY size_mb DESC;

-- آمار تراکنش‌ها
SELECT 
    DATE(created_at) as date,
    COUNT(*) as transaction_count,
    SUM(ABS(value)) as total_amount
FROM transactions 
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

---

**نکته مهم**: همیشه قبل از تغییرات ساختاری، پشتیبان مناسب از دیتابیس تهیه کنید و تغییرات را ابتدا در محیط تست آزمایش کنید.
