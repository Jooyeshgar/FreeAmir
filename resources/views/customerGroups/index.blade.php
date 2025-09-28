<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customer Groups') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('customer-groups.create') }}" class="btn btn-primary">{{ __('Create Customer Group') }}</a>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($customerGroups as $customerGroup)
                        <tr>
                            <td class="px-4 py-2">{{ $customerGroup->subject?->formattedCode() }}</td>
                            <td class="px-4 py-2">{{ $customerGroup->name }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('customer-groups.edit', $customerGroup) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('customer-groups.destroy', $customerGroup) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {!! $customerGroups->links() !!}
        </div>
    </div>
</x-app-layout>
