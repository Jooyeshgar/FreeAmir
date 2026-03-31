<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheque Books') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions flex justify-between gap-4">
                <a href="{{ route('cheque-books.create') }}" class="btn btn-primary">
                    {{ __('Add Cheque Book') }}
                </a>
            </div>

            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Issued At') }}</th>
                        <th>{{ __('Sayad') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Bank Account') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($chequeBooks as $chequeBook)
                        <tr>
                            <td>
                                <a href="{{ route('cheques.index', $chequeBook) }}">{{ $chequeBook->title }}</a>
                            </td>
                            <td>{{ formatDate($chequeBook->issued_at) }}</td>
                            <td>
                                <span>
                                    @if ($chequeBook->is_sayad)
                                        {{ __('Yes') }}
                                    @else
                                        {{ __('No') }}
                                    @endif
                                </span>
                            </td>
                            <td>
                                <span>
                                    {{ $chequeBook->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td>
                                @if ($chequeBook->bankAccount)
                                    <a
                                        href="{{ route('bank-accounts.show', $chequeBook->bankAccount) }}">{{ $chequeBook->bankAccount->name }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="flex gap-2">
                                <a href="{{ route('cheque-books.show', $chequeBook) }}" class="btn btn-sm btn-ghost">
                                    {{ __('Show') }}
                                </a>
                                <a href="{{ route('cheques.index', $chequeBook) }}" class="btn btn-sm">
                                    {{ __('Cheques') }}
                                </a>
                                <a href="{{ route('cheque-books.edit', $chequeBook) }}" class="btn btn-sm btn-info">
                                    {{ __('Edit') }}
                                </a>

                                <form action="{{ route('cheque-books.destroy', $chequeBook) }}" method="POST"
                                    class="inline-block m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error"
                                        onclick="return confirm('{{ __('Deleting this cheque book will also delete all related cheques. Are you sure?') }}')">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-gray-500">
                                {{ __('There are no cheque books.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($chequeBooks->hasPages())
                <div class="mt-4 flex justify-center">
                    {!! $chequeBooks->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
