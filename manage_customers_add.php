<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มลูกค้าหน่วยงาน - TJC GROUP</title>

    <?php include 'Logowab.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="load_scripts.js"></script>
    <link rel="stylesheet" href="style.css">
    <!-- Animate.css for quick animations if not already included -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="css/service_dashboard.css">

    <style>
        /* Animations */
        @keyframes fadeInUpScale {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUpScale 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .form-row-anim {
            opacity: 0;
            animation: fadeInUpScale 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .form-row-anim:nth-child(1) {
            animation-delay: 0.1s;
        }

        .form-row-anim:nth-child(2) {
            animation-delay: 0.2s;
        }

        .form-row-anim:nth-child(3) {
            animation-delay: 0.3s;
        }

        .form-row-anim:nth-child(4) {
            animation-delay: 0.4s;
        }

        .form-row-anim:nth-child(5) {
            animation-delay: 0.5s;
        }

        .form-row-anim:nth-child(6) {
            animation-delay: 0.6s;
        }

        .form-row-anim:nth-child(7) {
            animation-delay: 0.7s;
        }

        /* Modern Input Styles */
        .custom-input {
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: #f9fafb;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.02);
            width: 100%;
        }

        .custom-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            background-color: #ffffff;
            transform: translateY(-2px);
            outline: none;
        }

        .custom-input::placeholder {
            color: #9ca3af;
        }

        .label-text {
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 8px;
            display: inline-block;
            font-size: 0.95rem;
        }

        .custom-modal-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 20px 24px;
            border-radius: 16px 16px 0 0;
            margin: 0 0 1.5rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .custom-modal-header h3 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: white;
        }

        .custom-modal-header i {
            font-size: 1.6rem;
            color: #bfdbfe;
        }

        .btn-save-custom {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-save-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-save-custom:active {
            transform: translateY(0);
        }

        .btn-close-custom {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-close-custom:hover {
            background-color: #e5e7eb;
            color: #1f2937;
            border-color: #9ca3af;
        }

        .form-group-wrapper {
            margin-bottom: 1.25rem;
        }

        /* Parsley Errors Customization */
        .parsley-errors-list {
            margin: 5px 0 0 0;
            padding: 0;
            list-style-type: none;
            font-size: 0.85rem;
            color: #ef4444;
            animation: fadeInUpScale 0.3s ease-out forwards;
        }

        .custom-input.parsley-error {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .custom-input.parsley-error:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15) !important;
        }

        .custom-input.parsley-success {
            border-color: #10b981 !important;
            background-color: #f0fdf4 !important;
        }

        /* Sweet Loading State styling */
        .btn-save-custom.loading {
            opacity: 0.8;
            pointer-events: none;
        }

        /* Layout overrides */
        .action-card {
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
            margin-bottom: 30px;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="main-container">

            <div class="dashboard-header-wrapper">
                <div class="header-content">
                    <h2 class="page-title">จัดการลูกค้าหน่วยงาน</h2>
                    <span class="page-subtitle"><i class="fas fa-people-group"></i>
                        เพิ่มข้อมูลลูกค้าใหม่เข้าสู่ระบบ</span>
                </div>
            </div>

            <div class="action-card mt-4">
                <div class="custom-modal-header animate-fade-in-up" style="animation-delay: 0.05s;">
                    <i class="fas fa-building"></i>
                    <h3>เพิ่มข้อมูลหน่วยงาน/ลูกค้า</h3>
                </div>

                <div class="modal-body p-4">
                    <form id="create_customer" action="manage_customers_create.php" method="POST" novalidate>
                        <div class="row form-group-wrapper form-row-anim">
                            <div class="col-12">
                                <label class="label-text">ชื่อหน่วยงาน/ชื่อลูกค้า <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-input form-control custom-input" name="customer_name"
                                    id="customer_name" placeholder="ใส่ชื่อหน่วยงานหรือชื่อลูกค้าที่นี่..." required
                                    data-parsley-required-message="<i class='fas fa-exclamation-circle'></i> กรุณากรอกชื่อหน่วยงานหรือลูกค้า">
                                <div class="error-validate" id="validate-company"></div>
                            </div>
                        </div>

                        <div class="row form-group-wrapper form-row-anim">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="label-text">สังกัด</label>
                                <input type="text" class="form-input form-control custom-input" name="affiliation"
                                    id="affiliation" placeholder="สังกัด (ถ้ามี)">
                            </div>
                            <div class="col-md-6">
                                <label class="label-text">ที่อยู่
                                    <span style="font-size: 0.75rem; color: #ef4444; font-weight: normal;">
                                        (*หากไม่มีให้ระบุ -)
                                    </span>
                                </label>
                                <input type="text" class="form-input form-control custom-input" name="address"
                                    id="address" placeholder="รายละเอียดที่อยู่">
                            </div>
                        </div>

                        <div class="row form-group-wrapper form-row-anim">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="label-text">ตำบล <span class="text-danger">*</span></label>
                                <input type="text" class="form-input form-control custom-input" name="district"
                                    id="district" placeholder="ตำบล" required
                                    data-parsley-required-message="<i class='fas fa-exclamation-circle'></i> กรุณาระบุตำบล">
                            </div>
                            <div class="col-md-6">
                                <label class="label-text">อำเภอ <span class="text-danger">*</span></label>
                                <input type="text" class="form-input form-control custom-input" name="amphoe"
                                    id="amphoe" placeholder="อำเภอ" required
                                    data-parsley-required-message="<i class='fas fa-exclamation-circle'></i> กรุณาระบุอำเภอ">
                            </div>
                        </div>

                        <div class="row form-group-wrapper form-row-anim">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="label-text">จังหวัด <span class="text-danger">*</span></label>
                                <input type="text" class="form-input form-control custom-input" name="province"
                                    id="province" placeholder="จังหวัด" required
                                    data-parsley-required-message="<i class='fas fa-exclamation-circle'></i> กรุณาระบุจังหวัด">
                            </div>
                            <div class="col-md-6">
                                <label class="label-text">รหัสไปรษณีย์ <span class="text-danger">*</span></label>
                                <input type="text" class="form-input form-control custom-input" name="zipcode"
                                    id="zipcode" placeholder="รหัสไปรษณีย์ 5 หลัก" required data-parsley-type="digits"
                                    data-parsley-length="[5, 5]"
                                    data-parsley-required-message="<i class='fas fa-exclamation-circle'></i> กรุณาระบุรหัสไปรษณีย์"
                                    data-parsley-type-message="<i class='fas fa-exclamation-circle'></i> กรอกตัวเลขเท่านั้น"
                                    data-parsley-length-message="<i class='fas fa-exclamation-circle'></i> รหัสไปรษณีย์ต้องมี 5 หลัก">
                            </div>
                        </div>

                        <div class="row form-group-wrapper form-row-anim">
                            <div class="col-12">
                                <label class="label-text">เบอร์ติดต่อ <span class="text-danger">*</span>
                                    <span style="font-size: 0.75rem; color: #ef4444; font-weight: normal;"> (*ระบุ -
                                        หากไม่มี)</span>
                                </label>
                                <input type="text" class="form-input form-control custom-input" name="phone_number"
                                    id="phone_number" placeholder="เบอร์โทรศัพท์ติดต่อ" required
                                    data-parsley-required-message="<i class='fas fa-exclamation-circle'></i> กรุณาระบุเบอร์ติดต่อ">
                            </div>
                        </div>

                        <div class="row form-group-wrapper form-row-anim">
                            <div class="col-12">
                                <label class="label-text">หมายเหตุ </label>
                                <textarea class="form-input form-control custom-input" name="remark" id="remark"
                                    placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="modal-footer form-row-anim"
                            style="border-top: 1px solid #f3f4f6; padding-top: 1.25rem; margin-top: 0.5rem; justify-content: flex-end; gap: 10px;">
                            <a href="manage_customers.php" class="btn-close-custom">
                                <i class="fas fa-times"></i> ยกเลิก
                            </a>
                            <button type="submit" class="btn-save-custom">
                                <i class="fas fa-save"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        $(document).ready(function () {
            // ตั้งค่าภาษาไทย (ถ้ายังไม่ได้ตั้งในไฟล์หลัก)
            if (typeof Parsley !== 'undefined') {
                Parsley.addMessages('th', {
                    defaultMessage: "ข้อมูลนี้ไม่ถูกต้อง",
                    required: "จำเป็นต้องระบุข้อมูลนี้"
                });
                Parsley.setLocale('th');

                // เริ่มต้น Parsley
                $('#create_customer').parsley({
                    excluded: 'input[type=button], input[type=submit], input[type=reset]',
                    errorClass: 'parsley-error',
                    successClass: 'parsley-success',
                    errorsWrapper: '<ul class="parsley-errors-list"></ul>',
                    errorTemplate: '<li></li>'
                });
            }
        });

        $(document).on('submit', '#create_customer', function (e) {
            e.preventDefault();

            var $form = $(this);

            if (typeof Parsley !== 'undefined') {
                // 1. สั่งให้ Parsley ตรวจสอบข้อมูลทันที
                $form.parsley().validate();

                // 2. ถ้าตรวจสอบ "ไม่ผ่าน" ให้หยุดทำงาน (ไม่ยิง AJAX)
                if (!$form.parsley().isValid()) {
                    // เพิ่ม animation สั่นเตือนเบาๆ
                    $form.addClass('animate__animated animate__shakeX');
                    setTimeout(() => $form.removeClass('animate__animated animate__shakeX'), 1000);
                    return false;
                }
            }

            var formData = new FormData(this);

            // Disable submit button and show loading
            var $submitBtn = $form.find('button[type="submit"]');
            var originalBtnHtml = $submitBtn.html();
            $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...').prop('disabled', true).addClass('loading');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    // Show success animation or SweetAlert
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'เพิ่มข้อมูลสำเร็จ!',
                            text: 'ระบบกำลังพากลับไปยังหน้าจัดการลูกค้า...',
                            timer: 2000,
                            showConfirmButton: false,
                            backdrop: `rgba(0,0,0,0.4)`
                        }).then(() => {
                            window.location.href = 'manage_customers.php';
                        });
                    } else {
                        window.location.href = 'manage_customers.php';
                    }
                },
                error: function (xhr, status, error) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'ข้อผิดพลาดเครือข่าย',
                            text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ (' + error + ')',
                            confirmButtonText: 'รับทราบ',
                            confirmButtonColor: '#ef4444'
                        });
                    } else {
                        alert('Error: ' + error);
                    }
                },
                complete: function () {
                    // Restore button state
                    $submitBtn.html(originalBtnHtml).prop('disabled', false).removeClass('loading');
                }
            });
        });
    </script>
</body>

</html>