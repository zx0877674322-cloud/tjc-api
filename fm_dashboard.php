<?php
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

// ตรวจสอบว่ามี Session ของผู้ใช้หรือไม่ (เปลี่ยน 'user_id' เป็นชื่อตัวแปรที่คุณใช้ในหน้า login)
if (!isset($_SESSION['user_id'])) {
    // ถ้าไม่มีให้ Redirect ไปหน้า Login
    header("Location: login.php");
    exit(); // หยุดการทำงานของสคริปต์ที่เหลือทันที
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include 'Logowab.php'; ?>
<title>แดชบอร์ดขนส่ง</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ===== ROOT VARIABLES ===== */
:root { --primary: #3b82f6; --primary-dark: #1e40af; --success: #16a34a; --danger: #dc2626; --warning: #f59e0b; --info: #0ea5e9; --bg-body: #f1f5f9; --bg-card: #ffffff; --text-primary: #3b82f6; --text-secondary: #64748b; --border: #e2e8f0; --radius: 8px; --radius-lg: 12px; }
:root.dark-mode { --bg-body: #0f172a; --bg-card: #1e293b; --text-primary: #f1f5f9; --text-secondary: #cbd5e1; --border: #334155; }

/* ===== GLOBAL STYLES ===== */
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 100%; height: 100%; overflow: hidden; }
body { font-family: 'Prompt', sans-serif; background-color: var(--bg-body); color: var(--text-primary); transition: background-color 0.3s ease, color 0.3s ease; overflow: hidden; }
html {
    height: -webkit-fill-available;
}

body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100vh;
    background-color: var(--bg-body);
    color: var(--text-primary);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.dashboard-content {
    padding: 15px;
    padding-top: 70px;
    padding-bottom: 80px;
    min-height: 100vh;
    height: auto; 
    overflow-y: visible;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.8);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 10px;
    backdrop-filter: blur(5px);
}

.modal-content {
    background: var(--bg-card);
    width: 100%;
    max-width: 500px;
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    max-height: 90vh; 
    overflow: hidden; 
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 768px) {
    .dashboard-header { padding: 15px; }
    .header-title h1 { font-size: 22px; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
}

.table-card { 
    background: var(--bg-card); 
    border-radius: var(--radius-lg); 
    border: 1px solid var(--border); 
    overflow: hidden; 
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.table-responsive { 
    overflow-x: auto; 
    max-height: none !important;
    -webkit-overflow-scrolling: touch;
}

table.modern-table { 
    width: 100%; 
    border-collapse: collapse;
}

table.modern-table th { 
    position: sticky; 
    top: 0; 
    background: var(--bg-body); 
    z-index: 10; 
    padding: 12px; 
    font-size: 16px; 
}

table.modern-table td { 
    padding: 12px; 
    border-bottom: 1px solid var(--border); 
}

@media (max-width: 768px) {
    .modern-table, 
    .modern-table thead, 
    .modern-table tbody, 
    .modern-table th, 
    .modern-table td, 
    .modern-table tr { 
        display: block; 
    }

    .modern-table thead {
        display: none;
    }

    .modern-table tr {
        margin-bottom: 15px;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--bg-card);
        padding: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .modern-table td {
        border: none;
        position: relative;
        padding-left: 45% !important;
        text-align: left !important;
        white-space: normal;
        min-height: 35px;
        display: flex;
        align-items: center;
    }

    .modern-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 10px;
        width: 40%;
        font-weight: 700;
        color: var(--text-secondary);
        font-size: 16px;
        text-transform: uppercase;
    }

    .modern-table td[data-label="สถานะ"] {
        justify-content: flex-start;
        padding-top: 10px;
        border-top: 1px dashed var(--border);
    }
}

/* ===== LAYOUT ===== */
.dashboard-content { padding: 20px; height: 100vh; overflow-y: auto; overflow-x: hidden; transition: all 0.3s ease; }
.dashboard-header { margin-bottom: 20px; background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); }
.header-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
.header-title h1 { font-size: 28px; margin: 0 0 5px 0; background: linear-gradient(135deg, #3b82f6, #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.subtitle { color: var(--text-secondary); font-size: 16px; margin: 0; font-weight: 500; }
.header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* ===== CONTROLS ===== */
.toggle-group { display: flex; gap: 2px; background: var(--bg-body); padding: 4px; border-radius: var(--radius); border: 1px solid var(--border); }
.toggle-btn { padding: 6px 12px; border: none; background: transparent; color: var(--text-secondary); cursor: pointer; border-radius: 6px; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 5px; transition: all 0.2s; white-space: nowrap; }
.toggle-btn:hover { color: var(--primary); }
.toggle-btn.active { background: var(--primary); color: white; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3); }

.mode-toggle { display: flex; gap: 10px; flex-wrap: wrap; }
.mode-btn { padding: 8px 16px; background: var(--bg-body); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-secondary); font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 16px; }
.mode-btn.active { border-color: var(--primary); color: white; background: var(--primary); }

.date-input { padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius); font-family: inherit; background: var(--bg-body); color: var(--text-primary); font-size: 16px; cursor: pointer; }
.btn { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-size: 16px; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
.btn-primary { background: var(--primary); color: white; }
.btn-outline { border: 1px solid var(--border); background: transparent; color: var(--text-primary); }

/* ===== STATS ===== */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: var(--bg-card); padding: 15px; border-radius: var(--radius-lg); display: flex; align-items: center; gap: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02); }
/* ค้นหาส่วนนี้ในโค้ดของคุณ */
.stat-icon {
    width: 45px;             /* ขนาดกรอบขาวคงเดิม */
    height: 45px;            /* ขนาดกรอบขาวคงเดิม */
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    
    /* --- ส่วนที่ต้องปรับปรุง --- */
    font-size: 28px;         /* เพิ่มขนาด Icon (เดิมอาจเป็น 18px) */
    padding: 0;              /* มั่นใจว่าไม่มี padding มาดันไอคอนให้เล็กลง */
    flex-shrink: 0;
}.stat-icon.blue { background: #eff6ff; color: var(--primary); }
.stat-icon.green { background: #f0fdf4; color: var(--success); }
.stat-icon.red { background: #fef2f2; color: var(--danger); }
.stat-icon.orange { background: #fff7ed; color: var(--warning); }
.stat-icon.indigo { background: #eef2ff; color: #6366f1; }
.stat-content h3 { margin: 0 0 3px 0; font-size: 16px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; }
.stat-value { font-size: 28px; font-weight: 700; margin: 0; color: var(--text-primary); }

/* ===== TABLE ===== */
.table-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
.table-header { padding: 12px 15px; background: var(--bg-body); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.table-responsive { overflow-x: auto; max-height: 400px; }
table.modern-table { width: 100%; border-collapse: collapse; white-space: nowrap; }
table.modern-table th { position: sticky; top: 0; background: var(--bg-body); z-index: 10; padding: 10px 12px; text-align: left; font-size: 14px; text-transform: uppercase; color: var(--text-secondary); border-bottom: 2px solid var(--border); font-weight: 700; }
table.modern-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 16px; vertical-align: middle; }
table.modern-table tr:hover { background: rgba(59, 130, 246, 0.05); cursor: pointer; }

/* ===== DRIVER CARD & DRAG DROP ===== */
.driver-row-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: 10px; overflow: hidden; transition: 0.3s; }
.driver-header { padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: var(--bg-card); }
.driver-header:hover { background: rgba(59, 130, 246, 0.03); }
.driver-info { display: flex; align-items: center; gap: 10px; }
.driver-avatar { width: 40px; height: 40px; background: #eff6ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700; }
.driver-stats { display: flex; gap: 8px; font-size: 16px; align-items: center; }
.driver-content { display: none; padding: 0; border-top: 1px solid var(--border); background: var(--bg-body); }
.driver-content.show { display: block; animation: slideDown 0.3s; }

/* REORDER PANEL */
#reorderPanel { display: none; background: var(--bg-card); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 2px dashed var(--primary); animation: slideDown 0.3s; }
.reorder-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.draggable-list { list-style-type: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.draggable-item { background: var(--bg-body); color: var(--text-primary); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border); cursor: grab; display: flex; align-items: center; justify-content: space-between; transition: 0.2s; user-select: none; }
.draggable-item:hover { border-color: var(--primary); background: var(--bg-card); transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
.draggable-item.dragging { opacity: 0.5; border: 2px dashed var(--primary); background: #eff6ff; }
.drag-handle { color: var(--text-secondary); margin-right: 10px; cursor: grab; }

/* UTILS */
.cell-date-time { display: flex; flex-direction: column; gap: 2px; }
.cell-date { font-weight: 600; color: var(--text-primary); font-size: 16px; }
.cell-time { font-size: 13.8px; color: var(--text-secondary); }
.cell-vehicle { background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; display: inline-block; }
.status-badge { padding: 3px 8px; border-radius: 12px; font-size: 13.8px; font-weight: 700; text-transform: uppercase; display: inline-block; }
.st-completed { background: #dcfce7; color: #166534; }
.st-in_progress { background: #dbeafe; color: #1e40af; }
.st-failed { background: #fee2e2; color: #991b1b; }
.st-pending { background: #f1f5f9; color: #64748b; }
.amount-inc { color: var(--success); font-weight: 700; }
.amount-exp { color: var(--danger); font-weight: 700; }
.text-right { text-align: right; }
.text-center { text-align: center; }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; align-items: center; justify-content: center; }
.modal-overlay.show { display: flex; }
.modal-content { background: var(--bg-card); width: 100%; max-width: 500px; border-radius: var(--radius-lg); padding: 0; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column; }
.modal-header { padding: 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg-body); }
.modal-body { padding: 15px; overflow-y: auto; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.detail-item { padding: 10px; background: var(--bg-body); border-radius: 6px; border: 1px solid var(--border); }
.detail-label { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; display: block; margin-bottom: 4px; font-weight: 700; }
.detail-value { font-weight: 600; color: var(--text-primary); font-size: 14px; }
.full-width { grid-column: 1 / -1; }

@keyframes slideDown { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
@media (max-width: 768px) { .header-top { flex-direction: column; gap: 12px; } .header-actions { width: 100%; justify-content: space-between; } .toggle-group { width: 100%; justify-content: center; } .toggle-btn { flex: 1; justify-content: center; } .date-input { width: 100%; margin-top: 10px; } }

.modal-header button:hover {
    transform: rotate(90deg);
    background: var(--danger) !important;
    color: white !important;
}

/* ปรับปรุงขนาด Font และระยะห่างสำหรับตัวเลขหลักล้าน */
.stat-value {
    font-size: 24px; /* ลดลงเล็กน้อยเพื่อให้รองรับหลักล้าน */
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
    white-space: nowrap; /* ป้องกันตัวเลขขึ้นบรรทัดใหม่ */
    overflow: hidden;
    text-overflow: ellipsis; /* แสดง ... หากยาวเกินไป */
}

/* ปรับ Grid ให้รองรับมือถือ (ให้เรียงต่อกันเป็นแถวเดี่ยวถ้าหน้าจอเล็ก) */
@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: 1fr !important;
        gap: 10px;
    }
}

.stat-card {
    min-width: 0; /* ช่วยให้ text-overflow ทำงานใน flex/grid */
    padding: 15px 10px; /* ลด padding ข้างเพื่อเพิ่มพื้นที่ตัวเลข */
}


</style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="dashboard-content">

        <div class="dashboard-header">
            <div class="header-top">
                <div class="header-title">
                    <h1>📊 แผงควบคุมหลัก</h1>
                    <p class="subtitle" id="subtitleText">กำลังโหลด...</p>
                </div>
                <div class="header-actions">
                    <button class="toggle-btn" style="border:1px solid #ddd; margin-right:auto;" onclick="alert('Coming Soon')">
                        <i class="fas fa-cog"></i>
                    </button>
                    <select id="filterCategory" class="date-input" onchange="handleCategoryChange()">
                        <option value="all">👥 พนักงานทั้งหมด</option>
                        <option value="employee">👨‍💼 พนักงานประจำ</option>
                        <option value="partner">🚛 รถร่วม (Partner)</option>
                    </select>
                                        
                    <div class="toggle-group">
                        <button class="toggle-btn active" id="btn-daily" onclick="setViewMode('daily')"><i class="fas fa-list"></i> วัน</button>
                        <button class="toggle-btn" id="btn-monthly" onclick="setViewMode('monthly')"><i class="fas fa-calendar-alt"></i> เดือน</button>
                        <button class="toggle-btn" id="btn-yearly" onclick="setViewMode('yearly')"><i class="fas fa-calendar"></i> ปี</button>
                    </div>

                    <input type="date" id="datePicker" class="date-input" onchange="handleDateChange()">
                    <input type="month" id="monthPicker" class="date-input" style="display:none" onchange="handleDateChange()">
                    <select id="yearPicker" class="date-input" style="display:none" onchange="handleDateChange()"></select>
                </div>
            </div>

            <div class="mode-toggle">
                <button class="mode-btn active" id="mode-stats" onclick="setDisplayMode('stats')">📊 สรุปสถิติ</button>
                <button class="mode-btn" id="mode-drivers" onclick="setDisplayMode('drivers')">👥 ติดตามงานคนขับ</button>

        </div>

        <div id="loading" style="text-align:center; padding:50px; display:none;">
            <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--primary)"></i>
            <p>กำลังโหลดข้อมูล...</p>
        </div>

        <div id="main-content">
            
          <div id="stats-section">
    <div class="stats-grid">
        <div class="stat-card" style="border-left: 5px solid var(--primary);">
            <div class="stat-icon blue"><i class="fas fa-truck"></i></div>
            <div class="stat-content">
                <h3>รถทั้งหมด</h3>
                <p class="stat-value" id="val-total-veh">0</p>
            </div>
        </div>
        <div class="stat-card" style="border-left: 5px solid var(--success);">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <h3>พร้อมใช้</h3>
                <p class="stat-value" id="val-av-veh">0</p>
            </div>
        </div>
        <div class="stat-card" style="border-left: 5px solid var(--danger);">
            <div class="stat-icon red"><i class="fas fa-wrench"></i></div>
            <div class="stat-content">
                <h3>ซ่อม/ไม่ว่าง</h3>
                <p class="stat-value" id="val-main-veh">0</p>
            </div>
        </div>
    </div>

    <div class="stats-grid" style="margin-top: 20px;">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-content">
                <h3>งานทั้งหมด</h3>
                <p class="stat-value" id="val-total-jobs">0</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check"></i></div>
            <div class="stat-content">
                <h3>ส่งสำเร็จ</h3>
                <p class="stat-value" id="val-comp-jobs">0</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <h3>ไม่สำเร็จ/รอ</h3>
                <p class="stat-value" id="val-fail-jobs">0</p>
            </div>
        </div>
    </div>

    <div class="stats-grid" style="margin-top: 20px; grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card">
        <div class="stat-icon indigo" id="fin-icon"><i class="fas fa-wallet"></i></div>
        <div class="stat-content">
            <h3 id="fin-label">ค่าขนส่ง</h3>
            <p class="stat-value" id="fin-value" style="color:var(--success)">0</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#fff7ed; color:#d97706;"><i class="fas fa-tools"></i></div>
        <div class="stat-content">
            <h3>ค่าซ่อมรถ</h3>
            <p class="stat-value" id="val-rep-cost" style="color:#d97706">0</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue" style="background:#e0f2fe; color:#0284c7;"><i class="fas fa-gas-pump"></i></div>
        <div class="stat-content">
            <h3>ค่าน้ำมัน</h3>
            <p class="stat-value" id="val-fuel-cost" style="color:#0284c7">0</p>
        </div>
    </div>
</div>

<div class="stats-grid" style="margin-top: 15px; grid-template-columns: repeat(2, 1fr);">
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef2f2; color:#ef4444;"><i class="fas fa-bed"></i></div>
        <div class="stat-content">
            <h3>ค่าที่พัก</h3>
            <p class="stat-value" id="val-room-cost" style="color:#ef4444">0</p>
        </div>
    </div>

    <div class="stat-card" style="background: var(--bg-card); border: 2px solid var(--primary);">
        <div class="stat-icon indigo"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-content">
            <h3>ค่าใช้จ่ายรวม</h3>
            <p class="stat-value" id="val-total-profit" style="color:var(--primary)">0</p>
        </div>
    </div>
</div>
                

                <div class="table-card">
                    <div class="table-header">
                        <h3 style="margin:0; font-size:16px;" id="table-title">📦 รายการจัดส่ง</h3>
                        <button onclick="fetchData()" style="border:none; background:transparent; cursor:pointer; color:var(--primary)"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th width="15%">วัน-เวลา</th>
                                    <th width="10%">ทะเบียน</th>
                                    <th width="25%">ลูกค้า / ปลายทาง</th>
                                    <th width="20%">คนขับ</th>
                                    <th width="10%" class="text-right">รายจ่าย</th>
                                    <th width="10%" class="text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody id="jobs-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div> 

            <div id="drivers-section" style="display:none;">
                
                <div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; background:#fff; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:13px; font-weight:600; color:#3b82f6;"><i class="fas fa-sort"></i> เรียงโดย:</span>
                        <select id="driverSortSelect" class="date-input" style="padding:6px 10px; min-width:150px;" onchange="handleDriverSortChange()">
                            <option value="custom">👤 กำหนดเอง (Drag)</option>
                            <option value="time">🕒 เวลาเริ่มงาน (ก่อน->หลัง)</option>
                            <option value="jobs">📦 จำนวนงาน (มาก->น้อย)</option>
                            <option value="name">🅰️ ชื่อ (ก-ฮ)</option>
                        </select>
                    </div>
                    <button class="btn btn-outline" onclick="toggleReorderMode()" id="btnReorder">
                        <i class="fas fa-arrows-alt"></i> ลากจัดลำดับ
                    </button>
                </div>

                <div id="reorderPanel">
                    <div class="reorder-header">
                        <h4 style="margin:0; color:var(--text-main); font-size:14px;">
                            <i class="fas fa-hand-rock"></i> ลากเพื่อจัดลำดับคนขับ
                        </h4>
                        <button class="btn btn-primary" onclick="toggleReorderMode()">
                            <i class="fas fa-save"></i> บันทึก & ปิด
                        </button>
                    </div>
                    <ul id="reorderList" class="draggable-list"></ul>
                </div>
                
                <div id="driver-list-container"></div>
            </div>

        </div> 
    </div>

    <div class="modal-overlay" id="jobModal" onclick="closeModal(event)">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0"><i class="fas fa-info-circle"></i> รายละเอียดงาน</h3>
                <button onclick="document.getElementById('jobModal').classList.remove('show')" style="border:none; bg:transparent; cursor:pointer"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="modal-body"></div>
        </div>
    </div>

<script>
    // --- STATE ---
    let state = {
    viewMode: 'daily',
    displayMode: 'stats',
    driverSortMode: 'custom', 
    driverOrder: [],
    reorderMode: false,
    selectedDate: new Date().toISOString().split('T')[0],
    selectedMonth: new Date().toISOString().slice(0, 7),
    selectedYear: new Date().getFullYear().toString(),
    
    // 🟢 เพิ่มตัวแปรนี้
    filterCategory: 'all', 
    
    rawDrivers: [], rawVehicles: [], rawJobs: [], rawMaintenance: [],
    filteredJobs: []
};

    // --- INIT ---
    document.addEventListener('DOMContentLoaded', () => {
        // Init Inputs
        document.getElementById('datePicker').value = state.selectedDate;
        document.getElementById('monthPicker').value = state.selectedMonth;
        generateYearOptions();

        // Load Saved Order
        const saved = localStorage.getItem('fm_driver_order');
        if(saved) state.driverOrder = JSON.parse(saved);

        fetchData();
    });

    // --- API ---
   async function fetchData() {
    showLoading(true);
    try {
        let param = '';
        if (state.viewMode === 'daily') param = state.selectedDate;
        else if (state.viewMode === 'monthly') param = state.selectedMonth;
        else if (state.viewMode === 'yearly') param = state.selectedYear;

        const res = await fetch(`api_fm.php?action=fetch_dashboard&month=${param}`);
        const data = await res.json();
        
        // เก็บข้อมูลลง State (ตรวจสอบชื่อตัวแปรให้ตรงกับ api_fm.php)
        state.rawDrivers = data.drivers || [];
        state.rawVehicles = data.vehicles || [];
        state.rawJobs = data.jobs || [];
        state.rawMaintenance = data.maintenance || [];
        state.rawFuel = data.fuel || []; 
        state.rawAccommodation = data.accommodation || []; 

        syncDriverOrder();
        processData();
        render(); // สั่งให้ Render ทั้งหมดรวมถึง Stats
        
    } catch (error) { 
        console.error("Fetch Error:", error); 
    } finally { 
        showLoading(false); 
    }
}

    function syncDriverOrder() {
        const currentIds = state.rawDrivers.map(d => parseInt(d.id));
        state.driverOrder = state.driverOrder.filter(id => currentIds.includes(id));
        currentIds.forEach(id => {
            if (!state.driverOrder.includes(id)) state.driverOrder.push(id);
        });
    }

    function processData() {
    // 1. กรองตามวันที่ก่อน
    let jobs = state.rawJobs;
    if (state.viewMode === 'daily') {
        jobs = jobs.filter(j => j.start_time.startsWith(state.selectedDate));
    } else if (state.viewMode === 'monthly') {
        jobs = jobs.filter(j => j.start_time.startsWith(state.selectedMonth));
    } else if (state.viewMode === 'yearly') {
        jobs = jobs.filter(j => j.start_time.startsWith(state.selectedYear));
    }

    // 2. กรองตามประเภทพนักงาน (แก้ไขตรงนี้)
    if (state.filterCategory !== 'all') {
        jobs = jobs.filter(j => {
            const driver = state.rawDrivers.find(d => d.id == j.driver_id);
            if (!driver) return false;

            if (state.filterCategory === 'partner') {
                // ถ้าเลือก รถร่วม ให้เช็คว่าเป็น partner
                return driver.category === 'partner';
            } else if (state.filterCategory === 'employee') {
                // ถ้าเลือก พนักงานประจำ ให้เช็คว่า "ไม่ใช่ partner" 
                // (กันเหนียวในกรณีค่าใน DB เป็น employee หรือ staff หรือค่าอื่นๆ)
                return driver.category !== 'partner';
            }
            return true;
        });
    }

    state.filteredJobs = jobs;
    state.filteredJobs.sort((a,b) => new Date(b.start_time) - new Date(a.start_time));
}
    // --- RENDER ---
    function render() {
        renderHeader();
        renderStats();
        renderTable();
        renderDriversList(); // Called regardless of tab to be ready
    }

    function renderHeader() {
    // 1. จัดการข้อความ Header ตามโหมดเวลา (วัน/เดือน/ปี)
    const months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค. text"];
    let headerLabel = '';

    if (state.viewMode === 'daily') {
        const d = new Date(state.selectedDate);
        headerLabel = `ข้อมูลประจำวันที่ ${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear() + 543}`;
    } else if (state.viewMode === 'monthly') {
        const [y, m] = state.selectedMonth.split('-');
        headerLabel = `สรุปยอดประจำเดือน ${months[parseInt(m) - 1]} ${parseInt(y) + 543}`;
    } else if (state.viewMode === 'yearly') {
        headerLabel = `สรุปยอดประจำปี ${parseInt(state.selectedYear) + 543}`;
    }
    
    document.getElementById('subtitleText').innerText = headerLabel;

    // 2. จัดการสถานะ Active ของปุ่มตัวกรองเวลา (วัน/เดือน/ปี)
    ['daily', 'monthly', 'yearly'].forEach(mode => {
        const btn = document.getElementById(`btn-${mode}`);
        if (btn) btn.classList.toggle('active', state.viewMode === mode);
    });

    // 3. สลับการแสดงผล Input เลือกวันที่/เดือน/ปี
    document.getElementById('datePicker').style.display = state.viewMode === 'daily' ? 'block' : 'none';
    document.getElementById('monthPicker').style.display = state.viewMode === 'monthly' ? 'block' : 'none';
    document.getElementById('yearPicker').style.display = state.viewMode === 'yearly' ? 'block' : 'none';

    // 4. จัดการปุ่มสลับโหมด (สถิติ / ตามงานคนขับ)
    const isStatsMode = state.displayMode === 'stats';
    
    document.getElementById('mode-stats').classList.toggle('active', isStatsMode);
    document.getElementById('mode-drivers').classList.toggle('active', !isStatsMode);

    // 5. สลับ Section เนื้อหาหลัก
    document.getElementById('stats-section').style.display = isStatsMode ? 'block' : 'none';
    document.getElementById('drivers-section').style.display = isStatsMode ? 'none' : 'block';

    // 6. ซ่อนตารางรายการจัดส่ง (Table Card) เมื่ออยู่ในโหมดคนขับ
    const tableCard = document.querySelector('.table-card');
    if (tableCard) {
        tableCard.style.display = isStatsMode ? 'block' : 'none';
    }
}

function renderStats() {
    // --- 1. จัดการข้อมูลรถ (คงเดิม) ---
    const totalVeh = state.rawVehicles.length;
    const mainVeh = state.rawVehicles.filter(v => v.status === 'maintenance').length;
    const avVeh = state.rawVehicles.filter(v => v.status === 'available').length;

    document.getElementById('val-total-veh').innerText = totalVeh;
    document.getElementById('val-av-veh').innerText = avVeh;
    document.getElementById('val-main-veh').innerText = mainVeh;

    // --- 2. ดึงข้อมูลค่าซ่อมและค่าน้ำมันจากหน้าบันทึกหลัก ---
    const repairCostTotal = state.rawMaintenance.reduce((sum, m) => sum + (parseFloat(m.cost) || 0), 0);
    const fuelCostTotal = (state.rawFuel || []).reduce((sum, f) => sum + (parseFloat(f.amount) || 0), 0);
    const roomCostTotal = (state.rawAccommodation || []).reduce((sum, a) => sum + (parseFloat(a.amount) || 0), 0);

    document.getElementById('val-rep-cost').innerText = '฿' + repairCostTotal.toLocaleString();
    document.getElementById('val-fuel-cost').innerText = '฿' + fuelCostTotal.toLocaleString();
    document.getElementById('val-room-cost').innerText = '฿' + roomCostTotal.toLocaleString(); // 🟢 แสดงค่าที่พัก

    // --- 3. คำนวณรายรับและรายจ่ายจาก "ตารางขนส่ง" (Jobs Table) ---
    let totalIncome = 0;       // รายรับรวม (ราคาปกติ + ราคาเหมา)
    let totalJobExpense = 0;   // รายจ่ายรวมในตาราง (ตัวเลขสีแดง)
    const processedGroups = new Set();

    state.filteredJobs.forEach(j => {
        // A. คำนวณรายรับ (Income)
        if (!j.group_id || j.group_id == 0) {
            totalIncome += (parseFloat(j.actual_price) || 0);
        } else if (j.group_total_price && !processedGroups.has(j.group_id)) {
            const gPrice = parseFloat(j.group_total_price) || 0;
            const gType = j.group_type || 'income';
            if (gType === 'income') {
                totalIncome += gPrice;
            } else {
                // หากกลุ่มเป็นประเภทรายจ่าย ให้ไปบวกที่ฝั่งรายจ่าย
                totalJobExpense += gPrice;
            }
            // เก็บ ID ไว้ไม่ให้บวกซ้ำ
            processedGroups.add(j.group_id);
        }

        // B. คำนวณรายจ่ายรายตัว (Expense) 
        // ดึงจากคอลัมน์ cost (ตัวเลขสีแดงในหน้า fm_jobs)
        totalJobExpense += (parseFloat(j.cost) || 0);
    });

    // --- 4. แสดงผลค่าขนส่ง (รายรับสุทธิ) ---
    // สูตร: (รายรับปกติ + รายรับเหมา) - รายจ่ายในตารางงาน
    const netTransportIncome = Math.abs(totalIncome - totalJobExpense);
    
    const finVal = document.getElementById('fin-value');
    if (finVal) {
        finVal.innerText = '฿' + netTransportIncome.toLocaleString();
        // เปลี่ยนสี: กำไรเป็นเขียว ติดลบเป็นแดง
        finVal.style.color = netTransportIncome >= 0 ? 'var(--success)' : 'var(--danger)';
    }

    // --- 5. คำนวณ "ค่าใช้จ่ายรวม" (กล่องขวาสุด) ---
    // สูตร: รายจ่ายในตารางงาน + ค่าซ่อมรถ + ค่าน้ำมัน
    const grandTotalExpense = totalJobExpense + repairCostTotal + fuelCostTotal + roomCostTotal;

    const totalExpEl = document.getElementById('val-total-profit');
    if (totalExpEl) {
        totalExpEl.innerText = '฿' + grandTotalExpense.toLocaleString();
    }

    // --- 6. จัดการข้อมูลจำนวนงาน (คงเดิม) ---
    const totalJobs = state.filteredJobs.length;
    const compJobs = state.filteredJobs.filter(j => j.status === 'completed').length;
    const failJobs = state.filteredJobs.filter(j => ['failed', 'canceled', 'pending'].includes(j.status)).length;

    document.getElementById('val-total-jobs').innerText = totalJobs;
    document.getElementById('val-comp-jobs').innerText = compJobs;
    document.getElementById('val-fail-jobs').innerText = failJobs;
}
function renderTable() {
    const tbody = document.getElementById('jobs-tbody');
    tbody.innerHTML = '';

    if (state.filteredJobs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px; color:#999"><i class="fas fa-box-open fa-2x"></i><br>ไม่พบข้อมูล</td></tr>`;
        return;
    }

    state.filteredJobs.forEach(job => {
        const priceInfo = getDisplayPrice(job);
        const cost = Number(job.cost) || 0;
        const startT = job.start_time.substring(11,16);
        const dateDisplay = new Date(job.start_time).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' });

        // ตรวจสอบกลุ่มบิล
        const isGrouped = job.group_id && job.group_id != 0;
        let groupBadge = '';
        
        if (isGrouped) {
            // นับจำนวนงานที่อยู่ในกลุ่มเดียวกันจากรายการงานทั้งหมดที่โหลดมา
            const jobCount = state.rawJobs.filter(item => item.group_id == job.group_id).length;
            groupBadge = `<span style="font-size:10px; background:rgba(99, 102, 241, 0.1); color:#6366f1; padding:2px 6px; border-radius:4px; margin-left:5px; border:1px solid rgba(99, 102, 241, 0.2); display:inline-flex; align-items:center; gap:3px;">
                            <i class="fas fa-boxes"></i> ${job.group_name || 'เหมา'} (${jobCount})
                          </span>`;
        }

        const row = document.createElement('tr');
        row.onclick = () => openJobModal(job); 
        
        // ในฟังก์ชัน renderTable() ...
        row.innerHTML = `
            <td data-label="วัน-เวลา">
                <div class="cell-date-time">
                    <span class="cell-date">${dateDisplay}</span>
                    <span class="cell-time">${startT} น.</span>
                </div>
            </td>
            <td data-label="ทะเบียน">
                <span class="cell-vehicle">${job.vehicles?.fleet_number ? 'เบอร์ '+job.vehicles.fleet_number : (job.vehicles?.plate_number || '-')}</span>
            </td>
            <td data-label="ลูกค้า/ปลายทาง">
                <div style="font-weight:600; display: flex; align-items: center; flex-wrap: wrap;">
                    ${job.customer_name || '-'} ${groupBadge}
                </div>
                
                <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">
                    ${job.origin && job.origin.trim() !== "" 
                        ? `<span style="opacity:0.85">${job.origin}</span> <i class="fas fa-long-arrow-alt-right" style="color:var(--primary); margin:0 4px;"></i> ` 
                        : ''}
                    <span style="color:var(--text-primary); font-weight:600;">${job.destination || '-'}</span>
                </div>
            </td>
            <td data-label="คนขับ">
                <div>${job.driver_name || '-'}</div>
            </td>
            <td data-label="รายจ่าย" class="text-right">
                ${(priceInfo.type === 'cost' && priceInfo.value > 0) ? `<span class="amount-exp">-${priceInfo.value.toLocaleString()}</span>` : (cost > 0 ? `<span class="amount-exp">-${cost.toLocaleString()}</span>` : '-')}
            </td>
            <td data-label="สถานะ" class="text-center">
                <span class="status-badge st-${job.status}">${getStatusText(job.status)}</span>
            </td>
        `;
        tbody.appendChild(row);
    });
}
    // --- DRIVER LIST LOGIC ---
    function handleDriverSortChange() {
        state.driverSortMode = document.getElementById('driverSortSelect').value;
        renderDriversList();
    }

   function renderDriversList() {
    const container = document.getElementById('driver-list-container');
    container.innerHTML = '';
    const showDate = state.viewMode !== 'daily';
    
    // 1. จัดกลุ่มงานตามคนขับ
    const groups = {};
    state.rawDrivers.forEach(d => {
        // กรองประเภทพนักงาน (ตาม Filter ด้านบน)
        if (state.filterCategory === 'partner' && d.category !== 'partner') return;
        if (state.filterCategory === 'employee' && d.category === 'partner') return;

        groups[d.id] = { 
            id: d.id, name: d.name, category: d.category,
            jobs: [], earliestTime: '9999-99-99'
        };
    });

    // 2. ใส่ข้อมูลงานลงในกลุ่ม
    state.filteredJobs.forEach(j => {
        const key = j.driver_id;
        if(groups[key]) {
            groups[key].jobs.push(j);
            // หาเวลาเริ่มงานแรกสุดของคนขับคนนั้น (เพื่อใช้ในการเรียงลำดับคนขับ)
            if (new Date(j.start_time) < new Date(groups[key].earliestTime)) {
                groups[key].earliestTime = j.start_time;
            }
        }
    });

    // 3. เรียงลำดับคนขับ (Driver Sorting)
    const sortedGroups = Object.values(groups).sort((a,b) => {
        if (state.driverSortMode === 'time') return new Date(a.earliestTime) - new Date(b.earliestTime);
        else if (state.driverSortMode === 'jobs') return b.jobs.length - a.jobs.length;
        else if (state.driverSortMode === 'name') return a.name.localeCompare(b.name, 'th');
        else return state.driverOrder.indexOf(parseInt(a.id)) - state.driverOrder.indexOf(parseInt(b.id)); // Custom Order
    });

    // ถ้าไม่มีข้อมูลเลย
    if(sortedGroups.every(g => g.jobs.length === 0)) {
         container.innerHTML = '<div style="text-align:center; padding:40px; color:#999"><i class="fas fa-user-slash fa-2x"></i><br>ไม่พบข้อมูลการวิ่งงานในช่วงนี้</div>';
         return;
    }

    // 4. วนลูปสร้าง Card ของแต่ละคนขับ
    sortedGroups.forEach((g, index) => {
        if(g.jobs.length === 0) return;

        // 🟢 เรียงงาน: ล่าสุด -> เก่าสุด (Descending)
        g.jobs.sort((a, b) => new Date(b.start_time) - new Date(a.start_time));
        
        const total = g.jobs.length;
        
        // เวลาเริ่มงานแรก (เอาไว้โชว์ที่หัวข้อ)
        // เนื่องจากเราเรียง desc ไปแล้ว งานแรกสุดตามเวลาจริงคือตัวสุดท้ายของ array
        const firstJobTime = new Date(g.jobs[g.jobs.length - 1].start_time); 
        const firstJobText = showDate 
            ? firstJobTime.toLocaleDateString('th-TH', {day:'numeric', month:'short'}) + ' ' + firstJobTime.toLocaleTimeString('th-TH', {hour:'2-digit', minute:'2-digit'})
            : firstJobTime.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'}) + ' น.';

        // 🟢 สร้างรายการงานภายใน (Table Body)
        let jobsHtml = '';
        let lastDate = '';

        g.jobs.forEach(j => {
            const d = new Date(j.start_time);
            const dateStr = d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' });
            const timeStr = d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });

            // 🟢 เพิ่มหัวข้อวันที่คั่น (Date Divider)
            if (dateStr !== lastDate) {
                jobsHtml += `
                    <tr style="background:rgba(0,0,0,0.02);">
                        <td colspan="3" style="padding:6px 10px; font-size:11px; font-weight:bold; color:var(--text-secondary); text-align:left; border-bottom:1px solid var(--border);">
                            <i class="far fa-calendar-alt"></i> ${dateStr}
                        </td>
                    </tr>
                `;
                lastDate = dateStr;
            }

            // 🟢 สร้างแถวข้อมูลงาน (แสดง ต้นทาง -> ปลายทาง)
            jobsHtml += `
                <tr onclick="openJobModal(this.dataset.job)" 
                    data-job='${JSON.stringify(j).replace(/'/g, "&#39;")}' 
                    style="cursor:pointer; border-bottom:1px solid var(--border);">
                    
                    <td style="padding:10px; width:20%; vertical-align:top;">
                        <span style="font-weight:bold; color:var(--primary); font-size:13px;">${timeStr}</span>
                    </td>
                    
                    <td style="padding:10px; width:60%; vertical-align:top;">
                        <div style="font-weight:600; font-size:13px; color:var(--text-primary); margin-bottom:2px;">${j.customer_name}</div>
                        <div style="font-size:11px; color:var(--text-secondary);">
                            ${j.origin ? `<span style="opacity:0.8">${j.origin}</span> <i class="fas fa-arrow-right" style="font-size:10px"></i> ` : ''}
                            ${j.destination}
                        </div>
                    </td>
                    
                    <td class="text-center" style="padding:10px; width:20%; vertical-align:top;">
                        <span class="status-badge st-${j.status}" style="font-size:10px; padding:2px 6px;">${getStatusText(j.status)}</span>
                    </td>
                </tr>
            `;
        });

        // สร้าง Card HTML หลัก
        const card = document.createElement('div');
        card.className = 'driver-row-card';
        card.innerHTML = `
            <div class="driver-header" onclick="toggleDriver(${index})">
                <div class="driver-info">
                    <div class="driver-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <div style="font-weight:bold">${g.name}</div>
                        <div style="font-size:12px; color:var(--text-secondary)">
                            <i class="far fa-clock" style="color:var(--primary)"></i> เริ่ม: <b>${firstJobText}</b>
                        </div>
                    </div>
                </div>
                <div class="driver-stats">
                    <div style="text-align:right; margin-right:5px;">
                        <div style="font-size:10px; color:var(--text-secondary)">จำนวนงาน</div>
                        <div style="font-weight:bold;">${total} งาน</div>
                    </div>
                    <i class="fas fa-chevron-down" id="chevron-${index}" style="transition:0.3s"></i>
                </div>
            </div>
            <div class="driver-content" id="content-${index}">
                <div style="padding:0;">
                    <table class="modern-table" style="width:100%; border-collapse:collapse;">
                        <tbody>
                            ${jobsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

    // --- REORDER LOGIC ---
    function toggleReorderMode(){
        const p = document.getElementById('reorderPanel');
        const b = document.getElementById('btnReorder');
        state.reorderMode = !state.reorderMode;

        if(state.reorderMode){ 
            p.style.display = 'block'; 
            b.className = 'btn btn-primary'; 
            renderDraggableList(); 
        } else { 
            p.style.display = 'none'; 
            b.className = 'btn btn-outline'; 
            saveNewOrder();
            renderDriversList(); 
        }
    }

    function renderDraggableList() {
        const list = document.getElementById('reorderList');
        list.innerHTML = '';
        state.driverOrder.forEach((id) => {
            const drv = state.rawDrivers.find(d => d.id == id);
            if (!drv) return;

            const li = document.createElement('li');
            li.className = 'draggable-item';
            li.setAttribute('draggable', 'true');
            li.setAttribute('data-id', id);
            li.innerHTML = `<div style="display:flex; align-items:center;"><i class="fas fa-grip-vertical drag-handle"></i><div style="font-weight:600;">${drv.name}</div></div><i class="fas fa-sort" style="color:#ccc; font-size:12px;"></i>`;
            li.addEventListener('dragstart', () => li.classList.add('dragging'));
            li.addEventListener('dragend', () => { li.classList.remove('dragging'); saveNewOrder(); });
            list.appendChild(li);
        });
        list.addEventListener('dragover', initSortableList);
    }

    function initSortableList(e) {
        e.preventDefault();
        const list = document.getElementById('reorderList');
        const draggingItem = list.querySelector('.dragging');
        let siblings = [...list.querySelectorAll('.draggable-item:not(.dragging)')];
        let nextSibling = siblings.find(sibling => {
            return e.clientY <= sibling.getBoundingClientRect().top + sibling.offsetHeight / 2;
        });
        list.insertBefore(draggingItem, nextSibling);
    }

    function saveNewOrder() {
        const listItems = document.querySelectorAll('.draggable-item');
        const newOrder = [];
        listItems.forEach(item => newOrder.push(parseInt(item.getAttribute('data-id'))));
        state.driverOrder = newOrder;
        localStorage.setItem('fm_driver_order', JSON.stringify(state.driverOrder));
        
        // Auto select custom sort
        document.getElementById('driverSortSelect').value = 'custom';
        state.driverSortMode = 'custom';
    }

    // --- UTILITIES ---
    function setViewMode(mode) {
        state.viewMode = mode;
        fetchData();
    }
    
    function setDisplayMode(mode) {
        state.displayMode = mode;
        render();
    }
    
    function handleDateChange() {
        state.selectedDate = document.getElementById('datePicker').value;
        state.selectedMonth = document.getElementById('monthPicker').value;
        state.selectedYear = document.getElementById('yearPicker').value;
        fetchData();
    }
    
    function showLoading(show) {
        document.getElementById('loading').style.display = show ? 'block' : 'none';
        document.getElementById('main-content').style.display = show ? 'none' : 'block';
    }

    function getDisplayPrice(job) {
        const jobPrice = Number(job.actual_price) || 0;
        if (jobPrice > 0) return { value: jobPrice, type: 'income' };
        if (job.job_groups) {
            const gPrice = Number(job.job_groups.total_price) || 0;
            const gType = job.job_groups.type || 'income';
            if (gPrice > 0) return { value: gPrice, type: gType };
        }
        return { value: 0, type: 'none' };
    }

    function getStatusText(status) {
        switch(status) {
            case 'completed': return 'สำเร็จ';
            case 'in_progress': return 'กำลังส่ง';
            case 'failed': return 'ไม่สำเร็จ';
            case 'canceled': return 'ยกเลิก';
            default: return 'รอส่ง';
        }
    }

    function generateYearOptions() {
        const sel = document.getElementById('yearPicker');
        const cy = new Date().getFullYear();
        for(let i=cy; i>=cy-5; i--) {
            sel.innerHTML += `<option value="${i}">${i+543}</option>`;
        }
    }

    function toggleDriver(index) {
        const content = document.getElementById(`content-${index}`);
        const chevron = document.getElementById(`chevron-${index}`);
        if (content.classList.contains('show')) {
            content.classList.remove('show');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            content.classList.add('show');
            chevron.style.transform = 'rotate(180deg)';
        }
    }

    function openJobModal(jobData) {
    // แปลงข้อมูลเป็น Object (เพราะบางทีส่งมาเป็น String จาก data-attribute)
    const job = (typeof jobData === 'string') ? JSON.parse(jobData) : jobData;
    
    const modal = document.getElementById('jobModal');
    const header = modal.querySelector('.modal-header');
    const body = document.getElementById('modal-body');
    const priceInfo = getDisplayPrice(job); // ใช้ฟังก์ชันคำนวณราคาที่มีอยู่เดิม

    // 1. ส่วนหัว Modal (ปุ่มปิดแบบใหม่ + Dark Mode)
    header.innerHTML = `
        <h3 style="margin:0; display:flex; align-items:center; gap:10px; color: var(--text-primary);">
            <i class="fas fa-info-circle" style="color: var(--primary);"></i> รายละเอียดงาน
        </h3>
        <button onclick="closeModal('force')" 
                style="border:none; 
                       background: var(--bg-body); 
                       cursor:pointer; 
                       color: var(--danger); 
                       width: 32px; 
                       height: 32px; 
                       border-radius: 50%; 
                       display: flex; 
                       align-items: center; 
                       justify-content: center; 
                       font-size: 18px; 
                       transition: 0.2s;
                       box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <i class="fas fa-times"></i>
        </button>
    `;

    // 2. 🔥 Logic สร้างการแสดงผลเส้นทาง (ต้นทาง -> ปลายทาง) 🔥
    let routeLabel = "ปลายทาง"; 
    // ค่าเริ่มต้น: แสดงแค่ปลายทาง (ใช้สี variable เพื่อรองรับ Dark Mode)
    let routeDisplay = `<span style="color:var(--text-primary)">${job.destination}</span>`; 
    
    // ถ้ามีข้อมูลต้นทาง ให้แสดงแบบมีลูกศรเชื่อม
    if (job.origin && job.origin.trim() !== "") {
        routeLabel = "เส้นทาง";
        routeDisplay = `
            <span style="color:var(--text-secondary)">${job.origin}</span> 
            <i class="fas fa-long-arrow-alt-right" style="color:var(--primary); margin:0 5px;"></i> 
            <span style="color:var(--text-primary)">${job.destination}</span>
        `;
    }

    // 3. Logic แสดงรายละเอียดกลุ่มงาน (ถ้ามี)
    let groupDetailsHtml = '';
    if (job.group_id && job.group_id != 0) {
        // กรองหางานในกลุ่มเดียวกันจาก state.rawJobs
        const relatedJobs = state.rawJobs.filter(item => item.group_id == job.group_id);
        groupDetailsHtml = `
            <div class="detail-item full-width" style="background: rgba(99, 102, 241, 0.05); border-left: 4px solid #6366f1; margin-top: 10px; grid-column: 1 / -1;">
                <span class="detail-label" style="color: #6366f1;"><i class="fas fa-boxes"></i> รายละเอียดบิลเหมา (${relatedJobs.length} รายการ)</span>
                <span class="detail-value" style="color: #6366f1; font-size: 14px;">ชื่อบิล: ${job.group_name}</span>
                <div style="margin-top:8px; font-size:12px; color:var(--text-secondary);">
                    <strong>รายการลูกค้าในบิลนี้:</strong>
                    <ul style="padding-left:18px; margin-top:5px; list-style: disc;">
                        ${relatedJobs.map(rj => `<li>${rj.customer_name} (${rj.destination})</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;
    }

    // 4. Logic แสดงรูปภาพหลักฐาน
    const imgHtml = job.proof_image 
        ? `<div style="margin-bottom:15px; border-radius:8px; overflow:hidden; border:1px solid var(--border)">
            <img src="uploads/proofs/${job.proof_image}" style="width:100%; max-height:250px; object-fit:cover">
           </div>` 
        : '';

    // 5. ประกอบร่าง HTML ลงใน Body
    body.innerHTML = `
        ${imgHtml}
        <div style="text-align:center; margin-bottom:20px">
            <span class="status-badge st-${job.status}" style="font-size:14px; padding:6px 15px">${getStatusText(job.status)}</span>
        </div>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label"><i class="far fa-calendar"></i> วันที่</span>
                <span class="detail-value">${new Date(job.start_time).toLocaleDateString('th-TH')}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="far fa-clock"></i> เวลา</span>
                <span class="detail-value">${job.start_time.substring(11,16)} น.</span>
            </div>
            <div class="detail-item full-width">
                <span class="detail-label"><i class="fas fa-user"></i> ลูกค้า</span>
                <span class="detail-value" style="font-size:16px">${job.customer_name}</span>
            </div>
            
            ${groupDetailsHtml}

            <div class="detail-item full-width">
                <span class="detail-label"><i class="fas fa-map-marker-alt"></i> ${routeLabel}</span>
                <span class="detail-value" style="font-size:16px">${routeDisplay}</span>
            </div>

            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-truck"></i> รถ</span>
                <span class="detail-value">${job.vehicles?.fleet_number ? 'เบอร์ '+job.vehicles.fleet_number : (job.vehicles?.plate_number || '-')}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-id-card"></i> คนขับ</span>
                <span class="detail-value">${job.driver_name || '-'}</span>
            </div>
            <div class="detail-item full-width" style="border-left: 4px solid var(--danger);">
                <span class="detail-label" style="color: var(--danger)">รายจ่าย</span>
                <span class="detail-value" style="color: var(--danger); font-size: 18px;">
                    ${(Number(job.cost) > 0 || (priceInfo.type === 'cost' && priceInfo.value > 0)) 
                        ? '฿' + (Number(job.cost) || priceInfo.value).toLocaleString() 
                        : '-'}
                </span>
            </div>
        </div>
    `;

    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(e) {
    const modal = document.getElementById('jobModal');
    // ✅ ตรวจสอบว่าเป็นการกดปุ่มปิด หรือกดที่พื้นหลังสีดำรอบๆ
    if (e === 'force' || e.target === modal) {
        modal.classList.remove('show');
        // ✅ คืนค่าการเลื่อนปกติให้หน้าเว็บ
        document.body.style.overflow = '';
    }
}
function handleCategoryChange() {
    state.filterCategory = document.getElementById('filterCategory').value;
    processData(); // คำนวณข้อมูลใหม่ตามตัวกรอง
    render();      // วาดหน้าจอใหม่
}

async function updateSidebarTransportBadge() {
    // เช็คก่อนว่ามี Element Badge หรือไม่ (ถ้า User ไม่มีสิทธิ์เห็นเมนูนี้ ก็ไม่ต้องทำ)
    const badge = document.getElementById('sidebar-transport-badge');
    if (!badge) return;

    try {
        // ดึงเดือนปัจจุบัน เพื่อเช็คงานของเดือนนี้ (หรือคุณจะเปลี่ยน Logic ให้เช็คทั้งหมดก็ได้)
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const currentMonth = `${year}-${month}`;

        // เรียก API (ใช้ไฟล์เดียวกับหน้า Dashboard)
        const response = await fetch(`api_fm.php?action=fetch_dashboard&month=${currentMonth}`);
        const data = await response.json();

        if (data && data.jobs) {
            // นับจำนวนงานที่สถานะ: Failed (ไม่สำเร็จ), Pending (รอส่ง), Canceled (ยกเลิก)
            // หรือถ้าต้องการนับแค่ "รอส่ง" ให้แก้เงื่อนไขตรงนี้
            const alertCount = data.jobs.filter(j => 
                ['failed', 'pending', 'canceled'].includes(j.status)
            ).length;

            if (alertCount > 0) {
                badge.innerText = alertCount;
                badge.style.display = 'inline-block'; // แสดง Badge
                
                // เพิ่ม Animation ให้ดูเด่นขึ้นถ้าเป็น Sidebar
                badge.classList.add('nav-badge'); 
            } else {
                badge.style.display = 'none'; // ซ่อน Badge
            }
        }
    } catch (error) {
        console.error("Sidebar Badge Error:", error);
    }
}
document.addEventListener('DOMContentLoaded', () => {
    updateSidebarTransportBadge(); // เรียกครั้งแรกทันที
    setInterval(updateSidebarTransportBadge, 30000);
});

</script>

</body>
</html>