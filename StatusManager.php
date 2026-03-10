<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

$message = "";

// --- 3. แก้ไขข้อมูล (Update) ---
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_status_name = "";

if ($edit_id > 0) {
    if (isset($_POST['update_status'])) {
        $status_name = trim($_POST['status_name']);

        if (!empty($status_name)) {
            $stmt_check = $conn->prepare("SELECT id FROM master_job_status WHERE status_name = ? AND id != ?");
            $stmt_check->bind_param("si", $status_name, $edit_id);
            $stmt_check->execute();
            $check = $stmt_check->get_result();

            if ($check->num_rows == 0) {
                $stmt = $conn->prepare("UPDATE master_job_status SET status_name = ? WHERE id = ?");
                $stmt->bind_param("si", $status_name, $edit_id);
                if ($stmt->execute()) {
                    $message = "<div class='alert success'><i class='fas fa-check-circle'></i> อัปเดตสถานะเป็น <b>'$status_name'</b> เรียบร้อย</div>";
                    $edit_id = 0; // กลับไปโหมดเพิ่มสถานะ
                } else {
                    $message = "<div class='alert error'><i class='fas fa-exclamation-triangle'></i> Error: " . $conn->error . "</div>";
                }
            } else {
                $message = "<div class='alert warning'><i class='fas fa-exclamation-circle'></i> สถานะนี้มีอยู่แล้ว</div>";
            }
        } else {
            $message = "<div class='alert error'><i class='fas fa-times-circle'></i> กรุณากรอกชื่อสถานะ</div>";
        }
    }

    // ดึงข้อมูลเดิมมาแสดงในฟอร์ม
    if ($edit_id > 0) {
        $stmt_fetch = $conn->prepare("SELECT status_name FROM master_job_status WHERE id = ?");
        $stmt_fetch->bind_param("i", $edit_id);
        $stmt_fetch->execute();
        $res = $stmt_fetch->get_result();
        if ($row_edit = $res->fetch_assoc()) {
            $edit_status_name = $row_edit['status_name'];
        } else {
            $edit_id = 0;
            $message = "<div class='alert error'><i class='fas fa-times-circle'></i> ไม่พบข้อมูลที่ต้องการแก้ไข</div>";
        }
    }
}

// --- 1. เพิ่มข้อมูล (Insert) ---
if (isset($_POST['add_status'])) {
    $status_name = trim($_POST['status_name']);

    if (!empty($status_name)) {
        $stmt_check = $conn->prepare("SELECT id FROM master_job_status WHERE status_name = ?");
        $stmt_check->bind_param("s", $status_name);
        $stmt_check->execute();
        $check = $stmt_check->get_result();

        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO master_job_status (status_name) VALUES (?)");
            $stmt->bind_param("s", $status_name);
            if ($stmt->execute()) {
                $message = "<div class='alert success'><i class='fas fa-check-circle'></i> เพิ่มสถานะ <b>'$status_name'</b> เรียบร้อย</div>";
            } else {
                $message = "<div class='alert error'><i class='fas fa-exclamation-triangle'></i> Error: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert warning'><i class='fas fa-exclamation-circle'></i> สถานะนี้มีอยู่แล้ว</div>";
        }
    } else {
        $message = "<div class='alert error'><i class='fas fa-times-circle'></i> กรุณากรอกชื่อสถานะ</div>";
    }
}

// --- 2. ลบข้อมูล (Delete) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM master_job_status WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='alert success'><i class='fas fa-trash-alt'></i> ลบข้อมูลเรียบร้อย</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสถานะงาน - TJC System</title>

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php include 'Logowab.php'; ?>

    <style>
        /* จัด Layout (สีให้ Global CSS คุม) */
        .main-content {
            padding: 30px 20px;
            transition: all 0.3s;
        }

        .container-card {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
            background-color: var(--bg-card);
            /* รับค่าจาก Theme */
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        /* Header */
        .card-header {
            background-color: var(--hover-bg);
            /* สีพื้นหลังส่วนหัว */
            border-bottom: 1px solid var(--border-color);
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color) !important;
            /* สีตัวอักษร */
        }

        .card-body {
            padding: 30px;
        }

        /* Form */
        .form-box {
            background-color: var(--bg-input);
            /* พื้นหลังฟอร์ม */
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        .form-control label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main) !important;
            font-size: 1rem;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-sizing: border-box;
            font-family: 'Prompt', sans-serif;
            font-size: 1rem;
            color: var(--text-main) !important;
            background-color: var(--bg-card) !important;
            /* พื้นหลังช่องกรอก */
            transition: 0.3s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .input-group {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .form-control {
            flex: 1;
            min-width: 250px;
        }

        /* Buttons */
        .btn {
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Prompt', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            height: 48px;
            white-space: nowrap;
            transition: 0.2s;
        }

        .btn-save {
            background: var(--primary-color);
            color: white !important;
        }

        .btn-save:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: #fef08a;
            color: #a16207 !important;
            padding: 6px 15px;
            font-size: 0.9rem;
            height: auto;
            border: 1px solid #fde047;
            margin-right: 5px;
        }

        .btn-edit:hover {
            background: #eab308;
            color: white !important;
        }

        .btn-delete {
            background: #fee2e2;
            color: #ef4444 !important;
            padding: 6px 15px;
            font-size: 0.9rem;
            height: auto;
            border: 1px solid #fca5a5;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: white !important;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        th {
            background-color: var(--hover-bg) !important;
            color: var(--text-muted) !important;
            font-weight: 700;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main) !important;
            vertical-align: middle;
        }

        tr:hover td {
            background-color: var(--hover-bg);
        }

        .col-id {
            width: 60px;
            text-align: center;
            color: var(--text-muted) !important;
            font-weight: bold;
        }

        .col-action {
            width: 160px;
            text-align: right;
            white-space: nowrap;
        }

        /* Alerts */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert.warning {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-card">
            <div class="card-header">
                <h2><i class="fas fa-chart-pie"></i> จัดการสถานะงาน (Job Status)</h2>
            </div>

            <div class="card-body">
                <?php echo $message; ?>

                <div class="form-box">
                    <?php if ($edit_id > 0): ?>
                        <form method="post" action="?edit=<?php echo $edit_id; ?>">
                            <div class="input-group">
                                <div class="form-control">
                                    <label for="status_name">แก้ไขสถานะงาน</label>
                                    <input type="text" id="status_name" name="status_name"
                                        value="<?php echo htmlspecialchars($edit_status_name); ?>" required>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-save"
                                    style="background: #eab308;">
                                    <i class="fas fa-save"></i> อัปเดตข้อมูล
                                </button>
                                <a href="StatusManager.php" class="btn btn-delete"
                                    style="height: 48px; border: none; background: #e5e7eb; color: #374151 !important; display: inline-flex; align-items: center;">
                                    ยกเลิก
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <div class="input-group">
                                <div class="form-control">
                                    <label for="status_name">เพิ่มสถานะงานใหม่</label>
                                    <input type="text" id="status_name" name="status_name"
                                        placeholder="ระบุชื่อสถานะ (เช่น สนใจ, ปิดการขาย)" required>
                                </div>
                                <button type="submit" name="add_status" class="btn btn-save">
                                    <i class="fas fa-plus-circle"></i> บันทึก
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-id">#</th>
                                <th>ชื่อสถานะงาน</th>
                                <th style="text-align:right;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = $conn->query("SELECT * FROM master_job_status ORDER BY id ASC");

                            if ($result->num_rows > 0) {
                                $i = 1;
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td class='col-id'>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</td>";
                                    echo "<td>{$row['status_name']}</td>";
                                    echo "<td class='col-action'>
                                            <a href='?edit={$row['id']}' class='btn btn-edit'>
                                                <i class='fas fa-edit'></i> แก้ไข
                                            </a>
                                            <a href='?delete={$row['id']}' 
                                               class='btn btn-delete' 
                                               onclick=\"return confirm('คุณแน่ใจหรือไม่ที่จะลบสถานะ: {$row['status_name']} ?');\">
                                               <i class='fas fa-trash'></i> ลบ
                                            </a>
                                          </td>";
                                    echo "</tr>";
                                    $i++;
                                }
                            } else {
                                echo "<tr><td colspan='3' style='text-align:center; padding: 40px; color: var(--text-muted);'>
                                    <i class='fas fa-inbox' style='font-size:3rem; margin-bottom:10px; display:block;'></i>
                                    ยังไม่มีข้อมูลสถานะในระบบ
                                </td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 20px; text-align: right; color: var(--text-muted); font-size: 0.85rem;">
                    Total Status: <?php echo isset($i) ? ($i - 1) : 0; ?> items
                </div>
            </div>
        </div>
    </div>

</body>

</html>