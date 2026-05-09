<x-app-layout :title="__('Edit Work Site Contract')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.work-site-contracts.update', $workSiteContract) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Work Site Contract') }}</h2>
                <x-show-message-bags />

                @include('work-site-contracts.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('salary.work-site-contracts.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
