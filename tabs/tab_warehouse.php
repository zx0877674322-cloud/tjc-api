<?php
// -----------------------------------------------------------
// 1. รับค่าตัวกรอง (Filter Logic)
// -----------------------------------------------------------
$default_start = date('Y-m-01');
$default_end   = date('Y-m-d');

// รับค่าจาก $_GET
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start;
$end_date   = isset($_GET['end_date'])   ? $_GET['end_date']   : $default_end;

// ดึง ID ของฉัน (เฉพาะข้อมูลตัวเอง)
$my_id = $_SESSION['user_id'];

// สร้างเงื่อนไข SQL
$where_sql = " WHERE (report_date BETWEEN '$start_date' AND '$end_date') 
               AND reporter_id = $my_id ";

// -----------------------------------------------------------
// 2. ดึงข้อมูล (Data Fetching)
// -----------------------------------------------------------
$sql_wh = "SELECT r.*, u.fullname 
           FROM warehouse_daily_reports r 
           LEFT JOIN users u ON r.reporter_id = u.id 
           $where_sql 
           ORDER BY r.report_date DESC, r.report_time DESC";
$res_wh = $conn->query($sql_wh);
?>

<div class="filter-section" style="margin-bottom: 20px;">
    <form class="filter-form" method="GET" action="">
        
        <input type="hidden" name="tab" value="warehouse">

        <div class="form-group">
            <label class="form-label">ตั้งแต่วันที่</label>
            <input type="text" name="start_date" class="form-control date-picker" value="<?= $start_date ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">ถึงวันที่</label>
            <input type="text" name="end_date" class="form-control date-picker" value="<?= $end_date ?>">
        </div>

        <div class="btn-search-wrapper">
            <button type="submit" class="btn-search" style="background: #0d9488;">
                <i class="fas fa-search"></i> ค้นหา
            </button>
            <a href="?tab=warehouse" class="link-reset">
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
                    <th width="15%" style="color:#134e4a; background:#ccfbf1;">วัน-เวลา</th>
                    <th width="20%" style="color:#134e4a; background:#ccfbf1;">สถานะ</th>
                    <th width="55%" style="color:#134e4a; background:#ccfbf1;">รายละเอียดงาน (Note)</th>
                    <th width="10%" style="color:#134e4a; background:#ccfbf1;" class="text-center">ดูข้อมูล</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res_wh && $res_wh->num_rows > 0): ?>
                    <?php while ($row = $res_wh->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; color:var(--text-main);">
                                    <?php 
                                        // แสดงปี พ.ศ.
                                        echo date('d/m/', strtotime($row['report_date'])) . (date('Y', strtotime($row['report_date'])) + 543);
                                    ?>
                                </div>
                                <div style="font-size:13px; color:var(--text-muted); margin-top:2px;">
                                    <i class="far fa-clock"></i> <?= date('H:i', strtotime($row['report_time'])) ?> น.
                                </div>
                            </td>
                            <td>
                                <div class="status-badge" style="background:#ccfbf1; color:#0f766e;">
                                    <i class="fas fa-warehouse"></i> งานของฉัน
                                </div>
                            </td>
                            <td>
                                <div style="font-size:14px; color:var(--text-main); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height:1.5;">
                                    <?= htmlspecialchars($row['note']) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <button class="btn-view" onclick='showDetail(<?= json_encode($row) ?>, "WAREHOUSE")' style="border-color:#5eead4; color:#0d9488;">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center" style="padding:50px; color:var(--text-muted);">
                            <i class="fas fa-dolly-flatbed" style="font-size:2rem; opacity:0.3; margin-bottom:10px; display:block;"></i>
                            ไม่พบประวัติงานคลังของคุณในช่วงเวลานี้
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function() {
        flatpickr(".date-picker", { altInput: true, altFormat: "d/m/Y", dateFormat: "Y-m-d", locale: "th" });
    });
</script>