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

### دایرکتوری `app/` - هسته اپلیکیشن

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

- فایل `helpers.php` - توابع عمومی
- فایل `jdf.php` - توابع تبدیل تاریخ فارسی
- فایل `NumberToWordHelper.php` - تبدیل عدد به حروف

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
مدل‌های Eloquent موجود در همین پوشه منطق داده را مدیریت می‌کنند. مهم‌ترین فایل‌ها عبارت‌اند از:

- مدل `Document.php` – مدیریت اسناد حسابداری و ارتباط آن‌ها با تراکنش‌ها.
- مدل `Transaction.php` – ثبت تراکنش‌های مرتبط با اسناد و سناریوهای فروش.
- مدل `Subject.php` – ساختار درختی سرفصل‌ها و روابط والد/فرزند آن‌ها.
- مدل `Company.php` – اطلاعات شرکت و نگه‌داشتن شناسه شرکت فعال.
- مدل `User.php` – کاربران سیستم و ارتباط آن‌ها با شرکت‌ها.
- مدل‌های `Customer.php` و `CustomerGroup.php` – مدیریت مشتریان و گروه‌بندی آن‌ها.
- مدل‌های `Product.php` و `ProductGroup.php` – کالاها و گروه‌های کالایی.
- مدل‌های `Invoice.php` و `InvoiceItem.php` – فاکتورهای فروش و اقلامشان.
- مدل‌های `Bank.php`، `BankAccount.php`، `Cheque.php` و `ChequeHistory.php` – مدیریت اطلاعات بانکی و چک‌ها.
- مدل‌های `Config.php` و `Payment.php` – پیکربندی سیستم و پرداخت‌ها.

زیرپوشه `Scopes/` شامل `FiscalYearScope.php` است که بر روی مدل‌های مرتبط اعمال می‌شود تا داده‌ها به شرکت/سال فعال محدود شوند.

> نکته: مدلی با نام `FiscalYear.php` در پروژه وجود ندارد؛ مدیریت سال/شرکت فعال از طریق مدل `Company` و همین اسکوپ انجام می‌شود.

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
    $table->decimal('number', 16, 2)->nullable();
    $table->string('title')->nullable();
    $table->date('date')->nullable();
    $table->date('approved_at')->nullable();
    $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();
});
```

### `seeders/`
داده‌های اولیه:

- فایل `DatabaseSeeder.php` - داده‌های ضروری سیستم
- فایل `DemoSeeder.php` - داده‌های نمایشی

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

### فایل‌های `js/` و `css/`
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
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
```

در حال حاضر فایل تنها شامل نمونه‌ی پیش‌فرض لاراول است و می‌توانید مسیرهای API جدید را در همین گروه اضافه کنید.

## 🧪 تست‌ها (`tests/`)

ساختار تست‌ها مطابق استاندارد لاراول است و دو تست نمونه به صورت پیش‌فرض در مخزن حضور دارند:

```
tests/
├── Feature/ExampleTest.php   # بررسی پاسخ موفق صفحه‌ی اصلی
└── Unit/ExampleTest.php      # تست ساده صحت true
```

برای گسترش پوشش تست‌ها می‌توانید فایل‌های جدید با دستور `php artisan make:test` بسازید یا همین نمونه‌ها را ویرایش کنید. راهنمای تست در `docs/testing-guide.md` توضیحات بیشتری ارائه می‌دهد.

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
- کلاس‌های کنترلر (**Controllers**): `PascalCase` + `Controller` (مثل `DocumentController`)
- کلاس‌های مدل (**Models**): `PascalCase` منفرد (مثل `Document`)
- کلاس‌های سرویس (**Services**): `PascalCase` + `Service` (مثل `DocumentService`)

### فایل‌ها
- فایل‌های نما (**Views**): `kebab-case` (مثل `document-create.blade.php`)
- فایل‌های مایگریشن (**Migrations**): تاریخ + `snake_case` (مثل `2024_01_01_create_documents_table`)

### متغیرها
- متغیرهای **PHP**: `camelCase` (مثل `$fiscalYear`)
- ستون‌های دیتابیس (**Database**): `snake_case` (مثل `fiscal_year_id`)

### مسیرها
- نشانی‌های وب (**URLs**): `kebab-case` (مثل `/customer-groups`)
- نام‌های مسیر (**Route names**): `dot.notation` (مثل `documents.create`)

## 🔒 امنیت

### Middleware
- میان‌افزار `auth` - احراز هویت کاربر
- میان‌افزار `check-permission` - کنترل مجوزها
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
- کش پیکربندی (**Config**): `php artisan config:cache`
- کش مسیرها (**Routes**): `php artisan route:cache`
- کش نماها (**Views**): `php artisan view:cache`

### Database
- نمایه‌ها (**Indexes**): روی ستون‌های پرجستجو
- روابط (**Relations**): استفاده از Eager Loading
- صفحه‌بندی (**Pagination**): برای لیست‌های بزرگ

---

**نکته مهم**: همیشه قبل از اعمال تغییرات، ساختار پروژه موجود را مطالعه کنید و از الگوهای استفاده شده پیروی کنید.
