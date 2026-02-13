<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตั้งค่าโซนเวลาไทย
date_default_timezone_set('Asia/Bangkok');

// ==========================================
//  1. HANDLE FILTERS (รับค่าตัวกรอง)
// ==========================================

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date   = isset($_GET['end_date'])   ? $_GET['end_date']   : '';
$emp_id     = isset($_GET['emp_id'])     ? $_GET['emp_id']     : 'all';

$where_sql = " WHERE 1=1 ";

// กรองวันที่
if (!empty($start_date) && !empty($end_date)) {
    $where_sql .= " AND (report_date BETWEEN '$start_date' AND '$end_date') ";
}

// กรองพนักงาน
if ($emp_id != 'all') {
    $where_sql .= " AND reporter_id = " . intval($emp_id);
}

// ==========================================
//  2. DATA FETCHING (ดึงข้อมูล)
// ==========================================

// 2.1 ดึงรายชื่อพนักงานคลัง (เฉพาะคนที่มีประวัติ)
$users_opt = [];
$sql_emp = "SELECT DISTINCT u.id, u.fullname 
            FROM users u 
            INNER JOIN warehouse_daily_reports w ON u.id = w.reporter_id 
            ORDER BY u.fullname ASC";
$res_u = $conn->query($sql_emp);
while($u = $res_u->fetch_assoc()) $users_opt[] = $u;

// 2.2 ดึงรายการรายงานคลัง
$sql_list = "SELECT r.*, u.fullname 
             FROM warehouse_daily_reports r 
             LEFT JOIN users u ON r.reporter_id = u.id 
             $where_sql 
             ORDER BY r.report_date DESC, r.report_time DESC";
$res_list = $conn->query($sql_list);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <title>Warehouse Reports</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        :root {
            /* ธีมสีเขียว Teal */
            --primary: #0d9488; 
            --bg-body: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        body { font-family: 'Prompt', sans-serif; background: var(--bg-body); margin: 0; color: var(--text-main); }
        .main-content { margin-left: 100px; padding: 30px; transition: 0.3s; }

        /* Filter Box */
        .filter-section {
            background: #fff; padding: 24px; border-radius: 16px; margin-bottom: 25px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border-color);
        }
        .filter-form { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; }
        .form-group { flex: 1; min-width: 200px; margin-bottom: 0; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
        .form-control {
            width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid #cbd5e1;
            font-family: 'Prompt'; font-size: 0.95rem; box-sizing: border-box;
        }
        
        .btn-search {
            background: var(--primary); color: #fff; border: none; padding: 0 25px; border-radius: 10px;
            font-weight: 600; cursor: pointer; height: 44px; display: flex; align-items: center; gap: 8px; transition: 0.2s;
        }
        .btn-search:hover { filter: brightness(1.1); transform: translateY(-1px); }
        
        .btn-reset {
            background: #fff; color: var(--text-muted); border: 1px solid #cbd5e1; padding: 0 15px; border-radius: 10px;
            font-weight: 600; cursor: pointer; height: 44px; display: flex; align-items: center; text-decoration: none;
        }

        /* Table */
        .table-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border-color); overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
        
        /* หัวตารางสีเขียวอ่อน */
        thead th { background: #ccfbf1; color: #134e4a; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; padding: 18px 24px; text-align: left; border-bottom: 1px solid var(--border-color); }
        
        tbody td { padding: 18px 24px; color: var(--text-main); font-size: 0.95rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        tbody tr:hover { background: #f0fdfa; }

        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
        .btn-view {
            border: 1px solid var(--border-color); background: #fff; color: var(--text-muted); width: 38px; height: 38px;
            border-radius: 8px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center;
        }
        .btn-view:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-content { background: #fff; width: 90%; max-width: 600px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; animation: slideUp 0.3s ease; }
        .modal-header { padding: 20px 25px; background: #fff; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--text-main); }
        .modal-close { cursor: pointer; color: var(--text-muted); font-size: 1.5rem; }
        .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; background: #fcfcfc; }
        
        .d-group { margin-bottom: 15px; }
        .d-lbl { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-bottom: 5px; text-transform: uppercase; }
        .d-val { font-size: 1rem; color: var(--text-main); font-weight: 500; }

        @keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        @media (max-width: 992px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <div style="margin-bottom: 25px;">
        <h2 style="margin:0; font-weight:700; color:var(--text-main);">Warehouse Daily Reports</h2>
        <p style="margin:5px 0 0; color:var(--text-muted);">ระบบรายงานฝ่ายคลังสินค้า</p>
    </div>

    <div class="filter-section">
        <form class="filter-form" method="GET">
            <div class="form-group">
                <label class="form-label">ตั้งแต่วันที่</label>
                <input type="text" name="start_date" class="form-control date-picker" value="<?= $start_date ?>" placeholder="ทั้งหมด">
            </div>
            <div class="form-group">
                <label class="form-label">ถึงวันที่</label>
                <input type="text" name="end_date" class="form-control date-picker" value="<?= $end_date ?>" placeholder="ทั้งหมด">
            </div>
            <div class="form-group">
                <label class="form-label">พนักงานคลัง</label>
                <select name="emp_id" class="form-control select2-filter">
                    <option value="all">-- แสดงทั้งหมด --</option>
                    <?php foreach($users_opt as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($emp_id == $u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['fullname']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> ค้นหา</button>
            <a href="warehouse_dashboard.php" class="btn-reset"><i class="fas fa-redo"></i></a>
        </form>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="15%">วัน-เวลา</th>
                        <th width="25%">ผู้รายงาน (Staff)</th>
                        <th width="50%">รายละเอียดงานคลัง (Note)</th>
                        <th width="10%" class="text-center">ตรวจสอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(isset($res_list) && $res_list && $res_list->num_rows > 0): ?>
                        <?php while($row = $res_list->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600; color:var(--text-main);">
                                        <?php 
                                            // แสดงเป็น ปี พ.ศ.
                                            echo date('d/m/', strtotime($row['report_date'])) . (date('Y', strtotime($row['report_date'])) + 543);
                                        ?>
                                    </div>
                                    <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                        <i class="far fa-clock"></i> <?= date('H:i', strtotime($row['report_time'])) ?> น.
                                    </div>
                                </td>
                                <td>
                                    <div class="status-badge" style="background:#ccfbf1; color:#0f766e;">
                                        <i class="fas fa-warehouse"></i> <?= htmlspecialchars($row['fullname']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size:0.9rem; color:var(--text-muted); line-height:1.5;">
                                        <?= htmlspecialchars($row['note']) ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button class="btn-view" onclick='showDetail(<?= json_encode($row) ?>)'>
                                        <i class="fas fa-search-plus"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:50px; color:var(--text-muted);">
                                <i class="fas fa-boxes" style="font-size:2.5rem; opacity:0.3; margin-bottom:15px; display:block;"></i>
                                ไม่พบข้อมูลงานคลังในช่วงเวลาที่เลือก
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-dolly" style="color:var(--primary); margin-right:8px;"></i> รายละเอียดงานคลัง</h3>
            <div class="modal-close" onclick="closeModal()">&times;</div>
        </div>
        <div class="modal-body" id="modalBody">
            </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('.select2-filter').select2();
        
        // ตั้งค่า Datepicker เป็น DD/MM/YYYY
        flatpickr(".date-picker", {
            altInput: true, altFormat: "d/m/Y", dateFormat: "Y-m-d", locale: "th"
        });
    });

    function showDetail(data) {
        // แปลงวันที่ใน Modal เป็นปีไทย (Manual Calculation)
        let dateObj = new Date(data.report_date);
        let yearTh = dateObj.getFullYear() + 543;
        let day = String(dateObj.getDate()).padStart(2, '0');
        let month = String(dateObj.getMonth() + 1).padStart(2, '0'); // Month is 0-indexed
        let dateTh = `${day}/${month}/${yearTh}`;

        let content = `
            <div style="display:flex; justify-content:space-between; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
                <div>
                    <div class="d-lbl">วันที่รายงาน</div>
                    <div class="d-val">${dateTh}</div>
                </div>
                <div style="text-align:right;">
                    <div class="d-lbl">เวลา</div>
                    <div class="d-val">${data.report_time.substring(0,5)} น.</div>
                </div>
            </div>

            <div class="d-group">
                <div class="d-lbl">เจ้าหน้าที่คลังสินค้า</div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:40px; height:40px; background:#ccfbf1; color:#0f766e; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700;">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div style="font-size:1.1rem; font-weight:600; color:#1e293b;">${data.fullname}</div>
                </div>
            </div>

            <div class="d-group" style="margin-top:20px;">
                <div class="d-lbl" style="color:var(--primary); display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-clipboard-list"></i> รายละเอียดงาน / สต็อก (Note)
                </div>
                <div style="background:#f0fdfa; border:1px solid #ccfbf1; border-radius:12px; padding:15px; margin-top:8px; font-size:0.95rem; color:#134e4a; line-height:1.6; white-space: pre-wrap;">${data.note}</div>
            </div>
            
            <div style="text-align:right; margin-top:15px; font-size:0.8rem; color:#94a3b8;">
                Warehouse Log ID: #${data.id}
            </div>
        `;

        document.getElementById('modalBody').innerHTML = content;
        document.getElementById('detailModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('detailModal').style.display = 'none';
    }

    window.onclick = function(e) {
        if (e.target == document.getElementById('detailModal')) {
            closeModal();
        }
    }
</script>

</body>
</html>