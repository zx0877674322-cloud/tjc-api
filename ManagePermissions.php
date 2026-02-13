<?php
// 🔥 1. ตั้งค่า Config และแสดง Error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ เพิ่มขีดจำกัดการรับค่า Form (สำคัญมากสำหรับหน้า Matrix ที่มี checkbox เยอะๆ)
ini_set('max_input_vars', 5000); 
ini_set('post_max_size', '20M');

session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// 2. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "<script>window.location.href='index.php';</script>"; 
    exit();
}

// 🔥 AJAX: สำหรับบันทึกการเรียงลำดับ (ทำงานเบื้องหลัง)
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'update_sort') {
    $order = $_POST['order']; // รับค่า array ของ id ที่เรียงแล้ว
    if (is_array($order)) {
        $stmt = $conn->prepare("UPDATE master_pages SET sort_order = ? WHERE id = ?");
        foreach ($order as $position => $page_id) {
            $stmt->bind_param("ii", $position, $page_id);
            $stmt->execute();
        }
        $stmt->close();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit(); // จบการทำงานส่วน AJAX ทันที
}

// 3. บันทึกข้อมูลสิทธิ์ (Form Submit ปกติ)
$alert_script = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax_action'])) {
    
    // ----------------------------------------------------------------------
    // 3.1 Save Page Permissions (สิทธิ์เข้าหน้าเว็บ)
    // ----------------------------------------------------------------------
    $conn->query("TRUNCATE TABLE permissions"); 
    if (isset($_POST['perms']) && is_array($_POST['perms'])) {
        $stmt = $conn->prepare("INSERT INTO permissions (role_name, page_id) VALUES (?, ?)");
        foreach ($_POST['perms'] as $role => $pages) {
            if(is_array($pages)) {
                foreach ($pages as $page_id) {
                    $stmt->bind_param("si", $role, $page_id);
                    $stmt->execute();
                }
            }
        }
        $stmt->close();
    }

    // ----------------------------------------------------------------------
    // 3.2 Save Action Perms (สิทธิ์ปุ่ม) - 🔥 จุดที่แก้ไข
    // ----------------------------------------------------------------------
    // เช็คก่อนว่ามีข้อมูลส่งมาไหม ถ้าไม่มีเลย (หรือ php ตัดทิ้ง) ห้ามล้างตาราง
    if (isset($_POST['actions']) && is_array($_POST['actions']) && count($_POST['actions']) > 0) {
        
        // ล้างข้อมูลเก่าออกเฉพาะเมื่อมีข้อมูลใหม่มาแทนที่
        $conn->query("TRUNCATE TABLE role_actions"); 
        
        $stmt_act = $conn->prepare("INSERT INTO role_actions (role_name, action_code) VALUES (?, ?)");
        foreach ($_POST['actions'] as $role => $codes) {
            if(is_array($codes)) {
                foreach ($codes as $ac_code) {
                    if (!empty($ac_code)) { // กรองค่าว่าง
                        $stmt_act->bind_param("ss", $role, $ac_code);
                        $stmt_act->execute();
                    }
                }
            }
        }
        $stmt_act->close();
    } elseif (isset($_POST['save_marker'])) {
        // กรณีมีปุ่ม Save กดมา แต่ไม่มี actions ส่งมาเลย (แปลว่าติ๊กออกหมด)
        // ให้ล้างตารางได้ (ต้องมี input hidden ชื่อ save_marker ในฟอร์มเพื่อยืนยันว่ากด save จริง)
        $conn->query("TRUNCATE TABLE role_actions");
    }

    $alert_script = "Swal.fire({icon:'success', title:'บันทึกสิทธิ์เรียบร้อย', showConfirmButton:false, timer:1500});";
}

// 4. เตรียมข้อมูลสำหรับแสดงผล
$roles = $conn->query("SELECT * FROM master_roles ORDER BY id ASC");
$pages = $conn->query("SELECT * FROM master_pages ORDER BY sort_order ASC, id ASC");
$actions = $conn->query("SELECT * FROM master_actions ORDER BY id ASC");

// จัดกลุ่ม Action ตาม Page ID
$actions_by_page = [];
if ($actions) {
    while($act = $actions->fetch_assoc()) {
        $pid = $act['page_id'] ? $act['page_id'] : 0;
        $actions_by_page[$pid][] = $act;
    }
}

// ดึงสิทธิ์ปัจจุบัน (เข้าหน้าเว็บ)
$current_perms = [];
$res = $conn->query("SELECT * FROM permissions");
if($res) {
    while($row = $res->fetch_assoc()) { $current_perms[$row['role_name']][] = $row['page_id']; }
}

// ดึงสิทธิ์ปุ่มปัจจุบัน (Role Actions)
$current_actions = [];
$checkTable = $conn->query("SHOW TABLES LIKE 'role_actions'");
if($checkTable->num_rows > 0) {
    $res_act = $conn->query("SELECT * FROM role_actions");
    if($res_act) {
        while($row = $res_act->fetch_assoc()) { 
            // แปลง role_name เป็น lowercase เพื่อความชัวร์ในการเทียบ
            $current_actions[$row['role_name']][] = $row['action_code']; 
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กำหนดสิทธิ์ - TJC</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    
    <style>
        :root {
            --bg-body: #f8f9fa; --bg-card: #ffffff; --text-main: #1e293b; --text-muted: #64748b;
            --border-color: #e2e8f0; --hover-bg: #f1f5f9; --primary-color: #2563eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --bg-action-row: #fff7ed; --bg-action-cb: #ffffff;
            --border-action: #fed7aa; --text-action-primary: #c2410c;
            --text-action-code: #9a3412; --bg-action-code: #ffedd5; --color-orange-main: #f97316;
        }

        [data-theme="dark"], body.dark-mode {
            --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc; --text-muted: #cbd5e1;
            --border-color: #334155; --hover-bg: #334155; --primary-color: #60a5fa;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            --bg-action-row: rgba(249, 115, 22, 0.15); --bg-action-cb: #0f172a;
            --border-action: #7c2d12; --text-action-primary: #fdba74;
            --text-action-code: #fed7aa; --bg-action-code: rgba(255, 237, 213, 0.1);
        }

        body { background-color: var(--bg-body); color: var(--text-main); transition: 0.3s; font-family: 'Prompt', sans-serif; }
        .main-content { padding: 30px; margin-top: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }

        .card { background: var(--bg-card); border-radius: 15px; box-shadow: var(--shadow); border: 1px solid var(--border-color); overflow: hidden; }
        .card-header { padding: 20px; background: var(--hover-bg); border-bottom: 1px solid var(--border-color); }
        
        .table-wrapper { overflow-x: auto; max-height: 75vh; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        
        /* Headers */
        thead th { 
            padding: 15px; background: var(--bg-card); text-align: center; 
            border-bottom: 2px solid var(--border-color); border-right: 1px solid var(--border-color);
            position: sticky; top: 0; z-index: 50; color: var(--text-main);
        }
        thead th:first-child { left: 0; z-index: 60; text-align: left; width: 350px; min-width: 350px; }

        /* Cells */
        td { 
            padding: 10px; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); 
            vertical-align: middle; color: var(--text-main);
        }

        .row-page { background: var(--bg-card); }
        .col-sticky { position: sticky; left: 0; z-index: 40; background: inherit; border-right: 2px solid var(--border-color); }

        .row-action { display: none; background: var(--bg-action-row); } 
        .row-action.show { display: table-row; }
        .row-action td:first-child { border-right: 2px solid var(--border-color); padding-left: 50px; color: var(--text-action-primary); background: var(--bg-action-row); }

        .cb-wrap { display: flex; justify-content: center; align-items: center; width: 100%; height: 100%; cursor: pointer; padding: 5px; }
        .modern-cb { width: 24px; height: 24px; border: 2px solid var(--border-color); border-radius: 6px; display: flex; align-items: center; justify-content: center; background: var(--bg-card); transition:0.2s; pointer-events: none; }
        input:checked + .modern-cb { background: var(--primary-color); border-color: var(--primary-color); color: white; }
        input:disabled + .modern-cb { background: var(--hover-bg); border-color: var(--border-color); opacity: 0.6; }
        
        .action-cb-style { border-color: var(--color-orange-main) !important; color: var(--color-orange-main); background: var(--bg-action-cb); }
        input:checked + .action-cb-style { background: var(--color-orange-main) !important; border-color: var(--color-orange-main) !important; color: white !important; }

        .btn-toggle { background: none; border: none; cursor: pointer; color: var(--text-muted); width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; transition: 0.3s; }
        .btn-toggle:hover { background: rgba(0,0,0,0.1); color: var(--text-main); }
        .btn-toggle.active { transform: rotate(180deg); color: var(--color-orange-main); background: var(--bg-action-row); }

        .role-head { cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; user-select: none; }
        
        .btn-save-float { position: fixed; bottom: 30px; right: 30px; z-index: 999; background: var(--primary-color); color: white; border: none; padding: 15px 30px; border-radius: 50px; font-size: 1.1rem; font-weight: bold; box-shadow: 0 5px 20px rgba(0,0,0,0.3); cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 10px; }
        .btn-save-float:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        .code-badge { font-size: 0.75rem; background: var(--bg-action-code); color: var(--text-action-code); padding: 2px 6px; border-radius: 4px; margin-left: 8px; font-family: monospace; }

        .drag-handle { cursor: grab; color: var(--text-muted); padding: 5px 10px; font-size: 1.2rem; transition: color 0.2s; }
        .drag-handle:hover { color: var(--primary-color); }
        .drag-handle:active { cursor: grabbing; }
        .sortable-ghost { opacity: 0.4; background-color: var(--primary-color) !important; }
        .sortable-drag { cursor: grabbing; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    
    <form method="POST" id="matrixForm">
        <input type="hidden" name="save_marker" value="1">

        <div class="main-content">
            <div class="container">
                <div class="card">
                    <div class="card-header">
                        <h2 style="margin:0; color:var(--text-main);"><i class="fas fa-shield-alt"></i> กำหนดสิทธิ์การใช้งาน</h2>
                    </div>

                    <div class="table-wrapper">
                        <table id="sortableTable">
                            <thead>
                                <tr>
                                    <th>หน้าเว็บ / เมนู (ลากเพื่อย้าย)</th>
                                    <?php 
                                    $roles_arr = []; 
                                    if ($roles && $roles->num_rows > 0) {
                                        $roles->data_seek(0);
                                        while($r = $roles->fetch_assoc()) { 
                                            $roles_arr[] = $r['role_name'];
                                    ?>
                                            <th>
                                                <div class="role-head" onclick="toggleRoleColumn('<?php echo $r['role_name']; ?>')">
                                                    <?php echo ucfirst($r['role_name']); ?> <i class="fas fa-check-circle"></i>
                                                </div>
                                            </th>
                                    <?php 
                                        } 
                                    } 
                                    ?>
                                </tr>
                            </thead>

                            <?php 
                            if ($pages && $pages->num_rows > 0) {
                                $pages->data_seek(0); 
                                while($p = $pages->fetch_assoc()): 
                                    $has_actions = isset($actions_by_page[$p['id']]);
                            ?>
                                <tbody class="page-group" data-id="<?php echo $p['id']; ?>">
                                    <tr class="row-page">
                                        <td class="col-sticky">
                                            <div style="display:flex; align-items:center;">
                                                <div class="drag-handle" title="ลากเพื่อย้ายตำแหน่ง">
                                                    <i class="fas fa-grip-vertical"></i>
                                                </div>
                                                
                                                <div style="flex-grow:1; display:flex; justify-content:space-between; align-items:center;">
                                                    <div style="margin-left:10px;">
                                                        <div style="font-weight:600;"><?php echo $p['page_name']; ?></div>
                                                        <div style="font-size:0.8rem; color:var(--text-muted); font-family:monospace;"><?php echo $p['file_name']; ?></div>
                                                    </div>
                                                    <?php if($has_actions): ?>
                                                        <button type="button" class="btn-toggle" onclick="toggleSubRows(<?php echo $p['id']; ?>, this)">
                                                            <i class="fas fa-chevron-down"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <?php foreach($roles_arr as $r_name): 
                                            $checked = (isset($current_perms[$r_name]) && in_array($p['id'], $current_perms[$r_name])) ? 'checked' : '';
                                            $disabled = ($r_name == 'admin' && $p['file_name'] == 'ManagePermissions.php') ? 'disabled checked' : '';
                                            if($disabled) echo "<input type='hidden' name='perms[$r_name][]' value='".$p['id']."'>";
                                        ?>
                                            <td style="text-align:center;">
                                                <label class="cb-wrap">
                                                    <input type="checkbox" name="perms[<?php echo $r_name; ?>][]" value="<?php echo $p['id']; ?>" class="cb-role-<?php echo $r_name; ?>" style="display:none;" <?php echo $checked; ?> <?php echo $disabled; ?>>
                                                    <div class="modern-cb"><i class="fas fa-check"></i></div>
                                                </label>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>

                                    <?php if($has_actions): 
                                        foreach($actions_by_page[$p['id']] as $act): ?>
                                        <tr class="row-action group-<?php echo $p['id']; ?>">
                                            <td class="col-sticky">
                                                <div style="display:flex; align-items:center;">
                                                    <i class="fas fa-level-up-alt fa-rotate-90" style="margin-right:10px; opacity:0.5; color:var(--text-muted);"></i>
                                                    <span style="font-weight:600;"><?php echo $act['action_name']; ?></span>
                                                    <span class="code-badge"><?php echo $act['action_code']; ?></span>
                                                </div>
                                            </td>
                                            <?php foreach($roles_arr as $r_name): 
                                                // ตรวจสอบว่าติ๊กหรือยัง
                                                $act_checked = (isset($current_actions[$r_name]) && in_array($act['action_code'], $current_actions[$r_name])) ? 'checked' : '';
                                            ?>
                                            <td style="text-align:center;">
                                                <label class="cb-wrap">
                                                    <input type="checkbox" name="actions[<?php echo $r_name; ?>][]" value="<?php echo $act['action_code']; ?>" class="cb-role-<?php echo $r_name; ?>" style="display:none;" <?php echo $act_checked; ?>>
                                                    <div class="modern-cb action-cb-style"><i class="fas fa-fingerprint"></i></div>
                                                </label>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            <?php endwhile; 
                            } 
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn-save-float" onclick="confirmSave()">
            <i class="fas fa-save"></i> บันทึกสิทธิ์
        </button>
    </form>

    <script>
        // --- 1. Toggle Sub Rows ---
        function toggleSubRows(id, btn) {
            const tbody = btn.closest('tbody');
            const rows = tbody.querySelectorAll('.row-action');
            const isHidden = !btn.classList.contains('active');
            rows.forEach(row => {
                if(isHidden) row.classList.add('show');
                else row.classList.remove('show');
            });
            if(isHidden) btn.classList.add('active');
            else btn.classList.remove('active');
        }

        // --- 2. Toggle Column ---
        function toggleRoleColumn(role) {
            const boxes = document.querySelectorAll('.cb-role-' + role);
            let anyUnchecked = false;
            // เช็คว่ามีอันไหนยังไม่ติ๊กไหม
            for (let cb of boxes) { 
                // เช็คเฉพาะที่มองเห็น (หรือจะเช็คหมดก็ได้) แต่ต้องระวัง disabled
                if (!cb.disabled && !cb.checked) { anyUnchecked = true; break; } 
            }
            boxes.forEach(cb => { 
                if (!cb.disabled) cb.checked = anyUnchecked; 
            });
        }

        // --- 3. Save Form ---
        function confirmSave() {
            Swal.fire({
                title: 'ยืนยันบันทึก?',
                text: 'สิทธิ์การใช้งานจะถูกอัปเดตทันที',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#d33',
                confirmButtonText: 'บันทึกเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    document.getElementById('matrixForm').submit();
                }
            });
        }

        // --- 4. 🔥 DRAG & DROP LOGIC ---
        const table = document.getElementById('sortableTable');
        Sortable.create(table, {
            animation: 150,
            handle: '.drag-handle',
            draggable: 'tbody.page-group',
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                saveNewOrder();
            }
        });

        function saveNewOrder() {
            let orderedIds = [];
            document.querySelectorAll('tbody.page-group').forEach((tbody) => {
                orderedIds.push(tbody.getAttribute('data-id'));
            });

            const formData = new FormData();
            formData.append('ajax_action', 'update_sort');
            orderedIds.forEach((id, index) => {
                formData.append('order[' + index + ']', id);
            });

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 1500,
                        timerProgressBar: true
                    });
                    Toast.fire({ icon: 'success', title: 'อัปเดตลำดับแล้ว' });
                } else {
                    Swal.fire('Error', 'บันทึกลำดับไม่สำเร็จ', 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        <?php echo $alert_script; ?>
    </script>

</body>
</html>