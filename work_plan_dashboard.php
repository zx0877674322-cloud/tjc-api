<?php
// work_plan_dashboard.php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

$current_user = $_SESSION['fullname'] ?? $_SESSION['username'];

// --- Helper Function: Smart Color ---
function getStatusColorStyle($status_name, $status_id) {
    $status_name = trim($status_name);
    // 1. 🔴 เช็คสีแดง (ไม่ได้งาน)
    if (strpos($status_name, 'ไม่ได้งาน') !== false || strpos($status_name, 'ยกเลิก') !== false || $status_name == 'Cancelled') {
        return 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;';
    }
    // 2. 🟢 เช็คสีเขียว (ได้งาน)
    if (strpos($status_name, 'ได้งาน') !== false || strpos($status_name, 'สำเร็จ') !== false || $status_name == 'Completed') {
        return 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;';
    }
    // 3. 🔵 สีฟ้า (เสนอ)
    if (strpos($status_name, 'เสนอ') !== false || strpos($status_name, 'วางแผน') !== false || $status_name == 'Plan') {
        return 'background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe;';
    }
    // 4. 🟡 สีเหลือง (ติดตาม)
    if (strpos($status_name, 'ติดตาม') !== false || strpos($status_name, 'นัดหมาย') !== false || $status_name == 'Confirmed') {
        return 'background: #fef9c3; color: #854d0e; border: 1px solid #fde047;';
    }
    // 5. 🌈 สี Auto
    $hue = ($status_id * 137.508) % 360; 
    return "background: hsl($hue, 70%, 92%); color: hsl($hue, 80%, 25%); border: 1px solid hsl($hue, 60%, 80%);";
}

// --- Logic Save Summary ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_summary') {
    $plan_id = intval($_POST['plan_id']);
    $summary = trim($_POST['summary']);
    $status_id = intval($_POST['status_id']);
    
    $sql_update = "UPDATE work_plans SET summary = ?, status_id = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("sii", $summary, $status_id, $plan_id);
        $stmt->execute();
        $stmt->close();
        // SweetAlert Logic จะถูกเรียกผ่าน JS
        $_SESSION['swal_msg'] = "บันทึกสรุปงานเรียบร้อย";
        header("Location: work_plan_dashboard.php");
        exit();
    }
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
while($row = $q_status->fetch_assoc()) { $status_list[] = $row; }

$reporters_list = [];
$q_rep = $conn->query("SELECT DISTINCT reporter_name FROM work_plans ORDER BY reporter_name ASC");
while($r = $q_rep->fetch_assoc()) { $reporters_list[] = $r['reporter_name']; }

// --- Filter ---
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$filter_team = $_GET['filter_team'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';

$where_clauses = ["MONTH(wp.plan_date) = ?", "YEAR(wp.plan_date) = ?"];
$params = [$month, $year];
$types = "ss";

if (!empty($filter_user)) { $where_clauses[] = "wp.reporter_name = ?"; $params[] = $filter_user; $types .= "s"; }
if (!empty($filter_team)) { $where_clauses[] = "wp.team_type = ?"; $params[] = $filter_team; $types .= "s"; }
if (!empty($filter_status)) { $where_clauses[] = "wp.status_id = ?"; $params[] = $filter_status; $types .= "i"; }

$sql = "SELECT wp.*, c.company_shortname, ms.status_name, ms.id as master_status_id 
        FROM work_plans wp
        LEFT JOIN companies c ON wp.company = c.company_name COLLATE utf8mb4_general_ci
        LEFT JOIN master_job_status ms ON wp.status_id = ms.id
        WHERE " . implode(" AND ", $where_clauses) . "
        ORDER BY wp.plan_date ASC";

$plans = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $plans[] = $row; }
    $stmt->close();
}

function thaiMonth($m) {
    $thai_months = ['01'=>'ม.ค.', '02'=>'ก.พ.', '03'=>'มี.ค.', '04'=>'เม.ย.', '05'=>'พ.ค.', '06'=>'มิ.ย.', '07'=>'ก.ค.', '08'=>'ส.ค.', '09'=>'ก.ย.', '10'=>'ต.ค.', '11'=>'พ.ย.', '12'=>'ธ.ค.'];
    return $thai_months[$m];
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

        <form method="GET" class="filter-card">
            <div>
                <label class="form-label-sm">เดือน</label>
                <select name="month" class="form-select form-select-custom">
                    <?php for($i=1; $i<=12; $i++): $m_val = sprintf('%02d', $i); ?>
                        <option value="<?php echo $m_val; ?>" <?php if($month==$m_val) echo 'selected'; ?>><?php echo thaiMonth($m_val); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label-sm">ปี</label>
                <select name="year" class="form-select form-select-custom">
                    <?php for($y=date('Y'); $y>=2024; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($year==$y) echo 'selected'; ?>><?php echo $y+543; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label-sm">ประเภททีม</label>
                <select name="filter_team" class="form-select form-select-custom">
                    <option value="">-- ทั้งหมด --</option>
                    <option value="Marketing" <?php if($filter_team=='Marketing') echo 'selected'; ?>>การตลาด (เดี่ยว)</option>
                    <option value="Auction" <?php if($filter_team=='Auction') echo 'selected'; ?>>ประมูล (ทีม)</option>
                </select>
            </div>
            <div>
                <label class="form-label-sm">สถานะงาน</label>
                <select name="filter_status" class="form-select form-select-custom">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach($status_list as $st): ?>
                        <option value="<?php echo $st['id']; ?>" <?php if($filter_status==$st['id']) echo 'selected'; ?>>
                            <?php echo $st['status_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label-sm">ผู้บันทึก</label>
                <select name="filter_user" class="form-select form-select-custom" style="min-width: 180px;">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach($reporters_list as $rep): ?>
                        <option value="<?php echo $rep; ?>" <?php if($filter_user==$rep) echo 'selected'; ?>><?php echo $rep; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-search"><i class="fas fa-search me-1"></i> ค้นหา</button>
        </form>

        <div class="table-card">
            <div class="table-responsive" style="overflow-x: visible;"> <table class="table-custom">
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
                                $d = date('d', strtotime($row['plan_date']));
                                $compShort = $row['company_shortname'] ?? '-';
                                
                                // Team Type
                                $teamType = $row['team_type'] ?? 'Marketing';
                                if($teamType == 'Auction') {
                                    $typeBadge = '<span class="type-badge type-auc"><i class="fas fa-users"></i> ทีม</span>';
                                    $workerName = !empty($row['team_member']) ? $row['team_member'] : '-';
                                } else {
                                    $typeBadge = '<span class="type-badge type-mkt"><i class="fas fa-user"></i> เดี่ยว</span>';
                                    $workerName = $row['reporter_name'];
                                }

                                // Summary Button
                                $hasSummary = !empty($row['summary']);
                                $btnSummaryClass = $hasSummary ? 'btn-summary has-data' : 'btn-summary';
                                $btnSummaryText = $hasSummary ? '<i class="fas fa-check-circle"></i> สรุปแล้ว' : '<i class="fas fa-plus"></i> บันทึกผล';
                                
                                // Status Logic
                                if ($hasSummary && !empty($row['status_name'])) {
                                    $showStatus = $row['status_name'];
                                    $statusIdForColor = $row['master_status_id'];
                                } else {
                                    $showStatus = $row['status'];
                                    $statusIdForColor = 999;
                                }
                                $statusStyle = getStatusColorStyle($showStatus, $statusIdForColor);
                            ?>
                            <tr>
                                <td>
                                    <div class="date-badge">
                                        <span class="date-d"><?php echo $d; ?></span>
                                        <span class="date-m"><?php echo thaiMonth(date('m', strtotime($row['plan_date']))); ?></span>
                                    </div>
                                </td>
                                <td><?php echo $typeBadge; ?></td>
                                <td><div class="small text-muted fw-bold"><?php echo htmlspecialchars($row['reporter_name']); ?></div></td>
                                <td>
                                    <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($workerName); ?></div>
                                    <div class="badge bg-light text-secondary border mt-1"><i class="fas fa-building me-1"></i> <?php echo $compShort; ?></div>
                                </td>
                                <td><div class="fw-bold text-primary"><?php echo htmlspecialchars($row['contact_person']); ?></div></td>
                                <td><div class="small text-muted text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($row['work_detail']); ?></div></td>
                                
                                <td>
                                    <button class="<?php echo $btnSummaryClass; ?>" 
                                            onclick="openSummaryModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['summary'] ?? ''); ?>', <?php echo $row['status_id'] ?? 1; ?>)">
                                        <?php echo $btnSummaryText; ?>
                                    </button>
                                </td>

                                <td>
                                    <span class="status-pill" style="<?php echo $statusStyle; ?>">
                                        <?php echo htmlspecialchars($showStatus); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="work_plan_add.php?edit_id=<?php echo $row['id']; ?>" class="action-btn btn-edit" title="แก้ไข"><i class="fas fa-pen"></i></a>
                                        <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="action-btn btn-del" title="ลบ"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted bg-white">
                                <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i><br>ไม่พบข้อมูลแผนงาน
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="summaryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i> สรุปผลการเข้าพบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save_summary">
                        <input type="hidden" name="plan_id" id="modal_plan_id">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-primary">อัปเดตสถานะงาน</label>
                            <select name="status_id" id="modal_status_id" class="form-select form-select-lg">
                                <?php foreach($status_list as $st): ?>
                                    <option value="<?php echo $st['id']; ?>"><?php echo $st['status_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">รายละเอียดสรุปผล</label>
                            <textarea name="summary" id="modal_summary" class="form-control" rows="5" placeholder="เช่น ลูกค้าสนใจ, นัดคุยรอบหน้า, ปิดการขาย..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm">บันทึกผล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/work_plan_dashboard.js"></script>

    <?php 
    // Show SweetAlert from Session
    if(isset($_SESSION['swal_msg'])) {
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