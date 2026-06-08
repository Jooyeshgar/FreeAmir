<x-app-layout :title="__('Create User')">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="post" action="{{ route('users.store') }}">
                @csrf
                <x-show-message-bags />

                @include('users.form', ['user' => null])

                <div class="flex justify-end mt-4">
                    <a href="{{ route('users.index') }}" class="btn btn-ghost ml-2">{{ __('Back') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
