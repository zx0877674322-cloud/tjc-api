<?php
include 'auth.php';
include 'db_connect.php';

// 1. แก้ชื่อตัวแปรให้ตรงกัน (ใช้ user_id ตัวเล็กตามที่มักใช้กัน)
$user_id = $_SESSION['user_id'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $status = 4; // สถานะยกเลิก
    $remark = trim($_POST['remark']);

    try {
        $conn->begin_transaction();

        $sql = "UPDATE project_contracts SET project_status = ?, remark = ? WHERE site_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $status, $remark, $id);

        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }
        $stmt->close();
        $sql_log = "INSERT INTO `project_logs` (`site_id`, `action`, `status`, `user_id`) VALUES (?, 'Cancel', ?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("isi", $id, $status, $user_id);

        if (!$stmt_log->execute()) {
            throw new Exception("Log failed: " . $stmt_log->error);
        }
        $stmt_log->close();
        $conn->commit();
        echo "success";

    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
    $conn->close();
}
?>