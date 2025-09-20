# راهنمای تست در امیر

این راهنما نحوه نوشتن و اجرای تست‌ها در پروژه امیر را توضیح می‌دهد.

## 🎯 اهمیت تست در سیستم‌های مالی

در سیستم‌های حسابداری، تست‌نویسی بسیار حیاتی است زیرا:
- خطاهای محاسباتی می‌توانند باعث عدم تطبیق حساب‌ها شوند
- منطق حسابداری پیچیده و مستعد خطا است
- تغییرات کوچک می‌توانند تأثیرات گسترده داشته باشند
- قوانین مالیاتی و حسابداری باید دقیقاً رعایت شوند

## 🧪 انواع تست در امیر

### 1. Unit Tests
تست‌های واحد برای تست منطق کلاس‌ها و متدهای مجزا:

```php
<?php
namespace Tests\Unit;

use App\Services\DocumentService;
use App\Exceptions\DocumentServiceException;
use PHPUnit\Framework\TestCase;

class DocumentServiceTest extends TestCase
{
    public function test_calculates_document_balance_correctly()
    {
        // Arrange
        $transactions = [
            ['debit_amount' => 100000, 'credit_amount' => 0],
            ['debit_amount' => 0, 'credit_amount' => 100000]
        ];
        
        // Act
        $isBalanced = DocumentService::isBalanced($transactions);
        
        // Assert
        $this->assertTrue($isBalanced);
    }
    
    public function test_detects_unbalanced_transactions()
    {
        // Arrange
        $transactions = [
            ['debit_amount' => 100000, 'credit_amount' => 0],
            ['debit_amount' => 0, 'credit_amount' => 50000]
        ];
        
        // Act
        $isBalanced = DocumentService::isBalanced($transactions);
        
        // Assert
        $this->assertFalse($isBalanced);
    }
    
    public function test_validates_transaction_amounts()
    {
        // Arrange
        $invalidTransactions = [
            ['debit_amount' => -100, 'credit_amount' => 0]
        ];
        
        // Act & Assert
        $this->expectException(DocumentServiceException::class);
        $this->expectExceptionMessage('مبلغ نمی‌تواند منفی باشد');
        
        DocumentService::validateTransactions($invalidTransactions);
    }
}
```

### 2. Feature Tests
تست‌های عملکردی برای تست کل workflow:

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Subject;
use App\Models\Document;
use App\Models\Company;
use App\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;
    
    private User $user;
    private Company $company;
    private FiscalYear $fiscalYear;
    private Subject $cashAccount;
    private Subject $salesAccount;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // ایجاد داده‌های مورد نیاز برای تست
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->fiscalYear = FiscalYear::factory()->create([
            'company_id' => $this->company->id
        ]);
        
        $this->cashAccount = Subject::factory()->create([
            'type' => 'asset',
            'code' => '1.1.1.001',
            'title' => 'صندوق'
        ]);
        
        $this->salesAccount = Subject::factory()->create([
            'type' => 'income',
            'code' => '4.1.1.001',
            'title' => 'فروش'
        ]);
        
        // تنظیم session برای شرکت فعال
        session(['active-company-id' => $this->company->id]);
    }
    
    public function test_user_can_create_document_via_form()
    {
        // Arrange
        $this->actingAs($this->user);
        
        // Act
        $response = $this->post(route('documents.store'), [
            'date' => '2024-01-15',
            'description' => 'فروش نقدی کالا',
            'transactions' => [
                [
                    'subject_id' => $this->cashAccount->id,
                    'debit_amount' => 100000,
                    'credit_amount' => 0,
                    'description' => 'دریافت وجه نقد'
                ],
                [
                    'subject_id' => $this->salesAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 100000,
                    'description' => 'درآمد فروش'
                ]
            ]
        ]);
        
        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('documents', [
            'description' => 'فروش نقدی کالا',
            'company_id' => $this->company->id
        ]);
        
        $document = Document::where('description', 'فروش نقدی کالا')->first();
        $this->assertCount(2, $document->transactions);
        $this->assertEquals(100000, $document->total_debit);
        $this->assertEquals(100000, $document->total_credit);
        $this->assertTrue($document->is_balanced);
    }
    
    public function test_cannot_create_unbalanced_document()
    {
        // Arrange
        $this->actingAs($this->user);
        
        // Act
        $response = $this->post(route('documents.store'), [
            'date' => '2024-01-15',
            'description' => 'سند نامتوازن',
            'transactions' => [
                [
                    'subject_id' => $this->cashAccount->id,
                    'debit_amount' => 100000,
                    'credit_amount' => 0
                ],
                [
                    'subject_id' => $this->salesAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => 50000  // عدم موازنه
                ]
            ]
        ]);
        
        // Assert
        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('documents', [
            'description' => 'سند نامتوازن'
        ]);
    }
    
    public function test_user_can_view_document_list()
    {
        // Arrange
        $this->actingAs($this->user);
        Document::factory()->count(5)->create([
            'company_id' => $this->company->id
        ]);
        
        // Act
        $response = $this->get(route('documents.index'));
        
        // Assert
        $response->assertOk();
        $response->assertViewIs('documents.index');
        $response->assertViewHas('documents');
    }
    
    public function test_user_can_search_documents_by_number()
    {
        // Arrange
        $this->actingAs($this->user);
        $targetDocument = Document::factory()->create([
            'number' => 1001,
            'company_id' => $this->company->id
        ]);
        Document::factory()->create([
            'number' => 1002,
            'company_id' => $this->company->id
        ]);
        
        // Act
        $response = $this->get(route('documents.index', ['number' => 1001]));
        
        // Assert
        $response->assertOk();
        $response->assertSee($targetDocument->description);
    }
}
```

### 3. Integration Tests
تست‌های یکپارچگی برای تست ارتباط بین کامپوننت‌ها:

```php
<?php
namespace Tests\Integration;

use App\Models\User;
use App\Models\Subject;
use App\Models\Document;
use App\Models\Transaction;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_ledger_report_calculates_account_balance_correctly()
    {
        // Arrange
        $account = Subject::factory()->create(['type' => 'asset']);
        
        // ایجاد چند تراکنش
        Transaction::factory()->create([
            'subject_id' => $account->id,
            'debit_amount' => 100000,
            'credit_amount' => 0
        ]);
        
        Transaction::factory()->create([
            'subject_id' => $account->id,
            'debit_amount' => 50000,
            'credit_amount' => 0
        ]);
        
        Transaction::factory()->create([
            'subject_id' => $account->id,
            'debit_amount' => 0,
            'credit_amount' => 30000
        ]);
        
        // Act
        $balance = ReportService::calculateAccountBalance($account->id);
        
        // Assert
        $this->assertEquals(120000, $balance); // 100000 + 50000 - 30000
    }
}
```

## 🏭 تست سرویس‌ها

### تست DocumentService

```php
<?php
namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Subject;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Services\DocumentService;
use App\Exceptions\DocumentServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private User $user;
    private Company $company;
    private Subject $cashAccount;
    private Subject $salesAccount;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        
        $this->cashAccount = Subject::factory()->create(['type' => 'asset']);
        $this->salesAccount = Subject::factory()->create(['type' => 'income']);
        
        session(['active-company-id' => $this->company->id]);
    }
    
    public function test_creates_document_with_auto_number()
    {
        // Arrange
        $documentData = [
            'date' => '2024-01-01',
            'description' => 'تست'
        ];
        
        $transactions = [
            [
                'subject_id' => $this->cashAccount->id,
                'debit_amount' => 100000,
                'credit_amount' => 0,
                'description' => 'بدهکار'
            ],
            [
                'subject_id' => $this->salesAccount->id,
                'debit_amount' => 0,
                'credit_amount' => 100000,
                'description' => 'بستانکار'
            ]
        ];
        
        // Act
        $document = DocumentService::createDocument($this->user, $documentData, $transactions);
        
        // Assert
        $this->assertNotNull($document->number);
        $this->assertEquals(1, $document->number); // اولین سند
        $this->assertCount(2, $document->transactions);
    }
    
    public function test_throws_exception_for_invalid_subject()
    {
        // Arrange
        $documentData = [
            'date' => '2024-01-01',
            'description' => 'تست'
        ];
        
        $transactions = [
            [
                'subject_id' => 99999, // سرفصل غیرموجود
                'debit_amount' => 100000,
                'credit_amount' => 0
            ]
        ];
        
        // Act & Assert
        $this->expectException(DocumentServiceException::class);
        DocumentService::createDocument($this->user, $documentData, $transactions);
    }
}
```

### تست FiscalYearService

```php
<?php
namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Customer;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalYearServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_exports_fiscal_year_data()
    {
        // Arrange
        $company = Company::factory()->create();
        $fiscalYear = FiscalYear::factory()->create(['company_id' => $company->id]);
        
        // ایجاد داده‌های نمونه
        Subject::factory()->count(5)->create(['company_id' => $company->id]);
        Customer::factory()->count(3)->create(['company_id' => $company->id]);
        
        // Act
        $exportedData = FiscalYearService::exportData($fiscalYear->id, ['subjects', 'customers']);
        
        // Assert
        $this->assertArrayHasKey('subjects', $exportedData);
        $this->assertArrayHasKey('customers', $exportedData);
        $this->assertCount(5, $exportedData['subjects']);
        $this->assertCount(3, $exportedData['customers']);
    }
    
    public function test_imports_data_to_new_fiscal_year()
    {
        // Arrange
        $company = Company::factory()->create();
        $newFiscalYear = FiscalYear::factory()->create(['company_id' => $company->id]);
        
        $importData = [
            'subjects' => [
                ['code' => '1.1.1', 'title' => 'نقد', 'type' => 'asset'],
                ['code' => '4.1.1', 'title' => 'فروش', 'type' => 'income']
            ]
        ];
        
        // Act
        FiscalYearService::importData($newFiscalYear->id, $importData);
        
        // Assert
        $this->assertDatabaseHas('subjects', [
            'code' => '1.1.1',
            'title' => 'نقد',
            'company_id' => $company->id
        ]);
    }
}
```

## 🎭 استفاده از Factory و Seeder در تست‌ها

### ایجاد Factory

```php
<?php
namespace Database\Factories;

use App\Models\Document;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;
    
    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(1000, 9999),
            'date' => $this->faker->date(),
            'description' => $this->faker->sentence(),
            'company_id' => Company::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
            'creator_id' => User::factory(),
        ];
    }
    
    public function forCompany(Company $company): self
    {
        return $this->state(function () use ($company) {
            return [
                'company_id' => $company->id,
                'fiscal_year_id' => FiscalYear::factory()->create([
                    'company_id' => $company->id
                ])->id
            ];
        });
    }
    
    public function withTransactions(int $count = 2): self
    {
        return $this->afterCreating(function (Document $document) use ($count) {
            $amount = 100000;
            
            // ایجاد تراکنش‌های متوازن
            Transaction::factory()->create([
                'document_id' => $document->id,
                'debit_amount' => $amount,
                'credit_amount' => 0
            ]);
            
            Transaction::factory()->create([
                'document_id' => $document->id,
                'debit_amount' => 0,
                'credit_amount' => $amount
            ]);
        });
    }
}
```

### استفاده از Factory در تست

```php
public function test_document_with_transactions()
{
    // ایجاد سند با تراکنش‌های متوازن
    $document = Document::factory()
        ->forCompany($this->company)
        ->withTransactions()
        ->create();
    
    $this->assertTrue($document->is_balanced);
}
```

## 🗃️ تست مدل‌ها

### تست Relations

```php
<?php
namespace Tests\Unit\Models;

use App\Models\Document;
use App\Models\Transaction;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentModelTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_document_has_many_transactions()
    {
        // Arrange
        $document = Document::factory()->create();
        Transaction::factory()->count(3)->create([
            'document_id' => $document->id
        ]);
        
        // Act & Assert
        $this->assertCount(3, $document->transactions);
    }
    
    public function test_document_belongs_to_fiscal_year()
    {
        // Arrange
        $document = Document::factory()->create();
        
        // Act & Assert
        $this->assertInstanceOf(FiscalYear::class, $document->fiscalYear);
    }
    
    public function test_calculates_total_debit_correctly()
    {
        // Arrange
        $document = Document::factory()->create();
        
        Transaction::factory()->create([
            'document_id' => $document->id,
            'debit_amount' => 100000,
            'credit_amount' => 0
        ]);
        
        Transaction::factory()->create([
            'document_id' => $document->id,
            'debit_amount' => 50000,
            'credit_amount' => 0
        ]);
        
        // Act
        $totalDebit = $document->total_debit;
        
        // Assert
        $this->assertEquals(150000, $totalDebit);
    }
    
    public function test_is_balanced_attribute_works()
    {
        // Arrange
        $document = Document::factory()->create();
        
        // تراکنش‌های متوازن
        Transaction::factory()->create([
            'document_id' => $document->id,
            'debit_amount' => 100000,
            'credit_amount' => 0
        ]);
        
        Transaction::factory()->create([
            'document_id' => $document->id,
            'debit_amount' => 0,
            'credit_amount' => 100000
        ]);
        
        // Act & Assert
        $this->assertTrue($document->is_balanced);
    }
}
```

### تست Scopes

```php
public function test_for_fiscal_year_scope()
{
    // Arrange
    $fiscalYear1 = FiscalYear::factory()->create();
    $fiscalYear2 = FiscalYear::factory()->create();
    
    Document::factory()->create(['fiscal_year_id' => $fiscalYear1->id]);
    Document::factory()->create(['fiscal_year_id' => $fiscalYear1->id]);
    Document::factory()->create(['fiscal_year_id' => $fiscalYear2->id]);
    
    // Act
    $documentsInFiscalYear1 = Document::forFiscalYear($fiscalYear1->id)->get();
    
    // Assert
    $this->assertCount(2, $documentsInFiscalYear1);
}
```

## 🌐 تست Controller ها

### تست Authorization

```php
public function test_guest_cannot_access_documents()
{
    // Act
    $response = $this->get(route('documents.index'));
    
    // Assert
    $response->assertRedirect(route('login'));
}

public function test_user_cannot_access_other_company_documents()
{
    // Arrange
    $user = User::factory()->create();
    $otherCompany = Company::factory()->create();
    $document = Document::factory()->create([
        'company_id' => $otherCompany->id
    ]);
    
    // Act
    $this->actingAs($user);
    $response = $this->get(route('documents.show', $document));
    
    // Assert
    $response->assertForbidden();
}
```

### تست Validation

```php
public function test_document_creation_requires_valid_data()
{
    // Arrange
    $this->actingAs($this->user);
    
    // Act
    $response = $this->post(route('documents.store'), [
        'date' => 'invalid-date',
        'transactions' => []
    ]);
    
    // Assert
    $response->assertSessionHasErrors(['date', 'transactions']);
}
```

## ⚡ Performance Testing

### تست کارایی کوئری‌ها

```php
public function test_document_list_does_not_have_n_plus_one_problem()
{
    // Arrange
    Document::factory()
        ->withTransactions()
        ->count(10)
        ->create();
    
    // Act & Assert
    $this->assertDatabaseQueriesCount(3, function () {
        Document::with('transactions', 'creator')->get();
    });
}
```

## 🏃‍♂️ اجرای تست‌ها

### دستورات پایه

```bash
# Run all test
php artisan test

# اجرای تست‌های خاص
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# اجرای تست‌های یک کلاس خاص
php artisan test tests/Feature/DocumentTest.php

# اجرای یک تست خاص
php artisan test --filter test_user_can_create_document

# اجرای با گزارش Coverage
php artisan test --coverage

# اجرای با جزئیات بیشتر
php artisan test --verbose
```

### تنظیمات محیط تست

```php
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

## 🔧 ابزارهای کمکی تست

### Custom Assertions

```php
<?php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    
    protected function assertDocumentIsBalanced($document)
    {
        $this->assertEquals(
            $document->total_debit,
            $document->total_credit,
            'سند متوازن نیست'
        );
    }
    
    protected function assertAccountBalance($accountId, $expectedBalance)
    {
        $actualBalance = Transaction::where('subject_id', $accountId)
            ->sum(DB::raw('debit_amount - credit_amount'));
            
        $this->assertEquals(
            $expectedBalance,
            $actualBalance,
            "مانده حساب با مقدار مورد انتظار برابر نیست"
        );
    }
}
```

### Test Traits

```php
<?php
namespace Tests\Traits;

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;

trait SetsUpTestData
{
    protected function setUpTestCompany(): Company
    {
        $company = Company::factory()->create();
        $fiscalYear = FiscalYear::factory()->create([
            'company_id' => $company->id
        ]);
        
        session(['active-company-id' => $company->id]);
        
        return $company;
    }
    
    protected function actingAsUser($permissions = []): User
    {
        $user = User::factory()->create();
        
        if (!empty($permissions)) {
            $user->givePermissionTo($permissions);
        }
        
        $this->actingAs($user);
        
        return $user;
    }
}
```

## 📊 تست گزارش‌ها

```php
public function test_ledger_report_accuracy()
{
    // Arrange
    $account = Subject::factory()->create(['type' => 'asset']);
    
    // ایجاد تراکنش‌های مختلف
    $this->createTransaction($account, 100000, 0); // +100000
    $this->createTransaction($account, 50000, 0);  // +50000
    $this->createTransaction($account, 0, 30000);  // -30000
    
    // Act
    $ledgerData = app(ReportService::class)->generateLedger($account->id);
    
    // Assert
    $this->assertEquals(120000, $ledgerData['final_balance']);
    $this->assertCount(3, $ledgerData['transactions']);
}

private function createTransaction($account, $debit, $credit)
{
    $document = Document::factory()->create();
    
    Transaction::create([
        'document_id' => $document->id,
        'subject_id' => $account->id,
        'debit_amount' => $debit,
        'credit_amount' => $credit,
        'description' => 'تراکنش تست'
    ]);
}
```

---

**نکته مهم**: همیشه قبل از commit کردن تغییرات، تمام تست‌ها را اجرا کنید و مطمئن شوید که همه موفق هستند. در سیستم‌های مالی، هیچ تست شکست خورده‌ای نباید نادیده گرفته شود.
