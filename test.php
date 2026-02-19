<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'db_connect.php';

// ==========================================================================
//  PART 1: HANDLE ACTIONS (POST REQUESTS)
// ==========================================================================

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
    // --- [แก้ไข] 1.3 รับของกลับ / นำของออกจากหน้างาน (Premium 3D Log - แสดงข้อมูลครบถ้วน) ---
    if ($_POST['action'] == 'receive_item') {
        header('Content-Type: application/json');

        if (!isset($conn) || $conn->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }

        try {
            $req_id = intval($_POST['req_id']);
            $user_name = $_SESSION['fullname'] ?? 'Unknown';
            $type = $_POST['receiver_type']; // 'external' หรือ 'office'
            $remark = trim($_POST['receive_remark']);
            $selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : []; // array รายการสินค้า

            // 1. จัดการไฟล์แนบ
            $proof_file = null;
            if (isset($_FILES['receive_proof']) && $_FILES['receive_proof']['error'] == 0) {
                $upload_dir = 'uploads/proofs/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['receive_proof']['name'], PATHINFO_EXTENSION);
                $proof_file = 'rec_' . $req_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['receive_proof']['tmp_name'], $upload_dir . $proof_file);
            }

            // 2. ดึงข้อมูลเดิม
            $res_log = $conn->query("SELECT received_item_list, progress_logs FROM service_requests WHERE id = $req_id");
            $row_log = $res_log->fetch_assoc();
            $logs = json_decode($row_log['progress_logs'] ?? '[]', true) ?: [];

            // =====================================================================================
            // 🔥 เริ่มสร้าง HTML สำหรับ Log (Premium 3D Design) 🔥
            // =====================================================================================

            // กำหนดธีมสีและข้อมูลตามประเภท
            if ($type === 'external') {
                // 🟠 ธีมร้านนอก (สีส้ม)
                $theme_color = '#f59e0b';
                $theme_bg = '#fffbeb';
                $theme_border = '#fcd34d';
                $theme_gradient = 'linear-gradient(135deg, #f59e0b, #d97706)';
                $icon_class = 'fa-store';
                $title_text = 'ส่งซ่อมร้านภายนอก';
                $shadow_color = 'rgba(245, 158, 11, 0.3)';

                // รับค่าข้อมูลร้าน
                $s_name = trim($_POST['shop_name'] ?? '-');
                $s_owner = trim($_POST['shop_owner'] ?? '-');
                $s_phone = trim($_POST['shop_phone'] ?? '-');

            } else {
                // 🔵 ธีมกลับบริษัท (สีฟ้า)
                $theme_color = '#0ea5e9';
                $theme_bg = '#f0f9ff';
                $theme_border = '#7dd3fc';
                $theme_gradient = 'linear-gradient(135deg, #0ea5e9, #0284c7)';
                $icon_class = 'fa-building';
                $title_text = 'นำของกลับบริษัท';
                $shadow_color = 'rgba(14, 165, 233, 0.3)';
            }

            // CSS Keyframes (Animation)
            $css_anim = "<style>@keyframes fadeInUp { from { opacity:0; transform:translateY(15px); } to { opacity:1; transform:translateY(0); } } @keyframes pulseGlow { 0% { box-shadow: 0 0 0 0 {$shadow_color}; } 70% { box-shadow: 0 0 0 10px rgba(0,0,0,0); } 100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); } }</style>";

            // เริ่ม Container
            $progress_msg = $css_anim . "<div style='font-family:Prompt, sans-serif; position:relative;'>";

            // --- 1. Header Section ---
            $progress_msg .= "<div style='display:flex; align-items:flex-start; gap:12px; margin-bottom:15px; animation: fadeInUp 0.5s ease forwards;'>";
            $progress_msg .= "  <div style='flex-shrink:0; width:48px; height:48px; background:{$theme_gradient}; color:#fff; border-radius:14px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px -4px {$shadow_color}; animation: pulseGlow 2s infinite;'>";
            $progress_msg .= "      <i class='fas {$icon_class} fa-lg'></i>";
            $progress_msg .= "  </div>";
            $progress_msg .= "  <div>";
            $progress_msg .= "      <div style='font-weight:800; color:#1e293b; font-size:1.1rem; letter-spacing:-0.5px;'>{$title_text}</div>";
            $progress_msg .= "      <div style='font-size:0.85rem; color:#64748b; margin-top:4px;'>ดำเนินการโดย: <span style='color:{$theme_color}; font-weight:600; background:{$theme_bg}; padding:2px 8px; border-radius:4px;'>{$user_name}</span></div>";
            $progress_msg .= "  </div>";
            $progress_msg .= "</div>";

            // --- 2. Detail Box (แสดงข้อมูลร้าน หรือ ข้อมูลบริษัท) ---
            if ($type === 'external') {
                $progress_msg .= "<div style='background:#fff; border:1px solid #e2e8f0; border-left:5px solid {$theme_color}; padding:12px 15px; border-radius:8px; margin-bottom:12px; box-shadow:0 2px 4px rgba(0,0,0,0.02); animation: fadeInUp 0.5s ease forwards 0.1s; opacity:0;'>";
                $progress_msg .= "  <div style='font-weight:700; color:#92400e; margin-bottom:8px; border-bottom:1px dashed #e2e8f0; padding-bottom:5px;'><i class='fas fa-info-circle'></i> ข้อมูลร้านค้า</div>";
                $progress_msg .= "  <div style='display:grid; grid-template-columns: 1fr; gap:4px; font-size:0.9rem; color:#475569;'>";
                $progress_msg .= "      <div><i class='fas fa-store' style='color:#fcd34d; width:20px;'></i> <b>ร้าน:</b> " . htmlspecialchars($s_name) . "</div>";
                $progress_msg .= "      <div><i class='fas fa-user' style='color:#fcd34d; width:20px;'></i> <b>ผู้ติดต่อ:</b> " . htmlspecialchars($s_owner) . "</div>";
                $progress_msg .= "      <div><i class='fas fa-phone' style='color:#fcd34d; width:20px;'></i> <b>โทร:</b> <a href='tel:{$s_phone}' style='color:{$theme_color}; text-decoration:none; font-weight:600;'>{$s_phone}</a></div>";
                $progress_msg .= "  </div>";
                $progress_msg .= "</div>";
            } else {
                $progress_msg .= "<div style='background:{$theme_bg}; border:1px dashed {$theme_border}; padding:10px 15px; border-radius:8px; color:#0369a1; font-size:0.9rem; margin-bottom:12px; animation: fadeInUp 0.5s ease forwards 0.1s; opacity:0;'>";
                $progress_msg .= "  <i class='fas fa-map-marker-alt'></i> นำกลับมาตรวจสอบ/ซ่อม ที่สำนักงาน";
                $progress_msg .= "</div>";
            }

            // --- 3. Note Box (หมายเหตุ) ---
            if (!empty($remark)) {
                $progress_msg .= "<div style='background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:8px; color:#475569; font-size:0.9rem; position:relative; margin-bottom:15px; animation: fadeInUp 0.5s ease forwards 0.15s; opacity:0;'>";
                $progress_msg .= "  <i class='fas fa-comment-dots' style='color:#cbd5e1; position:absolute; top:10px; right:10px;'></i>";
                $progress_msg .= "  <b>หมายเหตุ:</b> " . htmlspecialchars($remark);
                $progress_msg .= "</div>";
            }

            // --- 4. Item List (รายการสินค้า - 1 บรรทัดต่อรายการ) ---
            if (!empty($selected_items)) {
                $progress_msg .= "<div style='margin-top:15px; padding-top:10px; border-top:1px dashed #e2e8f0; animation: fadeInUp 0.5s ease forwards 0.2s; opacity:0;'>";
                $progress_msg .= "  <div style='font-size:0.85rem; font-weight:700; color:#64748b; margin-bottom:10px;'><i class='fas fa-boxes' style='color:{$theme_color};'></i> รายการที่นำออก:</div>";
                $progress_msg .= "  <div style='display:flex; flex-direction:column; gap:8px;'>"; // Flex Column

                foreach ($selected_items as $index => $item) {
                    $delay = 0.3 + ($index * 0.1);
                    // ดีไซน์การ์ดสินค้า 1 บรรทัด
                    $progress_msg .= "<div style='background:#fff; border:1px solid #e2e8f0; border-left:4px solid {$theme_color}; padding:8px 12px; border-radius:6px; font-size:0.9rem; color:#334155; display:flex; align-items:flex-start; gap:10px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); animation: fadeInUp 0.4s ease forwards {$delay}s; opacity:0;'>";
                    $progress_msg .= "  <div style='flex-shrink:0; margin-top:2px; color:{$theme_color};'><i class='fas fa-box-open'></i></div>";
                    $progress_msg .= "  <div style='line-height:1.5; word-break:break-word;'>" . htmlspecialchars($item) . "</div>";
                    $progress_msg .= "</div>";
                }
                $progress_msg .= "  </div>";
                $progress_msg .= "</div>";
            }

            // --- 5. File Attachment Button ---
            if ($proof_file) {
                $progress_msg .= "<div style='margin-top:15px; text-align:right; animation: fadeInUp 0.5s ease forwards 0.4s; opacity:0;'>";
                $progress_msg .= "  <a href='uploads/proofs/$proof_file' target='_blank' style='display:inline-flex; align-items:center; gap:8px; background:linear-gradient(90deg, {$theme_color}, #d97706); color:#ffffff; padding:6px 16px; border-radius:50px; text-decoration:none; font-size:0.85rem; font-weight:600; box-shadow:0 3px 8px rgba(0,0,0,0.15); transition:all 0.2s;'>";
                $progress_msg .= "      <i class='fas fa-file-invoice'></i> ดูหลักฐาน/ใบส่งของ";
                $progress_msg .= "  </a>";
                $progress_msg .= "</div>";
            }

            $progress_msg .= "</div>"; // End Main Container

            // =====================================================================================

            // 3. เตรียมข้อมูล JSON (สำหรับ Timeline และ Status)
            $shop_info_arr = null;
            if ($type === 'external') {
                $shop_info_arr = [
                    'name' => $s_name,
                    'owner' => $s_owner,
                    'phone' => $s_phone
                ];
            }

            // โครงสร้างข้อมูลที่จะบันทึกใน received_item_list
            $details = [
                'type' => $type,
                'pickup_by' => $user_name,
                'pickup_at' => date('d/m/Y H:i'),
                'pickup_remark' => $remark,
                'shop_info' => $shop_info_arr,
                'file' => $proof_file,
                'office_log' => [] // สร้าง array ว่างรอไว้สำหรับ log การรับกลับ
            ];

            // รวมข้อมูลสินค้าและรายละเอียด
            $final_json = json_encode([
                'items' => $selected_items,
                'details' => $details
            ], JSON_UNESCAPED_UNICODE);

            // บันทึก Log ลง Database
            $logs[] = [
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'msg' => $progress_msg
            ];
            $new_logs_json = json_encode($logs, JSON_UNESCAPED_UNICODE);

            // 4. อัปเดต SQL
            // received_by จะถูกอัปเดตเป็นคนนำออกล่าสุด
            // received_item_list เก็บข้อมูลสถานะล่าสุด
            // progress_logs เก็บประวัติ HTML
            $sql = "UPDATE service_requests SET 
                    received_by = ?, 
                    received_at = NOW(), 
                    received_item_list = ?, 
                    progress_logs = ? 
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
                exit;
            }

            $stmt->bind_param("sssi", $user_name, $final_json, $new_logs_json, $req_id);

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
    // [เพิ่มใหม่] 1.4 อัปเดตความคืบหน้า (Update Progress)
    if ($_POST['action'] == 'update_progress') {
        $req_id = intval($_POST['req_id']);
        $update_msg = trim($_POST['update_msg']);
        $tech_name = isset($_POST['technician_name']) ? trim($_POST['technician_name']) : '';
        $user_name = $_SESSION['fullname'] ?? 'Unknown';

        // 1. ดึงข้อมูลเดิมมาดู (เพื่อเอา Log และ รายชื่อช่างปัจจุบัน)
        $stmt = $conn->prepare("SELECT progress_logs, status, technician_name FROM service_requests WHERE id = ?");
        $stmt->bind_param("i", $req_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $old_row = $res->fetch_assoc();

        $logs = !empty($old_row['progress_logs']) ? json_decode($old_row['progress_logs'], true) : [];
        $current_techs = $old_row['technician_name']; // รายชื่อเดิม (เช่น "ช่างเอ, ช่างบี")

        // 2. จัดการเรื่องรายชื่อช่าง (สะสมเพิ่ม)
        $final_tech_list = $current_techs;
        if (!empty($tech_name)) {
            if (empty($current_techs)) {
                $final_tech_list = $tech_name;
            } else {
                // แยกชื่อออกมาเป็น Array เพื่อเช็คตัวซ้ำ
                $tech_array = array_map('trim', explode(',', $current_techs));
                if (!in_array($tech_name, $tech_array)) {
                    $tech_array[] = $tech_name; // เพิ่มชื่อใหม่เข้าไป
                    $final_tech_list = implode(', ', $tech_array);
                }
            }
        }

        // 3. สร้างข้อความ Log
        $final_msg = $update_msg;
        if (!empty($tech_name)) {
            $assign_text = "<br><span style='color:#0369a1; font-weight:600; font-size:0.8rem;'><i class='fas fa-user-plus'></i> เพิ่มผู้รับผิดชอบ: {$tech_name}</span>";
            $final_msg = empty($final_msg) ? $assign_text : $final_msg . $assign_text;
        }

        if (!empty($final_msg)) {
            $logs[] = [
                'msg' => $final_msg,
                'by' => $user_name,
                'at' => date('d/m/Y H:i')
            ];
        }

        $new_status = ($old_row['status'] == 'completed') ? 'completed' : 'in_progress';

        // 4. บันทึกลงฐานข้อมูล
        $sql = "UPDATE service_requests SET progress_logs = ?, status = ?, technician_name = ? WHERE id = ?";
        $stmt_up = $conn->prepare($sql);
        $logs_json = json_encode($logs, JSON_UNESCAPED_UNICODE);
        $stmt_up->bind_param("sssi", $logs_json, $new_status, $final_tech_list, $req_id);

        echo json_encode(['status' => $stmt_up->execute() ? 'success' : 'error']);
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

            // 1. ดึงข้อมูลเดิม (ทั้งสถานะรับของ และ Log ความคืบหน้า)
            $res = $conn->query("SELECT received_item_list, progress_logs FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();

            // ตรวจสอบ JSON ป้องกันค่าว่าง
            $data = json_decode($row_data['received_item_list'] ?? '{}', true) ?: [];
            $logs = json_decode($row_data['progress_logs'] ?? '[]', true) ?: [];

            // 2. จัดการไฟล์แนบ (ถ้ามี)
            $file_name = null;
            if (isset($_FILES['office_file']) && $_FILES['office_file']['error'] == 0) {
                $upload_dir = 'uploads/service_updates/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);
                $file_name = 'off_' . $req_id . '_' . time() . '.' . pathinfo($_FILES['office_file']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($_FILES['office_file']['tmp_name'], $upload_dir . $file_name);
            }

            // 3. อัปเดตข้อมูลประวัติรับช่วงต่อ (Timeline ภายใน)
            $data['details']['office_log'][] = [
                'by' => $user_name,
                'at' => date('d/m/Y H:i'),
                'msg' => $remark,
                'items' => $office_items,
                'file' => $file_name
            ];

            // 4. 🔥 [จุดสำคัญ] เพิ่มเข้า "อัปเดตความคืบหน้า" (Main Log)
            $logs[] = [
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'msg' => "📦 [รับช่วงต่อ/ตรวจสอบสินค้า]: " . $remark,
                'file' => $file_name
            ];

            // 5. บันทึกกลับลง Database
            $new_json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $new_logs = json_encode($logs, JSON_UNESCAPED_UNICODE);

            // ✅ ตรวจสอบว่าใน SQL มีการ Update ทั้ง 2 คอลัมน์
            $sql = "UPDATE service_requests SET received_item_list = ?, progress_logs = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
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
    // --- [แก้ไข] 1.5.1 ยืนยันการรับช่วงต่อ (Premium Design + Animations) ---
    if ($_POST['action'] == 'confirm_office_receipt') {
        header('Content-Type: application/json');

        if (!isset($conn) || $conn->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }

        try {
            $req_id = intval($_POST['req_id']);
            $user_name = $_SESSION['fullname'] ?? 'Unknown';
            $remark = trim($_POST['remark']);
            $checked_items = isset($_POST['checked_items']) ? $_POST['checked_items'] : [];

            // 1. จัดการไฟล์แนบ
            $proof_file = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
                $upload_dir = 'uploads/proofs/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
                $proof_file = 'conf_' . $req_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['proof_file']['tmp_name'], $upload_dir . $proof_file);
            }

            // 2. ดึงข้อมูลเดิม
            $res = $conn->query("SELECT received_item_list, progress_logs FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();
            $data = json_decode($row_data['received_item_list'] ?? '{}', true) ?: [];
            $logs = json_decode($row_data['progress_logs'] ?? '[]', true) ?: [];

            // =====================================================================================
            // 🔥 เริ่มส่วนดีไซน์ HTML + CSS Animation (Premium 3D) 🔥
            // =====================================================================================

            // 3. จัดรูปแบบรายการสินค้า (Vertical List Cards with Cascade Animation)
            $item_html = "";
            if (!empty($checked_items)) {
                $item_html = "<div style='margin-top:15px; padding-top:15px; border-top:1px dashed #e2e8f0; animation: fadeInUp 0.6s ease forwards 0.2s; opacity:0;'>";
                $item_html .= "<div style='font-size:0.85rem; font-weight:700; color:#64748b; margin-bottom:12px; display:flex; align-items:center; gap:8px;'><i class='fas fa-clipboard-list' style='color:#3b82f6;'></i> รายการที่ตรวจสอบครบถ้วน:</div>";
                $item_html .= "<div style='display:flex; flex-direction:column; gap:10px;'>";

                foreach ($checked_items as $index => $item) {
                    // คำนวณ delay เพื่อให้เด้งขึ้นมาทีละชิ้น
                    $delay = 0.3 + ($index * 0.1);

                    $item_html .= "<div style='background:#ffffff; border-radius:10px; padding:12px 15px; font-size:0.9rem; color:#334155; display:flex; align-items:flex-start; gap:12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border:1px solid rgba(255,255,255,0.8); position:relative; overflow:hidden; animation: fadeInUp 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards {$delay}s; opacity:0;'>";
                    // แถบสีเขียวด้านซ้าย
                    $item_html .= "<div style='position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(to bottom, #10b981, #059669);'></div>";
                    // ไอคอนติ๊กถูก
                    $item_html .= "<div style='flex-shrink:0; margin-top:2px; background:#ecfdf5; color:#10b981; width:24px; height:24px;border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(16, 185, 129, 0.2);'><i class='fas fa-check' style='font-size:0.75rem;'></i></div>";
                    // ข้อความสินค้า
                    $item_html .= "<div style='line-height:1.6; word-break:break-word; flex-grow:1; font-weight:500;'>" . htmlspecialchars($item) . "</div>";
                    $item_html .= "</div>";
                }
                $item_html .= "</div></div>";
            }

            // 4. สร้าง Main Log Container
            // 🔥 ใส่ CSS Keyframes ไว้ใน style block เล็กๆ เพื่อให้ทำงานได้ใน Log
            $progress_msg = "
            <style>
                @keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
                @keyframes pulseGlow { 0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.5); } 70% { box-shadow: 0 0 0 15px rgba(59, 130, 246, 0); } 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); } }
            </style>
            <div style='font-family:Prompt, sans-serif; position:relative;'>";

            // Header Area with Pulse Animation
            $progress_msg .= "<div style='display:flex; align-items:flex-start; gap:15px; margin-bottom:15px; animation: fadeInUp 0.6s ease forwards;'>";
            $progress_msg .= "  <div style='flex-shrink:0; width:48px; height:48px; background:linear-gradient(135deg, #3b82f6, #1d4ed8); color:#fff; border-radius:14px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px -4px rgba(37, 99, 235, 0.5); animation: pulseGlow 2s infinite;'>";
            $progress_msg .= "      <i class='fas fa-user-shield fa-lg'></i>";
            $progress_msg .= "  </div>";
            $progress_msg .= "  <div>";
            $progress_msg .= "      <div style='font-weight:800; color:#1e3a8a; font-size:1.1rem; letter-spacing:-0.5px;'>รับช่วงต่อ / ตรวจสอบสินค้า</div>";
            $progress_msg .= "      <div style='font-size:0.85rem; color:#64748b; margin-top:4px; display:flex; align-items:center; gap:5px;'><i class='fas fa-user-tag' style='color:#94a3b8;'></i> ดำเนินการโดย <span style='color:#2563eb; font-weight:600; background:#eff6ff; padding:2px 8px; border-radius:4px;'>{$user_name}</span></div>";
            $progress_msg .= "  </div>";
            $progress_msg .= "</div>";

            // Note Box (Glassmorphism style)
            if (!empty($remark)) {
                $progress_msg .= "<div style='background:rgba(241, 245, 249, 0.8); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.5); border-left:5px solid #64748b; padding:12px 16px; border-radius:8px; color:#475569; font-size:0.9rem; margin-bottom:10px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.02); animation: fadeInUp 0.6s ease forwards 0.1s; opacity:0;'>";
                $progress_msg .= "  <div style='display:flex; gap:8px;'><i class='fas fa-comment-dots' style='color:#94a3b8; margin-top:4px;'></i> <div><b style='color:#334155;'>หมายเหตุ:</b> " . htmlspecialchars($remark) . "</div></div>";
                $progress_msg .= "</div>";
            } else {
                $progress_msg .= "<div style='font-size:0.85rem; color:#94a3b8; font-style:italic; padding-left:60px; margin-bottom:10px; animation: fadeInUp 0.6s ease forwards 0.1s; opacity:0;'>- ไม่มีบันทึกเพิ่มเติม -</div>";
            }

            // แทรกรายการสินค้า
            $progress_msg .= $item_html;

            // ปุ่มไฟล์แนบ (Premium Gradient Button)
            if ($proof_file) {
                $progress_msg .= "<div style='margin-top:20px; text-align:right; animation: fadeInUp 0.6s ease forwards 0.5s; opacity:0;'>";
                $progress_msg .= "  <a href='uploads/proofs/$proof_file' target='_blank' style='display:inline-flex; align-items:center; gap:8px; background:linear-gradient(90deg, #2563eb, #4f46e5); color:#ffffff; padding:8px 20px; border-radius:50px; text-decoration:none; font-size:0.85rem; font-weight:600; box-shadow:0 4px 15px -3px rgba(79, 70, 229, 0.4); transition:all 0.3s; border:1px solid rgba(255,255,255,0.2);'>";
                $progress_msg .= "      <i class='fas fa-file-import'></i> ดูหลักฐานแนบ";
                $progress_msg .= "  </a>";
                $progress_msg .= "</div>";
            }

            $progress_msg .= "</div>";

            // =====================================================================================

            // 5. บันทึกข้อมูล
            if (!isset($data['details']['office_log']))
                $data['details']['office_log'] = [];
            $data['details']['office_log'][] = [
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'status' => 'confirmed_receipt',
                'msg' => $remark,
                'items' => $checked_items,
                'file' => $proof_file
            ];
            $logs[] = [
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'msg' => $progress_msg
            ];

            // 6. อัปเดต SQL
            $sql = "UPDATE service_requests SET received_by = ?, received_item_list = ?, progress_logs = ?, received_at = NOW() WHERE id = ?";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
                exit;
            }

            $new_json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $new_logs = json_encode($logs, JSON_UNESCAPED_UNICODE);

            $stmt->bind_param("sssi", $user_name, $new_json, $new_logs, $req_id);

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
    // --- [แก้ไขตัวเต็ม] 1.6 ส่งคืนลูกค้า / ปิดงาน (บันทึกทุกอย่างลง service_requests) ---
    if ($_POST['action'] == 'return_to_customer') {
        header('Content-Type: application/json');

        try {
            $req_id = intval($_POST['req_id']); //
            $user_name = $_SESSION['fullname'] ?? 'Unknown'; //

            // รับค่าข้อมูลสรุปและการประเมิน
            $summary = trim($_POST['work_summary']);    // บันทึกลง return_summary
            $rating = intval($_POST['rating'] ?? 0);    // บันทึกลง return_rating
            $remark = trim($_POST['return_remark']);    // บันทึกลง return_remark
            $return_items = $_POST['returned_items'] ?? []; // รายการอุปกรณ์ที่คืน

            // 1. จัดการไฟล์แนบหลักฐานการส่งคืน (Save ลง return_file_path)
            $proof_file = null;
            if (isset($_FILES['return_proof']) && $_FILES['return_proof']['error'] == 0) {
                $upload_dir = 'uploads/returns/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['return_proof']['name'], PATHINFO_EXTENSION);
                $proof_file = 'ret_' . $req_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['return_proof']['tmp_name'], $upload_dir . $proof_file);
            }

            // 2. ดึงข้อมูลเดิมมาอัปเดต JSON และ Log
            $res = $conn->query("SELECT progress_logs, received_item_list FROM service_requests WHERE id = $req_id");
            $row_data = $res->fetch_assoc();
            $logs = json_decode($row_data['progress_logs'] ?? '[]', true) ?: [];
            $data_json = json_decode($row_data['received_item_list'] ?? '{}', true) ?: [];

            // =====================================================================================
            // 🔥 ส่วนดีไซน์ HTML สำหรับ "สรุปงานซ่อม" (Premium 3D Design) 🔥
            // =====================================================================================

            $stars = "";
            for ($i = 1; $i <= 5; $i++) {
                $color = ($i <= $rating) ? '#f59e0b' : '#e2e8f0';
                $stars .= "<i class='fas fa-star' style='color:{$color}; margin-right:2px; font-size:1rem;'></i>";
            }

            $progress_msg = "
<style>
    @keyframes fadeInUp { from { opacity:0; transform:translateY(15px); } to { opacity:1; transform:translateY(0); } }
    @keyframes pulseSuccess { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
</style>
<div style='font-family:Prompt, sans-serif; position:relative;'>
    <div style='display:flex; align-items:center; gap:12px; margin-bottom:15px; animation: fadeInUp 0.5s ease;'>
        <div style='flex-shrink:0; width:48px; height:48px; background:linear-gradient(135deg, #10b981, #059669); color:#fff; border-radius:14px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px -4px rgba(16, 185, 129, 0.4); animation: pulseSuccess 2s infinite;'>
            <i class='fas fa-check-double fa-lg'></i>
        </div>
        <div>
            <div style='font-weight:800; color:#065f46; font-size:1.1rem; letter-spacing:-0.5px;'>งานซ่อมเสร็จสมบูรณ์ส่งคืนลูกค้าเเล้ว</div>
            <div style='font-size:0.8rem; color:#64748b;'>ดำเนินการโดย: <span style='color:#059669; font-weight:600;'>{$user_name}</span></div>
        </div>
    </div>

    <div style='background:#fff; border:1px solid #e2e8f0; border-left:5px solid #10b981; padding:12px 15px; border-radius:12px; margin-bottom:15px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.02); animation: fadeInUp 0.5s ease 0.1s forwards; opacity:0;'>
        <div style='font-weight:700; color:#065f46; font-size:0.9rem; margin-bottom:5px; display:flex; align-items:center; gap:6px;'>
            <i class='fas fa-tools'></i> สรุปงานซ่อม
        </div>
        <div style='color:#475569; font-size:0.95rem; line-height:1.6;'>" . nl2br(htmlspecialchars($summary)) . "</div>
    </div>";

            // --- ส่วนรายการสินค้าที่ปรับให้สวยแบบตัวอื่นๆ ---
            if (!empty($return_items)) {
                $progress_msg .= "
        <div style='margin-bottom:15px; animation: fadeInUp 0.5s ease 0.2s forwards; opacity:0;'>
            <div style='font-size:0.85rem; font-weight:700; color:#64748b; margin-bottom:10px; display:flex; align-items:center; gap:8px;'>
                <i class='fas fa-boxes' style='color:#10b981;'></i> รายการอุปกรณ์ที่ส่งคืน:
            </div>
            <div style='display:flex; flex-direction:column; gap:8px;'>";

                foreach ($return_items as $index => $item) {
                    $delay = 0.3 + ($index * 0.1); // แอนิเมชันเด้งขึ้นทีละรายการ
                    $progress_msg .= "
                <div style='background:#f8fafc; border:1px solid #f1f5f9; padding:10px 14px; border-radius:10px; display:flex; align-items:center; gap:10px; animation: fadeInUp 0.4s ease {$delay}s forwards; opacity:0;'>
                    <div style='width:24px; height:24px; background:#ecfdf5; color:#10b981; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem;'>
                        <i class='fas fa-box'></i>
                    </div>
                    <div style='font-size:0.9rem; color:#334155; font-weight:500;'>" . htmlspecialchars($item) . "</div>
                </div>";
                }
                $progress_msg .= "</div></div>";
            }

            // ส่วนความพึงพอใจ
            $progress_msg .= "
    <div style='background:linear-gradient(135deg, #fff, #f0fdf4); border:1px solid #dcfce7; padding:15px; border-radius:14px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.04); animation: fadeInUp 0.5s ease 0.4s forwards; opacity:0;'>
        <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;'>
            <span style='font-weight:700; color:#1e293b; font-size:0.9rem;'>ระดับความพึงพอใจ:</span>
            <div style='background:#fff; padding:4px 10px; border-radius:20px; box-shadow:0 2px 4px rgba(0,0,0,0.05);'>
                {$stars} <span style='font-weight:800; color:#f59e0b; margin-left:4px;'>{$rating}.0</span>
            </div>
        </div>
        <div style='color:#64748b; font-size:0.9rem; font-style:italic; padding:10px; background:rgba(255,255,255,0.5); border-radius:8px; border-left:3px solid #cbd5e1;'>
            \"" . htmlspecialchars($remark ?: 'ไม่มีข้อเสนอแนะเพิ่มเติมจากลูกค้า') . "\"
        </div>";

            // ปุ่มดูหลักฐาน
            if ($proof_file) {
                $progress_msg .= "
            <div style='margin-top:15px; text-align:right;'>
                <a href='uploads/returns/{$proof_file}' target='_blank' style='display:inline-flex; align-items:center; gap:6px; background:#10b981; color:#fff; padding:6px 14px; border-radius:50px; text-decoration:none; font-size:0.8rem; font-weight:600; box-shadow:0 4px 10px rgba(16, 185, 129, 0.2);'>
                    <i class='fas fa-file-invoice'></i> ดูใบส่งของ/หลักฐาน
                </a>
            </div>";
            }

            $progress_msg .= "</div></div>";

            // =====================================================================================

            // 3. เตรียม JSON สำหรับ Timeline
            $data_json['details']['customer_return'] = [
                'by' => $user_name,
                'at' => date('d/m/Y H:i'),
                'rating' => $rating,
                'msg' => $summary,
                'remark' => $remark,
                'items_returned' => $return_items,
                'file' => $proof_file
            ];

            // 4. บันทึกประวัติลง Log
            $logs[] = [
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'msg' => $progress_msg
            ];

            // 5. อัปเดต SQL (ตารางเดียวจบ service_requests)
            $sql = "UPDATE service_requests SET 
                status = 'completed', 
                completed_at = NOW(), 
                completed_by = ?,
                return_rating = ?, 
                return_remark = ?, 
                return_summary = ?, 
                return_file_path = ?, 
                progress_logs = ?, 
                received_item_list = ? 
                WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $logs_json = json_encode($logs, JSON_UNESCAPED_UNICODE);
            $final_json = json_encode($data_json, JSON_UNESCAPED_UNICODE);

            // Bind ข้อมูลตามลำดับ
            $stmt->bind_param("sisssssi", $user_name, $rating, $remark, $summary, $proof_file, $logs_json, $final_json, $req_id);

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
    // --- [แก้ไข] 1.7 รับของกลับจากร้านซ่อม & บันทึกค่าใช้จ่าย (Premium Pink Design) ---
    if ($_POST['action'] == 'receive_from_shop') {
        header('Content-Type: application/json');

        if (!isset($conn) || $conn->connect_error) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            exit;
        }

        try {
            $req_id = intval($_POST['req_id']);
            $total_cost = floatval($_POST['repair_cost']);
            $items_json = $_POST['repair_items']; // JSON String จาก JS (มี name, qty, price, total)
            $user_name = $_SESSION['fullname'] ?? 'Unknown';

            // 1. จัดการไฟล์แนบ (ใบเสร็จ)
            $file_name = null;
            if (isset($_FILES['shop_file']) && $_FILES['shop_file']['error'] == 0) {
                $upload_dir = 'uploads/repairs/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['shop_file']['name'], PATHINFO_EXTENSION);
                $file_name = 'rep_' . $req_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['shop_file']['tmp_name'], $upload_dir . $file_name);
            }

            // 2. ดึงข้อมูลเดิม
            $res_data = $conn->query("SELECT received_item_list, progress_logs FROM service_requests WHERE id = $req_id");
            $row_data = $res_data->fetch_assoc();

            $item_list_dec = json_decode($row_data['received_item_list'] ?? '{}', true);
            $logs = json_decode($row_data['progress_logs'] ?? '[]', true) ?: [];

            // แปลงรายการสินค้าเป็น Array
            $items_arr = json_decode($items_json, true);

            // =====================================================================================
            // 🔥 ส่วนที่ 1: สร้าง HTML สำหรับโชว์หน้า "อัปเดตความคืบหน้า" (Main Log) 🔥
            // =====================================================================================

            $shop_name = $item_list_dec['details']['shop_info']['name'] ?? 'ร้านภายนอก';

            $css_anim = "<style>@keyframes fadeInUp { from { opacity:0; transform:translateY(15px); } to { opacity:1; transform:translateY(0); } } @keyframes pulsePink { 0% { box-shadow: 0 0 0 0 rgba(219, 39, 119, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(219, 39, 119, 0); } 100% { box-shadow: 0 0 0 0 rgba(219, 39, 119, 0); } }</style>";
            $progress_msg = $css_anim . "<div style='font-family:Prompt, sans-serif; position:relative;'>";

            // Header
            $progress_msg .= "<div style='display:flex; align-items:center; gap:12px; margin-bottom:15px; animation: fadeInUp 0.5s ease forwards;'>";
            $progress_msg .= "  <div style='flex-shrink:0; width:48px; height:48px; background:linear-gradient(135deg, #ec4899, #be185d); color:#fff; border-radius:14px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px -4px rgba(190, 24, 93, 0.5); animation: pulsePink 2s infinite;'>";
            $progress_msg .= "      <i class='fas fa-file-invoice-dollar fa-lg'></i>";
            $progress_msg .= "  </div>";
            $progress_msg .= "  <div>";
            $progress_msg .= "      <div style='font-weight:800; color:#831843; font-size:1.1rem; letter-spacing:-0.5px;'>รับของกลับ & บันทึกค่าใช้จ่าย</div>";
            $progress_msg .= "      <div style='font-size:0.85rem; color:#9d174d;'>รับกลับจาก: <b>{$shop_name}</b></div>";
            $progress_msg .= "  </div>";
            $progress_msg .= "</div>";

            // Cost Badge
            $progress_msg .= "<div style='background:linear-gradient(to right, #fdf2f8, #fff); border-left:5px solid #db2777; padding:12px 16px; border-radius:8px; margin-bottom:15px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 4px rgba(0,0,0,0.03); animation: fadeInUp 0.5s ease forwards 0.1s; opacity:0;'>";
            $progress_msg .= "  <div style='font-size:0.9rem; color:#be185d; font-weight:600;'>💰 ยอดรวมทั้งสิ้น</div>";
            $progress_msg .= "  <div style='font-size:1.4rem; color:#9d174d; font-weight:800;'>" . number_format($total_cost, 2) . " <span style='font-size:0.8rem;'>บาท</span></div>";
            $progress_msg .= "</div>";

            // Item Table (สร้างตาราง HTML เพื่อโชว์ใน Log)
            if (!empty($items_arr)) {
                $progress_msg .= "<div style='margin-top:10px; border:1px solid #fbcfe8; border-radius:8px; overflow:hidden; animation: fadeInUp 0.5s ease forwards 0.2s; opacity:0;'>";
                $progress_msg .= "  <table style='width:100%; border-collapse:collapse; font-size:0.85rem;'>";
                $progress_msg .= "    <tr style='background:#fce7f3; color:#831843; font-weight:700;'>";
                $progress_msg .= "      <td style='padding:8px;'>รายการ</td><td style='padding:8px; text-align:center;'>จำนวน</td><td style='padding:8px; text-align:right;'>รวม</td>";
                $progress_msg .= "    </tr>";

                foreach ($items_arr as $item) {
                    $name = htmlspecialchars($item['name'] ?? '-');
                    $qty = isset($item['qty']) ? number_format($item['qty']) : '1';
                    $line_total = isset($item['total']) ? number_format($item['total'], 2) : '0.00';

                    $progress_msg .= "    <tr style='border-bottom:1px dashed #fce7f3;'>";
                    $progress_msg .= "      <td style='padding:8px; color:#334155;'>{$name}</td>";
                    $progress_msg .= "      <td style='padding:8px; text-align:center; color:#64748b;'>{$qty}</td>";
                    $progress_msg .= "      <td style='padding:8px; text-align:right; font-weight:600; color:#be185d;'>{$line_total}</td>";
                    $progress_msg .= "    </tr>";
                }
                $progress_msg .= "  </table>";
                $progress_msg .= "</div>";
            }

            // File Button
            if ($file_name) {
                $progress_msg .= "<div style='margin-top:15px; text-align:right; animation: fadeInUp 0.5s ease forwards 0.3s; opacity:0;'>";
                $progress_msg .= "  <a href='uploads/repairs/$file_name' target='_blank' style='display:inline-flex; align-items:center; gap:8px; background:linear-gradient(90deg, #db2777, #be185d); color:#ffffff; padding:8px 20px; border-radius:50px; text-decoration:none; font-size:0.85rem; font-weight:600; box-shadow:0 4px 10px rgba(190, 24, 93, 0.3); transition:all 0.2s;'>";
                $progress_msg .= "      <i class='fas fa-receipt'></i> ดูใบเสนอราคา/หลักฐาน";
                $progress_msg .= "  </a>";
                $progress_msg .= "</div>";
            }
            $progress_msg .= "</div>";

            // =====================================================================================
            // 🔥 ส่วนที่ 2: เตรียมข้อมูลลง JSON Timeline (Received Item List) 🔥
            // =====================================================================================

            // สร้าง Object ใหม่ที่จะบันทึกลง JSON
            $new_office_log = [
                'status' => 'back_from_shop',
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'msg' => "รับของกลับจากร้านซ่อม (ค่าใช้จ่าย: " . number_format($total_cost, 2) . ")",
                'expenses' => $items_arr, // ✅ เก็บ Array รายการทั้งหมดลงไปตรงนี้ เพื่อให้ JS ดึงไปโชว์ได้
                'total_cost' => $total_cost,
                'file' => $file_name
            ];

            // อัปเดต JSON เดิม
            $item_list_dec['details']['type'] = 'office';
            if ($file_name) {
                $item_list_dec['details']['shop_info']['file'] = $file_name;
            }
            if (!isset($item_list_dec['details']['office_log']))
                $item_list_dec['details']['office_log'] = [];
            $item_list_dec['details']['office_log'][] = $new_office_log;

            $updated_json = json_encode($item_list_dec, JSON_UNESCAPED_UNICODE);

            // =====================================================================================
            // 🔥 ส่วนที่ 3: บันทึกและสรุปข้อมูล 🔥
            // =====================================================================================

            // บันทึก Log หน้าหลัก
            $logs[] = [
                'at' => date('d/m/Y H:i'),
                'by' => $user_name,
                'msg' => $progress_msg // เก็บ HTML ที่สร้างไว้ด้านบน
            ];
            $new_logs_json = json_encode($logs, JSON_UNESCAPED_UNICODE);

            // สร้าง Text Summary สำหรับ cost_details (เก็บไว้ดูง่ายๆ ใน DB)
            $summary_text = "";
            foreach ($items_arr as $it) {
                $q = $it['qty'] ?? 1;
                $p = $it['price'] ?? 0;
                $t = $it['total'] ?? 0;
                $summary_text .= "- " . ($it['name'] ?? '') . " ({$q} x {$p} = {$t})\n";
            }

            // สถานะการเงิน
            $cost_status = ($total_cost > 1000) ? 'pending' : ($total_cost > 0 ? 'approved' : 'none');

            // อัปเดต Database
            $sql = "UPDATE service_requests SET 
                    additional_cost = ?, 
                    cost_details = ?, 
                    cost_status = ?,
                    progress_logs = ?, 
                    received_item_list = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
                exit;
            }

            $stmt->bind_param("dssssi", $total_cost, $summary_text, $cost_status, $new_logs_json, $updated_json, $req_id);

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

    // [แก้ไข] อนุมัติค่าใช้จ่าย + บันทึกผู้กด
    if ($_POST['action'] == 'approve_cost') {
        header('Content-Type: application/json');

        $req_id = isset($_POST['req_id']) ? intval($_POST['req_id']) : 0;
        $user_name = $_SESSION['fullname'] ?? 'Unknown'; // ดึงชื่อคนล็อกอิน

        // อัปเดตสถานะ + ชื่อคนกด + เวลาปัจจุบัน (NOW())
        $sql = "UPDATE service_requests SET 
                cost_status = 'approved', 
                cost_approved_by = ?, 
                cost_approved_at = NOW() 
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $user_name, $req_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        exit;
    }
    // 1.8 ดึงประวัติการให้คะแนน
    if ($_POST['action'] == 'get_rating_history') {
        header('Content-Type: application/json');

        // ดึงข้อมูลโดยตรงจาก service_requests เชื่อมกับ project_contracts
        $sql = "SELECT 
                pc.site_id as site_code, 
                pc.project_name, 
                sr.return_rating as rating, 
                sr.return_remark as comment, 
                sr.completed_at 
            FROM service_requests sr
            JOIN project_contracts pc ON sr.site_id = pc.site_id 
            WHERE sr.status = 'completed' AND sr.return_rating > 0
            ORDER BY sr.completed_at DESC";

        $res = $conn->query($sql);
        $history = [];

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $history[] = [
                    'site_id' => $row['site_code'],
                    'project_name' => $row['project_name'],
                    'rating' => intval($row['rating']),
                    'comment' => $row['comment'] ?: '-',
                    'at' => date('d/m/Y H:i', strtotime($row['completed_at']))
                ];
            }
        }
        echo json_encode($history);
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
    $where_sql .= " AND (sr.site_id LIKE ? OR pc.project_name LIKE ? OR c.customer_name LIKE ? OR sr.reporter_name LIKE ?) ";
    $like_term = "%" . $search_keyword . "%";
    array_push($params, $like_term, $like_term, $like_term, $like_term);
    $types .= "ssss";
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
$sql_list = "SELECT sr.*, pc.project_name, c.customer_name, rt.rating as satisfaction_score
             FROM service_requests sr
             LEFT JOIN project_contracts pc ON sr.site_id = pc.site_id
             LEFT JOIN customers c ON pc.customer_id = c.customer_id
             LEFT JOIN service_ratings rt ON sr.id = rt.req_id
             $where_sql  -- ต้องมีบรรทัดนี้
             ORDER BY sr.request_date DESC";

$stmt = $conn->prepare($sql_list);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

// ✅ เปลี่ยนจาก $result = ... เป็น $res_list = ...
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
                // คำนวณยอดสรุป
                $sql_sum = "SELECT 
        SUM(CASE WHEN cost_status = 'approved' THEN additional_cost ELSE 0 END) as total_paid,
        SUM(CASE WHEN cost_status = 'pending' THEN additional_cost ELSE 0 END) as total_pending
        FROM service_requests";
                $sums = $conn->query($sql_sum)->fetch_assoc();

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
            <div style="
    display: flex; 
    flex-wrap: wrap; 
    align-items: center; 
    justify-content: space-between; 
    margin: 15px 0; 
    padding: 15px 20px; 
    background: #ffffff; 
    border: 1px solid #e2e8f0; 
    border-left: 5px solid #8b5cf6; 
    border-radius: 10px; 
    box-shadow: 0 2px 5px rgba(0,0,0,0.03);">

                <div style="display: flex; align-items: center;">

                    <div style="
            width: 45px; 
            height: 45px; 
            background: #f3e8ff; 
            color: #7c3aed; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.2rem; 
            margin-right: 15px;">
                        <i class="fas fa-star"></i>
                    </div>

                    <div>
                        <div
                            style="font-size: 0.85rem; color: #6b7280; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1;">
                            ความพึงพอใจ (SATISFACTION)
                        </div>
                        <div style="display: flex; align-items: center; margin-top: 5px;">
                            <span style="
                    background: #7c3aed; 
                    color: #fff; 
                    font-size: 0.9rem; 
                    font-weight: 700; 
                    padding: 2px 10px; 
                    border-radius: 20px; 
                    margin-right: 10px;">
                                <?php echo $avg_score; ?>
                            </span>

                            <div style="color: #cbd5e1; font-size: 0.95rem;">
                                <?php
                                $star_round = round((float) $avg_score);
                                for ($i = 1; $i <= 5; $i++) {
                                    $color = ($i <= $star_round && $total_votes > 0) ? '#f59e0b' : '#e2e8f0';
                                    echo '<i class="fas fa-star" style="color:' . $color . '; margin-right: 2px;"></i>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; margin-top: 5px;">

                    <div class="hidden-xs"
                        style="text-align: right; margin-right: 15px; border-right: 1px solid #e5e7eb; padding-right: 15px;">
                        <div style="font-size: 0.75rem; color: #9ca3af;">จากทั้งหมด</div>
                        <div style="font-weight: 700; color: #374151; font-size: 1rem; line-height: 1;">
                            <?php echo number_format($total_votes); ?> <span
                                style="font-weight: 400; font-size: 0.8rem;">รายการ</span>
                        </div>
                    </div>

                    <button onclick="showRatingHistory()" style="
            background: #fff; 
            border: 1px solid #ddd6fe; 
            color: #7c3aed; 
            border-radius: 50px; 
            padding: 6px 16px; 
            font-weight: 600; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            transition: 0.2s;">
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
                                <tr>
                                    <td>
                                        <span style="font-weight: 700; color: var(--accent-start); font-size:1rem;">
                                            <?php echo $row['site_id']; ?>
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
                                            <?php echo htmlspecialchars($row['customer_name']); ?>
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
                                        $rec_data = json_decode($row['received_item_list'] ?? '{}', true);
                                        $d = $rec_data['details'] ?? [];
                                        $type = $d['type'] ?? '';

                                        // 🔥 เช็คว่ามีข้อมูลการคืนลูกค้าไหม (ถ้ามี = ปุ่มหาย)
                                        $is_returned_customer = !empty($d['customer_return']);
                                        $returner_name = $d['customer_return']['by'] ?? '-';

                                        $jsonStr = htmlspecialchars(json_encode($row['received_item_list'] ?? '{}'), ENT_QUOTES, 'UTF-8');
                                        $projItemsStr = htmlspecialchars(json_encode($row['project_item_name'] ?? "[]"), ENT_QUOTES, 'UTF-8');
                                        ?>

                                        <?php if ($is_returned_customer): ?>
                                            <div onclick='viewReceiverDetails(<?= $jsonStr; ?>)'
                                                style="cursor:pointer; background:#ecfdf5; border:1px solid #10b981; border-radius:10px; padding:10px; width:100%; display:block; box-sizing:border-box; transition: 0.2s; box-shadow: 0 2px 5px rgba(16, 185, 129, 0.05);">

                                                <div
                                                    style="color:#047857; font-weight:800; font-size:0.95rem; margin-bottom:6px; display:block;">
                                                    <i class="fas fa-check-circle"></i> เรียบร้อย
                                                </div>

                                                <div
                                                    style="color:#065f46; font-size:0.85rem; border-top: 1px dashed rgba(16, 185, 129, 0.3); padding-top:6px; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                    ผู้คืน:
                                                    <strong><?= htmlspecialchars($returner_name) ?></strong>
                                                </div>
                                            </div>

                                        <?php elseif (empty($row['received_by'])): ?>
                                            <button type="button" class="btn-receive btn-sm orange"
                                                onclick='receiveItem(<?= $row['id']; ?>, <?= $projItemsStr; ?>)'>
                                                <i class="fas fa-hand-holding"></i> กดรับของ
                                            </button>

                                        <?php else: ?>
                                            <div class="btn-group-vertical">
                                                <?php if ($type === 'external'): ?>
                                                    <button type="button" class="btn-receive btn-sm"
                                                        style="background: linear-gradient(135deg, #db2777, #be185d); border-bottom: 2px solid #9d174d;"
                                                        onclick='receiveFromShop(<?= $row['id']; ?>, <?= $jsonStr; ?>)'>
                                                        <i class="fas fa-undo-alt"></i> รับจากร้าน
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-receive btn-sm blue"
                                                        onclick='confirmOfficeReceipt(<?= $row['id']; ?>, <?= $jsonStr; ?>)'>
                                                        <i class="fas fa-user-check"></i> รับช่วงต่อ
                                                    </button>
                                                <?php endif; ?>

                                                <button type="button" class="btn-receive btn-sm purple"
                                                    onclick='returnToCustomer(<?= $row['id']; ?>, <?= $jsonStr; ?>)'>
                                                    <i class="fas fa-shipping-fast"></i> คืนลูกค้า
                                                </button>

                                                <div class="current-holder-box" style="margin-top:5px;">
                                                    <?php
                                                    if ($type === 'external') {
                                                        echo '<div class="holder-tag shop"style="white-space:nowrap;" ><i class="fas fa-store"></i> ร้าน: ' . htmlspecialchars($d['shop_info']['name'] ?? '-') . '</div>';
                                                    } else {
                                                        $last_actor = $row['received_by'];
                                                        if (!empty($d['office_log'])) {
                                                            $last_log = end($d['office_log']);
                                                            $last_actor = $last_log['by'];
                                                        }
                                                        echo '<div class="holder-tag office"><i class="fas fa-hand-holding"></i> อยู่ที่: ' . htmlspecialchars($last_actor) . '</div>';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        // 1. แกะ JSON (พร้อมระบบกันเหนียว 2 ชั้น)
                                        $items_raw = json_decode($row['project_item_name'] ?? '[]', true);
                                        if (is_string($items_raw)) {
                                            $items_raw = json_decode($items_raw, true);
                                        }

                                        // 2. เตรียมข้อมูลสำหรับส่งให้ JavaScript
                                        $jsonForJS = htmlspecialchars(json_encode($items_raw), ENT_QUOTES, 'UTF-8');
                                        ?>

                                        <button class="btn-view" onclick='viewItems(<?php echo $jsonForJS; ?>)'
                                            title="ดูรายการสินค้าทั้งหมด"
                                            style="width: 42px; height: 42px; border-radius: 12px; background: #e0f2fe; color: #0369a1; border: none; cursor: pointer; transition: all 0.2s;">
                                            <i class="fas fa-box-open" style="font-size: 1.2rem;"></i>
                                        </button>
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

                                    <td class="text-center" style="vertical-align: middle;">
                                        <?php if ($row['additional_cost'] > 0): ?>

                                            <?php
                                            // 1. ดึงชื่อไฟล์แนบ (ใบเสร็จ) ออกมาจาก JSON
                                            $cost_data_json = json_decode($row['received_item_list'] ?? '{}', true);
                                            // ดึงไฟล์จาก path: details -> shop_info -> file
                                            $repair_file = $cost_data_json['details']['shop_info']['file'] ?? '';
                                            ?>

                                            <?php if ($row['cost_status'] === 'pending'): ?>
                                                <button type="button" class="btn-receive btn-sm orange"
                                                    style="background: linear-gradient(135deg, #f59e0b, #d97706); border-bottom: 2px solid #b45309; color:white; width: 100%;"
                                                    onclick='approveRepairCost(<?= $row['id'] ?>, <?= json_encode($row['cost_details'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>, <?= $row['additional_cost'] ?>, "<?= $repair_file ?>")'>
                                                    <i class="fas fa-exclamation-circle"></i> รออนุมัติ
                                                </button>
                                                <div style="font-size: 0.7rem; color: #d97706; margin-top: 4px; font-weight: 600;">
                                                    ฿ <?= number_format($row['additional_cost'], 2) ?>
                                                </div>

                                            <?php elseif ($row['cost_status'] === 'approved'): ?>
                                                <div
                                                    style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 6px; text-align: left;">
                                                    <div
                                                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                                        <div style="color: #059669; font-weight: 700; font-size: 0.9rem;">
                                                            <i class="fas fa-check-circle"></i> ฿
                                                            <?= number_format($row['additional_cost'], 2) ?>
                                                        </div>
                                                        <button
                                                            onclick='viewApprovedCost(<?= json_encode($row['cost_details'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>, "<?= $repair_file ?>")'
                                                            style="border:none; background:none; color:#059669; cursor:pointer; font-size:0.8rem;"
                                                            title="ดูรายละเอียด">
                                                            <i class="fas fa-search"></i>
                                                        </button>
                                                    </div>

                                                    <div
                                                        style="font-size: 0.65rem; color: #64748b; border-top: 1px dashed #d1fae5; padding-top: 4px;">
                                                        <div><i class="fas fa-user-check"></i>
                                                            <?= htmlspecialchars($row['cost_approved_by'] ?? '-') ?></div>
                                                        <div><i class="far fa-clock"></i>
                                                            <?= !empty($row['cost_approved_at']) ? date('d/m/y H:i', strtotime($row['cost_approved_at'])) : '-' ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <span style="color: #cbd5e1;">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <?php
                                        // 1. ใช้ Logic เดียวกัน แกะ JSON ให้เรียบร้อยก่อนส่ง
                                        $clean_items = json_decode($row['project_item_name'] ?? '[]', true);
                                        if (is_string($clean_items)) {
                                            $clean_items = json_decode($clean_items, true);
                                        }

                                        $detailData = [
                                            'project_item_name' => json_encode($clean_items), // ส่งเป็น JSON String ที่ถูกต้องแน่นอน
                                            'issue_description' => $row['issue_description'],
                                            'initial_advice' => $row['initial_advice'],
                                            'assessment' => $row['assessment'],
                                            'remark' => $row['remark']
                                        ];
                                        ?>
                                        <button class="btn-view-3d"
                                            onclick='viewDetails(<?php echo json_encode($detailData, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
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
    </div>

    <script>
        const techList = <?php echo json_encode($technicians); ?>;
        const allEmployeeList = <?php echo json_encode($all_employees); ?>;

    </script>
    <script src="js/service_dashboard.js"></script>
</body>

</html>