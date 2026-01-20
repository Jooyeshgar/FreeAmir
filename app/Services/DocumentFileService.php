<?php

namespace App\Services;

use App\Models\DocumentFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentFileService
{
    protected string $basePath = 'storage/documents';

    public function __construct() {}

    public function create(array $data): DocumentFile
    {
        return DB::transaction(function () use ($data) {

            if (isset($data['file'])) {
                $file = $data['file'];

                $directory = $this->basePath.'/'.$data['document_id'];

                if (! File::exists(public_path($directory))) {
                    File::makeDirectory(public_path($directory), 0755, true);
                }

                $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();

                $file->move(public_path($directory), $fileName);

                $data['name'] = $file->getClientOriginalName();
                $data['path'] = $directory.'/'.$fileName;

                unset($data['file']);
            }

            return DocumentFile::create($data);
        });
    }

    public function update(DocumentFile $documentFile, array $data): DocumentFile
    {
        return DB::transaction(function () use ($documentFile, $data) {

            if (isset($data['file'])) {
                if ($documentFile->path && File::exists(public_path($documentFile->path))) {
                    File::delete(public_path($documentFile->path));
                }

                $file = $data['file'];
                $directory = $this->basePath.'/'.$documentFile->document_id;

                if (! File::exists(public_path($directory))) {
                    File::makeDirectory(public_path($directory), 0755, true);
                }

                $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
                $file->move(public_path($directory), $fileName);

                $data['name'] = $file->getClientOriginalName();
                $data['path'] = $directory.'/'.$fileName;

                unset($data['file']);
            }

            $documentFile->update($data);

            return $documentFile;
        });
    }

    public function delete(DocumentFile $documentFile): void
    {
        DB::transaction(function () use ($documentFile) {

            if ($documentFile->path && File::exists(public_path($documentFile->path))) {
                File::delete(public_path($documentFile->path));
            }

            $documentFile->delete();
        });
    }
}
