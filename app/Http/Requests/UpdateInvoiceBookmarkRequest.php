<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceBookmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $this->user()->canAny(['invoices.index', 'invoices.show', 'invoices.*'])
            && (int) $invoice->fiscal_year_id === getActiveFiscalYear()
            && $this->user()->fiscalYears()->whereKey($invoice->fiscal_year_id)->exists();
    }

    public function rules(): array
    {
        return [
            'is_bookmarked' => ['required', 'boolean'],
        ];
    }
}
