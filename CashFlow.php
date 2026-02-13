<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['role'])) { header("Location: login.php"); exit(); }

// ✅ ป้องกัน Error กรณี auth.php ยังไม่มีฟังก์ชันนี้
if (!function_exists('hasAction')) {
    function hasAction($action) { return true; } 
}

// --- 1. ดึงรายชื่อบริษัท + โลโก้ ---
$all_companies = [];
$company_logos_db = []; 

$sql_get_companies = "SELECT company_name, logo_file FROM companies ORDER BY id ASC";
$result_companies = $conn->query($sql_get_companies);

if ($result_companies) {
    while($row_c = $result_companies->fetch_assoc()) {
        $all_companies[] = $row_c['company_name'];
        $company_logos_db[$row_c['company_name']] = $row_c['logo_file'];
    }
}

// --- รับค่า Filter วันที่ ---
$is_all_mode = true;
$start_date = '2000-01-01'; 
$end_date = date('Y-12-31'); 

if (isset($_GET['start_date']) && !empty($_GET['start_date']) && isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $is_all_mode = false;
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

$date_condition = "trans_date BETWEEN '$start_date' AND '$end_date'";

function dateToThai($date) {
    if (!$date) return '-';
    $ts = strtotime($date);
    $year = date('Y', $ts) + 543;
    return date('d/m/', $ts) . $year;
}

// --- 2. บันทึกข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_trans'])) {
    if(hasAction('btn_add_cashflow')) { 
        $t_date = $_POST['trans_date'];
        $company = $_POST['company'];
        $type = $_POST['type'];
        $amount = floatval($_POST['amount']);
        $desc = $_POST['description'];
        $file_path = "";
        
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] == 0) {
            $target_dir = __DIR__ . "/uploads/receipts/";
            if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }
            $ext = pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION);
            $new_name = "rcp_" . date('Ymd_His') . "_" . rand(100,999) . "." . $ext;
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_dir . $new_name)) { $file_path = $new_name; }
        }
        
        $stmt = $conn->prepare("INSERT INTO cash_flow (trans_date, company, type, amount, description, receipt_file) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdss", $t_date, $company, $type, $amount, $desc, $file_path);
        if($stmt->execute()) { echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'success', title: 'บันทึกสำเร็จ', timer: 1000, showConfirmButton: false}).then(() => window.location.href='CashFlow.php'); });</script>"; }
    }
}

// --- 3. ลบข้อมูล ---
if (isset($_GET['del'])) {
    if(hasAction('btn_delete_cashflow')) {
        $id = intval($_GET['del']);
        $res = $conn->query("SELECT receipt_file FROM cash_flow WHERE id = $id");
        $row = $res->fetch_assoc();
        if($row && !empty($row['receipt_file'])) { $file = __DIR__ . "/uploads/receipts/" . $row['receipt_file']; if(file_exists($file)) @unlink($file); }
        $conn->query("DELETE FROM cash_flow WHERE id = $id");
    }
    header("Location: CashFlow.php"); exit();
}

// --- 4. คำนวณยอดรวม ---
$sum_in = $conn->query("SELECT SUM(amount) as total FROM cash_flow WHERE type='Income' AND $date_condition")->fetch_assoc()['total'] ?? 0;
$sum_out = $conn->query("SELECT SUM(amount) as total FROM cash_flow WHERE type='Expense' AND $date_condition")->fetch_assoc()['total'] ?? 0;
$sum_diff = $sum_in - $sum_out;

// --- 5. สรุปรายบริษัท ---
$company_data = [];
$sql_comp = "SELECT company, 
             SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) as total_in,
             SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) as total_out
             FROM cash_flow 
             WHERE $date_condition
             GROUP BY company";
$res_comp = $conn->query($sql_comp);
while($row = $res_comp->fetch_assoc()) {
    $company_data[$row['company']] = $row;
}

// --- 6. เตรียมกราฟ ---
$chart_labels = []; 
$chart_data_in = []; 
$chart_data_out = [];
$chart_data_diff = []; 

if (!empty($all_companies)) {
    foreach ($all_companies as $comp) {
        $short_name = str_replace(['บริษัท ', ' จำกัด', ' (มหาชน)', ' คอร์ปอเรชั่น'], '', $comp);
        $chart_labels[] = $short_name;
        
        $in = isset($company_data[$comp]) ? $company_data[$comp]['total_in'] : 0;
        $out = isset($company_data[$comp]) ? $company_data[$comp]['total_out'] : 0;
        $diff = $in - $out;
        
        $chart_data_in[] = $in;
        $chart_data_out[] = $out;
        $chart_data_diff[] = $diff;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกเงินเข้า-ออก - TJC System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* จัด Layout (สีให้ Global CSS จาก Sidebar คุมทั้งหมด) */
        .main-content { padding: 30px; min-height: 100vh; }
        .page-container { width: 100%; max-width: 1400px; margin: 0; }
        @media (min-width: 992px) { .main-content { margin-left: 100px; width: calc(100% - 270px); padding: 40px; } }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding-top: 80px; } }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { 
            background: var(--bg-card); 
            border-radius: 20px; padding: 25px; box-shadow: var(--shadow); 
            display: flex; justify-content: space-between; align-items: center; 
            border: 1px solid var(--border-color); transition: transform 0.2s; 
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { margin: 0; color: var(--text-muted); font-size: 1rem; font-weight: 500; }
        .stat-info .value { font-family: 'Prompt'; font-size: 2rem; font-weight: 600; margin-top: 5px; color: var(--text-main); }
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .sc-in { color: #10b981 !important; } .icon-in { background: #d1fae5; color: #059669; }
        .sc-out { color: #ef4444 !important; } .icon-out { background: #fee2e2; color: #b91c1c; }
        .sc-diff { color: #3b82f6 !important; } .icon-diff { background: #dbeafe; color: #2563eb; }

        /* Charts & Cards */
        .chart-card { 
            background: var(--bg-card); border-radius: 20px; padding: 25px; 
            box-shadow: var(--shadow); margin-bottom: 30px; 
            border: 1px solid var(--border-color); 
            transition: height 0.3s; position: relative; overflow: hidden; 
        }
        .chart-card.collapsed { height: auto; padding-bottom: 10px; }
        
        .card { 
            background: var(--bg-card); border-radius: 16px; 
            box-shadow: var(--shadow); margin-bottom: 30px; 
            border: 1px solid var(--border-color); overflow: hidden; 
        }
        .card-header { 
            padding: 20px 30px; background: var(--hover-bg); 
            border-bottom: 1px solid var(--border-color); 
            display: flex; justify-content: space-between; align-items: center; 
        }
        .card-title { font-family: 'Prompt'; font-size: 1.1rem; font-weight: 600; color: var(--text-main); margin: 0; }
        .card-body { padding: 30px; }

        /* Companies Grid */
        .comp-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        @media (max-width: 768px) { .comp-grid { grid-template-columns: 1fr; } }

        .comp-card { 
            background: var(--bg-card); border-radius: 16px; padding: 20px; 
            box-shadow: var(--shadow); border: 1px solid var(--border-color); 
            transition: all 0.3s ease; position: relative; overflow: hidden; 
        }
        .comp-card:hover { transform: translateY(-5px); border-color: var(--primary-color); }
        .comp-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed var(--border-color); }
        .comp-icon { width: 55px; height: 55px; background: var(--bg-input); border:1px solid var(--border-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--primary-color); overflow:hidden; padding: 5px; box-sizing: border-box; }
        .comp-icon img { width: 100%; height: 100%; object-fit: contain; }
        .comp-name { font-family: 'Prompt'; font-weight: 600; color: var(--text-main); font-size: 1rem; line-height: 1.4; }
        
        .comp-stats { display: flex; justify-content: space-between; text-align: center; }
        .stat-item { flex: 1; border-right: 1px solid var(--border-color); }
        .stat-item:last-child { border-right: none; }
        .stat-item small { display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 2px; }
        .stat-item span { font-family: 'Prompt'; font-weight: 600; font-size: 1.05rem; }
        .stat-in span { color: #10b981; }
        .stat-out span { color: #ef4444; }
        .stat-diff span { color: #3b82f6; }

        /* Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; white-space: nowrap; }
        thead th { padding: 15px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; background: var(--bg-card); }
        tbody tr { background: var(--bg-card); box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 10px; transition: 0.2s; }
        tbody tr:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
        tbody td { padding: 15px; vertical-align: middle; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); color: var(--text-main); }
        tbody tr td:first-child { border-left: 1px solid var(--border-color); border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        tbody tr td:last-child { border-right: 1px solid var(--border-color); border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        
        .badge { padding: 6px 12px; border-radius: 30px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .bg-in { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
        .bg-out { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* Forms */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; }
        .form-control { 
            width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); 
            border-radius: 10px; font-family: 'Sarabun'; box-sizing: border-box; 
            font-size: 1rem; transition:0.2s; background: var(--bg-input); color: var(--text-main);
        }
        .form-control:focus { outline: none; border-color: var(--primary-color); }
        
        .btn-primary { background: var(--primary-color); color: white !important; border: none; padding: 12px 30px; border-radius: 30px; cursor: pointer; font-weight: 600; font-size: 1rem; transition: transform 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); opacity: 0.9; }
        
        /* 3D Back Button */
        .btn-back-3d {
            background: var(--bg-card); color: var(--primary-color); border: 1px solid var(--border-color); 
            padding: 10px 25px; border-radius: 50px;
            font-family: 'Prompt', sans-serif; font-weight: 600; font-size: 0.95rem; cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; gap: 10px;
        }
        .btn-back-3d:hover { transform: translateY(-3px); background: var(--hover-bg); color: var(--primary-color); }

        /* Radio Options */
        .type-selector-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; width: 100%; }
        .type-option { position: relative; height: 100%; }
        .type-option input { position: absolute; opacity: 0; cursor: pointer; width: 100%; height: 100%; z-index: 2; }
        .type-card { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 15px; border: 2px solid var(--border-color); border-radius: 12px; cursor: pointer; transition: all 0.2s; font-weight: 600; color: var(--text-muted); background: var(--bg-card); box-sizing: border-box; height: 100%; }
        .type-option input:checked + .type-card.card-in { background: #ecfdf5; border-color: #10b981; color: #047857; }
        .type-option input:checked + .type-card.card-out { background: #fef2f2; border-color: #ef4444; color: #b91c1c; }

        .file-upload-box { border: 2px dashed var(--border-color); border-radius: 10px; padding: 15px; text-align: center; cursor: pointer; transition: 0.2s; background: var(--bg-input); }
        .file-upload-box:hover { border-color: var(--primary-color); background: var(--hover-bg); }
        .btn-view { padding: 5px 10px; background: #e0f2fe; color: #0284c7; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .btn-view:hover { background: #0284c7; color: white; }

        #entryForm { display: none; margin-top: 20px; }
        .text-right { text-align: right !important; }
        .text-left { text-align: left !important; }
        .text-center { text-align: center !important; }

        .filter-bar { display: flex; gap: 10px; align-items: center; background: var(--bg-card); padding: 15px; border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 20px; flex-wrap: wrap; }
        
        /* Chart Toggles */
        .chart-toggle { display: flex; gap: 5px; background: var(--hover-bg); padding: 4px; border-radius: 8px; align-items: center; }
        .chart-btn { border: none; background: transparent; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-family: 'Prompt'; font-size: 0.85rem; color: var(--text-muted); font-weight: 600; transition: 0.2s; }
        .chart-btn.active { background: var(--bg-card); color: var(--primary-color); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .chart-btn:hover { color: var(--primary-color); }
        .chart-btn-toggle-view { margin-left: 5px; color: #ef4444; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-container">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h2 style="margin:0; font-family:'Prompt'; color:var(--text-main); font-size:1.8rem;"><i class="fas fa-wallet" style="color:var(--primary-color);"></i> บันทึกเงินเข้า-ออก</h2>
                    <p style="margin:5px 0 0 0; color:var(--text-muted);">จัดการรายรับรายจ่ายประจำวัน</p>
                </div>
                
                <?php if(hasAction('btn_add_cashflow')): ?>
                    <button id="btn_add_cashflow" name="btn_add_cashflow" onclick="toggleMode(true)" class="btn-primary"><i class="fas fa-plus-circle"></i> เพิ่มรายการใหม่</button>
                <?php endif; ?>

            </div>

            <form method="GET" class="filter-bar" autocomplete="off"> <i class="fas fa-filter" style="color:var(--primary-color);"></i>
                <span style="font-weight:600; color:var(--text-main);">ช่วงเวลา:</span>
                
                <input type="hidden" name="start_date" id="filter_start_hidden" value="<?php echo $start_date; ?>">
                <input type="hidden" name="end_date" id="filter_end_hidden" value="<?php echo $end_date; ?>">
                
                <input type="text" class="form-control" id="filter_start_display" placeholder="วันเริ่มต้น" style="width:150px;" readonly>
                
                <span style="color:var(--text-main);">ถึง</span>
                
                <input type="text" class="form-control" id="filter_end_display" placeholder="วันสิ้นสุด" style="width:150px;" readonly>
                
                <button type="submit" class="btn-primary" style="padding: 8px 15px; font-size: 0.9rem;">ค้นหา</button>
                <a href="CashFlow.php" class="btn-view" style="color:var(--text-muted); background:transparent; border:1px solid var(--border-color); margin-left:10px;">ล้างค่า (ดูทั้งหมด)</a>
            </form>

            <div id="dashboardView">
                
                <div style="margin-bottom:15px; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-building" style="color:var(--primary-color); font-size:1.2rem;"></i>
                    <h3 style="margin:0; font-family:'Prompt'; color:var(--text-main); font-size:1.2rem;">สรุปยอดรายบริษัท</h3>
                </div>

                <div class="comp-grid">
                    <?php
                    if (!empty($all_companies)) {
                        foreach ($all_companies as $comp_name) {
                            $in_val = isset($company_data[$comp_name]['total_in']) ? $company_data[$comp_name]['total_in'] : 0;
                            $out_val = isset($company_data[$comp_name]['total_out']) ? $company_data[$comp_name]['total_out'] : 0;
                            $diff = $in_val - $out_val;
                            $diff_text = ($diff >= 0 ? '+' : '') . number_format($diff, 2);
                            $diff_color = $diff >= 0 ? '#3b82f6' : '#ef4444'; 

                            $logo_file = isset($company_logos_db[$comp_name]) ? $company_logos_db[$comp_name] : '';
                            $logo_path = "uploads/logos/" . $logo_file;
                            $logo_html = (!empty($logo_file) && file_exists($logo_path)) 
                                ? "<img src='$logo_path'>" 
                                : "<i class='fas fa-briefcase'></i>";

                            ?>
                            <div class="comp-card">
                                <div class="comp-header">
                                    <div class="comp-icon"><?php echo $logo_html; ?></div>
                                    <div class="comp-name"><?php echo $comp_name; ?></div>
                                </div>
                                <div class="comp-stats">
                                    <div class="stat-item stat-in">
                                        <small><i class="fas fa-arrow-up"></i> รายรับ</small>
                                        <span>+<?php echo number_format($in_val, 2); ?></span>
                                    </div>
                                    <div class="stat-item stat-out">
                                        <small><i class="fas fa-arrow-down"></i> รายจ่าย</small>
                                        <span>-<?php echo number_format($out_val, 2); ?></span>
                                    </div>
                                    <div class="stat-item stat-diff">
                                        <small><i class="fas fa-balance-scale"></i> ส่วนต่าง</small>
                                        <span style="color:<?php echo $diff_color; ?>"><?php echo $diff_text; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div style='grid-column: 1/-1; text-align:center; padding:30px; color:var(--text-muted); background:var(--bg-card); border-radius:16px;'>กรุณาเพิ่มข้อมูลบริษัทในเมนู 'จัดการบริษัท'</div>";
                    }
                    ?>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>รายรับรวม (<?php echo $is_all_mode ? 'ทั้งหมด' : dateToThai($start_date)." - ".dateToThai($end_date); ?>)</h3>
                            <div class="value sc-in">+<?php echo number_format($sum_in, 2); ?></div>
                        </div>
                        <div class="stat-icon icon-in"><i class="fas fa-arrow-up"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>รายจ่ายรวม (<?php echo $is_all_mode ? 'ทั้งหมด' : dateToThai($start_date)." - ".dateToThai($end_date); ?>)</h3>
                            <div class="value sc-out">-<?php echo number_format($sum_out, 2); ?></div>
                        </div>
                        <div class="stat-icon icon-out"><i class="fas fa-arrow-down"></i></div>
                    </div>
                    <?php 
                        $diff_color_class = $sum_diff >= 0 ? 'sc-diff' : 'sc-out';
                        $diff_icon_class = $sum_diff >= 0 ? 'icon-diff' : 'icon-out';
                    ?>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>ส่วนต่าง (Difference)</h3>
                            <div class="value <?php echo $diff_color_class; ?>">
                                <?php echo ($sum_diff >= 0 ? '+' : '') . number_format($sum_diff, 2); ?>
                            </div>
                        </div>
                        <div class="stat-icon <?php echo $diff_icon_class; ?>"><i class="fas fa-balance-scale"></i></div>
                    </div>
                </div>

                <div class="chart-card" id="chartCard">
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px; align-items:center;">
                        <h3 style="margin:0; color:var(--text-main); font-size:1.2rem; font-family:'Prompt'; display:flex; align-items:center; gap:10px;">
                            <span style="width:10px; height:25px; background:var(--primary-color); border-radius:5px; display:inline-block;"></span>
                            สถิติการเงิน
                        </h3>
                        <div class="chart-toggle">
                            <button class="chart-btn" id="btn-total" onclick="setChartMode('total')">📊 ภาพรวม</button>
                            <button class="chart-btn active" id="btn-company" onclick="setChartMode('company')">🏢 แยกบริษัท</button>
                            <button class="chart-btn chart-btn-toggle-view" onclick="toggleChartVisibility(this)" title="ซ่อน/แสดงกราฟ">
                                <i class="fas fa-chevron-up"></i>
                            </button>
                        </div>
                    </div>
                    <div id="chartContainer" style="height:350px; width:100%; transition: all 0.3s ease;">
                        <canvas id="cashFlowChart"></canvas>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div class="card-title"><i class="fas fa-list-ul"></i> รายการล่าสุด</div></div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th width="60" class="text-center">#</th>
                                    <th width="120" class="text-left">วันที่</th>
                                    <th class="text-left">บริษัท</th>
                                    <th class="text-left">รายละเอียด</th>
                                    <th class="text-center">ประเภท</th>
                                    <th class="text-right">จำนวนเงิน</th>
                                    <th class="text-center">ใบเสร็จ</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT * FROM cash_flow WHERE $date_condition ORDER BY trans_date DESC, id DESC LIMIT 50";
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    $i = 1;
                                    while($row = $result->fetch_assoc()) {
                                        $is_in = $row['type'] == 'Income';
                                        $badge = $is_in ? '<span class="badge bg-in"><i class="fas fa-arrow-up"></i> รายรับ</span>' : '<span class="badge bg-out"><i class="fas fa-arrow-down"></i> รายจ่าย</span>';
                                        $color = $is_in ? 'text-success' : 'text-danger';
                                        $sign = $is_in ? '+' : '-';
                                        echo "<tr>";
                                        echo "<td class='text-center' style='color:var(--text-muted);'>{$i}</td>";
                                        echo "<td>".dateToThai($row['trans_date'])."</td>";
                                        echo "<td style='font-weight:600; color:var(--text-main);'>{$row['company']}</td>";
                                        echo "<td style='color:var(--text-muted);'>{$row['description']}</td>";
                                        echo "<td class='text-center'>{$badge}</td>";
                                        echo "<td class='text-right' style='font-family:Prompt; font-weight:600; font-size:1.05rem;' class='$color'>{$sign} ".number_format($row['amount'], 2)."</td>";
                                        
                                        // 🔥 เช็คสิทธิ์ปุ่มดูใบเสร็จ
                                        echo "<td class='text-center'>";
                                        if(!empty($row['receipt_file'])) {
                                            if(hasAction('btn_view_receipt')) {
                                                echo "<a href='uploads/receipts/{$row['receipt_file']}' target='_blank' class='btn-view'><i class='fas fa-file-invoice'></i> ดูรูป</a>";
                                            }
                                        } else { 
                                            echo "<span style='color:var(--text-muted);'>-</span>"; 
                                        }
                                        echo "</td>";
                                        
                                        // 🔥 เช็คสิทธิ์ปุ่มลบ
                                        echo "<td class='text-center'>";
                                        if(hasAction('btn_delete_cashflow')){
                                            echo "<a href='?del={$row['id']}' onclick=\"return confirm('ยืนยันการลบข้อมูลนี้?');\" style='color:#ef4444; background:#fee2e2; width:35px; height:35px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; transition:0.2s;'><i class='fas fa-trash-alt'></i></a>";
                                        }
                                        echo "</td>";
                                        echo "</tr>";
                                        $i++;
                                    }
                                } else { echo "<tr><td colspan='8' class='text-center' style='padding:50px; color:var(--text-muted);'><i class='fas fa-folder-open' style='font-size:3rem; margin-bottom:10px;'></i><br>ไม่พบข้อมูลรายการในช่วงเวลานี้</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="entryForm" class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-pen-to-square"></i> แบบฟอร์มบันทึกข้อมูล</div>
                    <button onclick="toggleMode(false)" class="btn-back-3d">
                        <i class="fas fa-arrow-left"></i> กลับไปหน้าสรุป
                    </button>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-grid" style="margin-bottom:25px;">
                            <div>
                                <label>วันที่ทำรายการ</label>
                                <input type="hidden" name="trans_date" id="trans_date_hidden" value="<?php echo date('Y-m-d'); ?>">
                                <input type="text" id="trans_date_display" class="form-control" placeholder="เลือกวันที่" readonly required>
                            </div>
                            <div>
                                <label>บริษัท / หน่วยงาน</label>
                                <select name="company" class="form-control" required>
                                    <option value="" disabled selected>-- เลือกบริษัท --</option>
                                    <?php foreach ($all_companies as $c) { echo "<option value='$c'>$c</option>"; } ?>
                                </select>
                            </div>
                            <div>
                                <label>ประเภทรายการ</label>
                                <div class="type-selector-wrapper">
                                    <div class="type-option">
                                        <input type="radio" name="type" id="t_in" value="Income" checked>
                                        <div class="type-card card-in"><i class="fas fa-arrow-up"></i> รายรับ</div>
                                    </div>
                                    <div class="type-option">
                                        <input type="radio" name="type" id="t_out" value="Expense">
                                        <div class="type-card card-out"><i class="fas fa-arrow-down"></i> รายจ่าย</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-grid" style="margin-bottom:30px;">
                            <div><label>จำนวนเงิน (บาท)</label><input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required style="font-weight:bold; color:var(--primary-color);"></div>
                            <div><label>รายละเอียดเพิ่มเติม</label><input type="text" name="description" class="form-control" placeholder="ระบุรายละเอียดค่าใช้จ่าย..."></div>
                            <div>
                                <label>หลักฐานการโอน / ใบเสร็จ (ถ้ามี)</label>
                                <div class="file-upload-box" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem; color:var(--text-muted);"></i>
                                    <span style="color:var(--text-muted); margin-left:10px;">คลิกเพื่อแนบไฟล์ (รูปภาพ หรือ PDF)</span>
                                    <input type="file" name="receipt_file" id="fileInput" style="display:none;" accept="image/*,.pdf" onchange="this.previousElementSibling.innerText = this.files[0].name">
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <button type="submit" name="save_trans" class="btn-primary" style="width:200px;"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        // --- 1. ฟังก์ชันสลับหน้า (Dashboard <-> Form) ---
        function toggleMode(isAdding) {
            const dashboard = document.getElementById('dashboardView');
            const form = document.getElementById('entryForm');
            const addBtn = document.getElementById('btn_add_cashflow');
            const filterBar = document.querySelector('.filter-bar'); // ซ่อน Filter ตอนเพิ่มข้อมูลด้วยเพื่อความสะอาดตา

            if(isAdding) {
                dashboard.style.display = 'none';
                if(filterBar) filterBar.style.display = 'none';
                form.style.display = 'block';
                if(addBtn) addBtn.style.display = 'none'; 
            } else {
                dashboard.style.display = 'block';
                if(filterBar) filterBar.style.display = 'flex';
                form.style.display = 'none';
                if(addBtn) addBtn.style.display = 'block';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            
            // --- 2. ฟังก์ชันจัดการวันที่ (FIXED: แก้ปัญหาวันที่กระโดด) ---
            
            // ฟังก์ชันแปลง String 'YYYY-MM-DD' เป็น Date Object โดยไม่สน Timezone
            function parseDateInternal(dateStr) {
                if (!dateStr || dateStr === "") return null;
                const parts = dateStr.split('-');
                if (parts.length !== 3) return null;
                // สร้าง Date: ปี, เดือน(เริ่มที่ 0), วัน
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }

            // ฟังก์ชันอัปเดตค่าลง Input Hidden
            function updateDateInput(selectedDates, hiddenId) {
                if (selectedDates.length > 0) {
                    let date = selectedDates[0];
                    let yyyy = date.getFullYear();
                    let mm = String(date.getMonth() + 1).padStart(2, '0');
                    let dd = String(date.getDate()).padStart(2, '0');
                    document.getElementById(hiddenId).value = `${yyyy}-${mm}-${dd}`;
                } else {
                    document.getElementById(hiddenId).value = ""; 
                }
            }

            // Config ของ Flatpickr
            const fpConfig = {
                dateFormat: "d/m/Y",
                locale: "th",
                disableMobile: true,
                formatDate: (date, format, locale) => {
                    // แสดงผลเป็น พ.ศ.
                    return flatpickr.formatDate(date, format, locale).replace(date.getFullYear(), date.getFullYear() + 543);
                }
            };

            // รับค่าจาก PHP (ค่าจะเป็น ค.ศ. เช่น 2026-01-15)
            const isAllMode = <?php echo $is_all_mode ? 'true' : 'false'; ?>;
            const phpStartDate = "<?php echo $start_date; ?>";
            const phpEndDate = "<?php echo $end_date; ?>";

            // แปลงเป็น Date Object เพื่อให้ Flatpickr เข้าใจตรงกัน
            const startDateObj = parseDateInternal(phpStartDate);
            const endDateObj = parseDateInternal(phpEndDate);

            // ตัวเลือก: วันเริ่มต้น
            flatpickr("#filter_start_display", { 
                ...fpConfig, 
                defaultDate: isAllMode ? null : startDateObj, 
                onChange: (dates) => updateDateInput(dates, 'filter_start_hidden') 
            });

            // ตัวเลือก: วันสิ้นสุด
            flatpickr("#filter_end_display", { 
                ...fpConfig, 
                defaultDate: isAllMode ? null : endDateObj, 
                onChange: (dates) => updateDateInput(dates, 'filter_end_hidden') 
            });

            // ตัวเลือก: วันที่ในฟอร์มบันทึก (ใช้วันปัจจุบันเสมอ)
            flatpickr("#trans_date_display", { 
                ...fpConfig, 
                defaultDate: "today", 
                onChange: (dates) => updateDateInput(dates, 'trans_date_hidden') 
            });

            // --- 3. ส่วนกราฟ (CHART LOGIC) ---
            const dataCompany = {
                labels: <?php echo json_encode($chart_labels); ?>,
                income: <?php echo json_encode($chart_data_in); ?>,
                expense: <?php echo json_encode($chart_data_out); ?>,
                diff: <?php echo json_encode($chart_data_diff); ?>
            };
            
            const dataTotal = {
                labels: ['ภาพรวมทั้งหมด'],
                income: [<?php echo $sum_in; ?>],
                expense: [<?php echo $sum_out; ?>],
                diff: [<?php echo $sum_diff; ?>]
            };

            const ctx = document.getElementById('cashFlowChart').getContext('2d');
            let myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dataCompany.labels,
                    datasets: [
                        { label: 'รายรับ (Income)', data: dataCompany.income, backgroundColor: '#10b981', borderRadius: 6, barPercentage: 0.6 },
                        { label: 'รายจ่าย (Expense)', data: dataCompany.expense, backgroundColor: '#ef4444', borderRadius: 6, barPercentage: 0.6 },
                        { label: 'ส่วนต่าง (Difference)', data: dataCompany.diff, backgroundColor: '#3b82f6', borderRadius: 6, barPercentage: 0.6 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'top', labels: { font: { family: 'Prompt', size: 13 }, usePointStyle: true, padding: 20 } },
                        tooltip: { callbacks: { label: function(c) { return ' ' + c.dataset.label + ': ' + Number(c.raw).toLocaleString() + ' บาท'; } } }
                    },
                    scales: { 
                        y: { beginAtZero: true, grid: { color: '#f1f5f9', borderDash: [5, 5] }, ticks: { font: { family: 'Sarabun' }, callback: function(value) { return value.toLocaleString(); } } }, 
                        x: { grid: { display: false }, ticks: { font: { family: 'Prompt', size: 11 } } } 
                    }
                }
            });

            window.setChartMode = function(mode) {
                document.getElementById('btn-total').classList.remove('active');
                document.getElementById('btn-company').classList.remove('active');
                document.getElementById('btn-' + mode).classList.add('active');

                if (mode === 'total') {
                    myChart.data.labels = dataTotal.labels;
                    myChart.data.datasets[0].data = dataTotal.income;
                    myChart.data.datasets[1].data = dataTotal.expense;
                    myChart.data.datasets[2].data = dataTotal.diff;
                } else {
                    myChart.data.labels = dataCompany.labels;
                    myChart.data.datasets[0].data = dataCompany.income;
                    myChart.data.datasets[1].data = dataCompany.expense;
                    myChart.data.datasets[2].data = dataCompany.diff;
                }
                myChart.update();
            }

            window.toggleChartVisibility = function(btn) {
                const container = document.getElementById('chartContainer');
                const card = document.getElementById('chartCard');
                const icon = btn.querySelector('i');

                if (container.style.height === '0px') {
                    container.style.height = '350px';
                    container.style.opacity = '1';
                    card.classList.remove('collapsed');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    container.style.height = '0px';
                    container.style.opacity = '0';
                    card.classList.add('collapsed');
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }
        });
    </script>
    <style> .text-success { color: #10b981; } .text-danger { color: #ef4444; } </style>
</body>
</html>