flatpickr(".date-picker", { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true, locale: "th" });
    
    $(document).ready(function () {
    $('.select2-search').select2({ width: '100%' });
    if (typeof calcDeadline === 'function') calcDeadline();
    $('.job-type-select').each(function () { toggleJobOtherDynamic(this); });
    if (typeof updateGlobalOptions === 'function') updateGlobalOptions();

    // ==========================================
    // 🔥 5. คืนชีพช่องทางติดต่อ (พร้อมระบบจับ Error)
    // ==========================================
    $('#contact_list_container').empty();
    let isContactLoaded = false;

    // --- เริ่มดักจับ Error ---
    try {
        console.log("🔍 ตรวจสอบข้อมูลดิบที่ได้รับจาก PHP:", existingContactsData);

        if (typeof existingContactsData !== 'undefined' && Array.isArray(existingContactsData)) {
            
            if (existingContactsData.length > 0) {
                console.log("✅ พบข้อมูลการติดต่อจำนวน", existingContactsData.length, "รายการ กำลังวาดกล่อง...");
                
                existingContactsData.forEach((contact, idx) => {
                    console.log(`➡️ กำลังวาดรายการที่ ${idx + 1}:`, contact);
                    // สั่งวาดกล่อง
                    addContactRow(contact.detail || '', contact.ext || '', contact.channel || '');
                });

                isContactLoaded = true;
                
                // อัปเดตกลับเข้า input hidden ทันที
                $('#contact_json').val(JSON.stringify(existingContactsData)); 
                console.log("✅ คืนค่าช่องทางติดต่อสำเร็จทั้งหมด!");
            } else {
                console.log("⚠️ ข้อมูลที่ส่งมาเป็น Array ว่างเปล่า (ไม่มีประวัติการติดต่อ)");
            }

        } else {
            console.error("❌ existingContactsData ไม่ได้เป็น Array หรือไม่มีตัวแปรนี้อยู่");
        }
    } catch (e) {
        // 🔴 ถ้าพัง จะเด้งแจ้งเตือนหน้าจอทันที
        console.error("💥 พบข้อผิดพลาดร้ายแรงในการสร้างกล่องติดต่อ:", e);
        
        Swal.fire({
            icon: 'error',
            title: 'พบข้อผิดพลาด (Contact Load Error)',
            html: `<b>ระบบไม่สามารถดึงข้อมูลติดต่อเดิมได้</b><br><br><b>Error:</b> <span style="color:red;">${e.message}</span>`,
            confirmButtonText: 'ตกลง'
        });
    }

    // ถ้าไม่มีข้อมูลเก่ามาเลย ให้สร้างกล่องว่างๆ รอ 1 กล่อง
    if (!isContactLoaded) {
        console.log("ℹ️ สร้างกล่องติดต่อเปล่าๆ 1 กล่อง (เพราะไม่มีข้อมูลเก่า)");
        addContactRow();
    }
});

    // 1. ฟังก์ชันเพิ่มกล่องรายการใหม่ (Main Box)
    function addServiceItemBox() {
        let currentCount = $('#service-items-container .service-item-box').length + 1;

        // 🔥 เช็คว่าตอนนี้อยู่โหมดไหน (Manual หรือ Search)
        const isManual = $('input[name="project_mode"]:checked').val() === 'manual';
        const dropProps = isManual ? 'disabled style="display:none;"' : 'required';
        const textProps = isManual ? 'required' : 'disabled style="display:none;"';

        // 🟢 สร้าง HTML String
        const html = `
            <div class="service-item-box" id="box_${itemIndex}" data-index="${itemIndex}">
                <span class="item-counter">รายการที่ ${currentCount}</span>
                <button type="button" class="btn-remove-item" onclick="removeServiceItem(this)" title="ลบรายการนี้"><i class="fas fa-trash-alt"></i></button>

                <div class="product-list-container">
                    <label class="form-label" style="font-size:0.9rem; color:var(--primary);">สินค้า / อุปกรณ์ <span style="color:var(--danger-text)">*</span></label>
                    <div class="product-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                        <div style="flex: 1; position: relative;">
                            <select name="items[${itemIndex}][product][]" class="form-control select2-search product-dropdown" style="width: 100%;" ${dropProps}>
                                ${optionsStr}
                            </select>
                            <input type="text" name="items[${itemIndex}][product][]" class="form-control product-text-input" placeholder="พิมพ์ชื่อสินค้า / อุปกรณ์..." ${textProps}>
                        </div>
                        <button type="button" onclick="removeRowAndCheck(this)" style="border:none; background:#fee2e2; color:#ef4444; width:38px; height:38px; border-radius:6px; cursor:pointer; flex-shrink: 0;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div style="text-align: right; margin-bottom: 20px;">
                    <button type="button" onclick="addProductToBox(this, ${itemIndex})" style="background:none; border:none; color:var(--accent-start); font-size:0.85rem; cursor:pointer; font-weight:600;">
                        <i class="fas fa-plus-circle"></i> เพิ่มสินค้าในรายการนี้
                    </button>
                </div>

                <div class="grid-2">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-size:0.85rem;">ประเภทงาน</label>
                        <select name="items[${itemIndex}][job_type]" class="form-control job-type-select" onchange="toggleJobOtherDynamic(this)" required>
                            ${jobOptionsHtml}
                        </select>
                        <input type="text" name="items[${itemIndex}][job_other]" class="form-control mt-2 job-other-input" style="display:none;" placeholder="ระบุประเภทอื่นๆ...">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size:0.85rem;">อาการ / ปัญหาที่พบ <span style="color:var(--danger-text)">*</span></label>
                        <textarea name="items[${itemIndex}][issue]" class="form-control" rows="2" required placeholder="ระบุอาการเสีย..." style="min-height: 80px;"></textarea>
                    </div>
                </div>

                <div class="grid-2" style="margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size:0.85rem; color:#059669;"><i class="fas fa-microscope"></i> คำแนะนำเบื้องต้น</label>
                        <textarea name="items[${itemIndex}][initial_advice]" class="form-control" rows="1" placeholder="คำแนะนำ..." style="min-height: 40px; font-size:0.9rem;"></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size:0.85rem; color:#d97706;"><i class="fas fa-clipboard-check"></i> การประเมิน</label>
                        <textarea name="items[${itemIndex}][assessment]" class="form-control" rows="1" placeholder="การประเมิน..." style="min-height: 40px; font-size:0.9rem;"></textarea>
                    </div>
                </div>
            </div>
        `;

        const newBox = $(html).appendTo('#service-items-container');
        
        // Init Select2 ให้ Box ใหม่ แล้วตรวจสอบว่าต้องซ่อนไหม
        let selectEl = newBox.find('.select2-search');
        selectEl.select2({ width: '100%' });
        if (isManual) { selectEl.next('.select2-container').hide(); }
        
        // สั่งเช็คซ้ำทันทีเพื่อให้ Box ใหม่รู้สถานะ
        updateGlobalOptions();

        itemIndex++;
    }

    // 2. ฟังก์ชันเพิ่มช่องสินค้าในกล่องเดิม
    function addProductToBox(btn, boxIdx) {
        const container = $(btn).closest('.service-item-box').find('.product-list-container');
        
        // 🔥 เช็คโหมดปัจจุบัน
        const isManual = $('input[name="project_mode"]:checked').val() === 'manual';
        const dropProps = isManual ? 'disabled style="display:none;"' : 'required';
        const textProps = isManual ? 'required' : 'disabled style="display:none;"';

        const productHtml = `
            <div class="product-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center; opacity: 0; transform: translateY(-5px); transition: all 0.3s;">
                <div style="flex: 1; position: relative;">
                    <select name="items[${boxIdx}][product][]" class="form-control select2-search product-dropdown" style="width: 100%;" ${dropProps}>
                        ${optionsStr}
                    </select>
                    <input type="text" name="items[${boxIdx}][product][]" class="form-control product-text-input" placeholder="พิมพ์ชื่อสินค้า / อุปกรณ์..." ${textProps}>
                </div>
                <button type="button" onclick="removeRowAndCheck(this)" style="border:none; background:#fee2e2; color:#ef4444; width:38px; height:38px; border-radius:6px; cursor:pointer; flex-shrink: 0;">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

        const newRow = $(productHtml).appendTo(container);
        
        // Init Select2
        let selectEl = newRow.find('.select2-search');
        selectEl.select2({ width: '100%' });
        if (isManual) { selectEl.next('.select2-container').hide(); }

        // Animation
        setTimeout(() => { newRow.css({ opacity: 1, transform: 'translateY(0)' }); }, 10);

        // 🔥 สั่งเช็คซ้ำทันที
        updateGlobalOptions();
    }

    // ฟังก์ชันลบ Box ใหญ่
    function removeServiceItem(btn) {
        $(btn).closest('.service-item-box').fadeOut(200, function () {
            $(this).remove();
            updateItemCounters();
            updateGlobalOptions(); // คืนค่าสินค้ากลับสู่ระบบ
        });
    }

    // ฟังก์ชันลบแถวสินค้า (Row)
    function removeRowAndCheck(btn) {
        $(btn).closest('.product-row').remove();
        updateGlobalOptions(); // คืนค่าสินค้ากลับสู่ระบบทันที
    }

    // ฟังก์ชันอัปเดตตัวนับ
    function updateItemCounters() {
        $('#service-items-container .service-item-box').each(function (index) {
            $(this).find('.item-counter').text('รายการที่ ' + (index + 1));
        });
    }

    // Toggle ช่องกรอกประเภทงานอื่นๆ
    function toggleJobOtherDynamic(selectObj) {
        const box = $(selectObj).closest('.form-group');
        const input = box.find('.job-other-input');
        if (selectObj.value === 'other') { input.slideDown(200).attr('required', true); }
        else { input.slideUp(200).attr('required', false).val(''); }
    }

    function calcDeadline() {
        let d = document.getElementById('request_date');
        if (d && d.value) {
            let reqDate = new Date(d.value);
            reqDate.setHours(reqDate.getHours() + 48);
            let day = String(reqDate.getDate()).padStart(2, '0');
            let month = String(reqDate.getMonth() + 1).padStart(2, '0');
            let year = reqDate.getFullYear();
            let time = String(reqDate.getHours()).padStart(2, '0') + ':' + String(reqDate.getMinutes()).padStart(2, '0');
            let display = document.getElementById('deadline_display');
            if (display) { display.innerHTML = `<i class="fas fa-history"></i> ต้องปิดงานภายใน: <strong>${day}/${month}/${year} เวลา ${time} น.</strong>`; }
        }
    }

    // ==========================================
    // 🔥 CORE LOGIC: เช็คสินค้าซ้ำ (Global Check)
    // ==========================================

    // Event Listener: ทำงานเมื่อมีการเปลี่ยนแปลง หรือ กดเปิด Dropdown
    $(document).on('change select2:open', '.select2-search', function() {
        updateGlobalOptions();
    });

    function updateGlobalOptions() {
        var allSelectedValues = [];

        // 1. วิ่งเก็บค่าที่ถูกเลือกจาก "ทุก Box" ทั่วหน้าเว็บ
        $('.select2-search').each(function() {
            var val = $(this).val();
            if (val && val !== "") {
                allSelectedValues.push(val);
            }
        });

        // 2. วิ่งไปปิด (Disable) ตัวเลือกที่ซ้ำใน "ทุก Box"
        $('.select2-search').each(function() {
            var currentDropdown = $(this);
            var myCurrentValue = currentDropdown.val(); // ค่าที่ตัวเองเลือกอยู่ (ห้ามปิด)

            currentDropdown.find('option').each(function() {
                var optVal = $(this).val();

                // ถ้าค่านี้นี้ถูกเลือกไปแล้ว (ใน Box ไหนก็ได้) AND ไม่ใช่ค่าของตัวเอง
                if (optVal && allSelectedValues.includes(optVal) && optVal !== myCurrentValue) {
                    $(this).prop('disabled', true); // ❌ ปิดการใช้งาน
                } else {
                    $(this).prop('disabled', false); // ✅ เปิดให้เลือกได้
                }
            });
            
            // Re-render Select2 (เผื่อบางเวอร์ชันไม่อัปเดต UI เอง)
            // if (currentDropdown.hasClass('select2-hidden-accessible')) { /* currentDropdown.select2(); */ }
        });
    }

    // ---- Contact Row Logic (ส่วนเดิมของคุณ) ----
    

    function addContactRow(initialVal = '', initialExt = '', initialChannel = '') {
        const rowId = 'row_' + Math.floor(Math.random() * 1000000); 
        
        let optionsHtml = channelConfigs.map(c => `
            <option value="${c.channel_name}" 
                data-type="${c.channel_type}" 
                data-placeholder="${c.placeholder_text}"
                data-has-ext="${c.has_ext}" 
                data-is-tel="${c.is_tel}"
                ${initialChannel === c.channel_name ? 'selected' : ''}>
            ${c.channel_name}
            </option>
        `).join('');

        const rowHtml = `
            <div class="contact-row" id="${rowId}" style="display: flex; gap: 8px; margin-bottom: 10px; align-items: center; background: #f8fafc; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="flex: 1;">
                    <select class="form-control sel-channel" onchange="updateRowLogic('${rowId}')" required>
                        <option value="">-- ช่องทาง --</option>
                        ${optionsHtml}
                    </select>
                </div>
                <div style="flex: 2; display: flex; gap: 5px; align-items: center;">
                    <input type="text" class="form-control inp-detail" placeholder="ระบุข้อมูล..." value="${initialVal}" required style="flex: 1;">
                    <div class="ext-box" style="display: none; width: 100px; position: relative;">
                        <span style="position: absolute; left: -5px; top: 10px; font-size: 0.7rem; font-weight: bold; color: #64748b;"></span>
                        <input type="text" class="form-control inp-ext" placeholder="เลขต่อ" value="${initialExt}" style="text-align: center; padding-left: 20px;">
                    </div>
                </div>
                <button type="button" onclick="removeContactRow(this)" style="background: #fee2e2; color: #ef4444; border: none; width: 35px; height: 35px; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        $('#contact_list_container').append(rowHtml);
        updateRowLogic(rowId);
    }

    function removeContactRow(btn) {
        $(btn).closest('.contact-row').remove();
    }

    function updateRowLogic(rowId) {
        const row = $('#' + rowId); 
        const sel = row.find('.sel-channel')[0];
        if (!sel || sel.selectedIndex === -1) return;

        const opt = sel.options[sel.selectedIndex];
        const inp = row.find('.inp-detail');
        const extBox = row.find('.ext-box');

        if (sel.value !== "") {
            inp.attr('placeholder', opt.getAttribute('data-placeholder'));
            
            if (opt.getAttribute('data-is-tel') === '1') {
                inp.attr('type', 'tel').attr('maxlength', '10').attr('oninput', "this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)");
            } else {
                inp.attr('type', 'text').removeAttr('maxlength').removeAttr('oninput');
            }
            
            opt.getAttribute('data-has-ext') === '1' ? extBox.show() : extBox.hide();
        }
    }

    // Submit Logic
    $('#serviceForm').on('submit', function() {
        let contacts = [];
        $('.contact-row').each(function() {
            if($(this).find('.sel-channel').val()) {
                contacts.push({
                    channel: $(this).find('.sel-channel').val(),
                    detail: $(this).find('.inp-detail').val(),
                    ext: $(this).find('.inp-ext').val()
                });
            }
        });
        $('#contact_json').val(JSON.stringify(contacts));
    });
    function toggleProjectMode(mode) {
        const inputIds = [
            'inp_site_code', 'inp_contract_no', 'inp_budget', 
            'inp_project_name', 'inp_customer_name', 'inp_province', 
            'inp_start_date', 'inp_end_date', 'device_name', 'site_id'
        ];
        
        const searchSection = document.getElementById('search-section');
        const realSiteId = document.getElementById('real_site_id');
        const reqMarkProj = document.getElementById('req_proj_name');
        const reqMarkCust = document.getElementById('req_cust_name');

        if (mode === 'manual') {
            searchSection.style.display = 'none'; 
            realSiteId.value = '0'; 
            reqMarkProj.style.display = 'inline'; 
            reqMarkCust.style.display = 'inline';

            inputIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.readOnly = false; 
                    el.classList.remove('readonly-field'); 
                    if(el.value === '-') el.value = '';
                    if(id.includes('date')) {
                        el.classList.add('date-picker');
                        if (typeof flatpickr === 'function') flatpickr(el, { locale: "th", dateFormat: "d/m/Y" });
                    }
                }
            });

            // 🔥 โหมด Manual: ซ่อน Dropdown (ปิดการใช้งาน) แล้วโชว์ช่องพิมพ์ (เปิดการใช้งาน)
            $('.product-dropdown').prop('disabled', true).next('.select2-container').hide();
            $('.product-text-input').prop('disabled', false).show();

        } else {
            searchSection.style.display = 'block';
            reqMarkProj.style.display = 'none';
            reqMarkCust.style.display = 'none';

            inputIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.readOnly = true; 
                    el.classList.add('readonly-field'); 
                }
            });

            // 🔥 โหมด Search: เปิด Dropdown แล้วซ่อนช่องพิมพ์
            $('.product-dropdown').prop('disabled', false).next('.select2-container').show();
            $('.product-text-input').prop('disabled', true).hide();
        }
    }
    
    // Auto check on load
    document.addEventListener('DOMContentLoaded', () => {
        const manualRadio = document.querySelector('input[name="project_mode"][value="manual"]');
        if(manualRadio && manualRadio.checked) toggleProjectMode('manual');
    });

    // ตรวจสอบตอนโหลดหน้า
    document.addEventListener('DOMContentLoaded', () => {
        const manualRadio = document.querySelector('input[name="project_mode"][value="manual"]');
        // ถ้า Radio Manual ถูกติ๊กอยู่ (เช่น กรณี Validation Error แล้วเด้งกลับมา) ให้เข้าโหมด Manual
        if(manualRadio && manualRadio.checked) {
            toggleProjectMode('manual');
        }
    });