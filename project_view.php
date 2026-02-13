<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

// ดึงข้อมูล id ตามที่แนบลิ้งค์มา
$doc_id = $_GET['id'];
$user = $_SESSION['user_id'];

$sql = "SELECT  a.*, b.company_name , 
                e.customer_name , e.affiliation , e.phone_number , 
                e.address , e.sub_district, e.district, e.province, e.zip_code,
                h.work_type_name , u_sale.fullname AS sale_name , u_quo.fullname AS quotation_user , 
                u_create.fullname AS user_create, u_create.role AS user_role
        FROM project_contracts a
        LEFT JOIN companies b ON a.company_id = b.id
        LEFT JOIN customers e ON a.customer_id = e.customer_id 
        LEFT JOIN project_work_type h ON a.work_type_id = h.work_type_id
        LEFT JOIN users u_sale ON a.sale_user_id = u_sale.id  
        LEFT JOIN users u_quo ON a.quotation_user_id = u_quo.id
        LEFT JOIN users u_create ON $user = u_create.id 
        WHERE a.site_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doc_id);
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
                        <h3><i class="fas fa-building"></i> <?php echo $data['company_name']; ?></h3>
                    </div>
                </div>
                <div class="col-2">

                </div>
            </div>
            <div class="row" style="justify-content: space-between;">
                <div class="col-6">
                    <button class="box-c1">
                        <i class="fas fa-book"></i>&nbsp;
                        สัญญาเลขที่ &nbsp;
                        <?php
                        if ($data['contract_number'] == '') {
                            echo '<span style="color: red;">รอข้อมูล</span>';
                        } else {
                            echo $data['contract_number'];
                        }
                        ?>
                    </button>
                </div>
                <div class="col-6">
                    <div class="row mt-2">
                        <div class="col-6" style="text-align: end;">
                            <label class="bold"><i class="fa fa-warning"></i> สถานะโครงการ</label>
                        </div>
                        <div class="col-6" style="justify-items: start;">
                            <?php if ($data['project_status'] == 0) {
                                echo '<button class="b-status s-wait">ยังไม่เซ็นสัญญา</button>';
                            } elseif ($data['project_status'] == 1) {
                                echo '<button class="b-status s-contract">เซ็นสัญญา</button>';
                            } elseif ($data['project_status'] == 2) {
                                echo '<button class="b-status s-process">ระหว่างดำเนินการ</button>';
                            } else if ($data['project_status'] == 3) {
                                echo '<button class="b-status s-completed">ดำเนินการเสร็จสิ้น</button>';
                            } else {
                                echo '<button class="b-status s-cancel">ยกเลิก</button>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- บรรทัดที่ 1 -->
            <div class="row mt-2">
                <div class="col-4">
                    <label class="bold">หน่วยงาน</label>
                    <br>
                    <span> <?php echo $data['customer_name']; ?> </span>
                </div>
                <div class="col-4">
                    <label class="bold">สังกัด</label>
                    <br>
                    <span>
                        <?php
                        if ($data['affiliation'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['affiliation'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-4">
                    <label class="bold"><i class="fa fa-phone-square"></i> เบอร์ติดต่อ</label>
                    <br>
                    <span>
                        <?php
                        if ($data['phone_number'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['phone_number'];
                        }
                        ?>
                    </span>
                </div>
            </div>

            <!-- บรรทัดที่ 2 -->
            <div class="row mt-1">
                <div class="col-2">
                    <label class="bold">ที่อยู่</label>
                    <br>
                    <span>
                        <?php
                        if ($data['address'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['address'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">ตำบล</label>
                    <br>
                    <span>
                        <?php
                        if ($data['sub_district'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['sub_district'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">อำเภอ</label>
                    <br>
                    <span>
                        <?php
                        if ($data['district'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['district'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">จังหวัด</label>
                    <br>
                    <span>
                        <?php
                        if ($data['province'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['province'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">รหัสไปรษณีย์</label>
                    <br>
                    <span>
                        <?php
                        if ($data['zip_code'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['zip_code'];
                        }
                        ?>
                    </span>
                </div>
            </div>

            <!-- บรรทัดที่ 3 -->
            <div class="row mt-2">
                <div class="col-6">
                    <label class="bold">ชื่อโครงการ/ชื่องบ</label>
                    <br>
                    <span>
                        <?php
                        if ($data['project_name'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['project_name'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold"><i class="fas fa-money-check-alt"></i> ยอดงบ</label>
                    <br>
                    <span class="bold blueviolet">
                        <?php
                        echo isset($data['project_budget']) ? number_format($data['project_budget'], 2) . ' บาท' : '0.00' . ' บาท';
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">ประเภทงาน</label>
                    <br>
                    <span>
                        <?php echo $data['work_type_name']; ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold"> เซลล์ที่รับผิดชอบ</label>
                    <br>
                    <span>
                        <?php echo $data['sale_name']; ?>
                    </span>
                </div>
            </div>

            <!-- บรรทัดที่ 4 -->
            <div class="row mt-2">
                <div class="col-2">
                    <label class="bold">ประเภทการยื่นซอง</label>
                    <br>
                    <span>
                        <?php
                        if ($data['is_submission_required'] == 0) {
                            echo 'ไม่ยื่น';
                        } else {
                            echo 'ยื่น';
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">วันที่ยื่นซอง</label>
                    <br>
                    <span>
                        <?php
                        if ($data['submission_date'] == NULL) {
                            echo '-';
                        } else {
                            $timestamp = strtotime(datetime: $data['submission_date']);
                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">วันที่เริ่มสัญญา</label>
                    <br>
                    <span class="bold green">
                        <?php
                        if ($data['contract_start_date'] == NULL) {
                            echo '-';
                        } else {
                            $timestamp = strtotime(datetime: $data['contract_start_date']);
                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">วันที่สิ้นสุดสัญญา</label>
                    <br>
                    <span class="bold rad">
                        <?php
                        if ($data['contract_end_date'] == NULL) {
                            echo '-';
                        } else {
                            $timestamp = strtotime(datetime: $data['contract_end_date']);
                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">เดือนที่สิ้นสุดสัญญา</label>
                    <br>
                    <span>
                        <?php
                        $timestamp = strtotime(datetime: $data['contract_end_date']);
                        echo date('n', $timestamp);
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">ระยะวันครบกำหนดสัญญา</label>
                    <br>
                    <span>
                        <?php
                        $days_remaining = "-";

                        if ($data['contract_end_date'] != NULL) {
                            $today = new DateTime(); // วันที่ปัจจุบัน
                            $expire_date = new DateTime($data['contract_end_date']); // วันที่สิ้นสุดสัญญา
                        
                            // ถ้ายังไม่ถึงวันสิ้นสุด
                            if ($today < $expire_date) {
                                $interval = $today->diff($expire_date);
                                $days_remaining = $interval->format('%a วัน'); // %a คือจำนวนวันทั้งหมด
                            } else {
                                $days_remaining = "ครบกำหนดแล้ว";
                            }
                        }
                        echo $days_remaining;
                        ?>
                    </span>
                </div>
            </div>

            <!-- บรรทัดที่ 5 -->
            <div class="row mt-2">
                <div class="col-2">
                    <label class="bold">ประเภทการค้ำประกัน</label>
                    <br>
                    <span>
                        <?php
                        if ($data['guarantee_type'] == 0) {
                            echo 'ไม่มี';
                        } else if ($data['guarantee_type'] == 1) {
                            echo 'หนังสือค้ำ';
                        } else {
                            echo 'เงินสด';
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">เลขที่หนังสือ</label>
                    <br>
                    <span>
                        <?php
                        if ($data['guarantee_ref_number'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['guarantee_ref_number'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">วันที่ออกหนังสือ</label>
                    <br>
                    <span>
                        <?php
                        if ($data['guarantee_issue_date'] == NULL) {
                            echo '-';
                        } else {
                            $timestamp = strtotime(datetime: $data['guarantee_issue_date']);
                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">วันที่สิ้นสุดหนังสือ</label>
                    <br>
                    <span>
                        <?php
                        if ($data['guarantee_expire_date'] == NULL) {
                            echo '-';
                        } else {
                            $timestamp = strtotime(datetime: $data['guarantee_expire_date']);
                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">จำนวนเงินสด</label>
                    <br>
                    <span>
                        <?php
                        echo isset($data['guarantee_amount']) ? number_format($data['guarantee_amount'], 2) . ' บาท' : '0.00' . ' บาท';
                        ?>
                    </span>
                </div>
            </div>

            <!-- บรรทัดที่ 6 -->
            <div class="row mt-2">
                <div class="col-6">
                    <label class="bold">เลขที่ใบเสนอราคา</label>
                    <br>
                    <span>
                        <?php
                        if ($data['quotation_number'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['quotation_number'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">ผู้เปิดใบเสนอราคา</label>
                    <br>
                    <span>
                        <?php
                        if ($data['quotation_user'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['quotation_user'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">ผู้ลงข้อมูล</label>
                    <br>
                    <span>
                        <?php
                        if ($data['user_create'] == NULL) {
                            echo '-';
                        } else {
                            echo $data['user_create'];
                        }
                        ?>
                    </span>
                </div>
                <div class="col-2">
                    <label class="bold">เวลาลงข้อมูล</label>
                    <br>
                    <span>
                        <?php
                        if ($data['created_at'] == NULL) {
                            echo '-';
                        } else {
                            $timestamp = strtotime(datetime: $data['created_at']);
                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543) . ' ' . date('H:i:s', $timestamp) . ' น.';
                        }
                        ?>
                    </span>
                </div>
            </div>

            <!-- บรรทัดที่ 7 -->
            <div class="mt-4" style="display: flex; justify-content:flex-end; align-items: center;">
                <button class="btn-create opanModalAddProduct" data-id="<?php echo $data['customer_name']; ?>"
                    style="background: linear-gradient(135deg, #9fce8d 0%, #60ad41 100%);border-radius: 25px;">
                    <i class="fa fa-cart-arrow-down" style="font-size: 18px;"></i> เพิ่มรายการ/สินค้า
                </button>

                <div id="addProductModal" class="modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
                    aria-labelledby="exampleModalLabel">
                    <div class="modal-content" id="opanModalAddProduct">
                    </div>
                </div>

                &nbsp;

                <button class=" btn-create" data-id="<?php echo $data['site_id']; ?>"
                    style="background: linear-gradient(135deg, #ce8d8d 0%, #CD5C5C 100%);border-radius: 25px;">
                    <i class="fas fa-american-sign-language-interpreting" style="font-size: 18px;"></i>
                    ดูรายการสินค้า
                </button>

                &nbsp;

                <button class="btn-create" onclick="add_Capital(<?php echo $data['site_id']; ?>)"
                    style="background: linear-gradient(135deg, #e186f7 0%, #d02ff8 100%);border-radius: 25px;">
                    <i class="fas fa-pen-nib" style="font-size: 18px;"></i> กรอกทุน
                </button>

                &nbsp;

                <button class="btn-create"
                    style="background: linear-gradient(135deg, #5f45f0 0%, #3817f3 100%);border-radius: 25px;">
                    <i class="fas fa-chart-line" style="font-size: 18px;"></i> ดูรายละเอียดทุน
                </button>
            </div>

            <!-- บรรทัดที่ 8 -->
            <div class="mt-3">
                <button class="box-c2" style="font-size: 16px;">
                    <i class="fas fa-car"></i>&nbsp;
                    สำหรับจัดส่ง (เพิ่ม/แก้ไข ได้เท่านั้น)
                </button>
            </div>
            <?php
            $basePath = __DIR__ . '/';
            $viewFile = match ($data['user_role']) {
                'Warehouse' => 'project_delivery_create.php',
                'admin' => 'project_delivery_create.php',
                default => 'project_delivery_view.php',
            };
            if (file_exists($basePath . $viewFile)) {
                require_once($basePath . $viewFile);
            } else {
                echo "Error: ไม่พบไฟล์ที่ต้องการ (View file not found).";
            }
            ?>

            <!-- บรรทัดที่ 9 -->
            <div class="mt-3">
                <button class="box-c3" style="font-size: 16px;">
                    <i class="fas fa-money-check-alt"></i>&nbsp;
                    สำหรับ บัญชี/การเงิน เพิ่ม/แก้ไข ได้เท่านั้น
                </button>
            </div>

            <?php
            $basePath = __DIR__ . '/';

            $viewFile = match ($data['user_role']) {
                'Accounting' => 'project_acc_create.php',
                'admin' => 'project_acc_create.php',
                default => 'project_acc_view.php',
            };

            if (file_exists($basePath . $viewFile)) {
                require_once($basePath . $viewFile);
            } else {
                echo "Error: ไม่พบไฟล์ที่ต้องการ (View file not found).";
            }
            ?>
        </div>
    </div>


    <div id="createProjectModal" class="modal">
        <div class="modal-content" id="modalContent">
        </div>
    </div>

    <script>
        // Function Select
        $(document).ready(function () {
            $('.select-search').select2({
                placeholder: "ค้นหาหน่วยงาน...",
                allowClear: true
            });

            var modal = $("#addProductModal");
            $(document).on("click", ".opanModalAddProduct", function () {
                var customer_name = $(this).data("id");

                $("#opanModalAddProduct").load("project_add_products.php?customer_name=" + customer_name, function (response, status, xhr) {
                    if (status == "error") {
                        $("#opanModalAddProduct").html("<div class='modal-body'><p>Sorry, there was an error loading the form.</p></div>");
                    }
                    modal.fadeIn(200).addClass('show').css('display', 'block');
                    setTimeout(function () {
                        $("#opanModalAddProduct").find('input:visible').first().focus();
                    }, 200);
                });

                // ปิด Modal (ใช้ Delegated Event เพราะปุ่มปิดถูกโหลดมาทีหลัง)
                $(document).on("click", ".btn-close", function () {
                    modal.fadeOut(200);
                });
            });
        });

        function add_Capital($data) {
            window.location.href = 'project_capital_add.php?id=' + $data;
        }

        function view_data($data) {
            window.location.href = 'project_view.php?id=' + $data;
        }

    </script>
</body>

</html>