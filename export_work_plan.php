<?php
// export_work_plan_excel.php

require_once 'db_connect.php'; 

// 1. รับค่าตัวแปรกรอง
$start_date = $_GET['start_date'] ?? ''; 
$end_date   = $_GET['end_date'] ?? '';   
$type       = $_GET['filter_team'] ?? $_GET['type'] ?? ''; 
$worker     = $_GET['filter_worker'] ?? $_GET['worker'] ?? '';    
$status     = $_GET['filter_status'] ?? $_GET['status'] ?? ''; 

$search     = $_GET['search'] ?? '';    

// 2. SQL Query
$sql = "SELECT p.*, ms.status_name 
        FROM work_plans p 
        LEFT JOIN master_job_status ms ON p.status_id = ms.id 
        WHERE 1=1 ";

$date_range_text = "ทั้งหมด (ทุกช่วงเวลา)"; 

// --- ฟังก์ชันแปลงวันที่สำหรับ SQL (Y-m-d) ---
function convertToSQLDate($dateStr) {
    if (empty($dateStr)) return false;
    // ลอง d/m/Y
    $d = DateTime::createFromFormat('d/m/Y', $dateStr);
    if ($d && $d->format('d/m/Y') == $dateStr) return $d->format('Y-m-d');
    // ลอง Y-m-d
    $d2 = DateTime::createFromFormat('Y-m-d', $dateStr);
    if ($d2 && $d2->format('Y-m-d') == $dateStr) return $d2->format('Y-m-d');
    return false;
}

// --- 🟢 ฟังก์ชันแปลงวันที่สำหรับแสดงผล (d/m/Y) ---
function formatDateForShow($dateYMD) {
    if (empty($dateYMD)) return "";
    $d = DateTime::createFromFormat('Y-m-d', $dateYMD);
    return $d ? $d->format('d/m/Y') : $dateYMD;
}

// --- การกรองวันที่ ---
$sql_start = convertToSQLDate($start_date);
$sql_end   = convertToSQLDate($end_date);

// 🟢 แก้ไขจุดแสดงผลหัวข้อรายงาน (ใช้ฟังก์ชันแปลงเป็น d/m/Y)
if ($sql_start && $sql_end) {
    $sql .= " AND p.plan_date BETWEEN '$sql_start' AND '$sql_end' ";
    $date_range_text = "วันที่ " . formatDateForShow($sql_start) . " ถึง " . formatDateForShow($sql_end);
} elseif ($sql_start) {
    $sql .= " AND p.plan_date >= '$sql_start' ";
    $date_range_text = "ตั้งแต่วันที่ " . formatDateForShow($sql_start) . " เป็นต้นไป";
} elseif ($sql_end) {
    $sql .= " AND p.plan_date <= '$sql_end' ";
    $date_range_text = "จนถึงวันที่ " . formatDateForShow($sql_end);
}

// กรองอื่นๆ
if (!empty($type)) $sql .= " AND p.team_type = '$type' ";
if (!empty($worker)) $sql .= " AND (p.reporter_name LIKE '%$worker%' OR p.team_member LIKE '%$worker%') ";
if ($status !== '') { 
    if ($status == '0') $sql .= " AND (p.status_id = 0 OR p.status_id IS NULL) ";
    else $sql .= " AND p.status_id = '$status' ";
}
if (!empty($search)) $sql .= " AND p.contact_person LIKE '%$search%' ";

$sql .= " ORDER BY p.plan_date ASC, p.id ASC";

$result = $conn->query($sql);

// 3. Header Excel
$filename = "WorkPlan_Export_" . date('Ymd_Hi') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; 
?>

<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table { border-collapse: collapse; width: 100%; font-family: 'Sarabun', 'Angsana New', sans-serif; }
        th { border: 1px solid #000; padding: 10px; background-color: #d1fae5; color: #065f46; font-weight: bold; font-size: 16px; white-space: nowrap; text-align: center; }
        td { border: 1px solid #ccc; padding: 8px; vertical-align: top; font-size: 14px; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .date-fmt { mso-number-format: "\@"; text-align: center; } 
    </style>
</head>
<body>
    <h3 style="text-align: center;">รายงานแผนงาน (Export Data)</h3>
    <p style="text-align: center;"><strong>ช่วงวันที่:</strong> <?php echo $date_range_text; ?></p>
    <br>
    
    <table>
        <thead>
            <tr>
                <th width="100">วันที่</th>
                <th width="100">ประเภท</th>
                <th width="150">ผู้บันทึก</th>
                <th width="150">ผู้ปฏิบัติงาน</th>
                <th width="200">ลูกค้า/หน่วยงาน</th>
                <th width="300">รายละเอียด</th>
                <th width="250">สรุปผล</th>
                <th width="150">ผู้สรุปงาน</th>
                <th width="120">สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($result && $result->num_rows > 0):
                $type_map = ['Marketing' => 'การตลาด', 'Auction' => 'ทีมประมูล'];

                while ($row = $result->fetch_assoc()):
                    $plan_date = date('d/m/Y', strtotime($row['plan_date']));
                    
                    $team_type = $row['team_type'];
                    $team_type_th = isset($type_map[$team_type]) ? $type_map[$team_type] : $team_type;
                    
                    if (!empty($row['status_name'])) {
                        $show_status = $row['status_name'];
                    } elseif ($row['status_id'] == 0 || empty($row['status_id'])) {
                        $show_status = 'Plan';
                    } else {
                        $show_status = !empty($row['status']) ? $row['status'] : 'Plan';
                    }

                    $work_detail = !empty($row['work_detail']) ? nl2br(htmlspecialchars($row['work_detail'])) : '-';
                    $summary = !empty($row['summary']) ? nl2br(htmlspecialchars($row['summary'])) : '-';
                    $summary_by = !empty($row['summary_by']) ? $row['summary_by'] : '-';
                    $worker_display = !empty($row['team_member']) ? $row['team_member'] : $row['reporter_name'];
                    $company = $row['company'];
                    $contact = !empty($row['contact_person']) ? $row['contact_person'] : '-';

                    $row_style = "";
                    if (strpos($show_status, 'ได้งาน') !== false || strpos($show_status, 'ปิดการขาย') !== false || strpos($show_status, 'สำเร็จ') !== false) {
                        $row_style = "background-color: #d1fae5; color: #065f46; font-weight:bold;";
                    } elseif (strpos($show_status, 'ยกเลิก') !== false || strpos($show_status, 'ไม่ได้งาน') !== false) {
                        $row_style = "background-color: #fee2e2; color: #b91c1c; font-weight:bold;";
                    } elseif ($show_status == 'Plan' || strpos($show_status, 'วางแผน') !== false) {
                        $row_style = "background-color: #ffffff; color: #0891b2; font-weight:bold;";
                    }
            ?>
                <tr>
                    <td class="date-fmt"><?php echo $plan_date; ?></td>
                    <td class="text-center"><?php echo $team_type_th; ?></td>
                    <td class="text-center"><?php echo $row['reporter_name']; ?></td>
                    <td class="text-center"><?php echo $worker_display; ?></td>
                    <td class="text-left">
                        <strong><?php echo $company; ?></strong><br>
                        <small>(<?php echo $contact; ?>)</small>
                    </td>
                    <td class="text-left"><?php echo $work_detail; ?></td>
                    <td class="text-left"><?php echo $summary; ?></td>
                    <td class="text-center"><?php echo $summary_by; ?></td>
                    <td class="text-center" style="<?php echo $row_style; ?>"><?php echo $show_status; ?></td>
                </tr>
            <?php endwhile; 
            else: ?>
                <tr><td colspan="9" class="text-center" style="padding:20px; color:red;">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php exit(); ?>