<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// เช็คว่าล็อกอินไหม
if (!isset($_SESSION['fullname'])) {
    echo json_encode(['has_target' => false, 'msg' => 'Not logged in']);
    exit();
}

$reporter_name = $_SESSION['fullname'];
$date_str = $_GET['date'] ?? date('Y-m-d'); // รับวันที่จาก JS

// แปลงวันที่เป็น เดือน/ปี เพื่อไปเช็คในตาราง
$timestamp = strtotime($date_str);
$month = date('n', $timestamp);
$year = date('Y', $timestamp);

// Query ดูซิว่ามีเป้าไหม
$sql = "SELECT target_amount FROM sales_targets 
        WHERE reporter_name = ? AND target_month = ? AND target_year = ?";

$response = ['has_target' => false, 'amount' => 0];

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("sii", $reporter_name, $month, $year);
    $stmt->execute();
    $stmt->bind_result($amount);

    if ($stmt->fetch()) {
        $response['has_target'] = true;
        $response['amount'] = number_format($amount, 0); // จัดรูปแบบมีคอมม่า (ไม่มีทศนิยม)
    }
    $stmt->close();
}

echo json_encode($response);
?>