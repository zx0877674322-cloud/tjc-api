<?php
session_start();
date_default_timezone_set('Asia/Bangkok'); 

require_once 'db_connect.php';
require_once 'CarManager.php';

// --- 1. ฟังก์ชันแปลงวันที่เป็นภาษาไทย ---
function getThaiDate($date) {
    if (!$date) return "-";
    $months = [
        1=>"ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", 
        "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];
    $time = strtotime($date);
    $thai_year = date('Y', $time) + 543;
    return date('j', $time) . " " . $months[date('n', $time)] . " " . $thai_year;
}

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$carMgr = new CarManager($conn);
$today = date('Y-m-d');
$now_time = date('H:i'); 

$activeBooking = $carMgr->getActiveBooking($_SESSION['user_id']);
$user_phone = $carMgr->getUserPhone($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   
    // --- A: ฟังก์ชันคืนรถ ---
    if (isset($_POST['action']) && $_POST['action'] == 'return_car') {
        $booking_id = $_POST['booking_id'];
        $parking_loc = $_POST['parking_location'];
        $energy_level = $_POST['energy_level'];
        $issue = $_POST['car_issue'];            
        
        $is_charging = isset($_POST['is_charging']) ? $_POST['is_charging'] : 0;

        $return_note = "📍 จอดที่: $parking_loc | 🔋 พลังงาน: $energy_level";
        
        if($is_charging == 1) {
            $return_note .= " | ⚡ เสียบชาร์จอยู่";
        }

        if(!empty($issue)) $return_note .= " | ⚠️ หมายเหตุ: $issue";

        if($carMgr->returnCar($booking_id, $_SESSION['user_id'], $return_note)) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', () => {
                    const isDark = document.body.classList.contains('dark-mode') || localStorage.getItem('tjc_theme') === 'dark';
                    Swal.fire({
                        icon: 'success', 
                        title: 'คืนรถเรียบร้อย',
                        html: 'บันทึกข้อมูลและเวลาคืนรถสำเร็จ',
                        confirmButtonText: 'ตกลง', 
                        confirmButtonColor: '#10b981',
                        background: isDark ? '#1e293b' : '#ffffff', 
                        color: isDark ? '#ffffff' : '#1e293b'
                    }).then(() => { window.location.href = 'CarBooking.php'; });
                });
            </script>";
        }
    }

    // --- B: ฟังก์ชันจองรถ ---
    elseif (isset($_POST['action']) && $_POST['action'] == 'book_car') {
        $start_date = $_POST['start_date'];
        $start_time = $_POST['start_time'];
        $end_date = $_POST['end_date'];
        $end_time = $_POST['end_time'];
        
        $start_time = str_replace(' น.', '', $start_time);
        $end_time = str_replace(' น.', '', $end_time);
        
        $start_datetime = $start_date . ' ' . $start_time;
        $end_datetime = $end_date . ' ' . $end_time;
        
        $phone_number = trim($_POST['phone_number']);

        if (strtotime($end_datetime) <= strtotime($start_datetime)) {
            $error_msg = "วันเวลาคืนรถ ต้องหลังจากเวลารับรถครับ";
        } elseif (empty($phone_number)) {
            $error_msg = "กรุณากรอกเบอร์โทรศัพท์ติดต่อ";
        } else {
            $carMgr->updateUserPhone($_SESSION['user_id'], $phone_number);
            $res = $carMgr->createBooking($_SESSION['user_id'], $_POST['car_id'], $start_datetime, $end_datetime, $_POST['destination'], $_POST['reason'], $_POST['passenger_count']);

            if ($res['success']) {
                echo "<script>document.addEventListener('DOMContentLoaded', () => {
                    const isDark = document.body.classList.contains('dark-mode') || localStorage.getItem('tjc_theme') === 'dark';
                    Swal.fire({
                        icon: 'success', 
                        title: 'จองรถสำเร็จ!', 
                        text: 'สถานะ: กำลังใช้งาน',
                        confirmButtonText: 'ตกลง', 
                        confirmButtonColor: '#10b981',
                        background: isDark ? '#1e293b' : '#ffffff', 
                        color: isDark ? '#ffffff' : '#1e293b'
                    }).then(() => { window.location.href = 'CarBooking.php'; });
                });</script>";
            } else {
                $error_msg = $res['message'];
            }
        }

        if (isset($error_msg)) {
            echo "<script>document.addEventListener('DOMContentLoaded', () => {
                const isDark = document.body.classList.contains('dark-mode') || localStorage.getItem('tjc_theme') === 'dark';
                Swal.fire({
                    title: 'แจ้งเตือน', 
                    text: '$error_msg', 
                    icon: 'warning',
                    background: isDark ? '#1e293b' : '#ffffff', 
                    color: isDark ? '#ffffff' : '#1e293b'
                });
            });</script>";
        }
    }
}

$cars = (!$activeBooking) ? $carMgr->getAllCars() : [];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จองรถบริษัท</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ================= VARIABLES ================= */
        :root {
            --bg-body: #f0f2f5; --bg-card: #ffffff; --text-main: #1e293b; --text-sub: #64748b; --border-color: #e2e8f0; --input-bg: #ffffff; --input-border: #cbd5e1;
            --date-box-bg: #e0f2fe; --date-box-text: #0369a1; --time-box-bg: #f8fafc; --time-box-border: #e2e8f0;
            --car-item-bg: #ffffff; --car-item-border: #f1f5f9; --car-item-hover: #94a3b8;
            --car-item-selected-bg: #eff6ff; --car-item-selected-border: #2563eb; --modal-bg: #ffffff; --modal-footer-bg: #f8f9fa;
            
            /* Flatpickr Colors */
            --fp-bg: #ffffff; --fp-text: #1e293b; --fp-border: #e2e8f0; --fp-accent: #2563eb;
        }
        body.dark-mode {
            --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc; --text-sub: #cbd5e1; --border-color: #334155; --input-bg: #334155; --input-border: #475569;
            --date-box-bg: #1e3a8a; --date-box-text: #e0f2fe; --time-box-bg: #0f172a; --time-box-border: #334155;
            --car-item-bg: #1e293b; --car-item-border: #334155; --car-item-hover: #64748b;
            --car-item-selected-bg: rgba(37, 99, 235, 0.15); --car-item-selected-border: #60a5fa; --modal-bg: #1e293b; --modal-footer-bg: #0f172a;

            /* Flatpickr Colors */
            --fp-bg: #1e293b; --fp-text: #f8fafc; --fp-border: #334155; --fp-accent: #60a5fa;
        }
        body { font-family: 'Prompt', sans-serif; background-color: var(--bg-body); color: var(--text-main); transition: 0.3s; }
        .booking-card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); background: var(--bg-card); overflow: hidden; }
        .header-bg { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 20px; }
        .header-bg.active-mode { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .header-bg.bg-white { background: var(--bg-card) !important; color: var(--text-main) !important; border-bottom: 1px solid var(--border-color) !important; }
        .form-label { font-weight: 500; color: var(--text-sub); font-size: 0.9rem; margin-bottom: 5px; }
        .form-control, .form-select { border-radius: 10px; padding: 12px 15px; background-color: var(--input-bg); color: var(--text-main); border-color: var(--input-border); font-weight: 500; }
        .form-control:focus { border-color: #2563eb; background-color: var(--input-bg); color: var(--text-main); }
        .form-control::placeholder { color: #94a3b8; opacity: 1; }
        body.dark-mode .form-control::placeholder { color: #cbd5e1; opacity: 0.6; }
        .datetime-group { background-color: var(--time-box-bg); border: 1px solid var(--time-box-border); border-radius: 12px; padding: 15px; margin-bottom: 15px; }
        .datetime-group input { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); border-radius: 8px; padding: 8px; width: 100%; margin-top: 5px; }
        
        /* Flatpickr Styles */
        .flatpickr-input { background-color: var(--input-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; cursor: pointer; }
        .flatpickr-calendar { background: var(--fp-bg) !important; border: 1px solid var(--fp-border) !important; box-shadow: 0 10px 20px rgba(0,0,0,0.2) !important; }
        .flatpickr-months .flatpickr-month, .flatpickr-current-month .flatpickr-monthDropdown-months, .flatpickr-current-month input.cur-year, .flatpickr-day, .flatpickr-time input, .flatpickr-time .flatpickr-am-pm { color: var(--fp-text) !important; fill: var(--fp-text) !important; }
        .flatpickr-day.selected { background: var(--fp-accent) !important; border-color: var(--fp-accent) !important; color: #fff !important; }
        .flatpickr-time { border-top: 1px solid var(--fp-border) !important; }

        /* Car List Item */
        .car-select-item { border: 2px solid var(--car-item-border); border-radius: 12px; padding: 12px; cursor: pointer; transition: 0.2s; position: relative; background: var(--car-item-bg); display: flex; align-items: center; gap: 15px; }
        .car-select-item:hover { border-color: var(--car-item-hover); transform: translateY(-2px); }
        .car-select-item.selected { border-color: var(--car-item-selected-border); background-color: var(--car-item-selected-bg); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15); }
        .car-select-item.selected::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 15px; top: 15px; color: var(--car-item-selected-border); font-size: 1.2rem; }
        .car-select-item.busy { opacity: 1; cursor: not-allowed; border-color: #ef4444; background: rgba(239, 68, 68, 0.05); }
        .car-img-thumb { width: 120px; height: 80px; border-radius: 8px; overflow: hidden; background: var(--bg-body); border: 1px solid var(--border-color); flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .car-img-thumb img { width: 100%; height: 100%; object-fit: contain; }

        /* Mobile Adjustment for Car List */
        @media (max-width: 576px) {
            .car-img-thumb { width: 80px; height: 60px; }
            .car-select-item { padding: 10px; gap: 10px; }
            .car-select-item.selected::after { top: 5px; right: 5px; font-size: 1rem; }
        }

        .modal-content { background-color: var(--modal-bg); color: var(--text-main); }
        .modal-header, .modal-footer { border-color: var(--border-color); }
        .modal-footer { background-color: var(--modal-footer-bg); }
        .cursor-pointer { cursor: pointer; }
        
        /* Fuel Options */
        .fuel-option { display: none; }
        .fuel-label { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; padding: 12px 5px; border: 2px solid var(--input-border); border-radius: 10px; text-align: center; cursor: pointer; transition: 0.2s; font-size: 0.85rem; height: 80px; background: var(--input-bg); color: var(--text-main); }
        .fuel-option:checked + .fuel-label { background-color: #fff7ed; border-color: #f97316; color: #c2410c; font-weight: bold; }
        body.dark-mode .fuel-option:checked + .fuel-label { background-color: rgba(249, 115, 22, 0.2); color: #fb923c; border-color: #f97316; }
        .fuel-label i { font-size: 1.5rem; margin-bottom: 5px; color: var(--text-sub); }
        .fuel-option:checked + .fuel-label i { color: #f97316; }

        /* [แก้ไข] Mobile Optimization สำหรับปุ่มน้ำมัน ให้เรียง 5 ปุ่มได้ในแถวเดียวเหมือน PC */
        @media (max-width: 576px) {
            .fuel-label {
                padding: 8px 2px; /* ลด Padding */
                height: 70px; /* ลดความสูง */
                font-size: 0.75rem; /* ลดขนาดตัวอักษร */
            }
            .fuel-label i {
                font-size: 1.2rem; /* ลดขนาดไอคอน */
                margin-bottom: 2px;
            }
        }

        .ev-input-box { background: #f0fdf4; border: 2px solid #16a34a; border-radius: 12px; padding: 20px; text-align: center; }
        body.dark-mode .ev-input-box { background: rgba(22, 163, 74, 0.2); }
        .ev-input-box input { font-size: 2.5rem; font-weight: 800; color: #166534; background: transparent; border: none; text-align: center; width: 100px; }
        body.dark-mode .ev-input-box input { color: #4ade80; }
        .ev-input-box input:focus { outline: none; }
        body.dark-mode .text-dark { color: #f8fafc !important; }
        body.dark-mode .text-muted { color: #cbd5e1 !important; }
        body.dark-mode .text-secondary { color: #cbd5e1 !important; }
        
        @keyframes blink { 50% { opacity: 0.5; } }
        .blink-animation { animation: blink 1.5s infinite; }

        /* Charging Box Style */
        .charging-checkbox { display: none; }
        .charging-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background-color: #ffffff;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-sub);
            font-weight: 500;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .charging-box:hover {
            border-color: #22c55e;
            background-color: #f0fdf4;
        }
        .charging-checkbox:checked + .charging-box {
            background-color: #dcfce7 !important; 
            border-color: #22c55e !important;     
            color: #15803d !important;             
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(34, 197, 94, 0.2);
        }
        
        body.dark-mode .charging-box { background-color: #334155; color: #cbd5e1; }
        body.dark-mode .charging-checkbox:checked + .charging-box { background-color: rgba(34, 197, 94, 0.25) !important; border-color: #4ade80 !important; color: #4ade80 !important; }
    </style>
</head>
<body class="overflow-x-hidden">
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        
        <div class="container-fluid p-4 flex-grow-1">
            
            <?php if ($activeBooking): ?>
                <h3 class="fw-bold text-dark mb-4"><i class="fas fa-steering-wheel me-2 text-success"></i>สถานะการใช้งาน</h3>
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="booking-card">
                            <div class="header-bg active-mode text-center">
                                <h4 class="fw-bold m-0"><i class="fas fa-check-circle me-2"></i>กำลังใช้งานรถ</h4>
                                <small class="opacity-75">คืนรถเมื่อใช้งานเสร็จสิ้น</small>
                            </div>
                            <div class="p-4 p-md-5">
                                <div class="text-center mb-4">
                                    <div class="mx-auto mb-3 car-img-thumb" style="width: 200px; height: 140px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                                        <?php if($activeBooking['car_image']): ?>
                                            <img src="uploads/cars/<?php echo $activeBooking['car_image']; ?>">
                                        <?php else: ?>
                                            <div class="text-muted"><i class="fas fa-car fa-3x"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <h2 class="fw-bold text-dark mb-1"><?php echo $activeBooking['car_name']; ?></h2>
                                    <div class="d-flex justify-content-center gap-2">
                                        <span class="badge bg-secondary"><?php echo $activeBooking['plate']; ?></span>
                                        <?php if($activeBooking['type']=='EV'): ?>
                                            <span class="badge bg-primary"><i class="fas fa-bolt"></i> EV</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-gas-pump"></i> น้ำมัน</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card bg-light border-0 rounded-4 p-3 mb-4" style="background-color: var(--bg-body) !important; color: var(--text-main);">
                                    <div class="row g-3">
                                        <div class="col-6"><small class="text-muted d-block">เริ่ม</small><strong class="text-dark fs-5"><?php echo getThaiDate(date('Y-m-d', strtotime($activeBooking['start_date']))); ?> <?php echo date('H:i', strtotime($activeBooking['start_date'])); ?> น.</strong></div>
                                        <div class="col-6"><small class="text-muted d-block">กำหนดคืน</small><strong class="text-danger fs-5"><?php echo getThaiDate(date('Y-m-d', strtotime($activeBooking['end_date']))); ?> <?php echo date('H:i', strtotime($activeBooking['end_date'])); ?> น.</strong></div>
                                        
                                        <div class="col-12 border-top pt-3 mt-2" style="border-color: var(--border-color) !important;">
                                            <div class="d-flex align-items-start gap-2 mb-2">
                                                <i class="fas fa-map-marker-alt text-danger mt-1"></i>
                                                <div>
                                                    <small class="text-muted d-block" style="line-height: 1;">สถานที่</small>
                                                    <strong class="text-dark fs-5"><?php echo $activeBooking['destination']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 border-top pt-3" style="border-color: var(--border-color) !important;">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fas fa-tasks text-info mt-1"></i>
                                                <div>
                                                    <small class="text-muted d-block" style="line-height: 1;">ภารกิจ</small>
                                                    <strong class="text-dark"><?php echo $activeBooking['reason']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-danger w-100 py-3 rounded-3 fw-bold fs-5 shadow-sm" data-bs-toggle="modal" data-bs-target="#returnModal">
                                    <i class="fas fa-undo-alt me-2"></i> คืนรถ
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="returnModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content border-0">
                            <div class="modal-header bg-dark">
                                <h5 class="modal-title fw-bold text-white">แบบฟอร์มคืนรถ</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body p-4">
                                    <input type="hidden" name="action" value="return_car">
                                    <input type="hidden" name="booking_id" value="<?php echo $activeBooking['id']; ?>">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold text-dark"><i class="fas fa-map-marker-alt me-2 text-danger"></i>จอดรถไว้ที่ไหน?</label>
                                        <input type="text" name="parking_location" class="form-control form-control-lg" placeholder="ระบุตำแหน่งจอดให้ชัดเจน" required>
                                    </div>
                                    <div class="mb-4">
                                        <?php if($activeBooking['type'] == 'EV'): ?>
                                            <label class="form-label fw-bold text-dark"><i class="fas fa-charging-station me-2 text-success"></i>แบตเตอรี่คงเหลือ (%)</label>
                                            <div class="ev-input-box">
                                                <input type="number" name="energy_level" min="0" max="100" placeholder="0" required>
                                                <span class="fs-4 fw-bold text-success">%</span>
                                            </div>

                                            <div class="mt-4">
                                                <input type="checkbox" name="is_charging" id="is_charging" value="1" class="charging-checkbox">
                                                <label for="is_charging" class="charging-box">
                                                    <i class="fas fa-plug fa-lg"></i>
                                                    <span class="fs-5">เสียบสายชาร์จทิ้งไว้</span>
                                                </label>
                                            </div>

                                        <?php else: ?>
                                            <label class="form-label fw-bold text-dark"><i class="fas fa-gas-pump me-2 text-warning"></i>ปริมาณน้ำมันคงเหลือ</label>
                                            <div class="row g-2">
                                                <div class="col"><input type="radio" name="energy_level" id="fuel_e" value="Empty" class="fuel-option" required><label for="fuel_e" class="fuel-label"><i class="fas fa-gas-pump"></i>แดง</label></div>
                                                <div class="col"><input type="radio" name="energy_level" id="fuel_1_4" value="1/4" class="fuel-option"><label for="fuel_1_4" class="fuel-label"><i class="fas fa-battery-quarter"></i>1/4</label></div>
                                                <div class="col"><input type="radio" name="energy_level" id="fuel_1_2" value="1/2" class="fuel-option"><label for="fuel_1_2" class="fuel-label"><i class="fas fa-battery-half"></i>1/2</label></div>
                                                <div class="col"><input type="radio" name="energy_level" id="fuel_3_4" value="3/4" class="fuel-option"><label for="fuel_3_4" class="fuel-label"><i class="fas fa-battery-three-quarters"></i>3/4</label></div>
                                                <div class="col"><input type="radio" name="energy_level" id="fuel_f" value="Full" class="fuel-option"><label for="fuel_f" class="fuel-label"><i class="fas fa-battery-full"></i>เต็ม</label></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark">หมายเหตุ / ปัญหาที่พบ (ถ้ามี)</label>
                                        <textarea name="car_issue" class="form-control" rows="2" placeholder="เช่น ยางแบน, มีรอยขีดข่วนใหม่"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                    <button type="submit" class="btn btn-success fw-bold px-4 py-2">ยืนยันคืนรถ (บันทึกเวลาปัจจุบัน)</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <h3 class="fw-bold text-dark mb-4"><i class="fas fa-calendar-day me-2 text-primary"></i>จองรถ</h3>
                <form method="POST" id="bookingForm" onsubmit="return validateForm()">
                    <input type="hidden" name="action" value="book_car">
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="booking-card h-100">
                                <div class="header-bg"><h5 class="fw-bold m-0"><i class="far fa-clock me-2"></i>รายละเอียดการเดินทาง</h5></div>
                                <div class="p-4">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold text-primary"><i class="fas fa-phone-alt me-2"></i>เบอร์โทรศัพท์ติดต่อ (จำเป็น)</label>
                                        <input type="tel" name="phone_number" class="form-control form-control-lg border-primary border-opacity-25" 
                                               placeholder="0xx-xxx-xxxx" 
                                               value="<?php echo htmlspecialchars($user_phone); ?>" required>
                                        <small class="text-muted" style="font-size: 0.8rem;">* ระบบจะจำเบอร์นี้ไว้สำหรับการจองครั้งถัดไป</small>
                                    </div>
                                    <hr class="opacity-25 my-4" style="border-color: var(--text-main);">
                                    
                                    <div class="datetime-group shadow-sm">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="fw-bold text-primary"><i class="fas fa-plane-departure me-2"></i>เริ่มต้นใช้งาน</label>
                                            <span class="badge bg-primary bg-opacity-10 text-primary cursor-pointer border border-primary px-2" onclick="setNow()">
                                                <i class="fas fa-history me-1"></i> เดี๋ยวนี้
                                            </span>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-7"><input type="text" name="start_date" id="start_date" value="<?php echo $today; ?>" required onchange="updateMinEndDate()"></div>
                                            <div class="col-5"><input type="text" name="start_time" id="start_time" value="<?php echo $now_time; ?>" required></div>
                                        </div>
                                    </div>

                                    <div class="datetime-group shadow-sm" style="border-color: var(--bs-danger-border-subtle);">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="fw-bold text-danger"><i class="fas fa-plane-arrival me-2"></i>สิ้นสุด / คืนรถ</label>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-7"><input type="text" name="end_date" id="end_date" value="<?php echo $today; ?>" required></div>
                                            <div class="col-5"><input type="text" name="end_time" id="end_time" value="18:00" required></div>
                                        </div>
                                    </div>

                                    <hr class="opacity-25 my-4" style="border-color: var(--text-main);">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold"><i class="fas fa-map-marker-alt me-2 text-secondary"></i>ไปที่ไหน (สถานที่)</label>
                                        <input type="text" name="destination" class="form-control form-control-lg" placeholder="ระบุอำเภอ / จังหวัด" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold"><i class="fas fa-comment-alt me-2 text-secondary"></i>ภารกิจ / เหตุผล</label>
                                        <textarea name="reason" class="form-control" rows="3" placeholder="รายละเอียดเพิ่มเติม..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-8">
                            <div class="booking-card h-100">
                                <div class="header-bg bg-white text-dark border-bottom"><h5 class="fw-bold m-0 text-dark"><i class="fas fa-car me-2"></i>เลือกรถที่ต้องการ</h5></div>
                                <div class="p-4">
                                    <input type="hidden" name="car_id" id="selected_car_id" required>
                                    <div class="row g-3">
                                        <?php foreach ($cars as $car):
                                            $isBusy = !empty($car['busy_user_id']); 
                                            $statusClass = $isBusy ? 'busy' : '';
                                            
                                            if ($isBusy) {
                                                $statusLabel = '<span class="badge bg-danger position-absolute top-0 end-0 m-2 shadow-sm"><i class="fas fa-user-lock me-1"></i> ไม่ว่าง</span>';
                                                
                                                $alertMsg = "รถคันนี้กำลังถูกใช้งานโดย {$car['busy_user_name']}\\nไปที่: {$car['busy_dest']}\\nเบอร์โทร: {$car['busy_user_phone']}";
                                                $clickAction = "onclick=\"Swal.fire({title:'รถไม่ว่าง', html:'".addslashes($alertMsg)."', icon:'warning', background: (document.body.classList.contains('dark-mode') || localStorage.getItem('tjc_theme') === 'dark') ? '#1e293b' : '#fff', color: (document.body.classList.contains('dark-mode') || localStorage.getItem('tjc_theme') === 'dark') ? '#fff' : '#000'})\"";
                                            } else {
                                                $statusLabel = '<span class="badge bg-success position-absolute top-0 end-0 m-2 shadow-sm">ว่าง</span>';
                                                $clickAction = "onclick=\"selectCar(this, {$car['id']})\"";
                                            }
                                            
                                            $last_location = "-"; $last_energy = "-"; $last_issue = "";
                                            $is_charging_status = false; 
                                            
                                            if (!empty($car['last_info'])) {
                                                $parts = explode('|', $car['last_info']);
                                                foreach ($parts as $p) {
                                                    if (strpos($p, 'จอดที่')!==false) $last_location = trim(explode(':', $p)[1] ?? '-');
                                                    if (strpos($p, 'พลังงาน')!==false) $last_energy = trim(explode(':', $p)[1] ?? '-');
                                                    if (strpos($p, 'หมายเหตุ')!==false) $last_issue = trim(explode(':', $p)[1] ?? '-');
                                                    
                                                    if (strpos($p, 'เสียบชาร์จอยู่')!==false) $is_charging_status = true;
                                                }
                                            }

                                            // ส่วนที่แปลงค่าเป็นภาษาไทยสำหรับแสดงผล
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
                                            
                                            // เปลี่ยนสีไอคอนตามระดับน้ำมัน
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
                                            <div class="col-md-6 col-xl-6">
                                                <div class="car-select-item <?php echo $statusClass; ?>" <?php echo $clickAction; ?>>
                                                    <?php echo $statusLabel; ?>
                                                    <div class="d-flex w-100 gap-3">
                                                        <div class="car-img-thumb">
                                                            <?php if($car['car_image']): ?><img src="uploads/cars/<?php echo $car['car_image']; ?>"><?php else: ?><div class="text-muted"><i class="fas fa-car fa-2x"></i></div><?php endif; ?>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-bold text-dark fs-5"><?php echo $car['name']; ?></div>
                                                            <div class="text-secondary small mb-2"><?php echo $car['plate']; ?></div>
                                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                                <?php if($car['type'] == 'EV'): ?><span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2">EV</span><?php else: ?><span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2">Fuel</span><?php endif; ?>
                                                            </div>
                                                            
                                                            <?php if ($isBusy): ?>
                                                                <div class="p-2 rounded border border-danger bg-danger bg-opacity-10 small text-danger mt-2">
                                                                    <div class="fw-bold"><i class="fas fa-user me-1"></i> <?php echo $car['busy_user_name']; ?></div>
                                                                    <div><i class="fas fa-phone me-1"></i> <?php echo $car['busy_user_phone']; ?></div>
                                                                    <div class="text-truncate"><i class="fas fa-map-marker-alt me-1"></i> <?php echo $car['busy_dest']; ?></div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="p-2 rounded border small text-secondary mt-2" style="background-color: var(--bg-body); border-color: var(--border-color) !important;">
                                                                    <div class="d-flex align-items-center mb-1"><i class="fas fa-map-marker-alt text-danger me-2" style="width:15px;"></i> <span class="text-truncate" style="max-width: 120px;">จอดที่ : <?php echo $last_location; ?></span></div>
                                                                    
                                                                    <div class="d-flex align-items-center">
                                                                        <i class="fas <?php echo $energyIcon; ?> me-2" style="width:15px;"></i> 
                                                                        <span>
                                                                            <?php echo $energyLabel; ?> : <?php echo $last_energy; ?><?php echo $energyUnit; ?>
                                                                            
                                                                            <?php if($is_charging_status): ?>
                                                                                <span class="badge bg-success ms-1 blink-animation">
                                                                                    <i class="fas fa-bolt"></i> ชาร์จอยู่
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </span>
                                                                    </div>

                                                                    <?php if($last_issue && $last_issue != '-'): ?>
                                                                        <div class="d-flex align-items-center mt-1 text-warning">
                                                                            <i class="fas fa-exclamation-circle me-2" style="width:15px;"></i>
                                                                            <span class="text-truncate" style="max-width: 140px;"><?php echo $last_issue; ?></span>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-5 pt-3 border-top" style="border-color: var(--border-color) !important;">
                                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 fw-bold shadow-sm fs-5"><i class="fas fa-check-circle me-2"></i> ยืนยันการจอง</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const body = document.body;
            const savedTheme = localStorage.getItem('tjc_theme') || 'light';
            if (savedTheme === 'dark') body.classList.add('dark-mode');
            
            const themeToggle = document.getElementById('theme-toggle');
            if(themeToggle) {
                themeToggle.addEventListener('click', () => {
                    // Sidebar handles toggle
                });
            }

            // --- เรียกใช้ Flatpickr เพื่อเปลี่ยนการแสดงผลในช่อง Input เป็นไทย ---
            
            // 1. ตั้งค่าช่องวันที่
            const dateConfig = {
                locale: "th",
                dateFormat: "Y-m-d", // รูปแบบที่ส่งไป PHP
                altInput: true,      // เปิดโหมดแสดงผลแยก
                altFormat: "d/m/Y",  // รูปแบบที่แสดงให้ user เห็น
                disableMobile: true
            };
            
            flatpickr("#start_date", { 
                ...dateConfig, 
                onChange: updateMinEndDate 
            });
            
            flatpickr("#end_date", dateConfig);

            // 2. ตั้งค่าช่องเวลา
            const timeConfig = {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i น.", 
                time_24hr: true,
                disableMobile: true,
                allowInput: true 
            };

            flatpickr("#start_time", timeConfig);
            flatpickr("#end_time", timeConfig);
        });

        function selectCar(el, id) {
            document.querySelectorAll('.car-select-item').forEach(i => i.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('selected_car_id').value = id;
        }

        function setNow() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            if(document.querySelector("#start_date")._flatpickr) {
                document.querySelector("#start_date")._flatpickr.setDate(`${year}-${month}-${day}`);
                document.querySelector("#start_time")._flatpickr.setDate(`${hours}:${minutes}`);
            } else {
                document.getElementById('start_date').value = `${year}-${month}-${day}`;
                document.getElementById('start_time').value = `${hours}:${minutes}`;
            }
            updateMinEndDate();
        }

        function updateMinEndDate() {
            const startPicker = document.querySelector("#start_date")._flatpickr;
            const endPicker = document.querySelector("#end_date")._flatpickr;
            
            if (startPicker && endPicker) {
                const startDateStr = startPicker.input.value; 
                endPicker.set('minDate', startDateStr);
                
                if (endPicker.input.value < startDateStr) {
                    endPicker.setDate(startDateStr);
                }
            }
        }

        function validateForm() {
            const carId = document.getElementById('selected_car_id').value;
            const isDark = document.body.classList.contains('dark-mode') || localStorage.getItem('tjc_theme') === 'dark';
            
            if(!carId) {
                Swal.fire({
                    title: 'แจ้งเตือน', 
                    text: 'กรุณาแตะเลือกรถที่ต้องการก่อนครับ', 
                    icon: 'warning',
                    background: isDark ? '#1e293b' : '#ffffff', 
                    color: isDark ? '#ffffff' : '#1e293b'
                });
                return false;
            }

            const startD = document.getElementById('start_date').value;
            let startT = document.getElementById('start_time').value.replace(' น.', '');
            const endD = document.getElementById('end_date').value;
            let endT = document.getElementById('end_time').value.replace(' น.', '');
            
            const startDateTime = new Date(`${startD}T${startT}`);
            const endDateTime = new Date(`${endD}T${endT}`);

            if (endDateTime <= startDateTime) {
                Swal.fire({
                    title: 'เวลาไม่ถูกต้อง', 
                    text: 'เวลาคืนรถ ต้องหลังจากเวลารับรถครับ', 
                    icon: 'warning',
                    background: isDark ? '#1e293b' : '#ffffff', 
                    color: isDark ? '#ffffff' : '#1e293b'
                });
                return false;
            }
            return true;
        }
    </script>
</body>
</html>