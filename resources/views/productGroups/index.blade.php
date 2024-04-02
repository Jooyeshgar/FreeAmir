<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products Group') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200 overflow-auto">
                    <a href="{{ route('product-groups.create') }}" class="btn btn-primary">Create product group</a>

                    <table class="table w-full mt-4 overflow-auto">
                        <thead>
                        <tr>
                            <th class="px-4 py-2">کد طرف حساب</th>
                            <th class="px-4 py-2">نام</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                        </thead>
                        <tbody>

                        @foreach ($productGroups as $productGroup)
                            <tr>
                                <td class="px-4 py-2">{{ $productGroup->code }}</td>
                                <td class="px-4 py-2">{{ $productGroup->name }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('product-groups.edit', $productGroup) }}"
                                       class="btn btn-sm btn-info">Edit</a>
                                    <form action="{{ route('product-groups.destroy', $productGroup) }}" method="POST"
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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
