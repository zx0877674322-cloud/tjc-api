<?php
session_start();
require_once 'db_connect.php'; // เปลี่ยนให้ตรงกับไฟล์เชื่อมต่อ DB ของลูกพี่

// ==========================================
// 1. จัดการการบันทึกข้อมูล (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_project') {
    header('Content-Type: application/json');
    try {
        $company_id = $_POST['company_id'] ?? null;
        $contract_no = $_POST['contract_no'] ?? null;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $recorder = $_SESSION['fullname'] ?? 'ไม่ระบุ';
        $warranty_value = intval($_POST['warranty_value'] ?? 0);
        $warranty_unit = $_POST['warranty_unit'] ?? 'days';
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        // 🔥 ค่าใหม่: จำนวนวันแจ้งเตือนล่วงหน้า
        $alert_days = intval($_POST['alert_days_before_expire'] ?? 30);

        $job_type_id = $_POST['job_type_id'] ?? null;
        $sales_user = $_POST['sales_user'] ?? null;
        $project_name = $_POST['project_name'] ?? '';
        $project_budget = floatval(str_replace(',', '', $_POST['project_budget'] ?? 0));
        $customer_id = $_POST['customer_id'] ?? null;

        // 1.1 Fetch details from customers table
        $customer_address_full = null;
        $customer_phone = null;
        $customer_affiliation = null;

        if ($customer_id) {
            $stmt_cust = $conn->prepare("SELECT address, sub_district, district, province, zip_code, phone_number, affiliation FROM customers WHERE customer_id = ?");
            $stmt_cust->bind_param("i", $customer_id);
            $stmt_cust->execute();
            $res_cust = $stmt_cust->get_result();
            if ($res_cust && $row_cust = $res_cust->fetch_assoc()) {
                $address_parts = array_filter([
                    $row_cust['address'],
                    $row_cust['sub_district'],
                    $row_cust['district'],
                    $row_cust['province'],
                    $row_cust['zip_code']
                ]);
                $customer_address_full = implode(' ', $address_parts);
                $customer_phone = $row_cust['phone_number'];
                $customer_affiliation = $row_cust['affiliation'];
            }
            $stmt_cust->close();
        }

        // Use the customer address from the customer table
        $customer_address = $customer_address_full;

        $bidding_type = $_POST['bidding_type'] ?? 'ไม่ยื่น';
        $bidding_date = ($bidding_type == 'ยื่น' && !empty($_POST['bidding_date'])) ? $_POST['bidding_date'] : null;

        $quotation_no = $_POST['quotation_no'] ?? null;
        $quote_creator = $_POST['quote_creator'] ?? null;

        $guarantee_type = $_POST['guarantee_type'] ?? 'ไม่มี';
        $guarantee_no = null;
        $guarantee_start_date = null;
        $guarantee_end_date = null;
        $guarantee_amount = 0; // 🔥 ค่าใหม่: จำนวนเงินค้ำประกัน

        if ($guarantee_type == 'หนังสือค้ำ') {
            $guarantee_no = $_POST['guarantee_no'] ?? null;
            $guarantee_start_date = !empty($_POST['guarantee_start_date']) ? $_POST['guarantee_start_date'] : null;
            $guarantee_end_date = !empty($_POST['guarantee_end_date']) ? $_POST['guarantee_end_date'] : null;
        }

        if ($guarantee_type != 'ไม่มี') {
            $guarantee_amount = floatval(str_replace(',', '', $_POST['guarantee_amount'] ?? 0));
        }

        // 🔥 LOGIC สถานะ: ถ้าวันที่เริ่ม หรือ วันที่สิ้นสุด ไม่มีข้อมูล = "รอเซ็นสัญญา"
        if (empty($start_date) || empty($end_date)) {
            $status = 'รอเซ็นสัญญา';
        } else {
            $status = 'เซ็นสัญญา';
        }

        // ตรวจสอบว่าแก้ไขหรือเพิ่มใหม่
        $edit_id = $_POST['edit_id'] ?? null;

        if ($edit_id) {
            // อัปเดตข้อมูล (26 ฟิลด์ + 1 edit_id = 27 ตัวแปร)
            $sql = "UPDATE projects SET 
                company_id=?, contract_no=?, start_date=?, warranty_value=?, warranty_unit=?, end_date=?, alert_days_before_expire=?,
                job_type_id=?, sales_user=?, project_name=?, project_budget=?, customer_id=?, customer_address=?, customer_phone=?, customer_affiliation=?,
                bidding_type=?, bidding_date=?, quotation_no=?, quote_creator=?, 
                guarantee_type=?, guarantee_no=?, guarantee_start_date=?, guarantee_end_date=?, guarantee_amount=?, status=?, recorder=?
                WHERE id=?";
            $stmt = $conn->prepare($sql);
            // type definition: 27 ตัว
            // i=1 (company_id), s=1 (contract_no), s=1 (start_date), i=1 (warranty_value), s=1 (warranty_unit), s=1 (end_date), i=1 (alert_days),
            // i=1 (job_type_id), s=1 (sales_user), s=1 (project_name), d=1 (project_budget), i=1 (customer_id), s=1 (customer_address), s=1 (customer_phone), s=1 (customer_affiliation),
            // s=1 (bidding_type), s=1 (bidding_date), s=1 (quotation_no), s=1 (quote_creator), 
            // s=1 (guarantee_type), s=1 (guarantee_no), s=1 (guarantee_start_date), s=1 (guarantee_end_date), d=1 (guarantee_amount), s=1 (status), s=1 (recorder)
            // i=1 (edit_id)
            $stmt->bind_param(
                "ississiisssdissssssssssdssi",
                $company_id,
                $contract_no,
                $start_date,
                $warranty_value,
                $warranty_unit,
                $end_date,
                $alert_days,
                $job_type_id,
                $sales_user,
                $project_name,
                $project_budget,
                $customer_id,
                $customer_address,
                $customer_phone,
                $customer_affiliation,
                $bidding_type,
                $bidding_date,
                $quotation_no,
                $quote_creator,
                $guarantee_type,
                $guarantee_no,
                $guarantee_start_date,
                $guarantee_end_date,
                $guarantee_amount,
                $status,
                $recorder,
                $edit_id
            );
            $msg = 'อัปเดตโครงการสำเร็จ';
        } else {
            // สร้างข้อมูลใหม่ (26 ฟิลด์)
            $sql = "INSERT INTO projects (
                company_id, contract_no, start_date, warranty_value, warranty_unit, end_date, alert_days_before_expire,
                job_type_id, sales_user, project_name, project_budget, customer_id, customer_address, customer_phone, customer_affiliation,
                bidding_type, bidding_date, quotation_no, quote_creator, 
                guarantee_type, guarantee_no, guarantee_start_date, guarantee_end_date, guarantee_amount, status, recorder
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            // type definition: 26 ตัว
            $stmt->bind_param(
                "ississiisssdissssssssssdss",
                $company_id,
                $contract_no,
                $start_date,
                $warranty_value,
                $warranty_unit,
                $end_date,
                $alert_days,
                $job_type_id,
                $sales_user,
                $project_name,
                $project_budget,
                $customer_id,
                $customer_address,
                $customer_phone,
                $customer_affiliation,
                $bidding_type,
                $bidding_date,
                $quotation_no,
                $quote_creator,
                $guarantee_type,
                $guarantee_no,
                $guarantee_start_date,
                $guarantee_end_date,
                $guarantee_amount,
                $status,
                $recorder
            );
            $msg = 'บันทึกโครงการสำเร็จ';
        }

        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => "SQL Error: " . $stmt->error]);
            exit;
        }
        echo json_encode(['status' => 'success', 'message' => $msg . ' สถานะ: ' . $status]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// 2. ดึงข้อมูลสำหรับ Dropdown (GET)
// ==========================================
$companies = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$job_types = $conn->query("SELECT id, type_name FROM project_job_types ORDER BY type_name ASC");
$users = $conn->query("SELECT fullname FROM users ORDER BY fullname ASC");
$customers = $conn->query("SELECT customer_id, customer_name FROM customers ORDER BY customer_name ASC");

// จำลองข้อมูล User สำหรับผู้เปิดบิล/เซลล์
$users_array = [];
if ($users) {
    while ($u = $users->fetch_assoc()) {
        $users_array[] = $u['fullname'];
    }
}

// ==========================================
// 3. ดึงข้อมูลโครงการเดิม (กรณีแก้ไข)
// ==========================================
$edit_id = $_GET['edit_id'] ?? null;
$project_data = null;
if ($edit_id) {
    $stmt_edit = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $res_edit = $stmt_edit->get_result();
    if ($res_edit && $res_edit->num_rows > 0) {
        $project_data = $res_edit->fetch_assoc();
    }
    $stmt_edit->close();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>บันทึกโครงการใหม่</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f1f5f9;
            color: #334155;
            margin: 0;
            padding: 20px;
        }

        .form-container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }

        h2 {
            margin-top: 0;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #3b82f6;
            margin: 25px 0 15px 0;
            background: #eff6ff;
            padding: 10px 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: #475569;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Prompt';
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: 0.3s;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .hidden-group {
            display: none;
            background: #fffbeb;
            border: 1px dashed #fcd34d;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Prompt', sans-serif;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
        }

        .btn-submit:hover {
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #ef4444;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Prompt', sans-serif;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
            text-decoration: none;
        }

        .btn-cancel:hover {
            background: #dc2626;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
            transform: translateY(-2px);
            color: #fff;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .select2-container .select2-selection--single {
            height: 42px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            display: flex;
            align-items: center;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class="form-container">
        <h2><i class="fas <?= $edit_id ? 'fa-edit' : 'fa-folder-plus' ?> text-blue-500"></i>
            <?= $edit_id ? 'แก้ไขโครงการ' : 'สร้างโครงการใหม่' ?></h2>

        <form id="projectForm">
            <input type="hidden" name="action" value="save_project">
            <?php if ($edit_id): ?>
                <input type="hidden" name="edit_id" value="<?= htmlspecialchars($edit_id) ?>">
            <?php endif; ?>

            <div class="section-title"><i class="fas fa-info-circle"></i> ข้อมูลหลัก (Main Info)</div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">บริษัท <span style="color:red">*</span></label>
                    <select name="company_id" class="form-control select2" required>
                        <option value="">-- เลือกบริษัท --</option>
                        <?php if ($companies)
                            while ($c = $companies->fetch_assoc()) {
                                $selected = ($project_data && $project_data['company_id'] == $c['id']) ? 'selected' : '';
                                echo "<option value='{$c['id']}' {$selected}>{$c['company_name']}</option>";
                            } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">ชื่อลูกค้า <span style="color:red">*</span></label>
                    <select name="customer_id" class="form-control select2" required>
                        <option value="">-- เลือกลูกค้า --</option>
                        <?php if ($customers)
                            while ($cust = $customers->fetch_assoc()) {
                                $selected = ($project_data && $project_data['customer_id'] == $cust['customer_id']) ? 'selected' : '';
                                echo "<option value='{$cust['customer_id']}' {$selected}>{$cust['customer_name']}</option>";
                            } ?>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">ชื่องบ / โครงการ <span style="color:red">*</span></label>
                    <input type="text" name="project_name" class="form-control" placeholder="ระบุชื่อโครงการ..."
                        value="<?= htmlspecialchars($project_data['project_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ยอดงบ / โครงการ (บาท)</label>
                    <input type="text" name="project_budget" id="project_budget" class="form-control" placeholder="0.00"
                        value="<?= $project_data ? number_format($project_data['project_budget'], 2) : '' ?>"
                        oninput="formatNumber(this); calcGuaranteeAmount();">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">ประเภทงาน <span style="color:red">*</span></label>
                    <select name="job_type_id" class="form-control select2" required>
                        <option value="">-- เลือกประเภทงาน --</option>
                        <?php if ($job_types)
                            while ($jt = $job_types->fetch_assoc()) {
                                $selected = ($project_data && $project_data['job_type_id'] == $jt['id']) ? 'selected' : '';
                                echo "<option value='{$jt['id']}' {$selected}>{$jt['type_name']}</option>";
                            } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">เซลล์ที่รับผิดชอบ</label>
                    <select name="sales_user" class="form-control select2">
                        <option value="">-- เลือกเซลล์ --</option>
                        <?php foreach ($users_array as $u) {
                            $selected = ($project_data && $project_data['sales_user'] == $u) ? 'selected' : '';
                            echo "<option value='{$u}' {$selected}>{$u}</option>";
                        } ?>
                    </select>
                </div>
            </div>

            <?php $g_type = $project_data['guarantee_type'] ?? 'ไม่มี'; ?>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">ประเภทการค้ำประกัน</label>
                    <select name="guarantee_type" id="guarantee_type" class="form-control" onchange="toggleGuarantee()">
                        <option value="ไม่มี" <?= $g_type == 'ไม่มี' ? 'selected' : '' ?>>ไม่มี</option>
                        <option value="หนังสือค้ำ" <?= $g_type == 'หนังสือค้ำ' ? 'selected' : '' ?>>หนังสือค้ำประกัน (Bank
                            Guarantee)</option>
                        <option value="เงินสด" <?= $g_type == 'เงินสด' ? 'selected' : '' ?>>เงินสด (Cash)</option>
                    </select>
                </div>
                <div class="form-group" id="guarantee_amount_box"
                    style="<?= $g_type != 'ไม่มี' ? 'display:block;' : 'display:none;' ?>">
                    <label class="form-label" style="color: #059669;">จำนวนเงินค้ำประกัน (บาท)</label>
                    <input type="text" name="guarantee_amount" id="guarantee_amount" class="form-control"
                        placeholder="0.00" oninput="formatNumber(this)"
                        value="<?= $project_data && $project_data['guarantee_amount'] > 0 ? number_format($project_data['guarantee_amount'], 2) : '' ?>">
                </div>
            </div>

            <div id="guarantee_section" style="<?= $g_type == 'หนังสือค้ำ' ? 'display:block;' : 'display:none;' ?>">
                <div class="grid-3"
                    style="background: #fffbeb; border: 1px dashed #fcd34d; padding: 15px; border-radius: 10px; margin-top: 10px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label">เลขที่หนังสือค้ำ</label>
                        <input type="text" name="guarantee_no" class="form-control" placeholder="ระบุเลขที่..."
                            value="<?= htmlspecialchars($project_data['guarantee_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">วันที่ออกหนังสือ</label>
                        <input type="text" name="guarantee_start_date" class="form-control datepicker"
                            placeholder="เลือกวันที่..."
                            value="<?= htmlspecialchars($project_data['guarantee_start_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">วันที่สิ้นสุดหนังสือ</label>
                        <input type="text" name="guarantee_end_date" class="form-control datepicker"
                            placeholder="เลือกวันที่..."
                            value="<?= htmlspecialchars($project_data['guarantee_end_date'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="section-title"><i class="fas fa-file-contract"></i> ข้อมูลสัญญา (Contract & Warranty)</div>
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">เลขที่สัญญา</label>
                    <input type="text" name="contract_no" class="form-control" placeholder="ระบุเลขที่สัญญา..."
                        value="<?= htmlspecialchars($project_data['contract_no'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">วันที่เริ่มสัญญา</label>
                    <input type="text" name="start_date" id="start_date" class="form-control datepicker"
                        placeholder="เลือกวันที่..." onchange="calcEndDate()"
                        value="<?= htmlspecialchars($project_data['start_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">วันที่สิ้นสุดสัญญา (คำนวณอัตโนมัติ)</label>
                    <input type="text" name="end_date" id="end_date" class="form-control datepicker"
                        placeholder="เลือกวันที่..." style="background:#f1f5f9; font-weight:bold; color:#059669;"
                        value="<?= htmlspecialchars($project_data['end_date'] ?? '') ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">ระยะเวลารับประกัน</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" name="warranty_value" id="warranty_value" class="form-control"
                            placeholder="ระบุตัวเลข"
                            value="<?= htmlspecialchars($project_data['warranty_value'] ?? 0) ?>" min="0"
                            oninput="calcEndDate()">
                        <select name="warranty_unit" id="warranty_unit" class="form-control" onchange="calcEndDate()">
                            <option value="days" <?= ($project_data && $project_data['warranty_unit'] == 'days') ? 'selected' : '' ?>>วัน (Days)</option>
                            <option value="years" <?= ($project_data && $project_data['warranty_unit'] == 'years') ? 'selected' : '' ?>>ปี (Years)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: #ea580c;"><i class="fas fa-bell"></i> แจ้งเตือนก่อนหมดสัญญา
                        (วัน)</label>
                    <input type="number" name="alert_days_before_expire" class="form-control"
                        placeholder="ระบุจำนวนวัน (เช่น 30)"
                        value="<?= htmlspecialchars($project_data['alert_days_before_expire'] ?? 30) ?>" min="0">
                </div>
            </div>

            <div class="section-title"><i class="fas fa-envelope-open-text"></i> การยื่นซอง & ใบเสนอราคา</div>
            <?php
            $b_type = $project_data['bidding_type'] ?? 'ไม่ยื่น';
            $b_date = $project_data['bidding_date'] ?? '';
            ?>
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">ประเภทการยื่นซอง</label>
                    <select name="bidding_type" id="bidding_type" class="form-control" onchange="toggleBidding()">
                        <option value="ไม่ยื่น" <?= $b_type == 'ไม่ยื่น' ? 'selected' : '' ?>>ไม่ยื่น (No Bidding)</option>
                        <option value="ยื่น" <?= $b_type == 'ยื่น' ? 'selected' : '' ?>>ยื่น (Bidding)</option>
                    </select>
                </div>
                <div class="form-group" id="bidding_date_box" style="<?= $b_type == 'ยื่น' ? '' : 'display:none;' ?>">
                    <label class="form-label">วันที่ยื่นซอง <span style="color:red">*</span></label>
                    <input type="text" name="bidding_date" class="form-control datepicker" placeholder="เลือกวันที่..."
                        value="<?= htmlspecialchars($b_date) ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">เลขที่ใบเสนอราคา</label>
                    <input type="text" name="quotation_no" class="form-control" placeholder="ระบุเลขที่ใบเสนอราคา..."
                        value="<?= htmlspecialchars($project_data['quotation_no'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">ผู้เปิดใบเสนอราคา</label>
                    <select name="quote_creator" class="form-control select2">
                        <option value="">-- เลือกผู้เปิดบิล --</option>
                        <?php foreach ($users_array as $u) {
                            $selected = ($project_data && $project_data['quote_creator'] == $u) ? 'selected' : '';
                            echo "<option value='{$u}' {$selected}>{$u}</option>";
                        } ?>
                    </select>
                </div>
            </div>

            <div class="btn-group">
                <a href="project_dashboard.php" class="btn-cancel"><i class="fas fa-times"></i> ยกเลิก</a>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกข้อมูลโครงการ</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

    <script>
        $(document).ready(function () {
            $('.select2').select2({ width: '100%' });

            flatpickr(".datepicker", {
                altInput: true,
                altFormat: "d/m/Y",
                dateFormat: "Y-m-d",
                locale: "th"
            });

            $('#projectForm').on('submit', function (e) {
                e.preventDefault();

                let formData = new FormData(this);
                Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

                $.ajax({
                    url: 'create_project.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('สำเร็จ', res.message, 'success').then(() => {
                                window.location.href = 'project_dashboard.php'; // บันทึกเสร็จเด้งกลับไปหน้า Dashboard 
                            });
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        Swal.fire({
                            title: 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์',
                            text: xhr.responseText || error,
                            icon: 'error'
                        });
                    }
                });
            });
        });

        // Format ตัวเลข
        function formatNumber(input) {
            let val = input.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
            if (val) input.value = parseFloat(val).toLocaleString('en-US');
        }

        // เปิด/ปิด ช่องวันที่ยื่นซอง
        function toggleBidding() {
            if ($('#bidding_type').val() === 'ยื่น') {
                $('#bidding_date_box').slideDown();
            } else {
                $('#bidding_date_box').slideUp();
            }
        }

        // เปิด/ปิด ช่องค้ำประกัน
        function toggleGuarantee() {
            let type = $('#guarantee_type').val();
            if (type === 'หนังสือค้ำ') {
                $('#guarantee_section').slideDown();
                $('#guarantee_amount_box').slideDown();
            } else if (type === 'เงินสด') {
                $('#guarantee_section').slideUp();
                $('#guarantee_amount_box').slideDown();
            } else {
                $('#guarantee_section').slideUp();
                $('#guarantee_amount_box').slideUp();
                $('#guarantee_amount').val(''); // ลบค่าเมื่อเลือกไม่มี
            }
            // คำนวณ 5% อัตโนมัติเมื่อเลือกประเภท
            calcGuaranteeAmount();
        }

        // คำนวณ 5% ของ ยอดงบ / โครงการ
        function calcGuaranteeAmount() {
            let type = $('#guarantee_type').val();
            if (type !== 'ไม่มี') {
                let budgetInput = $('#project_budget').val();
                let budget = parseFloat(budgetInput.replace(/,/g, '')) || 0;
                let amount = budget * 0.05;
                if (amount > 0) {
                    $('#guarantee_amount').val(amount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }));
                } else {
                    $('#guarantee_amount').val('');
                }
            }
        }

        // คํานวณวันสิ้นสุดสัญญาอัตโนมัติ
        function calcEndDate() {
            let startDateStr = $('#start_date').val();
            let val = parseInt($('#warranty_value').val()) || 0;
            let unit = $('#warranty_unit').val();

            if (!startDateStr || val === 0) return;

            let startDate = new Date(startDateStr);

            if (unit === 'years') {
                startDate.setFullYear(startDate.getFullYear() + val);
            } else if (unit === 'days') {
                startDate.setDate(startDate.getDate() + val);
            }

            let y = startDate.getFullYear();
            let m = ("0" + (startDate.getMonth() + 1)).slice(-2);
            let d = ("0" + startDate.getDate()).slice(-2);

            let formattedDate = `${y}-${m}-${d}`;
            document.getElementById("end_date")._flatpickr.setDate(formattedDate);
        }
    </script>
</body>

</html>