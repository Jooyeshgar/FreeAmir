<?php

namespace App\Http\Requests;

/** MCP already supplies Gregorian dates and structured items; reuse the invoice's domain validation. */
class StoreMcpInvoiceRequest extends StoreInvoiceRequest
{
    protected function prepareForValidation(): void
    {
        // Keep the MCP date independent of the application's Persian/English UI locale.
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['document_number']);
        // The MCP tool already checks every item against a fiscal-scoped batch lookup.
        $rules['transactions.*.item_id'] = ['required', 'integer'];

        return $rules;
    }
}
