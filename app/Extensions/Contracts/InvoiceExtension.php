<?php

namespace App\Extensions\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface InvoiceExtension
{
    /**
     * Validate and normalize this extension's request payload.
     *
     * The payload is read from extensions[<key>] and is omitted when the
     * extension did not participate in the request.
     *
     * @return array<string, mixed>
     */
    public function validate(array $input, ?Model $invoice = null): array;

    /**
     * Persist extension data for an already persisted invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function persist(Model $invoice, array $data): void;

    /**
     * Add this extension's filters to the invoice query.
     */
    public function extendQuery(Builder $query, Request $request): void;
}
