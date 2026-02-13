<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// เปิด Error Reporting เพื่อดูปัญหา (ปิดได้เมื่อใช้งานจริง)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = $_SESSION['user_id'];
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

require_once 'db_connect.php'; 

$message = "";

// --- ส่วนจัดการอัปโหลดไฟล์ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_img'])) {
    $file = $_FILES['profile_img'];
    
    // เช็คว่ามีไฟล์ถูกส่งมาจริงไหม และไม่มี Error จากฝั่ง Server
    if ($file['error'] == 0) {
        
        // 1. กำหนดนามสกุลไฟล์ที่อนุญาต (เพิ่ม webp แล้ว)
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp']; 
        
        // ดึงนามสกุลไฟล์ออกมาเช็ค
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // สร้างโฟลเดอร์ถ้ายังไม่มี
            $target_dir = __DIR__ . "/uploads/profiles/";
            if (!file_exists($target_dir)) { 
                @mkdir($target_dir, 0777, true); 
            }
            @chmod($target_dir, 0777);
            
            // ตั้งชื่อไฟล์ใหม่ป้องกันชื่อซ้ำ
            $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
            $target_file = $target_dir . $new_name;
            
            // ย้ายไฟล์จาก Temp ไปยังโฟลเดอร์จริง
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // อัปเดตฐานข้อมูล
                $sql_update = "UPDATE users SET avatar = ? WHERE id = ?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("si", $new_name, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['avatar'] = $new_name; // อัปเดต Session
                    $message = "<div class='alert success'>✅ อัปเดตเรียบร้อย</div>";
                    // รีเฟรชหน้าเพื่อให้รูปใหม่แสดงผลทันที
                    echo "<meta http-equiv='refresh' content='1'>"; 
                } else { 
                    $message = "<div class='alert error'>❌ Database Error: " . $conn->error . "</div>"; 
                }
                $stmt->close();
            } else { 
                $message = "<div class='alert error'>❌ Upload Failed (Move error)</div>"; 
            }
        } else { 
            // แจ้งเตือนถ้านามสกุลไฟล์ไม่ถูกต้อง (บอกด้วยว่าเป็นนามสกุลอะไร)
            $message = "<div class='alert error'>⚠️ File type error (ไฟล์ .$ext ไม่รองรับ)</div>"; 
        }
    } else {
        // แจ้งเตือนถ้าไฟล์มีปัญหา (เช่น ไฟล์ใหญ่เกินค่า php.ini)
        // Error Code 1 หรือ 2 มักหมายถึงไฟล์ใหญ่เกินไป
        $message = "<div class='alert error'>⚠️ Upload Error Code: " . $file['error'] . " (อาจเป็นเพราะไฟล์ใหญ่เกินไป)</div>";
    }
}

// --- ดึงข้อมูลผู้ใช้มาแสดง ---
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_user = $stmt->get_result();
$user_data = $result_user->fetch_assoc();

// จัดการรูปภาพที่จะแสดง (ถ้าไม่มีรูป ใช้ UI Avatars)
$avatar_path = "uploads/profiles/" . $user_data['avatar'];
if (!empty($user_data['avatar']) && file_exists(__DIR__ . "/" . $avatar_path)) {
    $avatar_url = $avatar_path . "?t=" . time(); // ใส่ time() เพื่อแก้ปัญหา Cache รูปเก่า
} else {
    $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($user_data['fullname']) . "&background=random&color=fff";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าโปรไฟล์ - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* จัด Layout (สีให้ Global CSS คุม) */
        .main-container {
            /* จัดกึ่งกลางทั้งแนวตั้งและแนวนอน */
            min-height: 100vh; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 40px; 
            box-sizing: border-box;
            width: 100%;
        }

        /* Profile Card */
        .profile-card {
            background-color: var(--bg-card); /* รับค่าจาก Theme */
            width: 100%; 
            max-width: 500px; /* ขยายให้กว้างขึ้นนิดหน่อย */
            padding: 50px 40px;
            border-radius: 24px; 
            box-shadow: var(--shadow); 
            border: 1px solid var(--border-color);
            text-align: center; 
            position: relative;
        }

        /* PC View: ขยับขวาหลบ Sidebar */
        @media (min-width: 992px) {
            .main-container { 
                margin-left: 150px; /* เว้นที่ให้ Sidebar */
                width: calc(100% - 270px); 
            }
        }
        
        /* Mobile View */
        @media (max-width: 991px) {
            .main-container { 
                margin-left: 0;
                padding-top: 100px; /* หลบ Header */
                align-items: flex-start; 
            }
            .profile-card { margin-bottom: 50px; }
        }

        /* Back Button */
        .back-btn {
            position: absolute; top: 20px; left: 20px;
            color: var(--text-muted); text-decoration: none; font-size: 14px;
            background-color: var(--bg-input); padding: 8px 15px; border-radius: 20px;
            transition: 0.3s; display: inline-flex; align-items: center; gap: 5px;
            border: 1px solid var(--border-color);
        }
        .back-btn:hover { background-color: var(--hover-bg); color: var(--primary-color); }

        h2 { margin: 15px 0 5px 0; color: var(--text-main); font-size: 24px; font-weight: 700; }
        
        p.role { 
            color: var(--primary-color); font-size: 14px; margin-bottom: 30px; font-weight: 600; 
            background-color: var(--hover-bg); display: inline-block; padding: 6px 15px; 
            border-radius: 50px; border: 1px solid var(--border-color);
        }

        /* Avatar */
        .avatar-wrapper { position: relative; width: 150px; height: 150px; margin: 0 auto 20px; }
        .avatar-img {
            width: 100%; height: 100%; border-radius: 50%; object-fit: cover;
            border: 5px solid var(--bg-card); box-shadow: var(--shadow);
        }
        .camera-icon {
            position: absolute; bottom: 5px; right: 5px;
            background: var(--primary-color); color: white;
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            cursor: pointer; border: 3px solid var(--bg-card); transition: 0.3s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .camera-icon:hover { transform: scale(1.1); }

        input[type="file"] { display: none; }
        
        /* Form Groups */
        .info-group { text-align: left; margin-bottom: 20px; }
        .info-group label { 
            display: block; font-weight: 600; font-size: 14px; 
            color: var(--text-muted); margin-bottom: 8px; 
        }
        .info-group input { 
            width: 100%; padding: 14px; border: 1px solid var(--border-color); 
            border-radius: 12px; background-color: var(--bg-input) !important; 
            color: var(--text-main) !important; font-family: 'Prompt'; font-size: 1rem;
            box-sizing: border-box; transition: 0.3s;
        }
        .info-group input:focus { border-color: var(--primary-color); outline: none; }

        /* Save Button */
        .btn-save {
            background: var(--primary-color);
            color: white !important; border: none; padding: 16px; border-radius: 12px;
            font-size: 16px; font-weight: 600; cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: 0.3s;
            margin-top: 10px; width: 100%; display: block; font-family: 'Prompt';
        }
        .btn-save:hover { transform: translateY(-2px); opacity: 0.9; box-shadow: 0 6px 20px rgba(0,0,0,0.15); }

        /* Alerts */
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 14px; text-align: left; display: flex; align-items: center; gap: 10px; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-container">
        <div class="profile-card">
   <?php echo $message; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="avatar-wrapper">
                    <img src="<?php echo $avatar_url; ?>" id="preview" class="avatar-img">
                    <label for="fileInput" class="camera-icon">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" name="profile_img" id="fileInput" accept="image/*" onchange="previewImage(event)">
                </div>

                <h2><?php echo htmlspecialchars($user_data['fullname']); ?></h2>
                <p class="role"><i class="fas fa-shield-alt"></i> <?php echo strtoupper($user_data['role']); ?></p>

                <div class="info-group">
                    <label><i class="fas fa-user"></i> ชื่อผู้ใช้ (Username)</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly>
                </div>

                <div class="info-group">
                    <label><i class="fas fa-id-card"></i> ชื่อ-นามสกุล</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_data['fullname']); ?>" readonly>
                </div>

                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> บันทึกรูปโปรไฟล์
                </button>
            </form>
        </div>
    </div>

    <script>
        function previewImage(event) {
            var reader = new FileReader();
            reader.onload = function(){
                var output = document.getElementById('preview');
                output.src = reader.result;
            };
            if(event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }
    </script>

</body>
</html>