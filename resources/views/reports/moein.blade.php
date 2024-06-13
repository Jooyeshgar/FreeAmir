<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Report moein') }}
        </h2>
    </x-slot>
    <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
         {{__('Report moein')}}
        </span>
    </div>
    @include('reports.form' , ['type'=>'moein'])
</x-app-layout>
