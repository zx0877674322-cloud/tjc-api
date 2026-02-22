<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once 'db_connect.php';
require_once 'auth.php'; 

$current_username = $_SESSION['username'] ?? 'Guest';
$user_fullname    = $_SESSION['fullname']  ?? $current_username;

$user_role = 'user';
if ($current_username !== 'Guest') {
    $stmt_u = $conn->prepare("SELECT role FROM users WHERE username = ? LIMIT 1");
    $stmt_u->bind_param("s", $current_username);
    $stmt_u->execute();
    $res_u = $stmt_u->get_result();
    if ($row_u = $res_u->fetch_assoc()) $user_role = strtolower($row_u['role']);
    $stmt_u->close();
}
$is_admin = ($user_role === 'admin');

$sql_companies = "SELECT id, company_name, logo_file FROM companies ORDER BY 
    CASE id WHEN 6 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 WHEN 5 THEN 4 ELSE 5 END ASC, id ASC";
$res_companies  = $conn->query($sql_companies);
$companies_data = [];
while ($row = $res_companies->fetch_assoc()) $companies_data[] = $row;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สมุดคุมหนังสือ รับ-ส่ง</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Mitr:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<?php include 'Logowab.php'; ?>

<style>
/* ─── BASE ─── */
html, body { color-scheme: light; }
body {
    background: #f0f4f8;
    background-image:
        radial-gradient(ellipse 80% 50% at 30% -10%, rgba(13,148,136,0.07) 0%, transparent 60%),
        radial-gradient(ellipse 60% 40% at 90% 90%, rgba(245,158,11,0.05) 0%, transparent 60%);
    padding-left: 270px;
    min-height: 100vh;
    font-family: 'Sarabun', sans-serif;
    color: #1e293b;
}
@media(max-width:1024px){ body { padding-left: 0; } }

/* ─── CARD ─── */
.glass-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 20px rgba(0,0,0,0.05);
}

/* ─── TABS ─── */
.tab-bar {
    border-radius: 13px;
    padding: 4px;
    display: flex;
    gap: 3px;
    border: 1px solid rgba(255,255,255,0.2);
    transition: background 0.4s ease;
}
.tab-pill {
    padding: 8px 20px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.65);
    transition: all 0.25s;
    font-family: 'Sarabun', sans-serif;
    letter-spacing: 0.01em;
}
.tab-pill.active { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.13); }
.tab-pill.active.recv { color: #0f766e; }
.tab-pill.active.sent { color: #b45309; }
.tab-pill:not(.active):hover { background: rgba(255,255,255,0.18); color: white; }

/* ─── FORM INPUTS ─── */
.form-input {
    border: 1.5px solid #e2e8f0;
    background: #fafbfc;
    color: #1e293b;
    border-radius: 10px;
    padding: 9px 13px;
    font-size: 13.5px;
    font-family: 'Sarabun', sans-serif;
    width: 100%;
    transition: all 0.2s;
}
.form-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    outline: none;
    background: white;
}
.form-input[readonly] {
    background: #f1f5f9;
    color: #64748b;
    cursor: not-allowed;
    border-color: #e2e8f0;
}
.form-input::placeholder { color: #94a3b8; }
textarea.form-input { resize: none; line-height: 1.65; }

.field-label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: #64748b;
    margin-bottom: 5px;
    font-family: 'Mitr', sans-serif;
}
.req { color: #f43f5e; }

/* ─── COMPANY DROPDOWN ─── */
.co-dd {
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    background: white;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    max-height: 260px;
    overflow-y: auto;
    z-index: 60;
    box-shadow: 0 12px 32px rgba(0,0,0,0.1);
    display: none;
}
.co-dd-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 13px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    transition: background 0.15s;
}
.co-dd-item:last-child { border-bottom: none; }
.co-dd-item:hover { background: #eff6ff; color: #2563eb; }
.co-dd-img { width: 26px; height: 26px; object-fit: contain; border-radius: 5px; border: 1px solid #e2e8f0; }
.co-dd-ph { width: 26px; height: 26px; border-radius: 5px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #94a3b8; }

/* ─── DROP ZONE ─── */
.drop-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 22px 16px;
    text-align: center;
    cursor: pointer;
    background: #f8fafc;
    transition: all 0.22s;
}
.drop-zone:hover, .drop-zone.drag-over {
    border-color: #3b82f6;
    background: #eff6ff;
}

/* ─── FILE ITEM (รูปแบบใหม่ — card แทน chip) ─── */
.file-list-group { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }

.file-item-card {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 8px 10px;
    font-size: 12px;
}
.file-card-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    overflow: hidden;
    background: #e2e8f0;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.file-card-icon img { width: 100%; height: 100%; object-fit: cover; }
.file-card-info { flex: 1; min-width: 0; }
.file-card-name {
    font-weight: 600;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
    font-size: 12.5px;
}
.file-card-meta { color: #94a3b8; font-size: 10.5px; margin-top: 1px; display: block; }
.btn-file-delete {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    border: none;
    background: transparent;
    border-radius: 7px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    transition: all 0.15s;
}
.btn-file-delete:hover { background: #fee2e2; color: #ef4444; }

/* ─── SUBMIT BUTTON ─── */
.btn-save {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
    font-family: 'Mitr', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.25s;
    color: white;
    letter-spacing: 0.02em;
}
.btn-save:hover { transform: translateY(-2px); filter: brightness(1.06); }
.btn-save:active { transform: none; }
.btn-save.recv {
    background: linear-gradient(135deg, #0f766e, #0d9488);
    box-shadow: 0 4px 14px rgba(13,148,136,0.28);
}
.btn-save.sent {
    background: linear-gradient(135deg, #b45309, #d97706);
    box-shadow: 0 4px 14px rgba(245,158,11,0.28);
}

/* ─── DATA TABLE ─── */
.data-table { table-layout: fixed; width: 100%; border-collapse: collapse; }
.th-col {
    background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
    padding: 11px 12px;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    border-bottom: 2px solid #e2e8f0;
    position: sticky;
    top: 0;
    z-index: 5;
    white-space: nowrap;
    font-family: 'Mitr', sans-serif;
}
tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.13s; cursor: pointer; }
tbody tr:hover { background: #f8fafc; }
tbody td {
    padding: 11px 12px;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #475569;
}

/* ─── FILE BADGE ─── */
.file-badge {
    position: absolute;
    top: -5px; right: -5px;
    background: #ef4444;
    color: white;
    font-size: 9px;
    font-weight: 700;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

/* ─── ACTION BUTTONS ─── */
.action-btn-view {
    position: relative;
    padding: 6px 8px;
    border-radius: 8px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #2563eb;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all 0.15s;
}
.action-btn-view:hover { background: #dbeafe; }
.action-btn-edit {
    padding: 6px 8px;
    border-radius: 8px;
    border: 1px solid #fde68a;
    background: #fffbeb;
    color: #d97706;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all 0.15s;
}
.action-btn-edit:hover { background: #fef3c7; }

/* ─── MODAL ─── */
#detailModal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 999;
    background: rgba(15,23,42,0.5);
    backdrop-filter: blur(10px);
    align-items: center;
    justify-content: center;
}
#detailModal.open { display: flex; animation: mFadeIn .22s ease; }
#detailModal.open .modal-box { animation: mSlideUp .32s cubic-bezier(.16,1,.3,1); }
@keyframes mFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes mSlideUp { from { transform: translateY(32px) scale(.97); opacity: 0; } to { transform: none; opacity: 1; } }

.modal-box {
    background: white;
    width: 100%;
    max-width: 660px;
    margin: 16px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    max-height: 92vh;
    box-shadow: 0 24px 64px rgba(15,23,42,0.18);
    overflow: hidden;
}
.modal-hd {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 18px;
    border-bottom: 1px solid #f1f5f9;
}
.modal-icon {
    width: 42px; height: 42px;
    border-radius: 12px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-title-area { flex: 1; min-width: 0; }
.modal-type {
    font-size: 9.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    margin-bottom: 3px;
    font-family: 'Mitr', sans-serif;
}
.modal-title-text {
    font-size: 14.5px;
    font-weight: 700;
    color: #1e293b;
    font-family: 'Mitr', sans-serif;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}
.modal-close {
    flex-shrink: 0;
    width: 32px; height: 32px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    color: #64748b;
    transition: all 0.15s;
}
.modal-close:hover { background: #fee2e2; border-color: #fca5a5; color: #ef4444; }
.modal-bd { flex: 1; overflow-y: auto; padding: 18px; min-height: 0; display: flex; flex-direction: column; gap: 12px; }
.modal-ft { flex-shrink: 0; padding: 13px 18px; border-top: 1px solid #f1f5f9; background: #fafafa; }

.m-cell {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 11px 13px;
}
.m-lbl {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.13em;
    color: #94a3b8;
    margin-bottom: 4px;
    font-family: 'Mitr', sans-serif;
}
.m-val { font-size: 13.5px; font-weight: 600; color: #1e293b; word-break: break-word; }

.img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(82px, 1fr)); gap: 7px; }
.img-item {
    border-radius: 9px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    aspect-ratio: 1;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.2s;
}
.img-item:hover { border-color: #3b82f6; transform: scale(1.04); }
.img-item img { width: 100%; height: 100%; object-fit: cover; }

/* ─── ANIMATIONS ─── */
.anim-in { animation: fadeUp 0.4s ease both; }
.anim-in:nth-child(2) { animation-delay: .06s; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

/* ─── SCROLLBAR ─── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: #f1f5f9; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="p-4 lg:p-8 max-w-[1600px] mx-auto space-y-5">

    <!-- ─── HEADER ─── -->
    <div class="glass-card p-5 anim-in" style="position:relative;overflow:hidden">
        <!-- accent bar top -->
        <div id="hdrAccentBar" style="position:absolute;top:0;left:0;right:0;height:3px;border-radius:18px 18px 0 0;background:linear-gradient(90deg,#0f766e,#2dd4bf);transition:background 0.4s ease"></div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div id="hdrIcon" class="w-14 h-14 rounded-2xl flex items-center justify-center shadow-sm flex-shrink-0"
                    style="background:linear-gradient(135deg,#ccfbf1,#99f6e4);border:1px solid #5eead4;transition:all 0.4s ease">
                    <i data-lucide="inbox" class="w-7 h-7" id="hdrIconEl" style="color:#0f766e"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-0.5" style="font-family:'Mitr',sans-serif">
                        ระบบสารบรรณ &bull; Document Management
                    </p>
                    <h1 class="text-2xl lg:text-3xl font-bold text-slate-800" id="pageTitle" style="font-family:'Mitr',sans-serif">สมุดทะเบียนรับ</h1>
                    <p class="text-xs text-slate-400 mt-0.5">บันทึกและจัดการหนังสือเข้า-ออก</p>
                </div>
            </div>
            <div class="tab-bar" id="tabBar" style="background:linear-gradient(135deg,#0f766e,#0d9488)">
                <button class="tab-pill active recv" id="tabRecv" onclick="switchTab('received')">
                    <i data-lucide="inbox" class="w-4 h-4"></i> ทะเบียนรับ
                </button>
                <button class="tab-pill" id="tabSent" onclick="switchTab('sent')">
                    <i data-lucide="send" class="w-4 h-4"></i> ทะเบียนส่ง
                </button>
            </div>
        </div>
    </div>

    <!-- ─── MAIN GRID ─── -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 anim-in" style="animation-delay:.06s">

        <!-- ─── FORM ─── -->
        <div class="xl:col-span-4">
            <div class="glass-card p-5 sticky top-5 border-t-4 transition-colors duration-300" id="formCard" style="border-top-color:#0f766e">
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2" style="font-family:'Mitr',sans-serif">
                        <span class="w-7 h-7 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center">
                            <i data-lucide="pen-tool" class="w-3.5 h-3.5 text-blue-500"></i>
                        </span>บันทึกข้อมูล
                    </h3>
                    <button onclick="resetForm()" class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 hover:bg-red-50 hover:text-red-500 transition-all flex items-center gap-1.5 font-semibold">
                        <i data-lucide="rotate-ccw" class="w-3 h-3"></i> รีเซ็ต
                    </button>
                </div>

                <form id="entryForm" onsubmit="saveEntry(event)" class="space-y-4">
                    <input type="hidden" name="id"        id="entryId">
                    <input type="hidden" name="book_type" id="bookType" value="received">
                    <input type="hidden" name="creator"   value="<?php echo htmlspecialchars($user_fullname); ?>">

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label">เลขทะเบียน <span class="req">*</span></label>
                            <input type="text" name="reg_no" id="regNo" class="form-input font-mono font-bold text-blue-600" placeholder="รหัส..." required>
                        </div>
                        <div>
                            <label class="field-label">วันที่</label>
                            <input type="text" name="book_date" id="bookDate" class="form-input text-center cursor-pointer" required>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-xl border border-slate-200/80 p-3.5 space-y-3">
                        <div>
                            <label class="field-label" id="lblSender">จาก (ผู้ส่งภายนอก)</label>
                            <div class="relative">
                                <input type="text" name="sender" id="sender" class="form-input" style="padding-left:35px" placeholder="ระบุผู้ส่ง...">
                                <i data-lucide="user" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                        <div>
                            <label class="field-label" id="lblReceiver">ผู้เขียน / ผู้รับผิดชอบ</label>
                            <div class="relative">
                                <input type="text" name="internal_staff" id="internalStaff"
                                    class="form-input" style="padding-left:35px;padding-right:28px"
                                    value="<?php echo htmlspecialchars($user_fullname); ?>" readonly>
                                <i data-lucide="user-check" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400 pointer-events-none"></i>
                                <i data-lucide="lock" class="absolute right-3 top-3 w-3 h-3 text-slate-300 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">เรื่อง / รายละเอียด <span class="req">*</span></label>
                        <textarea name="subject" id="subject" rows="3" class="form-input" placeholder="กรอกรายละเอียด..." required></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label">ที่หนังสือ</label>
                            <input type="text" name="ref_no" id="refNo" class="form-input" placeholder="-">
                        </div>
                        <div>
                            <label class="field-label">หน่วยงาน / บริษัท</label>
                            <div class="relative">
                                <input type="text" name="company" id="companyInput" class="form-input cursor-pointer"
                                    style="padding-left:34px" placeholder="เลือกหรือพิมพ์..."
                                    onfocus="showCoDd()" oninput="filterCoDd(this.value)" autocomplete="off">
                                <i data-lucide="building-2" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400 pointer-events-none"></i>
                                <div id="coDropdown" class="co-dd"></div>
                            </div>
                        </div>
                    </div>

                    <div style="display:none;">
                        <label class="field-label">แนบไฟล์เอกสาร</label>
                        <div class="drop-zone" id="dropZone"
                            onclick="document.getElementById('attachInput').click()"
                            ondragover="event.preventDefault();this.classList.add('drag-over')"
                            ondragleave="this.classList.remove('drag-over')"
                            ondrop="event.preventDefault();this.classList.remove('drag-over');handleFiles(event.dataTransfer.files)">
                            <div class="flex flex-col items-center gap-1.5">
                                <span class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-400">
                                    <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                                </span>
                                <p class="text-sm font-semibold text-slate-500">คลิกหรือลากไฟล์มาวาง</p>
                                <p class="text-xs text-slate-400">รูปภาพ / PDF</p>
                            </div>
                            <input type="file" id="attachInput" name="attachments[]" multiple accept="image/*,.pdf" class="hidden" onchange="handleFiles(this.files)">
                        </div>
                        <input type="hidden" name="old_attachments" id="oldAttachments">
                        <div id="newFileList"      class="mt-2"></div>
                        <div id="existingFileList" class="mt-1.5"></div>
                    </div>

                    <button type="submit" class="btn-save recv" id="btnSave">
                        <i data-lucide="save" class="w-4 h-4"></i> บันทึกข้อมูล
                    </button>
                </form>
            </div>
        </div>

        <!-- ─── TABLE ─── -->
        <div class="xl:col-span-8">
            <div class="glass-card overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 flex flex-wrap gap-3 items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-1 h-5 rounded-full transition-colors duration-300" id="tblBar" style="background:#0f766e"></span>
                        <h3 class="font-bold text-slate-700 text-sm" id="tblTitle" style="font-family:'Mitr',sans-serif">รายการล่าสุด — ทะเบียนรับ</h3>
                        <span class="text-xs text-slate-400" id="tblCount"></span>
                    </div>
                    <div class="flex gap-2">
                        <div class="relative">
                            <input type="text" id="searchInput" class="form-input text-sm py-2" style="width:190px;padding-left:33px" placeholder="ค้นหา...">
                            <i data-lucide="search" class="absolute left-3 top-2.5 w-3.5 h-3.5 text-slate-400 pointer-events-none"></i>
                        </div>
                        <input type="text" id="filterDate" class="form-input text-sm py-2 text-center" style="width:116px" placeholder="📅 วันที่">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="th-col text-center" style="width:44px">#</th>
                                <th class="th-col" style="width:90px">วันที่</th>
                                <th class="th-col" style="width:120px">เลขทะเบียน</th>
                                <th class="th-col" style="width:130px" id="thSender">ผู้ส่ง</th>
                                <th class="th-col" style="width:110px">หน่วยงาน</th>
                                <th class="th-col" style="width:95px">อ้างอิง</th>
                                <th class="th-col">เรื่อง</th>
                                <th class="th-col text-center" style="width:82px">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="8" class="p-10 text-center text-slate-400 text-sm">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-2.5 border-t border-slate-100 bg-slate-50/50 flex justify-between">
                    <span class="text-xs text-slate-400">คลิกที่แถวเพื่อดูรายละเอียด</span>
                    <span class="text-xs text-slate-400" id="tblFooter"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── MODAL ─── -->
<div id="detailModal">
    <div class="modal-box">
        <div class="modal-hd" id="modalHd">
            <div class="modal-icon" id="modalIconWrap">
                <i id="modalIconEl" class="w-5 h-5 text-white"></i>
            </div>
            <div class="modal-title-area">
                <p class="modal-type" id="modalType"></p>
                <div class="modal-title-text" id="modalTitle"></div>
            </div>
            <button class="modal-close" onclick="closeModal()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="modal-bd">
            <div class="grid grid-cols-2 gap-3">
                <div class="m-cell"><div class="m-lbl">เลขทะเบียน</div><div class="m-val font-mono text-blue-600" id="mRegNo">-</div></div>
                <div class="m-cell"><div class="m-lbl">วันที่</div><div class="m-val" id="mBookDate">-</div></div>
            </div>
            <div style="background:linear-gradient(135deg,#eff6ff,#f5f3ff);border:1px solid #c7d2fe;border-radius:10px;padding:13px">
                <div class="m-lbl" style="color:#6366f1">เรื่อง / รายละเอียด</div>
                <div style="font-size:13.5px;font-weight:500;color:#334155;word-break:break-word;white-space:pre-wrap;line-height:1.65" id="mSubject">-</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="m-cell" style="background:#f0fdf4;border-color:#bbf7d0">
                    <div class="m-lbl" style="color:#16a34a" id="mLblFrom">จาก</div>
                    <div class="m-val" id="mSender">-</div>
                </div>
                <div class="m-cell" style="background:#fffbeb;border-color:#fde68a">
                    <div class="m-lbl" style="color:#d97706" id="mLblTo">ผู้เขียน</div>
                    <div class="m-val" id="mReceiver">-</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="m-cell"><div class="m-lbl">หน่วยงาน</div><div class="m-val text-blue-600" id="mCompany">-</div></div>
                <div class="m-cell"><div class="m-lbl">เลขที่อ้างอิง</div><div class="m-val" id="mRefNo">-</div></div>
            </div>
            <?php if($is_admin): ?>
            <div class="m-cell" style="background:#eff6ff;border-color:#bfdbfe">
                <div class="m-lbl" style="color:#2563eb">ผู้บันทึก</div>
                <div class="m-val text-blue-700" id="mCreator">-</div>
            </div>
            <?php endif; ?>
            <div>
                <div class="m-lbl mb-2 flex items-center gap-1.5">
                    <i data-lucide="paperclip" style="width:11px;height:11px;display:inline"></i> ไฟล์แนบ
                </div>
                <div id="mGallery" class="img-grid"></div>
            </div>
        </div>

        <div class="modal-ft">
            <button onclick="closeModal()" class="w-full py-2.5 rounded-xl border-2 border-slate-200 text-slate-600 hover:bg-slate-50 font-semibold transition-all flex justify-center items-center gap-2 text-sm" style="font-family:'Mitr',sans-serif">
                <i data-lucide="x" class="w-4 h-4"></i> ปิด
            </button>
        </div>
    </div>
</div>

<script>
/* ───────────────────────────────────────────
   JS ทุกอย่างเหมือนเดิม 100% — ไม่มีการแก้ไข logic
─────────────────────────────────────────── */
const CUR_USER = "<?php echo htmlspecialchars($user_fullname); ?>";
const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
const CO_DATA  = <?php echo json_encode($companies_data); ?>;

let curType = 'received', selFiles = [], fpDate, fpFilter;

document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    const cfg = {locale:'th',dateFormat:'Y-m-d',altInput:true,altFormat:'d/m/Y',disableMobile:true};
    fpDate   = flatpickr('#bookDate',   {...cfg, defaultDate:'today'});
    fpFilter = flatpickr('#filterDate', {...cfg, onChange: loadData});
    document.getElementById('searchInput').addEventListener('input', () => { clearTimeout(window._st); window._st = setTimeout(loadData, 400); });
    document.addEventListener('click', e => {
        const dd = document.getElementById('coDropdown'), inp = document.getElementById('companyInput');
        if (dd && inp && !inp.contains(e.target) && !dd.contains(e.target)) dd.style.display = 'none';
    });
    renderCoDd(CO_DATA);
    switchTab('received');
});

/* Company dropdown */
function renderCoDd(data) {
    const dd = document.getElementById('coDropdown');
    dd.innerHTML = !data.length ? '<div style="padding:12px;font-size:13px;color:#94a3b8;text-align:center">ไม่พบข้อมูล</div>' : '';
    data.forEach(item => {
        const div = document.createElement('div');
        div.className = 'co-dd-item';
        div.onclick = () => { document.getElementById('companyInput').value = item.company_name; dd.style.display = 'none'; };
        div.innerHTML = (item.logo_file ? `<img src="uploads/logos/${item.logo_file}" class="co-dd-img">` : `<div class="co-dd-ph">${item.company_name.charAt(0)}</div>`) + `<span>${item.company_name}</span>`;
        dd.appendChild(div);
    });
}
function showCoDd()   { document.getElementById('coDropdown').style.display = 'block'; }
function filterCoDd(v){ renderCoDd(CO_DATA.filter(c => c.company_name.toLowerCase().includes(v.toLowerCase()))); document.getElementById('coDropdown').style.display = 'block'; }

/* Tab switch */
function switchTab(type) {
    curType = type;
    document.getElementById('bookType').value = type;
    const R = type === 'received';
    const COLOR = R ? '#0f766e' : '#b45309';
    const GRAD  = R ? 'linear-gradient(135deg,#0f766e,#0d9488)' : 'linear-gradient(135deg,#b45309,#d97706)';

    document.getElementById('pageTitle').textContent = R ? 'สมุดทะเบียนรับ' : 'สมุดทะเบียนส่ง';
    document.getElementById('hdrAccentBar').style.background = R ? 'linear-gradient(90deg,#0f766e,#2dd4bf)' : 'linear-gradient(90deg,#b45309,#f59e0b)';
    const hi = document.getElementById('hdrIcon');
    hi.style.background   = R ? 'linear-gradient(135deg,#ccfbf1,#99f6e4)' : 'linear-gradient(135deg,#fef3c7,#fde68a)';
    hi.style.borderColor  = R ? '#5eead4' : '#fcd34d';
    hi.innerHTML = `<i data-lucide="${R?'inbox':'send'}" class="w-7 h-7" style="color:${COLOR}"></i>`;
    document.getElementById('tabBar').style.background = GRAD;
    document.getElementById('tabRecv').className = 'tab-pill' + (R  ? ' active recv' : '');
    document.getElementById('tabSent').className = 'tab-pill' + (!R ? ' active sent' : '');
    document.getElementById('formCard').style.borderTopColor = COLOR;
    document.getElementById('btnSave').className = 'btn-save ' + (R ? 'recv' : 'sent');
    document.getElementById('tblBar').style.background = COLOR;
    document.getElementById('tblTitle').textContent = R ? 'รายการล่าสุด — ทะเบียนรับ' : 'รายการล่าสุด — ทะเบียนส่ง';
    document.getElementById('thSender').textContent = R ? 'ผู้ส่ง' : 'ผู้เขียน';
    document.getElementById('lblSender').textContent   = R ? 'จาก (ผู้ส่งภายนอก)' : 'ผู้เขียน / เจ้าของเรื่อง (ภายใน)';
    document.getElementById('lblReceiver').textContent = R ? 'ผู้เขียน / ผู้รับผิดชอบ (ภายใน)' : 'ผู้ลงนาม / อนุมัติ';
    const inpS = document.getElementById('sender'), inpR = document.getElementById('internalStaff');
    if (R) { inpS.value='';inpS.readOnly=false;inpS.classList.remove('cursor-not-allowed'); inpR.value=CUR_USER;inpR.readOnly=true;inpR.classList.add('cursor-not-allowed'); }
    else   { inpS.value=CUR_USER;inpS.readOnly=true;inpS.classList.add('cursor-not-allowed'); inpR.value='';inpR.readOnly=false;inpR.classList.remove('cursor-not-allowed'); }
    loadData(); lucide.createIcons();
}

/* Load + Render */
async function loadData() {
    const q = encodeURIComponent(document.getElementById('searchInput').value);
    const d = document.getElementById('filterDate').value;
    const u = `&user=${encodeURIComponent(CUR_USER)}`;
    try { const r = await fetch(`api_correspondence.php?action=fetch&type=${curType}&search=${q}&start=${d}&end=${d}${u}`); renderTable(await r.json()); } catch(e){}
}

function renderTable(data) {
    const tb = document.getElementById('tableBody'), cnt = document.getElementById('tblCount'), ft = document.getElementById('tblFooter');
    tb.innerHTML = '';
    if (!data || !data.length) { 
        tb.innerHTML='<tr><td colspan="8" class="p-10 text-center text-slate-400 italic text-sm">ไม่พบข้อมูลในระบบ</td></tr>'; 
        cnt.textContent=''; ft.textContent=''; return; 
    }
    
    cnt.textContent = `(${data.length} รายการ)`; 
    ft.textContent = `แสดง ${data.length} รายการ`;

    data.forEach((item, i) => {
        // เงื่อนไขตรวจสอบสิทธิ์: เป็น Admin หรือเป็นคนสร้างงานนี้เอง
        const canDelete = IS_ADMIN || item.creator === CUR_USER;
        
        const d = new Date(item.book_date), dTh = `${d.getDate()}/${d.getMonth()+1}/${d.getFullYear()+543}`;
        
        const icn = item.book_type === 'received'
            ? `<i data-lucide="inbox" class="w-3 h-3 inline-block" style="color:#0f766e"></i>`
            : `<i data-lucide="send"  class="w-3 h-3 inline-block" style="color:#b45309"></i>`;

        // สร้างปุ่มลบเฉพาะเมื่อมีสิทธิ์
        const deleteBtn = canDelete ? `
            <button onclick="deleteEntry(${item.id}, '${item.reg_no}')" 
                    class="p-1.5 rounded-lg border border-red-200 bg-red-50 text-red-500 hover:bg-red-100 transition-all flex items-center" 
                    title="ลบรายการ">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
        ` : '';

        tb.innerHTML += `<tr onclick="openModal(${item.id})">
            <td class="text-center text-xs font-bold text-slate-400">${i+1}</td>
            <td class="font-mono text-xs text-slate-400">${dTh}</td>
            <td><span class="flex items-center gap-1.5">${icn}<span class="font-bold text-blue-600 font-mono text-sm" style="font-family:'Mitr',sans-serif">${item.reg_no}</span></span></td>
            <td>${item.sender||'—'}</td>
            <td class="text-xs text-slate-400">${item.company||'—'}</td>
            <td class="font-mono text-xs text-slate-400">${item.ref_no||'—'}</td>
            <td class="font-medium text-slate-700" title="${item.subject}">${item.subject}</td>
            <td class="text-center" onclick="event.stopPropagation()">
                <div class="flex justify-center gap-1.5">
                    <button onclick="openModal(${item.id})" class="action-btn-view">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        ${item.file_count > 0 ? `<span class="file-badge">${item.file_count}</span>` : ''}
                    </button>
                    <button onclick='editEntry(${JSON.stringify(item)})' class="action-btn-edit">
                        <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                    </button>
                    ${deleteBtn}
                </div>
            </td>
        </tr>`;
    });
    lucide.createIcons();
}
async function deleteEntry(id, regNo) {
    const result = await Swal.fire({
        title: 'ยืนยันการลบ?',
        text: `คุณต้องการลบเลขทะเบียน ${regNo} ใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก'
    });

    if (result.isConfirmed) {
        try {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            const r = await fetch('api_correspondence.php', { method: 'POST', body: fd });
            const j = await r.json();

            if (j.success) {
                Swal.fire({ icon: 'success', title: 'ลบเรียบร้อย', timer: 1000, showConfirmButton: false });
                loadData(); // รีโหลดตาราง
            } else {
                throw new Error(j.message || 'ลบไม่สำเร็จ');
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: err.message });
        }
    }
}
/* Save */
async function saveEntry(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('entryForm'));
    fd.delete('attachments[]'); selFiles.forEach(f => fd.append('attachments[]', f)); fd.append('action','save');
    Swal.fire({title:'กำลังบันทึก...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    try {
        const r = await fetch('api_correspondence.php', {method:'POST',body:fd}), j = await r.json();
        if (j.success) { Swal.fire({icon:'success',title:'บันทึกสำเร็จ',timer:1500,showConfirmButton:false}); resetForm(); loadData(); }
        else throw new Error(j.message||'Error');
    } catch(err) { Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด',text:err.message}); }
}

/* Reset */
function resetForm() {
    document.getElementById('entryForm').reset();
    ['entryId','oldAttachments'].forEach(id => document.getElementById(id).value = '');
    selFiles = [];
    document.getElementById('newFileList').innerHTML = document.getElementById('existingFileList').innerHTML = '';
    if (fpDate) fpDate.setDate('today', true);
    switchTab(curType);
}

/* Edit */
function editEntry(item) {
    document.getElementById('entryId').value = item.id;
    document.getElementById('regNo').value   = item.reg_no;
    document.getElementById('subject').value = item.subject;
    document.getElementById('refNo').value   = item.ref_no   || '';
    document.getElementById('companyInput').value = item.company || '';
    document.getElementById('sender').value  = item.sender   || '';
    if (fpDate) fpDate.setDate(item.book_date, true);
    document.getElementById('existingFileList').innerHTML = '';
    fetch(`api_correspondence.php?action=get_single&id=${item.id}`).then(r=>r.json()).then(json => {
        if (json.status==='success') {
            document.getElementById('internalStaff').value = json.result.internal_staff || '';
            if (json.result.files && json.result.files.length) {
                const names = json.result.files.map(f => f.file_name);
                document.getElementById('oldAttachments').value = names.join(',');
                renderExistingPreviews(names);
            }
        }
    });
    if (item.book_type !== curType) switchTab(item.book_type);
    window.scrollTo({top:0, behavior:'smooth'});
}

/* File handling */
function handleFiles(files) { for (let f of files) selFiles.push(f); renderNewPreviews(); }

function renderNewPreviews() {
    const container = document.getElementById('newFileList');
    container.innerHTML = '';
    container.className = 'file-list-group';
    if (selFiles.length > 0) {
        const hdr = document.createElement('div');
        hdr.style.cssText = "font-size:11px;font-weight:700;color:#3b82f6;margin:4px 0;";
        hdr.innerHTML = '<i data-lucide="plus-circle" style="width:12px;height:12px;display:inline;margin-right:4px"></i>ไฟล์ใหม่ที่กำลังจะเพิ่ม:';
        container.appendChild(hdr);
    }
    selFiles.forEach((f, index) => {
        const ext = f.name.split('.').pop().toLowerCase();
        const isIm = ['jpg','jpeg','png','webp','gif'].includes(ext);
        const blobUrl = isIm ? URL.createObjectURL(f) : null;
        const sizeMB = (f.size/(1024*1024)).toFixed(2);
        const item = document.createElement('div');
        item.className = 'file-item-card';
        item.style.borderColor = '#bfdbfe'; item.style.background = '#eff6ff';
        item.innerHTML = `
            <div class="file-card-icon" style="background:white">
                ${isIm ? `<img src="${blobUrl}" alt="preview">` : `<i data-lucide="upload" style="width:16px;height:16px;color:#3b82f6"></i>`}
            </div>
            <div class="file-card-info">
                <span class="file-card-name" title="${f.name}">${f.name}</span>
                <span class="file-card-meta" style="color:#60a5fa">ไฟล์ใหม่ • ${sizeMB} MB</span>
            </div>
            <button type="button" class="btn-file-delete" onclick="removeNewFile(${index})">
                <i data-lucide="x" style="width:12px;height:12px"></i>
            </button>`;
        container.appendChild(item);
    });
    if(typeof lucide!=='undefined') lucide.createIcons();
}

function removeNewFile(index) { selFiles.splice(index, 1); renderNewPreviews(); }

function renderExistingPreviews(files) {
    const container = document.getElementById('existingFileList');
    container.innerHTML = '';
    container.className = 'file-list-group';
    if (!files || !files.length) return;
    const hdr = document.createElement('div');
    hdr.style.cssText = "font-size:11px;font-weight:700;color:#64748b;margin:4px 0;";
    hdr.innerHTML = '<i data-lucide="folder-open" style="width:12px;height:12px;display:inline;margin-right:4px"></i>ไฟล์เดิมที่มีอยู่:';
    container.appendChild(hdr);
    files.forEach(f => {
        const ext = f.split('.').pop().toLowerCase();
        const isIm = ['jpg','jpeg','png','webp','gif'].includes(ext);
        const path = `uploads/docs/${f}`;
        const item = document.createElement('div');
        item.className = 'file-item-card';
        item.innerHTML = `
            <div class="file-card-icon" onclick="window.open('${path}','_blank')">
                ${isIm ? `<img src="${path}" alt="img">` : `<i data-lucide="file-text" style="width:16px;height:16px;color:#64748b"></i>`}
            </div>
            <div class="file-card-info">
                <span class="file-card-name" title="${f}">${f}</span>
                <span class="file-card-meta">ไฟล์เดิม • ${ext.toUpperCase()}</span>
            </div>
            <button type="button" class="btn-file-delete" onclick="removeExistingFile('${f}')">
                <i data-lucide="trash-2" style="width:50px;height:50px"></i>
            </button>`;
        container.appendChild(item);
    });
    if(typeof lucide!=='undefined') lucide.createIcons();
}

function removeExistingFile(fn) {
    Swal.fire({
        title:'ยืนยันลบไฟล์?', text:`ต้องการลบไฟล์ "${fn}" ออกใช่ไหม?`,
        icon:'warning', showCancelButton:true,
        confirmButtonColor:'#d33', cancelButtonColor:'#3085d6',
        confirmButtonText:'ลบเลย!', cancelButtonText:'ยกเลิก'
    }).then(result => {
        if (result.isConfirmed) {
            const h = document.getElementById('oldAttachments');
            const arr = (h.value?h.value.split(','):[]).filter(f=>f!==fn);
            h.value = arr.join(',');
            renderExistingPreviews(arr);
        }
    });
}

/* Modal */
async function openModal(id) {
    Swal.showLoading();
    const r = await fetch(`api_correspondence.php?action=get_single&id=${id}`), json = await r.json();
    Swal.close();
    if (json.status !== 'success') return;
    const item = json.result;
    document.getElementById('detailModal').classList.add('open');
    const set = (id,v) => { const el=document.getElementById(id); if(el) el.textContent=v||'-'; };
    set('mRegNo', item.reg_no);
    const d = new Date(item.book_date); set('mBookDate', `${d.getDate()}/${d.getMonth()+1}/${d.getFullYear()+543}`);
    set('mSubject',  item.subject);
    set('mSender',   item.sender);
    set('mReceiver', item.internal_staff);
    set('mCompany',  item.company);
    set('mRefNo',    item.ref_no);
    if (IS_ADMIN) set('mCreator', item.creator);
    const R = item.book_type === 'received';
    document.getElementById('modalHd').style.background       = R ? 'linear-gradient(to right,#f0fdfa,#ecfdf5)' : 'linear-gradient(to right,#fffbeb,#fff7ed)';
    document.getElementById('modalIconWrap').style.background = R ? 'linear-gradient(135deg,#0f766e,#0d9488)'   : 'linear-gradient(135deg,#b45309,#d97706)';
    document.getElementById('modalIconEl').setAttribute('data-lucide', R ? 'inbox' : 'send');
    const mt = document.getElementById('modalType'); mt.textContent = R ? 'ทะเบียนรับ' : 'ทะเบียนส่ง'; mt.style.color = R ? '#0f766e' : '#b45309';
    document.getElementById('modalTitle').textContent = item.subject;
    document.getElementById('mLblFrom').textContent = R ? 'จาก'      : 'ผู้เขียน';
    document.getElementById('mLblTo').textContent   = R ? 'ผู้เขียน' : 'ผู้ลงนาม';
    const g = document.getElementById('mGallery'); g.innerHTML = '';
    if (item.files && item.files.length) {
        item.files.forEach(f => {
            const path = `uploads/docs/${f.file_name}`, ext = f.file_name.split('.').pop().toLowerCase();
            const div = document.createElement('div');
            if (['jpg','jpeg','png','webp'].includes(ext)) { div.className='img-item'; div.innerHTML=`<img src="${path}" alt="">`; }
            else {
                div.style.cssText='padding:9px;border:2px solid #e2e8f0;border-radius:9px;display:flex;align-items:center;gap:5px;cursor:pointer;background:#f8fafc;transition:border-color .15s';
                div.onmouseenter=()=>div.style.borderColor='#3b82f6'; div.onmouseleave=()=>div.style.borderColor='#e2e8f0';
                div.innerHTML=`<i data-lucide="file-text" style="width:15px;height:15px;color:#3b82f6"></i><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:#64748b">${ext}</span>`;
            }
            div.onclick=()=>window.open(path); g.appendChild(div);
        });
    } else g.innerHTML='<span style="font-size:13px;color:#94a3b8;font-style:italic">ไม่มีไฟล์แนบ</span>';
    lucide.createIcons();
}
function closeModal() { document.getElementById('detailModal').classList.remove('open'); }
</script>
</body>
</html>