// Theme Toggle Logic
const toggleBtn = document.getElementById('theme-toggle');
if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
        let theme = document.body.getAttribute('data-theme');
        if (theme === 'dark') {
            document.body.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
            updateToggleIcon('light');
        } else {
            document.body.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            updateToggleIcon('dark');
        }
    });
}

function updateToggleIcon(theme) {
    const btn = document.getElementById('theme-toggle');
    if (btn) {
        if (theme === 'dark') {
            btn.innerHTML = '<i class="fas fa-sun"></i>';
        } else {
            btn.innerHTML = '<i class="fas fa-moon"></i>';
        }
    }
}

// Initialization on Load
document.addEventListener("DOMContentLoaded", function () {
    attachAjaxEvents();
    applyTheme();
    initFlatpickr();
});

function initFlatpickr() {
    flatpickr(".datepicker-input", {
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d/m/Y",
        locale: "th",
        disableMobile: "true",
        allowInput: true
    });
}
flatpickr("#export_start_date", {
        dateFormat: "Y-m-d",      // ค่าที่ส่งไปหลังบ้าน (PHP) เป็น Y-m-d
        altInput: true,           // เปิดโหมดแสดงผลแยก
        altFormat: "d/m/Y",       // ให้ user เห็นเป็น DD/MM/YYYY
        locale: "th",             // ภาษาไทย
        defaultDate: "<?php echo date('Y-m-01'); ?>" // ค่าเริ่มต้น (ต้นเดือน)
    });

    flatpickr("#export_end_date", {
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d/m/Y",
        locale: "th",
        defaultDate: "<?php echo date('Y-m-d'); ?>" // ค่าเริ่มต้น (วันนี้)
    });

function confirmExport(e) {
    e.preventDefault();
    const sDate = document.getElementById('ex_start_date').value;
    const eDate = document.getElementById('ex_end_date').value;

    if (!sDate || !eDate) {
        alert('กรุณาระบุวันที่ให้ครบถ้วน');
        return;
    }
    const url = `DocumentDashboard.php?export_excel=1&start_date=${sDate}&end_date=${eDate}`;
    window.open(url, '_blank');
    closeModals();
}

function toggleCard(card) {
    card.classList.toggle('open');
}

function openDetailModal(data) {
    closeModals();
    document.getElementById('d_header_info').innerText = data.doc_type + ' ' + data.doc_number + ' | ' + data.supplier_name;
    document.getElementById('d_full_desc').innerText = data.description;
    
    let whtShow = (data.wht_tax && data.wht_tax !== 'ไม่มี') ? data.wht_tax : '-';
    if (data.wht_amount && parseFloat(data.wht_amount) > 0) {
        whtShow += ' (' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(data.wht_amount) + ' บ.)';
    }
    document.getElementById('d_wht_val').innerText = whtShow;
    
    let attContainer = document.getElementById('d_att_container');
    attContainer.innerHTML = '';
    let attList = [];
    try {
        attList = JSON.parse(data.attachments);
    } catch (e) {
        if (data.attachments && data.attachments !== '[]') attList = [data.attachments];
    }
    
    if (Array.isArray(attList) && attList.length > 0) {
        attList.forEach(item => {
            let badgeClass = 'tag-default';
            let icon = '<i class="fas fa-paperclip"></i>';
            let text = item;
            if (item.includes('รอใบกำกับภาษี 7%')) {
                badgeClass = 'tag-red';
                icon = '<i class="fas fa-exclamation-triangle"></i>';
                text += ' (ตาม!)';
            } else if (item.includes('ใบกำกับภาษี 7%')) {
                badgeClass = 'tag-green';
                icon = '<i class="fas fa-check-circle"></i>';
            } else if (item.includes('ใบสำคัญจ่าย')) {
                badgeClass = 'tag-yellow';
                icon = '<i class="fas fa-file-invoice-dollar"></i>';
            }
            attContainer.innerHTML += `<span class="tag-badge ${badgeClass}">${icon} ${text}</span>`;
        });
    } else {
        attContainer.innerHTML = '<span style="color:#cbd5e1; font-size:11px;">ไม่มีเอกสารแนบ</span>';
    }
    let safeStatusNote = (data.tax_status_note || '')
        .replace(/'/g, "\\'")
        .replace(/"/g, '&quot;')
        .replace(/\r\n/g, '\\n')
        .replace(/\n/g, '\\n');

    let statusSection = '';
    
    if (data.tax_status_note && data.tax_status_note !== "") {
        // กรณีมีข้อความแล้ว: โชว์กล่องข้อความ + ปุ่มแก้ไขเล็กๆ
        statusSection = `
            <div style="width: 100%; margin-top: 10px; padding: 10px; background-color: #fff7ed; border: 1px solid #fdba74; border-radius: 8px; color: #9a3412;">
                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:4px;">
                    <div style="font-size: 11px; font-weight: 700;"><i class="fas fa-info-circle"></i> หมายเหตุสถานะ:</div>
                    <button onclick="openModal('status_note', ${data.id}, '', '', '${safeStatusNote}')" 
                        style="border:none; background:transparent; color:#ea580c; font-size:11px; font-weight:700; cursor:pointer; padding:0;">
                        <i class="fas fa-pen"></i> แก้ไข
                    </button>
                </div>
                <div style="font-size: 13px; line-height: 1.5; white-space: pre-wrap;">${data.tax_status_note}</div>
            </div>
        `;
    } else {
        // กรณีไม่มีข้อความ: โชว์ปุ่มกดเพิ่ม
        statusSection = `
            <button onclick="openModal('status_note', ${data.id}, '', '', '')" 
                style="width:100%; margin-top:10px; padding:8px; border:1px dashed #cbd5e1; background:#f8fafc; border-radius:8px; color:#64748b; font-size:12px; cursor:pointer; transition:0.2s;">
                <i class="fas fa-plus-circle"></i> เพิ่มหมายเหตุสถานะ
            </button>
        `;
    }
    // แทรกต่อท้ายในกล่องเอกสารแนบเลย
    attContainer.innerHTML += statusSection;
    document.getElementById('d_created_by').innerText = data.created_by || '-';
    document.getElementById('d_created_at').innerText = data.created_at;
    
    let safeNote = (data.doc_note || '')
        .replace(/'/g, "\\'")
        .replace(/"/g, '&quot;')
        .replace(/\r\n/g, '\\n') // รองรับ Windows Line break
        .replace(/\n/g, '\\n');  // รองรับ Unix Line break

    // 🟢 [แก้ไขจุดที่ 2] เพิ่ม white-space: pre-wrap; เพื่อให้แสดงผลเว้นบรรทัดสวยงาม
    let noteHtml = data.doc_note ? 
        `<div class="info-box" style="background:#fff7ed; border-color:#ffedd5;">
            <label style="font-size:11px; font-weight:700; color:#c2410c;">
                <i class="fas fa-comment"></i> หมายเหตุ
            </label>
            <div style="font-size:13px; color:#431407; margin-top:4px; line-height:1.6; white-space: pre-wrap;">${data.doc_note}</div>
            <div style="font-size:10px; color:#9a3412; text-align:right; margin-top:5px; border-top:1px dashed #fdba74; padding-top:2px;">
                โดย: ${data.doc_note_by}
            </div>
        </div>
        <button onclick="openModal('note', ${data.id}, '', '', '${safeNote}')" style="width:100%; padding:5px; border:1px solid #cbd5e1; background:#fff; border-radius:5px; cursor:pointer; margin-top:5px;">
            ✏️ แก้ไขโน้ต
        </button>` : 
        `<button onclick="openModal('note', ${data.id}, '', '', '')" style="width:100%; padding:8px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:5px; cursor:pointer; color:#64748b; margin-top:5px;">
            + เพิ่มโน้ต
        </button>`;
        
    document.getElementById('modal_detail_view').style.display = 'flex';
}

function openModal(type, id, val1 = '', val2 = '', val3 = '', val4 = '') {
    if (typeof closeModals === 'function') {
        closeModals();
    } else {
        document.querySelectorAll('.modal').forEach(modal => modal.style.display = 'none');
    }

    function setNote(noteValue) {
        const noteIds = ['action_note', 'doc_note', 'note_input', 'tax_note', 'acc_note', 'note_text'];
        noteIds.forEach(nid => {
            const el = document.getElementById(nid);
            if (el) el.value = noteValue || '';
        });
    }

    function showOldFile(containerId, fileName, fileInputId) {
        const container = document.getElementById(containerId);
        const fileInput = document.getElementById(fileInputId);
        if (container) {
            if (fileName && fileName !== "") {
                container.innerHTML = `
                <div style="font-size:12px; color:#15803d; margin-top:8px; background:#f0fdf4; padding:6px 10px; border-radius:6px; border:1px dashed #86efac; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-file-check" style="font-size:14px;"></i> 
                    <span>มีไฟล์เดิมอยู่แล้ว:</span>
                    <a href="uploads/${fileName}" target="_blank" style="text-decoration:underline; font-weight:600; color:#15803d;">เปิดดูไฟล์</a>
                </div>
                <div style="font-size:10px; color:#6b7280; margin-top:2px; margin-left:4px;">* อัปโหลดไฟล์ใหม่เฉพาะเมื่อต้องการเปลี่ยนไฟล์</div>
                `;
                if (fileInput) fileInput.removeAttribute('required');
            } else {
                container.innerHTML = '';
                if (fileInput) fileInput.setAttribute('required', 'required');
            }
        }
    }

    let data = {};
    if (val4) {
        if (typeof val4 === 'object' && val4 !== null) {
            data = val4;
        } else {
            try {
                data = JSON.parse(val4);
            } catch (e) {
                console.error("JSON Parse Error in val4:", e);
                data = {};
            }
        }
    }

    if (type === 'tax') {
        const docIdEl = document.getElementById('tax_doc_id');
        if (docIdEl) docIdEl.value = id;
        if (document.getElementById('tax_inv_number')) {
            document.getElementById('tax_inv_number').value = val1 || '';
        } else if (document.getElementById('tax_inv_input_buyer')) {
            document.getElementById('tax_inv_input_buyer').value = val1 || '';
        }
        setNote(val2);
        let taxFileName = val3 || data.tax_file;
        showOldFile('tax_old_file_show', taxFileName, 'tax_file_input');
        const modal = document.getElementById('modal_receive_tax');
        if (modal) modal.style.display = 'flex';
    } 
    else if (type === 'acc') {
        // 1. เตรียมตัวแปร
        const docIdEl = document.getElementById('acc_doc_id');
        const accInvInput = document.getElementById('acc_inv_input');
        const accFileInput = document.getElementById('acc_file_input'); // ช่องอัปโหลดไฟล์
        const statusDiv = document.getElementById('buyer_file_status'); // ช่องโชว์สถานะไฟล์
        
        // 2. ใส่ค่า ID และ เลขที่ใบกำกับ
        if (docIdEl) docIdEl.value = id;
        
        let taxNo = val1;
        if (!taxNo) taxNo = data.tax_invoice_no || data.wh_tax_invoice_no || ''; 
        if (accInvInput) accInvInput.value = taxNo;
        
        // 3. ใส่หมายเหตุ
        setNote(val2);
        
        // 4. แสดงไฟล์เดิม (ของบัญชีเอง)
        let accFileName = val3 || data.acc_file;
        showOldFile('acc_old_file_show', accFileName, 'acc_file_input');

        // ============================================================
        // 🟢 5. [แก้ใหม่] จัดการสถานะไฟล์ + ปลดล็อก required
        // ============================================================
        if (statusDiv && accFileInput) {
            // เริ่มต้น: บังคับ reset ค่าและใส่ required ไว้ก่อน
            accFileInput.value = ''; 
            accFileInput.setAttribute('required', 'required');

            // ตรวจสอบเงื่อนไข
            let hasBuyerFile = (data.tax_file && data.tax_file !== "");
            let hasWhFile = (data.wh_tax_file && data.wh_tax_file !== "");
            let hasOwnFile = (accFileName && accFileName !== "");

            if (hasBuyerFile) {
                // กรณี A: ใช้ไฟล์ผู้ซื้อ
                statusDiv.innerHTML = `<span style="color:#16a34a; font-weight:500;"><i class="fas fa-check-circle"></i> มีไฟล์จากผู้ซื้อแล้ว</span> <a href="uploads/${data.tax_file}" target="_blank" style="font-size:12px; margin-left:5px; text-decoration:underline;">(ดูไฟล์)</a>`;
                accFileInput.removeAttribute('required'); // ปลดล็อก
            } 
            else if (hasWhFile) {
                // กรณี B: ใช้ไฟล์คลัง
                statusDiv.innerHTML = `<span style="color:#ea580c; font-weight:500;"><i class="fas fa-warehouse"></i> ใช้ไฟล์จากคลังสินค้า</span> <a href="uploads/${data.wh_tax_file}" target="_blank" style="font-size:12px; margin-left:5px; text-decoration:underline;">(ดูไฟล์)</a>`;
                
                // 🔥 บังคับปลดล็อก (ใช้ setTimeout ช่วยเพื่อให้ชัวร์ว่า DOM พร้อม)
                setTimeout(() => {
                    document.getElementById('acc_file_input').removeAttribute('required');
                    document.getElementById('acc_file_input').required = false; // สั่งแบบ Property ด้วย
                }, 10);
            } 
            else {
                // กรณี C: ไม่มีไฟล์เลย
                statusDiv.innerHTML = `<span style="color:#ef4444; font-weight:500;"><i class="fas fa-exclamation-circle"></i> ยังไม่มีไฟล์แนบ (กรุณาอัปโหลด)</span>`;
                
                // ถ้าบัญชีมีไฟล์เดิมอยู่แล้ว ก็ไม่ต้องบังคับ
                if (hasOwnFile) {
                    accFileInput.removeAttribute('required');
                } else {
                    accFileInput.setAttribute('required', 'required');
                }
            }
        }
        
        // เปิด Modal
        const modal = document.getElementById('modal_acc_receive');
        if (modal) modal.style.display = 'flex';
    }
    else if (type === 'wh_tax_receive') {
       document.getElementById('wh_tax_doc_id').value = id;

    // 2. ใส่ค่าเดิม (เลขที่ใบกำกับ) -> val1
    const invInput = document.querySelector('input[name="wh_tax_inv_number"]');
    if (invInput) invInput.value = val1 || '';

    // 3. ใส่ค่าเดิม (หมายเหตุ) -> val2
    const noteInput = document.getElementById('wh_action_note');
    if (noteInput) noteInput.value = val2 || '';

    // 4. จัดการไฟล์แนบ (แสดงไฟล์เก่าถ้ามี) -> val3
    // ใช้ฟังก์ชัน helper showOldFile ที่มีอยู่แล้วในไฟล์ JS เดิม
    let whFileName = val3 || ''; 
    showOldFile('wh_tax_old_file_show', whFileName, 'wh_tax_file_input');

    // 5. แสดง Modal
    const modal = document.getElementById('modal_wh_tax_receive');
    if (modal) modal.style.display = 'flex';
}
    else if (type === 'export') {
        const modal = document.getElementById('modal_export');
        if (modal) modal.style.display = 'flex';
    } 
    else if (type === 'approve') {
        document.getElementById('approve_doc_id').value = id;
        document.getElementById('modal_approve').style.display = 'flex';
    } 
    else if (type === 'finance') {
        document.getElementById('finance_doc_id').value = id;
        document.getElementById('modal_finance').style.display = 'flex';
    } 
    else if (type === 'note') {
        document.getElementById('note_doc_id').value = id;
        document.getElementById('note_text').value = val3 || '';
        document.getElementById('modal_note').style.display = 'flex';
    } 
    else if (type === 'credit') {
        document.getElementById('credit_doc_id').value = id;
        if (document.getElementById('credit_select')) {
            if (val1) document.getElementById('credit_select').value = val1;
            else document.getElementById('credit_select').value = "";
        }
        document.getElementById('modal_credit').style.display = 'flex';
    } 
    else if (type === 'warehouse') {
        document.getElementById('warehouse_doc_id').value = id;
        document.getElementById('modal_warehouse').style.display = 'flex';
    } 
    else if (type === 'bill_pay') {
        document.getElementById('bill_pay_doc_id').value = id;
        if (data) {
            if (document.getElementById('bp_tax_no')) document.getElementById('bp_tax_no').value = data.tax_invoice_no || '';
            if (document.getElementById('bp_bill_no')) document.getElementById('bp_bill_no').value = data.billing_doc_no || '';
            if (document.getElementById('bp_bill_date')) document.getElementById('bp_bill_date').value = data.billing_date || '';
            if (document.getElementById('bp_pay_due')) document.getElementById('bp_pay_due').value = data.payment_due_date || '';
            if (document.getElementById('bp_status')) document.getElementById('bp_status').value = data.payment_status || '';
        }
        document.getElementById('modal_bill_pay').style.display = 'flex';
    }
    else if (type === 'status_note') {
        // 1. ใส่ ID ลงใน Hidden Field
        const docIdEl = document.getElementById('status_note_doc_id');
        if (docIdEl) docIdEl.value = id;
        
        // 2. ใส่ข้อความเดิม (ถ้ามี)
        const inputEl = document.getElementById('status_note_input');
        if (inputEl) {
            // เช็คว่าค่าส่งมาที่ val3 หรือ val4 (กันพลาด)
            let noteVal = '';
            if (val3 && typeof val3 === 'string') noteVal = val3;
            else if (val4 && typeof val4 === 'string') noteVal = val4;
            
            inputEl.value = noteVal;
        }
        
        // 3. สั่งเปิด Modal
        const modal = document.getElementById('modal_status_note');
        if (modal) modal.style.display = 'flex';
    }
}

function closeModals() {
    document.querySelectorAll('.modal').forEach(el => el.style.display = 'none');
}

window.onclick = function (e) {
    if (e.target.classList.contains('modal')) closeModals();
}

function toggleSearch(id) {
    const input = document.getElementById(id);
    input.classList.toggle('show');
    if (input.classList.contains('show')) input.focus();
    else {
        input.value = '';
        filterTable();
    }
}

function filterTable() {
    const cleanText = (str) => str ? str.toLowerCase().replace(/\s+/g, '') : '';
    const docVal = cleanText(document.getElementById('search_doc').value);
    const userVal = cleanText(document.getElementById('search_user').value);
    const siteVal = cleanText(document.getElementById('search_site').value);
    const supVal = cleanText(document.getElementById('search_supplier').value);

    const rows = document.querySelectorAll('tbody tr.table-row-item');
    let sumMoney = 0;
    let sumJobs = 0;
    let grandStats = { pv_c: 0, pv_a: 0, vat_c: 0, vat_a: 0, wait_c: 0, wait_a: 0 };
    let compStats = {};

    rows.forEach(row => {
        const docText = row.cells[2] ? cleanText(row.cells[2].textContent) : '';
        const userText = row.cells[3] ? cleanText(row.cells[3].textContent) : '';
        const siteText = row.cells[4] ? cleanText(row.cells[4].textContent) : '';
        const supText = row.cells[5] ? cleanText(row.cells[5].textContent) : '';

        if (docText.includes(docVal) && userText.includes(userVal) && siteText.includes(siteVal) && supText.includes(supVal)) {
            row.style.display = '';
            let cid = row.getAttribute('data-company-id');
            let amt = parseFloat(row.getAttribute('data-amount')) || 0;
            let isPv = row.getAttribute('data-pv') == '1';
            let isVat = row.getAttribute('data-vat') == '1';
            let isWait = row.getAttribute('data-wait') == '1';
            let hasApp = row.getAttribute('data-has-app') == '1';
            let hasFin = row.getAttribute('data-has-fin') == '1';

            sumMoney += amt;
            sumJobs++;
            if (isPv) { grandStats.pv_c++; grandStats.pv_a += amt; }
            if (isVat) { grandStats.vat_c++; grandStats.vat_a += amt; }
            if (isWait) { grandStats.wait_c++; grandStats.wait_a += amt; }

            if (!compStats[cid]) {
                compStats[cid] = {
                    total: 0, count: 0, p_app: 0, app: 0, p_fin: 0, fin: 0,
                    pv_c: 0, pv_a: 0, vat_c: 0, vat_a: 0, wait_c: 0, wait_a: 0
                };
            }
            compStats[cid].total += amt;
            compStats[cid].count++;
            if (!hasApp) compStats[cid].p_app++;
            if (hasApp) compStats[cid].app++;
            if (!hasFin) compStats[cid].p_fin++;
            if (hasFin) compStats[cid].fin++;
            if (isPv) { compStats[cid].pv_c++; compStats[cid].pv_a += amt; }
            if (isVat) { compStats[cid].vat_c++; compStats[cid].vat_a += amt; }
            if (isWait) { compStats[cid].wait_c++; compStats[cid].wait_a += amt; }
        } else {
            row.style.display = 'none';
        }
    });

    const fmt = (num) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(num);
    const fmtMoney = (num) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);

    document.getElementById('sum_total_money').innerText = fmtMoney(sumMoney);
    document.getElementById('sum_total_jobs').innerText = fmt(sumJobs);
    document.getElementById('gt_pv_count').innerText = fmt(grandStats.pv_c); document.getElementById('gt_pv_amt').innerText = fmt(grandStats.pv_a);
    document.getElementById('gt_vat_count').innerText = fmt(grandStats.vat_c); document.getElementById('gt_vat_amt').innerText = fmt(grandStats.vat_a);
    document.getElementById('gt_wait_count').innerText = fmt(grandStats.wait_c); document.getElementById('gt_wait_amt').innerText = fmt(grandStats.wait_a);

    document.querySelectorAll('.kpi-card').forEach(card => {
        let cid = card.id.replace('card_comp_', '');
        let s = compStats[cid] || { total: 0, count: 0, p_app: 0, app: 0, p_fin: 0, fin: 0, pv_c: 0, pv_a: 0, vat_c: 0, vat_a: 0, wait_c: 0, wait_a: 0 };

        if (document.getElementById('c_money_' + cid)) document.getElementById('c_money_' + cid).innerText = fmtMoney(s.total);
        if (document.getElementById('c_job_' + cid)) document.getElementById('c_job_' + cid).innerText = fmt(s.count);
        if (document.getElementById('c_p_app_' + cid)) document.getElementById('c_p_app_' + cid).innerText = fmt(s.p_app);
        if (document.getElementById('c_app_' + cid)) document.getElementById('c_app_' + cid).innerText = fmt(s.app);
        if (document.getElementById('c_p_fin_' + cid)) document.getElementById('c_p_fin_' + cid).innerText = fmt(s.p_fin);
        if (document.getElementById('c_fin_rcv_' + cid)) document.getElementById('c_fin_rcv_' + cid).innerText = fmt(s.fin);
        if (document.getElementById('c_pv_cnt_' + cid)) document.getElementById('c_pv_cnt_' + cid).innerText = fmt(s.pv_c);
        if (document.getElementById('c_pv_amt_' + cid)) document.getElementById('c_pv_amt_' + cid).innerText = fmt(s.pv_a);
        if (document.getElementById('c_vat_cnt_' + cid)) document.getElementById('c_vat_cnt_' + cid).innerText = fmt(s.vat_c);
        if (document.getElementById('c_vat_amt_' + cid)) document.getElementById('c_vat_amt_' + cid).innerText = fmt(s.vat_a);
        if (document.getElementById('c_wait_cnt_' + cid)) document.getElementById('c_wait_cnt_' + cid).innerText = fmt(s.wait_c);
        if (document.getElementById('c_wait_amt_' + cid)) document.getElementById('c_wait_amt_' + cid).innerText = fmt(s.wait_a);
    });
}

function viewNote(title, text, color) {
    closeModals();
    document.getElementById('vn_title').innerText = title;
    document.getElementById('vn_text').innerText = text;
    document.getElementById('vn_icon').innerHTML = '<i class="fas fa-comment-alt" style="color:' + color + '"></i>';
    document.getElementById('modal_view_note').style.display = 'flex';
}

async function loadWithAjax(target) {
    let url = '';
    if (target.tagName === 'FORM') {
        const formData = new FormData(target);
        const params = new URLSearchParams(formData);
        url = window.location.pathname + '?' + params.toString();
    } else if (target.tagName === 'A') {
        url = target.getAttribute('href');
    } else {
        url = target;
    }

    const contentArea = document.getElementById('content-area');
    contentArea.style.opacity = '0.6';
    contentArea.style.pointerEvents = 'none';

    try {
        const response = await fetch(url);
        const text = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');
        const newContent = doc.getElementById('content-area').innerHTML;
        contentArea.innerHTML = newContent;

        if (url !== window.location.href) {
            window.history.pushState({ path: url }, '', url);
        }

        attachAjaxEvents();
        applyTheme();
        initFlatpickr();

    } catch (error) {
        console.error('AJAX Error:', error);
    } finally {
        contentArea.style.opacity = '1';
        contentArea.style.pointerEvents = 'auto';
    }
}

function attachAjaxEvents() {
    const links = document.querySelectorAll('#content-area a[href^="?"], .header-top a[href^="?"], .filter-bar a[href^="?"]');
    links.forEach(link => {
        link.removeEventListener('click', handleLinkClick);
        link.addEventListener('click', handleLinkClick);
    });
}

function applyTheme() {
    const theme = localStorage.getItem('theme') || 'light';
    if (theme === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
        updateToggleIcon('dark');
    } else {
        document.body.removeAttribute('data-theme');
        updateToggleIcon('light');
    }
}

function handleLinkClick(e) {
    if (this.classList.contains('no-ajax')) return;
    e.preventDefault();
    loadWithAjax(this);
}

window.onpopstate = function (event) {
    loadWithAjax(window.location.href);
};

async function submitModalForm(e, form) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const oldText = btn.getAttribute('data-original-text') || btn.innerHTML;
    if (!btn.getAttribute('data-original-text')) {
        btn.setAttribute('data-original-text', btn.innerHTML);
    }
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> กำลังบันทึก...';
    btn.disabled = true;

    try {
        const formData = new FormData(form);
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const text = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');
        const errorBox = doc.getElementById('server-error-box');
        
        if (errorBox) {
            Swal.fire({
                icon: 'error',
                title: 'บันทึกไม่สำเร็จ',
                html: errorBox.innerHTML,
                width: 600,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'ตกลง'
            });
            btn.innerHTML = oldText;
            btn.disabled = false;
            return;
        }

        const newContent = doc.getElementById('content-area');
        if (newContent) {
            const scrollY = window.scrollY;
            const tableContainer = document.querySelector('.table-responsive');
            const tableScrollX = tableContainer ? tableContainer.scrollLeft : 0;
            const tableScrollY = tableContainer ? tableContainer.scrollTop : 0;

            document.getElementById('content-area').innerHTML = newContent.innerHTML;
            window.scrollTo(0, scrollY);

            const newTableContainer = document.querySelector('.table-responsive');
            if (newTableContainer) {
                newTableContainer.scrollLeft = tableScrollX;
                newTableContainer.scrollTop = tableScrollY;
            }

            if (typeof attachAjaxEvents === 'function') attachAjaxEvents();
            if (typeof closeModals === 'function') closeModals();

            btn.innerHTML = oldText;
            btn.disabled = false;

            const Toast = Swal.mixin({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
            });
            Toast.fire({ icon: 'success', title: 'บันทึกข้อมูลเรียบร้อย' });
        } else {
            window.location.reload();
        }
    } catch (error) {
        console.error('Save Error:', error);
        Swal.fire({ icon: 'error', title: 'Connection Error', text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้' });
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}

function cancelDocument(id) {
    document.getElementById('cancel_doc_id').value = id;
    document.getElementById('modal_cancel').style.display = 'flex';
    setTimeout(() => {
        document.querySelector('#modal_cancel textarea').focus();
    }, 100);
}

function submitCancelForm(e, form) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const oldText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    btn.disabled = true;
    let formData = new FormData(form);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            window.location.reload();
        } else {
            alert('เกิดข้อผิดพลาด ไม่สามารถยกเลิกได้');
            btn.innerHTML = oldText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        alert('เชื่อมต่อล้มเหลว');
        btn.innerHTML = oldText;
        btn.disabled = false;
    });
}

function viewVoidDetail(who, when, reason) {
    const dateObj = new Date(when);
    const dateStr = dateObj.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
    const timeStr = dateObj.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });

    Swal.fire({
        title: '<span style="color:#ef4444;">รายการถูกยกเลิก </span>',
        icon: 'error',
        background: '#fff',
        html: `
        <div style="text-align: left; font-size: 15px; color: #374151;">
            <hr style="border: 0; border-top: 1px dashed #e5e7eb; margin: 10px 0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span><strong>👤 ผู้ทำรายการ:</strong></span>
                <span style="color: #4b5563;">${who}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <span><strong>🕒 เมื่อวันที่:</strong></span>
                <span style="color: #4b5563;">${dateStr} เวลา ${timeStr} น.</span>
            </div>
            <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 15px;">
                <strong style="color: #991b1b; display: block; margin-bottom: 5px;">
                    <i class="fas fa-comment-dots"></i> สาเหตุการยกเลิก:
                </strong>
                <span style="color: #ef4444; font-weight: 500;">"${reason}"</span>
            </div>
        </div>
        `,
        showConfirmButton: true,
        confirmButtonText: 'ปิดหน้าต่าง',
        confirmButtonColor: '#6b7280',
        showClass: { popup: 'animate__animated animate__fadeInDown' },
        hideClass: { popup: 'animate__animated animate__fadeOutUp' }
    });
}

function toggleVoidWidget() {
    const panel = document.getElementById('voidCardPanel');
    const arrow = document.getElementById('voidArrow');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        panel.style.display = 'none';
        arrow.style.transform = 'rotate(0deg)';
    }
}

function viewTaxDetails(title, colorTheme, data, editOnclick, isCancelled) {
    // 1. สร้าง HTML สำหรับแสดงรายละเอียด
    let contentHtml = `<div style="text-align:left; font-size:14px; line-height:1.6;">`;
    
    // เลขที่ใบกำกับ
    if(data.inv) {
        contentHtml += `<div style="margin-bottom:8px;">
            <span style="color:#64748b; font-size:12px;">เลขที่ใบกำกับ:</span><br>
            <span style="font-weight:700; font-size:16px; color:#0f172a;">${data.inv}</span>
        </div>`;
    } else {
        contentHtml += `<div style="margin-bottom:8px; color:#cbd5e1;">- ไม่ระบุเลขที่ -</div>`;
    }

    // ไฟล์แนบ
    if(data.file) {
        contentHtml += `<div style="margin-bottom:8px;">
            <span style="color:#64748b; font-size:12px;">ไฟล์แนบ:</span><br>
            <a href="uploads/${data.file}" target="_blank" style="display:inline-flex; align-items:center; gap:5px; text-decoration:none; color:${colorTheme}; font-weight:600; background:#f8fafc; padding:5px 10px; border-radius:6px; border:1px solid #e2e8f0;">
                <i class="fas fa-file-alt"></i> เปิดดูไฟล์เอกสาร
            </a>
        </div>`;
    } else {
        contentHtml += `<div style="margin-bottom:8px; color:#cbd5e1;">- ไม่มีไฟล์แนบ -</div>`;
    }

    // หมายเหตุ
    if(data.note) {
        contentHtml += `<div style="margin-top:10px; background:#f1f5f9; padding:10px; border-radius:8px;">
            <div style="font-size:11px; font-weight:700; color:#64748b;">📝 หมายเหตุ:</div>
            <div style="color:#334155;">${data.note}</div>
        </div>`;
    }
    contentHtml += `</div>`;

    // 2. สร้างปุ่ม Footer (ปุ่มแก้ไข)
    let footerHtml = '';
    if (!isCancelled && editOnclick) {
        // ใช้ Hack เล็กน้อยเพื่อส่ง onclick string เข้าไปในปุ่ม HTML
        // โดยเราจะใช้ id เพื่อ bind event ทีหลัง หรือใช้ attribute onclick ตรงๆ (ต้องระวัง quote)
        footerHtml = `<button id="swal-edit-btn" class="swal2-confirm swal2-styled" style="background-color: ${colorTheme}; width:100%;">
            <i class="fas fa-pen"></i> แก้ไขข้อมูล
        </button>`;
    }

    // 3. แสดง SweetAlert
    Swal.fire({
        title: `<span style="color:${colorTheme}"><i class="fas fa-info-circle"></i> ${title}</span>`,
        html: contentHtml,
        showConfirmButton: false, // ซ่อนปุ่ม OK เดิม
        showCloseButton: true,
        footer: footerHtml,
        didOpen: () => {
            // ผูก Event Click ให้ปุ่มแก้ไข
            const editBtn = document.querySelector('#swal-edit-btn');
            if(editBtn) {
                editBtn.addEventListener('click', () => {
                    Swal.close(); // ปิด popup นี้ก่อน
                    // เรียกฟังก์ชัน openModal ตาม string ที่ส่งมา
                    // ใช้ new Function เพื่อรันคำสั่ง string (หรือใช้ eval แต่ new Function ปลอดภัยกว่านิดหน่อยใน scope นี้)
                    // หรือเรียกใช้ตรงๆ ถ้า editOnclick เป็น function แต่ที่นี่ส่งมาเป็น string
                    
                    // วิธีบ้านๆ แต่ชัวร์: สร้าง element ชั่วคราวเพื่อ click
                    const temp = document.createElement('div');
                    temp.setAttribute('onclick', editOnclick);
                    temp.click();
                });
            }
        }
    });
}
function triggerExport() {
    // 1. รับค่าจากหน้าจอ (เหมือนเดิม)
    let user = document.getElementById('export_user').value;
    let type = document.getElementById('export_doc_type').value;
    
    // ค่าวันที่จาก Flatpickr
    let startDate = document.getElementById('export_start_date').value;
    let endDate = document.getElementById('export_end_date').value;

    // เช็คค่าว่าง
    if (!startDate || !endDate) {
        Swal.fire({
            icon: 'warning',
            title: 'กรุณาระบุวันที่',
            text: 'ต้องเลือกวันเริ่มต้นและสิ้นสุดก่อนส่งออก'
        });
        return;
    }

    // 🟢 2. [แก้ตรงนี้] เปลี่ยน URL ให้ชี้ไปที่ไฟล์ export_excel.php
    // (ของเดิมมันชี้ไปที่ DocumentDashboard.php?export_excel=1...)
    let url = `export_excel.php?start_date=${startDate}&end_date=${endDate}&export_user=${encodeURIComponent(user)}&export_doc_type=${encodeURIComponent(type)}`;

    // 3. สั่งดาวน์โหลด
    window.location.href = url;
    
    // ปิด Modal
    document.getElementById('modal_export').style.display = 'none';
}
function openHistoryModal(docId) {
    // 1. เปิด Modal
    const modal = document.getElementById('modal_history_view');
    const content = document.getElementById('history_content_list');
    const loading = document.getElementById('history_loading');
    
    modal.style.display = 'block';
    content.innerHTML = ''; // ล้างค่าเก่า
    loading.style.display = 'block'; // โชว์ loading

    // 2. เรียกข้อมูลจาก Server
    const formData = new FormData();
    formData.append('action', 'get_history');
    formData.append('doc_id', docId);

    fetch('DocumentDashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        loading.style.display = 'none';
        content.innerHTML = html;
    })
    .catch(err => {
        loading.style.display = 'none';
        content.innerHTML = '<p style="color:red; text-align:center;">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>';
        console.error(err);
    });
}
// ฟังก์ชันกดดูหมายเหตุ (Popup)
function viewReturnRemark(btn) {
    // ดึงข้อความจาก attribute data-remark
    let remarkText = btn.getAttribute('data-remark');

    Swal.fire({
        title: '<strong style="color:#b91c1c; font-size:1.1rem;"><i class="fas fa-exclamation-circle"></i> สาเหตุการตีกลับ</strong>',
        html: `
            <div style="background:#fff5f5; padding:15px; border-radius:8px; border:1px dashed #fca5a5; color:#7f1d1d; font-size:0.95rem; line-height:1.5; text-align:left;">
                ${remarkText}
            </div>
        `,
        confirmButtonText: 'รับทราบ',
        confirmButtonColor: '#64748b',
        width: '350px',
        padding: '1rem'
    });
}

// ฟังก์ชันตีกลับ/ยกเลิก (เหมือนเดิม แต่ย้ำเพื่อความชัวร์)
function toggleReturnDoc(docId, isReturning) {
    let titleText = isReturning == 1 ? "ยืนยันการตีกลับ?" : "ยกเลิกสถานะตีกลับ?";
    let confirmBtnColor = isReturning == 1 ? "#ef4444" : "#6b7280";

    let swalConfig = {
        title: titleText,
        icon: isReturning == 1 ? "warning" : "question",
        showCancelButton: true,
        confirmButtonColor: confirmBtnColor,
        confirmButtonText: "ยืนยัน",
        cancelButtonText: "ปิด"
    };

    if (isReturning == 1) {
        swalConfig.input = 'textarea';
        swalConfig.inputPlaceholder = 'ระบุสาเหตุ (เช่น เอกสารไม่ชัดเจน)...';
        swalConfig.inputValidator = (value) => {
            if (!value) return 'กรุณาระบุสาเหตุ!';
        };
    }

    Swal.fire(swalConfig).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'return_document');
            formData.append('doc_id', docId);
            formData.append('is_returning', isReturning);
            // ส่งค่าหมายเหตุไปด้วย
            if (isReturning == 1) {
                formData.append('return_remark', result.value);
            }

            fetch('DocumentDashboard.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload(); // รีโหลดหน้าเพื่ออัปเดตปุ่ม
                }
            });
        }
    });
}
function openReturnModal(id) {
    // 1. รับค่า Element แบบไม่ใช้ jQuery ($)
    var idInput = document.getElementById('return_req_id');
    var remarkInput = document.querySelector('textarea[name="return_remark"]');
    var modalEl = document.getElementById('returnModal');

    // 2. ใส่ค่า ID และเคลียร์ข้อความ
    if (idInput) idInput.value = id;
    if (remarkInput) remarkInput.value = '';

    // 3. สั่งเปิด Modal (รองรับทั้ง Bootstrap 5 และ 4)
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        // กรณีใช้ Bootstrap 5
        var myModal = bootstrap.Modal.getInstance(modalEl); // เช็คว่ามี instance อยู่แล้วไหม
        if (!myModal) {
            myModal = new bootstrap.Modal(modalEl);
        }
        myModal.show();
    } else if (typeof $ !== 'undefined' && $.fn.modal) {
        // กรณีใช้ Bootstrap 4 (ต้องมี jQuery)
        $(modalEl).modal('show');
    } else {
        // 🔴 กรณีเลวร้ายสุด: ไม่มีทั้ง Bootstrap JS และ jQuery
        alert('ระบบไม่สามารถเปิดหน้าต่างได้ (ไม่พบ Bootstrap JS หรือ jQuery)');
        // วิธีแก้คือต้องไปเพิ่ม Script Tag ในหน้า PHP
    }
}