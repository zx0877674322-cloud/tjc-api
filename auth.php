<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบว่า Login หรือยัง
if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php'; 

// ==================================================================================
// 🔑 ส่วนที่ 3: ฟังก์ชัน hasAction() (ฉบับแก้ไขให้ตรงกับตาราง role_actions ของคุณ)
// ==================================================================================

if (!function_exists('hasAction')) {
    function hasAction($action_code) {
        global $conn; 
        
        // -----------------------------------------------------------
        // 1. โหมด Admin (God Mode)
        // ถ้าคุณอยากทดสอบสิทธิ์ ให้ลองเอาเครื่องหมาย // หน้าบรรทัด return true ออก
        // -----------------------------------------------------------
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
             return true; // 👈 Admin เห็นทุกอย่าง (ถ้าอยากเทสให้คอมเมนต์บรรทัดนี้ทิ้ง)
        }
        
        // -----------------------------------------------------------
        // 2. โหลดสิทธิ์จาก DB (ตาราง role_actions) เก็บใส่ Session
        // (ทำเฉพาะตอนยังไม่มีข้อมูล เพื่อลดการ Query บ่อยๆ)
        // -----------------------------------------------------------
        if (!isset($_SESSION['user_actions'])) {
            $current_role = $_SESSION['role'];
            $actions = [];
            
            // ✅ แก้ SQL ให้ตรงกับตาราง role_actions ของคุณ
            $sql_actions = "SELECT action_code FROM role_actions WHERE role_name = ?";
            
            if ($stmt_act = $conn->prepare($sql_actions)) {
                $stmt_act->bind_param("s", $current_role);
                $stmt_act->execute();
                $res_act = $stmt_act->get_result();
                
                while ($row = $res_act->fetch_assoc()) {
                    $actions[] = $row['action_code']; // เก็บชื่อปุ่มที่อนุญาต เช่น view_sales_tab
                }
                $_SESSION['user_actions'] = $actions; // บันทึกลง Session
            } else {
                // กรณี Query ผิดพลาด
                $_SESSION['user_actions'] = [];
            }
        }

        // -----------------------------------------------------------
        // 3. ตรวจสอบว่า code ที่ส่งมา มีอยู่ในสิทธิ์ที่โหลดมาไหม
        // -----------------------------------------------------------
        $my_actions = $_SESSION['user_actions'] ?? [];
        
        // คืนค่า True ถ้ามีชื่อปุ่มนี้ใน Array สิทธิ์ของฉัน
        return in_array($action_code, $my_actions);
    }
}
?>