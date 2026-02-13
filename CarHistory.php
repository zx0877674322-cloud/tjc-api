<?php
session_start();
// require_once 'auth.php'; // เปิดใช้งานถ้ามีไฟล์นี้
require_once 'db_connect.php';
require_once 'CarManager.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// --- ส่วนจัดการข้อมูล (Data Logic) ---
$carMgr = new CarManager($conn);
$history = $carMgr->getUserHistory($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการใช้รถ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* --- COPY COLORS FROM DASHBOARD (เหมือนเป๊ะ 100%) --- */
        :root {
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --bg-input: #ffffff;
            --text-main: #1e293b;
            --text-sub: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --hover-bg: #eff6ff;

            --table-head-bg: #f8fafc;
            --table-head-text: #64748b;
            --table-row-hover: #f8faff;
            
            --badge-bg-light: #f1f5f9;
        }

        body.dark-mode {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --text-main: #f8fafc;
            --text-sub: #cbd5e1;
            --border-color: #334155;
            --shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            
            --primary-color: #60a5fa;
            --primary-dark: #3b82f6;
            --hover-bg: #334155;

            --table-head-bg: #1e293b; 
            --table-head-text: #e2e8f0; 
            --table-row-hover: #334155;

            --badge-bg-light: #0f172a;
        }

        /* --- Global Overrides (ส่วนสำคัญที่ทำให้สีตัวอักษรเหมือนกัน) --- */
        body { font-family: 'Prompt', sans-serif; background-color: var(--bg-body); color: var(--text-main); transition: 0.3s; margin: 0; }
        
        /* บังคับให้ Class Bootstrap ใช้สีจากตัวแปรของเรา */
        h1, h2, h3, h4, h5, h6, .text-dark { color: var(--text-main) !important; }
        .text-muted, .text-secondary { color: var(--text-sub) !important; }

        /* --- Card Styles --- */
        .card { background-color: var(--bg-card) !important; border: 1px solid var(--border-color) !important; box-shadow: var(--shadow) !important; border-radius: 12px; }
        .card-header { background-color: var(--bg-card) !important; border-bottom: 1px solid var(--border-color) !important; }
        
        /* --- Table Styles --- */
        .table { --bs-table-bg: transparent; --bs-table-hover-bg: transparent; margin-bottom: 0; white-space: nowrap; }
        th { background-color: var(--table-head-bg) !important; color: var(--table-head-text) !important; border-bottom: 2px solid var(--border-color) !important; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; padding: 15px !important; }
        td { background-color: transparent !important; color: var(--text-main) !important; border-bottom: 1px solid var(--border-color) !important; padding: 15px !important; vertical-align: middle; }
        
        /* --- Timeline Indicator --- */
        .timeline-indicator { display: flex; flex-direction: column; gap: 4px; position: relative; }
        .timeline-indicator::before { content: ''; position: absolute; left: 5px; top: 8px; bottom: 8px; width: 2px; background: var(--border-color); z-index: 0; }
        .time-row { display: flex; align-items: center; position: relative; z-index: 1; }
        .dot { width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--bg-card); flex-shrink: 0; }
        .dot.start { background-color: #10b981; } 
        .dot.end { background-color: #ef4444; }
        
        /* --- Utilities --- */
        .badge.bg-light { background-color: var(--badge-bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        .text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-dark); border-color: var(--primary-dark); }
        .text-primary { color: var(--primary-color) !important; }

        /* --- [NEW] Clickable Text & Modal Styles --- */
        .clickable-text { cursor: pointer; transition: color 0.2s; }
        .clickable-text:hover { color: var(--primary-color) !important; opacity: 0.8; }
        
        /* Modal Style Override for Dark Mode */
        .modal-content { background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
        .modal-header { border-bottom: 1px solid var(--border-color); }
        .modal-footer { border-top: 1px solid var(--border-color); }
        .btn-close { filter: invert(var(--bs-btn-close-white-filter, 0)); }
        body.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    </style>
</head>
<body>

    <div class="d-flex">
        
        <?php include 'sidebar.php'; ?>

        <div class="flex-grow-1" style="min-height: 100vh; overflow-y: auto;">
            <div class="container-fluid p-4">
                
                <div class="card shadow-sm border-0 overflow-hidden">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5 class="m-0 fw-bold text-primary"><i class="fas fa-history me-2"></i>ประวัติการจองของฉัน</h5>
                        <a href="CarBooking.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm text-white">
                            <i class="fas fa-plus me-1"></i> จองรถใหม่
                        </a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="small text-uppercase">
                                    <th class="ps-4">ช่วงเวลาที่ใช้</th>
                                    <th>รถที่ใช้</th>
                                    <th>สถานที่</th>
                                    <th>ภารกิจ</th>
                                    <th>หมายเหตุ</th> <th class="text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($history)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-secondary">ยังไม่มีประวัติการจอง</td></tr>
                                <?php else: ?>
                                    <?php foreach ($history as $h): ?>
                                    <?php 
                                        // [Logic แยกข้อมูลหมายเหตุ]
                                        $parsed_issue = '-';
                                        if (!empty($h['return_note'])) {
                                            $parts = explode('|', $h['return_note']);
                                            foreach ($parts as $p) {
                                                $p = trim($p);
                                                if (strpos($p, 'หมายเหตุ') !== false) {
                                                    $temp = explode(':', $p);
                                                    if (isset($temp[1])) {
                                                        $parsed_issue = trim($temp[1]);
                                                    }
                                                }
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="timeline-indicator">
                                                <div class="time-row">
                                                    <div class="dot start me-2"></div>
                                                    <small class="text-muted" style="width: 35px;">ออก</small>
                                                    <span class="fw-bold" style="font-family: monospace; font-size: 0.9rem; color: var(--text-main);">
                                                        <?php echo !empty($h['start_date']) ? date('d/m H:i', strtotime($h['start_date'])) . ' น.' : '-'; ?>
                                                    </span>
                                                </div>
                                                <div class="time-row">
                                                    <div class="dot end me-2"></div>
                                                    <small class="text-muted" style="width: 35px;">คืน</small>
                                                    <span class="fw-bold" style="font-family: monospace; font-size: 0.9rem; color: var(--text-main);">
                                                        <?php 
                                                            $endDate = !empty($h['end_date']) ? $h['end_date'] : null;
                                                            echo $endDate ? date('d/m H:i', strtotime($endDate)) . ' น.' : '-'; 
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light border text-dark"><?php echo $h['car_name'] ?? '-'; ?></span>
                                            <div class="small text-secondary ms-1 mt-1"><?php echo $h['plate'] ?? '-'; ?></div>
                                        </td>
                                        
                                        <td>
                                            <div class="text-truncate clickable-text" style="max-width: 150px;" 
                                                 title="คลิกเพื่อดูข้อความเต็ม"
                                                 onclick="showFullText('สถานที่', '<?php echo htmlspecialchars($h['destination'] ?? '-', ENT_QUOTES); ?>')">
                                                <i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo $h['destination'] ?? '-'; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="text-truncate text-secondary clickable-text" style="max-width: 200px;" 
                                                 title="คลิกเพื่อดูข้อความเต็ม"
                                                 onclick="showFullText('ภารกิจ', '<?php echo htmlspecialchars($h['reason'] ?? '-', ENT_QUOTES); ?>')">
                                                <i class="fas fa-tasks text-info me-1"></i> <?php echo $h['reason'] ?? '-'; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="text-truncate text-secondary small" style="max-width: 200px; min-width: 150px;">
                                                <?php 
                                                    if($parsed_issue !== '-') {
                                                        ?>
                                                        <div class="clickable-text" onclick="showFullText('หมายเหตุ', '<?php echo htmlspecialchars($parsed_issue, ENT_QUOTES); ?>')" title="คลิกเพื่อดูข้อความเต็ม">
                                                            <i class="fas fa-comment-alt me-1 text-warning"></i> <?php echo $parsed_issue; ?>
                                                        </div>
                                                        <?php
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </div>
                                        </td>

                                        <td class="text-center">
                                            <?php 
                                                $status = $h['status'] ?? 'unknown';
                                                if($status == 'active'): 
                                            ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-circle fa-xs me-1"></i> กำลังใช้งาน</span>
                                            <?php elseif($status == 'completed'): ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-3 py-2 rounded-pill"><i class="fas fa-check me-1"></i> คืนแล้ว</span>
                                            <?php elseif($status == 'pending'): ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i> รออนุมัติ</span>
                                            <?php elseif($status == 'approved'): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-3 py-2 rounded-pill"><i class="fas fa-check-double me-1"></i> อนุมัติแล้ว</span>
                                            <?php elseif($status == 'rejected'): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-times me-1"></i> ไม่อนุมัติ</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary text-light"><?php echo $status; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> 
        </div>
    </div>

    <div class="modal fade" id="textDetailModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTitle">รายละเอียด</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modalBody" class="m-0" style="white-space: pre-wrap; line-height: 1.6;"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // เช็ค Theme ที่บันทึกไว้เพื่อปรับสี Background ของ Body ให้ตรงกัน
            const savedTheme = localStorage.getItem('tjc_theme') || 'light';
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
            }
        });

        // [NEW] ฟังก์ชันแสดงข้อความเต็มใน Modal
        function showFullText(title, text) {
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalBody').innerText = text;
            
            var myModal = new bootstrap.Modal(document.getElementById('textDetailModal'));
            myModal.show();
        }
    </script>
</body>
</html>