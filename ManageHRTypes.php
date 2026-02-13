<?php
session_start();
require_once 'auth.php'; 
require_once 'db_connect.php';
$conn->set_charset("utf8");


$message = "";

// --- ลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // 🔥 เปลี่ยนชื่อตาราง
    $conn->query("DELETE FROM master_hr_types WHERE id = $del_id");
    $message = "<div class='alert success'><i class='fas fa-check'></i> ลบข้อมูลสำเร็จ</div>";
}

// --- บันทึก/แก้ไขข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['type_name']);
    $color = $_POST['color_class'];
    $edit_id = $_POST['edit_id'] ?? '';

    if (!empty($edit_id)) {
        // 🔥 เปลี่ยนชื่อตาราง
        $stmt = $conn->prepare("UPDATE master_hr_types SET type_name=?, color_class=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $color, $edit_id);
    } else {
        // 🔥 เปลี่ยนชื่อตาราง
        $stmt = $conn->prepare("INSERT INTO master_hr_types (type_name, color_class) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $color);
    }

    if ($stmt->execute()) {
        $message = "<div class='alert success'><i class='fas fa-check'></i> บันทึกข้อมูลสำเร็จ</div>";
    } else {
        $message = "<div class='alert error'>เกิดข้อผิดพลาด</div>";
    }
}

// 🔥 ดึงข้อมูลจาก master_hr_types
$result = $conn->query("SELECT * FROM master_hr_types ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <title>จัดการประเภทประกาศ (HR)</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-body: #f8fafc; --bg-card: #ffffff; --text-main: #1e293b; --border: #e2e8f0; --primary: #2563eb; }
        [data-theme="dark"] { --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc; --border: #334155; --primary: #60a5fa; }
        body { font-family: 'Prompt', sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; padding-left: 270px; transition:0.3s; }
        @media (max-width: 900px) { body { padding-left: 0; } }
        
        .container { padding: 30px; max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .form-control { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); box-sizing: border-box; }
        .btn { padding: 10px 15px; border-radius: 8px; border: none; cursor: pointer; color: white; font-family: 'Prompt'; width: 100%; }
        .btn-save { background: var(--primary); }
        .btn-del { background: #ef4444; width: auto; padding: 5px 10px; font-size: 0.8rem; }
        .btn-edit { background: #f59e0b; width: auto; padding: 5px 10px; font-size: 0.8rem; margin-right: 5px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px; border-bottom: 2px solid var(--border); color: var(--primary); }
        td { padding: 10px; border-bottom: 1px solid var(--border); }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; color: white; display: inline-block; }
        .bg-primary { background: #2563eb; } .bg-secondary { background: #64748b; } .bg-success { background: #10b981; } .bg-danger { background: #ef4444; } .bg-warning { background: #f59e0b; } .bg-info { background: #0ea5e9; }
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 8px; background: #dcfce7; color: #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        @media (max-width: 768px) { .container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div style="padding: 20px 30px; background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%); color: white; margin-bottom: 20px;">
        <h2><i class="fas fa-tags"></i> จัดการประเภทประกาศ (HR)</h2>
        <a href="Announcement.php" style="color:white; text-decoration:none; font-size:0.9rem;"><i class="fas fa-arrow-left"></i> กลับหน้าประกาศ</a>
    </div>

    <div class="container">
        <div class="card" style="height: fit-content;">
            <h3 style="margin-top:0;">เพิ่ม/แก้ไข ประเภท</h3>
            <?php echo $message; ?>
            <form method="POST">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <label>ชื่อประเภท</label>
                <input type="text" name="type_name" id="type_name" class="form-control" required placeholder="เช่น ข่าวประชาสัมพันธ์...">
                
                <label>สีป้ายกำกับ</label>
                <select name="color_class" id="color_class" class="form-control">
                    <option value="secondary">สีเทา (ทั่วไป)</option>
                    <option value="primary">สีน้ำเงิน (หลัก)</option>
                    <option value="success">สีเขียว (กิจกรรม)</option>
                    <option value="warning">สีส้ม (แจ้งเตือน)</option>
                    <option value="danger">สีแดง (ด่วน)</option>
                    <option value="info">สีฟ้า (ข้อมูล)</option>
                </select>

                <button type="submit" class="btn btn-save" id="btnSave">บันทึกข้อมูล</button>
                <button type="button" class="btn" style="background:transparent; color:var(--text-main); border:1px solid var(--border); margin-top:10px;" onclick="resetForm()">ล้างค่า</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">รายการประเภททั้งหมด</h3>
            <table>
                <thead>
                    <tr><th>ชื่อประเภท</th><th>ตัวอย่าง</th><th width="100">จัดการ</th></tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['type_name']; ?></td>
                        <td><span class="badge bg-<?php echo $row['color_class']; ?>"><?php echo $row['type_name']; ?></span></td>
                        <td>
                            <button onclick="editItem('<?php echo $row['id']; ?>','<?php echo $row['type_name']; ?>','<?php echo $row['color_class']; ?>')" class="btn btn-edit"><i class="fas fa-pen"></i></button>
                            <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('ลบรายการนี้?')" class="btn btn-del"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function editItem(id, name, color) {
            document.getElementById('edit_id').value = id;
            document.getElementById('type_name').value = name;
            document.getElementById('color_class').value = color;
            document.getElementById('btnSave').innerText = 'อัปเดตข้อมูล';
        }
        function resetForm() {
            document.querySelector('form').reset();
            document.getElementById('edit_id').value = '';
            document.getElementById('btnSave').innerText = 'บันทึกข้อมูล';
        }
    </script>
</body>
</html>