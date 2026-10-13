<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PreventClosedFiscalYearMutationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/test-closed-fiscal-year-mutation', fn () => response('mutated'))
            ->middleware(['web', 'auth', 'prevent-closed-fiscal-year-mutations'])
            ->name('test.closed-fiscal-year-mutation');
    }

    public function test_mutations_are_rejected_for_a_closed_active_fiscal_year(): void
    {
        $user = User::factory()->create();
        $fiscalYear = FiscalYear::factory()->create(['closed_at' => now()]);
        $fiscalYear->users()->attach($user);

        $response = $this->actingAs($user)
            ->withCookies(['active-fiscal-year-id' => (string) $fiscalYear->id])
            ->from('/source')
            ->post('/test-closed-fiscal-year-mutation');

        $response->assertRedirect('/source')
            ->assertSessionHas('error', __('This fiscal year is closed and cannot be modified.'));
    }

    public function test_mutations_continue_for_an_open_active_fiscal_year(): void
    {
        $user = User::factory()->create();
        $fiscalYear = FiscalYear::factory()->create();
        $fiscalYear->users()->attach($user);

        $response = $this->actingAs($user)
            ->withCookies(['active-fiscal-year-id' => (string) $fiscalYear->id])
            ->post('/test-closed-fiscal-year-mutation');

        $response->assertOk()->assertSee('mutated');
    }

    public function test_model_updates_are_rejected_even_without_an_http_request(): void
    {
        $fiscalYear = FiscalYear::factory()->create(['closed_at' => now()]);
        $document = Document::factory()->create(['fiscal_year_id' => $fiscalYear->id]);

        $this->expectException(ValidationException::class);
        $document->update(['title' => 'Attempted edit']);
    }

    public function test_related_model_deletes_are_rejected_even_without_an_http_request(): void
    {
        $fiscalYear = FiscalYear::factory()->create(['closed_at' => now()]);
        $document = Document::factory()->create(['fiscal_year_id' => $fiscalYear->id]);
        $transaction = Transaction::factory()->create(['document_id' => $document->id]);

        $this->expectException(ValidationException::class);
        $transaction->delete();
    }
}
