<x-app-layout :title="__('Edit Customer Group')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('customer-groups.update', $customerGroup) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit Customer Group') }}</div>
                <x-show-message-bags />

                @include('customerGroups.form')
                <div class="card-actions">
                    <button class="btn btn-success"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>

    </div>
</x-app-layout>
