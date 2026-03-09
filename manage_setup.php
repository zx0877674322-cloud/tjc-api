<?php
session_start();
require_once 'db_connect.php';

// --- จัดการ Action (เพิ่ม/ลบ) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. เพิ่มหน่วยนับ
    if (isset($_POST['add_unit'])) {
        $name = $conn->real_escape_string($_POST['unit_name']);
        if (!empty($name)) {
            $conn->query("INSERT IGNORE INTO setup_units (unit_name) VALUES ('$name')");
        }
    }
    // 2. ลบหน่วยนับ
    if (isset($_POST['delete_unit'])) {
        $id = intval($_POST['delete_unit']);
        $conn->query("DELETE FROM setup_units WHERE id = $id");
    }

    // 3. เพิ่มสินค้า
    if (isset($_POST['add_product'])) {
        $name = $conn->real_escape_string($_POST['product_name']);
        if (!empty($name)) {
            $conn->query("INSERT IGNORE INTO setup_products (item_name) VALUES ('$name')");
        }
    }
    // 4. ลบสินค้า
    if (isset($_POST['delete_product'])) {
        $id = intval($_POST['delete_product']);
        $conn->query("DELETE FROM setup_products WHERE id = $id");
    }
    // 5. ✅ เพิ่มสถานะจัดซื้อ
    if (isset($_POST['add_status'])) {
        $name = $conn->real_escape_string($_POST['status_name']);
        if (!empty($name)) {
            $conn->query("INSERT IGNORE INTO setup_purchase_status (status_name) VALUES ('$name')");
        }
    }
    // 6. ✅ ลบสถานะจัดซื้อ
    if (isset($_POST['delete_status'])) {
        $id = intval($_POST['delete_status']);
        $conn->query("DELETE FROM setup_purchase_status WHERE id = $id");
    }

    // Redirect เพื่อกันการกด Refresh แล้วส่งข้อมูลซ้ำ
    header("Location: manage_setup.php");
    exit();
}

// --- ดึงข้อมูลมาแสดง ---
$units = $conn->query("SELECT * FROM setup_units ORDER BY unit_name ASC");
$products = $conn->query("SELECT * FROM setup_products ORDER BY item_name ASC");
$statuses = $conn->query("SELECT * FROM setup_purchase_status ORDER BY seq ASC, id ASC");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลพื้นฐาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #4338ca;
            --secondary-color: #64748b;
            --bg-color: #eef2f6;
            --card-bg: #ffffff;
            --border-color: #cbd5e1;
            --danger-color: #ef4444;
            --success-color: #10b981;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: var(--bg-color);
            color: #1e293b;
            padding: 30px 20px;
            margin: 0;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-back {
            background: white;
            color: var(--secondary-color);
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #f1f5f9;
            color: var(--primary-color);
        }

        /* Grid Layout for 2 Columns */
        .setup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .setup-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 600px;
            /* Fixed height for scrolling */
        }

        .card-header {
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 0;
            overflow-y: auto;
            /* Scrollable list */
            flex-grow: 1;
        }

        .add-form {
            padding: 20px 25px;
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            gap: 10px;
        }

        .form-control {
            flex-grow: 1;
            padding: 10px 15px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            font-family: 'Prompt';
            outline: none;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.1);
        }

        .btn-add {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-add:hover {
            background: #047857;
        }

        /* List Table */
        .list-table {
            width: 100%;
            border-collapse: collapse;
        }

        .list-table th,
        .list-table td {
            padding: 12px 25px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .list-table th {
            background: #f8fafc;
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .list-table tr:hover {
            background: #f8fafc;
        }

        .btn-del {
            color: #cbd5e1;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.2s;
        }

        .btn-del:hover {
            color: var(--danger-color);
            transform: scale(1.1);
        }

        /* Icon Colors */
        .icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 10px;
        }

        .icon-unit {
            background: #e0e7ff;
            color: var(--primary-color);
        }

        .icon-prod {
            background: #ecfdf5;
            color: var(--success-color);
        }

        .badge-count {
            background: #f1f5f9;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #64748b;
        }
    </style>
</head>

<body>

    <div class="main-container">

        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-database"></i> จัดการข้อมูลพื้นฐาน
            </div>
            <a href="project_dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
            </a>
        </div>

        <div class="setup-grid">

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="icon-box icon-unit"><i class="fas fa-ruler-combined"></i></div>
                        หน่วยนับสินค้า
                    </div>
                    <span class="badge-count">
                        <?= $units->num_rows ?> รายการ
                    </span>
                </div>

                <form method="POST" class="add-form">
                    <input type="text" name="unit_name" class="form-control" placeholder="ระบุชื่อหน่วยใหม่..."
                        required>
                    <button type="submit" name="add_unit" class="btn-add"><i class="fas fa-plus"></i> เพิ่ม</button>
                </form>

                <div class="card-body">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th width="10%">#</th>
                                <th width="70%">ชื่อหน่วย</th>
                                <th width="20%" style="text-align:right;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($units->num_rows > 0): ?>
                                <?php $i = 1;
                                while ($u = $units->fetch_assoc()): ?>
                                    <tr>
                                        <td style="color:#94a3b8;">
                                            <?= $i++ ?>
                                        </td>
                                        <td style="font-weight:500;">
                                            <?= htmlspecialchars($u['unit_name']) ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <form method="POST" onsubmit="return confirm('ยืนยันการลบหน่วยนับนี้?');">
                                                <input type="hidden" name="delete_unit" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn-del"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:30px; color:#cbd5e1;">ไม่พบข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="icon-box icon-prod"><i class="fas fa-box-open"></i></div>
                        รายชื่อสินค้า (ประวัติ)
                    </div>
                    <span class="badge-count">
                        <?= $products->num_rows ?> รายการ
                    </span>
                </div>

                <form method="POST" class="add-form">
                    <input type="text" name="product_name" class="form-control" placeholder="ระบุชื่อสินค้าใหม่..."
                        required>
                    <button type="submit" name="add_product" class="btn-add"><i class="fas fa-plus"></i> เพิ่ม</button>
                </form>

                <div class="card-body">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th width="10%">#</th>
                                <th width="70%">ชื่อสินค้า</th>
                                <th width="20%" style="text-align:right;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products->num_rows > 0): ?>
                                <?php $j = 1;
                                while ($p = $products->fetch_assoc()): ?>
                                    <tr>
                                        <td style="color:#94a3b8;">
                                            <?= $j++ ?>
                                        </td>
                                        <td style="font-weight:500; color:#334155;">
                                            <?= htmlspecialchars($p['item_name']) ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <form method="POST"
                                                onsubmit="return confirm('ยืนยันการลบสินค้านี้ออกจากประวัติ?');">
                                                <input type="hidden" name="delete_product" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn-del"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:30px; color:#cbd5e1;">ไม่พบข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="icon-box" style="background:#fef3c7; color:#d97706;"><i class="fas fa-tags"></i>
                        </div>
                        สถานะจัดซื้อ
                    </div>
                    <span class="badge-count">
                        <?= $statuses->num_rows ?> รายการ
                    </span>
                </div>

                <form method="POST" class="add-form">
                    <input type="text" name="status_name" class="form-control" placeholder="ชื่อสถานะใหม่..." required>
                    <button type="submit" name="add_status" class="btn-add"><i class="fas fa-plus"></i> เพิ่ม</button>
                </form>

                <div class="card-body">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th width="10%">#</th>
                                <th width="70%">ชื่อสถานะ</th>
                                <th width="20%" style="text-align:right;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($statuses->num_rows > 0): ?>
                                <?php $k = 1;
                                while ($s = $statuses->fetch_assoc()): ?>
                                    <tr>
                                        <td style="color:#94a3b8;">
                                            <?= $k++ ?>
                                        </td>
                                        <td style="font-weight:500;">
                                            <span
                                                style="padding:4px 8px; background:#f1f5f9; border-radius:4px; font-size:0.9rem;">
                                                <?= htmlspecialchars($s['status_name']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <form method="POST" onsubmit="return confirm('ลบสถานะนี้?');">
                                                <input type="hidden" name="delete_status" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn-del"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:30px; color:#cbd5e1;">ไม่พบข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</body>

</html>