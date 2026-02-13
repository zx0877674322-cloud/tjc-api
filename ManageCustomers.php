<?php
session_start();
require_once 'auth.php'; 
require_once 'db_connect.php';

// --- จัดการเพิ่มข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_name'])) {
    $name = trim($_POST['customer_name']);
    
    if (!empty($name)) {
        // เช็คว่าชื่อซ้ำไหม
        $check = $conn->prepare("SELECT id FROM master_customers WHERE customer_name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        
        if ($check->get_result()->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO master_customers (customer_name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
        }
    }
    // รีเฟรชหน้าจอเพื่อล้างค่า
    header("Location: ManageCustomers.php");
    exit();
}

// --- จัดการลบข้อมูล ---
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $conn->query("DELETE FROM master_customers WHERE id = $id");
    header("Location: ManageCustomers.php");
    exit();
}

// ดึงรายชื่อทั้งหมด
$result = $conn->query("SELECT * FROM master_customers ORDER BY customer_name ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรายชื่อหน่วยงาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f4f6f9; margin: 0; }
        .container { max-width: 800px; margin: 40px auto; padding: 20px; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #004aad; margin-top: 0; }
        
        .input-group { display: flex; gap: 10px; margin-bottom: 20px; }
        input[type="text"] { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Prompt'; font-size: 1rem; }
        button { background: #004aad; color: white; border: none; padding: 0 20px; border-radius: 8px; cursor: pointer; font-family: 'Prompt'; font-size: 1rem; }
        button:hover { opacity: 0.9; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:last-child td { border-bottom: none; }
        .btn-del { color: #ef4444; background: #fee2e2; padding: 1px 2px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; }
        .btn-del:hover { background: #fecaca; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="container">
    <div class="card">
        <h2><i class="fas fa-list"></i> รายชื่อหน่วยงาน / ลูกค้า</h2>
        
        <form method="post">
            <div class="input-group">
                <input type="text" name="customer_name" placeholder="พิมพ์ชื่อหน่วยงานที่ต้องการเพิ่ม..." required autocomplete="off">
                <button type="submit"><i class="fas fa-plus"></i> เพิ่ม</button>
            </div>
        </form>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

        <table>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight: 500;"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                    <td style="text-align: right; width: 50px;">
                        <a href="?del=<?php echo $row['id']; ?>" class="btn-del" onclick="return confirm('ยืนยันการลบ?');">
                            <i class="fas fa-trash"></i> ลบ
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="2" style="text-align:center; color:#999;">ยังไม่มีข้อมูล</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</body>
</html>