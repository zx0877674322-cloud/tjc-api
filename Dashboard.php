<?php
session_start();
// require_once 'auth.php'; // เปิดใช้งานเมื่อระบบพร้อม
require_once 'db_connect.php'; 

// =========================================================
// 🚀 1. AJAX API (สำหรับดึงประวัติลูกค้า)
// =========================================================
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'get_customer_history') {
    $customer_name = $conn->real_escape_string($_GET['customer_name']);
    $s_date = $_GET['start_date'] ?? '';
    $e_date = $_GET['end_date'] ?? '';

    $sql_where = "WHERE work_result = '$customer_name'";
    if(!empty($s_date)) { $sql_where .= " AND report_date >= '$s_date'"; }
    if(!empty($e_date)) { $sql_where .= " AND report_date <= '$e_date'"; }

    $sql_hist = "SELECT report_date, reporter_name, job_status, total_expense, project_name, additional_notes 
                 FROM reports $sql_where ORDER BY report_date DESC";
                 
    $res_hist = $conn->query($sql_hist);
    $history_data = [];
    if ($res_hist) {
        while ($row = $res_hist->fetch_assoc()) {
            $history_data[] = $row;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($history_data);
    exit(); 
}

// --- CONFIG ---
$table_name = 'reports';
$upload_path = 'uploads/'; 
$page_title = 'Sales Dashboard';

// --- FILTER ---
$filter_name = $_GET['filter_name'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_sql = "WHERE 1=1";
if ($filter_name) $where_sql .= " AND reporter_name = '$filter_name'";
if ($start_date) $where_sql .= " AND report_date >= '$start_date'";
if ($end_date) $where_sql .= " AND report_date <= '$end_date'";
if ($filter_status) $where_sql .= " AND job_status = '$filter_status'";

// --- KPI CALCULATION ---
$status_counts = [];
$total_expense = 0;
$total_reports = 0;

$sql_stats = "SELECT job_status, COUNT(*) as count, SUM(total_expense) as expense FROM $table_name $where_sql GROUP BY job_status";
$res_stats = $conn->query($sql_stats);
if($res_stats) {
    while($row = $res_stats->fetch_assoc()) {
        $st = trim($row['job_status']) ?: 'ไม่ระบุ';
        $status_counts[$st] = $row['count'];
        $total_expense += $row['expense'];
        $total_reports += $row['count'];
    }
}

// --- DATA LIST ---
$sql_list = "SELECT * FROM $table_name $where_sql ORDER BY report_date DESC, id DESC";
$result_list = $conn->query($sql_list);

// --- OPTIONS ---
$users = $conn->query("SELECT DISTINCT reporter_name FROM $table_name ORDER BY reporter_name ASC");
$statuses = $conn->query("SELECT DISTINCT job_status FROM $table_name WHERE job_status != '' ORDER BY job_status ASC");

// ✅ Helper functions
function getCardConfig($status) {
    $s = trim($status);
    if (strpos($s, 'ได้งาน') !== false || strpos($s, 'สำเร็จ') !== false) return ['color' => '#10b981', 'icon' => 'fa-check-circle'];
    if (strpos($s, 'เข้าเสนอโครงการ') !== false || strpos($s, 'เสนอ') !== false) return ['color' => '#3b82f6', 'icon' => 'fa-briefcase']; // Blue Adjusted
    if (strpos($s, 'ติดตามงาน') !== false || strpos($s, 'ติดตาม') !== false || strpos($s, 'รอ') !== false) return ['color' => '#f59e0b', 'icon' => 'fa-clock'];
    if (strpos($s, 'ไม่ได้') !== false || strpos($s, 'ยกเลิก') !== false) return ['color' => '#ef4444', 'icon' => 'fa-times-circle'];
    
    $palette = ['#8b5cf6', '#ec4899', '#06b6d4', '#14b8a6', '#f97316', '#6366f1'];
    $hash = crc32($s); 
    $index = abs($hash) % count($palette);
    return ['color' => $palette[$index], 'icon' => 'fa-tag'];
}

function hexToRgba($hex, $alpha = 0.1) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "rgba($r, $g, $b, $alpha)";
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
        // --- Prevent FOUC ---
        (function() {
            if (localStorage.getItem('tjc_theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.body?.classList.add('dark-mode');
            }
        })();
    </script>

    <style>
        /* --- 🎨 THEME CONFIGURATION (Matches Sidebar) --- */
        :root { 
            /* Light Mode Defaults */
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --bg-hover: #f8fafc;
            --bg-input: #ffffff;
            --bg-inner: #f8fafc;
            
            --text-main: #1e293b;
            --text-sub: #64748b;
            --text-label: #475569;
            
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-card: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            
            --primary-color: #2563eb;
            --primary-soft: #eff6ff;

            /* Evidence Colors (Light) */
            --ev-fuel-bg: #fff7ed; --ev-fuel-text: #c2410c; --ev-fuel-border: #ffedd5;
            --ev-hotel-bg: #eff6ff; --ev-hotel-text: #1d4ed8; --ev-hotel-border: #dbeafe;
            --ev-other-bg: #fefce8; --ev-other-text: #a16207; --ev-other-border: #fef9c3;
        }

        /* 🌙 Dark Mode Override */
        body.dark-mode {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-hover: #334155;
            --bg-input: #334155;
            --bg-inner: #0f172a;
            
            --text-main: #f8fafc;
            --text-sub: #cbd5e1;
            --text-label: #94a3b8;
            
            --border-color: #334155;
            --shadow-card: 0 4px 10px rgba(0, 0, 0, 0.5);
            
            --primary-color: #60a5fa;
            --primary-soft: rgba(37, 99, 235, 0.2);

            /* Evidence Colors (Dark) */
            --ev-fuel-bg: rgba(234, 88, 12, 0.2); --ev-fuel-text: #fb923c; --ev-fuel-border: rgba(234, 88, 12, 0.3);
            --ev-hotel-bg: rgba(37, 99, 235, 0.2); --ev-hotel-text: #60a5fa; --ev-hotel-border: rgba(37, 99, 235, 0.3);
            --ev-other-bg: rgba(202, 138, 4, 0.2); --ev-other-text: #facc15; --ev-other-border: rgba(202, 138, 4, 0.3);
        }

        * { box-sizing: border-box; transition: background-color 0.3s, color 0.3s, border-color 0.3s; }
        body { font-family: 'Inter', 'Prompt', sans-serif; background: var(--bg-body); margin: 0; color: var(--text-main); font-size: 15px; }
        .main-container { max-width: 1440px; margin: 0 auto; padding: 40px 30px; }
        
        /* Header */
        .page-header { display: flex; flex-direction: column; gap: 20px; margin-bottom: 30px; }
        @media(min-width: 768px) { .page-header { flex-direction: row; justify-content: space-between; align-items: flex-end; } }
        .header-title h2 { margin: 0; font-size: 28px; font-weight: 800; color: var(--text-main); }
        .header-title p { margin: 5px 0 0; color: var(--text-sub); font-size: 14px; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--bg-card); padding: 20px; border-radius: 16px; box-shadow: var(--shadow-card); position: relative; overflow: hidden; border: 1px solid var(--border-color); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
        .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: var(--primary-color); }
        .kpi-label { font-size: 13px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; opacity: 0.8; }
        .kpi-value { font-size: 28px; font-weight: 800; color: var(--text-main); }
        .kpi-icon { position: absolute; right: -10px; bottom: -15px; font-size: 80px; opacity: 0.1; transform: rotate(-10deg); }

        /* Filter Section */
        .filter-section { background: var(--bg-card); padding: 25px; border-radius: 16px; margin-bottom: 25px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end; }
        .form-group { width: 100%; margin-bottom: 0; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-main); }
        
        .form-control { 
            width: 100%; padding: 0 12px; height: 45px; border-radius: 10px; 
            border: 1px solid var(--border-color); 
            font-family: 'Prompt'; font-size: 14px; 
            background-color: var(--bg-input); 
            color: var(--text-main); 
            transition: 0.2s; 
        }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        
        .button-group { display: flex; gap: 10px; height: 45px; }
        .btn-search { background: var(--primary-color); color: white; border: none; padding: 0 24px; height: 100%; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
        .btn-search:hover { transform: translateY(-2px); box-shadow: var(--shadow-card); }
        .btn-reset { display: flex; align-items: center; justify-content: center; height: 100%; padding: 0 15px; color: var(--text-sub); text-decoration: none; font-size: 14px; font-weight: 500; border-radius: 10px; transition: 0.2s; border: 1px solid transparent; }
        .btn-reset:hover { background: var(--bg-hover); color: var(--primary-color); }

        /* Table */
        .table-card { background: var(--bg-card); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
        th { background: var(--bg-hover); color: var(--text-sub); font-weight: 700; font-size: 13px; padding: 16px 20px; text-align: left; border-bottom: 1px solid var(--border-color); text-transform: uppercase; }
        td { padding: 16px 20px; border-bottom: 1px solid var(--border-color); font-size: 14px; vertical-align: middle; color: var(--text-main); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-hover); }
        
        .status-badge { padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }

        .btn-view { border: 1px solid var(--border-color); background: var(--bg-input); width: 36px; height: 36px; border-radius: 8px; cursor: pointer; color: var(--text-sub); transition: 0.2s; display: flex; align-items: center; justify-content: center; }
        .btn-view:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }

        .btn-evidence { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; border: 1px solid transparent; transition: 0.2s; }
        .btn-evidence:hover { transform: translateY(-2px); filter: brightness(0.95); }
        
        .ev-fuel { background: var(--ev-fuel-bg); color: var(--ev-fuel-text); border: 1px solid var(--ev-fuel-border); }
        .ev-hotel { background: var(--ev-hotel-bg); color: var(--ev-hotel-text); border: 1px solid var(--ev-hotel-border); }
        .ev-other { background: var(--ev-other-bg); color: var(--ev-other-text); border: 1px solid var(--ev-other-border); }
        
        .gps-tag-office { background: var(--bg-hover); color: var(--text-sub); border: 1px solid var(--border-color); }
        .gps-tag-out { background: var(--primary-soft); color: var(--primary-color); border: 1px solid var(--primary-soft); }

        .customer-link { cursor: pointer; color: var(--primary-color); font-weight: 600; text-decoration: none; transition: 0.2s; border-bottom: 1px dashed transparent; }
        .customer-link:hover { border-bottom: 1px dashed var(--primary-color); opacity: 0.8; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }
        .modal-content { 
            background: var(--bg-card); 
            color: var(--text-main); 
            margin: 5vh auto; padding: 0; border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; 
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); border: 1px solid var(--border-color); 
        }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: var(--bg-card); z-index: 10; }
        .modal-header h3 { margin: 0; font-size: 18px; color: var(--text-main); }
        .modal-close { cursor: pointer; font-size: 24px; color: var(--text-sub); transition: 0.2s; }
        .modal-close:hover { color: #ef4444; }

        .modal-body { padding: 25px; background: var(--bg-body); }
        .d-group { margin-bottom: 15px; }
        .d-lbl { font-size: 12px; color: var(--text-label); font-weight: 700; margin-bottom: 4px; text-transform: uppercase; }
        .d-val { font-size: 15px; font-weight: 500; color: var(--text-main); }
        
        .gps-box { background: var(--primary-soft); padding: 15px; border-radius: 10px; margin-bottom: 15px; }
        
        .expense-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; text-align: center; margin-top: 5px; }
        .ex-box { 
            background: var(--bg-inner); 
            padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); 
        }
        .note-box { 
            background: var(--bg-inner); 
            padding: 12px; border-radius: 10px; border: 1px solid var(--border-color); 
            font-size: 14px; line-height: 1.6; color: var(--text-main); 
        }

        /* Highlight Inner Value */
        .inner-val.inner-highlight {
            background: var(--bg-input);   
            color: var(--primary-color);
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-weight: 700;
        }

        /* History Modal */
        .hist-item { padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 8px; }
        .hist-item:last-child { border-bottom: none; }
        .hist-header { display: flex; justify-content: space-between; font-size: 13px; color: var(--text-sub); margin-bottom: 5px; }
        .hist-user { font-weight: 700; font-size: 15px; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        .hist-project { font-size: 14px; color: var(--primary-color); font-weight: 600; }
        .hist-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; background: var(--bg-hover); color: var(--text-sub); font-size: 12px; }
        .hist-note { 
            background: var(--bg-inner); 
            padding: 10px; border-radius: 8px; font-size: 13px; color: var(--text-main); line-height: 1.5; margin-top: 5px; 
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <script>
        // Check local storage immediately
        if (localStorage.getItem('tjc_theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
    </script>

    <?php include 'sidebar.php'; ?>
    <div class="main-container">
        <div class="page-header">
            <div class="header-title">
                <h2>Sales Dashboard</h2>
                <p>ภาพรวมการปฏิบัติงานฝ่ายขาย</p>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" onclick="filterByStatus('')" style="border-left: 5px solid var(--primary-color);">
                <div class="kpi-label" style="color: var(--primary-color);">รายงานทั้งหมด</div>
                <div class="kpi-value"><?php echo number_format($total_reports); ?></div>
                <i class="fas fa-file-alt kpi-icon" style="color: var(--primary-color);"></i>
            </div>

            <?php foreach($status_counts as $st => $cnt): 
                $cfg = getCardConfig($st); 
            ?>
            <div class="kpi-card" onclick="filterByStatus('<?php echo $st; ?>')" style="border-left: 5px solid <?php echo $cfg['color']; ?>;">
                <div class="kpi-label" style="color: <?php echo $cfg['color']; ?>;"><?php echo $st; ?></div>
                <div class="kpi-value"><?php echo number_format($cnt); ?></div>
                <i class="fas <?php echo $cfg['icon']; ?> kpi-icon" style="color: <?php echo $cfg['color']; ?>;"></i>
            </div>
            <?php endforeach; ?>

            <div class="kpi-card" style="border-left: 5px solid #ef4444; cursor: default;">
                <div class="kpi-label" style="color: #ef4444;">ค่าใช้จ่ายรวม</div>
                <div class="kpi-value" style="color: #ef4444;"><?php echo number_format($total_expense); ?> ฿</div>
                <i class="fas fa-wallet kpi-icon" style="color: #ef4444;"></i>
            </div>
        </div>

        <form class="filter-section">
            <div class="filter-form">
                <div class="form-group">
                    <label class="form-label">พนักงาน</label>
                    <select name="filter_name" class="form-control">
                        <option value="">ทั้งหมด</option>
                        <?php while($u=$users->fetch_assoc()){ echo "<option value='{$u['reporter_name']}' ".($filter_name==$u['reporter_name']?'selected':'').">{$u['reporter_name']}</option>"; } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">สถานะ</label>
                    <select name="filter_status" class="form-control">
                        <option value="">ทั้งหมด</option>
                        <?php while($s=$statuses->fetch_assoc()){ echo "<option value='{$s['job_status']}' ".($filter_status==$s['job_status']?'selected':'').">{$s['job_status']}</option>"; } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">วันที่เริ่ม</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">ถึงวันที่</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                </div>
                <div class="form-group">
                    <div class="button-group">
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> ค้นหา</button>
                        <a href="Dashboard.php" class="btn-reset"><i class="fas fa-undo"></i> รีเซ็ต</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่/เวลา</th><th>พนักงาน</th><th>ลูกค้า/กิจกรรม</th><th>โครงการ</th><th>สถานะ</th><th>ค่าใช้จ่าย</th><th style="text-align:center;">หลักฐาน</th><th style="text-align:center;">ดู</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result_list && $result_list->num_rows > 0): while($row = $result_list->fetch_assoc()): 
                            $row['std_fuel'] = (float)($row['fuel'] ?? $row['fuel_cost'] ?? $row['fuel_expense'] ?? 0);
                            $row['std_hotel'] = (float)($row['accommodation'] ?? $row['hotel'] ?? $row['hotel_cost'] ?? $row['accommodation_cost'] ?? 0);
                            $row['std_other'] = (float)($row['other'] ?? $row['other_cost'] ?? $row['public_transport'] ?? $row['other_expense'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:var(--text-main);"><?php echo date('d/m/Y', strtotime($row['report_date'])); ?></div>
                                <div style="font-size:12px; color:var(--text-sub); margin-top:2px;"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                            </td>
                            <td>
                                <div>
                                    <div style="font-weight:600; color:var(--text-main); margin-bottom: 4px; white-space: nowrap;"><?php echo $row['reporter_name']; ?></div>
                                    <?php if(isset($row['gps']) && $row['gps'] == 'Office'): ?>
                                        <span class="status-badge gps-tag-office"><i class="fas fa-building"></i> ออฟฟิศ</span>
                                    <?php else: ?>
                                        <span class="status-badge gps-tag-out"><i class="fas fa-map-marker-alt"></i> นอกสถานที่</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="customer-link" onclick="showCustomerHistory('<?php echo htmlspecialchars($row['work_result']); ?>')">
                                    <i class="fas fa-history" style="font-size:12px; margin-right:5px; opacity:0.6;"></i>
                                    <?php echo $row['work_result']; ?>
                                </div>
                                <div style="font-size:12px; color:var(--text-sub); margin-top:2px;"><?php echo $row['activity_type']; ?></div>
                            </td>
                            <td><?php echo $row['project_name']; ?></td>
                            <td>
                                <?php 
                                    $cfg = getCardConfig($row['job_status']);
                                    $bg_color = hexToRgba($cfg['color'], 0.1); 
                                ?>
                                <span class='status-badge' style="background-color: <?php echo $bg_color; ?>; color: <?php echo $cfg['color']; ?>; border: 1px solid <?php echo hexToRgba($cfg['color'], 0.2); ?>;">
                                    <i class='fas <?php echo $cfg['icon']; ?>'></i> <?php echo $row['job_status']; ?>
                                </span>
                            </td>
                            <td style="font-weight:700; color:var(--ev-fuel-text);"><?php echo number_format($row['total_expense']); ?></td>
                            
                            <td style="text-align:center;">
                                <div style="display:flex; justify-content:center; gap:5px;">
                                    <?php 
                                    $has_ev = false;
                                    if(!empty($row['fuel_receipt'])) {
                                        echo '<a href="'.$upload_path.$row['fuel_receipt'].'" target="_blank" class="btn-evidence ev-fuel" title="บิลน้ำมัน"><i class="fas fa-gas-pump"></i></a>';
                                        $has_ev = true;
                                    }
                                    if(!empty($row['accommodation_receipt'])) {
                                        echo '<a href="'.$upload_path.$row['accommodation_receipt'].'" target="_blank" class="btn-evidence ev-hotel" title="บิลที่พัก"><i class="fas fa-bed"></i></a>';
                                        $has_ev = true;
                                    }
                                    if(!empty($row['other_receipt'])) {
                                        echo '<a href="'.$upload_path.$row['other_receipt'].'" target="_blank" class="btn-evidence ev-other" title="บิลอื่นๆ"><i class="fas fa-receipt"></i></a>';
                                        $has_ev = true;
                                    }
                                    if(!$has_ev) echo '<span style="color:var(--text-sub); font-size:12px;">-</span>';
                                    ?>
                                </div>
                            </td>

                            <td style="text-align:center;"><button onclick='showDetail(<?php echo json_encode($row); ?>)' class="btn-view"><i class="fas fa-eye"></i></button></td>
                        </tr>
                        <?php endwhile; else: echo "<tr><td colspan='8' style='text-align:center; padding:30px; color:var(--text-sub);'>ไม่พบข้อมูล</td></tr>"; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="detailModal" class="modal" onclick="if(event.target==this)closeModal('detailModal')">
        <div class="modal-content">
            <div class="modal-header">
                <h3>รายละเอียดรายงาน</h3>
                <span onclick="closeModal('detailModal')" class="modal-close">&times;</span>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <div id="historyModal" class="modal" onclick="if(event.target==this)closeModal('historyModal')">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="histModalTitle"><i class="fas fa-users"></i> ประวัติ: ลูกค้า</h3>
                <span onclick="closeModal('historyModal')" class="modal-close">&times;</span>
            </div>
            <div class="modal-body" id="histModalBody">
                <div style="text-align:center; padding:20px; color:var(--text-sub);">กำลังโหลดข้อมูล...</div>
            </div>
        </div>
    </div>

    <script>
        function filterByStatus(status) {
            const statusSelect = document.querySelector('select[name="filter_status"]');
            if(statusSelect) { statusSelect.value = status; }
            const filterForm = document.querySelector('.filter-section');
            if(filterForm) { 
                // Check if it's a form element or just a div
                if(filterForm.tagName === 'FORM') {
                    filterForm.submit();
                } else {
                    // Fallback: Try finding a form inside
                    const actualForm = filterForm.querySelector('form');
                    if(actualForm) actualForm.submit();
                }
            }
        }

        function showDetail(data) {
            let fuel = parseFloat(data.std_fuel || 0);
            let hotel = parseFloat(data.std_hotel || 0);
            let other = parseFloat(data.std_other || 0);
            let total = parseFloat(data.total_expense || 0);
            
            let html = `
                <div style="display:flex; gap:20px; margin-bottom:15px;">
                    <div style="flex:1;"><div class="d-lbl">ผู้รายงาน</div><div class="d-val">${data.reporter_name}</div></div>
                    <div style="flex:1;"><div class="d-lbl">วันที่รายงาน</div><div class="d-val">${data.report_date}</div></div>
                </div>
                <div class="d-group"><div class="d-lbl">ลูกค้า / งาน</div><div class="d-val">${data.work_result}</div></div>
                <div class="d-group"><div class="d-lbl">โครงการ</div><div class="d-val">${data.project_name}</div></div>
                
                <div class="d-group" style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                    <span class="d-lbl">ยอดเบิกจ่ายรวม</span>
                    <span class="inner-val inner-highlight">${total.toLocaleString()} ฿</span>
                </div>
            `;
            
            if(data.gps && data.gps !== 'Office') {
                html += `<div class="gps-box"><div class="d-lbl" style="color:var(--primary-color);"><i class="fas fa-map-marker-alt"></i> GPS Check-in</div>
                         <div style="font-size:14px; margin-top:5px; word-break:break-all;">${data.gps_address || data.gps}</div>
                         <div style="font-size:13px; margin-top:5px; color:var(--text-sub);"><b>โซน:</b> ${data.area || '-'} | <b>จ.:</b> ${data.province || '-'}</div></div>`;
            }

            html += `<div class="d-group"><div class="d-lbl">รายละเอียดค่าใช้จ่าย</div>
                     <div class="expense-grid">
                        <div class="ex-box"><div style="font-size:11px; color:var(--text-sub);">น้ำมัน</div><div style="color:var(--ev-fuel-text); font-weight:700;">${fuel.toLocaleString()}</div></div>
                        <div class="ex-box"><div style="font-size:11px; color:var(--text-sub);">ที่พัก</div><div style="color:var(--ev-hotel-text); font-weight:700;">${hotel.toLocaleString()}</div></div>
                        <div class="ex-box"><div style="font-size:11px; color:var(--text-sub);">อื่นๆ</div><div style="color:var(--ev-other-text); font-weight:700;">${other.toLocaleString()}</div></div>
                     </div></div>`;
            
            html += `<div class="d-group"><div class="d-lbl">บันทึกเพิ่มเติม</div><div class="note-box">${data.problem || data.additional_notes || '-'}</div></div>`;

            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('detailModal').style.display = 'block';
        }

        function showCustomerHistory(customerName) {
            document.getElementById('histModalTitle').innerHTML = '<i class="fas fa-history"></i> ประวัติ: ' + customerName;
            document.getElementById('histModalBody').innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-sub);"><i class="fas fa-spinner fa-spin"></i> กำลังโหลดข้อมูล...</div>';
            document.getElementById('historyModal').style.display = 'block';

            var startDate = document.querySelector('input[name="start_date"]').value;
            var endDate = document.querySelector('input[name="end_date"]').value;

            var url = '?ajax_action=get_customer_history&customer_name=' + encodeURIComponent(customerName);
            if(startDate) url += '&start_date=' + startDate;
            if(endDate) url += '&end_date=' + endDate;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        document.getElementById('histModalBody').innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-sub);">ไม่พบประวัติในช่วงเวลาที่เลือก</div>';
                        return;
                    }

                    let listHtml = '';
                    data.forEach(item => {
                        let expense = parseFloat(item.total_expense) > 0 ? `<span style="color:#ef4444; font-size:12px;">(฿${parseFloat(item.total_expense).toLocaleString()})</span>` : '';
                        let projectHtml = item.project_name ? `<div class="hist-project"><i class="fas fa-folder"></i> ${item.project_name}</div>` : '';
                        let noteHtml = item.additional_notes ? `<div class="hist-note"><i class="far fa-comment-dots"></i> ${item.additional_notes}</div>` : '';

                        listHtml += `
                            <div class="hist-item">
                                <div class="hist-header">
                                    <span><i class="far fa-calendar-alt"></i> ${item.report_date}</span>
                                    ${expense}
                                </div>
                                <div class="hist-user">
                                    <i class="fas fa-user-circle" style="color:var(--primary-color);"></i> ${item.reporter_name}
                                </div>
                                ${projectHtml}
                                <div style="margin-top:5px;">
                                    <span class="hist-badge">สถานะ: ${item.job_status}</span>
                                </div>
                                ${noteHtml}
                            </div>
                        `;
                    });
                    document.getElementById('histModalBody').innerHTML = listHtml;
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('histModalBody').innerHTML = '<div style="color:red; text-align:center;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
                });
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    </script>
</body>
</html>