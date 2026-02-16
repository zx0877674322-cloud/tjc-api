// work_plan_dashboard.js

// ฟังก์ชันเปิด Modal สรุปงาน
function openSummaryModal(id, summary, statusId) {
    document.getElementById('modal_plan_id').value = id;
    document.getElementById('modal_summary').value = summary; 
    document.getElementById('modal_status_id').value = statusId;
    
    var myModal = new bootstrap.Modal(document.getElementById('summaryModal'));
    myModal.show();
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
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();

    // เพิ่ม Effect Loading (ถ้าต้องการ)
    document.querySelector('.table-card').style.opacity = '0.5';

    try {
        const response = await fetch(`work_plan_dashboard.php?${params}`);
        const html = await response.text();
        
        // สร้าง Parser เพื่อดึงเฉพาะส่วนที่ต้องการเปลี่ยน
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // 1. เปลี่ยนการ์ดสถานะ
        document.querySelector('.status-grid').innerHTML = doc.querySelector('.status-grid').innerHTML;
        // 2. เปลี่ยนเนื้อหาตาราง
        document.querySelector('tbody').innerHTML = doc.querySelector('tbody').innerHTML;
        
        document.querySelector('.table-card').style.opacity = '1';
    } catch (error) {
        console.error('Error:', error);
    }
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
// 🟢 [เพิ่มใหม่] ควบคุมการส่งฟอร์มจาก Modal สรุปผล
// 🟢 ส่วนควบคุมการบันทึกสรุปผลแบบห้ามรีเฟรช
document.getElementById('summaryForm').addEventListener('submit', function(e) {
    e.preventDefault(); // 🛑 หยุดการ Refresh หน้าจอทันที

    const formData = new FormData(this);
    formData.append('action', 'save_summary'); // ส่ง action ไปให้ PHP รู้

    // ส่งข้อมูลแบบ AJAX
    fetch('work_plan_dashboard.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest' // บอก PHP ว่านี่คือ AJAX
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 1. ปิด Modal สรุปผล
            const modalElement = document.getElementById('summaryModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            modal.hide();

            // 2. เคลียร์ค่าในฟอร์ม
            this.reset();

            // 3. แจ้งเตือนสำเร็จแบบสวยๆ
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                showConfirmButton: false,
                timer: 1500,
                background: '#ffffff',
                customClass: { popup: 'rounded-4' }
            });

            // 4. 🚀 สั่งอัปเดตตารางและการ์ดสถานะใหม่ (โดยไม่รีเฟรชหน้า)
            updateDashboard(); 
        } else {
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถบันทึกได้', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // ถ้าพัง ให้ลองเช็คว่า PHP พ่น Error อะไรออกมาใน Network Tab ครับ
    });
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