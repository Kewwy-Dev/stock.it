// assets/js/dashboard.js

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('stockChart');
    if (!canvas) {
        console.error('ไม่พบ element <canvas id="stockChart">');
        return;
    }

    const ctx = canvas.getContext('2d');

    // ข้อมูลเริ่มต้นจาก PHP
    const labels      = window.dashboardChartLabels  || [];
    const data        = window.dashboardChartData   || [];
    const deptDetails = window.dashboardDeptDetails || {};

    if (labels.length === 0 || data.length === 0) {
        console.warn('ไม่มีข้อมูลสำหรับแสดงกราฟการเบิกตามแผนก');
        // สามารถเพิ่มข้อความแจ้งในหน้าได้ที่นี่ถ้าต้องการ
    }

    // สร้างกราฟ
    window.myChart = new Chart(ctx, {
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
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.92)',
                    cornerRadius: 12,
                    padding: 16,
                    titleFont: { size: 16, weight: 'bold', family: 'Kanit, sans-serif' },
                    bodyFont: { size: 14, family: 'Kanit, sans-serif' },
                    footerFont: { size: 12, style: 'italic', family: 'Kanit, sans-serif' },
                    displayColors: false,
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    callbacks: {
                        title: (context) => '🛠️ แผนก: ' + context[0].label,
                        label: (context) => {
                            const total = Number(context.parsed.y).toLocaleString();
                            return `รวมเบิกออก: ${total} ชิ้น`;
                        },
                        afterBody: (context) => {
                            const dept = context[0].label;
                            const items = window.dashboardDeptDetails[dept] || [];
                            if (items.length === 0) return ['\nไม่มีรายการเบิกในช่วงนี้'];

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
                        // footer: () => 'Hover เพื่อดูรายละเอียด • ข้อมูลล่าสุด'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: (value) => Number.isInteger(value) ? value : null,
                        padding: 8,
                        font: { size: 11 }
                    },
                    grid: { color: '#e9ecef' }
                },
                x: {
                    grid: { display: false },
                    ticks: { padding: 8, font: { size: 11 } }
                }
            },
            animation: { duration: 1500, easing: 'easeOutQuart' }
        }
    });

    // จับ event การกดปุ่มเปลี่ยนช่วงเวลา
    document.querySelectorAll('.range-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            // อัปเดต active class
            document.querySelectorAll('.range-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const range = btn.dataset.range;

            try {
                const response = await fetch(`dashboard.php?ajax=1&range=${range}`);
                if (!response.ok) throw new Error('Network response was not ok');
                const data = await response.json();

                // อัปเดตข้อความช่วงเวลา
                const rangeLabelElement = document.querySelector('.text-center.text-muted.small strong');
                if (rangeLabelElement) {
                    rangeLabelElement.textContent = data.range_label;
                }

                // อัปเดตข้อมูลกราฟ
                window.dashboardChartLabels = data.chart_labels;
                window.dashboardChartData   = data.chart_data;
                window.dashboardDeptDetails = data.dept_details;

                if (window.myChart) {
                    window.myChart.data.labels = data.chart_labels;
                    window.myChart.data.datasets[0].data = data.chart_data;
                    window.myChart.update();
                }

                // อัปเดตส่วนธุรกรรมล่าสุด → แทนที่เฉพาะเนื้อหาภายใน container เท่านั้น
                const scrollContainer = document.querySelector('.transaction-scroll-container');
                if (scrollContainer) {
                    // ถ้าเป็นตารางปกติ ให้แทนที่ innerHTML
                    scrollContainer.innerHTML = data.recent_html;
                } else {
                    // กรณีไม่มีข้อมูลเดิม (แสดงข้อความว่าง) ให้แทนที่ทั้ง card-body
                    const cardBody = document.querySelector('.card-body.p-0');
                    if (cardBody) {
                        cardBody.innerHTML = data.recent_html;
                    }
                }

            } catch (error) {
                console.error('AJAX error:', error);
                // Optional: แสดงแจ้งเตือนผู้ใช้
                // alert('เกิดข้อผิดพลาดในการโหลดข้อมูล กรุณาลองใหม่');
            }
        });
    });
});