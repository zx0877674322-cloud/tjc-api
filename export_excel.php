<?php
// 1. ตั้งค่าและเชื่อมต่อฐานข้อมูล
session_start();
require_once 'auth.php'; 
require_once 'db_connect.php'; 

date_default_timezone_set('Asia/Bangkok');

// 2. รับค่ากรอง
$ex_start   = $_GET['start_date'] ?? '';
$ex_end     = $_GET['end_date'] ?? '';
$ex_user    = isset($_GET['export_user']) ? $conn->real_escape_string($_GET['export_user']) : '';
$ex_keyword = isset($_GET['filter_keyword']) ? $conn->real_escape_string($_GET['filter_keyword']) : '';
$ex_company = isset($_GET['company_id']) ? $_GET['company_id'] : '';
$ex_type    = isset($_GET['export_doc_type']) ? $conn->real_escape_string($_GET['export_doc_type']) : '';
$ex_status  = isset($_GET['status']) ? $_GET['status'] : '';

// 3. สร้าง SQL
$sql_export = "SELECT d.*, c.company_name, c.company_shortname 
               FROM document_submissions d
               LEFT JOIN companies c ON d.company_id = c.id
               WHERE 1=1 "; 

// กรองวันที่
if (!empty($ex_start) && !empty($ex_end)) {
    $sql_export .= " AND d.created_at BETWEEN '$ex_start 00:00:00' AND '$ex_end 23:59:59'";
}
// กรองผู้ทำรายการ
if (!empty($ex_user)) {
    $sql_export .= " AND d.ordered_by = '$ex_user' ";
}
// กรองประเภท
if (!empty($ex_type)) {
    $sql_export .= " AND (d.doc_type = '$ex_type' OR d.doc_number LIKE '$ex_type%') ";
}
// กรองบริษัท
if (!empty($ex_company)) {
    $c_ids = explode(',', $ex_company);
    $safe_ids = array_map(function($id) use ($conn) { return "'" . $conn->real_escape_string($id) . "'"; }, $c_ids);
    $sql_export .= " AND d.company_id IN (" . implode(',', $safe_ids) . ")";
}

// กรองสถานะพิเศษ
if ($ex_status == 'returned') {
    $sql_export .= " AND d.return_doc_by IS NOT NULL AND d.return_doc_by != ''";
} elseif ($ex_status == 'approved') {
    $sql_export .= " AND d.approver_name IS NOT NULL AND d.approver_name != '' AND (d.is_cancelled = 0 OR d.is_cancelled IS NULL)";
} elseif ($ex_status == 'pending_approve') {
    $sql_export .= " AND (d.approver_name IS NULL OR d.approver_name = '') AND (d.is_cancelled = 0 OR d.is_cancelled IS NULL)";
} elseif ($ex_status == 'cancelled') {
    $sql_export .= " AND d.is_cancelled = 1";
}

// กรอง Keyword
if (!empty($ex_keyword)) {
    if (strpos($ex_keyword, 'ตีกลับ') !== false || strpos($ex_keyword, 'ตั้งโอน') !== false) {
         $sql_export .= " AND (d.attachments LIKE '%ตีกลับ%' OR (d.return_doc_by IS NOT NULL AND d.return_doc_by != ''))";
    } else {
         $sql_export .= " AND (d.doc_number LIKE '%$ex_keyword%' OR d.supplier_name LIKE '%$ex_keyword%' OR d.description LIKE '%$ex_keyword%' OR d.attachments LIKE '%$ex_keyword%')";
    }
}

$sql_export .= " ORDER BY d.created_at DESC";
$q_export = $conn->query($sql_export);

// 4. ตั้งค่า Header ไฟล์ Excel
$filename = "Export_Data_" . date('Y-m-d_Hi') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; 
?>

<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table { border-collapse: collapse; width: 100%; font-family: 'Angsana New', sans-serif; }
        th { border: 1px solid #000; padding: 10px; text-align: center; vertical-align: middle; font-weight: bold; font-size: 16px; white-space: nowrap; }
        td { border: 1px solid #ccc; padding: 5px; vertical-align: top; font-size: 14px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .num-fmt { mso-number-format: "\#\,\#\#0\.00"; }
        .text-fmt { mso-number-format: "\@"; }
        .date-fmt { mso-number-format: "dd\/mm\/yyyy"; }
        
        .head-green { background-color: #d1fae5; color: #065f46; }
        .head-blue { background-color: #dbeafe; color: #1e40af; }
        .head-red { background-color: #fee2e2; color: #b91c1c; } 
        .head-orange-light { background-color: #ffedd5; color: #9a3412; }
        .head-orange-dark { background-color: #fdba74; color: #7c2d12; }
        .head-purple { background-color: #f3e8ff; color: #6b21a8; }
        .head-yellow { background-color: #fef3c7; color: #b45309; }
    </style>
</head>

<body>
    <table>
        <thead>
            <tr>
                <th class="head-green" width="150">บริษัท</th>
                <th class="head-green" width="100">วันที่</th>
                <th class="head-green" width="80">เวลา</th>
                <th class="head-green" width="80">ประเภท</th>
                <th class="head-green" width="120">เลขที่เอกสาร</th>
                <th class="head-green" width="150">ผู้สั่ง/ผู้เปิด</th>
                <th class="head-green" width="200">หน้างาน</th>
                <th class="head-green" width="200">ชื่อร้านค้า (Supplier)</th>
                
                <th class="head-green" width="120">ยอดเงิน (บาท)</th>
                <th class="head-green" width="100">WHT</th>
                <th class="head-green" width="120">สถานะการจ่าย</th>
                <th class="head-green" width="150">ผู้อนุมัติ</th>
                
                <th class="head-blue" width="150">การเงินรับเอกสาร</th>
                <th class="head-blue" width="120">วันที่รับ(การเงิน)</th> <th class="head-blue" width="200" style="color:#be123c;">ตีกลับเอกสาร (ตั้งโอน)</th>
                
                <th class="head-red" width="200">ยกเลิกตีกลับ</th>

                <th class="head-orange-light" width="150">คลังรับสินค้า</th>
                <th class="head-orange-light" width="120">วันที่รับ(สินค้า)</th>
                <th class="head-orange-light" width="150">Ref.เอกสารรับ</th>

                <th class="head-orange-dark" width="150">คลังรับใบกำกับ</th>
                <th class="head-orange-dark" width="120">วันที่รับ(คลัง)</th>
                <th class="head-orange-dark" width="200">หมายเหตุ(คลัง)</th>

                <th class="head-green" width="150">ผู้ซื้อรับใบกำกับ</th>
                <th class="head-green" width="120">วันที่รับ(ผู้ซื้อ)</th>
                <th class="head-green" width="200">หมายเหตุ(ผู้ซื้อ)</th>

                <th class="head-purple" width="150">บัญชีรับใบกำกับ</th>
                <th class="head-purple" width="120">วันที่รับ(บัญชี)</th>
                <th class="head-purple" width="200">หมายเหตุ(บัญชี)</th>
                
                <th class="head-green" width="200">เอกสารแนบ</th>
                <th class="head-yellow" width="200">หมายเหตุสถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $q_export->fetch_assoc()):
                $created_date = date('d/m/Y', strtotime($row['created_at']));
                $created_time = date('H:i', strtotime($row['created_at']));
                $company = $row['company_shortname'] ?: $row['company_name'];
                
                $app_date = $row['approved_at'] ? date('d/m/Y H:i', strtotime($row['approved_at'])) : '-';
                
                // การเงิน
                $fin_date = $row['finance_received_at'] ? date('d/m/Y H:i', strtotime($row['finance_received_at'])) : '-';
                
                $wh_goods_date = $row['warehouse_received_at'] ? date('d/m/Y H:i', strtotime($row['warehouse_received_at'])) : '-';
                $wh_bill_date  = $row['wh_tax_received_at'] ? date('d/m/Y H:i', strtotime($row['wh_tax_received_at'])) : '-';
                $tax_date = $row['tax_received_at'] ? date('d/m/Y H:i', strtotime($row['tax_received_at'])) : '-';
                $acc_date = $row['acc_received_at'] ? date('d/m/Y H:i', strtotime($row['acc_received_at'])) : '-';
                
                // Attachments
                $att_text = "";
                if (!empty($row['attachments'])) {
                    $att_raw = $row['attachments'];
                    $att_arr = json_decode($att_raw, true);
                    if (is_array($att_arr)) {
                        $att_text = implode(', ', $att_arr);
                    } else {
                        $att_text = str_replace(['[', ']', '"', '\\'], '', $att_raw);
                    }
                }

                // --- Logic ตรวจสอบสถานะ ---
                $row_style = ""; 
                
                // 1. ตีกลับ (Returned) -> แสดงชื่อ + วันที่
                $is_return = false;
                $return_info = "-";
                
                if (!empty($row['return_doc_by'])) {
                    $is_return = true;
                    // Format: ชื่อ (วันที่)
                    $return_info = $row['return_doc_by'] . "\n(" . date('d/m/y H:i', strtotime($row['return_doc_at'])) . ")";
                } elseif (strpos($att_text, 'ตีกลับ') !== false || strpos($att_text, 'ตั้งโอน') !== false) {
                    $is_return = true;
                    $return_info = "ระบุในเอกสารแนบ";
                }

                // 2. ยกเลิก (Cancelled) -> แสดงชื่อ + วันที่
                $is_cancel = ($row['is_cancelled'] == 1);
                $cancel_info = "-";
                
                if ($is_cancel) {
                    // กรณี Void เอกสารทิ้ง
                    $cancel_info = "VOID โดย: " . $row['cancelled_by'] . "\n(" . date('d/m/y H:i', strtotime($row['cancelled_at'])) . ")";
                    $row_style = "background-color: #fef2f2; color: #991b1b;"; 
                } elseif (!empty($row['cancel_return_by'])) {
                    // กรณียกเลิกการตีกลับ (Reset)
                    $cancel_info = "แก้คืนโดย: " . $row['cancel_return_by'] . "\n(" . date('d/m/y H:i', strtotime($row['cancel_return_at'])) . ")";
                }

                // ใส่สีพื้นหลัง
                if ($is_cancel) {
                    // สีแดง (ทำไปแล้วด้านบน)
                } elseif ($is_return) {
                    $row_style = "background-color: #fff1f2;"; // ชมพูสำหรับตีกลับ
                } elseif (strpos($att_text, 'ใบเสร็จรับเงิน') !== false) {
                    $row_style = "background-color: #ecfeff;";
                } elseif (strpos($att_text, 'ชุดชำระเงิน') !== false) {
                    $row_style = "background-color: #faf5ff;";
                }
            ?>
                <tr style="<?php echo $row_style; ?>">
                    <td><?php echo $company; ?></td>
                    <td class="text-center date-fmt"><?php echo $created_date; ?></td>
                    <td class="text-center text-fmt"><?php echo $created_time; ?></td>
                    <td class="text-center"><?php echo $row['doc_type']; ?></td>
                    <td class="text-center text-fmt"><?php echo $row['doc_number']; ?></td>
                    <td class="text-center"><?php echo $row['ordered_by']; ?></td>
                    <td><?php echo $row['job_site']; ?></td>
                    <td><?php echo $row['supplier_name']; ?></td>
                    
                    <td class="text-right num-fmt"><?php echo $row['amount']; ?></td>
                    <td class="text-right num-fmt"><?php echo $row['wht_amount'] > 0 ? $row['wht_amount'] : 0; ?></td>
                    <td class="text-center"><?php echo $row['payment_status'] ?? '-'; ?></td>
                    <td class="text-center"><?php echo $row['approver_name'] ?? '-'; ?></td>
                    
                    <td class="text-center"><?php echo $row['finance_receiver'] ?? '-'; ?></td>
                    <td class="text-center"><?php echo $fin_date; ?></td>

                    <td class="text-center" style="white-space: pre-wrap; font-weight:bold; color:#be123c;">
                        <?php echo $return_info; ?>
                    </td>
                    
                    <td class="text-center" style="white-space: pre-wrap; font-weight:bold; color:#b91c1c;">
                        <?php echo $cancel_info; ?>
                    </td>

                    <td class="text-center"><?php echo $row['warehouse_receiver'] ?? '-'; ?></td>
                    <td class="text-center"><?php echo $wh_goods_date; ?></td>
                    <td class="text-center text-fmt"><?php echo $row['warehouse_doc_no'] ?? '-'; ?></td>

                    <td class="text-center"><?php echo $row['wh_tax_receiver'] ?? '-'; ?></td>
                    <td class="text-center"><?php echo $wh_bill_date; ?></td>
                    <td style="text-align: left; white-space: pre-wrap; color:#7c2d12;"><?php echo htmlspecialchars($row['wh_tax_note'] ?? ''); ?></td>

                    <td class="text-center"><?php echo $row['tax_receiver'] ?? '-'; ?></td>
                    <td class="text-center"><?php echo $tax_date; ?></td>
                    <td style="text-align: left; white-space: pre-wrap;"><?php echo htmlspecialchars($row['note_buyer'] ?? ''); ?></td>

                    <td class="text-center"><?php echo $row['acc_receiver'] ?? '-'; ?></td>
                    <td class="text-center"><?php echo $acc_date; ?></td>
                    <td style="text-align: left; white-space: pre-wrap; color:#6b21a8;"><?php echo htmlspecialchars($row['note_acc'] ?? ''); ?></td>
                    
                    <td><?php echo $att_text; ?></td>
                    
                    <td style="text-align: left; white-space: pre-wrap; color: #d97706;"><?php echo htmlspecialchars($row['tax_status_note'] ?? ''); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>