<x-app-layout :title="__('Edit Employee')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('hr.employees.update', $employee) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Employee') }}</h2>
                <x-show-message-bags />

                @include('employees.form')

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('hr.employees.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
