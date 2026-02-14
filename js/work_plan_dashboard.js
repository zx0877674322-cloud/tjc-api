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

// Animation เมื่อบันทึกสำเร็จ (รับค่าจาก PHP Session หรือ Parameter ถ้าทำเพิ่ม)
// แต่นี่เราใช้ PHP echo Script อยู่แล้ว สามารถย้ายมาไว้ตรงนี้ได้ถ้าต้องการปรับโครงสร้างใหญ่
// เบื้องต้นใช้ Inline Script ที่ PHP ส่งมาเหมือนเดิม เพื่อความง่ายในการเชื่อมต่อ