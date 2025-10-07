<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('General Journal Report') }}
        </h2>
    </x-slot>
    <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
            {{ __('General Journal Report') }}
        </span>
    </div>

    <x-show-message-bags />

    <form action="{{ route('reports.result') }}" method="get">
        <x-card>
            <x-ledger-select-box :subjects="$subjects" />
            @include('reports.form', ['type' => 'Ledger'])
        </x-card>
        <div class="mt-2 flex gap-2 justify-end">
            <button type="submit" name="action" value="export_csv" class="btn btn-default rounded-md">
                {{ __('Convert to CSV') }}
            </button>
            <button type="submit" name="action" value="print" class="btn btn-default rounded-md"> {{ __('Print') }}</button>
            <button type="submit" name="action" value="preview" class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
        </div>
    </form>
</x-app-layout>

@pushOnce('scripts')
    <script type="module">
        jalaliDatepicker.startWatch({});
    </script>
@endPushOnce
