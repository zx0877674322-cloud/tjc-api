<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'db_connect.php';

// ==========================================================================
//  PART 1: DATA PREPARATION (LOGIC)
// ==========================================================================

$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$get_site_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$row_edit = [];

// 1. ดึงข้อมูล Service Request (กรณีแก้ไข)
$val_items_data = []; // เก็บข้อมูลรายการสินค้าแบบละเอียด

if ($edit_id > 0) {
    $sql_edit = "SELECT * FROM service_requests WHERE id = ?";
    $stmt_edit = $conn->prepare($sql_edit);
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $res_edit = $stmt_edit->get_result();
    if ($res_edit->num_rows > 0) {
        $row_edit = $res_edit->fetch_assoc();
        if (empty($get_site_id))
            $get_site_id = $row_edit['site_id'];

        // พยายาม decode JSON จาก field project_item_name
        $json_try = json_decode($row_edit['project_item_name'] ?? '[]', true);

        if (is_array($json_try) && !empty($json_try)) {
            // เช็คว่าเป็นโครงสร้างใหม่ (มี key 'product') หรือเก่า
            if (isset($json_try[0]['product'])) {
                $val_items_data = $json_try;
            } else {
                // แปลงข้อมูลเก่าให้เข้ากับโครงสร้างใหม่
                foreach ($json_try as $index => $item_name) {
                    $val_items_data[] = [
                        'product' => $item_name,
                        'job_type' => ($index == 0) ? ($row_edit['job_type'] ?? '') : '',
                        'job_other' => ($index == 0) ? ($row_edit['job_type_other'] ?? '') : '',
                        'issue' => ($index == 0) ? ($row_edit['issue_description'] ?? '') : ''
                    ];
                }
            }
        }
    }
}

// ถ้าไม่มีข้อมูลเลย ให้สร้าง array ว่างๆ ไว้ 1 อัน (เพื่อให้แสดงกล่องแรกเสมอ)
if (empty($val_items_data)) {
    $val_items_data[] = ['product' => '', 'job_type' => '', 'job_other' => '', 'issue' => ''];
}

// 2. เตรียมตัวแปรอื่นๆ
$val_remark = $row_edit['remark'] ?? '';
$val_request_date = isset($row_edit['request_date']) ? date('Y-m-d H:i', strtotime($row_edit['request_date'])) : date('Y-m-d H:i');
$val_receiver = $row_edit['receiver_by'] ?? ($_SESSION['fullname'] ?? '');
$val_reporter = $row_edit['reporter_name'] ?? '';
$val_contact_type = $row_edit['contact_channel'] ?? '';
$val_contact_detail = $row_edit['contact_detail'] ?? '';
$val_urgency = $row_edit['urgency'] ?? 'normal';
$val_initial_advice = $row_edit['initial_advice'] ?? '';
$val_assessment = $row_edit['assessment'] ?? '';

// 3. เตรียมข้อมูลโครงการ
$site_code_show = "-";
$project_name_show = "-";
$customer_info_show = "-";
$contract_info = ['start' => '-', 'end' => '-', 'budget' => '-', 'no' => '-'];
$is_expired = false;

if ($get_site_id > 0) {
    $sql_proj = "SELECT a.project_name, a.contract_number, a.project_budget, a.contract_start_date, a.contract_end_date, 
                        c.customer_name, c.province 
                 FROM project_contracts a
                 LEFT JOIN customers c ON a.customer_id = c.customer_id
                 WHERE a.site_id = ?";
    $stmt = $conn->prepare($sql_proj);
    $stmt->bind_param("i", $get_site_id);
    $stmt->execute();
    $res_proj = $stmt->get_result();

    if ($row_proj = $res_proj->fetch_assoc()) {
        $site_code_show = $get_site_id;
        $project_name_show = $row_proj['project_name'];
        $customer_info_show = ($row_proj['customer_name'] ?? '-') . " (" . ($row_proj['province'] ?? '-') . ")";
        $contract_info['no'] = $row_proj['contract_number'] ?? '-';
        $contract_info['budget'] = !empty($row_proj['project_budget']) ? number_format($row_proj['project_budget'], 2) : '-';

        if (!empty($row_proj['contract_start_date']))
            $contract_info['start'] = date('d/m/Y', strtotime($row_proj['contract_start_date']));

        if (!empty($row_proj['contract_end_date'])) {
            $contract_info['end'] = date('d/m/Y', strtotime($row_proj['contract_end_date']));
            if ($row_proj['contract_end_date'] < date('Y-m-d'))
                $is_expired = true;
        }
    }
} else {
    // โหลด List โครงการทั้งหมดสำหรับ Dropdown ค้นหา
    $all_projects = [];
    $sql_all = "SELECT site_id, project_name FROM project_contracts ORDER BY site_id ASC";
    $res_all = $conn->query($sql_all);
    while ($row = $res_all->fetch_assoc())
        $all_projects[] = $row;
}

// 4. ข้อมูล Fake Items (สำหรับ Dropdown)
$fake_items = [
    "Desktop PC (คอมพิวเตอร์ตั้งโต๊ะ)",
    "Notebook (โน้ตบุ๊ก)",
    "Monitor (จอภาพ)",
    "Printer (เครื่องพิมพ์)",
    "UPS (เครื่องสำรองไฟ)",
    "CCTV Camera (กล้องวงจรปิด)",
    "DVR/NVR (เครื่องบันทึกภาพ)",
    "Network Switch / Router",
    "Access Control",
    "Software / Program",
    "Other"
];
// --- [แก้ไข] 5. ดึงข้อมูลประเภทงานจากฐานข้อมูลเท่านั้น (ไม่มีการ Fix ค่า) ---
$job_types_list = [];
$res_jt = $conn->query("SELECT * FROM job_types ORDER BY id ASC");

// ดึงข้อมูลมาใส่ Array ตามจริง ถ้าไม่มีข้อมูล $job_types_list จะเป็น [] (ว่างเปล่า)
if ($res_jt && $res_jt->num_rows > 0) {
    while ($jt = $res_jt->fetch_assoc()) {
        $job_types_list[] = $jt;
    }
}

$contact_channels_list = [];
$res_cc = $conn->query("SELECT * FROM contact_channels ORDER BY id ASC");
if ($res_cc) {
    while ($cc = $res_cc->fetch_assoc()) {
        $contact_channels_list[] = $cc;
    }
}

// ==========================================================================
//  PART 2: FORM SUBMISSION HANDLING
// ==========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_service') {
    $site_id = $_POST['site_id'] ?? 0;
    $request_date = $_POST['request_date'] ?? date('Y-m-d H:i');
    $expected_finish = date('Y-m-d H:i:s', strtotime($request_date . ' +48 hours'));

    // --- [Logic ใหม่] จัดการข้อมูล Items ที่ส่งมาเป็น Array ---
    $items_data_to_save = [];
    $issue_summary = []; 
    $collected_job_types = []; // [แก้ไข 1] สร้าง Array ไว้เก็บประเภทงานทั้งหมด

    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $index => $itm) {
            // 1. ตรวจสอบข้อมูลสินค้า (product อาจเป็น String หรือ Array)
            $product_input = $itm['product'] ?? [];
            $final_products = [];

            if (is_array($product_input)) {
                $final_products = array_values(array_filter($product_input, function($v) { return !empty($v); }));
            } else if (!empty($product_input)) {
                $final_products = [$product_input];
            }

            // 2. ถ้ามีสินค้า และ มีอาการเสีย -> บันทึก
            if (!empty($final_products) && !empty($itm['issue'])) {
                
                $items_data_to_save[] = [
                    'product'   => $final_products,
                    'job_type'  => $itm['job_type'] ?? '',
                    'job_other' => trim($itm['job_other'] ?? ''),
                    'issue'     => trim($itm['issue'])
                ];

                // สร้างสรุปอาการ (Legacy field)
                $prod_names = implode(", ", $final_products);
                $issue_summary[] = ($index + 1) . ". [" . $prod_names . "] : " . trim($itm['issue']);

                // [แก้ไข 2] เก็บประเภทงานทั้งหมดลง Array (ไม่เอาค่าว่าง)
                if (!empty($itm['job_type'])) {
                    $collected_job_types[] = $itm['job_type'];
                }
            }
        }
    }

    // เตรียมข้อมูล JSON และ String สำหรับบันทึก
    $item_name_json = !empty($items_data_to_save) ? json_encode($items_data_to_save, JSON_UNESCAPED_UNICODE) : "[]";
    $issue_final = !empty($issue_summary) ? implode("\n", $issue_summary) : "-";
    
    // [แก้ไข 3] รวมประเภทงานทั้งหมดคั่นด้วยคอมม่า (ตัดตัวซ้ำออก)
    $unique_types = array_unique($collected_job_types);
    $job_type_final = !empty($unique_types) ? implode(', ', $unique_types) : 'other';
    
    // หมายเหตุ: เช็คใน Database ด้วยว่าคอลัมน์ job_type เป็น VARCHAR(255) หรือไม่ เพื่อให้เก็บข้อความยาวๆ ได้พอ
    $job_other_final = ($job_type_final == 'other' && isset($items_data_to_save[0]['job_other'])) ? $items_data_to_save[0]['job_other'] : '';

    // รับค่าอื่นๆ
    $assess = trim($_POST['assessment']);
    $remark = trim($_POST['remark']);
    $user_updated = $_SESSION['fullname'] ?? 'System';
    $receiver_by = $_POST['receiver_by'];
    $reporter_name = trim($_POST['reporter_name']);
    
    // รับค่า JSON ตัวเดียวจบ (ข้อมูลเบอร์/ไลน์/ต่อ รวมอยู่ในนี้หมดแล้ว)
    $contact_json = $_POST['contact_json'] ?? '[]';
    
    $urgency = $_POST['urgency'];
    $initial_advice = trim($_POST['initial_advice']);
    $status_to_save = 'pending';

    // ❌ [ลบส่วนเช็ค $contact_ext ทิ้งไปเลยครับ มันไม่ได้ใช้แล้ว] ❌

    if (!empty($site_id)) {
        $req_id_update = isset($_POST['req_id_for_update']) ? intval($_POST['req_id_for_update']) : 0;

        if ($req_id_update > 0) {
            // ================= UPDATE =================
            $sql = "UPDATE service_requests SET 
                    site_id=?, request_date=?, project_item_name=?, issue_description=?, assessment=?, remark=?, 
                    updated_by=?, expected_finish_date=?, 
                    receiver_by=?, reporter_name=?, contact_detail=?, 
                    job_type=?, job_type_other=?, urgency=?, initial_advice=?
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            
            // แก้ไข: ใช้ $contact_json และจำนวนตัวแปรครบ 16 ตัว
            $stmt->bind_param("issssssssssssssi",
                $site_id, $request_date, $item_name_json, $issue_final, $assess, $remark,
                $user_updated, $expected_finish,
                $receiver_by, $reporter_name, $contact_json, // <--- ส่ง JSON เข้าไป
                $job_type_final, $job_other_final, $urgency, $initial_advice, $req_id_update
            );
            $msg_title = "อัปเดตข้อมูลเรียบร้อย";
        } else {
            // ================= INSERT =================
            $sql = "INSERT INTO service_requests (
                        site_id, request_date, project_item_name, issue_description, assessment, remark, 
                        updated_by, expected_finish_date, status,
                        receiver_by, reporter_name, contact_detail, 
                        job_type, job_type_other, urgency, initial_advice
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            // แก้ไข: ลด s ลง 1 ตัว และส่งแค่ $contact_json
            $stmt->bind_param("isssssssssssssss",
                $site_id, $request_date, $item_name_json, $issue_final, $assess, $remark,
                $user_updated, $expected_finish, $status_to_save,
                $receiver_by, $reporter_name, $contact_json, // <--- ส่ง JSON เข้าไป
                $job_type_final, $job_other_final, $urgency, $initial_advice
            );
            $msg_title = "เปิดใบงานเรียบร้อย";
        }

        if ($stmt->execute()) {
            $alert_script = "Swal.fire({icon:'success', title:'$msg_title', text:'กำหนดเสร็จภายใน: $expected_finish', showConfirmButton:false, timer:2500}).then(()=>{ window.location.href='service_dashboard.php'; });";
        } else {
            $alert_script = "Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', text:'" . $conn->error . "'});";
        }
    } else {
        $alert_script = "Swal.fire({icon:'warning', title:'แจ้งเตือน', text:'กรุณาเลือกโครงการ'});";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Request Form</title>

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/ServiceRequest.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="main-container">

            <form method="POST" id="serviceForm">
                <input type="hidden" name="action" value="submit_service">
                <input type="hidden" name="req_id_for_update" value="<?php echo $edit_id; ?>">

                <div class="service-card">
                    <div class="card-header-modern">
                        <div>
                            <h2 style="margin:0; font-size:1.5rem; font-weight:600;"><i
                                    class="fas fa-file-signature"></i> แบบฟอร์มแจ้งบริการ</h2>
                            <p style="margin:0; opacity:0.8; font-size:0.85rem; font-weight:300;">Service Request Form
                            </p>
                        </div>
                        <?php if ($get_site_id > 0): ?>
                            <div
                                style="background: rgba(255,255,255,0.2); padding: 6px 18px; border-radius: 50px; font-weight: 500; font-size: 0.9rem; backdrop-filter: blur(5px);">
                                <i class="fas fa-map-marker-alt"></i> Site ID: <?php echo $get_site_id; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-body-modern">

                        <?php if ($get_site_id > 0): ?>
                            <div class="project-info-card">
                                <input type="hidden" name="site_id" value="<?php echo $get_site_id; ?>">
                                <div class="grid-3">
                                    <div class="form-group"><label class="form-label">เลขหน้างาน</label><input type="text"
                                            class="form-control readonly-field" value="<?php echo $site_code_show; ?>"
                                            readonly></div>
                                    <div class="form-group"><label class="form-label">เลขที่สัญญา</label><input type="text"
                                            class="form-control readonly-field" value="<?php echo $contract_info['no']; ?>"
                                            readonly></div>
                                    <div class="form-group"><label class="form-label">งบประมาณ</label><input type="text"
                                            class="form-control readonly-field"
                                            value="<?php echo $contract_info['budget']; ?>" readonly></div>
                                </div>
                                <div class="grid-2">
                                    <div class="form-group"><label class="form-label">ชื่อโครงการ</label><input type="text"
                                            class="form-control readonly-field" value="<?php echo $project_name_show; ?>"
                                            readonly></div>
                                    <div class="form-group"><label class="form-label">ลูกค้า / จังหวัด</label><input
                                            type="text" class="form-control readonly-field"
                                            value="<?php echo $customer_info_show; ?>" readonly></div>
                                </div>
                                <div class="grid-2" style="margin-bottom:0;">
                                    <div class="form-group" style="margin-bottom:0;"><label
                                            class="form-label">วันเริ่มสัญญา</label><input type="text"
                                            class="form-control readonly-field"
                                            value="<?php echo $contract_info['start']; ?>" readonly></div>
                                    <div class="form-group" style="margin-bottom:0;"><label
                                            class="form-label">วันหมดสัญญา</label><input type="text"
                                            class="form-control readonly-field" value="<?php echo $contract_info['end']; ?>"
                                            style="color:<?php echo $is_expired ? '#dc2626' : 'inherit'; ?>; font-weight:<?php echo $is_expired ? 'bold' : 'normal'; ?>;"
                                            readonly></div>
                                </div>
                                <a href="ServiceRequest.php"
                                    style="position: absolute; top: 20px; right: 25px; color: #ef4444; text-decoration: none; font-weight:600; font-size: 0.9rem;"><i
                                        class="fas fa-sync-alt"></i> เปลี่ยนโครงการ</a>
                            </div>
                        <?php else: ?>
                            <div class="form-group" style="margin-bottom: 40px;">
                                <label class="form-label" style="font-size:1.1rem; color:var(--primary);">🔍
                                    ค้นหาโครงการ</label>
                                <select name="site_id" class="form-control select2-search"
                                    onchange="window.location.href='ServiceRequest.php?id='+this.value">
                                    <option value="">-- พิมพ์ชื่อโครงการ หรือ Site ID --</option>
                                    <?php foreach ($all_projects as $p): ?>
                                        <option value="<?php echo $p['site_id']; ?>">
                                            <?php echo $p['site_id'] . " : " . htmlspecialchars($p['project_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="section-title"><i class="fas fa-info-circle"></i> ข้อมูลการแจ้ง (Request Info)</div>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">วันที่แจ้งเรื่อง <span
                                        style="color:var(--danger-text)">*</span></label><input type="text"
                                    id="request_date" name="request_date" class="form-control date-picker"
                                    value="<?php echo $val_request_date; ?>" required onchange="calcDeadline()"></div>
                            <div class="form-group"><label class="form-label">ผู้รับเรื่อง <i class="fas fa-lock"
                                        style="font-size:0.7rem; color:#94a3b8;"></i></label><input type="text"
                                    name="receiver_by" class="form-control readonly-field"
                                    value="<?php echo htmlspecialchars($val_receiver); ?>" readonly></div>
                        </div>

                        <div class="section-title"><i class="fas fa-user-tag"></i> ผู้ติดต่อ (Contact Person)</div>
                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start; background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">ชื่อผู้แจ้ง <span style="color:var(--danger-text)">*</span></label>
                                <input type="text" name="reporter_name" class="form-control" 
                                    value="<?php echo htmlspecialchars($val_reporter); ?>" required 
                                    placeholder="ระบุชื่อผู้แจ้ง..." style="height: 45px;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">ช่องทางติดต่อ (ระบุได้มากกว่า 1)</label>
                                <div id="contact_list_container">
                                    </div>
                                <button type="button" onclick="addContactRow()" class="btn-add-row" 
                                        style="background: #f0f9ff; border: 1px dashed #0ea5e9; color: #0ea5e9; width: 100%; padding: 10px; border-radius: 10px; margin-top: 10px; cursor: pointer; font-weight: 600;">
                                    <i class="fas fa-plus-circle"></i> เพิ่มช่องทางติดต่ออื่น
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="contact_json" id="contact_json">
                        <div class="section-title"><i class="fas fa-tasks"></i> รายละเอียดงาน (Job Details)</div>

                        <div class="form-group" style="max-width: 50%;">
                            <label class="form-label">ความเร่งด่วน <span
                                    style="color:var(--danger-text)">*</span></label>
                            <select name="urgency" class="form-control" required>
                                <option value="normal" <?php echo ($val_urgency == 'normal') ? 'selected' : ''; ?>>🟢 ปกติ
                                </option>
                                <option value="urgent" <?php echo ($val_urgency == 'urgent') ? 'selected' : ''; ?>>🟠 ด่วน
                                </option>
                                <option value="critical" <?php echo ($val_urgency == 'critical') ? 'selected' : ''; ?>>🔴
                                    ด่วนมาก</option>
                            </select>
                        </div>

                        <div style="margin-top: 30px; margin-bottom: 15px;">
                            <label class="form-label"
                                style="font-size: 1.1rem; font-weight: 600; color: var(--primary);">
                                <i class="fas fa-boxes"></i> รายการที่ต้องการแจ้งปัญหา
                            </label>
                        </div>

                        <div id="service-items-container">
                            <?php
                            // วนลูปแสดงรายการหลัก (Main Items)
                            foreach ($val_items_data as $index => $item_data):
                                // ถ้าข้อมูลสินค้าเก็บเป็น array (รองรับหลายชิ้น) ให้ดึงมาใช้
                                $products_list = is_array($item_data['product']) ? $item_data['product'] : [$item_data['product'] ?? ''];

                                $current_job_type = $item_data['job_type'] ?? '';
                                $current_job_other = $item_data['job_other'] ?? '';
                                $current_issue = $item_data['issue'] ?? '';
                                $count = $index + 1;
                                ?>
                                <div class="service-item-box" id="box_<?php echo $index; ?>"
                                    data-index="<?php echo $index; ?>">
                                    <span class="item-counter">รายการที่ <?php echo $count; ?></span>

                                    <?php if ($index > 0): ?>
                                        <button type="button" class="btn-remove-item" onclick="removeServiceItem(this)"
                                            title="ลบรายการนี้"><i class="fas fa-trash-alt"></i></button>
                                    <?php endif; ?>

                                    <div class="product-list-container">
                                        <label class="form-label" style="font-size:0.9rem; color:var(--primary);">สินค้า /
                                            อุปกรณ์ <span style="color:var(--danger-text)">*</span></label>

                                        <?php foreach ($products_list as $p_index => $p_name): ?>
                                            <div class="product-row"
                                                style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                                                <select name="items[<?php echo $index; ?>][product][]"
                                                    class="form-control select2-search" style="width: 100%;" required>
                                                    <option value="">-- เลือกรายการ --</option>
                                                    <?php foreach ($fake_items as $fake): ?>
                                                        <option value="<?php echo htmlspecialchars($fake); ?>" <?php echo ($fake == $p_name) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($fake); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <?php if ($p_index > 0): ?>
                                                    <button type="button" onclick="removeRowAndCheck(this)" 
                                                        style="border:none; background:#fee2e2; color:#ef4444; width:38px; height:38px; border-radius:6px; cursor:pointer;">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div style="text-align: right; margin-bottom: 20px;">
                                        <button type="button" onclick="addProductToBox(this, <?php echo $index; ?>)"
                                            style="background:none; border:none; color:var(--accent-start); font-size:0.85rem; cursor:pointer; font-weight:600;">
                                            <i class="fas fa-plus-circle"></i> เพิ่มสินค้าในรายการนี้
                                        </button>
                                    </div>

                                    <div class="grid-2">
                                        <div class="form-group" style="margin-bottom: 15px;">
                                            <label class="form-label" style="font-size:0.85rem;">ประเภทงาน</label>
                                            <select name="items[<?php echo $index; ?>][job_type]" class="form-control job-type-select" onchange="toggleJobOtherDynamic(this)">
                                                <option value="">-- เลือกประเภทงาน --</option>
                                                <?php foreach ($job_types_list as $jt): ?>
                                                    <option value="<?php echo htmlspecialchars($jt['job_type_name']); ?>" 
                                                        <?php echo ($current_job_type == $jt['job_type_name']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($jt['job_type_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" name="items[<?php echo $index; ?>][job_other]"
                                                class="form-control mt-2 job-other-input"
                                                style="display: <?php echo ($current_job_type == 'other') ? 'block' : 'none'; ?>;"
                                                placeholder="ระบุประเภทอื่นๆ..."
                                                value="<?php echo htmlspecialchars($current_job_other); ?>">
                                        </div>

                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size:0.85rem;">อาการ / ปัญหาที่พบ <span
                                                    style="color:var(--danger-text)">*</span></label>
                                            <textarea name="items[<?php echo $index; ?>][issue]" class="form-control"
                                                rows="2" required placeholder="ระบุอาการเสีย..."
                                                style="min-height: 80px;"><?php echo htmlspecialchars($current_issue); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="btn-add-group">
                            <button type="button" class="btn-add-new-item" onclick="addServiceItemBox()">
                                <i class="fas fa-plus"></i> เพิ่มรายการ
                            </button>
                        </div>

                        <div class="form-group">
                            <label class="form-label">กำหนดเสร็จ (SLA 48 Hours)</label>
                            <div id="deadline_display" class="deadline-box"><i class="fas fa-hourglass-half"></i>
                                กำลังคำนวณ...</div>
                        </div>

                        <div class="section-title"><i class="fas fa-microscope"></i> การวิเคราะห์เบื้องต้น</div>
                        <div class="form-group">
                            <label class="form-label">คำแนะนำเบื้องต้น</label>
                            <textarea name="initial_advice" class="form-control" rows="2"
                                placeholder="คำแนะนำ..."><?php echo htmlspecialchars($val_initial_advice); ?></textarea>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">การประเมิน</label><textarea
                                    name="assessment" class="form-control"
                                    rows="2"><?php echo htmlspecialchars($val_assessment); ?></textarea></div>
                            <div class="form-group"><label class="form-label">หมายเหตุ</label><textarea name="remark"
                                    class="form-control"
                                    rows="2"><?php echo htmlspecialchars($val_remark); ?></textarea></div>
                        </div>

                        <div
                            style="text-align: center; margin-top: 50px; display:flex; justify-content:center; gap:20px;">
                            <a href="service_dashboard.php" class="btn-reset-icon"
                                style="width:auto; padding:0 35px; border-radius:50px; background:#fff; border:1px solid #cbd5e1;">
                                <i class="fas fa-times" style="margin-right:5px;"></i> ยกเลิก
                            </a>
                            <?php if ($get_site_id > 0): ?>
                                <button type="submit" class="btn-create" style="padding:0 40px;"><i class="fas fa-save"></i>
                                    บันทึกข้อมูล</button>
                            <?php else: ?>
                                <button type="button" class="btn-create"
                                    style="background: #cbd5e1; cursor: not-allowed; padding:0 40px;">กรุณาเลือกโครงการด้านบนก่อน</button>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Init Plugins
    flatpickr(".date-picker", { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true, locale: "th" });
    
    $(document).ready(function () {
        // เริ่มต้น Select2
        $('.select2-search').select2({ width: '100%' });

        if (typeof calcDeadline === 'function') calcDeadline();
        $('.job-type-select').each(function () { toggleJobOtherDynamic(this); });

        // 🔥 1. เริ่มเช็คตัวเลือกซ้ำทันทีที่โหลดหน้า
        updateGlobalOptions();
    });

    // Alerts
    <?php if (isset($alert_script)) echo $alert_script; ?>
    <?php if ($is_expired): ?>
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: '⚠️ หมดสัญญาประกันแล้ว', showConfirmButton: false, timer: 5000 });
    <?php endif; ?>

    // ---- Global Variables ----
    let itemIndex = <?php echo count($val_items_data); ?>;
    const fakeItemsList = <?php echo json_encode($fake_items); ?>;
    
    // สร้าง Option List สำหรับประเภทงาน
    let jobOptionsHtml = '<option value="">-- เลือกประเภทงาน --</option>';
    <?php if (!empty($job_types_list)): ?>
        <?php foreach($job_types_list as $jt): ?>
            jobOptionsHtml += `<option value="<?php echo htmlspecialchars($jt['job_type_name']); ?>"><?php echo htmlspecialchars($jt['job_type_name']); ?></option>`;
        <?php endforeach; ?>
    <?php endif; ?>

    // สร้าง Option List สำหรับสินค้า
    let optionsStr = '<option value="">-- เลือกรายการ --</option>';
    fakeItemsList.forEach(item => {
        optionsStr += `<option value="${item}">${item}</option>`;
    });

    // ---- Main Functions ----

    // 1. ฟังก์ชันเพิ่มกล่องรายการใหม่ (Main Box)
    function addServiceItemBox() {
        let currentCount = $('#service-items-container .service-item-box').length + 1;

        const html = `
            <div class="service-item-box" id="box_${itemIndex}" data-index="${itemIndex}">
                <span class="item-counter">รายการที่ ${currentCount}</span>
                <button type="button" class="btn-remove-item" onclick="removeServiceItem(this)" title="ลบรายการนี้"><i class="fas fa-trash-alt"></i></button>

                <div class="product-list-container">
                    <label class="form-label" style="font-size:0.9rem; color:var(--primary);">สินค้า / อุปกรณ์ <span style="color:var(--danger-text)">*</span></label>
                    <div class="product-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                        <select name="items[${itemIndex}][product][]" class="form-control select2-search" style="width: 100%;" required>
                            ${optionsStr}
                        </select>
                        <button type="button" onclick="removeRowAndCheck(this)" style="border:none; background:#fee2e2; color:#ef4444; width:38px; height:38px; border-radius:6px; cursor:pointer; flex-shrink: 0;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div style="text-align: right; margin-bottom: 20px;">
                    <button type="button" onclick="addProductToBox(this, ${itemIndex})" style="background:none; border:none; color:var(--accent-start); font-size:0.85rem; cursor:pointer; font-weight:600;">
                        <i class="fas fa-plus-circle"></i> เพิ่มสินค้าในรายการนี้
                    </button>
                </div>

                <div class="grid-2">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-size:0.85rem;">ประเภทงาน</label>
                        <select name="items[${itemIndex}][job_type]" class="form-control job-type-select" onchange="toggleJobOtherDynamic(this)">
                            ${jobOptionsHtml}
                        </select>
                        <input type="text" name="items[${itemIndex}][job_other]" class="form-control mt-2 job-other-input" style="display:none;" placeholder="ระบุประเภทอื่นๆ...">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size:0.85rem;">อาการ / ปัญหาที่พบ <span style="color:var(--danger-text)">*</span></label>
                        <textarea name="items[${itemIndex}][issue]" class="form-control" rows="2" required placeholder="ระบุอาการเสีย..." style="min-height: 80px;"></textarea>
                    </div>
                </div>
            </div>
        `;

        const newBox = $(html).appendTo('#service-items-container');
        
        // Init Select2 ให้ Box ใหม่
        newBox.find('.select2-search').select2({ width: '100%' });
        
        // 🔥 สั่งเช็คซ้ำทันทีเพื่อให้ Box ใหม่รู้สถานะ
        updateGlobalOptions();

        itemIndex++;
    }

    // 2. ฟังก์ชันเพิ่มช่องสินค้าในกล่องเดิม
    function addProductToBox(btn, boxIdx) {
        const container = $(btn).closest('.service-item-box').find('.product-list-container');

        const productHtml = `
            <div class="product-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center; opacity: 0; transform: translateY(-5px); transition: all 0.3s;">
                <select name="items[${boxIdx}][product][]" class="form-control select2-search" style="width: 100%;" required>
                    ${optionsStr}
                </select>
                <button type="button" onclick="removeRowAndCheck(this)" style="border:none; background:#fee2e2; color:#ef4444; width:38px; height:38px; border-radius:6px; cursor:pointer; flex-shrink: 0;">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

        const newRow = $(productHtml).appendTo(container);
        
        // Init Select2
        newRow.find('.select2-search').select2({ width: '100%' });

        // Animation
        setTimeout(() => { newRow.css({ opacity: 1, transform: 'translateY(0)' }); }, 10);

        // 🔥 สั่งเช็คซ้ำทันที
        updateGlobalOptions();
    }

    // ฟังก์ชันลบ Box ใหญ่
    function removeServiceItem(btn) {
        $(btn).closest('.service-item-box').fadeOut(200, function () {
            $(this).remove();
            updateItemCounters();
            updateGlobalOptions(); // คืนค่าสินค้ากลับสู่ระบบ
        });
    }

    // ฟังก์ชันลบแถวสินค้า (Row)
    function removeRowAndCheck(btn) {
        $(btn).closest('.product-row').remove();
        updateGlobalOptions(); // คืนค่าสินค้ากลับสู่ระบบทันที
    }

    // ฟังก์ชันอัปเดตตัวนับ
    function updateItemCounters() {
        $('#service-items-container .service-item-box').each(function (index) {
            $(this).find('.item-counter').text('รายการที่ ' + (index + 1));
        });
    }

    // Toggle ช่องกรอกประเภทงานอื่นๆ
    function toggleJobOtherDynamic(selectObj) {
        const box = $(selectObj).closest('.form-group');
        const input = box.find('.job-other-input');
        if (selectObj.value === 'other') { input.slideDown(200).attr('required', true); }
        else { input.slideUp(200).attr('required', false).val(''); }
    }

    function calcDeadline() {
        let d = document.getElementById('request_date');
        if (d && d.value) {
            let reqDate = new Date(d.value);
            reqDate.setHours(reqDate.getHours() + 48);
            let day = String(reqDate.getDate()).padStart(2, '0');
            let month = String(reqDate.getMonth() + 1).padStart(2, '0');
            let year = reqDate.getFullYear();
            let time = String(reqDate.getHours()).padStart(2, '0') + ':' + String(reqDate.getMinutes()).padStart(2, '0');
            let display = document.getElementById('deadline_display');
            if (display) { display.innerHTML = `<i class="fas fa-history"></i> ต้องปิดงานภายใน: <strong>${day}/${month}/${year} เวลา ${time} น.</strong>`; }
        }
    }

    // ==========================================
    // 🔥 CORE LOGIC: เช็คสินค้าซ้ำ (Global Check)
    // ==========================================

    // Event Listener: ทำงานเมื่อมีการเปลี่ยนแปลง หรือ กดเปิด Dropdown
    $(document).on('change select2:open', '.select2-search', function() {
        updateGlobalOptions();
    });

    function updateGlobalOptions() {
        var allSelectedValues = [];

        // 1. วิ่งเก็บค่าที่ถูกเลือกจาก "ทุก Box" ทั่วหน้าเว็บ
        $('.select2-search').each(function() {
            var val = $(this).val();
            if (val && val !== "") {
                allSelectedValues.push(val);
            }
        });

        // 2. วิ่งไปปิด (Disable) ตัวเลือกที่ซ้ำใน "ทุก Box"
        $('.select2-search').each(function() {
            var currentDropdown = $(this);
            var myCurrentValue = currentDropdown.val(); // ค่าที่ตัวเองเลือกอยู่ (ห้ามปิด)

            currentDropdown.find('option').each(function() {
                var optVal = $(this).val();

                // ถ้าค่านี้นี้ถูกเลือกไปแล้ว (ใน Box ไหนก็ได้) AND ไม่ใช่ค่าของตัวเอง
                if (optVal && allSelectedValues.includes(optVal) && optVal !== myCurrentValue) {
                    $(this).prop('disabled', true); // ❌ ปิดการใช้งาน
                } else {
                    $(this).prop('disabled', false); // ✅ เปิดให้เลือกได้
                }
            });
            
            // Re-render Select2 (เผื่อบางเวอร์ชันไม่อัปเดต UI เอง)
            // if (currentDropdown.hasClass('select2-hidden-accessible')) { /* currentDropdown.select2(); */ }
        });
    }

    // ---- Contact Row Logic (ส่วนเดิมของคุณ) ----
    const channelConfigs = <?php echo json_encode($contact_channels_list); ?>;

    function addContactRow(initialVal = '', initialExt = '', initialChannel = '') {
        const rowId = 'row_' + Math.floor(Math.random() * 1000000); 
        
        let optionsHtml = channelConfigs.map(c => `
            <option value="${c.channel_name}" 
                data-type="${c.channel_type}" 
                data-placeholder="${c.placeholder_text}"
                data-has-ext="${c.has_ext}" 
                data-is-tel="${c.is_tel}"
                ${initialChannel === c.channel_name ? 'selected' : ''}>
            ${c.channel_name}
            </option>
        `).join('');

        const rowHtml = `
            <div class="contact-row" id="${rowId}" style="display: flex; gap: 8px; margin-bottom: 10px; align-items: center; background: #f8fafc; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="flex: 1;">
                    <select class="form-control sel-channel" onchange="updateRowLogic('${rowId}')" required>
                        <option value="">-- ช่องทาง --</option>
                        ${optionsHtml}
                    </select>
                </div>
                <div style="flex: 2; display: flex; gap: 5px; align-items: center;">
                    <input type="text" class="form-control inp-detail" placeholder="ระบุข้อมูล..." value="${initialVal}" required style="flex: 1;">
                    <div class="ext-box" style="display: none; width: 100px; position: relative;">
                        <span style="position: absolute; left: -5px; top: 10px; font-size: 0.7rem; font-weight: bold; color: #64748b;"></span>
                        <input type="text" class="form-control inp-ext" placeholder="เลขต่อ" value="${initialExt}" style="text-align: center; padding-left: 20px;">
                    </div>
                </div>
                <button type="button" onclick="removeContactRow(this)" style="background: #fee2e2; color: #ef4444; border: none; width: 35px; height: 35px; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        $('#contact_list_container').append(rowHtml);
        updateRowLogic(rowId);
    }

    function removeContactRow(btn) {
        $(btn).closest('.contact-row').remove();
    }

    function updateRowLogic(rowId) {
        const row = $('#' + rowId); 
        const sel = row.find('.sel-channel')[0];
        if (!sel || sel.selectedIndex === -1) return;

        const opt = sel.options[sel.selectedIndex];
        const inp = row.find('.inp-detail');
        const extBox = row.find('.ext-box');

        if (sel.value !== "") {
            inp.attr('placeholder', opt.getAttribute('data-placeholder'));
            
            if (opt.getAttribute('data-is-tel') === '1') {
                inp.attr('type', 'tel').attr('maxlength', '10').attr('oninput', "this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)");
            } else {
                inp.attr('type', 'text').removeAttr('maxlength').removeAttr('oninput');
            }
            
            opt.getAttribute('data-has-ext') === '1' ? extBox.show() : extBox.hide();
        }
    }

    // Submit Logic
    $('#serviceForm').on('submit', function() {
        let contacts = [];
        $('.contact-row').each(function() {
            if($(this).find('.sel-channel').val()) {
                contacts.push({
                    channel: $(this).find('.sel-channel').val(),
                    detail: $(this).find('.inp-detail').val(),
                    ext: $(this).find('.inp-ext').val()
                });
            }
        });
        $('#contact_json').val(JSON.stringify(contacts));
    });
</script>
</body>

</html>