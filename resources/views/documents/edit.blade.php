<x-app-layout :title="__('Edit accounting document')">
    <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
            {{ __('Edit accounting document') }}
        </span>
    </div>
    <div>

        <form action="{{ route('documents.update', array_merge(['document' => $document->id], request()->query())) }}" method="POST">
            <x-show-message-bags />
            @csrf
            @method('PUT')

            @include('documents.form')

        </form>
    </div>
</x-app-layout>
