<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Models\Subject;

class TransactionFilter extends FilterAbstract
{
    protected array $filterables = [
        'subject_id' => self::TYPE_INTEGER,
        'start_date' => self::TYPE_STRING,
        'end_date' => self::TYPE_STRING,
        'start_document_number' => self::TYPE_STRING,
        'end_document_number' => self::TYPE_STRING,
    ];

    protected array $searchables = [
        'desc',
        'subject.name',
        'subject.code',
        'document.title',
        'document.number',
    ];

    public function subject_id(int $subjectId): void
    {
        if ($subjectId > 0) {
            $subject = Subject::findOrFail($subjectId);
            $this->builder->whereIn('subject_id', $subject->getAllDescendantIds());
        }
    }

    public function start_date(string $date): void
    {
        if ($date !== '') {
            $this->builder->whereHas('document', fn ($query) => $query->whereDate('date', '>=', convertToGregorian($date)));
        }
    }

    public function end_date(string $date): void
    {
        if ($date !== '') {
            $this->builder->whereHas('document', fn ($query) => $query->whereDate('date', '<=', convertToGregorian($date)));
        }
    }

    public function start_document_number(string $number): void
    {
        if ($number !== '') {
            $this->builder->whereHas('document', fn ($query) => $query->where('number', '>=', convertToFloat($number)));
        }
    }

    public function end_document_number(string $number): void
    {
        if ($number !== '') {
            $this->builder->whereHas('document', fn ($query) => $query->where('number', '<=', convertToFloat($number)));
        }
    }
}
