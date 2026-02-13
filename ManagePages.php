<?php
session_start();
require_once 'auth.php'; 
require_once 'db_connect.php';

// 🔥 ประกาศตัวแปรเริ่มต้นกัน Error
$message = "";

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    echo "Access Denied"; 
    exit(); 
}

// ==================================================================================
// 📡 ส่วนสแกนไฟล์ (Scanner Logic - แก้ไข Regex แล้ว)
// ==================================================================================
if (isset($_POST['scan_file'])) {
    $filename = trim($_POST['scan_file']);
    $filepath = __DIR__ . '/' . $filename;
    $response = [];

    if (file_exists($filepath)) {
        $content = file_get_contents($filepath);
        
        // ✅ แก้ไข Regex: ให้จับ hasAction('code') แบบสั้นได้
        // ความหมาย: มองหาคำว่า hasAction ตามด้วยวงเล็บเปิด ตามด้วย ' หรือ " และเก็บค่าข้างใน
        preg_match_all("/hasAction\s*\(\s*['\"]([a-zA-Z0-9_]+)['\"]\s*\)/", $content, $matches);
        
        $found_actions = array_unique($matches[1]); // เอาเฉพาะส่วนที่อยู่ในวงเล็บ (Group 1)
        
        if (empty($found_actions)) {
            // ลอง Regex แบบเก่าเผื่อไฟล์เก่าๆ (3 parameters)
            preg_match_all("/hasAction\s*\(\s*.*?,.*?,\s*['\"]([a-zA-Z0-9_]+)['\"]\s*\)/", $content, $matches_old);
            $found_actions = array_unique($matches_old[1]);
        }

        if (empty($found_actions)) {
            echo json_encode(['status' => 'empty', 'message' => 'ไม่พบโค้ด hasAction(\'...\') ในไฟล์นี้']);
            exit();
        }

        foreach ($found_actions as $code) {
            $chk = $conn->query("SELECT id, action_name FROM master_actions WHERE action_code = '$code'");
            $data = $chk->fetch_assoc();
            
            $response[] = [
                'code' => $code,
                'exists' => ($chk->num_rows > 0),
                'id' => ($chk->num_rows > 0) ? $data['id'] : null,
                'name' => ($chk->num_rows > 0) ? $data['action_name'] : ''
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $response]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์: ' . $filename]);
    }
    exit();
}

// ==================================================================================
// 1. บันทึกข้อมูล
// ==================================================================================
if (isset($_POST['save_page'])) {
    $page_id = $_POST['page_id'];
    $page_name = trim($_POST['page_name']);
    $file_name = trim($_POST['file_name']);
    
    // 1.1 บันทึกปุ่มใหม่
    if (isset($_POST['new_actions'])) {
        foreach ($_POST['new_actions'] as $code => $name_th) {
            if (!empty($name_th)) {
                $chk = $conn->query("SELECT id FROM master_actions WHERE action_code = '$code'");
                if ($chk->num_rows == 0) {
                    $stmt = $conn->prepare("INSERT INTO master_actions (action_name, action_code, description) VALUES (?, ?, ?)");
                    $desc = "Auto from $file_name";
                    $stmt->bind_param("sss", $name_th, $code, $desc);
                    $stmt->execute();
                }
            }
        }
    }

    // 1.2 บันทึกหน้าเว็บ
    if (!empty($page_name) && !empty($file_name)) {
        if (!empty($page_id)) { // Edit
            $stmt = $conn->prepare("UPDATE master_pages SET page_name=?, file_name=? WHERE id=?");
            $stmt->bind_param("ssi", $page_name, $file_name, $page_id);
            $stmt->execute();
            $target_id = $page_id;
        } else { // Add
            $stmt = $conn->prepare("INSERT INTO master_pages (page_name, file_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $page_name, $file_name);
            $stmt->execute();
            $target_id = $stmt->insert_id;
        }

        // 1.3 ผูกปุ่มเข้ากับหน้านี้
        if (isset($_POST['scan_codes'])) {
            // Reset page_id ของปุ่มเก่าในหน้านี้ก่อน (เผื่อลบปุ่มออก)
            // แต่ระวัง: ถ้าปุ่มถูกใช้หลายหน้า Logic นี้อาจต้องปรับ แต่เบื้องต้น 1 ปุ่ม = 1 หน้าหลัก
            $conn->query("UPDATE master_actions SET page_id = 0 WHERE page_id = $target_id"); 
            
            foreach ($_POST['scan_codes'] as $code) {
                $conn->query("UPDATE master_actions SET page_id = $target_id WHERE action_code = '$code'");
            }
        }

        $message = "<script>Swal.fire({icon:'success', title:'บันทึกเรียบร้อย', showConfirmButton:false, timer:1500});</script>";
    }
}

// ลบหน้า
if (isset($_GET['del_page'])) {
    $id = $_GET['del_page'];
    $conn->query("DELETE FROM master_pages WHERE id = $id");
    // ปลด action ที่ผูกกับหน้านี้ให้ลอย (page_id = 0)
    $conn->query("UPDATE master_actions SET page_id = 0 WHERE page_id = $id");
    header("Location: ManagePages.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหน้าเว็บ - Auto Scanner</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        /* 🔥 CSS Variables for Dark/Light Mode */
        :root {
            --bg-body: #f8f9fa; --bg-card: #ffffff; --bg-input: #ffffff;
            --text-main: #1e293b; --text-muted: #64748b; --border-color: #e2e8f0;
            --shadow: 0 4px 10px rgba(0,0,0,0.05); --hover-bg: #f1f5f9; --primary-color: #7c3aed;
            --scan-exist-bg: #ffffff; --scan-exist-border: #10b981;
            --scan-new-bg: #fff7ed; --scan-new-border: #f97316;
            --scan-code-text: #334155; --scan-code-new-text: #c2410c; --scan-badge-new-bg: #ffedd5;
        }
        [data-theme="dark"], body.dark-mode {
            --bg-body: #0f172a; --bg-card: #1e293b; --bg-input: #334155;
            --text-main: #f8fafc; --text-muted: #cbd5e1; --border-color: #334155;
            --shadow: 0 4px 10px rgba(0,0,0,0.3); --hover-bg: #334155; --primary-color: #a78bfa;
            --scan-exist-bg: #1e293b; --scan-exist-border: #10b981;
            --scan-new-bg: rgba(249, 115, 22, 0.15); --scan-new-border: #f97316;
            --scan-code-text: #e2e8f0; --scan-code-new-text: #fdba74; --scan-badge-new-bg: rgba(253, 186, 116, 0.2);
        }

        body { background-color: var(--bg-body); color: var(--text-main); transition: 0.3s; font-family:'Prompt'; }
        .main-content { padding: 30px; }
        .container { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; }
        @media (max-width: 1000px) { .container { grid-template-columns: 1fr; } }

        .card { background: var(--bg-card); border-radius: 20px; box-shadow: var(--shadow); border: 1px solid var(--border-color); display: flex; flex-direction: column; overflow: hidden; }
        .card-header { padding: 15px 25px; background: var(--hover-bg); border-bottom: 1px solid var(--border-color); font-weight: 600; color: var(--primary-color); font-size:1.1rem; }
        .card-body { padding: 25px; }

        .form-control { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); box-sizing: border-box; font-family:'Prompt'; }
        .form-control:focus { outline:none; border-color: var(--primary-color); }
        
        .btn-primary { background: var(--primary-color); color: #ffffff !important; border: none; padding: 12px; border-radius: 10px; cursor: pointer; width: 100%; font-weight:600; transition:0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn-primary:hover { opacity:0.9; transform: translateY(-2px); }

        .input-group { display: flex; gap: 0; }
        .input-group .form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .btn-scan { background: var(--primary-color); color: #fff; border: none; padding: 0 25px; border-top-right-radius: 10px; border-bottom-right-radius: 10px; cursor: pointer; white-space: nowrap; font-weight: 600; transition: 0.2s; }
        .btn-scan:hover { opacity: 0.9; }

        .scan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 15px; }
        .scan-item { background: var(--scan-exist-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 15px; position: relative; transition: 0.2s; }
        .scan-item.exist { border-left: 5px solid var(--scan-exist-border); }
        .scan-item.exist label { cursor: pointer; display: flex; align-items: center; gap: 10px; color: var(--text-main); }
        .scan-item.new { border-left: 5px solid var(--scan-new-border); background: var(--scan-new-bg); }
        .code-text { font-family: monospace; font-weight: 700; color: var(--scan-code-text); font-size: 0.95rem; }
        .scan-item.new .code-text { color: var(--scan-code-new-text); }
        .name-text { font-size: 0.85rem; color: var(--text-muted); margin-top: 2px; }
        .badge-new { font-size:0.7rem; background: var(--scan-badge-new-bg); color: var(--scan-code-new-text); padding:2px 5px; border-radius:4px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: var(--hover-bg); color: var(--text-muted); font-size: 0.85rem; font-weight:600; }
        td { padding: 12px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; color: var(--text-main); }
        .file-tag { font-family:monospace; background: var(--hover-bg); padding:2px 5px; border-radius:4px; color: var(--text-muted); }
        .btn-icon { background:transparent; border:none; cursor:pointer; font-size:1.1rem; transition:0.2s; }
        .btn-edit { color: #f59e0b; } .btn-edit:hover { color: #d97706; transform:scale(1.1); }
        .btn-del { color: #ef4444; } .btn-del:hover { color: #b91c1c; transform:scale(1.1); }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>
    <?php echo $message; ?>

    <div class="main-content">
        <h2 style="margin-bottom:20px; color:var(--text-main); font-weight:700;"><i class="fas fa-magic"></i> จัดการหน้าเว็บ & สแกนปุ่ม</h2>

        <div class="container">
            <div class="card">
                <div class="card-header"><i class="fas fa-edit"></i> ฟอร์มจัดการข้อมูล</div>
                <div class="card-body">
                    <form method="post" id="pageForm">
                        <input type="hidden" name="page_id" id="inp_page_id">
                        
                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:8px; color:var(--text-main);">ชื่อหน้าเว็บ (เมนูภาษาไทย)</label>
                            <input type="text" name="page_name" id="inp_page_name" class="form-control" placeholder="เช่น: ประวัติงานของฉัน" required>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:8px; color:var(--text-main);">ชื่อไฟล์ (System File)</label>
                            <div class="input-group">
                                <input type="text" name="file_name" id="inp_file_name" class="form-control" placeholder="เช่น: StaffHistory.php" required>
                                <button type="button" class="btn-scan" onclick="scanFile()">
                                    <i class="fas fa-search"></i> &nbsp;สแกนหาปุ่ม
                                </button>
                            </div>
                            <small style="color:var(--primary-color); margin-top:5px; display:block;">* ระบบจะค้นหาโค้ด <code>hasAction(...)</code> ในไฟล์นี้</small>
                        </div>

                        <div id="scanResult" style="display:none; margin-bottom:20px;">
                            <h4 style="margin:0 0 10px 0; color:var(--text-muted); border-bottom:1px solid var(--border-color); padding-bottom:5px;">
                                <i class="fas fa-list-check"></i> รายการปุ่มที่พบ
                            </h4>
                            <div id="scanList" class="scan-grid"></div>
                        </div>

                        <button type="submit" name="save_page" id="btnSave" class="btn-primary">
                            <i class="fas fa-save"></i> บันทึกข้อมูล
                        </button>
                        <div style="text-align:center; margin-top:15px;">
                            <a href="#" onclick="resetForm(); return false;" style="color:var(--text-muted); text-decoration:none;">ล้างค่า / ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-list"></i> หน้าเว็บทั้งหมด</div>
                <div class="card-body" style="padding:0;">
                    <div style="overflow-x:auto; max-height:600px;">
                        <table>
                            <thead><tr><th>ชื่อหน้า</th><th>ไฟล์</th><th width="60"></th></tr></thead>
                            <tbody>
                                <?php
                                $res = $conn->query("SELECT * FROM master_pages ORDER BY id DESC");
                                while($row = $res->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td><b>{$row['page_name']}</b></td>";
                                    echo "<td><span class='file-tag'>{$row['file_name']}</span></td>";
                                    echo "<td>
                                            <button type='button' class='btn-icon btn-edit' 
                                                onclick=\"editPage('{$row['id']}', '{$row['page_name']}', '{$row['file_name']}')\">
                                                <i class='fas fa-pen-square'></i>
                                            </button>
                                            <a href='?del_page={$row['id']}' onclick=\"return confirm('ลบ?')\" class='btn-icon btn-del'><i class='fas fa-trash'></i></a>
                                        </td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function scanFile() {
            let filename = document.getElementById('inp_file_name').value;
            if(!filename) { Swal.fire('แจ้งเตือน', 'กรุณากรอกชื่อไฟล์ก่อน', 'warning'); return; }

            Swal.fire({ 
                title: 'กำลังสแกนไฟล์...', 
                background: getComputedStyle(document.body).getPropertyValue('--bg-card').trim(),
                color: getComputedStyle(document.body).getPropertyValue('--text-main').trim(),
                didOpen: () => Swal.showLoading() 
            });

            $.post('ManagePages.php', { scan_file: filename }, function(response) {
                Swal.close();
                let res = JSON.parse(response);
                
                if(res.status === 'error' || res.status === 'empty') {
                    Swal.fire({
                        title: 'ผลการสแกน', text: res.message, icon: 'info',
                        background: getComputedStyle(document.body).getPropertyValue('--bg-card').trim(),
                        color: getComputedStyle(document.body).getPropertyValue('--text-main').trim()
                    });
                    $('#scanResult').hide();
                } else {
                    let html = '';
                    res.data.forEach(item => {
                        let hiddenInput = `<input type="hidden" name="scan_codes[]" value="${item.code}">`;
                        
                        if(item.exists) {
                            // มีแล้ว (Green)
                            html += `
                            <div class="scan-item exist">
                                <label>
                                    <input type="checkbox" checked disabled> 
                                    <div style="width:100%;">
                                        <div class="code-text">${item.code}</div>
                                        <div class="name-text"><i class="fas fa-check-circle"></i> ${item.name}</div>
                                    </div>
                                    ${hiddenInput}
                                </label>
                            </div>`;
                        } else {
                            // เจอใหม่ (Orange)
                            html += `
                            <div class="scan-item new">
                                <div style="margin-bottom:5px;">
                                    <span class="code-text">${item.code}</span> 
                                    <span class="badge-new">NEW</span>
                                </div>
                                <input type="text" name="new_actions[${item.code}]" class="form-control" 
                                       style="padding:8px; font-size:0.9rem;" 
                                       placeholder="ตั้งชื่อปุ่มภาษาไทย..." required>
                                ${hiddenInput}
                            </div>`;
                        }
                    });
                    $('#scanList').html(html);
                    $('#scanResult').slideDown();
                }
            });
        }

        function editPage(id, name, file) {
            document.getElementById('inp_page_id').value = id;
            document.getElementById('inp_page_name').value = name;
            document.getElementById('inp_file_name').value = file;
            document.getElementById('btnSave').innerHTML = '<i class="fas fa-save"></i> บันทึกการแก้ไข';
            $('#scanResult').hide();
            scanFile();
        }

        function resetForm() {
            document.getElementById('pageForm').reset();
            document.getElementById('inp_page_id').value = '';
            document.getElementById('btnSave').innerHTML = '<i class="fas fa-save"></i> บันทึกข้อมูล';
            $('#scanResult').slideUp();
        }
    </script>

</body>
</html>