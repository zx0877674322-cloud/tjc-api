function toggleForm() {
    const container = document.getElementById('requestFormContainer');
    const btn = document.getElementById('btnToggle');
    const icon = btn.querySelector('.toggle-icon');
    const text = btn.querySelector('span');

    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'block';
        btn.classList.add('toggle-active');
        btn.style.background = '#ef4444'; // เปลี่ยนสีเป็นแดง
        text.innerHTML = '<i class="fas fa-minus-circle"></i> ปิดแบบฟอร์ม';
    } else {
        container.style.display = 'none';
        btn.classList.remove('toggle-active');
        btn.style.background = '#2563eb'; // กลับเป็นสีน้ำเงิน
        text.innerHTML = '<i class="fas fa-plus-circle"></i> เปิดแบบฟอร์มแจ้งลบเอกสาร';
    }
}

// 2. ดูสาเหตุ Popup (SweetAlert)
function showReason(text) {
    Swal.fire({
        title: 'สาเหตุ',
        text: text,
        icon: 'info',
        confirmButtonColor: '#ef4444',
        showCloseButton: true
    });
}

// 3. ยืนยันลบแบบไม่รีเฟรช (AJAX)
function confirmDelete(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "ดำเนินการลบใน WINSpeed แล้วใช่หรือไม่?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ใช่, ลบแล้ว',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'ajax_complete');
            formData.append('id', id);

            fetch('WINSpeedDeleteRequest.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const row = document.getElementById('row-' + id);
                    if (row) {
                        // 1. อัปเดตช่องสถานะ (โชว์แค่ Badge)
                        const statusCell = row.querySelector('.status-cell');
                        if(statusCell) statusCell.innerHTML = '<span class="badge badge-completed"><i class="fas fa-check"></i> เสร็จสิ้น</span>';
                        
                        // 2. อัปเดตช่องผู้ยืนยัน (โชว์ชื่อ + เวลา)
                        const completedCell = row.querySelector('.completed-cell');
                        if(completedCell) {
                            completedCell.innerHTML = `
                                <div style="font-size:12px; font-weight:600; color:#166534;">${data.admin_name}</div>
                                <div style="font-size:10px; color:#64748b;">${data.date}</div>
                            `;
                        }

                        // 3. ลบปุ่มจัดการออก
                        const actionCell = row.querySelector('.action-cell');
                        if(actionCell) actionCell.innerHTML = '-';
                    }
                    Swal.fire('สำเร็จ', 'อัปเดตสถานะเรียบร้อย', 'success');
                } else {
                    Swal.fire('Error', data.message || 'เกิดข้อผิดพลาด', 'error');
                }
            });
        }
    });
}
document.addEventListener('DOMContentLoaded', function() {
    flatpickr(".date-picker", {
        locale: "th",             // ภาษาไทย
        
        // 1. ค่าจริงที่จะส่งไป Database (ควรเป็น ปี-เดือน-วัน เพื่อไม่ให้ Query พัง)
        dateFormat: "Y-m-d",      
        
        // 2. ส่วนการแสดงผลให้คนเห็น (DD/MM/YYYY)
        altInput: true,           // เปิดใช้งานช่องแสดงผลแยก
        altFormat: "d/m/Y",       // รูปแบบที่ตาเห็น: 04/02/2026
        
        disableMobile: "true",    // บังคับใช้หน้าตานี้ในมือถือ
        allowInput: true          // อนุญาตให้พิมพ์วันที่เองได้
    });
});
function toggleRemarkField() {
    const select = document.getElementById('doc_type_select');
    const remarkDiv = document.getElementById('other_remark_field');
    const remarkInput = document.getElementById('doc_type_remark_input');

    if (select.value === 'Other') {
        // ถ้าเลือก อื่นๆ -> แสดงช่องกรอก และบังคับกรอก (Required)
        remarkDiv.style.display = 'block';
        remarkInput.required = true;
        // เพิ่ม Animation เล็กน้อย
        remarkDiv.style.animation = "fadeIn 0.3s";
    } else {
        // ถ้าเลือกอย่างอื่น -> ซ่อนช่องกรอก และยกเลิกบังคับกรอก
        remarkDiv.style.display = 'none';
        remarkInput.required = false;
        remarkInput.value = ''; // ล้างค่าเดิมออก
    }
}
// ฟังก์ชันดึงข้อมูลมาใส่ฟอร์ม (โหมดแก้ไข)
function populateEditForm(data) {
    console.log("Editing Data:", data); // เช็คข้อมูลใน Console (กด F12 ดูได้)

    // 1. เปิดฟอร์มและเลื่อนขึ้นบน
    const container = document.getElementById('requestFormContainer');
    if (container.style.display === 'none' || container.style.display === '') {
        toggleForm(); 
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // 2. ตั้งค่า Hidden Field และเปลี่ยนสถานะปุ่ม
    document.getElementById('form_action').value = 'update_request'; // เปลี่ยนโหมดเป็นแก้ไข
    document.getElementById('request_id').value = data.id;           // ใส่ ID ที่จะแก้
    
    // เปลี่ยนปุ่มเป็นสีส้ม "บันทึกการแก้ไข"
    const btnSubmit = document.getElementById('btn_submit');
    btnSubmit.innerHTML = '<i class="fas fa-save"></i> บันทึกการแก้ไข';
    btnSubmit.style.background = '#f59e0b';
    
    // โชว์ปุ่มยกเลิก
    document.getElementById('btn_cancel').style.display = 'block';

    // 3. กรอกข้อมูลพื้นฐาน (Text & Textarea)
    // ใช้ getElementsByName เพราะใน HTML อาจจะไม่ได้ใส่ ID ไว้ทุกตัว
    document.getElementsByName('doc_number')[0].value = data.doc_number;
    document.getElementsByName('reason')[0].value = data.reason;

    // 4. เลือกบริษัท (Radio Button)
    // วนลูปหา Radio ที่มีค่าตรงกับข้อมูล แล้วสั่ง checked = true
    const radios = document.getElementsByName('target_winspeed_company');
    for (let radio of radios) {
        if (radio.value === data.target_winspeed_company) {
            radio.checked = true;
            // เพิ่ม Effect ให้รู้ว่าเลือกตัวนี้ (ถ้าใช้ CSS ตัวที่ผมให้ไป)
            // radio.parentElement.classList.add('selected'); // ถ้ามี class highlight
            break;
        }
    }

    // 5. จัดการประเภทเอกสาร (Select Box + Remark)
    const docTypeSelect = document.getElementById('doc_type_select');
    const remarkInput = document.getElementById('doc_type_remark_input');
    const remarkDiv = document.getElementById('other_remark_field');

    // เช็คว่าข้อมูลเริ่มด้วยคำว่า "อื่นๆ" หรือไม่
    if (data.doc_type.startsWith('อื่นๆ')) {
        docTypeSelect.value = 'Other'; // เลือก Dropdown เป็น Other
        
        // ตัดคำว่า "อื่นๆ " ออก เพื่อเอาแค่ข้อความข้างหลังมาใส่ช่องหมายเหตุ
        // เช่น "อื่นๆ ใบเสนอราคา" -> ตัดเหลือ "ใบเสนอราคา"
        let remarkText = data.doc_type.replace('อื่นๆ', '').trim();
        
        // ถ้ามีวงเล็บหลงเหลืออยู่ (จากข้อมูลเก่า) ก็ตัดออกด้วย
        remarkText = remarkText.replace(/^\(/, '').replace(/\)$/, '');
        
        remarkInput.value = remarkText;

        // บังคับโชว์ช่องหมายเหตุ
        remarkDiv.style.display = 'block';
        remarkInput.required = true;
    } else {
        // กรณีเป็น PO หรือ AX
        docTypeSelect.value = data.doc_type;
        remarkInput.value = ''; // ล้างค่า
        
        // ซ่อนช่องหมายเหตุ
        remarkDiv.style.display = 'none';
        remarkInput.required = false;
    }
}

// ฟังก์ชันยกเลิกแก้ไข (เคลียร์ฟอร์มกลับเป็นปกติ)
function cancelEditMode() {
    // ล้างค่า
    document.getElementById('form_action').value = 'submit_request';
    document.getElementById('request_id').value = '';
    document.querySelector('form').reset(); // รีเซ็ตทุกช่อง
    
    // คืนค่าปุ่มเดิม
    const btnSubmit = document.getElementById('btn_submit');
    btnSubmit.innerHTML = '<i class="fas fa-paper-plane"></i> ส่งแจ้งลบ';
    btnSubmit.style.background = '#ef4444'; // สีแดงเดิม
    
    // ซ่อนปุ่มยกเลิก
    document.getElementById('btn_cancel').style.display = 'none';
    
    // รีเซ็ตช่องหมายเหตุ
    toggleRemarkField();
}
function cancelRequest(id) {
    Swal.fire({
        title: '<span style="font-family:Prompt; font-weight:600; color:#334155;">ยืนยันการยกเลิก?</span>',
        html: `
            <div style="font-family:Prompt; font-size:0.95rem; color:#64748b; margin-bottom:10px;">
                รายการนี้จะถูกเปลี่ยนสถานะเป็น <b style="color:#ef4444;">"ยกเลิก"</b><br>
                และจะไม่ถูกนำไปดำเนินการต่อ
            </div>
            <div style="text-align:left; font-family:Prompt; font-size:0.9rem; color:#334155; margin-bottom:5px; font-weight:500;">
                ระบุสาเหตุ / หมายเหตุ: <span style="color:red">*</span>
            </div>
        `,
        input: 'textarea',
        inputPlaceholder: 'เช่น ข้อมูลไม่ถูกต้อง, แจ้งผิด, เอกสารซ้ำซ้อน...',
        inputAttributes: {
            'style': 'font-family:Prompt; font-size:0.95rem; border-radius:10px; padding:12px; border:1px solid #cbd5e1; box-shadow:inset 0 1px 2px rgba(0,0,0,0.05); min-height:80px;'
        },
        icon: 'warning',
        iconColor: '#f87171', // สีไอคอนแดงอ่อนๆ
        showCancelButton: true,
        
        // --- 🔴 จุดที่เปลี่ยนสีปุ่ม ---
        confirmButtonText: '<i class="fas fa-ban"></i> ยืนยันยกเลิก',
        confirmButtonColor: '#ef4444', // สีแดงสวย (Red-500)
        cancelButtonText: 'ปิดหน้าต่าง',
        cancelButtonColor: '#94a3b8',  // สีเทา
        
        reverseButtons: true,
        focusInput: true,
        
        customClass: {
            popup: 'swal2-rounded-popup',
            confirmButton: 'swal2-confirm-btn-danger', // เพิ่มคลาสเผื่ออยากแต่ง CSS เพิ่ม
        },
        preConfirm: (value) => {
            if (!value) {
                Swal.showValidationMessage('<i class="fas fa-exclamation-circle"></i> กรุณาระบุสาเหตุการยกเลิกก่อนครับ')
            }
            return value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const reason = result.value;
            
            // แสดง Loading
            Swal.fire({
                title: 'กำลังบันทึก...',
                html: 'กรุณารอสักครู่',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            // ส่งข้อมูล
            const formData = new FormData();
            formData.append('action', 'ajax_cancel');
            formData.append('id', id);
            formData.append('cancel_reason', reason);

            fetch('WINSpeedDeleteRequest.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'ยกเลิกรายการสำเร็จ',
                        text: 'บันทึกสถานะเรียบร้อยแล้ว',
                        confirmButtonColor: '#10b981', // สีเขียวตอนสำเร็จ
                        confirmButtonText: 'ตกลง',
                        timer: 2000
                    }).then(() => {
                        location.reload(); 
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message,
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
            });
        }
    });
}
function showCancelReason(reason) {
    Swal.fire({
        title: '<span style="font-family:Prompt; font-weight:600; color:#ef4444;"><i class="fas fa-times-circle"></i> ถูกยกเลิกเนื่องจาก</span>',
        html: `
            <div style="font-family:Prompt; font-size:1rem; color:#334155; text-align:left; background:#fef2f2; padding:15px; border-radius:10px; border:1px solid #fca5a5; line-height: 1.6;">
                ${reason}
            </div>
        `,
        confirmButtonText: 'รับทราบ',
        confirmButtonColor: '#64748b',
        customClass: {
            popup: 'swal2-rounded-popup'
        }
    });
}