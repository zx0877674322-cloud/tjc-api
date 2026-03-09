<?php
session_start();
require_once 'auth.php'; // ตรวจสอบ Login

// ✅ ตั้งเวลาไทย
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบ Session
if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';

$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : null;
$edit_data = null;
$existing_work_data = 'null';

if ($edit_id) {
    $sql_edit = "SELECT * FROM reports WHERE id = $edit_id LIMIT 1";
    $res_edit = $conn->query($sql_edit);
    if ($res_edit && $row = $res_edit->fetch_assoc()) {
        $edit_data = $row;
        // ระเบิดข้อมูล Array
        $customers = array_map('trim', explode(',', $row['work_result']));
        $projects = preg_split('/,(?!\d)/', $row['project_name']);
        $statuses = array_map('trim', explode(',', $row['job_status']));
        $next_apps = array_map('trim', explode(',', $row['next_appointment']));
        $notes = explode("\n", $row['additional_notes']);
        // จัดการสรุปการเข้าพบ
        $raw_summaries = preg_split('/\n(?=•)/u', $row['activity_detail']);
        $clean_summaries = array_map(function ($line) {
            return preg_replace('/^•\s*.*:\s*/u', '', trim($line));
        }, $raw_summaries);

        $existing_work_data = json_encode([
            'customers' => $customers,
            'projects' => $projects,
            'statuses' => $statuses,
            'next_apps' => $next_apps,
            'summaries' => $clean_summaries,
            'notes' => $notes
        ], JSON_UNESCAPED_UNICODE);
    }
} // ✅ ปิดปีกกา if ($edit_id) ตรงนี้ (สำคัญมาก!)

// ✅ ย้ายออกมาข้างนอกปีกกา เพื่อให้ทำงานทั้งตอน "เพิ่มใหม่" และ "แก้ไข"
// ถ้าเป็นแก้ไข -> ใช้วันที่เดิม | ถ้าเป็นใหม่ -> ใช้วันนี้ (Y-m-d)
$default_date = ($edit_id && isset($edit_data['report_date'])) ? $edit_data['report_date'] : date('Y-m-d');

$message = "";

// =========================================================
// 🌐 1. ดึงข้อมูลจังหวัดและภาค
// =========================================================
$provinces_data = [];
$sql_prov = "SELECT name_th AS province, region_name AS region FROM master_provinces ORDER BY region_name ASC, name_th ASC";
$res_prov = $conn->query($sql_prov);
if ($res_prov && $res_prov->num_rows > 0) {
    while ($row = $res_prov->fetch_assoc()) {
        $reg = $row['region'] ?: 'อื่นๆ';
        $provinces_data[$reg][] = $row['province'];
    }
}

// =========================================================
// 🚀 2. ดึงรายชื่อลูกค้า (จากแผนงานของตัวเองเท่านั้น)
// =========================================================
$customers_data = [];
$my_name = $conn->real_escape_string($_SESSION['fullname']);
$sql_cus = "SELECT DISTINCT contact_person FROM work_plans 
            WHERE reporter_name = '$my_name' AND contact_person != '' 
            ORDER BY contact_person ASC";
$res_cus = $conn->query($sql_cus);
while ($row = $res_cus->fetch_assoc()) {
    $customers_data[] = $row['contact_person'];
}

// --- 2. รายชื่อสำหรับเช็ค เก่า/ใหม่ (ดึงจากฐานข้อมูลลูกค้าหลัก) ---
$master_customers_list = [];
$sql_master = "SELECT customer_name FROM master_customers";
$res_master = $conn->query($sql_master);
while ($row = $res_master->fetch_assoc()) {
    $master_customers_list[] = $row['customer_name'];
}

$current_month = date('n');
$current_year = date('Y');
$show_target_input = false;

$sql_check_target = "SELECT id FROM sales_targets 
                     WHERE reporter_name = '$my_name' 
                     AND target_month = $current_month 
                     AND target_year = $current_year";
$res_target = $conn->query($sql_check_target);
if ($res_target->num_rows == 0) {
    $show_target_input = true; // ยังไม่มีเป้าเดือนนี้ ให้โชว์ช่องกรอก
}

// --- PHP Functions (Upload) ---
function uploadReceipt($fileInputName)
{
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        $target_dir = __DIR__ . "/uploads/";
        if (!file_exists($target_dir)) {
            @mkdir($target_dir, 0777, true);
        }
        $fileExtension = pathinfo($_FILES[$fileInputName]["name"], PATHINFO_EXTENSION);
        $newFileName = "receipt_" . time() . "_" . rand(100, 999) . "." . $fileExtension;
        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $target_dir . $newFileName)) {
            return $newFileName;
        }
    }
    return "";
}

function uploadMultipleReceipts($fileInputName)
{
    $uploadedFiles = [];
    if (isset($_FILES[$fileInputName])) {
        $fileCount = count($_FILES[$fileInputName]['name']);
        $target_dir = __DIR__ . "/uploads/";
        if (!file_exists($target_dir)) {
            @mkdir($target_dir, 0777, true);
        }
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES[$fileInputName]['error'][$i] == 0) {
                $fileExtension = pathinfo($_FILES[$fileInputName]["name"][$i], PATHINFO_EXTENSION);
                $newFileName = "fuel_" . time() . "_" . $i . "_" . rand(100, 999) . "." . $fileExtension;
                if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"][$i], $target_dir . $newFileName)) {
                    $uploadedFiles[] = $newFileName;
                }
            }
        }
    }
    return implode(',', $uploadedFiles);
}

// --- Form Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. รับค่าส่วน Header
    $report_date = $_POST['report_date'];
    $reporter_name = $_SESSION['fullname'];
    $work_type = $_POST['work_type'];

    if ($work_type == 'company') {
        $area = "เข้าบริษัท (สำนักงาน)";
        $province = "กรุงเทพมหานคร";
        $gps = "Office";
        $gps_address = "สำนักงานใหญ่";
    } else {
        $area = $_POST['area_zone'] ?? 'ไม่ได้ระบุ';
        $province = $_POST['province'] ?? '';
        $gps = $_POST['gps'] ?? '';
        $gps_address = $_POST['gps_address'] ?? '';
    }

    // 2. จัดการค่าใช้จ่ายและไฟล์แนบ
    $fuel_costs_array = isset($_POST['fuel_cost']) ? $_POST['fuel_cost'] : [];
    $fuel_cost_sum = array_sum(array_map('floatval', $fuel_costs_array));
    $fuel_receipt = uploadMultipleReceipts('fuel_receipt_file');

    $accommodation_cost = !empty($_POST['accommodation_cost']) ? floatval($_POST['accommodation_cost']) : 0;
    $other_cost = !empty($_POST['other_cost']) ? floatval($_POST['other_cost']) : 0;
    $other_cost_detail = $_POST['other_cost_detail'] ?? '';
    $accommodation_receipt = uploadReceipt('accommodation_receipt_file');
    $other_receipt = uploadReceipt('other_receipt_file');
    $total_expense = $fuel_cost_sum + $accommodation_cost + $other_cost;

    $problem = $_POST['problem'] ?? '';
    $suggestion = $_POST['suggestion'] ?? '';

    // 🔴 ส่วนสำคัญ: รักษาไฟล์เดิมกรณีแก้ไขแล้วไม่มีการเลือกไฟล์ใหม่
    if ($edit_id && $edit_data) {
        if (empty($fuel_receipt))
            $fuel_receipt = $edit_data['fuel_receipt'];
        if (empty($accommodation_receipt))
            $accommodation_receipt = $edit_data['accommodation_receipt'];
        if (empty($other_receipt))
            $other_receipt = $edit_data['other_receipt'];
    }

    // 3. รับค่าส่วน Work Details (Loop Box)
    $work_results = $_POST['work_result'] ?? [];
    $project_names = $_POST['project_name'] ?? [];
    $visit_summaries = $_POST['visit_summary'] ?? [];
    $project_values = $_POST['project_value'] ?? [];
    $job_statuses = $_POST['job_status'] ?? [];
    $next_appointments = $_POST['next_appointment'] ?? [];
    $additional_notes_arr = $_POST['additional_notes'] ?? [];

    $combined_customers = [];
    $combined_projects = [];
    $combined_summaries = [];
    $combined_statuses = [];
    $combined_next_apps = [];

    for ($i = 0; $i < count($work_results); $i++) {
        $name = trim($work_results[$i]);
        if (empty($name))
            continue;

        $combined_customers[] = $name;
        if (!empty($project_names[$i])) {
            $val_raw = isset($project_values[$i]) ? str_replace(',', '', $project_values[$i]) : 0;
            $val_text = floatval($val_raw) > 0 ? " (มูลค่า: " . number_format((float) $val_raw, 2) . " บาท)" : "";
            $combined_projects[] = $project_names[$i] . $val_text;
        }

        $combined_statuses[] = !empty($job_statuses[$i]) ? $job_statuses[$i] : '-';
        $combined_next_apps[] = !empty($next_appointments[$i]) ? $next_appointments[$i] : '-';
        $combined_summaries[] = "• " . $name . ": " . ($visit_summaries[$i] ?? '-');

        // บันทึก Master Customers
        $check_sql = "SELECT id FROM master_customers WHERE customer_name = ?";
        if ($chk_stmt = $conn->prepare($check_sql)) {
            $chk_stmt->bind_param("s", $name);
            $chk_stmt->execute();
            $chk_stmt->store_result();
            if ($chk_stmt->num_rows == 0) {
                $add_sql = "INSERT INTO master_customers (customer_name) VALUES (?)";
                if ($add_stmt = $conn->prepare($add_sql)) {
                    $add_stmt->bind_param("s", $name);
                    $add_stmt->execute();
                    $add_stmt->close();
                }
            }
            $chk_stmt->close();
        }
    }

    $final_work_result = implode(', ', $combined_customers);
    $final_project_name = implode(', ', array_unique($combined_projects));
    $final_job_status = implode(', ', $combined_statuses);
    $final_next_app = implode(', ', $combined_next_apps);
    $final_activity_detail = implode("\n", $combined_summaries);
    $final_notes = implode("\n", array_filter($additional_notes_arr));

    // =========================================================
    // 🔵 4. สั่ง INSERT หรือ UPDATE
    // =========================================================
    if ($edit_id) {
        // กรณีแก้ไข (UPDATE): ไม่อัปเดต reporter_name
        $sql = "UPDATE reports SET 
                report_date=?, area=?, province=?, gps=?, gps_address=?, work_result=?, 
                customer_type=?, project_name=?, additional_notes=?, job_status=?, 
                next_appointment=?, activity_detail=?, fuel_cost=?, fuel_receipt=?, 
                accommodation_cost=?, accommodation_receipt=?, other_cost=?, other_receipt=?, 
                other_cost_detail=?, total_expense=?, problem=?, suggestion=? 
                WHERE id = ?";
    } else {
        // กรณีเพิ่มใหม่ (INSERT)
        $sql = "INSERT INTO reports (
                report_date, reporter_name, area, province, gps, gps_address, work_result, 
                customer_type, project_name, additional_notes, job_status, next_appointment, 
                activity_detail, fuel_cost, fuel_receipt, accommodation_cost, accommodation_receipt, 
                other_cost, other_receipt, other_cost_detail, total_expense, problem, suggestion, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    if ($stmt = $conn->prepare($sql)) {
        $cus_type_first = $_POST['customer_type_1'] ?? 'ลูกค้าใหม่';

        if ($edit_id) {
            // Bind Params สำหรับ UPDATE (22 ฟิลด์ + 1 ID = 23 ตัว)
            $stmt->bind_param(
                "ssssssssssssdsdsdsdsssi",
                $report_date,
                $area,
                $province,
                $gps,
                $gps_address,
                $final_work_result,
                $cus_type_first,
                $final_project_name,
                $final_notes,
                $final_job_status,
                $final_next_app,
                $final_activity_detail,
                $fuel_cost_sum,
                $fuel_receipt,
                $accommodation_cost,
                $accommodation_receipt,
                $other_cost,
                $other_receipt,
                $other_cost_detail,
                $total_expense,
                $problem,
                $suggestion,
                $edit_id
            );
        } else {
            $created_at = date('Y-m-d H:i:s');
            // Bind Params สำหรับ INSERT (24 ตัว)
            $stmt->bind_param(
                "sssssssssssssdssdssdssss",
                $report_date,
                $reporter_name,
                $area,
                $province,
                $gps,
                $gps_address,
                $final_work_result,
                $cus_type_first,
                $final_project_name,
                $final_notes,
                $final_job_status,
                $final_next_app,
                $final_activity_detail,
                $fuel_cost_sum,
                $fuel_receipt,
                $accommodation_cost,
                $accommodation_receipt,
                $other_cost,
                $other_receipt,
                $other_cost_detail,
                $total_expense,
                $problem,
                $suggestion,
                $created_at
            );
        }

        if ($stmt->execute()) {
            // ✅ 1. แทรกโค้ดบันทึกเป้าหมายตรงนี้ (ก่อนเด้งหน้า)
            if (isset($_POST['monthly_target']) && !empty($_POST['monthly_target'])) {
                // แปลงวันที่รายงานที่เลือก มาเป็น เดือน/ปี ของเป้าหมาย
                $report_date_timestamp = strtotime($_POST['report_date']);
                $target_month = date('n', $report_date_timestamp);
                $target_year = date('Y', $report_date_timestamp);

                $target_amt = floatval(str_replace(',', '', $_POST['monthly_target']));

                // บันทึกลงตาราง sales_targets
                $sql_target = "INSERT IGNORE INTO sales_targets (reporter_name, target_month, target_year, target_amount) 
                               VALUES (?, ?, ?, ?)";

                if ($t_stmt = $conn->prepare($sql_target)) {
                    $t_stmt->bind_param("siid", $reporter_name, $target_month, $target_year, $target_amt);
                    $t_stmt->execute();
                    $t_stmt->close();
                }
            }
            // ----------------------------------------------------

            // ✅ 2. บันทึกเสร็จค่อยเด้ง
            $redirect_url = $edit_id ? "StaffHistory.php?tab=sales" : "Dashboard.php";
            header("Location: " . $redirect_url);
            exit();
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานประจำวัน TJC</title>

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="css/report_style.css">

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        <header class="top-header">
            <h1><i class="fas fa-file-signature"></i> รายงานประจำวัน</h1>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-container card">
            <form method="post" action="" enctype="multipart/form-data" id="reportForm">

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-info"></i> ข้อมูลพื้นฐาน</div>
                    <div class="form-grid-2-custom">
                        <div class="form-group">
                            <label>วันที่รายงาน <span style="color:red">*</span></label>
                            <input type="text" name="report_date" id="reportDate" class="form-input"
                                value="<?php echo $default_date; ?>" placeholder="เลือกวันที่..." required readonly
                                style="background-color: #fff; cursor: pointer;">
                        </div>
                        <div class="form-group">
                            <label>ผู้รายงาน</label>
                            <input type="text" name="reporter_name" value="<?php echo $_SESSION['fullname']; ?>"
                                class="form-input" readonly
                                style="background-color: var(--hover-bg); cursor: not-allowed;">
                        </div>
                        <div id="targetSection" class="form-group"
                            style="margin-top: 15px; background: #fffbeb; padding: 15px; border-radius: 12px; border: 1px solid #fcd34d;">
                            <label id="targetLabel" style="color: #b45309; font-weight: 700;">
                                <i class="fas fa-bullseye"></i> เป้าหมายยอดขายเดือนนี้
                            </label>
                            <div style="position: relative;">
                                <input type="text" name="monthly_target" id="monthlyTargetInput" class="form-input"
                                    placeholder="ระบุยอดขาย..."
                                    style="border-color: #f59e0b; font-size: 1.1rem; font-weight: bold; color: #9a3412;"
                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');">
                                <span
                                    style="position: absolute; right: 15px; top: 10px; color: #b45309; font-weight: bold;">บาท</span>
                            </div>
                            <div id="targetStatusText"
                                style="margin-top: 5px; font-size: 0.85rem; color: #d97706; display: none;">
                                * เดือนนี้คุณได้ตั้งเป้าหมายไว้แล้ว (แสดงข้อมูลเดิม)
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>สถานที่ปฏิบัติงาน</label>
                        <div class="radio-select-group">
                            <label class="radio-option">
                                <input type="radio" name="work_type" value="company" onclick="toggleWorkMode('company')"
                                    checked>
                                <div class="radio-card"><i class="fas fa-building"></i> บริษัท (Office)</div>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="work_type" value="outside"
                                    onclick="toggleWorkMode('outside')">
                                <div class="radio-card"><i class="fas fa-map-marker-alt"></i> นอกสถานที่ (GPS)</div>
                            </label>
                        </div>
                    </div>

                    <div id="outsideOptions" class="gps-panel hidden card">

                        <div class="form-group">
                            <label>พิกัดปัจจุบัน <span style="color:red">*</span></label>
                            <div class="gps-actions">
                                <input type="text" id="gpsInput" name="gps" class="form-input"
                                    placeholder="กดปุ่มเพื่อจับพิกัด..." readonly>
                                <button type="button" class="btn-gps" onclick="getLocation()">
                                    <i class="fas fa-satellite-dish"></i> จับพิกัด GPS
                                </button>
                            </div>
                            <div style="margin-top:5px;">
                                <a id="googleMapLink" href="#" target="_blank"
                                    style="display:none; color:var(--primary-color); font-weight:bold;">
                                    <i class="fas fa-map-marker-alt"></i> ดูใน Google Maps
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-briefcase"></i> รายละเอียดงาน
                    </div>

                    <div id="work-container">
                        <div class="work-box card" id="work-box-1">
                            <div class="work-box-header">
                                <span class="work-box-title">งานที่ 1</span>
                            </div>

                            <div class="form-group">
                                <label>ลูกค้า / หน่วยงานที่ติดต่อ (เลือกจากแผนงาน) <span
                                        style="color:red">*</span></label>

                                <div class="autocomplete-wrapper">
                                    <input type="text" name="work_result[]" class="form-input customer-input"
                                        placeholder="🔍 พิมพ์ชื่อเพื่อค้นหา..." required autocomplete="off">
                                    <div class="autocomplete-items"></div>
                                </div>
                            </div>
                            <div
                                style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>ชื่อโครงการ (ถ้ามี)</label>
                                    <input type="text" name="project_name[]" class="form-input"
                                        placeholder="ระบุชื่อโครงการ...">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>มูลค่าโครงการ (บาท)</label>
                                    <input type="text" name="project_value[]" class="form-input" placeholder="0.00"
                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1').replace(/\B(?=(\d{3})+(?!\d))/g, ',');">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label>ประเภทลูกค้า</label>
                                    <div class="radio-select-group small-radio">
                                        <label class="radio-option">
                                            <input type="radio" name="customer_type_1" value="ลูกค้าเก่า"
                                                class="cust-type-old">
                                            <div class="radio-card"><i class="fas fa-user-check"></i> เก่า</div>
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="customer_type_1" value="ลูกค้าใหม่"
                                                class="cust-type-new" checked>
                                            <div class="radio-card"><i class="fas fa-user-plus"></i> ใหม่</div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>สถานะงาน <span style="color:red">*</span></label>
                                    <select name="job_status[]" class="form-select job-status-select" required>
                                        <option value="">-- เลือกสถานะ --</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>นัดหมายครั้งถัดไป</label>
                                    <input type="text" name="next_appointment[]" class="form-input next-appt"
                                        placeholder="เลือกวันที่นัดหมาย" readonly>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>สรุปการเข้าพบ (รายละเอียด) <span style="color:red">*</span></label>
                                <textarea name="visit_summary[]" class="form-textarea" rows="3"
                                    placeholder="เช่น ลูกค้าสนใจสินค้า, เสนอราคาแล้ว, ติดตามผล..." required></textarea>
                            </div>

                            <div style="display:none;" class="form-group">
                                <label>บันทึกเพิ่มเติม</label>
                                <textarea name="additional_notes[]" class="form-textarea" rows="2"
                                    placeholder="หมายเหตุอื่นๆ..."></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn-add-work" onclick="addWorkBox()">
                        <i class="fas fa-plus-circle"></i> เพิ่มงาน
                    </button>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-receipt"></i> เบิกค่าใช้จ่าย</div>
                    <div class="expense-list card">
                        <div class="expense-item" id="row-fuel">
                            <label class="expense-label">
                                <input type="checkbox" id="fuel_check"
                                    onclick="toggleExpenseContainer('fuel_container', 'row-fuel')">
                                <i class="fas fa-gas-pump"></i> ค่าน้ำมัน
                            </label>
                            <div class="expense-content">
                                <div id="fuel_container" style="gap:10px; display:flex; flex-direction:column;">
                                    <div class="expense-row">
                                        <input type="number" step="0.01" name="fuel_cost[]"
                                            class="form-input calc-expense" placeholder="บาท"
                                            oninput="calculateTotal()">
                                        <label class="file-upload-btn"><i class="fas fa-upload"></i> สลิป <input
                                                type="file" name="fuel_receipt_file[]" accept="image/*" hidden
                                                onchange="showFile(this)"></label>
                                    </div>
                                </div>
                                <button type="button" class="btn-add-row-small" onclick="addFuelRow()">+
                                    เพิ่มบิล</button>
                            </div>
                        </div>
                        <div class="expense-item" id="row-hotel">
                            <label class="expense-label">
                                <input type="checkbox" onclick="toggleOneExpense('hotel_input', 'row-hotel')">
                                <i class="fas fa-hotel"></i> ค่าที่พัก
                            </label>
                            <div class="expense-content">
                                <div class="expense-row">
                                    <input type="number" step="0.01" id="hotel_input" name="accommodation_cost"
                                        class="form-input calc-expense" placeholder="บาท" oninput="calculateTotal()">
                                    <label class="file-upload-btn"><i class="fas fa-upload"></i> สลิป <input type="file"
                                            name="accommodation_receipt_file" accept="image/*" hidden
                                            onchange="showFile(this)"></label>
                                </div>
                            </div>
                        </div>
                        <div class="expense-item" id="row-other">
                            <label class="expense-label">
                                <input type="checkbox" onclick="toggleOneExpense('other_input', 'row-other')">
                                <i class="fas fa-ellipsis-h"></i> อื่นๆ
                            </label>
                            <div class="expense-content">
                                <div class="expense-row">
                                    <input type="text" name="other_cost_detail" class="form-input"
                                        placeholder="รายละเอียด">
                                    <input type="number" step="0.01" id="other_input" name="other_cost"
                                        class="form-input calc-expense" placeholder="บาท" oninput="calculateTotal()">
                                    <label class="file-upload-btn"><i class="fas fa-upload"></i> สลิป <input type="file"
                                            name="other_receipt_file" accept="image/*" hidden
                                            onchange="showFile(this)"></label>
                                </div>
                            </div>
                        </div>
                        <div class="total-bar">รวม: <span id="totalExpenseDisplay">0.00</span> บาท</div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-lightbulb"></i> ปัญหา/ข้อเสนอแนะ</div>
                    <div class="form-grid-2">
                        <textarea name="problem" class="form-textarea" rows="3" placeholder="ปัญหาที่พบ"></textarea>
                        <textarea name="suggestion" class="form-textarea" rows="3" placeholder="ข้อเสนอแนะ"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> ส่งรายงาน</button>
            </form>
        </div>

        <datalist id="customer_list">
            <?php foreach ($customers_data as $cus): ?>
                <option value="<?php echo htmlspecialchars($cus); ?>">
                <?php endforeach; ?>
        </datalist>
    </div>

    <script>
        const provincesData = <?php echo json_encode($provinces_data, JSON_UNESCAPED_UNICODE); ?>;

        // 🟢 บรรทัดนี้สำคัญมาก! ถ้าไม่มี ข้อมูลจะไม่มา
        const customerList = <?php echo json_encode($customers_data, JSON_UNESCAPED_UNICODE); ?>;
        const masterCustomerList = <?php echo json_encode($master_customers_list, JSON_UNESCAPED_UNICODE); ?>;
        const isEditMode = <?= $edit_id ? 'true' : 'false' ?>;
        const editData = <?= json_encode($edit_data, JSON_UNESCAPED_UNICODE) ?: 'null' ?>;
        const existingWorkData = <?= $existing_work_data ?>;
    </script>
    </script>

    <script src="js/report_script.js"></script>
    <script>
        // ✅ 1. ฟังก์ชันเช็คเป้าหมาย (เขียนไว้ตรงนี้เลย เพื่อความชัวร์ว่าทำงานแน่นอน)
        function checkTargetForDate(dateStr) {
            const targetSection = document.getElementById('targetSection');
            const input = document.getElementById('monthlyTargetInput');
            const label = document.getElementById('targetLabel');
            const statusText = document.getElementById('targetStatusText');

            // ถ้าไม่มีกล่องเป้าหมาย (เช่น หน้าแก้ไขอาจจะไม่ได้ใส่ไว้) ให้จบการทำงาน
            if (!targetSection) return;

            // เรียก API ไปถาม Database
            fetch('check_target_api.php?date=' + dateStr)
                .then(response => response.json())
                .then(data => {
                    // แสดงกล่องเสมอ
                    targetSection.style.display = 'block';

                    if (data.has_target) {
                        // 🟢 กรณี 1: มีเป้าแล้ว -> เอาตัวเลขมาโชว์ทันที!
                        input.value = data.amount;       // ยัดตัวเลขลงกล่อง

                        // ล็อกไม่ให้แก้
                        input.readOnly = true;
                        input.style.backgroundColor = '#f3f4f6';
                        input.style.color = '#6b7280';
                        input.style.cursor = 'not-allowed';

                        // เปลี่ยนข้อความเป็นสีเขียว
                        label.innerHTML = `<i class="fas fa-check-circle"></i> เป้าหมายเดือนนี้ (บันทึกแล้ว)`;
                        label.style.color = '#059669';
                        statusText.style.display = 'block';
                    } else {
                        // 🟠 กรณี 2: ยังไม่มีเป้า -> เคลียร์ค่ารอให้กรอก
                        input.value = '';

                        // ปลดล็อกให้พิมพ์ได้
                        input.readOnly = false;
                        input.style.backgroundColor = '#ffffff';
                        input.style.color = '#9a3412';
                        input.style.cursor = 'text';

                        // เปลี่ยนข้อความเป็นสีส้ม
                        label.innerHTML = `<i class="fas fa-bullseye"></i> ตั้งเป้ายอดขาย (กรอกครั้งแรกของเดือน)`;
                        label.style.color = '#b45309';
                        statusText.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // ✅ 2. เริ่มทำงานเมื่อหน้าเว็บโหลดเสร็จ
        document.addEventListener("DOMContentLoaded", function () {

            // รับวันที่เริ่มต้นจาก PHP (วันที่รายงาน)
            var initDate = "<?php echo $default_date; ?>";

            // สร้างปฏิทิน Flatpickr
            flatpickr("#reportDate", {
                locale: "th",
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d/m/Y",
                allowInput: false,
                clickOpens: true,
                defaultDate: initDate, // บังคับวันที่เริ่มต้น

                // 🔥 ทำงานทันทีที่ปฏิทินโหลดเสร็จ (เปิดหน้ามาก็เช็คเลย)
                onReady: function (selectedDates, dateStr, instance) {
                    // 1. บังคับโชว์วันที่ในช่อง
                    instance.setDate(initDate, true);

                    // 2. สั่งเช็คเป้าหมายของ "เดือนในวันที่" นั้นทันที!
                    checkTargetForDate(initDate);
                },

                // 🔥 ทำงานเมื่อมีการเปลี่ยนวันที่
                onChange: function (selectedDates, dateStr, instance) {
                    checkTargetForDate(dateStr);
                }
            });
        });
    </script>
</body>

</html>