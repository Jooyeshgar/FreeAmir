import '@majidh1/jalalidatepicker/dist/jalalidatepicker'

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';

window.Alpine = Alpine;
Alpine.start();

Chart.register(...registerables);

if(document.getElementById('lineChart')) {
  document.addEventListener('DOMContentLoaded', () => {
    Chart.defaults.font.family = 'vazir'
    const ctx = document.getElementById('lineChart').getContext('2d');
  
    // ایجاد یک گرادینت برای پس‌زمینه
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(0, 255, 200, 0.3)'); // رنگ شروع گرادینت
    gradient.addColorStop(1, 'rgba(0, 255, 200, 0)');   // رنگ پایان گرادینت
  
    const myChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['ماه 1', 'ماه 2', 'ماه 3', 'ماه 4', 'ماه 5'],  // ماه‌ها
        datasets: [{
          label: 'رئال',
          data: [15250000, 11890000, 33754000, 21507000, 31708000],  // مقادیر داده‌ها
          fill: true,  // پر کردن زیر خط نمودار
          backgroundColor: gradient,  // استفاده از گرادینت برای پس‌زمینه
          borderColor: '#00cca3',  // رنگ خط
          borderWidth: 3,  // ضخامت خط
          pointBackgroundColor: '#ffffff',  // رنگ نقاط
          pointBorderColor: '#00cca3',  // رنگ حاشیه نقاط
          pointBorderWidth: 3,  // ضخامت حاشیه نقاط
          pointRadius: 5,  // اندازه نقاط
          pointHoverRadius: 7,  // اندازه نقاط هنگام هاور
          lineTension: 0.4,  // مقدار برای نرم کردن گوشه‌های نمودار
        }]
      },
      options: {
        scales: {
          x: {
            display: false,  // حذف محور x و لیبل‌های آن
          },
          y: {
            display: false,  // حذف محور y و لیبل‌های آن
            beginAtZero: true,  // همچنان از صفر شروع می‌شود
          }
        },
        plugins: {
          legend: {
            display: false  // مخفی کردن لیبل نمودار
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                let value = context.raw;
                return value.toLocaleString();  // افزودن کاما به مقادیر Tooltip
              }
            }
          }
        }
      }
    });
  });
  
  document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('gaugeChart').getContext('2d');
    const myChart = new Chart(ctx, {
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
  
  document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('midLineChart').getContext('2d');
  
    const data = {
      labels: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد'],
      datasets: [{
        label: 'نمودار درصدی',
        data: [-7.6, 2.5, -4.2, 4.7, -1.6],
        borderColor: '#888',
        borderWidth: 5,
        fill: false,
        tension: 0.4,  // نرم کردن خطوط نمودار
        pointBackgroundColor: '#fff',
        pointBorderColor: function(context) {
          const value = context.raw;
          return value >= 0 ? 'green' : 'red';
        },
        pointBorderWidth: 3,
        pointRadius: 6
      }]
    };
  
    const options = {
      scales: {
        x: {
          grid: {
            display: true,
            color: '#e0e0e0',  // رنگ خطوط شبکه محور x
          },
        },
        y: {
          grid: {
            display: true,
            color: '#e0e0e0',  // رنگ خطوط شبکه محور y
          },
          beginAtZero: false,
        }
      },
      plugins: {
        legend: {
          display: false,  // مخفی کردن لیبل نمودار
        },
        tooltip: {
          enabled: false,  // غیرفعال کردن tooltip
        },
        datalabels: {
          align: 'top',
          anchor: 'end',
          color: function(context) {
            const value = context.dataset.data[context.dataIndex];
            return value >= 0 ? 'green' : 'red';
          },
          font: {
            weight: 'bold',
            size: 14,
          },
          formatter: (value) => value + '%',  // افزودن علامت درصد به مقادیر
        }
      }
    };
  
    const myChart = new Chart(ctx, {
      type: 'line',
      data: data,
      options: options,
    });
  });
}