<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'db_connect.php';

// ==========================================================================
//  PART 1: DATA PREPARATION (LOGIC) - UPDATED FOR MANUAL MODE
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
        
        // ถ้า URL ไม่มี ID ให้ใช้ ID จากฐานข้อมูล
        if (empty($get_site_id)) {
            $get_site_id = $row_edit['site_id'];
        }

        // พยายาม decode JSON จาก field project_item_name
        $json_try = json_decode($row_edit['project_item_name'] ?? '[]', true);

        if (is_array($json_try) && !empty($json_try)) {
            if (isset($json_try[0]['product'])) {
                // โครงสร้างใหม่ (JSON มี key ครบ) -> ใช้ได้เลย
                $val_items_data = $json_try;
            } else {
                // แปลงข้อมูลเก่า (Legacy) ให้เข้าโครงสร้างใหม่
                foreach ($json_try as $index => $item_name) {
                    $val_items_data[] = [
                        'product' => $item_name,
                        'job_type' => ($index == 0) ? ($row_edit['job_type'] ?? '') : '',
                        'job_other' => ($index == 0) ? ($row_edit['job_type_other'] ?? '') : '',
                        'issue' => ($index == 0) ? ($row_edit['issue_description'] ?? '') : '',
                        // ดึงค่าเก่ามาใส่ในตัวแรก (ถ้ามี)
                        'initial_advice' => ($index == 0) ? ($row_edit['initial_advice'] ?? '') : '',
                        'assessment' => ($index == 0) ? ($row_edit['assessment'] ?? '') : ''
                    ];
                }
            }
        }
    }
}

// ถ้าไม่มีข้อมูลเลย ให้สร้าง array ว่างๆ ไว้ 1 อัน (เพื่อให้แสดงกล่องแรกเสมอ)
if (empty($val_items_data)) {
    $val_items_data[] = [
        'product' => '',
        'job_type' => '',
        'job_other' => '',
        'issue' => '',
        'initial_advice' => '',
        'assessment' => ''
    ];
}

// 2. เตรียมตัวแปรอื่นๆ (General Info)
$val_remark = $row_edit['remark'] ?? '';
$val_request_date = isset($row_edit['request_date']) ? date('Y-m-d H:i', strtotime($row_edit['request_date'])) : date('Y-m-d H:i');
$val_receiver = $row_edit['receiver_by'] ?? '';
$val_recorder = $row_edit['updated_by'] ?? ($_SESSION['fullname'] ?? '');
$val_reporter = $row_edit['reporter_name'] ?? '';
$val_contact_json = $row_edit['contact_detail'] ?? '[]'; // JSON string
$val_urgency = $row_edit['urgency'] ?? 'normal';

// 3. เตรียมข้อมูลโครงการ (Project Info) - รองรับทั้ง Search และ Manual
$site_code_show = "";
$project_name_show = "";
$customer_name_show = ""; 
$province_show = "";      
$contract_info = ['start' => '', 'end' => '', 'budget' => '', 'no' => ''];
$is_expired = false;

// กรณี A: ดึงจากฐานข้อมูลโครงการ (Site ID > 0)
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
        // ดึงแบบแยก
        $customer_name_show = $row_proj['customer_name'] ?? '';
        $province_show      = $row_proj['province'] ?? '';

        $contract_info['no'] = $row_proj['contract_number'] ?? '';
        $contract_info['budget'] = !empty($row_proj['project_budget']) ? number_format($row_proj['project_budget'], 2) : '';

        if (!empty($row_proj['contract_start_date']))
            $contract_info['start'] = date('d/m/Y', strtotime($row_proj['contract_start_date']));

        if (!empty($row_proj['contract_end_date'])) {
            $contract_info['end'] = date('d/m/Y', strtotime($row_proj['contract_end_date']));
            if ($row_proj['contract_end_date'] < date('Y-m-d')) $is_expired = true;
        }
    }
} 
// กรณี B: ดึงจากข้อมูลที่กรอกเอง (Manual) เมื่อแก้ไข (Edit Mode)
else if ($edit_id > 0 && !empty($row_edit)) {
    // ดึงค่าจากคอลัมน์ manual_... ที่บันทึกไว้ใน service_requests
    $site_code_show    = $row_edit['manual_site_code'] ?? '';
    $project_name_show = $row_edit['manual_project_name'] ?? '';
    
    // ดึงจาก column ใหม่
    $customer_name_show = $row_edit['manual_customer_name'] ?? '';
    $province_show      = $row_edit['manual_province'] ?? '';
    
    $contract_info['no']     = $row_edit['manual_contract_no'] ?? '';
    $contract_info['budget'] = $row_edit['manual_budget'] ?? '';

    // แปลงวันที่จาก DB (Y-m-d) เป็น d/m/Y
    if (!empty($row_edit['manual_start_date']))
        $contract_info['start'] = date('d/m/Y', strtotime($row_edit['manual_start_date']));

    if (!empty($row_edit['manual_end_date'])) {
        $contract_info['end'] = date('d/m/Y', strtotime($row_edit['manual_end_date']));
        if ($row_edit['manual_end_date'] < date('Y-m-d')) $is_expired = true;
    }
}

// โหลด List โครงการทั้งหมดสำหรับ Dropdown ค้นหา
$all_projects = [];
$sql_all = "SELECT site_id, project_name FROM project_contracts ORDER BY site_id ASC";
$res_all = $conn->query($sql_all);
while ($row = $res_all->fetch_assoc()) {
    $all_projects[] = $row;
}

// 4. ข้อมูล Fake Items (สำหรับ Dropdown สินค้า)
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

// 5. ดึงข้อมูลประเภทงานจากฐานข้อมูล (Dynamic Job Types)
$job_types_list = [];
$res_jt = $conn->query("SELECT * FROM job_types ORDER BY id ASC");
if ($res_jt && $res_jt->num_rows > 0) {
    while ($jt = $res_jt->fetch_assoc()) {
        $job_types_list[] = $jt;
    }
}

// 6. ดึงช่องทางติดต่อ (Contact Channels)
$contact_channels_list = [];
$res_cc = $conn->query("SELECT * FROM contact_channels ORDER BY id ASC");
if ($res_cc) {
    while ($cc = $res_cc->fetch_assoc()) {
        $contact_channels_list[] = $cc;
    }
}

// 7. ดึงรายชื่อจังหวัด (Provinces)
$provinces_list = [];
$res_prov = $conn->query("SELECT province_name FROM provinces ORDER BY province_name ASC");
if ($res_prov && $res_prov->num_rows > 0) {
    while ($prov = $res_prov->fetch_assoc()) {
        $provinces_list[] = $prov['province_name'];
    }
}

// 8. ดึงรายชื่อผู้ใช้งาน (Users)
$users_list = [];
$res_users = $conn->query("SELECT fullname FROM users ORDER BY fullname ASC");
if ($res_users && $res_users->num_rows > 0) {
    while ($u = $res_users->fetch_assoc()) {
        $users_list[] = $u['fullname'];
    }
}
// ==========================================================================
//  PART 2: FORM SUBMISSION HANDLING
// ==========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_service') {
    $site_id = $_POST['site_id'] ?? 0;
    $request_date = $_POST['request_date'] ?? date('Y-m-d H:i');
    $expected_finish = date('Y-m-d H:i:s', strtotime($request_date . ' +48 hours'));

    // --- 1. รับค่า Manual Inputs (แยกลูกค้า/จังหวัด) ---
    $man_code     = trim($_POST['manual_site_code'] ?? '');
    $man_contract = trim($_POST['manual_contract_no'] ?? '');
    $man_budget   = trim($_POST['manual_budget'] ?? '');
    $man_name     = trim($_POST['manual_project_name'] ?? '');
    $man_cust_name = trim($_POST['manual_customer_name'] ?? ''); 
    $man_province  = trim($_POST['manual_province'] ?? '');
    
    // แปลงวันที่
    function convertDateToDB($dateStr) {
        if (empty($dateStr) || $dateStr == '-') return null;
        $d = DateTime::createFromFormat('d/m/Y', $dateStr);
        return $d ? $d->format('Y-m-d') : null;
    }
    $man_start = convertDateToDB($_POST['manual_start_date'] ?? '');
    $man_end   = convertDateToDB($_POST['manual_end_date'] ?? '');

    // --- 2. จัดการ Items (เหมือนเดิม) ---
    $items_data_to_save = [];
    $issue_summary = []; $advice_summary = []; $assess_summary = []; $collected_job_types = [];

    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $index => $itm) {
            $product_input = $itm['product'] ?? [];
            $final_products = [];
            if (is_array($product_input)) {
                $final_products = array_values(array_filter($product_input, function($v) { return !empty($v); }));
            } else if (!empty($product_input)) {
                $final_products = [$product_input];
            }

            if (!empty($final_products) && !empty($itm['issue'])) {
                $this_advice = trim($itm['initial_advice'] ?? '');
                $this_assess = trim($itm['assessment'] ?? '');
                
                // --- จัดการไฟล์แนบ (Attached Files) สำหรับแต่ละรายการ ---
                $uploaded_files = [];
                if (isset($_FILES['items']['name'][$index]['attached_files']) && is_array($_FILES['items']['name'][$index]['attached_files'])) {
                    $file_count = count($_FILES['items']['name'][$index]['attached_files']);
                    $upload_dir = 'uploads/service_requests/';
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    for ($i = 0; $i < $file_count; $i++) {
                        $tmp_name = $_FILES['items']['tmp_name'][$index]['attached_files'][$i];
                        $orig_name = $_FILES['items']['name'][$index]['attached_files'][$i];
                        $error = $_FILES['items']['error'][$index]['attached_files'][$i];

                        if ($error === UPLOAD_ERR_OK && !empty($tmp_name)) {
                            // Gen ชื่อไฟล์ใหม่เพื่อป้องกันชื่อซ้ำ/มีปัญหา
                            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                            $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
                            
                            if (in_array($ext, $allowed_exts)) {
                                $new_filename = 'item_' . uniqid() . '_' . time() . '.' . $ext;
                                $dest_path = $upload_dir . $new_filename;
                                
                                if (move_uploaded_file($tmp_name, $dest_path)) {
                                    $uploaded_files[] = $new_filename;
                                }
                            }
                        }
                    }
                }

                // --- อ่านไฟล์เดิม (ถ้ามี) ---
                $existing_files = [];
                if (!empty($itm['existing_files'])) {
                    $decoded = json_decode(html_entity_decode($itm['existing_files']), true);
                    if (is_array($decoded)) {
                        $existing_files = $decoded;
                    }
                }
                
                // รวมไฟล์เดิมกับไฟล์ใหม่
                $all_files = array_merge($existing_files, $uploaded_files);

                $items_data_to_save[] = [
                    'product' => $final_products,
                    'job_type' => $itm['job_type'] ?? '',
                    'job_other' => trim($itm['job_other'] ?? ''),
                    'issue' => trim($itm['issue']),
                    'initial_advice' => $this_advice,
                    'assessment' => $this_assess,
                    'attached_files' => $all_files // <-- เก็บไฟล์รวมกันลง JSON
                ];

                $prod_names = implode(", ", $final_products);
                $issue_summary[] = ($index + 1) . ". [" . $prod_names . "] : " . trim($itm['issue']);
                if (!empty($this_advice)) $advice_summary[] = "(" . $prod_names . "): " . $this_advice;
                if (!empty($this_assess)) $assess_summary[] = "(" . $prod_names . "): " . $this_assess;
                if (!empty($itm['job_type'])) $collected_job_types[] = $itm['job_type'];
            }
        }
    }

    $item_name_json = !empty($items_data_to_save) ? json_encode($items_data_to_save, JSON_UNESCAPED_UNICODE) : "[]";
    $issue_final = !empty($issue_summary) ? implode("\n", $issue_summary) : "-";
    $initial_advice_final = !empty($advice_summary) ? implode("\n", $advice_summary) : ""; 
    $assessment_final = !empty($assess_summary) ? implode("\n", $assess_summary) : "";
    
    $unique_types = array_unique($collected_job_types);
    $job_type_final = !empty($unique_types) ? implode(', ', $unique_types) : 'other';
    $job_other_final = ($job_type_final == 'other' && isset($items_data_to_save[0]['job_other'])) ? $items_data_to_save[0]['job_other'] : '';
    
    $remark = trim($_POST['remark'] ?? '');
    $user_updated = $_SESSION['fullname'] ?? 'System';
    $receiver_by = $_POST['receiver_by'];
    $reporter_name = trim($_POST['reporter_name']);
    $contact_json = $_POST['contact_json'] ?? '[]';
    $urgency = $_POST['urgency'];
    $status_to_save = 'pending';

    // เช็คข้อมูลช่องทางการติดต่อต้องมีอย่างน้อย 1 รายการ
    $has_contact = (!empty($contact_json) && $contact_json !== '[]');

    // เงื่อนไข: ถ้า Manual (ID=0) ต้องระบุชื่อลูกค้า (ชื่อโครงการไม่บังคับ)
    $is_valid = (($site_id > 0) || ($site_id == 0 && !empty($man_cust_name))) && $has_contact;

    if ($is_valid) {
        $req_id_update = isset($_POST['req_id_for_update']) ? intval($_POST['req_id_for_update']) : 0;

        if ($req_id_update > 0) {
            // ================= UPDATE =================
            $sql = "UPDATE service_requests SET 
                    site_id=?, request_date=?, project_item_name=?, issue_description=?, assessment=?, remark=?, 
                    updated_by=?, expected_finish_date=?, 
                    receiver_by=?, reporter_name=?, contact_detail=?, 
                    job_type=?, job_type_other=?, urgency=?, initial_advice=?,
                    manual_site_code=?, manual_contract_no=?, manual_budget=?, manual_project_name=?, manual_customer_name=?, manual_province=?, manual_start_date=?, manual_end_date=?
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            
            // 🔴 แก้ไขตรงนี้: สตริงต้องมี 24 ตัว (i=1, s=22, i=1)
            // issssssssssssssssssssssi
            $stmt->bind_param("issssssssssssssssssssssi",
                $site_id, $request_date, $item_name_json, $issue_final, $assessment_final, $remark,
                $user_updated, $expected_finish,
                $receiver_by, $reporter_name, $contact_json,
                $job_type_final, $job_other_final, $urgency, $initial_advice_final,
                $man_code, $man_contract, $man_budget, $man_name, $man_cust_name, $man_province, $man_start, $man_end,
                $req_id_update
            );
            $msg_title = "อัปเดตข้อมูลเรียบร้อย";
        } else {
            // ================= INSERT =================
            $sql = "INSERT INTO service_requests (
                        site_id, request_date, project_item_name, issue_description, assessment, remark, 
                        updated_by, expected_finish_date, status,
                        receiver_by, reporter_name, contact_detail, 
                        job_type, job_type_other, urgency, initial_advice,
                        manual_site_code, manual_contract_no, manual_budget, manual_project_name, manual_customer_name, manual_province, manual_start_date, manual_end_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            // 🔴 แก้ไขตรงนี้: สตริงต้องมี 23 ตัว (i=1, s=22) ไม่มี WHERE ID
            // issssssssssssssssssssss
            $stmt->bind_param("isssssssssssssssssssssss", 
                $site_id,               // 1 (i)
                $request_date,          // 2 (s)
                $item_name_json,        // 3 (s)
                $issue_final,           // 4 (s)
                $assessment_final,      // 5 (s)
                $remark,                // 6 (s)
                $user_updated,          // 7 (s)
                $expected_finish,       // 8 (s)
                $status_to_save,        // 9 (s) - คือ 'pending'
                $receiver_by,           // 10 (s)
                $reporter_name,         // 11 (s)
                $contact_json,          // 12 (s)
                $job_type_final,        // 13 (s)
                $job_other_final,       // 14 (s)
                $urgency,               // 15 (s)
                $initial_advice_final,  // 16 (s)
                $man_code,              // 17 (s)
                $man_contract,          // 18 (s)
                $man_budget,            // 19 (s)
                $man_name,              // 20 (s)
                $man_cust_name,         // 21 (s)
                $man_province,          // 22 (s)
                $man_start,             // 23 (s)
                $man_end                // 24 (s)
            );
            $msg_title = "เปิดใบงานเรียบร้อย";
        }

        if ($stmt->execute()) {
            $alert_script = "Swal.fire({icon:'success', title:'$msg_title', text:'กำหนดเสร็จภายใน: $expected_finish', showConfirmButton:false, timer:2500}).then(()=>{ window.location.href='service_dashboard.php'; });";
        } else {
            $alert_script = "Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', text:'" . $conn->error . "'});";
        }
    } else {
        if (!$has_contact) {
            $alert_script = "Swal.fire({icon:'warning', title:'ข้อมูลไม่ครบ', text:'กรุณาระบุช่องทางติดต่ออย่างน้อย 1 รายการ'});";
        } else {
            $alert_script = "Swal.fire({icon:'warning', title:'ข้อมูลไม่ครบ', text:'กรุณาระบุชื่อลูกค้าให้ครบถ้วน'});";
        }
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

            <form method="POST" id="serviceForm" enctype="multipart/form-data">
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

                        <div  class="form-group" style="margin-bottom: 20px; ; background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px dashed #cbd5e1;">
                            <label class="form-label" style="font-size:1rem; color:var(--primary); margin-bottom:10px;">ระบุข้อมูลโครงการ</label>
                            <div style="display:flex; gap:30px;">
                                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                    <input type="radio" name="project_mode" value="search" 
                                        <?php echo ($get_site_id > 0) ? 'checked' : ''; ?> 
                                        onclick="toggleProjectMode('search')"> 
                                    <span style="font-weight:600; color:#334155; ;">🔍 ค้นหาจากฐานข้อมูล</span>
                                </label>
                                
                                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                    <input type="radio" name="project_mode" value="manual" 
                                        <?php echo ($get_site_id == 0) ? 'checked' : ''; ?> 
                                        onclick="toggleProjectMode('manual')"> 
                                    <span style="font-weight:600; color:#334155;">✍️ กรอกข้อมูลเอง (Manual)</span>
                                </label>
                            </div>
                        </div>

                        <div id="search-section" style="margin-bottom: 25px; display: <?php echo ($get_site_id > 0) ? 'block' : 'none'; ?>;">
                            <label class="form-label" style="font-size:0.9rem;">ค้นหาโครงการ</label>
                            <select name="site_id_search" id="site_id_search" class="form-control select2-search" style="width: 100%;"
                                    onchange="if(this.value) window.location.href='ServiceRequest.php?id='+this.value">
                                <option value="">-- พิมพ์ชื่อโครงการ หรือ Site ID เพื่อดึงข้อมูล --</option>
                                <?php foreach ($all_projects as $p): ?>
                                    <option value="<?php echo $p['site_id']; ?>" <?php echo ($get_site_id == $p['site_id']) ? 'selected' : ''; ?>>
                                        <?php echo $p['site_id'] . " : " . htmlspecialchars($p['project_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($get_site_id > 0): ?>
                                <div style="margin-top:5px; text-align:right;">
                                    <a href="ServiceRequest.php" style="font-size:0.85rem; color:#ef4444; text-decoration:none;">
                                        <i class="fas fa-times-circle"></i> ล้างค่า / เลือกใหม่
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="project-info-card" id="project-form-card"
                            style="position:relative; transition: all 0.3s; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; background: #fff;">
                            
                            <input type="hidden" name="site_id" id="real_site_id" value="<?php echo $get_site_id; ?>">

                            <div class="grid-3">
                                <div class="form-group">
                                    <label class="form-label">เลขหน้างาน</label>
                                    <input type="text" name="manual_site_code" id="inp_site_code" 
                                        class="form-control <?php echo ($get_site_id > 0) ? 'readonly-field' : ''; ?>" 
                                        value="<?php echo $site_code_show; ?>" 
                                        <?php echo ($get_site_id > 0) ? 'readonly' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">เลขที่สัญญา</label>
                                    <input type="text" name="manual_contract_no" id="inp_contract_no"
                                        class="form-control <?php echo ($get_site_id > 0) ? 'readonly-field' : ''; ?>" 
                                        value="<?php echo $contract_info['no']; ?>"
                                        <?php echo ($get_site_id > 0) ? 'readonly' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">งบประมาณ</label>
                                    <input type="text" name="manual_budget" id="inp_budget"
                                        class="form-control <?php echo ($get_site_id > 0) ? 'readonly-field' : ''; ?>" 
                                        value="<?php echo $contract_info['budget']; ?>"
                                        <?php echo ($get_site_id > 0) ? 'readonly' : ''; ?>>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">ชื่อโครงการ</label>
                                <input type="text" name="manual_project_name" id="inp_project_name"
                                    class="form-control <?php echo ($get_site_id > 0) ? 'readonly-field' : ''; ?>" 
                                    value="<?php echo $project_name_show; ?>"
                                    <?php echo ($get_site_id > 0) ? 'readonly' : ''; ?>>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">ชื่อลูกค้า / โรงเรียน <span style="color:red; display:<?php echo ($get_site_id == 0) ? 'inline' : 'none'; ?>;" id="req_cust_name">*</span></label>
                                    <input type="text" name="manual_customer_name" id="inp_customer_name" required
                                        class="form-control <?php echo ($get_site_id > 0) ? 'readonly-field' : ''; ?>" 
                                        value="<?php echo $customer_name_show; ?>"
                                        <?php echo ($get_site_id > 0) ? 'readonly' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">จังหวัด</label>
                                    <?php if ($get_site_id > 0): ?>
                                        <input type="text" name="manual_province" id="inp_province"
                                            class="form-control readonly-field" 
                                            value="<?php echo htmlspecialchars($province_show); ?>" readonly>
                                    <?php else: ?>
                                        <select name="manual_province" id="inp_province" class="form-control select2-search" style="width: 100%; cursor: pointer;">
                                            <option value="">-- พิมพ์ค้นหา หรือ เลือกจังหวัด --</option>
                                            <?php foreach ($provinces_list as $prov): ?>
                                                <option value="<?php echo htmlspecialchars($prov); ?>" <?php echo ($province_show == $prov) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($prov); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid-2" style="margin-bottom:0;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label">วันเริ่มสัญญา</label>
                                    <input type="text" name="manual_start_date" id="inp_start_date"
                                        class="form-control <?php echo ($get_site_id > 0) ? 'readonly-field' : 'date-picker'; ?>" 
                                        value="<?php echo ($contract_info['start'] == '-') ? '' : $contract_info['start']; ?>"
                                        <?php echo ($get_site_id > 0) ? 'readonly' : ''; ?>>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label">วันหมดสัญญา</label>
                                    <input type="text" name="manual_end_date" id="inp_end_date"
                                        class="form-control <?php echo ($get_site_id > 0) ? 'readonly-field' : 'date-picker'; ?>" 
                                        value="<?php echo ($contract_info['end'] == '-') ? '' : $contract_info['end']; ?>"
                                        style="color:<?php echo $is_expired ? '#dc2626' : 'inherit'; ?>;"
                                        <?php echo ($get_site_id > 0) ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                        </div>

                        <div class="section-title"><i class="fas fa-info-circle"></i> ข้อมูลการแจ้ง (Request Info)</div>
                        <div class="grid-3">
                            <div class="form-group"><label class="form-label">วันที่แจ้งเรื่อง <span
                                        style="color:var(--danger-text)">*</span></label><input type="text"
                                    id="request_date" name="request_date" class="form-control date-picker"
                                    value="<?php echo $val_request_date; ?>" required onchange="calcDeadline()"></div>
                            <div class="form-group">
                                <label class="form-label">ผู้รับเรื่อง <span style="color:var(--danger-text)">*</span></label>
                                <select name="receiver_by" class="form-control select2-search" style="width: 100%;" required>
                                    <option value="">-- เลือกผู้รับเรื่อง --</option>
                                    <?php foreach ($users_list as $user_name): ?>
                                        <option value="<?php echo htmlspecialchars($user_name); ?>" <?php echo ($val_receiver == $user_name) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ผู้ลงข้อมูล <i class="fas fa-lock" style="font-size:0.7rem; color:#94a3b8;"></i></label>
                                <input type="text" name="recorder_name" class="form-control readonly-field"
                                    value="<?php echo htmlspecialchars($val_recorder); ?>" readonly>
                            </div>
                        </div>

                        <div class="section-title"><i class="fas fa-user-tag"></i> ผู้ติดต่อ (Contact Person)</div>
                        <div
                            style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start; background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">

                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">ชื่อผู้แจ้ง <span
                                        style="color:var(--danger-text)">*</span></label>
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

                        <input type="hidden" name="contact_json" id="contact_json" value="<?php echo htmlspecialchars($val_contact_json, ENT_QUOTES, 'UTF-8'); ?>">
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

                                // 🟢 ดึงค่าคำแนะนำและการประเมิน (ถ้ามี)
                                $current_advice = $item_data['initial_advice'] ?? '';
                                $current_assess = $item_data['assessment'] ?? '';

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
                                                
                                                <div style="flex: 1; position: relative;">
                                                    <select name="items[<?php echo $index; ?>][product][]"
                                                        class="form-control select2-search product-dropdown" style="width: 100%;" 
                                                        <?php echo ($get_site_id == 0) ? 'disabled style="display:none;"' : 'required'; ?>>
                                                        <option value="">-- เลือกรายการ --</option>
                                                        <?php foreach ($fake_items as $fake): ?>
                                                            <option value="<?php echo htmlspecialchars($fake); ?>" <?php echo ($fake == $p_name) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($fake); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <input type="text" name="items[<?php echo $index; ?>][product][]" 
                                                        class="form-control product-text-input" 
                                                        placeholder="พิมพ์ชื่อสินค้า / อุปกรณ์..." 
                                                        value="<?php echo htmlspecialchars($p_name); ?>"
                                                        <?php echo ($get_site_id > 0) ? 'disabled style="display:none;"' : 'required'; ?>>
                                                </div>

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
                                            <select name="items[<?php echo $index; ?>][job_type]" class="form-control job-type-select" onchange="toggleJobOtherDynamic(this)" required>
                                                <option value="">-- เลือกประเภทงาน --</option>
                                                <?php foreach ($job_types_list as $jt): ?>
                                                    <option value="<?php echo htmlspecialchars($jt['job_type_name']); ?>" <?php echo ($current_job_type == $jt['job_type_name']) ? 'selected' : ''; ?>>
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

                                    <div class="grid-2"
                                        style="margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size:0.85rem; color:#059669;"><i
                                                    class="fas fa-microscope"></i> คำแนะนำเบื้องต้น</label>
                                            <textarea name="items[<?php echo $index; ?>][initial_advice]"
                                                class="form-control" rows="1" placeholder="คำแนะนำ..."
                                                style="min-height: 40px; font-size:0.9rem;"><?php echo htmlspecialchars($current_advice); ?></textarea>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label" style="font-size:0.85rem; color:#d97706;"><i
                                                    class="fas fa-clipboard-check"></i> การประเมิน</label>
                                            <textarea name="items[<?php echo $index; ?>][assessment]" class="form-control"
                                                rows="1" placeholder="การประเมิน..."
                                                style="min-height: 40px; font-size:0.9rem;"><?php echo htmlspecialchars($current_assess); ?></textarea>
                                        </div>
                                    </div>
                                    
                                        <?php 
                                            $existing_files = isset($item_data['attached_files']) && is_array($item_data['attached_files']) ? $item_data['attached_files'] : [];
                                            $existing_files_json = htmlspecialchars(json_encode($existing_files, JSON_UNESCAPED_UNICODE));
                                        ?>
                                        <input type="hidden" name="items[<?php echo $index; ?>][existing_files]" value="<?php echo $existing_files_json; ?>">
                                        
                                        <?php if (!empty($existing_files)): ?>
                                            <div style="margin-bottom: 10px; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                                                <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 5px;"><i class="fas fa-history"></i> ไฟล์แนบเดิม:</div>
                                                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                                    <?php foreach ($existing_files as $efile): 
                                                        $ext = strtolower(pathinfo($efile, PATHINFO_EXTENSION));
                                                        $icon = 'fa-file';
                                                        $isImg = false;
                                                        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $icon = 'fa-file-image'; $isImg = true; }
                                                        elseif ($ext == 'pdf') $icon = 'fa-file-pdf';
                                                        elseif (in_array($ext, ['doc','docx'])) $icon = 'fa-file-word';
                                                        elseif (in_array($ext, ['xls','xlsx'])) $icon = 'fa-file-excel';
                                                    ?>
                                                        <a href="uploads/service_requests/<?php echo urlencode($efile); ?>" target="_blank" 
                                                           style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 12px; background: #e2e8f0; border: 1px solid #cbd5e1; color: #334155; font-size: 0.75rem; text-decoration: none;">
                                                            <i class="fas <?php echo $icon; ?>" style="<?php echo $isImg ? 'color:#0ea5e9;' : 'color:#64748b;'; ?>"></i>
                                                            <?php echo htmlspecialchars($efile); ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="items[<?php echo $index; ?>][attached_files][]" class="form-control" style="font-size: 0.85rem; padding: 5px;" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
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

                        <div style="text-align: center; margin-top: 50px; display:flex; justify-content:center; gap:20px;">
                            <a href="service_dashboard.php" class="btn-reset-icon"
                            style="width:auto; padding:0 35px; border-radius:50px; background:#fff; border:1px solid #cbd5e1;">
                                <i class="fas fa-times" style="margin-right:5px;"></i> ยกเลิก
                            </a>

                            <button type="submit" class="btn-create" style="padding:0 40px;">
                                <i class="fas fa-save"></i> บันทึกข้อมูล
                            </button>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
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
            <?php foreach ($job_types_list as $jt): ?>
                jobOptionsHtml += `<option value="<?php echo htmlspecialchars($jt['job_type_name']); ?>"><?php echo htmlspecialchars($jt['job_type_name']); ?></option>`;
            <?php endforeach; ?>
        <?php endif; ?>

        // สร้าง Option List สำหรับสินค้า
        let optionsStr = '<option value="">-- เลือกรายการ --</option>';
        fakeItemsList.forEach(item => {
            optionsStr += `<option value="${item}">${item}</option>`;
        });
        
        const channelConfigs = <?php echo json_encode($contact_channels_list); ?>;

        // 🔥 [ตัวแก้บั๊กขั้นเด็ดขาด] ส่งข้อมูล Contact โดยป้องกันอักขระพิเศษพัง
        const existingContactsData = <?php echo json_encode(json_decode($val_contact_json ?? '[]', true) ?: []); ?>;

    </script>
    <script src="js/ServiceRequest.js"></script>
</body>

</html>