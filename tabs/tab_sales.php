<?php
// --- CONFIG ---
if(!isset($conn)) require_once 'db_connect.php'; 

$table_name = 'reports';
$upload_path = 'uploads/'; 
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// ✅ กรองเฉพาะของ "ฉัน"
$my_name = $_SESSION['fullname'];
$where_sql = "WHERE reporter_name = '$my_name' AND report_date BETWEEN '$start_date' AND '$end_date'";

// Filter Status
$filter_status = $_GET['filter_status'] ?? '';
if (!empty($filter_status)) {
    $where_sql .= " AND job_status = '$filter_status'";
}

// Search
$search_keyword = $_GET['keyword'] ?? '';
if (!empty($search_keyword)) {
    $where_sql .= " AND (work_result LIKE '%$search_keyword%' OR project_name LIKE '%$search_keyword%')";
}

// --- KPI & DATA PREPARATION ---
$status_counts = [];
$total_expense = 0;
$total_reports = 0;
$rows_buffer = []; 

$sql_list = "SELECT * FROM $table_name $where_sql ORDER BY report_date DESC, id DESC";
$result_list = $conn->query($sql_list);

if($result_list) {
    while($row = $result_list->fetch_assoc()) {
        // 🔧 1. ดึงค่าใช้จ่ายย่อย
        $f = (float)($row['fuel'] ?? $row['fuel_cost'] ?? $row['fuel_expense'] ?? 0);
        $h = (float)($row['accommodation'] ?? $row['hotel'] ?? $row['hotel_cost'] ?? $row['accommodation_cost'] ?? 0);
        $o = (float)($row['other'] ?? $row['other_cost'] ?? $row['public_transport'] ?? $row['other_expense'] ?? 0);
        
        // 🔧 2. คำนวณยอดรวมใหม่
        $calculated_total = $f + $h + $o;
        
        if ($row['total_expense'] == 0 || $calculated_total > 0) {
            $row['total_expense'] = $calculated_total;
        }

        // 🔧 3. บันทึกค่าที่ 'ถูกต้อง' กลับไปใน array
        $row['std_fuel'] = $f; 
        $row['std_hotel'] = $h; 
        $row['std_other'] = $o;

        // KPI Check
        $st = trim($row['job_status']) ?: 'ไม่ระบุ';
        $status_counts[$st] = ($status_counts[$st] ?? 0) + 1;
        $total_expense += $row['total_expense'];
        $total_reports++;

        $rows_buffer[] = $row;
    }
}

// --- OPTIONS ---
$statuses = $conn->query("SELECT DISTINCT job_status FROM $table_name WHERE job_status != '' ORDER BY job_status ASC");

// ✅ Helper functions
function getCardConfig($status) {
    $s = trim($status);
    if (strpos($s, 'ได้งาน') !== false || strpos($s, 'สำเร็จ') !== false) return ['color' => '#10b981', 'icon' => 'fa-check-circle'];
    if (strpos($s, 'เข้าเสนอโครงการ') !== false || strpos($s, 'เสนอ') !== false) return ['color' => '#3b82f6', 'icon' => 'fa-briefcase'];
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
        /* Light Mode Defaults */
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --bg-hover: #f1f5f9;
        --bg-input: #ffffff;
        
        --text-main: #1e293b;
        --text-muted: #64748b;
        
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        
        --primary-color: #4f46e5;
        --primary-soft: #e0e7ff;

        /* Evidence Colors (Light) */
        --ev-fuel-bg: #fff7ed; --ev-fuel-text: #c2410c; --ev-fuel-border: #ffedd5;
        --ev-hotel-bg: #eff6ff; --ev-hotel-text: #1d4ed8; --ev-hotel-border: #dbeafe;
        --ev-other-bg: #fefce8; --ev-other-text: #a16207; --ev-other-border: #fef9c3;
        
        --radius-lg: 16px;
    }

    /* 🌙 Dark Mode Override */
    body.dark-mode {
        --bg-body: #0f172a;
        --bg-card: #1e293b;
        --bg-hover: #334155;
        --bg-input: #334155;
        
        --text-main: #f8fafc;
        --text-muted: #cbd5e1;
        
        --border-color: #334155;
        --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.5);
        
        --primary-color: #818cf8;
        --primary-soft: rgba(99, 102, 241, 0.2);

        /* Evidence Colors (Dark) */
        --ev-fuel-bg: rgba(234, 88, 12, 0.2); --ev-fuel-text: #fb923c; --ev-fuel-border: rgba(234, 88, 12, 0.3);
        --ev-hotel-bg: rgba(37, 99, 235, 0.2); --ev-hotel-text: #60a5fa; --ev-hotel-border: rgba(37, 99, 235, 0.3);
        --ev-other-bg: rgba(202, 138, 4, 0.2); --ev-other-text: #facc15; --ev-other-border: rgba(202, 138, 4, 0.3);
    }

    /* --- Base Styles --- */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
    .kpi-card { 
        background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); 
        box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);
        position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center;
        height: 100px; transition: 0.3s; cursor: pointer;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--primary-color); }
    .kpi-label { font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; opacity: 0.8; color: var(--text-muted); }
    .kpi-value { font-size: 26px; font-weight: 800; color: var(--text-main); line-height: 1; z-index: 1; }
    .kpi-icon { position: absolute; right: -10px; bottom: -15px; font-size: 80px; opacity: 0.1; transform: rotate(-10deg); }

    .filter-wrapper { background: var(--bg-card); padding: 15px 25px; border-radius: 50px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
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

    .table-container { background: var(--bg-card); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); }
    .table-responsive { overflow-x: auto; width: 100%; }
    table { width: 100%; border-collapse: collapse; min-width: 1000px; }
    
    th { background: var(--bg-hover); color: var(--text-muted); font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 15px 25px; text-align: left; border-bottom: 2px solid var(--border-color); white-space: nowrap; }
    td { padding: 12px 25px; border-bottom: 1px solid var(--border-color); color: var(--text-main); font-size: 14px; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--bg-hover); }

    .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; border: 1px solid transparent; white-space: nowrap; }
    .gps-tag-office { background: var(--bg-hover); color: var(--text-muted); border: 1px solid var(--border-color); padding: 2px 8px; border-radius: 4px; font-size: 11px; }
    .gps-tag-out { background: var(--primary-soft); color: var(--primary-color); border: 1px solid var(--primary-soft); padding: 2px 8px; border-radius: 4px; font-size: 11px; }
    
    .btn-view { border: 1px solid var(--border-color); background: var(--bg-input); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .btn-view:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }
    
    .btn-evidence { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent; transition: 0.2s; cursor: pointer; text-decoration: none; }
    .btn-evidence:hover { transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    
    .ev-fuel { background: var(--ev-fuel-bg); color: var(--ev-fuel-text); border: 1px solid var(--ev-fuel-border); }
    .ev-hotel { background: var(--ev-hotel-bg); color: var(--ev-hotel-text); border: 1px solid var(--ev-hotel-border); }
    .ev-other { background: var(--ev-other-bg); color: var(--ev-other-text); border: 1px solid var(--ev-other-border); }

    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }
    .modal-content { 
        background: var(--bg-card); color: var(--text-main);
        margin: 4vh auto; border-radius: 20px; width: 95%; max-width: 650px; max-height: 90vh; overflow-y: auto; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid var(--border-color); animation: slideIn 0.3s ease; 
    }
    @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .modal-header { padding: 15px 25px; border-bottom: 1px solid var(--border-color); background: var(--bg-card); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
    .modal-title { font-size: 16px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
    .btn-close { width: 30px; height: 30px; border-radius: 50%; border: none; background: var(--bg-hover); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); }
    .btn-close:hover { background: #ef4444; color: white; }
    .modal-body { padding: 25px; background: var(--bg-body); }

    /* Modal Content Styles */
    .d-group { margin-bottom: 20px; }
    .d-lbl { font-size: 12px; color: var(--text-muted); font-weight: 700; margin-bottom: 5px; text-transform: uppercase; }
    .d-val { font-size: 15px; font-weight: 600; color: var(--text-main); }
    .gps-box { background: var(--primary-soft); padding: 15px; border-radius: 12px; border: 1px solid rgba(79, 70, 229, 0.2); }
    .expense-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 10px; }
    .ex-box { background: var(--bg-card); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); text-align: center; }
    .note-box { background: var(--bg-card); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); margin-top: 15px; font-size: 14px; line-height: 1.6; color: var(--text-main); }
</style>

<div class="kpi-grid">
    <div class="kpi-card" onclick="filterByStatus('')" style="border-left: 5px solid var(--primary-color);">
        <div class="kpi-label">รายงานทั้งหมด</div>
        <div class="kpi-value"><?php echo number_format($total_reports); ?></div>
        <i class="fas fa-file-alt kpi-icon" style="color: var(--primary-color);"></i>
    </div>

    <?php foreach($status_counts as $st => $cnt): $cfg = getCardConfig($st); ?>
    <div class="kpi-card" onclick="filterByStatus('<?php echo $st; ?>')" style="border-left: 5px solid <?php echo $cfg['color']; ?>;">
        <div class="kpi-label" style="color: <?php echo $cfg['color']; ?>;"><?php echo $st; ?></div>
        <div class="kpi-value" style="color: <?php echo $cfg['color']; ?>;"><?php echo number_format($cnt); ?></div>
        <i class="fas <?php echo $cfg['icon']; ?> kpi-icon" style="color: <?php echo $cfg['color']; ?>;"></i>
    </div>
    <?php endforeach; ?>

    <div class="kpi-card" style="border-left: 5px solid #ef4444; cursor: default;">
        <div class="kpi-label" style="color: #ef4444;">ค่าใช้จ่ายรวม</div>
        <div class="kpi-value" style="color: #ef4444;"><?php echo number_format($total_expense); ?> ฿</div>
        <i class="fas fa-wallet kpi-icon" style="color: #ef4444;"></i>
    </div>
</div>

<form class="filter-wrapper" method="GET">
    <input type="hidden" name="tab" value="sales">
    <div class="filter-header"><i class="fas fa-list-ul" style="color:var(--primary-color);"></i> รายการขายของฉัน</div>
    <div class="filter-form">
        <input type="text" name="keyword" value="<?php echo $search_keyword; ?>" class="form-input" placeholder="ค้นหา: ลูกค้า, โครงการ..." style="min-width: 200px;">
        
        <select name="filter_status" class="form-input" style="min-width: 150px;">
            <option value="">-- สถานะทั้งหมด --</option>
            <?php while($s=$statuses->fetch_assoc()){ echo "<option value='{$s['job_status']}' ".($filter_status==$s['job_status']?'selected':'').">{$s['job_status']}</option>"; } ?>
        </select>

        <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-input">
        <span style="color:var(--text-muted);">-</span>
        <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-input">
        
        <button class="btn-search"><i class="fas fa-search"></i></button>
    </div>
</form>

<div class="table-container">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>วันที่/เวลา</th><th>ลูกค้า/กิจกรรม</th><th>โครงการ</th><th>สถานะ</th><th>ค่าใช้จ่าย</th><th style="text-align:center;">หลักฐาน</th><th style="text-align:center;">ดู</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($rows_buffer)): foreach($rows_buffer as $row): 
                    // Prepare JSON for JS with pre-calculated values
                    $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700; color:var(--text-main);"><?php echo date('d/m/Y', strtotime($row['report_date'])); ?></div>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                    </td>
                    <td>
                        <div style="font-weight:600; color:var(--text-main);">
                            <?php echo $row['work_result']; ?>
                        </div>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
                            <?php echo $row['activity_type']; ?>
                            <?php if(isset($row['gps']) && $row['gps'] == 'Office'): ?>
                                <span class="gps-tag-office" style="margin-left:5px;"><i class="fas fa-building"></i> ออฟฟิศ</span>
                            <?php else: ?>
                                <span class="gps-tag-out" style="margin-left:5px;"><i class="fas fa-map-marker-alt"></i> นอกสถานที่</span>
                            <?php endif; ?>
                        </div>
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
                    <td style="font-weight:700; color:#ef4444;"><?php echo number_format($row['total_expense']); ?></td>
                    <td style="text-align:center;">
                        <div style="display:flex; justify-content:center; gap:5px;">
                            <?php 
                            $has_ev = false;
                            if(!empty($row['fuel_receipt'])) { echo '<a href="'.$upload_path.$row['fuel_receipt'].'" target="_blank" class="btn-evidence ev-fuel" title="บิลน้ำมัน"><i class="fas fa-gas-pump"></i></a>'; $has_ev = true; }
                            if(!empty($row['accommodation_receipt'])) { echo '<a href="'.$upload_path.$row['accommodation_receipt'].'" target="_blank" class="btn-evidence ev-hotel" title="บิลที่พัก"><i class="fas fa-bed"></i></a>'; $has_ev = true; }
                            if(!empty($row['other_receipt'])) { echo '<a href="'.$upload_path.$row['other_receipt'].'" target="_blank" class="btn-evidence ev-other" title="บิลอื่นๆ"><i class="fas fa-receipt"></i></a>'; $has_ev = true; }
                            if(!$has_ev) echo '<span style="color:var(--text-muted); font-size:12px;">-</span>';
                            ?>
                        </div>
                    </td>
                    <td style="text-align:center;"><button onclick='showDetail(<?php echo $row_json; ?>)' class="btn-view"><i class="fas fa-eye"></i></button></td>
                </tr>
                <?php endforeach; else: echo "<tr><td colspan='7' style='text-align:center; padding:40px; color:var(--text-muted);'>ไม่พบข้อมูล</td></tr>"; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">รายละเอียดข้อมูล</div>
            <button class="btn-close" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
    function filterByStatus(status) {
        const statusSelect = document.querySelector('select[name="filter_status"]');
        if(statusSelect) { statusSelect.value = status; }
        const filterForm = document.querySelector('.filter-wrapper');
        if(filterForm) { filterForm.submit(); }
    }

    function showDetail(data) {
        // Use standard keys calculated in PHP (std_fuel, std_hotel, std_other)
        let fuel = parseFloat(data.std_fuel || 0);
        let hotel = parseFloat(data.std_hotel || 0);
        let other = parseFloat(data.std_other || 0);
        
        let html = `
            <div style="display:flex; gap:20px; margin-bottom:15px;">
                <div style="flex:1;"><div class="d-lbl">ผู้รายงาน</div><div class="d-val">${data.reporter_name}</div></div>
                <div style="flex:1;"><div class="d-lbl">วันที่รายงาน</div><div class="d-val">${data.report_date}</div></div>
            </div>
            <div class="d-group"><div class="d-lbl">ลูกค้า / งาน</div><div class="d-val">${data.work_result}</div></div>
            <div class="d-group"><div class="d-lbl">โครงการ</div><div class="d-val">${data.project_name}</div></div>
        `;
        
        if(data.gps && data.gps !== 'Office') {
            html += `<div class="gps-box"><div class="d-lbl" style="color:var(--primary-color);"><i class="fas fa-map-marker-alt"></i> GPS Check-in</div>
                     <div style="font-size:14px; margin-top:5px; word-break:break-all;">${data.gps_address || data.gps}</div>
                     <div style="font-size:13px; margin-top:5px; color:var(--text-muted);"><b>โซน:</b> ${data.area || '-'} | <b>จ.:</b> ${data.province || '-'}</div></div>`;
        }

        html += `<div class="d-group"><div class="d-lbl">ค่าใช้จ่าย</div>
                 <div class="expense-grid">
                    <div class="ex-box"><div style="font-size:11px; color:var(--text-muted);">น้ำมัน</div><div style="color:var(--ev-fuel-text); font-weight:700;">${fuel.toLocaleString()}</div></div>
                    <div class="ex-box"><div style="font-size:11px; color:var(--text-muted);">ที่พัก</div><div style="color:var(--ev-hotel-text); font-weight:700;">${hotel.toLocaleString()}</div></div>
                    <div class="ex-box"><div style="font-size:11px; color:var(--text-muted);">อื่นๆ</div><div style="color:var(--ev-other-text); font-weight:700;">${other.toLocaleString()}</div></div>
                 </div></div>`;
        
        html += `<div class="d-group"><div class="d-lbl">บันทึกเพิ่มเติม</div><div class="note-box">${data.problem || data.additional_notes || '-'}</div></div>`;

        // Assumes detailModal and modalBody exist elsewhere or are part of a master layout
        if(document.getElementById('modalBody')) {
            document.getElementById('modalBody').innerHTML = html;
        }
        if(document.getElementById('detailModal')) {
            document.getElementById('detailModal').style.display = 'block';
        }
    }

    function closeModal(id) { 
        let el = document.getElementById(id);
        if(el) el.style.display = 'none'; 
    }
</script>