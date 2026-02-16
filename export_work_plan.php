<?php
// export_work_plan.php

require_once 'db_connect.php'; 

// 1. รับค่าตัวแปรกรองจาก Query String
$start_date = $_GET['start_date'] ?? ''; 
$end_date   = $_GET['end_date'] ?? '';   
$type       = $_GET['type'] ?? '';       
$worker     = $_GET['worker'] ?? '';     
$status     = $_GET['status'] ?? ''; // 🟢 รับค่า ID สถานะที่เลือกจาก Modal
$search     = $_GET['search'] ?? '';     

// 2. เริ่มสร้าง SQL Query
$sql = "SELECT p.* FROM work_plans p WHERE 1=1 ";

// --- 🟢 ส่วนการกรองข้อมูล (Filtering Logic) ---

// กรองช่วงวันที่
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND p.plan_date BETWEEN '$start_date' AND '$end_date' ";
}

// กรองประเภททีม
if (!empty($type)) {
    $sql .= " AND p.team_type = '$type' ";
}

// กรองผู้ปฏิบัติงาน
if (!empty($worker)) {
    $sql .= " AND (p.reporter_name LIKE '%$worker%' OR p.team_member LIKE '%$worker%') ";
}

// 🔴 ส่วนสำคัญ: กรองตามสถานะที่เลือก
if ($status !== '') { // เช็คว่ามีการเลือกสถานะ (รองรับค่า '0')
    if ($status == '0') {
        // ถ้าเลือก Plan (รอสรุป) ให้กรองแถวที่ยังไม่มีสรุปงาน
        $sql .= " AND (p.summary IS NULL OR p.summary = '') ";
    } else {
        // ถ้าเลือกสถานะอื่นๆ ให้กรองตาม status_id และต้องมีสรุปงานแล้ว
        $sql .= " AND p.status_id = '$status' AND p.summary != '' ";
    }
}

// กรองคำค้นหา
if (!empty($search)) {
    $sql .= " AND p.contact_person LIKE '%$search%' ";
}

$sql .= " ORDER BY p.plan_date ASC, p.id ASC";

$result = $conn->query($sql);

if (!$result) {
    die("<h3>SQL Error!</h3><br>คำสั่งผิดพลาด: " . $conn->error);
}

// 3. ตั้งค่า Header สำหรับไฟล์ Excel
$filename = "WorkPlan_Export_" . date('Ymd_Hi') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; // BOM สำหรับภาษาไทย
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
        .date-fmt { mso-number-format: "dd\/mm\/yyyy"; }
    </style>
</head>
<body>
    <h3>รายงานแผนงาน (Export Data)</h3>
    <p>ช่วงวันที่: <?php echo ($start_date && $end_date) ? date('d/m/Y', strtotime($start_date)).' - '.date('d/m/Y', strtotime($end_date)) : 'ทั้งหมด'; ?></p>
    
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
                <th width="120">สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($result && $result->num_rows > 0):
                $type_map = ['Marketing' => 'การตลาด', 'Auction' => 'ทีมประมูล'];

                while ($row = $result->fetch_assoc()):
                    $plan_date = date('d/m/Y', strtotime($row['plan_date']));
                    
                    // ประเภททีม
                    $team_type = $row['team_type'];
                    $team_type_th = isset($type_map[$team_type]) ? $type_map[$team_type] : $team_type;
                    
                    // 🟢 [แก้ไข] ดึงชื่อสถานะจากคอลัมน์ status โดยตรง (Text ที่บันทึกไว้)
                    $show_status = !empty($row['status']) ? $row['status'] : '-';

                    // Clean ข้อมูล
                    $work_detail = !empty($row['work_detail']) ? nl2br(htmlspecialchars($row['work_detail'])) : '-';
                    $summary = !empty($row['summary']) ? nl2br(htmlspecialchars($row['summary'])) : '-';
                    $team_member = !empty($row['team_member']) ? $row['team_member'] : '-';
                    $contact = !empty($row['contact_person']) ? $row['contact_person'] : '-';

                    // สีพื้นหลังตามสถานะ (เช็คจากข้อความโดยตรง)
                    $row_style = "";
                    if (strpos($show_status, 'ได้งาน') !== false || strpos($show_status, 'ปิดการขาย') !== false || strpos($show_status, 'สำเร็จ') !== false) {
                        $row_style = "background-color: #d1fae5; color: #065f46;"; // เขียว
                    } elseif (strpos($show_status, 'ยกเลิก') !== false || strpos($show_status, 'ไม่ได้งาน') !== false) {
                        $row_style = "background-color: #fee2e2; color: #b91c1c;"; // แดง
                    } elseif ($show_status == 'Plan') {
                        $row_style = "background-color: #ffffff; color: #000;"; // Plan ปกติ
                    }
            ?>
                <tr style="<?php echo $row_style; ?>">
                    <td class="text-center date-fmt"><?php echo $plan_date; ?></td>
                    <td class="text-center"><?php echo $team_type_th; ?></td>
                    <td class="text-center"><?php echo $row['reporter_name']; ?></td>
                    <td class="text-center"><?php echo $team_member; ?></td>
                    <td class="text-left"><?php echo $contact; ?></td>
                    <td class="text-left"><?php echo $work_detail; ?></td>
                    <td class="text-left"><?php echo $summary; ?></td>
                    <td class="text-center"><?php echo $show_status; ?></td>
                </tr>
            <?php endwhile; 
            else: ?>
                <tr><td colspan="8" class="text-center" style="padding:20px;">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php exit(); ?>