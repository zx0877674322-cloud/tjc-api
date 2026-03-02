<?php
// 1. ตั้งค่าและเชื่อมต่อฐานข้อมูล
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

date_default_timezone_set('Asia/Bangkok');

// 2. รับค่ากรอง
$start_date = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$filter_user = isset($_GET['export_user']) ? $conn->real_escape_string($_GET['export_user']) : '';
$filter_status = isset($_GET['export_status']) ? $conn->real_escape_string($_GET['export_status']) : '';

// 3. สร้าง SQL กรองข้อมูล
$table_name = 'reports'; // ตาราง Sales Report
$where_sql = "WHERE 1=1";

$s_date = $conn->real_escape_string($start_date);
$where_sql .= " AND report_date >= '$s_date'";

$e_date = $conn->real_escape_string($end_date);
$where_sql .= " AND report_date <= '$e_date'";
if (!empty($filter_user)) {
    $where_sql .= " AND reporter_name = '$filter_user'";
}
if (!empty($filter_status)) {
    // โลจิกเหมือนใน Dashboard
    if ($filter_status == 'ได้งาน') {
        $where_sql .= " AND (job_status LIKE '%ได้งาน%' AND job_status NOT LIKE '%ไม่ได้งาน%')";
    } else {
        $where_sql .= " AND job_status LIKE '%$filter_status%'";
    }
}

$sql_export = "SELECT * FROM $table_name $where_sql ORDER BY report_date DESC, id DESC";
$res_export = $conn->query($sql_export);

// 4. ตั้งค่า Header ไฟล์ Excel
$filename = "Sales_Report_Export_" . date('Y-m-d_Hi') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>

<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: 'Angsana New', sans-serif;
        }

        th {
            border: 1px solid #000;
            padding: 10px;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
            font-size: 16px;
            white-space: nowrap;
        }

        td {
            border: 1px solid #ccc;
            padding: 5px;
            vertical-align: top;
            font-size: 14px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .num-fmt {
            mso-number-format: "\#\,\#\#0\.00";
        }

        .text-fmt {
            mso-number-format: "\@";
        }

        .head-blue {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .head-green {
            background-color: #d1fae5;
            color: #065f46;
        }

        .head-orange {
            background-color: #ffedd5;
            color: #9a3412;
        }

        .head-purple {
            background-color: #f3e8ff;
            color: #6b21a8;
        }

        .head-gray {
            background-color: #f1f5f9;
            color: #334155;
        }
    </style>
</head>

<body>
    <table>
        <thead>
            <tr>
                <th class="head-blue" width="100">วันที่รายงาน</th>
                <th class="head-blue" width="80">เวลาที่บันทึก</th>
                <th class="head-blue" width="150">พนักงานขาย</th>
                <th class="head-green" width="250">ลูกค้า/กิจกรรม</th>
                <th class="head-orange" width="250">โครงการ</th>
                <th class="head-orange" width="120">มูลค่าโครงการ</th>
                <th class="head-purple" width="200">สถานะงาน</th>
                <th class="head-gray" width="100">ค่าน้ำมัน</th>
                <th class="head-gray" width="100">ค่าที่พัก</th>
                <th class="head-gray" width="100">ค่าอื่นๆ</th>
                <th class="head-gray" width="120">รวมค่าใช้จ่าย</th>
                <th width="200">สรุปการเข้าพบ</th>
                <th width="150">นัดหมายถัดไป</th>
                <th width="200">บันทึกเพิ่มเติม</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // ตัวแปรเก็บยอดรวมทั้งหมด
            $grand_total_project = 0;
            $grand_total_fuel = 0;
            $grand_total_hotel = 0;
            $grand_total_other = 0;
            $grand_total_expense = 0;

            if ($res_export && $res_export->num_rows > 0) {
                while ($row = $res_export->fetch_assoc()):
                    $report_date = date('d/m/Y', strtotime($row['report_date']));
                    $created_time = date('H:i', strtotime($row['created_at']));

                    // --- แยกข้อมูลที่เป็น Array แบบข้อความ ---
                    $customers = explode(', ', $row['work_result'] ?? '');

                    // แยกระบบโครงการและมูลค่า
                    $raw_projects = explode(', ', $row['project_name'] ?? '');
                    $clean_projects = [];
                    $project_values = [];
                    foreach ($raw_projects as $p) {
                        $p = trim($p);
                        if (preg_match('/^(.*?)\s*\(มูลค่า:\s*([\d,.]+)\s*บาท\)$/u', $p, $m)) {
                            $clean_projects[] = trim($m[1]);
                            $project_values[] = $m[2];
                        } else {
                            $clean_projects[] = $p;
                            $project_values[] = '-';
                        }
                    }

                    $statuses = explode(', ', $row['job_status'] ?? '');
                    $summaries = explode("\n", $row['activity_detail'] ?? '');
                    $next_appts = explode(', ', $row['next_appointment'] ?? '');

                    $raw_notes = $row['additional_notes'] ?? '';
                    $notes = [];
                    if (strpos($raw_notes, "\n") !== false) {
                        $notes = explode("\n", $raw_notes);
                    } else {
                        // แยกแบบมีเลขข้อถ้านับจากบรรทัดเดียวไม่ได้
                        $notes = array_filter(preg_split('/\d+\.\s+/', $raw_notes));
                        $notes = array_values($notes); // reset keys
                    }
                    if (count($notes) === 0 && !empty($raw_notes)) {
                        $notes = [$raw_notes];
                    }

                    $max_rows = max(count($customers), count($clean_projects));
                    if ($max_rows == 0)
                        $max_rows = 1;

                    // คำนวณค่าน้ำมัน
                    $fuel_sum = 0;
                    if (!empty($row['fuel_cost'])) {
                        $fuels = explode(',', $row['fuel_cost']);
                        foreach ($fuels as $fc) {
                            $fuel_sum += floatval($fc);
                        }
                    }

                    $hotel_cost = floatval($row['accommodation_cost'] ?? 0);
                    $other_cost = floatval($row['other_cost'] ?? 0);
                    $total_expense = floatval($row['total_expense'] ?? 0);

                    // สะสมยอดรวมค่าใช้จ่ายของแต่ละรายงาน
                    $grand_total_fuel += $fuel_sum;
                    $grand_total_hotel += $hotel_cost;
                    $grand_total_other += $other_cost;
                    $grand_total_expense += $total_expense;

                    // Rowspan สำหรับข้อมูลคอลัมน์แรกๆ เพื่อความสวยงาม
                    for ($i = 0; $i < $max_rows; $i++):
                        $cus_item = isset($customers[$i]) ? trim($customers[$i]) : '';
                        $proj_item = isset($clean_projects[$i]) ? trim($clean_projects[$i]) : '';
                        $val_item = isset($project_values[$i]) && $project_values[$i] !== '-' ? floatval(str_replace(',', '', $project_values[$i])) : '';
                        $st_item = isset($statuses[$i]) ? trim($statuses[$i]) : '';

                        // สะสมยอดรวมมูลค่าโครงการแต่ละรายการ
                        if ($val_item !== '') {
                            $grand_total_project += floatval($val_item);
                        }

                        $summary_item = isset($summaries[$i]) ? trim(preg_replace('/^[•\-\d\s]*.*?\s*:\s*/', '', $summaries[$i])) : '';
                        if (empty($summary_item) && isset($summaries[0])) {
                            $summary_item = trim(preg_replace('/^[•\-\d\s]*.*?\s*:\s*/', '', $summaries[0]));
                        }

                        $appt_item = isset($next_appts[$i]) ? trim($next_appts[$i]) : '';

                        $note_item = isset($notes[$i]) ? trim(preg_replace('/^[•\-\d]+\.?\s*.*?\s*:\s*/', '', $notes[$i])) : '';
                        if (empty($note_item) && isset($notes[0])) {
                            $note_item = trim(preg_replace('/^[•\-\d]+\.?\s*.*?\s*:\s*/', '', $notes[0]));
                        }
                        ?>
                        <tr>
                            <?php if ($i == 0): ?>
                                <td class="text-center" rowspan="<?php echo $max_rows; ?>">
                                    <?php echo $report_date; ?>
                                </td>
                                <td class="text-center" rowspan="<?php echo $max_rows; ?>">
                                    <?php echo $created_time; ?>
                                </td>
                                <td rowspan="<?php echo $max_rows; ?>">
                                    <?php echo $row['reporter_name']; ?>
                                </td>
                            <?php endif; ?>

                            <td>
                                <?php echo $cus_item; ?>
                            </td>
                            <td>
                                <?php echo $proj_item; ?>
                            </td>
                            <td class="text-right <?php echo ($val_item !== '') ? 'num-fmt' : ''; ?>">
                                <?php echo $val_item; ?>
                            </td>
                            <td class="text-center">
                                <?php echo $st_item; ?>
                            </td>

                            <?php if ($i == 0): ?>
                                <td class="text-right num-fmt" rowspan="<?php echo $max_rows; ?>">
                                    <?php echo $fuel_sum; ?>
                                </td>
                                <td class="text-right num-fmt" rowspan="<?php echo $max_rows; ?>">
                                    <?php echo $hotel_cost; ?>
                                </td>
                                <td class="text-right num-fmt" rowspan="<?php echo $max_rows; ?>">
                                    <?php echo $other_cost; ?>
                                </td>
                                <td class="text-right num-fmt" rowspan="<?php echo $max_rows; ?>" style="font-weight: bold;">
                                    <?php echo $total_expense; ?>
                                </td>
                            <?php endif; ?>

                            <td style="white-space: pre-wrap;">
                                <?php echo htmlspecialchars($summary_item); ?>
                            </td>
                            <td class="text-center">
                                <?php echo htmlspecialchars($appt_item); ?>
                            </td>
                            <td style="white-space: pre-wrap;">
                                <?php echo htmlspecialchars($note_item); ?>
                            </td>
                        </tr>
                        <?php
                    endfor; // end for row expansion
                endwhile;
                ?>
                <!-- บรรทัดสรุปยอดรวมทั้งหมด -->
                <tr>
                    <td colspan="5" class="text-right head-gray" style="font-weight:bold; font-size:16px;">รวมทั้งหมด</td>
                    <td class="text-right head-gray num-fmt" style="font-weight:bold; color:#065f46; font-size:16px;">
                        <?php echo $grand_total_project; ?>
                    </td>
                    <td class="head-gray"></td>
                    <td class="text-right head-gray num-fmt" style="font-weight:bold; color:#1e40af;">
                        <?php echo $grand_total_fuel; ?>
                    </td>
                    <td class="text-right head-gray num-fmt" style="font-weight:bold; color:#1e40af;">
                        <?php echo $grand_total_hotel; ?>
                    </td>
                    <td class="text-right head-gray num-fmt" style="font-weight:bold; color:#1e40af;">
                        <?php echo $grand_total_other; ?>
                    </td>
                    <td class="text-right head-gray num-fmt" style="font-weight:bold; color:#9a3412; font-size:16px;">
                        <?php echo $grand_total_expense; ?>
                    </td>
                    <td colspan="3" class="head-gray"></td>
                </tr>
                <?php
            } else {
                ?>
                <tr>
                    <td colspan="14" class="text-center" style="padding: 20px; font-weight: bold; color: red;">
                        ไม่มีข้อมูลตามเงื่อนไขที่เลือก</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</body>

</html>