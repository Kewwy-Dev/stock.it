// assets/js/dashboard.js

// รอให้หน้าโหลดเสร็จก่อน
document.addEventListener('DOMContentLoaded', () => {
    // ตรวจสอบว่ามี canvas หรือไม่
    const canvas = document.getElementById('stockChart');
    if (!canvas) {
        console.error('ไม่พบ element <canvas id="stockChart">');
        return;
    }

    const ctx = canvas.getContext('2d');

    // ตัวแปรข้อมูลที่ส่งมาจาก PHP (จะถูกกำหนดในไฟล์ PHP)
    // ถ้าไม่มีจะ fallback เป็นค่าเริ่มต้นป้องกัน error
    const labels      = window.dashboardChartLabels  || [];
    const data        = window.dashboardChartData   || [];
    const deptDetails = window.dashboardDeptDetails || {};

    // ตรวจสอบว่ามีข้อมูลพอแสดงกราฟไหม
    if (labels.length === 0 || data.length === 0) {
        console.warn('ไม่มีข้อมูลสำหรับแสดงกราฟการเบิกตามแผนก');
        // ถ้าต้องการแสดงข้อความในหน้าเว็บก็เพิ่มได้ตรงนี้
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'จำนวนที่เบิกออก',
                data: data,
                backgroundColor: 'rgba(67, 97, 238, 0.75)',
                borderColor: '#4361ee',
                borderWidth: 1,
                borderRadius: 6,
                maxBarThickness: 50,
                hoverBackgroundColor: '#4361ee'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.92)',
                    cornerRadius: 12,
                    padding: 16,
                    titleFont: {
                        size: 16,
                        weight: 'bold',
                        family: 'Kanit, sans-serif'
                    },
                    bodyFont: {
                        size: 14,
                        family: 'Kanit, sans-serif'
                    },
                    footerFont: {
                        size: 12,
                        style: 'italic',
                        family: 'Kanit, sans-serif'
                    },
                    displayColors: false,
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    callbacks: {
                        title: function(context) {
                            return '🛠️ แผนก: ' + context[0].label;
                        },
                        label: function(context) {
                            const total = Number(context.parsed.y).toLocaleString();
                            return `รวมเบิกออก: ${total} ชิ้น`;
                        },
                        afterBody: function(context) {
                            const dept = context[0].label;
                            const items = deptDetails[dept] || [];

                            if (items.length === 0) {
                                return ['\nไม่มีรายการเบิกในช่วงนี้'];
                            }

                            let lines = ['\nรายการที่เบิกมากที่สุด:'];
                            const maxItems = 8;
                            items.slice(0, maxItems).forEach(item => {
                                const qty = Number(item.qty).toLocaleString();
                                lines.push(`   • ${item.item_name}\n     ${qty} ชิ้น`);
                            });

                            if (items.length > maxItems) {
                                lines.push(`\n...และอีก ${items.length - maxItems} รายการ`);
                            }

                            return lines;
                        },
                        footer: function() {
                            return 'Hover เพื่อดูรายละเอียด • ข้อมูลล่าสุด';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function(value) {
                            if (Number.isInteger(value)) return value;
                        },
                        padding: 8,
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: '#e9ecef'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        padding: 8,
                        font: {
                            size: 11
                        }
                    }
                }
            },
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            }
        }
    });
});