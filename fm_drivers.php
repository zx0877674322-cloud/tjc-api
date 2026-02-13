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
<meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' 'unsafe-eval' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; img-src 'self' data: https:;">
<title>รายชื่อคนขับรถ</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php include 'Logowab.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { --primary: #3b82f6; --success: #16a34a; --danger: #ef4444; --warning: #f59e0b; --bg-body: #f1f5f9; --bg-card: #ffffff; --text-main: #1e293b; --text-sub: #64748b; --border: #e2e8f0; }
body.dark-mode { --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f1f5f9; --text-sub: #cbd5e1; --border: #334155; }

/* Profile & Other Styles */
#profileView { display: none; }
.profile-header { background: var(--bg-card); padding: 25px; border-radius: 12px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 20px; border: 1px solid var(--border); }
.profile-info { flex: 1; display: flex; gap: 20px; align-items: center; min-width: 300px; }
@media(max-width:480px) { .profile-info { flex-direction: column; text-align: center; } }
.big-avatar { width: 100px; height: 100px; border-radius: 50%; background: #e2e8f0; overflow: hidden; flex-shrink: 0; }
.big-avatar img { width: 100%; height: 100%; object-fit: cover; }

.profile-stats { display: flex; gap: 10px; flex-wrap: wrap; width: 100%; margin-top: 15px; }
.stat-box { flex: 1; background: var(--bg-body); padding: 15px; border-radius: 10px; min-width: 100px; text-align: center; border: 1px solid var(--border); }
.stat-label { font-size: 12px; color: var(--text-sub); margin-bottom: 5px; }
.stat-value { font-size: 28px; font-weight: 700; margin: 0; }
.text-green { color: var(--success); } 
.text-red { color: var(--danger); }

/* Buttons */
.btn { padding: 8px 16px; border-radius: 8px; cursor: pointer; border: none; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; font-size: 18px; white-space: nowrap; }
.btn-primary { background: var(--primary); color: white; } 
.btn-primary:hover { background: #2563eb; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
.btn-danger { background: var(--danger); color: white; }
.btn-icon { padding: 8px; border-radius: 6px; background: transparent; border: 1px solid var(--border); color: var(--text-sub); cursor: pointer; }

/* Tabs */
.tabs-container { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
.tabs-container::-webkit-scrollbar { display: none; }
.tab-btn { padding: 8px 16px; border-radius: 20px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-sub); cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
.tab-badge { background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 10px; font-size: 16px; }

/* Header */
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.header-content h1 { margin: 0; font-size: 28px; color: var(--primary); }
.header-subtitle { margin: 0; color: var(--text-sub); font-size: 18px; }

/* Mobile Menu Button */
.mobile-menu-btn { display: none; background: none; border: none; font-size: 24px; color: var(--text-main); cursor: pointer; margin-right: 15px; }
@media(max-width: 768px) { 
    .mobile-menu-btn { display: block; } 
    .header-content { display: flex; align-items: center; }
}

/* ✅ Grid System (ปรับให้สวยทุกจอ) */
.drivers-grid { 
    display: grid; 
    gap: 20px; 
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    padding-bottom: 40px;
}

/* Driver Card */
.driver-card { background: var(--bg-card); border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: relative; cursor: pointer; border: 1px solid var(--border); transition: 0.2s; display: flex; flex-direction: column; align-items: center; text-align: center; }
.driver-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--primary); }
.driver-card:active { transform: scale(0.98); }

.status-indicator { position: absolute; top: 12px; left: 12px; font-size: 16px; padding: 4px 10px; border-radius: 20px; font-weight: 600; }
.card-actions { position: absolute; top: 12px; right: 12px; display: flex; gap: 6px; }

.driver-avatar { width: 80px; height: 80px; border-radius: 50%; background: #e2e8f0; margin: 25px auto 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 3px solid var(--bg-body); }
.driver-avatar img { width: 100%; height: 100%; object-fit: cover; }

.driver-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 16px; margin-bottom: 10px; font-weight: 500; }
.badge-company { background: #eff6ff; color: var(--primary); }
.badge-partner { background: #fff7ed; color: #d97706; border: 1px solid #fed7aa; }

.driver-name { font-size: 18px; font-weight: 600; margin: 5px 0; color: var(--text-main); }
.driver-phone { font-size: 16px; color: var(--text-sub); display: flex; align-items: center; justify-content: center; gap: 5px; }

/* Tables */
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; border: 1px solid var(--border); }
.history-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.history-table th { background: var(--bg-body); text-align: left; padding: 12px; color: var(--text-sub); font-weight: 600; }
.history-table td { padding: 12px; border-top: 1px solid var(--border); color: var(--text-main); background: var(--bg-card); }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: none; justify-content: center; align-items: center; padding: 10px; backdrop-filter: blur(2px); }
.modal-overlay.show { display: flex; }
.modal-content { background: var(--bg-card); width: 100%; max-width: 500px; border-radius: 16px; padding: 25px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.form-input, .form-select { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-body); font-size: 16px; }

/* ✅ Layout แบบ App (เต็มจอ) */
.page-container { 
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 250px;
    padding: 25px; 
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    transition: 0.3s;
}

/* 📱 Mobile Layout */
@media(max-width: 768px) { 
    .page-container { 
        left: 0;
        width: 100%; 
        padding: 15px;
        padding-top: 70px;
    } 
    .sidebar { transform: translateX(-100%); transition: 0.3s; z-index: 1000; }
    .sidebar.show { transform: translateX(0); }
}
</style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div id="sidebarOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:900;" onclick="toggleSidebar()"></div>

    <div class="page-container" id="mainApp">
        
        <div id="listView">
            <div class="page-header">
                <div class="header-content">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <div>
                        <h1>👥 รายชื่อคนขับรถ</h1>
                        <p class="header-subtitle">จัดการข้อมูลคนขับและพนักงานช่วยเหลือ</p>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="openDriverModal()"><i class="fas fa-plus"></i> เพิ่มคนขับ</button>
            </div>

            <div class="tabs-container">
                <button class="tab-btn active" onclick="filterDrivers('all', this)"><span class="tab-badge" id="countAll">0</span> ทั้งหมด</button>
                <button class="tab-btn" onclick="filterDrivers('company', this)"><i class="fas fa-building"></i> พนักงานประจำ</button>
                <button class="tab-btn" onclick="filterDrivers('partner', this)"><i class="fas fa-handshake"></i> รถร่วม</button>
            </div>

            <div id="loading" style="text-align:center; padding:40px; color:var(--text-sub);"><i class="fas fa-spinner fa-spin fa-2x"></i><br>กำลังโหลด...</div>
            <div class="drivers-grid" id="driversGrid"></div>
        </div>

        <div id="profileView">
            <div class="page-header">
                <button class="btn btn-outline" onclick="closeProfile()"><i class="fas fa-arrow-left"></i> กลับ</button>
                <h2 style="margin:0; font-size:18px;">รายละเอียด</h2>
            </div>
            
            <div class="profile-header">
                <div class="profile-info">
                    <div class="big-avatar" id="pAvatar"></div>
                    <div>
                        <h2 id="pName" style="margin:0 0 5px 0;"></h2>
                        <div id="pBadge"></div>
                        <div style="margin-top:8px; color:var(--text-sub); font-size:14px;"><i class="fas fa-phone-alt"></i> <span id="pPhone"></span></div>
                        <button class="btn btn-outline" style="margin-top:10px; font-size:12px;" onclick="openLeaveModal()"><i class="fas fa-calendar-times"></i> บันทึกการลา</button>
                    </div>
                </div>
                <div class="profile-stats">
                    <div class="stat-box"><div class="stat-label">งานเสร็จ</div><h3 class="stat-value" id="sCompleted">-</h3></div>
                    <div class="stat-box"><div class="stat-label">รายรับ</div><h3 class="stat-value text-green" id="sIncome">-</h3></div>
                    <div class="stat-box"><div class="stat-label">รายจ่าย</div><h3 class="stat-value text-red" id="sExpense">-</h3></div>
                    <div class="stat-box"><div class="stat-label">กำไร</div><h3 class="stat-value" id="sProfit">-</h3></div>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-bottom:15px; border-bottom:1px solid var(--border);">
                <button class="btn" style="background:none; color:var(--primary); border-bottom:2px solid var(--primary); border-radius:0;" onclick="switchProfileTab('jobs', this)">ประวัติงาน</button>
                <button class="btn" style="background:none; color:var(--text-sub); border-radius:0;" onclick="switchProfileTab('leaves', this)">ประวัติการลา</button>
            </div>

            <div id="tabJobs">
                <div class="table-responsive">
                    <table class="history-table">
                        <thead><tr><th>วันที่</th><th>ตำแหน่ง</th><th>รายรับ</th><th>รายจ่าย</th><th>กำไร</th><th>สถานะ</th></tr></thead>
                        <tbody id="jobHistoryBody"></tbody>
                    </table>
                </div>
            </div>

            <div id="tabLeaves" style="display:none;">
                <div class="table-responsive">
                    <table class="history-table">
                        <thead><tr><th>วันที่ลา</th><th>ประเภท</th><th>เหตุผล</th><th>จัดการ</th></tr></thead>
                        <tbody id="leaveHistoryBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <div class="modal-overlay" id="driverModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="driverModalTitle">เพิ่มคนขับ</h3>
                <button class="btn-icon" onclick="closeModal('driverModal')"><i class="fas fa-times"></i></button>
            </div>
            <form id="driverForm" onsubmit="handleSaveDriver(event)">
                <input type="hidden" name="id" id="dId">
                <input type="hidden" name="action" id="dAction" value="add_driver">
                
                <div class="form-group">
                    <label class="form-label">ประเภท</label>
                    <div style="display:flex; gap:10px;">
                        <label class="form-input" style="flex:1; text-align:center;">
                            <input type="radio" name="category" value="company" checked> 🏢 พนักงาน
                        </label>
                        <label class="form-input" style="flex:1; text-align:center;">
                            <input type="radio" name="category" value="partner"> 🤝 รถร่วม
                        </label>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">ชื่อ-นามสกุล *</label><input type="text" name="name" id="dName" class="form-input" required></div>
                <div class="form-group"><label class="form-label">เบอร์โทร</label><input type="text" name="phone" id="dPhone" class="form-input"></div>
                <div class="form-group"><label class="form-label">รูปโปรไฟล์ (URL)</label><input type="text" name="photo_url" id="dPhoto" class="form-input" placeholder="https://..."></div>
                
                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;">บันทึกข้อมูล</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="leaveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>บันทึกการลา</h3>
                <button class="btn-icon" onclick="closeModal('leaveModal')"><i class="fas fa-times"></i></button>
            </div>
            <form id="leaveForm" onsubmit="handleSaveLeave(event)">
                <input type="hidden" name="action" value="add_leave">
                <input type="hidden" name="driver_id" id="lDriverId">
                
                <div class="form-group">
                    <label class="form-label">ประเภทการลา</label>
                    <select name="leave_type" class="form-select">
                        <option value="sick">🤒 ลาป่วย</option>
                        <option value="vacation">🏖️ ลาพักร้อน</option>
                        <option value="personal">📝 ลากิจ</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1"><label class="form-label">เริ่มวันที่</label><input type="date" name="start_date" class="form-input" required></div>
                    <div class="form-group" style="flex:1"><label class="form-label">ถึงวันที่</label><input type="date" name="end_date" class="form-input" required></div>
                </div>
                <div class="form-group"><label class="form-label">เหตุผล</label><input type="text" name="reason" class="form-input" placeholder="ระบุสาเหตุ..."></div>
                
                <button type="submit" class="btn btn-danger" style="width:100%; padding:12px;">ยืนยันการลา</button>
            </form>
        </div>
    </div>

<script>
    let state = { drivers: [], leaves: [], jobs: [], activeTab: 'all', selectedDriverId: null };
    const API_URL = 'api_fm.php';

    document.addEventListener('DOMContentLoaded', () => {
        const theme = localStorage.getItem('tjc_theme');
        if(theme === 'dark') document.body.classList.add('dark-mode');
        fetchAllData();
    });

    // 📱 ฟังก์ชันเปิด/ปิด Sidebar ในมือถือ
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if(sidebar) {
            sidebar.classList.toggle('show');
            if(sidebar.classList.contains('show')) overlay.style.display = 'block';
            else overlay.style.display = 'none';
        }
    }

    async function fetchAllData() {
        try {
            const [resDrivers, resLeaves] = await Promise.all([
                fetch(`${API_URL}?action=fetch_drivers`),
                fetch(`${API_URL}?action=fetch_leaves`)
            ]);
            state.drivers = await resDrivers.json();
            state.leaves = await resLeaves.json();
            
            if (!Array.isArray(state.drivers)) state.drivers = [];
            if (!Array.isArray(state.leaves)) state.leaves = [];

            renderDrivers();
            document.getElementById('loading').style.display = 'none';
            document.getElementById('countAll').innerText = state.drivers.length;
        } catch (e) {
            console.error(e);
            alert('โหลดข้อมูลไม่สำเร็จ (ตรวจสอบ API)');
        }
    }

    function getDriverStatus(driverId) {
        const today = new Date().toISOString().split('T')[0];
        const activeLeave = state.leaves.find(l => l.driver_id == driverId && l.start_date <= today && l.end_date >= today);
        if (activeLeave) {
            if (activeLeave.leave_type === 'sick') return { label: '⛔ ลาป่วย', color: '#ef4444', bg: '#fee2e2' };
            if (activeLeave.leave_type === 'vacation') return { label: '🏖️ ลาพักร้อน', color: '#3b82f6', bg: '#dbeafe' };
            return { label: '📝 ลากิจ', color: '#f59e0b', bg: '#fef3c7' };
        }
        return { label: '✅ พร้อมทำงาน', color: '#16a34a', bg: '#dcfce7' };
    }

    function renderDrivers() {
        const grid = document.getElementById('driversGrid');
        grid.innerHTML = '';
        
        // 1. กรองข้อมูลตาม Tab ที่เลือก
        let filtered = state.drivers.filter(d => state.activeTab === 'all' || (d.category || 'company') === state.activeTab);

        // 🟢 2. จัดเรียง: ให้ 'company' มาก่อน 'partner'
        filtered.sort((a, b) => {
            const catA = a.category || 'company';
            const catB = b.category || 'company';
            
            if (catA === 'company' && catB === 'partner') return -1; // a มาก่อน b
            if (catA === 'partner' && catB === 'company') return 1;  // b มาก่อน a
            return 0; // ถ้าประเภทเดียวกัน ไม่ต้องสลับ
        });

        if (filtered.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#999;padding:20px;">ไม่พบรายชื่อ</div>';
            return;
        }

        // 3. วนลูปแสดงผล (Render)
        filtered.forEach(d => {
            const st = getDriverStatus(d.id);
            const borderStyle = st.label !== '✅ พร้อมทำงาน' ? `border: 2px solid ${st.color};` : '';
            const avatar = d.photo_url 
                ? `<img src="${d.photo_url}" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">` 
                : `<i class="fas fa-user" style="font-size:30px; color:#cbd5e1;"></i>`;
            
            const badgeClass = d.category === 'partner' ? 'badge-partner' : 'badge-company';
            const badgeText = d.category === 'partner' ? '<i class="fas fa-handshake"></i> รถร่วม' : '<i class="fas fa-building"></i> พนักงาน';

            const html = `
                <div class="driver-card" style="${borderStyle}" onclick="viewProfile(${d.id})">
                    <div class="status-indicator" style="background:${st.bg}; color:${st.color}">${st.label}</div>
                    <div class="card-actions">
                        <button class="btn-icon" onclick="event.stopPropagation(); openEditModal(${d.id})"><i class="fas fa-pen"></i></button>
                        <button class="btn-icon" onclick="event.stopPropagation(); deleteDriver(${d.id})"><i class="fas fa-trash"></i></button>
                    </div>
                    <div class="driver-avatar">${avatar}</div>
                    <div class="driver-badge ${badgeClass}">${badgeText}</div>
                    <div class="driver-name">${d.name}</div>
                    <div class="driver-phone"><i class="fas fa-phone-alt"></i> ${d.phone || '-'}</div>
                </div>
            `;
            grid.insertAdjacentHTML('beforeend', html);
        });
    }

    function filterDrivers(tab, btn) {
        state.activeTab = tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderDrivers();
    }

    async function viewProfile(id) {
        state.selectedDriverId = id;
        const driver = state.drivers.find(d => d.id == id);
        if (!driver) return;

        document.getElementById('listView').style.display = 'none';
        document.getElementById('profileView').style.display = 'block';
        
        document.getElementById('pName').innerText = driver.name;
        document.getElementById('pPhone').innerText = driver.phone || 'ไม่ระบุเบอร์';
        const img = driver.photo_url ? `<img src="${driver.photo_url}" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">` : `<div style="display:flex;align-items:center;justify-content:center;height:100%;"><i class="fas fa-user" style="font-size:40px;color:#ccc;"></i></div>`;
        document.getElementById('pAvatar').innerHTML = img;
        
        const badgeClass = driver.category === 'partner' ? 'badge-partner' : 'badge-company';
        const badgeText = driver.category === 'partner' ? '🤝 รถร่วม' : '🏢 พนักงานประจำ';
        document.getElementById('pBadge').innerHTML = `<span class="driver-badge ${badgeClass}">${badgeText}</span>`;

        await fetchDriverJobs(id);
        renderLeavesHistory(id);
    }

    async function fetchDriverJobs(id) {
        try {
            const res = await fetch(`${API_URL}?action=fetch_schedule`);
            const data = await res.json();
            const allJobs = data.jobs || [];
            const myJobs = allJobs.filter(j => j.driver_id == id || j.assistant_id == id);
            
            const completed = myJobs.filter(j => j.status === 'completed');
            let income = 0, expense = 0;
            completed.forEach(j => { income += parseFloat(j.actual_price || 0); expense += parseFloat(j.cost || 0); });

            document.getElementById('sCompleted').innerText = `${completed.length}/${myJobs.length}`;
            document.getElementById('sIncome').innerText = '฿' + income.toLocaleString();
            document.getElementById('sExpense').innerText = '฿' + expense.toLocaleString();
            const profit = income - expense;
            const pEl = document.getElementById('sProfit');
            pEl.innerText = '฿' + profit.toLocaleString();
            pEl.className = `stat-value ${profit >= 0 ? 'text-green' : 'text-red'}`;

            const tbody = document.getElementById('jobHistoryBody');
            tbody.innerHTML = '';
            myJobs.sort((a,b) => new Date(b.start_time) - new Date(a.start_time));
            
            if (myJobs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">ไม่มีประวัติงาน</td></tr>';
            } else {
                myJobs.forEach(j => {
                    const statusMap = { pending: '⏳ รอ', in_progress: '🔄 ส่ง', completed: '✅ เสร็จ', failed: '❌ พลาด' };
                    const role = j.driver_id == id ? 'หลัก' : 'ผู้ช่วย';
                    const date = new Date(j.start_time).toLocaleDateString('th-TH', {day:'2-digit', month:'short', year:'2-digit'});
                    const row = `<tr><td>${date}<br><small style="color:#999">${role}</small></td><td>${j.destination}</td><td class="text-green">${j.price_note ? '+'+Number(j.price_note).toLocaleString() : '-'}</td><td class="text-red">${j.cost ? '-'+Number(j.cost).toLocaleString() : '-'}</td><td>${(Number(j.actual_price||0) - Number(j.cost||0)).toLocaleString()}</td><td><span class="status-badge status-${j.status}">${statusMap[j.status] || j.status}</span></td></tr>`;
                    tbody.insertAdjacentHTML('beforeend', row);
                });
            }
        } catch (e) { console.error(e); }
    }

    function renderLeavesHistory(id) {
        const tbody = document.getElementById('leaveHistoryBody');
        tbody.innerHTML = '';
        const myLeaves = state.leaves.filter(l => l.driver_id == id);
        if (myLeaves.length === 0) { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;">ไม่พบประวัติการลา</td></tr>'; return; }
        myLeaves.forEach(l => {
            const mapType = { sick: '🤒 ลาป่วย', vacation: '🏖️ พักร้อน', personal: '📝 ลากิจ' };
            const s = new Date(l.start_date).toLocaleDateString('th-TH');
            const e = new Date(l.end_date).toLocaleDateString('th-TH');
            const row = `<tr><td>${s} - ${e}</td><td>${mapType[l.leave_type] || l.leave_type}</td><td>${l.reason || '-'}</td><td><button class="btn-icon" style="color:red" onclick="deleteLeave(${l.id})"><i class="fas fa-trash"></i></button></td></tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        });
    }

    function closeProfile() {
        document.getElementById('profileView').style.display = 'none';
        document.getElementById('listView').style.display = 'block';
        state.selectedDriverId = null;
    }

    function switchProfileTab(tab, btn) {
        document.getElementById('tabJobs').style.display = tab === 'jobs' ? 'block' : 'none';
        document.getElementById('tabLeaves').style.display = tab === 'leaves' ? 'block' : 'none';
    }

    // --- CRUD ---
    function openDriverModal() {
        document.getElementById('driverForm').reset();
        document.getElementById('dId').value = '';
        document.getElementById('dAction').value = 'add_driver';
        document.getElementById('driverModalTitle').innerText = 'เพิ่มคนขับ';
        document.getElementById('driverModal').classList.add('show');
    }
    function openEditModal(id) {
        const d = state.drivers.find(x => x.id == id);
        if(!d) return;
        document.getElementById('dId').value = d.id;
        document.getElementById('dAction').value = 'update_driver';
        document.getElementById('dName').value = d.name;
        document.getElementById('dPhone').value = d.phone;
        document.getElementById('dPhoto').value = d.photo_url;
        document.getElementById('driverModalTitle').innerText = 'แก้ไขข้อมูล';
        document.getElementById('driverModal').classList.add('show');
    }
    async function handleSaveDriver(e) { e.preventDefault(); await postData(new FormData(e.target)); closeModal('driverModal'); fetchAllData(); }
    async function deleteDriver(id) { if(!confirm('ยืนยันลบ?')) return; const f = new FormData(); f.append('action', 'delete_driver'); f.append('id', id); await postData(f); fetchAllData(); }
    function openLeaveModal() { if(!state.selectedDriverId) return; document.getElementById('leaveForm').reset(); document.getElementById('lDriverId').value = state.selectedDriverId; document.getElementById('leaveModal').classList.add('show'); }
    async function handleSaveLeave(e) { e.preventDefault(); await postData(new FormData(e.target)); closeModal('leaveModal'); fetchAllData().then(() => { if(state.selectedDriverId) renderLeavesHistory(state.selectedDriverId); }); }
    async function deleteLeave(id) { if(!confirm('ลบ?')) return; const f = new FormData(); f.append('action', 'delete_leave'); f.append('id', id); await postData(f); fetchAllData().then(() => { if(state.selectedDriverId) renderLeavesHistory(state.selectedDriverId); }); }
    async function postData(formData) { try { const res = await fetch(API_URL, { method: 'POST', body: formData }); const txt = await res.text(); return txt ? JSON.parse(txt) : {}; } catch(e) { console.error(e); alert('Error'); } }
    function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    window.onclick = function(e) { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>
</body>
</html>