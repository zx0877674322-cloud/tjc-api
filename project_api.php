<?php
require_once 'auth.php';
require_once 'db_connect.php';

$path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';

if (!isset($action)) {
    $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $action = trim($path_info, '/'); // ตัด / ออก เช่น '/get_project' -> 'get_project'
}

// ถ้าไม่มี action อะไรเลย ให้ default เป็น 'get_project' (กรณี include ไฟล์นี้ตรงๆ)
if (empty($action)) {
    $action = 'get_project';
}

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
if (!isset($companys))
    $companys = [];
if ($action === 'get_companys') {
    $res_company = $conn->query("SELECT id , company_name ,logo_file,company_shortname	 FROM companies ORDER BY id ASC");
    if ($res_company && $res_company->num_rows > 0) {
        while ($row = $res_company->fetch_assoc()) {
            $companys[] = [
                'id' => $row['id'],
                'company_name' => $row['company_name'],
                'company_shortname' => $row['company_shortname'],
                'logo_file' => $row['logo_file']
            ];
        }
    }
}


if (isset($_POST['action']) && $_POST['action'] === 'get_site_list_filter') {
    $company_id = isset($_POST['company_id']) ? $_POST['company_id'] : '';
    $work_type_id = isset($_POST['work_type_id']) ? $_POST['work_type_id'] : '';
    if (!empty($company_id) && !empty($work_type_id)) {

        $data = [];
        $sql = "SELECT DISTINCT a.site_id, c.customer_name    
                FROM project_contracts a 
                JOIN project_lists b ON a.site_id = b.site_id  
                JOIN customers c ON a.customer_id = c.customer_id
                WHERE a.company_id  = '$company_id'  
                AND a.work_type_id = '$work_type_id'  
                AND a.project_status NOT IN (4, 5, 6, 7)
                AND b.order_status_main = 1";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'site_id' => $row['site_id'],
                    'customer_name' => $row['customer_name'],
                ];
            }
        }

        echo json_encode($data);
    } else {
        echo json_encode([]);
    }
    exit;

}


// ดึงข้อมูลโครงการโดยมีการกำหนด pagiantion
if ($action === 'get_project') {
    $s_company_id = isset($_GET['company_id']) ? $_GET['company_id'] : '';  // บริษัท
    $s_end_date = isset($_GET['contract_end_date']) ? $_GET['contract_end_date'] : ''; // วันที่สิ้นสุดสัญญา
    $s_customer_name = isset($_GET['customer_name']) ? $_GET['customer_name'] : ''; // ชื่อหน่วยงาน/ชื่อลูกค้า
    $s_affiliation = isset($_GET['affiliation']) ? $_GET['affiliation'] : ''; // สังกัด
    $s_sub_district = isset($_GET['sub_district']) ? $_GET['sub_district'] : ''; // ตำบล
    $s_district = isset($_GET['district']) ? $_GET['district'] : ''; // อำเภอ
    $s_province = isset($_GET['province']) ? $_GET['province'] : ''; // จังหวัด
    $s_project_name = isset($_GET['project_name']) ? $_GET['project_name'] : ''; // ชื่อโครงการ
    $s_project_budget = isset($_GET['project_budget']) ? $_GET['project_budget'] : ''; // งบโครงการ

    // 2. เตรียม WHERE Clause และ Parameters
    $whereSQL = "WHERE 1=1"; // เทคนิคเพื่อให้ต่อ AND ได้เลย
    $params = [];
    $types = "";

    // เงื่อนไข: บริษัท
    if (!empty($s_company_id)) {
        $whereSQL .= " AND a.company_id = ?";
        $params[] = $s_company_id;
        $types .= "i"; // หรือ i ถ้า id เป็น int
    }

    // เงื่อนไข: วันที่สิ้นสุดสัญญา (ตรงตัว)
    if (!empty($s_end_date)) {
        $whereSQL .= " AND a.contract_end_date = ?";
        $params[] = $s_end_date;
        $types .= "s";
    }

    // เงื่อนไข: หน่วยงาน/ลูกค้า
    if (!empty($s_customer_name)) {
        $whereSQL .= " AND b.customer_name = ?";
        $params[] = $s_customer_name;
        $types .= "s";
    }

    // เงื่อนไข: สังกัด
    if (!empty($s_affiliation)) {
        $whereSQL .= " AND b.affiliation = ?";
        $params[] = $s_affiliation;
        $types .= "s";
    }

    // เงื่อนไข: ตำบล
    if (!empty($s_sub_district)) {
        $whereSQL .= " AND b.sub_district = ?";
        $params[] = $s_sub_district;
        $types .= "s";
    }

    // เงื่อนไข: อำเภอ
    if (!empty($s_district)) {
        $whereSQL .= " AND b.district = ?";
        $params[] = $s_district;
        $types .= "s";
    }

    // เงื่อนไข: จังหวัด
    if (!empty($s_province)) {
        $whereSQL .= " AND b.province = ?";
        $params[] = $s_province;
        $types .= "s";
    }

    // เงื่อนไข: ชื่อโครงการ
    if (!empty($s_project_name)) {
        $whereSQL .= " AND a.project_name = ?";
        $params[] = $s_project_name;
        $types .= "s";
    }

    // เงื่อนไข: งบโครงการ
    if (!empty($s_project_budget)) {
        $whereSQL .= " AND a.project_budget = ?";
        $params[] = $s_project_budget;
        $types .= "s";
    }



    // เงื่อนไข: สถานะโครงการ
    // if (!empty($s_project_status)) {
    //     $whereSQL .= " AND a.project_status = ?";
    //     $params[] = $s_project_status;
    //     $types .= "s"; // หรือ i
    // }

    // --- Pagination Logic ---
// กำหนดจำนวนรายการต่อหน้า
    $limit = 5;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1)
        $page = 1;
    $offset = ($page - 1) * $limit;

    // 3. Query นับจำนวนทั้งหมด (ใช้ WHERE เดียวกัน)
    $sql_count = "SELECT COUNT(*) as total FROM project_contracts a
    JOIN customers b ON a.customer_id = b.customer_id $whereSQL";
    $stmt_count = $conn->prepare($sql_count);

    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $res_count = $stmt_count->get_result();
    $total_rows = 0;
    if ($res_count->num_rows > 0) {
        $row_count = $res_count->fetch_assoc();
        $total_rows = $row_count['total'];
    }
    $total_pages = ceil($total_rows / $limit);
    $stmt_count->close();

    // 4. Query ดึงข้อมูลจริง (เพิ่ม LIMIT, OFFSET)
    $sql_data = "SELECT a.* , b.customer_name, b.affiliation, 
        CONCAT(IF(b.address = '-', '', b.address), ' ตำบล', b.sub_district, ' อำเภอ', b.district, ' จังหวัด', b.province, ' ', b.zip_code) AS residence,
        a.project_name, a.project_budget ,c.company_shortname 
        FROM project_contracts a 
        LEFT JOIN customers b ON a.customer_id = b.customer_id  
        LEFT JOIN companies c ON a.company_id = c.id 
        $whereSQL
        ORDER BY a.site_id ASC LIMIT ? OFFSET ?";

    $stmt_data = $conn->prepare($sql_data);

    // Bind Param รวมของเดิม + limit, offset
    $params_data = $params;
    $params_data[] = $limit;
    $params_data[] = $offset;
    $types_data = $types . "ii";

    $stmt_data->bind_param($types_data, ...$params_data);
    $stmt_data->execute();
    $res_projects = $stmt_data->get_result();

    $projects = [];
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
                'project_status' => $row['project_status'],
                'customer_name' => $row['customer_name'],
                'company_shortname' => $row['company_shortname'],
                'affiliation' => $row['affiliation'],
                'residence' => $row['residence'],
                'project_name' => $row['project_name'],
                'project_budget' => $row['project_budget'],
            ];
        }
    }
    $stmt_data->close();

    // สร้าง Query String สำหรับ Pagination (เพื่อให้กดเปลี่ยนหน้าแล้วค่าค้นหายังอยู่)
    $queryString = http_build_query([
        // 'site_id' => $s_site_id,
        // 'contract_number' => $s_contract_number,
        // 'contract_start_date' => $s_start_date,
        // 'project_status' => $s_project_status
        'company_id' => $s_company_id,
        'contract_end_date' => $s_end_date,
        'customer_name' => $s_customer_name,
        'affiliation' => $s_affiliation,
        'sub_district' => $s_sub_district,
        'district' => $s_district,
        'province' => $s_province,
        'project_name' => $s_project_name,
        'project_budget' => $s_project_budget
    ]);
}

if ($action === 'get_po_list') {
    $supplier_id = isset($_POST['supplier_id']) ? $_POST['supplier_id'] : '';
    $po_list = [];

    if (!empty($supplier_id)) {
        // 1. หาชื่อ Supplier จาก ID
        $supplier_name = "";
        $sql_get_name = "SELECT name FROM suppliers WHERE id = ?";
        $stmt_name = $conn->prepare($sql_get_name);
        $stmt_name->bind_param("i", $supplier_id); // ใช้ "i" ถ้า id เป็น int
        $stmt_name->execute();
        $res_name = $stmt_name->get_result();

        if ($res_name->num_rows > 0) {
            $row_sup = $res_name->fetch_assoc();
            $supplier_name = $row_sup['name'];
        }
        $stmt_name->close();

        // 2. ถ้าเจอชื่อ ให้ไปค้นหา PO ใน document_submissions
        if (!empty($supplier_name)) {
            // ใช้ LIKE หรือ = ตามความเหมาะสมของข้อมูล (แนะนำ = ถ้าชื่อตรงกันเป๊ะ)
            $sql_po = "SELECT CONCAT(doc_type, doc_number) AS full_po 
                       FROM document_submissions 
                       WHERE supplier_name = ? 
                       GROUP BY full_po"; // Group by เพื่อไม่ให้ PO ซ้ำ

            $stmt_po = $conn->prepare($sql_po);
            $stmt_po->bind_param("s", $supplier_name);
            $stmt_po->execute();
            $res_po = $stmt_po->get_result();

            if ($res_po->num_rows > 0) {
                while ($row = $res_po->fetch_assoc()) {
                    $po_list[] = [
                        'po_code' => $row['full_po']
                    ];
                }
            }
            $stmt_po->close();
        }
    }

    // ส่งค่า JSON กลับและจบการทำงานทันที
    header('Content-Type: application/json');
    echo json_encode($po_list);
    exit; // สำคัญมาก! ต้องหยุดการทำงานตรงนี้
}


if ($path_info === '/get_units') {
    $units = [];
    $res_units = $conn->query("SELECT * FROM product_units ORDER BY id ASC");
    if ($res_units && $res_units->num_rows > 0) {
        while ($row = $res_units->fetch_assoc()) {
            $units[] = [
                'id' => $row['id'],
                'name_th' => $row['name_th'],
            ];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($units);
    exit;
}

$suppliers = [];
$res_suppliers = $conn->query("SELECT * FROM suppliers ORDER BY id ASC");
if ($res_suppliers && $res_suppliers->num_rows > 0) {
    while ($row = $res_suppliers->fetch_assoc()) {
        $suppliers[] = [
            'id' => $row['id'],
            'name' => $row['name'],
        ];
    }
}



// ดึงข้อมูลหน่วยงาน
if (!isset($customers))
    $customers = [];
if ($action === 'get_customer') {
    $sql = "SELECT customer_id, customer_name FROM customers ORDER BY customer_id ASC";
    $res_customers = $conn->query($sql);

    if ($res_customers && $res_customers->num_rows > 0) {
        while ($row = $res_customers->fetch_assoc()) {
            $customers[] = [
                'customer_id' => $row['customer_id'],
                'customer_name' => $row['customer_name'],
            ];
        }
    }
}

// ดึงข้อมูลหน่วยงาน เพื่อ Filter
if (!isset($customer_name))
    $customer_name = [];
if ($action === 'get_customername') {
    $sql = "SELECT DISTINCT customer_name FROM customers ORDER BY customer_name ASC";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $customer_name[] = $row;
        }
    }
}

// ดึงข้อมูลสังกัด เพื่อ Filter
if (!isset($affiliation))
    $affiliation = [];
if ($action === 'get_affiliation') {
    $sql = "SELECT DISTINCT affiliation FROM customers ORDER BY affiliation ASC";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $affiliation[] = $row;
        }
    }
}

// ดึงข้อมูลตำบล เพื่อ Filter
if (!isset($sub_district))
    $sub_district = [];
if ($action === 'get_sub_district') {
    $sql = "SELECT DISTINCT sub_district FROM customers ORDER BY sub_district ASC";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $sub_district[] = $row;
        }
    }
}

// ดึงข้อมูลอำเภอ เพื่อ Filter
if (!isset($district))
    $district = [];
if ($action === 'get_district') {
    $sql = "SELECT DISTINCT district FROM customers ORDER BY district ASC";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $district[] = $row;
        }
    }
}

// ดึงข้อมูลจังหวัด เพื่อ Filter
if (!isset($province))
    $province = [];
if ($action === 'get_province') {
    $sql = "SELECT DISTINCT province FROM customers ORDER BY province ASC";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $province[] = $row;
        }
    }
}

// ดึงข้อมูลชื่อโครงการ เพื่อ Filter
if (!isset($project_name))
    $project_name = [];
if ($action === 'get_project_name') {
    $sql = "SELECT DISTINCT project_name FROM project_contracts ORDER BY project_name ASC";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $project_name[] = $row;
        }
    }
}

// ดึงข้อมูลงบโครงการ เพื่อ Filter
if (!isset($project_budget))
    $project_budget = [];
if ($action === 'get_project_budget') {
    $sql = "SELECT DISTINCT project_budget FROM project_contracts ORDER BY project_budget ASC";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $project_budget[] = $row;
        }
    }
}

// ดึงข้อมูลประเภทงาน
if (!isset($type))
    $type = [];
if ($action === 'get_work_type') {
    $res_type = $conn->query("SELECT work_type_id , work_type_name ,	is_active FROM project_work_type WHERE is_active = 1 ORDER BY work_type_id ASC");
    if ($res_type && $res_type->num_rows > 0) {
        while ($row = $res_type->fetch_assoc()) {
            $type[] = [
                'work_type_id' => $row['work_type_id'],
                'work_type_name' => $row['work_type_name'],
            ];
        }
    }
}

// ดึง PO
if (!isset($po_number))
    $po_number = [];
if ($action === 'get_po') {
    $sql_po = $conn->query("SELECT CONCAT(doc_type, doc_number) AS full_po 
                       FROM document_submissions GROUP BY full_po"); // Group by เพื่อไม่ให้ PO ซ้ำ
    if ($sql_po && $sql_po->num_rows > 0) {
        while ($row = $sql_po->fetch_assoc()) {
            $po_number[] = [
                'po_code' => $row['full_po'],
            ];
        }
    }
}


// Start Function INSERT
if ($path_info === '/add_lists') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $product_name = trim($_POST['product_name'] ?? '');
        $product_qty = floatval($_POST['product_qty'] ?? 0);
        $product_unit = trim($_POST['product_unit'] ?? '');
        $price_per_unit = floatval($_POST['price_per_unit'] ?? 0);
        $total_price_per = floatval($_POST['total_price_per'] ?? 0);
        $site_id = intval($_POST['site_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);

        try {

            $conn->begin_transaction();

            $sql1 = "INSERT INTO project_lists (list_name,product_qty,product_unit,price_per_unit,total_price_per,order_status_main,site_id,create_id) VALUES (?,?,?,?,?,0,?,?)";
            $stmt1 = $conn->prepare($sql1);
            if (!$stmt1)
                throw new Exception("Prepare Query 1 Failed: " . $conn->error);

            $stmt1->bind_param('sdsddii', $product_name, $product_qty, $product_unit, $price_per_unit, $total_price_per, $site_id, $user_id);

            if (!$stmt1->execute()) {
                throw new Exception("Execute Query 1 Failed: " . $stmt1->error);
            }

            $last_id = $conn->insert_id;

            $stmt1->close();

            $conn->commit();

            echo json_encode(["status" => "success", "message" => "บันทึกข้อมูลเรียบร้อย", "list_id" => $last_id]);

        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollback();
            }

            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        } finally {
            // ปิด Connection
            if (isset($conn)) {
                $conn->close();
            }
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid Request Method"]);
    }

    exit;
}

if ($path_info === '/import_lists') {

    $response = [
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ'
    ];

    try {

        if (!isset($_FILES['excelFile'])) {
            throw new Exception("ไม่พบข้อมูลที่อัปโหลด");
        }

        $site_id = intval($_POST['site_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);

        if ($site_id <= 0) {
            throw new Exception("Site ID ไม่ถูกต้อง");
        }

        $fileName = $_FILES['excelFile']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExt !== 'csv') {
            throw new Exception("กรุณาอัปโหลดไฟล์ .csv เท่านั้น (คุณอัปโหลด .$fileExt)");
        }

        $fileTmp = $_FILES['excelFile']['tmp_name'];

        $fileContent = file_get_contents($fileTmp);

        $hasBOM = (substr($fileContent, 0, 3) === "\xEF\xBB\xBF");

        if ($hasBOM) {
            $fileContent = substr($fileContent, 3);
        } else {
            if (!mb_check_encoding($fileContent, 'UTF-8')) {
                $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'Windows-874');
            }
        }

        file_put_contents($fileTmp, $fileContent);


        if (($handle = fopen($fileTmp, "r")) === FALSE) {
            throw new Exception("ไม่สามารถเปิดไฟล์ได้");
        }

        $bom = fread($handle, 3);
        if ($bom != "\xEF\xBB\xBF") {
            rewind($handle);
        }

        fgetcsv($handle);

        $conn->begin_transaction();

        $sql1 = "INSERT INTO project_lists (list_name,product_qty,product_unit,price_per_unit,total_price_per,site_id,create_id) VALUES (?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql1);

        if (!$stmt) {
            throw new Exception("Prepare Failed: " . $conn->error);
        }

        $last_id = $conn->insert_id;

        $p_name = "";
        $p_qty = 0;
        $p_unit = "";
        $p_price = 0;
        $p_total = 0;

        $stmt->bind_param('sdsddii', $p_name, $p_qty, $p_unit, $p_price, $p_total, $site_id, $user_id);

        $imported_count = 0;
        $last_inserted_id = 0;

        // เริ่มวนลูปอ่านข้อมูลทีละบรรทัด
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // เช็คว่าคอลัมน์แรก (ชื่อสินค้า) ไม่ว่าง
            if (isset($data[0]) && trim($data[0]) !== '') {

                // 3. ดึงค่าจาก CSV เข้าตัวแปร (อ้างอิงตามรูป Excel: A=0, B=1, C=2, D=3, E=4)
                $p_name = trim($data[0]);

                // ลบ comma ออกก่อนแปลงเป็นตัวเลข (เผื่อไฟล์มี format 1,000.00)
                $p_qty = floatval(str_replace(',', '', $data[1] ?? 0));
                $p_unit = trim($data[2] ?? '');
                $p_price = floatval(str_replace(',', '', $data[3] ?? 0));
                $p_total = floatval(str_replace(',', '', $data[4] ?? 0));

                // ถ้าใน Excel ไม่ได้สูตรคำนวณยอดรวมมา เราคำนวณเองได้เพื่อความชัวร์
                if ($p_total == 0) {
                    $p_total = $p_qty * $p_price;
                }

                if (!$stmt->execute()) {
                    throw new Exception("Error saving: " . $p_name . " (" . $stmt->error . ")");
                }

                $last_inserted_id = $conn->insert_id; // เก็บ ID ล่าสุด
                $imported_count++;
            }
        }

        $conn->commit();
        $stmt->close();
        fclose($handle);

        $response = [
            'status' => 'success',
            'message' => "บันทึกข้อมูลเรียบร้อย จำนวน $imported_count รายการ",
            "list_id" => $last_id
        ];

    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_errno == 0) {
            $conn->rollback();
        }
        $response = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ส่งข้อมูลให้จัดซื้อ
if ($path_info === '/confrim_list') {
    $items = isset($_POST['items']) ? $_POST['items'] : ($input['items'] ?? []);
    $userId = isset($_POST['userId']) ? (int) $_POST['userId'] : ($input['userId'] ?? 0);
    $siteId = (isset($_POST['siteId']) && $_POST['siteId'] !== '') ? (int) $_POST['siteId'] : ($input['siteId'] ?? null);

    if ($siteId === null || empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน หรือ Site ID ว่าง']);
        exit;
    }

    try {
        $conn->begin_transaction();

        // 1. อัปเดตสถานะโครงการ
        $sql_project = "UPDATE `project_contracts` SET `project_status` = ? WHERE `site_id` = ?";
        $stmt1 = $conn->prepare($sql_project);
        $project_status_id = 3;
        $stmt1->bind_param("ii", $project_status_id, $siteId);
        if (!$stmt1->execute())
            throw new Exception("Update Project Status Failed: " . $stmt1->error);
        $stmt1->close(); // ปิด statement เมื่อใช้เสร็จ

        // 2. วนลูปอัปเดตรายการสินค้า
        if (is_array($items) && count($items) > 0) {
            $sql_list = "UPDATE `project_lists` SET `order_status_main` = ? WHERE `list_id` = ?";
            $stmt2 = $conn->prepare($sql_list);
            $item_order_status = 1; // สถานะของสินค้า

            foreach ($items as $id) {
                $item_id = (int) $id;
                $stmt2->bind_param("ii", $item_order_status, $item_id);
                if (!$stmt2->execute())
                    throw new Exception("Update List ID $item_id Failed: " . $stmt2->error);
            }
            $stmt2->close();
        }

        // 3. บันทึก Log หลัก
        $sql_log = "INSERT INTO `project_logs` (`site_id`, `action`, `status`, `user_id`) VALUES (?, 'Update Status', ?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("iii", $siteId, $project_status_id, $userId);
        if (!$stmt_log->execute())
            throw new Exception("Log failed: " . $stmt_log->error);
        $stmt_log->close();

        // 4. บันทึก Sub Log
        // ** แก้ไข: ตรวจสอบว่าต้องการเก็บค่า status ไหน (อันนี้ผมแก้ให้เก็บค่า item_order_status ตาม Context ของการ Confirm List)
        $sql_sub_log = "INSERT INTO `project_sub_logs` (`site_id`, `action`, `order_status`, `user_id`) VALUES (?, 'Confirm_list', ?, ?)";
        $stmt_sub_log = $conn->prepare($sql_sub_log);
        // ตรงนี้ถ้าต้องการเก็บค่า 3 ให้แก้ $item_order_status เป็น $project_status_id
        $stmt_sub_log->bind_param("iii", $siteId, $item_order_status, $userId);
        if (!$stmt_sub_log->execute())
            throw new Exception("Sub Log failed: " . $stmt_sub_log->error);
        $stmt_sub_log->close();

        $conn->commit();

        // ** แก้ไขจุดสำคัญ: ต้อง Echo ผลลัพธ์กลับไป **
        echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลสำเร็จ']);

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }

        // ส่ง HTTP 500 หรือ 200 ตาม Logic หน้าบ้าน (ปกติใช้ 200 แล้วเช็ค status: error ก็ได้)
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);

    } finally {
        // ปิด Connection
        if (isset($conn)) {
            $conn->close();
        }
    }
    exit;
}


// ยกเลิกโครงการ
if ($path_info === '/cc_project') {

    $site_id = intval($_POST['site_id'] ?? 0);

    if ($site_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Site ID ไม่ถูกต้อง"]);
        exit;
    }
    $status = 7; // สถานะยกเลิก
    $user_id = intval($_POST['user_id'] ?? 0);
    $remark = trim($_POST['remark'] ?? '');

    try {
        $conn->begin_transaction();

        $sql = "UPDATE project_contracts SET project_status = ?, remark = ? WHERE site_id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Prepare SQL 1 Failed: " . $conn->error);
        }
        $stmt->bind_param("isi", $status, $remark, $site_id);

        if (!$stmt->execute()) {
            throw new Exception("Update Execute Failed: " . $stmt->error);
        }

        $stmt->close();

        $sql_log = "INSERT INTO `project_logs` (`site_id`, `action`, `status`, `user_id`) VALUES (?, 'Cancel', ?, ?)";
        $stmt_log = $conn->prepare($sql_log);

        if (!$stmt_log) {
            throw new Exception("Prepare Log SQL Failed: " . $conn->error);
        }

        $stmt_log->bind_param("iii", $site_id, $status, $user_id);

        if (!$stmt_log->execute()) {
            throw new Exception("Log Execute Failed: " . $stmt_log->error);
        }

        $stmt_log->close();

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "ยกเลิกโครงการเรียบร้อย"]);

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

    if (isset($conn)) {
        $conn->close();
    }

    exit;
}

if ($path_info === '/insert_capital') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $list_id = $_POST['list_id'] ?? 0;
        $site_id = $_POST['site_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? 0;
        $status = $_POST['project_status'] ?? '';
    }
}


?>