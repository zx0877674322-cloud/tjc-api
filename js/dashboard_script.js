function filterByStatus(status) {
    const statusSelect = document.querySelector('select[name="filter_status"]');
    if (statusSelect) { statusSelect.value = status; }
    const filterForm = document.querySelector('.filter-section');
    if (filterForm) {
        if (filterForm.tagName === 'FORM') { filterForm.submit(); }
        else {
            const actualForm = filterForm.querySelector('form');
            if (actualForm) actualForm.submit();
        }
    }
}

// ✅ ฟังก์ชันแสดงรายละเอียด (แก้ไขให้รองรับไฟล์หลายใบ)
function showDetail(data) {
    // uploadPath มาจากตัวแปร global ในไฟล์หลัก
    let slipsHtml = '';

    // ฟังก์ชันช่วยสร้างปุ่ม (จะได้ไม่ต้องเขียนซ้ำ)
    const createBtn = (filesStr, path, cssClass, label, icon) => {
        let html = '';
        if (filesStr) {
            const files = filesStr.split(','); // 🟢 ระเบิดชื่อไฟล์ด้วยคอมม่า
            files.forEach(file => {
                file = file.trim();
                if (file) {
                    html += `<a href="${path}${file}" target="_blank" class="evidence-btn ${cssClass}">
                                <i class="fas ${icon}"></i> ${label}
                             </a>`;
                }
            });
        }
        return html;
    };

    // 1. สร้างปุ่มหลักฐาน
    slipsHtml += createBtn(data.fuel_receipt, uploadPath, 'ev-fuel', 'บิลน้ำมัน', 'fa-gas-pump');
    slipsHtml += createBtn(data.accommodation_receipt, uploadPath, 'ev-hotel', 'บิลที่พัก', 'fa-hotel');
    slipsHtml += createBtn(data.other_receipt, uploadPath, 'ev-other', 'บิลอื่นๆ', 'fa-receipt');

    if (slipsHtml === '') slipsHtml = '<span style="color:#94a3b8; font-style:italic;">- ไม่มีหลักฐานแนบ -</span>';

    // 2. จัดการข้อมูล GPS
    let gpsHtml = '';
    if (data.gps && data.gps !== 'Office') {
        gpsHtml = `
            <div class="gps-card">
                <div class="gps-icon-box"><i class="fas fa-map-marked-alt"></i></div>
                <div>
                    <div style="font-weight:600; color:#1e293b;">Check-in นอกสถานที่</div>
                    <div style="font-size:0.9rem; color:#475569;">${data.gps_address || data.gps}</div>
                    <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">จ.${data.province || '-'} (โซน ${data.area || '-'})</div>
                </div>
            </div>`;
    } else {
        gpsHtml = `
            <div class="gps-card" style="background:#f1f5f9; border-color:#e2e8f0;">
                <div class="gps-icon-box" style="background:#64748b;"><i class="fas fa-building"></i></div>
                <div>
                    <div style="font-weight:600; color:#1e293b;">ปฏิบัติงานที่ออฟฟิศ</div>
                    <div style="font-size:0.9rem; color:#475569;">สำนักงานใหญ่ / ประจำสาขา</div>
                </div>
            </div>`;
    }

    // 3. จัดการนัดหมาย
    let nextAppt = data.next_appointment 
        ? `<span style="color:#d97706; font-weight:700;"><i class="far fa-calendar-check"></i> ${data.next_appointment}</span>` 
        : '-';

    // 4. สร้าง HTML
    let html = `
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">ผู้รายงาน</span>
                <span class="info-value highlight">${data.reporter_name}</span>
            </div>
            <div class="info-item">
                <span class="info-label">วันที่รายงาน</span>
                <span class="info-value"><i class="far fa-clock"></i> ${data.report_date}</span>
            </div>
            <div class="info-item">
                <span class="info-label">ลูกค้า / หน่วยงาน</span>
                <span class="info-value" style="color:var(--primary-color); font-weight:700;">${data.work_result}</span>
                <span style="font-size:0.85rem; color:#64748b;">${data.project_name ? 'โครงการ: '+data.project_name : ''} (${data.customer_type})</span>
            </div>
            <div class="info-item">
                <span class="info-label">สถานะงาน</span>
                <span class="info-value"><span class="status-badge" style="background:#f1f5f9; color:#334155; font-size:0.9rem;">${data.job_status}</span></span>
            </div>
        </div>

        ${gpsHtml}

        <div class="summary-box">
            <div class="summary-title"><i class="fas fa-comment-alt"></i> สรุปการเข้าพบ</div>
            <div class="summary-content">${data.activity_detail || '<span style="color:#ccc;">- ไม่มีการระบุรายละเอียด -</span>'}</div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">นัดหมายครั้งถัดไป</span>
                <span class="info-value">${nextAppt}</span>
            </div>
        </div>

        <div class="expense-section">
            <div style="padding:10px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-weight:700; color:#475569; font-size:0.9rem;">
                <i class="fas fa-wallet"></i> รายละเอียดค่าใช้จ่าย
            </div>
            <div class="expense-row">
                <span><i class="fas fa-gas-pump" style="color:#f97316; width:20px;"></i> น้ำมัน</span>
                <span>${parseFloat(data.fuel_cost||0).toLocaleString()}</span>
            </div>
            <div class="expense-row">
                <span><i class="fas fa-hotel" style="color:#3b82f6; width:20px;"></i> ที่พัก</span>
                <span>${parseFloat(data.accommodation_cost||0).toLocaleString()}</span>
            </div>
            <div class="expense-row">
                <span><i class="fas fa-receipt" style="color:#eab308; width:20px;"></i> อื่นๆ</span>
                <span>${parseFloat(data.other_cost||0).toLocaleString()}</span>
            </div>
            <div class="expense-row" style="color:var(--primary-color); background:#eff6ff;">
                <span>รวมสุทธิ</span>
                <span>${parseFloat(data.total_expense||0).toLocaleString()} บาท</span>
            </div>
        </div>

        <div class="info-item">
            <span class="info-label" style="margin-bottom:10px;">หลักฐานการเบิก</span>
            <div style="display:flex; flex-wrap:wrap;">${slipsHtml}</div>
        </div>

        ${data.additional_notes ? `
            <div style="margin-top:20px; padding:15px; background:#fffbeb; border-radius:10px; border:1px solid #fcd34d;">
                <div class="info-label" style="color:#b45309;">หมายเหตุเพิ่มเติม</div>
                <div style="color:#92400e;">${data.additional_notes}</div>
            </div>` : ''}
            
        ${data.problem ? `
            <div style="margin-top:10px; padding:15px; background:#fef2f2; border-radius:10px; border:1px solid #fca5a5;">
                <div class="info-label" style="color:#b91c1c;">ปัญหาที่พบ</div>
                <div style="color:#991b1b;">${data.problem}</div>
            </div>` : ''}
    `;

    document.getElementById('modalBody').innerHTML = html;
    
    const modal = document.getElementById('detailModal');
    modal.style.display = 'block';
    
    // Animation
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function showCustomerHistory(customerName) {
    document.getElementById('histModalTitle').innerHTML = '<i class="fas fa-history"></i> ประวัติ: ' + customerName;
    document.getElementById('histModalBody').innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-sub);"><i class="fas fa-spinner fa-spin"></i> กำลังโหลดข้อมูล...</div>';
    document.getElementById('historyModal').style.display = 'block';

    var startDate = document.querySelector('input[name="start_date"]').value;
    var endDate = document.querySelector('input[name="end_date"]').value;
    var url = '?ajax_action=get_customer_history&customer_name=' + encodeURIComponent(customerName);
    if (startDate) url += '&start_date=' + startDate;
    if (endDate) url += '&end_date=' + endDate;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                document.getElementById('histModalBody').innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-sub);">ไม่พบประวัติในช่วงเวลาที่เลือก</div>';
                return;
            }
            let listHtml = '';
            data.forEach(item => {
                let expense = parseFloat(item.total_expense) > 0 ? `<span style="color:#ef4444; font-size:12px;">(฿${parseFloat(item.total_expense).toLocaleString()})</span>` : '';
                let projectHtml = item.project_name ? `<div class="hist-project"><i class="fas fa-folder"></i> ${item.project_name}</div>` : '';
                let noteHtml = item.additional_notes ? `<div class="hist-note"><i class="far fa-comment-dots"></i> ${item.additional_notes}</div>` : '';
                listHtml += `
                    <div class="hist-item">
                        <div class="hist-header"><span><i class="far fa-calendar-alt"></i> ${item.report_date}</span>${expense}</div>
                        <div class="hist-user"><i class="fas fa-user-circle" style="color:var(--primary-color);"></i> ${item.reporter_name}</div>
                        ${projectHtml}
                        <div style="margin-top:5px;"><span class="hist-badge">สถานะ: ${item.job_status}</span></div>
                        ${noteHtml}
                    </div>`;
            });
            document.getElementById('histModalBody').innerHTML = listHtml;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('histModalBody').innerHTML = '<div style="color:red; text-align:center;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
        });
}

function closeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

function openExpenseModal(data) {
    document.getElementById('ex_report_id').value = data.id;
    
    // 1. Reset ค่าน้ำมัน: ลบแถวที่เกินทิ้ง เหลือแค่แถวแรก
    const container = document.getElementById('fuel_edit_container');
    while (container.children.length > 1) {
        container.removeChild(container.lastChild);
    }
    
    // ใส่ค่าเดิมลงแถวแรก (User จะเห็นเป็นยอดรวมเดิม)
    document.getElementById('ex_fuel_0').value = parseFloat(data.fuel_cost || 0);
    showOldFileStatus('prev_fuel_0', data.fuel_receipt);

    // 2. ค่าอื่นๆ
    document.getElementById('ex_hotel').value = parseFloat(data.accommodation_cost || 0);
    document.getElementById('ex_other').value = parseFloat(data.other_cost || 0);
    
    showOldFileStatus('prev_hotel', data.accommodation_receipt);
    showOldFileStatus('prev_other', data.other_receipt);
    
    calcTotalEdit(); // คำนวณใหม่
    
    const modal = document.getElementById('expenseModal');
    modal.style.display = 'block';
    setTimeout(() => { modal.classList.add('show'); }, 10);
}

function showOldFileStatus(id, fileName) {
    const el = document.getElementById(id);
    
    // เงื่อนไขใหม่: ต้องมีค่า และไม่ใช่ค่าว่าง (""), ไม่ใช่ "0", ไม่ใช่ "null"
    if (fileName && fileName.toString().trim() !== "" && fileName !== "0" && fileName !== "null") {
        el.innerHTML = '<i class="fas fa-check-circle"></i> มีสลิปเดิมแล้ว';
        el.style.color = '#3b82f6'; // สีฟ้า
        el.style.fontWeight = '500';
    } else {
        el.innerHTML = '<span style="color:#9ca3af;">- ไม่มีสลิปเดิม -</span>';
    }
}

function addFuelRowEdit() {
    const container = document.getElementById('fuel_edit_container');
    const index = container.children.length; // ใช้นับจำนวนเพื่อสร้าง ID ไม่ซ้ำ
    
    const div = document.createElement('div');
    div.className = 'fuel-row';
    // ใช้ Style เดียวกับแถวแรก (display:flex; gap:10px; margin-bottom:10px;)
    div.style.cssText = "display:flex; gap:10px; margin-bottom:10px; align-items:flex-start; animation: fadeIn 0.3s;";
    
    div.innerHTML = `
        <input type="number" step="0.01" name="fuel_cost[]" class="form-control fuel-calc" 
               placeholder="0.00" oninput="calcTotalEdit()" style="height: 38px;">
        
        <div style="width:50%;">
            <label class="upload-btn-mini">
                <i class="fas fa-upload"></i> เปลี่ยนสลิป
                <input type="file" name="fuel_file[]" accept="image/*" hidden onchange="previewFile(this, 'prev_fuel_${index}')">
            </label>
            <div id="prev_fuel_${index}" class="file-status"></div>
        </div>

        <button type="button" onclick="this.parentElement.remove(); calcTotalEdit();" 
                style="border:none; background:none; color:#ef4444; cursor:pointer; height:38px; width:30px; display:flex; align-items:center; justify-content:center; padding:0;">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    
    container.appendChild(div);
}

function calcTotalEdit() {
    let fuelTotal = 0;
    
    // วนลูปหา Input ค่าน้ำมันทั้งหมด
    document.querySelectorAll('.fuel-edit-input').forEach(input => {
        fuelTotal += parseFloat(input.value) || 0;
    });

    let h = parseFloat(document.getElementById('ex_hotel').value) || 0;
    let o = parseFloat(document.getElementById('ex_other').value) || 0;
    
    let total = fuelTotal + h + o;
    document.getElementById('ex_total_display').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' ฿';
}

function previewFile(input, displayId) {
    if (input.files && input.files[0]) {
        document.getElementById(displayId).innerHTML = '<i class="fas fa-file-upload"></i> ' + input.files[0].name;
        document.getElementById(displayId).style.color = '#10b981';
    }
}