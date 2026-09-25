-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 25, 2026 at 10:15 AM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 7.4.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `e_eskuelog`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `activity_log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `date_log` datetime NOT NULL DEFAULT current_timestamp(),
  `action` longtext NOT NULL DEFAULT '',
  `session_id` varchar(255) NOT NULL DEFAULT '',
  `user_level` varchar(100) NOT NULL DEFAULT '0',
  `system_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL DEFAULT '',
  `department_code` varchar(100) NOT NULL DEFAULT '',
  `flag_status` int(11) NOT NULL DEFAULT 0,
  `flag_update` int(11) NOT NULL DEFAULT 0 COMMENT '0 - not updated\r\n1 - updated',
  `date_modify` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `department_code`, `flag_status`, `flag_update`, `date_modify`) VALUES
(1, 'Department of Arts and Sciences', 'DAS', 0, 0, '2026-07-11 11:58:12'),
(2, 'Department of Business and Accounting', 'DBA', 0, 0, '2026-07-11 11:58:12'),
(3, 'Department of Computing and Informatics', 'DCI', 0, 0, '2026-07-11 11:58:12'),
(4, 'Department of Teacher Education', 'DTE', 0, 0, '2026-07-11 11:58:12'),
(5, 'Department of Lifelong and Flexible Learning', 'DLFL', 0, 0, '2026-07-11 11:58:12');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `employee_id` varchar(100) NOT NULL DEFAULT '',
  `personnel_classification` enum('TEACHING PERSONNEL','NON-TEACHING PERSONNEL','') NOT NULL DEFAULT '' COMMENT 'PERSONNEL CLASSIFICATION\r\n- TEACHING PERSONNEL\r\n- NON-TEACHING PERSONNEL',
  `employment_status` enum('PERMANENT','CONTRACT OF SERVICE',' JOB ORDER','') NOT NULL DEFAULT '' COMMENT 'EMPLOYMENT STATUS\r\n- PERMANENT\r\n- CONTRACT OF SERVICE\r\n- JOB ORDER',
  `employment_basis` enum('','PART TIME','FULL TIME') NOT NULL DEFAULT '' COMMENT 'PART TIME\r\nFULL TIME\r\n',
  `position` varchar(255) NOT NULL DEFAULT '' COMMENT 'JOB TITLE',
  `profile_pic` varchar(100) DEFAULT NULL,
  `cover_photo` varchar(100) DEFAULT NULL,
  `employment_date` longtext NOT NULL DEFAULT '[""]',
  `office_id` int(11) NOT NULL DEFAULT 0,
  `department_id` int(11) NOT NULL DEFAULT 0,
  `room_id` int(11) NOT NULL DEFAULT 0,
  `service_status` int(11) NOT NULL DEFAULT 0,
  `flag_update` int(11) NOT NULL DEFAULT 0,
  `date_modify` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`id`, `user_id`, `employee_id`, `personnel_classification`, `employment_status`, `employment_basis`, `position`, `profile_pic`, `cover_photo`, `employment_date`, `office_id`, `department_id`, `room_id`, `service_status`, `flag_update`, `date_modify`) VALUES
(1, 1, 'CGC-123456', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 0, 0, 0, 0, 0, '2026-09-25 16:14:28');

-- --------------------------------------------------------

--
-- Table structure for table `facility`
--

CREATE TABLE `facility` (
  `facility_id` int(11) NOT NULL,
  `facility_code` varchar(100) NOT NULL DEFAULT '',
  `facility_name` varchar(500) NOT NULL DEFAULT '',
  `facility_floor` longtext NOT NULL DEFAULT '{}',
  `flag_status` int(11) NOT NULL DEFAULT 0,
  `flag_update` int(11) NOT NULL DEFAULT 0 COMMENT '0 - not updated\r\n1 - updated',
  `date_modify` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `facility`
--

INSERT INTO `facility` (`facility_id`, `facility_code`, `facility_name`, `facility_floor`, `flag_status`, `flag_update`, `date_modify`) VALUES
(1, 'ADMIN BLDG', 'Admin Building', '{\"Under Ground Floor\", \"Ground Floor\", \"Second Floor\"}', 0, 0, '2026-09-09 16:49:34'),
(2, 'RIZAL BLDG', 'Rizal Building', '{\"Under Ground Floor\", \"Ground Floor\", \"Second Floor\"}', 0, 0, '2026-09-09 16:49:34'),
(3, 'JMC BLDG', 'Joaquin M. Chipeco Building', '{\"Under Ground Floor\", \"Ground Floor\", \"Second Floor\"}', 0, 0, '2026-09-09 16:49:34');

-- --------------------------------------------------------

--
-- Table structure for table `login`
--

CREATE TABLE `login` (
  `id` int(11) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `username` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `recovery_email` varchar(100) DEFAULT '',
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0-active 1-deactivate 2-delete',
  `locked` int(11) NOT NULL DEFAULT 0 COMMENT '0-unlock 1-locked',
  `flag_update` int(11) NOT NULL DEFAULT 0 COMMENT '0 - not updated\r\n1 - updated',
  `flag_validity` int(11) NOT NULL DEFAULT 0,
  `date_modify` datetime NOT NULL DEFAULT current_timestamp(),
  `date_username` date NOT NULL DEFAULT current_timestamp(),
  `date_password` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `login`
--

INSERT INTO `login` (`id`, `user_id`, `username`, `password`, `recovery_email`, `status`, `locked`, `flag_update`, `flag_validity`, `date_modify`, `date_username`, `date_password`) VALUES
(1, '1', 'mlreolo@ccc.edu.ph', '$2y$10$Z/7cFcWlArZTuUNiK4VfJu52BBwt2ezyiHzZO7AfZu1eaIWt7CFbS', '', 0, 0, 0, 0, '2026-09-25 16:14:28', '2026-09-25', '2026-09-25');

-- --------------------------------------------------------

--
-- Table structure for table `office`
--

CREATE TABLE `office` (
  `office_id` int(11) NOT NULL,
  `office_code` varchar(100) NOT NULL DEFAULT '',
  `office_name` varchar(255) NOT NULL DEFAULT '',
  `office_logo` varchar(100) DEFAULT NULL,
  `department_id` int(11) DEFAULT 0,
  `room_id` int(11) DEFAULT 0,
  `flag_status` int(11) NOT NULL DEFAULT 0,
  `flag_update` int(11) NOT NULL DEFAULT 0,
  `date_modify` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `office`
--

INSERT INTO `office` (`office_id`, `office_code`, `office_name`, `office_logo`, `department_id`, `room_id`, `flag_status`, `flag_update`, `date_modify`) VALUES
(1, 'OCP', 'Office of the College President', NULL, 0, 0, 0, 0, '2026-09-09 17:10:00'),
(2, 'OVPAA', 'Office of the Vice President for Academic Affairs', NULL, 0, 0, 0, 0, '2026-09-09 17:10:00'),
(3, 'OVPSDA', 'Office of the Vice President for Student Development and Auxiliary', NULL, 0, 0, 0, 0, '2026-09-09 17:10:00'),
(4, 'OVPAF', 'Office of the Vice President for Administration and Finance', NULL, 0, 0, 0, 0, '2026-09-09 17:10:00'),
(5, 'OVPREPQA', 'Office of the Vice President for Research, Extension, Planning, and Quality Assurance', NULL, 0, 0, 0, 0, '2026-09-09 17:10:00'),
(6, 'OVPCAR', 'Office of the Vice President for College Advancement and Relations', NULL, 0, 0, 0, 0, '2026-09-09 17:10:00'),
(7, 'DTE', 'Department of Teacher Education', NULL, 0, 0, 0, 0, '2026-09-09 17:10:00'),
(8, 'DAS', 'Department of Arts and Sciences', NULL, 1, 0, 0, 0, '2026-09-09 17:10:00'),
(9, 'DBA', 'Department of Business and Accountancy', NULL, 0, 0, 0, 0, '2026-09-09 17:10:00'),
(10, 'DCI', 'Department of Computing and Informatics', NULL, 3, 0, 0, 0, '2026-09-21 18:07:41'),
(11, 'LRIC', 'Learning and Resource Information Center', NULL, 0, 2, 0, 0, '2026-09-21 18:25:59');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `program_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL DEFAULT 0,
  `program_code` varchar(100) NOT NULL DEFAULT '',
  `program_name` varchar(255) NOT NULL DEFAULT '',
  `major` varchar(100) DEFAULT '',
  `program_major` varchar(500) DEFAULT '' COMMENT 'Program Major (UNIQUE)',
  `flag_offer` int(11) NOT NULL DEFAULT 0 COMMENT '0 - still offered\r\n1 - not offered\r\n',
  `flag_status` int(11) NOT NULL DEFAULT 0,
  `flag_update` int(11) NOT NULL DEFAULT 0 COMMENT '0 - not updated\r\n1 - updated',
  `date_modify` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `department_id`, `program_code`, `program_name`, `major`, `program_major`, `flag_offer`, `flag_status`, `flag_update`, `date_modify`) VALUES
(1, 1, 'BSPsy', 'Bachelor of Science in Psychology', '', '', 0, 0, 0, '2026-07-11 13:39:37'),
(2, 2, 'BSA', 'Bachelor of Science in Accountancy', '', '', 0, 0, 0, '2026-07-11 13:39:37'),
(3, 2, 'BSAIS', 'Bachelor of Science in Accounting Information System', '', '', 0, 0, 0, '2026-07-11 13:39:37'),
(4, 3, 'BSCS', 'Bachelor of Science in Computer Science', '', '', 0, 0, 0, '2026-07-11 13:39:37'),
(5, 3, 'BSIT', 'Bachelor of Science in Information Technology', '', '', 0, 0, 0, '2026-07-11 13:39:37'),
(6, 4, 'BEEd', 'Bachelor of Elementary Education', '', '', 0, 0, 0, '2026-07-11 13:39:37'),
(7, 4, 'BSEdM', 'Bachelor of Secondary Education', 'Mathematics', '', 0, 0, 0, '2026-07-11 13:39:37'),
(8, 4, 'BSEdE', 'Bachelor of Secondary Education', 'English', '', 0, 0, 0, '2026-07-11 13:39:37'),
(9, 4, 'BSEdS', 'Bachelor of Secondary Education', 'Science', '', 0, 0, 0, '2026-07-11 13:39:37'),
(10, 4, 'BSEdF', 'Bachelor of Secondary Education', 'Filipino', '', 0, 0, 0, '2026-07-11 13:39:37'),
(11, 4, 'BSEdSoc', 'Bachelor of Secondary Education', 'Social Studies', '', 0, 0, 0, '2026-07-11 13:39:37'),
(12, 4, 'BECED', 'Bachelor of Early Childhood Education', '', '', 0, 0, 0, '2026-07-11 13:39:37');

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `room_id` int(11) NOT NULL,
  `room_code` varchar(100) NOT NULL DEFAULT '',
  `room_name` varchar(255) NOT NULL DEFAULT '',
  `room_type` varchar(100) NOT NULL DEFAULT '' COMMENT 'ROOM\r\nOFFICE\r\nLABORATORY',
  `room_details` text DEFAULT '\'\'',
  `facility_id` int(11) NOT NULL DEFAULT 0,
  `facility_floor` varchar(100) NOT NULL DEFAULT '',
  `flag_status` int(11) NOT NULL DEFAULT 0,
  `flag_update` int(11) NOT NULL DEFAULT 0 COMMENT '0 - not updated\r\n1 - updated',
  `date_modify` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `room`
--

INSERT INTO `room` (`room_id`, `room_code`, `room_name`, `room_type`, `room_details`, `facility_id`, `facility_floor`, `flag_status`, `flag_update`, `date_modify`) VALUES
(1, 'MIS OFFICE', 'Management Information System Department', 'OFFICE', NULL, 1, 'Ground Floor', 0, 0, '2026-09-21 14:04:07'),
(2, 'R1', 'Rizal 1', 'CLASSROOM', '', 2, 'Under Ground Floor', 0, 0, '2026-09-21 15:29:25'),
(3, '1-JMC-CL1', 'JMC Computer Lab 1', 'LABORATORY', '', 3, 'Second Floor', 0, 0, '2026-09-21 15:54:38');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `student_id` varchar(100) NOT NULL DEFAULT '',
  `year_level` int(11) DEFAULT 0,
  `department_id` int(11) DEFAULT 0,
  `program_id` int(11) DEFAULT 0,
  `major` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(100) DEFAULT 'default-profile.jpg',
  `cover_photo` varchar(100) DEFAULT 'default-cover.jpg',
  `flag_status` int(11) NOT NULL DEFAULT 0,
  `flag_update` int(11) NOT NULL DEFAULT 0 COMMENT '0 - not updated\r\n1 - updated',
  `date_modify` datetime NOT NULL DEFAULT current_timestamp(),
  `graduated_data` longtext DEFAULT NULL,
  `additional_data` longtext DEFAULT NULL,
  `academic_status` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `system_access`
--

CREATE TABLE `system_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `ref_id` int(11) NOT NULL DEFAULT 0 COMMENT 'user id [Ref API]',
  `system_type` varchar(100) NOT NULL DEFAULT '',
  `system_role` int(11) NOT NULL DEFAULT 0,
  `access_tag` enum('','EMPLOYEE','STUDENT') NOT NULL DEFAULT '',
  `employee_update` int(11) NOT NULL DEFAULT 0,
  `student_update` int(11) NOT NULL DEFAULT 0,
  `flag_access` int(11) NOT NULL DEFAULT 0,
  `date_modify` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `system_access`
--

INSERT INTO `system_access` (`id`, `user_id`, `ref_id`, `system_type`, `system_role`, `access_tag`, `employee_update`, `student_update`, `flag_access`, `date_modify`) VALUES
(1, 1, 1, 'E-SKUELOG', 1, 'EMPLOYEE', 0, 0, 0, '2026-09-25 16:14:28'),
(2, 1, 1, 'E-SKUELOG', 2, 'EMPLOYEE', 0, 0, 0, '2026-09-25 16:14:47'),
(3, 1, 1, 'E-SKUELOG', 3, 'EMPLOYEE', 0, 0, 0, '2026-09-25 16:14:53'),
(4, 1, 1, 'E-SKUELOG', 4, 'EMPLOYEE', 0, 0, 0, '2026-09-25 16:15:03');

-- --------------------------------------------------------

--
-- Table structure for table `system_key`
--

CREATE TABLE `system_key` (
  `system_id` int(11) NOT NULL,
  `system_type` varchar(255) NOT NULL,
  `system_key` varchar(255) NOT NULL,
  `public_key` text NOT NULL,
  `secret_key` varchar(512) NOT NULL,
  `img` blob DEFAULT NULL,
  `flag_transfer` tinyint(1) NOT NULL DEFAULT 0,
  `flag_status` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `system_key`
--

INSERT INTO `system_key` (`system_id`, `system_type`, `system_key`, `public_key`, `secret_key`, `img`, `flag_transfer`, `flag_status`) VALUES
(1, 'E-GURO++', '239cf01a82900e2ae22aa1c2daebc778353438345acf066989dd896c9a267584', '700aa2c4072294be8b4dfd86a7efd1e88d176fc38c02c4660800174c5eae5cc2', 'K0txckNjanJhSFJWQmlrSlpwRC9nREhmMzJ3SnRMRmxvSERLSGdqQVY2c2JHS1RYZTRPZ3I3L0t5ellBUXcwUHI2bExpeUV1cHpxaTBpdDh4QkJMaytYVUxvZTZrNzkzVjlITVVVRXVORHc9Ojo_PLUS_WVNXi9pedXZrJWb507xd', NULL, 0, 0),
(2, 'E-APP', '4c564a29da1696b7c2668456d30ad442054cccf615b427ff2cc2619f14d9a832', '8d170effcea914034b2b437c30549166a6facb79fbcce9157bb3dd5d7d28cc94', 'QmxSOE16TGsySGdxRVdNOThNalMrdldsTjRLMmNXUEcxcHJFb2NSbUV6VjBJMXpORHZSOGg4UENBdWVuaFBJNGJKNzVRM01IdmZVYzluc0tvSnNvczlSTVlTWmlGNTh5OVpuVmJCTWc4Y0U9Ojo3Kwg0zq_PLUS_3243A_PLUS_N_SLASH_fbiFV', NULL, 0, 0),
(3, 'E-INVENTORY', 'c0d3cbe29d3e6dab29bb307b9beaeec20050972206aac44120f16f2f3b1d4c3e', '4ced35e3e1f31ffa1d88cf09d49536319ec813f42acbde885ee3618600a4cdb9', 'Vtuiu8G_SLASH_YlGybJcrXQbsrfRMr17g5OphUV4MpvuvTJSeVKWLx_SLASH_MA_PLUS__SLASH_NKDMF1gHkP15ULz0Wa6TJiolXFK_SLASH_Q0iLISnw50tqiMrlCLIofEEXM6Oq43TB9WnlDe7wdwkHbUmdk_EQUALS_', NULL, 1, 0),
(4, 'E-SKED', 'a00f0692538bc31731c5b8a96e1e958e30dd62ef594d0e63b4742d50a98ac13a', 'ab85aeb5fa8e8148d42c4467a3e413c7f66a4949a18261a98bf181ab3ed6bec6', 'rIJSYuqS6tlZrhMCIhAE96lu2lSjCgGKRwYf8WvTdK7lY_PLUS_XtgeOHl5zUVa6sFT_PLUS_x1yBWhTMrKJ99JONDQ9Axl49C_SLASH_Q2VO3GE1xHPhxGewiw6OkowBrFYTKL_PLUS_8atoibqNzMw_EQUALS_', NULL, 1, 0),
(5, 'E-SKUELOG', '17a3c297d94d209e951e42234a635b01ff22caa1a35de6552f3ca006fee94b37', '250a6a351fe8528ab96275d09405487c2b8e9e6587a69bc45bf52e956a194c0c', 'hYZ3pbLQoa1sLhsQMRKQWFo1b_PLUS_y1Bvb7QLB4j8QGGWeQqndxzW3xZ9_SLASH_aq65iDy0alIcb3CRnN5W_SLASH_aCweVdNTDNjm6umYq7W379cG6NmvYWU6OmglhpzDwXu1ME_PLUS_t4AEyLUE_EQUALS_', NULL, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `ref_id` int(11) NOT NULL DEFAULT 0 COMMENT 'user id [Ref API]',
  `first_name` varchar(255) NOT NULL DEFAULT '',
  `middle_name` varchar(255) NOT NULL DEFAULT '',
  `middle_initial` varchar(10) NOT NULL DEFAULT '',
  `last_name` varchar(255) NOT NULL DEFAULT '',
  `suffix` varchar(255) NOT NULL DEFAULT '',
  `post_nominal` text DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `sex` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL DEFAULT '',
  `personal_email` varchar(100) DEFAULT '' COMMENT 'serve as Recovery Email Address [CCC Email Default]',
  `civil_status` varchar(100) NOT NULL DEFAULT '',
  `nationality` varchar(100) NOT NULL DEFAULT '',
  `birth_place` varchar(100) NOT NULL DEFAULT '',
  `contact_no` varchar(50) NOT NULL DEFAULT '',
  `brgy` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `province` varchar(255) NOT NULL DEFAULT '',
  `home_address` text NOT NULL DEFAULT '',
  `profile_pic` varchar(100) DEFAULT 'profile-img.png',
  `cover_photo` varchar(100) DEFAULT 'default-cover.jpg',
  `e_name` longtext NOT NULL DEFAULT '',
  `e_relationship` varchar(100) NOT NULL DEFAULT '',
  `e_contact` longtext NOT NULL DEFAULT '',
  `e_address` longtext NOT NULL DEFAULT '',
  `flag_status` int(11) NOT NULL DEFAULT 0,
  `flag_update` int(11) NOT NULL DEFAULT 0,
  `date_modify` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `ref_id`, `first_name`, `middle_name`, `middle_initial`, `last_name`, `suffix`, `post_nominal`, `birth_date`, `sex`, `email`, `personal_email`, `civil_status`, `nationality`, `birth_place`, `contact_no`, `brgy`, `city`, `province`, `home_address`, `profile_pic`, `cover_photo`, `e_name`, `e_relationship`, `e_contact`, `e_address`, `flag_status`, `flag_update`, `date_modify`) VALUES
(1, 1, 'MARLON', '', '', 'REOLO', '', NULL, '1995-12-04', 'male', 'mlreolo@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09199834580', 'Mayapa', 'Calamba City', 'Laguna', 'Christopher II', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, '2026-09-25 16:14:28');

-- --------------------------------------------------------

--
-- Table structure for table `user_log`
--

CREATE TABLE `user_log` (
  `user_log_id` int(11) NOT NULL,
  `login_date` datetime DEFAULT NULL,
  `logout_date` datetime DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `user_id` text NOT NULL,
  `session_id` text NOT NULL,
  `ip_address` varchar(20) NOT NULL,
  `device` varchar(255) NOT NULL,
  `system_id` int(11) NOT NULL DEFAULT 0,
  `token_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`token_id`)),
  `login_flag` int(11) NOT NULL DEFAULT 0,
  `user_level` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_log`
--

INSERT INTO `user_log` (`user_log_id`, `login_date`, `logout_date`, `action`, `user_id`, `session_id`, `ip_address`, `device`, `system_id`, `token_id`, `login_flag`, `user_level`) VALUES
(1, '2026-09-25 16:14:28', NULL, 'LOGIN', '1', '[[\"2026-09-25 16:14:28\", \"LOGIN\", \"::1\"], [\"2026-09-25 16:14:47\", \"LOGIN\", \"::1\"], [\"2026-09-25 16:14:53\", \"LOGIN\", \"::1\"], [\"2026-09-25 16:15:03\", \"LOGIN\", \"::1\"]]', '::1', '[]', 0, '[\"b1385352a40f7871ba8ea5fe0e129a3081e036aebcb88886593b09db38a4ee2b\", \"1eaee800b83bc469c3608040bf25ca4df997f08f985eeefd484dfbc70fce18df\", \"7032d2c2c9bf2c06c59b557db8ce6020c605c15cc2b2ff8203d4caac77293161\", \"01c21172e5975aadc45b9683615980f9e60b255f8b52cc8b27ca67fb03da5b0b\"]', 1, 4);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`activity_log_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facility`
--
ALTER TABLE `facility`
  ADD PRIMARY KEY (`facility_id`);

--
-- Indexes for table `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `LOGIN ACCESS` (`user_id`,`username`);

--
-- Indexes for table `office`
--
ALTER TABLE `office`
  ADD PRIMARY KEY (`office_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`room_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_access`
--
ALTER TABLE `system_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `SYSTEM ACCESSS` (`user_id`,`system_type`,`system_role`);

--
-- Indexes for table `system_key`
--
ALTER TABLE `system_key`
  ADD PRIMARY KEY (`system_id`),
  ADD UNIQUE KEY `idx_system_key` (`system_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_log`
--
ALTER TABLE `user_log`
  ADD PRIMARY KEY (`user_log_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `activity_log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `facility`
--
ALTER TABLE `facility`
  MODIFY `facility_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login`
--
ALTER TABLE `login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `office`
--
ALTER TABLE `office`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_access`
--
ALTER TABLE `system_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_key`
--
ALTER TABLE `system_key`
  MODIFY `system_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_log`
--
ALTER TABLE `user_log`
  MODIFY `user_log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
