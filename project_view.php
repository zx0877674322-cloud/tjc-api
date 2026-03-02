<?php
require_once 'auth.php';
require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

// หากไม่มี id ส่งมา ให้กลับไปที่หน้าแรก
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: project_details.php");
    exit();
}


$doc_id = $_GET['id'];
$user = $_SESSION['user_id'];

$sql = "SELECT  a.*, b.company_name , 
                e.customer_name , e.affiliation , e.phone_number , 
                e.address , e.sub_district, e.district, e.province, e.zip_code,
                h.work_type_name , u_sale.fullname AS sale_name , u_quo.fullname AS quotation_user , 
                u_create.fullname AS user_create, u_create.role AS user_role , l.order_status_main AS order_status_main
        FROM project_contracts a
        LEFT JOIN companies b ON a.company_id = b.id
        LEFT JOIN customers e ON a.customer_id = e.customer_id 
        LEFT JOIN project_work_type h ON a.work_type_id = h.work_type_id
        LEFT JOIN users u_sale ON a.sale_user_id = u_sale.id  
        LEFT JOIN users u_quo ON a.quotation_user_id = u_quo.id
        LEFT JOIN users u_create ON $user = u_create.id 
        LEFT JOIN project_lists l ON a.site_id = l.site_id
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

    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class='main-content'>
        <a href="javascript:history.back()">
            <i class="fas fa-angle-double-left" style="color: red;"> กลับหน้าหลัก</i>
        </a>
        <div class='content mt-2'>
            <div class="row">
                <div class="col-10">
                    <div class="header-title">
                        <h3><i class="fas fa-building"></i> <?php echo $data['company_name']; ?></h3>
                    </div>
                </div>
                <div class="col-2" style="justify-items: end;">
                    <h2 style="color:#60ad41 !important;">
                        หน้างานเลขที่
                        <?php echo $data['site_id']; ?>
                    </h2>
                </div>
            </div>

            <div class="container">
                <details class="custom-accordion">
                    <summary>
                        <label>โครงการ :
                            <?php
                            if ($data['project_name'] == NULL) {
                                echo '-';
                            } else {
                                echo $data['project_name'];
                            }
                            ?>
                            <br>
                            งบประมาณ :
                            <?php
                            echo isset($data['project_budget']) ? number_format($data['project_budget'], 2) . ' บาท' : '0.00' . ' บาท';
                            ?>
                        </label>
                        <span class="icon"><i class="fas fa-chevron-up"></i></span>
                    </summary>
                    <!-- ข้อมูลข้างใน -->
                    <div class="content">
                        <div class="row" style="justify-content: space-between;">
                            <div class="col-4">
                                <button class="box-c1">
                                    <i class="fas fa-book"></i>&nbsp;
                                    สัญญาเลขที่ &nbsp;
                                    <?php
                                    if ($data['contract_number'] == '') {
                                        echo '<span>รอข้อมูล</span>';
                                    } else {
                                        echo $data['contract_number'];
                                    }
                                    ?>
                                </button>
                            </div>
                            <div class="col-4">
                                <div class="row mt-2">
                                    <div class="col-6" style="text-align: end;">
                                        <label class="bold"><i class="fa fa-warning"></i> สถานะโครงการ</label>
                                    </div>
                                    <div class="col-6" style="justify-items: start;">
                                        <?php if ($data['project_status'] == 1) {
                                            echo '<button class="b-status s-wait">ไม่มีสัญญา</button>';
                                        } elseif ($data['project_status'] == 2) {
                                            echo '<button class="b-status s-contract">เซ็นสัญญา</button>';
                                        } else if ($data['project_status'] == 3) {
                                            echo '<button class="b-status s-completed">ระหว่างดำเนินการ</button>';
                                        } else if ($data['project_status'] == 4) {
                                            echo '<button class="b-status s-send">ดำเนินการเสร็จสิ้น</button>';
                                        } else {
                                            echo '<button class="b-status s-cancel">ยกเลิกโครงการ</button>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-2 mt-2">
                                <?php
                                $is_Check = ($data['project_status'] == 1 || $data['project_status'] == 2);
                                ?>
                                <?php if ($is_Check): ?>
                                    <button class="btn-edit tooltip" title="แก้ไขรายการ" style="width: 100% !important;"
                                        onclick="edit_data('<?php echo $data['site_id']; ?>')">
                                        <i class="fas fa-edit" style="font-size: 16px;"></i> แก้ไขรายการ
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="col-2 mt-2">
                                <button type="button" class="btn-cancel tooltip openCancelBtn"
                                    style="width: 100% !important;" data-id="<?php echo $data['site_id']; ?>"
                                    title="ยกเลิกรายการ">
                                    <i class="fa fa-close" style="font-size: 18px;"></i> ยกเลิกโครงการ
                                </button>
                            </div>
                        </div>

                        <!-- บรรทัดที่ 1 -->
                        <div class="row mt-2">
                            <div class="col-4">
                                <label class="bold">หน่วยงาน</label>
                                <br>
                                <span>
                                    <?php echo $data['customer_name']; ?>
                                </span>
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
                                <label class="bold" style="color: #170ebd;"><i class="fa fa-phone-square"></i>
                                    เบอร์ติดต่อ</label>
                                <br>
                                <span style="color: #170ebd;">
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
                        <hr style="border: 1px solid #e4e4e4;">

                        <!-- บรรทัดที่ 3 -->
                        <div class="row mt-2">
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
                            <div class="col-3">
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
                            <div class="col-4">
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
                        </div>

                        <!-- บรรทัดที่ 4 -->
                        <div class="row">
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
                                    if ($data['contract_start_date'] == NULL && $data['contract_end_date'] == NULL) {
                                        echo '-';
                                    } else {
                                        $timestamp = strtotime(datetime: $data['contract_end_date']);
                                        echo date('n', $timestamp);
                                    }


                                    ?>
                                </span>
                            </div>
                            <div class="col-3">
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
                            <div class="col-3">
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

                        <!--บรรทัดที่ 5 -->
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
                            <div class="col-3">
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
                            <div class="col-3">
                                <label class="bold">จำนวนเงินสด</label>
                                <br>
                                <span>
                                    <?php
                                    echo isset($data['guarantee_amount']) ? number_format($data['guarantee_amount'], 2) . ' บาท' : '0.00' . ' บาท';
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- บรรทัดที่ 7 -->
                        <div class="mt-2" style="display: flex; justify-content:flex-end; align-items: center;">
                            <?php if ($data['order_status_main'] == 0): ?>
                                <button class="btn-create opanModalAddProduct" data-id="<?php echo $data['site_id']; ?>"
                                    style="background: linear-gradient(135deg, #9fce8d 0%, #60ad41 100%);border-radius: 25px;">
                                    <i class="fa fa-cart-arrow-down" style="font-size: 18px;"></i> เพิ่มรายการ/สินค้า
                                </button>
                            <?php endif; ?>

                            <div id="addProductModal" class="modal" tabindex="-1" data-bs-backdrop="static"
                                data-bs-keyboard="false" aria-labelledby="exampleModalLabel">
                                <div class="modal-content" id="opanModalAddProduct" style="width: 80%;">
                                </div>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="container mt-2">
                <details class="custom-accordion">
                    <summary>
                        <i class="fas fa-shopping-basket"> &nbsp;รายการ</i>
                        <span class="icon"><i class="fas fa-chevron-up"></i></span>
                    </summary>
                    <div class="content">
                        <?php require_once('project_view_products.php'); ?>
                    </div>
                </details>
            </div>

            <div class="container mt-2">
                <details class="custom-accordion">
                    <summary>
                        <i class="fas fa-car"> &nbsp; สำหรับจัดส่ง (เพิ่ม/แก้ไข ได้เท่านั้น)</i>
                        <span class="icon"><i class="fas fa-chevron-up"></i></span>
                    </summary>
                    <div class="content">

                    </div>
                </details>
            </div>

            <div class="container mt-2">
                <details class="custom-accordion">
                    <summary>
                        <i class="fas fa-money-check-alt"> &nbsp; สำหรับ บัญชี/การเงิน เพิ่ม/แก้ไข ได้เท่านั้น</i>
                        <span class="icon"><i class="fas fa-chevron-up"></i></span>
                    </summary>
                    <div class="content">

                    </div>
                </details>
            </div>
        </div>
    </div>

    <div id="createProjectModal" class="modal">
        <div class="modal-content" id="modalContent">
        </div>
    </div>

    <!-- Modal remark cc -->
    <div id="cancelModal" class="modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
        aria-labelledby="exampleModalLabel">
        <div class="modal-content" id="modalCancel" style="width: 30% !important;">
        </div>
    </div>


    <script>
        // Function Select
        $(document).ready(function () {
            $('.select-search').select2({
                placeholder: "ค้นหาหน่วยงาน...",
                allowClear: true
            });

            var modal_cancel = $("#cancelModal");
            var modal = $("#addProductModal");

            // Modal ยกเลิก
            $(document).on("click", ".openCancelBtn", function () {
                var siteId = $(this).data("id");
                $("#modalCancel").load("project_cancel.php?site_id=" + siteId, function (response, status,
                    xhr) {
                    if (status == "error") {
                        $("#modalCancel").html(
                            "<div class='modal-body'><p>Sorry, there was an error loading the form.</p></div>"
                        );
                    }
                    modal_cancel.fadeIn(200);
                    modal_cancel.addClass('show');
                    modal_cancel.css('display', 'block');
                });

                $(document).on("click", ".close-modal, .btn-close", function () {
                    modal_cancel.fadeOut(200, function () {
                        modal_cancel.removeClass('show');
                        modal_cancel.css('display', 'none');
                    });
                });
            });

            $(document).on("click", ".opanModalAddProduct", function () {
                var id = $(this).data("id");

                $("#opanModalAddProduct").load("project_add_products.php?id=" + id, function (response, status, xhr) {
                    if (status == "error") {
                        $("#opanModalAddProduct").html("<div class='modal-body'><p>Sorry, there was an error loading the form.</p></div>");
                    }
                    modal.fadeIn(200).addClass('show').css('display', 'block');

                    const targetInput = $("#opanModalAddProduct").find('input[type="text"]:not([readonly])').first();
                    targetInput.focus();
                });

                $(document).on("click", ".close-modal,.btn-close", function () {
                    modal.fadeOut(200);
                    // location.reload();
                });
            });
        });

        function add_Capital($data) {
            window.location.href = 'project_capital_add.php?id=' + $data;
        }

        // แก้ไขข้อมูลโครงการ
        function edit_data($data) {
            window.location.href = 'project_create_update.php?id=' + $data;
        }


    </script>
</body>

</html>