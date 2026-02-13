<?php
// require_once 'auth.php'; // ⚠️ Comment ไว้ถูกต้องแล้วสำหรับ API
require_once 'db_connect.php';

class CarManager {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        // เช็คการเชื่อมต่อ
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        // 🟢 เพิ่มบรรทัดนี้ลงไปตรงนี้ครับ 🟢
        // บังคับให้ MySQL session นี้ใช้เวลาประเทศไทย (+7)
        $this->conn->query("SET time_zone = '+07:00'");
    }

    // --- [ส่วนจัดการเบอร์โทรศัพท์] ---
    public function getUserPhone($user_id) {
        $sql = "SELECT phone FROM users WHERE id = ?";
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                return $row['phone'];
            }
        }
        return '';
    }

    public function updateUserPhone($user_id, $phone) {
        $sql = "UPDATE users SET phone = ? WHERE id = ?";
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("si", $phone, $user_id);
            return $stmt->execute();
        }
        return false;
    }
    // -----------------------------

    // 1. เช็คว่า User คนนี้ มีรถที่ "กำลังใช้งาน" ค้างอยู่ไหม
    public function getActiveBooking($user_id) {
        $sql = "SELECT b.*, c.name as car_name, c.plate, c.car_image, c.type 
                FROM car_bookings b 
                JOIN cars c ON b.car_id = c.id 
                WHERE b.user_id = ? AND b.status = 'active' 
                LIMIT 1";
        
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        }
        return null;
    }

    // 2. ดึงข้อมูลรถทั้งหมด
    public function getAllCars() {
        $sql = "SELECT c.*, 
                b.user_id AS busy_user_id,
                u.fullname AS busy_user_name,
                u.phone AS busy_user_phone,
                b.destination AS busy_dest,
                (SELECT return_note FROM car_bookings WHERE car_id = c.id AND status = 'completed' ORDER BY id DESC LIMIT 1) as last_info
                FROM cars c 
                LEFT JOIN car_bookings b ON c.id = b.car_id AND b.status = 'active'
                LEFT JOIN users u ON b.user_id = u.id
                ORDER BY (b.id IS NOT NULL) ASC, c.status ASC, c.id DESC";
        
        $result = $this->conn->query($sql);
        $cars = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $cars[] = $row;
            }
        }
        return $cars;
    }

    // 3. ตรวจสอบรถว่าง
    public function getAvailableCars($start_date, $end_date) {
        $sql = "SELECT c.* FROM cars c 
                WHERE c.status != 'maintenance' 
                AND c.id NOT IN (
                    SELECT car_id FROM car_bookings 
                    WHERE status IN ('approved', 'active', 'pending') 
                    AND (
                        (start_date BETWEEN ? AND ?) OR 
                        (end_date BETWEEN ? AND ?) OR 
                        (? BETWEEN start_date AND end_date)
                    )
                )";
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("sssss", $start_date, $end_date, $start_date, $end_date, $start_date);
            $stmt->execute();
            $result = $stmt->get_result();
            $available = [];
            while ($row = $result->fetch_assoc()) {
                $available[] = $row;
            }
            return $available;
        }
        return [];
    }

    // 4. สร้างการจองใหม่ (แก้ไข: เพิ่ม Check $stmt เพื่อป้องกัน Crash)
    public function createBooking($user_id, $car_id, $start, $end, $dest, $reason, $passengers = 1) {
        // แปลงค่าเป็นตัวเลขให้ชัวร์
        $user_id = intval($user_id);
        $car_id = intval($car_id);
        $passengers = intval($passengers);

        if ($this->getActiveBooking($user_id)) {
            return ["success" => false, "message" => "คุณมีรถที่กำลังใช้งานอยู่ กรุณาคืนรถคันเดิมก่อนจองใหม่ครับ"];
        }

        $check_sql = "SELECT id FROM car_bookings WHERE car_id = ? AND status IN ('active', 'approved')";
        if ($check_stmt = $this->conn->prepare($check_sql)) {
            $check_stmt->bind_param("i", $car_id);
            $check_stmt->execute();
            if($check_stmt->get_result()->num_rows > 0) {
                return ["success" => false, "message" => "ขออภัย รถคันนี้เพิ่งถูกจองไปเมื่อสักครู่"];
            }
        }

        $start_ts = strtotime($start);
        $end_ts = strtotime($end);

        if ($start_ts >= $end_ts) {
            return ["success" => false, "message" => "เวลาเริ่มต้นต้องมาก่อนเวลาสิ้นสุด"];
        }

        $sql = "INSERT INTO car_bookings (user_id, car_id, start_date, end_date, destination, reason, passenger_count, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        
        $stmt = $this->conn->prepare($sql);
        
        // 🛡️ ป้องกัน Server Crash ถ้า SQL ผิด
        if ($stmt) {
            $stmt->bind_param("iissssi", $user_id, $car_id, $start, $end, $dest, $reason, $passengers);
            
            if ($stmt->execute()) {
                return ["success" => true, "message" => "บันทึกการใช้งานสำเร็จ! สถานะ: กำลังใช้งาน"];
            } else {
                return ["success" => false, "message" => "Error Execute: " . $stmt->error];
            }
        } else {
            return ["success" => false, "message" => "Error Prepare: " . $this->conn->error];
        }
    }

    // 5. อัปเดตรายละเอียดการจอง
    public function updateBookingDetails($id, $end_date, $dest, $reason) {
        $sql = "UPDATE car_bookings SET end_date = ?, destination = ?, reason = ? WHERE id = ?";
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("sssi", $end_date, $dest, $reason, $id);
            return $stmt->execute();
        }
        return false;
    }

    // 6. คืนรถ (แก้ไข: เพิ่ม Check $stmt)
    public function returnCar($booking_id, $user_id, $return_details = "คืนรถเรียบร้อย") {
        $booking_id = intval($booking_id);
        $user_id = intval($user_id);

        $sql = "UPDATE car_bookings SET status = 'completed', return_note = ?, end_date = NOW() WHERE id = ? AND user_id = ?";
        
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("sii", $return_details, $booking_id, $user_id);
            return $stmt->execute();
        }
        return false;
    }

    // 7. เพิ่มรถใหม่
    public function addCar($name, $plate, $type, $image) {
        $sql = "INSERT INTO cars (name, plate, type, car_image, status) VALUES (?, ?, ?, ?, 'available')";
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("ssss", $name, $plate, $type, $image);
            return $stmt->execute();
        }
        return false;
    }

    // 8. แก้ไขข้อมูลรถ
    public function updateCar($id, $name, $plate, $type, $image = null) {
        if ($image) {
            $sql = "UPDATE cars SET name = ?, plate = ?, type = ?, car_image = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if ($stmt) $stmt->bind_param("ssssi", $name, $plate, $type, $image, $id);
        } else {
            $sql = "UPDATE cars SET name = ?, plate = ?, type = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if ($stmt) $stmt->bind_param("sssi", $name, $plate, $type, $id);
        }
        if ($stmt) return $stmt->execute();
        return false;
    }

    // 9. ลบรถ
    public function deleteCar($id) {
        $sql = "DELETE FROM cars WHERE id = ?";
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("i", $id);
            return $stmt->execute();
        }
        return false;
    }

    // 10. ดึงประวัติการจอง
    public function getUserHistory($user_id) {
        $sql = "SELECT b.*, c.name as car_name, c.plate, c.car_image 
                FROM car_bookings b 
                JOIN cars c ON b.car_id = c.id 
                WHERE b.user_id = ? 
                ORDER BY b.created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $history = [];
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
        }
        return $history;
    }

    // 11. ดึงรายการจองทั้งหมด
    public function getAllBookings($status_filter = null) {
        $sql = "SELECT b.*, c.name as car_name, c.plate, u.fullname 
                FROM car_bookings b 
                JOIN cars c ON b.car_id = c.id
                JOIN users u ON b.user_id = u.id ";
        
        if ($status_filter) {
            $sql .= " WHERE b.status = ? ";
        }
        $sql .= " ORDER BY b.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            if ($status_filter) {
                $stmt->bind_param("s", $status_filter);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $bookings = [];
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
            return $bookings;
        }
        return [];
    }

    // 12. อัพเดทสถานะ
    public function updateStatus($booking_id, $status, $note = null) {
        $sql = "UPDATE car_bookings SET status = ?";
        if ($note) $sql .= ", return_note = ?";
        $sql .= " WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            if ($note) {
                $stmt->bind_param("ssi", $status, $note, $booking_id);
            } else {
                $stmt->bind_param("si", $status, $booking_id);
            }
            return $stmt->execute();
        }
        return false;
    }

    // 13. ดึงรถที่ใช้งานอยู่ (Boss Dashboard)
    public function getDailyUsage($date) {
        $sql = "SELECT b.*, c.name as car_name, c.plate, c.car_image, u.fullname, u.avatar, u.phone 
                FROM car_bookings b 
                JOIN cars c ON b.car_id = c.id 
                JOIN users u ON b.user_id = u.id 
                WHERE (DATE(b.start_date) <= ? AND DATE(b.end_date) >= ?)
                AND b.status IN ('active', 'approved', 'completed')
                ORDER BY b.start_date ASC";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $date, $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while($r = $result->fetch_assoc()) {
                $data[] = $r;
            }
            return $data;
        }
        return [];
    }

    // 14. ดึงประวัติแบบ Filter (Boss Dashboard)
    public function getHistoryReport($d, $m, $y) {
        $sql = "SELECT b.*, c.name as car_name, c.plate, u.fullname, b.return_note, b.reason, b.destination 
                FROM car_bookings b 
                JOIN cars c ON b.car_id = c.id 
                JOIN users u ON b.user_id = u.id 
                WHERE 1=1 ";
        
        $params = [];
        $types = "";

        if ($d) { $sql .= " AND DAY(b.start_date) = ?"; $params[] = $d; $types .= "i"; }
        if ($m) { $sql .= " AND MONTH(b.start_date) = ?"; $params[] = $m; $types .= "i"; }
        if ($y) { $sql .= " AND YEAR(b.start_date) = ?"; $params[] = $y; $types .= "i"; }

        $sql .= " ORDER BY b.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while($r = $result->fetch_assoc()) {
                $data[] = $r;
            }
            return $data;
        }
        return [];
    }
}
?>