<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customer Details') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-4 flex justify-between items-center">
                        <h1 class="text-2xl font-bold">{{ $customer->name }}</h1>
                        <div class="flex gap-2">
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-info">
                                {{ __('Edit') }}
                            </a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST"
                                onsubmit="return confirm('{{ __('Are you sure you want to delete this customer?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-error">
                                    {{ __('Delete') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Cards side by side with customer information -->
                    <div class="">
                        <!-- Identity Information Card -->
                        <div class="bg-gray-100 p-6 rounded-lg shadow-md mb-6">
                            <h2 class="text-lg font-bold mb-4">{{ __('Identity Information') }}</h2>
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600">{{ __('Name') }}</p>
                                        <p class="font-medium">{{ $customer->name ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">{{ __('Account Plan Group') }}</p>
                                        <p class="font-medium">{{ $customer->group->name ?? '-' }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600">{{ __('National ID') }}</p>
                                        <p class="font-medium">{{ $customer->personal_code ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">{{ __('Economic code') }}</p>
                                        <p class="font-medium">{{ $customer->ecnmcs_code ?? '-' }}</p>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-600">{{ __('Accounting code') }}</p>
                                    <p class="font-medium">{{ isset($customer->subject) ? $customer->subject->formattedCode() : '-' }}</p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-600">{{ __('Description') }}</p>
                                    <p class="font-medium">{{ $customer->desc ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- DaisyUI Tabs Card -->
                        <div class="bg-gray-100 p-6 rounded-lg shadow-md">
                            <div role="tablist" class="tabs tabs-lifted">
                                <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="{{ __('Contact Information') }}" checked />
                                <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6 space-y-4">
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Phone') }}</p>
                                            <p class="font-medium">{{ $customer->tel ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Mobile') }}</p>
                                            <p class="font-medium">{{ $customer->cell ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Fax') }}</p>
                                            <p class="font-medium">{{ $customer->fax ?? '-' }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Email') }}</p>
                                            <p class="font-medium">{{ $customer->email ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Website') }}</p>
                                            <p class="font-medium">{{ $customer->web_page ?? '-' }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-600">{{ __('Postal code') }}</p>
                                        <p class="font-medium">{{ $customer->postal_code ?? '-' }}</p>
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-600">{{ __('Address') }}</p>
                                        <p class="font-medium">{{ $customer->address ?? '-' }}</p>
                                    </div>
                                </div>

                                <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="{{ __('Financial Information') }}" />
                                <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6 space-y-4">
                                    <h3 class="font-semibold text-lg">{{ __('Account 1') }}</h3>
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Name') }}</p>
                                            <p class="font-medium">{{ $customer->acc_name_1 ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Account number') }}</p>
                                            <p class="font-medium">{{ $customer->acc_no_1 ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Bank') }}</p>
                                            <p class="font-medium">{{ $customer->acc_bank_1 ?? '-' }}</p>
                                        </div>
                                    </div>

                                    <h3 class="font-semibold text-lg mt-4">{{ __('Account 2') }}</h3>
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Name') }}</p>
                                            <p class="font-medium">{{ $customer->acc_name_2 ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Account number') }}</p>
                                            <p class="font-medium">{{ $customer->acc_no_2 ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Bank') }}</p>
                                            <p class="font-medium">{{ $customer->acc_bank_2 ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="{{ __('Other Information') }}" />
                                <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6 space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Connector') }}</p>
                                            <p class="font-medium">{{ $customer->connector ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600">{{ __('Responsible') }}</p>
                                            <p class="font-medium">{{ $customer->responsible ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
