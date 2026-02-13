<?php
// fm_jobs.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once 'db_connect.php'; 
// --- 🔴 ส่วนที่ 0: ตรวจสอบการล็อกอิน ---
if (!isset($_SESSION['user_id'])) {
    // ถ้าไม่มีให้ Redirect ไปหน้า Login
    header("Location: login.php");
    exit(); // หยุดการทำงานของสคริปต์ที่เหลือทันที
}
// --- 🟢 ส่วนที่ 1: ดึงสิทธิ์การใช้งานปุ่มจาก Database ---
$my_role = $_SESSION['role'] ?? ''; 
$allowed_actions = [];

if ($my_role === 'admin') {
    // Admin ผ่านตลอด
} else {
    $sql = "SELECT DISTINCT a.action_code 
            FROM master_actions a
            JOIN permissions p ON a.page_id = p.page_id
            WHERE p.role_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $my_role);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allowed_actions[] = $row['action_code'];
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>ตารางเดินรถ | Fleet Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" href="images/LOgoTJC.png" type="images/LOgoTJC.png">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>


<style>
    /* --- CSS Variables & Reset --- */
    :root { 
        --primary: #3b82f6; --primary-hover: #2563eb; --primary-light: #eff6ff;
        --success: #10b981; --success-bg: #d1fae5;
        --warning: #f59e0b; --warning-bg: #fef3c7;
        --danger: #ef4444; --danger-bg: #fee2e2;
        --info: #0ea5e9; --info-bg: #e0f2fe;
        --text-main: #1e293b; --text-sub: #64748b;
        --bg-body: #f1f5f9; --bg-card: #ffffff; --bg-input: #f8fafc; --bg-hover: #f8fafc;
        --border-color: #e2e8f0; --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        --header-bg: #f8fafc; --driver-cell-bg: #ffffff; --modal-bg: #ffffff;
        --fp-bg: #ffffff; --fp-text: #1e293b; --fp-accent: #2563eb;
    }
    
    body.dark-mode {
        --text-main: #f1f5f9; --text-sub: #94a3b8;
        --bg-body: #0f172a; --bg-card: #1e293b; --bg-input: #334155; --bg-hover: #334155;
        --border-color: #334155; --header-bg: #1e293b; --driver-cell-bg: #1e293b; --modal-bg: #1e293b;
        --primary-light: rgba(59, 130, 246, 0.1);
        --success-bg: rgba(16, 185, 129, 0.1); --warning-bg: rgba(245, 158, 11, 0.1);
        --danger-bg: rgba(239, 68, 68, 0.1); --info-bg: rgba(14, 165, 233, 0.1);
        --fp-bg: #1e293b; --fp-text: #f8fafc; --fp-accent: #60a5fa;
    }

    * { box-sizing: border-box; font-family: 'Prompt', sans-serif; }
    html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: var(--bg-body); color: var(--text-main); }

    /* --- Layout --- */
    .page-container { position: absolute; top: 0; right: 0; bottom: 0; left: 250px; padding: 20px; overflow-y: auto; overflow-x: hidden; transition: 0.3s; }
    @media(max-width: 768px) { .page-container { left: 0; width: 100%; padding: 10px; padding-top: 70px; } }
.flatpickr-calendar { background: var(--fp-bg) !important; color: var(--fp-text) !important; border: 1px solid var(--border-color) !important; box-shadow: 0 10px 20px rgba(0,0,0,0.2) !important; }
    .flatpickr-day, .flatpickr-time input, .flatpickr-month, .flatpickr-current-month, .flatpickr-weekday { color: var(--fp-text) !important; }
    .flatpickr-day.selected { background: var(--fp-accent) !important; border-color: var(--fp-accent) !important; color: #fff !important; }
    /* --- Header --- */
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; background: var(--bg-card); padding: 15px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow); margin-bottom: 20px; }
    .header-title h1 { margin: 0; font-size: 28px; color: var(--primary); display: flex; align-items: center; gap: 10px; }
    .header-tools { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .mobile-menu-btn { display: none; background: none; border: none; font-size: 24px; color: var(--text-main); }

    /* --- Inputs & Buttons --- */
    .form-control-filter { padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-input); color: var(--text-main); font-size: 14px; outline: none; }
    .btn { padding: 8px 16px; border-radius: 6px; border: 1px solid transparent; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; font-size: 14px; transition: 0.2s; white-space: nowrap; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-outline { background: transparent; border-color: var(--border-color); color: var(--text-main); }
    .btn-success { background: var(--success); color: white; }
    .btn-danger-soft { background: var(--danger-bg); color: var(--danger); }
    .btn:hover { opacity: 0.9; transform: translateY(-1px); }

    /* --- Schedule Table --- */
    .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--bg-card); border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow); }
    .schedule-table th { background: var(--header-bg); padding: 15px; text-align: left; color: var(--text-sub); border-bottom: 1px solid var(--border-color); font-weight: 600; }
    .driver-cell { width: 260px; min-width: 260px; padding: 20px; vertical-align: top; border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); background: var(--driver-cell-bg); }
    .jobs-cell { padding: 15px; vertical-align: top; border-bottom: 1px solid var(--border-color); background: var(--bg-card); }
    
    .driver-name { font-weight: 600; font-size: 16px; color: var(--text-main); display: block; }
    .driver-badge { font-size: 14px; padding: 2px 6px; border-radius: 4px; background: var(--bg-input); color: var(--text-sub); border: 1px solid var(--border-color); margin-top: 4px; display: inline-block; }
    .driver-car { font-size: 16px; color: var(--info); margin-top: 8px; display: flex; align-items: center; gap: 5px; }
    .add-job-mini { margin-top: 10px; width: 100%; padding: 6px; border: 1px dashed var(--border-color); background: transparent; color: var(--text-sub); border-radius: 6px; cursor: pointer; font-size: 12px; transition: 0.2s; }
    .add-job-mini:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

    /* --- Job Card --- */
    .jobs-list { display: flex; flex-direction: column; gap: 10px; }
    .job-card { background: var(--bg-card); border: 1px solid var(--border-color); border-left: 4px solid var(--text-sub); border-radius: 8px; padding: 12px; position: relative; transition: 0.2s; cursor: pointer; }
    .job-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .job-card.selected { border-color: var(--primary); background: var(--primary-light); }

    .status-pending { border-left-color: var(--warning); }
    .status-in_progress { border-left-color: var(--info); }
    .status-completed { border-left-color: var(--success); opacity: 0.8; }
    .status-failed { border-left-color: var(--danger); }

    .job-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
    .job-time { font-weight: 700; font-size: 14px; color: var(--primary); display: flex; align-items: center; gap: 6px; }
    
    .job-actions { opacity: 0; transition: 0.2s; display: flex; gap: 5px; }
    .job-card:hover .job-actions { opacity: 1; }
    @media(max-width: 768px) { .job-actions { opacity: 1; } }

    .action-icon { width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: var(--bg-input); color: var(--text-sub); font-size: 12px; transition: 0.2s; border: 1px solid var(--border-color); cursor: pointer; }
    .action-icon:hover { background: var(--bg-body); color: var(--text-main); border-color: var(--text-sub); }

    .job-title { font-weight: 600; font-size: 14px; color: var(--text-main); margin-bottom: 4px; line-height: 1.4; }
    .job-meta { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
    .meta-badge { font-size: 14px; padding: 2px 6px; border-radius: 4px; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-sub); display: flex; align-items: center; gap: 4px; }

    /* --- Modals & Autocomplete --- */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: none; justify-content: center; align-items: center; backdrop-filter: blur(2px); }
    .modal-overlay.show { display: flex; animation: fadeIn 0.2s; }
    .modal-content { background: var(--modal-bg); width: 100%; max-width: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; max-height: 90vh; display: flex; flex-direction: column; }
    .modal-header { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--header-bg); }
    .modal-header h3 { margin: 0; font-size: 18px; color: var(--text-main); }
    .modal-body { padding: 20px; overflow-y: auto; color: var(--text-main); }

    .form-group { margin-bottom: 15px; }
    .form-label { display: block; margin-bottom: 5px; font-size: 13px; color: var(--text-sub); }
    .form-input { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-main); font-size: 14px; outline: none; }
    .form-row { display: flex; gap: 10px; }

    .autocomplete-wrapper { position: relative; }
    .autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; border: 1px solid var(--border-color); border-top: none; z-index: 999; background: var(--bg-card); border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; }
    .autocomplete-item { padding: 10px; cursor: pointer; font-size: 13px; border-bottom: 1px solid var(--border-color); color: var(--text-main); display: flex; justify-content: space-between; }
    .autocomplete-item:hover { background: var(--primary-light); }
    .region-badge { font-size: 14px; background: var(--info-bg); color: var(--info); padding: 2px 6px; border-radius: 4px; }

.floating-footer { 
    position: fixed; 
    bottom: 20px; 
    left: 50%; 
    transform: translateX(-50%); 
    background: #1e293b; 
    color: white; 
    padding: 12px 25px; 
    border-radius: 50px; 
    display: none; /* ปล่อยให้ JS สั่งเปิด */
    align-items: center; 
    gap: 15px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
    z-index: 9999 !important; /* ดันขึ้นมาหน้าสุด */
    min-width: 280px;
}
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .check-select { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary); }
    /* --- Status Badges --- */
    .status-badge {
        font-size: 14px;
        padding: 3px 8px;
        border-radius: 50px;
        font-weight: 500;
        text-transform: uppercase;
    }
    .badge-pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
    .badge-in_progress { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
    .badge-completed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
    .badge-failed { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

    .finish-time {
        font-size: 14px;
        color: var(--success);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    /* --- SweetAlert2 Dark Mode Support --- */
    body.dark-mode .swal2-popup {
        background-color: var(--bg-card) !important;
        color: var(--text-main) !important;
    }
    body.dark-mode .swal2-title, 
    body.dark-mode .swal2-html-container {
        color: var(--text-main) !important;
    }
    body.dark-mode .swal2-footer {
        border-top: 1px solid var(--border-color);
        color: var(--text-sub);
    }
    body.dark-mode .swal2-close,
    body.dark-mode .swal2-timer-progress-bar {
        background: var(--primary);
    }


    /* ปรับปรุง Layout พื้นฐาน */
    .page-container { 
        position: absolute; 
        top: 0; right: 0; bottom: 0; left: 250px; 
        padding: 20px; 
        overflow-y: auto; 
        transition: 0.3s; 
    }

    /* ปรับปรุง Header ให้ยืดหยุ่น */
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    .header-tools {
        display: grid;
        grid-template-columns: 1fr 1fr; /* แบ่ง 2 คอลัมน์บนมือถือ */
        gap: 10px;
    }
    .header-tools .btn {
        justify-content: center;
    }

    /* --- 📱 จุดสำคัญ: ปรับปรุงตารางให้เป็น Responsive Card --- */
    @media(max-width: 768px) {
        .page-container { left: 0; width: 100%; padding: 10px; padding-top: 70px; }
        
        .header-tools {
            grid-template-columns: 1fr; /* เรียงแถวเดี่ยวบนจอเล็กมาก */
        }

        /* ซ่อนหัวตารางแบบดั้งเดิม */
        .schedule-table thead { display: none; }
        
        .schedule-table, .schedule-table tbody, .schedule-table tr, .schedule-table td {
            display: block;
            width: 100%;
        }

        .driver-row {
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-card);
            overflow: hidden;
        }

        .driver-cell {
            width: 100% !important;
            min-width: 100% !important;
            background: var(--header-bg);
            border-right: none;
            border-bottom: 1px solid var(--border-color);
            padding: 15px;
        }

        .jobs-cell {
            padding: 10px;
        }

        /* ปรับ Job Card ให้ใหญ่ขึ้นเพื่อให้อ่านง่ายบนมือถือ */
        .job-card {
            padding: 12px;
        }
        
        /* ปรับชื่อลูกค้าให้ใหญ่ขึ้นบนมือถือ */
        .job-card > div[style*="font-size: 18px"] {
            font-size: 16px !important;
            padding-left: 0 !important;
        }

        /* ปรับ Footer ปุ่มรวมบิลให้เต็มจอ */
        .floating-footer {
            width: 90%;
            justify-content: space-around;
            bottom: 10px;
        }
        
        /* ปรับ Modal ให้เต็มหน้าจอ */
        .modal-content {
            width: 95%;
            max-height: 95vh;
        }
        .form-row {
            flex-direction: column;
            gap: 0;
        }
    }
        /* ปรับขนาดการ์ดงานให้เล็กลง */
        .job-card {
            padding: 3px 12px !important; /* ลดระยะห่างภายในด้านบน-ล่าง และซ้าย-ขวา */
            margin-bottom: 6px !important; /* ลดช่องว่างระหว่างงานแต่ละงาน */
            border-radius: 6px !important; /* ปรับมุมให้โค้งน้อยลงเพื่อให้ดูคมและเล็กลง */
        }

        /* ปรับขนาดตัวอักษรของหัวข้อสถานะและเวลา */
        .job-header {
            font-size: 14px !important; 
            margin-bottom: 4px !important;
        }

        /* ปรับขนาดตัวอักษรรายละเอียดงาน (เช่น ชื่อลูกค้า/สถานที่) */
        .job-card div {
            font-size: 14px !important; /* ปรับขนาดตัวอักษรเนื้อหา */
            line-height: 1.5 !important; /* ลดระยะห่างระหว่างบรรทัด */
            height: auto !important;
        }
        /* จัดระยะห่างให้ปุ่ม Action Icon */
        .job-actions, .action-icon {
            margin: 2px;
        }

        /* ป้ายชื่อพนักงานไม่ให้เบียดกัน */
        .driver-cell span, .driver-cell div {
            line-height: 1.4 !important;
            margin-bottom: 4px;
        }

        /* ปรับขนาดปุ่มกดในรายการงาน */
        .btn-action-new {
            padding: 2px 6px !important;
            font-size: 13.8px !important;
        }

        /* ปรับช่องเลือกสถานะ (Select) ให้เล็กลง */
        select.form-control-sm {
            height: 28px !important;
            font-size: 14px !important;
            padding: 2px !important;
        }

        .floating-footer {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            z-index: 9999 !important; /* ต้องสูงกว่าอย่างอื่น */
            display: none;
            align-items: center;
            gap: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            pointer-events: auto !important; /* มั่นใจว่ากดได้ */
        }
                .action-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-input);
            color: var(--text-sub);
            font-size: 12px;
            transition: 0.2s;
            border: 1px solid var(--border-color);
            cursor: pointer;
        }
        .action-icon:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary);
        }
        .job-card {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .job-card:hover {
            filter: brightness(0.95);
            transform: translateY(-2px);
        }
        body.dark-mode .job-card:hover {
            filter: brightness(1.2);
        }
        /* ซ่อนรายการงานไว้เริ่มต้น */
        .jobs-row {
            display: none;
        }
        /* เมื่อมี class show ให้แสดงผล */
        .jobs-row.show {
            display: table-row;
        }
        /* สไตล์สำหรับแถวคนขับที่คลิกได้ */
        .driver-row {
            cursor: pointer;
            transition: background 0.2s;
        }
        .driver-row:hover {
            background: var(--primary-light) !important;
        }
        /* ลูกศรหมุน */
.chevron-icon { transition: transform 0.3s; float: right; margin-top: 5px;}
.driver-row.active .chevron-icon {   transform: rotate(180deg); }
/* แถวคนขับที่คลิกได้ */
.driver-header-row {cursor: pointer; transition: background 0.2s;  background: var(--bg-card); }
.driver-header-row:hover { background: var(--primary-light) !important; }
        /* ส่วนของเนื้อหางาน (เริ่มต้นให้ซ่อนไว้) */
.jobs-collapse-row {  display: none;}
.jobs-collapse-row.show { display: table-row; /* แสดงเมื่อมีการกด */}
        /* หมุนลูกศรเมื่อเปิด */
.chevron-icon {transition: transform 0.3s ease;color: var(--text-sub);}
.driver-header-row.active .chevron-icon {   transform: rotate(180deg);   color: var(--primary); }
        /* Badge นับจำนวนงาน */
.job-count-badge {  background: var(--primary);  color: white;   padding: 2px 8px;   border-radius: 20px;  font-size: 12px; margin-left: 10px;  }
.reorder-active { border: 2px dashed var(--primary) !important; background: var(--primary-light) !important; cursor: default !important;}
.reorder-active .action-icon:hover { background: var(--primary);color: white;}
/* ปรับให้แถวงานที่กางออกมาไม่มีเส้นแบ่งคอลัมน์และดูเป็นผืนเดียว */
.jobs-collapse-row.show { display: table-row;}
.jobs-full-width { padding: 15px !important; background: var(--bg-body); /* ใช้สีพื้นหลังที่ต่างออกไปเล็กน้อยเพื่อให้ดูว่าเป็นส่วนขยาย */}
/* เพิ่ม Effect เล็กน้อยตอนกางออก */
@keyframes slideDown {
from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); }}
.jobs-list {animation: slideDown 0.3s ease-out;}
/* สไตล์ตอนที่กำลังลาก */
.sortable-ghost { opacity: 0.4;  background-color: var(--primary-light) !important;}
.sortable-drag {  background: var(--bg-card) !important; box-shadow: 0 10px 20px rgba(0,0,0,0.2);}
/* จุดจับสำหรับลาก (Handle) */
.drag-handle {cursor: grab; padding: 10px; color: var(--text-sub);display: none; /* ซ่อนไว้ก่อน จะเปิดเมื่อกดปุ่มจัดลำดับ */}
.reorder-active .drag-handle {display: block;}
/* =========================================================
   🔥 ปรับ Modal ให้ยืดตามเนื้อหา และเลื่อนทั้งหน้า (Overlay Scroll)
   ========================================================= */

/* 1. ฉากหลัง (Overlay): อนุญาตให้ Scroll ได้ และจัดตำแหน่งใหม่ */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 2000;
    
    /* 🔥 จุดสำคัญ: */
    overflow-y: auto !important;       /* เปิดให้ Scroll ที่ฉากหลัง */
    align-items: flex-start !important; /* ให้กล่องเริ่มจากด้านบน (ไม่ใช่กึ่งกลาง) จะได้ไม่ตกขอบ */
    padding: 30px 10px;                /* เว้นระยะขอบบน-ล่างให้หายใจสะดวก */
    backdrop-filter: blur(2px);
}

/* 2. ตัวกล่อง (Content): ปลดล็อคความสูง */
.modal-content {
    background: var(--modal-bg);
    width: 100%;
    max-width: 550px;                  /* คงความกว้างไว้เท่าเดิม หรือปรับตามใจชอบ */
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
    
    /* 🔥 จุดสำคัญ: */
    margin: 0 auto;                    /* จัดกึ่งกลางแนวนอน */
    height: auto !important;           /* ให้สูงตามเนื้อหาที่ใส่ */
    max-height: none !important;       /* ยกเลิกการจำกัดความสูง (90vh เดิม) */
    overflow: visible !important;      /* ปิด Scrollbar ภายในกล่อง */
    display: block !important;         /* ยกเลิก flex เดิมเพื่อให้ยืดตามธรรมชาติ */
}

/* 3. เนื้อหาภายใน (Body): ปลดล็อคเช่นกัน */
.modal-body {
    padding: 20px;
    overflow: visible !important;      /* ปิด Scrollbar ภายใน */
    height: auto !important;           /* สูงตามเนื้อหา */
}

/* (ถ้ามี) ส่วนหัว */
.modal-header {
    border-bottom: 1px solid var(--border-color);
    padding: 15px 20px;
    border-radius: 12px 12px 0 0;
}

</style>
</head>
<body>


    <?php include 'sidebar.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
    <div id="sidebarOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:900;" onclick="toggleSidebar()"></div>

    <div class="page-container" id="mainApp">
        <div class="page-header">
            <div class="header-title">
                <button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <h1>📅 ตารางเดินรถ</h1>
            </div>
            
            <div class="header-tools">
                <input type="text" id="searchInput" class="form-control-filter" placeholder="🔍 ค้นหา...">
                <input type="date" id="filterDate" class="form-control-filter" onchange="renderSchedule()">
                <select id="filterDriver" class="form-control-filter" onchange="renderSchedule()"><option value="">- พนักงานทั้งหมด -</option></select>
                <select id="filterStatus" class="form-control-filter" onchange="renderSchedule()">
                    <option value="all">สถานะ: ทั้งหมด</option>
                    <option value="pending">⏳ รอดำเนินการ</option>
                    <option value="in_progress">🚚 กำลังส่ง</option>
                    <option value="completed">✅ เสร็จสิ้น</option>
                    <option value="failed">❌ ล้มเหลว</option>
                </select>
                <button class="btn btn-outline" onclick="openGlobalFleetModal()" style="margin-left:15px; font-size: 14px; padding: 5px 12px; border-radius: 8px;">
            <i class="fas fa-cog"></i> ตั้งค่ารถประจำ
        </button>
                
               <script>
    if(hasAction(null, 'top_btn_fleet')) document.write('<button class="btn btn-outline" onclick="openFleetModal()"><i class="fas fa-car"></i> รถประจำ</button>');
    if(hasAction(null, 'top_btn_reorder')) document.write('<button class="btn btn-outline" onclick="toggleReorderMode()" id="btnReorder"><i class="fas fa-sort"></i> จัดลำดับ</button>');
    if(hasAction(null, 'top_btn_add')) document.write('<button class="btn btn-primary" onclick="openJobModal()"><i class="fas fa-plus"></i> เพิ่มงาน</button>');
</script>
<button class="btn btn-outline" onclick="toggleReorderMode()" id="btnReorder">
    <i class="fas fa-sort"></i> จัดลำดับ
</button>
            </div>
        </div>

        <div id="reorderPanel" style="display:none; background:var(--bg-card); padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid var(--border-color);">
            <h4 style="margin:0 0 10px 0; font-size:14px; color:var(--text-main);">🖱️ คลิกปุ่มลูกศรเพื่อเลื่อนลำดับพนักงาน</h4>
            <div id="reorderList" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
        </div>

        <div id="loading" style="text-align:center; padding:50px; color:var(--text-sub);">
            <i class="fas fa-circle-notch fa-spin fa-2x"></i><br>กำลังโหลดข้อมูล...
        </div>
        
        <table class="schedule-table" id="scheduleTable" style="display:none">
            <thead>
                <tr>
                    <th width="260">คนขับ / รถประจำ</th>
                    <th>รายการงาน</th>
                </tr>
            </thead>
            <tbody id="scheduleBody"></tbody>
        </table>

        <div id="floatingFooter" class="floating-footer" style="display:none; z-index:9999;">
        <span id="selectedCount" style="margin-right: 15px;">เลือก 0 รายการ</span>
        
        <button type="button" class="btn btn-primary" onclick="openGroupModal()">
            <i class="fas fa-boxes"></i> รวมบิล
        </button>
        
        <button type="button" class="btn" onclick="clearSelection()" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.3); color:white;">
            <i class="fas fa-times"></i> ยกเลิก
        </button>
    </div>

    <div class="modal-overlay" id="jobModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="jobModalTitle">เพิ่มงานใหม่</h3>
                <button onclick="closeModal('jobModal')" style="border:none;background:none;cursor:pointer;color:var(--text-main)"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="jobForm" onsubmit="handleSaveJob(event)">
                    <input type="hidden" name="id" id="jobId">
                    
                    <div class="form-group"><label class="form-label">ลูกค้า / หน้างาน *</label><input type="text" name="customer_name" id="jobCustomer" class="form-input" required></div>
            
                    <div class="form-row">
                        <div class="form-group autocomplete-wrapper" style="flex:1">
                            <label class="form-label">ต้นทาง (ไม่ระบุก็ได้)</label>
                            <input type="text" name="origin" id="jobOrigin" class="form-input" autocomplete="off" placeholder="เช่น กทม.">
                            <div id="provinceListOrigin" class="autocomplete-list"></div>
                        </div>

                        <div class="form-group autocomplete-wrapper" style="flex:1">
                            <label class="form-label">ปลายทาง *</label>
                            <input type="text" name="destination" id="jobDest" class="form-input" required autocomplete="off" placeholder="เช่น เชียงใหม่">
                            <div id="provinceListDest" class="autocomplete-list"></div>
                        </div>
                    </div>
                  
                    </div>
                    
                    <div class="form-group">
    <div class="d-flex justify-content-between">
        <label class="form-label">วัน-เวลาเริ่ม *</label>
        <span class="badge bg-primary cursor-pointer" onclick="setNow()" style="cursor:pointer">
            <i class="fas fa-history"></i> เดี๋ยวนี้
        </span>
    </div>
    <div class="form-row">
        <div style="flex:2"><input type="text" id="jobStartDate" class="form-input" required></div>
        <div style="flex:1"><input type="text" id="jobStartTime" class="form-input" required></div>
    </div>
    <input type="hidden" name="start_time" id="jobStart">
</div>
                    <div class="form-group"><label class="form-label">คนขับ *</label><select name="driver_id" id="jobDriver" class="form-input" onchange="autoSelectVehicle(this.value)" required></select></div>
                    <div class="form-row">
                        <div class="form-group" style="flex:1"><label class="form-label">รถที่ใช้</label><select name="vehicle_id" id="jobVehicle" class="form-input"></select></div>
                        <div class="form-group" style="flex:1"><label class="form-label">ผู้ช่วย</label><select name="assistant_id" id="jobAsst" class="form-input"><option value="">- ไม่มี -</option></select></div>
                    </div>
                    <div class="form-row">
                        <input type="hidden" name="actual_price" id="jobActual">
                        <div class="form-group" style="flex:1"><label class="form-label">รายจ่าย (ค่ารถร่วม)</label><input type="number" name="cost" id="jobCost" class="form-input"></div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">บันทึกข้อมูล</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="fleetModal">
        <div class="modal-content">
            <div class="modal-header"><h3>🚗 ตั้งค่ารถประจำ</h3><button onclick="closeModal('fleetModal')" style="border:none;background:none;color:var(--text-main)"><i class="fas fa-times"></i></button></div>
            <div class="modal-body" id="fleetBody"></div>
        </div>
    </div>

    <div class="modal-overlay" id="groupModal">
        <div class="modal-content">
            <div class="modal-header"><h3>📦 รวมบิล / เหมา</h3><button onclick="closeModal('groupModal')" style="border:none;background:none;color:var(--text-main)"><i class="fas fa-times"></i></button></div>
            <div class="modal-body">
                <form onsubmit="handleSaveGroup(event)">
                    <input type="hidden" id="groupId">
                    <div class="form-group"><label class="form-label">ชื่อบิล / กลุ่มงาน</label><input type="text" id="groupName" class="form-input" required placeholder="เช่น เหมา กทม."></div>
                    <div class="form-group"><label class="form-label">ยอดรวม (บาท)</label><input type="number" id="groupPrice" class="form-input" required></div>
                    <div class="form-group">
                        <label class="form-label">ประเภท</label>
                        <select id="groupType" class="form-input">
                            <option value="cost">⛽ รายจ่าย (Cost)</option>
                        </select>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-top:20px">
                        <button type="button" id="btnUngroup" class="btn btn-danger-soft" style="display:none" onclick="deleteGroup()">แยกกลุ่ม/ลบ</button>
                        <div style="margin-left:auto; display:flex; gap:10px;">
                            <button type="button" class="btn btn-outline" onclick="closeModal('groupModal')">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="completeModal">
        <div class="modal-content">
            <div class="modal-header"><h3>✅ ยืนยันส่งสำเร็จ</h3><button onclick="closeModal('completeModal')" style="border:none;background:none;color:var(--text-main)"><i class="fas fa-times"></i></button></div>
            <div class="modal-body">
                <form onsubmit="handleComplete(event)">
                    <input type="hidden" id="compJobId">
                    <div class="form-group"><label class="form-label">เวลาเสร็จสิ้น</label><input type="datetime-local" id="compTime" class="form-input" required></div>
                    <div class="form-group"><label class="form-label">รูปถ่ายหลักฐาน</label><input type="file" id="compFile" class="form-input" accept="image/*"></div>
                    <button type="submit" class="btn btn-success" style="width:100%; justify-content:center;">ยืนยัน</button>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="globalFleetModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3>⚙️ ตั้งค่ารถประจำตัวพนักงาน</h3>
            <button onclick="closeModal('globalFleetModal')" style="border:none;background:none;color:var(--text-main)"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">1. เลือกพนักงาน</label>
                <select id="fleetDriverSelect" class="form-input" onchange="loadCurrentVehicle(this.value)">
                    <option value="">- กรุณาเลือกพนักงาน -</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">2. เลือกรถที่จะกำหนดให้เป็นรถประจำ</label>
                <select id="fleetVehicleSelect" class="form-input">
                    <option value="">- ไม่มีรถประจำ -</option>
                </select>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="saveGlobalFleet()" style="width: 100%; justify-content: center; height: 45px;">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    // --- 🟢 ส่วนที่ 2: ระบบตรวจสอบสิทธิ์รายปุ่ม ---
    const allowedActions = <?php echo json_encode($allowed_actions); ?>;
    const userRole = "<?php echo $_SESSION['role'] ?? ''; ?>";

    function hasAction(job, actionCode) {
        if (userRole === 'admin') return true;
        if (!actionCode) return false;

        // base permission from DB-driven allowed actions
        const base = allowedActions.includes(actionCode);

        // when no job context is provided (top-level buttons), rely on base permission
        if (!job) return base;

        // normalize identifiers
        const myId = String(<?php echo json_encode($_SESSION['user_id'] ?? ''); ?>);
        const jobDriver = String(job.driver_id ?? '');
        const jobAssistant = String(job.assistant_id ?? '');
        const jobOwner = String(job.created_by ?? job.creator_id ?? job.user_id ?? '');

        // Business-rule overrides per action
        switch (actionCode) {
            case 'job_edit':
                if (base) return true;
                // allow driver/assistant/creator to edit their own job
                return myId && (myId === jobDriver || myId === jobAssistant || myId === jobOwner);
            case 'job_delete':
                if (base) return true;
                // allow creator to delete; allow managers to delete pending jobs
                if (myId && myId === jobOwner) return true;
                if (userRole === 'manager' && job.status === 'pending') return true;
                return false;
            case 'job_status':
                if (base) return true;
                // allow assigned driver/assistant to change status
                return myId && (myId === jobDriver || myId === jobAssistant);
            default:
                return base;
        }
    }

    // --- GLOBAL STATE ---
    // แก้ไขส่วน state ด้านบน
    let state = {
        drivers: [], vehicles: [], jobs: [], provinces: [],
        driverOrder: [], selectedJobs: [], reorderMode: false,
        expandedDrivers: [] // <--- เพิ่มตัวนี้เพื่อเก็บ ID คนที่กางอยู่
    };

    const PROOF_URL = 'uploads/proofs';

    let start_date_picker, start_time_picker;           
    // --- INITIALIZATION ---
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('filterDate').value = ''; 
        const savedOrder = localStorage.getItem('fm_driver_order');
        if(savedOrder) state.driverOrder = JSON.parse(savedOrder);
        const theme = localStorage.getItem('tjc_theme');
        if(theme === 'dark') document.body.classList.add('dark-mode');
        const searchBox = document.getElementById('searchInput');
        if (searchBox) searchBox.addEventListener('input', () => renderSchedule());

        fetchData();
        fetchProvinces();
        setupAutocomplete();
start_date_picker = flatpickr("#jobStartDate", {
        locale: "th",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d/m/Y",
        defaultDate: "today",
        disableMobile: true,
        onChange: combineDateTime
    });

    // ตั้งค่า Flatpickr สำหรับเวลา (24ชม.)
    start_time_picker = flatpickr("#jobStartTime", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i น.",
        time_24hr: true,
        defaultDate: new Date().getHours() + ":" + new Date().getMinutes(),
        disableMobile: true,
        onChange: combineDateTime
    });
});    
function combineDateTime() {
    const d = document.getElementById('jobStartDate').value;
    // ตัด " น." ออกจากค่าเวลาที่ Flatpickr แสดง
    let t = document.getElementById('jobStartTime').value.replace(' น.', '');
    
    if(d && t) {
        // นำไปใส่ใน Input ตัวจริง (hidden หรือตัวที่ชื่อ start_time ในฟอร์ม)
        const fullDateTime = `${d} ${t}:00`;
        // ถ้าคุณใช้ ID jobStart ในฟอร์มเดิม ให้ใส่บรรทัดนี้
        const targetInput = document.getElementById('jobStart');
        if(targetInput) targetInput.value = fullDateTime;
    }
}

function setNow() {
    const now = new Date();
    // อัปเดตตัวแปร Flatpickr ที่คุณประกาศ global ไว้
    if(start_date_picker && start_time_picker) {
        start_date_picker.setDate(now);
        start_time_picker.setDate(now);
        combineDateTime(); // รวมค่าใหม่ทันที
    }
}

    // --- UTILS ---
    function cleanFileName(src) {
        if (!src || src === 'null' || src === 'undefined') return '';
        let s = String(src);
        s = s.replace(/uploads\/proofs\//g, '').replace(/\\/g, '/'); 
        s = s.replace(/[\[\]"']/g, '');
        return s.trim();
    }

    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if(sidebar) {
            sidebar.classList.toggle('show');
            overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
        }
    }

    // --- API & DATA ---
   async function fetchData() {
    try {
        const res = await fetch('api_fm.php?action=fetch_schedule');
        const data = await res.json();
        
        state.drivers = data.drivers || []; 
        state.vehicles = data.vehicles || []; 
        state.jobs = data.jobs || [];
        
        // 🟢 เปลี่ยน: ใช้ลำดับจากการ Query (ORDER BY priority) เป็นตัวตั้งต้นเสมอ
        state.driverOrder = state.drivers.map(d => parseInt(d.id));
        
        renderFilters(); 
        renderSchedule();
        
        document.getElementById('loading').style.display = 'none';
        document.getElementById('scheduleTable').style.display = 'table';
    } catch (e) { console.error('Fetch Error:', e); }
}
    async function fetchProvinces() {
    try {
        const res = await fetch('api_fm.php?action=fetch_provinces');
        const data = await res.json();
        
        state.provinces = Array.isArray(data) ? data : [];
        console.log("โหลดจังหวัดสำเร็จ:", state.provinces.length, "รายการ");
    } catch (e) { 
        console.error("โหลดจังหวัดล้มเหลว:", e); 
    }
}
 function renderSchedule() {
    const tbody = document.getElementById('scheduleBody');
    tbody.innerHTML = '';
    const filterDate = document.getElementById('filterDate').value;
    const filterDriver = document.getElementById('filterDriver').value;
    const filterStatus = document.getElementById('filterStatus').value;
    const searchText = document.getElementById('searchInput').value.trim().toLowerCase();

    // 1. กรองข้อมูล (Logic เดิม)
    let activeJobs = state.jobs.filter(j => {
        const dateMatch = filterDate ? (j.start_time && j.start_time.startsWith(filterDate)) : true;
        const drvMatch = filterDriver ? (j.driver_id == filterDriver || j.assistant_id == filterDriver) : true;
        const statusMatch = filterStatus === 'all' ? true : j.status === filterStatus;
        let searchMatch = true;
        if (searchText) {
            const txtCustomer = (j.customer_name || '').toLowerCase();
            const txtDest = (j.destination || '').toLowerCase();
            searchMatch = txtCustomer.includes(searchText) || txtDest.includes(searchText);
        }
        return dateMatch && drvMatch && statusMatch && searchMatch;
    });

    // 2. เรียงลำดับพนักงาน
    state.driverOrder.forEach(driverId => {
        const driver = state.drivers.find(d => d.id == driverId);
        
        if(!driver || (filterDriver && driver.id != filterDriver)) return;
        
        const myJobs = activeJobs.filter(j => j.driver_id == driver.id || j.assistant_id == driver.id);
        if ((filterStatus !== 'all' || searchText !== '' || filterDate !== '') && myJobs.length === 0) return;

        const defVeh = state.vehicles.find(v => v.id == driver.default_vehicle_id);
        const vehText = defVeh ? `🚛 ${defVeh.plate_number}` : '- ไม่ระบุรถ -';
        const isExpanded = state.expandedDrivers.includes(parseInt(driver.id));
        // --- แถวที่ 1: Header สำหรับคลิกเปิด/ปิด ---
        // เพิ่ม data-id="${driver.id}" และตัวจับ drag-handle
let html = `
    <tr class="driver-header-row ${state.reorderMode ? 'active reorder-active' : ''} ${isExpanded ? 'active' : ''}" 
        data-id="${driver.id}" 
        onclick="${state.reorderMode ? '' : `toggleAccordion(this, 'row-${driver.id}')`}">
        <td colspan="2" style="padding: 12px 15px; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center;">
                    ${state.reorderMode ? '<div class="drag-handle"><i class="fas fa-grip-vertical"></i></div>' : ''}
                    <span style="font-weight: 700; font-size: 16px; color: var(--text-main);">${driver.name}</span>
                    <span style="margin-left: 10px; font-size: 13px; color: var(--info);">${vehText}</span>
                    <span class="job-count-badge">${myJobs.length} งาน</span>
                </div>
                ${state.reorderMode ? '<i class="fas fa-arrows-alt-v" style="color:var(--primary)"></i>' : '<i class="fas fa-chevron-down chevron-icon"></i>'}
            </div>
        </td>
    </tr>

    <tr class="jobs-collapse-row ${isExpanded ? 'show' : ''}" id="row-${driver.id}">
        <td class="driver-cell">
            <span class="driver-name">${driver.name}</span>
            <span class="driver-badge">${driver.category==='partner'?'รถร่วม':'ประจำ'}</span>
            <div class="driver-car">${vehText}</div>
            <button class="add-job-mini" onclick="openJobModal(${driver.id})">+ เพิ่มงาน</button>
        </td>
        <td class="jobs-cell">
            <div class="jobs-list">`;
            
        if(myJobs.length === 0) {
            html += `<div style="text-align:center; color:var(--text-sub); font-style:italic; padding:10px;">ไม่มีงาน</div>`;
        } else {
            myJobs.sort((a, b) => new Date(b.start_time) - new Date(a.start_time));
            let lastDate = ""; 
            myJobs.forEach(job => {
                const d = new Date(job.start_time);
                const currentDate = d.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
                const timeStr = d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', hour12: false });
                
                if (currentDate !== lastDate) {
                    html += `
                        <div style="position: sticky; top: -1px; z-index: 10; background: var(--bg-body); padding: 8px 0; margin-top: 5px; display: flex; align-items: center; width: 100%;">
                            <div style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 50px; font-size: 14px; font-weight: 700; white-space: nowrap;">
                                <i class="fas fa-calendar-day"></i> ${currentDate}
                            </div>
                            <div style="flex: 1; height: 1px; background: var(--border-color); margin-left: 10px;"></div>
                        </div>`;
                    lastDate = currentDate;
                }

                const statusLabels = {
                    'pending': { text: '⏳ รอดำเนินการ', color: 'var(--warning)', bg: 'rgba(245, 158, 11, 0.1)' },
                    'in_progress': { text: '🚚 กำลังส่ง', color: 'var(--info)', bg: 'rgba(59, 130, 246, 0.1)' },
                    'completed': { text: '✅ เสร็จสิ้น', color: 'var(--success)', bg: 'rgba(16, 185, 129, 0.1)' },
                    'failed': { text: '❌ ไม่สำเร็จ', color: 'var(--danger)', bg: 'rgba(239, 68, 68, 0.1)' }
                };
                const currentStatus = statusLabels[job.status] || { text: job.status, color: 'var(--text-sub)', bg: 'var(--bg-input)' };
                const isSelected = state.selectedJobs.includes(parseInt(job.id));

                html += `
                    <div class="job-card status-${job.status} ${isSelected?'selected':''}" onclick="viewJobDetails(${job.id})">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" class="check-select" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); toggleSelect(${job.id})">
                                <span style="font-size: 18px; font-weight: 700; color: var(--primary);"><i class="far fa-clock"></i> ${timeStr} น.</span>
                            </div>
                            <span style="padding: 3px 10px; border-radius: 50px; font-size: 14px; font-weight: 600; color: ${currentStatus.color}; background: ${currentStatus.bg}; border: 1px solid ${currentStatus.color}33;">
                                ${currentStatus.text}
                            </span>
                        </div>
                        <div style="margin-left: 26px;">
                            <div style="font-size: 16px; font-weight: 600; color: var(--text-main); line-height: 1.5; margin-bottom: 4px;">${job.customer_name}</div>
                            <div style="font-size: 16px; color: var(--text-sub); line-height: 1.4;"><i class="fas fa-map-marker-alt"></i> ${job.destination}</div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; margin-top: 5px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                            <div style="display: flex; gap: 5px;">
                                ${hasAction(job, 'job_edit') ? `<button class="action-icon" onclick="event.stopPropagation(); editJob(${job.id})"><i class="fas fa-pen"></i></button>` : ''}
                                ${hasAction(job, 'job_delete') ? `<button class="action-icon" onclick="event.stopPropagation(); deleteJob(${job.id})" style="color:var(--danger);"><i class="fas fa-trash"></i></button>` : ''}
                            </div>
                            ${hasAction(job, 'job_status') ? `
                                <select onchange="changeStatus(${job.id}, this.value)" onclick="event.stopPropagation()">
                                    <option value="pending" ${job.status=='pending'?'selected':''}>⏳ รอ</option>
                                    <option value="in_progress" ${job.status=='in_progress'?'selected':''}>🚚 ส่ง</option>
                                    <option value="failed" ${job.status=='failed'?'selected':''}>❌ พลาด</option>
                                    <option value="completed" ${job.status=='completed'?'selected':''}>✅ เสร็จ</option>
                                </select>` : ''}
                        </div>
                    </div>`;
            });
        }
        html += `</div></td></tr>`;
        tbody.innerHTML += html;
    });
}

    // เพิ่มตัวแปรสำหรับเก็บพนักงานที่เลือกชั่วคราว
let lastSelectedDriverId = null;

// 1. ฟังก์ชันเปิด Modal เพิ่มงาน (ปรับเวลา 24 ชม.)
function openJobModal(driverId = null) {
    const modal = document.getElementById('jobModal');
    const form = document.getElementById('jobForm');
    if (!modal || !form) return;

    lastSelectedDriverId = driverId;
    document.getElementById('jobId').value = ''; 
    form.reset();
    document.getElementById('jobModalTitle').innerText = 'เพิ่มงานใหม่';
    
    // --- 🟢 ส่วนที่ 1: ตั้งค่าเวลาปัจจุบันแบบ 24 ชม. สำหรับประเทศไทย ---
    const now = new Date();
    // ดึงเวลาท้องถิ่นโดยคำนวณจากชดเชยเวลา (Offset) เพื่อให้ได้ค่ามาตรฐาน ISO ที่เป็นเวลาไทย
    const localNow = new Date(now.getTime() - (now.getTimezoneOffset() * 60000));
    const localISOTime = localNow.toISOString().slice(0, 16); // ตัดเอาเฉพาะ YYYY-MM-DDTHH:mm
    document.getElementById('jobStart').value = localISOTime;

    const drv = document.getElementById('jobDriver');
    const ast = document.getElementById('jobAsst');
    const veh = document.getElementById('jobVehicle');
    
    drv.innerHTML = '<option value="">- เลือกคนขับ -</option>'; 
    ast.innerHTML = '<option value="">- ไม่มี -</option>';
    state.drivers.forEach(d => { 
        const o = `<option value="${d.id}">${d.name}</option>`; 
        drv.innerHTML += o; 
        ast.innerHTML += o; 
    });
    
    veh.innerHTML = '<option value="">- เลือกรถ -</option>';
    state.vehicles.forEach(v => { 
        veh.innerHTML += `<option value="${v.id}">${v.fleet_number ? 'เบอร์ ' + v.fleet_number : ''} ${v.plate_number}</option>`; 
    });
    
    if (driverId) { 
        drv.value = driverId; 
        autoSelectVehicle(driverId); 
    }
    modal.classList.add('show');
}

// 2. ฟังก์ชันแก้ไขงาน (ปรับเวลา 24 ชม.)
function editJob(id) {
    const job = state.jobs.find(j => j.id == id);
    if (!job || !hasAction(job, 'job_edit')) {
        return Swal.fire('ผิดพลาด', 'คุณไม่มีสิทธิ์แก้ไขงานนี้', 'error');
    }
    openJobModal(); 
    document.getElementById('jobId').value = job.id;
    document.getElementById('jobCustomer').value = job.customer_name;
    document.getElementById('jobOrigin').value = job.origin || ''; 
    document.getElementById('jobDest').value = job.destination;
    // บังคับฟอร์แมตวันที่ให้เป็น 24 ชม. สำหรับ input
    if (job.start_time) {
        document.getElementById('jobStart').value = job.start_time.replace(' ', 'T').substring(0, 16);
    }
    document.getElementById('jobDriver').value = job.driver_id;
    document.getElementById('jobVehicle').value = job.vehicle_id;
    document.getElementById('jobCost').value = job.cost;
}

// 3. ฟังก์ชัน Autocomplete (แก้ปัญหาจังหวัดไม่ขึ้น)
// 1. ฟังก์ชันดึงข้อมูลจังหวัดจาก Database (master_provinces)
async function fetchProvinces() {
    try {
        // เรียก API ไปหลังบ้าน
        const res = await fetch('api_fm.php?action=fetch_provinces');
        const data = await res.json();
        
        // เช็คข้อมูลว่ามาไหม ถ้ามาให้เก็บลงตัวแปร state.provinces
        state.provinces = Array.isArray(data) ? data : [];
        
        // Debug: ดูใน Console ว่าข้อมูลมาจริงไหม
        console.log("โหลดจังหวัดเรียบร้อย:", state.provinces.length, "รายการ");
    } catch (e) { 
        console.error("Province Error:", e); 
    }
}

// 1. ฟังก์ชันดึงข้อมูลจังหวัดจาก Database
async function fetchProvinces() {
    try {
        // เรียก API ไปที่ api_fm.php
        const res = await fetch('api_fm.php?action=fetch_provinces');
        const data = await res.json();
        
        // ตรวจสอบข้อมูลและเก็บลงตัวแปร Global
        state.provinces = Array.isArray(data) ? data : [];
        console.log("โหลดจังหวัดสำเร็จ:", state.provinces.length, "รายการ");
    } catch (e) { 
        console.error("โหลดจังหวัดล้มเหลว:", e); 
    }
}

// 2. ฟังก์ชันค้นหาและเลือกจังหวัด (Autocomplete)
function setupAutocomplete() {
    // ฟังก์ชันย่อยสำหรับผูกการทำงานกับ Input
    function attach(inputId, listId) {
        const inp = document.getElementById(inputId);
        const list = document.getElementById(listId);
        
        if (!inp || !list) return;

        // เมื่อพิมพ์ข้อความ
        inp.addEventListener("input", function() {
            const val = this.value.trim();
            list.innerHTML = ''; // ล้างรายการเก่า
            
            if (!val) { 
                list.style.display = "none"; 
                return; 
            }
            
            // กรองหาจังหวัดที่ตรงกับที่พิมพ์ (ใช้ field 'name_th')
            const matches = state.provinces.filter(p => p.name_th && p.name_th.includes(val));
            
            if (matches.length > 0) {
                list.style.display = "block";
                matches.forEach(p => {
                    const item = document.createElement("div");
                    item.className = "autocomplete-item";
                    item.style.padding = "10px";
                    item.style.cursor = "pointer";
                    item.innerHTML = `<span><i class="fas fa-map-marker-alt" style="color:var(--primary)"></i> ${p.name_th}</span>`;
                    
                    item.onclick = function() {
                        inp.value = p.name_th; // ใส่ค่าลงใน Input
                        list.style.display = "none"; // ปิดรายการ
                    };
                    list.appendChild(item);
                });
            } else {
                list.style.display = "none";
            }
        });
        document.addEventListener("click", function(e) {
            if (e.target !== inp) {
                list.style.display = "none";
            }
        });
    }
    attach("jobOrigin", "provinceListOrigin"); // ช่องต้นทาง
    attach("jobDest", "provinceListDest");     // ช่องปลายทาง
}
    function renderFilters() {
        const drvSelect = document.getElementById('filterDriver');
        drvSelect.innerHTML = '<option value="">- พนักงานทั้งหมด -</option>';
        state.drivers.forEach(d => drvSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`);
        let currentDriverView = ""; // เก็บ ID พนักงานที่กำลังเลือกดูอยู่
    }
    function changeStatus(id, s) {
    const job = state.jobs.find(j => j.id == id);
    if (!job || !hasAction(job, 'job_status')) {
        renderSchedule(); // วาดตารางใหม่เพื่อรีเซ็ตค่า Select ให้เป็นค่าเดิม
        return Swal.fire('ผิดพลาด', 'คุณไม่มีสิทธิ์เปลี่ยนสถานะงานนี้', 'error');
    }

    if (s === 'completed') {
        // หากงานเสร็จสิ้น ให้เปิด Modal เพื่อแนบหลักฐานตามปกติ
        document.getElementById('compJobId').value = id;
        // ค้นหาบรรทัดที่มีการตั้งค่า n.setMinutes...
        const n = new Date();
        const offset = n.getTimezoneOffset() * 60000; // ปรับชดเชยเวลาท้องถิ่น
        const localISOTime = (new Date(Date.now() - offset)).toISOString().slice(0, 16);
        document.getElementById('compTime').value = localISOTime;
        openModal('completeModal');
    } else {
        // แจ้งเตือนยืนยันก่อนเปลี่ยนสถานะอื่นๆ
        Swal.fire({
            title: 'ยืนยันการเปลี่ยนสถานะ?',
            text: `คุณต้องการเปลี่ยนสถานะเป็น "${s === 'in_progress' ? 'กำลังส่ง' : 'รอดำเนินการ'}" ใช่หรือไม่?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช่, เปลี่ยนเลย',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: 'var(--primary)'
        }).then(async (result) => {
            if (result.isConfirmed) {
                const f = new FormData();
                f.append('action', 'update_status');
                f.append('id', id);
                f.append('status', s);
                
                await fetch('api_fm.php', { method: 'POST', body: f });
                
                // แจ้งเตือนความสำเร็จแบบ Toast (มุมขวาบน)
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'อัปเดตสถานะสำเร็จ',
                    showConfirmButton: false,
                    timer: 2000
                });
                fetchData();
            } else {
                renderSchedule(); // หากยกเลิก ให้วาดตารางใหม่เพื่อคืนค่าเดิมใน Select
            }
        });
    }
}
    function toggleSelect(id) { id=parseInt(id); if(state.selectedJobs.includes(id)) state.selectedJobs=state.selectedJobs.filter(x=>x!==id); else state.selectedJobs.push(id); renderSchedule(); updateFooter(); }
    function updateFooter() {
    const footer = document.getElementById('floatingFooter');
    const countText = document.getElementById('selectedCount');
    
    console.log("Selected Jobs:", state.selectedJobs.length); // เช็คใน Console ว่าเลขขึ้นไหม

    if (state.selectedJobs && state.selectedJobs.length > 0) {
        footer.style.setProperty('display', 'flex', 'important'); // บังคับแสดง
        countText.innerText = `เลือก ${state.selectedJobs.length} รายการ`;
    } else {
        footer.style.display = 'none';
    }
}
    function clearSelection() { state.selectedJobs = [];   renderSchedule(); updateFooter(); }
    function openModal(id) { document.getElementById(id).classList.add('show'); }
    function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    function autoSelectVehicle(id){ const d=state.drivers.find(x=>x.id==id); if(d&&d.default_vehicle_id) document.getElementById('jobVehicle').value=d.default_vehicle_id; }
    async function handleComplete(e){ e.preventDefault(); const f=new FormData(); f.append('action','complete_job'); f.append('id',document.getElementById('compJobId').value); f.append('end_time',document.getElementById('compTime').value); const file=document.getElementById('compFile').files[0]; if(file)f.append('proof_image',file); await fetch('api_fm.php',{method:'POST',body:f}); closeModal('completeModal'); fetchData(); }
    

    async function handleSaveJob(e) {
    e.preventDefault();
    const f = new FormData(document.getElementById('jobForm'));
    f.append('action', 'save_job');
    
    try {
        const response = await fetch('api_fm.php', { method: 'POST', body: f });
        const result = await response.json();
        
        closeModal('jobModal');
        
        // กรองหน้าจอให้เหลือแค่พนักงานคนที่เราเพิ่งเพิ่มงานให้
        if (lastSelectedDriverId) {
            document.getElementById('filterDriver').value = lastSelectedDriverId;
        }

        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: 'บันทึกข้อมูลงานเรียบร้อยแล้ว',
            timer: 1500,
            showConfirmButton: false
        });

        // โหลดข้อมูลใหม่และวาดตาราง (ซึ่งจะถูกกรองด้วยค่า filterDriver ที่เราตั้งไว้ข้างบน)
        await fetchData(); 
        
    } catch (err) {
        Swal.fire('Error', 'ไม่สามารถบันทึกข้อมูลได้', 'error');
    }
}
    function openFleetModal(){
        const div=document.getElementById('fleetBody'); let h='<table style="width:100%; font-size:14px; color:var(--text-main)">';
        state.drivers.forEach(d=>{ h+=`<tr><td style="padding:8px">${d.name}</td><td><select class="form-input" style="padding:5px" onchange="saveDefaultVeh(${d.id},this.value)"><option value="">-</option>`; state.vehicles.forEach(v=>{ h+=`<option value="${v.id}" ${d.default_vehicle_id==v.id?'selected':''}>${v.plate_number}</option>`; }); h+=`</select></td></tr>`; });
        div.innerHTML=h+'</table>'; openModal('fleetModal');
    }

    async function saveDefaultVeh(d,v){ const f=new FormData(); f.append('action','update_default_vehicle'); f.append('id',d); f.append('vehicle_id',v); await fetch('api_fm.php',{method:'POST',body:f}); fetchData(); }
    
    function toggleReorderMode() {
    state.reorderMode = !state.reorderMode;
    const btn = document.getElementById('btnReorder');
    
    if (state.reorderMode) {
        btn.innerHTML = '<i class="fas fa-save"></i> บันทึกลำดับ';
        btn.className = 'btn btn-success';
        // ปิด Dropdown ทั้งหมดก่อนจัดลำดับเพื่อความง่าย
        document.querySelectorAll('.jobs-collapse-row').forEach(row => row.classList.remove('show'));
    } else {
        btn.innerHTML = '<i class="fas fa-sort"></i> จัดลำดับ';
        btn.className = 'btn btn-outline';
        // บันทึกลง LocalStorage
        localStorage.setItem('fm_driver_order', JSON.stringify(state.driverOrder));
        
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'บันทึกลำดับพนักงานแล้ว',
            showConfirmButton: false,
            timer: 1500
        });
    }
    renderSchedule();
}
let sortableInstance = null; // ตัวแปรเก็บสถานะการลาก

async function toggleReorderMode() {
    state.reorderMode = !state.reorderMode;
    const btn = document.getElementById('btnReorder');
    const tbody = document.getElementById('scheduleBody');

    if (state.reorderMode) {
        // --- 🟢 เข้าสู่โหมดลากวาง ---
        btn.innerHTML = '<i class="fas fa-save"></i> บันทึกลำดับ';
        btn.className = 'btn btn-success';

        // ปิด Accordion ทั้งหมดก่อนเพื่อความง่ายในการลาก
        document.querySelectorAll('.jobs-collapse-row').forEach(row => row.classList.remove('show'));
        document.querySelectorAll('.driver-header-row').forEach(row => row.classList.remove('active'));

        // เริ่มใช้งาน Sortable
        sortableInstance = new Sortable(tbody, {
            animation: 150,
            handle: '.drag-handle', // ลากได้เฉพาะตรงไอคอน Grip
            draggable: '.driver-header-row', // ลากได้เฉพาะแถวหัวข้อ
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                // เมื่อลากเสร็จ ให้อ่านลำดับจาก DOM จริง
                const newOrder = [];
                document.querySelectorAll('.driver-header-row').forEach(row => {
                    const id = parseInt(row.getAttribute('data-id'));
                    if (id) newOrder.push(id);
                });
                state.driverOrder = newOrder;
                
                // วาดตารางใหม่เพื่อให้ "แถวงาน" ย้ายตามมาอยู่ใต้หัวข้อที่ถูกต้อง
                renderSchedule(); 
            }
        });
    } else {
        // --- 🔴 ออกจากโหมดลากวาง (บันทึกข้อมูล) ---
        if (sortableInstance) {
            sortableInstance.destroy();
            sortableInstance = null;
        }

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
        btn.disabled = true;

        try {
            const f = new FormData();
            f.append('action', 'save_driver_order');
            f.append('order_json', JSON.stringify(state.driverOrder));
            const response = await fetch('api_fm.php', { method: 'POST', body: f });
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'บันทึกลำดับเรียบร้อย',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        } catch (e) {
            Swal.fire('Error', 'ไม่สามารถบันทึกลำดับได้', 'error');
        }

        btn.innerHTML = '<i class="fas fa-sort"></i> จัดลำดับ';
        btn.className = 'btn btn-outline';
        btn.disabled = false;
    }
    renderSchedule();
}

    function renderReorderList(){
        const d=document.getElementById('reorderList'); d.innerHTML='';
        state.driverOrder.forEach((id,idx)=>{ const drv=state.drivers.find(x=>x.id==id); if(!drv)return;
            const el=document.createElement('div'); el.className='btn btn-outline'; el.innerHTML=`${drv.name} <span style="margin-left:5px;cursor:pointer" onclick="moveDriver(${idx},-1)">←</span><span style="margin-left:5px;cursor:pointer" onclick="moveDriver(${idx},1)">→</span>`; d.appendChild(el);
        });
    }

    function moveDriver(i,dir){ if(i+dir<0||i+dir>=state.driverOrder.length)return; const t=state.driverOrder[i]; state.driverOrder[i]=state.driverOrder[i+dir]; state.driverOrder[i+dir]=t; renderReorderList(); }
    
    function openGroupModal(){ 
    // เช็คว่าติ๊กเลือกงานหรือยัง
    if(state.selectedJobs.length < 1) {
        return Swal.fire('แจ้งเตือน', 'กรุณาเลือกงานอย่างน้อย 1 รายการ', 'info');
    }
    
    let sum = 0; 
    state.selectedJobs.forEach(id => { 
        const j = state.jobs.find(x => x.id == id); 
        if(j) sum += (parseFloat(j.actual_price) || 0); 
    });

    document.getElementById('groupId').value = ''; 
    document.getElementById('groupName').value = `รวมบิล ${state.selectedJobs.length} รายการ`; 
    document.getElementById('groupPrice').value = sum; 
    document.getElementById('btnUngroup').style.display = 'none'; 
    
    openModal('groupModal'); // บรรทัดนี้จะสั่งเปิดหน้าต่าง modal รวมบิล
}

    function editGroup(gid){ const j=state.jobs.find(x=>x.group_id==gid); if(!j)return; document.getElementById('groupId').value=gid; document.getElementById('groupName').value=j.group_name; document.getElementById('groupPrice').value=j.group_price; document.getElementById('btnUngroup').style.display='block'; openModal('groupModal'); }

    async function handleSaveGroup(e) {
    e.preventDefault();
    const isDark = document.body.classList.contains('dark-mode');
    const id = document.getElementById('groupId').value;
    const f = new FormData();
    f.append('group_name', document.getElementById('groupName').value);
    f.append('total_price', document.getElementById('groupPrice').value);
    f.append('type', 'cost'); // กำหนดประเภทคงที่ตาม HTML ของคุณ

    if (id) {
        f.append('action', 'update_group');
        f.append('id', id);
    } else {
        f.append('action', 'create_group');
        f.append('job_ids', JSON.stringify(state.selectedJobs));
        const fJob = state.jobs.find(x => x.id == state.selectedJobs[0]);
        f.append('job_date', fJob.start_time.substring(0, 10));
    }

    try {
        await fetch('api_fm.php', { method: 'POST', body: f });
        closeModal('groupModal');
        clearSelection();
        fetchData();
        Swal.fire({
            icon: 'success', 
            title: 'สำเร็จ!', 
            timer: 1500, 
            background: isDark ? 'var(--bg-card)' : '#fff',
            color: isDark ? 'var(--text-main)' : '#545454'
        });
    } catch (e) {
        Swal.fire('Error', 'ไม่สามารถบันทึกได้', 'error');
    }
}

async function deleteGroup() {
    const isDark = document.body.classList.contains('dark-mode');
    const id = document.getElementById('groupId').value;
    const result = await Swal.fire({
        title: 'แยกกลุ่มบิล?',
        text: "งานจะถูกแยกกลับไปเป็นงานเดี่ยว",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ใช่, แยกกลุ่ม',
        background: isDark ? 'var(--bg-card)' : '#fff',
        color: isDark ? 'var(--text-main)' : '#545454'
    });

    if (result.isConfirmed) {
        const f = new FormData();
        f.append('action', 'delete_group');
        f.append('id', id);
        await fetch('api_fm.php', { method: 'POST', body: f });
        closeModal('groupModal');
        fetchData();
        Swal.fire('สำเร็จ', 'แยกกลุ่มเรียบร้อย', 'success');
    }
}
    async function deleteGroupFromCard(groupId) {
        const isDark = document.body.classList.contains('dark-mode');
        
        const result = await Swal.fire({
            title: 'แยกกลุ่มบิลนี้?',
            text: "งานทั้งหมดในกลุ่มนี้จะกลับเป็นงานเดี่ยว",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ตกลon, แยกกลุ่ม',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: 'var(--danger)',
            background: isDark ? 'var(--bg-card)' : '#fff',
            color: isDark ? 'var(--text-main)' : '#545454'
        });

        if (result.isConfirmed) {
            const f = new FormData();
            f.append('action', 'delete_group');
            f.append('id', groupId);
            await fetch('api_fm.php', { method: 'POST', body: f });
            fetchData();
            Swal.fire({ icon: 'success', title: 'แยกกลุ่มสำเร็จ', timer: 1000, showConfirmButton: false });
        }
    }
    function viewJobDetails(id) {
    const job = state.jobs.find(j => j.id == id);
    if (!job) return;

    const isDark = document.body.classList.contains('dark-mode');
    const driver = state.drivers.find(d => d.id == job.driver_id);
    const vehicle = state.vehicles.find(v => v.id == job.vehicle_id);

    // ... (ส่วน Group Logic เดิมไม่ต้องแก้) ...
    let groupDetailsHtml = ''; 
    if (job.group_id && job.group_id != 0) { 
        // ... (โค้ดเดิม) ...
    }

    // 🟢 1. สร้างตัวแปรสำหรับแสดงเส้นทาง (เพิ่มตรงนี้)
    let routeDisplay = job.destination; // ค่าเริ่มต้นคือปลายทางอย่างเดียว
    if (job.origin && job.origin.trim() !== "") {
        // ถ้ามีต้นทาง ให้แสดงแบบ "ต้นทาง -> ปลายทาง"
        routeDisplay = `${job.origin} <i class="fas fa-long-arrow-alt-right" style="color:var(--primary); margin:0 5px;"></i> ${job.destination}`;
    }

    // 🟢 2. แก้ไข HTML ส่วนที่แสดงผล (เปลี่ยนจาก ปลายทาง เป็น เส้นทาง)
    let html = `
        <div style="text-align: left; font-family: 'Prompt', sans-serif;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                <span style="font-size: 18px; font-weight: 700; color: var(--primary);">ID: #${job.id}</span>
                <span style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 50px; font-size: 12px;">${job.status}</span>
            </div>
            
            <p style="margin: 8px 0;"><strong><i class="fas fa-user"></i> ลูกค้า:</strong> ${job.customer_name}</p>
            
            <p style="margin: 8px 0;"><strong><i class="fas fa-map-marker-alt"></i> เส้นทาง:</strong> ${routeDisplay}</p>

            <p style="margin: 8px 0;"><strong><i class="fas fa-clock"></i> เวลาเริ่ม:</strong> ${new Date(job.start_time).toLocaleString('th-TH', { hour12: false })} น.</p>            
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 10px 0;">
            
            <p style="margin: 8px 0;"><strong><i class="fas fa-steering-wheel"></i> คนขับ:</strong> ${driver ? driver.name : '-'}</p>
            <p style="margin: 8px 0;"><strong><i class="fas fa-truck"></i> รถที่ใช้:</strong> ${vehicle ? vehicle.plate_number : '-'}</p>
            
            ${groupDetailsHtml}

            ${job.proof_image ? `
                <div style="margin-top: 15px;">
                    <strong><i class="fas fa-image"></i> หลักฐานการส่ง:</strong>
                    <img src="uploads/proofs/${job.proof_image}" style="width: 100%; border-radius: 8px; margin-top: 8px; cursor: pointer;" onclick="window.open(this.src)">
                </div>
            ` : ''}
        </div>
    `;

    Swal.fire({
        title: 'รายละเอียดงาน',
        html: html,
        width: '500px',
        confirmButtonText: 'ปิดหน้าต่าง',
        confirmButtonColor: 'var(--primary)',
        background: isDark ? 'var(--bg-card)' : '#fff',
        color: isDark ? 'var(--text-main)' : '#1e293b'
    });
}
    // ฟังก์ชันเปิด Modal และโหลดข้อมูลลง Select
function openGlobalFleetModal() {
    const drvSelect = document.getElementById('fleetDriverSelect');
    const vehSelect = document.getElementById('fleetVehicleSelect');
    
    // โหลดรายชื่อพนักงานจาก Global State
    drvSelect.innerHTML = '<option value="">- กรุณาเลือกพนักงาน -</option>';
    state.drivers.forEach(d => {
        drvSelect.innerHTML += `<option value="${d.id}">${d.name} (${d.category === 'partner' ? 'รถร่วม' : 'ประจำ'})</option>`;
    });

    // โหลดรายการรถจาก Global State
    vehSelect.innerHTML = '<option value="">- ไม่มีรถประจำ -</option>';
    state.vehicles.forEach(v => {
        const plate = v.plate_number || '';
        const fleet = v.fleet_number ? `(${v.fleet_number})` : '';
        vehSelect.innerHTML += `<option value="${v.id}">${plate} ${fleet}</option>`;
    });

    openModal('globalFleetModal');
}

// เมื่อเลือกพนักงาน ให้เลือกทะเบียนรถคันปัจจุบันมาโชว์ (ถ้ามี)
function loadCurrentVehicle(driverId) {
    const driver = state.drivers.find(d => d.id == driverId);
    const vehSelect = document.getElementById('fleetVehicleSelect');
    if (driver && driver.default_vehicle_id) {
        vehSelect.value = driver.default_vehicle_id;
    } else {
        vehSelect.value = "";
    }
}

// ฟังก์ชันส่งค่าไปบันทึกที่ Database
async function saveGlobalFleet() {
    const driverId = document.getElementById('fleetDriverSelect').value;
    const vehicleId = document.getElementById('fleetVehicleSelect').value;
    const isDark = document.body.classList.contains('dark-mode');

    if (!driverId) {
        return Swal.fire({ title: 'แจ้งเตือน', text: 'กรุณาเลือกพนักงานก่อนครับ', icon: 'warning', background: isDark ? '#1e293b' : '#fff', color: isDark ? '#fff' : '#000' });
    }

    try {
        const f = new FormData();
        f.append('action', 'update_default_vehicle');
        f.append('id', driverId);
        f.append('vehicle_id', vehicleId);

        const res = await fetch('api_fm.php', { method: 'POST', body: f });
        const result = await res.json();

        if (result.success || result.message === 'success' || !result.error) {
            closeModal('globalFleetModal');
            
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                timer: 1500,
                showConfirmButton: false,
                background: isDark ? '#1e293b' : '#fff',
                color: isDark ? '#fff' : '#000'
            });

            fetchData(); // โหลดข้อมูลใหม่เพื่ออัปเดตทะเบียนรถที่โชว์ในตาราง
        }
    } catch (e) {
        console.error(e);
        Swal.fire('ผิดพลาด', 'ไม่สามารถบันทึกได้', 'error');
    }
}
function toggleDriverJobs(headerEl, targetId) {
    const targetRow = document.getElementById(targetId);
    const isOpen = targetRow.classList.contains('show');
    
    // ปิดอันอื่น (ถ้าต้องการให้เปิดได้ทีละคน)
    // document.querySelectorAll('.jobs-collapse-row').forEach(r => r.classList.remove('show'));
    // document.querySelectorAll('.driver-header-row').forEach(r => r.classList.remove('active'));

    if (isOpen) {
        targetRow.classList.remove('show');
        headerEl.classList.remove('active');
    } else {
        targetRow.classList.add('show');
        headerEl.classList.add('active');
    }
}

function toggleAccordion(header, rowId) {
    const row = document.getElementById(rowId);
    const driverId = parseInt(rowId.replace('row-', '')); // ดึงตัวเลข ID จากชื่อ ID ของแถว
    
    header.classList.toggle('active');
    row.classList.toggle('show');

    // จัดการกับ state.expandedDrivers
    if (row.classList.contains('show')) {
        if (!state.expandedDrivers.includes(driverId)) {
            state.expandedDrivers.push(driverId);
        }
    } else {
        state.expandedDrivers = state.expandedDrivers.filter(id => id !== driverId);
    }
}

function moveDriverDirectly(index, direction) {
    const oldIndex = parseInt(index);
    const newIndex = oldIndex + direction;
    if (newIndex < 0 || newIndex >= state.driverOrder.length) return;
    const temp = state.driverOrder[oldIndex];
    state.driverOrder[oldIndex] = state.driverOrder[newIndex];
    state.driverOrder[newIndex] = temp;
    renderSchedule();
}
    window.onclick = function(e) { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); }

    
</script>
</body> 
</html>