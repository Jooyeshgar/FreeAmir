@props([
    'title' => null,
    'description' => null,
    'columns' => [],
    'rows',
    'rowKey' => 'id',
    'selectable' => false,
    'searchable' => true,
    'searchPlaceholder' => null,
    'actions' => [],
    'bulkActions' => [],
    'emptyTitle' => null,
    'emptyDescription' => null,
    'currency' => null,
])

@php
    $tableRows = method_exists($rows, 'items') ? collect($rows->items()) : collect($rows);
    $rowIds = $tableRows->map(fn ($row) => (string) data_get($row, $rowKey))->values();
@endphp

<section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm"
    x-data="{ selected: [], rowIds: @js($rowIds), toggleAll(checked) { this.selected = checked ? [...this.rowIds] : [] } }">
    <header class="flex flex-col gap-4 border-b border-base-300 p-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            @if ($title)<h2 class="text-lg font-bold">{{ $title }}</h2>@endif
            @if ($description)<p class="mt-1 text-sm text-base-content/55">{{ $description }}</p>@endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($selectable && count($bulkActions))
                <div class="dropdown" x-show="selected.length" x-cloak>
                    <button type="button" tabindex="0" class="btn btn-sm btn-primary">
                        <span x-text="selected.length"></span> {{ __('selected') }}
                    </button>
                    <ul tabindex="0" class="menu dropdown-content z-20 mt-2 w-52 rounded-box border border-base-300 bg-base-100 p-2 shadow-xl">
                        @foreach ($bulkActions as $bulkAction)
                            <li>
                                <form method="POST" action="{{ route($bulkAction['route']) }}">
                                    @csrf
                                    @if (($bulkAction['method'] ?? 'POST') !== 'POST') @method($bulkAction['method']) @endif
                                    <template x-for="id in selected" :key="id"><input type="hidden" name="selected[]" :value="id"></template>
                                    <button class="w-full" type="submit">{{ __($bulkAction['label']) }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @isset($filters)
                {{ $filters }}
            @endisset

            @if ($searchable)
                <form method="GET" action="{{ url()->current() }}" class="join">
                    @foreach (request()->except(['search', 'page']) as $key => $value)
                        @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <label class="input input-sm join-item flex items-center gap-2">
                        <svg class="size-4 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                        <input name="search" value="{{ request('search') }}" class="w-40 sm:w-56" placeholder="{{ $searchPlaceholder ?? __('Search records…') }}">
                    </label>
                    <button class="btn btn-sm btn-neutral join-item" type="submit">{{ __('Search') }}</button>
                </form>
            @endif

            @isset($actionsSlot)
                {{ $actionsSlot }}
            @endisset
        </div>
    </header>

    <div class="overflow-x-auto">
        <table class="table table-zebra">
            <thead class="bg-base-200/70 text-xs uppercase tracking-wide text-base-content/55">
                <tr>
                    @if ($selectable)
                        <th class="w-12">
                            <input type="checkbox" class="checkbox checkbox-sm" aria-label="{{ __('Select all rows') }}"
                                :checked="rowIds.length > 0 && selected.length === rowIds.length"
                                :indeterminate="selected.length > 0 && selected.length < rowIds.length"
                                @change="toggleAll($event.target.checked)">
                        </th>
                    @endif
                    @foreach ($columns as $column)
                        <th @class([$column['class'] ?? '', 'text-end' => ($column['format'] ?? null) === 'currency'])>
                            @if ($column['sortable'] ?? false)
                                @php
                                    $key = $column['sortKey'] ?? $column['key'];
                                    $active = request('sort') === $key;
                                    $direction = $active && request('direction', 'asc') === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <a class="inline-flex items-center gap-1 hover:text-primary" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'direction' => $direction, 'page' => null]) }}">
                                    {{ __($column['label']) }}
                                    <span class="text-[10px] opacity-60">{{ $active ? (request('direction', 'asc') === 'asc' ? '▲' : '▼') : '↕' }}</span>
                                </a>
                            @else
                                {{ __($column['label']) }}
                            @endif
                        </th>
                    @endforeach
                    @if (count($actions))<th class="w-24 text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($tableRows as $row)
                    <tr class="hover">
                        @if ($selectable)
                            <td><input type="checkbox" class="checkbox checkbox-sm" value="{{ data_get($row, $rowKey) }}" x-model="selected"></td>
                        @endif
                        @foreach ($columns as $column)
                            @php
                                $value = data_get($row, $column['key']);
                                $format = $column['format'] ?? 'text';
                                $enumKey = $value instanceof \BackedEnum ? (method_exists($value, 'valueName') ? $value->valueName() : (string) $value->value) : strtolower((string) $value);
                                $displayValue = $value instanceof \BackedEnum ? (method_exists($value, 'label') ? $value->label() : $value->name) : $value;
                                $badgeClass = match ($enumKey) {
                                    'paid', 'approved', 'success', 'post', 'put' => 'badge-success',
                                    'pending', 'ready_to_approve', 'partially_paid', 'patch' => 'badge-warning',
                                    'overdue', 'rejected', 'failed', 'delete' => 'badge-error',
                                    'draft', 'get' => 'badge-ghost',
                                    default => 'badge-info',
                                };
                            @endphp
                            <td @class([$column['class'] ?? '', 'text-end font-mono tabular-nums' => $format === 'currency'])>
                                @switch($format)
                                    @case('currency')
                                        <span class="font-bold">{{ formatNumber((float) $value) }}</span>
                                        @if ($column['showCurrency'] ?? true)<span class="ms-1 text-xs opacity-50">{{ $column['currency'] ?? $currency ?? config('amir.currency') ?? __('Rial') }}</span>@endif
                                    @break
                                    @case('date')
                                        <time datetime="{{ $value }}">{{ formatDate($value) ?: '—' }}</time>
                                    @break
                                    @case('datetime')
                                        <time datetime="{{ $value }}" class="whitespace-nowrap">{{ formatDateTime($value) ?: '—' }}</time>
                                    @break
                                    @case('status')
                                    @case('badge')
                                        <span class="badge badge-sm {{ $badgeClass }}">{{ $displayValue ?: '—' }}</span>
                                    @break
                                    @case('boolean')
                                        <span class="badge badge-sm {{ $value ? 'badge-success' : 'badge-ghost' }}">{{ $value ? __('Yes') : __('No') }}</span>
                                    @break
                                    @default
                                        {{ filled($displayValue) ? $displayValue : '—' }}
                                @endswitch
                            </td>
                        @endforeach
                        @if (count($actions))
                            <td>
                                <div class="flex justify-end gap-1">
                                    @foreach ($actions as $action)
                                        @if (! isset($action['permission']) || auth()->user()->can($action['permission']))
                                            @php $actionUrl = route($action['route'], [$row]); @endphp
                                            @if (($action['method'] ?? 'GET') === 'GET')
                                                <a href="{{ $actionUrl }}" class="btn btn-ghost btn-xs">{{ __($action['label']) }}</a>
                                            @else
                                                <form method="POST" action="{{ $actionUrl }}">
                                                    @csrf @method($action['method'])
                                                    <button class="btn btn-ghost btn-xs text-error" type="submit"
                                                        @if ($action['confirm'] ?? false) onclick="return confirm('{{ __('Are you sure?') }}')" @endif>{{ __($action['label']) }}</button>
                                                </form>
                                            @endif
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + ($selectable ? 1 : 0) + (count($actions) ? 1 : 0) }}" class="py-14 text-center">
                            <div class="mx-auto grid size-12 place-items-center rounded-full bg-base-200 text-base-content/40">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 6h16v14H4zM8 3h8v3M8 11h8m-8 4h5" /></svg>
                            </div>
                            <p class="mt-3 font-bold">{{ $emptyTitle ?? __('No records found') }}</p>
                            <p class="mt-1 text-sm text-base-content/50">{{ $emptyDescription ?? __('Try changing the active filters.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (method_exists($rows, 'links'))
        <footer class="border-t border-base-300 px-4 py-3">{{ $rows->links() }}</footer>
    @endif
</section>
