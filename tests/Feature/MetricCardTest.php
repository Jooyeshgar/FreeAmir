<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetricCardTest extends TestCase
{
    public function test_existing_metric_cards_keep_their_value_change_and_progress(): void
    {
        $this->blade('<x-metric-card title="Income" :value="1200" suffix="Rial" :change="5" :progress="60" detail="Annual total" />')
            ->assertSee('Income')
            ->assertSee(formatNumber(1200))
            ->assertSee('Rial')
            ->assertSee('Annual total')
            ->assertSee('value="60"', false)
            ->assertSee('text-success', false)
            ->assertSee(formatNumber(5));
    }

    public function test_increase_is_marked_as_unfavorable_when_increase_is_not_good(): void
    {
        $this->blade('<x-metric-card title="Expense" :value="1200" :change="10" :increase-is-good="false" />')
            ->assertSee('text-error', false)
            ->assertDontSee('text-success', false)
            ->assertSee('↑');

        $this->blade('<x-metric-card title="Expense" :value="1200" :change="-10" :increase-is-good="false" />')
            ->assertSee('text-success', false)
            ->assertDontSee('text-error', false)
            ->assertSee('↓');
    }
}
