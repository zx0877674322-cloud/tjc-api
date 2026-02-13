<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';
require_once 'CarManager.php';

// Check Admin
if ($_SESSION['role'] != 'admin') { header("Location: index.php"); exit(); }

$carMgr = new CarManager($conn);

// 1. Handle Add Car (เพิ่มรถ)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_car'])) {
    $name = $_POST['name'];
    $plate = $_POST['plate'];
    $type = $_POST['type'];
    
    $image = null;
    if(isset($_FILES['car_image']) && $_FILES['car_image']['error'] == 0){
        $ext = pathinfo($_FILES['car_image']['name'], PATHINFO_EXTENSION);
        $image = "car_" . time() . "." . $ext;
        move_uploaded_file($_FILES['car_image']['tmp_name'], "uploads/cars/" . $image);
    }

    $carMgr->addCar($name, $plate, $type, $image);
    header("Location: AdminCarManage.php");
    exit();
}

// 2. Handle Edit Car (แก้ไขรถ)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_car'])) {
    $id = $_POST['car_id'];
    $name = $_POST['name'];
    $plate = $_POST['plate'];
    $type = $_POST['type'];
    
    $image = null;
    if(isset($_FILES['car_image']) && $_FILES['car_image']['error'] == 0){
        $ext = pathinfo($_FILES['car_image']['name'], PATHINFO_EXTENSION);
        $image = "car_" . time() . "." . $ext;
        move_uploaded_file($_FILES['car_image']['tmp_name'], "uploads/cars/" . $image);
    }

    $carMgr->updateCar($id, $name, $plate, $type, $image);
    header("Location: AdminCarManage.php");
    exit();
}

// 3. Handle Delete (ลบรถ)
if (isset($_GET['delete_id'])) {
    $carMgr->deleteCar($_GET['delete_id']);
    header("Location: AdminCarManage.php");
    exit();
}

$cars = $carMgr->getAllCars();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข้อมูลรถ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ================= VARIABLES ================= */
        :root {
            /* Light Mode Defaults */
            --bg-body: #f0f2f5;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-sub: #64748b;
            --border-color: #e2e8f0;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            
            /* Page Specific (Light) */
            --img-wrapper-bg: #f8fafc;
            --preview-box-bg: #f8fafc;
            --preview-box-border: #cbd5e1;
            --modal-bg: #ffffff;
            --modal-footer-bg: #f8f9fa;
            --add-btn-bg: #2563eb;
            --add-btn-hover: #1d4ed8;
        }

        body.dark-mode {
            /* Dark Mode Overrides */
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-sub: #cbd5e1;
            --border-color: #334155;
            --input-bg: #334155;
            --input-border: #475569;

            /* Page Specific (Dark) */
            --img-wrapper-bg: #334155;
            --preview-box-bg: #1e293b;
            --preview-box-border: #475569;
            --modal-bg: #1e293b;
            --modal-footer-bg: #0f172a;
            --add-btn-bg: #3b82f6;
            --add-btn-hover: #2563eb;
        }

        /* ================= GLOBAL STYLES ================= */
        body { 
            font-family: 'Prompt', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-main);
            transition: background-color 0.3s, color 0.3s; 
        }
        
        .car-manage-card { 
            transition: 0.2s; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            border-radius: 12px; overflow: hidden; 
            background: var(--bg-card); 
            color: var(--text-main);
        }
        .car-manage-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        
        /* --- Car Image Wrapper --- */
        .car-img-wrapper {
            width: 140px;          
            height: 90px;          
            background: var(--img-wrapper-bg);   
            border-radius: 8px;
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;        
            border: 1px solid var(--border-color);
        }
        .car-img-wrapper img { width: 100%; height: 100%; object-fit: contain; }

        /* --- Add Button --- */
        .add-btn { 
            position: fixed; bottom: 30px; right: 30px; 
            width: 60px; height: 60px; border-radius: 50%; 
            font-size: 24px; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4); 
            z-index: 100; 
            background: var(--add-btn-bg); 
            color: white; border: none;
            transition: 0.3s;
        }
        .add-btn:hover { background: var(--add-btn-hover); transform: scale(1.1); }

        /* --- Preview Box (Modal) --- */
        .preview-box {
            width: 100%; height: 200px; 
            border: 2px dashed var(--preview-box-border);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            background: var(--preview-box-bg); 
            overflow: hidden; position: relative; cursor: pointer;
        }
        .preview-box:hover { border-color: #2563eb; background: rgba(37, 99, 235, 0.05); }
        .preview-box img { max-width: 100%; max-height: 100%; object-fit: contain; display: none; }
        .preview-text { color: var(--text-sub); font-size: 0.9rem; pointer-events: none; }

        /* --- Modal & Form --- */
        .modal-content { background-color: var(--modal-bg); color: var(--text-main); border: 1px solid var(--border-color); }
        .modal-header { border-bottom-color: var(--border-color); }
        .modal-footer { background-color: var(--modal-footer-bg); border-top-color: var(--border-color); }
        
        .form-control, .form-select {
            background-color: var(--input-bg);
            color: var(--text-main);
            border-color: var(--input-border);
        }
        .form-control:focus, .form-select:focus {
            background-color: var(--input-bg);
            color: var(--text-main);
            border-color: #2563eb;
        }

        /* --- Text Utilities Override --- */
        body.dark-mode .text-dark { color: var(--text-main) !important; }
        body.dark-mode .text-secondary { color: var(--text-sub) !important; }
        body.dark-mode .text-muted { color: var(--text-sub) !important; }
        
        /* Action Buttons inside Card */
        .btn-light {
            background-color: var(--img-wrapper-bg);
            border-color: var(--border-color);
            color: var(--text-main);
        }
        body.dark-mode .btn-light:hover {
            background-color: #475569;
            color: white;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        
        <div class="container-fluid p-4">
            <h3 class="fw-bold mb-4 text-dark"><i class="fas fa-cogs me-2 text-primary"></i>จัดการข้อมูลรถ</h3>

            <div class="row g-4">
                <?php foreach ($cars as $car): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card car-manage-card h-100">
                        <div class="card-body d-flex align-items-center gap-3 p-3">
                            <div class="car-img-wrapper">
                                <?php if($car['car_image']): ?>
                                    <img src="uploads/cars/<?php echo $car['car_image']; ?>" alt="Car Image">
                                <?php else: ?>
                                    <div class="text-muted"><i class="fas fa-car fa-2x"></i></div>
                                <?php endif; ?>
                            </div>

                            <div class="flex-grow-1 overflow-hidden">
                                <h5 class="fw-bold mb-1 text-truncate"><?php echo $car['name']; ?></h5>
                                <div class="text-secondary small mb-2"><i class="fas fa-closed-captioning me-1"></i><?php echo $car['plate']; ?></div>
                                <?php if($car['type'] == 'EV'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">EV</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Fuel</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button onclick='openEditModal(<?php echo json_encode($car); ?>)' 
                                        class="btn btn-light text-warning btn-sm rounded-circle shadow-sm"
                                        style="width:40px; height:40px; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete_id=<?php echo $car['id']; ?>" onclick="return confirm('ยืนยันลบรถคันนี้?')" 
                                   class="btn btn-light text-danger btn-sm rounded-circle shadow-sm" 
                                   style="width:40px; height:40px; display:flex; align-items:center; justify-content:center;">
                                   <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button class="add-btn d-flex align-items-center justify-content-center" data-bs-toggle="modal" data-bs-target="#addCarModal">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>

    <div class="modal fade" id="addCarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>เพิ่มรถใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="add_car" value="1">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">รูปภาพรถ</label>
                            <div class="preview-box" onclick="document.getElementById('add_image_input').click()">
                                <span class="preview-text"><i class="fas fa-cloud-upload-alt me-1"></i> แตะเพื่อเลือกรูปภาพ</span>
                                <img id="add_preview" src="#">
                            </div>
                            <input type="file" name="car_image" id="add_image_input" class="d-none" accept="image/*" onchange="previewImage(this, 'add_preview')">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ชื่อรถ / รุ่น</label>
                            <input type="text" name="name" class="form-control" placeholder="เช่น Toyota Fortuner" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ทะเบียนรถ</label>
                            <input type="text" name="plate" class="form-control" placeholder="เช่น 1กข-1234 กทม" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ประเภทเชื้อเพลิง</label>
                            <select name="type" class="form-select">
                                <option value="OIL">รถน้ำมัน (OIL)</option>
                                <option value="EV">รถไฟฟ้า (EV)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลรถ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="edit_car" value="1">
                        <input type="hidden" name="car_id" id="edit_car_id">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">รูปภาพรถ (เปลี่ยนใหม่ได้)</label>
                            <div class="preview-box" onclick="document.getElementById('edit_image_input').click()">
                                <span class="preview-text" id="edit_preview_text"><i class="fas fa-cloud-upload-alt me-1"></i> แตะเพื่อเปลี่ยนรูป</span>
                                <img id="edit_preview" src="#" style="display:none;">
                            </div>
                            <input type="file" name="car_image" id="edit_image_input" class="d-none" accept="image/*" onchange="previewImage(this, 'edit_preview')">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ชื่อรถ / รุ่น</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ทะเบียนรถ</label>
                            <input type="text" name="plate" id="edit_plate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ประเภทเชื้อเพลิง</label>
                            <select name="type" id="edit_type" class="form-select">
                                <option value="OIL">รถน้ำมัน (OIL)</option>
                                <option value="EV">รถไฟฟ้า (EV)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-warning px-4 fw-bold">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check Theme on Load
        document.addEventListener('DOMContentLoaded', () => {
            const body = document.body;
            const savedTheme = localStorage.getItem('tjc_theme') || 'light';
            if (savedTheme === 'dark') {
                body.classList.add('dark-mode');
            }
        });

        // ฟังก์ชันเปิด Modal แก้ไขและเติมข้อมูล
        function openEditModal(car) {
            document.getElementById('edit_car_id').value = car.id;
            document.getElementById('edit_name').value = car.name;
            document.getElementById('edit_plate').value = car.plate;
            document.getElementById('edit_type').value = car.type;

            const previewImg = document.getElementById('edit_preview');
            const previewText = document.getElementById('edit_preview_text');
            
            if (car.car_image) {
                previewImg.src = 'uploads/cars/' + car.car_image;
                previewImg.style.display = 'block';
                previewText.style.display = 'none';
            } else {
                previewImg.style.display = 'none';
                previewText.style.display = 'block';
            }

            var myModal = new bootstrap.Modal(document.getElementById('editCarModal'));
            myModal.show();
        }

        // ฟังก์ชันแสดงตัวอย่างรูปภาพ (ใช้ร่วมกันทั้ง Add และ Edit)
        function previewImage(input, imgId) {
            const img = document.getElementById(imgId);
            const box = img.parentElement;
            const text = box.querySelector('.preview-text');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    if(text) text.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>