<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

$site_id = $_GET['id'];
$user = $_SESSION['user_id'];

$sql = "SELECT  a.*, b.company_name , 
                e.customer_name , e.affiliation 
        FROM project_contracts a
        LEFT JOIN companies b ON a.company_id = b.id
        LEFT JOIN customers e ON a.customer_id = e.customer_id 
        WHERE a.site_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $site_id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();

?>



<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title>รายละเอียดโครงการ</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <script src="load_scripts.js"></script>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class='main-content'>
        <a href="javascript:history.back()">
            <i class="fas fa-angle-double-left" style="color: red;"> ย้อนกลับ</i>
        </a>
        <div class='content mt-2'>
            <div class="row">
                <div class="col-10">
                    <div class="header-title">
                        <h2><i class="far fa-edit"></i> กรอกทุน </h2>
                    </div>
                </div>
                <div class="col-2">
                    <h2 style="color: #083275 !important;">&nbsp;หน้างานเลขที่
                        <?php echo $site_id; ?>
                    </h2>
                </div>
            </div>
            <div class="row">
                <div class="col-8">
                    <label class="bold" style="font-size: 16px !important;">หน่วยงาน : </label>
                    <span class="bold" style="font-size: 18px !important;color: #083275 !important;">
                        <?php echo $data['customer_name']; ?>
                    </span>
                </div>
                <div class="col-4">
                    <label class="bold" style="font-size: 16px !important;">สังกัด : </label>
                    <span class="bold" style="font-size: 18px !important;color: #083275 !important;">
                        <?php echo $data['affiliation']; ?>
                    </span>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-8">
                    <label class="bold" style="font-size: 14px !important;">ชื่อโครงการ/ชื่องบ : </label>
                    <span class="bold" style="font-size: 16px !important;color: #083275 !important;">
                        <?php echo $data['project_name']; ?>
                    </span>
                </div>
                <div class="col-4">
                    <label class="bold" style="font-size: 14px !important;">งบโครงการ : </label>
                    <span class="bold" style="font-size: 16px !important;color: #083275 !important;">
                        <?php
                        echo isset($data['project_budget']) ? number_format($data['project_budget'], 2) . ' บาท' : '0.00' . ' บาท';
                        ?>
                    </span>
                </div>
            </div>

            <hr>

            <div class="row mt-2">
                <div class="col-3">
                    <label class="label-text">เลขที่เอกสาร</label>
                    <input type="text" class="form-input form-control" name="doc_no" id="doc_no"
                        placeholder="ระบุ PO/AX" required data-parsley-required-message="ระบุ PO/AX"
                        data-parsley-errors-container="#validate-doc_no">
                    <div class="error-validate" id="validate-doc_no"></div>
                </div>
                <div class="col-5">
                    <label class="label-text">รายการ</label>
                    <input type="text" class="form-input form-control" name="list_name" id="list_name"
                        placeholder="ระบุรายการ" required data-parsley-required-message="กรุณาระบุรายการ"
                        data-parsley-errors-container="#validate-list_name">
                    <div class="error-validate" id="validate-list_name"></div>
                </div>
                <div class="col-2">
                    <label class="label-text">จำนวน</label>
                    <input type="number" class="form-input form-control" name="qty" id="qty" placeholder="ระบุจำนวน"
                        required data-parsley-required-message="กรุณาระบุจำนวน"
                        data-parsley-errors-container="#validate-qty">
                    <div class="error-validate" id="validate-qty"></div>
                </div>
                <div class="col-2">
                    <label class="label-text">หน่วย</label>
                    <input type="text" class="form-input form-control" name="unit" id="unit" placeholder="ระบุหน่วยนับ"
                        required data-parsley-required-message="กรุณาระบุหน่วยนับ"
                        data-parsley-errors-container="#validate-unit">
                    <div class="error-validate" id="validate-unit"></div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-2">
                    <label class="label-text">ราคากลาง/หน่วย</label>
                    <input type="number" class="form-input form-control" name="std_price" id="std_price"
                        placeholder="ระบุราคากลาง/หน่วย" required
                        data-parsley-required-message="กรุณาระบุราคากลาง/หน่วย"
                        data-parsley-errors-container="#validate-std_price">
                    <div class="error-validate" id="validate-std_price"></div>
                </div>
                <div class="col-2">
                    <label class="label-text">ราคาสืบ/หน่วย</label>
                    <input type="number" class="form-input form-control" name="market_price" id="market_price"
                        placeholder="ระบุราคาสืบ/หน่วย" required data-parsley-required-message="กรุณาระบุราคาสืบ/หน่วย"
                        data-parsley-errors-container="#validate-market_price">
                    <div class="error-validate" id="validate-market_price"></div>
                </div>
                <div class="col-2">
                    <label class="label-text">ค่าขนส่ง</label>
                    <input type="number" class="form-input form-control" name="transport_cost" id="transport_cost"
                        placeholder="ระบุค่าขนส่ง" required data-parsley-required-message="กรุณาระบุค่าขนส่ง"
                        data-parsley-errors-container="#validate-transport_cost">
                    <div class="error-validate" id="validate-transport_cost"></div>
                </div>
                <div class="col-2">
                    <label class="label-text">ค่าเซ็นสัญญา</label>
                    <input type="number" class="form-input form-control" name="sign_cost" id="sign_cost"
                        placeholder="ระบุค่าเซ็นสัญญา" required data-parsley-required-message="กรุณาระบุค่าเซ็นสัญญา"
                        data-parsley-errors-container="#validate-sign_cost">
                    <div class="error-validate" id="validate-sign_cost"></div>
                </div>
                <div class="col-4">
                    <label class="label-text">หมายเหตุ</label>
                    <input type="text" class="form-input form-control" name="remark" id="remark"
                        placeholder="ระบุหมายเหตุ">
                </div>
            </div>
            <div class="mt-3" style="display: flex; justify-content:flex-end; align-items: center;">
                <button type="submit" class="btn-create" onclick="addItem()">
                    <i class="far fa-plus"></i>Add
                </button>
                &nbsp;
                <button type="button" class="btn-clear" onclick="clearAllData()">
                    <i class="fas fa-undo-alt"></i> Clear
                </button>
            </div>

            <hr>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="itemsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>เลขที่เอกสาร (PO,AX)</th>
                                <th>รายการ</th>
                                <th>จำนวน</th>
                                <th>หน่วยนับ</th>
                                <th>ราคากลาง/หน่วย</th>
                                <th>ยอดรวม(ราคากลาง)</th>
                                <th>ราคาสืบ/หน่วย</th>
                                <th>ยอดรวม(ราคาสืบ)</th>
                                <th>ค่าขนส่ง</th>
                                <th>ค่าเซ็นสัญญา</th>
                                <th>หมายเหตุ</th>
                                <th>เครื่องมือ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
    </div>
</body>

</html>



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
                    <td>${item.doc_no}</td>
                    <td>${item.product_name}</td>
                    <td>${item.qty}</td>
                    <td>${item.unit}</td>
                    <td>${item.market_price}</td>
                    <td>${item.market_price}</td>
                    <td>${item.market_price}</td>
                    <td>${item.market_price}</td>
                    <td>${item.market_price}</td>
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