<?php
// -----------------------------------------------------------
// 1. รับค่าตัวกรอง (Filter Logic)
// -----------------------------------------------------------
$default_start = date('Y-m-01');
$default_end   = date('Y-m-d');

// รับค่าจาก $_GET
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

// ดึงรายการจัดส่ง (ใส่ Filter)
$sql_del = "SELECT r.*, u.fullname 
           FROM delivery_daily_reports r 
           LEFT JOIN users u ON r.reporter_id = u.id 
           $where_sql 
           ORDER BY r.report_date DESC, r.report_time DESC";
$res_del = $conn->query($sql_del);
?>

<div class="filter-section" style="margin-bottom: 20px;">
    <form class="filter-form" method="GET" action="">
        
        <input type="hidden" name="tab" value="delivery">

        <div class="form-group">
            <label class="form-label">ตั้งแต่วันที่</label>
            <input type="text" name="start_date" class="form-control date-picker" value="<?= $start_date ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">ถึงวันที่</label>
            <input type="text" name="end_date" class="form-control date-picker" value="<?= $end_date ?>">
        </div>

        <div class="btn-search-wrapper">
            <button type="submit" class="btn-search" style="background: #f59e0b;">
                <i class="fas fa-search"></i> ค้นหา
            </button>
            <a href="?tab=delivery" class="link-reset">
                <i class="fas fa-redo"></i> รีเซ็ต
            </a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th width="15%" style="color:#92400e; background:#fef3c7;">วัน-เวลา</th>
                    <th width="20%" style="color:#92400e; background:#fef3c7;">สถานะ</th>
                    <th width="55%" style="color:#92400e; background:#fef3c7;">รายละเอียดงาน (Note)</th>
                    <th width="10%" style="color:#92400e; background:#fef3c7;" class="text-center">ดูข้อมูล</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res_del && $res_del->num_rows > 0): ?>
                    <?php while ($row = $res_del->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; color:var(--text-main);">
                                    <?= date('d/m/Y', strtotime($row['report_date'])) ?>
                                </div>
                                <div style="font-size:13px; color:var(--text-muted); margin-top:2px;">
                                    <i class="far fa-clock"></i> <?= date('H:i', strtotime($row['report_time'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="status-badge" style="background:#fff7ed; color:#c2410c;">
                                    <i class="fas fa-truck"></i> งานของฉัน
                                </div>
                            </td>
                            <td>
                                <div style="font-size:14px; color:var(--text-main); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height:1.5;">
                                    <?= htmlspecialchars($row['note']) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <button class="btn-view" onclick='showDetail(<?= json_encode($row) ?>, "DELIVERY")' style="border-color:#fdba74; color:#ea580c;">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center" style="padding:50px; color:var(--text-muted);">
                            <i class="fas fa-shipping-fast" style="font-size:2rem; opacity:0.3; margin-bottom:10px; display:block;"></i>
                            ไม่พบประวัติการส่งงานของคุณในช่วงเวลานี้
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Init Flatpickr (ไม่ต้อง Init Select2 แล้ว)
        flatpickr(".date-picker", { altInput: true, altFormat: "d/m/Y", dateFormat: "Y-m-d", locale: "th" });
    });
</script>