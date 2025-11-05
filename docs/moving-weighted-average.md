### **راهنمای پیاده‌سازی سیستم بهای تمام شده (میانگین موزون) برای توسعه‌دهنده امیر**

این راهنما شامل ساختار جداول پایگاه داده، منطق محاسباتی، طراحی فرم‌ها و لیست هزینه‌های قابل قبول است.

#### **بخش ۱: ساختار پایگاه داده (Schema)**

در اینجا ساختار جداول اصلی مورد نیاز به همراه فیلدهای کلیدی توضیح داده شده است.

**۱. جدول فاکتورهای فروش (Sales_Invoice_Items)**

```php

// Sales_Invoice_Items (اقلام)
Schema::create('sales_invoice_items', function (Blueprint $table) {
    ...
    $table->decimal('quantity', 15, 2); // تعداد فروش
    $table->decimal('unit_price', 15, 2); // قیمت فروش
    $table->decimal('cog_after', 15, 2); // بهای تمام شده در لحظه فروش
    $table->timestamps();
});
```
*   `cog_after`: **بسیار مهم**. در زمان ثبت فروش، مقدار فیلد `average_cost` از جدول `products` در این فیلد "عکس‌برداری" (Snapshot) و ذخیره می‌شود.

**۲. جدول هزینه‌های جانبی (Ancillary_Costs)**
برای ثبت هزینه‌هایی مانند حمل که به یک فاکتور خرید مرتبط هستند.

```php
Schema::create('ancillary_costs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_invoice_id')->constrained()->onDelete('cascade');
    $table->string('description'); // توضیحات (مثلا: هزینه حمل)
    $table->decimal('amount', 15, 2); // مبلغ هزینه
    $table->date('cost_date'); // تاریخ هزینه
    $table->timestamps();
});
```

---

#### **بخش ۲: منطق محاسبات و فرمول‌ها**

این بخش گردش کار اصلی را توضیح می‌دهد. تمام محاسبات باید در سمت سرور (Backend) و ترجیحاً داخل یک **Database Transaction** انجام شوند تا از بروز خطا و عدم هماهنگی داده‌ها جلوگیری شود.

**مرحله ۱: ثبت فاکتور خرید (بدون هزینه جانبی)**

1.  فاکتور خرید و اقلام آن (`purchase_invoices` و `purchase_invoice_items`) ذخیره می‌شوند.
2.  برای هر قلم (`item`) در `purchase_invoice_items`:
    *   فیلد `final_unit_cost` موقتاً برابر با `unit_price` قرار داده می‌شود.
    *   بهای تمام شده میانگین جدید برای کالا (`product`) محاسبه می‌شود.

**فرمول کلیدی به‌روزرسانی میانگین موزون:**

```
// مقادیر قبل از خرید جدید
previous_stock = product.current_stock
previous_average_cost = product.average_cost

// مقادیر خرید جدید
new_quantity = item.quantity
new_final_unit_cost = item.final_unit_cost // فعلا همان unit_price است

// محاسبه
previous_total_value = previous_stock * previous_average_cost
new_purchase_value = new_quantity * new_final_unit_cost

total_stock = previous_stock + new_quantity
total_value = previous_total_value + new_purchase_value

new_average_cost = total_value / total_stock

// به‌روزرسانی جدول محصولات
product.current_stock = total_stock
product.average_cost = new_average_cost
product->save()
```

**مرحله ۲: طراحی و عملکرد فرم هزینه‌های جانبی**

**الف) طراحی فرم (UI):**
*   **انتخاب فاکتور خرید:** یک Dropdown یا فیلد جستجو که کاربر بتواند فاکتور خرید مورد نظر را انتخاب کند.
*   **مبلغ هزینه:** فیلد عددی برای ورود مبلغ (`amount`).
*   **تاریخ هزینه:** فیلد تاریخ (`cost_date`).
*   **توضیحات:** فیلد متنی برای `description`.

**ب) منطق سمت سرور (Backend) پس از ثبت فرم:**

1.  یک رکورد جدید در جدول `ancillary_costs` ایجاد کنید.
2.  **تسهیم (تقسیم) هزینه:** مبلغ هزینه (`amount`) باید بین اقلام فاکتور خرید مرتبط (`purchase_invoice_items`) تقسیم شود. روش استاندارد، **تسهیم بر اساس ارزش** است.
    *   `total_invoice_value` = مبلغ کل فاکتور خرید مربوطه را به دست آورید.
    *   برای هر `item` در آن فاکتور خرید:
        *   `item_value` = `item.quantity * item.unit_price`
        *   `cost_share_ratio` = `item_value / total_invoice_value`
        *   `ancillary_cost_share` = `ancillary_cost.amount * cost_share_ratio`
        *   این مقدار را به فیلد `item.ancillary_cost_share` **اضافه** کنید (`+=`).

3.  **محاسبه مجدد `final_unit_cost` و `average_cost`:**
    *   از آنجایی که هزینه جانبی اضافه شده، باید کل محاسبات بهای تمام شده برای کالاهای آن فاکتور از نو انجام شود. این بخش کمی پیچیده است و باید با دقت انجام شود.
    *   **روش پیشنهادی:** یک متد یا Job در لاراول ایجاد کنید به نام `recalculateProductAverageCost(Product $product)`. این متد کل تاریخچه خرید کالا را مرور کرده و میانگین را از ابتدا محاسبه می‌کند. این روش دقیق است اما ممکن است کند باشد.
    *   **روش بهینه‌تر:** تأثیر هزینه جانبی جدید را بر میانگین فعلی محاسبه کنید.
        *   کل هزینه جانبی اضافه شده به یک کالا (`total_new_ancillary_cost_for_product`) را به دست آورید.
        *   `current_total_value` = `product.current_stock * product.average_cost`
        *   `new_total_value` = `current_total_value + total_new_ancillary_cost_for_product`
        *   `product.average_cost` = `new_total_value / product.current_stock`
        *   `product->save()`

**مرحله ۳: ثبت فاکتور فروش**

1.  هنگام ذخیره هر قلم در `sales_invoice_items`:
    *   موجودی کالا را بررسی کنید (`product.current_stock >= item.quantity`).
    *   فیلد `cog_after` را با مقدار فعلی `product.average_cost` پر کنید.
    *   موجودی کالا را کاهش دهید: `product.current_stock -= item.quantity`.
    *   کالا (`product`) را ذخیره کنید.

**سود هر قلم فروش:** `(item.unit_price - item.cog_after) * item.quantity`

---

#### **بخش ۳: لیست هزینه‌های قابل قبول در بهای تمام شده کالا**

این هزینه‌ها، هزینه‌هایی هستند که مستقیماً برای رساندن کالا به محل کسب‌وکار و آماده‌سازی آن برای فروش انجام شده‌اند. طبق استانداردهای حسابداری، این موارد را می‌توانید در فرم هزینه‌های جانبی ثبت کرده و روی بهای تمام شده سرشکن کنید:

*   **قیمت خرید اولیه کالا:** مبلغی که به فروشنده پرداخت شده است.
*   **هزینه‌های حمل و نقل:** هزینه ارسال کالا از مبدأ تا انبار شما.
*   **بیمه حمل:** هزینه بیمه کردن کالا در مسیر حمل.
*   **هزینه‌های گمرکی و عوارض واردات:** اگر کالا وارداتی باشد، تمام حقوق و عوارض گمرکی جزو بهای تمام شده است.
*   **مالیات‌های غیرقابل استرداد:** مالیات‌هایی که پرداخت می‌کنید و نمی‌توانید آن‌ها را در آینده از اداره مالیات پس بگیرید (مانند برخی عوارض خاص).
*   **هزینه‌های بارگیری و تخلیه.**
*   **هر هزینه مستقیم دیگری** که برای به دست آوردن کالا صرف شده باشد.

**نکته مهم:** هزینه‌هایی مانند هزینه‌های انبارداری (بعد از رسیدن کالا)، هزینه‌های اداری و فروش، جزو بهای تمام شده کالا **نیستند** و باید به عنوان هزینه‌های دوره ثبت شوند.