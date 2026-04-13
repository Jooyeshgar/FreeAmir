## Amir: Free Laravel Accounting Software

**Important Notice:** Amir is currently under **active development** and is not yet ready for production use. We will announce the official release date soon. Stay tuned!

**Introduction:**

**Amir** is a free and open-source accounting software written in Laravel, designed specifically for Iranian businesses and individuals. It aims to provide a user-friendly and comprehensive solution for managing finances, with features tailored to the specific needs of Iranian users, including support for Iranian tax regulations.

**Features:**

* **Intuitive interface:** Easy to use for businesses of all sizes and technical expertise.
* **Multiple languages:** Currently supports Farsi (Persian) with potential for further language expansion.
* **Accounting functionalities:**
    * Manage income and expenses
    * Track invoices and receipts
    * Generate reports
    * Support for Iranian tax regulations
* **Warehousing (انبار داری):**
    * Manage products and inventory
    * Track stock levels and warehouse movements
    * Moving weighted average cost flow (perpetual inventory system)
* **Attendance (حضور و غیاب):**
    * Record employee check-in / check-out
    * Track daily and monthly attendance
    * Import attendance data from external sources
* **Payroll (حقوق و دستمزد):**
    * Calculate monthly salaries based on attendance
    * Manage deductions, bonuses, and benefits
    * Generate payslips and payroll reports
* **Open-source:** Free to use, modify, and contribute to.

**Installation:**

Full installation instructions are available in **[INSTALLATION.md](INSTALLATION.md)**, covering three options:

*   **[Option 1 — Production with Docker Compose (Recommended)](INSTALLATION.md#option-1-production--docker-compose-recommended):** Use pre-built images with no source code or build tools required.
*   **[Option 2 — All-in-One Docker (Testing only)](INSTALLATION.md#option-2-all-in-one--single-docker-command-testing-only):** Spin up everything in a single container for quick evaluation.
*   **[Option 3 — Standard Installation (PHP + MariaDB)](INSTALLATION.md#option-3-standard-installation--php--mariadb):** Install directly on your server or workstation using PHP, Composer, and npm.

**Usage:**

1.  Access the application in your web browser at http://localhost:8000 (or the port specified in your `.env` file).
2.  Log in with the default credentials (email: `admin@example.com`, password: `password`).
3.  Explore the features and functionalities of the application.


**Custom Artisan Commands:**

This project includes several custom Artisan commands to facilitate common tasks.

**Fiscal Year Management:**
    See [FiscalYearExportImport.md](FiscalYearExportImport.md) for more details.
    *   `fiscal-year:export`: Exports data from a specific fiscal year to a JSON file.
    *   `fiscal-year:import`: Imports fiscal year data from a JSON file into a new fiscal year.

**Contributing:**

We welcome contributions to the Amir project! Please refer to the [CONTRIBUTING.md](CONTRIBUTING.md) file for guidelines on how to submit bug reports, feature requests, and pull requests.

**License:**

This project is licensed under the GPL-3 License. See the [LICENSE](LICENSE) file for details.

**Support:**

For any questions or issues, please feel free to create an issue on the GitHub repository.
