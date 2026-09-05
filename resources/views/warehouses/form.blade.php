<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-input name="name" title="{{ __('Warehouse name') }}" :value="old('name', $warehouse->name)" />
    <x-input name="code" title="{{ __('Warehouse code') }}" :value="old('code', $warehouse->code)" />
    <div class="md:col-span-2">
        <x-textarea name="description" title="{{ __('Description') }}" :value="old('description', $warehouse->description)" />
    </div>
</div>
