<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'db_connect.php';

// รับค่าจากแบบฟอร์ม (GET)
$start_date = !empty($_GET['export_start']) ? $_GET['export_start'] : date('Y-m-d', strtotime('-30 days'));
$end_date = !empty($_GET['export_end']) ? $_GET['export_end'] : date('Y-m-d');
$receiver = !empty($_GET['export_receiver']) ? trim($_GET['export_receiver']) : '';
$tech = !empty($_GET['export_tech']) ? trim($_GET['export_tech']) : '';

// แก้ไขฟอร์แมตวันที่ (ถ้ารับมาเป็น d/m/Y ให้แปลงเป็น Y-m-d)
function convertDateForDB($dateStr)
{
    if (strpos($dateStr, '/') !== false) {
        $parts = explode('/', $dateStr);
        if (count($parts) == 3) {
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
    }
    return $dateStr;
}
$db_start = convertDateForDB($start_date) . ' 00:00:00';
$db_end = convertDateForDB($end_date) . ' 23:59:59';

// --------------------------------------------------------------------------
// สร้างชื่อไฟล์
// --------------------------------------------------------------------------
$filename = "Service_Export_" . date('Ymd_Hi') . ".xls";

// บังคับ HTTP Headers ให้ดาวน์โหลดเป็นไฟล์ Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// พิมพ์ BOM เพื่อให้เปิดใน Excel รองรับภาษาไทยได้ถูกต้อง
echo "\xEF\xBB\xBF";

// --------------------------------------------------------------------------
// สไตล์ CSS ขั้นต้นสำหรับตาราง (เพื่อให้แสดงเส้นขอบใน Excel)
// --------------------------------------------------------------------------
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table {
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000000;
            padding: 5px;
            font-size: 12pt;
            font-family: 'Tahoma', sans-serif;
            text-align: left;
            vertical-align: top;
            mso-number-format: "\@";
            word-wrap: break-word;
        }

        th {
            background-color: #f1f5f9;
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>

<body>

    <table>
        <thead>
            <tr>
                <th>หน้างาน</th>
                <th>โครงการ</th>
                <th>วันที่แจ้ง</th>
                <th>ผู้รับเรื่อง</th>
                <th>ผู้ลงข้อมูล</th>
                <th>ผู้แจ้ง / ติดต่อ</th>
                <th>ลูกค้า</th>
                <th>ความเร่งด่วน</th>
                <th>กำหนดเสร็จ (SLA)</th>
                <th>สถานะ</th>
                <th>ผู้รับผิดชอบ</th>
                <th>รายละเอียด (สินค้า - ปัญหา)</th>
                <th>ความคืบหน้า (Progress Logs)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // --------------------------------------------------------------------------
// สร้าง SQL Query
// --------------------------------------------------------------------------
            $sql = "SELECT sr.*, 
               pc.project_name AS joined_project_name, 
               c.customer_name AS joined_customer_name 
        FROM service_requests sr
        LEFT JOIN project_contracts pc ON sr.site_id = pc.site_id
        LEFT JOIN customers c ON pc.customer_id = c.customer_id
        WHERE sr.request_date BETWEEN ? AND ?";

            $params = [$db_start, $db_end];
            $types = "ss";

            if (!empty($receiver)) {
                $sql .= " AND sr.receiver_by = ?";
                $params[] = $receiver;
                $types .= "s";
            }

            // การค้นหาช่างที่รับผิดชอบ
            if (!empty($tech)) {
                $sql .= " AND sr.technician_name LIKE ?";
                $params[] = "%" . $tech . "%";
                $types .= "s";
            }

            $sql .= " ORDER BY sr.request_date DESC";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {

                        // 1. เลขหน้างาน (Site ID หรือ Manual Code)
                        $show_site_id = ($row['site_id'] > 0) ? $row['site_id'] : ($row['manual_site_code'] ?? '-');

                        $show_project = ($row['site_id'] > 0) ? $row['joined_project_name'] : ($row['manual_project_name'] ?? '-');
                        if (empty($show_project))
                            $show_project = '-';

                        // 2. ชื่อลูกค้า 
                        $show_customer = ($row['site_id'] > 0) ? $row['joined_customer_name'] : ($row['manual_customer_name'] ?? '-');
                        if (empty($show_customer))
                            $show_customer = '-';

                        // แปลงวันที่
                        $req_date = date('d/m/Y H:i', strtotime($row['request_date']));
                        $sla_date = date('d/m/Y H:i', strtotime($row['expected_finish_date']));

                        // สถานะ (คำนวณเหมือนใน Dashboard)
                        $status_db = $row['status'];
                        $has_logs = (!empty($row['progress_logs']) && $row['progress_logs'] !== '[]');

                        if ($status_db === 'completed') {
                            $status_th = 'เสร็จสิ้น';
                        } elseif ($has_logs) {
                            $status_th = 'กำลังดำเนินการ';
                        } else {
                            $status_th = 'รอดำเนินการ';
                        }

                        // ช่างผู้รับผิดชอบ
                        $tech_names = !empty($row['technician_name']) ? $row['technician_name'] : '-';

                        // ความเร่งด่วน (ภาษาไทย)
                        $urgency_map = [
                            'normal' => 'ปกติ',
                            'urgent' => 'ด่วน',
                            'critical' => 'ด่วนมาก'
                        ];
                        $show_urgency = $urgency_map[$row['urgency']] ?? $row['urgency'];

                        // รูปแบบการติดต่อ
                        $contacts_arr = json_decode($row['contact_detail'] ?? '[]', true);
                        $contact_text = "-";
                        if (is_array($contacts_arr) && count($contacts_arr) > 0) {
                            $c_lines = [];
                            // แบบเก่าอาจใช้ string ธรรมดา
                            if (isset($contacts_arr[0]) && is_string($contacts_arr[0])) {
                                $c_lines = $contacts_arr;
                            } else {
                                foreach ($contacts_arr as $c) {
                                    $channel = isset($c['channel']) ? "({$c['channel']})" : "";
                                    $val = $c['detail'] ?? '';
                                    $ext = !empty($c['ext']) ? " ต่อ {$c['ext']}" : "";
                                    if ($val)
                                        $c_lines[] = trim("$val $ext $channel");
                                }
                            }
                            $contact_text = implode('<br>', $c_lines);
                        }

                        // ข้อมูลสินค้าปัญหาแบบละเอียด
                        $item_details_text = "";
                        $items_arr = json_decode($row['project_item_name'] ?? '[]', true);
                        if (is_array($items_arr) && count($items_arr) > 0) {
                            $item_lines = [];
                            foreach ($items_arr as $idx => $it) {
                                $prods = isset($it['product']) ? (is_array($it['product']) ? implode(', ', $it['product']) : $it['product']) : '-';
                                $issue = $it['issue'] ?? '-';
                                $item_lines[] = ($idx + 1) . ". สินค้า: " . $prods . "<br>   ปัญหา: " . $issue;
                            }
                            $item_details_text = implode('<br><br>', $item_lines);
                        } else {
                            // หากไม่มีโครงสร้าง JSON (เก่า)
                            $item_details_text = str_replace("\n", "<br>", $row['issue_description'] ?? '-');
                        }

                        // ข้อมูลความคืบหน้า (Progress Logs) - ไม่เอาไฟล์รูป และจัดให้สวยงาม
                        $progress_text = "-";
                        $progress_arr = json_decode($row['progress_logs'] ?? '[]', true);
                        if (is_array($progress_arr) && count($progress_arr) > 0) {
                            $p_lines = [];
                            foreach ($progress_arr as $p) {
                                // โครงสร้าง JSON ของระบบใช้ at, by, msg
                                $p_date = $p['at'] ?? (isset($p['timestamp']) ? date('d/m/Y H:i', strtotime($p['timestamp'])) : '-');
                                $p_user = $p['by'] ?? ($p['user'] ?? '-');
                                $p_msg = $p['msg'] ?? ($p['note'] ?? '');

                                if (!empty($p_msg)) {
                                    // 1. ลบ tag <style>...</style> ออกไปเลย เพื่อไม่ให้เหลือโค้ด CSS
                                    $clean_msg = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $p_msg);

                                    // 2. ลบข้อความ "ดูรูปหลักฐานแนบ" หรือ <a href...>ดูรูปหลักฐานแนบ...</a> ออก
                                    $clean_msg = preg_replace('/<a[^>]*>.*?ดูรูปหลักฐานแนบ.*?<\/a>/is', '', $clean_msg);
                                    $clean_msg = preg_replace('/ดูรูปหลักฐานแนบ/u', '', $clean_msg);

                                    // 2.1 ลบข้อความ ดูใบเสนอราคา / ใบเสร็จ หรือลิงก์ที่เกี่ยวข้อง
                                    $clean_msg = preg_replace('/<a[^>]*>.*?ดูใบเสนอราคา \/ ใบเสร็จ.*?<\/a>/is', '', $clean_msg);
                                    $clean_msg = preg_replace('/ดูใบเสนอราคา \/ ใบเสร็จ/u', '', $clean_msg);

                                    // 3. เปลี่ยน tag ปิด block ให้เป็นขึ้นบรรทัดใหม่ก่อน
                                    $clean_msg = str_replace(['</div>', '</p>', '</tr>', '</table>', '</li>'], '<br>', $clean_msg);

                                    // เพิ่มสัญลักษณ์ขั้นระหว่างคอลัมน์ตารางให้ดูอ่านง่าย
                                    $clean_msg = str_replace(['</td>', '</th>'], ' | ', $clean_msg);
                                    $clean_msg = str_replace('<li>', '<br>- ', $clean_msg);

                                    // 4. ลบ HTML สไตล์ต่างๆ และรูปภาพออกให้หมด เหลือแค่ข้อความกับ <br>ไ
                                    $clean_msg = strip_tags($clean_msg, '<br>');

                                    // 5. ลบคำว่า "ดำเนินการโดย: [ชื่อผู้บันทึก]" (และคำอื่นๆ ที่คล้ายกัน) ที่ติดมากับ HTML สรุปยอด
                                    $clean_msg = preg_replace('/(?:ดำเนินการโดย|ผู้เบิก|ผู้ส่งมอบ|ผู้ทำรายการ|ผู้บันทึก|ผู้ดำเนินการ):\s*.*?<br>/iu', '', $clean_msg);
                                    $clean_msg = preg_replace('/(?:ดำเนินการโดย|ผู้เบิก|ผู้ส่งมอบ|ผู้ทำรายการ|ผู้บันทึก|ผู้ดำเนินการ):\s*.*?$/iu', '', $clean_msg);

                                    // 6. ลบ <br> หรือช่องว่างที่ซ้ำซ้อนให้เหลือบรรทัดเดียว
                                    $clean_msg = preg_replace('/(<br>\s*){2,}/', '<br>', $clean_msg);
                                    $clean_msg = trim($clean_msg, " \t\n\r\0\x0B<br>");

                                    if (!empty($clean_msg)) {
                                        $p_lines[] = "<b>▶ รอบบันทึกวันที่:</b> {$p_date}<br>" .
                                            "<b>👤 ผู้บันทึก:</b> {$p_user}<br>" .
                                            "<b>💬 รายละเอียด:</b><br>{$clean_msg}";
                                    }
                                }
                            }
                            if (count($p_lines) > 0) {
                                // ใช้เส้นประคั่นระหว่างแต่ละรายการเพื่อให้ดูง่ายใน Excel
                                $progress_text = implode('<br>----------------------------------------<br>', $p_lines);
                            }
                        }

                        // หาคนลงข้อมูล (sys_user หรือ reporter_name)
                        $recorded_by = !empty($row['sys_user']) ? $row['sys_user'] : ($row['reporter_name'] ?? '-');

                        echo "<tr>";
                        echo "<td class='text-center'>" . htmlspecialchars($show_site_id) . "</td>";
                        echo "<td>" . htmlspecialchars($show_project) . "</td>";
                        echo "<td class='text-center'>" . $req_date . "</td>";
                        echo "<td>" . htmlspecialchars($row['receiver_by'] ?: '-') . "</td>";
                        echo "<td>" . htmlspecialchars($recorded_by) . "</td>";
                        echo "<td>" . $contact_text . "</td>";
                        echo "<td>" . htmlspecialchars($show_customer) . "</td>";
                        echo "<td class='text-center'>" . htmlspecialchars($show_urgency) . "</td>";
                        echo "<td class='text-center'>" . $sla_date . "</td>";
                        echo "<td class='text-center'>" . htmlspecialchars($status_th) . "</td>";
                        echo "<td>" . htmlspecialchars($tech_names) . "</td>";
                        echo "<td>" . $item_details_text . "</td>";
                        echo "<td>" . $progress_text . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='12' class='text-center'>ไม่พบข้อมูล</td></tr>";
                }
            } else {
                echo "<tr><td colspan='12' class='text-center'>Query Error: " . $conn->error . "</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>

</html>