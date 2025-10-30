<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjectData = [
            ['id' => 1, 'code' => '010', 'name' => 'بانکها', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 2, 'code' => '040', 'name' => 'هزینه ها', 'parent_id' => null, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 3, 'code' => '011', 'name' => 'موجودیهای نقدی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 4, 'code' => '012', 'name' => 'بدهکاران/بستانکاران', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 5, 'code' => '067', 'name' => 'تراز افتتاحیه', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 6, 'code' => '013', 'name' => 'اسناد دریافتنی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 8, 'code' => '068', 'name' => 'جاری شرکا', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 9, 'code' => '019', 'name' => 'محصولات', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 17, 'code' => '062', 'name' => 'خرید', 'parent_id' => null, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 18, 'code' => '060', 'name' => 'فروش', 'parent_id' => null, 'type' => 1, 'company_id' => 1],
            ['id' => 22, 'code' => '020', 'name' => 'اسناد پرداختنی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 23, 'code' => '050', 'name' => 'درآمدها', 'parent_id' => null, 'type' => 1, 'company_id' => 1],
            ['id' => 25, 'code' => '061', 'name' => 'برگشت از فروش', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 38, 'code' => '018', 'name' => 'سایر حسابهای دریافتنی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 39, 'code' => '023', 'name' => 'سایر حسابهای پرداختنی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 67, 'code' => '014', 'name' => 'اسناد در جریان وصول', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 69, 'code' => '015', 'name' => 'موجودی مواد و کالا', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 71, 'code' => '016', 'name' => 'پیش پرداخت ها', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 75, 'code' => '017', 'name' => 'دارایی های غیر جاری', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 81, 'code' => '022', 'name' => 'پیش دریافت ها', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 84, 'code' => '030', 'name' => 'حقوق صاحبان سهام', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 88, 'code' => '041', 'name' => 'قیمت تمام شده کالای فروش رفته', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 90, 'code' => '064', 'name' => 'حسابهای انتظامی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 93, 'code' => '065', 'name' => 'طرف حسابهای انتظامی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 97, 'code' => '066', 'name' => 'تخفیفات نقدی', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 102, 'code' => '070', 'name' => 'بهای تمام شده', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 103, 'code' => '080', 'name' => 'موجودی کالا', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],
            ['id' => 104, 'code' => '090', 'name' => 'درآمد فروش', 'parent_id' => null, 'type' => 'both', 'company_id' => 1],

            ['id' => 10, 'code' => '040001', 'name' => 'حقوق پرسنل', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 11, 'code' => '040002', 'name' => 'آب', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 12, 'code' => '040003', 'name' => 'برق', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 13, 'code' => '040004', 'name' => 'تلفن', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 26, 'code' => '040005', 'name' => 'گاز', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 27, 'code' => '040006', 'name' => 'پست', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 28, 'code' => '040007', 'name' => 'هزینه حمل', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 29, 'code' => '040008', 'name' => 'ضایعات کالا', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 30, 'code' => '040009', 'name' => 'عوارض شهرداری', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 31, 'code' => '040010', 'name' => 'کارمزد بانک', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 33, 'code' => '040011', 'name' => 'مالیات', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 34, 'code' => '040012', 'name' => 'هزینه اجاره محل', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],
            ['id' => 32, 'code' => '040013', 'name' => 'هزینه های متفرقه', 'parent_id' => 2, 'type' => 'debtor', 'company_id' => 1],

            ['id' => 14, 'code' => '011001', 'name' => 'صندوق', 'parent_id' => 3, 'type' => 'both', 'company_id' => 1],
            ['id' => 59, 'code' => '011002', 'name' => 'تنخواه گردانها', 'parent_id' => 3, 'type' => 'both', 'company_id' => 1],

            ['id' => 58, 'code' => '012001', 'name' => 'اشخاص متفرقه', 'parent_id' => 4, 'type' => 'both', 'company_id' => 1],

            ['id' => 44, 'code' => '013001', 'name' => 'اسناد دریافتنی', 'parent_id' => 6, 'type' => 'both', 'company_id' => 1],

            ['id' => 68, 'code' => '014001', 'name' => 'اسناد در جریان وصول', 'parent_id' => 67, 'type' => 'both', 'company_id' => 1],

            ['id' => 7, 'code' => '015001', 'name' => 'موجودی مواد اولیه', 'parent_id' => 69, 'type' => 'both', 'company_id' => 1],
            ['id' => 70, 'code' => '015002', 'name' => 'موجودی مواد و کالا', 'parent_id' => 69, 'type' => 'both', 'company_id' => 1],

            ['id' => 72, 'code' => '016001', 'name' => 'پیش پرداخت مالیات', 'parent_id' => 71, 'type' => 'both', 'company_id' => 1],
            ['id' => 73, 'code' => '016002', 'name' => 'پیش پرداخت اجاره', 'parent_id' => 71, 'type' => 'both', 'company_id' => 1],
            ['id' => 74, 'code' => '016003', 'name' => 'پیش پرداخت هزینه های جاری', 'parent_id' => 71, 'type' => 'both', 'company_id' => 1],

            ['id' => 76, 'code' => '017001', 'name' => 'اموال، ماشین آلات و تجهیزات', 'parent_id' => 75, 'type' => 'both', 'company_id' => 1],
            ['id' => 77, 'code' => '017002', 'name' => 'استهلاک انباشته اموال، ماشین آلات و تجهیزات', 'parent_id' => 75, 'type' => 'both', 'company_id' => 1],
            ['id' => 78, 'code' => '017003', 'name' => 'سرمایه گذاری های بلند مدت', 'parent_id' => 75, 'type' => 'both', 'company_id' => 1],
            ['id' => 79, 'code' => '017004', 'name' => 'سپرده ها و مطالبات بلندمدت', 'parent_id' => 75, 'type' => 'both', 'company_id' => 1],
            ['id' => 80, 'code' => '017005', 'name' => 'سایر دارایی ها', 'parent_id' => 75, 'type' => 'both', 'company_id' => 1],

            ['id' => 40, 'code' => '018001', 'name' => 'مالیات بر ارزش افزوده خرید', 'parent_id' => 38, 'type' => 'both', 'company_id' => 1],
            ['id' => 56, 'code' => '018002', 'name' => 'عوارض خرید', 'parent_id' => 38, 'type' => 'both', 'company_id' => 1],
            ['id' => 62, 'code' => '018003', 'name' => 'مساعده حقوق', 'parent_id' => 38, 'type' => 'both', 'company_id' => 1],
            ['id' => 63, 'code' => '018004', 'name' => 'جاری کارکنان', 'parent_id' => 38, 'type' => 'both', 'company_id' => 1],
            ['id' => 64, 'code' => '018005', 'name' => 'حق بیمه 5درصد مکسوره از صورت وضعیت', 'parent_id' => 38, 'type' => 'both', 'company_id' => 1],

            ['id' => 46, 'code' => '020001', 'name' => 'اسناد پرداختنی', 'parent_id' => 22, 'type' => 'both', 'company_id' => 1],

            ['id' => 82, 'code' => '022001', 'name' => 'پیش دریافت فروش محصولات', 'parent_id' => 81, 'type' => 'both', 'company_id' => 1],
            ['id' => 83, 'code' => '022002', 'name' => 'سایر پیش دریافت ها', 'parent_id' => 81, 'type' => 'both', 'company_id' => 1],

            ['id' => 41, 'code' => '023001', 'name' => 'مالیات بر ارزش افزوده فروش', 'parent_id' => 39, 'type' => 'both', 'company_id' => 1],
            ['id' => 57, 'code' => '023002', 'name' => 'عوارض فروش', 'parent_id' => 39, 'type' => 'both', 'company_id' => 1],
            ['id' => 66, 'code' => '023003', 'name' => 'عیدی و پاداش پرداختنی', 'parent_id' => 39, 'type' => 'both', 'company_id' => 1],

            ['id' => 21, 'code' => '030001', 'name' => 'سرمایه', 'parent_id' => 84, 'type' => 'both', 'company_id' => 1],
            ['id' => 85, 'code' => '030002', 'name' => 'اندوخته قانونی', 'parent_id' => 84, 'type' => 'both', 'company_id' => 1],
            ['id' => 86, 'code' => '030003', 'name' => 'سود (زیان) انباشته', 'parent_id' => 84, 'type' => 'both', 'company_id' => 1],
            ['id' => 96, 'code' => '030004', 'name' => 'سود (زیان) جاری', 'parent_id' => 84, 'type' => 'both', 'company_id' => 1],
            ['id' => 87, 'code' => '030005', 'name' => 'تقسیم سود', 'parent_id' => 84, 'type' => 'both', 'company_id' => 1],

            ['id' => 89, 'code' => '041001', 'name' => 'قیمت تمام شده کالای فروش رفته', 'parent_id' => 88, 'type' => 'both', 'company_id' => 1],

            ['id' => 36, 'code' => '050001', 'name' => 'درآمد متفرقه', 'parent_id' => 23, 'type' => 1, 'company_id' => 1],

            ['id' => 20, 'code' => '060001', 'name' => 'فروش', 'parent_id' => 18, 'type' => 1, 'company_id' => 1],

            ['id' => 55, 'code' => '061001', 'name' => 'تخفیفات فروش', 'parent_id' => 25, 'type' => 'both', 'company_id' => 1],
            ['id' => 43, 'code' => '061002', 'name' => 'برگشت از فروش', 'parent_id' => 25, 'type' => 'both', 'company_id' => 1],

            ['id' => 19, 'code' => '062001', 'name' => 'خرید', 'parent_id' => 17, 'type' => 'debtor', 'company_id' => 1],

            ['id' => 92, 'code' => '064002', 'name' => 'حسابهای انتظامی به عهده شرکت', 'parent_id' => 90, 'type' => 'both', 'company_id' => 1],

            ['id' => 94, 'code' => '065001', 'name' => 'طرف حساب انتظامی به نفع شرکت', 'parent_id' => 93, 'type' => 'both', 'company_id' => 1],
            ['id' => 95, 'code' => '065002', 'name' => 'طرف حساب انتظامی به عهده شرکت', 'parent_id' => 93, 'type' => 'both', 'company_id' => 1],

            ['id' => 98, 'code' => '066001', 'name' => 'تخفیفات نقدی', 'parent_id' => 97, 'type' => 'both', 'company_id' => 1],

            ['id' => 15, 'code' => '067001', 'name' => 'تراز افتتاحیه', 'parent_id' => 5, 'type' => 'both', 'company_id' => 1],

            ['id' => 37, 'code' => '068001', 'name' => 'جاری شرکا', 'parent_id' => 8, 'type' => 1, 'company_id' => 1],
        ];
        DB::table('subjects')->insert($subjectData);
    }
}
