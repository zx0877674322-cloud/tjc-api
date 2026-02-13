<?php
// save_report.php

// 1. ตั้งค่า Header
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// ปิดการแสดง Error หน้าเว็บ (เพื่อไม่ให้ JSON พัง) แต่ให้ Log เก็บไว้แทน
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db_connect.php';

// เตรียมตัวแปรสำหรับตอบกลับ
$response = array();

// 2. เปลี่ยนจากการรับ JSON มาเช็คค่า POST แทน
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับค่าจากฟอร์ม
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $area = isset($_POST['area']) ? $_POST['area'] : '';
    $gps = isset($_POST['gps']) ? $_POST['gps'] : '';
    $jobSource = isset($_POST['jobSource']) ? $_POST['jobSource'] : '';
    
    // แปลงตัวเลข (ถ้าว่างให้เป็น 0)
    $jobValue = isset($_POST['jobValue']) ? floatval($_POST['jobValue']) : 0;
    $expense = isset($_POST['expense']) ? floatval($_POST['expense']) : 0;
    
    $problem = isset($_POST['problem']) ? $_POST['problem'] : '-';
    $suggestion = isset($_POST['suggestion']) ? $_POST['suggestion'] : '-';

    // ---------------------------------------------------------
    // 3. ส่วนจัดการอัปโหลดรูปภาพ (เพิ่มเติมให้)
    // ---------------------------------------------------------
    $image_path = ""; // ตัวแปรเก็บชื่อไฟล์รูป
    
    // เช็คว่ามีการส่งไฟล์ชื่อ 'receipt' มาไหม (ต้องดูใน HTML ว่าตั้งชื่อ name="อะไร")
    // สมมติว่าใน HTML ตั้งชื่อ input file ว่า name="receipt" หรือ name="image"
    // ถ้าคุณใช้ชื่ออื่น ให้แก้คำว่า 'receipt' ด้านล่างนี้ครับ
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        
        // ถ้ายังไม่มีโฟลเดอร์ uploads ให้สร้างใหม่
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // ตั้งชื่อไฟล์ใหม่ป้องกันชื่อซ้ำ (เช่น receipt_20251214_xxx.jpg)
        $file_extension = pathinfo($_FILES["receipt"]["name"], PATHINFO_EXTENSION);
        $new_filename = "receipt_" . date("YmdHis") . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        // ย้ายไฟล์ไปเก็บ
        if (move_uploaded_file($_FILES["receipt"]["tmp_name"], $target_file)) {
            $image_path = $new_filename; // เก็บแค่ชื่อไฟล์ลงฐานข้อมูล
        }
    }
    // ---------------------------------------------------------

    // 4. บันทึกลงฐานข้อมูล
    // หมายเหตุ: โค้ดเดิมของคุณไม่มีช่องเก็บรูป ผมเลยยังไม่ได้ใส่ column รูปภาพใน SQL นี้นะครับ
    // ถ้าต้องการเก็บรูป ต้องไปเพิ่ม column 'receipt_image' ใน Database ก่อน แล้วค่อยมาแก้ SQL ตรงนี้
    
    $sql = "INSERT INTO reports (report_date, reporter_name, area, gps, job_source, job_value, expense, problem, suggestion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        // s = string, d = double
        $stmt->bind_param("sssssddss", $date, $name, $area, $gps, $jobSource, $jobValue, $expense, $problem, $suggestion);

        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'บันทึกข้อมูลสำเร็จ!';
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Database Error: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Prepare Statement Error: ' . $conn->error;
    }

} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid Request Method';
}

$conn->close();

// ส่งค่ากลับเป็น JSON เสมอ
echo json_encode($response);
?>