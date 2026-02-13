<?php
// -----------------------------------------------------------
// 1. รับค่าตัวกรอง (Filter Logic)
// -----------------------------------------------------------
$default_start = date('Y-m-01');
$default_end   = date('Y-m-d');

// รับค่าวันที่จาก URL (ถ้าไม่มีใช้ค่า Default)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start;
$end_date   = isset($_GET['end_date'])   ? $_GET['end_date']   : $default_end;

// ดึง ID ของคนที่ Login อยู่
$my_id = $_SESSION['user_id'];

// สร้างเงื่อนไข SQL: กรองตามวันที่ AND เป็นของฉันเท่านั้น
$where_sql = " WHERE (report_date BETWEEN '$start_date' AND '$end_date') 
               AND reporter_id = $my_id ";

// -----------------------------------------------------------
// 2. ดึงข้อมูล (Data Fetching)
// -----------------------------------------------------------

// ไม่ต้องดึงรายชื่อพนักงาน ($users_opt) แล้ว เพราะไม่ได้ใช้

// ดึงรายการรายงาน (ใส่ Filter)
$sql_hr = "SELECT r.*, u.fullname 
           FROM hr_daily_reports r 
           LEFT JOIN users u ON r.reporter_id = u.id 
           $where_sql 
           ORDER BY r.report_date DESC, r.report_time DESC";
$res_hr = $conn->query($sql_hr);
?>

<div class="filter-section" style="margin-bottom: 20px;">
    <form class="filter-form" method="GET" action="">
        
        <input type="hidden" name="tab" value="hr">

        <div class="form-group">
            <label class="form-label">ตั้งแต่วันที่</label>
            <input type="text" name="start_date" class="form-control date-picker" value="<?= $start_date ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">ถึงวันที่</label>
            <input type="text" name="end_date" class="form-control date-picker" value="<?= $end_date ?>">
        </div>
        
        <div class="btn-search-wrapper">
            <button type="submit" class="btn-search" style="background: #4f46e5;">
                <i class="fas fa-search"></i> ค้นหา
            </button>
            <a href="?tab=hr" class="link-reset">
                <i class="fas fa-redo"></i> รีเซ็ต
            </a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table id="hrTable">
            <thead>
                <tr>
                    <th width="15%">วันที่ / เวลา</th>
                    <th width="20%">สถานะ</th> <th width="50%">รายละเอียด (Note)</th>
                    <th width="10%" class="text-center">ดูข้อมูล</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res_hr && $res_hr->num_rows > 0): ?>
                    <?php while ($row = $res_hr->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; color:var(--text-main);">
                                    <?= date('d/m/Y', strtotime($row['report_date'])) ?>
                                </div>
                                <div style="font-size:13px; color:var(--text-muted);">
                                    <i class="far fa-clock"></i> <?= date('H:i', strtotime($row['report_time'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="status-badge" style="background:#e0e7ff; color:#4338ca;">
                                    <i class="fas fa-user-check"></i> รายงานของฉัน
                                </div>
                            </td>
                            <td>
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size:14px; color:var(--text-muted); line-height:1.5;">
                                    <?= htmlspecialchars($row['note']) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <button class="btn-view" onclick='showDetail(<?= json_encode($row) ?>, "HR")'>
                                    <i class="fas fa-search-plus"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center" style="padding:40px; color:var(--text-muted);">
                            <i class="fas fa-inbox" style="font-size:2rem; opacity:0.3; margin-bottom:10px; display:block;"></i>
                            ไม่พบประวัติการรายงานของคุณในช่วงเวลานี้
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Init Flatpickr (ไม่ต้อง Init Select2 แล้วเพราะไม่มี Dropdown)
        flatpickr(".date-picker", { altInput: true, altFormat: "d/m/Y", dateFormat: "Y-m-d", locale: "th" });
    });
</script>