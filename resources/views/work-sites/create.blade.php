<x-app-layout :title="__('Create Work Site')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.work-sites.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Work Site') }}</h2>
                <x-show-message-bags />

                @include('work-sites.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('salary.work-sites.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
