<?php

namespace App\Services;

use App\Models\DocumentFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentFileService
{
    protected string $basePath = 'documents';

    protected string $disk = 'public';

    public function create(array $data): void
    {
        DB::transaction(function () use ($data) {
            if (isset($data['file'])) {
                $fileInfo = $this->storeFile($data['document_id'], $data['file']);
                $data = array_merge($data, $fileInfo);
                unset($data['file']);
            }

            DocumentFile::create($data);
        });
    }

    public function update(DocumentFile $documentFile, array $data): void
    {
        DB::transaction(function () use ($documentFile, $data) {
            if (isset($data['file'])) {
                $this->deleteFile($documentFile->path);

                $fileInfo = $this->storeFile($documentFile->document_id, $data['file']);
                $data = array_merge($data, $fileInfo);
                unset($data['file']);
            }

            $documentFile->update($data);
        });
    }

    public function delete(DocumentFile $documentFile): void
    {
        DB::transaction(function () use ($documentFile) {
            $this->deleteFile($documentFile->path);

            $documentFile->delete();
        });
    }

    public function resolvePath(DocumentFile $documentFile): string
    {
        $path = $documentFile->path;

        return Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path;
    }

    private function storeFile(int $documentId, UploadedFile $file): array
    {
        $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs($this->basePath.'/'.$documentId, $fileName, $this->disk);

        return [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
        ];
    }

    private function deleteFile(?string $path): void
    {
        $disk = Storage::disk($this->disk);

        if (! $path) {
            return;
        }

        $normalizedPath = Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path;

        if ($normalizedPath && $disk->exists($normalizedPath)) {
            $disk->delete($normalizedPath);
        }
    }
}
