<?php
// ==================================================================================
//  SECTION 1: API HANDLER (Handle Actions)
// ==================================================================================
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok');

// Check for AJAX Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    ob_clean();
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid Request'];

    try {
        $task_id = $_POST['task_id'] ?? '';
        if (empty($task_id)) throw new Exception("Task ID not found");

        // --- ACTION 1: Start Work ---
        if ($_POST['action'] == 'start_work') {
            $check = $conn->query("SELECT started_at FROM tasks WHERE task_id = '$task_id'");
            $row = $check->fetch_assoc();
            $started_sql = "";
            if (empty($row['started_at'])) {
                $now = date('Y-m-d H:i:s');
                $started_sql = ", started_at = '$now'";
            }

            // แจ้งเตือนบอส (is_read_admin = 0)
            $sql = "UPDATE tasks SET status = 'ดำเนินการ' $started_sql, is_read_admin = 0 WHERE task_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $task_id);

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'เริ่มงานแล้ว!'];
            } else {
                throw new Exception("Database Error: " . $stmt->error);
            }
        }

        // --- ACTION 2: Submit Work (Save) ---
        elseif ($_POST['action'] == 'save_work') {
            $status = 'สำเร็จ';
            $work_note = $_POST['work_note'] ?? '';

            // 1. Check completion time
            $completed_at_sql = "";
            $check = $conn->query("SELECT completed_at FROM tasks WHERE task_id = '$task_id'");
            $row = $check->fetch_assoc();
            if (empty($row['completed_at'])) {
                $now = date('Y-m-d H:i:s');
                $completed_at_sql = ", completed_at = '$now'";
            }

            // 2. Update status & Notify Boss
            $sql = "UPDATE tasks SET status = ?, submission_note = ? $completed_at_sql, is_read_admin = 0 WHERE task_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $status, $work_note, $task_id);

            if (!$stmt->execute()) throw new Exception("Database Error: " . $stmt->error);

            // 3. Handle File Uploads
            $uploaded_count = 0;
            if (isset($_FILES['work_files']) && !empty($_FILES['work_files']['name'][0])) {
                $upload_dir = "uploads/submissions/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                foreach ($_FILES['work_files']['name'] as $key => $name) {
                    if ($_FILES['work_files']['error'][$key] === 0) {
                        $tmp_name = $_FILES['work_files']['tmp_name'][$key];
                        $new_name = $task_id . "_" . time() . "_" . $key . "_" . basename($name);
                        $target_file = $upload_dir . $new_name;

                        if (move_uploaded_file($tmp_name, $target_file)) {
                            $sql_file = "INSERT INTO task_attachments (task_id, file_name, file_path, is_submission) VALUES (?, ?, ?, 1)";
                            $stmt_file = $conn->prepare($sql_file);
                            $stmt_file->bind_param("sss", $task_id, $name, $target_file);
                            $stmt_file->execute();
                            $uploaded_count++;
                        }
                    }
                }
            }
            $response = ['success' => true, 'message' => "บันทึกข้อมูลเรียบร้อยแล้ว"];
        }

        // --- ACTION 3: Delete Task ---
        elseif ($_POST['action'] == 'delete_task') {
            // ลบไฟล์ก่อน (Optional)
            $conn->query("DELETE FROM task_attachments WHERE task_id = '$task_id'");
            $conn->query("DELETE FROM tasks WHERE task_id = '$task_id'");
            $response = ['success' => true, 'message' => "ลบงานเรียบร้อยแล้ว"];
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// ==================================================================================
//  SECTION 2: DASHBOARD VIEW (Display)
// ==================================================================================

$my_role = $_SESSION['role'] ?? 'staff';
$my_name = $_SESSION['fullname'] ?? '';

// --- 1. Prepare Companies ---
$company_options = [];
// เปลี่ยนตัวเลขในวงเล็บให้ตรงกับลำดับ ID บริษัทที่คุณต้องการ
$res_comp = $conn->query("SELECT * FROM companies ORDER BY FIELD(id, 6, 2, 3, 5)");
if ($res_comp) {
    while ($row_comp = $res_comp->fetch_assoc()) {
        $company_options[] = $row_comp['company_name'];
    }
} else {
    $res_comp = $conn->query("SELECT DISTINCT company FROM tasks WHERE company IS NOT NULL");
    while ($row_comp = $res_comp->fetch_assoc()) {
        $company_options[] = $row_comp['company'];
    }
}

// --- 2. Prepare Filters ---
$where_clauses = [];
$stats_where_clauses = [];

if ($my_role !== 'admin' && strtoupper($my_role) !== 'CEO') {
    $where_clauses[] = "t.assigned_to = '" . $conn->real_escape_string($my_name) . "'";
    $stats_where_clauses[] = "assigned_to = '" . $conn->real_escape_string($my_name) . "'";
}

$filter_company = $_GET['company'] ?? '';
if (!empty($filter_company) && $filter_company != 'all') {
    $where_clauses[] = "(c.company_name = '" . $conn->real_escape_string($filter_company) . "' OR t.company = '" . $conn->real_escape_string($filter_company) . "')";
    $stats_where_clauses[] = "company = '" . $conn->real_escape_string($filter_company) . "'";
}

$filter_date = $_GET['assign_date'] ?? '';
if (!empty($filter_date)) {
    $date_condition = "DATE(assign_date) = '" . $conn->real_escape_string($filter_date) . "'";
    $where_clauses[] = $date_condition;
    $stats_where_clauses[] = $date_condition;
}

$filter_status = $_GET['status'] ?? '';
if (!empty($filter_status)) {
    $where_clauses[] = "status = '" . $conn->real_escape_string($filter_status) . "'";
}

$sql_where = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";
$sql_stats_where = count($stats_where_clauses) > 0 ? "WHERE " . implode(' AND ', $stats_where_clauses) : "";

// --- Pagination ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Fetch Data ---
$sql_count_total = "SELECT COUNT(*) as total FROM tasks t 
                    LEFT JOIN users u ON t.assigned_to = u.fullname 
                    LEFT JOIN companies c ON u.company_id = c.id 
                    $sql_where";
$res_count = $conn->query($sql_count_total);
$row_count = $res_count->fetch_assoc();
$total_records = $row_count['total'];
$total_pages = ceil($total_records / $limit);

$sql_tasks = "SELECT t.*, u.fullname as assignee_name, 
              COALESCE(c.company_name, t.company) as company_display 
              FROM tasks t 
              LEFT JOIN users u ON t.assigned_to = u.fullname 
              LEFT JOIN companies c ON u.company_id = c.id
              $sql_where 
              ORDER BY t.created_at DESC 
              LIMIT $limit OFFSET $offset";

$result = $conn->query($sql_tasks);

$total_tasks = $total_records;
$sql_stats = "SELECT 
                SUM(CASE WHEN status = 'มอบหมาย' THEN 1 ELSE 0 END) as count_ordered,
                SUM(CASE WHEN status = 'ดำเนินการ' THEN 1 ELSE 0 END) as count_process,
                SUM(CASE WHEN status = 'สำเร็จ' THEN 1 ELSE 0 END) as count_success
              FROM tasks $sql_stats_where";
$stats_result = $conn->query($sql_stats)->fetch_assoc();
$count_ordered = $stats_result['count_ordered'] ?? 0;
$count_process = $stats_result['count_process'] ?? 0;
$count_success = $stats_result['count_success'] ?? 0;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Executive Command</title>

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            750: '#2d3748',
                            850: '#1a202c',
                            900: '#0f172a',
                            950: '#020617'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }

        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 10px;
        }

        .swal2-container {
            z-index: 10000 !important;
        }

        /* --- Dark Mode Custom Overrides --- */
        .dark body { background-color: #0f172a; color: #f1f5f9; }
        .dark div:where(.swal2-container) div:where(.swal2-popup) {
            background: #1e293b !important;
            color: #f8fafc !important;
            border: 1px solid #334155;
        }
        .dark div:where(.swal2-icon) { border-color: #475569; }
        .dark div:where(.swal2-icon).swal2-warning { border-color: #f59e0b; color: #f59e0b; }
        .dark div:where(.swal2-icon).swal2-success { border-color: #10b981; color: #10b981; }
        .dark .swal2-title, .dark .swal2-html-container { color: #f8fafc !important; }
        .dark .swal2-timer-progress-bar { background: #6366f1; }

        .dark .flatpickr-calendar {
            background: #1e293b;
            border: 1px solid #334155;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
        }
        .dark .flatpickr-months .flatpickr-month { background: #1e293b; color: #fff; fill: #fff; }
        .dark .flatpickr-current-month .flatpickr-monthDropdown-months { background: #1e293b; color: #fff; }
        .dark .flatpickr-weekdays { background: #1e293b; }
        .dark span.flatpickr-weekday { color: #cbd5e1; }
        .dark .flatpickr-day { color: #e2e8f0; }
        .dark .flatpickr-day:hover, .dark .flatpickr-day.prevMonthDay:hover, .dark .flatpickr-day.nextMonthDay:hover, .dark .flatpickr-day:focus {
            background: #334155; border-color: #334155;
        }
        .dark .flatpickr-day.selected { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        .dark .flatpickr-day.prevMonthDay, .dark .flatpickr-day.nextMonthDay { color: #64748b; }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
        .dark .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
    </style>
</head>

<body class="bg-[#f8fafc] dark:bg-[#0f172a] transition-colors duration-300">

    <div class="flex flex-col md:flex-row h-screen overflow-hidden">
        
        <div class="flex-shrink-0 w-full md:w-auto bg-white dark:bg-slate-800 border-b md:border-b-0 md:border-r border-slate-200 dark:border-slate-700 z-10 transition-colors duration-300 overflow-y-auto max-h-[30vh] md:max-h-full">
            <?php include 'sidebar.php'; ?>
        </div>

        <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 lg:p-8 relative custom-scrollbar bg-[#f8fafc] dark:bg-[#0f172a]">
            <div class="dashboard-container space-y-6 pb-10">

                <div class="bg-white dark:bg-slate-800 rounded-[1.5rem] p-6 shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col lg:flex-row items-center justify-between gap-4 transition-colors duration-300">
                    <div class="flex items-center gap-5 w-full lg:w-auto">
                        <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-200 dark:shadow-none shrink-0">
                            <i data-lucide="layout-dashboard" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <h1 class="text-xl md:text-3xl font-extrabold tracking-tight">
                                <span class="bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 via-purple-600 to-blue-600 dark:from-indigo-400 dark:via-purple-400 dark:to-blue-400">
                                    ภาพรวมการสั่งการและติดตามงาน
                                </span>
                            </h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 w-full lg:w-auto justify-end">
                        <div class="px-4 py-2 bg-slate-50 dark:bg-slate-700/50 rounded-full shadow-sm text-sm font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-2 border border-slate-100 dark:border-slate-600">
                            <i data-lucide="calendar" class="w-4 h-4 text-indigo-500 dark:text-indigo-400"></i> <?php echo date('d M Y'); ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5">
                    <a href="?status=" class="bg-white dark:bg-slate-800 p-3 md:p-5 rounded-[1.5rem] shadow-sm border-2 border-slate-100 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-500 hover:shadow-md transition-all cursor-pointer group relative overflow-hidden <?php echo $filter_status == '' ? 'ring-2 ring-slate-400 dark:ring-slate-500' : ''; ?>">
                        <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><i data-lucide="layers" class="w-10 h-10 md:w-14 md:h-14 text-slate-600 dark:text-slate-400"></i></div>
                        <div class="flex flex-col h-full justify-between gap-3 md:gap-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl flex items-center justify-center"><i data-lucide="file-text" class="w-5 h-5 md:w-6 md:h-6"></i></div>
                            <div>
                                <p class="text-[10px] md:text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">งานทั้งหมด</p>
                                <h2 class="text-xl md:text-3xl font-bold text-slate-700 dark:text-slate-100 flex items-baseline gap-1"><?php echo number_format($total_tasks); ?> <span class="text-[10px] md:text-xs font-normal text-slate-400 dark:text-slate-500">รายการ</span></h2>
                            </div>
                        </div>
                    </a>

                    <a href="?status=มอบหมาย" class="bg-white dark:bg-slate-800 p-3 md:p-5 rounded-[1.5rem] shadow-sm border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-500 transition-all cursor-pointer group <?php echo $filter_status == 'มอบหมาย' ? 'ring-2 ring-blue-400 dark:ring-blue-500' : ''; ?>">
                        <div class="flex flex-col h-full justify-between gap-3 md:gap-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform"><i data-lucide="inbox" class="w-5 h-5 md:w-6 md:h-6"></i></div>
                            <div>
                                <p class="text-[10px] md:text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">มอบหมาย</p>
                                <h2 class="text-xl md:text-3xl font-bold text-blue-600 dark:text-blue-400 flex items-baseline gap-1"><?php echo number_format($count_ordered); ?> <span class="text-[10px] md:text-xs font-normal text-slate-400 dark:text-slate-500">รายการ</span></h2>
                            </div>
                        </div>
                    </a>

                    <a href="?status=ดำเนินการ" class="bg-white dark:bg-slate-800 p-3 md:p-5 rounded-[1.5rem] shadow-sm border border-slate-200 dark:border-slate-700 hover:border-orange-300 dark:hover:border-orange-500 transition-all cursor-pointer group <?php echo $filter_status == 'ดำเนินการ' ? 'ring-2 ring-orange-400 dark:ring-orange-500' : ''; ?>">
                        <div class="flex flex-col h-full justify-between gap-3 md:gap-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform"><i data-lucide="loader" class="w-5 h-5 md:w-6 md:h-6 animate-spin-slow"></i></div>
                            <div>
                                <p class="text-[10px] md:text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">กำลังดำเนินการ</p>
                                <h2 class="text-xl md:text-3xl font-bold text-orange-600 dark:text-orange-400 flex items-baseline gap-1"><?php echo number_format($count_process); ?> <span class="text-[10px] md:text-xs font-normal text-slate-400 dark:text-slate-500">รายการ</span></h2>
                            </div>
                        </div>
                    </a>

                    <a href="?status=สำเร็จ" class="bg-white dark:bg-slate-800 p-3 md:p-5 rounded-[1.5rem] shadow-sm border border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-500 transition-all cursor-pointer group <?php echo $filter_status == 'สำเร็จ' ? 'ring-2 ring-emerald-400 dark:ring-emerald-500' : ''; ?>">
                        <div class="flex flex-col h-full justify-between gap-3 md:gap-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform"><i data-lucide="check-circle-2" class="w-5 h-5 md:w-6 md:h-6"></i></div>
                            <div>
                                <p class="text-[10px] md:text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">สำเร็จ</p>
                                <h2 class="text-xl md:text-3xl font-bold text-emerald-600 dark:text-emerald-400 flex items-baseline gap-1"><?php echo number_format($count_success); ?> <span class="text-[10px] md:text-xs font-normal text-slate-400 dark:text-slate-500">รายการ</span></h2>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-[1.5rem] shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden transition-colors duration-300">
                    
                    <div class="p-4 md:p-6 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div class="flex items-center gap-3 w-full md:w-auto">
                            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-md shadow-indigo-200 dark:shadow-none shrink-0"><i data-lucide="list" class="w-5 h-5"></i></div>
                            
                            <?php if ($my_role == 'admin' || strtoupper($my_role) == 'CEO'): ?>
                            <form method="GET" class="w-full md:w-auto flex-1">
                                <select name="company" onchange="this.form.submit()" class="appearance-none bg-slate-50 dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 border border-slate-200 dark:border-slate-600 text-indigo-700 dark:text-indigo-400 text-sm md:text-lg font-bold rounded-xl focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 focus:border-indigo-500 block w-full pl-4 pr-10 py-2 outline-none transition-all cursor-pointer">
                                    <option value="all">บริษัท (ทั้งหมด)</option>
                                    <?php foreach ($company_options as $comp): ?>
                                        <option value="<?php echo $comp; ?>" <?php echo ($filter_company == $comp) ? 'selected' : ''; ?>><?php echo $comp; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="status" value="<?php echo $filter_status; ?>">
                            </form>
                            <?php else: ?>
                            <div class="text-lg font-bold text-slate-700 dark:text-slate-300 px-2 whitespace-nowrap">รายการงานของคุณ</div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto md:justify-end">
                            <?php if (!empty($filter_status) || !empty($filter_date) || !empty($filter_company)): ?>
                                <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="w-full sm:w-auto justify-center px-4 py-2.5 bg-red-50 dark:bg-red-900/20 text-red-500 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 rounded-xl text-sm font-bold transition-all flex items-center gap-2 border border-red-100 dark:border-red-900/30"><i data-lucide="rotate-ccw" class="w-4 h-4"></i> ล้างค่า</a>
                            <?php endif; ?>

                            <form id="filterForm" method="GET" class="w-full sm:w-64 relative group">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="calendar" class="h-4 w-4 text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 transition-colors"></i></div>
                                <input type="hidden" name="status" value="<?php echo $filter_status; ?>">
                                <input type="hidden" name="company" value="<?php echo $filter_company; ?>">
                                <input type="text" id="filter_date" name="assign_date" value="<?php echo $filter_date; ?>" placeholder="เลือกวันที่มอบหมาย" class="appearance-none bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-200 text-sm rounded-xl focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 focus:border-indigo-500 block w-full pl-10 py-2.5 font-medium outline-none transition-all cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-600 shadow-sm min-h-[42px]">
                            </form>
                        </div>
                    </div>

                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left border-collapse min-w-[900px]">
                            <thead class="text-slate-500 dark:text-slate-400 text-sm font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                                <tr>
                                    <th class="py-4 pl-6 pr-4 w-1/3">งานที่ได้รับมอบหมาย</th>
                                    <th class="py-4 px-4 w-1/4">ผู้รับผิดชอบ / บริษัท</th>
                                    <th class="py-4 px-4 w-1/6 text-center">สถานะ</th>
                                    <th class="py-4 px-4 w-1/6">ไทม์ไลน์</th>
                                    <th class="py-4 pr-6 text-center">เพิ่มเติม</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-slate-700 text-base">
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()):
                                        $assignDateShow = (!empty($row['assign_date'])) ? date('d/m/Y H:i', strtotime($row['assign_date'])) : '-';
                                        $dueDateShow = (!empty($row['due_date'])) ? date('d/m/Y H:i', strtotime($row['due_date'])) : '-';
                                        $completedAtShow = (!empty($row['completed_at'])) ? date('d/m/Y H:i', strtotime($row['completed_at'])) : '';
                                        $startedAtShow = (!empty($row['started_at'])) ? date('d/m/Y H:i', strtotime($row['started_at'])) : '';
                                        $submissionNote = $row['submission_note'] ?? '';
                                        $taskId = $row['task_id'];

                                        $sql_files = "SELECT * FROM task_attachments WHERE task_id = '$taskId'";
                                        $res_files = $conn->query($sql_files);
                                        $attachments = [];
                                        if ($res_files) {
                                            while ($file = $res_files->fetch_assoc()) {
                                                $attachments[] = $file;
                                            }
                                        }
                                        $attachmentsJson = htmlspecialchars(json_encode($attachments), ENT_QUOTES, 'UTF-8');

                                        $initial = mb_substr($row['assigned_to'], 0, 1);
                                        $companyBadge = !empty($row['company_display']) ? $row['company_display'] : '-';

                                        $statusBadge = '';
                                        if ($row['status'] == 'มอบหมาย') $statusBadge = '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-800">มอบหมาย</span>';
                                        elseif ($row['status'] == 'ดำเนินการ') $statusBadge = '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 border border-orange-100 dark:border-orange-800"><span class="relative flex h-2 w-2 mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-orange-500"></span></span>ดำเนินการ</span>';
                                        elseif ($row['status'] == 'สำเร็จ') $statusBadge = '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800">สำเร็จ</span>';

                                        $updateBadge = '';
                                        $rowClass = 'hover:bg-slate-50/80 dark:hover:bg-slate-700/30';

                                        if ($my_role == 'admin' && isset($row['is_read_admin']) && $row['is_read_admin'] == 0) {
                                            $updateBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-300 animate-pulse ml-2 border border-red-200 dark:border-red-800 shadow-sm">🔔 อัปเดต</span>';
                                            $rowClass = 'bg-red-50/50 dark:bg-red-900/10 border-l-4 border-l-red-500';
                                        }
                                        else if ($my_role != 'admin' && isset($row['is_read_user']) && $row['is_read_user'] == 0) {
                                            $updateBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-300 animate-pulse ml-2 border border-blue-200 dark:border-blue-800 shadow-sm">🆕 งานใหม่</span>';
                                            $rowClass = 'bg-blue-50/50 dark:bg-blue-900/10 border-l-4 border-l-blue-500';
                                        }
                                    ?>
                                        <tr class="transition-all cursor-pointer group border-b border-slate-100 dark:border-slate-700 <?php echo $rowClass; ?>"
                                            onclick="openTaskModal(this)"
                                            data-task-id="<?php echo $row['task_id']; ?>"
                                            data-title="<?php echo htmlspecialchars($row['title']); ?>"
                                            data-desc="<?php echo htmlspecialchars($row['description']); ?>"
                                            data-submission-note="<?php echo htmlspecialchars($submissionNote); ?>"
                                            data-company="<?php echo htmlspecialchars($companyBadge); ?>"
                                            data-assignee="<?php echo htmlspecialchars($row['assigned_to']); ?>"
                                            data-created-by="<?php echo htmlspecialchars($row['created_by']); ?>"
                                            data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                            data-assign-date="<?php echo strip_tags($assignDateShow); ?>"
                                            data-started-at="<?php echo strip_tags($startedAtShow); ?>"
                                            data-due-date="<?php echo $dueDateShow; ?>"
                                            data-completed-at="<?php echo $completedAtShow; ?>"
                                            data-attachments="<?php echo $attachmentsJson; ?>">
                                            <td class="py-4 pl-6 pr-4 align-top">
                                                <div class="flex items-start gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center shrink-0 border border-slate-200 dark:border-slate-600"><i data-lucide="clipboard-list" class="w-5 h-5"></i></div>
                                                    <div class="pt-1">
                                                        <div class="font-bold text-slate-800 dark:text-slate-200 text-base line-clamp-2">
                                                            <?php echo $row['title'] . $updateBadge; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 align-top">
                                                <div class="flex items-start gap-3">
                                                    <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 text-sm font-bold border border-slate-200 dark:border-slate-600 shadow-sm shrink-0"><?php echo $initial; ?></div>
                                                    <div>
                                                        <div class="font-bold text-slate-700 dark:text-slate-300 text-sm"><?php echo $row['assigned_to']; ?></div>
                                                        <div class="text-xs text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded-md inline-block mt-1 border border-slate-200 dark:border-slate-600"><?php echo $companyBadge; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 align-top text-center"><?php echo $statusBadge; ?></td>
                                            <td class="py-4 px-4 align-top">
                                                <div class="flex flex-col gap-1">
                                                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3"></i> <?php echo $assignDateShow; ?></div>
                                                    <?php if ($row['status'] == 'สำเร็จ' && !empty($completedAtShow)): ?>
                                                        <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> <?php echo $completedAtShow; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-4 pr-6 align-top text-center">
                                                <button class="w-9 h-9 rounded-full bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-slate-600 flex items-center justify-center hover:bg-indigo-50 dark:hover:bg-slate-600 transition-all shadow-sm mx-auto group-hover:scale-110 group-hover:border-indigo-300 dark:group-hover:border-indigo-500">
                                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="p-12 text-center text-slate-400 dark:text-slate-500 bg-slate-50/50 dark:bg-slate-900/50">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <div class="w-14 h-14 bg-slate-100 dark:bg-slate-700 rounded-full flex items-center justify-center text-slate-300 dark:text-slate-500"><i data-lucide="folder-open" class="w-7 h-7"></i></div>
                                                <span class="font-medium">ไม่พบรายการงาน</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex justify-center items-center gap-2">
                            <?php $queryParams = $_GET;
                            unset($queryParams['page']);
                            $queryString = http_build_query($queryParams);
                            $baseUrl = "?" . ($queryString ? $queryString . "&" : "") . "page="; ?>

                            <?php if ($page > 1): ?>
                                <a href="<?php echo $baseUrl . ($page - 1); ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-indigo-500 dark:hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors shadow-sm"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-indigo-600 text-white text-sm font-bold shadow-md shadow-indigo-200 dark:shadow-none"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo $baseUrl . $i; ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 text-sm font-medium hover:border-indigo-500 dark:hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors shadow-sm"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo $baseUrl . ($page + 1); ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-indigo-500 dark:hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors shadow-sm"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="modalOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[3000] hidden opacity-0 transition-opacity duration-300 flex items-end sm:items-center justify-center overlay-sidebar-offset p-0 sm:p-4 dark:bg-black/60">
        <div class="bg-white dark:bg-slate-800 w-full max-w-4xl rounded-t-[2rem] sm:rounded-[2rem] shadow-2xl overflow-hidden flex flex-col scale-95 transition-transform duration-300 relative z-50 max-h-[90vh]" id="modalContent">

            <div class="px-4 md:px-6 py-4 md:py-5 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 flex justify-between items-center sticky top-0 z-10">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-800 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 shadow-sm hidden sm:flex">
                        <i data-lucide="file-check-2" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div id="modalStatusBadge" class="mb-1"></div>
                        <h3 id="modalTitle" class="text-lg md:text-xl font-bold text-slate-800 dark:text-slate-100 leading-tight line-clamp-1"></h3>
                    </div>
                </div>
                <button onclick="toggleModal()" class="w-9 h-9 rounded-full bg-slate-50 dark:bg-slate-700 hover:bg-red-50 dark:hover:bg-red-900/30 hover:text-red-500 dark:hover:text-red-400 flex items-center justify-center text-slate-400 dark:text-slate-300 transition duration-200">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="modal-content overflow-y-auto flex-1 bg-slate-50 dark:bg-slate-900 p-4 md:p-6 custom-scrollbar">
                <input type="hidden" id="modalTaskId">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-4 space-y-6">
                        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <h5 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">วันและเวลาที่เกี่ยวข้อง</h5>
                            <div class="space-y-3">
                                <div class="flex items-center gap-3 p-3 bg-indigo-50/50 dark:bg-indigo-900/20 rounded-xl border border-indigo-100 dark:border-indigo-800">
                                    <i data-lucide="clock-3" class="w-5 h-5 text-indigo-500 dark:text-indigo-400"></i>
                                    <div>
                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase">เวลามอบงาน</p>
                                        <p id="modalAssignTimeDisplay" class="text-sm font-semibold text-slate-700 dark:text-slate-200">-</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-red-50/50 dark:bg-red-900/20 rounded-xl border border-red-100 dark:border-red-800">
                                    <i data-lucide="calendar-clock" class="w-5 h-5 text-red-500 dark:text-red-400"></i>
                                    <div>
                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase">กำหนดส่งงาน</p>
                                        <p id="modalDueDateDisplay" class="text-sm font-semibold text-red-600 dark:text-red-400">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                            <h5 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">ผู้เกี่ยวข้อง</h5>
                            <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-600">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold border-2 border-white dark:border-slate-800 shadow-sm"><span id="modalAssigneeInit"></span></div>
                                <div>
                                    <p class="text-xs text-slate-400 dark:text-slate-500">ผู้รับผิดชอบ</p>
                                    <p id="modalAssignee" class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1"></p>
                                    <p id="modalCompany" class="text-[10px] text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-800 px-2 py-0.5 rounded-md inline-block font-medium"></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-600">
                                <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/50 flex items-center justify-center text-orange-600 dark:text-orange-300 font-bold border-2 border-white dark:border-slate-800 shadow-sm"><i data-lucide="crown" class="w-5 h-5"></i></div>
                                <div>
                                    <p class="text-xs text-slate-400 dark:text-slate-500">ผู้มอบหมาย</p>
                                    <p id="modalCreatedBy" class="text-sm font-semibold text-slate-700 dark:text-slate-200"></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                            <h5 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-4">ไทม์ไลน์งาน</h5>
                            <div id="modalTimelineContainer"></div>
                        </div>
                    </div>

                    <div class="lg:col-span-8 space-y-6">
                        <div class="font-bold text-slate-700 dark:text-slate-200 text-lg">รายละเอียดงาน</div>

                        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                            <h4 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2"><i data-lucide="align-left" class="w-4 h-4 text-indigo-500 dark:text-indigo-400"></i> รายละเอียดงานเพิ่มเติม </h4>
                            <div class="text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-wrap text-sm" id="modalDesc"></div>
                        </div>

                        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                            <h4 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2"><i data-lucide="paperclip" class="w-4 h-4 text-indigo-500 dark:text-indigo-400"></i> ไฟล์แนบ (มอบหมาย)</h4>
                            <div id="modalAssignerAttachments" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                            <div id="noAssignerAttachments" class="flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-600 text-slate-400 dark:text-slate-500 text-xs gap-2"><i data-lucide="file-x" class="w-6 h-6 opacity-50"></i>ไม่มีไฟล์แนบ</div>
                        </div>

                        <div id="modalSubmissionSection">
                            <div id="modalSubmissionNoteContainer" class="hidden bg-emerald-50 dark:bg-emerald-900/20 p-6 rounded-2xl border border-emerald-100 dark:border-emerald-800 shadow-sm mb-6">
                                <h4 class="text-sm font-bold text-emerald-700 dark:text-emerald-400 mb-3 flex items-center gap-2"><i data-lucide="file-check" class="w-4 h-4"></i> รายงานผลการทำงาน</h4>
                                <div class="text-emerald-800 dark:text-emerald-300 leading-relaxed whitespace-pre-wrap text-sm" id="modalSubmissionNote"></div>
                            </div>

                            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                                <h4 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 dark:text-emerald-400"></i> ไฟล์ส่งงาน</h4>
                                <div id="modalWorkAttachments" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                                <div id="noWorkAttachments" class="flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-600 text-slate-400 dark:text-slate-500 text-xs gap-2"><i data-lucide="image-off" class="w-6 h-6 opacity-50"></i>ยังไม่มีการส่งงาน</div>
                            </div>
                        </div>

                        <div id="actionSectionWrapper" class="pt-4">
                            <div id="modalActionContainer"></div>

                            <div id="submitWorkSection" class="hidden bg-white dark:bg-slate-800 p-6 rounded-2xl border border-indigo-100 dark:border-indigo-900 shadow-sm relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
                                <h4 class="text-base font-bold text-slate-800 dark:text-slate-100 mb-6 flex items-center gap-2">
                                    <div class="p-1.5 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 rounded-lg"><i data-lucide="send" class="w-4 h-4"></i></div>ส่งงาน / รายงานผล
                                </h4>
                                <form id="submitWorkForm" class="space-y-5">
                                    <div class="group">
                                        <label class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase mb-2 block">รายละเอียดการทำงาน</label>
                                        <textarea id="work_note" name="work_note" rows="3" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl p-4 text-sm focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-600 outline-none transition-all resize-none dark:text-slate-100 dark:placeholder-slate-400" placeholder="สรุปผลการทำงาน..."></textarea>
                                    </div>
                                    <div>
                                        <label class="text-xs text-slate-500 dark:text-slate-400 font-bold uppercase mb-2 block">แนบหลักฐาน (รูปภาพ/PDF)</label>
                                        <div id="uploadCompletionBox" class="h-32 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-700/50 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-indigo-50/50 transition-all relative group cursor-pointer flex flex-col items-center justify-center gap-2" onclick="document.getElementById('work_files').click()">
                                            <input type="file" name="work_files[]" id="work_files" class="hidden" multiple accept="image/*,.pdf" onchange="previewFiles(event)">
                                            <div class="w-10 h-10 bg-white dark:bg-slate-600 text-indigo-500 dark:text-indigo-400 rounded-full flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform"><i data-lucide="upload-cloud" class="w-5 h-5"></i></div>
                                            <p class="text-xs font-bold text-slate-500 dark:text-slate-400 text-center">คลิกเพื่ออัปโหลดไฟล์</p>
                                        </div>
                                        <div id="previewGallery" class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-3"></div>
                                    </div>
                                    <button type="button" onclick="submitWork()" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-lg shadow-indigo-200 dark:shadow-indigo-900/50 transition-all flex items-center justify-center gap-2 active:scale-[0.98]"><i data-lucide="check-circle" class="w-5 h-5"></i> ยืนยันการส่งงาน</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer px-4 md:px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 flex justify-between items-center sticky bottom-0 z-20">
                <button onclick="deleteTask()" class="text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 text-sm font-semibold flex items-center gap-2 transition-colors px-4 py-2 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"><i data-lucide="trash-2" class="w-4 h-4"></i> ลบงานนี้</button>
                <button onclick="toggleModal()" class="px-6 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-200 rounded-xl text-sm font-bold transition-colors">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <div id="lightboxOverlay" class="fixed inset-0 bg-black/90 z-[4000] hidden flex items-center justify-center p-4 transition-opacity duration-300" onclick="closeLightbox()">
        <img id="lightboxImage" src="" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl animate-fade-in-up">
        <button class="absolute top-6 right-6 p-2 bg-white/10 hover:bg-white/20 rounded-full text-white transition-colors"><i data-lucide="x" class="w-8 h-8"></i></button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <script>
        const currentUserName = <?php echo json_encode($my_name); ?>;
        const currentUserRole = <?php echo json_encode($my_role); ?>;
        
        lucide.createIcons();

        flatpickr("#filter_date", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "j F Y",
            locale: "th",
            disableMobile: "true",
            onChange: function(selectedDates, dateStr, instance) {
                document.getElementById('filterForm').submit();
            }
        });

        const modal = document.querySelector('#modalOverlay');
        const modalContent = document.querySelector('#modalContent');
        const body = document.querySelector('body');

        let uploadedFiles = [];

        function toggleModal() {
            const isHidden = modal.classList.contains('hidden');
            if (isHidden) {
                modal.classList.remove('hidden');
                void modal.offsetWidth;
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
                body.style.overflow = 'hidden';
            } else {
                modal.classList.add('opacity-0');
                modalContent.classList.add('scale-95');
                modalContent.classList.remove('scale-100');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    body.style.overflow = '';
                }, 300);
            }
        }

        modal.addEventListener('click', function(e) {
            if (e.target === modal) toggleModal();
        });

        function openLightbox(src) {
            document.getElementById('lightboxImage').src = src;
            document.getElementById('lightboxOverlay').classList.remove('hidden');
        }

        function closeLightbox() {
            document.getElementById('lightboxOverlay').classList.add('hidden');
        }

        function previewFiles(event) {
            const files = Array.from(event.target.files);
            uploadedFiles = uploadedFiles.concat(files);
            renderPreviewGallery();
            event.target.value = '';
        }

        function renderPreviewGallery() {
            const gallery = document.getElementById('previewGallery');
            gallery.innerHTML = '';

            uploadedFiles.forEach((file, index) => {
                const isImage = file.type.startsWith('image/');
                const div = document.createElement('div');
                div.className = 'relative group aspect-square rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 overflow-hidden shadow-sm';

                let contentHTML = '';
                if (isImage) {
                    const url = URL.createObjectURL(file);
                    contentHTML = `<img src="${url}" class="w-full h-full object-cover cursor-pointer" onclick="openLightbox('${url}')">`;
                } else {
                    contentHTML = `<div class="flex flex-col items-center justify-center h-full text-slate-400"><i data-lucide="file" class="w-6 h-6 mb-1"></i><span class="text-[10px] truncate w-10/12 text-center">${file.name}</span></div>`;
                }

                div.innerHTML = `
                    ${contentHTML}
                    <button type="button" onclick="removeUploadedFile(${index})" class="absolute top-1 right-1 bg-white dark:bg-slate-700 text-red-500 rounded-full p-1 shadow-md hover:bg-red-50 dark:hover:bg-red-900 transition-colors z-10 border border-slate-100 dark:border-slate-600">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>
                `;
                gallery.appendChild(div);
            });
            lucide.createIcons();
        }

        function removeUploadedFile(index) {
            uploadedFiles.splice(index, 1);
            renderPreviewGallery();
        }

        function deleteTask() {
            const taskId = document.getElementById('modalTaskId').value;
            Swal.fire({
                title: 'ยืนยันการลบงาน?',
                text: "ข้อมูลและไฟล์แนบจะถูกลบถาวร ไม่สามารถกู้คืนได้",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก',
                customClass: { popup: 'rounded-2xl font-[Prompt]' }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=delete_task&task_id=' + taskId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', 'ลบงานเรียบร้อยแล้ว', 'success').then(() => { location.reload(); });
                            } else {
                                Swal.fire('Error!', 'เกิดข้อผิดพลาดในการลบ', 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error!', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error');
                        });
                }
            });
        }

        function generateAttachmentHTML(files) {
            let html = '';
            files.forEach(file => {
                const fileExt = file.file_name.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt);
                if (isImage) {
                    html += `<div onclick="openLightbox('${file.file_path}')" class="cursor-pointer group relative overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 aspect-square bg-white dark:bg-slate-800 shadow-sm hover:shadow-md transition-all">
                                <img src="${file.file_path}" class="w-full h-full object-cover transition-transform group-hover:scale-105" alt="${file.file_name}">
                                <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"><i data-lucide="maximize-2" class="w-5 h-5"></i></div>
                            </div>`;
                } else {
                    html += `<a href="${file.file_path}" target="_blank" class="flex flex-col items-center justify-center p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:shadow-md transition-all aspect-square group text-center">
                                <i data-lucide="file-text" class="w-8 h-8 text-slate-400 group-hover:text-indigo-500 mb-2 transition-colors"></i>
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 truncate w-full px-2">${file.file_name}</span>
                            </a>`;
                }
            });
            return html;
        }

        function openTaskModal(tr) {
            const taskIdForRead = tr.dataset.taskId;
            fetch('boss_mark_as_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'task_id=' + encodeURIComponent(taskIdForRead)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.is_new) {
                        const badge = document.getElementById('sidebarTaskBadge') || document.getElementById('bossSidebarBadge');
                        if (badge) {
                            let c = parseInt(badge.innerText);
                            (c > 1) ? badge.innerText = c - 1: badge.remove();
                        }
                    }
                });

            tr.classList.remove('bg-red-50/50', 'bg-blue-50/50', 'border-l-4', 'border-l-red-500', 'border-l-blue-500', 'dark:bg-red-900/10', 'dark:bg-blue-900/10');
            tr.classList.add('hover:bg-slate-50/80');
            const alertBadge = tr.querySelector('span.animate-pulse');
            if (alertBadge) alertBadge.remove();

            const taskId = tr.dataset.taskId;
            const title = tr.dataset.title;
            const desc = tr.dataset.desc;
            const submissionNote = tr.dataset.submissionNote;
            const company = tr.dataset.company;
            const assignee = tr.dataset.assignee;
            const createdBy = tr.dataset.createdBy;
            const status = tr.dataset.status;
            const assignDate = tr.dataset.assignDate;
            const dueDate = tr.dataset.dueDate;
            const startedAt = tr.dataset.startedAt;
            const completedAt = tr.dataset.completedAt;
            const rawAttachments = tr.dataset.attachments;

            document.getElementById('modalAssignTimeDisplay').innerText = assignDate || "-";
            document.getElementById('modalDueDateDisplay').innerText = dueDate || "-";
            document.getElementById('modalTaskId').value = taskId;
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalDesc').innerText = desc || "-";
            document.getElementById('modalCompany').innerText = company || "ทั่วไป";
            document.getElementById('modalAssignee').innerText = assignee;
            document.getElementById('modalAssigneeInit').innerText = assignee.charAt(0);
            document.getElementById('modalCreatedBy').innerText = createdBy;

            const subNoteEl = document.getElementById('modalSubmissionNote');
            const subNoteContainer = document.getElementById('modalSubmissionNoteContainer');
            if (submissionNote && submissionNote.trim() !== '') {
                subNoteEl.innerText = submissionNote;
                subNoteContainer.classList.remove('hidden');
            } else {
                subNoteContainer.classList.add('hidden');
            }

            document.getElementById('submitWorkForm').reset();
            uploadedFiles = [];
            renderPreviewGallery();

            const actionContainer = document.getElementById('modalActionContainer');
            const submitSection = document.getElementById('submitWorkSection');
            const statusBadge = document.getElementById('modalStatusBadge');

            actionContainer.innerHTML = '';
            submitSection.classList.add('hidden');

            const isOwner = (assignee === currentUserName);

            if (isOwner) {
                if (status === 'มอบหมาย') {
                    statusBadge.innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300">มอบหมาย</span>`;
                    actionContainer.innerHTML = `<button onclick="startWork()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-base font-bold shadow-lg shadow-blue-200 dark:shadow-blue-900/50 transition-all flex items-center justify-center gap-2 active:scale-95"><i data-lucide="play" class="w-5 h-5"></i> รับงานทันที</button>`;
                } else if (status === 'ดำเนินการ') {
                    statusBadge.innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300"><span class="w-2 h-2 bg-orange-500 rounded-full mr-1.5 animate-pulse"></span>กำลังดำเนินการ</span>`;
                    submitSection.classList.remove('hidden');
                } else {
                    statusBadge.innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300"><i data-lucide="check-circle-2" class="w-3 h-3 mr-1"></i> สำเร็จ</span>`;
                }
            } else {
                if (status === 'มอบหมาย') {
                    statusBadge.innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300">มอบหมาย</span>`;
                    actionContainer.innerHTML = `<div class="p-3 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-center text-slate-400 dark:text-slate-500 text-sm"><i data-lucide="eye" class="w-4 h-4 inline mr-1"></i> ดูข้อมูลเท่านั้น (รอผู้รับผิดชอบกดรับงาน)</div>`;
                } else if (status === 'ดำเนินการ') {
                    statusBadge.innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300"><span class="w-2 h-2 bg-orange-500 rounded-full mr-1.5 animate-pulse"></span>กำลังดำเนินการ</span>`;
                    actionContainer.innerHTML = `<div class="p-3 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-center text-slate-400 dark:text-slate-500 text-sm"><i data-lucide="loader" class="w-4 h-4 inline mr-1"></i> ดูข้อมูลเท่านั้น (งานกำลังดำเนินการ)</div>`;
                } else {
                    statusBadge.innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300"><i data-lucide="check-circle-2" class="w-3 h-3 mr-1"></i> สำเร็จ</span>`;
                }
            }

            let timelineHTML = `<div class="relative pl-6 border-l-2 border-slate-100 dark:border-slate-700 space-y-6"><div class="relative"><div class="absolute -left-[31px] bg-white dark:bg-slate-800 border-4 border-slate-100 dark:border-slate-700 w-4 h-4 rounded-full z-10 box-content bg-blue-500 border-blue-100 dark:border-blue-900/50"></div><p class="text-[10px] text-blue-500 dark:text-blue-400 font-bold uppercase mb-0.5">มอบหมายเมื่อ</p><p class="text-sm font-medium text-slate-700 dark:text-slate-200">${assignDate}</p></div>`;
            if (startedAt || completedAt) {
                timelineHTML += `<div class="relative"><div class="absolute -left-[31px] bg-white dark:bg-slate-800 border-4 border-slate-100 dark:border-slate-700 w-4 h-4 rounded-full z-10 box-content ${status == 'ดำเนินการ' || status == 'สำเร็จ' ? 'bg-orange-500 border-orange-100 dark:border-orange-900/50' : ''}"></div><p class="text-[10px] text-orange-500 dark:text-orange-400 font-bold uppercase mb-0.5">เริ่มดำเนินงาน</p><p class="text-sm font-medium text-slate-700 dark:text-slate-200">${startedAt ? startedAt : '-'}</p></div>`;
            }
            if (status === 'สำเร็จ' && completedAt) {
                timelineHTML += `<div class="relative"><div class="absolute -left-[31px] bg-white dark:bg-slate-800 border-4 border-slate-100 dark:border-slate-700 w-4 h-4 rounded-full z-10 box-content bg-emerald-500 border-emerald-100 dark:border-emerald-900/50"></div><p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold uppercase mb-0.5">ดำเนินการเสร็จสิ้น</p><p class="text-sm font-bold text-slate-800 dark:text-slate-100">${completedAt}</p></div>`;
            }
            timelineHTML += `</div>`;
            document.getElementById('modalTimelineContainer').innerHTML = timelineHTML;

            let attachments = [];
            try { attachments = JSON.parse(rawAttachments); } catch (e) { console.error("JSON Error", e); }

            const assignerFiles = attachments.filter(f => f.is_submission == 0 || !f.is_submission);
            const workFiles = attachments.filter(f => f.is_submission == 1);

            document.getElementById('modalAssignerAttachments').innerHTML = generateAttachmentHTML(assignerFiles);
            document.getElementById('noAssignerAttachments').className = assignerFiles.length > 0 ? 'hidden' : 'flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-600 text-slate-400 dark:text-slate-500 text-xs gap-2';

            document.getElementById('modalWorkAttachments').innerHTML = generateAttachmentHTML(workFiles);
            document.getElementById('noWorkAttachments').className = workFiles.length > 0 ? 'hidden' : 'flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-600 text-slate-400 dark:text-slate-500 text-xs gap-2';

            lucide.createIcons();
            toggleModal();
        }

        function startWork() {
            const taskId = document.getElementById('modalTaskId').value;
            const formData = new FormData();
            formData.append('action', 'start_work');
            formData.append('task_id', taskId);

            Swal.fire({
                title: 'ยืนยันการเริ่มงาน?',
                text: "สถานะงานจะเปลี่ยนเป็น 'กำลังดำเนินการ'",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                customClass: { popup: 'rounded-2xl font-[Prompt]' }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    sendData(formData);
                }
            });
        }

        function submitWork() {
            const taskId = document.getElementById('modalTaskId').value;
            const workNote = document.getElementById('work_note').value;
            const formData = new FormData();
            formData.append('action', 'save_work');
            formData.append('task_id', taskId);
            formData.append('work_note', workNote);
            uploadedFiles.forEach(file => { formData.append('work_files[]', file); });

            Swal.fire({
                title: 'ยืนยันการส่งงาน?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ยืนยันส่งงาน',
                cancelButtonText: 'ยกเลิก',
                customClass: { popup: 'rounded-2xl font-[Prompt]' }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'กำลังส่งข้อมูล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    sendData(formData);
                }
            });
        }

        function sendData(formData) {
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: data.message, timer: 2000, showConfirmButton: false, customClass: { popup: 'rounded-2xl font-[Prompt]' }})
                        .then(() => { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message, customClass: { popup: 'rounded-2xl font-[Prompt]' }});
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                });
        }
    </script>
</body>
</html>