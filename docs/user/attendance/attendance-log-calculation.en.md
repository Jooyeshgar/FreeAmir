# Attendance log calculation

**[نسخه فارسی](attendance-log-calculation.md)**  
**[Attendance import guide](README.en.md)**  
**[Back to user guide](../README.en.md)**

This guide explains how Amir calculates daily attendance logs, builds monthly totals, and recalculates stored results.

## Daily attendance values

The daily attendance row stores both source values and calculated values. Source values include employee, date, **Entry Time**, **Exit Time**, and approved personnel requests. Calculated values include **Worked**, **Delay**, **Early Leave**, **Overtime**, **Auto Overtime**, **Mission**, **Paid Leave**, **Unpaid Leave**, **Remote Work**, Friday status, and holiday status.

If an employee has no work shift, calculation falls back to an 08:00 start, a 17:00 end, and 480 working minutes where a default duration is required.

## Day and shift rules

- Friday and a registered public holiday are off-days.
- A Thursday marked as a holiday in the employee's shift is also an off-day.
- A half-day Thursday uses the shift's Thursday exit time.
- A full-day Thursday uses the normal shift end.
- Shift duration is the difference between its start and end times. The current calculation does not subtract the configured break value.

On an off-day, physical or remote minutes count as worked time. Delay, early leave, overtime, and automatic overtime are zero. Monthly calculation later moves worked time plus mission time into Friday work or holiday work.

## Clocked work, delay, and early leave

When both times exist, raw worked time is:

```text
Worked = Exit Time - Entry Time
```

A log with only one clock time has no complete clock interval. Without other approved coverage, worked time is zero.

The floating entry window changes the effective shift bounds:

- Entry within the floating window has no delay. The expected exit moves forward by the actual late-entry offset.
- Entry after the floating window produces delay only after that window. The expected exit moves forward by the full floating allowance.
- Entry before shift start contributes to possible automatic overtime.
- Exit before the adjusted expected exit produces early leave.
- Time outside the adjusted shift bounds is potential overtime.

### What "adjusted" means

“Adjusted” means that the employee has shifted one of the scheduled shift times (the start or end time) for that specific log. For example, the employee may have been late. This does not modify the work-shift information and only applies when calculating that specific employee and date.

The **adjusted expected exit** is the time by which the employee must leave after Amir applies the floating-entry rule:

- With a 08:00 to 16:00 shift and a 60-minute float, entry at 08:20 moves the expected exit to 16:20. There is no delay.
- With the same shift, entry at 09:15 is 15 minutes beyond the float. Amir records 15 minutes of delay and moves the expected exit by the full float to 17:00.
- If approved hourly leave starts at 08:00 and reaches the employee's entry time, it covers that entry gap. The expected exit stays at 16:00 instead of moving forward.
- If that leave ends before entry, only the uncovered gap can move the expected exit, up to the float limit. Leave ending at 10:00 followed by entry at 10:20 moves a 16:00 expected exit to 16:20.

The phrase **adjusted shift bounds** refers to the start and end times used after these rules. Amir compares entry and exit with those calculated bounds to determine delay, early leave, and potential overtime.

## Approved personnel requests

Recalculation reloads approved requests that cover the log date. It rebuilds request-backed values for paid leave, unpaid leave, mission, overtime orders, and remote work. A manually stored value remains when there is no approved request of that type.

Daily paid leave, daily unpaid leave, sick leave, and daily mission use the shift duration for that date. Hourly requests use their exact start-to-end duration.

Hourly leave and mission interact with clock time according to their position:

- Coverage before entry reduces delay.
- Coverage after exit reduces early leave.
- Coverage inside the clocked interval is subtracted from worked time so it is not counted as both work and leave or mission.
- Coverage is capped at the shift duration when it offsets delay and early leave.

For example, with a shift of 08:00 to 16:00, a clock interval of 08:00 to 16:00, and approved hourly leave from 11:00 to 13:00, the result is 360 worked minutes and 120 paid-leave minutes.

An approved remote-work request contributes real work rather than leave coverage. The calculator merges its time window with the office clock interval and removes overlaps. For example, office work from 09:00 to 12:00 and remote work from 10:00 to 15:00 produces 360 worked minutes, not 480. The combined earliest start and latest end determine delay, early leave, and potential overtime.

## Overtime

Time before the effective shift start or after the effective shift end is potential overtime.

- **Overtime** is limited to both the approved overtime-order minutes stored on the log and the potential overtime found from the clock times.
- Remaining potential overtime becomes **Auto Overtime**, up to the shift's maximum automatic overtime.
- Remote work beyond the shift can also become automatic overtime, subject to the same cap.

## Monthly attendance calculation

Monthly attendance covers one employee, a Jalali year and month label, a Gregorian start date, and 28 to 31 calendar days. It reads the calculated daily columns as stored. It does not recalculate each daily log first.

For every calendar day in the range:

- **Work Days** increases, including Fridays and holidays.
- A regular day without a log increases **Absent Days**.
- An off-day without a log is not an absence.
- A day with a log increases **Present Days**, except a regular day covered by a full shift of unpaid leave.
- Full-shift unpaid leave increases **Unpaid Leave Days** and does not increase **Present Days**.
- On regular days, monthly overtime, automatic overtime, paid leave, unpaid leave, and undertime come from the daily logs. Undertime is daily delay plus early leave.
- Mission and remote-work minutes are accumulated from every logged day.
- On an off-day, worked plus mission minutes become Friday work when the day is Friday. Otherwise they become holiday work.

The calculation creates or updates the employee's monthly record for that Jalali year and month, then links all daily logs in the selected date range to it.

## Recalculation

Amir has three related actions. They do different jobs.

| Action | What it recalculates | What it does not recalculate |
|---|---|---|
| **Recalculate** on a daily log | Reloads current shift, holiday, and approved-request data, recomputes the daily calculated columns, and overwrites their stored values | Monthly totals and payroll |
| **Recalculate All** on a monthly attendance page | Runs daily recalculation for every log currently linked to that monthly record | Days without logs, monthly totals, leave balance, and payroll |
| **Recalculate from Logs** on a monthly attendance record | Deletes and recreates the monthly summary from the stored daily log values, using the submitted start date and duration | Daily log calculations and payroll |

Use this sequence after importing or correcting logs for an already calculated month:

1. Run **Recalculate from Logs** so the monthly summary reads the imported or corrected daily values and links newly imported logs in the date range.
2. If shift settings, holidays, or approved personnel requests also changed, run **Recalculate All**, then run **Recalculate from Logs** once more. The importer itself has already recalculated logs that it created or changed.
3. Review the monthly totals and leave balance.
4. If payroll already exists, review or regenerate it separately. Attendance recalculation does not update payroll.

There are two limits to keep in mind. **Recalculate All** touches only logs already linked to the monthly record, so newly imported logs may not be included in that set. **Recalculate from Logs** queries all logs in its submitted date range and relinks them, so it is the step that brings those new logs into the monthly record. Also, manually edited calculated fields are overwritten when the corresponding daily log is recalculated.

## Calculation examples from the feature tests

These examples come from the attendance calculation cases in `tests/Feature/AttendanceLogTest.php`. All values are minutes unless a clock time is shown. The configured 30-minute break is listed because it is part of the test shift, but the current calculator does not subtract it from **Worked**.

### Import calculates a complete clock interval

Test: `test_import_recalculates_each_created_attendance_log`

| Shift Start | Shift End | Break | Float | Entry Time | Exit Time | Worked | Delay | Early Leave |
|---|---|---:|---:|---|---|---:|---:|---:|
| 08:00 | 17:00 | 60 | 0 | 08:00 | 17:00 | 540 | 0 | 0 |

The importer saves the two clock times and recalculates the log. **Worked** is 540 because the current calculation uses the complete 08:00 to 17:00 interval without subtracting the break.

### Late entry with leave after the shift

Test: `test_import_calculates_times_correctly_with_late_entry_overtime_and_hourly_leave_after_shift`

Every row below uses a 07:30 to 15:30 shift, a 30-minute break, a 60-minute float, and a 60-minute maximum automatic overtime.

| Entry Time | Exit Time | Approved Hourly Leave | Paid Leave | Delay | Overtime + Auto Overtime |
|---|---|---|---:|---:|---:|
| 08:45 | 16:45 | 16:45 to 17:00 | 15 | 15 | 30 |
| 08:52 | 16:30 | 16:30 to 17:00 | 30 | 22 | 30 |
| 08:58 | 16:38 | 16:32 to 17:00 | 28 | 28 | 30 |

Entry after the 60-minute float produces delay. Approved leave after physical exit covers the edge gap, while time beyond the adjusted shift end contributes to overtime.

### Leave starts at the shift boundary

Tests: `test_recalculation_applies_hourly_leave_until_entry_and_calculates_overtime` and `test_hourly_leave_at_shift_start_with_overtime_calculation`

| Shift | Float | Max Auto Overtime | Approved Hourly Leave | Entry Time | Exit Time | Paid Leave | Delay | Early Leave | Overtime + Auto Overtime |
|---|---:|---:|---|---|---|---:|---:|---:|---:|
| 08:00 to 16:00 | 60 | 60 | 08:00 to 10:02 | 10:02 | 16:28 | 122 | 0 | 0 | 28 |
| 07:30 to 15:30 | 60 | 120 | 07:30 to 10:35 | 10:35 | 16:38 | 185 | 0 | 0 | 68 |

Leave that starts at shift start and reaches entry covers the entry gap. The scheduled shift end does not slide in this case, so work after that end remains eligible for automatic overtime.

### Leave ends before entry and the remaining gap uses float

Test: `test_hourly_leave_before_entry_uses_remaining_float_without_early_leave`

| Shift | Float | Approved Hourly Leave | Entry Time | Exit Time | Paid Leave | Delay | Early Leave | Overtime + Auto Overtime |
|---|---:|---|---|---|---:|---:|---:|---:|
| 07:30 to 15:30 | 60 | 07:30 to 11:19 | 11:52 | 16:03 | 229 | 0 | 0 | 27 |

The leave covers 229 minutes. The remaining 33-minute gap before entry fits inside the 60-minute float, so it does not become delay. The expected exit moves by those 33 minutes to 16:03. Part of the clock interval overlaps the leave boundary and becomes 27 minutes of overtime.

### Hourly leave inside the clocked interval

Test: `test_hourly_leave_within_shift_with_float_calculates_paid_leave_and_no_delay`

| Shift | Float | Approved Hourly Leave | Entry Time | Exit Time | Paid Leave | Delay | Early Leave | Overtime + Auto Overtime |
|---|---:|---|---|---|---:|---:|---:|---:|
| 07:30 to 15:30 | 60 | 08:15 to 08:47 and 10:20 to 11:00 | 08:47 | 16:15 | 72 | 0 | 0 | 0 |

The two requests total 72 minutes. The first request covers the entry gap. The second overlaps the clocked interval and is excluded from worked time, which prevents the same minutes from counting as both work and paid leave.

### Leave inside the float period

Test: `test_hourly_leave_with_float_covers_delay_without_overtime`

| Shift | Float | Approved Hourly Leave | Entry Time | Exit Time | Paid Leave | Delay | Early Leave | Overtime + Auto Overtime |
|---|---:|---|---|---|---:|---:|---:|---:|
| 07:30 to 15:30 | 60 | 08:13 to 09:00 | 09:00 | 16:13 | 47 | 0 | 0 | 0 |

The first 43 minutes after shift start consume float, and the following 47 minutes are approved leave up to entry. The expected exit becomes 16:13. Exit at that time produces neither early leave nor overtime.

The four matching `test_recalculate_*` cases repeat the last four scenarios through the daily **Recalculate** endpoint. They verify that recalculation rebuilds the same paid-leave, delay, early-leave, and overtime values from stored clock times and approved requests.
