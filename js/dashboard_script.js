function filterByStatus(status) {
    console.log("กำลังสั่งกรองสถานะ: " + status);

    // 1. ล็อคเป้าไปที่ ID ของช่อง Input โดยตรง
    let input = document.getElementById('hiddenStatusInput');
    let form = document.getElementById('filterForm');

    // ถ้าหาฟอร์มหรือ Input ไม่เจอ ให้ลองสร้างใหม่ (กันเหนียวสุดๆ)
    if (!form) {
        // ลองหา form แรกในหน้า
        form = document.querySelector('form');
        if (!form) { alert("หาฟอร์มไม่เจอครับ!"); return; }
    }
    
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'filter_status';
        input.id = 'hiddenStatusInput'; // ตั้งชื่อ ID ให้ด้วย
        form.appendChild(input);
    }

    // 2. ใส่ค่าลงไป
    input.value = status;

    // 3. ส่งฟอร์ม
    form.submit();
}

// ✅ 1. ฟังก์ชันแสดงรายละเอียด (ฉบับแก้บั๊กยอดรวมน้ำมัน)
function showDetail(data) {
    const modalBody = document.getElementById('modalBody');
    let dateShow = data.report_date || '-';
    if (dateShow !== '-' && dateShow.includes('-')) {
        let parts = dateShow.split(' ')[0].split('-'); // ตัดเวลาและแยกขีด
        if (parts.length === 3) {
            dateShow = `${parts[2]}/${parts[1]}/${parts[0]}`; // สลับเป็น วัน/เดือน/ปี
        }
    }
    
    // --- 1. ระเบิดข้อมูล Array ---
    const customers = data.work_result ? data.work_result.split(/,\s*/) : [];
    const projects = data.project_name ? data.project_name.split(/,\s*/) : [];
    const jobStatuses = data.job_status ? data.job_status.split(/,\s*/) : []; 
    const nextAppts = data.next_appointment ? data.next_appointment.split(/,\s*/) : []; 
    
    // สรุปกิจกรรม (แยกบรรทัด)
    const summaries = data.activity_detail ? data.activity_detail.split('\n') : [];
    
    // 🟢 บันทึกเพิ่มเติม: พยายามแยกตามงาน
    let notes = [];
    if (data.additional_notes) {
        if (data.additional_notes.includes('\n')) {
            notes = data.additional_notes.split('\n');
        } else {
            notes = data.additional_notes.split(/\d+\.\s+/).filter(n => n.trim() !== "");
        }
    }
    if (notes.length === 0 && data.additional_notes) notes = [data.additional_notes];

    // --- 2. วนลูปสร้างการ์ดงาน (Job Cards) ---
    let jobsHtml = '';
    const totalJobs = customers.length;

    for (let i = 0; i < totalJobs; i++) {
        let summaryText = summaries[i] ? summaries[i].replace(/^[•\-\d].*?:\s*/, '').trim() : '-';
        if (!summaries[i] && i === 0 && summaries.length > 0) summaryText = summaries[0];
        if (summaryText === '' || summaryText === '-') summaryText = 'ไม่ได้ระบุรายละเอียด';

        let currentStatus = jobStatuses[i] ? jobStatuses[i].trim() : (jobStatuses[0] || '-');
        let currentNextAppt = nextAppts[i] ? nextAppts[i].trim() : (nextAppts[0] || '-');
        
        let currentProject = projects[i] ? projects[i].trim() : (projects[0] || '-');
        if(currentProject === '' || currentProject === '-') currentProject = 'ไม่ระบุโครงการ';
        
        let currentNote = '';
        if (notes.length > i) {
            currentNote = notes[i].replace(/^[•\-\d].*?:\s*/, '').trim();
        } else if (notes.length === 1 && i === 0) {
            currentNote = notes[0].trim();
        }

        let badgeStyle = getJsStatusColor(currentStatus);

        jobsHtml += `
            <div class="job-detail-card" style="border: 1px solid #e2e8f0; border-top: 4px solid #2563eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; background: #fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 10px;">
                    <span style="font-weight: 700; color: #2563eb;"><i class="fas fa-briefcase"></i> งานที่ ${i + 1}</span>
                    <span style="background: #dbeafe; color: #1e40af; padding: 2px 10px; border-radius: 20px; font-size: 12px;">${data.customer_type || 'ลูกค้า'}</span>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">ลูกค้า / หน่วยงาน</label>
                    <div style="font-size: 1.1rem; font-weight: 700; color: #1e293b;">${customers[i]}</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">ชื่อโครงการ</label>
                        <div style="font-weight: 500;">${currentProject}</div>
                    </div>
                    <div>
                        <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">สถานะงาน</label>
                        <div>
                            <span class="status-badge" style="font-size: 11px; background:${badgeStyle.bg}; color:${badgeStyle.text}; border: 1px solid ${badgeStyle.border}; padding: 3px 8px; border-radius: 4px;">
                                <i class="fas ${badgeStyle.icon}"></i> ${currentStatus}
                            </span>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <label style="font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">นัดหมายครั้งถัดไป</label>
                    <div style="color: #d97706; font-weight: 600;"><i class="far fa-calendar-alt"></i> ${currentNextAppt}</div>
                </div>

                <div style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-top: 15px; border-left: 3px solid #3b82f6;">
                    <label style="font-size: 12px; color: #3b82f6; font-weight: 600; display: block; margin-bottom: 4px;">สรุปการเข้าพบ</label>
                    <div style="white-space: pre-wrap; line-height: 1.5; color: #334155;">${summaryText}</div>
                </div>

                ${currentNote ? `
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                    <div style="background: #fff7ed; padding: 12px; border-radius: 8px; border: 1px solid #ffedd5;">
                        <label style="font-size: 12px; color: #c2410c; font-weight: 600; display: block; margin-bottom: 4px;">
                            <i class="far fa-sticky-note"></i> บันทึกเพิ่มเติม
                        </label>
                        <div style="font-size: 0.9rem; color: #7c2d12;">${currentNote}</div>
                    </div>
                </div>` : ''}
            </div>
        `;
    }

    // --- 3. จัดการชื่อค่าใช้จ่าย ---
    let otherLabel = (data.other_cost_detail && data.other_cost_detail.toString().trim() !== "") 
                      ? data.other_cost_detail 
                      : 'อื่นๆ';

    // สร้างปุ่มหลักฐาน
    let slipsHtml = createEvidenceBtns(data, otherLabel);

    // 🟢 4. คำนวณรวมค่าน้ำมันใหม่ (แก้บั๊ก "1000,200" โชว์แค่ 1000)
    let fuelSum = 0;
    if (data.fuel_cost) {
        // แยกด้วยคอมม่า แล้วบวกกันทีละตัว
        let costs = String(data.fuel_cost).split(',');
        costs.forEach(c => {
            fuelSum += parseFloat(c) || 0;
        });
    }

    // --- 5. ประกอบ HTML ---
    let html = `
        <div class="info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div class="info-item">
                <span class="info-label" style="color:#64748b; font-size:0.9rem;">ผู้รายงาน</span>
                <div class="info-value highlight" style="font-weight:bold; font-size:1.1rem; color:#2563eb;">${data.reporter_name}</div>
            </div>
            <div class="info-item">
                <span class="info-label" style="color:#64748b; font-size:0.9rem;">วันที่รายงาน</span>
                <div class="info-value" style="font-weight:bold;">
                    <i class="far fa-clock"></i> ${dateShow} 
                </div>
            </div>
        </div>

        ${jobsHtml}

        <div class="expense-section" style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-top:20px;">
            <div style="padding:10px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-weight:700; color:#475569; font-size:0.9rem;">
                <i class="fas fa-wallet"></i> รายละเอียดค่าใช้จ่าย
            </div>
            
            <div class="expense-row" style="display:flex; justify-content:space-between; padding:10px 20px; border-bottom:1px solid #f1f5f9;">
                <span><i class="fas fa-gas-pump" style="color:#f97316; width:20px;"></i> น้ำมัน</span>
                <span>${fuelSum.toLocaleString()}</span>
            </div>
            <div class="expense-row" style="display:flex; justify-content:space-between; padding:10px 20px; border-bottom:1px solid #f1f5f9;">
                <span><i class="fas fa-hotel" style="color:#3b82f6; width:20px;"></i> ที่พัก</span>
                <span>${parseFloat(data.accommodation_cost||0).toLocaleString()}</span>
            </div>
            <div class="expense-row" style="display:flex; justify-content:space-between; padding:10px 20px; border-bottom:1px solid #f1f5f9;">
                <span><i class="fas fa-receipt" style="color:#eab308; width:20px;"></i> ${otherLabel}</span>
                <span>${parseFloat(data.other_cost||0).toLocaleString()}</span>
            </div>
            <div class="expense-row" style="display:flex; justify-content:space-between; padding:10px 20px; background:#eff6ff; color:#1e40af; font-weight:bold;">
                <span>รวมสุทธิ</span>
                <span>${parseFloat(data.total_expense||0).toLocaleString()} บาท</span>
            </div>
        </div>

        <div class="info-item" style="margin-top:20px;">
            <span class="info-label" style="margin-bottom:10px; display:block; font-weight:600; color:#64748b;">หลักฐานการเบิก</span>
            <div style="display:flex; flex-wrap:wrap; gap:5px;">${slipsHtml}</div>
        </div>
    `;

    document.getElementById('modalBody').innerHTML = html;
    
    const modal = document.getElementById('detailModal');
    modal.style.display = 'block';
    setTimeout(() => { modal.classList.add('show'); }, 10);
}

// 🔵 ฟังก์ชันช่วยกำหนดสีสถานะใน JS
function getJsStatusColor(status) {
    if (!status) return { bg: '#f1f5f9', text: '#475569', border: '#cbd5e1', icon: 'fa-tag' };
    status = status.trim();

    // 🔴 1. สีแดง
    if (status.includes('ไม่ได้') || status.includes('ยกเลิก') || status.includes('แพ้')) {
        return { bg: '#fef2f2', text: '#b91c1c', border: '#fca5a5', icon: 'fa-times-circle' };
    }

    // 🟢 2. สีเขียว
    if (status.includes('ได้งาน') || status.includes('สำเร็จ') || status.includes('เรียบร้อย')) {
        return { bg: '#dcfce7', text: '#15803d', border: '#86efac', icon: 'fa-check-circle' };
    }

    // 🔵 3. สีฟ้า
    if (status.includes('เสนอ') || status.includes('เข้าพบ') || status.includes('ประมูล')) {
        return { bg: '#eff6ff', text: '#1d4ed8', border: '#93c5fd', icon: 'fa-briefcase' };
    }

    // 🟡 4. สีเหลือง
    if (status.includes('ติดตาม') || status.includes('รอ') || status.includes('นัดหมาย')) {
        return { bg: '#fff7ed', text: '#c2410c', border: '#fdba74', icon: 'fa-clock' };
    }

    // 🎨 5. เจนสีอัตโนมัติ (สูตร Sync กับ PHP)
    // ใช้ TextEncoder เพื่อแปลงเป็น UTF-8 Bytes เหมือน PHP unpack('C*') เป๊ะ
    const encoder = new TextEncoder();
    const bytes = encoder.encode(status);
    let sum = 0;
    bytes.forEach(b => sum += b);

    // คูณด้วยเลขตัวเดียวกับ PHP (157)
    const hue = (sum * 157) % 360;

    return { 
        bg: `hsl(${hue}, 80%, 96%)`,       // พื้นหลังอ่อน
        text: `hsl(${hue}, 65%, 45%)`,     // ตัวหนังสือเข้ม (ตรงกับ PHP)
        border: `hsl(${hue}, 60%, 80%)`,   // ขอบ
        icon: 'fa-circle'
    };
}
// 🔵 2. ฟังก์ชันช่วยสร้างปุ่มหลักฐาน (แยกไฟล์ด้วยคอมม่า)
function createEvidenceBtns(data, customOtherLabel) {
    let html = '';
    
    // ตั้งชื่อปุ่มสำหรับ "อื่นๆ" (ถ้าไม่มีค่าส่งมา ให้ใช้ "บิลอื่นๆ")
    const otherName = customOtherLabel ? customOtherLabel : 'บิลอื่นๆ';

    // ฟังก์ชันย่อยสร้างปุ่ม
    const generate = (filesStr, cssClass, label, icon) => {
        let btnHtml = '';
        if (filesStr && filesStr.toString().trim() !== '' && filesStr !== '0' && filesStr !== 'null') {
            const files = filesStr.split(',');
            files.forEach((file, index) => {
                const fileName = file.trim();
                if (fileName) {
                    btnHtml += `
                        <a href="uploads/${fileName}" target="_blank" class="evidence-btn ${cssClass}" 
                           style="display:inline-flex; align-items:center; gap:5px; padding:6px 12px; background:#f1f5f9; border-radius:6px; color:#475569; text-decoration:none; font-size:0.85rem; border:1px solid #cbd5e1; transition:all 0.2s;">
                            <i class="fas ${icon}"></i> ${label} ${files.length > 1 ? (index + 1) : ''}
                        </a>`;
                }
            });
        }
        return btnHtml;
    };

    html += generate(data.fuel_receipt, 'ev-fuel', 'บิลน้ำมัน', 'fa-gas-pump');
    html += generate(data.accommodation_receipt, 'ev-hotel', 'บิลที่พัก', 'fa-hotel');
    
    // ใช้ชื่อ otherName ที่รับมา แสดงบนปุ่ม
    html += generate(data.other_receipt, 'ev-other', otherName, 'fa-receipt');

    return html || '<span style="color:#94a3b8; font-style:italic; font-size:0.9rem;">- ไม่มีหลักฐานแนบ -</span>';
}

// ✅ ฟังก์ชันแสดงประวัติลูกค้า (ฉบับตัดชื่อลูกค้าออกจากสรุปการเข้าพบ)
function showCustomerHistory(customerName) {
    const modal = document.getElementById('historyModal');
    const modalTitle = document.getElementById('histModalTitle');
    const modalBody = document.getElementById('histModalBody');

    if (!modal || !modalBody) return;

    // Loading
    modalTitle.innerHTML = `<i class="fas fa-history text-primary"></i> ประวัติ: ${customerName}`;
    modalBody.innerHTML = `<div style="text-align:center; padding:50px;"><i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i><div style="margin-top:15px; color:#64748b;">กำลังโหลด...</div></div>`;
    modal.style.display = 'block';
    setTimeout(() => modal.classList.add('show'), 10);

    // Prepare Params
    let startDate = document.querySelector('input[name="start_date"]')?.value || "";
    let endDate = document.querySelector('input[name="end_date"]')?.value || "";
    const cleanName = customerName.trim();
    var url = `?ajax_action=get_customer_history&customer_name=${encodeURIComponent(cleanName)}`;
    if (startDate) url += `&start_date=${startDate}`;
    if (endDate) url += `&end_date=${endDate}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) { renderNoData(modalBody); return; }

            let listHtml = '';
            
            data.forEach((item, index) => {
                // 1. ระเบิดข้อมูล Array
                const rowCustomers = item.work_result ? item.work_result.split(/,\s*/) : [];
                const rowStatuses = item.job_status ? item.job_status.split(/,\s*/) : [];
                const rowProjects = item.project_name ? item.project_name.split(/,\s*/) : [];
                const rowSummaries = item.activity_detail ? item.activity_detail.split('\n') : [];
                
                // บันทึกเพิ่มเติม
                let rowNotes = [];
                if (item.additional_notes) {
                     if (item.additional_notes.includes('\n')) rowNotes = item.additional_notes.split('\n');
                     else rowNotes = item.additional_notes.split(/\d+\.\s+/).filter(n => n.trim() !== "");
                }

                // 2. หา Index ลูกค้า
                let targetIndex = -1;
                let isExactMatch = false;
                const searchStr = cleanName.toLowerCase();

                if (rowCustomers.length > 0) {
                    for (let i = 0; i < rowCustomers.length; i++) {
                        const dbName = rowCustomers[i].trim().toLowerCase();
                        if (dbName === searchStr) { targetIndex = i; isExactMatch = true; break; }
                        if (dbName.includes(searchStr) || searchStr.includes(dbName)) { targetIndex = i; }
                    }
                }
                if (targetIndex === -1) targetIndex = 0;

                // 3. ดึงข้อมูลย่อย
                let myStatus = rowStatuses[targetIndex] ? rowStatuses[targetIndex].trim() : (rowStatuses[0] || '-');
                let myProject = rowProjects[targetIndex] ? rowProjects[targetIndex].trim() : (rowProjects[0] || '-');
                if (!myProject || myProject === '' || myProject === '-') myProject = item.project_name || 'ไม่ระบุ';
                let displayCusName = rowCustomers[targetIndex] || cleanName;

                // 🟢 สรุปการเข้าพบ (แก้ใหม่: ตัดชื่อลูกค้าออก)
                let mySummary = '';
                if (rowSummaries.length > targetIndex) {
                    mySummary = rowSummaries[targetIndex];
                } else if (rowSummaries.length > 0) {
                    mySummary = rowSummaries[0]; 
                }
                
                // ✨ สูตรตัดคำนำหน้า: ตัดทุกอย่างตั้งแต่ต้น จนถึงเครื่องหมาย : ตัวแรก
                // เช่น "• โรงเรียนเลยอนุกูล: ไฟฟ้า" -> เหลือแค่ "ไฟฟ้า"
                if (mySummary) {
                    // 1. ลองตัดแพทเทิร์น "• ชื่อลูกค้า: " ออกก่อน
                    mySummary = mySummary.replace(/^[•\-\d\s]*.*?\s*:\s*/, '').trim();
                    
                    // 2. ถ้ายังเหลือพวก bullet point หรือขีดนำหน้า ให้ลบออกอีกรอบ (กันเหนียว)
                    mySummary = mySummary.replace(/^[•\-\d]+\.?\s*/, '').trim();
                }

                // ถ้าเป็นขีดเดียว "-" ให้ถือว่าว่าง
                if (mySummary === '-') mySummary = '';

                // 🟢 บันทึกเพิ่มเติม
                let myNote = '';
                if (rowNotes.length > targetIndex) {
                    myNote = rowNotes[targetIndex];
                } else if (rowNotes.length > 0) {
                    myNote = rowNotes[0];
                }
                // ตัดพวก bullet นำหน้าของ Note ด้วย
                if (myNote) myNote = myNote.replace(/^[•\-\d]+\.?\s*.*?\s*:\s*/, '').replace(/^[•\-\d]+\.?\s*/, '').trim();

                let stStyle = getJsStatusColor(myStatus);
                let expenseHtml = parseFloat(item.total_expense) > 0 ? `<span class="h-expense" style="color:#d97706; font-weight:bold;"><i class="fas fa-wallet"></i> ฿${parseFloat(item.total_expense).toLocaleString()}</span>` : '';

                listHtml += `
                    <div class="history-card" style="animation-delay: ${index * 0.1}s;">
                        <div class="h-card-header">
                            <div class="h-date"><i class="far fa-calendar-alt text-primary"></i> ${item.report_date}</div>
                            <div class="h-reporter"><i class="fas fa-user-check"></i> ${item.reporter_name}</div>
                        </div>
                        
                        <div class="h-card-body">
                            <div style="font-size:0.85rem; color:#64748b; margin-bottom:5px;">
                                ลูกค้า: <b style="color:#334155;">${displayCusName}</b>
                                ${!isExactMatch && targetIndex !== -1 ? '<i class="fas fa-info-circle text-warning"></i>' : ''}
                            </div>

                            <div class="h-project"><i class="fas fa-folder-open"></i> ${myProject}</div>
                            
                            ${mySummary !== '' ? `
                            <div style="background:#f1f5f9; padding:8px 10px; border-radius:6px; margin-top:8px; border-left:3px solid #3b82f6;">
                                <div style="font-size:0.75rem; color:#3b82f6; font-weight:600; margin-bottom:2px;">
                                    <i class="fas fa-comment-dots"></i> สรุปการเข้าพบ
                                </div>
                                <div style="font-size:0.85rem; color:#334155; line-height:1.4;">${mySummary}</div>
                            </div>` : ''}

                            ${myNote !== '' ? `
                            <div style="background:#fff7ed; padding:8px 10px; border-radius:6px; margin-top:8px; border:1px solid #ffedd5;">
                                <div style="font-size:0.75rem; color:#c2410c; font-weight:600; margin-bottom:2px;">
                                    <i class="far fa-sticky-note"></i> บันทึกเพิ่มเติม
                                </div>
                                <div style="font-size:0.85rem; color:#7c2d12; line-height:1.4;">${myNote}</div>
                            </div>` : ''}

                            <div style="display:flex; align-items:center; gap:10px; margin-top:10px;">
                                <span class="status-badge" style="background:${stStyle.bg}; color:${stStyle.text}; border:1px solid ${stStyle.border}; font-size:0.85rem; padding:4px 10px;">
                                    <i class="fas ${stStyle.icon}"></i> ${myStatus}
                                </span>
                            </div>
                        </div>
                        ${expenseHtml ? `<div class="h-card-footer">${expenseHtml}</div>` : ''}
                    </div>`;
            });
            modalBody.innerHTML = listHtml;
        })
        .catch(err => {
            console.error(err);
            modalBody.innerHTML = '<div style="color:red; text-align:center; padding:20px;">เกิดข้อผิดพลาดในการเชื่อมต่อ</div>';
        });
}

// ฟังก์ชันช่วยสำหรับตอนไม่พบข้อมูล
function renderNoData(container) {
    container.innerHTML = `
        <div style="text-align:center; padding:50px; color:#94a3b8;">
            <i class="far fa-folder-open fa-3x mb-3" style="opacity:0.5;"></i><br>
            ไม่พบประวัติการเข้าพบลูกค้าท่านนี้
        </div>`;
}

function closeModal(id) {
    // 1. ถ้าไม่ส่ง id มา ให้ลองหา 'detailModal' เป็นค่าเริ่มต้น
    const targetId = id || 'detailModal'; 
    const modal = document.getElementById(targetId);

    // 2. เช็คก่อนว่าเจอ Modal จริงไหม (ป้องกัน Error reading classList)
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => { 
            modal.style.display = 'none'; 
        }, 300);
    } else {
        // 3. ท่าไม้ตาย: ถ้าหา ID ไม่เจอ ให้สั่งปิดทุก Modal ที่มี Class .modal ในหน้าจอนั้นเลย
        console.warn("ไม่พบ Modal ID: " + targetId + " กำลังสั่งปิด Modal ทั้งหมดที่มีในหน้า");
        document.querySelectorAll('.modal').forEach(m => {
            m.classList.remove('show');
            setTimeout(() => { m.style.display = 'none'; }, 300);
        });
    }
}

function openExpenseModal(data) {
    // Debug: ดูค่าที่ส่งมาใน Console
    console.log("Expense Data:", data);

    try {
        // 1. ตรวจสอบว่ามี Modal อยู่จริงไหม
        const modal = document.getElementById('expenseModal');
        if (!modal) {
            console.error("ไม่พบ Modal ID: expenseModal");
            return;
        }

        // 2. ตั้งค่า ID รายงาน
        document.getElementById('ex_report_id').value = data.id || '';

        // --- 3. จัดการค่าน้ำมัน (ระเบิดคอมม่า & กรองค่า 0) ---
        const container = document.getElementById('fuel_edit_container');
        container.innerHTML = ''; // ล้างช่องเก่าทิ้ง

        // แปลงข้อมูลเป็น String กันเหนียว
        let fuelStr = data.fuel_cost ? String(data.fuel_cost) : '';
        // แยกด้วยคอมม่า (ถ้ามี) ถ้าไม่มีก็เป็น Array ตัวเดียว
        let fuelArray = fuelStr.includes(',') ? fuelStr.split(',') : [fuelStr];
        
        // แยกชื่อไฟล์สลิปน้ำมันด้วย
        let receiptStr = data.fuel_receipt ? String(data.fuel_receipt) : '';
        let fuelReceipts = receiptStr.includes(',') ? receiptStr.split(',') : (receiptStr ? [receiptStr] : []);

        // วนลูปสร้างช่องกรอกตามจำนวนรายการที่มี
        fuelArray.forEach((val, idx) => {
            // 🟢 สูตรเด็ด: ถ้าค่า > 0 ให้ใส่ค่านั้น ถ้าเป็น 0 หรือว่าง ให้ใส่ '' (โล่งๆ)
            let displayVal = (parseFloat(val) > 0) ? val : ''; 

            // เช็คว่ามีไฟล์เดิมไหม (ต้องไม่ว่าง, ไม่ใช่ '0', ไม่ใช่ 'null')
            let hasFile = fuelReceipts[idx] && fuelReceipts[idx].trim() !== '' && fuelReceipts[idx] !== '0' && fuelReceipts[idx] !== 'null';

            const div = document.createElement('div');
            div.className = 'fuel-row';
            div.style.cssText = "display:flex; gap:10px; margin-bottom:12px; align-items: center;";
            
            div.innerHTML = `
                <div style="position: relative; flex: 1;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem;">฿</span>
                    <input type="number" step="0.01" name="fuel_cost[]" value="${displayVal}" 
                        class="form-control fuel-calc" placeholder="0.00" 
                        oninput="calcTotalEdit()" 
                        style="padding-left: 25px; border-radius: 8px;">
                </div>
                <div style="flex: 1;">
                    <label class="upload-btn-mini" style="width: 100%; border-radius: 8px; justify-content: center; background: #f1f5f9; border: 1px dashed #cbd5e1;">
                        <i class="fas fa-camera"></i> สลิป ${idx + 1}
                        <input type="file" name="fuel_file[]" accept="image/*" hidden onchange="previewFile(this, 'prev_fuel_${idx}')">
                    </label>
                    <div id="prev_fuel_${idx}" class="file-status" style="font-size: 10px; margin-top:4px; text-align: center;">
                        ${hasFile ? '<i class="fas fa-check-circle" style="color:#3b82f6;"></i> มีสลิปเดิม' : ''}
                    </div>
                </div>
                ${idx > 0 ? `<button type="button" onclick="this.parentElement.remove(); calcTotalEdit();" style="color:#ef4444; background:none; border:none; cursor:pointer; padding:5px;"><i class="fas fa-trash-alt"></i></button>` : '<div style="width:26px;"></div>'}
            `;
            container.appendChild(div);
        });

        // --- 4. จัดการค่าที่พัก (ถ้า 0 ให้ว่าง) ---
        let hotelVal = (parseFloat(data.accommodation_cost) > 0) ? data.accommodation_cost : '';
        document.getElementById('ex_hotel').value = hotelVal;

        // เช็คไฟล์ที่พักเดิม
        const hotelStatusDiv = document.getElementById('prev_hotel');
        if (data.accommodation_receipt && data.accommodation_receipt !== '0' && data.accommodation_receipt.trim() !== '' && data.accommodation_receipt !== 'null') {
            hotelStatusDiv.innerHTML = '<i class="fas fa-check-circle" style="color:#3b82f6;"></i> มีสลิปเดิม';
        } else {
            hotelStatusDiv.innerHTML = '';
        }

        // --- 5. จัดการค่าอื่นๆ (ถ้า 0 ให้ว่าง) ---
        let otherVal = (parseFloat(data.other_cost) > 0) ? data.other_cost : '';
        document.getElementById('ex_other').value = otherVal;
        
        const otherDetailInput = document.getElementById('ex_other_detail');
        if (otherDetailInput) {
            let detail = data.other_cost_detail;
            // เช็คว่าถ้าเป็น null, undefined, "0", หรือ "null" ให้เคลียร์ทิ้ง
            if (!detail || detail === '0' || detail === 0 || detail === 'null') {
                otherDetailInput.value = '';
            } else {
                otherDetailInput.value = detail;
            }
        }

        // เช็คไฟล์อื่นๆเดิม
        const otherStatusDiv = document.getElementById('prev_other');
        if (data.other_receipt && data.other_receipt !== '0' && data.other_receipt.trim() !== '' && data.other_receipt !== 'null') {
            otherStatusDiv.innerHTML = '<i class="fas fa-check-circle" style="color:#3b82f6;"></i> มีสลิปเดิม';
        } else {
            otherStatusDiv.innerHTML = '';
        }

        // 6. คำนวณยอดรวมทั้งหมดทันทีที่เปิด
        calcTotalEdit(); 
        
        // 7. สั่งแสดง Modal
        modal.style.display = 'block';
        setTimeout(() => { modal.classList.add('show'); }, 10);

    } catch (err) {
        console.error("เกิดข้อผิดพลาดใน openExpenseModal:", err);
    }
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
    const rowCount = container.querySelectorAll('.fuel-row').length;
    const div = document.createElement('div');
    div.className = 'fuel-row';
    div.style.cssText = "display:flex; gap:10px; margin-bottom:12px; align-items: center;";

    // 🟢 สำคัญ: ต้องมี class="fuel-calc" และ oninput="calcTotalEdit()"
    div.innerHTML = `
        <div style="position: relative; flex: 1;">
            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem;">฿</span>
            <input type="number" step="0.01" name="fuel_cost[]" 
                class="form-control fuel-calc" placeholder="0.00" 
                oninput="calcTotalEdit()" 
                style="padding-left: 25px; border-radius: 8px;">
        </div>
        <div style="flex: 1;">
            <label class="upload-btn-mini" style="width: 100%; border-radius: 8px; justify-content: center; background: #f1f5f9; border: 1px dashed #cbd5e1;">
                <i class="fas fa-camera"></i> สลิปใหม่
                <input type="file" name="fuel_file[]" accept="image/*" hidden onchange="previewFile(this, 'prev_fuel_new_${rowCount}')">
            </label>
            <div id="prev_fuel_new_${rowCount}" class="file-status"></div>
        </div>
        <button type="button" onclick="this.parentElement.remove(); calcTotalEdit();" 
            style="color:#ef4444; background:none; border:none; cursor:pointer; padding:5px;">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    container.appendChild(div);
}

function calcTotalEdit() {
    let totalFuel = 0;
    
    // 🟢 1. วนลูปบวกค่าน้ำมันทุกช่องที่มีในหน้าจอตอนนี้
    const fuelInputs = document.querySelectorAll('.fuel-calc');
    fuelInputs.forEach(input => {
        let val = parseFloat(input.value) || 0;
        totalFuel += val;
    });

    // 🟢 2. ดึงค่าที่พักและค่าอื่นๆ
    const hotelCost = parseFloat(document.getElementById('ex_hotel').value) || 0;
    const otherCost = parseFloat(document.getElementById('ex_other').value) || 0;

    // 🟢 3. คำนวณยอดรวมสุทธิ
    const grandTotal = totalFuel + hotelCost + otherCost;

    // 🟢 4. แสดงผลที่ตัวเลขยอดรวม (ex_total_display)
    const display = document.getElementById('ex_total_display');
    if (display) {
        display.innerText = grandTotal.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ฿';
    }
}

function previewFile(input, displayId) {
    if (input.files && input.files[0]) {
        document.getElementById(displayId).innerHTML = '<i class="fas fa-file-upload"></i> ' + input.files[0].name;
        document.getElementById(displayId).style.color = '#10b981';
    }
}
function saveEdit() {
    // 1. ดึง ID และค่าใช้จ่าย
    const reportId = document.getElementById('ex_report_id').value;
    const accomCost = document.getElementById('ex_hotel').value;
    const otherCost = document.getElementById('ex_other').value;
    const otherDetail = document.getElementById('ex_other_detail')?.value || '';

    // 2. รวบรวมค่าน้ำมัน
    let fuelCosts = [];
    document.querySelectorAll('.fuel-calc').forEach(input => {
        fuelCosts.push(input.value);
    });

    // 3. เตรียมข้อมูลส่ง
    let formData = new FormData();
    formData.append('action', 'update_expense');
    formData.append('report_id', reportId);
    formData.append('accommodation_cost', accomCost);
    formData.append('other_cost', otherCost);
    formData.append('other_detail', otherDetail);

    fuelCosts.forEach(cost => formData.append('fuel_cost[]', cost));

    // ไฟล์สลิปต่างๆ
    document.querySelectorAll('input[name="fuel_file[]"]').forEach(fileInput => {
        if(fileInput.files[0]) formData.append('fuel_file[]', fileInput.files[0]);
    });
    const hotelFile = document.querySelector('input[name="hotel_file"]');
    if(hotelFile?.files[0]) formData.append('hotel_file', hotelFile.files[0]);
    const otherFile = document.querySelector('input[name="other_file"]');
    if(otherFile?.files[0]) formData.append('other_file', otherFile.files[0]);

    // 4. ยิง Fetch ไปที่ Dashboard.php
    fetch('Dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json()) // 👈 รับค่า JSON ที่โชว์ในภาพมาประมวลผล
    .then(data => {
        if (data.status === 'success') {
            // ✅ สั่งปิด Modal ทันที ไม่ให้ซ้อนกับแจ้งเตือน
            closeModal('expenseModal');

            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload(); // รีเฟรชหน้าเพื่อดูยอดใหม่
            });
        } else {
            Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Fetch Error:', err);
        Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    });
}
document.addEventListener('DOMContentLoaded', function() {
        flatpickr(".datepicker", {
            locale: "th",              // ภาษาไทย
            
            // 🟢 1. ตั้งค่าการส่งข้อมูลและการแสดงผล
            dateFormat: "Y-m-d",       // ค่าจริงที่ส่งไป Database (เช่น 2026-02-16)
            altInput: true,            // เปิดโหมด "แสดงผลต่างจากค่าจริง"
            altFormat: "d/m/Y",        // รูปแบบที่โชว์ให้ตาเห็น (DD/MM/YYYY)
            
            // 🟢 2. อนุญาตให้พิมพ์เองได้
            allowInput: true,          // 👈 บรรทัดนี้สำคัญ! ทำให้คลิกแล้วพิมพ์เลขได้เลย
            
            // 🟢 3. บังคับใช้ธีมนี้ทุกอุปกรณ์
            disableMobile: "true"      // ถ้าไม่ใส่ ในมือถือจะพิมพ์ไม่ได้ (มันจะเด้งลูกกลิ้งวันที่ของมือถือขึ้นมาแทน)
        });
    });
    