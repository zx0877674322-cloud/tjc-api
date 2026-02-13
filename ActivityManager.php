<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

$message = "";

// --- 1. เพิ่มข้อมูล (Insert) ---
if (isset($_POST['add_activity'])) {
    $activity_name = trim($_POST['activity_name']);
    
    if (!empty($activity_name)) {
        $stmt_check = $conn->prepare("SELECT id FROM master_activities WHERE activity_name = ?");
        $stmt_check->bind_param("s", $activity_name);
        $stmt_check->execute();
        $check = $stmt_check->get_result();

        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO master_activities (activity_name) VALUES (?)");
            $stmt->bind_param("s", $activity_name);
            if ($stmt->execute()) {
                $message = "<div class='alert success'><i class='fas fa-check-circle'></i> เพิ่มกิจกรรมสำเร็จ</div>";
            } else {
                $message = "<div class='alert error'>Error: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert warning'><i class='fas fa-exclamation-circle'></i> ชื่อกิจกรรมนี้มีอยู่แล้ว</div>";
        }
    } else {
        $message = "<div class='alert error'>⚠️ กรุณากรอกชื่อกิจกรรม</div>";
    }
}

// --- 2. ลบข้อมูล (Delete) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM master_activities WHERE id = ?");
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
    <title>จัดการกิจกรรม - TJC System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php include 'Logowab.php'; ?>
    
    <style>
        /* จัด Layout (สีให้ Global CSS คุม) */
        .main-content { padding: 30px 20px; transition: all 0.3s; }
        .container-card { 
            width: 100%; max-width: 850px; margin: 0 auto;
            background-color: var(--bg-card); /* รับค่าจาก Theme */
            border-radius: 20px; 
            box-shadow: var(--shadow); 
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        /* Header */
        .card-header {
            background-color: var(--hover-bg); /* สีพื้นหลังส่วนหัว */
            border-bottom: 1px solid var(--border-color);
            padding: 20px 25px; display: flex; align-items: center; gap: 10px;
        }
        .card-header h2 { 
            margin: 0; font-size: 1.4rem; font-weight: 600; 
            color: var(--primary-color) !important; /* สีตัวอักษร */
        }

        .card-body { padding: 30px; }

        /* Form */
        .form-box { 
            background-color: var(--bg-input); /* พื้นหลังฟอร์ม */
            padding: 25px; border-radius: 12px; margin-bottom: 25px; 
            border: 1px solid var(--border-color);
        }
        
        .form-control label { 
            display: block; font-weight: 600; margin-bottom: 8px; 
            color: var(--text-main) !important; font-size: 1rem;
        }
        
        input[type="text"] { 
            width: 100%; padding: 12px 15px; 
            border: 1px solid var(--border-color); 
            border-radius: 8px; box-sizing: border-box; 
            font-family: 'Prompt', sans-serif; font-size: 1rem;
            color: var(--text-main) !important;
            background-color: var(--bg-card) !important; /* พื้นหลังช่องกรอก */
            transition: 0.3s;
        }
        input[type="text"]:focus { outline: none; border-color: var(--primary-color); }

        .input-group { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-control { flex: 1; min-width: 250px; }

        /* Buttons */
        .btn {
            padding: 10px 30px; border: none; border-radius: 8px; cursor: pointer;
            font-family: 'Prompt', sans-serif; font-weight: 600; font-size: 1rem;
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            height: 48px; white-space: nowrap; transition: 0.2s;
        }
        .btn-save { 
            background: var(--primary-color); color: white !important; 
        }
        .btn-save:hover { opacity: 0.9; transform: translateY(-2px); }
        
        .btn-delete { 
            background: #fee2e2; color: #ef4444 !important; 
            padding: 6px 15px; font-size: 0.9rem; height: auto; 
            border: 1px solid #fca5a5;
        }
        .btn-delete:hover { background: #ef4444; color: white !important; }

        /* Table */
        .table-responsive {
            overflow-x: auto; border-radius: 8px; border: 1px solid var(--border-color);
        }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        
        th { 
            background-color: var(--hover-bg) !important;
            color: var(--text-muted) !important;
            font-weight: 700; padding: 15px; text-align: left;
            border-bottom: 2px solid var(--border-color);
        }
        
        td { 
            padding: 15px; border-bottom: 1px solid var(--border-color); 
            color: var(--text-main) !important; vertical-align: middle;
        }
        tr:hover td { background-color: var(--hover-bg); }
        
        .col-id { width: 60px; text-align: center; color: var(--text-muted) !important; font-weight: bold; }
        .col-action { width: 100px; text-align: right; }

        /* Alerts */
        .alert { 
            padding: 15px; margin-bottom: 20px; border-radius: 8px; 
            display: flex; align-items: center; gap: 10px; font-weight: 500;
        }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert.warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-card">
            <div class="card-header">
                <h2><i class="fas fa-tasks"></i> จัดการประเภทกิจกรรม</h2>
            </div>

            <div class="card-body">
                <?php echo $message; ?>

                <div class="form-box">
                    <form method="post">
                        <div class="input-group">
                            <div class="form-control">
                                <label for="act_name">เพิ่มกิจกรรมใหม่</label>
                                <input type="text" id="act_name" name="activity_name" 
                                       placeholder="ระบุชื่อกิจกรรม (เช่น ส่งสินค้า, เก็บเช็ค)" required>
                            </div>
                            <button type="submit" name="add_activity" class="btn btn-save">
                                <i class="fas fa-plus-circle"></i> บันทึก
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-id">#</th>
                                <th>ชื่อกิจกรรม</th>
                                <th style="text-align:right;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = $conn->query("SELECT * FROM master_activities ORDER BY id ASC");
                            
                            if ($result->num_rows > 0) {
                                $i = 1;
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td class='col-id'>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</td>"; 
                                    echo "<td>{$row['activity_name']}</td>";
                                    echo "<td class='col-action'>
                                            <a href='?delete={$row['id']}' 
                                               class='btn btn-delete' 
                                               onclick=\"return confirm('ยืนยันการลบ?');\">
                                               <i class='fas fa-trash'></i> ลบ
                                            </a>
                                          </td>";
                                    echo "</tr>";
                                    $i++;
                                }
                            } else {
                                echo "<tr><td colspan='3' style='text-align:center; padding: 30px; color: var(--text-muted);'>
                                    ยังไม่มีข้อมูลกิจกรรม
                                </td></tr>";
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