
// ✅ แก้ไขฟังก์ชันเปิด Modal
function openSummaryModal(planId, summary, statusId) {
    document.getElementById('modal_plan_id').value = planId;
    document.getElementById('modal_summary').value = summary;
    const statusSelect = document.getElementById('modal_status_id');

    if (!summary || summary.trim() === "") {
        for (let i = 0; i < statusSelect.options.length; i++) {
            if (statusSelect.options[i].text.toUpperCase().includes('PLAN')) {
                statusSelect.selectedIndex = i; break;
            }
        }
    } else { statusSelect.value = statusId; }
    new bootstrap.Modal(document.getElementById('summaryModal')).show();
}

// 🟢 บันทึกข้อมูลพร้อมแจ้งเตือนถ้ายังเป็น Plan
document.getElementById('summaryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const selText = document.getElementById('modal_status_id').options[document.getElementById('modal_status_id').selectedIndex].text.toUpperCase();

    if (selText.includes('PLAN')) {
        Swal.fire({
            title: 'ยังเป็น Plan?',
            text: "คุณยังไม่ได้เปลี่ยนสถานะงาน ยืนยันจะบันทึกใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ใช่, บันทึกเลย'
        }).then((res) => { if (res.isConfirmed) saveSummaryData(); });
    } else { saveSummaryData(); }
});

async function saveSummaryData() {
    const formData = new FormData(document.getElementById('summaryForm'));
    try {
        const res = await fetch('work_plan_dashboard.php', { method: 'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'} });
        const data = await res.json();
        if (data.success) Swal.fire('สำเร็จ', 'บันทึกเรียบร้อย', 'success').then(() => location.reload());
    } catch (e) { Swal.fire('ผิดพลาด', 'บันทึกไม่สำเร็จ', 'error'); }
}



// ฟังก์ชันยืนยันการลบ (SweetAlert2)
function confirmDelete(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "ข้อมูลนี้จะถูกลบถาวร ไม่สามารถกู้คืนได้",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fas fa-trash"></i> ลบข้อมูล',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'rounded-4 shadow-lg',
            confirmButton: 'btn btn-danger rounded-3 px-4',
            cancelButton: 'btn btn-secondary rounded-3 px-4'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `work_plan_dashboard.php?delete_id=${id}`;
        }
    });
}
    function getStatusThemeColor($status_name, $status_id) {
    $status_name = trim($status_name);
    
    // 1. สีตายตัว (แดง/เขียว/ฟ้า/เหลือง)
    if (strpos($status_name, 'ไม่ได้งาน') !== false || strpos($status_name, 'ยกเลิก') !== false || $status_name == 'Cancelled') return '#ef4444'; // Red-500
    if (strpos($status_name, 'ได้งาน') !== false || strpos($status_name, 'สำเร็จ') !== false || $status_name == 'Completed') return '#10b981'; // Emerald-500
    if (strpos($status_name, 'เสนอ') !== false || strpos($status_name, 'วางแผน') !== false || $status_name == 'Plan') return '#3b82f6'; // Blue-500
    if (strpos($status_name, 'ติดตาม') !== false || strpos($status_name, 'นัดหมาย') !== false || $status_name == 'Confirmed') return '#f59e0b'; // Amber-500
    
    // 2. สี Auto (คำนวณ HSL แล้วแปลงเป็น Hex หรือส่งกลับเป็น HSL string ให้ CSS ใช้)
    // เพื่อความง่ายและสวยงาม เราจะใช้ HSL String ที่ CSS อ่านรู้เรื่อง
    $hue = ($status_id * 137.508) % 360; 
    return "hsl($hue, 70%, 50%)"; 
}
async function updateDashboard() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('ajax', '1');
    const params = new URLSearchParams(formData).toString();
    const tableCard = document.querySelector('.table-card');

    tableCard.style.opacity = '0.5';
    try {
        const response = await fetch(`work_plan_dashboard.php?${params}`);
        const data = await response.json();
        
        if (data) {
            // อัปเดตตาราง
            if (data.html_content) {
                document.querySelector('tbody').innerHTML = data.html_content;
            }
            // 🟢 อัปเดตการ์ดสถานะ (จุดสำคัญที่ทำให้การ์ดเปลี่ยนตามจริง)
            if (data.grid_html) {
                document.querySelector('.status-grid').innerHTML = data.grid_html;
            }
        }
    } catch (e) { console.error('Error:', e); }
    finally { tableCard.style.opacity = '1'; }
}
// ฟังก์ชันช่วยอัปเดตตัวเลขบน Card (แถมให้ครับ)
function updateStatusNumbers(counts, total) {
    const totalEl = document.querySelector('.status-card[onclick*="selectStatus(\'\')"] .sc-count');
    if (totalEl) totalEl.innerText = total;
    
    // วนลูปอัปเดตเลขตามสถานะต่างๆ
    // (ลูกพี่อาจต้องเพิ่ม class หรือ id ให้ sc-count แต่ละตัวเพื่อให้เจาะจงได้ง่ายขึ้น)
}

// 🟢 เมื่อมีการกดยืนยันฟอร์ม (ค้นหา)
document.getElementById('filterForm').addEventListener('submit', function(e) {
    e.preventDefault(); // 🛑 ห้ามรีเฟรชหน้า
    updateDashboard();
});

// 🟢 เมื่อกดเลือกการ์ดสถานะ
function selectStatus(id) {
    document.getElementById('filter_status_input').value = id;
    updateDashboard(); // 🛑 เรียกฟังก์ชันอัปเดตแทนการ Submit แบบเดิม
}

// 🟢 เมื่อกดปุ่มล้างค่า
document.getElementById('btnClear').addEventListener('click', function() {
    const form = document.getElementById('filterForm');
    form.reset(); // ล้างค่าใน Form
    
    // ล้างค่าพิเศษที่ reset() ไม่ทำ (เช่น hidden input หรือค่าที่เลือกค้าง)
    document.getElementById('filter_status_input').value = '';
    
    // ตั้งค่า Select ให้กลับไปเป็นค่าแรก
    form.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    
    updateDashboard(); // 🛑 อัปเดตตารางกลับเป็นค่าเริ่มต้น
});

// เมื่อมีการเปลี่ยน เดือน/ปี/ทีม ให้ Auto Update ทันที
document.querySelectorAll('.form-select-custom').forEach(select => {
    select.addEventListener('change', updateDashboard);
});


// ฟังก์ชันกดแล้วดาวน์โหลด Excel
function exportToExcel() {
    // 1. ดึงค่าจาก Filter ปัจจุบัน
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    
    // 2. แปลงเป็น Query String
    const params = new URLSearchParams(formData);
    
    // 3. เพิ่มค่า status จาก hidden input (ถ้ามีระบบเลือกการ์ด)
    const statusVal = document.getElementById('filter_status_input').value;
    if(statusVal) params.set('status', statusVal);

    // 4. เพิ่ม flag บอกว่าเป็น export
    params.set('export', 'excel');

    // 5. สั่งเปิด URL เพื่อดาวน์โหลด (ไม่รีเฟรชหน้าเดิม)
    window.location.href = `export_work_plan.php?${params.toString()}`;
}
// 1. ฟังก์ชันเปิด Modal
    function openExportModal() {
        var myModal = new bootstrap.Modal(document.getElementById('exportModal'));
        myModal.show();
    }

    // 2. ฟังก์ชันกดปุ่มยืนยันดาวน์โหลด (ใน Modal)
    function confirmExport() {
    let startDate = document.getElementById('ex_start_date').value;
    let endDate   = document.getElementById('ex_end_date').value;
    let type      = document.getElementById('ex_type').value;
    let worker    = document.getElementById('ex_worker').value;
    let status    = document.getElementById('ex_status').value; // ดึงค่าจาก Select ใน Modal

    // ส่งค่า status ไปใน URL
    let url = `export_work_plan.php?start_date=${startDate}&end_date=${endDate}&type=${type}&worker=${encodeURIComponent(worker)}&status=${status}`;
    window.location.href = url;
}
