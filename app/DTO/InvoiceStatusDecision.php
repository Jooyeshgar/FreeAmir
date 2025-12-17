<?php

namespace App\DTO;

use Illuminate\Support\Collection;

class InvoiceStatusDecision
{
    public bool $canProceed = true;

    public bool $needsConfirmation = false;

    public Collection $messages;

    public Collection $conflicts;

    public function __construct()
    {
        $this->messages = collect();
        $this->conflicts = collect();
    }

    public function addMessage(string $type, string $text, array $meta = []): void
    {
        $this->messages->push(new DomainValidationMessage($type, $text, $meta));

        if ($type === 'error') {
            $this->canProceed = false;
        }

        if ($type === 'warning') {
            $this->needsConfirmation = true;
        }
    }

    public function hasErrors(): bool
    {
        return $this->messages->contains(fn ($m) => $m->type === 'error');
    }

    public function hasWarning(): bool
    {
        return $this->messages->contains(fn ($m) => $m->type === 'warning');
    }

    public function hasMessage(): bool
    {
        return $this->messages->isNotEmpty();
    }

    public function toDetailText(): string
    {
        $lines = [];

        foreach ($this->messages as $message) {
            $lines[] = e($message->text).':';
        }

        $conflictSummary = $this->conflictSummaryText();
        if ($conflictSummary !== null) {
            $lines[] = e($conflictSummary);
        }

        return implode('<br>', $lines);
    }

    public function toText(): string
    {
        $groups = $this->messages->groupBy('type');
        $parts = [];

        if ($groups->has('error')) {
            $errors = $groups->get('error')->pluck('text')->join('; ');
            $parts[] = 'Errors: '.$errors;
        }

        if ($groups->has('warning')) {
            $warnings = $groups->get('warning')->pluck('text')->join('; ');
            $parts[] = 'Warnings: '.$warnings;
        }

        // include any other message types
        $otherTypes = $groups->keys()->reject(fn ($k) => in_array($k, ['error', 'warning']));
        foreach ($otherTypes as $type) {
            $texts = $groups->get($type)->pluck('text')->join('; ');
            $parts[] = ucfirst((string) $type).': '.$texts;
        }

        $conflictSummary = $this->conflictSummaryText();
        if ($conflictSummary !== null) {
            $parts[] = 'Conflicts: '.$conflictSummary;
        }

        return collect($parts)->join(' | ');
    }

    private function conflictSummaryText(): ?string
    {
        $grouped = $this->groupedConflictsByType();
        if ($grouped->isEmpty()) {
            return null;
        }

        $typeTexts = $grouped->map(function (Collection $conflicts, string $type): string {
            $label = $this->conflictTypeLabel($type);
            $items = $this->formatConflicts($type, $conflicts)->filter(fn ($v) => $v !== null && $v !== '')->values();

            if ($items->isEmpty()) {
                return $label;
            }

            return $label.': '.$items->join(', ');
        })->values();

        return $typeTexts->join(' | ');
    }

    private function conflictTypeLabel(string $type): string
    {
        return match ($type) {
            'buy invoice' => 'Buy Invoice',
            'sell invoice' => 'Sell Invoice',
            'product' => 'Product',
            'ancillarycost' => 'Ancillary Cost',
            default => ucfirst($type),
        };
    }

    private function formatConflicts(string $type, Collection $conflicts): Collection
    {
        return match ($type) {
            'buy invoice', 'sell invoice' => $conflicts->map(fn ($inv) => data_get($inv, 'number', 'unknown')),
            'product' => $conflicts->map(fn ($prod) => sprintf('Code: %s, Name: %s', data_get($prod, 'code', 'unknown'), data_get($prod, 'name', 'unknown'))),
            'ancillarycost' => $conflicts->map(fn ($cost) => sprintf('Invoice Number: %s, Type: %s', data_get($cost, 'invoice.number', 'unknown'), data_get($cost, 'type.value', 'unknown'))),
            default => collect(),
        };
    }

    private function groupedConflictsByType(): Collection
    {
        return $this->conflicts->groupBy(fn ($conflict) => $conflict instanceof \App\Models\Invoice ?
                $conflict->invoice_type->value.' invoice' : strtolower(class_basename($conflict)));
    }

    public function addConflict($conflict): void
    {
        $this->conflicts->push($conflict);
    }
}
