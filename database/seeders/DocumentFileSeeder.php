<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Database\Seeder;

class DocumentFileSeeder extends Seeder
{
    public function run(): void
    {
        $documents = Document::withoutGlobalScopes()->get();

        foreach ($documents->take(10) as $document) {
            DocumentFile::factory()->count(2)->withDocument($document)->create();
        }
    }
}
