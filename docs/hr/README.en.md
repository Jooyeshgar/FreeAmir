# Human resources management guide

**[نسخه فارسی](README.md)**  
**[Back to the ordinary user guide](../user/README.en.md)**

This section is for HR managers, administrative staff, and support staff. It covers the management side of employee records, organizational structure, and personnel requests. Employees should use the [employee portal guide](../user/employee-portal.md) for their own records and requests.

## Guides in this section

The detailed guides are currently available in Persian:

| Guide | Topic |
|---|---|
| [Employee management](employees.md) | Employee records, search, export, and organizational assignments |
| [Organization units](organization-units.md) | Units, codes, parent units, active status, and assigned employees |
| [Organization chart](organization-chart.md) | Positions and reporting relationships |
| [Personnel requests](personnel-requests.md) | Manager-side entry, filtering, approval, rejection, and attendance effects |

Related English guides:

- [Attendance](../user/attendance/README.en.md)
- [Salary and payroll](../user/salary/README.en.md)

## Access and data scope

Each page requires authentication and the permission for the requested action. List, create, edit, delete, export, approve, and reject permissions are separate.

Employee records, units, chart positions, and requests are limited to the active company context. Check the active company and fiscal year before adding or changing records.

## Recommended setup order

1. Create the work site and, if needed, its contract framework in salary settings.
2. Create the work shift in attendance settings.
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
| Work site | The employee's workplace, maintained under salary settings |
| Work shift | The attendance schedule, working hours, and leave defaults |
| Contract framework | A work-site contract template maintained under salary settings |

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
| Change contact details | Own phone and address | Full employee record, subject to permission |
| Create an employee record | No | Yes |
| Submit a request | For the signed-in employee | For a permitted employee |
| Approve or reject a request | No | Yes, with the required permission |
| Define units and chart positions | No | Yes |
