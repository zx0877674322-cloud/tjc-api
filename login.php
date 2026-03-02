<?php
// 1. ตั้งค่าการแสดง Error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. เริ่มต้น Session
session_start();

// 3. ถ้าล็อกอินอยู่แล้ว ให้ส่งไปหน้าประกาศทันที
if (isset($_SESSION['fullname'])) {
    header("Location: Announcement.php");
    exit();
}

// 4. เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

$error = '';

// 5. ตรวจสอบการกดปุ่ม Submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = trim($_POST['username']);
    $pass_input = $_POST['password'];

    // ตรวจสอบการเชื่อมต่อ
    if (!isset($conn) || $conn->connect_error) {
        $error = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . (isset($conn) ? $conn->connect_error : "ตัวแปร \$conn ไม่ถูกสร้าง");
    } else {
        // SQL Query: เช็คชื่อและรหัสผ่าน
        $sql = "SELECT id, fullname, role, avatar FROM users WHERE username = ? AND password = ?";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $error = "SQL Error: " . $conn->error;
        } else {
            // Bind parameters
            $stmt->bind_param("ss", $user_input, $pass_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // เจอผู้ใช้: เก็บ Session
                $row = $result->fetch_assoc();
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['fullname'] = $row['fullname'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['avatar'] = $row['avatar'];

                $stmt->close();

                // ส่งไปหน้าประกาศข่าว (แก้ไขตาม Request)
                header("Location: boss dashboard.php");
                exit();
            } else {
                $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
            $stmt->close();
        }
    }
}

// ปิดการเชื่อมต่อ
if (isset($conn) && !empty($conn))
    $conn->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - TJC System</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'Logowab.php'; ?>
    <style>
        /* --- CSS เดิม (แบบ Clean) --- */
        :root {
            --primary-color: #4e54c8;
            --secondary-color: #8f94fb;
            --text-color: #333;
            --error-color: #dc3545;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Prompt', sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-color);
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            padding: 45px 35px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-img {
            width: 120px;
            margin-bottom: 20px;
        }

        h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 26px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-icon-left {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
            font-size: 18px;
            pointer-events: none;
            transition: color 0.3s;
        }

        .input-field {
            width: 100%;
            padding: 14px 45px;
            border: 1px solid #e1e1e1;
            background: #f9f9f9;
            border-radius: 10px;
            font-family: 'Prompt', sans-serif;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }

        .input-field:focus {
            background: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(78, 84, 200, 0.1);
        }

        .input-field:focus~.input-icon-left {
            color: var(--primary-color);
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #ccc;
            font-size: 16px;
            transition: color 0.3s;
            z-index: 5;
        }

        .toggle-password:hover {
            color: var(--primary-color);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Prompt', sans-serif;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(78, 84, 200, 0.3);
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(78, 84, 200, 0.4);
        }

        .error-message {
            background: #fff5f5;
            color: var(--error-color);
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid #ffcccc;
            display: flex;
            align-items: center;
        }

        /* --- CSS ที่เพิ่มใหม่สำหรับลิงก์ลืมรหัสผ่าน --- */
        .forgot-password-link {
            display: block;
            text-align: right;
            margin-top: -10px;
            /* ขยับขึ้นไปชิด input */
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
            text-decoration: none;
            transition: color 0.3s;
        }

        .forgot-password-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
    </style>
</head>

<body>


    <div class="login-card">
        <div class="logo-container">
            <img src="images/S__49692692.jpg" alt="TJC Logo" class="logo-img">
        </div>

        <h2>เข้าสู่ระบบ</h2>
        <p class="subtitle">One System One Management</p>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <input type="text" name="username" class="input-field" placeholder="ชื่อผู้ใช้" required
                    autocomplete="off" value="<?php echo isset($user_input) ? htmlspecialchars($user_input) : ''; ?>">
                <i class="fas fa-user input-icon-left"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" class="input-field" id="passwordField" placeholder="รหัสผ่าน"
                    required>
                <i class="fas fa-lock input-icon-left"></i>
                <i class="fas fa-eye toggle-password" id="toggleBtn"></i>
            </div>

            <a href="forgot_password.php" class="forgot-password-link">เปลี่ยนรหัสผ่าน?</a>

            <button type="submit" class="btn-login">
                เข้าใช้งานระบบ
            </button>
        </form>
    </div>

    <script>
        const toggleBtn = document.getElementById('toggleBtn');
        const passwordField = document.getElementById('passwordField');

        toggleBtn.addEventListener('click', function () {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>

</body>

</html>