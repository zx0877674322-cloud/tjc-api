<?php
// work_plan_dashboard.php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

$current_user = $_SESSION['fullname'] ?? $_SESSION['username'];

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

// --- Logic Save Summary ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_summary') {
    $plan_id = intval($_POST['plan_id']);
    $summary = trim($_POST['summary']);
    $status_id = intval($_POST['status_id']);

    $stmt = $conn->prepare("UPDATE work_plans SET summary = ?, status_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $summary, $status_id, $plan_id);
    $success = $stmt->execute();

    // 🟢 [เพิ่มส่วนนี้] ถ้าเป็น AJAX ให้ส่ง JSON กลับแล้วหยุดทำงานทันที
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => $success]);
        exit; // สำคัญมาก ห้ามเอาออก
    }

    // ส่วนเดิม (กรณีไม่ได้ใช้ AJAX)
    header("Location: work_plan_dashboard.php");
    exit();
}

// --- Logic Delete ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $sql_del = "DELETE FROM work_plans WHERE id = ? AND reporter_name = ?";
    if ($stmt = $conn->prepare($sql_del)) {
        $stmt->bind_param("is", $del_id, $current_user);
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
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$filter_team = $_GET['filter_team'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$filter_worker = $_GET['filter_worker'] ?? '';

// 🟢 1. สร้างเงื่อนไข "พื้นฐาน" (Base Clauses) 
// (ใช้สำหรับนับจำนวนการ์ดด้วยเงื่อนไขนี้ โดย *ไม่รวม* สถานะ เพื่อให้เห็นภาพรวม)
$base_clauses = ["MONTH(wp.plan_date) = ?", "YEAR(wp.plan_date) = ?"];
$base_params = [$month, $year];
$base_types = "ss";

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
if (!empty($filter_worker)) {
    $base_clauses[] = "(wp.team_member = ? OR ( (wp.team_member IS NULL OR wp.team_member = '') AND wp.reporter_name = ? ))";
    $base_params[] = $filter_worker;
    $base_params[] = $filter_worker;
    $base_types .= "ss";
}

// 🟢 2. ยิง Query นับจำนวน (แก้ Logic ให้ตรงกับตารางเป๊ะๆ)
$status_counts = [];
$total_jobs = 0;

// ใช้ CASE WHEN: ถ้า Summary ว่าง -> ให้นับเป็น ID 0 (Plan/รอสรุป) 
// ถ้าไม่ว่าง -> ให้นับตาม status_id จริงๆ
$sql_count = "SELECT 
                CASE 
                    WHEN wp.summary IS NULL OR wp.summary = '' THEN 0 
                    ELSE wp.status_id 
                END as computed_status_id, 
                COUNT(*) as total 
              FROM work_plans wp 
              WHERE " . implode(" AND ", $base_clauses) . " 
              GROUP BY computed_status_id";

if ($stmt = $conn->prepare($sql_count)) {
    if (!empty($base_params)) {
        $stmt->bind_param($base_types, ...$base_params);
    }
    $stmt->execute();
    $res_count = $stmt->get_result();
    while ($row_c = $res_count->fetch_assoc()) {
        $status_counts[$row_c['computed_status_id']] = $row_c['total'];
        $total_jobs += $row_c['total']; // รวมงานทั้งหมด
    }
    $stmt->close();
}

// 🟢 3. สร้าง Query หลักสำหรับแสดงตาราง (Main Query)
// เอาเงื่อนไขพื้นฐานมา + เงื่อนไขสถานะ (ถ้ามีการเลือก)
$main_clauses = $base_clauses;
$main_params = $base_params;
$main_types = $base_types;

if ($filter_status !== '') { // เช็คว่าไม่ว่าง (รองรับเลข 0)
    if ($filter_status == '0') {
        // 🟢 ถ้าเลือกดู Plan -> กรองเฉพาะที่ Summary ว่าง
        $main_clauses[] = "(wp.summary IS NULL OR wp.summary = '')";
    } else {
        // 🟢 ถ้าเลือกสถานะอื่น -> กรองตาม ID และต้องมี Summary แล้ว
        $main_clauses[] = "wp.status_id = ? AND wp.summary != ''";
        $main_params[] = $filter_status;
        $main_types .= "i";
    }
}

$sql = "SELECT wp.*, c.company_shortname, ms.status_name, ms.id as master_status_id 
        FROM work_plans wp
        LEFT JOIN companies c ON wp.company = c.company_name COLLATE utf8mb4_general_ci
        LEFT JOIN master_job_status ms ON wp.status_id = ms.id
        WHERE " . implode(" AND ", $main_clauses) . "
        ORDER BY wp.plan_date ASC";

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

if (isset($_GET['ajax'])) {
    // 1. ส่งข้อมูลการ์ด (Counts) และ ตาราง (Table Rows) กลับไปเป็น JSON
    ob_start();
    include 'work_plan_dashboard_rows.php'; // แยกไฟล์แสดงผลแถวตาราง (ถ้ามี) หรือเขียน Loop ตรงนี้
    $table_html = ob_get_clean();

    echo json_encode([
        'total_jobs' => $total_jobs,
        'status_counts' => $status_counts,
        'plans_count' => count($plans),
        // ส่วนนี้ส่ง HTML ของตารางและ Grid กลับไป
        'html_content' => $table_html
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการแผนงาน - Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/work_plan_dashboard.css">
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
                <label class="form-label-sm">เดือน</label>
                <select name="month" class="form-select form-select-custom">
                    <?php for ($i = 1; $i <= 12; $i++):
                        $m_val = sprintf('%02d', $i); ?>
                        <option value="<?php echo $m_val; ?>" <?php if ($month == $m_val)
                               echo 'selected'; ?>>
                            <?php echo thaiMonth($m_val); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label-sm">ปี</label>
                <select name="year" class="form-select form-select-custom">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if ($year == $y)
                               echo 'selected'; ?>><?php echo $y + 543; ?>
                        </option>
                    <?php endfor; ?>
                </select>
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
        </form>

        <div class="table-card">
            <div class="table-responsive" style="overflow-x: visible;">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th width="8%">วันที่</th>
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
                                $d = date('d/m', strtotime($row['plan_date']));
                                $worker = !empty($row['team_member']) ? $row['team_member'] : $row['reporter_name'];
                                $hasSummary = !empty($row['summary']);

                                // 🟢 [Logic] ถ้ามีสรุปงานเเล้ว -> ใช้สถานะใหม่ / ถ้ายัง -> ใช้ Plan
                                if ($hasSummary && !empty($row['status_name'])) {
                                    $showStatus = $row['status_name'];
                                    $statusIdForColor = $row['master_status_id'];
                                } else {
                                    $showStatus = $row['status']; // Plan
                                    $statusIdForColor = 999;
                                }

                                // 🟢 [เรียกใช้ฟังก์ชันใหม่] getStatusThemeColor
                                $themeColor = getStatusThemeColor($showStatus, $statusIdForColor);

                                // สร้าง Style สำหรับ Pill ในตาราง (พื้นหลังจางๆ สวยๆ)
                                $statusPillStyle = "background: $themeColor; color: white; border-radius: 6px; padding: 4px 10px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.2);";
                                ?>
                                <tr>
                                    <td><span class="fw-bold text-primary"><?php echo $d; ?></span></td>
                                    <td><?php echo ($row['team_type'] == 'Auction') ? '<span class="badge bg-warning text-dark rounded-pill">ทีม</span>' : '<span class="badge bg-info text-dark rounded-pill">เดี่ยว</span>'; ?>
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
                                            onclick="openSummaryModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['summary'] ?? ''); ?>', <?php echo $row['status_id']; ?>)">
                                            <i class="fas <?php echo $hasSummary ? 'fa-check-double' : 'fa-plus'; ?>"></i>
                                            <?php echo $hasSummary ? 'สรุปแล้ว' : 'บันทึกผล'; ?>
                                        </button>
                                    </td>

                                    <td>
                                        <span class="status-pill" style="<?php echo $statusPillStyle; ?>">
                                            <?php echo $showStatus; ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <a href="work_plan_add.php?edit_id=<?php echo $row['id']; ?>"
                                            class="text-warning me-2"><i class="fas fa-pen"></i></a>
                                        <a href="#" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="text-danger"><i
                                                class="fas fa-trash"></i></a>
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
</body>

</html>