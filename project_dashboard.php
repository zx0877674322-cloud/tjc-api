<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';
date_default_timezone_set(timezoneId: 'Asia/Bangkok');

?>

<!DOCTYPE html>
<html lang='th'>

<head>
    <?php include 'Logowab.php';
    ?>
    <title>Dashboard Project Management System</title>
    <link href='https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <link rel='stylesheet' href='style.css'>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class='main-content'>
            <div class="row">
                <div class="col-3">
                    <div class="card">
                        <div class="row">
                            <div class="col-4">
                                <div class="box-img-wrapper">
                                    <img src="uploads/logos/logo_1766472339_156.png" class="box-img">
                                </div>
                            </div>
                            <div class="col-6 text-center">
                                <span style="font-weight: 600;">TJC&nbsp;CORPORATION</span>
                            </div>
                        </div>
                        <hr>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-wait">
                                    <i class="fas fa-clock sb-icon-bg"></i>
                                    <div class="sb-label">รอข้อมูล</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-contract">
                                    <i class="fas fa-envelope-open-text sb-icon-bg"></i>
                                    <div class="sb-label">เริ่มสัญญา</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-end-contract">
                                    <i class="fas fa-weight sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้ครบกำหนดสัญญา</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-guarantee-Cash">
                                    <i class="fas fa-broadcast-tower sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้สิ้นสุดหนังสือเงินค้ำ/เงินสด</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-wait-send">
                                    <i class="fas fa-car-crash sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้วันส่งมอบงาน</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-send">
                                    <i class="fas fa-caravan sb-icon-bg"></i>
                                    <div class="sb-label">ส่งมอบงานแล้ว</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <di class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-cash">
                                    <i class="fas fa-money-check-alt sb-icon-bg"></i>
                                    <div class="sb-label">เงินเข้าแล้ว</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn sb-approved">
                                    <i class="fas fa-window-close sb-icon-bg"></i>
                                    <div class="sb-label">ปิดโครงการ</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                    </div>
                </div>

                <div class="col-3">
                    <div class="card">
                        <div class="row">
                            <div class="col-4">
                                <div class="box-img-wrapper">
                                    <img src="uploads/logos/logo_1766472914_167.png" class="box-img">
                                </div>
                            </div>
                            <div class="col-6 text-center">
                                <span style="font-weight: 600;">TANGJAI&nbsp;CORPORATION</span>
                            </div>
                        </div>
                        <hr>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-wait">
                                    <i class="fas fa-clock sb-icon-bg"></i>
                                    <div class="sb-label">รอข้อมูล</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-contract">
                                    <i class="fas fa-envelope-open-text sb-icon-bg"></i>
                                    <div class="sb-label">เริ่มสัญญา</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-end-contract">
                                    <i class="fas fa-weight sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้ครบกำหนดสัญญา</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-guarantee-Cash">
                                    <i class="fas fa-broadcast-tower sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้สิ้นสุดหนังสือเงินค้ำ/เงินสด</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-wait-send">
                                    <i class="fas fa-car-crash sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้วันส่งมอบงาน</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-send">
                                    <i class="fas fa-caravan sb-icon-bg"></i>
                                    <div class="sb-label">ส่งมอบงานแล้ว</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-cash">
                                    <i class="fas fa-money-check-alt sb-icon-bg"></i>
                                    <div class="sb-label">เงินเข้าแล้ว</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn sb-approved">
                                    <i class="fas fa-window-close sb-icon-bg"></i>
                                    <div class="sb-label">ปิดโครงการ</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-3">
                    <div class="card">
                        <div class="row">
                            <div class="col-4">
                                <div class="box-img-wrapper">
                                    <img src="uploads/logos/logo_1766473814_390.png" class="box-img">
                                </div>
                            </div>
                            <div class="col-6 text-center">
                                <span style="font-weight: 600;">ASCENT&nbsp;CORPORATION</span>
                            </div>
                        </div>
                        <hr>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-wait">
                                    <i class="fas fa-clock sb-icon-bg"></i>
                                    <div class="sb-label">รอข้อมูล</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-contract">
                                    <i class="fas fa-envelope-open-text sb-icon-bg"></i>
                                    <div class="sb-label">เริ่มสัญญา</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-end-contract">
                                    <i class="fas fa-weight sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้ครบกำหนดสัญญา</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-guarantee-Cash">
                                    <i class="fas fa-broadcast-tower sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้สิ้นสุดหนังสือเงินค้ำ/เงินสด</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-wait-send">
                                    <i class="fas fa-car-crash sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้วันส่งมอบงาน</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-send">
                                    <i class="fas fa-caravan sb-icon-bg"></i>
                                    <div class="sb-label">ส่งมอบงานแล้ว</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-cash">
                                    <i class="fas fa-money-check-alt sb-icon-bg"></i>
                                    <div class="sb-label">เงินเข้าแล้ว</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn sb-approved">
                                    <i class="fas fa-window-close sb-icon-bg"></i>
                                    <div class="sb-label">ปิดโครงการ</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-3">
                    <div class="card">
                        <div class="row">
                            <div class="col-4">
                                <div class="box-img-wrapper">
                                    <img src="uploads/logos/logo_1766473828_319.png" class="box-img">
                                </div>
                            </div>
                            <div class="col-6 text-center">
                                <span style="font-weight: 600;">A.R.T.&nbsp;EXPONENTIAL</span>
                            </div>
                        </div>
                        <hr>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-wait">
                                    <i class="fas fa-clock sb-icon-bg"></i>
                                    <div class="sb-label">รอข้อมูล</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-contract">
                                    <i class="fas fa-envelope-open-text sb-icon-bg"></i>
                                    <div class="sb-label">เริ่มสัญญา</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-end-contract">
                                    <i class="fas fa-weight sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้ครบกำหนดสัญญา</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-guarantee-Cash">
                                    <i class="fas fa-broadcast-tower sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้สิ้นสุดหนังสือเงินค้ำ/เงินสด</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-wait-send">
                                    <i class="fas fa-car-crash sb-icon-bg"></i>
                                    <div class="sb-label">ใกล้วันส่งมอบงาน</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-send">
                                    <i class="fas fa-caravan sb-icon-bg"></i>
                                    <div class="sb-label">ส่งมอบงานแล้ว</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="#" class="status-box-btn s-cash">
                                    <i class="fas fa-money-check-alt sb-icon-bg"></i>
                                    <div class="sb-label">เงินเข้าแล้ว</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="status-box-btn sb-approved">
                                    <i class="fas fa-window-close sb-icon-bg"></i>
                                    <div class="sb-label">ปิดโครงการ</div>
                                    <div class="sb-count">
                                        <span>10</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
</body>

</html>