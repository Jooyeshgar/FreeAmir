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

    private Subject $detailed;

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
        $this->detailed = Subject::create([
            'company_id' => $this->company->id,
            'parent_id' => $this->subsidiary->id,
            'code' => '101001001',
            'name' => 'بانک ملت',
        ]);
    }

    public function test_index_contains_history_grid_and_all_generation_options(): void
    {
        $response = $this->get(route('commercial-ledgers.index'));

        $response->assertOk()
            ->assertSee('دفاتر الکترونیکی')
            ->assertSee('کد رهگیری پلمب دفتر')
            ->assertSee('اکسل (XLSX)')
            ->assertSee('CSV');

        foreach (CommercialLedgerType::cases() as $type) {
            $response->assertSee($type->label());
        }
    }

    public function test_ledger_types_use_numeric_values_and_accounting_level_terms(): void
    {
        $this->assertSame(range(1, 7), array_column(CommercialLedgerType::cases(), 'value'));
        $this->assertTrue($this->general->isRoot());
        $this->assertSame(3, strlen((string) $this->general->code));
        $this->assertSame(6, strlen((string) $this->subsidiary->code));
        $this->assertStringContainsString('سطح معین', CommercialLedgerType::ALL_SUBSIDIARY->label());
        $this->assertStringContainsString('سطح کل', CommercialLedgerType::ALL_GENERAL->label());
        $this->assertStringNotContainsString('سطح تفصیلی', collect(CommercialLedgerType::cases())->map->label()->implode(' '));
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
        $this->assertStringContainsString('ردیف,تاریخ,"کد حساب کل"', $content);
        $this->assertStringContainsString('125000', $content);
        $this->assertStringNotContainsString('125,000', $content);
        $this->assertStringContainsString('خرید نقدی,125000,', $content);
        $this->assertStringContainsString('طرف حساب,,125000', $content);
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
        $this->assertStringContainsString('<c r="I2" s="2"/>', $sheet);
        $this->assertStringContainsString('Vazirmatn', $styles);
        $this->assertStringContainsString('FF1F2937', $styles);
    }

    public function test_all_ledger_types_group_transactions_at_general_or_subsidiary_level(): void
    {
        $otherSubsidiary = Subject::create([
            'company_id' => $this->company->id,
            'parent_id' => $this->general->id,
            'code' => '101002',
            'name' => 'صندوق',
        ]);
        $otherDetailed = Subject::create([
            'company_id' => $this->company->id,
            'parent_id' => $otherSubsidiary->id,
            'code' => '101002001',
            'name' => 'صندوق مرکزی',
        ]);

        $this->createTransaction(1, '1403/01/01', -100, 'افتتاحیه بانک یک', $this->detailed);
        $this->createTransaction(1, '1403/01/01', -50, 'افتتاحیه بانک دو', $this->detailed);
        $this->createTransaction(1, '1403/01/01', -200, 'افتتاحیه صندوق', $otherDetailed);
        $this->createTransaction(3, '1403/01/10', -30, 'گردش بانک', $this->detailed);
        $this->createTransaction(3, '1403/01/10', 10, 'برگشت بانک', $this->detailed);
        $this->createTransaction(3, '1403/01/10', -40, 'گردش صندوق', $otherDetailed);
        $this->createTransaction(4, '1403/01/20', -5, 'گردش دوم بانک', $this->detailed);

        $service = app(CommercialLedgerService::class);
        $from = jalali_to_gregorian_date('1403/01/01', '-', '/');
        $to = jalali_to_gregorian_date('1403/12/29', '-', '/');

        $allSubsidiary = $service->rows($from, $to, CommercialLedgerType::ALL_SUBSIDIARY);
        $this->assertCount(7, $allSubsidiary);
        $this->assertEqualsCanonicalizing(['101001', '101002'], $allSubsidiary->pluck('subsidiary_code')->unique()->all());

        $allGeneral = $service->rows($from, $to, CommercialLedgerType::ALL_GENERAL);
        $this->assertCount(7, $allGeneral);
        $this->assertSame(['101'], $allGeneral->pluck('general_code')->unique()->all());
        $this->assertSame([''], $allGeneral->pluck('subsidiary_code')->unique()->all());

        $this->assertCount(5, $service->rows($from, $to, CommercialLedgerType::DOCUMENT_SUBSIDIARY));
        $this->assertCount(3, $service->rows($from, $to, CommercialLedgerType::DOCUMENT_GENERAL));
        $this->assertCount(4, $service->rows($from, $to, CommercialLedgerType::MONTHLY_OPENING_SUBSIDIARY));
        $this->assertCount(2, $service->rows($from, $to, CommercialLedgerType::MONTHLY_OPENING_GENERAL));

        $monthlyGeneral = $service->rows($from, $to, CommercialLedgerType::MONTHLY_GENERAL);
        $this->assertCount(1, $monthlyGeneral);
        $this->assertEquals(425, $monthlyGeneral->first()['debit']);
        $this->assertEquals(10, $monthlyGeneral->first()['credit']);
    }

    public function test_preview_download_delete_and_company_scope_are_enforced(): void
    {
        $this->createTransaction(3, '1403/04/01', -500, 'آزمایش');
        $this->createTransaction(3, '1403/04/01', 0, 'ردیف صفر');
        $this->post(route('commercial-ledgers.store'), $this->payload('csv'));
        $export = CommercialLedgerExport::query()->sole();

        $preview = $this->get(route('commercial-ledgers.show', $export));
        $preview->assertOk()
            ->assertSee('آزمایش')
            ->assertSee('ردیف صفر')
            ->assertSee('۱ ردیف هشدار')
            ->assertSee('<tr class="bg-warning/20">', false);
        $this->assertSame(1, substr_count($preview->getContent(), '<tr class="bg-warning/20">'));
        $this->assertSame(3, substr_count($preview->getContent(), '<td></td>'));
        $index = $this->get(route('commercial-ledgers.index'));
        $index->assertOk()
            ->assertSee(__('Warning Rows'))
            ->assertSee('title="'.__('Rows with zero debit and credit').'">', false);
        $this->assertMatchesRegularExpression('/title="[^"]+">\s*۱\s*<\/span>/', $index->getContent());
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
            'ledger_type' => (string) CommercialLedgerType::ALL_SUBSIDIARY->value,
        ];
    }

    private function createTransaction(int $documentNumber, string $jalaliDate, float $value, string $description, ?Subject $subject = null): Transaction
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
            'subject_id' => ($subject ?? $this->subsidiary)->id,
            'user_id' => $this->user->id,
            'desc' => $description,
            'value' => $value,
        ]);
    }
}
