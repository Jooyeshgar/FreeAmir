<x-app-layout :title="__('Edit Payroll Element')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.payroll-elements.update', $payrollElement) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Payroll Element') }}</h2>
                <x-show-message-bags />

                @include('payroll-elements.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('salary.payroll-elements.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
