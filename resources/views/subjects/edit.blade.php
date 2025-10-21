<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Subject') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('subjects.update', $subject->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body" x-data="{
                selectedName: '{{ $parentSubject->name }}',
                selectedCode: '{{ $parentSubject->code }}',
                selectedId: {{ $parentSubject->id }},
            }">
                <div class="card-title">{{ __('Edit subject') }}</div>

                <div role="alert" class="alert alert-warning mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ __('Parent Subject Change Warning') }}</span>
                </div>

                <x-show-message-bags />

                @include('subjects.form')
                <div class="mb-6">
                    <button class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
