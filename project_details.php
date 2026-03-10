<?php
session_start();
require_once 'db_connect.php';

// [NEW] ส่วนอัปเดตสถานะใบกำกับภาษี (AJAX)
if (isset($_POST['action']) && $_POST['action'] == 'update_tax_invoice') {
    $po_id = intval($_POST['po_id']);
    $status = intval($_POST['status']); // 1 = มี, 0 = ไม่มี

    $stmt = $conn->prepare("UPDATE purchase_orders SET has_tax_invoice = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $po_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// [NEW] ส่วนอัปโหลดไฟล์สลิปย้อนหลัง (AJAX)
if (isset($_POST['action']) && $_POST['action'] == 'upload_slip') {
    // 🧹 1. ล้าง Buffer ป้องกัน HTML ขยะติดไปกับ JSON
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json');

    $po_id = intval($_POST['po_id']);

    if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['slip_file']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $new_name = time() . "_slip_" . $po_id . "." . $ext;
            $target_dir = "uploads/slips/";

            // 🛠️ 2. สร้างโฟลเดอร์แบบปลอดภัย
            if (!file_exists($target_dir)) {
                if (!mkdir($target_dir, 0777, true)) {
                    echo json_encode(['status' => 'error', 'message' => 'สร้างโฟลเดอร์ไม่ได้ (Permission Denied)']);
                    exit;
                }
            }

            if (move_uploaded_file($_FILES['slip_file']['tmp_name'], $target_dir . $new_name)) {
                // อัปเดต DB
                $stmt = $conn->prepare("UPDATE purchase_orders SET slip_file = ? WHERE id = ?");
                $stmt->bind_param("si", $new_name, $po_id);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'filename' => $new_name]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ย้ายไฟล์ไม่สำเร็จ (Check Folder Permission)']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'นามสกุลไฟล์ไม่ถูกต้อง']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่ได้รับไฟล์ หรือไฟล์มีปัญหา']);
    }
    exit;
}

// [NEW] ส่วนบันทึก/แก้ไข ลิงก์หลักของโครงการ (AJAX)
if (isset($_POST['action']) && $_POST['action'] == 'update_project_link') {
    $p_id = intval($_POST['project_id']);
    $url = trim($_POST['project_link']);

    // เติม https:// อัตโนมัติถ้าลืมใส่
    if ($url && !preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "https://" . $url;
    }

    $stmt = $conn->prepare("UPDATE projects SET project_link = ? WHERE id = ?");
    $stmt->bind_param("si", $url, $p_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'url' => $url]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("<div style='padding:20px; color:red;'>ไม่พบรหัสโครงการ</div>");
}

$project_id = $conn->real_escape_string($_GET['id']);

// ==========================================
// 1. ดึงข้อมูลส่วนหัว (Project Header)
// ==========================================
$sql_project = "SELECT p.*, 
                COALESCE(c.customer_name, 'ไม่ระบุ') as customer_name
                FROM projects p
                LEFT JOIN customers c ON p.customer_id = c.customer_id
                WHERE p.id = '$project_id'";
$result_project = $conn->query($sql_project);

if ($result_project->num_rows == 0) {
    die("<div style='padding:20px; color:red;'>ไม่พบข้อมูลโครงการ</div>");
}

$project = $result_project->fetch_assoc();

// คำนวณวันคงเหลือ
$today = new DateTime();
$today->setTime(0, 0, 0);
$days_remaining_txt = "-";
$days_color = "text-secondary";

if (!empty($project['end_date']) && $project['end_date'] != '0000-00-00') {
    $end_date_contract = new DateTime($project['end_date']);
    $end_date_contract->setTime(0, 0, 0);

    if ($today <= $end_date_contract) {
        $diff = $today->diff($end_date_contract);
        $days_remaining_txt = $diff->days . " วัน";
        $days_color = "text-success"; // ยังไม่เกินกำหนด
    } else {
        $days_remaining_txt = "เกินกำหนด";
        $days_color = "text-danger"; // เกินกำหนด
    }
}

// ==========================================
// 2. ดึงข้อมูลรายการสินค้า (JOIN Purchase Orders)
// ==========================================
// --- ส่วนที่ปรับปรุง: เชื่อม 3 ตารางผ่านสะพานเชื่อม (pi -> poi -> po) ---
$sql_items = "SELECT pi.*,pi.item_type,
              COUNT(po.id) as po_count,
              COALESCE(SUM(poi.total_price), 0) AS total_actual_spent,
              GROUP_CONCAT(po.supplier_name SEPARATOR '|') AS all_suppliers,
              MAX(po.has_tax_invoice) as has_tax_invoice,
              GROUP_CONCAT(poi.buy_quantity SEPARATOR '|') AS all_qtys,
              GROUP_CONCAT(poi.buy_unit_price SEPARATOR '|') AS all_prices,
              GROUP_CONCAT(po.order_date SEPARATOR '|') AS all_dates,
              GROUP_CONCAT(po.id SEPARATOR '|') AS all_po_ids,
              GROUP_CONCAT(po.slip_file SEPARATOR '|') AS all_slips,
              
              /* 🔥 [แก้ใหม่] ดึงราคาล่าสุดแบบบังคับคำนวณ */
              (
                SELECT 
                    CASE 
                        /* ถ้าราคาต่อหน่วยมีค่า ใช้ค่านั้นเลย */
                        WHEN poi_last.buy_unit_price > 0 THEN poi_last.buy_unit_price 
                        /* ถ้าราคาต่อหน่วยเป็น 0 ให้เอา (ราคารวม หาร จำนวน) */
                        WHEN poi_last.buy_quantity > 0 THEN (poi_last.total_price / poi_last.buy_quantity)
                        /* ถ้าไม่มีอะไรเลย ให้เป็น 0 */
                        ELSE 0 
                    END
                FROM purchase_order_items poi_last 
                JOIN purchase_orders po_last ON poi_last.purchase_order_id = po_last.id 
                WHERE poi_last.project_item_id = pi.id 
                /* เรียงตาม ID ล่าสุด (แม่นยำกว่าวันที่) */
                ORDER BY po_last.id DESC 
                LIMIT 1
              ) AS latest_buy_price,
              /* -------------------------------------- */

              (
                SELECT GROUP_CONCAT(CONCAT(posi.item_name, ';;', posi.quantity, ';;', posi.unit_price) SEPARATOR '||')
                FROM purchase_order_sub_items posi
                JOIN purchase_order_items poi_sub ON posi.purchase_order_item_id = poi_sub.id
                WHERE poi_sub.project_item_id = pi.id
              ) AS sub_items_list

              FROM project_items pi
              LEFT JOIN purchase_order_items poi ON pi.id = poi.project_item_id
              LEFT JOIN purchase_orders po ON poi.purchase_order_id = po.id
              WHERE pi.project_id = '$project_id' 
              GROUP BY pi.id
              ORDER BY pi.id ASC";

$result_items = $conn->query($sql_items);

// ดักจับ Error SQL เพื่อให้รู้ถ้ามีปัญหาอีก
if (!$result_items) {
    die("SQL Error: " . $conn->error);
}

function renderStatusBadge($status)
{
    $status = trim($status);
    $cssClass = 'st-wait'; // ค่าเริ่มต้น
    $icon = 'fa-hourglass-start';

    if ($status == 'สั่งซื้อครบแล้ว') {
        $cssClass = 'st-full';
        $icon = 'fa-check-circle';
    } elseif ($status == 'สั่งซื้อบางส่วน') {
        $cssClass = 'st-partial';
        $icon = 'fa-adjust'; // หรือ fa-clock
    } elseif ($status == 'ยกเลิก') {
        $cssClass = 'st-cancel';
        $icon = 'fa-times-circle';
    }

    return "<span class='status-badge $cssClass'><i class='fas $icon'></i> $status</span>";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title>รายละเอียดโครงการ - <?= htmlspecialchars($project['project_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-light: #e0e7ff;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --bg-color: #eef2f6;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --border-color: #cbd5e1;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background-color: var(--bg-color);
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 24px 24px;
            color: var(--text-main);
            margin: 0;
            padding: 0px;
        }

        .container-fluid {
            max-width: 1800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* --- Header Section --- */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: white;
            padding: 15px 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .project-title h2 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-back {
            background: #f1f5f9;
            color: var(--secondary-color);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }

        /* --- Info Cards Grid --- */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: transform 0.2s;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .icon-blue {
            background: #e0e7ff;
            color: var(--primary-color);
        }

        .icon-green {
            background: #dcfce7;
            color: var(--success-color);
        }

        .icon-orange {
            background: #ffedd5;
            color: var(--warning-color);
        }

        .icon-red {
            background: #fee2e2;
            color: var(--danger-color);
        }

        .icon-purple {
            background: #f3e8ff;
            color: #9333ea;
        }

        .info-content .label {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .info-content .value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }

        /* --- Action Bar --- */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
            background: #fff;
            padding: 15px 20px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .link-mini-group {
            display: flex;
            align-items: center;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 4px;
            border: 1px solid #cbd5e1;
        }

        .link-mini-group input {
            border: none;
            background: transparent;
            padding: 5px 10px;
            outline: none;
            font-size: 0.9rem;
            width: 250px;
            color: #334155;
        }

        .btn-icon-save {
            background: #3b82f6;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .btn-icon-save:hover {
            background: #2563eb;
        }

        .btn-mini-visit {
            margin-left: 10px;
            color: #10b981;
            background: #ecfdf5;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid #d1fae5;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
        }

        .btn-mini-visit:hover {
            background: #d1fae5;
            transform: translateY(-1px);
        }

        .btn-action {
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-add {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-add:hover {
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }

        .btn-po {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-po:hover {
            box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.4);
            transform: translateY(-2px);
        }

        /* --- Table Section --- */
        .table-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 12px 15px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            vertical-align: middle;
            color: #334155;
        }

        tr:hover td {
            background-color: #f8fafc;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            /* เงาให้ดูนูน */
            transition: all 0.2s ease;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* สีเขียว: ครบ */
        .st-full {
            background: #d1fae5;
            color: #065f46;
            border-color: #6ee7b7;
        }

        /* สีส้ม: บางรายการ */
        .st-partial {
            background: #ffedd5;
            color: #c2410c;
            border-color: #fdba74;
        }

        /* สีเทา: รอ */
        .st-wait {
            background: #f1f5f9;
            color: #64748b;
            border-color: #cbd5e1;
        }

        /* สีเหลือง: ค่าใช้จ่าย */
        .st-expense {
            background: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        /* สีแดง: ยกเลิก */
        .st-cancel {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .text-success {
            color: #10b981 !important;
        }

        .text-danger {
            color: #ef4444 !important;
        }

        .text-muted {
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .text-supplier {
            color: #0891b2;
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* สไตล์สำหรับรายการย่อย */
        .sub-item-container {
            margin-top: 8px;
            padding: 8px 12px;
            background-color: #fff7ed;
            /* สีส้มอ่อนๆ */
            border-left: 3px solid #f97316;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .sub-item-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #fed7aa;
            padding: 4px 0;
        }

        .sub-item-row:last-child {
            border-bottom: none;
        }

        .sub-item-name {
            color: #9a3412;
            font-weight: 500;
        }

        .sub-item-meta {
            color: #64748b;
            font-size: 0.8rem;
        }

        /* ปุ่ม Toggle */
        .btn-toggle-sub {
            font-size: 0.75rem;
            color: #f59e0b;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            transition: 0.2s;
        }

        .btn-toggle-sub:hover {
            color: #d97706;
            background: #fffbeb;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .btn-view-sub {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #fcd34d;
            color: #d97706;
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.1);
            font-weight: 600;
        }

        .btn-view-sub:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.2);
            background: #fbbf24;
            color: #fff;
        }

        /* 2. พื้นหลัง Modal (Glassmorphism) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            /* สีมืดโปร่งแสง */
            backdrop-filter: blur(8px);
            /* เบลอฉากหลัง */
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* 3. กล่อง Modal (Pop Animation) */
        .modal-box {
            background: #fff;
            width: 90%;
            max-width: 600px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            transform: scale(0.8) translateY(20px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            /* เด้งดึ๋ง */
            overflow: hidden;
        }

        .modal-overlay.active .modal-box {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        /* 4. Header สวยๆ */
        .modal-header-custom {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .modal-title-custom {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        .modal-close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close-btn:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: rotate(90deg);
        }

        /* 5. ตารางข้างใน */
        .modal-body-custom {
            padding: 20px;
            background: #fffbeb;
        }

        .sub-table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .sub-table-custom th {
            color: #78350f;
            font-weight: 600;
            padding: 10px;
            border-bottom: 2px solid #fcd34d;
            font-size: 0.9rem;
        }

        .sub-table-custom td {
            padding: 12px 10px;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 0.95rem;
            color: #334155;
            background: #fff;
        }

        .sub-table-custom tr:last-child td {
            border-bottom: none;
        }

        .sub-table-custom tr:hover td {
            background: #fef3c7;
        }

        /* โค้งมุมตาราง */
        .sub-table-custom tr:first-child th:first-child {
            border-top-left-radius: 10px;
        }

        .sub-table-custom tr:first-child th:last-child {
            border-top-right-radius: 10px;
        }

        .sub-table-custom tr:last-child td:first-child {
            border-bottom-left-radius: 10px;
        }

        .sub-table-custom tr:last-child td:last-child {
            border-bottom-right-radius: 10px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            /* สีตอนปิด */
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #10b981;
            /* สีตอนเปิด (เขียว) */
        }

        input:focus+.slider {
            box-shadow: 0 0 1px #10b981;
        }

        input:checked+.slider:before {
            transform: translateX(18px);
        }

        .tax-label {
            font-size: 0.75rem;
            margin-left: 5px;
            font-weight: 600;
            vertical-align: top;
            line-height: 22px;
        }

        .status-badge.st-expense {
            background-color: #fef3c7;
            /* สีเหลืองอ่อน */
            color: #92400e;
            /* สีน้ำตาลส้มเข้ม */
            border: 1px solid #fcd34d;
            /* ขอบเหลือง */
        }

        /* (แถม) เพื่อความสวยงามเวลาโฮเวอร์ */
        .status-badge.st-expense:hover {
            background-color: #fde68a;
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="container-fluid">

        <div class="page-header">
            <div class="project-title">
                <h2><i class="fas fa-folder-open"></i> <?= htmlspecialchars($project['project_name']) ?></h2>
                <span style="font-size: 0.9rem; color: #64748b; margin-left: 10px;">(ID:
                    <?= htmlspecialchars($project['id']) ?>)</span>
            </div>
            <a href="project_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> กลับหน้า Dashboard</a>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <div class="icon-box icon-purple"><i class="fas fa-user-tie"></i></div>
                <div class="info-content">
                    <div class="label">ชื่อลูกค้า</div>
                    <div class="value"><?= htmlspecialchars($project['customer_name']) ?></div>
                </div>
            </div>
            <div class="info-card">
                <div class="icon-box icon-green"><i class="fas fa-calendar-alt"></i></div>
                <div class="info-content">
                    <div class="label">เริ่มสัญญา</div>
                    <div class="value">
                        <?= !empty($project['start_date']) ? date('d/m/Y', strtotime($project['start_date'])) : '-' ?>
                    </div>
                </div>
            </div>
            <div class="info-card">
                <div class="icon-box icon-red"><i class="fas fa-calendar-times"></i></div>
                <div class="info-content">
                    <div class="label">สิ้นสุดสัญญา</div>
                    <div class="value">
                        <?= !empty($project['end_date']) ? date('d/m/Y', strtotime($project['end_date'])) : '-' ?>
                    </div>
                </div>
            </div>
            <div class="info-card">
                <div class="icon-box icon-orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="info-content">
                    <div class="label">ระยะเวลาคงเหลือ</div>
                    <div class="value <?= $days_color ?>"><?= $days_remaining_txt ?></div>
                </div>
            </div>
        </div>

        <?php $current_link = $project['project_link'] ?? ''; ?>

        <div class="action-bar">
            <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                <h3 style="margin:0; color:#334155; font-size:1.3rem;"><i class="fas fa-list text-primary"></i>
                    รายการสินค้า</h3>
                <div class="link-mini-group">
                    <i class="fas fa-link text-muted" style="padding-left:10px;"></i>
                    <input type="text" id="project_link_input" value="<?= htmlspecialchars($current_link) ?>"
                        placeholder="วางลิงก์ Google Drive / ไฟล์งาน...">
                    <button onclick="saveLink()" id="btn_save_link" class="btn-icon-save" title="บันทึกลิงก์"><i
                            class="fas fa-save"></i></button>
                </div>
                <a href="<?= htmlspecialchars($current_link) ?>" target="_blank" id="btn_go_link" class="btn-mini-visit"
                    style="<?= $current_link ? '' : 'display:none;' ?>">
                    <i class="fas fa-external-link-alt"></i> เปิดดู
                </a>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="create_item_entry.php?project_id=<?= $project_id ?>" class="btn-action btn-add"><i
                        class="fas fa-plus-circle"></i> เพิ่มสินค้า (ลงทุน)</a>
                <a href="create_purchase_order.php?project_id=<?= $project_id ?>" class="btn-action btn-po"><i
                        class="fas fa-shopping-cart"></i> ทำใบสั่งซื้อ (PO)</a>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align:center;">#</th>
                            <th>รายการสินค้า</th>
                            <th style="text-align:center;">แผน / ซื้อจริง</th>
                            <th style="text-align:center;">หน่วย</th>
                            <th style="text-align:right; color: #0284c7;">ราคาซื้อล่าสุด</th>
                            <th style="text-align:right;">ราคารวม</th>
                            <th style="color:#0891b2;">ร้านค้า / Supplier</th>
                            <th style="text-align:center;">สถานะ</th>
                            <th style="text-align:center;">เลขที่ PO</th>
                            <th style="text-align:center;">ไฟล์เอกสารPO</th>
                            <th>ผู้สั่งซื้อ</th>
                            <th style="text-align:center;">วันที่สั่ง</th>
                            <th style="text-align:center;">วันรับของ</th>
                            <th>เงื่อนไข</th>
                            <th style="text-align:center;">ใบกำกับฯ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_items && $result_items->num_rows > 0): ?>
                            <?php $i = 1;
                            while ($item = $result_items->fetch_assoc()):
                                $history_data = [];
                                $h_sql = "SELECT po.id as po_id, po.supplier_name, po.order_date, 
          po.orderer_name, po.delivery_date, po.conditions, po.has_tax_invoice, 
          poi.buy_quantity, poi.buy_unit_price, poi.total_price, 
          po.slip_file,
          
          /* 🔥 ท่อนนี้คือหัวใจสำคัญ! ถ้าขาดบรรทัดนี้ รายการย่อยจะไม่โชว์ */
          (
            SELECT GROUP_CONCAT(CONCAT(posi.item_name, ';;', posi.quantity, ';;', posi.unit_price) SEPARATOR '||')
            FROM purchase_order_sub_items posi 
            WHERE posi.purchase_order_item_id = poi.id
          ) as sub_items_raw

          FROM purchase_order_items poi
          JOIN purchase_orders po ON poi.purchase_order_id = po.id
          WHERE poi.project_item_id = '{$item['id']}'
          ORDER BY po.order_date DESC";

                                $h_query = $conn->query($h_sql);
                                if ($h_query) {
                                    while ($h_row = $h_query->fetch_assoc()) {
                                        $history_data[] = $h_row;
                                    }
                                }

                                // แปลงเป็น JSON เตรียมไว้
                                $json_history = htmlspecialchars(json_encode($history_data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                $total_est = $item['quantity'] * $item['standard_price'];
                                $latest_price = $item['latest_buy_price'];
                                $status = $item['purchase_status'];
                                $badge_class = 'status-wait';
                                if (strpos($status, 'สั่งซื้อบางส่วน') !== false)
                                    $badge_class = 'status-ordered';
                                if ($status == 'สั่งซื้อครบแล้ว')
                                    $badge_class = 'status-done';
                                if ($status == 'ยกเลิก')
                                    $badge_class = 'status-cancel';
                                ?>
                                <tr>
                                    <td style="text-align:center; color:#94a3b8;"><?= $i++ ?></td>
                                    <td>
                                        <div style="font-size: 1rem; color: #1e293b; font-weight: 700;">
                                            <?= htmlspecialchars($item['item_name']) ?>
                                        </div>

                                        <?php if (!empty($item['spec_type']) && $item['spec_type'] != 'ปกติ'): ?>
                                            <small class="text-secondary"><?= htmlspecialchars($item['spec_type']) ?></small>
                                        <?php endif; ?>

                                        <?php if (!empty($item['sub_items_list'])):
                                            // แปลงข้อมูลเป็น Array เพื่อส่งให้ JS
                                            $sub_data = [];
                                            $raw_subs = explode('||', $item['sub_items_list']);
                                            foreach ($raw_subs as $s) {
                                                list($n, $q, $p) = explode(';;', $s);
                                                $sub_data[] = [
                                                    'name' => $n,
                                                    'qty' => floatval($q),
                                                    'price' => floatval($p),
                                                    'total' => floatval($q) * floatval($p)
                                                ];
                                            }
                                            $json_sub = htmlspecialchars(json_encode($sub_data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <div style="margin-top: 8px;">
                                                <button type="button" class="btn-view-sub" onclick="openSubModal(this)"
                                                    data-main-name="<?= htmlspecialchars($item['item_name']) ?>"
                                                    data-subs='<?= $json_sub ?>'>
                                                    <i class="fas fa-layer-group"></i> ดูส่วนประกอบ (<?= count($sub_data) ?>)
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if (isset($item['item_type']) && $item['item_type'] == 'expense'): ?>
                                            <span style="color:#cbd5e1;">-</span>
                                        <?php else: ?>
                                            <?= number_format($item['quantity'], 2) ?> /
                                            <span class="text-success" style="font-weight:bold;">
                                                <?= number_format($item['purchased_quantity'], 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;"><?= htmlspecialchars($item['unit']) ?></td>
                                    <td
                                        style="text-align:right; font-weight:700; color: #0284c7; background: #f0f9ff; vertical-align: middle;">
                                        <?php
                                        // 1. เช็คว่าเป็นค่าแรง/ค่าใช้จ่าย หรือไม่?
                                        // 1. เช็คว่าเป็น 'ค่าใช้จ่าย' หรือไม่
                                        if (isset($item['item_type']) && $item['item_type'] == 'expense') {
                                            echo '<span class="status-badge st-expense"><i class="fas fa-file-invoice-dollar"></i> ค่าใช้จ่าย</span>';
                                        } else {
                                            // 2. เตรียมตัวเลข และตรวจสอบรายการย่อย!
                                            $plan_qty = floatval($item['quantity']);                  // แผน
                                            $bought_qty = floatval($item['purchased_quantity'] ?? 0); // ซื้อจริงตัวหลัก
                                
                                            // ใช้ trim() ตัดช่องว่างทิ้ง ป้องกันบั๊กจาก DB
                                            $db_status = trim($item['purchase_status'] ?? '');

                                            // 🟢 [ไม้ตาย] เช็คว่ามีประวัติสั่งซื้อ "รายการย่อย" อยู่ด้วยใช่ไหม?
                                            $has_sub_items = !empty($item['sub_items_list']);

                                            // ใช้ strpos เพื่อค้นหาคำแบบยืดหยุ่น (เจอคำว่า 'ยกเลิก' ก็ถือว่าใช่เลย)
                                            if (strpos($db_status, 'ยกเลิก') !== false) {
                                                echo '<span class="status-badge st-cancel"><i class="fas fa-ban"></i> ยกเลิก</span>';

                                            } elseif (strpos($db_status, 'ครบ') !== false || ($bought_qty >= $plan_qty && $plan_qty > 0)) {
                                                // ✅ ครบแล้ว
                                                echo '<span class="status-badge st-full"><i class="fas fa-check-circle"></i> สั่งซื้อครบแล้ว</span>';

                                            } elseif (strpos($db_status, 'บาง') !== false || $bought_qty > 0 || $has_sub_items) {
                                                // 🟠 ซื้อบางส่วน (ถ้ายอดหลักมีซื้อ หรือ "มีซื้อรายการย่อย" ให้แสดงสถานะนี้ทันที!)
                                                echo '<span class="status-badge st-partial"><i class="fas fa-box-open"></i> สั่งซื้อบางรายการ</span>';

                                            } else {
                                                // ⚪ ยังไม่มีการเคลื่อนไหวใดๆ
                                                echo '<span class="status-badge st-wait"><i class="fas fa-clock"></i> รอสั่งซื้อ</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align:right; vertical-align: middle;">
                                        <?php
                                        // คำนวณยอดรวม (จำนวน x ราคาต่อหน่วย)
                                        $total_budget = $item['quantity'] * $item['cost_price'];
                                        ?>

                                        <div style="font-weight: 800; color: #334155; font-size: 1rem;">
                                            <?= number_format($total_budget, 2) ?>
                                        </div>
                                    </td>

                                    <td style="text-align:center;">
                                        <textarea id="data-history-<?= $item['id'] ?>"
                                            style="display:none;"><?= $json_history ?></textarea>

                                        <?php if ($item['po_count'] > 1): ?>
                                            <button type="button"
                                                onclick="openHistoryModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name']) ?>')"
                                                style="background: #0891b2; color: white; border: none; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                                                <i class="fas fa-store-alt"></i> ซื้อหลายร้าน
                                            </button>

                                        <?php elseif ($item['po_count'] == 1): ?>
                                            <?php $shop_name = !empty($history_data[0]['supplier_name']) ? $history_data[0]['supplier_name'] : '-'; ?>

                                            <div onclick="openHistoryModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name']) ?>')"
                                                style="color: #1e293b; font-weight: 600; font-size: 0.9rem; cursor: pointer; text-decoration: underline; text-decoration-color: #cbd5e1; transition:0.2s;"
                                                title="คลิกเพื่อดูรายละเอียดการสั่งซื้อ">
                                                <?= htmlspecialchars($shop_name) ?>
                                            </div>

                                        <?php else: ?>
                                            <span style="color:#cbd5e1;">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="text-align:center; vertical-align: middle;">
                                        <?php
                                        // 1. เช็คว่าเป็น 'ค่าใช้จ่าย' หรือไม่
                                        if (isset($item['item_type']) && $item['item_type'] == 'expense') {
                                            echo '<span class="status-badge st-expense"><i class="fas fa-file-invoice-dollar"></i> ค่าใช้จ่าย</span>';
                                        } else {
                                            // 2. เตรียมตัวเลข และตรวจสอบรายการย่อย
                                            $plan_qty = floatval($item['quantity']);
                                            $bought_qty = floatval($item['purchased_quantity'] ?? 0);

                                            // 🟢 ใช้ trim() ตัดช่องว่างออก
                                            $db_status = trim($item['purchase_status'] ?? '');
                                            $has_sub = !empty($item['sub_items_list']);

                                            // 3. เช็คสถานะเรียงตามลำดับความสำคัญ
                                            if (strpos($db_status, 'ยกเลิก') !== false) {

                                                echo '<span class="status-badge st-cancel"><i class="fas fa-ban"></i> ยกเลิก</span>';

                                            } elseif (strpos($db_status, 'รออนุมัติ') !== false) {

                                                // 🟣 [เพิ่มใหม่] รออนุมัติสั่งซื้อ (ป้ายสีม่วงคราม สวยๆ)
                                                echo '<span class="status-badge" style="background:#e0e7ff; color:#4338ca; border:1px solid #818cf8;"><i class="fas fa-user-clock"></i> ' . htmlspecialchars($db_status) . '</span>';

                                            } elseif (strpos($db_status, 'ครบ') !== false || ($bought_qty >= $plan_qty && $plan_qty > 0)) {

                                                // ✅ ครบแล้ว
                                                echo '<span class="status-badge st-full"><i class="fas fa-check-circle"></i> สั่งซื้อครบแล้ว</span>';

                                            } elseif (strpos($db_status, 'บาง') !== false || $bought_qty > 0 || $has_sub) {

                                                // 🟠 ซื้อบางส่วน
                                                echo '<span class="status-badge st-partial"><i class="fas fa-box-open"></i> สั่งซื้อบางรายการ</span>';

                                            } else {

                                                // ⚪ [แก้ใหม่] ถ้าไม่เข้าเงื่อนไขอะไรเลย ให้เอาข้อความใน DB มาโชว์ตรงๆ เลย (ถ้าว่างค่อยโชว์ 'รอสั่งซื้อ')
                                                $display_text = !empty($db_status) ? htmlspecialchars($db_status) : 'รอสั่งซื้อ';
                                                echo '<span class="status-badge st-wait"><i class="fas fa-clock"></i> ' . $display_text . '</span>';

                                            }
                                        }
                                        ?>
                                    </td>

                                    <td
                                        style="text-align:center; font-family:'Courier New', monospace; font-weight:600; color: #1e293b;">
                                        <?php echo !empty($item['doc_no']) ? htmlspecialchars($item['doc_no']) : '-'; ?>
                                    </td>

                                    <td style="text-align:center; vertical-align:middle;">
                                        <?php
                                        // 1. ดึงรายชื่อไฟล์ทั้งหมดที่มีจริง จากประวัติ ($history_data)
                                        $valid_files = [];
                                        foreach ($history_data as $h) {
                                            if (!empty($h['slip_file'])) {
                                                $valid_files[] = $h['slip_file'];
                                            }
                                        }

                                        $file_count = count($valid_files);

                                        if ($file_count == 1) {
                                            // 🟢 กรณี: มีไฟล์เดียว -> แสดงไอคอนให้กดโหลดได้เลย
                                            $the_file = $valid_files[0];
                                            echo '<a href="uploads/slips/' . htmlspecialchars($the_file) . '" target="_blank" 
                 style="color:#ef4444; font-size:1.2rem; transition:0.2s;" 
                 title="ดูไฟล์แนบ">
                <i class="fas fa-file-pdf"></i>
              </a>';
                                        } elseif ($file_count > 1) {
                                            // 🔵 กรณี: มีหลายไฟล์ -> ให้กดดูในประวัติ
                                            echo '<div onclick="showHistoryModal(\'' . htmlspecialchars($item['item_name']) . '\', \'' . $json_history . '\')" 
                   style="cursor:pointer; color:#3b82f6; background:#eff6ff; padding:4px 8px; border-radius:12px; border:1px solid #bfdbfe; font-size:0.8rem; display:inline-block;">
                <i class="fas fa-layer-group"></i> ดูในประวัติ (' . $file_count . ')
              </div>';
                                        } else {
                                            // ⚪ กรณี: ไม่มีไฟล์
                                            echo '<span style="color:#cbd5e1;">-</span>';
                                        }
                                        ?>
                                    </td>

                                    <td style="text-align:center;">
                                        <?php
                                        if ($item['po_count'] > 1) {
                                            echo '<span style="color:#94a3b8; font-size:0.8rem;">(ดูในประวัติ)</span>';
                                        } elseif (!empty($history_data)) {
                                            echo htmlspecialchars($history_data[0]['orderer_name']);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td style="text-align:center;">
                                        <?php
                                        if ($item['po_count'] > 1) {
                                            echo '<span style="color:#94a3b8;">-</span>';
                                        } elseif (!empty($history_data) && !empty($history_data[0]['order_date'])) {
                                            echo date('d/m/Y', strtotime($history_data[0]['order_date']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td style="text-align:center;">
                                        <?php
                                        if ($item['po_count'] > 1) {
                                            echo '<span style="color:#94a3b8;">-</span>';
                                        } elseif (!empty($history_data) && !empty($history_data[0]['delivery_date'])) {
                                            echo date('d/m/Y', strtotime($history_data[0]['delivery_date']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td style="text-align:center;">
                                        <?php
                                        if ($item['po_count'] > 1) {
                                            echo '<span style="color:#94a3b8;">-</span>';
                                        } elseif (!empty($history_data)) {
                                            echo '<small class="text-secondary">' . htmlspecialchars($history_data[0]['conditions']) . '</small>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td style="text-align:center;">
                                        <?php
                                        if ($item['po_count'] > 1) {
                                            echo '<span style="color:#94a3b8;">-</span>';
                                        } elseif (!empty($history_data)) {
                                            if ($history_data[0]['has_tax_invoice'] == 1) {
                                                echo '<span style="color:#059669; background:#ecfdf5; padding:2px 8px; border-radius:5px; font-size:0.75rem; font-weight:bold;"><i class="fas fa-check"></i> มี</span>';
                                            } else {
                                                echo '<span style="color:#cbd5e1;">-</span>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="14" style="text-align: center; padding: 50px; color: #94a3b8;">
                                    <i class="fas fa-box-open fa-3x" style="margin-bottom:15px; opacity:0.5;"></i><br>
                                    ยังไม่มีรายการสินค้าในโครงการนี้
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="subItemModal" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-header-custom">
                    <div class="modal-title-custom">
                        <i class="fas fa-box-open"></i> รายการส่วนประกอบ
                        <span id="modalMainName"
                            style="font-size:0.9rem; font-weight:400; color:#e0f2fe; margin-left:10px; background:rgba(255,255,255,0.2); padding:2px 8px; border-radius:4px;"></span>
                    </div>
                    <button class="modal-close-btn" onclick="closeSubModal()"><i class="fas fa-times"></i></button>
                </div>

                <div class="modal-body-custom">
                    <table class="sub-table-custom">
                        <thead>
                            <tr>
                                <th style="text-align:left;">รายการ</th>
                                <th style="text-align:center;">จำนวน</th>
                                <th style="text-align:right;">ราคา/หน่วย</th>
                                <th style="text-align:right;">รวมเงิน</th>
                            </tr>
                        </thead>
                        <tbody id="modalSubBody">
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align:right; font-weight:bold;">รวมต้นทุนทั้งหมด:</td>
                                <td id="modalSubTotal" style="text-align:right; font-weight:800; color:#059669;"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>

        let currentOpenedItemId = null;

        // --- 1. ฟังก์ชันเปิด Modal (แบบใหม่อ่านข้อมูลจากกล่องลับ) ---
        function openHistoryModal(itemId, itemName) {
            currentOpenedItemId = itemId; // จำ ID ไว้

            // ดึงข้อมูลล่าสุดจาก Textarea
            const jsonStr = document.getElementById('data-history-' + itemId).value;

            // เรียกฟังก์ชันแสดงผลตัวเดิม
            showHistoryModal(itemName, jsonStr);
        }

        // --- 2. ฟังก์ชันบันทึก VAT (ปรับปรุงให้อัปเดตข้อมูลกลับเข้ากล่องลับ) ---
        function updateTaxInvoice(checkbox, poId) {
            const isChecked = checkbox.checked;
            const status = isChecked ? 1 : 0;
            const labelSpan = document.getElementById('tax-label-' + poId);

            // Optimistic UI: เปลี่ยนหน้าตาไปก่อนเลย
            if (isChecked) {
                labelSpan.innerText = 'มี VAT';
                labelSpan.style.color = '#059669';
                labelSpan.style.fontWeight = '700';
            } else {
                labelSpan.innerText = 'ไม่มี';
                labelSpan.style.color = '#94a3b8';
                labelSpan.style.fontWeight = '400';
            }

            $.post('project_details.php', {
                action: 'update_tax_invoice',
                po_id: poId,
                status: status
            }, function (response) {
                let res;
                try { res = JSON.parse(response); } catch (e) { res = { status: 'error' }; }

                if (res.status === 'success') {
                    // ✅ [เพิ่ม] อัปเดตข้อมูลกลับเข้ากล่องลับ (เพื่อให้เปิดดูใหม่แล้วค่าไม่หาย)
                    if (currentOpenedItemId) {
                        const textArea = document.getElementById('data-history-' + currentOpenedItemId);
                        if (textArea) {
                            let data = JSON.parse(textArea.value);
                            // หา PO ตัวที่แก้ แล้วอัปเดตค่าใน Array
                            const targetPO = data.find(p => p.po_id == poId);
                            if (targetPO) {
                                targetPO.has_tax_invoice = status; // อัปเดตค่า
                                textArea.value = JSON.stringify(data); // ยัดกลับ
                            }
                        }
                    }

                    Swal.fire({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 1000,
                        icon: 'success', title: 'บันทึกแล้ว'
                    });
                } else {
                    // ❌ พัง -> ดีดกลับ
                    checkbox.checked = !isChecked;
                    Swal.fire('Error', 'บันทึกไม่สำเร็จ', 'error');
                }
            });
        }
        function saveLink() {
            const url = $('#project_link_input').val().trim();
            const projectId = '<?= $project_id ?>';
            const btn = $('#btn_save_link');
            const originalHtml = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

            $.post('project_details.php', {
                action: 'update_project_link',
                project_id: projectId,
                project_link: url
            }, function (response) {
                btn.html(originalHtml).prop('disabled', false);
                let res;
                try { res = typeof response === 'object' ? response : JSON.parse(response); }
                catch (e) { res = { status: 'error', message: 'Invalid Server Response' }; }

                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success', title: 'บันทึกแล้ว',
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 1500
                    });
                    if (res.url) { $('#btn_go_link').attr('href', res.url).fadeIn(); } else { $('#btn_go_link').fadeOut(); }
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
        function showMultiSuppliers(itemName, suppliers, qtys, prices, dates) {
            const sArr = suppliers.split('|');
            const qArr = qtys.split('|');
            const pArr = prices.split('|');
            const dArr = dates.split('|');

            let tableHtml = `
        <div style="font-family: 'Prompt', sans-serif;">
            <table style="width:100%; border-collapse: collapse; margin-top:15px; font-size:0.9rem;">
                <thead>
                    <tr style="background:#f1f5f9; border-bottom:2px solid #cbd5e1;">
                        <th style="padding:10px; text-align:left;">ร้านค้า</th>
                        <th style="padding:10px; text-align:center;">จำนวน</th>
                        <th style="padding:10px; text-align:right;">ราคา/หน่วย</th>
                        <th style="padding:10px; text-align:center;">วันที่สั่ง</th>
                    </tr>
                </thead>
                <tbody>
    `;

            sArr.forEach((s, i) => {
                tableHtml += `
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding:10px; text-align:left; color:#0891b2; font-weight:600;">${s}</td>
                <td style="padding:10px; text-align:center; font-weight:700;">${parseFloat(qArr[i]).toLocaleString()}</td>
                <td style="padding:10px; text-align:right;">${parseFloat(pArr[i]).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                <td style="padding:10px; text-align:center; color:#64748b;">${dArr[i] || '-'}</td>
            </tr>
        `;
            });

            tableHtml += `</tbody></table></div>`;

            Swal.fire({
                title: `<span style="font-size:1.1rem;">ประวัติการสั่งซื้อ: <b>${itemName}</b></span>`,
                html: tableHtml,
                width: '600px',
                confirmButtonText: 'ปิดหน้าต่าง',
                confirmButtonColor: '#4f46e5'
            });
        }

        function openSubModal(btn) {
            // 1. รับค่าจากปุ่ม
            const mainName = btn.getAttribute('data-main-name');
            const subData = JSON.parse(btn.getAttribute('data-subs'));

            // 2. ใส่ชื่อหัวข้อ
            document.getElementById('modalMainName').innerText = mainName;

            // 3. สร้างตาราง
            const tbody = document.getElementById('modalSubBody');
            tbody.innerHTML = ''; // เคลียร์ของเก่า

            let grandTotal = 0;

            subData.forEach((item, index) => {
                grandTotal += item.total;

                // Format number
                const qty = item.qty.toLocaleString();
                const price = item.price.toLocaleString(undefined, { minimumFractionDigits: 2 });
                const total = item.total.toLocaleString(undefined, { minimumFractionDigits: 2 });

                const tr = `
                <tr style="animation: slideIn 0.3s ease-out ${index * 0.05}s both;">
                    <td><div style="font-weight:600; color:#d97706;">${item.name}</div></td>
                    <td style="text-align:center;"><span style="background:#ecfdf5; color:#065f46; padding:2px 8px; border-radius:10px; font-size:0.85rem;">${qty}</span></td>
                    <td style="text-align:right;">${price}</td>
                    <td style="text-align:right; font-weight:700;">${total}</td>
                </tr>
            `;
                tbody.innerHTML += tr;
            });

            // 4. ใส่ยอดรวม
            document.getElementById('modalSubTotal').innerText = '฿' + grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });

            // 5. เปิด Modal
            document.getElementById('subItemModal').classList.add('active');
        }

        function closeSubModal() {
            document.getElementById('subItemModal').classList.remove('active');
        }

        // ปิดเมื่อกดพื้นหลัง
        document.getElementById('subItemModal').addEventListener('click', function (e) {
            if (e.target === this) closeSubModal();
        });

        // Animation Keyframe สำหรับแถวตาราง
        const style = document.createElement('style');
        style.innerHTML = `
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
    `;
        document.head.appendChild(style);

        // ฟังก์ชันแสดง Popup ประวัติการสั่งซื้อ
        function showHistoryModal(itemName, jsonStr) {
            const history = JSON.parse(jsonStr);

            // 1. 🧮 คำนวณยอดรวมทุกร้าน (Grand Total)
            let grandTotal = 0;
            if (history.length > 0) {
                grandTotal = history.reduce((sum, item) => sum + (parseFloat(item.total_price) || 0), 0);
            }

            let tableHtml = `
        <div style="text-align:left; font-family:'Prompt', sans-serif; color: #334155;">
            <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #3b82f6; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size: 0.85rem; color: #64748b;">ประวัติการสั่งซื้อรายการ:</div>
                    <div style="font-size: 1.1rem; color: #1e3a8a; font-weight: 700;">${itemName}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size: 0.8rem; color: #64748b;">ยอดรวมสะสม</div>
                    <div style="font-size: 1.2rem; color: #059669; font-weight: 800;">฿${grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                </div>
            </div>

            <div style="max-height: 500px; overflow-y: auto;">
                <table style="width:100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead style="position: sticky; top: 0; background: #fff; z-index: 1;">
                        <tr style="background-color: #f8fafc; color: #475569; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 10px; text-align: center;">วันที่/รับของ</th>
                            <th style="padding: 10px; text-align: left;">ร้านค้า/ผู้สั่ง</th>
                            <th style="padding: 10px; text-align: left; width: 35%;">รายละเอียด</th>
                            <th style="padding: 10px; text-align: center;">เงื่อนไข/ภาษี</th>
                            <th style="padding: 10px; text-align: center;">จำนวน</th>
                            <th style="padding: 10px; text-align: right;">ราคา/หน่วย</th>
                            <th style="padding: 10px; text-align: right;">รวมเงิน</th>
                            <th style="padding: 10px; text-align: center;">ไฟล์</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

            if (history.length > 0) {
                history.forEach((h, index) => {
                    const dateObj = new Date(h.order_date);
                    const dateStr = dateObj.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: '2-digit' });
                    let deliveryStr = h.delivery_date ? new Date(h.delivery_date).toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '-';

                    const isChecked = h.has_tax_invoice == 1 ? 'checked' : '';
                    const labelText = h.has_tax_invoice == 1 ? 'มี VAT' : 'ไม่มี';
                    const labelColor = h.has_tax_invoice == 1 ? '#059669' : '#94a3b8';

                    const toggleHtml = `
                    <div style="display:flex; align-items:center; justify-content:center; gap:5px; margin-top:4px;">
                        <label class="switch">
                            <input type="checkbox" ${isChecked} onchange="updateTaxInvoice(this, ${h.po_id})">
                            <span class="slider round"></span>
                        </label>
                        <span id="tax-label-${h.po_id}" style="font-size:0.7rem; font-weight:600; color:${labelColor}; width:35px; text-align:left;">${labelText}</span>
                    </div>`;

                    let itemDetailHtml = '';
                    if (h.sub_items_raw && h.sub_items_raw !== "") {
                        const items = h.sub_items_raw.split('||');
                        let subTableRows = '';
                        items.forEach(itemStr => {
                            const parts = itemStr.split(';;');
                            if (parts.length >= 3) {
                                subTableRows += `<tr><td style="padding:2px 5px; border-bottom:1px dashed #fed7aa; text-align:left; color:#92400e;">• ${parts[0]}</td><td style="padding:2px 5px; border-bottom:1px dashed #fed7aa; text-align:center; color:#b45309;">${parseFloat(parts[1]).toLocaleString()}</td><td style="padding:2px 5px; border-bottom:1px dashed #fed7aa; text-align:right; font-weight:600; color:#b45309;">${parseFloat(parts[2]).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td></tr>`;
                            }
                        });
                        itemDetailHtml = `<div style="background:#fff7ed; padding:6px; border-radius:6px; border:1px solid #fed7aa;"><div style="font-weight:700; font-size:0.7rem; color:#c2410c; margin-bottom:4px;"><i class="fas fa-tools"></i> รายการอะไหล่:</div><table style="width:100%; font-size:0.75rem; border-collapse: collapse;">${subTableRows}</table></div>`;
                    } else {
                        itemDetailHtml = `<span style="color:#94a3b8; font-size:0.8rem;">(ซื้อรายการหลักโดยตรง)</span>`;
                    }

                    let fileBtn = '';
                    if (h.slip_file && h.slip_file.trim() !== "") {
                        fileBtn = `<a href="uploads/slips/${h.slip_file}" target="_blank" style="color: #ef4444; font-size: 1.2rem;"><i class="fas fa-file-pdf"></i></a>`;
                    } else {
                        fileBtn = `<label style="cursor:pointer; display:inline-flex; align-items:center; gap:4px; color:#4f46e5; background:#eef2ff; padding:4px 8px; border-radius:6px; font-size:0.75rem; border:1px solid #c7d2fe; transition:0.2s;" title="อัปโหลดไฟล์"><i class="fas fa-cloud-upload-alt"></i> เพิ่ม<input type="file" style="display:none;" onchange="uploadSlip(this, ${h.po_id})" accept=".jpg,.jpeg,.png,.pdf"></label>`;
                    }

                    tableHtml += `
                <tr style="border-bottom: 1px solid #f1f5f9; background: #fff;">
                    <td style="padding: 10px; text-align: center; vertical-align: top;">
                        <div style="font-weight:600; color:#334155;">${dateStr}</div>
                        <div style="font-size:0.75rem; color:#94a3b8;">รับ: ${deliveryStr}</div>
                    </td>
                    <td style="padding: 10px; vertical-align: top;">
                        <div style="color:#0891b2; font-weight:600;">${h.supplier_name}</div>
                        <div style="font-size:0.75rem; color:#64748b;"><i class="fas fa-user"></i> ${h.orderer_name || '-'}</div>
                    </td>
                    <td style="padding: 10px; vertical-align: top;">${itemDetailHtml}</td>
                    <td style="padding: 10px; text-align: center; vertical-align: top;">
                        <div style="font-size:0.75rem; color:#475569;">${h.conditions || '-'}</div>
                        ${toggleHtml}
                    </td>
                    <td style="padding: 10px; text-align: center; vertical-align: top; font-weight:600;">${parseFloat(h.buy_quantity).toLocaleString()}</td>
                    <td style="padding: 10px; text-align: right; vertical-align: top;">${parseFloat(h.buy_unit_price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td style="padding: 10px; text-align: right; vertical-align: top; font-weight: 700; color: #059669;">${parseFloat(h.total_price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td style="padding: 10px; text-align: center; vertical-align: top;">${fileBtn}</td>
                </tr>`;
                });

                // 2. 🏁 เพิ่ม Footer แสดงยอดรวมท้ายตาราง
                tableHtml += `
                <tr style="background-color: #f1f5f9; border-top: 2px solid #cbd5e1; font-weight: 800; color: #1e293b;">
                    <td colspan="6" style="padding: 12px; text-align: right; text-transform: uppercase;">รวมยอดเงินทั้งหมด (ทุกร้าน):</td>
                    <td style="padding: 12px; text-align: right; color: #059669; font-size: 1.1em; text-decoration: underline double;">
                        ${grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </td>
                    <td></td>
                </tr>
            `;

            } else {
                tableHtml += `<tr><td colspan="8" style="text-align:center; padding:30px; color:#94a3b8;">ไม่มีข้อมูลประวัติการสั่งซื้อ</td></tr>`;
            }

            tableHtml += `</tbody></table></div></div>`;

            Swal.fire({
                title: null, html: tableHtml, width: 1100, showConfirmButton: false, showCloseButton: true, customClass: { popup: 'rounded-lg' }
            });
        }

        function uploadSlip(input, poId) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const formData = new FormData();
                formData.append('action', 'upload_slip');
                formData.append('po_id', poId);
                formData.append('slip_file', file);

                // 1. เปลี่ยนปุ่มเป็นสถานะ "กำลังโหลด..."
                const label = input.parentElement; // ตัว label ที่ครอบ input อยู่
                const originalHtml = label.innerHTML;

                // เปลี่ยนหน้าตาให้รู้ว่าทำงานอยู่
                label.style.opacity = '0.5';
                label.style.pointerEvents = 'none';
                label.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ส่ง...';

                $.ajax({
                    url: 'project_details.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function (response) {
                        console.log("Upload Response:", response); // 🔥 เช็คตรงนี้ถ้ามีปัญหา

                        // บางที Server ส่งมาเป็น string ต้องแปลงก่อน
                        let res = response;
                        if (typeof response === 'string') {
                            try { res = JSON.parse(response); } catch (e) { res = { status: 'error', message: response }; }
                        }

                        if (res.status === 'success') {
                            // เปลี่ยนหน้าตาปุ่มเป็น PDF
                            const parentTd = label.parentElement;
                            parentTd.innerHTML = `
                            <a href="uploads/slips/${res.filename}" target="_blank" style="color: #ef4444; font-size: 1.2rem;">
                                <i class="fas fa-file-pdf"></i>
                            </a>`;

                            // ✅ [เพิ่ม] อัปเดตชื่อไฟล์กลับเข้ากล่องลับ
                            if (currentOpenedItemId) {
                                const textArea = document.getElementById('data-history-' + currentOpenedItemId);
                                if (textArea) {
                                    let data = JSON.parse(textArea.value);
                                    const targetPO = data.find(p => p.po_id == poId);
                                    if (targetPO) {
                                        targetPO.slip_file = res.filename; // อัปเดตชื่อไฟล์
                                        textArea.value = JSON.stringify(data); // ยัดกลับ
                                    }
                                }
                            }

                            Swal.fire({
                                toast: true, position: 'top-end', showConfirmButton: false, timer: 1500,
                                icon: 'success', title: 'อัปโหลดเรียบร้อย'
                            });
                        } else {
                            // ❌ พัง: คืนค่าเดิม
                            console.error(res.message);
                            revertButton(label, originalHtml);
                            Swal.fire('แจ้งเตือน', res.message, 'warning');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", error);
                        console.log(xhr.responseText); // ดูว่า PHP error อะไร
                        revertButton(label, originalHtml);
                        Swal.fire('Error', 'ติดต่อ Server ไม่ได้', 'error');
                    }
                });
            }
        }

        // ฟังก์ชันช่วยคืนค่าปุ่มเดิม
        function revertButton(label, html) {
            label.innerHTML = html;
            label.style.opacity = '1';
            label.style.pointerEvents = 'auto';
            // ต้อง bind input event กลับมาใหม่ไหม? ไม่ต้อง เพราะ innerHTML ใส่ input ตัวใหม่เข้าไป แต่เราต้องระวังเรื่อง event listener หาย
            // แต่วิธีนี้ input เก่าหายไปแล้ว input ใหม่ที่ใส่เข้าไปจากการคืนค่า html จะยังไม่มีไฟล์
            // ซึ่งก็ถูกต้องแล้ว ถือว่า reset ให้เลือกใหม่
        }

    </script>

</body>

</html>