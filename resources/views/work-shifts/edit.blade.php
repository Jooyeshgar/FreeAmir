<x-app-layout :title="__('Edit Work Shift')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('attendance.work-shifts.update', $workShift) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Work Shift') }}</h2>
                <x-show-message-bags />

                @include('work-shifts.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('attendance.work-shifts.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
