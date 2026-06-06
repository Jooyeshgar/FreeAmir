<x-app-layout :title="__('Edit User')">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="post" action="{{ route('users.update', $user) }}">
                @csrf
                @method('PUT')
                <x-show-message-bags />

                @include('users.form', ['user' => $user])

                <div class="flex justify-end mt-4">
                    <a href="{{ route('users.index') }}" class="btn btn-ghost ml-2">{{ __('Back') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Edit') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
