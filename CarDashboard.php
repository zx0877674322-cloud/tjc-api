<?php
// เริ่มต้น Session
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. เรียกไฟล์ตรวจสอบสิทธิ์
require_once 'auth.php'; 
require_once 'db_connect.php'; 
require_once 'CarManager.php';

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// 2. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// --- ส่วนจัดการข้อมูล Dashboard (Data Logic) ---
$carMgr = new CarManager($conn);
$today = date('Y-m-d');

// ดึงข้อมูลรถทั้งหมด
$allCars = $carMgr->getAllCars();

// ดึงข้อมูลการใช้งานวันนี้มาตรวจสอบสถานะ Active
$rawDailyUsage = $carMgr->getDailyUsage($today);
$activeUsageMap = [];
foreach ($rawDailyUsage as $usage) {
    if ($usage['status'] == 'active') {
        $activeUsageMap[$usage['car_id']] = $usage;
    }
}

// รับค่าตัวกรอง (Filter)
$f_day = isset($_GET['d']) ? $_GET['d'] : '';
$f_month = isset($_GET['m']) ? $_GET['m'] : date('n');
$f_year = isset($_GET['y']) ? $_GET['y'] : date('Y');
$f_car_id = isset($_GET['car_id']) ? $_GET['car_id'] : ''; 

// ดึงประวัติการใช้งาน
$history = $carMgr->getHistoryReport($f_day, $f_month, $f_year);

// ถ้ามีการเลือกรถ ให้กรอง array history
if (!empty($f_car_id)) {
    $history = array_filter($history, function($row) use ($f_car_id) {
        return isset($row['car_id']) && $row['car_id'] == $f_car_id;
    });
}

// ฟังก์ชันแปลงวันที่ไทย
function dateThai($strDate) {
    $strYear = date("Y",strtotime($strDate))+543;
    $strMonth= date("n",strtotime($strDate));
    $strDay= date("j",strtotime($strDate));
    $strMonthCut = Array("","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค.");
    $strMonthThai=$strMonthCut[$strMonth];
    return "$strDay $strMonthThai $strYear";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard ภาพรวมยานพาหนะ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* ================= COLORS ================= */
        :root {
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --bg-input: #ffffff;
            --text-main: #1e293b;
            --text-sub: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --hover-bg: #eff6ff;

            --table-head-bg: #f8fafc;
            --table-head-text: #64748b;
            --table-row-hover: #f8faff;
            
            --input-bg: #ffffff;
            --input-text: #1e293b;
            --input-border: #e2e8f0;

            --card-border-default: #e2e8f0;
            --card-border-hover: #cbd5e1;
            --card-bg-selected: #eff6ff;
            --card-border-selected: #2563eb;
            --card-shadow-selected: 0 10px 15px rgba(37, 99, 235, 0.1);
            --badge-bg-light: #f1f5f9;
        }

        body.dark-mode {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --text-main: #f8fafc;
            --text-sub: #cbd5e1;
            --border-color: #334155;
            --shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            
            --primary-color: #60a5fa;
            --primary-dark: #3b82f6;
            --hover-bg: #334155;

            --table-head-bg: #1e293b; 
            --table-head-text: #e2e8f0; 
            --table-row-hover: #334155;

            --input-bg: #334155;
            --input-text: #f1f5f9;
            --input-border: #475569;

            --card-border-default: #334155;
            --card-border-hover: #475569;
            --card-bg-selected: #1e3a8a;
            --card-border-selected: #60a5fa;
            --card-shadow-selected: 0 10px 15px rgba(0, 0, 0, 0.3);
            --badge-bg-light: #0f172a;
        }

        /* Basic Styles */
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Prompt', sans-serif; transition: background-color 0.3s, color 0.3s; margin: 0; }
        .card, .stat-card, .white-box, .filter-card, .comp-card, .form-container, .modal-content, .car-dashboard-card, .filter-box { background-color: var(--bg-card) !important; color: var(--text-main) !important; border: 1px solid var(--border-color) !important; box-shadow: var(--shadow) !important; }
        input, select, textarea, .form-control, .form-select { background-color: var(--input-bg) !important; color: var(--input-text) !important; border: 1px solid var(--border-color) !important; }
        h1, h2, h3, h4, h5, h6, .text-dark { color: var(--text-main) !important; }
        .text-muted, .text-secondary { color: var(--text-sub) !important; }

        /* Table Styles */
        .table { --bs-table-bg: transparent; --bs-table-hover-bg: transparent; margin-bottom: 0; white-space: nowrap; }
        th { background-color: var(--table-head-bg) !important; color: var(--table-head-text) !important; border-bottom: 2px solid var(--border-color) !important; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; padding: 15px !important; }
        td { background-color: transparent !important; color: var(--text-main) !important; border-bottom: 1px solid var(--border-color) !important; padding: 15px !important; vertical-align: middle; }
        
        /* Timeline */
        .timeline-indicator { display: flex; flex-direction: column; gap: 4px; position: relative; }
        .timeline-indicator::before { content: ''; position: absolute; left: 5px; top: 8px; bottom: 8px; width: 2px; background: var(--border-color); z-index: 0; }
        .time-row { display: flex; align-items: center; position: relative; z-index: 1; }
        .dot { width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--bg-card); flex-shrink: 0; }
        .dot.start { background-color: #10b981; } 
        .dot.end { background-color: #ef4444; } 

        /* Dashboard Specific */
        .car-dashboard-card { border-radius: 16px; transition: all 0.2s ease; cursor: pointer; height: 100%; position: relative; overflow: hidden; display: block; text-decoration: none; border-color: var(--card-border-default) !important; }
        .car-dashboard-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px rgba(0,0,0,0.1) !important; border-color: var(--card-border-hover) !important; }
        .car-dashboard-card.active-selected { background-color: var(--card-bg-selected) !important; border: 2px solid var(--card-border-selected) !important; box-shadow: var(--card-shadow-selected) !important; transform: translateY(-3px); }
        .car-dashboard-card.active-selected::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 10px; right: 10px; background: var(--card-border-selected); color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        
        .car-img-box { width: 100px; height: 70px; border-radius: 8px; overflow: hidden; background: var(--badge-bg-light); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; }
        .car-img-box img { width: 100%; height: 100%; object-fit: contain; }
        
        .status-badge-card { font-size: 0.75rem; padding: 4px 10px; border-radius: 6px; font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px; min-width: 80px; justify-content: center; }

        .filter-box { border-radius: 12px; padding: 20px; }
        .badge.bg-light { background-color: var(--badge-bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        .card-info-footer { background-color: var(--hover-bg) !important; border: 1px solid var(--border-color) !important; color: var(--text-sub) !important; }

        /* Animation สำหรับ Charge */
        @keyframes blink { 50% { opacity: 0.5; } }
        .blink-animation { animation: blink 1.5s infinite; }

        /* Clickable Text for Modal */
        .clickable-text { cursor: pointer; transition: color 0.2s; }
        .clickable-text:hover { color: var(--primary-color) !important; opacity: 0.8; }
        
        /* Modal Style Override */
        .modal-header { border-bottom: 1px solid var(--border-color); }
        .modal-footer { border-top: 1px solid var(--border-color); }
        .btn-close { filter: invert(var(--bs-btn-close-white-filter, 0)); }
        body.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    </style>
</head>
<body>

    <div class="d-flex">
        
        <?php include 'sidebar.php'; ?>

        <div class="flex-grow-1" style="min-height: 100vh; overflow-y: auto;">
            <div class="container-fluid p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold m-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Car Dashboard</h3>
                        <p class="text-secondary small m-0">สถานะรถและประวัติการใช้งาน ณ วันที่ <?php echo dateThai($today); ?></p>
                    </div>
                    <div class="text-end">
                        <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="fas fa-sync-alt me-1"></i> รีเซ็ตตัวกรอง
                        </a>
                    </div>
                </div>

                <h5 class="fw-bold text-secondary mb-3"><i class="fas fa-car me-2"></i>สถานะยานพาหนะ (คลิกเพื่อดูประวัติ)</h5>
                
                <div class="row g-3 mb-5">
                    <?php foreach ($allCars as $car): 
                        $isActive = isset($activeUsageMap[$car['id']]);
                        $activeData = $isActive ? $activeUsageMap[$car['id']] : null;
                        $isSelected = ($f_car_id == $car['id']) ? 'active-selected' : '';

                        // ข้อมูลแสดงผล
                        $displayUserName = $activeData ? $activeData['fullname'] : ($car['busy_user_name'] ?? '-');
                        $displayPhone = $activeData ? ($activeData['phone'] ?? '-') : ($car['busy_user_phone'] ?? '-'); 
                        $displayDest = $activeData ? $activeData['destination'] : ($car['busy_dest'] ?? '-');
                        
                        $isBusy = $isActive || !empty($car['busy_user_id']);

                        $last_location = "-";
                        $last_energy = "-";
                        $last_issue = "";
                        $is_charging_status = false;

                        if (!empty($car['last_info'])) {
                            $parts = explode('|', $car['last_info']);
                            foreach ($parts as $p) {
                                $p = trim($p);
                                if (strpos($p, 'จอดที่') !== false) { $temp = explode(':', $p); if(isset($temp[1])) $last_location = trim($temp[1]); }
                                if (strpos($p, 'พลังงาน') !== false) { $temp = explode(':', $p); if(isset($temp[1])) $last_energy = trim($temp[1]); }
                                if (strpos($p, 'หมายเหตุ') !== false) { $temp = explode(':', $p); if(isset($temp[1])) $last_issue = trim($temp[1]); }
                                
                                if (strpos($p, 'เสียบชาร์จอยู่') !== false) { $is_charging_status = true; }
                            }
                        }

                        // --- ส่วนที่เพิ่ม: แปลงค่าเป็นภาษาไทยและกำหนดสี ---
                        if ($car['type'] != 'EV') {
                            switch ($last_energy) {
                                case 'Empty': 
                                    $last_energy = '<span class="text-danger fw-bold">แดง</span>'; 
                                    break;
                                case 'Full':  
                                    $last_energy = '<span class="text-success fw-bold">เต็ม</span>'; 
                                    break;
                            }
                        }

                        $energyLabel = ($car['type'] == 'EV') ? 'แบตเหลือ' : 'น้ำมันเหลือ';
                        $energyUnit = ($car['type'] == 'EV' && $last_energy != '-') ? '%' : '';

                        // เปลี่ยนสีไอคอนตามระดับพลังงาน
                        if ($car['type'] == 'EV') {
                            $energyIcon = 'fa-charging-station text-primary';
                        } else {
                            if(strpos($last_energy, 'แดง') !== false) {
                                 $energyIcon = 'fa-gas-pump text-danger';
                            } elseif(strpos($last_energy, 'เต็ม') !== false) {
                                 $energyIcon = 'fa-gas-pump text-success';
                            } else {
                                 $energyIcon = 'fa-gas-pump text-warning';
                            }
                        }
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <a href="?car_id=<?php echo $car['id']; ?>&d=<?php echo $f_day; ?>&m=<?php echo $f_month; ?>&y=<?php echo $f_year; ?>" 
                           class="car-dashboard-card p-3 <?php echo $isSelected; ?>" 
                           data-car-id="<?php echo $car['id']; ?>"
                           onclick="handleCardClick(event, this, '<?php echo $car['id']; ?>')">
                           
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="car-img-box">
                                        <?php if($car['car_image']): ?>
                                            <img src="uploads/cars/<?php echo $car['car_image']; ?>">
                                        <?php else: ?>
                                            <div class="text-muted"><i class="fas fa-car fa-2x"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold fs-5"><?php echo $car['name']; ?></div>
                                        <div class="text-secondary small"><?php echo $car['plate']; ?></div>
                                        <div class="mt-1">
                                            <?php if($car['type'] == 'EV'): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2" style="font-size: 0.65rem;">EV</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2" style="font-size: 0.65rem;">Fuel</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <?php if($isBusy): ?>
                                        <span class="status-badge-card bg-danger text-white"><i class="fas fa-user-lock me-1"></i> ไม่ว่าง</span>
                                    <?php else: ?>
                                        <span class="status-badge-card bg-success text-white">ว่าง</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="p-2 rounded card-info-footer small">
                                <?php if($isBusy): ?>
                                    <div class="border border-danger bg-danger bg-opacity-10 text-danger p-2 rounded">
                                        <div class="fw-bold d-flex align-items-center"><i class="fas fa-user me-2"></i> <?php echo $displayUserName; ?></div>
                                        <div class="d-flex align-items-center mt-1"><i class="fas fa-phone me-2"></i> <?php echo $displayPhone; ?></div>
                                        <div class="d-flex align-items-center mt-1 text-truncate"><i class="fas fa-map-marker-alt me-2"></i> <?php echo $displayDest; ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="fas fa-map-marker-alt text-danger me-2" style="width:15px;"></i> 
                                        <span class="text-truncate">จอดที่ : <?php echo $last_location; ?></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas <?php echo $energyIcon; ?> me-2" style="width:15px;"></i> 
                                        <span>
                                            <?php echo $energyLabel; ?> : <?php echo $last_energy; ?><?php echo $energyUnit; ?>
                                        </span>
                                        
                                        <?php if($is_charging_status): ?>
                                            <span class="badge bg-success ms-1 blink-animation" style="font-size: 0.65rem;">
                                                <i class="fas fa-bolt"></i> ชาร์จอยู่
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($last_issue && $last_issue != '-'): ?>
                                        <div class="d-flex align-items-center mt-1 text-warning">
                                            <i class="fas fa-exclamation-circle me-2" style="width:15px;"></i>
                                            <span class="text-truncate" style="max-width: 200px;"><?php echo $last_issue; ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="history-section">
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <h5 class="fw-bold text-secondary m-0">
                            <i class="fas fa-history me-2"></i>
                            <?php 
                                if(!empty($f_car_id)) {
                                    $selectedCarName = "";
                                    foreach($allCars as $c) { if($c['id'] == $f_car_id) $selectedCarName = $c['name']; }
                                    echo "ประวัติการใช้งาน: <span class='text-primary'>$selectedCarName</span>";
                                } else {
                                    echo "ประวัติการใช้งานรวม";
                                }
                            ?>
                        </h5>
                    </div>

                    <div class="filter-box mb-4">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-12 col-md-3">
                                <label class="small text-secondary mb-1">เลือกรถ</label>
                                <select name="car_id" class="form-select form-select-sm" id="select_car_dropdown">
                                    <option value="">-- แสดงรถทุกคัน --</option>
                                    <?php foreach($allCars as $car): ?>
                                        <option value="<?php echo $car['id']; ?>" <?php echo ($f_car_id == $car['id'] ? 'selected' : ''); ?>>
                                            <?php echo $car['name'] . ' (' . $car['plate'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="small text-secondary mb-1">วัน</label>
                                <select name="d" class="form-select form-select-sm">
                                    <option value="">ทั้งหมด</option>
                                    <?php for($i=1; $i<=31; $i++) echo "<option value='$i' ".($f_day==$i?'selected':'').">$i</option>"; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="small text-secondary mb-1">เดือน</label>
                                <select name="m" class="form-select form-select-sm">
                                    <option value="">ทั้งหมด</option>
                                    <?php 
                                    $thai_months = [1=>"มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
                                    foreach($thai_months as $k=>$v) echo "<option value='$k' ".($f_month==$k?'selected':'').">$v</option>"; 
                                    ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="small text-secondary mb-1">ปี (พ.ศ.)</label>
                                <select name="y" class="form-select form-select-sm">
                                    <option value="">ทั้งหมด</option>
                                    <?php 
                                    $curYear = date('Y');
                                    for($i=$curYear; $i>=$curYear-2; $i--) {
                                        $thYear = $i+543;
                                        echo "<option value='$i' ".($f_year==$i?'selected':'').">$thYear</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100 h-100"><i class="fas fa-search me-1"></i> ค้นหา</button>
                            </div>
                        </form>
                    </div>

                    <div class="card shadow-sm border-0 overflow-hidden">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="small text-uppercase">
                                        <th class="ps-4">ช่วงเวลาที่ใช้</th>
                                        <th>ผู้ใช้งาน</th>
                                        <th>รถที่ใช้</th>
                                        <th>สถานที่</th> 
                                        <th>ภารกิจ</th> 
                                        <th>หมายเหตุ</th>
                                        <th class="text-center">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($history)): ?>
                                        <tr><td colspan="7" class="text-center py-5 text-secondary">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
                                    <?php else: ?>
                                        <?php foreach($history as $h): ?>
                                        <?php 
                                            // [NEW] Logic แยกข้อมูลหมายเหตุ (เหมือนในการ์ดรถ)
                                            $parsed_issue = '-';
                                            if (!empty($h['return_note'])) {
                                                $parts = explode('|', $h['return_note']);
                                                foreach ($parts as $p) {
                                                    $p = trim($p);
                                                    if (strpos($p, 'หมายเหตุ') !== false) {
                                                        $temp = explode(':', $p);
                                                        if (isset($temp[1])) {
                                                            $parsed_issue = trim($temp[1]);
                                                        }
                                                    }
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="timeline-indicator">
                                                    <div class="time-row">
                                                        <div class="dot start me-2"></div>
                                                        <small class="text-muted" style="width: 35px;">ออก</small>
                                                        <span class="fw-bold" style="font-family: monospace; font-size: 0.9rem; color: var(--text-main);">
                                                            <?php echo date('d/m H:i', strtotime($h['start_date'])) . ' น.'; ?>
                                                        </span>
                                                    </div>
                                                    <div class="time-row">
                                                        <div class="dot end me-2"></div>
                                                        <small class="text-muted" style="width: 35px;">คืน</small>
                                                        <span class="fw-bold" style="font-family: monospace; font-size: 0.9rem; color: var(--text-main);">
                                                            <?php 
                                                                $endDate = ($h['status'] == 'completed') ? $h['end_date'] : $h['end_date']; 
                                                                echo date('d/m H:i', strtotime($endDate)) . ' น.'; 
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo $h['fullname']; ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light border text-dark"><?php echo $h['car_name']; ?></span>
                                                <small class="text-secondary ms-1"><?php echo $h['plate']; ?></small>
                                            </td>
                                            
                                            <td>
                                                <div class="text-truncate clickable-text" style="max-width: 150px;" 
                                                     title="คลิกเพื่อดูข้อความเต็ม"
                                                     onclick="showFullText('สถานที่', '<?php echo htmlspecialchars($h['destination'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo $h['destination']; ?>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="text-truncate text-secondary clickable-text" style="max-width: 200px;" 
                                                     title="คลิกเพื่อดูข้อความเต็ม"
                                                     onclick="showFullText('ภารกิจ', '<?php echo htmlspecialchars($h['reason'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-tasks text-info me-1"></i> <?php echo $h['reason']; ?>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="text-truncate text-secondary small" style="max-width: 200px; min-width: 150px;">
                                                    <?php 
                                                        if($parsed_issue !== '-') {
                                                            ?>
                                                            <div class="clickable-text" onclick="showFullText('หมายเหตุ', '<?php echo htmlspecialchars($parsed_issue, ENT_QUOTES); ?>')" title="คลิกเพื่อดูข้อความเต็ม">
                                                                <i class="fas fa-comment-alt me-1 text-warning"></i> <?php echo $parsed_issue; ?>
                                                            </div>
                                                            <?php
                                                        } else {
                                                            echo '-';
                                                        }
                                                    ?>
                                                </div>
                                            </td>

                                            <td class="text-center">
                                                <?php if($h['status']=='active'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success"><i class="fas fa-circle fa-xs me-1"></i> กำลังใช้งาน</span>
                                                <?php elseif($h['status']=='completed'): ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fas fa-check me-1"></i> คืนแล้ว</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><?php echo $h['status']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div> 
            </div>
        </div>
    </div>

    <div class="modal fade" id="textDetailModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTitle">รายละเอียด</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modalBody" class="m-0" style="white-space: pre-wrap; line-height: 1.6;"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // โหลด Theme ที่บันทึกไว้ เพื่อให้ส่วน Content แสดงผลถูกต้อง
            const savedTheme = localStorage.getItem('tjc_theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
            }
        });

        // ฟังก์ชันแสดงข้อความเต็มใน Modal
        function showFullText(title, text) {
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalBody').innerText = text;
            
            var myModal = new bootstrap.Modal(document.getElementById('textDetailModal'));
            myModal.show();
        }

        // ฟังก์ชันคลิกการ์ดเพื่อกรองประวัติ (AJAX Simulation)
        function handleCardClick(event, element, carId) {
            event.preventDefault(); 
            
            // จัดการ UI Selected
            document.querySelectorAll('.car-dashboard-card').forEach(card => card.classList.remove('active-selected'));
            element.classList.add('active-selected');

            // เปลี่ยน URL โดยไม่รีโหลดหน้า
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('car_id', carId);
            const targetUrl = currentUrl.toString();
            window.history.pushState({path: targetUrl}, '', targetUrl);

            // Fetch ข้อมูลใหม่
            fetch(targetUrl)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // อัปเดตตารางประวัติ
                    const newHistory = doc.getElementById('history-section').innerHTML;
                    document.getElementById('history-section').innerHTML = newHistory;
                    
                    // อัปเดต Dropdown ให้ตรงกัน
                    const dropdown = document.getElementById('select_car_dropdown');
                    if(dropdown) dropdown.value = carId;
                })
                .catch(err => console.error('Error loading history:', err));
        }
    </script>
</body>
</html>