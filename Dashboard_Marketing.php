<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// 1. CONFIGURATION
date_default_timezone_set('Asia/Bangkok');
$table_name    = 'report_online_marketing';
$page_title    = 'Marketing Dashboard';
$primary_color = '#6366f1'; 
$upload_path   = 'uploads/marketing/'; 
$platform_img_path = 'uploads/platforms/'; 

// 2. ACTION LOGIC
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM $table_name WHERE id = $del_id");
    header("Location: Dashboard_Marketing.php");
    exit();
}

// 3. FILTER LOGIC
$filter_name   = $_GET['filter_name']   ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$start_date    = $_GET['start_date']    ?? '';
$end_date      = $_GET['end_date']      ?? '';
$keyword       = $_GET['keyword']       ?? '';

$base_where = "WHERE 1=1";
if ($filter_name)   $base_where .= " AND reporter_name = '" . $conn->real_escape_string($filter_name) . "'";
if ($start_date)    $base_where .= " AND report_date >= '$start_date'";
if ($end_date)      $base_where .= " AND report_date <= '$end_date'";
if ($keyword)       $base_where .= " AND (platform_name LIKE '%$keyword%' OR order_number LIKE '%$keyword%' OR item_details LIKE '%$keyword%')";

// 4. KPI & PLATFORM CALCULATION
$status_counts = []; 
$total_expense = 0;
$total_sales   = 0;
$platform_stats = []; 
$platform_info = [];

// Get Platform Images
$check_col = $conn->query("SHOW COLUMNS FROM marketing_platforms LIKE 'platform_image'");
if ($check_col && $check_col->num_rows > 0) {
    $res_pf = $conn->query("SELECT platform_name, platform_image FROM marketing_platforms");
    while($row = $res_pf->fetch_assoc()) {
        $platform_info[strtolower(trim($row['platform_name']))] = $row['platform_image'];
    }
}

$sql_raw = "SELECT tax_invoice_status, total_expense, total_sales, item_details FROM $table_name $base_where";
$res_raw = $conn->query($sql_raw);

while($row = $res_raw->fetch_assoc()) {
    $total_expense += floatval($row['total_expense']);
    $total_sales   += floatval($row['total_sales']);
    
    // Count Status
    if (!empty($row['tax_invoice_status'])) {
        $statuses = explode(',', $row['tax_invoice_status']);
        foreach($statuses as $st_raw) {
            $parts = explode(':', $st_raw);
            $clean_status = trim(end($parts));
            if ($clean_status !== '') {
                if (!isset($status_counts[$clean_status])) $status_counts[$clean_status] = 0;
                $status_counts[$clean_status]++;
            }
        }
    } else {
        $unknown = 'ไม่ระบุ';
        if (!isset($status_counts[$unknown])) $status_counts[$unknown] = 0;
        $status_counts[$unknown]++;
    }

    // Platform Stats
    $details = $row['item_details'];
    if (!empty($details)) {
        $shops = explode('--------------------', $details);
        foreach ($shops as $shop_txt) {
            $shop_txt = trim($shop_txt);
            if (empty($shop_txt)) continue;
            
            $lines = explode("\n", $shop_txt);
            $header = $lines[0]; 
            
            // 1. ลบ Pattern มาตรฐานออก (Order:..., #..., ช่องทางที่...)
            $temp_name = preg_replace('/[🌐\?]|(\(Order:.*?\))|(#.*)|(ช่องทางที่ \d+:)/i', '', $header);
            
            // 2. 🟢 [แก้ใหม่] ตัดตัวอักษรขยะที่เหลืออยู่ (เช่น วงเล็บปิด ) หรือ : หรือ ช่องว่าง)
            $clean_name = trim($temp_name, " :)\t\n\r\0\x0B"); 

            if (empty($clean_name)) $clean_name = 'อื่นๆ';
            
            // ดึงยอดเงิน
            if (preg_match('/💰.*?([\d,\.]+)/', $shop_txt, $matches)) {
                $amount = floatval(str_replace(',', '', $matches[1]));
                if (!isset($platform_stats[$clean_name])) $platform_stats[$clean_name] = 0;
                $platform_stats[$clean_name] += $amount;
            }
        }
    }
}
ksort($status_counts);
arsort($platform_stats);

// 5. FETCH LIST DATA
$list_where = $base_where;
if ($filter_status) {
    $list_where .= " AND tax_invoice_status LIKE '%" . $conn->real_escape_string($filter_status) . "%'";
}
$sql_list    = "SELECT * FROM $table_name $list_where ORDER BY report_date DESC, id DESC";
$result_list = $conn->query($sql_list);
$users       = $conn->query("SELECT DISTINCT reporter_name FROM $table_name ORDER BY reporter_name ASC");

// Helper: Auto Color Generation based on Status Name
function getStatusConfig($status) {
    // 1. สีเหลือง/ส้ม: กำลังดำเนินการ
    if (strpos($status, 'ดำเนินการ') !== false || strpos($status, 'รอ') !== false || strpos($status, 'Pending') !== false) {
        return ['bg'=>'var(--bg-warning-soft)', 'text'=>'var(--text-warning)', 'border'=>'var(--warning)', 'dot'=>'var(--warning)', 'icon'=>'fa-clock'];
    }
    // 2. สีเขียว: สำเร็จ
    if (strpos($status, 'ส่งแล้ว') !== false || strpos($status, 'สำเร็จ') !== false || strpos($status, 'Complete') !== false) {
        return ['bg'=>'var(--bg-success-soft)', 'text'=>'var(--text-success)', 'border'=>'var(--success)', 'dot'=>'var(--success)', 'icon'=>'fa-check-circle'];
    }
    // 3. สีแดง: มีปัญหา
    if (strpos($status, 'ตีกลับ') !== false || strpos($status, 'ยกเลิก') !== false || strpos($status, 'Fail') !== false) {
        return ['bg'=>'var(--bg-danger-soft)', 'text'=>'var(--text-danger)', 'border'=>'var(--danger)', 'dot'=>'var(--danger)', 'icon'=>'fa-times-circle'];
    }
    // 4. สีฟ้า: ขนส่ง
    if (strpos($status, 'ระหว่าง') !== false || strpos($status, 'ขนส่ง') !== false || strpos($status, 'Tracking') !== false) {
        return ['bg'=>'var(--bg-info-soft)', 'text'=>'var(--text-info)', 'border'=>'var(--info)', 'dot'=>'var(--info)', 'icon'=>'fa-truck'];
    }
    // 5. Auto Color
    $hash = crc32($status); 
    $palettes = [
        ['name'=>'purple', 'icon'=>'fa-cube'], ['name'=>'pink', 'icon'=>'fa-gift'],
        ['name'=>'teal', 'icon'=>'fa-clipboard-check'], ['name'=>'cyan', 'icon'=>'fa-tasks'],
        ['name'=>'indigo', 'icon'=>'fa-layer-group'], ['name'=>'rose', 'icon'=>'fa-box-open'],
    ];
    $index = abs($hash) % count($palettes);
    $p = $palettes[$index];
    return ['bg'=>"var(--bg-{$p['name']}-soft)", 'text'=>"var(--text-{$p['name']})", 'border'=>"var(--{$p['name']})", 'dot'=>"var(--{$p['name']})", 'icon'=>$p['icon']];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        function applyTheme() {
            const theme = localStorage.getItem('tjc_theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.body?.classList.add('dark-mode');
            } else {
                document.documentElement.classList.remove('dark-mode');
                document.body?.classList.remove('dark-mode');
            }
        }
        applyTheme();
        window.addEventListener('storage', function(e) { if (e.key === 'tjc_theme') applyTheme(); });
        setInterval(applyTheme, 1000); 
    </script>

    <style>
    /* --- CSS Palette --- */
    :root {
        --primary: #6366f1; --primary-hover: #4f46e5; --primary-soft: #e0e7ff;
        --info: #3b82f6; --text-info: #1d4ed8; --bg-info-soft: #eff6ff;
        --success: #10b981; --text-success: #047857; --bg-success-soft: #ecfdf5;
        --warning: #f59e0b; --text-warning: #b45309; --bg-warning-soft: #fffbeb;
        --danger: #ef4444; --text-danger: #b91c1c; --bg-danger-soft: #fef2f2; --border-danger-soft: #fecaca;

        --purple: #8b5cf6; --text-purple: #7c3aed; --bg-purple-soft: #f5f3ff;
        --pink: #ec4899;   --text-pink: #db2777;   --bg-pink-soft: #fdf2f8;
        --cyan: #06b6d4;   --text-cyan: #0891b2;   --bg-cyan-soft: #ecfeff;
        --teal: #14b8a6;   --text-teal: #0d9488;   --bg-teal-soft: #f0fdfa;
        --orange: #f97316; --text-orange: #ea580c; --bg-orange-soft: #fff7ed;
        --indigo: #6366f1; --text-indigo: #4338ca; --bg-indigo-soft: #eef2ff;
        --rose:   #f43f5e; --text-rose:   #be123c; --bg-rose-soft:   #fff1f2;

        --bg-body: #f1f5f9; --bg-card: #ffffff; --bg-subtle: #f8fafc; --bg-hover: #f1f5f9; --bg-input: #ffffff;
        --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --radius-lg: 12px; --radius-xl: 16px;

        --bg-receipt-head: #f8fafc; --bg-receipt-foot: #f8fafc; --bg-modal-overlay: rgba(15, 23, 42, 0.6);
    }

    body.dark-mode {
        --primary: #818cf8; --primary-hover: #6366f1; --primary-soft: rgba(99, 102, 241, 0.15);
        --info: #60a5fa; --text-info: #bfdbfe; --bg-info-soft: rgba(59, 130, 246, 0.15);
        --success: #34d399; --text-success: #6ee7b7; --bg-success-soft: rgba(16, 185, 129, 0.1);
        --warning: #fbbf24; --text-warning: #fcd34d; --bg-warning-soft: rgba(245, 158, 11, 0.1);
        --danger: #f87171; --text-danger: #fca5a5; --bg-danger-soft: rgba(239, 68, 68, 0.15); --border-danger-soft: rgba(239, 68, 68, 0.3);

        --purple: #a78bfa; --text-purple: #e9d5ff; --bg-purple-soft: rgba(139, 92, 246, 0.15);
        --pink: #f472b6; --text-pink: #fbcfe8; --bg-pink-soft: rgba(236, 72, 153, 0.15);
        --cyan: #22d3ee; --text-cyan: #cffafe; --bg-cyan-soft: rgba(6, 182, 212, 0.15);
        --teal: #2dd4bf; --text-teal: #ccfbf1; --bg-teal-soft: rgba(20, 184, 166, 0.15);
        --orange: #fb923c; --text-orange: #ffedd5; --bg-orange-soft: rgba(249, 115, 22, 0.15);
        --indigo: #818cf8; --text-indigo: #e0e7ff; --bg-indigo-soft: rgba(99, 102, 241, 0.15);
        --rose: #fb7185; --text-rose: #ffe4e6; --bg-rose-soft: rgba(244, 63, 94, 0.15);

        --bg-body: #0f172a; --bg-card: #1e293b; --bg-subtle: #334155; --bg-hover: #2d3748; --bg-input: #1e293b;
        --text-main: #f1f5f9; --text-muted: #94a3b8; --border-color: #334155;
        --shadow-sm: none; --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        --bg-receipt-head: #28364d; --bg-receipt-foot: #28364d; --bg-modal-overlay: rgba(0, 0, 0, 0.8);
    }

    * { box-sizing: border-box; transition: background-color 0.3s, border-color 0.3s, color 0.3s; }
    body { font-family: 'Inter', 'Prompt', sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; line-height: 1.6; font-size: 14px; }
    .main-container { max-width: 1600px; margin: 0 auto; padding: 20px 20px; }
    
    /* Header */
    .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
    .header-title h2 { font-size: 28px; font-weight: 700; margin: 0; color: var(--text-main); letter-spacing: -0.5px; }
    .header-title p { margin: 5px 0 0; color: var(--text-muted); font-size: 15px; }

    /* FINANCIAL CARDS */
    .financial-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .fin-card { background: var(--bg-card); padding: 25px; border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; cursor: pointer; height: 120px; }
    .fin-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
    .fin-card.active-filter { background: var(--bg-subtle); border-color: var(--primary); }
    .fin-content { z-index: 2; }
    .fin-label { font-size: 14px; color: var(--text-muted); font-weight: 600; margin-bottom: 5px; }
    .fin-value { font-size: 36px; font-weight: 800; letter-spacing: -1px; line-height: 1; }
    .fin-icon-bg { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; opacity: 0.9; z-index: 2; }

    /* STATUS MINI CARDS */
    .section-divider { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; color: var(--text-muted); font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .section-divider::after { content: ''; flex: 1; height: 1px; background: var(--border-color); }
    .status-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 30px; }
    .mini-card { background: var(--bg-card); padding: 16px 20px; border-radius: 14px; border: 1px solid var(--border-color); cursor: pointer; display: flex; flex-direction: column; justify-content: center; position: relative; height: 100px; transition: transform 0.2s, box-shadow 0.2s; }
    .mini-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .mini-card.active-filter { border-width: 2px; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.05); }
    .mini-top { display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px; }
    .mini-label { font-size: 13px; font-weight: 600; opacity: 0.9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 85%; }
    .mini-icon { font-size: 16px; opacity: 0.8; }
    .mini-value { font-size: 26px; font-weight: 800; line-height: 1; }
    
    /* Platform Grid */
    .pf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; margin-bottom: 30px; }
    .pf-card { background: var(--bg-card); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; height: 100px; position:relative; overflow:hidden; box-shadow: var(--shadow-sm); }
    .pf-card:hover { border-color:var(--primary); }
    .pf-logo { width: 40px; height: 40px; border-radius: 8px; object-fit: contain; background: #fff; padding: 4px; border: 1px solid var(--border-color); }

    /* Filters */
    .filter-wrapper { background: var(--bg-card); padding: 25px; border-radius: var(--radius-xl); border: 1px solid var(--border-color); margin-bottom: 30px; box-shadow: var(--shadow-sm); }
    .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end; }
    .form-group label { display: block; font-size: 13px; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; }
    .form-control { width: 100%; padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; font-size: 14px; outline: none; }
    .form-control:focus { border-color: var(--primary); }
    .btn { display: inline-flex; align-items: center; justify-content: center; height: 42px; padding: 0 24px; border-radius: var(--radius-md); font-weight: 600; border: none; cursor: pointer; font-size: 14px; text-decoration: none; gap: 8px; }
    .btn-primary { background: var(--primary); color: #ffffff; } 
    .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-muted); }
    .btn-outline:hover { color: var(--primary); border-color: var(--primary); }

    /* Table */
    .table-container { background: var(--bg-card); border-radius: var(--radius-xl); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm); }
    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; white-space: nowrap; }
    th { background: var(--bg-subtle); color: var(--text-muted); font-weight: 600; font-size: 12px; text-transform: uppercase; padding: 16px 20px; text-align: left; border-bottom: 1px solid var(--border-color); }
    td { padding: 16px 20px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: var(--text-main); }
    tr:hover td { background: var(--bg-hover); }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .btn-icon { width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); color: var(--text-muted); cursor: pointer; background: transparent; margin: 0 2px; }
    .btn-icon:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }

    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: var(--bg-modal-overlay); backdrop-filter: blur(4px); }
    .modal-content { background: var(--bg-card); margin: 3vh auto; border-radius: 20px; width: 95%; max-width: 850px; max-height: 94vh; overflow-y: auto; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); color: var(--text-main); }
    .modal-header { padding: 20px 30px; border-bottom: 1px solid var(--border-color); background: var(--bg-card); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
    .modal-body { padding: 30px; background: var(--bg-body); }
    
    .receipt-card { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 20px; overflow: hidden; box-shadow: var(--shadow-sm); }
    .receipt-header { padding: 15px 25px; background: var(--bg-receipt-head); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    
    /* Compact Table (Fit Content with Full Width in Card) */
    .receipt-table, .detail-table { 
        width: 100% !important; /* ปรับให้เต็มกรอบการ์ดตามที่ขอ */
        min-width: 400px; 
        border-collapse: collapse; 
        font-size: 13px; 
    }
    .receipt-table th, .detail-table th, .receipt-table td, .detail-table td { 
        padding: 8px 12px !important; 
        white-space: nowrap; 
        vertical-align: middle; 
    }
    .table-fit-wrapper { 
        overflow-x: auto; 
        background: var(--bg-card); 
        padding: 0; 
        display: block; 
    }

    .receipt-footer { background: var(--bg-receipt-foot); padding: 15px 25px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    
    .image-viewer { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); justify-content: center; align-items: center; flex-direction: column; }
    .image-viewer img { max-width: 90%; max-height: 85vh; border-radius: 8px; }
    .image-viewer-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-container">
        
        <div class="page-header">
            <div class="header-title">
                <h2><?php echo $page_title; ?></h2>
                <p>รายงานผลการดำเนินงานและการตลาดออนไลน์</p>
            </div>
        </div>

        <?php if (!empty($platform_stats)): ?>
        <div style="margin-bottom: 30px; background: var(--bg-card); border-radius: var(--radius-xl); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm);">
            <div onclick="document.getElementById('platformSection').style.display = document.getElementById('platformSection').style.display === 'none' ? 'block' : 'none'" style="padding: 15px 25px; background: var(--bg-subtle); cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width: 32px; height: 32px; background: var(--primary-soft); color: var(--primary); border-radius: 8px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-store"></i></div>
                    <h4 style="margin:0; font-weight: 700; font-size:16px; color: var(--text-main);">ยอดขายแยกตามร้านค้า</h4>
                    <span style="background:var(--bg-card); border:1px solid var(--border-color); color:var(--text-muted); padding:2px 10px; border-radius:12px; font-size:12px; font-weight:600;"><?php echo count($platform_stats); ?> ร้าน</span>
                </div>
                <i class="fas fa-chevron-down" style="color:var(--text-muted);"></i>
            </div>
            <div id="platformSection" style="padding: 20px; display: block;">
                <div class="pf-grid">
                    <?php foreach ($platform_stats as $pf_name => $amount): 
                        $pf_key = strtolower($pf_name);
                        $logo_file = isset($platform_info[$pf_key]) ? $platform_info[$pf_key] : null;
                        $has_logo = ($logo_file && file_exists($platform_img_path . $logo_file));
                    ?>
                    <div class="pf-card">
                        <div style="position:absolute; top:0; left:0; width:4px; height:100%; background:var(--primary);"></div>
                        <div>
                            <div style="font-size:13px; font-weight:600; color:var(--text-muted); margin-bottom:5px; max-width: 130px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo $pf_name; ?></div>
                            <div style="font-size:20px; font-weight:700; color:var(--text-main);"><?php echo number_format($amount); ?> ฿</div>
                        </div>
                        <?php if($has_logo): ?>
                            <img src="<?php echo $platform_img_path . $logo_file; ?>" class="pf-logo" alt="Logo">
                        <?php else: ?>
                            <div class="pf-logo" style="display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:18px;"><i class="fas fa-store"></i></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="financial-grid">
            <div class="fin-card <?php echo ($filter_status == '') ? 'active-filter' : ''; ?>" onclick="filterByStatus('')">
                <div class="fin-content">
                    <div class="fin-label">ยอดขายรวมสุทธิ</div>
                    <div class="fin-value" style="color: var(--primary);"><?php echo number_format($total_sales); ?> <span style="font-size:16px; color:var(--text-muted);">฿</span></div>
                </div>
                <div class="fin-icon-bg" style="background: var(--primary-soft); color: var(--primary);">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="fin-card" onclick="filterByStatus('')">
                <div class="fin-content">
                    <div class="fin-label">ค่าใช้จ่ายรวม</div>
                    <div class="fin-value" style="color: var(--danger);"><?php echo number_format($total_expense); ?> <span style="font-size:16px; color:var(--text-muted);">฿</span></div>
                </div>
                <div class="fin-icon-bg" style="background: var(--danger-soft); color: var(--danger);">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>

        <div class="section-divider">
            <i class="fas fa-tasks"></i> สถานะออเดอร์ (Order Status)
        </div>

        <div class="status-grid">
            <?php foreach($status_counts as $st_name => $count): 
                $c = getStatusConfig($st_name); 
                $isActive = ($filter_status == $st_name) ? 'active-filter' : '';
                $cardStyle = "background-color: {$c['bg']}; color: {$c['text']}; border-color: transparent;";
                if($isActive) {
                    $cardStyle = "background-color: var(--bg-card); color: {$c['text']}; border-color: {$c['border']}; box-shadow: 0 0 0 1px {$c['border']};";
                }
            ?>
            <div class="mini-card <?php echo $isActive; ?>" 
                 onclick="filterByStatus('<?php echo $st_name; ?>')"
                 style="<?php echo $cardStyle; ?>">
                
                <div class="mini-top">
                    <span class="mini-label"><?php echo htmlspecialchars($st_name); ?></span>
                    <i class="fas <?php echo $c['icon']; ?> mini-icon"></i>
                </div>
                <div class="mini-value">
                    <?php echo number_format($count); ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if($filter_status != ''): ?>
            <div class="mini-card" onclick="filterByStatus('')" style="background: var(--bg-subtle); color: var(--text-muted); border: 1px dashed var(--border-color); align-items: center; justify-content: center; gap:5px;">
                <i class="fas fa-undo" style="font-size: 18px;"></i>
                <span style="font-size:12px; font-weight:600;">ล้างตัวกรอง</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="filter-wrapper">
            <div style="font-weight: 700; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; color: var(--text-main);"><i class="fas fa-filter" style="color:var(--primary);"></i> ตัวกรองข้อมูล</div>
            <form class="filter-form" method="GET" id="filterForm">
                <input type="hidden" name="filter_status" id="hidden_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                
                <?php if(isset($users)): ?>
                <div class="form-group">
                    <label>พนักงาน</label>
                    <select name="filter_name" class="form-control" onchange="document.getElementById('filterForm').submit()">
                        <option value="">ทั้งหมด</option>
                        <?php 
                        $users->data_seek(0); 
                        while($u=$users->fetch_assoc()){ 
                            echo "<option value='{$u['reporter_name']}' ".($filter_name==$u['reporter_name']?'selected':'').">{$u['reporter_name']}</option>"; 
                        } 
                        ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>คำค้นหา</label>
                    <input type="text" name="keyword" value="<?php echo $keyword; ?>" class="form-control" placeholder="Platform, Order No...">
                </div>
                
                <div class="form-group">
                    <label>วันที่เริ่ม</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>ถึงวันที่</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                </div>
                
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-search"></i> ค้นหา</button>
                    <a href="?" class="btn btn-outline" title="ล้างค่า"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่ / เวลา</th>
                            <th>พนักงาน</th>
                            <th>แพลตฟอร์ม</th>
                            <th>Order No.</th>
                            <th>เลขที่เอกสาร</th>
                            <th>สถานะ</th>
                            <th style="text-align:right">ยอดขาย</th>
                            <th style="text-align:right">ค่าใช้จ่าย</th>
                            <th style="text-align:center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php if($result_list->num_rows > 0): while($row = $result_list->fetch_assoc()): ?>
    <tr>
        <td>
            <div style="font-weight:700; color:var(--text-main);"><?php echo date('d/m/Y', strtotime($row['report_date'])); ?></div>
            <div style="font-size:12px; color:var(--text-muted);"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
        </td>
        <td><span style="font-weight:500;"><?php echo $row['reporter_name']; ?></span></td>
        <td>
            <?php if($row['platform_name']) { 
                foreach(explode(',', $row['platform_name']) as $p) echo '<div style="font-size:12px; margin-bottom:2px;"><i class="fas fa-store" style="color:var(--primary); margin-right:4px;"></i>'.trim($p).'</div>'; 
            } else { echo "-"; } ?>
        </td>
        <td><span style="font-family:'Courier New', monospace; font-weight:600; font-size:13px;"><?php echo $row['order_number'] ? str_replace(',', '<br>', $row['order_number']) : '-'; ?></span></td>
        <td><span style="font-size:13px; color:var(--text-muted);"><?php echo $row['doc_references'] ? str_replace(',', '<br>', $row['doc_references']) : '-'; ?></span></td>
        
        <td>
            <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-start;">
                <?php foreach(explode(',', $row['tax_invoice_status']) as $st) {
                    $clean = trim(explode(':', $st)[1] ?? $st);
                    if($clean) { 
                        $c = getStatusConfig($clean); 
                        echo "<span class='status-badge' style='background:{$c['bg']}; color:{$c['text']}; border:1px solid {$c['border']}; width: fit-content;'>
                                <i class='fas {$c['icon']}' style='font-size:10px;'></i> $clean
                              </span>"; 
                    }
                } ?>
            </div>
        </td>
        <td style="text-align:right; font-weight:700; color:var(--success);">+<?php echo number_format($row['total_sales']); ?></td>
        <td style="text-align:right; font-weight:700; color:var(--danger);">-<?php echo number_format($row['total_expense']); ?></td>
        <td style="text-align:center;">
            <button onclick='showDetail(<?php echo htmlspecialchars(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_IGNORE), ENT_QUOTES, 'UTF-8'); ?>)' class="btn-icon" title="ดูรายละเอียด"><i class="fas fa-eye"></i></button>
            <?php if (function_exists('hasAction') && hasAction('edit_marketing')): ?><a href="Online_Marketing_Report.php?id=<?php echo $row['id']; ?>" class="btn-icon"><i class="fas fa-edit"></i></a><?php endif; ?>
            <?php if (function_exists('hasAction') && hasAction('delete_marketing')): ?><a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('ยืนยันลบ?')" class="btn-icon"><i class="fas fa-trash-alt"></i></a><?php endif; ?>
        </td>
    </tr>
    <?php endwhile; else: echo "<tr><td colspan='9' style='text-align:center; padding:50px; color:var(--text-muted);'>ไม่พบข้อมูล</td></tr>"; endif; ?>
</tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="detailModal" class="modal" onclick="if(event.target==this)closeModal()">
        <div class="modal-content">
            <div class="modal-header">
                <div style="font-size: 18px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
                    <div style="width: 36px; height: 36px; background: var(--primary-soft); color: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px;"><i class="fas fa-file-invoice"></i></div> รายละเอียดออเดอร์
                </div>
                <button onclick="closeModal()" style="width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--bg-subtle); color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>
    
    <div id="imageViewer" class="image-viewer" onclick="closeImageViewer()">
        <span class="image-viewer-close">&times;</span>
        <img id="viewerImage" src="">
        <div id="viewerCaption" class="image-viewer-caption" style="margin-top:15px; color:white; font-size:16px;"></div>
    </div>

    <script>
    const uploadPath = "<?php echo $upload_path; ?>"; 
    
    function toggleSection(id) {
        var el = document.getElementById(id);
        var icon = document.getElementById('icon-' + id);
        if (el.style.display === "none") { el.style.display = "block"; if(icon) icon.style.transform = "rotate(0deg)"; } 
        else { el.style.display = "none"; if(icon) icon.style.transform = "rotate(-90deg)"; }
    }

    function cleanFileName(f) { return f ? (f.includes(':') ? f.split(':').pop().trim() : f.trim()) : ''; }
    function isImage(f) { return ['jpg','jpeg','png','gif','webp'].includes(f.split('.').pop().toLowerCase()); }
    function getPlatformKey(text) { if (!text) return 'other'; return text.trim(); }

    function openFile(f, type) {
        const p = uploadPath + f;
        if(isImage(f)) { 
            document.getElementById('viewerImage').src=p; 
            document.getElementById('viewerCaption').innerText=type + ': ' + f; 
            document.getElementById('imageViewer').style.display='flex'; 
        } else {
            window.open(p,'_blank');
        }
    }
    
    function closeImageViewer() { document.getElementById('imageViewer').style.display='none'; document.getElementById('viewerImage').src = ''; }
    function closeModal() { document.getElementById('detailModal').style.display='none'; }
    function filterByStatus(s) { document.getElementById('hidden_status').value=s; document.getElementById('filterForm').submit(); }

    function showDetail(data) {
        if(!data){ alert("Data Error"); return; }
        let html = ``;
        let reportDate = new Date(data.report_date).toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
        
        html += `<div style="padding:20px; background:var(--bg-subtle); border-radius:12px; margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border-color);">
                    <div><small style="color:var(--text-muted);">Report Date</small><div style="font-weight:700; font-size:16px;">${reportDate}</div></div>
                    <div style="text-align:right;"><small style="color:var(--text-muted);">Reporter</small><div style="font-weight:700; color:var(--primary); font-size:16px;">${data.reporter_name}</div></div>
                 </div>`;

        let fileMap = {};
        let genericFiles = [];
        let rawFiles = data.platform_files || data.slip_file || "";
        if(rawFiles) {
             let groups = rawFiles.includes('|') ? rawFiles.split('|') : [rawFiles];
             groups.forEach(g => {
                let p = g.split(':');
                if(p.length >= 2) {
                    let key = getPlatformKey(p[0]); 
                    let files = p.slice(1).join(':').split(',');
                    if(!fileMap[key]) fileMap[key] = [];
                    files.forEach(f => { let safe = cleanFileName(f); if(safe) fileMap[key].push(safe); });
                } else {
                    g.split(',').forEach(f => { let safe = cleanFileName(f); if(safe) genericFiles.push(safe); });
                }
             });
        }

        if(data.item_details) {
            let shops = data.item_details.split('--------------------').filter(s => s.trim() !== "");
            let statuses = (data.tax_invoice_status||'').split(',');

            shops.forEach((shopTxt, idx) => {
                let lines = shopTxt.trim().split(/\r?\n/);
                let headerLine = lines[0]; 
                let lookupKey = getPlatformKey(headerLine);
                let displayName = headerLine.replace(/\?|🌐/g, '').split('#')[0].split('(')[0].trim();
                let orderNo = '-';
                if (headerLine.includes('Order:')) orderNo = headerLine.match(/\(Order: (.*?)\)/)[1];
                else if (headerLine.includes('#')) orderNo = headerLine.split('#')[1].trim();

                let tableRows = '';
                let shopTotal = '0.00';
                for(let i=1; i<lines.length; i++) {
                    let line = lines[i].trim();
                    if(!line) continue;
                    if(i === lines.length - 1 && (line.includes('บาท') || line.includes('ยอด'))) {
                        let match = line.match(/([\d,\.]+) บาท/);
                        if(match) shopTotal = match[1];
                        continue; 
                    }
                    if(line.includes('=')) {
                        let namePart = line.split('(')[0].replace('-','').trim();
                        let qty = (line.match(/\(x(.*?)\)/) || [])[1] || '1';
                        let price = (line.match(/@ (.*?)\)/) || [])[1] || '0';
                        let total = line.split('=')[1].replace('บ.','').trim();
                        let disc = '0'; let matchDisc = line.match(/\[(?:ส่วน)?ลด -(.*?)\]/); if(matchDisc) disc = matchDisc[1];
                        let ship = '0'; let matchShip = line.match(/\[(?:ค่า)?ส่ง \+(.*?)\]/); if(matchShip) ship = matchShip[1];
                        tableRows += `<tr><td>${namePart}</td><td style="text-align:center;">x${qty}</td><td style="text-align:right;">${price}</td><td style="text-align:right; color:var(--danger);">${disc!='0'?'-'+disc:'-'}</td><td style="text-align:right; color:var(--success);">${ship!='0'?'+'+ship:'-'}</td><td style="text-align:right; font-weight:700;">${total}</td></tr>`;
                    }
                }
                let st = (statuses[idx]||'ดำเนินการ').split(':')[1] || (statuses[idx]||'ดำเนินการ');
                st = st.trim();
                let stStyle = st.includes('ส่งแล้ว') ? 'background:var(--bg-success-soft); color:var(--text-success);' : (st.includes('ตีกลับ') ? 'background:var(--bg-danger-soft); color:var(--text-danger);' : 'background:var(--bg-warning-soft); color:var(--text-warning);');

                let myFiles = [];
                if (orderNo !== '-' && fileMap[orderNo]) myFiles = myFiles.concat(fileMap[orderNo]);
                else if (fileMap[lookupKey]) myFiles = myFiles.concat(fileMap[lookupKey]);
                if (idx === 0) myFiles = myFiles.concat(genericFiles);
                let fileHtml = '';
                [...new Set(myFiles)].forEach(f => {
                    fileHtml += `<div onclick="openFile('${f}', 'สลิป')" class="btn-slip" style="display:inline-flex; align-items:center; gap:5px; padding:6px 12px; border:1px solid var(--primary-soft); color:var(--primary); border-radius:8px; cursor:pointer; font-size:12px; font-weight:600;"><i class="fas fa-image"></i> หลักฐาน</div>`;
                });

                html += `<div class="receipt-card">
                            <div class="receipt-header">
                                <div style="font-weight:700;"><i class="fas fa-store" style="color:var(--primary);"></i> ${displayName} <span style="background:var(--bg-subtle); padding:2px 8px; border-radius:4px; font-size:12px; color:var(--text-muted); border:1px solid var(--border-color);">#${orderNo}</span></div>
                                <span style="font-size:12px; padding:4px 12px; border-radius:20px; font-weight:700; ${stStyle}">${st}</span>
                            </div>
                            <div class="table-fit-wrapper">
                                <table class="receipt-table">
                                    <thead><tr><th style="text-align:left;">สินค้า</th><th style="text-align:center;">จำนวน</th><th style="text-align:right;">ราคา</th><th style="text-align:right;">ลด</th><th style="text-align:right;">ส่ง</th><th style="text-align:right;">รวม</th></tr></thead>
                                    <tbody>${tableRows || '<tr><td colspan="6" style="text-align:center;">ไม่มีรายละเอียด</td></tr>'}</tbody>
                                </table>
                            </div>
                            <div class="receipt-footer"><div style="display:flex; gap:5px;">${fileHtml}</div><div style="text-align:right;"><small style="color:var(--text-muted);">ยอดรวม</small><div style="font-size:18px; font-weight:700; color:var(--primary);">${shopTotal} ฿</div></div></div>
                        </div>`;
            });
        }

        html += `<div style="background:linear-gradient(135deg, var(--primary), var(--primary-hover)); border-radius:16px; padding:20px; color:white; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <div><div style="font-size:14px; opacity:0.9;">ยอดขายสุทธิรวม (Grand Total)</div></div>
                    <div style="font-size:24px; font-weight:800;">+${parseFloat(data.total_sales).toLocaleString()} ฿</div>
                 </div>`;
        
        if(data.additional_notes) {
            let note = data.additional_notes.replace(/\[Memo:\s*/g,'').replace(/\]/g,'');
            
            html += `<div style="background:var(--bg-warning-soft); border:1px solid var(--warning); color:var(--text-warning); padding:20px; border-radius:12px; margin-top:20px; font-size:14px;">
                        <div style="font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-sticky-note"></i> บันทึกเพิ่มเติม:
                        </div>
                        <div style="white-space: pre-wrap; line-height: 1.7; opacity: 0.9;">${note}</div>
                     </div>`;
        }
        
        if(data.expense_list || data.expense_files) {
            let expRows = '';
            let expFiles = [];
            if(data.expense_files) {
                expFiles = data.expense_files.replace(/\|/g, ',').split(',').filter(f=>f.trim()!=='');
            }
            let expenses = [];
            if (data.expense_list) {
                let pattern = /([^\(\)\|]+)\s*\(([\d,\.]+)\)/g;
                let match;
                while ((match = pattern.exec(data.expense_list)) !== null) {
                    expenses.push({
                        name: match[1].trim().replace(/^,/, '').trim(),
                        amount: match[2]
                    });
                }
            }
            if(expenses.length > 0) {
                expenses.forEach((item, index) => {
                    let fileBtn = '<span style="color:var(--text-muted);">-</span>';
                    if (expFiles[index]) {
                        let fName = cleanFileName(expFiles[index]);
                        fileBtn = `<div onclick="openFile('${fName}', 'บิลค่าใช้จ่าย')" class="btn-slip" style="border-color:var(--border-danger-soft); color:var(--text-danger); background:var(--bg-danger-soft); cursor:pointer; display:inline-flex; align-items:center; gap:5px; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600;">
                                    <i class="fas fa-paperclip"></i> หลักฐาน
                                   </div>`;
                    }
                    expRows += `<tr>
                                    <td style="text-align:left;">${item.name}</td>
                                    <td style="text-align:center;">${fileBtn}</td>
                                    <td style="text-align:right; font-weight:700; color:var(--danger);">${item.amount}</td>
                                </tr>`;
                });
            }
            
            html += `<div class="receipt-card" style="border-color: var(--border-danger-soft); margin-top:25px;">
                        <div class="receipt-header" style="background: var(--bg-danger-soft); border-bottom: 1px solid var(--border-danger-soft);">
                            <div style="font-weight:700; color: var(--text-danger);">
                                <i class="fas fa-wallet" style="color: var(--danger);"></i> รายการค่าใช้จ่าย (Expenses)
                            </div>
                        </div>
                        <div class="table-fit-wrapper">
                            <table class="receipt-table">
                                <thead>
                                    <tr>
                                        <th style="width:50%;">รายการ</th>
                                        <th style="text-align:center; width:25%;">หลักฐาน</th>
                                        <th style="text-align:right; width:25%;">จำนวนเงิน</th>
                                    </tr>
                                </thead>
                                <tbody>${expRows || '<tr><td colspan="3" style="text-align:center; padding:20px; color:var(--text-muted);">ไม่มีข้อมูลค่าใช้จ่าย</td></tr>'}</tbody>
                            </table>
                        </div>
                        <div class="receipt-footer" style="background: var(--bg-danger-soft); border-top: 1px solid var(--border-danger-soft); justify-content: flex-end;">
                            <div style="text-align:right; display: flex; align-items: baseline; gap: 15px;">
                                <span style="font-size:14px; font-weight: 600; color:var(--text-danger);">รวมจ่ายสุทธิ</span>
                                <span style="font-size: 20px; font-weight: 800; color:var(--danger);">${parseFloat(data.total_expense).toLocaleString()} ฿</span>
                            </div>
                        </div>
                    </div>`;
        }

        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('detailModal').style.display = 'flex';
    }
    </script>
</body>
</html>