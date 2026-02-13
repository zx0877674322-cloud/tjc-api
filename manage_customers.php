<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';
?>

<script src="load_scripts.js"></script>
<link rel="stylesheet" href="style.css">

<div class="modal-header">
    <h3><i class="fas fa-plus"></i> เพิ่มข้อมูลหน่วยงาน/ลูกค้า</h3>
</div>

<div class="modal-body">
    <form id="create_customer" action="manage_customers_create.php" method="POST" novalidate>
        <div class="row">
            <div class="col-12">
                <label class="label-text">ชื่อหน่วยงาน/ชื่อลูกค้า <span class="text-danger">*</span></label>
                <input type="text" class="form-input form-control" name="customer_name" id="customer_name"
                    placeholder="ชื่อหน่วยงาน/ชื่อลูกค้า" required
                    data-parsley-required-message="กรุณากรอกชื่อหน่วยงานหรือลูกค้า">
                <div class="error-validate" id="validate-company"></div>
            </div>
        </div>

        <div class="row mt-1">
            <div class="col-6">
                <label class="label-text">สังกัด</label>
                <input type="text" class="form-input form-control" name="affiliation" id="affiliation"
                    placeholder="สังกัด">
            </div>
            <div class="col-6">
                <label class="label-text">ที่อยู่
                    <span style="font-size: 10px; color: red;">
                        (*หากไม่มีให้ระบุ -)
                    </span>
                </label>
                <input type="text" class="form-input form-control" name="address" id="address" placeholder="ที่อยู่">
            </div>
        </div>

        <div class="row mt-1">
            <div class="col-6">
                <label class="label-text">ตำบล <span class="text-danger">*</span></label>
                <input type="text" class="form-input form-control" name="district" id="district" placeholder="ตำบล"
                    required data-parsley-required-message="กรุณาระบุตำบล">
            </div>
            <div class="col-6">
                <label class="label-text">อำเภอ <span class="text-danger">*</span></label>
                <input type="text" class="form-input form-control" name="amphoe" id="amphoe" placeholder="อำเภอ"
                    required data-parsley-required-message="กรุณาระบุอำเภอ">
            </div>
        </div>

        <div class="row mt-1">
            <div class="col-6">
                <label class="label-text">จังหวัด <span class="text-danger">*</span></label>
                <input type="text" class="form-input form-control" name="province" id="province" placeholder="จังหวัด"
                    required data-parsley-required-message="กรุณาระบุจังหวัด">
            </div>
            <div class="col-6">
                <label class="label-text">รหัสไปรษณีย์ <span class="text-danger">*</span></label>
                <input type="text" class="form-input form-control" name="zipcode" id="zipcode"
                    placeholder="รหัสไปรษณีย์" required data-parsley-type="digits" data-parsley-length="[5, 5]"
                    data-parsley-required-message="กรุณาระบุรหัสไปรษณีย์" data-parsley-type-message="กรอกตัวเลขเท่านั้น"
                    data-parsley-length-message="รหัสไปรษณีย์ต้องมี 5 หลัก">
            </div>
        </div>

        <div class="row mt-1">
            <div class="col-12">
                <label class="label-text">เบอร์ติดต่อ <span class="text-danger">*</span>
                    <span style="font-size: 10px; color: red;"> (*ระบุ - หากไม่มี)</span>
                </label>
                <input type="text" class="form-input form-control" name="phone_number" id="phone_number"
                    placeholder="เบอร์ติดต่อ" required data-parsley-required-message="กรุณาระบุเบอร์ติดต่อ">
            </div>
        </div>

        <div class="row mt-1">
            <div class="col-12">
                <label class="label-text">หมายเหตุ </label>
                <input type="text" class="form-input form-control" name="remark" id="remark" placeholder="หมายเหตุ">
            </div>
        </div>
        <br>
        <div class="modal-footer">
            <button type="button" class="btn-close" data-bs-dismiss="modal"> <i class="fas fa-close"></i> ปิดหน้านี้
            </button>
            <button type="submit" class="btn-save"> <i class="fas fa-save"></i> บันทึกข้อมูล </button>
        </div>
    </form>
</div>

<script>
    $(document).ready(function () {
        // ตั้งค่าภาษาไทย (ถ้ายังไม่ได้ตั้งในไฟล์หลัก)
        Parsley.addMessages('th', {
            defaultMessage: "ข้อมูลนี้ไม่ถูกต้อง",
            required: "จำเป็นต้องระบุข้อมูลนี้"
        });
        Parsley.setLocale('th');

        // เริ่มต้น Parsley
        $('#create_customer').parsley({
            excluded: 'input[type=button], input[type=submit], input[type=reset]'
        });
    });

    $(document).on('submit', '#create_customer', function (e) {
        e.preventDefault();

        var $form = $(this);

        // 1. สั่งให้ Parsley ตรวจสอบข้อมูลทันที
        $form.parsley().validate();

        // 2. ถ้าตรวจสอบ "ไม่ผ่าน" ให้หยุดทำงาน (ไม่ยิง AJAX)
        if (!$form.parsley().isValid()) {
            return false;
        }

        var formData = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                $('body').append(response);
                $('#createCustomer').find('[data-bs-dismiss="modal"], .btn-close').click();
                $("#customer_wrapper").load(window.location.href + " #customer_wrapper > *", function () {
                    $('.select-search').select();
                });


            },
            error: function (xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ (' + error + ')',
                    confirmButtonText: 'ปิด'
                });
            }
        });
    });
</script>