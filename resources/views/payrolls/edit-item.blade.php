<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Payroll Item') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

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

                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">{{ __('Amount') }}</span>
                    </label>
                    <input type="number" step="0.01" name="calculated_amount" value="{{ old('calculated_amount', $payrollItem->calculated_amount) }}"
                        class="input input-bordered @error('calculated_amount') input-error @enderror" required />
                    @error('calculated_amount')
                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">{{ __('Unit Count') }}</span>
                    </label>
                    <input type="number" step="0.01" name="unit_count" value="{{ old('unit_count', $payrollItem->unit_count) }}"
                        class="input input-bordered @error('unit_count') input-error @enderror" />
                    @error('unit_count')
                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">{{ __('Unit Rate') }}</span>
                    </label>
                    <input type="number" step="0.01" name="unit_rate" value="{{ old('unit_rate', $payrollItem->unit_rate) }}"
                        class="input input-bordered @error('unit_rate') input-error @enderror" />
                    @error('unit_rate')
                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-control mb-6">
                    <label class="label">
                        <span class="label-text">{{ __('Description') }}</span>
                    </label>
                    <input type="text" name="description" value="{{ old('description', $payrollItem->description) }}"
                        class="input input-bordered @error('description') input-error @enderror" />
                    @error('description')
                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('salary.payrolls.show', $payrollItem->payroll_id) }}" class="btn btn-ghost btn-sm">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
