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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ประวัติการซ่อมบำรุง | TJC GROUP</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" href="images/LOgoTJC.png" type="images/LOgoTJC.png">
<style>
    /* --- 🟢 1. Color Variables --- */
:root { 
        --primary-color: #3b82f6; 
        --primary-dark: #1d4ed8; 
        --text-main: #1e293b; 
        --text-sub: #64748b; 
        --bg-body: #f8fafc; /* พื้นหลังสว่างที่นุ่มนวลขึ้น */
        --bg-card: #ffffff; 
        --bg-input: #ffffff; 
        --border-color: #e2e8f0; 
        --shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        --hover-bg: #f1f5f9; 
        --table-head-bg: #f8fafc; 
        --table-head-text: #64748b; 
        --table-row-hover: #f1f5f9; 
    }

    body.dark-mode {
        --text-main: #f1f5f9;
        --text-sub: #94a3b8;
        --bg-body: #0f172a;
        --bg-card: #1e293b;
        --bg-input: #334155; /* ช่อง Input เข้มขึ้น */
        --border-color: #334155;
        --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
        --hover-bg: #2d3748;
        --table-head-bg: #111827;
        --table-head-text: #94a3b8;
        --table-row-hover: #1e293b;
    }

    /* เพิ่มเติมสำหรับการจัดการสี Input ใน Dark Mode */
    body.dark-mode input, 
    body.dark-mode textarea, 
    body.dark-mode select {
        color: white;
    }

    /* ปรับสี Scrollbar สำหรับ Dark Mode */
    body.dark-mode::-webkit-scrollbar { width: 8px; }
    body.dark-mode::-webkit-scrollbar-track { background: #0f172a; }
    body.dark-mode::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        /* --- 🟢 2. Page Layout --- */
        body { font-family: 'Prompt', sans-serif; margin: 0; background-color: var(--bg-body); color: var(--text-main); transition: background-color 0.3s ease, color 0.3s ease; }
        .maintenance-container { padding: 20px; width: 100%; transition: all 0.3s ease; max-width: 1400px; margin: 0 auto; box-sizing: border-box; }
        
        @media (max-width: 900px) { 
            .maintenance-container { padding: 15px; padding-top: 80px; } 
        }

        /* Header & Filter */
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; background-color: var(--bg-card); padding: 20px; border-radius: 15px; box-shadow: var(--shadow); border: 1px solid var(--border-color); margin-bottom: 25px; }
        .header-title h1 { margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 10px; }
        .header-title p { margin: 5px 0 0; font-size: 0.9rem; color: var(--text-sub); }
        .header-icon { color: var(--primary-color); background: var(--hover-bg); padding: 10px; border-radius: 10px; }
        .summary-box { text-align: right; padding-left: 20px; border-left: 2px solid var(--border-color); }
        .summary-label { font-size: 0.8rem; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-value { font-size: 1.6rem; font-weight: 700; color: var(--primary-color); line-height: 1.2; }

        .filter-section { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; background: var(--bg-card); padding: 15px; border-radius: 15px; box-shadow: var(--shadow); border: 1px solid var(--border-color); }
        .search-wrapper { flex: 1; min-width: 250px; position: relative; }
        .search-wrapper i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-sub); }
        .search-wrapper input { width: 100%; padding: 10px 10px 10px 35px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); outline: none; transition: 0.2s; box-sizing: border-box; }
        .date-input { padding: 10px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); outline: none; }

        /* --- 🟢 3. Table & Mobile Cards (Responsive) --- */
        .table-responsive { background: var(--bg-card); border-radius: 15px; overflow: hidden; box-shadow: var(--shadow); border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 15px; text-align: left; font-size: 0.9rem; font-weight: 600; background: var(--table-head-bg); color: var(--table-head-text); border-bottom: 2px solid var(--border-color); }
        td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: var(--text-main); vertical-align: middle; font-size: 0.95rem; }
        tr:hover td { background-color: var(--table-row-hover); }

        /* Responsive Table */
        @media (max-width: 900px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border-bottom: 1px solid var(--border-color); margin-bottom: 10px; }
            td { border: none; position: relative; padding-left: 45% !important; text-align: right !important; }
            td:before { content: attr(data-label); position: absolute; left: 15px; width: 40%; text-align: left; font-weight: 700; color: var(--text-sub); font-size: 0.8rem; }
            td:last-child { text-align: center !important; padding-left: 15px !important; border-bottom: 2px solid var(--border-color); padding-bottom: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .summary-box { border-left: none; padding-left: 0; text-align: left; }
        }

        /* Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
        .badge:hover { transform: scale(1.05); filter: brightness(0.95); }
        .badge-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3); }
        .badge-comparing { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3); }
        .badge-approved { background: rgba(99, 102, 241, 0.15); color: #6366f1; border-color: rgba(99, 102, 241, 0.3); }
        .badge-completed { background: rgba(16, 185, 129, 0.15); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
        .badge-rejected { background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3); }
        .plate-badge { background: var(--hover-bg); color: var(--primary-color); padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; border: 1px solid var(--border-color); }
        
        /* Images */
        .thumb-img { width: 45px; height: 45px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border-color); cursor: pointer; transition: 0.2s; background: #eee; }
        .thumb-img:hover { transform: scale(1.2); border-color: var(--primary-color); z-index: 10; }
        
        /* Buttons */
        .action-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); background: transparent; color: var(--text-sub); cursor: pointer; transition: 0.2s; margin-right: 4px; }
        .action-btn:hover { background: var(--hover-bg); color: var(--primary-color); border-color: var(--primary-color); }
        .action-btn.delete-btn:hover { background: #fee2e2; color: #ef4444; border-color: #ef4444; }
        .action-btn.receipt-btn { color: #10b981; border-color: #10b981; background: rgba(16, 185, 129, 0.05); }
        .action-btn.receipt-btn:hover { background: #10b981; color: white; }

        .btn-custom { padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; font-size: 0.9rem; }
        .btn-primary-c { background: var(--primary-color); color: #fff; }
        .btn-outline-c { background: transparent; border: 1px solid var(--border-color); color: var(--text-sub); }

        /* Modal & Timeline */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal-overlay.show { display: flex; animation: fadeIn 0.2s; }
        .modal-box { background: var(--bg-card); width: 95%; max-width: 600px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: 1px solid var(--border-color); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--table-head-bg); }
        .modal-body { padding: 20px; overflow-y: auto; color: var(--text-main); }
        
        .status-timeline { display: flex; align-items: center; justify-content: space-between; position: relative; margin-top: 10px; padding: 0 10px; }
        .status-step { display: flex; flex-direction: column; align-items: center; cursor: pointer; z-index: 2; position: relative; flex: 1; opacity: 0.5; transition: 0.3s; }
        .status-step:hover { opacity: 1; transform: translateY(-2px); }
        .step-icon { width: 35px; height: 35px; border-radius: 50%; background: var(--bg-card); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: var(--text-sub); font-size: 0.9rem; transition: 0.3s; }
        .step-label { font-size: 0.75rem; color: var(--text-sub); margin-top: 6px; font-weight: 600; text-align: center; }
        .status-line { flex: 1; height: 2px; background: var(--border-color); margin: 0 -10px; position: relative; top: -14px; z-index: 1; }
        .status-step.active { opacity: 1; }
        .status-step.active .step-icon { background: var(--primary-color); border-color: var(--primary-color); color: white; transform: scale(1.1); box-shadow: 0 4px 10px rgba(59, 130, 246, 0.4); }
        .status-step.active .step-label { color: var(--primary-color); }
        .status-step.completed { opacity: 1; }
        .status-step.completed .step-icon { background: var(--success); border-color: var(--success); color: white; }
        .status-step.completed .step-label { color: var(--success); }

        .detail-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed var(--border-color); }
        .detail-label { color: var(--text-sub); }
        .detail-val { font-weight: 600; color: var(--text-main); text-align: right; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 6px; color: var(--text-sub); font-size: 0.9rem; }
        .form-control-modal { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); outline: none; box-sizing: border-box; }

        .lightbox { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 3000; display: none; justify-content: center; align-items: center; }
        .lightbox img { max-width: 90%; max-height: 90vh; border-radius: 8px; }
        .lightbox-close { position: absolute; top: 20px; right: 20px; color: white; background: none; border: none; font-size: 30px; cursor: pointer; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* --- แก้ไข SweetAlert2 สำหรับ Dark Mode --- */
body.dark-mode .swal2-popup {
    background-color: var(--bg-card) !important; /* ใช้สีพื้นหลังเดียวกับ Card */
    color: var(--text-main) !important;
    border: 1px solid var(--border-color);
}

body.dark-mode .swal2-title, 
body.dark-mode .swal2-html-container {
    color: var(--text-main) !important; /* แก้ให้ "เลือกสถานะ" มองเห็นได้ */
}

body.dark-mode .swal2-input, 
body.dark-mode .swal2-select, 
body.dark-mode .swal2-textarea {
    background-color: var(--bg-input) !important;
    color: var(--text-main) !important;
    border: 1px solid var(--border-color) !important;
}

/* ปรับสีปุ่ม Cancel ใน SweetAlert */
body.dark-mode .swal2-cancel {
    background-color: #4b5563 !important;
}
.stats-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
        gap: 20px; 
        margin-bottom: 25px; 
    }
    .stat-card { 
        background: var(--bg-card); 
        padding: 20px; 
        border-radius: 15px; 
        box-shadow: var(--shadow); 
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        cursor: pointer; /* ทำให้รู้ว่ากดได้ */
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover { 
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        border-color: var(--primary-color);
    }
    .stat-card.active {
        border-color: var(--primary-color);
        background: rgba(59, 130, 246, 0.05);
    }
    .stat-label { 
        font-size: 0.85rem; 
        color: var(--text-sub); 
        display: flex; 
        align-items: center; 
        gap: 8px;
        margin-bottom: 10px; 
    }
    .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--text-main); }
    .stat-card i { font-size: 1.2rem; color: var(--primary-color); }
    
    /* สีพิเศษสำหรับยอดรวมที่กรอง */
    .stat-card.filter-active { border-left: 5px solid #10b981; }
    .stat-card.filter-active .stat-value { color: #10b981; }

    /* ปรับปรุง Filter Bar */
    .filter-group-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        background: var(--bg-card);
        padding: 15px;
        border-radius: 15px;
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
    }

    /* เพิ่มความสูงของแถวและกำหนดความกว้างคอลัมน์ */
table.modern-table {
    width: 100%;
    border-spacing: 0 8px; /* เพิ่มช่องว่างระหว่างแถวเพื่อให้ดูโปร่ง */
    border-collapse: separate;
}

table.modern-table th {
    padding: 15px 20px;
    font-size: 0.9rem;
    color: #94a3b8; /* สีเทาอ่อนเพื่อให้หัวข้อไม่เด่นแย่งเนื้อหา */
}

table.modern-table td {
    padding: 18px 20px; /* เพิ่ม Padding ให้แถวดูหนาและพรีเมียมขึ้น */
    background: #1e293b; /* พื้นหลังของแถว */
}

/* กำหนดความกว้างคอลัมน์ที่เหมาะสม */
th:nth-child(1), td:nth-child(1) { width: 120px; } /* วันที่ */
th:nth-child(2), td:nth-child(2) { width: 180px; } /* รถ */
th:nth-child(5), td:nth-child(5) { width: 140px; text-align: right; } /* ค่าใช้จ่าย */
th:nth-child(6), td:nth-child(6) { width: 120px; text-align: center; } /* สถานะ */

.search-wrapper input {
    height: 45px;
    background: #0f172a00 !important;
    border: 1px solid #334155 !important;
    border-radius: 12px !important;
    font-size: 0.95rem;
}

.btn-custom {
    height: 45px;
    padding: 0 20px;
    border-radius: 12px !important;
    font-weight: 600;
}
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>
        <script>
    // ป้องกันหน้าจอกะพริบ (Flash) โดยการเช็คธีมทันทีที่เริ่มโหลดหน้า
    (function() {
        const savedTheme = localStorage.getItem('tjc_theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.body.classList.add('dark-mode');
        }
    })();
</script>
    <div class="maintenance-container">
    <header class="page-header">
        <div class="header-title">
            <i class="fas fa-tools header-icon fa-lg"></i>
            <div>
                <h1>ประวัติการซ่อมบำรุง</h1>
                <p>คลิกที่การ์ดเพื่อกรองข้อมูลตามช่วงเวลา</p>
            </div>
        </div>
      
    </header>

 <div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">
            <i class="fas fa-wallet"></i> 
            <span style="margin-left: 10px;">รายจ่ายรวมทั้งหมด</span>
        </div>
        <div class="stat-value" id="totalCostDisplay">฿0</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">
            <i class="fas fa-filter"></i>
            <span style="margin-left: 10px;">รายจ่ายการซ่อมบำรุง</span>
        </div>
        <div class="stat-value" id="totalFiltered">฿0</div>
    </div>
</div>
<section class="filter-group-wrapper" style="display: flex; flex-direction: column; gap: 15px;">
    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
        <div class="toggle-group" style="display: flex; gap: 5px; background: var(--bg-body); padding: 5px; border-radius: 10px; border: 1px solid var(--border-color);">
            <button class="toggle-btn active" id="btn-daily" onclick="setFilterMode('daily')" style="padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; font-family: inherit; font-weight: 600;">รายวัน</button>
            <button class="toggle-btn" id="btn-monthly" onclick="setFilterMode('monthly')" style="padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; font-family: inherit; font-weight: 600;">รายเดือน</button>
            <button class="toggle-btn" id="btn-yearly" onclick="setFilterMode('yearly')" style="padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; font-family: inherit; font-weight: 600;">รายปี</button>
        </div>

        <div id="filter-input-container" style="display: flex; gap: 10px;">
            <input type="date" id="dateInput" class="date-input" onchange="filterLogs()">
            <input type="month" id="monthInput" class="date-input" style="display:none;" onchange="filterLogs()">
            <select id="yearInput" class="date-input" style="display:none;" onchange="filterLogs()"></select>
        </div>
    </div>

    <div style="display: flex; gap: 10px; align-items: center; width: 100%;">
        <div class="search-wrapper" style="flex: 1;">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="ค้นหาทะเบียนรถ, ร้านซ่อม, รายการซ่อม..." onkeyup="filterLogs()">
        </div>
        <button class="btn-custom btn-outline-c" onclick="resetFilters()"><i class="fas fa-undo"></i> ล้างค่า</button>
        <button class="btn-custom btn-primary-c" onclick="fetchLogs()"><i class="fas fa-sync-alt"></i> รีโหลด</button>
    </div>
</section>


    <div class="table-responsive">
        </div>
</div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>รถ</th>
                        <th>สถานที่ซ่อม</th>
                        <th>รายการหลัก</th>
                        <th style="text-align: right;">ค่าใช้จ่าย</th>
                        <th style="text-align: center;">สถานะ</th>
                        <th>รูปภาพ</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <tr><td colspan="8" style="text-align:center; padding:50px; color:var(--text-sub);"><i class="fas fa-circle-notch fa-spin"></i> กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-overlay" id="detailModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> รายละเอียด</h3>
                <button onclick="closeModal('detailModal')" style="border:none; background:none; cursor:pointer; color:var(--text-main);"><i class="fas fa-times fa-lg"></i></button>
            </div>
            <div class="modal-body">
                <div style="background: var(--hover-bg); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                    <h4 style="margin:0 0 15px 0; color:var(--text-main); font-size:0.9rem;">สถานะการดำเนินการ:</h4>
                    <div class="status-timeline">
                        <div class="status-step" id="step-pending" onclick="setStatus('pending')">
                            <div class="step-icon"><i class="fas fa-clock"></i></div>
                            <div class="step-label">รออนุมัติ</div>
                        </div>
                        <div class="status-line"></div>
                        <div class="status-step" id="step-comparing" onclick="setStatus('comparing')">
                            <div class="step-icon"><i class="fas fa-balance-scale"></i></div>
                            <div class="step-label">เทียบราคา</div>
                        </div>
                        <div class="status-line"></div>
                        <div class="status-step" id="step-approved" onclick="setStatus('approved')">
                            <div class="step-icon"><i class="fas fa-check"></i></div>
                            <div class="step-label">อนุมัติซ่อม</div>
                        </div>
                        <div class="status-line"></div>
                        <div class="status-step" id="step-completed" onclick="setStatus('completed')">
                            <div class="step-icon"><i class="fas fa-flag-checkered"></i></div>
                            <div class="step-label">เสร็จสิ้น</div>
                        </div>
                    </div>
                </div>

                <div class="detail-row"><span class="detail-label">วันที่ส่งซ่อม</span> <span class="detail-val" id="dRepairDate">-</span></div>
                <div class="detail-row"><span class="detail-label">ทะเบียนรถ</span> <span class="detail-val" id="dVehicle">-</span></div>
                <div class="detail-row"><span class="detail-label">สถานที่ซ่อม</span> <span class="detail-val" id="dCenter">-</span></div>
                <div class="detail-row"><span class="detail-label">ค่าใช้จ่าย</span> <span class="detail-val" style="color:var(--primary-color); font-size:1.2rem;" id="dCost">-</span></div>
                <div class="detail-row" style="border:none;"><span class="detail-label">รับรถเมื่อ</span> <span class="detail-val" id="dPickupDate">-</span></div>

                <div style="background: var(--hover-bg); padding: 15px; border-radius: 10px; margin-top: 10px;">
                    <strong style="color:var(--text-sub); font-size:0.9rem;">รายการซ่อม:</strong>
                    <ul id="dDescList" style="margin:5px 0 0 20px; color:var(--text-main);"></ul>
                </div>

                <div style="margin-top:20px;">
                    <strong style="color:var(--text-sub); font-size:0.9rem;">รูปภาพแจ้งซ่อม:</strong>
                    <div id="dImages" style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;"></div>
                </div>

                <div id="receiptSection" style="margin-top:20px; display:none;">
                    <button id="btnViewReceipt" class="btn-custom btn-outline-c" style="width:100%; justify-content:center; border-style:dashed; color:#10b981; border-color:#10b981;">
                        <i class="fas fa-receipt"></i> ดูใบเสร็จรับเงิน
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> แก้ไขข้อมูล</h3>
                <button onclick="closeModal('editModal')" style="border:none; background:none; cursor:pointer; color:var(--text-main);"><i class="fas fa-times fa-lg"></i></button>
            </div>
            <div class="modal-body">
                <form id="editForm" onsubmit="handleUpdateLog(event)">
                    <input type="hidden" id="eId">
                    <div class="form-group"><label class="form-label">วันที่ซ่อม</label><input type="date" id="eDate" class="form-control-modal" required></div>
                    <div class="form-group"><label class="form-label">สถานที่ซ่อม</label><input type="text" id="eCenter" class="form-control-modal"></div>
                    <div class="form-group"><label class="form-label">รายการซ่อม (แยกบรรทัด)</label><textarea id="eDesc" class="form-control-modal" rows="4"></textarea></div>
                    <div class="form-group"><label class="form-label">ค่าใช้จ่าย</label><input type="number" id="eCost" class="form-control-modal" step="0.01"></div>
                    <div class="form-group"><label class="form-label">วันที่รับรถ</label><input type="datetime-local" id="ePickup" class="form-control-modal"></div>
                    
                    <div class="form-group">
                        <label class="form-label">แนบใบเสร็จ/บิล (ไฟล์ภาพ)</label>
                        <input type="file" id="eReceipt" class="form-control-modal" accept="image/*">
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                        <button type="button" class="btn-custom btn-outline-c" onclick="closeModal('editModal')">ยกเลิก</button>
                        <button type="submit" class="btn-custom btn-primary-c">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="lightbox" id="lightbox" onclick="this.style.display='none'">
        <button class="lightbox-close">&times;</button>
        <img id="lightboxImg" src="">
    </div>
    
<script>
  function toggleDarkMode() {
        const body = document.body;
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        
        // บันทึกลง LocalStorage
        localStorage.setItem('tjc_theme', isDark ? 'dark' : 'light');
        
        // อัปเดต UI ของปุ่ม
        updateDarkModeBtn();
        
        // ถ้า Sidebar มีฟังก์ชันรับธีม สามารถเรียกที่นี่ได้ (ถ้าจำเป็น)
        // updateSidebarTheme(isDark); 
    }

    function updateDarkModeBtn() {
        const btn = document.getElementById('darkModeBtn');
        if (!btn) return;
        const isDark = document.body.classList.contains('dark-mode');
        btn.innerHTML = isDark ? '<i class="fas fa-sun"></i> โหมดสว่าง' : '<i class="fas fa-moon"></i> โหมดมืด';
        
        // เปลี่ยนคลาสปุ่มตามธีม (Option)
        if(isDark) {
            btn.classList.replace('btn-outline-c', 'btn-primary-c');
        } else {
            btn.classList.replace('btn-primary-c', 'btn-outline-c');
        }
    }

    // --- 🟢 API Logic ---
    const API_URL = 'api_fm.php'; 
    const REPAIR_URL = 'uploads/maintenance';      
    const TAX_URL = 'uploads/tax_maintenance';     

    let logs = [];
    let selectedLogId = null;

    document.addEventListener('DOMContentLoaded', () => { 
        if (localStorage.getItem('tjc_theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
        updateDarkModeBtn();
        fetchLogs(); 
    });

    async function fetchLogs() {
        try {
            const res = await fetch(`${API_URL}?action=fetch_repair_logs_all`); 
            const data = await res.json();
            logs = Array.isArray(data) ? data : [];
            renderTable(logs);
        } catch (err) {
            console.error(err);
            document.getElementById('logsTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--danger);">เชื่อมต่อ API ไม่สำเร็จ</td></tr>';
        }
    }

    function cleanFileName(src) {
        if (!src || src === 'null' || src === 'undefined') return '';
        let s = String(src);
        s = s.replace(/uploads\/maintenance\//g, '').replace(/uploads\/tax_maintenance\//g, '').replace(/\\/g, '/'); 
        s = s.replace(/[\[\]"']/g, '');
        return s.trim();
    }

    function getRepairPath(src) {
        if (!src) return 'https://via.placeholder.com/50?text=No+Img';
        const cleanName = cleanFileName(src);
        return `${REPAIR_URL}/${cleanName}`;
    }

    function getTaxPath(src) {
        if (!src) return '';
        const cleanName = cleanFileName(src);
        return `${TAX_URL}/${cleanName}`;
    }

    function openLightbox(src) { 
        document.getElementById('lightboxImg').src = src; 
        document.getElementById('lightbox').style.display = 'flex'; 
    }

    function renderTable(data) {
    const tbody = document.getElementById('logsTableBody');
    const totalDisplay = document.getElementById('totalCostDisplay');
    tbody.innerHTML = '';
    let total = 0;

    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:50px; color:var(--text-sub);"><i class="fas fa-tools fa-2x" style="display:block; margin-bottom:10px; opacity:0.3;"></i>ไม่พบข้อมูลประวัติการซ่อม</td></tr>';
        if (totalDisplay) totalDisplay.innerText = '฿0';
        return;
    }

    data.forEach(log => {
        // 1. คำนวณยอดรวม (ไม่ติดลบ)
        const costValue = Math.abs(Number(log.cost || 0)); 
        total += costValue;
        
        // 2. ตั้งค่า Status Badge
        let badgeClass = 'badge-pending', badgeText = 'รอดำเนินการ', icon = 'clock';
        if(log.status === 'comparing'){ badgeClass = 'badge-comparing'; badgeText = 'เทียบราคา'; icon = 'balance-scale'; }
        else if(log.status === 'approved'){ badgeClass = 'badge-approved'; badgeText = 'กำลังซ่อม'; icon = 'tools'; }
        else if(log.status === 'completed'){ badgeClass = 'badge-completed'; badgeText = 'เสร็จสิ้น'; icon = 'check-circle'; }
        else if(log.status === 'rejected'){ badgeClass = 'badge-rejected'; badgeText = 'ไม่อนุมัติ'; icon = 'times-circle'; }

        const statusBadge = `<span class="badge ${badgeClass}" onclick="event.stopPropagation(); openQuickStatus(${log.id}, '${log.status}')">
                                <i class="fas fa-${icon}"></i> ${badgeText}
                            </span>`;

        // 3. จัดการรูปภาพซ่อม
        let imgArr = [];
        if (Array.isArray(log.images)) { imgArr = log.images; } 
        else if (typeof log.images === 'string') { 
            try { imgArr = JSON.parse(log.images); } catch(e){ imgArr = log.images ? [log.images] : []; } 
        }
        imgArr = imgArr.map(item => cleanFileName(item)).filter(item => item !== "");

        let imgsHtml = '<span style="color:#ccc; font-size:12px;">ไม่มีรูป</span>';
        if (imgArr.length > 0) {
            const firstImg = getRepairPath(imgArr[0]);
            imgsHtml = `<div class="thumb-container">
                            <img src="${firstImg}" class="thumb-img" onclick="event.stopPropagation(); openLightbox('${firstImg}')">
                            ${imgArr.length > 1 ? `<span class="img-count">+${imgArr.length - 1}</span>` : ''}
                        </div>`;
        }

        // 4. สร้างแถวตาราง
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.onclick = () => openDetail(log);
        tr.innerHTML = `
            <td data-label="วันที่"><b>${log.repair_date ? new Date(log.repair_date).toLocaleDateString('th-TH') : '-'}</b></td>
            <td data-label="รถ">
                <span class="plate-badge">
                    ${log.fleet_number ? '<small>#' + log.fleet_number + '</small> ' : ''}
                    ${log.plate_number || '-'}
                </span>
            </td>
            <td data-label="สถานที่ซ่อม"><i class="fas fa-store-alt" style="color:#94a3b8; margin-right:5px;"></i>${log.service_center || '-'}</td>
            <td data-label="รายการหลัก">
                <div style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--text-secondary);">
                    ${log.description || '-'}
                </div>
            </td>
            <td data-label="ค่าใช้จ่าย" style="text-align:right; font-weight:700; color:var(--primary);">
                ฿${costValue.toLocaleString()}
            </td>
            <td data-label="สถานะ" style="text-align:center;">${statusBadge}</td>
            <td data-label="รูปภาพ" style="text-align:center;">${imgsHtml}</td>
        `;
        tbody.appendChild(tr);
    });

    // 5. แสดงผลยอดรวมที่ Footer/Header
    if (totalDisplay) {
        totalDisplay.innerText = '฿' + total.toLocaleString();
    }
}
   async function deleteLog(id) {
    const result = await Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "ข้อมูลจะถูกลบถาวร ไม่สามารถกู้คืนได้",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ลบข้อมูล',
        cancelButtonText: 'ยกเลิก'
    });

    if (result.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'delete_log'); // ตรวจสอบว่าชื่อ action ตรงกับใน PHP
        fd.append('id', id);
        
        try {
            // แสดงสถานะกำลังโหลด
            Swal.showLoading();
            
            const res = await fetch(API_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                await Swal.fire('ลบสำเร็จ!', 'ข้อมูลถูกลบเรียบร้อยแล้ว', 'success');
                fetchLogs(); // รีโหลดตาราง
            } else {
                // ถ้า API ส่ง success: false กลับมา
                throw new Error(data.message || 'เกิดข้อผิดพลาดจาก Server');
            }
        } catch(e) {
            console.error("Delete Error:", e);
            Swal.fire('Error', 'ลบไม่สำเร็จ: ' + e.message, 'error');
        }
    }
}

    async function setStatus(status, id = null) {
        const targetId = id || selectedLogId;
        if (!targetId) return;

        const statusMap = { 'pending':'รออนุมัติ', 'comparing':'เทียบราคา', 'approved':'อนุมัติซ่อม', 'completed':'เสร็จสิ้น', 'rejected':'ไม่อนุมัติ' };
        
        const result = await Swal.fire({
            title: 'เปลี่ยนสถานะ?',
            html: `เปลี่ยนเป็น <b>"${statusMap[status]}"</b> ใช่หรือไม่?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: 'var(--primary-color)',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        });

        if (result.isConfirmed) {
            if (document.getElementById('detailModal').classList.contains('show')) updateStatusUI(status);
            const fd = new FormData();
            fd.append('action', 'update_maintenance_status');
            fd.append('id', targetId);
            fd.append('status', status);
            try {
                await postData(fd);
                Swal.fire({ icon: 'success', title: 'อัพเดทสถานะเรียบร้อย', toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000 });
                fetchLogs();
            } catch(e) { Swal.fire('Error', 'บันทึกไม่สำเร็จ', 'error'); }
        }
    }

    function openQuickStatus(id, current) {
        selectedLogId = id;
        const statusMap = { 'pending':'รออนุมัติ', 'comparing':'เทียบราคา', 'approved':'อนุมัติซ่อม', 'completed':'เสร็จสิ้น', 'rejected':'ไม่อนุมัติ' };
        Swal.fire({
            title: 'เลือกสถานะ',
            input: 'select',
            inputOptions: statusMap,
            inputValue: current || 'pending',
            showCancelButton: true,
            confirmButtonColor: 'var(--primary-color)',
            confirmButtonText: 'บันทึก'
        }).then((result) => {
            if (result.isConfirmed) setStatus(result.value, id);
        });
    }

    async function handleUpdateLog(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'update_log');
        fd.append('id', document.getElementById('eId').value);
        fd.append('repair_date', document.getElementById('eDate').value);
        fd.append('service_center', document.getElementById('eCenter').value);
        fd.append('description', document.getElementById('eDesc').value);
        fd.append('cost', document.getElementById('eCost').value);
        
        let pickup = document.getElementById('ePickup').value;
        if(pickup) fd.append('pickup_date', pickup.replace('T', ' ')); 

        const receiptFile = document.getElementById('eReceipt').files[0];
        if(receiptFile) fd.append('receipt_image', receiptFile);

        try {
            await postData(fd);
            closeModal('editModal');
            fetchLogs();
            Swal.fire({ icon: 'success', title: 'บันทึกข้อมูลเรียบร้อย', toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000 });
        } catch(err) { Swal.fire('บันทึกไม่สำเร็จ', err.message, 'error'); }
    }

    function openDetail(log) {
        selectedLogId = log.id;
        document.getElementById('dRepairDate').innerText = log.repair_date ? new Date(log.repair_date).toLocaleDateString('th-TH') : '-';
        document.getElementById('dVehicle').innerText = (log.fleet_number ? '#'+log.fleet_number+' ' : '') + (log.plate_number || '-');
        document.getElementById('dCenter').innerText = log.service_center || '-';
        document.getElementById('dCost').innerText = '฿' + Number(log.cost).toLocaleString();
        document.getElementById('dPickupDate').innerText = log.pickup_date ? new Date(log.pickup_date).toLocaleDateString('th-TH') : '-';
        
        const ul = document.getElementById('dDescList');
        ul.innerHTML = '';
        (log.description || '').split('\n').forEach(i => { if(i.trim()) ul.innerHTML += `<li>${i}</li>`; });

        const imgDiv = document.getElementById('dImages');
        imgDiv.innerHTML = '';
        
        let imgArr = [];
        if (Array.isArray(log.images)) { imgArr = log.images; } 
        else if (typeof log.images === 'string') { try { imgArr = JSON.parse(log.images); } catch(e){ imgArr = [log.images]; } }
        imgArr = imgArr.map(item => cleanFileName(item)).filter(item => item !== "");

        if(imgArr.length === 0) imgDiv.innerHTML = '<span style="color:#ccc">ไม่มีรูปภาพ</span>';
        imgArr.forEach(src => {
            const path = getRepairPath(src);
            imgDiv.innerHTML += `<img src="${path}" class="thumb-img" style="width:60px; height:60px;" onclick="openLightbox('${path}')">`;
        });

        const recBtn = document.getElementById('receiptSection');
        const cleanReceipt = cleanFileName(log.receipt_image);
        if(cleanReceipt) {
            recBtn.style.display = 'block';
            document.getElementById('btnViewReceipt').onclick = () => openLightbox(getTaxPath(cleanReceipt));
        } else { recBtn.style.display = 'none'; }

        updateStatusUI(log.status || 'pending');
        openModal('detailModal');
    }

    function openEdit(e, id) {
        if(e) e.stopPropagation();
        const log = logs.find(l => l.id == id);
        document.getElementById('eId').value = id;
        document.getElementById('eDate').value = log.repair_date;
        document.getElementById('eCenter').value = log.service_center;
        document.getElementById('eDesc').value = log.description;
        document.getElementById('eCost').value = log.cost;
        if(log.pickup_date) {
            const dt = new Date(log.pickup_date);
            dt.setMinutes(dt.getMinutes() - dt.getTimezoneOffset());
            document.getElementById('ePickup').value = dt.toISOString().slice(0,16);
        } else document.getElementById('ePickup').value = '';
        document.getElementById('eReceipt').value = ''; 
        openModal('editModal');
    }

    function updateStatusUI(status) {
        const steps = ['pending', 'comparing', 'approved', 'completed'];
        let active = false;
        steps.forEach(s => {
            const el = document.getElementById('step-'+s);
            el.classList.remove('active', 'completed');
            if(s === status) { el.classList.add('active'); active=true; }
            else if(!active) el.classList.add('completed');
        });
    }

    async function postData(fd) {
        const res = await fetch(API_URL, { method:'POST', body:fd });
        const data = await res.json();
        if(!data.success) throw new Error(data.message);
    }

    function filterLogs() {
        const t = document.getElementById('searchInput').value.toLowerCase();
        const d = document.getElementById('dateInput').value;
        const f = logs.filter(l => {
            const mD = !d || l.repair_date === d;
            const mT = !t || (l.plate_number||'').toLowerCase().includes(t) || (l.service_center||'').toLowerCase().includes(t);
            return mD && mT;
        });
        renderTable(f);
    }

    function resetFilters(){ document.getElementById('searchInput').value=''; document.getElementById('dateInput').value=''; filterLogs(); }
    function openModal(id){ document.getElementById(id).classList.add('show'); }
    function closeModal(id){ document.getElementById(id).classList.remove('show'); }
    window.onclick = e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }

    // 🟢 1. ฟังก์ชันคำนวณสถิติราย วัน/เดือน/ปี (เรียกใช้เมื่อโหลดข้อมูลเสร็จ)
    function updateDashboardStats(allData) {
        const now = new Date();
        const todayStr = now.toISOString().split('T')[0];
        const currentMonth = (now.getMonth() + 1).toString().padStart(2, '0');
        const currentYear = now.getFullYear().toString();

        let todayTotal = 0;
        let monthTotal = 0;
        let yearTotal = 0;
        let allTotal = 0;

        allData.forEach(log => {
            const cost = parseFloat(log.cost) || 0;
            const logDate = log.repair_date || ""; // YYYY-MM-DD
            const [y, m, d] = logDate.split('-');

            allTotal += cost;
            if (logDate === todayStr) todayTotal += cost;
            if (y === currentYear) {
                yearTotal += cost;
                if (m === currentMonth) monthTotal += cost;
            }
        });

        document.getElementById('costToday').innerText = '฿' + todayTotal.toLocaleString();
        document.getElementById('costMonth').innerText = '฿' + monthTotal.toLocaleString();
        document.getElementById('costYear').innerText = '฿' + yearTotal.toLocaleString();
        document.getElementById('totalCostDisplay').innerText = '฿' + allTotal.toLocaleString();
    }

    // 🟢 2. ปุ่มลัด กรองข้อมูลตามการ์ดที่กด (เหมือนหน้า Dashboard)
    function quickFilter(type) {
        const now = new Date();
        const todayStr = now.toISOString().split('T')[0];
        const currentMonth = (now.getMonth() + 1).toString().padStart(2, '0');
        const currentYear = now.getFullYear().toString();

        // ลบคลาส active จากปุ่มทั้งหมด
        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
        
        const filtered = logs.filter(l => {
            const logDate = l.repair_date || "";
            const [y, m, d] = logDate.split('-');
            
            if (type === 'today') {
                document.getElementById('btnToday').classList.add('active');
                return logDate === todayStr;
            }
            if (type === 'month') {
                document.getElementById('btnMonth').classList.add('active');
                return y === currentYear && m === currentMonth;
            }
            if (type === 'year') {
                document.getElementById('btnYear').classList.add('active');
                return y === currentYear;
            }
            return true;
        });

        renderTable(filtered, true); // true = กำลังกรอง ไม่ต้องแก้ Stats บน
    }

    function renderTable(data, isFiltering = false) {
    const tbody = document.getElementById('logsTableBody');
    tbody.innerHTML = '';
    let filterSum = 0;

    if (!isFiltering) updateDashboardStats(data);

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:50px; color:var(--text-sub);">ไม่พบข้อมูลในช่วงที่เลือก</td></tr>';
        document.getElementById('totalFiltered').innerText = '฿0';
        return;
    }

    data.forEach(log => {
        const cost = parseFloat(log.cost) || 0;
        filterSum += cost;

        const tr = document.createElement('tr');
        tr.onclick = () => openDetail(log);
        tr.innerHTML = `
            <td data-label="วันที่"><b>${log.repair_date ? new Date(log.repair_date).toLocaleDateString('th-TH') : '-'}</b></td>
            <td data-label="รถ"><span class="plate-badge">${log.fleet_number ? '#'+log.fleet_number : ''} ${log.plate_number || '-'}</span></td>
            <td data-label="สถานที่ซ่อม">${log.service_center || '-'}</td>
            <td data-label="รายการหลัก"><div style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${log.description || '-'}</div></td>
            <td data-label="ค่าใช้จ่าย" style="text-align:right; font-weight:700; color:var(--primary-color);">฿${cost.toLocaleString()}</td>
            <td data-label="สถานะ" style="text-align:center;">${getStatusBadge(log)}</td>
            <td data-label="รูปภาพ">${getThumbImg(log)}</td>
            `;
        tbody.appendChild(tr);
    });

    document.getElementById('totalFiltered').innerText = '฿' + filterSum.toLocaleString();
}
    // ฟังก์ชันเสริมสำหรับ Status
    function getStatusBadge(log) {
        let badgeClass = 'badge-pending', badgeText = 'รอดำเนินการ', icon = 'clock';
        if(log.status === 'comparing'){ badgeClass = 'badge-comparing'; badgeText = 'เทียบราคา'; icon = 'balance-scale'; }
        else if(log.status === 'approved'){ badgeClass = 'badge-approved'; badgeText = 'กำลังซ่อม'; icon = 'tools'; }
        else if(log.status === 'completed'){ badgeClass = 'badge-completed'; badgeText = 'เสร็จสิ้น'; icon = 'check-circle'; }
        return `<span class="badge ${badgeClass}"><i class="fas fa-${icon}"></i> ${badgeText}</span>`;
    }

    // 🟢 ฟังก์ชันดึงรูปภาพมาแสดงในตาราง (Thumbnail)
    function getThumbImg(log) {
        let imgArr = [];
        
        // 1. ตรวจสอบและแปลงข้อมูลรูปภาพ (รองรับทั้ง Array และ JSON string)
        if (Array.isArray(log.images)) { 
            imgArr = log.images; 
        } else if (typeof log.images === 'string' && log.images !== "") { 
            try { 
                imgArr = JSON.parse(log.images); 
            } catch(e) { 
                // หากไม่ใช่ JSON แต่เป็นชื่อไฟล์เดี่ยวๆ ให้ใส่ใน Array
                imgArr = [log.images]; 
            } 
        }

        // 2. กรองเอาค่าว่างหรือ null ออก และล้างชื่อไฟล์ให้สะอาด
        imgArr = imgArr.map(item => cleanFileName(item)).filter(item => item !== "" && item !== null);

        // 3. แสดงผลรูปภาพรูปแรก
        if (imgArr.length > 0) {
            const firstImgPath = getRepairPath(imgArr[0]);
            return `
                <div style="position: relative; width: 45px; height: 45px;">
                    <img src="${firstImgPath}" 
                         class="thumb-img" 
                         onclick="event.stopPropagation(); openLightbox('${firstImgPath}')" 
                         style="width:100%; height:100%; border-radius:8px; object-fit:cover; border:1px solid var(--border-color); background:#f1f5f9;"
                         onerror="this.src='https://via.placeholder.com/45?text=Error'">
                    ${imgArr.length > 1 ? `<span style="position:absolute; bottom:-2px; right:-2px; background:var(--primary-color); color:white; font-size:9px; padding:1px 4px; border-radius:4px; border:1px solid white;">+${imgArr.length - 1}</span>` : ''}
                </div>`;
        }
        
        return '<span style="color:var(--text-sub); font-size:11px;">ไม่มีรูป</span>';
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('dateInput').value = '';
        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
        renderTable(logs);
    }
    let currentFilterMode = 'daily';

    // เพิ่มในส่วนที่เริ่มต้นโหลดหน้า
    document.addEventListener('DOMContentLoaded', () => {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('dateInput').value = today;
        document.getElementById('monthInput').value = today.substring(0, 7);
        generateYearOptions();
    });

    function setFilterMode(mode) {
        currentFilterMode = mode;
        // ปรับแต่งสีปุ่ม
        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.style.background = 'transparent';
            btn.style.color = 'var(--text-sub)';
            btn.classList.remove('active');
        });
        const activeBtn = document.getElementById(`btn-${mode}`);
        activeBtn.classList.add('active');
        activeBtn.style.background = 'var(--primary-color)';
        activeBtn.style.color = 'white';
        
        // สลับ Input
        document.getElementById('dateInput').style.display = (mode === 'daily') ? 'block' : 'none';
        document.getElementById('monthInput').style.display = (mode === 'monthly') ? 'block' : 'none';
        document.getElementById('yearInput').style.display = (mode === 'yearly') ? 'block' : 'none';
        
        filterLogs(); // กรองข้อมูลทันที
    }

    function generateYearOptions() {
        const sel = document.getElementById('yearInput');
        const cy = new Date().getFullYear();
        sel.innerHTML = '';
        for(let i=cy; i>=cy-5; i--) {
            sel.innerHTML += `<option value="${i}">${i+543}</option>`;
        }
        sel.value = cy;
    }

    // 🟢 2. ปรับปรุงฟังก์ชันกรองข้อมูลให้รองรับการเลือกเวลาแบบ Dashboard
    function filterLogs() {
        const text = document.getElementById('searchInput').value.toLowerCase();
        
        const filtered = logs.filter(l => {
            const logDate = l.repair_date || ""; // YYYY-MM-DD
            let matchTime = false;

            if (currentFilterMode === 'daily') {
                matchTime = logDate === document.getElementById('dateInput').value;
            } else if (currentFilterMode === 'monthly') {
                matchTime = logDate.substring(0, 7) === document.getElementById('monthInput').value;
            } else if (currentFilterMode === 'yearly') {
                matchTime = logDate.substring(0, 4) === document.getElementById('yearInput').value;
            }

            const matchText = !text || 
                (l.plate_number||'').toLowerCase().includes(text) || 
                (l.service_center||'').toLowerCase().includes(text) || 
                (l.description||'').toLowerCase().includes(text);

            return matchTime && matchText;
        });
        
        renderTable(filtered, true); // สั่ง Render พร้อมระบุว่า "กำลังกรองอยู่"
    }

    // แก้ไขฟังก์ชันคำนวณสถิติรายจ่าย
    function updateDashboardStats(allData) {
        let allTotal = 0;
        allData.forEach(log => {
            allTotal += parseFloat(log.cost) || 0;
        });
        document.getElementById('totalCostDisplay').innerText = '฿' + allTotal.toLocaleString();
    }

    // ฟังก์ชันล้างค่า (Reset)
    function resetFilters(){ 
        document.getElementById('searchInput').value=''; 
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('dateInput').value = today;
        document.getElementById('monthInput').value = today.substring(0, 7);
        setFilterMode('daily'); 
    }

</script>
</body>
</html>