<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตั้งค่า Timezone ให้เป็นไทย (สำคัญมากสำหรับเวลา Default)
date_default_timezone_set('Asia/Bangkok');

// ==========================================
//  HANDLE SAVE REPORT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $r_date = $_POST['report_date'];
    $r_time = $_POST['report_time'];
    $r_user = intval($_POST['reporter_id']);
    $r_note = trim($_POST['note']);

    if (empty($r_date) || empty($r_note)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO hr_daily_reports (report_date, report_time, reporter_id, note) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $r_date, $r_time, $r_user, $r_note);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// ==========================================
//  DATA FETCHING
// ==========================================
// 1. ดึงรายชื่อพนักงาน
$users = [];
$sql_users = "SELECT id, fullname FROM users ORDER BY fullname ASC";
$res_users = $conn->query($sql_users);
while ($u = $res_users->fetch_assoc())
    $users[] = $u;

// 2. ดึงประวัติ "ของฉัน"
$my_id = $_SESSION['user_id'] ?? 0;
$sql_my = "SELECT * FROM hr_daily_reports WHERE reporter_id = ? ORDER BY report_date DESC, report_time DESC LIMIT 10";
$stmt_my = $conn->prepare($sql_my);
$stmt_my->bind_param("i", $my_id);
$stmt_my->execute();
$res_my = $stmt_my->get_result();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title>บันทึกรายงานประจำวัน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        :root {
            --primary: #4f46e5;
            --bg-body: #f8fafc;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: var(--bg-body);
            margin: 0;
            padding-bottom: 40px;
        }

        .main-content {
            margin-left: 250px;
            padding: 25px;
            max-width: 800px;
            margin-right: auto;
        }

        .report-card {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .page-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .page-header h2 {
            margin: 0;
            color: #1e293b;
            font-weight: 700;
        }

        .page-header p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
        }

        .modern-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Prompt', sans-serif;
            transition: 0.2s;
            box-sizing: border-box;
        }

        .modern-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);
        }

        .btn-submit:hover {
            background: #4338ca;
            transform: translateY(-2px);
        }

        /* History List */
        .history-item {
            background: #fff;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 10px;
            display: flex;
            gap: 15px;
            align-items: flex-start;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .h-date {
            background: #e0e7ff;
            color: #4338ca;
            padding: 8px 12px;
            border-radius: 8px;
            text-align: center;
            min-width: 60px;
        }

        .h-date .day {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1;
            display: block;
        }

        .h-date .time {
            font-size: 0.75rem;
            display: block;
            margin-top: 3px;
        }

        .h-content {
            flex: 1;
        }

        .h-note {
            font-size: 0.95rem;
            color: #334155;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .report-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <h2><i class="fas fa-file-pen" style="color:var(--primary);"></i> บันทึกรายงานประจำวัน</h2>
            <p>ฝ่ายทรัพยากรบุคคล (HR)</p>
        </div>

        <div class="report-card">
            <form id="dailyForm" onsubmit="return false;">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">วันที่</label>
                        <input type="text" name="report_date" class="modern-input date-picker"
                            value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">เวลา</label>
                        <input type="text" name="report_time" class="modern-input time-picker"
                            value="<?= date('H:i') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">ผู้รายงาน</label>
                    <input type="text" class="modern-input"
                        value="<?= htmlspecialchars($_SESSION['fullname'] ?? 'Unknown') ?>" readonly
                        style="background-color: #f1f5f9; color: #64748b; cursor: not-allowed; border-color: #e2e8f0;">

                    <input type="hidden" name="reporter_id" value="<?= $_SESSION['user_id'] ?? 0 ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">รายละเอียดการปฏิบัติงาน / หมายเหตุ</label>
                    <textarea name="note" class="modern-input" rows="6"
                        placeholder="พิมพ์รายละเอียดงานที่นี่..."></textarea>
                </div>

                <button class="btn-submit" onclick="saveReport()">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // รวม Script ไว้ในที่เดียว ไม่ให้ตีกัน
        $(document).ready(function() {
            // Init Select2
            $('.select2-users').select2();

            // ตั้งค่า Datepicker (วันที่) เป็น DD/MM/YYYY
            flatpickr(".date-picker", {
                altInput: true,
                altFormat: "d/m/Y",  // แสดงเป็น 09/02/2026
                dateFormat: "Y-m-d", // ส่งค่าเข้า DB เป็น Y-m-d
                locale: "th",
                defaultDate: "today"
            });
            
            // ตั้งค่า Timepicker (เวลา)
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
            let formData = new FormData(document.getElementById('dailyForm'));

            // เช็คค่าว่าง
            if (!formData.get('note').trim()) {
                Swal.fire({ icon: 'warning', title: 'แจ้งเตือน', text: 'กรุณากรอกรายละเอียดงาน' });
                return;
            }

            Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });

            $.ajax({
                url: 'hr_create_report.php',
                type: 'POST',
                data: formData,
                processData: false, contentType: false, dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success', title: 'บันทึกเรียบร้อย',
                            timer: 1500, showConfirmButton: false
                        }).then(() => {
                            location.reload(); // รีโหลดเพื่อโชว์ประวัติล่าสุด
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }
            });
        }
    </script>

</body>

</html>