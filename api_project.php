<?php
include 'db_connect.php';

// ดึงข้อมูลพนักงาน ตำแหน่งเซลล์
$salse = [];
$res_salse = $conn->query("SELECT id , fullname ,role FROM users WHERE role = 'staff'  ORDER BY id ASC");
if ($res_salse && $res_salse->num_rows > 0) {
    while ($row = $res_salse->fetch_assoc()) {
        $salse[] = [
            'id' => $row['id'],
            'fullname' => $row['fullname'],
            'role' => $row['role']
        ];
    }
}

// ดึงข้อมูลพนักงาน ตำแหน่งจัดซื้อ
$purchase = [];
$res_purchase = $conn->query("SELECT id , fullname ,role FROM users WHERE role = 'Purchase' ORDER BY id ASC");
if ($res_purchase && $res_purchase->num_rows > 0) {
    while ($row = $res_purchase->fetch_assoc()) {
        $purchase[] = [
            'id' => $row['id'],
            'fullname' => $row['fullname'],
            'role' => $row['role']
        ];
    }
}

// ดึงข้อมูลบริษัท
$companys = [];
$res_company = $conn->query("SELECT id , company_name ,logo_file FROM companies ORDER BY id ASC");
if ($res_company && $res_company->num_rows > 0) {
    while ($row = $res_company->fetch_assoc()) {
        $companys[] = [
            'id' => $row['id'],
            'company_name' => $row['company_name'],
            'logo_file' => $row['logo_file']
        ];
    }
}


// ดึงข้อมูลโครงการ
// 1. กำหนดจำนวนรายการต่อหน้า
$limit = 10;

// 2. รับค่าหน้าปัจจุบัน (ถ้าไม่มีให้เป็นหน้า 1)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

// 3. คำนวณตำแหน่งเริ่มต้น (Offset)
$offset = ($page - 1) * $limit;

// 4. นับจำนวนข้อมูลทั้งหมด (เพื่อหาว่ามีกี่หน้า)
$sql_count = "SELECT COUNT(*) as total FROM project_contracts";
$res_count = $conn->query($sql_count);
$total_rows = 0;
if ($res_count && $res_count->num_rows > 0) {
    $row_count = $res_count->fetch_assoc();
    $total_rows = $row_count['total'];
}

// 5. คำนวณจำนวนหน้าทั้งหมด
$total_pages = ceil($total_rows / $limit);

$projects = [];
$res_projects = $conn->query("SELECT a.* , c.customer_name ,d.company_shortname
FROM project_contracts a 
LEFT JOIN customers c ON a.customer_id = c.customer_id  
LEFT JOIN companies d ON a.company_id = d.id 
ORDER BY a.site_id ASC LIMIT $limit OFFSET $offset");
if ($res_projects && $res_projects->num_rows > 0) {
    while ($row = $res_projects->fetch_assoc()) {

        $projects[] = [
            'site_id' => $row['site_id'],
            'company_id' => $row['company_id'],
            'contract_number' => $row['contract_number'],
            'contract_start_date' => $row['contract_start_date'],
            'contract_end_date' => $row['contract_end_date'],
            'is_submission_required' => $row['is_submission_required'],
            'submission_date' => $row['submission_date'],
            'customer_id' => $row['customer_id'],
            'project_status' => $row['project_status'],
            'customer_name' => $row['customer_name'],
            'company_shortname' => $row['company_shortname'],
        ];

    }
}


?>