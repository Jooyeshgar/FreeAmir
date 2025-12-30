<?php

namespace App\DTO;

use Illuminate\Support\Collection;

class InvoiceStatusDecision
{
    public bool $canProceed = true;

    public bool $needsConfirmation = false;

    public Collection $messages;

    public Collection $conflicts; // collection of invoices that cause conflicts

    public array $conflictsItems = [];

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
            if ($message->type === 'error') {
                $lines[] = '<span class="text-red-700">'.$message->text.'. </span>';
            } else {
                $lines[] = '<span class="text-yellow-600">'.$message->text.'.</span>';
            }
        }

        $conflictSummary = $this->conflictSummaryText();
        if ($conflictSummary !== null) {
            $lines[] = '<span>'.$conflictSummary.'</span>';
        }

        return implode(' ', $lines);
    }

    public function toText(): string
    {
        $groups = $this->messages->groupBy('type');
        $parts = [];

        if ($groups->has('error')) {
            $errors = $groups->get('error')->pluck('text')->join('; ');
            $parts[] = __('Errors').': '.$errors;
        }

        if ($groups->has('warning')) {
            $warnings = $groups->get('warning')->pluck('text')->join('; ');
            $parts[] = __('Warnings').': '.$warnings;
        }

        // include any other message types
        $otherTypes = $groups->keys()->reject(fn ($k) => in_array($k, ['error', 'warning']));
        foreach ($otherTypes as $type) {
            $texts = $groups->get($type)->pluck('text')->join('; ');
            $parts[] = ucfirst((string) $type).': '.$texts;
        }

        $conflictSummary = $this->conflictSummaryText();
        if ($conflictSummary !== null) {
            $parts[] = __('Conflicts').': '.$conflictSummary;
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
            'buy invoice' => __('Buy Invoices'),
            'sell invoice' => __('Sell Invoices'),
            'product' => __('Products'),
            'ancillarycost' => __('Ancillary Costs'),
            default => __(ucfirst($type)),
        };
    }

    private function formatConflicts(string $type, Collection $conflicts): Collection
    {
        return match ($type) {
            'buy invoice', 'sell invoice' => $conflicts->map(fn ($inv) => formatDocumentNumber(data_get($inv, 'number', 'unknown'))),
            'product' => $conflicts->map(fn ($prod) => sprintf('%s: %s, %s: %s', __('Code'), formatDocumentNumber(data_get($prod, 'code', 'unknown')),
                __('Name'), data_get($prod, 'name', 'unknown'))),
            'ancillarycost' => $conflicts->map(fn ($cost) => sprintf('%s: %s, %s: %s', __('Invoice Number'), data_get($cost, 'invoice.number', 'unknown'),
                __('Type'), data_get($cost, 'type.value', 'unknown'))),
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
        $this->addConflictItem($conflict);
    }

    private function addConflictItem($conflict): void
    {
        $this->conflictsItems[] = [
            'id' => $conflict->id,
            'type' => $conflict->invoice_type ?? strtolower(class_basename($conflict)),
        ];
    }
}
