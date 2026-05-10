<x-app-layout :title="__('Edit Tax Slab')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.tax-slabs.update', $taxSlab) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Yearly Tax Slab') }}</h2>
                <x-show-message-bags />

                @include('tax-slabs.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('salary.tax-slabs.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
