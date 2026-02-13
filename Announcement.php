<?php
session_start();

// 🔥 1. ตั้งค่า Timezone เป็นไทย
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'db_connect.php';

// =================================================================================
// 🔥 Cookie: บันทึก ID ข่าวล่าสุด
// =================================================================================
$sql_mark_read = "SELECT id FROM announcements ORDER BY created_at DESC LIMIT 1";
$res_mark_read = $conn->query($sql_mark_read);

if ($res_mark_read && $res_mark_read->num_rows > 0) {
    $row_mark = $res_mark_read->fetch_assoc();
    $latest_news_id = $row_mark['id'];
    setcookie("tjc_read_news_id", $latest_news_id, time() + (86400 * 30), "/");
    $_COOKIE['tjc_read_news_id'] = $latest_news_id;
}

// 🔥 2. ตั้งค่า Timezone DB
$conn->query("SET time_zone = '+07:00'");

// Check Login
if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['fullname'];

// =================================================================================
// 🔐 ส่วนตรวจสอบสิทธิ์ (Permission Check)
// =================================================================================
$can_create = hasAction('news_create');
$can_edit   = hasAction('news_edit');
$can_delete = hasAction('news_delete');

// =================================================================================
// 💾 ส่วนจัดการข้อมูล (Save / Edit / Delete) - PRG Pattern
// =================================================================================

$message = ""; 

// เช็คว่ามีข้อความ Alert ค้างมาจากรอบที่แล้วไหม (PRG)
if (isset($_SESSION['alert_msg'])) {
    $message = $_SESSION['alert_msg'];
    unset($_SESSION['alert_msg']);
}

// 1. บันทึกประกาศใหม่
if ($can_create && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_news'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $type_id = $_POST['type_id']; 
    $post_date = $_POST['post_date'] ?? date('Y-m-d H:i:s');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0; // รับค่าปักหมุด
    
    // --- จัดการไฟล์หลายไฟล์ ---
    $attachment_arr = [];
    if (isset($_FILES['file_upload']) && !empty($_FILES['file_upload']['name'][0])) {
        $total_files = count($_FILES['file_upload']['name']);
        $upload_dir = __DIR__ . "/uploads/news/";
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        for($i=0; $i < $total_files; $i++){
            if ($_FILES['file_upload']['error'][$i] == 0) {
                $ext = strtolower(pathinfo($_FILES['file_upload']['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
                    $new_name = "news_" . time() . "_" . rand(1000,9999) . "_{$i}." . $ext;
                    if (move_uploaded_file($_FILES['file_upload']['tmp_name'][$i], $upload_dir . $new_name)) {
                        $attachment_arr[] = $new_name;
                    }
                }
            }
        }
    }
    // รวมชื่อไฟล์เป็น string เดียว
    $attachment = !empty($attachment_arr) ? implode(',', $attachment_arr) : NULL;
    
    if (!empty($title)) {
        // เพิ่ม is_pinned ใน SQL
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, type_id, attachment, created_by, created_at, is_pinned) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $title, $content, $type_id, $attachment, $current_user, $post_date, $is_pinned);
        
        if ($stmt->execute()) {
            $_SESSION['alert_msg'] = "<script>Swal.fire({icon:'success', title:'บันทึกประกาศเรียบร้อย', showConfirmButton:false, timer:1500})</script>";
            header("Location: " . $_SERVER['PHP_SELF']); 
            exit();
        }
    }
}

// 2. แก้ไขประกาศ
if ($can_edit && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_news'])) {
    $id = $_POST['edit_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $type_id = $_POST['type_id']; 
    $post_date = $_POST['post_date'];
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    // ดึงไฟล์เก่าออกมาก่อน
    $q_old = $conn->query("SELECT attachment FROM announcements WHERE id = $id");
    $old_row = $q_old->fetch_assoc();
    $current_files = !empty($old_row['attachment']) ? explode(',', $old_row['attachment']) : [];

    // ถ้าติ๊ก "ลบไฟล์เดิมทั้งหมด"
    if (isset($_POST['clear_old_files']) && $_POST['clear_old_files'] == '1') {
        foreach($current_files as $f) { @unlink(__DIR__ . "/uploads/news/" . $f); }
        $current_files = []; 
    }

    // วนลูปอัปโหลดไฟล์ใหม่ (Append)
    if (isset($_FILES['file_upload']) && !empty($_FILES['file_upload']['name'][0])) {
        $total_files = count($_FILES['file_upload']['name']);
        for($i=0; $i < $total_files; $i++){
            if ($_FILES['file_upload']['error'][$i] == 0) {
                $ext = strtolower(pathinfo($_FILES['file_upload']['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
                    $new_name = "news_" . time() . "_" . rand(1000,9999) . "_{$i}." . $ext;
                    move_uploaded_file($_FILES['file_upload']['tmp_name'][$i], __DIR__ . "/uploads/news/" . $new_name);
                    $current_files[] = $new_name; 
                }
            }
        }
    }

    $attachment_str = !empty($current_files) ? implode(',', $current_files) : NULL;

    // อัปเดต SQL
    $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, type_id=?, created_at=?, attachment=?, is_pinned=? WHERE id=?");
    $stmt->bind_param("sssssii", $title, $content, $type_id, $post_date, $attachment_str, $is_pinned, $id);
    
    if ($stmt->execute()) {
        $_SESSION['alert_msg'] = "<script>Swal.fire({icon:'success', title:'แก้ไขข้อมูลเรียบร้อย', showConfirmButton:false, timer:1500})</script>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// 3. ลบประกาศ
if ($can_delete && isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $q = $conn->query("SELECT attachment FROM announcements WHERE id = $id");
    $row = $q->fetch_assoc();
    
    // วนลบไฟล์จริงทั้งหมด
    if ($row['attachment']) {
        $files = explode(',', $row['attachment']);
        foreach($files as $f) {
            if(file_exists(__DIR__ . "/uploads/news/" . $f)) unlink(__DIR__ . "/uploads/news/" . $f);
        }
    }
    
    $conn->query("DELETE FROM announcements WHERE id = $id");
    header("Location: Announcement.php"); exit();
}
// 4. สลับสถานะปักหมุด (Toggle Pin)
if (isset($_GET['toggle_pin'])) {
    $id = (int)$_GET['toggle_pin']; // แปลงเป็นตัวเลขเพื่อความปลอดภัย
    
    // คำสั่ง SQL สลับค่า (ถ้า 1 เป็น 0, ถ้า 0 เป็น 1)
    $conn->query("UPDATE announcements SET is_pinned = NOT is_pinned WHERE id = $id");
    
    // Refresh หน้าจอ
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// =================================================================================
// 🔍 ส่วนการดึงข้อมูลและตัวกรอง
// =================================================================================

$search_text = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_date = $_GET['date'] ?? '';

// ดึงประเภทข่าว
$types_res = $conn->query("SELECT * FROM master_hr_types ORDER BY id ASC");
$types_options = [];
while($t = $types_res->fetch_assoc()) { $types_options[] = $t; }

// สร้าง SQL Query
$sql = "SELECT a.*, t.type_name, t.color_class 
        FROM announcements a 
        LEFT JOIN master_hr_types t ON a.type_id = t.id 
        WHERE 1=1 ";

$params = []; $types = "";

if (!empty($search_text)) {
    $sql .= " AND a.title LIKE ?";
    $params[] = "%$search_text%"; $types .= "s";
}
if (!empty($filter_type)) {
    $sql .= " AND a.type_id = ?";
    $params[] = $filter_type; $types .= "i";
}
if (!empty($filter_date)) {
    $sql .= " AND DATE(a.created_at) = ?";
    $params[] = $filter_date; $types .= "s";
}

// 🔥 เรียงตามการปักหมุดก่อน แล้วค่อยตามวันที่
$sql .= " ORDER BY a.is_pinned DESC, a.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();

function DateThaiShort($strDate) {
    if(!$strDate) return "-";
    $timestamp = strtotime($strDate);
    $thai_months = [1=>"ม.ค.", 2=>"ก.พ.", 3=>"มี.ค.", 4=>"เม.ย.", 5=>"พ.ค.", 6=>"มิ.ย.", 7=>"ก.ค.", 8=>"ส.ค.", 9=>"ก.ย.", 10=>"ต.ค.", 11=>"พ.ย.", 12=>"ธ.ค."];
    return date("j", $timestamp)." ".$thai_months[(int)date("n", $timestamp)]." ".(date("Y", $timestamp)+543)." ".date("H:i", $timestamp)." น.";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข่าวประชาสัมพันธ์</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-body: #f0f4f8;
            --bg-card: rgba(255, 255, 255, 0.95);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --primary-color: #2563eb;
            --accent-color: #f59e0b;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.02), 0 1px 2px rgba(0,0,0,0.03);
            --shadow-md: 0 8px 16px -4px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.025);
            --shadow-lg: 0 20px 25px -5px rgba(0,0,0,0.08), 0 10px 10px -5px rgba(0,0,0,0.02);
            --shadow-hover: 0 25px 50px -12px rgba(37, 99, 235, 0.15);
        }
        
        [data-theme="dark"], body.dark-mode {
            --bg-body: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.95);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --shadow-lg: 0 20px 25px -5px rgba(0,0,0,0.3);
            --shadow-hover: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        body { 
            background: var(--bg-body);
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(245, 158, 11, 0.05) 0px, transparent 50%);
            color: var(--text-main); 
            font-family: 'Prompt', sans-serif; 
            margin: 0; 
            min-height: 100vh;
        }

        .container-fluid { padding: 40px; max-width: 1400px; margin: 0 auto; }
        
        .top-filter-container { position: sticky; top: 20px; z-index: 99; margin-bottom: 40px; }
        
        .filter-glass-bar {
            background: var(--bg-card); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.2); padding: 15px 25px; border-radius: 100px;
            box-shadow: var(--shadow-lg); display: flex; flex-wrap: wrap; gap: 15px; align-items: center; transition: all 0.3s ease;
        }
        
        .filter-input-group {
            display: flex; align-items: center; background: rgba(0,0,0,0.03); border-radius: 50px; padding: 0 20px; height: 48px; 
            transition: 0.3s; border: 1px solid transparent; flex: 1;
        }
        .filter-input-group:hover, .filter-input-group:focus-within { 
            background: var(--bg-card); border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        /* Fix Inputs/Selects */
        .filter-input-group input, 
        .filter-input-group select,
        .filter-input-group .flatpickr-input {
            border: none !important; 
            background-color: transparent !important; 
            box-shadow: none !important; 
            outline: none !important;
            font-family: 'Prompt'; 
            font-size: 0.95rem; 
            color: var(--text-main); 
            width: 100%; 
            padding: 0 10px !important; 
            cursor: pointer;
        }
        
        .search-group { flex: 2; min-width: 250px; }
        .filter-icon { color: var(--primary-color); opacity: 0.8; }

        .btn-show-all {
            height: 48px; padding: 0 25px; border-radius: 50px; background: transparent; border: 1px solid var(--border-color);
            color: var(--text-muted); cursor: pointer; font-family: 'Prompt'; font-weight: 500; transition: 0.3s;
        }
        .btn-show-all:hover, .btn-show-all.active { 
            background: var(--primary-color); color: white; border-color: var(--primary-color); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .title-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; padding: 0 10px; }
        .title-section h2 { 
            margin: 0; font-weight: 800; font-size: 2.2rem; 
            background: linear-gradient(90deg, var(--text-main) 0%, var(--primary-color) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: -0.5px;
        }
        .title-sub { font-size: 0.95rem; color: var(--text-muted); margin-top: 5px; font-weight: 400; }

        .btn-create { 
            height: 50px; background: var(--primary-gradient); color: white; padding: 0 30px; border-radius: 50px; font-weight: 600; border: none;
            cursor: pointer; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3); transition: 0.3s;
        }
        .btn-create:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 12px 25px rgba(37, 99, 235, 0.4); }

        /* 🔥 GRID LAYOUT */
        .news-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); 
            gap: 40px; 
            padding-bottom: 50px;
        }
        
        .news-card { 
            background: var(--bg-card); 
            border-radius: 24px; 
            box-shadow: var(--shadow-md); 
            overflow: hidden; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            border: 1px solid rgba(255,255,255,0.5); 
            display: flex; 
            flex-direction: column; 
            position: relative;
        }
        .news-card:hover { 
            transform: translateY(-10px); 
            box-shadow: var(--shadow-hover); 
            border-color: rgba(37, 99, 235, 0.2); 
        }
        
        /* 🔥 CARD COVER (รูปเดียวเต็มใบ) */
        .card-cover {
            height: 300px; /* ความสูงคงที่ */
            width: 100%;
            position: relative;
            background-color: #334155; 
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }

        .card-cover img {
            width: 100%;
            height: 100%;
            /* 🔥 ใช้ contain เพื่อให้เห็นครบทุกส่วน */
            object-fit: contain; 
            transition: transform 0.5s ease;
        }
        
        .news-card:hover .card-cover img {
            transform: scale(1.05); /* เอฟเฟกต์ซูมนิดหน่อย */
        }

        /* ป้ายบอกจำนวนรูปภาพ (+X) */
        .multi-img-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.65);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 5;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .card-type-badge {
            position: absolute; top: 20px; left: 20px; padding: 8px 16px; border-radius: 30px;
            font-size: 0.85rem; font-weight: 700; backdrop-filter: blur(8px); 
            box-shadow: 0 4px 10px rgba(0,0,0,0.15); z-index: 2; letter-spacing: 0.5px;
        }

        .card-content { padding: 30px; flex: 1; display: flex; flex-direction: column; }
        
        .news-meta {
            display: flex; gap: 15px; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px; align-items: center;
        }
        .news-meta i { color: var(--primary-color); opacity: 0.7; }

        .news-title { 
            font-size: 1.5rem; font-weight: 700; color: var(--text-main); 
            margin: 0 0 15px; line-height: 1.4; 
        }
        
        .news-desc { 
            color: var(--text-muted); font-size: 1rem; line-height: 1.7; 
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
            margin-bottom: 25px;
        }

        .btn-read-more {
            display: inline-block; padding: 0; border: none; background: none;
            color: var(--primary-color); font-weight: 600; font-size: 0.95rem;
            cursor: pointer; margin-bottom: 20px; text-align: left; transition: 0.2s;
        }
        .btn-read-more:hover { text-decoration: underline; color: var(--accent-color); }

        .card-actions {
            border-top: 1px dashed var(--border-color); padding-top: 20px; margin-top: auto;
            display: flex; justify-content: space-between; align-items: center;
        }
        
        .pdf-link {
            display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 12px;
            background: rgba(239, 68, 68, 0.1); color: #ef4444; font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: 0.2s;
        }
        .pdf-link:hover { background: #ef4444; color: white; }

        .action-buttons { display: flex; gap: 10px; }
        .btn-icon { 
            width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.03); color: var(--text-muted); cursor: pointer; transition: 0.2s; font-size: 1rem;
        }
        .btn-icon:hover { transform: scale(1.15); }
        .btn-icon.edit:hover { background: #fef3c7; color: #d97706; }
        .btn-icon.delete:hover { background: #fee2e2; color: #dc2626; }

        .bg-primary { background: rgba(219, 234, 254, 0.9); color: #1e40af; }
        .bg-secondary { background: rgba(241, 245, 249, 0.9); color: #475569; }
        .bg-success { background: rgba(220, 252, 231, 0.9); color: #166534; }
        .bg-danger { background: rgba(254, 226, 226, 0.9); color: #991b1b; }
        .bg-warning { background: rgba(254, 243, 199, 0.9); color: #92400e; }
        .bg-info { background: rgba(207, 250, 254, 0.9); color: #155e75; }

        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); overflow-y: auto; }
        .modal-content { 
            background: var(--bg-card); margin: 50px auto; padding: 40px; border-radius: 30px; 
            width: 90%; max-width: 700px; border: 1px solid rgba(255,255,255,0.2); 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative;
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .form-label { display: block; margin-bottom: 10px; font-weight: 600; color: var(--text-main); font-size: 0.95rem; }
        .form-control { 
            width: 100%; padding: 14px 18px; border: 2px solid transparent; 
            border-radius: 15px; background: rgba(0,0,0,0.03); color: var(--text-main); 
            font-family: 'Prompt'; outline: none; transition: 0.3s; box-sizing: border-box; 
        }
        .form-control:focus { background: var(--bg-card); border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
        
        .empty-state { grid-column: 1/-1; text-align: center; padding: 80px 20px; background: rgba(255,255,255,0.5); border-radius: 30px; border: 2px dashed var(--border-color); }
        
        .view-content { 
            line-height: 1.8; color: var(--text-main); font-size: 1.05rem; white-space: pre-wrap; margin-top: 20px; 
        }
        
        .view-image { 
            width: 100%; 
            height: auto; 
            border-radius: 15px; 
            margin-bottom: 20px; 
            background: rgba(0,0,0,0.02);
            cursor: pointer; 
            transition: transform 0.2s ease;
            display: block; 
        }
        .view-image:hover { transform: scale(1.02); }

        .view-meta { color: var(--text-muted); font-size: 0.9rem; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px; }

        /* 🔥🔥🔥 CSS Force Colors for Dark Mode 🔥🔥🔥 */
        [data-theme="dark"] .view-content, body.dark-mode .view-content { color: #ffffff !important; }
        [data-theme="dark"] .form-control, body.dark-mode .form-control { color: #ffffff !important; background-color: rgba(255, 255, 255, 0.05); }
        [data-theme="dark"] select option, body.dark-mode select option { background-color: #1e293b; color: #ffffff; }
        [data-theme="dark"] .news-title, body.dark-mode .news-title { color: #ffffff !important; }
        [data-theme="dark"] .news-desc, body.dark-mode .news-desc { color: #e2e8f0 !important; }
        [data-theme="dark"] .news-meta, body.dark-mode .news-meta { color: #cbd5e1 !important; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    <?php echo $message; ?>

    <div class="main-content container-fluid">

        <div class="title-section">
            <div>
                <h2><i class="fas fa-bullhorn" style="margin-right:15px; background: -webkit-linear-gradient(45deg, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i> ข่าวประชาสัมพันธ์</h2>
                <div class="title-sub">
                    <?php 
                    if(!empty($filter_date)) echo "ข้อมูลประจำวันที่: " . DateThaiShort($filter_date . ' 00:00:00'); 
                    else echo "ศูนย์รวมข่าวสารและการแจ้งเตือนทั้งหมด";
                    ?>
                </div>
            </div>
            <?php if ($can_create): ?>
                <button type="button" class="btn-create" onclick="openCreateModal()">
                    <i class="fas fa-plus-circle"></i> ประกาศใหม่
                </button>
            <?php endif; ?>
        </div>

        <form method="GET" id="searchForm" class="top-filter-container">
            <div class="filter-glass-bar">
                <div class="filter-input-group search-group">
                    <i class="fas fa-search filter-icon"></i>
                    <input type="text" name="search" placeholder="ค้นหาหัวข้อเรื่อง..." value="<?php echo htmlspecialchars($search_text); ?>" onchange="this.form.submit()">
                </div>

                <div class="filter-input-group" style="min-width: 200px;">
                    <i class="fas fa-tag filter-icon"></i>
                    <select name="type" onchange="this.form.submit()" style="cursor:pointer;">
                        <option value="">ทุกหมวดหมู่</option>
                        <?php foreach($types_options as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $filter_type == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo $t['type_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-input-group" style="min-width: 160px; cursor: pointer;" onclick="document.querySelector('.flatpickr-filter')._flatpickr.open()">
                    <i class="far fa-calendar-alt filter-icon"></i>
                    <input type="text" name="date" class="flatpickr-filter" placeholder="เลือกวันที่" value="<?php echo $filter_date; ?>" readonly style="cursor:pointer;">
                </div>

                <button type="submit" name="date" value="" class="btn-show-all <?php echo empty($filter_date) ? 'active' : ''; ?>">ทั้งหมด</button>
            </div>
        </form>

        <div class="news-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                    $typeText = $row['type_name'] ?? 'ทั่วไป';
                    $colorClass = $row['color_class'] ?? 'secondary';
                    $bgBadge = 'bg-' . $colorClass;
                    
                    // --- Logic แยกไฟล์ และนับจำนวน ---
                    $file_list = !empty($row['attachment']) ? explode(',', $row['attachment']) : [];
                    $image_files = [];
                    foreach ($file_list as $f) {
                        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $image_files[] = "uploads/news/" . $f;
                        }
                    }
                    $imgCount = count($image_files);
                    $files_json = htmlspecialchars(json_encode($file_list), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="news-card">
                    <textarea id="hidden_content_<?php echo $row['id']; ?>" style="display:none;"><?php echo htmlspecialchars($row['content']); ?></textarea>
                    <input type="hidden" id="hidden_title_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['title']); ?>">
                    <input type="hidden" id="hidden_date_<?php echo $row['id']; ?>" value="<?php echo DateThaiShort($row['created_at']); ?>">
                    <input type="hidden" id="hidden_by_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['created_by']); ?>">
                    <input type="hidden" id="hidden_type_<?php echo $row['id']; ?>" value="<?php echo $typeText; ?>">
                    <input type="hidden" id="hidden_color_<?php echo $row['id']; ?>" value="<?php echo $bgBadge; ?>">
                    <input type="hidden" id="hidden_files_<?php echo $row['id']; ?>" value="<?php echo $files_json; ?>">

                    <?php if ($imgCount > 0): ?>
                        <div class="card-cover" onclick="viewNews(<?php echo $row['id']; ?>)">
                            <span class="card-type-badge <?php echo $bgBadge; ?>"><?php echo $typeText; ?></span>
                            
                            <?php if($can_edit): ?>
                                <?php 
                                    $pinColor = ($row['is_pinned'] == 1) ? '#f59e0b' : '#cbd5e1'; 
                                    $pinOpacity = ($row['is_pinned'] == 1) ? '1' : '0.5';
                                    $pinTitle = ($row['is_pinned'] == 1) ? 'ยกเลิกการปักหมุด' : 'ปักหมุดประกาศนี้';
                                ?>
                                <a href="?toggle_pin=<?php echo $row['id']; ?>" 
                                   title="<?php echo $pinTitle; ?>"
                                   onclick="event.stopPropagation();" 
                                   style="position:absolute; top:10px; right:10px; z-index:20; 
                                          background:<?php echo $pinColor; ?>; color:white; 
                                          width:35px; height:35px; border-radius:50%; 
                                          display:flex; align-items:center; justify-content:center; 
                                          box-shadow:0 2px 5px rgba(0,0,0,0.2); 
                                          transition:0.3s; opacity:<?php echo $pinOpacity; ?>; text-decoration:none;"
                                   onmouseover="this.style.opacity='1'; this.style.transform='scale(1.1)';"
                                   onmouseout="this.style.opacity='<?php echo $pinOpacity; ?>'; this.style.transform='scale(1)';"
                                >
                                    <i class="fas fa-thumbtack"></i>
                                </a>
                            <?php elseif($row['is_pinned'] == 1): ?>
                                <div style="position:absolute; top:10px; right:10px; z-index:20; background:#f59e0b; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.2);">
                                    <i class="fas fa-thumbtack"></i>
                                </div>
                            <?php endif; ?>

                            <img src="<?php echo $image_files[0]; ?>" alt="News Cover">

                            <?php if($imgCount > 1): ?>
                                <div class="multi-img-badge">
                                    <i class="far fa-images"></i> +<?php echo $imgCount - 1; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding: 25px 30px 0 30px;">
                            <span class="card-type-badge <?php echo $bgBadge; ?>" style="position:static; display:inline-block;"><?php echo $typeText; ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="card-content">
                        <div class="news-meta">
                            <span><i class="far fa-clock"></i> <?php echo DateThaiShort($row['created_at']); ?></span>
                            <span><i class="far fa-user"></i> <?php echo htmlspecialchars($row['created_by']); ?></span>
                        </div>
                        <h3 class="news-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <div class="news-desc"><?php echo nl2br(htmlspecialchars($row['content'])); ?></div>
                        <button class="btn-read-more" onclick="viewNews(<?php echo $row['id']; ?>)">อ่านเพิ่มเติม...</button>

                        <div class="card-actions">
                            <div></div>
                            <?php if ($can_edit || $can_delete): ?>
                            <div class="action-buttons">
                                <?php if ($can_edit): ?>
                                    <div class="btn-icon edit" onclick="openEditModal('<?php echo $row['id']; ?>', '<?php echo addslashes($row['title']); ?>', `<?php echo addslashes($row['content']); ?>`, '<?php echo $row['type_id']; ?>', '<?php echo $row['created_at']; ?>', '<?php echo $row['attachment']; ?>', <?php echo $row['is_pinned']; ?>)"><i class="fas fa-pen"></i></div>
                                <?php endif; ?>
                                <?php if ($can_delete): ?>
                                    <div class="btn-icon delete" onclick="confirmDelete(<?php echo $row['id']; ?>)"><i class="fas fa-trash"></i></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open" style="font-size: 5rem; color: var(--border-color); margin-bottom:20px;"></i>
                    <h3 style="color:var(--text-muted);">ไม่พบข้อมูลข่าวสาร</h3>
                    <?php if(!empty($filter_date)): ?>
                        <p style="color:var(--text-muted);">ในวันที่ <?php echo DateThaiShort($filter_date.' 00:00:00'); ?></p>
                        <a href="?date=" style="color:var(--primary-color); font-weight:600; text-decoration:none;">ดูรายการทั้งหมด</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="viewNewsModal" class="modal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <span id="view_badge" class="badge-type" style="margin-bottom:10px; display:inline-block; padding:5px 12px; border-radius:20px; font-size:0.8rem; font-weight:bold; color:#fff;"></span>
                    <h2 id="view_title" style="margin:0; line-height:1.3; color:var(--text-main);"></h2>
                </div>
                <i class="fas fa-times" onclick="document.getElementById('viewNewsModal').style.display='none'" style="cursor:pointer; font-size:1.5rem; color:var(--text-muted); margin-left:15px;"></i>
            </div>
            
            <div style="margin-top:20px;">
                <div class="view-meta">
                    <i class="far fa-clock"></i> <span id="view_date"></span> &nbsp;|&nbsp; 
                    <i class="far fa-user"></i> <span id="view_by"></span>
                </div>
                
                <img id="view_img" src="" class="view-image" style="display:none;" title="คลิกเพื่อดูรูปเต็ม">
                
                <div id="view_gallery_container" style="margin-bottom:20px;"></div>
                
                <div id="view_content" class="view-content"></div>
            </div>
            
            <div style="margin-top:30px; text-align:right;">
                 <button type="button" onclick="document.getElementById('viewNewsModal').style.display='none'" class="btn-create" style="display:inline-flex; width:auto; height:auto; padding:10px 30px;">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <div id="newsModal" class="modal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                <h3 id="modalTitle" style="margin:0; font-size:1.5rem;"><i class="fas fa-edit" style="color:var(--primary-color)"></i> จัดการประกาศ</h3>
                <i class="fas fa-times" onclick="document.getElementById('newsModal').style.display='none'" style="cursor:pointer; font-size:1.2rem; color:var(--text-muted);"></i>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div style="margin-bottom:20px; background:#fffbeb; padding:10px 15px; border-radius:10px; border:1px solid #fcd34d;">
                    <label style="cursor:pointer; display:flex; align-items:center; gap:10px; color:#92400e; font-weight:600;">
                        <input type="checkbox" name="is_pinned" id="inp_pinned" value="1" style="width:20px; height:20px; accent-color:#f59e0b;">
                        <i class="fas fa-thumbtack"></i> ปักหมุดประกาศนี้ไว้บนสุด
                    </label>
                </div>

                <div style="margin-bottom:20px;">
                    <label class="form-label">หัวข้อเรื่อง</label>
                    <input type="text" name="title" id="inp_title" class="form-control" placeholder="ระบุหัวข้อข่าวที่น่าสนใจ..." required>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                    <div>
                        <label class="form-label">วันที่เผยแพร่</label>
                        <input type="text" name="post_date" id="inp_date" class="form-control flatpickr-th-time" required>
                    </div>
                    <div>
                        <label class="form-label">หมวดหมู่</label>
                        <select name="type_id" id="inp_type" class="form-control" required style="appearance:none;">
                            <option value="">-- เลือกประเภท --</option>
                            <?php foreach($types_options as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['type_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:20px;">
                    <label class="form-label">เนื้อหารายละเอียด</label>
                    <textarea name="content" id="inp_content" class="form-control" rows="6" placeholder="รายละเอียดเนื้อหาข่าว..." required></textarea>
                </div>
                
                <div style="margin-bottom:30px;">
                    <label class="form-label">รูปภาพหน้าปก หรือไฟล์แนบ</label>
                    <div style="position:relative;">
                        <input type="file" id="real_file_input" name="file_upload[]" multiple style="display:none;" onchange="handleFileSelect(event)">
                        
                        <button type="button" onclick="document.getElementById('real_file_input').click()" class="form-control" style="text-align:left; color:#666; cursor:pointer; background:#fff;">
                            <i class="fas fa-plus-circle" style="color:var(--primary-color);"></i> คลิกเพื่อเพิ่มไฟล์ (เลือกทีละไฟล์ได้)
                        </button>
                    </div>
                    
                    <div id="selected_file_list" style="display:flex; flex-direction:column; gap:8px; margin-top:10px;"></div>

                    <div id="delete_option_area" style="display:none; margin-top:15px; padding-top:10px; border-top:1px dashed #ddd;">
                         <label style="cursor:pointer; color:#ef4444; font-weight:600;">
                            <input type="checkbox" name="clear_old_files" value="1"> ลบไฟล์เดิมที่มีอยู่ทั้งหมดทิ้ง
                         </label>
                         <div id="currentFileDisplay" style="font-size:0.9rem; color:#666; margin-left:20px;"></div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:15px;">
                    <button type="button" onclick="document.getElementById('newsModal').style.display='none'" style="padding:12px 30px; border-radius:50px; border:1px solid var(--border-color); background:transparent; cursor:pointer; color:var(--text-muted); font-weight:600;">ยกเลิก</button>
                    <button type="submit" id="btnSubmit" name="add_news" class="btn-create" style="box-shadow:none; width:auto; height:auto; padding:12px 40px;">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    
    <script>
        flatpickr(".flatpickr-filter", {
            locale: "th", dateFormat: "Y-m-d", altInput: true, altFormat: "j F Y", disableMobile: true,
            onReady: function(selectedDates, dateStr, instance) { convertBE(instance); },
            onValueUpdate: function(selectedDates, dateStr, instance) {
                convertBE(instance);
                if(dateStr !== "") { document.getElementById('searchForm').submit(); }
            }
        });

        flatpickr(".flatpickr-th-time", { 
            locale: "th", enableTime: true, time_24hr: true, dateFormat: "Y-m-d H:i", altInput: true, altFormat: "j F Y เวลา H:i น.", disableMobile: true,
            onValueUpdate: function(d, s, instance) { if(instance.altInput) { let val = instance.altInput.value; let yearMatch = val.match(/\d{4}/); if(yearMatch) { let yearAD = parseInt(yearMatch[0]); if(yearAD < 2400) { instance.altInput.value = val.replace(yearAD, yearAD + 543); } } } }
        });
        
        function convertBE(instance) {
            if (instance.altInput && instance.altInput.value) {
                let val = instance.altInput.value;
                let yearMatch = val.match(/\d{4}/); 
                if (yearMatch) {
                    let yearAD = parseInt(yearMatch[0]);
                    if (yearAD < 2400) { instance.altInput.value = val.replace(yearAD, yearAD + 543); }
                }
            }
        }

        function viewNews(id) {
            const title = document.getElementById('hidden_title_' + id).value;
            const content = document.getElementById('hidden_content_' + id).value;
            const date = document.getElementById('hidden_date_' + id).value;
            const by = document.getElementById('hidden_by_' + id).value;
            const type = document.getElementById('hidden_type_' + id).value;
            const color = document.getElementById('hidden_color_' + id).value;
            const filesJson = document.getElementById('hidden_files_' + id).value;
            const files = filesJson ? JSON.parse(filesJson) : [];

            document.getElementById('view_title').innerText = title;
            document.getElementById('view_content').innerHTML = content.replace(/\n/g, "<br>");
            document.getElementById('view_date').innerText = date;
            document.getElementById('view_by').innerText = by;
            
            const badge = document.getElementById('view_badge');
            badge.innerText = type;
            badge.className = 'badge-type ' + color;

            // --- 🔥 ส่วนแสดง Gallery (แนวตั้ง เต็มจอ) ---
            const galleryDiv = document.getElementById('view_gallery_container');
            const mainImg = document.getElementById('view_img'); 
            
            galleryDiv.innerHTML = '';
            mainImg.style.display = 'none'; 

            galleryDiv.style.display = 'block'; 
            galleryDiv.style.overflowX = 'visible'; 
            galleryDiv.style.flexWrap = 'nowrap';

            if (files.length > 0) {
                files.forEach(f => {
                    let ext = f.split('.').pop().toLowerCase();
                    if(['jpg','png','jpeg','gif','webp'].includes(ext)) {
                        let img = document.createElement('img');
                        img.src = 'uploads/news/' + f;
                        
                        // Style: กว้างเต็มจอ, มีระยะห่าง
                        img.style.width = '100%'; 
                        img.style.height = 'auto'; 
                        img.style.marginBottom = '20px';
                        img.style.borderRadius = '15px';
                        img.style.border = '1px solid #e2e8f0';
                        img.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
                        
                        galleryDiv.appendChild(img);
                        
                    } else if (ext === 'pdf') {
                        let a = document.createElement('a');
                        a.href = 'uploads/news/' + f;
                        a.target = '_blank';
                        a.className = 'pdf-link';
                        a.style.display = 'flex';
                        a.style.width = '100%';
                        a.style.marginBottom = '10px';
                        a.style.justifyContent = 'center';
                        a.innerHTML = '<i class="fas fa-file-pdf"></i> ดาวน์โหลดเอกสาร PDF';
                        galleryDiv.appendChild(a);
                    }
                });
            }
            // ----------------------------------------

            document.getElementById('viewNewsModal').style.display = 'block';
        }

        // ตัวแปรเก็บไฟล์ (สำหรับการเลือกทีละไฟล์)
        let accumulatedFiles = new DataTransfer();

        function handleFileSelect(event) {
            const input = event.target;
            const newFiles = input.files;
            
            for (let i = 0; i < newFiles.length; i++) {
                accumulatedFiles.items.add(newFiles[i]);
            }
            input.files = accumulatedFiles.files;
            renderFileList();
        }

        function renderFileList() {
            const container = document.getElementById('selected_file_list');
            container.innerHTML = ''; 

            Array.from(accumulatedFiles.files).forEach((file, index) => {
                let div = document.createElement('div');
                div.style.cssText = "display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.03); padding:8px 15px; border-radius:10px; font-size:0.9rem;";
                
                let icon = file.type.includes('pdf') ? '<i class="fas fa-file-pdf" style="color:#ef4444; margin-right:10px;"></i>' : '<i class="fas fa-image" style="color:#3b82f6; margin-right:10px;"></i>';
                
                div.innerHTML = `
                    <div>${icon} ${file.name} <span style="font-size:0.8rem; color:#999;">(${(file.size/1024).toFixed(1)} KB)</span></div>
                    <i class="fas fa-times" onclick="removeFile(${index})" style="cursor:pointer; color:#999; transition:0.2s;" onmouseover="this.style.color='red'" onmouseout="this.style.color='#999'"></i>
                `;
                container.appendChild(div);
            });
        }

        function removeFile(index) {
            const newDT = new DataTransfer();
            Array.from(accumulatedFiles.files).forEach((file, i) => {
                if (i !== index) newDT.items.add(file);
            });
            accumulatedFiles = newDT;
            document.getElementById('real_file_input').files = accumulatedFiles.files;
            renderFileList();
        }

        function openCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle" style="color:var(--primary-color)"></i> สร้างประกาศใหม่';
            document.getElementById('edit_id').value = '';
            document.getElementById('inp_title').value = '';
            document.getElementById('inp_content').value = '';
            document.getElementById('inp_type').value = ''; 
            
            // ล้างค่า Pin และไฟล์
            document.getElementById('inp_pinned').checked = false;
            accumulatedFiles = new DataTransfer(); 
            document.getElementById('selected_file_list').innerHTML = '';
            document.getElementById('real_file_input').value = '';

            const now = new Date();
            const offsetMs = now.getTimezoneOffset() * 60 * 1000;
            const localISOTime = (new Date(now.getTime() - offsetMs)).toISOString().slice(0, -1);
            document.querySelector("#inp_date")._flatpickr.setDate(localISOTime.slice(0,16).replace('T', ' '), true);
            
            document.getElementById('currentFileDisplay').innerText = '';
            document.getElementById('delete_option_area').style.display = 'none';
            
            document.getElementById('btnSubmit').name = 'add_news';
            document.getElementById('newsModal').style.display = 'block';
        }

        function openEditModal(id, title, content, typeId, dateStr, oldFile, isPinned) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color:var(--accent-color)"></i> แก้ไขประกาศ';
            document.getElementById('edit_id').value = id;
            document.getElementById('inp_title').value = title;
            document.getElementById('inp_content').value = content;
            document.getElementById('inp_type').value = typeId; 
            document.querySelector("#inp_date")._flatpickr.setDate(dateStr, true);
            
            // Set Pin
            document.getElementById('inp_pinned').checked = (isPinned == 1);

            // Reset Upload
            accumulatedFiles = new DataTransfer(); 
            document.getElementById('selected_file_list').innerHTML = '';
            document.getElementById('real_file_input').value = '';

            if(oldFile) {
                let count = oldFile.split(',').length;
                document.getElementById('currentFileDisplay').innerHTML = '<i class="fas fa-paperclip"></i> มีไฟล์เดิมอยู่ ' + count + ' ไฟล์';
                document.getElementById('delete_option_area').style.display = 'block';
            } else {
                document.getElementById('currentFileDisplay').innerText = '';
                document.getElementById('delete_option_area').style.display = 'none';
            }
            
            document.getElementById('btnSubmit').name = 'edit_news';
            document.getElementById('newsModal').style.display = 'block';
        }

        function confirmDelete(id) {
            const isDarkMode = document.body.classList.contains('dark-mode');
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลข่าวสารจะถูกลบออกถาวร!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: isDarkMode ? '#475569' : '#94a3b8',
                confirmButtonText: 'ลบทันที',
                cancelButtonText: 'ยกเลิก',
                background: isDarkMode ? '#1e293b' : '#ffffff',
                color: isDarkMode ? '#ffffff' : '#1e293b',
                customClass: { popup: 'swal2-rounded' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete=' + id;
                }
            });
        }

    </script>
</body>
</html>