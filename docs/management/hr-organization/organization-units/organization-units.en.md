<div dir="ltr" lang="en">

# Organization Units Guide

**[Back to Human Resources](../../../hr/README.en.md)**

An organization unit groups employees by administrative or operational department, such as Finance, Sales, Human Resources, a factory, or the Tehran branch.

## Access

Menu path:

> Management → Human Resources and Organization → Organization Units

The list is scoped to the active fiscal year. Viewing, creating, editing, and deleting each require separate permissions.

## Organization unit versus organization chart

An organization unit names a department, whereas an organization chart defines reporting positions. For example, “Finance Department” is a unit and “Finance Manager” is a chart position. An employee can be linked to both.

## Create a unit

Choose **Add** and complete these fields:

| Field | Description |
|---|---|
| Name | Required; at most 150 characters |
| Code | Optional; at most 50 characters and unique per company |
| Parent unit | Optional; creates a multilevel hierarchy |
| Active | Determines whether the unit is currently active |
| Description | Optional explanation of the unit's responsibility or scope |

Without a parent, the unit is at the top level. For example, you could create “Finance Division” at the top and “Sales Accounting” beneath it.

## View and search

You can search the list by name or code and filter by active status. It also shows the number of employees linked to each unit. A unit's detail page shows:

- Its information and description;
- Its parent unit;
- Its direct child units;
- Its linked employees, including personnel codes and workplaces.

## Link an employee to a unit

Select the unit on the [employee](../../../hr/employees/employees.en.md) create or edit form. This link is optional, and an employee currently belongs to only one organization unit. Changing the employee's unit does not change their chart position or workplace. If an organizational transfer changes all of these, update each field separately.

## Edit and delete

You can change the name, code, parent, status, and description. A unit cannot be its own parent. Before deleting, open the unit detail page and check its employees and child units. Deactivating a unit is generally preferable to deleting it to retain history. If necessary, move employees and child units to their correct destinations first.

## Common errors

### Unit code is rejected

The code is duplicated within the same fiscal year or exceeds 50 characters. Search the list for that code.

### Unit is missing from the employee form

Check the active fiscal year and refresh the form. The unit must be in the same fiscal year as the employee record.

### The hierarchy is wrong

Edit the unit and select the correct parent. Then inspect its detail page to check the child units.

## Related guides

- [Employees](../../../hr/employees/employees.en.md)
- [Organization chart](../org-charts/org-charts.en.md)

</div>
