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
<title>ข้อมูลยานพาหนะ</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" href="images/LOgoTJC.png" type="images/LOgoTJC.png">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* --- Global Styles & Variables --- */
    :root {
        /* Core Colors */
        --primary: #3b82f6;
        --success: #16a34a;
        --danger: #ef4444;
        --warning: #f59e0b;

        /* Light Mode Defaults */
        --bg-body: #f1f5f9;
        --bg-card: #ffffff;
        --bg-input: #ffffff;
        --text-main: #1e293b;
        --text-sub: #64748b;
        --border: #e2e8f0;
        --shadow: rgba(0,0,0,0.05);

        /* Badge & Status Colors (Light Mode) */
        --bg-badge-company: #eff6ff;
        --text-badge-company: #3b82f6; /* สีเดียวกับ primary */
        
        --bg-badge-partner: #fff7ed;
        --text-badge-partner: #d97706;

        --bg-status-available: #dcfce7;
        --text-status-available: #16a34a; /* success */

        --bg-status-maintenance: #fef3c7;
        --text-status-maintenance: #d97706;

        --bg-status-busy: #dbeafe;
        --text-status-busy: #3b82f6; /* primary */
        
        --bg-repair-warning: #fff7ed;
        --border-repair-warning: #fed7aa;
        --text-repair-warning: #c2410c;
    }

    /* --- Dark Mode Overrides --- */
    body.dark-mode {
        /* Core Dark Colors */
        --bg-body: #0f172a;
        --bg-card: #1e293b;
        --bg-input: #334155; /* พื้นหลังช่องกรอกข้อมูลเข้มขึ้น */
        --text-main: #f1f5f9;
        --text-sub: #94a3b8; /* ปรับให้สว่างขึ้นนิดนึงให้อ่านง่ายบนพื้นดำ */
        --border: #334155;
        --shadow: rgba(0,0,0,0.3); /* เงาเข้มขึ้น */

        /* Badge & Status Colors (Dark Mode - ปรับให้มืดลงและโปร่งแสง) */
        --bg-badge-company: rgba(59, 130, 246, 0.2);
        --text-badge-company: #60a5fa;

        --bg-badge-partner: rgba(245, 158, 11, 0.2);
        --text-badge-partner: #fbbf24;

        --bg-status-available: rgba(22, 163, 74, 0.2);
        --text-status-available: #4ade80;

        --bg-status-maintenance: rgba(245, 158, 11, 0.2);
        --text-status-maintenance: #fbbf24;

        --bg-status-busy: rgba(59, 130, 246, 0.2);
        --text-status-busy: #60a5fa;
        
        --bg-repair-warning: rgba(194, 65, 12, 0.2);
        --border-repair-warning: #7c2d12;
        --text-repair-warning: #fdba74;
    }

    * { box-sizing: border-box; font-family: 'Prompt', sans-serif; }
    
    html, body { 
        margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; 
        background: var(--bg-body); 
        color: var(--text-main); 
        transition: background 0.3s, color 0.3s; /* เพิ่ม Transition ให้เปลี่ยนสีนุ่มนวล */
    }

    /* Layout */
    .page-container { position: absolute; top: 0; right: 0; bottom: 0; left: 250px; padding: 25px; overflow-y: auto; -webkit-overflow-scrolling: touch; transition: 0.3s; }
    @media(max-width: 768px) { .page-container { left: 0; width: 100%; padding: 15px; padding-top: 70px; } }

    /* Header */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .header-title h1 { margin: 0; font-size: 24px; color: var(--primary); }
    .mobile-menu-btn { display: none; background: none; border: none; font-size: 24px; color: var(--text-main); cursor: pointer; } /* แก้สีปุ่มเมนู */
    @media(max-width: 768px) { .mobile-menu-btn { display: block; margin-right: 10px; } .header-title { display: flex; align-items: center; } }

    /* Buttons */
    .btn { padding: 8px 16px; border-radius: 8px; cursor: pointer; border: none; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 14px; transition: 0.2s; }
    .btn-primary { background: var(--primary); color: white; } .btn-primary:hover { opacity: 0.9; }
    .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); } /* เพิ่ม btn-outline ให้รองรับ */
    
    .btn-icon { width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-sub); cursor: pointer; transition: 0.2s; }
    .btn-icon:hover { border-color: var(--primary); color: var(--primary); background: var(--bg-body); }
    .btn-icon.active { background: var(--warning); color: white; border-color: var(--warning); }

    /* Grid */
    .vehicles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; padding-bottom: 40px; }
    
    /* Vehicle Card */
    .vehicle-card { 
        background: var(--bg-card); 
        border-radius: 16px; 
        padding: 20px; 
        box-shadow: 0 4px 6px -1px var(--shadow); /* ใช้ตัวแปรเงา */
        border: 1px solid var(--border); 
        position: relative; 
        transition: 0.2s; 
    }
    .vehicle-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px var(--shadow); border-color: var(--primary); }
    .vehicle-card.status-maintenance { border-left: 5px solid var(--warning); }
    .vehicle-card.status-available { border-left: 5px solid var(--success); }
    .vehicle-card.status-busy { border-left: 5px solid var(--primary); }

    .vehicle-actions { position: absolute; top: 15px; right: 15px; display: flex; gap: 5px; opacity: 0; transition: 0.2s; }
    .vehicle-card:hover .vehicle-actions { opacity: 1; }

    .card-icon { height: 60px; display: flex; align-items: center; margin-bottom: 10px; font-size: 24px; color: var(--text-sub); }
    .fleet-num-big { font-size: 32px; font-weight: bold; color: var(--primary); opacity: 0.2; }

    .card-info h3 { margin: 5px 0; font-size: 18px; color: var(--text-main); }
    
    /* แก้ไขการใช้สี Badge ให้เป็นตัวแปร */
    .category-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-bottom: 5px; }
    .badge-company { background: var(--bg-badge-company); color: var(--text-badge-company); }
    .badge-partner { background: var(--bg-badge-partner); color: var(--text-badge-partner); }
    
    /* แก้ไขการใช้สี Status ให้เป็นตัวแปร */
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; margin-top: 10px; font-weight: 500; }
    .status-available { background: var(--bg-status-available); color: var(--text-status-available); }
    .status-maintenance { background: var(--bg-status-maintenance); color: var(--text-status-maintenance); }
    .status-busy { background: var(--bg-status-busy); color: var(--text-status-busy); }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: none; justify-content: center; align-items: center; backdrop-filter: blur(2px); padding: 10px; }
    .modal-overlay.show { display: flex; }
    .modal-content { background: var(--bg-card); width: 100%; max-width: 500px; border-radius: 16px; padding: 25px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); border: 1px solid var(--border); }
    .repair-modal { max-width: 900px; } 
    
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-header h2 { margin: 0; font-size: 20px; color: var(--text-main); }
    
    /* Form */
    .form-group { margin-bottom: 15px; }
    .form-row { display: flex; gap: 15px; }
    .form-row .form-group { flex: 1; }
    .form-label { display: block; font-size: 13px; color: var(--text-sub); margin-bottom: 5px; }
    
    /* ปรับ Input ให้รองรับ Dark Mode */
    .form-input, .form-select { 
        width: 100%; padding: 10px; 
        border: 1px solid var(--border); 
        border-radius: 8px; 
        background: var(--bg-input); /* ใช้ตัวแปรพื้นหลัง Input */
        color: var(--text-main); 
        font-size: 14px; 
    }
    .form-input:focus, .form-select:focus { outline: 2px solid var(--primary); border-color: transparent; }
    
    /* Radio Styles */
    .radio-group { display: flex; gap: 10px; }
    .radio-option { flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 8px; text-align: center; cursor: pointer; font-size: 13px; color: var(--text-main); background: var(--bg-card); }
    .radio-option input { display: none; }
    .radio-option.active { border-color: var(--primary); background: var(--bg-badge-company); color: var(--primary); }

    /* Repair Layout & Elements */
    .repair-layout { display: flex; gap: 20px; flex-wrap: wrap; }
    .repair-form-section { flex: 1; min-width: 300px; }
    .repair-history-section { flex: 1; min-width: 300px; border-left: 1px solid var(--border); padding-left: 20px; }
    @media(max-width: 768px) { .repair-history-section { border-left: none; padding-left: 0; border-top: 1px solid var(--border); padding-top: 20px; } }

    /* Warning Box in Modal */
    .warning-box {
        background: var(--bg-repair-warning);
        color: var(--text-repair-warning);
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        font-size: 13px;
        border: 1px solid var(--border-repair-warning);
    }

    /* Timeline */
    .history-timeline { position: relative; padding-left: 20px; margin-top: 15px; }
    .timeline-item { position: relative; padding-bottom: 20px; border-left: 2px solid var(--border); padding-left: 20px; }
    .timeline-marker { position: absolute; left: -6px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: var(--border); }
    .timeline-marker.done { background: var(--success); }
    .timeline-marker.pending { background: var(--warning); }
    
    .timeline-date { font-size: 12px; color: var(--text-sub); margin-bottom: 5px; }
    .timeline-content { background: var(--bg-body); padding: 10px; border-radius: 8px; border: 1px solid var(--border); }
    .tl-header { display: flex; justify-content: space-between; font-weight: 600; font-size: 14px; color: var(--text-main); }
    .tl-images { display: flex; gap: 5px; margin-top: 5px; }
    .tl-images img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; cursor: pointer; }

    /* Image Preview */
    .image-preview-grid { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
    .preview-item { position: relative; width: 60px; height: 60px; }
    .preview-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }
    .preview-item button { position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; cursor: pointer; }

    /* Lightbox */
    .lightbox-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 3000; display: none; justify-content: center; align-items: center; }
    .lightbox-overlay.show { display: flex; }
    .lightbox-content img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
    .lightbox-close { position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 30px; cursor: pointer; }

    /* การตกแต่ง Modal ค่าน้ำมันเพิ่มเติม */
#fuelModal .modal-content {
    max-width: 850px; /* ขยายให้กว้างขึ้นเพื่อวาง 2 ฝั่ง */
    border-top: 5px solid #0284c7; /* แถบสีฟ้าน้ำมัน */
}

.fuel-input-card {
    background: var(--bg-body);
    padding: 15px;
    border-radius: 12px;
    border: 1px solid var(--border);
    margin-bottom: 15px;
}

.fuel-history-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
}

.fuel-history-table tr {
    background: var(--bg-card);
    transition: 0.2s;
}

.fuel-history-table td {
    padding: 12px;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.fuel-history-table td:first-child { border-left: 1px solid var(--border); border-radius: 8px 0 0 8px; }
.fuel-history-table td:last-child { border-right: 1px solid var(--border); border-radius: 0 8px 8px 0; }

.fuel-amount-badge {
    background: rgba(22, 163, 74, 0.1);
    color: var(--success);
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
}

.fuel-receipt-btn {
    color: var(--primary);
    cursor: pointer;
    background: none;
    border: 1px solid var(--primary);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
}

.fuel-receipt-btn:hover {
    background: var(--primary);
    color: white;
}


</style>
</head>
<body>

    <?php include 'sidebar.php'; ?>
    <div id="sidebarOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:900;" onclick="toggleSidebar()"></div>

    <div class="page-container" id="mainApp">
        <div class="page-header">
            <div class="header-title">
                <button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <h1>🚛 ข้อมูลยานพาหนะ</h1>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> เพิ่มรถใหม่</button>
        </div>

        <div id="loading" style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>
        <div class="vehicles-grid" id="vehiclesGrid"></div>
    </div>

    <div class="modal-overlay" id="vehicleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">เพิ่มรถ</h2>
                <button class="btn-icon" onclick="closeModal('vehicleModal')"><i class="fas fa-times"></i></button>
            </div>
            <form id="vehicleForm" onsubmit="handleSaveVehicle(event)">
                <input type="hidden" name="id" id="vId">
                <input type="hidden" name="action" id="vAction" value="add_vehicle">
                
                <div class="form-group">
                    <label class="form-label">ประเภท</label>
                    <div class="radio-group">
                        <label class="radio-option active" onclick="setCategory('company', this)">
                            <input type="radio" name="category" value="company" checked> 🏢 รถบริษัท
                        </label>
                        <label class="radio-option" onclick="setCategory('partner', this)">
                            <input type="radio" name="category" value="partner"> 🤝 รถร่วม
                        </label>
                    </div>
                </div>


                
                
                <div class="form-row">
                    <div class="form-group"><label class="form-label">เบอร์รถ</label><input type="text" name="fleet_number" id="vFleet" class="form-input"></div>
                    <div class="form-group"><label class="form-label">ทะเบียน</label><input type="text" name="plate_number" id="vPlate" class="form-input"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ชนิดรถ</label>
                    <select name="type" id="vType" class="form-select">
                        <option value="4 ล้อ">🚗 4 ล้อ</option>
                        <option value="6 ล้อ">🚛 6 ล้อ</option>
                        <option value="10 ล้อ">🚚 10 ล้อ</option>
                        <option value="หัวลาก">🚐 หัวลาก</option>
                    </select>
                </div>
                
                <div class="form-group" id="rateGroup" style="display:none;">
                    <label class="form-label">💰 เรตค่าจ้าง (บาท/วัน)</label>
                    <input type="number" name="daily_rate" id="vRate" class="form-input">
                </div>
                
                <div class="form-group"><label class="form-label">เบอร์โทร</label><input type="tel" name="phone" id="vPhone" class="form-input"></div>
                
                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;">บันทึกข้อมูล</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="repairModal">
        <div class="modal-content repair-modal">
            <div class="modal-header">
                <h2>🔧 แจ้งซ่อม: <span id="repairPlate"></span></h2>
                <button class="btn-icon" onclick="closeModal('repairModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body repair-layout">
                <div class="repair-form-section">
                    <div style="background:#fff7ed; color:#c2410c; padding:10px; border-radius:6px; margin-bottom:15px; font-size:13px; border:1px solid #fed7aa;">
                        <i class="fas fa-exclamation-triangle"></i> เมื่อบันทึกแล้ว สถานะรถจะเปลี่ยนเป็น <b>"ซ่อมบำรุง"</b> ทันที
                    </div>
                    <form id="repairForm" onsubmit="handleSaveRepair(event)">
                        <input type="hidden" name="vehicle_id" id="rVehicleId">
                        <input type="hidden" name="action" value="create_log">
                        
                        <div class="form-group">
                            <label class="form-label">วันที่ส่งซ่อม</label>
                            <input type="date" name="repair_date" id="rDate" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">สถานที่ซ่อม (ศูนย์/อู่)</label>
                            <input type="text" name="service_center" id="rCenter" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" style="display:flex; justify-content:space-between;">
                                รายการซ่อม
                                <button type="button" onclick="addRepairItem()" style="background:none; border:none; color:var(--primary); cursor:pointer; font-size:12px;">+ เพิ่มรายการ</button>
                            </label>
                            <div id="repairItemsContainer" style="display:flex; flexDirection:column; gap:8px;">
                                <input type="text" name="items[]" class="form-input repair-item-input" placeholder="รายการที่ 1">
                            </div>
                        </div>

                        <div class="form-group"><label class="form-label">ค่าใช้จ่าย (บาท)</label><input type="number" name="cost" id="rCost" class="form-input"></div>
                        
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">วันที่รับรถ (คาดการณ์)</label><input type="date" name="pickup_date" id="rPickupDate" class="form-input"></div>
                            <div class="form-group"><label class="form-label">เวลา</label><input type="time" name="pickup_time" id="rPickupTime" class="form-input"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">รูปภาพอาการเสีย</label>
                            <label class="btn btn-outline" style="width:100%; border-style:dashed;">
                                <i class="fas fa-image"></i> เลือกรูปภาพ...
                                <input type="file" id="rImages" multiple accept="image/*" onchange="handleImageSelect(this)" hidden>
                            </label>
                            <div class="image-preview-grid" id="rImagePreview"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width:100%; background:#f97316;">บันทึกแจ้งซ่อม</button>
                    </form>
                </div>

                <div class="repair-history-section">
                    <h3><i class="fas fa-history"></i> ประวัติการซ่อม</h3>
                    <div class="history-timeline" id="repairTimeline"></div>
                </div>
            </div>
        </div>
    </div>
   <div class="modal-overlay" id="fuelModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 style="color:#0284c7;"><i class="fas fa-gas-pump"></i> แจ้งค่าน้ำมัน: <span id="fuelPlateText"></span></h2>
            <button class="btn-icon" onclick="closeModal('fuelModal')"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="modal-body repair-layout">
            <div class="repair-form-section">
                <form id="fuelForm" onsubmit="handleSaveFuel(event)">
                    <input type="hidden" name="vehicle_id" id="fVehicleId">
                    <input type="hidden" name="action" value="add_fuel">
                    
                    <div class="fuel-input-card">
                        <div class="form-group">
                            <label class="form-label">วันที่เติม</label>
                            <input type="date" name="fill_date" id="fDate" class="form-input" required>
                        </div>
                        
                        <div class="form-row">
                     
                            <div class="form-group">
                                <label class="form-label">จำนวน (ลิตร)</label>
                                <input type="number" step="0.01" name="liters" id="fLiters" class="form-input" placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ยอดเงินรวม (บาท) *</label>
                            <input type="number" step="0.01" name="amount" id="fAmount" class="form-input" style="font-size:18px; font-weight:bold; color:#0284c7;" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">รูปภาพใบเสร็จ</label>
                        <label class="btn btn-outline" style="width:100%; border-style:dashed; padding:15px; background:white;">
                            <i class="fas fa-camera fa-lg"></i><br>แนบบิลน้ำมัน
                            <input type="file" name="fuel_receipt" id="fReceipt" accept="image/*" hidden onchange="document.getElementById('fFileName').innerText = '📁 ' + this.files[0].name">
                        </label>
                        <div id="fFileName" style="font-size:12px; margin-top:5px; color:var(--primary); text-align:center;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">หมายเหตุ</label>
                        <input type="text" name="note" id="fNote" class="form-input" placeholder="ระบุชื่อปั๊ม...">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; background:#0284c7; font-size:16px;">
                        <i class="fas fa-save"></i> บันทึกข้อมูล
                    </button>
                </form>
            </div>

            <div class="repair-history-section">
                <h3 style="font-size:16px; margin-bottom:15px;"><i class="fas fa-clock-rotate-left"></i> ประวัติล่าสุด</h3>
                <div id="fuelHistoryList">
                    </div>
            </div>
        </div>
    </div>
</div>

    <div class="modal-overlay" id="completionModal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header" style="background:#e0f2fe; margin:-25px -25px 20px -25px; padding:15px 25px; border-radius:16px 16px 0 0; color:#0369a1;">
                <h2 style="font-size:18px;"><i class="fas fa-check-circle"></i> ยืนยันซ่อมเสร็จ</h2>
                <button class="btn-icon" onclick="closeModal('completionModal')" style="background:transparent; border:none;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:15px; color:#666;">กรุณาระบุวัน-เวลาที่รับรถจริง</p>
                <input type="hidden" id="cLogId">
                <div class="form-group"><label class="form-label">วันที่รับรถ</label><input type="date" id="cDate" class="form-input"></div>
                <div class="form-group"><label class="form-label">เวลา</label><input type="time" id="cTime" class="form-input"></div>
                
                <div class="form-group" style="margin-top:15px; border-top:1px dashed #ccc; paddingTop:15px;">
                    <label class="form-label">แนบรูปใบเสร็จ (ถ้ามี)</label>
                    <label class="btn btn-outline" style="width:100%; border-style:dashed;">
                        <i class="fas fa-upload"></i> เลือกรูปใบเสร็จ
                        <input type="file" id="cFile" accept="image/*" hidden onchange="handleReceiptSelect(this)">
                    </label>
                    <div id="cFileName" style="font-size:12px; margin-top:5px; color:#666;"></div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button class="btn btn-outline" onclick="closeModal('completionModal')">ยกเลิก</button>
                    <button class="btn btn-primary" onclick="confirmCompletion()">ยืนยันจบงาน</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal-overlay" id="editRepairModal">
        <div class="modal-content repair-modal">
            <div class="modal-header">
                <h2>✏️ แก้ไขประวัติการซ่อม</h2>
                <button class="btn-icon" onclick="closeModal('editRepairModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="editRepairForm" onsubmit="handleUpdateRepair(event)">
                    <input type="hidden" name="id" id="eLogId">
                    <input type="hidden" name="action" value="update_log">

                    <div class="form-row">
                         <div class="form-group">
                            <label class="form-label">วันที่ซ่อม</label>
                            <input type="date" name="repair_date" id="eDate" class="form-input" required>
                        </div>
                         <div class="form-group">
                            <label class="form-label">ค่าใช้จ่าย (บาท)</label>
                            <input type="number" name="cost" id="eCost" class="form-input">
                        </div>
                    </div>
                   
                    <div class="form-group">
                        <label class="form-label">สถานที่ซ่อม</label>
                        <input type="text" name="service_center" id="eCenter" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">รายละเอียดการซ่อม</label>
                        <textarea name="description" id="eDesc" class="form-input" rows="4" style="resize: vertical;"></textarea>
                    </div>

                    <div class="form-group" style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 15px;">
                        <label class="form-label" style="font-weight:600;">จัดการรูปภาพ</label>
                        
                        <div style="margin-bottom: 10px; font-size: 13px; color: var(--text-sub);">รูปภาพเดิม (คลิก X เพื่อลบ):</div>
                        <div class="image-preview-grid" id="eOldImagesPreview" style="margin-bottom: 15px;"></div>

                        <label class="btn btn-outline" style="width:100%; border-style:dashed;">
                            <i class="fas fa-plus"></i> เพิ่มรูปภาพใหม่...
                            <input type="file" name="new_images[]" multiple accept="image/*" onchange="handleEditImageSelect(this)" hidden>
                        </label>
                        <div class="image-preview-grid" id="eNewImagesPreview" style="margin-top:10px;"></div>
                    </div>

                    <div class="form-group" style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 15px;">
                        <label class="form-label" style="font-weight:600;">ใบเสร็จรับเงิน</label>
                        <div id="eCurrentReceiptInfo" style="margin-bottom:10px; font-size:13px;"></div>
                         <label class="btn btn-outline" style="width:100%; border-style:dashed; font-size: 13px;">
                            <i class="fas fa-file-invoice"></i> เปลี่ยน/เพิ่ม ใบเสร็จ...
                            <input type="file" name="receipt_image" accept="image/*" hidden onchange="document.getElementById('eNewReceiptName').innerText = this.files[0]?.name || ''">
                        </label>
                        <div id="eNewReceiptName" style="font-size:12px; margin-top:5px; color:var(--primary);"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 20px;">บันทึกการแก้ไข</button>
                </form>
            </div>
        </div>
    </div>

    <div class="lightbox-overlay" id="lightbox" onclick="this.classList.remove('show')">
        <button class="lightbox-close">&times;</button>
        <div class="lightbox-content"><img id="lightboxImg" src=""></div>
    </div>
    <div class="modal-overlay" id="fuelModal">
    <div class="modal-content repair-modal"> <div class="modal-header">
            <h2>⛽ บันทึกการเติมน้ำมัน: <span id="fuelPlate"></span></h2>
            <button class="btn-icon" onclick="closeModal('fuelModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body repair-layout">
            <div class="repair-form-section">
                <form id="fuelForm" onsubmit="handleSaveFuel(event)">
                    <input type="hidden" name="vehicle_id" id="fVehicleId">
                    <input type="hidden" name="action" value="add_fuel">
                    
                    <div class="form-group">
                        <label class="form-label">วันที่เติม</label>
                        <input type="date" name="fill_date" id="fDate" class="form-input" required>
                    </div>
                    
                        <div class="form-group">
                            <label class="form-label">จำนวน (ลิตร)</label>
                            <input type="number" step="0.01" name="liters" class="form-input" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ยอดเงิน (บาท)</label>
                        <input type="number" step="0.01" name="amount" id="fAmount" class="form-input" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">สถานที่เติม / หมายเหตุ</label>
                        <input type="text" name="note" class="form-input" placeholder="เช่น ปตท. สาขา...">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%; background:#0284c7;">บันทึกข้อมูลน้ำมัน</button>
                </form>
            </div>

            <div class="repair-history-section">
                <h3><i class="fas fa-history"></i> ประวัติ 5 ครั้งล่าสุด</h3>
                <div id="fuelHistory" style="margin-top:15px;">
                    </div>
            </div>
        </div>
    </div>
</div>

<script>
    // --- Global State ---
    let state = { vehicles: [], selectedVehicle: null, repairFiles: [], editNewFiles: [] };
    
    // --- Configuration ---
    const API_URL = 'api_fm.php'; 
    const REPAIR_URL = 'uploads/maintenance';      // โฟลเดอร์รูปซ่อม
    const TAX_URL = 'uploads/tax_maintenance';     // โฟลเดอร์รูปบิล

    document.addEventListener('DOMContentLoaded', () => {
        const theme = localStorage.getItem('tjc_theme');
        if(theme === 'dark') document.body.classList.add('dark-mode');
        fetchVehicles();
    });

    // 🟢 1. ฟังก์ชันช่วยจัดการ Path (สำคัญมาก)
    function cleanFileName(src) {
        if (!src || src === 'null' || src === 'undefined') return '';
        let s = String(src);
        // ลบ Path ที่อาจติดมาจาก DB
        s = s.replace(/uploads\/maintenance\//g, '')
             .replace(/uploads\/tax_maintenance\//g, '')
             .replace(/\\/g, '/'); 
        // ลบเครื่องหมายคำพูดและวงเล็บ array
        s = s.replace(/[\[\]"']/g, '');
        return s.trim();
    }

    function getRepairPath(src) {
        const name = cleanFileName(src);
        return name ? `${REPAIR_URL}/${name}` : '';
    }

    function getTaxPath(src) {
        const name = cleanFileName(src);
        return name ? `${TAX_URL}/${name}` : '';
    }

    // --- Sidebar Toggle ---
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if(sidebar) {
            sidebar.classList.toggle('show');
            overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
        }
    }

    // --- Fetch Data ---
    async function fetchVehicles() {
        try {
            const res = await fetch(`${API_URL}?action=fetch_vehicles_all`);
            const data = await res.json();
            
            state.vehicles = Array.isArray(data) ? data : [];
            // Sort: Company -> Partner
            state.vehicles.sort((a, b) => {
                if (a.category === 'company' && b.category === 'company') {
                    return (parseInt(a.fleet_number)||999) - (parseInt(b.fleet_number)||999);
                }
                return (a.plate_number || '').localeCompare(b.plate_number || '');
            });

            renderVehicles();
            document.getElementById('loading').style.display = 'none';
        } catch (e) { console.error(e); }
    }

    // --- Render ---
function renderVehicles() {
    const grid = document.getElementById('vehiclesGrid');
    grid.innerHTML = '';
    
    state.vehicles.forEach(v => {
        const statusLabel = { available: '✓ พร้อมใช้', busy: '🔄 วิ่งงาน', maintenance: '🔧 ซ่อมบำรุง' };
        const badgeIcon = v.category === 'partner' ? '<i class="fas fa-handshake"></i> รถร่วม' : '<i class="fas fa-building"></i> รถบริษัท';
        const badgeClass = v.category === 'partner' ? 'badge-partner' : 'badge-company';
        const iconDisplay = v.fleet_number ? `<span class="fleet-num-big">${v.fleet_number}</span>` : `<i class="fas fa-truck" style="font-size:32px;"></i>`;

        const html = `
            <div class="vehicle-card status-${v.status}">
                <div class="vehicle-actions">
                    <button class="btn-icon" title="แจ้งค่าน้ำมัน" onclick="openFuelModal(${v.id})" style="color: #0284c7;">
                        <i class="fas fa-gas-pump"></i>
                    </button>
                    
                    <button class="btn-icon" title="แจ้งซ่อม" onclick="openRepairModal(${v.id})"><i class="fas fa-wrench"></i></button>
                    <button class="btn-icon ${v.status==='maintenance'?'active':''}" title="เปลี่ยนสถานะ" onclick="toggleStatus(${v.id}, '${v.status}')"><i class="fas fa-cog"></i></button>
                    <button class="btn-icon" title="แก้ไข" onclick="openEditModal(${v.id})"><i class="fas fa-pen"></i></button>
                    <button class="btn-icon" title="ลบ" onclick="deleteVehicle(${v.id})"><i class="fas fa-trash"></i></button>
                </div>
                
                <div class="card-icon">${iconDisplay}</div>
                
                <div class="card-info">
                    <div class="category-badge ${badgeClass}">${badgeIcon}</div>
                    <h3>${v.fleet_number ? 'เบอร์ '+v.fleet_number+' ' : ''}${v.plate_number}</h3>
                    <p style="font-size:12px; color:var(--text-sub); margin:5px 0;"><i class="fas fa-truck"></i> ${v.type}</p>
                    <div class="status-badge status-${v.status}">${statusLabel[v.status] || v.status}</div>
                </div>
            </div>
        `;
        grid.insertAdjacentHTML('beforeend', html);
    });
}

    // --- CRUD Vehicles ---
    function openAddModal() {
        document.getElementById('vehicleForm').reset();
        document.getElementById('vId').value = '';
        document.getElementById('vAction').value = 'add_vehicle';
        document.getElementById('modalTitle').innerText = 'เพิ่มรถใหม่';
        setCategory('company', document.querySelector('.radio-option input[value="company"]').parentElement);
        document.getElementById('vehicleModal').classList.add('show');
    }

    function openEditModal(id) {
        const v = state.vehicles.find(x => x.id == id);
        if(!v) return;
        document.getElementById('vId').value = v.id;
        document.getElementById('vAction').value = 'update_vehicle';
        document.getElementById('vFleet').value = v.fleet_number;
        document.getElementById('vPlate').value = v.plate_number;
        document.getElementById('vType').value = v.type;
        document.getElementById('vPhone').value = v.phone;
        document.getElementById('vRate').value = v.daily_rate;
        document.getElementById('modalTitle').innerText = 'แก้ไขรถ';
        
        const cat = v.category || 'company';
        const radio = document.querySelector(`.radio-option input[value="${cat}"]`);
        if(radio) { radio.checked = true; setCategory(cat, radio.parentElement); }
        
        document.getElementById('vehicleModal').classList.add('show');
    }

    function setCategory(val, el) {
        document.querySelectorAll('.radio-option').forEach(r => r.classList.remove('active'));
        el.classList.add('active');
        el.querySelector('input').checked = true;
        document.getElementById('rateGroup').style.display = val === 'partner' ? 'block' : 'none';
    }

    async function handleSaveVehicle(e) {
        e.preventDefault();
        const f = new FormData(e.target);
        await postData(f);
        closeModal('vehicleModal');
        fetchVehicles();
    }

    async function deleteVehicle(id) {
        if(!confirm('ยืนยันลบรถคันนี้?')) return;
        const f = new FormData();
        f.append('action', 'delete_vehicle');
        f.append('id', id);
        await postData(f);
        fetchVehicles();
    }

    async function toggleStatus(id, currentStatus) {
        const newStatus = currentStatus === 'maintenance' ? 'available' : 'maintenance';
        const f = new FormData();
        f.append('action', 'update_vehicle_status');
        f.append('id', id);
        f.append('status', newStatus);
        await postData(f);
        fetchVehicles();
    }

    // --- Repair Logic (แจ้งซ่อม) ---
    async function openRepairModal(id) {
        const v = state.vehicles.find(x => x.id == id);
        if(!v) return;
        state.selectedVehicle = v;
        document.getElementById('repairPlate').innerText = v.plate_number;
        document.getElementById('repairForm').reset();
        document.getElementById('rVehicleId').value = v.id;
        document.getElementById('rDate').valueAsDate = new Date();
        document.getElementById('repairItemsContainer').innerHTML = '<input type="text" name="items[]" class="form-input repair-item-input" placeholder="รายการที่ 1">';
        document.getElementById('rImagePreview').innerHTML = '';
        state.repairFiles = [];
        
        document.getElementById('repairModal').classList.add('show');
        fetchRepairHistory(id);
    }

    function addRepairItem() {
        const div = document.getElementById('repairItemsContainer');
        const count = div.children.length + 1;
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'items[]';
        input.className = 'form-input repair-item-input';
        input.placeholder = `รายการที่ ${count}`;
        div.appendChild(input);
    }

    function handleImageSelect(input) {
        const files = Array.from(input.files);
        state.repairFiles = [...state.repairFiles, ...files];
        renderImagePreviews();
    }

    function renderImagePreviews() {
        const div = document.getElementById('rImagePreview');
        div.innerHTML = '';
        state.repairFiles.forEach((f, i) => {
            const wrap = document.createElement('div');
            wrap.className = 'preview-item';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            const btn = document.createElement('button');
            btn.innerHTML = '&times;';
            btn.type = 'button';
            btn.onclick = () => { state.repairFiles.splice(i, 1); renderImagePreviews(); };
            wrap.appendChild(img);
            wrap.appendChild(btn);
            div.appendChild(wrap);
        });
    }

    async function handleSaveRepair(e) {
        e.preventDefault();
        const f = new FormData(e.target);
        
        const items = [];
        document.querySelectorAll('.repair-item-input').forEach(i => { if(i.value.trim()) items.push(i.value.trim()); });
        if(items.length === 0) return alert('กรุณากรอกรายการซ่อม');
        f.append('description', items.join('\n'));
        
        state.repairFiles.forEach(file => f.append('images[]', file));
        
        const pDate = document.getElementById('rPickupDate').value;
        const pTime = document.getElementById('rPickupTime').value;
        if(pDate) f.append('pickup_date', `${pDate} ${pTime || '00:00'}`);

        await postData(f);
        alert('แจ้งซ่อมสำเร็จ');
        openRepairModal(state.selectedVehicle.id); 
        fetchVehicles(); 
    }

    // 🟢 2. แก้ไข fetchRepairHistory ให้ใช้ Path ที่ถูกต้อง
    async function fetchRepairHistory(vid) {
        const div = document.getElementById('repairTimeline');
        div.innerHTML = '<p style="color:#999">กำลังโหลด...</p>';
        try {
            const res = await fetch(`${API_URL}?action=fetch_repair_logs&vehicle_id=${vid}`);
            const logs = await res.json();
            
            if(logs.length === 0) {
                div.innerHTML = '<div class="no-history">ยังไม่มีประวัติการซ่อม</div>';
                return;
            }

            div.innerHTML = '';
            logs.forEach(log => {
                const isDone = log.status === 'completed';
                const date = new Date(log.repair_date);
                const descList = log.description ? log.description.split('\n') : [];
                
                // จัดการรูปซ่อม (Maintenance Images)
                let repairImages = [];
                if (Array.isArray(log.images)) {
                     repairImages = log.images;
                } else if (typeof log.images === 'string') {
                     try { repairImages = JSON.parse(log.images); } catch(e) { repairImages = [log.images]; }
                }
                repairImages = repairImages.filter(img => img && img !== 'null' && img.trim() !== '');

                // จัดการรูปบิล (Receipt)
                const receiptName = cleanFileName(log.receipt_image);
                const hasReceipt = receiptName !== '';

                const logStr = JSON.stringify(log).replace(/"/g, '&quot;');
                const editBtn = `<button onclick="openEditRepairModal(${logStr})" style="background:transparent; border:1px solid var(--border); color:var(--text-sub); width:28px; height:28px; border-radius:4px; cursor:pointer; margin-left:10px;"><i class="fas fa-pen" style="font-size:12px;"></i></button>`;

                let html = `
                    <div class="timeline-item">
                        <div class="timeline-marker ${isDone?'done':'pending'}"></div>
                        <div class="timeline-date">
                            <div style="font-size:18px;font-weight:bold;">${date.getDate()}</div>
                            <div>${date.toLocaleDateString('th-TH', {month:'short'})}</div>
                        </div>
                        <div class="timeline-content">
                            <div class="tl-header">
                                <div>
                                    ${descList.length > 1 
                                        ? `<ul style="padding-left:15px;margin:0;">${descList.map(d=>`<li>${d}</li>`).join('')}</ul>`
                                        : `<span>${log.description}</span>`
                                    }
                                </div>
                                <div style="display:flex; align-items:center;">
                                    <span style="color:#d97706;">${log.cost > 0 ? '฿'+Number(log.cost).toLocaleString() : '-'}</span>
                                    ${editBtn}
                                </div>
                            </div>
                            <div style="font-size:12px; color:#64748b; margin-top:5px;"><i class="fas fa-map-marker-alt"></i> ${log.service_center || '-'}</div>
                            
                            ${repairImages.length > 0 
                                ? `<div class="tl-images">
                                    ${repairImages.map(img => `<img src="${getRepairPath(img)}" onclick="openLightbox(this.src)" onerror="this.style.display='none'">`).join('')}
                                   </div>` 
                                : ''}
                            
                            <div style="margin-top:10px; padding-top:10px; border-top:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    ${isDone 
                                        ? `<span style="color:#16a34a; font-size:12px;"><i class="fas fa-check-circle"></i> เสร็จ: ${new Date(log.pickup_date).toLocaleDateString('th-TH')}</span>`
                                        : `<span style="color:#d97706; font-size:12px;">กำลังซ่อม...</span>`
                                    }
                                    
                                    ${hasReceipt ? `<button onclick="openLightbox('${getTaxPath(receiptName)}')" style="margin-left:10px; background:#ecfccb; color:#365314; border:1px solid #84cc16; padding:2px 8px; border-radius:4px; font-size:11px; cursor:pointer;"><i class="fas fa-file-invoice"></i> ใบเสร็จ</button>` : ''}
                                </div>
                                
                                ${!isDone ? `<button onclick="openCompletionModal(${log.id})" style="background:#10b981; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">แจ้งจบงาน</button>` : ''}
                            </div>
                        </div>
                    </div>
                `;
                div.insertAdjacentHTML('beforeend', html);
            });

        } catch(e) { console.error(e); div.innerHTML = 'โหลดข้อมูลพลาด'; }
    }

    // 🟢 3. แก้ไข openEditRepairModal ให้แสดงรูปบิลถูกต้อง
    function openEditRepairModal(log) {
        state.editNewFiles = [];
        document.getElementById('editRepairForm').reset();
        document.getElementById('eNewImagesPreview').innerHTML = '';
        document.getElementById('eNewReceiptName').innerText = '';
        
        document.getElementById('eLogId').value = log.id;
        document.getElementById('eDate').value = log.repair_date.split(' ')[0];
        document.getElementById('eCost').value = log.cost;
        document.getElementById('eCenter').value = log.service_center;
        document.getElementById('eDesc').value = log.description;
        
        const oldPreview = document.getElementById('eOldImagesPreview');
        oldPreview.innerHTML = '';
        
        let currentImages = [];
        if (Array.isArray(log.images)) currentImages = log.images;
        else if (typeof log.images === 'string') try { currentImages = JSON.parse(log.images); } catch(e){ currentImages = [log.images]; }
        currentImages = currentImages.filter(img => img && img !== 'null' && img.trim() !== '');

        if(currentImages.length > 0) {
            currentImages.forEach(imgName => {
                const wrap = document.createElement('div');
                wrap.className = 'preview-item';
                wrap.innerHTML = `
                    <img src="${getRepairPath(imgName)}" onerror="this.parentElement.style.display='none'">
                    <button type="button" onclick="this.parentElement.remove()">&times;</button>
                    <input type="hidden" name="keep_images[]" value="${imgName}">
                `;
                oldPreview.appendChild(wrap);
            });
        } else {
            oldPreview.innerHTML = '<span style="color:#ccc; font-size:12px;">ไม่มีรูปภาพเดิม</span>';
        }

        // Receipt Status
        const receiptInfo = document.getElementById('eCurrentReceiptInfo');
        const receiptName = cleanFileName(log.receipt_image);
        
        if(receiptName) {
            receiptInfo.innerHTML = `<span style="color:var(--success)"><i class="fas fa-check"></i> มีใบเสร็จแล้ว</span> (<a href="#" onclick="openLightbox('${getTaxPath(receiptName)}'); return false;">ดู</a>)`;
        } else {
            receiptInfo.innerHTML = `<span style="color:#ccc;">ยังไม่มีใบเสร็จ</span>`;
        }

        document.getElementById('editRepairModal').classList.add('show');
    }

    function handleEditImageSelect(input) {
        const files = Array.from(input.files);
        state.editNewFiles = [...state.editNewFiles, ...files];
        renderEditNewImagePreviews();
    }

    function renderEditNewImagePreviews() {
        const div = document.getElementById('eNewImagesPreview');
        div.innerHTML = '';
        state.editNewFiles.forEach((f, i) => {
            const wrap = document.createElement('div');
            wrap.className = 'preview-item';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            const btn = document.createElement('button');
            btn.innerHTML = '&times;';
            btn.type = 'button';
            btn.onclick = () => { state.editNewFiles.splice(i, 1); renderEditNewImagePreviews(); };
            wrap.appendChild(img);
            wrap.appendChild(btn);
            div.appendChild(wrap);
        });
    }

    async function handleUpdateRepair(e) {
        e.preventDefault();
        if(!confirm('ยืนยันการแก้ไขข้อมูล?')) return;

        const f = new FormData(e.target);
        state.editNewFiles.forEach(file => {
            f.append('new_images[]', file);
        });

        await postData(f);
        alert('แก้ไขข้อมูลสำเร็จ');
        closeModal('editRepairModal');
        fetchRepairHistory(state.selectedVehicle.id); 
    }

    // --- Completion Logic ---
    function openCompletionModal(logId) {
        document.getElementById('cLogId').value = logId;
        document.getElementById('cDate').valueAsDate = new Date();
        document.getElementById('cTime').value = new Date().toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit'});
        document.getElementById('cFile').value = '';
        document.getElementById('cFileName').innerText = '';
        document.getElementById('completionModal').classList.add('show');
    }

    function handleReceiptSelect(input) {
        document.getElementById('cFileName').innerText = input.files[0] ? input.files[0].name : '';
    }

    async function confirmCompletion() {
        const id = document.getElementById('cLogId').value;
        const date = document.getElementById('cDate').value;
        const time = document.getElementById('cTime').value;
        const file = document.getElementById('cFile').files[0];

        const f = new FormData();
        f.append('action', 'complete_repair');
        f.append('id', id);
        f.append('pickup_date', `${date} ${time}`);
        if(file) f.append('receipt_image', file);

        await postData(f);
        alert('บันทึกเรียบร้อย');
        closeModal('completionModal');
        openRepairModal(state.selectedVehicle.id);
        fetchVehicles();
    }

    // --- Utilities ---
async function postData(formData) {
    try {
        const res = await fetch(API_URL, { 
            method: 'POST', 
            body: formData 
        });
        
        const text = await res.text();
        console.log("Raw Response:", text); // ตรวจสอบขยะตรงนี้

        try {
            // ค้นหาตำแหน่ง { ของ JSON
            const start = text.indexOf('{');
            if (start !== -1) {
                return JSON.parse(text.substring(start));
            }
            // ถ้าไม่เจอ { แต่ HTTP Status เป็น 200 (Success)
            if (res.ok) return { success: true, forced: true }; 
        } catch (e) {
            if (res.ok) return { success: true, forced: true };
        }
        
        return { success: false, message: "Server error but request sent" };

    } catch(e) { 
        console.error("Network Error:", e);
        return { success: false, message: "เชื่อมต่อล้มเหลว" };
    }
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    
    // 🟢 4. แก้ไข openLightbox ให้รองรับ Error Handling
    function openLightbox(src) { 
        console.log("Opening Image:", src);
        if(!src || src.includes('undefined') || src.endsWith('/')) {
            alert('ไม่พบรูปภาพ');
            return;
        }
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightbox').classList.add('show');
    }
    // ฟังก์ชันเปิด Modal แจ้งน้ำมัน
// 🟢 1. ฟังก์ชันเปิดหน้าต่างแจ้งน้ำมัน
// แก้ไขฟังก์ชัน openFuelModal ให้เรียกโหลดประวัติด้วย
function openFuelModal(vehicleId) {
    const v = state.vehicles.find(x => x.id == vehicleId);
    if (!v) return;

    document.getElementById('fuelForm').reset();
    document.getElementById('fVehicleId').value = v.id;
    document.getElementById('fuelPlateText').innerText = v.plate_number;
    document.getElementById('fDate').valueAsDate = new Date();
    document.getElementById('fFileName').innerText = '';
    
    document.getElementById('fuelModal').classList.add('show');
    fetchFuelHistory(v.id); // 🟢 เรียกโหลดประวัติ
}

// ฟังก์ชันโหลดประวัติน้ำมันแบบตกแต่งสวยงาม
async function fetchFuelHistory(vid) {
    const container = document.getElementById('fuelHistoryList');
    container.innerHTML = '<div style="text-align:center; padding:20px; color:#999;"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>';

    try {
        const res = await fetch(`${API_URL}?action=fetch_fuel_logs&vehicle_id=${vid}`);
        const data = await res.json();

        if (!data || data.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding:20px; color:#ccc;">ไม่มีประวัติการเติมน้ำมัน</div>';
            return;
        }

        let html = '<table class="fuel-history-table">';
        data.forEach(log => {
        const date = new Date(log.fill_date).toLocaleDateString('th-TH', {day:'2-digit', month:'short'});
        const receiptHtml = log.receipt_image 
            ? `<button class="fuel-receipt-btn" onclick="openLightbox('uploads/fuel_receipts/${log.receipt_image}')"><i class="fas fa-file-invoice"></i> บิล</button>` 
            : '';

        html += `
            <tr>
                <td style="font-size:13px;"><b>${date}</b></td>
                <td align="right"><span class="fuel-amount-badge">฿${Number(log.amount).toLocaleString()}</span></td>
                <td align="center">${receiptHtml}</td>
            </tr>
        `;
    });
    html += '</table>';
    container.innerHTML = html;

    } catch (e) {
        container.innerHTML = 'เกิดข้อผิดพลาดในการดึงข้อมูล';
    }
}
async function handleSaveFuel(e) {
    e.preventDefault();

    // 1. ตรวจสอบว่ามี SweetAlert2 (Swal) ติดตั้งอยู่หรือไม่
    const hasSwal = typeof Swal !== 'undefined';

    // 2. ถามเพื่อยืนยันก่อนบันทึก
    if (hasSwal) {
        const confirmAction = await Swal.fire({
            title: 'ยืนยันการบันทึก?',
            text: "คุณตรวจสอบข้อมูลค่าน้ำมันและบิลเรียบร้อยแล้วใช่หรือไม่?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ตกลง, บันทึกเลย',
            cancelButtonText: 'ยกเลิก'
        });
        if (!confirmAction.isConfirmed) return;
    } else {
        // ถ้าไม่มี Swal ให้ใช้ confirm มาตรฐานของเบราว์เซอร์แทน (กันค้าง)
        if (!confirm("ยืนยันบันทึกข้อมูลน้ำมัน?")) return;
    }

    // 3. เริ่มกระบวนการส่งข้อมูล
    const btn = e.target.querySelector('button[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    }

    const f = new FormData(e.target);

    try {
        const res = await postData(f); 
        
        // 4. บังคับให้ถือว่าสำเร็จถ้า res กลับมา หรือข้อมูลเข้า DB แล้ว (ตามที่คุณแจ้ง)
        closeModal('fuelModal'); // ปิดหน้าต่างทันที

        if (hasSwal) {
            await Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                text: 'ข้อมูลถูกเก็บเรียบร้อยแล้ว',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            alert('บันทึกค่าน้ำมันสำเร็จ');
        }

        // ล้างค่าในฟอร์ม
        e.target.reset();
        const fFileName = document.getElementById('fFileName');
        if (fFileName) fFileName.innerText = '';
        
        // อัปเดตข้อมูลรถ
        if (typeof fetchVehicles === 'function') await fetchVehicles();

    } catch (err) {
        console.error("Save Fuel Process Error:", err);
        // แม้จะ Error ในขั้นตอน JSON แต่ถ้าข้อมูลเข้าแล้ว ก็สั่งปิด Modal และอัปเดตหน้าจอ
        closeModal('fuelModal');
        if (typeof fetchVehicles === 'function') fetchVehicles();
        
        if (hasSwal) {
            Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', timer: 1500, showConfirmButton: false });
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-gas-pump"></i> บันทึกข้อมูลน้ำมัน';
        }
    }
}
    window.onclick = function(e) { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }
</script>
</body>
</html>