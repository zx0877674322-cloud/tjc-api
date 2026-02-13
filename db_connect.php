<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tjcrepor_tjc_db";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่าภาษาไทยให้แสดงผลถูกต้อง
$conn->set_charset("utf8mb4");
?>