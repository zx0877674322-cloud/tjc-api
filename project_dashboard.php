<?php
session_start();
require_once 'db_connect.php'; // เปลี่ยนให้ตรงกับไฟล์เชื่อมต่อ DB ของลูกพี่

// ==========================================
// 1. ดึงข้อมูลสรุป (KPIs)
// ==========================================
// 1.1 ภาพรวมทั้งหมด
$kpi_query = "SELECT 
    COUNT(id) AS total_projects,
    COALESCE(SUM(project_budget), 0) AS total_budget,
    SUM(CASE WHEN status = 'เซ็นสัญญา' THEN 1 ELSE 0 END) AS signed_projects,
    SUM(CASE WHEN status = 'รอเซ็นสัญญา' THEN 1 ELSE 0 END) AS waiting_projects,
    SUM(CASE 
        WHEN status = 'เซ็นสัญญา' AND end_date IS NOT NULL 
        THEN CASE 
            WHEN DATEDIFF(end_date, CURDATE()) <= COALESCE(NULLIF(alert_days_before_expire, 0), 30) THEN 1 
            ELSE 0 
        END 
        ELSE 0 
    END) AS expiring_projects,
    SUM(CASE 
        WHEN status = 'เซ็นสัญญา' AND guarantee_end_date IS NOT NULL AND guarantee_end_date != '0000-00-00'
        THEN CASE 
            WHEN DATEDIFF(guarantee_end_date, CURDATE()) <= COALESCE(NULLIF(alert_warranty_days, 0), 30) AND DATEDIFF(guarantee_end_date, CURDATE()) >= 0 THEN 1 
            ELSE 0 
        END ELSE 0 END) AS expiring_warranty
FROM projects";

$kpi_result = $conn->query($kpi_query);
$kpi_all = $kpi_result->fetch_assoc();

// 1.2 ข้อมูล KPI แยกตามบริษัท (สำหรับ JavaScript)
$kpi_company_query = "SELECT 
    company_id,
    COUNT(id) AS total_projects,
    COALESCE(SUM(project_budget), 0) AS total_budget,
    SUM(CASE WHEN status = 'เซ็นสัญญา' THEN 1 ELSE 0 END) AS signed_projects,
    SUM(CASE WHEN status = 'รอเซ็นสัญญา' THEN 1 ELSE 0 END) AS waiting_projects,
    SUM(CASE 
        WHEN status = 'เซ็นสัญญา' AND end_date IS NOT NULL 
        THEN CASE 
            WHEN DATEDIFF(end_date, CURDATE()) <= COALESCE(NULLIF(alert_days_before_expire, 0), 30) THEN 1 
            ELSE 0 
        END 
        ELSE 0 
    END) AS expiring_projects,
    SUM(CASE 
        WHEN status = 'เซ็นสัญญา' AND guarantee_end_date IS NOT NULL AND guarantee_end_date != '0000-00-00'
        THEN CASE 
            WHEN DATEDIFF(guarantee_end_date, CURDATE()) <= COALESCE(NULLIF(alert_warranty_days, 0), 30) AND DATEDIFF(guarantee_end_date, CURDATE()) >= 0 THEN 1 
            ELSE 0 END ELSE 0 END) AS expiring_warranty
FROM projects GROUP BY company_id";

$kpi_company_result = $conn->query($kpi_company_query);
$kpi_by_company = [];
while ($row = $kpi_company_result->fetch_assoc()) {
    $kpi_by_company[$row['company_id']] = $row;
}
$kpi_by_company_json = json_encode($kpi_by_company);

// 1.3 รายชื่อบริษัทสำหรับดึงมาทำปุ่มกรอง
$companies_query = "SELECT id, company_name, company_shortname, logo_file FROM companies ORDER BY list_order ASC, id ASC";
$companies_result = $conn->query($companies_query);
$companies = [];
if ($companies_result) {
    while ($row = $companies_result->fetch_assoc()) {
        $companies[] = $row;
    }
}

// ==========================================
// 2. ดึงข้อมูลตารางโครงการ (15 รายการล่าสุด)
// ==========================================
// หมายเหตุ: ใช้ LEFT JOIN เพื่อดึงชื่อลูกค้าและบริษัท (ปรับชื่อ field ให้ตรงกับตารางจริงของลูกพี่)
$sql_list = "SELECT p.*, 
            COALESCE(NULLIF(c.company_shortname, ''), c.company_name, 'ไม่ระบุ') as company_name, 
            COALESCE(cust.customer_name, 'ไม่ระบุ') as customer_name,
            COALESCE(p.customer_address, CONCAT_WS(' ', cust.address, cust.sub_district, cust.district, cust.province, cust.zip_code), 'ไม่ระบุ') as customer_address,
            COALESCE(p.customer_phone, cust.phone_number, 'ไม่ระบุ') as customer_phone,
            cust.contact_person,  /* <--- เพิ่ม */
            cust.contact_phone,   /* <--- เพิ่ม */
            COALESCE(p.customer_affiliation, cust.affiliation, 'ไม่ระบุ') as customer_affiliation,
            COALESCE(jt.type_name, 'ไม่ระบุ') as job_type_name
        FROM projects p
        LEFT JOIN companies c ON p.company_id = c.id
        LEFT JOIN customers cust ON p.customer_id = cust.customer_id
        LEFT JOIN project_job_types jt ON p.job_type_id = jt.id
        ORDER BY p.created_at ASC";
$projects = $conn->query($sql_list);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title>Project Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f1f5f9;
            color: #334155;
            margin: 0;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
        }

        .page-title {
            margin: 0;
            color: #1e293b;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-create {
            background: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        }

        .btn-create:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 5px solid;
            transition: 0.3s;
        }

        .kpi-card.clickable {
            cursor: pointer;
        }

        .kpi-card.clickable:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .kpi-card.active {
            box-shadow: 0 0 0 3px currentColor;
            transform: translateY(-3px);
        }

        .kpi-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .kpi-info {
            flex-grow: 1;
        }

        .kpi-title {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .kpi-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
        }

        .card-blue {
            border-color: #3b82f6;
        }

        .card-blue .kpi-icon {
            background: #eff6ff;
            color: #3b82f6;
        }

        .card-green {
            border-color: #10b981;
        }

        .card-green .kpi-icon {
            background: #ecfdf5;
            color: #10b981;
        }

        .card-orange {
            border-color: #f59e0b;
        }

        .card-orange .kpi-icon {
            background: #fffbeb;
            color: #f59e0b;
        }

        .card-purple {
            border-color: #8b5cf6;
        }

        .card-purple .kpi-icon {
            background: #f5f3ff;
            color: #8b5cf6;
        }

        .card-red {
            border-color: #ef4444;
            color: #ef4444;
            /* For active box-shadow */
        }

        .card-red .kpi-icon {
            background: #fef2f2;
            color: #ef4444;
        }

        .card-blue {
            color: #3b82f6;
        }

        .card-green {
            color: #10b981;
        }

        .card-orange {
            color: #f59e0b;
        }

        /* Top Level "All Companies" Card */
        .all-company-dashboard-container {
            margin-bottom: 20px;
        }

        .all-company-dashboard-container .company-group {
            margin-bottom: 0px;
        }

        .kpi-mini-grid-horizontal {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
        }

        @media (max-width: 1200px) {
            .kpi-mini-grid-horizontal {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .kpi-mini-grid-horizontal {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .kpi-mini-grid-horizontal {
                grid-template-columns: 1fr;
            }
        }

        /* Nested Company Cards */
        .company-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            align-items: start;
        }

        @media (max-width: 1400px) {
            .company-dashboard-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1100px) {
            .company-dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .company-dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .company-group {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .company-group:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .company-group.active {
            border-color: #3b82f6;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.2);
        }

        .company-title-area {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            cursor: pointer;
            padding: 4px;
            border-radius: 8px;
        }

        .company-title-area:hover .company-badge {
            background: #3b82f6;
            color: #fff;
        }

        .company-badge {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: #f1f5f9;
            color: #64748b;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.25rem;
            transition: 0.3s;
        }

        .company-group.active .company-badge {
            background: #3b82f6;
            color: #fff;
        }

        .company-logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 10px;
            padding: 2px;
            background-color: white;
        }

        .company-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .kpi-mini-grid {
            display: block;
        }

        .kpi-mini-grid .kpi-mini-card {
            margin-bottom: 15px;
        }

        .kpi-mini-grid .kpi-mini-card:last-child {
            margin-bottom: 0;
        }

        .kpi-mini-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            border-left: 4px solid #cbd5e1;
            position: relative;
            z-index: 10;
            overflow: hidden;
            max-height: 200px;
            transition: all 0.4s ease-in-out;
        }

        .kpi-mini-card.clickable {
            cursor: pointer;
        }

        .kpi-mini-card.clickable:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .kpi-mini-card.active {
            box-shadow: 0 0 0 3px currentColor;
        }

        .company-group.collapsed .hide-on-collapse {
            max-height: 0;
            padding: 0 !important;
            margin-bottom: 0 !important;
            border: none !important;
            opacity: 0;
            overflow: hidden;
            font-size: 0;
        }

        .toggle-collapse-btn {
            margin-left: auto;
            color: #94a3b8;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: 0.3s;
        }

        .toggle-collapse-btn:hover {
            color: #3b82f6;
            background: #eff6ff;
        }

        .company-group.collapsed .toggle-collapse-btn i {
            transform: rotate(-90deg);
        }

        .mini-title {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mini-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        /* Border specific colors */
        .border-blue {
            border-left-color: #3b82f6;
        }

        .text-blue {
            color: #3b82f6;
        }

        .border-purple {
            border-left-color: #8b5cf6;
        }

        .text-purple {
            color: #8b5cf6;
        }

        .border-green {
            border-left-color: #10b981;
        }

        .text-green {
            color: #10b981;
        }

        .border-orange {
            border-left-color: #f59e0b;
        }

        .text-orange {
            color: #f59e0b;
        }

        .border-red {
            border-left-color: #ef4444;
        }

        .text-red {
            color: #ef4444;
        }

        /* Table Section */
        .table-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 35px;
            border: 1px solid #cbd5e1;
            border-radius: 50px;
            font-family: 'Prompt';
            outline: none;
            transition: 0.3s;
            box-sizing: border-box;
        }

        .search-box input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #ffffff;
            color: #475569;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-size: 0.95rem;
            color: #334155;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* Badges & Text Styles */
        .text-bold {
            font-weight: 700;
            color: #1e293b;
        }

        .text-sub {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 3px;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .badge-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .badge-warning {
            background: #fffbeb;
            color: #e9a659ff;
            border: 1px solid #fcd34d;
        }

        .badge-danger {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fca5a5;
        }

        /* Pulse animation for expiring projects */
        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }

            70% {
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .expiring-row {
            background-color: #fef2f2 !important;
            border-left: 4px solid #ef4444 !important;
        }

        .expiring-badge {
            animation: pulse-red 2s infinite;
        }

        /* Action Buttons */
        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .btn-view {
            background: #eff6ff;
            color: #3b82f6;
        }

        .btn-view:hover {
            background: #3b82f6;
            color: #fff;
        }

        .btn-edit {
            background: #fef3c7;
            color: #d97706;
        }

        .btn-edit:hover {
            background: #f59e0b;
            color: #fff;
        }

        .currency {
            font-weight: 800;
            color: #059669;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
        }

        .modal-content-box {
            background: #fff;
            width: 90%;
            max-width: 500px;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.9) translateY(20px);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }

        .modal-overlay.show .modal-content-box {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: 0.3s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
            max-height: 70vh;
            overflow-y: auto;
        }

        /* ตกแต่ง Scrollbar ของ Modal ให้สวยงาม */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-label {
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .detail-label i {
            color: #3b82f6;
            width: 16px;
        }

        .detail-value {
            color: #1e293b;
            font-weight: 600;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
            font-size: 0.95rem;
        }

        .border-cyan {
            border-left-color: #06b6d4 !important;
        }

        .text-cyan {
            color: #06b6d4 !important;
        }

        /* ไฮไลท์แถวในตารางเมื่อประกันใกล้หมด */
        .expiring-warranty-row {
            background-color: #ecfeff !important;
            border-left: 4px solid #06b6d4 !important;
        }

        .btn-list {
            background: #f3e8ff;
            color: #9333ea;
        }

        .btn-list:hover {
            background: #9333ea;
            color: #fff;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class="dashboard-container">

        <div class="page-header">
            <h2 class="page-title"><i class="fas fa-chart-pie text-blue-500"></i> Dashboard โครงการ</h2>
            <a href="create_project.php" class="btn-create">
                <i class="fas fa-plus"></i> เพิ่มโครงการใหม่
            </a>
        </div>

        <div class="all-company-dashboard-container">
            <!-- ภาพรวมทุกบริษัท (All Companies) -->
            <div class="company-group active" id="compCard_all">
                <div class="company-title-area" onclick="filterByCompAndStat('all', 'all')">
                    <div class="company-badge"><i class="fas fa-globe"></i></div>
                    <div class="company-name">ภาพรวมทุกบริษัท (รวมทั้งหมด)</div>
                </div>
                <div class="kpi-mini-grid-horizontal">
                    <div class="kpi-mini-card border-blue text-blue clickable mini-card-status" data-status="all"
                        onclick="filterByCompAndStat('all', 'all')">
                        <div class="mini-title"><i class="fas fa-folder-open"></i> โครงการทั้งหมด</div>
                        <div class="mini-value"><?= number_format($kpi_all['total_projects']) ?> <span
                                style="font-size:0.9rem;color:#94a3b8;font-weight:400;">งาน</span></div>
                    </div>
                    <div class="kpi-mini-card border-purple text-purple clickable mini-card-status" data-status="all"
                        onclick="filterByCompAndStat('all', 'all')">
                        <div class="mini-title"><i class="fas fa-money-bill-wave"></i> มูลค่างบประมาณรวม</div>
                        <div class="mini-value">฿<?= number_format($kpi_all['total_budget'], 2) ?></div>
                    </div>
                    <div class="kpi-mini-card border-green text-green clickable mini-card-status"
                        data-status="เซ็นสัญญา" onclick="filterByCompAndStat('all', 'เซ็นสัญญา')">
                        <div class="mini-title"><i class="fas fa-file-signature"></i> เซ็นสัญญาแล้ว</div>
                        <div class="mini-value"><?= number_format($kpi_all['signed_projects']) ?> <span
                                style="font-size:0.9rem;color:#94a3b8;font-weight:400;">งาน</span></div>
                    </div>
                    <div class="kpi-mini-card border-orange text-orange clickable mini-card-status"
                        data-status="รอเซ็นสัญญา" onclick="filterByCompAndStat('all', 'รอเซ็นสัญญา')">
                        <div class="mini-title"><i class="fas fa-hourglass-half"></i> รอเซ็นสัญญา</div>
                        <div class="mini-value"><?= number_format($kpi_all['waiting_projects']) ?> <span
                                style="font-size:0.9rem;color:#94a3b8;font-weight:400;">งาน</span></div>
                    </div>
                    <div class="kpi-mini-card border-red text-red clickable mini-card-status" data-status="expiring"
                        onclick="filterByCompAndStat('all', 'expiring')">
                        <div class="mini-title"><i class="fas fa-bell"></i> แจ้งเตือนใกล้หมดสัญญา</div>
                        <div class="mini-value"><?= number_format($kpi_all['expiring_projects']) ?> <span
                                style="font-size:0.9rem;color:#ef4444;font-weight:400;">งาน</span></div>
                    </div>
                    <div class="kpi-mini-card border-cyan text-cyan clickable mini-card-status"
                        data-status="expiring_warranty" onclick="filterByCompAndStat('all', 'expiring_warranty')">
                        <div class="mini-title"><i class="fas fa-shield-alt"></i> แจ้งเตือนหมดประกัน</div>
                        <div class="mini-value">
                            <?= number_format($kpi_all['expiring_warranty'] ?? 0) ?> <span
                                style="font-size:0.9rem;color:#94a3b8;font-weight:400;">งาน</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="company-dashboard-grid">

            <!-- แบ่งตามบริษัทย่อย -->
            <?php foreach ($companies as $comp):
                $cid = $comp['id'];
                $comp_name = !empty($comp['company_shortname']) ? $comp['company_shortname'] : $comp['company_name'];
                $logo_file = isset($comp['logo_file']) ? "uploads/logos/" . $comp['logo_file'] : "";

                $cdata = isset($kpi_by_company[$cid]) ? $kpi_by_company[$cid] : [
                    'total_projects' => 0,
                    'total_budget' => 0,
                    'signed_projects' => 0,
                    'waiting_projects' => 0,
                    'expiring_projects' => 0
                ];
                ?>
                <div class="company-group" id="compCard_<?= $cid ?>">
                    <div class="company-title-area" onclick="filterByCompAndStat('<?= $cid ?>', 'all')">
                        <div class="company-badge">
                            <?php if (!empty($comp['logo_file']) && file_exists($logo_file)): ?>
                                <img src="<?= $logo_file ?>" alt="Logo" class="company-logo-img">
                            <?php else: ?>
                                <i class="fas fa-building"></i>
                            <?php endif; ?>
                        </div>
                        <div class="company-name"><?= htmlspecialchars($comp_name) ?></div>
                        <button class="toggle-collapse-btn" onclick="toggleCompanyCard(event, '<?= $cid ?>')"
                            title="ย่อ/ขยายการ์ด">
                            <i class="fas fa-chevron-down" style="transition: transform 0.3s;"></i>
                        </button>
                    </div>
                    <div class="kpi-mini-grid">
                        <div class="kpi-mini-card border-blue text-blue clickable mini-card-status hide-on-collapse"
                            data-status="all" onclick="filterByCompAndStat('<?= $cid ?>', 'all')">
                            <div class="mini-title"><i class="fas fa-folder-open"></i> โครงการทั้งหมด</div>
                            <div class="mini-value"><?= number_format($cdata['total_projects']) ?> <span
                                    style="font-size:0.9rem;color:#94a3b8;font-weight:400;">งาน</span></div>
                        </div>
                        <div class="kpi-mini-card border-purple text-purple clickable mini-card-status" data-status="all"
                            onclick="filterByCompAndStat('<?= $cid ?>', 'all')">
                            <div class="mini-title"><i class="fas fa-money-bill-wave"></i> มูลค่างบประมาณรวม</div>
                            <div class="mini-value">฿<?= number_format($cdata['total_budget'], 2) ?></div>
                        </div>
                        <div class="kpi-mini-card border-green text-green clickable mini-card-status hide-on-collapse"
                            data-status="เซ็นสัญญา" onclick="filterByCompAndStat('<?= $cid ?>', 'เซ็นสัญญา')">
                            <div class="mini-title"><i class="fas fa-file-signature"></i> เซ็นสัญญาแล้ว</div>
                            <div class="mini-value"><?= number_format($cdata['signed_projects']) ?> <span
                                    style="font-size:0.9rem;color:#94a3b8;font-weight:400;">งาน</span></div>
                        </div>
                        <div class="kpi-mini-card border-orange text-orange clickable mini-card-status hide-on-collapse"
                            data-status="รอเซ็นสัญญา" onclick="filterByCompAndStat('<?= $cid ?>', 'รอเซ็นสัญญา')">
                            <div class="mini-title"><i class="fas fa-hourglass-half"></i> รอเซ็นสัญญา</div>
                            <div class="mini-value"><?= number_format($cdata['waiting_projects']) ?> <span
                                    style="font-size:0.9rem;color:#94a3b8;font-weight:400;">งาน</span></div>
                        </div>
                        <div class="kpi-mini-card border-red text-red clickable mini-card-status" data-status="expiring"
                            onclick="filterByCompAndStat('<?= $cid ?>', 'expiring')">
                            <div class="mini-title"><i class="fas fa-bell"></i> แจ้งเตือนหมดสัญญา</div>
                            <div class="mini-value"><?= number_format($cdata['expiring_projects']) ?> <span
                                    style="font-size:0.9rem;color:#ef4444;font-weight:400;">งาน</span></div>
                        </div>
                        <div class="kpi-mini-card border-cyan text-cyan clickable mini-card-status"
                            data-status="expiring_warranty"
                            onclick="filterByCompAndStat('<?= $cid ?>', 'expiring_warranty')">
                            <div class="mini-title"><i class="fas fa-shield-alt"></i> แจ้งเตือนหมดประกัน</div>
                            <div class="mini-value">
                                <?= number_format($cdata['expiring_warranty'] ?? 0) ?> <span
                                    style="font-size:0.9rem;color:#94a3b8;font-weight:400;">งาน</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="table-card">
            <div class="table-header">
                <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem;"><i class="fas fa-list"></i>
                    รายการโครงการล่าสุด</div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <div class="date-filter-box" style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 0.9rem; color: #64748b;">ช่วงวันที่:</span>
                        <input type="text" id="startDateFilter" class="form-control datepicker"
                            placeholder="เลือกวันที่..."
                            style="padding: 6px 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-family: 'Prompt'; width: 140px; text-align: center;">
                        <span style="font-size: 0.9rem; color: #64748b;">ถึง</span>
                        <input type="text" id="endDateFilter" class="form-control datepicker"
                            placeholder="เลือกวันที่..."
                            style="padding: 6px 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-family: 'Prompt'; width: 140px; text-align: center;">
                    </div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="ค้นหาชื่อโครงการ, ลูกค้า..."
                            onkeyup="filterTable()">
                    </div>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table id="projectTable">
                    <thead>
                        <tr>
                            <th width="8%">หน้างาน</th>
                            <th width="12%">บริษัท</th>

                            <th width="10%" style="text-align: center;">สิ้นสุดสัญญา</th>
                            <th width="10%" style="text-align: center;">ครบกำหนดส่ง</th>

                            <th width="10%" style="text-align: center; color:#2563eb;">ระยะประกัน</th>
                            <th width="10%" style="text-align: center;">เริ่มค้ำฯ</th>
                            <th width="10%" style="text-align: center;">สิ้นสุดค้ำฯ</th>

                            <th width="15%">ลูกค้า</th>
                            <th width="8%">ประเภทงาน</th>
                            <th width="8%" style="text-align: center;">สถานะ</th>
                            <th width="7%" style="text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($projects && $projects->num_rows > 0): ?>
                            <?php while ($row = $projects->fetch_assoc()):
                                // --- เตรียมตัวแปรวันที่ปัจจุบัน ---
                                $today = new DateTime();
                                $today->setTime(0, 0, 0);

                                // 1. จัดการข้อมูลสัญญา (ครบกำหนดส่ง)
                                $is_signed = ($row['status'] == 'เซ็นสัญญา');
                                $badge_class = $is_signed ? 'badge-success' : 'badge-warning';
                                $badge_icon = $is_signed ? 'fa-check-circle' : 'fa-clock';
                                $end_date_show = !empty($row['end_date']) ? date('d/m/Y', strtotime($row['end_date'])) : '-';

                                $contract_countdown_html = "-";
                                $is_row_expiring = false;
                                $alert_contract = isset($row['alert_days_before_expire']) ? intval($row['alert_days_before_expire']) : 30;

                                if (!empty($row['end_date']) && $row['end_date'] != '0000-00-00') {
                                    $expire_date = new DateTime($row['end_date']);
                                    $expire_date->setTime(0, 0, 0);
                                    if ($today <= $expire_date) {
                                        $diff_c = $today->diff($expire_date)->days;
                                        if ($diff_c <= $alert_contract) {
                                            $is_row_expiring = true;
                                            $contract_countdown_html = '<span class="badge badge-danger expiring-badge" style="font-size: 0.85rem; min-width: 60px;">' . $diff_c . ' วัน</span>';
                                        } else {
                                            $contract_countdown_html = '<span style="color:#059669; font-weight:700; font-size: 0.9rem;">' . $diff_c . ' วัน</span>';
                                        }
                                    } else {
                                        $is_row_expiring = true;
                                        $contract_countdown_html = '<span class="badge badge-danger" style="font-size: 0.75rem;">หมดสัญญา</span>';
                                    }
                                }

                                // 2. จัดการข้อมูลประกัน (ระยะประกัน)
                                $warranty_countdown_html = "-";
                                $is_warranty_warning = false;
                                $alert_warranty = isset($row['alert_warranty_days']) ? intval($row['alert_warranty_days']) : 30;

                                if (!empty($row['guarantee_end_date']) && $row['guarantee_end_date'] != '0000-00-00') {
                                    $g_end_dt = new DateTime($row['guarantee_end_date']);
                                    $g_end_dt->setTime(0, 0, 0);
                                    if ($today <= $g_end_dt) {
                                        $diff_w = $today->diff($g_end_dt)->days;
                                        if ($diff_w <= $alert_warranty) {
                                            $is_warranty_warning = true;
                                            $warranty_countdown_html = '<span class="badge badge-warning expiring-badge" style="background:#f97316; color:#fff; border:none; font-size: 0.85rem; min-width: 60px;">' . $diff_w . ' วัน</span>';
                                        } else {
                                            $warranty_countdown_html = '<span style="color:#2563eb; font-weight:700; font-size: 0.9rem;">' . $diff_w . ' วัน</span>';
                                        }
                                    } else {
                                        $warranty_countdown_html = '<span class="badge" style="font-size: 0.75rem; background:#94a3b8; color:#fff;">หมดประกัน</span>';
                                    }
                                }

                                // จัดการข้อมูลวันที่เริ่ม-สิ้นสุดค้ำ เพื่อแสดงผลในตาราง
                                $g_start_show = (!empty($row['guarantee_start_date']) && $row['guarantee_start_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['guarantee_start_date'])) : '-';
                                $g_end_show = (!empty($row['guarantee_end_date']) && $row['guarantee_end_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['guarantee_end_date'])) : '-';
                                ?>

                                <tr class="<?= $is_row_expiring ? 'expiring-row' : '' ?> <?= $is_warranty_warning ? 'expiring-warranty-row' : '' ?>"
                                    data-company-id="<?= htmlspecialchars($row['company_id']) ?>"
                                    data-start-date="<?= htmlspecialchars($row['start_date']) ?>"
                                    data-end-date="<?= htmlspecialchars($row['end_date']) ?>">

                                    <td>
                                        <div class="text-bold"><?= htmlspecialchars($row['id']) ?></div>
                                    </td>

                                    <td>
                                        <div class="text-bold" style="font-size:0.85rem;"><i class="fas fa-building"
                                                style="color:#94a3b8; margin-right:5px;"></i><?= htmlspecialchars($row['company_name']) ?>
                                        </div>
                                    </td>

                                    <td style="text-align: center;">
                                        <div style="font-size: 0.85rem; color: #475569;"><?= $end_date_show ?></div>
                                    </td>

                                    <td style="text-align: center;"><?= $contract_countdown_html ?></td>

                                    <td style="text-align: center;"><?= $warranty_countdown_html ?></td>

                                    <td style="text-align: center;">
                                        <div style="font-size: 0.85rem; color: #64748b;"><?= $g_start_show ?></div>
                                    </td>

                                    <td style="text-align: center;">
                                        <div style="font-size: 0.85rem; color: #475569; font-weight:500;"><?= $g_end_show ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="text-bold"
                                            style="color: #4f46e5; font-size:0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;"
                                            title="<?= htmlspecialchars($row['customer_name']) ?>">
                                            <?= htmlspecialchars($row['customer_name']) ?>
                                        </div>
                                    </td>

                                    <td><span class="badge"
                                            style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.7rem;"><?= htmlspecialchars($row['job_type_name']) ?></span>
                                    </td>

                                    <td class="status-col" style="text-align: center;"><span class="badge <?= $badge_class ?>"
                                            style="font-size: 0.7rem;"><i class="fas <?= $badge_icon ?>"></i>
                                            <?= htmlspecialchars($row['status']) ?></span></td>

                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 4px;">
                                            <a href="javascript:void(0);" class="btn-action btn-view" title="ดูรายละเอียด"
                                                data-info='<?= htmlspecialchars(json_encode([
                                                    // ข้อมูลโครงการ
                                                    "project_name" => $row['project_name'] ?? '-',
                                                    "contract_no" => $row['contract_no'] ?? '-',
                                                    "project_budget" => number_format($row['project_budget'] ?? 0, 2),
                                                    "start_date" => (!empty($row['start_date']) && $row['start_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['start_date'])) : '-',

                                                    // ข้อมูลลูกค้า
                                                    "customer_affiliation" => $row['customer_affiliation'] ?? '-',
                                                    "customer_phone" => $row['customer_phone'] ?? '-',
                                                    "customer_address" => $row['customer_address'] ?? '-',
                                                    "contact_person" => $row['contact_person'] ?? '-',
                                                    "contact_phone" => $row['contact_phone'] ?? '-',

                                                    // ระยะประกันและการแจ้งเตือน (✅ ส่วนที่มักจะว่าง)
                                                    "warranty" => ($row['warranty_value'] > 0) ? $row['warranty_value'] . ' ' . ($row['warranty_unit'] == 'years' ? 'ปี' : 'วัน') : '-',
                                                    "alert_warranty_days" => ($row['alert_warranty_days'] ?? '30') . " วัน",
                                                    "alert_contract_days" => ($row['alert_days_before_expire'] ?? '30') . " วัน",
                                                    "alert_days" => ($row['alert_days_before_expire'] ?? '30') . " วัน",

                                                    // การยื่นซอง
                                                    "bidding_type" => $row['bidding_type'] ?? '-',
                                                    "bidding_date" => (!empty($row['bidding_date']) && $row['bidding_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['bidding_date'])) : '-',
                                                    "quotation_no" => $row['quotation_no'] ?? '-',
                                                    "quote_creator" => $row['quote_creator'] ?? '-',

                                                    // ค้ำประกัน (✅ ส่วนที่มักจะว่าง)
                                                    "guarantee_type" => $row['guarantee_type'] ?? 'ไม่มี',
                                                    "guarantee_no" => $row['guarantee_no'] ?: '-',
                                                    "guarantee_amount" => number_format($row['guarantee_amount'] ?? 0, 2),
                                                    "guarantee_percent" => ($row['project_budget'] > 0) ? number_format((($row['guarantee_amount'] ?? 0) / $row['project_budget']) * 100, 2) : '0',
                                                    "guarantee_start" => (!empty($row['guarantee_start_date']) && $row['guarantee_start_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['guarantee_start_date'])) : '-',
                                                    "guarantee_end" => (!empty($row['guarantee_end_date']) && $row['guarantee_end_date'] != '0000-00-00') ? date('d/m/Y', strtotime($row['guarantee_end_date'])) : '-',

                                                    // ประวัติการบันทึก (✅ ส่วนที่มักจะว่าง)
                                                    "sales_user" => $row['sales_user'] ?: '-',
                                                    "recorder" => $row['recorder'] ?? '-',
                                                    "created_at" => !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-'
                                                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                                                onclick="viewDetails(this)">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="create_project.php?edit_id=<?= $row['id'] ?>" class="btn-action btn-edit"
                                                title="แก้ไข"><i class="fas fa-edit"></i></a>
                                            <a href="project_details.php?id=<?= $row['id'] ?>" class="btn-action btn-list"
                                                title="จัดการรายการสินค้า">
                                                <i class="fas fa-list-ul"></i>
                                            </a>
                                            <a href="create_item_entry.php?project_id=<?= $row['id'] ?>"
                                                class="btn-action btn-add-item" title="เพิ่มรายการสินค้า / จัดซื้อ">
                                                <i class="fas fa-cart-plus"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    ยังไม่มีข้อมูลโครงการในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Popup for details -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-content-box">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-info-circle"></i> รายละเอียดข้อมูลเพิ่มเติม</h3>
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-file-signature"></i> ชื่องบ / โครงการ</div>
                    <div class="detail-value" id="modal-project-name"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-file-invoice"></i> เลขที่สัญญา</div>
                    <div class="detail-value" id="modal-contract-no"></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-sitemap"></i> สังกัด</div>
                    <div class="detail-value" id="modal-customer-affiliation">-</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-phone-alt"></i> เบอร์หน่วยงาน (Office)</div>
                    <div class="detail-value" id="modal-customer-phone">-</div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-user-tie text-success"></i> ชื่อผู้ติดต่อ</div>
                    <div class="detail-value" id="modal-contact-person">-</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-mobile-alt text-success"></i> เบอร์มือถือผู้ติดต่อ</div>
                    <div class="detail-value" id="modal-contact-phone">-</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-map-marker-alt"></i> ที่อยู่</div>
                    <div class="detail-value" id="modal-customer-address">-</div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-money-bill-wave"></i> มูลค่าโครงการ</div>
                    <div class="detail-value currency" id="modal-project-budget"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="far fa-calendar-plus"></i> วันที่เริ่มสัญญา</div>
                    <div class="detail-value" id="modal-start-date"></div>
                </div>
                <!-- ถอด วันที่สิ้นสุดสัญญา ออกเพราะแสดงในหน้าตารางหลักแล้ว -->

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-shield-alt"></i> ระยะเวลารับประกัน</div>
                    <div class="detail-value" id="modal-warranty"></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-exclamation-circle text-blue"></i> แจ้งเตือนหมดประกัน
                    </div>
                    <div class="detail-value" id="modal-alert-warranty-days"></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-bell text-red"></i> แจ้งเตือนหมดสัญญา</div>
                    <div class="detail-value" id="modal-alert-contract-days"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-bell"></i> แจ้งเตือนล่วงหน้า</div>
                    <div class="detail-value" id="modal-alert-days"></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-envelope-open-text"></i> ประเภทการยื่นซอง</div>
                    <div class="detail-value" id="modal-bidding-type"></div>
                </div>
                <div class="detail-row" id="bidding-date-row">
                    <div class="detail-label"><i class="far fa-calendar-alt"></i> วันที่ยื่นซอง</div>
                    <div class="detail-value" id="modal-bidding-date"></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-file-alt"></i> เลขที่ใบเสนอราคา</div>
                    <div class="detail-value" id="modal-quotation-no"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-user-edit"></i> ผู้เปิดใบเสนอราคา</div>
                    <div class="detail-value" id="modal-quote-creator"></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-file-contract"></i> ประเภทการค้ำประกัน</div>
                    <div class="detail-value" id="modal-guarantee-type"></div>
                </div>
                <div class="detail-row" id="guarantee-no-row">
                    <div class="detail-label"><i class="fas fa-hashtag"></i> เลขที่หนังสือค้ำ</div>
                    <div class="detail-value" id="modal-guarantee-no"></div>
                </div>
                <div class="detail-row" id="guarantee-amount-row">
                    <div class="detail-label"><i class="fas fa-coins"></i> จำนวนเงินค้ำประกัน</div>
                    <div class="detail-value currency" id="modal-guarantee-amount"></div>
                </div>
                <div class="detail-row" id="guarantee-start-row">
                    <div class="detail-label"><i class="far fa-calendar-plus"></i> วันออกค้ำประกัน</div>
                    <div class="detail-value" id="modal-guarantee-start"></div>
                </div>
                <div class="detail-row" id="guarantee-end-row">
                    <div class="detail-label"><i class="far fa-calendar-check"></i> วันสิ้นสุดค้ำประกัน</div>
                    <div class="detail-value" id="modal-guarantee-end"></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-user-circle"></i> เซลล์ที่รับผิดชอบ</div>
                    <div class="detail-value" id="modal-sales-user"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-pencil-alt"></i> ผู้ลงข้อมูล</div>
                    <div class="detail-value" id="modal-recorder"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="far fa-clock"></i> วันเวลาที่ลงข้อมูล</div>
                    <div class="detail-value" id="modal-created-at"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ข้อมูล KPI สำหรับใช้งานใน JS
        const kpiAllValues = <?= json_encode($kpi_all) ?>;
        const kpiByCompany = <?= $kpi_by_company_json ?>;

        // ฟังก์ชันช่วยจัดรูปแบบตัวเลข (1,000.00)
        function formatNumber(num) {
            return new Intl.NumberFormat('en-US').format(num);
        }

        // ตัวแปรเก็บสถานะการกรองปัจจุบัน
        let currentFilterStatus = 'all';
        let currentCompanyFilter = 'all';

        // ฟังก์ชันเมื่อคลิกที่ชื่อบริษัท หรือ การ์ดย่อย 
        function filterByCompAndStat(companyId, status) {
            event.stopPropagation(); // กันไม่ให้ click bubble ขึ้นไปห้ามคลิกซ้ำซ้อน

            currentCompanyFilter = companyId;
            currentFilterStatus = status;

            // อัพเดท CSS Classes สำหรับหน้าจอ Company Group กรอบใหญ่
            let groups = document.querySelectorAll('.company-group');
            groups.forEach(g => g.classList.remove('active'));
            let activeGroup = document.getElementById('compCard_' + companyId);
            if (activeGroup) activeGroup.classList.add('active');

            // ลบขอบ Active ออกจากการ์ดย่อย (mini-card) ทั้งหมด
            let miniCards = document.querySelectorAll('.mini-card-status');
            miniCards.forEach(c => c.classList.remove('active'));

            // จัดการเพิ่มวงกลมสี Active ให้กับการ์ดย่อยที่ถูกเลือก ของบริษัทที่ถูกเลือก
            if (activeGroup) {
                let cardsInside = activeGroup.querySelectorAll('.mini-card-status');
                cardsInside.forEach(c => {
                    if (c.getAttribute('data-status') === status) {
                        c.classList.add('active');
                    }
                });
            }

            // สั่งกรองตารางด้วย
            applyFilters();
        }

        // ฟังก์ชันสำหรับการค้นหา (ช่อง Search)
        function filterTable() {
            applyFilters();
        }

        // ฟังก์ชัน Toggle ย่อ/ขยาย Company Card
        function toggleCompanyCard(event, companyId) {
            event.stopPropagation(); // กันไม่ให้ไปทริกเกอร์ filterByCompAndStat ของพื้นที่คลิก
            let card = document.getElementById('compCard_' + companyId);
            if (card) {
                card.classList.toggle('collapsed');
            }
        }

        // รวมการกรองทั้งแบบ Search Text, แท็บ Company และแบบ KPI Status เข้าด้วยกัน
        function applyFilters() {
            let input = document.getElementById("searchInput");
            let searchText = input.value.toLowerCase();
            let table = document.getElementById("projectTable");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) { // ข้าม thead
                let row = tr[i];
                // ข้ามแถวที่แสดงกรณีไม่พบข้อมูล
                if (row.getElementsByTagName("td").length === 1) continue;

                // 0. ตรวจสอบ Company ID (จาก Data-Attribute)
                let matchCompany = false;
                if (currentCompanyFilter === 'all') {
                    matchCompany = true;
                } else {
                    let rowCompanyId = row.getAttribute('data-company-id');
                    if (rowCompanyId == currentCompanyFilter) {
                        matchCompany = true;
                    }
                }

                // 1. ตรวจสอบเงื่อนไขข้อความค้นหา (Search input)
                let matchSearch = false;
                let tdId = row.getElementsByTagName("td")[0];
                let tdComp = row.getElementsByTagName("td")[1];
                let tdCust = row.getElementsByTagName("td")[4];

                if (tdId || tdComp || tdCust) {
                    let txtId = tdId.textContent || tdId.innerText;
                    let txtComp = tdComp.textContent || tdComp.innerText;
                    let txtCust = tdCust.textContent || tdCust.innerText;

                    if (txtId.toLowerCase().indexOf(searchText) > -1 ||
                        txtComp.toLowerCase().indexOf(searchText) > -1 ||
                        txtCust.toLowerCase().indexOf(searchText) > -1) {
                        matchSearch = true;
                    }
                }

                // 2. ตรวจสอบเงื่อนไขสถานะ (KPI Card)
                let matchStatus = false;
                if (currentFilterStatus === 'all') {
                    matchStatus = true;
                } else if (currentFilterStatus === 'expiring') {
                    if (row.classList.contains('expiring-row')) {
                        matchStatus = true;
                    }
                } else if (currentFilterStatus === 'expiring_warranty') {
                    if (row.classList.contains('expiring-warranty-row')) {
                        matchStatus = true;
                    }
                } else {
                    // ค้นหาข้อความสถานะในคอลัมน์สถานะ (คอลัมน์ที่ 6 index 6)
                    let tdStatus = row.querySelector('.status-col');
                    if (tdStatus) {
                        let txtStatus = tdStatus.textContent || tdStatus.innerText;
                        if (txtStatus.trim() === currentFilterStatus) {
                            matchStatus = true;
                        }
                    }
                }

                // 3. ตรวจสอบเงื่อนไขช่วงวันที่ (Date Range)
                let matchDate = true;
                let filterStart = document.getElementById("startDateFilter").value;
                let filterEnd = document.getElementById("endDateFilter").value;
                let rowStart = row.getAttribute('data-start-date');
                let rowEnd = row.getAttribute('data-end-date');

                if (filterStart) {
                    if (!rowStart || rowStart < filterStart) {
                        matchDate = false;
                    }
                }
                if (filterEnd) {
                    if (!rowStart || rowStart > filterEnd) {
                        // ถ้าจะเช็คว่า start_date ต้องไม่เกิน endDate หรือ end_date ต้องไม่เกิน endDate สามารถปรับโลจิกได้
                        // ค่าเริ่มต้นจะถือว่าเอา start_date มาเช็คว่าอยู่ในช่วง filterStart ถึง filterEnd หรือไม่
                        matchDate = false;
                    }
                }

                // แสดงแถวก็ต่อเมื่อตรงกับทุกเงื่อนไข
                if (matchCompany && matchSearch && matchStatus && matchDate) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }
        }

        // Modal functions
        function viewDetails(element) {
            let data = JSON.parse(element.getAttribute('data-info'));

            // 1. ข้อมูลพื้นฐาน
            document.getElementById('modal-project-name').innerText = data.project_name;
            document.getElementById('modal-contract-no').innerText = data.contract_no;
            document.getElementById('modal-project-budget').innerText = '฿' + data.project_budget;
            document.getElementById('modal-start-date').innerText = data.start_date;

            // 2. ข้อมูลลูกค้า
            document.getElementById('modal-customer-affiliation').innerText = data.customer_affiliation;
            document.getElementById('modal-contact-person').innerText = data.contact_person;
            document.getElementById('modal-contact-phone').innerText = data.contact_phone;
            document.getElementById('modal-customer-phone').innerText = data.customer_phone;
            document.getElementById('modal-customer-address').innerText = data.customer_address;

            // 3. ประกันและการแจ้งเตือน
            document.getElementById('modal-warranty').innerText = data.warranty;
            document.getElementById('modal-alert-warranty-days').innerText = data.alert_warranty_days;
            document.getElementById('modal-alert-contract-days').innerText = data.alert_contract_days;
            document.getElementById('modal-alert-days').innerText = data.alert_days;

            // 4. การยื่นซอง
            document.getElementById('modal-bidding-type').innerText = data.bidding_type;
            if (data.bidding_type === 'ยื่น') {
                document.getElementById('bidding-date-row').style.display = 'flex';
                document.getElementById('modal-bidding-date').innerText = data.bidding_date;
            } else {
                document.getElementById('bidding-date-row').style.display = 'none';
            }
            document.getElementById('modal-quotation-no').innerText = data.quotation_no;
            document.getElementById('modal-quote-creator').innerText = data.quote_creator;

            // 5. ค้ำประกัน (จัดการซ่อน/แสดง ตามประเภท)
            document.getElementById('modal-guarantee-type').innerText = data.guarantee_type;
            if (data.guarantee_type === 'หนังสือค้ำ') {
                document.getElementById('guarantee-no-row').style.display = 'flex';
                document.getElementById('guarantee-start-row').style.display = 'flex';
                document.getElementById('guarantee-end-row').style.display = 'flex';
                document.getElementById('guarantee-amount-row').style.display = 'none';

                document.getElementById('modal-guarantee-no').innerText = data.guarantee_no;
                document.getElementById('modal-guarantee-start').innerText = data.guarantee_start;
                document.getElementById('modal-guarantee-end').innerText = data.guarantee_end;
            } else if (data.guarantee_type === 'เงินสด') {
                document.getElementById('guarantee-no-row').style.display = 'none';

                // ✅ 1. แก้เป็น flex เพื่อโชว์บรรทัดวันที่
                document.getElementById('guarantee-start-row').style.display = 'flex';
                document.getElementById('guarantee-end-row').style.display = 'flex';
                document.getElementById('guarantee-amount-row').style.display = 'flex';

                // ✅ 2. ใส่ข้อมูลวันที่ลงไป
                document.getElementById('modal-guarantee-amount').innerText = '฿' + data.guarantee_amount + ' (' + data.guarantee_percent + '%)';
                document.getElementById('modal-guarantee-start').innerText = data.guarantee_start;
                document.getElementById('modal-guarantee-end').innerText = data.guarantee_end;

            } else {
                document.getElementById('guarantee-no-row').style.display = 'none';
                document.getElementById('guarantee-start-row').style.display = 'none';
                document.getElementById('guarantee-end-row').style.display = 'none';
                document.getElementById('guarantee-amount-row').style.display = 'none';
            }

            // 6. ประวัติผู้บันทึก
            document.getElementById('modal-sales-user').innerText = data.sales_user;
            document.getElementById('modal-recorder').innerText = data.recorder;
            document.getElementById('modal-created-at').innerText = data.created_at;

            // เปิด Modal
            let modal = document.getElementById('detailModal');
            modal.style.display = 'flex';
            setTimeout(() => { modal.classList.add('show'); }, 10);
        }

        function closeModal() {
            let modal = document.getElementById('detailModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300); // Wait for transition out
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            let modal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
        }

    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                locale: "th",
                onChange: function (selectedDates, dateStr, instance) {
                    filterTable();
                }
            });
        });
    </script>
</body>

</html>