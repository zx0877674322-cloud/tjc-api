<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตั้งค่าโซนเวลาไทย
date_default_timezone_set('Asia/Bangkok');

// ==========================================
//  1. HANDLE SAVE REPORT (บันทึกข้อมูล)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $r_date = $_POST['report_date'];
    $r_time = $_POST['report_time'];
    $r_user = intval($_POST['reporter_id']);
    $r_note = trim($_POST['note']);

    if (empty($r_date) || empty($r_note)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรายละเอียดงานคลัง']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO warehouse_daily_reports (report_date, report_time, reporter_id, note) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $r_date, $r_time, $r_user, $r_note);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <title>บันทึกงานคลังสินค้า</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        :root {
            /* ธีมสีเขียว Teal สำหรับคลังสินค้า */
            --primary: #0d9488; 
            --primary-dark: #0f766e;
            --bg-body: #f8fafc;
        }
        body { font-family: 'Prompt', sans-serif; background: var(--bg-body); margin: 0; padding-bottom: 40px; }
        
        .main-content {
            margin-left: 250px; 
            padding: 25px;
            max-width: 800px; margin-right: auto;
        }

        /* Form Card */
        .report-card {
            background: #fff; border-radius: 16px; padding: 30px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0; margin-bottom: 25px;
            border-top: 5px solid var(--primary);
        }

        .page-header { margin-bottom: 20px; text-align: center; }
        .page-header h2 { margin: 0; color: #1e293b; font-weight: 700; display:flex; align-items:center; justify-content:center; gap:10px; }
        .page-header p { margin: 5px 0 0; color: #64748b; font-size: 0.9rem; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #334155; }
        
        .modern-input {
            width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 10px;
            font-size: 1rem; font-family: 'Prompt', sans-serif; transition: 0.2s;
            box-sizing: border-box;
        }
        .modern-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.1); }
        .modern-input[readonly] { background-color: #f1f5f9; color: #64748b; cursor: not-allowed; }

        .btn-submit {
            width: 100%; padding: 14px; background: var(--primary); color: #fff;
            border: none; border-radius: 10px; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(13, 148, 136, 0.2);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .report-card { padding: 20px; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h2><i class="fas fa-warehouse" style="color:var(--primary);"></i> บันทึกงานคลังสินค้า</h2>
        <p>Warehouse Daily Report Log</p>
    </div>

    <div class="report-card">
        <form id="warehouseForm" onsubmit="return false;">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">วันที่</label>
                    <input type="text" name="report_date" class="modern-input date-picker" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">เวลา</label>
                    <input type="text" name="report_time" class="modern-input time-picker" value="<?= date('H:i') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">ผู้บันทึก / เจ้าหน้าที่คลัง</label>
                <input type="text" class="modern-input" 
                       value="<?= htmlspecialchars($_SESSION['fullname'] ?? 'Unknown') ?>" 
                       readonly>
                <input type="hidden" name="reporter_id" value="<?= $_SESSION['user_id'] ?? 0 ?>">
            </div>

            <div class="form-group">
                <label class="form-label">รายละเอียด / รายการรับ-จ่ายสินค้า / ตรวจเช็คสต็อก</label>
                <textarea name="note" class="modern-input" rows="5" placeholder="เช่น รับสินค้าเข้า 500 รายการ, ตรวจนับสต็อกโซน A เรียบร้อย..."></textarea>
            </div>

            <button class="btn-submit" onclick="saveReport()">
                <i class="fas fa-save"></i> บันทึกงานคลัง
            </button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // วันที่ภาษาไทย รูปแบบ d/m/Y (เช่น 09/02/2026)
        flatpickr(".date-picker", {
            altInput: true,
            altFormat: "d/m/Y",
            dateFormat: "Y-m-d",
            locale: "th",
            defaultDate: "today"
        });
        
        // เวลา 24 ชม.
        flatpickr(".time-picker", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            locale: "th",
            defaultDate: new Date()
        });
    });

    function saveReport() {
        let formData = new FormData(document.getElementById('warehouseForm'));
        
        if(!formData.get('note').trim()) {
            Swal.fire({ icon: 'warning', title: 'แจ้งเตือน', text: 'กรุณากรอกรายละเอียดงาน' });
            return;
        }

        Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });

        $.ajax({
            url: 'warehouse_create_report.php',
            type: 'POST',
            data: formData,
            processData: false, contentType: false, dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire({
                        icon: 'success', title: 'บันทึกเรียบร้อย',
                        timer: 1500, showConfirmButton: false
                    }).then(() => {
                        location.reload(); 
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            }
        });
    }
</script>

</body>
</html>