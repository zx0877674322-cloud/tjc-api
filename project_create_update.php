<?php
include 'auth.php';
include 'db_connect.php';
include 'api_project.php';

$userID = $_SESSION['user_id'];
// ดึงข้อมูล id ตามที่แนบลิ้งค์มา
$id = $_GET['id'] ?? '';

if (!empty($id)) {
    $sql = "SELECT * FROM project_contracts WHERE site_id = '$id'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $project_data = $result->fetch_assoc();
    }
}

// ดึงข้อมูลประเภทงาน
$type = [];
$res_type = $conn->query("SELECT work_type_id , work_type_name ,	is_active FROM project_work_type WHERE is_active = 1 ORDER BY work_type_id ASC");
if ($res_type && $res_type->num_rows > 0) {
    while ($row = $res_type->fetch_assoc()) {
        $type[] = [
            'work_type_id' => $row['work_type_id'],
            'work_type_name' => $row['work_type_name'],
        ];
    }
}


?>

<!DOCTYPE html>
<html lang="th">

<head>
    <?php include 'Logowab.php'; ?>
    <title>บันทึกข้อมูลโครงการ</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/parsley.js/2.9.2/parsley.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <script src="load_scripts.js"></script>

</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class='main-content'>
        <a href="javascript:history.back()">
            <i class="fas fa-angle-double-left" style="color: red;"> ย้อนกลับ</i>
        </a>
        <div class="content mt-1">
            <?php if (!empty($id)) {
                echo ' <h2> <i class="fas fa-folder-plus"></i> แก้ไขข้อมูลโครงการ</h2>';
            } else {
                echo ' <h2> <i class="fas fa-folder-plus"></i> เพิ่มข้อมูลโครงการ</h2>';
            }
            ?>

            <hr>
            <form id="form_project" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="project_id" value="<?php echo $id; ?>">
                <div class="row">
                    <div class="col-4">
                        <span class="label-text"><i class="	fas fa-home"></i> บริษัท</span>
                        <select class="form-select select-search" name="company_id" id="company_id" required
                            data-placeholder="-- เลือกบริษัท --"
                            data-parsley-required-message="* กรุณาเลือกบริษัทจากรายการ"
                            data-parsley-errors-container="#validate-company">
                            <option value="">-- เลือกบริษัท --</option>
                            <?php foreach ($companys as $company): ?>
                                <?php
                                $is_selected = (isset($project_data['company_id']) && $project_data['company_id'] == $company['id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $company['id'] ?>" <?= $is_selected ?>>
                                    <?= $company['company_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-validate" id="validate-company"></div>
                    </div>
                    <div class="col-4">
                        <label class="label-text">เลขที่สัญญา</label>
                        <input type="text" name="contract_number" id="contract_number" class="form-input"
                            placeholder="ระบุเลขที่สัญญา" value="<?= $project_data['contract_number'] ?? '' ?>">
                    </div>
                    <div class="col-2">
                        <label class="label-text">วันที่เริ่มสัญญา</label>
                        <input type="date" name="contract_start_date" id="contract_start_date" class="form-input"
                            value="<?= $project_data['contract_start_date'] ?? '' ?>">
                    </div>
                    <div class="col-2">
                        <label class="label-text">วันที่สิ้นสุดสัญญา</label>
                        <input type="date" name="contract_end_date" id="contract_end_date" class="form-input"
                            value="<?= $project_data['contract_end_date'] ?? '' ?>">
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-2">
                        <label class="label-text">การรับประกัน</label>
                        <div class="row mt-2">
                            <div class="col-6">
                                <input class="form-check-input" type="radio" name="has_warranty" id="has_warranty_no"
                                    value="0" onchange="checkWarrantyStatus()" <?= (!isset($project_data['has_warranty']) || $project_data['has_warranty'] == 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="has_warranty_no">ไม่มี</label>
                            </div>

                            <div class="col-6">
                                <input class="form-check-input" type="radio" name="has_warranty" id="has_warranty_yes"
                                    value="1" onchange="checkWarrantyStatus()" <?= (isset($project_data['has_warranty']) && $project_data['has_warranty'] == 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="has_warranty_yes">มี</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-2" id="warranty_period_yes_div">
                        <label class="label-text">ระยะเวลารับประกัน</label>
                        <input type="text" name="warranty_period" id="warranty_period" class="form-input"
                            placeholder="Ex. 1 ปี" data-parsley-required-message="* กรุณาระบุระยะเวลารับประกัน"
                            data-parsley-errors-container="#validate_warranty_period"
                            value="<?= $project_data['warranty_period'] ?? '' ?>">
                        <div class="error-validate" id="validate_warranty_period"></div>
                    </div>
                    <div class="col-2" id="warranty_period_no_div"></div>
                    <div class="col-3">
                        <span class="label-text">ประเภทงาน</span>
                        <select class="form-select select-search" name="work_type_id" id="work_type_id"
                            data-placeholder="-- เลือกประเภทงาน --">
                            <option value="">...เลือกประเภทงาน...</option>
                            <?php foreach ($type as $data): ?>
                                <?php
                                $is_selected = (isset($project_data['work_type_id']) && $project_data['work_type_id'] == $data['work_type_id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $data['work_type_id'] ?>" <?= $is_selected ?>>
                                    <?= $data['work_type_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="label-text">เซลล์ที่รับผิดชอบ</label>
                        <select class="form-select select-search" name="sale_user_id" id="sale_user_id">
                            <option value="">...กรุณาเลือก...</option>
                            <?php foreach ($salse as $data): ?>
                                <?php
                                $is_selected = (isset($project_data['sale_user_id']) && $project_data['sale_user_id'] == $data['id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $data['id'] ?>" <?= $is_selected ?>>
                                    <?= $data['fullname'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-validate" id="validate-sale"></div>
                    </div>

                </div>

                <div class="row mt-2">
                    <div class="col-8">
                        <label class="label-text">ชื่องบ/โครงการ</label>
                        <input type="text" class="form-input" name="project_name" id="project_name"
                            placeholder="ระบุชื่องบ/โครงการ" autocomplete="off"
                            value="<?= $project_data['project_name'] ?? '' ?>">
                    </div>
                    <div class="col-3">
                        <label class="label-text"><i class="fas fa-money-check-alt"></i> ยอดงบ/โครงการ</label>
                        <input type="text" class="form-input form-control" name="project_budget" id="project_budget"
                            placeholder="ระบุจำนวนเงิน (บาท)" autocomplete="off"
                            value="<?= !empty($project_data['project_budget']) ? number_format($project_data['project_budget'], 2) : '' ?>">
                    </div>
                    <span style="margin-top: 35px !important;"> บาท </span>
                </div>


                <div class="row mt-2">
                    <div class="col-4" id="customer_wrapper">
                        <label class="label-text"><i class="fas fa-home"></i> ชื่อหน่วยงาน/ชื่อลูกค้า</label>
                        <select class="form-select select-search" name="customer_id" id="customer_id" required
                            data-placeholder="-- เลือกชื่อหน่วยงาน/ชื่อลูกค้า --"
                            data-parsley-required-message="* กรุณาเลือกชื่อหน่วยงาน/ชื่อลูกค้า"
                            data-parsley-errors-container="#validate_customer">
                            <option value="">-- เลือกชื่อหน่วยงาน/ชื่อลูกค้า --</option>
                            <?php include 'api_url/api_customer.php';
                            foreach ($customers as $data): ?>
                                <?php
                                $is_selected = (isset($project_data['customer_id']) && $project_data['customer_id'] == $data['customer_id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $data['customer_id'] ?>" <?= $is_selected ?>>
                                    <?= $data['customer_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-validate" id="validate_customer"></div>
                    </div>
                    <div class="col-2">
                        <label class="label-text">เพิ่มข้อมูลหน่วยงาน/ลูกค้า</label>
                        <button class="sub-btn openModalBtn" type="button" id="openModalBtn"> <i
                                class="fas fa-plus"></i>&nbsp;คลิก</button>
                    </div>
                </div>

                <hr class="mt-3">
                <div class="row">
                    <div class="col-3">
                        <label class="label-text">ประเภทการยื่นซอง</label>
                        <div class="row mt-2">
                            <div class="col-6">
                                <input class="form-check-input" type="radio" name="is_submission_required"
                                    id="is_submission_required_no" value="0" onchange="checkIsSubmission()"
                                    <?= (!isset($project_data['is_submission_required']) || $project_data['is_submission_required'] == 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_submission_required_no">ไม่ต้องยื่น</label>
                            </div>

                            <div class="col-6">
                                <input class="form-check-input" type="radio" name="is_submission_required"
                                    id="is_submission_required_yes" value="1" onchange="checkIsSubmission()"
                                    <?= (isset($project_data['is_submission_required']) && $project_data['is_submission_required'] == 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_submission_required_yes">ยื่น</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-2" id="submission_date_yes_div">
                        <label class="label-text">วันที่ยื่นซอง</label>
                        <input type="date" name="submission_date" id="submission_date" class="form-input"
                            data-parsley-required-message="* กรุณาระบุวันที่ยื่นซอง"
                            data-parsley-errors-container="#validate_submission"
                            value="<?= $project_data['submission_date'] ?? '' ?>">
                        <div class="error-validate" id="validate_submission"></div>
                    </div>
                    <div class="col-2" id="submission_date_no_div"></div>

                    <div class="col-4">
                        <label class="label-text">เลขที่ใบเสนอราคา</label>
                        <input type="text" class="form-input" name="quotation_number" id="quotation_number"
                            placeholder="ระบุเลขที่ใบเสนอราคา" value="<?= $project_data['quotation_number'] ?? '' ?>">
                    </div>
                    <div class="col-3">
                        <label class="label-text">ผู้เปิดใบเสนอราคา</label>
                        <select class="form-select select-search" name="quotation_user_id" id="quotation_user_id"
                            data-placeholder="-- เลือกผู้เปิดใบเสนอราคา --">
                            <option value="">...กรุณาเลือก...</option>
                            <?php foreach ($purchase as $data): ?>
                                <?php
                                $is_selected = (isset($project_data['quotation_user_id']) && $project_data['quotation_user_id'] == $data['id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $data['id'] ?>" <?= $is_selected ?>>
                                    <?= $data['fullname'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <div class="row mt-2">
                    <div class="col-3">
                        <label class="label-text">ประเภทการค้ำประกัน</label>
                        <div class="row mt-2">
                            <div class="col-3">
                                <input class="form-check-input" type="radio" name="guarantee_type" id="guarantee_no"
                                    value="0" onchange="toggleGuaranteeInputs()"
                                    <?= (!isset($project_data['guarantee_type']) || $project_data['guarantee_type'] == 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="guarantee_no">ไม่มี</label>
                            </div>

                            <div class="col-5">
                                <input class="form-check-input" type="radio" name="guarantee_type" id="guarantee_book"
                                    value="1" onchange="toggleGuaranteeInputs()"
                                    <?= (isset($project_data['guarantee_type']) && $project_data['guarantee_type'] == 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="guarantee_book">หนังสือค้ำ</label>
                            </div>

                            <div class="col-4">
                                <input class="form-check-input" type="radio" name="guarantee_type" id="guarantee_cash"
                                    value="2" onchange="toggleGuaranteeInputs()"
                                    <?= (isset($project_data['guarantee_type']) && $project_data['guarantee_type'] == 2) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="guarantee_cash">เงินสด</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-3" id="ref_number_div">
                        <label class="label-text">เลขที่หนังสือ</label>
                        <input type="text" class="form-input" name="guarantee_ref_number" id="guarantee_ref_number"
                            placeholder="ระบุเลขที่หนังสือ" data-parsley-required-message="* กรุณาระบุเลขที่หนังสือ"
                            data-parsley-errors-container="#validate_ref_number"
                            value="<?= $project_data['guarantee_ref_number'] ?? '' ?>">
                        <div class="error-validate" id="validate_ref_number"></div>
                    </div>
                    <div class="col-3" id="issue_date_div">
                        <label class="label-text">วันที่ออกหนังสือ</label>
                        <input type="date" class="form-input" id="guarantee_issue_date" name="guarantee_issue_date"
                            data-parsley-required-message="* กรุณาระบุวันที่ออกหนังสือ"
                            data-parsley-errors-container="#validate_issue_date"
                            value="<?= $project_data['guarantee_issue_date'] ?? '' ?>">
                        <div class="error-validate" id="validate_issue_date"></div>
                    </div>
                    <div class="col-3" id="expire_date_div">
                        <label class="label-text">วันที่สิ้นสุดหนังสือ</label>
                        <input type="date" class="form-input" id="guarantee_expire_date" name="guarantee_expire_date"
                            data-parsley-required-message="* กรุณาระบุวันที่สิ้นสุดหนังสือ"
                            data-parsley-errors-container="#validate_expire_date"
                            value="<?= $project_data['guarantee_expire_date'] ?? '' ?>">
                        <div class="error-validate" id="validate_expire_date"></div>

                    </div>
                    <div class="col-3" id="cash_div">
                        <label class="label-text">จำนวนเงินสด</label>
                        <input type="text" class="form-input" name="guarantee_amount" id="guarantee_amount"
                            placeholder="ระบุจำนวนเงิน (บาท)" data-parsley-required-message="* กรุณาระบุจำนวนเงิน"
                            data-parsley-errors-container="#validate_amount"
                            value="<?= !empty($project_data['guarantee_amount']) ? number_format($project_data['guarantee_amount'], 2) : '' ?>">
                        <div class="error-validate" id="validate_amount"></div>
                    </div>

                </div>
                <div class="row mt-2" style="justify-content: end !important;">
                    <div class="col-3">
                        <label class="label-text">สถานะโครงการ</label>
                        <select class="form-select select-search" name="project_status" id="project_status"
                            data-placeholder="-- เลือกสถานะโครงการ --" required
                            data-parsley-required-message="* กรุณาเลือกสถานะโครงการ"
                            data-parsley-errors-container="#validate_status">
                            <option value="">...กรุณาเลือก...</option>
                            <?php
                            // แปลงเป็นตัวเลข (int) เพื่อให้เปรียบเทียบ มากกว่า/น้อยกว่า ได้แม่นยำ
                            $current = isset($project_data['project_status']) ? (int) $project_data['project_status'] : '';
                            ?>
                            <option value="0" <?= ($current == 0) ? 'selected' : '' ?> <?= ($current > 0) ? 'disabled style="color:#ccc; background-color:#f9f9f9;"' : '' ?>>
                                ยังไม่เซ็นสัญญา
                            </option>
                            <option value="1" <?= ($current == 1) ? 'selected' : '' ?> <?= ($current > 1) ? 'disabled style="color:#ccc; background-color:#f9f9f9;"' : '' ?>>
                                เซ็นสัญญา
                            </option>
                            <option value="2" <?= ($current == 2) ? 'selected' : '' ?> <?= ($current > 2) ? 'disabled style="color:#ccc; background-color:#f9f9f9;"' : '' ?>>
                                ระหว่างดำเนินการ
                            </option>
                            <option value="3" <?= ($current == 3) ? 'selected' : '' ?>>
                                ดำเนินการเสร็จสิ้น
                            </option>
                        </select>
                        <div class="error-validate" id="validate_status"></div>
                    </div>
                </div>
                <br>
                <div class="modal-footer" style="justify-content: end;">
                    <?php if (!empty($id)): ?>

                    <?php else: ?>
                        <button type="button" class="btn-clear" onclick="clearAllData()">
                            <i class="fas fa-broom"></i>
                            ล้างค่า
                        </button>
                    <?php endif; ?>
                    <button type="submit" class="btn-save"><i class="far fa-save"></i>บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>



    <div id="createCustomer" class="modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
        aria-labelledby="exampleModalLabel">
        <div class="modal-content" id="modalContent" style="width: 50% !important;">
        </div>
    </div>
</body>

</html>

<script>
    // ฟังก์ชันจัดการส่วน "หลักประกันสัญญา" (Guarantee)
    function toggleGuaranteeInputs() {
        // 1. รับค่าสถานะ Radio
        const isNo = document.getElementById('guarantee_no').checked;
        const isBook = document.getElementById('guarantee_book').checked;
        const isCash = document.getElementById('guarantee_cash').checked;

        // 2. รับค่า Div (Container)
        const ref_number_div = document.getElementById('ref_number_div');
        const issue_date_div = document.getElementById('issue_date_div');
        const expire_date_div = document.getElementById('expire_date_div');
        const cash_div = document.getElementById('cash_div'); // ผมแก้ชื่อตัวแปรให้สื่อความหมายขึ้น

        // 3. รับค่า Input fields
        const inputFieldRef = document.getElementById('guarantee_ref_number');
        const inputFieldIssue = document.getElementById('guarantee_issue_date');
        const inputFieldExpire = document.getElementById('guarantee_expire_date');
        const inputFieldCash = document.getElementById('guarantee_amount'); // *ชื่อตัวแปรที่ถูกต้อง*

        // --- เริ่ม Logic ---

        if (isNo) {
            // [กรณีไม่มี]
            // 1. ซ่อนทุกอย่าง
            ref_number_div.style.display = 'none';
            issue_date_div.style.display = 'none';
            expire_date_div.style.display = 'none';
            cash_div.style.display = 'none';

            // 2. เอา Required ออก และเคลียร์ค่าทิ้งทั้งหมด
            if (inputFieldRef) { inputFieldRef.required = false; inputFieldRef.value = ''; }
            if (inputFieldIssue) { inputFieldIssue.required = false; inputFieldIssue.value = ''; }
            if (inputFieldExpire) { inputFieldExpire.required = false; inputFieldExpire.value = ''; }
            if (inputFieldCash) { inputFieldCash.required = false; inputFieldCash.value = ''; }

        } else if (isBook) {
            // [กรณีหนังสือค้ำ]
            // 1. แสดงส่วนหนังสือ ซ่อนส่วนเงินสด
            ref_number_div.style.display = 'block';
            issue_date_div.style.display = 'block';
            expire_date_div.style.display = 'block';
            cash_div.style.display = 'none';

            // 2. Set Required ให้หนังสือ
            if (inputFieldRef) inputFieldRef.required = true;
            if (inputFieldIssue) inputFieldIssue.required = true;
            if (inputFieldExpire) inputFieldExpire.required = true;

            // 3. ยกเลิก Required ของเงินสดและเคลียร์ค่า
            if (inputFieldCash) {
                inputFieldCash.required = false;
                inputFieldCash.value = '';
            }

        } else if (isCash) {
            // [กรณีเงินสด]
            // 1. ซ่อนส่วนหนังสือ แสดงส่วนเงินสด
            ref_number_div.style.display = 'none';
            issue_date_div.style.display = 'none';
            expire_date_div.style.display = 'none';
            cash_div.style.display = 'block';

            // 2. Set Required ให้เงินสด (**สำคัญ: ของเดิมคุณลืมบรรทัดนี้**)
            if (inputFieldCash) inputFieldCash.required = true;

            // 3. ยกเลิก Required ของหนังสือและเคลียร์ค่า
            if (inputFieldRef) { inputFieldRef.required = false; inputFieldRef.value = ''; }
            if (inputFieldIssue) { inputFieldIssue.required = false; inputFieldIssue.value = ''; }
            if (inputFieldExpire) { inputFieldExpire.required = false; inputFieldExpire.value = ''; }
        }
    }

    // การรับประกัน
    function checkWarrantyStatus() {
        const checkRadio1 = document.getElementById('has_warranty_no').checked;
        const checkRadio2 = document.getElementById('has_warranty_yes').checked;

        const checkOpen = document.getElementById('warranty_period_yes_div');
        const checkclose = document.getElementById('warranty_period_no_div');

        const inputField = document.getElementById('warranty_period');

        if (checkRadio1) {
            checkOpen.style.display = 'none';
            checkclose.style.display = 'block';
            if (inputField) {
                inputField.required = false;
                inputField.value = '';
            }
        } else if (checkRadio2) {
            checkOpen.style.display = 'block';
            checkclose.style.display = 'none';
            if (inputField) {
                inputField.required = true;
            }
        }
    }

    // ประเภทการยื่นซอง
    function checkIsSubmission() {
        const checkRadio1 = document.getElementById('is_submission_required_no').checked;
        const checkRadio2 = document.getElementById('is_submission_required_yes').checked;

        const checkOpen = document.getElementById('submission_date_yes_div');
        const checkclose = document.getElementById('submission_date_no_div');

        const inputField = document.getElementById('submission_date');

        if (checkRadio1) {
            if (checkOpen) checkOpen.style.display = 'none';
            if (checkclose) checkclose.style.display = 'block';
            if (inputField) {
                inputField.required = false;
                inputField.value = '';
            }
        } else if (checkRadio2) {
            if (checkOpen) checkOpen.style.display = 'block';
            if (checkclose) checkclose.style.display = 'none';
            if (inputField) {
                inputField.required = true;
            }
        }
    }

    function clearAllData() {
        document.querySelectorAll('input[type="text"], input[type="number"], input[type="date"]').forEach(input => input.value = '');
        document.querySelectorAll('textarea').forEach(area => area.value = '');
        $('.select-search').val(null).trigger('change');
        const radioWarrantyNo = document.getElementById('has_warranty_no');
        const radioGuaranteeNo = document.getElementById('guarantee_no');
        if (radioWarrantyNo) radioWarrantyNo.checked = true;
        if (radioGuaranteeNo) radioGuaranteeNo.checked = true;

        if (typeof checkWarrantyStatus === 'function') checkWarrantyStatus();
        if (typeof toggleGuaranteeInputs === 'function') toggleGuaranteeInputs();
    }

    $(document).ready(function () {
        toggleGuaranteeInputs();
        checkWarrantyStatus();
        checkIsSubmission();

        $('#form_project').parsley({
            excluded: 'input[type=button], input[type=submit], input[type=reset]'
        });
        $('input[name="has_warranty"]').on('change', function () {
            checkWarrantyStatus();
        });


        var modal = $("#createCustomer");

        $("#openModalBtn").on("click", function () {
            var user_id = '<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ''; ?>';
            $("#modalContent").load("manage_customers.php?user_id=" + user_id, function (response, status, xhr) {
                if (status == "error") {
                    $("#modalContent").html("<p>Sorry, there was an error loading the form.</p>");
                }
                modal.fadeIn(200);
            });
        });
        // ปิด Modal (ใช้ Delegated Event เพราะปุ่มปิดถูกโหลดมาทีหลัง)
        $(document).on("click", ".btn-close", function () {
            modal.fadeOut(200);
        });

    });

    $(document).on('submit', '#form_project', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var id = $('#project_id').val();

        var targetUrl = (id !== '') ? 'project_update_api.php' : 'project_create_api.php';

        $.ajax({
            url: targetUrl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกข้อมูลสำเร็จ',
                    text: 'ระบบจะรีโหลดหน้าอัตโนมัติ',
                    showCancelButton: false,
                    showConfirmButton: false,
                    timer: 800,
                    timerProgressBar: true,
                }).then(() => {
                    if (id !== '') {
                        window.location.href = 'project_details.php?id=' + id;
                    } else {
                        window.location.href = 'project_details.php';
                    }
                });
            },
            error: function (xhr, status, error) {
                var errorMessage = xhr.responseText;
                if (!errorMessage) {
                    errorMessage = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'แจ้งเตือน',
                    text: errorMessage,
                    showCancelButton: false,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'ปิด'
                });
            }
        });
    });
</script>