# Human resources management guide

**[نسخه فارسی](README.md)**  
**[Back to the ordinary user guide](../menu/documentation.en.md)**

This section is for HR managers, administrative staff, and support staff. It covers the management side of employee records, organizational structure, and personnel requests. Employees should use the [employee portal guide](../employee-portal/README.en.md) for their own records and requests.

## Guides in this section

In HR menu order: [Payroll Dashboard](dashboard/dashboard.en.md), [Payrolls](payrolls/payrolls.en.md), [Employees](employees/employees.en.md), [Personnel Requests](personnel-requests/personnel-requests.en.md), [Attendance Logs](attendance-logs/attendance-logs.en.md), [Monthly Attendances](monthly-attendances/monthly-attendances.en.md).


| Guide | Topic |
|---|---|
| [Employee management](employees/employees.en.md) | Employee records, search, export, and organizational assignments |
| [Organization units](../management/hr-organization/organization-units/organization-units.en.md) | Units, codes, parent units, active status, and assigned employees |
| [Organization chart](../management/hr-organization/org-charts/org-charts.en.md) | Positions and reporting relationships |
| [Personnel requests](personnel-requests/personnel-requests.en.md) | Manager-side entry, filtering, approval, rejection, and attendance effects |

Related English guides:

- [Attendance](attendance-logs/attendance-logs.en.md)
- [Salary and payroll](payrolls/payrolls.en.md)

## Access and data scope

Each page requires authentication and the permission for the requested action. List, create, edit, delete, export, approve, and reject permissions are separate.

Employee records, units, chart positions, and requests are limited to the active company context. Check the active company and fiscal year before adding or changing records.

## Recommended setup order

1. Create the work site and, if needed, its contract framework in Management → HR & Organization.
2. Create the work shift in Management → HR & Organization.
3. Define organization units.
4. Build the organization chart.
5. Add employees and assign each employee to a work site and shift.
6. Create salary decrees, then proceed with attendance, monthly attendance, and payroll.

Work site and work shift are required on the employee form. Organization unit, chart position, and contract framework are optional.

## Terms that look similar

| Term | Use |
|---|---|
| Organization unit | An administrative or operational group, such as Finance or Tehran Branch |
| Organization chart position | A reporting position, such as Finance Manager or Accountant |
| Work site | The employee's workplace, maintained under Management → HR & Organization |
| Work shift | The attendance schedule, working hours, and leave defaults |
| Contract framework | A work-site contract template maintained under Management → HR & Organization |

These fields are independent. An employee can have a unit, chart position, work site, work shift, and contract framework at the same time.

## Typical data flow

```text
Organization unit and position + work site and shift
                             ↓
                       Employee record
                             ↓
             Personnel requests and attendance logs
                             ↓
                     Monthly attendance
                             ↓
                          Payroll
```

Changing an earlier record does not always rebuild calculated records. After a shift, approved request, or attendance log changes, recalculate the affected month. Review payroll separately if it already exists.

## Difference with employee portal

The employee portal shows the signed-in employee's own profile, attendance, monthly attendance, payrolls, and requests. HR pages manage records for all employees permitted in the active company. Request approval, organizational setup, and full employee-record editing belong to the management pages.

| Action | Employee portal | HR management |
|---|---:|---:|
| View employee information | Own information | Permitted employee records |
| Change account name, email, password | Own account fields | Full employee record, subject to permission |
| Create an employee record | No | Yes |
| Submit a request | For the signed-in employee | For a permitted employee |
| Approve or reject a request | No | Yes, with the required permission |
| Define units and chart positions | No | Yes |
