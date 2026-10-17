<x-platform-layout :title="__('Edit Fiscal Year')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title">{{ __('Edit Fiscal Year') }} — {{ $fiscalYear->company->name }}</h1>
            <form action="{{ route('fiscal-years.update', $fiscalYear) }}" method="POST">
                @csrf
                @method('PATCH')
                <fieldset class="my-3 border p-5">
                    <legend>{{ __('Fiscal year') }}</legend>
                    <x-input name="year" id="year" title="{{ __('Fiscal year') }}" :value="old('year', $fiscalYear->year)" required />
                </fieldset>
                <div class="card-actions">
                    <button type="submit" class="btn btn-primary">{{ __('Edit') }}</button>
                    <a href="{{ route('fiscal-years.show', $fiscalYear) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-platform-layout>
