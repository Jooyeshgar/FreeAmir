<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $accessibleFiscalYear;

    private FiscalYear $inaccessibleFiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->accessibleFiscalYear = FiscalYear::factory()->create(['year' => 1402]);
        $this->accessibleFiscalYear->company->update(['name' => 'Accessible Source']);
        $this->inaccessibleFiscalYear = FiscalYear::factory()->create(['year' => 1403]);
        $this->inaccessibleFiscalYear->company->update(['name' => 'Inaccessible Source']);

        $this->accessibleFiscalYear->users()->syncWithoutDetaching([$this->user->id]);
        $this->inaccessibleFiscalYear->users()->detach($this->user->id);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'companies.create']),
            Permission::firstOrCreate(['name' => 'companies.store']),
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => (string) $this->accessibleFiscalYear->id]);
    }

    public function test_create_form_lists_only_companies_accessible_to_user(): void
    {
        $response = $this->get(route('companies.create'));

        $response->assertOk();
        $response->assertSee('Accessible Source');
        $response->assertDontSee('Inaccessible Source');
    }

    public function test_super_admin_without_a_source_company_can_create_their_first_company(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));

        $form = $this->actingAs($superAdmin)->get(route('companies.create'));

        $form->assertOk()->assertDontSee('id="previousYears"', false)->assertDontSee('name="source_year_id"', false);

        $response = $this->post(route('companies.store'), [
            'name' => 'First Company',
            'fiscal_year' => 1405,
            'currency' => 'Rial',
        ]);

        $response->assertRedirect(route('companies.index'));

        $fiscalYear = FiscalYear::whereHas('company', fn ($query) => $query->where('name', 'First Company'))->firstOrFail();
        $this->assertTrue($fiscalYear->users()->whereKey($superAdmin->id)->exists());
    }

    public function test_company_index_shows_create_button_to_super_admin_without_companies(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));

        $response = $this->actingAs($superAdmin)->withSession(['interface_mode' => 'management'])->get(route('companies.index'));
        $response->assertOk()->assertSee('data-testid="create-first-company"', false);
    }

    public function test_company_index_hides_first_company_button_after_super_admin_has_a_company(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));
        $this->accessibleFiscalYear->users()->attach($superAdmin);

        $response = $this->actingAs($superAdmin)->withSession(['interface_mode' => 'management'])->get(route('companies.index'));
        $response->assertOk()->assertDontSee('data-testid="create-first-company"', false);
    }

    public function test_closed_fiscal_year_has_an_enabled_link_to_the_closing_wizard(): void
    {
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'companies.index']),
            Permission::firstOrCreate(['name' => 'companies.close-fiscal-year']),
            Permission::firstOrCreate(['name' => 'companies.closing-wizard']),
        );
        $this->accessibleFiscalYear->update(['closed_at' => now()]);

        $response = $this->get(route('companies.index'));

        $response->assertOk()
            ->assertSee(route('companies.closing-wizard', $this->accessibleFiscalYear), false)
            ->assertSee(__('Review Fiscal Year Closing'))
            ->assertSee('btn-info btn-outline', false)
            ->assertDontSee('btn-disabled pointer-events-none', false);

        $this->get(route('companies.closing-wizard', $this->accessibleFiscalYear))->assertOk();
    }

    public function test_closing_another_accessible_company_reports_a_validation_error_without_changing_it(): void
    {
        $otherCompany = Company::factory()->create(['name' => 'Other Accessible Company']);
        $otherCompany->users()->attach($this->user);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'companies.close-fiscal-year']),
            Permission::firstOrCreate(['name' => 'companies.closing-wizard']),
            Permission::firstOrCreate(['name' => 'companies.closing-wizard.step1']),
            Permission::firstOrCreate(['name' => 'companies.closing-wizard.step3']),
            Permission::firstOrCreate(['name' => 'companies.closing-wizard.recalculate']),
        );

        foreach (['companies.close-fiscal-year', 'companies.closing-wizard.step1', 'companies.closing-wizard.step3', 'companies.closing-wizard.recalculate'] as $route) {
            $response = $this->from(route('companies.closing-wizard', $otherCompany))
                ->post(route($route, $otherCompany));

            $response->assertRedirect(route('companies.closing-wizard', $otherCompany));
            $response->assertSessionHasErrors([
                'company' => __('Select this company as the active company before closing its fiscal year.'),
            ]);
        }

        $this->get(route('companies.closing-wizard', $otherCompany))
            ->assertOk()
            ->assertSee(__('Select this company as the active company before closing its fiscal year.'));

        $this->assertNull($otherCompany->fresh()->pl_document_id);
        $this->assertNull($otherCompany->closed_at);
    }

    public function test_user_can_delete_an_accessible_company(): void
    {
        Storage::fake('public');
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.destroy']));
        config(['active-fiscal-year-id' => $this->accessibleFiscalYear->id]);
        $document = Document::factory()->create(['fiscal_year_id' => $this->accessibleFiscalYear->id]);
        $path = "documents/{$document->id}/attachment.pdf";
        Storage::disk('public')->put($path, 'attachment');
        DocumentFile::create([
            'document_id' => $document->id,
            'user_id' => $this->user->id,
            'name' => 'attachment.pdf',
            'path' => $path,
        ]);

        $response = $this->delete(route('companies.destroy', $this->accessibleFiscalYear));

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHas('success', __('Company deleted successfully.'));
        $this->assertDatabaseMissing('fiscal_years', ['id' => $this->accessibleFiscalYear->id]);
        $this->assertDatabaseMissing('fiscal_year_user', ['fiscal_year_id' => $this->accessibleFiscalYear->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_storage_cleanup_error_does_not_prevent_company_deletion(): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.destroy']));
        config(['active-fiscal-year-id' => $this->accessibleFiscalYear->id]);
        $document = Document::factory()->create(['fiscal_year_id' => $this->accessibleFiscalYear->id]);
        DocumentFile::create([
            'document_id' => $document->id,
            'user_id' => $this->user->id,
            'name' => 'imported.json',
            'path' => "documents/{$document->id}/imported.json",
        ]);
        Storage::shouldReceive('disk')->once()->with('public')->andThrow(new \RuntimeException('Storage unavailable'));

        $response = $this->delete(route('companies.destroy', $this->accessibleFiscalYear));

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHas('success', __('Company deleted successfully.'));
        $this->assertDatabaseMissing('fiscal_years', ['id' => $this->accessibleFiscalYear->id]);
    }

    public function test_company_deletion_shows_validation_exception(): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.destroy']));
        FiscalYear::deleting(function (): never {
            throw ValidationException::withMessages([
                'fiscal_year' => ['Imported fiscal year data is invalid.'],
            ]);
        });

        $response = $this->delete(route('companies.destroy', $this->accessibleFiscalYear));

        $response->assertRedirect(route('companies.index'));
        $response->assertSessionHasErrors([
            'fiscal_year' => 'Imported fiscal year data is invalid.',
        ]);
        $this->assertDatabaseHas('fiscal_years', ['id' => $this->accessibleFiscalYear->id]);
    }

    public function test_store_rejects_inaccessible_source_company(): void
    {
        $fiscalYearCount = FiscalYear::count();

        $response = $this->post(route('companies.store'), [
            'name' => 'Unauthorized Copy',
            'fiscal_year' => 1405,
            'source_year_id' => $this->inaccessibleFiscalYear->id,
            'tables_to_copy' => [FiscalYearSection::SUBJECTS->value],
        ]);

        $response->assertSessionHasErrors('source_year_id');
        $this->assertSame($fiscalYearCount, FiscalYear::count());
        $this->assertDatabaseMissing('companies', ['name' => 'Unauthorized Copy']);
    }

    public function test_store_accepts_accessible_source_company_during_validation(): void
    {
        $response = $this->post(route('companies.store'), [
            'source_year_id' => $this->accessibleFiscalYear->id,
            'tables_to_copy' => [FiscalYearSection::SUBJECTS->value],
        ]);

        $response->assertSessionHasErrors('name');
        $response->assertSessionDoesntHaveErrors('source_year_id');
    }

    public function test_store_copies_bank_accounts_with_same_iban_into_new_company(): void
    {
        config(['active-fiscal-year-id' => $this->accessibleFiscalYear->id]);

        $bank = Bank::create([
            'name' => 'Source Bank',
            'fiscal_year_id' => $this->accessibleFiscalYear->id,
        ]);

        $bankRoot = Subject::create([
            'code' => '010',
            'name' => 'Banks',
            'type' => 3,
            'fiscal_year_id' => $this->accessibleFiscalYear->id,
            'parent_id' => null,
        ]);

        $accountSubject = Subject::create([
            'code' => '010001',
            'name' => 'Main Account',
            'type' => 3,
            'fiscal_year_id' => $this->accessibleFiscalYear->id,
            'parent_id' => $bankRoot->id,
        ]);

        $sourceAccount = new BankAccount;
        $sourceAccount->forceFill([
            'name' => 'Main Account',
            'number' => '123456789',
            'type' => 1,
            'owner' => 'Source Owner',
            'bank_id' => $bank->id,
            'fiscal_year_id' => $this->accessibleFiscalYear->id,
            'subject_id' => $accountSubject->id,
            'iban' => 'IR163212724891703088374062',
        ])->saveQuietly();

        $accountSubject->subjectable()->associate($sourceAccount);
        $accountSubject->save();

        $response = $this->post(route('companies.store'), [
            'name' => 'Copied Company',
            'fiscal_year' => 1405,
            'source_year_id' => $this->accessibleFiscalYear->id,
            'tables_to_copy' => [
                FiscalYearSection::SUBJECTS->value,
                FiscalYearSection::BANKS->value,
            ],
        ]);

        $response->assertRedirect(route('companies.index'));

        $newFiscalYear = FiscalYear::where('year', 1405)->firstOrFail();

        $this->assertDatabaseHas('bank_accounts', [
            'fiscal_year_id' => $this->accessibleFiscalYear->id,
            'iban' => 'IR163212724891703088374062',
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'fiscal_year_id' => $newFiscalYear->id,
            'iban' => 'IR163212724891703088374062',
        ]);
    }
}
