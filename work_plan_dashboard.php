<?php
// work_plan_dashboard.php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// --- Helper Function: Smart Color ---

function getStatusThemeColor($status_name, $status_id)
{
    $status_name = trim($status_name);

    // -----------------------------------------------------------
    // 🟢 1. Plan (วางแผน/รอสรุป) -> สีฟ้าสว่าง (Cyan)
    // -----------------------------------------------------------
    if ($status_name == 'Plan' || strpos($status_name, 'วางแผน') !== false) {
        return '#06b6d4'; // Cyan-500
    }

    // -----------------------------------------------------------
    // 🟣 2. เข้าเสนอโครงการ (Proposal) -> สีม่วง (Purple) *แยกออกมาแล้ว*
    // -----------------------------------------------------------
    if (strpos($status_name, 'เสนอ') !== false || strpos($status_name, 'เข้าพบ') !== false) {
        return '#9333ea'; // Purple-600
    }

    // 🔴 3. ไม่ได้งาน / ยกเลิก -> สีแดง
    if (strpos($status_name, 'ไม่ได้งาน') !== false || strpos($status_name, 'ยกเลิก') !== false || $status_name == 'Cancelled') {
        return '#dc2626';
    }

    // 🟢 4. ได้งาน / สำเร็จ -> สีเขียว
    if (strpos($status_name, 'ได้งาน') !== false || strpos($status_name, 'สำเร็จ') !== false || $status_name == 'Completed') {
        return '#16a34a';
    }

    // 🟠 5. ติดตาม / นัดหมาย -> สีส้ม
    if (strpos($status_name, 'ติดตาม') !== false || strpos($status_name, 'นัดหมาย') !== false || $status_name == 'Confirmed') {
        return '#d97706';
    }

    // 🌈 6. สี Auto (สำหรับสถานะอื่นๆ)
    $hue = ($status_id * 137.508) % 360;
    return "hsl($hue, 80%, 45%)";
}

function renderStatusGrid($status_list, $status_counts, $total_jobs, $filter_status)
{
    ob_start();
    ?>
    <div class="status-card <?php echo empty($filter_status) ? 'active' : ''; ?>" onclick="selectStatus('')"
        style="--theme-color: #6366f1; animation-delay: 0ms;">
        <div class="d-flex flex-column position-relative z-1">
            <span class="sc-count"><?php echo $total_jobs; ?></span>
            <span class="sc-label">งานทั้งหมด</span>
        </div>
        <i class="fas fa-layer-group sc-icon"></i>
        <?php if (empty($filter_status)): ?>
            <div style="position: absolute; top: 15px; right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                <i class="fas fa-check-circle fa-xl"></i>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $delay = 100;
    // การ์ด Plan
    $planCount = $status_counts[0] ?? 0;
    if ($planCount > 0 || $filter_status === '0'):
        $isActivePlan = ($filter_status === '0');
        ?>
        <div class="status-card <?php echo $isActivePlan ? 'active' : ''; ?>" onclick="selectStatus('0')"
            style="--theme-color: #06b6d4; animation-delay: <?php echo $delay; ?>ms;">
            <div class="d-flex flex-column position-relative z-1">
                <span class="sc-count"><?php echo $planCount; ?></span>
                <span class="sc-label">Plan (รอสรุป)</span>
            </div>
            <i class="fas fa-clipboard-list sc-icon"></i>
            <?php if ($isActivePlan): ?>
                <div style="position: absolute; top: 15px; right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                    <i class="fas fa-check-circle fa-xl"></i>
                </div>
            <?php endif; ?>
        </div>
        <?php $delay += 100; endif; ?>

    <?php foreach ($status_list as $st):
        $count = $status_counts[$st['id']] ?? 0;
        $isActive = ($filter_status == $st['id']);
        if ($count == 0 && !$isActive)
            continue;

        $themeColor = getStatusThemeColor($st['status_name'], $st['id']);
        $icon = 'fa-circle';
        if (strpos($st['status_name'], 'ไม่ได้งาน') !== false || strpos($st['status_name'], 'ยกเลิก') !== false)
            $icon = 'fa-circle-xmark';
        else if (strpos($st['status_name'], 'ได้งาน') !== false || strpos($st['status_name'], 'สำเร็จ') !== false)
            $icon = 'fa-trophy';
        else if (strpos($st['status_name'], 'เสนอ') !== false)
            $icon = 'fa-file-contract';
        else if (strpos($st['status_name'], 'ติดตาม') !== false)
            $icon = 'fa-clock';
        ?>
        <div class="status-card <?php echo $isActive ? 'active' : ''; ?>" onclick="selectStatus('<?php echo $st['id']; ?>')"
            style="--theme-color: <?php echo $themeColor; ?>; animation-delay: <?php echo $delay; ?>ms;">
            <div class="d-flex flex-column position-relative z-1">
                <span class="sc-count"><?php echo $count; ?></span>
                <span class="sc-label"><?php echo $st['status_name']; ?></span>
            </div>
            <i class="fas <?php echo $icon; ?> sc-icon"></i>
            <?php if ($isActive): ?>
                <div style="position: absolute; top: 15px; right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                    <i class="fas fa-check-circle fa-xl"></i>
                </div>
            <?php endif; ?>
        </div>
        <?php $delay += 100; endforeach; ?>
    <?php
    return ob_get_clean();
}

// --- Logic Save Summary ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_summary') {
    $plan_id = intval($_POST['plan_id']);
    $summary = trim($_POST['summary']);
    $status_id = intval($_POST['status_id']);

    $my_name = $_SESSION['fullname'];

    // 🟢 1. [เพิ่ม] ไปดึงชื่อสถานะ (Text) มาก่อน จะได้เอาไปบันทึกด้วย
    $status_text = "Plan"; // ค่าเริ่มต้น
    $q_name = $conn->prepare("SELECT status_name FROM master_job_status WHERE id = ?");
    $q_name->bind_param("i", $status_id);
    $q_name->execute();
    $res_name = $q_name->get_result();
    if ($r_name = $res_name->fetch_assoc()) {
        $status_text = $r_name['status_name'];
    }
    $q_name->close();

    // 🟢 2. [แก้ไข] อัปเดตทั้ง summary, status_id และ status (Text) พร้อมกัน
    $stmt = $conn->prepare("UPDATE work_plans SET summary = ?, status_id = ?, status = ?, summary_by = ? WHERE id = ?");
    // sissi = string, int, string, string, int
    $stmt->bind_param("sissi", $summary, $status_id, $status_text, $my_name, $plan_id);
    $success = $stmt->execute();
    $stmt->close();

    // ถ้าเป็น AJAX ให้ส่ง JSON กลับ
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => $success]);
        exit;
    }

    header("Location: work_plan_dashboard.php");
    exit();
}

// --- Logic Delete ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // 🟢 แก้จาก $current_user เป็น $_SESSION['fullname']
    $my_name = $_SESSION['fullname'];

    $sql_del = "DELETE FROM work_plans WHERE id = ? AND reporter_name = ?";
    if ($stmt = $conn->prepare($sql_del)) {
        $stmt->bind_param("is", $del_id, $my_name);
        $stmt->execute();
        $stmt->close();
        $_SESSION['swal_msg'] = "ลบข้อมูลเรียบร้อย";
        header("Location: work_plan_dashboard.php");
        exit();
    }
}

// --- Prepare Data ---
$status_list = [];
$q_status = $conn->query("SELECT * FROM master_job_status ORDER BY id ASC");
while ($row = $q_status->fetch_assoc()) {
    $status_list[] = $row;
}

$reporters_list = [];
$q_rep = $conn->query("SELECT DISTINCT reporter_name FROM work_plans ORDER BY reporter_name ASC");
while ($r = $q_rep->fetch_assoc()) {
    $reporters_list[] = $r['reporter_name'];
}

$workers_list = [];
$sql_worker = "SELECT DISTINCT name FROM (
                SELECT reporter_name AS name FROM work_plans
                UNION
                SELECT team_member AS name FROM work_plans WHERE team_member IS NOT NULL AND team_member != ''
               ) AS distinct_workers 
               ORDER BY name ASC";
$q_worker = $conn->query($sql_worker);
while ($w = $q_worker->fetch_assoc()) {
    if (!empty($w['name'])) { // กันเหนียวเผื่อค่าว่างหลุดมา
        $workers_list[] = $w['name'];
    }
}
// --- Filter Variables ---
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_team = $_GET['filter_team'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$filter_worker = $_GET['filter_worker'] ?? '';

// --- 🟢 ส่วนที่ 2: สร้างอาเรย์เงื่อนไขแบบ "ตามจริง" ---
$base_clauses = [];
$base_params = [];
$base_types = "";

// ถ้ามีการระบุวันที่เริ่ม ให้เพิ่มเงื่อนไข
if (!empty($start_date)) {
    $db_start = DateTime::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
    $base_clauses[] = "wp.plan_date >= ?";
    $base_params[] = $db_start;
    $base_types .= "s";
}

// ถ้ามีการระบุวันที่สิ้นสุด ให้เพิ่มเงื่อนไข
if (!empty($end_date)) {
    $db_end = DateTime::createFromFormat('d/m/Y', $end_date)->format('Y-m-d');
    $base_clauses[] = "wp.plan_date <= ?";
    $base_params[] = $db_end;
    $base_types .= "s";
}

// --- 🟢 3. เงื่อนไขอื่นๆ (คงเดิม แต่อย่าลืมเปลี่ยนเป็นการ .push หรือ +=) ---
if (!empty($filter_user)) {
    $base_clauses[] = "wp.reporter_name = ?";
    $base_params[] = $filter_user;
    $base_types .= "s";
}
if (!empty($filter_team)) {
    $base_clauses[] = "wp.team_type = ?";
    $base_params[] = $filter_team;
    $base_types .= "s";
}
if (isset($_GET['filter_worker'])) {
    // กรณี 1: ผู้ใช้กดเลือกจาก Dropdown เอง
    $filter_worker = $_GET['filter_worker'];
} else {
    // กรณี 2: เพิ่งเข้ามาหน้าแรก (Auto Filter)
    $my_name = $_SESSION['fullname'] ?? '';

    // 🟢 เช็คก่อนว่าชื่อฉัน มีอยู่ในรายการผู้ปฏิบัติงานไหม?
    if (in_array($my_name, $workers_list)) {
        $filter_worker = $my_name; // มี -> กรองชื่อตัวเอง
    } else {
        $filter_worker = ''; // ไม่มี -> แสดงทั้งหมด (Show All)
    }
}

// 🟢 2. [ส่วนที่เพิ่ม] นำค่า $filter_worker ไปสร้างเงื่อนไข SQL
if (!empty($filter_worker)) {
    // กรองทั้งคนบันทึก (reporter_name) หรือ สมาชิกทีม (team_member)
    $base_clauses[] = "(wp.team_member = ? OR ( (wp.team_member IS NULL OR wp.team_member = '') AND wp.reporter_name = ? ))";
    $base_params[] = $filter_worker;
    $base_params[] = $filter_worker;
    $base_types .= "ss";
}

$where_cond = !empty($base_clauses) ? implode(" AND ", $base_clauses) : "1=1";



// 🟢 2. ยิง Query นับจำนวน (ใช้ $where_cond แทนการ implode สด)
$status_counts = [];
$total_jobs = 0;

$sql_count = "SELECT 
                CASE 
                    WHEN wp.summary IS NULL OR wp.summary = '' THEN 0 
                    ELSE wp.status_id 
                END as computed_status_id, 
                COUNT(*) as total 
              FROM work_plans wp 
              WHERE $where_cond 
              GROUP BY computed_status_id";

if ($stmt = $conn->prepare($sql_count)) {
    // ตรวจสอบว่ามี Params ไหม ถ้ามีค่อย Bind (ถ้าโชว์ทั้งหมด $base_params จะว่าง)
    if (!empty($base_params)) {
        $stmt->bind_param($base_types, ...$base_params);
    }
    $stmt->execute();
    $res_count = $stmt->get_result();
    while ($row_c = $res_count->fetch_assoc()) {
        $status_counts[$row_c['computed_status_id']] = $row_c['total'];
        $total_jobs += $row_c['total'];
    }
    $stmt->close();
}

// ---------------------------------------------------------
// 🟢 3. อย่าลืมแก้ในส่วน Query หลัก (Main Query) 
// ---------------------------------------------------------

// สร้างเงื่อนไขหลัก (ถ้ามี filter_status ให้เอาไปบวกเพิ่ม)
$main_clauses = $base_clauses;
$main_params = $base_params;
$main_types = $base_types;

if ($filter_status !== '') {
    if ($filter_status == '0') {
        $main_clauses[] = "(wp.summary IS NULL OR wp.summary = '')";
    } else {
        $main_clauses[] = "wp.status_id = ? AND wp.summary != ''";
        $main_params[] = $filter_status;
        $main_types .= "i";
    }
}

$final_where = !empty($main_clauses) ? implode(" AND ", $main_clauses) : "1=1";

$sql = "SELECT wp.*, c.company_shortname, ms.status_name, ms.id as master_status_id 
        FROM work_plans wp
        LEFT JOIN companies c ON wp.company = c.company_name COLLATE utf8mb4_general_ci
        LEFT JOIN master_job_status ms ON wp.status_id = ms.id
        WHERE $final_where
        ORDER BY wp.plan_date DESC";

$plans = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($main_params)) {
        $stmt->bind_param($main_types, ...$main_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }
    $stmt->close();
}

function thaiMonth($m)
{
    $thai_months = ['01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.', '05' => 'พ.ค.', '06' => 'มิ.ย.', '07' => 'ก.ค.', '08' => 'ส.ค.', '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'];
    return $thai_months[$m];
}

// --- 🟢 ส่วน AJAX: ส่งข้อมูลกลับเป็น JSON (เวอร์ชันอัปเกรด: แสดงคนบันทึก + แก้สถานะตกบรรทัด) ---
// --- 🟢 ส่วน AJAX: ส่งข้อมูลกลับเป็น JSON (ฉบับสมบูรณ์ที่สุด) ---
if (isset($_GET['ajax'])) {
    // ล้าง Buffer ก่อนเพื่อป้องกัน Error ปนเปื้อน
    if (ob_get_length())
        ob_clean();
    ob_start();

    if (count($plans) > 0) {
        foreach ($plans as $row) {
            // Logic การแสดงผลแต่ละแถว (ดึงมาจากโค้ดเดิมของคุณ)
            $d_display = date('d/m/Y', strtotime($row['plan_date']));
            $worker = !empty($row['team_member']) ? $row['team_member'] : $row['reporter_name'];
            $hasSummary = !empty($row['summary']);

            if ($hasSummary && !empty($row['status_name'])) {
                $showStatus = $row['status_name'];
                $statusIdColor = $row['master_status_id'];
            } else {
                $showStatus = $row['status'] ?? 'Plan';
                $statusIdForColor = 999;
                // Fix bug: statusIdForColor variable name consistency
                $statusIdColor = 999;
            }

            $themeColor = getStatusThemeColor($showStatus, $statusIdColor);
            $statusPillStyle = "display: inline-block; white-space: nowrap; background: $themeColor; color: white; border-radius: 6px; padding: 4px 12px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.2); text-align: center; min-width: 100px;";

            $summary_by_html = "";
            if ($hasSummary && !empty($row['summary_by'])) {
                $summary_by_html = "<div class='small text-muted mt-1' style='font-size: 10px; line-height: 1.2;'><i class='fas fa-user-edit me-1'></i>{$row['summary_by']}</div>";
            }

            // 🟢 [จุดที่ต้องแก้] เพิ่ม 3 บรรทัดนี้เพื่อลบ Enter ออกจากข้อความ
            $raw_summary = $row['summary'] ?? '';
            $safe_summary = htmlspecialchars($raw_summary, ENT_QUOTES, 'UTF-8');
            $safe_summary = preg_replace("/\r|\n/", " ", $safe_summary);
            // --------------------------------------------------------

            $current_status_id = (int) ($row['status_id'] ?? 0);

            // แสดง HTML ของแถว
            echo "<tr>
                    <td><span class='fw-bold text-primary'>$d_display</span></td>
                    <td>" . (($row['team_type'] == 'Auction') ? '<span class="badge bg-warning text-dark rounded-pill">ทีมประมูล</span>' : '<span class="badge bg-info text-dark rounded-pill">การตลาด</span>') . "</td>
                    <td><small class='text-muted'>{$row['reporter_name']}</small></td>
                    <td>
                        <div class='fw-bold text-dark'>$worker</div>
                        <div class='small text-muted fw-normal'><i class='fas fa-building me-1'></i>{$row['company_shortname']}</div>
                    </td>
                    <td>{$row['contact_person']}</td>
                    <td><div class='text-truncate text-muted' style='max-width: 150px;'>{$row['work_detail']}</div></td>
                    <td class='text-center'>
                        <button class='btn btn-sm btn-light border text-success shadow-sm' 
                                onclick=\"openSummaryModal({$row['id']}, '$safe_summary', $current_status_id)\">
                            <i class='fas " . ($hasSummary ? 'fa-check-double' : 'fa-plus') . "'></i> " . ($hasSummary ? 'สรุปแล้ว' : 'บันทึกผล') . "
                        </button>
                        $summary_by_html
                    </td>
                    <td class='text-center'>
                        <span class='status-pill' style='$statusPillStyle'>$showStatus</span>
                    </td>
                    <td class='text-center'>
                        " . ($_SESSION['fullname'] == $row['reporter_name'] ? "
                            <a href='work_plan_add.php?edit_id={$row['id']}' class='text-warning me-2'><i class='fas fa-pen'></i></a>
                            <a href='#' onclick='confirmDelete({$row['id']})' class='text-danger'><i class='fas fa-trash'></i></a>
                        " : "") . "
                    </td>
                  </tr>";
        }
    } else {
        echo '<tr><td colspan="9" class="text-center py-5 text-muted">ไม่พบข้อมูลแผนงานในช่วงวันที่เลือก</td></tr>';
    }

    $table_html = ob_get_clean();

    // 🟢 สร้าง HTML การ์ดใหม่ส่งกลับไปด้วย (เรียกใช้ฟังก์ชันที่สร้างไว้)
    $grid_html = renderStatusGrid($status_list, $status_counts, $total_jobs, $filter_status);

    header('Content-Type: application/json');
    echo json_encode([
        'total_jobs' => (int) $total_jobs,
        'status_counts' => $status_counts,
        'plans_count' => count($plans),
        'html_content' => $table_html,
        'grid_html' => $grid_html // ส่ง HTML การ์ดกลับไป
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการแผนงาน - Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/work_plan_dashboard.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/th.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="page-header">
            <div class="page-title">
                <div class="icon-wrapper"><i class="fas fa-calendar-alt"></i></div>
                <div>แผนงานประจำเดือน</div>
            </div>
            <a href="work_plan_add.php" class="btn-add">
                <i class="fas fa-plus-circle"></i> <span>เพิ่มแผนงานใหม่</span>
            </a>
        </div>

        <div class="status-grid">

            <div class="status-card <?php echo empty($filter_status) ? 'active' : ''; ?>" onclick="selectStatus('')"
                style="--theme-color: #6366f1; animation-delay: 0ms;">

                <div class="d-flex flex-column position-relative z-1">
                    <span class="sc-count"><?php echo $total_jobs; ?></span>
                    <span class="sc-label">งานทั้งหมด</span>
                </div>
                <i class="fas fa-layer-group sc-icon"></i>

                <?php if (empty($filter_status)): ?>
                    <div
                        style="position: absolute; top: 15px; right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                        <i class="fas fa-check-circle fa-xl"></i>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            // ตัวแปรสำหรับหน่วงเวลาอนิเมชั่น (เพิ่มทีละ 100ms)
            $delay = 100;

            // 2. การ์ด Plan (รอสรุป)
            $planCount = $status_counts[0] ?? 0;
            if ($planCount > 0 || $filter_status === '0'):
                $isActivePlan = ($filter_status === '0');
                ?>
                <div class="status-card <?php echo $isActivePlan ? 'active' : ''; ?>" onclick="selectStatus('0')"
                    style="--theme-color: #06b6d4; animation-delay: <?php echo $delay; ?>ms;">

                    <div class="d-flex flex-column position-relative z-1">
                        <span class="sc-count"><?php echo $planCount; ?></span>
                        <span class="sc-label">Plan (รอสรุป)</span>
                    </div>
                    <i class="fas fa-clipboard-list sc-icon"></i>

                    <?php if ($isActivePlan): ?>
                        <div
                            style="position: absolute; top: 15px; right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                            <i class="fas fa-check-circle fa-xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
                $delay += 100; // เพิ่มเวลาดีเลย์ให้ใบต่อไป
            endif;
            ?>

            <?php foreach ($status_list as $st):
                $count = $status_counts[$st['id']] ?? 0;
                $isActive = ($filter_status == $st['id']);
                if ($count == 0 && !$isActive)
                    continue;

                $themeColor = getStatusThemeColor($st['status_name'], $st['id']);

                // Icon Mapping
                $icon = 'fa-circle';
                if (strpos($st['status_name'], 'ไม่ได้งาน') !== false || strpos($st['status_name'], 'ยกเลิก') !== false) {
                    $icon = 'fa-circle-xmark'; // ❌ เปลี่ยนเป็นกากบาท X
                } else if (strpos($st['status_name'], 'ได้งาน') !== false || strpos($st['status_name'], 'สำเร็จ') !== false) {
                    $icon = 'fa-trophy';
                } else if (strpos($st['status_name'], 'เสนอ') !== false) {
                    $icon = 'fa-file-contract';
                } else if (strpos($st['status_name'], 'ติดตาม') !== false) {
                    $icon = 'fa-clock';
                }
                ?>
                <div class="status-card <?php echo $isActive ? 'active' : ''; ?>"
                    onclick="selectStatus('<?php echo $st['id']; ?>')"
                    style="--theme-color: <?php echo $themeColor; ?>; animation-delay: <?php echo $delay; ?>ms;">

                    <div class="d-flex flex-column position-relative z-1">
                        <span class="sc-count"><?php echo $count; ?></span>
                        <span class="sc-label"><?php echo $st['status_name']; ?></span>
                    </div>
                    <i class="fas <?php echo $icon; ?> sc-icon"></i>

                    <?php if ($isActive): ?>
                        <div
                            style="position: absolute; top: 15px; right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                            <i class="fas fa-check-circle fa-xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
                $delay += 100; // ใบต่อไปช้าลงอีกนิด ให้ดูเป็นคลื่น
            endforeach;
            ?>

        </div>

        <form method="GET" class="filter-card" id="filterForm">
            <div>
                <label class="form-label-sm">วันที่เริ่ม</label>
                <input type="text" name="start_date" id="start_date" class="form-control form-select-custom datepicker"
                    placeholder="วว/ดด/ปปปป" value="<?php echo htmlspecialchars($start_date); ?>" readonly>
            </div>
            <div>
                <label class="form-label-sm">ถึงวันที่</label>
                <input type="text" name="end_date" id="end_date" class="form-control form-select-custom datepicker"
                    placeholder="วว/ดด/ปปปป" value="<?php echo htmlspecialchars($end_date); ?>" readonly>
            </div>

            <div>
                <label class="form-label-sm">ประเภททีม</label>
                <select name="filter_team" class="form-select form-select-custom">
                    <option value="">-- ทั้งหมด --</option>
                    <option value="Marketing" <?php if ($filter_team == 'Marketing')
                        echo 'selected'; ?>>การตลาด (เดี่ยว)
                    </option>
                    <option value="Auction" <?php if ($filter_team == 'Auction')
                        echo 'selected'; ?>>ประมูล (ทีม)</option>
                </select>
            </div>

            <div>
                <label class="form-label-sm text-primary">ผู้ปฏิบัติงาน (Worker)</label>
                <select name="filter_worker" class="form-select form-select-custom"
                    style="min-width: 180px; border-color: #bfdbfe;">
                    <option value="">-- แสดงทุกคน --</option>
                    <?php foreach ($workers_list as $wk): ?>
                        <option value="<?php echo $wk; ?>" <?php if ($filter_worker == $wk)
                               echo 'selected'; ?>>
                            <?php echo $wk; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label-sm">ผู้บันทึก</label>
                <select name="filter_user" class="form-select form-select-custom" style="min-width: 180px;">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach ($reporters_list as $rep): ?>
                        <option value="<?php echo $rep; ?>" <?php if ($filter_user == $rep)
                               echo 'selected'; ?>>
                            <?php echo $rep; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" name="filter_status" id="filter_status_input"
                value="<?php echo htmlspecialchars($filter_status); ?>">

            <button type="submit" class="btn-search"><i class="fas fa-search me-1"></i> ค้นหา</button>

            <button type="button" id="btnClear" class="btn btn-light border-0 shadow-sm"
                style="border-radius: 10px; padding: 10px 20px;">
                <i class="fas fa-undo me-1"></i> ล้างค่า
            </button>

            <button type="button" onclick="openExportModal()" class="btn btn-success text-white shadow-sm"
                style="border-radius: 10px; padding: 10px 20px; background-color: #10b981; border-color: #10b981;">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </button>
        </form>

        <div class="table-card">
            <div class="table-responsive" style="overflow-x: visible;">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th width="8%">วันที่เเพลนงาน</th>
                            <th width="8%">ประเภท</th>
                            <th width="12%">ผู้บันทึก</th>
                            <th width="15%">ผู้ปฏิบัติงาน</th>
                            <th width="15%">ลูกค้า/หน่วยงาน</th>
                            <th>รายละเอียด</th>
                            <th width="10%">สรุปผล</th>
                            <th width="10%">สถานะ</th>
                            <th width="8%" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($plans) > 0): ?>
                            <?php foreach ($plans as $row):
                                $d = date('d/m/Y', strtotime($row['plan_date']));
                                $worker = !empty($row['team_member']) ? $row['team_member'] : $row['reporter_name'];
                                $hasSummary = !empty($row['summary']);

                                // 🟢 [1. เพิ่มส่วนนี้] จัดการข้อความสรุป: ลบ Enter ออก เพื่อไม่ให้ JS พัง
                                $raw_summary = $row['summary'] ?? '';
                                $safe_summary = htmlspecialchars($raw_summary, ENT_QUOTES, 'UTF-8');
                                $safe_summary = preg_replace("/\r|\n/", " ", $safe_summary); // เปลี่ยนบรรทัดใหม่เป็นวรรคตอน
                                // -----------------------------------------------------------
                        
                                if ($hasSummary && !empty($row['status_name'])) {
                                    $showStatus = $row['status_name'];
                                    $statusIdForColor = $row['master_status_id'];
                                } else {
                                    $showStatus = $row['status'];
                                    $statusIdForColor = 999;
                                }

                                $themeColor = getStatusThemeColor($showStatus, $statusIdForColor);
                                $statusPillStyle = "background: $themeColor; color: white; border-radius: 6px; padding: 4px 10px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.2);";
                                ?>
                                <tr>
                                    <td><span class="fw-bold text-primary"><?php echo $d; ?></span></td>
                                    <td><?php echo ($row['team_type'] == 'Auction') ? '<span class="badge bg-warning text-dark rounded-pill">ทีมประมูล</span>' : '<span class="badge bg-info text-dark rounded-pill">การตลาด</span>'; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo $row['reporter_name']; ?></small></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo $worker; ?></div>
                                        <div class="small text-muted fw-normal"><i class="fas fa-building me-1"></i>
                                            <?php echo $row['company_shortname']; ?></div>
                                    </td>
                                    <td><?php echo $row['contact_person']; ?></td>
                                    <td>
                                        <div class="text-truncate text-muted" style="max-width: 150px;">
                                            <?php echo $row['work_detail']; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <button class="btn btn-sm btn-light border mt-1 text-success"
                                            onclick="openSummaryModal(<?php echo $row['id']; ?>, '<?php echo $safe_summary; ?>', <?php echo (int) $row['status_id']; ?>)">
                                            <i class="fas <?php echo $hasSummary ? 'fa-check-double' : 'fa-plus'; ?>"></i>
                                            <?php echo $hasSummary ? 'สรุปแล้ว' : 'บันทึกผล'; ?>
                                        </button>

                                        <?php if ($hasSummary && !empty($row['summary_by'])): ?>
                                            <div class="small text-muted mt-1" style="font-size: 10px;">
                                                <i class="fas fa-user-edit me-1"></i><?php echo $row['summary_by']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="status-pill" style="<?php echo $statusPillStyle; ?>">
                                            <?php echo $showStatus; ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($_SESSION['fullname'] == $row['reporter_name']): ?>
                                            <a href="work_plan_add.php?edit_id=<?php echo $row['id']; ?>" class="text-warning me-2">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <a href="#" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="text-danger">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted bg-white">
                                    <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i><br>ไม่พบข้อมูลแผนงาน
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="summaryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="summaryForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-check me-2"></i> สรุปผลการเข้าพบ
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="action" value="save_summary">
                    <input type="hidden" name="plan_id" id="modal_plan_id">

                    <div class="mb-4">
                        <label class="form-label fw-bold text-primary">อัปเดตสถานะงาน</label>
                        <select name="status_id" id="modal_status_id" class="form-select form-select-lg">
                            <?php foreach ($status_list as $st): ?>
                                <option value="<?php echo $st['id']; ?>"><?php echo $st['status_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">รายละเอียดสรุปผล</label>
                        <textarea name="summary" id="modal_summary" class="form-control" rows="5"
                            placeholder="เช่น ลูกค้าสนใจ, นัดคุยรอบหน้า, ปิดการขาย..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm">บันทึกผล</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-file-excel me-2"></i> ส่งออกข้อมูล (Export Excel)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="exportForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">ช่วงวันที่แผนงาน</label>
                            <div class="input-group">
                                <input type="text" id="ex_start_date" class="form-control datepicker"
                                    placeholder="เลือกวันที่เริ่มต้น" value="">

                                <span class="input-group-text bg-light">ถึง</span>

                                <input type="text" id="ex_end_date" class="form-control datepicker"
                                    placeholder="เลือกวันที่สิ้นสุด" value="">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภททีม</label>
                            <select id="ex_type" class="form-select">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="Marketing">การตลาด</option>
                                <option value="Auction">ทีมประมูล</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ผู้ปฏิบัติงาน / ผู้บันทึก</label>
                            <select id="ex_worker" class="form-select">
                                <option value="">-- ทั้งหมด --</option>
                                <?php foreach ($workers_list as $wk): ?>
                                    <option value="<?php echo $wk; ?>"><?php echo $wk; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">สถานะงาน</label>
                            <select id="ex_status" class="form-select">
                                <option value="">-- ทุกสถานะ --</option>
                                <?php foreach ($status_list as $st): ?>
                                    <option value="<?php echo $st['id']; ?>">
                                        <?php echo $st['status_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" onclick="confirmExport()" class="btn btn-success fw-bold">
                        <i class="fas fa-download me-1"></i> ดาวน์โหลดไฟล์
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/work_plan_dashboard.js"></script>

    <?php
    // Show SweetAlert from Session
    if (isset($_SESSION['swal_msg'])) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '{$_SESSION['swal_msg']}',
                showConfirmButton: false,
                timer: 1500
            });
        </script>";
        unset($_SESSION['swal_msg']);
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // เช็คก่อนว่ามีตัวแปร flatpickr มาหรือยัง
            if (typeof flatpickr !== 'undefined') {
                flatpickr(".datepicker", {
                    dateFormat: "d/m/Y",
                    locale: "th",
                    allowInput: true
                });
            } else {
                console.error("❌ Flatpickr Library ยังไม่ถูกโหลด!");
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            flatpickr(".datepicker", {
                locale: "th",             // 🇹🇭 ตั้งค่าเป็นภาษาไทย
                dateFormat: "Y-m-d",      // 💾 ค่าจริงที่ส่งไป Database (เช่น 2026-03-01)
                altInput: true,           // ✅ เปิดโหมดแสดงผลต่างจากค่าจริง
                altFormat: "d/m/Y",       // 👀 รูปแบบที่โชว์ให้ตาเห็น (เช่น 01/03/2026)
                allowInput: true,         // อนุญาตให้พิมพ์วันที่เองได้
                disableMobile: "true"     // บังคับใช้หน้าตา Flatpickr บนมือถือ (เพื่อให้ฟอร์แมตไม่เพี้ยน)
            });
        });
    </script>
</body>

</html>