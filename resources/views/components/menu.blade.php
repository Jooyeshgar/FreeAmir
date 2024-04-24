<li><a href="/">{{ __('Home') }}</a></li>
<li>
    <details>
        <summary>عملیات</summary>
        <ul>
            <li><a href="">دریافت و پرداخت</li>
            <li><a href="">ثبت فاکترو فروش</a></li>
            <li><a href="">ثبت فاکتور خرید</a></li>
            <li><a href="">اضافه کردن طرف حساب</a></li>
        </ul>
    </details>
</li>
<li>
    <details>
        <summary>حسابداری</summary>
        <ul>
            <li><a href="{{ route('transactions.index') }}">{{ __('Transactions') }}</a></li>
            <li><a href="">فروش ها</a></li>
            <li><a href="">خرید ها</a></li>
            <li><a href="">چک ها</a></li>
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
                        <li><a href="">روزنامه</a></li>
                        <li><a href="">معین</a></li>
                        <li><a href="">کل</a></li>
                        <li><a href="">سود و زیان</a></li>
                    </ul>
                </details>
                </a>
            </li>
            <li>
                <details>
                    <summary>انبار</summary>
                    <ul>
                        <li><a href="{{ route('products.index') }}">{{ __('Products') }}</a></li>
                        <li><a href="{{ route('product-groups.index') }}">{{ __('Product Groups') }}</a>
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
        <summary>{{ __('Management') }}</summary>
        <ul>
            <li><a href="{{ route('subjects.index') }}">{{ __('Subjects') }}</a></li>
            <li><a href="{{ route('bank-accounts.index') }}">{{ __('Bank Accounts') }}</a></li>
            <li><a href="{{ route('customers.index') }}">{{ __('Customers') }} </a></li>
            <li><a href="{{ route('customer-groups.index') }}">{{ __('Customers Groups') }} </a></li>
            <li><a href="{{ route('banks.index') }}">{{ __('Banks') }}</a></li>
            <li><a href="{{ route('users.index') }}">{{ __('Users') }}</a></li>
            <li><a href="{{ route('permissions.index') }}">{{ __('Permissions') }}</a></li>
            <li><a href="{{ route('roles.index') }}">{{ __('Roles') }}</a></li>
            <li><a href="">تنظیمات</a></li>
            <li><a href="">پشتیبانی</a></li>
        </ul>
    </details>
</li>
