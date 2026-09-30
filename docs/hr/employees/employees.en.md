<div dir="ltr" lang="en">

# Managing Employees

**[Back to Human Resources](../README.en.md)**

This guide covers creating and maintaining employee records. An employee record underpins links to the Employee Portal, attendance, monthly work calculations, employment rulings, and payslips.

## Access the employee list

Application menu path:

> Human Resources → Employees

The user needs permission to list employees. Add, Edit, Delete, and Export buttons appear only for users with the corresponding operation permission.

## Employee list

The page shows counts of all and active employees, full-time, contract or other employment, hires in the last 30 days, and employees without a salary ruling. An employee may be flagged for attention due to an expiring contract or missing active ruling.

You can search by first name, last name, personnel code, or national ID; filter active/inactive status, workplace, or contract framework; and export employees using the current filters.

## Prerequisites

Two records are required to create an employee:

1. A **workplace**, under Management → Human Resources and Organization → Workplaces;
2. A **work shift**, under Management → Human Resources and Organization → Work Shifts.

Organization unit, chart position, and contract framework are optional, but creating them before the employee record improves organizational structure and reporting.

## Create an employee record

Choose **Add Employee** from the list and complete the form.

### Identity

Personnel code, first name, last name, and nationality are primary fields. Personnel code is required and unique. A national ID, if supplied, must be exactly 10 characters and unique.

Other details include father's name, passport number, gender, marital status, number of children, birth date and place, and military-service status.

### Attendance device link

**Device ID** matches the employee to their attendance-device identifier and accepts at most 20 characters. If device data is imported by identifier, use the same value as on the device.

### Organization details

| Field | Status | Purpose |
|---|---|---|
| Workplace | Required | Service location and related payroll settings |
| Work shift | Required | Work hours, holidays, and attendance calculation |
| Chart position | Optional | Employee's place in the reporting hierarchy |
| Organization unit | Optional | Administrative or operational department |
| Contract framework | Optional | Workplace-specific contract template |

The **New** link beside an option opens its creation page in another tab. After creating an option, you may need to refresh the employee form.

### Contact, insurance, bank, and education

Contact data includes phone and address. Insurance, bank, account number, card number, IBAN, education level, and field of study are optional. Match these against the employee's official record because they appear on their personal page.

### Employment and contract

Enter employment type and contract start/end dates. End date cannot precede start date. **Active** determines whether the employee is currently considered employed.

Annual-leave balance is stored in minutes. When creating the record, the system takes its initial value from the selected shift; the edit form allows correction.

## View a record

**View** displays the employee's main data and recorded links. It also provides access to salary rulings, monthly attendance, payslips, workplace, shift, organization unit, and chart position. Missing attendance or payslips mean that no such record exists for that employee in the active fiscal year.

## Edit a record

You can change identity, contact, insurance, banking, employment, and organization fields. If the first or last name changes for an employee linked to a user account, that account's name is updated too.

Changing workplace or shift does not automatically recalculate prior monthly attendance. Check the effect from the intended date and rerun the monthly calculation if necessary.

## Link a user account to the employee

To enter the [Employee Portal](../../employee-portal/README.en.md), a user account must be linked to the employee record. Creating the record from the Employees page alone does not create that link.

An administrator can use User Management's create-employee action for a user who does not yet have a corresponding employee. Link each account to the correct person. A portal 403 error often means this link is missing.

## Delete or deactivate

Deleting an employee record can affect attendance, requests, monthly work, and payroll history. When employment ends, normally turn off **Active** and enter the contract end date to retain history. Delete only an erroneous or test record with no important dependencies.

## Common problems

### Workplace or shift is missing from the form

Create it in the relevant section first and check the active fiscal year.

### Duplicate personnel code or national ID

Search the list for that value. National ID must have 10 characters. Edit an existing employee rather than creating a second record.

### Employee cannot access the portal

A record alone is insufficient. Check the user–employee link, account activity, and company context.

### List or export differs from expectations

Clear search, status, workplace, and contract filters. Export uses the current filters.

## Related guides

- [Organization units](../../management/hr-organization/organization-units/organization-units.en.md)
- [Organization chart](../../management/hr-organization/org-charts/org-charts.en.md)
- [Attendance logs](../attendance-logs/attendance-logs.en.md)
- [Payroll](../payrolls/payrolls.en.md)

</div>
