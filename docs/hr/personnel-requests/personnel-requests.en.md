<div dir="ltr" lang="en">

# Managing Personnel Requests

**[Back to Human Resources](../README.en.md)**

This guide is for a user who registers or reviews employee requests in Human Resources. Employees registering their own requests should use the [Employee Portal guide](../../employee-portal/README.en.md).

## Access

Menu path:

> Human Resources → Personnel Requests

Listing, creating, editing, deleting, approving, and rejecting requests have separate permissions. A user may see requests without having an Approve or Create button. Requests are scoped to the active fiscal year. If one is missing, first check that year and the page filters.

## Request categories

The page has four tabs:

| Tab | Request types |
|---|---|
| Leave | Hourly, daily, sick, unpaid daily, and unpaid hourly leave |
| Missions | Hourly and daily missions |
| Work orders | Overtime orders and remote work |
| Other | Requests outside the above categories |

The number by each tab counts pending requests in that category.

## Search and review

Each tab can be filtered by employee and status. The statuses are **Pending**, **Approved**, and **Rejected**. Pending rows have a distinct color. The list shows request type, start and end, duration, status, and approver name.

## Register a request for an employee

1. Open the appropriate tab and choose **Add**.
2. Select the employee and request type.
3. Enter the request date.
4. For hourly requests, overtime orders, or remote work, enter start and end times in `HH:MM` format.
5. Add a reason or explanation if needed.
6. Choose **Register Request** or **Save and Create Another Request**.

End time must be at or after start time, and both must be valid times from 00:00 through 23:59. For daily leave, unpaid daily leave, and daily missions, the system takes that day's start and end times from the employee's work shift. Assigning the correct shift to the employee record therefore matters.

A new management-entered request is saved as **Pending**; its creator cannot bypass approval by creating it.

## Edit and delete

The application offers editing only for pending requests. The edit form allows changes to type, date, times, and reason. The Delete button is not shown for approved requests. Users with permission can delete pending or rejected requests from the list. Deletion cannot be undone in the application, so confirm the employee, date, and type first.

## Approve and reject

Find the request on its category tab:

- **Approve** appears for pending or rejected requests.
- **Reject** appears for pending or approved requests.
- The system retains the username of the person who made the latest decision.

A rejected request may be approved later, and an approved request may be rejected. Such changes affect dependent attendance logs, so review monthly attendance after changing a decision.

## Effect on attendance

On approval, the system synchronizes relevant data with attendance logs. Attendance calculations can include these approved requests in their respective columns:

- Paid and sick leave;
- Unpaid leave;
- Missions;
- Overtime;
- Remote work.

For a daily request, duration follows that day's shift hours. For an hourly request, it is based on the difference between start and end times. Rejecting an approved request runs reverse synchronization for its dependent logs. Monthly attendance and payslips are nevertheless separate outputs. After any change:

1. Review the day's attendance log.
2. Recalculate monthly attendance.
3. If payroll already exists, check the effect on the payslip and continue the payroll process according to its status.

Approval alone does not issue a new payslip automatically.

## Example

Suppose an employee requests a two-hour mission:

1. Open **Missions** and filter for the employee and Pending status.
2. Check the date, 10:00–12:00 time span, and reason.
3. Approve the request.
4. Open the same day's attendance log and check the mission amount.
5. Recalculate the corresponding month's attendance.
6. If that month's payslip already exists, review its payroll result separately.

## Common problems

### Approve or Reject is not shown

Check the account's `approve` or `reject` permission and the request's current status. Each button appears only when that transition is valid.

### Daily request times differ from what you expected

Daily requests use the employee's linked shift. Check the employee's shift and that weekday's settings.

### An approved request does not appear in monthly attendance

Check and, if necessary, recalculate the day's log. Then run the monthly attendance calculation. The fiscal year, year, month, and employee must match the request.

### The request is missing from a tab

Each type appears on its own tab. Clear employee and status filters and check the active fiscal year.

## Related guides

- [Employee Portal](../../employee-portal/README.en.md)
- [Employees](../employees/employees.en.md)
- [Attendance logs](../attendance-logs/attendance-logs.en.md)
- [Payroll](../payrolls/payrolls.en.md)

</div>
