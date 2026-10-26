-- Seed Script: Populate Transaction Types / Services per Office
-- Date: 2026-10-23
-- Target Table: transaction_types

START TRANSACTION;

INSERT INTO `transaction_types` (`office_id`, `transaction_name`, `estimated_minutes`, `flag_status`) VALUES

-- 1. Office of the College President (OCP) - office_id = 1
(1, 'Executive Appointment Request', 15, 0),
(1, 'Document Signing & Endorsement', 10, 0),
(1, 'Official Complaints & Grievances', 20, 0),
(1, 'General Administrative Inquiry', 5, 0),

-- 2. Office of the Vice President for Academic Affairs (OVPAA) - office_id = 2
(2, 'Curriculum & Course Syllabus Inquiry', 10, 0),
(2, 'Cross-Enrolment / Permit Approval', 10, 0),
(2, 'Overload / Underload Subject Approval', 10, 0),
(2, 'Academic Appeals & Grade Re-evaluations', 15, 0),
(2, 'Graduation Clearance & Honors Verification', 10, 0),
(2, 'Other Academic Affairs Concerns', 10, 0),

-- 3. Office of the Vice President for Student Development and Auxiliary (OVPSDA) - office_id = 3
(3, 'Student ID Validation / Replacement', 5, 0),
(3, 'Good Moral Certificate Request', 10, 0),
(3, 'Scholarship & Financial Aid Inquiry', 10, 0),
(3, 'Student Organization Accreditation & Event Permit', 15, 0),
(3, 'Student Disciplinary Clearance', 10, 0),
(3, 'Guidance & Counseling Appointment', 15, 0),
(3, 'Other Student Development Services', 10, 0),

-- 4. Office of the Vice President for Administration and Finance (OVPAF) - office_id = 4
(4, 'Tuition & Fee Payment / Balance Verification', 10, 0),
(4, 'Refund / Overpayment Request', 15, 0),
(4, 'Promissory Note & Financial Appeal', 15, 0),
(4, 'Official Receipt (OR) Re-issuance Request', 5, 0),
(4, 'Campus Facilities & Equipment Rental', 15, 0),

-- 5. Office of the Vice President for Research, Extension, Planning, and QA (OVPREPQA) - office_id = 5
(5, 'Research Ethics & Review Submission', 15, 0),
(5, 'Extension & Community Outreach Partnership', 15, 0),
(5, 'Quality Assurance & Accreditation Inquiries', 10, 0),
(5, 'Institutional Data & Survey Request', 10, 0),

-- 6. Office of the Vice President for College Advancement and Relations (OVPCAR) - office_id = 6
(6, 'Alumni Membership & Transcript Services', 10, 0),
(6, 'Public Relations & Media Inquiries', 10, 0),
(6, 'Industry Partnership & OJT Linkages', 15, 0),
(6, 'Donations & Advancement Inquiries', 15, 0),

-- 7. Department of Teacher Education (DTE) - office_id = 7
(7, 'Academic Advising & Subject Consultation', 10, 0),
(7, 'Practice Teaching / Internship Requirement Clearance', 15, 0),
(7, 'Shifting / Department Transfer Evaluation', 15, 0),
(7, 'Departmental Exam / Special Exam Request', 10, 0),
(7, 'General Department Inquiry', 5, 0),

-- 8. Department of Arts and Sciences (DAS) - office_id = 8
(8, 'Academic Advising & Subject Consultation', 10, 0),
(8, 'General Education (GE) Subject Equivalence', 10, 0),
(8, 'Shifting / Department Transfer Evaluation', 15, 0),
(8, 'Departmental Clearance Signing', 5, 0),
(8, 'General Department Inquiry', 5, 0),

-- 9. Department of Business and Accountancy (DBA) - office_id = 9
(9, 'Academic Advising & Subject Consultation', 10, 0),
(9, 'Qualifying Exam Inquiry (Accountancy)', 10, 0),
(9, 'OJT / Practicum Endorsement', 10, 0),
(9, 'Shifting / Department Transfer Evaluation', 15, 0),
(9, 'General Department Inquiry', 5, 0),

-- 10. Department of Computing and Informatics (DCI) - office_id = 10
(10, 'Academic Advising & Subject Consultation', 10, 0),
(10, 'Capstone / Thesis Concern & Adviser Assignment', 15, 0),
(10, 'Practicum / Industry Internship Endorsement', 10, 0),
(10, 'Computer Lab Access & System Account Concern', 5, 0),
(10, 'Shifting / Department Transfer Evaluation', 15, 0),
(10, 'General Department Inquiry', 5, 0),

-- 11. Learning and Resource Information Center (LRIC) - office_id = 11
(11, 'Book Borrowing & Returning', 5, 0),
(11, 'Library Clearance Signing', 5, 0),
(11, 'Online Database & E-Journal Account Request', 5, 0),
(11, 'Discussion Room Reservation', 10, 0),
(11, 'Lost Book / Penalty Settlement', 10, 0),
(11, 'General Library Inquiry', 5, 0);

COMMIT;