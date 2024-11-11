import '@majidh1/jalalidatepicker/dist/jalalidatepicker'

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';

window.Alpine = Alpine;
Alpine.start();

window.Chart = Chart;
Chart.register(...registerables);
Chart.defaults.font.family = 'vazir'


if(document.getElementById('gaugeChart')) {  
  document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('gaugeChart').getContext('2d');
    const gaugeChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['درآمد از فروش', 'درآمد از تخفیف خرید', 'خالی'],  // برچسب‌ها
        datasets: [{
          data: [30, 50, 20],  // مقادیر داده‌ها
          backgroundColor: ['#FF6B6B', '#FFA726', '#FFFFFF'],  // رنگ‌ها
          borderWidth: 0,  // حذف حاشیه‌ها
        }]
      },
      options: {
        responsive: true,  // واکنش‌گرا بودن نمودار
        maintainAspectRatio: false,  // غیرفعال کردن حفظ نسبت ابعاد
        aspectRatio: 2,  // نسبت ابعاد عرض به ارتفاع برای بوم
        circumference: 180,  // تعیین زاویه نمودار (180 درجه)
        rotation: -90,  // چرخش نمودار برای نمایش از پایین به بالا
        cutout: '70%',  // تعیین اندازه حفره مرکزی
        layout: {
          padding: {
            top: 0,  // حذف فضای خالی بالا
            bottom: 0  // حذف فضای خالی پایین
          }
        },
        plugins: {
          legend: {
            display: true,  // نمایش یا عدم نمایش لیبل‌های کنار نمودار
            position: 'right',  // موقعیت لیبل‌ها
            rtl: true,  // راست‌چین کردن لیبل‌ها
            labels: {
              usePointStyle: true,  // نمایش نقطه‌ها به جای مربع‌ها
              pointStyle: 'circle',  // تعیین سبک نقاط به عنوان دایره
              boxWidth: 8,  // تنظیم اندازه نقاط (کوچک‌تر کردن آنها)
              boxHeight: 8,  // تنظیم ارتفاع نقاط
              textAlign: 'left',  // تنظیم متن به راست
              filter: function (legendItem, data) {
                // فقط لیبل‌هایی که نام آنها "خالی" نیست را نمایش بده
                return legendItem.text !== 'خالی';
              }
            }
          },
          datalabels: {
            color: '#000',  // رنگ متن داخل نمودار
            formatter: function (value, context) {
              // نمایش مقادیر فقط برای بخش‌های غیر از "خالی"
              return context.chart.data.labels[context.dataIndex] !== 'خالی' ? value : '';
            },
            anchor: 'end',  // موقعیت متن نسبت به نقطه
            align: 'end',  // تنظیم متن به بالا
            offset: 10,  // فاصله متن از نقطه
            textAlign: 'right',  // راست‌چین کردن متن داخل نمودار
          }
        }
      }
    });
  });
  
  
  
}