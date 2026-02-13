<?php
session_start();
require_once 'auth.php'; 
require_once 'db_connect.php'; 

date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$mode = 'create';
$edit_id = 0;
$data = [];
$parsed_orders = [];
$parsed_docs = [];
$parsed_expenses = [];

// --- 1. Helper Functions ---
function uploadFiles($fileArray, $prefix = "doc") {
    $uploadedFiles = [];
    if (isset($fileArray['name']) && is_array($fileArray['name'])) {
        $fileCount = count($fileArray['name']);
        $target_dir = __DIR__ . "/uploads/marketing/";
        if (!file_exists($target_dir)) @mkdir($target_dir, 0777, true);
        for ($i = 0; $i < $fileCount; $i++) {
            if (!empty($fileArray['name'][$i]) && $fileArray['error'][$i] == 0) {
                $ext = pathinfo($fileArray['name'][$i], PATHINFO_EXTENSION);
                $newFileName = $prefix . "_" . time() . "_" . $i . "_" . rand(100, 999) . "." . $ext;
                if (move_uploaded_file($fileArray['tmp_name'][$i], $target_dir . $newFileName)) {
                    $uploadedFiles[] = $newFileName;
                }
            }
        }
    }
    return $uploadedFiles;
}

function cleanFileName($filename) {
    if (!$filename) return '';
    return strpos($filename, ':') !== false ? trim(substr($filename, strpos($filename, ':') + 1)) : trim($filename);
}

// ✅ Parse Order Details
function parseItemDetails($text) {
    $orders = [];
    $blocks = explode("--------------------", $text);
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;
        
        $lines = explode("\n", $block);
        $header = array_shift($lines); 
        
        $platform = "";
        $orderNo = "";
        if (preg_match('/🌐.*?: (.*?)(?: \(Order: (.*?)\))?$/', $header, $m)) {
            $platform = trim($m[1]);
            $orderNo = isset($m[2]) ? trim($m[2]) : '';
        } else {
            $platform = str_replace(['🌐','ช่องทางที่'], '', $header); 
        }

        $products = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '- ') === 0) {
                if (preg_match('/- (.*?) \(x(.*?) @ (.*?)\)/', $line, $pm)) {
                    $name = trim($pm[1]);
                    $qty = floatval($pm[2]);
                    $price = floatval(str_replace(',', '', $pm[3]));
                    
                    $discount = 0;
                    if (preg_match('/\[ลด -(.*?)\]/', $line, $dm)) $discount = floatval(str_replace(',', '', $dm[1]));
                    
                    $shipping = 0;
                    if (preg_match('/\[ส่ง \+(.*?)\]/', $line, $sm)) $shipping = floatval(str_replace(',', '', $sm[1]));
                    
                    $products[] = [
                        'name' => $name, 'qty' => $qty, 'price' => $price, 
                        'discount' => $discount, 'shipping' => $shipping
                    ];
                }
            }
        }
        $orders[] = ['platform' => $platform, 'order_no' => $orderNo, 'products' => $products];
    }
    return $orders;
}

// ✅ แก้ไข: ฟังก์ชัน Parse Expenses ให้ฉลาดขึ้น (รองรับตัวคั่น | และ Regex จับ pattern)
function parseExpenses($text) {
    $exps = [];
    if (empty($text)) return $exps;

    // 1. ลองแยกด้วย | (Format ใหม่)
    if (strpos($text, '|') !== false) {
        $items = explode('|', $text);
        foreach ($items as $item) {
            if (preg_match('/(.*?)\s*\(([\d,.]+)\)/', trim($item), $m)) {
                $exps[] = [
                    'name' => trim($m[1]),
                    'amount' => floatval(str_replace(',', '', $m[2]))
                ];
            }
        }
    } 
    // 2. ถ้าไม่มี | ให้ใช้ Regex จับ Pattern "ชื่อ (จำนวนเงิน)" โดยตรง (รองรับ Format เก่า)
    else {
        // Pattern: ข้อความใดๆ ตามด้วย (ตัวเลขที่มี , หรือ . ได้)
        // ใช้ preg_match_all เพื่อจับทุกรายการโดยไม่สนตัวคั่น
        if (preg_match_all('/(.*?)\s*\(([\d,.]+)\)/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $cleanName = trim(str_replace(',', '', $m[1])); // กันเหนียวลบ comma ในชื่อ
                // ถ้าชื่อว่างและ amount เป็น 0 ข้าม
                if(empty($cleanName) && empty($m[2])) continue; 
                
                // ตรวจสอบว่าไม่ใช่ส่วนหนึ่งของข้อความอื่น (Basic check)
                $exps[] = [
                    'name' => trim($m[1]), // กลุ่ม 1 คือชื่อ
                    'amount' => floatval(str_replace(',', '', $m[2])) // กลุ่ม 2 คือตัวเลข
                ];
            }
        }
    }
    return $exps;
}

function parseDocRefs($text) {
    $docs = [];
    if (empty($text)) return $docs;
    $items = explode(',', $text);
    foreach ($items as $item) {
        $parts = explode(' ', trim($item), 2);
        $docs[] = ['prefix' => $parts[0] ?? 'AX', 'number' => $parts[1] ?? ''];
    }
    return $docs;
}

// --- 2. Check Edit Mode ---
if (isset($_GET['id'])) {
    $mode = 'edit';
    $edit_id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM report_online_marketing WHERE id = $edit_id");
    if ($res->num_rows > 0) {
        $data = $res->fetch_assoc();
        $parsed_orders = parseItemDetails($data['item_details']);
        $parsed_docs = parseDocRefs($data['doc_references']);
        $parsed_expenses = parseExpenses($data['expense_list']); // ✅ ใช้ฟังก์ชันใหม่
        
        $status_arr = explode(',', $data['tax_invoice_status']);
        
        // Parse Files
        $file_map = [];
        $raw_files = $data['platform_files'] ?? '';
        if ($raw_files) {
            $groups = explode('|', $raw_files);
            foreach ($groups as $g) {
                $p = explode(':', $g);
                if (count($p) >= 2) {
                    $key = trim($p[0]);
                    $files = explode(',', $p[1]);
                    $file_map[$key] = $files;
                }
            }
        }

        foreach ($parsed_orders as $idx => &$order) {
            $raw_stat = $status_arr[$idx] ?? '';
            if (strpos($raw_stat, ':') !== false) {
                $order['status'] = trim(explode(':', $raw_stat)[1]);
            } else {
                $order['status'] = trim($raw_stat);
            }

            $order['files'] = [];
            $lookup_key = !empty($order['order_no']) ? $order['order_no'] : $order['platform'];
            if (isset($file_map[$lookup_key])) {
                $order['files'] = $file_map[$lookup_key];
            }
        }
    }
}

// --- 3. Platform List ---
$platforms_list = [];
$q_plat = $conn->query("SELECT platform_name FROM marketing_platforms ORDER BY platform_name ASC");
if ($q_plat) {
    while ($row = $q_plat->fetch_assoc()) {
        $platforms_list[] = $row['platform_name'];
    }
}

// --- 4. Form Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $is_update = isset($_POST['action']) && $_POST['action'] == 'update';
    $target_id = $is_update ? intval($_POST['edit_id']) : 0;

    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $reporter_name = $_SESSION['fullname'];
    $work_type = 'Online Marketing';
    $area = ''; $province = ''; $gps = ''; $gps_address = '';
    $problem = $_POST['problem'] ?? '';
    $memo = trim($_POST['memo'] ?? '');

    // Doc Refs
    $doc_refs_arr = [];
    foreach ($_POST['doc_refs'] ?? [] as $doc) {
        if (!empty($doc['number'])) $doc_refs_arr[] = $doc['prefix'] . " " . trim($doc['number']);
    }
    $doc_references_str = implode(", ", $doc_refs_arr);

    // Orders
    $orders_data = $_POST['orders'] ?? [];
    $platform_names_arr = [];
    $customer_orders_arr = [];
    $all_item_details_arr = [];
    $tax_statuses_arr = [];
    $platform_files_arr = [];

    $total_item_count = 0;
    $grand_total_sales = 0;    
    $grand_total_discount = 0; 
    $grand_total_shipping = 0; 

    foreach ($orders_data as $index => $order) {
        $p_name = trim($order['platform']);
        $o_num = trim($order['order_no']);

        if (!empty($p_name)) {
            $platform_names_arr[] = $p_name;
            if (!empty($o_num)) $customer_orders_arr[] = "$p_name: $o_num";

            $shop_subtotal = 0;
            $product_list_str = "";
            if (isset($order['products'])) {
                foreach ($order['products'] as $prod) {
                    $prod_name = trim($prod['name']);
                    $prod_qty = floatval($prod['qty'] ?? 0);
                    $prod_price = floatval($prod['price'] ?? 0);
                    $prod_disc = floatval($prod['discount'] ?? 0);
                    $prod_ship = floatval($prod['shipping'] ?? 0);

                    if (!empty($prod_name)) {
                        $line_net = ($prod_qty * $prod_price) - $prod_disc + $prod_ship; 
                        $shop_subtotal += $line_net;
                        $grand_total_discount += $prod_disc;
                        $grand_total_shipping += $prod_ship;
                        $total_item_count++;
                        
                        $product_list_str .= "- $prod_name (x$prod_qty @ " . number_format($prod_price) . ")";
                        if ($prod_disc > 0) $product_list_str .= " [ลด -" . number_format($prod_disc) . "]";
                        if ($prod_ship > 0) $product_list_str .= " [ส่ง +" . number_format($prod_ship) . "]";
                        $product_list_str .= " = " . number_format($line_net) . " บ.\n";
                    }
                }
            }
            $grand_total_sales += $shop_subtotal;

            $detail_str = "🌐 ช่องทางที่ " . ($index + 1) . ": " . $p_name;
            if (!empty($o_num)) $detail_str .= " (Order: $o_num)";
            $detail_str .= "\n" . $product_list_str;
            $detail_str .= "💰 ยอดสุทธิร้านนี้: " . number_format($shop_subtotal, 2) . " บาท\n";
            $all_item_details_arr[] = $detail_str;

            // Files Management
            $current_files = [];
            if (isset($order['existing_files']) && is_array($order['existing_files'])) {
                foreach ($order['existing_files'] as $f) $current_files[] = $f;
            }
            $file_input_name = "order_files_" . $index;
            if (isset($_FILES[$file_input_name])) {
                $file_prefix = !empty($o_num) ? "slip_" . preg_replace('/[^a-zA-Z0-9]/', '', $o_num) : "slip_idx_" . $index;
                $new_files = uploadFiles($_FILES[$file_input_name], $file_prefix);
                $current_files = array_merge($current_files, $new_files);
            }
            if (!empty($current_files)) {
                $key_name = !empty($o_num) ? $o_num : $p_name;
                $platform_files_arr[] = $key_name . ":" . implode(",", $current_files);
            }
            
            $t_stat = $order['tax_status'] ?? '';
            if (!empty($t_stat)) $tax_statuses_arr[] = (count($orders_data) > 1 ? "$p_name: " : "") . $t_stat;
        }
    }

    $supplier_name = implode(", ", $platform_names_arr);
    $order_number_str = implode(", ", $customer_orders_arr);
    $item_details = implode("\n--------------------\n", $all_item_details_arr);
    $tax_invoice_status = !empty($tax_statuses_arr) ? implode(", ", $tax_statuses_arr) : 'ไม่ระบุ';
    $additional_notes = !empty($memo) ? "[Memo: $memo]" : "";
    $platform_files_str = implode("|", $platform_files_arr);

    // Expenses Processing
    $exp_names = $_POST['exp_name'] ?? [];
    $exp_amounts = $_POST['exp_amount'] ?? [];
    $expense_files_arr = [];
    
    if (isset($_POST['existing_exp_files']) && is_array($_POST['existing_exp_files'])) {
        foreach ($_POST['existing_exp_files'] as $f) $expense_files_arr[] = $f;
    }
    if (isset($_FILES['exp_file'])) {
        $new_exp_files = uploadFiles($_FILES['exp_file'], "exp");
        $expense_files_arr = array_merge($expense_files_arr, $new_exp_files);
    }
    $expense_files_str = implode(",", $expense_files_arr);

    $expense_summary_list = [];
    $total_expense = 0.00;
    if (is_array($exp_names)) {
        for ($i = 0; $i < count($exp_names); $i++) {
            $name = trim($exp_names[$i]);
            $amount = isset($exp_amounts[$i]) ? floatval($exp_amounts[$i]) : 0;
            if (!empty($name) || $amount > 0) {
                $total_expense += $amount;
                // ✅ แก้ไข: ใช้ตัวคั่น | (Pipe) แทน , (Comma) เพื่อไม่ให้ชนกับลูกน้ำในตัวเลข
                $expense_summary_list[] = "$name (" . number_format($amount, 2) . ")";
            }
        }
    }
    // ✅ Join ด้วย Pipe
    $expense_list_str = implode(" | ", $expense_summary_list);
    $current_timestamp = date('Y-m-d H:i:s'); 

    // SQL Operation
    if ($is_update) {
        $sql = "UPDATE report_online_marketing SET 
                report_date=?, work_type=?, area=?, province=?, gps=?, gps_address=?, 
                platform_name=?, order_number=?, doc_references=?, item_count=?, item_details=?, problem=?,
                tax_invoice_status=?, additional_notes=?, expense_list=?, expense_files=?, total_expense=?, 
                total_sales=?, total_discount=?, total_shipping_cost=?, platform_files=?
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssissssssddddsi", 
            $report_date, $work_type, $area, $province, $gps, $gps_address, 
            $supplier_name, $order_number_str, $doc_references_str, $total_item_count, $item_details, $problem,
            $tax_invoice_status, $additional_notes, $expense_list_str, $expense_files_str, $total_expense,
            $grand_total_sales, $grand_total_discount, $grand_total_shipping, $platform_files_str, $target_id
        );
    } else {
        $sql = "INSERT INTO report_online_marketing (
            report_date, reporter_name, work_type, area, province, gps, gps_address, 
            platform_name, order_number, doc_references, item_count, item_details, problem,
            tax_invoice_status, additional_notes, expense_list, expense_files, total_expense, 
            total_sales, total_discount, total_shipping_cost, platform_files, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssissssssddddss", 
            $report_date, $reporter_name, $work_type, $area, $province, $gps, $gps_address, 
            $supplier_name, $order_number_str, $doc_references_str, $total_item_count, $item_details, $problem,
            $tax_invoice_status, $additional_notes, $expense_list_str, $expense_files_str, $total_expense, 
            $grand_total_sales, $grand_total_discount, $grand_total_shipping, $platform_files_str, $current_timestamp
        );
    }

    if ($stmt->execute()) {
        header("Location: StaffHistory.php?tab=marketing");
        exit();
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title><?php echo ($mode == 'edit') ? 'แก้ไขรายงาน' : 'รายงานการตลาดออนไลน์'; ?> - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Styles เดิม */
        :root { --primary-color: #6366f1; --primary-light: #eef2ff; --text-main: #1e293b; --text-muted: #64748b; --bg-body: #f8fafc; --bg-card: #ffffff; --bg-input: #f9fafb; --border-color: #e2e8f0; --danger: #ef4444; }
        body { font-family: 'Prompt', sans-serif; background-color: var(--bg-body); color: var(--text-main); margin: 0; }
        .main-container { width: 100%; padding: 40px; max-width: 1200px; margin: 0 auto; box-sizing: border-box; }
        .form-container { background: var(--bg-card); border-radius: 16px; padding: 40px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .section-title { font-size: 1.3rem; font-weight: 700; color: var(--primary-color); margin-bottom: 25px; display: flex; align-items: center; gap: 12px; padding-bottom: 15px; border-bottom: 2px dashed var(--border-color); }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 25px; width: 100%; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid var(--border-color); background: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; font-size: 1rem; box-sizing: border-box; }
        .purchase-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 30px; position: relative; }
        .pc-header { background: linear-gradient(to right, var(--bg-body), var(--bg-card)); padding: 15px 25px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; }
        .pc-body { padding: 30px; }
        .product-item-row { display: grid; grid-template-columns: 2fr 0.8fr 1fr 1fr 1fr 1.2fr auto; gap: 10px; margin-bottom: 15px; align-items: center; }
        .btn-add-shop { background: var(--text-main); color: var(--bg-card); width: 100%; padding: 16px; border-radius: 16px; border: none; font-weight: 700; cursor: pointer; margin: 15px 0 40px 0; }
        .btn-submit { width: 100%; padding: 20px; background: linear-gradient(135deg, var(--primary-color), #3730a3); color: #fff; border: none; border-radius: 20px; font-size: 1.2rem; font-weight: 800; cursor: pointer; margin-top: 40px; }
        .dynamic-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 30px; }
        .dynamic-row { display: flex; gap: 10px; margin-bottom: 15px; align-items: center; }
        .btn-remove-mini { background: var(--bg-card); color: var(--text-muted); width: 45px; height: 45px; border-radius: 10px; border: 1px solid var(--border-color); cursor: pointer; }
        
        .status-radio-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .status-option input[type="radio"] { display: none; }
        .status-chip { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 20px; border: 1px solid var(--border-color); background-color: var(--bg-input); color: var(--text-muted); font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; gap: 6px; }
        .status-option input[type="radio"]:checked + .status-chip.chip-processing { background-color: #fffbeb; border-color: #f59e0b; color: #b45309; }
        .status-option input[type="radio"]:checked + .status-chip.chip-sent { background-color: #ecfdf5; border-color: #10b981; color: #047857; }
        .status-option input[type="radio"]:checked + .status-chip.chip-returned { background-color: #fef2f2; border-color: #ef4444; color: #b91c1c; }

        /* File Tag Style */
        .file-tag {
            display: inline-flex; align-items: center; gap: 6px; 
            padding: 4px 10px; background: var(--primary-light); 
            color: var(--primary-color); border-radius: 8px; font-size: 0.85rem; 
            margin-right: 5px; margin-bottom: 5px; border: 1px solid transparent;
        }
        .file-tag i { cursor: pointer; }
        .file-tag:hover { border-color: var(--primary-color); }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-container">
        <a href="Dashboard_Marketing.php" style="display:inline-flex; align-items:center; gap:5px; text-decoration:none; color:#64748b; font-weight:600; margin-bottom:20px;">
            <i class="fas fa-arrow-left"></i> ย้อนกลับไป Dashboard
        </a>

        <div class="form-container">
            <h2 style="margin-top:0; color:var(--primary-color);">
                <?php echo ($mode == 'edit') ? '<i class="fas fa-edit"></i> แก้ไขรายงาน' : '<i class="fas fa-plus-circle"></i> สร้างรายงานใหม่'; ?>
            </h2>
            
            <form method="post" action="" enctype="multipart/form-data" id="reportForm" onsubmit="confirmSubmit(event)">
                <?php if($mode == 'edit'): ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-info-circle"></i> ข้อมูลทั่วไป</div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>วันที่รายงาน <span style="color:red">*</span></label>
                            <input type="hidden" name="report_date" id="reportDateHidden" value="<?php echo $data['report_date'] ?? date('Y-m-d'); ?>">
                            <input type="text" id="reportDateDisplay" class="form-input" placeholder="เลือกวันที่" readonly required value="<?php echo $data['report_date'] ?? date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>ผู้รายงาน</label>
                            <input type="text" value="<?php echo $_SESSION['fullname']; ?>" class="form-input" readonly style="background:var(--bg-input);">
                        </div>
                    </div>
                </div>

                <div class="form-section" style="margin-top:40px;">
                    <div class="section-title"><i class="fas fa-cubes"></i> รายละเอียดออเดอร์ / แพลตฟอร์ม</div>
                    <div id="orders_container"></div>
                    <button type="button" class="btn-add-shop" onclick="addOrderCard()"><i class="fas fa-plus-circle"></i> เพิ่มช่องทาง / ออเดอร์อื่น</button>

                    <div style="background: linear-gradient(135deg, #4f46e5, #4338ca); color: white; padding: 25px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; font-size: 1.3rem; font-weight: 800;">
                        <span><i class="fas fa-chart-line"></i> ยอดขายสุทธิรวม</span>
                        <span id="grandTotalSalesDisplay">0.00 บาท</span>
                    </div>

                    <div class="form-group" style="margin-top:30px;">
                        <label style="color:var(--danger); font-weight:700;">ปัญหาที่พบ / ข้อเสนอแนะ</label>
                        <textarea name="problem" class="form-textarea" rows="3"><?php echo htmlspecialchars($data['problem']??''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label style="color:var(--primary-color); font-weight:700;">บันทึกเพิ่มเติม</label>
                        <textarea name="memo" class="form-textarea" rows="3"><?php 
                            if(isset($data['additional_notes'])) echo str_replace(['[Memo:', ']'], '', strstr($data['additional_notes'], '[Memo:') ?: '');
                        ?></textarea>
                    </div>
                </div>

                <div class="form-section" style="margin-top:40px;">
                    <div class="section-title"><i class="fas fa-file-invoice"></i> เลขที่เอกสาร (AX / PO / SO)</div>
                    <div class="dynamic-box">
                        <div id="doc_ref_container"></div>
                        <button type="button" class="btn-add-shop" style="margin:10px 0; background:var(--primary-light); color:var(--primary-color);" onclick="addDocRefRow()">
                            <i class="fas fa-plus"></i> เพิ่มเลขที่เอกสาร
                        </button>
                    </div>
                </div>

                <div class="form-section" style="margin-top:40px;">
                    <div class="section-title"><i class="fas fa-wallet"></i> ค่าใช้จ่าย (Ads / อื่นๆ)</div>
                    <div class="dynamic-box">
                        <div id="expense_container"></div>
                        
                        <?php if($mode == 'edit' && !empty($data['expense_files'])): ?>
                        <div style="margin-top:10px; margin-bottom:15px;">
                            <label style="font-size:0.9rem; font-weight:bold;">ไฟล์เดิมที่มีอยู่:</label>
                            <div style="margin-top:5px;">
                                <?php 
                                $exFiles = explode(',', $data['expense_files']);
                                foreach($exFiles as $ef): if(trim($ef) == '') continue;
                                    $efName = (strpos($ef, ':')!==false) ? trim(substr($ef, strpos($ef, ':')+1)) : trim($ef);
                                ?>
                                <span class="file-tag" id="exp_file_<?php echo md5($ef); ?>">
                                    <i class="fas fa-file-alt"></i> <?php echo $efName; ?>
                                    <input type="hidden" name="existing_exp_files[]" value="<?php echo $ef; ?>">
                                    <i class="fas fa-times" style="margin-left:5px; color:#ef4444;" onclick="document.getElementById('exp_file_<?php echo md5($ef); ?>').remove()"></i>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <button type="button" class="btn-add-shop" style="margin:10px 0; background:var(--primary-light); color:var(--primary-color);" onclick="addExpenseRow()">
                            <i class="fas fa-plus-circle"></i> เพิ่มรายการค่าใช้จ่าย
                        </button>
                        <div style="text-align:right; margin-top:20px; font-weight:800; font-size:1.1rem;">
                            รวมค่าใช้จ่าย: <span id="totalDisplay">0.00</span> บาท
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> <?php echo ($mode == 'edit') ? 'บันทึกการแก้ไข' : 'บันทึกรายงาน'; ?>
                </button>
            </form>
        </div>
        <div style="height: 80px;"></div>
    </div>

    <script>
        let orderCount = 0;
        let docRefCount = 0;
        
        // ✅ 1. ส่งข้อมูล Platform จาก PHP มาเป็น JavaScript Array
        const platformOptionsData = <?php echo json_encode($platforms_list); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            flatpickr("#reportDateDisplay", { dateFormat: "d/m/Y", defaultDate: "<?php echo $data['report_date'] ?? 'today'; ?>", locale: "th", disableMobile: true, onChange: function (dates) { if (dates.length) document.getElementById("reportDateHidden").value = formatDate(dates[0]); } });
            
            // --- Auto-Fill Data (Magic Part) ---
            <?php if ($mode == 'edit'): ?>
                // 1. Doc Refs
                <?php if(!empty($parsed_docs)): foreach($parsed_docs as $d): ?>
                    addDocRefRow('<?php echo $d['prefix']; ?>', '<?php echo $d['number']; ?>');
                <?php endforeach; else: ?>
                    addDocRefRow(); 
                <?php endif; ?>

                // 2. Expenses (ใช้ Loop จากตัวแปรที่ Parse มาใหม่)
                <?php if(!empty($parsed_expenses)): foreach($parsed_expenses as $e): ?>
                    addExpenseRow('<?php echo addslashes($e['name']); ?>', '<?php echo $e['amount']; ?>');
                <?php endforeach; else: ?>
                    addExpenseRow();
                <?php endif; ?>

                // 3. Orders (Complex)
                <?php if(!empty($parsed_orders)): foreach($parsed_orders as $o): ?>
                    // Create Object for Files
                    var existingFiles = <?php echo json_encode($o['files'] ?? []); ?>;
                    
                    var idx = addOrderCard('<?php echo addslashes($o['platform']); ?>', '<?php echo addslashes($o['order_no']); ?>', '<?php echo $o['status']??''; ?>', existingFiles);
                    
                    // Add products for this order
                    <?php foreach($o['products'] as $p): ?>
                        addProductToOrder(idx, '<?php echo addslashes($p['name']); ?>', <?php echo $p['qty']; ?>, <?php echo $p['price']; ?>, <?php echo $p['discount']; ?>, <?php echo $p['shipping']; ?>);
                    <?php endforeach; ?>
                <?php endforeach; else: ?>
                    addOrderCard();
                <?php endif; ?>

            <?php else: ?>
                // Create Mode
                addDocRefRow(); 
                addOrderCard(); 
                addExpenseRow();
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                Swal.fire({ icon: 'error', title: 'แจ้งเตือน', text: '<?php echo addslashes($message); ?>' });
            <?php endif; ?>
        });

        function formatDate(date) { return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0'); }

        // --- JS: Doc Ref ---
        function addDocRefRow(preVal='AX', numVal='') {
            const container = document.getElementById('doc_ref_container');
            const index = docRefCount++;
            const div = document.createElement('div');
            div.className = 'dynamic-row';
            div.innerHTML = `
            <div style="flex:0 0 100px;"><select name="doc_refs[${index}][prefix]" class="form-select">
                <option value="AX" ${preVal=='AX'?'selected':''}>AX</option>
                <option value="PO" ${preVal=='PO'?'selected':''}>PO</option>
                <option value="SO" ${preVal=='SO'?'selected':''}>SO</option>
            </select></div>
            <div style="flex:1;"><input type="text" name="doc_refs[${index}][number]" class="form-input" placeholder="เลขที่เอกสาร" value="${numVal}"></div>
            <button type="button" class="btn-remove-mini" onclick="this.parentElement.remove()"><i class="fas fa-trash-alt"></i></button>
        `;
            container.appendChild(div);
        }

        // --- JS: Expenses ---
        function addExpenseRow(nameVal='', amtVal='') {
            const container = document.getElementById('expense_container');
            const div = document.createElement('div');
            div.className = 'dynamic-row';
            div.innerHTML = `
                <input type="text" name="exp_name[]" class="form-input" placeholder="รายการ (เช่น ค่าโฆษณา)" style="flex:2;" value="${nameVal}">
                <input type="number" step="0.01" name="exp_amount[]" class="form-input calc-expense" placeholder="บาท" style="flex:1;" oninput="calcExpenseTotal()" value="${amtVal}">
                <input type="file" name="exp_file[]" class="form-input" style="flex:1;">
                <button type="button" class="btn-remove-mini" onclick="this.parentElement.remove(); calcExpenseTotal();"><i class="fas fa-trash-alt"></i></button>
            `;
            container.appendChild(div);
            calcExpenseTotal();
        }

        function calcExpenseTotal() {
            let total = 0;
            document.querySelectorAll('.calc-expense').forEach(inp => total += parseFloat(inp.value) || 0);
            document.getElementById('totalDisplay').innerText = total.toLocaleString('en-US', { minimumFractionDigits: 2 });
        }

        // --- JS: Orders ---
        function addOrderCard(platVal='', orderVal='', statusVal='', existingFiles=[]) {
            const container = document.getElementById('orders_container');
            const index = orderCount++;
            const div = document.createElement('div');
            div.className = 'purchase-card';
            div.id = `order_card_${index}`;
            
            const chkProc = statusVal.includes('กำลังดำเนินการ') ? 'checked' : '';
            const chkSent = statusVal.includes('ส่งแล้ว') ? 'checked' : '';
            const chkRet  = statusVal.includes('ตีกลับ') ? 'checked' : '';

            // ✅ 2. สร้าง HTML ตัวเลือก (Options) สำหรับ Dropdown
            let optionsHtml = '<option value="">-- เลือกแพลตฟอร์ม --</option>';
            platformOptionsData.forEach(p => {
                const selected = (p === platVal) ? 'selected' : '';
                optionsHtml += `<option value="${p}" ${selected}>${p}</option>`;
            });

            // Generate Existing Files HTML
            let fileHtml = '';
            if (existingFiles.length > 0) {
                fileHtml = `<div style="margin-top:10px;"><small style="font-weight:bold;">ไฟล์เดิม:</small><br>`;
                existingFiles.forEach(f => {
                    let fName = f.includes(':') ? f.split(':')[1] : f;
                    let fileId = 'file_' + Math.random().toString(36).substr(2, 5);
                    fileHtml += `<span class="file-tag" id="${fileId}">
                                    <i class="fas fa-file-image"></i> ${fName}
                                    <input type="hidden" name="orders[${index}][existing_files][]" value="${f}">
                                    <i class="fas fa-times" style="margin-left:5px; color:#ef4444;" onclick="document.getElementById('${fileId}').remove()"></i>
                                 </span>`;
                });
                fileHtml += `</div>`;
            }

            div.innerHTML = `
            <div class="pc-header"><div style="font-weight:700;"><i class="fas fa-store"></i> ช่องทางที่ ${index + 1}</div>${index > 0 ? `<button type="button" class="btn-remove-mini" onclick="removeOrderCard(${index})"><i class="fas fa-trash"></i></button>` : ''}</div>
            <div class="pc-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>แพลตฟอร์ม</label>
                        <select name="orders[${index}][platform]" class="form-select platform-input">
                            ${optionsHtml}
                        </select>
                    </div>
                    <div class="form-group"><label>เลขที่ Order ลูกค้า</label><input type="text" name="orders[${index}][order_no]" class="form-input" placeholder="Order No." value="${orderVal}"></div>
                </div>
                <div class="product-list-container">
                    <div style="display:grid; grid-template-columns: 2fr 0.8fr 1fr 1fr 1fr 1.2fr auto; gap:10px; margin-bottom:10px; font-size:0.85rem; color:#64748b; font-weight:bold;">
                        <span>ชื่อสินค้า</span><span>จำนวน</span><span>ราคา</span><span>ส่วนลด</span><span>ค่าส่ง</span><span style="text-align:right;">รวม</span><span></span>
                    </div>
                    <div id="product_container_${index}"></div>
                </div>
                <button type="button" class="btn-add-shop" style="margin:0; background:transparent; border:2px dashed var(--primary-color); color:var(--primary-color);" onclick="addProductToOrder(${index})">+ เพิ่มสินค้า</button>
                <div style="margin-top:20px; text-align:right; font-weight:700;">ยอดรวมร้านนี้: <span id="shop_total_${index}">0.00</span></div>
                
                <div style="margin-top:20px;">
                    <label>แนบสลิป/หลักฐาน (เพิ่มใหม่)</label>
                    <input type="file" name="order_files_${index}[]" multiple class="form-input" style="padding: 10px;">
                    ${fileHtml}
                </div>

                <div style="margin-top:25px;">
                    <label style="font-weight:700; margin-bottom:10px; display:block;">สถานะ</label>
                    <div class="status-radio-group">
                        <label class="status-option"><input type="radio" name="orders[${index}][tax_status]" value="กำลังดำเนินการ" ${chkProc}><span class="status-chip chip-processing"><i class="fas fa-hourglass-half"></i> ดำเนินการ</span></label>
                        <label class="status-option"><input type="radio" name="orders[${index}][tax_status]" value="ส่งแล้ว" ${chkSent}><span class="status-chip chip-sent"><i class="fas fa-check-circle"></i> ส่งแล้ว</span></label>
                        <label class="status-option"><input type="radio" name="orders[${index}][tax_status]" value="ตีกลับ" ${chkRet}><span class="status-chip chip-returned"><i class="fas fa-undo"></i> ตีกลับ</span></label>
                    </div>
                </div>
            </div>`;
            container.appendChild(div);
            if(!platVal) addProductToOrder(index);
            return index; 
        }

        function removeOrderCard(index) { document.getElementById(`order_card_${index}`).remove(); calcGrandTotalSales(); }

        function addProductToOrder(orderIndex, n='', q='', p='', d='', s='') {
            const container = document.getElementById(`product_container_${orderIndex}`);
            const uniqueId = Date.now() + Math.random().toString(36).substr(2, 5);
            const div = document.createElement('div');
            div.className = 'product-item-row';
            div.innerHTML = `
            <input type="text" name="orders[${orderIndex}][products][${uniqueId}][name]" class="form-input product-input" placeholder="สินค้า" value="${n}">
            <input type="number" name="orders[${orderIndex}][products][${uniqueId}][qty]" class="form-input calc-qty" placeholder="จำนวน" oninput="calcOrderTotal(${orderIndex})" value="${q}">
            <input type="number" name="orders[${orderIndex}][products][${uniqueId}][price]" class="form-input calc-price" placeholder="ราคา" oninput="calcOrderTotal(${orderIndex})" value="${p}">
            <input type="number" name="orders[${orderIndex}][products][${uniqueId}][discount]" class="form-input calc-disc" placeholder="ลด" oninput="calcOrderTotal(${orderIndex})" style="color:red;" value="${d}">
            <input type="number" name="orders[${orderIndex}][products][${uniqueId}][shipping]" class="form-input calc-ship" placeholder="ส่ง" oninput="calcOrderTotal(${orderIndex})" style="color:green;" value="${s}">
            <span class="row-total" style="text-align:right; font-weight:bold;">0.00</span>
            <button type="button" class="btn-remove-mini" onclick="removeProductRow(this, ${orderIndex})"><i class="fas fa-times"></i></button>
            `;
            container.appendChild(div);
            calcOrderTotal(orderIndex);
        }

        function removeProductRow(btn, orderIndex) {
            const container = document.getElementById(`product_container_${orderIndex}`);
            if (container.children.length > 1) { btn.parentElement.remove(); calcOrderTotal(orderIndex); }
        }

        function calcOrderTotal(index) {
            const container = document.getElementById(`product_container_${index}`);
            let total = 0;
            container.querySelectorAll('.product-item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.calc-qty').value) || 0;
                const price = parseFloat(row.querySelector('.calc-price').value) || 0;
                const disc = parseFloat(row.querySelector('.calc-disc').value) || 0;
                const ship = parseFloat(row.querySelector('.calc-ship').value) || 0;

                const line = (qty * price) - disc + ship;
                row.querySelector('.row-total').innerText = line.toLocaleString(undefined, { minimumFractionDigits: 2 });
                total += line;
            });
            document.getElementById(`shop_total_${index}`).innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
            calcGrandTotalSales();
        }

        function calcGrandTotalSales() {
            let grand = 0;
            document.querySelectorAll('[id^="shop_total_"]').forEach(el => { grand += parseFloat(el.innerText.replace(/,/g, '')) || 0; });
            document.getElementById('grandTotalSalesDisplay').innerText = grand.toLocaleString('en-US', { minimumFractionDigits: 2 }) + " บาท";
        }

        function confirmSubmit(e) {
            e.preventDefault();
            const orderCards = document.querySelectorAll('.purchase-card');
            let warningMsg = '';

            orderCards.forEach((card, index) => {
                let cardIssues = []; // เก็บปัญหาของการ์ดใบนี้
                
                // 1. เช็คชื่อแพลตฟอร์ม
                const platInput = card.querySelector('.platform-input').value.trim();
                if (!platInput) {
                    warningMsg += `<br>• ช่องทางที่ ${index + 1}: ไม่ได้ระบุชื่อแพลตฟอร์ม`;
                    cardIssues.push('platform');
                }

                // 2. เช็คสถานะ
                const statusChecked = card.querySelector('input[type="radio"][name*="tax_status"]:checked');
                if (!statusChecked) {
                    warningMsg += `<br>• ช่องทางที่ ${index + 1}: ไม่ได้ระบุสถานะ`;
                    cardIssues.push('status');
                }

                // 3. เช็คชื่อสินค้า (ถ้ามีช่องไหนว่าง)
                let hasEmptyProduct = false;
                card.querySelectorAll('.product-input').forEach(prod => {
                    if (prod.value.trim() === '') hasEmptyProduct = true;
                });
                if (hasEmptyProduct) {
                    warningMsg += `<br>• ช่องทางที่ ${index + 1}: มีสินค้าไม่ได้ระบุชื่อ`;
                    cardIssues.push('product');
                }

                // เปลี่ยนสีกรอบถ้ามีปัญหา
                if (cardIssues.length > 0) {
                    card.style.border = "2px solid #f59e0b"; 
                } else {
                    card.style.border = "1px solid #e2e8f0"; 
                }
            });

            if (warningMsg !== '') {
                // มีข้อมูลไม่ครบ -> แจ้งเตือนทั้งหมด
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลบางส่วนไม่ครบถ้วน',
                    html: '<div style="text-align:left; font-size:0.9rem;">พบรายการดังนี้:' + warningMsg + '</div><br><b>ต้องการบันทึกโดยไม่ระบุหรือไม่?</b>',
                    showCancelButton: true,
                    confirmButtonText: 'บันทึกเลย',
                    cancelButtonText: 'กลับไปแก้ไข',
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#64748b'
                }).then((res) => {
                    if (res.isConfirmed) document.getElementById('reportForm').submit();
                });
            } else {
                // ข้อมูลครบ -> ยืนยันปกติ
                Swal.fire({ 
                    title: 'ยืนยันการบันทึก?', 
                    text: 'ตรวจสอบความถูกต้องก่อนบันทึก', 
                    icon: 'question', 
                    showCancelButton: true, 
                    confirmButtonColor: '#4f46e5', 
                    cancelButtonColor: '#d33', 
                    confirmButtonText: 'บันทึกทันที', 
                    cancelButtonText: 'แก้ไข' 
                }).then((res) => { 
                    if (res.isConfirmed) document.getElementById('reportForm').submit(); 
                });
            }
        }
    </script>
</body>
</html>