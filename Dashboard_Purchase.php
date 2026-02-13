<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');
// --- CONFIG ---
$table_name = 'report_purchases';
$page_title = 'Purchasing Dashboard';
$primary_color = '#059669'; // Emerald Green
$primary_soft = '#d1fae5';
$upload_path = 'uploads/'; // 📁 ตรวจสอบว่ารูปอยู่ใน folder นี้จริงไหม

// --- FILTER ---
$filter_name = $_GET['filter_name'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_sql = "WHERE 1=1";
if ($filter_name) $where_sql .= " AND reporter_name = '$filter_name'";
if ($start_date) $where_sql .= " AND report_date >= '$start_date'";
if ($end_date) $where_sql .= " AND report_date <= '$end_date'";
if ($filter_status) $where_sql .= " AND tax_invoice_status LIKE '%$filter_status%'";

// --- KPI ---
$status_counts = [];
$total_expense = 0;
$total_reports = 0;

$sql_raw = "SELECT tax_invoice_status, total_expense FROM $table_name $where_sql";
$res_raw = $conn->query($sql_raw);
while($row = $res_raw->fetch_assoc()) {
    $total_expense += $row['total_expense'];
    $total_reports++;
    
    $raws = explode(',', $row['tax_invoice_status'] ?? '');
    foreach($raws as $r) {
        $parts = explode(':', $r);
        $clean = trim(end($parts));
        if($clean) $status_counts[$clean] = ($status_counts[$clean] ?? 0) + 1;
    }
}
ksort($status_counts);

// --- DATA ---
$sql_list = "SELECT * FROM $table_name $where_sql ORDER BY report_date DESC, id DESC";
$result_list = $conn->query($sql_list);
$users = $conn->query("SELECT DISTINCT reporter_name FROM $table_name ORDER BY reporter_name ASC");
$status_keys = array_keys($status_counts);

// ✅ Helper Function for Badges
function getStatusConfig($status) {
    if (strpos($status, 'ได้รับ')!==false || strpos($status, 'เรียบร้อย')!==false || strpos($status, 'ชำระแล้ว')!==false) 
        return ['bg'=>'rgba(16, 185, 129, 0.15)', 'text'=>'#10b981', 'dot'=>'#10b981', 'icon'=>'fa-check']; 
    
    if (strpos($status, 'รอ')!==false || strpos($status, 'กำลัง')!==false) 
        return ['bg'=>'rgba(245, 158, 11, 0.15)', 'text'=>'#f59e0b', 'dot'=>'#f59e0b', 'icon'=>'fa-clock'];
        
    return ['bg'=>'rgba(100, 116, 139, 0.15)', 'text'=>'#64748b', 'dot'=>'#94a3b8', 'icon'=>'fa-file-invoice'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        /* --- 🎨 THEME VARIABLES --- */
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
            
            --primary-color: #059669; /* Emerald */
            --primary-soft: #d1fae5;

            /* Shop Box Colors (Light) */
            --bg-shop-head: #f1f5f9;
            --text-shop-head: #059669;
        }

        /* 🌙 Dark Mode Override */
        body.dark-mode {
            --bg-body: #0f172a; 
            --bg-card: #1e293b; 
            --bg-hover: #334155; 
            --bg-input: #334155;
            --bg-inner: #0f172a;
            
            --text-main: #f1f5f9; 
            --text-muted: #cbd5e1; 
            --text-label: #94a3b8;
            
            --border-color: #334155;
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.5);
            
            --primary-color: #34d399; /* Emerald Lighter */
            --primary-soft: rgba(5, 150, 105, 0.2);

            /* Shop Box Colors (Dark) */
            --bg-shop-head: #334155;
            --text-shop-head: #34d399;
        }
        
        * { box-sizing: border-box; transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease; }
        body { font-family: 'Prompt', sans-serif; background: var(--bg-body); margin: 0; color: var(--text-main); font-size:15px; }
        .main-container { max-width: 1440px; margin: 0 auto; padding: 40px 30px; }
        
        /* Header */
        .page-header { display: flex; flex-direction: column; gap: 20px; margin-bottom: 30px; position: relative; }
        @media(min-width: 768px) { .page-header { flex-direction: row; justify-content: space-between; align-items: flex-end; } }
        .header-title h2 { margin: 0; font-size: 28px; font-weight: 800; color: var(--text-main); }
        .header-title p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .kpi-card { 
            background: var(--bg-card); padding: 20px; border-radius: 16px; 
            box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); 
            position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center;
            height: 100px; transition: 0.3s;
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--primary-color); }
        .kpi-label { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px; }
        .kpi-value { font-size: 26px; font-weight: 800; color: var(--text-main); line-height: 1; z-index: 1; }
        .kpi-icon-bg { position: absolute; right: -10px; bottom: -15px; font-size: 80px; opacity: 0.08; transform: rotate(-10deg); color: currentColor; pointer-events: none; }

        /* Filter Section */
        .filter-wrapper { 
            background: var(--bg-card); padding: 15px 25px; border-radius: 50px; 
            box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); 
            margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; gap: 20px;
            flex-wrap: wrap;
        }
        .filter-header { font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .filter-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        
        .form-input { 
            padding: 8px 15px; border-radius: 20px; border: 1px solid var(--border-color); 
            background: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; font-size: 13px; outline: none; 
            transition: 0.2s;
        }
        .form-input:focus { border-color: var(--primary-color); }
        
        .btn-search { 
            background: var(--primary-color); color: white; border: none; width: 36px; height: 36px; 
            border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; 
        }
        .btn-search:hover { transform: scale(1.1); }
        
        .btn-reset { 
            display: flex; align-items: center; justify-content: center; height: 100%; padding: 0 15px; 
            color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 600; 
            border: 1px solid transparent; border-radius: 20px; 
        }
        .btn-reset:hover { color: var(--primary-color); background: var(--bg-hover); }

        /* Table */
        .table-container { background: var(--bg-card); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
        .table-responsive { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        
        th { background: var(--bg-hover); color: var(--text-muted); font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 15px 25px; text-align: left; border-bottom: 2px solid var(--border-color); white-space: nowrap; }
        td { padding: 12px 25px; border-bottom: 1px solid var(--border-color); color: var(--text-main); font-size: 14px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--bg-hover); }

        /* Helper Classes */
        .status-badge { 
            padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; 
            display: inline-flex; align-items: center; gap: 5px; 
            border: 1px solid transparent; white-space: nowrap;
        }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        .btn-view { 
            border: 1px solid var(--border-color); background: var(--bg-input); width: 32px; height: 32px; 
            border-radius: 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: 0.2s;
        }
        .btn-view:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }

        .btn-evidence { 
            display: inline-flex; align-items: center; justify-content: center; 
            width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent; 
            transition: 0.2s; cursor: pointer; text-decoration: none;
        }
        .btn-evidence:hover { transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-content { 
            background: var(--bg-card); color: var(--text-main);
            width: 95%; max-width: 650px; max-height: 90vh; border-radius: 20px; 
            overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid var(--border-color); animation: slideIn 0.3s ease; 
        }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header { padding: 15px 25px; border-bottom: 1px solid var(--border-color); background: var(--bg-card); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .modal-title { font-size: 16px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        .btn-close { width: 30px; height: 30px; border-radius: 50%; border: none; background: var(--bg-hover); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); }
        .btn-close:hover { background: #ef4444; color: white; }
        .modal-body { padding: 25px; background: var(--bg-body); }

        /* Modal Content Styles */
        .shop-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 15px; overflow: hidden; }
        .shop-head { background: var(--bg-shop-head); padding: 12px 20px; font-weight: 700; color: var(--text-shop-head); font-size: 14px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px; }
        .shop-body { padding: 15px 20px; font-size: 13px; line-height: 1.6; color: var(--text-main); }
        
        .d-group { display: flex; gap: 15px; margin-bottom: 15px; }
        .d-item { flex: 1; }
        .d-lbl { font-size: 11px; color: var(--text-muted); font-weight: 700; margin-bottom: 4px; text-transform: uppercase; }
        .d-val { font-size: 14px; font-weight: 600; color: var(--text-main); }
        
        .note-box { background: var(--bg-card); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); margin-top: 15px; font-size: 13px; color: var(--text-main); line-height: 1.6; }
        
        .total-box { margin-top: 20px; display: flex; justify-content: space-between; align-items: center; background: rgba(239, 68, 68, 0.1); padding: 15px 20px; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.3); }
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
                <h2>Purchasing Dashboard</h2>
                <p>ภาพรวมการจัดซื้อและการเบิกจ่าย</p>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">บิลทั้งหมด</div>
                <div class="kpi-value" style="color:var(--primary-color);"><?php echo number_format($total_reports); ?></div>
                <i class="fas fa-file-invoice kpi-icon-bg" style="color:var(--primary-color);"></i>
            </div>
            
            <?php foreach($status_counts as $st => $cnt): 
                $conf=getStatusConfig($st); 
            ?>
            <div class="kpi-card">
                <div class="kpi-label" style="color:<?php echo $conf['text']; ?>;"><?php echo $st; ?></div>
                <div class="kpi-value" style="color:<?php echo $conf['text']; ?>;"><?php echo number_format($cnt); ?></div>
                <i class="fas <?php echo $conf['icon']; ?> kpi-icon-bg" style="color:<?php echo $conf['text']; ?>;"></i>
            </div>
            <?php endforeach; ?>
            
            <div class="kpi-card">
                <div class="kpi-label" style="color:#ef4444;">ยอดซื้อสุทธิ</div>
                <div class="kpi-value" style="color:#ef4444;"><?php echo number_format($total_expense); ?> ฿</div>
                <i class="fas fa-coins kpi-icon-bg" style="color:#ef4444;"></i>
            </div>
        </div>

        <form class="filter-wrapper" method="GET">
            <div class="filter-header"><i class="fas fa-list-ul" style="color:var(--primary-color);"></i> รายการจัดซื้อของฉัน</div>
            <div class="filter-form">
                <input type="text" name="filter_name" value="<?php echo $filter_name; ?>" class="form-input" placeholder="ค้นหา: พนักงาน..." style="min-width: 150px;">
                
                <select name="filter_status" class="form-input" style="min-width: 150px;">
                    <option value="">-- สถานะบิลทั้งหมด --</option>
                    <?php foreach($status_keys as $s){ echo "<option value='$s' ".($filter_status==$s?'selected':'').">$s</option>"; } ?>
                </select>

                <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-input">
                <span style="color:var(--text-muted);">-</span>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-input">
                
                <button class="btn-search"><i class="fas fa-search"></i></button>
                <a href="Dashboard_Purchase.php" class="btn-reset">รีเซ็ต</a>
            </div>
        </form>

        <div class="table-container">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>พนักงาน</th>
                            <th>ร้านค้า/ผู้ขาย</th>
                            <th>โครงการ</th>
                            <th>จำนวน</th>
                            <th>สถานะบิล</th>
                            <th style="text-align:right">ยอดรวม</th>
                            <th style="text-align:center;">หลักฐาน</th>
                            <th style="text-align:center;">ดู</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result_list->num_rows > 0): while($row = $result_list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:var(--text-main);"><?php echo date('d/m/Y', strtotime($row['report_date'])); ?></div>
                                <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px; white-space:nowrap;">
                                    <span style="font-weight:600; color:var(--text-main);"><?php echo $row['reporter_name']; ?></span>
                                </div>
                            </td>
                            <td><span style="color:var(--primary-color); font-weight:700;"><?php echo $row['supplier_name']; ?></span></td>
                            <td><?php echo $row['project_name']; ?></td>
                            <td style="text-align:center;"><span style="background:var(--bg-hover); color:var(--text-muted); padding:3px 8px; border-radius:6px; font-weight:600; font-size:12px; border:1px solid var(--border-color);"><?php echo $row['item_count']; ?></span></td>
                            <td>
                                <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                <?php 
                                $statuses = explode(',', $row['tax_invoice_status']);
                                foreach($statuses as $st) {
                                    $clean = trim(explode(':', $st)[1] ?? $st); 
                                    if($clean) {
                                        $c = getStatusConfig($clean);
                                        echo "<span class='status-badge' style='background:{$c['bg']}; color:{$c['text']}; border:1px solid {$c['text']}20;'>
                                                <span class='status-dot' style='background:{$c['text']}'></span> $clean
                                              </span>";
                                    }
                                }
                                ?>
                                </div>
                            </td>
                            <td style="font-weight:700; color:#ef4444; font-size:15px; text-align:right;"><?php echo number_format($row['total_expense']); ?></td>
                            
                            <td style="text-align:center;">
                                <div style="display:flex; justify-content:center; gap:5px;">
                                    <?php 
                                    $has_ev = false;
                                    if (!empty($row['expense_files'])) {
                                        $files = explode(',', $row['expense_files']);
                                        foreach($files as $f) {
                                            $f = trim($f);
                                            if($f) {
                                                echo '<a href="'.$upload_path . $f.'" target="_blank" class="btn-evidence" style="color:var(--primary-color); background:var(--primary-soft);" title="ดูใบเสร็จ"><i class="fas fa-file-invoice-dollar"></i></a>';
                                                $has_ev = true;
                                            }
                                        }
                                    }
                                    if(!$has_ev && !empty($row['receipt_file'])) {
                                        echo '<a href="'.$upload_path . $row['receipt_file'].'" target="_blank" class="btn-evidence" style="color:var(--primary-color); background:var(--primary-soft);" title="ดูใบเสร็จ"><i class="fas fa-file-invoice-dollar"></i></a>';
                                        $has_ev = true;
                                    }
                                    if(!$has_ev) echo '<span style="color:var(--text-muted); font-size:12px;">-</span>';
                                    ?>
                                </div>
                            </td>

                            <td style="text-align:center;"><button onclick='showPurchaseDetail(<?php echo json_encode($row); ?>)' class="btn-view"><i class="fas fa-eye"></i></button></td>
                        </tr>
                        <?php endwhile; else: echo "<tr><td colspan='9' style='text-align:center; padding:40px; color:var(--text-muted);'>ไม่พบข้อมูล</td></tr>"; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="purchaseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-shopping-cart" style="color:var(--primary-color);"></i> รายละเอียดการเบิกจ่าย</div>
                <button class="btn-close" onclick="closePurchaseModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="purchaseModalBody"></div>
        </div>
    </div>

    <script>
    function showPurchaseDetail(data) {
        let html = `
            <div class="d-group">
                <div class="d-item"><div class="d-lbl">วันที่ / ผู้เบิก</div><div class="d-val">${data.report_date} <span style="font-weight:400; color:var(--text-muted);">โดย</span> ${data.reporter_name}</div></div>
                <div class="d-item"><div class="d-lbl">โครงการ</div><div class="d-val">${data.project_name}</div></div>
            </div>
            <div class="d-lbl" style="margin-top:20px;">รายการสินค้า/ร้านค้า</div>
        `;
        
        if(data.item_details) {
            let items = data.item_details.split('\n');
            let shopCount = 0;
            items.forEach(line => {
                // Check Header (ร้านค้า)
                if(line.includes('เลขที่:')) {
                    if(shopCount > 0) html += `</div></div>`; // Close prev box
                    html += `<div class="shop-box"><div class="shop-head"><i class="fas fa-store"></i> ${line.replace('เลขที่:','<span style="background:var(--bg-card); padding:2px 8px; border-radius:4px; font-size:12px; margin-left:5px; color:var(--primary-color); border:1px solid var(--border-color);">#').replace('|','</span> |')}</div><div class="shop-body">`;
                    shopCount++;
                } else if(line.includes('---')) {
                    // Separator
                } else if(line.trim() !== '') {
                    html += `<div style="padding:4px 0; border-bottom:1px dashed var(--border-color); color:var(--text-main);">${line}</div>`;
                }
            });
            if(shopCount > 0) html += `</div></div>`; // Close last box
        }

        html += `
            <div class="total-box">
                <div style="font-weight:700; color:#ef4444; font-size:15px;">ยอดรวมสุทธิ (Grand Total)</div>
                <div style="font-size:24px; font-weight:800; color:#ef4444;">${parseFloat(data.total_expense).toLocaleString()} ฿</div>
            </div>
            
            <div class="note-box">
                <div class="d-lbl"><i class="fas fa-comment-alt"></i> หมายเหตุ</div>
                <div>${data.additional_notes || '- ไม่มีบันทึก -'}</div>
            </div>
        `;

        document.getElementById('purchaseModalBody').innerHTML = html;
        
        // 🔥 แก้ไขบรรทัดนี้ครับ จาก 'block' เป็น 'flex'
        document.getElementById('purchaseModal').style.display = 'flex'; 
    }
    
    function closePurchaseModal() { document.getElementById('purchaseModal').style.display = 'none'; }
    window.onclick = function(e) { if(e.target == document.getElementById('purchaseModal')) closePurchaseModal(); }
</script>
</body>
</html>