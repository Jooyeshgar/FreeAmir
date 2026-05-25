<x-app-layout :title="__('Edit Comment')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('comments.update', [$comment->customer_id, $comment]) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit comment') }}</div>
                <x-show-message-bags />

                @include('customers.comments.form')
                <div class="card-actions justify-end">
                    <a href="{{ route('comments.index', $comment->customer) }}" class="btn">{{ __('cancel') }}</a>
                    <button class="btn btn-primary"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
