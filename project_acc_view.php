<div class="row mt-4">
    <div class="col-4">
        <label class="bold">เลขใบรับเงิน</label>
        
    </div>
    <div class="col-2">
        <label class="bold">วันที่ได้รับเงิน</label>
       
    </div>

    <div class="col-3">
        <label class="bold">หัก ณ ที่จ่าย</label>
        
    </div>

    <div class="col-3">
        <label class="bold">ค่าปรับ</label>
       
    </div>
</div>
<div class="row mt-2">
    <div class="col-4">
        <label class="bold">ประเภทการรับเงิน</label>
       
       
    </div>
    <div class="col-3" id="bank_details_div">
        <label class="bold">ธนาคาร</label>
       
    </div>
    <div class="col-3" id="check_details_div">
        <label class="bold">เลขที่เช็ค</label>
       
    </div>
    <div class="col-5">
        <label class="bold">หมายเหตุ</label>
       
    </div>
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