let workBoxCount = 1;

document.addEventListener('DOMContentLoaded', function() {
    // 1. Init ปฏิทิน (เหมือนเดิม)
    initFlatpickr("#reportDateDisplay", "#reportDateHidden");
    initFlatpickr(".next-appt", null);

    // 2. Load Job Status (เหมือนเดิม)
    loadJobStatus(document.querySelector('#work-box-1 .job-status-select'));

    // ✅ 3. เปิดใช้งาน Autocomplete ให้กล่องแรกทันที
    setupAutocomplete(document.querySelector('#work-box-1 .customer-input'), customerList);
});

// ฟังก์ชันเพิ่มกล่องงาน
function addWorkBox() {
    workBoxCount++;
    const container = document.getElementById('work-container');
    const template = document.getElementById('work-box-1').cloneNode(true);
    
    template.id = 'work-box-' + workBoxCount;
    template.querySelector('.work-box-title').innerText = 'งานที่ ' + workBoxCount;
    
    // เคลียร์ค่าเก่า
    template.querySelectorAll('input:not([type="radio"]), textarea, select').forEach(input => {
        input.value = '';
    });
    
    // เคลียร์กล่อง Autocomplete เก่าที่ติดมาตอน Clone (สำคัญ!)
    const oldList = template.querySelector('.autocomplete-items');
    if(oldList) oldList.innerHTML = ''; 

    // จัดการ Radio Button
    const radios = template.querySelectorAll('input[type="radio"]');
    radios.forEach(radio => {
        radio.name = 'customer_type_' + workBoxCount;
        if(radio.value === 'ลูกค้าใหม่') radio.checked = true;
    });

    // ปุ่มลบ
    const header = template.querySelector('.work-box-header');
    if(!header.querySelector('.btn-remove-box')) {
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn-remove-box';
        removeBtn.innerHTML = '<i class="fas fa-trash"></i> ลบรายการ';
        removeBtn.onclick = function() { template.remove(); };
        header.appendChild(removeBtn);
    }
    
    container.appendChild(template);

    // Re-init Flatpickr
    const newDateInput = template.querySelector('.next-appt');
    if(newDateInput._flatpickr) newDateInput._flatpickr.destroy();
    initFlatpickr(newDateInput, null);

    // ✅ เปิดใช้งาน Autocomplete ให้กล่องใหม่ด้วย
    setupAutocomplete(template.querySelector('.customer-input'), customerList);
}

// ฟังก์ชันเช็คประเภทลูกค้า (เก่า/ใหม่) ตาม Box
function checkCustomerType(inputElement) {
    const val = inputElement.value.trim();
    // หา container ของ box นี้ (closest)
    const box = inputElement.closest('.work-box');
    const radioOld = box.querySelector('.cust-type-old');
    const radioNew = box.querySelector('.cust-type-new');

    if (customerList.includes(val)) {
        radioOld.checked = true;
    } else {
        radioNew.checked = true;
    }
}

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
    const zone = document.getElementById("areaSelect").value;
    const provinceSelect = document.getElementById("provinceSelect");
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
// ✅ ฟังก์ชัน CUSTOM AUTOCOMPLETE (เวอร์ชั่นใหม่: จิ้มปุ๊บเด้งปั๊บ)
function setupAutocomplete(inp, arr) {
    if (!inp) return;
    let currentFocus;

    // ฟังก์ชันวาดรายการ (แยกออกมาเพื่อเรียกใช้ซ้ำ)
    function renderList(val) {
        let a, b, i, count = 0;
        
        // ปิดรายการเก่าก่อนเสมอ
        closeAllLists();
        
        currentFocus = -1;
        
        // สร้าง div แม่ข่าย (ถ้ายังไม่มี)
        let wrapper = inp.parentElement;
        a = wrapper.querySelector('.autocomplete-items');
        if (!a) {
            a = document.createElement("div");
            a.className = "autocomplete-items";
            wrapper.appendChild(a);
        }
        
        a.innerHTML = ""; // เคลียร์ของเก่า
        a.style.display = "block"; // โชว์กล่อง

        // วนลูปข้อมูล
        for (i = 0; i < arr.length; i++) {
            // เงื่อนไข: ถ้าช่องว่าง (จิ้มเฉยๆ) ให้โชว์หมด หรือ ถ้าพิมพ์ให้กรองตามคำ
            if (val === "" || arr[i].toUpperCase().indexOf(val.toUpperCase()) > -1) {
                
                b = document.createElement("div");
                b.className = "autocomplete-item";
                
                // การแสดงผลตัวหนังสือ
                if (val === "") {
                    // ถ้าไม่ได้พิมพ์อะไร ให้โชว์ชื่อเต็มๆ
                    b.innerHTML = `<i class="fas fa-user-tag"></i> ` + arr[i];
                } else {
                    // ถ้าพิมพ์ ให้ทำตัวหนาตรงคำที่ค้นหา
                    let matchIndex = arr[i].toUpperCase().indexOf(val.toUpperCase());
                    b.innerHTML = `<i class="fas fa-user-tag"></i> ` + 
                                  arr[i].substr(0, matchIndex) + 
                                  "<strong>" + arr[i].substr(matchIndex, val.length) + "</strong>" + 
                                  arr[i].substr(matchIndex + val.length);
                }

                // ใส่ค่าที่จะส่งไป input
                b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
                
                // คลิกเลือกรายการ
                b.addEventListener("click", function(e) {
                    inp.value = this.getElementsByTagName("input")[0].value;
                    checkCustomerType(inp); // เช็คประเภทลูกค้า
                    closeAllLists();
                });
                
                a.appendChild(b);
                count++;
            }
        }
        
        if (count === 0) {
             // ถ้าค้นไม่เจอ ให้ปิด (หรือจะโชว์ว่า "ไม่พบข้อมูล" ก็ได้)
             a.style.display = "none";
        }
    }

    // 🟢 Event 1: เมื่อพิมพ์ (Input) -> กรองข้อมูล
    inp.addEventListener("input", function(e) {
        renderList(this.value);
    });

    // 🟢 Event 2: เมื่อจิ้ม (Click) -> โชว์ทั้งหมดทันที
    inp.addEventListener("click", function(e) {
        // ถ้าค่าว่าง หรือมีค่าอยู่แล้ว ก็ให้เรียก renderList เพื่อโชว์ Dropdown
        renderList(this.value); 
    });

    // ปุ่มกดคีย์บอร์ด (ลง, ขึ้น, Enter)
    inp.addEventListener("keydown", function(e) {
        let x = this.parentElement.querySelector('.autocomplete-items');
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
        x[currentFocus].classList.add("active"); // ใช้ CSS .active ที่ทำไว้
        x[currentFocus].scrollIntoView({block: "nearest"});
    }

    function removeActive(x) {
        for (let i = 0; i < x.length; i++) {
            x[i].classList.remove("active");
        }
    }

    function closeAllLists(elmnt) {
        let x = document.getElementsByClassName("autocomplete-items");
        for (let i = 0; i < x.length; i++) {
            if (elmnt != x[i] && elmnt != inp) {
                x[i].innerHTML = "";
                x[i].style.display = "none";
            }
        }
    }

    // คลิกที่อื่นเพื่อปิด
    document.addEventListener("click", function (e) {
        closeAllLists(e.target);
    });
}