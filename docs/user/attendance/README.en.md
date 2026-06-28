# Attendance Guide

**[نسخه فارسی](README.md)**  
**[Back to user guide](../README.en.md)**

This guide explains Amir's attendance workflow: defining work shifts, recording or importing entry/exit logs, calculating monthly attendance, and preparing data for payroll.

## Main Parts

| Area | Purpose |
|---|---|
| Work shifts | Define start/end time, Thursday handling, floating entry window, break time, leave allowance, and overtime/mission coefficients |
| Attendance logs | Record daily entry and exit for each employee, make manual corrections, and review calculation details |
| Attendance import | Preview and import device or external files, with duplicate rows ignored or replaced |
| Monthly attendance | Summarize work days, presence, absence, overtime, mission, leave, Fridays, and holidays |
| Payroll creation | Create a payroll from confirmed monthly attendance and an active salary decree |

## Suggested Workflow

1. Create the work shifts you need from the Attendance menu.
2. Assign the correct work shift to each employee.
3. Register public holidays in Salary, because they affect attendance calculation.
4. Record attendance logs manually or import them from a file.
5. Review the import preview before confirming.
6. Calculate monthly attendance for each employee and month.
7. Recalculate a daily log or the whole month if source logs change.
8. After monthly attendance is confirmed, create payroll from the monthly attendance page.

## Work Shifts

Important work-shift fields include:

- Start and end time
- Thursday status: holiday, full day, or half day
- Thursday exit time for half-day Thursdays
- Floating entry window, meaning tolerated late arrival before penalty
- Break time
- Auto overtime limit and coefficient
- Holiday, overtime, mission, and undertime coefficients
- Paid leave allowance

Changing a shift's paid leave can affect the leave balance of employees assigned to that shift. Review active employees before changing live shifts.

## Daily Logs

An attendance log stores employee, date, entry time, exit time, and description. After calculation, these values can be viewed or corrected:

- Worked time
- Delay
- Early leave
- Overtime
- Auto overtime
- Mission
- Paid and unpaid leave

Editing a log marks it as manually corrected. Use recalculation when you want the system to apply the current rules again.

## Importing Logs

During import, choose the import type, date range, and duplicate behavior. Duplicate rows can be ignored or replaced. Before final import, review the preview page, especially unknown device IDs that are not mapped to employees. Please note that in both cases (keeping the existing record or replacing it with the new one), the check-in and check-out times in the log will be updated if their values are `NULL`.

## Monthly Attendance and Payroll

Monthly attendance is calculated for one employee, a start date, and a duration of 28 to 31 days. The monthly page also shows days without logs so absences and holidays can be reviewed.

After attendance is confirmed, and if the employee has an active salary decree, payroll can be created from the monthly attendance page. If attendance changes after payroll is created, review the payroll too.
