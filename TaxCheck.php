<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// เพิ่มเวลาประมวลผลให้ PHP (เพื่อให้สแกนละเอียดได้โดยไม่ Time out)
set_time_limit(300);
ini_set('memory_limit', '256M');

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// =========================================================
// 1. SETUP DATABASE
// =========================================================
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS company_name VARCHAR(150) AFTER id");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS doc_no VARCHAR(50) AFTER inv_date");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS inv_no VARCHAR(50) AFTER doc_no");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS vendor_name VARCHAR(255) AFTER inv_no");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS tax_id VARCHAR(20) AFTER vendor_name");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS branch VARCHAR(10) AFTER tax_id");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS amount_novat DECIMAL(15,2) DEFAULT 0 AFTER branch");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS amount_vatable DECIMAL(15,2) DEFAULT 0 AFTER amount_novat");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS vat_amount DECIMAL(15,2) DEFAULT 0 AFTER amount_vatable");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS total_amount DECIMAL(15,2) DEFAULT 0 AFTER vat_amount");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'NoFile'");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS remark MEDIUMTEXT");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS source_cols VARCHAR(100) AFTER remark");
$conn->query("ALTER TABLE tax_invoices ADD COLUMN IF NOT EXISTS scan_time VARCHAR(20) AFTER status");

// ดึงรายชื่อบริษัท
$companies_list = [];
$q_comp = $conn->query("SELECT * FROM companies ORDER BY id ASC");
if ($q_comp) {
    while ($c = $q_comp->fetch_assoc()) {
        $companies_list[] = $c['company_name'];
    }
}
$companies_json = json_encode($companies_list);

// =========================================================
// 🛠️ CONFIG: พจนานุกรมคำขยะ (Garbage Dictionary)
// =========================================================
$GARBAGE_WORDS = [
    // OCR อ่านผิด (Noise)
    'ULYG',
    'ONLUM',
    'ONLUMNUN',
    'IVONIPU',
    'IVON',
    'LUMNUN',
    'LUM',
    'UIG',
    'WIG',
    'ILG',
    // คำนำหน้าภาษาอังกฤษ
    'INVOICE',
    'TAX',
    'INV',
    'NO.',
    'NO',
    'DOC',
    'REF',
    'VOL',
    'BOOK',
    'BAHT',
    'AMOUNT',
    'DATE',
    'ID',
    'CODE',
    'ULYG',
    'ONLUM',
    'IVONIPU',
    'UIG',
    'UYG',
    'IV',
    'ulyg',
    // คำนำหน้าภาษาไทย
    'จำนวน',
    'ลำดับ',
    'เล่มที่',
    'เลขที่',
    'บาท',
    'ราคา',
    'มูลค่า',
    'ใบกำกับภาษี',
    'เอกสาร',
    'อ้างอิง',
    'วันที่',
    'สาขา'
];
// สร้าง Regex Pattern (Case Insensitive / Unicode)
$GARBAGE_REGEX = '/(' . implode('|', $GARBAGE_WORDS) . ')[\.\s:\-]*/iu';


// =========================================================
// FUNCTION: GLOBAL HELPER FUNCTIONS (FORENSIC MODE)
// =========================================================

if (!function_exists('utf8ize')) {
    function utf8ize($d)
    {
        if (is_array($d))
            foreach ($d as $k => $v)
                $d[$k] = utf8ize($v);
        else if (is_string($d))
            return mb_convert_encoding($d, 'UTF-8', 'UTF-8');
        return $d;
    }
}

// ฟังก์ชันแปลงร่างตัวอักษรให้เป็นตัวเลข (แก้คำผิด OCR ขั้นสูง)
if (!function_exists('morphToNum')) {
    function morphToNum($str)
    {
        $str = strtoupper(trim($str));
        // แปลงตัวอักษรที่หน้าตาเหมือนตัวเลข
        $map = [
            'O' => '0',
            'D' => '0',
            'Q' => '0',
            'U' => '0',
            'C' => '0',
            'L' => '1',
            'I' => '1',
            'T' => '1',
            'J' => '1',
            '|' => '1',
            'l' => '1',
            'i' => '1',
            'Z' => '2',
            'S' => '5',
            'B' => '8',
            '&' => '8',
            'G' => '6',
            'A' => '4'
        ];
        return strtr($str, $map);
    }
}

// ฟังก์ชันทำความสะอาดค่า (ล้างขยะ -> แปลงเลข -> เหลือเนื้อหา)
if (!function_exists('cleanAndFix')) {
    function cleanAndFix($str)
    {
        global $GARBAGE_REGEX;
        // 1. ตัดคำขยะทิ้งก่อน
        $str = preg_replace($GARBAGE_REGEX, '', $str);
        // 2. แปลงร่างตัวเลข
        $str = morphToNum($str);
        // 3. เหลือไว้เฉพาะ A-Z และ 0-9
        return preg_replace('/[^A-Z0-9]/', '', $str);
    }
}

// =========================================================
// ฟังก์ชันตรวจสอบข้อมูลภายใน (Deep Scan: Content Matcher)
// =========================================================

// =========================================================
// ฟังก์ชันตรวจสอบข้อมูลภายใน (Deep Scan: Transparent Mode)
// =========================================================

if (!function_exists('deepScan')) {
    function deepScan($label, $dbVal, $aiVal, $rawText, $type = 'text', $box = null) {
        // 1. ถ้า Excel เป็นค่าว่าง ถือว่าผ่านเสมอ
        if (empty($dbVal) || $dbVal == '0' || $dbVal == '0.00') {
            return ['label' => $label, 'db' => $dbVal, 'ai' => '-', 'ok' => true, 'box' => null];
        }

        $status = false;
        $aiDisplay = $aiVal; // ค่าเริ่มต้นคือสิ่งที่ AI ส่งมา
        $dbValStr = trim((string) $dbVal);
        
        // เตรียมข้อมูลดิบสำหรับค้นหา (เผื่อ AI อ่านผิดช่อง)
        $rawClean = strtolower(preg_replace('/\s+/', '', $rawText));
        $rawNumOnly = preg_replace('/[^0-9]/', '', $rawText);

        // -----------------------------------------------------
        // 💰 TYPE 1: ตัวเลข (Number)
        // -----------------------------------------------------
        if ($type == 'num') {
            $dbFloat = floatval($dbVal);
            $cleanAi = preg_replace('/[^0-9\.\-]/', '', morphToNum($aiVal)); // แปลง O->0, l->1
            $aiFloat = floatval($cleanAi);

            // 1.1 เทียบค่าจากช่องที่ AI ส่งมา
            if ($cleanAi !== '' && abs($dbFloat - $aiFloat) < 1.0) {
                $status = true;
                $aiDisplay = number_format($aiFloat, 2); // โชว์ตัวเลขที่ AI อ่านได้
            } 
            // 1.2 ถ้าไม่ตรง ให้ลองกวาดหาใน Text ทั้งหมด (เผื่อ AI วางผิดช่อง)
            else {
                $formats = [
                    number_format($dbFloat, 2, '.', ','),
                    number_format($dbFloat, 2, '.', ''),
                    number_format($dbFloat, 0, '', '')
                ];
                foreach ($formats as $fmt) {
                    if (strpos($rawClean, str_replace(',', '', $fmt)) !== false) {
                        $status = true;
                        $aiDisplay = "เจอในกระดาษ: $fmt"; // แจ้งว่าเจอตัวเลขนี้ซ่อนอยู่
                        break;
                    }
                }
                
                // ถ้าหาไม่เจอจริงๆ ให้โชว์ค่าผิดที่ AI อ่านได้
                if (!$status) {
                    $aiDisplay = ($aiFloat == 0) ? "อ่านได้: 0.00" : "อ่านได้: " . number_format($aiFloat, 2);
                }
            }
            $dbDisplay = number_format($dbFloat, 2);

        // -----------------------------------------------------
        // 🆔 TYPE 2: รหัส/เลขที่ (Exact String)
        // -----------------------------------------------------
        } elseif ($type == 'exact') {
            $dbClean = preg_replace('/[^a-z0-9]/i', '', $dbValStr);
            $dbNumOnly = preg_replace('/[^0-9]/', '', $dbValStr);
            
            global $GARBAGE_REGEX;
            $aiCleanRaw = preg_replace($GARBAGE_REGEX, '', strtoupper($aiVal));
            $aiClean = preg_replace('/[^A-Z0-9]/', '', $aiCleanRaw);

            if (strlen($dbClean) < 2) {
                $status = true; $aiDisplay = "-";
            } 
            // 2.1 เทียบตรงๆ
            elseif (strpos($aiClean, $dbClean) !== false) {
                $status = true;
                $aiDisplay = $aiVal; // โชว์ค่าที่อ่านได้
            } 
            // 2.2 เทียบแบบแก้คำผิด (Morph)
            elseif (strpos(morphToNum($aiClean), morphToNum($dbClean)) !== false) {
                $status = true;
                $aiDisplay = "$aiVal (แก้คำผิด)";
            } 
            // 2.3 หาเฉพาะตัวเลข
            elseif (strlen($dbNumOnly) >= 4 && strpos($rawNumOnly, $dbNumOnly) !== false) {
                $status = true;
                $aiDisplay = "เจอเลข: $dbNumOnly";
            } else {
                $status = false;
                $aiDisplay = empty($aiVal) ? "ไม่พบข้อมูล" : "อ่านได้: $aiVal";
            }
            $dbDisplay = $dbValStr;

        // -----------------------------------------------------
        // 📅 TYPE 3: วันที่ (Date)
        // -----------------------------------------------------
        } elseif ($label == 'วันที่') {
            $ts = strtotime(str_replace('/', '-', $dbValStr));
            if (!$ts) {
                $status = true; $dbDisplay = $dbValStr;
            } else {
                $d = date('d', $ts); $m = date('m', $ts); $y = date('Y', $ts); $yTh = $y + 543;
                $d_int = intval($d); $m_int = intval($m);
                // รูปแบบวันที่ที่เป็นไปได้
                $patterns = ["$d/$m/$y", "$d-$m-$y", "$d.$m.$y", "$d/$m/$yTh", "$d-$m-$yTh", "$d.$m.$yTh", "$d_int/$m_int/$y", "$d_int/$m_int/$yTh"];
                
                $foundDate = false;
                foreach ($patterns as $pat) {
                    if (strpos($rawText, $pat) !== false) { // หาใน Text ดิบ
                        $foundDate = true; 
                        $aiDisplay = "เจอ: $pat"; // โชว์รูปแบบวันที่เจอ
                        break; 
                    }
                }
                
                if ($foundDate) {
                    $status = true;
                } else {
                    $status = false;
                    $aiDisplay = "ไม่พบวันที่";
                }
                $dbDisplay = date('d/m/Y', $ts);
            }

        // -----------------------------------------------------
        // 🏢 TYPE 4: ชื่อบริษัท/สาขา (Fuzzy Search)
        // -----------------------------------------------------
        } else { 
            // 1. เตรียมคำหลักจาก Excel (ตัดคำนำหน้าทิ้ง)
            $cleanDb = str_replace(['บริษัท', 'หจก.', 'สาขา', 'จำกัด', '(มหาชน)', 'สำนักงานใหญ่', '(',')'], ' ', $dbValStr);
            
            // 2. ระเบิดคำ (Split) ด้วยช่องว่าง
            $keywords = explode(' ', $cleanDb);
            
            $foundKeyword = false;
            $matchedWord = '';

            // 3. วนลูปหาทีละคำ
            foreach ($keywords as $word) {
                $word = trim($word);
                // ข้ามคำสั้นๆ หรือตัวเลขล้วน (ป้องกันมั่ว)
                if (mb_strlen($word) < 3 || is_numeric($word)) continue;
                
                // แปลงเป็นตัวเล็กแล้วค้นหา
                $wordLower = strtolower($word);
                if (strpos($rawClean, $wordLower) !== false) {
                    $foundKeyword = true;
                    $matchedWord = $word;
                    break; // เจอคำไหนคำหนึ่งก็พอแล้ว (เช่น เจอคำว่า "ไทวัสดุ" หรือ "เลย")
                }
            }

            if ($foundKeyword) {
                $status = true;
                $aiDisplay = "✔ เจอคำว่า: $matchedWord"; 
            } else {
                $status = false;
                // ถ้าไม่เจอเลย ให้โชว์ Text บางส่วนที่อ่านได้ (จะได้รู้ว่าอ่านผิดเป็นอะไร)
                $preview = mb_substr($rawText, 0, 30);
                $aiDisplay = "อ่านได้: $preview..."; 
            }
            $dbDisplay = $dbValStr;
        }

        return ['label' => $label, 'db' => $dbDisplay, 'ai' => $aiDisplay, 'ok' => $status, 'box' => $box];
    }
}

// ฟังก์ชันรวม Logic การตรวจสอบ
if (!function_exists('processVerification')) {
    function processVerification($row_db, $extracted, $raw_text) {
        usleep(100000);
        $remark = [];

        // ข้อมูล Debug
        $debug_vendor = $extracted['debug_vendor'] ?? '';
        $debug_vendor_clean = mb_substr(preg_replace('/[\r\n\t]+/', ' ', $debug_vendor), 0, 60); // ตัดให้สั้นลง
        $debug_numbers = isset($extracted['debug_numbers']) ? implode(', ', $extracted['debug_numbers']) : '-';

        // 1. ตรวจสอบแต่ละช่อง
        $remark[] = deepScan('วันที่', $row_db['inv_date'], '', $raw_text, 'text');
        $remark[] = deepScan('เลขที่ใบกำกับภาษี', $row_db['inv_no'], $extracted['inv_no'] ?? '', $raw_text, 'exact', $extracted['inv_no_box'] ?? null);
        $remark[] = deepScan('เลขที่เอกสาร', $row_db['doc_no'], $extracted['doc_no'] ?? '', $raw_text, 'exact');
        
        // --- ตรวจผู้ขาย (Custom Logic) ---
        $vendor_found = $extracted['vendor_found'] ?? false;
        if ($vendor_found) {
            $remark[] = ['label' => 'ผู้ขาย', 'db' => $row_db['vendor_name'], 'ai' => '✔ เจอชื่อในเอกสาร', 'ok' => true];
        } else {
            // 🔥 ตรงนี้สำคัญ: ถ้าไม่เจอ ให้โชว์ว่า AI อ่านหัวกระดาษได้ว่าอะไร
            $remark[] = ['label' => 'ผู้ขาย', 'db' => $row_db['vendor_name'], 'ai' => "อ่านได้: $debug_vendor_clean...", 'ok' => false];
        }

        $remark[] = deepScan('เลขผู้เสียภาษี', $row_db['tax_id'], $extracted['tax_id'] ?? '', $raw_text, 'exact', $extracted['tax_id_box'] ?? null);
        $remark[] = deepScan('สาขา', $row_db['branch'], $extracted['branch'] ?? '', $raw_text, 'exact');
        $remark[] = deepScan('ยอดไม่คิดภาษี', $row_db['amount_novat'], $extracted['novat'] ?? 0, $raw_text, 'num');
        $remark[] = deepScan('ยอดก่อนภาษี', $row_db['amount_vatable'], $extracted['vatable'] ?? 0, $raw_text, 'num');
        
        // --- ตรวจ VAT ---
        $vat_val = $extracted['vat'] ?? 0;
        if ($vat_val > 0) {
             $remark[] = deepScan('ภาษี (VAT)', $row_db['vat_amount'], $vat_val, $raw_text, 'num', $extracted['vat_box'] ?? null);
        } else {
             // ถ้าไม่เจอ ให้โชว์ตัวเลขอื่นๆ ที่เจอในหน้า
             $remark[] = ['label' => 'ภาษี (VAT)', 'db' => number_format($row_db['vat_amount'],2), 'ai' => "ไม่พบ (เลขที่เจอ: $debug_numbers)", 'ok' => false];
        }

        // --- ตรวจ Total ---
        $remark[] = deepScan('ยอดรวมสุทธิ', $row_db['total_amount'], $extracted['total'] ?? 0, $raw_text, 'num', $extracted['total_box'] ?? null);

        // สรุปสถานะ (Logic เดิม)
        $hasTotal = end($remark)['ok'];
        $keyInfo = ($remark[1]['ok'] || $remark[3]['ok']); 

        if ($hasTotal && $keyInfo) { 
            $status = 'Verified';
        } else {
            $status = 'Mismatch';
        }

        return ['status' => $status, 'remark' => $remark];
    }
}

// =========================================================
// 2. LOGIC: Import Excel
// =========================================================
if (isset($_POST['import_data_json'])) {
    try {
        $rows = json_decode($_POST['import_data_json'], true);
        $selected_company = $_POST['import_company_select'];

        if (empty($rows))
            throw new Exception("ไม่พบข้อมูลรายการที่ส่งมา");

        $stmt = $conn->prepare("INSERT INTO tax_invoices (company_name, inv_date, doc_no, inv_no, vendor_name, tax_id, branch, amount_novat, amount_vatable, vat_amount, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'NoFile')");

        $count = 0;
        foreach ($rows as $data) {
            if (empty($data['date']))
                continue;

            $doc_no = $data['doc_no'] ?? '';
            $inv_no = $data['inv_no'] ?? '';
            $vendor = $data['vendor'] ?? '';
            $tax_id = $data['tax_id'] ?? '';
            $branch = $data['branch'] ?? '';
            $novat = floatval($data['novat'] ?? 0);
            $vatable = floatval($data['vatable'] ?? 0);
            $vat = floatval($data['vat'] ?? 0);
            $total = floatval($data['total'] ?? 0);

            $stmt->bind_param("sssssssdddd", $selected_company, $data['date'], $doc_no, $inv_no, $vendor, $tax_id, $branch, $novat, $vatable, $vat, $total);
            if ($stmt->execute())
                $count++;
        }
        echo "<script>alert('✅ บันทึกสำเร็จทั้งหมด $count รายการ'); window.location.href='TaxCheck.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='TaxCheck.php';</script>";
    }
    exit();
}

// =========================================================
// 3. LOGIC: AI Upload & Verify
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- PART 1: Blind Scan (AI อ่านก่อนส่งกลับ JS) ---
    if (isset($_FILES['blind_file'])) {
        error_reporting(0);
        header('Content-Type: application/json');

        $target_dir = __DIR__ . "/uploads/tax_invoices/";
        if (!file_exists($target_dir))
            @mkdir($target_dir, 0777, true);

        $ext = pathinfo($_FILES['blind_file']['name'], PATHINFO_EXTENSION);
        $new_name = "bulk_" . date('Ymd_His') . "_" . rand(1000, 9999) . "." . $ext;
        $full_path = $target_dir . $new_name;

        if (move_uploaded_file($_FILES['blind_file']['tmp_name'], $full_path)) {
            $api_url = "http://127.0.0.1:5000/process_invoice";
            $cfile = new CURLFile($full_path, mime_content_type($full_path), $new_name);
            $data = ['ajax_file' => $cfile];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $ai_result = json_decode($response, true);
            $extracted_data = $ai_result['extracted'] ?? [];
            $extracted_data['text_preview'] = $ai_result['extracted']['text_preview'] ?? ''; // <--- บรรทัดสำคัญ!

            echo json_encode([
                'status' => 'success',
                'file_name' => $new_name,
                'extracted' => $extracted_data
            ]);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit();
    }

    // --- PART 2: Link File (AI เจอคู่ -> ส่งมา Verify ละเอียดและบันทึก) ---
    // --- PART 2: Link File (จับคู่ได้ -> สั่งสแกนละเอียดซ้ำ + บันทึกเวลา!) ---
    if (isset($_POST['link_file_id'])) {
        error_reporting(0);
        header('Content-Type: application/json');

        $id = intval($_POST['link_file_id']);
        $file_name = $_POST['file_name'];
        
        // 1. ดึงข้อมูล Hint จาก DB
        $stmt_db = $conn->prepare("SELECT * FROM tax_invoices WHERE id = ?");
        $stmt_db->bind_param("i", $id);
        $stmt_db->execute();
        $row_db = $stmt_db->get_result()->fetch_assoc();

        // 2. เตรียมไฟล์ส่งให้ Python สแกนรอบ 2 (Deep Scan)
        $full_path = __DIR__ . "/uploads/tax_invoices/" . $file_name;
        
        if (file_exists($full_path)) {
            $api_url = "http://127.0.0.1:5000/process_invoice";
            $cfile = new CURLFile($full_path, mime_content_type($full_path), $file_name);
            
            // ส่ง Hint ไปด้วย เพื่อให้ Python เข้าโหมดละเอียด
            $data = [
                'ajax_file' => $cfile,
                'hint_inv' => $row_db['inv_no'] ?? '',
                'hint_total' => $row_db['total_amount'] ?? 0,
                'hint_vat' => $row_db['vat_amount'] ?? 0,
                'hint_vendor' => $row_db['vendor_name'] ?? ''
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            // 3. รับผลลัพธ์ใหม่ (รวมถึงเวลาที่ใช้)
            $ai_result = ($response === false) ? ['extracted' => []] : json_decode($response, true);
            $extracted = $ai_result['extracted'] ?? [];
            $raw_text = strtolower($extracted['text_preview'] ?? '');
            
            // 🔥 รับค่าเวลาจาก Python (นี่คือจุดที่ทำให้เวลาขึ้น!)
            $exec_time = $ai_result['execution_time'] ?? '-'; 

            // 4. ตรวจสอบความถูกต้อง
            $result = processVerification($row_db, $extracted, $raw_text);
            $status = $result['status'];
            $remark_json = json_encode(utf8ize($result['remark']), JSON_UNESCAPED_UNICODE);

            // 5. บันทึกลง DB (เพิ่ม scan_time)
            $stmt = $conn->prepare("UPDATE tax_invoices SET file_path = ?, status = ?, remark = ?, scan_time = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $file_name, $status, $remark_json, $exec_time, $id);
            $stmt->execute();

            echo json_encode([
                'status' => 'success',
                'row_status' => $status,
                'remark' => $remark_json,
                'scan_time' => $exec_time // ส่งกลับไปโชว์ที่ตาราง
            ]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'File not found']);
        }
        exit();
    }

    // --- PART 3: Single Upload (อัปโหลดเดี่ยว) ---
    if (isset($_FILES['ajax_file'])) {
        error_reporting(0);
        header('Content-Type: application/json');

        $id = intval($_POST['upload_id']);
        $target_dir = __DIR__ . "/uploads/tax_invoices/";
        if (!file_exists($target_dir))
            @mkdir($target_dir, 0777, true);

        $ext = pathinfo($_FILES['ajax_file']['name'], PATHINFO_EXTENSION);
        $new_name = "tax_" . date('Ymd_His') . "_" . rand(1000, 9999) . "." . $ext;
        $full_path = $target_dir . $new_name;

        if (move_uploaded_file($_FILES['ajax_file']['tmp_name'], $full_path)) {

            // ✅ 1. ดึงข้อมูลจาก DB ก่อน (ย้ายขึ้นมาตรงนี้) 
            // เพื่อนำค่า inv_no และ total_amount ไปส่งเป็น Hint ให้ AI
            $stmt_db = $conn->prepare("SELECT * FROM tax_invoices WHERE id = ?");
            $stmt_db->bind_param("i", $id);
            $stmt_db->execute();
            $row_db = $stmt_db->get_result()->fetch_assoc();

            // ✅ 2. เตรียมส่ง Python (แนบ Hint ไปด้วย)
            $api_url = "http://127.0.0.1:5000/process_invoice";
            $cfile = new CURLFile($full_path, mime_content_type($full_path), $new_name);
            
            $data = [
                'ajax_file' => $cfile,
                'hint_inv' => $row_db['inv_no'] ?? '',
                'hint_total' => $row_db['total_amount'] ?? 0,
                
                // 🔥 ต้องมี 2 บรรทัดนี้
                'hint_vat' => $row_db['vat_amount'] ?? 0,      // ส่งยอดภาษีไปให้หา
                'hint_vendor' => $row_db['vendor_name'] ?? ''  // ส่งชื่อผู้ขายไปให้หา
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $ai_result = ($response === false) ? ['extracted' => []] : json_decode($response, true);
            $extracted = $ai_result['extracted'] ?? [];
            $raw_text = strtolower($extracted['text_preview'] ?? '');
            $exec_time = $ai_result['execution_time'] ?? '-';

            // ✅ 3. ตรวจสอบ (Verification)
            // ใช้ row_db ที่ดึงมาแล้วด้านบน ไม่ต้องดึงซ้ำ
            $result = processVerification($row_db, $extracted, $raw_text);

            $status = $result['status'];
            $remark_json = json_encode(utf8ize($result['remark']), JSON_UNESCAPED_UNICODE);

            // ✅ 4. อัปเดตผลลัพธ์ลง DB
            $stmt_up = $conn->prepare("UPDATE tax_invoices SET file_path = ?, status = ?, remark = ?, scan_time = ? WHERE id = ?");
            $stmt_up->bind_param("ssssi", $new_name, $status, $remark_json, $exec_time, $id);

            if ($stmt_up->execute()) {
                echo json_encode([
                    'status' => 'success', 
                    'file' => $new_name, 
                    'row_status' => $status, 
                    'remark' => $remark_json,
                    'scan_time' => $exec_time // 🔥 [เพิ่มบรรทัดนี้]
                ]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Database update failed']);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Move file failed']);
        }
        exit();
    }

    if (isset($_POST['save_verify_result'])) {
        // ... (ไม่น่าจะได้ใช้แล้ว แต่เก็บไว้กันเหนียว)
        echo "Saved";
        exit();
    }

    if (isset($_POST['bulk_delete_ids'])) {
        $ids = json_decode($_POST['bulk_delete_ids'], true);
        if (!empty($ids)) {
            $id_list = implode(',', array_map('intval', $ids));
            $res = $conn->query("SELECT file_path FROM tax_invoices WHERE id IN ($id_list)");
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['file_path']))
                    @unlink(__DIR__ . "/uploads/tax_invoices/" . $row['file_path']);
            }
            $conn->query("DELETE FROM tax_invoices WHERE id IN ($id_list)");
            echo "Deleted";
        }
        exit();
    }
}

// =========================================================
// 4. DISPLAY DATA
// =========================================================
$filter_month = isset($_GET['m']) ? $_GET['m'] : '';
$filter_comp = isset($_GET['c']) ? $_GET['c'] : '';

$sql = "SELECT * FROM tax_invoices WHERE 1=1";
if ($filter_month)
    $sql .= " AND DATE_FORMAT(inv_date, '%Y-%m') = '$filter_month'";
if ($filter_comp)
    $sql .= " AND company_name = '$filter_comp'";
$sql .= " ORDER BY inv_date DESC, id DESC LIMIT 500";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title>ตรวจสอบใบกำกับภาษี (Smart Match)</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&family=Prompt:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f8fafc;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            overflow-x: auto;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
            white-space: nowrap;
        }

        th {
            background: #f1f5f9;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .st-nofile {
            background: #e2e8f0;
            color: #64748b;
        }

        .st-pending {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fdba74;
        }

        .st-verified {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .st-mismatch {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: #fff;
            font-family: 'Prompt';
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .btn-primary {
            background: #4f46e5;
        }

        .btn-danger {
            background: #ef4444;
        }

        .btn-import {
            background: #10b981;
        }

        .btn-upload {
            cursor: pointer;
            background: #3b82f6;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            transition: 0.2s;
            border: none;
            display: inline-block;
        }

        .btn-upload:hover {
            background: #2563eb;
        }

        .btn-bulk {
            background: #f59e0b;
            color: white;
            margin-left: 5px;
        }

        .btn-bulk:hover {
            background: #d97706;
        }

        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        .num {
            text-align: right;
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            align-items: center;
            border: 1px solid #e2e8f0;
        }

        input[type="month"],
        select {
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
        }

        /* Modal & Zoom */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: #f8fafc;
            margin: 2% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 1200px;
            height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            background: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .view-left {
            flex: 1;
            background: #2d3748;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 10px;
        }

        .view-left img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border: 1px solid #4a5568;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .view-right {
            flex: 0 0 400px;
            background: #fff;
            border-left: 1px solid #e2e8f0;
            padding: 20px;
            overflow-y: auto;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 10px;
        }

        .detail-table th {
            background: #f1f5f9;
            padding: 8px;
            text-align: left;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }

        .detail-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .diff-val {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }

        .val-correct {
            color: #15803d;
        }

        .val-error {
            color: #dc2626;
            background: #fef2f2;
            padding: 2px 5px;
            border-radius: 4px;
        }

        .reason-text {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .status-icon {
            font-size: 16px;
        }

        .img-zoom-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            background: #1e1e1e;
        }

        .img-zoom-lens {
            position: absolute;
            border: 2px solid #d4d4d4;
            border-radius: 50%;
            width: 150px;
            height: 150px;
            background-repeat: no-repeat;
            cursor: none;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            display: none;
            pointer-events: none;
            z-index: 1000;
        }

        .img-tools {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 100;
            display: flex;
            gap: 5px;
        }

        .tool-btn {
            background: rgba(255, 255, 255, 0.8);
            border: none;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .tool-btn:hover {
            background: #fff;
            transform: scale(1.1);
        }

        .tool-btn.active {
            background: #4f46e5;
            color: white;
        }


        /* เมื่อเอาเมาส์ชี้ที่ตาราง ให้กรอบเด่นขึ้น */
        .ai-marker.active {
            border-color: #22c55e;
            /* เปลี่ยนเป็นสีเขียว */
            background-color: rgba(34, 197, 94, 0.3);
            box-shadow: 0 0 10px #22c55e;
            z-index: 910;
            transform: scale(1.05);
        }

        /* ป้ายบอกคอลัมน์ในหน้าผลการตรวจสอบ */
        .col-tag {
            display: inline-block;
            font-size: 11px;
            background-color: #f1f5f9;
            /* สีเทาอ่อน */
            color: #64748b;
            /* ตัวหนังสือสีเทาเข้ม */
            padding: 1px 6px;
            border-radius: 4px;
            margin-left: 8px;
            border: 1px solid #cbd5e1;
            font-weight: normal;
        }

        #imageContainer {
            position: relative;
            display: inline-block;
            /* ให้ขนาดเท่ารูปภาพ */
            cursor: zoom-in;
            /* เมาส์เป็นรูปแว่นขยาย */
        }

        #modalImg {
            cursor: zoom-in;
            transition: transform 0.2s;
        }

        /* Lightbox สำหรับขยายรูปเต็มจอ */
        #zoomOverlay {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
            text-align: center;
        }

        #zoomOverlay img {
            margin: auto;
            display: block;
            max-width: 95%;
            height: auto;
            margin-top: 50px;
            border: 2px solid white;
            box-shadow: 0 0 20px black;
        }

        #closeZoomBtn {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 10001;
        }

        .ai-marker {
            position: absolute;
            border: 3px solid #ef4444;
            /* สีแดง */
            background-color: rgba(239, 68, 68, 0.1);
            /* สีแดงจางๆ ด้านใน */
            z-index: 10;
            pointer-events: none;
            /* ให้คลิกทะลุได้ */
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
        }

        /* กรอบสีเขียว (ถ้าถูกต้อง) */
        .ai-marker.correct {
            border-color: #22c55e;
            background-color: rgba(34, 197, 94, 0.1);
        }

        /* --- สไตล์สำหรับหน้าต่างขยายรูป (Lightbox) --- */
        #zoomModal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
            text-align: center;
        }

        #zoomModal img {
            margin: auto;
            display: block;
            max-width: 95%;
            max-height: 95vh;
            margin-top: 2.5vh;
            border: 2px solid white;
            box-shadow: 0 0 20px black;
        }

        #closeZoom {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        .badge-time {
            background: #e2e8f0;
            color: #475569;
            font-family: monospace;
            font-size: 11px;
            padding: 3px 6px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="card" style="margin: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 style="font-family:'Prompt'; color:#1e293b; margin:0;"><i class="fas fa-file-invoice"></i>
                ตรวจสอบใบกำกับภาษี</h1>
            <div style="display:flex; gap:10px;">
                <button onclick="bulkDelete()" class="btn btn-danger"><i class="fas fa-trash"></i> ลบ</button>
                <button onclick="preImportCheck()" class="btn btn-import"><i class="fas fa-file-excel"></i> Import
                    Excel</button>
                <button onclick="document.getElementById('bulkInput').click()" class="btn btn-bulk"><i
                        class="fas fa-robot"></i> สแกนจับคู่ไฟล์ (AI)</button>
            </div>
        </div>

        <div class="filter-bar">
            <span>ตัวกรอง:</span>
            <input type="month" id="monthFilter" value="<?php echo $filter_month; ?>" onchange="applyFilter()">
            <select id="companyFilter" onchange="applyFilter()">
                <option value="">-- ทุกบริษัท --</option>
                <?php foreach ($companies_list as $c)
                    echo "<option value='$c'" . ($filter_comp == $c ? ' selected' : '') . ">$c</option>"; ?>
            </select>
            <button onclick="window.location.href='?m=&c='" class="btn" style="background:#94a3b8;">Reset</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="30"><input type="checkbox" onchange="toggleSelectAll(this)"></th>
                    <th width="40">#</th>
                    <th width="90">วันที่</th>
                    <th>เลขที่เอกสาร</th>
                    <th>เลขที่ใบกำกับภาษี</th>
                    <th>ผู้ขาย</th>
                    <th>เลขผู้เสียภาษี</th>
                    <th class="num">สาขา</th>
                    <th class="num">ไม่คิดภาษี</th>
                    <th class="num">คิดภาษี</th>
                    <th class="num" style="color:red">ภาษี</th>
                    <th class="num" style="color:blue">รวมทั้งสิ้น</th>
                    <th>เวลา</th> <th>อัปโหลด</th>
                    <th style="text-align:center">สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php $i = 1;
                    while ($row = $result->fetch_assoc()): ?>
                        <?php
                        $status_html = '<span class="badge st-nofile">รอไฟล์</span>';
                        if ($row['status'] == 'Pending')
                            $status_html = '<span class="badge st-pending"><i class="fas fa-circle-notch fa-spin"></i> ตรวจสอบ</span>';
                        elseif ($row['status'] == 'Verified')
                            $status_html = '<span class="badge st-verified">✅ ผ่าน</span>';
                        elseif ($row['status'] == 'Mismatch')
                            $status_html = '<span class="badge st-mismatch">❌ ไม่ตรง</span>';

                        $ts = strtotime($row['inv_date']);
                        $d_date_th = date('d/m/', $ts) . (date('Y', $ts) + 543);
                        $d_date_en = date('d/m/Y', $ts);
                        $scan_time_display = !empty($row['scan_time']) ? '<span class="badge-time">'.$row['scan_time'].'</span>' : '-';
                        ?>
                        <tr id="row-<?php echo $row['id']; ?>" data-doc="<?php echo htmlspecialchars($row['doc_no']); ?>"
                            data-inv="<?php echo htmlspecialchars($row['inv_no']); ?>"
                            data-vendor="<?php echo htmlspecialchars($row['vendor_name']); ?>"
                            data-tax="<?php echo htmlspecialchars($row['tax_id']); ?>"
                            data-branch="<?php echo htmlspecialchars($row['branch']); ?>"
                            data-date-th="<?php echo $d_date_th; ?>" data-date-en="<?php echo $d_date_en; ?>"
                            data-novat="<?php echo $row['amount_novat']; ?>"
                            data-vatable="<?php echo $row['amount_vatable']; ?>" data-vat="<?php echo $row['vat_amount']; ?>"
                            data-total="<?php echo $row['total_amount']; ?>">

                            <td><input type="checkbox" class="row-cb" value="<?php echo $row['id']; ?>"></td>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo date('d/m/Y', $ts); ?></td>
                            <td><?php echo $row['doc_no']; ?></td>
                            <td><?php echo $row['inv_no']; ?></td>
                            <td><?php echo $row['vendor_name']; ?></td>
                            <td><?php echo $row['tax_id']; ?></td>
                            <td class="num"><?php echo $row['branch']; ?></td>
                            <td class="num"><?php echo number_format($row['amount_novat'], 2); ?></td>
                            <td class="num"><?php echo number_format($row['amount_vatable'], 2); ?></td>
                            <td class="num" style="color:red"><?php echo number_format($row['vat_amount'], 2); ?></td>
                            <td class="num" style="font-weight:bold; color:blue">
                                <?php echo number_format($row['total_amount'], 2); ?>
                            </td>
                            <td align="center" id="time-<?php echo $row['id']; ?>">
                                <?php echo $scan_time_display; ?>
                            </td>
                            <td align="center" style="white-space: nowrap;">
                                <label class="btn-upload" title="อัปโหลดไฟล์ใหม่">
                                    <i class="fas fa-upload"></i>
                                    <input type="file" style="display:none" accept="image/*"
                                        onchange="uploadAndScan(this, <?php echo $row['id']; ?>)">
                                </label>
                                <a id="btn-view-<?php echo $row['id']; ?>" href="javascript:void(0)"
                                    onclick="showDetailModal(<?php echo $row['id']; ?>)" class="btn-upload"
                                    style="background: #64748b; margin-left: 5px; display: <?php echo !empty($row['file_path']) ? 'inline-block' : 'none'; ?>;">
                                    <i class="fas fa-search"></i>
                                </a>
                                <input type="hidden" id="file-path-<?php echo $row['id']; ?>"
                                    value="<?php echo $row['file_path']; ?>">
                                <input type="hidden" id="ai-remark-<?php echo $row['id']; ?>"
                                    value='<?php echo htmlspecialchars($row['remark'] ?? '', ENT_QUOTES); ?>'>
                            </td>
                            <td align="center">
                                <div id="status-<?php echo $row['id']; ?>"><?php echo $status_html; ?></div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="14" align="center" style="padding:20px;">ไม่พบข้อมูล</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <input type="file" id="excelInput" accept=".xlsx,.csv" style="display:none;" onchange="handleExcelUpload(this)">
    <input type="file" id="bulkInput" multiple accept="image/*,.pdf" style="display:none;"
        onchange="handleBulkUpload(this)">

    <form id="jsonImportForm" method="POST" style="display:none;">
        <input type="hidden" name="import_data_json" id="importJsonData">
        <input type="hidden" name="import_company_select" id="importCompanySelect">
    </form>

    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-search-plus"></i> ผลการตรวจสอบ (Verification Result)</div>
                <button onclick="document.getElementById('detailModal').style.display='none'"
                    style="border:none; background:none; font-size:24px; cursor:pointer; color:#64748b;">&times;</button>
            </div>
            <div class="modal-body">
                <div class="view-left">
                    <div class="img-zoom-container" id="imgContainer" onmousemove="moveLens(event)"
                        onmouseleave="hideLens()">
                        <img id="modalImg" src="" onload="drawAiMarkers()" style="max-width:100%; max-height:100%;">

                        <div id="markerLayer"
                            style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;">
                        </div>

                        <div id="zoomLens" class="img-zoom-lens"></div>
                        <div id="noImgPlaceholder" style="display:none;color:#ccc">ไม่พบรูป</div>
                        <div class="img-tools">
                        </div>
                    </div>
                </div>
                <div class="view-right">
                    <h3 style="margin-top:0; border-bottom:2px solid #e2e8f0; padding-bottom:10px; color:#1e293b;">
                        รายการตรวจสอบ</h3>
                    <div id="modalCheckList"></div>
                </div>
            </div>
            <div
                style="margin-top:20px; padding:10px; background:#f1f5f9; border-radius:5px; font-size:12px; color:#64748b;">
                <i class="fas fa-info-circle"></i> <b>หมายเหตุ:</b><br>
                หากข้อมูลใน Excel ถูกต้องแต่ AI อ่านผิด (เช่น อ่านเลข 0 เป็นตัว O) ท่านสามารถกด "ผ่าน"
                ได้เลยโดยยึดข้อมูล Excel เป็นหลัก
            </div>
        </div>
    </div>
    <div id="zoomModal" onclick="this.style.display='none'">
        <span id="closeZoom">&times;</span>
        <img id="expandedImg">
    </div>

    <div id="zoomOverlay" onclick="this.style.display='none'">
        <span id="closeZoomBtn">&times;</span>
        <img id="zoomImageSrc">
    </div>

    <script>
        const companiesList = <?php echo $companies_json; ?>;
        let isBulkScanning = false;

        // --- FILTER & UTILS ---
        function applyFilter() {
            const m = document.getElementById('monthFilter').value;
            const c = document.getElementById('companyFilter').value;
            window.location.href = `?m=${m}&c=${encodeURIComponent(c)}`;
        }
        function toggleSelectAll(e) { document.querySelectorAll('.row-cb').forEach(cb => cb.checked = e.checked); }

        // --- BULK DELETE ---
        function bulkDelete() {
            const ids = Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => cb.value);
            if (ids.length === 0) return Swal.fire('เตือน', 'กรุณาเลือกรายการ', 'warning');
            Swal.fire({ title: 'ยืนยันลบ?', text: `ลบ ${ids.length} รายการ`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' }).then(r => {
                if (r.isConfirmed) {
                    let fd = new FormData(); fd.append('bulk_delete_ids', JSON.stringify(ids));
                    fetch('', { method: 'POST', body: fd }).then(res => res.text()).then(t => {
                        if (t.includes('Deleted')) location.reload();
                    });
                }
            });
        }

        window.stopBulkScan = function () { isBulkScanning = false; };

        // --- IMPORT EXCEL ---
        function preImportCheck() {
            let opts = companiesList.map(c => `<option value="${c}">${c}</option>`).join('');
            Swal.fire({
                title: 'เลือกบริษัทก่อนนำเข้า',
                html: `<select id="swalComp" class="swal2-select" style="width:80%"><option value="">-- เลือก --</option>${opts}</select>`,
                showCancelButton: true,
                preConfirm: () => {
                    const v = document.getElementById('swalComp').value;
                    if (!v) Swal.showValidationMessage('กรุณาเลือกบริษัท');
                    return v;
                }
            }).then(r => {
                if (r.isConfirmed) {
                    document.getElementById('importCompanySelect').value = r.value;
                    document.getElementById('excelInput').click();
                }
            });
        }

        function handleExcelUpload(input) {
            const file = input.files[0];
            if (!file) return;
            input.value = '';

            const reader = new FileReader();
            reader.onload = async function (e) {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array', cellDates: true });
                const sheetNames = workbook.SheetNames;
                let targetSheet = sheetNames[0];

                if (sheetNames.length > 1) {
                    let opts = {}; sheetNames.forEach(n => opts[n] = n);
                    const { value: sel } = await Swal.fire({
                        title: 'เลือก Sheet',
                        input: 'select', inputOptions: opts, showCancelButton: true
                    });
                    if (!sel) return;
                    targetSheet = sel;
                }

                const worksheet = workbook.Sheets[targetSheet];
                const json = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                let headerIdx = -1;
                let mapLog = {
                    date: { idx: -1, name: '-', ex: '-' }, inv: { idx: -1, name: '-', ex: '-' },
                    doc: { idx: -1, name: '-', ex: '-' }, vendor: { idx: -1, name: '-', ex: '-' },
                    tax: { idx: -1, name: '-', ex: '-' }, branch: { idx: -1, name: '-', ex: '-' },
                    novat: { idx: -1, name: '-', ex: '-' }, vatable: { idx: -1, name: '-', ex: '-' },
                    vat: { idx: -1, name: '-', ex: '-' }, total: { idx: -1, name: '-', ex: '-' }
                };
                const keys = {
                    date: ['วันที่', 'Date'], inv: ['ใบกำกับภาษี', 'Inv No', 'Invoice'], doc: ['เลขที่เอกสาร', 'Doc No'],
                    vendor: ['ผู้ขาย', 'Vendor', 'Supplier'], tax: ['เลขผู้เสียภาษี', 'Tax ID'], branch: ['สาขา', 'Branch'],
                    novat: ['ไม่คิดภาษี', 'Non-Vat'], vatable: ['มูลค่าสินค้า', 'Vatable'], vat: ['ภาษี', 'Vat'], total: ['รวมทั้งสิ้น', 'Total', 'Amount']
                };

                for (let i = 0; i < Math.min(50, json.length); i++) {
                    const row = json[i]; if (!row) continue;
                    const rowStr = row.join(' ');
                    if ((rowStr.includes('วันที่') || rowStr.includes('Date')) && (rowStr.includes('รวม') || rowStr.includes('Total'))) {
                        headerIdx = i;
                        row.forEach((cell, idx) => {
                            let txt = String(cell).trim();
                            for (let k in keys) {
                                if (keys[k].some(x => txt.includes(x))) {
                                    mapLog[k].idx = idx; mapLog[k].name = txt;
                                }
                            }
                        });
                        break;
                    }
                }

                if (headerIdx === -1) return Swal.fire('Error', 'ไม่พบหัวตารางมาตรฐาน', 'error');

                let scanResults = [];
                let sampleRow = null;
                for (let i = headerIdx + 1; i < json.length; i++) {
                    const row = json[i]; if (!row || row.length < 2) continue;
                    let dVal = row[mapLog.date.idx];
                    let totalVal = parseFloat(row[mapLog.total.idx] || 0);

                    if (dVal instanceof Date) dVal = dVal.toISOString().split('T')[0];
                    else if (typeof dVal === 'string') {
                        const p = dVal.trim().split(/[\/\-]/);
                        if (p.length === 3) {
                            let y = parseInt(p[2]); if (y > 2400) y -= 543;
                            dVal = `${y}-${p[1].padStart(2, '0')}-${p[0].padStart(2, '0')}`;
                        }
                    }

                    if (dVal) {
                        if (!sampleRow) {
                            sampleRow = row;
                            for (let k in mapLog) {
                                if (mapLog[k].idx !== -1) {
                                    let val = row[mapLog[k].idx];
                                    if (val instanceof Date) val = val.toLocaleDateString();
                                    mapLog[k].ex = val;
                                }
                            }
                        }
                        scanResults.push({
                            date: dVal,
                            doc_no: String(row[mapLog.doc.idx] || '').trim(),
                            inv_no: String(row[mapLog.inv.idx] || '').trim(),
                            vendor: String(row[mapLog.vendor.idx] || '').trim(),
                            tax_id: String(row[mapLog.tax.idx] || '').trim(),
                            branch: String(row[mapLog.branch.idx] || '').trim(),
                            novat: parseFloat(row[mapLog.novat.idx] || 0),
                            vatable: parseFloat(row[mapLog.vatable.idx] || 0),
                            vat: parseFloat(row[mapLog.vat.idx] || 0),
                            total: totalVal,
                            source_cols: createSourceColsStr(mapLog) // สร้าง string บอกคอลัมน์
                        });
                    }
                }

                // Show Confirmation
                let mappingHtml = `<table style="width:100%;font-size:12px;text-align:left">
                    <tr style="background:#eee"><td>Field</td><td>Col</td><td>Sample</td></tr>
                    ${createMapRow('วันที่', mapLog.date)}
                    ${createMapRow('ใบกำกับ', mapLog.inv)}
                    ${createMapRow('ยอดรวม', mapLog.total)}
                </table>`;

                Swal.fire({
                    title: `พบ ${scanResults.length} รายการ`,
                    html: mappingHtml,
                    width: '600px',
                    showCancelButton: true,
                    confirmButtonText: '✅ นำเข้า'
                }).then((r) => {
                    if (r.isConfirmed) {
                        document.getElementById('importJsonData').value = JSON.stringify(scanResults);
                        document.getElementById('jsonImportForm').submit();
                    }
                });
            };
            reader.readAsArrayBuffer(file);
        }

        function createMapRow(label, obj) {
            const found = obj.idx !== -1;
            return `<tr><td>${label}</td><td style="color:${found ? 'green' : 'red'}">${found ? XLSX.utils.encode_col(obj.idx) : '-'}</td><td>${obj.ex}</td></tr>`;
        }

        function createSourceColsStr(mapLog) {
            let parts = [];
            if (mapLog.date.idx > -1) parts.push(`date:${XLSX.utils.encode_col(mapLog.date.idx)}`);
            if (mapLog.inv.idx > -1) parts.push(`inv:${XLSX.utils.encode_col(mapLog.inv.idx)}`);
            if (mapLog.total.idx > -1) parts.push(`total:${XLSX.utils.encode_col(mapLog.total.idx)}`);
            return parts.join(',');
        }

        // --- BULK SCAN & SINGLE UPLOAD ---
        async function handleBulkUpload(input) {
            // หมายเหตุ: แต่ต้องแน่ใจว่าใช้โค้ด handleBulkUpload ที่มีการส่ง text_preview กลับไป deepScan นะครับ
            const files = Array.from(input.files);
            if (files.length === 0) return;

            // 1. เตรียมข้อมูล DB
            const allRows = Array.from(document.querySelectorAll('tr[data-inv]')).map(tr => ({
                id: tr.id.replace('row-', ''),
                el: tr,
                invRaw: (tr.getAttribute('data-inv') || '').toLowerCase().replace(/[^a-z0-9]/g, ''),
                docRaw: (tr.getAttribute('data-doc') || '').toLowerCase().replace(/[^a-z0-9]/g, ''),
                total: parseFloat(tr.getAttribute('data-total')),
                tax: (tr.getAttribute('data-tax') || '').replace(/\D/g, '')
            }));

            let usedRowIds = new Set();
            let successCount = 0; let failCount = 0;
            let scanLogs = [];
            isBulkScanning = true;

            Swal.fire({
                title: 'กำลังสแกนละเอียด (Deep Scan)...',
                html: '<div id="scan-log" style="height:200px;overflow-y:auto;text-align:left;background:#f1f5f9;padding:10px;"></div>',
                showConfirmButton: false
            });
            const addLog = (m, c = 'black') => {
                scanLogs.push(`<div style="color:${c}">${m}</div>`);
                const d = document.getElementById('scan-log');
                if (d) { d.innerHTML = scanLogs.join(''); d.scrollTop = d.scrollHeight; }
            };

            for (let i = 0; i < files.length; i++) {
                if (!isBulkScanning) break;
                const file = files[i];
                try {
                    const fd = new FormData(); fd.append('blind_file', file);
                    const res = await fetch('', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.status === 'success') {
                        const ai = data.extracted;
                        const aiTotal = parseFloat(ai.total || 0);
                        const aiTax = (ai.tax_id || '').replace(/\D/g, '');

                        let keys = [ai.inv_no, ai.doc_no].map(k => (k || '').toLowerCase().replace(/[^a-z0-9]/g, ''));
                        let match = null;

                        for (let k of keys) {
                            if (k.length < 3) continue;
                            match = allRows.find(r => !usedRowIds.has(r.id) && (r.invRaw.includes(k) || k.includes(r.invRaw) || r.docRaw.includes(k)));
                            if (match) break;
                        }
                        if (!match && aiTotal > 0 && aiTax.length > 5) {
                            match = allRows.find(r => !usedRowIds.has(r.id) && Math.abs(r.total - aiTotal) < 1.0 && r.tax.includes(aiTax));
                        }
                        if (!match && aiTotal > 0) {
                            match = allRows.find(r => !usedRowIds.has(r.id) && Math.abs(r.total - aiTotal) < 0.1);
                        }

                        if (match) {
                            usedRowIds.add(match.id);
                            const fd2 = new FormData();
                            fd2.append('link_file_id', match.id);
                            fd2.append('file_name', data.file_name);
                            fd2.append('extracted_data_json', JSON.stringify(ai));

                            const res2 = await fetch('', { method: 'POST', body: fd2 });
                            const d2 = await res2.json();

                            if (d2.status === 'success') {
                                document.getElementById(`status-${match.id}`).innerHTML = `<span class="badge ${d2.row_status === 'Verified' ? 'st-verified' : 'st-mismatch'}">${d2.row_status}</span>`;
                                document.getElementById(`file-path-${match.id}`).value = data.file_name;
                                document.getElementById(`ai-remark-${match.id}`).value = d2.remark;
                                const btn = document.getElementById(`btn-view-${match.id}`);
                                if (btn) { btn.style.display = 'inline-block'; btn.onclick = () => showDetailModal(match.id); }
                                addLog(`✔ ${file.name} -> จับคู่สำเร็จ`, 'green');
                            }
                            successCount++;
                        } else {
                            addLog(`❌ ${file.name} -> ไม่พบคู่`, 'red');
                            failCount++;
                        }
                    } else {
                        addLog(`⚠️ ${file.name} -> อ่านไม่ออก`, 'orange');
                        failCount++;
                    }
                } catch (e) {
                    console.error(e); addLog(`Error: ${file.name}`, 'red'); failCount++;
                }
            }
            input.value = '';
            Swal.fire(`เสร็จสิ้น (เจอ ${successCount}, ไม่เจอ ${failCount})`);
        }

        async function uploadAndScan(input, rowId) {
            const file = input.files[0];
            if (!file) return;
            const statusDiv = document.getElementById(`status-${rowId}`);
            statusDiv.innerHTML = '<span class="badge st-pending"><i class="fas fa-circle-notch spinner"></i> กำลังส่ง AI...</span>';
            const formData = new FormData();
            formData.append('ajax_file', file);
            formData.append('upload_id', rowId);

            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') {
                    const badgeClass = (data.row_status === 'Verified') ? 'st-verified' : 'st-mismatch';
                    const icon = (data.row_status === 'Verified') ? '✅' : '❌';
                    statusDiv.innerHTML = `<span class="badge ${badgeClass}">${icon} ${data.row_status}</span>`;
                    document.getElementById(`file-path-${rowId}`).value = data.file;
                    document.getElementById(`ai-remark-${rowId}`).value = data.remark;
                    const btn = document.getElementById(`btn-view-${rowId}`);
                    btn.style.display = 'inline-block';
                    btn.onclick = function () { showDetailModal(rowId); };
                    if (data.scan_time) {
                        const timeCell = document.getElementById('time-' + rowId);
                        if(timeCell) timeCell.innerHTML = '<span class="badge-time">' + data.scan_time + '</span>';
                    }
                } else {
                    statusDiv.innerHTML = '<span class="badge st-mismatch">Error</span>';
                    alert(data.msg);
                }
            } catch (err) {
                console.error(err); statusDiv.innerHTML = '<span class="badge st-mismatch">Net Error</span>';
            }
        }

        // ==========================================
        // 🔍 Show Detail Modal (ฉบับสมบูรณ์: แก้ JS Error & Logic)
        // ==========================================
        let currentMarkers = [];

        function showDetailModal(rowId) {
            const pathInput = document.getElementById(`file-path-${rowId}`);
            const remarkInput = document.getElementById(`ai-remark-${rowId}`);
            const imgEl = document.getElementById('modalImg');
            imgEl.onclick = function() {
                const zoomOverlay = document.getElementById('zoomOverlay');
                const zoomImg = document.getElementById('zoomImageSrc');
                zoomImg.src = this.src; // เอารูปปัจจุบันไปใส่ในตัวซูม
                zoomOverlay.style.display = 'block';
            };

            // 1. รับ Element (แก้ ID ให้ตรงกับ HTML)
            const container = document.getElementById('imgContainer');
            const markerLayer = document.getElementById('markerLayer');

            // 2. โหลดรูปภาพ
            if (!pathInput || !pathInput.value) {
                imgEl.style.display = 'none';
                document.getElementById('noImgPlaceholder').style.display = 'block';
                imgEl.src = "";
            } else {
                imgEl.style.display = 'block';
                document.getElementById('noImgPlaceholder').style.display = 'none';
                imgEl.src = `uploads/tax_invoices/${pathInput.value}`;
            }

            // 3. แปลงข้อมูล JSON
            let data = [];
            try { data = JSON.parse(remarkInput.value); } catch (e) { data = []; }
            currentMarkers = data;

            // 4. ล้าง Marker เก่า (เพื่อให้ drawAiMarkers วาดใหม่ทีหลัง)
            if (markerLayer) markerLayer.innerHTML = '';

            // 5. สร้างตาราง
            let html = `<table class="detail-table">
                <thead><tr><th>รายการ</th><th>Excel</th><th>AI อ่านได้</th><th style="text-align:center">ผล</th></tr></thead><tbody>`;

            if (data.length > 0) {
                data.forEach((item, index) => {
                    const icon = item.ok ? '✅' : '❌';
                    const rowClass = item.ok ? 'val-correct' : 'val-error';
                    const colTag = item.xls_col ? `<span class="col-tag">Col ${item.xls_col}</span>` : '';

                    // หมายเหตุ: ไม่สร้าง <div marker> ในนี้แล้ว ให้ drawAiMarkers จัดการทีเดียว

                    html += `<tr onmouseenter="highlightMarker(${index}, true)" onmouseleave="highlightMarker(${index}, false)" style="cursor:pointer">
                        <td><strong>${item.label}</strong></td>
                        <td>${item.db} ${colTag}</td>
                        <td class="${rowClass}">${item.ai}</td>
                        <td style="text-align:center">${icon}</td>
                    </tr>`;
                });
            } else {
                html += `<tr><td colspan="4" style="text-align:center">ไม่มีข้อมูล</td></tr>`;
            }
            html += `</tbody></table>`;

            document.getElementById('modalCheckList').innerHTML = html;

            // 6. เปิด Modal (แบบ Vanilla JS - ไม่ง้อ jQuery)
            const modal = document.getElementById('detailModal');
            modal.style.display = 'block'; // บังคับโชว์

            // รอรูปโหลดเสร็จแล้วค่อยคำนวณตำแหน่ง
            imgEl.onload = () => drawAiMarkers();
        }

        // --- ฟังก์ชันวาดกรอบ (รวมศูนย์ที่เดียว) ---
        function drawAiMarkers() {
            const img = document.getElementById('modalImg');
            const layer = document.getElementById('markerLayer');
            if (!img || !img.complete || img.naturalWidth === 0 || !layer) return;

            layer.innerHTML = ''; // ล้างก่อนวาด

            const scaleX = img.width / img.naturalWidth;
            const scaleY = img.height / img.naturalHeight;

            currentMarkers.forEach((item, index) => {
                // เช็คว่ามี box ส่งมาไหม (Format: [x, y, w, h])
                if (item.box && Array.isArray(item.box) && item.box.length === 4) {
                    const [x, y, w, h] = item.box;
                    const div = document.createElement('div');
                    div.className = `ai-marker ${item.ok ? 'correct' : ''}`;
                    div.id = `marker-${index}`;

                    // คำนวณตำแหน่ง
                    div.style.left = (x * scaleX) + 'px';
                    div.style.top = (y * scaleY) + 'px';
                    div.style.width = (w * scaleX) + 'px';
                    div.style.height = (h * scaleY) + 'px';

                    // ปรับแต่ง: ให้แสดงผลตลอดเวลาแบบจางๆ
                    div.style.display = 'block';

                    layer.appendChild(div);
                }
            });
        }

        // --- ฟังก์ชัน Highlight ---
        function highlightMarker(index, isActive) {
            const marker = document.getElementById(`marker-${index}`);
            if (marker) {
                if (isActive) marker.classList.add('active');
                else marker.classList.remove('active');
            }
        }

        // อัปเดตตำแหน่งเมื่อย่อขยายจอ
        window.addEventListener('resize', drawAiMarkers);

        // --- Zoom Tools (คงเดิม) ---
        let zoomActive = false;
        const ZOOM_LEVEL = 2.5;
        // ... (ฟังก์ชัน Zoom moveLens, toggleZoomMode ใช้ของเดิมได้เลยครับ ถ้ามีอยู่แล้ว) ...
        // ... หากไม่มี แจ้งได้ครับ ผมจะแปะเพิ่มให้ ...

    </script>
</body>

</html>