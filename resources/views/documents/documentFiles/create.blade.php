<x-app-layout :title="__('Create Document File')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('documents.files.store', $document->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Document File') }}</h2>
                <x-show-message-bags />

                @include('documents.documentFiles.form')
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-pr"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
