<?php

namespace App\Extensions;

use App\Extensions\Contracts\InvoiceExtension;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final class InvoiceExtensionRegistry
{
    /**
     * @var array<string, array{handler: class-string<InvoiceExtension>, priority: int}>
     */
    private array $extensions = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<InvoiceExtension> $handler */
    public function register(string $key, string $handler, int $priority = 100): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/D', $key)) {
            throw new InvalidArgumentException('Invoice extension keys must use lowercase letters, digits, and underscores.');
        }

        if (! is_subclass_of($handler, InvoiceExtension::class)) {
            throw new InvalidArgumentException("Invoice extension [{$key}] must implement the InvoiceExtension contract.");
        }

        if (isset($this->extensions[$key])) {
            throw new LogicException("Invoice extension [{$key}] is already registered.");
        }

        $this->extensions[$key] = ['handler' => $handler, 'priority' => $priority];
    }

    /**
     * @return array<string, array{handler: class-string<InvoiceExtension>, priority: int}>
     */
    public function extensions(): array
    {
        $extensions = $this->extensions;
        uksort($extensions, fn (string $left, string $right): int => [$extensions[$left]['priority'], $left] <=> [$extensions[$right]['priority'], $right]);

        return $extensions;
    }

    /**
     * Validate only payloads explicitly submitted by the request.
     *
     * @return array<string, array<string, mixed>>
     */
    public function validateRequest(Request $request, ?Model $invoice = null): array
    {
        $payloads = $request->input('extensions', []);
        if (! is_array($payloads)) {
            throw ValidationException::withMessages(['extensions' => __('The extensions field must be an array.')]);
        }

        foreach ($payloads as $key => $input) {
            if (! isset($this->extensions[$key]) || ! is_array($input)) {
                throw ValidationException::withMessages(['extensions' => __('The extension payload is invalid.')]);
            }
        }

        $validated = [];
        foreach ($this->extensions() as $key => $definition) {
            if (! array_key_exists($key, $payloads)) {
                continue;
            }

            $validated[$key] = $this->container->make($definition['handler'])->validate($payloads[$key], $invoice);
        }

        return $validated;
    }

    /**
     * Persist validated payloads. Missing keys intentionally mean no change.
     * An explicitly submitted empty array remains available to the extension
     * so it can implement a remove-all operation.
     *
     * @param  array<string, array<string, mixed>>  $payloads
     */
    public function persist(Model $invoice, array $payloads): void
    {
        if (array_diff_key($payloads, $this->extensions) !== []) {
            throw new LogicException('Cannot persist data for an unregistered invoice extension.');
        }

        foreach ($this->extensions() as $key => $definition) {
            if (array_key_exists($key, $payloads)) {
                $this->container->make($definition['handler'])->persist($invoice, $payloads[$key]);
            }
        }
    }

    public function extendQuery(Builder $query, Request $request): void
    {
        foreach ($this->extensions() as $definition) {
            $this->container->make($definition['handler'])->extendQuery($query, $request);
        }
    }

    /**
     * @return array<int, string>
     */
    public function csvHeaders(): array
    {
        $headers = [];
        foreach ($this->extensions() as $definition) {
            $handler = $this->container->make($definition['handler']);
            if (method_exists($handler, 'csvHeaders')) {
                foreach ($handler->csvHeaders() as $header) {
                    $headers[] = (string) $header;
                }
            }
        }

        return $headers;
    }

    /**
     * @return array<int, string>
     */
    public function csvRow(Model $invoice): array
    {
        $row = [];
        foreach ($this->extensions() as $definition) {
            $handler = $this->container->make($definition['handler']);
            if (method_exists($handler, 'csvRow')) {
                foreach ($handler->csvRow($invoice) as $value) {
                    $row[] = (string) $value;
                }
            }
        }

        return $row;
    }
}
