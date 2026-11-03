# فهرست مبنای انتقال منو — ایشو #453

این سند خروجی مرحلهٔ اول است: ثبت رفتار فعلی برای مقایسه هنگام انتقال منوی هسته به رجیستری مشترک. شناسه‌های `N…` و `G…` فقط ارجاع‌های این فهرست‌اند و API عمومی یا نام نهایی parentها نیستند.

این فهرست بر اساس برنچ ترکیبی قبلی تهیه شده است. ارجاع‌های تاریخی به `ExtensionNavigation`،
قالب آن و اتصال‌های کتگوری، توصیف همان مبنا هستند؛ این فایل‌ها جزو برنچ مستقل #453 نیستند.
رجیستری و renderer مشترک در این برنچ، ثبت‌های آرایه‌ای قدیمی را نیز پشتیبانی می‌کنند.

- تاریخ بررسی: 2026-11-01
- مبنای کد: `bff3c2dee506d661706aca770541fd049ae7e2fa`
- منبع: `resources/views/components/menu.blade.php`
- SHA-256 فایل منبع: `694ff68716660713963938d84064d98980d2042af961ce9e48fa85b0e3d16c61`
- محدوده: منوی مشترک `x-menu` در نوار دسکتاپ و drawer موبایل. منوی کاربر، انتخاب شرکت/سال مالی، تغییر زبان و پوسته و منوی مستقل پنل مدیریت در فایل‌های دیگر قرار دارند؛ مرز این مرحله ثبت و بررسی شده است.
- روش بررسی: استخراج ترتیب، والد، کلید ترجمه، route، پارامترهای ثابت و تمام guardهای محصورکننده از منبع؛ تطبیق مستقل تعدادها و بازبینی کد اطراف. اعتبارسنجی رفتاری و مرورگر در بخش وضعیت checkpoint ثبت شده است.

## شمارش و ترتیب

| مورد | تعداد |
|---|---:|
| لینک‌های هسته، شامل Home | 70 |
| گروه‌های هسته | 14 |
| ورودی‌های سطح اول، شامل Home | 9 |
| جایگاه‌های درج اکستنشن | 2 |
| لینک‌های دارای پارامتر ثابت | 8 |

ترتیب سطح اول: `Home`، `My Portal`، `Invoices`، `Accounting`، `Reports`، `Warehouse`، `HR`، `CRM`، `Management`.

در جدول‌ها، ترتیب نسبت به خواهر/برادرهاست؛ محل اکستنشن هم یک جایگاه شمرده شده است. guardهای ذکرشده شرط مؤثر فعلی‌اند: تمام guardهای یک ردیف باید برقرار باشند و هر guard از نوع `any` با برقرار بودن یکی از permissionهایش برقرار می‌شود. تکرار یک شرط یکسان در nesting برای خوانایی حذف شده است. `—` یعنی شرط permission در همین قالب وجود ندارد؛ authorization مسیر همچنان مستقل است.

## گروه‌ها

| ارجاع | والد | ترتیب | کلید ترجمه | guardهای مؤثر | خط منبع |
|---|---|---:|---|---|---:|
| N002 | root | 2 | `My Portal` | G02 | 13 |
| N009 | root | 3 | `Invoices` | G03 | 29 |
| N011 | N009 | 2 | `Sales` | G03, G05 | 37 |
| N016 | N009 | 3 | `Purchases` | G03, G06 | 60 |
| N025 | root | 4 | `Accounting` | G10 | 98 |
| N029 | root | 5 | `Reports` | G14 | 117 |
| N034 | N029 | 5 | `Accounting` | G14, G19 | 135 |
| N041 | root | 6 | `Warehouse` | G26 | 167 |
| N048 | root | 7 | `HR` | G33 | 196 |
| N055 | root | 8 | `CRM` | G40 | 224 |
| N059 | root | 9 | `Management` | — | 242 |
| N060 | N059 | 1 | `HR & Organization` | G44 | 252 |
| N071 | N059 | 2 | `Finance` | G54 | 299 |
| N077 | N059 | 3 | `System` | G60 | 326 |

## لینک‌ها

کلیدها عیناً از کد فعلی ثبت شده‌اند تا ترجمهٔ فارسی و انگلیسی و عبارت‌های موجود حفظ شوند. route برابر `/` تنها لینک فعلی بدون استفاده از helper است؛ route نام‌دار معادل آن `home` در `routes/web.php:84` موجود است.

| ارجاع | والد | ترتیب | کلید ترجمه | route فعلی | پارامتر | guardهای مؤثر | خط |
|---|---|---:|---|---|---|---|---:|
| N001 | root | 1 | `Home` | `/` | — | G01 | 8 |
| N003 | N002 | 1 | `Dashboard` | `employee-portal.dashboard` | — | G02 | 16 |
| N004 | N002 | 2 | `My Attendance` | `employee-portal.attendance-logs` | — | G02 | 17 |
| N005 | N002 | 3 | `Monthly Attendance` | `employee-portal.monthly-attendances` | — | G02 | 18 |
| N006 | N002 | 4 | `My Payrolls` | `employee-portal.payrolls` | — | G02 | 19 |
| N007 | N002 | 5 | `My Requests` | `employee-portal.personnel-requests.index` | — | G02 | 20 |
| N008 | N002 | 6 | `My Information` | `employee-portal.employee.show` | — | G02 | 21 |
| N010 | N009 | 1 | `Invoice Dashboard` | `invoices.dashboard` | — | G03, G04 | 33 |
| N012 | N011 | 1 | `Sell List` | `invoices.index` | P01 | G03, G05 | 41 |
| N013 | N011 | 2 | `Return Sell List` | `invoices.index` | P02 | G03, G05 | 44 |
| N014 | N011 | 3 | `Voided Sell` | `invoices.index` | P03 | G03, G05 | 47 |
| N015 | N011 | 4 | `Moadian Histories` | `invoices.moadian-histories.index` | — | G03, G05 | 50 |
| N017 | N016 | 1 | `Buy List` | `invoices.index` | P04 | G03, G06 | 63 |
| N018 | N016 | 2 | `Buy Service` | `invoices.index` | P05 | G03, G06 | 66 |
| N019 | N016 | 3 | `Return Buy List` | `invoices.index` | P06 | G03, G06 | 69 |
| N020 | N016 | 4 | `Service Buy Return` | `invoices.index` | P07 | G03, G06 | 72 |
| N021 | N016 | 5 | `Ancillary Cost List` | `ancillary-costs.index` | — | G03, G06, G07 | 76 |
| N022 | N009 | 4 | `Beginning Inventory` | `invoices.index` | P08 | G03, G06 | 81 |
| N023 | N009 | 5 | `Activate Confirmed Invoices` | `invoices.inactive` | — | G03, G08 | 86 |
| N024 | N009 | 6 | `Add Customer` | `customers.create` | — | G03, G09 | 89 |
| N026 | N025 | 1 | `Create Document` | `documents.create` | — | G10, G11 | 102 |
| N027 | N025 | 2 | `Document List` | `documents.index` | — | G10, G12 | 105 |
| N028 | N025 | 3 | `Cheque Management` | `cheques.index` | — | G10, G13 | 108 |
| N030 | N029 | 1 | `Company overview` | `reports.company-overview` | — | G14, G15 | 121 |
| N031 | N029 | 2 | `Cost and Income Dashboard` | `reports.cost-income` | — | G14, G16 | 124 |
| N032 | N029 | 3 | `Monthly Income and Expense Workbench` | `budgets.index` | — | G14, G17 | 127 |
| N033 | N029 | 4 | `Commercial Ledgers` | `commercial-ledgers.index` | — | G14, G18 | 130 |
| N035 | N034 | 1 | `Documents Report` | `reports.documents` | — | G14, G19, G20 | 139 |
| N036 | N034 | 2 | `Journal` | `reports.journal` | — | G14, G19, G21 | 142 |
| N037 | N034 | 3 | `Ledger` | `reports.ledger` | — | G14, G19, G22 | 145 |
| N038 | N034 | 4 | `Sub Ledger` | `reports.sub-ledger` | — | G14, G19, G23 | 148 |
| N039 | N034 | 5 | `Trial Balance` | `reports.trial-balance` | — | G14, G19, G24 | 151 |
| N040 | N034 | 6 | `Inventory Turnover` | `reports.inventory-turnover` | — | G14, G19, G25 | 154 |
| N042 | N041 | 1 | `Warehouse Dashboard` | `warehouse.dashboard` | — | G26, G27 | 171 |
| N043 | N041 | 2 | `Warehouses` | `warehouses.index` | — | G26, G28 | 174 |
| N044 | N041 | 3 | `Products` | `products.index` | — | G26, G29 | 177 |
| N045 | N041 | 4 | `Product Groups` | `product-groups.index` | — | G26, G30 | 180 |
| N046 | N041 | 5 | `Services` | `services.index` | — | G26, G31 | 183 |
| N047 | N041 | 6 | `Service Groups` | `service-groups.index` | — | G26, G32 | 186 |
| N049 | N048 | 1 | `Payroll Dashboard` | `salary.payrolls.dashboard` | — | G33, G34 | 200 |
| N050 | N048 | 2 | `Payrolls` | `salary.payrolls.index` | — | G33, G35 | 203 |
| N051 | N048 | 3 | `Employees` | `hr.employees.index` | — | G33, G36 | 206 |
| N052 | N048 | 4 | `Personnel Requests` | `hr.personnel-requests.index` | — | G33, G37 | 209 |
| N053 | N048 | 5 | `Attendance Logs` | `attendance.attendance-logs.index` | — | G33, G38 | 212 |
| N054 | N048 | 6 | `Monthly Attendances` | `attendance.monthly-attendances.index` | — | G33, G39 | 215 |
| N056 | N055 | 1 | `CRM Dashboard` | `crm.dashboard` | — | G40, G41 | 228 |
| N057 | N055 | 2 | `Customers` | `customers.index` | — | G40, G42 | 231 |
| N058 | N055 | 3 | `Customer Groups` | `customer-groups.index` | — | G40, G43 | 234 |
| N061 | N060 | 1 | `Organization Chart` | `hr.org-charts.index` | — | G44, G45 | 256 |
| N062 | N060 | 2 | `Organization Units` | `hr.organization-units.index` | — | G44, G46 | 259 |
| N063 | N060 | 3 | `Work Shifts` | `attendance.work-shifts.index` | — | G44, G47 | 263 |
| N064 | N060 | 4 | `Payrolls` | `salary.payrolls.index` | — | G44, G35 | 266 |
| N065 | N060 | 5 | `Yearly Tax Slabs` | `salary.tax-slabs.index` | — | G44, G48 | 269 |
| N066 | N060 | 6 | `Work Sites` | `salary.work-sites.index` | — | G44, G49 | 272 |
| N067 | N060 | 7 | `Work Site Contracts` | `salary.work-site-contracts.index` | — | G44, G50 | 275 |
| N068 | N060 | 8 | `Public Holidays` | `salary.public-holidays.index` | — | G44, G51 | 280 |
| N069 | N060 | 9 | `Payroll Elements` | `salary.payroll-elements.index` | — | G44, G52 | 284 |
| N070 | N060 | 10 | `Salary Decrees` | `salary.salary-decrees.index` | — | G44, G53 | 288 |
| N072 | N071 | 1 | `Subjects` | `subjects.index` | — | G54, G55 | 303 |
| N073 | N071 | 2 | `Bank Accounts` | `bank-accounts.index` | — | G54, G56 | 306 |
| N074 | N071 | 3 | `Banks` | `banks.index` | — | G54, G57 | 309 |
| N075 | N071 | 4 | `Chequebooks` | `chequebooks.index` | — | G54, G58 | 312 |
| N076 | N071 | 5 | `Sort Documents Number` | `documents.sort-numbers` | — | G54, G59 | 315 |
| N078 | N077 | 1 | `Companies` | `companies.index` | — | G60, G61 | 330 |
| N079 | N077 | 2 | `Fiscal years` | `fiscal-years.index` | — | G60, G62 | 333 |
| N080 | N077 | 3 | `Users` | `users.index` | — | G60, G63 | 336 |
| N081 | N077 | 4 | `Configs` | `configs.index` | — | G60, G64 | 339 |
| N082 | N077 | 5 | `Backup` | `backups.create` | — | G60, G65 | 342 |
| N083 | N077 | 6 | `Upload Backup` | `backups.upload` | — | G60, G65 | 343 |
| N084 | N059 | 5 | `about.about` | `about` | — | — | 352 |

## پارامترهای ثابت route

افزودن `routeParameters` به `MenuItem` در همین گفتگو تأیید شده است. تمام این لینک‌ها route مشترک `invoices.index` دارند. پارامترهای زیر هنگام ثبت تعیین می‌شوند؛ نام route از request گرفته نمی‌شود.

مقادیر جدول رشته‌اند؛ به‌ویژه مقدار فعلی `service_buy` برابر رشتهٔ `'1'` است و برای ثبت مبنای دقیق، نوع آن هم حفظ شده است.

| ارجاع | پارامترها | خط |
|---|---|---:|
| P01 | `{"invoice_type": "sell"}` | 41 |
| P02 | `{"invoice_type": "return_sell"}` | 44 |
| P03 | `{"invoice_type": "void"}` | 47 |
| P04 | `{"invoice_type": "buy"}` | 63 |
| P05 | `{"invoice_type": "buy", "service_buy": "1"}` | 66 |
| P06 | `{"invoice_type": "return_buy"}` | 69 |
| P07 | `{"invoice_type": "return_buy", "service_buy": "1"}` | 72 |
| P08 | `{"invoice_type": "beginning_inventory"}` | 81 |

## تعریف guardها

| ارجاع | نوع | permissionها به ترتیب فعلی | اولین خط |
|---|---|---|---:|
| G01 | `can` | `home.*` | 7 |
| G02 | `can` | `employee-portal.dashboard` | 11 |
| G03 | `any` | `invoices.dashboard`, `invoices.index`, `invoices.inactive`, `customers.create`, `ancillary-costs.index`, `invoices.moadian-histories.index` | 26 |
| G04 | `can` | `invoices.dashboard` | 32 |
| G05 | `can` | `invoices.index` | 35 |
| G06 | `any` | `invoices.index`, `ancillary-costs.index` | 58 |
| G07 | `can` | `ancillary-costs.index` | 75 |
| G08 | `can` | `invoices.inactive` | 85 |
| G09 | `can` | `customers.create` | 88 |
| G10 | `any` | `documents.index`, `documents.create` | 96 |
| G11 | `can` | `documents.create` | 101 |
| G12 | `can` | `documents.index` | 104 |
| G13 | `can` | `cheques.index` | 107 |
| G14 | `any` | `reports.documents`, `reports.journal`, `reports.ledger`, `reports.sub-ledger`, `reports.trial-balance`, `reports.inventory-turnover`, `commercial-ledgers.index`, `reports.cost-income`, `budgets.index`, `reports.company-overview` | 114 |
| G15 | `can` | `reports.company-overview` | 120 |
| G16 | `can` | `reports.cost-income` | 123 |
| G17 | `can` | `budgets.index` | 126 |
| G18 | `can` | `commercial-ledgers.index` | 129 |
| G19 | `any` | `reports.documents`, `reports.journal`, `reports.ledger`, `reports.sub-ledger`, `reports.trial-balance`, `reports.inventory-turnover` | 132 |
| G20 | `can` | `reports.documents` | 138 |
| G21 | `can` | `reports.journal` | 141 |
| G22 | `can` | `reports.ledger` | 144 |
| G23 | `can` | `reports.sub-ledger` | 147 |
| G24 | `can` | `reports.trial-balance` | 150 |
| G25 | `can` | `reports.inventory-turnover` | 153 |
| G26 | `any` | `warehouse.dashboard`, `warehouses.index`, `products.index`, `product-groups.index`, `services.index`, `service-groups.index` | 165 |
| G27 | `can` | `warehouse.dashboard` | 170 |
| G28 | `can` | `warehouses.index` | 173 |
| G29 | `can` | `products.index` | 176 |
| G30 | `can` | `product-groups.index` | 179 |
| G31 | `can` | `services.index` | 182 |
| G32 | `can` | `service-groups.index` | 185 |
| G33 | `any` | `salary.payrolls.dashboard`, `salary.payrolls.index`, `hr.employees.index`, `hr.personnel-requests.index`, `attendance.attendance-logs.index`, `attendance.monthly-attendances.index` | 193 |
| G34 | `can` | `salary.payrolls.dashboard` | 199 |
| G35 | `can` | `salary.payrolls.index` | 202 |
| G36 | `can` | `hr.employees.index` | 205 |
| G37 | `can` | `hr.personnel-requests.index` | 208 |
| G38 | `can` | `attendance.attendance-logs.index` | 211 |
| G39 | `can` | `attendance.monthly-attendances.index` | 214 |
| G40 | `any` | `crm.dashboard`, `customers.index`, `customer-groups.index` | 222 |
| G41 | `can` | `crm.dashboard` | 227 |
| G42 | `can` | `customers.index` | 230 |
| G43 | `can` | `customer-groups.index` | 233 |
| G44 | `any` | `hr.org-charts.index`, `hr.organization-units.index`, `attendance.work-shifts.index`, `salary.payrolls.index`, `salary.tax-slabs.index`, `salary.work-sites.index`, `salary.work-site-contracts.index`, `salary.public-holidays.index`, `salary.payroll-elements.index`, `salary.salary-decrees.index` | 247 |
| G45 | `can` | `hr.org-charts.index` | 255 |
| G46 | `can` | `hr.organization-units.index` | 258 |
| G47 | `can` | `attendance.work-shifts.index` | 262 |
| G48 | `can` | `salary.tax-slabs.index` | 268 |
| G49 | `can` | `salary.work-sites.index` | 271 |
| G50 | `can` | `salary.work-site-contracts.index` | 274 |
| G51 | `can` | `salary.public-holidays.index` | 279 |
| G52 | `can` | `salary.payroll-elements.index` | 283 |
| G53 | `can` | `salary.salary-decrees.index` | 287 |
| G54 | `any` | `bank-accounts.index`, `banks.index`, `chequebooks.index`, `subjects.index`, `documents.sort-numbers` | 296 |
| G55 | `can` | `subjects.index` | 302 |
| G56 | `can` | `bank-accounts.index` | 305 |
| G57 | `can` | `banks.index` | 308 |
| G58 | `can` | `chequebooks.index` | 311 |
| G59 | `can` | `documents.sort-numbers` | 314 |
| G60 | `any` | `companies.index`, `fiscal-years.index`, `users.index`, `configs.index`, `backups.create` | 324 |
| G61 | `can` | `companies.index` | 329 |
| G62 | `can` | `fiscal-years.index` | 332 |
| G63 | `can` | `users.index` | 335 |
| G64 | `can` | `configs.index` | 338 |
| G65 | `can` | `backups.create` | 341 |

## جایگاه‌های فعلی اکستنشن

| area | والد فعلی | ترتیب | guard محصورکننده | خط |
|---|---|---:|---|---:|
| `navigation.workspace.invoices` | N009 | 7 | G03 | 91 |
| `navigation.management` | N059 | 4 | — | 350 |

خود `ExtensionNavigation` علاوه بر guard قالب، در هر render این شرایط را بررسی می‌کند: کاربر موجود باشد، route رشته و ثبت‌شده باشد، label رشته و غیرخالی باشد، `permission` اختیاری با `user->can()` مجاز باشد و closure اختیاری `visible` برقرار باشد. callback آرایهٔ `user` و `fiscal_year_id` جاری را می‌گیرد. label پس از ترجمه escape می‌شود. در [extension-navigation.blade.php](../../resources/views/components/extension-navigation.blade.php) لینک فعلی با `route($item['route'])` بدون پارامتر ساخته می‌شود و `request()->routeIs($item['route'])` فقط نام route را بررسی می‌کند؛ renderer جدید باید `routeParameters` را پاس بدهد و برای variantهای query مثل `invoice_type` و `service_buy` قاعدهٔ active مشخصی داشته باشد.

قرارداد موجود `NavigationRegistry` شامل `register(string $id, array $definition)`، فیلد اجباری `area`، اولویت پیش‌فرض 100، رد ID تکراری در تمام areaها، و خروجی آرایه‌ای `items(?string $area)` است. ترتیب `(priority, id)` است. سازگاری عقب‌رو علاوه بر نام دو area باید نحوهٔ ثبت، permission تکی، context callback، مرتب‌سازی و مصرف‌کنندگان خروجی را هم در نظر بگیرد. singleton تنها دادهٔ ثبت را نگه می‌دارد؛ نتیجهٔ visibility نباید در آن ذخیره شود.

## قرارداد ظاهر و تعامل فعلی

- هر دو محل `resources/views/components/header.blade.php:16` و `resources/views/components/app-layout.blade.php:54` از `x-menu` استفاده می‌کنند. بنابراین هر لینک دو بار در DOM صفحهٔ معمولی حضور دارد؛ visibility هر دو باید یکسان باشد و IDهای DOM احتمالی نباید تکراری شوند.
- wrapper دسکتاپ از `app-main-menu` و `xl:menu-horizontal` استفاده می‌کند و از breakpoint `xl` نمایش داده می‌شود. wrapper موبایل `app-mobile-menu` و drawer مرتبط با `workspace-drawer` است. لینک پنل مدیریت در هر wrapper مستقل از `x-menu` قرار دارد.
- هشت گروه سطح اول `class="app-main-menu-dropdown"` و `data-main-menu-dropdown` دارند. شش گروه داخلی `<details>` ساده هستند؛ presenter باید عمق را حفظ کند.
- همهٔ پنل‌های سطح اول کلاس `app-main-menu-panel z-50 w-full min-w-full max-w-[calc(100vw-2rem)] overflow-y-auto md:w-50` دارند. فقط پنل Management علاوه بر آن `max-h-[calc(100dvh-8rem)]` دارد؛ این تفاوت در renderer جدید باید منتقل شود.
- `resources/js/app.js:80` گروه‌های باز هم‌سطح را می‌بندد، با بستن والد فرزندان باز را می‌بندد، و با کلیک لینک یا بیرون منو dropdownها را می‌بندد. کد به رابطهٔ مستقیم `li > details` و parent list وابسته است.
- `resources/css/app.css:308` به شکل درخت و کلاس‌های wrapper وابسته است: overflow سطح اول، nowrap متن، حداقل عرض زیرمنو، سایه و پس‌زمینهٔ پنل و فلش‌های موبایل. چیدمان در `app-layout` از locale برای RTL/LTR استفاده می‌کند.
- منوی هسته در وضعیت فعلی هیچ `aria-current`، کلاس active یا `open` وابسته به route ندارد. فقط لینک اکستنشن از `request()->routeIs($item['route'])` برای `aria-current="page"` استفاده می‌کند. فعال‌شدن والدها یک رفتار جدید تعریف‌شده در ایشو است.
- نام‌ها همگی با `__()` ترجمه و با خروجی پیش‌فرض Blade escape می‌شوند؛ نام فارسی جدیدی برای این فهرست اختراع نشده است.

## مواردی که انتقال ساده می‌تواند جا بیندازد

این‌ها مشاهدات وضعیت فعلی‌اند؛ ثبت آن‌ها به معنی تأیید یا اصلاح ضمنی permissionها نیست.

1. **My Portal:** شش لینک فقط از guard والد `employee-portal.dashboard` استفاده می‌کنند و در قالب permission جدا ندارند. افزودن guard بر اساس نام route هر لینک رفتار نمایش را تغییر می‌دهد.
2. **Accounting و چک:** والد فقط `documents.index` یا `documents.create` را می‌پذیرد، اما فرزند چک به `cheques.index` نیاز دارد. داشتن مجوز چک به‌تنهایی لینک را ظاهر نمی‌کند.
3. **Moadian Histories:** permission آن در شرط والد Invoices هست، ولی لینک زیر Sales در شرط `invoices.index` قرار دارد. بنابراین فقط داشتن `invoices.moadian-histories.index` می‌تواند گروه خالی Invoices بسازد. در قالب هیچ guard اختصاصی روی خود این لینک نیست.
4. **Purchases و موجودی اول دوره:** داشتن `ancillary-costs.index` به‌تنهایی، لینک‌های Buy/Return Buy/Service و Beginning Inventory را هم در قالب ظاهر می‌کند؛ روی این لینک‌ها guard اختصاصی `invoices.index` وجود ندارد. endpointها جداگانه permission را بررسی می‌کنند.
5. **Backup و Upload Backup:** هر دو زیر `backups.create` هستند؛ نام route آپلود `backups.upload` است. انتقال مکانیکی permission از نام route باعث تغییر رفتار می‌شود.
6. **Payrolls تکراری:** route `salary.payrolls.index` هم زیر HR و هم زیر Management → HR & Organization وجود دارد. دو آیتم مستقل با IDهای متفاوت لازم است؛ route نمی‌تواند ID یکتای رجیستری باشد.
7. **Home:** href فعلی `/` است، اما guard آن `home.*` است. route نام‌دار `home` موجود است؛ permission نباید خودکار از route استخراج شود.
8. **Management و About:** Management در این قالب guard ندارد و About بدون guard اختصاصی فرزند آن است؛ About بعد از محل اکستنشن قرار دارد. گروه با کاربر فاقد سایر مجوزها هم یک فرزند ثابت دارد.
9. **اکستنشن Invoices:** guard والد هسته قبل از رندر اکستنشن اجرا می‌شود. کاربر دارای permission package و فاقد permissionهای هسته ممکن است آیتم package را نبیند. درختی‌کردن به‌تنهایی این مشکل را حل نمی‌کند اگر همان gate روی parent جدید حفظ شود. سیاست parentهای عمومی و گروه‌هایی که عمداً محدود هستند باید در مرحلهٔ طراحی مشخص شود؛ قاعدهٔ ایشو دربارهٔ پنهان‌شدن فرزند والد غیرمجاز همچنان باید رعایت شود.
10. **active و پارامترها:** هشت لینک نام route یکسان دارند. مقایسهٔ صرف نام route آن‌ها را هم‌زمان active می‌کند؛ مقایسهٔ subset ساده هم Buy و Buy Service را روی `service_buy=1` هم‌زمان تطبیق می‌دهد. باید قواعد پارامترهای متمایزکننده و فیلترهای غیرمنویی مثل page/search روشن شود. `InvoiceController::index` نبودن invoice_type را به همهٔ انواع اعمال می‌کند، نه پیش‌فرض Sell؛ `service_buy` با boolean درخواست تفسیر می‌شود و فقط در Buy/Return Buy مؤثر است.
11. **منوی کاربر و پنل مدیریت:** زبان، پوسته، انتخاب شرکت/سال مالی، API Tokens، Logout و منوی مستقل super-admin در `x-menu` نیستند و form یا context متفاوت دارند. انتقال آن‌ها به مدل route-only جزو فهرست فعلی نیست و نیاز به scope صریح دارد.
12. **priority و سازگاری ترتیب:** areaهای قدیمی در انتهای Invoices و قبل از About هستند و priority آن‌ها فقط بین اکستنشن‌ها معنی دارد. mapping ساده به parent با حفظ عدد پیش‌فرض 100 می‌تواند اکستنشن‌های قدیمی را ابتدای گروه بیاورد. حفظ placement قدیمی همراه با اولویت‌های جدید هسته باید صریح پوشش داده شود.

## کنترل کامل‌بودن مرحلهٔ اول

- [x] شمارش 70 تگ لینک بسته با 70 ردیف فهرست؛ شامل href مستقیم Home.
- [x] شمارش 14 گروه باز و بسته و 14 ردیف گروه، و 9 ورودی سطح اول.
- [x] ثبت 69 فراخوانی route و یک href مستقیم؛ route تکراری Payrolls و هشت variant فاکتورها حفظ شده‌اند.
- [x] ثبت هر هشت آرایهٔ پارامتر ثابت؛ نبودن `service_buy` در لینک‌های عادی هم بخشی از رفتار فعلی است.
- [x] ثبت guardهای تمام والدها و لینک‌ها و هر دو محل اکستنشن، بدون استنتاج permission از route.
- [x] ثبت ترتیب خواهر/برادرها، About پس از اکستنشن و تفاوت پنل Management.
- [x] بررسی دو مصرف‌کنندهٔ desktop/mobile، وابستگی JavaScript و CSS، escape/ترجمه و وضعیت active فعلی.
- [x] ثبت API فعلی، context callback، route بدون پارامتر در component extension و پوشش موجود در `ExtensionRegistryTest` و `ExtensionNavigationTest`؛ تست موجود عمدتاً registry/extension را می‌پوشاند و برابری کل منوی هسته را اثبات نمی‌کند.
- [x] نقش‌های محدود با کاربر واقعی و رندر HTTP برای RTL/LTR بررسی شد؛ تعامل منوی دسکتاپ و drawer موبایل نیز روی fixture رندرشده بررسی شد.

## تصمیم‌های تأییدشده پس از فهرست‌برداری

- شناسه‌های هسته با `core.*` ثبت می‌شوند، مثل `core.invoices`، `core.management` و `core.management.system`.
- نام route و پارامترهای ثابت جدا هستند؛ `routeParameters` به تعریف آیتم اضافه می‌شود.
- active بودن بر اساس route و تطبیق دقیق پارامترهای متمایزکنندهٔ منو محاسبه می‌شود؛ نبودن `service_buy` در variant عادی مهم است و page/search نادیده گرفته می‌شوند.
- areaهای قدیمی جایگاه خود را حفظ می‌کنند و priority هر اکستنشن فقط در همان جایگاه مرتب‌سازی می‌کند؛ ثبت جدید با parent امکان جای‌گذاری بین آیتم‌های هسته را دارد.

`MenuItem`، ساخت درخت رجیستری، provider منوی هسته و renderer بازگشتی پیاده شده‌اند؛ جزئیات API و جایگاه‌های سازگاری در `docs/developer/extensions.md` آمده‌اند.

## وضعیت checkpoint و نقطهٔ ادامه

این checkpoint شامل مراحل ۱ تا ۶ است؛ اعتبارسنجی با نقش‌های محدود و مرورگر روی fixture محلی انجام شد. اجرای سرویس review دائمی و CI مستقل هنوز خارج از این checkout است.

- [x] مرحلهٔ ۱: فهرست مبنای منوی فعلی، guardها، پارامترها و وابستگی‌های ظاهر و تعامل ثبت شد.
- [x] مرحلهٔ ۲: `MenuItem` تغییرناپذیر و ساخت درخت در `NavigationRegistry`، اعتبارسنجی والد و چرخه، مرتب‌سازی قطعی و سازگاری API آرایه‌ای پیاده شد. جایگاه دو area قدیمی در درخت حفظ می‌شود.
- [x] مرحلهٔ ۳: provider منوی هسته با شناسه‌های پایدار `core.*` تمام 70 لینک، 14 گروه، هشت variant پارامتر و ترتیب موجود را ثبت می‌کند.
- [x] مرحلهٔ ۴: فیلتر permission/visibility در هر درخواست، حذف گروه‌های خالی، renderer بازگشتی و active بودن والد و فرزند با تطبیق دقیق پارامترهای منویی پیاده شد.
- [x] مرحلهٔ ۵: مصرف‌کنندگان دسکتاپ و موبایل به درخت مشترک منتقل شدند و ثبت‌های قدیمی، context callback و ترتیب جایگاه‌های اکستنشن حفظ شد.
- [x] مرحلهٔ ۶: نقش‌های دارای مجوز محدود، منوی دسکتاپ/موبایل و RTL/LTR بررسی شدند.

قالب منو و provider هسته اکنون از رجیستری مشترک استفاده می‌کنند. آیتم‌های typed و ثبت‌های قدیمی پس از پایان boot providerها در هر دو منوی دسکتاپ و موبایل فیلتر و render می‌شوند.

اعتبارسنجی انجام‌شده برای همین checkpoint:

اعداد و بررسی مرورگر در فهرست زیر مربوط به checkpoint برنچ ترکیبی قبلی‌اند، نه اجرای
مجدد روی برنچ مستقل. نتیجهٔ بررسی مستقل در بخش بعد ثبت شده است.

- مجموعهٔ `tests/Feature/Extensions` محلی: **73 تست و 215 assertion موفق**؛ شامل شمارش فهرست هسته، routeهای موجود، variantهای invoice، فیلتر permission و جایگاه legacy extension.
- Laravel Pint روی فایل‌های PHP تغییرکرده موفق بود؛ `git diff --check` نیز بدون خطا بود.
- تست HTTP نقش‌ها و localeها در `CoreNavigationAccessTest`: 9 تست و 46 assertion موفق؛ fixture مرورگر برای Admin/Accountant در fa/en تولید و با viewport دسکتاپ و 375px موبایل بررسی شد.

## بررسی برنچ مستقل #453 در 2026-11-03

- اصلاح‌های فایل تست رجیستری از `dac39b83` و `9f6628c4` و انتقال آن به Feature از
  `5856626b` منتقل شدند. تغییرات کتگوری، قرارداد CSV و مستندات frontend آن‌ها منتقل نشدند.
- دستور `php vendor/bin/phpunit tests/Feature/Extensions --colors=never` روی PHP 8.2.12
  با SQLite موفق بود: **77 تست و 248 assertion**. از این تعداد، 48 تست اختصاصی navigation هستند.
- اجرای کامل `main` در `e5bb5336` در همان محیط: 1514 تست، 30 error و 20 failure.
  خطاهای `ThursdayStatus` روی همین مبنا نیز وجود دارند و اصلاحشان پیش‌نیاز عمومی جداگانه است.
- این بررسی شامل اجرای مجدد تعامل مرورگر یا تأیید CI نیست. API GitLab برای خواندن
  job پایپ‌لاین 7613 پاسخ HTTP 404 داد؛ نتیجهٔ اجرای محلی به معنی تأیید پایپ‌لاین نیست.
