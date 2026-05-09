<x-app-layout :title="__('Create Work Shift')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('attendance.work-shifts.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Work Shift') }}</h2>
                <x-show-message-bags />

                @include('work-shifts.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('attendance.work-shifts.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
