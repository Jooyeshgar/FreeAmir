
<html lang="fa" data-theme="corporate">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AmirAccountingSoftware</title>
    <link rel="preload" as="style" href="https://develop.freeamir.com/build/assets/app-DkrfhBXT.css" /><link rel="modulepreload" href="https://develop.freeamir.com/build/assets/app-q1b8DbYI.js" /><link rel="stylesheet" href="https://develop.freeamir.com/build/assets/app-DkrfhBXT.css" /><script type="module" src="https://develop.freeamir.com/build/assets/app-q1b8DbYI.js"></script>
</head>

<body class="min-h-screen" dir="rtl">

    <span class="flex items-center fixed left-0 right-0 top-0 bottom-0">
        <img src="/images/background.jpg" alt="" class="w-full h-full">
    </span>
    <div x-data="{ open: false }" class="min-[1430px]:w-[1430px] m-auto">
        <header class="navbar flex justify-between">
    <div class="flex-none">
        <div class="flex items-center bg-gray-200 rounded-xl mx-4 p-1">
            <img src="/images/logo.png" alt="Logo" width="50" class="">
        </div>
        <ul class="menu menu-horizontal px-1 bg-gray-200 rounded-xl">
            <li><a href="/" class="hover:rounded-xl">صفحه اصلی</a></li>
<li>
    <details>
        <summary>عملیات</summary>
        <ul>
            <li><a href="">دریافت و پرداخت</li>
            <li><a href="">ثبت فاکترو فروش</a></li>
            <li><a href="">ثبت فاکتور خرید</a></li>
            <li><a href="https://develop.freeamir.com/customers/create">ثبت طرف حساب</a></li>
        </ul>
    </details>
</li>
<li>
    <details>
        <summary>حسابداری</summary>
        <ul>
            <li><a href="https://develop.freeamir.com/documents/create">ایجاد سند</a></li>
            <li><a href="https://develop.freeamir.com/documents">فهرست اسناد</a></li>
        </ul>
    </details>
</li>
<li>
    <details>
        <summary>گزارشات</summary>
        <ul>
            <li>
                <details>
                    <summary>حسابداری</summary>
                    <ul>
                        <li><a href="">سند</a></li>
                        <li><a href="https://develop.freeamir.com/reports/journal">روزنامه</a></li>
                        <li><a href="https://develop.freeamir.com/reports/ledger">کل</a></li>
                        <li><a href="https://develop.freeamir.com/reports/sub-ledger">معین</a></li>
                        <li><a href="">سود و زیان</a></li>
                    </ul>
                </details>
                </a>
            </li>
            <li>
                <details>
                    <summary>انبار</summary>
                    <ul>
                        <li><a href="https://develop.freeamir.com/products">محصولات</a></li>
                        <li><a href="https://develop.freeamir.com/product-groups">گروه های محصول</a>
                        </li>
                    </ul>
                </details>
            </li>
            <li>
                <details>
                    <summary>طرف حسابها</summary>
                    <ul>
                        <li><a href="">بدهکاران</a></li>
                        <li><a href="">بستانکاران</a></li>
                    </ul>
                </details>
            </li>
        </ul>
    </details>
</li>

<li>
    <details>
        <summary>مدیریت</summary>
        <ul>
            <li><a href="https://develop.freeamir.com/subjects">سرفصل</a></li>
            <li><a href="https://develop.freeamir.com/bank-accounts">حساب های بانکی</a></li>
            <li><a href="https://develop.freeamir.com/customers">مشتریان </a></li>
            <li><a href="https://develop.freeamir.com/customer-groups">گروه های مشتریان </a></li>
            <li><a href="https://develop.freeamir.com/banks">بانک ها</a></li>
            <li><a href="https://develop.freeamir.com/management/users">کاربران</a></li>
            <li><a href="https://develop.freeamir.com/management/permissions">مجوزها</a></li>
            <li><a href="https://develop.freeamir.com/management/roles">نقش ها</a></li>
            <li><a href="https://develop.freeamir.com/management/configs">تنظیمات</a></li>
            <li><a href="https://github.com/Jooyeshgar/FreeAmir/issues">حمایت کردن</a></li>
        </ul>
    </details>
</li>
        </ul>
    </div>

    <div class="text-right">
        <ul class="menu menu-horizontal px-1 bg-gray-200 rounded-xl">
            <li>
                <details>
                    <summary>admin</summary>
                    <ul>
                        <li><a href="/logout">خروج</a></li>
                    </ul>
                </details>
            </li>
        </ul>
    </div>
</header>

        <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
            ثبت سند حسابداری
        </span>
    </div>
    <div class="">

        <form action="https://develop.freeamir.com/documents" method="POST">
                        <input type="hidden" name="_token" value="KhsOtiFgMTxNY7Xqsw3W6XnJwal3DYnK3e96dnVf" autocomplete="off">            <div class="card bg-base-100 shadow-xl rounded-2xl w-full rounded-2xl w-full" class_body="p-4">
    <div class="card-body p-4 rounded-2xl w-full" class_body="p-4">
        <div class="flex gap-2">
        <label class="flex flex-col flex-wrap w-full" name="title" title="نام سند" value="" placeholder="نام سند" label_text_class="text-gray-500" label_class="w-full" input_class="max-w-96">
    <span  class="text-gray-500" name="title" title="نام سند" value="" placeholder="نام سند" label_text_class="text-gray-500" label_class="w-full" input_class="max-w-96">
        نام سند
    </span>
    <input onkeyup="" " id="" name="title" value=""
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 max-w-96" name="title" title="نام سند" value="" placeholder="نام سند" label_text_class="text-gray-500" label_class="w-full" input_class="max-w-96" type="text"
           placeholder=" نام سند"/>
</label>
        <label class="flex flex-col flex-wrap w-full hidden" value="" name="document_id" label_text_class="text-gray-500" label_class="w-full hidden">
    <span  class="text-gray-500" value="" name="document_id" label_text_class="text-gray-500" label_class="w-full hidden">
        
    </span>
    <input onkeyup="" " id="" name="document_id" value=""
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42" value="" name="document_id" label_text_class="text-gray-500" label_class="w-full hidden" type="text"
           placeholder=" "/>
</label>
        <div class="flex-1"></div>
        <label class="flex flex-col flex-wrap" disabled="true" value="100" name="" title="شماره سند قبلی" placeholder="شماره سند قبلی" label_text_class="text-gray-500 text-nowrap">
    <span  class="text-gray-500 text-nowrap" disabled="true" value="100" name="" title="شماره سند قبلی" placeholder="شماره سند قبلی" label_text_class="text-gray-500 text-nowrap">
        شماره سند قبلی
    </span>
    <input onkeyup="" disabled" id="" name="" value="100"
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42" disabled="true" value="100" name="" title="شماره سند قبلی" placeholder="شماره سند قبلی" label_text_class="text-gray-500 text-nowrap" type="text"
           placeholder=" شماره سند قبلی"/>
</label>
        <label class="flex flex-col flex-wrap" value="101" name="number" title="شماره سند قبلی" placeholder="شماره سند فعلی" label_text_class="text-gray-500 text-nowrap">
    <span  class="text-gray-500 text-nowrap" value="101" name="number" title="شماره سند قبلی" placeholder="شماره سند فعلی" label_text_class="text-gray-500 text-nowrap">
        شماره سند قبلی
    </span>
    <input onkeyup="" " id="" name="number" value="101"
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42" value="101" name="number" title="شماره سند قبلی" placeholder="شماره سند فعلی" label_text_class="text-gray-500 text-nowrap" type="text"
           placeholder=" شماره سند فعلی"/>
</label>
        <label class="flex flex-col flex-wrap" data-jdp="data-jdp" title="تاریخ" name="date" placeholder="تاریخ" value="" label_text_class="text-gray-500 text-nowrap" input_class="datePicker">
    <span  class="text-gray-500 text-nowrap" data-jdp="data-jdp" title="تاریخ" name="date" placeholder="تاریخ" value="" label_text_class="text-gray-500 text-nowrap" input_class="datePicker">
        تاریخ
    </span>
    <input onkeyup="" " id="" name="date" value=""
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 datePicker" data-jdp="data-jdp" title="تاریخ" name="date" placeholder="تاریخ" value="" label_text_class="text-gray-500 text-nowrap" input_class="datePicker" type="text"
           placeholder=" تاریخ"/>
</label>
    </div>
    </div>
</div>

<div class="card bg-base-100 shadow-xl mt-4 rounded-2xl w-full mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-0">
    <div class="card-body p-0 pt-0 mt-0 mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-0">
        <div class="flex overflow-x-auto overflow-y-hidden  gap-2 items-center px-4  ">
        <div class="text-sm flex-1 max-w-8  text-center text-gray-500 pt-3 ">
            *
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            کد سرفصل
        </div>
        <div class="text-sm flex-1 min-w-80 max-w-80 text-center text-gray-500 pt-3 ">
            عنوان سر فصل
        </div>
        <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3 ">
            توضیحات
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            بدهکار
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            بستانکار
        </div>
    </div>
    <div class="h-96 overflow-y-auto px-4">
        <div id="transactions">
                            <div onclick="activeRow(event)" class="transaction flex gap-2 overflow-auto items-center ">

                    <label class="flex flex-col flex-wrap w-full hidden" value="" name="transactions[0][transaction_id]" label_text_class="text-gray-500" label_class="w-full hidden">
    <span  class="text-gray-500" value="" name="transactions[0][transaction_id]" label_text_class="text-gray-500" label_class="w-full hidden">
        
    </span>
    <input onkeyup="" " id="" name="transactions[0][transaction_id]" value=""
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42" value="" name="transactions[0][transaction_id]" label_text_class="text-gray-500" label_class="w-full hidden" type="text"
           placeholder=" "/>
</label>

                    <div class="flex-1 text-center  max-w-8 pb-3">
                        <span class="transaction-count">1</span>

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                            class="px-2 size-8 rounded-md  h-10 flex justify-center items-center text-center  bg-red-500 hover:bg-red-700 text-white font-bold rounded removeTransaction text-center">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>

                    </div>
                    <div class="flex-1 min-w-24 max-w-24 pb-3">

                        <label class="flex flex-col flex-wrap w-full" value="" id="value" name="transactions[0][code]" label_text_class="text-gray-500" label_class="w-full" input_class="codeInput">
    <span  class="text-gray-500" value="" id="value" name="transactions[0][code]" label_text_class="text-gray-500" label_class="w-full" input_class="codeInput">
        
    </span>
    <input onkeyup="" " id="" name="transactions[0][code]" value=""
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 codeInput" value="" id="value" name="transactions[0][code]" label_text_class="text-gray-500" label_class="w-full" input_class="codeInput" type="text"
           placeholder=" "/>
</label>

                    </div>
                    <div class="flex-1 min-w-80 max-w-80 pb-3">
                        <select name="transactions[0][subject_id]" id="subject_id"
                            class="codeSelectBox rounded-md max-h-10 min-h-10 select select-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 focus:outline-none ">
                            <option value="">یک عنوان انتخاب کنید</option>
                                                            <option disabled value="248" data-title="Prof. Orion Von Sr."
                                    data-type="creditor" >
                                    Prof. Orion Von Sr. - (creditor)
                                </option>
                                                            <option disabled value="1" data-title="بانکها"
                                    data-type="both" >
                                    بانکها 
                                </option>
                                                            <option disabled value="3" data-title="موجودیهای نقدی"
                                    data-type="both" >
                                    موجودیهای نقدی 
                                </option>
                                                            <option  value="14" data-title="صندوق"
                                    data-type="both" >
                                    صندوق 
                                </option>
                                                            <option  value="59" data-title="تنخواه گردانها"
                                    data-type="both" >
                                    تنخواه گردانها 
                                </option>
                                                            <option disabled value="4" data-title="بدهکاران/بستانکاران"
                                    data-type="both" >
                                    بدهکاران/بستانکاران 
                                </option>
                                                            <option  value="58" data-title="اشخاص متفرقه"
                                    data-type="both" >
                                    اشخاص متفرقه 
                                </option>
                                                            <option disabled value="104" data-title="Lexus McKenzie"
                                    data-type="creditor" >
                                    Lexus McKenzie - (creditor)
                                </option>
                                                            <option disabled value="6" data-title="اسناد دریافتنی"
                                    data-type="both" >
                                    اسناد دریافتنی 
                                </option>
                                                            <option  value="44" data-title="اسناد دریافتنی"
                                    data-type="both" >
                                    اسناد دریافتنی 
                                </option>
                                                            <option disabled value="107" data-title="Prof. Silas Morar IV"
                                    data-type="creditor" >
                                    Prof. Silas Morar IV - (creditor)
                                </option>
                                                            <option disabled value="67" data-title="اسناد در جریان وصول"
                                    data-type="both" >
                                    اسناد در جریان وصول 
                                </option>
                                                            <option  value="68" data-title="اسناد در جریان وصول"
                                    data-type="both" >
                                    اسناد در جریان وصول 
                                </option>
                                                            <option disabled value="69" data-title="موجودی مواد و کالا"
                                    data-type="both" >
                                    موجودی مواد و کالا 
                                </option>
                                                            <option  value="7" data-title="موجودی مواد اولیه"
                                    data-type="both" >
                                    موجودی مواد اولیه 
                                </option>
                                                            <option  value="70" data-title="موجودی مواد و کالا"
                                    data-type="both" >
                                    موجودی مواد و کالا 
                                </option>
                                                            <option disabled value="71" data-title="پیش پرداخت ها"
                                    data-type="both" >
                                    پیش پرداخت ها 
                                </option>
                                                            <option  value="72" data-title="پیش پرداخت مالیات"
                                    data-type="both" >
                                    پیش پرداخت مالیات 
                                </option>
                                                            <option  value="73" data-title="پیش پرداخت اجاره"
                                    data-type="both" >
                                    پیش پرداخت اجاره 
                                </option>
                                                            <option  value="74" data-title="پیش پرداخت هزینه های جاری"
                                    data-type="both" >
                                    پیش پرداخت هزینه های جاری 
                                </option>
                                                            <option disabled value="75" data-title="دارایی های غیر جاری"
                                    data-type="both" >
                                    دارایی های غیر جاری 
                                </option>
                                                            <option  value="76" data-title="اموال، ماشین آلات و تجهیزات"
                                    data-type="both" >
                                    اموال، ماشین آلات و تجهیزات 
                                </option>
                                                            <option  value="77" data-title="استهلاک انباشته اموال، ماشین آلات و تجهیزات"
                                    data-type="both" >
                                    استهلاک انباشته اموال، ماشین آلات و تجهیزات 
                                </option>
                                                            <option  value="78" data-title="سرمایه گذاری های بلند مدت"
                                    data-type="both" >
                                    سرمایه گذاری های بلند مدت 
                                </option>
                                                            <option  value="79" data-title="سپرده ها و مطالبات بلندمدت"
                                    data-type="both" >
                                    سپرده ها و مطالبات بلندمدت 
                                </option>
                                                            <option  value="80" data-title="سایر دارایی ها"
                                    data-type="both" >
                                    سایر دارایی ها 
                                </option>
                                                            <option disabled value="38" data-title="سایر حسابهای دریافتنی"
                                    data-type="both" >
                                    سایر حسابهای دریافتنی 
                                </option>
                                                            <option  value="40" data-title="مالیات بر ارزش افزوده خرید"
                                    data-type="both" >
                                    مالیات بر ارزش افزوده خرید 
                                </option>
                                                            <option  value="56" data-title="عوارض خرید"
                                    data-type="both" >
                                    عوارض خرید 
                                </option>
                                                            <option  value="62" data-title="مساعده حقوق"
                                    data-type="both" >
                                    مساعده حقوق 
                                </option>
                                                            <option  value="63" data-title="جاری کارکنان"
                                    data-type="both" >
                                    جاری کارکنان 
                                </option>
                                                            <option  value="64" data-title="حق بیمه 5درصد مکسوره از صورت وضعیت"
                                    data-type="both" >
                                    حق بیمه 5درصد مکسوره از صورت وضعیت 
                                </option>
                                                            <option disabled value="22" data-title="اسناد پرداختنی"
                                    data-type="both" >
                                    اسناد پرداختنی 
                                </option>
                                                            <option  value="46" data-title="اسناد پرداختنی"
                                    data-type="both" >
                                    اسناد پرداختنی 
                                </option>
                                                            <option disabled value="163" data-title="Henri Durgan"
                                    data-type="creditor" >
                                    Henri Durgan - (creditor)
                                </option>
                                                            <option disabled value="145" data-title="Prof. Bennett Cronin IV"
                                    data-type="creditor" >
                                    Prof. Bennett Cronin IV - (creditor)
                                </option>
                                                            <option disabled value="81" data-title="پیش دریافت ها"
                                    data-type="both" >
                                    پیش دریافت ها 
                                </option>
                                                            <option  value="82" data-title="پیش دریافت فروش محصولات"
                                    data-type="both" >
                                    پیش دریافت فروش محصولات 
                                </option>
                                                            <option  value="83" data-title="سایر پیش دریافت ها"
                                    data-type="both" >
                                    سایر پیش دریافت ها 
                                </option>
                                                            <option disabled value="39" data-title="سایر حسابهای پرداختنی"
                                    data-type="both" >
                                    سایر حسابهای پرداختنی 
                                </option>
                                                            <option  value="41" data-title="مالیات بر ارزش افزوده فروش"
                                    data-type="both" >
                                    مالیات بر ارزش افزوده فروش 
                                </option>
                                                            <option  value="57" data-title="عوارض فروش"
                                    data-type="both" >
                                    عوارض فروش 
                                </option>
                                                            <option  value="66" data-title="عیدی و پاداش پرداختنی"
                                    data-type="both" >
                                    عیدی و پاداش پرداختنی 
                                </option>
                                                            <option disabled value="84" data-title="حقوق صاحبان سهام"
                                    data-type="both" >
                                    حقوق صاحبان سهام 
                                </option>
                                                            <option  value="21" data-title="سرمایه"
                                    data-type="both" >
                                    سرمایه 
                                </option>
                                                            <option  value="85" data-title="اندوخته قانونی"
                                    data-type="both" >
                                    اندوخته قانونی 
                                </option>
                                                            <option  value="86" data-title="سود (زیان) انباشته"
                                    data-type="both" >
                                    سود (زیان) انباشته 
                                </option>
                                                            <option  value="96" data-title="سود (زیان) جاری"
                                    data-type="both" >
                                    سود (زیان) جاری 
                                </option>
                                                            <option  value="87" data-title="تقسیم سود"
                                    data-type="both" >
                                    تقسیم سود 
                                </option>
                                                            <option disabled value="196" data-title="Orville Zulauf"
                                    data-type="creditor" >
                                    Orville Zulauf - (creditor)
                                </option>
                                                            <option disabled value="210" data-title="Ezra Zemlak"
                                    data-type="creditor" >
                                    Ezra Zemlak - (creditor)
                                </option>
                                                            <option disabled value="2" data-title="هزینه ها"
                                    data-type="debtor" >
                                    هزینه ها - (debtor)
                                </option>
                                                            <option  value="10" data-title="حقوق پرسنل"
                                    data-type="debtor" >
                                    حقوق پرسنل - (debtor)
                                </option>
                                                            <option  value="11" data-title="آب"
                                    data-type="debtor" >
                                    آب - (debtor)
                                </option>
                                                            <option  value="12" data-title="برق"
                                    data-type="debtor" >
                                    برق - (debtor)
                                </option>
                                                            <option  value="13" data-title="تلفن"
                                    data-type="debtor" >
                                    تلفن - (debtor)
                                </option>
                                                            <option  value="26" data-title="گاز"
                                    data-type="debtor" >
                                    گاز - (debtor)
                                </option>
                                                            <option  value="27" data-title="پست"
                                    data-type="debtor" >
                                    پست - (debtor)
                                </option>
                                                            <option  value="28" data-title="هزینه حمل"
                                    data-type="debtor" >
                                    هزینه حمل - (debtor)
                                </option>
                                                            <option  value="29" data-title="ضایعات کالا"
                                    data-type="debtor" >
                                    ضایعات کالا - (debtor)
                                </option>
                                                            <option  value="30" data-title="عوارض شهرداری"
                                    data-type="debtor" >
                                    عوارض شهرداری - (debtor)
                                </option>
                                                            <option  value="31" data-title="کارمزد بانک"
                                    data-type="debtor" >
                                    کارمزد بانک - (debtor)
                                </option>
                                                            <option  value="33" data-title="مالیات"
                                    data-type="debtor" >
                                    مالیات - (debtor)
                                </option>
                                                            <option  value="34" data-title="هزینه اجاره محل"
                                    data-type="debtor" >
                                    هزینه اجاره محل - (debtor)
                                </option>
                                                            <option  value="32" data-title="هزینه های متفرقه"
                                    data-type="debtor" >
                                    هزینه های متفرقه - (debtor)
                                </option>
                                                            <option disabled value="88" data-title="قیمت تمام شده کالای فروش رفته"
                                    data-type="both" >
                                    قیمت تمام شده کالای فروش رفته 
                                </option>
                                                            <option  value="89" data-title="قیمت تمام شده کالای فروش رفته"
                                    data-type="both" >
                                    قیمت تمام شده کالای فروش رفته 
                                </option>
                                                            <option disabled value="135" data-title="Eliza Braun"
                                    data-type="creditor" >
                                    Eliza Braun - (creditor)
                                </option>
                                                            <option disabled value="23" data-title="درآمدها"
                                    data-type="debtor" >
                                    درآمدها - (debtor)
                                </option>
                                                            <option  value="36" data-title="درآمد متفرقه"
                                    data-type="debtor" >
                                    درآمد متفرقه - (debtor)
                                </option>
                                                            <option disabled value="125" data-title="Dr. Kenton King I"
                                    data-type="creditor" >
                                    Dr. Kenton King I - (creditor)
                                </option>
                                                            <option disabled value="188" data-title="Joy McCullough"
                                    data-type="creditor" >
                                    Joy McCullough - (creditor)
                                </option>
                                                            <option disabled value="18" data-title="فروش"
                                    data-type="debtor" >
                                    فروش - (debtor)
                                </option>
                                                            <option  value="20" data-title="فروش"
                                    data-type="debtor" >
                                    فروش - (debtor)
                                </option>
                                                            <option disabled value="25" data-title="برگشت از فروش و تخفیفات"
                                    data-type="both" >
                                    برگشت از فروش و تخفیفات 
                                </option>
                                                            <option  value="55" data-title="تخفیفات فروش"
                                    data-type="both" >
                                    تخفیفات فروش 
                                </option>
                                                            <option  value="43" data-title="برگشت از فروش"
                                    data-type="both" >
                                    برگشت از فروش 
                                </option>
                                                            <option disabled value="17" data-title="خرید"
                                    data-type="debtor" >
                                    خرید - (debtor)
                                </option>
                                                            <option  value="19" data-title="خرید"
                                    data-type="debtor" >
                                    خرید - (debtor)
                                </option>
                                                            <option disabled value="24" data-title="برگشت از خرید و تخفیفات"
                                    data-type="both" >
                                    برگشت از خرید و تخفیفات 
                                </option>
                                                            <option  value="42" data-title="برگشت از خرید"
                                    data-type="both" >
                                    برگشت از خرید 
                                </option>
                                                            <option  value="53" data-title="تخفیفات خرید"
                                    data-type="both" >
                                    تخفیفات خرید 
                                </option>
                                                            <option disabled value="90" data-title="حسابهای انتظامی"
                                    data-type="both" >
                                    حسابهای انتظامی 
                                </option>
                                                            <option  value="91" data-title="حسابهای انتظامی به نفع شرکت"
                                    data-type="both" >
                                    حسابهای انتظامی به نفع شرکت 
                                </option>
                                                            <option  value="92" data-title="حسابهای انتظامی به عهده شرکت"
                                    data-type="both" >
                                    حسابهای انتظامی به عهده شرکت 
                                </option>
                                                            <option disabled value="93" data-title="طرف حسابهای انتظامی"
                                    data-type="both" >
                                    طرف حسابهای انتظامی 
                                </option>
                                                            <option  value="94" data-title="طرف حساب انتظامی به نفع شرکت"
                                    data-type="both" >
                                    طرف حساب انتظامی به نفع شرکت 
                                </option>
                                                            <option  value="95" data-title="طرف حساب انتظامی به عهده شرکت"
                                    data-type="both" >
                                    طرف حساب انتظامی به عهده شرکت 
                                </option>
                                                            <option disabled value="97" data-title="تخفیفات نقدی"
                                    data-type="both" >
                                    تخفیفات نقدی 
                                </option>
                                                            <option  value="98" data-title="تخفیفات نقدی"
                                    data-type="both" >
                                    تخفیفات نقدی 
                                </option>
                                                            <option disabled value="5" data-title="تراز افتتاحیه"
                                    data-type="both" >
                                    تراز افتتاحیه 
                                </option>
                                                            <option  value="15" data-title="تراز افتتاحیه"
                                    data-type="both" >
                                    تراز افتتاحیه 
                                </option>
                                                            <option disabled value="8" data-title="جاری شرکا"
                                    data-type="both" >
                                    جاری شرکا 
                                </option>
                                                            <option  value="37" data-title="جاری شرکا"
                                    data-type="debtor" >
                                    جاری شرکا - (debtor)
                                </option>
                                                            <option disabled value="134" data-title="Kayleigh Armstrong III"
                                    data-type="creditor" >
                                    Kayleigh Armstrong III - (creditor)
                                </option>
                                                            <option disabled value="204" data-title="Nadia Gulgowski"
                                    data-type="creditor" >
                                    Nadia Gulgowski - (creditor)
                                </option>
                                                            <option disabled value="225" data-title="Roderick Jerde"
                                    data-type="creditor" >
                                    Roderick Jerde - (creditor)
                                </option>
                                                            <option disabled value="239" data-title="Frank Hintz"
                                    data-type="creditor" >
                                    Frank Hintz - (creditor)
                                </option>
                                                            <option disabled value="185" data-title="Buck Volkman"
                                    data-type="creditor" >
                                    Buck Volkman - (creditor)
                                </option>
                                                            <option disabled value="100" data-title="Yasmeen Lockman"
                                    data-type="creditor" >
                                    Yasmeen Lockman - (creditor)
                                </option>
                                                            <option disabled value="243" data-title="Cora Kutch"
                                    data-type="creditor" >
                                    Cora Kutch - (creditor)
                                </option>
                                                            <option disabled value="174" data-title="Ignacio Yost"
                                    data-type="creditor" >
                                    Ignacio Yost - (creditor)
                                </option>
                                                            <option disabled value="153" data-title="Felicity Hansen II"
                                    data-type="creditor" >
                                    Felicity Hansen II - (creditor)
                                </option>
                                                            <option disabled value="124" data-title="Dr. Marty Schmeler PhD"
                                    data-type="creditor" >
                                    Dr. Marty Schmeler PhD - (creditor)
                                </option>
                                                            <option disabled value="208" data-title="Prof. Lora Robel"
                                    data-type="creditor" >
                                    Prof. Lora Robel - (creditor)
                                </option>
                                                            <option disabled value="128" data-title="Barry Jast"
                                    data-type="creditor" >
                                    Barry Jast - (creditor)
                                </option>
                                                            <option disabled value="230" data-title="Vincent Schmeler"
                                    data-type="creditor" >
                                    Vincent Schmeler - (creditor)
                                </option>
                                                            <option disabled value="170" data-title="Celestino Funk I"
                                    data-type="creditor" >
                                    Celestino Funk I - (creditor)
                                </option>
                                                            <option disabled value="116" data-title="Cynthia Zieme"
                                    data-type="creditor" >
                                    Cynthia Zieme - (creditor)
                                </option>
                                                            <option disabled value="101" data-title="Prof. Eldridge Abbott Sr."
                                    data-type="creditor" >
                                    Prof. Eldridge Abbott Sr. - (creditor)
                                </option>
                                                            <option disabled value="143" data-title="Eric Wiza"
                                    data-type="creditor" >
                                    Eric Wiza - (creditor)
                                </option>
                                                            <option disabled value="244" data-title="Amiya Littel"
                                    data-type="creditor" >
                                    Amiya Littel - (creditor)
                                </option>
                                                            <option disabled value="226" data-title="Kamron Grady"
                                    data-type="creditor" >
                                    Kamron Grady - (creditor)
                                </option>
                                                            <option disabled value="189" data-title="Turner Hand"
                                    data-type="creditor" >
                                    Turner Hand - (creditor)
                                </option>
                                                            <option disabled value="223" data-title="Miss Mikayla Kuhn II"
                                    data-type="creditor" >
                                    Miss Mikayla Kuhn II - (creditor)
                                </option>
                                                            <option disabled value="123" data-title="Dr. Kolby McDermott"
                                    data-type="creditor" >
                                    Dr. Kolby McDermott - (creditor)
                                </option>
                                                            <option disabled value="136" data-title="Madie Bins"
                                    data-type="creditor" >
                                    Madie Bins - (creditor)
                                </option>
                                                            <option disabled value="176" data-title="Prof. Manley Fay V"
                                    data-type="creditor" >
                                    Prof. Manley Fay V - (creditor)
                                </option>
                                                            <option disabled value="181" data-title="Fermin Lockman Sr."
                                    data-type="creditor" >
                                    Fermin Lockman Sr. - (creditor)
                                </option>
                                                            <option disabled value="222" data-title="Ahmed Schowalter I"
                                    data-type="creditor" >
                                    Ahmed Schowalter I - (creditor)
                                </option>
                                                            <option disabled value="171" data-title="Prof. Keshaun Pollich DVM"
                                    data-type="creditor" >
                                    Prof. Keshaun Pollich DVM - (creditor)
                                </option>
                                                            <option disabled value="214" data-title="Mr. Waino Feil III"
                                    data-type="creditor" >
                                    Mr. Waino Feil III - (creditor)
                                </option>
                                                            <option disabled value="194" data-title="Kevon Mayert"
                                    data-type="creditor" >
                                    Kevon Mayert - (creditor)
                                </option>
                                                            <option disabled value="157" data-title="Terry Hagenes"
                                    data-type="creditor" >
                                    Terry Hagenes - (creditor)
                                </option>
                                                            <option disabled value="215" data-title="Keanu Funk V"
                                    data-type="creditor" >
                                    Keanu Funk V - (creditor)
                                </option>
                                                            <option disabled value="139" data-title="Gonzalo Kilback II"
                                    data-type="creditor" >
                                    Gonzalo Kilback II - (creditor)
                                </option>
                                                            <option disabled value="187" data-title="Jerome Botsford"
                                    data-type="creditor" >
                                    Jerome Botsford - (creditor)
                                </option>
                                                            <option disabled value="236" data-title="Franco Cassin"
                                    data-type="creditor" >
                                    Franco Cassin - (creditor)
                                </option>
                                                            <option disabled value="103" data-title="Cameron Jacobson"
                                    data-type="creditor" >
                                    Cameron Jacobson - (creditor)
                                </option>
                                                            <option disabled value="151" data-title="Francisco Ankunding I"
                                    data-type="creditor" >
                                    Francisco Ankunding I - (creditor)
                                </option>
                                                            <option disabled value="191" data-title="Dr. Dayna Dickens"
                                    data-type="creditor" >
                                    Dr. Dayna Dickens - (creditor)
                                </option>
                                                            <option disabled value="132" data-title="Maryjane Jakubowski"
                                    data-type="creditor" >
                                    Maryjane Jakubowski - (creditor)
                                </option>
                                                            <option disabled value="182" data-title="Prof. Samara Hettinger"
                                    data-type="creditor" >
                                    Prof. Samara Hettinger - (creditor)
                                </option>
                                                            <option disabled value="195" data-title="Dr. Collin McDermott"
                                    data-type="creditor" >
                                    Dr. Collin McDermott - (creditor)
                                </option>
                                                            <option disabled value="108" data-title="Eldon Smith"
                                    data-type="creditor" >
                                    Eldon Smith - (creditor)
                                </option>
                                                            <option disabled value="217" data-title="Rosalind O&#039;Conner"
                                    data-type="creditor" >
                                    Rosalind O&#039;Conner - (creditor)
                                </option>
                                                            <option disabled value="186" data-title="Amir Adams"
                                    data-type="creditor" >
                                    Amir Adams - (creditor)
                                </option>
                                                            <option disabled value="202" data-title="Kurtis Durgan V"
                                    data-type="creditor" >
                                    Kurtis Durgan V - (creditor)
                                </option>
                                                            <option disabled value="149" data-title="Dr. August Heaney IV"
                                    data-type="creditor" >
                                    Dr. August Heaney IV - (creditor)
                                </option>
                                                            <option disabled value="140" data-title="Jana Kling"
                                    data-type="creditor" >
                                    Jana Kling - (creditor)
                                </option>
                                                            <option disabled value="193" data-title="Prof. Oran Walter DDS"
                                    data-type="creditor" >
                                    Prof. Oran Walter DDS - (creditor)
                                </option>
                                                            <option disabled value="121" data-title="Giovanna Christiansen"
                                    data-type="creditor" >
                                    Giovanna Christiansen - (creditor)
                                </option>
                                                            <option disabled value="233" data-title="Mr. Jettie Mertz IV"
                                    data-type="creditor" >
                                    Mr. Jettie Mertz IV - (creditor)
                                </option>
                                                            <option disabled value="234" data-title="Glennie Schoen"
                                    data-type="creditor" >
                                    Glennie Schoen - (creditor)
                                </option>
                                                            <option disabled value="169" data-title="Ramona Hammes"
                                    data-type="creditor" >
                                    Ramona Hammes - (creditor)
                                </option>
                                                            <option disabled value="154" data-title="Tara Kiehn"
                                    data-type="creditor" >
                                    Tara Kiehn - (creditor)
                                </option>
                                                            <option disabled value="242" data-title="Casper Gottlieb DDS"
                                    data-type="creditor" >
                                    Casper Gottlieb DDS - (creditor)
                                </option>
                                                            <option disabled value="246" data-title="Estevan Homenick"
                                    data-type="creditor" >
                                    Estevan Homenick - (creditor)
                                </option>
                                                            <option disabled value="119" data-title="Aliya Dibbert V"
                                    data-type="creditor" >
                                    Aliya Dibbert V - (creditor)
                                </option>
                                                            <option disabled value="99" data-title="Kameron Parker"
                                    data-type="creditor" >
                                    Kameron Parker - (creditor)
                                </option>
                                                            <option disabled value="112" data-title="Prof. Liliana Gislason"
                                    data-type="creditor" >
                                    Prof. Liliana Gislason - (creditor)
                                </option>
                                                            <option disabled value="165" data-title="Alexandro O&#039;Reilly"
                                    data-type="creditor" >
                                    Alexandro O&#039;Reilly - (creditor)
                                </option>
                                                            <option disabled value="122" data-title="Justus Kub"
                                    data-type="creditor" >
                                    Justus Kub - (creditor)
                                </option>
                                                            <option disabled value="240" data-title="D&#039;angelo Lesch"
                                    data-type="creditor" >
                                    D&#039;angelo Lesch - (creditor)
                                </option>
                                                            <option disabled value="147" data-title="Lavada Gusikowski Sr."
                                    data-type="creditor" >
                                    Lavada Gusikowski Sr. - (creditor)
                                </option>
                                                            <option disabled value="113" data-title="Prof. Lisette Bergnaum IV"
                                    data-type="creditor" >
                                    Prof. Lisette Bergnaum IV - (creditor)
                                </option>
                                                            <option disabled value="173" data-title="Abdullah Koch"
                                    data-type="creditor" >
                                    Abdullah Koch - (creditor)
                                </option>
                                                            <option disabled value="133" data-title="Prof. Kamryn Schroeder DVM"
                                    data-type="creditor" >
                                    Prof. Kamryn Schroeder DVM - (creditor)
                                </option>
                                                            <option disabled value="190" data-title="Kitty Maggio"
                                    data-type="creditor" >
                                    Kitty Maggio - (creditor)
                                </option>
                                                            <option disabled value="166" data-title="Clarabelle Shanahan"
                                    data-type="creditor" >
                                    Clarabelle Shanahan - (creditor)
                                </option>
                                                            <option disabled value="110" data-title="Mrs. Alverta Keebler"
                                    data-type="creditor" >
                                    Mrs. Alverta Keebler - (creditor)
                                </option>
                                                            <option disabled value="237" data-title="Laurel Kling V"
                                    data-type="creditor" >
                                    Laurel Kling V - (creditor)
                                </option>
                                                            <option disabled value="211" data-title="Prof. Duane Moore V"
                                    data-type="creditor" >
                                    Prof. Duane Moore V - (creditor)
                                </option>
                                                            <option disabled value="168" data-title="Berenice Ziemann PhD"
                                    data-type="creditor" >
                                    Berenice Ziemann PhD - (creditor)
                                </option>
                                                            <option disabled value="115" data-title="Kip Jones"
                                    data-type="creditor" >
                                    Kip Jones - (creditor)
                                </option>
                                                            <option disabled value="172" data-title="Gordon Homenick"
                                    data-type="creditor" >
                                    Gordon Homenick - (creditor)
                                </option>
                                                            <option disabled value="229" data-title="Prof. Robb Streich DVM"
                                    data-type="creditor" >
                                    Prof. Robb Streich DVM - (creditor)
                                </option>
                                                            <option disabled value="148" data-title="Alec Reichert"
                                    data-type="creditor" >
                                    Alec Reichert - (creditor)
                                </option>
                                                            <option disabled value="203" data-title="Myrl O&#039;Conner"
                                    data-type="creditor" >
                                    Myrl O&#039;Conner - (creditor)
                                </option>
                                                            <option disabled value="106" data-title="Jennie Gleason"
                                    data-type="creditor" >
                                    Jennie Gleason - (creditor)
                                </option>
                                                            <option disabled value="129" data-title="Clementine Kub"
                                    data-type="creditor" >
                                    Clementine Kub - (creditor)
                                </option>
                                                            <option disabled value="206" data-title="Marcelina Conn"
                                    data-type="creditor" >
                                    Marcelina Conn - (creditor)
                                </option>
                                                            <option disabled value="150" data-title="Kacie Greenfelder PhD"
                                    data-type="creditor" >
                                    Kacie Greenfelder PhD - (creditor)
                                </option>
                                                            <option disabled value="216" data-title="Sienna Jacobi"
                                    data-type="creditor" >
                                    Sienna Jacobi - (creditor)
                                </option>
                                                            <option disabled value="247" data-title="Quinn O&#039;Hara"
                                    data-type="creditor" >
                                    Quinn O&#039;Hara - (creditor)
                                </option>
                                                            <option disabled value="184" data-title="Blanche Daniel"
                                    data-type="creditor" >
                                    Blanche Daniel - (creditor)
                                </option>
                                                            <option disabled value="160" data-title="Della Abshire MD"
                                    data-type="creditor" >
                                    Della Abshire MD - (creditor)
                                </option>
                                                            <option disabled value="235" data-title="Alize Schumm"
                                    data-type="creditor" >
                                    Alize Schumm - (creditor)
                                </option>
                                                            <option disabled value="207" data-title="Eloy Schneider"
                                    data-type="creditor" >
                                    Eloy Schneider - (creditor)
                                </option>
                                                            <option disabled value="179" data-title="Miss Lucy Kohler I"
                                    data-type="creditor" >
                                    Miss Lucy Kohler I - (creditor)
                                </option>
                                                            <option disabled value="126" data-title="Arvid Bahringer"
                                    data-type="creditor" >
                                    Arvid Bahringer - (creditor)
                                </option>
                                                            <option disabled value="144" data-title="Ardella Considine"
                                    data-type="creditor" >
                                    Ardella Considine - (creditor)
                                </option>
                                                            <option disabled value="180" data-title="Prof. Melody Lehner Jr."
                                    data-type="creditor" >
                                    Prof. Melody Lehner Jr. - (creditor)
                                </option>
                                                            <option disabled value="209" data-title="Heather Spinka"
                                    data-type="creditor" >
                                    Heather Spinka - (creditor)
                                </option>
                                                            <option disabled value="177" data-title="Abbigail Schuppe"
                                    data-type="creditor" >
                                    Abbigail Schuppe - (creditor)
                                </option>
                                                            <option disabled value="238" data-title="Katharina Hammes"
                                    data-type="creditor" >
                                    Katharina Hammes - (creditor)
                                </option>
                                                            <option disabled value="146" data-title="Ellsworth Bartoletti III"
                                    data-type="creditor" >
                                    Ellsworth Bartoletti III - (creditor)
                                </option>
                                                            <option disabled value="178" data-title="Dr. Hermina Veum I"
                                    data-type="creditor" >
                                    Dr. Hermina Veum I - (creditor)
                                </option>
                                                            <option disabled value="192" data-title="Elouise Borer DVM"
                                    data-type="creditor" >
                                    Elouise Borer DVM - (creditor)
                                </option>
                                                            <option disabled value="213" data-title="Dr. Velda Brekke"
                                    data-type="creditor" >
                                    Dr. Velda Brekke - (creditor)
                                </option>
                                                            <option disabled value="102" data-title="Dr. Katrine Fisher PhD"
                                    data-type="creditor" >
                                    Dr. Katrine Fisher PhD - (creditor)
                                </option>
                                                            <option disabled value="198" data-title="Prof. Sallie Larkin I"
                                    data-type="creditor" >
                                    Prof. Sallie Larkin I - (creditor)
                                </option>
                                                            <option disabled value="232" data-title="Dr. Claudine Hintz"
                                    data-type="creditor" >
                                    Dr. Claudine Hintz - (creditor)
                                </option>
                                                            <option disabled value="114" data-title="Abbie Herzog DDS"
                                    data-type="creditor" >
                                    Abbie Herzog DDS - (creditor)
                                </option>
                                                            <option disabled value="241" data-title="Alek Gerhold DDS"
                                    data-type="creditor" >
                                    Alek Gerhold DDS - (creditor)
                                </option>
                                                            <option disabled value="164" data-title="Lesley Corwin"
                                    data-type="creditor" >
                                    Lesley Corwin - (creditor)
                                </option>
                                                            <option disabled value="155" data-title="Mrs. Aiyana Corwin"
                                    data-type="creditor" >
                                    Mrs. Aiyana Corwin - (creditor)
                                </option>
                                                            <option disabled value="158" data-title="Bobbie Lowe"
                                    data-type="creditor" >
                                    Bobbie Lowe - (creditor)
                                </option>
                                                            <option disabled value="118" data-title="Josiane McDermott III"
                                    data-type="creditor" >
                                    Josiane McDermott III - (creditor)
                                </option>
                                                            <option disabled value="197" data-title="Reese O&#039;Connell"
                                    data-type="creditor" >
                                    Reese O&#039;Connell - (creditor)
                                </option>
                                                            <option disabled value="130" data-title="Germaine Bartell"
                                    data-type="creditor" >
                                    Germaine Bartell - (creditor)
                                </option>
                                                            <option disabled value="212" data-title="Mrs. Kitty O&#039;Keefe I"
                                    data-type="creditor" >
                                    Mrs. Kitty O&#039;Keefe I - (creditor)
                                </option>
                                                            <option disabled value="117" data-title="Murl Herman"
                                    data-type="creditor" >
                                    Murl Herman - (creditor)
                                </option>
                                                            <option disabled value="131" data-title="Aidan Hahn IV"
                                    data-type="creditor" >
                                    Aidan Hahn IV - (creditor)
                                </option>
                                                            <option disabled value="200" data-title="Mortimer Kuvalis"
                                    data-type="creditor" >
                                    Mortimer Kuvalis - (creditor)
                                </option>
                                                            <option disabled value="120" data-title="Ernestine Brown"
                                    data-type="creditor" >
                                    Ernestine Brown - (creditor)
                                </option>
                                                            <option disabled value="161" data-title="Adrain Marquardt MD"
                                    data-type="creditor" >
                                    Adrain Marquardt MD - (creditor)
                                </option>
                                                            <option disabled value="159" data-title="Adelle Runolfsdottir"
                                    data-type="creditor" >
                                    Adelle Runolfsdottir - (creditor)
                                </option>
                                                            <option disabled value="109" data-title="Chyna Prosacco"
                                    data-type="creditor" >
                                    Chyna Prosacco - (creditor)
                                </option>
                                                            <option disabled value="221" data-title="Dr. Deion Spencer"
                                    data-type="creditor" >
                                    Dr. Deion Spencer - (creditor)
                                </option>
                                                            <option disabled value="219" data-title="Prof. Jermain Rice"
                                    data-type="creditor" >
                                    Prof. Jermain Rice - (creditor)
                                </option>
                                                            <option disabled value="224" data-title="Nigel Bartell"
                                    data-type="creditor" >
                                    Nigel Bartell - (creditor)
                                </option>
                                                            <option disabled value="199" data-title="Ms. Eveline Wolff PhD"
                                    data-type="creditor" >
                                    Ms. Eveline Wolff PhD - (creditor)
                                </option>
                                                            <option disabled value="141" data-title="Buddy Nader"
                                    data-type="creditor" >
                                    Buddy Nader - (creditor)
                                </option>
                                                            <option disabled value="175" data-title="Magnolia Davis"
                                    data-type="creditor" >
                                    Magnolia Davis - (creditor)
                                </option>
                                                            <option disabled value="167" data-title="Prof. Frederic Reinger"
                                    data-type="creditor" >
                                    Prof. Frederic Reinger - (creditor)
                                </option>
                                                            <option disabled value="183" data-title="Mr. Ezra Corwin DDS"
                                    data-type="creditor" >
                                    Mr. Ezra Corwin DDS - (creditor)
                                </option>
                                                            <option disabled value="152" data-title="Dr. Curtis Schmeler"
                                    data-type="creditor" >
                                    Dr. Curtis Schmeler - (creditor)
                                </option>
                                                            <option disabled value="218" data-title="Prof. Mayra Goldner"
                                    data-type="creditor" >
                                    Prof. Mayra Goldner - (creditor)
                                </option>
                                                            <option disabled value="245" data-title="Valentin Bednar"
                                    data-type="creditor" >
                                    Valentin Bednar - (creditor)
                                </option>
                                                            <option disabled value="220" data-title="Mrs. Caleigh Hermann"
                                    data-type="creditor" >
                                    Mrs. Caleigh Hermann - (creditor)
                                </option>
                                                            <option disabled value="137" data-title="Mrs. Chyna Ebert"
                                    data-type="creditor" >
                                    Mrs. Chyna Ebert - (creditor)
                                </option>
                                                            <option disabled value="231" data-title="Prof. Milan Torphy Sr."
                                    data-type="creditor" >
                                    Prof. Milan Torphy Sr. - (creditor)
                                </option>
                                                            <option disabled value="205" data-title="Vidal Schroeder"
                                    data-type="creditor" >
                                    Vidal Schroeder - (creditor)
                                </option>
                                                            <option disabled value="111" data-title="Wyatt Hane"
                                    data-type="creditor" >
                                    Wyatt Hane - (creditor)
                                </option>
                                                            <option disabled value="201" data-title="Dr. Adaline Kuphal"
                                    data-type="creditor" >
                                    Dr. Adaline Kuphal - (creditor)
                                </option>
                                                            <option disabled value="105" data-title="Ashton Pagac"
                                    data-type="creditor" >
                                    Ashton Pagac - (creditor)
                                </option>
                                                            <option disabled value="156" data-title="Jerrell Harris"
                                    data-type="creditor" >
                                    Jerrell Harris - (creditor)
                                </option>
                                                            <option disabled value="227" data-title="Ms. Meredith Collier"
                                    data-type="creditor" >
                                    Ms. Meredith Collier - (creditor)
                                </option>
                                                            <option disabled value="228" data-title="Baron Lesch"
                                    data-type="creditor" >
                                    Baron Lesch - (creditor)
                                </option>
                                                            <option disabled value="127" data-title="Hester Bergstrom"
                                    data-type="creditor" >
                                    Hester Bergstrom - (creditor)
                                </option>
                                                            <option disabled value="142" data-title="Prof. Lorenz Champlin DVM"
                                    data-type="creditor" >
                                    Prof. Lorenz Champlin DVM - (creditor)
                                </option>
                                                            <option disabled value="138" data-title="Prof. Myrtice Hilpert DDS"
                                    data-type="creditor" >
                                    Prof. Myrtice Hilpert DDS - (creditor)
                                </option>
                                                            <option disabled value="162" data-title="Prof. Raymundo Prosacco PhD"
                                    data-type="creditor" >
                                    Prof. Raymundo Prosacco PhD - (creditor)
                                </option>
                            
                        </select>
                    </div>
                    <div class="flex-1 min-w-80 pb-3">
                        <label class="flex flex-col flex-wrap w-full" value="" placeholder="توضیحات این سطر از سند حسابداری" id="desc" name="transactions[0][desc]" label_text_class="text-gray-500" label_class="w-full" input_class="">
    <span  class="text-gray-500" value="" placeholder="توضیحات این سطر از سند حسابداری" id="desc" name="transactions[0][desc]" label_text_class="text-gray-500" label_class="w-full" input_class="">
        
    </span>
    <input onkeyup="" " id="" name="transactions[0][desc]" value=""
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42" value="" placeholder="توضیحات این سطر از سند حسابداری" id="desc" name="transactions[0][desc]" label_text_class="text-gray-500" label_class="w-full" input_class="" type="text"
           placeholder=" توضیحات این سطر از سند حسابداری"/>
</label>

                    </div>

                    <div class="flex-1 min-w-24 max-w-24 pb-3">
                        <label class="flex flex-col flex-wrap w-full" value="" placeholder="0" id="debit" name="transactions[0][debit]" label_text_class="text-gray-500" label_class="w-full" input_class="debitInput">
    <span  class="text-gray-500" value="" placeholder="0" id="debit" name="transactions[0][debit]" label_text_class="text-gray-500" label_class="w-full" input_class="debitInput">
        
    </span>
    <input onkeyup="" " id="" name="transactions[0][debit]" value=""
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 debitInput" value="" placeholder="0" id="debit" name="transactions[0][debit]" label_text_class="text-gray-500" label_class="w-full" input_class="debitInput" type="text"
           placeholder=" 0"/>
</label>
                    </div>
                    <div class="flex-1 min-w-24 max-w-24 pb-3">
                        <label class="flex flex-col flex-wrap w-full" value="" placeholder="0" id="credit" name="transactions[0][credit]" label_text_class="text-gray-500" label_class="w-full" input_class="creditInput">
    <span  class="text-gray-500" value="" placeholder="0" id="credit" name="transactions[0][credit]" label_text_class="text-gray-500" label_class="w-full" input_class="creditInput">
        
    </span>
    <input onkeyup="" " id="" name="transactions[0][credit]" value=""
           class="max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 creditInput" value="" placeholder="0" id="credit" name="transactions[0][credit]" label_text_class="text-gray-500" label_class="w-full" input_class="creditInput" type="text"
           placeholder=" 0"/>
</label>

                    </div>
                </div>
                    </div>

        <div class="flex justify-content gap-4 align-center">
            <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active" id="addTransaction">
                <span class="text-2xl">+</span>
                افزودن تراکنش
            </div>
        </div>
    </div>

    <hr style="">
    <div class="flex justify-end px-4 gap-2">
        <span class="min-w-24 text-center text-gray-500" id="debitSum">1000</span>
        <span class="min-w-24 text-center text-gray-500" id="creditSum">2000</span>
    </div>
    </div>
</div>
<div class="mt-2 flex gap-2 justify-end">
    <a href="https://develop.freeamir.com/documents" type="submit" class="btn btn-default rounded-md"> لغو </a>
    <button type="submit" class="btn btn-default rounded-md"> ذخیره و ایجاد سند جدید </button>
    <button type="submit" class="btn text-white btn-primary rounded-md"> ذخیره و بستن فرم </button>
</div>
<script type="module">
    jalaliDatepicker.startWatch({});
</script>
<script>
    var subjects = [{"id":248,"code":"00806169","name":"Prof. Orion Von Sr.","type":"creditor","_lft":399,"_rgt":400,"parent_id":null,"created_at":"2024-08-07T10:37:05.000000Z","updated_at":"2024-08-07T10:37:05.000000Z"},{"id":1,"code":"010","name":"\u0628\u0627\u0646\u06a9\u0647\u0627","type":"both","_lft":1,"_rgt":2,"parent_id":0,"created_at":null,"updated_at":null},{"id":3,"code":"011","name":"\u0645\u0648\u062c\u0648\u062f\u06cc\u0647\u0627\u06cc \u0646\u0642\u062f\u06cc","type":"both","_lft":29,"_rgt":32,"parent_id":0,"created_at":null,"updated_at":null},{"id":14,"code":"011001","name":"\u0635\u0646\u062f\u0648\u0642","type":"both","_lft":30,"_rgt":31,"parent_id":3,"created_at":null,"updated_at":null},{"id":59,"code":"011002","name":"\u062a\u0646\u062e\u0648\u0627\u0647 \u06af\u0631\u062f\u0627\u0646\u0647\u0627","type":"both","_lft":30,"_rgt":31,"parent_id":3,"created_at":null,"updated_at":null},{"id":4,"code":"012","name":"\u0628\u062f\u0647\u06a9\u0627\u0631\u0627\u0646\/\u0628\u0633\u062a\u0627\u0646\u06a9\u0627\u0631\u0627\u0646","type":"both","_lft":33,"_rgt":34,"parent_id":0,"created_at":null,"updated_at":null},{"id":58,"code":"012001","name":"\u0627\u0634\u062e\u0627\u0635 \u0645\u062a\u0641\u0631\u0642\u0647","type":"both","_lft":33,"_rgt":34,"parent_id":4,"created_at":null,"updated_at":null},{"id":104,"code":"01295894","name":"Lexus McKenzie","type":"creditor","_lft":111,"_rgt":112,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":6,"code":"013","name":"\u0627\u0633\u0646\u0627\u062f \u062f\u0631\u06cc\u0627\u0641\u062a\u0646\u06cc","type":"both","_lft":39,"_rgt":42,"parent_id":0,"created_at":null,"updated_at":null},{"id":44,"code":"013001","name":"\u0627\u0633\u0646\u0627\u062f \u062f\u0631\u06cc\u0627\u0641\u062a\u0646\u06cc","type":"both","_lft":40,"_rgt":41,"parent_id":6,"created_at":null,"updated_at":null},{"id":107,"code":"01319996","name":"Prof. Silas Morar IV","type":"creditor","_lft":117,"_rgt":118,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":67,"code":"014","name":"\u0627\u0633\u0646\u0627\u062f \u062f\u0631 \u062c\u0631\u06cc\u0627\u0646 \u0648\u0635\u0648\u0644","type":"both","_lft":30,"_rgt":31,"parent_id":0,"created_at":null,"updated_at":null},{"id":68,"code":"014001","name":"\u0627\u0633\u0646\u0627\u062f \u062f\u0631 \u062c\u0631\u06cc\u0627\u0646 \u0648\u0635\u0648\u0644","type":"both","_lft":30,"_rgt":31,"parent_id":67,"created_at":null,"updated_at":null},{"id":69,"code":"015","name":"\u0645\u0648\u062c\u0648\u062f\u06cc \u0645\u0648\u0627\u062f \u0648 \u06a9\u0627\u0644\u0627","type":"both","_lft":30,"_rgt":31,"parent_id":0,"created_at":null,"updated_at":null},{"id":7,"code":"015001","name":"\u0645\u0648\u062c\u0648\u062f\u06cc \u0645\u0648\u0627\u062f \u0627\u0648\u0644\u06cc\u0647","type":"both","_lft":30,"_rgt":31,"parent_id":69,"created_at":null,"updated_at":null},{"id":70,"code":"015002","name":"\u0645\u0648\u062c\u0648\u062f\u06cc \u0645\u0648\u0627\u062f \u0648 \u06a9\u0627\u0644\u0627","type":"both","_lft":30,"_rgt":31,"parent_id":69,"created_at":null,"updated_at":null},{"id":71,"code":"016","name":"\u067e\u06cc\u0634 \u067e\u0631\u062f\u0627\u062e\u062a \u0647\u0627","type":"both","_lft":30,"_rgt":31,"parent_id":0,"created_at":null,"updated_at":null},{"id":72,"code":"016001","name":"\u067e\u06cc\u0634 \u067e\u0631\u062f\u0627\u062e\u062a \u0645\u0627\u0644\u06cc\u0627\u062a","type":"both","_lft":30,"_rgt":31,"parent_id":71,"created_at":null,"updated_at":null},{"id":73,"code":"016002","name":"\u067e\u06cc\u0634 \u067e\u0631\u062f\u0627\u062e\u062a \u0627\u062c\u0627\u0631\u0647","type":"both","_lft":30,"_rgt":31,"parent_id":71,"created_at":null,"updated_at":null},{"id":74,"code":"016003","name":"\u067e\u06cc\u0634 \u067e\u0631\u062f\u0627\u062e\u062a \u0647\u0632\u06cc\u0646\u0647 \u0647\u0627\u06cc \u062c\u0627\u0631\u06cc","type":"both","_lft":30,"_rgt":31,"parent_id":71,"created_at":null,"updated_at":null},{"id":75,"code":"017","name":"\u062f\u0627\u0631\u0627\u06cc\u06cc \u0647\u0627\u06cc \u063a\u06cc\u0631 \u062c\u0627\u0631\u06cc","type":"both","_lft":30,"_rgt":31,"parent_id":0,"created_at":null,"updated_at":null},{"id":76,"code":"017001","name":"\u0627\u0645\u0648\u0627\u0644\u060c \u0645\u0627\u0634\u06cc\u0646 \u0622\u0644\u0627\u062a \u0648 \u062a\u062c\u0647\u06cc\u0632\u0627\u062a","type":"both","_lft":30,"_rgt":31,"parent_id":75,"created_at":null,"updated_at":null},{"id":77,"code":"017002","name":"\u0627\u0633\u062a\u0647\u0644\u0627\u06a9 \u0627\u0646\u0628\u0627\u0634\u062a\u0647 \u0627\u0645\u0648\u0627\u0644\u060c \u0645\u0627\u0634\u06cc\u0646 \u0622\u0644\u0627\u062a \u0648 \u062a\u062c\u0647\u06cc\u0632\u0627\u062a","type":"both","_lft":30,"_rgt":31,"parent_id":75,"created_at":null,"updated_at":null},{"id":78,"code":"017003","name":"\u0633\u0631\u0645\u0627\u06cc\u0647 \u06af\u0630\u0627\u0631\u06cc \u0647\u0627\u06cc \u0628\u0644\u0646\u062f \u0645\u062f\u062a","type":"both","_lft":30,"_rgt":31,"parent_id":75,"created_at":null,"updated_at":null},{"id":79,"code":"017004","name":"\u0633\u067e\u0631\u062f\u0647 \u0647\u0627 \u0648 \u0645\u0637\u0627\u0644\u0628\u0627\u062a \u0628\u0644\u0646\u062f\u0645\u062f\u062a","type":"both","_lft":30,"_rgt":31,"parent_id":75,"created_at":null,"updated_at":null},{"id":80,"code":"017005","name":"\u0633\u0627\u06cc\u0631 \u062f\u0627\u0631\u0627\u06cc\u06cc \u0647\u0627","type":"both","_lft":30,"_rgt":31,"parent_id":75,"created_at":null,"updated_at":null},{"id":38,"code":"018","name":"\u0633\u0627\u06cc\u0631 \u062d\u0633\u0627\u0628\u0647\u0627\u06cc \u062f\u0631\u06cc\u0627\u0641\u062a\u0646\u06cc","type":"both","_lft":89,"_rgt":94,"parent_id":0,"created_at":null,"updated_at":null},{"id":40,"code":"018001","name":"\u0645\u0627\u0644\u06cc\u0627\u062a \u0628\u0631 \u0627\u0631\u0632\u0634 \u0627\u0641\u0632\u0648\u062f\u0647 \u062e\u0631\u06cc\u062f","type":"both","_lft":90,"_rgt":91,"parent_id":38,"created_at":null,"updated_at":null},{"id":56,"code":"018002","name":"\u0639\u0648\u0627\u0631\u0636 \u062e\u0631\u06cc\u062f","type":"both","_lft":92,"_rgt":93,"parent_id":38,"created_at":null,"updated_at":null},{"id":62,"code":"018003","name":"\u0645\u0633\u0627\u0639\u062f\u0647 \u062d\u0642\u0648\u0642","type":"both","_lft":89,"_rgt":94,"parent_id":38,"created_at":null,"updated_at":null},{"id":63,"code":"018004","name":"\u062c\u0627\u0631\u06cc \u06a9\u0627\u0631\u06a9\u0646\u0627\u0646","type":"both","_lft":89,"_rgt":94,"parent_id":38,"created_at":null,"updated_at":null},{"id":64,"code":"018005","name":"\u062d\u0642 \u0628\u06cc\u0645\u0647 5\u062f\u0631\u0635\u062f \u0645\u06a9\u0633\u0648\u0631\u0647 \u0627\u0632 \u0635\u0648\u0631\u062a \u0648\u0636\u0639\u06cc\u062a","type":"both","_lft":89,"_rgt":94,"parent_id":38,"created_at":null,"updated_at":null},{"id":22,"code":"020","name":"\u0627\u0633\u0646\u0627\u062f \u067e\u0631\u062f\u0627\u062e\u062a\u0646\u06cc","type":"both","_lft":65,"_rgt":68,"parent_id":0,"created_at":null,"updated_at":null},{"id":46,"code":"020001","name":"\u0627\u0633\u0646\u0627\u062f \u067e\u0631\u062f\u0627\u062e\u062a\u0646\u06cc","type":"both","_lft":66,"_rgt":67,"parent_id":22,"created_at":null,"updated_at":null},{"id":163,"code":"02018874","name":"Henri Durgan","type":"creditor","_lft":229,"_rgt":230,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":145,"code":"02167121","name":"Prof. Bennett Cronin IV","type":"creditor","_lft":193,"_rgt":194,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":81,"code":"022","name":"\u067e\u06cc\u0634 \u062f\u0631\u06cc\u0627\u0641\u062a \u0647\u0627","type":"both","_lft":30,"_rgt":31,"parent_id":0,"created_at":null,"updated_at":null},{"id":82,"code":"022001","name":"\u067e\u06cc\u0634 \u062f\u0631\u06cc\u0627\u0641\u062a \u0641\u0631\u0648\u0634 \u0645\u062d\u0635\u0648\u0644\u0627\u062a","type":"both","_lft":30,"_rgt":31,"parent_id":81,"created_at":null,"updated_at":null},{"id":83,"code":"022002","name":"\u0633\u0627\u06cc\u0631 \u067e\u06cc\u0634 \u062f\u0631\u06cc\u0627\u0641\u062a \u0647\u0627","type":"both","_lft":30,"_rgt":31,"parent_id":81,"created_at":null,"updated_at":null},{"id":39,"code":"023","name":"\u0633\u0627\u06cc\u0631 \u062d\u0633\u0627\u0628\u0647\u0627\u06cc \u067e\u0631\u062f\u0627\u062e\u062a\u0646\u06cc","type":"both","_lft":95,"_rgt":100,"parent_id":0,"created_at":null,"updated_at":null},{"id":41,"code":"023001","name":"\u0645\u0627\u0644\u06cc\u0627\u062a \u0628\u0631 \u0627\u0631\u0632\u0634 \u0627\u0641\u0632\u0648\u062f\u0647 \u0641\u0631\u0648\u0634","type":"both","_lft":96,"_rgt":97,"parent_id":39,"created_at":null,"updated_at":null},{"id":57,"code":"023002","name":"\u0639\u0648\u0627\u0631\u0636 \u0641\u0631\u0648\u0634","type":"both","_lft":98,"_rgt":99,"parent_id":39,"created_at":null,"updated_at":null},{"id":66,"code":"023003","name":"\u0639\u06cc\u062f\u06cc \u0648 \u067e\u0627\u062f\u0627\u0634 \u067e\u0631\u062f\u0627\u062e\u062a\u0646\u06cc","type":"both","_lft":95,"_rgt":100,"parent_id":39,"created_at":null,"updated_at":null},{"id":84,"code":"030","name":"\u062d\u0642\u0648\u0642 \u0635\u0627\u062d\u0628\u0627\u0646 \u0633\u0647\u0627\u0645","type":"both","_lft":30,"_rgt":31,"parent_id":0,"created_at":null,"updated_at":null},{"id":21,"code":"030001","name":"\u0633\u0631\u0645\u0627\u06cc\u0647","type":"both","_lft":30,"_rgt":31,"parent_id":84,"created_at":null,"updated_at":null},{"id":85,"code":"030002","name":"\u0627\u0646\u062f\u0648\u062e\u062a\u0647 \u0642\u0627\u0646\u0648\u0646\u06cc","type":"both","_lft":30,"_rgt":31,"parent_id":84,"created_at":null,"updated_at":null},{"id":86,"code":"030003","name":"\u0633\u0648\u062f (\u0632\u06cc\u0627\u0646) \u0627\u0646\u0628\u0627\u0634\u062a\u0647","type":"both","_lft":30,"_rgt":31,"parent_id":84,"created_at":null,"updated_at":null},{"id":96,"code":"030004","name":"\u0633\u0648\u062f (\u0632\u06cc\u0627\u0646) \u062c\u0627\u0631\u06cc","type":"both","_lft":30,"_rgt":31,"parent_id":84,"created_at":null,"updated_at":null},{"id":87,"code":"030005","name":"\u062a\u0642\u0633\u06cc\u0645 \u0633\u0648\u062f","type":"both","_lft":30,"_rgt":31,"parent_id":84,"created_at":null,"updated_at":null},{"id":196,"code":"03681145","name":"Orville Zulauf","type":"creditor","_lft":295,"_rgt":296,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":210,"code":"03879221","name":"Ezra Zemlak","type":"creditor","_lft":323,"_rgt":324,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":2,"code":"040","name":"\u0647\u0632\u06cc\u0646\u0647 \u0647\u0627","type":"debtor","_lft":3,"_rgt":28,"parent_id":0,"created_at":null,"updated_at":null},{"id":10,"code":"040001","name":"\u062d\u0642\u0648\u0642 \u067e\u0631\u0633\u0646\u0644","type":"debtor","_lft":4,"_rgt":5,"parent_id":2,"created_at":null,"updated_at":null},{"id":11,"code":"040002","name":"\u0622\u0628","type":"debtor","_lft":6,"_rgt":7,"parent_id":2,"created_at":null,"updated_at":null},{"id":12,"code":"040003","name":"\u0628\u0631\u0642","type":"debtor","_lft":8,"_rgt":9,"parent_id":2,"created_at":null,"updated_at":null},{"id":13,"code":"040004","name":"\u062a\u0644\u0641\u0646","type":"debtor","_lft":10,"_rgt":11,"parent_id":2,"created_at":null,"updated_at":null},{"id":26,"code":"040005","name":"\u06af\u0627\u0632","type":"debtor","_lft":12,"_rgt":13,"parent_id":2,"created_at":null,"updated_at":null},{"id":27,"code":"040006","name":"\u067e\u0633\u062a","type":"debtor","_lft":16,"_rgt":17,"parent_id":2,"created_at":null,"updated_at":null},{"id":28,"code":"040007","name":"\u0647\u0632\u06cc\u0646\u0647 \u062d\u0645\u0644","type":"debtor","_lft":16,"_rgt":17,"parent_id":2,"created_at":null,"updated_at":null},{"id":29,"code":"040008","name":"\u0636\u0627\u06cc\u0639\u0627\u062a \u06a9\u0627\u0644\u0627","type":"debtor","_lft":18,"_rgt":19,"parent_id":2,"created_at":null,"updated_at":null},{"id":30,"code":"040009","name":"\u0639\u0648\u0627\u0631\u0636 \u0634\u0647\u0631\u062f\u0627\u0631\u06cc","type":"debtor","_lft":20,"_rgt":21,"parent_id":2,"created_at":null,"updated_at":null},{"id":31,"code":"040010","name":"\u06a9\u0627\u0631\u0645\u0632\u062f \u0628\u0627\u0646\u06a9","type":"debtor","_lft":22,"_rgt":23,"parent_id":2,"created_at":null,"updated_at":null},{"id":33,"code":"040011","name":"\u0645\u0627\u0644\u06cc\u0627\u062a","type":"debtor","_lft":26,"_rgt":27,"parent_id":2,"created_at":null,"updated_at":null},{"id":34,"code":"040012","name":"\u0647\u0632\u06cc\u0646\u0647 \u0627\u062c\u0627\u0631\u0647 \u0645\u062d\u0644","type":"debtor","_lft":26,"_rgt":27,"parent_id":2,"created_at":null,"updated_at":null},{"id":32,"code":"040013","name":"\u0647\u0632\u06cc\u0646\u0647 \u0647\u0627\u06cc \u0645\u062a\u0641\u0631\u0642\u0647","type":"debtor","_lft":26,"_rgt":27,"parent_id":2,"created_at":null,"updated_at":null},{"id":88,"code":"041","name":"\u0642\u06cc\u0645\u062a \u062a\u0645\u0627\u0645 \u0634\u062f\u0647 \u06a9\u0627\u0644\u0627\u06cc \u0641\u0631\u0648\u0634 \u0631\u0641\u062a\u0647","type":"both","_lft":30,"_rgt":31,"parent_id":0,"created_at":null,"updated_at":null},{"id":89,"code":"041001","name":"\u0642\u06cc\u0645\u062a \u062a\u0645\u0627\u0645 \u0634\u062f\u0647 \u06a9\u0627\u0644\u0627\u06cc \u0641\u0631\u0648\u0634 \u0631\u0641\u062a\u0647","type":"both","_lft":30,"_rgt":31,"parent_id":88,"created_at":null,"updated_at":null},{"id":135,"code":"04544753","name":"Eliza Braun","type":"creditor","_lft":173,"_rgt":174,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":23,"code":"050","name":"\u062f\u0631\u0622\u0645\u062f\u0647\u0627","type":"debtor","_lft":69,"_rgt":76,"parent_id":0,"created_at":null,"updated_at":null},{"id":36,"code":"050001","name":"\u062f\u0631\u0622\u0645\u062f \u0645\u062a\u0641\u0631\u0642\u0647","type":"debtor","_lft":74,"_rgt":75,"parent_id":23,"created_at":null,"updated_at":null},{"id":125,"code":"05283460","name":"Dr. Kenton King I","type":"creditor","_lft":153,"_rgt":154,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":188,"code":"05946099","name":"Joy McCullough","type":"creditor","_lft":279,"_rgt":280,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":18,"code":"060","name":"\u0641\u0631\u0648\u0634","type":"debtor","_lft":57,"_rgt":60,"parent_id":0,"created_at":null,"updated_at":null},{"id":20,"code":"060001","name":"\u0641\u0631\u0648\u0634","type":"debtor","_lft":58,"_rgt":59,"parent_id":18,"created_at":null,"updated_at":null},{"id":25,"code":"061","name":"\u0628\u0631\u06af\u0634\u062a \u0627\u0632 \u0641\u0631\u0648\u0634 \u0648 \u062a\u062e\u0641\u06cc\u0641\u0627\u062a","type":"both","_lft":83,"_rgt":88,"parent_id":0,"created_at":null,"updated_at":null},{"id":55,"code":"061001","name":"\u062a\u062e\u0641\u06cc\u0641\u0627\u062a \u0641\u0631\u0648\u0634","type":"both","_lft":86,"_rgt":87,"parent_id":25,"created_at":null,"updated_at":null},{"id":43,"code":"061002","name":"\u0628\u0631\u06af\u0634\u062a \u0627\u0632 \u0641\u0631\u0648\u0634","type":"both","_lft":86,"_rgt":87,"parent_id":25,"created_at":null,"updated_at":null},{"id":17,"code":"062","name":"\u062e\u0631\u06cc\u062f","type":"debtor","_lft":53,"_rgt":56,"parent_id":0,"created_at":null,"updated_at":null},{"id":19,"code":"062001","name":"\u062e\u0631\u06cc\u062f","type":"debtor","_lft":54,"_rgt":55,"parent_id":17,"created_at":null,"updated_at":null},{"id":24,"code":"063","name":"\u0628\u0631\u06af\u0634\u062a \u0627\u0632 \u062e\u0631\u06cc\u062f \u0648 \u062a\u062e\u0641\u06cc\u0641\u0627\u062a","type":"both","_lft":77,"_rgt":82,"parent_id":0,"created_at":null,"updated_at":null},{"id":42,"code":"063001","name":"\u0628\u0631\u06af\u0634\u062a \u0627\u0632 \u062e\u0631\u06cc\u062f","type":"both","_lft":80,"_rgt":81,"parent_id":24,"created_at":null,"updated_at":null},{"id":53,"code":"063002","name":"\u062a\u062e\u0641\u06cc\u0641\u0627\u062a \u062e\u0631\u06cc\u062f","type":"both","_lft":80,"_rgt":81,"parent_id":24,"created_at":null,"updated_at":null},{"id":90,"code":"064","name":"\u062d\u0633\u0627\u0628\u0647\u0627\u06cc \u0627\u0646\u062a\u0638\u0627\u0645\u06cc","type":"both","_lft":80,"_rgt":81,"parent_id":0,"created_at":null,"updated_at":null},{"id":91,"code":"064001","name":"\u062d\u0633\u0627\u0628\u0647\u0627\u06cc \u0627\u0646\u062a\u0638\u0627\u0645\u06cc \u0628\u0647 \u0646\u0641\u0639 \u0634\u0631\u06a9\u062a","type":"both","_lft":80,"_rgt":81,"parent_id":90,"created_at":null,"updated_at":null},{"id":92,"code":"064002","name":"\u062d\u0633\u0627\u0628\u0647\u0627\u06cc \u0627\u0646\u062a\u0638\u0627\u0645\u06cc \u0628\u0647 \u0639\u0647\u062f\u0647 \u0634\u0631\u06a9\u062a","type":"both","_lft":80,"_rgt":81,"parent_id":90,"created_at":null,"updated_at":null},{"id":93,"code":"065","name":"\u0637\u0631\u0641 \u062d\u0633\u0627\u0628\u0647\u0627\u06cc \u0627\u0646\u062a\u0638\u0627\u0645\u06cc","type":"both","_lft":80,"_rgt":81,"parent_id":0,"created_at":null,"updated_at":null},{"id":94,"code":"065001","name":"\u0637\u0631\u0641 \u062d\u0633\u0627\u0628 \u0627\u0646\u062a\u0638\u0627\u0645\u06cc \u0628\u0647 \u0646\u0641\u0639 \u0634\u0631\u06a9\u062a","type":"both","_lft":80,"_rgt":81,"parent_id":93,"created_at":null,"updated_at":null},{"id":95,"code":"065002","name":"\u0637\u0631\u0641 \u062d\u0633\u0627\u0628 \u0627\u0646\u062a\u0638\u0627\u0645\u06cc \u0628\u0647 \u0639\u0647\u062f\u0647 \u0634\u0631\u06a9\u062a","type":"both","_lft":80,"_rgt":81,"parent_id":93,"created_at":null,"updated_at":null},{"id":97,"code":"066","name":"\u062a\u062e\u0641\u06cc\u0641\u0627\u062a \u0646\u0642\u062f\u06cc","type":"both","_lft":80,"_rgt":81,"parent_id":0,"created_at":null,"updated_at":null},{"id":98,"code":"066001","name":"\u062a\u062e\u0641\u06cc\u0641\u0627\u062a \u0646\u0642\u062f\u06cc","type":"both","_lft":80,"_rgt":81,"parent_id":97,"created_at":null,"updated_at":null},{"id":5,"code":"067","name":"\u062a\u0631\u0627\u0632 \u0627\u0641\u062a\u062a\u0627\u062d\u06cc\u0647","type":"both","_lft":35,"_rgt":38,"parent_id":0,"created_at":null,"updated_at":null},{"id":15,"code":"067001","name":"\u062a\u0631\u0627\u0632 \u0627\u0641\u062a\u062a\u0627\u062d\u06cc\u0647","type":"both","_lft":36,"_rgt":37,"parent_id":5,"created_at":null,"updated_at":null},{"id":8,"code":"068","name":"\u062c\u0627\u0631\u06cc \u0634\u0631\u06a9\u0627","type":"both","_lft":47,"_rgt":50,"parent_id":0,"created_at":null,"updated_at":null},{"id":37,"code":"068001","name":"\u062c\u0627\u0631\u06cc \u0634\u0631\u06a9\u0627","type":"debtor","_lft":48,"_rgt":49,"parent_id":8,"created_at":null,"updated_at":null},{"id":134,"code":"06870867","name":"Kayleigh Armstrong III","type":"creditor","_lft":171,"_rgt":172,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":204,"code":"07057762","name":"Nadia Gulgowski","type":"creditor","_lft":311,"_rgt":312,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":225,"code":"07174063","name":"Roderick Jerde","type":"creditor","_lft":353,"_rgt":354,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":239,"code":"07362316","name":"Frank Hintz","type":"creditor","_lft":381,"_rgt":382,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":185,"code":"07394478","name":"Buck Volkman","type":"creditor","_lft":273,"_rgt":274,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":100,"code":"09214019","name":"Yasmeen Lockman","type":"creditor","_lft":103,"_rgt":104,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":243,"code":"09715417","name":"Cora Kutch","type":"creditor","_lft":389,"_rgt":390,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":174,"code":"09748583","name":"Ignacio Yost","type":"creditor","_lft":251,"_rgt":252,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":153,"code":"09901278","name":"Felicity Hansen II","type":"creditor","_lft":209,"_rgt":210,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":124,"code":"10067062","name":"Dr. Marty Schmeler PhD","type":"creditor","_lft":151,"_rgt":152,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":208,"code":"10575253","name":"Prof. Lora Robel","type":"creditor","_lft":319,"_rgt":320,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":128,"code":"10770481","name":"Barry Jast","type":"creditor","_lft":159,"_rgt":160,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":230,"code":"11492061","name":"Vincent Schmeler","type":"creditor","_lft":363,"_rgt":364,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":170,"code":"13134891","name":"Celestino Funk I","type":"creditor","_lft":243,"_rgt":244,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":116,"code":"13816612","name":"Cynthia Zieme","type":"creditor","_lft":135,"_rgt":136,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":101,"code":"14907647","name":"Prof. Eldridge Abbott Sr.","type":"creditor","_lft":105,"_rgt":106,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":143,"code":"15485687","name":"Eric Wiza","type":"creditor","_lft":189,"_rgt":190,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":244,"code":"15853356","name":"Amiya Littel","type":"creditor","_lft":391,"_rgt":392,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":226,"code":"16147904","name":"Kamron Grady","type":"creditor","_lft":355,"_rgt":356,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":189,"code":"16439092","name":"Turner Hand","type":"creditor","_lft":281,"_rgt":282,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":223,"code":"17460736","name":"Miss Mikayla Kuhn II","type":"creditor","_lft":349,"_rgt":350,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":123,"code":"17652001","name":"Dr. Kolby McDermott","type":"creditor","_lft":149,"_rgt":150,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":136,"code":"18490138","name":"Madie Bins","type":"creditor","_lft":175,"_rgt":176,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":176,"code":"20090142","name":"Prof. Manley Fay V","type":"creditor","_lft":255,"_rgt":256,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":181,"code":"21734793","name":"Fermin Lockman Sr.","type":"creditor","_lft":265,"_rgt":266,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":222,"code":"21837906","name":"Ahmed Schowalter I","type":"creditor","_lft":347,"_rgt":348,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":171,"code":"21888625","name":"Prof. Keshaun Pollich DVM","type":"creditor","_lft":245,"_rgt":246,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":214,"code":"22267610","name":"Mr. Waino Feil III","type":"creditor","_lft":331,"_rgt":332,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":194,"code":"23244504","name":"Kevon Mayert","type":"creditor","_lft":291,"_rgt":292,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":157,"code":"23680142","name":"Terry Hagenes","type":"creditor","_lft":217,"_rgt":218,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":215,"code":"25128499","name":"Keanu Funk V","type":"creditor","_lft":333,"_rgt":334,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":139,"code":"25338065","name":"Gonzalo Kilback II","type":"creditor","_lft":181,"_rgt":182,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":187,"code":"27290750","name":"Jerome Botsford","type":"creditor","_lft":277,"_rgt":278,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":236,"code":"27617878","name":"Franco Cassin","type":"creditor","_lft":375,"_rgt":376,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":103,"code":"27620083","name":"Cameron Jacobson","type":"creditor","_lft":109,"_rgt":110,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":151,"code":"29515448","name":"Francisco Ankunding I","type":"creditor","_lft":205,"_rgt":206,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":191,"code":"29579976","name":"Dr. Dayna Dickens","type":"creditor","_lft":285,"_rgt":286,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":132,"code":"30224513","name":"Maryjane Jakubowski","type":"creditor","_lft":167,"_rgt":168,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":182,"code":"30259317","name":"Prof. Samara Hettinger","type":"creditor","_lft":267,"_rgt":268,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":195,"code":"30945265","name":"Dr. Collin McDermott","type":"creditor","_lft":293,"_rgt":294,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":108,"code":"31916608","name":"Eldon Smith","type":"creditor","_lft":119,"_rgt":120,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":217,"code":"32324570","name":"Rosalind O'Conner","type":"creditor","_lft":337,"_rgt":338,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":186,"code":"32493900","name":"Amir Adams","type":"creditor","_lft":275,"_rgt":276,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":202,"code":"32554045","name":"Kurtis Durgan V","type":"creditor","_lft":307,"_rgt":308,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":149,"code":"33454665","name":"Dr. August Heaney IV","type":"creditor","_lft":201,"_rgt":202,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":140,"code":"33728216","name":"Jana Kling","type":"creditor","_lft":183,"_rgt":184,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":193,"code":"34265352","name":"Prof. Oran Walter DDS","type":"creditor","_lft":289,"_rgt":290,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":121,"code":"34391570","name":"Giovanna Christiansen","type":"creditor","_lft":145,"_rgt":146,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":233,"code":"34401033","name":"Mr. Jettie Mertz IV","type":"creditor","_lft":369,"_rgt":370,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":234,"code":"35488804","name":"Glennie Schoen","type":"creditor","_lft":371,"_rgt":372,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":169,"code":"36458455","name":"Ramona Hammes","type":"creditor","_lft":241,"_rgt":242,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":154,"code":"37738587","name":"Tara Kiehn","type":"creditor","_lft":211,"_rgt":212,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":242,"code":"39354983","name":"Casper Gottlieb DDS","type":"creditor","_lft":387,"_rgt":388,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":246,"code":"39594839","name":"Estevan Homenick","type":"creditor","_lft":395,"_rgt":396,"parent_id":null,"created_at":"2024-08-07T10:37:05.000000Z","updated_at":"2024-08-07T10:37:05.000000Z"},{"id":119,"code":"41637036","name":"Aliya Dibbert V","type":"creditor","_lft":141,"_rgt":142,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":99,"code":"42139911","name":"Kameron Parker","type":"creditor","_lft":101,"_rgt":102,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":112,"code":"42363262","name":"Prof. Liliana Gislason","type":"creditor","_lft":127,"_rgt":128,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":165,"code":"45462528","name":"Alexandro O'Reilly","type":"creditor","_lft":233,"_rgt":234,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":122,"code":"47887381","name":"Justus Kub","type":"creditor","_lft":147,"_rgt":148,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":240,"code":"48821278","name":"D'angelo Lesch","type":"creditor","_lft":383,"_rgt":384,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":147,"code":"48823920","name":"Lavada Gusikowski Sr.","type":"creditor","_lft":197,"_rgt":198,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":113,"code":"49262650","name":"Prof. Lisette Bergnaum IV","type":"creditor","_lft":129,"_rgt":130,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":173,"code":"49871708","name":"Abdullah Koch","type":"creditor","_lft":249,"_rgt":250,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":133,"code":"50822058","name":"Prof. Kamryn Schroeder DVM","type":"creditor","_lft":169,"_rgt":170,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":190,"code":"50947614","name":"Kitty Maggio","type":"creditor","_lft":283,"_rgt":284,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":166,"code":"51064204","name":"Clarabelle Shanahan","type":"creditor","_lft":235,"_rgt":236,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":110,"code":"51563721","name":"Mrs. Alverta Keebler","type":"creditor","_lft":123,"_rgt":124,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":237,"code":"51731250","name":"Laurel Kling V","type":"creditor","_lft":377,"_rgt":378,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":211,"code":"51780746","name":"Prof. Duane Moore V","type":"creditor","_lft":325,"_rgt":326,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":168,"code":"52429781","name":"Berenice Ziemann PhD","type":"creditor","_lft":239,"_rgt":240,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":115,"code":"52565656","name":"Kip Jones","type":"creditor","_lft":133,"_rgt":134,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":172,"code":"52816505","name":"Gordon Homenick","type":"creditor","_lft":247,"_rgt":248,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":229,"code":"53376633","name":"Prof. Robb Streich DVM","type":"creditor","_lft":361,"_rgt":362,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":148,"code":"53606747","name":"Alec Reichert","type":"creditor","_lft":199,"_rgt":200,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":203,"code":"54885066","name":"Myrl O'Conner","type":"creditor","_lft":309,"_rgt":310,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":106,"code":"54893467","name":"Jennie Gleason","type":"creditor","_lft":115,"_rgt":116,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":129,"code":"55712330","name":"Clementine Kub","type":"creditor","_lft":161,"_rgt":162,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":206,"code":"56375954","name":"Marcelina Conn","type":"creditor","_lft":315,"_rgt":316,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":150,"code":"56637595","name":"Kacie Greenfelder PhD","type":"creditor","_lft":203,"_rgt":204,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":216,"code":"57179780","name":"Sienna Jacobi","type":"creditor","_lft":335,"_rgt":336,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":247,"code":"58409459","name":"Quinn O'Hara","type":"creditor","_lft":397,"_rgt":398,"parent_id":null,"created_at":"2024-08-07T10:37:05.000000Z","updated_at":"2024-08-07T10:37:05.000000Z"},{"id":184,"code":"58517260","name":"Blanche Daniel","type":"creditor","_lft":271,"_rgt":272,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":160,"code":"58905890","name":"Della Abshire MD","type":"creditor","_lft":223,"_rgt":224,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":235,"code":"59120100","name":"Alize Schumm","type":"creditor","_lft":373,"_rgt":374,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":207,"code":"59567301","name":"Eloy Schneider","type":"creditor","_lft":317,"_rgt":318,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":179,"code":"59863496","name":"Miss Lucy Kohler I","type":"creditor","_lft":261,"_rgt":262,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":126,"code":"61260153","name":"Arvid Bahringer","type":"creditor","_lft":155,"_rgt":156,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":144,"code":"61582293","name":"Ardella Considine","type":"creditor","_lft":191,"_rgt":192,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":180,"code":"62312073","name":"Prof. Melody Lehner Jr.","type":"creditor","_lft":263,"_rgt":264,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":209,"code":"62738354","name":"Heather Spinka","type":"creditor","_lft":321,"_rgt":322,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":177,"code":"63157895","name":"Abbigail Schuppe","type":"creditor","_lft":257,"_rgt":258,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":238,"code":"63633122","name":"Katharina Hammes","type":"creditor","_lft":379,"_rgt":380,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":146,"code":"64787756","name":"Ellsworth Bartoletti III","type":"creditor","_lft":195,"_rgt":196,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":178,"code":"65109403","name":"Dr. Hermina Veum I","type":"creditor","_lft":259,"_rgt":260,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":192,"code":"66849155","name":"Elouise Borer DVM","type":"creditor","_lft":287,"_rgt":288,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":213,"code":"67225415","name":"Dr. Velda Brekke","type":"creditor","_lft":329,"_rgt":330,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":102,"code":"68110284","name":"Dr. Katrine Fisher PhD","type":"creditor","_lft":107,"_rgt":108,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":198,"code":"68402402","name":"Prof. Sallie Larkin I","type":"creditor","_lft":299,"_rgt":300,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":232,"code":"68729691","name":"Dr. Claudine Hintz","type":"creditor","_lft":367,"_rgt":368,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":114,"code":"68884314","name":"Abbie Herzog DDS","type":"creditor","_lft":131,"_rgt":132,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":241,"code":"69726231","name":"Alek Gerhold DDS","type":"creditor","_lft":385,"_rgt":386,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":164,"code":"70788921","name":"Lesley Corwin","type":"creditor","_lft":231,"_rgt":232,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":155,"code":"71256368","name":"Mrs. Aiyana Corwin","type":"creditor","_lft":213,"_rgt":214,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":158,"code":"72646083","name":"Bobbie Lowe","type":"creditor","_lft":219,"_rgt":220,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":118,"code":"73745013","name":"Josiane McDermott III","type":"creditor","_lft":139,"_rgt":140,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":197,"code":"73906599","name":"Reese O'Connell","type":"creditor","_lft":297,"_rgt":298,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":130,"code":"73908937","name":"Germaine Bartell","type":"creditor","_lft":163,"_rgt":164,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":212,"code":"74890170","name":"Mrs. Kitty O'Keefe I","type":"creditor","_lft":327,"_rgt":328,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":117,"code":"75268978","name":"Murl Herman","type":"creditor","_lft":137,"_rgt":138,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":131,"code":"75364236","name":"Aidan Hahn IV","type":"creditor","_lft":165,"_rgt":166,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":200,"code":"76502897","name":"Mortimer Kuvalis","type":"creditor","_lft":303,"_rgt":304,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":120,"code":"77571311","name":"Ernestine Brown","type":"creditor","_lft":143,"_rgt":144,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":161,"code":"78715899","name":"Adrain Marquardt MD","type":"creditor","_lft":225,"_rgt":226,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":159,"code":"79099202","name":"Adelle Runolfsdottir","type":"creditor","_lft":221,"_rgt":222,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":109,"code":"79359467","name":"Chyna Prosacco","type":"creditor","_lft":121,"_rgt":122,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":221,"code":"79366304","name":"Dr. Deion Spencer","type":"creditor","_lft":345,"_rgt":346,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":219,"code":"79853316","name":"Prof. Jermain Rice","type":"creditor","_lft":341,"_rgt":342,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":224,"code":"80294399","name":"Nigel Bartell","type":"creditor","_lft":351,"_rgt":352,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":199,"code":"81426973","name":"Ms. Eveline Wolff PhD","type":"creditor","_lft":301,"_rgt":302,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":141,"code":"82722517","name":"Buddy Nader","type":"creditor","_lft":185,"_rgt":186,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":175,"code":"83717062","name":"Magnolia Davis","type":"creditor","_lft":253,"_rgt":254,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":167,"code":"84130075","name":"Prof. Frederic Reinger","type":"creditor","_lft":237,"_rgt":238,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":183,"code":"84200808","name":"Mr. Ezra Corwin DDS","type":"creditor","_lft":269,"_rgt":270,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":152,"code":"84704320","name":"Dr. Curtis Schmeler","type":"creditor","_lft":207,"_rgt":208,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":218,"code":"84982544","name":"Prof. Mayra Goldner","type":"creditor","_lft":339,"_rgt":340,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":245,"code":"85192072","name":"Valentin Bednar","type":"creditor","_lft":393,"_rgt":394,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":220,"code":"86451307","name":"Mrs. Caleigh Hermann","type":"creditor","_lft":343,"_rgt":344,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":137,"code":"87002102","name":"Mrs. Chyna Ebert","type":"creditor","_lft":177,"_rgt":178,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":231,"code":"88654119","name":"Prof. Milan Torphy Sr.","type":"creditor","_lft":365,"_rgt":366,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":205,"code":"88759395","name":"Vidal Schroeder","type":"creditor","_lft":313,"_rgt":314,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":111,"code":"90952753","name":"Wyatt Hane","type":"creditor","_lft":125,"_rgt":126,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":201,"code":"91114280","name":"Dr. Adaline Kuphal","type":"creditor","_lft":305,"_rgt":306,"parent_id":null,"created_at":"2024-08-07T10:37:03.000000Z","updated_at":"2024-08-07T10:37:03.000000Z"},{"id":105,"code":"91257741","name":"Ashton Pagac","type":"creditor","_lft":113,"_rgt":114,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":156,"code":"93002158","name":"Jerrell Harris","type":"creditor","_lft":215,"_rgt":216,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"},{"id":227,"code":"94072976","name":"Ms. Meredith Collier","type":"creditor","_lft":357,"_rgt":358,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":228,"code":"94608137","name":"Baron Lesch","type":"creditor","_lft":359,"_rgt":360,"parent_id":null,"created_at":"2024-08-07T10:37:04.000000Z","updated_at":"2024-08-07T10:37:04.000000Z"},{"id":127,"code":"98274840","name":"Hester Bergstrom","type":"creditor","_lft":157,"_rgt":158,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":142,"code":"98590742","name":"Prof. Lorenz Champlin DVM","type":"creditor","_lft":187,"_rgt":188,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":138,"code":"98985722","name":"Prof. Myrtice Hilpert DDS","type":"creditor","_lft":179,"_rgt":180,"parent_id":null,"created_at":"2024-08-07T10:37:01.000000Z","updated_at":"2024-08-07T10:37:01.000000Z"},{"id":162,"code":"99002862","name":"Prof. Raymundo Prosacco PhD","type":"creditor","_lft":227,"_rgt":228,"parent_id":null,"created_at":"2024-08-07T10:37:02.000000Z","updated_at":"2024-08-07T10:37:02.000000Z"}];
    var p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))

    function onCodeInputChange(e, selectBox) {
        let code = e.target.value
        code = p2e(code)
        e.target.value = code
        let itemIndex = subjects.findIndex(i => (code === i.code && i.parent_id))
        if (itemIndex !== -1) selectBox.value = subjects[itemIndex].id;
    }

    function onCodeSelectBoxChange(e, codeInput) {
        let id = e.target.value
        let itemIndex = subjects.findIndex(i => parseInt(id) === parseInt(i.id))
        if (itemIndex !== -1) codeInput.value = subjects[itemIndex].code;
    }

    function deleteAction() {
        if (document.getElementsByClassName('removeTransaction').length > 1) {
            this.parentNode.parentNode.remove();
            updateTransactionCounter()
        }
    }

    function activeRow(e) {
        console.log(e.currentTarget)
        deactivateAllTransactionRow()
        e.currentTarget.classList.remove('deactivated-transaction-row')
    }

    function debitInputChange(e, creditInput) {
        let value = e.target.value
        value = p2e(value)
        e.target.value = parseInt(value) > 0 ? parseInt(value) : null
        if (value <= 0) e.target.value = null;
        else if (value > 0) creditInput.value = null
        updateSumCalculation()
    }

    function creditInputChange(e, debitInput) {
        let value = e.target.value
        value = p2e(value)
        e.target.value = parseInt(value) > 0 ? parseInt(value) : null
        if (value <= 0) e.target.value = null;
        else if (value > 0) debitInput.value = null
        updateSumCalculation();
    }

    function updateSumCalculation() {
        let debits = Array.from(document.getElementsByClassName('debitInput'))
        let credits = Array.from(document.getElementsByClassName('creditInput'))
        let sumDebit = 0;
        let sumCredit = 0;
        debits.map(i => i.value > 0 ? sumDebit += parseInt(i.value) : '')
        credits.map(i => i.value > 0 ? sumCredit += parseInt(i.value) : '')
        document.getElementById('creditSum').innerText = sumCredit
        document.getElementById('debitSum').innerText = sumDebit
    }

    updateSumCalculation()
        var codeInputs = document.getElementById('transactions').getElementsByClassName('codeInput')
    var codeSelectBoxs = document.getElementById('transactions').getElementsByClassName('codeSelectBox')
    var removeButtons = document.getElementById('transactions').getElementsByClassName('removeTransaction')
    var debitInputs = document.getElementById('transactions').getElementsByClassName('debitInput')
    var creditInputs = document.getElementById('transactions').getElementsByClassName('creditInput')

    for (var i = 0; i < codeInputs.length; i++) {
        let codeInput = codeInputs[i];
        let codeSelectBox = codeSelectBoxs[i];
        let removeButton = removeButtons[i];
        let debitInput = debitInputs[i];
        let creditInput = creditInputs[i];
        codeInput.addEventListener('keyup', (e) => onCodeInputChange(e, codeSelectBox))
        codeSelectBox.addEventListener('change', (e) => onCodeSelectBoxChange(e, codeInput))
        removeButton.addEventListener('click', deleteAction)
        debitInput.addEventListener('keyup', (e) => debitInputChange(e, creditInput))
        creditInput.addEventListener('keyup', (e) => creditInputChange(e, debitInput))
    }

    function deactivateAllTransactionRow() {
        let transactionsDiv = document.getElementById('transactions');
        let transactionDivs = transactionsDiv.getElementsByClassName('transaction');
        Array.from(transactionDivs).map(i => i.classList.add('deactivated-transaction-row'))
    }

    function updateTransactionCounter() {
        Array.from(document.getElementsByClassName('transaction-count')).map((element, index) => element.innerText = index + 1)
    }

    document.getElementById('addTransaction').addEventListener('click', function() {
        var transactionsDiv = document.getElementById('transactions');
        var transactionDivs = transactionsDiv.getElementsByClassName('transaction');
        var lastTransactionDiv = transactionDivs[transactionDivs.length - 1];
        var newTransactionDiv = lastTransactionDiv.cloneNode(true);
        deactivateAllTransactionRow();
        newTransactionDiv.classList.remove('deactivated-transaction-row');
        // Update the index in the name attribute
        var selects = newTransactionDiv.getElementsByTagName('select');
        for (var i = 0; i < selects.length; i++) {
            selects[i].name = selects[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length + ']');
            selects[i].value = ''
        }

        var inputs = newTransactionDiv.getElementsByTagName('input');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].name = inputs[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length + ']');
            inputs[i].value = ''
        }


        // Add the remove button event listener
        var removeButton = newTransactionDiv.getElementsByClassName('removeTransaction')[0];
        removeButton.addEventListener('click', deleteAction);

        // Add code onchange event listener
        var codeInput = newTransactionDiv.getElementsByClassName('codeInput')[0];
        var codeSelectBox = newTransactionDiv.getElementsByClassName('codeSelectBox')[0];
        codeInput.addEventListener('keyup', (e) => onCodeInputChange(e, codeSelectBox));
        codeSelectBox.addEventListener('change', (e) => onCodeSelectBoxChange(e, codeInput));


        // Add code onchange event listener
        var debitInput = newTransactionDiv.getElementsByClassName('debitInput')[0];
        var creditInput = newTransactionDiv.getElementsByClassName('creditInput')[0];
        debitInput.addEventListener('keyup', (e) => debitInputChange(e, creditInput));
        creditInput.addEventListener('keyup', (e) => creditInputChange(e, debitInput));


        // Append the new transaction div to the transactions div
        transactionsDiv.appendChild(newTransactionDiv);
        updateTransactionCounter()
    });
</script>



        </form>
    </div>

    </div>

</body>

</html>
