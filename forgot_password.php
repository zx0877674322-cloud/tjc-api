<?php
    session_start();
    require_once 'db_connect.php'; 

    $step = 1; // 1=หาUser, 2=ยืนยันรหัสเก่า+ตั้งใหม่, 3=สำเร็จ
    $error = '';
    $success = '';
    $found_user_id = '';
    $found_fullname = '';

    // กรณีมีการส่งข้อมูลมา (POST)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // --- ส่วนที่ 1: ตรวจสอบ Username ---
        if (isset($_POST['action']) && $_POST['action'] == 'verify_user') {
            $username = trim($_POST['username']);
            
            if (empty($username)) {
                $error = "กรุณากรอกชื่อผู้ใช้";
            } else {
                $sql = "SELECT id, fullname FROM users WHERE username = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $stmt->store_result();
                    
                    if ($stmt->num_rows > 0) {
                        $stmt->bind_result($found_user_id, $found_fullname);
                        $stmt->fetch();
                        $step = 2; // เจอ User -> ไปขั้นตอนยืนยันรหัสเก่า
                    } else {
                        $error = "ไม่พบชื่อผู้ใช้นี้ในระบบ";
                    }
                    $stmt->close();
                } else {
                    $error = "Database Error";
                }
            }
        }

        // --- ส่วนที่ 2: ตรวจสอบรหัสเก่า และ บันทึกรหัสใหม่ ---
        if (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
            $user_id = $_POST['user_id'];
            $old_pass = $_POST['old_password']; // รับรหัสผ่านเก่า
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];
            
            // รับค่าชื่อมาแสดงผลต่อ
            $found_fullname = isset($_POST['fullname_hidden']) ? $_POST['fullname_hidden'] : 'ผู้ใช้งาน'; 

            if (empty($user_id)) {
                $error = "ไม่พบรหัสผู้ใช้งาน กรุณาเริ่มใหม่";
                $step = 1;
            } elseif (empty($old_pass)) {
                $error = "กรุณากรอกรหัสผ่านเดิมเพื่อยืนยันตัวตน";
                $step = 2;
                $found_user_id = $user_id; // คงค่า ID ไว้
            } elseif ($new_pass !== $confirm_pass) {
                $error = "รหัสผ่านใหม่ไม่ตรงกัน";
                $step = 2;
                $found_user_id = $user_id;
            } else {
                // 1. ตรวจสอบรหัสผ่านเก่าก่อน
                $check_sql = "SELECT password FROM users WHERE id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $res = $check_stmt->get_result();
                $user_data = $res->fetch_assoc();
                
                // ตรวจสอบรหัสผ่าน (รองรับทั้ง Hash และ Plain Text ตามระบบเดิม)
                $is_password_correct = false;
                if (password_verify($old_pass, $user_data['password'])) {
                    $is_password_correct = true; // แบบ Hash
                } elseif ($old_pass === $user_data['password']) {
                    $is_password_correct = true; // แบบธรรมดา (ถ้ามี)
                }

                if ($is_password_correct) {
                    // --- รหัสเก่าถูก! ทำการเปลี่ยนรหัสใหม่ ---
                    
                    // Hash รหัสใหม่ก่อนบันทึก (แนะนำ)
                    // $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT); 
                    
                    // *หมายเหตุ: ถ้าระบบเดิมไม่ได้ Hash ให้ใช้ $new_pass ตรงๆ
                    // แต่เพื่อความปลอดภัยควร Hash ครับ ในที่นี้สมมติว่าระบบเดิมรับค่าตรงๆ หรือคุณจะแก้ให้ Hash ก็ได้
                    $final_pass = $new_pass; // หรือ password_hash($new_pass, PASSWORD_DEFAULT);

                    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                    $up_stmt = $conn->prepare($update_sql);
                    $up_stmt->bind_param("si", $final_pass, $user_id);
                    
                    if ($up_stmt->execute()) {
                        $success = "เปลี่ยนรหัสผ่านสำเร็จ!";
                        $step = 3;
                    } else {
                        $error = "เกิดข้อผิดพลาดในการอัปเดต";
                        $step = 2;
                        $found_user_id = $user_id;
                    }
                } else {
                    $error = "❌ รหัสผ่านเดิมไม่ถูกต้อง!";
                    $step = 2;
                    $found_user_id = $user_id;
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปลี่ยนรหัสผ่าน - TJC System</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'Logowab.php'; ?>

    <style>
        /* CSS เดิม */
        :root { --primary-color: #4e54c8; --secondary-color: #8f94fb; --text-color: #333; --error-color: #dc3545; --success-color: #15803d; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Prompt', sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); color: var(--text-color); }
        .login-card { background: #ffffff; width: 100%; max-width: 400px; padding: 45px 35px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; animation: fadeInUp 0.6s ease forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        h2 { color: #333; font-weight: 600; margin-bottom: 10px; font-size: 24px; }
        .subtitle { color: #666; font-size: 14px; margin-bottom: 25px; }
        .input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .input-icon-left { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #ccc; pointer-events: none; }
        .input-field { width: 100%; padding: 14px 45px; border: 1px solid #e1e1e1; background: #f9f9f9; border-radius: 10px; font-family: 'Prompt', sans-serif; font-size: 16px; outline: none; transition: all 0.3s; }
        .input-field:focus { background: #fff; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(78, 84, 200, 0.1); }
        .btn-login { width: 100%; padding: 14px; border: none; border-radius: 10px; background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(78, 84, 200, 0.4); }
        .btn-back { display: inline-block; margin-top: 20px; color: #666; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .btn-back:hover { color: var(--primary-color); }
        .error-message { background: #fff5f5; color: var(--error-color); padding: 10px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; border: 1px solid #ffcccc; }
        .success-icon { font-size: 3.5rem; color: var(--success-color); margin-bottom: 15px; }
        .user-badge { background: #eef2ff; color: var(--primary-color); padding: 8px 15px; border-radius: 50px; font-size: 0.9rem; margin-bottom: 20px; display: inline-block; font-weight: 600; }
    </style>
</head>
<body>

    <div class="login-card">
        
        <?php if ($step == 1): ?>
            <div style="margin-bottom: 20px;">
                <i class="fas fa-search" style="font-size: 3rem; color: var(--secondary-color);"></i>
            </div>
            <h2>เปลี่ยนรหัสผ่าน</h2>
            <p class="subtitle">ระบุชื่อผู้ใช้ของคุณ (Username)</p>

            <?php if ($error): ?> <div class="error-message"><?php echo $error; ?></div> <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="verify_user">
                <div class="input-group">
                    <input type="text" name="username" class="input-field" placeholder="ชื่อผู้ใช้" required autofocus>
                    <i class="fas fa-user input-icon-left"></i>
                </div>
                <button type="submit" class="btn-login">ถัดไป</button>
            </form>
            <a href="login.php" class="btn-back"><i class="fas fa-arrow-left"></i> ยกเลิก</a>

        <?php elseif ($step == 2): ?>
            <div style="margin-bottom: 15px;">
                <i class="fas fa-key" style="font-size: 3rem; color: var(--primary-color);"></i>
            </div>
            <h2>ยืนยันตัวตน</h2>
            <div class="user-badge"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($found_fullname); ?></div>
            <p class="subtitle">กรุณากรอกรหัสผ่านเดิมให้ถูกต้อง</p>

            <?php if ($error): ?> <div class="error-message"><?php echo $error; ?></div> <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?php echo $found_user_id; ?>">
                <input type="hidden" name="fullname_hidden" value="<?php echo htmlspecialchars($found_fullname); ?>">

                <div class="input-group">
                    <input type="password" name="old_password" class="input-field" placeholder="รหัสผ่านเดิม (Current Password)" required autofocus style="border-color:#8f94fb;">
                    <i class="fas fa-lock input-icon-left" style="color:#4e54c8;"></i>
                </div>
                
                <hr style="border:0; border-top:1px dashed #ddd; margin: 15px 0;">

                <div class="input-group">
                    <input type="password" name="new_password" class="input-field" placeholder="รหัสผ่านใหม่ที่ต้องการ" required>
                    <i class="fas fa-key input-icon-left"></i>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" class="input-field" placeholder="ยืนยันรหัสผ่านใหม่" required>
                    <i class="fas fa-check-circle input-icon-left"></i>
                </div>
                
                <button type="submit" class="btn-login">ยืนยันการเปลี่ยนรหัส</button>
            </form>
            <a href="forgot_password.php" class="btn-back">ยกเลิก</a>

        <?php elseif ($step == 3): ?>
            <div class="success-icon"><i class="fas fa-check-circle"></i></div>
            <h2 style="color: var(--success-color);">สำเร็จ!</h2>
            <p class="subtitle" style="margin-bottom: 30px;">เปลี่ยนรหัสผ่านเรียบร้อยแล้ว</p>
            
            <a href="login.php" class="btn-login" style="text-decoration: none; display: inline-block;">
                เข้าสู่ระบบใหม่
            </a>
        <?php endif; ?>

    </div>

</body>
</html>