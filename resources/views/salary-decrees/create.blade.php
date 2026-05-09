<x-app-layout :title="__('Create Salary Decree')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.salary-decrees.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Salary Decree') }}</h2>
                <x-show-message-bags />

                @include('salary-decrees.form')

                <div class="card-actions justify-end mt-6">
                    <a href="{{ route('salary.salary-decrees.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
