<x-app-layout :title="__('Chequebook')">
    <x-show-message-bags />

    <div class="space-y-4">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h1 class="card-title">{{ __('Chequebook') }}</h1>
                    <div class="flex gap-2">
                        <a href="{{ route('cheques.chequebooks.index') }}" class="btn btn-ghost btn-sm">{{ __('Back') }}</a>
                        @can('cheques.chequebooks.edit')
                            <a href="{{ route('cheques.chequebooks.edit', $chequebook) }}" class="btn btn-outline btn-sm">{{ __('Edit') }}</a>
                        @endcan
                        @can('cheques.chequebooks.destroy')
                            <form method="POST" action="{{ route('cheques.chequebooks.destroy', $chequebook) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-error btn-outline btn-sm" onclick="return confirm('{{ __('Delete this chequebook? Its cheques will be preserved and unlinked.') }}')">{{ __('Delete') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        __('Bank') => $chequebook->bankAccount?->bank?->name,
                        __('Bank account') => $chequebook->bankAccount?->name,
                        __('Serial prefix') => $chequebook->serial_prefix,
                        __('First leaf') => localizeNumber($chequebook->first_leaf),
                        __('Last leaf') => localizeNumber($chequebook->last_leaf),
                        __('Next leaf') => localizeNumber($chequebook->next_leaf),
                        __('Description') => $chequebook->desc,
                    ] as $label => $value)
                        <div>
                            <div class="text-xs opacity-60">{{ $label }}</div>
                            <div class="mt-1 font-medium">{{ $value ?: '—' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('Cheques') }}</h2>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Cheque number') }}</th>
                                <th>{{ __('Account side') }}</th>
                                <th>{{ __('Due date') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cheques as $cheque)
                                <tr>
                                    <td>
                                        <a class="link" href="{{ route('cheques.show', $cheque) }}">{{ localizeNumber($cheque->cheque_number) ?: '—' }}</a>
                                    </td>
                                    <td>{{ $cheque->customer?->name ?? '—' }}</td>
                                    <td>{{ formatDate($cheque->due_date) }}</td>
                                    <td>{{ formatNumber($cheque->amount) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $cheque->status->color() }} badge-sm">{{ $cheque->status->label() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center opacity-60">{{ __('No cheques are associated with this chequebook.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $cheques->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
