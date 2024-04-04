<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products Group') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('product-groups.create') }}" class="btn btn-primary">{{ __("Create product group") }}</a>
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Account code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
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
</x-app-layout>
