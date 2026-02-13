<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

date_default_timezone_set('Asia/Bangkok');

// --- 1. CONFIG & FILTER ---
$table_name = 'report_admin';
$start_date    = $_GET['start_date']    ?? '';
$end_date      = $_GET['end_date']      ?? '';
$selected_reporter = $_GET['reporter'] ?? '';

$where_sql = "WHERE 1=1";

// กรองพนักงาน
if (!empty($selected_reporter)) {
    $where_sql .= " AND reporter_name = '" . $conn->real_escape_string($selected_reporter) . "'";
}

// กรองวันที่ (ถ้ามีค่า)
if (!empty($start_date)) $where_sql .= " AND report_date >= '$start_date'";
if (!empty($end_date)) $where_sql .= " AND report_date <= '$end_date'";

// --- 2. FETCH REPORTERS ---
$reporters_list = [];
$q_rep = $conn->query("SELECT DISTINCT reporter_name FROM $table_name ORDER BY reporter_name ASC");
if ($q_rep) {
    while ($row = $q_rep->fetch_assoc()) {
        $reporters_list[] = $row['reporter_name'];
    }
}

// --- 3. KPI CALCULATION ---
$kpi = ['accom'=>0, 'labor'=>0, 'pr'=>0, 'job'=>0, 'bg'=>0, 'stamp'=>0, 'other'=>0, 'docs'=>0];

function sumDocsFromString($str) {
    if(empty($str)) return 0;
    $arr = json_decode($str, true);
    if(!is_array($arr)) $arr = explode(',', $str);
    
    $total = 0;
    foreach($arr as $item) {
        // Regex หาตัวเลขหลังเครื่องหมาย : และก่อนวงเล็บปิด )
        // รองรับรูปแบบ: "AX 123 (รายการ : 500)" หรือ "PO 456 (1,200.50)"
        if(preg_match('/:\s*([\d,\.]+)\s*\)/', $item, $matches)) {
            $total += floatval(str_replace(',', '', $matches[1]));
        }
    }
    return $total;
}
function sumJsonStr($str) {
    if(empty($str)) return 0;
    $arr = json_decode($str, true);
    if(!is_array($arr)) $arr = explode(',', $str);
    return array_sum(array_map(function($v){ return floatval(trim($v)); }, $arr));
}

$sql_kpi_data = "SELECT * FROM $table_name $where_sql";
$res_kpi = $conn->query($sql_kpi_data);

if ($res_kpi) {
    while ($row = $res_kpi->fetch_assoc()) {
        if ($row['has_expense']) {
            $kpi['accom'] += sumJsonStr($row['exp_accom']);
            $raw_labor = sumJsonStr($row['exp_labor']);
            $kpi['labor'] += ($raw_labor * 0.97); // Net Labor
            $kpi['other'] += sumJsonStr($row['exp_other_amount']);
            $kpi['docs'] += sumDocsFromString($row['exp_doc']);
        }
        if ($row['has_pr'])    $kpi['pr']    += sumJsonStr($row['pr_budget']);
        if ($row['has_job'])   $kpi['job']   += sumJsonStr($row['job_budget']);
        if ($row['has_bg'])    $kpi['bg']    += sumJsonStr($row['bg_amount']);
        if ($row['has_stamp']) $kpi['stamp'] += sumJsonStr($row['stamp_cost']);
    }
}

// --- 4. LIST ---
$sql_list = "SELECT * FROM $table_name $where_sql ORDER BY report_date DESC, created_at DESC LIMIT 200";
$result = $conn->query($sql_list);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <title>Executive Dashboard (Admin)</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">

    <script>
        // --- Prevent FOUC ---
        (function() {
            if (localStorage.getItem('tjc_theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.body?.classList.add('dark-mode');
            }
        })();
    </script>

    <style>
        /* --- 🎨 THEME CONFIGURATION --- */
        :root { 
            /* Light Mode */
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-hover: #f1f5f9;
            --bg-input: #ffffff;
            --bg-inner: #f8fafc;
            
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-label: #475569;
            
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            
            --primary-color: #4f46e5; /* Indigo for Admin */

            /* Status Colors (Light) */
            --c-exp-bg: #fef2f2; --c-exp-txt: #ef4444;
            --c-pr-bg: #eff6ff;  --c-pr-txt: #3b82f6;
            --c-job-bg: #f5f3ff; --c-job-txt: #8b5cf6;
            --c-bg-bg: #fffbeb;  --c-bg-txt: #f59e0b;
            --c-stamp-bg: #ecfdf5; --c-stamp-txt: #10b981;
            --c-accom: #ec4899; 
            --c-labor: #ef4444;
            --c-other: #f97316; 

             /* Highlight Backgrounds (Light) */
             --bg-hl-accom: #fff1f2;
             --bg-hl-labor: #fef2f2;
             --bg-hl-other: #fffbeb;
        }

        /* 🌙 Dark Mode Override */
        body.dark-mode {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-hover: #334155;
            --bg-input: #334155;
            --bg-inner: #0f172a;
            
            --text-main: #f8fafc;
            --text-muted: #cbd5e1;
            --text-label: #94a3b8;
            
            --border-color: #334155;
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.5);
            
            --primary-color: #60a5fa;

            /* Status Colors (Dark - Translucent) */
            --c-exp-bg: rgba(239, 68, 68, 0.15); --c-exp-txt: #f87171;
            --c-pr-bg: rgba(59, 130, 246, 0.15); --c-pr-txt: #60a5fa;
            --c-job-bg: rgba(124, 58, 237, 0.15); --c-job-txt: #a78bfa;
            --c-bg-bg: rgba(245, 158, 11, 0.15);  --c-bg-txt: #fbbf24;
            --c-stamp-bg: rgba(16, 185, 129, 0.15); --c-stamp-txt: #34d399;
            --c-accom: #f472b6; 
            --c-labor: #f87171;
            --c-other: #fb923c;

            /* Highlight Backgrounds (Dark) */
            --bg-hl-accom: rgba(219, 39, 119, 0.15);
            --bg-hl-labor: rgba(220, 38, 38, 0.15);
            --bg-hl-other: rgba(217, 119, 6, 0.15);
        }

        * { box-sizing: border-box; transition: background-color 0.3s, color 0.3s, border-color 0.3s; }
        body { font-family: 'Inter', 'Prompt', sans-serif; background: var(--bg-body); margin: 0; color: var(--text-main); font-size: 15px; }
        .main-container { max-width: 1440px; margin: 0 auto; padding: 40px 30px; }

        /* Header */
        .page-header { display: flex; flex-direction: column; gap: 20px; margin-bottom: 30px; }
        @media(min-width: 768px) { .page-header { flex-direction: row; justify-content: space-between; align-items: flex-end; } }
        .header-title h2 { margin: 0; font-size: 28px; font-weight: 800; color: var(--text-main); }
        .header-title p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }
        .btn-add { background: var(--primary-color); color: white; padding: 12px 28px; border-radius: 12px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: var(--shadow-md); transition: all 0.2s; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.15); }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .kpi-card { 
            background: var(--bg-card); padding: 25px; border-radius: 20px; 
            border: 1px solid var(--border-color); position: relative; overflow: hidden; 
            box-shadow: var(--shadow-sm); transition: 0.3s;
        }
        .kpi-card:hover { transform: translateY(-3px); border-color: var(--primary-color); box-shadow: var(--shadow-md); }
        .kpi-title { font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; }
        .kpi-value { font-size: 26px; font-weight: 800; color: var(--text-main); }
        .kpi-icon { position: absolute; right: -10px; bottom: -15px; font-size: 80px; opacity: 0.08; transform: rotate(-15deg); }
        .kpi-bar { position: absolute; top: 0; left: 0; width: 5px; height: 100%; }

        /* Filter */
        .filter-wrapper { 
            background: var(--bg-card); padding: 15px 25px; border-radius: 50px; 
            box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); 
            margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; gap: 20px;
            flex-wrap: wrap;
        }
        .filter-label { font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 10px; font-size: 16px; }
        .filter-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        
        .form-input { 
            padding: 10px 20px; border-radius: 25px; border: 1px solid var(--border-color); 
            background: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; font-size: 14px; outline: none; 
            transition: 0.2s;
        }
        .form-input:focus { border-color: var(--primary-color); }
        
        .btn-search { 
            background: var(--primary-color); color: white; border: none; width: 40px; height: 40px; 
            border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; 
            font-size: 16px; box-shadow: var(--shadow-sm);
        }
        .btn-search:hover { transform: scale(1.1); }
        
        .btn-reset { 
            display: flex; align-items: center; justify-content: center; height: 40px; padding: 0 20px; 
            color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 600; 
            border: 1px solid var(--border-color); border-radius: 25px; background: var(--bg-input);
            cursor: pointer; transition: 0.2s;
        }
        .btn-reset:hover { color: var(--primary-color); border-color: var(--primary-color); background: var(--bg-hover); }

        /* Table */
        .table-container { 
            background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border-color); 
            overflow: hidden; box-shadow: var(--shadow-sm); 
        }
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        
        th { 
            background: var(--bg-input); color: var(--text-main); font-weight: 800; font-size: 14px; 
            text-transform: uppercase; padding: 18px 30px; text-align: left; border-bottom: 2px solid var(--border-color); 
        }
        td { 
            padding: 20px 30px; border-bottom: 1px solid var(--border-color); 
            color: var(--text-main); font-size: 15px; vertical-align: middle; 
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-body); }

        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; margin-right: 6px; letter-spacing: 0.3px; }
        .b-exp { background: var(--c-exp-bg); color: var(--c-exp-txt); }
        .b-pr { background: var(--c-pr-bg); color: var(--c-pr-txt); }
        .b-job { background: var(--c-job-bg); color: var(--c-job-txt); }
        .b-bg { background: var(--c-bg-bg); color: var(--c-bg-txt); }
        .b-stamp { background: var(--c-stamp-bg); color: var(--c-stamp-txt); }
        .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(3px); align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); width: 95%; max-width: 650px; border-radius: 24px; box-shadow: var(--shadow-md); overflow: hidden; animation: zoomIn 0.25s cubic-bezier(0.16, 1, 0.3, 1); border: 1px solid var(--border-color); }
        @keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); }
        .modal-title { font-size: 20px; font-weight: 800; color: var(--text-main); }
        .btn-close { border: none; background: none; font-size: 28px; cursor: pointer; color: var(--text-muted); transition: 0.2s; }
        .btn-close:hover { color: var(--c-exp-txt); }
        .modal-body { padding: 30px; background: var(--bg-body); max-height: 80vh; overflow-y: auto; }

        /* Details */
        .detail-card { background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 20px; position: relative; overflow: hidden; box-shadow: var(--shadow-sm); }
        .detail-stripe { position: absolute; left: 0; top: 0; bottom: 0; width: 6px; }
        .detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 15px; }
        .detail-title { font-weight: 800; font-size: 18px; display: flex; align-items: center; gap: 10px; color: var(--text-main) !important; }
        
        .inner-item-container { display: flex; flex-direction: column; gap: 15px; margin-bottom: 15px; }
        .inner-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; position: relative; transition: 0.2s; }
        .inner-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px; color: var(--text-main); }
        .inner-label { color: var(--text-secondary); font-size: 13px; font-weight: 700; }
        .inner-val { font-weight: 700; color: var(--text-main); text-align: right; }
        
        .grand-total { background: var(--bg-card); border: 2px solid var(--primary-color); padding: 20px; border-radius: 16px; text-align: center; margin-top: 25px; box-shadow: var(--shadow-md); }
        .total-label { font-size: 14px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }
        .total-val { font-size: 34px; font-weight: 900; color: var(--primary-color); margin-top: 5px; }
        
        .btn-link-file { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--primary-color); text-decoration: none; border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 8px; background: var(--bg-input); font-weight: 600; transition: 0.2s; }
        .btn-link-file:hover { border-color: var(--primary-color); background: var(--bg-body); }
        
        /* Inner Highlight (Updated for Dark Mode Support) */
        .inner-val.inner-highlight {
            background: var(--bg-input);   /* ปรับใช้ตัวแปร */
            color: var(--primary-color);
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        /* --- Custom File Upload Style --- */
        .custom-file-upload {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .custom-file-upload:hover {
            background-color: #f1f5f9;
            border-color: var(--primary);
            color: var(--primary);
        }
        .custom-file-upload.has-file {
            background-color: #eff6ff; /* สีฟ้าอ่อนเมื่อมีไฟล์ */
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
            border-style: solid;
        }
        .custom-file-upload i {
            margin-right: 8px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-container">

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-bar" style="background:var(--c-accom);"></div>
                <div class="kpi-title">ค่าที่พัก</div>
                <div class="kpi-value" style="color:var(--c-accom);"><?php echo number_format($kpi['accom']); ?></div>
                <i class="fas fa-hotel kpi-icon" style="color:var(--c-accom);"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-bar" style="background:var(--c-labor);"></div>
                <div class="kpi-title">ค่าแรง (สุทธิ)</div>
                <div class="kpi-value" style="color:var(--c-labor);"><?php echo number_format($kpi['labor']); ?></div>
                <i class="fas fa-users kpi-icon" style="color:var(--c-labor);"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-bar" style="background:var(--c-pr-txt);"></div>
                <div class="kpi-title">งบประมาณ BOQ</div>
                <div class="kpi-value" style="color:var(--c-pr-txt);"><?php echo number_format($kpi['pr']); ?></div>
                <i class="fas fa-file-invoice kpi-icon" style="color:var(--c-pr-txt);"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-bar" style="background:var(--c-job-txt);"></div>
                <div class="kpi-title">งบประมาณโครงการ</div>
                <div class="kpi-value" style="color:var(--c-job-txt);"><?php echo number_format($kpi['job']); ?></div>
                <i class="fas fa-briefcase kpi-icon" style="color:var(--c-job-txt);"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-bar" style="background:var(--c-bg-txt);"></div>
                <div class="kpi-title">ยอดค้ำประกัน</div>
                <div class="kpi-value" style="color:var(--c-bg-txt);"><?php echo number_format($kpi['bg']); ?></div>
                <i class="fas fa-university kpi-icon" style="color:var(--c-bg-txt);"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-bar" style="background:var(--c-stamp-txt);"></div>
                <div class="kpi-title">ค่าใช้จ่ายตีตราสาร</div>
                <div class="kpi-value" style="color:var(--c-stamp-txt);"><?php echo number_format($kpi['stamp']); ?></div>
                <i class="fas fa-stamp kpi-icon" style="color:var(--c-stamp-txt);"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-bar" style="background:var(--c-other);"></div>
                <div class="kpi-title">ค่าใช้จ่ายอื่นๆ</div>
                <div class="kpi-value" style="color:var(--c-other);">
                    <?php echo number_format($kpi['other']); ?>
                </div>
                <i class="fas fa-coins kpi-icon" style="color:var(--c-other);"></i>
            </div>
            <div class="kpi-card" style="border-color: #6366f1;">
                <div class="kpi-bar" style="background: #6366f1;"></div>
                <div class="kpi-title">ยอดรวมเอกสาร (AX/PO)</div>
                <div class="kpi-value" style="color: #6366f1;">
                    <?php echo number_format($kpi['docs']); ?>
                </div>
                <i class="fas fa-file-contract kpi-icon" style="color: #6366f1;"></i>
            </div>
        </div>

        <form class="filter-wrapper">
            <div class="filter-label"><i class="fas fa-filter" style="color:var(--primary-color)"></i> ตัวกรองข้อมูล</div>
            <div class="filter-form">
                <select name="reporter" class="form-input" style="min-width: 180px;">
                    <option value="">-- พนักงานทั้งหมด --</option>
                    <?php foreach($reporters_list as $r_name): ?>
                        <option value="<?php echo $r_name; ?>" <?php echo ($selected_reporter == $r_name) ? 'selected' : ''; ?>><?php echo $r_name; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="start_date" value="<?php echo $start_date; ?>" class="form-input flatpickr" placeholder="dd/mm/yyyy">
                <span style="color:var(--text-muted); font-weight:bold;">ถึง</span>
                <input type="text" name="end_date" value="<?php echo $end_date; ?>" class="form-input flatpickr" placeholder="dd/mm/yyyy">
                
                <button class="btn-search" title="ค้นหา"><i class="fas fa-search"></i></button>
                <button type="button" class="btn-reset" onclick="showAllDates()" title="ดูทั้งหมด">ดูทั้งหมด</button>
                <a href="Dashboard_Admin.php" class="btn-reset" title="รีเซ็ต"><i class="fas fa-undo"></i></a>
            </div>
        </form>

        <div class="table-container">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่ / เวลา</th>
                            <th>ผู้ทำรายการ</th>
                            <th>รายการที่บันทึก</th>
                            <th style="text-align:right;">ยอดรวม (บาท)</th>
                            <th style="text-align:center;">ดู</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700; color:var(--text-main);">
                                        <?php 
                                            // ✅ แสดงวันที่แบบ dd/mm/yyyy พ.ศ.
                                            $ts = strtotime($row['report_date']);
                                            echo date('d/m/', $ts) . (date('Y', $ts) + 543); 
                                        ?>
                                    </div>
                                    <div style="font-size:12px; color:var(--text-muted); margin-top:2px; font-weight:600;">
                                        <?php echo date('H:i', strtotime($row['created_at'])); ?> น.
                                    </div>
                                </td>
                                <td style="font-weight:600; color:var(--text-secondary);"><?php echo $row['reporter_name']; ?></td>
                                <td>
                                    <?php 
                                        if($row['has_expense']) echo '<span class="badge b-exp"><div class="dot"></div> Expenses</span>';
                                        if($row['has_pr']) echo '<span class="badge b-pr"><div class="dot"></div> BOQ</span>';
                                        if($row['has_job']) echo '<span class="badge b-job"><div class="dot"></div> อัปงาน</span>';
                                        if($row['has_bg']) echo '<span class="badge b-bg"><div class="dot"></div> LG</span>';
                                        if($row['has_stamp']) echo '<span class="badge b-stamp"><div class="dot"></div> ตีตราสาร</span>';
                                    ?>
                                </td>
                                <td style="text-align:right; font-weight:800; font-size:16px; color:var(--text-main);"><?php echo number_format($row['total_amount']); ?></td>
                                <td style="text-align:center;">
                                    <button onclick='showDetail(<?php echo $row_json; ?>)' style="border:1px solid var(--border-color); background:var(--bg-card); padding:8px 12px; border-radius:10px; cursor:pointer; color:var(--text-secondary); transition:0.2s;"><i class="fas fa-eye"></i></button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--text-muted); font-weight:600;">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">รายละเอียดข้อมูล</div>
                <button class="btn-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

    <script>
        // ✅ 1. ตั้งค่าปฏิทิน Flatpickr
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".flatpickr", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d/m/Y",
                locale: "th",
                allowInput: true
            });
        });

        // ✅ 2. ฟังก์ชันปุ่ม "ดูทั้งหมด"
        function showAllDates() {
            const inputs = document.querySelectorAll('.flatpickr');
            inputs.forEach(input => {
                if (input._flatpickr) input._flatpickr.clear();
            });
            document.querySelector('input[name="start_date"]').value = '';
            document.querySelector('input[name="end_date"]').value = '';
            document.querySelector('.filter-wrapper').submit();
        }

        // ✅ 3. Helper: แปลง JSON String เป็น Array
        function safeParse(str) {
            try {
                let parsed = JSON.parse(str);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return str ? String(str).split(',') : [];
            }
        }

        // ✅ 4. Helper: สร้าง HTML สำหรับข้อมูลกลุ่ม
        function renderGroupedData(dataObj) {
            let html = '<div class="inner-item-container">';
            let keys = Object.keys(dataObj);
            if (keys.length === 0) return '';
            
            let firstArr = safeParse(dataObj[keys[0]].val || '[]'); 
            let count = firstArr.length;

            for (let i = 0; i < count; i++) {
                if (!firstArr[i]) continue;
                html += '<div class="inner-card">';
                keys.forEach(k => {
                    let vals = safeParse(dataObj[k].val || '[]'); 
                    let val = vals[i] || '-';
                    if (dataObj[k].isMoney) val = new Intl.NumberFormat().format(parseFloat(val) || 0) + ' ฿';
                    
                    html += `<div class="inner-row"><span class="inner-label">${dataObj[k].label}</span><span class="inner-val ${dataObj[k].highlight ? 'inner-highlight' : ''}">${val}</span></div>`;
                });
                html += '</div>';
            }
            html += '</div>';
            return html;
        }

        // ✅ 5. ฟังก์ชันหลัก: แสดงรายละเอียด (Modal)
        // ✅ 5. ฟังก์ชันหลัก: แสดงรายละเอียด (Modal) - ปรับปรุงย้ายยอดรวมเอกสารไปไว้ในกล่องสรุป
        function showDetail(d) {
            let html = `
                <div style="margin-bottom:20px; display:flex; justify-content:space-between; font-size:15px; color:var(--text-main); font-weight:700;">
                    <span><i class="far fa-calendar-alt"></i> ${d.report_date}</span>
                    <span><i class="far fa-user"></i> ${d.reporter_name}</span>
                </div>
            `;

            // --- Expense Section ---
            if (d.has_expense == 1) {
                let docs      = safeParse(d.exp_doc || '[]'); 
                let companies = safeParse(d.exp_company || '[]');
                let depts     = safeParse(d.exp_dept || '[]');
                let projs     = safeParse(d.exp_proj || '[]');
                let accoms    = safeParse(d.exp_accom || '[]'); 
                let labors    = safeParse(d.exp_labor || '[]'); 
                let files     = safeParse(d.exp_file || '[]');
                let others_desc = safeParse(d.exp_other_desc || '[]');
                let others_amt  = safeParse(d.exp_other_amount || '[]');
                let others_file = safeParse(d.exp_other_file || '[]');

                html += `
                <div class="detail-card">
                    <div class="detail-stripe" style="background:var(--c-exp-txt);"></div>
                    <div class="detail-header">
                        <div class="detail-title" style="color:var(--text-main);">
                            <i class="fas fa-file-invoice" style="color:var(--c-exp-txt);"></i> ค่าใช้จ่าย (Expenses)
                        </div>
                    </div>
                    <div class="inner-item-container">`;

                let count = Math.max(companies.length, depts.length, accoms.length);
                
                // [ตัวแปรเก็บผลรวม]
                let totalAccom = 0, totalLabor = 0, totalOther = 0, totalDocs = 0;

                for (let i = 0; i < count; i++) {
                    if (!companies[i] && !depts[i]) continue;

                    let ac = parseFloat(accoms[i]) || 0;
                    let lb = parseFloat(labors[i]) || 0;
                    let lb_net = lb * 0.97;
                    let oth_a = parseFloat(others_amt[i]) || 0;
                    let oth_d = others_desc[i] || '';
                    let oth_f = others_file[i] || '';

                    totalAccom += ac; totalLabor += lb; totalOther += oth_a;
                    let cardTotal = ac + lb_net + oth_a; 
                    let f = files[i] || '';

                    // --- ส่วนจัดการเอกสาร ---
                    let docRaw = docs[i] || '-';
                    let docHtml = '';
                    let subDocs = docRaw.split(',').map(s => s.trim()).filter(s => s);

                    if (subDocs.length > 0) {
                        docHtml = '<div style="display:flex; flex-direction:column; gap:8px; width:100%;">';
                        
                        subDocs.forEach(subDoc => {
                            let match = subDoc.match(/^(.*?)\s*\((.*?)\s*:\s*(.*?)\)$/);
                            if (match) {
                                let header = match[1].trim();
                                let item   = match[2].trim();
                                let price  = match[3].trim();
                                
                                // [คำนวณ] บวกยอดเอกสารสะสมเข้าตัวแปร totalDocs
                                totalDocs += parseFloat(price.replace(/,/g, '')) || 0;

                                docHtml += `
                                <div style="background:var(--bg-body); border:1px solid var(--border-color); border-left:4px solid var(--c-exp-txt); border-radius:6px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                                    <div style="font-weight:700; color:var(--text-main); font-size:13px; border-bottom:1px dashed var(--border-color); padding-bottom:5px; margin-bottom:5px; display:flex; align-items:center;">
                                        <i class="far fa-file-alt" style="margin-right:6px; color:var(--text-muted);"></i> ${header}
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px;">
                                        <span style="color:var(--text-label);"><i class="fas fa-tag" style="font-size:10px; opacity:0.5; margin-right:4px;"></i> ${item}</span>
                                        <span style="color:var(--c-exp-txt); font-weight:700; background:var(--c-exp-bg); padding:2px 8px; border-radius:4px;">${price} ฿</span>
                                    </div>
                                </div>`;
                            } else {
                                docHtml += `<div style="background:var(--bg-body); border:1px solid var(--border-color); border-radius:6px; padding:6px 10px; font-weight:600; color:var(--text-label); font-size:13px;">${subDoc}</div>`;
                            }
                        });
                        docHtml += '</div>';
                    } else {
                        docHtml = '-';
                    }
                    // -----------------------

                    html += `
                    <div class="inner-card">
                        <div class="inner-row" style="align-items: flex-start;"> 
                            <span class="inner-label" style="padding-top: 10px; white-space:nowrap;">เลขที่เอกสาร</span>
                            <span class="inner-val" style="text-align:right; width:70%;">${docHtml}</span>
                        </div>
                        
                        <div class="inner-row"><span class="inner-label">บริษัท</span><span class="inner-val">${companies[i] || '-'}</span></div>
                        <div class="inner-row"><span class="inner-label">หน่วยงาน</span><span class="inner-val">${depts[i] || '-'}</span></div>
                        <div class="inner-row"><span class="inner-label">โครงการ</span><span class="inner-val">${projs[i] || '-'}</span></div>
                        <hr style="border:0; border-top:1px dashed var(--border-color); margin:10px 0;">
                        
                        <div class="inner-row">
                            <span class="inner-label">🏨 ค่าที่พัก</span>
                            <span class="inner-val inner-highlight" style="color:var(--c-accom); border-color:var(--c-accom); background:var(--bg-hl-accom);">
                                ${new Intl.NumberFormat().format(ac)} ฿
                            </span>
                        </div>
                        
                        <div class="inner-row">
                            <span class="inner-label">👷 ค่าแรง</span>
                            <div style="text-align:right;">
                                <div class="inner-val inner-highlight" style="color:var(--c-labor); border-color:var(--c-labor); background:var(--bg-hl-labor);">
                                    ${new Intl.NumberFormat().format(lb)} ฿
                                </div>
                                <div style="font-size:12px; color:#059669; font-weight:600; margin-top:4px;">(สุทธิ: ${new Intl.NumberFormat().format(lb_net)})</div>
                            </div>
                        </div>
                        ${f ? `<div style="text-align:right; margin-top:5px;"><a href="uploads/admin/${f}" target="_blank" class="btn-link-file"><i class="fas fa-paperclip"></i> ไฟล์หลักฐาน</a></div>` : ''}

                        ${ (oth_a > 0 || oth_d !== '') ? `
                        <div style="background:var(--bg-hl-other); padding:10px; border-radius:8px; margin-top:10px; border:1px solid #fcd34d;">
                            <div style="font-size:12px; color:#b45309; font-weight:700; margin-bottom:5px;"><i class="fas fa-coins"></i> ค่าใช้จ่ายอื่นๆ</div>
                            <div class="inner-row"><span class="inner-label">รายละเอียด</span><span class="inner-val">${oth_d||'-'}</span></div>
                            <div class="inner-row">
                                <span class="inner-label">จำนวนเงิน</span>
                                <span class="inner-val inner-highlight" style="color:#d97706; border-color:#d97706; background:var(--bg-hl-other);">
                                    ${new Intl.NumberFormat().format(oth_a)} ฿
                                </span>
                            </div>
                            ${oth_f ? `<div style="text-align:right; margin-top:5px;"><a href="uploads/admin/${oth_f}" target="_blank" class="btn-link-file" style="border-color:#d97706; color:#d97706;"><i class="fas fa-paperclip"></i> ไฟล์แนบ</a></div>` : ''}
                        </div>
                        ` : '' }

                        <div style="margin-top:10px; padding-top:8px; border-top:2px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:12px; font-weight:700; color:var(--text-muted);">รวมรายการนี้</span>
                            <span style="font-size:16px; font-weight:800; color:var(--primary-color);">${new Intl.NumberFormat().format(cardTotal)} ฿</span>
                        </div>
                    </div>`;
                }
                html += `</div>`; 

                // Summary Calculation
                let totalWht = totalLabor * 0.03;
                let totalNet = totalAccom + (totalLabor * 0.97) + totalOther;

                html += `
                    <div class="box-summary" style="background:var(--bg-card); padding:15px; border-radius:12px; border:1px solid var(--border-color); margin-top:15px;">
                        <div class="inner-row"><span class="inner-label">รวมค่าที่พัก</span><span class="inner-val">${new Intl.NumberFormat().format(totalAccom)} ฿</span></div>
                        <div class="inner-row"><span class="inner-label">รวมค่าแรง</span><span class="inner-val">${new Intl.NumberFormat().format(totalLabor)} ฿</span></div>
                        
                        <div class="inner-row">
                            <span class="inner-label">รวมค่าเอกสาร</span>
                            <span class="inner-val" style="color:var(--primary-color); font-weight:700;">${new Intl.NumberFormat().format(totalDocs)} ฿</span>
                        </div>
                        
                        <div class="inner-row"><span class="inner-label">รวมค่าอื่นๆ</span><span class="inner-val" style="color:#d97706;">${new Intl.NumberFormat().format(totalOther)} ฿</span></div>
                        <div class="inner-row" style="color:var(--text-muted);">
                            <span class="inner-label">หัก ณ ที่จ่าย 3%</span>
                            <span class="inner-val" style="color:var(--c-exp-txt);">-${new Intl.NumberFormat().format(totalWht)} ฿</span>
                        </div>
                        <hr style="border:0; border-top:1px solid var(--border-color); margin:10px 0;">
                        <div class="inner-row" style="font-size:16px;">
                            <span class="inner-label" style="font-weight:800; color:var(--text-main);">ยอดสุทธิ (Net)</span>
                            <span class="inner-val" style="color:var(--primary-color); font-weight:900;">${new Intl.NumberFormat().format(totalNet)} ฿</span>
                        </div>
                    </div>
                </div>`; 
            }

            // --- ส่วน PR, Job, BG, Stamp (คงเดิม) ---
            if (d.has_pr == 1) {
                html += `
                <div class="detail-card">
                    <div class="detail-stripe" style="background:var(--c-pr-txt);"></div>
                    <div class="detail-header"><div class="detail-title" style="color:var(--text-main);"><i class="fas fa-file-invoice" style="color:var(--c-pr-txt);"></i> BOQ</div></div>
                    ${renderGroupedData({
                        dept: { label: 'หน่วยงาน', val: d.pr_dept },
                        proj: { label: 'โครงการ', val: d.pr_proj },
                        budg: { label: 'งบประมาณ', val: d.pr_budget, isMoney:true, highlight:true }
                    })}
                </div>`;
            }
            if (d.has_job == 1) {
                html += `
                <div class="detail-card">
                    <div class="detail-stripe" style="background:var(--c-job-txt);"></div>
                    <div class="detail-header"><div class="detail-title" style="color:var(--text-main);"><i class="fas fa-briefcase" style="color:var(--c-job-txt);"></i> แจ้งอัปงาน</div></div>
                    ${renderGroupedData({
                        num:  { label: 'เลขหน้างาน', val: d.job_num },
                        dept: { label: 'หน่วยงาน', val: d.job_dept },
                        proj: { label: 'โครงการ', val: d.job_proj },
                        budg: { label: 'งบโครงการ', val: d.job_budget, isMoney:true, highlight:true } 
                    })}
                </div>`;
            }
            if (d.has_bg == 1) {
                html += `
                <div class="detail-card">
                    <div class="detail-stripe" style="background:var(--c-bg-txt);"></div>
                    <div class="detail-header"><div class="detail-title" style="color:var(--text-main);"><i class="fas fa-university" style="color:var(--c-bg-txt);"></i> หนังสือค้ำประกัน</div></div>
                    ${renderGroupedData({
                        dept: { label: 'หน่วยงาน', val: d.bg_dept },
                        proj: { label: 'โครงการ', val: d.bg_proj },
                        amt:  { label: 'ยอดค้ำ', val: d.bg_amount, isMoney:true, highlight:true }
                    })}
                </div>`;
            }
            if (d.has_stamp == 1) {
                html += `
                <div class="detail-card">
                    <div class="detail-stripe" style="background:var(--c-stamp-txt);"></div>
                    <div class="detail-header"><div class="detail-title" style="color:var(--text-main);"><i class="fas fa-stamp" style="color:var(--c-stamp-txt);"></i> อากรแสตมป์</div></div>
                    ${renderGroupedData({
                        dept: { label: 'หน่วยงาน', val: d.stamp_dept },
                        proj: { label: 'โครงการ', val: d.stamp_proj },
                        cost: { label: 'ค่าอากร', val: d.stamp_cost, isMoney:true, highlight:true }
                    })}
                </div>`;
            }

            // --- ยอดรวมท้ายสุด ---
            html += `<div class="grand-total"><div class="total-label">ยอดรวมทั้งสิ้น (สุทธิ)</div><div class="total-val">${new Intl.NumberFormat().format(d.total_amount)} ฿</div></div>`;
            
            // --- Note ---
            if (d.note) { html += `<div style="background:var(--bg-body); border:2px solid var(--border-color); padding:20px; border-radius:12px; margin-top:15px; font-size:15px; color:var(--text-main); font-weight:600; white-space: pre-wrap ;"><b>Note:</b> ${d.note}</div>`; }

            // แสดง Modal
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('detailModal').style.display = 'flex';
        }

        // ✅ ฟังก์ชันปิด Modal
        function closeModal() { document.getElementById('detailModal').style.display = 'none'; }
        window.onclick = function(e) { if (e.target == document.getElementById('detailModal')) closeModal(); }
    </script>
</body>
</html>