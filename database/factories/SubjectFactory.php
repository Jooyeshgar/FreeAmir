<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => session('active-company-id') ?? Company::inRandomOrder()->first()->id,
            'parent_id' => null,
            'code' => '001', // Will be overridden by withAutoCode()
            'name' => $this->faker->name,
            'type' => 'both',
        ];
    }

    public function withParent(?Subject $parent = null): static
    {
        return $this->state(function () use ($parent) {
            return [
                'parent_id' => $parent?->id,
            ];
        });
    }

    public function withAutoCode(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'code' => $this->generateSubjectCode(
                    $attributes['parent_id'] ?? null,
                    $attributes['company_id']
                ),
            ];
        });
    }

    private function generateSubjectCode(?int $parentId, int $companyId): string
    {
        if ($parentId) {
            $parent = Subject::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->find($parentId);

            if ($parent) {
                $lastChild = Subject::withoutGlobalScopes()
                    ->where('parent_id', $parentId)
                    ->where('company_id', $companyId)
                    ->orderBy('code', 'desc')
                    ->first();

                if ($lastChild) {
                    $lastPortion = (int) substr($lastChild->code, -3);
                    $nextPortion = str_pad($lastPortion + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $nextPortion = '001';
                }

                return $parent->code.$nextPortion;
            }
        }

        // Root level
        $lastRoot = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->orderBy('code', 'desc')
            ->first();

        if ($lastRoot) {
            $nextCode = (int) $lastRoot->code + 1;

            return str_pad($nextCode, 3, '0', STR_PAD_LEFT);
        }

        return '001';
    }
}
