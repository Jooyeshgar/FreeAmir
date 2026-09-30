# Testing Guide for FreeAmir

This guide describes the current state of testing in the project and shows how to turn the sample tests into more realistic scenarios.

## 🎯 Why write tests?

In an accounting and financial system, even a small change can have large consequences. Tests:

- Catch calculation errors.
- Prevent unintended behavior from returning.
- Make it safer to develop new features.

## 🧪 Current state of `/tests`

The project starts with Laravel's default tests:

```
/tests
├── Feature/ExampleTest.php   # GET the home page and assert a 200 response
└── Unit/ExampleTest.php      # Simple true === true test
```

These are useful starting points. You can rewrite them or generate new tests with Artisan:

```bash
php artisan make:test DocumentControllerTest
php artisan make:test DocumentServiceTest --unit
```

## ▶️ Running tests

Use the following common commands:

```bash
php artisan test                        # Run all tests
php artisan test --testsuite=Feature    # Run only Feature tests
php artisan test --testsuite=Unit       # Run only Unit tests
php artisan test --filter=Document      # Filter tests by class or method name
```

## 🚀 Extending feature tests

For HTTP scenarios, use `Tests/TestCase` and Laravel's testing traits. This example adapts the sample test to check access to the document list:

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

Notes:

- Many models use `FiscalYearScope` and expect the active company ID in the session (`session(['active-company-id' => ...])`).
- When necessary, `$this->withoutMiddleware()` can disable middleware such as `check-permission` so the test focuses on its main behavior.

## 🧩 Example unit test for services

To test methods in `App/Services/DocumentService`, use the test database and factories. This example checks `createTransaction`:

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
            'desc' => 'Goods purchase entry',
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

You can follow the same pattern for other methods such as `createDocument` or `updateDocumentTransactions`, building initial data with the available factories.

## 🏭 Working with factories

Factories in `database/factories` can create test data. Examples include:

- `CompanyFactory`
- `UserFactory`
- `SubjectFactory`
- `DocumentFactory`
- `TransactionFactory`
- `CustomerFactory`
- `ProductFactory`

Before using `DocumentFactory`, make sure at least one company and one user exist; this factory uses existing records to populate their IDs.

Example in a test:

```php
$company = Company::factory()->create();
$user = User::factory()->create();

session(['active-company-id' => $company->id]);

$document = Document::factory()->create([
    'company_id' => $company->id,
    'creator_id' => $user->id,
]);
```

## 💡 Additional tips

- Use the `RefreshDatabase` trait to reset the database between tests. ⚠️ **Warning:** this trait drops and recreates the test database.
- For time-dependent methods, use `Carbon::setTestNow()`.
- When sample data is needed, use an existing seeder or make a test-specific seeder and run it in `setUp`.

These patterns help replace placeholder tests with reliable project coverage over time.
