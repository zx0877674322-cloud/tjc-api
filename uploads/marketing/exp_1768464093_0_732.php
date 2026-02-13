<?php
// --- 1. CONFIG & FILTER ---
$table_name = 'report_admin';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// กรองเฉพาะของ "ฉัน"
$my_name = $_SESSION['fullname'];
$where_sql = "WHERE reporter_name = '$my_name' AND report_date BETWEEN '$start_date' AND '$end_date'";

// Search Keyword
$search_keyword = $_GET['keyword'] ?? '';
if (!empty($search_keyword)) {
    $where_sql .= " AND (note LIKE '%$search_keyword%' OR exp_company LIKE '%$search_keyword%' OR exp_proj LIKE '%$search_keyword%')";
}

// --- 2. KPI CALCULATION (Updated for JSON) ---
$kpi = ['accom'=>0, 'labor'=>0, 'pr'=>0, 'job'=>0, 'bg'=>0, 'stamp'=>0];

// Helper Function for JSON Sum (PHP Side)
function sumJsonStr($str) {
    if(empty($str)) return 0;
    $arr = json_decode($str, true);
    if(!is_array($arr)) $arr = explode(',', $str); // Fallback for old data
    return array_sum(array_map(function($v){ return floatval(trim($v)); }, $arr));
}

// Fetch Data for KPI
$sql_kpi_data = "SELECT * FROM $table_name $where_sql";
$res_kpi = $conn->query($sql_kpi_data);

if ($res_kpi) {
    while ($row = $res_kpi->fetch_assoc()) {
        if ($row['has_expense']) {
            $kpi['accom'] += sumJsonStr($row['exp_accom']);
            $raw_labor = sumJsonStr($row['exp_labor']);
            $kpi['labor'] += ($raw_labor * 0.97); // Net Labor
        }
        if ($row['has_pr'])    $kpi['pr']    += sumJsonStr($row['pr_budget']);
        if ($row['has_job'])   $kpi['job']   += sumJsonStr($row['job_budget']);
        if ($row['has_bg'])    $kpi['bg']    += sumJsonStr($row['bg_amount']);
        if ($row['has_stamp']) $kpi['stamp'] += sumJsonStr($row['stamp_cost']);
    }
}

// --- 3. LIST ---
$sql_list = "SELECT * FROM $table_name $where_sql ORDER BY report_date DESC, created_at DESC";
$result = $conn->query($sql_list);
?>

<style>
    /* --- 🎨 THEME CONFIGURATION (Force Light Mode) --- */
    :root {
        --primary: #4f46e5;
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --bg-input: #f1f5f9;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
        
        /* Status Colors */
        --c-exp-bg: #fef2f2; --c-exp-txt: #ef4444;
        --c-pr-bg: #eff6ff;  --c-pr-txt: #3b82f6;
        --c-job-bg: #f5f3ff; --c-job-txt: #8b5cf6;
        --c-bg-bg: #fffbeb;  --c-bg-txt: #f59e0b;
        --c-stamp-bg: #ecfdf5; --c-stamp-txt: #10b981;
        
        --c-accom: #ec4899; 
        --c-labor: #ef4444; 
    }

    /* KPI Cards */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .kpi-card { 
        background: var(--bg-card); padding: 25px; border-radius: 20px; 
        border: 1px solid var(--border-color); position: relative; overflow: hidden; 
        box-shadow: var(--shadow-sm); transition: 0.3s;
    }
    .kpi-title { font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; }
    .kpi-value { font-size: 26px; font-weight: 800; color: var(--text-main); }
    .kpi-icon { position: absolute; right: -10px; bottom: -15px; font-size: 80px; opacity: 0.1; transform: rotate(-15deg); }
    .kpi-bar { position: absolute; top: 0; left: 0; width: 5px; height: 100%; }

    /* Filter */
    .filter-wrapper { 
        background: var(--bg-card); padding: 15px 30px; border-radius: 50px; 
        box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); 
        margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; gap: 20px;
        flex-wrap: wrap;
    }
    .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
    .form-input { 
        padding: 10px 20px; border-radius: 25px; border: 1px solid var(--border-color); 
        background: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; font-size: 14px; outline: none; 
    }
    
    .btn-search { 
        background: var(--primary); color: white; border: none; width: 40px; height: 40px; 
        border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; 
    }
    .btn-search:hover { transform: scale(1.1); }

    /* Table */
    .table-container { 
        background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border-color); 
        overflow: hidden; box-shadow: var(--shadow-sm); 
    }
    .table-responsive { overflow-x: auto; width: 100%; }
    table { width: 100%; border-collapse: collapse; min-width: 900px; }
    
    th { 
        background: var(--bg-input); color: var(--text-muted); font-weight: 700; font-size: 13px; 
        text-transform: uppercase; padding: 20px 30px; text-align: left; border-bottom: 2px solid var(--border-color); 
    }
    td { 
        padding: 20px 30px; border-bottom: 1px solid var(--border-color); 
        color: var(--text-main); font-size: 15px; vertical-align: middle; 
    }
    tr:hover td { background: rgba(79, 70, 229, 0.03); }

    /* Badges */
    .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 700; margin-right: 5px; }
    .b-exp { background: var(--c-exp-bg); color: var(--c-exp-txt); }
    .b-pr { background: var(--c-pr-bg); color: var(--c-pr-txt); }
    .b-job { background: var(--c-job-bg); color: var(--c-job-txt); }
    .b-bg { background: var(--c-bg-bg); color: var(--c-bg-txt); }
    .b-stamp { background: var(--c-stamp-bg); color: var(--c-stamp-txt); }
    .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

    /* --- Modal & Details Styles --- */
    .modal { 
        display: none; position: fixed; z-index: 2000; 
        left: 0; top: 0; width: 100%; height: 100%; 
        background: rgba(0, 0, 0, 0.6); 
        backdrop-filter: blur(4px);
        align-items: center; justify-content: center; 
    }
    .modal-content { 
        background: var(--bg-card); /* ใช้สีการ์ดตาม Theme */
        width: 90%; max-width: 600px; 
        border-radius: 24px; 
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
        overflow: hidden; 
        animation: slideIn 0.2s ease; 
        border: 1px solid var(--border-color); 
        color: var(--text-main);
    }
    @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .modal-header { 
        padding: 20px 30px; 
        border-bottom: 1px solid var(--border-color); 
        display: flex; justify-content: space-between; align-items: center; 
        background: var(--bg-card);
    }
    .modal-title { font-size: 18px; font-weight: 800; color: var(--text-main); }
    .btn-close { border: none; background: none; font-size: 24px; cursor: pointer; color: var(--text-muted); }
    .btn-close:hover { color: var(--primary); }
    
    .modal-body { 
        padding: 30px; 
        background: var(--bg-body); /* ใช้สีพื้นหลังตาม Theme */
        max-height: 80vh; overflow-y: auto; 
    }

    /* Inner Detail Card Style (Similar to Admin) */
    .inner-card { 
        background: var(--bg-card); padding: 20px; border-radius: 16px; 
        border: 1px solid var(--border-color); margin-bottom: 15px; 
        position: relative; overflow: hidden; 
        box-shadow: var(--shadow-sm);
    }
    .inner-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: var(--text-main); }
    .inner-label { color: var(--text-muted); font-weight: 600; }
    .inner-val { font-weight: 700; text-align: right; }
    
    .detail-header-text { font-weight: 800; color: var(--text-main); margin-bottom: 15px; display:flex; align-items:center; gap:8px; }
    
    .grand-total { 
        background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); 
        padding: 20px; border-radius: 16px; text-align: center; margin-top: 20px; 
    }
    .total-label { font-size: 13px; font-weight: 600; color: #059669; text-transform: uppercase; }
    .total-val { font-size: 32px; font-weight: 800; color: var(--text-main); }

    .note-box {
        background: var(--bg-card); border: 1px solid var(--border-color); 
        padding: 15px; border-radius: 12px; margin-top: 15px; 
        font-size: 14px; color: var(--text-main); line-height: 1.6;
    }

    .btn-link-file {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 12px; color: var(--primary); text-decoration: none;
        border: 1px solid var(--primary); padding: 4px 10px; border-radius: 6px;
        transition: 0.2s; background: transparent;
    }
    .btn-link-file:hover { background: var(--primary); color: white; }
    
    /* Custom View Button */
    .btn-view-custom {
        border: 1px solid var(--border-color); background: var(--bg-card); 
        padding: 8px 12px; border-radius: 10px; cursor: pointer; 
        color: var(--text-muted); transition: 0.2s;
    }
    .btn-view-custom:hover { background: var(--primary); color: white; border-color: var(--primary); }
</style>

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
        <div class="kpi-title">งบ ปร.</div>
        <div class="kpi-value" style="color:var(--c-pr-txt);"><?php echo number_format($kpi['pr']); ?></div>
        <i class="fas fa-file-invoice kpi-icon" style="color:var(--c-pr-txt);"></i>
    </div>
    <div class="kpi-card">
        <div class="kpi-bar" style="background:var(--c-job-txt);"></div>
        <div class="kpi-title">งบโครงการ แจ้งอัปงาน</div>
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
        <div class="kpi-title">ค่าอากรแสตมป์</div>
        <div class="kpi-value" style="color:var(--c-stamp-txt);"><?php echo number_format($kpi['stamp']); ?></div>
        <i class="fas fa-stamp kpi-icon" style="color:var(--c-stamp-txt);"></i>
    </div>
</div>

<form class="filter-wrapper" method="GET">
    <input type="hidden" name="tab" value="admin">
    <div style="font-weight:700; color:var(--text-main); display:flex; align-items:center; gap:10px;">
        <i class="fas fa-list-ul" style="color:var(--primary)"></i> รายการของฉัน
    </div>
    <div class="filter-form">
        <input type="text" name="keyword" value="<?php echo $search_keyword; ?>" class="form-input" placeholder="ค้นหารายละเอียด..." style="min-width: 200px;">
        
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
                    <th>วันที่ / เวลา</th>
                    <th>ประเภทรายการ</th>
                    <th>รายละเอียดเบื้องต้น</th>
                    <th style="text-align:right;">ยอดรวม (บาท)</th>
                    <th style="text-align:center;">ดู</th>
                </tr>
            </thead>
            <tbody>
                <?php if($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:700;"><?php echo date('d/m/Y', strtotime($row['report_date'])); ?></div>
                            <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                        </td>
                        <td>
                            <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                <?php 
                                    if($row['has_expense']) echo '<span class="badge b-exp"><div class="dot"></div> ธุรการ</span>';
                                    if($row['has_pr']) echo '<span class="badge b-pr"><div class="dot"></div> PR</span>';
                                    if($row['has_job']) echo '<span class="badge b-job"><div class="dot"></div> อัปงาน</span>';
                                    if($row['has_bg']) echo '<span class="badge b-bg"><div class="dot"></div> BG</span>';
                                    if($row['has_stamp']) echo '<span class="badge b-stamp"><div class="dot"></div> อากร</span>';
                                ?>
                            </div>
                        </td>
                        <td>
                            <div style="max-width:300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color:var(--text-muted);">
                                <?php echo $row['note'] ? $row['note'] : '-'; ?>
                            </div>
                        </td>
                        <td style="text-align:right; font-weight:700; font-size:15px; color:#059669;"><?php echo number_format($row['total_amount']); ?></td>
                        <td style="text-align:center;">
                            <button onclick='showAdminDetail(<?php echo $row_json; ?>)' class="btn-view-custom"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--text-muted);">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="adminModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">รายละเอียดข้อมูล</div>
            <button class="btn-close" onclick="closeAdminModal()">&times;</button>
        </div>
        <div class="modal-body" id="adminModalBody"></div>
    </div>
</div>

<script>
    // ✅ Safe JSON Parse (เพิ่มใหม่)
    function safeParse(str) {
        try {
            let parsed = JSON.parse(str);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return str ? String(str).split(',') : [];
        }
    }

    function showAdminDetail(d) {
        let html = `
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; font-size:14px; color:var(--text-main);">
                <span><b>วันที่:</b> ${d.report_date}</span>
                <span><b>เวลา:</b> ${d.created_at}</span>
            </div>
        `;

        if (d.has_expense == 1) {
            // ใช้ safeParse ดึงข้อมูล Array
            let companies = safeParse(d.exp_company || '[]');
            let depts = safeParse(d.exp_dept || '[]');
            let projs = safeParse(d.exp_proj || '[]');
            let accoms = safeParse(d.exp_accom || '[]'); 
            let labors = safeParse(d.exp_labor || '[]'); 
            let files = safeParse(d.exp_file || '[]');
            
            // เอกสาร (Docs) อาจจะเป็น string รวม หรือ json ก็ได้
            let docs = [];
            try { docs = JSON.parse(d.exp_doc || '[]'); if(!Array.isArray(docs)) throw new Error(); } 
            catch(e) { docs = d.exp_doc ? String(d.exp_doc).split(',') : []; }

            let docHtml = '';
            docs.forEach(doc => {
                if(String(doc).trim() !== '') {
                    docHtml += `<span style="background:#e2e8f0; border:1px solid #cbd5e1; padding:4px 10px; border-radius:6px; font-size:13px; color:#0f172a; font-weight:700;">${doc}</span> `;
                }
            });

            html += `
            <div class="inner-card" style="border-left: 4px solid var(--c-exp-txt);">
                <div class="detail-header-text" style="color:var(--c-exp-txt);"><i class="fas fa-file-invoice"></i> ค่าใช้จ่ายธุรการ</div>
                
                <div style="background:#f8fafc; padding:10px; border-radius:8px; margin-bottom:15px; border:1px dashed #cbd5e1;">
                    <div style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:5px;">เอกสารที่เกี่ยวข้อง</div>
                    <div style="display:flex; flex-wrap:wrap; gap:5px;">${docHtml || '-'}</div>
                </div>
            `;

            let count = Math.max(companies.length, depts.length, accoms.length);
            for (let i = 0; i < count; i++) {
                if (!companies[i] && !depts[i]) continue;

                let ac = parseFloat(accoms[i]) || 0;
                let lb = parseFloat(labors[i]) || 0;
                let lb_net = lb * 0.97;
                let f = files[i] || '';

                html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:15px; margin-bottom:10px; position:relative;">
                    
                    <div class="inner-row"><span class="inner-label">บริษัท</span><span class="inner-val">${companies[i]||'-'}</span></div>
                    <div class="inner-row"><span class="inner-label">หน่วยงาน</span><span class="inner-val">${depts[i]||'-'}</span></div>
                    <div class="inner-row"><span class="inner-label">โครงการ</span><span class="inner-val">${projs[i]||'-'}</span></div>
                    <hr style="border:0; border-top:1px dashed #e2e8f0; margin:8px 0;">
                    
                    <div class="inner-row"><span class="inner-label">🏨 ค่าที่พัก</span><span class="inner-val" style="color:var(--c-accom);">${new Intl.NumberFormat().format(ac)} ฿</span></div>
                    <div class="inner-row">
                        <span class="inner-label">👷 ค่าแรง</span>
                        <div style="text-align:right;">
                            <div class="inner-val" style="color:var(--c-labor);">${new Intl.NumberFormat().format(lb)} ฿</div>
                            <div style="font-size:11px; color:#059669; font-weight:600;">(สุทธิ: ${new Intl.NumberFormat().format(lb_net)})</div>
                        </div>
                    </div>
                    ${f ? `<div style="text-align:right; margin-top:5px;"><a href="uploads/admin/${f}" target="_blank" class="btn-link-file"><i class="fas fa-paperclip"></i> ไฟล์แนบ</a></div>` : ''}
                </div>`;
            }
            html += `</div>`;
        }

        // Helper Function for other sections
        function renderSection(title, color, icon, dataObj) {
            let html = `
            <div class="inner-card" style="border-left: 4px solid ${color};">
                <div class="detail-header-text" style="color:${color};"><i class="${icon}"></i> ${title}</div>`;
            
            let keys = Object.keys(dataObj);
            let firstArr = safeParse(dataObj[keys[0]].val || '[]');
            let count = firstArr.length;

            for (let i = 0; i < count; i++) {
                if (!firstArr[i]) continue;
                html += `<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:15px; margin-bottom:10px; position:relative;">`;
                
                keys.forEach(k => {
                    let vals = safeParse(dataObj[k].val || '[]');
                    let val = vals[i] || '-';
                    if (dataObj[k].isMoney) val = new Intl.NumberFormat().format(parseFloat(val)||0) + ' ฿';
                    
                    html += `<div class="inner-row"><span class="inner-label">${dataObj[k].label}</span><span class="inner-val" style="${dataObj[k].highlight?'color:'+color+';font-weight:800;':''}">${val}</span></div>`;
                });
                html += `</div>`;
            }
            html += `</div>`;
            return html;
        }

        if (d.has_pr == 1) {
            html += renderSection('PR Request', 'var(--c-pr-txt)', 'fas fa-file-invoice', {
                dept: { label: 'หน่วยงาน', val: d.pr_dept },
                proj: { label: 'โครงการ', val: d.pr_proj },
                budg: { label: 'งบประมาณ', val: d.pr_budget, isMoney:true, highlight:true }
            });
        }

        if (d.has_job == 1) {
            html += renderSection('แจ้งอัปงาน', 'var(--c-job-txt)', 'fas fa-briefcase', {
                num:  { label: 'เลขหน้างาน', val: d.job_num },
                dept: { label: 'หน่วยงาน', val: d.job_dept },
                proj: { label: 'โครงการ', val: d.job_proj },
                budg: { label: 'งบโครงการ', val: d.job_budget, isMoney:true, highlight:true }
            });
        }

        if (d.has_bg == 1) {
            html += renderSection('หนังสือค้ำประกัน', 'var(--c-bg-txt)', 'fas fa-university', {
                dept: { label: 'หน่วยงาน', val: d.bg_dept },
                proj: { label: 'โครงการ', val: d.bg_proj },
                amt:  { label: 'ยอดค้ำ', val: d.bg_amount, isMoney:true, highlight:true }
            });
        }

        if (d.has_stamp == 1) {
            html += renderSection('อากรแสตมป์', 'var(--c-stamp-txt)', 'fas fa-stamp', {
                dept: { label: 'หน่วยงาน', val: d.stamp_dept },
                proj: { label: 'โครงการ', val: d.stamp_proj },
                cost: { label: 'ค่าอากร', val: d.stamp_cost, isMoney:true, highlight:true }
            });
        }

        html += `
            <div class="grand-total">
                <div class="total-label">ยอดรวมทั้งสิ้น (สุทธิ)</div>
                <div class="total-val">${new Intl.NumberFormat().format(d.total_amount)} ฿</div>
            </div>
        `;

        if (d.note) {
            html += `<div class="note-box"><b><i class="fas fa-sticky-note"></i> Note:</b><br>${d.note}</div>`;
        }

        document.getElementById('adminModalBody').innerHTML = html;
        document.getElementById('adminModal').style.display = 'flex';
    }

    function closeAdminModal() {
        document.getElementById('adminModal').style.display = 'none';
    }
    
    window.onclick = function(e) {
        if (e.target == document.getElementById('adminModal')) closeAdminModal();
    }
</script>