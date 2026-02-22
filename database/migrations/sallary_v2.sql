

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- SECTION 1: Foundation / Lookup Tables
-- ============================================================

-- Organizational chart (self-referencing tree)
CREATE TABLE `org_charts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200) NOT NULL,
  `parent_id`   INT UNSIGNED DEFAULT NULL COMMENT 'NULL = root node',
  `description` TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_org_charts_parent` (`parent_id`),
  CONSTRAINT `fk_org_charts_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `org_charts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- work sites
CREATE TABLE `work_sites` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(200) NOT NULL,
  `code`              VARCHAR(20)  NOT NULL,
  `address`           TEXT DEFAULT NULL,
  `phone`             VARCHAR(50)  DEFAULT NULL,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_work_sites_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- contract frameworks / Peyman
CREATE TABLE `contract_frameworks` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(200) NOT NULL,
  `code`        VARCHAR(20)  NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_frameworks_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `work_site_contracts` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `work_site_id`          INT UNSIGNED NOT NULL,
  `contract_framework_id` INT UNSIGNED NOT NULL,
  `created_at`            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_work_site_contract` (`work_site_id`, `contract_framework_id`),
  KEY `fk_wsc_work_site`          (`work_site_id`),
  KEY `fk_wsc_contract_framework` (`contract_framework_id`),
  CONSTRAINT `fk_wsc_work_site`
    FOREIGN KEY (`work_site_id`)          REFERENCES `work_sites`          (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wsc_contract_framework`
    FOREIGN KEY (`contract_framework_id`) REFERENCES `contract_frameworks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Public holidays
CREATE TABLE `public_holidays` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `date`        DATE         NOT NULL,
  `name`        VARCHAR(200) NOT NULL,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_holidays_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 2: Employees
-- ============================================================

CREATE TABLE `employees` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`             VARCHAR(20)  NOT NULL COMMENT 'Personnel code',

  -- Identity
  `first_name`       VARCHAR(100) NOT NULL,
  `last_name`        VARCHAR(100) NOT NULL,
  `father_name`      VARCHAR(100) DEFAULT NULL,
  `national_code`    CHAR(10)     DEFAULT NULL,
  `passport_number`  VARCHAR(20)  DEFAULT NULL,

  `nationality`      ENUM('iranian', 'foreign')                              NOT NULL DEFAULT 'iranian',
  `gender`           ENUM('male', 'female')                                  DEFAULT NULL,
  `marital_status`   ENUM('single', 'married', 'divorced', 'widowed')        DEFAULT NULL,
  `children_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `birth_date`       DATE         DEFAULT NULL,
  `birth_place`      VARCHAR(100) DEFAULT NULL,
  `duty_status`      ENUM('liable', 'completed', 'in_progress', 'exempt')   DEFAULT NULL,

  -- Contact
  `phone`            VARCHAR(20)  DEFAULT NULL,
  `address`          TEXT         DEFAULT NULL,

  -- Insurance
  `insurance_number` VARCHAR(20)  DEFAULT NULL,
  `insurance_type`   ENUM('social_security', 'other')                        DEFAULT NULL,

  -- Banking
  `bank_name`        VARCHAR(100) DEFAULT NULL,
  `bank_account`     VARCHAR(50)  DEFAULT NULL,
  `card_number`      VARCHAR(20)  DEFAULT NULL,
  `shaba_number`     VARCHAR(30)  DEFAULT NULL,

  -- Education
  `education_level`  ENUM('below_diploma', 'diploma', 'associate', 'bachelor', 'master', 'phd') DEFAULT NULL,
  `field_of_study`   VARCHAR(100) DEFAULT NULL,

  -- Employment
  `employment_type`  ENUM('permanent', 'contract', 'other')                  DEFAULT NULL,
  `contract_start_date` DATE      DEFAULT NULL,
  `contract_end_date`   DATE      DEFAULT NULL,

  -- Org Relations
  `org_chart_id`          INT UNSIGNED DEFAULT NULL,
  `work_site_id`          INT UNSIGNED NOT NULL,
  `contract_framework_id` INT UNSIGNED DEFAULT NULL,

  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employees_code`          (`code`),
  UNIQUE KEY `uq_employees_national_code` (`national_code`),
  KEY `fk_employees_org_chart`          (`org_chart_id`),
  KEY `fk_employees_work_site`          (`work_site_id`),
  KEY `fk_employees_contract_framework` (`contract_framework_id`),
  CONSTRAINT `fk_employees_org_chart`
    FOREIGN KEY (`org_chart_id`)          REFERENCES `org_charts`          (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_employees_work_site`
    FOREIGN KEY (`work_site_id`)          REFERENCES `work_sites`          (`id`),
  CONSTRAINT `fk_employees_contract_framework`
    FOREIGN KEY (`contract_framework_id`) REFERENCES `contract_frameworks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 3: Payroll Elements (Earnings & Deductions)
-- ============================================================

CREATE TABLE `payroll_elements` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(100)  NOT NULL,
  `system_code`      ENUM(
                       'CHILD_ALLOWANCE',
                       'HOUSING_ALLOWANCE',
                       'FOOD_ALLOWANCE',
                       'MARRIAGE_ALLOWANCE',
                       'OVERTIME',
                       'FRIDAY_PAY',
                       'HOLIDAY_PAY',
                       'MISSION_PAY',
                       'INSURANCE_EMP',
                       'INSURANCE_EMP2',
                       'UNEMPLOYMENT_INS',
                       'INCOME_TAX',
                       'ABSENCE_DEDUCTION',
                       'OTHER'
                     ) NOT NULL DEFAULT 'OTHER',
  `category`         ENUM('earning', 'deduction')          NOT NULL,
  `calc_type`        ENUM('fixed', 'formula', 'percentage') NOT NULL,
  `formula`          VARCHAR(500) DEFAULT NULL,
  `default_amount`   DECIMAL(18,2) DEFAULT NULL,
  `is_taxable`       TINYINT(1)   NOT NULL DEFAULT 0,
  `is_insurable`     TINYINT(1)   NOT NULL DEFAULT 0,
  `show_in_payslip`  TINYINT(1)    NOT NULL DEFAULT 1,
  `is_system_locked` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Cannot be deleted if 1',
  `gl_account_code`  VARCHAR(50)  DEFAULT NULL,
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 4: Salary & Decrees
-- ============================================================

-- Salary decrees (employment orders) — historical, one employee may have many
CREATE TABLE `salary_decrees` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`          INT UNSIGNED NOT NULL,
  `org_chart_id`         INT UNSIGNED NOT NULL,
  `name`                 VARCHAR(200) DEFAULT NULL COMMENT 'Decree name or number',
  `start_date`           DATE NOT NULL,
  `end_date`             DATE DEFAULT NULL COMMENT 'NULL = currently active',
  `contract_type`        ENUM('full_time', 'part_time', 'hourly', 'shift') DEFAULT NULL,

  -- Base financials — snapshotted at decree issuance for audit integrity
  `daily_wage`           DECIMAL(18,2) DEFAULT NULL COMMENT 'مزد روزانه (base_salary / 30)',

  `description`          TEXT DEFAULT NULL,
  `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `fk_decrees_employee`  (`employee_id`),
  KEY `fk_decrees_org_chart` (`org_chart_id`),
  KEY `idx_decrees_dates`     (`start_date`, `end_date`),
  CONSTRAINT `fk_decrees_employee`
    FOREIGN KEY (`employee_id`)  REFERENCES `employees`  (`id`),
  CONSTRAINT `fk_decrees_org_chart`
    FOREIGN KEY (`org_chart_id`) REFERENCES `org_charts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extra/custom element overrides per decree (Many-to-Many with amount)
CREATE TABLE `decree_benefits` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `decree_id`     INT UNSIGNED NOT NULL,
  `element_id`    INT UNSIGNED NOT NULL,
  `element_value` DECIMAL(18,2) DEFAULT NULL COMMENT 'Override amount or percentage',
  `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_decree_element` (`decree_id`, `element_id`),
  KEY `fk_db_element` (`element_id`),
  CONSTRAINT `fk_db_decree`
    FOREIGN KEY (`decree_id`)  REFERENCES `salary_decrees`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_db_element`
    FOREIGN KEY (`element_id`) REFERENCES `payroll_elements` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 5: Attendance & Leave
-- ============================================================

-- Monthly attendance summary (computed/cached from attendance_logs)
CREATE TABLE `monthly_attendances` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`       INT UNSIGNED NOT NULL,
  `year`              SMALLINT UNSIGNED NOT NULL,
  `month`             TINYINT UNSIGNED  NOT NULL COMMENT '1-12',
  `work_days`         TINYINT UNSIGNED  NOT NULL DEFAULT 30,
  `present_days`      TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `absent_days`       TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `overtime_hours`    DECIMAL(5,2)      NOT NULL DEFAULT 0.00,
  `mission_days`      TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `paid_leave_days`   TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `unpaid_leave_days` TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `sick_leave_days`   TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `friday_hours`      DECIMAL(5,2)      NOT NULL DEFAULT 0.00 COMMENT 'Friday overtime hours',
  `holiday_hours`     DECIMAL(5,2)      NOT NULL DEFAULT 0.00 COMMENT 'Holiday work hours',
  `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monthly_attendance` (`employee_id`, `year`, `month`),
  KEY `idx_ma_year_month` (`year`, `month`),
  CONSTRAINT `fk_ma_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily clock-in / clock-out log
CREATE TABLE `attendance_logs` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`            INT UNSIGNED NOT NULL,
  `monthly_attendance_id`  INT UNSIGNED DEFAULT NULL COMMENT 'NULL = هنوز محاسبه نشده',
  `log_date`               DATE         NOT NULL,
  `entry_time`             TIME         DEFAULT NULL,
  `exit_time`              TIME         DEFAULT NULL,
  `log_type`               ENUM('normal', 'contract_overtime', 'mission') NOT NULL DEFAULT 'normal',
  `is_manual`              TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Manually corrected by operator',
  `description`            TEXT         DEFAULT NULL,
  `created_at`             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_logs_employee`           (`employee_id`),
  KEY `fk_logs_monthly_attendance` (`monthly_attendance_id`),
  KEY `idx_logs_date`              (`log_date`),
  CONSTRAINT `fk_logs_employee`
    FOREIGN KEY (`employee_id`)           REFERENCES `employees`           (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_logs_monthly_attendance`
    FOREIGN KEY (`monthly_attendance_id`) REFERENCES `monthly_attendances` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- personnel requests
CREATE TABLE `personnel_requests` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`      INT UNSIGNED NOT NULL,

  `request_type`     ENUM(
    'LEAVE_HOURLY',
    'LEAVE_DAILY',
    'SICK_LEAVE',
    'LEAVE_WITHOUT_PAY',
    'MISSION_HOURLY',
    'MISSION_DAILY',
    'OVERTIME_ORDER',
    'REMOTE_WORK',
    'OTHER'
  ) NOT NULL,

  `start_date`       DATETIME     NOT NULL,
  `end_date`         DATETIME     NOT NULL,
  `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 0,

  `reason`           TEXT         DEFAULT NULL,
  `status`           ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `approved_by`      INT UNSIGNED DEFAULT NULL COMMENT 'FK به employees',


  `payroll_id`       INT UNSIGNED DEFAULT NULL COMMENT 'NULL = هنوز در فیش حساب نشده',

  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `fk_pr_employee`   (`employee_id`),
  KEY `fk_pr_approved_by`(`approved_by`),
  KEY `fk_pr_payroll`    (`payroll_id`),
  CONSTRAINT `fk_pr_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_pr_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_payroll`
    FOREIGN KEY (`payroll_id`)  REFERENCES `payrolls`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 6: Tax
-- ============================================================

CREATE TABLE `tax_slabs` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year`             SMALLINT UNSIGNED NOT NULL,
  `slab_order`       TINYINT UNSIGNED  NOT NULL,
  `income_from`      DECIMAL(18,2)     NOT NULL,
  `income_to`        DECIMAL(18,2)     DEFAULT NULL COMMENT 'NULL = unlimited',
  `tax_rate`         DECIMAL(5,2)      NOT NULL,
  `annual_exemption` DECIMAL(18,2)     DEFAULT NULL,
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_slab` (`year`, `slab_order`),
  KEY `idx_tax_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 7: Payroll (Payslip Header + Line Items)
-- ============================================================

CREATE TABLE `payrolls` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`           INT UNSIGNED NOT NULL,
  `decree_id`             INT UNSIGNED DEFAULT NULL,
  `year`                  SMALLINT UNSIGNED NOT NULL,
  `month`                 TINYINT UNSIGNED  NOT NULL,
  `total_earnings`        DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_deductions`      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `net_payment`           DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `employer_insurance`    DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Employer share of social insurance (not in net, but stored for reporting)',
  `issue_date`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`                ENUM('draft', 'approved', 'paid') NOT NULL DEFAULT 'draft',
  `accounting_voucher_id` INT UNSIGNED  DEFAULT NULL COMMENT 'FK to external accounting module',
  `description`           TEXT          DEFAULT NULL,
  `created_at`            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_employee_month` (`employee_id`, `year`, `month`),
  KEY `fk_payrolls_employee` (`employee_id`),
  KEY `fk_payrolls_decree`   (`decree_id`),
  KEY `idx_payrolls_period`  (`year`, `month`),
  CONSTRAINT `fk_payrolls_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees`      (`id`),
  CONSTRAINT `fk_payrolls_decree`
    FOREIGN KEY (`decree_id`)   REFERENCES `salary_decrees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll line items (one row per element per payslip)
CREATE TABLE `payroll_items` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_id`        INT UNSIGNED NOT NULL,
  `element_id`        INT UNSIGNED NOT NULL,
  `calculated_amount` DECIMAL(18,2) NOT NULL,
  `unit_count`        DECIMAL(8,2)  DEFAULT NULL COMMENT 'e.g. overtime hours, absent days — for traceability',
  `unit_rate`         DECIMAL(18,2) DEFAULT NULL COMMENT 'Rate applied per unit — for traceability',
  `description`       VARCHAR(300)  DEFAULT NULL,
  `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_items_payroll` (`payroll_id`),
  KEY `fk_items_element` (`element_id`),
  CONSTRAINT `fk_items_payroll`
    FOREIGN KEY (`payroll_id`) REFERENCES `payrolls`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_element`
    FOREIGN KEY (`element_id`) REFERENCES `payroll_elements` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;