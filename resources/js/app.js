import '@majidh1/jalalidatepicker/dist/jalalidatepicker'

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';

window.Alpine = Alpine;
Alpine.start();

Chart.register(...registerables);

if (document.getElementById('lineChart')) {
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
        pointBorderColor: function (context) {
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
          color: function (context) {
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

if (document.querySelector(".selfSelectBoxContainer")) {
  const csrf = document.querySelector('meta[name="csrf_token"]').getAttribute("content");
  let searchInputs = document.querySelectorAll(".searchInput"),
    resultDivs = document.querySelectorAll(".resultDiv"),
    searchResultDivs = document.querySelectorAll(".searchResultDiv");

  function voidInputSearch() {
    searchInputs.forEach((e, t) => {
      e.addEventListener("input", e => debouncedSearch(e, t))
    })
  }

  function debounce(e, t) {
    let n;
    return function (...a) {
      clearTimeout(n), n = setTimeout(() => {
        e.apply(this, a)
      }, t)
    }
  }

  function formatCode(e) {
    let t = [];
    for (let n = 0; n < e.length; n += 3) t.push(e.substring(n, n + 3));
    return e = t.join("/"), ["fa", "fa_IR"].includes("fa") && (e = convertToFarsi(e)), e
  }

  function convertToFarsi(e) {
    let t = {
      0: "۰",
      1: "۱",
      2: "۲",
      3: "۳",
      4: "۴",
      5: "۵",
      6: "۶",
      7: "۷",
      8: "۸",
      9: "۹"
    };
    return e.replace(/[0-9]/g, e => t[e])
  }

  function searchQuery(e, t) {
    fetch("/subjects/search", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrf
      },
      body: JSON.stringify({
        query: e
      })
    }).then(e => {
      if (!e.ok) throw Error("خطا در دریافت پاسخ");
      return e.json()
    }).then(e => {
      resultDivs[t].style.display = "none", searchResultDivs[t].style.display = "block", 0 == e.length ?
        searchResultDivs[t].innerHTML = '<span class="block text-center">چیزی پیدا نشد!</span>' : e
          .forEach(e => {
            let n = e.name,
              a = e.code;
            if (0 == e.sub_subjects.length) {
              let s = `
                        <div class="w-full ps-2 mb-4">
                            <div class="flex justify-between">
                                <span>
                                    ${n}
                                </span>

                                <span>
                                    ${formatCode(a)}
                                </span>
                            </div>
                        </div>
                        `;
              searchResultDivs[t].innerHTML = s
            } else {
              let l = e.sub_subjects,
                r = `
                        <div class="w-full ps-2 mb-4">
                            <div class="flex justify-between">
                                <span>
                                    ${n}
                                </span>

                                <span>
                                    ${formatCode(a)}
                                </span>
                            </div>
                        </div>
                        <div class="ps-1 mt-4">
                            <div class="border-s-[1px] ps-7 border-[#ADB5BD]" id="sub-${t}"></div>
                        </div>
                        `;
              searchResultDivs[t].innerHTML = r, l.forEach(e => {
                let n = document.getElementById(`sub-${t}`),
                  a = `
                                    <a href="javascript:void(0)"
                                        class="selfSelectBoxItems flex justify-between mb-4"
                                        onclick="fillInput(this, '${t}')">
                                        <span class="selfItemTitle">
                                            ${e.name}
                                        </span>
                                        <span class="selfItemCode">
                                            ${formatCode(e.code)}
                                        </span>
                                        <span class="selfItemId hidden">${e.id}</span>
                                    </a>
                                    `;
                n.innerHTML += a
              })
            }
          })
    }).catch(e => {
      console.error("خطایی رخ داده: ", e)
    })
  }

  document.getElementById("addTransaction").addEventListener("click", function () {
    setTimeout(() => {
      var e = document.getElementById("transactions"),
        t = e.getElementsByClassName("transaction"),
        n = t[t.length - 1],
        a = n.querySelector(".transaction-count").innerText;
      n.querySelectorAll(".selfSelectBoxItems").forEach(e => {
        e.setAttribute("onclick", `fillInput(this, '${a - 1}')`)
      })
    }, 200);
  }), document.addEventListener("click", function (e) {
    e.target.closest(".selfSelectBoxContainer") || document.querySelectorAll(".selfSelectBox").forEach(
      function (e) {
        e.style.display = "none", resultDivs.forEach((e, t) => {
          searchInputs[t].value = "", e.style.display = "block", searchResultDivs[t].style
            .display = "none"
        })
      })
  });
  const debouncedSearch = debounce(function (e, t) {
    let n = e.target.value;
    n ? searchQuery(n, t) : (resultDivs[t].style.display = "block", searchResultDivs[t].style.display =
      "none")
  }, 500);
  voidInputSearch();
}