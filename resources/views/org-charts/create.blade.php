<x-app-layout :title="__('Create Organization Chart Node')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('hr.org-charts.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Node') }}</h2>
                <x-show-message-bags />

                @include('org-charts.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('hr.org-charts.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
