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
// 🌐 1. ดึงข้อมูลจังหวัดและภาค (จาก master_provinces)
// =========================================================
$provinces_data = [];
$sql_prov = "SELECT name_th AS province, region_name AS region 
             FROM master_provinces 
             ORDER BY region_name ASC, name_th ASC";
$res_prov = $conn->query($sql_prov);

if ($res_prov && $res_prov->num_rows > 0) {
    while($row = $res_prov->fetch_assoc()) {
        $reg = $row['region']; 
        $prov = $row['province']; 
        if(empty($reg)) $reg = 'อื่นๆ';
        $provinces_data[$reg][] = $prov;
    }
}

// =========================================================
// 🌐 2. ดึงรายชื่อหน่วยงาน/ลูกค้า (จาก master_customers)
// =========================================================
$customers_data = [];
$sql_cus = "SELECT customer_name FROM master_customers ORDER BY customer_name ASC";
$res_cus = $conn->query($sql_cus);

if ($res_cus && $res_cus->num_rows > 0) {
    while($row = $res_cus->fetch_assoc()) {
        $customers_data[] = $row['customer_name'];
    }
}

// --- PHP Functions ---
function uploadReceipt($fileInputName) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        $target_dir = __DIR__ . "/uploads/";
        if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }
        $fileExtension = pathinfo($_FILES[$fileInputName]["name"], PATHINFO_EXTENSION);
        $newFileName = "receipt_" . time() . "_" . rand(100, 999) . "." . $fileExtension;
        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $target_dir . $newFileName)) {
            return $newFileName;
        }
    }
    return "";
}

function uploadMultipleReceipts($fileInputName) {
    $uploadedFiles = [];
    if (isset($_FILES[$fileInputName])) {
        $fileCount = count($_FILES[$fileInputName]['name']);
        $target_dir = __DIR__ . "/uploads/";
        if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }
        
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
    $report_date = $_POST['report_date'];
    $reporter_name = $_SESSION['fullname'];
    $work_type = $_POST['work_type'];
    
    if ($work_type == 'company') { 
        $area = "เข้าบริษัท (สำนักงาน)"; $province = "กรุงเทพมหานคร"; $gps = "Office"; $gps_address = "สำนักงานใหญ่"; 
    } else { 
        $area = $_POST['area_zone'] ?? 'ไม่ได้ระบุ'; 
        $province = $_POST['province'] ?? ''; 
        $gps = $_POST['gps'] ?? ''; 
        $gps_address = $_POST['gps_address'] ?? ''; 
    }

    $work_result = trim($_POST['work_result']); 
    $customer_type = $_POST['customer_type'] ?? ''; 
    $project_name = $_POST['project_name'] ?? ''; 
    $additional_notes = $_POST['additional_notes'] ?? '';
    $job_status = $_POST['job_status']; 
    $next_appointment = !empty($_POST['next_appointment']) ? $_POST['next_appointment'] : NULL; 
    $activity_type = $_POST['activity_type'] ?? ''; 
    $activity_detail = $_POST['activity_detail'] ?? '';
    
    $fuel_costs_array = isset($_POST['fuel_cost']) ? $_POST['fuel_cost'] : [];
    $fuel_cost = 0;
    foreach ($fuel_costs_array as $cost) { $fuel_cost += floatval($cost); }
    
    $fuel_receipt = uploadMultipleReceipts('fuel_receipt_file');
    $accommodation_cost = !empty($_POST['accommodation_cost']) ? floatval($_POST['accommodation_cost']) : 0;
    $other_cost = !empty($_POST['other_cost']) ? floatval($_POST['other_cost']) : 0;
    $other_cost_detail = $_POST['other_cost_detail'] ?? '';
    $accommodation_receipt = uploadReceipt('accommodation_receipt_file');
    $other_receipt = uploadReceipt('other_receipt_file');
    $total_expense = $fuel_cost + $accommodation_cost + $other_cost;
    
    $problem = $_POST['problem'] ?? ''; 
    $suggestion = $_POST['suggestion'] ?? '';

    // ⭐ AUTO-SAVE: บันทึกชื่อลูกค้าลง Master ถ้าไม่มี
    if (!empty($work_result)) {
        $check_sql = "SELECT id FROM master_customers WHERE customer_name = ?";
        if ($chk_stmt = $conn->prepare($check_sql)) {
            $chk_stmt->bind_param("s", $work_result);
            $chk_stmt->execute();
            $chk_stmt->store_result();
            if ($chk_stmt->num_rows == 0) {
                $add_sql = "INSERT INTO master_customers (customer_name) VALUES (?)";
                if ($add_stmt = $conn->prepare($add_sql)) {
                    $add_stmt->bind_param("s", $work_result);
                    $add_stmt->execute();
                    $add_stmt->close();
                }
            }
            $chk_stmt->close();
        }
    }
    
    $sql = "INSERT INTO reports (report_date, reporter_name, area, province, gps, gps_address, work_result, customer_type, project_name, additional_notes, job_status, next_appointment, activity_type, activity_detail, fuel_cost, fuel_receipt, accommodation_cost, accommodation_receipt, other_cost, other_receipt, other_cost_detail, total_expense, problem, suggestion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssssssssssdsdsdssdss", $report_date, $reporter_name, $area, $province, $gps, $gps_address, $work_result, $customer_type, $project_name, $additional_notes, $job_status, $next_appointment, $activity_type, $activity_detail, $fuel_cost, $fuel_receipt, $accommodation_cost, $accommodation_receipt, $other_cost, $other_receipt, $other_cost_detail, $total_expense, $problem, $suggestion);
        if ($stmt->execute()) { header("Location: StaffHistory.php"); exit(); } 
        else { $message = "Error: " . $stmt->error; } 
        $stmt->close();
    } else { $message = "Prepare Error: " . $conn->error; } 
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-color: #004aad;
            --text-main: #333;
            --bg-body: #f4f6f9;
            --bg-card: #ffffff;
            --bg-input: #f9fafb;
            --border-color: #e5e7eb;
            --hover-bg: #f0f9ff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body { font-family: 'Prompt', sans-serif; background-color: var(--bg-body); margin: 0; }

        .main-container { width: 100%; padding: 30px; box-sizing: border-box; }
        @media (min-width: 992px) { .main-container { margin-left: 150px; width: calc(100% - 270px); padding: 40px; } }
        @media (max-width: 991px) { .main-container { margin-left: 0; width: 100%; padding-top: 80px; } }

        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .top-header h1 { font-size: 1.8rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; color: var(--text-main); }

        .form-container { background-color: var(--bg-card); border-radius: 20px; padding: 30px; position: relative; overflow: hidden; border-top: 4px solid var(--primary-color); box-shadow: var(--shadow); border: 1px solid var(--border-color); max-width: 850px; margin: 0 auto; }
        .form-section { margin-bottom: 35px; }
        .section-title { font-size: 1.2rem; font-weight: 600; color: var(--primary-color) !important; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .section-title i { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: var(--hover-bg); color: var(--primary-color); }

        .form-grid-2-custom { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .form-grid-2-custom { grid-template-columns: 1fr; } }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; width: 100%; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.95rem; color: var(--text-main); }

        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border-radius: 10px; font-family: 'Prompt', sans-serif; font-size: 1rem; border: 1px solid var(--border-color); background-color: var(--bg-input); color: var(--text-main); transition: all 0.3s; box-sizing: border-box; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--primary-color); }

        .radio-select-group { display: flex; gap: 15px; flex-wrap: wrap; }
        .radio-option { cursor: pointer; position: relative; flex: 1; min-width: 140px; } 
        .radio-option input { position: absolute; opacity: 0; }
        .radio-card { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 15px; border: 2px solid var(--border-color); background: var(--bg-card); color: var(--text-main); border-radius: 10px; transition: 0.3s; font-weight: 600; font-size: 0.95rem; }
        .radio-option input:checked + .radio-card { border-color: transparent; background: var(--primary-color); color: #fff !important; transform: translateY(-2px); }

        .gps-panel { border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; margin-top: 20px; background: var(--bg-body); animation: fadeIn 0.5s ease; }
        .gps-actions { display: flex; gap: 10px; width: 100%; }
        .btn-gps { background: #f59e0b; color: white !important; border: none; padding: 0 25px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap; }
        .btn-gps:hover { transform: scale(1.05); }
        .btn-gps.checked { background: #10b981; cursor: default; }

        .expense-list { border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); }
        .expense-item { display: grid; grid-template-columns: 200px 1fr; gap: 20px; padding: 25px; border-bottom: 1px solid var(--border-color); background: var(--bg-card); align-items: start; transition: 0.3s; }
        .expense-item:last-child { border-bottom: none; }
        .expense-item.active { background: var(--hover-bg); border-left: 4px solid var(--primary-color); }
        .expense-label { display: flex; align-items: center; gap: 12px; font-weight: 600; cursor: pointer; font-size: 1rem; color: var(--text-main); height: 45px; }
        .expense-label i { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: var(--border-color); color: var(--text-main); }
        .expense-label input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary-color); }
        .expense-content { width: 100%; opacity: 0.5; pointer-events: none; transition: 0.3s; display: flex; flex-direction: column; gap: 15px; }
        .expense-item.active .expense-content { opacity: 1; pointer-events: auto; }
        .expense-row { display: flex; gap: 10px; align-items: center; width: 100%; }
        .expense-row .form-input { flex: 1; } 
        
        .file-upload-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 20px; height: 47px; border: 2px dashed var(--border-color); background: var(--bg-input); border-radius: 10px; font-size: 0.9rem; cursor: pointer; transition: 0.3s; font-weight: 500; color: var(--text-main); white-space: nowrap; min-width: 130px; }
        .file-upload-btn:hover { border-color: var(--primary-color); color: var(--primary-color); background: var(--hover-bg); }
        .file-name-display { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; text-align: right; }

        .btn-action-icon { width: 47px; height: 47px; border-radius: 10px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .btn-remove { background: #fee2e2; color: #ef4444; } .btn-remove:hover { background: #fecaca; }
        .btn-add-row { background: #dcfce7; color: #166534; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; align-self: flex-start; }
        .btn-add-row:hover { background: #bbf7d0; }

        .total-bar { background: var(--primary-color); color: #fff !important; padding: 20px; text-align: right; font-size: 1.2rem; font-weight: 700; }
        .btn-submit { width: 100%; padding: 18px; background: var(--primary-color); color: #fff !important; border: none; border-radius: 20px; font-size: 1.1rem; font-weight: 700; font-family: 'Prompt', sans-serif; cursor: pointer; transition: 0.3s; margin-top: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-submit:hover { transform: translateY(-2px); opacity: 0.9; }
        .hidden { display: none; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 768px) {
            .form-container { padding: 20px; }
            .expense-item { grid-template-columns: 1fr; gap: 10px; }
            .expense-row { flex-direction: column; align-items: stretch; }
            .file-upload-btn, .btn-action-icon { width: 100%; }
            .top-header { flex-direction: column; text-align: center; }
            .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
            .gps-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-container">
    
    <header class="top-header">
        <h1><i class="fas fa-file-signature"></i> รายงานประจำวัน</h1>
    </header>

    <?php if(!empty($message)): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="form-container card">
        <form method="post" action="" enctype="multipart/form-data" id="reportForm" onsubmit="confirmSubmit(event)">
            
            <div class="form-section">
                <div class="section-title"><i class="fas fa-info"></i> ข้อมูลพื้นฐาน</div>
                
                <div class="form-grid-2-custom">
                    <div class="form-group">
                        <label>วันที่รายงาน <span style="color:red">*</span></label>
                        <input type="hidden" name="report_date" id="reportDateHidden" value="<?php echo date('Y-m-d'); ?>">
                        <input type="text" id="reportDateDisplay" class="form-input" placeholder="เลือกวันที่" readonly required>
                    </div>
                    <div class="form-group">
                        <label>ผู้รายงาน</label>
                        <input type="text" name="reporter_name" value="<?php echo $_SESSION['fullname']; ?>" class="form-input" readonly style="background-color: var(--hover-bg); cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label>สถานที่ปฏิบัติงาน</label>
                    <div class="radio-select-group">
                        <label class="radio-option">
                            <input type="radio" name="work_type" value="company" onclick="toggleWorkMode('company')" checked>
                            <div class="radio-card"><i class="fas fa-building"></i> บริษัท (Office)</div>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="work_type" value="outside" onclick="toggleWorkMode('outside')">
                            <div class="radio-card"><i class="fas fa-map-marker-alt"></i> นอกสถานที่ (GPS)</div>
                        </label>
                    </div>
                </div>

                <div id="outsideOptions" class="gps-panel hidden card">
                    <div class="form-grid-2" style="position: relative; z-index: 1;">
                        <div class="form-group">
                            <label>เลือกภาค/โซน <span style="color:red">*</span></label>
                            
                            <select name="area_zone" id="areaSelect" class="form-select" onchange="updateProvinces()">
                                <option value="">-- กรุณาเลือกภาค --</option>
                                <option value="เฉพาะ จ.อุบลราชธานี" style="font-weight:bold; color:var(--primary-color);">★ เฉพาะ จ.อุบลราชธานี</option>
                                
                                <?php foreach ($provinces_data as $region => $list): ?>
                                    <option value="<?php echo htmlspecialchars($region); ?>"><?php echo htmlspecialchars($region); ?></option>
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
                    <div class="form-group" style="position: relative; z-index: 1;">
                        <label>พิกัดปัจจุบัน (ต้องระบุ) <span style="color:red">*</span></label>
                        <div class="gps-actions">
                            <input type="text" id="gpsInput" name="gps" class="form-input" placeholder="กดปุ่มเพื่อจับพิกัด..." readonly>
                            <button type="button" class="btn-gps" onclick="getLocation()">
                                <i class="fas fa-satellite-dish"></i> จับพิกัด GPS
                            </button>
                        </div>
                        <div style="margin-top:10px;">
                            <a id="googleMapLink" href="#" target="_blank" style="display:none; text-decoration:none; font-weight:600; color:var(--primary-color);">
                                <i class="fas fa-map"></i> เปิดดูใน Google Maps
                            </a>
                        </div>
                    </div>
                    <div class="form-group" style="position: relative; z-index: 1;">
                        <label>ที่อยู่ (อัตโนมัติจาก GPS)</label>
                        <input type="text" id="addressInput" name="gps_address" class="form-input" placeholder="ระบบจะระบุที่อยู่ให้เองเมื่อกดปุ่มจับพิกัด..." readonly>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title"><i class="fas fa-briefcase"></i> รายละเอียดงาน</div>
                
                <div class="form-group">
                    <label>ลูกค้า / หน่วยงานที่ติดต่อ <span style="color:red">*</span></label>
                    <input type="text" name="work_result" id="workResultInput" list="customer_list" class="form-input" placeholder="พิมพ์ชื่อเพื่อค้นหา หรือระบุใหม่เพื่อเพิ่มอัตโนมัติ..." required autocomplete="off">
                    
                    <datalist id="customer_list">
                        <?php foreach ($customers_data as $cus): ?>
                            <option value="<?php echo htmlspecialchars($cus); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>ชื่อโครงการ (ถ้ามี)</label>
                        <input type="text" name="project_name" class="form-input" placeholder="ระบุชื่อโครงการ...">
                    </div>
                    <div class="form-group">
                        <label>ประเภทลูกค้า (Auto Check)</label>
                        <div class="radio-select-group" style="gap:10px;">
                            <label class="radio-option">
                                <input type="radio" name="customer_type" id="type_old" value="ลูกค้าเก่า">
                                <div class="radio-card" style="padding:12px;"><i class="fas fa-user-check"></i> ลูกค้าเก่า</div>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="customer_type" id="type_new" value="ลูกค้าใหม่" checked>
                                <div class="radio-card" style="padding:12px;"><i class="fas fa-user-plus"></i> ลูกค้าใหม่</div>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label>กิจกรรมที่ทำ</label>
                        <select name="activity_type" id="activitySelect" class="form-select" onchange="toggleActivityDetail()">
                            <option value="">-- เลือกกิจกรรม --</option>
                        </select>
                        <input type="text" name="activity_detail" id="activityDetail" class="form-input hidden" placeholder="โปรดระบุกิจกรรม..." style="margin-top:10px;">
                    </div>
                    <div class="form-group">
                        <label>สถานะงาน <span style="color:red">*</span></label>
                        <select name="job_status" id="jobStatusSelect" class="form-select" required>
                            <option value="">-- เลือกสถานะ --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>นัดหมายครั้งถัดไป</label>
                        <input type="text" id="nextAppointmentDisplay" class="form-input" placeholder="เลือกวันที่นัดหมาย" readonly>
                        <input type="hidden" name="next_appointment" id="nextAppointmentHidden">
                    </div>
                </div>
                <div class="form-group">
                    <label>บันทึกเพิ่มเติม</label>
                    <textarea name="additional_notes" class="form-textarea" rows="3" placeholder="รายละเอียดอื่นๆ..."></textarea>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title"><i class="fas fa-receipt"></i> เบิกค่าใช้จ่าย</div>
                <div class="expense-list card">
                    <div class="expense-item" id="row-fuel">
                        <label class="expense-label">
                            <input type="checkbox" id="fuel_check" onclick="toggleExpenseContainer('fuel_container_wrapper', 'row-fuel')">
                            <i class="fas fa-gas-pump"></i> ค่าน้ำมัน
                        </label>
                        <div id="fuel_container_wrapper" class="expense-content">
                            <div id="fuel_container" style="display:flex; flex-direction:column; gap:10px;">
                                <div class="expense-row">
                                    <input type="number" step="0.01" name="fuel_cost[]" class="form-input calc-expense" placeholder="ระบุจำนวนเงิน (บาท)" oninput="calculateTotal()">
                                    <div style="display:flex; flex-direction:column; align-items:flex-end;">
                                        <label class="file-upload-btn">
                                            <i class="fas fa-cloud-upload-alt"></i> แนบสลิป <span style="color:red">*</span>
                                            <input type="file" name="fuel_receipt_file[]" accept="image/*" hidden onchange="showFile(this)">
                                        </label>
                                        <div class="file-name-display"></div>
                                    </div>
                                    <button type="button" class="btn-action-icon btn-remove" onclick="removeFuelRow(this)" style="visibility:hidden;"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <button type="button" class="btn-add-row" onclick="addFuelRow()">
                                <i class="fas fa-plus-circle"></i> เพิ่มรายการน้ำมัน
                            </button>
                        </div>
                    </div>

                    <div class="expense-item" id="row-hotel">
                        <label class="expense-label">
                            <input type="checkbox" onclick="toggleExpense('hotel_group', 'hotel_input', 'row-hotel')">
                            <i class="fas fa-hotel"></i> ค่าที่พัก
                        </label>
                        <div id="hotel_group" class="expense-content">
                            <div class="expense-row">
                                <input type="number" step="0.01" id="hotel_input" name="accommodation_cost" class="form-input calc-expense" placeholder="ระบุจำนวนเงิน (บาท)" oninput="calculateTotal()">
                                <div style="display:flex; flex-direction:column; align-items:flex-end;">
                                    <label class="file-upload-btn">
                                        <i class="fas fa-cloud-upload-alt"></i> แนบสลิป <span style="color:red">*</span>
                                        <input type="file" name="accommodation_receipt_file" accept="image/*" hidden onchange="showFile(this)">
                                    </label>
                                    <div class="file-name-display"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="expense-item" id="row-other">
                        <label class="expense-label">
                            <input type="checkbox" onclick="toggleExpense('other_group', 'other_input', 'row-other')">
                            <i class="fas fa-ellipsis-h"></i> อื่นๆ
                        </label>
                        <div id="other_group" class="expense-content">
                            <div class="expense-row">
                                <input type="text" name="other_cost_detail" class="form-input" placeholder="ระบุรายละเอียด (เช่น ค่าทางด่วน)">
                                <input type="number" step="0.01" id="other_input" name="other_cost" class="form-input calc-expense" placeholder="จำนวนเงิน" oninput="calculateTotal()">
                                <div style="display:flex; flex-direction:column; align-items:flex-end;">
                                    <label class="file-upload-btn">
                                        <i class="fas fa-cloud-upload-alt"></i> แนบสลิป <span style="color:red">*</span>
                                        <input type="file" name="other_receipt_file" accept="image/*" hidden onchange="showFile(this)">
                                    </label>
                                    <div class="file-name-display"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="total-bar">ยอดรวมสุทธิ: <span id="totalExpenseDisplay">0.00</span> บาท</div>
                </div>
            </div>

            <div class="form-section" style="border-bottom: none;">
                <div class="section-title"><i class="fas fa-lightbulb"></i> ปัญหาและข้อเสนอแนะ</div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>⚠️ ปัญหาที่พบ</label>
                        <textarea name="problem" class="form-textarea" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>💡 ข้อเสนอแนะ</label>
                        <textarea name="suggestion" class="form-textarea" rows="3"></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane" style="margin-right:10px;"></i> ส่งรายงานประจำวัน
            </button>
        </form>
    </div>
    <div style="height: 60px;"></div>
</div>

<script>
    // ✅ ส่งข้อมูลจังหวัดและรายชื่อลูกค้าจาก PHP -> JS
    const provincesData = <?php echo json_encode($provinces_data, JSON_UNESCAPED_UNICODE); ?>;
    const customerList = <?php echo json_encode($customers_data, JSON_UNESCAPED_UNICODE); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        flatpickr("#reportDateDisplay", {
            dateFormat: "d/m/Y", defaultDate: "today", locale: "th", disableMobile: true,
            onChange: function(dates) { if (dates.length) document.getElementById("reportDateHidden").value = formatDate(dates[0]); }
        });
        flatpickr("#nextAppointmentDisplay", {
            dateFormat: "d/m/Y", locale: "th", minDate: "today", disableMobile: true,
            onChange: function(dates) { document.getElementById("nextAppointmentHidden").value = dates.length ? formatDate(dates[0]) : ""; }
        });

        // ⭐ AUTO-CHECK CUSTOMER TYPE LOGIC ⭐
        const workInput = document.getElementById('workResultInput');
        const radioOld = document.getElementById('type_old');
        const radioNew = document.getElementById('type_new');

        workInput.addEventListener('input', function() {
            const val = this.value.trim();
            // เช็คว่าชื่อที่พิมพ์ มีอยู่ในรายชื่อทั้งหมดไหม?
            if (customerList.includes(val)) {
                radioOld.checked = true; // มี -> ลูกค้าเก่า
            } else {
                radioNew.checked = true; // ไม่มี -> ลูกค้าใหม่
            }
        });
    });

    function formatDate(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    // --- Dynamic Rows ---
    function addFuelRow() {
        const container = document.getElementById('fuel_container');
        const newRow = document.createElement('div');
        newRow.className = 'expense-row';
        newRow.innerHTML = `
            <input type="number" step="0.01" name="fuel_cost[]" class="form-input calc-expense" placeholder="ระบุจำนวนเงิน (บาท)" oninput="calculateTotal()">
            <div style="display:flex; flex-direction:column; align-items:flex-end;">
                <label class="file-upload-btn">
                    <i class="fas fa-cloud-upload-alt"></i> แนบสลิป <span style="color:red">*</span>
                    <input type="file" name="fuel_receipt_file[]" accept="image/*" hidden onchange="showFile(this)">
                </label>
                <div class="file-name-display"></div>
            </div>
            <button type="button" class="btn-action-icon btn-remove" onclick="removeFuelRow(this)"><i class="fas fa-times"></i></button>
        `;
        container.appendChild(newRow);
    }

    function removeFuelRow(btn) { btn.parentElement.remove(); calculateTotal(); }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.calc-expense').forEach(input => {
            let val = parseFloat(input.value);
            if (!isNaN(val)) total += val;
        });
        document.getElementById('totalExpenseDisplay').innerText = total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    function showFile(input) {
        const display = input.parentElement.nextElementSibling;
        display.innerText = input.files[0] ? input.files[0].name.substring(0, 15) + "..." : "";
        if(input.files[0]) {
            input.parentElement.style.borderColor = "#10b981";
            input.parentElement.style.color = "#10b981";
            input.parentElement.style.background = "#dcfce7";
        }
    }

    function toggleExpense(groupId, inputId, rowId) {
        const row = document.getElementById(rowId);
        const checkbox = row.querySelector('input[type="checkbox"]');
        const input = document.getElementById(inputId);
        if(checkbox.checked) { row.classList.add('active'); input.focus(); }
        else { row.classList.remove('active'); input.value = ""; calculateTotal(); }
    }

    function toggleExpenseContainer(containerId, rowId) {
        const row = document.getElementById(rowId);
        const checkbox = row.querySelector('input[type="checkbox"]');
        if(checkbox.checked) { row.classList.add('active'); }
        else { row.classList.remove('active'); row.querySelectorAll('input[type="number"]').forEach(i => i.value=""); calculateTotal(); }
    }

    // --- GPS & Province Logic ---
    function toggleWorkMode(mode) {
        const panel = document.getElementById("outsideOptions");
        if(mode === 'outside') {
            panel.classList.remove('hidden');
        } else {
            panel.classList.add('hidden');
            document.getElementById("gpsInput").value = "";
            document.getElementById("addressInput").value = "";
            document.getElementById("areaSelect").value = "";
            updateProvinces(); // Reset province dropdown
        }
    }

    function updateProvinces() {
        const zone = document.getElementById("areaSelect").value;
        const provinceSelect = document.getElementById("provinceSelect");
        provinceSelect.innerHTML = '<option value="">-- รอเลือกภาค --</option>';
        
        let list = [];
        if (zone === 'เฉพาะ จ.อุบลราชธานี') {
            list = ['อุบลราชธานี'];
        } else if (zone && provincesData[zone]) {
            list = provincesData[zone];
        }
        
        list.forEach(p => {
            let option = document.createElement("option");
            option.value = p;
            option.text = p;
            if(list.length === 1) option.selected = true;
            provinceSelect.add(option);
        });
    }

    function getLocation() {
        if(navigator.geolocation) {
            Swal.fire({ title: 'กำลังจับพิกัด...', didOpen: () => Swal.showLoading() });
            navigator.geolocation.getCurrentPosition(showPosition, showError, { enableHighAccuracy: true });
        } else {
            Swal.fire('Error', 'เบราว์เซอร์นี้ไม่รองรับ GPS', 'error');
        }
    }

    function showPosition(pos) {
        Swal.close();
        const lat = pos.coords.latitude.toFixed(6);
        const lng = pos.coords.longitude.toFixed(6);
        
        document.getElementById("gpsInput").value = lat + ", " + lng;
        
        const btn = document.querySelector('.btn-gps');
        btn.classList.add('checked');
        btn.innerHTML = '<i class="fas fa-check"></i> จับพิกัดเรียบร้อย';

        const link = document.getElementById("googleMapLink");
        link.href = `https://www.google.com/maps?q=${lat},${lng}`;
        link.style.display = 'inline-block';

        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(res => res.json())
            .then(data => {
                if(data && data.display_name) {
                    document.getElementById("addressInput").value = data.display_name;
                } else {
                    document.getElementById("addressInput").value = "ไม่พบชื่อสถานที่ (ใช้พิกัดแทน)";
                }
            })
            .catch(() => {
                document.getElementById("addressInput").value = "ไม่สามารถดึงชื่อสถานที่ได้";
            });
    }

    function showError(error) {
        Swal.close();
        Swal.fire('Error', 'ไม่สามารถจับพิกัดได้ (กรุณาเปิด GPS)', 'error');
    }

    // --- Validation & Init ---
    function confirmSubmit(e) {
        e.preventDefault();
        
        if (document.getElementById('fuel_check').checked) {
            let fuelValid = true;
            document.querySelectorAll('#fuel_container .expense-row').forEach(row => {
                const cost = row.querySelector('input[name="fuel_cost[]"]').value;
                const file = row.querySelector('input[name="fuel_receipt_file[]"]').files.length;
                if (cost > 0 && file === 0) fuelValid = false;
            });
            if (!fuelValid) {
                Swal.fire({ icon: 'warning', title: 'กรุณาแนบสลิปค่าน้ำมัน', confirmButtonText: 'ตกลง' });
                return;
            }
        }

        Swal.fire({
            title: 'ยืนยันการส่งรายงาน?', icon: 'question',
            showCancelButton: true, confirmButtonText: 'ส่งข้อมูล', cancelButtonText: 'ยกเลิก'
        }).then((res) => {
            if(res.isConfirmed) { document.getElementById('reportForm').submit(); }
        });
    }

    function toggleActivityDetail() {
        const val = document.getElementById("activitySelect").value;
        const detail = document.getElementById("activityDetail");
        if(val==="อื่นๆ") { detail.classList.remove('hidden'); detail.required=true; }
        else { detail.classList.add('hidden'); detail.required=false; }
    }

    async function loadJobStatus() {
        try {
            const res = await fetch('api_data.php?action=get_job_status');
            const data = await res.json();
            const select = document.getElementById("jobStatusSelect");
            data.forEach(i => { let opt = document.createElement("option"); opt.value=i; opt.text=i; select.add(opt); });
        } catch(e){}
    }
    async function loadActivityTypes() {
        try {
            const res = await fetch('api_data.php?action=get_activities');
            const data = await res.json();
            const select = document.getElementById("activitySelect");
            data.forEach(i => { let opt = document.createElement("option"); opt.value=i; opt.text=i; select.add(opt); });
        } catch(e){}
    }

    window.addEventListener('DOMContentLoaded', () => {
        loadJobStatus(); loadActivityTypes();
        var radios = document.getElementsByName('work_type');
        for (var i = 0; i < radios.length; i++) { if (radios[i].checked) { toggleWorkMode(radios[i].value); break; } }
    });
</script>

</body>
</html>