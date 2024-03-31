<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customer Groups') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200 overflow-auto">
                    <a href="{{ route('customer-groups.create') }}" class="btn btn-primary">Create customer group</a>

                    <table class="table w-full mt-4 overflow-auto">
                        <thead>
                        <tr>
                            @foreach($cols as $col)
                                <th class="px-4 py-2">{{$col}}</th>
                            @endforeach
                            <th class="px-4 py-2">Action</th>
                        </tr>
                        </thead>
                        <tbody>

                        @foreach ($customerGroups as $customerGroup)
                            <tr>
                                @foreach($cols as $col)
                                    <td class="px-4 py-2">{{ $customerGroup[$col] }}</td>
                                @endforeach
                                <td class="px-4 py-2">
                                    <a href="{{ route('customer-groups.edit', $customerGroup) }}"
                                       class="btn btn-sm btn-info">Edit</a>
                                    <form action="{{ route('customer-groups.destroy', $customerGroup) }}" method="POST"
                                          class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-error">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    {!! $customerGroups->links() !!}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
