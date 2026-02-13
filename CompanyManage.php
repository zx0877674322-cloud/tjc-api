<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: Main.php");
    exit();
}

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// --- 0. (Backend) รับค่าการจัดเรียงใหม่จาก JS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reorder') {
    // รับข้อมูล Array ของ ID ที่เรียงแล้ว
    $order = $_POST['order']; 
    
    if (is_array($order)) {
        $stmt = $conn->prepare("UPDATE companies SET list_order = ? WHERE id = ?");
        foreach ($order as $position => $id) {
            $rank = $position + 1; // ลำดับเริ่มจาก 1
            $id = intval($id);
            $stmt->bind_param("ii", $rank, $id);
            $stmt->execute();
        }
        $stmt->close();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit(); // หยุดการทำงานส่วนอื่น (เพราะนี่คือ AJAX Request)
}

// --- 1. เพิ่มบริษัท ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company'])) {
    $comp_name = trim($_POST['company_name']);
    $comp_short = trim($_POST['company_shortname']); 
    $logo_filename = "";

    if (!empty($comp_name)) {
        $check = $conn->query("SELECT id FROM companies WHERE company_name = '$comp_name'");
        if ($check->num_rows == 0) {
            // หา list_order สูงสุดแล้ว +1 เพื่อต่อท้าย
            $max_q = $conn->query("SELECT MAX(list_order) as max_val FROM companies");
            $max_r = $max_q->fetch_assoc();
            $next_order = intval($max_r['max_val']) + 1;

            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
                $target_dir = __DIR__ . "/uploads/logos/";
                if (!file_exists($target_dir)) { @mkdir($target_dir, 0777, true); }
                $ext = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
                $new_name = "logo_" . time() . "_" . rand(100, 999) . "." . $ext;
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_dir . $new_name)) {
                    $logo_filename = $new_name;
                }
            }

            $stmt = $conn->prepare("INSERT INTO companies (company_name, company_shortname, logo_file, list_order) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $comp_name, $comp_short, $logo_filename, $next_order);
            
            if ($stmt->execute()) {
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'success', title: 'เพิ่มบริษัทสำเร็จ', timer: 1500, showConfirmButton: false}).then(() => window.location.href='CompanyManage.php'); });</script>";
            } else {
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด', text: '".$conn->error."'}); });</script>";
            }
        } else {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'warning', title: 'ชื่อบริษัทซ้ำ', text: 'มีบริษัทนี้ในระบบแล้ว'}); });</script>";
        }
    }
}

// --- 2. ลบบริษัท ---
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $res = $conn->query("SELECT logo_file FROM companies WHERE id = $id");
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['logo_file'])) {
            $file = __DIR__ . "/uploads/logos/" . $row['logo_file'];
            if (file_exists($file)) @unlink($file);
        }
    }
    $conn->query("DELETE FROM companies WHERE id = $id");
    header("Location: CompanyManage.php");
    exit();
}

// --- 3. ดึงข้อมูล (เรียงตาม list_order) ---
$result = $conn->query("SELECT * FROM companies ORDER BY list_order ASC");

// --- 4. ดึงข้อมูลเพื่อแก้ไข ---
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $edit_res = $conn->query("SELECT * FROM companies WHERE id = $id");
    $edit_data = $edit_res->fetch_assoc();
}

// --- 5. บันทึกการแก้ไข ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_company'])) {
    $id = intval($_POST['edit_id']);
    $comp_name = trim($_POST['company_name']);
    $comp_short = trim($_POST['company_shortname']);
    
    $stmt = $conn->prepare("UPDATE companies SET company_name=?, company_shortname=? WHERE id=?");
    $stmt->bind_param("ssi", $comp_name, $comp_short, $id);
    
    if ($stmt->execute()) {
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
            $target_dir = __DIR__ . "/uploads/logos/";
            $ext = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
            $new_name = "logo_" . time() . "_" . rand(100, 999) . "." . $ext;
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_dir . $new_name)) {
                $old_res = $conn->query("SELECT logo_file FROM companies WHERE id=$id");
                $old_row = $old_res->fetch_assoc();
                if(!empty($old_row['logo_file']) && file_exists($target_dir.$old_row['logo_file'])){
                    @unlink($target_dir.$old_row['logo_file']);
                }
                $stmt_img = $conn->prepare("UPDATE companies SET logo_file=? WHERE id=?");
                $stmt_img->bind_param("si", $new_name, $id);
                $stmt_img->execute();
            }
        }
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'success', title: 'แก้ไขข้อมูลสำเร็จ', timer: 1500, showConfirmButton: false}).then(() => window.location.href='CompanyManage.php'); });</script>";
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด', text: '".$conn->error."'}); });</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบริษัท - TJC System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

    <style>
        /* CSS หลัก (เหมือนเดิม) */
        .main-content { padding: 30px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title h2 { margin: 0; color: var(--text-main); font-size: 1.8rem; display: flex; align-items: center; gap: 10px; }
        .page-title p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.95rem; }
        .card { background-color: var(--bg-card); border-radius: 20px; box-shadow: var(--shadow); border: 1px solid var(--border-color); overflow: hidden; margin-bottom: 30px; }
        .card-header { padding: 20px 25px; background-color: var(--hover-bg); border-bottom: 1px solid var(--border-color); font-weight: 600; font-size: 1.1rem; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-main); }
        .form-control { width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--border-color); background-color: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; font-size: 1rem; box-sizing: border-box; transition: 0.3s; }
        .form-control:focus { outline: none; border-color: var(--primary-color); }
        .upload-box { border: 2px dashed var(--border-color); border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; background-color: var(--bg-input); transition: 0.3s; }
        .upload-box:hover { border-color: var(--primary-color); background-color: var(--hover-bg); }
        .upload-icon { font-size: 2rem; color: var(--text-muted); margin-bottom: 10px; }
        .upload-text { color: var(--text-muted); font-size: 0.9rem; }
        .btn-primary { background: var(--primary-color); color: white !important; border: none; padding: 12px 25px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 1rem; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-danger { background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-danger:hover { background: #ef4444; color: white; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { padding: 15px; text-align: left; background-color: var(--hover-bg); color: var(--text-muted); font-weight: 600; border-bottom: 2px solid var(--border-color); }
        td { padding: 15px; vertical-align: middle; border-bottom: 1px solid var(--border-color); color: var(--text-main); }
        tr:last-child td { border-bottom: none; }
        .company-logo { width: 50px; height: 50px; object-fit: contain; border-radius: 8px; border: 1px solid var(--border-color); background: #fff; padding: 2px; }
        .no-logo { width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: var(--hover-bg); border-radius: 8px; color: var(--text-muted); font-size: 1.5rem; border: 1px solid var(--border-color); }
        .btn-warning { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; margin-right: 5px; }
        .btn-warning:hover { background: #d97706; color: white; }
        @media (max-width: 768px) { .page-header { flex-direction: column; align-items: flex-start; gap: 15px; } .main-content { padding: 20px; } }

        /* 🔥 Style สำหรับ Drag Handle */
        .drag-handle {
            cursor: move; /* เปลี่ยน Cursor เป็นรูปมือจับ */
            color: #9ca3af;
            font-size: 1.2rem;
            transition: color 0.2s;
        }
        .drag-handle:hover {
            color: var(--primary-color);
        }
        /* ไฮไลท์แถวที่กำลังถูกลาก */
        .sortable-ghost {
            background-color: #f3f4f6;
            opacity: 0.5;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            
            <div class="page-header">
                <div class="page-title">
                    <h2><i class="fas fa-building" style="color:var(--primary-color);"></i> จัดการบริษัท</h2>
                    <p>เพิ่ม ลบ แก้ไข และจัดเรียงรายชื่อบริษัท</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i> <?php echo $edit_data ? 'แก้ไขข้อมูลบริษัท' : 'เพิ่มบริษัทใหม่'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?php if($edit_data): ?>
                            <input type="hidden" name="edit_id" value="<?php echo $edit_data['id']; ?>">
                        <?php endif; ?>

                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">ชื่อบริษัท / หน่วยงาน (เต็ม)</label>
                                <input type="text" name="company_name" class="form-control" 
                                       placeholder="ระบุชื่อบริษัทเต็ม..." required
                                       value="<?php echo $edit_data ? $edit_data['company_name'] : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">ชื่อย่อ (ถ้ามี)</label>
                                <input type="text" name="company_shortname" class="form-control" 
                                       placeholder="เช่น TJC, ABC..."
                                       value="<?php echo $edit_data ? $edit_data['company_shortname'] : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">โลโก้ (อัปโหลดเพื่อเปลี่ยน)</label>
                                <div class="upload-box" onclick="document.getElementById('logoInput').click()">
                                    <i class="fas fa-image upload-icon"></i>
                                    <div class="upload-text" id="fileName">
                                        <?php echo $edit_data && !empty($edit_data['logo_file']) ? 'มีรูปเดิมแล้ว (คลิกเพื่อเปลี่ยน)' : 'เลือกรูป'; ?>
                                    </div>
                                    <input type="file" name="company_logo" id="logoInput" accept="image/*" style="display:none;" onchange="showFileName(this)">
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px;">
                            <?php if($edit_data): ?>
                                <a href="CompanyManage.php" class="btn-danger" style="background:#6b7280; border-color:#4b5563; color:white; margin-right:10px;">ยกเลิก</a>
                                <button type="submit" name="update_company" class="btn-primary" style="background:#d97706;">
                                    <i class="fas fa-edit"></i> บันทึกการแก้ไข
                                </button>
                            <?php else: ?>
                                <button type="submit" name="add_company" class="btn-primary">
                                    <i class="fas fa-save"></i> บันทึกข้อมูล
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list-ul"></i> รายชื่อบริษัททั้งหมด (ลากเพื่อจัดเรียง)
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="50" style="text-align:center;">ย้าย</th>
                                <th width="60" style="text-align:center;">#</th>
                                <th width="80" style="text-align:center;">โลโก้</th>
                                <th width="120">ชื่อย่อ</th> 
                                <th>ชื่อบริษัทเต็ม</th>
                                <th width="100" style="text-align:center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="companyTableBody">
                            <?php 
                            if ($result->num_rows > 0) {
                                $i = 1;
                                while($row = $result->fetch_assoc()) {
                                    $img_path = "uploads/logos/" . $row['logo_file'];
                                    $img_html = (!empty($row['logo_file']) && file_exists($img_path)) 
                                        ? "<img src='$img_path' class='company-logo'>" 
                                        : "<div class='no-logo'><i class='fas fa-briefcase'></i></div>";
                                    
                                    $show_short = !empty($row['company_shortname']) ? $row['company_shortname'] : '-';
                            ?>
                                <tr data-id="<?php echo $row['id']; ?>">
                                    <td style="text-align:center;">
                                        <div class="drag-handle">
                                            <i class="fas fa-grip-vertical"></i>
                                        </div>
                                    </td>
                                    <td style="text-align:center; color:var(--text-muted);"><?php echo $i++; ?></td>
                                    <td style="text-align:center;"><?php echo $img_html; ?></td>
                                    <td style="font-weight:600; color:var(--primary-color);"><?php echo $show_short; ?></td> 
                                    <td style="font-weight:500;"><?php echo $row['company_name']; ?></td>
                                    <td style="text-align:center;">
                                        <a href="?edit=<?php echo $row['id']; ?>" class="btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?del=<?php echo $row['id']; ?>" class="btn-danger" onclick="return confirm('ยืนยันการลบบริษัท <?php echo $row['company_name']; ?>? ข้อมูลที่เกี่ยวข้องอาจหายไป');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center; padding:30px; color:var(--text-muted);'>ไม่พบข้อมูลบริษัท</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showFileName(input) {
            const display = document.getElementById('fileName');
            if (input.files && input.files[0]) {
                let name = input.files[0].name;
                if(name.length > 15) name = name.substring(0, 15) + '...';
                display.innerText = name;
                display.style.color = "var(--primary-color)";
                display.style.fontWeight = "bold";
            } else {
                display.innerText = "เลือกรูป";
                display.style.color = "var(--text-muted)";
                display.style.fontWeight = "normal";
            }
        }

        // --- Script สำหรับ Drag & Drop ---
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('companyTableBody');
            
            // เปิดใช้งาน Sortable
            Sortable.create(el, {
                handle: '.drag-handle', // จับได้เฉพาะตรงไอคอน
                animation: 150, // ความลื่น
                ghostClass: 'sortable-ghost', // class ตอนลาก
                
                // เมื่อวางแล้วให้ทำอะไรต่อ
                onEnd: function (evt) {
                    var itemEl = evt.item;  // แถวที่ถูกลาก
                    var newOrder = [];
                    
                    // วนลูปหา ID ตามลำดับใหม่
                    document.querySelectorAll('#companyTableBody tr').forEach(function(row) {
                        newOrder.push(row.getAttribute('data-id'));
                    });

                    // ส่ง AJAX ไปบันทึก
                    updateOrder(newOrder);
                }
            });
        });

        function updateOrder(orderList) {
            // ใช้ Fetch API ส่งข้อมูลไปที่หน้าเดิม (Backend ส่วนบนจะรับจัดการ)
            const formData = new FormData();
            formData.append('action', 'reorder');
            
            // ส่ง array id ไป
            orderList.forEach((id, index) => {
                formData.append('order[]', id);
            });

            fetch('CompanyManage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    // เรียงสำเร็จ (อาจจะขึ้น Toast เล็กๆ ก็ได้ แต่นี้เงียบๆ ไว้เพื่อให้ลื่น)
                    console.log('Order updated');
                    
                    // (Optional) รีเฟรชหน้าเพื่อรันเลขลำดับ 1,2,3 ใหม่
                    // window.location.reload(); 
                } else {
                    Swal.fire({icon: 'error', title: 'จัดเรียงไม่สำเร็จ'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>

</body>
</html>