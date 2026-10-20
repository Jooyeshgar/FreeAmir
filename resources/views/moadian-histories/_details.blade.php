@php
    $response = is_array($history->data) ? $history->data : [];
    $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
    $responseStatus = strtoupper((string) ($response['status'] ?? 'UNKNOWN'));
    $messages = [
        'error' => $responseData['error'] ?? [],
        'warning' => $responseData['warning'] ?? [],
    ];
@endphp

<button type="button" class="btn btn-xs btn-ghost" title="{{ __('Details') }}" aria-label="{{ __('Details') }}"
    onclick="document.getElementById('moadian-history-details-{{ $history->id }}').showModal()">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12s3.5-7 9.75-7 9.75 7 9.75 7-3.5 7-9.75 7-9.75-7-9.75-7Z" />
        <circle cx="12" cy="12" r="3" stroke-width="2" />
    </svg>
</button>

<dialog id="moadian-history-details-{{ $history->id }}" class="modal">
    <div class="modal-box relative max-h-[90vh] w-11/12 max-w-3xl overflow-y-auto">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold">{{ __('Details') }}</h3>
            <form method="dialog">
                <button type="submit" class="btn btn-sm btn-ghost" aria-label="{{ __('Close') }}">{{ __('Close') }}</button>
            </form>
        </div>

        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-base-content/60">{{ __('Status') }}</dt>
                <dd>{{ __($responseStatus) }}</dd>
            </div>
            <div>
                <dt class="font-medium text-base-content/60">{{ __('Reference Number') }}</dt>
                <dd class="break-all">{{ $response['referenceNumber'] ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-base-content/60">{{ __('UID') }}</dt>
                <dd class="break-all">{{ $response['uid'] ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-base-content/60">{{ __('Packet Type') }}</dt>
                <dd class="break-all">{{ $response['packetType'] ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-base-content/60">{{ __('Fiscal ID') }}</dt>
                <dd class="break-all">{{ $response['fiscalId'] ?? '-' }}</dd>
            </div>
            @if(array_key_exists('success', $responseData))
                <div>
                    <dt class="font-medium text-base-content/60">{{ __('Success') }}</dt>
                    <dd>{{ $responseData['success'] ? __('Yes') : __('No') }}</dd>
                </div>
            @endif
        </dl>

        @foreach($messages as $type => $items)
            @if(!empty($items))
                <section class="mt-4">
                    <h4 class="mb-2 font-semibold {{ $type === 'error' ? 'text-error' : 'text-warning' }}">{{ __($type === 'error' ? 'Errors' : 'Warnings') }}</h4>
                    <ul class="space-y-2">
                        @foreach(is_array($items) ? $items : [$items] as $item)
                            @php $item = is_array($item) ? $item : ['message' => $item]; @endphp
                            <li class="rounded-box bg-base-200 p-3 text-sm">
                                <div class="flex flex-wrap gap-x-4 gap-y-1">
                                    @if(isset($item['code']))
                                        <span><span class="font-medium">{{ __('Code') }}:</span> {{ $item['code'] }}</span>
                                    @endif
                                    @if(isset($item['message']))
                                        <span class="min-w-0 break-words">{{ $item['message'] }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach

        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-medium link">{{ __('Response Data') }}</summary>
            <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-box bg-base-200 p-3 text-xs text-left" dir="ltr">{{ json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="{{ __('Close') }}"></button></form>
</dialog>
