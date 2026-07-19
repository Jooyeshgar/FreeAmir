<x-app-layout :title="__('Edit Organization Unit')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('hr.organization-units.update', array_merge(['organization_unit' => $organizationUnit->id], request()->query())) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Organization Unit') }}</h2>
                <x-show-message-bags />

                @include('organization-units.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('hr.organization-units.index', request()->query()) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
