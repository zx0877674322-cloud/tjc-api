<?php
session_start();
require_once 'auth.php'; // ถ้ามีระบบล็อกอิน
require_once 'db_connect.php';

// ==========================================
// 🔴 Backend API: จัดการข้อมูลผ่าน AJAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        // --- 1. จัดการจังหวัด (Province) ---
        if ($action == 'add_province') {
            $name = trim($_POST['name']);
            if ($name) {
                $conn->query("INSERT INTO provinces (province_name) VALUES ('$name')");
                echo json_encode(['status' => 'success']);
            }
        } elseif ($action == 'delete_province') {
            $id = intval($_POST['id']);
            // ลบลูกๆ ก่อน (ตำบล -> อำเภอ -> จังหวัด)
            $conn->query("DELETE FROM districts WHERE amphure_id IN (SELECT id FROM amphures WHERE province_id = $id)");
            $conn->query("DELETE FROM amphures WHERE province_id = $id");
            $conn->query("DELETE FROM provinces WHERE id = $id");
            echo json_encode(['status' => 'success']);
        }

        // --- 2. จัดการอำเภอ (Amphure) ---
        elseif ($action == 'get_amphures') {
            $prov_id = intval($_POST['province_id']);
            $res = $conn->query("SELECT * FROM amphures WHERE province_id = $prov_id ORDER BY amphure_name ASC");
            $data = [];
            while ($row = $res->fetch_assoc())
                $data[] = $row;
            echo json_encode($data);
        } elseif ($action == 'add_amphure') {
            $prov_id = intval($_POST['province_id']);
            $name = trim($_POST['name']);
            if ($name && $prov_id) {
                $conn->query("INSERT INTO amphures (province_id, amphure_name) VALUES ($prov_id, '$name')");
                echo json_encode(['status' => 'success']);
            }
        } elseif ($action == 'delete_amphure') {
            $id = intval($_POST['id']);
            $conn->query("DELETE FROM districts WHERE amphure_id = $id");
            $conn->query("DELETE FROM amphures WHERE id = $id");
            echo json_encode(['status' => 'success']);
        }

        // --- 3. จัดการตำบล (District) ---
        elseif ($action == 'get_districts') {
            $amp_id = intval($_POST['amphure_id']);
            $res = $conn->query("SELECT * FROM districts WHERE amphure_id = $amp_id ORDER BY district_name ASC");
            $data = [];
            while ($row = $res->fetch_assoc())
                $data[] = $row;
            echo json_encode($data);
        } elseif ($action == 'add_district') {
            $amp_id = intval($_POST['amphure_id']);
            $name = trim($_POST['name']);
            $zip = trim($_POST['zip']);
            if ($name && $amp_id) {
                $conn->query("INSERT INTO districts (amphure_id, district_name, zip_code) VALUES ($amp_id, '$name', '$zip')");
                echo json_encode(['status' => 'success']);
            }
        } elseif ($action == 'delete_district') {
            $id = intval($_POST['id']);
            $conn->query("DELETE FROM districts WHERE id = $id");
            echo json_encode(['status' => 'success']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ดึงจังหวัดเริ่มต้น
$provinces = $conn->query("SELECT * FROM provinces ORDER BY province_name ASC");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลที่อยู่ (จังหวัด/อำเภอ/ตำบล)</title>
    <?php include 'Logowab.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f1f5f9;
            color: #334155;
            margin: 0;
        }

        .main-content {
            padding: 20px;
            margin-left: 250px;
            /* ปรับตาม Sidebar */
        }

        .page-header {
            margin-bottom: 20px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Layout 3 Columns */
        .address-manager-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1.2fr;
            gap: 20px;
            align-items: start;
            height: calc(100vh - 120px);
        }

        .col-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .col-header {
            padding: 15px 20px;
            font-weight: 700;
            font-size: 1.1rem;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .col-body {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            background: #f8fafc;
        }

        .col-footer {
            padding: 15px;
            background: #fff;
            border-top: 1px solid #e2e8f0;
        }

        /* List Items */
        .item-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .item-list li {
            background: #fff;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-list li:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .item-list li.active {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1d4ed8;
            font-weight: 600;
        }

        /* Specific Colors */
        .card-prov .col-header {
            color: #3b82f6;
            background: #eff6ff;
            border-bottom-color: #dbeafe;
        }

        .card-amp .col-header {
            color: #d97706;
            background: #fffbeb;
            border-bottom-color: #fef3c7;
        }

        .card-dist .col-header {
            color: #059669;
            background: #ecfdf5;
            border-bottom-color: #d1fae5;
        }

        .btn-mini-del {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: 0.2s;
        }

        .item-list li:hover .btn-mini-del {
            opacity: 1;
        }

        .btn-mini-del:hover {
            background: #ef4444;
            color: #fff;
        }

        .input-group {
            display: flex;
            gap: 8px;
        }

        .form-control {
            flex: 1;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Prompt';
        }

        .btn-add {
            background: #3b82f6;
            color: #fff;
            border: none;
            padding: 0 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-add:hover {
            background: #2563eb;
        }

        .btn-add:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }

        .zip-badge {
            font-size: 0.8rem;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 20px;
            color: #64748b;
            margin-right: 10px;
            border: 1px solid #e2e8f0;
        }

        .placeholder-text {
            text-align: center;
            color: #94a3b8;
            margin-top: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .placeholder-text i {
            font-size: 2rem;
            color: #cbd5e1;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-map-marked-alt text-primary"></i> จัดการข้อมูลที่อยู่ (จังหวัด > อำเภอ > ตำบล)
            </div>
        </div>

        <div class="address-manager-grid">

            <div class="col-card card-prov">
                <div class="col-header">
                    <span><i class="fas fa-city"></i> เลือกจังหวัด</span>
                </div>
                <div class="col-body">
                    <ul class="item-list" id="list_province">
                        <?php while ($p = $provinces->fetch_assoc()): ?>
                            <li onclick="loadAmphures(<?= $p['id'] ?>, '<?= htmlspecialchars($p['province_name']) ?>', this)"
                                data-id="<?= $p['id'] ?>">
                                <span><?= htmlspecialchars($p['province_name']) ?></span>
                                <button class="btn-mini-del" onclick="deleteItem(event, 'province', <?= $p['id'] ?>)"><i
                                        class="fas fa-trash-alt"></i></button>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <div class="col-footer">
                    <div class="input-group">
                        <input type="text" id="new_province" class="form-control" placeholder="ชื่อจังหวัดใหม่...">
                        <button class="btn-add" onclick="addProvince()"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
            </div>

            <div class="col-card card-amp">
                <div class="col-header">
                    <span id="header_amp"><i class="fas fa-map"></i> อำเภอ</span>
                    <input type="hidden" id="current_prov_id">
                </div>
                <div class="col-body">
                    <div id="amp_placeholder" class="placeholder-text">
                        <i class="fas fa-arrow-left"></i>
                        <span>กรุณาเลือกจังหวัดจากด้านซ้าย</span>
                    </div>
                    <ul class="item-list" id="list_amphure" style="display:none;"></ul>
                </div>
                <div class="col-footer">
                    <div class="input-group">
                        <input type="text" id="new_amphure" class="form-control" placeholder="ชื่ออำเภอ..." disabled>
                        <button class="btn-add" id="btn_add_amp" onclick="addAmphure()" disabled><i
                                class="fas fa-plus"></i></button>
                    </div>
                </div>
            </div>

            <div class="col-card card-dist">
                <div class="col-header">
                    <span id="header_dist"><i class="fas fa-home"></i> ตำบล & รหัสปณ.</span>
                    <input type="hidden" id="current_amp_id">
                </div>
                <div class="col-body">
                    <div id="dist_placeholder" class="placeholder-text">
                        <span>เลือกอำเภอก่อน</span>
                    </div>
                    <ul class="item-list" id="list_district" style="display:none;"></ul>
                </div>
                <div class="col-footer">
                    <div class="input-group" style="flex-direction:column;">
                        <div style="display:flex; gap:8px;">
                            <input type="text" id="new_district" class="form-control" placeholder="ชื่อตำบล..."
                                disabled>
                            <input type="text" id="new_zip" class="form-control" placeholder="รหัส ปณ."
                                style="width: 100px;" maxlength="5" disabled>
                            <button class="btn-add" id="btn_add_dist" onclick="addDistrict()" disabled><i
                                    class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // --- Province ---
        function addProvince() {
            const name = $('#new_province').val().trim();
            if (!name) return Swal.fire('แจ้งเตือน', 'กรุณาระบุชื่อจังหวัด', 'warning');

            $.post('manage_provinces.php', { action: 'add_province', name: name }, function (res) {
                if (res.status === 'success') location.reload();
            }, 'json');
        }

        // --- Amphure ---
        function loadAmphures(provId, provName, el) {
            // Highlight Active
            $('#list_province li').removeClass('active');
            $(el).addClass('active');

            // Setup UI
            $('#current_prov_id').val(provId);
            $('#header_amp').html(`<i class="fas fa-map"></i> อ. ใน จ.${provName}`);
            $('#new_amphure').prop('disabled', false).focus();
            $('#btn_add_amp').prop('disabled', false);

            // Reset District Column
            resetDistColumn();

            // AJAX Fetch
            $.post('manage_provinces.php', { action: 'get_amphures', province_id: provId }, function (data) {
                $('#amp_placeholder').hide();
                const list = $('#list_amphure').empty().show();

                if (data.length === 0) list.append('<div style="text-align:center; padding:20px; color:#cbd5e1;">- ไม่มีข้อมูลอำเภอ -</div>');

                data.forEach(item => {
                    list.append(`
                    <li onclick="loadDistricts(${item.id}, '${item.amphure_name}', this)" data-id="${item.id}">
                        <span>${item.amphure_name}</span>
                        <button class="btn-mini-del" onclick="deleteItem(event, 'amphure', ${item.id})"><i class="fas fa-trash-alt"></i></button>
                    </li>
                `);
                });
            }, 'json');
        }

        function addAmphure() {
            const provId = $('#current_prov_id').val();
            const name = $('#new_amphure').val().trim();
            if (!name) return;

            $.post('manage_provinces.php', { action: 'add_amphure', province_id: provId, name: name }, function (res) {
                if (res.status === 'success') {
                    $('#new_amphure').val('');
                    $(`#list_province li[data-id="${provId}"]`).click(); // Reload list
                }
            }, 'json');
        }

        // --- District ---
        function loadDistricts(ampId, ampName, el) {
            $('#list_amphure li').removeClass('active');
            $(el).addClass('active');

            $('#current_amp_id').val(ampId);
            $('#header_dist').html(`<i class="fas fa-home"></i> ต. ใน อ.${ampName}`);
            $('#new_district, #new_zip, #btn_add_dist').prop('disabled', false);
            $('#new_district').focus();

            $.post('manage_provinces.php', { action: 'get_districts', amphure_id: ampId }, function (data) {
                $('#dist_placeholder').hide();
                const list = $('#list_district').empty().show();

                if (data.length === 0) list.append('<div style="text-align:center; padding:20px; color:#cbd5e1;">- ไม่มีข้อมูลตำบล -</div>');

                data.forEach(item => {
                    const zipHTML = item.zip_code ? `<span class="zip-badge"><i class="fas fa-envelope"></i> ${item.zip_code}</span>` : '';
                    list.append(`
                    <li>
                        <span>${zipHTML} ${item.district_name}</span>
                        <button class="btn-mini-del" onclick="deleteItem(event, 'district', ${item.id})"><i class="fas fa-trash-alt"></i></button>
                    </li>
                `);
                });
            }, 'json');
        }

        function addDistrict() {
            const ampId = $('#current_amp_id').val();
            const name = $('#new_district').val().trim();
            const zip = $('#new_zip').val().trim();
            if (!name) return;

            $.post('manage_provinces.php', { action: 'add_district', amphure_id: ampId, name: name, zip: zip }, function (res) {
                if (res.status === 'success') {
                    $('#new_district').val('');
                    $('#new_zip').val('');
                    $(`#list_amphure li[data-id="${ampId}"]`).click(); // Reload list
                }
            }, 'json');
        }

        // --- Helpers ---
        function resetDistColumn() {
            $('#current_amp_id').val('');
            $('#header_dist').html('<i class="fas fa-home"></i> ตำบล & รหัสปณ.');
            $('#list_district').hide().empty();
            $('#dist_placeholder').show();
            $('#new_district, #new_zip, #btn_add_dist').prop('disabled', true).val('');
        }

        function deleteItem(e, type, id) {
            e.stopPropagation();
            let msg = '';
            if (type === 'province') msg = 'จังหวัด (และข้อมูลภายใน)';
            if (type === 'amphure') msg = 'อำเภอ (และตำบลทั้งหมด)';
            if (type === 'district') msg = 'ตำบล';

            Swal.fire({
                title: 'ยืนยันลบ?',
                text: `ต้องการลบ ${msg} นี้ใช่ไหม?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('manage_provinces.php', { action: 'delete_' + type, id: id }, function (res) {
                        if (res.status === 'success') {
                            if (type === 'province') location.reload();
                            else if (type === 'amphure') $(`#list_province li.active`).click();
                            else if (type === 'district') $(`#list_amphure li.active`).click();
                        }
                    }, 'json');
                }
            });
        }
    </script>

</body>

</html>