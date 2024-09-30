<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customers') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('customers.create') }}" class="btn btn-primary">Create customer</a>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">کد</th>
                        <th class="px-4 py-2">نام</th>
                        <th class="px-4 py-2">شماره</th>
                        <th class="px-4 py-2">ایمیل</th>
                        <th class="px-4 py-2">گروه طرف حساب</th>
                        <th class="px-4 py-2">Action</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($customers as $customer)
                        <tr>
                            <td class="px-4 py-2">{{ $customer->code }}</td>
                            <td class="px-4 py-2">{{ $customer->name }}</td>
                            <td class="px-4 py-2">{{ $customer->phone }}</td>
                            <td class="px-4 py-2">{{ $customer->email }}</td>
                            <td class="px-4 py-2">{{ $customer->group ? $customer->group->name : '' }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-info">Edit</a>
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {!! $customers->links() !!}
        </div>
    </div>
</x-app-layout>
