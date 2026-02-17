<?php

session_start();
// require_once 'auth.php'; // เปิดใช้งานเมื่อระบบพร้อม
require_once 'db_connect.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_expense') {
    // 1. ล้างลำโพง (Output Buffer) เพื่อให้ส่งแต่ JSON เท่านั้น
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // 2. รับค่า ID รายงาน
    $id = intval($_POST['report_id']);

    // 3. จัดการค่าน้ำมัน (บันทึกเป็น String เพื่อแยกช่องได้)
    $fuel_costs = $_POST['fuel_cost'] ?? [];
    $fuel_total_sum = 0;


    // กรองเอาเฉพาะค่าที่มีตัวเลข และรวมยอดสุทธิเพื่อบันทึกในช่อง total_expense
    if (is_array($fuel_costs)) {
        $fuel_costs = array_filter($fuel_costs, function ($v) {
            return $v !== '';
        });
        foreach ($fuel_costs as $c) {
            $fuel_total_sum += floatval($c);
        }
        $fuel_cost_save = implode(',', $fuel_costs); // 🟢 บันทึกเป็น "100,200,300"
    } else {
        $fuel_total_sum = floatval($fuel_costs);
        $fuel_cost_save = $fuel_costs;
    }

    // 4. รับค่าที่พักและค่าอื่นๆ
    $accom_cost = floatval($_POST['accommodation_cost'] ?? 0);
    $other_cost = floatval($_POST['other_cost'] ?? 0);
    $other_detail = isset($_POST['other_detail']) ? trim($_POST['other_detail']) : '';

    // คำนวณยอดรวมสุทธิ
    $total_expense = $fuel_total_sum + $accom_cost + $other_cost;

    // 5. ดึงข้อมูลไฟล์เก่ามาตรวจสอบเพื่อคงค่าไว้ถ้าไม่มีการอัปโหลดใหม่
    $stmt_old = $conn->prepare("SELECT fuel_receipt, accommodation_receipt, other_receipt FROM reports WHERE id = ?");
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();

    // 6. ฟังก์ชันจัดการไฟล์น้ำมัน (Multiple Uploads)
    function processFuelUploads($fileKey, $oldString)
    {
        $names = !empty($oldString) ? explode(',', $oldString) : [];
        if (isset($_FILES[$fileKey]) && is_array($_FILES[$fileKey]['name'])) {
            foreach ($_FILES[$fileKey]['name'] as $i => $name) {
                if ($_FILES[$fileKey]['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $new_name = "fuel_" . time() . "_" . $i . "_" . rand(100, 999) . "." . $ext;
                    if (move_uploaded_file($_FILES[$fileKey]["tmp_name"][$i], "uploads/" . $new_name)) {
                        $names[] = $new_name; // เพิ่มไฟล์ใหม่เข้าไปต่อท้ายไฟล์เดิม
                    }
                }
            }
        }
        return implode(',', array_filter($names));
    }

    // ฟังก์ชันจัดการไฟล์เดี่ยว
    function processSingleUpload($fileKey, $oldFile)
    {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
            $ext = pathinfo($_FILES[$fileKey]["name"], PATHINFO_EXTENSION);
            $new_name = "upd_" . $fileKey . "_" . time() . "_" . rand(100, 999) . "." . $ext;
            if (move_uploaded_file($_FILES[$fileKey]["tmp_name"], "uploads/" . $new_name)) {
                return $new_name;
            }
        }
        return $oldFile;
    }

    $fuel_slips = processFuelUploads('fuel_file', $old_data['fuel_receipt']);
    $hotel_slip = processSingleUpload('hotel_file', $old_data['accommodation_receipt']);
    $other_slip = processSingleUpload('other_file', $old_data['other_receipt']);

    // 7. อัปเดตข้อมูลลง Database
    $sql_upd = "UPDATE reports SET 
                fuel_cost = ?, fuel_receipt = ?,
                accommodation_cost = ?, accommodation_receipt = ?,
                other_cost = ?, other_receipt = ?, other_cost_detail = ?, 
                total_expense = ?
                WHERE id = ?";

    if ($stmt = $conn->prepare($sql_upd)) {
        // s = string, d = double, i = integer
        // หมายเหตุ: fuel_cost เปลี่ยนเป็น "s" เพื่อเก็บ string คั่นคอมม่า
        $stmt->bind_param(
            "ssdsdssdi",
            $fuel_cost_save,
            $fuel_slips,
            $accom_cost,
            $hotel_slip,
            $other_cost,
            $other_slip,
            $other_detail,
            $total_expense,
            $id
        );

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'บันทึกข้อมูลค่าใช้จ่ายและแยกรายการเรียบร้อยแล้ว'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // 🛑 หยุดทำงานทันทีเพื่อป้องกัน HTML ส่วนเกิน
    exit();
}
// =========================================================
// 🚀 1. AJAX API (สำหรับดึงประวัติลูกค้า)
// =========================================================
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'get_customer_history') {
    $customer_name = $conn->real_escape_string($_GET['customer_name']);
    $s_date = $_GET['start_date'] ?? '';
    $e_date = $_GET['end_date'] ?? '';

    // ✅ ใช้ LIKE เพื่อหาชื่อในลิสต์ที่มีคอมม่าได้
    $sql_where = "WHERE work_result LIKE '%$customer_name%'";

    if (!empty($s_date)) {
        $sql_where .= " AND report_date >= '$s_date'";
    }
    if (!empty($e_date)) {
        $sql_where .= " AND report_date <= '$e_date'";
    }

    // 🟢 แก้ไข: เพิ่ม work_result เข้าไปใน SELECT
    $sql_hist = "SELECT 
                    id, 
                    report_date, 
                    reporter_name, 
                    work_result, 
                    job_status, 
                    total_expense, 
                    project_name, 
                    activity_detail,   /* 👈 ตัวสำคัญ! ต้องเพิ่มคำนี้ */
                    additional_notes   /* 👈 บันทึกเพิ่มเติม ก็ต้องเพิ่ม */
                 FROM reports $sql_where 
                 ORDER BY report_date DESC";

    $res_hist = $conn->query($sql_hist);
    $history_data = [];
    if ($res_hist) {
        while ($row = $res_hist->fetch_assoc()) {
            $history_data[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($history_data);
    exit();
}

// --- CONFIG ---
$table_name = 'reports';
$upload_path = 'uploads/';
$page_title = 'Sales Dashboard';

// --- FILTER ---
$filter_name = $_GET['filter_name'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_sql = "WHERE 1=1"; // เริ่มต้นเงื่อนไข

// 🟢 1. ดักจับค่าวันที่ (ของเดิมน่าจะมีอยู่แล้ว)
if (!empty($_GET['start_date'])) {
    $s_date = $conn->real_escape_string($_GET['start_date']);
    $where_sql .= " AND report_date >= '$s_date'";
}
if (!empty($_GET['end_date'])) {
    $e_date = $conn->real_escape_string($_GET['end_date']);
    $where_sql .= " AND report_date <= '$e_date'";
}

// 🟢 2. เพิ่มส่วนดักจับสถานะ (สำคัญมาก! ต้องเพิ่มตรงนี้)
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

if (!empty($filter_status)) {
    $filter_status = $conn->real_escape_string($filter_status);

    // 🚨 แก้บั๊ก: ถ้าเลือก "ได้งาน" ต้องระวังไม่ให้ไปติด "ไม่ได้งาน"
    if ($filter_status == 'ได้งาน') {
        // สูตร: ต้องมีคำว่า "ได้งาน" แต่อย่ามีคำว่า "ไม่ได้งาน" ในบรรทัดเดียวกัน
        // (หรือถ้าอยากให้ละเอียดกว่านี้ อาจต้องใช้ REGEXP แต่แบบนี้เข้าใจง่ายสุดครับ)
        $where_sql .= " AND (job_status LIKE '%ได้งาน%' AND job_status NOT LIKE '%ไม่ได้งาน%')";
    } else {
        // กรณีอื่นๆ (เช่น ติดตามงาน, เข้าพบ) ใช้ LIKE ตามปกติ
        $where_sql .= " AND job_status LIKE '%$filter_status%'";
    }
}
// --- KPI CALCULATION ---
$status_counts = [];
$total_expense = 0;
$total_reports = 0;

// 1. ดึงข้อมูลดิบมาทั้งหมดก่อนตามเงื่อนไข Filter
$sql_all = "SELECT job_status, total_expense FROM $table_name $where_sql";
$res_all = $conn->query($sql_all);

if ($res_all) {
    while ($row = $res_all->fetch_assoc()) {
        $total_expense += $row['expense'] ?? $row['total_expense'];

        // 2. ระเบิดสถานะที่คั่นด้วยคอมม่าออกเป็น Array
        $raw_status = $row['job_status'] ?: 'ไม่ระบุ';
        $individual_statuses = explode(',', $raw_status);

        foreach ($individual_statuses as $st) {
            $st = trim($st); // ตัดช่องว่าง
            if ($st != '-' && !empty($st)) {
                // 3. นับเพิ่มทีละสถานะแยกกัน
                if (!isset($status_counts[$st])) {
                    $status_counts[$st] = 0;
                }
                $status_counts[$st]++;
            }
        }
        $total_reports++; // นับจำนวนใบรายงานจริง
    }
}
// --- DATA LIST ---
$sql_list = "SELECT * FROM $table_name $where_sql ORDER BY report_date DESC, id DESC";
$result_list = $conn->query($sql_list);

// --- OPTIONS ---
$users = $conn->query("SELECT DISTINCT reporter_name FROM $table_name ORDER BY reporter_name ASC");
$statuses = $conn->query("SELECT DISTINCT job_status FROM $table_name WHERE job_status != '' ORDER BY job_status ASC");

// ✅ Helper functions
function getCardConfig($status)
{
    $status = trim($status);

    // 🔴 1. สีแดง (ไม่ได้งาน)
    if (strpos($status, 'ไม่ได้') !== false || strpos($status, 'ยกเลิก') !== false || strpos($status, 'แพ้') !== false) {
        return ['color' => '#ef4444', 'icon' => 'fa-times-circle'];
    }

    // 🟢 2. สีเขียว (ได้งาน)
    if (strpos($status, 'ได้งาน') !== false || strpos($status, 'สำเร็จ') !== false || strpos($status, 'เรียบร้อย') !== false) {
        return ['color' => '#10b981', 'icon' => 'fa-check-circle'];
    }

    // 🔵 3. สีฟ้า (เสนอ)
    if (strpos($status, 'เสนอ') !== false || strpos($status, 'เข้าพบ') !== false || strpos($status, 'ประมูล') !== false) {
        return ['color' => '#3b82f6', 'icon' => 'fa-briefcase'];
    }

    // 🟡 4. สีเหลือง (ติดตาม)
    if (strpos($status, 'ติดตาม') !== false || strpos($status, 'รอ') !== false || strpos($status, 'นัดหมาย') !== false) {
        return ['color' => '#f59e0b', 'icon' => 'fa-clock'];
    }

    // 🎨 5. เจนสีอัตโนมัติ (สูตร Sync กับ JS)
    // แปลงข้อความเป็นชุดตัวเลข (Bytes) แล้วเอามาบวกกัน
    $bytes = unpack('C*', $status);
    $sum = 0;
    foreach ($bytes as $b) {
        $sum += $b;
    }

    // คูณด้วยเลขจำนวนเฉพาะ (157) เพื่อกระจายสีให้ไม่ซ้ำกัน
    $hue = ($sum * 157) % 360;

    $generated_color = "hsl($hue, 65%, 45%)"; // สีเข้มกำลังดี
    return ['color' => $generated_color, 'icon' => 'fa-tag'];
}

function hexToRgba($hex, $alpha = 0.1)
{
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "rgba($r, $g, $b, $alpha)";
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title><?php echo $page_title; ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="css/dashboard_style.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

    <script>
        // --- Prevent FOUC ---
        (function () {
            if (localStorage.getItem('tjc_theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.body?.classList.add('dark-mode');
            }
        })();
    </script>
</head>

<body>
    <script>
        // Check local storage immediately
        if (localStorage.getItem('tjc_theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
    </script>

    <?php include 'sidebar.php'; ?>
    <div class="main-container">
        <div class="page-header">
            <div class="header-title">
                <h2>Sales Dashboard</h2>
                <p>ภาพรวมการปฏิบัติงานฝ่ายขาย</p>
            </div>
        </div>

        <div class="kpi-grid">

            <div class="kpi-card" onclick="filterByStatus('')" style="cursor: pointer; border-left: 5px solid #64748b;">
                <div class="kpi-label" style="color:#64748b;">รายงานทั้งหมด</div>
                <div class="kpi-value" style="color:#1e293b;"><?php echo number_format($total_reports); ?></div>
                <i class="fas fa-file-alt kpi-icon" style="color:#64748b;"></i>
            </div>

            <?php
            // วนลูปตัวแปร $status_counts ที่ลูกพี่ทำไว้
            foreach ($status_counts as $status_name => $count) {

                // กันเหนียว: ถ้าชื่อสถานะว่างเปล่า ให้ข้ามไป
                if (trim($status_name) == '')
                    continue;

                // ดึงสีและไอคอน (ใช้ฟังก์ชัน getCardConfig ที่เรามีอยู่แล้ว)
                $cfg = getCardConfig($status_name);
                $color = $cfg['color'];
                $icon = $cfg['icon'];

                // แปลงชื่อสถานะให้ปลอดภัย (เผื่อมีอักขระพิเศษ)
                $safe_status = htmlspecialchars($status_name, ENT_QUOTES);
                ?>
                <div class="kpi-card" onclick="filterByStatus('<?php echo $safe_status; ?>')"
                    style="cursor: pointer; border-left: 5px solid <?php echo $color; ?>;">

                    <div class="kpi-label" style="color: <?php echo $color; ?>;">
                        <?php echo $status_name; ?>
                    </div>

                    <div class="kpi-value" style="color: <?php echo $color; ?>; filter: brightness(0.8);">
                        <?php echo number_format($count); ?>
                    </div>

                    <i class="fas <?php echo $icon; ?> kpi-icon" style="color: <?php echo $color; ?>;"></i>
                </div>
            <?php } ?>

        </div>

        <div class="filter-section">

            <form method="GET" action="">
                <input type="hidden" name="filter_status" id="filter_status"
                    value="<?php echo htmlspecialchars($filter_status); ?>">

                <div class="filter-form">
                    <div class="form-group">
                        <label class="form-label">พนักงาน</label>
                        <select name="filter_name" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <?php while ($u = $users->fetch_assoc()) {
                                echo "<option value='{$u['reporter_name']}' " . ($filter_name == $u['reporter_name'] ? 'selected' : '') . ">{$u['reporter_name']}</option>";
                            } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">สถานะ</label>
                        <select name="filter_status" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <?php while ($s = $statuses->fetch_assoc()) {
                                echo "<option value='{$s['job_status']}' " . ($filter_status == $s['job_status'] ? 'selected' : '') . ">{$s['job_status']}</option>";
                            } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">วันที่เริ่ม</label>
                        <div style="position: relative;">
                            <input type="text" name="start_date" class="form-control datepicker"
                                value="<?php echo $start_date; ?>" placeholder="เลือกวันที่...">
                            <i class="fas fa-calendar-alt"
                                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none;"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ถึงวันที่</label>
                        <div style="position: relative;">
                            <input type="text" name="end_date" class="form-control datepicker"
                                value="<?php echo $end_date; ?>" placeholder="เลือกวันที่...">
                            <i class="fas fa-calendar-alt"
                                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none;"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <div style="height: 19px;"></div>
                        <div class="button-group">
                            <button type="submit" class="btn-search"><i class="fas fa-search"></i> ค้นหา</button>
                            <a href="Dashboard.php" class="btn-reset"><i class="fas fa-undo"></i> รีเซ็ต</a>
                        </div>
                    </div>
                </div>
            </form>

        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่/เวลา</th>
                            <th>พนักงาน</th>
                            <th>ลูกค้า/กิจกรรม</th>
                            <th>โครงการ</th>
                            <th>สถานะ</th>
                            <th>ค่าใช้จ่าย</th>
                            <th style="text-align:center;">หลักฐาน</th>
                            <th style="text-align:center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_list && $result_list->num_rows > 0):
                            while ($row = $result_list->fetch_assoc()):
                                $row['std_fuel'] = (float) ($row['fuel'] ?? $row['fuel_cost'] ?? $row['fuel_expense'] ?? 0);
                                $row['std_hotel'] = (float) ($row['accommodation'] ?? $row['hotel'] ?? $row['hotel_cost'] ?? $row['accommodation_cost'] ?? 0);
                                $row['std_other'] = (float) ($row['other'] ?? $row['other_cost'] ?? $row['public_transport'] ?? $row['other_expense'] ?? 0);

                                $cfg = getCardConfig($row['job_status']);
                                $bg_color = hexToRgba($cfg['color'], 0.1);
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700; color:var(--text-main);">
                                            <?php echo date('d/m/Y', strtotime($row['report_date'])); ?>
                                        </div>
                                        <div style="font-size:12px; color:var(--text-sub); margin-top:2px;">
                                            <?php echo date('H:i', strtotime($row['created_at'])); ?> น.
                                        </div>
                                    </td>

                                    <td>
                                        <div style="font-weight:600; color:var(--text-main); margin-bottom: 4px;">
                                            <?php echo $row['reporter_name']; ?>
                                        </div>

                                        <div style="margin-top: 5px;">
                                            <?php if (isset($row['gps']) && $row['gps'] == 'Office'): ?>
                                                <span class="status-badge gps-tag-office" style="font-size: 10px;">
                                                    <i class="fas fa-building"></i> ออฟฟิศ
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge gps-tag-out" style="font-size: 10px;">
                                                    <i class="fas fa-map-marker-alt"></i> นอกสถานที่
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <?php
                                    // --- เตรียมข้อมูลสำหรับเเยกบรรทัด ---
                                    $customers = explode(',', $row['work_result']);
                                    $projects = explode(',', $row['project_name']);
                                    $max_rows = max(count($customers), count($projects));
                                    $min_h = "min-height: 40px;"; // ปรับความสูงขั้นต่ำให้ลดลงได้เพราะไม่มี GPS แล้ว
                                    ?>

                                    <td style="padding: 0; vertical-align: top; border-right: 1px solid rgba(0,0,0,0.05);">
                                        <?php for ($i = 0; $i < $max_rows; $i++):
                                            $cus_item = isset($customers[$i]) ? trim($customers[$i]) : '';
                                            $border = ($i < $max_rows - 1) ? 'border-bottom: 1px dashed rgba(0,0,0,0.05);' : '';
                                            ?>
                                            <div style="padding: 10px; <?php echo $min_h . $border; ?>">
                                                <?php if (!empty($cus_item)): ?>
                                                    <div style="cursor: pointer; color: var(--primary-color); font-weight: 600;"
                                                        onclick="event.stopPropagation(); showCustomerHistory('<?php echo htmlspecialchars($cus_item, ENT_QUOTES); ?>')">
                                                        <i class="fas fa-university text-primary me-2"></i>
                                                        <span style="text-decoration: underline;"><?php echo $cus_item; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </td>

                                    <td style="padding: 0; vertical-align: top;">
                                        <?php for ($i = 0; $i < $max_rows; $i++):
                                            $proj_item = isset($projects[$i]) ? trim($projects[$i]) : '';
                                            $border = ($i < $max_rows - 1) ? 'border-bottom: 1px dashed rgba(0,0,0,0.05);' : '';
                                            ?>
                                            <div style="padding: 10px; <?php echo $min_h . $border; ?> font-weight: 500;">
                                                <?php if (!empty($proj_item)): ?>
                                                    <i class="fas fa-caret-right text-muted me-1"></i> <?php echo $proj_item; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </td>
                                    <td style="padding: 0; vertical-align: top; border-right: 1px solid rgba(0,0,0,0.05);">
                                        <?php
                                        // แยกสถานะด้วยคอมม่า
                                        $job_statuses_arr = explode(',', $row['job_status']);

                                        // วนลูปตาม $max_rows (ที่เราคำนวณไว้ก่อนหน้านี้ในคอลัมน์ลูกค้า)
                                        for ($i = 0; $i < $max_rows; $i++) {
                                            $st_item = isset($job_statuses_arr[$i]) ? trim($job_statuses_arr[$i]) : '';
                                            $border = ($i < $max_rows - 1) ? 'border-bottom: 1px dashed rgba(0,0,0,0.05);' : '';

                                            // ถ้าบรรทัดนั้นมีข้อมูลสถานะ ให้ดึง Config สีและไอคอน
                                            if (!empty($st_item)) {
                                                $st_cfg = getCardConfig($st_item);
                                                $st_bg = hexToRgba($st_cfg['color'], 0.1);
                                            }
                                            ?>
                                            <div
                                                style="padding: 10px; <?php echo $min_h . $border; ?> display: flex; align-items: center;">
                                                <?php if (!empty($st_item)): ?>
                                                    <span class='status-badge'
                                                        style="background-color: <?php echo $st_bg; ?>; color: <?php echo $st_cfg['color']; ?>; border: 1px solid <?php echo hexToRgba($st_cfg['color'], 0.2); ?>; font-size: 11px; white-space: nowrap;">
                                                        <i class='fas <?php echo $st_cfg['icon']; ?>'></i> <?php echo $st_item; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td style="font-weight:700; color:var(--ev-fuel-text);">
                                        <?php echo number_format($row['total_expense']); ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex; justify-content:center; gap:5px; flex-wrap: wrap;">
                                            <?php
                                            $has_ev = false;

                                            // 🟢 1. แก้ไขส่วนน้ำมัน (ระเบิดไฟล์ด้วย comma)
                                            if (!empty($row['fuel_receipt'])) {
                                                $fuel_files = explode(',', $row['fuel_receipt']); // แยกไฟล์ด้วย ,
                                                foreach ($fuel_files as $file) {
                                                    $file = trim($file); // ตัดช่องว่างออก
                                                    if (!empty($file)) {
                                                        echo '<a href="' . $upload_path . $file . '" target="_blank" class="btn-evidence ev-fuel" title="บิลน้ำมัน"><i class="fas fa-gas-pump"></i></a>';
                                                        $has_ev = true;
                                                    }
                                                }
                                            }

                                            // 🔵 2. ส่วนที่พัก (ถ้ามีไฟล์เดียวก็โชว์เลย)
                                            if (!empty($row['accommodation_receipt'])) {
                                                // ถ้าอนาคตที่พักมีหลายไฟล์ ก็ใช้ explode เหมือนข้างบนได้ครับ
                                                echo '<a href="' . $upload_path . $row['accommodation_receipt'] . '" target="_blank" class="btn-evidence ev-hotel" title="บิลที่พัก"><i class="fas fa-hotel"></i></a>';
                                                $has_ev = true;
                                            }

                                            // 🟡 3. ส่วนอื่นๆ
                                            if (!empty($row['other_receipt'])) {
                                                echo '<a href="' . $upload_path . $row['other_receipt'] . '" target="_blank" class="btn-evidence ev-other" title="บิลอื่นๆ"><i class="fas fa-receipt"></i></a>';
                                                $has_ev = true;
                                            }

                                            if (!$has_ev)
                                                echo '<span style="color:var(--text-sub); font-size:12px;">-</span>';
                                            ?>
                                        </div>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex; gap:5px; justify-content:center;">
                                            <button onclick='showDetail(<?php echo json_encode($row); ?>)' class="btn-view"
                                                title="ดูรายละเอียด"><i class="fas fa-eye"></i></button>
                                            <button style="display:none;" onclick='openExpenseModal(<?php echo json_encode($row); ?>)'
                                                class="btn-action-edit" title="แก้ไขค่าใช้จ่าย"><i
                                                    class="fas fa-edit"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:30px; color:var(--text-sub);">ไม่พบข้อมูล
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="detailModal" class="modal" onclick="if(event.target==this)closeModal('detailModal')">
        <div class="modal-content">
            <div class="modal-header">
                <h3>รายละเอียดรายงาน</h3>
                <span onclick="closeModal('detailModal')" class="modal-close">&times;</span>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <div id="historyModal" class="modal" onclick="if(event.target==this)closeModal('historyModal')">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="histModalTitle"><i class="fas fa-users"></i> ประวัติ: ลูกค้า</h3>
                <span onclick="closeModal('historyModal')" class="modal-close">&times;</span>
            </div>
            <div class="modal-body" id="histModalBody">
                <div style="text-align:center; padding:20px; color:var(--text-sub);">กำลังโหลดข้อมูล...</div>
            </div>
        </div>
    </div>

    <div id="expenseModal" class="modal" onclick="if(event.target==this)closeModal('expenseModal')">
        <div class="modal-content" style="max-width: 550px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_expense">
                <input type="hidden" name="report_id" id="ex_report_id">
                <div class="modal-header-orange">
                    <h3><i class="fas fa-coins"></i> อัปเดตค่าใช้จ่าย</h3>
                    <span onclick="closeModal('expenseModal')" class="modal-close">&times;</span>
                </div>
                <div class="modal-body" style="padding: 25px; background-color: #f8fafc;">
                    <div
                        style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; border: 1px solid #e2e8f0;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                            <label class="detail-label" style="font-weight: 700; color: #334155; margin: 0;">
                                <i class="fas fa-gas-pump" style="color: #ef4444; margin-right: 8px;"></i>ค่าน้ำมัน
                                (ระบุเป็นรายการ)
                            </label>
                            <button type="button" onclick="addFuelRowEdit()"
                                style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; border-radius:8px; font-size:0.75rem; padding:6px 12px; cursor:pointer; font-weight: 600; transition: all 0.2s;">
                                <i class="fas fa-plus-circle"></i> เพิ่มช่อง
                            </button>
                        </div>

                        <div id="fuel_edit_container">
                            <div class="fuel-row"
                                style="display:flex; gap:10px; margin-bottom:12px; align-items: center;">
                                <div style="position: relative; flex: 1;">
                                    <span
                                        style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem;">฿</span>
                                    <input type="number" step="0.01" name="fuel_cost[]" id="ex_fuel_0"
                                        class="form-control fuel-calc" placeholder="0.00" oninput="calcTotalEdit()"
                                        style="padding-left: 25px; border-radius: 8px;">
                                </div>

                                <div style="flex: 1;">
                                    <label class="upload-btn-mini"
                                        style="width: 100%; border-radius: 8px; justify-content: center; background: #f1f5f9; border: 1px dashed #cbd5e1;">
                                        <i class="fas fa-camera"></i> สลิปน้ำมัน
                                        <input type="file" name="fuel_file[]" accept="image/*" hidden
                                            onchange="previewFile(this, 'prev_fuel_0')">
                                    </label>
                                    <div id="prev_fuel_0" class="file-status"
                                        style="font-size: 10px; margin-top: 4px; text-align: center;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">

                        <div
                            style="background: white; padding: 18px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <label class="detail-label" style="font-weight: 700; color: #334155;"><i
                                    class="fas fa-hotel"
                                    style="color: #3b82f6; margin-right: 8px;"></i>ค่าที่พัก</label>
                            <div style="display:flex; gap:10px; margin-top: 8px;">
                                <input type="number" step="0.01" name="accommodation_cost" id="ex_hotel"
                                    class="form-control" placeholder="0.00" oninput="calcTotalEdit()"
                                    style="border-radius: 8px;">
                                <div style="width:50%;">
                                    <label class="upload-btn-mini"
                                        style="width: 100%; border-radius: 8px; justify-content: center;">
                                        <i class="fas fa-upload"></i> เปลี่ยนสลิป
                                        <input type="file" name="hotel_file" accept="image/*" hidden
                                            onchange="previewFile(this, 'prev_hotel')">
                                    </label>
                                    <div id="prev_hotel" class="file-status"></div>
                                </div>
                            </div>
                        </div>

                        <div
                            style="background: white; padding: 18px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <label class="detail-label" style="font-weight: 700; color: #334155;"><i
                                    class="fas fa-receipt"
                                    style="color: #eab308; margin-right: 8px;"></i>ค่าใช้จ่ายอื่นๆ</label>
                            <div style="display:flex; gap:10px; align-items: flex-start; margin-top: 8px;">
                                <input type="number" step="0.01" name="other_cost" id="ex_other" class="form-control"
                                    placeholder="0.00" oninput="calcTotalEdit()"
                                    style="width: 30%; border-radius: 8px;">
                                <input type="text" name="other_detail" id="ex_other_detail" class="form-control"
                                    placeholder="รายละเอียด (เช่น ทางด่วน)" style="width: 40%; border-radius: 8px;">
                                <div style="width: 30%;">
                                    <label class="upload-btn-mini"
                                        style="width: 100%; border-radius: 8px; justify-content: center;">
                                        <i class="fas fa-upload"></i> สลิป
                                        <input type="file" name="other_file" accept="image/*" hidden
                                            onchange="previewFile(this, 'prev_other')">
                                    </label>
                                    <div id="prev_other" class="file-status"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="total-card"
                        style="margin-top: 25px; background: linear-gradient(135deg, #475569 0%, #1e293b 100%); color: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);">
                        <div
                            style="font-size:0.85rem; opacity:0.8; margin-bottom:5px; text-transform: uppercase; letter-spacing: 1px;">
                            ยอดรวมสุทธิใหม่</div>
                        <div style="font-size:2.5rem; font-weight:800; line-height:1; text-shadow: 0 2px 4px rgba(0,0,0,0.3);"
                            id="ex_total_display">0.00 ฿</div>
                    </div>

                    <button type="button" onclick="saveEdit()" class="btn-save-orange"
                        style="width: 100%; margin-top: 20px; padding: 15px; border-radius: 12px; font-size: 1.1rem; font-weight: 700; box-shadow: 0 4px 6px -1px rgba(249, 115, 22, 0.4); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <i class="fas fa-save"></i> บันทึกการแก้ไขข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>const uploadPath = '<?php echo $upload_path; ?>';</script>
    <script src="js/dashboard_script.js"></script>
</body>

</html>