<x-app-layout title="{{ __('Year-End Closing Wizard') }} – {{ $company->name }} {{ $company->fiscal_year }}">
    <div class="card bg-base-100 shadow-xl">

        {{-- Header --}}
        <div class="card-header bg-gradient-to-r from-amber-50 to-orange-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-warning/30">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                        {{ __('Year-End Closing Wizard') }}
                    </h2>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="badge badge-lg badge-warning gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ $company->fiscal_year }}
                        </span>
                        <span class="badge badge-lg badge-neutral gap-2">
                            {{ $company->name }}
                        </span>
                        @if ($company->closed_at)
                            <span class="badge badge-lg badge-error gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                {{ __('Closed') }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <a href="{{ route('companies.index') }}" class="btn btn-ghost btn-sm gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        {{ __('Back') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body space-y-8">

            {{-- Flash messages --}}
            @if (session('success'))
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- PRE-FLIGHT VALIDATIONS                                  --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">{{ __('Pre-flight Checks') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    @foreach ($validations as $check)
                        <div class="bg-base-200 rounded-lg px-4 py-4 flex items-start gap-3">
                            @if ($check['pass'])
                                <span class="text-success mt-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                            @else
                                <span class="text-error mt-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                            @endif
                            <div>
                                <div class="font-semibold {{ $check['pass'] ? 'text-success' : 'text-error' }}">
                                    {{ $check['label'] }}
                                </div>
                                @if ($check['detail'])
                                    <div class="text-xs text-gray-500 mt-1">{{ $check['detail'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if (!$allPass)
                    <div class="alert alert-warning mt-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{{ __('Please fix all failing checks before proceeding with year-end closing.') }}</span>
                    </div>
                @endif
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STEP 1 – CLOSE TEMPORARY ACCOUNTS                      --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">
                    <span class="badge badge-lg {{ $plDocument ? 'badge-success' : 'badge-neutral' }} me-2">1</span>
                    {{ __('Step 1: Close Temporary Accounts') }}
                    @if ($plDocument)
                        <span class="badge badge-success badge-sm ms-2">{{ __('Completed') }}</span>
                    @endif
                </div>

                <div class="bg-base-200 rounded-lg px-4 py-4 text-sm space-y-3">
                    <p class="text-gray-600 dark:text-gray-300">
                        {{ __('This step generates the Income Summary document, closing all revenue and expense (temporary) accounts to the "Current Profit and Loss Summary" subject.') }}
                    </p>

                    @if ($plDocument)
                        <div class="flex items-center gap-3 bg-success/10 border border-success/30 rounded-lg px-4 py-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <span class="font-semibold text-success">{{ __('Income Summary document created:') }}</span>
                                <a href="{{ route('documents.show', $plDocument) }}" class="link link-primary ms-2">
                                    {{ __('Document') }} #{{ $plDocument->number }} – {{ $plDocument->title }}
                                </a>
                            </div>
                        </div>
                    @else
                        <form action="{{ route('companies.closing-wizard.step1', $company) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-warning gap-2 {{ !$allPass || $company->closed_at ? 'btn-disabled' : '' }}"
                                @disabled(!$allPass || $company->closed_at) @if (!$allPass) title="{{ __('Fix all pre-flight checks first.') }}" @endif>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ __('Close Temporary Accounts') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STEP 2 – MANUAL ADJUSTMENTS (TAXES / DIVIDENDS)        --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">
                    <span class="badge badge-lg {{ $plDocument ? 'badge-warning' : 'badge-neutral' }} me-2">2</span>
                    {{ __('Step 2: Manual Adjustments') }}
                </div>

                <div class="bg-base-200 rounded-lg px-4 py-4 text-sm space-y-3">
                    <p class="text-gray-600 dark:text-gray-300">
                        {{ __('Manually zero out the Income Summary account (taxes, dividends, retained earnings, etc.). The balance must reach exactly 0 before Step 3 can proceed.') }}
                    </p>

                    @if ($plDocument)
                        {{-- Income Summary balance indicator --}}
                        <div
                            class="flex items-center gap-3 {{ $incomeSummaryBalance === 0.0 ? 'bg-success/10 border-success/30' : 'bg-warning/10 border-warning/30' }} border rounded-lg px-4 py-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 {{ $incomeSummaryBalance === 0.0 ? 'text-success' : 'text-warning' }}"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                            <div>
                                <span class="font-semibold">{{ __('Income Summary balance:') }}</span>
                                <span class="font-mono ms-2 {{ $incomeSummaryBalance === 0.0 ? 'text-success font-bold' : 'text-warning font-bold' }}">
                                    {{ formatNumber($incomeSummaryBalance) }}
                                </span>
                                @if ($incomeSummaryBalance === 0.0)
                                    <span class="badge badge-success badge-sm ms-2">{{ __('Zero ✓') }}</span>
                                @else
                                    <span class="badge badge-warning badge-sm ms-2">{{ __('Not zero') }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($incomeSummaryBalance !== 0.0)
                            <a href="{{ route('documents.create') }}" class="btn btn-outline btn-primary gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('Create Manual Document (Taxes / Dividends)') }}
                            </a>
                        @endif
                    @else
                        <div class="text-gray-400 italic">{{ __('Complete Step 1 first to unlock this step.') }}</div>
                    @endif
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STEP 3 – CLOSE PERMANENT ACCOUNTS & OPEN NEW YEAR      --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">
                    <span class="badge badge-lg {{ $company->closed_at ? 'badge-success' : ($step3Enabled ? 'badge-error' : 'badge-neutral') }} me-2">3</span>
                    {{ __('Step 3: Close Permanent Accounts & Open New Year') }}
                    @if ($company->closed_at)
                        <span class="badge badge-success badge-sm ms-2">{{ __('Completed') }}</span>
                    @endif
                </div>

                <div class="bg-base-200 rounded-lg px-4 py-4 text-sm space-y-3">
                    <p class="text-gray-600 dark:text-gray-300">
                        {{ __('This final step performs three actions in a single transaction:') }}
                    </p>
                    <ol class="list-decimal list-inside space-y-1 text-gray-600 dark:text-gray-300 ms-2">
                        <li>{{ __('Generates the Closing Document (closes all permanent accounts to the Closing Summary).') }}</li>
                        <li>{{ __('Creates the new Fiscal Year entity for year :year.', ['year' => $company->fiscal_year + 1]) }}</li>
                        <li>{{ __('Generates the Opening Document in the new fiscal year.') }}</li>
                    </ol>

                    @if ($company->closed_at)
                        <div class="flex items-center gap-3 bg-success/10 border border-success/30 rounded-lg px-4 py-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <span class="font-semibold text-success">{{ __('Fiscal year closed on') }}</span>
                                <span class="ms-1">{{ formatDate($company->closed_at) }}</span>
                                @if ($company->closingDocument)
                                    <a href="{{ route('documents.show', $company->closingDocument) }}" class="link link-primary ms-3">
                                        {{ __('View Closing Document') }} #{{ $company->closingDocument->number }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @else
                        @if (!$step3Enabled)
                            <div class="text-xs text-gray-500 space-y-1">
                                @if (!$plDocument)
                                    <div class="flex items-center gap-1 text-error">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        {{ __('Step 1 must be completed first.') }}
                                    </div>
                                @endif
                                @if ($incomeSummaryBalance !== null && $incomeSummaryBalance !== 0.0)
                                    <div class="flex items-center gap-1 text-error">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        {{ __('Income Summary balance must be zero.') }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        <form action="{{ route('companies.closing-wizard.step3', $company) }}" method="POST"
                            onsubmit="return confirm('{{ __('This will permanently close fiscal year :year and create year :next. Are you absolutely sure?', ['year' => $company->fiscal_year, 'next' => $company->fiscal_year + 1]) }}')">
                            @csrf
                            <button type="submit" class="btn btn-error gap-2 {{ !$step3Enabled ? 'btn-disabled' : '' }}" @disabled(!$step3Enabled)>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                {{ __('Close Permanent Accounts & Open New Year') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Bottom navigation --}}
            <div class="card-actions justify-start mt-4">
                <a href="{{ route('companies.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back to Companies') }}
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
