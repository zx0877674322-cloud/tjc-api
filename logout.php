<?php
session_start();
session_destroy(); // ล้างข้อมูลการล็อกอินทั้งหมด (Session)
header("Location: login.php"); // ดีดกลับไปหน้า Login
exit();
?>