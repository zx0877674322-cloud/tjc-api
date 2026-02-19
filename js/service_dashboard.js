flatpickr(".date-picker-alt", {
            altInput: true,
            altFormat: "d/m/Y",
            dateFormat: "Y-m-d",
            locale: "th",
            allowInput: true
        });

        // 3. ฟังก์ชันกรองสถานะ (เมื่อคลิกการ์ด)
        function filterStatus(value, type) {
            let inputId = '';
            if (type === 'status') inputId = 'status_input';
            else if (type === 'urgency') inputId = 'urgency_input';
            else if (type === 'return_status') inputId = 'return_input';
            else if (type === 'job_type') inputId = 'job_type_input';
            else if (type === 'cost_filter') inputId = 'cost_filter_input'; // [เพิ่มบรรทัดนี้]

            if (inputId) {
                const inputEl = document.getElementById(inputId);
                // ระบบ Toggle: ถ้าค่าเดิมตรงกับที่กดมา ให้ล้างค่า (ยกเลิกการกรอง)
                if (inputEl.value === value) {
                    inputEl.value = '';
                } else {
                    inputEl.value = value;
                }
            }

            updateData(); // สั่งโหลดข้อมูลใหม่ด้วย AJAX ตามโค้ดเดิมของคุณ
        }

        // 4. ยืนยันปิดงาน
        function confirmFinish(reqId) {
            Swal.fire({
                title: 'ยืนยันปิดงาน?',
                text: "สถานะจะเปลี่ยนเป็น 'เสร็จสิ้น'",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('service_dashboard.php', {
                        action: 'finish_job',
                        req_id: reqId
                    }, function (res) {
                        if (res.status === 'success') Swal.fire('สำเร็จ!', 'ปิดงานเรียบร้อยแล้ว', 'success').then(() => location.reload());
                        else Swal.fire('Error!', res.message, 'error');
                    }, 'json');
                }
            })
        }

        // 5. ลบรายการ
        function deleteItem(reqId) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลจะหายไปถาวร!",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'ลบข้อมูล'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('service_dashboard.php', {
                        action: 'delete_item',
                        req_id: reqId
                    }, function (res) {
                        if (res.status === 'success') location.reload();
                    }, 'json');
                }
            })
        }

        // 6. ดูรายการสินค้า
        function viewItems(dataInput) {
            let items = [];
            try {
                items = (typeof dataInput === 'string') ? JSON.parse(dataInput) : dataInput;
                if (!Array.isArray(items)) items = [];
            } catch (e) { items = []; }

            let listHtml = '';
            let hasContent = false;
            if (items.length > 0) {
                listHtml = '<div style="text-align: left; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;"><ul style="margin: 0; padding-left: 20px; color: #1e293b; font-size: 1rem; line-height: 1.8;">';
                items.forEach((box) => {
                    let products = Array.isArray(box.product) ? box.product : [box.product];
                    products.forEach(pName => {
                        if (pName && pName.trim() !== "") {
                            listHtml += `<li style="margin-bottom: 5px; font-weight: 500;">${pName}</li>`;
                            hasContent = true;
                        }
                    });
                });
                listHtml += '</ul></div>';
            }
            if (!hasContent) listHtml = '<div style="text-align:center; padding: 20px; color: #94a3b8;">- ไม่ระบุรายการสินค้า -</div>';

            Swal.fire({
                title: '<div style="color:#0369a1; font-size:1.25rem; font-weight:700;"><i class="fas fa-boxes"></i> รายการสินค้าทั้งหมด</div>',
                html: listHtml, width: 450, confirmButtonText: 'ปิด', confirmButtonColor: '#64748b'
            });
        }

        // 7. ดูรายละเอียดปัญหา (แบบแยกกล่องตามรายการ)
        function viewDetails(itemsData) {
    let items = [];

    // 1. ตรวจสอบและแปลงข้อมูล
    try {
        if (Array.isArray(itemsData)) {
            items = itemsData;
        } else if (typeof itemsData === 'string') {
            // เช็คว่าเป็น JSON String หรือไม่
            if (itemsData.trim().startsWith('[') || itemsData.trim().startsWith('{')) {
                items = JSON.parse(itemsData);
            } else if (itemsData.trim() !== "") {
                // กรณีเป็น Text ธรรมดา (ข้อมูลเก่า) ให้แปลงเป็น Item จำลอง
                items = [{
                    product: ['รายการทั่วไป'],
                    issue: itemsData,
                    initial_advice: '',
                    assessment: ''
                }];
            }
        }
    } catch (e) {
        console.error("JSON Parse Error:", e);
        items = [];
    }

    // 2. สร้าง HTML
    let htmlContent = '<div style="text-align: left; font-family: Prompt, sans-serif; max-height:70vh; overflow-y:auto; padding-right:5px;">';

    if (!items || items.length === 0) {
        htmlContent += '<div style="text-align:center; color:#94a3b8; padding:30px; background:#f8fafc; border-radius:10px; border:1px dashed #cbd5e1;"><i class="fas fa-box-open" style="font-size:2rem; display:block; margin-bottom:10px; opacity:0.5;"></i>- ไม่มีข้อมูลรายการ -</div>';
    } else {
        items.forEach((item, index) => {
            // ดึงค่า (รองรับทั้ง format เก่าและใหม่)
            let products = '-';
            if (Array.isArray(item.product)) {
                products = item.product.join(", ");
            } else if (item.product) {
                products = item.product;
            }

            let issue = item.issue || item.issue_description || '-'; // รองรับ key เก่า
            let advice = item.initial_advice || '';
            let assessment = item.assessment || '';
            
            // Job Type
            let jobType = item.job_type || '';
            let jobTypeBadge = '';
            if (jobType && jobType !== 'other') {
                jobTypeBadge = `<span style="font-size:0.75rem; color:#3b82f6; background:#eff6ff; padding:2px 8px; border-radius:20px; border:1px solid #dbeafe; margin-left:auto;">${jobType}</span>`;
            }

            htmlContent += `
            <div style="background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #6366f1; border-radius: 10px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                
                <div style="display:flex; align-items:center; margin-bottom:10px; border-bottom:1px dashed #e2e8f0; padding-bottom:8px;">
                    <span style="background:#f1f5f9; color:#64748b; padding:2px 8px; border-radius:6px; font-size:0.8rem; margin-right:8px; font-weight:700;">#${index + 1}</span>
                    <span style="font-weight:700; color:#334155; font-size:0.95rem;">${products}</span>
                    ${jobTypeBadge}
                </div>

                <div style="margin-bottom: 10px;">
                    <div style="font-size:0.8rem; color:#ef4444; font-weight:600; margin-bottom:2px;"><i class="fas fa-tools"></i> อาการที่พบ:</div>
                    <div style="font-size:0.9rem; color:#1e293b; background:#fef2f2; padding:8px 12px; border-radius:6px; border:1px solid #fee2e2;">${issue}</div>
                </div>

                ${advice ? `
                <div style="margin-top: 8px; display:flex; gap:10px;">
                    <div style="flex:1; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px;">
                        <div style="font-size:0.8rem; color:#166534; font-weight:700; margin-bottom:2px;"><i class="fas fa-microscope"></i> คำแนะนำเบื้องต้น</div>
                        <div style="font-size:0.85rem; color:#14532d;">${advice}</div>
                    </div>
                </div>` : ''}

                ${assessment ? `
                <div style="margin-top: 8px;">
                    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px;">
                        <div style="font-size:0.8rem; color:#92400e; font-weight:700; margin-bottom:2px;"><i class="fas fa-clipboard-check"></i> การประเมินงาน</div>
                        <div style="font-size:0.85rem; color:#78350f;">${assessment}</div>
                    </div>
                </div>` : ''}

            </div>`;
        });
    }

    htmlContent += '</div>';

    Swal.fire({
        title: '<div style="display:flex; align-items:center; gap:10px; font-family:Prompt;"><div style="width:40px; height:40px; background:#e0e7ff; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#4338ca;"><i class="fas fa-file-alt"></i></div> รายละเอียดงาน</div>',
        html: htmlContent,
        width: '650px',
        showCloseButton: true,
        showConfirmButton: false,
        background: '#f8fafc',
        customClass: { popup: 'rounded-xl shadow-xl' }
    });
}
        // 8. นับถอยหลัง SLA
        // ฟังก์ชันนับถอยหลัง (Format ตามรูปภาพ: X วัน HH:MM:SS)
        function updateSLACountdown() {
            $('.sla-countdown-wrapper').each(function () {
                let deadlineStr = $(this).data('deadline');
                if (!deadlineStr) return;

                let deadline = new Date(deadlineStr).getTime();
                let now = new Date().getTime();
                let diff = deadline - now; // เวลาที่เหลือ

                // คำนวณเวลา
                let days = Math.floor(Math.abs(diff) / (1000 * 60 * 60 * 24));
                let hours = Math.floor((Math.abs(diff) % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                let minutes = Math.floor((Math.abs(diff) % (1000 * 60 * 60)) / (1000 * 60));
                let seconds = Math.floor((Math.abs(diff) % (1000 * 60)) / 1000);

                // เติมเลข 0 ข้างหน้า
                hours = hours < 10 ? "0" + hours : hours;
                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                let html = "";
                let color = "#2563eb"; // สีฟ้า (ค่าเริ่มต้น)

                if (diff < 0) {
                    // --- กรณีล่าช้า (สีแดง) ---
                    color = "#dc2626";
                    html += `<span style="font-size:0.8rem;">ล่าช้า </span>`; // เพิ่มคำว่าล่าช้าเล็กๆ
                    if (days > 0) html += `${days} วัน `;
                    html += `${hours}:${minutes}:${seconds}`;
                } else {
                    // --- กรณีปกติ (สีฟ้า ตามรูป) ---
                    // ถ้าเหลือน้อยกว่า 24 ชม. ให้เป็นสีส้มเตือน (ถ้าไม่ชอบให้ลบบรรทัดนี้ทิ้ง จะกลับเป็นฟ้า)
                    if (diff <= 24 * 60 * 60 * 1000) color = "#d97706";

                    if (days > 0) html += `${days} วัน `;
                    html += `${hours}:${minutes}:${seconds}`;
                }

                // อัปเดต HTML และสี
                $(this).html(html).css('color', color);

                // อัปเดตสีวันที่ด้านบนด้วย (ถ้าต้องการให้เปลี่ยนตามกัน)
                $(this).closest('div').prev().prev().find('span:last-child').css('color', color);
            });
        }
        $(document).ready(function () {
            updateSLACountdown();
            setInterval(updateSLACountdown, 1000);
        });

        // 9. ฟังก์ชันรับของกลับ / นำของออก (Premium Design + Animation)
function receiveItem(reqId) {
    Swal.fire({ title: 'กำลังโหลดข้อมูล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.ajax({
        url: 'service_dashboard.php',
        type: 'POST',
        data: { action: 'get_latest_item_data', req_id: reqId },
        dataType: 'json',
        success: function(rawData) {
            Swal.close();
            if (!rawData) rawData = {};

            const superClean = (str) => {
                if (!str) return "";
                let s = str.toString().toLowerCase();
                if (s.includes(':')) s = s.split(':')[0]; 
                return s.replace(/[^a-z0-9ก-๙]/g, ''); 
            };

            const extractFromProjectField = (source) => {
                let extracted = new Set();
                if (!source) return [];
                try {
                    let parsed = (typeof source === 'string') ? JSON.parse(source) : source;
                    if (Array.isArray(parsed)) {
                        parsed.forEach(obj => {
                            let pName = obj.product || obj.name || "";
                            if (Array.isArray(pName)) {
                                pName.forEach(v => { if(v) extracted.add(v.trim()); });
                            } else if (pName) { extracted.add(pName.trim()); }
                        });
                    }
                } catch (e) {
                    source.toString().split(/[\r\n,]+/).forEach(v => {
                        let cleanV = v.replace(/^\d+\.\s*/, '').replace(/^\[+|\]+$/g, '').trim();
                        if (cleanV && !cleanV.includes('{')) extracted.add(cleanV);
                    });
                }
                return Array.from(extracted);
            };

            let finishedListClean = (rawData.finished_items || []).map(superClean);
            let itemsStatus = rawData.items_status || {};
            let itemsFromDB = extractFromProjectField(rawData.project_item_name_raw);
            let itemsMoved = rawData.accumulated_moved || [];
            let allSource = Array.from(new Set([...itemsFromDB, ...itemsMoved]));
            let moveHistory = rawData.items_moved || [];

            let displayItems = allSource.map(originalName => {
                let cleanName = superClean(originalName);
                let isFinished = finishedListClean.includes(cleanName);
                
                let currentStat = '';
                for (let key in itemsStatus) {
                    if (superClean(key) === cleanName) {
                        currentStat = itemsStatus[key];
                        break;
                    }
                }
                
                let isReceived = currentStat.includes('at_office') || currentStat === 'back_from_shop';
                let isAtExternal = currentStat === 'at_external'; // 🔥 สถานะอยู่ร้านนอก (สีส้ม)
                
                let history = moveHistory.find(h => superClean(h.name) === cleanName) || null;
                
                return { name: originalName, isFinished, isReceived, isAtExternal, history };
            });

            let htmlForm = `<div class="item-scroll-area" id="modal_scroll_container">
            <style>
                .item-scroll-area { display: flex; flex-direction: column; gap: 10px; text-align: left; padding: 10px; max-height: 60vh; overflow-y: auto; background: #f8fafc; border-radius: 12px; }
                .item-card-3d { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; transition: 0.3s; width: 100%; box-sizing: border-box; }
                .item-card-3d.finished { background: #ecfdf5 !important; border: 1px solid #10b981 !important; }
                .item-card-3d.received { background: #eff6ff !important; border: 1px solid #3b82f6 !important; }
                .item-card-3d.at-external { background: #fff7ed !important; border: 1px solid #f97316 !important; }
                .item-card-3d.active { border-color: #f97316; box-shadow: 0 10px 15px -3px rgba(249, 115, 22, 0.15); }
                .card-header-clickable { padding: 12px 15px; display: flex; align-items: center; cursor: pointer; border-radius: 12px; }
                .chk-3d-box { width: 22px; height: 22px; border: 2px solid #cbd5e1; border-radius: 6px; margin-right: 12px; display: flex; align-items: center; justify-content: center; background: #fff; flex-shrink: 0; color: transparent; }
                input[type="checkbox"]:checked:not(:disabled) + .chk-3d-box { background: #f97316; border-color: #f97316; color: #fff; }
                input[type="checkbox"]:disabled + .chk-3d-box { background: #94a3b8; border-color: #94a3b8; color: #fff; cursor: not-allowed; }
                .item-card-3d.finished .chk-3d-box { background: #10b981 !important; border-color: #10b981 !important; }
                .item-card-3d.received .chk-3d-box { background: #3b82f6 !important; border-color: #3b82f6 !important; }
                .item-card-3d.at-external .chk-3d-box { background: #f97316 !important; border-color: #f97316 !important; }
                .card-content-reveal { display: none; padding: 15px; background: #fff; border-top: 1px solid #f1f5f9; }
                .dest-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
                .dest-ui { border: 2px solid #e2e8f0; border-radius: 8px; padding: 10px; text-align: center; color: #64748b; }
                input:checked + .dest-ui { border-color: #f97316; background: #fff7ed; color: #c2410c; font-weight: 700; }
                .form-input-3d { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; box-sizing: border-box; background: #fff; }
                .form-input-3d:read-only { background: #f8fafc; color: #64748b; border-color: #e2e8f0; cursor: default; }
                .shop-fields-grid { display: none; background: #fffbeb; padding: 12px; border-radius: 10px; border: 1px dashed #fcd34d; margin-bottom: 15px; }
            </style>`;

            if (displayItems.length === 0) {
                htmlForm += `<div style="text-align:center; padding:40px; color:#94a3b8;">ไม่พบข้อมูลรายการสินค้า</div>`;
            } else {
                displayItems.forEach((it, idx) => {
                    // 🔥 ล็อครายการถ้า: เสร็จแล้ว (เขียว) หรือ รับกลับแล้ว (ฟ้า) หรือ อยู่ร้านนอก (ส้ม)
                    let isLocked = it.isFinished || it.isReceived || it.isAtExternal;
                    
                    let cardClass = 'item-card-3d';
                    let badge = '';
                    if (it.isFinished) {
                        cardClass += ' finished';
                        badge = '<span style="font-size:0.7rem; background:#10b981; color:#fff; padding:2px 8px; border-radius:20px; margin-left:8px;">เสร็จสิ้น</span>';
                    } else if (it.isReceived) {
                        cardClass += ' received';
                        badge = '<span style="font-size:0.7rem; background:#3b82f6; color:#fff; padding:2px 8px; border-radius:20px; margin-left:8px;">รับกลับแล้ว</span>';
                    } else if (it.isAtExternal) {
                        cardClass += ' at-external';
                        badge = '<span style="font-size:0.7rem; background:#f97316; color:#fff; padding:2px 8px; border-radius:20px; margin-left:8px;">อยู่ร้านนอก</span>';
                    }

                    let hDest = it.history ? it.history.destination : 'office';
                    let hRemark = it.history ? it.history.remark : '';
                    let hShopName = (it.history && it.history.shop_info) ? it.history.shop_info.name : '';
                    let hShopOwner = (it.history && it.history.shop_info) ? it.history.shop_info.owner : '';
                    let hShopPhone = (it.history && it.history.shop_info) ? it.history.shop_info.phone : '';

                    htmlForm += `
                    <div class="${cardClass}" id="card_${idx}">
                        <div class="card-header-clickable" onclick="toggleItemCardViewOnly(${idx}, ${isLocked})">
                            <input type="checkbox" id="chk_${idx}" style="display:none;" ${isLocked ? 'checked disabled' : `onchange="toggleItemCard(${idx})"`} onclick="event.stopPropagation()">
                            <div class="chk-3d-box"><i class="fas fa-check"></i></div>
                            <span style="flex:1; font-size:0.95rem; font-weight:700; color:#334155;">${it.name} ${badge}</span>
                            <i class="fas fa-chevron-down" style="color:#94a3b8; transition:0.3s;" id="arrow_${idx}"></i>
                        </div>

                        <div class="card-content-reveal" id="details_${idx}">
                            <label style="font-size:0.8rem; font-weight:700; color:#64748b; margin-bottom:6px; display:block;">📍 ปลายทาง</label>
                            <div class="dest-selector">
                                <label style="cursor:${isLocked?'default':'pointer'};">
                                    <input type="radio" name="dest_${idx}" value="office" ${hDest==='office'?'checked':''} onchange="toggleShopFields(${idx})" style="display:none;" ${isLocked?'disabled':''}>
                                    <div class="dest-ui">กลับบริษัท</div>
                                </label>
                                <label style="cursor:${isLocked?'default':'pointer'};">
                                    <input type="radio" name="dest_${idx}" value="external" ${hDest==='external'?'checked':''} onchange="toggleShopFields(${idx})" style="display:none;" ${isLocked?'disabled':''}>
                                    <div class="dest-ui">ร้านภายนอก</div>
                                </label>
                            </div>
                            
                            <div id="shop_fields_${idx}" class="shop-fields-grid" style="${hDest==='external'?'display:block;':''}">
                                <input type="text" id="s_name_${idx}" class="form-input-3d" placeholder="ชื่อร้านค้า *" style="margin-bottom:8px;" value="${hShopName}" ${isLocked?'readonly':''}>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                                    <input type="text" id="s_owner_${idx}" class="form-input-3d" placeholder="ผู้ติดต่อ" value="${hShopOwner}" ${isLocked?'readonly':''}>
                                    <input type="text" id="s_phone_${idx}" class="form-input-3d" placeholder="เบอร์โทร" value="${hShopPhone}" ${isLocked?'readonly':''}>
                                </div>
                            </div>

                            <label style="font-size:0.8rem; font-weight:700; color:#64748b; margin-bottom:6px; display:block;">📝 หมายเหตุ *</label>
                            <textarea id="remark_${idx}" class="form-input-3d" rows="2" placeholder="ระบุเหตุผล..." ${isLocked?'readonly':''}>${hRemark}</textarea>

                            ${!isLocked ? `
                            <label style="font-size:0.8rem; font-weight:700; color:#64748b; margin-bottom:6px; display:block; margin-top:10px;">📎 หลักฐาน</label>
                            <div class="upload-area-3d" id="file_zone_${idx}" onclick="document.getElementById('file_${idx}').click()">
                                <div style="font-size:0.75rem; color:#64748b;" id="file_label_${idx}"><i class="fas fa-camera"></i> กดเพื่อแนบไฟล์</div>
                                <input type="file" id="file_${idx}" style="display:none;" accept="image/*" onchange="if(this.files.length>0){document.getElementById('file_label_'+${idx}).innerText='เลือก: '+this.files[0].name;document.getElementById('file_zone_'+${idx}).style.borderColor='#f97316';}">
                            </div>` : ''}
                        </div>
                    </div>`;
                });
            }
            htmlForm += `</div>`;

            Swal.fire({
                title: '<div style="font-family:Prompt; font-weight:800;">บันทึกนำของออก</div>',
                html: htmlForm,
                width: '550px',
                showCancelButton: true,
                confirmButtonText: 'บันทึกข้อมูล',
                confirmButtonColor: '#f97316',
                preConfirm: () => {
                    const formData = new FormData();
                    formData.append('action', 'receive_item');
                    formData.append('req_id', reqId);
                    let count = 0;
                    displayItems.forEach((it, idx) => {
                        if (it.isFinished || it.isReceived || it.isAtExternal) return; // 🔥 ห้ามส่งค่าตัวที่ล็อคแล้ว
                        const chk = document.getElementById(`chk_${idx}`);
                        if (chk && chk.checked) {
                            count++;
                            const destInp = document.querySelector(`input[name="dest_${idx}"]:checked`);
                            if(!destInp) return;
                            const dest = destInp.value;
                            const remark = document.getElementById(`remark_${idx}`).value.trim();
                            if (!remark) { Swal.showValidationMessage(`กรุณาใส่หมายเหตุรายการที่ ${idx+1}`); return false; }
                            
                            formData.append(`items[${idx}][name]`, it.name);
                            formData.append(`items[${idx}][destination]`, dest);
                            formData.append(`items[${idx}][remark]`, remark);

                            if (dest === 'external') {
                                const sName = document.getElementById(`s_name_${idx}`).value.trim();
                                if (!sName) { Swal.showValidationMessage(`กรุณาใส่ชื่อร้านค้ารายการที่ ${idx+1}`); return false; }
                                formData.append(`items[${idx}][shop_name]`, sName);
                                formData.append(`items[${idx}][shop_owner]`, document.getElementById(`s_owner_${idx}`).value);
                                formData.append(`items[${idx}][shop_phone]`, document.getElementById(`s_phone_${idx}`).value);
                            }
                            const fInp = document.getElementById(`file_${idx}`);
                            if (fInp.files.length > 0) formData.append(`item_files_${idx}`, fInp.files[0]);
                        }
                    });
                    if (count === 0) { Swal.showValidationMessage('กรุณาเลือกรายการสินค้าที่ยังค้างอยู่'); return false; }
                    return formData;
                }
            }).then((res) => {
                if (res.isConfirmed) {
                    $.ajax({
                        url: 'service_dashboard.php', type: 'POST', data: res.value, processData: false, contentType: false, dataType: 'json',
                        success: (r) => r.status === 'success' ? location.reload() : Swal.fire('Error', r.message, 'error')
                    });
                }
            });
        }
    });
}

// 🟢 ฟังก์ชันควบคุมการเปิดดู (View-Only)
window.toggleItemCardViewOnly = function(index, isLocked) {
    if (!isLocked) {
        const chk = document.getElementById(`chk_${index}`);
        chk.checked = !chk.checked;
        window.toggleItemCard(index);
    } else {
        const card = document.getElementById(`card_${index}`);
        const details = $(`#details_${index}`);
        const arrow = document.getElementById(`arrow_${index}`);
        if (card.classList.contains('active')) {
            card.classList.remove('active');
            arrow.style.transform = 'rotate(0deg)';
            details.slideUp(200);
        } else {
            card.classList.add('active');
            arrow.style.transform = 'rotate(180deg)';
            details.slideDown(300);
        }
    }
};

window.toggleItemCard = function(index) {
    const chk = document.getElementById(`chk_${index}`);
    const card = document.getElementById(`card_${index}`);
    const details = $(`#details_${index}`);
    const arrow = document.getElementById(`arrow_${index}`);
    if (chk.checked) {
        card.classList.add('active'); arrow.style.transform = 'rotate(180deg)'; details.slideDown(300);
    } else {
        card.classList.remove('active'); arrow.style.transform = 'rotate(0deg)'; details.slideUp(200);
    }
};

window.toggleShopFields = function(index) {
    const destInp = document.querySelector(`input[name="dest_${index}"]:checked`);
    if(!destInp) return;
    const dest = destInp.value;
    const shopBox = $(`#shop_fields_${index}`);
    const scrollBox = $('#modal_scroll_container');
    if (dest === 'external') {
        shopBox.slideDown(300, function() {
            let cardBottom = document.getElementById(`card_${index}`).offsetTop + document.getElementById(`card_${index}`).offsetHeight;
            scrollBox.animate({ scrollTop: cardBottom - scrollBox.height() + 20 }, 500);
        });
    } else { shopBox.slideUp(200); }
};

        // Helper Function สำหรับสลับการแสดงผลร้านค้า
        function toggleDest(type) {
            const box = document.getElementById('ext_box');
            if (box) {
                if (type === 'external') {
                    box.style.display = 'block';
                    box.style.animation = 'fadeInUp 0.3s ease-out';
                } else {
                    box.style.display = 'none';
                }
            }
        }

        // 9.1 ฟังก์ชันช่วยสลับช่องกรอก (ปลายทาง)
        function toggleDest(t) {
            $('#ext_box').toggle(t === 'external');
            $('#off_info').toggle(t === 'office');
        }

        // 10. ฟังก์ชันตรวจรับสินค้า / รับช่วงต่อ (แก้ไขให้รายการไม่หายหลังกดบันทึก)
function confirmOfficeReceipt(reqId, jsonInput) {
    let data = (typeof jsonInput === 'string') ? JSON.parse(jsonInput) : jsonInput;
    if (typeof data === 'string') data = JSON.parse(data);

    let itemsStatus = data.items_status || {};
    let itemsMovedList = data.items_moved || [];
    let currentHolding = data.accumulated_moved || [];
    let officeLogs = (data.details && data.details.office_log) ? data.details.office_log : [];

    let pendingItems = [];
    currentHolding.forEach(itemName => {
        let currentStatus = itemsStatus[itemName] || '';
        if (currentStatus !== 'at_external') {
            let initialMove = itemsMovedList.find(it => it.name === itemName);
            let lastHandover = [...officeLogs].reverse().find(log => 
                log.items && log.items.includes(itemName)
            );
            pendingItems.push({
                name: itemName,
                initialRemark: initialMove ? initialMove.remark : '-',
                initialFile: initialMove ? initialMove.file : null,
                lastRemark: lastHandover ? lastHandover.msg : null,
                lastFile: lastHandover ? lastHandover.file : null,
                dest: (currentStatus === 'at_office_unconfirmed') ? 'รอตรวจรับเข้า' : 'เปลี่ยนมือผู้ถือ'
            });
        }
    });

    let itemsHtml = '';
    pendingItems.forEach((it, idx) => {
        let uniqueId = `conf_chk_${idx}`;
        let initialImgBtn = it.initialFile ? 
            `<a href="uploads/proofs/${it.initialFile}" target="_blank" style="display:inline-flex; align-items:center; gap:5px; margin-top:8px; background:#f1f5f9; color:#475569; padding:5px 10px; border-radius:6px; font-size:11px; text-decoration:none; font-weight:700; border:1px solid #e2e8f0; white-space:nowrap;">
                <i class="fas fa-image"></i> รูปนำออก
            </a>` : '';

        let lastHandoverHtml = '';
        if (it.lastRemark) {
            let lastImgBtn = it.lastFile ? 
                `<a href="uploads/proofs/${it.lastFile}" target="_blank" style="display:inline-flex; align-items:center; gap:5px; margin-top:5px; background:#eff6ff; color:#2563eb; padding:5px 10px; border-radius:6px; font-size:11px; text-decoration:none; font-weight:700; border:1px solid #dbeafe; white-space:nowrap;">
                    <i class="fas fa-camera"></i> รูปรับล่าสุด
                </a>` : '';

            lastHandoverHtml = `
            <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #cbd5e1; width:100%;">
                <div style="font-size:11px; color:#3b82f6; font-weight:800; text-transform:uppercase; margin-bottom:2px;">บันทึกรับล่าสุด:</div>
                <div style="font-size:13px; color:#1e40af; line-height:1.4; word-break:break-word;">${it.lastRemark}</div>
                ${lastImgBtn}
            </div>`;
        }
        
        itemsHtml += `
        <div class="conf-card-premium" style="animation-delay: ${idx * 0.1}s;">
            <label for="${uniqueId}" style="display:flex; align-items:flex-start; padding:12px; cursor:pointer; gap:12px; margin:0; width:100%; box-sizing:border-box;">
                <input type="checkbox" id="${uniqueId}" class="item-chk-conf" value="${it.name}" style="width:20px; height:20px; accent-color:#3b82f6; cursor:pointer; flex-shrink:0; margin-top:2px;">
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:6px;">
                        <span style="font-weight:800; color:#1e293b; font-size:14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${it.name}</span>
                        <span style="font-size:10px; background:${it.dest === 'รอตรวจรับเข้า' ? '#fef3c7' : '#dcfce7'}; color:${it.dest === 'รอตรวจรับเข้า' ? '#d97706' : '#166534'}; padding:2px 8px; border-radius:12px; font-weight:700; border:1px solid ${it.dest === 'รอตรวจรับเข้า' ? '#fcd34d' : '#86efac'}; flex-shrink:0;">${it.dest}</span>
                    </div>
                    <div style="background:#f8fafc; padding:10px; border-radius:10px; border-left:4px solid #3b82f6; width:100%; box-sizing:border-box;">
                        <div style="font-size:11px; color:#64748b; font-weight:800; text-transform:uppercase; margin-bottom:2px;">ข้อมูลตอนนำออก:</div>
                        <div style="font-size:13px; color:#334155; line-height:1.4; word-break:break-word;">${it.initialRemark}</div>
                        ${initialImgBtn}
                        ${lastHandoverHtml}
                    </div>
                </div>
            </label>
        </div>`;
    });

    Swal.fire({
        title: '',
        html: `
        <style>
            @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
            .conf-card-premium { background:#fff; border:1px solid #e2e8f0; border-radius:14px; margin-bottom:10px; transition:0.2s; box-shadow:0 2px 4px rgba(0,0,0,0.02); opacity:0; animation: fadeInUp 0.4s ease forwards; text-align:left; box-sizing:border-box; overflow:hidden; }
            .conf-card-premium:hover { border-color:#cbd5e1; background:#fafafa; }
            .conf-card-premium:has(input:checked) { border-color:#3b82f6; background:#f0f9ff; }
            .swal-label { font-size:14px; font-weight:700; color:#475569; display:block; text-align:left; margin-bottom:6px; }
            .swal-input-premium { width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:10px; font-size:14px; box-sizing:border-box; transition:0.2s; background:#fff; font-family:'Prompt', sans-serif; }
            .swal-input-premium:focus { border-color:#3b82f6; outline:none; box-shadow:0 0 0 3px rgba(59, 130, 246, 0.1); }
            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
            ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        </style>

        <div style="padding:0; box-sizing:border-box;">
            <div style="text-align:center; margin-bottom:20px;">
                <div style="width:60px; height:60px; background:linear-gradient(135deg, #3b82f6, #1d4ed8); color:#fff; border-radius:18px; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; box-shadow:0 8px 16px -4px rgba(59, 130, 246, 0.4);">
                    <i class="fas fa-handshake fa-2x"></i>
                </div>
                <div style="font-size:22px; font-weight:900; color:#1e293b;">รับช่วงต่อ / ตรวจรับ</div>
                <div style="font-size:13px; color:#64748b;">กรุณาเลือกรายการและระบุหมายเหตุเพื่อยืนยัน</div>
            </div>

            <div style="text-align:left; margin-bottom:15px;">
                <label class="swal-label"><i class="fas fa-list-check"></i> รายการสินค้า (${pendingItems.length})</label>
                <div style="max-height:35vh; overflow-y:auto; padding:2px; margin-bottom:5px; box-sizing:border-box;">
                    ${itemsHtml || '<div style="text-align:center; padding:20px; color:#94a3b8;">- ไม่มีรายการ -</div>'}
                </div>
            </div>

            <div style="text-align:left; margin-bottom:15px;">
                <label class="swal-label">📝 หมายเหตุตรวจรับใหม่ <span style="color:#ef4444;">*</span></label>
                <textarea id="conf_remark" class="swal-input-premium" placeholder="ระบุสภาพของล่าสุด หรือข้อความถึงผู้ส่ง..." style="height:80px; resize:none;"></textarea>
            </div>

            <div style="text-align:left;">
                <label class="swal-label">📎 แนบรูปภาพ (ถ้ามี)</label>
                <input type="file" id="conf_file" class="swal-input-premium" style="padding:7px; font-size:12px;">
            </div>
        </div>
        `,
        width: '550px',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันการรับ',
        confirmButtonColor: '#3b82f6',
        cancelButtonText: 'ยกเลิก',
        preConfirm: () => {
            const checked = document.querySelectorAll('.item-chk-conf:checked');
            const remark = document.getElementById('conf_remark').value.trim();
            if (checked.length === 0) return Swal.showValidationMessage('กรุณาเลือกสินค้าอย่างน้อย 1 รายการ');
            if (!remark) return Swal.showValidationMessage('กรุณากรอกหมายเหตุ');
            let sel = []; checked.forEach(c => sel.push(c.value));
            return { items: sel, remark: remark, file: document.getElementById('conf_file').files[0] };
        }
    }).then(res => {
        if (res.isConfirmed) {
            let fd = new FormData();
            fd.append('action', 'confirm_office_receipt');
            fd.append('req_id', reqId);
            fd.append('remark', res.value.remark);
            if (res.value.file) fd.append('proof_file', res.value.file);
            res.value.items.forEach((it, idx) => fd.append(`checked_items[${idx}]`, it));
            Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: 'service_dashboard.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
                success: (response) => response.status === 'success' ? location.reload() : Swal.fire('Error', response.message, 'error')
            });
        }
    });
}

        // 11. ดูประวัติ Timeline (Premium 3D + เพิ่มชื่อเจ้าของร้าน)
        function viewReceiverDetails(jsonInput) {
            let data = (typeof jsonInput === 'string') ? JSON.parse(jsonInput) : jsonInput;
            if (typeof data === 'string') data = JSON.parse(data);
            let d = data.details || {};

            let stepCount = 1;
            let delayCounter = 0;

            // --- Helper: สร้าง HTML สำหรับ Timeline Item ---
            const createItem = (type, icon, title, subtitle, content, extraHtml = '') => {
                delayCounter += 0.1;
                let colorClass = '';
                let iconBg = '';

                switch (type) {
                    case 'start': colorClass = 'blue'; iconBg = 'linear-gradient(135deg, #60a5fa, #2563eb)'; break;
                    case 'shop': colorClass = 'orange'; iconBg = 'linear-gradient(135deg, #fbbf24, #d97706)'; break;
                    case 'check': colorClass = 'green'; iconBg = 'linear-gradient(135deg, #34d399, #059669)'; break;
                    case 'back': colorClass = 'pink'; iconBg = 'linear-gradient(135deg, #f472b6, #db2777)'; break;
                    case 'finish': colorClass = 'purple'; iconBg = 'linear-gradient(135deg, #a78bfa, #7c3aed)'; break;
                    default: colorClass = 'gray'; iconBg = '#cbd5e1';
                }

                return `
        <div class="timeline-row" style="animation-delay: ${delayCounter}s;">
            <div class="timeline-time-col">
                <div class="time-text">${subtitle}</div>
            </div>
            <div class="timeline-line-col">
                <div class="timeline-icon-circle" style="background: ${iconBg}; box-shadow: 0 0 0 4px #fff, 0 4px 10px rgba(0,0,0,0.2);">
                    <i class="fas ${icon}" style="color:#fff; font-size:0.9rem;"></i>
                </div>
                <div class="timeline-line"></div>
            </div>
            <div class="timeline-content-col">
                <div class="timeline-card hover-lift">
                    <div class="card-header-sm">
                        <span class="step-badge bg-${colorClass}">Step ${stepCount++}</span>
                        <span class="header-title text-${colorClass}">${title}</span>
                    </div>
                    <div class="card-body-sm">
                        ${content}
                        ${extraHtml}
                    </div>
                </div>
            </div>
        </div>`;
            };

            let timelineHtml = '<div class="premium-timeline">';

            // 1. Start: นำของออก
            timelineHtml += createItem(
                'start', 'fa-dolly',
                'นำออกจากหน้างาน',
                d.pickup_at || '-',
                `<div><i class="fas fa-user-tag"></i> ผู้ดำเนินการ: <b>${d.pickup_by}</b></div>
         <div class="remark-text">"${d.pickup_remark}"</div>`
            );

            // 2. Shop: ร้านภายนอก (🔥 แก้ไขจุดนี้: เพิ่มชื่อเจ้าของร้าน)
            if (d.shop_info) {
                timelineHtml += createItem(
                    'shop', 'fa-store',
                    'ส่งซ่อมร้านภายนอก',
                    '',
                    `<div><i class="fas fa-home"></i> <b>${d.shop_info.name}</b></div>
             <div><i class="fas fa-user-tie"></i> ผู้ติดต่อ: ${d.shop_info.owner || '-'}</div> <div><i class="fas fa-phone"></i> ${d.shop_info.phone || '-'}</div>`
                );
            }

            // 3. Office Log: ประวัติในบริษัท
            if (d.office_log && d.office_log.length > 0) {
                d.office_log.forEach(log => {
                    let isBack = (log.status === 'back_from_shop');
                    let type = isBack ? 'back' : 'check';
                    let icon = isBack ? 'fa-undo-alt' : 'fa-user-check';
                    let title = isBack ? 'รับกลับจากร้าน' : 'ตรวจสอบ / รับช่วงต่อ';

                    let attach = log.file ? `<a href="uploads/repairs/${log.file}" target="_blank" class="attach-link"><i class="fas fa-paperclip"></i> ดูใบเสนอราคา/หลักฐาน</a>` : '';

                    // สร้างตารางรายการค่าใช้จ่าย (ถ้ามี)
                    let expenseTable = '';
                    if (isBack && log.expenses && log.expenses.length > 0) {
                        expenseTable = `
                <div style="margin-top:10px; background:#fff; border:1px solid #fbcfe8; border-radius:8px; overflow:hidden;">
                    <div style="background:#fdf2f8; padding:5px 10px; font-weight:700; color:#be185d; font-size:0.8rem; border-bottom:1px solid #fbcfe8;">
                        <i class="fas fa-file-invoice-dollar"></i> รายละเอียดค่าใช้จ่าย
                    </div>
                    <table style="width:100%; font-size:0.8rem; border-collapse:collapse;">
                        <tr style="border-bottom:1px dashed #fce7f3; color:#831843;">
                            <th style="padding:5px; text-align:left;">รายการ</th>
                            <th style="padding:5px; text-align:center;">จำนวน</th>
                            <th style="padding:5px; text-align:right;">รวม</th>
                        </tr>
                        <tbody>`;

                        log.expenses.forEach(ex => {
                            let total = parseFloat(ex.total || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                            expenseTable += `
                        <tr style="border-bottom:1px dashed #fce7f3;">
                            <td style="padding:5px; color:#334155;">${ex.name}</td>
                            <td style="padding:5px; text-align:center; color:#64748b;">${ex.qty}</td>
                            <td style="padding:5px; text-align:right; color:#db2777;">${total}</td>
                        </tr>`;
                        });

                        let grandTotal = parseFloat(log.total_cost || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                        expenseTable += `
                        <tr style="background:#fdf2f8;">
                            <td colspan="2" style="padding:5px; text-align:right; font-weight:700; color:#831843;">ยอดสุทธิ</td>
                            <td style="padding:5px; text-align:right; font-weight:800; color:#be185d;">${grandTotal}</td>
                        </tr>
                    </tbody>
                </table>
                </div>`;
                    }

                    timelineHtml += createItem(type, icon, title, log.at,
                        `<div><i class="fas fa-user"></i> ${log.by}</div>
                 ${log.msg ? `<div class="remark-text">${log.msg}</div>` : ''}
                 ${expenseTable}`,
                        attach
                    );
                });
            } else if (d.type === 'office' && !d.customer_return) {
                timelineHtml += `<div class="timeline-row pending">
            <div class="timeline-time-col"></div>
            <div class="timeline-line-col">
                <div class="timeline-icon-circle" style="background:#e2e8f0;"><i class="fas fa-ellipsis-h" style="color:#94a3b8;"></i></div>
            </div>
            <div class="timeline-content-col"><div style="padding:10px; color:#94a3b8; font-style:italic;">... กำลังดำเนินการ ...</div></div>
        </div>`;
            }

            // 4. Finish: จบงาน
            if (d.customer_return) {
                let r = d.customer_return;
                let stars = ''; for (let i = 1; i <= 5; i++) stars += i <= (r.rating || 0) ? '<i class="fas fa-star text-yellow"></i>' : '<i class="fas fa-star text-gray"></i>';
                let attach = r.file ? `<a href="uploads/returns/${r.file}" target="_blank" class="btn-view-proof"><i class="fas fa-image"></i> รูปส่งงาน</a>` : '';

                timelineHtml += createItem(
                    'finish', 'fa-flag-checkered',
                    'จบงาน (ส่งคืนเรียบร้อย)',
                    r.at,
                    `<div style="margin-bottom:5px;">${stars} <span style="font-weight:bold; color:#7c3aed;">(${r.rating}/5)</span></div>
             <div class="remark-text">"${r.msg || '-'}"</div>`,
                    attach
                );
            }

            timelineHtml += '</div>';

            // --- UI Popup ---
            Swal.fire({
                title: '',
                html: `
        <style>
            /* CSS Styles (Keep original styles) */
            .premium-timeline { display: flex; flex-direction: column; position: relative; padding-bottom: 20px; }
            .timeline-row { display: flex; gap: 15px; position: relative; opacity: 0; animation: fadeInUp 0.5s ease forwards; }
            .timeline-time-col { width: 80px; text-align: right; padding-top: 15px; flex-shrink: 0; }
            .time-text { font-size: 0.75rem; color: #64748b; font-weight: 600; line-height: 1.2; }
            .timeline-line-col { width: 40px; position: relative; display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
            .timeline-icon-circle { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 2; position: relative; margin-top: 5px; transition: transform 0.2s; }
            .timeline-row:hover .timeline-icon-circle { transform: scale(1.1); }
            .timeline-line { width: 2px; background: #e2e8f0; flex-grow: 1; margin-top: 5px; margin-bottom: -15px; z-index: 1; }
            .timeline-row:last-child .timeline-line { display: none; }
            .timeline-content-col { flex-grow: 1; padding-bottom: 20px; text-align: left; }
            .timeline-card { background: #fff; border: 1px solid #f1f5f9; border-radius: 12px; padding: 12px 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); transition: all 0.2s; position: relative; }
            .timeline-card::before { content:''; position:absolute; left:-6px; top:20px; width:12px; height:12px; background:#fff; transform:rotate(45deg); border-left:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9; }
            .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.08); border-color: #e2e8f0; }
            .card-header-sm { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
            .step-badge { font-size: 0.65rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
            .header-title { font-size: 0.95rem; font-weight: 700; }
            .card-body-sm { font-size: 0.9rem; color: #334155; line-height: 1.5; }
            .remark-text { background: #f8fafc; padding: 6px 10px; border-radius: 6px; font-style: italic; color: #475569; margin-top: 5px; border-left: 3px solid #cbd5e1; font-size: 0.85rem; }
            .attach-link { display: inline-block; margin-top: 8px; font-size: 0.8rem; color: #2563eb; text-decoration: none; font-weight: 600; background: #eff6ff; padding: 4px 10px; border-radius: 20px; }
            .btn-view-proof { display: inline-block; margin-top: 10px; background: linear-gradient(90deg, #8b5cf6, #7c3aed); color: #fff; padding: 6px 15px; border-radius: 50px; text-decoration: none; font-size: 0.85rem; font-weight: 600; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3); transition: 0.2s; }
            .btn-view-proof:hover { transform: scale(1.05); }
            
            .text-blue { color: #2563eb; } .bg-blue { background: #dbeafe; color: #1e40af; }
            .text-orange { color: #d97706; } .bg-orange { background: #fef3c7; color: #92400e; }
            .text-green { color: #059669; } .bg-green { background: #d1fae5; color: #065f46; }
            .text-pink { color: #db2777; } .bg-pink { background: #fce7f3; color: #9d174d; }
            .text-purple { color: #7c3aed; } .bg-purple { background: #ede9fe; color: #5b21b6; }
            .text-yellow { color: #f59e0b; } .text-gray { color: #cbd5e1; }

            @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        </style>

        <div style="padding: 5px 0;">
            <div class="modal-modern-header" style="text-align: center; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                <div style="width: 50px; height: 50px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; color: #334155;">
                    <i class="fas fa-history fa-lg"></i>
                </div>
                <div class="modal-title-text" style="font-size: 1.2rem; font-weight: 800; color: #1e293b;">ประวัติการดำเนินการ</div>
                <div style="font-size: 0.85rem; color: #64748b;">Tracking History</div>
            </div>
            
            <div style="max-height: 500px; overflow-y: auto; padding-right: 5px;">
                ${timelineHtml}
            </div>
        </div>
        `,
                width: '600px',
                showConfirmButton: true,
                confirmButtonText: 'ปิดหน้าต่าง',
                confirmButtonColor: '#64748b',
                customClass: { popup: 'rounded-2xl' }
            });
        }

        // 12. ฟังก์ชันส่งคืนลูกค้า / จบงาน (แก้ไข: โชว์หมายเหตุ+รูป จากตอนนำออก)
function returnToCustomer(reqId, jsonInput, isEditMode = false) {
    // 1. เตรียมข้อมูล
    let data = (typeof jsonInput === 'string') ? JSON.parse(jsonInput) : jsonInput;
    if (typeof data === 'string') data = JSON.parse(data);

    let d = data.details || {};
    let repairSummaries = data.item_repair_summaries || {};
    
    // ดึงรายการที่ "เคยคืนไปแล้วในงวดก่อนๆ" เพื่อเอามาล็อก Checkbox
    let alreadyReturned = (d.customer_return && d.customer_return.items_returned) ? d.customer_return.items_returned : [];

    // ดึงรายชื่อสินค้าทั้งหมดที่มีในใบงาน
    let allItems = data.accumulated_moved || data.items || [];
    if (allItems.length === 0 && data.items_moved) {
        allItems = [...new Set(data.items_moved.map(i => i.name))];
    }

    // ---------------------------------------------------------
    // 🟢 โหมดดูรายละเอียด (Read Only / Summary)
    // ---------------------------------------------------------
    // จะแสดงหน้านี้ก็ต่อเมื่อ คืนครบทุกชิ้นแล้ว และไม่ได้อยู่ในโหมดแก้ไข
    if (d.customer_return && d.customer_return.at && !isEditMode && alreadyReturned.length === allItems.length) {
        let r = d.customer_return;
        let stars = '';
        let score = parseInt(r.rating) || 0;
        for (let i = 1; i <= 5; i++) stars += i <= score ? '<span style="color:#f59e0b; font-size:1.5rem;">★</span>' : '<span style="color:#e2e8f0; font-size:1.5rem;">★</span>';

        let itemsHtml = (r.items_returned || []).map(it => {
            let itClean = it.trim();
            let itemSum = repairSummaries[itClean] ? 
                `<div style="margin-top:5px; padding-top:5px; border-top:1px dashed #ddd6fe; font-size:0.8rem; color:#059669; font-style:italic;">
                    <i class="fas fa-wrench"></i> สรุปซ่อม: ${repairSummaries[itClean]}
                </div>` : '';
            
            return `<div style="background:#fff; border:1px solid #ddd6fe; padding:10px 12px; border-radius:10px; margin-bottom:8px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                <div style="display:flex; align-items:center; gap:8px; font-size:0.9rem; color:#5b21b6; font-weight:700;">
                    <i class="fas fa-check-circle" style="color:#8b5cf6;"></i> ${itClean}
                </div>
                ${itemSum}
            </div>`;
        }).join('');

        let fileBtn = r.file ? `<a href="uploads/returns/${r.file}" target="_blank" style="display:block; width:100%; text-align:center; background:#8b5cf6; color:#fff; padding:10px; border-radius:10px; text-decoration:none; margin-top:15px; font-weight:600;"><i class="fas fa-image"></i> ดูรูปหลักฐานการส่งคืนล่าสุด</a>` : '';

        Swal.fire({
            title: '',
            html: `<div style="text-align:left; padding:5px;">
                <div style="text-align:center; margin-bottom:20px;">
                    <div style="width:60px; height:60px; background:#ede9fe; color:#8b5cf6; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);">
                        <i class="fas fa-file-signature fa-2x"></i>
                    </div>
                    <div style="font-size:1.4rem; font-weight:800; color:#4c1d95;">รายละเอียดการส่งมอบงาน</div>
                </div>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:15px; margin-bottom:15px;">
                    <div style="text-align:center; margin-bottom:10px;">${stars}</div>
                    <label style="font-weight:700; color:#166534; display:block; margin-bottom:5px;"><i class="fas fa-comment-dots"></i> ข้อเสนอแนะลูกค้า:</label>
                    <div style="color:#14532d; font-size:0.9rem;">${r.remark || '-'}</div>
                </div>
                <label style="font-weight:700; color:#4c1d95; display:block; margin-bottom:8px;">📦 รายการสินค้าที่คืนพร้อมผลซ่อม:</label>
                <div style="max-height:250px; overflow-y:auto; padding-right:5px;">${itemsHtml || '-'}</div>
                ${fileBtn}
                <div style="margin-top:20px; font-size:0.75rem; color:#94a3b8; text-align:center; border-top:1px solid #f1f5f9; padding-top:10px;">บันทึกโดย: ${r.by} เมื่อ ${r.at}</div>
            </div>`,
            width: '550px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-edit"></i> แก้ไข/บันทึกเพิ่ม',
            confirmButtonColor: '#f59e0b',
            cancelButtonText: 'ปิด',
            customClass: { popup: 'rounded-24' }
        }).then((res) => { if (res.isConfirmed) returnToCustomer(reqId, jsonInput, true); });
        return;
    }

    // ---------------------------------------------------------
    // 🟠 โหมดกรอกข้อมูล / แก้ไข (Input Form)
    // ---------------------------------------------------------
    let moveHistory = data.items_moved || [];
    let oldRating = 0; // เริ่มที่ 0 ทุกครั้งเพื่อให้ประเมินใหม่ในแต่ละรอบ
    let oldRemark = '';

    let itemCheckHtml = '<div class="item-grid-wrapper">';
    allItems.forEach((itName, idx) => {
        let itNameTrim = itName.trim();
        let uniqueId = `ret_itm_${reqId}_${idx}`;
        
        // เช็คว่ารายการนี้เคยคืนไปหรือยัง
        let isDoneBefore = alreadyReturned.includes(itNameTrim);
        
        // ค้นหาประวัติการนำออกล่าสุดเพื่อเอารูปและหมายเหตุ
        let itemInfo = [...moveHistory].reverse().find(m => m.name === itNameTrim);
        let prevRemark = itemInfo ? itemInfo.remark : '-';
        let prevFile = itemInfo ? itemInfo.file : null;

        // ดึงผลการซ่อมรายชิ้น
        let itemSummaryText = repairSummaries[itNameTrim] || '<span style="color:#ef4444;">ยังไม่ได้ระบุสรุปงานซ่อม</span>';

        // ไฟล์แนบตอนนำออก (ปุ่มดูรูป)
        let fileLinkHtml = prevFile ? 
            `<a href="uploads/proofs/${prevFile}" target="_blank" onclick="event.stopPropagation()" style="display:inline-flex; align-items:center; gap:5px; background:#fff; border:1px solid #ddd6fe; color:#7c3aed; padding:4px 10px; border-radius:20px; font-size:0.75rem; text-decoration:none; font-weight:600; margin-top:8px;">
                <i class="fas fa-image"></i> ดูรูปตอนนำออก
            </a>` : '';

        itemCheckHtml += `
        <div class="item-card-3d ${isDoneBefore ? 'active' : ''}" id="card_wrap_${idx}" style="${isDoneBefore ? 'opacity:0.75;' : ''}">
            <label for="${uniqueId}" class="card-header" ${isDoneBefore ? '' : `onclick="toggleRetDetail(${idx})"`}>
                <input type="checkbox" id="${uniqueId}" class="return-item-chk" value="${itNameTrim}" 
                    ${isDoneBefore ? 'checked disabled' : ''} onchange="toggleRetDetail(${idx})"> 
                <div class="chk-circle"><i class="fas fa-check"></i></div>
                <span class="item-text">${itNameTrim} ${isDoneBefore ? '<small>(คืนแล้ว)</small>' : ''}</span>
                <i class="fas fa-chevron-down arrow-icon" id="arrow_${idx}"></i>
            </label>
            <div class="card-detail" id="detail_${idx}" style="${isDoneBefore ? 'display:block;' : 'display:none;'}">
                <div style="margin-bottom:10px; background:#ecfdf5; padding:10px; border-radius:8px; border:1px solid #bbf7d0;">
                    <div style="font-size:0.75rem; color:#065f46; font-weight:800; text-transform:uppercase;">ผลการซ่อมรายอุปกรณ์:</div>
                    <div style="font-size:0.85rem; color:#047857; font-weight:600;">${itemSummaryText}</div>
                </div>
                <div style="font-size:0.8rem; color:#6b7280; font-weight:600; margin-bottom:2px;">บันทึกตอนนำออก:</div>
                <div style="background:#f9fafb; padding:8px; border-radius:6px; font-size:0.85rem; color:#374151; border-left:3px solid #ddd6fe;">
                    ${prevRemark}
                </div>
                ${fileLinkHtml}
            </div>
        </div>`;
    });
    itemCheckHtml += '</div>';

    Swal.fire({
        title: '',
        html: `
        <style>
            .item-grid-wrapper { display: flex; flex-direction: column; gap: 10px; text-align: left; }
            .item-card-3d { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: all 0.2s; }
            .item-card-3d.active { border-color: #8b5cf6; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.1); }
            .card-header { display: flex; align-items: center; gap: 12px; padding: 12px 15px; cursor: pointer; background: #fff; transition: background 0.2s; }
            .item-card-3d.active .card-header { background: linear-gradient(to right, #f5f3ff, #fff); border-bottom: 1px dashed #ede9fe; }
            .chk-circle { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #d1d5db; display: flex; align-items: center; justify-content: center; color: transparent; flex-shrink: 0; }
            .item-card-3d.active .chk-circle { background: #8b5cf6; border-color: #8b5cf6; color: #fff; }
            .item-text { flex-grow: 1; font-weight: 500; color: #374151; font-size: 0.95rem; }
            .item-card-3d.active .item-text { color: #5b21b6; font-weight: 700; }
            .arrow-icon { color: #9ca3af; transition: transform 0.3s; }
            .card-detail { padding: 0 15px 15px 49px; background: #fff; }
            .rating-wrapper { display: flex; flex-direction: row-reverse; justify-content: center; gap: 10px; margin: 15px 0; }
            .rating-wrapper input { display: none; }
            .rating-wrapper label { font-size: 2.8rem; color: #e5e7eb; cursor: pointer; transition: 0.2s; }
            .rating-wrapper label:hover, .rating-wrapper label:hover ~ label, .rating-wrapper input:checked ~ label { color: #f59e0b; }
            .input-box-purple { width: 100%; border: 1px solid #ddd6fe; border-radius: 12px; padding: 12px; font-size: 0.95rem; box-sizing: border-box; background: #fff; }
        </style>

        <div style="padding:0;">
            <div style="text-align:center; margin-bottom:20px;">
                <div style="width:60px; height:60px; background:linear-gradient(135deg, #ede9fe, #f5f3ff); color:#8b5cf6; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);">
                    <i class="fas fa-shipping-fast fa-2x"></i>
                </div>
                <div style="font-size:1.4rem; font-weight:800; color:#4c1d95;">คืนสินค้า / จบงาน</div>
                <p id="status_hint" style="font-size:0.85rem; color:#7c3aed; font-weight:700; margin-top:5px;"></p>
            </div>

            <div id="return-form-container" style="text-align:left;">
                <label style="font-weight:700; color:#5b21b6; margin-bottom:8px; display:block;">📦 รายการสินค้าที่ส่งคืนรอบนี้</label>
                ${itemCheckHtml}
                
                <div id="rating_section" style="margin-top:25px; padding-top:20px; border-top:2px dashed #ede9fe;">
                    <label style="font-weight:700; color:#5b21b6; display:block; text-align:center;">⭐ ประเมินความพึงพอใจลูกค้าในรอบนี้</label>
                    <div class="rating-wrapper">
                        <input type="radio" id="star5" name="rt" value="5"><label for="star5">★</label>
                        <input type="radio" id="star4" name="rt" value="4"><label for="star4">★</label>
                        <input type="radio" id="star3" name="rt" value="3"><label for="star3">★</label>
                        <input type="radio" id="star2" name="rt" value="2"><label for="star2">★</label>
                        <input type="radio" id="star1" name="rt" value="1"><label for="star1">★</label>
                    </div>
                </div>

                <div style="margin-top:10px;">
                    <label style="font-weight:700; color:#5b21b6; margin-bottom:8px; display:block;">📝 หมายเหตุ / ข้อเสนอแนะลูกค้า</label>
                    <textarea id="ret_remark" class="input-box-purple" placeholder="ระบุความคิดเห็นเพิ่มเติมจากลูกค้า (ถ้ามี)..." style="height: 80px;">${oldRemark}</textarea>
                </div>

                <div style="margin-top:15px;">
                    <label style="font-weight:700; color:#5b21b6; margin-bottom:8px; display:block;">📎 แนบหลักฐานการส่งคืน (ถ้ามี)</label>
                    <input type="file" id="ret_file" class="form-control" style="border-radius:10px; font-size:0.85rem;">
                </div>
            </div>
        </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: 'บันทึกการส่งคืน',
        confirmButtonColor: '#8b5cf6',
        cancelButtonText: 'ยกเลิก',
        
        didOpen: () => {
            window.toggleRetDetail = function(idx) {
                const chk = document.getElementById(`ret_itm_${reqId}_${idx}`);
                if (!chk) return;
                const card = document.getElementById(`card_wrap_${idx}`);
                const detail = document.getElementById(`detail_${idx}`);
                const arrow = document.getElementById(`arrow_${idx}`);
                
                if (chk.checked) { 
                    card.classList.add('active'); 
                    $(detail).slideDown(200);
                    if(arrow) arrow.style.transform = 'rotate(180deg)';
                } else { 
                    card.classList.remove('active'); 
                    $(detail).slideUp(200);
                    if(arrow) arrow.style.transform = 'rotate(0deg)';
                }
                
                // อัปเดตข้อความ Hint บอกสถานะจำนวนชิ้น
                let checkedCount = document.querySelectorAll('.return-item-chk:checked').length;
                let totalCount = allItems.length;
                document.getElementById('status_hint').innerText = (checkedCount === totalCount) ? "✨ ติ๊กครบทุกชิ้นแล้ว พร้อมบันทึกจบงานหลัก" : `📦 คืนแล้ว ${checkedCount}/${totalCount} รายการ (ยังไม่จบงานทั้งหมด)`;
            };
            // เรียกทำงานครั้งแรกเพื่อจัดระเบียบหน้าจอ
            allItems.forEach((_, idx) => window.toggleRetDetail(idx));
        },

        preConfirm: () => {
            const items = document.querySelectorAll('.return-item-chk:checked:not(:disabled)');
            if (items.length === 0) return Swal.showValidationMessage('กรุณาเลือกรายการที่จะส่งคืนในรอบนี้');

            const ratingEl = document.querySelector(`input[name="rt"]:checked`);
            if (!ratingEl) return Swal.showValidationMessage('กรุณาประเมินความพึงพอใจลูกค้าด้วยครับ');

            // ตรวจสอบว่าหลังจากบันทึกรอบนี้แล้ว ของจะครบทุกชิ้นหรือไม่
            let totalCheckedNow = document.querySelectorAll('.return-item-chk:checked').length;
            let isFinal = (totalCheckedNow === allItems.length);

            let sel = []; items.forEach(c => sel.push(c.value));
            return { 
                items: sel, 
                rating: ratingEl.value, 
                remark: document.getElementById('ret_remark').value.trim(),
                is_final: isFinal,
                file: document.getElementById('ret_file').files[0]
            };
        }
    }).then(res => {
        if (res.isConfirmed) {
            let formData = new FormData();
            formData.append('action', 'return_to_customer');
            formData.append('req_id', reqId);
            formData.append('rating', res.value.rating);
            formData.append('return_remark', res.value.remark);
            formData.append('is_final', res.value.is_final ? '1' : '0'); // ส่งไปบอก PHP ว่าจบงานหรือยัง
            if (res.value.file) formData.append('return_proof', res.value.file);
            
            // ส่งรายชื่อสินค้าที่คืนรอบนี้
            res.value.items.forEach((it, i) => formData.append(`returned_items[${i}]`, it));

            Swal.fire({ title: 'กำลังบันทึกข้อมูล...', didOpen: () => Swal.showLoading() });

            $.ajax({
                url: 'service_dashboard.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: (response) => {
                    if (response.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'บันทึกเรียบร้อย', timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}
        // 13. ฟังก์ชันรับของกลับ (ฉบับเลือกทีละร้าน - ป้องกันบิลซ้ำซ้อน)
function receiveFromShop(reqId, jsonInput) {
    let data = (typeof jsonInput === 'string') ? JSON.parse(jsonInput) : jsonInput;
    if (typeof data === 'string') data = JSON.parse(data);

    let itemsStatus = data.items_status || {};
    let moveHistory = data.items_moved || [];
    
    // 🟢 1. จัดกลุ่มข้อมูลแยกตามร้านค้าเตรียมไว้
    let shopData = {}; 
    for (let itemName in itemsStatus) {
        if (itemsStatus[itemName] === 'at_external') {
            let history = moveHistory.filter(h => h.name === itemName && h.destination === 'external').pop();
            let sInfo = (history && history.shop_info) ? history.shop_info : { name: 'ไม่ระบุร้าน', owner: '-', phone: '-' };
            
            let sName = sInfo.name;
            if (!shopData[sName]) {
                shopData[sName] = { info: sInfo, items: [] };
            }
            shopData[sName].items.push(itemName);
        }
    }

    // สร้างตัวเลือก Dropdown สำหรับรายชื่อร้าน
    let shopOptions = `<option value="">-- เลือกบริษัท/ร้านค้า ที่จะรับของกลับ --</option>`;
    for (let sName in shopData) {
        shopOptions += `<option value="${sName}">${sName} (${shopData[sName].items.length} รายการ)</option>`;
    }

    Swal.fire({
        title: '',
        html: `
        <style>
            .shop-selector-box { background: #be185d; padding: 20px; border-radius: 15px 15px 0 0; margin: -25px -30px 20px -30px; color: #fff; }
            .modern-select { width: 100%; padding: 12px; border-radius: 10px; border: none; font-weight: 700; color: #be185d; cursor: pointer; }
            .section-label { font-size: 0.9rem; font-weight: 700; color: #831843; margin-bottom: 10px; display: block; }
            .item-container { background: #fff; border: 1px solid #fbcfe8; border-radius: 12px; padding: 10px; display: none; margin-bottom: 20px; }
            .expense-row { display: flex; gap: 8px; margin-bottom: 8px; }
            .f-input { border: 1px solid #fbcfe8; border-radius: 8px; padding: 8px; font-size: 0.85rem; outline: none; width: 100%; box-sizing: border-box; }
            .modern-textarea { width: 100%; border: 1px solid #fbcfe8; border-radius: 10px; padding: 10px; font-size: 0.9rem; margin-bottom: 15px; }
            .file-upload-pink { background: #fff; border: 2px dashed #f9a8d4; border-radius: 12px; padding: 15px; text-align: center; cursor: pointer; color: #9d174d; }
        </style>

        <div style="padding:0;">
            <div class="shop-selector-box">
                <div style="font-size: 1.1rem; font-weight: 800; margin-bottom: 10px;"><i class="fas fa-store"></i> ดำเนินการรับของจากร้านค้า</div>
                <select id="selected_shop_name" class="modern-select" onchange="updateShopItems()">
                    ${shopOptions}
                </select>
                <div id="shop_contact_info" style="margin-top:10px; font-size:0.8rem; display:none;">
                    <i class="fas fa-user-tie"></i> ผู้ติดต่อ: <span id="lbl_owner">-</span> | <i class="fas fa-phone-alt"></i> <span id="lbl_phone">-</span>
                </div>
            </div>

            <div id="main_form_content" style="opacity: 0.5; pointer-events: none;">
                <label class="section-label">📦 เลือกสินค้าที่รับกลับ</label>
                <div id="item_list_area" class="item-container" style="display:block;">
                    <div style="text-align:center; color:#94a3b8; padding:10px;">กรุณาเลือกร้านค้าก่อน</div>
                </div>

                <label class="section-label">📋 บันทึกค่าใช้จ่าย (ตามบิลใบเสร็จ)</label>
                <div id="expense-list-container" style="margin-bottom:10px;"></div>
                <button type="button" class="btn-add-row" onclick="addExpenseRow()" style="width:100%; padding:8px; border:1px dashed #db2777; border-radius:8px; background:none; color:#db2777; cursor:pointer; font-weight:700;">
                    <i class="fas fa-plus-circle"></i> เพิ่มรายการค่าใช้จ่าย
                </button>

                <div style="margin-top:15px; background:#fff; padding:12px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; border:1px solid #fbcfe8;">
                    <div style="font-weight:700; color:#831843;">💰 ยอดรวมร้านนี้</div>
                    <div id="total-display" style="font-size:1.3rem; font-weight:800; color:#db2777;">0.00</div>
                    <input type="hidden" id="final_total_cost" value="0">
                </div>

                <div style="margin-top:20px;">
                    <label class="section-label">🧾 หลักฐานการรับกลับ / ใบเสร็จ</label>
                    <textarea id="shop_return_remark" class="modern-textarea" placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                    <div class="file-upload-pink" onclick="document.getElementById('shop_file').click()">
                        <i class="fas fa-cloud-upload-alt fa-lg"></i>
                        <div id="file-label-text" style="font-size:0.85rem; margin-top:5px;">แนบใบเสร็จ/รูปภาพ</div>
                        <input type="file" id="shop_file" style="display:none;" onchange="if(this.files.length>0) document.getElementById('file-label-text').innerText='แนบแล้ว: '+this.files[0].name;">
                    </div>
                </div>
            </div>
        </div>
        `,
        width: '550px',
        showCancelButton: true,
        confirmButtonText: 'บันทึกการรับกลับ',
        confirmButtonColor: '#db2777',
        didOpen: () => {
            // ฟังก์ชันอัปเดตรายการสินค้าเมื่อเปลี่ยนร้าน
            window.updateShopItems = function() {
                let sName = document.getElementById('selected_shop_name').value;
                let area = document.getElementById('item_list_area');
                let form = document.getElementById('main_form_content');
                let contact = document.getElementById('shop_contact_info');
                
                if (!sName) {
                    area.innerHTML = `<div style="text-align:center; color:#94a3b8; padding:10px;">กรุณาเลือกร้านค้าก่อน</div>`;
                    form.style.opacity = "0.5";
                    form.style.pointer_events = "none";
                    contact.style.display = "none";
                    return;
                }

                form.style.opacity = "1";
                form.style.pointerEvents = "auto";
                contact.style.display = "block";
                
                let group = shopData[sName];
                document.getElementById('lbl_owner').innerText = group.info.owner;
                document.getElementById('lbl_phone').innerText = group.info.phone;

                let html = "";
                group.items.forEach(itemName => {
                    html += `
                    <label style="display:flex; align-items:center; gap:10px; padding:10px; border-radius:8px; cursor:pointer; background:#fdf2f8; margin-bottom:5px; border:1px solid #fce7f3;">
                        <input type="checkbox" class="return-item-chk" value="${itemName}" checked style="width:18px; height:18px; accent-color:#db2777;">
                        <span style="font-size:0.9rem; color:#831843; font-weight:600;">${itemName}</span>
                    </label>`;
                });
                area.innerHTML = html;
            };

            window.addExpenseRow = function() {
                const container = document.getElementById('expense-list-container');
                const row = document.createElement('div');
                row.className = 'expense-row';
                row.innerHTML = `
                    <input type="text" class="exp-name f-input" style="flex:3;" placeholder="รายการ..." oninput="updateTotal()">
                    <input type="number" class="exp-qty f-input" style="flex:1; text-align:center;" value="1" oninput="updateTotal()">
                    <input type="number" class="exp-price f-input" style="flex:1.5; text-align:right;" placeholder="0.00" oninput="updateTotal()">
                    <button type="button" onclick="this.parentElement.remove(); updateTotal();" style="border:none; background:none; color:#ef4444; cursor:pointer; width:30px;"><i class="fas fa-times-circle"></i></button>
                `;
                container.appendChild(row);
            };

            window.updateTotal = function() {
                let total = 0;
                document.querySelectorAll('.expense-row').forEach(row => {
                    const qty = parseFloat(row.querySelector('.exp-qty').value) || 0;
                    const price = parseFloat(row.querySelector('.exp-price').value) || 0;
                    total += qty * price;
                });
                document.getElementById('total-display').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2});
                document.getElementById('final_total_cost').value = total;
            };

            addExpenseRow();
        },
        preConfirm: () => {
            let shopName = document.getElementById('selected_shop_name').value;
            if (!shopName) return Swal.showValidationMessage('กรุณาเลือกร้านค้า');

            let selectedItems = [];
            document.querySelectorAll('.return-item-chk:checked').forEach(chk => selectedItems.push(chk.value));
            if (selectedItems.length === 0) return Swal.showValidationMessage('กรุณาเลือกสินค้าอย่างน้อย 1 รายการ');

            let expenses = [];
            document.querySelectorAll('.expense-row').forEach(row => {
                let name = row.querySelector('.exp-name').value.trim();
                let qty = parseFloat(row.querySelector('.exp-qty').value) || 0;
                let price = parseFloat(row.querySelector('.exp-price').value) || 0;
                if (name) expenses.push({ name: name, qty: qty, price: price, total: qty * price });
            });

            return {
                req_id: reqId,
                shop_name: shopName,
                selected_items: selectedItems,
                expenses: expenses,
                total: document.getElementById('final_total_cost').value,
                remark: document.getElementById('shop_return_remark').value,
                file: document.getElementById('shop_file').files[0]
            };
        }
    }).then(res => {
        if (res.isConfirmed) {
            let fd = new FormData();
            fd.append('action', 'receive_from_shop');
            fd.append('req_id', res.value.req_id);
            fd.append('shop_name', res.value.shop_name);
            fd.append('return_items', JSON.stringify(res.value.selected_items));
            fd.append('repair_items', JSON.stringify(res.value.expenses));
            fd.append('repair_cost', res.value.total);
            fd.append('return_remark', res.value.remark);
            if (res.value.file) fd.append('shop_file', res.value.file);

            $.ajax({
                url: 'service_dashboard.php', type: 'POST', data: fd,
                processData: false, contentType: false, dataType: 'json',
                success: (resp) => { if(resp.status==='success') location.reload(); else Swal.fire('Error', resp.message, 'error'); }
            });
        }
    });
}

        function calculateTotalExpense() {
            let total = 0;
            document.querySelectorAll('.exp-price').forEach(input => {
                total += parseFloat(input.value || 0);
            });
            document.getElementById('total-display').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('final_total_cost').value = total;
        }

// 14. ฟังก์ชันเปิดหน้าต่างอัปเดตความคืบหน้า (ฉบับแยกปุ่ม: บันทึกทั่วไป vs จบงานรายการที่เลือก)
function openUpdateModal(data) {
    console.log("🔍 Data Processing:", data);

    // 1. แปลง Data เป็น Object
    if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch (e) { }
    }

    let logs = [];
    try { logs = JSON.parse(data.progress_logs) || []; } catch (e) { }

    // 🔥 จุดสำคัญ: ดึงรายชื่อรายการที่ทำเสร็จไปแล้ว และสถานะที่อยู่ปัจจุบัน
    let finishedItems = [];
    let itemsStatus = {}; 
    try {
        let rec = (typeof data.received_item_list === 'string') ? JSON.parse(data.received_item_list) : data.received_item_list;
        finishedItems = rec.finished_items || []; 
        itemsStatus = rec.items_status || {}; // 👈 ดึงสถานะที่อยู่สินค้ามาเก็บไว้
    } catch(e) {}

    // --- 📦 ส่วนการสกัดรายชื่อสินค้า (Clean Name Only) ---
    let finalItemsSet = new Set(); 

    const extractNameOnly = (val) => {
        if (!val) return;
        let str = (typeof val === 'string') ? val.trim() : JSON.stringify(val);
        if (str === "" || str === "-" || str === "null") return;

        if (str.startsWith('[') || str.startsWith('{')) {
            try {
                let p = JSON.parse(str);
                recursiveFind(p); 
                return;
            } catch(e) {}
        }

        let parts = str.split(/[\r\n]+/); 
        parts.forEach(pt => {
            let v = pt.trim();
            if (v.includes(':')) v = v.split(':')[0].trim();
            v = v.replace(/^\d+\.\s*/, ''); 
            if (v.length > 1 && isNaN(v) && !v.includes('undefined')) {
                v = v.replace(/^\[+|\]+$/g, ''); 
                finalItemsSet.add(v.trim());
            }
        });
    };

    const recursiveFind = (obj) => {
        if (!obj || typeof obj !== 'object') return;
        if (Array.isArray(obj)) {
            obj.forEach(item => recursiveFind(item));
            return;
        }
        if (obj.product) extractNameOnly(obj.product);
        if (obj.items) recursiveFind(obj.items);
        if (obj.accumulated_moved) recursiveFind(obj.accumulated_moved);
        if (obj.issue) extractNameOnly(obj.issue);
        if (obj.problem) extractNameOnly(obj.problem);
        if (obj.issue_description) extractNameOnly(obj.issue_description);
    };

    recursiveFind(data);
    if (data.received_item_list && typeof data.received_item_list === 'string') {
        try { recursiveFind(JSON.parse(data.received_item_list)); } catch(e){}
    }

    let allItems = Array.from(finalItemsSet);
    // --- จบกระบวนการสกัดชื่อ ---

    const isCompleted = (data.status === 'completed');

    // Timeline Log
    let reqDate = new Date(data.request_date);
    let dateStr = isNaN(reqDate.getTime()) ? '-' : 
        ("0" + reqDate.getDate()).slice(-2) + "/" + ("0" + (reqDate.getMonth() + 1)).slice(-2) + "/" + reqDate.getFullYear() + " " + ("0" + reqDate.getHours()).slice(-2) + ":" + ("0" + reqDate.getMinutes()).slice(-2);
    logs.unshift({ msg: "รับเรื่องแจ้งซ่อม (เข้าระบบ)", by: data.receiver_by || 'System', at: dateStr, is_system: true });

    let logHtml = '';
    logs.forEach((l, index) => {
        let dotClass = (index === 0) ? 'background:#10b981; border-color:#d1fae5;' : 'background:#3b82f6; border-color:#dbeafe;';
        if (index === logs.length - 1 && logs.length > 1) dotClass = 'background:#f59e0b; border-color:#fef3c7;';
        logHtml += `
            <div class="timeline-item">
                <div class="timeline-marker" style="${dotClass}"></div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-user"><i class="fas fa-user-circle"></i> ${l.by}</span>
                        <span class="timeline-time"><i class="far fa-clock"></i> ${l.at}</span>
                    </div>
                    <div class="timeline-body">${l.msg}</div>
                </div>
            </div>`;
    });

    // Dropdown ช่าง
    let listItemsHtml = '';
    if (!isCompleted && typeof allEmployeeList !== 'undefined') {
        allEmployeeList.forEach(name => {
            listItemsHtml += `<div class="dropdown-item" onclick="selectTech('${name}')" style="padding: 10px 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer;">${name}</div>`;
        });
    }

    // 🔥 เตรียมเนื้อหา Content
    let contentBody = '';
    if (isCompleted) {
        contentBody = `<div style="background:#ecfdf5; border:1px solid #10b981; border-radius:12px; padding:20px; text-align:center; margin-bottom:20px;"><i class="fas fa-check-circle" style="font-size:3rem; color:#10b981;"></i><h3 style="color:#065f46;">ดำเนินการเสร็จสิ้นแล้ว</h3></div>`;
    } else {
        let itemsSection = '';
        if (allItems.length > 0) {
            let chkList = '';
            allItems.forEach((item) => {
                const isDone = finishedItems.includes(item); // 1. เช็คว่าเสร็จหรือยัง

                // 🔥 [เพิ่มใหม่] 2. เช็คสถานะที่อยู่ปัจจุบัน
                let currentStat = itemsStatus[item] || '';
                let isAtExternal = (currentStat === 'at_external'); // อยู่ร้านนอก
                let isAtOffice = (currentStat.includes('at_office') || currentStat === 'back_from_shop'); // อยู่บริษัท
                
                // 🔥 [เพิ่มใหม่] 3. เงื่อนไขการล็อก (ถ้าจบงาน หรือ ย้ายออกจากหน้างานแล้ว = ติ๊กไม่ได้)
                let isLocked = isDone || isAtExternal || isAtOffice;

                // 🔥 [เพิ่มใหม่] 4. สร้าง Badge
                let statusBadge = "";
                if (isAtExternal) {
                    statusBadge = `<span style="font-size:0.7rem; background:#fff7ed; color:#f97316; border:1px solid #fdba74; padding:1px 6px; border-radius:4px; margin-left:5px;"><i class="fas fa-store"></i> อยู่ร้านนอก</span>`;
                } else if (isAtOffice) {
                    statusBadge = `<span style="font-size:0.7rem; background:#f0f9ff; color:#0ea5e9; border:1px solid #7dd3fc; padding:1px 6px; border-radius:4px; margin-left:5px;"><i class="fas fa-building"></i> อยู่บริษัท</span>`;
                }
                
                chkList += `
                <label style="display:flex; align-items:center; gap:10px; cursor:${isLocked ? 'default' : 'pointer'}; background:${isDone ? '#f0fdf4' : '#fff'}; padding:10px 12px; border-radius:8px; border:1px solid ${isDone ? '#4ade80' : '#e2e8f0'}; margin-bottom:0; opacity: ${isLocked ? '0.85' : '1'};">
                    <input type="checkbox" class="completed-item-chk" value="${item}" 
                        ${isDone ? 'checked' : ''} 
                        ${isLocked ? 'disabled' : ''} 
                        style="width:18px; height:18px; accent-color:#10b981; cursor:${isLocked ? 'default' : 'pointer'};">
                    <div style="flex-grow:1; text-align:left;">
                        <span style="font-size:0.95rem; color:${isDone ? '#166534' : '#334155'}; flex-grow:1; font-weight: ${isDone ? '600' : '400'};">
                            ${item} 
                        </span>
                        ${statusBadge} 
                        ${isDone ? '<span style="font-size:0.7rem; background:#10b981; color:#fff; padding:2px 8px; border-radius:10px; margin-left:8px; vertical-align:middle;">เสร็จสิ้น</span>' : ''}
                    </div>
                </label>`;
            });

            itemsSection = `
            <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:15px; margin-bottom:15px; text-align:left;">
                <label style="font-size:0.85rem; font-weight:700; color:#64748b; margin-bottom:10px; display:block;">
                    <i class="fas fa-tasks" style="color:#f59e0b;"></i> รายการสินค้า (รายการที่เป็นสีเขียวคือจบงานแล้ว)
                </label>
                <div style="display:flex; flex-direction:column; gap:8px; max-height:220px; overflow-y:auto;">${chkList}</div>
            </div>`;
        } else {
            itemsSection = `<div style="text-align:center; color:#94a3b8; padding:15px; border:1px dashed #e2e8f0; border-radius:8px; margin-bottom:15px;">ไม่พบรายการสินค้า</div>`;
        }

        contentBody = `
            ${itemsSection}
            <div style="margin-bottom: 15px; text-align:left;">
                <label style="font-size: 0.9rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; display: block;">
                    <i class="fas fa-pen"></i> บันทึกรายละเอียดงาน:
                </label>
                <textarea id="up_msg" class="modern-textarea" placeholder="ระบุสิ่งที่ดำเนินการไป..."></textarea>
            </div>
            <div style="background: #f0fdf4; padding: 15px; border-radius: 12px; border: 1px solid #dcfce7; text-align:left;">
                <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="chk_tech" style="width: 16px; height: 16px; cursor: pointer;">
                    <label for="chk_tech" style="font-weight: 600; color: #166534; cursor: pointer;">มอบหมายช่างเพิ่ม</label>
                </div>
                <div id="tech_wrapper" style="display:none; position: relative; width: 100%;">
                    <input type="text" id="tech_input" class="modern-select" placeholder="-- ค้นหาชื่อช่าง --" value="${data.technician_name || ''}" style="width: 100%;">
                    <div id="tech_dropdown" style="position: absolute; top: 100%; left: 0; width: 100%; max-height: 180px; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; z-index: 9999; display: none;">${listItemsHtml}</div>
                </div>
            </div>
        `;
    }

    Swal.fire({
        title: '',
        html: `<div style="padding: 5px;"><div class="modal-modern-header"><div class="modal-title-text">${isCompleted ? 'สรุปงานซ่อม' : '<i class="fas fa-edit" style="color:#3b82f6;"></i> อัปเดตความคืบหน้า'}</div></div><div class="history-timeline-container"><div class="timeline-list">${logHtml}</div></div>${contentBody}</div>`,
        width: '600px',
        padding: '0',
        showCancelButton: true,
        cancelButtonText: 'ปิด',
        showConfirmButton: !isCompleted,
        confirmButtonText: '<i class="fas fa-save"></i> บันทึกความคืบหน้า',
        confirmButtonColor: '#3b82f6',
        showDenyButton: !isCompleted,
        denyButtonText: '<i class="fas fa-check-circle"></i> เสร็จสิ้นรายการที่เลือก',
        denyButtonColor: '#10b981',

        didOpen: () => {
            if (isCompleted) return;
            const container = Swal.getPopup().querySelector('.history-timeline-container');
            if (container) container.scrollTop = container.scrollHeight;
            const chk = Swal.getPopup().querySelector('#chk_tech');
            const wrapper = Swal.getPopup().querySelector('#tech_wrapper');
            const input = Swal.getPopup().querySelector('#tech_input');
            const dropdown = Swal.getPopup().querySelector('#tech_dropdown');
            const items = Swal.getPopup().querySelectorAll('.dropdown-item');
            
            if(chk) chk.addEventListener('change', () => { wrapper.style.display = chk.checked ? 'block' : 'none'; });
            
            if(input) {
                input.addEventListener('input', function () {
                    const val = this.value.toLowerCase();
                    let hasMatch = false;
                    dropdown.style.display = 'block';
                    items.forEach(item => {
                        if (item.textContent.toLowerCase().includes(val)) { item.style.display = 'block'; hasMatch = true; }
                        else { item.style.display = 'none'; }
                    });
                    if (!hasMatch) dropdown.style.display = 'none';
                });
                input.addEventListener('focus', () => { dropdown.style.display = 'block'; });
                input.addEventListener('blur', () => { setTimeout(() => { dropdown.style.display = 'none'; }, 200); });
            }
            window.selectTech = function (name) { if (input) input.value = name; if (dropdown) dropdown.style.display = 'none'; };
        },

        preConfirm: () => {
            const msg = Swal.getPopup().querySelector('#up_msg').value.trim();
            const isChecked = Swal.getPopup().querySelector('#chk_tech').checked;
            const techVal = Swal.getPopup().querySelector('#tech_input').value.trim();
            
            let selectedItems = [];
            Swal.getPopup().querySelectorAll('.completed-item-chk:checked:not(:disabled)').forEach(c => selectedItems.push(c.value));

            if (!msg && !isChecked && selectedItems.length === 0) {
                Swal.showValidationMessage('กรุณากรอกข้อมูลหรือเลือกสินค้า');
                return false;
            }
            return { actionType: 'update', msg: msg, tech: isChecked ? techVal : '', items: selectedItems };
        },

        preDeny: () => {
            const msg = Swal.getPopup().querySelector('#up_msg').value.trim();
            const isChecked = Swal.getPopup().querySelector('#chk_tech').checked;
            const techVal = Swal.getPopup().querySelector('#tech_input').value.trim();
            
            let selectedItems = [];
            Swal.getPopup().querySelectorAll('.completed-item-chk:checked:not(:disabled)').forEach(c => selectedItems.push(c.value));

            if (selectedItems.length === 0) {
                Swal.showValidationMessage('กรุณาเลือกรายการสินค้าที่ทำเสร็จแล้ว');
                return false;
            }
            return { actionType: 'finish', msg: msg, tech: isChecked ? techVal : '', items: selectedItems };
        }
    }).then((res) => {
        if (!isCompleted) {
            let dataToSend = res.isConfirmed ? res.value : (res.isDenied ? res.value : null);
            if (dataToSend) {
                $.post('service_dashboard.php', {
                    action: 'update_progress',
                    req_id: data.id,
                    update_msg: dataToSend.msg,
                    technician_name: dataToSend.tech,
                    completed_items: dataToSend.items,
                    action_type: dataToSend.actionType 
                }, function (response) {
                    location.reload();
                });
            }
        }
    });
}
        function updateData() {
            // 1. แสดง Loading (เลือกทำหรือไม่ก็ได้)
            const btn = document.querySelector('.btn-search-solid');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> โหลด...';
            btn.disabled = true;

            // 2. เก็บค่าจากฟอร์ม
            const formData = new FormData(document.getElementById('filterForm'));
            const params = new URLSearchParams(formData);

            // 3. ยิง Request ไปที่ไฟล์เดิม
            fetch(`service_dashboard.php?${params.toString()}`)
                .then(response => response.text())
                .then(html => {
                    // 4. แปลง Text เป็น HTML เพื่อดึงเฉพาะส่วนที่ต้องการ
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // 5. อัปเดต Grid 4 เสา (ยอดตัวเลข)
                    const newGrid = doc.getElementById('dashboard-grid');
                    if (newGrid) {
                        document.getElementById('dashboard-grid').innerHTML = newGrid.innerHTML;
                    }

                    // 6. อัปเดตตารางรายการ (Table)
                    const newTable = doc.getElementById('data-table');
                    if (newTable) {
                        document.getElementById('data-table').innerHTML = newTable.innerHTML;
                    }

                    // 7. อัปเดต URL บน Browser (เผื่อกด Refresh แล้วค่าไม่หาย)
                    window.history.pushState({}, '', `service_dashboard.php?${params.toString()}`);

                    // 8. รันฟังก์ชันนับเวลาถอยหลังใหม่ (เพราะ HTML เปลี่ยนไปแล้ว)
                    updateSLACountdown();
                })
                .catch(err => console.error('Error loading data:', err))
                .finally(() => {
                    // คืนค่าปุ่ม
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }
        function filterSLA(type) {
            // 1. ใส่ค่าลงใน Hidden Input
            document.getElementById('sla_input').value = type;

            // 2. เรียกโหลดข้อมูลใหม่ (AJAX)
            updateData();
        }
        // ฟังก์ชันสำหรับเปิด Popup อนุมัติค่าใช้จ่าย
        
        function showRatingHistory() {
            Swal.fire({
                title: '<div style="color:#7c3aed; font-weight:800;"><i class="fas fa-star"></i> เสียงตอบรับจากลูกค้า</div>',
                html: '<div id="rating-content" style="padding:20px;"><i class="fas fa-circle-notch fa-spin fa-2x" style="color:#7c3aed;"></i></div>',
                width: '600px',
                showConfirmButton: false,
                showCloseButton: true,
                customClass: { popup: 'rounded-24' },
                didOpen: () => {
                    $.ajax({
                        url: 'service_dashboard.php',
                        type: 'POST',
                        data: { action: 'get_rating_history' },
                        dataType: 'json',
                        success: function (res) {
                            console.log("Data from server:", res); // 🔥 เช็คใน Console (F12) ว่าข้อมูลมาไหม
                            let html = '<div style="text-align:left; max-height:450px; overflow-y:auto; padding-right:10px;">';

                            if (Array.isArray(res) && res.length > 0) {
                                res.forEach(r => {
                                    let stars = '';
                                    for (let i = 1; i <= 5; i++) {
                                        stars += `<i class="fas fa-star" style="color:${i <= r.rating ? '#f59e0b' : '#e5e7eb'}; font-size:0.85rem; margin-right:2px;"></i>`;
                                    }
                                    html += `
                            <div style="background:#fff; border:1px solid #f1f5f9; border-radius:16px; padding:15px; margin-bottom:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.02);">
                                <div style="display:flex; justify-content:space-between;">
                                    <div>
                                        <div style="font-weight:800; color:#1e293b;">${r.site_id}</div>
                                        <div style="font-size:0.85rem; color:#64748b; margin-bottom:5px;">${r.project_name}</div>
                                        <div>${stars}</div>
                                    </div>
                                    <div style="font-size:0.75rem; color:#94a3b8;">${r.at}</div>
                                </div>
                                <div style="background:#f8fafc; padding:10px; border-radius:10px; color:#475569; font-size:0.9rem; margin-top:10px; border-left:4px solid #ddd6fe;">
                                    "${r.comment}"
                                </div>
                            </div>`;
                                });
                            } else {
                                html += '<div style="text-align:center; padding:30px; color:#94a3b8;">ไม่พบประวัติการประเมินในขณะนี้</div>';
                            }
                            html += '</div>';
                            $('#rating-content').html(html);
                        },
                        error: function (xhr) {
                            console.error("AJAX Error:", xhr.responseText); // 🔥 ถ้า Error จะโชว์ตรงนี้ว่าติดอะไร
                            $('#rating-content').html('<div style="color:red; text-align:center;">เกิดข้อผิดพลาดในการดึงข้อมูล</div>');
                        }
                    });
                }
            });
        }
        // ฟังก์ชันดูรายละเอียดค่าใช้จ่าย (แบบดูอย่างเดียว)
        function viewApprovedCost(detailText) {
            // แปลง \n เป็น <br> เพื่อแสดงผลสวยงาม
            let formattedDetail = detailText ? detailText.replace(/\n/g, '<br>') : '- ไม่ระบุรายละเอียด -';

            Swal.fire({
                title: '',
                html: `
                    <div style="padding:5px;">
                        <div class="modal-modern-header">
                            <div class="modal-title-text" style="color:#059669;">
                                <i class="fas fa-file-invoice-dollar"></i> รายละเอียดที่อนุมัติแล้ว
                            </div>
                        </div>
                        <div style="text-align:left; background:#f0fdf4; border:1px solid #bbf7d0; padding:15px; border-radius:12px; margin-top:10px; font-size:0.9rem; color:#334155; line-height:1.6;">
                            ${formattedDetail}
                        </div>
                    </div>
                `,
                width: '450px',
                confirmButtonText: 'ปิดหน้าต่าง',
                confirmButtonColor: '#64748b',
                customClass: { popup: 'rounded-20' }
            });
        }

        // ฟังก์ชันดูรายละเอียดค่าใช้จ่าย (ที่อนุมัติแล้ว) + ดูไฟล์แนบ
        function viewApprovedCost(detailText, fileName) {
            // แปลง \n เป็น <br> เพื่อแสดงผลสวยงาม
            let formattedDetail = detailText ? detailText.replace(/\n/g, '<br>') : '- ไม่ระบุรายละเอียด -';

            // สร้างปุ่มดูไฟล์ (ถ้ามีไฟล์)
            let fileBtn = "";
            if (fileName && fileName !== "") {
                fileBtn = `
            <div style="margin-top: 15px; border-top: 1px dashed #bbf7d0; padding-top: 10px;">
                <a href="uploads/repairs/${fileName}" target="_blank" 
                   style="display: flex; align-items: center; justify-content: center; gap: 8px; background: #fff; color: #059669; border: 1px solid #a7f3d0; padding: 8px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition:0.2s;">
                    <i class="fas fa-image"></i> ดูหลักฐานใบเสร็จ
                </a>
            </div>`;
            }

            Swal.fire({
                title: '',
                html: `
            <div style="padding:5px;">
                <div class="modal-modern-header">
                    <div class="modal-title-text" style="color:#059669;">
                        <i class="fas fa-file-invoice-dollar"></i> รายละเอียดที่อนุมัติแล้ว
                    </div>
                </div>
                <div style="text-align:left; background:#f0fdf4; border:1px solid #bbf7d0; padding:15px; border-radius:12px; margin-top:10px; font-size:0.9rem; color:#334155; line-height:1.6;">
                    ${formattedDetail}
                    ${fileBtn}
                </div>
            </div>
        `,
                width: '450px',
                confirmButtonText: 'ปิดหน้าต่าง',
                confirmButtonColor: '#64748b',
                customClass: { popup: 'rounded-20' }
            });
        }

// แก้ไขฟังก์ชันคำนวณเดิมให้เป็น Global ด้วย
window.calculateTotalExpense = function () {
    let total = 0;
    document.querySelectorAll('.expense-row').forEach(row => {
        let qty = parseFloat(row.querySelector('.exp-qty').value) || 0;
        let price = parseFloat(row.querySelector('.exp-price').value) || 0;
        total += (qty * price);
    });

    const display = document.getElementById('total-display');
    const hiddenInp = document.getElementById('final_total_cost');
    if (display) display.innerText = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (hiddenInp) hiddenInp.value = total;
};
// 🔥 ฟังก์ชันสรุปงานซ่อม (Version: โชว์ของเดิม + แก้ไขได้ทันที)
function openRepairSummaryModal(reqId) {
    Swal.fire({
        title: 'กำลังโหลดข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.post('service_dashboard.php', { action: 'get_latest_item_data', req_id: reqId }, function(res) {
        Swal.close();

        let data = (typeof res === 'string') ? JSON.parse(res) : res;
        let itemsStatus = data.items_status || {};
        let currentSummaries = data.item_repair_summaries || {};

        // 1. รวมรายชื่อสินค้า
        let allItems = data.items || [];
        if (data.items_moved && data.items_moved.length > 0) {
            let movedNames = data.items_moved.map(m => m.name);
            allItems = [...new Set([...allItems, ...movedNames])];
        }

        // 2. กรองเฉพาะของที่นำออกมาแล้ว
        let filteredItems = allItems.filter(item => {
            let status = itemsStatus[item] || '';
            return status !== '' && status !== 'pending';
        });

        if (filteredItems.length === 0) {
            return Swal.fire({
                icon: 'info',
                title: 'ไม่พบรายการสินค้า',
                text: 'ยังไม่มีสินค้าที่ถูกบันทึกนำออกจากหน้างานครับ',
                confirmButtonColor: '#3b82f6',
                confirmButtonText: 'ตกลง'
            });
        }

        // 3. สร้าง HTML
        let html = `
        <style>
            @keyframes slideUpFade { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
            .repair-modal-container { text-align: left; max-height: 65vh; overflow-y: auto; padding: 5px 10px; }
            .repair-modal-container::-webkit-scrollbar { width: 6px; }
            .repair-modal-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
            .repair-modal-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

            .repair-card {
                background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 12px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden;
                opacity: 0; animation: slideUpFade 0.4s ease forwards;
            }
            .repair-card.active {
                border-color: #3b82f6; background: #eff6ff; box-shadow: 0 4px 12px -2px rgba(59, 130, 246, 0.15);
            }

            .repair-header { padding: 12px 15px; display: flex; align-items: center; gap: 12px; cursor: pointer; user-select: none; }
            
            .chk-modern-wrapper { position: relative; width: 24px; height: 24px; flex-shrink: 0; }
            .chk-modern { opacity: 0; width: 0; height: 0; position: absolute; }
            .checkmark {
                position: absolute; top: 0; left: 0; height: 24px; width: 24px;
                background-color: #fff; border: 2px solid #cbd5e1; border-radius: 6px;
                transition: 0.2s; display: flex; align-items: center; justify-content: center; color: transparent;
            }
            .chk-modern:checked ~ .checkmark { background-color: #3b82f6; border-color: #3b82f6; color: #fff; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3); }

            .item-info { flex-grow: 1; display: flex; flex-direction: column; }
            .item-name { font-weight: 700; color: #1e293b; font-size: 0.95rem; }
            
            .status-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 12px; font-weight: 600; display: inline-block; width: fit-content; margin-top: 2px; }
            .badge-shop { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
            .badge-back { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
            .badge-office { background: #f0f9ff; color: #0369a1; border: 1px solid #e0f2fe; }
            .badge-saved { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; margin-left: 5px; }

            .repair-body { display: none; padding: 0 15px 15px 15px; border-top: 1px dashed #bfdbfe; margin-top: -5px; }
            .input-label { font-size: 0.75rem; color: #3b82f6; font-weight: 700; margin: 10px 0 5px 0; }
            .repair-textarea {
                width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;
                font-size: 0.9rem; background: #fff; min-height: 70px; resize: vertical; transition: 0.2s;
            }
            .repair-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        </style>

        <div class="repair-modal-container">
            <div style="font-size:0.9rem; color:#64748b; margin-bottom:15px; padding-left:5px;">
                <i class="fas fa-magic"></i> เลือกรายการสินค้าเพื่อระบุ/แก้ไขผลการซ่อม
            </div>`;

        filteredItems.forEach((item, idx) => {
            let val = currentSummaries[item] || '';
            let status = itemsStatus[item] || '';
            
            // 🔥 ตรวจสอบว่ามีข้อมูลเดิมไหม? ถ้ามีให้เปิดเลย
            let hasData = (val.trim() !== '');
            
            // Logic เลือก Badge สถานะ
            let badgeHtml = '';
            if (status === 'at_external') badgeHtml = `<span class="status-badge badge-shop"><i class="fas fa-tools"></i> อยู่ร้านซ่อม</span>`;
            else if (status === 'back_from_shop') badgeHtml = `<span class="status-badge badge-back"><i class="fas fa-undo"></i> กลับจากร้าน</span>`;
            else badgeHtml = `<span class="status-badge badge-office"><i class="fas fa-building"></i> อยู่ที่บริษัท</span>`;

            // ถ้ามีบันทึกแล้ว เพิ่ม Badge บอก
            if (hasData) {
                badgeHtml += `<span class="status-badge badge-saved"><i class="fas fa-check"></i> บันทึกแล้ว</span>`;
            }

            let delay = idx * 0.05; 

            html += `
            <div class="repair-card ${hasData ? 'active' : ''}" id="card_${idx}" style="animation-delay: ${delay}s">
                <label class="repair-header">
                    <div class="chk-modern-wrapper">
                        <input type="checkbox" class="chk-modern" id="chk_${idx}" value="${idx}" 
                            onchange="toggleRepairInput(${idx})" ${hasData ? 'checked' : ''}>
                        <div class="checkmark"><i class="fas fa-check fa-xs"></i></div>
                    </div>
                    <div class="item-info">
                        <span class="item-name">${idx + 1}. ${item}</span>
                        <div>${badgeHtml}</div>
                    </div>
                </label>
                
                <div class="repair-body" id="body_${idx}" style="${hasData ? 'display: block;' : ''}">
                    <div class="input-label"><i class="fas fa-pen"></i> รายละเอียดผลการซ่อม:</div>
                    <textarea id="text_${idx}" class="repair-textarea" placeholder="เช่น เปลี่ยนอะไหล่เรียบร้อย, ทำความสะอาดแล้ว...">${val}</textarea>
                    <input type="hidden" id="name_${idx}" value="${item}">
                </div>
            </div>`;
        });

        html += `</div>`;

        Swal.fire({
            title: '<div style="font-family:Prompt; font-weight:800; color:#1e293b;">สรุปผลการซ่อมรายชิ้น</div>',
            html: html,
            width: '550px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> บันทึกข้อมูล',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'ปิด',
            didOpen: () => {
                window.toggleRepairInput = (idx) => {
                    const isChecked = document.getElementById(`chk_${idx}`).checked;
                    const card = $(`#card_${idx}`);
                    const body = $(`#body_${idx}`);

                    if (isChecked) {
                        card.addClass('active');
                        body.slideDown(200);
                        setTimeout(() => document.getElementById(`text_${idx}`).focus(), 250);
                    } else {
                        card.removeClass('active');
                        body.slideUp(200);
                    }
                };
            },
            preConfirm: () => {
                let result = {};
                let count = 0;
                filteredItems.forEach((_, idx) => {
                    const chk = document.getElementById(`chk_${idx}`);
                    // บันทึกเฉพาะรายการที่ติ๊กอยู่
                    if (chk && chk.checked) {
                        let name = document.getElementById(`name_${idx}`).value;
                        let text = document.getElementById(`text_${idx}`).value.trim();
                        result[name] = text;
                        count++;
                    }
                });
                
                if (count === 0) {
                    Swal.showValidationMessage('กรุณาติ๊กเลือกรายการอย่างน้อย 1 รายการ');
                    return false;
                }
                return result;
            }
        }).then((res) => {
            if (res.isConfirmed) {
                Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });
                
                $.post('service_dashboard.php', {
                    action: 'save_repair_summary',
                    req_id: reqId,
                    summaries: JSON.stringify(res.value)
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกเรียบร้อย!',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('บันทึกไม่สำเร็จ', response.message || 'Error', 'error');
                    }
                }, 'json')
                .fail(function(xhr) {
                    Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                });
            }
        });

    }, 'json');
}
// 🔥 ฟังก์ชันพิจารณาค่าใช้จ่าย (Update: โชว์รายการรับกลับ + หมายเหตุ + ไฟล์แนบ)
function approveCost(reqId) {
    Swal.fire({ title: 'กำลังดึงข้อมูลบิล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.post('service_dashboard.php', { action: 'get_latest_item_data', req_id: reqId }, function(data) {
        Swal.close();
        
        let officeLog = (data && data.details && data.details.office_log) ? data.details.office_log : [];
        let moveHistory = (data && data.items_moved) ? data.items_moved : []; 

        let pendingBills = officeLog.map((log, index) => ({...log, logIndex: index}))
                                   .filter(log => log.status === 'back_from_shop' && log.approved !== true && log.approved !== 'rejected');

        if (pendingBills.length === 0) {
            return Swal.fire('ไม่พบรายการรออนุมัติ', 'รายการทั้งหมดอาจถูกจัดการไปแล้ว', 'info');
        }

        let html = `
        <style>
            .bill-list-container { display: flex; flex-direction: column; height: 60vh; text-align: left; }
            .bill-scroll-area { flex-grow: 1; overflow-y: auto; padding: 5px; padding-right: 10px; margin-bottom: 10px; }
            .bill-scroll-area::-webkit-scrollbar { width: 6px; }
            .bill-scroll-area::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
            .bill-scroll-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
            
            .bill-card { background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; margin-bottom: 15px; flex-shrink: 0; position: relative; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
            .bill-card.approved { border: 2px solid #10b981; background: #f0fdf4; }
            .bill-card.rejected { border: 2px solid #ef4444; background: #fff1f2; }
            
            .shop-header { background: #f8fafc; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: start; gap: 10px; }
            .shop-icon { width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
            .shop-info { flex-grow: 1; font-size: 0.8rem; color: #475569; }
            .shop-name { font-weight: 800; font-size: 0.95rem; color: #1e293b; display: block; margin-bottom: 2px;}
            
            /* ส่วนแสดงรายการสินค้าที่รับกลับ */
            .returned-items-box {
                margin: 10px 15px 5px 15px;
                padding: 8px;
                background: #f1f5f9;
                border-radius: 8px;
                font-size: 0.85rem;
                color: #334155;
                border: 1px dashed #cbd5e1;
            }

            .item-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; margin-top: 5px; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
            .item-table th { background: #f8fafc; color: #64748b; padding: 6px 8px; font-weight: 600; text-align: left; }
            .item-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; color: #334155; }
            .item-table tr:last-child td { border-bottom: none; }
            
            /* ส่วนหมายเหตุและไฟล์แนบ */
            .extra-info { padding: 0 15px 10px 15px; }
            .remark-box { 
                margin-top: 8px; padding: 8px; background: #fffbeb; 
                border-radius: 6px; font-size: 0.8rem; color: #92400e; 
                border: 1px solid #fcd34d; 
            }
            .file-btn {
                display: inline-flex; align-items: center; gap: 5px;
                margin-top: 8px; padding: 5px 10px;
                background: #e0e7ff; color: #4338ca;
                border-radius: 20px; text-decoration: none;
                font-size: 0.75rem; font-weight: 600;
                border: 1px solid #c7d2fe; transition: 0.2s;
            }
            .file-btn:hover { background: #c7d2fe; }

            .action-bar { padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; background: #fff; border-top: 1px dashed #e2e8f0; }
            .chk-wrapper { display: flex; align-items: center; gap: 8px; cursor: pointer; }
            .custom-chk { width: 22px; height: 22px; border: 2px solid #cbd5e1; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: transparent; transition: 0.2s; background:#fff; }
            .real-chk:checked + .custom-chk { background: #10b981; border-color: #10b981; color: #fff; }
            
            .btn-reject-modern { background: #fff; color: #ef4444; border: 1px solid #ef4444; padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 5px; }
            .btn-undo-modern { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 5px; }
            
            .reject-box { display: none; margin: 10px 15px; background: #fff; border: 1px solid #fca5a5; border-radius: 8px; padding: 10px; }
            .reject-textarea { width: 100%; border: 1px solid #fda4af; border-radius: 6px; padding: 8px; font-size: 0.9rem; min-height: 60px; }

            .total-footer { background: #1e293b; color: #fff; padding: 12px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; font-weight: 700; flex-shrink: 0; }
        </style>

        <div class="bill-list-container">
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:8px; padding-left:5px;">
                <i class="fas fa-tasks"></i> ตรวจสอบรายละเอียดสินค้าและค่าใช้จ่ายก่อนอนุมัติ
            </div>
            
            <div class="bill-scroll-area">`;

        pendingBills.forEach((bill, i) => {
            // --- Safe Mode Code ---
            let amount = 0;
            let displayShopName = 'ร้านซ่อมภายนอก';
            let sPhone = '-';
            let sContact = '-';
            let tableRows = '';
            let returnedItemsList = [];
            
            try {
                amount = parseFloat(bill.total_cost || 0);
                let rawShopName = bill.shop || '';
                displayShopName = rawShopName || 'ร้านซ่อมภายนอก';

                // ค้นหาข้อมูลร้าน
                let matchedMove = null;
                if (rawShopName && rawShopName !== 'ร้านซ่อมภายนอก') {
                    matchedMove = moveHistory.find(m => m.shop_info && m.shop_info.name === rawShopName);
                }
                if (!matchedMove) {
                    let searchItems = [];
                    if (bill.items && Array.isArray(bill.items)) searchItems = [...bill.items];
                    if (bill.expenses && Array.isArray(bill.expenses)) bill.expenses.forEach(e => { if(e.name) searchItems.push(e.name); });
                    for (let itemName of searchItems) {
                        let cleanItemName = itemName.toString().trim();
                        let found = moveHistory.slice().reverse().find(m => m.name && m.name.trim() === cleanItemName && m.destination === 'external' && m.shop_info);
                        if (found) { matchedMove = found; break; }
                    }
                }
                if (matchedMove && matchedMove.shop_info) {
                    if (!rawShopName || rawShopName === 'ร้านซ่อมภายนอก') displayShopName = matchedMove.shop_info.name || displayShopName;
                    sPhone = matchedMove.shop_info.phone || '-';
                    sContact = matchedMove.shop_info.owner || '-';
                }

                // สร้างตารางรายการค่าใช้จ่าย
                let expensesList = Array.isArray(bill.expenses) ? bill.expenses : [];
                tableRows = expensesList.map((ex, idx) => `
                    <tr>
                        <td style="width: 50%;">${idx+1}. ${ex.name || '-'}</td>
                        <td style="width: 15%; text-align: center;">${ex.qty || 0}</td>
                        <td style="width: 15%; text-align: right;">${parseFloat(ex.price || 0).toLocaleString()}</td>
                        <td style="width: 20%; text-align: right; font-weight: 700;">${parseFloat(ex.total || 0).toLocaleString()}</td>
                    </tr>
                `).join('');

                // รายการที่รับกลับ (Items Returned)
                if (Array.isArray(bill.items)) {
                    returnedItemsList = bill.items;
                }

            } catch (err) { console.error(err); }

            // 🔥 [ส่วนเสริม] สร้าง HTML สำหรับรายการรับกลับ / หมายเหตุ / ไฟล์แนบ
            let returnedItemsHtml = '';
            if (returnedItemsList.length > 0) {
                returnedItemsHtml = `
                <div class="returned-items-box">
                    <div style="font-weight:700; margin-bottom:4px; color:#475569;"><i class="fas fa-box-open"></i> รายการสินค้าที่รับกลับ:</div>
                    <ul style="margin:0; padding-left:20px; list-style-type:circle;">
                        ${returnedItemsList.map(it => `<li>${it}</li>`).join('')}
                    </ul>
                </div>`;
            }

            let remarkHtml = '';
            if (bill.remark && bill.remark.trim() !== "") {
                remarkHtml = `<div class="remark-box"><i class="fas fa-comment-dots"></i> <b>หมายเหตุ:</b> ${bill.remark}</div>`;
            }

            let fileHtml = '';
            if (bill.file) {
                fileHtml = `
                <a href="uploads/repairs/${bill.file}" target="_blank" class="file-btn">
                    <i class="fas fa-paperclip"></i> ดูหลักฐานแนบ/ใบเสร็จ
                </a>`;
            }

            html += `
            <div class="bill-card" id="card_${i}">
                <div class="shop-header">
                    <div class="shop-icon"><i class="fas fa-store"></i></div>
                    <div class="shop-info">
                        <span class="shop-name">${displayShopName}</span>
                        <div><i class="fas fa-user"></i> ติดต่อ: <span style="color:#0284c7;">${sContact}</span> | <i class="fas fa-phone"></i> โทร: <span style="color:#0284c7;">${sPhone}</span></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.7rem; color:#64748b;">ยอดสุทธิ</div>
                        <div style="font-size:1.1rem; font-weight:800; color:#db2777;">฿${amount.toLocaleString()}</div>
                    </div>
                </div>

                ${returnedItemsHtml}

                <div style="padding: 0 15px;">
                    <table class="item-table">
                        <thead><tr><th>รายการค่าใช้จ่าย</th><th style="text-align:center;">จำนวน</th><th style="text-align:right;">ราคา</th><th style="text-align:right;">รวม</th></tr></thead>
                        <tbody>${tableRows}</tbody>
                    </table>
                </div>

                <div class="extra-info">
                    ${remarkHtml}
                    ${fileHtml}
                </div>

                <div class="action-bar">
                    <label class="chk-wrapper" id="lbl_${i}">
                        <input type="checkbox" class="real-chk" id="chk_${i}" value="${i}" onchange="toggleBill(${i})">
                        <div class="custom-chk"><i class="fas fa-check"></i></div>
                        <span style="font-weight:700; color:#10b981; font-size:0.9rem;">อนุมัติรายการนี้</span>
                    </label>
                    
                    <div class="btn-reject-modern" id="btn_reject_${i}" onclick="activateReject(${i})">
                        <i class="fas fa-times-circle"></i> ไม่ผ่าน
                    </div>

                    <div class="btn-undo-modern" id="btn_undo_${i}" style="display:none;" onclick="undoReject(${i})">
                        <i class="fas fa-undo"></i> ยกเลิกการปฏิเสธ
                    </div>
                </div>
                
                <div class="reject-box" id="reject_area_${i}">
                    <div class="reject-title"><i class="fas fa-exclamation-triangle"></i> ระบุสาเหตุที่ไม่อนุมัติ:</div>
                    <textarea id="note_${i}" class="reject-textarea" placeholder="เช่น ราคาแพงเกินจริง, ไม่ได้ซ่อมรายการนี้..."></textarea>
                </div>
            </div>`;
        });

        html += `</div> <div class="total-footer">
                <span><i class="fas fa-coins"></i> ยอดอนุมัติรวมทั้งสิ้น</span>
                <span style="font-size:1.3rem; color:#4ade80;" id="sum_display">฿0</span>
            </div>
        </div>`;

        Swal.fire({
            title: '<div style="font-family:Prompt; font-weight:800;">พิจารณาอนุมัติค่าใช้จ่าย</div>',
            html: html,
            width: '600px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check-circle"></i> ยืนยันผลการพิจารณา',
            confirmButtonColor: '#10b981',
            cancelButtonText: 'ปิด',
            didOpen: () => {
                window.calculateSum = () => {
                    let sum = 0;
                    document.querySelectorAll('.real-chk:checked').forEach(chk => {
                        let idx = chk.value;
                        sum += parseFloat(pendingBills[idx].total_cost || 0);
                    });
                    document.getElementById('sum_display').innerText = '฿' + sum.toLocaleString();
                };
                window.toggleBill = (idx) => {
                    const card = document.getElementById(`card_${idx}`);
                    const chk = document.getElementById(`chk_${idx}`);
                    if (chk.checked) { card.classList.add('approved'); card.classList.remove('rejected'); }
                    else { card.classList.remove('approved'); }
                    calculateSum();
                };
                window.activateReject = (idx) => {
                    document.getElementById(`chk_${idx}`).checked = false;
                    document.getElementById(`card_${idx}`).classList.remove('approved');
                    document.getElementById(`card_${idx}`).classList.add('rejected');
                    $(`#reject_area_${idx}`).slideDown(200);
                    $(`#btn_reject_${idx}`).hide(); $(`#btn_undo_${idx}`).show();
                    $(`#lbl_${idx}`).css({'pointer-events': 'none', 'opacity': '0.5'});
                    calculateSum();
                };
                window.undoReject = (idx) => {
                    document.getElementById(`chk_${idx}`).checked = false;
                    document.getElementById(`card_${idx}`).classList.remove('approved');
                    document.getElementById(`card_${idx}`).classList.remove('rejected');
                    $(`#reject_area_${idx}`).slideUp(200);
                    $(`#btn_reject_${idx}`).show(); $(`#btn_undo_${idx}`).hide();
                    $(`#lbl_${idx}`).css({'pointer-events': 'auto', 'opacity': '1'});
                    calculateSum();
                };
            },
            preConfirm: () => {
                let decisions = [];
                let hasError = false;
                pendingBills.forEach((bill, i) => {
                    const chk = document.getElementById(`chk_${i}`);
                    const isRejected = document.getElementById(`card_${i}`).classList.contains('rejected');
                    let displayedShopName = document.querySelector(`#card_${i} .shop-name`).innerText.trim();

                    if (isRejected) {
                        let note = document.getElementById(`note_${i}`).value.trim();
                        if (!note) { Swal.showValidationMessage(`กรุณาระบุเหตุผลสำหรับร้าน: ${displayedShopName}`); hasError = true; }
                        decisions.push({ logIndex: bill.logIndex, status: 'rejected', note: note, amount: bill.total_cost, shop: displayedShopName });
                    } else if (chk.checked) {
                        decisions.push({ logIndex: bill.logIndex, status: 'approved', note: '', amount: bill.total_cost, shop: displayedShopName });
                    }
                });
                if (hasError) return false;
                if (decisions.length === 0) return Swal.showValidationMessage('กรุณาเลือกอนุมัติ หรือ ปฏิเสธ อย่างน้อย 1 รายการ');
                return decisions;
            }
        }).then(res => {
            if (res.isConfirmed) {
                $.post('service_dashboard.php', {
                    action: 'process_multi_approval',
                    req_id: reqId,
                    decisions: JSON.stringify(res.value)
                }, function(r) {
                    if(r.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'บันทึกเรียบร้อย', timer: 1000, showConfirmButton: false }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', r.message, 'error');
                    }
                }, 'json');
            }
        });
    }, 'json');
}