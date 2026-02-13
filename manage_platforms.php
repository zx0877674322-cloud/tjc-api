<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// =============================================
// 1. CONFIGURATION & SETUP
// =============================================
$upload_dir = 'uploads/platforms/';
if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }

// Auto Update DB
$conn->query("ALTER TABLE marketing_platforms ADD COLUMN IF NOT EXISTS platform_image VARCHAR(255) DEFAULT NULL");

// =============================================
// 2. HANDLE ACTIONS (ADD / UPDATE / DELETE)
// =============================================

// --- A. เพิ่มข้อมูลใหม่ (ADD) ---
if (isset($_POST['add_platform'])) {
    $name = trim($_POST['platform_name']);
    $image_filename = NULL;

    if (isset($_FILES['platform_image']) && $_FILES['platform_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['platform_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $new_filename = 'logo_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['platform_image']['tmp_name'], $upload_dir . $new_filename)) {
                $image_filename = $new_filename;
            }
        }
    }

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO marketing_platforms (platform_name, platform_image) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $image_filename);
        $stmt->execute();
    }
    header("Location: manage_platforms.php"); exit();
}

// --- B. อัปเดตข้อมูล (UPDATE) ---
if (isset($_POST['update_platform'])) {
    $id = intval($_POST['platform_id']);
    $name = trim($_POST['platform_name']);
    
    // ดึงข้อมูลเก่าเพื่อดูชื่อไฟล์รูปเดิม
    $old_res = $conn->query("SELECT platform_image FROM marketing_platforms WHERE id=$id");
    $old_row = $old_res->fetch_assoc();
    $image_filename = $old_row['platform_image']; // ค่าเริ่มต้นคือรูปเดิม

    // ถ้ามีการอัปโหลดรูปใหม่
    if (isset($_FILES['platform_image']) && $_FILES['platform_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['platform_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $new_filename = 'logo_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['platform_image']['tmp_name'], $upload_dir . $new_filename)) {
                // ลบรูปเก่าทิ้ง (ถ้ามี)
                if (!empty($old_row['platform_image']) && file_exists($upload_dir . $old_row['platform_image'])) {
                    unlink($upload_dir . $old_row['platform_image']);
                }
                $image_filename = $new_filename; // อัปเดตเป็นรูปใหม่
            }
        }
    }

    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE marketing_platforms SET platform_name=?, platform_image=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $image_filename, $id);
        $stmt->execute();
    }
    header("Location: manage_platforms.php"); exit();
}

// --- C. ลบข้อมูล (DELETE) ---
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $res = $conn->query("SELECT platform_image FROM marketing_platforms WHERE id = $id");
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['platform_image']) && file_exists($upload_dir . $row['platform_image'])) {
            unlink($upload_dir . $row['platform_image']);
        }
    }
    $conn->query("DELETE FROM marketing_platforms WHERE id = $id");
    header("Location: manage_platforms.php"); exit();
}

// =============================================
// 3. FETCH DATA FOR EDIT & LIST
// =============================================
$edit_mode = false;
$edit_data = null;

// เช็คว่ากำลังแก้ไขหรือไม่
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $res_edit = $conn->query("SELECT * FROM marketing_platforms WHERE id = $edit_id");
    if ($res_edit->num_rows > 0) {
        $edit_mode = true;
        $edit_data = $res_edit->fetch_assoc();
    }
}

// ดึงรายการทั้งหมด
$platforms = $conn->query("SELECT * FROM marketing_platforms ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'Logowab.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการร้านค้า</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- Theme & Layout --- */
        :root {
            --primary: #6366f1; --primary-light: #eef2ff; --primary-hover: #4f46e5;
            --bg-body: #f8fafc; --bg-card: #ffffff; --bg-input: #f9fafb;
            --text-main: #1e293b; --text-muted: #64748b; --border: #e2e8f0;
            --radius: 16px;
        }
        [data-theme="dark"] {
            --primary: #818cf8; --bg-body: #0f172a; --bg-card: #1e293b; --bg-input: #334155;
            --text-main: #f8fafc; --text-muted: #cbd5e1; --border: #334155;
        }
        body { font-family: 'Prompt', sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; transition: 0.3s; }
        .main-container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        @media (min-width: 992px) { .main-container { margin-left: 120px; } }

        /* Header & Card */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .btn-back { text-decoration: none; color: var(--text-muted); border: 1px solid var(--border); padding: 8px 15px; border-radius: 10px; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); background: var(--bg-card); }
        .card { background: var(--bg-card); border-radius: var(--radius); padding: 30px; border: 1px solid var(--border); margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: relative; }
        
        /* Edit Mode Indicator */
        .edit-badge { 
            position: absolute; top: 20px; right: 20px; background: #fffbeb; color: #b45309; 
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid #fcd34d;
        }

        /* Form Elements */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-input); color: var(--text-main); font-family: 'Prompt'; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: var(--primary); }

        /* ✨ Image Upload Area (แก้ไขแล้ว) */
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 20px;
            /* text-align: center; เอาออก และใช้ Flexbox แทน */
            cursor: pointer;
            transition: 0.2s;
            background: var(--bg-input);
            position: relative;
            
            /* เพิ่ม Flexbox เพื่อจัดให้อยู่กึ่งกลาง */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            box-sizing: border-box;
        }
        .upload-area:hover { border-color: var(--primary); background: var(--primary-light); }
        .upload-icon { font-size: 40px; color: var(--text-muted); margin-bottom: 10px; }
        .upload-text { color: var(--text-muted); font-size: 14px; }
        #fileInput { display: none; }
        
        /* Image Preview */
        #previewContainer { margin-top: 15px; display: flex; justify-content: center; gap: 15px; align-items: flex-end; }
        .img-box { text-align: center; }
        .img-box img { width: 100px; height: 100px; border-radius: 12px; border: 1px solid var(--border); padding: 2px; background: var(--bg-card); object-fit: contain; }
        .img-caption { font-size: 12px; color: var(--text-muted); margin-top: 5px; }

        .btn-submit { background: var(--primary); color: white; border: none; padding: 12px; border-radius: 10px; width: 100%; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-cancel { background: transparent; color: var(--text-muted); border: 1px solid var(--border); padding: 12px; border-radius: 10px; width: 100%; font-weight: 600; cursor: pointer; margin-top: 10px; display: block; text-align: center; text-decoration: none; transition: 0.2s; }
        .btn-cancel:hover { background: var(--bg-input); color: var(--text-main); }

        /* List Items */
        .list-item { background: var(--bg-card); border: 1px solid var(--border); padding: 15px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; transition:0.2s; }
        .list-item:hover { border-color: var(--primary); transform: translateX(5px); }
        .item-info { display: flex; align-items: center; gap: 15px; }
        .platform-logo { width: 50px; height: 50px; border-radius: 10px; border: 1px solid var(--border); padding: 2px; background: var(--bg-card); object-fit: contain; }
        .platform-placeholder { width: 50px; height: 50px; border-radius: 10px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .item-name { font-weight: 600; font-size: 16px; }
        
        .action-group { display: flex; gap: 5px; }
        .btn-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; text-decoration:none; border: 1px solid transparent; font-size: 14px; }
        .btn-edit { background: #fffbeb; color: #d97706; border-color: #fcd34d; }
        .btn-edit:hover { background: #d97706; color: white; }
        .btn-del { background: #fef2f2; color: #ef4444; border-color: #fecaca; }
        .btn-del:hover { background: #ef4444; color: white; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        
        <div class="page-header">
            <div>
                <h2 style="margin:0; color:var(--primary);"><i class="fas fa-store-alt"></i> จัดการร้านค้า</h2>
                <p style="margin:5px 0 0; color:var(--text-muted);">เพิ่ม/แก้ไข ชื่อร้านและโลโก้</p>
            </div>
            <a href="Online_Marketing_Report.php" class="btn-back"><i class="fas fa-arrow-left"></i> กลับหน้าหลัก</a>
        </div>

        <div class="card">
            <?php if($edit_mode): ?>
                <div class="edit-badge"><i class="fas fa-pen"></i> กำลังแก้ไข</div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                
                <?php if($edit_mode): ?>
                    <input type="hidden" name="platform_id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">ชื่อร้านค้า / แพลตฟอร์ม</label>
                    <input type="text" name="platform_name" class="form-control" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['platform_name']) : ''; ?>" 
                           placeholder="เช่น Shopee, Lazada..." required autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label">โลโก้ร้านค้า</label>
                    
                    <label for="fileInput" class="upload-area">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="upload-text">คลิกเพื่อ<?php echo $edit_mode ? 'เปลี่ยน' : 'เลือก'; ?>รูปภาพ (JPG, PNG)</div>
                        <input type="file" name="platform_image" id="fileInput" accept="image/png, image/jpeg, image/gif, image/webp">
                    </label>
                    
                    <div id="previewContainer" style="<?php echo ($edit_mode && !empty($edit_data['platform_image'])) ? '' : 'display:none;'; ?>">
                        
                        <?php if($edit_mode && !empty($edit_data['platform_image'])): ?>
                        <div class="img-box" id="currentImgBox">
                            <img src="<?php echo $upload_dir . $edit_data['platform_image']; ?>" alt="Current">
                            <div class="img-caption">รูปปัจจุบัน</div>
                        </div>
                        <i class="fas fa-arrow-right" id="arrowIcon" style="display:none; color:var(--text-muted); padding-bottom:30px;"></i>
                        <?php endif; ?>

                        <div class="img-box" id="newImgBox" style="display:none;">
                            <img id="previewImage" src="">
                            <div class="img-caption">รูปใหม่</div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="<?php echo $edit_mode ? 'update_platform' : 'add_platform'; ?>" class="btn-submit">
                    <i class="fas <?php echo $edit_mode ? 'fa-save' : 'fa-plus-circle'; ?>"></i> 
                    <?php echo $edit_mode ? 'บันทึกการแก้ไข' : 'บันทึกข้อมูล'; ?>
                </button>

                <?php if($edit_mode): ?>
                    <a href="manage_platforms.php" class="btn-cancel">ยกเลิก</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="margin-bottom: 15px; font-weight: 600; color: var(--text-muted);">รายการที่มีอยู่</div>
        
        <div class="platform-list">
            <?php if($platforms->num_rows > 0): while($row = $platforms->fetch_assoc()): 
                $img_path = $upload_dir . $row['platform_image'];
                $has_image = !empty($row['platform_image']) && file_exists($img_path);
            ?>
            <div class="list-item">
                <div class="item-info">
                    <?php if($has_image): ?>
                        <img src="<?php echo $img_path; ?>" alt="logo" class="platform-logo">
                    <?php else: ?>
                        <div class="platform-placeholder"><i class="fas fa-store"></i></div>
                    <?php endif; ?>
                    
                    <span class="item-name"><?php echo htmlspecialchars($row['platform_name']); ?></span>
                </div>
                
                <div class="action-group">
                    <a href="?edit=<?php echo $row['id']; ?>" class="btn-icon btn-edit" title="แก้ไข">
                        <i class="fas fa-pen"></i>
                    </a>
                    <a href="?del=<?php echo $row['id']; ?>" class="btn-icon btn-del" onclick="return confirm('ต้องการลบและไฟล์รูปภาพ ใช่หรือไม่?');" title="ลบ">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div style="text-align:center; padding:40px; color:var(--text-muted); background:var(--bg-card); border-radius:12px; border:1px dashed var(--border);">
                    <i class="far fa-image" style="font-size:40px; opacity:0.3; margin-bottom:10px;"></i><br>
                    ยังไม่มีข้อมูลร้านค้า
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('previewImage');
        const newImgBox = document.getElementById('newImgBox');
        const arrowIcon = document.getElementById('arrowIcon');

        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'flex';
                    newImgBox.style.display = 'block';
                    if(arrowIcon) arrowIcon.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                // ถ้ากดเลือกแล้ว Cancel ให้เคลียร์ค่า
                if(!document.getElementById('currentImgBox')) {
                    previewContainer.style.display = 'none';
                }
                newImgBox.style.display = 'none';
                if(arrowIcon) arrowIcon.style.display = 'none';
            }
        });
    </script>
</body>
</html>