# ساختار پروژه امیر

این راهنما ساختار کلی پروژه امیر و نحوه سازماندهی کدها را توضیح می‌دهد.

## 🏗️ ساختار کلی پروژه

امیر بر اساس **Laravel Framework** پیاده‌سازی شده و از معماری **MVC** پیروی می‌کند.

```
FreeAmir/
├── app/                    # منطق اصلی اپلیکیشن
│   ├── Console/           # دستورات Artisan سفارشی
│   ├── Enums/             # انواع داده‌های ثابت
│   ├── Exceptions/        # مدیریت خطاهای سفارشی
│   ├── Helpers/           # توابع کمکی
│   ├── Http/              # کنترلرها، میدلویرها و درخواست‌ها
│   ├── Models/            # مدل‌های Eloquent
│   ├── Providers/         # ارائه‌دهندگان سرویس
│   ├── Services/          # منطق کسب‌وکار پیچیده
│   └── View/              # کامپوننت‌های View
├── config/                # فایل‌های پیکربندی
├── database/              # مایگریشن‌ها و سیدرها
├── docs/                  # مستندات پروژه
├── public/                # فایل‌های عمومی
├── resources/             # ویوها، CSS، JS
├── routes/                # تعریف مسیرها
├── storage/               # فایل‌های موقت و لاگ‌ها
├── tests/                 # تست‌های خودکار
└── vendor/                # پکیج‌های Composer
```

## 📂 توضیح دایرکتوری‌های اصلی

### `app/` - هسته اپلیکیشن

#### `Console/Commands/`
دستورات سفارشی Artisan برای عملیات خاص:

```php
// Example: دستور مدیریت سال مالی
php artisan fiscal-year:export --year=1403
php artisan fiscal-year:import --file=data.json --year=1404
```

#### `Enums/`
انواع داده‌های ثابت پروژه:

```php
// app/Enums/FiscalYearSection.php
enum FiscalYearSection: string
{
    case SUBJECTS = 'subjects';
    case CUSTOMERS = 'customers';
    case PRODUCTS = 'products';
    // ...
}
```

#### `Exceptions/`
خطاهای سفارشی:

```php
// app/Exceptions/DocumentServiceException.php
class DocumentServiceException extends Exception
{
    // منطق خاص برای خطاهای سند حسابداری
}
```

#### `Helpers/`
توابع کمکی عمومی:

- `helpers.php` - توابع عمومی
- `jdf.php` - توابع تبدیل تاریخ فارسی
- `NumberToWordHelper.php` - تبدیل عدد به حروف

#### `Http/`
لایه HTTP اپلیکیشن:

```
Http/
├── Controllers/           # کنترلرها
│   ├── Auth/             # احراز هویت
│   ├── Management/       # مدیریت کاربران و نقش‌ها
│   ├── DocumentController.php
│   ├── InvoiceController.php
│   └── ...
├── Middleware/           # میدلویرها
├── Requests/             # اعتبارسنجی درخواست‌ها
└── Kernel.php           # تنظیمات HTTP
```

#### `Models/`
مدل‌های Eloquent برای پایگاه داده:

**مدل‌های اصلی حسابداری:**
- `Document.php` - اسناد حسابداری
- `Transaction.php` - تراکنش‌های مالی
- `Subject.php` - سرفصل‌های حسابداری
- `Company.php` - شرکت‌ها
- `FiscalYear.php` - سال‌های مالی

**مدل‌های کسب‌وکار:**
- `Customer.php` - مشتریان
- `Product.php` - کالاها
- `Invoice.php` - فاکتورها
- `Bank.php` - بانک‌ها
- `BankAccount.php` - حساب‌های بانکی

#### `Services/`
منطق کسب‌وکار پیچیده:

```php
// app/Services/DocumentService.php
class DocumentService
{
    public static function createDocument(User $user, array $data, array $transactions)
    {
        // منطق ایجاد سند با کنترل موازنه
    }
}

// app/Services/FiscalYearService.php
class FiscalYearService
{
    public static function exportData($fiscalYearId, array $sections)
    {
        // صادرات داده‌های سال مالی
    }
}
```

## 🎛️ پیکربندی (`config/`)

فایل‌های پیکربندی مهم:

```php
// config/app.php - تنظیمات کلی
'locale' => 'fa',  // زبان پیش‌فرض فارسی

// config/database.php - تنظیمات پایگاه داده
'default' => env('DB_CONNECTION', 'mysql'),

// config/permission.php - تنظیمات نقش‌ها و مجوزها
```

## 📊 پایگاه داده (`database/`)

### `migrations/`
تعریف ساختار جداول:

```php
// Example: مایگریشن جدول documents
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('number')->unique();
    $table->date('date');
    $table->text('description')->nullable();
    $table->foreignId('fiscal_year_id');
    $table->foreignId('company_id');
    $table->timestamps();
});
```

### `seeders/`
داده‌های اولیه:

- `DatabaseSeeder.php` - داده‌های ضروری سیستم
- `DemoSeeder.php` - داده‌های نمایشی

## 🎨 منابع (`resources/`)

### `views/`
قالب‌های Blade:

```
views/
├── layouts/              # قالب‌های اصلی
├── documents/            # صفحات مربوط به اسناد
├── reports/              # صفحات گزارش‌ها
├── auth/                 # صفحات احراز هویت
└── components/           # کامپوننت‌های قابل استفاده مجدد
```

### `js/` و `css/`
فایل‌های JavaScript و CSS با Vite مدیریت می‌شوند.

## 🛣️ مسیریابی (`routes/`)

### `web.php`
مسیرهای وب اپلیکیشن:

```php
Route::group(['middleware' => ['auth', 'check-permission']], function () {
    Route::resource('documents', DocumentController::class);
    Route::resource('subjects', SubjectController::class);
    
    // مسیرهای گزارش‌ها
    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('ledger', [ReportsController::class, 'ledger']);
        Route::get('journal', [ReportsController::class, 'journal']);
    });
    
    // مدیریت کاربران
    Route::group(['prefix' => 'management'], function () {
        Route::resource('users', Management\UserController::class);
        Route::resource('roles', Management\RoleController::class);
    });
});
```

### `api.php`
مسیرهای API (در صورت نیاز):

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('documents', DocumentApiController::class);
});
```

## 🧪 تست‌ها (`tests/`)

### `Feature/`
تست‌های عملکردی (End-to-End):

```php
// tests/Feature/DocumentTest.php
class DocumentTest extends TestCase
{
    public function test_can_create_balanced_document()
    {
        // تست ایجاد سند متوازن
    }
}
```

### `Unit/`
تست‌های واحد:

```php
// tests/Unit/DocumentServiceTest.php
class DocumentServiceTest extends TestCase
{
    public function test_validates_document_balance()
    {
        // تست اعتبارسنجی موازنه سند
    }
}
```

## 🔧 ابزارهای توسعه

### Composer
مدیریت dependency های PHP:

```bash
composer install        # نصب پکیج‌ها
composer dump-autoload   # بازسازی autoloader
composer update         # به‌روزرسانی پکیج‌ها
```

### NPM
مدیریت dependency های JavaScript:

```bash
npm install             # نصب پکیج‌ها
npm run dev            # اجرای توسعه
npm run build          # ساخت نهایی
```

### Artisan
دستورات Laravel:

```bash
php artisan migrate     # اجرای مایگریشن‌ها
php artisan db:seed     # اجرای سیدرها
php artisan make:model  # ایجاد مدل جدید
php artisan serve       # اجرای سرور توسعه
```

## 📝 کنوانسیون‌های نام‌گذاری

### کلاس‌ها
- **Controllers**: `PascalCase` + `Controller` (مثل `DocumentController`)
- **Models**: `PascalCase` منفرد (مثل `Document`)
- **Services**: `PascalCase` + `Service` (مثل `DocumentService`)

### فایل‌ها
- **Views**: `kebab-case` (مثل `document-create.blade.php`)
- **Migrations**: تاریخ + `snake_case` (مثل `2024_01_01_create_documents_table`)

### متغیرها
- **PHP**: `camelCase` (مثل `$fiscalYear`)
- **Database**: `snake_case` (مثل `fiscal_year_id`)

### مسیرها
- **URLs**: `kebab-case` (مثل `/customer-groups`)
- **Route names**: `dot.notation` (مثل `documents.create`)

## 🔒 امنیت

### Middleware
- `auth` - احراز هویت کاربر
- `check-permission` - کنترل مجوزها
- سایر middleware های Laravel

### مجوزها
استفاده از پکیج Spatie Permission:

```php
// permission in Controller
$this->authorize('documents.create');

// کنترل در Blade
@can('documents.edit')
    <button>ویرایش</button>
@endcan
```

## 🚀 بهینه‌سازی

### Caching
- **Config**: `php artisan config:cache`
- **Routes**: `php artisan route:cache`
- **Views**: `php artisan view:cache`

### Database
- **Indexes**: روی ستون‌های پرجستجو
- **Relations**: استفاده از Eager Loading
- **Pagination**: برای لیست‌های بزرگ

---

**نکته مهم**: همیشه قبل از اعمال تغییرات، ساختار پروژه موجود را مطالعه کنید و از الگوهای استفاده شده پیروی کنید.
