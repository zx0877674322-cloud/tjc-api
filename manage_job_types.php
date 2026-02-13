<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once 'auth.php';
require_once 'db_connect.php';

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_type') {
        $name = trim($_POST['job_type_name']);
        if (!empty($name)) {
            $key = "type_" . time(); 
            $sql = "INSERT INTO job_types (job_type_key, job_type_name) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $key, $name);
            $stmt->execute();
        }
    }
    if ($_POST['action'] == 'delete_type') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM job_types WHERE id = $id");
    }
    if ($_POST['action'] == 'add_channel') {
        $c_name = trim($_POST['channel_name']);
        $c_type = $_POST['channel_type'];
        $c_placeholder = trim($_POST['placeholder_text']);
        $has_ext = isset($_POST['has_ext']) ? 1 : 0;
        $is_tel = isset($_POST['is_tel']) ? 1 : 0;
        if (!empty($c_name)) {
            $stmt = $conn->prepare("INSERT INTO contact_channels (channel_name, channel_type, placeholder_text, has_ext, is_tel) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $c_name, $c_type, $c_placeholder, $has_ext, $is_tel);
            $stmt->execute();
        }
    }
    header("Location: manage_job_types.php");
    exit;
}

if (isset($_GET['delete_channel'])) {
    $id = intval($_GET['delete_channel']);
    $conn->query("DELETE FROM contact_channels WHERE id = $id");
    header("Location: manage_job_types.php");
    exit;
}

$res_types = $conn->query("SELECT * FROM job_types ORDER BY id ASC");
$contact_channels = $conn->query("SELECT * FROM contact_channels ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <?php include 'Logowab.php'; ?>
    <title>ตั้งค่าระบบ - Service System</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/service_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ปรับแต่งฟอร์มให้ดูแพงและเป็นระเบียบ */
        .form-row-custom { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .form-col-main { flex: 2; min-width: 200px; }
        .form-col-sub { flex: 1; min-width: 120px; }
        .options-group { 
            display: flex; gap: 25px; background: #fff; 
            padding: 12px 20px; border-radius: 12px; 
            border: 1px solid #e2e8f0; margin-top: 10px;
            width: fit-content;
        }
        .checkbox-item { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: #475569; }
        .checkbox-item input { width: 18px; height: 18px; cursor: pointer; accent-color: #3b82f6; }
        .action-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; border: 1px solid #f1f5f9; }
        .btn-add-pro { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0 25px; height: 46px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .btn-add-pro:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3); }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="main-container">
        
        <div class="dashboard-header-wrapper">
            <div class="header-content">
                <h2 class="page-title">ตั้งค่าพื้นฐาน</h2>
                <span class="page-subtitle"><i class="fas fa-cogs"></i> จัดการประเภทงานและช่องทางติดต่อ</span>
            </div>
            <a href="service_dashboard.php" class="btn-clear-solid" style="background:#fff; border:1px solid #cbd5e1; color:#64748b;">
                <i class="fas fa-arrow-left"></i> กลับ Dashboard
            </a>
        </div>

        <div class="action-card mt-4">
            <div class="panel-header" style="padding: 20px; border-bottom: 1px solid #f1f5f9;">
                <div class="panel-title">
                    <div class="icon-3d bg-slate-soft"><i class="fas fa-layer-group text-slate"></i></div>
                    <div><div class="title-text">จัดการประเภทงาน</div><div class="subtitle-text">Job Categories</div></div>
                </div>
            </div>
            <div class="panel-body" style="padding: 25px;">
                <form method="POST" style="margin-bottom: 25px;">
                    <input type="hidden" name="action" value="add_type">
                    <div class="form-row-custom">
                        <div class="form-col-main">
                            <label class="form-label">ชื่อประเภทงานใหม่</label>
                            <input type="text" name="job_type_name" class="modern-input" placeholder="เช่น ซ่อมแซม, ติดตั้ง..." required>
                        </div>
                        <button type="submit" class="btn-add-pro"><i class="fas fa-plus"></i> เพิ่มประเภทงาน</button>
                    </div>
                </form>
                <div class="recent-table-card" style="box-shadow:none; border:1px solid #f1f5f9;">
                    <table class="table">
                        <thead><tr><th>ชื่อประเภทงาน</th><th width="100" class="text-center">ลบ</th></tr></thead>
                        <tbody>
                            <?php while($row = $res_types->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($row['job_type_name']) ?></td>
                                <td class="text-center">
                                    <form method="POST" onsubmit="return confirm('ยืนยันการลบ?')">
                                        <input type="hidden" name="action" value="delete_type"><input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="action-card">
            <div class="panel-header" style="padding: 20px; border-bottom: 1px solid #f1f5f9;">
                <div class="panel-title">
                    <div class="icon-3d bg-pink-soft"><i class="fas fa-comments text-pink"></i></div>
                    <div><div class="title-text">จัดการช่องทางติดต่อ</div><div class="subtitle-text">Contact Channels</div></div>
                </div>
            </div>
            <div class="panel-body" style="padding: 25px;">
                <form method="POST" style="margin-bottom: 30px; background: #f8fafc; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <input type="hidden" name="action" value="add_channel">
                    
                    <div class="form-row-custom">
                        <div class="form-col-main">
                            <label class="form-label">ชื่อช่องทาง (เช่น Line, TikTok)</label>
                            <input type="text" name="channel_name" class="modern-input" placeholder="ระบุชื่อช่องทาง..." required>
                        </div>
                        <div class="form-col-sub">
                            <label class="form-label">ประเภทข้อมูล</label>
                            <select name="channel_type" class="modern-input" style="cursor:pointer;">
                                <option value="text">ข้อความ (Text)</option>
                                <option value="number">ตัวเลข (Number)</option>
                            </select>
                        </div>
                        <div class="form-col-main">
                            <label class="form-label">ข้อความแนะนำ (Placeholder)</label>
                            <input type="text" name="placeholder_text" class="modern-input" placeholder="เช่น กรอก ID ของท่าน...">
                        </div>
                        <button type="submit" class="btn-add-pro"><i class="fas fa-save"></i> เพิ่ม</button>
                    </div>

                    <div class="options-group">
                        <label class="checkbox-item">
                            <input type="checkbox" name="has_ext"> มีเบอร์ต่อ?
                        </label>
                        <label class="checkbox-item" style="color:#ef4444;">
                            <input type="checkbox" name="is_tel"> บังคับ 10 หลัก?
                        </label>
                    </div>
                </form>

                <div class="recent-table-card" style="box-shadow:none; border:1px solid #f1f5f9;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ชื่อช่องทาง</th>
                                <th>ประเภท</th>
                                <th>Placeholder</th>
                                <th class="text-center">เบอร์ต่อ</th>
                                <th class="text-center">10 หลัก</th>
                                <th width="80" class="text-center">ลบ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($cc = $contact_channels->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($cc['channel_name']) ?></td>
                                <td><span class="badge-status" style="background:#f1f5f9; color:#475569; font-size:0.7rem;"><?= strtoupper($cc['channel_type']) ?></span></td>
                                <td style="color:#64748b; font-size:0.85rem;"><?= htmlspecialchars($cc['placeholder_text']) ?></td>
                                <td class="text-center"><?= $cc['has_ext'] ? '✅' : '-' ?></td>
                                <td class="text-center"><?= $cc['is_tel'] ? '✅' : '-' ?></td>
                                <td class="text-center">
                                    <button onclick="confirmDeleteChannel(<?= $cc['id'] ?>, '<?= $cc['channel_name'] ?>')" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function confirmDeleteChannel(id, name) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: `ช่องทาง "${name}" จะถูกลบถาวร`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ลบข้อมูล',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-20' }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `manage_job_types.php?delete_channel=${id}`;
        }
    });
}
</script>
</body>
</html>