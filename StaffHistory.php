<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตรวจสอบ Login
if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$my_name = $_SESSION['fullname'];

// --- Dummy function ---
if (!function_exists('hasAction')) {
    function hasAction($action_code)
    {
        return true;
    }
}

// 1. Logic เลือก Tab
$allowed_tabs = [];
if (hasAction('view_sales_tab'))
    $allowed_tabs[] = 'sales';
if (hasAction('view_purchase_tab'))
    $allowed_tabs[] = 'purchase';
if (hasAction('view_marketing_tab'))
    $allowed_tabs[] = 'marketing';
if (hasAction('view_admin_tab'))
    $allowed_tabs[] = 'admin';
if (hasAction('view_hr_tab'))
    $allowed_tabs[] = 'hr';
if (hasAction('view_delivery_tab'))
    $allowed_tabs[] = 'delivery';
if (hasAction('view_warehouse_tab'))
    $allowed_tabs[] = 'warehouse';
$default_tab = !empty($allowed_tabs) ? $allowed_tabs[0] : 'sales';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

if (!empty($allowed_tabs) && !in_array($current_tab, $allowed_tabs)) {
    $current_tab = $default_tab;
}

$is_sales = ($current_tab == 'sales');

// Helper Function
function getStatusConfig($status)
{
    if (strpos($status, 'ชำระแล้ว') !== false || strpos($status, 'ได้รับแล้ว') !== false || strpos($status, 'ได้งาน') !== false || strpos($status, 'ส่งแล้ว') !== false || strpos($status, 'สำเร็จ') !== false || strpos($status, 'เสร็จสิ้น') !== false)
        return ['bg' => 'rgba(16, 185, 129, 0.2)', 'text' => '#10b981', 'icon' => 'fa-check-circle'];
    if (strpos($status, 'รอ') !== false || strpos($status, 'ติดตาม') !== false || strpos($status, 'เก็บเงินปลายทาง') !== false || strpos($status, 'กำลังดำเนินการ') !== false)
        return ['bg' => 'rgba(245, 158, 11, 0.2)', 'text' => '#f59e0b', 'icon' => 'fa-clock'];
    if (strpos($status, 'ยกเลิก') !== false || strpos($status, 'คืนเงิน') !== false || strpos($status, 'ไม่มี') !== false || strpos($status, 'ไม่ได้') !== false || strpos($status, 'ตีกลับ') !== false)
        return ['bg' => 'rgba(239, 68, 68, 0.2)', 'text' => '#ef4444', 'icon' => 'fa-times-circle'];
    return ['bg' => 'rgba(100, 116, 139, 0.2)', 'text' => '#64748b', 'icon' => 'fa-tag'];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการทำงาน - <?php echo $_SESSION['fullname']; ?></title>

    <link
        href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        // --- Prevent FOUC ---
        (function () {
            if (localStorage.getItem('tjc_theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.body?.classList.add('dark-mode');
            }
        })();
    </script>

    <style>
        :root {
            /* Light Mode Defaults */
            --primary:
                <?php echo ($current_tab == 'marketing' ? '#6366f1' : ($current_tab == 'purchase' ? '#059669' : ($current_tab == 'admin' ? '#4f46e5' : '#4f46e5'))); ?>
            ;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-input: #ffffff;
            --bg-hover: #f1f5f9;
            --bg-inner: #f8fafc;

            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;

            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-glow: 0 8px 20px rgba(0, 0, 0, 0.1);

            --radius-md: 12px;
            --radius-lg: 20px;
        }

        /* 🌙 Dark Mode Override */
        body.dark-mode {
            --primary:
                <?php echo ($current_tab == 'marketing' ? '#818cf8' : ($current_tab == 'purchase' ? '#34d399' : ($current_tab == 'admin' ? '#60a5fa' : '#60a5fa'))); ?>
            ;
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --bg-hover: #334155;
            --bg-inner: #0f172a;

            --text-main: #f8fafc;
            --text-muted: #cbd5e1;
            --border-color: #334155;

            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.5);
        }

        * {
            box-sizing: border-box;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: var(--bg-body);
            margin: 0;
            color: var(--text-main);
        }

        .main-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .page-header {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 35px;
        }

        @media(min-width: 768px) {
            .page-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-end;
            }
        }

        .header-title h2 {
            margin: 0;
            font-size: 30px;
            font-weight: 700;
            color: var(--text-main);
        }

        .header-title p {
            margin: 5px 0 0;
            color: var(--text-muted);
            font-size: 15px;
        }

        /* Tab Navigation */
        .tab-container {
            background: var(--bg-card);
            padding: 6px;
            border-radius: 50px;
            display: inline-flex;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 5px;
        }

        .tab-item {
            padding: 10px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            transition: 0.3s;
        }

        .tab-item:hover {
            color: var(--primary);
            background: var(--bg-hover);
        }

        .tab-item.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Shared Styles for Sub-pages */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .kpi-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: 0.3s;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            border-color: var(--primary);
        }

        .kpi-label {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .kpi-value {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.1;
            color: var(--text-main);
            z-index: 2;
        }

        .kpi-icon-bg {
            position: absolute;
            right: -15px;
            bottom: -20px;
            font-size: 100px;
            opacity: 0.08;
            transform: rotate(-15deg);
        }

        .kpi-bar {
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }

        .filter-section {
            background: var(--bg-card);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 180px;
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            font-family: 'Prompt';
            font-size: 14px;
            box-sizing: border-box;
            background: var(--bg-input);
            color: var(--text-main);
            transition: 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-search-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            height: 48px;
        }

        .btn-search {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 32px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            height: 100%;
            transition: 0.2s;
            white-space: nowrap;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-search:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .link-reset {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .link-reset:hover {
            color: var(--primary);
            background: var(--bg-hover);
        }

        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 1000px;
        }

        thead th {
            background: var(--bg-input);
            color: var(--text-muted);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            padding: 20px 24px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        tbody td {
            padding: 20px 24px;
            color: var(--text-main);
            font-size: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        tbody tr:hover td {
            background-color: var(--bg-hover);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .data-highlight {
            font-weight: 600;
            color: var(--text-main);
        }

        .btn-view {
            border: 1px solid var(--border-color);
            background: var(--bg-input);
            color: var(--text-muted);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-view:hover {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        /* Modal General */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-card);
            color: var(--text-main);
            width: 90%;
            max-width: 650px;
            border-radius: var(--radius-lg);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            animation: slideUp 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            padding: 20px 30px;
            background: var(--bg-card);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: var(--text-main);
            font-weight: 700;
        }

        .modal-body {
            padding: 30px;
            max-height: 75vh;
            overflow-y: auto;
            background: var(--bg-body);
        }

        .modal-close {
            cursor: pointer;
            color: var(--text-muted);
            font-size: 24px;
        }

        .modal-close:hover {
            color: var(--primary);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Modal Utilities */
        .total-box {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 16px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }

        .total-income {
            background: linear-gradient(135deg, #4f46e5, #4338ca);
        }

        .total-expense {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
        }

        .d-group {
            margin-bottom: 24px;
        }

        .d-lbl {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .d-val {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-main);
        }

        /* Shop Card & Others */
        .order-card-modern,
        .shop-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            margin-bottom: 16px;
        }

        .expense-box-row {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin-top: 10px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        <div class="page-header">
            <div class="header-title">
                <h2>ประวัติการทำงานของฉัน</h2>
                <p>รายการที่คุณบันทึกในระบบทั้งหมด</p>
            </div>

            <div class="tab-container">
                <?php if (hasAction('view_sales_tab')): ?>
                    <a href="?tab=sales" class="tab-item <?php echo $is_sales ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> ฝ่ายขาย
                    </a>
                <?php endif; ?>

                <?php if (hasAction('view_purchase_tab')): ?>
                    <a href="?tab=purchase" class="tab-item <?php echo $current_tab == 'purchase' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> ฝ่ายจัดซื้อ
                    </a>
                <?php endif; ?>

                <?php if (hasAction('view_marketing_tab')): ?>
                    <a href="?tab=marketing" class="tab-item <?php echo $current_tab == 'marketing' ? 'active' : ''; ?>">
                        <i class="fas fa-bullhorn"></i> การตลาด
                    </a>
                <?php endif; ?>

                <?php if (hasAction('view_admin_tab')): ?>
                    <a href="?tab=admin" class="tab-item <?php echo $current_tab == 'admin' ? 'active' : ''; ?>">
                        <i class="fas fa-folder-open"></i> ธุรการ
                    </a>
                <?php endif; ?>
                <?php if (hasAction('view_hr_tab')): ?>
                    <a href="?tab=hr" class="tab-item <?php echo $current_tab == 'hr' ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i> ฝ่ายบุคคล
                    </a>
                <?php endif; ?>
                <?php if (hasAction('view_delivery_tab')): ?>
                    <a href="?tab=delivery" class="tab-item <?php echo $current_tab == 'delivery' ? 'active' : ''; ?>"
                        style="<?php echo $current_tab == 'delivery' ? 'background:#f59e0b; color:#fff;' : ''; ?>">
                        <i class="fas fa-truck"></i> ฝ่ายจัดส่ง
                    </a>
                <?php endif; ?>
                <?php if (hasAction('view_warehouse_tab')): // หรือใช้ hasAction('view_delivery_tab') ไปก่อนก็ได้ครับ ?>
                    <a href="?tab=warehouse" class="tab-item <?= $current_tab == 'warehouse' ? 'active' : ''; ?>"
                        style="<?= $current_tab == 'warehouse' ? 'background:#0d9488; color:#fff;' : ''; ?>">
                        <i class="fas fa-warehouse"></i> ฝ่ายคลังสินค้า
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php
        switch ($current_tab) {
            case 'marketing':
                include 'tabs/tab_marketing.php';
                break;
            case 'purchase':
                include 'tabs/tab_purchase.php';
                break;
            case 'admin':
                include 'tabs/tab_admin.php';
                break;
            case 'hr':
                include 'tabs/tab_hr.php';
                break;
            case 'delivery':
                include 'tabs/tab_delivery.php';
                break;
            case 'warehouse':
                include 'tabs/tab_warehouse.php';
                break;
            default:
                include 'tabs/tab_sales.php';
                break;
        }
        ?>

    </div>

    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>รายละเอียดรายงาน</h3>
                <div class="modal-close" onclick="closeModal('detailModal')">&times;</div>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <div id="expenseModal" class="modal" onclick="if(event.target==this)closeModal('expenseModal')">
        <div class="modal-content" style="max-width: 550px;">
            <form id="editExpenseForm" onsubmit="event.preventDefault(); saveEdit();">
                <input type="hidden" name="action" value="update_expense">
                <input type="hidden" name="report_id" id="ex_report_id">

                <div class="modal-header-orange">
                    <h3><i class="fa-solid fa-coins"></i> อัปเดตรายละเอียดค่าใช้จ่าย</h3>
                    <span onclick="closeModal('expenseModal')" class="modal-close">&times;</span>
                </div>

                <div class="modal-body" style="padding: 25px; background: #fff;">
                    <div class="expense-edit-group">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <label class="detail-label"><i class="fa-solid fa-gas-pump"></i> ค่าน้ำมัน
                                (ระบุเป็นรายการ)</label>
                            <button type="button" onclick="addFuelRowEdit()" class="btn-view"
                                style="width:auto; height:30px; padding:0 10px; font-size:12px; background:#eff6ff; color:#2563eb;">
                                <i class="fa-solid fa-plus me-1"></i> เพิ่มบิล
                            </button>
                        </div>
                        <div id="fuel_edit_container">
                        </div>
                    </div>

                    <div class="expense-edit-group">
                        <label class="detail-label"><i class="fa-solid fa-hotel"></i> ค่าที่พัก</label>
                        <div style="display:flex; gap:10px;">
                            <input type="number" step="0.01" name="accommodation_cost" id="ex_hotel"
                                class="form-control" placeholder="0.00" oninput="calcTotalEdit()">
                            <div style="width:50%;">
                                <label class="upload-btn-mini">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> เปลี่ยนสลิป
                                    <input type="file" name="hotel_file" accept="image/*" hidden
                                        onchange="previewFile(this, 'prev_hotel')">
                                </label>
                                <div id="prev_hotel" class="file-status"></div>
                            </div>
                        </div>
                    </div>

                    <div class="expense-edit-group" style="border-bottom:none;">
                        <label class="detail-label"><i class="fa-solid fa-receipt"></i> ค่าใช้จ่ายอื่นๆ</label>
                        <div style="display:grid; grid-template-columns: 1fr 1.5fr 1fr; gap:10px;">
                            <input type="number" step="0.01" name="other_cost" id="ex_other" class="form-control"
                                placeholder="0.00" oninput="calcTotalEdit()">
                            <input type="text" name="other_detail" id="ex_other_detail" class="form-control"
                                placeholder="ระบุรายละเอียด">
                            <div>
                                <label class="upload-btn-mini">
                                    <i class="fa-solid fa-upload"></i> สลิป
                                    <input type="file" name="other_file" accept="image/*" hidden
                                        onchange="previewFile(this, 'prev_other')">
                                </label>
                                <div id="prev_other" class="file-status"></div>
                            </div>
                        </div>
                    </div>

                    <div class="total-card">
                        <div style="font-size:0.85rem; opacity:0.8; margin-bottom:5px;">ยอดเบิกรวมสุทธิใหม่</div>
                        <div style="font-size:2.2rem; font-weight:800;" id="ex_total_display">0.00 ฿</div>
                    </div>

                    <button type="submit" class="btn-save-orange">
                        <i class="fa-solid fa-floppy-disk me-2"></i> บันทึกการอัปเดต
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>const uploadPath = 'uploads/';</script>

    <script src="js/dashboard_script.js">
        window.onclick = function (e) {
            if (e.target == document.getElementById('detailModal')) {
                closeModal('detailModal'); // เติม ID ลงไปในนี้ด้วย
            }
        }
    </script>
</body>

</html>