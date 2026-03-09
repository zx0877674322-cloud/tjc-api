<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'staff';
$fullname = $_SESSION['fullname'] ?? 'Guest';
$current_page = basename($_SERVER['PHP_SELF']);

require_once 'db_connect.php';
$count_sql = "SELECT COUNT(*) as total FROM winspeed_deletion_requests WHERE status = 'pending'";
$count_res = @$conn->query($count_sql);
$pending_count = 0;
if ($count_res && $count_res->num_rows > 0) {
    $count_row = $count_res->fetch_assoc();
    $pending_count = $count_row['total'] ?? 0;
}

// เช็คงานแจ้ง Service ใหม่ (เฉพาะสถานะ SLA ปกติ คือเวลาคงเหลือมากกว่า 24 ชั่วโมง)
$service_sql = "SELECT COUNT(*) as total FROM service_requests WHERE status = 'pending' AND expected_finish_date > DATE_ADD(NOW(), INTERVAL 1 DAY)";
$service_res = @$conn->query($service_sql);
$service_pending_count = 0;
if ($service_res && $service_res->num_rows > 0) {
    $service_row = $service_res->fetch_assoc();
    $service_pending_count = $service_row['total'] ?? 0;
}
$user_company = 'TJC GROUP';
if (isset($fullname) && isset($conn)) {
    // ดึงชื่อย่อ (company_shortname) หรือชื่อเต็ม (company_name) จากตาราง users เชื่อม companies
    $sql_comp = "SELECT c.company_shortname, c.company_name 
                 FROM users u 
                 LEFT JOIN companies c ON u.company_id = c.id 
                 WHERE u.fullname = ?";

    $stmt_comp = $conn->prepare($sql_comp);
    if ($stmt_comp) {
        $stmt_comp->bind_param("s", $fullname);
        $stmt_comp->execute();
        $res_comp = $stmt_comp->get_result();

        if ($row_comp = $res_comp->fetch_assoc()) {
            if (!empty($row_comp['company_shortname'])) {
                $user_company = $row_comp['company_shortname'];
            } elseif (!empty($row_comp['company_name'])) {
                $user_company = $row_comp['company_name'];
            }
        }
        $stmt_comp->close();
    }
}
// Fetch allowed pages based on permissions
$allowed_pages = [];
if ($role != 'admin') {
    $sql_perm = "SELECT mp.file_name FROM permissions p 
                 JOIN master_pages mp ON p.page_id = mp.id 
                 WHERE p.role_name = '$role'";
    $res_perm = @$conn->query($sql_perm);
    if ($res_perm) {
        while ($row = $res_perm->fetch_assoc()) {
            $allowed_pages[] = $row['file_name'];
        }
    }
}

// 🔥 [ส่วนที่เพิ่ม 1] เช็คว่ามีข่าวใหม่หรือไม่?
$show_news_badge = false;
// ตรวจสอบว่า table 'announcements' มีอยู่จริงและดึงข่าวล่าสุด
$sql_news_chk = "SELECT id FROM announcements ORDER BY created_at DESC LIMIT 1";
// ใช้ @ เพื่อกัน Error กรณี Table ยังไม่ถูกสร้าง หรือ Query ผิดพลาดหน้าเว็บจะได้ไม่พัง
$res_news_chk = @$conn->query($sql_news_chk);

if ($res_news_chk && $res_news_chk->num_rows > 0) {
    $row_news = $res_news_chk->fetch_assoc();
    $latest_id = $row_news['id'];

    // เช็ค Cookie ว่า user เคยอ่านข่าว ID นี้หรือยัง
    // (ถ้ายังไม่มี cookie หรือ ID ใน cookie น้อยกว่า ID ล่าสุด = มีข่าวใหม่)
    $read_id = isset($_COOKIE['tjc_read_news_id']) ? intval($_COOKIE['tjc_read_news_id']) : 0;

    if ($latest_id > $read_id) {
        $show_news_badge = true;
    }
}
// ----------------------------------------



// =========================================================
// 🔥 [ส่วนที่ 1] Logic แจ้งเตือน: งานมอบหมาย (Tasks) ของเดียร์
// =========================================================
$unread_task_count = 0; // งานใหม่ถึงเรา (ลูกน้อง)
$boss_noti_count = 0;   // งานที่มีการอัปเดต (หัวหน้า)

if (!empty($fullname) && $fullname !== 'Guest' && isset($conn)) {
    // 1.1 นับงานใหม่ที่ได้รับมอบหมาย (สำหรับลูกน้อง)
    $sql_user = "SELECT COUNT(*) as count FROM tasks 
                 WHERE assigned_to = ? 
                 AND status IN ('มอบหมาย', 'ดำเนินการ') 
                 AND is_read_user = 0";
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param("s", $fullname);
        $stmt_user->execute();
        $res_user = $stmt_user->get_result();
        if ($row = $res_user->fetch_assoc()) {
            $unread_task_count = $row['count'];
        }
        $stmt_user->close();
    }

    // 1.2 นับงานที่มีการเคลื่อนไหว (สำหรับหัวหน้า/คนสั่ง)
    $role_upper = strtoupper($role);
    if ($role_upper === 'ADMIN' || $role_upper === 'CEO') {
        // Admin เห็นทั้งหมด
        $res_boss = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE is_read_admin = 0");
        if ($res_boss && $row = $res_boss->fetch_assoc())
            $boss_noti_count = $row['count'];
    } else {
        // Manager เห็นเฉพาะงานตัวเอง
        $stmt_boss = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND is_read_admin = 0");
        if ($stmt_boss) {
            $stmt_boss->bind_param("s", $fullname);
            $stmt_boss->execute();
            $res_boss = $stmt_boss->get_result();
            if ($row = $res_boss->fetch_assoc())
                $boss_noti_count = $row['count'];
            $stmt_boss->close();
        }
    }
}

function canSeeMenu($file)
{
    global $role, $allowed_pages;
    if ($role == 'admin')
        return true;
    return in_array($file, $allowed_pages);
}

function isActive($target_pages, $current_page)
{
    if (!is_array($target_pages))
        $target_pages = [$target_pages];
    return in_array($current_page, $target_pages) ? 'active' : '';
}

function getAvatar()
{
    if (isset($_SESSION['avatar']) && !empty($_SESSION['avatar']) && file_exists('uploads/profiles/' . $_SESSION['avatar'])) {
        return 'uploads/profiles/' . $_SESSION['avatar'];
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['fullname']) . '&background=random&color=fff';
}
?>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* =========================================================
       🔥 1. GLOBAL THEME VARIABLES
       ========================================================= */
    :root {
        /* Light Mode */
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

        /* Table Specific (Light) */
        --table-head-bg: #f8fafc;
        --table-head-text: #64748b;
        --table-row-hover: #f8faff;

        /* Modal & Form Specific (Light) */
        --input-bg: #ffffff;
        --input-text: #1e293b;
        --input-border: #e2e8f0;
    }

    /* 🌑 Dark Mode Variables */
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

        /* Table Specific (Dark) */
        --table-head-bg: #1e293b;
        --table-head-text: #e2e8f0;
        --table-row-hover: #334155;

        /* Modal & Form Specific (Dark) */
        --input-bg: #334155;
        --input-text: #f1f5f9;
        --input-border: #475569;
    }

    /* =========================================================
       2. GLOBAL STYLES
       ========================================================= */
    body {
        background-color: var(--bg-body);
        color: var(--text-main);
        font-family: 'Prompt', sans-serif;
        transition: background-color 0.3s, color 0.3s;
        margin: 0;
        padding-left: 350px;
    }

    /* บังคับใช้สีกับ Element หลักๆ */
    .card,
    .stat-card,
    .white-box,
    .filter-card,
    .comp-card,
    .form-container,
    .modal-content {
        background-color: var(--bg-card) !important;
        color: var(--text-main) !important;
        border: 1px solid var(--border-color) !important;
        box-shadow: var(--shadow) !important;
    }

    /* บังคับใช้สีกับ Input/Select/Textarea */
    input,
    select,
    textarea,
    .form-control {
        background-color: var(--input-bg) !important;
        color: var(--input-text) !important;
        border: 1px solid var(--border-color) !important;
    }

    /* บังคับสีหัวข้อ */
    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        color: var(--text-main) !important;
    }

    /* ตาราง (Table) */
    th {
        background-color: var(--table-head-bg) !important;
        color: var(--table-head-text) !important;
        border-bottom: 2px solid var(--border-color) !important;
    }

    td {
        color: var(--text-main) !important;
        border-bottom: 1px solid var(--border-color) !important;
    }

    tr:hover td {
        background-color: var(--table-row-hover) !important;
    }

    /* Responsive Sidebar Padding */
    @media (max-width: 900px) {
        body {
            padding-left: 0;
        }
    }

    /* =========================================================
       3. SIDEBAR STYLES (Normal State)
       ========================================================= */
    #mySidebar {
        height: 100vh;
        /* ความสูงเต็มจอ */
        width: 350px;
        position: fixed;
        top: 0;
        left: 0;
        background: linear-gradient(180deg, #1e3a8a 0%, #172554 100%);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        flex-direction: column;

        z-index: 1000;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
        overflow: hidden;
    }

    /* Header */
    #mySidebar .sidebar-brand {
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 25px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    #mySidebar .brand-logo {
        color: #ffffff !important;
        font-weight: 800;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    #mySidebar .toggle-btn {
        color: rgba(255, 255, 255, 0.7);
        cursor: pointer;
        font-size: 1.2rem;
    }

    #mySidebar .sidebar-menu {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;

        padding: 25px 15px;
        list-style: none;
        margin: 0;
        min-height: 0;
    }

    #mySidebar .sidebar-menu::-webkit-scrollbar {
        width: 4px;
    }

    #mySidebar .sidebar-menu::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
    }

    #mySidebar .menu-header {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #93c5fd !important;
        margin: 25px 0 10px 15px;
        letter-spacing: 1px;
        opacity: 0.9;
    }

    #mySidebar .menu-header:first-child {
        margin-top: 5px;
    }

    #mySidebar .sidebar-menu li a {
        display: flex;
        align-items: center;
        padding: 12px 18px;
        color: #ffffff !important;
        text-decoration: none;
        border-radius: 12px;
        margin-bottom: 6px;
        transition: all 0.2s ease;
        font-size: 0.95rem;
        font-weight: 500;
        border: 1px solid transparent;
    }

    #mySidebar .sidebar-menu li a i {
        color: #ffffff !important;
        width: 24px;
        text-align: center;
        margin-right: 12px;
        font-size: 1.1rem;
    }

    #mySidebar .sidebar-menu li a:hover {
        background-color: rgba(255, 255, 255, 0.15);
        transform: translateX(5px);
    }

    #mySidebar .sidebar-menu li a.active {
        background: #ffffff !important;
        color: #1e3a8a !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        font-weight: 700;
    }

    #mySidebar .sidebar-menu li a.active i,
    #mySidebar .sidebar-menu li a.active span {
        color: #1e3a8a !important;
    }

    /* Footer Profile */
    #mySidebar .sidebar-footer {
        /* 🔥 จุดที่เพิ่ม: ห้ามหดตัวเด็ดขาด จะได้ติดขอบล่างเสมอ */
        flex-shrink: 0;

        padding: 20px;
        background: rgba(0, 0, 0, 0.2);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    #mySidebar .user-img {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.3);
        object-fit: cover;
    }

    #mySidebar .user-info {
        flex: 1;
        overflow: hidden;
    }

    #mySidebar .user-name {
        color: #ffffff !important;
        font-weight: 700;
        font-size: 0.9rem;
    }

    #mySidebar .user-role {
        color: rgba(255, 255, 255, 0.7) !important;
        font-size: 0.75rem;
    }

    #mySidebar .footer-actions {
        display: flex;
        gap: 8px;
    }

    #mySidebar .icon-btn {
        color: rgba(255, 255, 255, 0.8);
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.2s;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: transparent;
    }

    #mySidebar .icon-btn:hover {
        background-color: rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }

    /* LOGOUT BUTTON */
    #mySidebar .logout-btn {
        background-color: rgba(239, 68, 68, 0.15) !important;
        border: 1px solid #ef4444 !important;
        color: #fca5a5 !important;
    }

    #mySidebar .logout-btn:hover {
        background-color: #ef4444 !important;
        border-color: #ef4444 !important;
        color: #ffffff !important;
    }

    #mySidebar .logout-btn i {
        color: inherit !important;
    }

    /* =========================================================
       4. DROPDOWN STYLES (Normal State)
       ========================================================= */
    .has-dropdown {
        position: relative;
    }

    /* ซ่อนปกติ */
    .sidebar-menu .submenu {
        display: none;
        list-style: none;
        padding: 0;
        margin: 0;
        background-color: rgba(0, 0, 0, 0.15);
    }

    /* แสดงเมื่อมี class 'show' */
    .sidebar-menu .submenu.show {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    .sidebar-menu .submenu li a {
        padding-left: 50px !important;
        font-size: 0.9rem;
        border-left: 3px solid transparent;
    }

    .sidebar-menu .submenu li a.active {
        border-left: 3px solid var(--primary-color);
        background-color: rgba(255, 255, 255, 0.05) !important;
    }

    .dropdown-icon {
        transition: transform 0.3s ease;
        margin-left: auto;
        font-size: 0.75rem !important;
        opacity: 0.7;
    }

    .has-dropdown.open .dropdown-icon {
        transform: rotate(180deg);
    }

    /* =========================================================
       🔥 5. SIDEBAR COLLAPSED (โหมดพับจอ & Floating Menu)
       ========================================================= */

    body.sidebar-collapsed {
        padding-left: 85px;
    }

    body.sidebar-collapsed #mySidebar {
        width: 85px;
    }

    /* --- ซ่อน Text/Elements ที่ไม่ใช้ตอนพับ --- */
    body.sidebar-collapsed .menu-text,
    body.sidebar-collapsed .menu-header,
    body.sidebar-collapsed .brand-logo span,
    body.sidebar-collapsed .user-info,
    body.sidebar-collapsed .nav-badge,
    body.sidebar-collapsed .has-dropdown .dropdown-icon {
        display: none !important;
    }

    body.sidebar-collapsed .sidebar-brand {
        justify-content: center !important;
        padding: 0 !important;
    }

    body.sidebar-collapsed .brand-logo {
        display: none !important;
    }

    body.sidebar-collapsed .toggle-btn {
        margin: 0 !important;
        font-size: 1.6rem !important;
    }

    /* 🔥 1. ล็อคตำแหน่งปุ่มแม่ (LI) ให้เป็นจุดอ้างอิง */
    body.sidebar-collapsed .sidebar-menu li {
        position: relative;
    }

    body.sidebar-collapsed .sidebar-menu li a {
        justify-content: center;
        padding: 15px 0;
    }

    body.sidebar-collapsed .sidebar-menu li a i {
        margin-right: 0;
        font-size: 1.4rem;
    }

    body.sidebar-collapsed .sidebar-footer {
        flex-direction: column;
        justify-content: center;
        padding: 15px 0;
        gap: 15px;
    }

    body.sidebar-collapsed .footer-actions {
        flex-direction: column;
        gap: 10px;
    }

    /* 🔥 จุดสำคัญ: ต้องเปิด Overflow ให้เมนูที่ลอย (Submenu) ทะลุออกมาได้ */
    body.sidebar-collapsed #mySidebar .sidebar-menu {
        overflow-y: auto !important;
        /* ให้เลื่อนขึ้นลงได้ */
        overflow-x: hidden !important;
        /* ซ่อนแกนขวาง */
        padding-bottom: 20px;
        /* เผื่อที่ด้านล่างนิดหน่อย */
    }

    /* 🔥 แก้ไข 2: ปรับเมนูย่อย (Submenu) ให้เป็น Fixed เพื่อไม่ให้โดนตัดบังเมื่อมี Scrollbar */
    body.sidebar-collapsed .sidebar-menu .submenu {
        position: fixed !important;
        /* ใช้ fixed เพื่อให้ลอยเหนือทุกอย่าง */
        left: 85px !important;
        /* ระยะห่างจากซ้ายคงที่ */
        top: 0;
        bottom: auto !important;
        width: 240px;

        /* 👇 บรรทัดนี้คือตัวแก้ปัญหาครับ (ใส่สีน้ำเงินเข้มทึบ) */
        background-color: #172554 !important;

        border-radius: 0 10px 10px 10px;
        box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        z-index: 9999;
        /* ชั้นบนสุด */
        padding: 10px 0;
        display: none;
        max-height: 80vh;
        /* จำกัดความสูง */
        overflow-y: auto;
        /* ให้เลื่อนได้ถ้าเมนูยาว */
        scrollbar-width: thin;
    }

    /* 🔥 แก้ไข 3: ปรับ Footer ให้ทึบและอยู่ชั้นบนสุด ป้องกันเมนูไหลมาซ้อน */
    #mySidebar .sidebar-footer {
        position: relative;
        z-index: 50;
        /* ลอยเหนือเมนู */
        background: #172554 !important;
        /* ใส่สีพื้นหลังทึบ (สีเดียวกับปลาย Gradient) */
    }

    /* 🔥 4. (แก้ไข) สำหรับเมนู 5 ตัวสุดท้าย -> ให้เด้ง "สวนขึ้นบน" แทน (ป้องกันตกขอบจอ) */
    body.sidebar-collapsed .sidebar-menu li:nth-last-child(-n+5) .submenu {
        top: auto !important;
        /* ยกเลิกการเกาะบน */
        bottom: 0 !important;
        /* สั่งให้เกาะล่าง (เสมอกับปุ่มแม่) */
        border-radius: 10px 10px 10px 0;
        /* ปรับมุมโค้งให้สวยงาม */
        transform-origin: bottom left;
        /* อนิเมชั่นเริ่มจากล่าง */
    }

    /* แสดงผลเมื่อมี class 'show' */
    body.sidebar-collapsed .sidebar-menu .submenu.show {
        display: block !important;
        animation: fadeInLeft 0.2s ease;
    }

    /* ปรับแต่ง Scrollbar */
    body.sidebar-collapsed .sidebar-menu .submenu::-webkit-scrollbar {
        width: 6px;
    }

    body.sidebar-collapsed .sidebar-menu .submenu::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
    }

    body.sidebar-collapsed .sidebar-menu .submenu::-webkit-scrollbar-thumb {
        background-color: rgba(255, 255, 255, 0.3);
        border-radius: 4px;
    }

    body.sidebar-collapsed .sidebar-menu .submenu::-webkit-scrollbar-thumb:hover {
        background-color: rgba(255, 255, 255, 0.5);
    }

    /* จัด Text ข้างในเมนูลอย */
    body.sidebar-collapsed .sidebar-menu .submenu li a {
        padding-left: 20px !important;
        padding-right: 15px !important;
        justify-content: flex-start !important;
        display: flex !important;
    }

    body.sidebar-collapsed .sidebar-menu .submenu li a span,
    body.sidebar-collapsed .sidebar-menu .submenu li a div {
        display: inline-block !important;
        font-size: 0.9rem;
    }

    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* =========================================================
       6. MISC / BADGES / ANIMATION
       ========================================================= */
    .nav-badge {
        background-color: #ef4444;
        color: white;
        font-size: 0.65rem;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: auto;
        margin-right: 10px;
        display: inline-block;
        animation: pulse-red 2s infinite;
    }

    @keyframes pulse-red {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
        }

        70% {
            transform: scale(1);
            box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
        }

        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* SweetAlert2 Dark Mode */
    body.dark-mode .swal2-popup {
        background-color: var(--bg-card) !important;
        color: var(--text-main) !important;
        border: 1px solid var(--border-color) !important;
    }

    body.dark-mode .swal2-title,
    body.dark-mode .swal2-html-container,
    body.dark-mode .swal2-close {
        color: var(--text-main) !important;
    }

    /* Mobile Header */
    .mobile-header {
        display: none;
    }

    @media (max-width: 900px) {
        #mySidebar {
            left: -100%;
        }

        #mySidebar.show {
            left: 0;
        }

        .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #1e3a8a;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            backdrop-filter: blur(3px);
        }

        .overlay.show {
            display: block;
        }
    }

    /* Floating Header Style (แสดงเฉพาะตอนพับจอ) */
    .floating-header {
        display: none;
    }

    body.sidebar-collapsed .floating-header {
        display: block;
        padding: 12px 20px;
        font-size: 0.95rem;
        font-weight: 700;
        color: #60a5fa;
        background-color: rgba(0, 0, 0, 0.2);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 5px;
        text-align: left;
        border-radius: 10px 10px 0 0;
    }

    /* ป้ายแจ้งเตือนที่ Sidebar */
    .sidebar-badge {
        background-color: #ef4444;
        /* สีแดงสด */
        color: white;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        min-width: 18px;
        text-align: center;
        line-height: 1.5;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4);
    }

    /* (แถม) เอฟเฟกต์กระพริบเบาๆ ให้รู้ว่ามีของใหม่ */
    @keyframes pulse-red {
        0% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
        }

        70% {
            box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }

    .pulse-animation {
        animation: pulse-red 2s infinite;
    }
</style>

<div class="mobile-header">
    <div style="display:flex; align-items:center; gap:15px;">
        <i class="fas fa-bars" style="font-size:24px; cursor:pointer;" onclick="toggleMobile()"></i>
        <span style="font-weight:700; font-size:1.2rem;">TJC GROUP</span>
    </div>
    <img src="<?php echo getAvatar(); ?>"
        style="width:35px; height:35px; border-radius:50%; border:2px solid rgba(255,255,255,0.5);">
</div>

<div class="sidebar" id="mySidebar">
    <div class="sidebar-brand">
        <a class="brand-logo">
            <img src="images/S__49692692.jpg"
                style="height:35px; width:auto; border-radius:6px; background:white; padding:2px;">
            <span>TJC GROUP</span>
        </a>
        <i class="fas fa-indent toggle-btn" onclick="toggleDesktop()"></i>
    </div>

    <ul class="sidebar-menu">


        <?php if (canSeeMenu('boss_task_form.php') || canSeeMenu('boss dashboard.php')):
            $boss_pages = ['boss dashboard.php', 'boss_task_form.php'];
            $boss_open = in_array($current_page, $boss_pages) ? 'open' : '';
            $boss_show = in_array($current_page, $boss_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $boss_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-user-tie"></i> <span class="menu-text">ทีมผู้บริหาร</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <?php if ($boss_noti_count > 0): ?><span
                                class="nav-badge"><?php echo $boss_noti_count; ?></span><?php endif; ?>
                        <?php if ($unread_task_count > 0): ?><span class="nav-badge"
                                style="background-color: #f59e0b;"><?php echo $unread_task_count; ?></span><?php endif; ?>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </div>
                </a>
                <ul class="submenu <?php echo $boss_show; ?>">
                    <?php if (canSeeMenu('boss dashboard.php')): ?>
                        <li>
                            <a href="boss dashboard.php" class="<?php echo isActive('boss dashboard.php', $current_page); ?>"
                                style="display:flex; justify-content:space-between;">
                                <div style="display:flex; align-items:center;"><i class="fas fa-chart-pie"></i> Dashboard
                                    ผู้บริหาร</div>
                                <div style="display:flex; gap:3px;">
                                    <?php if ($boss_noti_count > 0): ?><span class="nav-badge"
                                            style="transform:scale(0.8);"><?php echo $boss_noti_count; ?></span><?php endif; ?>
                                    <?php if ($unread_task_count > 0): ?><span class="nav-badge"
                                            style="background-color: #f59e0b; transform:scale(0.8);"><?php echo $unread_task_count; ?></span><?php endif; ?>
                                </div>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('boss_task_form.php')): ?>
                        <li><a href="boss_task_form.php" class="<?php echo isActive('boss_task_form.php', $current_page); ?>"><i
                                    class="fas fa-edit"></i> สมุดลงงานผู้บริหาร</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('Announcement.php') || canSeeMenu('ManageHRTypes.php') || canSeeMenu('hr_create_report.php') || canSeeMenu('hr_dashboad.php')):
            $hr_pages = ['Announcement.php', 'ManageHRTypes.php'];
            $hr_open = in_array($current_page, $hr_pages) ? 'open' : '';
            $hr_show = in_array($current_page, $hr_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $hr_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-users"></i> <span class="menu-text">ทีมทรัพยากรบุคคล</span>
                    </div>
                    <div style="display:flex; align-items:center;">
                        <?php if (isset($show_news_badge) && $show_news_badge): ?><span
                                class="nav-badge">NEW</span><?php endif; ?>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </div>
                </a>
                <ul class="submenu <?php echo $hr_show; ?>">
                    <?php if (canSeeMenu('Announcement.php')): ?>
                        <li>
                            <a href="Announcement.php" class="<?php echo isActive('Announcement.php', $current_page); ?>"
                                style="display:flex; justify-content:space-between;">
                                <div style="display:flex; align-items:center;"><i class="fas fa-bullhorn"></i> ข่าวประชาสัมพันธ์
                                </div>
                                <?php if (isset($show_news_badge) && $show_news_badge): ?><span class="nav-badge"
                                        style="transform:scale(0.8);">NEW</span><?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('ManageHRTypes.php')): ?>
                        <li><a href="ManageHRTypes.php" class="<?php echo isActive('ManageHRTypes.php', $current_page); ?>"><i
                                    class="fas fa-bullhorn"></i> จัดการประเภทข่าว</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('hr_dashboad.php')): ?>
                        <li>
                            <a href="hr_dashboad.php" class="<?php echo isActive('hr_dashboad.php', $current_page); ?>"
                                style="display:flex; justify-content:space-between;">
                                <div style="display:flex; align-items:center;"><i
                                        class="fas fa-chart-pie"></i>สรุปรายงานทรัพยากรบุคคล
                                </div>
                                <?php if (isset($show_news_badge) && $show_news_badge): ?><span class="nav-badge"
                                        style="transform:scale(0.8);">NEW</span><?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('hr_create_report.php')): ?>
                        <li>
                            <a href="hr_create_report.php"
                                class="<?php echo isActive('hr_create_report.php', $current_page); ?>"
                                style="display:flex; justify-content:space-between;">
                                <div style="display:flex; align-items:center;"><i class="fas fa-edit"></i> รายงานประจำวัน
                                </div>
                                <?php if (isset($show_news_badge) && $show_news_badge): ?><span class="nav-badge"
                                        style="transform:scale(0.8);">NEW</span><?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('report_admin.php') || canSeeMenu('Dashboard_Admin.php')):
            $admin_pages = ['Dashboard_Admin.php', 'report_admin.php'];
            $admin_open = in_array($current_page, $admin_pages) ? 'open' : '';
            $admin_show = in_array($current_page, $admin_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $admin_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-folder-open"></i> <span class="menu-text">ทีมธุรการ</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $admin_show; ?>">
                    <?php if (canSeeMenu('Dashboard_Admin.php')): ?>
                        <li><a href="Dashboard_Admin.php"
                                class="<?php echo isActive('Dashboard_Admin.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> Dashboard ธุรการ</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('report_admin.php')): ?>
                        <li><a href="report_admin.php" class="<?php echo isActive('report_admin.php', $current_page); ?>"><i
                                    class="fas fa-edit"></i> รายงานประจำวัน</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('Report_Purchase.php') || canSeeMenu('Dashboard_Purchase.php')):
            $purch_pages = ['Dashboard_Purchase.php', 'Report_Purchase.php'];
            $purch_open = in_array($current_page, $purch_pages) ? 'open' : '';
            $purch_show = in_array($current_page, $purch_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $purch_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-shopping-cart"></i> <span class="menu-text">ทีมจัดซื้อ</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $purch_show; ?>">
                    <?php if (canSeeMenu('Dashboard_Purchase.php')): ?>
                        <li><a href="Dashboard_Purchase.php"
                                class="<?php echo isActive('Dashboard_Purchase.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> Dashboard จัดซื้อ</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('Report_Purchase.php')): ?>
                        <li><a href="Report_Purchase.php"
                                class="<?php echo isActive('Report_Purchase.php', $current_page); ?>"><i
                                    class="fas fa-edit"></i> รายงานประจำวัน</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('Online_Marketing_Report.php') || canSeeMenu('Dashboard_Marketing.php') || canSeeMenu('manage_platforms.php')):
            $ecom_pages = ['Dashboard_Marketing.php', 'Online_Marketing_Report.php', 'manage_platforms.php'];
            $ecom_open = in_array($current_page, $ecom_pages) ? 'open' : '';
            $ecom_show = in_array($current_page, $ecom_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $ecom_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-globe"></i> <span class="menu-text">ทีม E-Commerce</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $ecom_show; ?>">
                    <?php if (canSeeMenu('Dashboard_Marketing.php')): ?>
                        <li><a href="Dashboard_Marketing.php"
                                class="<?php echo isActive('Dashboard_Marketing.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> Dashboard การตลาดออนไลน์</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('Online_Marketing_Report.php')): ?>
                        <li><a href="Online_Marketing_Report.php"
                                class="<?php echo isActive('Online_Marketing_Report.php', $current_page); ?>"><i
                                    class="fas fa-edit"></i> รายงานประจำวัน</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('manage_platforms.php')): ?>
                        <li><a href="manage_platforms.php"
                                class="<?php echo isActive('manage_platforms.php', $current_page); ?>"><i
                                    class="fas fa-edit"></i> จัดการแพลตฟอร์ม</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php
        if (canSeeMenu('Dashboard.php') || canSeeMenu('Report.php') || canSeeMenu('ManageCustomers.php') || canSeeMenu('MapDashboard.php') || canSeeMenu('work_plan_add.php') || canSeeMenu('work_plan_dashboard.php') || canSeeMenu('StatusManager.php')):

            $sale_pages = ['Dashboard.php', 'Report.php', 'MapDashboard.php', 'StatusManager.php', 'ManageCustomers.php', 'work_plan_dashboard.php', 'work_plan_add.php'];
            $sale_open = in_array($current_page, $sale_pages) ? 'open' : '';
            $sale_show = in_array($current_page, $sale_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $sale_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-chart-line"></i> <span class="menu-text">ทีมการตลาด</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $sale_show; ?>">

                    <?php if (canSeeMenu('Dashboard.php')): ?>
                        <li><a href="Dashboard.php" class="<?php echo isActive('Dashboard.php', $current_page); ?>">
                                <i class="fas fa-chart-pie"></i> Dashboard ประจำวันการตลาด</a>
                        </li>
                    <?php endif; ?>

                    <?php if (canSeeMenu('Report.php')): ?>
                        <li><a href="Report.php" class="<?php echo isActive('Report.php', $current_page); ?>">
                                <i class="fas fa-edit"></i> รายงานประจำวัน</a>
                        </li>
                    <?php endif; ?>

                    <?php if (canSeeMenu('MapDashboard.php')): ?>
                        <li><a href="MapDashboard.php" class="<?php echo isActive('MapDashboard.php', $current_page); ?>">
                                <i class="fas fa-map-marked-alt"></i> แผนที่ติดตามการตลาด</a>
                        </li>
                    <?php endif; ?>

                    <?php if (canSeeMenu('work_plan_dashboard.php')): ?>
                        <li><a href="work_plan_dashboard.php"
                                class="<?php echo isActive('work_plan_dashboard.php', $current_page); ?>">
                                <i class="fas fa-chart-pie"></i> Dashboard งานการตลาด</a>
                        </li>
                    <?php endif; ?>

                    <?php if (canSeeMenu('work_plan_add.php')): ?>
                        <li><a href="work_plan_add.php" class="<?php echo isActive('work_plan_add.php', $current_page); ?>">
                                <i class="fas fa-edit"></i> สมุดลงแพลนงานการตลาด</a>
                        </li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('StatusManager.php')): ?>
                        <li><a href="StatusManager.php" class="<?php echo isActive('StatusManager.php', $current_page); ?>">
                                <i class="fas fa-tasks"></i> จัดการสถานะ</a>
                        </li>
                    <?php endif; ?>

                    <?php if (canSeeMenu('ManageCustomers.php')): ?>
                        <li><a href="ManageCustomers.php" class="<?php echo isActive('ManageCustomers.php', $current_page); ?>">
                                <i class="fas fa-tasks"></i> จัดการชื่อหน่วยงาน</a>
                        </li>
                    <?php endif; ?>

                </ul>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('CashFlow.php') || canSeeMenu('CompanyManage.php')):
            $acc_pages = ['CashFlow.php', 'TaxCheck.php', 'CompanyManage.php'];
            $acc_open = in_array($current_page, $acc_pages) ? 'open' : '';
            $acc_show = in_array($current_page, $acc_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $acc_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-wallet"></i> <span class="menu-text">ทีมบัญชีและการเงิน</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $acc_show; ?>">
                    <?php if (canSeeMenu('CashFlow.php')): ?>
                        <li><a href="CashFlow.php" class="<?php echo isActive('CashFlow.php', $current_page); ?>"><i
                                    class="fas fa-wallet"></i> Dashboard เงินเข้า-ออก</a></li><?php endif; ?>
                    <?php if (canSeeMenu('TaxCheck.php')): ?>
                        <li><a href="TaxCheck.php" class="<?php echo isActive('TaxCheck.php', $current_page); ?>"><i
                                    class="fas fa-building"></i> ตรวจใบกำกับภาษี</a></li><?php endif; ?>
                    <?php if (canSeeMenu('CompanyManage.php')): ?>
                        <li><a href="CompanyManage.php" class="<?php echo isActive('CompanyManage.php', $current_page); ?>"><i
                                    class="fas fa-building"></i> จัดการบริษัท</a></li><?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('fm_dashboard.php') || canSeeMenu('fm_jobs.php') || canSeeMenu('delivery_dashboard.php') || canSeeMenu('delivery_create_report.php')):
            $fm_pages = ['fm_dashboard.php', 'fm_jobs.php', 'fm_accommodation.php', 'fm_report_travel.php', 'fm_drivers.php'];
            $fm_open = in_array($current_page, $fm_pages) ? 'open' : '';
            $fm_show = in_array($current_page, $fm_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $fm_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this); markJobsAsRead();"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-truck"></i> <span class="menu-text">ทีมจัดส่ง</span>
                    </div>
                    <div style="display:flex; align-items:center;">
                        <span id="parentTransportBadge" class="nav-badge" style="display:none;">0</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </div>
                </a>
                <ul class="submenu <?php echo $fm_show; ?>">
                    <?php if (canSeeMenu('fm_dashboard.php')): ?>
                        <li>
                            <a href="fm_dashboard.php" class="<?php echo isActive('fm_dashboard.php', $current_page); ?>"
                                style="display:flex; justify-content:space-between;">
                                <div style="display:flex; align-items:center;"><i class="fas fa-chart-pie"></i> Dashboard จัดส่ง
                                </div>
                                <span id="transport-badge" class="nav-badge"
                                    style="display:none; transform:scale(0.8);">0</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('fm_jobs.php')): ?>
                        <li><a href="fm_jobs.php" class="<?php echo isActive('fm_jobs.php', $current_page); ?>"><i
                                    class="fas fa-calendar-alt"></i> ตารางขนส่ง</a></li><?php endif; ?>
                    <?php if (canSeeMenu('fm_accommodation.php')): ?>
                        <li><a href="fm_accommodation.php"
                                class="<?php echo isActive('fm_accommodation.php', $current_page); ?>"><i
                                    class="fas fa-receipt"></i> แจ้งบิลค่าที่พัก</a></li><?php endif; ?>
                    <?php if (canSeeMenu('fm_report_travel.php')): ?>
                        <li><a href="fm_report_travel.php"
                                class="<?php echo isActive('fm_report_travel.php', $current_page); ?>"><i
                                    class="fas fa-receipt"></i> เบี้ยเลี้ยงขนส่ง</a></li><?php endif; ?>
                    <?php if (canSeeMenu('fm_drivers.php')): ?>
                        <li><a href="fm_drivers.php" class="<?php echo isActive('fm_drivers.php', $current_page); ?>"><i
                                    class="fas fa-id-badge"></i> ข้อมูลพนักงาน</a></li><?php endif; ?>
                    <?php if (canSeeMenu('delivery_dashboard.php')): ?>
                        <li><a href="delivery_dashboard.php"
                                class="<?php echo isActive('delivery_dashboard.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> สรุปรายงานจัดส่ง</a></li><?php endif; ?>
                    <?php if (canSeeMenu('delivery_create_report.php')): ?>
                        <li><a href="delivery_create_report.php"
                                class="<?php echo isActive('delivery_create_report.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> รายงานจัดส่ง</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('warehouse_dashboard.php') || canSeeMenu('warehouse_create_report.php')):
            $wh_pages = ['warehouse_dashboard.php', 'warehouse_create_report.php'];
            $wh_open = in_array($current_page, $wh_pages) ? 'open' : '';
            $wh_show = in_array($current_page, $wh_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $wh_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this);"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-warehouse"></i> <span class="menu-text">ทีมคลังสินค้า</span>
                    </div>
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </div>
                </a>
                <ul class="submenu <?php echo $wh_show; ?>">
                    <li>
                        <a href="https://tjcstock.vercel.app/login" target="_blank" style="color: #0d9488;">
                            <i class="fas fa-external-link-alt"></i> ไประบบสต็อก (Vercel)
                        </a>
                    </li>
                    <li>
                        <a href="warehouse_dashboard.php"
                            class="<?php echo isActive('warehouse_dashboard.php', $current_page); ?>">
                            <i class="fas fa-chart-pie"></i> สรุปรายงานคลัง
                        </a>
                    </li>

                    <li>
                        <a href="warehouse_create_report.php"
                            class="<?php echo isActive('warehouse_create_report.php', $current_page); ?>">
                            <i class="fas fa-file-alt"></i> บันทึกงานคลัง
                        </a>
                    </li>

                </ul>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('SubmitDocument.php') || canSeeMenu('DocumentDashboard.php') || canSeeMenu('ManageSuppliers.php') || canSeeMenu('WINSpeedDeleteRequest.php')):
            $po_pages = ['DocumentDashboard.php', 'SubmitDocument.php', 'ManageSuppliers.php', 'WINSpeedDeleteRequest.php'];
            $po_open = in_array($current_page, $po_pages) ? 'open' : '';
            $po_show = in_array($current_page, $po_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $po_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-file-invoice"></i>
                        <span class="menu-text">เอกสาร PO/AX</span>

                        <?php if (isset($pending_count) && $pending_count > 0): ?>
                            <span class="sidebar-badge pulse-animation"
                                style="margin-left: 8px; background-color: #ff4757; color: white; padding: 2px 6px; font-size: 12px;">
                                <?php echo $pending_count; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $po_show; ?>">
                    <?php if (canSeeMenu('DocumentDashboard.php')): ?>
                        <li><a href="DocumentDashboard.php"
                                class="<?php echo isActive('DocumentDashboard.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> Dashboard PO/AX</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('SubmitDocument.php')): ?>
                        <li><a href="SubmitDocument.php" class="<?php echo isActive('SubmitDocument.php', $current_page); ?>"><i
                                    class="fas fa-edit"></i> สมุดลงเอกสาร</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('WINSpeedDeleteRequest.php')): ?>
                        <li>
                            <a href="WINSpeedDeleteRequest.php"
                                class="<?php echo isActive('WINSpeedDeleteRequest.php', $current_page); ?>"
                                style="display: flex; justify-content: space-between; align-items: center;">

                                <span>
                                    <i class="fas fa-trash-alt"></i> เเจ้งลบเอกสาร WINSpeed
                                </span>

                                <?php if (isset($pending_count) && $pending_count > 0): ?>
                                    <span class="sidebar-badge pulse-animation">
                                        <?php echo $pending_count; ?>
                                    </span>
                                <?php endif; ?>

                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('ManageSuppliers.php')): ?>
                        <li><a href="ManageSuppliers.php"
                                class="<?php echo isActive('ManageSuppliers.php', $current_page); ?>"><i
                                    class="fas fa-edit"></i> ร้านค้าและบัญชี</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>


        <?php
        // เพิ่ม canSeeMenu('manage_setup.php') ในเงื่อนไขตรวจสอบ
        if (canSeeMenu('project_dashboard.php') || canSeeMenu('project_details.php') || canSeeMenu('ServiceRequest.php') || canSeeMenu('service_dashboard.php') || canSeeMenu('ProjectShops.php') || canSeeMenu('manage_job_types.php') || canSeeMenu('manage_customers.php') || canSeeMenu('create_project.php') || canSeeMenu('project_dashboard.php') || canSeeMenu('project_job_types.php') || canSeeMenu('manage_setup.php') || canSeeMenu('manage_provinces.php')):

            // เพิ่ม manage_setup.php ลงใน Array เพื่อให้เมนูเปิดค้างไว้เมื่อกด
            $proj_pages = ['project_dashboard.php', 'project_details.php', 'manage_setup.php'];
            $proj_open = in_array($current_page, $proj_pages) ? 'open' : '';
            $proj_show = in_array($current_page, $proj_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $proj_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-project-diagram"></i> <span class="menu-text">โครงการในเครือ TJC GROUP </span>
                        <?php if (isset($service_pending_count) && $service_pending_count > 0): ?>
                            <span class="sidebar-badge pulse-animation"
                                style="margin-left: 8px; background-color: #ff4757; color: white; padding: 2px 6px; font-size: 12px;">
                                <?php echo $service_pending_count; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $proj_show; ?>">
                    <?php if (canSeeMenu('service_dashboard.php')): ?>
                        <li>
                            <a href="service_dashboard.php"
                                class="<?php echo isActive('service_dashboard.php', $current_page); ?>"
                                style="display: flex; justify-content: space-between; align-items: center;">
                                <span><i class="fas fa-chart-pie"></i> Dashboard Service</span>
                                <?php if (isset($service_pending_count) && $service_pending_count > 0): ?>
                                    <span class="sidebar-badge pulse-animation">
                                        <?php echo $service_pending_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('ServiceRequest.php')): ?>
                        <li><a href="ServiceRequest.php" class="<?php echo isActive('ServiceRequest.php', $current_page); ?>"><i
                                    class="fa-brands fa-rocketchat"></i> สมุดแจ้ง Service</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('ProjectShops.php')): ?>
                        <li><a href="ProjectShops.php" class="<?php echo isActive('ProjectShops.php', $current_page); ?>"><i
                                    class="fas fa-tools"></i> ร้านช่าง</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('manage_job_types.php')): ?>
                        <li><a href="manage_job_types.php"
                                class="<?php echo isActive('manage_job_types.php', $current_page); ?>"><i
                                    class="fa-solid fa-address-book"></i> ประเภทงานเเละติดต่อ</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('manage_customers.php')): ?>
                        <li><a href="manage_customers.php"
                                class="<?php echo isActive('manage_customers.php', $current_page); ?>"><i
                                    class="fa-solid fa-people-group"></i> จัดการลูกค้าหน่วยงาน</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('create_project.php')): ?>
                        <li><a href="create_project.php" class="<?php echo isActive('create_project.php', $current_page); ?>"><i
                                    class="fa-solid fa-people-group"></i> บันทึกโครงการ</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('project_dashboard.php')): ?>
                        <li><a href="project_dashboard.php"
                                class="<?php echo isActive('project_dashboard.php', $current_page); ?>"><i
                                    class="fa-solid fa-people-group"></i> Dashboard Project</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('project_job_types.php')): ?>
                        <li><a href="project_job_types.php"
                                class="<?php echo isActive('project_job_types.php', $current_page); ?>"><i
                                    class="fa-solid fa-people-group"></i> ประเภทงาน</a></li>
                    <?php endif; ?>

                    <?php if (canSeeMenu('manage_setup.php')): ?>
                        <li><a href="manage_setup.php" class="<?php echo isActive('manage_setup.php', $current_page); ?>"><i
                                    class="fas fa-cogs"></i> ตั้งค่าข้อมูลพื้นฐาน</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('manage_provinces.php')): ?>
                        <li><a href="manage_provinces.php"
                                class="<?php echo isActive('manage_provinces.php', $current_page); ?>"><i
                                    class="fas fa-cogs"></i> จัดการข้อมูลจังหวัด</a></li>
                    <?php endif; ?>

                </ul>
            </li>
        <?php endif; ?>


        <?php if (canSeeMenu('dashboard_document_out.php') || canSeeMenu('db_document_out.php')):
            $docout_pages = ['dashboard_document_out.php', 'db_document_out.php'];
            $docout_open = in_array($current_page, $docout_pages) ? 'open' : '';
            $docout_show = in_array($current_page, $docout_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $docout_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-file-export"></i> <span class="menu-text">เลขที่หนังสือออก</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $docout_show; ?>">
                    <?php if (canSeeMenu('dashboard_document_out.php')): ?>
                        <li><a href="dashboard_document_out.php"
                                class="<?php echo isActive('dashboard_document_out.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> Dashboard หนังสือออก</a></li>
                    <?php endif; ?>
                    <?php if (canSeeMenu('db_document_out.php')): ?>
                        <li><a href="db_document_out.php"
                                class="<?php echo isActive('db_document_out.php', $current_page); ?>"><i
                                    class="fas fa-file-export"></i> สมุดลงหนังสือออก</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>


        <?php
        $vehicle_pages = ['CarDashboard.php', 'CarBooking.php', 'CarHistory.php', 'AdminCarManage.php', 'fm_vehicles.php', 'repair_requesst.php', 'repair_history.php', 'vehicle_alerts.php'];
        $vehicle_open = in_array($current_page, $vehicle_pages) ? 'open' : '';
        $vehicle_show = in_array($current_page, $vehicle_pages) ? 'show' : '';
        ?>
        <?php if (canSeeMenu('CarBooking.php') || canSeeMenu('CarHistory.php') || canSeeMenu('AdminCarManage.php')): ?>
            <li class="has-dropdown <?php echo $vehicle_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-car-side"></i> <span class="menu-text">ยานพาหนะบริษัท</span>
                    </div>
                    <div style="display:flex; align-items:center;">
                        <span id="parentTaxBadge" class="nav-badge" style="display:none;">0</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </div>
                </a>

                <ul class="submenu <?php echo $vehicle_show; ?>">
                    <?php if (canSeeMenu('CarDashboard.php')): ?>
                        <li><a href="CarDashboard.php" class="<?php echo isActive('CarDashboard.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> Dashboard จองรถ</a></li><?php endif; ?>
                    <?php if (canSeeMenu('CarBooking.php')): ?>
                        <li><a href="CarBooking.php" class="<?php echo isActive('CarBooking.php', $current_page); ?>"><i
                                    class="fas fa-car"></i> จองรถบริษัท</a></li><?php endif; ?>
                    <?php if (canSeeMenu('CarHistory.php')): ?>
                        <li><a href="CarHistory.php" class="<?php echo isActive('CarHistory.php', $current_page); ?>"><i
                                    class="fas fa-history"></i> ประวัติการใช้รถ</a></li><?php endif; ?>
                    <?php if (canSeeMenu('AdminCarManage.php')): ?>
                        <li><a href="AdminCarManage.php" class="<?php echo isActive('AdminCarManage.php', $current_page); ?>"><i
                                    class="fas fa-tasks"></i> จัดการข้อมูลรถ</a></li><?php endif; ?>
                    <?php if (canSeeMenu('fm_vehicles.php')): ?>
                        <li><a href="fm_vehicles.php" class="<?php echo isActive('fm_vehicles.php', $current_page); ?>"><i
                                    class="fas fa-truck-moving"></i> เอกสารยานพาหนะ</a></li><?php endif; ?>
                    <?php if (canSeeMenu('repair_requesst.php')): ?>
                        <li><a href="repair_requesst.php"
                                class="<?php echo isActive('repair_requesst.php', $current_page); ?>"><i
                                    class="fas fa-wrench"></i> แจ้งซ่อมรถ</a></li><?php endif; ?>
                    <?php if (canSeeMenu('repair_history.php')): ?>
                        <li><a href="repair_history.php" class="<?php echo isActive('repair_history.php', $current_page); ?>"><i
                                    class="fas fa-tools"></i> ประวัติซ่อมบำรุง</a></li><?php endif; ?>
                    <?php if (canSeeMenu('vehicle_alerts.php')): ?>
                        <li><a href="vehicle_alerts.php" class="<?php echo isActive('vehicle_alerts.php', $current_page); ?>"
                                style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="display:flex; align-items:center;"><i
                                        class="fas fa-exclamation-triangle"></i>แจ้งเตือน พ.ร.บ. /ประกันภัย </div><span
                                    id="taxAlertBadge" class="badge rounded-pill bg-danger d-none"
                                    style="font-size: 0.7rem;">0</span>
                            </a></li><?php endif; ?>
                </ul>
            </li><?php endif; ?>


        <?php if (canSeeMenu('Immigration_dashboard.php') || canSeeMenu('Immigration_Report.php')):
            $imm_pages = ['Immigration_dashboard.php', 'Immigration_Report.php'];
            $imm_open = in_array($current_page, $imm_pages) ? 'open' : '';
            $imm_show = in_array($current_page, $imm_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $imm_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-passport"></i> <span class="menu-text">งานเข้าเมือง</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $imm_show; ?>">
                    <?php if (canSeeMenu('Immigration_dashboard.php')): ?>
                        <li><a href="Immigration_dashboard.php"
                                class="<?php echo isActive('Immigration_dashboard.php', $current_page); ?>"><i
                                    class="fas fa-chart-pie"></i> Dashboard เข้าเมือง</a></li><?php endif; ?>
                    <?php if (canSeeMenu('Immigration_Report.php')): ?>
                        <li><a href="Immigration_Report.php"
                                class="<?php echo isActive('Immigration_Report.php', $current_page); ?>"><i
                                    class="fas fa-edit"></i> สมุดลงงาน</a></li><?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>


        <?php
        $personal_pages = ['Profile.php', 'StaffHistory.php'];
        $personal_open = in_array($current_page, $personal_pages) ? 'open' : '';
        $personal_show = in_array($current_page, $personal_pages) ? 'show' : '';
        ?>
        <li class="has-dropdown <?php echo $personal_open; ?>">
            <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                style="display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center;">
                    <i class="fas fa-user-circle"></i> <span class="menu-text">ข้อมูลส่วนตัว</span>
                </div>
                <i class="fas fa-chevron-down dropdown-icon"></i>
            </a>
            <ul class="submenu <?php echo $personal_show; ?>">
                <li><a href="Profile.php" class="<?php echo isActive('Profile.php', $current_page); ?>"><i
                            class="fas fa-user-circle"></i> โปรไฟล์ส่วนตัว</a></li>
                <?php if (canSeeMenu('StaffHistory.php')): ?>
                    <li><a href="StaffHistory.php" class="<?php echo isActive('StaffHistory.php', $current_page); ?>"><i
                                class="fas fa-history"></i> ประวัติงานของฉัน</a></li>
                <?php endif; ?>
            </ul>
        </li>

        <?php if (canSeeMenu('AddUser.php') || canSeeMenu('ManagerRoles.php')):
            $sys_pages = ['AddUser.php', 'ManagerRoles.php', 'ManagePermissions.php', 'ManagePages.php', 'ProvinceManager.php'];
            $sys_open = in_array($current_page, $sys_pages) ? 'open' : '';
            $sys_show = in_array($current_page, $sys_pages) ? 'show' : '';
            ?>
            <li class="has-dropdown <?php echo $sys_open; ?>">
                <a href="javascript:void(0);" onclick="toggleDropdown(event, this)"
                    style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <i class="fas fa-cogs"></i> <span class="menu-text">ผู้ดูแลระบบ</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="submenu <?php echo $sys_show; ?>">
                    <?php if (canSeeMenu('AddUser.php')): ?>
                        <li><a href="AddUser.php" class="<?php echo isActive('AddUser.php', $current_page); ?>"><i
                                    class="fas fa-users-cog"></i> จัดการพนักงาน</a></li><?php endif; ?>
                    <?php if (canSeeMenu('ManagerRoles.php')): ?>
                        <li><a href="ManagerRoles.php" class="<?php echo isActive('ManagerRoles.php', $current_page); ?>"><i
                                    class="fas fa-shield-alt"></i> จัดการตำแหน่ง</a></li><?php endif; ?>
                    <?php if (canSeeMenu('ManagePermissions.php')): ?>
                        <li><a href="ManagePermissions.php"
                                class="<?php echo isActive('ManagePermissions.php', $current_page); ?>"><i
                                    class="fas fa-key"></i> กำหนดสิทธิ์</a></li><?php endif; ?>
                    <?php if (canSeeMenu('ManagePages.php')): ?>
                        <li><a href="ManagePages.php" class="<?php echo isActive('ManagePages.php', $current_page); ?>"><i
                                    class="fas fa-sitemap"></i> จัดการหน้าเว็บ</a></li><?php endif; ?>
                    <?php if (canSeeMenu('ProvinceManager.php')): ?>
                        <li><a href="ProvinceManager.php"
                                class="<?php echo isActive('ProvinceManager.php', $current_page); ?>"><i class="fas fa-map"></i>
                                จัดการจังหวัด</a></li><?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>

    </ul>

    <div class="sidebar-footer">
        <img src="<?php echo getAvatar(); ?>" class="user-img">
        <div class="user-info">
            <div class="user-name"><?php echo $fullname; ?></div>
            <div class="user-role"><?php echo ucfirst($role); ?></div>

            <div
                style="font-size: 0.65rem; color: #fbbf24; margin-top: 3px; font-weight: 500; display: flex; align-items: center; gap: 5px;">
                <i class="fas fa-building"></i>
                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;"
                    title="<?php echo isset($user_company) ? $user_company : 'TJC GROUP'; ?>">
                    <?php echo isset($user_company) ? $user_company : 'TJC GROUP'; ?>
                </span>
            </div>

        </div>
        <div class="footer-actions">
            <div id="theme-toggle" class="icon-btn" title="เปลี่ยนโหมด">
                <i class="fas fa-moon"></i>
            </div>

            <a href="#" onclick="confirmLogout(event)" class="icon-btn logout-btn" title="ออกจากระบบ">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</div>

<div class="overlay" id="overlay" onclick="toggleMobile()"></div>

<script>
    const currentUserCompany = "<?php echo htmlspecialchars($user_company); ?>";
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = themeToggle.querySelector('i');
        const body = document.body;

        // ✅ [ส่วนที่แก้] สร้างฟังก์ชันกลาง เพื่อบังคับให้เปลี่ยนทั้ง Class และ Attribute
        function applyTheme(isDark) {
            if (isDark) {
                body.classList.add('dark-mode');         // สำหรับ Sidebar
                body.setAttribute('data-theme', 'dark'); // ✅ เพิ่มบรรทัดนี้เพื่อให้หน้า Dashboard เปลี่ยนสี
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            } else {
                body.classList.remove('dark-mode');
                body.removeAttribute('data-theme');      // ✅ ลบ Attribute ออกเมื่อเป็น Light Mode
                themeIcon.classList.replace('fa-sun', 'fa-moon');
            }
        }

        // 1. ตรวจสอบค่าเมื่อโหลดหน้า
        const savedTheme = localStorage.getItem('tjc_theme');
        // ถ้าค่าเป็น dark ให้เปิด dark mode ทันที
        applyTheme(savedTheme === 'dark');

        // 2. เมื่อกดปุ่มสลับธีม
        themeToggle.addEventListener('click', () => {
            // เช็คว่าปัจจุบันเป็น dark อยู่ไหม?
            const isCurrentlyDark = body.classList.contains('dark-mode');
            // สลับสถานะ (ถ้ามืดอยู่ -> สว่าง, ถ้าสว่างอยู่ -> มืด)
            const nextStateIsDark = !isCurrentlyDark;

            // เรียกใช้ฟังก์ชันเปลี่ยนสี
            applyTheme(nextStateIsDark);

            // บันทึกค่าใหม่ลงเครื่อง
            localStorage.setItem('tjc_theme', nextStateIsDark ? 'dark' : 'light');
        });

        // 3. จำค่าการย่อ/ขยาย Sidebar (ส่วนเดิม ไม่ได้แก้)
        if (localStorage.getItem('tjc_sidebar_collapsed') === 'true' && window.innerWidth > 900) {
            document.body.classList.add('sidebar-collapsed');
        }
    });

    function toggleMobile() {
        document.getElementById('mySidebar').classList.toggle('show');
        document.getElementById('overlay').classList.toggle('show');
    }

    function toggleDesktop() {
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('tjc_sidebar_collapsed', document.body.classList.contains('sidebar-collapsed'));
    }

    function confirmLogout(event) {
        event.preventDefault();
        const isDark = document.body.classList.contains('dark-mode');

        Swal.fire({
            title: 'ยืนยันการออกจากระบบ?',
            text: "คุณต้องการออกจากระบบใช่หรือไม่",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: isDark ? '#475569' : '#94a3b8',
            confirmButtonText: 'ใช่, ออกจากระบบ',
            cancelButtonText: 'ยกเลิก',
            background: isDark ? '#1e293b' : '#ffffff',
            color: isDark ? '#ffffff' : '#1e293b',
            iconColor: '#f59e0b',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
                sessionStorage.removeItem('shown_vehicle_alert');
            }
        });
    }

    // ---------------------------------------------------------
    // 🔥 ฟังก์ชันดึงข้อมูลแจ้งเตือนสำหรับ Sidebar (ระบบขนส่ง)
    // ---------------------------------------------------------
    // ตัวแปรเก็บ ID ล่าสุดที่โหลดมาได้ (เอาไว้ใช้ตอนกดเคลียร์)
    let latestLoadedJobId = 0;

    // 🔥 อัปเดตฟังก์ชันนี้ (สำหรับงานจัดส่ง)
    async function updateTransportBadge() {
        const badge = document.getElementById('transport-badge');
        const parentBadge = document.getElementById('parentTransportBadge'); // ID ของหัวข้อแม่

        if (!badge && !parentBadge) return;

        try {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const currentMonth = `${year}-${month}`;

            const response = await fetch(`api_fm.php?action=fetch_dashboard&month=${currentMonth}`);

            // ตรวจสอบว่า Response เป็น JSON หรือไม่
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                console.warn("[Sidebar] api_fm.php did not return JSON. Skipping badge update.");
                return;
            }

            const data = await response.json();

            if (data && data.jobs && data.jobs.length > 0) {
                const allIds = data.jobs.map(j => parseInt(j.id));
                const maxIdInSystem = Math.max(...allIds);
                latestLoadedJobId = maxIdInSystem;

                let lastSeenId = localStorage.getItem('tjc_last_seen_job_id');
                if (!lastSeenId) {
                    lastSeenId = maxIdInSystem;
                    localStorage.setItem('tjc_last_seen_job_id', maxIdInSystem);
                } else {
                    lastSeenId = parseInt(lastSeenId);
                }

                const newJobsCount = data.jobs.filter(j => parseInt(j.id) > lastSeenId).length;

                if (newJobsCount > 0) {
                    // อัปเดตทั้งตัวลูกและตัวแม่
                    if (badge) {
                        badge.innerText = newJobsCount;
                        badge.style.display = 'inline-flex';
                    }
                    if (parentBadge) {
                        parentBadge.innerText = newJobsCount;
                        parentBadge.style.display = 'inline-flex';
                    }
                } else {
                    if (badge) badge.style.display = 'none';
                    if (parentBadge) parentBadge.style.display = 'none';
                }
            }
        } catch (error) {
            console.error("Transport Badge Error:", error);
        }
    }

    // 🔥 อัปเดตฟังก์ชันนี้ (สำหรับ พ.ร.บ./ภาษี)
    async function updateSidebarAlerts() {
        const badge = document.getElementById('taxAlertBadge');
        const parentBadge = document.getElementById('parentTaxBadge'); // ID ของหัวข้อแม่

        if (!badge && !parentBadge) return;

        try {
            const formData = new FormData();
            formData.append('ajax_action', 'get_tax_alerts');

            const res = await fetch('api_manager_car.php', {
                method: 'POST',
                body: formData
            });

            if (!res.ok) return;

            const resp = await res.json();

            if (resp.success && resp.data && resp.data.length > 0) {
                // อัปเดตทั้งตัวลูกและตัวแม่
                const count = resp.data.length;

                [badge, parentBadge].forEach(el => {
                    if (el) {
                        el.innerText = count;
                        el.classList.remove('d-none');
                        el.style.setProperty('display', 'inline-flex', 'important');
                        el.style.setProperty('background-color', '#dc3545', 'important');
                        el.style.setProperty('color', '#ffffff', 'important');
                    }
                });
            } else {
                if (badge) {
                    badge.classList.add('d-none');
                    badge.style.display = 'none';
                }
                if (parentBadge) {
                    parentBadge.style.display = 'none';
                }
            }
        } catch (error) {
            console.warn('Alert system waiting...');
        }
    }

    // ฟังก์ชันกดแล้วเคลียร์ตัวเลข (จัดส่ง)
    function markJobsAsRead() {
        if (latestLoadedJobId > 0) {
            localStorage.setItem('tjc_last_seen_job_id', latestLoadedJobId);

            // ซ่อนทั้งคู่
            const badge = document.getElementById('transport-badge');
            const parentBadge = document.getElementById('parentTransportBadge');
            if (badge) badge.style.display = 'none';
            if (parentBadge) parentBadge.style.display = 'none';
        }
    }

    // เรียกใช้เมื่อโหลดหน้า
    document.addEventListener('DOMContentLoaded', () => {
        updateSidebarAlerts();
        updateTransportBadge();
    });
    // ตั้งเวลาอัปเดตทุก 1 นาที เพื่อให้ข้อมูลสดใหม่ทุกหน้า
    setInterval(() => {
        updateSidebarAlerts();
        updateTransportBadge();
    }, 60000);

    document.addEventListener('DOMContentLoaded', async () => {
        // 1. เช็คว่าเคยเด้งแจ้งเตือนไปหรือยังในรอบการใช้งานนี้ (Session)
        const hasShownAlert = sessionStorage.getItem('shown_vehicle_alert');

        // ถ้ายังไม่เคยแจ้งเตือน ให้ทำงาน
        if (!hasShownAlert) {
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'get_tax_alerts');

                // ⚠️ อย่าลืมเช็ค Path ไฟล์ api_manager_car.php ให้ถูกต้อง
                const res = await fetch('api_manager_car.php', {
                    method: 'POST',
                    body: formData
                });

                if (!res.ok) return;

                const resp = await res.json();

                // 2. ถ้ามีข้อมูลรถที่ต้องต่อภาษี (> 0 คัน)
                if (resp.success && resp.data && resp.data.length > 0) {

                    // สั่งให้ SweetAlert เด้งขึ้นมา
                    Swal.fire({
                        title: '⚠️ แจ้งเตือน พ.ร.บ./ประกันภัย',
                        html: `
                        <div class="text-start">
                            มีรายการรถจำนวน <b>${resp.data.length} คัน</b><br>
                            ที่กำลังจะหมดอายุหรือหมดอายุแล้ว
                            <br><small class="text-muted">กรุณาตรวจสอบเพื่อดำเนินการต่ออายุ</small>
                        </div>
                    `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',     // สีแดงให้ดูสำคัญ
                        cancelButtonColor: '#3085d6',   // สีฟ้าสำหรับปุ่มปิด
                        confirmButtonText: 'ไปที่หน้าจัดการทันที',
                        cancelButtonText: 'ไว้ทีหลัง',
                        customClass: {
                            popup: 'tjc-alert-popup'
                        }
                    }).then((result) => {
                        // ถ้ากดยอมรับ ให้พาไปหน้าแจ้งเตือน
                        if (result.isConfirmed) {
                            window.location.href = 'vehicle_alerts.php';
                        }
                    });

                    // 3. บันทึกว่า "แจ้งแล้วนะ" ครั้งต่อไปจะไม่เด้งซ้ำจนกว่าจะปิด Browser เปิดใหม่
                    sessionStorage.setItem('shown_vehicle_alert', 'true');
                }

            } catch (error) {
                console.error('Error checking alerts:', error);
            }
        }
    });


    // ฟังก์ชันสำหรับเปิด/ปิด Dropdown (ไม่เด้ง)
    // 🔥 [แก้จุดที่ 2] ฟังก์ชันคำนวณตำแหน่งเมนูใหม่
    function toggleDropdown(event, element) {
        event.preventDefault(); // ป้องกันไม่ให้ Link ดีด

        const parentLi = element.parentElement;
        const submenu = parentLi.querySelector('.submenu');
        const isCollapsed = document.body.classList.contains('sidebar-collapsed');

        if (isCollapsed) {
            // 1. ปิดเมนูอื่นๆ ก่อน
            document.querySelectorAll('.sidebar-menu .submenu.show').forEach(el => {
                if (el !== submenu) el.classList.remove('show');
            });

            // 2. สร้าง Header ชื่อเมนู (ถ้ายังไม่มี)
            const titleText = parentLi.querySelector('.menu-text').textContent.trim();
            if (!submenu.querySelector('.floating-header')) {
                const header = document.createElement('li');
                header.className = 'floating-header';
                header.innerHTML = `<div style="font-size: 1rem;">${titleText}</div>`;
                submenu.prepend(header);
            }

            // 3. 🔥 [สูตรใหม่] แสดงแบบซ่อนก่อน เพื่อวัดขนาดจริง
            submenu.classList.add('show');
            submenu.style.visibility = 'hidden';

            // ใช้ element (ปุ่ม <a>) วัดพิกัด แทน parentLi เพื่อความแม่นยำเป๊ะๆ ตามนิ้วที่กด
            const buttonRect = element.getBoundingClientRect();
            const submenuHeight = submenu.offsetHeight;
            const windowHeight = window.innerHeight;

            // ล้างค่าเก่าก่อนคำนวณ
            submenu.style.top = '';
            submenu.style.bottom = '';

            // 4. คำนวณพื้นที่: ถ้าพื้นที่ด้านล่าง "พอ" ให้เปิดลงล่าง
            // (เช็คจากขอบบนปุ่ม ถึงขอบล่างจอ เทียบกับความสูงเมนู)
            if (windowHeight - buttonRect.top >= submenuHeight) {
                // ✅ แบบปกติ: ปล่อยลงล่าง (ขอบบนเมนู = ขอบบนปุ่ม)
                submenu.style.top = buttonRect.top + 'px';
                submenu.style.bottom = 'auto';
                submenu.style.borderRadius = '0 10px 10px 10px'; // มุมซ้ายบนแหลม (เกาะปุ่ม)
            } else {
                // 🚀 แบบเด้งขึ้น: ถ้าที่ต่ำกว่าไม่พอ ให้เด้งขึ้นบน (ขอบล่างเมนู = ขอบล่างปุ่ม)
                submenu.style.top = 'auto';
                submenu.style.bottom = (windowHeight - buttonRect.bottom) + 'px';
                submenu.style.borderRadius = '10px 10px 10px 0'; // มุมซ้ายล่างแหลม (เกาะปุ่ม)
            }

            // 5. แสดงผลจริง
            submenu.style.visibility = 'visible';

        } else {
            // โหมดจอปกติ (Accordion)
            submenu.classList.toggle('show');
            parentLi.classList.toggle('open');
            // ล้างค่า CSS ที่อาจค้าง
            submenu.style.removeProperty('top');
            submenu.style.removeProperty('bottom');
            submenu.style.removeProperty('visibility');
            submenu.style.removeProperty('border-radius');
        }
    }

    // 🔥 เพิ่ม: สั่งปิดเมนูเมื่อมีการเลื่อน Scroll (เพื่อไม่ให้เมนูลอยค้างผิดตำแหน่ง)
    document.querySelector('.sidebar-menu').addEventListener('scroll', function () {
        if (document.body.classList.contains('sidebar-collapsed')) {
            document.querySelectorAll('.sidebar-menu .submenu.show').forEach(el => {
                el.classList.remove('show');
            });
        }
    });

    // 🔥 [เพิ่ม] : คลิกที่พื้นที่ว่าง (Outside Click) แล้วให้ปิดเมนูที่ลอยอยู่
    document.addEventListener('click', function (e) {
        // ทำงานเฉพาะตอนพับจอ
        if (document.body.classList.contains('sidebar-collapsed')) {
            // ถ้าสิ่งที่คลิก ไม่ใช่ Sidebar
            if (!e.target.closest('.sidebar-menu')) {
                // ปิดทุกเมนูที่เปิดอยู่
                document.querySelectorAll('.sidebar-menu .submenu.show').forEach(el => {
                    el.classList.remove('show');
                });
            }
        }
    });

    // 🔥 [STATE SAVER] : จดจำตำแหน่ง Scroll และสถานะเมนูที่กำลังเปิดอยู่
    document.addEventListener('DOMContentLoaded', () => {
        const sidebarMenu = document.querySelector('.sidebar-menu');
        if (!sidebarMenu) return;

        // 1. ฟื้นฟูตำแหน่ง Scroll ของ Sidebar
        const savedScrollPos = sessionStorage.getItem('tjc_sidebar_scroll');
        if (savedScrollPos) {
            sidebarMenu.scrollTop = parseInt(savedScrollPos, 10);
        }

        // 2. ตั้ง ID ให้เมนู Dropdown (เพื่อให้จำได้ว่าอันไหน) และดักจับการคลิก
        const dropdownLinks = document.querySelectorAll('.sidebar-menu > li > a[onclick^="toggleDropdown"]');
        dropdownLinks.forEach((link, index) => {
            const parentLi = link.parentElement;
            // ตั้ง ID ชั่วคราวให้ li ถ้าไม่มี
            if (!parentLi.id) {
                parentLi.id = 'sidebar_menu_item_' + index;
            }

            // บันทึกสถานะตอนเปิดปิดเมนู
            link.addEventListener('click', () => {
                // รอให้ class 'open' ถูกเพิ่ม/ลบ ก่อนถึงค่อยเช็ค
                setTimeout(() => {
                    if (parentLi.classList.contains('open')) {
                        // เพิ่ม class เพื่อให้กางออก
                        parentLi.classList.add('open');
                        const submenu = parentLi.querySelector('.submenu');
                        if (submenu) submenu.classList.add('show');

                        sessionStorage.setItem('tjc_active_submenu', parentLi.id);
                    } else {
                        // ถ้ากดปิด ก็ล้างค่า
                        if (sessionStorage.getItem('tjc_active_submenu') === parentLi.id) {
                            sessionStorage.removeItem('tjc_active_submenu');
                        }
                    }
                }, 10);
            });
        });

        // 3. ฟื้นฟูเมนูย่อย (Submenu) ที่เปิดค้างไว้ (ต้องทำหลังจากให้ ID ลิ้งก์แล้ว)
        // ** ป้องกัน PHP ปิดเมนูออโต้ **
        const activeSubmenuId = sessionStorage.getItem('tjc_active_submenu');
        if (activeSubmenuId) {
            // ปิดทุกเมนูก่อน (เผื่อ PHP เปิดอันที่ผิดค้างไว้)
            document.querySelectorAll('.sidebar-menu > li.has-dropdown').forEach(li => {
                if (li.id !== activeSubmenuId && !li.querySelector('a.active')) {
                    li.classList.remove('open');
                    const sub = li.querySelector('.submenu');
                    if (sub) sub.classList.remove('show');
                }
            });

            // กางเฉพาะอันที่กดค้างไว้ทันทีโดยไม่ใช้วิธีหน่วงเวลา
            requestAnimationFrame(() => {
                const parentLi = document.getElementById(activeSubmenuId);
                if (parentLi) {
                    parentLi.classList.add('open');
                    // ปิด Transition ชั่วคราวไม่ให้เห็นจังหวะกาง
                    const submenu = parentLi.querySelector('.submenu');
                    if (submenu) {
                        submenu.style.transition = 'none';
                        submenu.classList.add('show');
                        // คืนค่าออริจินอลหลังจากเรนเดอร์เฟรมถัดไป
                        requestAnimationFrame(() => {
                            submenu.style.transition = '';
                        });
                    }
                }
            });
        }

        // 4. บันทึกตำแหน่ง Scroll
        sidebarMenu.addEventListener('scroll', () => {
            sessionStorage.setItem('tjc_sidebar_scroll', sidebarMenu.scrollTop);
        });

        // 5. บันทึก ID เมื่อคลิกลิงก์เมนูย่อย ให้พ่อมันกางเสมอเมื่อย้ายหน้า
        const subLinks = document.querySelectorAll('.sidebar-menu .submenu a');
        subLinks.forEach(subLink => {
            subLink.addEventListener('click', () => {
                const parentLi = subLink.closest('.has-dropdown');
                if (parentLi && parentLi.id) {
                    sessionStorage.setItem('tjc_active_submenu', parentLi.id);
                }
            });
        });
    });

</script>