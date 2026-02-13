<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php'; 

// เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    exit();
}

$message = "";

// --- ฟังก์ชันเพิ่ม Role ---
if (isset($_POST['add_role'])) {
    $new_role = trim($_POST['role_name']);
    if (!empty($new_role)) {
        // เช็คว่าซ้ำไหม
        $check = $conn->query("SELECT * FROM master_roles WHERE role_name = '$new_role'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO master_roles (role_name) VALUES (?)");
            $stmt->bind_param("s", $new_role);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>✅ เพิ่มตำแหน่ง '$new_role' เรียบร้อย</div>";
            } else {
                $message = "<div class='alert error'>❌ เกิดข้อผิดพลาด: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert error'>⚠️ ตำแหน่งนี้มีอยู่แล้ว</div>";
        }
    }
}

// --- ฟังก์ชันลบ Role ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM master_roles WHERE id = $id");
    header("Location: ManagerRoles.php"); 
    exit();
}

// ดึงข้อมูล Role ทั้งหมด
$roles = $conn->query("SELECT * FROM master_roles ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title>จัดการตำแหน่ง (Roles) - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* จัด Layout อย่างเดียว (สีให้ Global CSS จัดการ) */
        .container { 
            max-width: 800px; margin: 40px auto; 
            padding: 30px; border-radius: 15px; 
            /* สีพื้นหลังและเงาจะถูกคุมโดย class .card จาก sidebar.php */
        }
        
        .form-group { display: flex; gap: 10px; margin-bottom: 25px; }
        .form-control { flex: 1; padding: 12px; border-radius: 8px; font-family: 'Prompt'; }
        
        button { 
            background: #4f46e5; color: white; border: none; 
            padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: 600; 
            transition: 0.2s;
        }
        button:hover { opacity: 0.9; transform: translateY(-2px); }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
        th, td { padding: 15px; text-align: left; }
        th { font-weight: 600; }
        
        .role-badge { padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: 500; background: rgba(0,0,0,0.05); }
        
        .btn-del { color: #ef4444; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.2s; }
        .btn-del:hover { color: #dc2626; text-decoration: underline; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        @media (max-width: 768px) { .container { padding: 20px; margin: 20px; } .form-group { flex-direction: column; } button { width: 100%; } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="container card">
        <h2 style="margin-top:0; margin-bottom:20px; font-size:24px;">
            <i class="fas fa-shield-alt" style="color:#4f46e5;"></i> จัดการตำแหน่งผู้ใช้งาน
        </h2>
        
        <?php echo $message; ?>

        <form method="POST" class="form-group">
            <input type="text" name="role_name" class="form-control" placeholder="ระบุชื่อตำแหน่งใหม่... (เช่น Supervisor, HR)" required>
            <button type="submit" name="add_role"><i class="fas fa-plus"></i> เพิ่มตำแหน่ง</button>
        </form>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th width="10%">ID</th>
                        <th>ชื่อตำแหน่ง (Role Name)</th>
                        <th width="20%" style="text-align:right;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $roles->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <span class="role-badge">
                                <i class="fas fa-user-tag"></i> <?php echo $row['role_name']; ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <?php if(!in_array(strtolower($row['role_name']), ['admin', 'manager', 'staff'])): ?>
                                <a href="ManagerRoles.php?delete=<?php echo $row['id']; ?>" class="btn-del" onclick="return confirm('ยืนยันการลบตำแหน่ง <?php echo $row['role_name']; ?>?');">
                                    <i class="fas fa-trash-alt"></i> ลบ
                                </a>
                            <?php else: ?>
                                <span style="opacity:0.5; font-size:12px; font-style:italic;">(ระบบล็อค)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>