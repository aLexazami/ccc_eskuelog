-- Migration: Create and Seed Transaction Sub-Concerns Table
-- Date: 2026-10-23
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Create Table Structure
CREATE TABLE IF NOT EXISTS `transaction_sub_concerns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_type_id` INT NOT NULL,
    `sub_concern_name` VARCHAR(150) NOT NULL,
    `flag_status` INT NOT NULL DEFAULT 0,
    CONSTRAINT `fk_sub_trans` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types`(`id`) ON DELETE CASCADE,
    INDEX `idx_trans_sub` (`transaction_type_id`, `flag_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed Data using Clean Scalar Subqueries
INSERT INTO `transaction_sub_concerns` (`transaction_type_id`, `sub_concern_name`) VALUES

-- 1. Office of the College President (OCP) - office_id = 1
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Executive Appointment Request'), 'Courtesy Call / Executive Meeting'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Executive Appointment Request'), 'Institutional Partnership Proposal'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Executive Appointment Request'), 'Policy & Administrative Escalation'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Document Signing & Endorsement'), 'MOA / MOU Signing Approval'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Document Signing & Endorsement'), 'Official Endorsement Letter'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Document Signing & Endorsement'), 'Executive Resolution Request'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Official Complaints & Grievances'), 'Student Conduct / Disciplinary Appeal'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Official Complaints & Grievances'), 'Academic Grievance Escalation'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 1 AND `transaction_name` = 'Official Complaints & Grievances'), 'Administrative & Staff Service Complaint'),

-- 2. Office of the Vice President for Academic Affairs (OVPAA) - office_id = 2
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Curriculum & Course Syllabus Inquiry'), 'Syllabus Copy Request for Transfer'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Curriculum & Course Syllabus Inquiry'), 'Prerequisite Evaluation & Crediting'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Curriculum & Course Syllabus Inquiry'), 'Course Description Verification'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Cross-Enrolment / Permit Approval'), 'Outbound Cross-Enrolment Permit'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Cross-Enrolment / Permit Approval'), 'Inbound Cross-Enrolment Acceptance'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Cross-Enrolment / Permit Approval'), 'Permit to Take Subjects in Another Campus'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Overload / Underload Subject Approval'), 'Graduating Student Overload Request'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Overload / Underload Subject Approval'), 'Working Student Underload Permit'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Overload / Underload Subject Approval'), 'Prerequisite Co-requisite Waiver'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Academic Appeals & Grade Re-evaluations'), 'Grade Transmutation & Encoding Review'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Academic Appeals & Grade Re-evaluations'), 'Completion of Incomplete (INC) Grade'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Academic Appeals & Grade Re-evaluations'), 'Academic Retention & Probation Appeal'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Graduation Clearance & Honors Verification'), 'Latin Honors Eligibility Audit'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Graduation Clearance & Honors Verification'), 'Graduation Deficiency Clearance'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 2 AND `transaction_name` = 'Graduation Clearance & Honors Verification'), 'Re-issuance of Graduation Clearance'),

-- 3. Office of the Vice President for Student Development and Auxiliary (OVPSDA) - office_id = 3
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Student ID Validation / Replacement'), 'ID Sticker Validation for Current Term'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Student ID Validation / Replacement'), 'Damaged / Defaced ID Replacement'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Student ID Validation / Replacement'), 'Lost Student ID Card Replacement'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Good Moral Certificate Request'), 'Transfer / Honorable Dismissal Clearance'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Good Moral Certificate Request'), 'Scholarship / Grant Requirement'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Good Moral Certificate Request'), 'Employment / Internship Application'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Scholarship & Financial Aid Inquiry'), 'CHED / Government Scholarship Application'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Scholarship & Financial Aid Inquiry'), 'Institutional Grant Renewal'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Scholarship & Financial Aid Inquiry'), 'Private Donor Allowance Verification'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Student Organization Accreditation & Event Permit'), 'New Organization Accreditation'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Student Organization Accreditation & Event Permit'), 'On-Campus Event Permit Approval'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Student Organization Accreditation & Event Permit'), 'Off-Campus Activity Clearance'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Guidance & Counseling Appointment'), 'Routine Academic & Personal Counseling'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Guidance & Counseling Appointment'), 'Psychological Evaluation / Testing'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 3 AND `transaction_name` = 'Guidance & Counseling Appointment'), 'Exit Interview for Dropping / Transferring'),

-- 4. Office of the Vice President for Administration and Finance (OVPAF) - office_id = 4
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Tuition & Fee Payment / Balance Verification'), 'Statement of Account (SOA) Request'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Tuition & Fee Payment / Balance Verification'), 'Assessment Discrepancy Verification'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Tuition & Fee Payment / Balance Verification'), 'Government Educational Loan Voucher'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Refund / Overpayment Request'), 'Dropped Course Tuition Refund'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Refund / Overpayment Request'), 'Double Payment / Credit Transfer'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Refund / Overpayment Request'), 'Graduation Deposit Refund'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Promissory Note & Financial Appeal'), 'Midterm / Final Exam Promissory Note'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Promissory Note & Financial Appeal'), 'Installment Extension Application'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Promissory Note & Financial Appeal'), 'Surcharge / Penalty Fee Waiver Request'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Campus Facilities & Equipment Rental'), 'Audio-Visual & Sound System Borrowing'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Campus Facilities & Equipment Rental'), 'Gymnasium / Auditorium Reservation'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 4 AND `transaction_name` = 'Campus Facilities & Equipment Rental'), 'Classroom / Multi-Purpose Hall Use'),

-- 5. Office of the Vice President for Research, Extension, Planning, and QA (OVPREPQA) - office_id = 5
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Research Ethics & Review Submission'), 'Undergraduate Thesis Ethics Protocol Review'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Research Ethics & Review Submission'), 'Ethics Clearance Certificate Issuance'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Research Ethics & Review Submission'), 'Human Subject Protection Review'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Extension & Community Outreach Partnership'), 'Community Outreach Project Proposal'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Extension & Community Outreach Partnership'), 'LGU / NGO Extension Partnership'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Extension & Community Outreach Partnership'), 'Outreach Activity Logistics Support'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Institutional Data & Survey Request'), 'Survey Questionnaire Permit Approval'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Institutional Data & Survey Request'), 'Campus Demographics & Statistics Request'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 5 AND `transaction_name` = 'Institutional Data & Survey Request'), 'Feasibility Study Data Access'),

-- 6. Office of the Vice President for College Advancement and Relations (OVPCAR) - office_id = 6
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 6 AND `transaction_name` = 'Alumni Membership & Transcript Services'), 'Alumni Lifetime Membership ID'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 6 AND `transaction_name` = 'Alumni Membership & Transcript Services'), 'Alumni Transcript Endorsement'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 6 AND `transaction_name` = 'Alumni Membership & Transcript Services'), 'Yearbook & Certificate Claiming'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 6 AND `transaction_name` = 'Industry Partnership & OJT Linkages'), 'OJT / Internship Host Company MOA'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 6 AND `transaction_name` = 'Industry Partnership & OJT Linkages'), 'Industry Partner Accreditation'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 6 AND `transaction_name` = 'Industry Partnership & OJT Linkages'), 'On-Campus Recruitment / Career Fair'),

-- 7. Department of Teacher Education (DTE) - office_id = 7
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 7 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Evaluate Passed Units & GWA'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 7 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Interview & Acceptance Permit'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 7 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Course Equivalence Verification'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 7 AND `transaction_name` = 'General Department Inquiry'), 'Department Advisory & Announcements'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 7 AND `transaction_name` = 'General Department Inquiry'), 'Faculty Consultation Schedule'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 7 AND `transaction_name` = 'General Department Inquiry'), 'Special Class / Petition Request'),

-- 8. Department of Arts and Sciences (DAS) - office_id = 8
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 8 AND `transaction_name` = 'General Education (GE) Subject Equivalence'), 'GE Subject Crediting from Other School'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 8 AND `transaction_name` = 'General Education (GE) Subject Equivalence'), 'Old Curriculum to New Curriculum Mapping'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 8 AND `transaction_name` = 'General Education (GE) Subject Equivalence'), 'Transferred GE Subject Validation'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 8 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Evaluate Passed Units & GWA'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 8 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Interview & Acceptance Permit'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 8 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Course Equivalence Verification'),

-- 9. Department of Business and Accountancy (DBA) - office_id = 9
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 9 AND `transaction_name` = 'Qualifying Exam Inquiry (Accountancy)'), 'Qualifying Exam Scope & Schedule'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 9 AND `transaction_name` = 'Qualifying Exam Inquiry (Accountancy)'), 'Qualifying Exam Result Verification'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 9 AND `transaction_name` = 'Qualifying Exam Inquiry (Accountancy)'), 'Re-evaluation / Retake Application'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 9 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Evaluate Passed Units & GWA'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 9 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Interview & Acceptance Permit'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 9 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Course Equivalence Verification'),

-- 10. Department of Computing and Informatics (DCI) - office_id = 10
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Capstone / Thesis Concern & Adviser Assignment'), 'Capstone Proposal Submission'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Capstone / Thesis Concern & Adviser Assignment'), 'Adviser Re-assignment Request'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Capstone / Thesis Concern & Adviser Assignment'), 'Defense Panel Schedule & Requirements'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Computer Lab Access & System Account Concern'), 'Lab Computer Account Password Reset'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Computer Lab Access & System Account Concern'), 'Student Portal Access Error'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Computer Lab Access & System Account Concern'), 'Specialized Software Access Request'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Evaluate Passed Units & GWA'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Interview & Acceptance Permit'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 10 AND `transaction_name` = 'Shifting / Department Transfer Evaluation'), 'Course Equivalence Verification'),

-- 11. Learning and Resource Information Center (LRIC) - office_id = 11
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Book Borrowing & Returning'), 'Book Renewal / Due Date Extension'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Book Borrowing & Returning'), 'Reserve Section Book Request'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Book Borrowing & Returning'), 'Inter-Library Loan Application'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Lost Book / Penalty Settlement'), 'Lost Book Replacement Assessment'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Lost Book / Penalty Settlement'), 'Overdue Fine Payment Breakdown'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Lost Book / Penalty Settlement'), 'Library Hold / Clearance Unblocking'),

((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Online Database & E-Journal Account Request'), 'Off-Campus E-Library Access Credentials'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Online Database & E-Journal Account Request'), 'IEEE / ScienceDirect Account Activation'),
((SELECT `id` FROM `transaction_types` WHERE `office_id` = 11 AND `transaction_name` = 'Online Database & E-Journal Account Request'), 'Turnitin Plagiarism Checker Account Request');

COMMIT;