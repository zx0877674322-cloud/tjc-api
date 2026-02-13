<?php
header('Content-Type: application/json; charset=utf-8');

// เรียกใช้การเชื่อมต่อจากไฟล์ db_connect.php (ในนั้นมีตัวแปร $conn อยู่แล้ว)
require_once 'db_connect.php';

// ตรวจสอบว่าเชื่อมต่อได้จริงไหม ถ้าไม่ได้ให้แจ้ง Error
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// ไม่ต้อง set_charset ซ้ำ เพราะใน db_connect.php น่าจะทำแล้ว 
// แต่ถ้าอยากชัวร์ ใส่ไว้ก็ได้ครับ
$conn->set_charset("utf8");

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. ดึงจังหวัด (ตามภาค)
if ($action == 'get_provinces') {
    $region = isset($_GET['region']) ? $_GET['region'] : '';
    
    // ถ้ามีการส่งภาคมา ให้กรองตามภาค
    if ($region != '') {
        $sql = "SELECT name_th FROM master_provinces WHERE region_name = ? ORDER BY name_th ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // ถ้าไม่ได้ส่งภาคมา ให้ดึงทั้งหมด (เผื่อกรณีดึงจังหวัดทั้งหมด)
        $sql = "SELECT name_th FROM master_provinces ORDER BY name_th ASC";
        $result = $conn->query($sql);
    }

    $data = [];
    while($row = $result->fetch_assoc()) { 
        $data[] = $row['name_th']; 
    }
    echo json_encode($data);
}

// 2. ดึงสถานะงาน (NEW)
else if ($action == 'get_job_status') {
    $sql = "SELECT status_name FROM master_job_status ORDER BY id ASC";
    $result = $conn->query($sql);
    
    $data = [];
    if ($result) {
        while($row = $result->fetch_assoc()) { 
            $data[] = $row['status_name']; 
        }
    }
    echo json_encode($data);
}

// 3. ดึงกิจกรรม (NEW)
else if ($action == 'get_activities') {
    $sql = "SELECT activity_name FROM master_activities ORDER BY id ASC";
    $result = $conn->query($sql);
    
    $data = [];
    if ($result) {
        while($row = $result->fetch_assoc()) { 
            $data[] = $row['activity_name']; 
        }
    }
    echo json_encode($data);
}
// เพิ่มกรณีที่ Action ไม่ถูกต้อง
else {
    echo json_encode(["error" => "Invalid Action or No Action provided"]);
}

$conn->close();
?>