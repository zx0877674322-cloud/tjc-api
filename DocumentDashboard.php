<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'auth.php';
require_once 'db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Asia/Bangkok');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ใช้ Try-Catch เพื่อดักจับ Error ระหว่างการบันทึกข้อมูล
    try {
        // ✅ 1. รับค่าแบบปลอดภัย (ป้องกัน Undefined variable)
        $id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        $current_user = $_SESSION['fullname'];
        $now = date('Y-m-d H:i:s');

        // กำหนดไฟล์ที่อนุญาต (ใช้ร่วมกันทั้งระบบเพื่อความปลอดภัย)
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'doc', 'docx'];

        if ($action == 'approve') {
            $stmt = $conn->prepare("UPDATE document_submissions SET approver_name=?, approved_at=? WHERE id=?");
            $stmt->bind_param("ssi", $current_user, $now, $id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'finance') {
            $stmt = $conn->prepare("UPDATE document_submissions SET finance_receiver=?, finance_received_at=? WHERE id=?");
            $stmt->bind_param("ssi", $current_user, $now, $id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'note') {
            $note = $_POST['doc_note'];
            $stmt = $conn->prepare("UPDATE document_submissions SET doc_note=?, doc_note_by=? WHERE id=?");
            $stmt->bind_param("ssi", $note, $current_user, $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action == 'return_document') {
            $doc_id = intval($_POST['doc_id']);
            $is_returning = $_POST['is_returning']; // 1=ตีกลับ, 0=ยกเลิก

            if ($is_returning == '1') {
                // ✅ 1. รับค่าหมายเหตุ (ถ้าไม่มีให้เป็น null)
                $remark = isset($_POST['return_remark']) ? trim($_POST['return_remark']) : null;

                // ✅ 2. เพิ่มการบันทึก return_remark ลงฐานข้อมูล
                $sql = "UPDATE document_submissions SET 
                return_doc_by = ?, 
                return_doc_at = ?, 
                return_remark = ?,  -- เพิ่มคอลัมน์นี้
                cancel_return_by = NULL, 
                cancel_return_at = NULL 
                WHERE id = ?";

                $stmt = $conn->prepare($sql);
                // ✅ 3. ผูกตัวแปร (sssi -> sssi) : s ตัวที่ 3 คือ remark
                $stmt->bind_param("sssi", $current_user, $now, $remark, $doc_id);
                $stmt->execute();
                $stmt->close();

            } else {
                // กรณี "ยกเลิก" (ต้องล้างค่าหมายเหตุทิ้งด้วย เพื่อไม่ให้ค้าง)
                $sql = "UPDATE document_submissions SET 
                return_doc_by = NULL, 
                return_doc_at = NULL,
                return_remark = NULL, -- ล้างหมายเหตุออก
                cancel_return_by = ?, 
                cancel_return_at = ?
                WHERE id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $current_user, $now, $doc_id);
                $stmt->execute();
                $stmt->close();
            }

            // ✅ 4. ส่งค่าหมายเหตุกลับไปให้ JavaScript แสดงผลทันที
            echo json_encode([
                'status' => 'success',
                'doc_id' => $doc_id,
                'is_returning' => $is_returning,
                'action_by' => $current_user,
                'action_at' => date('d/m/y H:i', strtotime($now)),
                'return_remark' => isset($remark) ? $remark : '' // ส่งกลับไปแสดง
            ]);
            exit;
        } elseif ($action == 'update_credit') {
            $credit = $_POST['credit_term'];
            $stmt = $conn->prepare("UPDATE document_submissions SET credit_term=? WHERE id=?");
            $stmt->bind_param("si", $credit, $id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'receive_tax') {
            // --- 1. ดึงข้อมูลเดิมเพื่อดูว่าคลังใส่อะไรไว้ไหม ---
            $q_chk = $conn->query("SELECT wh_tax_invoice_no, wh_tax_file, tax_file FROM document_submissions WHERE id=$id");
            $r_chk = $q_chk->fetch_assoc();

            // --- 2. จัดการเลขที่ใบกำกับ ---
            $tax_no = trim($_POST['tax_inv_number']);
            // ถ้าช่องกรอกว่างเปล่า แต่คลังมีข้อมูล -> ให้ใช้เลขของคลังแทน
            if (empty($tax_no) && !empty($r_chk['wh_tax_invoice_no'])) {
                $tax_no = $r_chk['wh_tax_invoice_no'];
            }

            $note_input = $_POST['action_note'] ?? '';
            $note_append = $note_input ? "\n[ผู้ซื้อรับใบกำกับ]: " . $note_input : "";

            $stmt = null;
            $final_filename = null; // ตัวแปรเก็บชื่อไฟล์ที่จะบันทึก

            // --- 3. ตรวจสอบการอัปโหลดไฟล์ ---
            // กรณี A: มีการอัปโหลดไฟล์ใหม่มา
            if (isset($_FILES['tax_file_upload']) && $_FILES['tax_file_upload']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['tax_file_upload']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_extensions)) {
                    throw new Exception("นามสกุลไฟล์ไม่ถูกต้อง");
                }
                $final_filename = "tax_" . time() . "_" . $id . "." . $ext;
                if (!move_uploaded_file($_FILES['tax_file_upload']['tmp_name'], "uploads/" . $final_filename)) {
                    throw new Exception("ไม่สามารถย้ายไฟล์ได้");
                }
            }
            // กรณี B: ไม่ได้อัปไฟล์ใหม่ แต่ยังไม่มีไฟล์ในระบบ และคลังมีไฟล์แนบไว้
            // (ดึงชื่อไฟล์คลังมาใช้เลย ไม่ต้องอัปโหลดซ้ำ)
            elseif (empty($r_chk['tax_file']) && !empty($r_chk['wh_tax_file'])) {
                $final_filename = $r_chk['wh_tax_file'];
            }

            // --- 4. บันทึกลงฐานข้อมูล ---
            if ($final_filename) {
                // กรณีมีไฟล์ใหม่ หรือดึงไฟล์คลังมา
                $stmt = $conn->prepare("UPDATE document_submissions SET tax_receiver=?, tax_received_at=?, tax_file=?, tax_invoice_no=?, note_buyer=?, doc_note=CONCAT(IFNULL(doc_note,''), ?) WHERE id=?");
                $stmt->bind_param("ssssssi", $current_user, $now, $final_filename, $tax_no, $note_input, $note_append, $id);
            } else {
                // กรณีไม่มีไฟล์เปลี่ยนแปลง (ใช้ไฟล์เดิมที่มีอยู่แล้ว หรือไม่มีไฟล์เลย)
                $stmt = $conn->prepare("UPDATE document_submissions SET tax_receiver=?, tax_received_at=?, tax_invoice_no=?, note_buyer=?, doc_note=CONCAT(IFNULL(doc_note,''), ?) WHERE id=?");
                $stmt->bind_param("sssssi", $current_user, $now, $tax_no, $note_input, $note_append, $id);
            }

            if ($stmt) {
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($action == 'update_status_note') {
            $note = $_POST['status_note_text'];
            // บันทึกข้อมูลลงคอลัมน์ tax_status_note
            $stmt = $conn->prepare("UPDATE document_submissions SET tax_status_note=? WHERE id=?");
            $stmt->bind_param("si", $note, $id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'acc_receive') {
            $note_input = $_POST['action_note'] ?? '';
            $note_append = $note_input ? "\n[บัญชีรับใบกำกับ]: " . $note_input : "";
            $tax_no = trim($_POST['tax_inv_number']);

            // 1. ดึงข้อมูลเก่า (เพิ่ม wh_tax_file และ wh_tax_invoice_no)
            $stmt_get = $conn->prepare("SELECT attachments, acc_file, tax_file, wh_tax_file, wh_tax_invoice_no FROM document_submissions WHERE id=?");
            $stmt_get->bind_param("i", $id);
            $stmt_get->execute();
            $res = $stmt_get->get_result();
            $row_old = $res->fetch_assoc();
            $stmt_get->close();

            // 2. ถ้าบัญชียังไม่กรอกเลขที่ใบกำกับ ให้ลองดึงจากคลังมาใช้ (ถ้าผู้ซื้อไม่มี)
            if (empty($tax_no) && !empty($row_old['wh_tax_invoice_no'])) {
                $tax_no = $row_old['wh_tax_invoice_no'];
            }

            // จัดการ Attachments (เปลี่ยนสถานะ รอ -> ได้รับ) - ส่วนนี้เหมือนเดิม
            $new_att_str = $row_old['attachments'];
            if ($new_att_str) {
                $att_arr = json_decode($new_att_str, true);
                if (is_array($att_arr)) {
                    foreach ($att_arr as $key => $val) {
                        if (strpos($val, 'รอใบกำกับภาษี 7%') !== false) {
                            $att_arr[$key] = str_replace('รอใบกำกับภาษี 7%', 'ใบกำกับภาษี 7%', $val);
                        }
                    }
                    $new_att_str = json_encode($att_arr, JSON_UNESCAPED_UNICODE);
                }
            }

            $stmt = null;
            $acc_filename = null;

            // 3. Logic เลือกไฟล์
            if (isset($_FILES['acc_file_upload']) && $_FILES['acc_file_upload']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['acc_file_upload']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_extensions))
                    throw new Exception("นามสกุลไฟล์ไม่ถูกต้อง");

                $acc_filename = "acc_" . time() . "_" . $id . "." . $ext;
                if (!move_uploaded_file($_FILES['acc_file_upload']['tmp_name'], "uploads/" . $acc_filename)) {
                    throw new Exception("Upload Failed");
                }
            }
            // กรณี B: ไม่ได้อัปไฟล์ใหม่ (เช็คไฟล์ที่มีอยู่)
            else {
                if (!empty($row_old['acc_file'])) {
                    // ถ้าบัญชีมีไฟล์อยู่แล้ว -> ใช้ไฟล์เดิม
                } elseif (!empty($row_old['tax_file'])) {
                    // ถ้าไม่มีไฟล์บัญชี -> ดึงไฟล์ผู้ซื้อ
                    $acc_filename = $row_old['tax_file'];
                } elseif (!empty($row_old['wh_tax_file'])) {
                    // ถ้าไม่มีไฟล์ผู้ซื้อ -> ดึงไฟล์คลัง
                    $acc_filename = $row_old['wh_tax_file'];
                }
            }

            // 4. บันทึกลงฐานข้อมูล
            if ($acc_filename) {
                $stmt = $conn->prepare("UPDATE document_submissions SET acc_receiver=?, acc_received_at=?, acc_file=?, tax_invoice_no=?, attachments=?, note_acc=?, doc_note=CONCAT(IFNULL(doc_note,''), ?) WHERE id=?");
                $stmt->bind_param("sssssssi", $current_user, $now, $acc_filename, $tax_no, $new_att_str, $note_input, $note_append, $id);
            } else {
                $stmt = $conn->prepare("UPDATE document_submissions SET acc_receiver=?, acc_received_at=?, tax_invoice_no=?, attachments=?, note_acc=?, doc_note=CONCAT(IFNULL(doc_note,''), ?) WHERE id=?");
                $stmt->bind_param("ssssssi", $current_user, $now, $tax_no, $new_att_str, $note_input, $note_append, $id);
            }

            if ($stmt) {
                $stmt->execute();
                $stmt->close();
            }

        } elseif ($action == 'wh_tax_receive') {
            $tax_no = trim($_POST['wh_tax_inv_number']);
            $note_input = $_POST['wh_action_note'] ?? '';

            // เตรียมไฟล์แนบ
            $wh_filename = null;
            if (isset($_FILES['wh_tax_file_upload']) && $_FILES['wh_tax_file_upload']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['wh_tax_file_upload']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_extensions)) {
                    throw new Exception("นามสกุลไฟล์ไม่ถูกต้อง");
                }
                $wh_filename = "wh_tax_" . time() . "_" . $id . "." . $ext;
                if (!move_uploaded_file($_FILES['wh_tax_file_upload']['tmp_name'], "uploads/" . $wh_filename)) {
                    throw new Exception("Upload Failed");
                }
            }

            // อัปเดตฐานข้อมูล
            if ($wh_filename) {
                $stmt = $conn->prepare("UPDATE document_submissions SET wh_tax_receiver=?, wh_tax_received_at=?, wh_tax_invoice_no=?, wh_tax_file=?, wh_tax_note=? WHERE id=?");
                $stmt->bind_param("sssssi", $current_user, $now, $tax_no, $wh_filename, $note_input, $id);
            } else {
                $stmt = $conn->prepare("UPDATE document_submissions SET wh_tax_receiver=?, wh_tax_received_at=?, wh_tax_invoice_no=?, wh_tax_note=? WHERE id=?");
                $stmt->bind_param("ssssi", $current_user, $now, $tax_no, $note_input, $id);
            }
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'warehouse_receive') {
            $wh_doc = $_POST['warehouse_doc_no'];
            $stmt = $conn->prepare("UPDATE document_submissions SET warehouse_receiver=?, warehouse_received_at=?, warehouse_doc_no=? WHERE id=?");
            $stmt->bind_param("sssi", $current_user, $now, $wh_doc, $id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'update_bill_pay') {
            $tax_no = $_POST['tax_invoice_no'];
            $bill_no = $_POST['billing_doc_no'];
            $bill_date = !empty($_POST['billing_date']) ? $_POST['billing_date'] : NULL;
            $pay_due = !empty($_POST['payment_due_date']) ? $_POST['payment_due_date'] : NULL;
            $pay_status = $_POST['payment_status'];

            $stmt = $conn->prepare("UPDATE document_submissions SET tax_invoice_no=?, billing_doc_no=?, billing_date=?, payment_due_date=?, payment_status=?, bill_updater_name=?, bill_updated_at=? WHERE id=?");
            $stmt->bind_param("sssssssi", $tax_no, $bill_no, $bill_date, $pay_due, $pay_status, $current_user, $now, $id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'edit_doc') {
            // ✅ เก็บประวัติการแก้ไข (Audit Trail)
            $doc_no = $_POST['doc_number'];
            $job = $_POST['job_site'];
            $supp = $_POST['supplier_name'];
            $desc = $_POST['description'];
            $amt = str_replace(',', '', $_POST['amount']);

            // 1. ดึงข้อมูลเก่าก่อนอัปเดต
            $stmt_old = $conn->prepare("SELECT doc_number, job_site, supplier_name, description, amount FROM document_submissions WHERE id = ?");
            $stmt_old->bind_param("i", $id);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();
            $stmt_old->close();

            // 2. เปรียบเทียบหาความเปลี่ยนแปลง
            $changes = [];
            if ($old_data['doc_number'] != $doc_no) {
                $changes[] = "เลขที่เอกสาร: '{$old_data['doc_number']}' -> '$doc_no'";
            }
            if ($old_data['job_site'] != $job) {
                $changes[] = "หน้างาน: '{$old_data['job_site']}' -> '$job'";
            }
            if ($old_data['supplier_name'] != $supp) {
                $changes[] = "ผู้ขาย: '{$old_data['supplier_name']}' -> '$supp'";
            }
            if ($old_data['description'] != $desc) {
                $changes[] = "รายละเอียด: '{$old_data['description']}' -> '$desc'";
            }
            if (floatval($old_data['amount']) != floatval($amt)) {
                $old_fmt = number_format($old_data['amount'], 2);
                $new_fmt = number_format($amt, 2);
                $changes[] = "ยอดเงิน: '$old_fmt' -> '$new_fmt'";
            }

            // 3. อัปเดตข้อมูลตามปกติ
            $stmt = $conn->prepare("UPDATE document_submissions SET doc_number=?, job_site=?, supplier_name=?, description=?, amount=? WHERE id=?");
            $stmt->bind_param("ssssdi", $doc_no, $job, $supp, $desc, $amt, $id);
            $stmt->execute();
            $stmt->close();

            // 4. บันทึกประวัติลง document_logs ถ้ามีการเปลี่ยนแปลง
            if (!empty($changes)) {
                $log_details = implode("\n", $changes);
                $log_action = 'edit';

                $stmt_log = $conn->prepare("INSERT INTO document_logs (doc_id, action_type, action_by, action_at, details) VALUES (?, ?, ?, ?, ?)");
                $stmt_log->bind_param("issss", $id, $log_action, $current_user, $now, $log_details);
                $stmt_log->execute();
                $stmt_log->close();
            }

            $_SESSION['success_msg'] = "แก้ไขข้อมูลเรียบร้อยแล้ว";

        } elseif ($action == 'cancel_doc') {
            // ✅ เก็บประวัติการยกเลิก (Void Log)
            $doc_id = intval($_POST['doc_id']);
            $reason = trim($_POST['cancel_reason']);
            $who = $_SESSION['fullname'] ?? 'Unknown';
            $when = date('Y-m-d H:i:s');

            $stmt = $conn->prepare("UPDATE document_submissions SET is_cancelled = 1, cancelled_by = ?, cancelled_at = ?, cancel_reason = ? WHERE id = ?");
            $stmt->bind_param("sssi", $who, $when, $reason, $doc_id);
            $stmt->execute();
            $stmt->close();

            // บันทึก Log การยกเลิก
            $log_action = 'cancel';
            $log_details = "ระบุสาเหตุ: " . $reason;
            $stmt_log = $conn->prepare("INSERT INTO document_logs (doc_id, action_type, action_by, action_at, details) VALUES (?, ?, ?, ?, ?)");
            $stmt_log->bind_param("issss", $doc_id, $log_action, $who, $when, $log_details);
            $stmt_log->execute();
            $stmt_log->close();

            echo "Cancelled";
            exit;

        } elseif ($action == 'get_history') {
            // ✅ ส่วนดึงประวัติ (รองรับข้อมูลทุกฟิลด์: บัญชี, บริษัท, ยอดเงิน ฯลฯ)
            $doc_id = intval($_POST['doc_id']);

            $sql_log = "SELECT * FROM document_logs WHERE doc_id = ? ORDER BY action_at DESC";
            $stmt = $conn->prepare($sql_log);
            $stmt->bind_param("i", $doc_id);
            $stmt->execute();
            $res_log = $stmt->get_result();

            if ($res_log->num_rows > 0) {
                echo '<table class="table-history">';
                echo '<thead>
                        <tr>
                            <th width="18%">วัน/เวลา</th>
                            <th width="18%">ผู้ทำรายการ</th>
                            <th width="12%">สถานะ</th>
                            <th width="52%">รายละเอียดการเปลี่ยนแปลง</th>
                        </tr>
                      </thead>';
                echo '<tbody>';

                while ($log = $res_log->fetch_assoc()) {
                    $date_show = date('d/m/Y', strtotime($log['action_at']));
                    $time_show = date('H:i', strtotime($log['action_at']));

                    // กำหนดสีป้าย (Badge)
                    $is_cancel = ($log['action_type'] == 'cancel');
                    $badge_class = $is_cancel ? 'badge-cancel' : 'badge-edit';
                    $badge_text = $is_cancel ? 'ยกเลิก' : 'แก้ไข';

                    echo '<tr>';

                    // 1. วันที่
                    echo '<td>
                            <div style="font-weight:600; color:#334155;">' . $date_show . '</div>
                            <div style="font-size:11px; color:#94a3b8;">' . $time_show . ' น.</div>
                          </td>';

                    // 2. ผู้ทำ
                    echo '<td>
                            <div style="font-weight:500; color:#475569;">
                                <i class="fas fa-user-circle" style="color:#cbd5e1; margin-right:3px;"></i> ' .
                        htmlspecialchars($log['action_by']) .
                        '</div>
                          </td>';

                    // 3. สถานะ
                    echo '<td><span class="history-badge ' . $badge_class . '">' . $badge_text . '</span></td>';

                    // 4. รายละเอียด (Parse ค่าเก่า -> ค่าใหม่)
                    echo '<td>';
                    echo '<ul class="detail-list">';

                    // แยกบรรทัด (เพราะเราบันทึกด้วย \n)
                    $detail_lines = explode("\n", $log['details']);

                    foreach ($detail_lines as $line) {
                        // เช็คว่าเป็นรูปแบบ "Field: 'Old' -> 'New'" หรือไม่
                        if (strpos($line, '->') !== false) {
                            // แยกชื่อฟิลด์ (ก่อน :) และค่า (หลัง :)
                            $parts = explode(':', $line, 2);
                            $field_name = isset($parts[0]) ? trim($parts[0]) : '';
                            $values = isset($parts[1]) ? $parts[1] : '';

                            // แยกค่าเก่า -> ค่าใหม่
                            $val_parts = explode('->', $values);
                            // ลบ ' ออกเพื่อให้ดูสะอาดตา
                            $old_val = isset($val_parts[0]) ? trim(str_replace("'", "", $val_parts[0])) : '-';
                            $new_val = isset($val_parts[1]) ? trim(str_replace("'", "", $val_parts[1])) : '-';

                            echo '<li>
                                    <strong>' . htmlspecialchars($field_name) . ':</strong> 
                                    <span class="val-old">' . htmlspecialchars($old_val) . '</span>
                                    <i class="fas fa-arrow-right val-arrow"></i>
                                    <span class="val-new">' . htmlspecialchars($new_val) . '</span>
                                  </li>';
                        } else {
                            // กรณีข้อความทั่วไป (เช่น เหตุผลการยกเลิก หรือ การเปลี่ยนไฟล์แนบ)
                            echo '<li>' . htmlspecialchars($line) . '</li>';
                        }
                    }
                    echo '</ul>';
                    echo '</td>';

                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';

            } else {
                echo '<div style="text-align:center; color:#94a3b8; padding:40px;">';
                echo '<div style="background:#f1f5f9; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">';
                echo '<i class="fas fa-history" style="font-size:24px; color:#cbd5e1;"></i>';
                echo '</div>';
                echo 'ยังไม่มีประวัติการแก้ไข';
                echo '</div>';
            }
            exit;
        }
        // ถ้าทำงานสำเร็จ ให้ Redirect กลับหน้าเดิม
        header("Location: DocumentDashboard.php?" . $_SERVER['QUERY_STRING']);
        exit();

    } catch (Exception $e) {
        // 🔴 เติม id="server-error-box" ตรงนี้ เพื่อให้ JS รู้จัก
        echo '<div id="server-error-box" style="font-family: sans-serif; padding: 20px; background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; margin: 20px; border-radius: 8px;">';
        echo '<h2 style="margin-top:0;">❌ เกิดข้อผิดพลาด (System Error)</h2>';
        echo '<p>ระบบไม่สามารถบันทึกข้อมูลได้ เนื่องจากสาเหตุดังนี้:</p>';
        echo '<pre style="background: #fff; padding: 15px; border-radius: 5px; color: #dc2626; font-weight: bold; overflow-x: auto;">';
        echo $e->getMessage();
        echo '</pre>';
        echo '<p><strong>ตำแหน่งที่เกิด Error:</strong> ' . $e->getFile() . ' บรรทัดที่ ' . $e->getLine() . '</p>';
        echo '<button onclick="window.history.back()" style="padding: 10px 20px; cursor: pointer;">⬅️ กลับไปแก้ไข</button>';
        echo '</div>';
        exit();
    }
}

// --- 2. Prepare Filters (Escape Inputs for Safety) ---
$filter_user = isset($_GET['user']) ? $conn->real_escape_string($_GET['user']) : '';
$start_date = "";
$end_date = "";

// 1. เช็คว่ากด "แสดงทั้งหมด" หรือไม่?
$is_show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

if ($is_show_all) {
    // ถ้ากดแสดงทั้งหมด -> ล้างค่าวันที่ให้ว่าง
    $start_date = "";
    $end_date = "";
}
// 2. ถ้ามีการกดค้นหาและเลือกวันที่มา
elseif (isset($_GET['start_date']) && $_GET['start_date'] != "") {
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : "";
}
// 3. (ค่าเริ่มต้น) เข้าเว็บครั้งแรก หรือ กดรีเซ็ต -> ตั้งเป็นเดือนปัจจุบัน
else {
    $start_date = date('Y-m-01'); // วันที่ 1 ของเดือน
    $end_date = date('Y-m-t');    // วันสุดท้ายของเดือน
}
$amount_range = isset($_GET['amount_range']) ? $_GET['amount_range'] : '';
$filter_company_str = isset($_GET['company_id']) ? $_GET['company_id'] : '';
$filter_urgent = isset($_GET['filter_urgent']) ? $_GET['filter_urgent'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_keyword = isset($_GET['filter_keyword']) ? $conn->real_escape_string($_GET['filter_keyword']) : '';
$filter_credit_alert = isset($_GET['filter_credit_alert']) ? $_GET['filter_credit_alert'] : '';
$filter_credit_status = $_GET['filter_credit_status'] ?? '';
$filter_doc_type = isset($_GET['doc_type']) ? $conn->real_escape_string($_GET['doc_type']) : '';
$search_keyword = isset($_GET['search_all']) ? trim($_GET['search_all']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT * FROM document_submissions d WHERE 1=1";


// 2.4 ใส่เงื่อนไขตัวกรองอื่นๆ (ถ้ามี)
if ($status_filter !== 'all') {
    // ตัวอย่าง: ถ้าเลือกสถานะ ให้กรองตามนั้น
    // $kw_status = $conn->real_escape_string($status_filter);
    // $sql .= " AND status = '$kw_status'";
}

// 2.5 เรียงลำดับจากใหม่ไปเก่า
$sql .= " ORDER BY d.is_cancelled ASC, d.created_at DESC";

// 2.6 รันคำสั่ง SQL เพื่อเอาผลลัพธ์ใส่ตัวแปร $result
$result = $conn->query($sql);
$selected_companies = [];
if ($filter_company_str != '') {
    $selected_companies = explode(',', $filter_company_str);
}

$where_clauses = [];
if ($amount_range == 'low') {
    // ช่วง 0 - 10,000
    $where_clauses[] = "d.amount <= 10000";
} elseif ($amount_range == 'medium') {
    // ช่วง 10,001 - 100,000
    $where_clauses[] = "(d.amount > 10000 AND d.amount <= 100000)";
} elseif ($amount_range == 'high') {
    // ช่วง 100,001 ขึ้นไป
    $where_clauses[] = "d.amount > 100000";
}
if (!empty($filter_doc_type)) {
    $where_clauses[] = "d.doc_type = '$filter_doc_type'";
}
if ($filter_credit_status == 'paid') {
    // กรองเฉพาะ จ่ายแล้ว (และต้องมีเครดิต)
    $where_clauses[] = "d.credit_term > 0 AND d.payment_status = 'จ่ายแล้ว (Complete)'";
} elseif ($filter_credit_status == 'unpaid') {
    // กรองเฉพาะ ค้างจ่าย (และต้องมีเครดิต)
    $where_clauses[] = "d.credit_term > 0 AND (d.payment_status IS NULL OR d.payment_status != 'จ่ายแล้ว (Complete)')";
}
// กรองเครดิตใกล้หมด
if ($filter_credit_alert == '1') {
    // 1. ต้องเป็นงานที่มีเครดิต
    $where_clauses[] = "d.credit_term IS NOT NULL AND d.credit_term > 0";

    // 2. ต้องยังไม่จ่ายเงิน
    $where_clauses[] = "(d.payment_status IS NULL OR d.payment_status != 'จ่ายแล้ว (Complete)')";

    // 3. สูตรคำนวณวันครบกำหนด (แก้ไขบรรทัดนี้)
    // ความหมาย: (วันที่สร้าง + เครดิต) ต้องน้อยกว่าหรือเท่ากับ (วันนี้ + 10 วัน)
    // ผลลัพธ์: จะโชว์รายการที่ "เกินกำหนดแล้ว" และ "เหลือเวลาอีก 0-10 วัน"
    $where_clauses[] = "DATE_ADD(d.created_at, INTERVAL d.credit_term DAY) <= DATE_ADD(NOW(), INTERVAL 10 DAY)";

    // 4. ไม่ใช่รายการยกเลิก
    $where_clauses[] = "(d.is_cancelled = 0 OR d.is_cancelled IS NULL)";
}

if (!empty($search_keyword)) {
    $kw = $conn->real_escape_string($search_keyword);
    $where_clauses[] = "(
        d.doc_number LIKE '%$kw%' OR 
        d.supplier_name LIKE '%$kw%' OR 
        d.job_site LIKE '%$kw%' OR
        d.description LIKE '%$kw%' OR
        d.amount LIKE '%$kw%'
    )";
}
// กรอง Keyword (ใช้ตัวแปรที่ escape แล้ว)
if (!empty($filter_keyword)) {
    if ($filter_keyword == 'ใบกำกับภาษี 7%') {
        $where_clauses[] = "(d.attachments LIKE '%ใบกำกับภาษี 7%%' AND d.attachments NOT LIKE '%รอใบกำกับภาษี%')";
    } else {
        $where_clauses[] = "d.attachments LIKE '%$filter_keyword%'";
    }
}

// ... ส่วนเงื่อนไข Status อื่นๆ เหมือนเดิม (เพราะเป็น Hardcode string ปลอดภัยอยู่แล้ว) ...
if ($filter_status == 'pending_approve') {
    // 🟢 เพิ่ม: ต้องไม่ยกเลิก
    $where_clauses[] = "(d.approver_name IS NULL OR d.approver_name = '') AND (d.is_cancelled = 0 OR d.is_cancelled IS NULL)";

} elseif ($filter_status == 'approved') {
    // 🟢 เพิ่ม: ต้องไม่ยกเลิก
    $where_clauses[] = "(d.approver_name IS NOT NULL AND d.approver_name != '') AND (d.is_cancelled = 0 OR d.is_cancelled IS NULL)";

} elseif ($filter_status == 'pending_finance') {
    // 🟢 เพิ่ม: ต้องไม่ยกเลิก
    $where_clauses[] = "(d.finance_receiver IS NULL OR d.finance_receiver = '') AND (d.is_cancelled = 0 OR d.is_cancelled IS NULL)";

} elseif ($filter_status == 'finance_received') {
    // 🟢 เพิ่ม: ต้องไม่ยกเลิก
    $where_clauses[] = "(d.finance_receiver IS NOT NULL AND d.finance_receiver != '') AND (d.is_cancelled = 0 OR d.is_cancelled IS NULL)";
} elseif ($filter_status == 'returned') {
    // กรองเฉพาะรายการที่มีคนกดตีกลับ
    $where_clauses[] = "d.return_doc_by IS NOT NULL AND d.return_doc_by != ''";

} elseif ($filter_status == 'cancelled') {
    // อันนี้คงเดิม (เพราะเราต้องการดูตัวยกเลิก)
    $where_clauses[] = "d.is_cancelled = 1";
}

if ($filter_urgent == '1') {
    $where_clauses[] = "d.attachments LIKE '%รอใบกำกับภาษี 7%%'";
    $where_clauses[] = "(d.is_cancelled = 0 OR d.is_cancelled IS NULL)";
}
if (!empty($selected_companies)) {
    // Escape ID แต่ละตัวเพื่อความชัวร์
    $safe_ids = array_map(function ($id) use ($conn) {
        return "'" . $conn->real_escape_string($id) . "'";
    }, $selected_companies);
    $id_list = implode(',', $safe_ids);
    $where_clauses[] = "d.company_id IN ($id_list)";
}
if (!empty($filter_user)) {
    $where_clauses[] = "d.ordered_by = '$filter_user'";
}
if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "d.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
}

$table_where = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";
$kpi_join_condition = count($where_clauses) > 0 ? " AND " . implode(' AND ', $where_clauses) : "";
$kpi_join_condition .= " AND (d.is_cancelled = 0 OR d.is_cancelled IS NULL)";

// --- 3. Fetch KPI Data (แก้ไขสมบูรณ์) ---
$comp_totals = [];
$total_all_money = 0;
$total_all_jobs = 0;

// ประกาศตัวแปรนับจำนวนเริ่มต้น
$grand_total_pv = 0;
$grand_total_vat = 0;
$grand_total_wait = 0;
$grand_total_credit_paid = 0;
$grand_total_credit_unpaid = 0;
$grand_total_pending_app = 0;
$grand_total_approved = 0;
$grand_total_pending_fin = 0;
$grand_total_received = 0;

// ประกาศตัวแปรยอดเงินเริ่มต้น (ป้องกัน Error Undefined Variable)
$grand_total_pv_amt = 0;
$grand_total_vat_amt = 0;
$grand_total_wait_amt = 0;
$grand_total_credit_paid_amt = 0;
$grand_total_credit_unpaid_amt = 0;
$grand_total_receipt = 0;
$grand_total_receipt_amt = 0;
$grand_total_payment = 0;
$grand_total_payment_amt = 0;

// SQL Query: เพิ่มการคำนวณ amount_credit_paid และ amount_credit_unpaid เข้าไปใน SELECT
$sql_kpi = "SELECT c.id, c.company_name, c.logo_file, 
            COUNT(d.id) as job_count, 
            COALESCE(SUM(d.amount), 0) as total_money,
            SUM(CASE WHEN d.id IS NOT NULL AND (d.approver_name IS NULL OR d.approver_name = '') THEN 1 ELSE 0 END) as count_pending_app,
            SUM(CASE WHEN d.id IS NOT NULL AND (d.approver_name IS NOT NULL AND d.approver_name != '') THEN 1 ELSE 0 END) as count_approved,
            SUM(CASE WHEN d.id IS NOT NULL AND (d.finance_receiver IS NULL OR d.finance_receiver = '') THEN 1 ELSE 0 END) as count_pending_fin,
            SUM(CASE WHEN d.id IS NOT NULL AND (d.finance_receiver IS NOT NULL AND d.finance_receiver != '') THEN 1 ELSE 0 END) as count_received,
            SUM(CASE WHEN d.return_doc_by IS NOT NULL AND d.return_doc_by != '' THEN 1 ELSE 0 END) as count_returned,
            SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%รอใบกำกับภาษี 7%%' THEN 1 ELSE 0 END) as count_urgent,
            SUM(CASE WHEN 
                d.credit_term > 0 
                AND (d.payment_status IS NULL OR d.payment_status != 'จ่ายแล้ว (Complete)') 
                AND DATE_ADD(d.created_at, INTERVAL d.credit_term DAY) <= DATE_ADD(NOW(), INTERVAL 10 DAY)
            THEN 1 ELSE 0 END) as count_credit_alert,
            -- ส่วนใบสำคัญจ่าย
            SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%ใบสำคัญจ่าย%' THEN 1 ELSE 0 END) as count_pv,
            COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%ใบสำคัญจ่าย%' THEN d.amount ELSE 0 END), 0) as amount_pv,
            
            -- ส่วนใบกำกับภาษี
            SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%ใบกำกับภาษี 7%%' AND d.attachments NOT LIKE '%รอใบกำกับภาษี%' THEN 1 ELSE 0 END) as count_vat,
            COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%ใบกำกับภาษี 7%%' AND d.attachments NOT LIKE '%รอใบกำกับภาษี%' THEN d.amount ELSE 0 END), 0) as amount_vat,
            
            -- ส่วนรอใบกำกับ
            SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%รอใบกำกับภาษี 7%%' THEN 1 ELSE 0 END) as count_wait,
            COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%รอใบกำกับภาษี 7%%' THEN d.amount ELSE 0 END), 0) as amount_wait,
            SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%ใบเสร็จรับเงิน%' THEN 1 ELSE 0 END) as count_receipt,
            COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%ใบเสร็จรับเงิน%' THEN d.amount ELSE 0 END), 0) as amount_receipt,

            SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%ชุดชำระเงิน%' THEN 1 ELSE 0 END) as count_payment,
            COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND d.attachments LIKE '%ชุดชำระเงิน%' THEN d.amount ELSE 0 END), 0) as amount_payment,
            
            -- ส่วนเครดิต (นับจำนวน)
            SUM(CASE WHEN d.credit_term > 0 AND d.payment_status = 'จ่ายแล้ว (Complete)' THEN 1 ELSE 0 END) as count_credit_paid,
            SUM(CASE WHEN d.credit_term > 0 AND (d.payment_status IS NULL OR d.payment_status != 'จ่ายแล้ว (Complete)') THEN 1 ELSE 0 END) as count_credit_unpaid,

            -- 🟢 ส่วนเครดิต (ยอดเงิน - ที่ขาดไปก่อนหน้านี้)
            COALESCE(SUM(CASE WHEN d.credit_term > 0 AND d.payment_status = 'จ่ายแล้ว (Complete)' THEN d.amount ELSE 0 END), 0) as amount_credit_paid,
            COALESCE(SUM(CASE WHEN d.credit_term > 0 AND (d.payment_status IS NULL OR d.payment_status != 'จ่ายแล้ว (Complete)') THEN d.amount ELSE 0 END), 0) as amount_credit_unpaid

            FROM companies c
            LEFT JOIN document_submissions d ON c.id = d.company_id $kpi_join_condition
            GROUP BY c.id, c.company_name, c.logo_file
            ORDER BY c.list_order ASC";

$q_kpi = $conn->query($sql_kpi);

if ($q_kpi) {
    while ($row = $q_kpi->fetch_assoc()) {
        $comp_totals[] = $row;
        // บวกค่าสะสม
        $total_all_money += $row['total_money'];
        $total_all_jobs += $row['job_count'];

        $grand_total_pv += $row['count_pv'];
        $grand_total_pv_amt += $row['amount_pv'];
        $grand_total_receipt += $row['count_receipt'];
        $grand_total_receipt_amt += $row['amount_receipt'];

        $grand_total_payment += $row['count_payment'];
        $grand_total_payment_amt += $row['amount_payment'];

        $grand_total_vat += $row['count_vat'];
        $grand_total_vat_amt += $row['amount_vat'];

        $grand_total_wait += $row['count_wait'];
        $grand_total_wait_amt += $row['amount_wait'];

        $grand_total_credit_paid += $row['count_credit_paid'];
        $grand_total_credit_unpaid += $row['count_credit_unpaid'];

        // 🟢 บรรทัดนี้จะทำงานได้ถูกต้อง เพราะใน SQL มี field นี้แล้ว
        $grand_total_credit_paid_amt += $row['amount_credit_paid'];
        $grand_total_credit_unpaid_amt += $row['amount_credit_unpaid'];

        $grand_total_pending_app += $row['count_pending_app'];
        $grand_total_approved += $row['count_approved'];
        $grand_total_pending_fin += $row['count_pending_fin'];
        $grand_total_received += $row['count_received'];
        $grand_total_returned = isset($grand_total_returned) ? $grand_total_returned + $row['count_returned'] : $row['count_returned'];
        $grand_total_urgent = isset($grand_total_urgent) ? $grand_total_urgent + $row['count_urgent'] : $row['count_urgent'];
        $grand_total_credit_alert = isset($grand_total_credit_alert) ? $grand_total_credit_alert + $row['count_credit_alert'] : $row['count_credit_alert'];
    }
} else {
    // กรณี SQL Error ให้แสดงออกมาดู (ลบออกได้ภายหลัง)
    echo "SQL Error: " . $conn->error;
}

// --- 4. Fetch Documents ---
$sql_docs = "SELECT d.*, c.company_shortname 
             FROM document_submissions d
             LEFT JOIN companies c ON d.company_id = c.id
             $table_where
             ORDER BY d.created_at DESC";
$docs = $conn->query($sql_docs);

if (isset($_GET['ajax_search'])) {
    if ($docs->num_rows > 0) {
        while ($row = $docs->fetch_assoc()) {
            // ⚠️ สำคัญ: ก๊อปปี้ HTML ใน <tr>...</tr> จากตารางด้านล่างมาใส่ตรงนี้
            // ตัวอย่าง (คุณต้องปรับให้ตรงกับคอลัมน์ของคุณ):
            ?>
            <tr class="hover:bg-gray-50 transition">
                <td class="p-3"><?php echo htmlspecialchars($row['doc_number']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($row['company_shortname']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($row['job_site']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($row['description']); ?></td>
                <td class="p-3 text-right font-bold text-blue-600"><?php echo number_format($row['amount'], 2); ?></td>
                <td class="p-3">
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="20" style="text-align:center; padding:20px; color:#999;">ไม่พบข้อมูลที่ค้นหา</td></tr>';
    }
    exit; // 🛑 หยุดการทำงานทันที (เพื่อไม่ให้โหลด Header/Footer ซ้ำ)
}

$users_list = [];
$q_u = $conn->query("SELECT DISTINCT ordered_by FROM document_submissions WHERE ordered_by IS NOT NULL AND ordered_by != '' ORDER BY ordered_by ASC");
while ($r = $q_u->fetch_assoc()) {
    $users_list[] = $r['ordered_by'];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title>Dashboard ทะเบียนคุมเอกสาร</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <link rel="stylesheet" href="css/DocumentDashboard.css">

    <script src="js/DocumentDashboard.js"></script>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">

        <div id="content-area">
            <?php
            function buildMergeUrl($newParams = [])
            {
                $params = $_GET; // ดึงค่าค้นหาเดิมทั้งหมด (เช่น วันที่)
                foreach ($newParams as $key => $value) {
                    if ($value === null)
                        unset($params[$key]); // ถ้าส่ง null คือลบค่าออก
                    else
                        $params[$key] = $value; // ถ้ามีค่าใหม่ ให้ทับค่าเดิม
                }
                return '?' . http_build_query($params);
            }
            ?>
            <div class="total-section">
                <div class="total-summary-card">
                    <div class="total-main">
                        <div class="total-icon-box"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <span class="total-label-text">ยอดรวมสุทธิ (ทั้งหมด)</span>
                            <div class="total-amount-text">
                                <span id="sum_total_money"><?php echo number_format($total_all_money, 2); ?></span>
                                <span style="font-size:18px;">฿</span>
                            </div>
                            <div class="total-sub-text">
                                รวม <span id="sum_total_jobs"><?php echo number_format($total_all_jobs); ?></span>
                                รายการ
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px; width:100%;">
                        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px;">
                            <a href="<?php echo buildMergeUrl(['status' => 'pending_approve']); ?>"
                                class="gt-box ajax-link"
                                style="background:rgba(245, 158, 11, 0.15); border-color:rgba(245, 158, 11, 0.3);">
                                <i class="fas fa-signature gt-icon" style="color:#f59e0b;"></i>
                                <div class="gt-title">รออนุมัติ</div>
                                <div class="gt-val"><?php echo number_format($grand_total_pending_app); ?></div>
                            </a>
                            <a href="<?php echo buildMergeUrl(['status' => 'approved']); ?>" class="gt-box ajax-link"
                                style="background:rgba(16, 185, 129, 0.15); border-color:rgba(16, 185, 129, 0.3);">
                                <i class="fas fa-check-circle gt-icon" style="color:#10b981;"></i>
                                <div class="gt-title">อนุมัติแล้ว</div>
                                <div class="gt-val"><?php echo number_format($grand_total_approved); ?></div>
                            </a>
                            <a href="<?php echo buildMergeUrl(['status' => 'pending_finance']); ?>"
                                class="gt-box ajax-link"
                                style="background:rgba(59, 130, 246, 0.15); border-color:rgba(59, 130, 246, 0.3);">
                                <i class="fas fa-clock gt-icon" style="color:#3b82f6;"></i>
                                <div class="gt-title">รอการเงิน</div>
                                <div class="gt-val"><?php echo number_format($grand_total_pending_fin); ?></div>
                            </a>
                            <a href="<?php echo buildMergeUrl(['status' => 'finance_received']); ?>"
                                class="gt-box ajax-link"
                                style="background:rgba(139, 92, 246, 0.15); border-color:rgba(139, 92, 246, 0.3);">
                                <i class="fas fa-wallet gt-icon" style="color:#8b5cf6;"></i>
                                <div class="gt-title">รับเอกสารแล้ว</div>
                                <div class="gt-val"><?php echo number_format($grand_total_received); ?></div>
                            </a>
                        </div>

                        <div class="grand-total-grid">
                            <a href="<?php echo buildMergeUrl(['filter_keyword' => 'ใบสำคัญจ่าย']); ?>"
                                class="gt-box ajax-link">
                                <i class="fas fa-file-invoice-dollar gt-icon"></i>
                                <div class="gt-title">ใบสำคัญจ่าย</div>
                                <div class="gt-val" id="gt_pv_count"><?php echo number_format($grand_total_pv); ?></div>
                                <div class="gt-sub"><span
                                        id="gt_pv_amt"><?php echo number_format($grand_total_pv_amt); ?></span> บ.</div>
                            </a>

                            <a href="<?php echo buildMergeUrl(['filter_keyword' => 'ใบกำกับภาษี 7%']); ?>"
                                class="gt-box ajax-link">
                                <i class="fas fa-check-circle gt-icon"></i>
                                <div class="gt-title">ใบกำกับ 7%</div>
                                <div class="gt-val" id="gt_vat_count"><?php echo number_format($grand_total_vat); ?>
                                </div>
                                <div class="gt-sub"><span
                                        id="gt_vat_amt"><?php echo number_format($grand_total_vat_amt); ?></span> บ.
                                </div>
                            </a>

                            <a href="<?php echo buildMergeUrl(['filter_keyword' => 'รอใบกำกับภาษี 7%']); ?>"
                                class="gt-box ajax-link">
                                <i class="fas fa-history gt-icon"></i>
                                <div class="gt-title">รอใบกำกับ</div>
                                <div class="gt-val" id="gt_wait_count"><?php echo number_format($grand_total_wait); ?>
                                </div>
                                <div class="gt-sub"><span
                                        id="gt_wait_amt"><?php echo number_format($grand_total_wait_amt); ?></span> บ.
                                </div>
                            </a>

                            <a href="<?php echo buildMergeUrl(['filter_keyword' => 'ใบเสร็จรับเงิน']); ?>"
                                class="gt-box ajax-link">
                                <i class="fas fa-receipt gt-icon" style="color: #06b6d4;"></i>
                                <div class="gt-title">ใบเสร็จรับเงิน</div>
                                <div class="gt-val"><?php echo number_format($grand_total_receipt); ?></div>
                                <div class="gt-sub"><span><?php echo number_format($grand_total_receipt_amt); ?></span>
                                    บ.</div>
                            </a>

                            <a href="<?php echo buildMergeUrl(['filter_keyword' => 'ชุดชำระเงิน']); ?>"
                                class="gt-box ajax-link">
                                <i class="fas fa-money-check-alt gt-icon" style="color: #a855f7;"></i>
                                <div class="gt-title">ชุดชำระเงิน</div>
                                <div class="gt-val"><?php echo number_format($grand_total_payment); ?></div>
                                <div class="gt-sub"><span><?php echo number_format($grand_total_payment_amt); ?></span>
                                    บ.</div>
                            </a>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                            <?php
                            // (คง Logic CSS เดิมไว้)
                            $style_paid = ($filter_credit_status == 'paid') ? 'border:2px solid #10b981; background:rgba(16, 185, 129, 0.25); box-shadow: 0 0 10px rgba(16, 185, 129, 0.2);' : 'border-color:rgba(16, 185, 129, 0.3); background:rgba(16, 185, 129, 0.15);';
                            $style_unpaid = ($filter_credit_status == 'unpaid') ? 'border:2px solid #f59e0b; background:rgba(245, 158, 11, 0.25); box-shadow: 0 0 10px rgba(245, 158, 11, 0.2);' : 'border-color:rgba(245, 158, 11, 0.3); background:rgba(245, 158, 11, 0.15);';
                            ?>
                            <a href="<?php echo buildMergeUrl(['filter_credit_status' => 'paid']); ?>"
                                class="gt-box ajax-link" style="<?php echo $style_paid; ?>">
                                <div style="display:flex; align-items:center; gap:5px; justify-content:center;">
                                    <i class="fas fa-check-double gt-icon" style="color:#10b981; margin:0;"></i>
                                    <div class="gt-title" style="margin:0;">เครดิต (จ่ายแล้ว)</div>
                                </div>
                                <div class="gt-val" style="color:#10b981; margin-top:2px;">
                                    <?php echo number_format($grand_total_credit_paid); ?>
                                </div>
                                <div class="gt-sub" style="color:#10b981; opacity:0.9; font-weight:500;">
                                    <?php echo number_format($grand_total_credit_paid_amt, 2); ?> บ.
                                </div>
                            </a>
                            <a href="<?php echo buildMergeUrl(['filter_credit_status' => 'unpaid']); ?>"
                                class="gt-box ajax-link" style="<?php echo $style_unpaid; ?>">
                                <div style="display:flex; align-items:center; gap:5px; justify-content:center;">
                                    <i class="fas fa-clock gt-icon" style="color:#f59e0b; margin:0;"></i>
                                    <div class="gt-title" style="margin:0;">เครดิต (ค้างจ่าย)</div>
                                </div>
                                <div class="gt-val" style="color:#f59e0b; margin-top:2px;">
                                    <?php echo number_format($grand_total_credit_unpaid); ?>
                                </div>
                                <div class="gt-sub" style="color:#f59e0b; opacity:0.9; font-weight:500;">
                                    <?php echo number_format($grand_total_credit_unpaid_amt, 2); ?> บ.
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kpi-grid">
                <?php
                // Function helper
                if (!function_exists('makeLink')) {
                    function makeLink($params, $type = null, $val = null)
                    {
                        $newParams = ['company_id' => $params['company_id']];
                        if ($type == 'status')
                            $newParams['status'] = $val;
                        elseif ($type == 'keyword')
                            $newParams['filter_keyword'] = $val;
                        return "?" . http_build_query($newParams);
                    }
                }

                foreach ($comp_totals as $c):
                    $cid = $c['id'];
                    $isActive = in_array($cid, $selected_companies);
                    $openClass = $isActive ? 'open active' : '';
                    $temp_selected = $isActive ? array_diff($selected_companies, [$cid]) : array_merge($selected_companies, [$cid]);


                    // ✅ 4. แก้บรรทัดนี้: ใช้ buildMergeUrl แทนการเขียนสตริงเอง
                    $filterLink = buildMergeUrl(['company_id' => implode(',', $temp_selected)]);
                    ?>
                    <div class="kpi-card <?php echo $openClass; ?>" id="card_comp_<?php echo $cid; ?>">
                        <div class="card-header-section">

                            <div class="header-info-group" onclick="loadWithAjax('<?php echo $filterLink; ?>')"
                                style="display:flex; align-items:flex-start; gap:12px; flex:1; cursor:pointer;">

                                <div class="kpi-img-wrapper">
                                    <?php if (!empty($c['logo_file']) && file_exists('uploads/logos/' . $c['logo_file'])): ?>
                                        <img src="uploads/logos/<?php echo $c['logo_file']; ?>" alt="Logo" class="kpi-img">
                                    <?php else: ?>
                                        <i class="fas fa-building" style="font-size:20px; color:var(--text-light);"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight:700; font-size:16px; color:var(--text-muted);">
                                        <?php echo $c['company_name']; ?>
                                    </div>
                                    <div style="font-weight:800; font-size:16px; color:var(--primary);">
                                        <span
                                            id="c_money_<?php echo $cid; ?>"><?php echo number_format($c['total_money'], 2); ?></span>
                                        <span style="font-size:12px;">฿</span>
                                    </div>
                                    <?php if ($isActive): ?><span
                                            style="font-size:10px; color:var(--success); font-weight:600;"><i
                                                class="fas fa-check-circle"></i> แสดงผลอยู่</span><?php endif; ?>
                                </div>
                            </div>

                            <div class="header-toggle-btn"
                                onclick="toggleCard(this.closest('.kpi-card')); event.stopPropagation();"
                                style="padding:5px; cursor:pointer;">
                                <i class="fas fa-chevron-down arrow-indicator"></i>
                            </div>
                        </div>

                        <div class="card-details-section">
                            <div class="status-grid-4">
                                <?php
                                $base_params = $_GET;
                                $base_params['company_id'] = $cid;
                                $url_p_app = '?' . http_build_query(array_merge($base_params, ['status' => 'pending_approve']));
                                $url_app = '?' . http_build_query(array_merge($base_params, ['status' => 'approved']));
                                $url_p_fin = '?' . http_build_query(array_merge($base_params, ['status' => 'pending_finance']));
                                $url_fin = '?' . http_build_query(array_merge($base_params, ['status' => 'finance_received']));
                                $is_current_comp_selected = in_array($cid, $selected_companies);
                                $act_p_app = ($filter_status == 'pending_approve' && $is_current_comp_selected) ? 'active' : '';
                                $act_app = ($filter_status == 'approved' && $is_current_comp_selected) ? 'active' : '';
                                $act_p_fin = ($filter_status == 'pending_finance' && $is_current_comp_selected) ? 'active' : '';
                                $act_fin = ($filter_status == 'finance_received' && $is_current_comp_selected) ? 'active' : '';
                                ?>
                                <a href="<?php echo $url_p_app; ?>"
                                    class="status-box-btn sb-pending <?php echo $act_p_app; ?>">
                                    <i class="fas fa-signature sb-icon-bg"></i>
                                    <div class="sb-label">รออนุมัติ</div>
                                    <div class="sb-count" id="c_p_app_<?php echo $cid; ?>">
                                        <?php echo $c['count_pending_app']; ?>
                                    </div>
                                </a>
                                <a href="<?php echo $url_app; ?>"
                                    class="status-box-btn sb-approved <?php echo $act_app; ?>">
                                    <i class="fas fa-check-circle sb-icon-bg"></i>
                                    <div class="sb-label">อนุมัติแล้ว</div>
                                    <div class="sb-count" id="c_app_<?php echo $cid; ?>"><?php echo $c['count_approved']; ?>
                                    </div>
                                </a>
                                <a href="<?php echo $url_p_fin; ?>"
                                    class="status-box-btn sb-fin-wait <?php echo $act_p_fin; ?>">
                                    <i class="fas fa-clock sb-icon-bg"></i>
                                    <div class="sb-label">รอการเงิน</div>
                                    <div class="sb-count" id="c_p_fin_<?php echo $cid; ?>">
                                        <?php echo $c['count_pending_fin']; ?>
                                    </div>
                                </a>
                                <a href="<?php echo $url_fin; ?>"
                                    class="status-box-btn sb-received <?php echo $act_fin; ?>">
                                    <i class="fas fa-wallet sb-icon-bg"></i>
                                    <div class="sb-label">รับเอกสารแล้ว</div>
                                    <div class="sb-count" id="c_fin_rcv_<?php echo $cid; ?>">
                                        <?php echo $c['count_received']; ?>
                                    </div>
                                </a>
                            </div>
                            <div class="section-divider"><span>ประเภทเอกสาร</span></div>
                            <div class="doc-type-grid">
                                <?php
                                $base_url = "?company_id=" . $cid;
                                // สร้าง URL สำหรับแต่ละปุ่ม
                                $url_pv = $base_url . "&filter_keyword=" . urlencode("ใบสำคัญจ่าย");
                                $url_vat = $base_url . "&filter_keyword=" . urlencode("ใบกำกับภาษี 7%");
                                $url_wait = $base_url . "&filter_keyword=" . urlencode("รอใบกำกับภาษี 7%");
                                $url_receipt = $base_url . "&filter_keyword=" . urlencode("ใบเสร็จรับเงิน");
                                $url_payment = $base_url . "&filter_keyword=" . urlencode("ชุดชำระเงิน");

                                // เช็คสถานะ Active
                                $act_pv = ($filter_keyword == 'ใบสำคัญจ่าย' && in_array($cid, $selected_companies)) ? 'active' : '';
                                $act_vat = ($filter_keyword == 'ใบกำกับภาษี 7%' && in_array($cid, $selected_companies)) ? 'active' : '';
                                $act_wait = ($filter_keyword == 'รอใบกำกับภาษี 7%' && in_array($cid, $selected_companies)) ? 'active' : '';
                                $act_receipt = ($filter_keyword == 'ใบเสร็จรับเงิน' && in_array($cid, $selected_companies)) ? 'active' : '';
                                $act_payment = ($filter_keyword == 'ชุดชำระเงิน' && in_array($cid, $selected_companies)) ? 'active' : '';
                                ?>

                                <a href="<?php echo $url_pv; ?>" class="dt-box dt-pv <?php echo $act_pv; ?>">
                                    <i class="fas fa-file-invoice-dollar dt-icon"></i>
                                    <div class="dt-text">ใบสำคัญจ่าย</div>
                                    <div class="dt-val" id="c_pv_cnt_<?php echo $cid; ?>"><?php echo $c['count_pv']; ?>
                                    </div>
                                    <div class="dt-amt"><span
                                            id="c_pv_amt_<?php echo $cid; ?>"><?php echo number_format($c['amount_pv']); ?></span>
                                    </div>
                                </a>

                                <a href="<?php echo $url_vat; ?>" class="dt-box dt-vat <?php echo $act_vat; ?>">
                                    <i class="fas fa-check-circle dt-icon"></i>
                                    <div class="dt-text">ใบกำกับ 7%</div>
                                    <div class="dt-val" id="c_vat_cnt_<?php echo $cid; ?>"><?php echo $c['count_vat']; ?>
                                    </div>
                                    <div class="dt-amt"><span
                                            id="c_vat_amt_<?php echo $cid; ?>"><?php echo number_format($c['amount_vat']); ?></span>
                                    </div>
                                </a>

                                <a href="<?php echo $url_wait; ?>" class="dt-box dt-wait <?php echo $act_wait; ?>">
                                    <i class="fas fa-history dt-icon"></i>
                                    <div class="dt-text">รอใบกำกับ</div>
                                    <div class="dt-val" id="c_wait_cnt_<?php echo $cid; ?>"><?php echo $c['count_wait']; ?>
                                    </div>
                                    <div class="dt-amt"><span
                                            id="c_wait_amt_<?php echo $cid; ?>"><?php echo number_format($c['amount_wait']); ?></span>
                                    </div>
                                </a>

                                <a href="<?php echo $url_receipt; ?>" class="dt-box dt-receipt <?php echo $act_receipt; ?>">
                                    <i class="fas fa-receipt dt-icon"></i>
                                    <div class="dt-text">ใบเสร็จ</div>
                                    <div class="dt-val" id="c_rcpt_cnt_<?php echo $cid; ?>">
                                        <?php echo $c['count_receipt']; ?>
                                    </div>
                                    <div class="dt-amt"><span
                                            id="c_rcpt_amt_<?php echo $cid; ?>"><?php echo number_format($c['amount_receipt']); ?></span>
                                    </div>
                                </a>

                                <a href="<?php echo $url_payment; ?>" class="dt-box dt-payment <?php echo $act_payment; ?>">
                                    <i class="fas fa-money-check-alt dt-icon"></i>
                                    <div class="dt-text">ชุดชำระ</div>
                                    <div class="dt-val" id="c_pay_cnt_<?php echo $cid; ?>">
                                        <?php echo $c['count_payment']; ?>
                                    </div>
                                    <div class="dt-amt"><span
                                            id="c_pay_amt_<?php echo $cid; ?>"><?php echo number_format($c['amount_payment']); ?></span>
                                    </div>
                                </a>
                            </div>
                            <div class="section-divider" style="margin-top:10px;"><span>สถานะเครดิต</span></div>
                            <?php
                            $base_params = $_GET;
                            $base_params['company_id'] = $c['id'];
                            $params_paid = $base_params;
                            $params_paid['filter_credit_status'] = 'paid';
                            $url_paid = '?' . http_build_query($params_paid);
                            $params_unpaid = $base_params;
                            $params_unpaid['filter_credit_status'] = 'unpaid';
                            $url_unpaid = '?' . http_build_query($params_unpaid);
                            $is_active_paid = ($filter_credit_status == 'paid' && in_array($c['id'], $selected_companies));
                            $is_active_unpaid = ($filter_credit_status == 'unpaid' && in_array($c['id'], $selected_companies));
                            $style_box_paid = "flex:1; background:var(--bg-green-soft); border:1px solid #bbf7d0; border-radius:8px; padding:6px; text-align:center; text-decoration:none; transition:0.2s;";
                            if ($is_active_paid)
                                $style_box_paid .= "border:2px solid #10b981; box-shadow:0 0 5px rgba(16, 185, 129, 0.3); transform:scale(1.02);";
                            $style_box_unpaid = "flex:1; background:var(--bg-orange-soft); border:1px solid #fde68a; border-radius:8px; padding:6px; text-align:center; text-decoration:none; transition:0.2s;";
                            if ($is_active_unpaid)
                                $style_box_unpaid .= "border:2px solid #f59e0b; box-shadow:0 0 5px rgba(245, 158, 11, 0.3); transform:scale(1.02);";
                            ?>
                            <div style="display:flex; gap:5px; margin-bottom:10px;">
                                <a href="<?php echo $url_paid; ?>" style="<?php echo $style_box_paid; ?>"
                                    onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                                    <div style="font-size:9px; color:#15803d; font-weight:700;"><i
                                            class="fas fa-check-double"></i> จ่ายแล้ว</div>
                                    <div style="font-size:14px; font-weight:800; color:#166534;">
                                        <?php echo number_format($c['count_credit_paid']); ?>
                                    </div>
                                    <div style="font-size:14px; color:#166534; font-weight:500; margin-top:-2px;">
                                        (<?php echo number_format($c['amount_credit_paid'], 2); ?>)</div>
                                </a>
                                <a href="<?php echo $url_unpaid; ?>" style="<?php echo $style_box_unpaid; ?>"
                                    onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                                    <div style="font-size:9px; color:#b45309; font-weight:700;"><i class="fas fa-clock"></i>
                                        ยังไม่จ่าย</div>
                                    <div style="font-size:14px; font-weight:800; color:#92400e;">
                                        <?php echo number_format($c['count_credit_unpaid']); ?>
                                    </div>
                                    <div style="font-size:14px; color:#92400e; font-weight:500; margin-top:-2px;">
                                        (<?php echo number_format($c['amount_credit_unpaid'], 2); ?>)</div>
                                </a>
                            </div>
                            <a href="#" class="btn-show-all-sub">แสดงรายการ (<span
                                    id="c_job_<?php echo $cid; ?>"><?php echo $c['job_count']; ?></span>)</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            // คำนวณยอด Void (ต้องมีส่วนนี้ไว้บนสุดไฟล์)
            $stat_void_count = 0;
            $stat_void_amount = 0;
            $sql_void = "SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as amt FROM document_submissions d WHERE d.is_cancelled = 1";

            if (!empty($start_date) && !empty($end_date)) {
                $sql_void .= " AND d.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
            }
            if (!empty($selected_companies)) {
                $safe_ids = array_map(function ($id) use ($conn) {
                    return $conn->real_escape_string($id);
                }, $selected_companies);
                $id_list = implode(',', $safe_ids);
                if (!empty($id_list))
                    $sql_void .= " AND d.company_id IN ($id_list)";
            }
            $q_void = $conn->query($sql_void);
            if ($q_void) {
                $r_void = $q_void->fetch_assoc();
                $stat_void_count = $r_void['cnt'];
                $stat_void_amount = $r_void['amt'];
            }
            ?>

            <div style="margin-bottom: 20px; position: relative; z-index: 10;">

                <div style="display: flex; justify-content: flex-end; align-items: center;">
                    <button type="button" onclick="toggleVoidWidget()" id="btnVoidTrigger" style="
                    background: #fff;
                    border: 1px solid #fee2e2;
                    color: #ef4444;
                    padding: 8px 16px;
                    border-radius: 50px;
                    cursor: pointer;
                    font-family: 'Prompt', sans-serif;
                    font-size: 13px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
                    transition: all 0.2s ease;
                " onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(239, 68, 68, 0.15)'"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.05)'">
                        <div
                            style="background: #fee2e2; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-history" style="font-size: 12px;"></i>
                        </div>
                        ประวัติการยกเลิก

                        <?php if ($stat_void_count > 0): ?>
                            <span
                                style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; min-width: 20px; text-align: center;">
                                <?php echo number_format($stat_void_count); ?>
                            </span>
                        <?php endif; ?>

                        <i class="fas fa-chevron-down" id="voidArrow"
                            style="font-size: 10px; margin-left: 2px; transition: transform 0.3s;"></i>
                    </button>
                </div>

                <div id="voidCardPanel" style="
            display: none; 
            margin-top: 10px; 
            margin-left: auto; /* ชิดขวา */
            width: 100%; 
            max-width: 320px;
            animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
         ">

                    <div onclick="window.location.href='?status=cancelled<?php echo ($start_date ? "&start_date=$start_date" : "") . ($end_date ? "&end_date=$end_date" : ""); ?>'"
                        style="
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                border-radius: 16px;
                padding: 20px;
                color: white;
                cursor: pointer;
                box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.4);
                position: relative;
                overflow: hidden;
                transition: transform 0.2s;
             " onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                        <i class="fas fa-ban"
                            style="position: absolute; right: -10px; bottom: -20px; font-size: 100px; opacity: 0.1;"></i>

                        <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                            <div>
                                <h4 style="margin: 0; font-size: 16px; font-weight: 600; opacity: 0.9;">รายการยกเลิก
                                    (VOID)</h4>
                                <p style="margin: 5px 0 0; font-size: 13px; opacity: 0.8;">คลิกเพื่อกรองข้อมูล</p>
                            </div>
                            <div
                                style="background: rgba(255,255,255,0.2); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
                                <i class="fas fa-file-excel" style="font-size: 20px;"></i>
                            </div>
                        </div>

                        <div style="margin-top: 20px; display: flex; align-items: flex-end; gap: 10px;">
                            <div>
                                <span
                                    style="font-size: 32px; font-weight: 700; line-height: 1;"><?php echo number_format($stat_void_count); ?></span>
                                <span style="font-size: 14px; opacity: 0.8;">รายการ</span>
                            </div>
                            <div style="margin-left: auto; text-align: right;">
                                <span style="font-size: 12px; opacity: 0.8; display: block;">ยอดรวมมูลค่า</span>
                                <span
                                    style="font-size: 18px; font-weight: 600;">฿<?php echo number_format($stat_void_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="dashboard-header">
                <div class="header-top">
                    <div class="page-title">Dashboard ทะเบียนคุมเอกสาร</div>
                </div>

                <form id="searchForm" method="GET" class="filter-bar"
                    onsubmit="event.preventDefault(); loadWithAjax(this);">

                    <?php if ($filter_credit_alert == '1'): ?><input type="hidden" name="filter_credit_alert"
                            value="1"><?php endif; ?>
                    <?php if ($filter_urgent == '1'): ?><input type="hidden" name="filter_urgent"
                            value="1"><?php endif; ?>

                    <div class="filter-row">
                        <div class="form-group-filter expanded">
                            <label class="filter-label">📅 วันที่เริ่ม</label>
                            <input type="text" name="start_date" class="form-control-sm datepicker-input"
                                value="<?php echo $start_date; ?>" placeholder="วว/ดด/ปปปป" readonly
                                style="width:100%;">
                        </div>

                        <div class="form-group-filter expanded">
                            <label class="filter-label">📅 ถึงวันที่</label>
                            <input type="text" name="end_date" class="form-control-sm datepicker-input"
                                value="<?php echo $end_date; ?>" placeholder="วว/ดด/ปปปป" readonly style="width:100%;">
                        </div>
                    </div>

                    <div class="filter-row">
                        <a href="DocumentDashboard.php?filter_urgent=1"
                            class="filter-btn-custom btn-urgent <?php echo ($filter_urgent == '1') ? 'active' : ''; ?>">
                            <i class="fab fa-hotjar"></i> งานต้องตาม
                            <?php if (!empty($grand_total_urgent) && $grand_total_urgent > 0): ?>
                                <span class="badge-count-white"><?php echo number_format($grand_total_urgent); ?></span>
                            <?php endif; ?>
                        </a>

                        <a href="DocumentDashboard.php?filter_credit_alert=1"
                            class="filter-btn-custom btn-credit <?php echo ($filter_credit_alert == '1') ? 'active' : ''; ?>">
                            <i class="fas fa-exclamation-triangle"></i> เครดิตใกล้หมด
                            <?php if (!empty($grand_total_credit_alert) && $grand_total_credit_alert > 0): ?>
                                <span
                                    class="badge-count-white"><?php echo number_format($grand_total_credit_alert); ?></span>
                            <?php endif; ?>
                        </a>

                        <a href="DocumentDashboard.php?status=returned"
                            class="btn-solid-filter btn-return-solid <?php echo ($status_filter == 'returned') ? 'active' : ''; ?>">

                            <i class="fas fa-undo-alt"></i>

                            ตีกลับ (แก้ไข)

                            <?php if (!empty($grand_total_returned) && $grand_total_returned > 0): ?>
                                <span class="badge-count-solid"><?php echo number_format($grand_total_returned); ?></span>
                            <?php endif; ?>
                        </a>

                        <div style="width:1px; height:25px; background:var(--border); margin:0 5px;"></div>

                        <div class="search-wrapper">
                            <i class="fas fa-search search-icon-global"></i>
                            <input type="text" id="searchInput" name="search_all" class="search-input"
                                placeholder="พิมพ์เลขที่, ร้านค้า, หน้างาน..."
                                value="<?php echo isset($_GET['search_all']) ? htmlspecialchars($_GET['search_all']) : ''; ?>"
                                oninput="searchRealtime()">
                        </div>
                        <div class="form-group-filter">
                            <label class="filter-label">ช่วงยอดเงิน</label>
                            <select name="amount_range" class="form-control-sm" onchange="this.form.submit()">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="low" <?php echo ($amount_range == 'low') ? 'selected' : ''; ?>>
                                    0 - 10,000
                                </option>
                                <option value="medium" <?php echo ($amount_range == 'medium') ? 'selected' : ''; ?>>
                                    10,001 - 100,000
                                </option>
                                <option value="high" <?php echo ($amount_range == 'high') ? 'selected' : ''; ?>>
                                    100,001 ขึ้นไป
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search" style="margin-right:5px;"></i> ค้นหา
                        </button>

                        <a href="DocumentDashboard.php?show_all=1" class="btn-reset"
                            style="background: #3b82f6; color: white; border-color: #3b82f6; margin-left:5px;">
                            <i class="fas fa-calendar-alt"></i> แสดงทั้งหมด
                        </a>

                        <a href="DocumentDashboard.php" class="btn-reset">
                            <i class="fas fa-sync-alt"></i> ค่าเริ่มต้น
                        </a>

                        <button type="button" class="btn-excel" onclick="openModal('export')">
                            <i class="fas fa-file-csv" style="margin-right:5px;"></i> Export
                        </button>
                    </div>

                </form>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-nowrap">บริษัท</th>
                                <th class="col-nowrap">วันที่</th>
                                <th class="col-nowrap" style="min-width:120px;">
                                    <div class="th-content">เอกสาร <i class="fas fa-search filter-icon"
                                            onclick="toggleSearch('search_doc')"></i></div>
                                    <input type="text" id="search_doc" class="col-search-input"
                                        placeholder="พิมพ์เลข/ชื่อ..." onkeyup="filterTable()">
                                </th>
                                <th class="col-nowrap" style="min-width:100px;">
                                    <div class="th-content">
                                        ผู้สั่ง/ผู้เปิด
                                        <i class="fas fa-filter filter-icon" onclick="toggleSearch('search_user')"></i>
                                    </div>
                                    <select id="search_user" class="col-search-input" onchange="filterTable()"
                                        style="cursor:pointer; padding:6px;">
                                        <option value="">-- ทั้งหมด --</option>
                                        <?php foreach ($users_list as $u): ?>
                                            <option value="<?php echo $u; ?>"><?php echo $u; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th class="col-nowrap" style="min-width:100px;">
                                    <div class="th-content">หน้างาน <i class="fas fa-search filter-icon"
                                            onclick="toggleSearch('search_site')"></i></div>
                                    <input type="text" id="search_site" class="col-search-input"
                                        placeholder="พิมพ์หน้างาน..." onkeyup="filterTable()">
                                </th>
                                <th class="col-nowrap" style="min-width:140px;">
                                    <div class="th-content">
                                        ซัพพลายเออร์ / บัญชีปลายทาง
                                        <i class="fas fa-search filter-icon"
                                            onclick="toggleSearch('search_supplier')"></i>
                                    </div>
                                    <input type="text" id="search_supplier" class="col-search-input"
                                        placeholder="พิมพ์ชื่อร้าน..." onkeyup="filterTable()">
                                </th>
                                <th class="col-nowrap" style="text-align: right;">ยอดเงิน</th>
                                <th width="100" style="text-align:center;">อนุมัติ</th>
                                <th width="100" style="text-align:center;">การเงินรับเอกสาร</th>
                                <th width="100" style="text-align:center; color:#ef4444;">ตีกลับเอกสาร<br>(ตั้งโอน)</th>
                                <th width="110" style="text-align:center;">คลัง<br>(รับใบกำกับ)</th>
                                <th width="110" style="text-align:center;">ผู้ซื้อ<br>(รับใบกำกับ)</th>
                                <th width="80" style="text-align:center;">บัญชี<br>(รับใบกำกับ)</th>
                                <th class="col-nowrap" style="text-align:center;">เครดิต</th>
                                <th width="80" style="text-align:center;">เพิ่มเติม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($docs->num_rows > 0):
                                $grand_total = 0;
                                $company_totals = []; ?>
                                <?php while ($row = $docs->fetch_assoc()): ?>
                                    <?php
                                    // -------------------------------------------------------------------------
                                    // 1. เตรียมข้อมูลพื้นฐาน
                                    // -------------------------------------------------------------------------
                                    $js_options = JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP;
                                    $row_data = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

                                    // ตัวแปรตรวจสอบ Attachments
                                    $att = $row['attachments'] ?? '';
                                    $is_waiting_tax = (strpos($att, 'รอใบกำกับภาษี 7%') !== false);
                                    $is_finished_doc = (strpos($att, 'ใบสำคัญจ่าย') !== false) || (strpos($att, 'ใบกำกับภาษี 7%') !== false && !$is_waiting_tax);
                                    $has_urgent = $is_waiting_tax;
                                    $has_bill_input = (!empty($row['tax_invoice_no']) || !empty($row['billing_doc_no']) || !empty($row['billing_date']) || !empty($row['payment_due_date']));
                                    $has_pay_status = !empty($row['payment_status']);
                                    $has_credit = (!empty($row['credit_term']) && intval($row['credit_term']) > 0);

                                    // Flag สำหรับ JS
                                    $js_is_pv = (strpos($att, 'ใบสำคัญจ่าย') !== false) ? 1 : 0;
                                    $js_is_wait = $is_waiting_tax ? 1 : 0;
                                    $js_is_vat = (strpos($att, 'ใบกำกับภาษี 7%') !== false && !$is_waiting_tax) ? 1 : 0;

                                    // -------------------------------------------------------------------------
                                    // 2. แยกข้อมูลหมายเหตุ (Note)
                                    // -------------------------------------------------------------------------
                                    $raw_note = $row['doc_note'] ?? '';
                                    $note_buyer = '';
                                    $note_acc = '';
                                    $note_general = $raw_note;

                                    if (preg_match('/\[ผู้ซื้อรับใบกำกับ\]:\s*(.*?)(?=\n|$|\[)/u', $raw_note, $m)) {
                                        $note_buyer = trim($m[1]);
                                        $note_general = str_replace($m[0], '', $note_general);
                                    }
                                    if (preg_match('/\[บัญชีรับใบกำกับ\]:\s*(.*?)(?=\n|$|\[)/u', $raw_note, $m)) {
                                        $note_acc = trim($m[1]);
                                        $note_general = str_replace($m[0], '', $note_general);
                                    }
                                    $note_general = trim($note_general);

                                    // -------------------------------------------------------------------------
                                    // 3. Logic การยกเลิก (VOID) และการคำนวณเงิน
                                    // -------------------------------------------------------------------------
                            
                                    // 🟢 เช็คสถานะยกเลิก
                                    $is_cancel = (isset($row['is_cancelled']) && $row['is_cancelled'] == 1);

                                    // 🟢 ถ้า "ไม่ยกเลิก" ให้บวกเงิน (ถ้ายกเลิก ข้ามไปเลย เหมือนลบรายการทิ้ง)
                                    if ($is_cancel) {
                                        // ถ้ายกเลิก: บังคับให้ทุกสถานะเป็น 0 (เสมือนไม่มีตัวตน)
                                        $js_is_pv = 0;
                                        $js_is_wait = 0;
                                        $js_is_vat = 0;
                                    } else {
                                        // ถ้าปกติ: ให้คำนวณตามเดิม
                                        $js_is_pv = (strpos($att, 'ใบสำคัญจ่าย') !== false) ? 1 : 0;
                                        $js_is_wait = $is_waiting_tax ? 1 : 0;
                                        $js_is_vat = (strpos($att, 'ใบกำกับภาษี 7%') !== false && !$is_waiting_tax) ? 1 : 0;
                                    }

                                    // 🟢 สร้าง Class สำหรับแถว (ถ้ายกเลิก ให้ใส่ row-cancelled เพื่อทำสีจาง/ขีดฆ่า)
                                    $tr_class = "table-row-item " . ($is_cancel ? "row-cancelled" : "");
                                    ?>

                                    <tr class="<?php echo $tr_class; ?>"
                                        data-amount="<?php echo $is_cancel ? 0 : $row['amount']; ?>"
                                        data-pv="<?php echo $js_is_pv; ?>" data-vat="<?php echo $js_is_vat; ?>"
                                        data-wait="<?php echo $js_is_wait; ?>">

                                        <td class="col-nowrap">
                                            <div style="font-weight:700; color:var(--primary);">
                                                <?php echo !empty($row['company_shortname']) ? $row['company_shortname'] : $row['company_name']; ?>
                                            </div>
                                        </td>

                                        <td class="col-nowrap">
                                            <div style="font-weight:600;">
                                                <?php
                                                // 🟢 แก้จาก 'd/m/y' เป็น 'd/m/Y' เพื่อแสดงปี 4 หลัก (DD/MM/YYYY)
                                                echo date('d/m/Y', strtotime($row['created_at']));
                                                ?>
                                            </div>
                                            <div style="font-size:11px; color:var(--secondary);">
                                                <?php echo date('H:i', strtotime($row['created_at'])); ?> น.
                                            </div>
                                        </td>

                                        <td class="col-nowrap">
                                            <span
                                                style="font-weight:700; color:var(--text-main);"><?php echo $row['doc_type']; ?></span>
                                            <span style="font-weight:600;"><?php echo $row['doc_number']; ?></span>
                                        </td>

                                        <td class="col-nowrap">
                                            <div style="font-weight:600; "><?php echo $row['ordered_by']; ?>
                                            </div>
                                        </td>

                                        <td class="col-wrap-site">
                                            <?php if ($row['job_site']): ?>
                                                <div style="color:var(--text-main);"><?php echo $row['job_site']; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="vertical-align: top;">
                                            <div class="supplier-name"><?php echo $row['supplier_name']; ?></div>

                                            <?php if (!empty($row['bank_name']) || !empty($row['bank_account'])): ?>
                                                <div class="bank-wrapper">
                                                    <?php if (!empty($row['bank_name'])): ?>
                                                        <div class="bank-row">
                                                            <div class="bank-icon"><i class="fas fa-university"></i></div>
                                                            <span style="font-weight:600;"><?php echo $row['bank_name']; ?></span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($row['bank_account']) || !empty($row['account_name'])): ?>
                                                        <div class="bank-row">
                                                            <div class="bank-icon"><i class="fas fa-hashtag"></i></div>

                                                            <div
                                                                style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">

                                                                <?php if (!empty($row['bank_account'])): ?>
                                                                    <span class="acc-number"><?php echo $row['bank_account']; ?></span>
                                                                <?php endif; ?>

                                                                <?php if (!empty($row['account_name'])): ?>
                                                                    <span style="font-size:12px; margin-left:5px; color:var(--text-muted);">
                                                                        / <?php echo $row['account_name']; ?>
                                                                    </span>
                                                                <?php endif; ?>

                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>



                                        <td class="col-nowrap" style="text-align:right;">
                                            <div style="font-weight:800; color:var(--primary);">
                                                <?php echo number_format($row['amount'], 2); ?>
                                            </div>
                                        </td>

                                        <td class="col-nowrap" style="text-align:center;">
                                            <?php if ($row['approver_name']): ?>
                                                <div class="action-info-box box-green">
                                                    <div class="action-user" style="color:inherit;"><i class="fas fa-check"></i>
                                                        <?php echo $row['approver_name']; ?></div>
                                                    <div>
                                                        <?php echo date('d/m/y H:i', strtotime($row['approved_at'])); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="status-btn btn-wait-action"
                                                    onclick="openModal('approve', <?php echo $row['id']; ?>)">
                                                    <i class="fas fa-signature"></i> รออนุมัติ
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="col-nowrap" style="text-align:center;">
                                            <?php if ($row['finance_receiver']): ?>
                                                <div class="action-info-box box-blue">
                                                    <div class="action-user" style="color:inherit;"><i class="fas fa-wallet"></i>
                                                        <?php echo $row['finance_receiver']; ?></div>
                                                    <div>
                                                        <?php echo date('d/m/y H:i', strtotime($row['finance_received_at'])); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="status-btn btn-wait-action"
                                                    onclick="openModal('finance', <?php echo $row['id']; ?>)">
                                                    <i class="fas fa-hand-holding-usd"></i> รอการเงิน
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td id="cell-return-<?php echo $row['id']; ?>" class="col-nowrap"
                                            style="text-align:center; vertical-align:middle;">

                                            <?php if (!empty($row['return_doc_by'])): ?>
                                                <div class="action-info-box"
                                                    style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:8px 6px; border-radius:8px; width: 100%;">

                                                    <div style="font-weight:700; font-size:11px; margin-bottom:2px;">
                                                        <i class="fas fa-user-times"></i>
                                                        <?php echo htmlspecialchars($row['return_doc_by']); ?>
                                                    </div>

                                                    <div style="font-size:10px; color:#ef4444; margin-bottom:8px;">
                                                        <?php echo date('d/m/y H:i', strtotime($row['return_doc_at'])); ?>
                                                    </div>

                                                    <?php
                                                    $remark_text = !empty($row['return_remark']) ? $row['return_remark'] : "- ไม่ระบุ -";
                                                    ?>
                                                    <button type="button"
                                                        data-remark="<?php echo htmlspecialchars($remark_text, ENT_QUOTES); ?>"
                                                        onclick="viewReturnRemark(this)"
                                                        style="background:#fff; border:1px solid #fca5a5; color:#dc2626; font-size:10px; padding:4px 10px; border-radius:15px; cursor:pointer; width:100%; margin-bottom:8px; font-weight:600; display:flex; align-items:center; justify-content:center; gap:5px;">
                                                        <i class="fas fa-comment-alt"></i> อ่านสาเหตุ
                                                    </button>

                                                    <div style="border-top:1px dashed #fca5a5; padding-top:6px;">
                                                        <button type="button"
                                                            onclick="toggleReturnDoc(<?php echo $row['id']; ?>, 0)"
                                                            style="background:none; border:none; color:#991b1b; font-size:10px; cursor:pointer; text-decoration:underline;">
                                                            ยกเลิกสถานะ (Reset)
                                                        </button>
                                                    </div>
                                                </div>

                                            <?php else: ?>
                                                <?php if (!empty($row['cancel_return_by'])): ?>
                                                    <div
                                                        style="font-size:10px; color:#64748b; margin-bottom:6px; background:#f1f5f9; padding:4px; border-radius:4px; border:1px solid #e2e8f0; text-align:left;">
                                                        <i class="fas fa-history"></i> ยกเลิกโดย: <br>
                                                        <strong><?php echo htmlspecialchars($row['cancel_return_by']); ?></strong><br>
                                                        <span
                                                            style="font-size:9px;">(<?php echo date('d/m/y H:i', strtotime($row['cancel_return_at'])); ?>)</span>
                                                    </div>
                                                <?php endif; ?>

                                                <button type="button" onclick="toggleReturnDoc(<?php echo $row['id']; ?>, 1)"
                                                    class="status-btn"
                                                    style="background:#fff; border:1px solid #dc2626; color:#dc2626; width:100%; justify-content:center; padding:4px; border-radius:4px; font-size:11px; cursor:pointer; font-weight:600; display:block;">
                                                    <i class="fas fa-reply" style="margin-right:3px;"></i> ตีกลับ
                                                </button>

                                            <?php endif; ?>
                                        </td>

                                        <td class="col-nowrap" style="text-align:center;">
                                            <?php if (!empty($row['wh_tax_receiver'])): ?>
                                                <?php
                                                // ส่วนแสดงผลเมื่อคลังรับไปแล้ว (เหมือนเดิม)
                                                $detail_data = json_encode([
                                                    'inv' => $row['wh_tax_invoice_no'] ?? '',
                                                    'file' => $row['wh_tax_file'] ?? '',
                                                    'note' => $row['wh_tax_note'] ?? ''
                                                ], $js_options);

                                                $wh_inv_js = json_encode($row['wh_tax_invoice_no'] ?? '', $js_options);
                                                $wh_note_js = json_encode($row['wh_tax_note'] ?? '', $js_options);
                                                $wh_file_js = json_encode($row['wh_tax_file'] ?? '', $js_options);

                                                $edit_cmd = "openModal('wh_tax_receive', {$row['id']}, $wh_inv_js, $wh_note_js, $wh_file_js)";
                                                ?>

                                                <div class="action-info-box box-orange"
                                                    style="background:#fff7ed; border:1px solid #fdba74; color:#c2410c;">
                                                    <div class="action-user" style="color:inherit; justify-content:center;">
                                                        <i class="fas fa-warehouse"></i>
                                                        <?php echo htmlspecialchars($row['wh_tax_receiver']); ?>
                                                    </div>
                                                    <div style="font-size:11px; margin-bottom:4px;">
                                                        <?php echo !empty($row['wh_tax_received_at']) ? date('d/m/y H:i', strtotime($row['wh_tax_received_at'])) : '-'; ?>
                                                    </div>

                                                    <button type="button"
                                                        onclick='viewTaxDetails("รายละเอียด (คลังสินค้า)", "#ea580c", <?php echo $detail_data; ?>, <?php echo json_encode($edit_cmd, $js_options); ?>, <?php echo $is_cancel ? 1 : 0; ?>)'
                                                        style="border:1px solid #fdba74; background:#fff; color:#ea580c; font-size:11px; border-radius:4px; cursor:pointer; width:100%; padding:2px;">
                                                        <i class="fas fa-search-plus"></i> ดูรายละเอียด
                                                    </button>
                                                </div>

                                            <?php else: ?>
                                                <?php
                                                // 🟢 [Logic ใหม่] ตรวจสอบเงื่อนไขการแสดงปุ่ม
                                                $att_str = $row['attachments'] ?? '';

                                                // 1. ต้องเป็น "รอใบกำกับภาษี 7%" เท่านั้น (ถ้าเป็น ใบสำคัญจ่าย หรือ ใบกำกับฯ 7% เฉยๆ ไม่ต้องโชว์)
                                                $is_waiting = (strpos($att_str, 'รอใบกำกับภาษี 7%') !== false);

                                                // 2. ผู้ซื้อต้องยังไม่ได้รับ (ถ้าผู้ซื้อรับแล้ว คลังไม่ต้องกดแล้ว)
                                                $buyer_not_received = empty($row['tax_receiver']);

                                                if ($is_waiting && $buyer_not_received):
                                                    ?>
                                                    <div class="status-btn btn-receive-orange"
                                                        onclick='openModal("wh_tax_receive", <?php echo $row['id']; ?>)'>
                                                        <i class="fas fa-file-import"></i> คลังรับเอกสาร
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);">-</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>

                                        <td class="col-nowrap" style="text-align:center;">
                                            <?php
                                            // ประกาศตัวแปร options (ถ้ามีข้างบนแล้ว ลบบรรทัดนี้ได้)
                                            $js_options = JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP;
                                            ?>

                                            <?php if (!empty($row['tax_receiver'])): ?>
                                                <?php
                                                // --- ส่วนแสดงผลเมื่อผู้ซื้อรับไปแล้ว (เหมือนเดิม) ---
                                                $detail_data = json_encode([
                                                    'inv' => $row['tax_invoice_no'] ?? '',
                                                    'file' => $row['tax_file'] ?? '',
                                                    'note' => $row['note_buyer'] ?? ''
                                                ], $js_options);

                                                $buyer_inv_js = json_encode($row['tax_invoice_no'] ?? '', $js_options);
                                                $buyer_note_js = json_encode($row['note_buyer'] ?? '', $js_options);
                                                $buyer_file_js = json_encode($row['tax_file'] ?? '', $js_options);
                                                $row_json_js = json_encode($row, $js_options);

                                                $edit_cmd = "openModal('tax', {$row['id']}, $buyer_inv_js, $buyer_note_js, $buyer_file_js, $row_json_js)";
                                                ?>

                                                <div class="action-info-box box-green">
                                                    <div class="action-user" style="color:inherit; justify-content:center;">
                                                        <i class="fas fa-file-invoice"></i>
                                                        <?php echo htmlspecialchars($row['tax_receiver']); ?>
                                                    </div>
                                                    <div style="font-size:11px; margin-bottom:4px;">
                                                        <?php echo !empty($row['tax_received_at']) ? date('d/m/y H:i', strtotime($row['tax_received_at'])) : '-'; ?>
                                                    </div>

                                                    <button type="button"
                                                        onclick='viewTaxDetails("รายละเอียด (ผู้ซื้อ)", "#16a34a", <?php echo $detail_data; ?>, <?php echo json_encode($edit_cmd, $js_options); ?>, <?php echo $is_cancel ? 1 : 0; ?>)'
                                                        style="border:1px solid #bbf7d0; background:#fff; color:#16a34a; font-size:11px; border-radius:4px; cursor:pointer; width:100%; padding:2px;">
                                                        <i class="fas fa-search-plus"></i> ดูรายละเอียด
                                                    </button>
                                                </div>

                                            <?php else: ?>
                                                <?php
                                                // 🟢 [Logic ใหม่] ตรวจสอบเงื่อนไขการแสดงปุ่ม
                                                $att_str = $row['attachments'] ?? '';
                                                // ปุ่มจะขึ้นเฉพาะเมื่อเป็น "รอใบกำกับภาษี 7%" เท่านั้น
                                                $is_waiting = (strpos($att_str, 'รอใบกำกับภาษี 7%') !== false);

                                                if ($is_waiting):
                                                    // ดึงค่าจากคลังมาใส่เป็น Default (ถ้ามี)
                                                    $def_inv = !empty($row['wh_tax_invoice_no']) ? $row['wh_tax_invoice_no'] : '';
                                                    $def_file = !empty($row['wh_tax_file']) ? $row['wh_tax_file'] : '';

                                                    $js_def_inv = json_encode($def_inv, $js_options);
                                                    $js_def_file = json_encode($def_file, $js_options);
                                                    ?>
                                                    <div class="status-btn btn-receive-blue"
                                                        onclick='openModal("tax", <?php echo $row['id']; ?>, <?php echo $js_def_inv; ?>, "", <?php echo $js_def_file; ?>)'>
                                                        <i class="fas fa-cloud-upload-alt"></i> รับเอกสาร
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);">-</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>

                                        <td class="col-nowrap" style="text-align:center;">
                                            <?php if (!empty($row['acc_receiver'])): ?>
                                                <?php
                                                $detail_data = json_encode([
                                                    'inv' => $row['tax_invoice_no'] ?? '',
                                                    'file' => $row['acc_file'] ?? $row['tax_file'] ?? '',
                                                    'note' => $row['note_acc'] ?? ''
                                                ], $js_options);

                                                $acc_inv_js = json_encode($row['tax_invoice_no'] ?? '', $js_options);
                                                $acc_note_js = json_encode($row['note_acc'] ?? '', $js_options);
                                                $acc_file_js = json_encode($row['acc_file'] ?? '', $js_options);
                                                $row_json_js = json_encode($row, $js_options);

                                                $edit_cmd = "openModal('acc', {$row['id']}, $acc_inv_js, $acc_note_js, $acc_file_js, $row_json_js)";
                                                ?>

                                                <div class="action-info-box box-purple">
                                                    <div class="action-user" style="color:inherit; justify-content:center;">
                                                        <i class="fas fa-user-check"></i>
                                                        <?php echo htmlspecialchars($row['acc_receiver']); ?>
                                                    </div>
                                                    <div style="font-size:11px; margin-bottom:4px;">
                                                        <?php echo !empty($row['acc_received_at']) ? date('d/m/y H:i', strtotime($row['acc_received_at'])) : '-'; ?>
                                                    </div>

                                                    <button type="button"
                                                        onclick='viewTaxDetails("รายละเอียด (บัญชี)", "#7e22ce", <?php echo $detail_data; ?>, <?php echo json_encode($edit_cmd, $js_options); ?>, <?php echo $is_cancel ? 1 : 0; ?>)'
                                                        style="border:1px solid #e9d5ff; background:#fff; color:#7e22ce; font-size:11px; border-radius:4px; cursor:pointer; width:100%; padding:2px;">
                                                        <i class="fas fa-search-plus"></i> ดูรายละเอียด
                                                    </button>
                                                </div>

                                            <?php elseif ($is_waiting_tax || !empty($row['tax_receiver'])): ?>
                                                <div class="status-btn btn-receive-purple"
                                                    onclick='openModal("acc", <?php echo $row['id']; ?>, "", "", "", <?php echo json_encode($row, $js_options); ?>)'>
                                                    <i class="fas fa-stamp"></i> รับเอกสาร
                                                </div>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php
                                            $display_credit = !empty($row['credit_term']) ? intval($row['credit_term']) : null;
                                            if (!$display_credit) {
                                                $att_check = json_decode($row['attachments'] ?? '[]', true);
                                                if (is_array($att_check)) {
                                                    foreach ($att_check as $att_str) {
                                                        if (strpos($att_str, 'เครดิต') !== false && strpos($att_str, 'วัน') !== false) {
                                                            $display_credit = intval(preg_replace('/[^0-9]/', '', $att_str));
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                            $status_html = "";
                                            $btn_class = "st-empty";
                                            if ($display_credit > 0) {
                                                $due_ts = strtotime("+$display_credit days", strtotime($row['created_at']));
                                                $diff_days = ceil(($due_ts - time()) / (60 * 60 * 24));
                                                $due_date_show = date('d/m/y', $due_ts);
                                                if ($row['payment_status'] == 'จ่ายแล้ว (Complete)') {
                                                    $btn_class = "st-paid";
                                                    $status_html = "<div style='font-size:10px; margin-top:2px; opacity:0.9;'>จ่ายแล้ว</div>";
                                                } elseif ($diff_days <= 5) {
                                                    $btn_class = "st-danger";
                                                    $status_html = "<div style='font-size:10px; margin-top:2px; font-weight:700;'>เหลือ $diff_days วัน<br>ครบ $due_date_show</div>";
                                                } elseif ($diff_days <= 10) {
                                                    $btn_class = "st-warn";
                                                    $status_html = "<div style='font-size:10px; margin-top:2px;'>เหลือ $diff_days วัน<br>ครบ $due_date_show</div>";
                                                } else {
                                                    $btn_class = "st-normal";
                                                    $status_html = "<div style='font-size:10px; margin-top:2px; opacity:0.8;'>ครบ $due_date_show</div>";
                                                }
                                            }
                                            ?>
                                            <?php if ($display_credit): ?>
                                                <div class="status-btn <?php echo $btn_class; ?>"
                                                    onclick="openModal('credit', <?php echo $row['id']; ?>, '<?php echo $display_credit; ?>')">
                                                    <div style="font-size:10px; font-weight:800; line-height:1;">
                                                        <?php echo $display_credit; ?> วัน
                                                    </div><?php echo $status_html; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="status-btn st-empty"
                                                    onclick="openModal('credit', <?php echo $row['id']; ?>)"
                                                    style="padding: 2px 8px; font-size: 11px; min-width: auto; width: fit-content; margin: 0 auto;">
                                                    <i class="fas fa-plus" style="font-size: 10px;"></i> ระบุ
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="col-nowrap" style="text-align:center; min-width:140px; vertical-align:top;">
                                            <?php if ($is_cancel): ?>
                                                <div class="btn-view-void"
                                                    onclick="viewVoidDetail('<?php echo $row['cancelled_by']; ?>', '<?php echo $row['cancelled_at']; ?>', '<?php echo htmlspecialchars($row['cancel_reason'], ENT_QUOTES); ?>')"
                                                    style="display:flex; flex-direction:column; align-items:center; gap:4px;">
                                                    <span
                                                        style="background:#ef4444; color:white; padding:4px 12px; border-radius:4px; font-size:12px; font-weight:bold;">🚫
                                                        ยกเลิกรายการ</span>
                                                    <div style="font-size:10px; color:#ef4444; font-weight:bold;"><i
                                                            class="fas fa-history"></i> กดดูสาเหตุ</div>
                                                </div>
                                            <?php else: ?>
                                                <div class="action-stack">

                                                    <?php
                                                    // เช็คว่ามีหมายเหตุสถานะไหม
                                                    $status_note = $row['tax_status_note'] ?? '';
                                                    $has_status_note = !empty($status_note);
                                                    ?>

                                                    <?php
                                                    $doc_btn_class = "btn-type-normal";
                                                    $doc_btn_icon = "fa-file-alt";
                                                    $doc_btn_text = "เอกสารทั่วไป";
                                                    if (strpos($att, 'รอใบกำกับภาษี 7%') !== false) {
                                                        $doc_btn_class = "btn-type-urgent";
                                                        $doc_btn_icon = "fa-exclamation-triangle";
                                                        $doc_btn_text = "รอใบกำกับฯ 7%";
                                                    } elseif (strpos($att, 'ใบกำกับภาษี 7%') !== false) {
                                                        $doc_btn_class = "btn-type-normal";
                                                        $doc_btn_icon = "fa-check-circle";
                                                        $doc_btn_text = "ใบกำกับภาษี 7%";
                                                    } elseif (strpos($att, 'ใบสำคัญจ่าย') !== false) {
                                                        $doc_btn_class = "btn-type-pv";
                                                        $doc_btn_icon = "fa-file-invoice-dollar";
                                                        $doc_btn_text = "ใบสำคัญจ่าย";
                                                    } elseif (strpos($att, 'ใบเสร็จรับเงิน') !== false) {
                                                        $doc_btn_class = "btn-type-receipt";
                                                        $doc_btn_icon = "fa-receipt";
                                                        $doc_btn_text = "ใบเสร็จรับเงิน";

                                                        // 🔥 [แทรกตรงนี้] ส่วนชุดชำระเงิน (ใช้สีปกติ หรือจะเพิ่มสีใหม่ก็ได้)
                                                    } elseif (strpos($att, 'ชุดชำระเงิน') !== false) {
                                                        $doc_btn_class = "btn-type-payment"; // หรือใช้ class อื่นถ้าอยากได้สีพิเศษ
                                                        $doc_btn_icon = "fa-money-check-alt";
                                                        $doc_btn_text = "ชุดชำระเงิน";
                                                    }
                                                    ?>
                                                    <button class="btn-stack-item <?php echo $doc_btn_class; ?>"
                                                        onclick='openDetailModal(<?php echo $row_data; ?>)'>
                                                        <i class="fas <?php echo $doc_btn_icon; ?>"></i>
                                                        <?php echo $doc_btn_text; ?>
                                                        <?php if ($has_status_note): ?>
                                                            <i class="fas fa-circle"
                                                                style="color:#f97316; font-size:8px; margin-left:6px; margin-top:2px;"
                                                                title="มีหมายเหตุสถานะ"></i>
                                                        <?php endif; ?>
                                                    </button>

                                                    <?php if ($has_credit): ?>
                                                        <?php if ($row['warehouse_receiver']): ?>
                                                            <div class="btn-stack-item btn-type-warehouse active"
                                                                style="flex-direction:column; align-items:flex-start; gap:2px; text-align:left;">
                                                                <div style="font-weight:700;"><i class="fas fa-check"></i> คลังรับแล้ว</div>
                                                                <div style="font-size:10px; opacity:0.9;">Ref:
                                                                    <?php echo $row['warehouse_doc_no']; ?>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="btn-stack-item btn-type-warehouse"
                                                                onclick="openModal('warehouse', <?php echo $row['id']; ?>)">
                                                                <i class="fas fa-dolly"></i> คลังรับของ
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($has_pay_status): ?>
                                                            <div class="btn-stack-item btn-type-bill active"
                                                                onclick='openModal("bill_pay", <?php echo $row['id']; ?>, "", "", "", <?php echo $row_data; ?>)'
                                                                style="flex-direction:column; align-items:flex-start; gap:2px; text-align:left;">
                                                                <div style="font-weight:700;"><i class="fas fa-check-double"></i> บันทึกแล้ว
                                                                </div>
                                                                <div style="font-size:10px; opacity:0.9;">
                                                                    <?php echo $row['payment_status']; ?>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="btn-stack-item btn-type-bill"
                                                                onclick='openModal("bill_pay", <?php echo $row['id']; ?>, "", "", "", <?php echo $row_data; ?>)'>
                                                                <i class="fas fa-file-invoice-dollar"></i> บิล/จ่ายเงิน
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <?php if (hasAction('btn_edit')): ?>
                                                        <div style="display:flex; gap:5px; width:100%;">
                                                            <a href="SubmitDocument.php?edit_id=<?php echo $row['id']; ?>"
                                                                class="btn-stack-item btn-type-edit" style="flex:1;">
                                                                <i class="fas fa-pen"></i> แก้ไข
                                                            </a>

                                                            <div class="btn-stack-item"
                                                                onclick="openHistoryModal(<?php echo $row['id']; ?>)"
                                                                style="background:#f8fafc; color:#64748b; border:1px solid #cbd5e1; width:35px; justify-content:center; cursor:pointer; flex:0 0 35px;"
                                                                title="ดูประวัติการแก้ไข">
                                                                <i class="fas fa-history"></i>
                                                            </div>

                                                            <div class="btn-stack-item"
                                                                style="background:#fee2e2; color:#ef4444; border:1px solid #ef4444; width:35px; justify-content:center; cursor:pointer; flex:0 0 35px;"
                                                                onclick="cancelDocument(<?php echo $row['id']; ?>)"
                                                                title="ยกเลิกรายการ (Void)">
                                                                <i class="fas fa-times"></i>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($note_general): ?>
                                                    <div style="text-align:center; margin-top:6px;">
                                                        <i class="fas fa-comment-dots"
                                                            style="color:var(--text-muted); cursor:help; font-size:14px;"
                                                            title="<?php echo htmlspecialchars($note_general); ?>"
                                                            onclick="viewNote('หมายเหตุทั่วไป', '<?php echo htmlspecialchars($note_general, ENT_QUOTES); ?>', '#64748b')"></i>
                                                    </div>
                                                    <div class="btn-stack-item" onclick="openHistoryModal(<?php echo $row['id']; ?>)"
                                                        style="cursor:pointer; color:#64748b; border:1px solid #cbd5e1; width:35px; justify-content:center; flex:0 0 35px;"
                                                        title="ดูประวัติการแก้ไข">
                                                        <i class="fas fa-history"></i>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>

                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="13" style="text-align:center; padding:30px; color:var(--text-muted);">
                                        ไม่พบข้อมูลตามเงื่อนไข</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

    <div id="modal_approve" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:var(--primary);">✍️ ลงนามผู้อนุมัติ</h3>
            <form method="POST" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="doc_id" id="approve_doc_id">
                <label style="display:block; margin-bottom:5px; font-weight:600;">ผู้อนุมัติ</label>
                <input type="text" name="approver_name" class="form-control-readonly"
                    value="<?php echo $_SESSION['fullname']; ?>" readonly>
                <div
                    style="font-size:12px; color:var(--text-muted); margin-bottom:15px; background:var(--bg-soft); padding:8px; border-radius:6px;">
                    ระบบจะบันทึกเวลาปัจจุบันให้อัตโนมัติ</div>
                <button type="submit" class="btn-save">ยืนยัน</button>
            </form>
        </div>
    </div>

    <div id="modal_finance" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#d97706;">💰 ลงรับเอกสารตัดจ่าย</h3>
            <form method="POST" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="finance">
                <input type="hidden" name="doc_id" id="finance_doc_id">
                <label style="display:block; margin-bottom:5px; font-weight:600;">ผู้รับเอกสาร</label>
                <input type="text" name="finance_receiver" class="form-control-readonly"
                    value="<?php echo $_SESSION['fullname']; ?>" readonly>
                <button type="submit" class="btn-save">ยืนยันการรับ</button>
            </form>
        </div>
    </div>

    <div id="modal_credit" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:var(--primary);">📅 ระบุเครดิต (Credit Term)</h3>
            <form method="POST" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="update_credit">
                <input type="hidden" name="doc_id" id="credit_doc_id">
                <label style="display:block; margin-bottom:10px; font-weight:600;">เลือกเครดิต (วัน)</label>
                <select name="credit_term" id="credit_select" class="form-control" required>
                    <option value="">-- กรุณาเลือก --</option>
                    <option value="15">15 วัน</option>
                    <option value="30">30 วัน</option>
                    <option value="45">45 วัน</option>
                    <option value="60">60 วัน</option>
                    <option value="90">90 วัน</option>
                </select>
                <button type="submit" class="btn-save">บันทึก</button>
            </form>
        </div>
    </div>

    <div id="modal_note" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#475569;">📝 บันทึกข้อความ</h3>
            <form method="POST" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="note">
                <input type="hidden" name="doc_id" id="note_doc_id">
                <textarea name="doc_note" id="note_text" class="form-control" rows="4"
                    placeholder="ระบุข้อความ..."></textarea>
                <button type="submit" class="btn-save" style="background:#475569;">บันทึก</button>
            </form>
        </div>
    </div>

    <div id="modal_receive_tax" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:var(--primary);">📂 ผู้ซื้อรับใบกำกับภาษี</h3>

            <form method="POST" enctype="multipart/form-data" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="receive_tax">
                <input type="hidden" name="doc_id" id="tax_doc_id">

                <label style="display:block; margin-bottom:5px; font-weight:600;">เลขที่ใบกำกับภาษี</label>
                <input type="text" name="tax_inv_number" id="tax_inv_input_buyer" class="form-control"
                    placeholder="ระบุเลขที่..." required>

                <label style="display:block; margin-bottom:5px; font-weight:600;">แนบไฟล์ใบกำกับภาษี <span
                        style="color:red">*</span></label>

                <input type="file" name="tax_file_upload" id="tax_file_input" class="form-control" required
                    accept="image/*,.pdf">

                <div id="tax_old_file_show"></div>

                <label style="display:block; margin-bottom:5px; margin-top:10px; font-weight:600;">หมายเหตุ
                    (ถ้ามี)</label>

                <textarea name="action_note" id="action_note" class="form-control" rows="2"
                    placeholder="เช่น บิลมีปัญหา..."></textarea>

                <label style="display:block; margin-bottom:5px; font-weight:600; margin-top:10px;">ผู้รับใบกำกับ</label>
                <input type="text" class="form-control-readonly" value="<?php echo $_SESSION['fullname']; ?>" readonly>

                <button type="submit" class="btn-save" style="margin-top:15px;">ยืนยัน</button>
            </form>
        </div>
    </div>

    <div id="modal_acc_receive" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#9333ea;">👩‍💼 บัญชีรับใบกำกับภาษี</h3>

            <form method="POST" enctype="multipart/form-data" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="acc_receive">
                <input type="hidden" name="doc_id" id="acc_doc_id">

                <label style="display:block; margin-bottom:5px; font-weight:600;">เลขที่ใบกำกับภาษี</label>
                <input type="text" name="tax_inv_number" id="acc_inv_input" class="form-control"
                    placeholder="ตรวจสอบ/ระบุเลขที่...">

                <div
                    style="background:var(--bg-soft); padding:10px; border-radius:8px; margin-bottom:15px; border:1px solid var(--border);">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">ไฟล์เอกสาร</label>

                    <div id="buyer_file_status" style="margin-bottom:10px; font-size:13px;"></div>

                    <input type="file" name="acc_file_upload" id="acc_file_input" class="form-control"
                        accept="image/*,.pdf">
                    <div id="acc_old_file_show"></div>
                    <div style="font-size:11px; color:var(--secondary); margin-top:4px;">
                        * หากผู้ซื้อแนบไฟล์แล้ว ไม่ต้องแนบใหม่ (ระบบจะดึงไฟล์ผู้ซื้อมาใช้) เว้นแต่ต้องการเปลี่ยนไฟล์
                    </div>
                </div>

                <label style="display:block; margin-bottom:5px; font-weight:600;">หมายเหตุบัญชี</label>
                <textarea name="action_note" class="form-control" rows="2" placeholder="ระบุข้อความ..."></textarea>

                <button type="submit" class="btn-save"
                    style="background:#9333ea; margin-top:15px;">ยืนยันการรับ</button>
            </form>
        </div>
    </div>

    <div id="modal_detail_view" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <div class="detail-header">
                <h3 style="margin:0; color:var(--primary);">รายละเอียดเพิ่มเติม</h3>
                <div id="d_header_info" style="font-size:13px; color:var(--secondary); margin-top:5px;"></div>
            </div>
            <div class="info-box"><label
                    style="font-size:11px; font-weight:700; color:var(--text-muted);">รายละเอียดงาน</label>
                <div id="d_full_desc" style="font-size:14px; margin-top:2px;"></div>
            </div>
            <div style="display:flex; gap:10px;">
                <div class="info-box" style="flex:1;"><label
                        style="font-size:11px; font-weight:700; color:#ef4444;">WHT</label>
                    <div id="d_wht_val" style="font-weight:700; color:#ef4444;"></div>
                </div>
            </div>
            <div class="info-box"><label style="font-size:11px; font-weight:700; color:var(--text-muted);"><i
                        class="fas fa-paperclip"></i> เอกสารแนบ</label>
                <div id="d_att_container" style="margin-top:5px; display:flex; flex-wrap:wrap;"></div>
            </div>
            <div id="d_note_placeholder" style="margin-top:10px;"></div>
            <div style="text-align:right; font-size:11px; color:var(--text-light); margin-top:10px;">บันทึก: <span
                    id="d_created_by" style="font-weight:600;"></span> เมื่อ <span id="d_created_at"></span></div>
        </div>
    </div>

    <div id="modal_warehouse" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#d97706;">📦 คลังรับสินค้า</h3>
            <form method="POST" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="warehouse_receive">
                <input type="hidden" name="doc_id" id="warehouse_doc_id">

                <label style="display:block; margin-bottom:5px; font-weight:600;">ผู้รับสินค้า</label>
                <input type="text" class="form-control-readonly" value="<?php echo $_SESSION['fullname']; ?>" readonly>

                <label style="display:block; margin-bottom:5px; font-weight:600;">เลขที่เอกสารรับของ (GR/Ref No.)
                    <span style="color:red">*</span></label>
                <input type="text" name="warehouse_doc_no" class="form-control" required
                    placeholder="ระบุเลขที่เอกสารรับ...">

                <button type="submit" class="btn-save" style="background:#d97706;">ยืนยันการรับของ</button>
            </form>
        </div>
    </div>

    <div id="modal_bill_pay" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#0ea5e9;">✏️ แก้ไขข้อมูลเอกสาร</h3>
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#4f46e5;">🧾 ข้อมูลบิล & การจ่ายเงิน</h3>
            <form method="POST" enctype="multipart/form-data" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="update_bill_pay">
                <input type="hidden" name="doc_id" id="bill_pay_doc_id">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div><label class="filter-label">เลขที่ใบกำกับภาษี</label><input type="text" name="tax_invoice_no"
                            id="bp_tax_no" class="form-control" placeholder="-"></div>
                    <div><label class="filter-label">เลขที่ใบวางบิล</label><input type="text" name="billing_doc_no"
                            id="bp_bill_no" class="form-control" placeholder="-"></div>
                    <div><label class="filter-label">วันที่ใบวางบิล</label><input type="date" name="billing_date"
                            id="bp_bill_date" class="form-control"></div>
                    <div><label class="filter-label">วันที่ครบกำหนดจ่าย</label><input type="date"
                            name="payment_due_date" id="bp_pay_due" class="form-control"></div>
                </div>
                <hr style="margin: 15px 0; border:0; border-top:1px dashed var(--border);">
                <label style="display:block; margin-bottom:5px; font-weight:600;">สถานะการจ่ายเงิน</label>
                <select name="payment_status" id="bp_status" class="form-control">
                    <option value="">-- ยังไม่ระบุ --</option>
                    <option value="วางบิลแล้ว">วางบิลแล้ว</option>
                    <option value="รออนุมัติจ่าย">รออนุมัติจ่าย</option>
                    <option value="เตรียมเช็ค/โอน">เตรียมเช็ค/โอน</option>
                    <option value="จ่ายแล้ว (Complete)">✅ จ่ายแล้ว (Complete)</option>
                    <option value="ยกเลิก">❌ ยกเลิก</option>
                </select>
                <button type="submit" class="btn-save">บันทึกข้อมูล</button>
            </form>
        </div>
    </div>
    <div id="modal_view_note" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="btn-close-modal" onclick="closeModals()" style="top:10px; right:15px;">&times;</span>
            <div id="vn_icon" style="font-size: 40px; margin-bottom: 10px;"></div>
            <h3 id="vn_title" style="margin: 0 0 10px 0; color: var(--text-main);"></h3>
            <div id="vn_text"
                style="background: var(--bg-soft); padding: 15px; border-radius: 8px; text-align: left; font-size: 14px; color: var(--text-main); white-space: pre-wrap;">
            </div>
        </div>
    </div>
    <div id="modal_export" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <span class="close" onclick="document.getElementById('modal_export').style.display='none'">&times;</span>
            <h3 style="color:#1e293b;"><i class="fas fa-file-excel"></i> ส่งออกข้อมูล (Excel)</h3>

            <div style="margin-top:15px;">
                <label style="font-weight:bold; display:block; margin-bottom:5px;">เลือกผู้ทำรายการ:</label>
                <select id="export_user" class="form-control" style="width:100%; margin-bottom:15px;">
                    <option value="">-- ทั้งหมด (ทุกคน) --</option>
                    <?php
                    // 🟢 แก้ไข: เปลี่ยนจาก created_by เป็น ordered_by ให้ตรงกับฐานข้อมูลจริง
                    $u_sql = "SELECT DISTINCT ordered_by FROM document_submissions WHERE ordered_by IS NOT NULL AND ordered_by != '' ORDER BY ordered_by ASC";
                    $u_res = $conn->query($u_sql);

                    while ($u_row = $u_res->fetch_assoc()) {
                        echo '<option value="' . $u_row['ordered_by'] . '">' . $u_row['ordered_by'] . '</option>';
                    }
                    ?>
                </select>

                <label style="font-weight:bold; display:block; margin-bottom:5px;">ประเภทเอกสาร:</label>
                <select id="export_doc_type" class="form-control" style="width:100%; margin-bottom:15px;">
                    <option value="">-- ทั้งหมด --</option>
                    <option value="AX">AX (ใบขอซื้อ)</option>
                    <option value="PO">PO (ใบสั่งซื้อ)</option>
                </select>

                <label style="font-weight:bold; display:block; margin-bottom:5px;">เลือกช่วงวันที่ (DD/MM/YYYY):</label>
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <div style="flex:1;">
                        <span style="font-size:12px; color:#64748b;">จากวันที่</span>
                        <input type="text" id="export_start_date" class="form-control datepicker-input"
                            placeholder="วว/ดด/ปปปป" style="width:100%; background:#fff;">
                    </div>
                    <div style="flex:1;">
                        <span style="font-size:12px; color:#64748b;">ถึงวันที่</span>
                        <input type="text" id="export_end_date" class="form-control datepicker-input"
                            placeholder="วว/ดด/ปปปป" style="width:100%; background:#fff;">
                    </div>
                </div>

                <button onclick="triggerExport()"
                    style="width:100%; background:#107c41; color:white; padding:10px; border:none; border-radius:5px; margin-top:15px; cursor:pointer;">
                    <i class="fas fa-download"></i> ดาวน์โหลด Excel
                </button>
            </div>
        </div>
    </div>
    <div id="modal_cancel" class="modal" style="z-index: 9999;">
        <div class="modal-content" style="max-width: 400px; text-align: center; border-top: 5px solid #ef4444;">
            <div style="margin-bottom: 15px;">
                <div
                    style="width: 60px; height: 60px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>

            <h3 style="margin: 0 0 10px 0; color: #1f2937;">ยืนยันการยกเลิก (Void)</h3>
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 20px;">
                รายการนี้จะถูกระบุสถานะเป็น "ยกเลิก"<br>และยอดเงินจะไม่ถูกนำไปคำนวณ
            </p>

            <form onsubmit="submitCancelForm(event, this)">
                <input type="hidden" name="action" value="cancel_doc">
                <input type="hidden" name="doc_id" id="cancel_doc_id">

                <div style="text-align: left; margin-bottom: 15px;">
                    <label style="font-size: 12px; font-weight: 600; color: #374151;">ระบุเหตุผลที่ยกเลิก <span
                            style="color:red">*</span></label>
                    <textarea name="cancel_reason" class="form-control" rows="2" required
                        placeholder="เช่น พิมพ์ผิด, เอกสารซ้ำ..."></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-save"
                        onclick="document.getElementById('modal_cancel').style.display='none'"
                        style="background: #e5e7eb; color: #374151; flex: 1;">
                        ไม่, กลับไป
                    </button>
                    <button type="submit" class="btn-save" style="background: #ef4444; color: white; flex: 1;">
                        ใช่, ยืนยันยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div id="modal_wh_tax_receive" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#c2410c;">📦 คลังรับใบกำกับภาษี</h3>

            <form method="POST" enctype="multipart/form-data" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="wh_tax_receive">
                <input type="hidden" name="doc_id" id="wh_tax_doc_id">

                <label style="display:block; margin-bottom:5px; font-weight:600;">เลขที่ใบกำกับภาษี</label>
                <input type="text" name="wh_tax_inv_number" class="form-control" placeholder="ระบุเลขที่..." required>

                <div
                    style="background:var(--bg-soft); padding:10px; border-radius:8px; margin:15px 0; border:1px solid var(--border);">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">แนบไฟล์ใบกำกับภาษี <span
                            style="color:red">*</span></label>

                    <input type="file" name="wh_tax_file_upload" id="wh_tax_file_input" class="form-control"
                        accept="image/*,.pdf" required>

                    <div id="wh_tax_old_file_show"></div>
                </div>

                <label style="display:block; margin-bottom:5px; font-weight:600;">หมายเหตุ (ถ้ามี)</label>

                <textarea name="wh_action_note" id="wh_action_note" class="form-control" rows="2"
                    placeholder="ระบุหมายเหตุ..."></textarea>

                <label style="display:block; margin-bottom:5px; font-weight:600; margin-top:10px;">ผู้รับเอกสาร</label>
                <input type="text" class="form-control-readonly" value="<?php echo $_SESSION['fullname']; ?>" readonly>

                <button type="submit" class="btn-save"
                    style="background:#c2410c; margin-top:15px;">ยืนยันการรับ</button>
            </form>
        </div>
    </div>
    <div id="modal_status_note" class="modal">
        <div class="modal-content">
            <button class="btn-close-modal" onclick="closeModals()">&times;</button>
            <h3 style="margin-top:0; color:#ea580c;">📝 หมายเหตุสถานะใบกำกับ</h3>

            <form method="POST" onsubmit="submitModalForm(event, this)">
                <input type="hidden" name="action" value="update_status_note">
                <input type="hidden" name="doc_id" id="status_note_doc_id">

                <label style="display:block; margin-bottom:5px; font-weight:600;">ระบุรายละเอียด</label>
                <textarea name="status_note_text" id="status_note_input" class="form-control" rows="5"
                    placeholder="เช่น ติดปัญหาเรื่องยอด, รอเคลียร์กับร้านค้า..."></textarea>

                <button type="submit" class="btn-save" style="background:#ea580c;">บันทึก</button>
            </form>
        </div>
    </div>
    <div id="modal_history_view" class="modal">
        <div class="modal-content" style="max-width: 1300px;">
            <span class="btn-close-modal"
                onclick="document.getElementById('modal_history_view').style.display='none'">&times;</span>
            <h3 style="margin-top:0; color:#334155; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
                <i class="fas fa-history"></i> ประวัติการแก้ไข
            </h3>

            <div id="history_loading" style="text-align:center; padding:20px; display:none;">
                <i class="fas fa-spinner fa-spin"></i> กำลังโหลดข้อมูล...
            </div>

            <div id="history_content_list" style="max-height:60vh; overflow-y:auto; padding-right:5px;">
            </div>
        </div>
    </div>
</body>

</html>