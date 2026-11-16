-- ======================================================================
-- MIGRATION: Insert Student Profiles with Major for Users 1 to 60
-- (Skipping user_id = 2 as it already exists in the student table)
-- 5 users assigned per program_id (Programs 1 to 12)
-- ======================================================================

START TRANSACTION;

INSERT INTO `student` (
    `user_id`, 
    `student_id`, 
    `year_level`, 
    `department_id`,
    `program_id`,
    `major`
) VALUES 
-- Program 1: BSPsy (Department 1) -> Users 1, 3, 4, 5, 6
(1,  '2026-01001', 1, 1, 1, ''),
(3,  '2026-01002', 2, 1, 1, ''),
(4,  '2026-01003', 3, 1, 1, ''),
(5,  '2026-01004', 4, 1, 1, ''),
(6,  '2026-01005', 1, 1, 1, ''),

-- Program 2: BSA (Department 2) -> Users 7 to 11
(7,  '2026-02001', 2, 2, 2, ''),
(8,  '2026-02002', 3, 2, 2, ''),
(9,  '2026-02003', 4, 2, 2, ''),
(10, '2026-02004', 1, 2, 2, ''),
(11, '2026-02005', 2, 2, 2, ''),

-- Program 3: BSAIS (Department 2) -> Users 12 to 16
(12, '2026-03001', 3, 2, 3, ''),
(13, '2026-03002', 4, 2, 3, ''),
(14, '2026-03003', 1, 2, 3, ''),
(15, '2026-03004', 2, 2, 3, ''),
(16, '2026-03005', 3, 2, 3, ''),

-- Program 4: BSCS (Department 3) -> Users 17 to 21
(17, '2026-04001', 4, 3, 4, ''),
(18, '2026-04002', 1, 3, 4, ''),
(19, '2026-04003', 2, 3, 4, ''),
(20, '2026-04004', 3, 3, 4, ''),
(21, '2026-04005', 4, 3, 4, ''),

-- Program 5: BSIT (Department 3) -> Users 22 to 26
(22, '2026-05001', 1, 3, 5, ''),
(23, '2026-05002', 2, 3, 5, ''),
(24, '2026-05003', 3, 3, 5, ''),
(25, '2026-05004', 4, 3, 5, ''),
(26, '2026-05005', 1, 3, 5, ''),

-- Program 6: BEEd (Department 4) -> Users 27 to 31
(27, '2026-06001', 2, 4, 6, ''),
(28, '2026-06002', 3, 4, 6, ''),
(29, '2026-06003', 4, 4, 6, ''),
(30, '2026-06004', 1, 4, 6, ''),
(31, '2026-06005', 2, 4, 6, ''),

-- Program 7: BSEdM (Department 4 - Mathematics Major) -> Users 32 to 36
(32, '2026-07001', 3, 4, 7, 'Mathematics'),
(33, '2026-07002', 4, 4, 7, 'Mathematics'),
(34, '2026-07003', 1, 4, 7, 'Mathematics'),
(35, '2026-07004', 2, 4, 7, 'Mathematics'),
(36, '2026-07005', 3, 4, 7, 'Mathematics'),

-- Program 8: BSEdE (Department 4 - English Major) -> Users 37 to 41
(37, '2026-08001', 4, 4, 8, 'English'),
(38, '2026-08002', 1, 4, 8, 'English'),
(39, '2026-08003', 2, 4, 8, 'English'),
(40, '2026-08004', 3, 4, 8, 'English'),
(41, '2026-08005', 4, 4, 8, 'English'),

-- Program 9: BSEdS (Department 4 - Science Major) -> Users 42 to 46
(42, '2026-09001', 1, 4, 9, 'Science'),
(43, '2026-09002', 2, 4, 9, 'Science'),
(44, '2026-09003', 3, 4, 9, 'Science'),
(45, '2026-09004', 4, 4, 9, 'Science'),
(46, '2026-09005', 1, 4, 9, 'Science'),

-- Program 10: BSEdF (Department 4 - Filipino Major) -> Users 47 to 51
(47, '2026-10001', 2, 4, 10, 'Filipino'),
(48, '2026-10002', 3, 4, 10, 'Filipino'),
(49, '2026-10003', 4, 4, 10, 'Filipino'),
(50, '2026-10004', 1, 4, 10, 'Filipino'),
(51, '2026-10005', 2, 4, 10, 'Filipino'),

-- Program 11: BSEdSoc (Department 4 - Social Studies Major) -> Users 52 to 56
(52, '2026-11001', 3, 4, 11, 'Social Studies'),
(53, '2026-11002', 4, 4, 11, 'Social Studies'),
(54, '2026-11003', 1, 4, 11, 'Social Studies'),
(55, '2026-11004', 2, 4, 11, 'Social Studies'),
(56, '2026-11005', 3, 4, 11, 'Social Studies'),

-- Program 12: BECED (Department 4) -> Users 57 to 60
(57, '2026-12001', 4, 4, 12, ''),
(58, '2026-12002', 1, 4, 12, ''),
(59, '2026-12003', 2, 4, 12, ''),
(60, '2026-12004', 3, 4, 12, '');

COMMIT;