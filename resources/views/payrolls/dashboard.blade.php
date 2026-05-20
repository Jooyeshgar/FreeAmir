@php
    $periodLabel = (\App\Models\MonthlyAttendance::MONTH_NAMES[$month] ?? $month) . ' ' . convertToFarsi((string) $year);
    $filterParams = array_filter(
        request()->only(['year', 'month', 'organization_unit_id', 'q']),
        fn($value) => filled($value),
    );
    $statusParams = array_filter(
        request()->only(['year', 'month', 'organization_unit_id']),
        fn($value) => filled($value),
    );
    $departmentBarClasses = ['progress-info', 'progress-success', 'progress-warning', 'progress-secondary', 'progress-error', 'progress-primary'];
    $heatmapClasses = [
        'present' => 'border-emerald-400/40 bg-emerald-400',
        'delay' => 'border-amber-400/40 bg-amber-400',
        'leave' => 'border-sky-400/40 bg-sky-400',
        'absent' => 'border-rose-400/40 bg-rose-400',
        'mission' => 'border-violet-400/40 bg-violet-400',
        'future' => 'border-base-300 bg-base-300/40',
    ];
@endphp

<x-app-layout :title="'داشبورد حقوق و دستمزد'">
    <x-show-message-bags />

    <main class="mt-8 space-y-4" x-data="{ compactTable: false }">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-base-content">داشبورد حقوق و دستمزد</h1>
                <p class="mt-1 text-sm text-base-content/60">
                    نمای کلی پرداختی‌ها، کسورات و حضور پرسنل - دوره گزارش: {{ $periodLabel }}
                </p>
            </div>

            <form action="{{ route('salary.payrolls.dashboard') }}" method="GET" class="flex flex-wrap items-end gap-2">
                <label class="form-control w-32">
                    <span class="label-text mb-1 text-xs">سال</span>
                    <select name="year" class="select select-sm select-bordered">
                        @foreach ($availableYears as $availableYear)
                            <option value="{{ $availableYear }}" @selected($year === (int) $availableYear)>
                                {{ convertToFarsi((string) $availableYear) }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control w-36">
                    <span class="label-text mb-1 text-xs">ماه</span>
                    <select name="month" class="select select-sm select-bordered">
                        @foreach (\App\Models\MonthlyAttendance::MONTH_NAMES as $monthNumber => $monthName)
                            <option value="{{ $monthNumber }}" @selected($month === $monthNumber)>{{ $monthName }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control w-44">
                    <span class="label-text mb-1 text-xs">واحد</span>
                    <select name="organization_unit_id" class="select select-sm select-bordered">
                        <option value="">همه واحدها</option>
                        @foreach ($organizationUnits as $unit)
                            <option value="{{ $unit->id }}" @selected($organizationUnitId === $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="btn btn-sm btn-neutral">اعمال</button>
                <a href="{{ route('salary.payrolls.dashboard') }}" class="btn btn-sm btn-ghost">بازنشانی</a>

                @can('attendance.monthly-attendances.index')
                    <a href="{{ route('attendance.monthly-attendances.index', ['year' => $year, 'month' => $month]) }}" class="btn btn-sm btn-info">
                        + محاسبه حقوق ماه
                    </a>
                @else
                    <button type="button" class="btn btn-sm btn-info" disabled>+ محاسبه حقوق ماه</button>
                @endcan

                <button type="button" class="btn btn-sm btn-outline" disabled>خروجی Excel</button>
            </form>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ($metricCards as $card)
                @php
                    $tone = [
                        'info' => ['card' => 'border-sky-500/25 bg-sky-500/10', 'icon' => 'bg-sky-500/15 text-sky-500', 'stroke' => '#38bdf8'],
                        'error' => ['card' => 'border-rose-500/25 bg-rose-500/10', 'icon' => 'bg-rose-500/15 text-rose-500', 'stroke' => '#fb7185'],
                        'success' => ['card' => 'border-emerald-500/25 bg-emerald-500/10', 'icon' => 'bg-emerald-500/15 text-emerald-500', 'stroke' => '#34d399'],
                        'primary' => ['card' => 'border-primary/25 bg-primary/10', 'icon' => 'bg-primary/15 text-primary', 'stroke' => '#60a5fa'],
                        'warning' => ['card' => 'border-amber-500/25 bg-amber-500/10', 'icon' => 'bg-amber-500/15 text-amber-500', 'stroke' => '#f59e0b'],
                        'secondary' => ['card' => 'border-violet-500/25 bg-violet-500/10', 'icon' => 'bg-violet-500/15 text-violet-500', 'stroke' => '#a78bfa'],
                    ][$card['tone']] ?? ['card' => 'border-base-300 bg-base-200', 'icon' => 'bg-base-300 text-base-content', 'stroke' => '#64748b'];
                    $change = $card['change'];
                @endphp

                <article class="card border shadow-sm {{ $tone['card'] }}">
                    <div class="card-body gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="rounded-lg p-2 {{ $tone['icon'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 3 2 4-6" />
                                </svg>
                            </div>
                            <span class="text-xs text-base-content/60">{{ $card['title'] }}</span>
                        </div>

                        <div>
                            <div class="text-xl font-bold leading-8 text-base-content">
                                {{ formatNumber($card['value']) }}
                            </div>
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="text-base-content/60">{{ $card['suffix'] }}</span>
                                @if ($change === null)
                                    <span class="text-base-content/50">بدون تغییر</span>
                                @else
                                    <span class="{{ $change >= 0 ? 'text-success' : 'text-error' }}">
                                        {{ $change >= 0 ? '↑' : '↓' }}
                                        {{ formatNumber(abs($change)) }}٪
                                    </span>
                                @endif
                            </div>
                        </div>

                        <svg class="h-11 w-full overflow-visible" viewBox="0 0 180 56" preserveAspectRatio="none" aria-hidden="true">
                            <polyline points="{{ $card['sparkline'] }}" fill="none" stroke="{{ $tone['stroke'] }}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity=".9" />
                        </svg>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(20rem,0.8fr)_minmax(0,1.6fr)]">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">تفکیک هزینه به‌ازای واحد</h2>
                            <p class="text-xs text-base-content/55">سهم هر واحد از کل پرداختی و بیمه کارفرما</p>
                        </div>
                        <span class="badge badge-ghost">جزئیات</span>
                    </div>

                    <div class="mt-3 space-y-4">
                        @forelse ($departmentCosts as $department)
                            @php $barClass = $departmentBarClasses[$loop->index % count($departmentBarClasses)]; @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium">{{ $department['name'] }}</span>
                                    <span class="text-xs text-base-content/60">{{ convertToFarsi((string) $department['employees']) }} نفر</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <progress class="progress {{ $barClass }} h-2" value="{{ $department['percent'] }}" max="100"></progress>
                                    <span class="w-24 shrink-0 text-xs text-base-content/70">{{ formatNumber($department['cost']) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-base-300 bg-base-200/50 p-5 text-center text-sm text-base-content/60">
                                برای این دوره هزینه‌ای ثبت نشده است.
                            </div>
                        @endforelse
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">روند حقوق و کسورات سال جاری</h2>
                            <p class="text-xs text-base-content/55">مجموع ماهانه (ریال)</p>
                        </div>
                        <div class="join">
                            <button type="button" class="btn join-item btn-xs btn-active">۱۲ ماه</button>
                            <button type="button" class="btn join-item btn-xs" disabled>فصلی</button>
                            <button type="button" class="btn join-item btn-xs" disabled>ابتدای سال</button>
                        </div>
                    </div>
                    <div class="mt-3 h-72">
                        <canvas id="payrollTrendChart" class="h-full w-full"></canvas>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">هشدارها و یادآوری‌ها</h2>
                            <p class="text-xs text-base-content/55">مواردی که نیاز به توجه دارد</p>
                        </div>
                        <span class="text-xs text-base-content/50">مشاهده همه</span>
                    </div>

                    <div class="mt-3 space-y-3">
                        @foreach ($alerts as $alert)
                            @php
                                $alertClass = match ($alert['tone']) {
                                    'warning' => 'bg-warning/10 text-warning',
                                    'info' => 'bg-info/10 text-info',
                                    'success' => 'bg-success/10 text-success',
                                    default => 'bg-base-200 text-base-content/70',
                                };
                            @endphp
                            <div class="flex items-center gap-3 rounded-lg bg-base-200/70 p-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $alertClass }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold">{{ $alert['title'] }}</div>
                                    <div class="truncate text-xs text-base-content/55">{{ $alert['description'] }}</div>
                                </div>
                                @if ($alert['tone'] === 'placeholder')
                                    <span class="badge badge-ghost badge-sm">نمونه</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">جریان تایید حقوق</h2>
                            <p class="text-xs text-base-content/55">وضعیت احکام در گردش</p>
                        </div>
                        <span class="badge badge-warning badge-sm">{{ convertToFarsi((string) $statusSummaries->sum('count')) }} مورد فعال</span>
                    </div>

                    <div class="mt-3 space-y-3">
                        @foreach ($statusSummaries as $status)
                            <a href="{{ route('salary.payrolls.dashboard', array_merge($filterParams, ['status' => $status['value']])) }}" class="block rounded-lg bg-base-200/70 p-3 transition hover:bg-base-200">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold">{{ $status['label'] }}</div>
                                        <div class="text-xs text-base-content/55">{{ formatNumber($status['amount']) }} ریال</div>
                                    </div>
                                    <span class="badge {{ $status['badge'] }}">{{ convertToFarsi((string) $status['count']) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">حضور و غیاب ماه</h2>
                            <p class="text-xs text-base-content/55">جمع روزهای ثبت‌شده</p>
                        </div>
                        <span class="badge badge-ghost">همه پرسنل</span>
                    </div>

                    <div class="mt-3 grid grid-cols-1 items-center gap-4 sm:grid-cols-[12rem_1fr]">
                        <div class="relative h-48">
                            <canvas id="attendanceDonutChart" class="h-full w-full"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="text-2xl font-bold">{{ formatNumber($attendanceSummary['present_days'] + $attendanceSummary['absent_days']) }}</span>
                                <span class="text-xs text-base-content/55">روز</span>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>حضور کامل</span>
                                <span>{{ formatNumber($attendanceSummary['present_days']) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>تاخیر / تعجیل</span>
                                <span>{{ formatMinutesAsTime($attendanceSummary['delay_minutes']) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-sky-400"></span>مرخصی</span>
                                <span>{{ formatMinutesAsTime($attendanceSummary['leave_minutes']) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>غیبت</span>
                                <span>{{ formatNumber($attendanceSummary['absent_days']) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body p-0">
                <div class="flex flex-col gap-3 border-b border-base-300 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="card-title text-base">لیست حقوق پرسنل</h2>
                        <p class="text-xs text-base-content/55">نمایش {{ convertToFarsi((string) $payrolls->count()) }} از {{ convertToFarsi((string) $payrolls->total()) }} مورد</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="join">
                            <a href="{{ route('salary.payrolls.dashboard', $statusParams) }}" class="btn join-item btn-xs {{ $statusFilter ? 'btn-ghost' : 'btn-primary' }}">
                                همه
                                <span class="badge badge-sm">{{ convertToFarsi((string) $statusSummaries->sum('count')) }}</span>
                            </a>
                            @foreach ($statusSummaries as $status)
                                <a href="{{ route('salary.payrolls.dashboard', array_merge($statusParams, ['status' => $status['value']])) }}"
                                    class="btn join-item btn-xs {{ $statusFilter === $status['value'] ? 'btn-primary' : 'btn-ghost' }}">
                                    {{ $status['label'] }}
                                    <span class="badge badge-sm">{{ convertToFarsi((string) $status['count']) }}</span>
                                </a>
                            @endforeach
                        </div>

                        <form action="{{ route('salary.payrolls.dashboard') }}" method="GET" class="flex items-center gap-2">
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="month" value="{{ $month }}">
                            @if ($organizationUnitId)
                                <input type="hidden" name="organization_unit_id" value="{{ $organizationUnitId }}">
                            @endif
                            @if ($statusFilter)
                                <input type="hidden" name="status" value="{{ $statusFilter }}">
                            @endif
                            <input type="search" name="q" value="{{ $search }}" class="input input-sm input-bordered w-52" placeholder="جستجوی نام یا واحد..." />
                            <button type="submit" class="btn btn-sm btn-ghost">جستجو</button>
                        </form>

                        <label class="flex cursor-pointer items-center gap-2 text-xs text-base-content/60">
                            <input type="checkbox" class="toggle toggle-xs" x-model="compactTable">
                            فشرده
                        </label>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra" :class="{ 'table-sm': compactTable }">
                        <thead>
                            <tr>
                                <th>کارمند</th>
                                <th>واحد</th>
                                <th>روزهای کاری</th>
                                <th>اضافه‌کاری</th>
                                <th>درآمد ناخالص</th>
                                <th>کسورات</th>
                                <th>بیمه کارفرما</th>
                                <th>پرداخت خالص</th>
                                <th>وضعیت</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payrolls as $payroll)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar placeholder">
                                                <div class="w-9 rounded-lg bg-primary text-primary-content">
                                                    <span>{{ mb_substr($payroll->employee?->first_name ?? '؟', 0, 1) }}</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-semibold">
                                                    {{ $payroll->employee?->first_name }} {{ $payroll->employee?->last_name }}
                                                </div>
                                                <div class="text-xs text-base-content/50">{{ $payroll->employee?->code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $payroll->employee?->organizationUnit?->name ?? 'بدون واحد' }}</td>
                                    <td>
                                        @if ($payroll->monthlyAttendance)
                                            {{ formatNumber($payroll->monthlyAttendance->present_days) }}
                                            /
                                            {{ formatNumber($payroll->monthlyAttendance->work_days) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $payroll->monthlyAttendance ? formatMinutesAsTime($payroll->monthlyAttendance->overtime + $payroll->monthlyAttendance->auto_overtime) : '-' }}</td>
                                    <td class="font-medium">{{ formatNumber($payroll->total_earnings) }}</td>
                                    <td class="font-medium text-error">-{{ formatNumber($payroll->total_deductions) }}</td>
                                    <td>{{ formatNumber($payroll->employer_insurance) }}</td>
                                    <td class="font-bold">{{ formatNumber($payroll->net_payment) }} ریال</td>
                                    <td><span class="badge {{ $payroll->statusBadgeClass() }} badge-sm">{{ $payroll->statusLabel() }}</span></td>
                                    <td>
                                        @can('salary.payrolls.show')
                                            <a href="{{ route('salary.payrolls.show', $payroll) }}" class="btn btn-xs btn-ghost">مشاهده</a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-8 text-center text-base-content/55">برای فیلتر انتخاب‌شده فیش حقوقی وجود ندارد.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-base-300 p-4">
                    {{ $payrolls->links() }}
                </div>
            </div>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="card-title text-base">لاگ حضور روزانه - {{ $periodLabel }}</h2>
                        <p class="text-xs text-base-content/55">
                            پنج کارمند آخر، کلیک روی هر روز برای جزئیات در نسخه بعدی فعال می‌شود
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline" disabled>فیلتر</button>
                        <button type="button" class="btn btn-sm btn-outline" disabled>خروجی</button>
                    </div>
                </div>

                @if ($attendanceHeatmap['placeholder'])
                    <div class="alert alert-info mt-3 py-2 text-sm">
                        <span>این ماتریس با نمونه داده نمایش داده شده تا منبع کامل لاگ روزانه به داشبورد وصل شود.</span>
                    </div>
                @endif

                <div class="mt-4 overflow-x-auto">
                    <div class="min-w-[860px] space-y-2">
                        <div class="grid items-center gap-1" style="grid-template-columns: 8rem repeat({{ $attendanceHeatmap['days']->count() }}, minmax(1.6rem, 1fr));">
                            <div></div>
                            @foreach ($attendanceHeatmap['days'] as $day)
                                <div class="text-center text-[11px] text-base-content/50">{{ convertToFarsi((string) $day) }}</div>
                            @endforeach
                        </div>

                        @foreach ($attendanceHeatmap['employees'] as $employee)
                            <div class="grid items-center gap-1" style="grid-template-columns: 8rem repeat({{ $attendanceHeatmap['days']->count() }}, minmax(1.6rem, 1fr));">
                                <div class="truncate pl-2 text-xs font-medium">{{ $employee['name'] }}</div>
                                @foreach ($employee['cells'] as $cell)
                                    <div class="h-7 rounded-md border {{ $heatmapClasses[$cell] ?? $heatmapClasses['future'] }}"></div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-4 text-xs text-base-content/60">
                    <span class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-emerald-400"></span>حضور کامل</span>
                    <span class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-amber-400"></span>تاخیر / تعجیل</span>
                    <span class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-sky-400"></span>مرخصی</span>
                    <span class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-rose-400"></span>غیبت</span>
                    <span class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-base-300"></span>روزهای آینده</span>
                </div>
            </div>
        </section>
    </main>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const payrollData = @json($payrollChartData);
                const attendanceData = @json($attendanceChartData);
                let payrollChart = null;
                let attendanceChart = null;

                const formatMoney = (value) => {
                    const locale = document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-US';
                    return new Intl.NumberFormat(locale, {
                        maximumFractionDigits: 0,
                    }).format(value);
                };

                const renderCharts = () => {
                    if (!window.Chart) {
                        return;
                    }

                    const theme = window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                        textColor: '#475569',
                        mutedTextColor: '#64748b',
                        gridColor: 'rgba(148, 163, 184, 0.24)',
                    };

                    const payrollCanvas = document.getElementById('payrollTrendChart');
                    if (payrollCanvas) {
                        payrollChart?.destroy();
                        payrollChart = new Chart(payrollCanvas, {
                            type: 'bar',
                            data: {
                                labels: payrollData.labels,
                                datasets: [{
                                        label: 'پرداخت خالص',
                                        data: payrollData.net,
                                        backgroundColor: '#38bdf8',
                                        borderRadius: 7,
                                        stack: 'payroll',
                                    },
                                    {
                                        label: 'کسورات',
                                        data: payrollData.deductions,
                                        backgroundColor: '#f87171',
                                        borderRadius: 7,
                                        stack: 'payroll',
                                    },
                                ],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        align: 'end',
                                        labels: {
                                            color: theme.textColor,
                                            boxWidth: 10,
                                            boxHeight: 10,
                                            usePointStyle: true,
                                        },
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => `${context.dataset.label}: ${formatMoney(context.parsed.y)} ریال`,
                                        },
                                    },
                                },
                                scales: {
                                    x: {
                                        stacked: true,
                                        grid: {
                                            display: false,
                                        },
                                        ticks: {
                                            color: theme.mutedTextColor,
                                        },
                                    },
                                    y: {
                                        stacked: true,
                                        beginAtZero: true,
                                        grid: {
                                            color: theme.gridColor,
                                        },
                                        ticks: {
                                            color: theme.mutedTextColor,
                                            callback: (value) => formatMoney(value),
                                        },
                                    },
                                },
                            },
                        });
                    }

                    const attendanceCanvas = document.getElementById('attendanceDonutChart');
                    if (attendanceCanvas) {
                        attendanceChart?.destroy();
                        attendanceChart = new Chart(attendanceCanvas, {
                            type: 'doughnut',
                            data: {
                                labels: attendanceData.labels,
                                datasets: [{
                                    data: attendanceData.data,
                                    backgroundColor: ['#34d399', '#fbbf24', '#38bdf8', '#fb7185'],
                                    borderColor: 'transparent',
                                    hoverOffset: 3,
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '68%',
                                plugins: {
                                    legend: {
                                        display: false,
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => `${context.label}: ${formatMoney(context.parsed)}`,
                                        },
                                    },
                                },
                            },
                        });
                    }
                };

                renderCharts();
                window.addEventListener('theme:changed', renderCharts);
            });
        </script>
    @endpush
</x-app-layout>
