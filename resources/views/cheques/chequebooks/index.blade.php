<x-app-layout :title="__('Chequebooks')">
    <x-show-message-bags />

    <div class="card bg-base-100">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="card-title">{{ __('Chequebooks') }}</h1>
                    <p class="text-sm opacity-60">{{ __('Manage chequebooks for payable cheques') }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('cheques.index') }}" class="btn btn-ghost btn-sm">{{ __('Back to cheques') }}</a>
                    @can('cheques.chequebooks.create')
                        <a href="{{ route('cheques.chequebooks.create') }}" class="btn btn-primary btn-sm">{{ __('Create chequebook') }}</a>
                    @endcan
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Bank account') }}</th>
                            <th>{{ __('Serial prefix') }}</th>
                            <th>{{ __('Serial range') }}</th>
                            <th>{{ __('Next leaf') }}</th>
                            <th>{{ __('Cheques') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($chequebooks as $chequebook)
                            <tr>
                                <td>{{ $chequebook->bankAccount?->name }}</td>
                                <td>{{ $chequebook->serial_prefix ?: '—' }}</td>
                                <td>{{ localizeNumber($chequebook->first_leaf) }}–{{ localizeNumber($chequebook->last_leaf) }}</td>
                                <td>{{ localizeNumber($chequebook->next_leaf) }}</td>
                                <td>{{ localizeNumber($chequebook->cheques_count) }}</td>
                                <td class="space-x-1 rtl:space-x-reverse">
                                    @can('cheques.chequebooks.show')
                                        <a href="{{ route('cheques.chequebooks.show', $chequebook) }}" class="btn btn-ghost btn-xs">{{ __('View') }}</a>
                                    @endcan
                                    @can('cheques.chequebooks.edit')
                                        <a href="{{ route('cheques.chequebooks.edit', $chequebook) }}" class="btn btn-outline btn-xs">{{ __('Edit') }}</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-10 text-center opacity-60">{{ __('No chequebooks found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $chequebooks->links() }}
        </div>
    </div>
</x-app-layout>
