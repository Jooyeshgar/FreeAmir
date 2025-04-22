<x-app-layout>
    <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
            {{ __('Documents Report') }}
        </span>
    </div>
    <x-show-message-bags />

    <form action="{{ route('reports.result') }}" method="get">
        <x-card>
            @include('reports.form', ['type' => 'Document'])
        </x-card>
        <div class="mt-2 flex gap-2 justify-end">
            <a href="{{ route('documents.index') }}" type="submit" class="btn btn-default rounded-md">
                {{ __('Convert to CSV') }}
            </a>
            <button type="submit" class="btn btn-default rounded-md"> {{ __('Print') }}</button>
            <button type="submit" class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
        </div>
    </form>
</x-app-layout>
