<?php
session_start();
require_once 'auth.php'; // ตรวจสอบสิทธิ์การเข้าใช้งาน
require_once 'db_connect.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

// ==========================================
// 1. จัดการเพิ่มประเภทงาน (AJAX POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    header('Content-Type: application/json');
    $type_name = trim($_POST['type_name']);

    if (!empty($type_name)) {
        // เช็คก่อนว่ามีชื่อนี้ในระบบหรือยัง
        $check = $conn->prepare("SELECT id FROM project_job_types WHERE type_name = ?");
        $check->bind_param("s", $type_name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'มีประเภทงานนี้อยู่ในระบบแล้วครับ']);
            exit;
        }
        $check->close();

        // บันทึกข้อมูลใหม่
        $stmt = $conn->prepare("INSERT INTO project_job_types (type_name) VALUES (?)");
        $stmt->bind_param("s", $type_name);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'เพิ่มข้อมูลไม่สำเร็จ']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกชื่อประเภทงาน']);
    }
    exit;
}

// ==========================================
// 2. จัดการลบประเภทงาน (AJAX POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM project_job_types WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ลบข้อมูลไม่สำเร็จ หรือมีการใช้งานอยู่']);
    }
    $stmt->close();
    exit;
}

// ==========================================
// 3. ดึงข้อมูลมาแสดงในตาราง
// ==========================================
$result = $conn->query("SELECT * FROM project_job_types ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title>จัดการประเภทงาน (Job Types)</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f8fafc;
            color: #334155;
            margin: 0;
        }

        .main-content {
            padding: 30px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            border-bottom: 2px dashed #e2e8f0;
            padding-bottom: 15px;
        }

        .icon-box {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .form-group {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .form-control {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Prompt';
            font-size: 1rem;
            outline: none;
            transition: 0.3s;
        }

        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .btn-add {
            background: #6366f1;
            color: #fff;
            border: none;
            padding: 0 25px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Prompt';
            font-weight: 600;
            font-size: 1rem;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);
        }

        .btn-add:hover {
            background: #4f46e5;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fca5a5;
            padding: 6px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Prompt';
            font-weight: 600;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        th {
            background: #f8fafc;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #cbd5e1;
        }

        tr:hover {
            background: #fdfaf5;
        }

        .type-name-badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            border: 1px solid #c7d2fe;
            display: inline-block;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container">
            <div class="header-title">
                <div class="icon-box"><i class="fas fa-tags"></i></div>
                <div>
                    <h2 style="margin: 0; color: #1e293b; font-size: 1.5rem;">จัดการประเภทงาน</h2>
                    <p style="margin: 5px 0 0; color: #64748b; font-size: 0.9rem;">
                        เพิ่มหรือลบหมวดหมู่ประเภทงานสำหรับใช้ในโปรเจกต์</p>
                </div>
            </div>

            <div class="form-group">
                <input type="text" id="new_job_type" class="form-control"
                    placeholder="พิมพ์ชื่อประเภทงานใหม่ (เช่น เดินสายไฟ, งานบริการ)..."
                    onkeypress="if(event.key === 'Enter') addJobType();">
                <button type="button" class="btn-add" onclick="addJobType()">
                    <i class="fas fa-plus-circle"></i> บันทึก
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th width="10%" style="text-align:center;">ลำดับ</th>
                        <th>ชื่อประเภทงาน</th>
                        <th width="15%" style="text-align:center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            ?>
                            <tr>
                                <td style="text-align:center; color:#94a3b8; font-weight:600;"><?= $i++; ?></td>
                                <td>
                                    <div class="type-name-badge">
                                        <i class="fas fa-tag" style="margin-right: 5px; opacity: 0.7;"></i>
                                        <?= htmlspecialchars($row['type_name']); ?>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <button class="btn-danger"
                                        onclick="deleteJobType(<?= $row['id']; ?>, '<?= htmlspecialchars($row['type_name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-trash-alt"></i> ลบ
                                    </button>
                                </td>
                            </tr>
                            <?php
                        endwhile;
                    else:
                        ?>
                        <tr>
                            <td colspan="3" style="text-align:center; padding:50px; color:#94a3b8;">
                                <i class="fas fa-inbox fa-3x" style="display:block; margin-bottom:15px; opacity: 0.3;"></i>
                                <div style="font-size: 1.1rem; font-weight: 600;">ยังไม่มีข้อมูลประเภทงาน</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // ฟังก์ชันเพิ่มประเภทงาน
        function addJobType() {
            const name = $('#new_job_type').val().trim();
            if (!name) {
                return Swal.fire('แจ้งเตือน', 'กรุณากรอกชื่อประเภทงานก่อนกดบันทึกครับ', 'warning');
            }

            // แสดงโหลดดิ้ง
            Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            // 🔥 เปลี่ยนปลายทางให้ตรงกับชื่อไฟล์ project_job_types.php
            $.post('project_job_types.php', { action: 'add', type_name: name }, function (res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'เพิ่มสำเร็จ!',
                        showConfirmButton: false,
                        timer: 1000
                    }).then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            });
        }

        // ฟังก์ชันลบประเภทงาน
        function deleteJobType(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                html: `คุณต้องการลบประเภทงาน <b>"${name}"</b> ใช่หรือไม่?<br><span style="font-size:13px; color:#ef4444;">*หากลบไปแล้วจะไม่สามารถกู้คืนได้</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: '<i class="fas fa-trash"></i> ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก',
                customClass: { confirmButton: 'shadow-sm' }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                    // 🔥 เปลี่ยนปลายทางให้ตรงกับชื่อไฟล์ project_job_types.php
                    $.post('project_job_types.php', { action: 'delete', id: id }, function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'ลบข้อมูลสำเร็จ',
                                showConfirmButton: false,
                                timer: 1000
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                        }
                    }, 'json').fail(function () {
                        Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                    });
                }
            });
        }
    </script>
</body>

</html>