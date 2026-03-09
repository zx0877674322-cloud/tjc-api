<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}
$current_user_name = $_SESSION['fullname'] ?? $_SESSION['username'];
$project_id = isset($_GET['project_id']) ? $_GET['project_id'] : '';

// --- 1. Logic การบันทึก (ซับซ้อนหน่อยเพราะเป็น Nested Array + Files) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['po']) && is_array($_POST['po'])) {
        $success_count = 0;

        foreach ($_POST['po'] as $index => $poData) {
            // 1.1 รับข้อมูล Header ของ PO นั้นๆ
            $supplier = $conn->real_escape_string($poData['supplier_name']);
            $orderer = $conn->real_escape_string($poData['orderer_name']);
            $o_date = !empty($poData['order_date']) ? "'" . $poData['order_date'] . "'" : "NULL";
            $d_date = !empty($poData['delivery_date']) ? "'" . $poData['delivery_date'] . "'" : "NULL";
            $cond = $conn->real_escape_string($poData['conditions'] ?? '');

            // จัดการไฟล์สลิป (ต้องเช็ค $_FILES แบบ Array)
            $slip_filename = NULL; // ค่าเริ่มต้นเป็น NULL (ถ้าไม่มีไฟล์)

            // เช็คว่ามีตัวแปรไฟล์ส่งมาใน Index นี้จริงไหม + เช็ค Error Code ต้องเป็น 0 (OK)
            if (
                isset($_FILES['po']['name'][$index]['slip_file']) &&
                $_FILES['po']['error'][$index]['slip_file'] == 0
            ) {

                $tmp_name = $_FILES['po']['tmp_name'][$index]['slip_file'];
                $org_name = basename($_FILES['po']['name'][$index]['slip_file']);

                // 1. ตั้งชื่อไฟล์ใหม่ (กันชื่อซ้ำ + ตัดอักขระแปลกๆ)
                // รูปแบบ: timestamp_index_ชื่อไฟล์เดิม
                $new_name = time() . "_" . $index . "_" . $org_name;

                // 2. กำหนดโฟลเดอร์ปลายทาง
                $target_dir = "uploads/slips/";
                $target_file = $target_dir . $new_name;

                // 3. 🛡️ เช็คว่ามีโฟลเดอร์ไหม? ถ้าไม่มี "สร้างให้เลย"
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                // 4. ย้ายไฟล์
                if (move_uploaded_file($tmp_name, $target_file)) {
                    $slip_filename = $new_name; // ✅ ย้ายสำเร็จ เก็บชื่อลงตัวแปร
                } else {
                    // กรณี Permission ผิด หรือย้ายไม่ได้
                    // echo "<script>console.log('Move Uploaded File Failed: $org_name');</script>";
                }
            }
            // ---------------------------------------------------------

            $has_tax = isset($poData['has_tax_invoice']) ? 1 : 0;

            // 1.2 Insert ลงตาราง purchase_orders (Header)
            $sql_po = "INSERT INTO purchase_orders (project_id, supplier_name, orderer_name, order_date, delivery_date, conditions, slip_file, has_tax_invoice) 
           VALUES ('$project_id', '$supplier', '$orderer', $o_date, $d_date, '$cond', '$slip_filename', '$has_tax')";

            if ($conn->query($sql_po)) {
                $po_id = $conn->insert_id;

                // 1.3 Loop สินค้าที่เลือกใน PO นี้
                if (isset($poData['items']) && is_array($poData['items'])) {
                    foreach ($poData['items'] as $itemId => $itemData) {
                        // ต้องมีการติ๊กเลือก (checked) ถึงจะบันทึก
                        if (isset($itemData['selected']) && $itemData['selected'] == 1) {

                            $buy_qty = floatval($itemData['buy_qty']);
                            $buy_price = floatval($itemData['buy_price']);
                            $note = $conn->real_escape_string($itemData['note'] ?? '');

                            // 1. คำนวณหายอดรวมจากรายการย่อยก่อน (ถ้ามี)
                            $sub_total_sum = 0;
                            if (isset($itemData['sub_items']) && is_array($itemData['sub_items'])) {
                                foreach ($itemData['sub_items'] as $sub) {
                                    $s_qty = floatval($sub['qty'] ?? 0);
                                    $s_price = floatval($sub['price'] ?? 0);
                                    $sub_total_sum += ($s_qty * $s_price);
                                }
                            }

                            // 2. ตัดสินใจว่าจะใช้ยอดเงินไหนบันทึกเป็น Total
                            // ถ้ามียอดรวมจากรายการย่อย ให้ใช้ยอดนั้นเลย (แม้จำนวนหลักจะเป็น 0)
                            if ($sub_total_sum > 0) {
                                $total = $sub_total_sum;
                            } else {
                                // ถ้าไม่มีรายการย่อย ค่อยใช้สูตรปกติ
                                $total = $buy_qty * $buy_price;
                            }

                            // 3. บันทึกลง purchase_order_items
                            $sql_detail = "INSERT INTO purchase_order_items (purchase_order_id, project_item_id, buy_quantity, buy_unit_price, total_price, note)
                   VALUES ('$po_id', '$itemId', '$buy_qty', '$buy_price', '$total', '$note')";
                            $conn->query($sql_detail);

                            $po_item_id = $conn->insert_id;

                            if (isset($itemData['sub_items']) && is_array($itemData['sub_items'])) {
                                foreach ($itemData['sub_items'] as $sub) {
                                    $sub_name = $conn->real_escape_string($sub['name'] ?? '');
                                    $sub_qty = floatval($sub['qty'] ?? 0);
                                    $sub_price = floatval($sub['price'] ?? 0);
                                    $sub_total = $sub_qty * $sub_price;

                                    if (!empty($sub_name)) {
                                        $sql_sub = "INSERT INTO purchase_order_sub_items 
                       (purchase_order_item_id, item_name, quantity, unit_price, total_price)
                       VALUES ('$po_item_id', '$sub_name', '$sub_qty', '$sub_price', '$sub_total')";
                                        $conn->query($sql_sub);
                                    }
                                }
                            }

                            // 1.4 อัปเดตตารางแม่ (project_items)
                            // เพิ่มยอด purchased_quantity และเช็คสถานะ
                            if (isset($itemData['update_progress']) && $itemData['update_progress'] == 1) {

                                // ถ้าติ๊ก -> ให้อัปเดตจำนวนที่เสร็จจริง + เช็คสถานะ
                                $sql_update_main = "UPDATE project_items 
                        SET purchased_quantity = purchased_quantity + $buy_qty,
                            purchase_status = CASE 
                                WHEN (purchased_quantity + $buy_qty) >= quantity THEN 'สั่งซื้อครบแล้ว'
                                ELSE 'สั่งซื้อบางส่วน'
                            END
                        WHERE id = '$itemId'";
                                $conn->query($sql_update_main);

                            } else {
                                // 🟡 ถ้าไม่ติ๊ก (ซื้อแค่อะไหล่) -> ไม่อัปเดต purchased_quantity
                                // แต่อาจจะอัปเดตสถานะเป็น 'สั่งซื้อบางส่วน' เพื่อให้รู้ว่ามีการขยับ
                                $sql_update_status = "UPDATE project_items 
                          SET purchase_status = 'สั่งซื้อบางส่วน' 
                          WHERE id = '$itemId' AND purchase_status != 'สั่งซื้อครบแล้ว'";
                                $conn->query($sql_update_status);
                            }
                        }
                    }
                }
                $success_count++;
            }
        }

        echo "<script>alert('บันทึกใบสั่งซื้อเรียบร้อย $success_count ใบ'); window.location.href='project_details.php?id=$project_id';</script>";
    }
}

// --- 2. ดึงรายการสินค้า และ ประวัติ (Previous Sub-items) ---
$sql_items = "SELECT * FROM project_items WHERE project_id = '$project_id' AND purchased_quantity < quantity AND purchase_status != 'ยกเลิก' ORDER BY id ASC";
$res_items = $conn->query($sql_items);
$items_array = [];

while ($row = $res_items->fetch_assoc()) {
    $itemId = $row['id'];

    // ✅ [จุดแก้ที่ 1] ดึงประวัติรายการย่อย (ชื่อ + ราคาเฉลี่ย)
    // ต้อง JOIN กับ purchase_order_items เพื่อกรองเฉพาะของสินค้านี้
    $sql_prev = "SELECT posi.item_name, SUM(posi.quantity) as total_qty, AVG(posi.unit_price) as avg_price 
                 FROM purchase_order_sub_items posi
                 JOIN purchase_order_items poi ON posi.purchase_order_item_id = poi.id
                 WHERE poi.project_item_id = '$itemId'
                 GROUP BY posi.item_name";

    $res_prev = $conn->query($sql_prev);
    $history = [];
    while ($h = $res_prev->fetch_assoc()) {
        $history[] = [
            'name' => $h['item_name'],
            'qty' => floatval($h['total_qty']),
            'price' => floatval($h['avg_price'])
        ];
    }

    $row['prev_subs'] = $history; // ส่งข้อมูลประวัติไปให้ JS
    $items_array[] = $row;
}
$items_json = json_encode($items_array, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title>Multi-Supplier Purchasing</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        :root {
            --primary-color: #f59e0b;
            /* Amber-500 */
            --primary-dark: #d97706;
            --bg-color: #fef3c7;
            --card-bg: #ffffff;
            --border-color: #fcd34d;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: #fffbeb;
            color: #1e293b;
            padding: 20px;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* --- PO BOX --- */
        .po-box {
            background: #fff;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            margin-bottom: 40px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: slideUp 0.4s ease-out;
            border-top: 6px solid var(--primary-color);
        }

        .po-header {
            background: #fdf6e7;
            /* สีส้มอ่อนๆ */
            padding: 20px;
            border-bottom: 1px solid #fed7aa;
        }

        .po-body {
            padding: 20px;
        }

        .po-title {
            font-weight: 700;
            color: #9a3412;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .grid-responsive {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
        }

        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 5px;
        }

        /* --- Table Styling --- */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .items-table th {
            background: #f1f5f9;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #334155;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .items-table tr:hover {
            background: #f8fafc;
        }

        /* Inputs in Table */
        .qty-input,
        .price-input {
            width: 100px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            text-align: right;
            font-weight: bold;
            color: #059669;
        }

        .qty-input:disabled,
        .price-input:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .progress-bar-bg {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin-top: 5px;
        }

        .progress-bar-fill {
            height: 100%;
            background: #10b981;
            border-radius: 3px;
        }

        /* Buttons */
        .btn-add-po {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
        }

        .btn-add-po:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-submit {
            background: #1e293b;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 700;
            width: 100%;
            font-size: 1.1rem;
            cursor: pointer;
            margin-top: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }

        .btn-submit:hover {
            background: #0f172a;
        }

        .btn-remove-box {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-remove-box:hover {
            background: #ef4444;
            color: white;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hidden {
            display: none;
        }

        .text-muted {
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .disabled-area {
            opacity: 0.3;
            /* จางลง */
            pointer-events: none;
            /* ห้ามกด */
            filter: grayscale(100%);
            /* ขาวดำ */
            transition: all 0.3s;
        }

        .sub-row-hidden {
            display: none !important;
        }
    </style>
</head>

<body>

    <div class="main-container">

        <div class="page-header">
            <div class="page-title"><i class="fas fa-shopping-cart"></i> ทำรายการสั่งซื้อ (Multi-Supplier)</div>
            <a href="project_details.php?id=<?= $project_id ?>" style="color:#64748b; text-decoration:none;"><i
                    class="fas fa-times"></i> ยกเลิก</a>
        </div>

        <form action="" method="POST" enctype="multipart/form-data">

            <div id="poContainer">
            </div>

            <button type="button" class="btn-add-po" onclick="addPOBox()">
                <i class="fas fa-store"></i> เพิ่มร้านค้า / ใบสั่งซื้อ
            </button>

            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> บันทึกการสั่งซื้อทั้งหมด
            </button>
        </form>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <script>
        // 1. รับข้อมูลสินค้าจาก PHP
        const pendingItems = <?php echo $items_json; ?>;
        const currentUser = "<?php echo htmlspecialchars($current_user_name); ?>";
        let poCounter = 0;

        // --- 1. สร้างกล่อง PO (Main Function) ---
        function addPOBox() {
            const container = document.getElementById('poContainer');
            const idx = poCounter++;

            const box = document.createElement('div');
            box.className = 'po-box';

            let itemsHtml = '';
            if (pendingItems.length > 0) {
                pendingItems.forEach(item => {
                    const planQty = parseFloat(item.quantity);
                    const boughtQty = parseFloat(item.purchased_quantity || 0);
                    const remainQty = planQty - boughtQty;
                    const percent = (boughtQty / planQty) * 100;
                    const stdPriceShow = parseFloat(item.standard_price || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });

                    // --- สร้างแถวรายการหลัก (Main Row) ---
                    itemsHtml += `
                <tr id="main-row-${idx}-${item.id}" style="background: #ffffff; border-bottom: 2px solid #f1f5f9;">
    
                    <td style="text-align:center; vertical-align: top; padding-top: 15px;">
                        <input type="checkbox" name="po[${idx}][items][${item.id}][selected]" value="1" 
                            onchange="toggleItem(this, ${idx}, ${item.id})" style="width:18px; height:18px; cursor:pointer;">
                    </td>

                    <td style="vertical-align: top;">
                        <div style="font-weight:600; color:#1e293b; font-size:1rem;">${item.item_name}</div>
                        <div class="text-muted" style="font-size:0.85rem; margin-bottom: 5px;">${item.spec_type || '-'} | หน่วย: ${item.unit}</div>
                        
                        <div id="ctrl-${idx}-${item.id}" class="disabled-area">
                            
                            <div class="bom-option" style="background: #ecfdf5; padding: 4px 8px; border-radius: 6px; border: 1px solid #a7f3d0; display: inline-block; margin-bottom: 5px;">
                                <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: #047857; margin: 0;">
                                    <input type="checkbox" name="po[${idx}][items][${item.id}][use_sub_only]" value="1" 
                                        onclick="autoOpenSub(${idx}, ${item.id}, this)"
                                        style="accent-color: #10b981;">
                                    <span><i class="fas fa-tools"></i> ซื้อส่วนประกอบ (ซ่อนชื่อหลัก)</span>
                                </label>
                            </div>

                            <div>
                                <a href="javascript:void(0)" onclick="toggleSubRows(${idx}, ${item.id})" 
                                style="font-size: 0.85rem; color: #3b82f6; text-decoration: none; border-bottom: 1px dashed #3b82f6; display:inline-flex; align-items:center; gap:5px;">
                                    <i class="fas fa-list-ul"></i> จัดการรายการย่อย
                                </a>
                            </div>
                        </div>
                    </td>

                    <td style="width:200px; vertical-align: top; padding-top: 15px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem;">
                            <span>แผน: <b>${planQty}</b></span>
                            <span>ซื้อแล้ว: <b>${boughtQty}</b></span>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:${percent}%"></div></div>
                        <div style="text-align:right; font-size:0.8rem; color:#d97706;">เหลือต้องซื้อ: ${remainQty}</div>
                    </td>
                    
                    <td style="text-align:right; font-weight:600; color:#d97706; vertical-align: top; padding-top: 15px;">
                        ${stdPriceShow}
                    </td>

                    <td style="text-align:center; vertical-align: top; padding-top: 15px;">
                        
                        <input type="number" step="0.01" name="po[${idx}][items][${item.id}][buy_qty]" 
                            class="qty-input main-qty" 
                            oninput="calcSubToMain(${idx}, ${item.id})"
                            placeholder="กรอกจำนวน"
                            disabled 
                            required>
                        
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">(จำนวนชุด)</div>

                        <div style="margin-top: 8px; background: #fff7ed; padding: 4px; border-radius: 4px; border: 1px dashed #fdba74;">
                            <label style="font-size: 0.75rem; color: #c2410c; display: flex; align-items: center; justify-content: center; gap: 4px; cursor: pointer;">
                                <input type="checkbox" name="po[${idx}][items][${item.id}][update_progress]" value="1">
                                <span>นับยอดเสร็จ</span>
                            </label>
                        </div>

                    </td>

                    <td style="text-align:center; vertical-align: top; padding-top: 15px;">
    <input type="number" step="0.01" name="po[${idx}][items][${item.id}][buy_price]" 
           class="price-input main-price" 
           placeholder="จำนวนเงิน" 
           oninput="calcSubToMain(${idx}, ${item.id})"
           required readonly 
           style="background-color: #f3f4f6; color: #334155; font-weight: 600;">
           
    <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">(ต้นทุนเฉลี่ย/ชุด)</div>
</td>
                </tr>

                <tr id="sub-footer-${idx}-${item.id}" style="display:none; background-color: #fff7ed;">
                    <td></td>
                    <td colspan="5" style="padding:10px 20px; border-bottom:2px solid #fed7aa;">
                        
                        <div id="history-list-${idx}-${item.id}" style="margin-bottom: 12px;"></div>

                        <div style="padding-left: 0px; font-size: 0.85rem; color: #c2410c; margin-bottom:5px; font-weight:700;">
                            <i class="fas fa-plus-circle"></i> รายการที่จะซื้อเพิ่มรอบนี้:
                        </div>
                        <button type="button" onclick="addSubItemRow(${idx}, ${item.id})" 
                                style="background: #ffedd5; border: 1px dashed #f97316; color: #ea580c; padding: 6px 15px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                            <i class="fas fa-plus"></i> เพิ่มรายการย่อย
                        </button>
                    </td>
                </tr>
                `;
                });
            } else {
                itemsHtml = '<tr><td colspan="6" style="text-align:center; padding:20px;">ไม่มีรายการที่ต้องสั่งซื้อแล้ว (ครบตามแผน)</td></tr>';
            }

            box.innerHTML = `
        <div class="po-header">
            <div class="po-title">
                <span><i class="fas fa-file-invoice-dollar"></i> ใบสั่งซื้อที่ ${idx + 1}</span>
                <button type="button" class="btn-remove-box" onclick="this.closest('.po-box').remove()"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="grid-responsive">
                <div class="form-group">
                    <label>ร้านค้า / Supplier *</label>
                    <input type="text" name="po[${idx}][supplier_name]" class="form-control" placeholder="ชื่อร้านค้า..." required>
                </div>
                <div class="form-group">
                    <label>ผู้สั่งซื้อ</label>
                    <input type="text" name="po[${idx}][orderer_name]" class="form-control" value="${currentUser}" readonly style="background:#f3f4f6;">
                </div>
                <div class="form-group">
                    <label>วันที่สั่ง</label>
                    <input type="text" name="po[${idx}][order_date]" class="form-control datepicker-input">
                </div>
                <div class="form-group">
                    <label>วันรับของ</label>
                    <input type="text" name="po[${idx}][delivery_date]" class="form-control datepicker-input">
                </div>
            </div>
            <div class="grid-responsive">
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> เงื่อนไขส่งของ</label>
                    <select name="po[${idx}][conditions]" class="form-control">
                        <option value="ส่งคลัง">🏠 ส่งคลัง</option>
                        <option value="ส่งหน้างาน">👷 ส่งหน้างาน</option>
                        <option value="รับเอง">🚗 รับเอง</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>แนบเอกสารPO</label>
                    <input type="file" name="po[${idx}][slip_file]" class="form-control">
                </div>
            </div>
            <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e40af; background: #e0e7ff; padding: 8px 12px; border-radius: 8px; width: 100%;">
                    <input type="checkbox" name="po[${idx}][has_tax_invoice]" value="1" style="width: 18px; height: 18px;">
                    <i class="fas fa-file-invoice"></i> มีใบกำกับภาษี
                </label>
            </div>
        </div>

        <div class="po-body">
            <div style="font-weight:700; margin-bottom:10px; color:#475569;">เลือกรายการที่จะสั่งกับร้านนี้:</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="5%" style="text-align:center;">เลือก</th>
                        <th width="30%">สินค้า</th>
                        <th width="20%">สถานะยอด (Plan vs Actual)</th>
                        <th width="15%" style="text-align:right;">ราคากลาง</th>
                        <th width="15%" style="text-align:center;">จำนวนที่จะซื้อ</th>
                        <th width="15%" style="text-align:center;">ราคาซื้อจริง/หน่วย</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>
        </div>
        `;

            container.appendChild(box);
            flatpickr(box.querySelectorAll('.datepicker-input'), {
                dateFormat: "Y-m-d",
                locale: "th"
            });
            if (poCounter > 1) box.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });
        }

        // --- 2. เปิด/ปิด แถวรายการย่อย ---
        function toggleSubRows(poIdx, itemId) {
            const footerRow = document.getElementById(`sub-footer-${poIdx}-${itemId}`);

            // เช็คก่อนว่าเปิดหรือปิด
            if (footerRow.style.display === 'none') {
                footerRow.style.display = 'table-row'; // สั่งเปิด

                // ✅ [จุดแก้ที่ 3] ดึงข้อมูลมาโชว์ (แก้ให้ตรงกับตัวแปร PHP)
                const itemData = pendingItems.find(i => i.id == itemId);
                const historyDiv = document.getElementById(`history-list-${poIdx}-${itemId}`);

                if (itemData && itemData.prev_subs && itemData.prev_subs.length > 0) {
                    let hHtml = `
                    <div style="font-size: 0.8rem; color: #9a3412; font-weight: 700; margin-bottom: 5px;">
                        <i class="fas fa-history"></i> ประวัติรายการที่เคยสั่งซื้อไปแล้ว:
                    </div>
                    <table style="width:100%; font-size:0.85rem; background: #fff; border: 1px solid #fed7aa; border-radius: 6px; margin-bottom: 10px; border-collapse: collapse;">
                        <tr style="background:#ffedd5; color:#9a3412;">
                            <th style="padding:5px; text-align:left;">รายการ</th>
                            <th style="padding:5px; text-align:right;">ราคาเฉลี่ย</th>
                        </tr>
                    `;

                    itemData.prev_subs.forEach(prev => {
                        hHtml += `
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:5px 10px;">${prev.name} (รวม ${parseFloat(prev.qty).toLocaleString()})</td>
                            <td style="padding:5px 10px; text-align:right;">${parseFloat(prev.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        </tr>`;
                    });

                    hHtml += `</table>`;
                    historyDiv.innerHTML = hHtml;
                } else {
                    historyDiv.innerHTML = ''; // ไม่มีประวัติก็เคลียร์ทิ้ง
                }
            } else {
                footerRow.style.display = 'none'; // สั่งปิด
            }
        }

        // --- 3. เพิ่มแถวรายการย่อย (แทรก Row ในตารางหลัก) ---
        function addSubItemRow(poIdx, itemId) {
            const footerRow = document.getElementById(`sub-footer-${poIdx}-${itemId}`);
            // นับจำนวนเพื่อสร้าง index (ใช้ Date.now() มาช่วยกันเลขซ้ำ กรณีลบแล้วเพิ่มใหม่)
            const subIdx = document.querySelectorAll(`.sub-row-${poIdx}-${itemId}`).length + "_" + Date.now();

            const tr = document.createElement('tr');
            tr.className = `sub-row-${poIdx}-${itemId}`;
            tr.style.backgroundColor = '#fff7ed';

            tr.innerHTML = `
            <td></td>
            <td style="padding-left: 30px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-angle-right" style="color:#f97316;"></i>
                    
                    <input type="text" 
                           name="po[${poIdx}][items][${itemId}][sub_items][${subIdx}][name]" 
                           class="form-control" 
                           style="font-size:0.9rem; padding:6px;" 
                           placeholder="ระบุชื่อรายการย่อย/อะไหล่..." 
                           required> 
                </div>
            </td>
            <td></td> <td></td> 
            
            <td style="text-align:center;">
                <input type="number" step="0.01" min="0.01" 
                       name="po[${poIdx}][items][${itemId}][sub_items][${subIdx}][qty]" 
                       class="qty-input sub-qty-${poIdx}-${itemId}" 
                       style="background:#fff; border-color:#fdba74;"
                       placeholder="กรอกจำนวน"
                       oninput="calcSubToMain(${poIdx}, ${itemId})"
                       required> 
            </td>

            <td style="text-align:center; position:relative;">
                <input type="number" step="0.01" min="0.01" 
                       name="po[${poIdx}][items][${itemId}][sub_items][${subIdx}][price]" 
                       class="price-input sub-price-${poIdx}-${itemId}" 
                       style="background:#fff; border-color:#fdba74;"
                       placeholder="0.00"
                       oninput="calcSubToMain(${poIdx}, ${itemId})"
                       required> 
                
                <button type="button" onclick="removeSubItem(this, ${poIdx}, ${itemId})" 
                        style="position:absolute; right: -25px; top: 50%; transform: translateY(-50%); border:none; background:none; color:#ef4444; cursor:pointer;" title="ลบรายการนี้">
                    <i class="fas fa-times-circle"></i>
                </button>
            </td>
            `;

            // แทรกก่อนหน้า Footer Row
            footerRow.parentNode.insertBefore(tr, footerRow);
        }

        // --- 4. ลบรายการย่อย ---
        function removeSubItem(btn, poIdx, itemId) {
            btn.closest('tr').remove();
            calcSubToMain(poIdx, itemId); // คำนวณใหม่หลังลบ
        }

        // --- 5. สูตรคำนวณเทพ: รวมราคาย่อย -> หารจำนวนหลัก -> ใส่ราคาหลัก ---
        function calcSubToMain(poIdx, itemId) {
            // 1. หา Input ของรายการแม่
            const mainQtyInput = document.querySelector(`input[name="po[${poIdx}][items][${itemId}][buy_qty]"]`);
            const mainPriceInput = document.querySelector(`input[name="po[${poIdx}][items][${itemId}][buy_price]"]`);

            // 2. หา Input ของรายการลูกทั้งหมด
            const subQtys = document.querySelectorAll(`.sub-qty-${poIdx}-${itemId}`);
            const subPrices = document.querySelectorAll(`.sub-price-${poIdx}-${itemId}`);

            // 3. เริ่มคำนวณ
            let totalSubCost = 0;
            let hasActiveSub = false; // เช็คว่ามีการกรอกตัวเลขในลูกไหม

            // วนลูปบวกยอดเงิน (Qty x Price) ของลูกทุกตัว
            if (subQtys.length > 0) {
                subQtys.forEach((qInput, i) => {
                    const qty = parseFloat(qInput.value) || 0;
                    const price = parseFloat(subPrices[i].value) || 0;

                    if (qty > 0) hasActiveSub = true; // เจอว่ามีการกรอกจำนวนลูก

                    totalSubCost += (qty * price);
                });
            }

            // -----------------------------------------------------------
            // 🔴 [จุดที่เพิ่ม] ถ้ามีการกรอกลูก แต่ตัวแม่เป็น 0 หรือว่าง -> ใส่ 1 ให้เลย
            // -----------------------------------------------------------
            if (hasActiveSub) {
                const currentMainQty = parseFloat(mainQtyInput.value) || 0;
                if (currentMainQty === 0) {
                    mainQtyInput.value = 1;
                }
            }
            // -----------------------------------------------------------

            // 4. รับค่าจำนวนแม่ (หลังจาก Auto 1 แล้ว)
            const mainQty = parseFloat(mainQtyInput.value) || 0;

            // 5. แสดงผลราคาต่อหน่วยที่ตัวแม่
            if (subQtys.length > 0) {
                // ถ้ามีลูก -> ล็อคราคาแม่ (ให้คำนวณเอา)
                mainPriceInput.readOnly = true;
                mainPriceInput.style.backgroundColor = "#f3f4f6";
                mainPriceInput.style.color = "#059669";
                mainPriceInput.style.fontWeight = "bold";

                if (mainQty > 0) {
                    // สูตร: ต้นทุนรวม ÷ จำนวนแม่ = ราคาต่อหน่วย
                    const unitPrice = totalSubCost / mainQty;
                    mainPriceInput.value = unitPrice.toFixed(2);
                } else {
                    // ถ้าจำนวนแม่เป็น 0 (กรณีลบเลขทิ้ง) ให้ราคาเป็น 0
                    mainPriceInput.value = "0.00";
                }
            } else {
                // ถ้าไม่มีลูก -> ปลดล็อกให้พิมพ์เองได้
                mainPriceInput.readOnly = false;
                mainPriceInput.style.backgroundColor = "#ffffff";
                mainPriceInput.style.color = "#1e293b";
            }
        }

        // --- 6. เมื่อเปลี่ยนจำนวนสินค้าหลัก ให้คำนวณราคาต่อหน่วยใหม่ด้วย ---
        function reCalcMainPrice(poIdx, itemId) {
            // เช็คก่อนว่ามีรายการย่อยไหม ถ้ามีให้คำนวณใหม่
            const subRows = document.querySelectorAll(`.sub-row-${poIdx}-${itemId}`);
            if (subRows.length > 0) {
                calcSubToMain(poIdx, itemId);
            }
        }

        // --- 7. ฟังก์ชันเปิด/ปิด เมื่อติ๊กเลือกรายการหลัก ---
        function toggleItem(checkbox, poIdx, itemId) {
            const row = document.getElementById(`main-row-${poIdx}-${itemId}`);
            const qtyInput = row.querySelector('.main-qty');
            const priceInput = row.querySelector('.main-price');
            const ctrlDiv = document.getElementById(`ctrl-${poIdx}-${itemId}`);
            const footer = document.getElementById(`sub-footer-${poIdx}-${itemId}`);
            const subRows = document.querySelectorAll(`.sub-row-${poIdx}-${itemId}`);

            if (checkbox.checked) {
                // ✅ ติ๊กเลือก -> ปลดล็อค
                qtyInput.disabled = false;
                priceInput.disabled = false;

                // ถ้าไม่มีรายการย่อย ให้ปลด readonly ราคาด้วย
                if (subRows.length === 0) {
                    priceInput.readOnly = false;
                    priceInput.style.backgroundColor = "#ffffff";
                }

                if (ctrlDiv) ctrlDiv.classList.remove('disabled-area');

            } else {
                // ❌ เอาออก -> ล็อคทุกอย่าง
                qtyInput.disabled = true;
                priceInput.disabled = true;
                priceInput.readOnly = true;
                priceInput.style.backgroundColor = "#f3f4f6";

                if (ctrlDiv) ctrlDiv.classList.add('disabled-area');

                // ซ่อนส่วนรายการย่อย
                if (footer) footer.style.display = 'none';

                // ซ่อนแถวลูกที่เคยสร้างไว้
                subRows.forEach(r => {
                    r.classList.add('sub-row-hidden');
                    // ต้อง disabled input ลูกด้วย กันค่าส่งไปมั่ว
                    r.querySelectorAll('input').forEach(i => i.disabled = true);
                });
            }
        }

        // Auto Run
        document.addEventListener('DOMContentLoaded', function () {
            addPOBox();
        });

        function autoOpenSub(poIdx, itemId, checkbox) {
            const subRows = document.querySelectorAll(`.sub-row-${poIdx}-${itemId}`);

            // ถ้าติ๊กถูก -> ให้เปิดกล่องรายการย่อยอัตโนมัติ (ถ้ายังไม่เปิด)
            if (checkbox.checked) {
                // เช็คว่า Footer เปิดอยู่ไหม ถ้าไม่เปิดให้สั่งเปิด
                const footerRow = document.getElementById(`sub-footer-${poIdx}-${itemId}`);
                if (footerRow.style.display === 'none') {
                    toggleSubRows(poIdx, itemId);
                }
            }
        }
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                // ตรวจสอบว่าไม่ได้อยู่ใน textarea
                if (e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    return false;
                }
            }
        }, false);
    </script>

</body>

</html>