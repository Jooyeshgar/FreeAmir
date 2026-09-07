<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-input name="name" title="{{ __('Warehouse name') }}" :value="old('name', $warehouse->name)" />
    <x-input name="code" title="{{ __('Warehouse code') }}" :value="old('code', $warehouse->code)" />
    <div class="sm:col-span-2">
        <x-textarea name="description" title="{{ __('Description') }}" :value="old('description', $warehouse->description)" />
    </div>
</div>
