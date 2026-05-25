<x-app-layout :title="__('Edit Payroll Item')">
    <div class="card bg-base-100 shadow-xl mb-6 max-w-xl mx-auto">
        <div class="card-header bg-gradient-to-r from-green-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <h2 class="card-title text-lg">
                {{ $payrollItem->description ?? ($payrollItem->element?->title ?? __('Payroll Item')) }}
            </h2>
        </div>

        <div class="card-body">
            <form action="{{ route('salary.payroll-items.update', $payrollItem) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="fieldset mb-4">
                    <label class="label">
                        <span>{{ __('Amount') }}</span>
                    </label>
                    <x-input type="number" step="0.01" name="calculated_amount" value="{{ old('calculated_amount', $payrollItem->calculated_amount) }}" required />
                </div>

                <div class="fieldset mb-4">
                    <label class="label">
                        <span>{{ __('Unit Count') }}</span>
                    </label>
                    <x-input type="number" step="0.01" name="unit_count" value="{{ old('unit_count', $payrollItem->unit_count) }}" />
                </div>

                <div class="fieldset mb-4">
                    <label class="label">
                        <span>{{ __('Unit Rate') }}</span>
                    </label>
                    <x-input type="number" step="0.01" name="unit_rate" value="{{ old('unit_rate', $payrollItem->unit_rate) }}" />
                </div>

                <div class="fieldset mb-6">
                    <label class="label">
                        <span>{{ __('Description') }}</span>
                    </label>
                    <x-input type="text" name="description" value="{{ old('description', $payrollItem->description) }}" />
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('salary.payrolls.show', $payrollItem->payroll_id) }}" class="btn btn-ghost btn-sm">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
