<?php

namespace App\Services;

/**
 * Central catalogue of the supported document import formats.
 */
class DocumentImportFormatRegistry
{
    private const FORMATS = [
        FreeAmirImportFormat::class,
        ParsianImportFormat::class,
    ];

    public function all(): array
    {
        return array_map(fn (string $class) => new $class, self::FORMATS);
    }

    public function keys(): array
    {
        return array_map(fn (DocumentImportFormat $format) => $format->key(), $this->all());
    }

    /**
     * Key => translated label, for building a select box.
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->all() as $format) {
            $options[$format->key()] = $format->label();
        }

        return $options;
    }

    public function get(string $key): DocumentImportFormat
    {
        foreach ($this->all() as $format) {
            if ($format->key() === $key) {
                return $format;
            }
        }

        throw new \InvalidArgumentException("Unknown import format: {$key}");
    }
}
