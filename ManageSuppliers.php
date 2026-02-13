<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// --- ส่วนจัดการบันทึกข้อมูล (PHP Logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 🟠 1. บันทึกร้านค้า (Suppliers)
    if (isset($_POST['action']) && $_POST['action'] == 'add_supplier') {
        $name = trim($_POST['supplier_name']);
        if ($name) {
            $check = $conn->query("SELECT id FROM suppliers WHERE name = '$name'");
            if ($check->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO suppliers (name) VALUES (?)");
                $stmt->bind_param("s", $name);
                $stmt->execute();
            }
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] == 'edit_supplier') {
        $id = $_POST['id'];
        $name = trim($_POST['supplier_name']);
        if ($id && $name) {
            $stmt = $conn->prepare("UPDATE suppliers SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_supplier') {
        $id = $_POST['id'];
        $conn->query("DELETE FROM suppliers WHERE id = $id");
    }

    // 🟣 2. บันทึกบัญชีธนาคาร (Bank Accounts)
    elseif (isset($_POST['action']) && $_POST['action'] == 'add_account') {
        $bank_id = $_POST['bank_master_id']; // รับ ID จาก bank_masters
        
        // ดึงชื่อธนาคารจาก ID เพื่อมาเก็บ (หรือจะเก็บ ID ก็ได้ แต่ออันนี้เก็บชื่อตามระบบเดิมของคุณ)
        $q_bank = $conn->query("SELECT bank_name FROM bank_masters WHERE id = '$bank_id'");
        $r_bank = $q_bank->fetch_assoc();
        $bank_name = $r_bank['bank_name'] ?? 'ไม่ระบุ';

        $acc_no = trim($_POST['bank_account']); 
        $acc_name = trim($_POST['account_name']);

        if ($bank_name && $acc_no && $acc_name) {
            $check = $conn->query("SELECT id FROM bank_accounts WHERE bank_account = '$acc_no'");
            if ($check->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO bank_accounts (bank_name, bank_account, account_name) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $bank_name, $acc_no, $acc_name);
                $stmt->execute();
            }
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] == 'edit_account') {
        $id = $_POST['id'];
        $bank = $_POST['bank_name'];
        $acc_no = trim($_POST['bank_account']);
        $acc_name = trim($_POST['account_name']);
        
        if ($id && $acc_no) {
            $stmt = $conn->prepare("UPDATE bank_accounts SET bank_name=?, bank_account=?, account_name=? WHERE id=?");
            $stmt->bind_param("sssi", $bank, $acc_no, $acc_name, $id);
            $stmt->execute();
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_account') {
        $id = $_POST['id'];
        $conn->query("DELETE FROM bank_accounts WHERE id = $id");
    }

    // ⚙️ 3. จัดการ Master ธนาคาร (เพิ่ม/ลบ ธนาคารใหม่)
    elseif (isset($_POST['action']) && $_POST['action'] == 'add_master_bank') {
        $b_name = trim($_POST['new_bank_name']);
        $b_digit = intval($_POST['new_bank_digit']);
        if($b_name) {
            $stmt = $conn->prepare("INSERT INTO bank_masters (bank_name, digit_limit) VALUES (?, ?)");
            $stmt->bind_param("si", $b_name, $b_digit);
            $stmt->execute();
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_master_bank') {
        $id = $_POST['id'];
        $conn->query("DELETE FROM bank_masters WHERE id = $id");
    }
    
    header("Location: ManageSuppliers.php");
    exit();
}

// --- ดึงข้อมูลมาแสดง ---
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");
$accounts = $conn->query("SELECT * FROM bank_accounts ORDER BY bank_name ASC, account_name ASC");

// ดึงรายชื่อธนาคารจาก Database (แทนการ Hardcode)
$bank_masters = $conn->query("SELECT * FROM bank_masters ORDER BY bank_name ASC");
$bank_options = []; // เอาไว้ส่งให้ JS
while($bm = $bank_masters->fetch_assoc()) {
    $bank_options[] = $bm; 
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <title>จัดการร้านค้าและบัญชี</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* (CSS เหมือนเดิมทุกประการ ย่อเพื่อความกระชับ) */
        :root { --primary: #4f46e5; --orange-theme: #f97316; --purple-theme: #8b5cf6; --bg-body: #f8fafc; --bg-card: #ffffff; --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0; --input-bg: #ffffff; --shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        [data-theme="dark"] { --primary: #818cf8; --orange-theme: #fb923c; --purple-theme: #a78bfa; --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc; --text-muted: #cbd5e1; --border: #334155; --input-bg: #0f172a; --shadow: 0 4px 6px -1px rgba(0,0,0,0.5); }
        body { font-family: 'Prompt', sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; }
        .main-container { max-width: 1400px; margin: 30px auto; padding: 0 20px; margin-left: 80px; }
        @media (max-width: 900px) { .main-container { margin-left: auto; } }
        .grid-layout { display: grid; grid-template-columns: 1fr 1.2fr; gap: 30px; align-items: start; }
        @media (max-width: 1000px) { .grid-layout { grid-template-columns: 1fr; } }
        .card { background: var(--bg-card); padding: 0; border-radius: 16px; box-shadow: var(--shadow); border: 1px solid var(--border); overflow: hidden; display: flex; flex-direction: column; height: 100%; }
        .card-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .header-orange { background: rgba(249, 115, 22, 0.1); color: var(--orange-theme); border-top: 4px solid var(--orange-theme); }
        .header-purple { background: rgba(139, 92, 246, 0.1); color: var(--purple-theme); border-top: 4px solid var(--purple-theme); }
        .card-body { padding: 20px; }
        .card-title { font-size: 18px; font-weight: 700; margin: 0; display:flex; align-items:center; gap:10px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
        .form-control { width: 100%; height: 42px; padding: 0 12px; border: 1px solid var(--border); border-radius: 8px; font-family: 'Prompt'; font-size: 14px; background: var(--input-bg); color: var(--text-main); box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: var(--primary); }
        .btn { height: 42px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; font-size: 14px; width: 100%; color: white; }
        .btn-orange { background: var(--orange-theme); box-shadow: 0 4px 10px rgba(249, 115, 22, 0.3); }
        .btn-purple { background: var(--purple-theme); box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3); }
        .btn-icon { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; border: none; cursor: pointer; margin-left: 4px; }
        .btn-edit { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .btn-del { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .btn-setting { background: rgba(139, 92, 246, 0.2); color: var(--purple-theme); width:auto; padding:0 10px; height:32px; font-size:12px; }
        .table-container { max-height: 500px; overflow-y: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px; background: var(--bg-body); color: var(--text-muted); font-size: 12px; font-weight: 600; position: sticky; top: 0; }
        td { padding: 10px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text-main); }
        .hint-text { font-size: 11px; color: var(--purple-theme); margin-top: 3px; font-weight: 500; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        <div class="grid-layout">
            
            <div class="card">
                <div class="card-header header-orange">
                    <div class="card-title"><i class="fas fa-store"></i> บันทึกร้านค้า</div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_supplier">
                        <div class="form-group">
                            <label class="form-label">ชื่อร้านค้า / บริษัท</label>
                            <input type="text" name="supplier_name" class="form-control" required placeholder="ระบุชื่อร้านค้า..." autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-orange"><i class="fas fa-save"></i> บันทึกร้านค้า</button>
                    </form>

                    <div class="table-container">
                        <table>
                            <thead> <tr> <th>ชื่อร้านค้า</th> <th width="80" style="text-align:center;">จัดการ</th> </tr> </thead>
                            <tbody>
                                <?php while($s = $suppliers->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight:600;"><?php echo $s['name']; ?></td>
                                    <td style="text-align:center;">
                                        <button type="button" class="btn-icon btn-edit" onclick="editSupplier(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['name']); ?>')"><i class="fas fa-pen"></i></button>
                                        <form method="POST" onsubmit="return confirm('ยืนยันลบ?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_supplier">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" class="btn-icon btn-del"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header header-purple">
                    <div class="card-title"><i class="fas fa-university"></i> บันทึกบัญชีธนาคาร</div>
                    <button type="button" class="btn btn-setting" onclick="manageBankList()"><i class="fas fa-cog"></i> ตั้งค่าธนาคาร</button>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_account">
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label class="form-label">เลือกธนาคาร</label>
                                <select name="bank_master_id" id="bankSelect" class="form-control" onchange="updateBankInput(this)" required>
                                    <option value="" disabled selected>-- กรุณาเลือก --</option>
                                    <?php 
                                    // วนลูปสร้าง Options จาก Database
                                    foreach ($bank_options as $bm) {
                                        echo "<option value='{$bm['id']}' data-digits='{$bm['digit_limit']}'>{$bm['bank_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">เลขที่บัญชี</label>
                                <input type="text" name="bank_account" id="accInput" class="form-control" required placeholder="เลือกธนาคารก่อน" autocomplete="off" disabled>
                                <span id="digitHint" class="hint-text"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ชื่อบัญชี / ชื่อเจ้าของ</label>
                            <input type="text" name="account_name" class="form-control" required placeholder="เช่น บจก.ตัวอย่าง จำกัด" autocomplete="off">
                        </div>

                        <button type="submit" class="btn btn-purple"><i class="fas fa-save"></i> บันทึกบัญชี</button>
                    </form>

                    <div class="table-container">
                        <table>
                            <thead> <tr> <th width="30%">ธนาคาร</th> <th width="30%">เลขบัญชี</th> <th width="30%">ชื่อบัญชี</th> <th width="10%">จัดการ</th> </tr> </thead>
                            <tbody>
                                <?php while($acc = $accounts->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="hint-text" style="font-size:12px; font-weight:700;"><?php echo $acc['bank_name']; ?></span></td>
                                    <td style="font-family:monospace; font-weight:700;"><?php echo $acc['bank_account']; ?></td>
                                    <td style="color:var(--text-muted);"><?php echo $acc['account_name']; ?></td>
                                    <td style="text-align:center;">
                                        <button type="button" class="btn-icon btn-edit" onclick="editAccount(<?php echo $acc['id']; ?>, '<?php echo $acc['bank_name']; ?>', '<?php echo $acc['bank_account']; ?>', '<?php echo $acc['account_name']; ?>')"><i class="fas fa-pen"></i></button>
                                        <form method="POST" onsubmit="return confirm('ยืนยันลบ?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_account">
                                            <input type="hidden" name="id" value="<?php echo $acc['id']; ?>">
                                            <button type="submit" class="btn-icon btn-del"><i class="fas fa-trash"></i></button>
                                        </form>
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
        // 1. Theme Logic
        document.addEventListener("DOMContentLoaded", function() {
            if (localStorage.getItem('theme') === 'dark') document.body.setAttribute('data-theme', 'dark');
        });

        // 2. จัดการเลขที่บัญชี
        function updateBankInput(select) {
            const option = select.options[select.selectedIndex];
            const digits = parseInt(option.getAttribute('data-digits')) || 0;
            const accInput = document.getElementById('accInput');
            const hint = document.getElementById('digitHint');

            accInput.disabled = false;
            accInput.value = '';

            if (digits > 0) {
                accInput.setAttribute('maxlength', digits);
                accInput.placeholder = `กรอก ${digits} หลัก`;
                hint.innerText = `* บังคับ ${digits} หลัก (ตัวเลขเท่านั้น)`;
            } else {
                accInput.removeAttribute('maxlength');
                accInput.placeholder = 'ระบุเลขที่บัญชี';
                hint.innerText = `* ไม่จำกัดจำนวนหลัก`;
            }

            accInput.oninput = function() { this.value = this.value.replace(/[^0-9]/g, ''); };
        }

        // 3. Popup จัดการรายชื่อธนาคาร (เพิ่ม/ลบ Bank Master)
        function manageBankList() {
            // สร้าง HTML รายชื่อธนาคารเพื่อโชว์ใน Popup
            let bankListHtml = `
                <div style="text-align:left; max-height:200px; overflow-y:auto; margin-bottom:15px; border:1px solid #e2e8f0; border-radius:8px; padding:10px;">
                    <?php foreach ($bank_options as $bm): ?>
                    <div style="display:flex; justify-content:space-between; padding:5px; border-bottom:1px dashed #eee;">
                        <span><?php echo $bm['bank_name']; ?> (<?php echo $bm['digit_limit'] > 0 ? $bm['digit_limit'].' หลัก' : 'ไม่จำกัด'; ?>)</span>
                        <form method="POST" onsubmit="return confirm('ลบธนาคารนี้?');" style="margin:0;">
                            <input type="hidden" name="action" value="delete_master_bank">
                            <input type="hidden" name="id" value="<?php echo $bm['id']; ?>">
                            <button type="submit" style="background:none; border:none; color:red; cursor:pointer;">&times;</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            `;

            Swal.fire({
                title: 'ตั้งค่ารายชื่อธนาคาร',
                html: `
                    ${bankListHtml}
                    <hr style="margin:15px 0; border:0; border-top:1px solid #eee;">
                    <div style="text-align:left;">
                        <label style="font-size:13px; font-weight:600;">เพิ่มธนาคารใหม่</label>
                        <input id="new-bank-name" class="swal2-input" placeholder="ชื่อธนาคาร" style="width:100%; margin:5px 0;">
                        <input id="new-bank-digit" type="number" class="swal2-input" placeholder="จำนวนหลัก (ใส่ 0 ถ้าไม่จำกัด)" style="width:100%; margin:5px 0;">
                    </div>
                `,
                confirmButtonText: '+ เพิ่มธนาคาร',
                showCloseButton: true,
                preConfirm: () => {
                    const name = document.getElementById('new-bank-name').value;
                    const digit = document.getElementById('new-bank-digit').value;
                    if(!name) Swal.showValidationMessage('กรุณาใส่ชื่อธนาคาร');
                    return { name: name, digit: digit };
                }
            }).then((result) => {
                if(result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="add_master_bank">
                        <input type="hidden" name="new_bank_name" value="${result.value.name}">
                        <input type="hidden" name="new_bank_digit" value="${result.value.digit}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // ฟังก์ชันแก้ไขชื่อร้านค้า (เหมือนเดิม)
        function editSupplier(id, currentName) {
            Swal.fire({
                title: 'แก้ไขชื่อร้านค้า',
                input: 'text',
                inputValue: currentName,
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                confirmButtonColor: '#f97316'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `<input type="hidden" name="action" value="edit_supplier"><input type="hidden" name="id" value="${id}"><input type="hidden" name="supplier_name" value="${result.value}">`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // ฟังก์ชันแก้ไขบัญชี (ปรับให้ใช้ง่ายขึ้น)
        function editAccount(id, currentBank, currentAcc, currentName) {
            Swal.fire({
                title: 'แก้ไขข้อมูลบัญชี',
                html: `
                    <div style="text-align:left; margin-bottom:10px;">
                        <label>ธนาคาร (แก้ไขไม่ได้)</label>
                        <input class="swal2-input" value="${currentBank}" disabled style="width:100%; margin:0; background:#f1f5f9;">
                    </div>
                    <div style="text-align:left; margin-bottom:10px;">
                        <label>เลขบัญชี</label>
                        <input id="swal-acc" class="swal2-input" value="${currentAcc}" style="width:100%; margin:0;">
                    </div>
                    <div style="text-align:left;">
                        <label>ชื่อบัญชี</label>
                        <input id="swal-name" class="swal2-input" value="${currentName}" style="width:100%; margin:0;">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                confirmButtonColor: '#8b5cf6',
                preConfirm: () => {
                    return {
                        bank: currentBank, // ส่งค่าเดิมกลับไป
                        acc: document.getElementById('swal-acc').value,
                        name: document.getElementById('swal-name').value
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="edit_account">
                        <input type="hidden" name="id" value="${id}">
                        <input type="hidden" name="bank_name" value="${result.value.bank}">
                        <input type="hidden" name="bank_account" value="${result.value.acc}">
                        <input type="hidden" name="account_name" value="${result.value.name}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>