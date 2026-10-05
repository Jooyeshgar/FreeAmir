<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FiscalYearWorkflowIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_selective_copy_creates_a_new_year_under_the_same_company(): void
    {
        $user = User::factory()->create();
        Company::factory()->withoutFiscalYear()->create(['name' => 'Unrelated']);
        $source = Company::factory()->withoutFiscalYear()->create(['name' => 'Shared Business']);
        $sourceYear = FiscalYear::create(['company_id' => $source->id, 'year' => 1402]);
        $source->users()->attach($user);
        $sourceYear->users()->attach($user);
        $this->actingAs($user);

        Subject::create([
            'company_id' => $source->id,
            'fiscal_year_id' => $sourceYear->id,
            'code' => '100',
            'name' => 'Cash',
            'type' => SubjectType::BOTH,
            'parent_id' => null,
        ]);
        Document::create([
            'company_id' => $source->id,
            'fiscal_year_id' => $sourceYear->id,
            'number' => 1,
            'date' => now(),
            'title' => 'Do not copy',
            'creator_id' => $user->id,
        ]);

        $target = FiscalYearService::createWithCopiedData(
            ['name' => $source->name, 'fiscal_year' => 1403],
            $sourceYear->id,
            ['subjects']
        );

        $this->assertSame($source->id, $target->company_id);
        $this->assertSame(2, Company::count());
        $this->assertSame(2, FiscalYear::count());
        $this->assertDatabaseHas('subjects', [
            'company_id' => $source->id,
            'fiscal_year_id' => $target->id,
            'code' => '100',
        ]);
        $this->assertDatabaseMissing('documents', ['fiscal_year_id' => $target->id]);
        $this->assertTrue($user->fiscalYears()->whereKey($target->id)->exists());
    }

    public function test_export_and_import_preserve_one_years_accounting_links_files_and_closing_state(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Company::factory()->withoutFiscalYear()->create(['name' => 'Unrelated']);
        $source = Company::factory()->withoutFiscalYear()->create(['name' => 'Shared Business']);
        $sourceYear = FiscalYear::create(['company_id' => $source->id, 'year' => 1402]);
        $otherYear = FiscalYear::create(['company_id' => $source->id, 'year' => 1403]);
        $source->users()->attach($user);
        $sourceYear->users()->attach($user);
        $otherYear->users()->attach($user);
        $this->actingAs($user);

        $debit = Subject::create(['company_id' => $source->id, 'fiscal_year_id' => $sourceYear->id, 'code' => '100', 'name' => 'Cash', 'type' => SubjectType::BOTH, 'parent_id' => null]);
        $credit = Subject::create(['company_id' => $source->id, 'fiscal_year_id' => $sourceYear->id, 'code' => '200', 'name' => 'Equity', 'type' => SubjectType::BOTH, 'parent_id' => null]);
        $document = Document::create([
            'company_id' => $source->id,
            'fiscal_year_id' => $sourceYear->id,
            'number' => 1,
            'date' => now(),
            'title' => 'Source entry',
            'creator_id' => $user->id,
            'approved_at' => now(),
            'approver_id' => $user->id,
        ]);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $debit->id, 'user_id' => $user->id, 'value' => 100]);
        Transaction::create(['document_id' => $document->id, 'subject_id' => $credit->id, 'user_id' => $user->id, 'value' => -100]);
        Storage::disk('public')->put('documents/source/receipt.txt', 'receipt contents');
        DocumentFile::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => 'receipt.txt',
            'path' => 'documents/source/receipt.txt',
        ]);
        Document::create(['company_id' => $source->id, 'fiscal_year_id' => $otherYear->id, 'number' => 1, 'date' => now(), 'title' => 'Other year', 'creator_id' => $user->id]);
        $sourceYear->forceFill([
            'closed_at' => now(),
            'closed_by' => $user->id,
            'pl_document_id' => $document->id,
            'closing_document_id' => $document->id,
        ])->save();

        $payload = FiscalYearService::exportData($sourceYear->id, ['subjects', 'documents', 'document_files']);
        FiscalYearService::documentFilesInBase64($payload);

        $this->assertSame($sourceYear->id, $payload['meta']['source_fiscal_year_id']);
        $this->assertSame($source->id, $payload['meta']['source_company_id']);
        $this->assertCount(1, $payload['documents']);
        $this->assertSame('Source entry', $payload['documents'][0]['title']);
        $this->assertCount(2, $payload['transactions']);
        $this->assertSame('receipt contents', base64_decode($payload['document_files'][0]['document_file']['content']));

        $restored = FiscalYearService::importData($payload, ['name' => $source->name, 'fiscal_year' => 1404]);
        $restoredDocument = Document::withoutGlobalScopes()->where('fiscal_year_id', $restored->id)->firstOrFail();
        $this->assertSame($source->id, $restored->company_id);
        $this->assertSame($restoredDocument->id, $restored->fresh()->closing_document_id);
        $this->assertNotNull($restored->fresh()->closed_at);
        $this->assertEqualsCanonicalizing([100, -100], $restoredDocument->transactions()->pluck('value')->map(fn ($value) => (int) $value)->all());
        $restoredFile = DocumentFile::where('document_id', $restoredDocument->id)->firstOrFail();
        $this->assertSame('receipt contents', Storage::disk('public')->get($restoredFile->path));
        $this->assertTrue($user->fiscalYears()->whereKey($restored->id)->exists());
    }

    public function test_import_rejects_duplicate_year_without_modifying_existing_year(): void
    {
        $source = Company::factory()->withoutFiscalYear()->create(['name' => 'Shared Business']);
        FiscalYear::create(['company_id' => $source->id, 'year' => 1402]);

        $this->expectException(ValidationException::class);
        try {
            FiscalYearService::importData([], ['name' => $source->name, 'fiscal_year' => 1402]);
        } finally {
            $this->assertSame(1, FiscalYear::count());
        }
    }
}
