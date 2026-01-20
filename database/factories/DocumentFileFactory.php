<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFileFactory extends Factory
{
    protected $model = DocumentFile::class;

    public function definition()
    {
        return [
            'user_id' => User::withoutGlobalScopes()->inRandomOrder()->first()->id,
            'name' => $this->faker->name,
            'title' => $this->faker->title,
            'file_address' => '/document-'.$this->faker->randomDigit.'/file-'.$this->faker->randomNumber,
        ];
    }

    public function withDocument(Document $document): static
    {
        return $this->state([
            'document_id' => $document->id,
        ]);
    }
}
