-- ======================================================================
-- Migration: Seed Dummy Users (58 Records: IDs 3 to 60)
-- Date: 2026-11-16
-- Target Database: e_eskuelog
-- Total Users after seeding = 60 (12 Offices x 5 Staff per Office)
-- Office Mapping:
--   IDs 3–7   -> Office 1  (OCP - 5 users)
--   IDs 8–12  -> Office 2  (OVPAA - 5 users)
--   IDs 13–17 -> Office 3  (OVPSDA - 5 users)
--   IDs 18–22 -> Office 4  (OVPAF - 5 users)
--   IDs 23–27 -> Office 5  (OVPREPQA - 5 users)
--   IDs 28–32 -> Office 6  (OVPCAR - 5 users)
--   IDs 33–37 -> Office 7  (DTE - 5 users)
--   IDs 38–42 -> Office 8  (DAS - 5 users)
--   IDs 43–47 -> Office 9  (DBA - 5 users)
--   IDs 1, 2 (Existing) + IDs 48–50 -> Office 10 (DCI - 5 users)
--   IDs 51–55 -> Office 11 (LRIC - 5 users)
--   IDs 56–60 -> Office 12 (MISD - 5 users)
-- ======================================================================

START TRANSACTION;

INSERT INTO `users` (
    `ref_id`, `first_name`, `middle_name`, `middle_initial`, `last_name`,
    `suffix`, `post_nominal`, `birth_date`, `sex`, `email`, `personal_email`,
    `civil_status`, `nationality`, `birth_place`, `contact_no`, `brgy`, `city`,
    `province`, `home_address`, `profile_pic`, `cover_photo`, `e_name`,
    `e_relationship`, `e_contact`, `e_address`, `flag_status`, `flag_update`, `date_modify`
) VALUES
-- Office 1: OCP (5 users: IDs 3-7)
(3, 'CARLOS', 'MENDOZA', 'M', 'SANTOS', '', NULL, '1988-03-14', 'male', 'cmsantos@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000003', 'Parian', 'Calamba City', 'Laguna', 'Block 4 Lot 12 Villa de Calamba', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(4, 'ELENA', 'TORRES', 'T', 'REYES', '', NULL, '1991-07-22', 'female', 'etreyes@ccc.edu.ph', '', 'single', 'Filipino', 'Los Banos, Laguna', '09171000004', 'Real', 'Calamba City', 'Laguna', '142 Rizal Avenue', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(5, 'MIGUEL', 'AQUINO', 'A', 'CRUZ', '', NULL, '1985-11-05', 'male', 'macruz@ccc.edu.ph', '', 'married', 'Filipino', 'San Pablo, Laguna', '09171000005', 'Halang', 'Calamba City', 'Laguna', '78 National Highway', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(6, 'SOFIA', 'DELA CRUZ', 'D', 'BAUTISTA', '', NULL, '1990-02-18', 'female', 'sdbautista@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000006', 'Bucal', 'Calamba City', 'Laguna', '23 Springville Subd.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(7, 'ANTONIO', 'RAMOS', 'R', 'GARCIA', '', NULL, '1987-09-30', 'male', 'argarcia@ccc.edu.ph', '', 'married', 'Filipino', 'Cabuyao, Laguna', '09171000007', 'Banlic', 'Calamba City', 'Laguna', 'Lot 9 Phase 2 St. Joseph', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 2: OVPAA (5 users: IDs 8-12)
(8, 'ISABELLA', 'MORALES', 'M', 'MENDOZA', '', NULL, '1994-06-12', 'female', 'immendoza@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000008', 'Pansol', 'Calamba City', 'Laguna', '56 Solemar del Pansol', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(9, 'RAFAEL', 'CASTILLO', 'C', 'TORRES', '', NULL, '1989-12-01', 'male', 'rctorres@ccc.edu.ph', '', 'married', 'Filipino', 'Sta. Rosa, Laguna', '09171000009', 'Makiling', 'Calamba City', 'Laguna', '114 Makiling Heights', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(10, 'CAMILA', 'VILLANUEVA', 'V', 'RAMOS', '', NULL, '1996-04-25', 'female', 'cvramos@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000010', 'Lawa', 'Calamba City', 'Laguna', '89 Lakeview Homes', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(11, 'GABRIEL', 'FERNANDEZ', 'F', 'AQUINO', '', NULL, '1992-08-19', 'male', 'gfaquino@ccc.edu.ph', '', 'single', 'Filipino', 'Binan, Laguna', '09171000011', 'Canlubang', 'Calamba City', 'Laguna', '12 Carmelray Park', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(12, 'VALERIA', 'NAVARRO', 'N', 'CASTILLO', '', NULL, '1993-01-10', 'female', 'vncastillo@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000012', 'Uno', 'Calamba City', 'Laguna', '45 Burgos St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 3: OVPSDA (5 users: IDs 13-17)
(13, 'DANIEL', 'SALAZAR', 'S', 'MORALES', '', NULL, '1986-05-03', 'male', 'dsmorales@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000013', 'Dos', 'Calamba City', 'Laguna', '30 Mabini St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(14, 'NATALIA', 'GONZALES', 'G', 'VILLANUEVA', '', NULL, '1995-10-15', 'female', 'ngvillanueva@ccc.edu.ph', '', 'single', 'Filipino', 'Los Banos, Laguna', '09171000014', 'Tres', 'Calamba City', 'Laguna', '67 Bonifacio St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(15, 'JOSE', 'HERRERA', 'H', 'FERNANDEZ', '', NULL, '1984-07-08', 'male', 'jhfernandez@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000015', 'Quatro', 'Calamba City', 'Laguna', '19 Del Pilar St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(16, 'ANDREA', 'PEREZ', 'P', 'NAVARRO', '', NULL, '1991-11-27', 'female', 'apnavarro@ccc.edu.ph', '', 'married', 'Filipino', 'Cabuyao, Laguna', '09171000016', 'Singko', 'Calamba City', 'Laguna', '82 Luna St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(17, 'LUIS', 'DIAZ', 'D', 'SALAZAR', '', NULL, '1988-04-14', 'male', 'ldsalazar@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000017', 'Sais', 'Calamba City', 'Laguna', '104 Zamora St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 4: OVPAF (5 users: IDs 18-22)
(18, 'PAULA', 'RIVERA', 'R', 'GONZALES', '', NULL, '1994-09-02', 'female', 'prgonzales@ccc.edu.ph', '', 'single', 'Filipino', 'Sta. Cruz, Laguna', '09171000018', 'Siete', 'Calamba City', 'Laguna', '51 Lopez Jaena St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(19, 'MARCO', 'GOMEZ', 'G', 'HERRERA', '', NULL, '1990-06-21', 'male', 'mgherrera@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000019', 'Lecheria', 'Calamba City', 'Laguna', '15 Calamba Hills', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(20, 'CLARA', 'FLORES', 'F', 'PEREZ', '', NULL, '1997-01-29', 'female', 'cfperez@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000020', 'Palingon', 'Calamba City', 'Laguna', '93 Aplaya Road', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(21, 'RICARDO', 'CASTRO', 'C', 'DIAZ', '', NULL, '1983-08-11', 'male', 'rcdiaz@ccc.edu.ph', '', 'married', 'Filipino', 'San Pablo, Laguna', '09171000021', 'Lingga', 'Calamba City', 'Laguna', '28 Bayshore Subd.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(22, 'BEATRIZ', 'ORTIZ', 'O', 'RIVERA', '', NULL, '1992-03-17', 'female', 'borivera@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000022', 'Sampiruhan', 'Calamba City', 'Laguna', '64 Sampaguita St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 5: OVPREPQA (5 users: IDs 23-27)
(23, 'FERNANDO', 'SILVA', 'S', 'GOMEZ', '', NULL, '1989-10-06', 'male', 'fsgomez@ccc.edu.ph', '', 'married', 'Filipino', 'Los Banos, Laguna', '09171000023', 'Looc', 'Calamba City', 'Laguna', '37 Lakeside Park', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(24, 'MONICA', 'VARGAS', 'V', 'CASTRO', '', NULL, '1995-05-24', 'female', 'mvcastro@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000024', 'Banadero', 'Calamba City', 'Laguna', '110 Maharlika Ave.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(25, 'EDUARDO', 'ROJAS', 'R', 'ORTIZ', '', NULL, '1986-12-19', 'male', 'erortiz@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000025', 'San Juan', 'Calamba City', 'Laguna', '49 Narra St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(26, 'PATRICIA', 'MENDEZ', 'M', 'SILVA', '', NULL, '1993-07-13', 'female', 'pmsilva@ccc.edu.ph', '', 'single', 'Filipino', 'Sta. Rosa, Laguna', '09171000026', 'San Jose', 'Calamba City', 'Laguna', '72 Molave St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(27, 'ALEJANDRO', 'CRUZ', 'C', 'VARGAS', '', NULL, '1990-02-04', 'male', 'acvargas@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000027', 'Mayapa', 'Calamba City', 'Laguna', '18 St. Francis Homes', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 6: OVPCAR (5 users: IDs 28-32)
(28, 'DANIELA', 'REYES', 'R', 'ROJAS', '', NULL, '1996-08-28', 'female', 'drrojas@ccc.edu.ph', '', 'single', 'Filipino', 'Cabuyao, Laguna', '09171000028', 'Paciano Rizal', 'Calamba City', 'Laguna', '95 Checkpoint Road', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(29, 'ROBERTO', 'SANTOS', 'S', 'MENDEZ', '', NULL, '1985-04-09', 'male', 'rsmendez@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000029', 'Sirang Lupa', 'Calamba City', 'Laguna', '31 Industrial Valley', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(30, 'GABRIELA', 'BAUTISTA', 'B', 'DELGADO', '', NULL, '1994-11-16', 'female', 'gbdelgado@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000030', 'Majada Out', 'Calamba City', 'Laguna', '60 Cypress Village', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(31, 'ENRIQUE', 'GARCIA', 'G', 'VEGA', '', NULL, '1987-06-30', 'male', 'egvega@ccc.edu.ph', '', 'married', 'Filipino', 'Los Banos, Laguna', '09171000031', 'Majada In', 'Calamba City', 'Laguna', '14 Acacia Grove', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(32, 'MARIANA', 'MENDOZA', 'M', 'CABRERA', '', NULL, '1991-09-12', 'female', 'mmcabrera@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000032', 'Palo-Alto', 'Calamba City', 'Laguna', '88 Palm Hill Subd.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 7: DTE (5 users: IDs 33-37)
(33, 'VICTOR', 'TORRES', 'T', 'CAMPOS', '', NULL, '1988-01-23', 'male', 'vtcampos@ccc.edu.ph', '', 'single', 'Filipino', 'San Pablo, Laguna', '09171000033', 'Barandal', 'Calamba City', 'Laguna', '42 Ridgeview Estates', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(34, 'LUCIA', 'RAMOS', 'R', 'SOLIS', '', NULL, '1995-03-07', 'female', 'lrsolis@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000034', 'Prinza', 'Calamba City', 'Laguna', '75 Riverbend Homes', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(35, 'HECTOR', 'AQUINO', 'A', 'LUNA', '', NULL, '1984-10-18', 'male', 'haluna@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000035', 'Bubuyan', 'Calamba City', 'Laguna', '21 Greenfields Subd.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(36, 'TERESA', 'CASTILLO', 'C', 'IBARRA', '', NULL, '1990-05-14', 'female', 'tcibarra@ccc.edu.ph', '', 'single', 'Filipino', 'Binan, Laguna', '09171000036', 'Bunggo', 'Calamba City', 'Laguna', '53 Highland Park', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(37, 'OSCAR', 'MORALES', 'M', 'OCAMPO', '', NULL, '1989-08-25', 'male', 'omocampo@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000037', 'Burol', 'Calamba City', 'Laguna', '97 Crestview Subd.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 8: DAS (5 users: IDs 38-42)
(38, 'VERONICA', 'VILLANUEVA', 'V', 'PINEDA', '', NULL, '1993-12-03', 'female', 'vvpineda@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000038', 'Camaligan', 'Calamba City', 'Laguna', '16 Valley Breeze', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(39, 'SERGIO', 'FERNANDEZ', 'F', 'SORIANO', '', NULL, '1986-07-19', 'male', 'sfsoriano@ccc.edu.ph', '', 'married', 'Filipino', 'Cabuyao, Laguna', '09171000039', 'Kay-Anlog', 'Calamba City', 'Laguna', '69 Sierra Madre St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(40, 'LORENA', 'NAVARRO', 'N', 'TOLENTINO', '', NULL, '1997-02-28', 'female', 'lntolentino@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000040', 'La Mesa', 'Calamba City', 'Laguna', '118 Southville Ave.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(41, 'ARTURO', 'SALAZAR', 'S', 'VALDEZ', '', NULL, '1985-09-08', 'male', 'asvaldez@ccc.edu.ph', '', 'married', 'Filipino', 'Sta. Cruz, Laguna', '09171000041', 'Lagundi', 'Calamba City', 'Laguna', '34 Eastwood Homes', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(42, 'CECILIA', 'GONZALES', 'G', 'ZAMORA', '', NULL, '1992-04-21', 'female', 'cgzamora@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000042', 'Lamesa', 'Calamba City', 'Laguna', '80 Sunrise Village', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 9: DBA (5 users: IDs 43-47)
(43, 'RAMON', 'HERRERA', 'H', 'ALVAREZ', '', NULL, '1988-11-11', 'male', 'rhalvarez@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000043', 'Maunong', 'Calamba City', 'Laguna', '47 Cedar Lane', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(44, 'DIANA', 'PEREZ', 'P', 'BENITEZ', '', NULL, '1994-06-05', 'female', 'dpbenitez@ccc.edu.ph', '', 'single', 'Filipino', 'Los Banos, Laguna', '09171000044', 'Milagrosa', 'Calamba City', 'Laguna', '91 Milagrosa Subd.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(45, 'EMILIO', 'DIAZ', 'D', 'CORDOVA', '', NULL, '1991-01-15', 'male', 'edcordova@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000045', 'Putho', 'Calamba City', 'Laguna', '25 Pinewood St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(46, 'KRISTINA', 'RIVERA', 'R', 'DEL ROSARIO', '', NULL, '1993-08-29', 'female', 'krdelrosario@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000046', 'Real', 'Calamba City', 'Laguna', '102 Chipeco Ave.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(47, 'JOSHUA', 'GOMEZ', 'G', 'ESPINOSA', '', NULL, '1995-03-19', 'male', 'jgespinosa@ccc.edu.ph', '', 'single', 'Filipino', 'Sta. Rosa, Laguna', '09171000047', 'Parian', 'Calamba City', 'Laguna', '58 Bamboo Grove', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 10: DCI (Existing Users 1 & 2 + Remaining 3 users: IDs 48-50)
(48, 'ANGELICA', 'CASTRO', 'C', 'FAJARDO', '', NULL, '1992-10-04', 'female', 'acfajardo@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000048', 'Halang', 'Calamba City', 'Laguna', '73 Villa Remedios', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(49, 'CHRISTIAN', 'ORTIZ', 'O', 'GALANG', '', NULL, '1990-07-01', 'male', 'cogalang@ccc.edu.ph', '', 'married', 'Filipino', 'Cabuyao, Laguna', '09171000049', 'Bucal', 'Calamba City', 'Laguna', '39 Bucal Springs', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(50, 'MICHELLE', 'SILVA', 'S', 'IGNACIO', '', NULL, '1996-12-22', 'female', 'msignacio@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000050', 'Banlic', 'Calamba City', 'Laguna', '84 San Isidro Homes', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 11: LRIC (5 users: IDs 51-55)
(51, 'KENNETH', 'VARGAS', 'V', 'JACINTO', '', NULL, '1989-04-16', 'male', 'kvjacinto@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000051', 'Lawa', 'Calamba City', 'Laguna', '11 Lawa Bayview', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(52, 'KATRINA', 'ROJAS', 'R', 'LAXAMANA', '', NULL, '1994-09-27', 'female', 'krlaxamana@ccc.edu.ph', '', 'single', 'Filipino', 'Los Banos, Laguna', '09171000052', 'Lecheria', 'Calamba City', 'Laguna', '66 Hilltop Terrace', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(53, 'MARK', 'MENDEZ', 'M', 'MANALO', '', NULL, '1987-02-08', 'male', 'mmmanalo@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000053', 'Pansol', 'Calamba City', 'Laguna', '29 Hot Spring Village', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(54, 'JENNIFER', 'DELGADO', 'D', 'NOLASCO', '', NULL, '1991-06-11', 'female', 'jdnolasco@ccc.edu.ph', '', 'married', 'Filipino', 'San Pablo, Laguna', '09171000054', 'Makiling', 'Calamba City', 'Laguna', '90 Forest Edge', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(55, 'PATRICK', 'VEGA', 'V', 'PANGILINAN', '', NULL, '1995-11-30', 'male', 'pvpangilinan@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000055', 'Canlubang', 'Calamba City', 'Laguna', '44 Carmel Village', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),

-- Office 12: MISD (5 users: IDs 56-60)
(56, 'STEPHANIE', 'CABRERA', 'C', 'QUIAMBAO', '', NULL, '1993-05-02', 'female', 'scquiambao@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000056', 'Mayapa', 'Calamba City', 'Laguna', '77 St. Jude Subd.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(57, 'KEVIN', 'CAMPOS', 'C', 'ROSALES', '', NULL, '1990-10-14', 'male', 'kcrosales@ccc.edu.ph', '', 'married', 'Filipino', 'Binan, Laguna', '09171000057', 'Paciano Rizal', 'Calamba City', 'Laguna', '13 Golden City', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(58, 'MELISSA', 'SOLIS', 'S', 'SANTIAGO', '', NULL, '1996-01-26', 'female', 'mssantiago@ccc.edu.ph', '', 'single', 'Filipino', 'Calamba, Laguna', '09171000058', 'Real', 'Calamba City', 'Laguna', '52 Burgos Extension', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(59, 'BRYAN', 'LUNA', 'L', 'TOLENTINO', '', NULL, '1988-08-07', 'male', 'bltolentino@ccc.edu.ph', '', 'married', 'Filipino', 'Calamba, Laguna', '09171000059', 'Uno', 'Calamba City', 'Laguna', '86 Rizal St.', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW()),
(60, 'CHRISTINE', 'IBARRA', 'I', 'UMALI', '', NULL, '1992-03-20', 'female', 'ciumali@ccc.edu.ph', '', 'single', 'Filipino', 'Sta. Rosa, Laguna', '09171000060', 'Parian', 'Calamba City', 'Laguna', '35 Villa de Calamba', 'profile-img.png', 'default-cover.jpg', '', '', '', '', 0, 0, NOW())
ON DUPLICATE KEY UPDATE
    `first_name`     = VALUES(`first_name`),
    `middle_name`    = VALUES(`middle_name`),
    `middle_initial` = VALUES(`middle_initial`),
    `last_name`      = VALUES(`last_name`),
    `email`          = VALUES(`email`),
    `contact_no`     = VALUES(`contact_no`),
    `date_modify`    = NOW();

COMMIT;