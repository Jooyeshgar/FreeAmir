<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheque Histories') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions flex justify-between gap-4">
                <a href="{{ route('cheque-histories.create') }}" class="btn btn-primary">
                    {{ __('Add Cheque History') }}
                </a>
            </div>

            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th>{{ __('Cheque') }}</th>
                        <th>{{ __('Action Type') }}</th>
                        <th>{{ __('From Status') }}</th>
                        <th>{{ __('To Status') }}</th>
                        <th>{{ __('Action At') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($histories as $history)
                        <tr>
                            <td>{{ $history->cheque->serial ?? '-' }}</td>
                            <td>{{ $history->action_type }}</td>
                            <td>{{ $history->from_status ?? '-' }}</td>
                            <td>{{ $history->to_status ?? '-' }}</td>
                            <td>{{ $history->action_at ?? '-' }}</td>
                            <td class="flex gap-2">
                                <a href="{{ route('cheque-histories.show', $history) }}"
                                    class="btn btn-sm btn-info">{{ __('Show') }}</a>
                                <a href="{{ route('cheque-histories.edit', $history) }}"
                                    class="btn btn-sm btn-warning">{{ __('Edit') }}</a>

                                <form action="{{ route('cheque-histories.destroy', $history) }}" method="POST"
                                    class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-gray-500">
                                {{ __('There are no cheque histories.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($histories->hasPages())
                <div class="mt-4 flex justify-center">
                    {!! $histories->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
