<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// 1. ตั้งค่า Timezone ทั้ง PHP และ MySQL ให้เป็นไทย
date_default_timezone_set('Asia/Bangkok');
$conn->query("SET time_zone = '+07:00'");
$conn->query("SET NAMES utf8mb4");

// ==========================================
// BACKEND LOGIC
// ==========================================

// --- Helper: Company List ---
$companies_list = [];
$q = $conn->query("SELECT company_name FROM companies ORDER BY company_name ASC");
if ($q) {
    while ($row = $q->fetch_assoc())
        $companies_list[] = $row['company_name'];
}

// --- Helper: Upload Multiple Files ---
function uploadMultipleFiles($file_input)
{
    $uploaded_names = [];
    $dir = __DIR__ . "/uploads/admin/";
    if (!file_exists($dir))
        @mkdir($dir, 0777, true);

    if (isset($file_input['name']) && is_array($file_input['name'])) {
        foreach ($file_input['name'] as $key => $name) {
            if (!empty($name) && $file_input['error'][$key] == 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $new_name = "adm_" . time() . "_" . rand(100, 999) . "_" . $key . "." . $ext;
                if (move_uploaded_file($file_input['tmp_name'][$key], $dir . $new_name)) {
                    $uploaded_names[] = $new_name;
                }
            } else {
                $uploaded_names[] = "";
            }
        }
    }
    return empty($uploaded_names) ? json_encode([], JSON_UNESCAPED_UNICODE) : json_encode($uploaded_names, JSON_UNESCAPED_UNICODE);
}

// --- Helper: Data Formatting ---
function arrToString($arr)
{
    if (!is_array($arr))
        return json_encode([], JSON_UNESCAPED_UNICODE);
    $cleaned = array_map(function ($value) {
        return trim($value);
    }, $arr);
    return json_encode($cleaned, JSON_UNESCAPED_UNICODE);
}

function arrSum($arr)
{
    if (!is_array($arr))
        return floatval($arr);
    return array_sum(array_map('floatval', $arr));
}
function sumDocsFromStringArray($arr)
{
    $total = 0;
    if (is_array($arr)) {
        foreach ($arr as $str) {
            // Regex จับตัวเลขหลังเครื่องหมาย : และก่อนวงเล็บปิด )
            // เช่น "AX 123 (รายการ : 500)" จะได้ 500
            if (preg_match_all('/:\s*([\d,\.]+)\s*\)/', $str, $matches)) {
                foreach ($matches[1] as $amt) {
                    $total += floatval(str_replace(',', '', $amt));
                }
            }
        }
    }
    return $total;
}

// --- Form Processing ---
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$edit_data = null;
$my_name = $_SESSION['fullname'] ?? 'Test User';

// --- Fetch Data for Edit Mode ---
if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM report_admin WHERE id = ? AND reporter_name = ?");
    $stmt->bind_param("is", $edit_id, $my_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $edit_data = $row;
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = $_POST['report_date'];
    $note = trim($_POST['additional_note']);
    $is_edit = isset($_POST['action_edit_id']) && intval($_POST['action_edit_id']) > 0;
    $target_id = $is_edit ? intval($_POST['action_edit_id']) : 0;

    $timestamp = time();
    $created_at = date('Y-m-d H:i:s', strtotime('+12 hours', $timestamp));

    $grand_total = 0;

    // 1. Admin Expense
    $has_exp = isset($_POST['enable_admin_expense']) ? 1 : 0;
    $exp_doc = $has_exp ? arrToString($_POST['adm_doc'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_comp = $has_exp ? arrToString($_POST['adm_company'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_dept = $has_exp ? arrToString($_POST['adm_department'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_proj = $has_exp ? arrToString($_POST['adm_project'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_accom_str = $has_exp ? arrToString($_POST['accommodation_cost'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_labor_str = $has_exp ? arrToString($_POST['labor_cost'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_other_desc = $has_exp ? arrToString($_POST['other_desc'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_other_amt = $has_exp ? arrToString($_POST['other_amount'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_other_file = $has_exp ? uploadMultipleFiles($_FILES['other_file']) : json_encode([], JSON_UNESCAPED_UNICODE);
    $exp_file = $has_exp ? uploadMultipleFiles($_FILES['accommodation_file']) : json_encode([], JSON_UNESCAPED_UNICODE);

    if ($has_exp) {
        $sum_accom = arrSum($_POST['accommodation_cost'] ?? []);
        $sum_labor = arrSum($_POST['labor_cost'] ?? []);
        $sum_other = arrSum($_POST['other_amount'] ?? []);
        $sum_docs = sumDocsFromStringArray($_POST['adm_doc'] ?? []);
        $grand_total += ($sum_accom + ($sum_labor * 0.97) + $sum_other + $sum_docs);
    }

    // 2. PR
    $has_pr = isset($_POST['enable_pr']) ? 1 : 0;
    $pr_dept = $has_pr ? arrToString($_POST['pr_department'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $pr_proj = $has_pr ? arrToString($_POST['pr_project'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $pr_budget_str = $has_pr ? arrToString($_POST['pr_budget'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    if ($has_pr)
        $grand_total += arrSum($_POST['pr_budget'] ?? []);

    // 3. Job
    $has_job = isset($_POST['enable_job_update']) ? 1 : 0;
    $job_num = $has_job ? arrToString($_POST['job_number'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $job_dept = $has_job ? arrToString($_POST['job_department'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $job_proj = $has_job ? arrToString($_POST['job_project'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $job_budget_str = $has_job ? arrToString($_POST['job_budget'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    if ($has_job)
        $grand_total += arrSum($_POST['job_budget'] ?? []);

    // 4. BG
    $has_bg = isset($_POST['enable_bank_guarantee']) ? 1 : 0;
    $bg_dept = $has_bg ? arrToString($_POST['bg_department'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $bg_proj = $has_bg ? arrToString($_POST['bg_project'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $bg_amt_str = $has_bg ? arrToString($_POST['bg_amount'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    if ($has_bg)
        $grand_total += arrSum($_POST['bg_amount'] ?? []);

    // 5. Stamp
    $has_stamp = isset($_POST['enable_stamp_duty']) ? 1 : 0;
    $stamp_dept = $has_stamp ? arrToString($_POST['sd_department'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $stamp_proj = $has_stamp ? arrToString($_POST['sd_project'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    $stamp_cost_str = $has_stamp ? arrToString($_POST['sd_cost'] ?? []) : json_encode([], JSON_UNESCAPED_UNICODE);
    if ($has_stamp)
        $grand_total += arrSum($_POST['sd_cost'] ?? []);

    if ($is_edit) {
        $sql = "UPDATE report_admin SET 
            report_date=?, note=?, total_amount=?,
            has_expense=?, exp_company=?, exp_dept=?, exp_proj=?, exp_doc=?, exp_accom=?, exp_labor=?, exp_file=?, 
            exp_other_desc=?, exp_other_amount=?, exp_other_file=?,
            has_pr=?, pr_dept=?, pr_proj=?, pr_budget=?,
            has_job=?, job_num=?, job_dept=?, job_proj=?, job_budget=?,
            has_bg=?, bg_dept=?, bg_proj=?, bg_amount=?,
            has_stamp=?, stamp_dept=?, stamp_proj=?, stamp_cost=?
            WHERE id=? AND reporter_name=?";

        $stmt = $conn->prepare($sql);
        if (!$stmt)
            die("Prepare failed: " . $conn->error);

        $stmt->bind_param(
            "ssd" . "isssssssss" . "isss" . "issss" . "isss" . "issss" . "is",
            $date,
            $note,
            $grand_total,
            $has_exp,
            $exp_comp,
            $exp_dept,
            $exp_proj,
            $exp_doc,
            $exp_accom_str,
            $exp_labor_str,
            $exp_file,
            $exp_other_desc,
            $exp_other_amt,
            $exp_other_file,
            $has_pr,
            $pr_dept,
            $pr_proj,
            $pr_budget_str,
            $has_job,
            $job_num,
            $job_dept,
            $job_proj,
            $job_budget_str,
            $has_bg,
            $bg_dept,
            $bg_proj,
            $bg_amt_str,
            $has_stamp,
            $stamp_dept,
            $stamp_proj,
            $stamp_cost_str,
            $target_id,
            $my_name
        );
    } else {
        // 3. Insert Command (เพิ่ม created_at)
        $sql = "INSERT INTO report_admin 
        (report_date, reporter_name, note, total_amount, created_at,
         has_expense, exp_company, exp_dept, exp_proj, exp_doc, exp_accom, exp_labor, exp_file, 
         exp_other_desc, exp_other_amount, exp_other_file,
         has_pr, pr_dept, pr_proj, pr_budget,
         has_job, job_num, job_dept, job_proj, job_budget,
         has_bg, bg_dept, bg_proj, bg_amount,
         has_stamp, stamp_dept, stamp_proj, stamp_cost) 
        VALUES 
        (?, ?, ?, ?, ?, 
         ?, ?, ?, ?, ?, ?, ?, ?, 
         ?, ?, ?,
         ?, ?, ?, ?, 
         ?, ?, ?, ?, ?, 
         ?, ?, ?, ?, 
         ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        // 4. Bind Params (เพิ่ม s และตัวแปร $created_at)
        $stmt->bind_param(
            "sssdsissssssssssisssissssisssisss",
            $date,
            $my_name,
            $note,
            $grand_total,
            $created_at,
            $has_exp,
            $exp_comp,
            $exp_dept,
            $exp_proj,
            $exp_doc,
            $exp_accom_str,
            $exp_labor_str,
            $exp_file,
            $exp_other_desc,
            $exp_other_amt,
            $exp_other_file,
            $has_pr,
            $pr_dept,
            $pr_proj,
            $pr_budget_str,
            $has_job,
            $job_num,
            $job_dept,
            $job_proj,
            $job_budget_str,
            $has_bg,
            $bg_dept,
            $bg_proj,
            $bg_amt_str,
            $has_stamp,
            $stamp_dept,
            $stamp_proj,
            $stamp_cost_str
        );
    }

    if ($stmt->execute()) {
        $msg = $is_edit ? 'อัปเดตสำเร็จ' : 'บันทึกสำเร็จ';
        echo "<script>setTimeout(() => { Swal.fire({icon: 'success', title: '$msg', text: 'บันทึกข้อมูลเรียบร้อยแล้ว', showConfirmButton: false, timer: 1500}).then(() => { window.location.href = 'StaffHistory.php?tab=admin'; }); }, 100);</script>";
    } else {
        echo "<script>Swal.fire({icon: 'error', title: 'Error', text: '" . $stmt->error . "'});</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกรายงานประจำวัน</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* ================= VARIABLES ================= */
        :root {
            --primary: #4f46e5;
            /* Indigo */
            --primary-light: #e0e7ff;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;

            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --bg-input: #f8fafc;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-sub: #64748b;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --radius: 12px;
        }

        [data-theme="dark"] {
            --primary: #818cf8;
            --primary-light: #312e81;
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --border-color: #475569;
            --text-main: #f1f5f9;
            --text-sub: #94a3b8;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }

        body {
            font-family: 'Inter', 'Prompt', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            transition: background-color 0.3s, color 0.3s;
            margin: 0;
            padding-bottom: 60px;
        }

        * {
            box-sizing: border-box;
        }

        .main-wrapper {
            max-width: 850px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header-container {
            margin-bottom: 30px;
            text-align: left;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .page-subtitle {
            font-size: 0.95rem;
            color: var(--text-sub);
            margin-top: 5px;
        }

        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            transition: 0.3s;
        }

        .section-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            background: var(--bg-card);
            user-select: none;
        }

        .section-header:hover {
            background-color: var(--primary-light);
            opacity: 0.9;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-main);
        }

        .section-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0, 1, 0, 1);
            opacity: 0.5;
        }

        .section-body.open {
            max-height: 5000px;
            opacity: 1;
            border-top: 1px solid var(--border-color);
            padding: 24px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        input:checked+.slider {
            background-color: var(--primary);
        }

        input:checked+.slider:before {
            transform: translateX(20px);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 16px;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-input);
            color: var(--text-main);
            font-family: 'Prompt';
            font-size: 0.95rem;
            transition: 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background: var(--bg-card);
        }

        .date-input-wrapper {
            text-align: center;
            margin-bottom: 30px;
        }

        .date-input {
            font-size: 1.2rem;
            text-align: center;
            font-weight: 600;
            max-width: 250px;
            margin: 0 auto;
            border: 2px solid var(--primary);
            color: var(--primary);
            background: var(--bg-card);
            cursor: pointer;
        }

        .dynamic-item {
            background: var(--bg-body);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            position: relative;
            margin-bottom: 16px;
            animation: fadeIn 0.3s ease;
        }

        .btn-remove {
            position: absolute;
            top: 10px;
            right: 10px;
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .btn-remove:hover {
            background: var(--danger);
            color: white;
        }

        .btn-add {
            width: 100%;
            padding: 12px;
            border: 2px dashed var(--border-color);
            background: transparent;
            color: var(--text-sub);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-add:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary), #6366f1);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
            transition: 0.3s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 20px -3px rgba(79, 70, 229, 0.4);
        }

        .icon-box {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-right: 12px;
        }

        .icon-admin {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .icon-pr {
            background: #dcfce7;
            color: #10b981;
        }

        .icon-job {
            background: #ffedd5;
            color: #f97316;
        }

        .icon-bg {
            background: #fef3c7;
            color: #d97706;
        }

        .icon-sd {
            background: #fee2e2;
            color: #ef4444;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <form method="post" action="" enctype="multipart/form-data" id="mainForm">

            <div class="header-container">
                <h1 class="page-title"><?php echo $edit_id > 0 ? 'แก้ไขรายงานประจำวัน' : 'บันทึกรายงานประจำวัน'; ?></h1>
                <div class="page-subtitle">Daily Work & Expense Report</div>
            </div>

            <div class="date-input-wrapper">
                <input type="hidden" name="action_edit_id" value="<?php echo $edit_id; ?>">
                <input type="date" name="report_date" id="reportDate" class="form-control date-input"
                    value="<?php echo $edit_id > 0 ? $edit_data['report_date'] : date('Y-m-d'); ?>" required>
                <input type="hidden" name="client_time" id="clientTime">
            </div>

            <div class="card">
                <div class="section-header" onclick="toggleSection('adminSection', this)">
                    <div class="header-left">
                        <div class="icon-box icon-admin"><i class="fas fa-file-invoice-dollar"></i></div>
                        <span class="section-title">ค่าใช้จ่าย(Expenses)</span>
                    </div>
                    <label class="switch" onclick="event.stopPropagation()">
                        <input type="checkbox" name="enable_admin_expense"
                            onchange="toggleInputs('adminSection', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                <div id="adminSection" class="section-body">
                    <div id="admin_exp_container" class="dynamic-container"></div>
                    <button type="button" class="btn-add" onclick="addAdminExpRow()"><i class="fas fa-plus"></i>
                        เพิ่มรายการ</button>

                    <div
                        style="margin-top:20px; padding:15px; background:var(--primary-light); border-radius:8px; border:1px solid var(--primary);">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:0.9rem;">
                            <span style="color:var(--text-sub);">หัก 3% (เฉพาะค่าแรง):</span>
                            <span id="totalWht" style="font-weight:600; color:var(--danger);">0.00</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:1.1rem; font-weight:700;">
                            <span style="color:var(--text-main);">ยอดสุทธิรวม:</span>
                            <span id="totalNet" style="color:var(--primary);">0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="section-header" onclick="toggleSection('prSection', this)">
                    <div class="header-left">
                        <div class="icon-box icon-pr"><i class="fas fa-shopping-cart"></i></div>
                        <span class="section-title">BOQ</span>
                    </div>
                    <label class="switch" onclick="event.stopPropagation()">
                        <input type="checkbox" name="enable_pr" onchange="toggleInputs('prSection', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                <div id="prSection" class="section-body">
                    <div id="pr_container"></div>
                    <button type="button" class="btn-add" onclick="addPrRow()"><i class="fas fa-plus"></i> เพิ่ม
                        PR</button>
                </div>
            </div>

            <div class="card">
                <div class="section-header" onclick="toggleSection('jobSection', this)">
                    <div class="header-left">
                        <div class="icon-box icon-job"><i class="fas fa-hard-hat"></i></div>
                        <span class="section-title">แจ้งอัปงาน (Job Update)</span>
                    </div>
                    <label class="switch" onclick="event.stopPropagation()">
                        <input type="checkbox" name="enable_job_update"
                            onchange="toggleInputs('jobSection', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                <div id="jobSection" class="section-body">
                    <div id="job_container"></div>
                    <button type="button" class="btn-add" onclick="addJobRow()"><i class="fas fa-plus"></i> เพิ่ม
                        Job</button>
                </div>
            </div>

            <div class="card">
                <div class="section-header" onclick="toggleSection('bgSection', this)">
                    <div class="header-left">
                        <div class="icon-box icon-bg"><i class="fas fa-university"></i></div>
                        <span class="section-title">ค้ำประกัน (Bank Guarantee)</span>
                    </div>
                    <label class="switch" onclick="event.stopPropagation()">
                        <input type="checkbox" name="enable_bank_guarantee"
                            onchange="toggleInputs('bgSection', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                <div id="bgSection" class="section-body">
                    <div id="bg_container"></div>
                    <button type="button" class="btn-add" onclick="addBgRow()"><i class="fas fa-plus"></i> เพิ่ม
                        BG</button>
                </div>
            </div>

            <div class="card">
                <div class="section-header" onclick="toggleSection('sdSection', this)">
                    <div class="header-left">
                        <div class="icon-box icon-sd"><i class="fas fa-stamp"></i></div>
                        <span class="section-title">ตีตราสาร (Stamp Duty)</span>
                    </div>
                    <label class="switch" onclick="event.stopPropagation()">
                        <input type="checkbox" name="enable_stamp_duty"
                            onchange="toggleInputs('sdSection', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                <div id="sdSection" class="section-body">
                    <div id="sd_container"></div>
                    <button type="button" class="btn-add" onclick="addSdRow()"><i class="fas fa-plus"></i> เพิ่ม
                        Stamp</button>
                </div>
            </div>

            <div class="card" style="padding: 20px;">
                <label class="form-label"><i class="fas fa-comment-alt" style="margin-right:5px;"></i>
                    บันทึกเพิ่มเติม</label>
                <textarea name="additional_note" rows="3" class="form-control"
                    placeholder="รายละเอียดอื่นๆ..."><?php echo $edit_id > 0 ? htmlspecialchars($edit_data['note']) : ''; ?></textarea>
            </div>

            <button type="button" class="btn-submit" onclick="confirmSubmit()"><i class="fas fa-save"></i>
                <?php echo $edit_id > 0 ? 'บันทึกการแก้ไข' : 'บันทึกข้อมูลเข้าระบบ'; ?></button>
        </form>
    </div>

    <script>
        // --- Init ---
        const companyOptions = `<?php foreach ($companies_list as $c)
            echo "<option value='$c'>$c</option>"; ?>`;
        const isEditMode = <?php echo $edit_id > 0 ? 'true' : 'false'; ?>;
        const editData = <?php echo $edit_id > 0 ? json_encode($edit_data) : 'null'; ?>;

        document.addEventListener("DOMContentLoaded", () => {
            flatpickr("#reportDate", { dateFormat: "Y-m-d", locale: "th" });

            if (isEditMode && editData) {
                // Pre-fill data
                populateEditData();
            } else {
                ['adminSection', 'prSection', 'jobSection', 'bgSection', 'sdSection'].forEach(id => toggleInputs(id, false));
            }

            // Check LocalStorage on load (in case sidebar set it but page reloaded)
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        });

        // --- Logic: Populate Edit Data ---
        function populateEditData() {
            // Admin Exp Ensure Boolean
            const adminSwitch = document.querySelector('input[name="enable_admin_expense"]');
            if (editData.has_expense == 1) {
                adminSwitch.checked = true;
                toggleInputs('adminSection', true);
                document.getElementById('admin_exp_container').innerHTML = ''; // clear auto-added row

                try {
                    let comps = JSON.parse(editData.exp_company || '[]');
                    let depts = JSON.parse(editData.exp_dept || '[]');
                    let projs = JSON.parse(editData.exp_proj || '[]');
                    let accoms = JSON.parse(editData.exp_accom || '[]');
                    let labors = JSON.parse(editData.exp_labor || '[]');
                    let otherDescs = JSON.parse(editData.exp_other_desc || '[]');
                    let otherAmts = JSON.parse(editData.exp_other_amount || '[]');
                    let docs = JSON.parse(editData.exp_doc || '[]');

                    let maxLen = Math.max(comps.length, depts.length, projs.length, accoms.length, labors.length, otherDescs.length, otherAmts.length, docs.length, 1);

                    for (let i = 0; i < maxLen; i++) {
                        addAdminExpRow();
                        let rows = document.getElementById('admin_exp_container').querySelectorAll('.dynamic-item');
                        let currRow = rows[rows.length - 1];

                        if (comps[i]) currRow.querySelector('select[name="adm_company[]"]').value = comps[i];
                        if (depts[i]) currRow.querySelector('input[name="adm_department[]"]').value = depts[i];
                        if (projs[i]) currRow.querySelector('input[name="adm_project[]"]').value = projs[i];
                        if (accoms[i]) currRow.querySelector('input[name="accommodation_cost[]"]').value = accoms[i];

                        let laborInput = currRow.querySelector('input[name="labor_cost[]"]');
                        if (labors[i]) {
                            laborInput.value = labors[i];
                            calcRowNet(laborInput);
                        }

                        if (otherDescs[i]) currRow.querySelector('input[name="other_desc[]"]').value = otherDescs[i];
                        if (otherAmts[i]) currRow.querySelector('input[name="other_amount[]"]').value = otherAmts[i];

                        // Parse doc string into rows
                        if (docs[i] && typeof docs[i] === 'string' && docs[i] !== '') {
                            let docWrapper = currRow.querySelector('.doc-list-wrapper');
                            docWrapper.innerHTML = ''; // Clear default 1 row

                            let docEntries = docs[i].split(', ');
                            docEntries.forEach(entry => {
                                // "AX 1234 (ขนม : 500.00)"
                                let prefix = '';
                                let num = '';
                                let desc = '';
                                let amt = '';

                                let r1 = entry.match(/^(AX|PO|SO)?\s*([^\(]+)?/);
                                if (r1) {
                                    prefix = r1[1] ? r1[1].trim() : '';
                                    num = r1[2] ? r1[2].trim() : '';
                                }

                                let r2 = entry.match(/\(([^:]+) : ([^\)]+)\)/);
                                if (r2) {
                                    desc = r2[1] ? r2[1].trim() : '';
                                    amt = r2[2] ? r2[2].trim() : '';
                                }

                                addSubDocBtnObj(currRow, prefix, num, desc, amt);
                            });

                            updateDocHidden(currRow); // trigger calc
                        }
                    }
                } catch (e) { console.error('Error parsing admin data', e); }
                calcTotalWht();
            } else { toggleInputs('adminSection', false); }

            // PR Ensure Boolean
            const prSwitch = document.querySelector('input[name="enable_pr"]');
            if (editData.has_pr == 1) {
                prSwitch.checked = true;
                toggleInputs('prSection', true);
                document.getElementById('pr_container').innerHTML = '';
                try {
                    let depts = JSON.parse(editData.pr_dept || '[]');
                    let projs = JSON.parse(editData.pr_proj || '[]');
                    let buds = JSON.parse(editData.pr_budget || '[]');
                    let maxLen = Math.max(depts.length, projs.length, buds.length, 1);
                    for (let i = 0; i < maxLen; i++) {
                        addPrRow();
                        let rows = document.getElementById('pr_container').querySelectorAll('.dynamic-item');
                        let currRow = rows[rows.length - 1];
                        if (depts[i]) currRow.querySelector('input[name="pr_department[]"]').value = depts[i];
                        if (projs[i]) currRow.querySelector('input[name="pr_project[]"]').value = projs[i];
                        if (buds[i]) currRow.querySelector('input[name="pr_budget[]"]').value = buds[i];
                    }
                } catch (e) { }
            } else { toggleInputs('prSection', false); }

            // Job
            const jobSwitch = document.querySelector('input[name="enable_job_update"]');
            if (editData.has_job == 1) {
                jobSwitch.checked = true;
                toggleInputs('jobSection', true);
                document.getElementById('job_container').innerHTML = '';
                try {
                    let nums = JSON.parse(editData.job_num || '[]');
                    let depts = JSON.parse(editData.job_dept || '[]');
                    let projs = JSON.parse(editData.job_proj || '[]');
                    let buds = JSON.parse(editData.job_budget || '[]');
                    let maxLen = Math.max(nums.length, depts.length, projs.length, buds.length, 1);
                    for (let i = 0; i < maxLen; i++) {
                        addJobRow();
                        let rows = document.getElementById('job_container').querySelectorAll('.dynamic-item');
                        let currRow = rows[rows.length - 1];
                        if (nums[i]) currRow.querySelector('input[name="job_number[]"]').value = nums[i];
                        if (depts[i]) currRow.querySelector('input[name="job_department[]"]').value = depts[i];
                        if (projs[i]) currRow.querySelector('input[name="job_project[]"]').value = projs[i];
                        if (buds[i]) currRow.querySelector('input[name="job_budget[]"]').value = buds[i];
                    }
                } catch (e) { }
            } else { toggleInputs('jobSection', false); }

            // BG
            const bgSwitch = document.querySelector('input[name="enable_bank_guarantee"]');
            if (editData.has_bg == 1) {
                bgSwitch.checked = true;
                toggleInputs('bgSection', true);
                document.getElementById('bg_container').innerHTML = '';
                try {
                    let depts = JSON.parse(editData.bg_dept || '[]');
                    let projs = JSON.parse(editData.bg_proj || '[]');
                    let amts = JSON.parse(editData.bg_amount || '[]');
                    let maxLen = Math.max(depts.length, projs.length, amts.length, 1);
                    for (let i = 0; i < maxLen; i++) {
                        addBgRow();
                        let rows = document.getElementById('bg_container').querySelectorAll('.dynamic-item');
                        let currRow = rows[rows.length - 1];
                        if (depts[i]) currRow.querySelector('input[name="bg_department[]"]').value = depts[i];
                        if (projs[i]) currRow.querySelector('input[name="bg_project[]"]').value = projs[i];
                        if (amts[i]) currRow.querySelector('input[name="bg_amount[]"]').value = amts[i];
                    }
                } catch (e) { }
            } else { toggleInputs('bgSection', false); }

            // Stamp
            const stampSwitch = document.querySelector('input[name="enable_stamp_duty"]');
            if (editData.has_stamp == 1) {
                stampSwitch.checked = true;
                toggleInputs('sdSection', true);
                document.getElementById('sd_container').innerHTML = '';
                try {
                    let depts = JSON.parse(editData.stamp_dept || '[]');
                    let projs = JSON.parse(editData.stamp_proj || '[]');
                    let costs = JSON.parse(editData.stamp_cost || '[]');
                    let maxLen = Math.max(depts.length, projs.length, costs.length, 1);
                    for (let i = 0; i < maxLen; i++) {
                        addSdRow();
                        let rows = document.getElementById('sd_container').querySelectorAll('.dynamic-item');
                        let currRow = rows[rows.length - 1];
                        if (depts[i]) currRow.querySelector('input[name="sd_department[]"]').value = depts[i];
                        if (projs[i]) currRow.querySelector('input[name="sd_project[]"]').value = projs[i];
                        if (costs[i]) currRow.querySelector('input[name="sd_cost[]"]').value = costs[i];
                    }
                } catch (e) { }
            } else { toggleInputs('sdSection', false); }
        }

        function addSubDocBtnObj(itemRow, p, n, desc, amt) {
            const wrapper = itemRow.querySelector('.doc-list-wrapper');
            const html = `
            <div class="sub-doc-row" style="padding:10px; border:1px dashed #cbd5e1; border-radius:8px; margin-bottom:8px; background:#f8fafc;">
                <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                    <select class="sub-prefix form-control" style="width:85px; font-weight:bold;" onchange="updateDocHidden(this)">
                        <option value="AX" ${p === 'AX' ? 'selected' : ''}>AX</option>
                        <option value="PO" ${p === 'PO' ? 'selected' : ''}>PO</option>
                        <option value="SO" ${p === 'SO' ? 'selected' : ''}>SO</option>
                        <option value="" ${!p ? 'selected' : ''}>-</option>
                    </select>
                    <input type="text" class="sub-num form-control" placeholder="เลขที่เอกสาร..." oninput="updateDocHidden(this)" value="${n}">
                    <button type="button" onclick="const main=this.closest('.dynamic-item'); this.closest('.sub-doc-row').remove(); updateDocHidden(main);" style="color:var(--danger); background:none; border:none; cursor:pointer; margin-left:auto;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div style="display:flex; gap:8px;">
                    <input type="text" class="sub-desc form-control" style="flex:2;" placeholder="รายการค่าใช้จ่าย" oninput="updateDocHidden(this)" value="${desc}">
                    <input type="number" step="0.01" class="sub-amount form-control" style="flex:1;" placeholder="จำนวนเงิน" oninput="updateDocHidden(this)" value="${amt}">
                </div>
            </div>`;
            wrapper.insertAdjacentHTML('beforeend', html);
        }

        // --- Logic: Toggle Sections ---
        function toggleSection(id, header) {
            const switchInput = header.querySelector('input[type="checkbox"]');
            switchInput.checked = !switchInput.checked;
            toggleInputs(id, switchInput.checked);
        }

        function toggleInputs(sectionId, isChecked) {
            const section = document.getElementById(sectionId);
            if (isChecked) section.classList.add('open'); else section.classList.remove('open');

            section.querySelectorAll('input:not(.switch input), select, button').forEach(i => {
                if (isChecked) i.removeAttribute('disabled'); else i.setAttribute('disabled', 'true');
            });

            // Auto Add Row if Empty
            if (isChecked) {
                const container = section.querySelector('[id$="_container"]');
                if (container && container.children.length === 0) {
                    if (sectionId === 'adminSection') addAdminExpRow();
                    else if (sectionId === 'prSection') addPrRow();
                    else if (sectionId === 'jobSection') addJobRow();
                    else if (sectionId === 'bgSection') addBgRow();
                    else if (sectionId === 'sdSection') addSdRow();
                }
            }
        }

        // --- Logic: Dynamic Rows ---
        function removeRow(btn) {
            btn.closest('.dynamic-item').remove();
            calcTotalWht();
        }

        function addAdminExpRow() {
            const html = `
            <div class="dynamic-item">
                <button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                
                <div style="background:var(--bg-input); padding:10px; border-radius:8px; margin-bottom:15px; border:1px solid var(--border-color);">
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <label class="form-label" style="margin:0;"><i class="far fa-file-alt"></i> เลขที่เอกสาร (AX/PO/SO)</label>
                        <span style="font-size:0.9rem; color:var(--primary); font-weight:bold;">รวม: <span class="sub-doc-total">0.00</span></span>
                    </div>
                    <input type="hidden" name="adm_doc[]" class="final-doc-value">
                    <div class="doc-list-wrapper" style="display:flex; flex-direction:column; gap:8px;"></div>
                    <button type="button" onclick="addSubDoc(this)" style="margin-top:8px; font-size:0.8rem; background:none; border:none; color:var(--primary); cursor:pointer; font-weight:600;"><i class="fas fa-plus-circle"></i> เพิ่มเอกสาร</button>
                </div>

                <div class="form-grid">
                    <div><label class="form-label">บริษัท</label><select name="adm_company[]" class="form-control"><option value="">-- เลือก --</option>${companyOptions}</select></div>
                    <div><label class="form-label">หน่วยงาน</label><input type="text" name="adm_department[]" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">โครงการ</label><input type="text" name="adm_project[]" class="form-control"></div>
                
                <div class="form-grid">
                    <div>
                        <label class="form-label" style="color:var(--primary);">ค่าที่พัก</label>
                        <input type="number" step="0.01" name="accommodation_cost[]" class="accom-input form-control" placeholder="0.00" oninput="calcTotalWht()">
                        <input type="file" name="accommodation_file[]" class="form-control" style="margin-top:5px; padding:6px; font-size:0.8rem;">
                    </div>
                    <div>
                        <label class="form-label" style="color:var(--primary);">ค่าแรง (เต็ม)</label>
                        <input type="number" step="0.01" name="labor_cost[]" class="labor-input form-control" placeholder="0.00" oninput="calcRowNet(this)">
                        <div style="font-size:0.8rem; color:var(--success); margin-top:4px; text-align:right;">สุทธิ: <span class="row-net-display">0.00</span></div>
                    </div>
                </div>

                <div style="margin-top:15px; padding-top:15px; border-top:1px dashed var(--border-color);">
                    <label class="form-label" style="color:var(--warning);"><i class="fas fa-coins"></i> ค่าใช้จ่ายอื่นๆ</label>
                    <div class="form-grid">
                        <input type="text" name="other_desc[]" class="form-control" placeholder="ระบุรายการ">
                        <input type="number" step="0.01" name="other_amount[]" class="other-input form-control" placeholder="จำนวนเงิน" oninput="calcTotalWht()">
                    </div>
                    <input type="file" name="other_file[]" class="form-control" style="padding:6px; font-size:0.8rem;">
                </div>
            </div>`;
            document.getElementById('admin_exp_container').insertAdjacentHTML('beforeend', html);
            addSubDoc(document.getElementById('admin_exp_container').lastElementChild.querySelector('button[onclick^="addSubDoc"]'));
        }

        // --- Sub Doc Logic ---
        // --- [ส่วนที่ปรับแก้] Sub Doc Logic: เพิ่มช่องรายการและจำนวนเงิน ---
        function addSubDoc(btn) {
            const wrapper = btn.previousElementSibling;
            // ปรับ HTML ให้มี 2 บรรทัด (บรรทัดแรก: เลขที่, บรรทัดสอง: รายละเอียดเงิน)
            const html = `
            <div class="sub-doc-row" style="padding:10px; border:1px dashed #cbd5e1; border-radius:8px; margin-bottom:8px; background:#f8fafc;">
                <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                    <select class="sub-prefix form-control" style="width:85px; font-weight:bold;" onchange="updateDocHidden(this)">
                        <option value="AX">AX</option>
                        <option value="PO">PO</option>
                        <option value="SO">SO</option>
                        <option value="">-</option>
                    </select>
                    <input type="text" class="sub-num form-control" placeholder="เลขที่เอกสาร..." oninput="updateDocHidden(this)">
                    <button type="button" onclick="const main=this.closest('.dynamic-item'); this.closest('.sub-doc-row').remove(); updateDocHidden(main);" style="color:var(--danger); background:none; border:none; cursor:pointer; margin-left:auto;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div style="display:flex; gap:8px;">
                    <input type="text" class="sub-desc form-control" style="flex:2;" placeholder="รายการค่าใช้จ่าย" oninput="updateDocHidden(this)">
                    <input type="number" step="0.01" class="sub-amount form-control" style="flex:1;" placeholder="จำนวนเงิน" oninput="updateDocHidden(this)">
                </div>
            </div>`;
            wrapper.insertAdjacentHTML('beforeend', html);
        }

        function updateDocHidden(elem) {
            // หา dynamic-item หลัก
            const mainRow = (elem.classList && elem.classList.contains('dynamic-item')) ? elem : elem.closest('.dynamic-item');
            if (!mainRow) return;

            const hiddenInput = mainRow.querySelector('.final-doc-value');
            const subRows = mainRow.querySelectorAll('.sub-doc-row');

            // เตรียมตัวแปรเก็บข้อมูล
            let docList = [];
            let totalAmount = 0; // ตัวแปรเก็บผลรวม

            subRows.forEach(row => {
                let p = row.querySelector('.sub-prefix').value;
                let n = row.querySelector('.sub-num').value.trim();
                let desc = row.querySelector('.sub-desc').value.trim();
                let amtStr = row.querySelector('.sub-amount').value.trim();

                // บวกเลข (ถ้ามีค่า)
                let amtVal = parseFloat(amtStr);
                if (!isNaN(amtVal)) {
                    totalAmount += amtVal;
                }

                // สร้างข้อความเก็บลง DB (เหมือนเดิม)
                let header = "";
                if (p) header += p + " ";
                if (n) header += n;

                let details = [];
                if (desc) details.push(desc);
                if (amtStr) details.push(amtStr);

                let finalStr = header.trim();
                if (details.length > 0) {
                    finalStr += " (" + details.join(" : ") + ")";
                }

                if (finalStr) docList.push(finalStr);
            });

            // 1. อัปเดตค่าที่จะส่งเข้า Database
            hiddenInput.value = docList.join(', ');

            // 2. ✅ อัปเดตตัวเลขยอดรวมที่หน้าจอ
            const totalDisplay = mainRow.querySelector('.sub-doc-total');
            if (totalDisplay) {
                totalDisplay.innerText = totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            calcTotalWht();
        }

        // --- Calculation ---
        function calcRowNet(input) {
            let val = parseFloat(input.value) || 0;
            let net = val * 0.97;
            let display = input.parentElement.querySelector('.row-net-display');
            if (display) display.innerText = net.toLocaleString(undefined, { minimumFractionDigits: 2 });
            calcTotalWht();
        }

        function calcTotalWht() {
            let tLabor = 0, tAccom = 0, tOther = 0, tDocs = 0;

            // 1. รวมค่าแรง/ที่พัก/อื่นๆ (ของเดิม)
            document.querySelectorAll('.labor-input').forEach(i => tLabor += parseFloat(i.value) || 0);
            document.querySelectorAll('.accom-input').forEach(i => tAccom += parseFloat(i.value) || 0);
            document.querySelectorAll('.other-input').forEach(i => tOther += parseFloat(i.value) || 0);

            // 2. [เพิ่มใหม่] รวมยอดเงินจากเอกสาร (AX/PO/SO)
            document.querySelectorAll('.sub-amount').forEach(i => tDocs += parseFloat(i.value) || 0);

            // 3. คำนวณภาษีหัก ณ ที่จ่าย 3% (คิดเฉพาะค่าแรง)
            let wht = tLabor * 0.03;

            // 4. คำนวณยอดสุทธิ (ค่าแรงหัก 3% + ที่พัก + อื่นๆ + เอกสาร)
            let net = (tLabor * 0.97) + tAccom + tOther + tDocs;

            // 5. แสดงผล
            document.getElementById('totalWht').innerText = wht.toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('totalNet').innerText = net.toLocaleString(undefined, { minimumFractionDigits: 2 });
        }

        // --- Add Rows (Generic) ---
        function addPrRow() {
            const html = `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button><div class="form-grid"><div class="form-group"><label class="form-label">หน่วยงาน</label><input type="text" name="pr_department[]" class="form-control"></div><div class="form-group"><label class="form-label">โครงการ</label><input type="text" name="pr_project[]" class="form-control"></div></div><div class="form-group"><label class="form-label">💰 งบประมาณ</label><input type="number" step="0.01" name="pr_budget[]" class="form-control"></div></div>`;
            document.getElementById('pr_container').insertAdjacentHTML('beforeend', html);
        }
        function addJobRow() {
            const html = `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button><div class="form-group"><label class="form-label">เลขหน้างาน</label><input type="text" name="job_number[]" class="form-control"></div><div class="form-grid"><div class="form-group"><label class="form-label">หน่วยงาน</label><input type="text" name="job_department[]" class="form-control"></div><div class="form-group"><label class="form-label">โครงการ</label><input type="text" name="job_project[]" class="form-control"></div></div><div class="form-group"><label class="form-label">📊 งบโครงการ</label><input type="number" step="0.01" name="job_budget[]" class="form-control"></div></div>`;
            document.getElementById('job_container').insertAdjacentHTML('beforeend', html);
        }
        function addBgRow() {
            const html = `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button><div class="form-grid"><div class="form-group"><label class="form-label">หน่วยงาน</label><input type="text" name="bg_department[]" class="form-control"></div><div class="form-group"><label class="form-label">โครงการ</label><input type="text" name="bg_project[]" class="form-control"></div></div><div class="form-group"><label class="form-label">🏦 ยอดค้ำประกัน</label><input type="number" step="0.01" name="bg_amount[]" class="form-control"></div></div>`;
            document.getElementById('bg_container').insertAdjacentHTML('beforeend', html);
        }
        function addSdRow() {
            const html = `<div class="dynamic-item"><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button><div class="form-grid"><div class="form-group"><label class="form-label">หน่วยงาน</label><input type="text" name="sd_department[]" class="form-control"></div><div class="form-group"><label class="form-label">โครงการ</label><input type="text" name="sd_project[]" class="form-control"></div></div><div class="form-group"><label class="form-label">📜 ค่าตีตราสาร</label><input type="number" step="0.01" name="sd_cost[]" class="form-control"></div></div>`;
            document.getElementById('sd_container').insertAdjacentHTML('beforeend', html);
        }

        // 7. แก้ไขฟังก์ชัน confirmSubmit จับเวลาเครื่อง
        function confirmSubmit() {
            // เช็ค Validation
            const anyChecked = document.querySelector('input[type="checkbox"]:checked');
            if (!anyChecked && !document.querySelector('textarea').value) {
                return Swal.fire('เตือน', 'กรุณากรอกข้อมูลอย่างน้อย 1 ส่วน', 'warning');
            }

            Swal.fire({
                title: 'ยืนยันการบันทึก?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                confirmButtonColor: 'var(--primary)'
            }).then(r => {
                if (r.isConfirmed) {
                    // จับเวลาเครื่อง (Client Time)
                    const d = new Date();
                    const pad = (n) => n.toString().padStart(2, '0');
                    const localTime = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;

                    // ใส่ค่าลงใน Input ซ่อน
                    document.getElementById('clientTime').value = localTime;

                    // ส่งฟอร์ม
                    document.getElementById('mainForm').submit();
                }
            });
        }
    </script>
</body>

</html>