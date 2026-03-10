<?php
// --- 1. CONFIG & FILTER ---
$table_name = 'report_admin';

// ✅ แก้ไข: ปรับค่าเริ่มต้นเป็นค่าว่าง (เพื่อให้แสดงทั้งหมดตอนแรก)
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date']   ?? '';

// กรองเฉพาะของ "ฉัน" (User Session)
$my_name = $_SESSION['fullname'];
$where_sql = "WHERE reporter_name = '$my_name'";

// ✅ เพิ่มเติม: Logic วันที่ ถ้ามีค่าค่อยกรอง (ถ้าว่างคือไม่กรอง = ดูทั้งหมด)
if (!empty($start_date) && !empty($end_date)) {
    $where_sql .= " AND report_date BETWEEN '$start_date' AND '$end_date'";
} elseif (!empty($start_date)) {
    $where_sql .= " AND report_date >= '$start_date'";
} elseif (!empty($end_date)) {
    $where_sql .= " AND report_date <= '$end_date'";
}

// --- 2. KPI CALCULATION ---
$kpi = ['accom'=>0, 'labor'=>0, 'pr'=>0, 'job'=>0, 'bg'=>0, 'stamp'=>0, 'other'=>0, 'docs'=>0];

function sumDocsFromString($str) {
    if(empty($str)) return 0;
    $arr = json_decode($str, true);
    if(!is_array($arr)) $arr = explode(',', $str);
    
    $total = 0;
    foreach($arr as $item) {
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
            $kpi['labor'] += ($raw_labor * 0.97);
            $kpi['other'] += sumJsonStr($row['exp_other_amount']);
            $kpi['docs'] += sumDocsFromString($row['exp_doc']);
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">

<script>
    // --- Prevent FOUC (Dark Mode) ---
    (function() {
        if (localStorage.getItem('tjc_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
            document.body?.classList.add('dark-mode'); // Fallback for ajax load
        }
    })();
</script>

<style>
    /* --- 🎨 THEME CONFIGURATION (Matches Dashboard_Admin) --- */
    :root {
        /* Light Mode Defaults */
        --bg-body: #f1f5f9;
        --bg-card: #ffffff;
        --bg-input: #ffffff;
        --bg-inner: #f8fafc;
        
        --text-main: #1e293b;
        --text-sub: #64748b;
        --text-label: #475569;
        
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        
        --primary-color: #2563eb;

        /* Status Colors (Light) */
        --c-exp-bg: #fef2f2; --c-exp-txt: #dc2626;
        --c-pr-bg: #eff6ff;  --c-pr-txt: #2563eb;
        --c-job-bg: #f5f3ff; --c-job-txt: #7c3aed;
        --c-bg-bg: #fffbeb;  --c-bg-txt: #d97706;
        --c-stamp-bg: #ecfdf5; --c-stamp-txt: #059669;
        --c-accom: #db2777; --c-labor: #dc2626;
        --c-other: #f97316;

        /* Highlight Backgrounds (Light) */
        --bg-hl-accom: #fff1f2;
        --bg-hl-labor: #fef2f2;
        --bg-hl-other: #fffbeb;
    }

    /* 🌑 Dark Mode Override */
    body.dark-mode {
        --bg-body: #0f172a;
        --bg-card: #1e293b;
        --bg-input: #334155;
        --bg-inner: #0f172a;
        
        --text-main: #f8fafc;
        --text-sub: #cbd5e1;
        --text-label: #94a3b8;
        
        --border-color: #334155;
        --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.5);
        
        --primary-color: #60a5fa;

        /* Status Colors (Dark - Translucent) */
        --c-exp-bg: rgba(220, 38, 38, 0.15); --c-exp-txt: #f87171;
        --c-pr-bg: rgba(37, 99, 235, 0.15);  --c-pr-txt: #60a5fa;
        --c-job-bg: rgba(124, 58, 237, 0.15); --c-job-txt: #a78bfa;
        --c-bg-bg: rgba(217, 119, 6, 0.15);  --c-bg-txt: #fbbf24;
        --c-stamp-bg: rgba(5, 150, 105, 0.15); --c-stamp-txt: #34d399;
        --c-accom: #f472b6; --c-labor: #f87171;
        --c-other: #fb923c;

        /* Highlight Backgrounds (Dark) */
        --bg-hl-accom: rgba(219, 39, 119, 0.15);
        --bg-hl-labor: rgba(220, 38, 38, 0.15);
        --bg-hl-other: rgba(217, 119, 6, 0.15);
    }

    /* KPI Cards */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .kpi-card { 
        background: var(--bg-card); padding: 24px; border-radius: 16px; 
        border: 1px solid var(--border-color); position: relative; overflow: hidden; 
        box-shadow: var(--shadow-sm); transition: 0.3s;
    }
    .kpi-card:hover { transform: translateY(-3px); border-color: var(--primary-color); box-shadow: var(--shadow-md); }
    .kpi-title { font-size: 14px; font-weight: 700; color: var(--text-sub); text-transform: uppercase; margin-bottom: 8px; opacity: 0.9; }
    .kpi-value { font-size: 26px; font-weight: 800; color: var(--text-main); }
    .kpi-bar { position: absolute; top: 0; left: 0; width: 5px; height: 100%; }
    .kpi-icon { position: absolute; right: -5px; bottom: -10px; font-size: 70px; opacity: 0.08; transform: rotate(-15deg); }

    /* Filter */
    /* --- 🎨 Filter Bar Redesign --- */
/* --- จัด Layout ให้เรียงแนวนอนสวยงาม --- */
.filter-wrapper {
    background: #ffffff;
    padding: 15px 20px;
    border-radius: 16px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    margin-bottom: 30px;
    display: flex;
    align-items: center; /* จัดให้อยู่กึ่งกลางแนวตั้ง */
    justify-content: space-between; /* ให้หัวข้ออยู่ซ้าย ฟอร์มอยู่ขวา (หรือชิดกันถ้าพื้นที่เหลือ) */
    flex-wrap: wrap; /* รองรับมือถือให้ตกบรรทัดได้ */
    gap: 15px;
}

/* หัวข้อ "รายการของฉัน" */
.filter-title {
    font-weight: 700;
    color: #1e293b;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap; /* ไม่ให้ข้อความตัดบรรทัด */
}

/* ส่วนฟอร์ม (วันที่ + ปุ่ม) */
.filter-form {
    display: flex;
    align-items: center;
    gap: 10px; /* ระยะห่างระหว่างช่อง */
    flex-wrap: wrap;
}

/* --- แต่งช่องวันที่ (Input) ให้ดูดี --- */
.date-input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.form-input.flatpickr {
    width: 140px; /* ✅ กำหนดความกว้างให้พอดี ไม่ยาวเกินไป */
    padding: 8px 12px 8px 35px; /* เว้นซ้ายไว้ใส่ไอคอน */
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-family: 'Prompt', sans-serif;
    font-size: 14px;
    color: #334155;
    background-color: #fff;
    outline: none;
    transition: all 0.2s ease;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'%3E%3C/rect%3E%3Cline x1='16' y1='2' x2='16' y2='6'%3E%3C/line%3E%3Cline x1='8' y1='2' x2='8' y2='6'%3E%3C/line%3E%3Cline x1='3' y1='10' x2='21' y2='10'%3E%3C/line%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 10px center; /* ตำแหน่งไอคอนปฏิทิน */
}

.form-input.flatpickr:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* ข้อความ "ถึง" */
.date-separator {
    color: #64748b;
    font-weight: 600;
    font-size: 14px;
}

/* ปุ่มค้นหา (สีน้ำเงิน) */
.btn-search {
    background: #2563eb;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
    box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
}
.btn-search:hover { background: #1d4ed8; transform: translateY(-1px); }

/* ปุ่มดูทั้งหมด (สีขาว) */
.btn-reset {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #475569;
    height: 40px;
    padding: 0 16px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
    transition: 0.2s;
}
.btn-reset:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }

/* Mobile: ให้เรียงแนวตั้งเมื่อจอเล็ก */
@media (max-width: 768px) {
    .filter-wrapper { justify-content: center; }
    .filter-form { width: 100%; justify-content: center; }
    .form-input.flatpickr { width: 100%; } /* มือถือให้เต็มจอ */
}

.filter-header {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* กลุ่ม Input วันที่ */
.date-group {
    display: flex;
    align-items: center;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 5px 10px;
    transition: 0.2s;
}

.date-group:focus-within {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.date-input {
    border: none;
    background: transparent;
    color: var(--text-main);
    font-family: 'Prompt', sans-serif;
    font-size: 14px;
    padding: 8px;
    outline: none;
    cursor: pointer;
}

.date-separator {
    color: var(--text-muted);
    font-size: 12px;
    margin: 0 5px;
}

/* ช่องค้นหา */
.search-input {
    padding: 10px 15px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background: var(--bg-input);
    color: var(--text-main);
    font-family: 'Prompt';
    font-size: 14px;
    outline: none;
    min-width: 200px;
    transition: 0.2s;
}
.search-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

/* ปุ่มค้นหา (สีหลัก) */
.btn-search {
    background: linear-gradient(135deg, var(--primary-color) 0%, #4338ca 100%);
    color: white;
    border: none;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
    transition: all 0.2s ease;
}

/* ปุ่ม Reset (สีรอง) */
.btn-reset {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    height: 42px;
    padding: 0 18px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s;
}
.btn-reset:hover {
    background: var(--bg-hover);
    color: var(--text-main);
    border-color: var(--text-muted);
}
.btn-reset:active {
    background: var(--border-color);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .filter-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .date-group {
        justify-content: space-between;
    }
    .btn-search {
        width: 100%;
    }
    .btn-reset {
        justify-content: center;
    }
}
    .filter-form { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .form-input { 
        padding: 10px 16px; border-radius: 12px; border: 1px solid var(--border-color); 
        background: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; font-size: 14px; outline: none; 
        transition: 0.2s;
    }
    .form-input:focus { border-color: var(--primary-color); }
    
    .btn-search { 
        background: var(--primary-color); color: white; border: none; width: 42px; height: 42px; 
        border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; 
    }
    .btn-search:hover { transform: scale(1.05); }

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

    /* Badges */
    .badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; margin-right: 6px; letter-spacing: 0.3px; }
    .b-exp { background: var(--c-exp-bg); color: var(--c-exp-txt); }
    .b-pr { background: var(--c-pr-bg); color: var(--c-pr-txt); }
    .b-job { background: var(--c-job-bg); color: var(--c-job-txt); }
    .b-bg { background: var(--c-bg-bg); color: var(--c-bg-txt); }
    .b-stamp { background: var(--c-stamp-bg); color: var(--c-stamp-txt); }
    .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

    /* Modal */
    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(3px); align-items: center; justify-content: center; }
    .modal-content { background: var(--bg-card); width: 95%; max-width: 650px; border-radius: 24px; box-shadow: var(--shadow-md); overflow: hidden; animation: zoomIn 0.25s ease; border: 1px solid var(--border-color); color: var(--text-main); }
    @keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .modal-header { padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); }
    .modal-title { font-size: 20px; font-weight: 800; color: var(--text-main); }
    .btn-close { border: none; background: none; font-size: 28px; cursor: pointer; color: var(--text-sub); transition: 0.2s; }
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
    .inner-label { color: var(--text-sub); font-size: 13px; font-weight: 700; }
    .inner-val { font-weight: 700; color: var(--text-main); text-align: right; }
    
    .grand-total { background: var(--bg-card); border: 2px solid var(--primary-color); padding: 20px; border-radius: 16px; text-align: center; margin-top: 25px; box-shadow: var(--shadow-sm); }
    .total-label { font-size: 14px; font-weight: 700; color: var(--text-sub); text-transform: uppercase; letter-spacing: 1px; }
    .total-val { font-size: 34px; font-weight: 900; color: var(--primary-color); margin-top: 5px; }
    
    .btn-link-file { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--primary-color); text-decoration: none; border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 8px; background: var(--bg-input); font-weight: 600; transition: 0.2s; }
    .btn-link-file:hover { border-color: var(--primary-color); background: var(--bg-body); }
    
    .btn-view-custom { border: 1px solid var(--border-color); background: var(--bg-input); padding: 8px 12px; border-radius: 10px; cursor: pointer; color: var(--text-sub); transition: 0.2s; }
    .btn-view-custom:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }

    /* Highlight Inner Value */
    .inner-val.inner-highlight {
        background: var(--bg-input);   
        color: var(--primary-color);
        padding: 4px 10px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        font-weight: 700;
    }
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
            <div class="kpi-card">
                <div class="kpi-bar" style="background:#2563eb;"></div> <div class="kpi-title">ยอดรวมเอกสาร (AX/PO)</div>
                <div class="kpi-value" style="color:#2563eb;">
                    <?php echo number_format($kpi['docs']); ?>
                </div>
                <i class="fas fa-file-contract kpi-icon" style="color:#2563eb;"></i>
            </div>
        </div>


<form class="filter-wrapper" method="GET" id="adminFilterForm">
    <input type="hidden" name="tab" value="admin">
    
    <div class="filter-title">
        <i class="fas fa-list-ul" style="color:var(--primary-color)"></i> รายการของฉัน
    </div>

    <div class="filter-form">        
        
        <input type="text" name="start_date" value="<?php echo $start_date; ?>" 
               class="form-input flatpickr" placeholder="dd/mm/yyyy">
        
        <span class="date-separator">ถึง</span>
        
        <input type="text" name="end_date" value="<?php echo $end_date; ?>" 
               class="form-input flatpickr" placeholder="dd/mm/yyyy">
        
        <button type="submit" class="btn-search" title="ค้นหา">
            <i class="fas fa-search"></i>
        </button>
        
        <button type="button" class="btn-reset" onclick="resetAdminDates()" title="ล้างวันที่">
            <i class="fas fa-calendar-check"></i> ดูทั้งหมด
        </button>
    </div>
</form>

<div class="table-container">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>วันที่ / เวลา</th>
                    <th>ประเภทรายการ</th>
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
                            <div style="font-weight:700; color:var(--text-main);"><?php echo date('d/m/Y', strtotime($row['report_date'])); ?></div>
                            <div style="font-size:12px; color:var(--text-sub); margin-top:2px; font-weight:600;"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                        </td>
                        <td>
                            <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                <?php 
                                    if($row['has_expense']) echo '<span class="badge b-exp"><div class="dot"></div> Expenses</span>';
                                    if($row['has_pr']) echo '<span class="badge b-pr"><div class="dot"></div> BOQ</span>';
                                    if($row['has_job']) echo '<span class="badge b-job"><div class="dot"></div> อัปงาน</span>';
                                    if($row['has_bg']) echo '<span class="badge b-bg"><div class="dot"></div> LG</span>';
                                    if($row['has_stamp']) echo '<span class="badge b-stamp"><div class="dot"></div> ตีตราสาร</span>';
                                ?>
                            </div>
                        </td>
                        <td style="text-align:right; font-weight:800; font-size:16px; color:var(--text-main);"><?php echo number_format($row['total_amount']); ?></td>
                        <td style="text-align:center;">
                            <div style="display:flex; gap:8px; justify-content:center; align-items:center;">
                                <button onclick='showAdminDetail(<?php echo $row_json; ?>)' class="btn-view-custom" title="เปิดดูรายละเอียด" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <a href="report_admin.php?edit_id=<?= $row['id'] ?>" class="btn-edit-main" title="แก้ไขข้อมูลรายงาน" style="border: 1px solid var(--border-color); background: var(--bg-input); width: 36px; height: 36px; border-radius: 10px; cursor: pointer; color: var(--primary-color); text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='var(--primary-color)'; this.style.color='white';" onmouseout="this.style.background='var(--bg-input)'; this.style.color='var(--primary-color)';">
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <button onclick='confirmDeleteAdminReport(<?= $row["id"] ?>)' class="btn-action-delete" title="ลบรายงานนี้" style="color: #ef4444; border: 1px solid #fee2e2; background: #fef2f2; border-radius: 10px; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--text-sub); font-weight:600;">ไม่มีข้อมูลในช่วงเวลานี้</td></tr>
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

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
    // ✅ Safe JSON Parse
    function safeParse(str) {
        try {
            let parsed = JSON.parse(str);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return str ? String(str).split(',') : [];
        }
    }

    // ✅ 5. ฟังก์ชันหลัก: แสดงรายละเอียด (ฉบับเต็ม + รวมยอดเอกสาร)
    function showAdminDetail(d) {
        // --- 1. จัดการวันที่ (แปลง YYYY-MM-DD เป็น DD/MM/YYYY) ---
        let dateParts = d.report_date.split('-');
        let formattedDate = d.report_date;
        if (dateParts.length === 3) formattedDate = `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;

        let html = `
            <div style="margin-bottom:20px; display:flex; justify-content:space-between; font-size:14px; color:var(--text-main);">
                <span><b>วันที่:</b> ${formattedDate}</span>
                <span><b>เวลา:</b> ${d.created_at}</span>
            </div>
        `;

        // ==========================================
        // ส่วนที่ 1: ค่าใช้จ่าย (Expenses)
        // ==========================================
        if (d.has_expense == 1) {
            let docs        = safeParse(d.exp_doc || '[]'); 
            let companies   = safeParse(d.exp_company || '[]');
            let depts       = safeParse(d.exp_dept || '[]');
            let projs       = safeParse(d.exp_proj || '[]');
            let accoms      = safeParse(d.exp_accom || '[]'); 
            let labors      = safeParse(d.exp_labor || '[]'); 
            let files       = safeParse(d.exp_file || '[]');
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
            
            // --- ตัวแปรสะสมยอดรวมทั้งหมด ---
            let totalAccom = 0;
            let totalLabor = 0;
            let totalOther = 0;
            let totalDocs  = 0; // ✅ ตัวแปรเก็บยอดรวมเอกสาร

            for (let i = 0; i < count; i++) {
                if (!companies[i] && !depts[i]) continue;

                // แปลงค่าเงินเป็นตัวเลข
                let ac = parseFloat(accoms[i]) || 0;
                let lb = parseFloat(labors[i]) || 0;
                let lb_net = lb * 0.97; // หัก 3%
                let oth_a = parseFloat(others_amt[i]) || 0;
                let oth_d = others_desc[i] || '';
                let oth_f = others_file[i] || '';

                // สะสมยอดเข้าตัวแปรหลัก
                totalAccom += ac; 
                totalLabor += lb; 
                totalOther += oth_a;
                
                let cardTotal = ac + lb_net + oth_a; 
                let f = files[i] || '';

                // ---------------------------------------------------------
                // 🔥 เริ่มส่วนจัดการเอกสาร (AX/PO/SO) ในแต่ละแถว
                // ---------------------------------------------------------
                let docRaw = docs[i] || '-';
                let docHtml = '';
                // แยกรายการด้วย comma
                let subDocs = docRaw.split(',').map(s => s.trim()).filter(s => s);

                if (subDocs.length > 0 && subDocs[0] !== '-') {
                    docHtml = '<div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end; width:100%;">';
                    
                    subDocs.forEach(subDoc => {
                        // Regex แกะแพทเทิร์น: "AX 123 ( รายการ : 500 )"
                        let match = subDoc.match(/^(.*?)\s*\(\s*(.*?)\s*[:]\s*(.*?)\s*\)/);
                        
                        if (match) {
                            let header = match[1].trim(); 
                            let item   = match[2].trim(); 
                            let priceStr = match[3].trim().replace(/บ\.|บาท/g, '').replace(/,/g, '').trim();
                            let price  = parseFloat(priceStr) || 0;

                            // ✅ บวกยอดเงินเอกสารเข้าตัวแปรหลัก (totalDocs)
                            totalDocs += price; 

                            // สร้าง HTML การ์ดเล็กๆ แสดงรายละเอียด (แต่ไม่โชว์ยอดรวมที่นี่)
                            docHtml += `
                            <div style="background:#fff; border:1px solid #e2e8f0; border-left:4px solid #ef4444; border-radius:6px; padding:6px 10px; font-size:12px; min-width:200px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                                <div style="font-weight:700; color:#334155; border-bottom:1px dashed #cbd5e1; padding-bottom:4px; margin-bottom:4px;">
                                    <i class="far fa-file-alt"></i> ${header}
                                </div>
                                <div style="display:flex; justify-content:space-between; color:#64748b;">
                                    <span>${item}</span>
                                    <span style="font-weight:700; color:#ef4444;">${price.toLocaleString()} ฿</span>
                                </div>
                            </div>`;
                        } else {
                            // กรณีข้อความธรรมดา
                            docHtml += `<span style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:4px; padding:4px 8px; font-size:12px;">${subDoc}</span>`;
                        }
                    });
                    docHtml += '</div>';
                } else {
                    docHtml = '-';
                }
                // ---------------------------------------------------------

                // แสดงผล Card ย่อยแต่ละรายการ
                html += `
                <div class="inner-card">
                    <div class="inner-row" style="align-items: flex-start;"> 
                        <span class="inner-label" style="padding-top:5px;">เลขที่เอกสาร</span>
                        <div class="inner-val" style="width:70%;">${docHtml}</div>
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
                    </div>` : '' }

                    <div style="margin-top:10px; padding-top:8px; border-top:2px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:12px; font-weight:700; color:var(--text-sub);">รวมรายการนี้</span>
                        <span style="font-size:16px; font-weight:800; color:var(--primary-color);">${new Intl.NumberFormat().format(cardTotal)} ฿</span>
                    </div>
                </div>`;
            }
            html += `</div>`; 

            // คำนวณยอดสรุปสุดท้าย
            let totalWht = totalLabor * 0.03;
            let totalNet = totalAccom + (totalLabor * 0.97) + totalOther;

            // ✅ แสดงกล่องสรุป (Summary Box) พร้อมยอดรวมเอกสาร
            html += `
                <div class="box-summary" style="background:var(--bg-card); padding:15px; border-radius:12px; border:1px solid var(--border-color); margin-top:15px;">
                    <div class="inner-row"><span class="inner-label">รวมค่าที่พัก</span><span class="inner-val">${new Intl.NumberFormat().format(totalAccom)} ฿</span></div>
                    <div class="inner-row"><span class="inner-label">รวมค่าแรง</span><span class="inner-val">${new Intl.NumberFormat().format(totalLabor)} ฿</span></div>
                    
                    <div class="inner-row" style="background:#f1f5f9; padding:4px 8px; border-radius:6px; margin:6px 0;">
                        <span class="inner-label" style="font-weight:700; color:#1e293b;">รวมค่าเอกสาร (AX/PO)</span>
                        <span class="inner-val" style="color:#2563eb; font-weight:700;">${new Intl.NumberFormat().format(totalDocs)} ฿</span>
                    </div>
                    
                    <div class="inner-row"><span class="inner-label">รวมค่าอื่นๆ</span><span class="inner-val" style="color:#d97706;">${new Intl.NumberFormat().format(totalOther)} ฿</span></div>
                    <div class="inner-row" style="color:var(--text-sub);">
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

        // ==========================================
        // ส่วนที่ 2: อื่นๆ (PR, Job, BG, Stamp)
        // ==========================================
        if (d.has_pr == 1) {
            html += renderGroupedData('BOQ', 'var(--c-pr-txt)', 'fas fa-file-invoice', {
                dept: { label: 'หน่วยงาน', val: d.pr_dept },
                proj: { label: 'โครงการ', val: d.pr_proj },
                budg: { label: 'งบประมาณ', val: d.pr_budget, isMoney:true, highlight:true }
            });
        }
        if (d.has_job == 1) {
            html += renderGroupedData('แจ้งอัปงาน', 'var(--c-job-txt)', 'fas fa-briefcase', {
                num:  { label: 'เลขหน้างาน', val: d.job_num },
                dept: { label: 'หน่วยงาน', val: d.job_dept },
                proj: { label: 'โครงการ', val: d.job_proj },
                budg: { label: 'งบโครงการ', val: d.job_budget, isMoney:true, highlight:true } 
            });
        }
        if (d.has_bg == 1) {
            html += renderGroupedData('หนังสือค้ำประกัน', 'var(--c-bg-txt)', 'fas fa-university', {
                dept: { label: 'หน่วยงาน', val: d.bg_dept },
                proj: { label: 'โครงการ', val: d.bg_proj },
                amt:  { label: 'ยอดค้ำ', val: d.bg_amount, isMoney:true, highlight:true }
            });
        }
        if (d.has_stamp == 1) {
            html += renderGroupedData('ตีตราสาร', 'var(--c-stamp-txt)', 'fas fa-stamp', {
                dept: { label: 'หน่วยงาน', val: d.stamp_dept },
                proj: { label: 'โครงการ', val: d.stamp_proj },
                cost: { label: 'ค่าอากร', val: d.stamp_cost, isMoney:true, highlight:true }
            });
        }

        // ==========================================
        // ส่วนที่ 3: Grand Total & Note
        // ==========================================
        html += `<div class="grand-total"><div class="total-label">ยอดรวมทั้งสิ้น (สุทธิ)</div><div class="total-val">${new Intl.NumberFormat().format(d.total_amount)} ฿</div></div>`;
        
        if (d.note) { 
            html += `<div style="background:var(--bg-body); border:2px solid var(--border-color); padding:20px; border-radius:12px; margin-top:15px; font-size:15px; color:var(--text-main); font-weight:600; white-space: pre-wrap;"><b>Note:</b> ${d.note}</div>`; 
        }

        // Render HTML ลงใน Modal
        document.getElementById('adminModalBody').innerHTML = html;
        document.getElementById('adminModal').style.display = 'flex';
    }

    function closeAdminModal() {
        document.getElementById('adminModal').style.display = 'none';
    }
    
    window.onclick = function(e) {
        if (e.target == document.getElementById('adminModal')) closeAdminModal();
    }
    
    // ✅ เรียกใช้ Flatpickr พร้อม Locale ภาษาไทย
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr(".flatpickr", {
            dateFormat: "Y-m-d", // ส่งค่า Y-m-d ให้ PHP
            altInput: true,      // เปิดโหมดแสดงผลแยก
            altFormat: "d/m/Y",  // แสดงผลเป็น dd/mm/yyyy
            locale: "th",        // ภาษาไทย
            allowInput: true     // ยอมให้พิมพ์เองได้
        });
    });

    // ✅ อัปเดตฟังก์ชัน Reset ให้ล้างค่า Flatpickr
    function resetAdminDates() {
        const form = document.getElementById('adminFilterForm');
        // สั่งเคลียร์ค่าใน flatpickr
        const inputs = document.querySelectorAll('.flatpickr');
        inputs.forEach(input => {
            if (input._flatpickr) {
                input._flatpickr.clear();
            }
        });
        form.submit();
    }

    // ✅ ฟังก์ชันลบรายงาน (Admin)
    function confirmDeleteAdminReport(id) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "คุณต้องการลบข้อมูลรายงานธุรการนี้ใช่หรือไม่? (การลบจะไม่สามารถกู้คืนได้)",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: '<i class="fas fa-trash"></i> ลบข้อมูล',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    executeDeleteAdminReport(id);
                }
            });
        } else if (confirm('คุณต้องการลบข้อมูลรายงานธุรการนี้ใช่หรือไม่? (การลบจะไม่สามารถกู้คืนได้)')) {
            executeDeleteAdminReport(id);
        }
    }

    function executeDeleteAdminReport(id) {
        fetch('StaffHistory.php?action=delete_admin_report&id=' + id, { method: 'GET' })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success', 
                            title: 'ลบสำเร็จ!', 
                            text: data.message, 
                            showConfirmButton: false, 
                            timer: 1500
                        }).then(() => location.reload());
                    } else {
                        alert(data.message);
                        location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('ข้อผิดพลาด', data.message, 'error');
                    } else {
                        alert('ข้อผิดพลาด: ' + data.message);
                    }
                }
            })
            .catch(err => {
                console.error('Error deleting:', err);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            });
    }
</script>