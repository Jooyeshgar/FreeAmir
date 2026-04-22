<x-app-layout :title="$title">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('employee-portal.personnel-requests.store') }}" method="POST">
            @csrf
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="card-body">
                <h2 class="card-title">{{ $title }}</h2>
                <x-show-message-bags />

                @include('employee-portal.personnel-requests.form')

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('employee-portal.personnel-requests.index', ['tab' => $tab]) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Submit Request') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
