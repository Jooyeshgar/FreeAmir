<x-app-layout :title="__('Import Products')">
    <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
        <form action="{{ route('products.import.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Import Products') }}</h2>
                <x-show-message-bags />

                <p class="text-sm text-gray-500">
                    {{ __('Upload a CSV file with one product per line. Required columns: name, group_name. If a group with the same name exists it is reused, otherwise it is created. Leave code empty to auto-assign one; an existing code updates the matching product.') }}
                </p>

                <div class="mt-3">
                    <x-file-input name="file" title="{{ __('File') }}" accept=".csv,text/csv" required />
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('products.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Import') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
