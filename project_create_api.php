<?php
include 'auth.php';
include 'db_connect.php';

$userID = $_SESSION['user_id'];

$project_budget = (double) str_replace(',', '', $_POST['project_budget'] ?? '0');
$guarantee_amount = !empty($_POST['guarantee_amount']) ? (double) str_replace(',', '', $_POST['guarantee_amount']) : NULL;

$company_id = $_POST['company_id'] ?? null;
$contract_number = trim($_POST['contract_number'] ?? '');
if ($contract_number === '') {
    $contract_number = null;
}

$project_name = trim($_POST['project_name'] ?? '');
$customer_id = $_POST['customer_id'] ?? null;
$has_warranty = $_POST['has_warranty'] ?? 0;
$warranty_period = $_POST['warranty_period'] ?? '';
$is_submission_required = $_POST['is_submission_required'] ?? 0;
$submission_date = !empty($_POST['submission_date']) ? $_POST['submission_date'] : null;
$contract_start_date = !empty($_POST['contract_start_date']) ? $_POST['contract_start_date'] : null;
$contract_end_date = !empty($_POST['contract_end_date']) ? $_POST['contract_end_date'] : null;
$work_type_id = $_POST['work_type_id'] ?? null;
$guarantee_type = $_POST['guarantee_type'] ?? '';
$guarantee_ref_number = $_POST['guarantee_ref_number'] ?? '';
$guarantee_issue_date = !empty($_POST['guarantee_issue_date']) ? $_POST['guarantee_issue_date'] : null;
$guarantee_expire_date = !empty($_POST['guarantee_expire_date']) ? $_POST['guarantee_expire_date'] : null;
$quotation_number = $_POST['quotation_number'] ?? '';
$quotation_user_id = $_POST['quotation_user_id'] ?? null;
$user_id = $userID;
$sale_user_id = $_POST['sale_user_id'] ?? null;
$project_status = $_POST['project_status'] ?? null;


if (!empty($contract_number)) {

    $check_sql = "SELECT site_id FROM project_contracts WHERE contract_number = ? LIMIT 1";
    $stmt_check = $conn->prepare($check_sql);

    // bind แค่ s ตัวเดียว
    $stmt_check->bind_param("s", $contract_number);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        http_response_code(400);
        echo "เลขที่สัญญา '$contract_number' มีอยู่ในระบบแล้ว กรุณาตรวจสอบ!";
        exit;
    }
    $stmt_check->close();
}

try {
    $conn->begin_transaction();

    $sql = "INSERT INTO `project_contracts` (
        `company_id`, `customer_id`, `user_id`, `sale_user_id`, `quotation_user_id`, 
        `work_type_id`, `project_name`, `project_status`, `project_budget`, `contract_number`, 
        `quotation_number`, `contract_start_date`, `contract_end_date`, `submission_date`, `is_submission_required`, 
        `has_warranty`, `warranty_period`, `guarantee_type`, `guarantee_amount`, `guarantee_ref_number`, 
        `guarantee_issue_date`, `guarantee_expire_date`
    ) VALUES (
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?
    )";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $params = [
        $company_id,            // 1. i
        $customer_id,           // 2. i
        $user_id,               // 3. i
        $sale_user_id,          // 4. i
        $quotation_user_id,     // 5. i
        $work_type_id,          // 6. i (ส่วนใหญ่ id เป็น int)
        $project_name,          // 7. s
        $project_status,        // 8. s
        $project_budget,        // 9. d (Double)
        $contract_number,       // 10. s
        $quotation_number,      // 11. s
        $contract_start_date,   // 12. s
        $contract_end_date,     // 13. s
        $submission_date,       // 14. s (วันที่ต้องเป็น String หรือ NULL ไม่ใช่ Int)
        $is_submission_required,// 15. i
        $has_warranty,          // 16. i
        $warranty_period,       // 17. s
        $guarantee_type,        // 18. s
        $guarantee_amount,      // 19. d (Double)
        $guarantee_ref_number,  // 20. s
        $guarantee_issue_date,  // 21. s
        $guarantee_expire_date  // 22. s
    ];

    $types = "iiiiiissdssssiissdsss";

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $last_id = $conn->insert_id;

    // Log
    $sql_log = "INSERT INTO `project_logs` (`site_id`, `action`, `status`, `user_id`) VALUES (?, 'Create', ?, ?)";
    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->bind_param("isi", $last_id, $project_status, $user_id);

    if (!$stmt_log->execute()) {
        throw new Exception("Log failed: " . $stmt_log->error);
    }

    $conn->commit();
    echo "success";

} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>