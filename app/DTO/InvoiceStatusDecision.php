<?php

namespace App\DTO;

use Illuminate\Support\Collection;

class InvoiceStatusDecision
{
    public bool $canProceed = true;

    public bool $needsConfirmation = false;

    public Collection $messages;

    public Collection $conflicts; // collection of invoices that cause conflicts

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

        if ($this->conflicts->isNotEmpty()) {
            $conflictTexts = $this->conflicts
                ->map(fn ($c) => sprintf('%s (%s)', data_get($c, 'number', 'unknown'), data_get($c, 'type', 'unknown')))
                ->join(', ');
            $parts[] = 'Conflicts: '.$conflictTexts;
        }

        return collect($parts)->join(' | ');
    }

    public function addConflict($conflict): void
    {
        $conflict->type ??= $conflict instanceof \App\Models\Invoice
            ? __('Invoice').' '.$conflict->invoice_type->label()
            : __(class_basename($conflict));

        $conflict->number ??= $conflict->code ?? $conflict->document->number ?? null;

        $this->conflicts->push($conflict);
    }
}
