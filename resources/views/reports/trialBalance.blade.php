<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Trial Balance') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions ">
                @if($currentParent)
                    @php
                        $upUrl = route('reports.trial-balance');
                        if ($currentParent->parent_id) {
                            $upUrl .= '?parent_id=' . $currentParent->parent_id;
                        }
                    @endphp

                    <span class="ml-2 text-lg leading-[3rem] font-semibold grow">{{ $currentParent->name }}</span>
                    <a href="{{ $upUrl }}" class="btn btn-outline">
                        {{ __('Back') }}
                    </a>
                @endif
            </div>
            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Parent') }}</th>
                        <th class="px-4 py-2">{{ __('Type') }}</th>
                        <th class="px-4 py-2">{{ __('Debtor') }}</th>
                        <th class="px-4 py-2">{{ __('Creditor') }}</th>
                        <th class="px-4 py-2">{{ __('Debtor Remaining') }}</th>
                        <th class="px-4 py-2">{{ __('Creditor Remaining') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $subject)
                        <tr>
                            <td>
                                <a href="{{ route('transactions.index', ['subject_id' => $subject->id]) }}" class="text-primary hover:underline" title="{{ __('View transactions for this subject') }}">
                                    {{ $subject->formattedCode() }}
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('reports.trial-balance', ['parent_id' => $subject->id]) }}" class="text-primary"> {{ $subject->name }}</a>
                                @if ($subject->subjectable)
                                    <div class="badge badge-primary" title="{{ __('Related to') }}: {{ class_basename($subject->subjectable::class) }}">
                                        <a href="{{ route(model_route($subject->subjectable, 'show'), $subject->subjectable) }}">
                                        {{ __(class_basename($subject->subjectable::class)) }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $subject->parent ? $subject->parent->name : __('Main') }}</td>
                            <td class="px-4 py-2">{{ $subject->type ? __(ucfirst($subject->type)) : '-' }}</td>
                            <td class="px-4 py-2">{{ formatNumber(abs($subject->debit)) }}</td>
                            <td class="px-4 py-2">{{ formatNumber($subject->credit) }}</td>
                            <td class="px-4 py-2">{{ $subject->balance < 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                            <td class="px-4 py-2">{{ $subject->balance > 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                            <td class="px-4 py-2">
                                <form action="{{ route('reports.result') }}" method="get">
                                    <input type="hidden" name="report_for" value="subLedger">
                                    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                                    <div class="mt-2 flex gap-2">
                                        <button type="submit" name="action" value="print" class="btn btn-default rounded-md"> {{ __('Print') }}</button>
                                    </div>
                                </form>

                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
