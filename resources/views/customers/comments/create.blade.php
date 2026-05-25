<x-app-layout :title="__('Create Comment')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('comments.store', $customer->id) }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add comment') }}</h2>
                <x-show-message-bags />

                @include('customers.comments.form')
                <div class="card-actions justify-end">
                    <a href="{{ route('comments.index', $customer) }}" class="btn">{{ __('cancel') }}</a>
                    <button type="submit" class="btn btn-primary"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
