<script src="load_scripts.js"></script>
<link rel="stylesheet" href="style.css">
<?php
$customer_name = $_GET['customer_name'];
?>

<div class="modal-header">
    <h2>เพิ่มรายการ/ชื่อสินค้า</h2>
</div>
<div class="modal-body" style="padding: 0px 10px !important">
    <h4 style="color: #083275 !important;">
        หน่วยงาน :
        <?php echo $customer_name; ?>
    </h4>

    <div class="row">
        <div class="col-12">
            <label class="label-text">รายการ/ชื่อสินค้า</label>
            <input type="text" name="item_name" id="item_name" class="form-input" placeholder="ระบุรายการ/ชื่อสินค้า"
                data-parsley-required-message="* กรุณาระบุรายการ/ชื่อสินค้า" required
                value="<?= $project_data['item_name'] ?? '' ?>" autofocus>
            <div class="error-validate" id="validate_item_name"></div>
        </div>
    </div>

    <div class="mt-2" style="display: flex; justify-content:center; align-items: center;">
        <button type="submit" class="btn-create" onclick="addItem()">
            <i class="far fa-plus"></i>Add
        </button>
    </div>
    <br>
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="itemsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>รายการ</th>
                        <th>เครื่องมือ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn-close close-modal" data-bs-dismiss="modal">
        <i class="fas fa-close"></i> ปิดหน้านี้
    </button>
    <button type="submit" form="form_cancel" class="btn-save">
        <i class="fas fa-save"></i> บันทึก
    </button>
</div>


<script>
    // ตัวแปร global เก็บรายการสินค้า
    let itemsArray = [];

    function addItem() {
        // 1. ดึงค่าจาก input
        let item = {
            doc_no: $('#doc_no').val(),
            product_name: $('#product_name').val(),
            qty: $('#qty').val(),
            unit: $('#unit').val(),
            std_price: $('#std_price').val(),
            market_price: $('#market_price').val(),
            transport_cost: $('#transport_cost').val(),
            sign_cost: $('#sign_cost').val(),
            remark: $('#remark').val()
        };

        // Validate: ตรวจสอบว่ากรอกข้อมูลครบไหม (ตัวอย่างเช็คแค่ชื่อสินค้า)
        if (item.product_name === "") {
            alert("กรุณาระบุชื่อสินค้า");
            return;
        }

        // 2. เพิ่มลงใน Array
        itemsArray.push(item);

        // 3. อัพเดทตารางแสดงผล และ Hidden Input
        renderTable();
        clearInputs();
    }

    function renderTable() {
        let tbody = $('#itemsTable tbody');
        tbody.empty(); // ล้างข้อมูลเก่าในตารางก่อน

        itemsArray.forEach((item, index) => {
            let row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.product_name}</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(${index})">ลบ</button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        // 4. แปลง Array เป็น JSON String ใส่ใน Hidden Input เพื่อเตรียมส่ง PHP
        $('#cart_data').val(JSON.stringify(itemsArray));
    }

    function removeItem(index) {
        itemsArray.splice(index, 1); // ลบออกจาก array
        renderTable(); // วาดตารางใหม่
    }

    function clearInputs() {
        // ล้างค่าใน input หลังจากกด Add
        $('.form-input').val('');
        $('#doc_no').focus();
    }


    function clearAllData() {
        document.querySelectorAll('input[type="text"], input[type="number"]').forEach(input => input.value = '');
    }

</script>