<x-app-layout :title="$personnelRequest->request_type?->label()">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('employee-portal.personnel-requests.update', $personnelRequest) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="card-body">
                <h2 class="card-title">{{ $personnelRequest->request_type?->label() }}</h2>
                <x-show-message-bags />

                @include('employee-portal.personnel-requests.form')

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('employee-portal.personnel-requests.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
