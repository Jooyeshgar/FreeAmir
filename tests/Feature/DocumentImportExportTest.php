<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Scopes\FiscalYearScope;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DocumentImportExport\DocumentImportExportService;
use App\Services\DocumentImportExport\ImportSubjectResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class DocumentImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private DocumentImportExportService $service;

    private ImportSubjectResolver $resolver;

    private function buildCsv(array $rows): UploadedFile
    {
        $headers = 'doc_number,doc_date,doc_title,doc_type,doc_status,subject_root_code,subject_moein_code,subject_tafsili_code,subject_name,transaction_desc,debit,credit';
        $lines = [$headers];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row));
        }

        $content = chr(0xEF).chr(0xBB).chr(0xBF).implode("\n", $lines);

        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    private function makeCsvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    private function exportCsvViaService(array $filters): string
    {
        $response = $this->service->export($filters);

        ob_start();
        $response->sendContent();

        return ob_get_clean() ?: '';
    }

    private function runCsvImport(UploadedFile $file, string $format = 'free_amir', ?Company $company = null): array
    {
        $targetCompany = $company ?? $this->company;
        config(['active-company-id' => $targetCompany->id]);

        return $this->service->importCsv($file, $this->user, $format);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        foreach (['documents.create', 'documents.index', 'documents.export', 'documents.import'] as $perm) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $perm]));
        }

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        config(['active-company-id' => $this->company->id]);

        $this->service = new DocumentImportExportService;
        $this->resolver = new ImportSubjectResolver;
    }

    public function test_export_form_renders(): void
    {
        $response = $this->get(route('documents.export'));
        $response->assertOk();
        $response->assertSee(__('Export Documents'));
    }

    public function test_export_form_shows_optional_columns_only(): void
    {
        $response = $this->get(route('documents.export'));
        $response->assertOk();

        foreach (['doc_title', 'doc_type', 'doc_status', 'subject_moein_code', 'subject_tafsili_code', 'transaction_desc'] as $col) {
            $response->assertSee('col-'.$col);
        }
    }

    public function test_export_form_hides_mandatory_columns(): void
    {
        $response = $this->get(route('documents.export'));
        $response->assertOk();

        foreach (DocumentImportExportService::MANDATORY_COLUMNS as $col) {
            $response->assertDontSee('col-'.$col);
        }
    }

    public function test_export_download_returns_csv_content_type(): void
    {
        $response = $this->post(route('documents.export.download'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_import_form_renders(): void
    {
        $response = $this->get(route('documents.import'));
        $response->assertOk();
        $response->assertSee(__('Import Documents'));
    }

    public function test_export_csv_contains_transaction_rows_for_used_accounts(): void
    {
        $root = Subject::factory()->create(['company_id' => $this->company->id, 'code' => '001', 'name' => 'Assets']);
        $child = Subject::factory()->create(['company_id' => $this->company->id, 'code' => '001001', 'name' => 'Bank', 'parent_id' => $root->id]);
        $document = Document::factory()->create(['company_id' => $this->company->id, 'number' => 1, 'date' => '2026-01-01']);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $child->id, 'value' => 1000, 'user_id' => $this->user->id]);

        $csv = $this->exportCsvViaService([]);

        $this->assertStringContainsString('Bank', $csv);
        $this->assertStringContainsString('001', $csv);
        $this->assertStringNotContainsString('SUBJECT', $csv);
    }

    public function test_csv_import_creates_subjects_and_documents(): void
    {
        Subject::factory()->create(['company_id' => $this->company->id, 'code' => '001', 'name' => 'Assets']);

        $csv = $this->buildCsv([
            ['1', '2026-01-15', 'Test Doc', 'manual', 'unapproved', '001', '002', '', 'Bank', 'Payment', '5000', '0'],
            ['1', '2026-01-15', 'Test Doc', 'manual', 'unapproved', '001', '002', '', 'Bank', 'Offset',  '0',    '5000'],
        ]);

        $result = $this->runCsvImport($csv);

        $this->assertSame(1, $result['documents_created']);
        $this->assertSame(0, count($result['errors']), 'No errors expected: '.implode(' | ', $result['errors']));
        $this->assertDatabaseHas('documents', ['company_id' => $this->company->id, 'number' => 1]);
        $this->assertDatabaseHas('subjects', ['company_id' => $this->company->id, 'name' => 'Bank', 'code' => '001002']);
    }

    public function test_csv_import_rejects_document_when_parent_subject_missing(): void
    {
        $csv = $this->buildCsv([
            ['1', '2026-01-15', 'Test Doc', 'manual', 'unapproved', '001', '002', '', 'Bank', 'Payment', '5000', '0'],
            ['1', '2026-01-15', 'Test Doc', 'manual', 'unapproved', '001', '002', '', 'Bank', 'Offset',  '0',    '5000'],
        ]);

        $result = $this->runCsvImport($csv);

        $this->assertSame(0, $result['documents_created']);
        $this->assertSame(1, $result['documents_skipped']);
        $this->assertNotEmpty($result['errors']);
        $this->assertDatabaseMissing('documents', ['company_id' => $this->company->id, 'number' => 1]);
    }

    public function test_csv_import_builds_missing_ancestor_from_a_later_row(): void
    {
        $csv = $this->buildCsv([
            ['1', '2026-01-01', 'D1', 'manual', 'unapproved', '001', '002', '003', 'Detail X', 'd', '100', '0'],
            ['1', '2026-01-01', 'D1', 'manual', 'unapproved', '001', '002', '003', 'Detail X', 'd', '0', '100'],
            ['2', '2026-01-02', 'D2', 'manual', 'unapproved', '001', '002', '', 'Bank', 'd', '50', '0'],
            ['2', '2026-01-02', 'D2', 'manual', 'unapproved', '001', '', '', 'Assets', 'd', '0', '50'],
        ]);

        $result = $this->runCsvImport($csv);

        $this->assertSame(2, $result['documents_created']);
        $this->assertSame(0, count($result['errors']), 'No errors expected: '.implode(' | ', $result['errors']));

        $detail = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('name', 'Detail X')->first();
        $this->assertNotNull($detail);
        $bank = Subject::withoutGlobalScope(FiscalYearScope::class)->find($detail->parent_id);
        $this->assertSame('Bank', $bank->name);
        $assets = Subject::withoutGlobalScope(FiscalYearScope::class)->find($bank->parent_id);
        $this->assertSame('Assets', $assets->name);
        $this->assertNull($assets->parent_id);
    }

    public function test_csv_import_reuses_existing_subject_by_name_when_code_differs(): void
    {
        $assets = Subject::create(['company_id' => $this->company->id, 'code' => '001', 'name' => 'Assets', 'parent_id' => null, 'type' => 'both']);
        $existingCash = Subject::create(['company_id' => $this->company->id, 'code' => '001009', 'name' => 'Cash', 'parent_id' => $assets->id, 'type' => 'both']);

        $csv = $this->buildCsv([
            ['1', '2026-01-01', 'D', 'manual', 'unapproved', '001', '002', '', 'Cash', 'd', '100', '0'],
            ['1', '2026-01-01', 'D', 'manual', 'unapproved', '001', '', '', 'Assets', 'd', '0', '100'],
        ]);

        $result = $this->runCsvImport($csv);

        $this->assertSame(1, $result['documents_created']);
        $this->assertSame(1, Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('name', 'Cash')->count(), 'No duplicate Cash subject should be created.');

        $document = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('number', 1)->first();
        $this->assertTrue($document->transactions->pluck('subject_id')->contains($existingCash->id));
    }

    public function test_csv_import_keeps_same_named_subjects_under_different_parents_distinct(): void
    {
        $products = Subject::create(['company_id' => $this->company->id, 'code' => '010', 'name' => 'Products', 'parent_id' => null, 'type' => 'both']);
        $services = Subject::create(['company_id' => $this->company->id, 'code' => '020', 'name' => 'Services', 'parent_id' => null, 'type' => 'both']);
        Subject::create(['company_id' => $this->company->id, 'code' => '010001', 'name' => 'Cash', 'parent_id' => $products->id, 'type' => 'both']);
        Subject::create(['company_id' => $this->company->id, 'code' => '020001', 'name' => 'Cash', 'parent_id' => $services->id, 'type' => 'both']);

        $csv = $this->buildCsv([
            ['1', '2026-01-01', 'D', 'manual', 'unapproved', '010', '001', '', 'Cash', 'd', '100', '0'],
            ['1', '2026-01-01', 'D', 'manual', 'unapproved', '010', '001', '', 'Cash', 'd', '0', '100'],
        ]);

        $result = $this->runCsvImport($csv);

        $this->assertSame(1, $result['documents_created']);
        $this->assertSame(2, Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('name', 'Cash')->count());

        $document = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('number', 1)->first();
        $usedSubject = Subject::withoutGlobalScope(FiscalYearScope::class)->find($document->transactions->first()->subject_id);
        $this->assertSame($products->id, $usedSubject->parent_id, 'The Cash under Products must be used, not the one under Services.');
    }

    public function test_csv_import_matches_by_code_when_a_name_is_reused_for_several_codes_in_the_file(): void
    {
        Subject::create(['company_id' => $this->company->id, 'code' => '001', 'name' => 'Assets', 'parent_id' => null, 'type' => 'both']);

        $csv = $this->buildCsv([
            ['1', '2026-01-01', 'D', 'manual', 'unapproved', '001', '002', '', 'Widget', 'd', '100', '0'],
            ['1', '2026-01-01', 'D', 'manual', 'unapproved', '001', '003', '', 'Widget', 'd', '0', '100'],
        ]);

        $result = $this->runCsvImport($csv);

        $this->assertSame(1, $result['documents_created']);
        $this->assertSame(2, Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('name', 'Widget')->count());
        $this->assertDatabaseHas('subjects', ['company_id' => $this->company->id, 'code' => '001002', 'name' => 'Widget']);
        $this->assertDatabaseHas('subjects', ['company_id' => $this->company->id, 'code' => '001003', 'name' => 'Widget']);
    }

    public function test_imported_documents_have_is_imported_flag_set(): void
    {
        $csv = $this->buildCsv([
            ['5', '2026-02-01', 'Imported Doc', 'manual', 'unapproved', '001', '', '', 'Assets', 'Test', '100', '100'],
        ]);

        $this->runCsvImport($csv);

        $document = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('number', 5)->first();

        $this->assertNotNull($document);
        $this->assertTrue((bool) $document->is_imported);
        $this->assertSame('imported', $document->document_type);
    }

    public function test_csv_import_is_idempotent_for_documents(): void
    {
        $csv = $this->buildCsv([
            ['7', '2026-03-01', 'Doc', 'manual', 'unapproved', '001', '', '', 'Assets', 'Desc', '100', '100'],
        ]);

        $this->runCsvImport($csv);
        $countAfterFirst = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count();

        $this->runCsvImport($csv);
        $countAfterSecond = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'Re-importing the same CSV must not create duplicate documents');
    }

    public function test_csv_import_preserves_subject_hierarchy(): void
    {
        $assets = Subject::create(['company_id' => $this->company->id, 'code' => '011', 'name' => 'Assets', 'parent_id' => null, 'type' => 'both']);
        Subject::create(['company_id' => $this->company->id, 'code' => '011004', 'name' => 'Bank', 'parent_id' => $assets->id, 'type' => 'both']);

        $csv = $this->buildCsv([
            ['2', '2026-01-10', 'T', 'manual', 'unapproved', '011', '004', '001', 'Mellat', 'x', '0', '0'],
        ]);

        $this->runCsvImport($csv);

        $mellat = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('name', 'Mellat')->first();
        $this->assertNotNull($mellat);

        $bank = Subject::withoutGlobalScope(FiscalYearScope::class)->find($mellat->parent_id);
        $this->assertSame('Bank', $bank->name);

        $assets = Subject::withoutGlobalScope(FiscalYearScope::class)->find($bank->parent_id);
        $this->assertSame('Assets', $assets->name);
        $this->assertNull($assets->parent_id);
    }

    public function test_export_import_roundtrip_preserves_documents_and_hierarchy(): void
    {
        $root = Subject::factory()->create(['company_id' => $this->company->id, 'code' => '001', 'name' => 'Assets']);
        $child = Subject::factory()->create(['company_id' => $this->company->id, 'code' => '001001', 'name' => 'Bank', 'parent_id' => $root->id]);
        $document = Document::factory()->create(['company_id' => $this->company->id, 'number' => 42, 'date' => '2026-06-01', 'title' => 'Round-trip doc']);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $child->id, 'value' => 500, 'user_id' => $this->user->id]);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $child->id, 'value' => -500, 'user_id' => $this->user->id]);

        $csv = $this->exportCsvViaService([]);

        $newCompany = Company::factory()->create();
        config(['active-company-id' => $newCompany->id]);
        Subject::factory()->create(['company_id' => $newCompany->id, 'code' => '001', 'name' => 'Assets']);

        $result = $this->runCsvImport($this->makeCsvFile($csv), 'free_amir', $newCompany);

        $this->assertSame(1, $result['documents_created'], 'One document should be imported');

        $importedDoc = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $newCompany->id)->where('number', 42)->first();
        $this->assertNotNull($importedDoc);
        $this->assertSame('Round-trip doc', $importedDoc->title);

        $importedChild = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $newCompany->id)->where('name', 'Bank')->first();
        $this->assertNotNull($importedChild);

        $importedRoot = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $newCompany->id)->where('name', 'Assets')->first();
        $this->assertNotNull($importedRoot);
        $this->assertSame($importedRoot->id, $importedChild->parent_id);
    }

    public function test_document_type_is_manual_for_regular_documents(): void
    {
        $doc = Document::factory()->create(['company_id' => $this->company->id, 'is_imported' => false]);
        $this->assertSame('manual', $doc->document_type);
    }

    public function test_document_type_is_imported_for_imported_documents(): void
    {
        $doc = Document::factory()->create(['company_id' => $this->company->id, 'is_imported' => true]);
        $this->assertSame('imported', $doc->document_type);
    }

    public function test_build_query_returns_all_documents_with_no_filters(): void
    {
        Document::factory()->count(3)->create(['company_id' => $this->company->id]);
        $query = $this->service->buildQuery([]);
        $this->assertSame(3, $query->count());
    }

    public function test_build_query_filters_by_status_approved(): void
    {
        Document::factory()->create(['company_id' => $this->company->id, 'approved_at' => now()]);
        Document::factory()->create(['company_id' => $this->company->id, 'approved_at' => null]);

        $query = $this->service->buildQuery(['status' => 'approved']);
        $this->assertSame(1, $query->count());
    }

    public function test_build_query_filters_by_status_unapproved(): void
    {
        Document::factory()->create(['company_id' => $this->company->id, 'approved_at' => now()]);
        Document::factory()->create(['company_id' => $this->company->id, 'approved_at' => null]);

        $query = $this->service->buildQuery(['status' => 'unapproved']);

        $this->assertSame(1, $query->count());
    }

    public function test_build_query_filters_by_document_number_range(): void
    {
        Document::factory()->create(['company_id' => $this->company->id, 'number' => 1]);
        Document::factory()->create(['company_id' => $this->company->id, 'number' => 5]);
        Document::factory()->create(['company_id' => $this->company->id, 'number' => 10]);

        $query = $this->service->buildQuery(['start_document_number' => 2, 'end_document_number' => 7]);

        $this->assertSame(1, $query->count());
        $this->assertSame(5.0, (float) $query->first()->number);
    }

    public function test_build_query_filters_by_text_in_title(): void
    {
        Document::factory()->create(['company_id' => $this->company->id, 'title' => 'Salary payment']);
        Document::factory()->create(['company_id' => $this->company->id, 'title' => 'Office expenses']);

        $query = $this->service->buildQuery(['text' => 'Salary']);

        $this->assertSame(1, $query->count());
        $this->assertStringContainsString('Salary', $query->first()->title);
    }

    public function test_export_returns_streamed_response(): void
    {
        Document::factory()->create(['company_id' => $this->company->id]);
        $response = $this->service->export([]);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_finds_existing_subject_by_code(): void
    {
        $existing = Subject::factory()->create(['company_id' => $this->company->id, 'code' => '001', 'name' => 'Assets']);
        $result = $this->resolver->findOrCreate('001', 'Assets', '');

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(1, Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('code', '001')->count());
    }

    public function test_creates_root_subject_when_not_found(): void
    {
        $this->assertDatabaseMissing('subjects', ['code' => '099', 'company_id' => $this->company->id]);
        $result = $this->resolver->findOrCreate('099', 'NewRoot', '');

        $this->assertNotNull($result->id);
        $this->assertSame('NewRoot', $result->name);
    }

    public function test_creates_child_subject_with_existing_parent(): void
    {
        $parent = Subject::create(['company_id' => $this->company->id, 'code' => '100', 'name' => 'Assets', 'parent_id' => null, 'type' => 'both']);
        $child = $this->resolver->findOrCreate('100002', 'Bank', '100');

        $this->assertNotNull($child->id);
        $this->assertSame('Bank', $child->name);
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_falls_back_to_name_parent_match_when_code_missing(): void
    {
        $parent = Subject::create(['company_id' => $this->company->id, 'code' => '200', 'name' => 'Liabilities', 'parent_id' => null, 'type' => 'both']);
        $existing = Subject::create(['company_id' => $this->company->id, 'code' => '200001', 'name' => 'Loans', 'parent_id' => $parent->id, 'type' => 'both']);
        $result = $this->resolver->findOrCreate('200999', 'Loans', '200');

        $this->assertSame($existing->id, $result->id, 'Should match by name + parent_id when code differs');
    }

    public function test_process_subject_rows_creates_hierarchy_in_order(): void
    {
        $rows = [
            ['code' => '001', 'name' => 'Assets', 'parent_code' => ''],
            ['code' => '001001', 'name' => 'Bank', 'parent_code' => '001'],
            ['code' => '001001001', 'name' => 'Mellat', 'parent_code' => '001001'],
        ];

        $this->resolver->processSubjectRows($rows);
        $mellat = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('name', 'Mellat')->first();

        $this->assertNotNull($mellat);

        $bank = Subject::withoutGlobalScope(FiscalYearScope::class)->find($mellat->parent_id);
        $this->assertSame('Bank', $bank->name);

        $assets = Subject::withoutGlobalScope(FiscalYearScope::class)->find($bank->parent_id);
        $this->assertSame('Assets', $assets->name);
        $this->assertNull($assets->parent_id);
    }

    public function test_idempotent_repeated_import_creates_no_duplicates(): void
    {
        $rows = [
            ['code' => '001',    'name' => 'Assets', 'parent_code' => ''],
            ['code' => '001001', 'name' => 'Bank',   'parent_code' => '001'],
        ];

        $this->resolver->processSubjectRows($rows);
        $countAfterFirst = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count();

        $this->resolver->reset();
        $this->resolver->processSubjectRows($rows);
        $countAfterSecond = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'Importing same subjects twice must not create duplicates');
    }

    private function buildParsianCsv(array $rows): UploadedFile
    {
        $header = 'ID,IsNote,Sanad_Num,Factor_Num,Tick,SanadDate,KolCode,MoeenCode,TafsiliCode,Bed,Bes,Comment,HesabName,ChkNum,IsRecPayChk,CostCenterCode';
        $lines = [$header];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $row));
        }

        return UploadedFile::fake()->createWithContent('parsian.csv', implode("\n", $lines));
    }

    private function buildParsianTrialBalanceCsv(array $rows): UploadedFile
    {
        $header = 'KolCode,KolName,SumBed,SumBes,RemainBed,RemainBes';
        $lines = [$header];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $row));
        }

        return UploadedFile::fake()->createWithContent('trial_balance.csv', implode("\n", $lines));
    }

    private function parsianRow(int $sanadNum, string $date, int $kol, int $moen, int $taf, float $bed, float $bes, string $comment, string $hesabName): array
    {
        return [0, 'FALSE', $sanadNum, '', '', $date, $kol, $moen, $taf, $bed, $bes, $comment, $hesabName, '', 'FALSE', ''];
    }

    public function test_parsian_csv_import_creates_documents(): void
    {
        $csv = $this->buildParsianCsv([
            $this->parsianRow(503, '1404/12/21', 11, 4, 0, 1080000000, 0, 'Charge bank', 'بانک پاسارگاد'),
            $this->parsianRow(503, '1404/12/21', 19, 7, 0, 0, 1080000000, 'Charge bank', 'سایر پرداختنی'),
        ]);

        $result = $this->runCsvImport($csv, 'parsian');

        $this->assertSame(1, $result['documents_created']);
        $this->assertSame(0, count($result['errors']));

        $doc = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('number', 503)->first();
        $this->assertNotNull($doc);
        $this->assertSame('imported', $doc->document_type);
        $this->assertCount(2, $doc->transactions);
    }

    public function test_parsian_csv_import_builds_two_level_subject_hierarchy(): void
    {
        $csv = $this->buildParsianCsv([
            $this->parsianRow(503, '1404/12/21', 11, 4, 0, 1000, 0, 'desc', 'بانک پاسارگاد'),
            $this->parsianRow(503, '1404/12/21', 11, 3, 0, 0, 1000, 'desc', 'بانک ملی'),
        ]);

        $this->runCsvImport($csv, 'parsian');

        $pasargad = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('code', '011004')->first();
        $melli = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('code', '011003')->first();
        $kol11 = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('code', '011')->first();

        $this->assertNotNull($pasargad);
        $this->assertSame('بانک پاسارگاد', $pasargad->name);
        $this->assertSame($kol11->id, $pasargad->parent_id);

        $this->assertNotNull($melli);
        $this->assertSame('بانک ملی', $melli->name);
        $this->assertSame($kol11->id, $melli->parent_id);
    }

    public function test_parsian_csv_import_builds_three_level_subject_hierarchy(): void
    {
        $csv = $this->buildParsianCsv([
            $this->parsianRow(503, '1404/12/21', 19, 7, 1, 0, 1000, 'desc', 'شریک - آقای امین‌زاده'),
            $this->parsianRow(503, '1404/12/21', 11, 4, 0, 1000, 0, 'desc', 'بانک پاسارگاد'),
        ]);

        $this->runCsvImport($csv, 'parsian');

        $leaf = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('code', '019007001')->first();
        $mid = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('code', '019007')->first();
        $root = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('code', '019')->first();

        $this->assertNotNull($leaf);
        $this->assertSame('شریک - آقای امین‌زاده', $leaf->name);
        $this->assertNotNull($mid);
        $this->assertSame($mid->id, $leaf->parent_id);
        $this->assertNotNull($root);
        $this->assertSame($root->id, $mid->parent_id);
    }

    public function test_parsian_csv_import_is_idempotent(): void
    {
        $csv = $this->buildParsianCsv([
            $this->parsianRow(503, '1404/12/21', 11, 4, 0, 1000, 0, 'desc', 'بانک پاسارگاد'),
            $this->parsianRow(503, '1404/12/21', 19, 7, 0, 0, 1000, 'desc', 'سایر'),
        ]);

        $this->runCsvImport($csv, 'parsian');
        $docsAfterFirst = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count();

        $this->runCsvImport($csv, 'parsian');
        $docsAfterSecond = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count();

        $this->assertSame($docsAfterFirst, $docsAfterSecond, 'Re-importing same Parsian CSV must not create duplicates');
    }

    public function test_parsian_trial_balance_import_creates_kol_subjects(): void
    {
        $csv = $this->buildParsianTrialBalanceCsv([
            [11, 'بانک ها', 60000000, 58000000, 2000000, 0],
            [19, 'سایر حسابهای پرداختنی', 20000000, 24000000, 0, 4000000],
        ]);

        $result = $this->runCsvImport($csv, 'parsian');

        $this->assertSame(2, $result['subjects_created']);

        $this->assertDatabaseHas('subjects', ['company_id' => $this->company->id, 'code' => '011', 'name' => 'بانک ها']);
        $this->assertDatabaseHas('subjects', ['company_id' => $this->company->id, 'code' => '019', 'name' => 'سایر حسابهای پرداختنی']);
    }

    public function test_parsian_transaction_import_uses_trial_balance_kol_names(): void
    {
        $trialBalanceCsv = $this->buildParsianTrialBalanceCsv([
            [11, 'بانک ها', 0, 0, 0, 0],
        ]);
        $this->runCsvImport($trialBalanceCsv, 'parsian');

        $transactionCsv = $this->buildParsianCsv([
            $this->parsianRow(503, '1404/12/21', 11, 4, 0, 1000, 0, 'desc', 'بانک پاسارگاد'),
            $this->parsianRow(503, '1404/12/21', 11, 3, 0, 0, 1000, 'desc', 'بانک ملی'),
        ]);
        $this->runCsvImport($transactionCsv, 'parsian');

        $kol11 = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->where('code', '011')->first();
        $this->assertSame('بانک ها', $kol11->name);
    }

    public function test_free_amir_format_rejects_parsian_file(): void
    {
        $parsianFile = $this->buildParsianCsv([
            $this->parsianRow(503, '1404/12/21', 11, 4, 0, 1000, 0, 'desc', 'بانک پاسارگاد'),
        ]);

        try {
            $this->runCsvImport($parsianFile, 'free_amir');
            $this->fail('Expected a ValidationException for a mismatched file.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }

        $this->assertSame(0, Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count());
        $this->assertSame(0, Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count());
    }

    public function test_parsian_format_rejects_free_amir_file(): void
    {
        $freeAmirFile = $this->buildCsv([
            ['7', '2026-03-01', 'Doc', 'manual', 'unapproved', '001', '', '', 'Assets', 'Desc', '100', '100'],
        ]);

        try {
            $this->runCsvImport($freeAmirFile, 'parsian');
            $this->fail('Expected a ValidationException for a mismatched file.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }

        $this->assertSame(0, Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count());
        $this->assertSame(0, Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count());
    }

    public function test_import_requires_a_format_to_be_selected(): void
    {
        $file = $this->buildCsv([
            ['7', '2026-03-01', 'Doc', 'manual', 'unapproved', '001', '', '', 'Assets', 'Desc', '100', '100'],
        ]);

        $response = $this->post(route('documents.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('format');
        $this->assertSame(0, Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $this->company->id)->count());
    }

    public function test_export_always_includes_mandatory_columns_even_when_none_selected(): void
    {
        $root = Subject::factory()->create(['company_id' => $this->company->id, 'code' => '001', 'name' => 'Assets']);
        $document = Document::factory()->create(['company_id' => $this->company->id, 'number' => 1, 'date' => '2026-01-01']);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $root->id, 'value' => 1000, 'user_id' => $this->user->id]);
        $csv = $this->exportCsvViaService(['columns_selected' => 1, 'columns' => []]);

        $headerLine = strtok($csv, "\n");
        foreach (DocumentImportExportService::MANDATORY_COLUMNS as $col) {
            $this->assertStringContainsString(__($col), $headerLine, "Mandatory column {$col} must be in the export header.");
        }
    }

    public function test_export_column_ordering_is_preserved(): void
    {
        $root = Subject::factory()->create(['company_id' => $this->company->id, 'code' => '001', 'name' => 'Assets']);
        $document = Document::factory()->create(['company_id' => $this->company->id, 'number' => 1, 'date' => '2026-01-01']);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $root->id, 'value' => 1000, 'user_id' => $this->user->id]);
        $csv = $this->exportCsvViaService(['columns_selected' => 1, 'columns' => ['transaction_desc', 'doc_title']]);

        $headerLine = trim(strtok($csv, "\n"), "\xEF\xBB\xBF\r\n");
        $actual = array_map('trim', str_getcsv($headerLine));

        $expectedColumns = array_reverse(array_values(array_filter(
            DocumentImportExportService::ALL_COLUMNS,
            fn ($col) => in_array($col, DocumentImportExportService::MANDATORY_COLUMNS, true) || in_array($col, ['transaction_desc', 'doc_title'], true)
        )));
        $expected = array_map(fn ($col) => __($col), $expectedColumns);

        $this->assertSame($expected, $actual);
    }
}
