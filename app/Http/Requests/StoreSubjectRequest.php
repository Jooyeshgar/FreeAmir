<?php

namespace App\Http\Requests;

use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:60',
            'parent_id' => 'nullable|exists:subjects,id',
            'subject_code' => 'nullable|string|max:3',
            'type' => ['required', Rule::in(['debtor', 'creditor', 'both'])],
            'is_permanent' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $parentId = $this->input('parent_id');
            if ($parentId === '' || $parentId === 0 || $parentId === '0') {
                $parentId = null;
            }

            $parentSubject = $parentId ? Subject::find($parentId) : null;
            $allowedTypes = app(SubjectService::class)->getAllowedTypesForSubject($parentSubject);

            if (! in_array($this->input('type'), $allowedTypes, true)) {
                $validator->errors()->add('type', __('The selected type is not allowed according to the chosen parent subject.'));
            }
        });
    }

    public function getValidatedData(): array
    {
        $validatedData = $this->validated();

        $validatedData['code'] = $validatedData['subject_code'] ?? null;
        unset($validatedData['subject_code']);
        $validatedData['is_permanent'] = $this->has('is_permanent') ? $this->boolean('is_permanent') : null;

        return $validatedData;
    }
}
