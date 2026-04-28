<x-app-layout :title="__('Payroll Detail')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-header bg-gradient-to-r from-green-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="card-title text-lg">
                        {{ $payroll->employee?->first_name }}
                        {{ $payroll->employee?->last_name }}
                        &mdash;
                        {{ \App\Models\MonthlyAttendance::MONTH_NAMES[$payroll->month] ?? $payroll->month }}
                        {{ convertToFarsi($payroll->year) }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{ __('Decree') }}:
                        <span class="font-medium">{{ $payroll->decree?->name ?? '—' }}</span>
                        &nbsp;|&nbsp;
                        {{ __('Status') }}:
                        @if ($payroll->status === 'draft')
                            <span class="badge badge-warning badge-sm">{{ __('Draft') }}</span>
                        @elseif ($payroll->status === 'approved')
                            <span class="badge badge-success badge-sm">{{ __('Approved') }}</span>
                        @else
                            <span class="badge badge-info badge-sm">{{ __('Paid') }}</span>
                        @endif
                    </p>
                </div>

                <div class="flex gap-2 flex-wrap">
                    @if ($isEmployeeView ?? false)
                        <a href="{{ route('employee-portal.payrolls') }}" class="btn btn-sm btn-ghost">
                            {{ __('Back to Payrolls') }}
                        </a>
                    @else
                        @if ($payroll->monthlyAttendance)
                            <a href="{{ route('attendance.monthly-attendances.show', $payroll->monthly_attendance_id) }}" class="btn btn-sm btn-ghost">
                                {{ __('Back to Attendance') }}
                            </a>
                        @else
                            <a href="{{ route('attendance.monthly-attendances.index') }}" class="btn btn-sm btn-ghost">
                                {{ __('Back') }}
                            </a>
                        @endif

                        @can('salary.payrolls.delete')
                            <form action="{{ route('salary.payrolls.destroy', $payroll) }}" method="POST" class="inline-block"
                                onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-error">
                                    {{ __('Delete') }}
                                </button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        <div class="card-body">
            {{-- Summary stats --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-2">
                <div class="stat bg-success/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Total Earnings') }}</div>
                    <div x-data="{ show: false }" @click="show = !show">
                        <span x-show="!show">****</span>
                        <div x-show="show" class="stat-value text-base text-success">{{ formatNumber($payroll->total_earnings) }}</div>
                    </div>
                </div>
                <div class="stat bg-error/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Total Deductions') }}</div>
                    <div class="stat-value text-base text-error">{{ formatNumber($payroll->total_deductions) }}</div>
                </div>
                <div class="stat bg-primary/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Net Payment') }}</div>
                    <div x-data="{ show: false }" @click="show = !show">
                        <span x-show="!show">****</span>
                        <div x-show="show" class="stat-value text-base text-primary">{{ formatNumber($payroll->net_payment) }}</div>
                    </div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Issue Date') }}</div>
                    <div class="stat-value text-base">{{ formatDate($payroll->issue_date) }}</div>
                </div>
            </div>

            @if ($payroll->description)
                <p class="text-sm text-gray-500 mt-3">{{ $payroll->description }}</p>
            @endif

            <div class="divider">{{ __('Payroll Items') }}</div>

            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th class="text-end">{{ __('Unit Count') }}</th>
                            <th class="text-end">{{ __('Unit Rate') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            @can('salary.payrolls.edit')
                                <th></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payroll->items as $item)
                            <tr>
                                <td>{{ $item->description ?? ($item->element?->title ?? '—') }}</td>
                                <td>
                                    @if ($item->element)
                                        @if ($item->element->category === 'earning')
                                            <span class="badge badge-success badge-sm">{{ __('Earning') }}</span>
                                        @else
                                            <span class="badge badge-error badge-sm">{{ __('Deduction') }}</span>
                                        @endif
                                    @elseif ($item->calculated_amount >= 0)
                                        <span class="badge badge-success badge-sm">{{ __('Earning') }}</span>
                                    @else
                                        <span class="badge badge-error badge-sm">{{ __('Deduction') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $item->unit_count !== null ? formatNumber($item->unit_count) : '—' }}</td>
                                <td class="text-end">{{ $item->unit_rate !== null ? formatNumber($item->unit_rate) : '—' }}</td>
                                <td class="text-end {{ $item->calculated_amount >= 0 ? 'text-success' : 'text-error' }}">
                                    {{ formatNumber(abs((float) $item->calculated_amount)) }}
                                </td>
                                @can('salary.payrolls.edit')
                                    <td class="text-end">
                                        @if (!($isEmployeeView ?? false))
                                            <a href="{{ route('salary.payroll-items.edit', $item) }}" class="btn btn-xs btn-ghost" title="{{ __('Edit') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                                                 m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        @endif
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-gray-500">
                                    {{ __('No payroll items found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-bold border-t-2">
                            <td colspan="4" class="text-end">{{ __('Net Payment') }}</td>
                            <td class="text-end text-primary" x-data="{ show: false }" @click="show = !show">
                                <span x-show="!show">****</span>
                                <div x-show="show" class="stat-value text-xs text-primary">{{ formatNumber($payroll->net_payment) }}</div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
