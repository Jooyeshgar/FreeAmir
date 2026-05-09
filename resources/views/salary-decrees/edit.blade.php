<x-app-layout :title="__('Edit Salary Decree')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.salary-decrees.update', $salaryDecree) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Salary Decree') }}</h2>
                <x-show-message-bags />

                @include('salary-decrees.form')

                <div class="card-actions justify-end mt-6">
                    <a href="{{ route('salary.salary-decrees.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
