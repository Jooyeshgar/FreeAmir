## Amir: Free Laravel Accounting Software (فارسی)

**Introduction:**

Amir is a free and open-source accounting software written in Laravel, designed specifically for Iranian businesses and individuals (فارسی پشتیبانی). It aims to provide a user-friendly and comprehensive solution for managing finances, with features tailored to the specific needs of Iranian users, including support for Iranian tax regulations (مالیات).

**Features:**

* **Intuitive interface:** Easy to use for businesses of all sizes and technical expertise.
* **Multiple languages:** Currently supports Farsi (Persian) with potential for further language expansion.
* **Accounting functionalities:**
    * Manage income and expenses
    * Track invoices and receipts
    * Generate reports
    * Support for Iranian tax regulations (مالیات)
* **Open-source:** Free to use, modify, and contribute to.

**Installation:**

1. **Prerequisites:**
    * PHP >= 8.0
    * Composer
    * MySQL database
2. **Clone the repository:**

```bash
git clone git@github.com:Jooyeshgar/FreeAmir.git
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

7. **(Optional) Seed the database with sample data:**

```bash
php artisan db:seed
```

8. **Start the development server:**

```bash
php artisan serve
```

**Usage:**

1. Access the application in your web browser at http://localhost:8000 (or the port specified in your `.env` file).
2. Login with the default credentials (username: `admin`, password: `password`).
3. Explore the features and functionalities of the application.

**Contributing:**

We welcome contributions to the Amir project! Please refer to the CONTRIBUTING.md: CONTRIBUTING.md file for guidelines on how to submit bug reports, feature requests, and pull requests.

**License:**

This project is licensed under the MIT License. See the LICENSE: LICENSE file for details.

**Support:**

For any questions or issues, please feel free to create an issue on the GitHub repository.
