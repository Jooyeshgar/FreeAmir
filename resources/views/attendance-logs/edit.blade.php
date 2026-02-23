<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Attendance Log') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('attendance-logs.update', $attendanceLog) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Attendance Log') }}</h2>
                <x-show-message-bags />

                @include('attendance-logs.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('attendance-logs.index') }}" class="btn btn-ghost">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
