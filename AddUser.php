<?php
session_start();
require_once 'auth.php'; 
require_once 'db_connect.php';
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

$message = "";

// --- ส่วนลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $conn->query("DELETE FROM users WHERE id = $del_id");
    $message = "<div class='alert success'><div class='icon-box'><i class='fas fa-check'></i></div><div>ลบข้อมูลสำเร็จ</div></div>";
}

// --- ส่วนบันทึกข้อมูล ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']); 
    $fname = trim($_POST['fullname']);
    $role = $_POST['role'];
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : ''; // รับค่าเบอร์โทร
    $comp_id = !empty($_POST['company_id']) ? $_POST['company_id'] : NULL;
    
    $edit_id = $_POST['edit_id'] ?? '';

    if (!empty($edit_id)) {
        // --- กรณีแก้ไข ---
        if (!empty($pass)) {
            $stmt = $conn->prepare("UPDATE users SET fullname=?, username=?, password=?, role=?, phone=?, company_id=? WHERE id=?");
            $stmt->bind_param("sssssii", $fname, $user, $pass, $role, $phone, $comp_id, $edit_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET fullname=?, username=?, role=?, phone=?, company_id=? WHERE id=?");
            $stmt->bind_param("ssssii", $fname, $user, $role, $phone, $comp_id, $edit_id);
        }
        
        if($stmt->execute()){
            $message = "<div class='alert success'><div class='icon-box'><i class='fas fa-check'></i></div><div>อัปเดตข้อมูลสำเร็จ</div></div>";
        } else {
            $message = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    } else {
        // --- กรณีเพิ่มใหม่ ---
        $chk = $conn->query("SELECT id FROM users WHERE username = '$user'");
        if ($chk->num_rows > 0) {
            $message = "<div class='alert error'><div class='icon-box'><i class='fas fa-exclamation'></i></div><div>Username ซ้ำ</div></div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, role, phone, company_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $user, $pass, $fname, $role, $phone, $comp_id);
            
            if($stmt->execute()){
                $message = "<div class='alert success'><div class='icon-box'><i class='fas fa-check'></i></div><div>เพิ่มข้อมูลสำเร็จ</div></div>";
            } else {
                $message = "<div class='alert error'>Error: " . $conn->error . "</div>";
            }
        }
    }
}

// 🔥 [แก้ไขจุดที่ 1] เปลี่ยน company_code เป็น company_shortname ให้ตรงกับ Database
$sql_users = "SELECT u.*, c.company_name, c.company_shortname 
              FROM users u 
              LEFT JOIN companies c ON u.company_id = c.id 
              ORDER BY u.id ASC";
$users_result = $conn->query($sql_users);

// เช็ค Error ถ้า SQL พัง
if (!$users_result) {
    die("SQL Error: " . $conn->error);
}

$companies_opt = $conn->query("SELECT * FROM companies ORDER BY id ASC"); 
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* (Style เดิมคงไว้) */
        :root { 
            --bg-body: #f8f9fa; --bg-card: #ffffff; --bg-input: #f3f4f6;
            --text-main: #2b2d42; --text-sub: #64748b; --border-color: #e2e8f0;
            --primary: #4361ee; --shadow: 0 4px 15px rgba(0,0,0,0.05);
            --table-head-bg: #f8fafc; --table-hover: #f8faff;
        }
        [data-theme="dark"] {
            --bg-body: #0f172a; --bg-card: #1e293b; --bg-input: #334155;      
            --text-main: #f8fafc; --text-sub: #cbd5e1; --border-color: #475569;
            --shadow: 0 4px 15px rgba(0,0,0,0.3); --table-head-bg: #1e293b; --table-hover: #334155;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Prompt', sans-serif; background-color: var(--bg-body); color: var(--text-main); margin: 0; padding: 0; transition: 0.3s; min-height: 100vh; }
        .navbar { background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; color: white; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-actions { display: flex; gap: 10px; }
        .btn-theme-toggle { background: rgba(255,255,255,0.2); border: none; color: white; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .btn-back { background: rgba(255,255,255,0.15); color: white; padding: 8px 20px; border-radius: 50px; text-decoration: none; font-size: 14px; border: 1px solid rgba(255,255,255,0.2); }
        .main-container { display: flex; gap: 24px; max-width: 1400px; margin: 30px auto; padding: 0 24px; align-items: flex-start; }
        .card { background: var(--bg-card); padding: 24px; border-radius: 16px; box-shadow: var(--shadow); border: 1px solid var(--border-color); }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 16px; margin-bottom: 24px; }
        .card-title { color: var(--primary); margin: 0; font-size: 18px; font-weight: 600; }
        .btn-expand { background: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-sub); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; }
        .form-section { flex: 1; min-width: 300px; position: sticky; top: 100px; }
        .table-section { flex: 2.5; display: flex; flex-direction: column; }
        .hidden-card { display: none !important; }
        .full-width-card { flex: 1 1 100% !important; max-width: 100% !important; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; font-size: 14px; }
        .form-group input:focus { border-color: var(--primary); outline: none; }
        .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 12px; border-radius: 10px; cursor: pointer; font-weight: 600; margin-top: 10px; }
        .btn-cancel { width: 100%; background: var(--bg-input); color: var(--text-sub); border: 1px solid var(--border-color); padding: 10px; border-radius: 10px; margin-top: 10px; cursor: pointer; display: none; }
        .table-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: var(--table-head-bg) !important; color: var(--text-sub) !important; padding: 15px; text-align: left; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        [data-theme="dark"] th { background-color: #334155 !important; color: #f8fafc !important; border-bottom: 2px solid #475569 !important; }
        td { padding: 15px; border-bottom: 1px solid var(--border-color); font-size: 14px; vertical-align: middle; }
        tr:hover td { background-color: var(--table-hover); }
        [data-theme="dark"] tr:hover td { background-color: #1e293b; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .role-admin { background: #dcfce7; color: #166534; } .role-staff { background: #dbeafe; color: #1e40af; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        @media (max-width: 900px) { .main-container { flex-direction: column; } .form-section, .table-section { width: 100%; } .btn-expand { display: none; } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="navbar">
        <h2><i class="fas fa-users-cog"></i> จัดการผู้ใช้งาน</h2>
        <div class="nav-actions">
        </div>
    </div>

    <div class="main-container">
        
        <div class="card form-section" id="cardForm">
            <div class="card-header">
                <h3 class="card-title" id="formTitle">เพิ่มพนักงาน</h3>
                <button class="btn-expand" onclick="toggleExpand('form')"><i class="fas fa-expand-alt"></i></button>
            </div>
            <?php echo $message; ?>
            <form method="POST" id="userForm" autocomplete="off">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="form-group">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="fullname" id="fullname" required>
                </div>

                <div class="form-group">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" id="phone" placeholder="เช่น 08x-xxx-xxxx" maxlength="20">
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="username" required autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label>Password (แสดงผล)</label>
                    <input type="text" name="password" id="password" placeholder="ตั้งรหัสผ่าน..." autocomplete="new-password">
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="role" required>
                        <option value="">-- เลือกสิทธิ์ --</option>
                        <?php 
                        $q = $conn->query("SELECT * FROM master_roles");
                        while($r=$q->fetch_assoc()) echo "<option value='{$r['role_name']}'>{$r['role_name']}</option>";
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>สังกัดบริษัท</label>
                    <select name="company_id" id="company_id">
                        <option value="">-- ไม่ระบุ / ส่วนกลาง --</option>
                        <?php 
                        if ($companies_opt->num_rows > 0) {
                            $companies_opt->data_seek(0);
                            while($comp = $companies_opt->fetch_assoc()) {
                                echo "<option value='{$comp['id']}'>{$comp['company_name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit" id="btnSubmit">บันทึก</button>
                <button type="button" class="btn-cancel" id="btnCancel" onclick="cancelEdit()">ยกเลิก</button>
            </form>
        </div>

        <div class="card table-section" id="cardTable">
            <div class="card-header">
                <h3 class="card-title">รายชื่อพนักงาน</h3>
                <button class="btn-expand" onclick="toggleExpand('table')"><i class="fas fa-expand-alt"></i></button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>เบอร์โทร</th>
                            <th>บริษัท (ย่อ)</th> 
                            <th>Username</th>
                            <th>Password</th>
                            <th>Role</th>
                            <th style="text-align:center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i=1; while($row = $users_result->fetch_assoc()): 
                            $cls = ($row['role']=='admin')?'role-admin':'role-staff';
                            
                            // 🔥 [แก้ไขจุดที่ 2] เรียกใช้ company_shortname
                            if (!empty($row['company_shortname'])) {
                                $show_comp = $row['company_shortname'];
                            } elseif (!empty($row['company_name'])) {
                                $show_comp = $row['company_name'];
                            } else {
                                $show_comp = '-';
                            }
                            
                            $comp_id_val = !empty($row['company_id']) ? $row['company_id'] : '';
                            $show_phone = !empty($row['phone']) ? $row['phone'] : '-';
                        ?>
                        <tr>
                            <td>#<?php echo $i++; ?></td>
                            <td><b><?php echo $row['fullname']; ?></b></td>
                            <td><?php echo $show_phone; ?></td>
                            <td><span style="color:var(--primary); font-weight:500;"><?php echo $show_comp; ?></span></td> 
                            <td><?php echo $row['username']; ?></td>
                            
                            <td style="font-family:monospace; color:var(--primary); font-weight:bold;">
                                <?php echo htmlspecialchars($row['password']); ?>
                            </td>

                            <td><span class="badge <?php echo $cls; ?>"><?php echo ucfirst($row['role']); ?></span></td>
                            <td style="text-align:center;">
                                <button type="button" onclick="editUser(
                                    '<?php echo $row['id']; ?>',
                                    '<?php echo $row['fullname']; ?>',
                                    '<?php echo $row['username']; ?>',
                                    '<?php echo addslashes($row['password']); ?>',
                                    '<?php echo $row['role']; ?>',
                                    '<?php echo $comp_id_val; ?>',
                                    '<?php echo $show_phone != '-' ? $show_phone : ''; ?>' 
                                )" style="border:none; background:none; cursor:pointer; color:#f59e0b; font-size:1.1rem;"><i class="fas fa-pen"></i></button>
                                
                                <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('ลบ?')" style="color:#ef4444; margin-left:10px;"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const body = document.body;
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        }
        if(localStorage.getItem('theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
        }

        function toggleExpand(target) {
            const formCard = document.getElementById('cardForm');
            const tableCard = document.getElementById('cardTable');
            const iconForm = formCard.querySelector('.btn-expand i');
            const iconTable = tableCard.querySelector('.btn-expand i');

            const isFormExpanded = formCard.classList.contains('full-width-card');
            const isTableExpanded = tableCard.classList.contains('full-width-card');

            formCard.classList.remove('hidden-card', 'full-width-card');
            tableCard.classList.remove('hidden-card', 'full-width-card');
            
            iconForm.className = 'fas fa-expand-alt';
            iconTable.className = 'fas fa-expand-alt';

            if ((target === 'form' && isFormExpanded) || (target === 'table' && isTableExpanded)) {
                return; 
            }

            if (target === 'form') {
                formCard.classList.add('full-width-card');
                tableCard.classList.add('hidden-card');
                iconForm.className = 'fas fa-compress-alt';
            } else {
                tableCard.classList.add('full-width-card');
                formCard.classList.add('hidden-card');
                iconTable.className = 'fas fa-compress-alt';
            }
        }

        function editUser(id, name, user, pass, role, compId, phone) {
            document.getElementById('edit_id').value = id;
            document.getElementById('fullname').value = name;
            document.getElementById('username').value = user;
            document.getElementById('role').value = role;
            document.getElementById('password').value = pass;
            document.getElementById('phone').value = phone;
            document.getElementById('company_id').value = compId;
            
            document.getElementById('formTitle').innerText = 'แก้ไขข้อมูล';
            document.getElementById('formTitle').style.color = '#f59e0b';
            document.getElementById('btnSubmit').innerText = 'อัปเดต';
            document.getElementById('btnSubmit').style.background = '#f59e0b';
            document.getElementById('btnCancel').style.display = 'block';

            if (document.getElementById('cardTable').classList.contains('full-width-card')) {
                toggleExpand('table');
            }
        }

        function cancelEdit() {
            document.getElementById('userForm').reset();
            document.getElementById('edit_id').value = '';
            document.getElementById('password').value = ''; 
            document.getElementById('company_id').value = ''; 
            document.getElementById('phone').value = '';

            document.getElementById('formTitle').innerText = 'เพิ่มพนักงาน';
            document.getElementById('formTitle').style.color = '';
            document.getElementById('btnSubmit').innerText = 'บันทึก';
            document.getElementById('btnSubmit').style.background = '';
            document.getElementById('btnCancel').style.display = 'none';
        }
    </script>
</body>
</html>