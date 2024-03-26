<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            [ "id" => 1, "code" => "010", "name" => "بانکها", "parent_id" => 0, "_lft" => 1, "_rgt" => 2, "type" => 'both' ],
            [ "id" => 2, "code" => "040", "name" => "هزینه ها", "parent_id" => 0, "_lft" => 3, "_rgt" => 28, "type" => 'debtor' ],
            [ "id" => 3, "code" => "011", "name" => "موجودیهای نقدی", "parent_id" => 0, "_lft" => 29, "_rgt" => 32, "type" => 'both' ],
            [ "id" => 4, "code" => "012", "name" => "بدهکاران/بستانکاران", "parent_id" => 0, "_lft" => 33, "_rgt" => 34, "type" => 'both' ],
            [ "id" => 6, "code" => "013", "name" => "اسناد دریافتنی", "parent_id" => 0, "_lft" => 39, "_rgt" => 42, "type" => 'both' ],
            [ "id" => 10, "code" => "040001", "name" => "حقوق پرسنل", "parent_id" => 2, "_lft" => 4, "_rgt" => 5, "type" => 'debtor' ],
            [ "id" => 11, "code" => "040002", "name" => "آب", "parent_id" => 2, "_lft" => 6, "_rgt" => 7, "type" => 'debtor' ],
            [ "id" => 12, "code" => "040003", "name" => "برق", "parent_id" => 2, "_lft" => 8, "_rgt" => 9, "type" => 'debtor' ],
            [ "id" => 13, "code" => "040004", "name" => "تلفن", "parent_id" => 2, "_lft" => 10, "_rgt" => 11, "type" => 'debtor' ],
            [ "id" => 26, "code" => "040005", "name" => "گاز", "parent_id" => 2, "_lft" => 12, "_rgt" => 13, "type" => 'debtor' ],
            [ "id" => 27, "code" => "040006", "name" => "پست", "parent_id" => 2, "_lft" => 16, "_rgt" => 17, "type" => 'debtor' ],
            [ "id" => 28, "code" => "040007", "name" => "هزینه حمل", "parent_id" => 2, "_lft" => 16, "_rgt" => 17, "type" => 'debtor' ],
            [ "id" => 29, "code" => "040008", "name" => "ضایعات کالا", "parent_id" => 2, "_lft" => 18, "_rgt" => 19, "type" => 'debtor' ],
            [ "id" => 30, "code" => "040009", "name" => "عوارض شهرداری", "parent_id" => 2, "_lft" => 20, "_rgt" => 21, "type" => 'debtor' ],
            [ "id" => 31, "code" => "040010", "name" => "کارمزد بانک", "parent_id" => 2, "_lft" => 22, "_rgt" => 23, "type" => 'debtor' ],
            [ "id" => 33, "code" => "040011", "name" => "مالیات", "parent_id" => 2, "_lft" => 26, "_rgt" => 27, "type" => 'debtor' ],
            [ "id" => 34, "code" => "040012", "name" => "هزینه اجاره محل", "parent_id" => 2, "_lft" => 26, "_rgt" => 27, "type" => 'debtor' ],
            [ "id" => 32, "code" => "040013", "name" => "هزینه های متفرقه", "parent_id" => 2, "_lft" => 26, "_rgt" => 27, "type" => 'debtor' ],
            [ "id" => 14, "code" => "011001", "name" => "صندوق", "parent_id" => 3, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 59, "code" => "011002", "name" => "تنخواه گردانها", "parent_id" => 3, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 58, "code" => "012001", "name" => "اشخاص متفرقه", "parent_id" => 4, "_lft" => 33, "_rgt" => 34, "type" => 'both' ],
            [ "id" => 44, "code" => "013001", "name" => "اسناد دریافتنی", "parent_id" => 6, "_lft" => 40, "_rgt" => 41, "type" => 'both' ],
            [ "id" => 67, "code" => "014", "name" => "اسناد در جریان وصول", "parent_id" => 0, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 68, "code" => "014001", "name" => "اسناد در جریان وصول", "parent_id" => 67, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 69, "code" => "015", "name" => "موجودی مواد و کالا", "parent_id" => 0, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 7, "code" => "015001", "name" => "موجودی مواد اولیه", "parent_id" => 69, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 70, "code" => "015002", "name" => "موجودی مواد و کالا", "parent_id" => 69, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 71, "code" => "016", "name" => "پیش پرداخت ها", "parent_id" => 0, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 72, "code" => "016001", "name" => "پیش پرداخت مالیات", "parent_id" => 71, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 73, "code" => "016002", "name" => "پیش پرداخت اجاره", "parent_id" => 71, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 74, "code" => "016003", "name" => "پیش پرداخت هزینه های جاری", "parent_id" => 71, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 75, "code" => "017", "name" => "دارایی های غیر جاری", "parent_id" => 0, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 76, "code" => "017001", "name" => "اموال، ماشین آلات و تجهیزات", "parent_id" => 75, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 77, "code" => "017002", "name" => "استهلاک انباشته اموال، ماشین آلات و تجهیزات", "parent_id" => 75, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 78, "code" => "017003", "name" => "سرمایه گذاری های بلند مدت", "parent_id" => 75, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 79, "code" => "017004", "name" => "سپرده ها و مطالبات بلندمدت", "parent_id" => 75, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 80, "code" => "017005", "name" => "سایر دارایی ها", "parent_id" => 75, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 38, "code" => "018", "name" => "سایر حسابهای دریافتنی", "parent_id" => 0, "_lft" => 89, "_rgt" => 94, "type" => 'both' ],
            [ "id" => 40, "code" => "018001", "name" => "مالیات بر ارزش افزوده خرید", "parent_id" => 38, "_lft" => 90, "_rgt" => 91, "type" => 'both' ],
            [ "id" => 56, "code" => "018002", "name" => "عوارض خرید", "parent_id" => 38, "_lft" => 92, "_rgt" => 93, "type" => 'both' ],
            [ "id" => 62, "code" => "018003", "name" => "مساعده حقوق", "parent_id" => 38, "_lft" => 89, "_rgt" => 94, "type" => 'both' ],
            [ "id" => 63, "code" => "018004", "name" => "جاری کارکنان", "parent_id" => 38, "_lft" => 89, "_rgt" => 94, "type" => 'both' ],
            [ "id" => 64, "code" => "018005", "name" => "حق بیمه 5درصد مکسوره از صورت وضعیت", "parent_id" => 38, "_lft" => 89, "_rgt" => 94, "type" => 'both' ],
            [ "id" => 22, "code" => "020", "name" => "اسناد پرداختنی", "parent_id" => 0, "_lft" => 65, "_rgt" => 68, "type" => 'both' ],
            [ "id" => 46, "code" => "020001", "name" => "اسناد پرداختنی", "parent_id" => 22, "_lft" => 66, "_rgt" => 67, "type" => 'both' ],
            [ "id" => 81, "code" => "022", "name" => "پیش دریافت ها", "parent_id" => 0, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 82, "code" => "022001", "name" => "پیش دریافت فروش محصولات", "parent_id" => 81, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 83, "code" => "022002", "name" => "سایر پیش دریافت ها", "parent_id" => 81, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 39, "code" => "023", "name" => "سایر حسابهای پرداختنی", "parent_id" => 0, "_lft" => 95, "_rgt" => 100, "type" => 'both' ],
            [ "id" => 41, "code" => "023001", "name" => "مالیات بر ارزش افزوده فروش", "parent_id" => 39, "_lft" => 96, "_rgt" => 97, "type" => 'both' ],
            [ "id" => 57, "code" => "023002", "name" => "عوارض فروش", "parent_id" => 39, "_lft" => 98, "_rgt" => 99, "type" => 'both' ],
            [ "id" => 66, "code" => "023003", "name" => "عیدی و پاداش پرداختنی", "parent_id" => 39, "_lft" => 95, "_rgt" => 100, "type" => 'both' ],
            [ "id" => 84, "code" => "030", "name" => "حقوق صاحبان سهام", "parent_id" => 0, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 21, "code" => "030001", "name" => "سرمایه", "parent_id" => 84, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 85, "code" => "030002", "name" => "اندوخته قانونی", "parent_id" => 84, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 86, "code" => "030003", "name" => "سود (زیان) انباشته", "parent_id" => 84, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 96, "code" => "030004", "name" => "سود (زیان) جاری", "parent_id" => 84, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 87, "code" => "030005", "name" => "تقسیم سود", "parent_id" => 84, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 88, "code" => "041", "name" => "قیمت تمام شده کالای فروش رفته", "parent_id" => 0, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 89, "code" => "041001", "name" => "قیمت تمام شده کالای فروش رفته", "parent_id" => 88, "_lft" => 30, "_rgt" => 31, "type" => 'both' ],
            [ "id" => 23, "code" => "050", "name" => "درآمدها", "parent_id" => 0, "_lft" => 69, "_rgt" => 76, "type" => 1 ],
            [ "id" => 36, "code" => "050001", "name" => "درآمد متفرقه", "parent_id" => 23, "_lft" => 74, "_rgt" => 75, "type" => 1 ],
            [ "id" => 18, "code" => "060", "name" => "فروش", "parent_id" => 0, "_lft" => 57, "_rgt" => 60, "type" => 1 ],
            [ "id" => 20, "code" => "060001", "name" => "فروش", "parent_id" => 18, "_lft" => 58, "_rgt" => 59, "type" => 1 ],
            [ "id" => 25, "code" => "061", "name" => "برگشت از فروش و تخفیفات", "parent_id" => 0, "_lft" => 83, "_rgt" => 88, "type" => 'both' ],
            [ "id" => 55, "code" => "061001", "name" => "تخفیفات فروش", "parent_id" => 25, "_lft" => 86, "_rgt" => 87, "type" => 'both' ],
            [ "id" => 43, "code" => "061002", "name" => "برگشت از فروش", "parent_id" => 25, "_lft" => 86, "_rgt" => 87, "type" => 'both' ],
            [ "id" => 17, "code" => "062", "name" => "خرید", "parent_id" => 0, "_lft" => 53, "_rgt" => 56, "type" => 'debtor' ],
            [ "id" => 19, "code" => "062001", "name" => "خرید", "parent_id" => 17, "_lft" => 54, "_rgt" => 55, "type" => 'debtor' ],
            [ "id" => 24, "code" => "063", "name" => "برگشت از خرید و تخفیفات", "parent_id" => 0, "_lft" => 77, "_rgt" => 82, "type" => 'both' ],
            [ "id" => 42, "code" => "063001", "name" => "برگشت از خرید", "parent_id" => 24, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 53, "code" => "063002", "name" => "تخفیفات خرید", "parent_id" => 24, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 90, "code" => "064", "name" => "حسابهای انتظامی", "parent_id" => 0, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 91, "code" => "064001", "name" => "حسابهای انتظامی به نفع شرکت", "parent_id" => 90, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 92, "code" => "064002", "name" => "حسابهای انتظامی به عهده شرکت", "parent_id" => 90, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 93, "code" => "065", "name" => "طرف حسابهای انتظامی", "parent_id" => 0, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 94, "code" => "065001", "name" => "طرف حساب انتظامی به نفع شرکت", "parent_id" => 93, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 95, "code" => "065002", "name" => "طرف حساب انتظامی به عهده شرکت", "parent_id" => 93, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 97, "code" => "066", "name" => "تخفیفات نقدی", "parent_id" => 0, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 98, "code" => "066001", "name" => "تخفیفات نقدی", "parent_id" => 97, "_lft" => 80, "_rgt" => 81, "type" => 'both' ],
            [ "id" => 5, "code" => "067", "name" => "تراز افتتاحیه", "parent_id" => 0, "_lft" => 35, "_rgt" => 38, "type" => 'both' ],
            [ "id" => 15, "code" => "067001", "name" => "تراز افتتاحیه", "parent_id" => 5, "_lft" => 36, "_rgt" => 37, "type" => 'both' ],
            [ "id" => 8, "code" => "068", "name" => "جاری شرکا", "parent_id" => 0, "_lft" => 47, "_rgt" => 50, "type" => 'both' ],
            [ "id" => 37, "code" => "068001", "name" => "جاری شرکا", "parent_id" => 8, "_lft" => 48, "_rgt" => 49, "type" => 1 
           ] 
          ];
          DB::table('subjects')->insert($subjectData);
    }
}