<x-app-layout :title="__('Bulk Attendance Log Creation')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('attendance.attendance-logs.bulk-store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Bulk Attendance Log Creation') }}</h2>
                <x-show-message-bags />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-date-picker name="start_date" id="start_date" title="{{ __('Start Date') }}" :value="old('start_date')" :hint="__('First day of the Jalali month (e.g. 1404/01/01)')" required />
                    </div>
                    <div>
                        <x-input name="duration" id="duration" type="number" title="{{ __('Month Duration (days)') }}" :value="old('duration', 30)" placeholder="29, 30 or 31"
                            hint="{{ __('Number of calendar days in this Jalali month (29–31)') }}" required />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 md:grid-cols-2 gap-4">
                    <div x-data="{
                        selectedValue: '',
                        selected: @js($preselected),
                        addEmployee(detail) {
                            if (!detail || !detail.id) return;
                            const id = parseInt(detail.id);
                            if (!this.selected.some(e => e.id === id)) {
                                this.selected.push({ id: id, name: detail.text });
                            }
                            this.$nextTick(() => { this.selectedValue = ''; });
                        },
                        remove(id) {
                            this.selected = this.selected.filter(e => e.id !== id);
                        },
                    }">
                        <span class="label">{{ __('Employees') }}</span>
                        <x-select-box url="{{ route('attendance.attendance-logs.search-employee') }}"
                            :options="[['headerGroup' => 'employee', 'options' => $employees]]" placeholder="{{ __('Select Employee') }}"
                            x-model="selectedValue" @selected="addEmployee($event.detail)" />
                        
                        <div class="flex flex-wrap gap-2 mt-3">
                            <template x-for="emp in selected" :key="emp.id">
                                <span class="badge badge-lg badge-outline text-xs">
                                    <span x-text="emp.name"></span>
                                    <button type="button" @click="remove(emp.id)" class="cursor-pointer text-error hover:text-error/70 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                    <x-input name="employee_ids[]" x-bind:value="emp.id" hidden />
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-checkbox title="{{ __('Override existing attendance logs') }}" name="override" id="override" value="1" checked="{{ old('override') ? 'checked' : '' }}" />
                    </div>
                </div>

                <div class="alert alert-info mt-4 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        {{ __('One attendance log is created per worked day for each selected employee using their shift times. Fridays and public holidays are always skipped. Thursdays follow the shift setting: Just skipped if Holiday.') }}
                    </span>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('attendance.attendance-logs.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create Logs') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
