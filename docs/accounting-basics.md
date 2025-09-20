# مبانی حسابداری برای برنامه‌نویسان

این راهنما برای توسعه‌دهندگانی نوشته شده که می‌خواهند در پروژه **امیر** مشارکت کنند ولی با مفاهیم حسابداری آشنایی کاملی ندارند.

## چرا توسعه‌دهنده باید حسابداری بداند؟

عدم درک مفاهیم حسابداری منجر به:
- ایجاد باگ‌های منطق کسب‌وکار
- طراحی نادرست پایگاه داده
- عدم رعایت اصول موازنه مالی
- ایجاد گزارشات نادرست

**نکته مهم**: در سیستم‌های مالی، یک خطای کوچک برنامه‌نویسی می‌تواند منجر به عدم تطبیق حساب‌ها و مشکلات جدی مالی شود.

## مبانی حسابداری

### بدهکار و بستانکار چیست؟

**بدهکار (Debit)**: سمت چپ حساب - اضافه شدن دارایی یا کاهش بدهی
**بستانکار (Credit)**: سمت راست حساب - کاهش دارایی یا اضافه شدن بدهی

```
مثال: خرید ۱۰۰,۰۰۰ تومان کالا نقداً
┌─────────────────┬──────────┬──────────┐
│ شرح             │ بدهکار  │ بستانکار│
├─────────────────┼──────────┼──────────┤
│ حساب کالا       │ 100,000  │    -     │
│ حساب صندوق      │    -     │ 100,000  │
│ مجموع           │ 100,000  │ 100,000  │
└─────────────────┴──────────┴──────────┘
```

### قانون طلایی: موازنه

**در هر سند، مجموع بدهکار = مجموع بستانکار**

این قانون در کد باید همیشه کنترل شود:

```php
// مثال کنترل موازنه در کد
$totalDebit = $document->transactions->sum('debit_amount');
$totalCredit = $document->transactions->sum('credit_amount');

if ($totalDebit !== $totalCredit) {
    throw new DocumentServiceException('سند متوازن نیست');
}
```

## ساختار سرفصل‌های حسابداری (Chart of Accounts)

### سرفصل‌های اصلی

```
1. دارایی‌ها (Assets)
   ├── 1.1 دارایی‌های جاری
   │   ├── 1.1.1 نقد و بانک
   │   ├── 1.1.2 حساب‌های دریافتنی
   │   └── 1.1.3 موجودی کالا
   ├── 1.2 دارایی‌های ثابت
   │   ├── 1.2.1 ساختمان
   │   ├── 1.2.2 ماشین‌آلات
   │   └── 1.2.3 وسایل نقلیه

2. بدهی‌ها (Liabilities)
   ├── 2.1 بدهی‌های جاری
   │   ├── 2.1.1 حساب‌های پرداختنی
   │   ├── 2.1.2 مالیات پرداختنی
   │   └── 2.1.3 حقوق پرداختنی
   ├── 2.2 بدهی‌های بلندمدت

3. سرمایه (Equity)
   ├── 3.1 سرمایه اولیه
   ├── 3.2 سود انباشته
   └── 3.3 سود سال جاری

4. درآمدها (Income)
   ├── 4.1 درآمد فروش
   ├── 4.2 درآمدهای غیرعملیاتی
   └── 4.3 سایر درآمدها

5. هزینه‌ها (Expenses)
   ├── 5.1 بهای کالای فروخته شده
   ├── 5.2 هزینه‌های عملیاتی
   └── 5.3 هزینه‌های مالی
```

### کدینگ حسابداری

در امیر، هر سرفصل کد منحصربه‌فردی دارد:

```php
// مثال: ساختار کدینگ در جدول accounts
'code'   => '1.1.1.001',  // صندوق شماره 1
'title'  => 'صندوق فروشگاه',
'parent' => '1.1.1',      // زیرمجموعه نقد و بانک
'type'   => 'asset'       // نوع حساب
```

## گروه‌بندی مشتریان و ارتباط با سرفصل‌ها

### مشتریان (Customers)
```php
// هر مشتری به یک سرفصل "حساب‌های دریافتنی" متصل است
$customer = new Customer([
    'name' => 'شرکت الف',
    'account_id' => $receivableAccount->id  // اتصال به سرفصل
]);
```

### کالاها و خدمات
```php
// هر کالا با سرفصل‌های مختلف ارتباط دارد
$product = new Product([
    'name' => 'محصول A',
    'inventory_account_id' => $inventoryAccount->id,  // حساب موجودی
    'income_account_id' => $salesAccount->id,         // حساب درآمد فروش
    'expense_account_id' => $cogsAccount->id          // حساب بهای تمام شده
]);
```

## سند و تراکنش‌ها

### ساختار سند

```php
class Document {
    public $id;
    public $date;           // تاریخ سند
    public $number;         // شماره سند (یکتا در سال مالی)
    public $description;    // شرح کلی سند
    public $fiscal_year_id; // سال مالی
    public $company_id;     // شرکت
    
    public function transactions() {
        return $this->hasMany(Transaction::class);
    }
}

class Transaction {
    public $document_id;
    public $account_id;     // سرفصل
    public $debit_amount;   // مبلغ بدهکار
    public $credit_amount;  // مبلغ بستانکار
    public $description;    // شرح ردیف
}
```

### نمونه سند در کد

```php
// مثال: ثبت فروش ۵۰۰,۰۰۰ تومان نقدی
$document = Document::create([
    'date' => '2024-01-01',
    'number' => '1001',
    'description' => 'فروش کالا به مشتری',
    'fiscal_year_id' => $currentFiscalYear->id
]);

// ردیف 1: افزایش صندوق (بدهکار)
Transaction::create([
    'document_id' => $document->id,
    'account_id' => $cashAccount->id,
    'debit_amount' => 500000,
    'credit_amount' => 0,
    'description' => 'دریافت وجه نقد'
]);

// ردیف 2: افزایش درآمد فروش (بستانکار)
Transaction::create([
    'document_id' => $document->id,
    'account_id' => $salesAccount->id,
    'debit_amount' => 0,
    'credit_amount' => 500000,
    'description' => 'درآمد حاصل از فروش'
]);
```

## چرخه حسابداری در FreeAmir

### 1. ثبت سند
```php
// استفاده از DocumentService
$documentService = new DocumentService();
$document = $documentService->createDocument([
    'date' => $date,
    'description' => $description,
    'transactions' => $transactionsData
]);
```

### 2. اعتبارسنجی
```php
// کنترل‌های ضروری در DocumentService
private function validateDocument($data) {
    // 1. کنترل یکتایی شماره سند
    $this->checkUniqueDocumentNumber($data['number']);
    
    // 2. کنترل موازنه
    $this->checkBalance($data['transactions']);
    
    // 3. کنترل صحت حساب‌ها
    $this->validateAccounts($data['transactions']);
}
```

### 3. ذخیره و ایجاد تراکنش‌ها
```php
DB::transaction(function() use ($documentData) {
    $document = Document::create($documentData);
    
    foreach($transactions as $transaction) {
        $document->transactions()->create($transaction);
    }
});
```

## سال مالی و چندشرکته بودن

### مفهوم سال مالی
سال مالی دوره‌ای است (معمولاً ۱۲ ماه) که حساب‌ها در آن ثبت می‌شوند.

```php
class FiscalYear {
    public $title;          // "سال مالی 1403"
    public $start_date;     // "1403/01/01"
    public $end_date;       // "1403/12/29"
    public $is_closed;      // بسته شده یا خیر
    public $company_id;     // متعلق به کدام شرکت
}
```

### جداسازی داده‌ها
```php
// همه کوئری‌ها باید شامل فیلتر سال مالی و شرکت باشند
$documents = Document::where('fiscal_year_id', $currentFiscalYear->id)
                    ->where('company_id', $currentCompany->id)
                    ->get();
```

### مهاجرت و کلون سال مالی
```php
// ایجاد سال مالی جدید بر اساس سال قبل
php artisan fiscal-year:clone --from=1403 --to=1404
```

## گزارش‌های مالی

### دفتر روزنامه (Journal)
لیست تمام اسناد و تراکنش‌ها به ترتیب تاریخ

```php
// کوئری دفتر روزنامه
$journalEntries = Transaction::join('documents', 'transactions.document_id', '=', 'documents.id')
    ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
    ->where('documents.fiscal_year_id', $fiscalYearId)
    ->orderBy('documents.date')
    ->orderBy('documents.number')
    ->get();
```

### دفتر کل (General Ledger)
گردآوری تراکنش‌ها بر اساس هر سرفصل

```php
// کوئری دفتر کل برای یک حساب
$ledger = Transaction::join('documents', 'transactions.document_id', '=', 'documents.id')
    ->where('transactions.account_id', $accountId)
    ->where('documents.fiscal_year_id', $fiscalYearId)
    ->orderBy('documents.date')
    ->get();
```

### ترازنامه (Balance Sheet)
```php
// محاسبه مانده حساب‌ها
$accountBalance = Transaction::where('account_id', $accountId)
    ->sum('debit_amount') - Transaction::where('account_id', $accountId)
    ->sum('credit_amount');
```

### سود و زیان (Income Statement)
```php
// محاسبه درآمدها و هزینه‌ها
$totalIncome = Account::where('type', 'income')
    ->join('transactions', 'accounts.id', '=', 'transactions.account_id')
    ->sum('transactions.credit_amount');

$totalExpenses = Account::where('type', 'expense')
    ->join('transactions', 'accounts.id', '=', 'transactions.account_id')
    ->sum('transactions.debit_amount');

$netIncome = $totalIncome - $totalExpenses;
```

## تنظیمات و پیکربندی

### حساب‌های پیش‌فرض
```php
// در جدول configs
$defaultCashAccount = Config::where('key', 'default_cash_account')
                           ->where('company_id', $companyId)
                           ->value('value');
```

### بارگذاری تنظیمات
```php
// در سرویس‌ها همیشه تنظیمات شرکت جاری بارگذاری شود
class CompanyConfigService {
    public function getDefaultAccount($type) {
        return Config::where('company_id', auth()->user()->current_company_id)
                    ->where('key', "default_{$type}_account")
                    ->value('value');
    }
}
```

## امنیت و کنترل دسترسی

### نقش‌ها در سیستم مالی
```php
// مجوزهای مربوط به حسابداری
'accounting.documents.create'
'accounting.documents.edit'
'accounting.documents.delete'
'accounting.reports.view'
'accounting.settings.manage'
```

### محدودیت دسترسی به داده‌ها
```php
// Middleware برای کنترل دسترسی شرکت
class EnsureCompanyAccess {
    public function handle($request, Closure $next) {
        $document = Document::findOrFail($request->id);
        
        if ($document->company_id !== auth()->user()->current_company_id) {
            abort(403, 'دسترسی مجاز نیست');
        }
        
        return $next($request);
    }
}
```

## نکات مهم برای توسعه‌دهندگان

### 1. همیشه موازنه را کنترل کنید
```php
// قبل از ذخیره سند
$totalDebit = collect($transactions)->sum('debit_amount');
$totalCredit = collect($transactions)->sum('credit_amount');

if ($totalDebit != $totalCredit) {
    throw new ValidationException('سند متوازن نیست');
}
```

### 2. از Transaction در پایگاه داده استفاده کنید
```php
DB::transaction(function() {
    // عملیات مالی
});
```

### 3. همیشه لاگ تغییرات مالی را ثبت کنید
```php
// برای رهگیری تغییرات مهم
DocumentHistory::create([
    'document_id' => $document->id,
    'action' => 'updated',
    'user_id' => auth()->id(),
    'changes' => json_encode($changes)
]);
```

### 4. اعداد اعشاری را درست مدیریت کنید
```php
// استفاده از bcmath برای محاسبات مالی دقیق
$total = bcadd($amount1, $amount2, 2); // 2 رقم اعشار
```

---

**نکته نهایی**: هرگز فراموش نکنید که در سیستم‌های مالی، دقت بیش از سرعت اولویت دارد. همیشه کدهای خود را چندین بار تست کنید.
