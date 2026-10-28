-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 28, 2026 at 10:53 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mvc_admission`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year_name` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year_name`, `is_active`, `description`, `created_at`) VALUES
(1, '2570', 1, 'ปีการศึกษา 2570 (รอบรับสมัครปัจจุบัน)', '2026-10-27 15:03:33'),
(2, '2569', 0, 'ปีการศึกษา 2569 (ข้อมูลย้อนหลัง)', '2026-10-27 15:03:33'),
(3, '2571', 0, 'ปีการศึกษา 2571 (เตรียมการรับสมัครล่วงหน้า)', '2026-10-27 15:03:33');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','officer') DEFAULT 'officer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `email`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$mPZnOrsTIFZzUqOOzUNxiOS63Sui9INFqAuC/fawdFdq2kOZyo9ca', 'งานทะเบียน', 'test@test.com', 'superadmin', '2026-10-27 12:45:46'),
(3, 'tamayari', '$2y$10$42DRxt2zNanJ3415AtR70eF2UWhkHrPU9DucJ0iX3MrkrNRzODtfK', 'นายเมธา  สวาทพงษ์', 'maytasw@mvc.ac.th', 'superadmin', '2026-10-27 14:30:38'),
(4, 'thitisak', '$2y$10$uJP4LgaX2kCpL8Tqyp8pU.j0AaS39fJ6VFQ8soBgumXJRZ26Fln8K', 'Admin Thitisak', 'dev.thitisak@mvc.ac.th', 'superadmin', '2026-10-28 09:08:22');

-- --------------------------------------------------------

--
-- Table structure for table `admission_rounds`
--

CREATE TABLE `admission_rounds` (
  `id` int(11) NOT NULL,
  `round_code` varchar(50) NOT NULL,
  `round_name` varchar(255) NOT NULL,
  `round_type` enum('quota','normal') NOT NULL DEFAULT 'quota',
  `academic_year` varchar(10) NOT NULL DEFAULT '2570',
  `education_levels` varchar(255) NOT NULL DEFAULT 'ปวช., ปวส., ปริญญาตรี',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `announcement_date` date DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `quota_seats` int(11) NOT NULL DEFAULT 0,
  `min_gpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `status` enum('open','closed','upcoming') NOT NULL DEFAULT 'open',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admission_rounds`
--

INSERT INTO `admission_rounds` (`id`, `round_code`, `round_name`, `round_type`, `academic_year`, `education_levels`, `start_date`, `end_date`, `announcement_date`, `registration_date`, `quota_seats`, `min_gpa`, `status`, `is_active`, `description`, `created_at`, `updated_at`) VALUES
(1, 'QUOTA-2570', 'รอบโควตากรณีพิเศษและโควตาสถานศึกษาเดิม', 'quota', '2570', 'ปวช., ปวส., ปริญญาตรี', '2026-10-01', '2026-12-31', '2027-01-10', '2027-01-20', 250, 2.00, 'open', 1, 'สำหรับนักเรียน นักศึกษาโควตากรณีพิเศษ โควตาเรียนดี กิจกรรมเด่น และโควตาสถานศึกษาเดิม (GPA 2.00 ขึ้นไป)', '2026-10-28 03:36:17', '2026-10-28 03:36:17'),
(2, 'NORMAL-2570', 'รอบรับสมัครทั่วไป (รอบปกติ)', 'normal', '2570', 'ปวช., ปวส., ปริญญาตรี', '2027-01-05', '2027-02-28', '2027-03-05', '2027-03-15', 500, 0.00, 'open', 1, 'เปิดรับสมัครนักเรียน นักศึกษาใหม่ทั่วไป วุฒิ ม.3, ปวช., ม.6 หรือเทียบเท่า เข้าศึกษาต่อระดับ ปวช., ปวส. และ ป.ตรี', '2026-10-28 03:36:17', '2026-10-28 03:36:17');

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(11) NOT NULL,
  `citizen_id` varchar(13) NOT NULL COMMENT 'เลขบัตรประจำตัวประชาชน 13 หลัก',
  `application_no` varchar(20) NOT NULL COMMENT 'รหัสใบสมัคร เช่น MVC2570-0001',
  `round_id` int(11) DEFAULT 1 COMMENT 'รหัสรอบการรับสมัคร (admission_rounds.id)',
  `admission_round` varchar(150) DEFAULT 'รอบโควตากรณีพิเศษและโควตาสถานศึกษาเดิม',
  `quota_type` varchar(100) DEFAULT NULL COMMENT 'ประเภทโควตา/รอบย่อย',
  `academic_year` varchar(10) DEFAULT '2570',
  `education_level` enum('ปวช.','ปวส.','ปริญญาตรี') NOT NULL,
  `program_id` int(11) NOT NULL,
  `pvs_type` varchar(100) DEFAULT NULL COMMENT 'ประเภท ปวส. (ปกติ / ทวิภาคี / ม.6 / ออนไลน์)',
  `prefix` varchar(20) NOT NULL COMMENT 'คำนำหน้า เช่น นาย, นางสาว',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('male','female','other') DEFAULT 'other',
  `birthdate` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'ไทย',
  `religion` varchar(50) DEFAULT 'พุทธ',
  `addr_no` varchar(50) DEFAULT NULL,
  `addr_moo` varchar(20) DEFAULT NULL,
  `addr_soi` varchar(100) DEFAULT NULL,
  `addr_road` varchar(100) DEFAULT NULL,
  `addr_subdistrict` varchar(100) DEFAULT NULL,
  `addr_district` varchar(100) DEFAULT NULL,
  `addr_province` varchar(100) DEFAULT NULL,
  `addr_zipcode` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `parent_name` varchar(200) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `father_prefix` varchar(30) DEFAULT NULL,
  `father_firstname` varchar(100) DEFAULT NULL,
  `father_lastname` varchar(100) DEFAULT NULL,
  `father_occupation` varchar(100) DEFAULT NULL,
  `father_phone` varchar(20) DEFAULT NULL,
  `mother_prefix` varchar(30) DEFAULT NULL,
  `mother_firstname` varchar(100) DEFAULT NULL,
  `mother_lastname` varchar(100) DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `mother_phone` varchar(20) DEFAULT NULL,
  `prev_level` varchar(50) DEFAULT NULL COMMENT 'วุฒิการศึกษาเดิม (ม.3, ม.6, กศน., ปวช.3, ปวส.2)',
  `previous_school` varchar(200) DEFAULT NULL COMMENT 'สถานศึกษาเดิม',
  `prev_subdistrict` varchar(100) DEFAULT NULL,
  `prev_district` varchar(100) DEFAULT NULL,
  `prev_province` varchar(100) DEFAULT NULL,
  `prev_zipcode` varchar(10) DEFAULT NULL,
  `prev_phone` varchar(20) DEFAULT NULL,
  `prev_major` varchar(150) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL COMMENT 'เกรดเฉลี่ยสะสม',
  `special_ability` text DEFAULT NULL,
  `file_photo` varchar(255) DEFAULT NULL,
  `file_transcript` varchar(255) DEFAULT NULL,
  `file_idcard` varchar(255) DEFAULT NULL,
  `pdpa_consent` tinyint(1) DEFAULT 1,
  `status` enum('pending','approved','rejected','document_incomplete','paid') DEFAULT 'pending' COMMENT 'สถานะการสมัคร',
  `status_note` text DEFAULT NULL COMMENT 'หมายเหตุจากงานทะเบียน',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`id`, `citizen_id`, `application_no`, `round_id`, `admission_round`, `quota_type`, `academic_year`, `education_level`, `program_id`, `pvs_type`, `prefix`, `first_name`, `last_name`, `gender`, `birthdate`, `age`, `nationality`, `religion`, `addr_no`, `addr_moo`, `addr_soi`, `addr_road`, `addr_subdistrict`, `addr_district`, `addr_province`, `addr_zipcode`, `address`, `phone`, `email`, `password`, `parent_name`, `parent_phone`, `father_prefix`, `father_firstname`, `father_lastname`, `father_occupation`, `father_phone`, `mother_prefix`, `mother_firstname`, `mother_lastname`, `mother_occupation`, `mother_phone`, `prev_level`, `previous_school`, `prev_subdistrict`, `prev_district`, `prev_province`, `prev_zipcode`, `prev_phone`, `prev_major`, `gpa`, `special_ability`, `file_photo`, `file_transcript`, `file_idcard`, `pdpa_consent`, `status`, `status_note`, `created_at`, `updated_at`) VALUES
(1, '3440100359627', 'MVC2570-0001', 1, 'รอบโควตากรณีพิเศษและโควตาสถานศึกษาเดิม', 'รอบโควต้าในเขตพื้นที่', '2570', 'ปวช.', 60, '', 'นาย', 'เมธา', 'สวาทพงษ์', 'male', '1979-08-05', 47, 'ไทย', 'พุทธ', '96', '-', '-', 'ศรีสวัสดิ์ดำเนิน', 'ตลาด', 'เมืองมหาสารคาม', 'มหาสารคาม', '44000', 'บ้านเลขที่ 96 ถนนศรีสวัสดิ์ดำเนิน ต.ตลาด อ.เมืองมหาสารคาม จ.มหาสารคาม 44000', '0874264659', 'maytasw@mvc.ac.th', '$2y$10$mPZnOrsTIFZzUqOOzUNxiOS63Sui9INFqAuC/fawdFdq2kOZyo9ca', 'นายบิดา สวาทพงษ์', '0812345678', 'นาย', 'บิดา', 'สวาทพงษ์', 'ข้าราชการ', '0812345678', 'นาง', 'มารดา', 'สวาทพงษ์', 'ค้าขาย', '0898765432', 'ม.3', 'โรงเรียนผดุงนารี', 'ตลาด', 'เมืองมหาสารคาม', 'มหาสารคาม', '44000', '043711234', 'วิทย์-คณิต', 3.75, 'คอมพิวเตอร์และเทคโนโลยีสารสนเทศ', 'photo_3440100359627_1773732826.png', 'trans_3440100359627_1773732826.pdf', 'idcard_3440100359627_1773732826.pdf', 1, 'approved', 'ตรวจสอบเอกสารครบถ้วน ผ่านการคัดเลือก', '2026-10-27 07:33:46', '2026-10-28 08:00:00'),
(6, '1449900801401', 'MVC2570-0006', 1, 'รอบโควตากรณีพิเศษและโควตาสถานศึกษาเดิม', 'รอบรับสมัครทั่วไป (รอบปกติ)', '2570', 'ปวส.', 69, 'ระดับ ปวส. จบ ม.6 ระบบปกติ', 'นาย', 'ฐิติศักดิ์', 'ภูสีริด', 'male', '2005-10-07', 20, 'ไทย', 'พุทธ', '56', '23', '-', '', 'แกดำ', 'อำเภอแกดำ', 'มหาสารคาม', '44190', 'บ้านเลขที่ 56 หมู่ 23 ซอย - ต.แกดำ อ.อำเภอแกดำ จ.มหาสารคาม 44190', '0650574758', NULL, '$2y$10$OT7TjQ1CwGyUthpL0/Hveu4YRrizAHnPPu8qwTOsxAEJkLhr47vuK', 'นาย- -', '-', 'นาย', '-', '-', '-', '-', 'นาง', '-', '-', '-', '-', 'กศน.', 'test01', 'สนามชัย', 'อำเภอบางไทร', 'พระนครศรีอยุธยา', '13190', '0253652', '', 4.00, 'เก่ง', 'photo_1449900801401_1793177044_565.png', 'trans_1449900801401_1793177044_365.pdf', 'idcard_1449900801401_1793177044_840.pdf', 1, 'approved', '', '2026-10-28 08:44:04', '2026-10-28 09:12:30');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_documents`
--

CREATE TABLE `applicant_documents` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `doc_type` enum('citizen_card','house_reg','transcript','photo','other') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicant_documents`
--

INSERT INTO `applicant_documents` (`id`, `applicant_id`, `doc_type`, `file_name`, `file_path`, `verified`, `uploaded_at`) VALUES
(1, 1, 'photo', 'photo_3440100359627_1773732826.png', 'uploads/photo_3440100359627_1773732826.png', 1, '2026-10-27 07:33:46'),
(2, 1, 'transcript', 'trans_3440100359627_1773732826.pdf', 'uploads/trans_3440100359627_1773732826.pdf', 1, '2026-10-27 07:33:46'),
(3, 1, 'citizen_card', 'idcard_3440100359627_1773732826.pdf', 'uploads/idcard_3440100359627_1773732826.pdf', 1, '2026-10-27 07:33:46'),
(4, 6, 'photo', 'photo_1449900801401_1793177044_565.png', 'uploads/photo_1449900801401_1793177044_565.png', 0, '2026-10-28 08:44:04'),
(5, 6, 'transcript', 'trans_1449900801401_1793177044_365.pdf', 'uploads/trans_1449900801401_1793177044_365.pdf', 0, '2026-10-28 08:44:04'),
(6, 6, 'citizen_card', 'idcard_1449900801401_1793177044_840.pdf', 'uploads/idcard_1449900801401_1793177044_840.pdf', 0, '2026-10-28 08:44:04');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `dept_code` varchar(20) NOT NULL,
  `dept_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `academic_year` varchar(10) NOT NULL DEFAULT '2570',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `dept_code`, `dept_name`, `description`, `academic_year`, `created_at`) VALUES
(20, 'DBT', 'ภาควิชาเทคโนโลยีธุรกิจดิจิทัล', 'มุ่งเน้นการพัฒนาทักษะดิจิทัล นวัตกรรมเทคโนโลยี การประยุกต์ธุรกิจดิจิทัล และเทคโนโลยีสารสนเทศสมัยใหม่', '2570', '2026-10-27 15:35:48'),
(21, 'ACC', 'ภาควิชาการบัญชี', 'มุ่งเน้นมาตรฐานการบัญชี การบัญชีภาษีอากร การใช้โปรแกรมคอมพิวเตอร์ในงานบัญชี และการตรวจสอบบัญชี', '2570', '2026-10-27 15:35:48'),
(22, 'MKT', 'ภาควิชาการตลาด', 'มุ่งเน้นการวางแผนกลยุทธ์การตลาด การตลาดดิจิทัล การสร้างแบรนด์ และการค้าออนไลน์', '2570', '2026-10-27 15:35:48'),
(23, 'OFC', 'ภาควิชาการจัดการสำนักงาน', 'มุ่งเน้นงานสารบรรณอิเล็กทรอนิกส์ การจัดการสำนักงานดิจิทัล งานเลขานุการ และการประสานงานองค์กร', '2570', '2026-10-27 15:35:48'),
(24, 'RET', 'ภาควิชาธุรกิจค้าปลีก', 'มุ่งเน้นการจัดการธุรกิจค้าปลีกสมัยใหม่ ระบบบริหารร้านสะดวกซื้อ การส่งเสริมการขาย และผู้ประกอบการรุ่นใหม่', '2570', '2026-10-27 15:35:48'),
(25, 'CG', 'ภาควิชาคอมพิวเตอร์กราฟิก', 'มุ่งเน้นการออกแบบกราฟิก 2D/3D แอนิเมชัน การตัดต่อวิดีโอ สื่อดิจิทัล และวิชวลเอฟเฟกต์', '2570', '2026-10-27 15:35:48'),
(26, 'DES', 'ภาควิชาการออกแบบ', 'มุ่งเน้นการออกแบบผลิตภัณฑ์ การออกแบบตกแต่งภายใน นิเทศศิลป์ และการเขียนแบบสถาปัตย์', '2570', '2026-10-27 15:35:48'),
(27, 'ART', 'ภาควิชาวิจิตรศิลป์', 'มุ่งเน้นจิตรกรรมไทย-สากล ประติมากรรม ศิลปะภาพพิมพ์ จิตรกรรมสีน้ำ และงานแกะสลักศิลปกรรม', '2570', '2026-10-27 15:35:48'),
(28, 'PHT', 'ภาควิชาการถ่ายภาพและมัลติมีเดีย', 'มุ่งเน้นการถ่ายภาพในสตูดิโอ การผลิตสื่อวิดีโอ การจัดระบบไลฟ์สด ครีเอทีฟคอนเทนต์ และมัลติมีเดียโปรดักชัน', '2570', '2026-10-27 15:35:48'),
(29, 'FND', 'ภาควิชาอาหารและโภชนาการ', 'มุ่งเน้นอาหารไทย อาหารนานาชาติ การแต่งหน้าเค้กและเบเกอรี่ ธุรกิจจัดเลี้ยง และโภชนาการเพื่อสุขภาพ', '2570', '2026-10-27 15:35:48'),
(30, 'LOG', 'ภาควิชาการจัดการโลจิสติกส์และซัพพลายเชน', 'มุ่งเน้นการจัดการคลังสินค้า ระบบการขนส่ง การจัดซื้อ-จัดหา และการบริหารจัดการห่วงโซ่อุปทาน', '2570', '2026-10-27 15:35:48'),
(31, 'HTL', 'ภาควิชาการโรงแรมและบริการ', 'มุ่งเน้นการบริการอาหารและเครื่องดื่ม บาร์เทนเดอร์ ศิลปะการชงกาแฟ งานส่วนหน้า และการจัดการโรงแรมมาตรฐาน', '2570', '2026-10-27 15:35:48'),
(32, 'TRV', 'ภาควิชาการท่องเที่ยว', 'มุ่งเน้นวิชาชีพมัคคุเทศก์ ภาษาอังกฤษเพื่อการท่องเที่ยว ธุรกิจนำเที่ยว และการจัดการการท่องเที่ยวสากล', '2570', '2026-10-27 15:35:48'),
(33, 'FSH', 'ภาควิชาแฟชั่นและสิ่งทอ', 'มุ่งเน้นการออกแบบและตัดเย็บเสื้อผ้า แฟชั่นดีไซเนอร์ ช่างแพทเทิร์น สไตลิสต์ และการจัดแสดงแฟชั่น', '2570', '2026-10-27 15:35:48'),
(34, 'HEC', 'ภาควิชาคหกรรมศาสตร์', 'มุ่งเน้นศิลปะการจัดดอกไม้ งานใบตองเพื่อธุรกิจ ศิลปะประดิษฐ์ งานแกะสลัก และการจัดตกแต่งสถานที่ครบวงจร', '2570', '2026-10-27 15:35:48'),
(35, 'ELD', 'ภาควิชาการดูแลผู้สูงอายุ', 'มุ่งเน้นการบริบาลและฟื้นฟูสุขภาพผู้สูงอายุ ภาษาและวัฒนธรรมญี่ปุ่นเพื่อการทำงาน โอกาสฝึกงานและทำงาน ณ ประเทศญี่ปุ่น', '2570', '2026-10-27 15:35:48');

-- --------------------------------------------------------

--
-- Table structure for table `homepage_settings`
--

CREATE TABLE `homepage_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` mediumtext DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `homepage_settings`
--

INSERT INTO `homepage_settings` (`setting_key`, `setting_value`, `setting_group`, `updated_at`) VALUES
('card1_btn_text', 'เริ่มกรอกใบสมัคร', 'services', '2026-10-28 03:26:38'),
('card1_desc', 'กรอกข้อมูลประวัติส่วนตัว เลือกแผนกวิชาที่ต้องการศึกษาต่อ และอัปโหลดรูปถ่าย', 'services', '2026-10-28 03:26:38'),
('card1_featured', '1', 'services', '2026-10-28 03:26:38'),
('card1_icon', 'bi-pencil-square', 'services', '2026-10-28 03:26:38'),
('card1_image', '', 'services', '2026-10-28 03:26:38'),
('card1_link', 'register.php', 'services', '2026-10-28 03:26:38'),
('card1_title', 'สมัครเรียนออนไลน์', 'services', '2026-10-28 03:26:38'),
('card2_btn_text', 'ดูประกาศและคุณสมบัติ', 'services', '2026-10-28 03:26:38'),
('card2_desc', 'ตรวจสอบรอบการรับสมัคร คุณสมบัติเฉพาะสาขา เกณฑ์คะแนน และจำนวนรับแต่ละแผนก', 'services', '2026-10-28 03:26:38'),
('card2_featured', '0', 'services', '2026-10-28 03:26:38'),
('card2_icon', 'bi-megaphone-fill', 'services', '2026-10-28 03:26:38'),
('card2_image', '', 'services', '2026-10-28 03:26:38'),
('card2_link', '#announcements', 'services', '2026-10-28 03:26:38'),
('card2_title', 'ประกาศรับสมัคร', 'services', '2026-10-28 03:26:38'),
('card3_btn_text', 'พิมพ์ใบสมัคร / แนบเอกสาร', 'services', '2026-10-28 03:26:38'),
('card3_desc', 'ค้นหาข้อมูลใบสมัคร แนบเอกสารหลักฐานเพิ่มเติม และสั่งพิมพ์ใบสมัครฉบับจริง', 'services', '2026-10-28 03:26:38'),
('card3_featured', '0', 'services', '2026-10-28 03:26:38'),
('card3_icon', 'bi-printer-fill', 'services', '2026-10-28 03:26:38'),
('card3_image', '', 'services', '2026-10-28 03:26:38'),
('card3_link', 'print_application.php', 'services', '2026-10-28 03:26:38'),
('card3_title', 'พิมพ์ใบสมัคร / เอกสาร', 'services', '2026-10-28 03:26:38'),
('card4_btn_text', 'ค้นหาด้วยเลขบัตรประชาชน', 'services', '2026-10-28 03:26:38'),
('card4_desc', 'ติดตามความคืบหน้าการตรวจสอบเอกสาร ผลการคัดเลือก และสถานะการรับสมัคร', 'services', '2026-10-28 03:26:38'),
('card4_featured', '0', 'services', '2026-10-28 03:26:38'),
('card4_icon', 'bi-search', 'services', '2026-10-28 03:26:38'),
('card4_image', '', 'services', '2026-10-28 03:26:38'),
('card4_link', 'status.php', 'services', '2026-10-28 03:26:38'),
('card4_title', 'ตรวจสอบสถานะ', 'services', '2026-10-28 03:26:38'),
('footer_address', 'ถนนนครสวรรค์ ตำบลตลาด อำเภอเมือง จังหวัดมหาสารคาม 44000', 'footer', '2026-10-28 03:26:38'),
('footer_copyright', '© 2570 วิทยาลัยอาชีวศึกษามหาสารคาม. สงวนลิขสิทธิ์ทุกประการ.', 'footer', '2026-10-28 03:26:38'),
('footer_developer', 'งานศูนย์ดิจิทัลวิทยาลัยอาชีวศึกษามหาสารคาม', 'footer', '2026-10-28 03:26:38'),
('footer_email', 'admission@mvc.ac.th', 'footer', '2026-10-28 03:26:38'),
('footer_hours', 'วันจันทร์ - ศุกร์: 08:30 - 16:30 น. (เว้นวันหยุดราชการ)', 'footer', '2026-10-28 03:26:38'),
('footer_org_desc', 'ระบบบริการรับสมัครนักเรียน นักศึกษาใหม่ออนไลน์ ประจำปีการศึกษา 2570', 'footer', '2026-10-28 03:26:38'),
('footer_org_en', 'Mahasarakham Vocational College', 'footer', '2026-10-28 03:26:38'),
('footer_org_name', 'วิทยาลัยอาชีวศึกษามหาสารคาม', 'footer', '2026-10-28 03:26:38'),
('footer_phone', 'งานทะเบียน: 043-711-371', 'footer', '2026-10-28 03:26:38'),
('hero_badge', 'เปิดรับสมัครรอบโควตาและรอบทั่วไป ประจำปีการศึกษา 2570', 'hero', '2026-10-28 03:26:38'),
('hero_badges_text', 'ระดับ ปวช., ระดับ ปวส., ระดับ ปริญญาตรี', 'hero', '2026-10-28 03:26:38'),
('hero_btn1_link', 'register.php', 'hero', '2026-10-28 03:26:38'),
('hero_btn1_text', 'สมัครเรียนออนไลน์ทันที', 'hero', '2026-10-28 03:26:38'),
('hero_btn2_link', '#steps', 'hero', '2026-10-28 03:26:38'),
('hero_btn2_text', 'ขั้นตอนการสมัคร', 'hero', '2026-10-28 03:26:38'),
('hero_card_desc', 'มุ่งเน้นทักษะจริงสู่ตลาดแรงงานสากล', 'hero', '2026-10-28 03:26:38'),
('hero_card_icon', 'bi-award-fill', 'hero', '2026-10-28 03:26:38'),
('hero_card_title', 'มาตรฐานอาชีวศึกษาชั้นนำ', 'hero', '2026-10-28 03:26:38'),
('hero_description', 'สมัครเรียนออนไลน์ง่าย สะดวก รวดเร็ว เลือกระดับการศึกษา ปวช., ปวส. หรือ ปริญญาตรี พร้อมส่งเอกสารและติดตามผลการพิจารณาได้ครบจบในระบบเดียว', 'hero', '2026-10-28 03:26:38'),
('hero_highlight', 'รับสมัครนักเรียน นักศึกษาใหม่', 'hero', '2026-10-28 03:26:38'),
('hero_image', 'img/hero_20261028_065259_a6a57dcf.png', 'hero', '2026-10-28 05:52:59'),
('hero_subtitle', 'วิทยาลัยอาชีวศึกษามหาสารคาม (Mahasarakham Vocational College)', 'hero', '2026-10-28 03:26:38'),
('hero_title', 'สร้างอนาคตวิชาชีพสู่มืออาชีพ', 'hero', '2026-10-28 03:26:38'),
('navbar_subtitle', 'วิทยาลัยอาชีวศึกษามหาสารคาม', 'general', '2026-10-28 03:26:38'),
('navbar_title', 'ระบบรับสมัครนักเรียน นักศึกษา', 'general', '2026-10-28 03:39:26'),
('notice_badge', 'สำคัญ', 'notice', '2026-10-28 03:26:38'),
('notice_btn_link', '#contact', 'notice', '2026-10-28 03:26:38'),
('notice_btn_text', 'ศูนย์ช่วยเหลือผู้สมัคร', 'notice', '2026-10-28 03:26:38'),
('notice_desc', 'เปิดรับสมัครตั้งแต่วันนี้ ถึง 28 กุมภาพันธ์ 2570 กรุณาตรวจสอบความถูกต้องของข้อมูลก่อนยืนยันการสมัคร', 'notice', '2026-10-28 03:26:38'),
('notice_title', 'กำหนดการยื่นใบสมัครรอบแรก', 'notice', '2026-10-28 03:26:38'),
('services_desc', 'เข้าถึงทุกข้อมูลที่จำเป็นสำหรับการสมัครเรียนได้อย่างรวดเร็ว ถูกต้องตามระเบียบการ', 'services', '2026-10-28 03:26:38'),
('services_eyebrow', 'Online Admission Services', 'services', '2026-10-28 03:26:38'),
('services_title', 'บริการและขั้นตอนสำหรับผู้สมัคร', 'services', '2026-10-28 03:26:38'),
('site_logo', 'img/Logo-W.png', 'general', '2026-10-28 03:26:38'),
('topbar_address', 'วิทยาลัยอาชีวศึกษามหาสารคาม สถาบันการอาชีวศึกษาภาคตะวันออกเฉียงเหนือ 3', 'topbar', '2026-10-28 03:26:38'),
('topbar_badge', 'ปีการศึกษา 2570', 'topbar', '2026-10-28 03:26:38'),
('topbar_phone', '043-711-371', 'topbar', '2026-10-28 03:26:38');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `program_name` varchar(150) NOT NULL,
  `education_level` enum('ปวช.','ปวส.','ปริญญาตรี') NOT NULL,
  `academic_year` varchar(10) NOT NULL DEFAULT '2570',
  `seats_available` int(11) DEFAULT 40,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `dept_id`, `program_code`, `program_name`, `education_level`, `academic_year`, `seats_available`, `status`, `created_at`) VALUES
(60, 20, 'DBT-101', 'สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล', 'ปวช.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(61, 20, 'DBT-201', 'สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล', 'ปวส.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(62, 20, 'DBT-301', 'สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล (ทล.บ.)', 'ปริญญาตรี', '2570', 30, 'open', '2026-10-27 15:35:48'),
(63, 21, 'ACC-101', 'สาขาวิชาการบัญชี', 'ปวช.', '2570', 50, 'open', '2026-10-27 15:35:48'),
(64, 21, 'ACC-201', 'สาขาวิชาการบัญชี', 'ปวส.', '2570', 45, 'open', '2026-10-27 15:35:48'),
(65, 22, 'MKT-101', 'สาขาวิชาการตลาด', 'ปวช.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(66, 22, 'MKT-201', 'สาขาวิชาการตลาด', 'ปวส.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(67, 22, 'MKT-301', 'สาขาวิชาการตลาด (ทล.บ.)', 'ปริญญาตรี', '2570', 30, 'open', '2026-10-27 15:35:48'),
(68, 23, 'OFC-101', 'สาขาวิชาการจัดการสำนักงาน', 'ปวช.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(69, 23, 'OFC-201', 'สาขาวิชาการจัดการสำนักงานดิจิทัล', 'ปวส.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(70, 23, 'OFC-301', 'สาขาวิชาการจัดการสำนักงานดิจิทัล (ทล.บ.)', 'ปริญญาตรี', '2570', 30, 'open', '2026-10-27 15:35:48'),
(71, 24, 'RET-101', 'สาขาวิชาธุรกิจค้าปลีก', 'ปวช.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(72, 24, 'RET-201', 'สาขาวิชาการจัดการธุรกิจค้าปลีก', 'ปวส.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(73, 25, 'CG-101', 'สาขาวิชาคอมพิวเตอร์กราฟิก', 'ปวช.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(74, 25, 'CG-201', 'สาขาวิชาคอมพิวเตอร์กราฟิก', 'ปวส.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(75, 25, 'CG-301', 'สาขาวิชาคอมพิวเตอร์กราฟิก (ทล.บ.)', 'ปริญญาตรี', '2570', 30, 'open', '2026-10-27 15:35:48'),
(76, 26, 'DES-101', 'สาขาวิชาการออกแบบ', 'ปวช.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(77, 26, 'DES-201', 'สาขาวิชาการออกแบบ', 'ปวส.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(78, 27, 'ART-101', 'สาขาวิชาวิจิตรศิลป์', 'ปวช.', '2570', 30, 'open', '2026-10-27 15:35:48'),
(79, 27, 'ART-201', 'สาขาวิชาวิจิตรศิลป์', 'ปวส.', '2570', 30, 'open', '2026-10-27 15:35:48'),
(80, 28, 'PHT-101', 'สาขาวิชาการถ่ายภาพและมัลติมีเดีย', 'ปวช.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(81, 28, 'PHT-201', 'สาขาวิชาการถ่ายภาพและมัลติมีเดีย', 'ปวส.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(82, 29, 'FND-101', 'สาขาวิชาอาหารและโภชนาการ', 'ปวช.', '2570', 45, 'open', '2026-10-27 15:35:48'),
(83, 29, 'FND-201', 'สาขาวิชาอาหารและโภชนาการ', 'ปวส.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(84, 30, 'LOG-101', 'สาขาวิชาการจัดการโลจิสติกส์และซัพพลายเชน', 'ปวช.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(85, 30, 'LOG-201', 'สาขาวิชาการจัดการโลจิสติกส์และซัพพลายเชน', 'ปวส.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(86, 31, 'HTL-101', 'สาขาวิชาการโรงแรม', 'ปวช.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(87, 31, 'HTL-201', 'สาขาวิชาการโรงแรมและบริการ', 'ปวส.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(88, 32, 'TRV-101', 'สาขาวิชาการท่องเที่ยว', 'ปวช.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(89, 32, 'TRV-201', 'สาขาวิชาการท่องเที่ยว', 'ปวส.', '2570', 40, 'open', '2026-10-27 15:35:48'),
(90, 33, 'FSH-101', 'สาขาวิชาแฟชั่นและสิ่งทอ', 'ปวช.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(91, 33, 'FSH-201', 'สาขาวิชาแฟชั่นและสิ่งทอ', 'ปวส.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(92, 34, 'HEC-101', 'สาขาวิชาคหกรรมศาสตร์', 'ปวช.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(93, 34, 'HEC-201', 'สาขาวิชาคหกรรมศาสตร์', 'ปวส.', '2570', 35, 'open', '2026-10-27 15:35:48'),
(94, 35, 'ELD-201', 'สาขาวิชาการดูแลผู้สูงอายุ', 'ปวส.', '2570', 35, 'open', '2026-10-27 15:35:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year_name` (`year_name`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admission_rounds`
--
ALTER TABLE `admission_rounds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `round_code` (`round_code`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `citizen_id` (`citizen_id`),
  ADD UNIQUE KEY `application_no` (`application_no`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dept_year` (`dept_code`,`academic_year`);

--
-- Indexes for table `homepage_settings`
--
ALTER TABLE `homepage_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `idx_prog_year` (`academic_year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admission_rounds`
--
ALTER TABLE `admission_rounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `applicants_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`);

--
-- Constraints for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD CONSTRAINT `applicant_documents_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
