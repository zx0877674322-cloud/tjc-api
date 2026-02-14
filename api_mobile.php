<?php


header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
function getInput() {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

if (!isset($conn)) {
    echo json_encode(["status" => "error", "message" => "Database connection variable not found."]);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 🛠️ Helper Functions
function uploadSingleFile($fileKey, $targetDir = "uploads/") {
    if (!file_exists($targetDir)) @mkdir($targetDir, 0777, true);
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
        $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
        $filename = "file_" . time() . "_" . rand(100,999) . "." . $ext;
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetDir . $filename)) {
            return $filename;
        }
    }
    return "";
}

function uploadMultipleFiles($fileKey, $targetDir = "uploads/") {
    $uploaded_files = [];
    if (!file_exists($targetDir)) @mkdir($targetDir, 0777, true);
    
    if (isset($_FILES[$fileKey])) {
        if (is_array($_FILES[$fileKey]['name'])) {
            $count = count($_FILES[$fileKey]['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES[$fileKey]['error'][$i] == 0) {
                    $ext = pathinfo($_FILES[$fileKey]['name'][$i], PATHINFO_EXTENSION);
                    $filename = "multi_" . time() . "_" . $i . "_" . rand(100,999) . "." . $ext;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'][$i], $targetDir . $filename)) {
                        $uploaded_files[] = $filename;
                    }
                }
            }
        } 
    }
    return $uploaded_files; 
}

// ==========================================
// 1. LOGIN (เข้าสู่ระบบ + ดึงสิทธิ์ Permission)
// ==========================================
if ($action == 'login') {
    $data = getInput();
    $user = isset($data['username']) ? $data['username'] : '';
    $pass = isset($data['password']) ? $data['password'] : '';

    $sql = "SELECT * FROM users WHERE username = ? AND password = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $user, $pass);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $role = $row['role'];
            
            // ดึงสิทธิ์ (Allowed Pages)
            $allowed_pages = [];
            if ($role == 'admin') {
                $allowed_pages = ['ALL'];
            } else {
                $sql_perm = "SELECT mp.file_name FROM permissions p 
                             JOIN master_pages mp ON p.page_id = mp.id 
                             WHERE p.role_name = ?";
                if ($stmt_perm = $conn->prepare($sql_perm)) {
                    $stmt_perm->bind_param("s", $role);
                    $stmt_perm->execute();
                    $res_perm = $stmt_perm->get_result();
                    while($perm = $res_perm->fetch_assoc()) {
                        $allowed_pages[] = $perm['file_name']; 
                    }
                    $stmt_perm->close();
                }
            }

            echo json_encode([
                "status" => "success", 
                "id" => (string)$row['id'],
                "fullname" => $row['fullname'],
                "role" => $role,
                "avatar" => $row['avatar'],
                "phone" => isset($row['phone']) ? $row['phone'] : "",
                "allowed_pages" => $allowed_pages 
            ]);
        } else {
            echo json_encode(["status" => "fail", "message" => "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "SQL Error: " . $conn->error]);
    }
}

// ==========================================
// 2. GET USERS
// ==========================================
else if ($action == 'get_users') {
    $sql = "SELECT DISTINCT reporter_name FROM reports ORDER BY reporter_name ASC";
    $result = $conn->query($sql);
    $users = [];
    if ($result) { while($row = $result->fetch_assoc()) { $users[] = $row['reporter_name']; } }
    echo json_encode($users);
}
// ==========================================
// 2.1 GET COMPANIES (ดึงรายชื่อบริษัท)
// ==========================================
else if ($action == 'get_companies') {
    $data = [];
    $sql = "SELECT company_name FROM companies ORDER BY company_name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row['company_name'];
        }
    }
    // ถ้าไม่มีข้อมูล ให้ส่งค่า Default ไปบ้าง
    if (empty($data)) {
        $data = ["TJC Group", "TJC Engineering", "TJC Construction"];
    }
    echo json_encode($data);
}
// ==========================================
// 2.2 GET CUSTOMERS (ดึงรายชื่อลูกค้า/หน่วยงาน)
// ==========================================
else if ($action == 'get_customers') {
    $data = [];
    $sql = "SELECT customer_name FROM master_customers ORDER BY customer_name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row['customer_name'];
        }
    }
    echo json_encode($data);
}

// ==========================================
// 3. GET DASHBOARD STATS (Modified for Platform Stats & Images)
// ==========================================
else if ($action == 'get_dashboard_stats') {
    $tab = $_GET['tab'] ?? 'sales';
    $filter_name = $_GET['filter_name'] ?? '';
    $filter_status = $_GET['filter_status'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';

    $table = 'reports';
    $status_col = 'job_status';
    
    if ($tab == 'purchase') { $table = 'report_purchases'; $status_col = 'tax_invoice_status'; }
    else if ($tab == 'marketing') { $table = 'report_online_marketing'; $status_col = 'tax_invoice_status'; }

    $where = "WHERE 1=1";
    if (!empty($filter_name) && $filter_name != 'undefined') $where .= " AND reporter_name = '$filter_name'";
    if (!empty($start_date) && $start_date != 'undefined') $where .= " AND DATE(report_date) >= '$start_date'";
    if (!empty($end_date) && $end_date != 'undefined') $where .= " AND DATE(report_date) <= '$end_date'";
    
    if (!empty($filter_status) && $filter_status != 'ทั้งหมด' && $filter_status != 'undefined') {
        if ($tab == 'sales') $where .= " AND $status_col = '$filter_status'";
        else $where .= " AND $status_col LIKE '%$filter_status%'";
    }

    // 1. Summary Calculation
    $sql_summary = "SELECT COUNT(*) as total, SUM(total_expense) as expense";
    if ($tab == 'marketing') $sql_summary .= ", SUM(total_sales) as sales";
    $sql_summary .= " FROM $table $where";
    
    $res_summary = $conn->query($sql_summary);
    $summary_row = ($res_summary) ? $res_summary->fetch_assoc() : [];
    
    $summary = [
        "total" => intval($summary_row['total'] ?? 0),
        "expense" => floatval($summary_row['expense'] ?? 0),
        "sales" => floatval($summary_row['sales'] ?? 0)
    ];

    // 2. Status Breakdown
    $breakdown = [];
    if ($tab == 'sales') {
        $sql_group = "SELECT $status_col, COUNT(*) as count FROM $table $where GROUP BY $status_col";
        $res_group = $conn->query($sql_group);
        if ($res_group) {
            while($row = $res_group->fetch_assoc()) {
                $st = !empty($row[$status_col]) ? $row[$status_col] : 'ไม่ระบุ';
                $breakdown[] = ['status' => $st, 'count' => intval($row['count'])];
            }
        }
    } else {
        $sql_raw = "SELECT $status_col FROM $table $where";
        $res_raw = $conn->query($sql_raw);
        $status_counts = [];
        if ($res_raw) {
            while($row = $res_raw->fetch_assoc()) {
                $raw_txt = $row[$status_col];
                if(empty($raw_txt)) continue;
                $items = explode(',', $raw_txt);
                foreach($items as $item) {
                    $parts = explode(':', $item);
                    $clean_st = trim(end($parts));
                    if(!empty($clean_st)) {
                        if(!isset($status_counts[$clean_st])) $status_counts[$clean_st] = 0;
                        $status_counts[$clean_st]++;
                    }
                }
            }
        }
        foreach($status_counts as $st => $cnt) $breakdown[] = ['status' => $st, 'count' => $cnt];
    }

    // 3. Platform Stats Calculation (แก้ไขใหม่: ดึงรูปจาก marketing_platforms)
    $platform_stats = [];
    
    // Only calculate for marketing tab if requested
    if ($tab == 'marketing') {
        $platform_data = []; // เก็บข้อมูลรูปภาพและชื่อจริงจาก DB

        // 1. ดึงข้อมูลชื่อและรูปภาพจากตาราง marketing_platforms
        $sql_pf_img = "SELECT platform_name, platform_image FROM marketing_platforms";
        $res_pf_img = $conn->query($sql_pf_img);
        
        // สร้าง Base URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $current_path = str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']);
        $base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . $current_path . "uploads/platforms/";

        if ($res_pf_img) {
            while($row = $res_pf_img->fetch_assoc()) {
                $db_key = strtolower(trim($row['platform_name']));
                $img_filename = $row['platform_image'];
                $full_url = (!empty($img_filename) && file_exists("uploads/platforms/" . $img_filename)) 
                            ? $base_url . $img_filename 
                            : null;

                $platform_data[$db_key] = [
                    'real_name' => $row['platform_name'], // ✅ ชื่อมาตรฐานสำหรับรวมกลุ่ม
                    'image_url' => $full_url
                ];
            }
        }

        // 2. คำนวณยอดขายจาก item_details
        $sql_details = "SELECT item_details FROM $table $where";
        $res_details = $conn->query($sql_details);
        
        if ($res_details) {
            while ($row = $res_details->fetch_assoc()) {
                $details = $row['item_details'];
                if (!empty($details)) {
                    $shops = explode('--------------------', $details);
                    foreach ($shops as $shop_txt) {
                        $shop_txt = trim($shop_txt);
                        if (empty($shop_txt)) continue;
                        
                        $lines = explode("\n", $shop_txt);
                        $header = $lines[0];
                        
                        // Logic สกัดชื่อร้าน + ลบวงเล็บส่วนเกิน
                        $clean_name = trim(preg_replace('/[🌐\?]|(\(Order:.*?\))|(#.*)|(ช่องทางที่ \d+:)/i', '', $header));
                        $clean_name = str_replace(')', '', $clean_name); // ลบวงเล็บปิดที่หลุดมา
                        $clean_name = trim($clean_name);

                        if (empty($clean_name) || $clean_name == ':') $clean_name = 'อื่นๆ';
                        
                        // ดึงยอดเงิน
                        if (preg_match('/💰.*?([\d,\.]+)/', $shop_txt, $matches)) {
                            $amount = floatval(str_replace(',', '', $matches[1]));
                            
                            // 🔥 GROUPING LOGIC (หัวใจสำคัญ: รวมชื่อร้าน) 🔥
                            $lookup_key = strtolower($clean_name);
                            $group_name = $clean_name; // Default: ใช้ชื่อตามรายงาน
                            $final_img = null;

                            // 2.1 ลองเทียบตรงๆ
                            if (isset($platform_data[$lookup_key])) {
                                $group_name = $platform_data[$lookup_key]['real_name']; // ✅ เจอใน DB -> ใช้ชื่อจริงรวมกลุ่ม
                                $final_img  = $platform_data[$lookup_key]['image_url'];
                            } 
                            // 2.2 ถ้าไม่เจอ ลองวนหา (Fuzzy Match)
                            else {
                                foreach ($platform_data as $db_key => $data) {
                                    if ((strpos($lookup_key, $db_key) !== false && $db_key !== '') || 
                                        (strpos($db_key, $lookup_key) !== false && $lookup_key !== '')) {
                                        
                                        $group_name = $data['real_name']; // ✅ เจอคล้ายๆ -> ใช้ชื่อจริงรวมกลุ่ม
                                        $final_img  = $data['image_url'];
                                        break;
                                    }
                                }
                            }

                            // สร้างข้อมูลลง Array Stats (ใช้ $group_name เป็น Key เพื่อรวมยอด)
                            if (!isset($platform_stats[$group_name])) {
                                $platform_stats[$group_name] = [
                                    'name' => $group_name,
                                    'total' => 0,
                                    'image' => $final_img 
                                ];
                            }
                            $platform_stats[$group_name]['total'] += $amount; // ✅ บวกยอดเพิ่มเข้าไปในกลุ่มเดิม
                            
                            // อัปเดตเผื่อรายการแรกไม่มีรูป
                            if ($final_img && $platform_stats[$group_name]['image'] == null) {
                                $platform_stats[$group_name]['image'] = $final_img;
                            }
                        }
                    }
                }
            }
        }

        // Sort by total descending
        usort($platform_stats, function($a, $b) {
            return $b['total'] - $a['total'];
        });
        
        $platform_stats = array_values($platform_stats);
    }

    // 4. Recent List
    $sql_recent = "SELECT * FROM $table $where ORDER BY report_date DESC, id DESC LIMIT 20";
    $res_recent = $conn->query($sql_recent);
    $recent = [];
    if ($res_recent) { while($row = $res_recent->fetch_assoc()) { $recent[] = $row; } }

    echo json_encode([
        "summary" => $summary, 
        "breakdown" => $breakdown, 
        "platform_stats" => $platform_stats, 
        "recent" => $recent
    ]);
}

// ==========================================
// 4. SUBMIT REPORT (ฝ่ายขาย - เพิ่ม Auto Save ลูกค้า)
// ==========================================
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'get_customer_history') {
    
    // รับค่าชื่อลูกค้าที่ส่งมา
    $customer_name = $conn->real_escape_string($_GET['customer_name']);
    
    // รับช่วงวันที่ (ถ้ามี)
    $s_date = $_GET['start_date'] ?? '';
    $e_date = $_GET['end_date'] ?? '';

    // สร้าง SQL Query
    $sql_where = "WHERE work_result = '$customer_name'";
    
    if(!empty($s_date)) { $sql_where .= " AND report_date >= '$s_date'"; }
    if(!empty($e_date)) { $sql_where .= " AND report_date <= '$e_date'"; }

    // ดึงข้อมูล: วันที่, คนทำ, สถานะ, ยอดเงิน, โครงการ, หมายเหตุ
    $sql_hist = "SELECT report_date, reporter_name, job_status, total_expense, project_name, additional_notes 
                 FROM reports $sql_where 
                 ORDER BY report_date DESC";
                 
    $res_hist = $conn->query($sql_hist);
    $history_data = [];
    
    if ($res_hist) {
        while ($row = $res_hist->fetch_assoc()) {
            $history_data[] = $row;
        }
    }
    
    // ส่งค่ากลับเป็น JSON ให้แอป
    header('Content-Type: application/json');
    echo json_encode($history_data);
    exit(); // จบการทำงานทันที (สำคัญมาก)
}
else if ($action == 'submit_report') {
    // รับค่าจาก POST
    $report_date = $_POST['report_date'] ?? date('Y-m-d H:i:s');
    $reporter_name = $_POST['reporter_name'] ?? '';
    
    $work_type = $_POST['work_type'] ?? '';
    $area = ($work_type=='company') ? 'เข้าบริษัท (สำนักงาน)' : ($_POST['area_zone']??'');
    $province = ($work_type=='company') ? 'กรุงเทพมหานคร' : ($_POST['province']??'');
    $gps = ($work_type=='company') ? 'Office' : ($_POST['gps']??'');
    $gps_address = ($work_type=='company') ? 'สำนักงานใหญ่' : ($_POST['gps_address']??'');
    
    $work_result = trim($_POST['work_result'] ?? ''); // ชื่อลูกค้า/หน่วยงาน
    $customer_type = $_POST['customer_type'] ?? 'ลูกค้าเก่า';
    $project_name = $_POST['project_name'] ?? '';
    $additional_notes = $_POST['additional_notes'] ?? '';
    $job_status = $_POST['job_status'] ?? '';
    $next_appointment = (!empty($_POST['next_appointment']) && $_POST['next_appointment']!='null') ? $_POST['next_appointment'] : NULL;
    $activity_type = $_POST['activity_type'] ?? '';
    $activity_detail = $_POST['activity_detail'] ?? '';

    // คำนวณค่าใช้จ่าย
    $fuel = 0.00;
    if (isset($_POST['fuel_cost'])) {
        if (is_array($_POST['fuel_cost'])) { foreach ($_POST['fuel_cost'] as $c) $fuel += floatval($c); }
        else { $fuel = floatval($_POST['fuel_cost']); }
    }
    $acc = floatval($_POST['accommodation_cost'] ?? 0);
    $other = floatval($_POST['other_cost'] ?? 0);
    $total = $fuel + $acc + $other;
    
    $other_cost_detail = $_POST['other_cost_detail'] ?? '';
    $problem = $_POST['problem'] ?? '';
    $suggestion = $_POST['suggestion'] ?? '';

    // อัปโหลดรูป
    $fuel_files = uploadMultipleFiles('fuel_receipt_file', 'uploads/');
    $fuel_receipt = implode(',', $fuel_files);
    $acc_receipt = uploadSingleFile('accommodation_receipt_file', 'uploads/');
    $other_receipt = uploadSingleFile('other_receipt_file', 'uploads/');

    // ⭐ AUTO-SAVE: บันทึกชื่อลูกค้าลง Master ถ้าไม่มี
    if (!empty($work_result)) {
        // 1. เช็คก่อนว่ามีชื่อนี้หรือยัง
        $check_sql = "SELECT id FROM master_customers WHERE customer_name = ?";
        if ($chk_stmt = $conn->prepare($check_sql)) {
            $chk_stmt->bind_param("s", $work_result);
            $chk_stmt->execute();
            $chk_stmt->store_result();
            
            // 2. ถ้ายังไม่มี (num_rows == 0) ให้เพิ่มเข้าไปใหม่
            if ($chk_stmt->num_rows == 0) {
                $add_sql = "INSERT INTO master_customers (customer_name) VALUES (?)";
                if ($add_stmt = $conn->prepare($add_sql)) {
                    $add_stmt->bind_param("s", $work_result);
                    $add_stmt->execute();
                    $add_stmt->close();
                }
            }
            $chk_stmt->close();
        }
    }

    // บันทึกรายงานหลัก
    $sql = "INSERT INTO reports (
        report_date, reporter_name, area, province, gps, gps_address, 
        work_result, customer_type, project_name, additional_notes, job_status, next_appointment, activity_type, activity_detail, 
        fuel_cost, fuel_receipt, accommodation_cost, accommodation_receipt, 
        other_cost, other_receipt, other_cost_detail, total_expense, problem, suggestion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssssssssssdsdsdssdds", 
            $report_date, $reporter_name, $area, $province, $gps, $gps_address, 
            $work_result, $customer_type, $project_name, $additional_notes, $job_status, $next_appointment, $activity_type, $activity_detail, 
            $fuel, $fuel_receipt, $acc, $acc_receipt, 
            $other, $other_receipt, $other_cost_detail, $total, $problem, $suggestion
        );

        if ($stmt->execute()) echo json_encode(["status" => "success", "message" => "บันทึกข้อมูลเรียบร้อย"]);
        else echo json_encode(["status" => "error", "message" => $stmt->error]);
    } else {
        echo json_encode(["status" => "error", "message" => "Prepare Error: " . $conn->error]);
    }
}

// ==========================================
// 5. SUBMIT PURCHASE (ฝ่ายจัดซื้อ)
// ==========================================
else if ($action == 'submit_purchase') {
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $reporter_name = $_POST['reporter_name'] ?? 'Unknown';
    $problem = $_POST['problem'] ?? '';
    $additional_notes = $_POST['additional_notes'] ?? '';

    $shops = $_POST['shops'] ?? [];
    $shop_names = [];
    $project_names = []; 
    $status_list = [];
    $item_details_parts = [];

    foreach ($shops as $idx => $shop) {
        $supplier = trim($shop['supplier'] ?? '');
        if (!$supplier) continue;

        $shop_names[] = $supplier;
        if (!empty($shop['project'])) $project_names[] = $shop['project'];
        
        $status = $shop['tax_status'] ?? '-';
        $status_list[] = "$supplier: $status";

        $detail = "ร้าน: $supplier";
        if(!empty($shop['project'])) $detail .= " (หน้างาน: {$shop['project']})";
        if(!empty($shop['doc_no'])) $detail .= " | เลขที่: {$shop['doc_no']}";
        $detail .= "\n";
        
        if (isset($shop['products']) && is_array($shop['products'])) {
            foreach ($shop['products'] as $prod) {
                if(!empty($prod['name'])) $detail .= "- {$prod['name']} (x{$prod['qty']})\n";
            }
        }
        $item_details_parts[] = $detail;
    }

    $supplier_name_str = implode(", ", $shop_names);
    $project_name_str = implode(", ", array_unique($project_names)); 
    $item_details_str = implode("\n--------------------\n", $item_details_parts);
    $tax_status_str = implode(", ", $status_list);
    $item_count = count($shop_names);

    $exp_names = $_POST['exp_name'] ?? [];
    $exp_amounts = $_POST['exp_amount'] ?? [];
    $exp_files = uploadMultipleFiles('exp_file', 'uploads/');

    $expense_list = [];
    $total_expense = 0;
    
    for ($i = 0; $i < count($exp_names); $i++) {
        $nm = trim($exp_names[$i]);
        $amt = floatval($exp_amounts[$i]);
        if ($nm || $amt > 0) {
            $expense_list[] = "$nm (" . number_format($amt, 2) . ")";
            $total_expense += $amt;
        }
    }
    $expense_list_str = implode(", ", $expense_list);
    $expense_files_str = implode(",", $exp_files);

    $sql = "INSERT INTO report_purchases (
        report_date, reporter_name, supplier_name, project_name, item_count, item_details, 
        problem, tax_invoice_status, additional_notes, expense_list, expense_files, total_expense
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssissssssd", 
            $report_date, $reporter_name, $supplier_name_str, $project_name_str, $item_count, $item_details_str,
            $problem, $tax_status_str, $additional_notes, $expense_list_str, $expense_files_str, $total_expense
        );
        if ($stmt->execute()) echo json_encode(["status" => "success", "message" => "บันทึกข้อมูลจัดซื้อเรียบร้อย"]);
        else echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
}

// ==========================================
// 6. SUBMIT MARKETING (ฝ่ายการตลาด - UPDATED)
// ==========================================
if ($action == 'submit_marketing') {
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $reporter_name = $_POST['reporter_name'] ?? 'Unknown';
    $problem = $_POST['problem'] ?? '';
    
    // ✅ รับค่า Memo
    $additional_notes = $_POST['additional_notes'] ?? '';
    $memo = trim($_POST['memo'] ?? '');
    if (!empty($memo)) {
        if (!empty($additional_notes)) $additional_notes .= " | ";
        $additional_notes .= $memo;
    }
    
    $work_type = 'Online Marketing';
    $area = '-';
    $province = '-';
    $gps = '-';
    $gps_address = '-';

    $total_expense = floatval($_POST['total_expense'] ?? 0);
    $total_sales = floatval($_POST['total_sales'] ?? 0);

    // ✅ 1. รับค่า Doc Refs (เลขที่เอกสาร)
    $doc_refs_arr = [];
    if (isset($_POST['doc_refs']) && is_array($_POST['doc_refs'])) {
        foreach ($_POST['doc_refs'] as $doc) {
            if (!empty($doc['number'])) {
                $doc_refs_arr[] = $doc['prefix'] . " " . trim($doc['number']);
            }
        }
    }
    // ถ้า App ส่ง string มาด้วย (เผื่อไว้) ก็ใช้ได้เช่นกัน
    if (empty($doc_refs_arr) && isset($_POST['doc_references'])) {
        $doc_references_str = $_POST['doc_references'];
    } else {
        $doc_references_str = implode(", ", $doc_refs_arr);
    }

    // ✅ 2. รับค่า Orders (เปลี่ยนจาก platforms เป็น orders)
    // ตรงนี้สำคัญ! App ส่ง 'orders' มา PHP ต้องรับ 'orders'
    $orders_data = $_POST['orders'] ?? []; 
    
    $pf_names = []; 
    $order_nos = []; 
    $status_list = []; 
    $item_details_parts = [];
    $platform_files_arr = []; // เก็บชื่อไฟล์แยกตาม Order

    $calculated_total_sales = 0;

    foreach ($orders_data as $idx => $order) {
        $p_name = trim($order['platform'] ?? '');
        $o_num  = trim($order['order_no'] ?? '');

        if (!$p_name) continue;

        $pf_names[] = $p_name;
        if (!empty($o_num)) $order_nos[] = "$p_name: $o_num";
        
        $st = $order['tax_status'] ?? '-';
        $status_list[] = "$p_name: $st";

        // สร้างรายละเอียดสินค้า
        $detail = "🌐 $p_name";
        if(!empty($o_num)) $detail .= " (Order: $o_num)";
        $detail .= "\n";
        
        $platform_total = 0;

        if (isset($order['products']) && is_array($order['products'])) {
            foreach ($order['products'] as $prod) {
                if(!empty($prod['name'])) {
                    $qty = floatval($prod['qty'] ?? 0);
                    $price = floatval($prod['price'] ?? 0);
                    
                    // ✅ รับค่า ส่วนลด และ ค่าส่ง
                    $discount = floatval($prod['discount'] ?? 0);
                    $shipping = floatval($prod['shipping'] ?? 0);

                    // ✅ สูตรคำนวณใหม่
                    $line_total = ($qty * $price) - $discount + $shipping;
                    
                    $platform_total += $line_total;
                    
                    $detail .= "- {$prod['name']} (x{$qty} @ " . number_format($price) . ")";
                    if ($discount > 0) $detail .= " [ลด -" . number_format($discount) . "]";
                    if ($shipping > 0) $detail .= " [ส่ง +" . number_format($shipping) . "]";
                    $detail .= " = " . number_format($line_total) . " บ.\n";
                }
            }
        }
        $calculated_total_sales += $platform_total;
        $detail .= "💰 ยอดรวมร้านนี้: " . number_format($platform_total, 2) . " บาท";
        $item_details_parts[] = $detail;

        // ✅ จัดการไฟล์รูปภาพ (รับตาม key: order_files_0, order_files_1, ...)
        $file_input_name = "order_files_" . $idx;
        $new_files = uploadMultipleFiles($file_input_name, 'uploads/marketing/');
        if (!empty($new_files)) {
            // เก็บรูปแบบ "OrderNo:file1.jpg,file2.jpg"
            $key_name = !empty($o_num) ? $o_num : $p_name;
            $platform_files_arr[] = $key_name . ":" . implode(",", $new_files);
        }
    }

    $pf_name_str = implode(", ", $pf_names);
    $order_no_str = implode(", ", $order_nos);
    $item_details_str = implode("\n--------------------\n", $item_details_parts);
    $status_str = implode(", ", $status_list); 
    $pf_files_str = implode("|", $platform_files_arr); // ใช้ | คั่นแต่ละออเดอร์
    $item_count = count($pf_names);

    // --- Expenses Data ---
    $exp_names = $_POST['exp_name'] ?? [];
    $exp_amounts = $_POST['exp_amount'] ?? [];
    $exp_files = uploadMultipleFiles('exp_file', 'uploads/marketing/');

    $expense_list = [];
    $calc_expense = 0;
    for ($i = 0; $i < count($exp_names); $i++) {
        $nm = trim($exp_names[$i]);
        $amt = floatval($exp_amounts[$i]);
        if ($nm || $amt > 0) {
            $expense_list[] = "$nm (" . number_format($amt, 2) . ")";
            $calc_expense += $amt;
        }
    }
    $expense_list_str = implode(" | ", $expense_list);
    $expense_files_str = implode(",", $exp_files);

    $final_total_sales = $calculated_total_sales;
    $final_total_expense = ($calc_expense > 0) ? $calc_expense : $total_expense;

    // ✅ SQL INSERT (เพิ่ม doc_references เข้าไป)
    // ตรวจสอบว่าตาราง report_online_marketing มีฟิลด์ doc_references หรือยัง?
    // ถ้ายังไม่มีต้องไปเพิ่มใน Database: ALTER TABLE report_online_marketing ADD COLUMN doc_references TEXT AFTER order_number;
    
    $sql = "INSERT INTO report_online_marketing (
        report_date, reporter_name, work_type, area, province, gps, gps_address,
        platform_name, order_number, doc_references, item_count, item_details,
        problem, tax_invoice_status, additional_notes, expense_list, expense_files, platform_files,
        total_expense, total_sales, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    if ($stmt = $conn->prepare($sql)) {
        // Bind Params: 20 ตัว (ssssssssssisssssssdd)
        $stmt->bind_param("ssssssssssisssssssdd", 
            $report_date,       // 1
            $reporter_name,     // 2
            $work_type,         // 3
            $area,              // 4
            $province,          // 5
            $gps,               // 6
            $gps_address,       // 7
            $pf_name_str,       // 8
            $order_no_str,      // 9
            $doc_references_str,// 10 (เพิ่มตัวนี้)
            $item_count,        // 11
            $item_details_str,  // 12
            $problem,           // 13
            $status_str,        // 14
            $additional_notes,  // 15
            $expense_list_str,  // 16
            $expense_files_str, // 17
            $pf_files_str,      // 18
            $final_total_expense, // 19
            $final_total_sales    // 20
        );

        if ($stmt->execute()) echo json_encode(["status" => "success", "message" => "บันทึกข้อมูลการตลาดเรียบร้อย"]);
        else echo json_encode(["status" => "error", "message" => "SQL Error: " . $stmt->error]);
    } else {
        echo json_encode(["status" => "error", "message" => "Prepare Failed: " . $conn->error]);
    }
}

// ==========================================
// 7. GET HISTORY (ฉบับสมบูรณ์: เพิ่ม Admin)
// ==========================================
else if ($action == 'get_history') {
    $reporter = $_GET['reporter_name'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';

    // เตรียมเงื่อนไขวันที่
    $dateSql = "";
    if ($startDate && $endDate) {
        $dateSql = " AND DATE(report_date) BETWEEN '$startDate' AND '$endDate'";
    }

    $history = [];

    // --- 1. SALES (ฝ่ายขาย) ---
    $sql1 = "SELECT *, 'sales' as source_type FROM reports WHERE reporter_name = ? $dateSql ORDER BY report_date DESC LIMIT 100";
    if ($stmt1 = $conn->prepare($sql1)) {
        $stmt1->bind_param("s", $reporter);
        $stmt1->execute();
        $res1 = $stmt1->get_result();
        while ($row = $res1->fetch_assoc()) {
            $row['total_sales'] = floatval($row['total_sales'] ?? 0);
            $row['total_expense'] = floatval($row['total_expense'] ?? 0);
            $history[] = $row;
        }
        $stmt1->close();
    }

    // --- 2. PURCHASE (ฝ่ายจัดซื้อ) ---
    $sql2 = "SELECT *, 'purchase' as source_type, 0 as total_sales FROM report_purchases WHERE reporter_name = ? $dateSql ORDER BY report_date DESC LIMIT 100";
    if ($stmt2 = $conn->prepare($sql2)) {
        $stmt2->bind_param("s", $reporter);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            $row['work_result'] = $row['supplier_name'] ?? 'ร้านค้าทั่วไป'; 
            $row['project_name'] = $row['project_name'] ?? 'งานทั่วไป';
            
            $raw_status = $row['tax_invoice_status'] ?? '';
            if (strpos($raw_status, ':') !== false) {
                $parts = explode(':', $raw_status);
                $row['job_status'] = trim(end($parts));
            } else {
                $row['job_status'] = $raw_status ?: 'สำเร็จ';
            }

            $row['total_expense'] = floatval($row['total_expense'] ?? 0);
            $history[] = $row;
        }
        $stmt2->close();
    }

    // --- 3. MARKETING (ฝ่ายการตลาด) ---
    $sql3 = "SELECT *, 'marketing' as source_type FROM report_online_marketing WHERE reporter_name = ? $dateSql ORDER BY report_date DESC LIMIT 100";
    if ($stmt3 = $conn->prepare($sql3)) {
        $stmt3->bind_param("s", $reporter);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        while ($row = $res3->fetch_assoc()) {
            $row['work_result'] = $row['platform_name'] ?? 'Online Marketing'; 
            $row['project_name'] = "Order: " . ($row['order_number'] ?? '-');
            
            $raw_status = $row['tax_invoice_status'] ?? '';
            if (strpos($raw_status, ':') !== false) {
                $parts = explode(':', $raw_status);
                $row['job_status'] = trim(end($parts));
            } else {
                $row['job_status'] = $raw_status ?: 'สำเร็จ';
            }

            $row['total_sales'] = floatval($row['total_sales'] ?? 0);
            $row['total_expense'] = floatval($row['total_expense'] ?? 0);
            $history[] = $row;
        }
        $stmt3->close();
    }

    // ✅✅ --- 4. ADMIN (ฝ่ายธุรการ) [เพิ่มส่วนนี้] --- ✅✅
    // หมายเหตุ: ต้อง map ฟิลด์ให้ตรงกับหน้า App (work_result, job_status, total_expense)
    $sql4 = "SELECT *, 'admin' as source_type FROM report_admin WHERE reporter_name = ? $dateSql ORDER BY report_date DESC LIMIT 100";
    if ($stmt4 = $conn->prepare($sql4)) {
        $stmt4->bind_param("s", $reporter);
        $stmt4->execute();
        $res4 = $stmt4->get_result();
        while ($row = $res4->fetch_assoc()) {
            // Map ข้อมูลให้แอปอ่านได้
            // ใช้ "งานทั่วไป" เป็นชื่อหัวข้อ ถ้าไม่มี Note
            $row['work_result'] = !empty($row['note']) ? $row['note'] : 'งานธุรการทั่วไป'; 
            $row['project_name'] = 'Admin Report'; // ชื่อโปรเจกต์สมมติ
            $row['job_status'] = 'เสร็จสิ้น'; // Admin มักจะเสร็จเลย หรือจะเช็คเงื่อนไขอื่นก็ได้
            
            // ยอดเงินรวม
            $row['total_expense'] = floatval($row['total_amount'] ?? 0);
            $row['total_sales'] = 0;

            // แปลง Boolean เป็น 0/1 เพื่อความชัวร์
            $row['has_expense'] = (bool)$row['has_expense'];
            $row['has_pr']      = (bool)$row['has_pr'];
            $row['has_job']     = (bool)$row['has_job'];
            $row['has_bg']      = (bool)$row['has_bg'];
            $row['has_stamp']   = (bool)$row['has_stamp'];

            $history[] = $row;
        }
        $stmt4->close();
    }

    // เรียงลำดับตามวันที่ล่าสุด
    usort($history, function($a, $b) {
        return strtotime($b['report_date']) - strtotime($a['report_date']);
    });

    echo json_encode([
        "status" => "success",
        "count" => count($history),
        "history" => $history
    ]);
}
// ==========================================
// 8. GET MAP DATA (แก้ไข: เปลี่ยนชื่อตัวแปรให้ตรงแอป และเพิ่ม work_result)
// ==========================================
else if ($action == 'get_map_data') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $filter_name = isset($_GET['filter_name']) ? $_GET['filter_name'] : '';

    // ✅ สิ่งที่แก้:
    // 1. เปลี่ยน "r.reporter_name as name" -> "r.reporter_name" (เพื่อให้ตรงกับแอป)
    // 2. เพิ่ม "r.work_result" (เพื่อให้แสดงชื่องาน/ลูกค้า)
    // 3. กรอง "gps != 'Office'" ออก (เพราะเอาลงแผนที่ไม่ได้)
    
    $sql = "SELECT r.id, r.reporter_name, r.gps, r.job_status as status, 
            r.project_name, r.work_result, r.customer_type as client, 
            DATE_FORMAT(r.report_date, '%d/%m/%Y') as date, 
            u.avatar, u.role as position 
            FROM reports r 
            LEFT JOIN users u ON r.reporter_name = u.fullname 
            WHERE r.gps IS NOT NULL AND r.gps != '' AND r.gps != 'Office' ";

    if (!empty($start_date) && $start_date != 'undefined') { $sql .= " AND DATE(r.report_date) >= '$start_date'"; }
    if (!empty($end_date) && $end_date != 'undefined') { $sql .= " AND DATE(r.report_date) <= '$end_date'"; }
    if (!empty($filter_name) && $filter_name != 'undefined') { $sql .= " AND r.reporter_name = '$filter_name'"; }

    $result = $conn->query($sql);
    $data = [];

    if ($result) {
        while($row = $result->fetch_assoc()) {
            if (strpos($row['gps'], ',') !== false) {
                $gps_parts = explode(',', $row['gps']);
                $row['lat'] = trim($gps_parts[0]);
                $row['lng'] = trim($gps_parts[1]);
                unset($row['gps']); 
                $data[] = $row;
            }
        }
    }
    echo json_encode($data);
}

// ==========================================
// 9. GET ANNOUNCEMENTS (ข่าวประชาสัมพันธ์)
// ==========================================
else if ($action == 'get_announcements') {
    // 1. รับค่าและป้องกัน SQL Injection
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $type_id = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
    $date_filter = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : ''; // รับค่าวันที่ (YYYY-MM-DD)
    
    // 2. สร้าง SQL Query หลัก
    $sql = "SELECT a.*, t.type_name, t.color_class 
            FROM announcements a 
            LEFT JOIN master_hr_types t ON a.type_id = t.id 
            WHERE 1=1 ";
            
    // เงื่อนไข: ค้นหาข้อความ
    if (!empty($search)) {
        $sql .= " AND (a.title LIKE '%$search%' OR a.content LIKE '%$search%')";
    }
    
    // เงื่อนไข: กรองหมวดหมู่
    if (!empty($type_id) && $type_id != 'all') {
        $sql .= " AND a.type_id = '$type_id'";
    }

    // เงื่อนไข: กรองวันที่ (สำคัญ! ต้องใช้ DATE() เพื่อตัดเวลาออก)
    if (!empty($date_filter)) {
        $sql .= " AND DATE(a.created_at) = '$date_filter'";
    }
    
    $sql .= " ORDER BY a.created_at DESC LIMIT 50";

    $result = $conn->query($sql);
    $news = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // 3. จัดการ path รูปภาพและไฟล์แนบ
            $img_url = "";
            $is_pdf = false;
            
            if (!empty($row['attachment'])) {
                $ext = strtolower(pathinfo($row['attachment'], PATHINFO_EXTENSION));
                // ถ้าเป็นรูปภาพ
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $img_url = $row['attachment']; 
                } 
                // ถ้าเป็น PDF
                elseif ($ext == 'pdf') {
                    $is_pdf = true;
                    $img_url = $row['attachment']; 
                }
            }
            
            $row['image_url'] = $img_url;
            $row['is_pdf'] = $is_pdf;
            
            // 4. สร้าง Preview Text (ลบ HTML Tag และจัดระเบียบข้อความ)
            $clean_content = strip_tags($row['content']); 
            $clean_content = str_replace(["&nbsp;", "\r", "\n"], " ", $clean_content); 
            $clean_content = preg_replace('/\s+/', ' ', $clean_content); 
            
            $row['preview_content'] = mb_substr($clean_content, 0, 100, 'UTF-8') . '...';
            
            $news[] = $row;
        }
    }
    
    // 5. ดึงหมวดหมู่ (เอาเฉพาะหมวดที่มีข่าวโพสต์อยู่จริง)
    $cats = [];
    $sql_cat = "SELECT DISTINCT t.id, t.type_name 
                FROM master_hr_types t 
                JOIN announcements a ON a.type_id = t.id 
                ORDER BY t.id ASC";
                
    $res_cat = $conn->query($sql_cat);
    if($res_cat) {
        while($c = $res_cat->fetch_assoc()) $cats[] = $c;
    }

    // ส่ง JSON กลับ
    echo json_encode(["status" => "success", "news" => $news, "categories" => $cats]);
}
// ==========================================
// 9.5 GET USER PROFILE (เพิ่มส่วนนี้!)
// ==========================================
else if ($action == 'get_user_profile') {
    $username = $_GET['username'] ?? '';

    $sql = "SELECT id, username, fullname, role, phone, avatar FROM users WHERE username = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode($row);
        } else {
            echo json_encode(["status" => "error", "message" => "User not found"]);
        }
        $stmt->close();
    } else {
         echo json_encode(["status" => "error", "message" => "SQL Error"]);
    }
}
// ==========================================
// 10. UPDATE PROFILE (อัปโหลดรูปโปรไฟล์)
// ==========================================
else if ($action == 'update_profile') {
    $username = $_POST['username'] ?? '';
    
    if (empty($username)) {
        echo json_encode(["status" => "error", "message" => "ไม่พบข้อมูล Username"]);
        exit();
    }

    // 1. ตรวจสอบว่ามีการส่งไฟล์มาไหม
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        
        $target_dir = __DIR__ . "/uploads/profiles/";
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!file_exists($target_dir)) {
            @mkdir($target_dir, 0777, true);
        }

        // ตั้งชื่อไฟล์ใหม่ป้องกันชื่อซ้ำ (ใช้ timestamp)
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $new_filename = $username . "_" . time() . "." . $ext;
        $target_file = $target_dir . $new_filename;

        // ย้ายไฟล์
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
            
            // 2. อัปเดตชื่อไฟล์ลงฐานข้อมูล
            $sql = "UPDATE users SET avatar = ? WHERE username = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ss", $new_filename, $username);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        "status" => "success", 
                        "message" => "อัปเดตโปรไฟล์เรียบร้อย",
                        "avatar" => $new_filename
                    ]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Database Error: " . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(["status" => "error", "message" => "Prepare Failed"]);
            }

        } else {
            echo json_encode(["status" => "error", "message" => "ไม่สามารถบันทึกไฟล์รูปภาพได้ (Permission Denied?)"]);
        }

    } else {
        echo json_encode(["status" => "error", "message" => "ไม่พบไฟล์รูปภาพ หรือไฟล์มีปัญหา (Error Code: " . $_FILES['avatar']['error'] . ")"]);
    }
}
// ... (ใน api_mobile.php) ...

// ==========================================
// 11. GET CASH FLOW (แก้ไขป้องกัน Error 500)
// ==========================================
else if ($action == 'get_cashflow') {
    // --- 1. รับค่า Filter วันที่ ---
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : '';

    // สร้างเงื่อนไข SQL
    $date_condition = "1=1"; 
    if (!empty($start_date) && !empty($end_date)) {
        $start_date = $conn->real_escape_string($start_date);
        $end_date = $conn->real_escape_string($end_date);
        $date_condition = "trans_date BETWEEN '$start_date' AND '$end_date'";
    }

    // --- 2. คำนวณยอดรวมทั้งหมด (Safe Mode) ---
    $sum_in = 0;
    $sum_out = 0;

    // คำนวณรายรับ (Income)
    $sql_sum_in = "SELECT SUM(amount) as total FROM cash_flow WHERE type='Income' AND $date_condition";
    $res_in = $conn->query($sql_sum_in);
    if ($res_in) {
        $row_in = $res_in->fetch_assoc();
        $sum_in = $row_in['total'] ?? 0;
    }

    // คำนวณรายจ่าย (Expense)
    $sql_sum_out = "SELECT SUM(amount) as total FROM cash_flow WHERE type='Expense' AND $date_condition";
    $res_out = $conn->query($sql_sum_out);
    if ($res_out) {
        $row_out = $res_out->fetch_assoc();
        $sum_out = $row_out['total'] ?? 0;
    }
    
    // --- 3. คำนวณยอดแยกรายบริษัท ---
    $comp_stats = [];
    $sql_comp = "SELECT company, 
                 SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) as total_in,
                 SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) as total_out
                 FROM cash_flow 
                 WHERE $date_condition
                 GROUP BY company 
                 ORDER BY total_in DESC";
    
    $res_comp = $conn->query($sql_comp);
    
    if ($res_comp) {
        // เตรียมดึง Logo แยก (เพื่อความชัวร์ ไม่ Subquery ซ้อน)
        while($row = $res_comp->fetch_assoc()) {
            $company_name = $row['company'];
            
            // ดึง Logo แบบแยก query (ช้ากว่านิดหน่อยแต่ปลอดภัยกว่า Subquery ถ้า DB ไม่สมบูรณ์)
            $logo_sql = "SELECT logo_file FROM companies WHERE company_name = '$company_name' LIMIT 1";
            $res_logo = $conn->query($logo_sql);
            $logo_file = ($res_logo && $r = $res_logo->fetch_assoc()) ? $r['logo_file'] : null;
            $row['logo_file'] = $logo_file;

            // ตัดคำชื่อบริษัท (Check mb_string support)
            $short_name = str_replace(['บริษัท ', ' จำกัด', ' (มหาชน)', ' คอร์ปอเรชั่น'], '', $company_name);
            
            if (function_exists('mb_substr')) {
                $row['short_name'] = mb_substr($short_name, 0, 10, 'UTF-8') . (mb_strlen($short_name) > 10 ? '..' : '');
            } else {
                // Fallback ถ้า Server ไม่มี mb_string
                $row['short_name'] = substr($short_name, 0, 10) . '..';
            }
            
            $row['total_in'] = floatval($row['total_in']);
            $row['total_out'] = floatval($row['total_out']);
            $row['diff'] = $row['total_in'] - $row['total_out'];
            
            $comp_stats[] = $row;
        }
    }

    // --- 4. ดึงรายการ (Transaction History) ---
    $limit_clause = ($date_condition === "1=1") ? "LIMIT 50" : "";
    $sql_list = "SELECT * FROM cash_flow WHERE $date_condition ORDER BY trans_date DESC, id DESC $limit_clause";
    $result = $conn->query($sql_list);
    $history = [];
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $row['amount'] = floatval($row['amount']);
            $history[] = $row;
        }
    }

    // --- 5. ส่ง JSON กลับ ---
    echo json_encode([
        "status" => "success",
        "filter" => [
            "is_filtered" => !empty($start_date) && !empty($end_date)
        ],
        "summary" => [
            "income" => floatval($sum_in),
            "expense" => floatval($sum_out),
            "diff" => floatval($sum_in - $sum_out)
        ],
        "company_stats" => $comp_stats,
        "history" => $history
    ]);
}

// ==========================================
// 12. GET BOOKING DATA
// ==========================================
else if ($action == 'get_booking_data') {
    require_once 'CarManager.php'; 
    if (!class_exists('CarManager')) {
        echo json_encode(["status" => "error", "message" => "CarManager class not found."]);
        exit();
    }

    $carMgr = new CarManager($conn);
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    $activeBooking = $carMgr->getActiveBooking($user_id);
    $user_phone = $carMgr->getUserPhone($user_id);
    
    $cars = $carMgr->getAllCars();
    
    // ---------------------------------------------------------
    // ✅ วนลูปดึงข้อมูลแจ้งซ่อม
    // ---------------------------------------------------------
    foreach ($cars as &$car) { 
        $car['m_reporter'] = '-';
        $car['m_phone'] = '-';
        $car['m_loc'] = '-';

        // เช็คว่ารถคันนี้สถานะเป็น maintenance หรือไม่
        if (isset($car['status']) && $car['status'] == 'maintenance') {
            
            // ✅ แก้ไข: ลบ "AND m.status = 'pending'" ออก 
            // เพื่อให้ดึงข้อมูลล่าสุดเสมอ ไม่ว่าสถานะ Log จะเป็นอะไร
            $sql_m = "SELECT m.reporter_name, m.service_center, u.fullname, u.phone 
                      FROM maintenance_logs m 
                      LEFT JOIN users u ON m.user_id = u.id 
                      WHERE m.vehicle_id = ? 
                      ORDER BY m.id DESC LIMIT 1"; 
            
            if ($stmt_m = $conn->prepare($sql_m)) {
                $stmt_m->bind_param("i", $car['id']); 
                $stmt_m->execute();
                $res_m = $stmt_m->get_result();
                
                if ($rm = $res_m->fetch_assoc()) {
                    // ชื่อผู้แจ้ง
                    $car['m_reporter'] = !empty($rm['reporter_name']) ? $rm['reporter_name'] : ($rm['fullname'] ?? '-');
                    
                    // เบอร์โทร
                    $car['m_phone'] = !empty($rm['phone']) ? $rm['phone'] : '-';
                    
                    // สถานที่ซ่อม
                    $car['m_loc'] = !empty($rm['service_center']) ? $rm['service_center'] : '-';
                }
                $stmt_m->close();
            }
        }
    }
    unset($car); 
    // ---------------------------------------------------------
    
    echo json_encode([
        "status" => "success",
        "activeBooking" => $activeBooking,
        "user_phone" => $user_phone,
        "cars" => $cars
    ]);
}
// ==========================================
// 13. BOOK CAR (แก้ปัญหาค่าว่าง/จอขาว)
// ==========================================
else if ($action == 'book_car') {
    require_once 'CarManager.php';
    if (!class_exists('CarManager')) {
        echo json_encode(["status" => "error", "message" => "Server Error: CarManager class not found"]);
        exit();
    }

    try {
        $carMgr = new CarManager($conn);

        // 1. รับค่าและแปลงเป็นตัวเลข (ป้องกัน SQL Crash)
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
        $passenger = isset($_POST['passenger_count']) ? intval($_POST['passenger_count']) : 1;
        
        $phone = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
        $destination = isset($_POST['destination']) ? trim($_POST['destination']) : '';
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        
        // 2. จัดการเวลา (ล้างค่าที่ผิดปกติ)
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
        $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
        
        // ล้างช่องว่างและ : ที่ซ้ำ (เช่น "16: :20")
        $start_time = str_replace([' ', '::'], ['', ':'], $start_time);
        $end_time = str_replace([' ', '::'], ['', ':'], $end_time);

        $start_datetime = "$start_date $start_time";
        $end_datetime = "$end_date $end_time";

        // 3. ตรวจสอบข้อมูลจำเป็น
        if ($user_id == 0 || $car_id == 0) {
            echo json_encode(["status" => "error", "message" => "ข้อมูลไม่ครบถ้วน (User ID หรือ Car ID เป็น 0)"]);
            exit();
        }

        // 4. อัปเดตเบอร์ (ถ้ามี)
        if (!empty($phone)) {
            $carMgr->updateUserPhone($user_id, $phone);
        }

        // 5. สร้างการจอง
        $res = $carMgr->createBooking($user_id, $car_id, $start_datetime, $end_datetime, $destination, $reason, $passenger);

        // 6. ส่งผลลัพธ์ (เช็คว่าได้ Array กลับมาไหม)
        if (is_array($res) && isset($res['success'])) {
            if ($res['success']) {
                echo json_encode(["status" => "success", "message" => $res['message']]);
            } else {
                echo json_encode(["status" => "error", "message" => $res['message']]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ (CarManager ไม่คืนค่า)"]);
        }

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Exception: " . $e->getMessage()]);
    }
}

// ==========================================
// 14. RETURN CAR (แก้ปัญหาค่าว่าง)
// ==========================================
else if ($action == 'return_car') {
    require_once 'CarManager.php';
    $carMgr = new CarManager($conn);

    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    
    // Step 1: หา user_id จาก booking_id (เพราะ API บางครั้งไม่ได้ส่ง user_id มาตอนคืน)
    $sql_find = "SELECT user_id FROM car_bookings WHERE id = ?";
    $stmt = $conn->prepare($sql_find);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $real_user_id = $row['user_id'];
        
        $parking_loc = isset($_POST['parking_location']) ? $_POST['parking_location'] : '';
        $energy = isset($_POST['energy_level']) ? $_POST['energy_level'] : '';
        $issue = isset($_POST['car_issue']) ? $_POST['car_issue'] : '';

        $return_note = "📍 จอดที่: $parking_loc | 🔋 พลังงาน: $energy";
        if(!empty($issue)) $return_note .= " | ⚠️ หมายเหตุ: $issue";

        // Step 2: เรียกฟังก์ชันคืนรถ
        if($carMgr->returnCar($booking_id, $real_user_id, $return_note)) {
            echo json_encode(["status" => "success", "message" => "คืนรถเรียบร้อย"]);
        } else {
            echo json_encode(["status" => "error", "message" => "บันทึกคืนรถไม่สำเร็จ (DB Error)"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "ไม่พบรายการจองนี้ ($booking_id)"]);
    }
}

// ==========================================
// 15. GET CAR DASHBOARD DATA (แก้ไข: เพิ่มการดึงข้อมูลซ่อม)
// ==========================================
else if ($action == 'get_car_dashboard_data') {
    require_once 'CarManager.php';
    if (!class_exists('CarManager')) {
        echo json_encode(["status" => "error", "message" => "CarManager class not found."]);
        exit();
    }

    $carMgr = new CarManager($conn);
    
    // รับค่าตัวกรองจาก App
    $d = isset($_GET['d']) ? $_GET['d'] : '';
    $m = isset($_GET['m']) ? $_GET['m'] : date('n');
    $y = isset($_GET['y']) ? $_GET['y'] : date('Y');
    
    // 1. ดึงข้อมูลรถทั้งหมด
    $allCars = $carMgr->getAllCars();

    // ---------------------------------------------------------
    // ✅ [ส่วนที่เพิ่ม] วนลูปดึงข้อมูลแจ้งซ่อม (Copy Logic มาจาก get_booking_data)
    // ---------------------------------------------------------
    foreach ($allCars as &$car) { 
        $car['m_reporter'] = '-';
        $car['m_phone'] = '-';
        $car['m_loc'] = '-';

        if (isset($car['status']) && $car['status'] == 'maintenance') {
            // ดึงข้อมูลล่าสุดโดยไม่สน status ย่อย (pending/processing)
            $sql_m = "SELECT m.reporter_name, m.service_center, u.fullname, u.phone 
                      FROM maintenance_logs m 
                      LEFT JOIN users u ON m.user_id = u.id 
                      WHERE m.vehicle_id = ? 
                      ORDER BY m.id DESC LIMIT 1"; 
            
            if ($stmt_m = $conn->prepare($sql_m)) {
                $stmt_m->bind_param("i", $car['id']); 
                $stmt_m->execute();
                $res_m = $stmt_m->get_result();
                
                if ($rm = $res_m->fetch_assoc()) {
                    $car['m_reporter'] = !empty($rm['reporter_name']) ? $rm['reporter_name'] : ($rm['fullname'] ?? '-');
                    $car['m_phone'] = !empty($rm['phone']) ? $rm['phone'] : '-';
                    $car['m_loc'] = !empty($rm['service_center']) ? $rm['service_center'] : '-';
                }
                $stmt_m->close();
            }
        }
    }
    unset($car); 
    // ---------------------------------------------------------
    
    // 2. ดึงประวัติการใช้งาน
    $history = $carMgr->getHistoryReport($d, $m, $y);
    
    echo json_encode([
        "status" => "success",
        "cars" => $allCars,
        "history" => $history,
        "filter" => ["d"=>$d, "m"=>$m, "y"=>$y]
    ]);
}
///////////////////////////////////////////////////////////// //API ของเดียร์  
// 16. GET ALL DAILY REPORTS
else if ($action == 'get_all_reports') {
    $sql = "
        SELECT 
            d.*,
            u.fullname AS completed_by_name,
            u.fullname AS accepted_by_name 
        FROM daily_reports d
        LEFT JOIN users u ON d.completed_by = u.id -- หรือเปลี่ยนเป็น u.fullname ถ้าเก็บเป็นชื่อ
        ORDER BY d.created_at DESC
    ";
    // หมายเหตุ: ถ้าใน DB เก็บ completed_by เป็น 'ชื่อคน' ตรงๆ ให้ใช้ d.completed_by ได้เลย ไม่ต้อง JOIN ก็ได้
    // แต่ถ้าเก็บเป็น ID ให้คง JOIN ไว้

    $result = $conn->query($sql);
    $reports = array();

    if ($result && $result->num_rows > 0) {
        
        $base_assign_url = "http://" . $_SERVER['HTTP_HOST'] . "/uploads/assign_img/";
        $base_comp_url   = "http://" . $_SERVER['HTTP_HOST'] . "/uploads/complete_img/"; 

        while($row = $result->fetch_assoc()) {
            
            // --- 🔥 [เพิ่มใหม่] ดึงประวัติการเลื่อนงาน (Postpone History) ---
            $histSql = "SELECT * FROM postpone_history WHERE report_id = '" . $row['id'] . "' ORDER BY id DESC";
            $histRes = $conn->query($histSql);
            $historyLog = [];
            if ($histRes) {
                while ($h = $histRes->fetch_assoc()) {
                    $historyLog[] = [
                        'old_date' => $h['old_date'],
                        'new_date' => $h['new_date'],
                        'reason' => $h['reason'],
                        'requested_by' => $h['requested_by'],
                        'moved_at' => $h['moved_at']
                    ];
                }
            }
            $row['history_log'] = $historyLog; 
            // -----------------------------------------------------------

            // ฟังก์ชันจัดการ URL รูปภาพ (ของเดิม)
            $addUrl = function($imgStr, $baseUrl) {
                if (empty($imgStr)) return null;
                $imgs = explode(',', $imgStr);
                $fullUrls = [];
                foreach($imgs as $img) {
                    $img = trim($img);
                    if(!empty($img)) {
                        if (strpos($img, 'http') === 0) {
                            $fullUrls[] = $img;
                        } else {
                            // Clean paths
                            $cleanImg = str_replace([
                                'uploads/complete_img/',   
                                'uploads/completion_img/', 
                                'uploads/complet_img/',    
                                'uploads/assign_img/'
                            ], '', $img);
                            
                            $fullUrls[] = $baseUrl . $cleanImg;
                        }
                    }
                }
                return implode(',', $fullUrls);
            };

            $row['image_path'] = $addUrl($row['image_path'], $base_assign_url);
            $row['completion_image'] = $addUrl($row['completion_image'], $base_comp_url);

            array_push($reports, $row);
        }
    }
    echo json_encode(['success' => true, 'data' => $reports]); // ปรับ format return ให้มาตรฐาน
}

// 17. UPDATE IMMIGRATION STATUS
else if ($action == 'update_immigration_status') {
    date_default_timezone_set('Asia/Bangkok');
    $currentTime = date("Y-m-d H:i:s");

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $statusAction = isset($_POST['action']) ? $_POST['action'] : ''; // รับ action พิเศษ เช่น request_extension
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    // ชื่อผู้ทำรายการ
    $action_by = isset($_POST['completed_by']) ? $conn->real_escape_string($_POST['completed_by']) : 'Mobile User';

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    // --- CASE A: ขอเลื่อนงาน (Request Extension) ---
    if ($statusAction === 'request_extension') {
        $newDate = isset($_POST['new_work_date']) ? $_POST['new_work_date'] : '';
        $reason = isset($_POST['reason']) ? $conn->real_escape_string($_POST['reason']) : '';

        if (empty($newDate)) {
            echo json_encode(['success' => false, 'message' => 'กรุณาระบุวันที่ใหม่']);
            exit;
        }

        // 1. บันทึกประวัติ
        $sqlHistory = "INSERT INTO postpone_history (report_id, old_date, new_date, reason, requested_by, moved_at) 
                       SELECT id, work_date, '$newDate', '$reason', '$action_by', '$currentTime' 
                       FROM daily_reports WHERE id = $id";
        $conn->query($sqlHistory);

        // 2. อัปเดตสถานะหลัก
        $sql = "UPDATE daily_reports SET 
                status = 'postponed', 
                requested_due_date = '$newDate', 
                extension_reason = '$reason',
                extension_status = 'pending',
                extension_requested_by = '$action_by',
                postpone_count = postpone_count + 1
                WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'บันทึกการขอเลื่อนแล้ว']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    // --- CASE B: ยกเลิกคำขอเลื่อน ---
    if ($statusAction === 'cancel_extension_request') {
        $sql = "UPDATE daily_reports SET 
                extension_status = 'none', 
                extension_reason = NULL, 
                extension_requested_by = NULL,
                requested_due_date = NULL, 
                status = 'pending' 
                WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    // --- CASE C: ลบรายงาน ---
    if ($statusAction === 'delete_report') {
        $sql = "DELETE FROM daily_reports WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    // --- CASE D: อัปเดตสถานะปกติ (รับงาน / จบงาน) ---
    if (!empty($status)) {
        $updateFields = [];
        $updateFields[] = "status = '" . $conn->real_escape_string($status) . "'";

        // --- เริ่มงาน (Processing) ---
        if ($status === 'processing') {
            $updateFields[] = "started_at = '$currentTime'";
            $updateFields[] = "accepted_by = '$action_by'"; // บันทึกคนรับงาน

            // กรณีรับงานที่ถูกเลื่อน (เริ่มงานตามวันนัดหมายใหม่)
            if (isset($_POST['new_work_date']) && !empty($_POST['new_work_date'])) {
                $newWorkDate = $conn->real_escape_string($_POST['new_work_date']);
                $updateFields[] = "work_date = '$newWorkDate'"; 
                $updateFields[] = "requested_due_date = NULL";   
                $updateFields[] = "extension_status = 'approved'";
            }
        }

        // --- จบงาน (Approved) ---
        if ($status === 'approved') {
            $finalCost = isset($_POST['final_cost']) ? floatval($_POST['final_cost']) : 0;
            $updateFields[] = "final_cost = $finalCost";
            $updateFields[] = "completed_at = '$currentTime'";
            $updateFields[] = "completed_by = '$action_by'";

            // จัดการรูปภาพ (รองรับหลายรูป)
            $uploadedFiles = [];
            $targetDir = "uploads/complete_img/";
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

            // ตรวจสอบว่ามีการส่งไฟล์มาไหม (รองรับทั้งแบบ array และ single)
            if (isset($_FILES['completion_image'])) {
                $files = $_FILES['completion_image'];
                
                // แปลงโครงสร้าง $_FILES ให้วนลูปง่ายขึ้น
                if (!is_array($files['name'])) {
                    // กรณีส่งมาไฟล์เดียว (Single File)
                    $fileArray = [['name'=>$files['name'], 'tmp_name'=>$files['tmp_name'], 'error'=>$files['error']]];
                } else {
                    // กรณีส่งมาหลายไฟล์ (Multiple Files)
                    $fileArray = [];
                    $count = count($files['name']);
                    for($i=0; $i<$count; $i++) {
                        $fileArray[] = [
                            'name' => $files['name'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i]
                        ];
                    }
                }

                foreach($fileArray as $i => $file) {
                    if ($file['error'] === 0) {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $newFilename = "comp_" . time() . "_" . uniqid() . "." . $ext;
                        $targetPath = $targetDir . $newFilename;
                        
                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $uploadedFiles[] = $newFilename; // เก็บแค่ชื่อไฟล์
                        }
                    }
                }
            }

            if (!empty($uploadedFiles)) {
                $imgsStr = implode(',', $uploadedFiles);
                $updateFields[] = "completion_image = '$imgsStr'";
            }
        }

        $sql = "UPDATE daily_reports SET " . implode(', ', $updateFields) . " WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'No action matched']);
}
////////////////////////////////////////////////////////////////สิ้นสุดของเดียร์



// ==========================================
// 18. SUBMIT ADMIN REPORT (ฉบับสมบูรณ์: บันทึกครบทุกส่วน)
// ==========================================
else if ($action == 'submit_admin_report') {
    
    function getPostJsonSafe($key) {
        if (!isset($_POST[$key])) return [];
        if (is_array($_POST[$key])) return $_POST[$key];
        $clean = stripslashes($_POST[$key]);
        $decoded = json_decode($clean, true);
        if ($decoded === null) $decoded = json_decode($_POST[$key], true);
        return is_array($decoded) ? $decoded : [];
    }

    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $reporter_name = $_POST['reporter_name'] ?? 'Unknown';
    $note = $_POST['note'] ?? '';
    
    // รับข้อมูล Array
    $adminItems = getPostJsonSafe('adminItems');
    $prItems    = getPostJsonSafe('prItems');
    $jobItems   = getPostJsonSafe('jobItems');
    $bgItems    = getPostJsonSafe('bgItems');
    $stampItems = getPostJsonSafe('stampItems');
    $totals     = getPostJsonSafe('totals'); 

    // คำนวณยอดรวม (Grand Total)
    $grand_total = floatval($totals['net'] ?? 0);
    // (หมายเหตุ: ยอด net จากแอป คือรวมทุกอย่างมาแล้ว ไม่ต้องบวกซ้ำ)

    // --- 1. Admin Expense ---
    $has_exp = count($adminItems) > 0 ? 1 : 0;
    
    $exp_doc = []; $exp_comp = []; $exp_dept = []; $exp_proj = [];
    $exp_accom = []; $exp_labor = []; 
    $exp_other_desc = []; $exp_other_amt = [];
    $exp_files = []; $exp_other_files = [];

    $uploaded_accom = uploadMultipleFiles('admin_accom_files', 'uploads/admin/');
    $uploaded_other = uploadMultipleFiles('admin_other_files', 'uploads/admin/');

    foreach ($adminItems as $index => $item) {
        $exp_doc[] = is_array($item['docRefs']) ? implode(', ', $item['docRefs']) : ($item['docRefs'] ?? '');
        $exp_comp[] = $item['company'] ?? '-';
        $exp_dept[] = $item['department'] ?? '-';
        $exp_proj[] = $item['project'] ?? '-';
        $exp_accom[] = $item['accommodationCost'] ?? 0;
        $exp_labor[] = $item['laborCost'] ?? 0;
        $exp_other_desc[] = $item['otherDesc'] ?? '';
        $exp_other_amt[] = $item['otherAmount'] ?? 0;

        $exp_files[] = isset($uploaded_accom[$index]) ? $uploaded_accom[$index] : '';
        $exp_other_files[] = isset($uploaded_other[$index]) ? $uploaded_other[$index] : '';
    }

    // --- 2. Other Sections (Prepare Data) ---
    // PR
    $has_pr = count($prItems) > 0 ? 1 : 0;
    $pr_dept = []; $pr_proj = []; $pr_budget = [];
    foreach ($prItems as $item) { 
        $pr_dept[] = $item['department'] ?? ''; 
        $pr_proj[] = $item['project'] ?? ''; 
        $pr_budget[] = $item['budget'] ?? 0; 
    }

    // Job
    $has_job = count($jobItems) > 0 ? 1 : 0;
    $job_num = []; $job_dept = []; $job_proj = []; $job_budget = [];
    foreach ($jobItems as $item) { 
        $job_num[] = $item['jobNumber'] ?? '';
        $job_dept[] = $item['department'] ?? ''; 
        $job_proj[] = $item['project'] ?? ''; 
        $job_budget[] = $item['budget'] ?? 0; 
    }

    // BG
    $has_bg = count($bgItems) > 0 ? 1 : 0;
    $bg_dept = []; $bg_proj = []; $bg_amt = [];
    foreach ($bgItems as $item) { 
        $bg_dept[] = $item['department'] ?? ''; 
        $bg_proj[] = $item['project'] ?? ''; 
        $bg_amt[] = $item['amount'] ?? 0; 
    }

    // Stamp
    $has_stamp = count($stampItems) > 0 ? 1 : 0;
    $st_dept = []; $st_proj = []; $st_cost = [];
    foreach ($stampItems as $item) { 
        $st_dept[] = $item['department'] ?? ''; 
        $st_proj[] = $item['project'] ?? ''; 
        $st_cost[] = $item['cost'] ?? 0; 
    }

    // --- 3. SQL Insert ---
    // เปลี่ยนค่า 0 เป็น ? ให้หมด เพื่อรับค่า dynamic
    $sql = "INSERT INTO report_admin 
    (report_date, reporter_name, note, total_amount,
     has_expense, exp_company, exp_dept, exp_proj, exp_doc, exp_accom, exp_labor, exp_file, 
     exp_other_desc, exp_other_amount, exp_other_file,
     has_pr, pr_dept, pr_proj, pr_budget,
     has_job, job_num, job_dept, job_proj, job_budget,
     has_bg, bg_dept, bg_proj, bg_amount,
     has_stamp, stamp_dept, stamp_proj, stamp_cost, created_at) 
    VALUES 
    (?, ?, ?, ?, 
     ?, ?, ?, ?, ?, ?, ?, ?, 
     ?, ?, ?,
     ?, ?, ?, ?, 
     ?, ?, ?, ?, ?, 
     ?, ?, ?, ?, 
     ?, ?, ?, ?, NOW())";

    if ($stmt = $conn->prepare($sql)) {
        // Encode JSON
        $j_exp_comp = json_encode($exp_comp, JSON_UNESCAPED_UNICODE);
        $j_exp_dept = json_encode($exp_dept, JSON_UNESCAPED_UNICODE);
        $j_exp_proj = json_encode($exp_proj, JSON_UNESCAPED_UNICODE);
        $j_exp_doc  = json_encode($exp_doc, JSON_UNESCAPED_UNICODE);
        $j_exp_accom = json_encode($exp_accom);
        $j_exp_labor = json_encode($exp_labor);
        $j_exp_file  = json_encode($exp_files, JSON_UNESCAPED_UNICODE);
        $j_exp_odesc = json_encode($exp_other_desc, JSON_UNESCAPED_UNICODE);
        $j_exp_oamt  = json_encode($exp_other_amt);
        $j_exp_ofile = json_encode($exp_other_files, JSON_UNESCAPED_UNICODE);

        $j_pr_dept = json_encode($pr_dept, JSON_UNESCAPED_UNICODE);
        $j_pr_proj = json_encode($pr_proj, JSON_UNESCAPED_UNICODE);
        $j_pr_budg = json_encode($pr_budget);

        $j_job_num  = json_encode($job_num, JSON_UNESCAPED_UNICODE);
        $j_job_dept = json_encode($job_dept, JSON_UNESCAPED_UNICODE);
        $j_job_proj = json_encode($job_proj, JSON_UNESCAPED_UNICODE);
        $j_job_budg = json_encode($job_budget);

        $j_bg_dept = json_encode($bg_dept, JSON_UNESCAPED_UNICODE);
        $j_bg_proj = json_encode($bg_proj, JSON_UNESCAPED_UNICODE);
        $j_bg_amt  = json_encode($bg_amt);

        $j_st_dept = json_encode($st_dept, JSON_UNESCAPED_UNICODE);
        $j_st_proj = json_encode($st_proj, JSON_UNESCAPED_UNICODE);
        $j_st_cost = json_encode($st_cost);

        // Bind Params (32 ตัว - ถูกต้องแน่นอน)
        // sssdissssssssssisssissssisssisss
        $stmt->bind_param("sssdissssssssssisssissssisssisss", 
            $report_date, $reporter_name, $note, $grand_total,              
            $has_exp, $j_exp_comp, $j_exp_dept, $j_exp_proj, $j_exp_doc, $j_exp_accom, $j_exp_labor, $j_exp_file, 
            $j_exp_odesc, $j_exp_oamt, $j_exp_ofile,
            $has_pr, $j_pr_dept, $j_pr_proj, $j_pr_budg,        
            $has_job, $j_job_num, $j_job_dept, $j_job_proj, $j_job_budg, 
            $has_bg, $j_bg_dept, $j_bg_proj, $j_bg_amt,           
            $has_stamp, $j_st_dept, $j_st_proj, $j_st_cost 
        );

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "บันทึกรายงานเรียบร้อย"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Execute Failed: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Prepare Failed: " . $conn->error]);
    }
}

// ==========================================================
// 19. GET ADMIN DASHBOARD (Update: เพิ่ม Other + Filter)
// ==========================================================
else if ($action == 'get_admin_dashboard') {
    $today = date('Y-m-d');
    
    // ✅ 1. รับค่า Filter จาก App (ถ้าไม่ส่งมา ให้ใช้ค่า Default คือเดือนปัจจุบัน)
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date   = $_GET['end_date']   ?? date('Y-m-t');
    $reporter   = $_GET['reporter']   ?? '';

    // สร้าง WHERE clause สำหรับกรองข้อมูล
    $where_sql = "WHERE report_date BETWEEN '$start_date' AND '$end_date'";
    if (!empty($reporter)) {
        $where_sql .= " AND reporter_name = '" . $conn->real_escape_string($reporter) . "'";
    }

    // 2. สรุปยอดวันนี้ (Today) - *วันนี้ไม่เกี่ยวกับ Filter วันที่*
    $sql_today = "SELECT COUNT(*) as count, SUM(total_amount) as total FROM report_admin WHERE report_date = '$today'";
    // แต่ถ้ามีการกรองพนักงาน ต้องกรองยอดวันนี้ของพนักงานคนนั้นด้วย
    if (!empty($reporter)) {
        $sql_today .= " AND reporter_name = '" . $conn->real_escape_string($reporter) . "'";
    }
    $res_today = $conn->query($sql_today)->fetch_assoc();

    // 3. เตรียมตัวแปร KPI
    // ✅ เพิ่ม key 'other' เข้าไป
    $kpi = ['accom'=>0, 'labor'=>0, 'pr'=>0, 'job'=>0, 'bg'=>0, 'stamp'=>0, 'other'=>0, 'docs'=>0];

    // Helper Function (เหมือนเดิม)
    function sumJsonStr($str) {
        if(empty($str)) return 0;
        $clean_str = stripslashes($str);
        $arr = json_decode($clean_str, true);
        if ($arr === null) $arr = json_decode($str, true);
        if(!is_array($arr)) $arr = explode(',', $str);
        if (!is_array($arr)) return 0;
        return array_sum(array_map(function($v){ return floatval(trim($v)); }, $arr));
    }
    function apiSumDocs($str) {
    if(empty($str)) return 0;
    // แปลง JSON หรือ String เป็น Array
    $arr = json_decode($str, true);
    if(!is_array($arr)) $arr = explode(',', $str);
    
    $total = 0;
    foreach($arr as $item) {
        // Regex หาตัวเลขหลัง : และก่อน )
        // เช่น "AX 123 (รายการ : 500)" -> ได้ 500
        if(preg_match('/:\s*([\d,\.]+)\s*\)/', $item, $matches)) {
            $total += floatval(str_replace(',', '', $matches[1]));
        }
    }
    return $total;
}

    // 4. Query ข้อมูลตาม Filter มาคำนวณ
    $sql_kpi_data = "SELECT * FROM report_admin $where_sql";
    $res_kpi = $conn->query($sql_kpi_data);

    $month_total = 0; // ยอดรวมทั้งหมดในช่วงเวลาที่เลือก

    if ($res_kpi) {
        while ($row = $res_kpi->fetch_assoc()) {
            $row_total = 0;

            // --- Admin Expense ---
            if ($row['has_expense']) {
                $accom = sumJsonStr($row['exp_accom']);
                
                $raw_labor = sumJsonStr($row['exp_labor']);
                $labor_net = ($raw_labor * 0.97); // หัก 3%
                
                // ✅ คำนวณค่าใช้จ่ายอื่นๆ
                $other = sumJsonStr($row['exp_other_amount']);

                // บวกเข้า KPI รวม
                $kpi['accom'] += $accom;
                $kpi['labor'] += $labor_net;
                $kpi['other'] += $other; // ✅ เพิ่มบรรทัดนี้
                $kpi['docs'] += apiSumDocs($row['exp_doc']);
                
                $row_total += ($accom + $labor_net + $other);
            }

            // --- Generic Sections ---
            if ($row['has_pr']) {
                $val = sumJsonStr($row['pr_budget']);
                $kpi['pr'] += $val;
                $row_total += $val;
            }
            if ($row['has_job']) {
                $val = sumJsonStr($row['job_budget']);
                $kpi['job'] += $val;
                $row_total += $val;
            }
            if ($row['has_bg']) {
                $val = sumJsonStr($row['bg_amount']);
                $kpi['bg'] += $val;
                $row_total += $val;
            }
            if ($row['has_stamp']) {
                $val = sumJsonStr($row['stamp_cost']);
                $kpi['stamp'] += $val;
                $row_total += $val;
            }
            
            $month_total += $row_total;
        }
    }

    // 5. รายการล่าสุด 20 รายการ (ต้องกรองตาม Filter ด้วยไหม? ปกติ Dashboard มักโชว์ล่าสุดโดยไม่สน Filter หรือจะเอาตาม Filter ก็ได้)
    // ในที่นี้ขอใช้ตาม Filter เพื่อให้ข้อมูลสอดคล้องกันครับ
    $recent = [];
    $sql_list = "SELECT * FROM report_admin $where_sql ORDER BY report_date DESC, created_at DESC LIMIT 20";
    $result = $conn->query($sql_list);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // แปลงเป็น boolean เพื่อให้ App ใช้ง่าย
            $row['has_expense'] = (bool)$row['has_expense'];
            $row['has_pr']      = (bool)$row['has_pr'];
            $row['has_job']     = (bool)$row['has_job'];
            $row['has_bg']      = (bool)$row['has_bg'];
            $row['has_stamp']   = (bool)$row['has_stamp'];
            $recent[] = $row;
        }
    }

    echo json_encode([
        "status" => "success",
        "summary" => [
            "today_count" => intval($res_today['count'] ?? 0),
            "today_total" => floatval($res_today['total'] ?? 0),
            "month_count" => $res_kpi->num_rows, 
            "month_total" => $month_total 
        ],
        "kpi" => $kpi, 
        "recent" => $recent
    ]);
}
// ==========================================================
// 20. GET ACTIVE REPORTERS (ดึงรายชื่อเฉพาะคนที่มีรายงานส่งเข้ามา)
// ==========================================================
else if ($action == 'get_active_reporters') {
    // เลือกชื่อที่ไม่ซ้ำกัน (DISTINCT) จากตาราง report_admin
    $sql = "SELECT DISTINCT reporter_name FROM report_admin WHERE reporter_name IS NOT NULL AND reporter_name != '' ORDER BY reporter_name ASC";
    $result = $conn->query($sql);
    
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row['reporter_name'];
        }
    }
    
    echo json_encode($data); // ส่งกลับเป็น Array: ["สมชาย", "สมหญิง", ...]
}
// ==========================================
// 21. GET PLATFORMS (ดึงรายชื่อแพลตฟอร์มพร้อมรูป)
// ==========================================
else if ($action == 'get_marketing_platforms') {
    $platforms = [];
    $sql = "SELECT platform_name, platform_image FROM marketing_platforms";
    $result = $conn->query($sql);
    
    // สร้าง Base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']) . "uploads/platforms/";

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $name = trim($row['platform_name']);
            $key = strtolower($name); // ใช้ตัวพิมพ์เล็กเป็น Key
            $img = $row['platform_image'];
            $full_url = null;

            // 1. เช็คจาก DB
            if (!empty($img) && file_exists("uploads/platforms/" . $img)) {
                $full_url = $base_url . $img;
            }
            
            // 2. ถ้า DB ไม่มี ให้ลองหาจากชื่อร้าน (Auto Detect)
            if ($full_url === null) {
                // รองรับ .png และ .jpg
                $possible_names = [$name . ".png", $name . ".jpg"];
                foreach ($possible_names as $fname) {
                    if (file_exists("uploads/platforms/" . $fname)) {
                        $full_url = $base_url . rawurlencode($fname);
                        break;
                    }
                }
            }

            // ส่งกลับเป็น Map: "shopee" => "http://.../Shopee.png"
            $platforms[$key] = $full_url;
        }
    }
    echo json_encode($platforms); // ส่งกลับเป็น Object JSON
    exit();
}
// ==========================================================
// 22. GET MARKETING ACTIVE REPORTERS (ดึงรายชื่อคนที่มีข้อมูลการตลาดจริง)
// ==========================================================
else if ($action == 'get_marketing_active_reporters') {
    // ดึงชื่อที่ไม่ซ้ำกันจากตารางการตลาดออนไลน์
    $sql = "SELECT DISTINCT reporter_name 
            FROM report_online_marketing 
            WHERE reporter_name IS NOT NULL AND reporter_name != '' 
            ORDER BY reporter_name ASC";
            
    $result = $conn->query($sql);
    $data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row['reporter_name'];
        }
    }
    // ส่งกลับเป็น Array ของรายชื่อ
    echo json_encode($data);
}
// ==========================================
// 25. GET DYNAMIC MENUS (แก้ไข: เช็คจาก Action Code ตาม StaffHistory.php)
// ==========================================
else if ($action == 'get_menus') {
    header('Content-Type: application/json');

    $role = isset($_GET['role']) ? trim($_GET['role']) : ''; 
    $safe_role = $conn->real_escape_string($role);

    // 1. ดึง Action Code ที่ Role นี้ทำได้ (จากตาราง role_actions)
    $allowed_actions = [];
    
    // ถ้าเป็น Admin ให้ผ่านหมด (หรือจะให้ดึงจริงก็ได้ แต่ปกติ Admin จะเห็นหมด)
    if (strtolower($role) == 'admin') {
        $allowed_actions = ['ALL'];
    } else {
        // ดึงข้อมูลจากตาราง role_actions (ที่ ManagePermissions.php บันทึกไว้)
        $sql = "SELECT action_code FROM role_actions WHERE role_name = '$safe_role'";
        $res = $conn->query($sql);
        if ($res) {
            while($r = $res->fetch_assoc()) {
                $allowed_actions[] = $r['action_code'];
            }
        }
    }

    // 2. กำหนดปุ่ม โดยใช้ "requiredAction" ให้ตรงกับ StaffHistory.php
    $all_menus = [
        [
            "id" => "sales",
            "label" => "ฝ่ายขาย (Sales)",
            "subLabel" => "ประวัติและรายงานการขาย",
            "icon" => "briefcase",
            "color" => "#4f46e5",
            "route" => "/history/sales",
            "requiredAction" => "view_sales_tab"  // ✅ เช็คตัวนี้
        ],
        [
            "id" => "purchase",
            "label" => "ฝ่ายจัดซื้อ (Purchase)",
            "subLabel" => "ประวัติการสั่งซื้อ",
            "icon" => "shopping-cart",
            "color" => "#059669",
            "route" => "/history/purchase",
            "requiredAction" => "view_purchase_tab" // ✅ เช็คตัวนี้
        ],
        [
            "id" => "marketing",
            "label" => "การตลาด (Marketing)",
            "subLabel" => "ประวัติงานออนไลน์",
            "icon" => "bullhorn", 
            "color" => "#6366f1",
            "route" => "/history/marketing",
            "requiredAction" => "view_marketing_tab" // ✅ เช็คตัวนี้
        ],
        [
            "id" => "admin",
            "label" => "ธุรการ (Admin)",
            "subLabel" => "งานเอกสารทั่วไป",
            "icon" => "folder-open",
            "color" => "#f97316",
            "route" => "/history/admin",
            "requiredAction" => "view_admin_tab" // ✅ เช็คตัวนี้
        ]
    ];

    // 3. กรองปุ่ม (เช็คว่ามี Action Code นั้นๆ หรือไม่)
    $my_menus = [];
    foreach ($all_menus as $menu) {
        // ถ้าเป็น admin หรือ มี Action Code นั้นอยู่ในรายการที่อนุญาต
        if (in_array('ALL', $allowed_actions) || in_array($menu['requiredAction'], $allowed_actions)) {
            $my_menus[] = $menu;
        }
    }

    echo json_encode($my_menus);
    exit();
}
// ==========================================
// 26. GET MANAGER DASHBOARD MENUS (ดึงเมนูผู้บริหารตามสิทธิ์)
// ==========================================
else if ($action == 'get_manager_menus') {
    header('Content-Type: application/json');

    $role = isset($_GET['role']) ? trim($_GET['role']) : ''; 
    $safe_role = $conn->real_escape_string($role);

    // 1. ดึงรายชื่อไฟล์ที่ Role นี้มีสิทธิ์ (Logic เดียวกับหน้า ManagePermissions)
    $allowed_files = [];
    
    // ถ้าเป็น Admin ให้เห็นหมด
    if (strtolower($role) == 'admin') {
        $allowed_files = ['ALL'];
    } else {
        $sql = "SELECT mp.file_name 
                FROM permissions p 
                JOIN master_pages mp ON p.page_id = mp.id 
                WHERE p.role_name = '$safe_role'";
                
        $res = $conn->query($sql);
        if ($res) {
            while($r = $res->fetch_assoc()) {
                $allowed_files[] = $r['file_name'];
            }
        }
    }

    // 2. กำหนดรายการเมนู Dashboard ทั้งหมด (และไฟล์ที่ต้องใช้ในการเข้าถึง)
    // 📌 ตรงนี้สำคัญ! ชื่อ requiredFile ต้องตรงกับที่พี่กำหนดในหน้าเว็บ (Database)
    $dashboard_menus = [
        [
            "id" => "sales",
            "label" => "ฝ่ายขาย",
            "icon" => "chart-line",
            "color" => "#4e54c8",
            "route" => "/(tabs)/ManagerSales",
            "requiredFile" => "Dashboard.php" // ⚠️ แก้ชื่อไฟล์ให้ตรงกับ DB ของฝ่ายขาย
        ],
        [
            "id" => "purchase",
            "label" => "ฝ่ายจัดซื้อ",
            "icon" => "shopping-cart",
            "color" => "#059669",
            "route" => "/(tabs)/ManagerPurchase",
            "requiredFile" => "Dashboard_Purchase.php" // ⚠️ แก้ชื่อไฟล์ให้ตรงกับ DB ของฝ่ายจัดซื้อ
        ],
        [
            "id" => "marketing",
            "label" => "การตลาด",
            "icon" => "bullhorn",
            "color" => "#6366f1",
            "route" => "/(tabs)/ManagerMarketing",
            "requiredFile" => "Dashboard_Marketing.php" // ⚠️ แก้ชื่อไฟล์ให้ตรงกับ DB ของการตลาด
        ],
        [
            "id" => "admin",
            "label" => "ฝ่ายธุรการ",
            "icon" => "building",
            "color" => "#e11d48",
            "route" => "/(tabs)/AdminDashboard",
            "requiredFile" => "Dashboard_Admin.php" // ⚠️ แก้ชื่อไฟล์ให้ตรงกับ DB ของธุรการ
        ]
    ];

    // 3. กรองเมนูที่จะส่งกลับไปให้แอป
    $my_menus = [];
    foreach ($dashboard_menus as $menu) {
        // ถ้าเป็น Admin หรือ มีไฟล์นั้นอยู่ในสิทธิ์ที่ดึงมา
        if (in_array('ALL', $allowed_files) || in_array($menu['requiredFile'], $allowed_files)) {
            $my_menus[] = $menu;
        }
    }

    echo json_encode($my_menus);
    exit();
}



// ==========================================================
// 27. BOSS COMMAND: SUBMIT TASK (สั่งงาน)
// ==========================================================
else if ($action == 'submit_boss_task') {
    $task_id = $_POST['task_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $assigned_to = $_POST['assigned_to'] ?? '';
    $due_date = $_POST['due_date'] ?? ''; 
    $created_by = $_POST['created_by'] ?? 'App User'; 

    $current_time = date('Y-m-d H:i:s'); // ใช้เวลาจาก Server PHP

    if (empty($title) || empty($assigned_to)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit();
    }

    $sql = "INSERT INTO tasks (task_id, title, description, assigned_to, due_date, created_by, status, assign_date, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'มอบหมาย', ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssss", $task_id, $title, $description, $assigned_to, $due_date, $created_by, $current_time, $current_time);
        
        if ($stmt->execute()) {
             // ฟังก์ชันอัปโหลดไฟล์ (ถ้าคุณมีฟังก์ชันนี้อยู่แล้วในไฟล์ API หลัก)
             if (function_exists('uploadMultipleFiles')) {
                uploadMultipleFiles('attachments', 'uploads/tasks/'); 
             }
            echo json_encode(['status' => 'success', 'message' => 'มอบหมายงานเรียบร้อยแล้ว']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Prepare Failed']);
    }
}

// ==========================================================
// 28. BOSS COMMAND: GET INITIAL DATA (ดึงข้อมูลบริษัท/พนักงาน/TaskID)
// ==========================================================
else if ($action == 'get_boss_data') {
    $response = [];
    
    // 1. Gen Task ID
    $year = date("Y");
    $task_id_prefix = "T-" . $year;
    $task_id = $task_id_prefix . "001"; 
    $sql_last_id = "SELECT task_id FROM tasks WHERE task_id LIKE '$task_id_prefix%' ORDER BY id DESC LIMIT 1";
    $res_id = $conn->query($sql_last_id);
    if ($res_id && $res_id->num_rows > 0) {
        $row_id = $res_id->fetch_assoc();
        $last_num = (int)substr($row_id['task_id'], -3);
        $task_id = $task_id_prefix . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    }
    $response['task_id'] = $task_id;

    // 2. Companies
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']);
    
    $company_list = [];
    $sql_comp = "SELECT id, company_name, logo_file FROM companies ORDER BY FIELD(id, 6, 2, 3, 5)";
    $res_comp = $conn->query($sql_comp);
    if ($res_comp) {
        while ($row = $res_comp->fetch_assoc()) {
            $row['logo_url'] = !empty($row['logo_file']) ? $base_url . 'uploads/logos/' . $row['logo_file'] : $base_url . 'uploads/logos/default_company.png';
            $company_list[] = $row;
        }
    }
    $response['companies'] = $company_list;

    // 3. Employees
    $employee_list = [];
    $sql_emp = "SELECT u.fullname, u.company_id, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id ORDER BY u.fullname ASC";
    $res_emp = $conn->query($sql_emp);
    if ($res_emp) {
        while ($row = $res_emp->fetch_assoc()) {
            $employee_list[] = [
                'value' => $row['fullname'], 
                'label' => $row['fullname'], 
                'company_id' => $row['company_id'],
                'company_name' => $row['company_name'] ?? '-'
            ];
        }
    }
    $response['employees'] = $employee_list;

    echo json_encode($response);
}

// ==========================================================
// 34. BOSS DASHBOARD: GET STATS & LIST (หน้าหลักผู้บริหาร)
// ==========================================================
else if ($action == 'get_boss_dashboard') {
    $role = $_GET['role'] ?? 'staff';
    $user_name = $_GET['user_name'] ?? '';
    
    $where = [];
    // ถ้าไม่ใช่ Admin/CEO เห็นเฉพาะงานตัวเอง
    if ($role !== 'admin' && strtoupper($role) !== 'CEO') {
        $where[] = "t.assigned_to = '" . $conn->real_escape_string($user_name) . "'";
    }
    
    if (!empty($_GET['status'])) $where[] = "t.status = '" . $conn->real_escape_string($_GET['status']) . "'";
    // กรองวันที่ (ถ้าส่งมา)
    if (!empty($_GET['date'])) $where[] = "DATE(t.assign_date) = '" . $conn->real_escape_string($_GET['date']) . "'";

    $sql_where = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";

    // 1. Stats
    $sql_stats = "SELECT 
        COUNT(DISTINCT t.id) as total,
        SUM(CASE WHEN t.status = 'มอบหมาย' THEN 1 ELSE 0 END) as ordered,
        SUM(CASE WHEN t.status = 'ดำเนินการ' THEN 1 ELSE 0 END) as process,
        SUM(CASE WHEN t.status = 'สำเร็จ' THEN 1 ELSE 0 END) as success
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.fullname 
    LEFT JOIN companies c ON u.company_id = c.id
    $sql_where";
    
    $stats = $conn->query($sql_stats)->fetch_assoc();

    // 2. Task List
    $sql_list = "SELECT t.*, u.fullname as assignee_name, 
        COALESCE(c.company_name, t.company) as company_display,
        u.avatar
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.fullname 
    LEFT JOIN companies c ON u.company_id = c.id
    $sql_where 
    GROUP BY t.id
    ORDER BY t.created_at DESC LIMIT 50";

    $result = $conn->query($sql_list);
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $checkDate = !empty($row['assign_date']) ? $row['assign_date'] : $row['created_at'];
        $row['assign_date_fmt'] = date('d M Y', strtotime($checkDate));
        
        $row['avatar_url'] = !empty($row['avatar']) 
            ? "http://" . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']) . "uploads/profiles/" . $row['avatar'] 
            : null;

        $tasks[] = $row;
    }

    echo json_encode(['status' => 'success', 'stats' => $stats, 'tasks' => $tasks]);
}

// ==========================================================
// 30. UPDATE TASK STATUS (อัปเดตสถานะงาน)
// ==========================================================
else if ($action == 'update_task_status') {
    // รับได้ทั้ง JSON และ POST
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    if (!$input) $input = $_POST;

    $task_id    = $input['task_id'] ?? '';
    $new_status = $input['status'] ?? '';
    $updated_by = $input['updated_by'] ?? 'System';

    if (empty($task_id) || empty($new_status)) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit();
    }

    $sql = "";
    if ($new_status == 'ดำเนินการ') {
        $sql = "UPDATE tasks SET status = ?, started_at = NOW(), updated_by = ? WHERE task_id = ?";
    } elseif ($new_status == 'สำเร็จ') {
        $sql = "UPDATE tasks SET status = ?, completed_at = NOW(), updated_by = ? WHERE task_id = ?";
    } else {
        $sql = "UPDATE tasks SET status = ?, updated_by = ? WHERE task_id = ?";
    }

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sss", $new_status, $updated_by, $task_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => "สถานะอัปเดตเป็น: $new_status"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'SQL Error']);
    }
}
$conn->close();
?>