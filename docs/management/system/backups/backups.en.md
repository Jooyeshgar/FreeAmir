# Download a backup

The intended purpose of Management → System → Backup is to download a ZIP of selected sections from one fiscal year. Each fiscal year belongs to a company, and selecting it should depend on the user's assignment to that year. Store the file securely: it may contain financial data and document attachments.

> **Current limitation:** The export service uses a fiscal-year ID, but the year-selection page still reads the old `companies` relationship. Backup downloads through the UI are not reliable until that selector is updated.

1. After the selector is updated, choose a fiscal year assigned to you under “Backup from,” then check its company and year before continuing.
2. Select the data sections you need. “Document Files” are exported only together with “Documents”; if Documents is not selected, Document Files are excluded as well.
3. Select “Create,” save the downloaded ZIP in a secure location, and record its name.

If you need to restore data, read the [upload-backup guide](../upload-backup/upload-backup.en.md) first. Downloading a backup does not change current data, but verify the file and its contents according to your organization’s recovery procedure.
