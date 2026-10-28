<?php
session_start();
require_once __DIR__ . '/config/database.php';

// ตั้งค่าโฟลเดอร์อัปโหลด
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ฟังก์ชันสำหรับจัดการอัปโหลดไฟล์พร้อมเปลี่ยนชื่อไฟล์เพื่อป้องกันการซ้ำซ้อน
function uploadFile($file_input, $upload_dir, $prefix_name) {
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == 0) {
        $file_info = pathinfo($_FILES[$file_input]['name']);
        $ext = strtolower($file_info['extension'] ?? '');
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        // เช็คขนาดไฟล์ไม่เกิน 5MB
        if ($_FILES[$file_input]['size'] > 5 * 1024 * 1024) {
            return false;
        }

        if (in_array($ext, $allowed)) {
            $new_name = $prefix_name . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $upload_dir . $new_name)) {
                return $new_name;
            }
        }
    }
    return null;
}

$status = 'error';
$message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
$application_no = '';
$program_display = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // 1. รับค่ารอบรับสมัครและปีการศึกษา
        $round_id = !empty($_POST['round_id']) ? intval($_POST['round_id']) : 1;
        $academic_year = !empty($_POST['academic_year']) ? trim($_POST['academic_year']) : '2570';
        $quota_type = trim($_POST['quota_type'] ?? 'โควตาทั่วไป (เกรดเฉลี่ยสะสม)');
        $program_id = !empty($_POST['major_id']) ? intval($_POST['major_id']) : 60;
        $level_id = !empty($_POST['level_id']) ? intval($_POST['level_id']) : 1;
        $pvs_type = trim($_POST['pvs_type'] ?? '');

        // แปลง level_id เป็นค่า enum ของตาราง applicants
        $edu_map = [
            1 => 'ปวช.',
            2 => 'ปวส.',
            3 => 'ปริญญาตรี'
        ];
        $education_level = $edu_map[$level_id] ?? 'ปวช.';

        // ดึงชื่อรอบรับสมัครและข้อมูลสาขาวิชาจากฐานข้อมูล
        $admission_round_name = $quota_type;
        $stmt_r = $pdo->prepare("SELECT round_name, academic_year FROM admission_rounds WHERE id = :id LIMIT 1");
        $stmt_r->execute([':id' => $round_id]);
        if ($r_row = $stmt_r->fetch(PDO::FETCH_ASSOC)) {
            $admission_round_name = $r_row['round_name'];
            if (!empty($r_row['academic_year'])) {
                $academic_year = $r_row['academic_year'];
            }
        }

        $stmt_p = $pdo->prepare("SELECT p.program_name, p.education_level, d.dept_name FROM programs p LEFT JOIN departments d ON p.dept_id = d.id WHERE p.id = :pid LIMIT 1");
        $stmt_p->execute([':pid' => $program_id]);
        if ($p_row = $stmt_p->fetch(PDO::FETCH_ASSOC)) {
            $education_level = $p_row['education_level'];
            $program_display = $p_row['program_name'] . (!empty($p_row['dept_name']) ? ' (' . $p_row['dept_name'] . ')' : '');
        }

        // 2. เลขบัตรประจำตัวประชาชน (รองรับทั้งแบบกล่องเดียว หรือ 5 กล่อง)
        $id_card = trim($_POST['id_card'] ?? '');
        if (empty($id_card)) {
            $id_card = trim(($_POST['id_card_1'] ?? '') . ($_POST['id_card_2'] ?? '') . ($_POST['id_card_3'] ?? '') . ($_POST['id_card_4'] ?? '') . ($_POST['id_card_5'] ?? ''));
        }
        $id_card = preg_replace('/[^0-9]/', '', $id_card);

        // 3. ข้อมูลส่วนตัว
        $prefix = trim($_POST['prefix'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');

        $gender = 'other';
        if (in_array($prefix, ['นาย', 'เด็กชาย'], true)) {
            $gender = 'male';
        } elseif (in_array($prefix, ['นางสาว', 'นาง', 'เด็กหญิง'], true)) {
            $gender = 'female';
        }

        // วันเดือนปีเกิด
        $birth_date = null;
        if (!empty($_POST['birth_date'])) {
            $birth_date = $_POST['birth_date'];
        } elseif (!empty($_POST['birth_year']) && !empty($_POST['birth_month']) && !empty($_POST['birth_day'])) {
            $b_year = intval($_POST['birth_year']);
            if ($b_year > 2400) {
                $b_year -= 543; // แปลง พ.ศ. เป็น ค.ศ.
            }
            $b_month = str_pad(intval($_POST['birth_month']), 2, '0', STR_PAD_LEFT);
            $b_day = str_pad(intval($_POST['birth_day']), 2, '0', STR_PAD_LEFT);
            $birth_date = "{$b_year}-{$b_month}-{$b_day}";
        }

        $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
        $nationality = trim($_POST['nationality'] ?? 'ไทย');
        $religion = trim($_POST['religion'] ?? 'พุทธ');

        // 4. ที่อยู่
        $addr_no = trim($_POST['addr_no'] ?? '');
        $addr_moo = trim($_POST['addr_moo'] ?? '');
        $addr_soi = trim($_POST['addr_soi'] ?? '');
        $addr_road = trim($_POST['addr_road'] ?? '');
        $addr_province = trim($_POST['addr_province'] ?? '');
        $addr_district = trim($_POST['addr_district'] ?? '');
        $addr_subdistrict = trim($_POST['addr_subdistrict'] ?? '');
        $addr_zipcode = trim($_POST['addr_zipcode'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // สร้างข้อความ address แบบครบถ้วน
        $address_parts = [];
        if ($addr_no) $address_parts[] = "บ้านเลขที่ {$addr_no}";
        if ($addr_moo) $address_parts[] = "หมู่ {$addr_moo}";
        if ($addr_soi) $address_parts[] = "ซอย {$addr_soi}";
        if ($addr_road) $address_parts[] = "ถนน {$addr_road}";
        if ($addr_subdistrict) $address_parts[] = "ต.{$addr_subdistrict}";
        if ($addr_district) $address_parts[] = "อ.{$addr_district}";
        if ($addr_province) $address_parts[] = "จ.{$addr_province}";
        if ($addr_zipcode) $address_parts[] = $addr_zipcode;
        $address_info = implode(' ', $address_parts);
        if (empty($address_info) && !empty($_POST['address_info'])) {
            $address_info = trim($_POST['address_info']);
        }

        // 5. ข้อมูลบิดามารดา
        $father_prefix = trim($_POST['father_prefix'] ?? '');
        $father_firstname = trim($_POST['father_firstname'] ?? '');
        $father_lastname = trim($_POST['father_lastname'] ?? '');
        $father_occupation = trim($_POST['father_occupation'] ?? '');
        $father_phone = trim($_POST['father_phone'] ?? '');

        $mother_prefix = trim($_POST['mother_prefix'] ?? '');
        $mother_firstname = trim($_POST['mother_firstname'] ?? '');
        $mother_lastname = trim($_POST['mother_lastname'] ?? '');
        $mother_occupation = trim($_POST['mother_occupation'] ?? '');
        $mother_phone = trim($_POST['mother_phone'] ?? '');

        $parent_name = trim($_POST['parent_name'] ?? '');
        if (empty($parent_name)) {
            if ($father_firstname) {
                $parent_name = ($father_prefix ? $father_prefix : '') . $father_firstname . ' ' . $father_lastname;
            } elseif ($mother_firstname) {
                $parent_name = ($mother_prefix ? $mother_prefix : '') . $mother_firstname . ' ' . $mother_lastname;
            }
        }
        $parent_phone = trim($_POST['parent_phone'] ?? '');
        if (empty($parent_phone)) {
            $parent_phone = $father_phone ?: $mother_phone;
        }

        // 6. ข้อมูลการศึกษาเดิม (ข้อ 4)
        $prev_level = trim($_POST['prev_level'] ?? '');
        $prev_school = trim($_POST['prev_school'] ?? '');
        $prev_district = trim($_POST['prev_district'] ?? '');
        $prev_subdistrict = trim($_POST['prev_subdistrict'] ?? '');
        $prev_province = trim($_POST['prev_province'] ?? '');
        $prev_zipcode = trim($_POST['prev_zipcode'] ?? '');
        $prev_phone = trim($_POST['prev_phone'] ?? '');
        $prev_major = trim($_POST['prev_major'] ?? '');
        $gpax = !empty($_POST['gpax']) ? floatval($_POST['gpax']) : 0.00;
        $special_ability = trim($_POST['special_ability'] ?? '');

        // ยินยอม PDPA
        $pdpa_consent = isset($_POST['pdpa_consent']) ? 1 : 1;

        // 7. สร้างเลขที่ใบสมัครอัตโนมัติ (เช่น MVC2570-0002)
        $stmt_max = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM applicants");
        $next_id = (int)$stmt_max->fetchColumn();
        $application_no = sprintf("MVC%s-%04d", $academic_year, $next_id);

        // รหัสผ่านเริ่มต้นสำหรับการเข้าสู่ระบบของผู้สมัคร (ใช้เบอร์โทรศัพท์ หรือ 123456)
        $default_password_hash = password_hash($phone ?: '123456', PASSWORD_DEFAULT);

        // 8. อัปโหลดไฟล์หลักฐาน
        $file_photo = uploadFile('file_photo', $upload_dir, 'photo_' . $id_card);
        $file_transcript = uploadFile('file_transcript', $upload_dir, 'trans_' . $id_card);
        $file_idcard = uploadFile('file_idcard', $upload_dir, 'idcard_' . $id_card);

        // 9. SQL บันทึกข้อมูลลงตาราง applicants (Unified)
        $sql = "INSERT INTO applicants 
                (citizen_id, application_no, round_id, admission_round, quota_type, academic_year, education_level, program_id, pvs_type,
                 prefix, first_name, last_name, gender, birthdate, age, nationality, religion,
                 addr_no, addr_moo, addr_soi, addr_road, addr_subdistrict, addr_district, addr_province, addr_zipcode, address,
                 phone, email, password,
                 parent_name, parent_phone,
                 father_prefix, father_firstname, father_lastname, father_occupation, father_phone,
                 mother_prefix, mother_firstname, mother_lastname, mother_occupation, mother_phone,
                 prev_level, previous_school, prev_subdistrict, prev_district, prev_province, prev_zipcode, prev_phone, prev_major, gpa, special_ability,
                 file_photo, file_transcript, file_idcard, pdpa_consent, status) 
                VALUES 
                (:citizen_id, :application_no, :round_id, :admission_round, :quota_type, :academic_year, :education_level, :program_id, :pvs_type,
                 :prefix, :first_name, :last_name, :gender, :birthdate, :age, :nationality, :religion,
                 :addr_no, :addr_moo, :addr_soi, :addr_road, :addr_subdistrict, :addr_district, :addr_province, :addr_zipcode, :address,
                 :phone, :email, :password,
                 :parent_name, :parent_phone,
                 :father_prefix, :father_firstname, :father_lastname, :father_occupation, :father_phone,
                 :mother_prefix, :mother_firstname, :mother_lastname, :mother_occupation, :mother_phone,
                 :prev_level, :previous_school, :prev_subdistrict, :prev_district, :prev_province, :prev_zipcode, :prev_phone, :prev_major, :gpa, :special_ability,
                 :file_photo, :file_transcript, :file_idcard, :pdpa_consent, 'pending')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':citizen_id'       => $id_card,
            ':application_no'   => $application_no,
            ':round_id'         => $round_id,
            ':admission_round'  => $admission_round_name,
            ':quota_type'       => $quota_type,
            ':academic_year'    => $academic_year,
            ':education_level'  => $education_level,
            ':program_id'       => $program_id,
            ':pvs_type'         => $pvs_type,
            ':prefix'           => $prefix,
            ':first_name'       => $firstname,
            ':last_name'        => $lastname,
            ':gender'           => $gender,
            ':birthdate'        => $birth_date,
            ':age'              => $age,
            ':nationality'      => $nationality,
            ':religion'         => $religion,
            ':addr_no'          => $addr_no,
            ':addr_moo'         => $addr_moo,
            ':addr_soi'         => $addr_soi,
            ':addr_road'        => $addr_road,
            ':addr_subdistrict' => $addr_subdistrict,
            ':addr_district'    => $addr_district,
            ':addr_province'    => $addr_province,
            ':addr_zipcode'     => $addr_zipcode,
            ':address'          => $address_info,
            ':phone'            => $phone,
            ':email'            => $email ?: null,
            ':password'         => $default_password_hash,
            ':parent_name'      => $parent_name,
            ':parent_phone'     => $parent_phone,
            ':father_prefix'    => $father_prefix,
            ':father_firstname' => $father_firstname,
            ':father_lastname'  => $father_lastname,
            ':father_occupation'=> $father_occupation,
            ':father_phone'     => $father_phone,
            ':mother_prefix'    => $mother_prefix,
            ':mother_firstname' => $mother_firstname,
            ':mother_lastname'  => $mother_lastname,
            ':mother_occupation'=> $mother_occupation,
            ':mother_phone'     => $mother_phone,
            ':prev_level'       => $prev_level,
            ':previous_school'  => $prev_school,
            ':prev_subdistrict' => $prev_subdistrict,
            ':prev_district'    => $prev_district,
            ':prev_province'    => $prev_province,
            ':prev_zipcode'     => $prev_zipcode,
            ':prev_phone'       => $prev_phone,
            ':prev_major'       => $prev_major,
            ':gpa'              => $gpax,
            ':special_ability'  => $special_ability,
            ':file_photo'       => $file_photo,
            ':file_transcript'  => $file_transcript,
            ':file_idcard'      => $file_idcard,
            ':pdpa_consent'     => $pdpa_consent,
        ]);

        $app_id = (int)$pdo->lastInsertId();

        // อัปเดตเลขที่ใบสมัครให้ตรงกับ ID จริง
        $application_no = sprintf("MVC%s-%04d", $academic_year, $app_id);
        $pdo->prepare("UPDATE applicants SET application_no = :app_no WHERE id = :id")
            ->execute([':app_no' => $application_no, ':id' => $app_id]);

        // บันทึกเอกสารลงตาราง applicant_documents ด้วยเพื่อให้แสดงผลในหน้าตรวจสอบสถานะและหน้า Admin
        $doc_stmt = $pdo->prepare("INSERT INTO applicant_documents (applicant_id, doc_type, file_name, file_path, verified) VALUES (:aid, :dtype, :fname, :fpath, 0)");
        if (!empty($file_photo)) {
            $doc_stmt->execute([':aid' => $app_id, ':dtype' => 'photo', ':fname' => $file_photo, ':fpath' => 'uploads/' . $file_photo]);
        }
        if (!empty($file_transcript)) {
            $doc_stmt->execute([':aid' => $app_id, ':dtype' => 'transcript', ':fname' => $file_transcript, ':fpath' => 'uploads/' . $file_transcript]);
        }
        if (!empty($file_idcard)) {
            $doc_stmt->execute([':aid' => $app_id, ':dtype' => 'citizen_card', ':fname' => $file_idcard, ':fpath' => 'uploads/' . $file_idcard]);
        }

        $status = 'success';
        $message = 'ระบบได้รับข้อมูลการสมัครเรียนของท่านเรียบร้อยแล้ว!';

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = 'หมายเลขบัตรประชาชนนี้ ได้ทำการสมัครในระบบไปแล้ว สามารถตรวจสอบสถานะหรือพิมพ์ใบสมัครได้ทันที';
        } else {
            $message = 'ข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการสมัครเรียน - วิทยาลัยอาชีวศึกษามหาสารคาม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6fa; }
        .result-box { 
            margin-top: 6vh; 
            margin-bottom: 6vh;
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 10px 35px rgba(0,0,0,0.08); 
            background: white; 
            text-align: center; 
            border-top: 6px solid #8B1E2B;
        }
        .icon-large { font-size: 4.5rem; margin-bottom: 18px; }
        .text-success-custom { color: #198754; }
        .text-danger-custom { color: #8B1E2B; }
        .btn-custom { background-color: #8B1E2B; color: white; border: none; font-weight: 600; }
        .btn-custom:hover { background-color: #6E1420; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="result-box">
                    <img src="assets/images/mvc_round_seal.png?v=<?= time() ?>" alt="ตราสัญลักษณ์ วิทยาลัยอาชีวศึกษามหาสารคาม" style="width: 85px; height: 85px; border-radius: 50%; object-fit: contain; box-shadow: 0 4px 12px rgba(128, 0, 0, 0.12); padding: 3px; background: white; border: 2px solid #eef2f6;" class="mb-3">
                    <h5 class="fw-bold mb-4" style="color: #8B1E2B;">วิทยาลัยอาชีวศึกษามหาสารคาม</h5>
                    
                    <?php if($status == 'success'): ?>
                        <div class="icon-large text-success-custom">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <h3 class="mb-3 fw-bold text-success">สมัครเรียนสำเร็จ!</h3>
                        <div class="alert alert-success text-start py-3 px-4 mb-4">
                            <p class="mb-1"><strong>เลขที่ใบสมัคร:</strong> <span class="badge bg-danger fs-6"><?= htmlspecialchars($application_no) ?></span></p>
                            <p class="mb-1"><strong>ผู้สมัคร:</strong> <?= htmlspecialchars($prefix . $firstname . ' ' . $lastname) ?></p>
                            <p class="mb-1"><strong>เลขประจำตัวประชาชน:</strong> <?= htmlspecialchars($id_card) ?></p>
                            <p class="mb-1"><strong>ระดับชั้น / สาขาวิชา:</strong> <?= htmlspecialchars(($education_level ?? '') . ' - ' . $program_display) ?></p>
                            <p class="mb-0"><strong>เบอร์โทรศัพท์:</strong> <?= htmlspecialchars($phone) ?></p>
                        </div>
                        <p class="text-muted mb-1"><?= htmlspecialchars($message) ?></p>
                        <p class="small text-secondary">กรุณาพิมพ์ใบสมัครและตรวจสอบเอกสารประกอบการสมัครเพื่อนำมายื่นในวันรายงานตัว</p>
                    <?php else: ?>
                        <div class="icon-large text-danger-custom">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </div>
                        <h3 class="mb-3 fw-bold text-danger">ไม่สามารถทำรายการได้</h3>
                        <p class="text-danger fs-5"><?= htmlspecialchars($message) ?></p>
                    <?php endif; ?>
                    
                    <div class="mt-4 d-flex justify-content-center gap-2 flex-wrap">
                        <?php if($status == 'success' || !empty($id_card)): ?>
                            <a href="print_application.php?search=<?= urlencode($id_card) ?>" class="btn btn-custom px-4 py-2">
                                <i class="fa-solid fa-print me-1"></i> พิมพ์ใบสมัคร / แนบเอกสาร
                            </a>
                            <a href="status.php?citizen_id=<?= urlencode($id_card) ?>" class="btn btn-outline-danger px-4 py-2 fw-semibold">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> ตรวจสอบสถานะการสมัคร
                            </a>
                        <?php endif; ?>
                        <a href="register.php" class="btn btn-outline-secondary px-3 py-2">
                            <i class="fa-solid fa-pen-to-square me-1"></i> หน้ากรอกใบสมัคร
                        </a>
                        <a href="index.php" class="btn btn-outline-dark px-3 py-2">
                            <i class="fa-solid fa-house me-1"></i> หน้าแรก
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
