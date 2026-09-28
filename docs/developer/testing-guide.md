# راهنمای تست در امیر

این راهنما وضعیت فعلی تست‌ها در پروژه را توضیح می‌دهد و نشان می‌دهد چگونه می‌توانید از تست‌های نمونه برای ساخت سناریوهای واقعی‌تر استفاده کنید.

## 🎯 چرا تست‌نویسی مهم است؟

در سیستم‌های حسابداری و مالی هر تغییر کوچک می‌تواند اثرات بزرگی داشته باشد، بنابراین:
- مانع ایجاد خطاهای محاسباتی می‌شود.
- از بازگشت رفتارهای ناخواسته جلوگیری می‌کند.
- امکان توسعه‌ی مطمئن قابلیت‌های جدید را فراهم می‌کند.

## 🧪 وضعیت فعلی پوشه‌ی `/tests`

پروژه با تست‌های پیش‌فرض لاراول راه‌اندازی شده است:

```
/tests
├── Feature/ExampleTest.php   # درخواست GET به صفحه‌ی اصلی و بررسی پاسخ 200
└── Unit/ExampleTest.php      # تست ساده‌ی true === true
```

این فایل‌ها نقطه‌ی شروع خوبی برای توسعه‌ی تست‌های جدید هستند. می‌توانید آن‌ها را بازنویسی کنید یا با دستورهای artisan تست‌های تازه بسازید.

```bash
php artisan make:test DocumentControllerTest
php artisan make:test DocumentServiceTest --unit
```

## ▶️ اجرای تست‌ها

برای اجرای تست‌ها می‌توانید از دستورهای متداول زیر استفاده کنید:

```bash
php artisan test                        # Run all tests
php artisan test --testsuite=Feature    # Run only Feature tests
php artisan test --testsuite=Unit       # Run only Unit tests
php artisan test --filter=Document      # Filter tests by class or method name
```

## 🚀 گسترش تست‌های فیچر

برای سناریوهای HTTP از `Tests/TestCase` به همراه traits لاراول استفاده کنید. نمونه‌ی زیر نشان می‌دهد چگونه می‌توانید از تست نمونه برای بررسی دسترسی به لیست اسناد استفاده کنید:

```php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_documents_index(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        session(['active-company-id' => $company->id]);

        $this->actingAs($user);
        $this->withoutMiddleware('check-permission');

        $response = $this->get('/documents');

        $response->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/documents');

        $response->assertRedirect('/login');
    }
}
```

نکته‌ها:
- بسیاری از مدل‌ها از `FiscalYearScope` استفاده می‌کنند و انتظار دارند شناسه‌ی شرکت فعال در session موجود باشد (`session(['active-company-id' => ...])`).
- در صورت نیاز می‌توانید با `$this->withoutMiddleware()` برخی middlewareها مثل `check-permission` را غیرفعال کنید تا روی منطق اصلی تمرکز کنید.

## 🧩 نمونه‌ی تست واحد برای سرویس‌ها

برای تست متدهای موجود در `App/Services/DocumentService` می‌توانید از دیتابیس تست به همراه factoryها استفاده کنید. مثال زیر متد `createTransaction` را بررسی می‌کند:

```php
<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_transaction_persists_value(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $subject = Subject::factory()->create();

        session(['active-company-id' => $company->id]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'creator_id' => $user->id,
        ]);

        $transaction = DocumentService::createTransaction($document, [
            'subject_id' => $subject->id,
            'desc' => 'ثبت خرید کالا',
            'value' => '120000.00',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'document_id' => $document->id,
            'subject_id' => $subject->id,
        ]);
    }
}
```

اگر قصد تست سایر متدها مانند `createDocument` یا `updateDocumentTransactions` را دارید می‌توانید از همین الگو بهره بگیرید و با factoryهای موجود داده‌ی اولیه بسازید.

## 🏭 کار با Factoryها

تمامی Factoryها در مسیر `database/factories` برای ایجاد داده‌های تستی در دسترس هستند. برای مثال در ادامه چند نمونه آمده است: 

- `CompanyFactory`
- `UserFactory`
- `SubjectFactory`
- `DocumentFactory`
- `TransactionFactory`
- `CustomerFactory`
- `ProductFactory`

پیش از استفاده از `DocumentFactory` مطمئن شوید حداقل یک شرکت و کاربر ایجاد کرده‌اید؛ این factory برای مقداردهی شناسه‌ها از رکوردهای موجود استفاده می‌کند.

نمونه‌ی استفاده در تست:

```php
$company = Company::factory()->create();
$user = User::factory()->create();

session(['active-company-id' => $company->id]);

$document = Document::factory()->create([
    'company_id' => $company->id,
    'creator_id' => $user->id,
]);
```

## 💡 نکات تکمیلی

- برای ریست دیتابیس بین تست‌ها از trait با نام `RefreshDatabase` استفاده کنید. ⚠️ **هشدار**: اجرای این trait پایگاه داده را حذف و نوسازی می‌کند.
- برای تست متدهایی که به تاریخ یا زمان متکی‌اند می‌توانید از متد `Carbon::setTestNow()` کمک بگیرید.
- اگر نیاز به داده‌های نمونه دارید، می‌توانید از seederهای موجود استفاده کرده یا seeder مخصوص تست بسازید و در متد `setUp` اجرا کنید.

با دنبال کردن این الگوها می‌توانید تست‌های موجود را از حالت نمونه خارج کرده و به مرور، پوشش قابل اعتمادی برای پروژه ایجاد کنید.