<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheques') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions flex justify-between gap-4">
                <a href="{{ route('cheques.create') }}" class="btn btn-primary">
                    {{ __('Add Cheque') }}
                </a>
            </div>

            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th>{{ __('Serial') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Cheque Book') }}</th>
                        <th>{{ __('Due Date') }}</th>
                        <th>{{ __('Received') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cheques as $cheque)
                        <tr>
                            <td>{{ $cheque->serial ?? '-' }}</td>
                            <td>{{ $cheque->amount }}</td>
                            <td>{{ $cheque->customer->name ?? '-' }}</td>
                            <td>{{ $cheque->chequeBook->title ?? '-' }}</td>
                            <td>{{ $cheque->due_date ?? '-' }}</td>
                            <td>
                                @if ($cheque->is_received)
                                    <span class="badge badge-success">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-ghost">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                <a href="{{ route('cheques.show', $cheque) }}"
                                    class="btn btn-sm btn-info">{{ __('Show') }}</a>
                                <a href="{{ route('cheques.edit', $cheque) }}"
                                    class="btn btn-sm btn-warning">{{ __('Edit') }}</a>

                                <form action="{{ route('cheques.destroy', $cheque) }}" method="POST"
                                    class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error"
                                        onclick="return confirm('{{ __('Deleting this cheque will also delete all related histories. Are you sure?') }}')">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-gray-500">
                                {{ __('There are no cheques.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($cheques->hasPages())
                <div class="mt-4 flex justify-center">
                    {!! $cheques->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
