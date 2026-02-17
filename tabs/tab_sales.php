<?php
/**
 * tab_sales.php - Sales Personal History (Dashboard Matching Version)
 * ปรับ Class และโครงสร้างให้ใช้ dashboard_style.css 100%
 */

if (!isset($conn))
    require_once 'db_connect.php';

// --- LINK CSS (เพื่อให้ใช้ Class จากไฟล์ Dashboard ได้) ---
echo '<link rel="stylesheet" href="css/dashboard_style.css">';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">';

// --- CONFIG & FILTER ---
$table_name = 'reports';
$upload_path = 'uploads/';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// ✅ 1. กรองเฉพาะงานของฉัน
$my_name = $_SESSION['fullname'] ?? '';
$where_sql = "WHERE reporter_name = '$my_name' AND report_date BETWEEN '$start_date' AND '$end_date'";

// Filter Status
$filter_status = $_GET['filter_status'] ?? '';
if (!empty($filter_status)) {
    $filter_status = $conn->real_escape_string($filter_status);
    $where_sql .= ($filter_status == 'ได้งาน')
        ? " AND (job_status LIKE '%ได้งาน%' AND job_status NOT LIKE '%ไม่ได้งาน%')"
        : " AND job_status LIKE '%$filter_status%'";
}

// Search Keyword
$search_keyword = $_GET['keyword'] ?? '';
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_sql .= " AND (work_result LIKE '%$search_keyword%' OR project_name LIKE '%$search_keyword%')";
}

// --- DATA PREPARATION ---
$status_counts = [];
$total_expense = 0;
$total_reports = 0;
$rows_buffer = [];

$sql_list = "SELECT * FROM $table_name $where_sql ORDER BY report_date DESC, id DESC";
$result_list = $conn->query($sql_list);

if ($result_list) {
    while ($row = $result_list->fetch_assoc()) {

        // 🟢 แก้ไข: คำนวณค่าน้ำมันใหม่ (ระเบิดคอมม่าแล้วบวก)
        $fuel_sum = 0;
        if (!empty($row['fuel_cost'])) {
            // แยกด้วยคอมม่า (,) แล้ววนลูปบวกทีละตัว
            foreach (explode(',', $row['fuel_cost']) as $fc) {
                $fuel_sum += (float) trim($fc);
            }
        }

        // 🟢 รวมยอดสุทธิใหม่ (ใช้น้ำมันที่บวกครบแล้ว + ที่พัก + อื่นๆ)
        $row['total_expense_calc'] = $fuel_sum + (float) ($row['accommodation_cost'] ?? 0) + (float) ($row['other_cost'] ?? 0);

        // ส่วนนับสถานะงาน (เหมือนเดิม)
        $raw_status = $row['job_status'] ?: 'ไม่ระบุ';
        foreach (explode(',', $raw_status) as $st) {
            $st = trim($st);
            if ($st != '-' && !empty($st)) {
                $status_counts[$st] = ($status_counts[$st] ?? 0) + 1;
            }
        }

        $total_expense += $row['total_expense_calc'];
        $total_reports++;
        $rows_buffer[] = $row;
    }
}

// ✅ Helper functions (Sync ระบบสี และไอคอน Dashboard)
function getCardConfig($status)
{
    $status = trim($status);
    // 1. ล็อคสีหลัก (แดง, เขียว, ฟ้า, เหลือง)
    if (preg_match('/ไม่ได้|ยกเลิก|แพ้/', $status))
        return ['color' => '#ef4444', 'icon' => 'fa-circle-xmark'];
    if (preg_match('/ได้งาน|สำเร็จ|เรียบร้อย/', $status))
        return ['color' => '#10b981', 'icon' => 'fa-circle-check'];
    if (preg_match('/เสนอ|เข้าพบ|ประมูล/', $status))
        return ['color' => '#3b82f6', 'icon' => 'fa-briefcase-clock'];
    if (preg_match('/ติดตาม|รอ|นัดหมาย/', $status))
        return ['color' => '#f59e0b', 'icon' => 'fa-hourglass-half'];

    // 🟢 2. สูตรเจนสีอัตโนมัติ (ปรับให้ตรงกับ JS)
    // ใช้ unpack('C*', ...) เพื่อดึงค่า Bytes ของ UTF-8
    $bytes = unpack('C*', $status);
    $sum = array_sum($bytes);

    // ใช้สูตรเดียวกัน: (ผลรวม bytes * 157) Mod 360
    $hue = ($sum * 157) % 360;

    // ตั้งค่า Saturation 70% และ Lightness 40% (เพื่อให้สีเข้มอ่านง่าย)
    return ['color' => "hsl($hue, 70%, 40%)", 'icon' => 'fa-tags'];
}

function hexToRgba($hex, $alpha = 0.1)
{
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r, $g, $b, $alpha)";
    }
    return "rgba(0,0,0,$alpha)";
}
?>

<div class="kpi-grid">
    <div class="kpi-card" onclick="filterByStatus('')" style="border-left: 5px solid #64748b;">
        <div class="kpi-label" style="color:#64748b;">รายงานทั้งหมดของฉัน</div>
        <div class="kpi-value"> <?= number_format($total_reports) ?> </div>
        <i class="fa-solid fa-file-signature kpi-icon"></i>
    </div>

    <?php foreach ($status_counts as $st => $cnt):
        $cfg = getCardConfig($st); ?>
        <div class="kpi-card" onclick="filterByStatus('<?= $st ?>')" style="border-left: 5px solid <?= $cfg['color'] ?>;">
            <div class="kpi-label" style="color:<?= $cfg['color'] ?>;"><?= $st ?></div>
            <div class="kpi-value" style="color:<?= $cfg['color'] ?>;"><?= number_format($cnt) ?></div>
            <i class="fa-solid <?= $cfg['icon'] ?> kpi-icon" style="color:<?= $cfg['color'] ?>;"></i>
        </div>
    <?php endforeach; ?>

    <div class="kpi-card" style="border-left: 5px solid #f97316; cursor: default;">
        <div class="kpi-label" style="color:#f97316;">ยอดเบิกสะสมรวม</div>
        <div class="kpi-value" style="color:#f97316;">฿<?= number_format($total_expense, 2) ?></div>
        <i class="fa-solid fa-sack-dollar kpi-icon"></i>
    </div>
</div>

<div class="filter-section">
    <form class="filter-form" method="GET" id="salesFilterForm">
        <input type="hidden" name="tab" value="sales">
        <input type="hidden" name="filter_status" id="hiddenStatusInput"
            value="<?= htmlspecialchars($filter_status) ?>">

        <div class="form-group" style="flex: 2;">
            <label class="form-label">ค้นหาลูกค้า / โครงการ</label>
            <input type="text" name="keyword" value="<?= htmlspecialchars($search_keyword) ?>" class="form-control"
                placeholder="ระบุชื่อลูกค้า หรือ โครงการ...">
        </div>
        <div class="form-group">
            <label class="form-label">เริ่มวันที่</label>
            <div style="position: relative;">
                <input type="text" name="start_date" value="<?= $start_date ?>" class="form-control datepicker"
                    placeholder="เลือกวันที่...">
                <i class="fa-regular fa-calendar"
                    style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none;"></i>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">ถึงวันที่</label>
            <div style="position: relative;">
                <input type="text" name="end_date" value="<?= $end_date ?>" class="form-control datepicker"
                    placeholder="เลือกวันที่...">
                <i class="fa-regular fa-calendar"
                    style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none;"></i>
            </div>
        </div>
        <div class="button-group">
            <button type="submit" class="btn-search"> ค้นหา</button>
            <a href="StaffHistory.php?tab=sales" class="btn-reset" title="รีเซ็ต"><i
                    class="fa-solid fa-rotate-left"></i></a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:140px;">วันที่/เวลา</th>
                    <th>ลูกค้า / หน่วยงาน</th>
                    <th>โครงการ</th>
                    <th style="width:160px;">สถานะ</th>
                    <th style="width:120px; text-align:right;">ค่าเบิก</th>
                    <th style="width:140px; text-align:center;">หลักฐาน</th>
                    <th style="width:80px; text-align:center;">ดู</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows_buffer)):
                    foreach ($rows_buffer as $row):
                        $customers = explode(',', $row['work_result']);
                        $projects = explode(',', $row['project_name']);
                        $st_list = explode(',', $row['job_status']);
                        $max_rows = max(count($customers), count($projects), count($st_list));
                        $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td style="vertical-align: top; padding-top:15px;">
                                <div style="font-weight:700; color:var(--text-main);">
                                    <?= date('d/m/Y', strtotime($row['report_date'])) ?>
                                </div>
                                <div style="font-size:12px; color:var(--text-sub); margin-top:4px;"><i
                                        class="fa-regular fa-clock me-1"></i> <?= date('H:i', strtotime($row['created_at'])) ?>
                                    น.</div>
                            </td>

                            <td style="padding:0; vertical-align:top;">
                                <?php for ($i = 0; $i < $max_rows; $i++):
                                    $border = ($i < $max_rows - 1) ? 'border-bottom: 1px dashed var(--border-color);' : ''; ?>
                                    <div style="padding:12px 15px; <?= $border ?>">
                                        <span class="customer-link"
                                            onclick="showCustomerHistory('<?= htmlspecialchars(trim($customers[$i] ?? '-'), ENT_QUOTES) ?>')">
                                            <?= trim($customers[$i] ?? '-') ?>
                                        </span>
                                    </div>
                                <?php endfor; ?>
                            </td>

                            <td style="padding:0; vertical-align:top;">
                                <?php for ($i = 0; $i < $max_rows; $i++):
                                    $border = ($i < $max_rows - 1) ? 'border-bottom: 1px dashed var(--border-color);' : ''; ?>
                                    <div style="padding:12px 15px; <?= $border ?> color:var(--text-sub); font-size:13px;">
                                        <?= trim($projects[$i] ?? '-') ?>
                                    </div>
                                <?php endfor; ?>
                            </td>

                            <td style="padding:0; vertical-align:top;">
                                <?php for ($i = 0; $i < $max_rows; $i++):
                                    $st = trim($st_list[$i] ?? (count($st_list) == 1 ? $st_list[0] : '-'));
                                    $sc = getCardConfig($st);
                                    $border = ($i < $max_rows - 1) ? 'border-bottom: 1px dashed var(--border-color);' : '';
                                    ?>
                                    <div style="padding:10px 15px; <?= $border ?>">
                                        <span class="status-badge"
                                            style="background:<?= hexToRgba($sc['color'], 0.12) ?>; color:<?= $sc['color'] ?>; border:1px solid <?= hexToRgba($sc['color'], 0.2) ?>;">
                                            <i class="fa-solid <?= $sc['icon'] ?> me-1"></i> <?= $st ?>
                                        </span>
                                    </div>
                                <?php endfor; ?>
                            </td>

                            <td
                                style="text-align:right; font-weight:700; color:#ef4444; vertical-align: top; padding-top:15px;">
                                <?= number_format($row['total_expense_calc']) ?>
                            </td>

                            <td style="text-align:center; vertical-align: top; padding-top:12px;">
                                <div style="display:flex; justify-content:center; gap:6px; flex-wrap: wrap;">
                                    <?php
                                    $has_ev = false;
                                    if (!empty($row['fuel_receipt'])) {
                                        foreach (array_filter(explode(',', $row['fuel_receipt'])) as $idx => $f) {
                                            echo '<a href="' . $upload_path . trim($f) . '" target="_blank" class="btn-evidence ev-fuel" title="บิลน้ำมัน ' . ($idx + 1) . '"><i class="fa-solid fa-gas-pump"></i></a>';
                                            $has_ev = true;
                                        }
                                    }
                                    if (!empty($row['accommodation_receipt']) && $row['accommodation_receipt'] !== '0') {
                                        echo '<a href="' . $upload_path . $row['accommodation_receipt'] . '" target="_blank" class="btn-evidence ev-hotel" title="บิลที่พัก"><i class="fa-solid fa-hotel"></i></a>';
                                        $has_ev = true;
                                    }
                                    if (!empty($row['other_receipt']) && $row['other_receipt'] !== '0') {
                                        $other_title = (!empty($row['other_cost_detail'])) ? $row['other_cost_detail'] : 'บิลอื่นๆ';
                                        echo '<a href="' . $upload_path . $row['other_receipt'] . '" target="_blank" class="btn-evidence ev-other" title="' . htmlspecialchars($other_title) . '"><i class="fa-solid fa-receipt"></i></a>';
                                        $has_ev = true;
                                    }
                                    if (!$has_ev)
                                        echo '<span style="color:var(--text-sub); font-size:12px;">-</span>';
                                    ?>
                                </div>
                            </td>

                            <td style="text-align:center; vertical-align: top; padding-top:12px;">
                                <div style="display:flex; gap:8px; justify-content:center;">
                                    <button onclick='showDetail(<?= $row_json ?>, "SALES")' class="btn-view"
                                        title="เปิดดูรายละเอียด">
                                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                                    </button>

                                    <button onclick='openExpenseModal(<?= $row_json ?>)' class="btn-action-edit"
                                        title="อัปเดตค่าใช้จ่าย">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding:80px; color:var(--text-sub);">
                            ไม่พบประวัติการส่งรายงาน</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // ฟังก์ชันกกรองสถานะจาก KPI
    function filterByStatus(status) {
        const input = document.getElementById('hiddenStatusInput');
        if (input) {
            input.value = status;
            document.getElementById('salesFilterForm').submit();
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr(".datepicker", {
            locale: "th",              // ภาษาไทย
            dateFormat: "Y-m-d",       // ส่งค่าเข้า Database (2026-02-01)
            altInput: true,            // โชว์ให้ User เห็น
            altFormat: "d/m/Y",        // รูปแบบโชว์ (01/02/2026)
            disableMobile: "true",     // บังคับใช้ธีมสวยในมือถือด้วย
            allowInput: true           // พิมพ์เองได้
        });
    });
</script>