<x-app-layout :title="__('Create Employee')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('hr.employees.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Employee') }}</h2>
                <x-show-message-bags />

                @include('employees.form')

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('hr.employees.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
