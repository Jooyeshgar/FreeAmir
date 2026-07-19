<x-app-layout :title="__('Edit Work Site')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('salary.work-sites.update', array_merge(['work_site' => $workSite->id], request()->query())) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Work Site') }}</h2>
                <x-show-message-bags />

                @include('work-sites.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('salary.work-sites.index', request()->query()) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
