<?php
session_start();
require_once 'db_connect.php';

$pre_select_id = isset($_GET['project_id']) ? $_GET['project_id'] : '';

// --- 1. Logic การบันทึกข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['docs']) && is_array($_POST['docs'])) {
        $success_count = 0;
        $error_msg = "";

        foreach ($_POST['docs'] as $docIndex => $docData) {
            $p_id = $docData['project_id'] ?? '';
            $doc_no = $conn->real_escape_string($docData['doc_no'] ?? '');
            $status = $docData['purchase_status'] ?? 'รอสั่งซื้อ';

            if (isset($docData['items']) && is_array($docData['items'])) {
                foreach ($docData['items'] as $itemIndex => $itemData) {
                    $name = $conn->real_escape_string($itemData['item_name'] ?? '');

                    if (!empty($p_id) && !empty($name)) {
                        // รับค่าพื้นฐาน
                        $unit = $conn->real_escape_string($itemData['unit'] ?? '');
                        $qty = floatval($itemData['quantity'] ?? 0);
                        $cost = floatval($itemData['cost_price'] ?? 0);
                        $std = floatval($itemData['standard_price'] ?? 0);

                        // 🟢 รับค่าประเภทรายการ
                        $item_type = $conn->real_escape_string($itemData['item_type'] ?? 'product');

                        // 🟢 [ใหม่] รับค่าสถานะการจัดซื้อรายตัว (ถ้าไม่มี ให้ยึดตามหน้าเอกสารรวม)
                        $item_status = isset($itemData['purchase_status']) ? $conn->real_escape_string($itemData['purchase_status']) : $status;

                        // 🟢 บันทึกข้อมูล
                        $sql = "INSERT INTO project_items (
                            project_id, doc_no, purchase_status,
                            item_name, unit, quantity, cost_price, standard_price, 
                            item_type,
                            created_at
                        ) VALUES (
                            '$p_id', '$doc_no', '$item_status', /* ใช้สถานะรายตัว */
                            '$name', '$unit', '$qty', '$cost', '$std', 
                            '$item_type',
                            NOW()
                        )";

                        if ($conn->query($sql)) {
                            $success_count++;

                            // 🟢 [แก้ไขจุดนี้] เช็คประเภทก่อนบันทึกลงตาราง Master
                            if ($item_type === 'expense') {
                                // ถ้าเป็นค่าใช้จ่าย -> ลงตาราง setup_expenses
                                $clean_name = $conn->real_escape_string($name);
                                $sql_master = "INSERT IGNORE INTO setup_expenses (expense_name) VALUES ('$clean_name')";
                            } else {
                                // ถ้าเป็นสินค้า -> ลงตาราง setup_products
                                $clean_name = $conn->real_escape_string($name);
                                $sql_master = "INSERT IGNORE INTO setup_products (item_name) VALUES ('$clean_name')";
                            }
                            $conn->query($sql_master); // บันทึกชื่อเข้าคลัง Master Data

                        } else {
                            $error_msg .= "Error: " . $conn->error . "<br>";
                        }
                    }
                }
            }
        }

        // ... (ส่วนแจ้งเตือนผลลัพธ์เหมือนเดิม) ...
        if ($success_count > 0 && empty($error_msg)) {
            echo "<script>alert('บันทึกสำเร็จ $success_count รายการ'); window.location.href='project_dashboard.php';</script>";
        } else {
            echo "<script>alert('เกิดข้อผิดพลาด: $error_msg');</script>";
        }
    }
}

// --- ดึงข้อมูล Master Data ---
$projects_opt = [];
$res = $conn->query("SELECT id, project_name FROM projects WHERE status != 'ยกเลิก' ORDER BY created_at DESC");
while ($row = $res->fetch_assoc())
    $projects_opt[] = $row;

// ✅ เอา Users Query ออกแล้ว

$units_opt = [];
$res_unit = $conn->query("SELECT unit_name FROM setup_units ORDER BY unit_name ASC");
while ($row_ut = $res_unit->fetch_assoc())
    $units_opt[] = $row_ut['unit_name'];

$products_history = [];
$res_prod = $conn->query("SELECT item_name FROM setup_products ORDER BY item_name ASC");
while ($row_p = $res_prod->fetch_assoc())
    $products_history[] = $row_p['item_name'];

$expenses_history = [];
$res_exp = $conn->query("SELECT expense_name FROM setup_expenses ORDER BY expense_name ASC");
while ($row_e = $res_exp->fetch_assoc())
    $expenses_history[] = $row_e['expense_name'];

$status_opt = [];
$res_status = $conn->query("SELECT status_name FROM setup_purchase_status ORDER BY seq ASC");
while ($row_st = $res_status->fetch_assoc())
    $status_opt[] = $row_st['status_name'];
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title>บันทึกรายการสินค้า</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* CSS Style (คงเดิม) */
        :root {
            --primary-color: #4338ca;
            --primary-light: #e0e7ff;
            --secondary-color: #475569;
            --success-color: #059669;
            --danger-color: #dc2626;
            --bg-color: #eef2f6;
            --card-bg: #ffffff;
            --border-color: #cbd5e1;
            --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: var(--bg-color);
            color: #1e293b;
            padding: 30px 20px;
            margin: 0;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .card-form {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 35px;
            box-shadow: var(--box-shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-close-page {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 10px;
            background: #f1f5f9;
        }

        .btn-close-page:hover {
            background: #fee2e2;
            color: var(--danger-color);
        }

        .mode-wrapper {
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            padding: 20px 30px;
            border-radius: 18px;
            margin-bottom: 35px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 56px;
            height: 30px;
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
            background-color: #94a3b8;
            transition: .4s;
            border-radius: 34px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        input:checked+.slider {
            background-color: var(--primary-color);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        .mode-text {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .doc-box {
            background: #f8fafc;
            border-radius: 20px;
            box-shadow: var(--box-shadow-lg);
            margin-bottom: 40px;
            border: 2px solid var(--border-color);
            position: relative;
            animation: slideIn 0.4s ease-out;
        }

        .doc-box.multi-card {
            border-color: var(--primary-light);
            border-left: 10px solid var(--primary-color);
        }

        .doc-header {
            background: linear-gradient(to bottom, #ffffff, #f1f5f9);
            padding: 25px 30px;
            border-bottom: 2px solid var(--border-color);
        }

        .doc-header-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-area {
            padding: 30px;
            background: #f1f5f9;
            border-radius: 0 0 20px 20px;
            box-shadow: inset 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .product-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.3s ease-in;
        }

        .product-box:focus-within {
            z-index: 50;
            /* เมื่อคลิกหรือพิมพ์ในกล่องนี้ ให้มันลอยขึ้นมาทับเพื่อน */
            border-color: var(--primary-color);
            /* (แถม) เปลี่ยนสีขอบให้รู้ว่ากำลังใช้อันนี้ */
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .product-box:hover {
            border-color: var(--primary-light);
            box-shadow: var(--box-shadow-lg);
            transform: translateY(-4px) scale(1.005);
        }

        .grid-responsive {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            align-items: end;
        }

        .grid-full {
            grid-column: 1 / -1;
        }

        .grid-prices {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }

        @media (max-width: 1000px) {
            .grid-prices {
                grid-template-columns: 1fr 1fr;
            }
        }

        .form-group {
            margin-bottom: 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 0.95rem;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            background: #f9fafb;
            font-family: 'Prompt', sans-serif;
            font-size: 1rem;
            transition: 0.2s;
            box-sizing: border-box;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(67, 56, 202, 0.15);
        }

        .input-readonly {
            background-color: #e2e8f0;
            color: #64748b;
            border-color: #cbd5e1;
            font-weight: 700;
            cursor: not-allowed;
        }

        .input-highlight {
            background-color: #ecfdf5;
            border-color: #34d399;
            color: var(--success-color);
            font-weight: 700;
        }

        .btn {
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            font-family: 'Prompt', sans-serif;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            font-size: 1rem;
        }

        .btn-add-product {
            background: linear-gradient(to right, #e0e7ff, #c7d2fe);
            color: var(--primary-color);
            width: 100%;
            border: 2px dashed #a5b4fc;
        }

        .btn-add-product:hover {
            background: linear-gradient(to right, #c7d2fe, #a5b4fc);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .btn-add-doc {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            box-shadow: 0 4px 10px rgba(67, 56, 202, 0.4);
            margin-bottom: 50px;
            padding: 15px 30px;
            font-size: 1.1rem;
        }

        .btn-add-doc:hover {
            box-shadow: 0 6px 15px rgba(67, 56, 202, 0.5);
            transform: translateY(-3px);
        }

        .btn-save-all {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
            font-size: 1.3rem;
            padding: 18px;
            width: 100%;
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.3);
            border-radius: 16px;
        }

        .btn-save-all:hover {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            transform: translateY(-3px);
            box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.4);
        }

        .btn-remove {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: #fee2e2;
            color: var(--danger-color);
            position: absolute;
            transition: 0.3s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 2px solid #fecaca;
        }

        .btn-remove:hover {
            background: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
            transform: rotate(90deg) scale(1.1);
        }

        .remove-doc {
            top: 25px;
            right: 25px;
            z-index: 2;
        }

        .remove-prod {
            top: -15px;
            right: -15px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .hidden {
            display: none !important;
        }

        .autocomplete-wrapper {
            position: relative;
            width: 100%;
        }

        .autocomplete-input {
            /* Style เดียวกับ form-control แต่เพิ่ม icon */
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%2394a3b8" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-height: 250px;
            overflow-y: auto;
            display: none;
            /* ซ่อนไว้ก่อน */
            z-index: 100;
            margin-top: 5px;
            animation: slideDown 0.2s ease-out;
        }

        .autocomplete-list div {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
            color: #334155;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .autocomplete-list div:last-child {
            border-bottom: none;
        }

        .autocomplete-list div:hover {
            background-color: #f8fafc;
            color: var(--primary-color);
            padding-left: 20px;
            /* ขยับขวาเล็กน้อยเมื่อ Hover */
            font-weight: 600;
        }

        .autocomplete-list div i {
            color: #cbd5e1;
            font-size: 0.8rem;
        }

        /* Scrollbar สวยๆ */
        .autocomplete-list::-webkit-scrollbar {
            width: 6px;
        }

        .autocomplete-list::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .autocomplete-list::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 10px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hidden-field {
            display: none !important;
        }

        .product-only {
            transition: all 0.3s ease;
        }
    </style>
</head>

<body>

    <datalist id="product_history_list">
        <?php foreach ($products_history as $p_name): ?>
            <option value="<?= htmlspecialchars($p_name) ?>">
            <?php endforeach; ?>
    </datalist>

    <div class="main-container">
        <div class="card-form">

            <div class="page-header">
                <div class="page-title"><i class="fas fa-layer-group"></i> บันทึกรายการสินค้า / ลงทุน</div>
                <a href="project_dashboard.php" class="btn-close-page"><i class="fas fa-times-circle fa-lg"></i>
                    ปิดหน้าต่าง</a>
            </div>

            <form action="" method="POST">

                <div class="mode-wrapper">
                    <label class="switch">
                        <input type="checkbox" id="modeToggle" onchange="toggleMode()">
                        <span class="slider"></span>
                    </label>
                    <div id="modeText" class="mode-text"><i class="fas fa-file"></i> โหมด: หน้างานเดียว (Single Project)
                    </div>
                </div>

                <div id="docsContainer"></div>

                <button type="button" id="btnAddDoc" class="btn btn-add-doc hidden" onclick="addDocBox()">
                    <i class="fas fa-folder-plus fa-lg"></i> เพิ่มชุดเอกสาร/หน้างานใหม่
                </button>

                <button type="submit" class="btn btn-save-all"><i class="fas fa-save fa-lg"></i>
                    บันทึกข้อมูลทั้งหมดลงฐานข้อมูล</button>
            </form>
        </div>
    </div>

    <script>
        // 1. ข้อมูลสำหรับ Autocomplete
        const productList = <?php echo json_encode($products_history); ?>;
        const expenseList = <?php echo json_encode($expenses_history); ?>;

        // 2. Options ต่างๆ
        const projectOptions = `
        <option value="">-- กรุณาเลือกหน้างาน --</option>
        <?php foreach ($projects_opt as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($p['id'] == $pre_select_id) ? 'selected' : '' ?>>
                [<?= $p['id'] ?>] <?= addslashes($p['project_name']) ?>
            </option>
        <?php endforeach; ?>
    `;

        const unitOptions = `
        <option value="">- ระบุหน่วย -</option>
        <?php foreach ($units_opt as $u_val): ?>
            <option value="<?= htmlspecialchars($u_val) ?>"><?= htmlspecialchars($u_val) ?></option>
        <?php endforeach; ?>
    `;

        const statusOptions = `
        <?php foreach ($status_opt as $st_val): ?>
            <option value="<?= htmlspecialchars($st_val) ?>" <?= $st_val == 'รอสั่งซื้อ' ? 'selected' : '' ?>>
                <?= htmlspecialchars($st_val) ?>
            </option>
        <?php endforeach; ?>
    `;

        let docCounter = 0;

        // --- ✅ 1. ฟังก์ชันปิด Dropdown (ย้ายมาไว้ข้างนอก เป็น Global) ---
        function closeAllLists(elmnt) {
            var x = document.getElementsByClassName("autocomplete-list");
            for (var i = 0; i < x.length; i++) {
                // เช็คว่าสิ่งที่กด ไม่ใช่ตัว List เอง
                if (elmnt != x[i]) {
                    // เช็คว่าสิ่งที่กด ไม่ใช่ Input ที่เป็นเจ้าของ List นี้
                    // (List อยู่ใน Wrapper เดียวกับ Input ดังนั้นเราหา Input ได้จาก parent)
                    var inputOwner = x[i].parentNode.querySelector('input');

                    if (elmnt != inputOwner) {
                        x[i].parentNode.removeChild(x[i]);
                    }
                }
            }
        }

        // --- ✅ 2. ตัวดักจับการคลิกหน้าจอ (มีตัวเดียวพอ) ---
        document.addEventListener("click", function (e) {
            closeAllLists(e.target);
        });


        // --- Toggle Mode ---
        function toggleMode() {
            const isMulti = document.getElementById('modeToggle').checked;
            const btnAddDoc = document.getElementById('btnAddDoc');
            const container = document.getElementById('docsContainer');
            const modeText = document.getElementById('modeText');

            if (isMulti) {
                modeText.innerHTML = '<i class="fas fa-copy"></i> โหมด: หลายหน้างาน (Multi Projects)';
                btnAddDoc.classList.remove('hidden');
                Array.from(container.children).forEach(box => {
                    box.classList.add('multi-card');
                    box.querySelector('.remove-doc').classList.remove('hidden');
                });
            } else {
                modeText.innerHTML = '<i class="fas fa-file-alt"></i> โหมด: หน้างานเดียว (Single Project)';
                btnAddDoc.classList.add('hidden');
                container.innerHTML = '';
                docCounter = 0;
                addDocBox(false);
            }
        }

        // --- Add Doc Box ---
        function addDocBox(isMultiCard = true) {
            const container = document.getElementById('docsContainer');
            const docIdx = docCounter++;

            const box = document.createElement('div');
            box.className = `doc-box ${isMultiCard ? 'multi-card' : ''}`;

            const removeBtn = isMultiCard ?
                `<button type="button" class="btn btn-remove remove-doc" onclick="this.closest('.doc-box').remove()" title="ลบชุดเอกสารนี้"><i class="fas fa-times"></i></button>` :
                `<button type="button" class="btn btn-remove remove-doc hidden" onclick="this.closest('.doc-box').remove()"><i class="fas fa-times"></i></button>`;

            box.innerHTML = `
            ${removeBtn}
            <div class="doc-header">
                <div class="doc-header-title"><i class="fas fa-folder-open"></i> ข้อมูลเอกสาร & หน้างาน</div>
                <div class="grid-responsive" style="margin-bottom: 30px;">
                    <div class="form-group grid-full">
                        <label style="color:#d97706; font-size:1.05rem;"><i class="fas fa-map-marker-alt"></i> เลือกหน้างาน *</label>
                        <select name="docs[${docIdx}][project_id]" class="form-select" style="border-color:#fdba74; background:#fff7ed; font-weight:600;" required>
                            ${projectOptions}
                        </select>
                    </div>
                </div>
                <div class="grid-responsive">
                    <div class="form-group">
                        <label>เลขที่เอกสาร (PO) *</label>
                        <input type="text" name="docs[${docIdx}][doc_no]" class="form-control" placeholder="PO-XXXX" required>
                    </div>
                    <div class="form-group">
                        <label>สถานะจัดซื้อ</label>
                        <select name="docs[${docIdx}][purchase_status]" class="form-select">${statusOptions}</select>
                    </div>
                </div>
            </div>
            <div class="product-area">
                <div style="font-weight:800; color:var(--primary-color); margin-bottom:25px; display:flex; align-items:center; gap:10px; font-size:1.2rem;">
                    <i class="fas fa-boxes"></i> รายการสินค้าในชุดนี้
                </div>
                <div class="product-list-wrapper"></div>
                <button type="button" class="btn btn-add-product" onclick="addProductRow(this, ${docIdx})">
                    <i class="fas fa-plus-circle fa-lg"></i> เพิ่มสินค้า
                </button>
            </div>
        `;

            container.appendChild(box);
            addProductRow(box.querySelector('.btn-add-product'), docIdx);
            if (docCounter > 1) box.scrollIntoView({ behavior: "smooth", block: "start" });
        }

        // --- Add Product Row ---
        // --- Add Product Row (ปรับปรุงใหม่ รองรับสินค้า/ค่าใช้จ่าย) ---
        function addProductRow(btn, docIdx) {
            const wrapper = btn.previousElementSibling;
            const itemIdx = wrapper.children.length;

            const row = document.createElement('div');
            row.className = 'product-box';

            // Auto ID
            const inputID = `prod_auto_${docIdx}_${itemIdx}_${Math.floor(Math.random() * 1000)}`;
            const rowID = `row_${docIdx}_${itemIdx}`; // ID เฉพาะของแถวนี้

            row.setAttribute('id', rowID);

            row.innerHTML = `
            <button type="button" class="btn btn-remove remove-prod" onclick="this.parentElement.remove()" title="ลบสินค้านี้"><i class="fas fa-times"></i></button>
            
            <div class="form-group mb-3" style="margin-bottom: 15px;">
                <label class="form-label fw-bold" style="color:var(--primary-color);">ประเภทรายการ</label>
                <div class="d-flex gap-4" style="display:flex; gap: 20px;">
                    <label class="d-flex align-items-center gap-2 cursor-pointer" style="cursor:pointer; display:flex; align-items:center; gap:5px;">
                        <input type="radio" name="docs[${docIdx}][items][${itemIdx}][item_type]" value="product" checked onchange="toggleItemType('${rowID}')">
                        <span class="badge bg-primary rounded-pill px-3 py-2" style="background:#e0e7ff; color:#4338ca; padding:5px 10px; border-radius:15px; font-size:0.85rem;"><i class="fas fa-box"></i> สินค้า/วัสดุ</span>
                    </label>
                    <label class="d-flex align-items-center gap-2 cursor-pointer" style="cursor:pointer; display:flex; align-items:center; gap:5px;">
                        <input type="radio" name="docs[${docIdx}][items][${itemIdx}][item_type]" value="expense" onchange="toggleItemType('${rowID}')">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2" style="background:#fef3c7; color:#d97706; padding:5px 10px; border-radius:15px; font-size:0.85rem;"><i class="fas fa-file-invoice-dollar"></i> ค่าใช้จ่าย/บริการ</span>
                    </label>
                </div>
            </div>

            <div class="grid-responsive" style="margin-bottom: 20px;">
                <div class="form-group" style="grid-column: span 2;"> 
                    <label id="label-item-name-${rowID}">รายการสินค้า *</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="${inputID}" name="docs[${docIdx}][items][${itemIdx}][item_name]" 
                               class="form-control autocomplete-input" placeholder="ระบุชื่อสินค้า..." required style="font-weight:600;" autocomplete="off">
                    </div>
                </div>
                <div class="form-group product-only">
                    <label>หน่วยนับ</label>
                    <select id="unit-${rowID}" name="docs[${docIdx}][items][${itemIdx}][unit]" class="form-select">${unitOptions}</select>
                </div>
            </div>

            <div class="grid-prices">
                <div class="form-group product-only">
                    <label style="color:var(--success-color);">จำนวน *</label>
                    <input type="number" step="0.01" id="qty-${rowID}" name="docs[${docIdx}][items][${itemIdx}][quantity]" class="form-control input-highlight qty-input" placeholder="0" value="1" required oninput="calcTotal(this)">
                </div>
                
                <div class="form-group">
                    <label id="label-cost-${rowID}" style="color:#2563eb;">ทุน/หน่วย</label>
                    <input type="number" step="0.01" id="cost-${rowID}" name="docs[${docIdx}][items][${itemIdx}][cost_price]" class="form-control cost-input" placeholder="0.00" oninput="calcTotal(this)">
                </div>
                
                <div class="form-group product-only">
                    <label style="color:#1e40af;">รวมทุน</label>
                    <input type="text" class="form-control input-readonly total-cost" readonly value="0.00">
                </div>
                
                <div class="form-group product-only">
                    <label style="color:#d97706;">กลาง/หน่วย</label>
                    <input type="number" step="0.01" name="docs[${docIdx}][items][${itemIdx}][standard_price]" class="form-control std-input" placeholder="0.00" oninput="calcTotal(this)">
                </div>
                
                <div class="form-group product-only">
                    <label style="color:#b45309;">รวมกลาง</label>
                    <input type="text" class="form-control input-readonly total-std" readonly value="0.00">
                </div>
            </div>
        `;

            wrapper.appendChild(row);
            const newInput = document.getElementById(inputID);
            setupAutocomplete(newInput);

            // เรียกใช้ครั้งแรกเพื่อตั้งค่า Default (เป็นสินค้า)
            toggleItemType(rowID);
        }

        // --- ✅ New Function: Toggle Item Type ---
        function toggleItemType(rowID) {
            const row = document.getElementById(rowID);
            if (!row) return;

            // 1. ดึงข้อมูล Element ที่จำเป็น
            const type = row.querySelector(`input[name*="[item_type]"]:checked`).value;
            const inp = row.querySelector('.autocomplete-input'); // ช่องกรอกชื่อรายการ
            const productFields = row.querySelectorAll('.product-only');

            const itemNameLabel = row.querySelector(`#label-item-name-${rowID}`);
            const costLabel = row.querySelector(`#label-cost-${rowID}`);

            const qtyInput = row.querySelector(`#qty-${rowID}`);
            const unitInput = row.querySelector(`#unit-${rowID}`);

            if (type === 'expense') {
                // 🔴 โหมดค่าใช้จ่าย / บริการ

                // --- จุดสำคัญ: สลับแหล่งข้อมูล Autocomplete ---
                inp.dataset.source = 'expense';

                // 1. ซ่อนช่องที่ไม่จำเป็น (หน่วยนับ, จำนวน, รวมทุน, ราคากลาง, รวมกลาง)
                productFields.forEach(el => el.classList.add('hidden-field'));

                // 2. เปลี่ยนชื่อ Label และ Placeholder ให้ตรงกับประเภท
                if (itemNameLabel) itemNameLabel.innerText = 'รายการค่าใช้จ่าย';
                inp.placeholder = 'ค้นหา/ระบุรายการค่าใช้จ่าย (เช่น ค่าแรง, ค่าขนส่ง)...';
                if (costLabel) costLabel.innerText = 'จำนวนเงิน (บาท)';

                // 3. Fix ค่า Default (เพื่อให้ระบบคำนวณเงินยอดรวมได้ถูกต้อง)
                qtyInput.value = 1;     // จำนวนเป็น 1 เสมอสำหรับค่าใช้จ่าย
                unitInput.value = '';   // เคลียร์หน่วยนับ
                unitInput.removeAttribute('required');

                // ปรับแต่งสีเล็กน้อยเพื่อให้รู้ว่าอยู่ในโหมดค่าใช้จ่าย
                inp.style.borderLeft = "5px solid #f59e0b";

            } else {
                // 🟢 โหมดสินค้า / วัสดุ

                // --- จุดสำคัญ: สลับแหล่งข้อมูล Autocomplete ---
                inp.dataset.source = 'product';

                // 1. แสดงช่องที่ซ่อนไว้กลับมาทั้งหมด
                productFields.forEach(el => el.classList.remove('hidden-field'));

                // 2. คืนค่า Label และ Placeholder เป็นแบบปกติ
                if (itemNameLabel) itemNameLabel.innerText = 'ชื่อรายการสินค้า';
                inp.placeholder = 'ระบุชื่อสินค้า...';
                if (costLabel) costLabel.innerText = 'ทุน/หน่วย';

                // 3. คืนค่า Required และลบการตั้งค่า Fix ออก
                unitInput.setAttribute('required', 'required');
                inp.style.borderLeft = "";
            }

            // 4. สั่งคำนวณยอดรวม (Total) ใหม่ทันทีหลังจากสลับโหมด
            calcTotal(qtyInput);
        }

        // --- Calculation (Updated) ---
        function calcTotal(element) {
            const box = element.closest('.product-box');

            // ใช้ parseFloat || 0 เพื่อกัน NaN กรณีซ่อน field
            const qty = parseFloat(box.querySelector('.qty-input').value) || 0;
            const cost = parseFloat(box.querySelector('.cost-input').value) || 0;
            const std = parseFloat(box.querySelector('.std-input').value) || 0;

            // ถ้าเป็น expense ยอดรวมทุน ก็คือ cost * 1 (ซึ่งก็คือ cost นั่นแหละ)
            // แต่ logic เดิม cost * qty ก็ถูกต้องแล้วเพราะ qty ถูก fix เป็น 1

            box.querySelector('.total-cost').value = (qty * cost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            box.querySelector('.total-std').value = (qty * std).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // --- ✅ Autocomplete (Logic ปรับปรุงใหม่) ---
        function setupAutocomplete(inp) {
            let currentFocus;

            // 1. ฟังก์ชันแสดงรายการ (เวอร์ชันอัปเกรด: แยกข้อมูลตามประเภท)
            // 1. ฟังก์ชันแสดงรายการ (เวอร์ชันอัปเกรด: สลับชุดข้อมูล สินค้า/ค่าใช้จ่าย อัตโนมัติ)
            function showList() {
                let listDiv, itemDiv, val = inp.value;

                // ปิด List อื่นก่อนเสมอ เพื่อไม่ให้ซ้อนกัน
                closeAllLists();

                currentFocus = -1;

                // 🟢 [จุดสำคัญ] เลือกชุดข้อมูลตาม Source ที่ระบุใน Dataset ของ Input นั้นๆ
                // expenseList และ productList ต้องถูกส่งมาจาก PHP (json_encode) ไว้ที่ด้านบนของ Script
                const currentDataList = (inp.dataset.source === 'expense') ? expenseList : productList;

                // สลับไอคอน: ถ้าเป็นค่าใช้จ่ายใช้รูป "ใบเสร็จสีเหลือง" ถ้าเป็นสินค้าใช้รูป "นาฬิกาสีเทา"
                const iconClass = (inp.dataset.source === 'expense') ? 'fas fa-file-invoice-dollar text-warning' : 'fas fa-history text-muted';

                // สร้าง Container สำหรับรายการ Dropdown
                listDiv = document.createElement("DIV");
                listDiv.setAttribute("id", inp.id + "autocomplete-list");
                listDiv.setAttribute("class", "autocomplete-list");
                inp.parentNode.appendChild(listDiv);

                let hasItems = false;

                // วนลูปตามชุดข้อมูลที่สลับมาให้แล้ว
                for (let i = 0; i < currentDataList.length; i++) {
                    // ถ้าช่องว่าง = แสดงทั้งหมด (ประวัติ) || ถ้าเริ่มพิมพ์ = กรองเฉพาะคำที่ตรงกัน
                    if (val === "" || currentDataList[i].toUpperCase().indexOf(val.toUpperCase()) > -1) {

                        itemDiv = document.createElement("DIV");

                        if (val === "") {
                            // กรณีช่องว่าง: แสดงชื่อปกติพร้อมไอคอนประเภท
                            itemDiv.innerHTML = `<i class="${iconClass}"></i> <span>${currentDataList[i]}</span>`;
                        } else {
                            // กรณีมีการพิมพ์: หาตำแหน่งคำเพื่อทำตัวหนา (Bold) ตรงที่พิมพ์
                            const matchIndex = currentDataList[i].toUpperCase().indexOf(val.toUpperCase());
                            const beforeMatch = currentDataList[i].substr(0, matchIndex);
                            const matchText = currentDataList[i].substr(matchIndex, val.length);
                            const afterMatch = currentDataList[i].substr(matchIndex + val.length);

                            itemDiv.innerHTML = `<i class="${iconClass}"></i> <span>${beforeMatch}<strong>${matchText}</strong>${afterMatch}</span>`;
                        }

                        // เก็บค่าจริงไว้ใน Hidden input เพื่อกัน Error กรณีชื่อมี Single Quote (')
                        itemDiv.innerHTML += "<input type='hidden' value='" + currentDataList[i].replace(/'/g, "&#39;") + "'>";

                        // เมื่อคลิกเลือกรายการจาก Dropdown
                        itemDiv.addEventListener("click", function (e) {
                            inp.value = this.getElementsByTagName("input")[0].value;
                            closeAllLists(); // เลือกเสร็จ ปิด List ทันที

                            // ⚡ Smart Focus: เมื่อเลือกชื่อเสร็จ ให้กระโดดไปช่อง "ทุน/หน่วย" หรือ "จำนวนเงิน" ทันที
                            // โดยข้ามช่อง "หน่วยนับ" และ "จำนวน" ถ้าช่องนั้นถูกซ่อนอยู่ (โหมดค่าใช้จ่าย)
                            const parentBox = inp.closest('.product-box');
                            const costInput = parentBox.querySelector('.cost-input');
                            const qtyInput = parentBox.querySelector('.qty-input');

                            if (inp.dataset.source === 'expense') {
                                if (costInput) costInput.focus();
                            } else {
                                if (qtyInput) qtyInput.focus();
                            }
                        });

                        listDiv.appendChild(itemDiv);
                        hasItems = true;
                    }
                }

                // แสดง List เฉพาะเมื่อมีข้อมูลที่ Matching
                if (hasItems) {
                    listDiv.style.display = "block";
                } else {
                    // ถ้าไม่มีข้อมูลเลย ให้ลบ List ทิ้งเพื่อไม่ให้เหลือเงาขาวๆ
                    if (listDiv.parentNode) {
                        listDiv.parentNode.removeChild(listDiv);
                    }
                }
            }
            // 2. Event Listeners
            inp.addEventListener("input", showList);

            // **สำคัญ** ใช้ click อย่างเดียว (ตัด focus ออก เพื่อกันเด้งซ้ำซ้อน)
            inp.addEventListener("click", function (e) {
                // หยุดการส่งต่อ Event ไม่ให้ไปถึง document (กันปิดตัวเอง)
                e.stopPropagation();
                showList();
            });

            inp.addEventListener("keydown", function (e) {
                let x = document.getElementById(this.id + "autocomplete-list");
                if (x) x = x.getElementsByTagName("div");
                if (e.keyCode == 40) { // Down
                    currentFocus++;
                    addActive(x);
                } else if (e.keyCode == 38) { // Up
                    currentFocus--;
                    addActive(x);
                } else if (e.keyCode == 13) { // Enter
                    e.preventDefault();
                    if (currentFocus > -1) {
                        if (x) x[currentFocus].click();
                    }
                }
            });

            function addActive(x) {
                if (!x) return false;
                removeActive(x);
                if (currentFocus >= x.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = (x.length - 1);
                x[currentFocus].classList.add("autocomplete-active");
                x[currentFocus].style.backgroundColor = "#f1f5f9";
            }

            function removeActive(x) {
                for (let i = 0; i < x.length; i++) {
                    x[i].classList.remove("autocomplete-active");
                    x[i].style.backgroundColor = "";
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            toggleMode();
        });
    </script>

</body>

</html>