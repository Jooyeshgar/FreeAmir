{{-- Shared editable fields for MonthlyAttendance update --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

    <x-input name="work_days" id="work_days" type="number" title="{{ __('Work Days') }}" :value="old('work_days', $monthlyAttendance->work_days)" required />

    <x-input name="present_days" id="present_days" type="number" title="{{ __('Present Days') }}" :value="old('present_days', $monthlyAttendance->present_days)" required />

    <x-input name="absent_days" id="absent_days" type="number" title="{{ __('Absent Days') }}" :value="old('absent_days', $monthlyAttendance->absent_days)" required />

    <x-input name="overtime" id="overtime" type="number" title="{{ __('Overtime (min)') }}" :value="old('overtime', $monthlyAttendance->overtime)" hint="{{ __('Total overtime minutes') }}" required />

    <x-input name="mission_days" id="mission_days" type="number" title="{{ __('Mission Days') }}" :value="old('mission_days', $monthlyAttendance->mission_days)" required />

    <x-input name="paid_leave_days" id="paid_leave_days" type="number" title="{{ __('Paid Leave Days') }}" :value="old('paid_leave_days', $monthlyAttendance->paid_leave_days)" required />

    <x-input name="unpaid_leave_days" id="unpaid_leave_days" type="number" title="{{ __('Unpaid Leave Days') }}" :value="old('unpaid_leave_days', $monthlyAttendance->unpaid_leave_days)" required />

    <x-input name="friday" id="friday" type="number" title="{{ __('Friday Work (min)') }}" :value="old('friday', $monthlyAttendance->friday)" hint="{{ __('Total Friday worked minutes') }}"
        required />

    <x-input name="holiday" id="holiday" type="number" title="{{ __('Holiday Work (min)') }}" :value="old('holiday', $monthlyAttendance->holiday)"
        hint="{{ __('Total public holiday worked minutes') }}" required />

</div>
