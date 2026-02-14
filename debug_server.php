<?php
// 1. บังคับโชว์ Error แบบดิบๆ (ห้ามซ่อน)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 เริ่มการตรวจสอบ Server (Debug Mode)</h1>";

// 2. ลองเชื่อมต่อ Database
// ⚠️ แก้ค่าตรงนี้ให้ตรงกับ Server จริง
$servername = "82.163.176.14";
$username = "tjcrepor";
$password = "2q3EXl7G6O](vr";
$dbname = "tjcrepor_tjc_db";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("<h2 style='color:red'>❌ เชื่อมต่อ Database ไม่ได้: " . $conn->connect_error . "</h2>");
}
echo "<h3 style='color:green'>✅ เชื่อมต่อ Database สำเร็จ</h3>";

$conn->set_charset("utf8mb4");

// 3. เช็คว่ามีตารางชื่ออะไรบ้าง (ดูเรื่องตัวเล็กตัวใหญ่)
echo "<hr><h3>📂 รายชื่อตารางใน Database (เช็คตัวพิมพ์เล็ก/ใหญ่):</h3>";
$result = $conn->query("SHOW TABLES");
if ($result->num_rows > 0) {
    echo "<ul>";
    $found_cashflow = false;
    while($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
        if (strtolower($row[0]) == 'cash_flow') {
            $real_table_name = $row[0]; // เก็บชื่อจริงที่เจอ
            $found_cashflow = true;
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>❌ ไม่พบตารางใดๆ เลย (Database ว่างเปล่า?)</p>";
}

// 4. ถ้าเจอตาราง cash_flow ลองดึงข้อมูลดิบๆ
if ($found_cashflow) {
    echo "<hr><h3>📊 ตรวจสอบตาราง: $real_table_name</h3>";
    
    // เช็คจำนวนข้อมูล
    $count = $conn->query("SELECT COUNT(*) as total FROM $real_table_name")->fetch_assoc()['total'];
    echo "<p>จำนวนข้อมูลทั้งหมด: <strong>$count</strong> แถว</p>";

    if ($count > 0) {
        echo "<h4>ตัวอย่างข้อมูล 1 แถวล่าสุด:</h4>";
        $sql = "SELECT * FROM $real_table_name ORDER BY id DESC LIMIT 1";
        $result = $conn->query($sql);
        
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc;'>";
            print_r($row);
            echo "</pre>";
        } else {
            echo "<p style='color:red'>❌ Query พัง: " . $conn->error . "</p>";
        }
    } else {
        echo "<h2 style='color:red'>⚠️ ตารางมีอยู่จริง แต่ไม่มีข้อมูล (Empty)!</h2>";
        echo "<p>คุณอาจจะลืม Import ข้อมูลขึ้น Server หรือเปล่า?</p>";
    }

} else {
    echo "<h2 style='color:red; border: 2px solid red; padding: 10px;'>❌ ไม่พบตารางที่ชื่อว่า 'cash_flow' (หรือ Cash_Flow)</h2>";
    echo "<p>กรุณาเช็คใน phpMyAdmin ว่าคุณตั้งชื่อตารางว่าอะไรกันแน่?</p>";
}

$conn->close();
?>