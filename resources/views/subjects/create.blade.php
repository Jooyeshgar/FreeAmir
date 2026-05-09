<x-app-layout :title="__('Create Subject')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('subjects.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Add subject') }}</div>
                <x-show-message-bags />

                @include('subjects.form')
                <div class="mb-6">
                    <button class="btn btn-pr"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
