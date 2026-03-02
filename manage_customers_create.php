<?php
include 'auth.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

// 1. เช็คว่ามีการ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['customer_name'])) {

    // รับค่าตัวแปร
    $name = trim($_POST['customer_name']);
    $affiliation = trim($_POST['affiliation'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $sub_district = trim($_POST['sub_district'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $remark = $_POST['remark'] ?? '';

    try {
        // เริ่ม Transaction
        $conn->begin_transaction();

        // 2. เช็คชื่อซ้ำ
        $check = $conn->prepare("SELECT customer_id FROM customers WHERE customer_name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        $result_check = $check->get_result();

        if ($result_check->num_rows == 0) {

            $sql = "INSERT INTO customers (customer_name, affiliation, address, sub_district, district, province, zip_code, 
            phone_number, user_id, remark, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "ssssssssss",
                $name,
                $affiliation,
                $address,
                $sub_district,
                $district,
                $province,
                $zip_code,
                $phone_number,
                $user_id,
                $remark
            );

            if ($stmt->execute()) {
                $conn->commit();
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'เพิ่มข้อมูลลูกค้าเรียบร้อยแล้ว',
                        showConfirmButton: false,
                        timer: 800,
                        timerProgressBar: true
                    });
                </script>";
            } else {
                throw new Exception($stmt->error); // โยน Error ไปที่ catch
            }
            $stmt->close();

        } else {
            $name_safe = json_encode("ชื่อหน่วยงาน/ลูกค้า '$name' มีอยู่ในระบบแล้ว");
            // กรณีชื่อซ้ำ
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลซ้ำ',
                    text:  $name_safe ,
                    confirmButtonText: 'ตกลง'
                });
            </script>";
        }
        $check->close();

    } catch (Exception $e) {
        $conn->rollback(); // ยกเลิกการเปลี่ยนแปลงหากมี Error

        $error_msg = addslashes($e->getMessage());
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ระบบแจ้งว่า: $error_msg',
                confirmButtonText: 'ปิด'
            });
        </script>";
    }
}
?>