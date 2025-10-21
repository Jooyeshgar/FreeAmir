<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transaction Details') }} #{{ $transaction->id }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Transaction Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Transaction Information') }}</h3>

                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Transaction ID') }}</label>
                            <p class="text-base">{{ $transaction->id }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Description') }}</label>
                            <p class="text-base">{{ $transaction->desc }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600">{{ __('Debit') }}</label>
                                <p class="text-base text-red-600 font-semibold">{{ $transaction->debit ?: '-' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600">{{ __('Credit') }}</label>
                                <p class="text-base text-green-600 font-semibold">{{ $transaction->credit ?: '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Information -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Subject Information') }}</h3>

                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Subject Code') }}</label>
                            <p class="text-base font-mono">{{ $transaction->subject->code }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Subject Name') }}</label>
                            <p class="text-base">{{ $transaction->subject->name }}</p>
                        </div>

                        @if ($transaction->subject->parent)
                            <div>
                                <label class="text-sm font-medium text-gray-600">{{ __('Parent Subject') }}</label>
                                <p class="text-base">{{ $transaction->subject->parent->name }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Document Information -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Document Information') }}</h3>

                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Document Number') }}</label>
                            <p class="text-base">
                                <a href="{{ route('documents.show', $transaction->document->id) }}" class="text-blue-600 hover:text-blue-800 font-semibold">
                                    {{ formatDocumentNumber($transaction->document->number) }}
                                </a>
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Document Title') }}</label>
                            <p class="text-base">{{ $transaction->document->title ?: __('No title') }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Document Date') }}</label>
                            <p class="text-base">{{ formatDate($transaction->document->date) }}</p>
                        </div>
                    </div>
                </div>

                <!-- User Information -->
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4">{{ __('User Information') }}</h3>

                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Created By') }}</label>
                            <p class="text-base">{{ $transaction->user->name }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('Created At') }}</label>
                            <p class="text-base">{{ formatDate($transaction->created_at) }}</p>
                        </div>

                        @if ($transaction->updated_at != $transaction->created_at)
                            <div>
                                <label class="text-sm font-medium text-gray-600">{{ __('Updated At') }}</label>
                                <p class="text-base">{{ formatDate($transaction->updated_at) }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card-actions justify-end mt-6 pt-4 border-t">
                <a href="{{ route('transactions.index') }}" class="btn btn-secondary">
                    {{ __('Back to Transactions') }}
                </a>
                <a href="{{ route('documents.show', $transaction->document->id) }}" class="btn btn-primary">
                    {{ __('View Document') }}
                </a>
                @if ($transaction->subject_id)
                    <a href="{{ route('transactions.index', ['subject_id' => $transaction->subject_id]) }}" class="btn btn-info">
                        {{ __('View Subject Transactions') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
