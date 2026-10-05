<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    private const YEAR_ATTRIBUTES = [
        'fiscal_year', 'closed_at', 'closed_by', 'pl_document_id',
        'closing_document_id', 'closing_recalculation_step',
    ];

    public function definition()
    {
        return [
            'name' => $this->faker->company,
            'address' => $this->faker->address,
            'postal_code' => $this->faker->postcode,
            'phone_number' => $this->faker->phoneNumber,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Company $company) {
            $user = User::first() ?? User::factory()->create();
            $user->companies()->syncWithoutDetaching([$company->id]);

            $attributes = $company->getRelation('factoryFiscalYearAttributes');
            if ($attributes['without_fiscal_year'] ?? false) {
                return;
            }
            $year = FiscalYear::create(array_merge([
                'company_id' => $company->id,
                'year' => $attributes['fiscal_year'] ?? 1403,
            ], collect($attributes)->except('fiscal_year')->all()));
            $year->users()->syncWithoutDetaching([$user->id]);

            $activeYearId = getActiveFiscalYear();
            $activeCompanyId = getActiveCompany();
            $activeContextIsValid = $activeYearId && $activeCompanyId
                && FiscalYear::query()->whereKey($activeYearId)->where('company_id', $activeCompanyId)->exists();

            if (! $activeContextIsValid) {
                config([
                    'active-company-id' => $company->id,
                    'active-fiscal-year-id' => $year->id,
                ]);
            }
        });
    }

    public function newModel(array $attributes = []): Model
    {
        $yearAttributes = array_intersect_key($attributes, array_flip([...self::YEAR_ATTRIBUTES, 'without_fiscal_year']));
        $company = parent::newModel(array_diff_key($attributes, $yearAttributes));
        $company->setRelation('factoryFiscalYearAttributes', $yearAttributes);

        return $company;
    }

    public function withoutFiscalYear(): static
    {
        return $this->state(['without_fiscal_year' => true]);
    }
}
