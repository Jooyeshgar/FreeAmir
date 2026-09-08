<?php

namespace Tests\Feature;

use App\Enums\CommercialLedgerType;
use App\Models\CommercialLedgerExport;
use App\Models\Company;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CommercialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class CommercialLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Subject $general;

    private Subject $subsidiary;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');
        Storage::fake('local');
        $this->company = Company::factory()->create(['fiscal_year' => 1403]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching([$this->user->id]);

        foreach (['index', 'store', 'show', 'download', 'destroy'] as $action) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'commercial-ledgers.'.$action]));
        }

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        config(['active-company-id' => $this->company->id]);

        $this->general = Subject::create([
            'company_id' => $this->company->id,
            'parent_id' => null,
            'code' => '101',
            'name' => 'دارایی جاری',
        ]);
        $this->subsidiary = Subject::create([
            'company_id' => $this->company->id,
            'parent_id' => $this->general->id,
            'code' => '101001',
            'name' => 'بانک',
        ]);
    }

    public function test_index_contains_history_grid_and_all_generation_options(): void
    {
        $response = $this->get(route('commercial-ledgers.index'));

        $response->assertOk()
            ->assertSee('سامانه دفاتر تجاری / دفاتر الکترونیکی')
            ->assertSee('کد رهگیری پلمب دفتر')
            ->assertSee('اکسل (XLSX)')
            ->assertSee('CSV');

        foreach (CommercialLedgerType::cases() as $type) {
            $response->assertSee($type->label());
        }
    }

    public function test_csv_generation_persists_history_and_exports_standard_columns(): void
    {
        $this->createTransaction(3, '1403/02/10', -125000, 'خرید نقدی');
        $this->createTransaction(3, '1403/02/10', 125000, 'طرف حساب');

        $response = $this->post(route('commercial-ledgers.store'), $this->payload('csv'));

        $response->assertRedirect(route('commercial-ledgers.index'))->assertSessionHas('success');
        $export = CommercialLedgerExport::query()->sole();
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertSame(2, $export->row_count);
        $this->assertSame('PLM-1403-01', $export->seal_tracking_code);

        $content = Storage::disk('local')->get($export->file_path);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('ردیف,تاریخ,"کد کل"', $content);
        $this->assertStringContainsString('125000', $content);
        $this->assertStringNotContainsString('125,000', $content);
    }

    public function test_xlsx_is_rtl_styled_and_amount_cells_are_numeric(): void
    {
        $this->createTransaction(3, '1403/03/12', -9876.5, 'پرداخت');

        $this->post(route('commercial-ledgers.store'), $this->payload('xlsx'))->assertRedirect();
        $export = CommercialLedgerExport::query()->sole();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($export->file_path)) === true);

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $zip->close();

        $this->assertStringContainsString('rightToLeft="1"', $sheet);
        $this->assertStringContainsString('<c r="H2" s="2" t="n"><v>9876.5</v></c>', $sheet);
        $this->assertStringContainsString('Vazirmatn', $styles);
        $this->assertStringContainsString('FF1F2937', $styles);
    }

    public function test_aggregation_modes_group_rows_at_the_requested_level(): void
    {
        $this->createTransaction(1, '1403/01/01', -100, 'افتتاحیه یک');
        $this->createTransaction(1, '1403/01/01', -50, 'افتتاحیه دو');
        $this->createTransaction(3, '1403/01/10', -25, 'گردش یک');
        $this->createTransaction(3, '1403/01/10', 10, 'گردش دو');
        $service = app(CommercialLedgerService::class);
        $from = jalali_to_gregorian_date('1403/01/01', '-', '/');
        $to = jalali_to_gregorian_date('1403/12/29', '-', '/');

        $this->assertCount(4, $service->rows($from, $to, CommercialLedgerType::ALL_SUBSIDIARY));
        $voucherRows = $service->rows($from, $to, CommercialLedgerType::VOUCHER_SUBSIDIARY);
        $this->assertCount(2, $voucherRows);
        $this->assertEquals(150, $voucherRows->first()['debit']);

        $monthlyWithOpening = $service->rows($from, $to, CommercialLedgerType::MONTHLY_OPENING_GENERAL);
        $this->assertCount(2, $monthlyWithOpening);
        $this->assertSame('', $monthlyWithOpening->first()['subsidiary_code']);

        $monthly = $service->rows($from, $to, CommercialLedgerType::MONTHLY_GENERAL);
        $this->assertCount(1, $monthly);
        $this->assertEquals(175, $monthly->first()['debit']);
        $this->assertEquals(10, $monthly->first()['credit']);
    }

    public function test_preview_download_delete_and_company_scope_are_enforced(): void
    {
        $this->createTransaction(3, '1403/04/01', -500, 'آزمایش');
        $this->post(route('commercial-ledgers.store'), $this->payload('csv'));
        $export = CommercialLedgerExport::query()->sole();

        $this->get(route('commercial-ledgers.show', $export))->assertOk()->assertSee('آزمایش');
        $this->get(route('commercial-ledgers.download', $export))->assertDownload();

        $otherCompany = Company::factory()->create(['fiscal_year' => 1403]);
        config(['active-company-id' => $otherCompany->id]);
        $this->get(route('commercial-ledgers.show', $export->id))->assertNotFound();

        config(['active-company-id' => $this->company->id]);
        $path = $export->file_path;
        $this->delete(route('commercial-ledgers.destroy', $export))->assertRedirect();
        $this->assertDatabaseMissing('commercial_ledger_exports', ['id' => $export->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_generation_rejects_invalid_or_reversed_jalali_dates(): void
    {
        $payload = $this->payload('csv');
        $payload['from_date'] = '1403/07/31';
        $this->post(route('commercial-ledgers.store'), $payload)->assertSessionHasErrors('from_date');

        $payload['from_date'] = '1403/12/29';
        $payload['to_date'] = '1403/01/01';
        $this->post(route('commercial-ledgers.store'), $payload)->assertSessionHasErrors('from_date');
    }

    private function payload(string $format): array
    {
        return [
            'from_date' => '1403/01/01',
            'to_date' => '1403/12/29',
            'format' => $format,
            'seal_tracking_code' => 'PLM-1403-01',
            'ledger_type' => CommercialLedgerType::ALL_SUBSIDIARY->value,
        ];
    }

    private function createTransaction(int $documentNumber, string $jalaliDate, float $value, string $description): Transaction
    {
        $document = Document::query()->firstOrCreate([
            'company_id' => $this->company->id,
            'number' => $documentNumber,
        ], [
            'date' => jalali_to_gregorian_date($jalaliDate, '-', '/'),
            'title' => 'سند '.$documentNumber,
            'creator_id' => $this->user->id,
        ]);

        return Transaction::create([
            'document_id' => $document->id,
            'subject_id' => $this->subsidiary->id,
            'user_id' => $this->user->id,
            'desc' => $description,
            'value' => $value,
        ]);
    }
}
