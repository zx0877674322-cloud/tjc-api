<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตั้งค่า Timezone 
date_default_timezone_set('Asia/Bangkok');

// --- 1. AJAX Handler: สำหรับ Admin กดยืนยัน (ทำงานส่วนนี้ก่อน HTML) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'ajax_complete') {
    if (strtolower($_SESSION['role'] ?? '') !== 'admin' && !hasAction('btn_confirm_delete')) {
        echo json_encode(['status' => 'error', 'message' => 'No permission']);
        exit;
    }
    $id = intval($_POST['id']);
    $admin_name = $_SESSION['fullname'];
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE winspeed_deletion_requests SET status = 'completed', completed_by = ?, completed_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $admin_name, $now, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'admin_name' => $admin_name, 'date' => date('d/m/Y H:i', strtotime($now))]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'ajax_cancel') {
    // เช็คสิทธิ์ (ใช้สิทธิ์เดียวกับ confirm หรือจะสร้างสิทธิ์ใหม่ก็ได้)
    if (strtolower($_SESSION['role'] ?? '') !== 'admin' && !hasAction('btn_confirm_delete')) {
        echo json_encode(['status' => 'error', 'message' => 'No permission']);
        exit;
    }

    $id = intval($_POST['id']);
    $cancel_reason = trim($_POST['cancel_reason']); // รับค่าหมายเหตุ
    $admin_name = $_SESSION['fullname'];
    $now = date('Y-m-d H:i:s');

    // อัปเดตสถานะเป็น cancelled และบันทึกสาเหตุ
    $stmt = $conn->prepare("UPDATE winspeed_deletion_requests SET status = 'cancelled', cancel_reason = ?, completed_by = ?, completed_at = ? WHERE id = ?");
    $stmt->bind_param("sssi", $cancel_reason, $admin_name, $now, $id);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'admin_name' => $admin_name,
            'date' => date('d/m/Y H:i', strtotime($now)),
            'reason' => $cancel_reason
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// --- 2. ดึงข้อมูล User ผู้ใช้งานปัจจุบัน ---
$current_user_id = $_SESSION['user_id'] ?? 0;
$user_info = null;

if ($current_user_id > 0) {
    $stmt = $conn->prepare("SELECT u.fullname, c.company_name, c.company_shortname 
                            FROM users u 
                            LEFT JOIN companies c ON u.company_id = c.id 
                            WHERE u.id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $user_info = $res->fetch_assoc();
    }
    $stmt->close();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_request') {

    $id = intval($_POST['request_id']);
    $doc_type = $_POST['doc_type'];
    $doc_number = $_POST['doc_number'];
    $reason = trim($_POST['reason']);
    $target_companies = $_POST['target_winspeed_company'] ?? '';

    // เก็บชื่อคนแก้ และ เวลาปัจจุบัน
    $updater_name = $_SESSION['fullname'] ?? 'Unknown';
    $update_time = date('Y-m-d H:i:s');

    if ($doc_type === 'Other' && !empty($_POST['doc_type_remark'])) {
        $doc_type = "อื่นๆ " . trim($_POST['doc_type_remark']);
    }

    // [แก้ไข SQL] เพิ่ม updated_by และ updated_at
    $stmt = $conn->prepare("UPDATE winspeed_deletion_requests SET doc_type=?, doc_number=?, target_winspeed_company=?, reason=?, updated_by=?, updated_at=? WHERE id=?");
    $stmt->bind_param("ssssssi", $doc_type, $doc_number, $target_companies, $reason, $updater_name, $update_time, $id);

    if ($stmt->execute()) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() { 
                Swal.fire({
                    icon: 'success', 
                    title: 'แก้ไขข้อมูลเรียบร้อย', 
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => { window.location.href = 'WINSpeedDeleteRequest.php'; }); 
            });
        </script>";
    } else {
        echo "<script>Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด', text: '" . $conn->error . "'});</script>";
    }
    $stmt->close();
}
$requester_name = $user_info['fullname'] ?? $_SESSION['fullname'] ?? 'Unknown';
$requester_company = $user_info['company_name'] ?? 'ไม่ระบุสังกัด'; // ค่านี้คือค่าที่โชว์ใน Input

$current_user = $_SESSION['fullname'] ?? 'Unknown User';

// *** แก้ตรงนี้: ให้ใช้ค่าเดียวกับที่โชว์ใน Input ($requester_company) ไปบันทึก ***
$user_company_origin = $requester_company;
// ถ้าค่าว่าง ให้ใส่สำนักงานใหญ่
if (empty($user_company_origin) || $user_company_origin == 'ไม่ระบุสังกัด') {
    $user_company_origin = 'สำนักงานใหญ่';
}

$current_datetime = date('Y-m-d H:i:s');

// --- 3. บันทึกข้อมูลการแจ้งลบ (Insert Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_request') {

    $doc_type = $_POST['doc_type'];
    $doc_number = $_POST['doc_number'];
    $reason = trim($_POST['reason']);
    $target_companies = $_POST['target_winspeed_company'] ?? '';

    if ($doc_type === 'Other' && !empty($_POST['doc_type_remark'])) {
        $doc_type = "อื่นๆ " . trim($_POST['doc_type_remark']);
    }

    if (empty($doc_number) || empty($target_companies)) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() { 
                Swal.fire({icon: 'warning', title: 'กรุณากรอกข้อมูลให้ครบ', text: 'ต้องระบุเลขที่เอกสารและเลือกบริษัท'}); 
            });
        </script>";
    } else {
        $sql = "INSERT INTO winspeed_deletion_requests 
                (requester_name, requester_company, request_datetime, doc_type, doc_number, target_winspeed_company, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $current_user, $user_company_origin, $current_datetime, $doc_type, $doc_number, $target_companies, $reason);

        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() { 
                    Swal.fire({
                        icon: 'success', 
                        title: 'แจ้งลบเรียบร้อย', 
                        text: 'ข้อมูลถูกส่งไปยังผู้ดูแลระบบแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => { window.location.href = 'WINSpeedDeleteRequest.php'; }); 
                });
            </script>";
        } else {
            echo "<script>Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด', text: '" . $conn->error . "'});</script>";
        }
        $stmt->close();
    }
}

// --- 4. ดึงรายชื่อบริษัท (สำหรับ Loop Checkbox) ---
$companies_list = [];
$q_comp = $conn->query("SELECT id, company_name, company_shortname, logo_file FROM companies ORDER BY list_order ASC");
while ($row = $q_comp->fetch_assoc()) {
    $companies_list[] = $row;
}

// --- 5. ดึงประวัติการแจ้งลบ (History & Filter) ---
$where_clauses = [];
$params = [];
$types = "";

// 5.1 กรองวันที่
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $where_clauses[] = "w.request_datetime BETWEEN ? AND ?";
    $params[] = $_GET['start_date'] . " 00:00:00";
    $params[] = $_GET['end_date'] . " 23:59:59";
    $types .= "ss";
}

// 5.2 กรองชื่อผู้แจ้ง
if (!empty($_GET['filter_user'])) {
    $where_clauses[] = "w.requester_name = ?";
    $params[] = $_GET['filter_user'];
    $types .= "s";
}

// 5.3 ช่องค้นหา (Search Keyword)
if (!empty($_GET['keyword'])) {
    // แก้ไข: เพิ่มเงื่อนไขให้ครอบคลุมที่สุด
    $where_clauses[] = "(w.doc_number LIKE ? 
                        OR w.reason LIKE ? 
                        OR w.doc_type LIKE ? 
                        OR CONCAT(w.doc_type, ' ', w.doc_number) LIKE ?  
                        OR CONCAT(w.doc_type, w.doc_number) LIKE ?)"; // เพิ่มบรรทัดนี้ (แบบไม่เว้นวรรค)

    $keyword_param = "%" . $_GET['keyword'] . "%";

    $params[] = $keyword_param; // 1. เลขที่
    $params[] = $keyword_param; // 2. สาเหตุ
    $params[] = $keyword_param; // 3. ประเภท
    $params[] = $keyword_param; // 4. แบบมีเว้นวรรค (เช่น "AX 123")
    $params[] = $keyword_param; // 5. แบบติดกัน (เช่น "AX123")

    $types .= "sssss"; // แก้ไข: ต้องมี s 5 ตัว (เพราะมี ? 5 ตัว)
}
if (!empty($_GET['filter_status'])) {
    $where_clauses[] = "w.status = ?";
    $params[] = $_GET['filter_status'];
    $types .= "s";
}
// สร้าง SQL Query 
// *** แก้ไข: เพิ่ม COLLATE และตั้งชื่อเล่น AS req_comp_short ให้ชัดเจน ***
$sql_history = "SELECT w.*, c.company_shortname AS req_comp_short
                FROM winspeed_deletion_requests w
                LEFT JOIN companies c ON w.requester_company = c.company_name COLLATE utf8mb4_general_ci";

if (!empty($where_clauses)) {
    $sql_history .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_history .= " ORDER BY w.request_datetime DESC LIMIT 100";

// Prepare & Execute
$stmt_hist = $conn->prepare($sql_history);

if ($stmt_hist === false) {
    die("SQL Error: " . $conn->error);
}

if (!empty($params)) {
    $stmt_hist->bind_param($types, ...$params);
}
$stmt_hist->execute();
$history = $stmt_hist->get_result();

// ดึงรายชื่อผู้แจ้ง (สำหรับ Dropdown Filter)
$users_list_q = $conn->query("SELECT DISTINCT requester_name FROM winspeed_deletion_requests ORDER BY requester_name ASC");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งลบเอกสาร WINSpeed</title>

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/WINSpeedDeleteRequest.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">

            <button class="toggle-form-btn toggle-active" onclick="toggleForm()" id="btnToggle"
                style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);">
                <span><i class="fas fa-minus-circle"></i> ปิดแบบฟอร์ม</span>
                <i class="fas fa-chevron-down toggle-icon" style="transform: rotate(180deg);"></i>
            </button>

            <div id="requestFormContainer" style="display: block;">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-trash-alt"></i> แจ้งลบเอกสาร WINSpeed
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" id="form_action" value="submit_request">

                            <input type="hidden" name="request_id" id="request_id" value="">

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>ชื่อผู้แจ้ง</label>
                                    <input type="text" class="form-control"
                                        value="<?php echo htmlspecialchars($requester_name); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>สังกัดบริษัท (ผู้แจ้ง)</label>
                                    <input type="text" class="form-control"
                                        value="<?php echo htmlspecialchars($requester_company); ?>" readonly>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">วันเวลาที่แจ้ง</label>
                                    <input type="text" class="form-control"
                                        value="<?php echo date('d/m/Y H:i', strtotime($current_datetime)); ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">ประเภทเอกสาร <span style="color:red">*</span></label>
                                    <select name="doc_type" id="doc_type_select" class="form-control" required
                                        onchange="toggleRemarkField()">
                                        <option value="PO">PO (ใบสั่งซื้อ)</option>
                                        <option value="AX">AX (ใบขอซื้อ/ค่าใช้จ่าย)</option>
                                        <option value="Other">อื่นๆ</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" id="other_remark_field"
                                style="display:none; margin-top: -10px; margin-bottom: 20px;">
                                <label class="form-label">ระบุประเภทเอกสารเพิ่มเติม <span
                                        style="color:red">*</span></label>
                                <input type="text" name="doc_type_remark" id="doc_type_remark_input"
                                    class="form-control" placeholder="เช่น ใบเสนอราคา, ใบรับของ...">
                            </div>

                            <div class="form-group">
                                <label class="form-label">เลขที่เอกสารที่จะลบ <span style="color:red">*</span>
                                    (เฉพาะตัวเลขและขีด -)</label>
                                <input type="text" name="doc_number" class="form-control" placeholder="เช่น 2023-0001"
                                    required oninput="this.value = this.value.replace(/[^0-9-]/g, '')">
                            </div>

                            <div class="form-group">
                                <label class="form-label">บริษัทที่เปิดเอกสารใน WINSpeed <span
                                        style="color:red">*</span>
                                    (เลือก 1 บริษัท)</label>

                                <div class="company-grid">
                                    <?php foreach ($companies_list as $comp): ?>
                                        <?php
                                        // เช็คว่ามีไฟล์รูปไหม
                                        $img_path = "uploads/logos/" . $comp['logo_file'];
                                        $has_logo = (!empty($comp['logo_file']) && file_exists($img_path));
                                        ?>
                                        <label class="company-option">
                                            <input type="radio" name="target_winspeed_company"
                                                value="<?php echo $comp['company_name']; ?>" required>

                                            <div class="comp-logo-wrapper">
                                                <?php if ($has_logo): ?>
                                                    <img src="<?php echo $img_path; ?>" class="comp-logo-img" alt="Logo">
                                                <?php else: ?>
                                                    <div class="comp-no-logo"><i class="fas fa-building"></i></div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="comp-info">
                                                <?php if (!empty($comp['company_shortname'])): ?>
                                                    <div class="comp-code-badge">
                                                        <?php echo $comp['company_shortname']; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="comp-name-full">
                                                    <?php echo $comp['company_name']; ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">สาเหตุในการลบ <span style="color:red">*</span></label>
                                <textarea name="reason" class="form-control" rows="3" required
                                    placeholder="ระบุสาเหตุ เช่น เปิดผิดบริษัท, เอกสารซ้ำซ้อน..."></textarea>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" class="btn-submit" id="btn_submit">
                                    <i class="fas fa-paper-plane"></i> ส่งแจ้งลบ
                                </button>

                                <button type="button" class="btn-submit" id="btn_cancel"
                                    style="background: #64748b; display: none;" onclick="cancelEditMode()">
                                    <i class="fas fa-times"></i> ยกเลิกแก้ไข
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background: white; color: #1e293b; border-bottom: 2px solid #f1f5f9;">
                    <i class="fas fa-history" style="color: #64748b;"></i> ประวัติการแจ้งลบ (ล่าสุด)
                </div>

                <form method="GET" class="filter-bar">
                    <div class="filter-item" style="flex: 1; min-width: 200px;">
                        <label class="filter-label">คำค้นหา</label>
                        <input type="text" name="keyword" class="filter-input" placeholder="เลขที่เอกสาร / สาเหตุ..."
                            value="<?php echo htmlspecialchars($_GET['keyword'] ?? ''); ?>">
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">วันที่เริ่ม</label>
                        <input type="text" name="start_date" class="filter-input date-picker"
                            placeholder="เลือกวันที่..." value="<?php echo $_GET['start_date'] ?? ''; ?>">
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">ถึงวันที่</label>
                        <input type="text" name="end_date" class="filter-input date-picker" placeholder="เลือกวันที่..."
                            value="<?php echo $_GET['end_date'] ?? ''; ?>">
                    </div>
                    <div class="filter-item" style="min-width: 150px;">
                        <label class="filter-label">ผู้แจ้ง</label>
                        <select name="filter_user" class="filter-input" onchange="this.form.submit()">
                            <option value="">-- ทั้งหมด --</option>
                            <?php while ($u = $users_list_q->fetch_assoc()): ?>
                                <option value="<?php echo $u['requester_name']; ?>" <?php echo ($_GET['filter_user'] ?? '') == $u['requester_name'] ? 'selected' : ''; ?>>
                                    <?php echo $u['requester_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-item" style="min-width: 140px;">
                        <label class="filter-label">สถานะ</label>
                        <select name="filter_status" class="filter-input" style="width:100%;"
                            onchange="this.form.submit()">
                            <option value="">-- ทั้งหมด --</option>
                            <option value="pending" <?php echo ($_GET['filter_status'] ?? '') == 'pending' ? 'selected' : ''; ?>>
                                เเจ้งลบ
                            </option>
                            <option value="completed" <?php echo ($_GET['filter_status'] ?? '') == 'completed' ? 'selected' : ''; ?>>
                                เสร็จสิ้น
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                    <a href="WINSpeedDeleteRequest.php" class="btn-clear">
                        <i class="fas fa-undo"></i> ล้างค่า
                    </a>
                </form>

                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="min-width: 140px;">วันที่แจ้ง</th>

                                    <th style="min-width: 150px;">ผู้แจ้ง</th>

                                    <th style="min-width: 140px;">เอกสาร</th>

                                    <th>บริษัท (WINSpeed)</th>

                                    <th style="text-align:center;">สาเหตุ</th>

                                    <th style="text-align:center; min-width: 130px;">สถานะ</th>

                                    <th style="text-align:center; min-width: 120px;">ผู้ยืนยัน/ยกเลิก</th>

                                    <th style="text-align:center; min-width: 120px;">ผู้แก้ไขล่าสุด</th>
                                    <th style="text-align:center; min-width: 110px;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history->num_rows > 0): ?>
                                    <?php while ($row = $history->fetch_assoc()): ?>
                                        <tr id="row-<?php echo $row['id']; ?>">
                                            <td><?php echo date('d/m/Y H:i', strtotime($row['request_datetime'])); ?></td>

                                            <td>
                                                <strong><?php echo $row['requester_name']; ?></strong><br>
                                                <?php
                                                $show_comp = !empty($row['req_comp_short']) ? $row['req_comp_short'] : $row['requester_company'];
                                                if (trim($show_comp) === 'สำนักงานใหญ่')
                                                    $show_comp = '';
                                                ?>
                                                <?php if ($show_comp !== ''): ?>
                                                    <small
                                                        style="color:#64748b; background:#f1f5f9; padding:2px 6px; border-radius:4px;">
                                                        <i class="fas fa-building"></i> <?php echo $show_comp; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <span
                                                    style="font-weight:600; color:#ef4444;"><?php echo $row['doc_type']; ?></span>
                                                <?php echo $row['doc_number']; ?>
                                            </td>

                                            <td><?php echo $row['target_winspeed_company']; ?></td>

                                            <td style="text-align:center;">
                                                <button type="button" class="btn-view-reason"
                                                    onclick="showReason('<?php echo htmlspecialchars($row['reason'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>

                                            <td style="text-align:center;" class="status-cell">
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <span class="badge badge-pending"><i class="fas fa-clock"></i> แจ้งลบ</span>

                                                <?php elseif ($row['status'] == 'completed'): ?>
                                                    <span class="badge badge-completed"><i class="fas fa-check"></i>
                                                        เสร็จสิ้น</span>

                                                <?php else: /* กรณี cancelled */ ?>
                                                    <span class="badge"
                                                        style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;">
                                                        <i class="fas fa-times-circle"></i> ยกเลิก
                                                    </span>

                                                    <?php if (!empty($row['cancel_reason'])): ?>
                                                        <div style="margin-top: 5px;">
                                                            <button type="button"
                                                                onclick="showCancelReason('<?php echo htmlspecialchars($row['cancel_reason'], ENT_QUOTES); ?>')"
                                                                style="border: none; background: none; color: #ef4444; font-size: 0.75rem; cursor: pointer; text-decoration: underline; padding: 0;">
                                                                (ดูสาเหตุ)
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>

                                            <td style="text-align:center;" class="completed-cell">
                                                <?php
                                                // กรณี 1: ทำรายการสำเร็จ (Completed) -> สีเขียว
                                                if ($row['status'] == 'completed'):
                                                    ?>
                                                    <div style="font-size:12px; font-weight:600; color:#166534;">
                                                        <i class="fas fa-check-circle"></i>
                                                        <?php echo !empty($row['completed_by']) ? $row['completed_by'] : '-'; ?>
                                                    </div>
                                                    <div style="font-size:10px; color:#64748b;">
                                                        <?php echo !empty($row['completed_at']) ? date('d/m/Y H:i', strtotime($row['completed_at'])) : ''; ?>
                                                    </div>

                                                    <?php
                                                    // กรณี 2: ถูกยกเลิก (Cancelled) -> สีแดง (ต้องเพิ่มส่วนนี้!)
                                                elseif ($row['status'] == 'cancelled'):
                                                    ?>
                                                    <div style="font-size:12px; font-weight:600; color:#ef4444;">
                                                        <i class="fas fa-user-times"></i>
                                                        <?php echo !empty($row['completed_by']) ? $row['completed_by'] : 'Admin'; ?>
                                                    </div>
                                                    <div style="font-size:10px; color:#64748b;">
                                                        <?php echo !empty($row['completed_at']) ? date('d/m/Y H:i', strtotime($row['completed_at'])) : ''; ?>
                                                    </div>
                                                    <div
                                                        style="font-size:9px; background:#fef2f2; color:#b91c1c; display:inline-block; padding:1px 4px; border-radius:3px; margin-top:2px; border:1px solid #fca5a5;">
                                                        ผู้ยกเลิก
                                                    </div>

                                                    <?php
                                                    // กรณี 3: ยังไม่ดำเนินการ (Pending) -> ขีด
                                                else:
                                                    ?>
                                                    <span style="color:#cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td style="text-align:center;">
                                                <?php if (!empty($row['updated_by'])): ?>
                                                    <div style="font-size:12px; font-weight:600; color:#d97706;">
                                                        <?php echo $row['updated_by']; ?>
                                                    </div>
                                                    <div style="font-size:10px; color:#64748b;">
                                                        <?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color:#cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td style="text-align:center;" class="action-cell">
                                                <?php if ($row['status'] == 'pending'): ?>

                                                    <div style="display:flex; flex-direction:column; gap:5px; align-items:center;">

                                                        <?php // 1. ส่วนของ Admin: ปุ่มยืนยัน และ ปุ่มยกเลิก ?>
                                                        <?php if (hasAction('btn_confirm_delete')): ?>

                                                            <button type="button" id="btn_confirm_delete"
                                                                class="btn-confirm-ajax hasAction"
                                                                style="width: 100%; margin-bottom: 2px;"
                                                                onclick="confirmDelete(<?php echo $row['id']; ?>)">
                                                                ยืนยันการลบ
                                                            </button>

                                                            <button type="button" class="btn-cancel-ajax"
                                                                style="background: #ef4444; color: white; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; width: 100%; box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3); transition: all 0.2s ease;"
                                                                onmouseover="this.style.background='#dc2626'; this.style.transform='translateY(-1px)';"
                                                                onmouseout="this.style.background='#ef4444'; this.style.transform='translateY(0)';"
                                                                onclick="cancelRequest(<?php echo $row['id']; ?>)">
                                                                <i class="fas fa-times-circle"></i> ยกเลิก
                                                            </button>

                                                        <?php endif; ?>

                                                        <?php // 2. ปุ่มแก้ไข (ของเดิม) ?>
                                                        <button type="button" class="btn-edit-ajax"
                                                            onclick='populateEditForm(<?php echo json_encode($row); ?>)'>
                                                            <i class="fas fa-edit"></i>
                                                        </button>

                                                    </div>

                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" style="text-align:center; padding: 20px;">ไม่พบข้อมูล</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <script src="js/WINSpeedDeleteRequest.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
</body>

</html>