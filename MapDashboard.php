<?php
session_start();
require_once 'auth.php';
date_default_timezone_set('Asia/Bangkok'); 

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['fullname'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once 'db_connect.php';

// ============================================
// ส่วนที่ 1: ตัวกรอง (Filter)
// ============================================
$filter_name = isset($_GET['filter_name']) ? $_GET['filter_name'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; 
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where_sql = "WHERE r.gps != '' AND r.gps != 'Office'";

if (!empty($filter_name)) { $where_sql .= " AND r.reporter_name = '$filter_name'"; }
if (!empty($start_date)) { $where_sql .= " AND r.report_date >= '$start_date'"; }
if (!empty($end_date)) { $where_sql .= " AND r.report_date <= '$end_date'"; }

// ดึงรายชื่อพนักงาน
$sql_users = "SELECT DISTINCT reporter_name FROM reports ORDER BY reporter_name ASC";
$result_users = $conn->query($sql_users);

// ============================================
// ส่วนที่ 2: จัดการสีสถานะ
// ============================================
$sql_master = "SELECT * FROM master_job_status ORDER BY id ASC";
$res_master = $conn->query($sql_master);

$fixed_colors = [
    'ได้งาน' => '#2ecc71',        
    'กำลังติดตาม' => '#f1c40f', 
    'ไม่ได้งาน' => '#e74c3c',     
    'เข้าเสนอโครงการ' => '#3498db' 
];
$status_config = [];
$palette = ['#9b59b6', '#e67e22', '#1abc9c', '#34495e', '#7f8c8d', '#c0392b'];
$palette_index = 0;

if ($res_master) {
    while($row = $res_master->fetch_assoc()) {
        $name = $row['status_name'];
        if (isset($fixed_colors[$name])) {
            $status_config[$name] = $fixed_colors[$name];
        } else {
            $status_config[$name] = $palette[$palette_index % count($palette)];
            $palette_index++;
        }
    }
}

// ============================================
// ส่วนที่ 3: ดึงข้อมูล Report
// ============================================
$sql = "SELECT r.id, r.reporter_name, r.project_name, r.work_result, r.job_status, r.gps, r.report_date, r.total_expense,
                u.avatar 
        FROM reports r 
        LEFT JOIN users u ON r.reporter_name = u.fullname 
        $where_sql 
        ORDER BY r.report_date DESC";

$result = $conn->query($sql);

$locations = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $coords = explode(',', $row['gps']);
        if (count($coords) == 2) {
            $row['lat'] = trim($coords[0]);
            $row['lng'] = trim($coords[1]);
            $row['expense_fmt'] = number_format($row['total_expense']);
            
            // จัดการรูปภาพ
            if (!empty($row['avatar']) && file_exists('uploads/profiles/' . $row['avatar'])) {
                $row['avatar_url'] = 'uploads/profiles/' . $row['avatar'];
            } else {
                $row['avatar_url'] = 'https://ui-avatars.com/api/?name='.urlencode($row['reporter_name']).'&background=random&color=fff'; 
            }
            $locations[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แผนที่ติดตามงาน - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* จัด Layout Global */
        :root {
            --sidebar-width: 320px;
        }
        body { 
            font-family: 'Prompt', sans-serif; margin: 0; padding: 0; 
            display: flex; flex-direction: column; height: 100vh; overflow: hidden;
            background-color: #f8f9fa;
        }
        
        .main-content { 
            flex: 1; display: flex; flex-direction: column;
            overflow: hidden; 
        }

        /* Navbar */
        .navbar { 
            background: #ffffff; color: #333; 
            padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 1002; border-bottom: 1px solid #eee;
            flex-shrink: 0;
        }
        .navbar h2 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #4e54c8; display: flex; align-items: center; gap: 10px; }

        /* Filter Bar */
        .filter-bar { 
            background: #ffffff; padding: 10px 20px; border-bottom: 1px solid #eee; 
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap; z-index: 1001;
            flex-shrink: 0;
        }
        .filter-bar select, .filter-bar input { 
            padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px; 
            font-family: 'Prompt'; font-size: 0.85rem; background-color: #fff;
        }
        .btn-search { background: #4e54c8; color: white !important; border: none; padding: 6px 15px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 0.85rem; }
        .btn-reset { background: #f1f1f1; color: #666; text-decoration: none; padding: 6px 15px; border-radius: 6px; font-size: 0.85rem; border: 1px solid #ddd; }

        /* --- Map Wrapper & Sidebar Toggle Logic --- */
        .map-wrapper {
            display: flex;
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        /* Sidebar List */
        .sidebar-list {
            width: var(--sidebar-width);
            background: #fff;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            /* Animation Transition */
            transition: margin-left 0.3s ease;
        }
        
        /* Class สำหรับซ่อน Sidebar */
        .sidebar-list.collapsed {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        .list-header {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: #555;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ปุ่มปิด Sidebar */
        .btn-toggle-close {
            cursor: pointer;
            color: #999;
            padding: 5px;
            transition: color 0.2s;
        }
        .btn-toggle-close:hover { color: #333; }

        /* ปุ่มเปิด Sidebar (จะโชว์เมื่อ Sidebar ถูกซ่อน) */
        .btn-toggle-open {
            position: absolute;
            top: 10px; left: 10px;
            z-index: 998; /* อยู่ใต้ Navbar แต่อยู่บน Map */
            background: white;
            border: 1px solid #ccc;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            font-size: 0.9rem;
            color: #555;
            display: none; /* ซ่อนไว้ก่อน */
            align-items: center; gap: 5px;
        }
        .btn-toggle-open:hover { background: #f0f0f0; }

        /* กฎการแสดงปุ่มเปิด: เมื่อ sidebar มี class 'collapsed' ให้ปุ่มเปิดแสดงขึ้นมา */
        .sidebar-list.collapsed ~ .btn-toggle-open {
            display: flex;
        }

        /* Job Card Styling */
        .job-card {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 12px;
        }
        .job-card:hover { background-color: #f0f4ff; }
        .job-card.active { background-color: #eef2ff; border-left: 4px solid #4e54c8; }

        .job-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .job-info { flex: 1; min-width: 0; }
        .job-name { font-weight: 600; font-size: 0.9rem; color: #333; margin-bottom: 2px; }
        .job-project { font-size: 0.8rem; color: #4e54c8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;}
        .job-meta { font-size: 0.75rem; color: #888; display: flex; justify-content: space-between; }
        .job-status-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; color: white; display: inline-block; margin-top: 5px; }

        /* Map Container */
        #map { flex: 1; height: 100%; width: 100%; transition: width 0.3s ease; }

        /* Legend */
        .legend { 
            background: rgba(255, 255, 255, 0.95); padding: 10px 15px; position: absolute; bottom: 25px; right: 15px; 
            z-index: 999; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); font-size: 0.8rem; 
            border: 1px solid #eee;
        }
        .legend h4 { margin: 0 0 8px 0; font-size: 0.9rem; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .legend-item { display: flex; align-items: center; margin-bottom: 5px; }
        .color-dot { width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; display: inline-block; }
        
        /* Popup Styling */
        .popup-content { font-family: 'Prompt', sans-serif; width: 220px; }
        .popup-header { text-align: center; margin-bottom: 8px; border-bottom: 1px dashed #eee; padding-bottom: 8px; }
        .popup-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .popup-row { font-size: 13px; color: #333; margin-bottom: 3px; display:flex; }
        .popup-label { font-weight: bold; margin-right: 5px; color: #666; min-width: 50px; }
        .popup-value { flex:1; }

        /* Style ปุ่มดูจุดพิกัด */
        .btn-navigate {
            display: inline-block;
            background-color: #555;
            color: #fff !important;
            padding: 6px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            margin-top: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: background 0.2s;
        }
        .btn-navigate:hover { background-color: #333; }

        /* Marker Icons */
        .avatar-pin { position: relative; transition: transform 0.2s; }
        .avatar-pin:hover { transform: scale(1.1); z-index: 9999 !important; }
        .avatar-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; box-shadow: 0 3px 6px rgba(0,0,0,0.3); background: white; }
        .pin-tip { position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 7px solid white; }

        @media (max-width: 768px) {
            .sidebar-list { position: absolute; height: 100%; top: 0; left: 0; width: 85%; transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar-list.mobile-show { transform: translateX(0); margin-left: 0; }
            /* Mobile Logic แยกต่างหาก */
            .sidebar-list.collapsed { margin-left: 0; } /* Reset Desktop style */
            .btn-toggle-open { display: flex; } /* Show button always on mobile to open menu */
        }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="navbar">
            <h2><i class="fas fa-map-marked-alt"></i> แผนที่ติดตามงาน</h2>
        </div>

        <div class="filter-bar">
            <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%;">
                <select name="filter_name">
                    <option value="">-- พนักงานทั้งหมด --</option>
                    <?php 
                    if ($result_users && $result_users->num_rows > 0) {
                        while($user = $result_users->fetch_assoc()) {
                            $selected = ($filter_name == $user['reporter_name']) ? 'selected' : '';
                            echo "<option value='".$user['reporter_name']."' $selected>".$user['reporter_name']."</option>";
                        }
                    }
                    ?>
                </select>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> ค้นหา</button>
                <a href="MapDashboard.php" class="btn-reset"><i class="fas fa-sync-alt"></i> รีเซ็ต</a>
            </form>
        </div>

        <div class="map-wrapper">
            
            <div class="sidebar-list" id="sidebarList">
                <div class="list-header">
                    <span><i class="fas fa-list"></i> รายการงาน (<?php echo count($locations); ?>)</span>
                    <i class="fas fa-chevron-left btn-toggle-close" onclick="toggleSidebar()" title="ซ่อนรายการ"></i>
                </div>
                
                <?php if (count($locations) > 0): ?>
                    <?php foreach ($locations as $index => $loc): 
                         $color = isset($status_config[$loc['job_status']]) ? $status_config[$loc['job_status']] : '#999';
                         $timestamp = strtotime($loc['report_date']) + (7 * 60 * 60);
                    ?>
                    <div class="job-card" onclick="focusMap(<?php echo $loc['lat']; ?>, <?php echo $loc['lng']; ?>, <?php echo $index; ?>)">
                        <img src="<?php echo $loc['avatar_url']; ?>" class="job-avatar" style="border-color: <?php echo $color; ?>">
                        <div class="job-info">
                            <div class="job-name"><?php echo $loc['reporter_name']; ?></div>
                            <div class="job-project"><?php echo $loc['project_name'] ? $loc['project_name'] : '-'; ?></div>
                            <div class="job-meta">
                                <span style="color: #666;">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', $timestamp); ?>
                                </span>
                                <span class="job-status-badge" style="background: <?php echo $color; ?>"><?php echo $loc['job_status']; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding:20px; text-align:center; color:#999;">ไม่พบข้อมูล</div>
                <?php endif; ?>
            </div>

            <div class="btn-toggle-open" onclick="toggleSidebar()">
                <i class="fas fa-list"></i> แสดงรายการ
            </div>

            <div id="map"></div>

            <div class="legend">
                <h4>สถานะงาน</h4>
                <?php foreach ($status_config as $status => $color): ?>
                    <div class="legend-item">
                        <span class="color-dot" style="background-color: <?php echo $color; ?>;"></span>
                        <?php echo $status; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Map Setup
        var googleStreets = L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',{ maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3'] });
        var map = L.map('map', { center: [13.7563, 100.5018], zoom: 6, layers: [googleStreets], zoomControl: false }); 
        L.control.zoom({ position: 'topright' }).addTo(map); 

        // Data from PHP
        const locations = <?php echo json_encode($locations); ?>;
        const statusColors = <?php echo json_encode($status_config); ?>;
        var markers = []; 

        function createAvatarIcon(imgUrl, color) {
            return L.divIcon({
                className: 'avatar-pin',
                html: `<div style="position:relative;">
                        <img src="${imgUrl}" class="avatar-img" style="border: 3px solid ${color};">
                        <div class="pin-tip" style="border-top-color: ${color};"></div>
                       </div>`,
                iconSize: [46, 46], iconAnchor: [23, 50], popupAnchor: [0, -45]
            });
        }

        var bounds = [];
        locations.forEach((loc, index) => {
            var color = statusColors[loc.job_status] || '#95a5a6';
            
            var popupHtml = `
                <div class="popup-content">
                    <div class="popup-header">
                        <img src="${loc.avatar_url}" class="popup-avatar" style="border-color:${color}">
                        <div style="margin-top:5px; font-weight:bold;">${loc.reporter_name}</div>
                    </div>
                    <div class="popup-row"><span class="popup-label">โครงการ:</span> <span class="popup-value" style="color:#4e54c8;">${loc.project_name || '-'}</span></div>
                    <div class="popup-row"><span class="popup-label">งาน:</span> <span class="popup-value">${loc.work_result}</span></div>
                    <div class="popup-row"><span class="popup-label">วันที่:</span> <span class="popup-value">${new Date(loc.report_date).toLocaleDateString('th-TH')}</span></div>
                    <div style="text-align:center;">
                        <a href="https://maps.google.com/?q=${loc.lat},${loc.lng}" target="_blank" class="btn-navigate">
                           <i class="fas fa-map-marker-alt"></i> ดูจุดพิกัด
                        </a>
                    </div>
                </div>
            `;

            var marker = L.marker([loc.lat, loc.lng], { icon: createAvatarIcon(loc.avatar_url, color) })
                          .bindPopup(popupHtml);
            
            marker.addTo(map);
            markers.push(marker); 
            bounds.push([loc.lat, loc.lng]);
        });

        if (bounds.length > 0) { map.fitBounds(bounds, { padding: [50, 50] }); }

        function focusMap(lat, lng, index) {
            // เช็คว่าถ้า Mobile ให้ปิด sidebar อัตโนมัติหลังกดเลือกw
            if (window.innerWidth <= 768) {
                toggleSidebar(); 
            }

            map.flyTo([lat, lng], 15, { animate: true, duration: 1.5 });
            setTimeout(() => { markers[index].openPopup(); }, 300);

            document.querySelectorAll('.job-card').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.job-card')[index].classList.add('active');
        }

        // --- ฟังก์ชันสำหรับ หุบ/กาง แถบรายการ ---
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebarList');
            
            // สำหรับ Mobile (ใช้ class mobile-show)
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-show');
            } else {
                // สำหรับ Desktop (ใช้ class collapsed)
                sidebar.classList.toggle('collapsed');
            }

            // สำคัญ! ต้องสั่งให้ Map คำนวณขนาดใหม่หลังจาก Animation จบ
            setTimeout(function() {
                map.invalidateSize();
            }, 300); // 300ms ตรงกับ transition css
        }
    </script>
</body>
</html>