<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">
            {{ __('Create Transaction') }}
        </h2>
    </x-slot>
    <div class="font-bold text-gray-600 dark:text-slate-200 py-6 text-2xl">
        <span>
            {{ __('Registration of accounting document') }}
        </span>
    </div>
    <div class="">

        <form action="{{ route('documents.store') }}" method="POST" id="documentForm">
            <x-show-message-bags />
            @csrf
            @include('documents.form')



        </form>
    </div>
</x-app-layout>
