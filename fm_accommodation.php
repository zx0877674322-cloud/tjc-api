<?php
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการค่าที่พัก - Fleet Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'Logowab.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --radius: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 20px rgba(0,0,0,0.15);
        }

        html.dark-mode {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Prompt', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
            min-height: 100vh;
        }

        .container { max-width: 1200px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; gap: 15px; flex-wrap: wrap; }
        .page-header h1 { font-size: 32px; background: linear-gradient(135deg, #3b82f6, #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; display: flex; align-items: center; gap: 12px; font-weight: 800; }
        
        .btn { padding: 12px 24px; border-radius: var(--radius); cursor: pointer; border: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; font-size: 14px; font-family: inherit; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #0ea5e9); color: white; box-shadow: var(--shadow-md); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59,130,246,0.4); }
        .btn-info { background: linear-gradient(135deg, var(--info), #06b6d4); color: white; font-size: 12px; padding: 10px 16px; box-shadow: var(--shadow-sm); border-radius: 6px; }
        .btn-info:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(239,68,68,0.4); }

        /* === FILTER SECTION === */
        .filter-section {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }

        .filter-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-controls {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .period-toggle {
            display: flex;
            gap: 6px;
            border-radius: var(--radius);
            background: var(--bg-body);
            padding: 4px;
            border: 1px solid var(--border);
        }

        .period-btn {
            padding: 8px 16px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .period-btn:hover {
            color: var(--primary);
        }

        .period-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .input-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .date-input, .month-input, .year-select {
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            background: var(--bg-card);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 150px;
        }

        .date-input:focus, .month-input:focus, .year-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: var(--bg-card); padding: 28px; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: all 0.3s; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: -50%; right: -50%; width: 200px; height: 200px; background: rgba(59,130,246,0.05); border-radius: 50%; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
        .stat-card > * { position: relative; z-index: 1; }
        .stat-card-label { color: var(--text-secondary); font-size: 11px; text-transform: uppercase; font-weight: 700; margin-bottom: 10px; letter-spacing: 1px; display: flex; align-items: center; gap: 6px; }
        .stat-card-value { font-size: 36px; font-weight: 800; background: linear-gradient(135deg, #3b82f6, #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .stat-card-subtext { font-size: 12px; color: var(--text-secondary); margin-top: 10px; }
        .stat-card:nth-child(2) .stat-card-value { background: linear-gradient(135deg, #f59e0b, #d97706); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .stat-card:nth-child(3) .stat-card-value { background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

        .section-header { font-size: 18px; font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; color: var(--text-primary); }
        .section-header i { color: var(--primary); font-size: 22px; }

        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        
        .driver-card { background: var(--bg-card); border-radius: var(--radius-lg); border: 2px solid var(--border); overflow: hidden; box-shadow: var(--shadow-sm); transition: all 0.3s; position: relative; display: flex; flex-direction: column; }
        .driver-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--info)); }
        .driver-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); border-color: var(--primary); }

        .driver-card-header { padding: 20px; display: flex; align-items: center; gap: 12px; }
        .driver-avatar { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #0ea5e9); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700; flex-shrink: 0; }
        .driver-info { flex: 1; }
        .driver-name { font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: 4px; }
        .driver-id { font-size: 12px; color: var(--text-secondary); }

        .driver-card-body { padding: 0 20px 20px; flex: 1; }
        .stat-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .stat-row:last-child { border-bottom: none; }
        .stat-label { font-size: 13px; color: var(--text-secondary); font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .stat-value { font-size: 14px; font-weight: 700; color: var(--primary); }
        .stat-value.warning { color: var(--warning); }
        .stat-value.success { color: var(--success); }

        .driver-card-footer { padding: 16px 20px; background: linear-gradient(135deg, rgba(59,130,246,0.05), rgba(6,182,212,0.05)); border-top: 1px solid var(--border); display: flex; gap: 10px; }
        .driver-card-footer .btn { flex: 1; padding: 10px 12px; font-size: 13px; justify-content: center; }

        .empty-state { text-align: center; padding: 80px 20px; color: var(--text-secondary); }
        .empty-state-icon { font-size: 64px; opacity: 0.2; margin-bottom: 20px; }
        .loading { display: inline-block; animation: spin 0.8s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .alert { padding: 14px 16px; border-radius: var(--radius); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 14px; animation: slideDown 0.3s ease; font-weight: 500; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid rgba(16,185,129,0.3); }
        .alert-danger { background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 15px; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); width: 100%; max-width: 550px; padding: 32px; border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(0,0,0,0.2); animation: slideUp 0.3s ease; max-height: 90vh; overflow-y: auto; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { font-size: 22px; font-weight: 700; margin-bottom: 28px; display: flex; align-items: center; gap: 10px; }

        .form-group { margin-bottom: 22px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-control, .form-select { width: 100%; padding: 12px 14px; border: 2px solid var(--border); border-radius: var(--radius); background: var(--bg-card); color: var(--text-primary); font-family: inherit; font-size: 14px; transition: all 0.3s; }
        .form-control:focus, .form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .modal-footer { display: flex; gap: 12px; margin-top: 32px; }
        .modal-footer button { flex: 1; padding: 14px; font-size: 14px; }

        .history-item { background: var(--bg-card); border: 1px solid var(--border); padding: 20px; border-radius: var(--radius-lg); margin-bottom: 16px; border-left: 5px solid var(--primary); transition: all 0.3s; }
        .history-item:hover { box-shadow: var(--shadow-md); }
        .history-image-wrapper { margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; }
        .history-image-preview { width: 100px; height: 100px; border-radius: var(--radius); overflow: hidden; border: 2px solid var(--border); cursor: pointer; position: relative; transition: all 0.2s; }
        .history-image-preview img { width: 100%; height: 100%; object-fit: cover; }
        .history-image-preview:hover { transform: scale(1.05); border-color: var(--primary); box-shadow: 0 4px 12px rgba(59,130,246,0.2); }

        @media (max-width: 768px) {
            body { margin-left: 0; padding: 10px; padding-top: 65px; }
            .page-header { flex-direction: column; align-items: stretch; margin-bottom: 30px; }
            .page-header h1 { font-size: 22px; }
            .btn { width: 100%; justify-content: center; }
            .filter-controls { flex-direction: column; }
            .date-input, .month-input, .year-select { width: 100%; }
            .stats-grid { grid-template-columns: 1fr; }
            .stat-card { padding: 16px; }
            .stat-card-value { font-size: 28px; }
            .cards-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .modal-content { padding: 20px; }
            .modal-header { font-size: 18px; margin-bottom: 20px; }
        }

        @media (max-width: 480px) {
            .page-header h1 { font-size: 18px; }
            .stat-card-value { font-size: 24px; }
            .cards-grid { grid-template-columns: 1fr; }
            .period-btn { padding: 6px 12px; font-size: 12px; }
        }
    </style>
</head>
<body>
<!-- 
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-hotel"></i> จัดการค่าที่พัก</h1>
            <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus-circle"></i> เพิ่มรายการ</button>
        </div>

        <div id="alertContainer"></div>

        <!-- === FILTER SECTION === -->
        <div class="filter-section">
            <div class="filter-title">
                <i class="fas fa-filter"></i> เลือกช่วงเวลา
            </div>
            <div class="filter-controls">
                <div class="period-toggle">
                    <button class="period-btn active" onclick="setPeriod('daily')">
                        <i class="fas fa-calendar-day"></i> วัน
                    </button>
                    <button class="period-btn" onclick="setPeriod('monthly')">
                        <i class="fas fa-calendar-alt"></i> เดือน
                    </button>
                    <button class="period-btn" onclick="setPeriod('yearly')">
                        <i class="fas fa-calendar-check"></i> ปี
                    </button>
                </div>

                <!-- Daily Input -->
                <div class="input-group" id="daily-input">
                    <label><i class="fas fa-calendar"></i> เลือกวันที่</label>
                    <input type="date" id="dailyDate" class="date-input" onchange="fetchData()">
                </div>

                <!-- Monthly Input -->
                <div class="input-group" id="monthly-input" style="display: none;">
                    <label><i class="fas fa-calendar-alt"></i> เลือกเดือน</label>
                    <input type="month" id="monthValue" class="month-input" onchange="fetchData()">
                </div>

                <!-- Yearly Input -->
                <div class="input-group" id="yearly-input" style="display: none;">
                    <label><i class="fas fa-calendar"></i> เลือกปี</label>
                    <select id="yearValue" class="year-select" onchange="fetchData()">
                        <option value="">-- เลือกปี --</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-label"><i class="fas fa-calendar-day"></i> ช่วงเวลานี้</div>
                <div class="stat-card-value" id="statPeriod">฿0</div>
                <div class="stat-card-subtext" id="statPeriodText">ยอดเงิน</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label"><i class="fas fa-calendar-alt"></i> เดือนนี้</div>
                <div class="stat-card-value" id="statMonth">฿0</div>
                <div class="stat-card-subtext">ยอดเงินประจำเดือน</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label"><i class="fas fa-chart-bar"></i> ยอดรวม</div>
                <div class="stat-card-value" id="statTotal">฿0</div>
                <div class="stat-card-subtext">ยอดเงินทั้งหมด</div>
            </div>
        </div>

        <div class="section-header">
            <i class="fas fa-th-large"></i>
            <span>รายการค่าที่พักประจำบุคคล</span>
        </div>

        <div class="cards-grid" id="cardsContainer">
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                <i class="fas fa-spinner loading" style="font-size: 40px; opacity: 0.5;"></i>
            </div>
        </div>
    </div>

    <!-- === ADD/EDIT MODAL === -->
    <div class="modal" id="accModal" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header"><i class="fas fa-bed"></i><span>บันทึกค่าที่พัก</span></div>
            <form id="accForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user-circle"></i> พนักงาน *</label>
                    <select name="driver_id" id="driver_select" class="form-control" required></select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> วันที่เข้าพัก *</label>
                        <input type="date" name="stay_date" class="form-control" required value="<?=date('Y-m-d')?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> จังหวัด</label>
                        <select name="province" id="province_select" class="form-control"></select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-building"></i> ชื่อโรงแรม</label>
                    <input type="text" name="hotel_name" class="form-control" placeholder="เช่น โรงแรมสวรรค์">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-bed"></i> จำนวนคืน</label>
                        <input type="number" name="nights" class="form-control" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-money-bill-wave"></i> ยอดเงิน *</label>
                        <input type="number" name="amount" class="form-control" required step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-receipt"></i> รูปใบเสร็จ/บิล</label>
                    <input type="file" name="receipt_image" class="form-control" accept="image/*">
                    <small style="color: var(--text-secondary); margin-top: 5px; display: block;">JPG, PNG, WebP (ไม่บังคับ)</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="closeModal()"><i class="fas fa-times"></i> ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- -->
    <div class="modal" id="detailModal" onclick="closeDetailModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header"><i class="fas fa-history"></i> <span id="detailModalTitle">ประวัติค่าที่พัก</span></div>
            <div style="padding: 20px;">
                <div id="detailContent" style="max-height: 60vh; overflow-y: auto;"><div style="text-align: center; padding: 20px;"><i class="fas fa-spinner loading"></i></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" style="flex: 1;" onclick="closeDetailModal()"><i class="fas fa-times"></i> ปิด</button>
            </div>
        </div>
    </div>

    <script>
        let state = {
            period: 'daily',
            selectedDate: new Date().toISOString().split('T')[0],
            selectedMonth: new Date().toISOString().slice(0, 7),
            selectedYear: new Date().getFullYear().toString()
        };

        window.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('darkMode') === 'true') {
                document.documentElement.classList.add('dark-mode');
            }
            initPage();
            initDateInputs();
            fetchData();
        });

        function initDateInputs() {
            document.getElementById('dailyDate').value = state.selectedDate;
            document.getElementById('monthValue').value = state.selectedMonth;
            generateYearOptions();
        }

        function generateYearOptions() {
            const select = document.getElementById('yearValue');
            const currentYear = new Date().getFullYear();
            for (let i = currentYear; i >= currentYear - 10; i--) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i + 543;
                select.appendChild(option);
            }
            select.value = currentYear;
            state.selectedYear = currentYear.toString();
        }

        function setPeriod(period) {
            state.period = period;
            
            // Update buttons
            document.querySelectorAll('.period-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // Update inputs
            document.getElementById('daily-input').style.display = period === 'daily' ? 'flex' : 'none';
            document.getElementById('monthly-input').style.display = period === 'monthly' ? 'flex' : 'none';
            document.getElementById('yearly-input').style.display = period === 'yearly' ? 'flex' : 'none';

            fetchData();
        }

        async function initPage() {
            try {
                const res = await fetch('api_fm.php?action=fetch_acc_setup');
                const data = await res.json();
                if (data.success) {
                    document.getElementById('driver_select').innerHTML = '<option value="">เลือกพนักงาน</option>' + data.drivers.map(d => `<option value="${d.id}">${d.name}</option>`).join('');
                    document.getElementById('province_select').innerHTML = '<option value="">เลือกจังหวัด</option>' + data.provinces.map(p => `<option value="${p}">${p}</option>`).join('');
                }
            } catch (e) { console.error("Init Error", e); }
        }

        async function fetchData() {
    const container = document.getElementById('cardsContainer'); // ย้ายมาไว้ตรงนี้
    try {
        let param = '';
        let paramName = '';

        if (state.period === 'daily') {
            state.selectedDate = document.getElementById('dailyDate').value;
            param = state.selectedDate;
            paramName = 'date';
        } else if (state.period === 'monthly') {
            state.selectedMonth = document.getElementById('monthValue').value;
            param = state.selectedMonth;
            paramName = 'month';
        } else if (state.period === 'yearly') {
            state.selectedYear = document.getElementById('yearValue').value;
            param = state.selectedYear;
            paramName = 'year';
        }

        const res = await fetch(`api_fm.php?action=fetch_accommodation_stats&period=${state.period}&${paramName}=${param}`);
        const data = await res.json();
        
        if (data.success) {
            // 1. อัปเดต Stat Cards ด้านบน
            const periodTotal = data.summary.period_total || 0;
            const monthTotal = data.summary.month_total || 0;
            const grandTotal = data.summary.grand_total || 0;

            document.getElementById('statPeriod').innerText = `฿${Number(periodTotal).toLocaleString('th-TH')}`;
            document.getElementById('statPeriodText').innerText = getPeriodText(state.period, param);
            document.getElementById('statMonth').innerText = `฿${Number(monthTotal).toLocaleString('th-TH')}`;
            document.getElementById('statTotal').innerText = `฿${Number(grandTotal).toLocaleString('th-TH')}`;
            
            // 2. อัปเดตรายการรายบุคคล (Cards)
            if (!data.drivers || data.drivers.length === 0) {
                container.innerHTML = `<div style="grid-column: 1/-1;"><div class="empty-state"><div class="empty-state-icon">📭</div><p>ยังไม่มีข้อมูลค่าที่พักในช่วงเวลานี้</p></div></div>`;
            } else {
                container.innerHTML = data.drivers.map((d) => {
                    const pTotal = d.period_total || 0;
                    const tAmount = d.total_amount || 0;
                    const initial = d.name ? d.name.charAt(0).toUpperCase() : '?';

                    return `
                        <div class="driver-card">
                            <div class="driver-card-header">
                                <div class="driver-avatar">${initial}</div>
                                <div class="driver-info">
                                    <div class="driver-name">${d.name}</div>
                                    <div class="driver-id">รหัส: ${d.driver_id}</div>
                                </div>
                            </div>
                            <div class="driver-card-body">
                                <div class="stat-row">
                                    <span class="stat-label"><i class="fas fa-repeat"></i> จำนวนครั้ง</span>
                                    <span class="stat-value">${d.total_stays || 0} ครั้ง</span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><i class="fas fa-calendar-alt"></i> ช่วงเวลานี้</span>
                                    <span class="stat-value warning">฿${Number(pTotal).toLocaleString('th-TH')}</span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><i class="fas fa-money-bill-wave"></i> ยอดรวม</span>
                                    <span class="stat-value success">฿${Number(tAmount).toLocaleString('th-TH')}</span>
                                </div>
                            </div>
                            <div class="driver-card-footer">
                                <button class="btn btn-info" onclick="viewDetail(${d.driver_id}, '${d.name}')">
                                    <i class="fas fa-history"></i> ประวัติ
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }
    } catch (e) { 
        console.error("Fetch Error", e);
        container.innerHTML = `<div style="grid-column: 1/-1; text-align: center; color: var(--danger);">เกิดข้อผิดพลาดในการเชื่อมต่อข้อมูล</div>`;
    }
}

        function getPeriodText(period, param) {
            const months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", 
                           "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
            
            if (period === 'daily') {
                const date = new Date(param);
                return date.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
            } else if (period === 'monthly') {
                const [y, m] = param.split('-');
                return `เดือน${months[parseInt(m)]} ${parseInt(y) + 543}`;
            } else if (period === 'yearly') {
                return `ปี ${parseInt(param) + 543}`;
            }
            return '';
        }

       // ปรับปรุงฟังก์ชัน viewDetail ให้รับเงื่อนไขเวลา
async function viewDetail(driverId, driverName) {
    const modal = document.getElementById('detailModal');
    const content = document.getElementById('detailContent');
    document.getElementById('detailModalTitle').textContent = `ประวัติค่าที่พัก: ${driverName}`;
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin loading"></i> กำลังดึงข้อมูล...</div>';
    modal.classList.add('active');

    const period = state.period;
    let param = '';
    let paramName = '';

    if (period === 'daily') {
        param = document.getElementById('dailyDate').value;
        paramName = 'date';
    } else if (period === 'monthly') {
        param = document.getElementById('monthValue').value;
        paramName = 'month';
    } else if (period === 'yearly') {
        param = document.getElementById('yearValue').value;
        paramName = 'year';
    }

    try {
        const res = await fetch(`api_fm.php?action=fetch_driver_accommodations&driver_id=${driverId}&period=${period}&${paramName}=${param}`);
        const data = await res.json();
        
        // --- ส่วนที่ต้องเพิ่ม: นำข้อมูลมาแสดงผล ---
        if (data.success && data.accommodations.length > 0) {
            let html = '';
            data.accommodations.forEach(acc => {
                const dateStr = new Date(acc.stay_date).toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
                const hasImage = acc.receipt_image && acc.receipt_image.trim() !== "";
                const imagePath = hasImage ? `uploads/room_receipts/${acc.receipt_image}` : null;

                html += `
                    <div class="history-item">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <div style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">วันที่เข้าพัก</div>
                                <div style="font-size: 16px; font-weight: 700;">${dateStr}</div>
                                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 5px;">
                                    <i class="fas fa-hotel"></i> ${acc.hotel_name || 'ไม่ระบุชื่อ'} | 
                                    <i class="fas fa-map-marker-alt"></i> ${acc.province || '-'}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">ยอดเงิน</div>
                                <div style="font-size: 20px; font-weight: 800; color: var(--primary);">฿${Number(acc.amount).toLocaleString()}</div>
                                <div style="font-size: 12px; color: var(--text-secondary);">${acc.nights} คืน</div>
                            </div>
                        </div>
                        ${hasImage ? `
                            <div class="history-image-wrapper">
                                <div class="history-image-preview" onclick="window.open('${imagePath}', '_blank')">
                                    <img src="${imagePath}" alt="ใบเสร็จ">
                                </div>
                            </div>` : `
                            <div style="margin-top: 12px; font-size: 12px; color: var(--text-secondary);">
                                <i class="fas fa-info-circle"></i> ไม่มีรูปภาพใบเสร็จ
                            </div>`
                        }
                    </div>`;
            });
            content.innerHTML = html;
        } else {
            content.innerHTML = `
                <div style="text-align: center; padding: 50px; color: var(--text-secondary);">
                    <i class="fas fa-folder-open" style="font-size: 40px; opacity: 0.3; margin-bottom: 10px;"></i><br>
                    ไม่พบประวัติในช่วงเวลาที่เลือก
                </div>`;
        }
    } catch (e) {
        console.error(e);
        content.innerHTML = '<div style="text-align: center; padding: 50px; color: var(--danger);">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
    }
}
        function openModal() { document.getElementById('accModal').classList.add('active'); }
        function closeModal(e) { if (!e || e.target === document.getElementById('accModal')) { document.getElementById('accModal').classList.remove('active'); } }
        function closeDetailModal(e) { if (!e || e.target === document.getElementById('detailModal')) { document.getElementById('detailModal').classList.remove('active'); } }
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            alertContainer.appendChild(alert);
            setTimeout(() => alert.remove(), 4000);
        }
        // ฟังก์ชันสำหรับบันทึกข้อมูล (ส่งฟอร์มไปยัง PHP)
// --- ส่วนที่ 1: จัดการการบันทึกข้อมูลเมื่อกดปุ่มบันทึกใน Modal ---
document.getElementById('accForm').addEventListener('submit', async (e) => {
    e.preventDefault(); // ป้องกันหน้าจอกะพริบ/รีโหลด
    
    const formData = new FormData(e.target);
    formData.append('action', 'save_accommodation'); // บอก PHP ว่าจะให้ทำ action อะไร

    try {
        const res = await fetch('api_fm.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            showAlert(data.message || 'บันทึกข้อมูลสำเร็จ', 'success');
            document.getElementById('accForm').reset(); // ล้างข้อมูลในฟอร์ม
            closeModal(); // ปิดหน้าต่าง Modal
            fetchData(); // อัปเดตตัวเลขและ Card ทันทีโดยไม่ต้องรีโหลดหน้าเว็บ
        } else {
            showAlert(data.message || 'เกิดข้อผิดพลาดในการบันทึก', 'danger');
        }
    } catch (err) {
        console.error("Save Error:", err);
        showAlert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'danger');
    }
});

// --- ส่วนที่ 2: ปรับปรุงฟังก์ชันโหลดข้อมูลจังหวัด (ทับฟังก์ชันเดิม) ---
async function initPage() {
    try {
        const res = await fetch('api_fm.php?action=fetch_acc_setup');
        const data = await res.json();
        if (data.success) {
            // ใส่รายชื่อพนักงาน
            document.getElementById('driver_select').innerHTML = 
                '<option value="">เลือกพนักงาน</option>' + 
                data.drivers.map(d => `<option value="${d.id}">${d.name}</option>`).join('');

            // ใส่รายชื่อจังหวัด
            if (data.provinces) {
                document.getElementById('province_select').innerHTML = 
                    '<option value="">เลือกจังหวัด</option>' + 
                    data.provinces.map(p => `<option value="${p}">${p}</option>`).join('');
            }
        }
    } catch (e) { 
        console.error("Init Error", e); 
    }
}
    </script>

    <?php include 'sidebar.php'; ?> -->

</body>
</html>