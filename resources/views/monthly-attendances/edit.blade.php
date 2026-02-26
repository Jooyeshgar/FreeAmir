<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Monthly Attendance') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('monthly-attendances.update', $monthlyAttendance) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">
                    {{ $monthlyAttendance->employee?->first_name }}
                    {{ $monthlyAttendance->employee?->last_name }}
                    &mdash;
                    {{ $monthlyAttendance->month_name }} {{ $monthlyAttendance->year }}
                </h2>
                <x-show-message-bags />

                @include('monthly-attendances.form')

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('monthly-attendances.show', $monthlyAttendance) }}" class="btn btn-ghost">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Save Changes') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
