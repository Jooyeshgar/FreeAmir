# Attendance guide

**[نسخه فارسی](README.md)**  
**[Back to user guide](../README.en.md)**

This guide describes how Amir imports attendance logs from a device TSV file.

For daily calculations, monthly totals, and recalculation, read the [attendance log calculation guide](attendance-log-calculation.en.md).

## Before importing attendance logs

Complete these settings first:

1. Create the required work shifts.
2. Assign a work shift to each employee.
3. Set each employee's **Device ID** to the identifier used by the attendance device.
4. Register public holidays. They change how daily and monthly attendance is classified.
5. Check the shift's start and end times, Thursday status, floating entry window, and maximum automatic overtime.

If an employee has no work shift, daily calculation falls back to an 08:00 start, a 17:00 end, and 480 working minutes where the calculation needs a default duration.

## Importing a device TSV file

Open **Attendance > Attendance Logs > Import Attendance Logs** and select **Device TSV (tab-separated)**.

The uploaded file may be up to 10 MB. Each non-empty line must contain at least four tab-separated columns:

| Column | Meaning | Accepted value |
|---|---|---|
| 1 | Device ID | The value stored in the employee's **Device ID** field |
| 2 | Date and time | Gregorian `Y-m-d H:i:s`, for example `2026-09-05 08:03:00` |
| 3 | Ignored | Any value |
| 4 | Entry type | `0` for entry, `1` for exit, `2` to skip the row |
| 5 and later | Ignored | Any value |

Example:

```text
1042	2026-09-05 08:03:00	0	0	0	0
1042	2026-09-05 17:12:00	0	1	0	0
```

The importer treats any fourth-column value other than `0` or `2` as an exit. Use `0`, `1`, and `2` only, even though other values currently fall into the exit case.

### How rows become daily logs

The importer works in two passes:

1. It validates each line, resolves column 1 against employees in the active company, parses the Gregorian timestamp, and applies the optional date range.
2. It groups accepted rows by employee and Gregorian date, then creates or updates one attendance log for each employee and day.

Within one employee-day group, the last entry row in the file supplies **Entry Time**, and the last exit row supplies **Exit Time**. File order therefore matters when a device exports several entries or exits for the same day. The importer does not select the earliest entry or latest exit.

Rows with fewer than four columns, an invalid timestamp, type `2`, an unknown device ID, or a date outside the selected range are skipped. The final result counts skipped source rows and skipped employee-day groups together.

The preview shows no more than 20 accepted source rows. Its total is the number of accepted source rows, not the number of daily logs that will be created or updated. Unknown device IDs remain visible in the warning and are not imported.

### Duplicate mode

A duplicate is an existing attendance log with the same active company, employee, and date.

| Mode | Existing non-empty time | Existing empty time | Result when neither field changes |
|---|---|---|---|
| Ignore | Keep it | Fill it from the TSV when available | Skip the employee-day group |
| Replace | Overwrite it from the TSV when available | Fill it from the TSV | Skip only if the TSV supplies no usable entry or exit |

Ignore mode does not mean that the whole existing log is untouchable. It fills a missing **Entry Time** or **Exit Time**. Replace mode changes only times supplied by the file. A missing entry or exit in the TSV does not erase the existing value.

Every created or changed log is calculated immediately. A new imported log is marked as device-generated rather than manual. Updating an existing log does not change its current manual/device flag. Import does not rebuild an existing monthly attendance record. Read [attendance log calculation and recalculation](attendance-log-calculation.en.md) before updating an already calculated month.

## Checks after an import

- Compare the preview's unknown device IDs with employee **Device ID** values.
- Check employees who have several entry or exit rows on one date, because the last matching row wins.
- Review logs with only an entry or only an exit.
- Follow the [recalculation sequence](attendance-log-calculation.en.md#recalculation) if the month already has a monthly attendance record.
