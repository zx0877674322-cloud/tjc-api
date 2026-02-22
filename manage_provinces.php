<?php
session_start();
require_once 'db_connect.php'; // เปลี่ยนให้ตรงกับไฟล์เชื่อมต่อ DB ของลูกพี่

// จัดการเพิ่มจังหวัด
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    header('Content-Type: application/json');
    $prov_name = trim($_POST['province_name']);
    if (!empty($prov_name)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO provinces (province_name) VALUES (?)");
        $stmt->bind_param("s", $prov_name);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'เพิ่มข้อมูลไม่สำเร็จ หรือมีจังหวัดนี้อยู่แล้ว']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกชื่อจังหวัด']);
    }
    exit;
}

// จัดการลบจังหวัด
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM provinces WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ลบข้อมูลไม่สำเร็จ']);
    }
    exit;
}

// ดึงข้อมูลมาแสดง
$result = $conn->query("SELECT * FROM provinces ORDER BY province_name ASC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลจังหวัด</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f8fafc; color: #334155; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-group { display: flex; gap: 10px; margin-bottom: 20px; }
        .form-control { flex: 1; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Prompt'; }
        .btn { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-family: 'Prompt'; font-weight: 600; transition: 0.2s; }
        .btn:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; padding: 6px 12px; }
        .btn-danger:hover { background: #dc2626; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; font-weight: 700; color: #475569; }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-map-marked-alt text-blue-500"></i> จัดการข้อมูลจังหวัด</h2>
    
    <div class="form-group">
        <input type="text" id="new_province" class="form-control" placeholder="พิมพ์ชื่อจังหวัดที่ต้องการเพิ่ม...">
        <button type="button" class="btn" onclick="addProvince()"><i class="fas fa-plus"></i> เพิ่มจังหวัด</button>
    </div>

    <table>
        <thead>
            <tr>
                <th width="10%">ลำดับ</th>
                <th>ชื่อจังหวัด</th>
                <th width="15%" style="text-align:center;">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $i++; ?></td>
                <td style="font-weight: 500; color: #1e293b;"><?= htmlspecialchars($row['province_name']); ?></td>
                <td style="text-align:center;">
                    <button class="btn btn-danger" onclick="deleteProvince(<?= $row['id']; ?>, '<?= htmlspecialchars($row['province_name']); ?>')">
                        <i class="fas fa-trash-alt"></i> ลบ
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if($result->num_rows == 0): ?>
            <tr><td colspan="3" style="text-align:center; color:#94a3b8;">ยังไม่มีข้อมูลจังหวัด</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function addProvince() {
    const name = $('#new_province').val().trim();
    if(!name) return Swal.fire('แจ้งเตือน', 'กรุณากรอกชื่อจังหวัด', 'warning');
    
    $.post('manage_provinces.php', { action: 'add', province_name: name }, function(res) {
        if(res.status === 'success') location.reload();
        else Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
    }, 'json');
}

function deleteProvince(id, name) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: `คุณต้องการลบจังหวัด "${name}" ใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('manage_provinces.php', { action: 'delete', id: id }, function(res) {
                if(res.status === 'success') location.reload();
                else Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
            }, 'json');
        }
    });
}
</script>
</body>
</html>