<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ลบข้อมูลลูกค้า
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // เตรียมคำสั่งลบ
    $delete_stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
    $delete_stmt->bind_param("i", $delete_id);

    // ส่ง response กลับเป็น JSON ถ้าเป็น AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($delete_stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลลูกค้าเรียบร้อยแล้ว']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $conn->error]);
        }
        exit;
    } else {
        $delete_stmt->execute();
        header("Location: manage_customers.php");
        exit;
    }
}

// Fetch all customers
$customers = [];
$query = "SELECT * FROM customers ORDER BY customer_id DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการลูกค้าหน่วยงาน - TJC GROUP</title>

    <?php include 'Logowab.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="load_scripts.js"></script>
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="css/service_dashboard.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .main-container {
            padding: 1.5rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .dashboard-header-wrapper {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            animation: fadeInDown 0.6s ease-out;
        }

        .page-title {
            color: #0f172a;
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .action-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            margin-bottom: 2rem;
            overflow: hidden;
            animation: fadeInUp 0.5s ease-out;
        }

        .card-header {
            background-color: #fafaf9;
            padding: 18px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-add-customer {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-add-customer:hover {
            background-color: #2563eb;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .search-box {
            position: relative;
            width: 280px;
        }

        .search-box input {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 16px 10px 42px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 0.9rem;
            line-height: 1.5;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #f8fafc;
            margin: 0;
        }

        .search-box input:focus {
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .table-responsive {
            padding: 0;
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }

        .custom-table thead th {
            background-color: #ffffff;
            color: #64748b;
            font-weight: 600;
            padding: 14px 20px;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            text-align: initial;
        }

        .custom-table tbody tr {
            transition: all 0.15s ease;
        }

        .custom-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .custom-table tbody td {
            padding: 16px 20px;
            color: #334155;
            font-size: 0.95rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .custom-table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            background-color: #dcfce7;
            color: #166534;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #bbf7d0;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-edit {
            background-color: #ffffff;
            color: #f59e0b;
            border-color: #fde68a;
        }

        .btn-edit:hover {
            background-color: #fef3c7;
            color: #d97706;
            border-color: #fcd34d;
        }

        .btn-delete {
            background-color: #ffffff;
            color: #ef4444;
            border-color: #fecaca;
        }

        .btn-delete:hover {
            background-color: #fee2e2;
            color: #dc2626;
            border-color: #fca5a5;
        }

        .empty-state {
            padding: 48px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .address-text {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            font-size: 0.9rem;
            color: #475569;
            max-width: 400px;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="main-container">

            <div class="dashboard-header-wrapper">
                <div class="header-content">
                    <h2 class="page-title animate__animated animate__fadeInDown">หน้าจัดการลูกค้า/หน่วยงาน</h2>
                    <span class="page-subtitle animate__animated animate__fadeInUp animate__delay-1s">
                        <i class="fas fa-users-cog"></i> รายการลูกค้าและหน่วยงานทั้งหมดในระบบ
                    </span>
                </div>
            </div>

            <!-- Data Table Card -->
            <div class="action-card">
                <div class="card-header">
                    <h3><i class="fas fa-building text-primary"></i> รายชื่อลูกค้าและหน่วยงาน</h3>

                    <div class="d-flex gap-3 align-items-center">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="ค้นหาชื่อลูกค้า หรือหน่วยงาน...">
                        </div>
                        <a href="manage_customers_add.php" class="btn-add-customer">
                            <i class="fas fa-plus"></i> เพิ่มลูกค้าใหม่
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="custom-table" id="customersTable">
                        <thead>
                            <tr>
                                <th width="5%" class="text-center">#</th>
                                <th width="25%" class="text-start">ชื่อหน่วยงาน/ลูกค้า</th>
                                <th width="15%" class="text-start">เบอร์ติดต่อ</th>
                                <th width="30%" class="text-start">ที่อยู่</th>
                                <th width="15%" class="text-center">สถานะ</th>
                                <th width="10%" class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customers) > 0): ?>
                                <?php foreach ($customers as $index => $customer): ?>
                                    <tr>
                                        <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark" style="font-size: 1rem;">
                                                <?php echo htmlspecialchars($customer['customer_name'] ?? '-'); ?>
                                            </div>
                                            <?php if (!empty($customer['affiliation'])): ?>
                                                <div class="text-muted small mt-1">
                                                    <i class="fas fa-building fa-sm text-secondary me-1"></i>
                                                    สังกัด: <span
                                                        class="fw-medium text-dark"><?php echo htmlspecialchars($customer['affiliation']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($customer['phone_number']) && $customer['phone_number'] !== '-'): ?>
                                                <span class="text-dark"><i
                                                        class="fas fa-phone fa-sm text-muted me-2"></i><?php echo htmlspecialchars($customer['phone_number']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="address-text"
                                                title="<?php echo htmlspecialchars(($customer['address'] ?? '') . " " . ($customer['sub_district'] ?? '') . " " . ($customer['district'] ?? '') . " " . ($customer['province'] ?? '') . " " . ($customer['zip_code'] ?? '')); ?>">
                                                <?php
                                                $full_addr = trim(($customer['address'] ?? '') . " " . ($customer['sub_district'] ?? '') . " " . ($customer['district'] ?? '') . " " . ($customer['province'] ?? ''));
                                                echo empty($full_addr) ? '<span class="text-muted">-</span>' : htmlspecialchars($full_addr);
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-status">
                                                <i class="fas fa-check-circle"></i> ใช้งานพลปกติ
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <button class="btn-action btn-edit" title="แก้ไข"
                                                    onclick="editCustomer(<?php echo $customer['customer_id']; ?>)">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                <button class="btn-action btn-delete" title="ลบ"
                                                    onclick="deleteCustomer(<?php echo $customer['customer_id']; ?>, '<?php echo addslashes($customer['customer_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-box-open"></i>
                                            <h5 class="mt-3 mb-1 text-dark fw-bold">ยังไม่มีข้อมูลลูกค้าในระบบ</h5>
                                            <p class="text-muted mb-0">คลิก "เพิ่มลูกค้าใหม่"
                                                เพื่อเริ่มต้นเพิ่มข้อมูลหน่วยงานหรือลูกค้าของคุณ</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Scripts for SweetAlert and interactions -->
    <script>
        // ระบบค้นหาในตาราง
        document.getElementById('searchInput').addEventListener('keyup', function () {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#customersTable tbody tr');

            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return; // ignore empty state row

                let text = row.innerText.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // เปิดหน้าแก้ไข (ต้องสร้างไฟล์ manage_customers_edit.php)
        function editCustomer(id) {
            // window.location.href = 'manage_customers_edit.php?id=' + id;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'กำลังพัฒนา',
                    text: 'ระบบแก้ไขข้อมูลลูกค้ากำลังอยู่ในช่วงพัฒนา',
                    confirmButtonText: 'ตกลง'
                });
            } else {
                alert('ระบบแก้ไขกำลังพัฒนา');
            }
        }

        // ระบบลบข้อมูลด้วย SweetAlert2 และ AJAX
        function deleteCustomer(id, name) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'ยืนยันการลบข้อมูล?',
                    html: `คุณต้องการลบข้อมูลลูกค้า <br><b class="text-dark">${name}</b> ใช่หรือไม่?<br><small class="text-danger mt-2 d-block">การลบข้อมูลไม่สามารถกู้คืนได้</small>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: '<i class="fas fa-trash"></i> ยืนยันการลบ',
                    cancelButtonText: 'ยกเลิก',
                    reverseButtons: true,
                    customClass: {
                        confirmButton: 'px-4',
                        cancelButton: 'px-4'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // ส่ง Request ไปลบข้อมูล
                        $.ajax({
                            url: 'manage_customers.php?delete_id=' + id,
                            type: 'GET',
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'ลบสำเร็จ!',
                                        text: response.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('ข้อผิดพลาด', response.message, 'error');
                                }
                            },
                            error: function () {
                                Swal.fire('อ๊ะ!', 'เกิดข้อผิดพลาดในการติดต่อเซิร์ฟเวอร์', 'error');
                            }
                        });
                    }
                });
            } else {
                if (confirm(`คุณต้องการลบข้อมูลลูกค้า ${name} ใช่หรือไม่?`)) {
                    window.location.href = 'manage_customers.php?delete_id=' + id;
                }
            }
        }
    </script>
</body>

</html>