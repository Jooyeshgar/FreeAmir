<?php

namespace App\Services\DocumentImportExport;

use App\Models\User;

/**
 * Base class for a document import format (Free Amir, Parsian, ...).
 */
abstract class DocumentImportFormat
{
    protected ImportSubjectResolver $subjects;

    /** @var array{subjects_created:int, subjects_skipped:int, documents_created:int, documents_skipped:int, rows_skipped:int, errors:array<int,string>} */
    protected array $result;

    public function __construct(?ImportSubjectResolver $subjects = null)
    {
        $this->subjects = $subjects ?? new ImportSubjectResolver;
    }

    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function matches(array $headers): bool;

    abstract public function import(array $rows, User $user): array;

    protected function initResult(): void
    {
        $this->subjects->reset();
        $this->result = [
            'subjects_created' => 0,
            'subjects_skipped' => 0,
            'documents_created' => 0,
            'documents_skipped' => 0,
            'rows_skipped' => 0,
            'errors' => [],
        ];
    }
}
