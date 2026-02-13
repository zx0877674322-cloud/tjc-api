<form action="POST">
    <div class="row mt-4">
        <div class="col-4">
            <label class="bold">วันที่ขอเข้าส่งมอบ</label>
            <input type="date" name="delivery_request_date" id="delivery_request_date" class="form-input">
        </div>
        <div class="col-4">
            <label class="bold">วันที่ส่งมอบงาน</label>
            <input type="date" name="delivery_actual_date" id="delivery_actual_date" class="form-input">
        </div>
        <div class="col-4">
            <label class="bold">ผลการส่งมอบ</label>
            <input type="text" name="delivery_result" id="delivery_result" class="form-input"
                placeholder="ระบุผลการส่งมอบ">
        </div>
        <div class="col-4">
            <label class="bold">หมายเหตุ</label>
            <input type="text" name="delivery_remark" id="delivery_remark" class="form-input"
                placeholder="ระบุหมายเหตุ">
        </div>
    </div>
    <div class="mt-3" style="justify-items: end;">
        <button type="submit" class="btn-save" style="background: #37a6c2 !important;">
            <i class="far fa-save"></i> บันทึกข้อมูล
        </button>
    </div>
</form>