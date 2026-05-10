<x-app-layout :title="__('Create Tax Slab')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.tax-slabs.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Yearly Tax Slab') }}</h2>
                <x-show-message-bags />

                @include('tax-slabs.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('salary.tax-slabs.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
