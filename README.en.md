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
* **Open-source:** Free to use, modify, and contribute to.

**Installation:**

Alternatively, you can use **[Laravel Sail](https://laravel.com/docs/sail)** for installation. If you choose Sail:
*   After step 3 (installing Composer dependencies), run the Composer install command again within Sail (`sail composer install`).
*   For subsequent steps (5-7, 10), prefix the `php artisan` commands with `sail` (e.g., `sail artisan key:generate`).
*   Similarly, prefix `npm` commands (8-9) with `sail` (e.g., `sail npm install`).
*   Refer to the official **[Sail documentation](https://laravel.com/docs/sail)** for more details.

1.  **Prerequisites:**
    *   PHP >= 8.1
    *   Composer
    *   MySQL database
    *   Node.js >= 18.0.0
2. **Clone the repository:**

```bash
git clone https://github.com/Jooyeshgar/FreeAmir.git
cd FreeAmir
```

3. **Install dependencies:**

```bash
composer install
```

4. **Copy `.env.example` to `.env` and configure database credentials.**

5. **Generate application key:**

```bash
php artisan key:generate
```

6. **Migrate the database:**

```bash
php artisan migrate
```

7. **Seed the database with sample data:**

```bash
php artisan db:seed
```

Optional: Seed demo data
```bash
php artisan db:seed --class DemoSeeder
```

8.  **Install npm packages:**

```bash
npm install
```

9.  **Run the Vite development server:**

```bash
npm run dev
```

10. **Start the development server:**

```bash
php artisan serve
```

**Database Migration:**

If you are migrating from the older version of Amir (based on SQLite), please refer to the [Database Migration Guide](script/README.md) for detailed instructions.

****Usage:**

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
