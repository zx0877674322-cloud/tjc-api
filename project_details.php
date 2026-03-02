<!-- MENU  สมุดลงงานโครงการ -->
<?php
session_start();
include 'auth.php';
include 'db_connect.php';

$projects = [];
$total_pages = 0;
$total_rows = 0;
$page = 1;
$queryString = '';

$action = 'get_project';
include 'project_api.php';

// --- ดึงข้อมูลบริษัท ---
$action = 'get_companys';
include 'project_api.php';

// --- ดึงข้อมูลหน่วยงาน --
$action = 'get_customername';
include 'project_api.php';

// --- ดึงข้อมูลสังกัด--
$action = 'get_affiliation';
include 'project_api.php';

// --- ดึงข้อมูลตำบล--
$action = 'get_sub_district';
include 'project_api.php';

// --- ดึงข้อมูลอำเภอ--
$action = 'get_district';
include 'project_api.php';

// --- ดึงข้อมูลจังหวัด--
$action = 'get_province';
include 'project_api.php';

// --- ดึงข้อมูลชื่อโครงการ--
$action = 'get_project_name';
include 'project_api.php';

// --- ดึงข้อมูลงบโครงการ--
$action = 'get_project_budget';
include 'project_api.php';

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
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

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
        <form method="GET" action="" id="form_search">
            <div class="content mt-2" style="padding: 25px!important;">
                <div class="row">
                    <div class="col-2">
                        <span class="label-text">บริษัท</span>
                        <select class="form-select select-search" name="company_id" id="company_id"
                            data-placeholder="-- เลือกบริษัท --">
                            <option value="">-- เลือกบริษัท --</option>
                            <?php foreach ($companys as $company): ?>
                                <option value="<?= $company['id'] ?>">
                                    <?= $company['company_shortname'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-2">
                        <label class="label-text">วันที่สิ้นสุดสัญญา</label>
                        <input type="date" class="form-input" id="contract_end_date" name="contract_end_date">
                    </div>

                    <div class="col-8">
                        <label class="label-text">หน่วยงาน</label>
                        <select class="form-select select-search" name="customer_name" id="customer_name"
                            data-placeholder="-- เลือกชื่อหน่วยงาน/ชื่อลูกค้า --">
                            <option value="">-- เลือกชื่อหน่วยงาน/ชื่อลูกค้า --</option>
                            <?php foreach ($customer_name as $item_name): ?>
                                <option value="<?= $item_name['customer_name'] ?>">
                                    <?= $item_name['customer_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mt-1">
                    <div class="col-3">
                        <span class="label-text">สังกัด</span>
                        <select class="form-select select-search" name="affiliation" id="affiliation"
                            data-placeholder="-- เลือกสังกัด --">
                            <option value="">-- เลือกสังกัด --</option>
                            <?php foreach ($affiliation as $data): ?>
                                <option value="<?= $data['affiliation'] ?>">
                                    <?= $data['affiliation'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <span class="label-text">ตำบล</span>
                        <select class="form-select select-search" name="sub_district" id="sub_district"
                            data-placeholder="-- เลือกตำบล --">
                            <option value="">-- เลือกตำบล --</option>
                            <?php foreach ($sub_district as $data): ?>
                                <option value="<?= $data['sub_district'] ?>">
                                    <?= $data['sub_district'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <span class="label-text">อำเภอ</span>
                        <select class="form-select select-search" name="district" id="districts"
                            data-placeholder="-- เลือกอำเภอ --">
                            <option value="">-- เลือกอำเภอ --</option>
                            <?php foreach ($district as $data): ?>
                                <option value="<?= $data['district'] ?>">
                                    <?= $data['district'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <span class="label-text">จังหวัด</span>
                        <select class="form-select select-search" name="province" id="provinces"
                            data-placeholder="-- เลือกจังหวัด --">
                            <option value="">-- เลือกจังหวัด --</option>
                            <?php foreach ($province as $data): ?>
                                <option value="<?= $data['province'] ?>">
                                    <?= $data['province'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mt-1">
                    <div class="col-9">
                        <span class="label-text">ชื่อโครงการ</span>
                        <select class="form-select select-search" name="project_name" id="project_name"
                            data-placeholder="-- เลือกชื่อโครงการ --">
                            <option value="">-- เลือกชื่อโครงการ --</option>
                            <?php foreach ($project_name as $data): ?>
                                <option value="<?= $data['project_name'] ?>">
                                    <?= $data['project_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <span class="label-text">งบโครงการ</span>
                        <select class="form-select select-search" name="project_budget" id="project_budget"
                            data-placeholder="-- เลือก --">
                            <option value="">-- เลือก --</option>
                            <?php foreach ($project_budget as $data): ?>
                                <option value="<?= $data['project_budget'] ?>">
                                    <?= isset($data['project_budget']) ? number_format($data['project_budget'], 2) . ' บาท' : '0.00' . ' บาท'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mt-1" style="justify-content: end;">
                    <div class="col-2 mt-4" style="text-align: center;">
                        <button type="submit" class="btn-search"
                            style="font-size: 16px;font-weight: 600;width: 100% !important;">
                            <i class="fas fa-search"></i>&nbsp;ค้นหา
                        </button>
                    </div>
                    <div class="col-2 mt-4" style="text-align: center;">
                        <button class="btn-search"
                            style="font-size: 16px;font-weight: 600;width: 100% !important;background: linear-gradient(135deg, #fe4f4f 0%, #fe0000 100%);">
                            <i class="fas fa-history"> ล้างค่า</i>
                        </button>
                    </div>
                </div>
            </div>
        </form>



        <div style="display: flex;justify-content: end;">

            <button class="btn-create openChooseBtn"
                style="background: linear-gradient(135deg, #e186f7 0%, #d02ff8 100%);border-radius: 25px;">
                <i class="fas fa-pen-nib" style="font-size: 18px;"></i> เลือกรายการกรอกทุน
            </button>

            <div id="chooseListsModal" class="modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
                aria-labelledby="exampleModalLabel">
                <div class="modal-content" id="modalChooseLists" style="width: 30% !important;">
                </div>
            </div>
            &nbsp;
            <button class="btn-create"
                style="background: linear-gradient(135deg, #5f45f0 0%, #3817f3 100%);border-radius: 25px;">
                <i class="fas fa-chart-line" style="font-size: 18px;"></i> รายละเอียดทุนสินค้า
            </button>

        </div>

        <!-- Table -->
        <div class="table-card mt-2">
            <div class="table-responsive" style="max-height: 100% !important;">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center" style="border:unset !important;">#</th>
                            <th style="border:unset !important;min-width:unset !important;">บริษัท</th>
                            <th class="text-center" style="border:unset !important;">วันที่สิ้นสุดสัญญา</th>
                            <th class="text-center" style="border:unset !important;">ระยะเวลาครบกำหนดส่ง</th>
                            <th class="text-center" style="border:unset !important;">หน่วยงาน</th>
                            <th class="text-center" style="border:unset !important;">สังกัด</th>
                            <th class="text-center" style="border:unset !important;">ที่อยู่</th>
                            <th class="text-center" style="border:unset !important;">ชื่อโครงการ</th>
                            <th class="text-center" style="border:unset !important;">งบโครงการ</th>
                            <th class="text-center" style="border:unset !important;">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="loading-row">
                            <td colspan="10" class="text-center" style="padding: 80px; background: #fff;">
                                <i class="fas fa-circle-notch fa-spin"
                                    style="font-size: 40px; color: #3498db; margin-bottom: 15px;"></i>
                                <p style="font-weight: bold; font-size: 18px; color: #333;">กำลังดึงข้อมูลโครงการ...</p>
                            </td>
                        </tr>
                        <?php if (!empty($projects)): ?>
                            <?php foreach ($projects as $row): ?>
                                <?php
                                $is_Check = ($row['project_status'] == 1 || $row['project_status'] == 2 || $row['project_status'] == 3);
                                ?>
                                <tr class="project-row" style="display: none;"
                                    style="<?php echo ($row['project_status'] == 5) ? 'text-decoration: line-through !important;color: #888 !important;opacity: 0.7 !important;' : ''; ?>">
                                    <td class="text-center"
                                        style="border: none !important;border-bottom: 1px solid #e1e1e1 !important;">
                                        <div class="row">
                                            <div class="col-12">
                                                <button class="btn-view tooltip" title="คลิกดูรายการ"
                                                    onclick="view_data('<?php echo $row['site_id']; ?>')">
                                                    <?php echo $row['site_id']; ?>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td
                                        style="border: none !important;border-bottom: 1px solid #e1e1e1 !important;min-width:unset !important;">
                                        <?php echo $row['company_shortname']; ?>
                                    </td>
                                    <td class="text-center"
                                        style="border: none !important;border-bottom: 1px solid #e1e1e1 !important">
                                        <?php
                                        if ($row['project_status'] == 1) {
                                            echo '<span style="color: blue;">ไม่มีสัญญา</span>';
                                        } else if ($row['contract_end_date'] == '') {
                                            echo '<span style="color: red;">รอข้อมูล</span>';
                                        } else {
                                            $timestamp = strtotime(datetime: $row['contract_end_date']);
                                            echo date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center"
                                        style="border: none !important;border-bottom: 1px solid #e1e1e1 !important">
                                        <?php
                                        $days_remaining = "-";

                                        if ($row['contract_end_date'] != NULL) {
                                            $today = new DateTime(); // วันที่ปัจจุบัน
                                            $expire_date = new DateTime($row['contract_end_date']); // วันที่สิ้นสุดสัญญา
                                
                                            // ถ้ายังไม่ถึงวันสิ้นสุด
                                            if ($today < $expire_date) {
                                                $interval = $today->diff($expire_date);
                                                $days_remaining = $interval->format('%a วัน'); // %a คือจำนวนวันทั้งหมด
                                            } else {
                                                $days_remaining = "ครบกำหนดแล้ว";
                                            }
                                        }
                                        echo $days_remaining; ?>
                                    </td>
                                    <td class="cut-text"
                                        style="border: none !important;border-bottom: 1px solid #e1e1e1 !important;min-width: 100px;">
                                        <?php echo $row['customer_name'] ?? '-' ?>

                                    </td>
                                    <td class="cut-text"
                                        style="border: none !important;border-bottom: 1px solid #e1e1e1 !important;min-width: 100px;">
                                        <?= $row['affiliation'] ?>
                                    </td>
                                    <td class="cut-text"
                                        style="border: none !important;border-bottom: 1px solid #e1e1e1 !important;min-width: 100px;">
                                        <?= $row['residence'] ?>
                                    </td>
                                    <td class="cut-text"
                                        style="border: none !important;border-bottom: 1px solid #e1e1e1 !important;min-width: 120px;">
                                        <?= $row['project_name'] ?>
                                    </td>
                                    <td style="border: none !important;border-bottom: 1px solid #e1e1e1 !important">
                                        <?= number_format($row['project_budget'], 2) ?>
                                    </td>
                                    <td style="border: none !important;border-bottom: 1px solid #e1e1e1 !important">
                                        <div class="row">
                                            <div class="col-12">
                                                <i class="fas fa-people-carry"></i> ส่งมอบแล้ว
                                            </div>
                                            <div class="col-12">
                                                <i class="fas fa-money-check-alt"></i> เงินยังไม่เข้า
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="project-row" style="display: none;">
                                <td colspan="10" class="text-center p-3">--- ไม่พบข้อมูล ---</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if (isset($total_pages) && $total_pages > 1): ?>
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
                                        <a class="page-link"
                                            href="?page=<?php echo $page - 1; ?>&<?php echo $queryString; ?>"
                                            aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>

                                    <?php
                                    $range = 2;
                                    for ($i = 1; $i <= $total_pages; $i++):
                                        if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)):
                                            ?>
                                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $queryString; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; endfor; ?>

                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link"
                                            href="?page=<?php echo $page + 1; ?>&<?php echo $queryString; ?>"
                                            aria-label="Next">
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


        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let seconds = 0;
                const timerDisplay = document.getElementById('timer-count');
                const timerInterval = setInterval(() => {
                    seconds++;
                    if (timerDisplay) timerDisplay.innerText = seconds;
                }, 1000);

                setTimeout(() => {
                    clearInterval(timerInterval); // Stop timer
                    const loadingRow = document.getElementById('loading-row');
                    if (loadingRow) loadingRow.style.display = 'none';
                    const rows = document.querySelectorAll('.project-row');
                    rows.forEach(row => {
                        row.style.display = 'table-row';
                    });
                    const buttons = document.querySelectorAll('.btn-search');
                    buttons.forEach(btn => {
                        btn.style.display = 'inline-block';
                    });
                }, 1000);
            });

            $(document).ready(function () {
                $('.select-search').select2({
                    allowClear: true,
                });
            });

            var modal_choose = $("#chooseListsModal");
            // Modal เลือกรายการเลขหน้างาน เพื่อกรอกทุน
            $(document).on("click", ".openChooseBtn", function () {
                $("#modalChooseLists").load("project_choose_lists.php", function (response, status, xhr) {
                    if (status == "error") {
                        $("#modalChooseLists").html(
                            "<div class='modal-body'><p>Sorry, there was an error loading the form.</p></div>"
                        );
                    } else {
                        $(this).find('.select-search').select2({
                            allowClear: true,
                            dropdownParent: modal_choose
                        });
                    }
                    modal_choose.fadeIn(200);
                    modal_choose.addClass('show');

                });
            });

            $(document).on("click", ".btn-close", function () {
                modal_choose.fadeOut(200, function () {
                    modal_choose.removeClass('show');

                });
            });

            // บันทึกข้อมูลโครงการ
            function create_data() {
                window.location.href = 'project_create_update.php';
            }

            // ดูข้อมูลโครงการ
            function view_data($data) {
                window.location.href = 'project_view.php?id=' + $data
            }

            // กรอกทุน
            function add_Capital($data) {
                window.location.href = 'project_capital_add.php';
            }
        </script>
</body>

</html>