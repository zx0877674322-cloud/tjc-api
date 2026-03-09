let workBoxCount = 1;

document.addEventListener('DOMContentLoaded', async function() {
    // 1. Init ปฏิทิน (เหมือนเดิม ยกเว้นวันที่รายงานถูกล็อคแล้ว)
    // initFlatpickr("#reportDateDisplay", "#reportDateHidden");
    initFlatpickr(".next-appt", null);

    // 2. Load Job Status (เหมือนเดิม)
    await loadJobStatus(document.querySelector('#work-box-1 .job-status-select'));

    // ✅ 3. เปิดใช้งาน Autocomplete ให้กล่องแรกทันที
    setupAutocomplete(document.querySelector('#work-box-1 .customer-input'), customerList);
    if (typeof isEditMode !== 'undefined' && isEditMode === true && existingWorkData) {
        loadExistingData();
    }
});
function loadExistingData() {
    console.log("Loading existing data...");
    try {
        // 1. จัดการข้อมูล Header (GPS, จังหวัด ฯลฯ)
        // หมายเหตุ: ตัวแปร editData ต้องถูกส่งมาจาก PHP ผ่าน json_encode
        if (typeof editData !== 'undefined' && editData) {
        const workType = editData.gps && editData.gps !== 'Office' ? 'outside' : 'company';
        const radioInp = document.querySelector(`input[name="work_type"][value="${workType}"]`);
        if (radioInp) {
            radioInp.checked = true;
            toggleWorkMode(workType);
        }

        if (workType === 'outside') {
            const gpsInput = document.getElementById("gpsInput");
            if (gpsInput) gpsInput.value = editData.gps || '';
            const areaSelect = document.getElementById("areaSelect");
            if (areaSelect) {
                areaSelect.value = editData.area || '';
                updateProvinces(); 
                setTimeout(() => {
                    const provinceSelect = document.getElementById("provinceSelect");
                    if (provinceSelect) provinceSelect.value = editData.province || '';
                }, 100);
            }
        }
    }

    // 2. จัดการกล่องงาน (Work Boxes)
    const customers = existingWorkData.customers || [];
    const projects = existingWorkData.projects || [];
    const statuses = existingWorkData.statuses || [];
    const nextApps = existingWorkData.next_apps || [];
    const summaries = existingWorkData.summaries || []; // ต้องส่งมาจาก PHP ด้วย
    const notes = existingWorkData.notes || [];

    // วนลูปสร้างกล่องงานตามจำนวนข้อมูลจริง
    customers.forEach((cus, idx) => {
        if (idx > 0) addWorkBox(); // ถ้ามีงานมากกว่า 1 ให้สร้างกล่องเพิ่ม

        const box = document.getElementById(`work-box-${idx + 1}`);
        if (box) {
            // ยัดข้อมูลลงช่องต่างๆ
            box.querySelector('.customer-input').value = cus;
            
            // แยกข้อมูลโครงการ (กรณีมีการเก็บ มูลค่า: xxx บาท ต่อท้าย)
            let pjFull = projects[idx] || '';
            let pjName = pjFull.replace(/\(มูลค่า:.*บาท\)/g, "").trim();
            let pjVal = "";
            if (pjFull.match(/มูลค่า:\s*([\d,.]+)/)) {
                pjVal = pjFull.match(/มูลค่า:\s*([\d,.]+)/)[1];
            }

            box.querySelector('input[name="project_name[]"]').value = pjName;
            box.querySelector('input[name="project_value[]"]').value = pjVal;
            box.querySelector('.job-status-select').value = statuses[idx] || '';
            box.querySelector('.next-appt').value = nextApps[idx] || '';
            box.querySelector('textarea[name="visit_summary[]"]').value = (summaries[idx] || '').replace(/^•\s.*:\s/, "");
            box.querySelector('textarea[name="additional_notes[]"]').value = notes[idx] || '';
            
            // เรียกเช็คประเภทลูกค้าเพื่อติ๊ก Radio เก่า/ใหม่ อัตโนมัติ
            checkCustomerType(box.querySelector('.customer-input'));
        }
    });

    // 3. จัดการค่าใช้จ่าย (Expenses)
    if (typeof editData !== 'undefined') {
        // ค่าน้ำมัน
        if (parseFloat(editData.fuel_cost) > 0) {
            document.getElementById('fuel_check').checked = true;
            document.getElementById('row-fuel').classList.add('active');
            // กรณีน้ำมันมีหลายบิล (คั่นด้วยคอมม่า)
            const fuelParts = String(editData.fuel_cost).split(','); 
            fuelParts.forEach((val, i) => {
                if (i > 0) addFuelRow();
                const fuelInputs = document.querySelectorAll('input[name="fuel_cost[]"]');
                fuelInputs[i].value = val.trim();
            });
        }
        
        // ค่าที่พัก
        if (parseFloat(editData.accommodation_cost) > 0) {
            const chk = document.querySelector('#row-hotel input[type="checkbox"]');
            chk.checked = true;
            toggleOneExpense('hotel_input', 'row-hotel');
            document.getElementById('hotel_input').value = editData.accommodation_cost;
        }

        // ค่าอื่นๆ
        if (parseFloat(editData.other_cost) > 0) {
            const chk = document.querySelector('#row-other input[type="checkbox"]');
            chk.checked = true;
            toggleOneExpense('other_input', 'row-other');
            document.getElementById('other_input').value = editData.other_cost;
            document.querySelector('input[name="other_cost_detail"]').value = editData.other_cost_detail;
        }
        
        // สรุปยอดรวมเบื้องต้น
        calculateTotal();

        // ปัญหาและข้อเสนอแนะ
        document.querySelector('textarea[name="problem"]').value = editData.problem || '';
        }
    } catch (e) {
        console.error("Error in loadExistingData:", e);
        alert("เกิดข้อผิดพลาดในการโหลดข้อมูลเก่า กรุณาติดต่อผู้พัฒนา: \n" + e.message + "\n\n" + e.stack);
    }
}

// ฟังก์ชันเพิ่มกล่องงาน
function addWorkBox() {
    // 1. หา Container
    const container = document.getElementById('work-container');
    
    // 2. Clone กล่องต้นแบบ (กล่องที่ 1)
    const template = document.getElementById('work-box-1').cloneNode(true);
    
    // 3. เคลียร์ค่า input/select/textarea ในกล่องใหม่ให้ว่าง
    template.querySelectorAll('input:not([type="radio"]), textarea, select').forEach(input => {
        input.value = '';
    });
    
    // 4. เคลียร์ Flatpickr เก่าที่ติดมา (ไม่งั้นกดปฏิทินไม่ติด)
    const oldDateInput = template.querySelector('.next-appt');
    if (oldDateInput._flatpickr) {
        oldDateInput._flatpickr.destroy();
        oldDateInput.value = ''; // เคลียร์ค่าวันที่
    }

    // 5. ล้าง autocomplete ที่ติดมา
    const oldAutocomplete = template.querySelector('.autocomplete-items');
    if (oldAutocomplete) oldAutocomplete.innerHTML = '';

    // 6. เพิ่มปุ่ม "ลบ" (Delete Button)
    // เช็คก่อนว่ามีปุ่มลบหรือยัง (กล่อง 1 ไม่มี แต่ถ้า clone มาจากกล่องอื่นอาจมีติดมา)
    let header = template.querySelector('.work-box-header');
    if (!header.querySelector('.btn-remove-box')) {
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn-remove-box';
        removeBtn.innerHTML = '<i class="fas fa-trash-alt"></i> ลบรายการนี้';
        
        // 🔴 เมื่อกดลบ -> ให้ลบกล่องนี้ทิ้ง แล้วเรียงเลขใหม่
        removeBtn.onclick = function() {
            template.remove();
            reorderWorkBoxes(); // 👈 เรียกฟังก์ชันเรียงเลข
        };
        header.appendChild(removeBtn);
    } else {
        // ถ้ามีปุ่มลบติดมาอยู่แล้ว (กรณี clone จากกล่อง 2,3) ให้แก้ onclick
        header.querySelector('.btn-remove-box').onclick = function() {
            template.remove();
            reorderWorkBoxes();
        };
    }

    // 7. เอาไปแปะใน Container
    container.appendChild(template);

    // 8. เรียงเลขงานใหม่ทั้งหมด (เพื่อให้งานล่าสุดได้เลขที่ถูกต้อง)
    reorderWorkBoxes();

    // 9. Re-init ระบบต่างๆ ให้กล่องใหม่
    // - สร้างปฏิทินใหม่
    initFlatpickr(template.querySelector('.next-appt'), null);
    // - เปิดใช้งาน Autocomplete
    setupAutocomplete(template.querySelector('.customer-input'), customerList);
}

// 🔄 ฟังก์ชันเรียงเลขงานใหม่ (Re-index)
function reorderWorkBoxes() {
    const allBoxes = document.querySelectorAll('.work-box');
    
    allBoxes.forEach((box, index) => {
        const workNumber = index + 1; // ลำดับที่เริ่มจาก 1
        
        // 1. อัปเดต ID ของกล่อง
        box.id = 'work-box-' + workNumber;
        
        // 2. อัปเดตหัวข้อ (งานที่ X)
        const title = box.querySelector('.work-box-title');
        title.innerHTML = `<i class="fas fa-briefcase"></i> งานที่ ${workNumber}`;
        
        // 3. อัปเดต Radio Name (เพื่อให้เลือกแยกกันได้)
        // เช่น customer_type_1, customer_type_2, ...
        const radios = box.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => {
            radio.name = 'customer_type_' + workNumber;
        });

        // 4. ตั้งค่าเริ่มต้น Radio (เฉพาะกล่องใหม่ที่ยังไม่ได้เลือก)
        // ถ้าเป็นกล่องแรก หรือกล่องที่เพิ่งเพิ่มมา ให้ Default เป็น "ลูกค้าใหม่"
        const radioNew = box.querySelector('.cust-type-new');
        const radioOld = box.querySelector('.cust-type-old');
        if (!radioNew.checked && !radioOld.checked) {
             radioNew.checked = true;
        }
    });
    
    // อัปเดตตัวแปร global (ถ้ามีใช้)
    workBoxCount = allBoxes.length;
}

// ✅ ฟังก์ชันเช็คประเภทลูกค้า (แยกแหล่งข้อมูล)
function checkCustomerType(inputElement) {
    const val = inputElement.value.trim();
    if (!val) return;

    const box = inputElement.closest('.work-box');
    if (!box) return;

    const radioOld = box.querySelector('.cust-type-old');
    const radioNew = box.querySelector('.cust-type-new');

    // 🟢 เปลี่ยนมาเช็คกับ masterCustomerList (รายชื่อจากตาราง master_customers)
    const isExisting = masterCustomerList.some(c => 
        c.trim().toLowerCase() === val.toLowerCase()
    );

    if (isExisting) {
        radioOld.checked = true; // มีชื่อในตารางหลัก = ลูกค้าเก่า
    } else {
        radioNew.checked = true; // ไม่มีชื่อในตารางหลัก = ลูกค้าใหม่
    }
}

// 🟢 (แถม) ดักจับตอนพิมพ์เองแล้วกดออก (Blur) ให้เช็คด้วย
document.addEventListener('focusout', function(e) {
    if (e.target && e.target.classList.contains('customer-input')) {
        checkCustomerType(e.target);
    }
});


// Flatpickr Helper
function initFlatpickr(selector, hiddenInputId) {
    let element = (typeof selector === 'string') ? document.querySelector(selector) : selector;
    if(!element) return;
    
    flatpickr(element, {
        dateFormat: "d/m/Y",
        locale: "th",
        minDate: (hiddenInputId ? null : "today"), // นัดหมายห้ามย้อนหลัง
        defaultDate: (hiddenInputId ? "today" : null),
        disableMobile: true,
        onChange: function(dates) {
            if (dates.length) {
                let fmt = formatDate(dates[0]);
                // ถ้ามี hidden input (เช่น วันที่รายงาน) ให้ใส่ค่า
                if(hiddenInputId) document.querySelector(hiddenInputId).value = fmt;
                else {
                    // ถ้าเป็นนัดหมาย (ใน box) ให้หา hidden field ใกล้ๆ (ถ้ามี) หรือส่งค่าเป็น Y-m-d ผ่าน value เดิม
                    // ใน PHP เราแปลง d/m/Y กลับได้ หรือจะใช้ hidden field ใน Loop ก็ได้ 
                    // เพื่อความง่าย: flatpickr ใส่ text เป็น d/m/Y, เราส่งค่านี้ไป PHP แล้ว PHP แปลงเอง
                    // หรือจะเปลี่ยน value ของ input นี้ให้เป็น Y-m-d ไปเลยตอน submit (แต่ user จะเห็นเปลี่ยน)
                    // *วิธีแก้: ใน PHP โค้ดผมไม่ได้แปลงกลับ ดังนั้น Flatpickr ควรส่งค่าที่ PHP อ่านได้ หรือ PHP ต้องแปลง
                    // เพื่อความชัวร์ ให้ Flatpickr แสดง d/m/Y แต่เก็บ value จริงเป็น Y-m-d
                    element.value = fmt; // บังคับใส่ Y-m-d ลง value (User อาจเห็น format เปลี่ยนนิดหน่อย หรือใช้ altInput)
                }
            }
        }
    });
}
// ใช้ altInput ของ flatpickr จะดีกว่าเพื่อให้ user เห็น d/m/Y แต่ value เป็น Y-m-d
// แต่แก้โค้ด JS เดิมให้ง่าย:
function formatDate(date) {
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
}

// --- API & Utility ---
async function loadJobStatus(selectElement) {
    if(!selectElement) return;
    try {
        const res = await fetch('api_data.php?action=get_job_status');
        const data = await res.json();
        data.forEach(i => {
            let opt = document.createElement("option");
            opt.value = i; opt.text = i;
            selectElement.add(opt);
        });
    } catch(e) {}
}

// --- GPS & Other Logic (คงเดิมจาก Code เก่า) ---
function toggleWorkMode(mode) {
    const panel = document.getElementById("outsideOptions");
    if(mode === 'outside') panel.classList.remove('hidden');
    else panel.classList.add('hidden');
}

function updateProvinces() {
    const areaSelect = document.getElementById("areaSelect");
    if (!areaSelect) return;
    const zone = areaSelect.value;
    const provinceSelect = document.getElementById("provinceSelect");
    if (!provinceSelect) return;
    provinceSelect.innerHTML = '<option value="">-- รอเลือกภาค --</option>';
    let list = [];
    if (zone === 'เฉพาะ จ.อุบลราชธานี') list = ['อุบลราชธานี'];
    else if (zone && provincesData[zone]) list = provincesData[zone];
    
    list.forEach(p => {
        let option = document.createElement("option");
        option.value = p; option.text = p;
        if(list.length === 1) option.selected = true;
        provinceSelect.add(option);
    });
}

function getLocation() {
    if(navigator.geolocation) {
        Swal.fire({ title: 'กำลังจับพิกัด...', didOpen: () => Swal.showLoading() });
        navigator.geolocation.getCurrentPosition(showPosition, () => Swal.fire('Error', 'เปิด GPS ก่อนนะครับ', 'error'));
    }
}
function showPosition(pos) {
    Swal.close();
    document.getElementById("gpsInput").value = pos.coords.latitude.toFixed(6) + ", " + pos.coords.longitude.toFixed(6);
    document.getElementById("googleMapLink").href = `http://maps.google.com/?q=${pos.coords.latitude},${pos.coords.longitude}`;
    document.getElementById("googleMapLink").style.display = 'inline-block';
}

// --- Expense Logic ---
function toggleExpenseContainer(containerId, rowId) {
    const row = document.getElementById(rowId);
    const chk = row.querySelector('input[type="checkbox"]');
    if(chk.checked) row.classList.add('active');
    else row.classList.remove('active');
}
function toggleOneExpense(inputId, rowId) {
    toggleExpenseContainer(null, rowId);
    if(!document.getElementById(rowId).querySelector('input[type="checkbox"]').checked) {
        document.getElementById(inputId).value = '';
        calculateTotal();
    }
}
function addFuelRow() {
    const container = document.getElementById('fuel_container');
    const div = document.createElement('div');
    div.className = 'expense-row';
    div.innerHTML = `<input type="number" step="0.01" name="fuel_cost[]" class="form-input calc-expense" placeholder="บาท" oninput="calculateTotal()"> <label class="file-upload-btn"><i class="fas fa-upload"></i> <input type="file" name="fuel_receipt_file[]" hidden onchange="showFile(this)"></label>`;
    container.appendChild(div);
}
function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.calc-expense').forEach(i => total += Number(i.value));
    document.getElementById('totalExpenseDisplay').innerText = total.toFixed(2);
}
function showFile(input) {
    if(input.files[0]) input.parentElement.style.background = '#dcfce7';
}

// Submit Check
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยันการส่ง?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ส่งรายงาน'
    }).then((result) => {
        if (result.isConfirmed) this.submit();
    });
});
// ✅ ฟังก์ชัน CUSTOM AUTOCOMPLETE (เวอร์ชั่นสมบูรณ์: จิ้มก็เด้ง พิมพ์ก็เด้ง)
function setupAutocomplete(inp, arr) {
    if (!inp) return;
    
    var currentFocus;

    // ฟังก์ชันวาดรายการ
    function renderList(val) {
        var a, b, i, valUpper = val.toUpperCase();
        
        // ปิดรายการเก่าของทุก Box ทิ้งก่อน (เพื่อไม่ให้ซ้อนกัน)
        closeAllLists();
        
        if (!val) { val = ""; }
        currentFocus = -1;
        
        // สร้าง div แม่ข่าย
        a = document.createElement("div");
        a.setAttribute("id", this.id + "autocomplete-list");
        a.setAttribute("class", "autocomplete-items");
        
        // ป้องกันการคลิกที่ List แล้วปิดตัวเอง
        a.addEventListener("click", function(e) {
            e.stopPropagation(); 
        });

        // ใส่ div ต่อท้าย input
        inp.parentElement.appendChild(a);

        var count = 0;
        for (i = 0; i < arr.length; i++) {
            if (val === "" || arr[i].toUpperCase().indexOf(valUpper) > -1) {
                b = document.createElement("div");
                b.className = "autocomplete-item";
                
                if (val !== "" && arr[i].toUpperCase().indexOf(valUpper) === 0) {
                     b.innerHTML = "<strong>" + arr[i].substr(0, val.length) + "</strong>" + arr[i].substr(val.length);
                } else {
                     b.innerHTML = arr[i];
                }
                
                b.innerHTML = `<i class="fas fa-user-tag"></i> ` + b.innerHTML;
                b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
                
                b.addEventListener("click", function(e) {
                    e.stopPropagation(); // 🟢 สำคัญ: คลิกเลือกแล้วห้ามส่ง event ต่อ
                    inp.value = this.getElementsByTagName("input")[0].value;
                    if(typeof checkCustomerType === 'function') {
                        checkCustomerType(inp); 
                    }
                    closeAllLists();
                });
                a.appendChild(b);
                count++;
            }
        }
        
        if (count > 0) a.style.display = "block";
    }

    // 🟢 1. Event Input: พิมพ์แล้วกรอง (เพิ่ม stopPropagation)
    inp.addEventListener("input", function(e) {
        e.stopPropagation(); // ห้ามบอกคนอื่น
        renderList(this.value);
    });

    // 🟢 2. Event Click: คลิกแล้วโชว์ (เพิ่ม stopPropagation)
    inp.addEventListener("click", function(e) {
        e.stopPropagation(); // 🟢 จุดสำคัญ! หยุดไม่ให้ Box อื่นรู้ว่ามีการคลิกที่นี่
        renderList(this.value);
    });

    // 🟢 3. Event Focus: เผื่อกด Tab เข้ามา
    inp.addEventListener("focus", function(e) {
        e.stopPropagation(); // ห้ามบอกคนอื่น
        renderList(this.value);
    });

    // ปุ่มคีย์บอร์ด
    inp.addEventListener("keydown", function(e) {
        var x = this.parentElement.querySelector('.autocomplete-items');
        if (x) x = x.getElementsByTagName("div");
        if (e.keyCode == 40) { // ลง
            currentFocus++;
            addActive(x);
        } else if (e.keyCode == 38) { // ขึ้น
            currentFocus--;
            addActive(x);
        } else if (e.keyCode == 13) { // Enter
            e.preventDefault();
            if (currentFocus > -1) {
                if (x) x[currentFocus].click();
            }
        }
    });

    function addActive(x) {
        if (!x) return false;
        removeActive(x);
        if (currentFocus >= x.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = (x.length - 1);
        x[currentFocus].classList.add("active");
        x[currentFocus].scrollIntoView({block: "nearest"});
    }

    function removeActive(x) {
        for (var i = 0; i < x.length; i++) {
            x[i].classList.remove("active");
        }
    }

    function closeAllLists(elmnt) {
        var x = document.getElementsByClassName("autocomplete-items");
        for (var i = 0; i < x.length; i++) {
            if (elmnt != x[i] && elmnt != inp) {
                x[i].parentNode.removeChild(x[i]);
            }
        }
    }

    // 🟢 Event Global: คลิกที่ว่างๆ ถึงจะปิด (ต้องเช็คดีๆ)
    // เราจะใช้ Listener ตัวเดียวที่ document แทนการสร้างใหม่ทุกครั้ง
    if (!window.hasGlobalClickListener) {
        document.addEventListener("click", function (e) {
            closeAllLists(e.target);
        });
        window.hasGlobalClickListener = true; // ป้องกันการสร้าง Listener ซ้ำซ้อน
    }
}
