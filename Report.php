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

// 🟢 [Logic ใหม่] ดึงจาก work_plans โดยกรองเฉพาะ reporter_name ของคนนี้
$sql_cus = "SELECT DISTINCT contact_person 
            FROM work_plans 
            WHERE reporter_name = '$my_name' 
              AND contact_person IS NOT NULL 
              AND contact_person != '' 
            ORDER BY contact_person ASC";

$res_cus = $conn->query($sql_cus);
if ($res_cus && $res_cus->num_rows > 0) {
    while ($row = $res_cus->fetch_assoc()) {
        $customers_data[] = $row['contact_person'];
    }
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

    $fuel_costs_array = isset($_POST['fuel_cost']) ? $_POST['fuel_cost'] : [];
    $fuel_cost = 0;
    foreach ($fuel_costs_array as $cost) {
        $fuel_cost += floatval($cost);
    }
    $fuel_receipt = uploadMultipleReceipts('fuel_receipt_file');

    $accommodation_cost = !empty($_POST['accommodation_cost']) ? floatval($_POST['accommodation_cost']) : 0;
    $other_cost = !empty($_POST['other_cost']) ? floatval($_POST['other_cost']) : 0;
    $other_cost_detail = $_POST['other_cost_detail'] ?? '';
    $accommodation_receipt = uploadReceipt('accommodation_receipt_file');
    $other_receipt = uploadReceipt('other_receipt_file');
    $total_expense = $fuel_cost + $accommodation_cost + $other_cost;

    $problem = $_POST['problem'] ?? '';
    $suggestion = $_POST['suggestion'] ?? '';

    // 2. รับค่าส่วน Work Details (Loop Box)
    $work_results = $_POST['work_result'] ?? [];
    $project_names = $_POST['project_name'] ?? [];
    // $customer_types จัดการใน Loop
    $job_statuses = $_POST['job_status'] ?? [];
    $next_appointments = $_POST['next_appointment'] ?? [];
    $visit_summaries = $_POST['visit_summary'] ?? [];
    $additional_notes = $_POST['additional_notes'] ?? [];

    $sql = "INSERT INTO reports (report_date, reporter_name, area, province, gps, gps_address, work_result, customer_type, project_name, additional_notes, job_status, next_appointment, activity_detail, fuel_cost, fuel_receipt, accommodation_cost, accommodation_receipt, other_cost, other_receipt, other_cost_detail, total_expense, problem, suggestion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $successCount = 0;

        for ($i = 0; $i < count($work_results); $i++) {
            $w_result = trim($work_results[$i]);
            if (empty($w_result))
                continue;

            $w_project = $project_names[$i] ?? '';
            $w_cus_type = $_POST['customer_type_' . ($i + 1)] ?? 'ลูกค้าใหม่';
            $w_status = $job_statuses[$i] ?? '';
            $w_next = !empty($next_appointments[$i]) ? $next_appointments[$i] : NULL;
            $w_summary = $visit_summaries[$i] ?? '';
            $w_note = $additional_notes[$i] ?? '';

            // ⭐ AUTO-SAVE: ยังคงบันทึกชื่อลูกค้าลง Master ถ้าเป็นชื่อใหม่ (เพื่อให้ระบบจำได้ในอนาคต)
            $check_sql = "SELECT id FROM master_customers WHERE customer_name = ?";
            if ($chk_stmt = $conn->prepare($check_sql)) {
                $chk_stmt->bind_param("s", $w_result);
                $chk_stmt->execute();
                $chk_stmt->store_result();
                if ($chk_stmt->num_rows == 0) {
                    $add_sql = "INSERT INTO master_customers (customer_name) VALUES (?)";
                    if ($add_stmt = $conn->prepare($add_sql)) {
                        $add_stmt->bind_param("s", $w_result);
                        $add_stmt->execute();
                        $add_stmt->close();
                    }
                }
                $chk_stmt->close();
            }

            $stmt->bind_param(
                "sssssssssssssdssdssdsss",
                $report_date,
                $reporter_name,
                $area,
                $province,
                $gps,
                $gps_address,
                $w_result,
                $w_cus_type,
                $w_project,
                $w_note,
                $w_status,
                $w_next,
                $w_summary,
                $fuel_cost,
                $fuel_receipt,
                $accommodation_cost,
                $accommodation_receipt,
                $other_cost,
                $other_receipt,
                $other_cost_detail,
                $total_expense,
                $problem,
                $suggestion
            );

            if ($stmt->execute()) {
                $successCount++;
            }
        }

        $stmt->close();
        if ($successCount > 0) {
            header("Location: StaffHistory.php");
            exit();
        } else {
            $message = "Error: ไม่สามารถบันทึกข้อมูลได้";
        }
    } else {
        $message = "Prepare Error: " . $conn->error;
    }
    $conn->close();
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
                            <input type="hidden" name="report_date" id="reportDateHidden"
                                value="<?php echo date('Y-m-d'); ?>">
                            <input type="text" id="reportDateDisplay" class="form-input" placeholder="เลือกวันที่"
                                readonly required>
                        </div>
                        <div class="form-group">
                            <label>ผู้รายงาน</label>
                            <input type="text" name="reporter_name" value="<?php echo $_SESSION['fullname']; ?>"
                                class="form-input" readonly
                                style="background-color: var(--hover-bg); cursor: not-allowed;">
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
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>เลือกภาค/โซน <span style="color:red">*</span></label>
                                <select name="area_zone" id="areaSelect" class="form-select"
                                    onchange="updateProvinces()">
                                    <option value="">-- กรุณาเลือกภาค --</option>
                                    <?php foreach ($provinces_data as $region => $list): ?>
                                        <option value="<?php echo htmlspecialchars($region); ?>">
                                            <?php echo htmlspecialchars($region); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>จังหวัด</label>
                                <select name="province" id="provinceSelect" class="form-select">
                                    <option value="">-- รอเลือกภาค --</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>พิกัดปัจจุบัน <span style="color:red">*</span></label>
                            <div class="gps-actions">
                                <input type="text" id="gpsInput" name="gps" class="form-input"
                                    placeholder="กดปุ่มเพื่อจับพิกัด..." readonly>
                                <button type="button" class="btn-gps" onclick="getLocation()"><i
                                        class="fas fa-satellite-dish"></i> จับพิกัด GPS</button>
                            </div>
                            <div style="margin-top:5px;"><a id="googleMapLink" href="#" target="_blank"
                                    style="display:none; color:var(--primary-color); font-weight:bold;">ดูใน Google
                                    Maps</a></div>
                        </div>
                        <div class="form-group">
                            <label>ที่อยู่</label>
                            <input type="text" id="addressInput" name="gps_address" class="form-input"
                                placeholder="ที่อยู่จาก GPS" readonly>
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
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>ชื่อโครงการ (ถ้ามี)</label>
                                    <input type="text" name="project_name[]" class="form-input"
                                        placeholder="ระบุชื่อโครงการ...">
                                </div>
                                <div class="form-group">
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

                            <div class="form-group">
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
    </script>

    <script src="js/report_script.js"></script>

</body>

</html>