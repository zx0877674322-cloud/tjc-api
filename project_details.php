<!-- MENU  สมุดลงงานโครงการ -->
<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';
include 'api_project.php';
date_default_timezone_set('Asia/Bangkok');


$can_create = hasAction('news_create');
$can_edit = hasAction('news_edit');
$can_delete = hasAction('news_delete');

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title>สมุดลงงานโครงการ</title>
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
        <div class="row">
            <div class="col-6">
                <div class="header-title">
                    <h2>สมุดลงงานโครงการ</h2>
                </div>
            </div>
            <div class="col-6" style="justify-content: end;display: flex;">
                <button class="btn-create" onclick="create_data()">
                    <i class="fas fa-folder-plus"></i> บันทึกข้อมูลโครงการ
                </button>
            </div>
        </div>

        <!-- Action Search -->
        <div class="content mt-2" style="padding: 25px!important;">
            <div class="row">
                <div class="col-2">
                    <label class="label-text">เลขที่สัญญา</label>
                    <input type="text" class="form-input" placeholder="กรอกเลขที่สัญญา">
                </div>

                <div class="col-2">
                    <label class="label-text">วันที่เริ่มสัญญา</label>
                    <input type="date" class="form-input">
                </div>

                <div class="col-2">
                    <label class="label-text">วันที่สิ้นสุดสัญญา</label>
                    <input type="date" class="form-input">
                </div>

                <div class="col-3">
                    <label class="label-text">หน่วยงาน</label>
                    <select class="form-select select-search" name="">
                        <option value="">ค้นหาหน่วยงาน...</option>
                        <option value="1">โรงเรียนพังโคนวิทยาคม</option>
                        <option value="2">โรงเรียนลือคำหาญวารินชำราบ</option>
                        <option value="3">โรงเรียนบ้านหนองยาง</option>
                    </select>
                </div>

                <div class="col-3">
                    <span class="label-text">สถานะโครงการ</span>
                    <select class="form-select select-search" name="status">
                        <option value="">เลือกสถานะ...</option>
                        <?php foreach ($status as $data): ?>
                            <option value="<?= $data['status_id'] ?>">
                                <?= $data['status_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12" style="text-align: end;">
                    <button class="btn-search">
                        <i class="fas fa-search"></i>&nbsp;ค้นหา
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">หน้างาน</th>
                            <th>บริษัท</th>
                            <th class="text-center">เลขที่สัญญา</th>
                            <th class="text-center">วันที่เริ่มสัญญา</th>
                            <th class="text-center">วันที่สิ้นสุดสัญญา</th>
                            <th class="text-center">หน่วยงาน</th>
                            <th class="text-center">สถานะโครงการ</th>
                            <th class="text-center">เครื่องมือ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($projects) > 0): ?>
                            <?php foreach ($projects as $row): ?>
                                <?php $is_canceled = ($row['project_status'] == 4); ?>
                                <tr>
                                    <td class="text-center"><?php echo $row['site_id']; ?></td>
                                    <td>
                                        <?php echo $row['company_shortname']; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        if ($row['contract_number'] == '') {
                                            echo '<span style="color: red;">รอข้อมูล</span>';
                                        } else {
                                            echo $row['contract_number'];
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        if ($row['contract_start_date'] == '') {
                                            echo '<span style="color: red;">รอข้อมูล</span>';
                                        } else {
                                            $timestamp = strtotime(datetime: $row['contract_start_date']);
                                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        if ($row['contract_end_date'] == '') {
                                            echo '<span style="color: red;">รอข้อมูล</span>';
                                        } else {
                                            $timestamp = strtotime(datetime: $row['contract_end_date']);
                                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $row['customer_name'] ?? '-' ?>

                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['project_status'] == 0) {
                                            echo '<button class="b-status s-wait">ยังไม่เซ็นสัญญา</button>';
                                        } elseif ($row['project_status'] == 1) {
                                            echo '<button class="b-status s-contract">เซ็นสัญญา</button>';
                                        } elseif ($row['project_status'] == 2) {
                                            echo '<button class="b-status s-process">ระหว่างดำเนินการ</button>';
                                        } else if ($row['project_status'] == 3) {
                                            echo '<button class="b-status s-completed">ดำเนินการเสร็จสิ้น</button>';
                                        } else {
                                            echo '<button class="b-status s-cancel">ยกเลิก</button>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!$is_canceled): ?>
                                            <div class="tooltip">
                                                <button class="btn-view" onclick="view_data('<?php echo $row['site_id']; ?>')">
                                                    <i class="fas fa-eye" style="font-size: 18px;"></i>
                                                </button>
                                                <span class="tooltiptext">ดู</span>
                                            </div>
                                            <div class="tooltip">
                                                <button class="btn-edit" onclick="edit_data('<?php echo $row['site_id']; ?>')">
                                                    <i class="fas fa-edit" style="font-size: 18px;"></i>
                                                </button>
                                                <span class=" tooltiptext">แก้ไข</span>
                                            </div>
                                            <div class="tooltip">
                                                <button type="button" class="btn-cancel openCancelBtn"
                                                    data-id="<?php echo $row['site_id']; ?>">
                                                    <i class="fa fa-close" style="font-size: 18px;"></i>
                                                </button>
                                                <span class="tooltiptext">ยกเลิก</span>
                                            </div>

                                            <div id="cancelModal" class="modal" tabindex="-1" data-bs-backdrop="static"
                                                data-bs-keyboard="false" aria-labelledby="exampleModalLabel">
                                                <div class="modal-content" id="modalCancel" style="width: 30% !important;">
                                                </div>
                                            </div>

                                        <?php endif; ?>
                                        <div class="tooltip">
                                            <button class="btn-flow">
                                                <i class="fas fa-bezier-curve" style="font-size: 18px;"></i>
                                            </button>
                                            <span class="tooltiptext">Flow</span>
                                        </div>
                                        <div class="tooltip">
                                            <button class="btn-flow"
                                                style="background-color: #f59e0b; border: 1px solid #d97706;"
                                                onclick="service_data('<?php echo $row['site_id']; ?>')">
                                                <i class="fas fa-tools" style="font-size: 18px;"></i>
                                            </button>
                                            <span class="tooltiptext">Service</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center p-3">--- ไม่พบข้อมูล ---</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): // แสดงเฉพาะเมื่อมีมากกว่า 1 หน้า ?>
                    <div class="row mt-2">
                        <div class="col-6">
                            <small class="text-muted">
                                แสดง
                                <?php echo ($total_rows > 0) ? $offset + 1 : 0; ?> ถึง
                                <?php echo min($offset + $limit, $total_rows); ?>
                                จากทั้งหมด
                                <?php echo $total_rows; ?> รายการ
                            </small>
                        </div>

                        <div class="col-6">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-end mb-0">

                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>

                                    <?php
                                    $range = 2; // จำนวนหน้าที่จะแสดงรอบๆ หน้าปัจจุบัน
                                    for ($i = 1; $i <= $total_pages; $i++):
                                        if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                                            ?>
                                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>

                                        <?php elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; endfor; ?>

                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>

                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>




    <script>
        // Function Select
        $(document).ready(function () {
            $('.select-search').select2({
                placeholder: "ค้นหาหน่วยงาน...",
                allowClear: true
            });
        });

        // Modal Script
        $(document).ready(function () {
            var modal = $("#cancelModal");

            $(document).on("click", ".openCancelBtn", function () {
                var siteId = $(this).data("id");

                $("#modalCancel").load("project_cancel.php?site_id=" + siteId, function (response, status, xhr) {
                    if (status == "error") {
                        $("#modalCancel").html("<div class='modal-body'><p>Sorry, there was an error loading the form.</p></div>");
                    }
                    modal.fadeIn(200);
                    modal.addClass('show');
                    modal.css('display', 'block');
                });
            });

            $(document).on("click", ".btn-close", function () {
                modal.fadeOut(200, function () {
                    modal.removeClass('show');
                    modal.css('display', 'none');
                });
            });

        });

        function create_data() {
            window.location.href = 'project_create_update.php';
        }

        function view_data($data) {
            window.location.href = 'project_view.php?id=' + $data
        }

        function edit_data($data) {
            window.location.href = 'project_create_update.php?id=' + $data;
        }

        function add_capital($data) {

        }
        function service_data($data) {
            window.location.href = 'ServiceRequest.php?id=' + $data;
        }
    </script>
</body>

</html>