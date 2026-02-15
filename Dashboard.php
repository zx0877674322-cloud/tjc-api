<?php
session_start();
// require_once 'auth.php'; // เปิดใช้งานเมื่อระบบพร้อม
require_once 'db_connect.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_expense') {
    $id = intval($_POST['report_id']);

    // 1. รับค่าและคำนวณยอดเงิน (รองรับ Array สำหรับน้ำมัน)
    $fuel_costs = $_POST['fuel_cost'] ?? [];
    $fuel_total = 0;

    // วนลูปบวกเงินค่าน้ำมันทุกช่อง
    if (is_array($fuel_costs)) {
        foreach ($fuel_costs as $c) {
            $fuel_total += floatval($c);
        }
    } else {
        $fuel_total = floatval($fuel_costs);
    }

    $accom_cost = floatval($_POST['accommodation_cost']);
    $other_cost = floatval($_POST['other_cost']);
    $total_expense = $fuel_total + $accom_cost + $other_cost;

    // ดึงข้อมูลเก่าเพื่อเอาชื่อไฟล์เดิมมาตั้งต้น
    $q_old = $conn->query("SELECT fuel_receipt, accommodation_receipt, other_receipt FROM reports WHERE id=$id");
    $old = $q_old->fetch_assoc();

    // 2. ฟังก์ชันจัดการไฟล์แบบ Multiple (สำหรับน้ำมัน)
    function handleMultipleUpload($fileKey, $oldFilesString)
    {
        $uploaded_names = [];
        if (!empty($oldFilesString)) {
            $uploaded_names = explode(',', $oldFilesString); // เก็บชื่อไฟล์เดิมไว้
        }

        if (isset($_FILES[$fileKey])) {
            $count = count($_FILES[$fileKey]['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES[$fileKey]['error'][$i] == 0) {
                    $ext = pathinfo($_FILES[$fileKey]["name"][$i], PATHINFO_EXTENSION);
                    $new_name = "upd_fuel_" . time() . "_" . $i . "_" . rand(100, 999) . "." . $ext;
                    if (move_uploaded_file($_FILES[$fileKey]["tmp_name"][$i], "uploads/" . $new_name)) {
                        $uploaded_names[] = $new_name;
                    }
                }
            }
        }
        return implode(',', array_filter($uploaded_names));
    }

    // ฟังก์ชันอัปโหลดเดี่ยว (สำหรับที่พัก/อื่นๆ)
    function handleSingleUpload($fileKey, $oldFile)
    {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
            $target_dir = "uploads/";
            $ext = pathinfo($_FILES[$fileKey]["name"], PATHINFO_EXTENSION);
            $new_name = "upd_" . time() . "_" . rand(100, 999) . "." . $ext;
            if (move_uploaded_file($_FILES[$fileKey]["tmp_name"], $target_dir . $new_name)) {
                return $new_name;
            }
        }
        return $oldFile;
    }

    // จัดการไฟล์
    // กรณีน้ำมัน: ถ้า User กดลบไฟล์เก่าในหน้าเว็บ (Logic นี้ซับซ้อน ขอใช้แบบ Append เพิ่มไฟล์ใหม่เข้าไปก่อนเพื่อความปลอดภัย)
    // หรือถ้าต้องการ Reset ไฟล์ ให้เพิ่ม Logic ลบไฟล์เดิม (ในที่นี้เน้น Add เพิ่ม)
    $fuel_slips = handleMultipleUpload('fuel_file', $old['fuel_receipt']);

    $hotel_slip = handleSingleUpload('hotel_file', $old['accommodation_receipt']);
    $other_slip = handleSingleUpload('other_file', $old['other_receipt']);

    // 3. อัปเดตลงฐานข้อมูล
    $sql_upd = "UPDATE reports SET 
                fuel_cost = ?, fuel_receipt = ?,
                accommodation_cost = ?, accommodation_receipt = ?,
                other_cost = ?, other_receipt = ?,
                total_expense = ?
                WHERE id = ?";

    if ($stmt = $conn->prepare($sql_upd)) {
        $stmt->bind_param("dsdsdsdi", $fuel_total, $fuel_slips, $accom_cost, $hotel_slip, $other_cost, $other_slip, $total_expense, $id);
        // ... (ส่วน Execute และแจ้งเตือน เหมือนเดิม) ...
        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success', title: 'อัปเดตเรียบร้อย', text: 'ยอดเงินและหลักฐานถูกบันทึกแล้ว',
                        timer: 1500, showConfirmButton: false
                    }).then(() => { window.location.href = 'Dashboard.php'; });
                });
            </script>";
        }
        $stmt->close();
    }
}
// =========================================================
// 🚀 1. AJAX API (สำหรับดึงประวัติลูกค้า)
// =========================================================
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'get_customer_history') {
    $customer_name = $conn->real_escape_string($_GET['customer_name']);
    $s_date = $_GET['start_date'] ?? '';
    $e_date = $_GET['end_date'] ?? '';

    $sql_where = "WHERE work_result = '$customer_name'";
    if (!empty($s_date)) {
        $sql_where .= " AND report_date >= '$s_date'";
    }
    if (!empty($e_date)) {
        $sql_where .= " AND report_date <= '$e_date'";
    }

    $sql_hist = "SELECT report_date, reporter_name, job_status, total_expense, project_name, additional_notes 
                 FROM reports $sql_where ORDER BY report_date DESC";

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

$where_sql = "WHERE 1=1";
if ($filter_name)
    $where_sql .= " AND reporter_name = '$filter_name'";
if ($start_date)
    $where_sql .= " AND report_date >= '$start_date'";
if ($end_date)
    $where_sql .= " AND report_date <= '$end_date'";
if ($filter_status)
    $where_sql .= " AND job_status = '$filter_status'";

// --- KPI CALCULATION ---
$status_counts = [];
$total_expense = 0;
$total_reports = 0;

$sql_stats = "SELECT job_status, COUNT(*) as count, SUM(total_expense) as expense FROM $table_name $where_sql GROUP BY job_status";
$res_stats = $conn->query($sql_stats);
if ($res_stats) {
    while ($row = $res_stats->fetch_assoc()) {
        $st = trim($row['job_status']) ?: 'ไม่ระบุ';
        $status_counts[$st] = $row['count'];
        $total_expense += $row['expense'];
        $total_reports += $row['count'];
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
    $s = trim($status);
    if (strpos($s, 'ได้งาน') !== false || strpos($s, 'สำเร็จ') !== false)
        return ['color' => '#10b981', 'icon' => 'fa-check-circle'];
    if (strpos($s, 'เข้าเสนอโครงการ') !== false || strpos($s, 'เสนอ') !== false)
        return ['color' => '#3b82f6', 'icon' => 'fa-briefcase']; // Blue Adjusted
    if (strpos($s, 'ติดตามงาน') !== false || strpos($s, 'ติดตาม') !== false || strpos($s, 'รอ') !== false)
        return ['color' => '#f59e0b', 'icon' => 'fa-clock'];
    if (strpos($s, 'ไม่ได้') !== false || strpos($s, 'ยกเลิก') !== false)
        return ['color' => '#ef4444', 'icon' => 'fa-times-circle'];

    $palette = ['#8b5cf6', '#ec4899', '#06b6d4', '#14b8a6', '#f97316', '#6366f1'];
    $hash = crc32($s);
    $index = abs($hash) % count($palette);
    return ['color' => $palette[$index], 'icon' => 'fa-tag'];
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
            <div class="kpi-card" onclick="filterByStatus('')" style="border-left: 5px solid var(--primary-color);">
                <div class="kpi-label" style="color: var(--primary-color);">รายงานทั้งหมด</div>
                <div class="kpi-value"><?php echo number_format($total_reports); ?></div>
                <i class="fas fa-file-alt kpi-icon" style="color: var(--primary-color);"></i>
            </div>
            <?php foreach ($status_counts as $st => $cnt):
                $cfg = getCardConfig($st); ?>
                <div class="kpi-card" onclick="filterByStatus('<?php echo $st; ?>')"
                    style="border-left: 5px solid <?php echo $cfg['color']; ?>;">
                    <div class="kpi-label" style="color: <?php echo $cfg['color']; ?>;"><?php echo $st; ?></div>
                    <div class="kpi-value"><?php echo number_format($cnt); ?></div>
                    <i class="fas <?php echo $cfg['icon']; ?> kpi-icon" style="color: <?php echo $cfg['color']; ?>;"></i>
                </div>
            <?php endforeach; ?>
            <div class="kpi-card" style="border-left: 5px solid #ef4444; cursor: default;">
                <div class="kpi-label" style="color: #ef4444;">ค่าใช้จ่ายรวม</div>
                <div class="kpi-value" style="color: #ef4444;"><?php echo number_format($total_expense); ?> ฿</div>
                <i class="fas fa-wallet kpi-icon" style="color: #ef4444;"></i>
            </div>
        </div>

        <form class="filter-section">
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
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">ถึงวันที่</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                </div>
                <div class="form-group">
                    <div class="button-group">
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> ค้นหา</button>
                        <a href="Dashboard.php" class="btn-reset"><i class="fas fa-undo"></i> รีเซ็ต</a>
                    </div>
                </div>
            </div>
        </form>

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
                                        <div>
                                            <div
                                                style="font-weight:600; color:var(--text-main); margin-bottom: 4px; white-space: nowrap;">
                                                <?php echo $row['reporter_name']; ?>
                                            </div>
                                            <?php if (isset($row['gps']) && $row['gps'] == 'Office'): ?>
                                                <span class="status-badge gps-tag-office"><i class="fas fa-building"></i>
                                                    ออฟฟิศ</span>
                                            <?php else: ?>
                                                <span class="status-badge gps-tag-out"><i class="fas fa-map-marker-alt"></i>
                                                    นอกสถานที่</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="customer-link"
                                            onclick="showCustomerHistory('<?php echo htmlspecialchars($row['work_result']); ?>')">
                                            <i class="fas fa-history"
                                                style="font-size:12px; margin-right:5px; opacity:0.6;"></i>
                                            <?php echo $row['work_result']; ?>
                                        </div>
                                        <div style="font-size:12px; color:var(--text-sub); margin-top:2px;">
                                            <?php echo $row['activity_type']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $row['project_name']; ?></td>
                                    <td>
                                        <span class='status-badge'
                                            style="background-color: <?php echo $bg_color; ?>; color: <?php echo $cfg['color']; ?>; border: 1px solid <?php echo hexToRgba($cfg['color'], 0.2); ?>;">
                                            <i class='fas <?php echo $cfg['icon']; ?>'></i> <?php echo $row['job_status']; ?>
                                        </span>
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
                                            <button onclick='openExpenseModal(<?php echo json_encode($row); ?>)'
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
                <div class="modal-body" style="padding: 25px;">

                    <div style="border-bottom:1px dashed #e2e8f0; margin-bottom:15px; padding-bottom:10px;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <label class="detail-label"><i class="fas fa-gas-pump"></i> ค่าน้ำมัน (หลายรายการ)</label>
                            <button type="button" onclick="addFuelRowEdit()"
                                style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; font-size:0.8rem; padding:4px 10px; cursor:pointer; transition:0.2s;">
                                <i class="fas fa-plus-circle"></i> เพิ่มช่อง
                            </button>
                        </div>

                        <div id="fuel_edit_container">
                            <div class="fuel-row" style="display:flex; gap:10px; margin-bottom:10px;">
                                <input type="number" step="0.01" name="fuel_cost[]" id="ex_fuel_0"
                                    class="form-control fuel-calc" placeholder="บาท" oninput="calcTotalEdit()">

                                <div style="width:50%;">
                                    <label class="upload-btn-mini">
                                        <i class="fas fa-upload"></i> สลิป
                                        <input type="file" name="fuel_file[]" accept="image/*" hidden
                                            onchange="previewFile(this, 'prev_fuel_0')">
                                    </label>
                                    <div id="prev_fuel_0" class="file-status"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="expense-edit-group">
                        <label class="detail-label"><i class="fas fa-hotel"></i> ค่าที่พัก</label>
                        <div style="display:flex; gap:10px;">
                            <input type="number" step="0.01" name="accommodation_cost" id="ex_hotel"
                                class="form-control" placeholder="0.00" oninput="calcTotalEdit()">
                            <div style="width:50%;">
                                <label class="upload-btn-mini"><i class="fas fa-upload"></i> เปลี่ยนสลิป
                                    <input type="file" name="hotel_file" accept="image/*" hidden
                                        onchange="previewFile(this, 'prev_hotel')">
                                </label>
                                <div id="prev_hotel" class="file-status"></div>
                            </div>
                        </div>
                    </div>

                    <div class="expense-edit-group">
                        <label class="detail-label"><i class="fas fa-receipt"></i> อื่นๆ</label>
                        <div style="display:flex; gap:10px;">
                            <input type="number" step="0.01" name="other_cost" id="ex_other" class="form-control"
                                placeholder="0.00" oninput="calcTotalEdit()">
                            <div style="width:50%;">
                                <label class="upload-btn-mini"><i class="fas fa-upload"></i> เปลี่ยนสลิป
                                    <input type="file" name="other_file" accept="image/*" hidden
                                        onchange="previewFile(this, 'prev_other')">
                                </label>
                                <div id="prev_other" class="file-status"></div>
                            </div>
                        </div>
                    </div>

                    <div class="total-card">
                        <div style="font-size:0.9rem; opacity:0.8; margin-bottom:5px;">ยอดรวมสุทธิใหม่</div>
                        <div style="font-size:2rem; font-weight:800; line-height:1;" id="ex_total_display">0.00 ฿</div>
                    </div>

                    <button type="submit" class="btn-save-orange"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>

    <script>const uploadPath = '<?php echo $upload_path; ?>';</script>
    <script src="js/dashboard_script.js"></script>
</body>

</html>