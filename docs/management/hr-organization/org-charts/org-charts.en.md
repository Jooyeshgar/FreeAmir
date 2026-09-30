<div dir="ltr" lang="en">

# Organization Chart Guide

**[Back to Human Resources](../../../hr/README.en.md)**

The organization chart records positions and their reporting relationships. Each person on the chart has a position such as CEO, Finance Manager, or Accounting Specialist.

## Access

Menu path:

> Management → Human Resources and Organization → Organization Chart

Data is limited to the active fiscal year. Users need the corresponding permission to view, create, edit, or delete.

## Build the chart

It is best to start at the highest position:

1. Choose **Add**.
2. Enter the position title. It is required and limited to 200 characters.
3. For the top position, choose **No Superior Position**.
4. For other positions, select their superior position.
5. If needed, add a responsibility description or other details.
6. Save the form.

From a position's detail page, you can create a subordinate position; the current position is preselected as its superior.

## Example

```text
CEO
├── Finance Manager
│   ├── Accounting Supervisor
│   └── Treasurer
└── Sales Manager
    └── Sales Specialist
```

Each title is a separate position. If two units have positions with the same name, make the title more specific, such as “Tehran Branch Administration Specialist.”

## View and search

Search the list by position title; the direct superior is shown for each position. A position's detail page shows the entire organization chart and highlights the selected position.

## Link an employee to a position

Choose **Position in Organization Chart** on the [employee](../../../hr/employees/employees.en.md) create or edit form. The link is optional. Each employee currently has one chart position, but several employees can share one position. Define the position first, then link the employee record.

## Edit and delete

The title, superior position, and description can be edited. A position cannot be its own superior.

Deleting a position clears direct links from its employees and subordinate positions. Neither the employees nor subordinate positions are deleted, but their placement in the chart becomes incomplete. Before deleting, move employees to the correct position and assign new superiors to subordinate positions.

## Common errors

### Position is missing from the employee form

Check the active fiscal year and refresh the employee form after creating the position.

### Position appears in the wrong place

Edit it and select the correct superior. Leaving the superior blank puts the position at the chart root.

### The chart does not match organization units

These are separate structures. The chart describes relationships among positions; units group employees by department. Set each employee's links separately.

## Related guides

- [Organization units](../organization-units/organization-units.en.md)
- [Employees](../../../hr/employees/employees.en.md)

</div>
