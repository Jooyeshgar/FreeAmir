<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customers') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions flex justify-between gap-4 ">
                <a href="{{ route('customers.create') }}" class="btn btn-primary ">{{ __('Create Customer') }}</a>

                <form method="GET" action="{{ route('customers.index') }}" class="flex items-center gap-2 ">
                    <label for="group_id" class="sr-only">{{ __('Customer Group') }}</label>
                    <select name="group_id" id="group_id" class="select select-bordered select-sm" onchange="this.form.submit()">
                        <option value="all">{{ __('All Groups') }}</option>
                        @foreach ($groups as $g)
                            <option value="{{ $g->id }}" @selected(isset($groupId) && $groupId !== 'all' && (int) $groupId === $g->id)>{{ $g->name }}
                            </option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="btn btn-sm">{{ __('Filter') }}</button></noscript>
                </form>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Subject Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('‌Balance') }}</th>
                        <th class="px-4 py-2">{{ __('Phone number') }}</th>
                        <th class="px-4 py-2">{{ __('Account Plan Group') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($customers as $customer)
                        <tr>
                            <td class="px-4 py-2">{{ $customer->subject?->formattedCode() }}</td>
                            <td class="px-4 py-2"><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                            <td class="px-4 py-2"><a
                                    href="{{ route('transactions.index', ['subject_id' => $customer->subject->id]) }}">{{ app\Services\SubjectService::sumSubject($customer->subject) }}</a>
                            </td>
                            <td class="px-4 py-2">{{ $customer->phone }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('customer-groups.show', $customer->group) }}">
                                    {{ $customer->group ? $customer->group->name : '' }}</a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
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
