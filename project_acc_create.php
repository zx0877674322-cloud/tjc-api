<div class="row mt-4">
    <div class="col-4">
        <label class="bold">เลขใบรับเงิน</label>
        <input type="text" name="receipt_no" class="form-input">
    </div>
    <div class="col-2">
        <label class="bold">วันที่ได้รับเงิน</label>
        <input type="date" name="" class="form-input" placeholder="ระบุวันที่ได้รับเงิน">
    </div>

    <div class="col-3">
        <label class="bold">หัก ณ ที่จ่าย</label>
        <input type="number" name="" class="form-input" placeholder="ระบุหัก ณ ที่จ่าย">
    </div>

    <div class="col-3">
        <label class="bold">ค่าปรับ</label>
        <input type="number" name="" class="form-input" placeholder="ระบุค่าปรับ">
    </div>
</div>
<div class="row mt-2">
    <div class="col-4">
        <label class="bold">ประเภทการรับเงิน</label>
        <input class="form-check-input" type="radio" name="type_received" id="transfer" value="0" checked
            onchange="toggleInputs()">
        <label class="form-check-label" for="transfer">โอน</label>

        <input class="form-check-input" type="radio" name="type_received" id="check" value="1"
            onchange="toggleInputs()">
        <label class="form-check-label" for="check">เช็ค</label>

        <input class="form-check-input" type="radio" name="type_received" id="cash" value="2" onchange="toggleInputs()">
        <label class="form-check-label" for="cash">เงินสด</label>
    </div>
    <div class="col-3" id="bank_details_div">
        <label class="bold">ธนาคาร</label>
        <input type="text" id="bank_input" class="form-input" placeholder="ระบุชื่อธนาคาร">
    </div>
    <div class="col-3" id="check_details_div">
        <label class="bold">เลขที่เช็ค</label>
        <input type="text" id="check_input" class="form-input" placeholder="ระบุเลขที่เช็ค">
    </div>
    <div class="col-5">
        <label class="bold">หมายเหตุ</label>
        <input type="text" name="" class="form-input" placeholder="ระบุหมายเหตุ">
    </div>
</div>

<div class="mt-3" style="justify-items: end;">
    <button type="submit" class="btn-save" style="background: blueviolet !important;">
        <i class="far fa-save"></i>บันทึกข้อมูล
    </button>
</div>

<script>
    function toggleInputs() {
        // 1. ตรวจสอบว่าเลือก Radio ตัวไหนอยู่
        const isTransfer = document.getElementById('transfer').checked;
        const isCheck = document.getElementById('check').checked;
        const isCash = document.getElementById('cash').checked;

        // 2. ดึง Element กล่องข้อความที่ต้องการซ่อน/แสดง
        const bankDiv = document.getElementById('bank_details_div');
        const checkDiv = document.getElementById('check_details_div');

        const bankInput = document.getElementById('bank_input');
        const checkInput = document.getElementById('check_input');

        // 3. Logic การแสดงผล
        if (isTransfer) {
            // กรณีโอน: แสดงช่องธนาคาร, ซ่อนช่องเช็ค
            bankDiv.style.display = 'block';
            checkDiv.style.display = 'none';
            checkInput.value = ''; // ล้างค่าเช็ค

        } else if (isCheck) {
            // กรณีเช็ค: ซ่อนช่องธนาคาร, แสดงช่องเช็ค
            bankDiv.style.display = 'none';
            checkDiv.style.display = 'block';
            bankInput.value = ''; // ล้างค่าธนาคาร

        } else if (isCash) {
            // กรณีเงินสด: ซ่อนทั้งคู่
            bankDiv.style.display = 'none';
            checkDiv.style.display = 'none';
            bankInput.value = '';
            checkInput.value = '';
        }
    }

    // เรียกทำงาน 1 ครั้งตอนโหลดหน้า เพื่อให้สถานะเริ่มต้นถูกต้อง
    document.addEventListener("DOMContentLoaded", function () {
        toggleInputs();
    });
</script>