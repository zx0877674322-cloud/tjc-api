<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'db_connect.php';
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
if (!isset($conn)) {
    // ถ้า connect ไม่ติด ให้หยุดทำงานและแจ้ง JSON กลับไป
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection variable $conn not found']);
        exit;
    }
    die("Database connection failed: \$conn is null");
}
// ==========================================================================
//  PART 1: HANDLE ACTIONS (POST REQUESTS)
// ==========================================================================
if (isset($_POST['action']) && $_POST['action'] == 'get_latest_item_data') {
    while (ob_get_level())
        ob_end_clean();
    header('Content-Type: application/json');

    if (!isset($conn) || $conn->connect_error) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    $req_id = intval($_POST['req_id']);

    // 🔥 แก้ SQL: ดึง project_item_name (ซึ่งพี่ใช้เก็บรายชื่อสินค้า)
    $stmt = $conn->prepare("SELECT received_item_list, project_item_name FROM service_requests WHERE id = ?");
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $response = [];
    if ($row = $res->fetch_assoc()) {
        // ดึงข้อมูลการเคลื่อนย้ายที่มีอยู่เดิม
        $json_data = json_decode($row['received_item_list'] ?? '{}', true);
        if (!is_array($json_data))
            $json_data = [];
        $response = $json_data;

        // 🔥 ส่งข้อมูลสินค้าตั้งต้นจากฟิลด์ project_item_name ไปให้ JS ด้วย
        $response['project_item_name_raw'] = $row['project_item_name'];
    }

    echo json_encode($response);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_name = $_SESSION['fullname'] ?? 'Unknown';
    $req_id = isset($_POST['req_id']) ? intval($_POST['req_id']) : 0;


    // 1.1 จบงาน (Finish Job)
    if ($_POST['action'] == 'finish_job') {
        $sql = "UPDATE service_requests SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $user_name, $req_id);
        echo json_encode(['status' => $stmt->execute() ? 'success' : 'error', 'message' => $conn->error]);
        exit;
    }

    // 1.2 ลบงาน (Delete Item)
    if ($_POST['action'] == 'delete_item') {
        // [เพิ่ม] ลบคะแนนประเมินของงานนี้ทิ้งก่อน
        $stmt_rate = $conn->prepare("DELETE FROM service_ratings WHERE req_id = ?");
        $stmt_rate->bind_param("i", $req_id);
        $stmt_rate->execute();

        // ลบข้อมูลงานหลัก
        $sql = "DELETE FROM service_requests WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $req_id);
        echo json_encode(['status' => $stmt->execute() ? 'success' : 'error', 'message' => $conn->error]);
        exit;
    }

    // --- 1.3 [ฉบับแก้ไข UI ขั้นสุด: ปุ่มเด้ง + โชว์ไฟล์ + แก้ Undefined] ---
    if ($_POST['action'] == 'receive_item') {
        header('Content-Type: application/json');

        if (!isset($conn) || $conn->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }

        try {
            $req_id = intval($_POST['req_id']);
            $user_name = $_SESSION['fullname'] ?? 'Unknown';
            $items_post = $_POST['items'] ?? [];

            if (empty($items_post)) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการที่เลือก']);
                exit;
            }

            // 1. ระบบจัดการไฟล์ (Mapping ตาม Key รายชิ้นเท่านั้น)
            $upload_dir = 'uploads/proofs/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);

            $file_map = [];

            if (!empty($_FILES)) {
                foreach ($_FILES as $key => $file) {
                    if ($file['error'] == 0) {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $new_filename = 'rec_' . $req_id . '_' . time() . '_' . $key . '.' . $ext;

                        if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
                            // เก็บชื่อไฟล์คู่กับ Key (เช่น item_files_0 => rec_...jpg)
                            $file_map[$key] = $new_filename;
                        }
                    }
                }
            }

            // 2. ดึงข้อมูลเดิม
            $res_log = $conn->query("SELECT received_item_list, progress_logs FROM service_requests WHERE id = $req_id");
            $row_log = $res_log->fetch_assoc();
            $old_data = json_decode($row_log['received_item_list'] ?? '{}', true) ?: [];

            $raw_logs = $row_log['progress_logs'];
            $logs = json_decode($raw_logs, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($logs)) {
                $logs = [];
            }

            $accumulated_moved = $old_data['accumulated_moved'] ?? [];
            $items_status = $old_data['items_status'] ?? [];
            $existing_finished_items = $old_data['finished_items'] ?? [];
            $existing_moves = $old_data['items_moved'] ?? [];
            $items_moved_this_round = [];

            // 3. จัดกลุ่มรายการ (Grouping)
            $grouped_batches = [];
            foreach ($items_post as $index => $item) {
                $dest = $item['destination'] ?? 'office';
                $shop = ($dest === 'external') ? trim($item['shop_name'] ?? '') : 'OFFICE';
                $group_key = $dest . '_' . $shop;

                if (!isset($grouped_batches[$group_key])) {
                    $grouped_batches[$group_key] = [];
                }

                // จับคู่ไฟล์ (เฉพาะของรายการนี้)
                $my_file_key = 'item_files_' . $index;
                $item['attached_file'] = isset($file_map[$my_file_key]) ? $file_map[$my_file_key] : null;

                $grouped_batches[$group_key][] = $item;
            }

            // 4. วนลูปสร้างการ์ด (ทีละกลุ่มร้าน)
            $all_new_logs_html = "";
            foreach ($grouped_batches as $group_key => $batch_items) {

                $first_in_batch = $batch_items[0];
                $main_type = $first_in_batch['destination'];

                $s_name = ($first_in_batch['shop_name'] === 'undefined') ? '-' : ($first_in_batch['shop_name'] ?? '-');
                $s_owner = ($first_in_batch['shop_owner'] === 'undefined') ? '-' : ($first_in_batch['shop_owner'] ?? '-');
                $s_phone = ($first_in_batch['shop_phone'] === 'undefined') ? '-' : ($first_in_batch['shop_phone'] ?? '-');

                // 🎨 กำหนดธีมสีสไตล์ Premium 3D
                if ($main_type === 'external') {
                    // 🟠 ธีมส้ม (ร้านนอก)
                    $header_bg = 'linear-gradient(135deg, #f97316, #ea580c)';
                    $border_left = '#ea580c';
                    $info_bg = '#fff7ed';
                    $info_border = '#fdba74';
                    $icon = 'fa-store';
                    $title = 'ส่งซ่อมร้านภายนอก';
                    $btn_grad = 'linear-gradient(135deg, #f97316, #ea580c)';
                    $btn_shadow = 'rgba(234, 88, 12, 0.3)';
                    $pulse_color = 'rgba(234, 88, 12, 0.4)';
                    $text_dark = '#9a3412';
                } else {
                    // 🔵 ธีมฟ้า (กลับบริษัท)
                    $header_bg = 'linear-gradient(135deg, #3b82f6, #1d4ed8)';
                    $border_left = '#3b82f6';
                    $info_bg = '#eff6ff';
                    $info_border = '#bfdbfe';
                    $icon = 'fa-building';
                    $title = 'นำของกลับบริษัท';
                    $btn_grad = 'linear-gradient(135deg, #3b82f6, #2563eb)';
                    $btn_shadow = 'rgba(37, 99, 235, 0.3)';
                    $pulse_color = 'rgba(59, 130, 246, 0.4)';
                    $text_dark = '#1e3a8a';
                }

                $progress_msg = "
                <style>
                    @keyframes fadeInUpVal { from { opacity:0; transform:translateY(15px); } to { opacity:1; transform:translateY(0); } }
                    @keyframes pulseIconVal { 0% { box-shadow: 0 0 0 0 {$pulse_color}; } 70% { box-shadow: 0 0 0 8px rgba(0,0,0,0); } 100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); } }
                    
                    .log-anim-val { animation: fadeInUpVal 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
                    
                    .btn-smart-val {
                        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                        padding: 8px 16px; border-radius: 50px;
                        color: #fff !important; font-weight: 700; text-decoration: none; font-size: 0.85rem;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: none; margin-top: 10px;
                    }
                    .btn-smart-val:hover { transform: translateY(-3px); filter: brightness(1.1); }
                </style>
                <div style='font-family:Prompt, sans-serif; position:relative; margin-bottom:15px; padding:18px; background:#fff; border-radius:16px; border:1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);'>";

                // --- 1. Header ---
                $progress_msg .= "
                <div class='log-anim-val' style='display:flex; align-items:center; gap:15px; margin-bottom:18px;'>
                    <div style='width:50px; height:50px; background:{$header_bg}; color:#fff; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow: 0 4px 10px {$btn_shadow}; animation: pulseIconVal 2s infinite;'>
                        <i class='fas {$icon}'></i>
                    </div>
                    <div>
                        <div style='font-weight:800; color:#1e293b; font-size:1.1rem; letter-spacing:-0.5px;'>{$title}</div>
                        <div style='font-size:0.8rem; color:#64748b;'>ผู้ดำเนินการ: <b style='color:{$text_dark};'>{$user_name}</b></div>
                    </div>
                </div>";

                // --- 2. Info Box (เฉพาะร้านนอก) ---
                if ($main_type === 'external') {
                    $progress_msg .= "
                    <div class='log-anim-val' style='background:{$info_bg}; border:1px solid {$info_border}; border-left:4px solid {$border_left}; padding:12px 15px; border-radius:10px; margin-bottom:18px; animation-delay: 0.1s;'>
                        <div style='display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;'>
                            <div style='font-size:0.95rem; font-weight:800; color:{$text_dark};'>{$s_name}</div>
                            <div style='font-size:0.8rem; font-weight:700; color:{$text_dark}; background:#fff; padding:4px 12px; border-radius:50px; border:1px solid {$info_border}; box-shadow:0 2px 4px rgba(0,0,0,0.02);'><i class='fas fa-phone-alt' style='margin-right:4px;'></i> {$s_phone}</div>
                        </div>
                        <div style='font-size:0.8rem; color:{$text_dark}; margin-top:6px; opacity:0.9;'><i class='fas fa-user-tie' style='margin-right:4px;'></i> ติดต่อ: {$s_owner}</div>
                    </div>";
                }

                // --- 3. Item List Loop ---
                $progress_msg .= "<div class='log-anim-val' style='margin-bottom:10px; animation-delay: 0.2s;'>";
                $progress_msg .= "<div style='font-size:0.75rem; font-weight:800; color:#64748b; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;'>รายการที่ดำเนินการ</div>";
                $progress_msg .= "<div style='display:flex; flex-direction:column; gap:10px;'>";

                $files_to_render = [];

                foreach ($batch_items as $idx_item => $item_data) {
                    $item_name = trim($item_data['name']);
                    $itm_rem = isset($item_data['remark']) && $item_data['remark'] !== 'undefined' ? trim($item_data['remark']) : '';

                    if (!empty($item_data['attached_file'])) {
                        $files_to_render[] = ['file' => $item_data['attached_file'], 'label' => $item_name];
                    }

                    $item_delay = 0.25 + ($idx_item * 0.05);

                    $progress_msg .= "
                    <div class='log-anim-val' style='background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid {$border_left}; padding:12px 15px; border-radius:10px; display:flex; flex-direction:column; gap:6px; transition:all 0.2s; animation-delay: {$item_delay}s;'>
                        <div style='display:flex; align-items:center; gap:10px;'>
                            <div style='color:{$border_left}; font-size:1rem;'><i class='fas fa-check-circle'></i></div>
                            <div style='font-size:0.95rem; color:#334155; font-weight:700;'>{$item_name}</div>
                        </div>";
                    if ($itm_rem) {
                        $progress_msg .= "<div style='font-size:0.85rem; color:#475569; padding-left:26px;'><i class='fas fa-comment-dots' style='color:#cbd5e1; margin-right:4px;'></i> {$itm_rem}</div>";
                    }
                    $progress_msg .= "</div>";

                    // Update DB Array
                    if (!in_array($item_name, $accumulated_moved))
                        $accumulated_moved[] = $item_name;
                    $items_status[$item_name] = ($main_type === 'external') ? 'at_external' : 'at_office_unconfirmed';
                    $shop_info_arr = ($main_type === 'external') ? ['name' => $s_name, 'owner' => $s_owner, 'phone' => $s_phone] : null;

                    $items_moved_this_round[] = [
                        'name' => $item_name,
                        'destination' => $main_type,
                        'remark' => $itm_rem,
                        'shop_info' => $shop_info_arr,
                        'file' => $item_data['attached_file'],
                        'at' => date('d/m/Y H:i'),
                        'by' => $user_name
                    ];
                }
                $progress_msg .= "</div></div>";

                // --- 4. ปุ่มเปิดรูป (ถ้ามี) ---
                if (!empty($files_to_render)) {
                    // เช็คว่าส่งหลายชิ้นแต่แนบไฟล์เดียว (บิลรวม) หรือไม่
                    $is_batch = count($batch_items) > 1;
                    $is_single_file_for_batch = ($is_batch && count($files_to_render) === 1);

                    foreach ($files_to_render as $idx => $f) {
                        $delay = 0.3 + ($idx * 0.1);

                        // 🌟 กำหนดชื่อปุ่มตาม Logic ที่คุยกัน
                        if (!$is_batch) {
                            $btn_label = 'ดูรูปหลักฐานแนบ';
                        } else if ($is_single_file_for_batch) {
                            // กรณีหลายชิ้นรูปเดียว: ถ้าส่งร้านนอกโชว์ชื่อร้าน ถ้ากลับบริษัทโชว์ว่ารูปรวม
                            $btn_label = ($main_type === 'external') ? "ใบส่งซ่อม ({$s_name})" : "ดูรูปหลักฐานรวม";
                        } else {
                            // กรณีแยกรูปรายชิ้น: โชว์ชื่อสินค้า
                            $btn_label = "ดูรูป ({$f['label']})";
                        }

                        // 🔥 ดีไซน์ปุ่มแบบเดิมที่ลูกพี่ชอบ (เด้งๆ มีมิติ กว้างเต็มการ์ด)
                        $progress_msg .= "
                        <div class='log-anim' style='margin-top:8px; animation-delay: {$delay}s;'>
                            <a href='uploads/proofs/{$f['file']}' target='_blank' class='btn-smart-action' style='background: {$btn_grad}; box-shadow: 0 4px 10px {$btn_shadow};'>
                                <i class='fas fa-image fa-lg'></i> {$btn_label}
                            </a>
                        </div>";
                    }
                }

                $progress_msg .= "</div>"; // End Card Wrapper

                $logs[] = ['at' => date('d/m/Y H:i'), 'by' => $user_name, 'msg' => $progress_msg];
                $all_new_logs_html .= $progress_msg;
            }

            // 5. บันทึก
            $old_data['details'] = $old_data['details'] ?? [];
            $old_data['items_moved'] = array_merge($existing_moves, $items_moved_this_round);
            $old_data['accumulated_moved'] = $accumulated_moved;
            $old_data['items_status'] = $items_status;
            $old_data['finished_items'] = $existing_finished_items;

            $new_json_str = json_encode($old_data, JSON_UNESCAPED_UNICODE);
            $new_logs_str = json_encode($logs, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("UPDATE service_requests SET received_by = ?, received_at = NOW(), received_item_list = ?, progress_logs = ? WHERE id = ?");
            $stmt->bind_param("sssi", $user_name, $new_json_str, $new_logs_str, $req_id);

            if ($stmt->execute())
                echo json_encode(['status' => 'success']);
            else
                echo json_encode(['status' => 'error', 'message' => $stmt->error]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // [แก้ไข: 1.4 อัปเดตความคืบหน้า] - ใช้ดีไซน์ Premium 3D (Theme Blue) แบบเดียวกับโค้ดเก่าเป๊ะ!
    if ($_POST['action'] == 'update_progress') {
        header('Content-Type: application/json');
        try {
            $req_id = intval($_POST['req_id']);
            $update_msg = trim($_POST['update_msg'] ?? '');
            $tech_name = isset($_POST['technician_name']) ? trim($_POST['technician_name']) : '';
            $completed_items = isset($_POST['completed_items']) ? $_POST['completed_items'] : [];
            $action_type = $_POST['action_type'] ?? 'update'; // รับค่าปุ่ม
            $user_name = $_SESSION['fullname'] ?? 'System';

            // 1. ดึงข้อมูลเดิมจากฐานข้อมูล
            $stmt = $conn->prepare("SELECT received_item_list, progress_logs, status, technician_name FROM service_requests WHERE id = ?");
            $stmt->bind_param("i", $req_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if (!$row) {
                throw new Exception("ไม่พบข้อมูลใบงาน");
            }

            // แปลง JSON เดิมออกมา (ถ้าว่างให้สร้าง Array ใหม่)
            $data_json = json_decode($row['received_item_list'] ?? '{}', true);
            if (!is_array($data_json))
                $data_json = [];

            $logs = json_decode($row['progress_logs'] ?? '[]', true) ?: [];
            $current_techs = $row['technician_name'] ?? '';

            // 2. จัดการรายชื่อช่าง (สะสม)
            $final_tech_list = $current_techs;
            if (!empty($tech_name)) {
                $tech_array = !empty($current_techs) ? array_map('trim', explode(',', $current_techs)) : [];
                if (!in_array($tech_name, $tech_array)) {
                    $tech_array[] = $tech_name;
                    $final_tech_list = implode(', ', $tech_array);
                }
            }

            // 🟢 3. Logic บันทึกสถานะสินค้า (หัวใจสำคัญ!)
            $items_log_text = "";

            if (!empty($completed_items)) {
                // ทำความสะอาดรายชื่อสินค้าก่อนบันทึก (ตัด [ ] และช่องว่าง)
                $clean_completed_items = array_map(function ($item) {
                    $item = preg_replace('/^\[+|\]+$/', '', $item); // ตัด [ ]
                    $item = preg_replace('/^\d+\.\s*/', '', $item); // ตัดเลขลำดับ 1.
                    if (strpos($item, ':') !== false) {
                        $item = explode(':', $item)[0]; // ตัดอาการเสียหลัง :
                    }
                    return trim($item);
                }, $completed_items);

                $items_log_text = implode(', ', $clean_completed_items);

                if ($action_type == 'finish') {
                    // 🔥 บันทึกลง finished_items (เพื่อให้ JS เอาไปโชว์สีเขียว)
                    if (!isset($data_json['finished_items'])) {
                        $data_json['finished_items'] = [];
                    }

                    foreach ($clean_completed_items as $itm) {
                        // เช็คว่ามีอยู่แล้วหรือยัง (กันซ้ำ)
                        $exists = false;
                        foreach ($data_json['finished_items'] as $existing) {
                            if (strcasecmp($existing, $itm) == 0) {
                                $exists = true;
                                break;
                            }
                        }

                        if (!$exists) {
                            $data_json['finished_items'][] = $itm;
                        }
                    }

                    // ❌ ลูกพี่สั่ง: "ไม่ต้องไปตัดออก" -> ผมเลยคอมเมนต์ส่วนลบ accumulated_moved ทิ้งครับ
                    // if (isset($data_json['accumulated_moved'])) { ... array_diff ... }
                }
            }

            // 4. สร้างข้อความ Log
            $final_msg = "<div style='font-family:Prompt;'>";
            $final_msg .= "<b>อัปเดตความคืบหน้า โดย $user_name</b><br>";

            if ($action_type == 'finish' && !empty($items_log_text)) {
                $final_msg .= "<span style='color:#10b981;'><i class='fas fa-check-circle'></i> เสร็จสิ้นรายการ: $items_log_text</span><br>";
            } elseif (!empty($items_log_text)) {
                $final_msg .= "<span style='color:#3b82f6;'><i class='fas fa-info-circle'></i> บันทึกรายการ: $items_log_text</span><br>";
            }

            if (!empty($update_msg)) {
                $final_msg .= "<span style='color:#334155;'>" . nl2br(htmlspecialchars($update_msg)) . "</span>";
            }

            if (!empty($tech_name)) {
                $final_msg .= "<br><span style='font-size:0.85rem; color:#d97706;'><i class='fas fa-user-plus'></i> มอบหมายช่าง: $tech_name</span>";
            }
            $final_msg .= "</div>";

            $logs[] = ['msg' => $final_msg, 'by' => $user_name, 'at' => date('d/m/Y H:i')];

            // 5. บันทึกกลับลงฐานข้อมูล
            $sql = "UPDATE service_requests SET 
                progress_logs = ?, 
                status = ?, 
                technician_name = ?, 
                received_item_list = ? 
                WHERE id = ?";

            $stmt_up = $conn->prepare($sql);

            // กำหนดสถานะงาน (ถ้ากด Finish อาจจะยังไม่จบงานทั้งหมด ให้เป็น in_progress ไว้ก่อน หรือแล้วแต่ Logic)
            // แต่ถ้าอยากให้งานหลักยังไม่ปิด ก็ใช้ in_progress ไปก่อนครับ
            $new_status = ($old_row['status'] == 'completed') ? 'completed' : 'in_progress';

            $logs_json = json_encode($logs, JSON_UNESCAPED_UNICODE);
            $data_json_str = json_encode($data_json, JSON_UNESCAPED_UNICODE);

            $stmt_up->bind_param("ssssi", $logs_json, $new_status, $final_tech_list, $data_json_str, $req_id);

            if ($stmt_up->execute()) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'บันทึกข้อมูลไม่สำเร็จ']);
            }

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }


    // [แก้ไขใหม่] 1.5 ยืนยันรับของเข้าบริษัท (Step 2: ติ๊กของ + แนบไฟล์)
    if ($_POST['action'] == 'office_receive') {
        header('Content-Type: application/json');
        try {
            $req_id = intval($_POST['req_id']);
            $user_name = $_SESSION['fullname'] ?? 'Unknown';
            $remark = trim($_POST['office_remark']);
            $office_items = isset($_POST['office_items']) ? $_POST['office_items'] : [];
            $current_time = date('d/m/Y H:i'); // 🔥 เตรียมเวลาไว้ใช้

            // 1. ดึงข้อมูลเดิม
            $res = $conn->query("SELECT received_item_list, progress_logs FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();

            $data = json_decode($row_data['received_item_list'] ?? '{}', true) ?: [];
            $logs = json_decode($row_data['progress_logs'] ?? '[]', true) ?: [];

            // 🔥 [จุดที่ 1 เพิ่มเติม] อัปเดตสถานะสินค้าในตัวแปร $data พร้อมเก็บเวลา/คนทำ รายชิ้น
            if (!isset($data['items_status']))
                $data['items_status'] = [];

            foreach ($office_items as $itm) {
                $item_name = trim($itm);
                $data['items_status'][$item_name] = 'at_office_confirmed';

                // บันทึกเวลาลงในประวัติการเคลื่อนย้ายรายชิ้น (ถ้ามี array items_moved)
                if (isset($data['items_moved'])) {
                    foreach ($data['items_moved'] as &$move) {
                        if ($move['name'] === $item_name && $move['destination'] === 'office') {
                            $move['received_at'] = $current_time;
                            $move['received_by'] = $user_name;
                        }
                    }
                }
            }

            // 2. จัดการไฟล์แนบ (เหมือนเดิม)
            $file_name = null;
            if (isset($_FILES['office_file']) && $_FILES['office_file']['error'] == 0) {
                $upload_dir = 'uploads/proofs/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);
                $file_name = 'off_' . $req_id . '_' . time() . '.' . pathinfo($_FILES['office_file']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($_FILES['office_file']['tmp_name'], $upload_dir . $file_name);
            }

            // 🎨 สร้าง HTML Log Design (เพิ่มเวลาในส่วน Header)
            $header_bg = 'linear-gradient(135deg, #3b82f6, #1d4ed8)';
            $border_left = '#3b82f6';
            $pulse_color = 'rgba(59, 130, 246, 0.5)';
            $btn_grad = 'linear-gradient(to right, #3b82f6, #1e40af)';
            $btn_shadow = 'rgba(59, 130, 246, 0.4)';

            $progress_msg = "
            <style>
                @keyframes fadeInUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
                @keyframes pulseBlue { 0% { box-shadow: 0 0 0 0 {$pulse_color}; } 70% { box-shadow: 0 0 0 10px rgba(0,0,0,0); } 100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); } }
                .log-anim { animation: fadeInUp 0.5s ease forwards; }
            </style>
            <div style='font-family:Prompt, sans-serif; position:relative;'>";

            // --- 1. Header (เพิ่มการแสดงเวลา $current_time ตรงนี้ด้วย) ---
            $progress_msg .= "
            <div class='log-anim' style='display:flex; align-items:center; gap:12px; margin-bottom:15px;'>
                <div style='width:48px; height:48px; background:{$header_bg}; color:#fff; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; animation: pulseBlue 2s infinite;'>
                    <i class='fas fa-building'></i>
                </div>
                <div>
                    <div style='font-weight:800; color:#1e3a8a; font-size:1rem;'>นำของกลับบริษัท / ตรวจรับสินค้า</div>
                    <div style='font-size:0.8rem; color:#64748b;'>โดย: <b>{$user_name}</b> | เวลา: <b>{$current_time}</b></div>
                </div>
            </div>";

            // (ส่วนที่เหลือ: Note Box, Item List, File Button ใช้ของเดิมของลูกพี่ได้เลย)
            if (!empty($remark)) {
                $progress_msg .= "
                <div class='log-anim' style='background:#f8fafc; padding:10px 15px; border-radius:10px; font-size:0.9rem; color:#475569; margin-bottom:15px; border:1px dashed #cbd5e1; text-align:center; animation-delay: 0.1s;'>
                    <i class='fas fa-comment-dots' style='color:#94a3b8; margin-right:5px;'></i> {$remark}
                </div>";
            }

            if (!empty($office_items)) {
                $progress_msg .= "<div class='log-anim' style='margin-bottom:15px; animation-delay: 0.2s;'>";
                $progress_msg .= "<div style='font-size:0.75rem; font-weight:700; color:#64748b; margin-bottom:5px; text-transform:uppercase;'>รายการที่ตรวจรับแล้ว</div>";
                $progress_msg .= "<div style='display:flex; flex-direction:column; gap:8px;'>";
                foreach ($office_items as $itm) {
                    $progress_msg .= "
                    <div style='background:#fff; border:1px solid #e2e8f0; border-left:5px solid {$border_left}; padding:12px 15px; border-radius:8px; display:flex; align-items:center; gap:12px;'>
                        <div style='background:{$border_left}; color:#fff; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.7rem;'><i class='fas fa-check'></i></div>
                        <div style='font-size:0.95rem; color:#334155; font-weight:600;'>" . htmlspecialchars($itm) . "</div>
                    </div>";
                }
                $progress_msg .= "</div></div>";
            }

            if ($file_name) {
                $progress_msg .= "
                <div class='log-anim' style='margin-top:10px; animation-delay: 0.3s;'>
                    <a href='uploads/proofs/{$file_name}' target='_blank' class='btn-office-full' style='background: {$btn_grad}; box-shadow: 0 4px 10px {$btn_shadow};'>
                        <i class='fas fa-image fa-lg'></i> ดูรูปหลักฐานแนบ
                    </a>
                </div>";
            }
            $progress_msg .= "</div>";

            // 3. 🔥 [จุดที่ 2] อัปเดตข้อมูลใน office_log ให้มีคีย์ 'at' และ 'by' ที่ชัดเจน
            $data['details']['office_log'][] = [
                'status' => 'at_office_confirmed',
                'by' => $user_name,
                'at' => $current_time, // <--- ตรงนี้สำคัญมาก
                'msg' => $remark,
                'items' => $office_items,
                'file' => $file_name
            ];

            // 4. บันทึกเข้า Main Log
            $logs[] = ['at' => $current_time, 'by' => $user_name, 'msg' => $progress_msg];

            // 5. บันทึกกลับลง Database
            $new_json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $new_logs = json_encode($logs, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("UPDATE service_requests SET received_item_list = ?, progress_logs = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_json, $new_logs, $req_id);

            if ($stmt->execute())
                echo json_encode(['status' => 'success']);
            else
                echo json_encode(['status' => 'error', 'message' => $stmt->error]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // --- [แก้ไข] 1.5.1 ยืนยันการรับช่วงต่อ (Premium Design + Animations) ---
    if ($_POST['action'] == 'confirm_office_receipt') {
        header('Content-Type: application/json');

        if (!isset($conn) || $conn->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }

        try {
            $req_id = intval($_POST['req_id']);
            $remark = trim($_POST['remark']);
            $checked_items = isset($_POST['checked_items']) ? $_POST['checked_items'] : [];
            $user_name = $_SESSION['fullname'] ?? 'Unknown';

            // 1. ดึงข้อมูลเดิม
            $res = $conn->query("SELECT received_item_list, progress_logs FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();
            $data_json = json_decode($row_data['received_item_list'] ?? '{}', true);
            $logs = json_decode($row_data['progress_logs'] ?? '[]', true);

            // 2. จัดการไฟล์แนบ
            $file_name = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
                $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
                $file_name = 'handover_' . $req_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['proof_file']['tmp_name'], 'uploads/proofs/' . $file_name);
            }

            // 🟢 Logic: เปลี่ยนสถานะสินค้า
            foreach ($checked_items as $name) {
                $data_json['items_status'][$name] = 'at_office_confirmed';
            }

            // =====================================================================================
            // 🔥 สร้าง HTML Log (Premium 3D & Organized Layout) 🔥
            // =====================================================================================

            $theme_color = '#3b82f6'; // สีฟ้า
            $theme_bg = '#eff6ff';
            $shadow_color = 'rgba(59, 130, 246, 0.2)';

            $css_anim = "<style>@keyframes fadeInUp { from { opacity:0; transform:translateY(15px); } to { opacity:1; transform:translateY(0); } }</style>";
            $progress_msg = $css_anim . "<div style='font-family:Prompt, sans-serif; position:relative;'>";

            // --- 1. Header (หัวข้อ) ---
            $progress_msg .= "<div style='display:flex; align-items:center; gap:12px; margin-bottom:15px; animation: fadeInUp 0.5s ease forwards;'>";
            $progress_msg .= "  <div style='width:42px; height:42px; background:linear-gradient(135deg, #3b82f6, #1d4ed8); color:#fff; border-radius:10px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px {$shadow_color}; font-size:1.1rem;'><i class='fas fa-clipboard-check'></i></div>";
            $progress_msg .= "  <div>";
            $progress_msg .= "      <div style='font-weight:700; color:#1e3a8a; font-size:1rem;'>รับช่วงต่อ / ตรวจรับสินค้า</div>";
            $progress_msg .= "      <div style='font-size:0.8rem; color:#64748b;'>ผู้ดำเนินการ: <b>{$user_name}</b></div>";
            $progress_msg .= "  </div>";
            $progress_msg .= "</div>";

            // --- 2. Note Box (หมายเหตุ) ---
            if (!empty($remark)) {
                $progress_msg .= "<div style='background:#f8fafc; padding:10px 14px; border-radius:8px; font-size:0.9rem; color:#475569; margin-bottom:15px; border:1px dashed #cbd5e1; line-height:1.5; animation: fadeInUp 0.5s ease 0.1s forwards; opacity:0;'>";
                $progress_msg .= "  <i class='fas fa-comment-dots' style='color:#94a3b8; margin-right:5px;'></i> {$remark}";
                $progress_msg .= "</div>";
            }

            // --- 3. Item List (รายการสินค้า - แยกการ์ด) ---
            if (!empty($checked_items)) {
                $progress_msg .= "<div style='margin-bottom:10px; animation: fadeInUp 0.5s ease 0.2s forwards; opacity:0;'>";
                $progress_msg .= "  <div style='font-size:0.75rem; font-weight:700; color:#64748b; padding-bottom:5px; text-transform:uppercase;'>รายการที่ตรวจรับ (" . count($checked_items) . ")</div>";
                $progress_msg .= "  <div style='display:flex; flex-direction:column; gap:8px;'>";

                foreach ($checked_items as $index => $item) {
                    $delay = 0.3 + ($index * 0.1);
                    // การ์ดสินค้าแต่ละชิ้น
                    $progress_msg .= "<div style='background:#fff; border:1px solid #e2e8f0; border-left:4px solid #10b981; padding:10px 14px; border-radius:8px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 4px rgba(0,0,0,0.02); animation: fadeInUp 0.4s ease {$delay}s forwards; opacity:0;'>";
                    $progress_msg .= "  <div style='color:#10b981;'><i class='fas fa-check-circle'></i></div>"; // ไอคอนติ๊กถูก
                    $progress_msg .= "  <div style='font-size:0.9rem; color:#334155; font-weight:500;'>" . htmlspecialchars($item) . "</div>";
                    $progress_msg .= "</div>";
                }
                $progress_msg .= "  </div>";
                $progress_msg .= "</div>";
            }

            // --- 4. File Button ---
            if ($file_name) {
                $progress_msg .= "<div style='margin-top:15px; text-align:center; animation: fadeInUp 0.5s ease 0.4s forwards; opacity:0;'>";
                $progress_msg .= "  <a href='uploads/proofs/{$file_name}' target='_blank' style='display:inline-block; width:100%; box-sizing:border-box; background:linear-gradient(to right, #3b82f6, #2563eb); color:#fff; padding:10px 0; border-radius:8px; text-decoration:none; font-size:0.9rem; font-weight:600; box-shadow:0 4px 6px rgba(37, 99, 235, 0.2); transition:transform 0.2s;'>";
                $progress_msg .= "      <i class='fas fa-image' style='margin-right:6px;'></i> ดูรูปหลักฐานแนบ";
                $progress_msg .= "  </a>";
                $progress_msg .= "</div>";
            }

            $progress_msg .= "</div>"; // จบ Container

            // 4. บันทึกข้อมูลลง Database
            if (!isset($data_json['details']['office_log'])) {
                $data_json['details']['office_log'] = [];
            }

            // เพิ่ม Log ย่อยใน JSON
            $data_json['details']['office_log'][] = [
                'status' => 'handover',
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'msg' => $remark,
                'file' => $file_name,
                'items' => $checked_items
            ];

            // เพิ่ม Log หลัก (HTML)
            $logs[] = [
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'msg' => $progress_msg
            ];

            $new_json = json_encode($data_json, JSON_UNESCAPED_UNICODE);
            $new_logs = json_encode($logs, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("UPDATE service_requests SET received_item_list = ?, progress_logs = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_json, $new_logs, $req_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $stmt->error]);
            }

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // --- [ฉบับแก้ไขสมบูรณ์] 1.6 ส่งคืนลูกค้า / ปิดงาน (เก็บประวัติเรตติ้งแยกตามรอบ ไม่ทับของเดิม!) ---
    if ($_POST['action'] == 'return_to_customer') {
        header('Content-Type: application/json');

        try {
            $req_id = intval($_POST['req_id']);
            $user_name = $_SESSION['fullname'] ?? 'Unknown';

            // รับค่าจากฟอร์ม
            $rating = intval($_POST['rating'] ?? 0);
            $new_remark = isset($_POST['return_remark']) ? trim($_POST['return_remark']) : '';
            $return_items = $_POST['returned_items'] ?? [];
            $is_final = intval($_POST['is_final'] ?? 0);
            $summary_default = "ส่งมอบอุปกรณ์คืนลูกค้า";

            // 1. จัดการโฟลเดอร์อัปโหลด
            $upload_dir = 'uploads/returns/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);

            // 2. จัดการไฟล์แนบ (รูปรวม/บิลรวมหลัก)
            $proof_file = null;
            if (isset($_FILES['return_proof']) && $_FILES['return_proof']['error'] == 0) {
                $ext = pathinfo($_FILES['return_proof']['name'], PATHINFO_EXTENSION);
                $proof_file = 'ret_main_' . $req_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['return_proof']['tmp_name'], $upload_dir . $proof_file);
            }

            // 3. จัดการไฟล์แนบแบบ **รายชิ้น** (ถ้ามีส่งมา)
            // คาดหวังว่าฝั่ง JS จะส่งไฟล์มาในชื่อ input array เช่น return_item_proofs[0], return_item_proofs[1]
            $item_files_map = []; // เก็บว่าสินค้ารายการไหน มีรูปอะไรแนบมาบ้าง
            if (!empty($_FILES['return_item_proofs'])) {
                foreach ($_FILES['return_item_proofs']['name'] as $idx => $name) {
                    if ($_FILES['return_item_proofs']['error'][$idx] == 0) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $item_file_name = 'ret_itm_' . $req_id . '_' . $idx . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['return_item_proofs']['tmp_name'][$idx], $upload_dir . $item_file_name)) {
                            // Map ไฟล์เข้ากับชื่อสินค้า
                            if (isset($return_items[$idx])) {
                                $item_name_clean = trim($return_items[$idx]);
                                $item_files_map[$item_name_clean] = $item_file_name;
                            }
                        }
                    }
                }
            }

            // 4. ดึงข้อมูลเดิมจาก DB
            $res = $conn->query("SELECT progress_logs, received_item_list, status, return_file_path, return_remark FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();

            $old_remark = $row_data['return_remark'] ?? '';
            $final_remark = $old_remark;
            if ($new_remark !== '') {
                $timestamp = date('d/m/Y H:i');
                $prefix = ($old_remark !== '') ? "\n----------------\n" : "";
                $final_remark .= "{$prefix}[{$timestamp}] {$new_remark}";
            }

            $raw_logs = $row_data['progress_logs'];
            $current_logs = json_decode($raw_logs, true) ?: [];
            $data_json = json_decode($row_data['received_item_list'] ?? '{}', true) ?: [];
            $current_file_path = $row_data['return_file_path'];

            // Sync รายการ finished_items (อัปเดตสถานะว่าจบแล้ว)
            if (!isset($data_json['finished_items']))
                $data_json['finished_items'] = [];
            foreach ($return_items as $itm) {
                $itm_clean = trim($itm);
                if (!in_array($itm_clean, $data_json['finished_items'])) {
                    $data_json['finished_items'][] = $itm_clean;
                }
            }

            // 5. บันทึกประวัติการคืน "แยกเป็นรอบๆ" (ประวัติเก่าจะไม่หาย)
            if (!isset($data_json['return_history'])) {
                $data_json['return_history'] = [];
            }

            // สร้าง Array รายละเอียดสินค้าสำหรับเก็บในประวัติ
            $items_with_details = [];
            foreach ($return_items as $itm) {
                $itm_clean = trim($itm);
                $items_with_details[] = [
                    'name' => $itm_clean,
                    'file' => $item_files_map[$itm_clean] ?? null // ใส่รูปรายชิ้นเข้าไป
                ];
            }

            $data_json['return_history'][] = [
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'rating' => $rating,
                'remark' => $new_remark,
                'items_detail' => $items_with_details, // บันทึกแบบ Array ที่มีทั้งชื่อและไฟล์
                'items' => $return_items, // เก็บชื่อไว้เฉยๆ เพื่อความเข้ากันได้กับโค้ดเก่า
                'file' => $proof_file // รูปรวม
            ];

            // อัปเดตรายการสินค้าที่คืนแล้วรวมๆ ไว้
            if (!isset($data_json['details']['customer_return']['items_returned'])) {
                $data_json['details']['customer_return']['items_returned'] = [];
            }
            foreach ($return_items as $it) {
                if (!in_array(trim($it), $data_json['details']['customer_return']['items_returned'])) {
                    $data_json['details']['customer_return']['items_returned'][] = trim($it);
                }
            }
            $data_json['details']['customer_return']['at'] = date('d/m/Y H:i');
            $data_json['details']['customer_return']['by'] = $user_name;

            // ====================================================================
            // 6. สร้าง HTML Log สำหรับแสดงใน Timeline
            // ====================================================================
            $item_repair_summaries = $data_json['item_repair_summaries'] ?? [];
            $theme_color = ($is_final == 1) ? "#10b981" : "#8b5cf6";
            $status_title = ($is_final == 1) ? "งานซ่อมเสร็จสมบูรณ์ส่งคืนครบถ้วน" : "📦 ส่งคืนอุปกรณ์บางส่วน";

            $new_msg_html = "
            <style>@keyframes popIn { 0% { transform: scale(0.8); opacity:0; } 100% { transform: scale(1); opacity:1; } }</style>
            <div style='font-family:Prompt, sans-serif;'>
                <div style='display:flex; align-items:center; gap:10px; margin-bottom:10px;'>
                    <div style='width:36px; height:36px; background:linear-gradient(135deg, {$theme_color}, #4c1d95); color:#fff; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:0.9rem;'>
                        <i class='fas " . ($is_final == 1 ? "fa-check-double" : "fa-box-open") . "'></i>
                    </div>
                    <div>
                        <div style='font-weight:700; color:#1e293b; font-size:0.9rem;'>{$status_title}</div>
                        <div style='font-size:0.75rem; color:#64748b;'>โดย: {$user_name}</div>
                    </div>
                </div>";

            // --- ส่วนแสดงรายการสินค้า (พร้อมรูปรายชิ้นถ้ามี) ---
            if (!empty($return_items)) {
                $new_msg_html .= "<div style='margin-bottom:12px; padding-left:5px;'>";
                foreach ($return_items as $item) {
                    $item_name_clean = trim($item);
                    $sum_text = $item_repair_summaries[$item_name_clean] ?? '-';
                    $has_item_file = isset($item_files_map[$item_name_clean]);

                    $new_msg_html .= "<div style='background:#f8fafc; border-left:3px solid {$theme_color}; padding:8px 10px; border-radius:6px; margin-bottom:6px;'>
                        <div style='display:flex; justify-content:space-between; align-items:start;'>
                            <div>
                                <div style='font-weight:600; font-size:0.85rem; color:#334155;'>{$item_name_clean}</div>
                                <div style='font-size:0.75rem; color:#059669; margin-top:2px;'>└ ซ่อม: {$sum_text}</div>
                            </div>";

                    // ปุ่มเปิดรูปรายชิ้น
                    if ($has_item_file) {
                        $item_file_url = 'uploads/returns/' . $item_files_map[$item_name_clean];
                        $new_msg_html .= "
                            <div>
                                <a href='{$item_file_url}' target='_blank' style='display:inline-block; background:#e0e7ff; color:#4338ca; font-size:0.7rem; padding:3px 8px; border-radius:12px; text-decoration:none; font-weight:600;'>
                                    <i class='fas fa-image'></i> ดูรูป
                                </a>
                            </div>";
                    }

                    $new_msg_html .= "</div></div>"; // ปิด flex / ปิดการ์ดสินค้า
                }
                $new_msg_html .= "</div>";
            }

            // --- ข้อเสนอแนะ ---
            if ($new_remark) {
                $new_msg_html .= "<div style='background:#fffbeb; border:1px dashed #f59e0b; padding:8px 12px; border-radius:6px; font-size:0.8rem; color:#92400e; margin-bottom:10px;'><b>💬 ข้อเสนอแนะ:</b> {$new_remark}</div>";
            }

            // --- ส่วนแสดงไฟล์รวม (บิลรวม/ใบเซ็นรับ) ---
            if ($proof_file) {
                $new_msg_html .= "
                <div style='margin-bottom:10px;'>
                    <a href='uploads/returns/{$proof_file}' target='_blank' style='display:flex; align-items:center; justify-content:center; gap:8px; background:linear-gradient(to right, #f1f5f9, #e2e8f0); color:#475569; padding:8px 10px; border-radius:8px; font-size:0.8rem; text-decoration:none; font-weight:600; border:1px solid #cbd5e1; transition:0.2s;'>
                        <i class='fas fa-file-invoice fa-lg' style='color:#64748b;'></i> ดูรูปหลักฐานใบส่งมอบ (รวม)
                    </a>
                </div>";
            }

            // --- การให้คะแนนดาว ---
            if ($rating > 0) {
                $stars_display = "";
                for ($i = 1; $i <= 5; $i++) {
                    $stars_display .= ($i <= $rating) ? "<i class='fas fa-star' style='color:#fff; font-size:1rem; margin-right:2px;'></i>" : "<i class='far fa-star' style='color:rgba(255,255,255,0.6); font-size:1rem; margin-right:2px;'></i>";
                }
                $new_msg_html .= "
                <div style='animation: popIn 0.5s ease forwards;'>
                    <div style='background:linear-gradient(135deg, #f59e0b, #d97706); border-radius:8px; padding:10px 15px; display:flex; align-items:center; justify-content:space-between; color:#fff; box-shadow:0 4px 10px -3px rgba(245, 158, 11, 0.4);'>
                        <div style='display:flex; align-items:center; gap:8px;'>
                            <div style='font-size:0.8rem; font-weight:700; text-transform:uppercase;'>คะแนนความพึงพอใจ</div>
                            <div style='background:rgba(255,255,255,0.25); padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:700;'>{$rating}/5</div>
                        </div>
                        <div>{$stars_display}</div>
                    </div>
                </div>";
            }
            $new_msg_html .= "</div>"; // ปิด Main wrapper

            $current_logs[] = ['at' => date('d/m/Y H:i'), 'by' => $user_name, 'msg' => $new_msg_html];

            // 7. เตรียมข้อมูลอัปเดต SQL
            $new_status = ($is_final == 1) ? 'completed' : 'in_progress';

            // ถ้ามีไฟล์รวมให้ยึดไฟล์ใหม่, ถ้าไม่มีใช้ไฟล์เดิม (ของรอบเก่า)
            $final_file_path = $proof_file ? $proof_file : $current_file_path;

            $logs_json_final = json_encode($current_logs, JSON_UNESCAPED_UNICODE);
            $final_json = json_encode($data_json, JSON_UNESCAPED_UNICODE);

            $sql = "UPDATE service_requests SET 
                status = ?, 
                completed_at = " . ($is_final == 1 ? "NOW()" : "completed_at") . ", 
                completed_by = " . ($is_final == 1 ? "'$user_name'" : "completed_by") . ",
                return_rating = ?, 
                return_remark = ?, 
                return_summary = ?, 
                return_file_path = ?, 
                progress_logs = ?, 
                received_item_list = ? 
                WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisssssi", $new_status, $rating, $final_remark, $summary_default, $final_file_path, $logs_json_final, $final_json, $req_id);

            if ($stmt->execute()) {
                // บันทึกเรตติ้งเพื่อไปโชว์สรุปบนการ์ด Dashboard
                if ($rating > 0) {
                    $stmt_r = $conn->prepare("INSERT INTO service_ratings (req_id, rating, comment, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt_r->bind_param("iis", $req_id, $rating, $new_remark);
                    $stmt_r->execute();
                }
                echo json_encode(['status' => 'success']);
            } else {
                throw new Exception($stmt->error);
            }

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // --- [แก้ไขตัวเต็มสมบูรณ์] 1.7 รับของกลับจากร้านซ่อม & บันทึกค่าใช้จ่าย (จำสถานะรายชิ้น + บันทึกเบอร์โทร) ---
    if ($_POST['action'] == 'receive_from_shop') {
        header('Content-Type: application/json');

        if (!isset($conn) || $conn->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }

        try {
            $req_id = intval($_POST['req_id']);
            $total_cost = floatval($_POST['repair_cost']);
            $items_json = $_POST['repair_items'];

            // 1. รับข้อมูล
            $return_items = json_decode($_POST['return_items'] ?? '[]', true);
            $shop_name = $_POST['shop_name'] ?? 'ร้านภายนอก';
            $return_remark = trim($_POST['return_remark'] ?? '');
            $user_name = $_SESSION['fullname'] ?? 'Unknown';

            // 2. จัดการไฟล์แนบ
            $file_name = null;
            if (isset($_FILES['shop_file']) && $_FILES['shop_file']['error'] == 0) {
                $upload_dir = 'uploads/repairs/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['shop_file']['name'], PATHINFO_EXTENSION);
                $file_name = 'rep_' . $req_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['shop_file']['tmp_name'], $upload_dir . $file_name);
            }

            // 3. ดึงข้อมูลเดิม
            $res_data = $conn->query("SELECT received_item_list, progress_logs, additional_cost, cost_details FROM service_requests WHERE id = $req_id");
            $row_data = $res_data->fetch_assoc();

            $data_json = json_decode($row_data['received_item_list'] ?? '{}', true);
            $raw_logs = $row_data['progress_logs'];
            $logs = json_decode($raw_logs, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($logs)) {
                $logs = [];
            }
            $items_status = $data_json['items_status'] ?? [];

            // =================================================================================
            // 🔥 1. ค้นหาเบอร์โทร/ผู้ติดต่อ (ห้ามลบ!)
            // 🔥 2. Map หมายเหตุตอนส่งออก (items_moved) เพื่อเอามาโชว์คู่สินค้า
            // =================================================================================
            $shop_phone = '-';
            $shop_contact = '-';
            $item_remarks_map = []; // เก็บหมายเหตุ: ชื่อสินค้า => หมายเหตุตอนส่ง

            $move_history = $data_json['items_moved'] ?? [];
            foreach ($move_history as $move) {
                // หาข้อมูลร้าน
                if (isset($move['shop_info']['name']) && $move['shop_info']['name'] === $shop_name) {
                    // เจอร้านแล้ว เอาเบอร์ล่าสุดที่หาได้
                    if (!empty($move['shop_info']['phone']))
                        $shop_phone = $move['shop_info']['phone'];
                    if (!empty($move['shop_info']['owner']))
                        $shop_contact = $move['shop_info']['owner'];
                }
                // เก็บหมายเหตุของสินค้าแต่ละชิ้น
                $m_name = trim($move['name'] ?? '');
                $m_remark = trim($move['remark'] ?? '');
                if ($m_name && $m_remark) {
                    $item_remarks_map[$m_name] = $m_remark;
                }
            }

            // 4. เปลี่ยนสถานะสินค้า
            $items_returned_from_shop = [];
            if (!empty($return_items)) {
                foreach ($return_items as $name) {
                    $items_status[$name] = 'at_office_unconfirmed';
                    $items_returned_from_shop[] = $name;
                }
            }

            // =================================================================================
            // 🔥 สร้าง HTML Log
            // =================================================================================
            $items_arr = json_decode($items_json, true);

            $css_style = "
            <style>
                @keyframes fadeInUp { from { opacity:0; transform:translateY(15px); } to { opacity:1; transform:translateY(0); } }
                @keyframes pulsePink { 0% { box-shadow: 0 0 0 0 rgba(236, 72, 153, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(236, 72, 153, 0); } 100% { box-shadow: 0 0 0 0 rgba(236, 72, 153, 0); } }
                .log-anim { animation: fadeInUp 0.5s ease forwards; }
                .btn-pink-full {
                    display: flex; align-items: center; justify-content: center; gap: 8px;
                    width: 100%; padding: 12px 1px; border-radius: 8px;
                    background: linear-gradient(to right, #db2777, #be185d); 
                    color: #fff !important; font-weight: 700; text-decoration: none; font-size: 0.95rem;
                    box-shadow: 0 4px 6px -1px rgba(219, 39, 119, 0.3);
                    transition: all 0.2s; border: none; margin-top: 10px;
                }
                .btn-pink-full:hover { transform: translateY(-2px); box-shadow: 0 8px 12px -2px rgba(219, 39, 119, 0.4); filter: brightness(1.1); }
            </style>";

            $progress_msg = $css_style . "<div style='font-family:Prompt, sans-serif; position:relative;'>";

            // --- 1. Header (Pulse Animation) ---
            $progress_msg .= "
            <div class='log-anim' style='display:flex; align-items:center; gap:15px; margin-bottom:15px;'>
                <div style='flex-shrink:0; width:50px; height:50px; background:linear-gradient(135deg, #ec4899, #be185d); color:#fff; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; box-shadow:0 8px 20px -4px rgba(190, 24, 93, 0.5); animation: pulsePink 2s infinite;'>
                    <i class='fas fa-file-invoice-dollar'></i>
                </div>
                <div>
                    <div style='font-weight:800; color:#831843; font-size:1.1rem;'>รับของกลับจากร้านซ่อม</div>
                    <div style='font-size:0.85rem; color:#9d174d;'>โดย: <b>{$user_name}</b></div>
                </div>
            </div>";

            // --- 2. Shop Info (แสดงครบ: ร้าน, ผู้ติดต่อ, เบอร์โทร) ---
            $progress_msg .= "
            <div class='log-anim' style='background:#fdf2f8; border:1px solid #fbcfe8; border-left:4px solid #db2777; padding:10px 15px; border-radius:8px; margin-bottom:15px; animation-delay: 0.1s;'>
                <div style='display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;'>
                    <div>
                        <div style='font-size:0.9rem; font-weight:700; color:#9d174d;'>{$shop_name}</div>
                        <div style='font-size:0.8rem; color:#be185d; margin-top:2px;'><i class='fas fa-user-circle'></i> ติดต่อ: {$shop_contact}</div>
                    </div>
                    <div style='font-size:0.85rem; font-weight:600; color:#be185d; background:#fff; padding:4px 10px; border-radius:15px; border:1px solid #fbcfe8;'>
                        <i class='fas fa-phone-alt'></i> {$shop_phone}
                    </div>
                </div>
            </div>";

            // --- 3. สินค้าที่รับกลับ (แสดงการ์ด + ดึงหมายเหตุตอนส่งมาโชว์) ---
            if (!empty($items_returned_from_shop)) {
                $progress_msg .= "<div class='log-anim' style='margin-bottom:15px; animation-delay: 0.2s;'>";
                $progress_msg .= "<div style='font-size:0.75rem; font-weight:700; color:#db2777; margin-bottom:5px; text-transform:uppercase;'>📦 สินค้าที่รับกลับ</div>";
                $progress_msg .= "<div style='display:flex; flex-direction:column; gap:8px;'>";

                foreach ($items_returned_from_shop as $itm_name) {
                    // ดึงหมายเหตุตอนส่งออก (ถ้ามี)
                    $prev_note = $item_remarks_map[$itm_name] ?? '';

                    $progress_msg .= "
                    <div style='background:#fff; border:1px solid #fce7f3; border-left:5px solid #db2777; padding:10px 15px; border-radius:8px; display:flex; flex-direction:column; gap:4px; box-shadow:0 2px 4px rgba(0,0,0,0.02);'>
                        <div style='display:flex; align-items:center; gap:10px;'>
                            <div style='background:#db2777; color:#fff; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; flex-shrink:0;'>
                                <i class='fas fa-check'></i>
                            </div>
                            <div style='font-size:0.95rem; color:#831843; font-weight:600;'>{$itm_name}</div>
                        </div>";

                    // ถ้ามีหมายเหตุตอนส่ง ให้โชว์ด้วย
                    if ($prev_note) {
                        $progress_msg .= "<div style='font-size:0.8rem; color:#64748b; padding-left:32px;'><i class='fas fa-history' style='font-size:0.7rem;'></i> <b>หมายเหตุนำของออก:</b> {$prev_note}</div>";
                    }
                    $progress_msg .= "</div>";
                }
                $progress_msg .= "</div></div>";
            }

            // --- 4. ตารางค่าซ่อม (ชิดซ้ายยาวๆ ตามสั่ง) ---
            $progress_msg .= "<div class='log-anim' style='background:#fff; border:1px solid #fbcfe8; border-radius:12px; overflow:hidden; margin-bottom:15px; animation-delay: 0.3s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>";
            $progress_msg .= "
            <div style='background:#fce7f3; padding:10px 15px; border-bottom:1px solid #fbcfe8;'>
                <div style='font-weight:700; color:#9d174d; font-size:0.9rem;'><i class='fas fa-list-ul'></i> รายละเอียดค่าซ่อม</div>
            </div>
            <table style='width:100%; border-collapse:collapse; font-size:0.85rem;'>
                <thead>
                    <tr style='color:#831843; border-bottom:2px solid #fbcfe8; background:#fff0f7;'>
                        <th style='padding:10px 15px; text-align:left; font-weight:700;'>รายการ</th>
                        <th style='padding:10px; text-align:center; font-weight:700; width:60px; white-space:nowrap;'>จำนวน</th>
                        <th style='padding:10px 15px; text-align:right; font-weight:700; width:90px; white-space:nowrap;'>รวม (฿)</th>
                    </tr>
                </thead>
                <tbody>";

            $summary_text = "";
            foreach ($items_arr as $idx => $it) {
                $line_total = number_format($it['total'], 2);
                $bg_row = ($idx % 2 == 0) ? '#fff' : '#fff9fc';
                $progress_msg .= "
                <tr style='background:{$bg_row}; border-bottom:1px dashed #fce7f3;'>
                    <td style='padding:10px 15px; color:#334155; font-weight:500; vertical-align:top; text-align:left;'>{$it['name']}</td>
                    <td style='padding:10px; text-align:center; color:#64748b; vertical-align:top;'>x{$it['qty']}</td>
                    <td style='padding:10px 15px; text-align:right; color:#be185d; font-weight:700; vertical-align:top;'>{$line_total}</td>
                </tr>";
                $summary_text .= "- {$it['name']} ({$it['qty']} x " . number_format($it['price'], 2) . " = {$line_total})\n";
            }
            $progress_msg .= "</tbody>
                <tfoot>
                    <tr style='background:#fdf2f8; border-top:2px solid #fbcfe8;'>
                        <td colspan='2' style='padding:12px 15px; text-align:right; font-weight:700; color:#831843;'>รวมสุทธิ</td>
                        <td style='padding:12px 15px; text-align:right; color:#db2777; font-weight:800; font-size:1.1rem;'>" . number_format($total_cost, 2) . "</td>
                    </tr>
                </tfoot>
            </table></div>";

            // --- 5. Remark (หมายเหตุรับกลับ) ---
            if ($return_remark) {
                $progress_msg .= "<div class='log-anim' style='background:#fff; padding:12px 15px; border-radius:10px; font-size:0.85rem; color:#64748b; margin-bottom:15px; border:1px solid #e5e7eb; animation-delay: 0.4s;'><i class='fas fa-comment-dots' style='color:#9ca3af; margin-right:5px;'></i> {$return_remark}</div>";
            }

            // --- 6. File Button ---
            if ($file_name) {
                $progress_msg .= "
                <div class='log-anim' style='margin-top:10px; animation-delay: 0.5s;'>
                    <a href='uploads/repairs/{$file_name}' target='_blank' class='btn-pink-full'>
                        <i class='fas fa-receipt fa-lg'></i> ดูใบเสร็จ/หลักฐานแนบ
                    </a>
                </div>";
            }

            $progress_msg .= "</div>"; // End Wrapper

            // 6. บันทึกข้อมูล
            $logs[] = ['at' => date('d/m/Y H:i'), 'by' => $user_name, 'msg' => $progress_msg];
            $data_json['items_status'] = $items_status;

            if (!isset($data_json['details']['office_log']))
                $data_json['details']['office_log'] = [];

            $data_json['details']['office_log'][] = [
                'status' => 'back_from_shop',
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'total_cost' => $total_cost,
                'expenses' => $items_arr,
                'shop' => $shop_name,
                'shop_phone' => $shop_phone,
                'shop_contact' => $shop_contact,
                'items' => $items_returned_from_shop,
                'file' => $file_name,
                'approved' => false
            ];

            $new_json_str = json_encode($data_json, JSON_UNESCAPED_UNICODE);
            $new_logs_str = json_encode($logs, JSON_UNESCAPED_UNICODE);
            $new_cost_details = trim(($row_data['cost_details'] ?? '') . "\n--- " . date('d/m/Y') . " ---\n" . $summary_text);

            $sql = "UPDATE service_requests SET additional_cost = additional_cost + ?, cost_details = ?, cost_status = 'pending', received_item_list = ?, progress_logs = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dsssi", $total_cost, $new_cost_details, $new_json_str, $new_logs_str, $req_id);

            if ($stmt->execute())
                echo json_encode(['status' => 'success']);
            else
                echo json_encode(['status' => 'error', 'message' => $stmt->error]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // [แก้ไข] อนุมัติค่าใช้จ่าย + บันทึกผู้กด
    if ($_POST['action'] == 'approve_cost') {
        header('Content-Type: application/json');
        try {
            $req_id = intval($_POST['req_id']);
            $user_name = $_SESSION['fullname'] ?? 'Admin';

            $res = $conn->query("SELECT received_item_list, progress_logs, additional_cost FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();

            $data_json = json_decode($row_data['received_item_list'] ?? '{}', true);
            $logs = json_decode($row_data['progress_logs'] ?? '[]', true);
            $total_pending_cost = $row_data['additional_cost'];

            $expense_details_html = "";
            $found_any = false;

            // 🔥 Logic ใหม่: วนลูปหา "ทุกรายการ" ใน Log ที่ยังไม่ได้อนุมัติ
            if (!empty($data_json['details']['office_log'])) {
                foreach ($data_json['details']['office_log'] as $key => &$ol) {
                    // ถ้าเป็นรายการรับของกลับ และ ยังไม่ได้อนุมัติ
                    if ($ol['status'] === 'back_from_shop' && (!isset($ol['approved']) || $ol['approved'] === false)) {

                        $found_any = true;
                        $shop_name_in_log = $ol['shop'] ?? 'ร้านภายนอก'; // ดึงชื่อร้านจาก Log

                        $expense_details_html .= "<div style='margin-top:10px; border-top:1px dashed #cbd5e1; padding-top:8px;'>";
                        $expense_details_html .= "<b style='color:#be185d; font-size:0.85rem;'><i class='fas fa-store'></i> จากร้าน: $shop_name_in_log</b>";
                        $expense_details_html .= "<ul style='margin:5px 0; padding-left:20px; font-size:0.8rem; color:#475569;'>";

                        foreach ($ol['expenses'] as $ex) {
                            $expense_details_html .= "<li>{$ex['name']} x {$ex['qty']} = <b>" . number_format($ex['total'], 2) . "</b></li>";
                        }

                        $expense_details_html .= "</ul></div>";

                        // ✅ ทำเครื่องหมายว่ารายการนี้ "อนุมัติแล้ว" จะได้ไม่โชว์ซ้ำรอบหน้า
                        $ol['approved'] = true;
                    }
                }
            }

            // 3. สร้าง Log การอนุมัติ
            $progress_msg = "
            <div style='font-family:Prompt; background:#fff7ed; border:1px solid #fdba74; border-left:5px solid #f97316; padding:15px; border-radius:12px;'>
                <div style='display:flex; justify-content:space-between; align-items:center;'>
                    <span style='font-weight:800; color:#9a3412; font-size:1rem;'><i class='fas fa-check-shield'></i> อนุมัติยอดรวมทั้งหมดแล้ว</span>
                    <span style='background:#f97316; color:#fff; padding:2px 10px; border-radius:20px; font-size:0.85rem; font-weight:800;'>฿" . number_format($total_pending_cost, 2) . "</span>
                </div>
                <div style='font-size:0.85rem; color:#c2410c; margin-top:4px;'>ผู้อนุมัติ: <b>{$user_name}</b></div>
                {$expense_details_html}
            </div>";

            $logs[] = ['at' => date('d/m/Y H:i'), 'by' => $user_name, 'msg' => $progress_msg];

            $new_json_str = json_encode($data_json, JSON_UNESCAPED_UNICODE);
            $new_logs_str = json_encode($logs, JSON_UNESCAPED_UNICODE);

            // 4. อัปเดตสถานะ (ยอดเงินจะยังคงค้างไว้ แต่สถานะเป็น approved)
            $stmt = $conn->prepare("UPDATE service_requests SET cost_status = 'approved', received_item_list = ?, progress_logs = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_json_str, $new_logs_str, $req_id);

            if ($stmt->execute())
                echo json_encode(['status' => 'success']);
            else
                echo json_encode(['status' => 'error', 'message' => $stmt->error]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // 1.8 ดึงประวัติการให้คะแนน
    if ($_POST['action'] == 'get_rating_history') {
        header('Content-Type: application/json');

        // 🔥 แก้ SQL: ใช้ COALESCE เพื่อเลือกค่า ถ้า pc ไม่มี ให้ไปเอา manual จาก req
        $sql = "SELECT 
                -- 1. รหัสหน้างาน: เอาจาก PC ก่อน -> ถ้าไม่มีเอา manual_site_code -> ถ้าไม่มีขีด -
                COALESCE(pc.site_id, req.manual_site_code, '-') as site_code, 
                
                -- 2. ชื่อโครงการ: เอาจาก PC ก่อน -> ถ้าไม่มีเอา manual_project_name
                COALESCE(pc.project_name, req.manual_project_name, 'General Request') as project_name,
                
                -- 3. ดึงชื่อลูกค้า (manual) มาสำรองไว้ เผื่อต้องใช้ต่อท้าย
                req.manual_customer_name,
                
                sr.rating, 
                sr.comment, 
                sr.created_at
            FROM service_ratings sr
            JOIN service_requests req ON sr.req_id = req.id
            LEFT JOIN project_contracts pc ON req.site_id = pc.site_id 
            WHERE sr.rating > 0
            ORDER BY sr.created_at DESC";

        $res = $conn->query($sql);
        $history = [];

        if ($res) {
            while ($row = $res->fetch_assoc()) {

                // จัดการชื่อที่จะแสดง (Display Name)
                $displayName = $row['project_name'];

                // 🔥 ถ้าเป็นการกรอกเอง (เช็คว่า manual_customer_name มีค่าไหม และชื่อไม่ซ้ำกับโปรเจกต์)
                // เพื่อโชว์รูปแบบ: "ชื่อโครงการ (ชื่อลูกค้า)"
                if (!empty($row['manual_customer_name']) && strpos($displayName, $row['manual_customer_name']) === false) {
                    // ถ้าในชื่อโครงการยังไม่มีชื่อลูกค้า ให้ต่อท้ายเข้าไป
                    $displayName .= " (" . $row['manual_customer_name'] . ")";
                }

                $history[] = [
                    'site_id' => $row['site_code'],
                    'project_name' => $displayName, // ✅ โชว์ครบทั้งชื่อโครงการและลูกค้า (ถ้ามี)
                    'rating' => intval($row['rating']),
                    'comment' => $row['comment'] ?: '-',
                    'at' => date('d/m/Y H:i', strtotime($row['created_at']))
                ];
            }
        }
        echo json_encode($history);
        exit;
    }
    // --- [ส่วนที่ขาด: คำนวณตัวเลขให้การ์ดสรุป (Satisfaction Card)] ---
    if ($_POST['action'] == 'get_satisfaction_stats') {
        header('Content-Type: application/json');
        try {
            // 🔥 คำนวณจากตาราง service_ratings (รวมทุกรอบการส่งคืน)
            $sql = "SELECT 
                        COUNT(*) as total_count, 
                        AVG(rating) as avg_score 
                    FROM service_ratings 
                    WHERE rating > 0";

            $result = $conn->query($sql);
            $row = $result->fetch_assoc();

            $total = intval($row['total_count']);
            $avg = $row['avg_score'] ? number_format((float) $row['avg_score'], 1) : "0.0";

            echo json_encode(['status' => 'success', 'total' => $total, 'average' => $avg]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // 🔥 [แก้ไข: ไม่ให้ข้อมูลเก่าหาย] 1.9 บันทึกสรุปงานซ่อมรายชิ้น
    if ($_POST['action'] == 'save_repair_summary') {
        header('Content-Type: application/json');
        try {
            $req_id = intval($_POST['req_id']);
            $new_summaries = json_decode($_POST['summaries'], true) ?? [];

            $res = $conn->query("SELECT received_item_list FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();
            $data_json = json_decode($row_data['received_item_list'] ?? '{}', true) ?: [];

            $existing_summaries = $data_json['item_repair_summaries'] ?? [];

            // 🔥 วนลูปอัปเดตเฉพาะอันที่ส่งมา อันที่ไม่ได้ส่งมาจะคงเดิม
            foreach ($new_summaries as $item_name => $text) {
                $existing_summaries[$item_name] = $text;
            }

            $data_json['item_repair_summaries'] = $existing_summaries;
            $new_json = json_encode($data_json, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("UPDATE service_requests SET received_item_list = ? WHERE id = ?");
            $stmt->bind_param("si", $new_json, $req_id);

            if ($stmt->execute())
                echo json_encode(['status' => 'success']);
            else
                throw new Exception($stmt->error);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // 🔥 [แก้ไขล่าสุด] 1.10 ระบบจัดการอนุมัติหลายบิล (เพิ่มรายการรับกลับ/หมายเหตุ/ไฟล์แนบ - ไม่โชว์ยอดสุทธิ)
    if ($_POST['action'] == 'process_multi_approval') {
        header('Content-Type: application/json');
        try {
            $req_id = intval($_POST['req_id']);
            $decisions = json_decode($_POST['decisions'], true);
            $user_name = $_SESSION['fullname'] ?? 'Admin';

            // 1. ดึงข้อมูล
            $res = $conn->query("SELECT received_item_list, progress_logs, additional_cost FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();
            $data_json = json_decode($row_data['received_item_list'] ?? '{}', true);
            $logs = json_decode($row_data['progress_logs'] ?? '[]', true);
            $current_total_cost = floatval($row_data['additional_cost']);

            $move_history = $data_json['items_moved'] ?? [];
            $total_rejected_amount = 0;

            // เริ่มสร้าง HTML สำหรับ Log
            $log_html = "<div style='font-family:Prompt; font-size:0.85rem; display:flex; flex-direction:column; gap:12px;'>";

            // 2. ประมวลผลแต่ละรายการ
            foreach ($decisions as $dec) {
                $idx = $dec['logIndex'];

                if (isset($data_json['details']['office_log'][$idx])) {
                    $log_item = &$data_json['details']['office_log'][$idx];

                    // --- ค้นหาข้อมูลร้าน (Logic เดิม) ---
                    $display_shop = $dec['shop'] ?? 'ร้านซ่อมภายนอก';
                    $display_contact = '-';
                    $display_phone = '-';

                    if ($display_shop !== 'ร้านซ่อมภายนอก') {
                        foreach ($move_history as $move) {
                            if (isset($move['shop_info']['name']) && $move['shop_info']['name'] === $display_shop) {
                                $display_contact = $move['shop_info']['owner'] ?? '-';
                                $display_phone = $move['shop_info']['phone'] ?? '-';
                                break;
                            }
                        }
                    }
                    if ($display_contact === '-') {
                        $bill_expenses = $log_item['expenses'] ?? [];
                        foreach ($bill_expenses as $ex) {
                            $search_name = trim($ex['name']);
                            for ($i = count($move_history) - 1; $i >= 0; $i--) {
                                $m = $move_history[$i];
                                if (isset($m['name']) && trim($m['name']) === $search_name && ($m['destination'] ?? '') === 'external' && isset($m['shop_info'])) {
                                    $display_shop = $m['shop_info']['name'] ?? $display_shop;
                                    $display_contact = $m['shop_info']['owner'] ?? '-';
                                    $display_phone = $m['shop_info']['phone'] ?? '-';
                                    break 2;
                                }
                            }
                        }
                    }
                    // ------------------------------------

                    // 1. สร้างตารางค่าใช้จ่าย
                    $expenses_list = $log_item['expenses'] ?? [];
                    $table_rows = "";
                    foreach ($expenses_list as $k => $ex) {
                        $qty = number_format($ex['qty'] ?? 0);
                        $price = number_format($ex['price'] ?? 0);
                        $total = number_format($ex['total'] ?? 0);
                        $bg_row = ($k % 2 == 0) ? '#ffffff' : '#f8fafc';
                        $table_rows .= "<tr style='background:{$bg_row}; border-bottom:1px solid #e2e8f0;'><td style='padding:4px 8px;'>{$ex['name']}</td><td style='padding:4px 8px; text-align:center;'>{$qty}</td><td style='padding:4px 8px; text-align:right;'>{$price}</td><td style='padding:4px 8px; text-align:right; font-weight:600;'>{$total}</td></tr>";
                    }

                    // 2. ข้อมูลเพิ่มเติมที่จะแสดง (รายการรับกลับ, หมายเหตุ, ไฟล์)
                    $extra_info_html = "";

                    // [รายการที่รับกลับ]
                    if (!empty($log_item['items'])) {
                        $items_str = implode(', ', $log_item['items']);
                        $extra_info_html .= "<div style='margin-top:8px; padding:6px; background:#f1f5f9; border-radius:6px; color:#475569; font-size:0.8rem;'><i class='fas fa-box'></i> <b>รายการที่รับกลับ:</b> {$items_str}</div>";
                    }

                    // [หมายเหตุจากร้าน]
                    if (!empty($log_item['remark'])) {
                        $extra_info_html .= "<div style='margin-top:5px; padding:6px; background:#fffbeb; border:1px solid #fcd34d; border-radius:6px; color:#92400e; font-size:0.8rem;'><i class='fas fa-comment'></i> <b>หมายเหตุ:</b> {$log_item['remark']}</div>";
                    }

                    // [ไฟล์แนบ]
                    if (!empty($log_item['file'])) {
                        $extra_info_html .= "<div style='margin-top:5px; text-align:right;'><a href='uploads/repairs/{$log_item['file']}' target='_blank' style='display:inline-block; padding:4px 10px; background:#e0e7ff; color:#4338ca; border-radius:15px; text-decoration:none; font-size:0.75rem; font-weight:600;'><i class='fas fa-paperclip'></i> ดูใบเสร็จ/หลักฐาน</a></div>";
                    }


                    // กำหนดสีและสถานะ
                    if ($dec['status'] === 'approved') {
                        $log_item['approved'] = true;
                        $border_color = "#10b981";
                        $bg_header = "#f0fdf4";
                        $icon_status = "<span style='background:#10b981; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem;'><i class='fas fa-check'></i> อนุมัติ</span>";
                        $note_html = "";
                        $amount_color = "#047857";
                    } else {
                        $log_item['approved'] = 'rejected';
                        $log_item['reject_reason'] = $dec['note'];
                        $total_rejected_amount += floatval($dec['amount']);

                        $border_color = "#ef4444";
                        $bg_header = "#fef2f2";
                        $icon_status = "<span style='background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem;'><i class='fas fa-times'></i> ปฏิเสธ</span>";
                        $note_html = "<div style='margin-top:8px; padding:8px; background:#fff1f2; border:1px dashed #fca5a5; border-radius:6px; color:#991b1b; font-size:0.8rem;'><b>⚠️ เหตุผลที่ปฏิเสธ:</b> {$dec['note']}</div>";
                        $amount_color = "#b91c1c";
                    }

                    // สร้างการ์ด HTML
                    $log_html .= "
                    <div style='border:1px solid {$border_color}; border-radius:8px; overflow:hidden;'>
                        <div style='padding:8px 12px; background:{$bg_header}; border-bottom:1px solid {$border_color}; display:flex; justify-content:space-between; align-items:start;'>
                            <div style='display:flex; gap:10px; align-items:center;'>
                                <div style='width:36px; height:36px; background:#fff; border:1px solid {$border_color}; border-radius:8px; display:flex; align-items:center; justify-content:center; color:{$border_color}; font-size:1.1rem;'><i class='fas fa-store'></i></div>
                                <div>
                                    <div style='font-weight:700; color:#1e293b; font-size:0.9rem;'>{$display_shop}</div>
                                    <div style='font-size:0.8rem; color:#475569; display:flex; gap:10px;'>
                                        <span><i class='fas fa-user'></i> {$display_contact}</span>
                                        <span><i class='fas fa-phone'></i> <b style='color:#0369a1;'>{$display_phone}</b></span>
                                    </div>
                                </div>
                            </div>
                            <div style='text-align:right;'>
                                {$icon_status}
                                <div style='font-size:1rem; font-weight:800; color:{$amount_color}; margin-top:2px;'>฿" . number_format($dec['amount'], 2) . "</div>
                            </div>
                        </div>

                        <div style='padding:10px;'>
                            {$extra_info_html}

                            <table style='width:100%; border-collapse:collapse; font-size:0.8rem; color:#334155; margin-top:8px;'>
                                <thead>
                                    <tr style='background:#f1f5f9; color:#475569;'>
                                        <th style='text-align:left; padding:6px 8px;'>รายการค่าใช้จ่าย</th>
                                        <th style='text-align:center; padding:6px 8px; width:15%;'>จำนวน</th>
                                        <th style='text-align:right; padding:6px 8px; width:20%;'>ราคา</th>
                                        <th style='text-align:right; padding:6px 8px; width:20%;'>รวม</th>
                                    </tr>
                                </thead>
                                <tbody>{$table_rows}</tbody>
                            </table>
                            
                            {$note_html}
                        </div>
                    </div>";
                }
            }
            $log_html .= "</div>";

            // 3. คำนวณเงินคงเหลือ (แต่ไม่แสดงยอดรวมด้านล่างแล้ว ตามสั่ง)
            $new_total_cost = $current_total_cost - $total_rejected_amount;
            if ($new_total_cost < 0)
                $new_total_cost = 0;

            // 4. สร้าง Main Log Container (เอาส่วนสรุปยอดล่างสุดออก)
            $main_msg = "
            <div style='background:#fff; border:1px solid #cbd5e1; border-left:5px solid #f97316; padding:15px; border-radius:10px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);'>
                <div style='margin-bottom:10px; border-bottom:1px dashed #e2e8f0; padding-bottom:8px;'>
                    <b style='color:#ea580c; font-size:1rem;'><i class='fas fa-file-invoice-dollar'></i> ผลการพิจารณาค่าใช้จ่าย</b>
                    <div style='font-size:0.8rem; color:#64748b;'>ดำเนินการโดย: <b>$user_name</b></div>
                </div>
                $log_html
            </div>";

            $logs[] = ['at' => date('d/m/Y H:i'), 'by' => $user_name, 'msg' => $main_msg];

            // 5. เช็คสถานะ Pending
            $any_pending = false;
            foreach ($data_json['details']['office_log'] as $ol) {
                if ($ol['status'] === 'back_from_shop' && !isset($ol['approved'])) {
                    $any_pending = true;
                    break;
                }
            }
            $new_cost_status = $any_pending ? 'pending' : 'approved';

            // 6. บันทึก
            $new_json = json_encode($data_json, JSON_UNESCAPED_UNICODE);
            $new_logs = json_encode($logs, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("UPDATE service_requests SET additional_cost = ?, cost_status = ?, received_item_list = ?, progress_logs = ? WHERE id = ?");
            $stmt->bind_param("dsssi", $new_total_cost, $new_cost_status, $new_json, $new_logs, $req_id);

            if ($stmt->execute())
                echo json_encode(['status' => 'success']);
            else
                throw new Exception($stmt->error);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ==========================================================================
//  PART 2: FILTER & SEARCH LOGIC (GET REQUESTS)
// ==========================================================================

// 1. รับค่าจาก URL (เพิ่ม urgency เข้ามา)
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status_filter = $_GET['status'] ?? '';
$urgency_filter = $_GET['urgency'] ?? ''; // [เพิ่มใหม่] รับค่าความเร่งด่วนจากการ์ด
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$return_status = $_GET['return_status'] ?? ''; // สถานะการรับของกลับ
$job_type_filter = $_GET['job_type'] ?? '';    // ประเภทงาน
$receiver_filter = $_GET['receiver'] ?? ''; // กรองผู้รับเรื่อง
$tech_filter = $_GET['technician'] ?? '';   // กรองช่าง/ผู้รับนัดหมาย
$cost_filter = $_GET['cost_filter'] ?? '';
$where_sql = " WHERE 1=1 ";
$params = [];
$types = "";

// --- (ของเดิม) กรองวันที่ ---
if (!empty($start_date) && !empty($end_date)) {
    $where_sql .= " AND (DATE(sr.request_date) BETWEEN ? AND ?) ";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

// --- (ของเดิม) ค้นหา ---
if (!empty($search_keyword)) {
    $where_sql .= " AND (
        sr.site_id LIKE ? OR 
        pc.project_name LIKE ? OR 
        c.customer_name LIKE ? OR 
        sr.reporter_name LIKE ? OR
        sr.manual_site_code LIKE ? OR       -- เพิ่มค้นหา Manual Site
        sr.manual_project_name LIKE ? OR    -- เพิ่มค้นหา Manual Project
        sr.manual_customer_name LIKE ?      -- เพิ่มค้นหา Manual Customer
    ) ";
    $like_term = "%" . $search_keyword . "%";
    // ใส่ตัวแปรให้ครบ 7 ตัวตามเครื่องหมาย ?
    array_push($params, $like_term, $like_term, $like_term, $like_term, $like_term, $like_term, $like_term);
    $types .= "sssssss";
}

// --- [ปรับปรุง] กรองสถานะ (Status) ---
if (!empty($status_filter)) {
    if ($status_filter == 'pending_group') {
        $where_sql .= " AND (sr.status = 'pending' OR sr.status = 'in_progress') ";
    } else {
        $where_sql .= " AND sr.status = ? ";
        $params[] = $status_filter;
        $types .= "s";
    }
}

// --- [เพิ่มใหม่] กรองความเร่งด่วน (Urgency) ---
if (!empty($urgency_filter)) {
    // แก้ไข: ใช้ชื่อให้ตรงกับฐานข้อมูล (normal, quick, urgent)
    $where_sql .= " AND sr.urgency = ? ";
    $params[] = $urgency_filter;
    $types .= "s";
}
if (!empty($return_status)) {
    if ($return_status == 'received') {
        // รับแล้ว = มีชื่อผู้รับ
        $where_sql .= " AND sr.received_by IS NOT NULL AND sr.received_by != '' ";
    } elseif ($return_status == 'not_received') {
        // ยังไม่รับ = ไม่มีชื่อผู้รับ
        $where_sql .= " AND (sr.received_by IS NULL OR sr.received_by = '') ";
    }
}

// --- [แก้ไข] กรองประเภทงาน (Job Type) ---
if (!empty($job_type_filter)) {
    // ใช้ LIKE %...% เพื่อให้หาเจอแม้จะอยู่ในรายการที่มีหลายประเภท
    $where_sql .= " AND (sr.job_type LIKE ? OR sr.project_item_name LIKE ?) ";

    $search_val = "%" . $job_type_filter . "%";
    $params[] = $search_val;
    $params[] = $search_val;
    $types .= "ss";
}
if (!empty($receiver_filter)) {
    $where_sql .= " AND sr.receiver_by = ? ";
    $params[] = $receiver_filter;
    $types .= "s";
}

// [เพิ่มใหม่] Logic กรองช่าง/ผู้รับนัดหมาย
if (!empty($tech_filter)) {
    $where_sql .= " AND sr.technician_name = ? ";
    $params[] = $tech_filter;
    $types .= "s";
}
// [เพิ่มใหม่] กรองสถานะค่าใช้จ่าย (สำหรับกดที่การ์ด)
if (!empty($cost_filter)) {
    $where_sql .= " AND sr.cost_status = ? ";
    $params[] = $cost_filter;
    $types .= "s";
}
// ==========================================================================
//  PART 3: DATA FETCHING
// ==========================================================================
$now = date('Y-m-d H:i:s');

// 1. ล่าช้า (Overdue)
$sql_late = "SELECT COUNT(*) as c FROM service_requests sr WHERE sr.status != 'completed' AND sr.expected_finish_date < '$now'";
$cnt_late = $conn->query($sql_late)->fetch_assoc()['c'];

// 2. เฝ้าระวัง (Warning) : เหลือเวลา 0 - 24 ชม.
$sql_warn = "SELECT COUNT(*) as c FROM service_requests sr WHERE sr.status != 'completed' AND sr.expected_finish_date >= '$now' AND sr.expected_finish_date <= DATE_ADD('$now', INTERVAL 1 DAY)";
$cnt_warn = $conn->query($sql_warn)->fetch_assoc()['c'];

// 3. ปกติ (Normal) : เหลือเวลามากกว่า 24 ชม.
$sql_norm = "SELECT COUNT(*) as c FROM service_requests sr WHERE sr.status != 'completed' AND sr.expected_finish_date > DATE_ADD('$now', INTERVAL 1 DAY)";
$cnt_norm = $conn->query($sql_norm)->fetch_assoc()['c'];

// รับค่า Filter จากการกดปุ่ม (ถ้ามี)
$sla_filter = $_GET['sla'] ?? '';
if ($sla_filter == 'overdue') {
    $where_sql .= " AND (sr.status != 'completed' AND sr.expected_finish_date < '$now')";
} elseif ($sla_filter == 'warning') {
    $where_sql .= " AND (sr.status != 'completed' AND sr.expected_finish_date >= '$now' AND sr.expected_finish_date <= DATE_ADD('$now', INTERVAL 1 DAY))";
} elseif ($sla_filter == 'normal') {
    $where_sql .= " AND (sr.status != 'completed' AND sr.expected_finish_date > DATE_ADD('$now', INTERVAL 1 DAY))";
}
// 3.1 Fetch Statistics
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
$sql_stat = "SELECT status, COUNT(*) as count FROM service_requests sr 
             LEFT JOIN project_contracts pc ON sr.site_id = pc.site_id
             LEFT JOIN customers c ON pc.customer_id = c.customer_id
             $where_sql GROUP BY status";

$stmt_stat = $conn->prepare($sql_stat);
if (!empty($types)) {
    $stmt_stat->bind_param($types, ...$params);
}
$stmt_stat->execute();
$res_stat = $stmt_stat->get_result();

while ($row = $res_stat->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}
$display_pending = ($stats['pending'] ?? 0) + ($stats['in_progress'] ?? 0);

// 3.2 Fetch Main List (ตรวจสอบส่วนนี้)
$sql_list = "SELECT sr.*, pc.project_name, c.customer_name, MAX(rt.rating) as satisfaction_score
             FROM service_requests sr
             LEFT JOIN project_contracts pc ON sr.site_id = pc.site_id
             LEFT JOIN customers c ON pc.customer_id = c.customer_id
             LEFT JOIN service_ratings rt ON sr.id = rt.req_id
             $where_sql 
             GROUP BY sr.id  -- 🔥 [เพิ่มบรรทัดนี้!] รวบข้อมูลตารางหลักไม่ให้เบิ้ล
             ORDER BY sr.request_date DESC";

$stmt = $conn->prepare($sql_list);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res_list = $stmt->get_result();

// 3.3 Helper Maps
$urgency_map = [
    'normal' => ['label' => 'ปกติ', 'style' => 'background:#ecfdf5; color:#059669; border:1px solid #a7f3d0;'],
    'urgent' => ['label' => 'ด่วน', 'style' => 'background:#fffbeb; color:#d97706; border:1px solid #fcd34d;'],
    'critical' => ['label' => 'ด่วนมาก', 'style' => 'background:#fef2f2; color:#dc2626; border:1px solid #fecaca; font-weight:bold;']
];

$job_type_map = [
    'computer' => 'คอมพิวเตอร์',
    'durable' => 'ครุภัณฑ์',
    'medical' => 'อุปกรณ์การแพทย์',
    'other' => 'อื่นๆ'
];
// [เพิ่มใหม่] 3.4 ดึงรายชื่อพนักงาน (Users) สำหรับ Dropdown
$receivers = [];
$sql_rec = "SELECT DISTINCT receiver_by FROM service_requests WHERE receiver_by IS NOT NULL AND receiver_by != '' ORDER BY receiver_by ASC";
$res_rec = $conn->query($sql_rec);
if ($res_rec) {
    while ($r = $res_rec->fetch_assoc()) {
        $receivers[] = $r['receiver_by'];
    }
}

// 2. ดึงช่าง/ผู้รับนัดหมาย (Technician)
$technicians = [];
$sql_tech = "SELECT DISTINCT technician_name FROM service_requests WHERE technician_name IS NOT NULL AND technician_name != '' ORDER BY technician_name ASC";
$res_tech = $conn->query($sql_tech);
if ($res_tech) {
    while ($t = $res_tech->fetch_assoc()) {
        $technicians[] = $t['technician_name'];
    }
}
// 3.5 ดึงยอดสรุป Dashboard (การ์ดด้านบน) 
$sql_dash = "SELECT 
    COUNT(*) as total,
    -- แยกตามสถานะ
    SUM(CASE WHEN sr.status = 'pending' THEN 1 ELSE 0 END) as s_pending,        
    SUM(CASE WHEN sr.status = 'in_progress' THEN 1 ELSE 0 END) as s_doing,      
    SUM(CASE WHEN sr.status = 'completed' THEN 1 ELSE 0 END) as s_done,        
    -- แยกตามความเร่งด่วน
    SUM(CASE WHEN sr.urgency = 'normal' THEN 1 ELSE 0 END) as u_normal,        
    SUM(CASE WHEN sr.urgency = 'urgent' THEN 1 ELSE 0 END) as u_quick,
    SUM(CASE WHEN sr.urgency = 'critical' THEN 1 ELSE 0 END) as u_urgent,
    -- แยกตามการรับของ
    SUM(CASE WHEN sr.received_by IS NULL OR sr.received_by = '' THEN 1 ELSE 0 END) as r_not_received,
    SUM(CASE WHEN sr.received_by IS NOT NULL AND sr.received_by != '' THEN 1 ELSE 0 END) as r_received
FROM service_requests sr
LEFT JOIN project_contracts pc ON sr.site_id = pc.site_id
LEFT JOIN customers c ON pc.customer_id = c.customer_id";
// ลบ $where_sql ออกจากบรรทัดบน เพื่อให้ยอดสรุปแสดงผลครบทุกหมวดเสมอ

$stmt_dash = $conn->prepare($sql_dash);
// ไม่ต้องใช้ bind_param เพราะเราต้องการยอดรวมทั้งหมดคงที่ไว้เป็นเมนู
$stmt_dash->execute();
$dash = $stmt_dash->get_result()->fetch_assoc();

// 3.6 ดึงข้อมูลประเภทงาน (Job Type) สำหรับการ์ดเมนู
$dynamic_job_counts = [];

// ✅ แก้ไข: ดึงจากตารางตรงๆ ไม่ใช้ $where_sql เพื่อให้เมนูปุ่มกดไม่หายไป
$sql_json_all = "SELECT project_item_name, job_type, job_type_other FROM service_requests";
$res_json_all = $conn->query($sql_json_all);

while ($row_json = $res_json_all->fetch_assoc()) {
    $found_types = [];

    // 1. เช็คจากคอลัมน์ job_type (ถ้ามีข้อมูล)
    if (!empty($row_json['job_type'])) {
        // รองรับกรณีเก็บหลายค่าแบบคั่นด้วย comma เช่น "อุปกรณ์ IT, ครุภัณฑ์"
        $split_types = explode(',', $row_json['job_type']);
        foreach ($split_types as $st) {
            $val = trim($st);
            if (!empty($val))
                $found_types[] = $val;
        }
    }

    // 2. เช็คจาก JSON (เผื่อเป็นงานเก่า)
    $items = json_decode($row_json['project_item_name'] ?? '[]', true);
    if (is_array($items)) {
        foreach ($items as $item) {
            if (!empty($item['job_type']))
                $found_types[] = $item['job_type'];
        }
    }

    // นับยอด (เอาค่าซ้ำออกในแต่ละแถวก่อนนับ)
    foreach (array_unique($found_types) as $t_key) {
        $dynamic_job_counts[$t_key] = ($dynamic_job_counts[$t_key] ?? 0) + 1;
    }
}
arsort($dynamic_job_counts); // เรียงจากมากไปน้อย
// [เพิ่มใหม่] 3.7 ดึงรายชื่อพนักงาน "ทั้งหมด" (จากตาราง users)
$all_employees = [];
$sql_all_users = "SELECT fullname FROM users ORDER BY fullname ASC";
$res_all_users = $conn->query($sql_all_users);

if ($res_all_users) {
    while ($emp = $res_all_users->fetch_assoc()) {
        $all_employees[] = $emp['fullname'];
    }
}

$sql_summary = "SELECT 
    SUM(CASE WHEN cost_status = 'approved' THEN additional_cost ELSE 0 END) as total_paid,
    SUM(CASE WHEN cost_status = 'pending' THEN additional_cost ELSE 0 END) as total_pending
    FROM service_requests";
$res_summary = $conn->query($sql_summary);
$sums = $res_summary->fetch_assoc();

// --- [แก้ไข] 3.8 คำนวณคะแนนจากการรวมตารางเดียว (service_requests) ---
$sql_rate_stat = "SELECT AVG(return_rating) as avg_score, COUNT(id) as total_votes 
                  FROM service_requests 
                  WHERE status = 'completed' AND return_rating > 0"; // ดึงจากฟิลด์ในตารางหลัก

$res_rate_stat = $conn->query($sql_rate_stat);
$rate_data = $res_rate_stat->fetch_assoc();

// กำหนดค่าตัวแปรให้ HTML นำไปแสดงผล
$avg_score = ($rate_data['avg_score'] > 0) ? number_format($rate_data['avg_score'], 1) : "0.0";
$total_votes = $rate_data['total_votes'] ?? 0;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title>Service Dashboard</title>

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="css/service_dashboard.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">

            <div class="dashboard-header-wrapper">
                <div class="header-content">
                    <h2 class="page-title">Service Dashboard</h2>
                    <span class="page-subtitle">
                        <i class="fas fa-chart-line"></i> ภาพรวมการแจ้งบริการและซ่อมบำรุง
                    </span>
                </div>
                <a href="ServiceRequest.php" class="btn-create-main">
                    <i class="fas fa-plus-circle"></i> <span>แจ้งงานใหม่</span>
                </a>
            </div>


            <div id="dashboard-grid" class="dashboard-grid-wrapper four-pillars">

                <div class="dashboard-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <div class="icon-3d bg-slate-soft"><i class="fas fa-layer-group text-slate"></i></div>
                            <div>
                                <div class="title-text">สถานะงาน</div>
                                <div class="subtitle-text">ภาพรวมทั้งหมด</div>
                            </div>
                        </div>
                        <a href="javascript:void(0)" onclick="filterStatus('', 'status')" class="btn-reset-pro"><i
                                class="fas fa-sync-alt"></i></a>
                    </div>

                    <div class="vertical-stack">
                        <a href="javascript:void(0)" onclick="filterStatus('', 'status')"
                            class="pro-list-item theme-slate-soft <?= (empty($status_filter)) ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-slate"><i class="fas fa-folder-open"></i></div>
                                <span class="list-label text-slate-dark">ทั้งหมด</span>
                            </div>
                            <span class="list-value text-slate"><?= number_format($dash['total']); ?></span>
                        </a>

                        <a href="javascript:void(0)" onclick="filterStatus('pending', 'status')"
                            class="pro-list-item theme-orange-soft <?= ($status_filter == 'pending') ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-orange"><i class="fas fa-clock"></i></div>
                                <span class="list-label text-orange-dark">ระหว่างดำเนินการ</span>
                            </div>
                            <span class="list-value text-orange"><?= number_format($dash['s_pending']); ?></span>
                        </a>

                        <a href="javascript:void(0)" onclick="filterStatus('in_progress', 'status')"
                            class="pro-list-item theme-blue-soft <?= ($status_filter == 'in_progress') ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-blue"><i class="fas fa-tools"></i></div>
                                <span class="list-label text-blue-dark">กำลังดำเนินการ</span>
                            </div>
                            <span class="list-value text-blue"><?= number_format($dash['s_doing']); ?></span>
                        </a>

                        <a href="javascript:void(0)" onclick="filterStatus('completed', 'status')"
                            class="pro-list-item theme-emerald-soft <?= ($status_filter == 'completed') ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-emerald"><i class="fas fa-check-circle"></i></div>
                                <span class="list-label text-emerald-dark">เสร็จสิ้น</span>
                            </div>
                            <span class="list-value text-emerald"><?= number_format($dash['s_done']); ?></span>
                        </a>
                    </div>
                </div>

                <div class="dashboard-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <div class="icon-3d bg-red-soft"><i class="fas fa-fire-alt text-red"></i></div>
                            <div>
                                <div class="title-text">ความเร่งด่วน</div>
                                <div class="subtitle-text">Priorities</div>
                            </div>
                        </div>
                        <a href="javascript:void(0)" onclick="filterStatus('', 'urgency')" class="btn-reset-pro"><i
                                class="fas fa-sync-alt"></i></a>
                    </div>

                    <div class="vertical-stack">
                        <a href="javascript:void(0)" onclick="filterStatus('critical', 'urgency')"
                            class="pro-list-item theme-red-soft <?= ($urgency_filter == 'critical') ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-red"><i class="fas fa-fire"></i></div>
                                <span class="list-label text-red-dark">ด่วนมาก</span>
                            </div>
                            <span class="list-value text-red"><?= number_format($dash['u_urgent']); ?></span>
                        </a>

                        <a href="javascript:void(0)" onclick="filterStatus('urgent', 'urgency')"
                            class="pro-list-item theme-yellow-soft <?= ($urgency_filter == 'urgent') ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-yellow"><i class="fas fa-shipping-fast"></i></div>
                                <span class="list-label text-yellow-dark">ด่วน</span>
                            </div>
                            <span class="list-value text-yellow"><?= number_format($dash['u_quick']); ?></span>
                        </a>

                        <a href="javascript:void(0)" onclick="filterStatus('normal', 'urgency')"
                            class="pro-list-item theme-cyan-soft <?= ($urgency_filter == 'normal') ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-cyan"><i class="fas fa-smile"></i></div>
                                <span class="list-label text-cyan-dark">ปกติ</span>
                            </div>
                            <span class="list-value text-cyan"><?= number_format($dash['u_normal']); ?></span>
                        </a>
                    </div>
                </div>

                <div class="dashboard-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <div class="icon-3d bg-pink-soft"><i class="fas fa-box-open text-pink"></i></div>
                            <div>
                                <div class="title-text">การรับของ</div>
                                <div class="subtitle-text">สถานะคืน</div>
                            </div>
                        </div>
                        <a href="javascript:void(0)" onclick="filterStatus('', 'return_status')"
                            class="btn-reset-pro"><i class="fas fa-sync-alt"></i></a>
                    </div>

                    <div class="vertical-stack">
                        <?php
                        ?>
                        <a href="javascript:void(0)" onclick="filterStatus('not_received', 'return_status')"
                            class="pro-list-item theme-pink-soft <?= ($return_status == 'not_received') ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-pink"><i class="fas fa-hand-holding"></i></div>
                                <span class="list-label text-pink-dark">ยังไม่รับคืน</span>
                            </div>
                            <span class="list-value text-pink"><?= number_format($dash['r_not_received']); ?></span>
                        </a>

                        <a href="javascript:void(0)" onclick="filterStatus('received', 'return_status')"
                            class="pro-list-item theme-teal-soft <?= ($return_status == 'received') ? 'active' : '' ?>">
                            <div class="item-start">
                                <div class="list-icon bg-gradient-teal"><i class="fas fa-check-double"></i></div>
                                <span class="list-label text-teal-dark">รับคืนแล้ว</span>
                            </div>
                            <span class="list-value text-teal"><?= number_format($dash['r_received']); ?></span>
                        </a>
                    </div>
                </div>

                <div class="dashboard-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <div class="icon-3d bg-indigo-soft"><i class="fas fa-briefcase text-indigo"></i></div>
                            <div>
                                <div class="title-text">ประเภทงาน</div>
                                <div class="subtitle-text">หมวดหมู่</div>
                            </div>
                        </div>
                        <a href="javascript:void(0)" onclick="filterStatus('', 'job_type')" class="btn-reset-pro"><i
                                class="fas fa-sync-alt"></i></a>
                    </div>

                    <div class="job-list-container">
                        <?php
                        $i = 0;
                        // ชุดสีแบบไล่ระดับ (Gradient) สำหรับป้ายที่เพิ่มมาใหม่
                        $auto_colors = ['bg-gradient-indigo', 'bg-gradient-lime', 'bg-gradient-cyan', 'bg-gradient-orange', 'bg-gradient-purple', 'bg-gradient-pink', 'bg-gradient-teal'];

                        // วนลูปตามประเภทงานที่เจอจริงในฐานข้อมูล
                        foreach ($dynamic_job_counts as $key => $count):
                            $theme = $auto_colors[$i % count($auto_colors)];
                            $i++;

                            // ตรวจสอบค่า Active ให้แม่นยำขึ้น
                            $isActive = ($job_type_filter === (string) $key) ? 'active' : '';

                            $display_name = isset($job_type_map[$key]) ? $job_type_map[$key] : $key;
                            if ($key == 'other')
                                $display_name = 'อื่นๆ';
                            ?>
                            <a href="javascript:void(0)" onclick="filterStatus('<?= addslashes($key); ?>', 'job_type')"
                                class="job-pill vivid-pill <?= $theme ?> <?= $isActive ?>">
                                <span class="job-name text-white"><?= htmlspecialchars($display_name); ?></span>
                                <span class="job-badge-glass"><?= number_format($count); ?></span>
                            </a>
                        <?php endforeach; ?>

                        <?php if (empty($dynamic_job_counts)): ?>
                            <div style="text-align:center; color:#cbd5e1; font-size:0.8rem; width:100%;">
                                ไม่มีข้อมูลประเภทงาน</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="cost-summary-container mb-4">
                <?php
                // --- เริ่มต้นการคำนวณยอดสรุปแบบใหม่ (แงะไส้บิลรายใบจาก JSON) ---
                $total_paid = 0;
                $total_pending = 0;

                // 1. ดึงข้อมูลใบงานทั้งหมดที่มีค่าใช้จ่าย (มากกว่า 0) มาตรวจสอบ
                $sql_all_costs = "SELECT received_item_list FROM service_requests WHERE additional_cost > 0";
                $res_costs = $conn->query($sql_all_costs);

                if ($res_costs) {
                    while ($cost_row = $res_costs->fetch_assoc()) {
                        // 2. แงะ JSON ออกมาดูประวัติบิลรายชิ้น
                        $rec_data = json_decode($cost_row['received_item_list'] ?? '{}', true);
                        $office_logs = $rec_data['details']['office_log'] ?? [];

                        foreach ($office_logs as $log) {
                            // 3. ตรวจสอบว่าเป็นบิลที่ส่งมาจากร้านซ่อม (status: back_from_shop)
                            if (($log['status'] ?? '') === 'back_from_shop') {
                                $bill_amount = floatval($log['total_cost'] ?? 0);

                                // 4. แยกยอดเงินตามสถานะอนุมัติของบิลนั้นๆ
                                if (($log['approved'] ?? null) === true) {
                                    $total_paid += $bill_amount;
                                } else if (($log['approved'] ?? null) !== 'rejected') {
                                    // บิลที่ยังไม่กดอนุมัติ (false/null) และไม่ได้ถูกปฏิเสธ ให้ถือว่า "รออนุมัติ"
                                    $total_pending += $bill_amount;
                                }
                            }
                        }
                    }
                }

                // 5. เตรียมตัวแปร $sums ให้ HTML ด้านล่างเอาไปแสดงผลเหมือนเดิม
                $sums = [
                    'total_paid' => $total_paid,
                    'total_pending' => $total_pending
                ];

                // รับค่าตัวกรองปัจจุบันมาเช็ค active
                $current_cost_filter = $_GET['cost_filter'] ?? '';
                ?>

                <div class="cost-summary-grid">
                    <div class="cost-panel approved <?= ($current_cost_filter == 'approved') ? 'active' : '' ?>"
                        onclick="filterStatus('approved', 'cost_filter')" style="cursor: pointer; position: relative;">
                        <div class="icon-3d bg-emerald-soft"><i class="fas fa-check-double text-emerald"></i></div>
                        <div class="cost-data">
                            <div class="cost-label">ค่าใช้จ่ายที่อนุมัติแล้ว</div>
                            <div class="cost-value text-emerald">฿ <?= number_format($sums['total_paid'], 2) ?></div>
                        </div>
                        <?php if ($current_cost_filter == 'approved'): ?>
                            <div class="active-dot bg-emerald"></div>
                        <?php endif; ?>
                    </div>

                    <div class="cost-panel pending <?= ($current_cost_filter == 'pending') ? 'active' : '' ?>"
                        onclick="filterStatus('pending', 'cost_filter')" style="cursor: pointer; position: relative;">
                        <div class="icon-3d bg-orange-soft"><i class="fas fa-hand-holding-usd text-orange"></i></div>
                        <div class="cost-data">
                            <div class="cost-label">ยอดที่รอการอนุมัติ</div>
                            <div class="cost-value text-orange">฿ <?= number_format($sums['total_pending'], 2) ?></div>
                        </div>
                        <?php if ($current_cost_filter == 'pending'): ?>
                            <div class="active-dot bg-orange"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="search-container-card mt-4">
                <form id="filterForm" class="search-form-grid" onsubmit="return false;">
                    <input type="hidden" name="sla" id="sla_input" value="<?php echo htmlspecialchars($sla_filter); ?>">
                    <input type="hidden" name="cost_filter" id="cost_filter_input"
                        value="<?php echo htmlspecialchars($cost_filter); ?>">

                    <div class="form-group full-width">
                        <label for="search_input">ค้นหาข้อมูล</label>
                        <div class="input-box">
                            <input type="text" id="search_input" name="search" class="modern-input"
                                placeholder="ระบุ Site ID / ชื่อโครงการ / ชื่อลูกค้า..."
                                value="<?php echo htmlspecialchars($search_keyword); ?>" onchange="updateData()"> <i
                                class="fas fa-search input-icon-right"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>ผู้รับเรื่อง</label>
                        <div class="input-box">
                            <select name="receiver" class="modern-input cursor-pointer" onchange="updateData()">
                                <option value="">-- ทั้งหมด --</option>
                                <?php foreach ($receivers as $name): ?>
                                    <option value="<?= htmlspecialchars($name) ?>" <?= ($receiver_filter == $name) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-user-edit input-icon-right"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>ช่างผู้รับผิดชอบ</label>
                        <div class="input-box">
                            <select name="technician" class="modern-input cursor-pointer" onchange="updateData()">
                                <option value="">-- ทั้งหมด --</option>
                                <?php foreach ($technicians as $name): ?>
                                    <option value="<?= htmlspecialchars($name) ?>" <?= ($tech_filter == $name) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-tools input-icon-right"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>ตั้งแต่วันที่</label>
                        <div class="input-box">
                            <input type="text" name="start_date" class="modern-input date-picker-alt"
                                placeholder="วว/ดด/ปปปป" value="<?php echo $start_date; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>ถึงวันที่</label>
                        <div class="input-box">
                            <input type="text" name="end_date" class="modern-input date-picker-alt"
                                placeholder="วว/ดด/ปปปป" value="<?php echo $end_date; ?>">
                        </div>
                    </div>

                    <input type="hidden" name="status" id="status_input" value="<?php echo $status_filter; ?>">
                    <input type="hidden" name="urgency" id="urgency_input" value="<?php echo $urgency_filter; ?>">
                    <input type="hidden" name="return_status" id="return_input" value="<?php echo $return_status; ?>">
                    <input type="hidden" name="job_type" id="job_type_input" value="<?php echo $job_type_filter; ?>">

                    <div class="form-group action-group">
                        <label>&nbsp;</label>
                        <div class="button-group">
                            <button type="button" class="btn-search-solid" onclick="updateData()">
                                <i class="fas fa-filter"></i> ค้นหา
                            </button>

                            <a href="service_dashboard.php" class="btn-clear-solid">
                                <i class="fas fa-redo"></i> รีเซ็ต
                            </a>
                        </div>
                    </div>

                </form>
            </div>

        </div>

        <div class="sla-filter-buttons" style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">

            <button type="button" onclick="filterSLA('')"
                class="btn-sla-filter <?php echo ($sla_filter == '') ? 'active' : ''; ?>"
                style="background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1;">
                ทั้งหมด
            </button>

            <button type="button" onclick="filterSLA('normal')"
                class="btn-sla-filter <?php echo ($sla_filter == 'normal') ? 'active' : ''; ?>"
                style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">
                <i class="fas fa-clock"></i> ปกติ
                <span class="sla-badge bg-blue"><?php echo number_format($cnt_norm); ?></span>
            </button>

            <button type="button" onclick="filterSLA('warning')"
                class="btn-sla-filter <?php echo ($sla_filter == 'warning') ? 'active' : ''; ?>"
                style="background:#fffbeb; color:#d97706; border:1px solid #fcd34d;">
                <i class="fas fa-exclamation-circle"></i> ใกล้ถึงกำหนด
                <span class="sla-badge bg-orange"><?php echo number_format($cnt_warn); ?></span>
            </button>

            <button type="button" onclick="filterSLA('overdue')"
                class="btn-sla-filter <?php echo ($sla_filter == 'overdue') ? 'active' : ''; ?>"
                style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca;">
                <i class="fas fa-times-circle"></i> ล่าช้า
                <span class="sla-badge bg-red"><?php echo number_format($cnt_late); ?></span>
            </button>

        </div>

        <div id="data-table" class="recent-table-card">
            <div
                style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; margin: 15px 0; padding: 15px 20px; background: #ffffff; border: 1px solid #e2e8f0; border-left: 5px solid #8b5cf6; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.03);">

                <div style="display: flex; align-items: center;">
                    <div
                        style="width: 45px; height: 45px; background: #f3e8ff; color: #7c3aed; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 15px;">
                        <i class="fas fa-star"></i>
                    </div>

                    <div>
                        <div
                            style="font-size: 0.85rem; color: #6b7280; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1;">
                            ความพึงพอใจ (SATISFACTION)
                        </div>
                        <div style="display: flex; align-items: center; margin-top: 5px;">

                            <span id="avg_rating_text"
                                style="background: #7c3aed; color: #fff; font-size: 0.9rem; font-weight: 700; padding: 2px 10px; border-radius: 20px; margin-right: 10px;">
                                0.0
                            </span>

                            <div id="star_container" style="color: #cbd5e1; font-size: 0.95rem;">
                                <i class="fas fa-star" style="color:#e2e8f0; margin-right: 2px;"></i>
                                <i class="fas fa-star" style="color:#e2e8f0; margin-right: 2px;"></i>
                                <i class="fas fa-star" style="color:#e2e8f0; margin-right: 2px;"></i>
                                <i class="fas fa-star" style="color:#e2e8f0; margin-right: 2px;"></i>
                                <i class="fas fa-star" style="color:#e2e8f0; margin-right: 2px;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; margin-top: 5px;">
                    <div class="hidden-xs"
                        style="text-align: right; margin-right: 15px; border-right: 1px solid #e5e7eb; padding-right: 15px;">
                        <div style="font-size: 0.75rem; color: #9ca3af;">จากทั้งหมด</div>
                        <div style="font-weight: 700; color: #374151; font-size: 1rem; line-height: 1;">

                            <span id="total_rating_text">0</span>
                            <span style="font-weight: 400; font-size: 0.8rem;">รายการ</span>

                        </div>
                    </div>

                    <button onclick="showRatingHistory()"
                        style="background: #fff; border: 1px solid #ddd6fe; color: #7c3aed; border-radius: 50px; padding: 6px 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: 0.2s;">
                        <i class="fas fa-history"></i> <span style="margin-left:5px;">ดูประวัติ</span>
                    </button>
                </div>

            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="8%">หน้างาน</th>
                            <th width="10%">ผู้รับเรื่อง / วันที่แจ้ง</th>
                            <th width="12%">ผู้แจ้ง / ติดต่อ</th>
                            <th width="15%">ลูกค้า</th>
                            <th width="8%" class="text-center">ความเร่งด่วน</th>
                            <th width="12%" class="text-center">กำหนดเสร็จ (SLA)</th>
                            <th width="10%" class="text-center">สถานะ</th>
                            <th width="8%" class="text-center">ผู้รับของกลับ</th>
                            <th width="5%" class="text-center">สินค้า</th>
                            <th width="8%" class="text-center">ประเภทงาน</th>
                            <th width="8%" class="text-center">ผู้รับผิดชอบ</th>
                            <th style="width: 140px;">ค่าใช้จ่ายเพิ่มเติม</th>
                            <th width="5%" class="text-center">รายละเอียด</th>
                            <th width="5%" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_list->num_rows > 0): ?>
                            <?php while ($row = $res_list->fetch_assoc()): ?>
                                <?php
                                // 🟢 Logic การแสดงผล (ปรับตามสั่ง)
                        
                                // 1. เลขหน้างาน (Site ID หรือ Manual Code)
                                $show_site_id = ($row['site_id'] > 0) ? $row['site_id'] : ($row['manual_site_code'] ?? '-');

                                // 2. ชื่อลูกค้า (ถ้าไม่มีจาก Join ให้เอาจาก Manual) *ไม่รวมจังหวัด*
                                $show_customer = $row['customer_name'];
                                if (empty($show_customer)) {
                                    $show_customer = $row['manual_customer_name'] ?? '-';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <span style="font-weight: 700; color: var(--accent-start); font-size:1rem;">
                                            <?php echo htmlspecialchars($show_site_id); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div style="font-weight:600; color:var(--primary); white-space:nowrap;">
                                            <i class="fas fa-user-shield" style="font-size:0.75rem; color:#94a3b8;"></i>
                                            <?php echo htmlspecialchars($row['receiver_by']); ?>
                                        </div>
                                        <div style="font-size:0.75rem; color:#64748b; margin-top:2px; white-space:nowrap;">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('d/m/y H:i', strtotime($row['request_date'])); ?>
                                        </div>
                                        <?php if (!empty($row['updated_by']) && $row['updated_by'] != $row['receiver_by']): ?>
                                            <div style="font-size:0.7rem; color:#94a3b8; font-style:italic; white-space:nowrap;">
                                                <i class="fas fa-pencil-alt"></i> แก้ไข:
                                                <?= htmlspecialchars($row['updated_by']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="contact-cell">
                                        <div class="reporter-header">
                                            <div class="reporter-avatar">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <?php echo htmlspecialchars($row['reporter_name']); ?>
                                        </div>

                                        <div class="contact-list-wrapper">
                                            <?php
                                            $contacts = json_decode($row['contact_detail'] ?? '', true);

                                            if (json_last_error() === JSON_ERROR_NONE && is_array($contacts) && count($contacts) > 0) {
                                                foreach ($contacts as $c) {
                                                    $chan = htmlspecialchars($c['channel'] ?? '-');
                                                    $val = htmlspecialchars($c['detail'] ?? '-');
                                                    $ext = htmlspecialchars($c['ext'] ?? '');

                                                    // กำหนด Class ของไอคอนตามประเภท
                                                    $iconClass = 'fa-phone-alt';
                                                    $colorClass = 'icon-default'; // Default Gray
                                    
                                                    if (stripos($chan, 'line') !== false) {
                                                        $iconClass = 'fa-line';
                                                        $colorClass = 'icon-line'; // Green
                                                    } elseif (stripos($chan, 'email') !== false || stripos($chan, 'อีเมล') !== false) {
                                                        $iconClass = 'fa-envelope';
                                                        $colorClass = 'icon-email'; // Red
                                                    } elseif (stripos($chan, 'face') !== false) {
                                                        $iconClass = 'fa-facebook';
                                                        $colorClass = 'icon-fb'; // Blue
                                                    }
                                                    ?>
                                                    <div class="contact-item">
                                                        <div class="contact-icon-box <?php echo $colorClass; ?>">
                                                            <i
                                                                class="<?php echo (stripos($chan, 'line') !== false || stripos($chan, 'face') !== false) ? 'fab' : 'fas'; ?> <?php echo $iconClass; ?>"></i>
                                                        </div>

                                                        <span class="contact-value"><?php echo $val; ?></span>

                                                        <?php if (!empty($ext)): ?>
                                                            <span class="contact-ext-badge">ต่อ <?php echo $ext; ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php
                                                } // End Foreach
                                            } elseif (!empty($row['contact_detail'])) {
                                                // กรณีข้อมูลเก่า (Text ธรรมดา)
                                                echo '<div class="contact-item"><div class="contact-icon-box icon-default"><i class="fas fa-phone-alt"></i></div> <span class="contact-value">' . htmlspecialchars($row['contact_detail']) . '</span></div>';
                                            } else {
                                                // กรณีไม่มีข้อมูล
                                                echo '<span class="contact-empty">- ไม่ระบุ -</span>';
                                            }
                                            ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="text-stretch-row">
                                            <?php echo htmlspecialchars($show_customer); ?>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <?php
                                        $urg = $row['urgency'] ?? 'normal';
                                        $u_info = $urgency_map[$urg] ?? $urgency_map['normal'];
                                        ?>
                                        <span
                                            style="padding: 3px 10px; border-radius: 50px; font-size: 0.7rem; display:inline-block; <?php echo $u_info['style']; ?>">
                                            <?php echo $u_info['label']; ?>
                                        </span>
                                    </td>

                                    <td class="text-left" style="vertical-align: middle; padding: 6px;">
                                        <?php if ($row['status'] == 'completed' && !empty($row['completed_at'])): ?>

                                            <?php
                                            // คำนวณเวลาที่ใช้ไป (Time Spent)
                                            $start = strtotime($row['request_date']); // เวลาเริ่ม
                                            $end = strtotime($row['completed_at']);   // เวลาเสร็จ
                                            $duration = $end - $start; // ระยะเวลา (วินาที)
                                
                                            // แปลงเป็น วัน/ชม./นาที
                                            $d = floor($duration / (60 * 60 * 24));
                                            $h = floor(($duration % (60 * 60 * 24)) / (60 * 60));
                                            $m = floor(($duration % (60 * 60)) / 60);

                                            $time_spent_str = "";
                                            if ($d > 0)
                                                $time_spent_str .= "{$d} วัน ";
                                            if ($h > 0)
                                                $time_spent_str .= "{$h} ชม. ";
                                            $time_spent_str .= "{$m} นาที";
                                            ?>

                                            <div
                                                style="background:#ecfdf5; border:1px solid #10b981; border-radius:10px; padding:8px 12px; min-width:170px;">

                                                <div
                                                    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                                    <span style="font-size:0.75rem; color:#047857; font-weight:700;">
                                                        <i class="fas fa-check-circle"></i> เสร็จสิ้น
                                                    </span>
                                                    <span style="font-size:0.8rem; color:#047857; font-weight:600;">
                                                        <?= date('d/m/y', $end); ?>
                                                    </span>
                                                </div>

                                                <div style="border-bottom:1px dashed #6ee7b7; margin-bottom:4px; opacity:0.8;">
                                                </div>

                                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                                    <span
                                                        style="font-size:0.7rem; color:#059669; font-weight:600;">ใช้เวลาซ่อม</span>
                                                    <span style="font-size:0.85rem; font-weight:800; color:#047857;">
                                                        <?= $time_spent_str; ?>
                                                    </span>
                                                </div>
                                            </div>

                                        <?php elseif (!empty($row['expected_finish_date'])): ?>

                                            <?php
                                            $deadline = strtotime($row['expected_finish_date']);
                                            $is_overdue = ($deadline < time());
                                            $base_color = $is_overdue ? '#dc2626' : '#2563eb';
                                            ?>
                                            <div
                                                style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:8px 12px; min-width:170px; box-shadow:0 2px 4px rgba(0,0,0,0.03);">
                                                <div
                                                    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                                    <span style="font-size:0.7rem; color:#64748b; font-weight:700;">ครบกำหนด</span>
                                                    <span style="font-size:0.9rem; font-weight:800; color:<?= $base_color ?>;">
                                                        <?= date('d/m/y', $deadline); ?>
                                                    </span>
                                                </div>
                                                <div style="border-bottom:1px dashed #cbd5e1; margin-bottom:4px; opacity:0.6;">
                                                </div>
                                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                                    <span
                                                        style="font-size:0.7rem; color:#64748b; font-weight:700;">นับถอยหลัง</span>
                                                    <span class="sla-countdown-wrapper"
                                                        data-deadline="<?= $row['expected_finish_date']; ?>"
                                                        style="font-size:0.95rem; font-weight:800; color:<?= $base_color ?>;">
                                                        <i class="fas fa-spinner fa-spin" style="font-size:0.8rem;"></i>
                                                    </span>
                                                </div>
                                            </div>

                                        <?php else: ?>
                                            <span style="color:#cbd5e1;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="white-space: nowrap;">
                                        <?php if ($row['status'] == 'completed'): ?>

                                            <div style="cursor:pointer; margin-bottom: 4px;"
                                                onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <div
                                                    style="display: inline-flex; align-items: center; gap: 6px; 
                        background: #ecfdf5; border: 1px solid #10b981; color: #059669; 
                        padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                                    <i class="fas fa-check-circle"></i> เสร็จสิ้น
                                                </div>
                                            </div>

                                            <?php
                                            // ดึงคะแนนจากตารางที่ Join มา (ถ้ามี) หรือจาก JSON
                                            $rating = intval($row['satisfaction_score'] ?? 0);
                                            if ($rating == 0) {
                                                // เผื่อกรณีไม่ได้ Join ตาราง ให้ลองแกะจาก JSON
                                                $rec_data = json_decode($row['received_item_list'] ?? '{}', true);
                                                $rating = intval($rec_data['details']['customer_return']['rating'] ?? 0);
                                            }

                                            if ($rating > 0):
                                                ?>
                                                <div style="display: flex; justify-content: center; gap: 2px;"
                                                    title="คะแนน: <?php echo $rating; ?>/5">
                                                    <?php for ($i = 1; $i <= 5; $i++):
                                                        $color = ($i <= $rating) ? '#f59e0b' : '#cbd5e1';
                                                        ?>
                                                        <i class="fas fa-star"
                                                            style="color: <?php echo $color; ?>; font-size: 0.7rem; text-shadow: 0 1px 2px rgba(0,0,0,0.1);"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php endif; ?>

                                        <?php else: ?>

                                            <?php
                                            // 1. ประกาศค่าเริ่มต้นก่อน (แก้ปัญหา Undefined variable)
                                            $status_text = 'รอรับเรื่อง';
                                            $status_style = 'background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0;';
                                            $status_icon = 'fa-hourglass-start';
                                            $badge_bg = '#e2e8f0';

                                            // 2. คำนวณ Logic
                                            $logs = !empty($row['progress_logs']) ? json_decode($row['progress_logs'], true) : [];
                                            $has_update = (is_array($logs) && count($logs) > 0);

                                            if ($has_update) {
                                                $status_text = 'กำลังดำเนินการ';
                                                $status_style = 'background:#eff6ff; color:#2563eb; border:1px solid #3b82f6;';
                                                $status_icon = 'fa-tools';
                                                $badge_bg = 'rgba(37, 99, 235, 0.1)';
                                            } else {
                                                $status_text = 'ระหว่างดำเนินการ';
                                                $status_style = 'background:#fff7ed; color:#ea580c; border:1px solid #f97316;';
                                                $status_icon = 'fa-clock';
                                                $badge_bg = 'rgba(234, 88, 12, 0.1)';
                                            }
                                            ?>

                                            <button
                                                onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)"
                                                style="<?= $status_style ?> padding:6px 12px; border-radius:50px; font-size:0.75rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; margin:0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                                <i class="fas <?= $status_icon; ?>"></i> <?= $status_text; ?>
                                                <?php if ($has_update): ?>
                                                    <span
                                                        style="background:<?= $badge_bg ?>; padding:1px 6px; border-radius:10px; font-size:0.65rem; min-width:18px; text-align:center; font-weight:700;">
                                                        <?= count($logs) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </button>

                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center" style="vertical-align:middle; padding: 8px;">
                                        <?php
                                        // 1. Convert JSON data
                                        $rec_json_raw = $row['received_item_list'] ?? '{}';
                                        $rec_data = json_decode($rec_json_raw, true) ?: [];

                                        // Extract various data points
                                        $items_status = $rec_data['items_status'] ?? [];
                                        $accumulated_moved = $rec_data['accumulated_moved'] ?? [];
                                        $returned_items_list = $rec_data['details']['customer_return']['items_returned'] ?? [];
                                        $office_logs = $rec_data['details']['office_log'] ?? [];

                                        // 🔥 [เพิ่มใหม่] ดึงรายการที่กดเสร็จสิ้นหน้างานมาด้วย
                                        $finished_items_list = $rec_data['finished_items'] ?? [];

                                        // 2. Calculate ALL items (from the project request)
                                        $all_items = [];
                                        $raw_items = json_decode($row['project_item_name'] ?? '[]', true);

                                        // Logic to parse item names (Legacy String vs New JSON Array)
                                        if (!is_array($raw_items) && !empty($row['project_item_name'])) {
                                            $parts = preg_split('/[\r\n,]+/', $row['project_item_name']);
                                            foreach ($parts as $pt) {
                                                $v = trim(preg_replace('/^\d+\.\s*/', '', preg_replace('/^\[+|\]+$/', '', $pt)));
                                                if ($v)
                                                    $all_items[] = $v;
                                            }
                                        } else {
                                            foreach ($raw_items as $ri) {
                                                $p_val = is_array($ri['product'] ?? []) ? $ri['product'] : [$ri['product'] ?? ''];
                                                foreach ($p_val as $pv) {
                                                    if (!empty(trim($pv)))
                                                        $all_items[] = trim($pv);
                                                }
                                            }
                                        }
                                        // Merge with accumulated_moved to ensure we don't miss anything that was added later
                                        $all_items = array_values(array_unique(array_merge($all_items, $accumulated_moved)));

                                        // 3. Count statuses for button logic
                                        $count_at_external = 0;
                                        $count_at_office = 0;
                                        foreach ($items_status as $status) {
                                            if ($status === 'at_external')
                                                $count_at_external++;
                                            elseif (strpos($status, 'at_office') !== false || $status === 'back_from_shop')
                                                $count_at_office++;
                                        }

                                        $remaining_at_site = array_values(array_diff($all_items, $accumulated_moved));
                                        $total_items_count = count($all_items);
                                        $returned_count = count($returned_items_list); // ของเดิมเก็บไว้ไม่ลบ
                                
                                        // 🔥 [เพิ่มใหม่] คำนวณจำนวนที่เสร็จทั้งหมด (คืนลูกค้าแล้ว + เสร็จหน้างาน) เพื่อไปแสดงบนปุ่ม
                                        $total_done_items = array_unique(array_merge($returned_items_list, $finished_items_list));
                                        $total_done_count = count($total_done_items);

                                        // Check for pending approvals
                                        $pending_approval_count = 0;
                                        foreach ($office_logs as $log) {
                                            if (($log['status'] ?? '') === 'back_from_shop' && !isset($log['approved'])) {
                                                $pending_approval_count++;
                                            }
                                        }

                                        // Completion Logic
                                        $db_status_completed = ($row['status'] === 'completed');
                                        // 🔥 ปรับให้เช็คจาก $total_done_count แทน $returned_count เพื่อให้จบงานได้จริง
                                        $all_items_returned = ($total_items_count > 0 && $total_done_count >= $total_items_count);
                                        $is_truly_finished = ($db_status_completed && $all_items_returned);

                                        // 🔥 VITAL FIX: Add all_items to the data sent to JS
                                        $rec_data['all_project_items'] = $all_items;

                                        // Encode to JSON for the button
                                        $jsonStr = htmlspecialchars(json_encode($rec_data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                        ?>

                                        <?php if ($is_truly_finished): ?>
                                            <div onclick='viewReceiverDetails(<?= $jsonStr; ?>)'
                                                style="cursor:pointer; background:#f0fdf4; border:1px solid #10b981; border-radius:10px; padding:10px; text-align:center; transition: all 0.2s;"
                                                onmouseover="this.style.background='#dcfce7'; this.style.borderColor='#059669';"
                                                onmouseout="this.style.background='#f0fdf4'; this.style.borderColor='#10b981';">
                                                <div style="color:#15803d; font-weight:800; font-size:0.9rem; margin-bottom:4px;">
                                                    <i class="fas fa-check-circle"></i> เสร็จสิ้นกระบวนการ
                                                </div>
                                                <div
                                                    style="font-size:0.75rem; color:#166534; font-weight:600; display:flex; align-items:center; justify-content:center; gap:5px;">
                                                    <i class="fas fa-history"></i> ดู Timeline ทั้งหมด
                                                </div>
                                            </div>

                                        <?php else: ?>
                                            <div class="btn-group-vertical"
                                                style="width:100%; gap: 5px; display:flex; flex-direction:column;">

                                                <?php if ($pending_approval_count > 0): ?>
                                                    <button type="button" class="btn-receive btn-sm orange"
                                                        style="background: linear-gradient(135deg, #f59e0b, #d97706); border-bottom: 2px solid #b45309; color:white; width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"
                                                        onclick='approveCost(<?= $row['id'] ?>)'>
                                                        <i class="fas fa-exclamation-circle"></i> รออนุมัติ
                                                    </button>
                                                <?php endif; ?>

                                                <?php if (count($remaining_at_site) > 0): ?>
                                                    <button type="button" class="btn-receive btn-sm orange"
                                                        onclick='receiveItem(<?= $row['id']; ?>)'>
                                                        <i class="fas fa-hand-holding"></i> กดรับของ
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($count_at_external > 0): ?>
                                                    <button type="button" class="btn-receive btn-sm"
                                                        style="background: linear-gradient(135deg, #db2777, #be185d); color:white;"
                                                        onclick='receiveFromShop(<?= $row['id']; ?>, <?= $jsonStr; ?>)'>
                                                        <i class="fas fa-undo-alt"></i> รับจากร้านซ่อม
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($count_at_office > 0 || ($total_done_count < $total_items_count && $total_items_count > 0)): ?>

                                                    <?php if ($count_at_office > 0): ?>
                                                        <button type="button" class="btn-receive btn-sm blue"
                                                            onclick='confirmOfficeReceipt(<?= $row['id']; ?>, <?= $jsonStr; ?>)'>
                                                            <i class="fas fa-user-check"></i> รับช่วงต่อ
                                                        </button>
                                                    <?php endif; ?>

                                                    <button type="button" class="btn-receive btn-sm purple"
                                                        onclick='returnToCustomer(<?= $row['id']; ?>, <?= $jsonStr; ?>)'>
                                                        <i class="fas fa-shipping-fast"></i> คืนลูกค้า
                                                        <span
                                                            style="font-size:0.7rem; background:rgba(255,255,255,0.2); padding:0 5px; border-radius:10px; margin-left:5px;">
                                                            (<?= $total_done_count ?>/<?= $total_items_count ?>)
                                                        </span>
                                                    </button>
                                                <?php endif; ?>

                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="vertical-align:middle; min-width: 130px;">
                                        <?php
                                        // 1. ดึงข้อมูลรายการที่เสร็จแล้วจาก JSON
                                        $rec_data_col = json_decode($row['received_item_list'] ?? '{}', true) ?: [];
                                        $finished_items_list = $rec_data_col['finished_items'] ?? [];
                                        $count_finished = count($finished_items_list);

                                        // 2. คำนวณจำนวนรายการทั้งหมด (Logic เดียวกับส่วนอื่น)
                                        $raw_items_col = json_decode($row['project_item_name'] ?? '[]', true);
                                        if (!is_array($raw_items_col) && !empty($row['project_item_name'])) {
                                            $count_total = 0;
                                            $parts_col = preg_split('/[\r\n,]+/', $row['project_item_name']);
                                            foreach ($parts_col as $p)
                                                if (trim($p))
                                                    $count_total++;
                                        } else {
                                            $count_total = is_array($raw_items_col) ? count($raw_items_col) : 0;
                                        }

                                        // 3. 🔥 คำนวณยอดคงเหลือ
                                        $count_remaining = $count_total - $count_finished;
                                        if ($count_remaining < 0)
                                            $count_remaining = 0; // กันเลขติดลบ
                                
                                        // 4. กำหนดสี Badge (ถ้าเหลือ 0 คือซ่อมครบแล้วจะเป็นสีเขียว)
                                        $is_all_done = ($count_remaining === 0 && $count_total > 0);

                                        if ($is_all_done) {
                                            $badge_style = "background: #ecfdf5; color: #10b981; border: 1px solid #10b981;";
                                            $badge_text = "ซ่อมครบแล้ว";
                                            $icon = "fa-check-circle";
                                        } else {
                                            $badge_style = "background: #fff7ed; color: #f97316; border: 1px solid #fdba74;";
                                            $badge_text = "คงเหลือ " . $count_remaining . "/" . $count_total;
                                            $icon = "fa-tools";
                                        }
                                        ?>

                                        <button type="button" class="btn btn-sm"
                                            style="background:#fff; border:1px solid #cbd5e1; color:#1e293b; border-radius:10px; padding:8px 12px; font-weight:700; width:100%; box-shadow: 0 2px 4px rgba(0,0,0,0.05);"
                                            onclick="openRepairSummaryModal(<?= $row['id'] ?>)">
                                            <div style="font-size:0.8rem;"><i class="fas fa-edit" style="color:#3b82f6;"></i>
                                                สรุปงานซ่อม</div>
                                        </button>

                                        <div style="margin-top: 6px;">
                                            <span
                                                style="font-size: 0.7rem; font-weight: 800; padding: 2px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; <?= $badge_style ?>">
                                                <i class="fas <?= $icon ?>"></i>
                                                <?= $badge_text ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <?php
                                        // 1. แกะ JSON (พร้อมระบบกันเหนียว 2 ชั้น)
                                        $items_json = json_decode($row['project_item_name'] ?? '[]', true);
                                        if (is_string($items_json)) {
                                            $items_json = json_decode($items_json, true);
                                        }

                                        $types_found = [];

                                        // 2. วนลูปหาประเภทงาน
                                        if (is_array($items_json) && !empty($items_json)) {
                                            foreach ($items_json as $itm) {
                                                if (!empty($itm['job_type'])) {
                                                    $raw = $itm['job_type'];
                                                    // แปลงเป็นชื่อไทย
                                                    $label = $job_type_map[$raw] ?? $raw;
                                                    if ($raw == 'other')
                                                        $label = 'อื่นๆ';

                                                    if (!in_array($label, $types_found)) {
                                                        $types_found[] = $label;
                                                    }
                                                }
                                            }
                                        }

                                        // 3. ถ้าหาไม่เจอ ให้ใช้ค่าจาก Column หลัก
                                        if (empty($types_found) && !empty($row['job_type'])) {
                                            $raw = $row['job_type'];
                                            $label = $job_type_map[$raw] ?? $raw;
                                            if ($raw == 'other')
                                                $label = 'อื่นๆ';
                                            $types_found[] = $label;
                                        }

                                        // 4. แสดงผล
                                        if (!empty($types_found)) {
                                            foreach ($types_found as $t) {
                                                echo '<span class="multi-job-pill">' . htmlspecialchars($t) . '</span>';
                                            }
                                        } else {
                                            echo '<span style="color:#cbd5e1;">-</span>';
                                        }
                                        ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if (!empty($row['technician_name'])):
                                            // แยกชื่อด้วยจุลภาค (Comma)
                                            $t_list = explode(',', $row['technician_name']);
                                            foreach ($t_list as $t_name): ?>
                                                <div
                                                    style="font-size:0.75rem; color:#0f172a; font-weight:600; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:4px; padding:2px 6px; margin-bottom:3px; display:block; white-space:nowrap; text-align:left;">
                                                    <i class=" fas fa-user-cog" style="color:#64748b; font-size:0.7rem;"></i>
                                                    <?= htmlspecialchars(trim($t_name)); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color:#cbd5e1;">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center" style="vertical-align: middle; padding: 8px;">
                                        <?php
                                        // 1. ดึงข้อมูล JSON
                                        $rec_data = json_decode($row['received_item_list'] ?? '{}', true);
                                        $office_logs = $rec_data['details']['office_log'] ?? [];

                                        // 2. คำนวณยอดเงินและรายการค้าง
                                        $pending_count = 0;
                                        $pending_amount = 0;

                                        foreach ($office_logs as $log) {
                                            // เช็คว่าเป็นรายการรับของกลับจากร้าน
                                            if (($log['status'] ?? '') === 'back_from_shop') {
                                                $is_approved = ($log['approved'] ?? null) === true;
                                                $is_rejected = ($log['approved'] ?? null) === 'rejected';

                                                // ถ้ายังไม่ อนุมัติ(true) และยังไม่ ปฏิเสธ(rejected) ถือว่า "รออนุมัติ"
                                                if (!$is_approved && !$is_rejected) {
                                                    $pending_count++;
                                                    $pending_amount += floatval($log['total_cost'] ?? 0);
                                                }
                                            }
                                        }

                                        // 3. คำนวณยอดที่อนุมัติไปแล้วจริง (ยอดรวมใน DB - ยอดที่รออนุมัติ)
                                        $total_db_cost = floatval($row['additional_cost']);
                                        $approved_amount = $total_db_cost - $pending_amount;
                                        if ($approved_amount < 0)
                                            $approved_amount = 0; // กันติดลบ
                                        ?>

                                        <div style="display:flex; flex-direction:column; gap:6px;">

                                            <?php if ($pending_count > 0): ?>
                                                <button type="button" class="btn-receive btn-sm orange"
                                                    style="background: linear-gradient(135deg, #f59e0b, #d97706); border-bottom: 2px solid #b45309; color:white; width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"
                                                    onclick='approveCost(<?= $row['id'] ?>)'>
                                                    <i class="fas fa-exclamation-circle"></i> รออนุมัติ (<?= $pending_count ?>)
                                                </button>
                                                <div style="font-size: 0.7rem; color: #d97706; font-weight: 600;">
                                                    (รออนุมัติ: ฿<?= number_format($pending_amount, 2) ?>)
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($approved_amount > 0): ?>
                                                <div
                                                    style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 6px; text-align: left;">
                                                    <div
                                                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                                        <div style="color: #059669; font-weight: 700; font-size: 0.9rem;">
                                                            <i class="fas fa-check-circle"></i>
                                                            ฿<?= number_format($approved_amount, 2) ?>
                                                        </div>

                                                        <button onclick='approveCost(<?= $row['id'] ?>)'
                                                            style="border:none; background:none; color:#059669; cursor:pointer; font-size:0.8rem;"
                                                            title="ดูรายละเอียด/ประวัติ">
                                                            <i class="fas fa-search"></i>
                                                        </button>
                                                    </div>

                                                    <?php if (!empty($row['cost_approved_by'])): ?>
                                                        <div
                                                            style="font-size: 0.65rem; color: #64748b; border-top: 1px dashed #d1fae5; padding-top: 4px;">
                                                            <div><i class="fas fa-user-check"></i>
                                                                <?= htmlspecialchars($row['cost_approved_by']) ?></div>
                                                            <div><i class="far fa-clock"></i>
                                                                <?= !empty($row['cost_approved_at']) ? date('d/m/y H:i', strtotime($row['cost_approved_at'])) : '-' ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($pending_count == 0 && $approved_amount <= 0): ?>
                                                <span style="color: #cbd5e1;">-</span>
                                            <?php endif; ?>

                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <?php
                                        // 1. ดึงข้อมูล
                                        $raw_json = $row['project_item_name'];

                                        // 2. ตรวจสอบและแปลง
                                        // ถ้าเป็นค่าว่าง, NULL, หรือไม่ใช่ String ให้ตั้งเป็น '[]' (Array ว่าง)
                                        if (empty($raw_json)) {
                                            $final_json = '[]';
                                        }
                                        // ถ้ามีข้อมูล ลอง Decode ดูว่าเป็น JSON จริงไหม
                                        else {
                                            $test_decode = json_decode($raw_json, true);
                                            if (json_last_error() === JSON_ERROR_NONE) {
                                                // ถ้าเป็น JSON ที่ถูกต้องอยู่แล้ว ให้ใช้ค่าเดิม (แต่ต้อง escape single quote เพื่อไม่ให้ JS พัง)
                                                $final_json = $raw_json;
                                            } else {
                                                // ถ้าไม่ใช่ JSON (เช่น เป็น Text เก่า) ให้สร้าง JSON หลอกๆ ขึ้นมา
                                                $fake_item = [
                                                    [
                                                        'product' => ['-'],
                                                        'issue' => $raw_json, // เอา Text เดิมมาใส่ในช่องอาการ
                                                        'initial_advice' => '',
                                                        'assessment' => ''
                                                    ]
                                                ];
                                                $final_json = json_encode($fake_item, JSON_UNESCAPED_UNICODE);
                                            }
                                        }
                                        ?>

                                        <button class="btn-view-3d"
                                            onclick='viewDetails(<?php echo htmlspecialchars($final_json, ENT_QUOTES, "UTF-8"); ?>)'>
                                            <i class="fas fa-file-alt"></i>
                                            <span>เปิดดู</span>
                                        </button>
                                    </td>

                                    <td class="text-center">
                                        <div style="display:flex; justify-content:center; gap:4px; margin-bottom:5px;">

                                            <?php if (hasAction('edit_service')): ?>
                                                <a href="ServiceRequest.php?edit_id=<?php echo $row['id']; ?>" class="btn-edit"
                                                    title="แก้ไข">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (hasAction('delete_service')): ?>
                                                <button class="btn-delete" onclick="deleteItem(<?php echo $row['id']; ?>)"
                                                    title="ลบ">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>

                                        </div>

                                        <?php if ($row['status'] != 'completed'): ?>
                                            <button class="btn-finish-3d" onclick="confirmFinish(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-check-circle"></i> จบงาน
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center" style="padding:40px; color:var(--secondary);">
                                    <i class="fas fa-inbox"
                                        style="font-size: 2rem; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                                    ไม่พบข้อมูลที่ค้นหา
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const techList = <?php echo json_encode($technicians); ?>;
        const allEmployeeList = <?php echo json_encode($all_employees); ?>;

    </script>
    <script src="js/service_dashboard.js"></script>
</body>

</html>