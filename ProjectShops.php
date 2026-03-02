<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

$site_id = isset($_GET['site_id']) ? intval($_GET['site_id']) : 0;

// ---------------------------------------------------------
//  1. FUNCTIONS
// ---------------------------------------------------------
function getDistance($lat1, $lon1, $lat2, $lon2)
{
    if (($lat1 == $lat2) && ($lon1 == $lon2))
        return 0;
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return number_format($miles * 1.609344, 2);
}

// ---------------------------------------------------------
//  2. HANDLE POST
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'save_site_location') {
        $lat = $_POST['site_lat'];
        $lon = $_POST['site_lon'];
        $sql = "INSERT INTO site_locations (site_id, latitude, longitude) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idd", $site_id, $lat, $lon);
        echo json_encode(['status' => $stmt->execute() ? 'success' : 'error']);
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] == 'add_shop') {
        $name = $_POST['shop_name'];
        $contact = $_POST['contact_name'];
        $phone = $_POST['phone'];
        $lat = $_POST['latitude'];
        $lon = $_POST['longitude'];
        $remark = $_POST['remark'];
        $stmt = $conn->prepare("INSERT INTO nearby_shops (site_id, shop_name, contact_name, phone, latitude, longitude, remark) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issssss", $site_id, $name, $contact, $phone, $lat, $lon, $remark);
        echo json_encode(['status' => $stmt->execute() ? 'success' : 'error']);
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] == 'delete_shop') {
        $shop_id = intval($_POST['shop_id']);
        $conn->query("DELETE FROM nearby_shops WHERE id = $shop_id");
        echo json_encode(['status' => 'success']);
        exit;
    }
    // --- [แก้ไข] ปรับ Zoom=14 เพื่อบังคับให้ดึง "ตำบล" ออกมาให้ได้ ---
    if (isset($_POST['action']) && $_POST['action'] == 'get_address') {
        $lat = $_POST['lat'];
        $lon = $_POST['lon'];

        // ปรับ zoom เป็น 14 เพื่อให้ระบบมองหา "เขตการปกครอง (ตำบล)" แทนการหา "บ้านเลขที่"
        $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=$lat&lon=$lon&zoom=14&addressdetails=1&accept-language=th";

        $options = ["http" => ["header" => "User-Agent: TJCLocalSystem/1.0\r\n"]];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result) {
            $data = json_decode($result, true);
            $addr = $data['address'] ?? [];
            $parts = [];

            $is_bkk = (strpos($addr['state'] ?? '', 'กรุงเทพ') !== false);

            // 1. ตำบล / แขวง (กวาดหาทุก key ที่เป็นไปได้)
            $tambon = $addr['suburb'] ?? $addr['quarter'] ?? $addr['neighbourhood'] ?? $addr['town'] ?? $addr['municipality'] ?? $addr['village'] ?? ''; // เพิ่ม village เป็นตัวกันเหนียว (แต่จะกรองชื่อ "บ้าน..." ออกทีหลังถ้าไม่ต้องการ)

            // คลีนคำนำหน้าทิ้งให้หมดก่อน
            $tambon = str_replace(['เทศบาลตำบล', 'เทศบาลเมือง', 'เทศบาลนคร', 'องค์การบริหารส่วนตำบล'], '', $tambon);

            // กรองกรณีที่ village ติดชื่อ "บ้าน..." มา แต่เราอยากได้ตำบล (อันนี้แก้ยากถ้า OSM ไม่มีข้อมูลจริง)
            // แต่ปกติ zoom=14 จะได้ชื่อตำบลที่ถูกต้องกว่า zoom=18

            if (!empty($tambon) && trim($tambon) !== '') {
                $prefix = $is_bkk ? "แขวง" : "ต.";
                $clean_name = str_replace(['ตำบล', 'แขวง'], '', $tambon);
                $parts[] = $prefix . trim($clean_name);
            }

            // 2. อำเภอ / เขต
            $amphoe = $addr['city_district'] ?? $addr['district'] ?? $addr['county'] ?? $addr['city'] ?? '';
            // บางที อำเภอเมือง ไปอยู่ใน key 'city'

            if (!empty($amphoe)) {
                $prefix = $is_bkk ? "เขต" : "อ.";
                $clean_name = str_replace(['อำเภอ', 'เขต'], '', $amphoe);
                // ป้องกันชื่อซ้ำ (เช่น ต.เมือง อ.เมือง)
                if (trim($clean_name) !== trim(str_replace(['ตำบล', 'แขวง'], '', $tambon))) {
                    $parts[] = $prefix . trim($clean_name);
                }
            }

            // 3. จังหวัด
            if (!empty($addr['state'])) {
                $clean_name = str_replace('จังหวัด', '', $addr['state']);
                $parts[] = "จ." . trim($clean_name);
            }

            // 4. รหัสไปรษณีย์
            if (!empty($addr['postcode'])) {
                $parts[] = $addr['postcode'];
            }

            $full_text = implode(" ", $parts);

            if (empty(trim($full_text))) {
                $full_text = $data['display_name'] ?? "ไม่พบข้อมูล";
            }

            header('Content-Type: application/json');
            echo json_encode(['display_name' => $full_text]);
            exit;
        } else {
            echo json_encode(['error' => 'Failed to fetch']);
            exit;
        }
    }
}

// ---------------------------------------------------------
//  3. FETCH DATA
// ---------------------------------------------------------
$cust_info = [];
$site_coords = [];
$has_location = false;

// 1. ดึงรายชื่อลูกค้าทั้งหมดมาแสดงใน Dropdown
$sql_list = "SELECT customer_id, customer_name, province, district FROM customers ORDER BY customer_name ASC";
$all_customers = $conn->query($sql_list);

if ($site_id > 0) {
    // 2. ดึงข้อมูลลูกค้าที่ถูกเลือก
    $sql = "SELECT customer_name, province, district, sub_district, address, phone_number 
            FROM customers WHERE customer_id = $site_id";
    $res_info = $conn->query($sql);
    if ($res_info && $res_info->num_rows > 0) {
        $cust_info = $res_info->fetch_assoc();
    }

    // 3. ดึงพิกัด (ใช้ site_id แทน customer_id ในตาราง site_locations)
    $res_loc = $conn->query("SELECT * FROM site_locations WHERE site_id = $site_id");
    if ($res_loc && $res_loc->num_rows > 0) {
        $site_coords = $res_loc->fetch_assoc();
        $has_location = true;
    }
}

$cust_name = isset($cust_info['customer_name']) ? $cust_info['customer_name'] : 'ไม่ระบุชื่อลูกค้า';
$province = isset($cust_info['province']) ? $cust_info['province'] : 'ไม่ระบุจังหวัด';
$shops = [];

if ($has_location) {
    $res = $conn->query("SELECT * FROM nearby_shops WHERE site_id = $site_id");
    while ($row = $res->fetch_assoc()) {
        $row['distance'] = getDistance($site_coords['latitude'], $site_coords['longitude'], $row['latitude'], $row['longitude']);
        $shops[] = $row;
    }
    usort($shops, function ($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title>บริหารจัดการร้านช่างใกล้เคียง</title>

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="css/ProjectShops.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">

            <div class="page-header-3d">
                <div class="header-title">
                    <h2><i class="fas fa-map-marked-alt" style="color:var(--primary);"></i> ร้านช่างใกล้เคียง</h2>
                    <span>จัดการพิกัดลูกค้า และค้นหาร้านซ่อมในพื้นที่</span>
                </div>

                <div class="header-search-wrapper">
                    <select class="form-control select2-search"
                        onchange="window.location.href='ProjectShops.php?site_id='+this.value">
                        <option value="">-- ค้นหารายชื่อลูกค้า --</option>
                        <?php while ($c = $all_customers->fetch_assoc()): ?>
                            <option value="<?= $c['customer_id'] ?>" <?= ($site_id == $c['customer_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['customer_name']) ?>
                                (<?= htmlspecialchars($c['province']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <?php if ($site_id > 0): ?>
                <div class="info-panel-3d">
                    <div class="info-content">
                        <h3><?= htmlspecialchars($cust_name) ?></h3>
                        <p>
                            <span class="info-badge"><i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($province) ?></span>
                            <?php if (!empty($cust_info['district'])): ?>
                                <span style="font-size:0.9rem; color:#64748b; margin-left:10px;">
                                    <i class="fas fa-home"></i> อ.<?= htmlspecialchars($cust_info['district']) ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <?php if (!$has_location): ?>
                            <button class="btn-3d-primary" onclick="openSiteLocModal()"
                                style="background: linear-gradient(to bottom, #ef4444, #dc2626); box-shadow: 0 4px 0 #b91c1c;">
                                <i class="fas fa-map-pin"></i> ระบุพิกัดลูกค้า
                            </button>
                        <?php else: ?>
                            <button class="btn-3d-primary" onclick="openFullscreenMap()">
                                <i class="fas fa-map"></i> ดูแผนที่เต็มจอ
                            </button>
                            <button class="btn-3d-danger" onclick="openSiteLocModal()"
                                style="border:1px solid #cbd5e1; color:#64748b; background:#fff;">
                                <i class="fas fa-edit"></i> แก้ไขพิกัด
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($has_location): ?>
                    <div class="layout-grid">
                        <div class="col-shop-list">
                            <div class="list-header">
                                <h4 style="margin:0; color:#334155;">ร้านค้า (<?= count($shops) ?>)</h4>
                                <button class="btn-3d-success" onclick="openAddShopModal()">
                                    <i class="fas fa-plus"></i> เพิ่มร้าน
                                </button>
                            </div>
                            <div class="scroll-area">
                                <?php if (count($shops) > 0): ?>
                                    <?php foreach ($shops as $shop): ?>
                                        <div class="shop-card-3d"
                                            onclick="focusOnMap(<?= $shop['latitude'] ?>, <?= $shop['longitude'] ?>, '<?= htmlspecialchars($shop['shop_name']) ?>')">
                                            <div class="card-top">
                                                <div class="shop-name"><?= htmlspecialchars($shop['shop_name']) ?></div>
                                                <div class="dist-badge" id="dist-badge-<?= $shop['id'] ?>">
                                                    <i class="fas fa-location-arrow"></i> ~<?= $shop['distance'] ?> km
                                                </div>
                                            </div>
                                            <div class="shop-detail">
                                                <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($shop['phone']) ?> <br>
                                                <?php if (!empty($shop['contact_name'])): ?>
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($shop['contact_name']) ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($shop['remark'])): ?>
                                                <div class="shop-remark">
                                                    <i class="fas fa-map-marker-alt" style="margin-right:5px; color:#ef4444;"></i>
                                                    <?= htmlspecialchars($shop['remark']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="card-actions">
                                                <a href="https://www.google.com/maps/dir/?api=1&origin=<?= $site_coords['latitude'] ?>,<?= $site_coords['longitude'] ?>&destination=<?= $shop['latitude'] ?>,<?= $shop['longitude'] ?>"
                                                    target="_blank" class="btn-nav-link" onclick="event.stopPropagation();">
                                                    <i class="fas fa-directions"></i> นำทาง
                                                </a>
                                                <button class="btn-3d-danger" onclick="deleteShop(<?= $shop['id'] ?>, event)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="text-align:center; padding:50px 20px; color:#cbd5e1;">
                                        <i class="fas fa-store-slash" style="font-size:3rem; margin-bottom:10px;"></i>
                                        <p>ยังไม่มีข้อมูลร้านค้า</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-map-view">
                            <div id="mini-map" style="width:100%; height:100%;"></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div
                        style="text-align:center; padding:80px; background:#fff; border-radius:20px; border:2px dashed #cbd5e1;">
                        <i class="fas fa-map-marked-alt" style="font-size:5rem; color:#cbd5e1; margin-bottom:20px;"></i>
                        <h3 style="color:#475569;">กรุณาระบุพิกัดลูกค้าก่อน</h3>
                        <p style="color:#94a3b8;">ระบบจะคำนวณระยะทางและแสดงแผนที่ให้คุณ</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align:center; padding:100px 0; color:#94a3b8;">
                    <i class="fas fa-search-location" style="font-size:5rem; margin-bottom:20px; opacity:0.5;"></i>
                    <h2>เลือกลูกค้าเพื่อเริ่มจัดการ</h2>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <div id="map-overlay">
        <div class="map-sidebar">

            <div class="map-sidebar-header">
                <div class="map-sidebar-title">
                    <i class="fas fa-list-ul" style="color:#64748b;"></i> รายการร้านค้า
                </div>
                <span class="shop-count-badge">
                    <?= count($shops) ?> ร้าน
                </span>
            </div>

            <div class="map-sidebar-content" style="padding: 15px;">
                <?php if (count($shops) > 0): ?>
                    <?php foreach ($shops as $shop): ?>

                        <div class="sidebar-shop-item"
                            onclick="focusOnMap(<?= $shop['latitude'] ?>, <?= $shop['longitude'] ?>, '<?= htmlspecialchars($shop['shop_name']) ?>')">

                            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                <h5 style="margin:0; font-weight:700; color:#1e293b; font-size:1rem;">
                                    <?= htmlspecialchars($shop['shop_name']) ?>
                                </h5>
                            </div>

                            <div style="font-size:0.85rem; color:#64748b; margin-bottom:4px;">
                                <i class="fas fa-phone-alt" style="width:16px; text-align:center;"></i>
                                <?= htmlspecialchars($shop['phone']) ?>
                            </div>

                            <?php if (!empty($shop['remark'])): ?>
                                <div class="shop-address">
                                    <i class="fas fa-map-marker-alt text-danger" style="margin-top:2px;"></i>
                                    <span><?= htmlspecialchars($shop['remark']) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="shop-item-dist-badge" id="sidebar-dist-<?= $shop['id'] ?>">
                                <i class="fas fa-route"></i> ~<?= $shop['distance'] ?> km
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:40px 20px; color:#94a3b8;">
                        <i class="fas fa-store-slash" style="font-size:2.5rem; margin-bottom:15px; opacity:0.3;"></i><br>
                        ยังไม่มีข้อมูลร้านค้าในบริเวณนี้
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="map-wrapper-full">
            <div class="map-overlay-header">
                <div style="font-size:1.1rem; font-weight:700; color:#1e293b;"><i
                        class="fas fa-map-marked-alt text-danger"></i> แผนที่เส้นทาง</div>
                <button class="close-btn" onclick="closeFullscreenMap()"
                    style="background:#ef4444; color:#fff; border:none; padding:5px 15px; border-radius:20px; cursor:pointer;"><i
                        class="fas fa-times"></i> ปิด</button>
            </div>
            <div id="map" style="width:100%; height:100%;"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // --- ฟังก์ชันแปลงพิกัดเป็นที่อยู่ (Reverse Geocoding) ---
        function getAddressFromCoords(lat, lon, targetInputId) {
            const targetEl = document.getElementById(targetInputId);
            if (!targetEl) return;

            targetEl.value = "⏳ กำลังค้นหาที่อยู่...";

            // ยิงไปหา PHP ตัวเอง (ProjectShops.php)
            $.post('ProjectShops.php', {
                action: 'get_address',
                lat: lat,
                lon: lon
            }, function (data) {
                // PHP ส่ง JSON กลับมา (data)
                if (data && data.display_name) {
                    targetEl.value = data.display_name;
                } else {
                    targetEl.value = "ไม่พบข้อมูลที่อยู่";
                }
            }, 'json') // ระบุว่ารอรับค่าเป็น json
                .fail(function () {
                    console.error("Connection Error");
                    targetEl.value = "เกิดข้อผิดพลาดในการเชื่อมต่อ";
                });
        }
        const siteLat = <?= $has_location ? $site_coords['latitude'] : 'null' ?>;
        const siteLon = <?= $has_location ? $site_coords['longitude'] : 'null' ?>;
        const shops = <?= json_encode($shops) ?>;
        const customerName = <?= json_encode($cust_name) ?>; // [เพิ่ม] ส่งชื่อลูกค้าไปใช้ใน JS

        let map, miniMap;
        let routingControl = null;

        $(document).ready(function () {
            $('.select2-search').select2();

            if (siteLat && siteLon) {
                initMiniMap();
                calculateRealDistances();
            }
        });

        // --- 1. คำนวณระยะทางจริง ---
        async function calculateRealDistances() {
            for (const shop of shops) {
                // Elements ที่ต้องการอัปเดต (การ์ดหลัก + แท็บซ้าย)
                const mainBadge = document.getElementById(`dist-badge-${shop.id}`);
                const sidebarBadge = document.getElementById(`sidebar-dist-${shop.id}`);

                const loadingHtml = `<i class="fas fa-spinner fa-spin"></i> ...`;
                if (mainBadge) mainBadge.innerHTML = loadingHtml;
                if (sidebarBadge) sidebarBadge.innerHTML = loadingHtml;

                try {
                    const url = `https://router.project-osrm.org/route/v1/driving/${siteLon},${siteLat};${shop.longitude},${shop.latitude}?overview=false`;
                    const response = await fetch(url);
                    const data = await response.json();

                    let distDisplay = '';
                    let isReal = false;

                    if (data.code === 'Ok' && data.routes.length > 0) {
                        const distKm = (data.routes[0].distance / 1000).toFixed(2);
                        distDisplay = `${distKm} km`;
                        isReal = true;

                        // แจ้งเตือนสีแดงถ้าไกลเกิน 50 กม. ในหน้าหลัก
                        if (mainBadge && distKm > 50) {
                            mainBadge.style.background = '#fee2e2';
                            mainBadge.style.color = '#ef4444';
                        }
                    } else {
                        distDisplay = `~${shop.distance} km`;
                    }

                    // อัปเดต HTML ทั้งสองจุด
                    const icon = isReal ? '<i class="fas fa-route"></i>' : '<i class="fas fa-plane"></i>';
                    if (mainBadge) mainBadge.innerHTML = `${icon} ${distDisplay}`;
                    if (sidebarBadge) sidebarBadge.innerHTML = `${icon} ${distDisplay}`;

                } catch (err) {
                    // กรณี Error ให้ใช้ระยะขจัด
                    const fallback = `<i class="fas fa-plane"></i> ~${shop.distance} km`;
                    if (mainBadge) mainBadge.innerHTML = fallback;
                    if (sidebarBadge) sidebarBadge.innerHTML = fallback;
                }
                await new Promise(r => setTimeout(r, 800));
            }
        }

        // --- 2. MAP LOGIC ---
        function openFullscreenMap() {
            document.getElementById('map-overlay').style.display = 'flex';
            if (!map) initMap();
            else setTimeout(() => { map.invalidateSize(); }, 200);
        }

        function closeFullscreenMap() {
            document.getElementById('map-overlay').style.display = 'none';
            if (routingControl) { map.removeControl(routingControl); routingControl = null; }
        }

        function focusOnMap(shopLat, shopLon, title) {
            openFullscreenMap();

            // 1. ค้นหาข้อมูลร้านค้าตัวเต็มจากตัวแปร shops (เพื่อให้ได้เบอร์, ที่อยู่, ผู้ติดต่อ)
            // (ใช้การเปรียบเทียบ Lat/Lon เพื่อหา Object ที่ถูกต้อง)
            const shop = shops.find(s => s.latitude == shopLat && s.longitude == shopLon) || { shop_name: title };

            setTimeout(() => {
                if (routingControl) map.removeControl(routingControl);

                routingControl = L.Routing.control({
                    waypoints: [L.latLng(siteLat, siteLon), L.latLng(shopLat, shopLon)],
                    lineOptions: { styles: [{ color: '#2563eb', opacity: 0.8, weight: 6 }] },
                    createMarker: function () { return null; },
                    addWaypoints: false, draggableWaypoints: false, fitSelectedRoutes: true, show: false
                })
                    .on('routesfound', function (e) {
                        // ดึงข้อมูลการเดินทาง
                        var summary = e.routes[0].summary;
                        var realDist = (summary.totalDistance / 1000).toFixed(2);
                        var realTime = Math.round(summary.totalTime / 60);

                        // 2. สร้าง HTML สำหรับ Popup (จัดหน้าตาให้เหมือน Sidebar + Routing Info)
                        let popupContent = `
                    <div style="text-align:left; min-width:240px; font-family:'Prompt',sans-serif;">
                        
                        <h6 style="margin:0 0 8px 0; color:#1e293b; font-weight:700; font-size:1.05rem; border-bottom:2px solid #e2e8f0; padding-bottom:5px;">
                            ${shop.shop_name}
                        </h6>
                        
                        <div style="font-size:0.9rem; color:#475569; margin-bottom:8px; line-height:1.6;">
                            ${shop.phone ? `<div><i class="fas fa-phone-alt" style="width:20px; text-align:center; color:#94a3b8;"></i> ${shop.phone}</div>` : ''}
                            ${shop.contact_name ? `<div><i class="fas fa-user" style="width:20px; text-align:center; color:#94a3b8;"></i> ${shop.contact_name}</div>` : ''}
                        </div>

                        ${shop.remark ? `
                            <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px; padding:8px; font-size:0.85rem; color:#64748b; margin-bottom:12px; line-height:1.4; display:flex; gap:6px;">
                                <i class="fas fa-map-marker-alt text-danger" style="margin-top:2px;"></i>
                                <span>${shop.remark}</span>
                            </div>
                        ` : ''}

                        <div style="text-align:center; background:#eff6ff; border:1px solid #dbeafe; border-radius:8px; padding:10px; margin-top:5px;">
                            <span style="font-size:0.85rem; color:#64748b;">ระยะทางถนนจริง</span><br>
                            <strong style="color:#2563eb; font-size:1.3rem;">${realDist} กม.</strong><br>
                            <span style="font-size:0.9rem; color:#059669; font-weight:500;">
                                <i class="fas fa-car-side"></i> ขับรถประมาณ ${realTime} นาที
                            </span>
                        </div>
                    </div>
                `;

                        L.popup()
                            .setLatLng([shopLat, shopLon])
                            .setContent(popupContent)
                            .openOn(map);
                    })
                    .addTo(map);

            }, 300);
        }

        function initMap() {
            map = L.map('map').setView([siteLat, siteLon], 13);
            setupMapLayers(map);
            addMarkers(map);
        }

        function initMiniMap() {
            miniMap = L.map('mini-map', { zoomControl: false }).setView([siteLat, siteLon], 13);
            setupMapLayers(miniMap);
            addMarkers(miniMap);
            miniMap.scrollWheelZoom.disable();
        }

        function setupMapLayers(targetMap) {
            const googleStreets = L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { maxZoom: 20, subdomains: ['mt0', 'mt1', 'mt2', 'mt3'] });
            const googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', { maxZoom: 20, subdomains: ['mt0', 'mt1', 'mt2', 'mt3'] });
            googleStreets.addTo(targetMap);
            if (targetMap === map) L.control.layers({ "แผนที่ถนน": googleStreets, "ดาวเทียม": googleHybrid }).addTo(targetMap);
        }

        function addMarkers(targetMap) {
            const redIcon = new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] });
            const blueIcon = new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] });

            // 1. หมุดลูกค้า (แก้ไข: โชว์ชื่อทันทีด้วย permanent tooltip)
            L.marker([siteLat, siteLon], { icon: redIcon }).addTo(targetMap)
                .bindTooltip(`<b>${customerName}</b>`, { permanent: true, direction: 'top', offset: [0, -30] })
                .bindPopup("<b>📍 ลูกค้า</b>");

            // 2. หมุดร้านค้า
            shops.forEach(s => {
                const marker = L.marker([s.latitude, s.longitude], { icon: blueIcon }).addTo(targetMap);
                marker.on('click', function () { focusOnMap(s.latitude, s.longitude, s.shop_name); });
                marker.bindTooltip(`<b>${s.shop_name}</b>`, { direction: 'top', offset: [0, -30] });
            });
        }

        // --- MODALS ---
        function openSiteLocModal() {
            Swal.fire({
                title: 'ระบุพิกัดลูกค้า',
                html: `
            <input id="swal-lat" class="swal2-input" placeholder="Latitude" value="${siteLat || ''}">
            <input id="swal-lon" class="swal2-input" placeholder="Longitude" value="${siteLon || ''}">
            <div id="addr-preview" style="font-size:0.8rem; color:#64748b; margin-top:10px;"></div>
        `,
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                didOpen: () => {
                    const latInput = Swal.getPopup().querySelector('#swal-lat');
                    const lonInput = Swal.getPopup().querySelector('#swal-lon');
                    const preview = Swal.getPopup().querySelector('#addr-preview');

                    const fetchAddr = () => {
                        if (latInput.value && lonInput.value) {
                            preview.innerHTML = "⏳ กำลังดึงที่อยู่...";
                            // ใช้ API เดิม แต่เขียน Logic แสดงผลเฉพาะหน้า modal นี้
                            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latInput.value}&lon=${lonInput.value}&accept-language=th`)
                                .then(r => r.json())
                                .then(data => { preview.innerHTML = data.display_name || "ไม่พบที่อยู่"; })
                                .catch(() => { preview.innerHTML = ""; });
                        }
                    };

                    latInput.addEventListener('input', function () {
                        if (this.value.includes(',')) {
                            let parts = this.value.split(',');
                            this.value = parts[0].trim();
                            lonInput.value = parts[1].trim();
                            fetchAddr();
                        }
                    });
                    lonInput.addEventListener('change', fetchAddr);
                },
                preConfirm: () => {
                    return { lat: document.getElementById('swal-lat').value, lon: document.getElementById('swal-lon').value };
                }
            }).then((res) => {
                if (res.isConfirmed) $.post('ProjectShops.php?site_id=<?= $site_id ?>', { action: 'save_site_location', site_lat: res.value.lat, site_lon: res.value.lon }, function () { location.reload(); });
            });
        }

        function openAddShopModal() {
            Swal.fire({
                title: 'เพิ่มร้านช่าง',
                // เพิ่มช่อง input id="s-address" สำหรับแสดงที่อยู่
                html: `
            <input id="s-name" class="swal2-input" placeholder="ชื่อร้าน">
            <input id="s-contact" class="swal2-input" placeholder="ชื่อผู้ติดต่อ">
            <input id="s-phone" class="swal2-input" placeholder="เบอร์โทร">
            <input id="s-lat" class="swal2-input" placeholder="Lat (เช่น 13.7563)">
            <input id="s-lon" class="swal2-input" placeholder="Lon (เช่น 100.5018)">
            <textarea id="s-remark" class="swal2-textarea" placeholder="ที่อยู่ / หมายเหตุ (ดึงให้อัตโนมัติ)"></textarea>
        `,
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                didOpen: () => {
                    const latInput = Swal.getPopup().querySelector('#s-lat');
                    const lonInput = Swal.getPopup().querySelector('#s-lon');
                    const remarkInput = Swal.getPopup().querySelector('#s-remark'); // ช่องที่จะให้แสดงที่อยู่

                    // ฟังก์ชันทำงานเมื่อมีการพิมพ์หรือวางข้อความในช่อง Lat
                    latInput.addEventListener('input', function () {
                        let latVal = this.value.trim();

                        // ถ้าวางแบบมีลูกน้ำ (เช่น "13.123, 100.123") ให้แยกอัตโนมัติ
                        if (latVal.includes(',')) {
                            let parts = latVal.split(',');
                            latVal = parts[0].trim();
                            let lonVal = parts[1].trim();

                            this.value = latVal;
                            lonInput.value = lonVal;

                            // *** เรียกฟังก์ชันดึงที่อยู่ ***
                            getAddressFromCoords(latVal, lonVal, 's-remark');
                        } else if (latVal && lonInput.value) {
                            // ถ้ากรอกทีละช่องจนครบ ก็ดึงเหมือนกัน
                            getAddressFromCoords(latVal, lonInput.value, 's-remark');
                        }
                    });

                    // ฟังก์ชันทำงานเมื่อกรอกช่อง Lon เสร็จ
                    lonInput.addEventListener('change', function () {
                        if (latInput.value && this.value) {
                            getAddressFromCoords(latInput.value, this.value, 's-remark');
                        }
                    });
                },
                preConfirm: () => {
                    return {
                        shop_name: document.getElementById('s-name').value,
                        contact_name: document.getElementById('s-contact').value,
                        phone: document.getElementById('s-phone').value,
                        latitude: document.getElementById('s-lat').value,
                        longitude: document.getElementById('s-lon').value,
                        remark: document.getElementById('s-remark').value // ส่งค่าที่อยู่ที่ดึงมาได้ไปด้วย
                    };
                }
            }).then((res) => {
                if (res.isConfirmed) {
                    if (!res.value.shop_name || !res.value.latitude) {
                        Swal.fire('Error', 'กรอกข้อมูลไม่ครบ', 'error');
                        return;
                    }
                    $.post('ProjectShops.php?site_id=<?= $site_id ?>', { action: 'add_shop', ...res.value }, function () { location.reload(); });
                }
            });
        }

        function deleteShop(id, e) {
            e.stopPropagation();
            Swal.fire({ title: 'ลบร้านค้านี้?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบ' }).then((res) => {
                if (res.isConfirmed) $.post('ProjectShops.php?site_id=<?= $site_id ?>', { action: 'delete_shop', shop_id: id }, function () { location.reload(); });
            });
        }
    </script>

</body>

</html>