<x-app-layout :title="__('Edit Public Holiday')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.public-holidays.update', $publicHoliday) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Public Holiday') }}</h2>
                <x-show-message-bags />

                @include('public-holidays.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('salary.public-holidays.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
