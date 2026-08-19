-- ============================================================
--  ARAMS 1.0 - DATABASE SCHEMA (STRUCTURE ONLY)
--  Universiti Tun Hussein Onn Malaysia
-- ------------------------------------------------------------
--  Structure only: CREATE TABLE, views, indexes, foreign keys.
--  ALL DATA ROWS REMOVED - this file contains no staff names,
--  email addresses, password hashes or other personal data.
--
--  The populated dump is kept locally and is NOT in this
--  repository. See docs/ for the full schema assessment.
-- ============================================================

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 19, 2026 at 01:57 PM
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
-- Database: `arams_uthm`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_admin`
--

CREATE TABLE `tbl_admin` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='TNCPI administrator profiles';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_log`
--

CREATE TABLE `tbl_audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'e.g. Approved Publication, Rejected Grant',
  `target_id` int(11) DEFAULT NULL COMMENT 'ID of the affected record',
  `target_type` varchar(50) DEFAULT NULL COMMENT 'e.g. Publication, Grant, HIndex',
  `details` text DEFAULT NULL,
  `logged_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Admin action audit trail';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_award`
--

CREATE TABLE `tbl_award` (
  `award_id` int(11) NOT NULL,
  `award_name` varchar(300) NOT NULL,
  `award_type` varchar(100) DEFAULT NULL COMMENT 'e.g. Gold, Silver, Special Award',
  `organiser` varchar(200) DEFAULT NULL,
  `level` enum('University','National','International') NOT NULL DEFAULT 'University',
  `award_year` year(4) NOT NULL,
  `lecturer_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Awards and recognition received by lecturers';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty`
--

CREATE TABLE `tbl_faculty` (
  `faculty_id` int(11) NOT NULL,
  `faculty_code` varchar(10) NOT NULL,
  `faculty_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='UTHM Faculty reference list';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty_transfer`
--

CREATE TABLE `tbl_faculty_transfer` (
  `transfer_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `from_faculty_id` int(11) DEFAULT NULL,
  `to_faculty_id` int(11) NOT NULL,
  `transferred_at` datetime NOT NULL DEFAULT current_timestamp(),
  `transferred_by` int(11) DEFAULT NULL COMMENT 'admin user_id who performed the transfer',
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_grant`
--

CREATE TABLE `tbl_grant` (
  `grant_id` int(11) NOT NULL,
  `grant_title` varchar(500) NOT NULL,
  `grant_code` varchar(100) DEFAULT NULL COMMENT 'Official funder reference e.g. FRGS/1/2023/ICT02/UTHM/01/1',
  `funder` varchar(200) DEFAULT NULL COMMENT 'e.g. MOHE, MOSTI, UTHM Internal, Industry',
  `grant_category` varchar(50) NOT NULL DEFAULT 'Others' COMMENT 'FRT grant type, scoped by grant_level in the form',
  `grant_level` varchar(30) DEFAULT NULL,
  `role` enum('PI','Co-I','Member') NOT NULL DEFAULT 'Member' COMMENT 'PI = Principal Investigator, critical for income attribution',
  `amount` decimal(15,2) DEFAULT NULL COMMENT 'Total approved grant amount in RM',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Completed','Terminated','Pending Approval') NOT NULL DEFAULT 'Active',
  `mygrants_id` varchar(50) DEFAULT NULL COMMENT 'ID from mygrants.gov.my portal',
  `data_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Research grants and funding received';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_hindex`
--

CREATE TABLE `tbl_hindex` (
  `hindex_id` int(11) NOT NULL,
  `record_year` year(4) NOT NULL COMMENT 'Year the H-Index value was recorded',
  `hindex_value` int(5) NOT NULL,
  `citation_count` int(10) DEFAULT NULL COMMENT 'Total citations as of this year',
  `source` enum('Scopus','WoS','Google Scholar','Others') NOT NULL DEFAULT 'Scopus',
  `data_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Annual H-Index and citation records';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ip_record`
--

CREATE TABLE `tbl_ip_record` (
  `ip_id` int(11) NOT NULL,
  `ip_title` varchar(500) NOT NULL,
  `ip_type` enum('Patent','Copyright','Trademark','Industrial Design','Trade Secret','Others') NOT NULL DEFAULT 'Patent',
  `ip_number` varchar(100) DEFAULT NULL COMMENT 'MyIPO registration number e.g. PI2024XXXXXX',
  `inventors` text DEFAULT NULL COMMENT 'All inventor names in order',
  `country` varchar(50) DEFAULT NULL COMMENT 'e.g. Malaysia, USA, PCT International',
  `filing_date` date DEFAULT NULL,
  `grant_date` date DEFAULT NULL COMMENT 'Date IP was granted or registered',
  `registration_status` varchar(20) NOT NULL DEFAULT 'Filed' COMMENT 'FRT IP status: Filed / Awarded',
  `data_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Intellectual property records (patents, copyrights, trademarks)';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_kpi_target`
--

CREATE TABLE `tbl_kpi_target` (
  `kpi_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL COMMENT 'NULL = institution-wide target',
  `metric` enum('Publications','Grants','H-Index','Research Income (RM)','IP Records','Q1 Publications','Q2 Publications') NOT NULL,
  `target_value` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='TNCPI annual KPI targets for dashboard progress tracking';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_kpi_task`
--

CREATE TABLE `tbl_kpi_task` (
  `task_id` int(11) NOT NULL,
  `tdpp_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `task_title` varchar(255) NOT NULL,
  `task_desc` text DEFAULT NULL,
  `task_type` enum('Publication','Grant','H-Index','Research Income','IP','Award') NOT NULL,
  `target_count` int(11) NOT NULL DEFAULT 1,
  `criteria_quartile` enum('Any','Q1','Q2','Q3','Q4') DEFAULT 'Any',
  `criteria_indexing` enum('Any','WOS','Scopus','MyCite') DEFAULT 'Any',
  `criteria_grant_level` enum('Any','Universiti','National','International','Industries') DEFAULT 'Any',
  `criteria_min_amount` decimal(12,2) DEFAULT 0.00,
  `assigned_date` date NOT NULL DEFAULT curdate(),
  `deadline` date NOT NULL,
  `status` enum('Pending','In Progress','Completed','Completed (Late)','Overdue') NOT NULL DEFAULT 'Pending',
  `progress_count` int(11) NOT NULL DEFAULT 0,
  `completed_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_lecturer`
--

CREATE TABLE `tbl_lecturer` (
  `lecturer_id` int(11) NOT NULL,
  `staff_no` varchar(20) NOT NULL COMMENT 'e.g. UTH980234',
  `full_name` varchar(150) NOT NULL,
  `title` varchar(50) DEFAULT NULL COMMENT 'e.g. Dr., Prof., Ts.',
  `position` varchar(100) DEFAULT NULL COMMENT 'e.g. Senior Lecturer, Associate Professor',
  `grade` varchar(10) DEFAULT NULL COMMENT 'JPA grade e.g. DS45, DH52',
  `phone` varchar(20) DEFAULT NULL,
  `faculty_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `specialisation` varchar(200) DEFAULT NULL COMMENT 'Field of research expertise',
  `scopus_id` varchar(30) DEFAULT NULL COMMENT 'Scopus Author ID',
  `orcid_id` varchar(30) DEFAULT NULL COMMENT 'ORCID ID e.g. 0000-0002-XXXX-XXXX',
  `researcher_id` varchar(30) DEFAULT NULL COMMENT 'WoS ResearcherID / Publons',
  `lens_id` varchar(30) DEFAULT NULL COMMENT 'Lens.org ID',
  `google_scholar` varchar(255) DEFAULT NULL COMMENT 'Google Scholar profile URL',
  `profile_photo` varchar(255) DEFAULT NULL,
  `research_centre` varchar(150) DEFAULT NULL COMMENT 'COE / COR / Focus Group affiliation',
  `research_group_id` int(11) DEFAULT NULL COMMENT 'FK to tbl_research_group when category=FG',
  `managerial_position` tinyint(1) DEFAULT 0,
  `research_group_category` varchar(20) DEFAULT NULL,
  `status_researcher` varchar(50) DEFAULT NULL,
  `cv_url` varchar(255) DEFAULT NULL COMMENT 'Link to community.uthm.edu.my profile',
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='UTHM Lecturer profiles';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_login_attempt`
--

CREATE TABLE `tbl_login_attempt` (
  `attempt_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL COMMENT 'IPv4/IPv6 of the request',
  `email` varchar(100) DEFAULT NULL COMMENT 'Email that was tried (for reference)',
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Failed login attempts for brute-force rate limiting';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notification`
--

CREATE TABLE `tbl_notification` (
  `notif_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `data_id` int(11) DEFAULT NULL COMMENT 'Related research data record if applicable'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='In-system notifications for submission and validation events';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_password_reset`
--

CREATE TABLE `tbl_password_reset` (
  `reset_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL COMMENT 'Email that requested the reset (matches tbl_user.email)',
  `otp_hash` varchar(255) NOT NULL COMMENT 'bcrypt hash of the 6-digit code (never store the code in plain text)',
  `expires_at` datetime NOT NULL COMMENT 'Code is only valid until this time (e.g. +10 minutes)',
  `attempts` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'How many times verification was tried (lock after a few)',
  `used` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = code already consumed, cannot be reused',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='One-time codes for the Forgot Password / reset flow';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_publication`
--

CREATE TABLE `tbl_publication` (
  `publication_id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL,
  `authors` text DEFAULT NULL COMMENT 'All author names in order',
  `author_role` varchar(50) NOT NULL DEFAULT 'Co-Author' COMMENT 'FRT author role',
  `student_author` tinyint(1) DEFAULT 0,
  `national_collaboration` tinyint(1) DEFAULT 0,
  `international_collaboration` tinyint(1) DEFAULT 0,
  `industries_collaboration` tinyint(1) DEFAULT 0,
  `journal_name` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL COMMENT 'Country of publication / journal (FRT)',
  `issn` varchar(20) DEFAULT NULL,
  `pub_year` year(4) NOT NULL,
  `volume` varchar(20) DEFAULT NULL,
  `issue` varchar(20) DEFAULT NULL,
  `pages` varchar(30) DEFAULT NULL,
  `pub_type` varchar(50) NOT NULL DEFAULT 'Journal' COMMENT 'FRT publication type',
  `indexing_type` set('Scopus','WoS','MyCite','ERA','ERIC','Others') NOT NULL DEFAULT 'Scopus' COMMENT 'Can be multiple e.g. Scopus,WoS',
  `quartile` enum('Q1','Q2','Q3','Q4','N/A') NOT NULL DEFAULT 'N/A' COMMENT 'SJR / JCR quartile — critical for MyRA KPI scoring',
  `impact_factor` decimal(6,3) DEFAULT NULL COMMENT 'Journal Impact Factor from JCR/Clarivate',
  `doi` varchar(255) DEFAULT NULL COMMENT 'Digital Object Identifier',
  `url` varchar(500) DEFAULT NULL COMMENT 'Full URL to the published paper',
  `data_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Academic publications — journals, conferences, books';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_report`
--

CREATE TABLE `tbl_report` (
  `report_id` int(11) NOT NULL,
  `report_type` enum('Annual Research Report','Publications Summary','Grants Summary','H-Index Report','Research Income Report','Faculty Performance Report','Individual Lecturer Report') NOT NULL DEFAULT 'Annual Research Report',
  `report_year` year(4) DEFAULT NULL,
  `faculty_filter` varchar(100) DEFAULT NULL COMMENT 'NULL = all faculties',
  `format` enum('Excel','PDF','CSV') NOT NULL DEFAULT 'Excel',
  `date_generated` datetime NOT NULL DEFAULT current_timestamp(),
  `admin_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Generated institutional research performance reports';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_research_data`
--

CREATE TABLE `tbl_research_data` (
  `data_id` int(11) NOT NULL,
  `submission_date` date NOT NULL DEFAULT curdate(),
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL COMMENT 'admin user_id who soft-deleted',
  `remarks` text DEFAULT NULL COMMENT 'Admin feedback / rejection reason',
  `validated_at` datetime DEFAULT NULL COMMENT 'Timestamp of approval or rejection',
  `lecturer_id` int(11) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT 'NULL until admin takes action'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Parent research submission record — links to Publication, Grant, HIndex, IP, Income';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_research_group`
--

CREATE TABLE `tbl_research_group` (
  `group_id` int(11) NOT NULL,
  `group_code` varchar(30) DEFAULT NULL COMMENT 'short code e.g. DASM, CERCOM',
  `group_name` varchar(150) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL COMMENT 'owning faculty; NULL = university-wide',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Research groups / focus groups master list';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_research_income`
--

CREATE TABLE `tbl_research_income` (
  `income_id` int(11) NOT NULL,
  `source` varchar(200) NOT NULL COMMENT 'Name of funder / company',
  `income_category` varchar(50) NOT NULL DEFAULT 'Research Grant' COMMENT 'FRT income generation type',
  `amount` decimal(15,2) NOT NULL COMMENT 'Actual amount received in RM',
  `year_received` year(4) NOT NULL,
  `related_grant_id` int(11) DEFAULT NULL COMMENT 'FK to Tbl_Grant if income is from a grant',
  `data_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Research income received from grants, consultancy, commercialisation';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_tdpp`
--

CREATE TABLE `tbl_tdpp` (
  `tdpp_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user`
--

CREATE TABLE `tbl_user` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hashed password',
  `role` enum('Lecturer','Admin','TDPP') NOT NULL DEFAULT 'Lecturer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Login credentials for all ARAMS users';

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_grant_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_grant_summary` (
`full_name` varchar(150)
,`faculty_code` varchar(10)
,`grant_category` varchar(50)
,`role` enum('PI','Co-I','Member')
,`status` enum('Active','Completed','Terminated','Pending Approval')
,`grant_count` bigint(21)
,`total_amount_rm` decimal(37,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_lecturer_kpi`
-- (See below for the actual view)
--
CREATE TABLE `vw_lecturer_kpi` (
`lecturer_id` int(11)
,`full_name` varchar(150)
,`staff_no` varchar(20)
,`faculty_code` varchar(10)
,`total_publications` bigint(21)
,`q1_pubs` decimal(23,0)
,`q2_pubs` decimal(23,0)
,`total_grants` bigint(21)
,`grants_as_pi` decimal(22,0)
,`total_grant_amount_rm` decimal(37,2)
,`current_hindex` int(5)
,`total_citations` int(10)
,`total_ip` bigint(21)
,`total_income_rm` decimal(37,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_pending_validation`
-- (See below for the actual view)
--
CREATE TABLE `vw_pending_validation` (
`data_id` int(11)
,`submission_date` date
,`status` enum('Pending','Approved','Rejected')
,`lecturer_name` varchar(150)
,`staff_no` varchar(20)
,`faculty_code` varchar(10)
,`record_type` varchar(15)
,`record_title` varchar(500)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_pub_summary_by_year`
-- (See below for the actual view)
--
CREATE TABLE `vw_pub_summary_by_year` (
`full_name` varchar(150)
,`faculty_code` varchar(10)
,`pub_year` year(4)
,`total_pubs` bigint(21)
,`q1_count` decimal(23,0)
,`q2_count` decimal(23,0)
,`scopus_count` decimal(23,0)
,`wos_count` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_grant_summary`
--
DROP TABLE IF EXISTS `vw_grant_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_grant_summary`  AS SELECT `l`.`full_name` AS `full_name`, `f`.`faculty_code` AS `faculty_code`, `g`.`grant_category` AS `grant_category`, `g`.`role` AS `role`, `g`.`status` AS `status`, count(`g`.`grant_id`) AS `grant_count`, sum(`g`.`amount`) AS `total_amount_rm` FROM (((`tbl_grant` `g` join `tbl_research_data` `rd` on(`g`.`data_id` = `rd`.`data_id`)) join `tbl_lecturer` `l` on(`rd`.`lecturer_id` = `l`.`lecturer_id`)) join `tbl_faculty` `f` on(`l`.`faculty_id` = `f`.`faculty_id`)) WHERE `rd`.`status` = 'Approved' AND `rd`.`is_deleted` = 0 GROUP BY `l`.`lecturer_id`, `g`.`grant_category`, `g`.`role`, `g`.`status` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_lecturer_kpi`
--
DROP TABLE IF EXISTS `vw_lecturer_kpi`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_lecturer_kpi`  AS SELECT `l`.`lecturer_id` AS `lecturer_id`, `l`.`full_name` AS `full_name`, `l`.`staff_no` AS `staff_no`, `f`.`faculty_code` AS `faculty_code`, count(distinct `p`.`publication_id`) AS `total_publications`, sum(`p`.`quartile` = 'Q1') AS `q1_pubs`, sum(`p`.`quartile` = 'Q2') AS `q2_pubs`, count(distinct `g`.`grant_id`) AS `total_grants`, sum(case when `g`.`role` = 'PI' then 1 else 0 end) AS `grants_as_pi`, sum(`g`.`amount`) AS `total_grant_amount_rm`, max(`h`.`hindex_value`) AS `current_hindex`, max(`h`.`citation_count`) AS `total_citations`, count(distinct `ip`.`ip_id`) AS `total_ip`, sum(`inc`.`amount`) AS `total_income_rm` FROM (((((((`tbl_lecturer` `l` join `tbl_faculty` `f` on(`l`.`faculty_id` = `f`.`faculty_id`)) left join `tbl_research_data` `rd` on(`rd`.`lecturer_id` = `l`.`lecturer_id` and `rd`.`status` = 'Approved' and `rd`.`is_deleted` = 0)) left join `tbl_publication` `p` on(`p`.`data_id` = `rd`.`data_id`)) left join `tbl_grant` `g` on(`g`.`data_id` = `rd`.`data_id`)) left join `tbl_hindex` `h` on(`h`.`data_id` = `rd`.`data_id`)) left join `tbl_ip_record` `ip` on(`ip`.`data_id` = `rd`.`data_id`)) left join `tbl_research_income` `inc` on(`inc`.`data_id` = `rd`.`data_id`)) GROUP BY `l`.`lecturer_id` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_pending_validation`
--
DROP TABLE IF EXISTS `vw_pending_validation`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_pending_validation`  AS SELECT `rd`.`data_id` AS `data_id`, `rd`.`submission_date` AS `submission_date`, `rd`.`status` AS `status`, `l`.`full_name` AS `lecturer_name`, `l`.`staff_no` AS `staff_no`, `f`.`faculty_code` AS `faculty_code`, CASE WHEN `p`.`publication_id` is not null THEN 'Publication' WHEN `g`.`grant_id` is not null THEN 'Grant' WHEN `h`.`hindex_id` is not null THEN 'H-Index' WHEN `ip`.`ip_id` is not null THEN 'IP Record' WHEN `inc`.`income_id` is not null THEN 'Research Income' ELSE 'Unknown' END AS `record_type`, coalesce(`p`.`title`,`g`.`grant_title`,concat('H-Index ',`h`.`record_year`),`ip`.`ip_title`,`inc`.`source`) AS `record_title` FROM (((((((`tbl_research_data` `rd` join `tbl_lecturer` `l` on(`rd`.`lecturer_id` = `l`.`lecturer_id`)) join `tbl_faculty` `f` on(`l`.`faculty_id` = `f`.`faculty_id`)) left join `tbl_publication` `p` on(`p`.`data_id` = `rd`.`data_id`)) left join `tbl_grant` `g` on(`g`.`data_id` = `rd`.`data_id`)) left join `tbl_hindex` `h` on(`h`.`data_id` = `rd`.`data_id`)) left join `tbl_ip_record` `ip` on(`ip`.`data_id` = `rd`.`data_id`)) left join `tbl_research_income` `inc` on(`inc`.`data_id` = `rd`.`data_id`)) WHERE `rd`.`status` = 'Pending' AND `rd`.`is_deleted` = 0 ORDER BY `rd`.`submission_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_pub_summary_by_year`
--
DROP TABLE IF EXISTS `vw_pub_summary_by_year`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_pub_summary_by_year`  AS SELECT `l`.`full_name` AS `full_name`, `f`.`faculty_code` AS `faculty_code`, `p`.`pub_year` AS `pub_year`, count(`p`.`publication_id`) AS `total_pubs`, sum(`p`.`quartile` = 'Q1') AS `q1_count`, sum(`p`.`quartile` = 'Q2') AS `q2_count`, sum(`p`.`indexing_type` like '%Scopus%') AS `scopus_count`, sum(`p`.`indexing_type` like '%WoS%') AS `wos_count` FROM (((`tbl_publication` `p` join `tbl_research_data` `rd` on(`p`.`data_id` = `rd`.`data_id`)) join `tbl_lecturer` `l` on(`rd`.`lecturer_id` = `l`.`lecturer_id`)) join `tbl_faculty` `f` on(`l`.`faculty_id` = `f`.`faculty_id`)) WHERE `rd`.`status` = 'Approved' AND `rd`.`is_deleted` = 0 GROUP BY `l`.`lecturer_id`, `p`.`pub_year` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_admin`
--
ALTER TABLE `tbl_admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD KEY `fk_admin_user` (`user_id`);

--
-- Indexes for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_log_user` (`user_id`),
  ADD KEY `idx_log_date` (`logged_at`);

--
-- Indexes for table `tbl_award`
--
ALTER TABLE `tbl_award`
  ADD PRIMARY KEY (`award_id`),
  ADD KEY `fk_award_lec` (`lecturer_id`),
  ADD KEY `idx_award_year` (`award_year`);

--
-- Indexes for table `tbl_faculty`
--
ALTER TABLE `tbl_faculty`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `faculty_code` (`faculty_code`);

--
-- Indexes for table `tbl_faculty_transfer`
--
ALTER TABLE `tbl_faculty_transfer`
  ADD PRIMARY KEY (`transfer_id`),
  ADD KEY `idx_ft_lecturer` (`lecturer_id`),
  ADD KEY `idx_ft_to` (`to_faculty_id`);

--
-- Indexes for table `tbl_grant`
--
ALTER TABLE `tbl_grant`
  ADD PRIMARY KEY (`grant_id`),
  ADD KEY `fk_grant_data` (`data_id`),
  ADD KEY `idx_grant_status` (`status`),
  ADD KEY `idx_grant_year` (`start_date`);

--
-- Indexes for table `tbl_hindex`
--
ALTER TABLE `tbl_hindex`
  ADD PRIMARY KEY (`hindex_id`),
  ADD UNIQUE KEY `uq_hindex_year_lecturer` (`record_year`,`data_id`),
  ADD KEY `fk_hi_data` (`data_id`),
  ADD KEY `idx_hindex_year` (`record_year`);

--
-- Indexes for table `tbl_ip_record`
--
ALTER TABLE `tbl_ip_record`
  ADD PRIMARY KEY (`ip_id`),
  ADD KEY `fk_ip_data` (`data_id`);

--
-- Indexes for table `tbl_kpi_target`
--
ALTER TABLE `tbl_kpi_target`
  ADD PRIMARY KEY (`kpi_id`),
  ADD UNIQUE KEY `uq_kpi` (`year`,`faculty_id`,`metric`),
  ADD KEY `fk_kpi_faculty` (`faculty_id`);

--
-- Indexes for table `tbl_kpi_task`
--
ALTER TABLE `tbl_kpi_task`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `idx_task_lecturer` (`lecturer_id`),
  ADD KEY `idx_task_tdpp` (`tdpp_id`),
  ADD KEY `idx_task_status` (`status`);

--
-- Indexes for table `tbl_lecturer`
--
ALTER TABLE `tbl_lecturer`
  ADD PRIMARY KEY (`lecturer_id`),
  ADD UNIQUE KEY `staff_no` (`staff_no`),
  ADD KEY `fk_lec_user` (`user_id`),
  ADD KEY `idx_staff_no` (`staff_no`),
  ADD KEY `idx_faculty_id` (`faculty_id`),
  ADD KEY `fk_lec_rgroup` (`research_group_id`);

--
-- Indexes for table `tbl_login_attempt`
--
ALTER TABLE `tbl_login_attempt`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`);

--
-- Indexes for table `tbl_notification`
--
ALTER TABLE `tbl_notification`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_read` (`is_read`);

--
-- Indexes for table `tbl_password_reset`
--
ALTER TABLE `tbl_password_reset`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `idx_email_state` (`email`,`used`,`expires_at`);

--
-- Indexes for table `tbl_publication`
--
ALTER TABLE `tbl_publication`
  ADD PRIMARY KEY (`publication_id`),
  ADD KEY `fk_pub_data` (`data_id`),
  ADD KEY `idx_pub_year` (`pub_year`),
  ADD KEY `idx_quartile` (`quartile`);

--
-- Indexes for table `tbl_report`
--
ALTER TABLE `tbl_report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_report_admin` (`admin_id`),
  ADD KEY `idx_report_year` (`report_year`);

--
-- Indexes for table `tbl_research_data`
--
ALTER TABLE `tbl_research_data`
  ADD PRIMARY KEY (`data_id`),
  ADD KEY `fk_rd_admin` (`admin_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_lecturer_rd` (`lecturer_id`);

--
-- Indexes for table `tbl_research_group`
--
ALTER TABLE `tbl_research_group`
  ADD PRIMARY KEY (`group_id`),
  ADD UNIQUE KEY `uq_group_name` (`group_name`),
  ADD KEY `fk_rg_faculty` (`faculty_id`);

--
-- Indexes for table `tbl_research_income`
--
ALTER TABLE `tbl_research_income`
  ADD PRIMARY KEY (`income_id`),
  ADD KEY `fk_inc_data` (`data_id`),
  ADD KEY `fk_inc_grant` (`related_grant_id`),
  ADD KEY `idx_income_year` (`year_received`);

--
-- Indexes for table `tbl_tdpp`
--
ALTER TABLE `tbl_tdpp`
  ADD PRIMARY KEY (`tdpp_id`),
  ADD KEY `idx_tdpp_user` (`user_id`),
  ADD KEY `idx_tdpp_faculty` (`faculty_id`);

--
-- Indexes for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_admin`
--
ALTER TABLE `tbl_admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `tbl_award`
--
ALTER TABLE `tbl_award`
  MODIFY `award_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `tbl_faculty`
--
ALTER TABLE `tbl_faculty`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_faculty_transfer`
--
ALTER TABLE `tbl_faculty_transfer`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_grant`
--
ALTER TABLE `tbl_grant`
  MODIFY `grant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `tbl_hindex`
--
ALTER TABLE `tbl_hindex`
  MODIFY `hindex_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `tbl_ip_record`
--
ALTER TABLE `tbl_ip_record`
  MODIFY `ip_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tbl_kpi_target`
--
ALTER TABLE `tbl_kpi_target`
  MODIFY `kpi_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_kpi_task`
--
ALTER TABLE `tbl_kpi_task`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tbl_lecturer`
--
ALTER TABLE `tbl_lecturer`
  MODIFY `lecturer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `tbl_login_attempt`
--
ALTER TABLE `tbl_login_attempt`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_notification`
--
ALTER TABLE `tbl_notification`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `tbl_password_reset`
--
ALTER TABLE `tbl_password_reset`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_publication`
--
ALTER TABLE `tbl_publication`
  MODIFY `publication_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `tbl_report`
--
ALTER TABLE `tbl_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `tbl_research_data`
--
ALTER TABLE `tbl_research_data`
  MODIFY `data_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=294;

--
-- AUTO_INCREMENT for table `tbl_research_group`
--
ALTER TABLE `tbl_research_group`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tbl_research_income`
--
ALTER TABLE `tbl_research_income`
  MODIFY `income_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `tbl_tdpp`
--
ALTER TABLE `tbl_tdpp`
  MODIFY `tdpp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_admin`
--
ALTER TABLE `tbl_admin`
  ADD CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`user_id`);

--
-- Constraints for table `tbl_award`
--
ALTER TABLE `tbl_award`
  ADD CONSTRAINT `fk_award_lec` FOREIGN KEY (`lecturer_id`) REFERENCES `tbl_lecturer` (`lecturer_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_grant`
--
ALTER TABLE `tbl_grant`
  ADD CONSTRAINT `fk_grant_data` FOREIGN KEY (`data_id`) REFERENCES `tbl_research_data` (`data_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_hindex`
--
ALTER TABLE `tbl_hindex`
  ADD CONSTRAINT `fk_hi_data` FOREIGN KEY (`data_id`) REFERENCES `tbl_research_data` (`data_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_ip_record`
--
ALTER TABLE `tbl_ip_record`
  ADD CONSTRAINT `fk_ip_data` FOREIGN KEY (`data_id`) REFERENCES `tbl_research_data` (`data_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_kpi_target`
--
ALTER TABLE `tbl_kpi_target`
  ADD CONSTRAINT `fk_kpi_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `tbl_faculty` (`faculty_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_kpi_task`
--
ALTER TABLE `tbl_kpi_task`
  ADD CONSTRAINT `fk_task_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `tbl_lecturer` (`lecturer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_task_tdpp` FOREIGN KEY (`tdpp_id`) REFERENCES `tbl_tdpp` (`tdpp_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_lecturer`
--
ALTER TABLE `tbl_lecturer`
  ADD CONSTRAINT `fk_lec_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `tbl_faculty` (`faculty_id`),
  ADD CONSTRAINT `fk_lec_rgroup` FOREIGN KEY (`research_group_id`) REFERENCES `tbl_research_group` (`group_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lec_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_notification`
--
ALTER TABLE `tbl_notification`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_publication`
--
ALTER TABLE `tbl_publication`
  ADD CONSTRAINT `fk_pub_data` FOREIGN KEY (`data_id`) REFERENCES `tbl_research_data` (`data_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_report`
--
ALTER TABLE `tbl_report`
  ADD CONSTRAINT `fk_report_admin` FOREIGN KEY (`admin_id`) REFERENCES `tbl_admin` (`admin_id`);

--
-- Constraints for table `tbl_research_data`
--
ALTER TABLE `tbl_research_data`
  ADD CONSTRAINT `fk_rd_admin` FOREIGN KEY (`admin_id`) REFERENCES `tbl_admin` (`admin_id`),
  ADD CONSTRAINT `fk_rd_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `tbl_lecturer` (`lecturer_id`);

--
-- Constraints for table `tbl_research_group`
--
ALTER TABLE `tbl_research_group`
  ADD CONSTRAINT `fk_rg_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `tbl_faculty` (`faculty_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_research_income`
--
ALTER TABLE `tbl_research_income`
  ADD CONSTRAINT `fk_inc_data` FOREIGN KEY (`data_id`) REFERENCES `tbl_research_data` (`data_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inc_grant` FOREIGN KEY (`related_grant_id`) REFERENCES `tbl_grant` (`grant_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_tdpp`
--
ALTER TABLE `tbl_tdpp`
  ADD CONSTRAINT `fk_tdpp_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `tbl_faculty` (`faculty_id`),
  ADD CONSTRAINT `fk_tdpp_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
