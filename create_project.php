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

        // 🔥 [1] แก้ไข: รับค่าตัวแปรให้ตรงกับที่ bind_param เรียกใช้
        // (ในโค้ดเก่าคุณประกาศ $alert_days แต่ข้างล่างเรียก $alert_contract_days ทำให้ค่าเป็น null)
        $alert_contract_days = intval($_POST['alert_contract_days'] ?? 30);
        $alert_warranty_days = intval($_POST['alert_warranty_days'] ?? 30);

        $job_type_id = $_POST['job_type_id'] ?? null;
        $sales_user = $_POST['sales_user'] ?? null;
        $project_name = $_POST['project_name'] ?? '';
        $project_budget = floatval(str_replace(',', '', $_POST['project_budget'] ?? 0));
        $customer_id = $_POST['customer_id'] ?? null;

        // 🔥 [NEW] Logic: จัดการลูกค้าใหม่ (แทรกตรงนี้ก่อนจะไปดึงข้อมูลลูกค้าเดิม)
        if (isset($_POST['is_new_customer']) && $_POST['is_new_customer'] == '1') {
            // 1. รับค่าจากฟอร์มลูกค้าใหม่
            $new_name = $_POST['new_customer_name'] ?? '';
            $new_phone = $_POST['new_phone_number'] ?? '';
            $new_affil = $_POST['new_affiliation'] ?? '';
            $new_addr = $_POST['new_address'] ?? '';
            $new_sub = $_POST['new_sub_district'] ?? '';
            $new_dist = $_POST['new_district'] ?? '';
            $new_prov = $_POST['new_province'] ?? '';
            $new_zip = $_POST['new_zip_code'] ?? '';

            // ✅ [NEW] รับค่าผู้ติดต่อและเบอร์มือถือ
            $new_contact_person = $_POST['new_contact_person'] ?? '';
            $new_contact_phone = $_POST['new_contact_phone'] ?? '';

            $new_remark = $_POST['new_remark'] ?? '';

            // 2. Insert ลงตาราง customers (เพิ่ม contact_person, contact_phone)
            if (!empty($new_name)) {
                $sql_new_cust = "INSERT INTO customers (
                    customer_name, phone_number, affiliation, address, sub_district, district, province, zip_code, 
                    contact_person, contact_phone, remark, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

                $stmt_nc = $conn->prepare($sql_new_cust);

                // s = 11 ตัว (เพิ่มมา 2 ตัว)
                $stmt_nc->bind_param(
                    "sssssssssss",
                    $new_name,
                    $new_phone,
                    $new_affil,
                    $new_addr,
                    $new_sub,
                    $new_dist,
                    $new_prov,
                    $new_zip,
                    $new_contact_person,
                    $new_contact_phone,
                    $new_remark
                );

                if ($stmt_nc->execute()) {
                    $customer_id = $stmt_nc->insert_id; // ได้ ID ลูกค้าใหม่

                    // 3. เตรียมข้อมูล Address Snapshot
                    $address_parts = array_filter([$new_addr, $new_sub, $new_dist, $new_prov, $new_zip]);
                    $customer_address_full = implode(' ', $address_parts);
                    $customer_phone = $new_phone;
                    $customer_affiliation = $new_affil;
                } else {
                    throw new Exception("ไม่สามารถบันทึกข้อมูลลูกค้าใหม่ได้: " . $stmt_nc->error);
                }
                $stmt_nc->close();
            } else {
                throw new Exception("กรุณาระบุชื่อลูกค้าใหม่");
            }
        }
        // ถ้าไม่ใช่ลูกค้าใหม่ ให้ใช้ Logic เดิม (ดึงข้อมูลจาก DB)
        else {
            // ... (วางโค้ดส่วน // 1.1 Fetch details... เดิม ไว้ใน block else นี้) ...
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
        }

        // กำหนดค่าเข้าตัวแปรหลัก (เพื่อให้ Logic เดิมข้างล่างทำงานต่อได้)
        $customer_address = $customer_address_full;

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

        $customer_address = $customer_address_full;

        $bidding_type = $_POST['bidding_type'] ?? 'ไม่ยื่น';
        $bidding_date = ($bidding_type == 'ยื่น' && !empty($_POST['bidding_date'])) ? $_POST['bidding_date'] : null;

        $quotation_no = $_POST['quotation_no'] ?? null;
        $quote_creator = $_POST['quote_creator'] ?? null;

        $guarantee_type = $_POST['guarantee_type'] ?? 'ไม่มี';
        $guarantee_no = null;
        $guarantee_start_date = null;
        $guarantee_end_date = null;
        $guarantee_amount = 0;

        if ($guarantee_type == 'หนังสือค้ำ') {
            $guarantee_no = $_POST['guarantee_no'] ?? null;
            // รับค่าจากช่องของหนังสือค้ำ
            $guarantee_start_date = !empty($_POST['guarantee_start_date']) ? $_POST['guarantee_start_date'] : null;
            $guarantee_end_date = !empty($_POST['guarantee_end_date']) ? $_POST['guarantee_end_date'] : null;
        } elseif ($guarantee_type == 'เงินสด') {
            // รับค่าจากช่องของเงินสด (ใช้ชื่อตัวแปรเดียวกับ DB เพื่อความง่าย)
            $guarantee_start_date = !empty($_POST['cash_start_date']) ? $_POST['cash_start_date'] : null;
            $guarantee_end_date = !empty($_POST['cash_end_date']) ? $_POST['cash_end_date'] : null;
        }

        if ($guarantee_type != 'ไม่มี') {
            $guarantee_amount = floatval(str_replace(',', '', $_POST['guarantee_amount'] ?? 0));
        }

        if (!empty($contract_no) && !empty($start_date) && $start_date != '0000-00-00' && !empty($end_date) && $end_date != '0000-00-00') {
            $status = 'เซ็นสัญญา';
            $stage_id = 2; // ✅ ID 2 = เซ็นสัญญาแล้ว (ต้องตรงกับในตาราง setup_project_stages)
        } else {
            $status = 'รอเซ็นสัญญา';
            $stage_id = 1; // ❌ ID 1 = รอเซ็นสัญญา
        }

        // เช็คเผื่อกรณีที่เป็นโปรเจกต์เดิมที่มีการเปิด PO ไปแล้ว (stage_id >= 3) ห้ามถอยหลังกลับ
        if (!empty($_POST['edit_id'])) {
            $check_stage = $conn->query("SELECT stage_id FROM projects WHERE id = " . intval($_POST['edit_id']));
            if ($check_stage && $current_stage = $check_stage->fetch_assoc()) {
                if ($current_stage['stage_id'] >= 3) {
                    $stage_id = $current_stage['stage_id']; // คงค่าเดิมไว้
                    // $status = อาจจะปล่อยตามเดิมหรือดึงชื่อเก่ามาก็ได้
                }
            }
        }
        // ==========================================================

        $edit_id = $_POST['edit_id'] ?? null;

        if ($edit_id) {
            // ==========================================
            // CASE UPDATE (เพิ่ม stage_id=?)
            // ==========================================
            $sql = "UPDATE projects SET 
                company_id=?, contract_no=?, start_date=?, warranty_value=?, warranty_unit=?, end_date=?, 
                alert_days_before_expire=?, alert_warranty_days=?, 
                job_type_id=?, sales_user=?, project_name=?, project_budget=?, customer_id=?, customer_address=?, customer_phone=?, customer_affiliation=?,
                bidding_type=?, bidding_date=?, quotation_no=?, quote_creator=?, 
                guarantee_type=?, guarantee_no=?, guarantee_start_date=?, guarantee_end_date=?, guarantee_amount=?, status=?, recorder=?, stage_id=?
                WHERE id=?";

            $stmt = $conn->prepare($sql);

            // เพิ่ม type ตัวอักษร i (Integer) สำหรับ stage_id ก่อนตัว id
            $stmt->bind_param(
                "ississiiissdisssssssssssdsiii",
                $company_id,
                $contract_no,
                $start_date,
                $warranty_value,
                $warranty_unit,
                $end_date,
                $alert_contract_days,
                $alert_warranty_days,
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
                $stage_id, // 🟢 ตัวแปรใหม่ที่เพิ่มเข้ามา
                $edit_id
            );
            $msg = 'อัปเดตโครงการสำเร็จ';

        } else {
            // ==========================================
            // CASE INSERT (27 Params)
            // ==========================================
            $sql = "INSERT INTO projects (
                company_id, contract_no, start_date, warranty_value, warranty_unit, end_date, 
                alert_days_before_expire, alert_warranty_days,
                job_type_id, sales_user, project_name, project_budget, customer_id, customer_address, customer_phone, customer_affiliation,
                bidding_type, bidding_date, quotation_no, quote_creator, 
                guarantee_type, guarantee_no, guarantee_start_date, guarantee_end_date, guarantee_amount, status, recorder
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            // 🔥 [3] แก้ไข Type String ให้ครบ 27 ตัว (เพิ่ม s ไป 1 ตัวตรงกลาง)
            // ississiiissdisssssssssssdss
            $stmt->bind_param(
                "ississiiissdisssssssssssdss",
                $company_id,
                $contract_no,
                $start_date,
                $warranty_value,
                $warranty_unit,
                $end_date,
                $alert_contract_days,
                $alert_warranty_days,
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
            throw new Exception("SQL Error: " . $stmt->error);
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
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    <script type="text/javascript"
        src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dependencies/JQL.min.js"></script>
    <script type="text/javascript"
        src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dependencies/typeahead.bundle.js"></script>
    <link rel="stylesheet"
        href="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dist/jquery.Thailand.min.css">
    <script type="text/javascript"
        src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dist/jquery.Thailand.min.js"></script>

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



        .tt-menu {
            width: 100%;
            margin-top: 5px;
            padding: 8px 0;
            background-color: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
        }

        .tt-suggestion {
            padding: 8px 15px;
            font-size: 0.95rem;
            color: #334155;
            cursor: pointer;
        }

        .tt-suggestion:hover {
            background-color: #f1f5f9;
            color: #3b82f6;
        }

        .tt-cursor {
            /* ตอนกดลูกศรเลื่อนลง */
            background-color: #3b82f6 !important;
            color: #fff !important;
        }

        .select2-container .select2-selection--single {
            height: 38px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: 12px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
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
                        <?php
                        // ✅ 1. รีเซ็ต Pointer ข้อมูลบริษัท (สำคัญ! เผื่อมีการใช้ตัวแปรนี้ไปแล้ว)
                        if ($companies) {
                            $companies->data_seek(0);

                            while ($c = $companies->fetch_assoc()) {
                                // ✅ 2. เช็คเงื่อนไขเทียบกับข้อมูลเดิม ($project_data)
                                $selected = (isset($project_data['company_id']) && $project_data['company_id'] == $c['id']) ? 'selected' : '';

                                echo "<option value='{$c['id']}' {$selected}>{$c['company_name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <div class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>ชื่อลูกค้า <span style="color:red">*</span></span>

                        <div style="font-size:0.85rem; color:#2563eb; display:flex; align-items:center; gap:6px;">
                            <input type="checkbox" name="is_new_customer" id="is_new_customer" value="1"
                                onchange="toggleCustomerMode()" style="width:16px; height:16px; cursor:pointer;">

                            <span style="cursor:default;"> <i class="fas fa-plus-circle"></i> เพิ่มลูกค้าใหม่
                            </span>
                        </div>
                    </div>

                    <div id="existing_customer_box">
                        <select name="customer_id" id="customer_id" class="form-control select2">
                            <option value="">-- เลือกลูกค้า --</option>
                            <?php
                            if ($customers)
                                $customers->data_seek(0);
                            while ($cust = $customers->fetch_assoc()) {
                                $selected = ($project_data && $project_data['customer_id'] == $cust['customer_id']) ? 'selected' : '';
                                echo "<option value='{$cust['customer_id']}' {$selected}>{$cust['customer_name']}</option>";
                            } ?>
                        </select>
                    </div>

                    <div id="new_customer_box" style="display:none;">
                        <input type="text" name="new_customer_name" id="new_customer_name" class="form-control"
                            placeholder="ระบุชื่อลูกค้า / หน่วยงานใหม่...">
                    </div>
                </div>
            </div>

            <div id="new_customer_details" class="hidden-group"
                style="margin-bottom:20px; border:1px dashed #2563eb; background:#eff6ff;">
                <div class="section-title" style="margin-top:0; background:none; padding-left:0; color:#1e40af;">
                    <i class="fas fa-user-plus"></i> รายละเอียดลูกค้าใหม่
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">สังกัด / หน่วยงาน</label>
                        <input type="text" name="new_affiliation" class="form-control" placeholder="เช่น สพฐ., อบต.">
                    </div>
                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์หน่วยงาน (Office)</label>
                        <input type="text" name="new_phone_number" class="form-control" placeholder="0xx-xxx-xxxx">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" style="color:#059669;"><i class="fas fa-user-tie"></i> ชื่อผู้ติดต่อ
                            (Contact Person)</label>
                        <input type="text" name="new_contact_person" class="form-control"
                            placeholder="ชื่อ-นามสกุล ผู้ประสานงาน">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#059669;"><i class="fas fa-mobile-alt"></i>
                            เบอร์มือถือผู้ติดต่อ</label>
                        <input type="text" name="new_contact_phone" class="form-control" placeholder="0xx-xxx-xxxx">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">ที่อยู่ (เลขที่, หมู่, ซอย, ถนน)</label>
                    <input type="text" name="new_address" class="form-control" placeholder="ระบุที่อยู่...">
                </div>

                <div style="text-align: right; margin-bottom: 10px;">
                    <button type="button" class="btn-cancel" onclick="clearAddress()"
                        style="padding: 5px 15px; font-size: 0.85rem; width: auto;">
                        <i class="fas fa-sync-alt"></i> ล้างค่าที่อยู่
                    </button>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">จังหวัด</label>
                        <select id="sel_province" name="new_province" class="form-control select2-addr">
                            <option value="">-- เลือกจังหวัด --</option>
                            <?php
                            $res_p = $conn->query("SELECT * FROM provinces ORDER BY province_name ASC");
                            if ($res_p) {
                                while ($p = $res_p->fetch_assoc()) {
                                    echo "<option value='{$p['province_name']}' data-id='{$p['id']}'>{$p['province_name']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">อำเภอ / เขต</label>
                        <select id="sel_amphure" name="new_district" class="form-control select2-addr">
                            <option value="">-- เลือกอำเภอ --</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">ตำบล / แขวง</label>
                        <select id="sel_district" name="new_sub_district" class="form-control select2-addr">
                            <option value="">-- เลือกตำบล --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">รหัสไปรษณีย์</label>
                        <select id="sel_zipcode" name="new_zip_code" class="form-control select2-addr">
                            <option value="">-- เลือกรหัสไปรษณีย์ --</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">หมายเหตุ (ถ้ามี)</label>
                    <textarea name="new_remark" class="form-control" rows="2"></textarea>
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
                        <input type="text" name="guarantee_start_date" id="guarantee_start_date"
                            class="form-control datepicker" placeholder="เลือกวันที่..."
                            onchange="masterCalcWarrantyEndDate()"
                            value="<?= htmlspecialchars($project_data['guarantee_start_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">วันที่สิ้นสุดหนังสือ (คำนวณอัตโนมัติ)</label>
                        <input type="text" name="guarantee_end_date" id="guarantee_end_date"
                            class="form-control datepicker" placeholder="เลือกวันที่..."
                            style="background:#fffbeb; font-weight:bold; color:#d97706;"
                            value="<?= htmlspecialchars($project_data['guarantee_end_date'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div id="cash_guarantee_section" style="<?= $g_type == 'เงินสด' ? 'display:block;' : 'display:none;' ?>">
                <div class="grid-2"
                    style="background: #ecfdf5; border: 1px dashed #10b981; padding: 15px; border-radius: 10px; margin-top: 10px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" style="color: #047857;">วันที่เริ่มรับประกัน (Start)</label>
                        <input type="text" name="cash_start_date" id="cash_start_date" class="form-control datepicker"
                            placeholder="เลือกวันที่..." onchange="masterCalcWarrantyEndDate()"
                            value="<?= htmlspecialchars($g_type == 'เงินสด' ? ($project_data['guarantee_start_date'] ?? '') : '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color: #047857;">วันที่สิ้นสุดประกัน
                            (คำนวณจากระยะเวลารับประกัน)</label>
                        <input type="text" name="cash_end_date" id="cash_end_date" class="form-control datepicker"
                            placeholder="เลือกวันที่..." style="background:#ffffff; font-weight:bold; color:#047857;"
                            onchange="calcWarrantyFromDate()"
                            value="<?= htmlspecialchars($g_type == 'เงินสด' ? ($project_data['guarantee_end_date'] ?? '') : '') ?>">
                    </div>
                </div>
            </div>

            <div class="grid-2" id="warranty_duration_group"
                style="<?= ($project_data['guarantee_type'] ?? 'ไม่มี') == 'ไม่มี' ? 'display:none;' : '' ?>">
                <div class="form-group">
                    <label class="form-label" style="color: #2563eb;">ระยะเวลารับประกัน
                        (ใช้คำนวณวันหมดอายุหนังสือค้ำ)</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" name="warranty_value" id="warranty_value" class="form-control"
                            placeholder="ระบุตัวเลข"
                            value="<?= htmlspecialchars($project_data['warranty_value'] ?? 0) ?>" min="0"
                            oninput="masterCalcWarrantyEndDate()">

                        <select name="warranty_unit" id="warranty_unit" class="form-control"
                            onchange="masterCalcWarrantyEndDate()">
                            <option value="days" <?= ($project_data && $project_data['warranty_unit'] == 'days') ? 'selected' : '' ?>>วัน (Days)</option>
                            <option value="years" <?= ($project_data && $project_data['warranty_unit'] == 'years') ? 'selected' : '' ?>>ปี (Years)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: #2563eb;"><i class="fas fa-bell"></i> แจ้งเตือนก่อนหมดประกัน
                        (วัน)</label>
                    <input type="number" name="alert_warranty_days" class="form-control"
                        placeholder="ระบุจำนวนวัน (เช่น 30)"
                        value="<?= htmlspecialchars($project_data['alert_warranty_days'] ?? 30) ?>" min="0">
                </div>
            </div>

            <div class="section-title"><i class="fas fa-file-contract"></i> ข้อมูลสัญญา (Contract)</div>

            <div class="form-group">
                <label class="form-label">เลขที่สัญญา</label>
                <input type="text" name="contract_no" class="form-control" placeholder="ระบุเลขที่สัญญา..."
                    value="<?= htmlspecialchars($project_data['contract_no'] ?? '') ?>">
            </div>

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">วันที่เริ่มสัญญา</label>
                    <input type="text" name="start_date" id="start_date" class="form-control datepicker"
                        placeholder="เลือกวันที่..." onchange="calcContractEndDate()"
                        value="<?= htmlspecialchars($project_data['start_date'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: #059669;">ระยะเวลาสัญญา</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="number" id="contract_duration" class="form-control" placeholder="ระบุจำนวน" min="0"
                            oninput="calcContractEndDate()">
                        <select id="contract_unit" class="form-control" style="width: 80px;"
                            onchange="calcContractEndDate()">
                            <option value="days">วัน</option>
                            <option value="months">เดือน</option>
                            <option value="years">ปี</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">วันที่สิ้นสุดสัญญา (คำนวณอัตโนมัติ)</label>
                    <input type="text" name="end_date" id="end_date" class="form-control datepicker"
                        placeholder="เลือกวันที่..." style="background:#f0fdf4; font-weight:bold; color:#15803d;"
                        value="<?= htmlspecialchars($project_data['end_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label" style="color: #ea580c;"><i class="fas fa-bell"></i> แจ้งเตือนก่อนหมดสัญญา
                    (วัน)</label>
                <input type="number" name="alert_contract_days" class="form-control"
                    placeholder="ระบุจำนวนวัน (เช่น 45, 60)" style="width: 50%;"
                    value="<?= htmlspecialchars($project_data['alert_contract_days'] ?? 30) ?>" min="0">
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
        // --- [1] ตัวแปร Global ---
        let addressData = [];
        let isUpdating = false;
        function clearAddress() {
            // 1. ล้างค่าในช่องโชว์ให้หายไปก่อน
            $('.select2-addr').val(null).trigger('change.select2');

            // 2. ถ้ายังไม่มีข้อมูล ไม่ต้องทำต่อ
            if (addressData.length === 0) return;

            // 3. (ไม้ตาย) บังคับสร้างรายการใหม่ทั้งหมดจากฐานข้อมูลดิบ 
            // โดยไม่ผ่าน Logic การกรองใดๆ ทั้งสิ้น เพื่อให้มั่นใจว่าได้ครบทุกตัว
            const fullLists = {
                sel_province: [...new Set(addressData.map(i => i.p))].filter(Boolean).sort(),
                sel_amphure: [...new Set(addressData.map(i => i.a))].filter(Boolean).sort(),
                sel_district: [...new Set(addressData.map(i => i.d))].filter(Boolean).sort(),
                sel_zipcode: [...new Set(addressData.map(i => i.z))].filter(Boolean).sort()
            };

            // 4. ยัดข้อมูลใหม่ใส่ Dropdown ทุกช่อง
            Object.keys(fullLists).forEach(id => {
                const $el = $('#' + id);
                let html = '<option value="">-- เลือก --</option>';
                fullLists[id].forEach(val => {
                    html += `<option value="${val}">${val}</option>`;
                });
                $el.html(html).trigger('change.select2');
            });
        }

        // --- [2] ฟังก์ชัน Helper (อยู่นอก ready) ---
        function toggleCustomerMode() {
            const isNew = document.getElementById('is_new_customer').checked;
            if (isNew) {
                $('#existing_customer_box').hide();
                $('#new_customer_box, #new_customer_details').show();
                $('#new_customer_name').attr('required', 'required');
                $('#customer_id').val(null).trigger('change').removeAttr('required');
                $('.select2-addr').val(null).trigger('change.select2'); // ล้างค่าที่อยู่
            } else {
                $('#existing_customer_box').show();
                $('#new_customer_box, #new_customer_details').hide();
                $('#customer_id').attr('required', 'required');
                $('#new_customer_name').removeAttr('required').val('');
            }
        }

        function formatNumber(input) {
            let val = input.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
            if (val) input.value = parseFloat(val).toLocaleString('en-US');
        }

        function toggleBidding() {
            $('#bidding_type').val() === 'ยื่น' ? $('#bidding_date_box').slideDown() : $('#bidding_date_box').slideUp();
        }

        function toggleGuarantee() {
            let type = $('#guarantee_type').val();
            $('#guarantee_section, #cash_guarantee_section, #guarantee_amount_box, #warranty_duration_group').slideUp();
            if (type === 'หนังสือค้ำ') { $('#guarantee_section, #guarantee_amount_box, #warranty_duration_group').slideDown(); }
            else if (type === 'เงินสด') { $('#cash_guarantee_section, #guarantee_amount_box, #warranty_duration_group').slideDown(); }
            calcGuaranteeAmount();
        }

        function calcGuaranteeAmount() {
            let budget = parseFloat($('#project_budget').val().replace(/,/g, '')) || 0;
            $('#guarantee_amount').val(budget > 0 ? (budget * 0.05).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '');
        }

        function masterCalcWarrantyEndDate() {
            let duration = parseInt($('#warranty_value').val()) || 0, unit = $('#warranty_unit').val();
            let type = $('#guarantee_type').val();
            let startStr = (type === 'หนังสือค้ำ') ? $('#guarantee_start_date').val() : $('#cash_start_date').val();
            let targetId = (type === 'หนังสือค้ำ') ? 'guarantee_end_date' : 'cash_end_date';
            if (!startStr || duration === 0) return;
            let d = new Date(startStr);
            unit === 'years' ? d.setFullYear(d.getFullYear() + duration) : d.setDate(d.getDate() + duration);
            d.setDate(d.getDate() - 1);
            let f = d.getFullYear() + '-' + ("0" + (d.getMonth() + 1)).slice(-2) + '-' + ("0" + d.getDate()).slice(-2);
            let target = document.getElementById(targetId);
            if (target && target._flatpickr) target._flatpickr.setDate(f); else if (target) target.value = f;
        }

        function calcContractEndDate() {
            let s = $('#start_date').val(), dur = parseInt($('#contract_duration').val()) || 0, u = $('#contract_unit').val();
            if (!s || dur === 0) return;
            let d = new Date(s);
            if (u === 'days') d.setDate(d.getDate() + dur); else if (u === 'months') d.setMonth(d.getMonth() + dur); else d.setFullYear(d.getFullYear() + dur);
            d.setDate(d.getDate() - 1);
            let f = d.getFullYear() + '-' + ("0" + (d.getMonth() + 1)).slice(-2) + '-' + ("0" + d.getDate()).slice(-2);
            let target = document.getElementById("end_date");
            if (target && target._flatpickr) target._flatpickr.setDate(f);
        }

        function calcWarrantyFromDate() {
            let sStr = $('#cash_start_date').val(), eStr = $('#cash_end_date').val();
            if (!sStr || !eStr) return;
            let s = new Date(sStr), e = new Date(eStr);
            let diffDays = Math.round((e - s) / (1000 * 3600 * 24)) + 1;
            if (diffDays <= 0) return Swal.fire('เตือน', 'วันสิ้นสุดต้องมากกว่าวันเริ่ม', 'warning');

            let isYear = false;
            for (let i = 1; i <= 20; i++) {
                let t = new Date(sStr); t.setFullYear(t.getFullYear() + i); t.setDate(t.getDate() - 1);
                if (t.toISOString().split('T')[0] === eStr) {
                    $('#warranty_value').val(i); $('#warranty_unit').val('years').trigger('change');
                    isYear = true; break;
                }
            }
            if (!isYear) { $('#warranty_value').val(diffDays); $('#warranty_unit').val('days').trigger('change'); }
        }

        // --- [3] ส่วนเริ่มต้นระบบ ---
        $(document).ready(function () {
            $('.select2').select2({ width: '100%' });

            const $addrFields = $('.select2-addr').select2({
                placeholder: 'กำลังโหลดข้อมูล...', allowClear: true, width: '100%', disabled: true
            });

            flatpickr(".datepicker", { altInput: true, altFormat: "d/m/Y", dateFormat: "Y-m-d", locale: "th" });

            // 🔥 ดึงไฟล์ดิบ (Raw Data) เพื่อความชัวร์เรื่องข้อมูล
            $.getJSON('https://raw.githubusercontent.com/earthchie/jquery.Thailand.js/master/jquery.Thailand.js/database/raw_database/raw_database.json')
                .done(function (res) {
                    addressData = res.map(item => ({
                        d: item.district, a: item.amphoe, p: item.province, z: String(item.zipcode)
                    }));

                    $addrFields.prop('disabled', false).select2({ placeholder: '-- ค้นหา/เลือก --', allowClear: true, width: '100%' });
                    updateAddressOptions();
                });

            // ⭐ แก้ไข: เมื่อมีการ "เปลี่ยนค่า" หรือ "ลบค่า" (Clear)
            $('.select2-addr').on('change', function (e) {
                // ทำงานเฉพาะเมื่อ User เป็นคนเลือกเอง (originalEvent)
                // หรือ กดกากบาท X (val เป็น null)
                // *สำคัญ* เราจะไม่ให้มันทำงานตอนกดปุ่ม "ล้างที่อยู่" (เพราะเราจัดการใน clearAddress แล้ว)
                if (e.originalEvent || $(this).data('manual')) {
                    const val = $(this).val();

                    // ถ้าเป็นการกดกากบาท X (ค่าว่าง) ให้ล้างช่องลูกน้องทิ้งด้วย
                    if (!val) {
                        // ไม่ต้องทำอะไรพิเศษ เพราะเดี๋ยว updateAddressOptions จะจัดการกรองใหม่ให้เอง
                    }

                    updateAddressOptions(this.id);
                }
            });

            // เพิ่มส่วนนี้: ดักจับตอนกดปุ่ม X (Clear) ของ Select2 โดยตรง
            $('.select2-addr').on('select2:select', function () {
                $(this).data('manual', true).trigger('change').data('manual', false);
            });

            // ดักจับการกด X (Clear) ในช่อง Select2 โดยตรง
            $('.select2-addr').on('select2:clear', function (e) {
                $(this).data('manual', true).trigger('change').data('manual', false);
            });

            function updateAddressOptions(triggerId) {
                if (addressData.length === 0) return;

                const selP = $('#sel_province').val();
                const selA = $('#sel_amphure').val();
                const selD = $('#sel_district').val();
                const selZ = $('#sel_zipcode').val();

                const filtered = addressData.filter(i => {
                    return (!selP || i.p === selP) && (!selA || i.a === selA) && (!selD || i.d === selD) && (!selZ || i.z === selZ);
                });

                const lists = {
                    sel_province: [...new Set(filtered.map(i => i.p))].filter(Boolean).sort(),
                    sel_amphure: [...new Set(filtered.map(i => i.a))].filter(Boolean).sort(),
                    sel_district: [...new Set(filtered.map(i => i.d))].filter(Boolean).sort(),
                    sel_zipcode: [...new Set(filtered.map(i => i.z))].filter(Boolean).sort()
                };

                Object.keys(lists).forEach(id => {
                    if (id !== triggerId) {
                        const $el = $('#' + id);
                        const cur = $el.val();
                        let options = '<option value="">-- เลือก --</option>';
                        lists[id].forEach(val => {
                            const isSelected = (lists[id].length === 1 || String(val) === String(cur)) ? 'selected' : '';
                            options += `<option value="${val}" ${isSelected}>${val}</option>`;
                        });
                        $el.html(options).trigger('change.select2');
                    }
                });
            }

            // AJAX Submit
            $('#projectForm').on('submit', function (e) {
                e.preventDefault();
                let formData = new FormData(this);
                Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
                $.ajax({
                    url: 'create_project.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('สำเร็จ', res.message, 'success').then(() => { window.location.href = 'project_dashboard.php'; });
                        } else { Swal.fire('เกิดข้อผิดพลาด', res.message, 'error'); }
                    }
                });
            });
        });
    </script>
</body>

</html>