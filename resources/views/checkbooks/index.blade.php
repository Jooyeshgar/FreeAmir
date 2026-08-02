<x-app-layout :title="__('cheques checkbooks')">
    <x-show-message-bags />
    <div class="card bg-base-100">
        <div class="card-body">
            <div class="flex justify-between">
                <h1 class="card-title">{{ __('cheques checkbooks') }}</h1>
                <div>
                    <a class="btn btn-ghost btn-sm" href="{{ route('cheques.index') }}">{{ __('cheques back') }}</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('checkbooks.create') }}">{{ __('cheques create_checkbook') }}</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('cheques fields title') }}</th>
                            <th>{{ __('cheques fields bank_account') }}</th>
                            <th>{{ __('cheques serial_range') }}</th>
                            <th>{{ __('cheques next_leaf') }}</th>
                            <th>{{ __('cheques remaining') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($checkbooks as $checkbook)
                            <tr>
                                <td>{{ $checkbook->title }} @unless ($checkbook->is_active)
                                        <span class="badge badge-ghost">{{ __('cheques inactive') }}</span>
                                    @endunless
                                </td>
                                <td>{{ $checkbook->bankAccount?->bank?->name }} — {{ $checkbook->bankAccount?->name }}</td>
                                <td>{{ localizeNumber($checkbook->serialFor($checkbook->start_leaf_number)) }} – {{ localizeNumber($checkbook->serialFor($checkbook->end_leaf_number)) }}</td>
                                <td>{{ localizeNumber($checkbook->next_leaf_number) }}</td>
                                <td>{{ localizeNumber($checkbook->remainingLeaves()) }}</td>
                                <td><a class="btn btn-ghost btn-xs" href="{{ route('checkbooks.edit', $checkbook) }}">{{ __('cheques edit') }}</a>
                                    <form class="inline" method="POST" action="{{ route('checkbooks.destroy', $checkbook) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-error btn-xs" onclick="return confirm('{{ __('cheques confirm_action') }}')">{{ __('cheques delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-gray-500">{{ __('chequebook empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $checkbooks->links() }}
        </div>
    </div>
</x-app-layout>
