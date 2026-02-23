<?php

namespace App\Services;

use App\Models\Document;
use Cache;
use DB;

class DocumentNumberService
{
    public function sortStats(): array
    {
        $query = Document::query();

        $numberGapStats = $this->getNumberGapStats();

        return [
            'total_documents_count' => (clone $query)->count(),
            'automatic_documents_count' => (clone $query)->whereNotNull('documentable_type')->count(),
            'manual_documents_count' => (clone $query)->whereNull('documentable_type')->count(),
            'min_document_date' => (clone $query)->min('date'),
            'max_document_date' => (clone $query)->max('date'),
            'min_document_number' => (clone $query)->min('number'),
            'max_document_number' => (clone $query)->max('number'),
            'unused_document_numbers_count' => $numberGapStats['count'],
            'unused_document_numbers_samples' => $numberGapStats['samples'],
        ];
    }

    private function getNumberGapStats(int $sampleLimit = 10): array
    {
        $cursor = Document::query()->selectRaw('DISTINCT CAST(number AS INTEGER) AS number_int')
            ->whereNotNull('number')->orderBy('number_int')->cursor();

        $previousNumber = null;
        $missingCount = 0;
        $sampleNumbers = [];

        foreach ($cursor as $row) {
            $currentNumber = (int) ($row->number_int ?? 0);

            if ($previousNumber === null) {
                $previousNumber = $currentNumber;

                continue;
            }

            if ($currentNumber <= $previousNumber) {
                continue;
            }

            $gap = $currentNumber - $previousNumber - 1;
            if ($gap > 0) {
                $missingCount += $gap;

                if (count($sampleNumbers) < $sampleLimit) {
                    $nextMissingNumber = $previousNumber + 1;
                    while ($nextMissingNumber < $currentNumber && count($sampleNumbers) < $sampleLimit) {
                        $sampleNumbers[] = $nextMissingNumber;
                        $nextMissingNumber++;
                    }
                }
            }

            $previousNumber = $currentNumber;
        }

        return [
            'count' => $missingCount,
            'samples' => $sampleNumbers,
        ];
    }

    /**
     * Persists an initial progress state to cache so that subsequent calls to processNextSortingBatch() know where to continue from.
     * If there are no documents, the sorting progress is immediately marked completed.
     */
    public function initializeSorting(int $userId): array
    {
        $total = Document::count();

        $state = [
            'status' => $total === 0 ? 'completed' : 'running',
            'processed' => 0,
            'total' => $total,
            'next_number' => 1,
            'last_date' => null,
            'last_id' => 0,
            'batch_size' => 50,
            'started_at' => now()->toDateTimeString(),
            'finished_at' => $total === 0 ? now()->toDateTimeString() : null,
        ];

        $this->putState($userId, $state);

        return $this->formatProgress($state);
    }

    /**
     * Return the current progress snapshot of sorting documents number, or a 'not_started' placeholder when sort progress is not cached.
     */
    public function getProgress(int $userId): array
    {
        $state = $this->getState($userId);
        if (! $state) {
            $total = Document::count();

            return $this->formatProgress([
                'status' => 'not_started',
                'processed' => 0,
                'total' => $total,
                'batch_size' => 50,
                'started_at' => null,
                'finished_at' => null,
            ]);
        }

        return $this->formatProgress($state);
    }

    /**
     * Advance the sorting progress by one batch.
     * Reads the cursor position stored in cache (last processed 'date' + 'id'), fetches the next slice of documents ordered by 'date' then 'id',
     * assigns sequential numbers starting from the stored next_number, and persists the updated cursor back to cache.
     * When the batch exhausts all remaining documents the progress is marked completed and the cache entry is cleared.
     */
    public function processNextSortingBatch(int $userId): array
    {
        $state = $this->getState($userId);
        if (! $state) {
            $total = Document::count();

            return $this->formatProgress([
                'status' => 'not_started',
                'processed' => 0,
                'total' => $total,
                'batch_size' => 50,
                'started_at' => null,
                'finished_at' => null,
            ]);
        }

        if ($state['status'] === 'completed') {
            return $this->formatProgress($state);
        }

        $batch = $this->getNextBatch($state['last_date'], (int) $state['last_id']);

        if ($batch->isEmpty()) {
            $state['status'] = 'completed';
            $state['finished_at'] = now()->toDateTimeString();
            $progress = $this->formatProgress($state);
            $this->forgetState($userId);

            return $progress;
        }

        $nextNumber = (int) $state['next_number'];

        DB::transaction(function () use ($batch, &$nextNumber, &$state) {
            foreach ($batch as $document) {
                Document::whereKey($document->id)->update(['number' => $nextNumber]);

                $nextNumber++;
                $state['last_id'] = $document->id;
                $state['last_date'] = $document->date?->format('Y-m-d');
            }
        });

        $state['processed'] = (int) $state['processed'] + $batch->count();
        $state['next_number'] = $nextNumber;

        if ($state['processed'] >= $state['total']) {
            $state['status'] = 'completed';
            $state['finished_at'] = now()->toDateTimeString();

            $progress = $this->formatProgress($state);
            $this->forgetState($userId);

            return $progress;
        }

        $this->putState($userId, $state);

        return $this->formatProgress($state);
    }

    private function getNextBatch(?string $lastDate, int $lastId)
    {
        $query = Document::query()->select(['id', 'date'])->orderBy('date')->orderBy('id')->limit(50);

        if ($lastDate !== null) {
            $query->where(function ($q) use ($lastDate, $lastId) {
                $q->where('date', '>', $lastDate)
                    ->orWhere(function ($nested) use ($lastDate, $lastId) {
                        $nested->where('date', $lastDate)->where('id', '>', $lastId);
                    });
            });
        }

        return $query->get();
    }

    private function getState(int $userId): ?array
    {
        return Cache::get($this->cacheKey($userId));
    }

    /**
     * Persist the sorting progress state for 5 minutes.
     * TTL acts as an automatic cleanup, if user closes browser mid-process the stale state will expire on its own without blocking a future run.
     */
    private function putState(int $userId, array $state): void
    {
        Cache::put($this->cacheKey($userId), $state, now()->addSeconds(300));
    }

    private function forgetState(int $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    private function cacheKey(int $userId): string
    {
        return "document-sort-progress:{$userId}";
    }

    private function formatProgress(array $state): array
    {
        $processed = (int) ($state['processed'] ?? 0);
        $total = (int) ($state['total'] ?? 0);

        return [
            'status' => $state['status'] ?? 'not_started',
            'processed' => $processed,
            'total' => $total,
            'processed_label' => formatNumber($processed),
            'total_label' => formatNumber($total),
            'percent' => $total > 0 ? (int) floor(($processed / $total) * 100) : 0,
            'batch_size' => (int) ($state['batch_size'] ?? 50),
            'started_at' => $state['started_at'] ?? null,
            'finished_at' => $state['finished_at'] ?? null,
        ];
    }
}
