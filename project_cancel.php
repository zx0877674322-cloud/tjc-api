<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

$site_id = isset($_GET['site_id']) ? $_GET['site_id'] : '';
?>


<script src="load_scripts.js"></script>
<link rel="stylesheet" href="style.css">

<div class="modal-header">
    <h3><i class="fas fa-times"></i> ยกเลิกหน้างานเลขที่ <?php echo $site_id; ?></h3>
</div>

<div class="modal-body">
    <form id="form_cancel" action="project_cancel_api.php?id=<?php echo $site_id; ?>" method="POST" novalidate>

        <div class="row">
            <div class="col-12">
                <label class="label-text">หมายเหตุ<span class="text-danger">*</span></label>
                <input type="text" class="form-input form-control" name="remark" id="remark"
                    placeholder="ระบุหมายเหตุที่ยกเลิก" required
                    data-parsley-required-message="กรุณาระบุหมายเหตุที่ยกเลิก"
                    data-parsley-errors-container="#validate-remark">
                <div class="error-validate" id="validate-remark"></div>
            </div>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn-close close-modal" data-bs-dismiss="modal">
        <i class="fas fa-close"></i> ยกเลิก
    </button>
    <button type="submit" form="form_cancel" class="btn-save">
        <i class="fas fa-save"></i> ยืนยัน
    </button>
</div>

<script>

    $(document).on('submit', '#form_cancel', function (e) {
        e.preventDefault();
        var $form = $(this);
        $form.parsley().validate();

        if (typeof $form.parsley === 'function') {
            $form.parsley().validate();
            if (!$form.parsley().isValid()) {
                return false;
            }
        }

        var formData = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                Swal.fire({
                    icon: 'success',
                    title: 'ยกเลิกโครงการเรียบร้อยแล้ว',
                    text: '',
                    showCancelButton: false,
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true,
                }).then(() => {
                    $('body').append(response);
                    $("#cancelModal").fadeOut(200);
                    window.location.reload();
                });
            },
            error: function (xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้: ' + error,
                    confirmButtonText: 'ตกลง'
                });
            }
        });
    });

</script>