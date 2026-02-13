<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// 🟢 1. ตั้งค่า Timezone ให้เป็นไทย (สำคัญมาก)
date_default_timezone_set('Asia/Bangkok');

// --- 1. เตรียมตัวแปรสำหรับโหมดแก้ไข ---
$edit_data = null;
$is_edit_mode = false;

if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $q_edit = $conn->query("SELECT * FROM document_submissions WHERE id = $edit_id");
    if ($q_edit->num_rows > 0) {
        $edit_data = $q_edit->fetch_assoc();
        $is_edit_mode = true;
    }
}

// --- 2. Handle Actions (บันทึกข้อมูล) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_doc') {

    // รับค่าจากฟอร์ม
    $doc_number = $_POST['doc_number'];
    $doc_type = $_POST['doc_type'];
    $company_id = intval($_POST['company_id']);
    $job_site = $_POST['job_site'];
    $supplier_name = trim($_POST['supplier_name']);
    $description = $_POST['description'];
    $amount = floatval(str_replace(',', '', $_POST['amount']));

    // Bank Data
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_account = trim($_POST['bank_account'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');

    // ถ้าข้อมูลครบ ให้เช็คและบันทึกเข้าสมุดบัญชี (bank_accounts)
    if (!empty($bank_account) && !empty($account_name) && !empty($bank_name)) {
        $chk_acc = $conn->prepare("SELECT bank_account FROM bank_accounts WHERE bank_account = ?");
        $chk_acc->bind_param("s", $bank_account);
        $chk_acc->execute();
        $chk_acc->store_result();

        if ($chk_acc->num_rows == 0) {
            $ins_acc = $conn->prepare("INSERT INTO bank_accounts (bank_account, account_name, bank_name) VALUES (?, ?, ?)");
            $ins_acc->bind_param("sss", $bank_account, $account_name, $bank_name);
            $ins_acc->execute();
            $ins_acc->close();
        }
        $chk_acc->close();
    }

    // Attachments
    $att_select = $_POST['attachment_select'] ?? '';
    $attachments = json_encode([$att_select], JSON_UNESCAPED_UNICODE);

    // WHT & Credit
    $wht_tax = $_POST['wht_tax'] ?? 'ไม่มี';
    $wht_amount = !empty($_POST['wht_amount']) ? floatval($_POST['wht_amount']) : 0.00;
    $is_credit = isset($_POST['is_credit']);
    $credit_term = ($is_credit && !empty($_POST['credit_term'])) ? intval($_POST['credit_term']) : 0;

    $current_user = $_SESSION['fullname'] ?? 'System';
    $edit_doc_id = $_POST['edit_doc_id'];

    if (!empty($supplier_name)) {
        $check_sql = "SELECT id FROM suppliers WHERE name = ?";
        $stmt_chk = $conn->prepare($check_sql);
        $stmt_chk->bind_param("s", $supplier_name);
        $stmt_chk->execute();
        $stmt_chk->store_result();

        if ($stmt_chk->num_rows == 0) {
            $insert_sup_sql = "INSERT INTO suppliers (name) VALUES (?)";
            $stmt_add = $conn->prepare($insert_sup_sql);
            $stmt_add->bind_param("s", $supplier_name);
            $stmt_add->execute();
            $stmt_add->close();
        }
        $stmt_chk->close();
    }

    if (!empty($edit_doc_id)) {
        // --- 🟢 1. ดึงข้อมูลเก่า (ครบทุกฟิลด์ รวม PO, AX, บริษัท, ไฟล์แนบ) ---
        $stmt_old = $conn->prepare("SELECT doc_number, doc_type, company_id, job_site, supplier_name, description, amount, wht_tax, wht_amount, credit_term, bank_name, bank_account, account_name, attachments FROM document_submissions WHERE id = ?");
        $stmt_old->bind_param("i", $edit_doc_id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        $old_data = $res_old->fetch_assoc();
        $stmt_old->close();
        // ------------------------------------------

        // UPDATE ข้อมูล
        $sql = "UPDATE document_submissions SET 
                doc_number=?, doc_type=?, company_id=?, job_site=?, supplier_name=?, 
                description=?, amount=?, bank_name=?, bank_account=?, account_name=?, 
                attachments=?, wht_tax=?, wht_amount=?, credit_term=?
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("SQL Update Error: " . $conn->error);
        }
        $stmt->bind_param(
            "ssisssdsssssdii",
            $doc_number,
            $doc_type,
            $company_id,
            $job_site,
            $supplier_name,
            $description,
            $amount,
            $bank_name,
            $bank_account,
            $account_name,
            $attachments,
            $wht_tax,
            $wht_amount,
            $credit_term,
            $edit_doc_id
        );

        // --- 🟢 2. เปรียบเทียบและบันทึก Log ---
        if ($stmt->execute()) {
            $changes = [];
            
            // ✅ กลุ่ม 1: ข้อมูล PO / AX / บริษัท
            if ($old_data['doc_number'] != $doc_number) $changes[] = "เลขที่เอกสาร: '{$old_data['doc_number']}' -> '$doc_number'";
            if ($old_data['doc_type'] != $doc_type) $changes[] = "ประเภท: '{$old_data['doc_type']}' -> '$doc_type'";
            if ($old_data['company_id'] != $company_id) {
                // 1. หาชื่อบริษัทเก่า
                $q_old_c = $conn->query("SELECT company_name FROM companies WHERE id = " . intval($old_data['company_id']));
                $name_old = ($q_old_c && $row = $q_old_c->fetch_assoc()) ? $row['company_name'] : 'ไม่ระบุ';

                // 2. หาชื่อบริษัทใหม่
                $q_new_c = $conn->query("SELECT company_name FROM companies WHERE id = " . intval($company_id));
                $name_new = ($q_new_c && $row = $q_new_c->fetch_assoc()) ? $row['company_name'] : 'ไม่ระบุ';

                $changes[] = "เปลี่ยนบริษัท: '$name_old' -> '$name_new'";
            }
            if ($old_data['job_site'] != $job_site) $changes[] = "หน้างาน: '{$old_data['job_site']}' -> '$job_site'";
            if ($old_data['supplier_name'] != $supplier_name) $changes[] = "ผู้ขาย: '{$old_data['supplier_name']}' -> '$supplier_name'";
            if ($old_data['description'] != $description) $changes[] = "รายละเอียด: '{$old_data['description']}' -> '$description'";
            
            // ✅ กลุ่ม 2: ตัวเลขการเงิน
            if (floatval($old_data['amount']) != floatval($amount)) {
                $changes[] = "ยอดเงิน: '" . number_format($old_data['amount'], 2) . "' -> '" . number_format($amount, 2) . "'";
            }
            if ($old_data['wht_tax'] != $wht_tax) {
                $changes[] = "ประเภทหัก ณ ที่จ่าย: '{$old_data['wht_tax']}' -> '$wht_tax'";
            }
            if (floatval($old_data['wht_amount']) != floatval($wht_amount)) {
                $changes[] = "ยอดหัก ณ ที่จ่าย: '" . number_format($old_data['wht_amount'], 2) . "' -> '" . number_format($wht_amount, 2) . "'";
            }
            if (intval($old_data['credit_term']) != intval($credit_term)) {
                $changes[] = "เครดิต: '{$old_data['credit_term']}' -> '$credit_term'";
            }

            // ✅ กลุ่ม 3: บัญชีธนาคาร
            if ($old_data['bank_name'] != $bank_name) $changes[] = "ธนาคาร: '{$old_data['bank_name']}' -> '$bank_name'";
            if ($old_data['bank_account'] != $bank_account) $changes[] = "เลขบัญชี: '{$old_data['bank_account']}' -> '$bank_account'";
            if ($old_data['account_name'] != $account_name) $changes[] = "ชื่อบัญชี: '{$old_data['account_name']}' -> '$account_name'";

            // ✅ กลุ่ม 4: ไฟล์แนบ
            if ($old_data['attachments'] != $attachments) {
                $changes[] = "มีการเปลี่ยนแปลงไฟล์แนบ/ประเภทแนบ";
            }

            // บันทึกลง Database
            if (!empty($changes)) {
                $log_details = implode("\n", $changes);
                $log_action = 'edit';
                $log_user = $_SESSION['fullname'] ?? 'Unknown';
                $log_now = date('Y-m-d H:i:s');
                
                $stmt_log = $conn->prepare("INSERT INTO document_logs (doc_id, action_type, action_by, action_at, details) VALUES (?, ?, ?, ?, ?)");
                $stmt_log->bind_param("issss", $edit_doc_id, $log_action, $log_user, $log_now, $log_details);
                $stmt_log->execute();
                $stmt_log->close();
            }

            header("Location: DocumentDashboard.php");
            exit();
        } else {
            echo "Execute Error: " . $stmt->error;
            exit();
        }
        $stmt->close();

    } else {
        // ... (ส่วน INSERT ใหม่ ปล่อยไว้เหมือนเดิม) ...
        // INSERT (บันทึกใหม่)
        // 🟢 สร้างตัวแปรเวลาปัจจุบัน (PHP เป็น Timezone Thai แล้ว) Format: Y-m-d H:i:s (24ชม.)
        $thai_timestamp = date('Y-m-d H:i:s');
        
        $ordered_by = $_POST['ordered_by'];
        
        // 🟢 เปลี่ยน NOW() เป็น ? เพื่อรับค่าจาก PHP
        $sql = "INSERT INTO document_submissions 
                (doc_number, doc_type, company_id, ordered_by, job_site, supplier_name, 
                description, amount, bank_name, bank_account, account_name, 
                attachments, wht_tax, wht_amount, credit_term, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("SQL Insert Error: " . $conn->error);
        }
        
        // 🟢 เพิ่ม 's' ตัวสุดท้ายใน Type String (รวมเป็น 16 ตัว)
        // 🟢 เพิ่ม $thai_timestamp ใน Parameter ตัวสุดท้าย
        $stmt->bind_param(
            "ssissssdsssssdis", 
            $doc_number,
            $doc_type,
            $company_id,
            $ordered_by,
            $job_site,
            $supplier_name,
            $description,
            $amount,
            $bank_name,
            $bank_account,
            $account_name,
            $attachments,
            $wht_tax,
            $wht_amount,
            $credit_term,
            $thai_timestamp 
        );
    }

    if ($stmt->execute()) {
        header("Location: DocumentDashboard.php");
        exit();
    } else {
        echo "Execute Error: " . $stmt->error;
        exit();
    }
    $stmt->close();
}

// --- 3. Fetch Data ---
$companies = [];
$q_comp = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
if ($q_comp)
    while ($row = $q_comp->fetch_assoc())
        $companies[] = $row;

$banks = [];
$bank_rules_db = [];
$q_banks = $conn->query("SELECT bank_name, digit_limit FROM bank_masters ORDER BY bank_name ASC");
if ($q_banks) {
    while ($bm = $q_banks->fetch_assoc()) {
        $banks[] = $bm['bank_name'];
        $bank_rules_db[$bm['bank_name']] = (int) $bm['digit_limit'];
    }
}
if (empty($banks)) {
    $banks = ["กสิกรไทย (KBANK)", "ไทยพาณิชย์ (SCB)", "กรุงเทพ (BBL)", "กรุงไทย (KTB)", "อื่นๆ"];
    $bank_rules_db = ["กสิกรไทย (KBANK)" => 10, "ไทยพาณิชย์ (SCB)" => 10, "กรุงเทพ (BBL)" => 10, "กรุงไทย (KTB)" => 10, "อื่นๆ" => 0];
}

$suppliers_list = [];
$q_sup = $conn->query("SELECT name FROM suppliers ORDER BY name ASC");
if ($q_sup)
    while ($row = $q_sup->fetch_assoc())
        $suppliers_list[] = $row['name'];

$accounts_history = [];
$q_acc = $conn->query("SELECT bank_account, account_name, bank_name FROM bank_accounts ORDER BY account_name ASC");
if ($q_acc) {
    while ($row = $q_acc->fetch_assoc()) {
        $accounts_history[trim($row['bank_account'])] = [
            'name' => $row['account_name'],
            'bank' => $row['bank_name']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title><?php echo $is_edit_mode ? 'แก้ไขเอกสาร' : 'ลงสมุดเสนอเอกสาร'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        /* (CSS เดิมทั้งหมด ย่อเพื่อความสะดวก) */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #ec4899;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-label: #334155;
            --text-muted: #94a3b8;
            --input-bg: #ffffff;
            --input-border: #e2e8f0;
            --input-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            --card-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --btn-cancel-bg: #f1f5f9;
            --btn-cancel-text: #64748b;
        }

        [data-theme="dark"] {
            --primary: #818cf8;
            --primary-dark: #6366f1;
            --secondary: #f472b6;
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-label: #cbd5e1;
            --text-muted: #94a3b8;
            --input-bg: #0f172a;
            --input-border: #334155;
            --input-shadow: none;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            --btn-cancel-bg: #334155;
            --btn-cancel-text: #cbd5e1;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
        }

        .main-container {
            width: 100%;
            padding: 40px 20px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .header-section {
            margin-bottom: 30px;
            text-align: left;
        }

        .header-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-icon {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 10px;
            border-radius: 14px;
        }

        .header-subtitle {
            color: var(--text-label);
            font-size: 15px;
            margin-top: 5px;
            opacity: 0.8;
            margin-left: 54px;
        }

        .form-card {
            background: var(--bg-card);
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            padding: 50px;
            border: 1px solid var(--input-border);
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .form-section {
            margin-bottom: 45px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px dashed var(--input-border);
        }

        .section-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border-radius: 14px;
            border: 2px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text-main);
            font-family: 'Prompt';
            font-size: 15px;
            box-sizing: border-box;
            transition: 0.3s;
            box-shadow: var(--input-shadow);
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.2);
            transform: translateY(-3px);
        }

        .form-control[readonly] {
            background: rgba(0, 0, 0, 0.02);
            cursor: not-allowed;
            color: var(--text-muted);
            border-color: transparent;
            box-shadow: none;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        @media (max-width: 650px) {

            .grid-2,
            .option-grid {
                grid-template-columns: 1fr !important;
            }
        }

        /* Select2 */
        .select2-container .select2-selection--single {
            height: 54px !important;
            border-radius: 14px !important;
            border: 2px solid var(--input-border) !important;
            background-color: var(--input-bg) !important;
            box-shadow: var(--input-shadow) !important;
            display: flex;
            align-items: center;
            transition: 0.3s;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--text-main) !important;
            padding-left: 18px;
            font-size: 15px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            top: 14px;
            right: 14px;
        }

        .select2-dropdown {
            background-color: var(--bg-card) !important;
            border: 1px solid var(--input-border) !important;
            border-radius: 14px !important;
            box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.15) !important;
            padding: 10px;
            z-index: 9999;
        }

        .select2-search--dropdown .select2-search__field {
            background-color: var(--input-bg) !important;
            color: var(--text-main) !important;
            border: 1px solid var(--input-border) !important;
            border-radius: 8px !important;
            padding: 10px !important;
        }

        .select2-results__option {
            color: var(--text-main) !important;
            padding: 12px 15px !important;
            font-size: 14px;
            border-radius: 8px;
            margin-bottom: 2px;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
            color: white !important;
        }

        /* Radio Cards */
        .option-grid {
    display: grid;
    /* เทคนิคนี้จะทำให้ปุ่มเรียงกันสวยงามตามขนาดหน้าจอ */
    /* ถ้าจอใหญ่จะเรียง 4, ถ้าจอเล็กจะปัดลงมาเป็น 2x2 */
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); 
    gap: 15px; /* ระยะห่างระหว่างปุ่ม */
    margin-bottom: 20px;
}

/* ดีไซน์ตัวการ์ด */
.option-card {
    position: relative;
    cursor: pointer;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 15px 10px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    height: 80px; /* กำหนดความสูงให้เท่ากันหมด */
}

/* ซ่อนปุ่ม Radio กลมๆ เดิม */
.option-card input[type="radio"] {
    display: none;
}

/* จัดเนื้อหาในการ์ด */
.option-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: #64748b; /* สีเทาตอนยังไม่เลือก */
    font-size: 13px;
    font-weight: 600;
}

.option-content i {
    font-size: 20px;
    margin-bottom: 2px;
}

/* --- สถานะตอนเอาเมาส์ชี้ (Hover) --- */
.option-card:hover {
    border-color: #a5b4fc;
    background: #f8fafc;
    transform: translateY(-2px);
}

/* --- สถานะตอนเลือก (Checked) --- */
/* ใช้ :has selector (Browser ใหม่ๆ) หรือใช้ logic นี้ */
.option-card:has(input:checked) {
    border-color: #4f46e5; /* สีขอบตอนเลือก (สี Primary) */
    background-color: #e0e7ff; /* สีพื้นหลังจางๆ ตอนเลือก */
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
}

.option-card input:checked + .option-content {
    color: #4f46e5; /* เปลี่ยนสีตัวหนังสือและไอคอน */
}

/* Fallback สำหรับ Browser เก่าที่ไม่รองรับ :has */
.option-card input:checked {
    /* ถ้า CSS ข้างบนไม่ทำงาน อาจต้องใช้ JS ช่วยเปลี่ยน class */
}

        /* Toggles */
        .doc-type-switch {
            background: var(--input-bg);
            padding: 8px;
            border-radius: 50px;
            display: inline-flex;
            border: 1px solid var(--input-border);
        }

        .type-btn {
            padding: 10px 35px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: 0.3s;
        }

        .type-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transform: scale(1.05);
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
        }

        .btn-submit {
            flex: 2;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 18px;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.5);
            transition: 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            filter: brightness(1.1);
        }

        .btn-cancel {
            flex: 1;
            background: var(--btn-cancel-bg);
            color: var(--btn-cancel-text);
            border: none;
            padding: 18px;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: 0.3s;
        }

        .btn-cancel:hover {
            background: #cbd5e1;
            transform: translateY(-3px);
            color: #1e293b;
        }

        input[type="checkbox"] {
            width: 22px;
            height: 22px;
            accent-color: var(--secondary);
            cursor: pointer;
        }

        @media (max-width: 600px) {
            .form-actions {
                flex-direction: column-reverse;
            }

            .btn-submit,
            .btn-cancel {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        <div class="header-section">
            <div class="header-title"><i
                    class="fas <?php echo $is_edit_mode ? 'fa-edit' : 'fa-book-medical'; ?> header-icon"></i><?php echo $is_edit_mode ? 'แก้ไขเอกสาร' : 'ลงสมุดเสนอเอกสาร'; ?>
            </div>
            <div class="header-subtitle">
                <?php echo $is_edit_mode ? 'กำลังแก้ไขรายการ: <b>' . htmlspecialchars($edit_data['doc_number']) . '</b>' : 'กรอกข้อมูล PO/AX เพื่อดำเนินการขออนุมัติและจ่ายเงิน'; ?>
            </div>
        </div>

        <form method="POST" class="form-card" id="docForm" onsubmit="confirmSave(event)">
            <input type="hidden" name="action" value="submit_doc">
            <input type="hidden" name="edit_doc_id" value="<?php echo $edit_data['id'] ?? ''; ?>">

            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-file-contract"></i></div>
                    <div class="section-title">ข้อมูลเอกสาร</div>
                </div>
                <div class="form-group" style="text-align:center;">
                    <div class="doc-type-switch">
                        <?php $t = $edit_data['doc_type'] ?? 'PO'; ?>
                        <label class="type-btn <?php echo $t == 'PO' ? 'active' : ''; ?>" id="btn_PO"
                            onclick="selectDocType('PO')"><input type="radio" name="doc_type" value="PO" <?php echo $t == 'PO' ? 'checked' : ''; ?> style="display:none"> PO (ใบสั่งซื้อ)</label>
                        <label class="type-btn <?php echo $t == 'AX' ? 'active' : ''; ?>" id="btn_AX"
                            onclick="selectDocType('AX')"><input type="radio" name="doc_type" value="AX" <?php echo $t == 'AX' ? 'checked' : ''; ?> style="display:none"> AX (ค่าใช้จ่าย)</label>
                    </div>
                </div>
                <div class="grid-2">
    <div class="form-group">
        <label class="form-label">เลขที่เอกสาร <span style="color:#ef4444">*</span></label>
        
        <input type="text" name="doc_number" 
               class="form-control" required 
               placeholder="เช่น 2023-0001" 
               value="<?php echo $edit_data['doc_number'] ?? ''; ?>"
               oninput="this.value = this.value.replace(/[^0-9-]/g, '')">
               
    </div>
    
    <div class="form-group">
        <label class="form-label">บริษัท (ผู้ซื้อ) <span style="color:#ef4444">*</span></label>
        <select name="company_id" class="form-control" required>
            <option value="">-- เลือกบริษัท --</option>
            <?php foreach ($companies as $comp): ?>
                <option value="<?php echo $comp['id']; ?>" <?php echo (isset($edit_data) && $edit_data['company_id'] == $comp['id']) ? 'selected' : ''; ?>>
                    <?php echo $comp['company_name']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">โครงการ / หน้างาน <span
                                style="color:#ef4444">*</span></label><input type="text" name="job_site"
                            class="form-control" required placeholder="ระบุหน้างาน"
                            value="<?php echo $edit_data['job_site'] ?? ''; ?>"></div>
                    <div class="form-group"><label class="form-label">ผู้เปิดเอกสาร</label><input type="text"
                            name="ordered_by" class="form-control" readonly
                            value="<?php echo $edit_data['ordered_by'] ?? $_SESSION['fullname']; ?>"></div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="section-title">รายละเอียดการจ่ายเงิน</div>
                </div>
                <div class="form-group"><label class="form-label">ชื่อร้านค้า / Supplier <span
                            style="color:#ef4444">*</span></label><select name="supplier_name" id="supplier_select"
                        class="form-control" required style="width:100%;">
                        <option value="">-- พิมพ์หรือเลือกชื่อร้าน --</option>
                        <?php foreach ($suppliers_list as $sup_name): ?>
                            <option value="<?php echo $sup_name; ?>" <?php echo (isset($edit_data) && $edit_data['supplier_name'] == $sup_name) ? 'selected' : ''; ?>><?php echo $sup_name; ?>
                            </option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">รายละเอียด / งวดงาน <span
                            style="color:#ef4444">*</span></label><textarea name="description" class="form-control"
                        rows="2" required
                        placeholder="ระบุรายละเอียด..."><?php echo $edit_data['description'] ?? ''; ?></textarea></div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">ยอดจ่ายจริง (บาท) <span
                                style="color:#ef4444">*</span></label><input type="number" step="0.01" name="amount"
                            id="amount" class="form-control" required
                            style="font-weight:800; color:var(--primary); font-size:18px;" oninput="calculateWHT()"
                            placeholder="0.00" value="<?php echo $edit_data['amount'] ?? ''; ?>"></div>
                    <div class="form-group" style="display:flex; align-items:flex-end; padding-bottom:12px;"><label
                            style="cursor:pointer; font-weight:700; color:var(--primary); display:flex; align-items:center; gap:10px; background:rgba(99, 102, 241, 0.1); padding:10px 20px; border-radius:12px; transition:0.2s;"><?php $has_credit = !empty($edit_data['credit_term']); ?><input
                                type="checkbox" id="is_credit" name="is_credit" onchange="toggleCredit()" <?php echo $has_credit ? 'checked' : ''; ?>> ขอเครดิต (Credit Term)</label></div>
                </div>
                <div id="credit_options"
                    style="display:<?php echo $has_credit ? 'block' : 'none'; ?>; background:var(--input-bg); padding:25px; border-radius:18px; margin-bottom:20px; border:2px dashed var(--input-border);">
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom:0;"><label class="form-label"
                                style="font-size:12px;">เลือกวันมาตรฐาน (ตัวช่วย)</label><select id="credit_preset"
                                class="form-control" onchange="applyCreditPreset(this.value)">
                                <option value="" selected>-- ระบุเอง (พิมพ์ช่องขวา) --</option>
                                <option value="15">15 วัน</option>
                                <option value="30">30 วัน</option>
                                <option value="45">45 วัน</option>
                                <option value="60">60 วัน</option>
                                <option value="90">90 วัน</option>
                            </select></div>
                        <div class="form-group" style="margin-bottom:0;"><label class="form-label"
                                style="font-size:12px;">จำนวนวันเครดิต <span
                                    style="color:#ef4444">*</span></label><input type="number" name="credit_term"
                                id="credit_day" class="form-control" placeholder="ระบุจำนวนวัน..."
                                value="<?php echo $edit_data['credit_term'] ?? ''; ?>" oninput="resetCreditDropdown()">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-university"></i></div>
                    <div class="section-title">บัญชีปลายทาง</div>
                </div>
                <div class="form-group">
                    <label class="form-label">ชื่อบัญชี (ค้นหาจากชื่อคนรับเงิน) <span
                            style="color:#ef4444">*</span></label>
                    <select name="account_name" id="account_name" class="form-control" required style="width:100%;">
                        <option value="">-- ระบุชื่อเจ้าของบัญชี --</option>
                        <?php
                        $unique_names = [];
                        foreach ($accounts_history as $info) {
                            $unique_names[$info['name']] = $info['name'];
                        }
                        sort($unique_names);
                        foreach ($unique_names as $name):
                            ?>
                            <option value="<?php echo $name; ?>" <?php echo (isset($edit_data) && $edit_data['account_name'] == $name) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">เลขที่บัญชี <span style="color:#ef4444">*</span></label>
                        <select name="bank_account" id="bank_account" class="form-control" required style="width:100%;">
                            <option value="">-- เลือก/พิมพ์เลขบัญชี --</option>
                            <?php foreach ($accounts_history as $acc => $info): ?>
                                <option value="<?php echo $acc; ?>" <?php echo (isset($edit_data) && $edit_data['bank_account'] == $acc) ? 'selected' : ''; ?>>
                                    <?php echo $acc; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ธนาคาร <span style="color:#ef4444">*</span></label>
                        <select name="bank_name" id="bank_name" class="form-control" required>
                            <option value="">-- เลือกธนาคาร --</option>
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo $bank; ?>" <?php echo (isset($edit_data) && $edit_data['bank_name'] == $bank) ? 'selected' : ''; ?>><?php echo $bank; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-paperclip"></i></div>
                    <div class="section-title">เอกสารแนบ</div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="margin-bottom:15px;">เลือกประเภทเอกสารแนบ (1 รายการ) <span
                            style="color:#ef4444">*</span></label>
                    <div class="form-group">
    
    <div class="option-grid">
    <?php $att_str = $edit_data['attachments'] ?? '[]'; ?>
    
    <label class="option-card">
        <input type="radio" name="attachment_select" value="ใบสำคัญจ่าย"
            <?php echo strpos($att_str, 'ใบสำคัญจ่าย') !== false ? 'checked' : ''; ?>>
        <div class="option-content">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>ใบสำคัญจ่าย</span>
        </div>
    </label>

    <label class="option-card">
        <input type="radio" name="attachment_select" value="ใบกำกับภาษี 7%"
            <?php echo (strpos($att_str, 'ใบกำกับภาษี 7%') !== false && strpos($att_str, 'รอ') === false) ? 'checked' : ''; ?>>
        <div class="option-content">
            <i class="fas fa-file-invoice"></i>
            <span>ใบกำกับ 7%</span>
        </div>
    </label>

    <label class="option-card">
        <input type="radio" name="attachment_select" value="รอใบกำกับภาษี 7%"
            <?php echo strpos($att_str, 'รอใบกำกับภาษี 7%') !== false ? 'checked' : ''; ?>>
        <div class="option-content">
            <i class="fas fa-clock"></i>
            <span>รอใบกำกับ 7%</span>
        </div>
    </label>

    <label class="option-card">
        <input type="radio" name="attachment_select" value="ใบเสร็จรับเงิน"
            <?php echo strpos($att_str, 'ใบเสร็จรับเงิน') !== false ? 'checked' : ''; ?>>
        <div class="option-content">
            <i class="fas fa-receipt"></i>
            <span>ใบเสร็จรับเงิน</span>
        </div>
    </label>

    <label class="option-card">
        <input type="radio" name="attachment_select" value="ชุดชำระเงิน"
            <?php echo strpos($att_str, 'ชุดชำระเงิน') !== false ? 'checked' : ''; ?>>
        <div class="option-content">
            <i class="fas fa-money-check-alt"></i>
            <span>ชุดชำระเงิน</span>
        </div>
    </label>
</div>
</div>
                </div>
                <div class="form-group"
                    style="margin-top:30px; background:var(--input-bg); padding:25px; border-radius:18px; border:2px solid var(--input-border);">
                    <label class="form-label">หัก ณ ที่จ่าย (WHT)</label>
                    <?php
                    $wht_raw = $edit_data['wht_tax'] ?? 'ไม่มี';
                    $old_rate = 'ไม่มี';
                    $old_amt = '';
                    if (strpos($wht_raw, '1%') !== false)
                        $old_rate = '1%';
                    if (strpos($wht_raw, '2%') !== false) $old_rate = '2%';
                    if (strpos($wht_raw, '3%') !== false)
                        $old_rate = '3%';
                    if (strpos($wht_raw, '5%') !== false)
                        $old_rate = '5%';
                    if (preg_match('/\(([\d,.]+)/', $wht_raw, $m))
                        $old_amt = str_replace(',', '', $m[1]);
                    ?>
                    <div style="display:flex; gap:25px; flex-wrap:wrap;">
    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
        <input type="radio" name="wht_tax" value="ไม่มี" 
               <?php echo $old_rate == 'ไม่มี' ? 'checked' : ''; ?> 
               onchange="toggleWHT(this)"> ไม่มี
    </label>

    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
        <input type="radio" name="wht_tax" value="1%" 
               <?php echo $old_rate == '1%' ? 'checked' : ''; ?> 
               onchange="toggleWHT(this)"> 1%
    </label>

    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
        <input type="radio" name="wht_tax" value="2%" 
               <?php echo $old_rate == '2%' ? 'checked' : ''; ?> 
               onchange="toggleWHT(this)"> 2%
    </label>

    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
        <input type="radio" name="wht_tax" value="3%" 
               <?php echo $old_rate == '3%' ? 'checked' : ''; ?> 
               onchange="toggleWHT(this)"> 3%
    </label>

    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
        <input type="radio" name="wht_tax" value="5%" 
               <?php echo $old_rate == '5%' ? 'checked' : ''; ?> 
               onchange="toggleWHT(this)"> 5%
    </label>
</div>
                    <div id="wht_input_area"
                        style="display:<?php echo $old_rate != 'ไม่มี' ? 'block' : 'none'; ?>; margin-top:20px;">
                        <div style="display:flex; align-items:center; gap:15px;">
                            <span style="font-size:15px; font-weight:600; color:var(--text-main);">ยอดหัก:</span>
                            <input type="number" step="0.01" name="wht_amount" id="wht_amount" class="form-control"
                                style="width:180px; font-weight:800; color:#ef4444; font-size:16px;"
                                value="<?php echo $old_amt; ?>">
                            <span style="font-size:15px; font-weight:600; color:var(--text-main);">บาท</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="DocumentDashboard.php" class="btn-cancel"><i class="fas fa-times"></i> ยกเลิก</a>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i>
                    <?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'บันทึกเอกสาร'; ?></button>
            </div>
        </form>
    </div>

    <script>
    // --- 1. เตรียมข้อมูลจาก PHP ---
    const bankRules = <?php echo json_encode($bank_rules_db); ?>;
    const rawHistory = <?php echo json_encode($accounts_history ?? []); ?>;
    const accToInfo = rawHistory;
    const nameToAccList = {};
    const accountHistory = rawHistory;

    for (const [accNo, info] of Object.entries(rawHistory)) {
        if (!nameToAccList[info.name]) nameToAccList[info.name] = [];
        nameToAccList[info.name].push({ acc: accNo, bank: info.bank });
    }

    // --- 2. ทำงานเมื่อโหลดหน้าเว็บ ---
    $(document).ready(function () {
        
        $('#supplier_select').select2({ placeholder: "-- พิมพ์หรือเลือกชื่อร้านค้า --", allowClear: true, tags: true });

        $('#account_name').select2({ placeholder: "-- ระบุชื่อเจ้าของบัญชี --", allowClear: true, tags: true })
            .on('change', filterAccountsByName); 

        $('#bank_account').select2({
            placeholder: "-- เลือก/พิมพ์เลขบัญชี --",
            allowClear: true,
            tags: true,
            templateResult: formatAccountOption
        }).on('change', fillBankDetails);

        $('#bank_account').on('select2:open', function () {
            const bankName = $('#bank_name').val();
            const limit = bankRules[bankName] || 0;
            const searchField = document.querySelector('.select2-container--open .select2-search__field');
            if (searchField) {
                if (limit > 0) {
                    searchField.setAttribute('maxlength', limit);
                    searchField.placeholder = `กรอกได้ไม่เกิน ${limit} หลัก`;
                } else { 
                    searchField.removeAttribute('maxlength'); 
                }
                searchField.addEventListener('input', function (e) { 
                    this.value = this.value.replace(/[^0-9]/g, ''); 
                });
            }
        });

        // เรียกใช้ฟังก์ชันฉบับแก้ไข
        autoSelectOnBlur('#supplier_select');
        autoSelectOnBlur('#account_name');
        autoSelectOnBlur('#bank_account');
    });

    // --- 3. ฟังก์ชันเสริม ---

    // 🔥 [แก้ไขจุดสำคัญ] แก้บัคคลิกเลือกแล้วได้คำไม่เต็ม
    function autoSelectOnBlur(selector) {
        let isSelecting = false; // ตัวแปรเช็คสถานะการเลือก

        // 1. ถ้ามีการกดเลือกรายการ (Selecting) ให้จำไว้ว่ากำลังเลือก
        $(selector).on('select2:selecting', function () {
            isSelecting = true;
        });

        // 2. เมื่อเปิด Dropdown ให้รีเซ็ตสถานะ
        $(selector).on('select2:open', function () {
            isSelecting = false;
        });

        // 3. ตอนกำลังจะปิด (Closing)
        $(selector).on('select2:closing', function (e) {
            // 🛑 ถ้ากำลังกดเลือกรายการอยู่ (isSelecting = true) ให้หยุดทำงานส่วนนี้ทันที (เพื่อให้มันเอาค่าที่เลือก)
            if (isSelecting) {
                return; 
            }

            // ✅ ถ้าไม่ได้กดเลือก (เช่น กด Tab หรือคลิกข้างนอก) ค่อยเอาคำที่พิมพ์มาใส่
            var searchField = $('.select2-container--open .select2-search__field');
            if (searchField.length > 0) {
                var term = searchField.val().trim();
                if (term !== '' && term !== $(this).val()) {
                    if ($(this).find("option[value='" + term + "']").length === 0) {
                        var newOption = new Option(term, term, true, true);
                        $(this).append(newOption).trigger('change');
                    } else {
                        $(this).val(term).trigger('change');
                    }
                }
            }
        });
    }

    function fillBankDetails() {
        const accNo = $('#bank_account').val();
        const bankSelect = $('#bank_name');
        if (!accNo) return;

        if (accToInfo[accNo]) {
            const info = accToInfo[accNo];
            if (info.bank) bankSelect.val(info.bank).trigger('change');

            const currentName = $('#account_name').val();
            if (!currentName || currentName !== info.name) {
                $('#account_name').val(info.name).trigger('change.select2');
            }
        }
    }

    function filterAccountsByName() {
        const name = $('#account_name').val();
        const accSelect = $('#bank_account');
        const currentAcc = accSelect.val();

        accSelect.empty();
        accSelect.append(new Option("-- เลือก/พิมพ์เลขบัญชี --", ""));

        if (name && nameToAccList[name]) {
            const accounts = nameToAccList[name];
            let matchFound = false;

            accounts.forEach(item => {
                const newOption = new Option(item.acc, item.acc, false, false);
                accSelect.append(newOption);
                if (item.acc === currentAcc) matchFound = true;
            });

            if (matchFound) {
                accSelect.val(currentAcc).trigger('change.select2');
            } else if (accounts.length === 1 && !currentAcc) {
                accSelect.val(accounts[0].acc).trigger('change');
            } else {
                if (!currentAcc) accSelect.val(null).trigger('change.select2');
            }
        } else if (!name) {
            loadAllAccounts();
        } else {
            accSelect.append(new Option("-- พิมพ์เลขบัญชีใหม่ --", ""));
        }
    }

    function loadAllAccounts() {
        const accSelect = $('#bank_account');
        accSelect.empty();
        accSelect.append(new Option("-- เลือก/พิมพ์เลขบัญชี --", ""));
        for (const [accNo, info] of Object.entries(accToInfo)) {
            const option = new Option(accNo, accNo, false, false);
            accSelect.append(option);
        }
        accSelect.trigger('change.select2');
    }

    function formatAccountOption(state) {
        if (!state.id) return state.text;
        if (accountHistory[state.id]) {
            const info = accountHistory[state.id];
            return $(`<span><b>${state.text}</b> <span style="color:#94a3b8; font-size:12px;"> - ${info.name} (${info.bank})</span></span>`);
        }
        return state.text;
    }

    // ฟังก์ชันเดิม (ไม่ต้องแก้)
    function selectDocType(type) { document.querySelectorAll('.type-btn').forEach(el => el.classList.remove('active')); document.getElementById(type === 'PO' ? 'btn_PO' : 'btn_AX').classList.add('active'); }
    function toggleCredit() { const isCredit = document.getElementById('is_credit').checked; document.getElementById('credit_options').style.display = isCredit ? 'block' : 'none'; const input = document.getElementById('credit_day'); if (isCredit) { input.setAttribute('required', 'required'); if (input.value === '') input.focus(); } else { input.removeAttribute('required'); input.value = ''; document.getElementById('credit_preset').value = ""; } }
    function applyCreditPreset(val) { const input = document.getElementById('credit_day'); if (val !== "") { input.value = val; } else { input.value = ""; input.focus(); } }
    function resetCreditDropdown() { document.getElementById('credit_preset').value = ""; }
    function toggleWHT(radio) { const area = document.getElementById('wht_input_area'); const input = document.getElementById('wht_amount'); if (radio.value === 'ไม่มี') { area.style.display = 'none'; input.removeAttribute('required'); input.value = ''; } else { area.style.display = 'block'; input.setAttribute('required', 'required'); calculateWHT(); } }
    function calculateWHT() { const amount = parseFloat(document.getElementById('amount').value) || 0; const rateVal = document.querySelector('input[name="wht_tax"]:checked').value; if (rateVal !== 'ไม่มี') { const rate = parseFloat(rateVal.replace('%', '')); document.getElementById('wht_amount').value = ((amount * rate) / 100).toFixed(2); } }
    function confirmSave(e) { e.preventDefault(); if (!$('#supplier_select').val()) { Swal.fire({ icon: 'warning', title: 'กรุณาระบุชื่อร้านค้า', confirmButtonColor: '#f59e0b' }); return; } if (!$('#bank_account').val()) { Swal.fire({ icon: 'warning', title: 'กรุณาระบุเลขบัญชี', confirmButtonColor: '#f59e0b' }); return; } const bankName = $('#bank_name').val(); const accNo = $('#bank_account').val(); if (bankName && bankRules[bankName] > 0) { const cleanAccNo = accNo.replace(/-/g, ''); if (cleanAccNo.length !== bankRules[bankName]) { Swal.fire({ icon: 'error', title: 'เลขบัญชีไม่ถูกต้อง', text: `ธนาคาร ${bankName} ต้องมี ${bankRules[bankName]} หลัก`, confirmButtonColor: '#ef4444' }); return; } } Swal.fire({ title: 'บันทึกข้อมูล?', icon: 'question', showCancelButton: true, confirmButtonColor: '#6366f1', confirmButtonText: 'บันทึก' }).then((r) => { if (r.isConfirmed) document.getElementById('docForm').submit(); }); }
</script>
</body>

</html>