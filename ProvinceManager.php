<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    exit();
}

$message = "";

// --- 1. เพิ่มข้อมูล (Insert) ---
if (isset($_POST['add_province'])) {
    $region_name = $_POST['region_name'];
    $name_th = trim($_POST['name_th']);
    
    if (!empty($region_name) && !empty($name_th)) {
        // เช็คว่ามีจังหวัดนี้ในภาคนี้หรือยัง
        $check = $conn->query("SELECT id FROM master_provinces WHERE name_th = '$name_th' AND region_name = '$region_name'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO master_provinces (region_name, name_th) VALUES (?, ?)");
            $stmt->bind_param("ss", $region_name, $name_th);
            if ($stmt->execute()) {
                $message = "<div class='alert success'><i class='fas fa-check-circle'></i> เพิ่มจังหวัด '$name_th' เรียบร้อย</div>";
            } else {
                $message = "<div class='alert error'><i class='fas fa-times-circle'></i> Error: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert error'><i class='fas fa-exclamation-triangle'></i> มีจังหวัดนี้ในระบบแล้ว</div>";
        }
    } else {
        $message = "<div class='alert error'>⚠️ กรุณากรอกข้อมูลให้ครบ</div>";
    }
}

// --- 2. ลบข้อมูล (Delete) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM master_provinces WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='alert success'><i class='fas fa-trash-alt'></i> ลบข้อมูลเรียบร้อย</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการจังหวัด - TJC System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* จัด Layout (สีให้ Global CSS คุม) */
        .main-content { padding: 30px; }
        .container { max-width: 1000px; margin: 0 auto; }

        /* Header */
        .page-header { margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .page-header h2 { margin: 0; color: var(--text-main); font-size: 1.8rem; }
        .page-icon { 
            width: 50px; height: 50px; background: var(--hover-bg); 
            color: var(--primary-color); border-radius: 12px; 
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        }

        /* Cards */
        .card {
            background-color: var(--bg-card);
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 30px;
        }
        .card-header {
            padding: 20px 25px;
            background-color: var(--hover-bg);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600; font-size: 1.1rem; color: var(--primary-color);
            display: flex; align-items: center; gap: 10px;
        }
        .card-body { padding: 25px; }

        /* Forms */
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-main); }
        .form-control, .form-select {
            width: 100%; padding: 12px 15px; border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-input); color: var(--text-main);
            font-family: 'Prompt'; font-size: 1rem; box-sizing: border-box; transition: 0.3s;
        }
        .form-control:focus, .form-select:focus { outline: none; border-color: var(--primary-color); }

        /* Buttons */
        .btn-primary {
            background: var(--primary-color); color: white !important; border: none;
            padding: 12px 25px; border-radius: 10px; cursor: pointer;
            font-weight: 600; font-size: 1rem; transition: 0.2s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }

        .btn-danger {
            background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5;
            padding: 6px 12px; border-radius: 8px; cursor: pointer;
            font-size: 0.9rem; transition: 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-danger:hover { background: #ef4444; color: white; }

        /* Alerts */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 10px; font-weight: 500; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { 
            padding: 15px; text-align: left; background-color: var(--hover-bg);
            color: var(--text-muted); font-weight: 600; border-bottom: 2px solid var(--border-color);
        }
        td { 
            padding: 15px; vertical-align: middle; 
            border-bottom: 1px solid var(--border-color); color: var(--text-main);
        }
        tr:last-child td { border-bottom: none; }
        
        .badge-region {
            background: var(--bg-input); border: 1px solid var(--border-color);
            padding: 4px 10px; border-radius: 6px;
            color: var(--primary-color); font-weight: 600; font-size: 0.9rem;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .form-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            
            <div class="page-header">
                <div class="page-icon"><i class="fas fa-map-marked-alt"></i></div>
                <div>
                    <h2>จัดการข้อมูลจังหวัด</h2>
                    <p style="margin:5px 0 0 0; color:var(--text-muted);">เพิ่ม ลบ แก้ไข รายชื่อจังหวัดและภาคในระบบ</p>
                </div>
            </div>

            <?php echo $message; ?>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i> เพิ่มจังหวัดใหม่
                </div>
                <div class="card-body">
                    <form method="post">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;" class="form-row">
                            <div class="form-group">
                                <label>เลือกภาค (Region)</label>
                                <select name="region_name" class="form-select" required>
                                    <option value="">-- เลือกภาค --</option>
                                    <option value="ภาคเหนือ">ภาคเหนือ</option>
                                    <option value="ภาคอีสาน">ภาคอีสาน</option>
                                    <option value="ภาคกลาง">ภาคกลาง</option>
                                    <option value="ภาคใต้">ภาคใต้</option>
                                    <option value="ภาคตะวันออก">ภาคตะวันออก</option>
                                    <option value="ภาคตะวันตก">ภาคตะวันตก</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>ชื่อจังหวัด (ภาษาไทย)</label>
                                <input type="text" name="name_th" class="form-control" placeholder="เช่น เชียงใหม่, ขอนแก่น" required>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px;">
                            <button type="submit" name="add_province" class="btn-primary">
                                <i class="fas fa-save"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list-ul"></i> รายชื่อจังหวัดในระบบ
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="10%">ลำดับ</th> 
                                <th width="30%">ภาค (Region)</th>
                                <th>ชื่อจังหวัด</th>
                                <th width="15%" style="text-align:center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = $conn->query("SELECT * FROM master_provinces ORDER BY region_name ASC, name_th ASC");
                            
                            if ($result->num_rows > 0) {
                                $i = 1;
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>{$i}</td>"; 
                                    echo "<td><span class='badge-region'>{$row['region_name']}</span></td>";
                                    echo "<td style='font-weight:600;'>{$row['name_th']}</td>";
                                    echo "<td style='text-align:center;'>
                                            <a href='?delete={$row['id']}' class='btn-danger' onclick=\"return confirm('ยืนยันที่จะลบจังหวัด {$row['name_th']}?');\">
                                                <i class='fas fa-trash-alt'></i> ลบ
                                            </a>
                                          </td>";
                                    echo "</tr>";
                                    $i++;
                                }
                            } else {
                                echo "<tr><td colspan='4' style='text-align:center; padding:30px; color:var(--text-muted);'>ยังไม่มีข้อมูลจังหวัด</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</body>
</html>