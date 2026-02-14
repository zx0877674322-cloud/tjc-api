<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

date_default_timezone_set('Asia/Bangkok');

$current_user = $_SESSION['fullname'] ?? $_SESSION['username'];
$current_user_id = $_SESSION['user_id']; 

// --- 1. ดึงข้อมูลบริษัทของผู้ใช้ปัจจุบัน ---
$user_company_fullname = "ไม่ระบุสังกัด"; 
if ($current_user_id > 0) {
    $stmt = $conn->prepare("SELECT c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($row_c = $stmt->get_result()->fetch_assoc()) {
        $user_company_fullname = $row_c['company_name'] ?? "ไม่ระบุสังกัด"; 
    }
    $stmt->close();
}

// --- 2. ดึงรายชื่อพนักงาน ---
$employees = [];
$q_emp = $conn->query("SELECT u.id, u.fullname, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id ORDER BY u.fullname ASC");
while($row = $q_emp->fetch_assoc()){ $employees[] = $row; }

$message = "";
$edit_mode = false;
$row_edit = [];
$edit_id = 0;
$is_team_edit = false; // ตัวแปรเช็คว่าเป็นงานทีมหรือไม่

// --- 3. โหมดแก้ไข ---
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt_edit = $conn->prepare("SELECT * FROM work_plans WHERE id = ?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $res_edit = $stmt_edit->get_result();
    if ($res_edit->num_rows > 0) {
        $temp = $res_edit->fetch_assoc();
        // เช็คสิทธิ์ (เจ้าของ หรือ เป็นชื่อลูกทีมในนั้น)
        if ($temp['reporter_name'] === $current_user || $temp['team_member'] === $current_user) {
            $row_edit = $temp;
            $edit_mode = true;
            // เช็คว่าเป็นงานทีมหรือไม่
            if ($row_edit['team_type'] == 'Auction') {
                $is_team_edit = true;
            }
        }
    }
    $stmt_edit->close();
}

// --- 4. ส่วนบันทึกข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_plan'])) {
    
    $plan_type = $_POST['plan_type']; 
    $record_date = $_POST['record_date'];
    $final_created_at = $record_date . " " . date("H:i:s");
    $status = 'Plan';

    // กรณีแก้ไข (Update) หรือ เพิ่มแบบเดี่ยว (Individual)
    // *หมายเหตุ: การแก้ไขงานทีม ก็จะใช้วิธีเดียวกับ Individual คือแก้ทีละแถว
    if ($edit_mode || $plan_type == 'individual') {
        
        $plan_date = $_POST['plan_date'];
        $contact_person = trim($_POST['contact_person']);
        $work_detail = trim($_POST['work_detail']);
        
        // ถ้าเป็นการแก้ไข ให้คงค่าเดิมไว้ ถ้าเพิ่มใหม่ใช้ค่า default
        $team_type = $edit_mode ? $row_edit['team_type'] : 'Marketing';
        $company_to_save = $edit_mode ? $row_edit['company'] : $user_company_fullname;
        
        // ถ้าเป็นการแก้ไขงานทีม ชื่อลูกทีมต้องเหมือนเดิม
        $team_member_save = ($edit_mode && !empty($row_edit['team_member'])) ? $row_edit['team_member'] : null;

        if ($edit_mode) {
            $status = $_POST['status'];
            // อัปเดตข้อมูล (รวมถึง team_member และ team_type ให้คงเดิม)
            $sql = "UPDATE work_plans SET plan_date=?, contact_person=?, work_detail=?, status=?, created_at=?, team_member=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $plan_date, $contact_person, $work_detail, $status, $final_created_at, $team_member_save, $edit_id);
        } else {
            // เพิ่มใหม่แบบเดี่ยว
            $sql = "INSERT INTO work_plans (reporter_name, created_at, plan_date, contact_person, work_detail, status, company, team_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $current_user, $final_created_at, $plan_date, $contact_person, $work_detail, $status, $company_to_save, $team_type);
        }
        $stmt->execute();
        $stmt->close();

    } elseif ($plan_type == 'team' && !$edit_mode) {
        // --- เพิ่มใหม่แบบทีม (Auction) Loop Insert ---
        $team_type = 'Auction';
        $emp_names = $_POST['emp_name'];
        $emp_comps = $_POST['emp_comp'];
        $team_dates = $_POST['team_plan_date'];
        $contacts  = $_POST['team_contact'];
        $details   = $_POST['team_detail'];

        $sql = "INSERT INTO work_plans (reporter_name, team_member, created_at, plan_date, contact_person, work_detail, status, company, team_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        for ($i = 0; $i < count($emp_names); $i++) {
            $r_member = $emp_names[$i];
            $r_comp = $emp_comps[$i];
            $r_date = $team_dates[$i];
            $r_contact = $contacts[$i];
            $r_detail = $details[$i];

            if(!empty($r_member) && !empty($r_date)) {
                $stmt->bind_param("sssssssss", $current_user, $r_member, $final_created_at, $r_date, $r_contact, $r_detail, $status, $r_comp, $team_type);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    $title = $edit_mode ? 'อัปเดตข้อมูลเรียบร้อย' : 'บันทึกสำเร็จ';
    $message = "<script>Swal.fire({icon: 'success', title: '$title', showConfirmButton: false, timer: 1500}).then(() => { window.location.href='work_plan_dashboard.php'; });</script>";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?php echo $edit_mode ? 'แก้ไขแผนงาน' : 'สร้างแผนงาน'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { background-color: #f3f4f6; font-family: 'Prompt', sans-serif; }
        .card-custom { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); max-width: 900px; margin: 40px auto; overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #4f46e5, #4338ca); color: white; padding: 20px 30px; }
        .bg-readonly { background-color: #f3f4f6; color: #6b7280; }
        
        .plan-type-selector { display: flex; gap: 10px; margin-bottom: 20px; background: #f1f5f9; padding: 5px; border-radius: 10px; }
        .plan-option { flex: 1; text-align: center; padding: 10px; cursor: pointer; border-radius: 8px; font-weight: 600; transition: 0.3s; border: 1px solid transparent; }
        .plan-option.active { background: white; color: #4f46e5; border-color: #e0e7ff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .plan-option:not(.active) { color: #64748b; }

        .team-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 20px; margin-bottom: 15px; position: relative; }
        .btn-remove-box { position: absolute; top: 10px; right: 10px; color: #ef4444; cursor: pointer; font-size: 1.2rem; }
        .btn-add-box { background: #e0e7ff; color: #4338ca; border: 1px dashed #4338ca; width: 100%; padding: 10px; border-radius: 10px; font-weight: 600; transition: 0.2s; }
        .btn-add-box:hover { background: #dbeafe; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="container">
        <div class="card-custom">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="m-0"><i class="fas <?php echo $edit_mode ? 'fa-edit' : 'fa-calendar-plus'; ?> me-2"></i> <?php echo $edit_mode ? 'แก้ไขข้อมูล' : 'บันทึกแผนงาน'; ?></h4>
                <a href="work_plan_dashboard.php" class="btn btn-sm btn-outline-light">กลับ</a>
            </div>
            <div class="card-body p-4">
                <?php echo $message; ?>
                
                <form method="POST" id="mainForm">
                    
                    <?php if(!$edit_mode): ?>
                    <div class="plan-type-selector">
                        <div class="plan-option active" onclick="switchType('individual')">
                            <i class="fas fa-user me-2"></i> แผนงานเดี่ยว (การตลาด)
                        </div>
                        <div class="plan-option" onclick="switchType('team')">
                            <i class="fas fa-users me-2"></i> แผนงานทีม (ประมูล)
                        </div>
                    </div>
                    <?php else: ?>
                        <?php if($is_team_edit): ?>
                        <div class="alert alert-warning border-0 bg-warning-subtle text-warning-emphasis mb-4">
                            <i class="fas fa-exclamation-triangle me-1"></i> คุณกำลังแก้ไขข้อมูลลูกทีม: <strong><?php echo htmlspecialchars($row_edit['team_member']); ?></strong> (สังกัด: <?php echo htmlspecialchars($row_edit['company']); ?>)
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <input type="hidden" name="plan_type" id="plan_type" value="<?php echo $edit_mode ? 'individual' : 'individual'; ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ผู้บันทึก (หัวหน้า)</label>
                            <input type="text" class="form-control bg-readonly" value="<?php echo htmlspecialchars($current_user); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">สังกัด (ชื่อเต็ม)</label>
                            <input type="text" class="form-control bg-readonly" value="<?php echo htmlspecialchars($user_company_fullname); ?>" readonly>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">วันที่บันทึกข้อมูล</label>
                            <input type="date" name="record_date" class="form-control" value="<?php echo $edit_mode ? date('Y-m-d', strtotime($row_edit['created_at'])) : date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <div id="form-individual">
                        
                        <?php if($edit_mode && !empty($row_edit['team_member'])): ?>
                        <div class="mb-3">
                            <label class="form-label text-warning fw-bold">พนักงานผู้ปฏิบัติงาน (ลูกทีม)</label>
                            <input type="text" class="form-control bg-readonly fw-bold text-dark" value="<?php echo htmlspecialchars($row_edit['team_member']); ?>" readonly>
                        </div>
                        <?php endif; ?>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-primary fw-bold">วันที่แพลนงาน <span class="text-danger">*</span></label>
                                <input type="date" name="plan_date" class="form-control border-primary" value="<?php echo $edit_mode ? $row_edit['plan_date'] : date('Y-m-d', strtotime('+1 day')); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">บุคคล / หน่วยงาน <span class="text-danger">*</span></label>
                                <input type="text" name="contact_person" class="form-control" value="<?php echo $edit_mode ? htmlspecialchars($row_edit['contact_person']) : ''; ?>" placeholder="ระบุชื่อลูกค้า...">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รายละเอียดงาน</label>
                            <textarea name="work_detail" class="form-control" rows="4" placeholder="ระบุรายละเอียด..."><?php echo $edit_mode ? htmlspecialchars($row_edit['work_detail']) : ''; ?></textarea>
                        </div>
                        
                        <?php if($edit_mode): ?>
                        <div class="mb-3">
                             <label class="form-label">สถานะ</label>
                             <select name="status" class="form-select">
                                <?php 
                                $opts = ['Plan'=>'Plan', 'Confirmed'=>'Confirmed', 'Completed'=>'Completed', 'Cancelled'=>'Cancelled'];
                                foreach($opts as $k=>$v) echo "<option value='$k' ".($row_edit['status']==$k?'selected':'').">$v</option>";
                                ?>
                             </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div id="form-team" style="display: none;">
                        <div class="alert alert-primary border-0 bg-primary-subtle text-primary-emphasis mb-4">
                            <i class="fas fa-info-circle me-1"></i> เลือกพนักงานและ<b>วันที่แพลนงาน</b>ของแต่ละคนได้เลย
                        </div>

                        <div id="team-container">
                            <div class="team-box">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label sm text-muted">พนักงาน (ลูกทีม)</label>
                                        <select name="emp_name[]" class="form-select emp-select" onchange="updateCompany(this)">
                                            <option value="">-- เลือกพนักงาน --</option>
                                            <?php foreach($employees as $emp): ?>
                                                <option value="<?php echo htmlspecialchars($emp['fullname']); ?>" 
                                                        data-comp="<?php echo htmlspecialchars($emp['company_name']); ?>">
                                                    <?php echo htmlspecialchars($emp['fullname']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label sm text-muted">สังกัด (Auto)</label>
                                        <input type="text" name="emp_comp[]" class="form-control bg-readonly comp-input" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label sm text-primary fw-bold">วันที่แพลนงาน</label>
                                        <input type="date" name="team_plan_date[]" class="form-control border-primary" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label sm text-muted">บุคคล / หน่วยงาน</label>
                                        <input type="text" name="team_contact[]" class="form-control" placeholder="ระบุหน่วยงาน...">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label sm text-muted">รายละเอียดงาน</label>
                                        <textarea name="team_detail[]" class="form-control" rows="2" placeholder="รายละเอียด..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-add-box mb-3" onclick="addTeamBox()">
                            <i class="fas fa-plus-circle me-1"></i> เพิ่มพนักงานอีก
                        </button>
                    </div>

                    <button type="submit" name="save_plan" class="btn btn-primary w-100 py-2 fw-bold" style="background: #4f46e5; border-radius: 10px;">
                        <i class="fas fa-save me-2"></i> <?php echo $edit_mode ? 'อัปเดตข้อมูล' : 'บันทึกข้อมูล'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ถ้าอยู่ใน Edit Mode เราจะไม่ให้ Switch ไปมา
        <?php if(!$edit_mode): ?>
        function switchType(type) {
            $('#plan_type').val(type);
            $('.plan-option').removeClass('active');
            
            if (type === 'individual') {
                $('.plan-option:first-child').addClass('active');
                $('#form-individual').slideDown();
                $('#form-team').slideUp();
                // Required Logic for Individual
                $('input[name="plan_date"]').prop('required', true);
                $('input[name="contact_person"]').prop('required', true);
            } else {
                $('.plan-option:last-child').addClass('active');
                $('#form-individual').slideUp();
                $('#form-team').slideDown();
                // Remove Required for Individual
                $('input[name="plan_date"]').prop('required', false);
                $('input[name="contact_person"]').prop('required', false);
            }
        }
        <?php endif; ?>

        function updateCompany(selectObj) {
            let compName = $(selectObj).find(':selected').data('comp');
            $(selectObj).closest('.row').find('.comp-input').val(compName || '');
        }

        function addTeamBox() {
            let boxHtml = `
            <div class="team-box">
                <i class="fas fa-times btn-remove-box" onclick="$(this).parent().remove()"></i>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label sm text-muted">พนักงาน (ลูกทีม)</label>
                        <select name="emp_name[]" class="form-select emp-select" onchange="updateCompany(this)" required>
                            <option value="">-- เลือกพนักงาน --</option>
                            <?php foreach($employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['fullname']); ?>" 
                                        data-comp="<?php echo htmlspecialchars($emp['company_name']); ?>">
                                    <?php echo htmlspecialchars($emp['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label sm text-muted">สังกัด (Auto)</label>
                        <input type="text" name="emp_comp[]" class="form-control bg-readonly comp-input" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label sm text-primary fw-bold">วันที่แพลนงาน</label>
                        <input type="date" name="team_plan_date[]" class="form-control border-primary" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label sm text-muted">บุคคล / หน่วยงาน</label>
                        <input type="text" name="team_contact[]" class="form-control" placeholder="ระบุหน่วยงาน..." required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label sm text-muted">รายละเอียดงาน</label>
                        <textarea name="team_detail[]" class="form-control" rows="2" placeholder="รายละเอียด..."></textarea>
                    </div>
                </div>
            </div>`;
            $('#team-container').append(boxHtml);
        }
    </script>
</body>
</html>