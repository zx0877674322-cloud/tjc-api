<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php'; // เชื่อมต่อฐานข้อมูล

// 1. ✅ ตั้งค่า Timezone ให้เป็นไทยทันทีที่เริ่มไฟล์
date_default_timezone_set('Asia/Bangkok');
$conn->query("SET time_zone = '+07:00'"); 
$conn->query("SET NAMES utf8mb4");

// ตรวจสอบ Login
if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$message = "";

// --- PHP Functions ---
function uploadExpenseFiles($fileInputName) {
    $uploadedFiles = [];
    if (isset($_FILES[$fileInputName])) {
        $fileCount = count($_FILES[$fileInputName]['name']);
        $target_dir = __DIR__ . "/uploads/";
        if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES[$fileInputName]['error'][$i] == 0) {
                $fileExtension = pathinfo($_FILES[$fileInputName]["name"][$i], PATHINFO_EXTENSION);
                $newFileName = "exp_" . time() . "_" . $i . "_" . rand(100, 999) . "." . $fileExtension;
                if(move_uploaded_file($_FILES[$fileInputName]["tmp_name"][$i], $target_dir . $newFileName)){
                    $uploadedFiles[$i] = $newFileName; 
                }
            }
        }
    }
    return $uploadedFiles;
}

// --- Form Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $report_date = $_POST['report_date'];
    $reporter_name = $_SESSION['fullname'];
    
    // 3. ✅ สร้างเวลาปัจจุบันเป็นเวลาไทย (ใช้ date ธรรมดาเพราะตั้ง timezone ด้านบนแล้ว)
    $created_at = date('Y-m-d H:i:s', strtotime('+12 hours', $timestamp));
    
    // Default Location
    $work_type = "company";
    $area = "สำนักงานใหญ่"; 
    $province = "กรุงเทพมหานคร"; 
    $gps = "Office"; 
    $gps_address = "ปฏิบัติงานภายในบริษัท"; 

    $problem = $_POST['problem'] ?? '';
    $general_note = $_POST['additional_notes'] ?? '';

    // จัดการข้อมูลร้านค้า
    $shops_data = $_POST['shops'] ?? [];
    
    $supplier_names_arr = [];
    $project_names_arr = [];
    $all_item_details_arr = [];
    $tax_statuses_arr = []; 
    $shop_tax_notes_arr = []; 
    $total_item_count = 0;

    foreach ($shops_data as $index => $shop) {
        $s_name = trim($shop['supplier']);
        $p_name = trim($shop['project']);
        $d_no   = trim($shop['doc_no'] ?? ''); 
        
        if(!empty($s_name)) {
            $supplier_names_arr[] = $s_name;
            if(!empty($p_name) && !in_array($p_name, $project_names_arr)) $project_names_arr[] = $p_name;

            // สร้างรายละเอียดสินค้า
            $shop_detail_str = "🏪 ร้านที่ " . ($index + 1) . ": " . $s_name;
            
            $extra_info = [];
            if(!empty($d_no))   $extra_info[] = "เลขที่: $d_no";
            if(!empty($p_name)) $extra_info[] = "หน้างาน: $p_name";
            
            if(!empty($extra_info)) {
                $shop_detail_str .= " (" . implode(" | ", $extra_info) . ")";
            }
            $shop_detail_str .= "\n";

            if (isset($shop['products']) && is_array($shop['products'])) {
                foreach ($shop['products'] as $prod) {
                    $prod_name = trim($prod['name']);
                    $prod_qty = trim($prod['qty']);
                    
                    if (!empty($prod_name)) {
                        $line = "- " . $prod_name;
                        if (!empty($prod_qty)) $line .= " (จำนวน: $prod_qty)";
                        $shop_detail_str .= $line . "\n";
                        $total_item_count++;
                    }
                }
            }
            $all_item_details_arr[] = $shop_detail_str;

            // เก็บสถานะภาษี
            $t_stat = $shop['tax_status'] ?? ''; 
            $t_note = trim($shop['tax_note'] ?? '');
            
            if(!empty($t_stat)) {
                if(count($shops_data) > 1) {
                    $tax_statuses_arr[] = "$s_name: $t_stat";
                } else {
                    $tax_statuses_arr[] = $t_stat;
                }
            }
            
            if(!empty($t_note)) {
                $shop_tax_notes_arr[] = "($s_name: $t_note)";
            }
        }
    }

    $supplier_name = implode(", ", $supplier_names_arr); 
    $project_name = implode(", ", $project_names_arr);   
    $item_details = implode("\n--------------------\n", $all_item_details_arr);
    $item_count = $total_item_count;
    $tax_invoice_status = !empty($tax_statuses_arr) ? implode(", ", $tax_statuses_arr) : 'ไม่ระบุ';
    
    // รวมหมายเหตุ
    $final_notes = $general_note;
    if (!empty($shop_tax_notes_arr)) {
        $tax_note_str = implode(" ", $shop_tax_notes_arr);
        $final_notes = trim($final_notes . "\n[หมายเหตุบิล: " . $tax_note_str . "]");
    }

    // จัดการค่าใช้จ่าย
    $exp_names = $_POST['exp_name'] ?? [];
    $exp_amounts = $_POST['exp_amount'] ?? [];
    $uploaded_files_map = uploadExpenseFiles('exp_file');

    $expense_summary_list = [];
    $total_expense = 0;
    $expense_files_db = [];

    for ($i = 0; $i < count($exp_names); $i++) {
        $name = trim($exp_names[$i]);
        $amount = floatval($exp_amounts[$i]);
        if (!empty($name) || $amount > 0) {
            $total_expense += $amount;
            $expense_summary_list[] = "$name (" . number_format($amount, 2) . ")";
            if(isset($uploaded_files_map[$i])) {
                $expense_files_db[] = $uploaded_files_map[$i];
            }
        }
    }

    $expense_list_str = implode(", ", $expense_summary_list); 
    $expense_files_str = implode(",", $expense_files_db);

    $sql = "INSERT INTO report_purchases (
        report_date, reporter_name, work_type, area, province, gps, gps_address, 
        supplier_name, project_name, item_count, item_details, problem,
        tax_invoice_status, additional_notes,
        expense_list, expense_files, total_expense, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssssssssissssssds", 
            $report_date, $reporter_name, $work_type, $area, $province, $gps, $gps_address,
            $supplier_name, $project_name, $item_count, $item_details, $problem,
            $tax_invoice_status, $final_notes, 
            $expense_list_str, $expense_files_str, $total_expense, $created_at
        );
        if ($stmt->execute()) { header("Location: StaffHistory.php"); exit(); } 
        else { $message = "Error: " . $stmt->error; } 
        $stmt->close();
    } else { $message = "Prepare Error: " . $conn->error; } 
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานฝ่ายจัดซื้อ - TJC</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* (คง CSS เดิมไว้ทั้งหมด เพราะส่วนนี้ถูกต้องแล้ว) */
        :root {
            --primary-color: #059669;
            --primary-light: #d1fae5;
            --text-main: #1f2937;
            --bg-body: #f0fdf4;
            --bg-card: #ffffff;
            --bg-input: #f9fafb;
            --border-color: #d1fae5;
            --hover-bg: #ecfdf5;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --danger: #ef4444;
            --danger-bg: #fee2e2;
        }
        body { font-family: 'Prompt', sans-serif; background-color: var(--bg-body); margin: 0; }
        .main-container { width: 100%; padding: 30px; box-sizing: border-box; }
        @media (min-width: 992px) { .main-container { margin-left: 150px; width: calc(100% - 270px); padding: 40px; } }
        @media (max-width: 991px) { .main-container { margin-left: 0; width: 100%; padding-top: 80px; } }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px dashed #a7f3d0; }
        .top-header h1 { font-size: 1.8rem; font-weight: 700; margin: 0; color: #065f46; display: flex; align-items: center; gap: 10px; }
        .form-container { background-color: var(--bg-card); border-radius: 20px; padding: 30px; border-top: 5px solid var(--primary-color); box-shadow: var(--shadow); border: 1px solid var(--border-color); max-width: 850px; margin: 0 auto; }
        .section-title { font-size: 1.2rem; font-weight: 600; color: var(--primary-color) !important; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .section-title i { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: var(--hover-bg); }
        .form-group { margin-bottom: 20px; width: 100%; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main); }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid #d1d5db; background-color: var(--bg-input); font-family: 'Prompt'; transition: 0.3s; box-sizing: border-box; }
        .form-input:focus { outline: none; border-color: var(--primary-color); ring: 2px solid var(--hover-bg); }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .form-grid-2 { grid-template-columns: 1fr; } }
        .purchase-card { background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 15px; overflow: hidden; box-shadow: var(--shadow); margin-bottom: 30px; position: relative; border-left: 5px solid var(--primary-color); animation: slideIn 0.3s; }
        .pc-header { background: var(--hover-bg); padding: 12px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .pc-title-label { font-weight: 700; color: var(--primary-color); font-size: 1.1rem; }
        .pc-body { padding: 25px; background: var(--bg-card); }
        .btn-remove-shop { background: var(--danger-bg); color: var(--danger); border:none; padding: 5px 12px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .product-item-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; margin-bottom: 15px; position: relative; box-shadow: var(--shadow); }
        .pic-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .pic-title { font-size: 13px; font-weight: 700; color: #6b7280; background: var(--hover-bg); padding: 2px 10px; border-radius: 20px; }
        .btn-remove-mini { background: none; border: none; color: var(--danger); cursor: pointer; font-size: 16px; }
        .btn-add-card { background: var(--bg-card); color: var(--primary-color); border: 2px dashed var(--primary-color); padding: 12px; border-radius: 12px; cursor: pointer; font-weight: 600; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; font-size: 14px; }
        .btn-add-shop { background: var(--text-main); color: var(--bg-card); width: 100%; padding: 15px; border-radius: 15px; border: none; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 1rem; box-shadow: var(--shadow); margin-top: 10px; margin-bottom: 30px; }
        .dynamic-box { background: var(--hover-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; }
        .expense-row { display: grid; grid-template-columns: 2fr 1fr 1.5fr auto; gap: 10px; margin-bottom: 10px; align-items: center; padding-bottom: 10px; border-bottom: 1px dashed var(--border-color); }
        .btn-add-row { background: var(--hover-bg); color: var(--primary-color); border: 1px solid var(--primary-color); padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; width: 100%; justify-content: center; margin-top: 10px; transition: 0.2s;}
        .btn-remove { background: var(--danger-bg); color: var(--danger); border:none; width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; flex-shrink: 0; }
        .total-bar { background: var(--primary-color); color: #fff; padding: 15px; text-align: right; border-radius: 10px; margin-top: 20px; font-weight: bold; font-size: 1.2rem; }
        .card-radio-group { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
        .card-radio-opt { cursor: pointer; position: relative; flex: 1; min-width: 120px; }
        .card-radio-opt input { position: absolute; opacity: 0; }
        .card-radio-box { display: flex; align-items: center; justify-content: center; gap: 5px; padding: 10px; border: 1px solid var(--border-color); background: var(--bg-input); border-radius: 8px; transition: 0.2s; font-size: 13px; font-weight: 600; text-align: center; color: var(--text-main); }
        .card-radio-opt input:checked + .card-radio-box { border-color: transparent; color: #fff; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card-radio-opt input[value="ได้รับแล้ว"]:checked + .card-radio-box { background: #10b981; }
        .card-radio-opt input[value="รอส่งตามหลัง"]:checked + .card-radio-box { background: #f59e0b; }
        .card-radio-opt input[value="ไม่มี/ออกไม่ได้"]:checked + .card-radio-box { background: #ef4444; }
        .btn-submit { width: 100%; padding: 18px; background: var(--primary-color); color: #fff !important; border: none; border-radius: 20px; font-size: 1.1rem; font-weight: 700; cursor: pointer; margin-top: 30px; transition:0.3s; }
        .file-upload-wrapper input[type=file] { width: 100%; font-size: 12px; color: #6b7280; }
        .tax-status-box { background: var(--hover-bg); border: 1px dashed var(--primary-color); padding: 15px; border-radius: 10px; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-container">
    <header class="top-header">
        <h1><i class="fas fa-shopping-cart"></i> รายงานฝ่ายจัดซื้อ</h1>
    </header>

    <?php if(!empty($message)): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="form-container">
        <form method="post" action="" enctype="multipart/form-data" id="purchaseForm">
            
            <div class="form-section">
                <div class="section-title"><i class="fas fa-info-circle"></i> 1. แจ้งงานประจำวัน</div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>วันที่รายงาน <span style="color:red">*</span></label>
                        <input type="hidden" name="report_date" id="reportDateHidden" value="<?php echo date('Y-m-d'); ?>">
                        <input type="text" id="reportDateDisplay" class="form-input" placeholder="เลือกวันที่" readonly required>
                    </div>
                    <div class="form-group">
                        <label>ผู้รายงาน</label>
                        <input type="text" value="<?php echo $_SESSION['fullname']; ?>" class="form-input" readonly style="opacity: 0.7;">
                        </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title"><i class="fas fa-clipboard-list"></i> 2. อัพเดทงานซื้อหน้างาน</div>
                
                <div id="shops_container">
                    </div>

                <button type="button" class="btn-add-shop" onclick="addShopCard()">
                    <i class="fas fa-store"></i> เพิ่มร้านค้าอีก (ซื้อจากหลายร้าน)
                </button>

                <div class="form-group">
                    <label style="color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> มีปัญหาอะไรไหม? (ถ้ามีระบุ)</label>
                    <textarea name="problem" class="form-textarea" rows="2" placeholder="เช่น ของหมด, ร้านปิด, ราคาแพงกว่าปกติ..."></textarea>
                </div>
                
                <div class="form-group">
                    <label style="color:#059669;"><i class="fas fa-pen"></i> บันทึกเพิ่มเติม (Optional)</label>
                    <textarea name="additional_notes" class="form-textarea" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติมอื่นๆ..."></textarea>
                </div>

            </div>

            <div class="form-section">
                <div class="section-title"><i class="fas fa-receipt"></i> 3. เบิกค่าใช้จ่าย (ถ้ามี)</div>
                <div class="dynamic-box">
                    <div id="expense_container">
                        <div class="expense-row">
                            <input type="text" name="exp_name[]" class="form-input" placeholder="รายการ (เช่น ค่าน้ำมัน)">
                            <input type="number" step="0.01" name="exp_amount[]" class="form-input calc-expense" placeholder="จำนวนเงิน" oninput="calculateTotal()">
                            <div class="file-upload-wrapper">
                                <input type="file" name="exp_file[]" class="form-input" accept="image/*,.pdf">
                            </div>
                            <button type="button" class="btn-remove" onclick="removeExpenseRow(this)"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn-add-row" onclick="addExpenseRow()">
                        <i class="fas fa-plus-circle"></i> เพิ่มรายการเบิก
                    </button>
                    <div class="total-bar">
                        ยอดรวมสุทธิ: <span id="totalDisplay">0.00</span> บาท
                    </div>
                </div>
            </div>

            <button type="button" class="btn-submit" onclick="confirmSubmit()">
                <i class="fas fa-paper-plane" style="margin-right:10px;"></i> ส่งรายงานฝ่ายจัดซื้อ
            </button>

        </form>
    </div>
    <div style="height: 60px;"></div>
</div>

<script>
    let shopCount = 0;

    document.addEventListener('DOMContentLoaded', function() {
        flatpickr("#reportDateDisplay", {
            dateFormat: "d/m/Y", defaultDate: "today", locale: "th", disableMobile: true,
            onChange: function(dates) { if (dates.length) document.getElementById("reportDateHidden").value = formatDate(dates[0]); }
        });
        
        addShopCard();
    });

    function formatDate(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    window.toggleRadio = function(el) {
        if (el.getAttribute('data-checked') === 'true') {
            el.checked = false;
            el.setAttribute('data-checked', 'false');
        } else {
            let group = document.getElementsByName(el.name);
            for(let i=0; i<group.length; i++) {
                group[i].setAttribute('data-checked', 'false');
            }
            el.setAttribute('data-checked', 'true');
        }
    }

    // --- Multi-Shop System ---
    function addShopCard() {
        const container = document.getElementById('shops_container');
        const shopIndex = shopCount;
        shopCount++;

        const shopDiv = document.createElement('div');
        shopDiv.className = 'purchase-card';
        shopDiv.id = `shop_card_${shopIndex}`;
        
        shopDiv.innerHTML = `
            <div class="pc-header">
                <div class="pc-title-label"><i class="fas fa-store"></i> ร้านค้าที่ ${shopIndex + 1}</div>
                ${shopIndex > 0 ? `<button type="button" class="btn-remove-shop" onclick="removeShopCard(${shopIndex})"><i class="fas fa-trash"></i> ลบร้านนี้</button>` : ''}
            </div>
            <div class="pc-body">
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>ชื่อร้านค้า / ซัพพลายเออร์ <span style="color:red">*</span></label>
                        <input type="text" name="shops[${shopIndex}][supplier]" class="form-input" placeholder="ระบุชื่อร้าน..." required>
                    </div>
                    <div class="form-group">
                        <label>หน้างาน / โครงการ(ชื่องบ)</label>
                        <input type="text" name="shops[${shopIndex}][project]" class="form-input" placeholder="ระบุหน้างาน (ถ้ามี)">
                    </div>
                </div>

                <div class="form-group">
                    <label>เลขที่เอกสาร / เลขที่บิล (ถ้ามี)</label>
                    <input type="text" name="shops[${shopIndex}][doc_no]" class="form-input" placeholder="ระบุเลขที่บิล / PO / ใบเสร็จ...">
                </div>

                <div style="border-top:1px dashed var(--border-color); margin-bottom:15px; margin-top:5px;"></div>

                <label style="margin-bottom:10px; display:block; color:var(--text-main); font-weight:600;">📦 รายการสินค้าที่ซื้อ</label>
                
                <div id="product_container_${shopIndex}">
                    <div class="product-item-card">
                        <div class="pic-header">
                            <span class="pic-title">สินค้า #1</span>
                            <button type="button" class="btn-remove-mini" onclick="removeProductRow(this)"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="form-grid-2" style="margin-bottom:0; gap:10px;">
                            <input type="text" name="shops[${shopIndex}][products][0][name]" class="form-input" placeholder="ชื่อสินค้า (เช่น ปูนเสือ)">
                            <input type="text" name="shops[${shopIndex}][products][0][qty]" class="form-input" placeholder="จำนวน (เช่น 5 ถุง)">
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-add-card" onclick="addProductToShop(${shopIndex})" style="margin-bottom:20px;">
                    <i class="fas fa-plus-circle"></i> เพิ่มสินค้าในร้านนี้
                </button>

                <div class="tax-status-box">
                    <label style="color:var(--primary-color); margin-bottom:10px; display:block; font-weight:700;">
                        <i class="fas fa-file-invoice"></i> สถานะใบกำกับภาษี
                    </label>
                    <div class="card-radio-group">
                        <label class="card-radio-opt">
                            <input type="radio" name="shops[${shopIndex}][tax_status]" value="ได้รับแล้ว" onclick="toggleRadio(this)">
                            <div class="card-radio-box"><i class="fas fa-check"></i> ได้รับแล้ว</div>
                        </label>
                        <label class="card-radio-opt">
                            <input type="radio" name="shops[${shopIndex}][tax_status]" value="รอส่งตามหลัง" onclick="toggleRadio(this)">
                            <div class="card-radio-box"><i class="fas fa-clock"></i> รอส่งตามหลัง</div>
                        </label>
                        <label class="card-radio-opt">
                            <input type="radio" name="shops[${shopIndex}][tax_status]" value="ไม่มี/ออกไม่ได้" onclick="toggleRadio(this)">
                            <div class="card-radio-box"><i class="fas fa-times"></i> ไม่มี/ร้านไม่ออกให้</div>
                        </label>
                    </div>
                    <input type="text" name="shops[${shopIndex}][tax_note]" class="form-input" placeholder="หมายเหตุ (เช่น จะส่งมาทีหลัง)" style="font-size:13px;">
                </div>

            </div>
        `;
        container.appendChild(shopDiv);
    }

    function removeShopCard(index) {
        const card = document.getElementById(`shop_card_${index}`);
        if(card) card.remove();
    }

    function addProductToShop(shopIndex) {
        const container = document.getElementById(`product_container_${shopIndex}`);
        const prodCount = container.children.length; // 0-based index
        
        const newProd = document.createElement('div');
        newProd.className = 'product-item-card';
        newProd.innerHTML = `
            <div class="pic-header">
                <span class="pic-title">สินค้า #${prodCount + 1}</span>
                <button type="button" class="btn-remove-mini" onclick="removeProductRow(this)"><i class="fas fa-times"></i></button>
            </div>
            <div class="form-grid-2" style="margin-bottom:0; gap:10px;">
                <input type="text" name="shops[${shopIndex}][products][${prodCount}][name]" class="form-input" placeholder="ชื่อสินค้า">
                <input type="text" name="shops[${shopIndex}][products][${prodCount}][qty]" class="form-input" placeholder="จำนวน/หน่วย">
            </div>
        `;
        container.appendChild(newProd);
    }

    function removeProductRow(btn) {
        const card = btn.closest('.product-item-card');
        const container = card.parentElement;
        if(container.children.length <= 1) {
            card.querySelectorAll('input').forEach(i => i.value = '');
        } else {
            card.remove();
            Array.from(container.children).forEach((c, i) => {
                c.querySelector('.pic-title').innerText = `สินค้า #${i + 1}`;
            });
        }
    }

    // --- Expense Logic ---
    function addExpenseRow() {
        const container = document.getElementById('expense_container');
        const newRow = document.createElement('div');
        newRow.className = 'expense-row';
        newRow.innerHTML = `
            <input type="text" name="exp_name[]" class="form-input" placeholder="รายการ">
            <input type="number" step="0.01" name="exp_amount[]" class="form-input calc-expense" placeholder="จำนวนเงิน" oninput="calculateTotal()">
            <div class="file-upload-wrapper"><input type="file" name="exp_file[]" class="form-input" accept="image/*,.pdf"></div>
            <button type="button" class="btn-remove" onclick="removeExpenseRow(this)"><i class="fas fa-trash-alt"></i></button>
        `;
        container.appendChild(newRow);
    }
    function removeExpenseRow(btn) {
        const container = document.getElementById('expense_container');
        if(container.children.length > 1) { btn.parentElement.remove(); calculateTotal(); }
        else { btn.parentElement.querySelectorAll('input').forEach(i => i.value = ''); calculateTotal(); }
    }
    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.calc-expense').forEach(input => {
            let val = parseFloat(input.value);
            if (!isNaN(val)) total += val;
        });
        document.getElementById('totalDisplay').innerText = total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    // ✅ 6. ปรับแก้ให้เหลือแค่ยืนยันการส่ง ไม่มีการจับเวลาเครื่องแล้ว
    function confirmSubmit() {
        // ตรวจสอบข้อมูลเบื้องต้น
        const form = document.getElementById('purchaseForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'ยืนยันการส่งรายงาน?', icon: 'question',
            showCancelButton: true, confirmButtonText: 'ส่งข้อมูล', cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#059669', cancelButtonColor: '#d33'
        }).then((res) => {
            if(res.isConfirmed) { 
                // ส่งฟอร์มปกติ ปล่อยให้ PHP จัดการเวลา
                form.submit(); 
            }
        });
    }
</script>

</body>
</html>