@php
    $parent_id = old('parent_id', $subject->parent_id ?? '');
    $type = old('type', $subject->type ?? '');
@endphp

<div class="mb-6">
    <label for="code" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Code</label>
    <input type="text" id="code" name="code"
        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
        value="{{ old('code', $subject->code ?? '') }}" required>
</div>
<div class="mb-6">
    <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Name</label>
    <input type="text" id="name" name="name"
        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
        value="{{ old('name', $subject->name ?? '') }}" required>
</div>
<div class="mb-6">
    <label for="parent_id" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Parent</label>
    <select id="parent_id" name="parent_id"
        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
        <option value="">None</option>
        @foreach ($parentSubjects as $parentSubject)
            <option value="{{ $parentSubject->id }}" {{ ( $parent_id == $parentSubject->id) ? 'selected' : '' }}>
                {{ $parentSubject->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-6">
    <label for="type" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Type</label>
    <select id="type" name="type"
        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
        <option value="">Select type</option>
        <option value="debtor" {{ ($type === 'debtor') ? 'selected' : '' }}>Debtor</option>
        <option value="creditor" {{ ($type === 'creditor') ? 'selected' : '' }}>Creditor</option>
        <option value="both" {{ ($type === 'both') ? 'selected' : '' }}>Both</option>
    </select>
</div>