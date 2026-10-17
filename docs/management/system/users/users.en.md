# Users

“Management → System → Users” is for searching users and managing their access to fiscal years. A user-management permission is required to access this section; ordinary managers see only users and fiscal years within their authorized scope.

In the create and edit forms, assign access by selecting one or more fiscal years. These assignments are stored in the `fiscal_year_user` table. The user detail page shows assigned fiscal years for review, and the list shows the number of fiscal years assigned to each user.

## Creating and editing users

1. Search the list by name or email. If needed, filter by email-verification status.
2. To create a user, enter a name, a unique email address, a password of at least eight characters, and its confirmation. Select at least one role and one fiscal year.
3. After saving, the application sends an email-verification notification. If sending fails, the user has still been created; follow up on sending the notification.
4. Open **Edit** to change the user's details, roles, or fiscal years. A password is optional when editing; if provided, it must be at least eight characters and confirmed. You can link an employee only if that employee is not already linked to another user.

Roles determine operational permissions, while assignments in `fiscal_year_user` define the data the user can access. Access to one fiscal year does not grant access to the company's other fiscal years. User and fiscal-year assignment changes do not create accounting documents.
