<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\FiscalYearMutationGuard;
use App\Services\InvoiceService;
use App\Services\MoadianService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClosedFiscalYearMutationGuardTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $closedYear;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->closedYear = FiscalYear::factory()->create([
            'company_id' => $company->id,
            'closed_at' => now(),
        ]);
        Cache::forever('active_fiscal_year_id', $this->closedYear->id);

        $this->invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'fiscal_year_id' => $this->closedYear->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'date' => now()->toDateString(),
            'number' => 1,
            'amount' => 100,
            'vat' => 10,
        ]));
    }

    public function test_guard_rejects_a_closed_invoice_year(): void
    {
        $this->expectException(ValidationException::class);
        app(FiscalYearMutationGuard::class)->ensureOpen($this->invoice);
    }

    public function test_nested_invoice_mutations_are_guarded_before_writing(): void
    {
        $user = User::factory()->create();

        foreach ([
            fn () => app(PaymentService::class)->createPayment($user, $this->invoice, ['amount' => 1, 'subject_id' => 1]),
            fn () => InvoiceService::deleteInvoice($this->invoice->id),
            fn () => AncillaryCostService::createAncillaryCost($user, ['fiscal_year_id' => $this->closedYear->id, 'invoice_id' => $this->invoice->id]),
            fn () => app(MoadianService::class)->moadianStatus('reference', $this->invoice),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('A closed-year mutation unexpectedly completed.');
            } catch (ValidationException $exception) {
                $this->assertSame(__('This fiscal year is already closed.'), $exception->errors()['fiscal_year'][0]);
            }
        }

        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id]);
    }
}
