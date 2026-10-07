# Users

Management → System → Users is intended for searching users and managing their fiscal-year access. User-management permission is required; ordinary managers see users and fiscal years within their authorized scope.

**Current limitation:** The controllers read and write assignments through the `fiscalYears` relationship and `fiscal_year_user` pivot, but the index, detail, create, and edit views still reference the old `companies` relationship and `fiscal_year` column. The user-management interface is not aligned with the refactor and cannot yet be relied on to assign or review fiscal-year access.

## Intended create and edit flow after the interface is updated

1. Search the list by name or email and, if needed, filter by email-verification status.
2. Once the form is updated, create a user by entering a name, unique email address, a password of at least eight characters, and its confirmation. Choose at least one role and one fiscal year.
3. After saving, the application sends an email-verification notification. If delivery fails, the user has still been created; follow up on sending the notification.
4. Open Edit to change details, roles, or fiscal years. A password is optional on edit; if supplied, it must be at least eight characters and confirmed. An employee can only be linked if that employee is not already linked to another user.

Roles determine operational permissions, while assignments in `fiscal_year_user` determine accessible data scope. Access to one year does not grant access to the company's other years. After the interface is updated, review saved assignments through the user's fiscal-year relationship. This operation does not itself create an accounting document.
