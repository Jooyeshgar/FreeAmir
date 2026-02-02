<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Document File') }}
        </h2>
    </x-slot>
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('documents.files.update', [$document->id, $documentFile]) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Document File') }}</h2>
                <x-show-message-bags />

                @include('documents.documentFiles.form')
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
