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
    jalaliDatepicker.startWatch({});
    const csrf = document.querySelector('meta[name="csrf_token"]').getAttribute("content");
    let searchInputs = document.querySelectorAll(".searchInput"),
        resultDivs = document.querySelectorAll(".resultDiv"),
        searchResultDivs = document.querySelectorAll(".searchResultDiv");

    function codeInputFiller() {
        const codeInputs = document.querySelectorAll(".codeInput");
        const codeLists = document.querySelectorAll(".codeList");
        const codeSelectBoxes = document.querySelectorAll(".codeSelectBox");
        const subjectIds = document.querySelectorAll(".subject_id");
        const mainformCodes = document.querySelectorAll(".mainformCode");

        function normalizeCode(code) {
            const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

            let normalizedCode = code.split('').map(char => {
                const persianIndex = persianNumbers.indexOf(char);
                const arabicIndex = arabicNumbers.indexOf(char);
                return persianIndex !== -1 ? englishNumbers[persianIndex] :
                    arabicIndex !== -1 ? englishNumbers[arabicIndex] : char;
            }).join('');

            normalizedCode = normalizedCode.replace(/\D/g, '');

            return normalizedCode;
        }

        codeInputs.forEach((element, index) => {
            element.addEventListener('input', (e) => {
                let code = e.target.value;

                const normalizedCode = normalizeCode(code);

                const matchedSpan = Array.from(codeLists).find(span => {
                    const spanCode = normalizeCode(span.getAttribute('data-code'));
                    return spanCode === normalizedCode;
                });

                if (matchedSpan) {
                    codeSelectBoxes[index].value = matchedSpan.getAttribute("data-name");
                    subjectIds[index].value = matchedSpan.getAttribute("data-id");
                    mainformCodes[index].value = normalizedCode;

                    setTimeout(() => {
                        e.target.value = window.formatCode(normalizedCode);
                    }, 200);
                } else {
                    codeSelectBoxes[index].value = "";
                    subjectIds[index].value = "";
                    mainformCodes[index].value = "";
                }
            });
        });
    }

    codeInputFiller()

    window.openSelectBox = function (e) {
        document.querySelectorAll(".selfSelectBox").forEach(function (e) {
            e.style.display = "none"
        }), e.querySelector(".selfSelectBox").style.display = "block"
    }

    window.formatCode = function (e) {
        let t = [];
        for (let n = 0; n < e.length; n += 3) t.push(e.substring(n, n + 3));
        return e = t.join("/"), ["fa", "fa_IR"].includes("fa") && (e = window.convertToFarsi(e)), e
    }

    window.convertToFarsi = function (e) {
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

    window.reOrderInputs = function () {
        setTimeout(() => {
            document.querySelectorAll(".transaction").forEach(elem => {
                let t = elem.querySelector(".transaction-count").innerText;
            })
        }, 200);
    }

    function countInputs() {
        searchInputs = document.querySelectorAll(".searchInput"), resultDivs = document.querySelectorAll(".resultDiv"),
            searchResultDivs = document.querySelectorAll(".searchResultDiv")
    }

    document.addEventListener("click", function (e) {
        e.target.closest(".selfSelectBoxContainer") || document.querySelectorAll(".selfSelectBox").forEach(
            function (e) {
                e.style.display = "none", resultDivs.forEach((e, t) => {
                    searchInputs[t].value = "", e.style.display = "block", searchResultDivs[t].style
                        .display = "none"
                })
            })
    });
}