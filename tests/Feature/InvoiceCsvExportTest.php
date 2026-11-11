<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SubjectType;
use App\Http\Controllers\ReportsController;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ReportExportNotification;
use App\Services\CommercialLedgerService;
use App\Services\DocumentImportExport\DocumentImportExportService;
use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceCsvExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private int $fiscalYearId;

    protected function setUp(): void
    {
        parent::setUp();

        $fiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        $this->fiscalYearId = $fiscalYear->id;

        $this->user = User::factory()->create();
        $fiscalYear->users()->attach($this->user);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'invoices.export'])
        );

        $this->withCookies(['active-fiscal-year-id' => $this->fiscalYearId]);
        config(['active-fiscal-year-id' => $this->fiscalYearId]);
        App::setLocale('en');
        $this->withSession(['locale' => 'en']);

        $this->customer = Customer::create(['name' => 'Alpha Customer', 'fiscal_year_id' => $this->fiscalYearId]);
    }

    public function test_export_uses_filters_and_writes_the_nine_requested_columns(): void
    {
        $document = Document::create(['number' => 7001, 'date' => '2026-06-10', 'creator_id' => $this->user->id, 'fiscal_year_id' => $this->fiscalYearId]);

        $included = $this->invoice([
            'number' => 1001,
            'date' => '2026-06-10',
            'document_id' => $document->id,
            'amount' => 1040,
            'subtraction' => 20,
        ]);
        $this->item($included, quantity: 2, unitPrice: 500, discount: 50, vat: 90);

        $otherCustomer = Customer::create(['name' => 'Other Customer', 'fiscal_year_id' => $this->fiscalYearId]);
        $excludedByText = $this->invoice(['number' => 1002, 'customer_id' => $otherCustomer->id]);
        $this->item($excludedByText, quantity: 1, unitPrice: 300);

        $excludedByType = $this->invoice(['number' => 1003, 'invoice_type' => InvoiceType::BUY]);
        $this->item($excludedByType, quantity: 1, unitPrice: 400);

        $response = $this->actingAs($this->user)->get(route('invoices.export', [
            'invoice_type' => 'sell',
            'status' => 'approved',
            'text' => 'Alpha',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = $this->csvRows($response->streamedContent());

        $this->assertSame([
            'Invoice Number',
            'Customer Name',
            'Date',
            'Document Number',
            'Before discounts and tax',
            'Discounts',
            'Tax',
            'Amount',
            'Amount - Discounts',
        ], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertEquals(1001, (float) $rows[1][0]);
        $this->assertSame('Alpha Customer', $rows[1][1]);
        $this->assertSame('1405/03/20', $rows[1][2]);
        $this->assertEquals(7001, (float) $rows[1][3]);
        $this->assertEquals(1000, (float) $rows[1][4]);
        $this->assertEquals(50, (float) $rows[1][5]);
        $this->assertEquals(90, (float) $rows[1][6]);
        $this->assertEquals(1020, (float) $rows[1][7]);
        $this->assertEquals(950, (float) $rows[1][8]);
    }

    public function test_export_translates_column_titles(): void
    {
        App::setLocale('fa');
        $this->withSession(['locale' => 'fa']);

        $response = $this->actingAs($this->user)->get(route('invoices.export', ['invoice_type' => 'sell']));

        $headers = $this->csvRows($response->streamedContent())[0];

        $this->assertSame([
            'شماره فاکتور',
            'نام مشتری',
            'تاریخ',
            'شماره',
            'قبل از تخفیف و مالیات',
            'تخفیف ها',
            'مالیات',
            'مبلغ',
            'مبلغ پس از تخفیف',
        ], $headers);
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'number' => random_int(2000, 9000),
            'date' => '2026-06-10',
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
        ], $attributes));
    }

    private function item(Invoice $invoice, float $quantity, float $unitPrice, float $discount = 0, float $vat = 0): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'vat' => $vat,
            'amount' => ($quantity * $unitPrice) - $discount + $vat,
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvRows(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));

        return array_map('str_getcsv', $lines);
    }

    public function test_product_and_service_exports_normalize_identifiers_and_preserve_text(): void
    {
        Product::factory()->create([
            'name' => 'کالای ۱۲', 'code' => '۰۰۱', 'sstid' => '۰۰۲۳',
            'location' => 'قفسه ۴', 'description' => 'توضیح ۵', 'selling_price' => 123.45,
        ]);
        Service::factory()->create([
            'name' => 'خدمت ۱۲', 'code' => '۰۰۱', 'sstid' => '۰۰۲۳',
            'description' => 'توضیح ۵', 'selling_price' => 123.45,
        ]);

        $product = $this->exportRows('products_csv', [
            'cols_submitted' => 1, 'columns' => ['code', 'sstid', 'location', 'description', 'selling_price'],
        ])[1];
        $this->assertSame(['کالای ۱۲', '001', '123.45', '0023', 'قفسه ۴', 'توضیح ۵'], $product);

        $service = $this->exportRows('services_csv', [
            'cols_submitted' => 1, 'columns' => ['code', 'sstid', 'description', 'selling_price'],
        ])[1];
        $this->assertSame(['001', 'خدمت ۱۲', '123.45', '0023', 'توضیح ۵'], $service);
    }

    public function test_customer_export_normalizes_contact_and_bank_identifiers_only(): void
    {
        $this->customer->delete();
        Customer::create([
            'type' => CustomerType::INDIVIDUAL, 'name' => 'مشتری ۱۲', 'phone' => '۰۲۱-۱۲۳', 'mobile' => '۰۹۱۲', 'fax' => '۰۲۱-۴۵۶',
            'postal_code' => '۰۰۱۲۳', 'ecnmcs_code' => '۰۰۴۵', 'personal_code' => '۰۰۶۷',
            'address' => 'خیابان ۱۲ پلاک ۳', 'desc' => 'توضیح ۴',
            'acc_name_1' => 'حساب ۵', 'acc_no_1' => '۰۰۱۲-۳۴', 'acc_bank_1' => 'بانک ۶',
            'acc_no_2' => '۰۰۵۶', 'fiscal_year_id' => $this->fiscalYearId, 'credit' => 123.45, 'disc_rate' => 2.5,
        ]);

        [$headers, $values] = $this->exportRows('customers_csv');
        $row = array_combine($headers, $values);
        foreach (['phone' => '021-123', 'mobile' => '0912', 'fax' => '021-456', 'postal_code' => '00123',
            'ecnmcs_code' => '0045', 'personal_code' => '0067', 'acc_no_1' => '0012-34', 'acc_no_2' => '0056'] as $column => $expected) {
            $this->assertSame($expected, $row[$column]);
        }
        foreach (['name' => 'مشتری ۱۲', 'address' => 'خیابان ۱۲ پلاک ۳', 'desc' => 'توضیح ۴',
            'acc_name_1' => 'حساب ۵', 'acc_bank_1' => 'بانک ۶'] as $column => $expected) {
            $this->assertSame($expected, $row[$column]);
        }
        $this->assertEquals(123.45, (float) $row['credit']);
        $this->assertEquals(2.5, (float) $row['disc_rate']);
    }

    public function test_employee_export_uses_jalali_contract_dates_and_preserves_empty_dates(): void
    {
        Employee::factory()->create([
            'fiscal_year_id' => getActiveFiscalYear(), 'code' => 'EMP-۰۰۱', 'first_name' => 'نام ۲',
            'national_code' => '۰۰۱۲۳۴۵۶۷۸', 'phone' => '۰۹۱۲',
            'contract_start_date' => '2026-03-21', 'contract_end_date' => null,
        ]);

        $row = $this->exportRows('employees_csv')[1];
        $this->assertSame('EMP-001', $row[0]);
        $this->assertSame('نام ۲', $row[1]);
        $this->assertSame('0012345678', $row[3]);
        $this->assertSame('0912', $row[4]);
        $this->assertSame('1405-01-01', $row[10]);
        $this->assertSame('', $row[11]);
        $this->assertSame('0', $row[12]);
    }

    public function test_document_export_uses_jalali_dates_and_english_structured_values(): void
    {
        app()->setLocale('fa');
        $subject = Subject::create(['parent_id' => null, 'type' => SubjectType::BOTH, 'fiscal_year_id' => $this->fiscalYearId, 'code' => '001002003', 'name' => 'حساب ۱۲']);
        $document = Document::factory()->create(['number' => 42, 'date' => '2026-03-21', 'title' => 'سند ۳']);
        Transaction::create([
            'document_id' => $document->id, 'subject_id' => $subject->id,
            'value' => -123.45, 'user_id' => auth()->id(), 'desc' => 'شرح ۴',
        ]);

        [$headers, $values] = $this->exportRows('documents_csv');
        $row = array_combine($headers, $values);
        $this->assertSame('1405/01/01', $row[__('doc_date')]);
        $this->assertSame('42', $row[__('doc_number')]);
        $this->assertSame('001', $row[__('subject_root_code')]);
        $this->assertSame('002', $row[__('subject_moein_code')]);
        $this->assertSame('003', $row[__('subject_tafsili_code')]);
        $this->assertSame('سند ۳', $row[__('doc_title')]);
        $this->assertSame('شرح ۴', $row[__('transaction_desc')]);
        $this->assertSame('123.45', $row[__('debit')]);
    }

    public function test_ledger_csv_paths_use_jalali_dates_and_preserve_grouped_codes(): void
    {
        $subject = Subject::create(['parent_id' => null, 'type' => SubjectType::BOTH, 'fiscal_year_id' => $this->fiscalYearId, 'code' => '001002003', 'name' => 'حساب ۱۲']);
        $document = Document::factory()->create(['number' => 42.5, 'date' => '2026-03-21']);
        $transaction = Transaction::create([
            'document_id' => $document->id, 'subject_id' => $subject->id,
            'value' => -123.45, 'user_id' => $this->user->id, 'desc' => 'شرح ۴',
        ]);
        $expected = ['1405/01/01', '42/50', '001/002/003', 'حساب ۱۲', 'شرح ۴', '123.45', ''];

        foreach (['fa', 'fa_IR', 'en'] as $locale) {
            app()->setLocale($locale);
            $this->assertSame($expected, $this->exportRows('accounting_report_csv', [
                'report_for' => 'Ledger', 'subject_id' => $subject->id,
            ])[1]);

            $method = new \ReflectionMethod(ReportsController::class, 'streamCsvResponse');
            $response = $method->invoke(app(ReportsController::class), collect([$transaction->load('document', 'subject')]), 'ledger.csv');
            ob_start();
            $response->sendContent();
            $this->assertSame($expected, $this->csvRows(ob_get_clean())[1]);
        }
    }

    public function test_commercial_ledger_csv_normalizes_structured_values_and_preserves_text(): void
    {
        $method = new \ReflectionMethod(CommercialLedgerService::class, 'csv');
        $content = $method->invoke(app(CommercialLedgerService::class), collect([[
            'row_number' => '۱۲', 'date' => '۱۴۰۵/۰۱/۰۱', 'general_code' => '۰۰۱',
            'general_title' => 'کل ۲', 'subsidiary_code' => '۰۰۱۰۰۲', 'subsidiary_title' => 'معین ۳',
            'description' => 'شرح ۴', 'debit' => 123.45, 'credit' => null,
        ]]));

        $this->assertSame(['12', '1405/01/01', '001', 'کل ۲', '001002', 'معین ۳', 'شرح ۴', '123.45', ''], $this->csvRows($content)[1]);
    }

    public function test_english_digit_jalali_document_export_can_be_imported(): void
    {
        app()->setLocale('fa');
        $subject = Subject::factory()->create(['parent_id' => null, 'type' => SubjectType::BOTH, 'fiscal_year_id' => $this->fiscalYearId, 'code' => '001', 'name' => 'حساب ۱۲']);
        $document = Document::factory()->create(['number' => 42, 'date' => '2026-03-21', 'title' => 'سند ۳']);
        foreach ([-123.45, 123.45] as $value) {
            Transaction::create(['document_id' => $document->id, 'subject_id' => $subject->id, 'value' => $value, 'user_id' => $this->user->id]);
        }
        $content = app(ReportExportService::class)->generate('documents_csv', [])['content'];
        $fiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $fiscalYear->id]);
        $result = app(DocumentImportExportService::class)->importCsv(
            UploadedFile::fake()->createWithContent('documents.csv', $content), $this->user, 'free_amir'
        );

        $this->assertSame(1, $result['documents_created']);
        $imported = Document::where('number', 42)->sole();
        $this->assertSame('2026-03-21', $imported->date->toDateString());
        $this->assertSame('سند ۳', $imported->title);
        $this->assertEquals(0, $imported->transactions->sum('value'));
    }

    public function test_download_and_email_contain_the_same_normalized_csv(): void
    {
        Notification::fake();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'report']));
        $this->customer->update(['name' => 'مشتری ۱۲']);
        $invoice = $this->invoice(['number' => 123, 'date' => '2026-03-21']);
        $this->item($invoice, quantity: 2, unitPrice: 123.45);
        app()->setLocale('fa');
        $this->withSession(['locale' => 'fa']);
        $payload = ['export' => 'invoices_csv', 'filters' => []];
        $download = $this->actingAs($this->user)->post(route('report'), [...$payload, 'delivery' => 'download']);
        $download->assertOk();
        $content = $download->streamedContent();
        $this->assertSame(['123', 'مشتری ۱۲', '1405/01/01', '', '246.9', '0', '0', '0', '246.9'], $this->csvRows($content)[1]);

        $this->post(route('report'), [...$payload, 'delivery' => 'email', 'email' => $this->user->email])->assertRedirect();
        Notification::assertSentTo($this->user, ReportExportNotification::class,
            fn (ReportExportNotification $notification): bool => $notification->content === $content
        );
    }

    public function test_trial_balance_csv_uses_english_digits_without_changing_account_names(): void
    {
        app()->setLocale('fa');
        $subject = Subject::create(['parent_id' => null, 'type' => SubjectType::BOTH,
            'fiscal_year_id' => $this->fiscalYearId, 'code' => '۰۰۱', 'name' => 'حساب ۲']);
        $document = Document::factory()->create(['number' => 3, 'date' => '2026-03-21']);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $subject->id,
            'value' => -123.45, 'user_id' => $this->user->id]);

        $this->assertSame(['001', 'حساب ۲', '123.45', '0', '123.45', '0'], $this->exportRows('trial_balance_csv')[1]);
    }

    public function test_csv_numbers_preserve_separators_leading_zeros_and_empty_values_in_every_locale(): void
    {
        foreach (['fa', 'fa_IR', 'en'] as $locale) {
            app()->setLocale($locale);
            $this->assertSame('001/002', csvNumber('۰۰۱/۰۰۲'));
            $this->assertSame('-1,234.50', csvNumber('-۱,۲۳۴.۵۰'));
            $this->assertSame('0', csvNumber(0));
            $this->assertSame('', csvNumber(null));
        }
    }

    private function exportRows(string $export, array $filters = []): array
    {
        $content = app(ReportExportService::class)->generate($export, $filters)['content'];
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, substr($content, 3));
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
