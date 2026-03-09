<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. รับค่าจากฟอร์ม
        $customer_name = $_POST['customer_name'] ?? '';
        $affiliation = $_POST['affiliation'] ?? '';
        $address = $_POST['address'] ?? '';
        $sub_district = $_POST['district'] ?? ''; // ในฟอร์มใช้ name="district" คือตำบล
        $district = $_POST['amphoe'] ?? '';   // ในฟอร์มใช้ name="amphoe" คืออำเภอ
        $province = $_POST['province'] ?? '';
        $zip_code = $_POST['zipcode'] ?? '';

        // เบอร์โทรศัพท์หน่วยงาน (Office)
        $phone_number = $_POST['phone_number'] ?? '';

        // ✅ [ใหม่] ผู้ติดต่อและเบอร์มือถือ
        $contact_person = $_POST['contact_person'] ?? '';
        $contact_phone = $_POST['contact_phone'] ?? '';

        $remark = $_POST['remark'] ?? '';

        // ตรวจสอบข้อมูลจำเป็น
        if (empty($customer_name)) {
            throw new Exception("กรุณาระบุชื่อหน่วยงาน/ลูกค้า");
        }

        // 2. เตรียม SQL Insert (เพิ่ม contact_person, contact_phone)
        $sql = "INSERT INTO customers (
                    customer_name, affiliation, address, sub_district, district, province, zip_code, 
                    phone_number, contact_person, contact_phone, remark, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // 3. Bind Parameters (เพิ่ม type string 'ss' เข้าไปอีก 2 ตัว)
        // s = string (11 ตัว)
        $stmt->bind_param(
            "sssssssssss",
            $customer_name,
            $affiliation,
            $address,
            $sub_district,
            $district,
            $province,
            $zip_code,
            $phone_number,
            $contact_person, // ✅ เพิ่มตรงนี้
            $contact_phone,  // ✅ เพิ่มตรงนี้
            $remark
        );

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'เพิ่มข้อมูลลูกค้าเรียบร้อยแล้ว']);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();

    } catch (Exception $e) {
        // ส่งค่า Error กลับไปให้ AJAX แสดงผล
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
}

$conn->close();
?>