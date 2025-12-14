-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 14, 2025 at 09:21 AM
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
-- Database: `classroom_voting`
--

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `sp_get_deleted_records` (IN `p_table_name` VARCHAR(100))   BEGIN
    CASE p_table_name
        WHEN 'users' THEN
            SELECT * FROM `users` WHERE `deleted_at` IS NOT NULL ORDER BY `deleted_at` DESC;
        WHEN 'candidates' THEN
            SELECT * FROM `candidates` WHERE `deleted_at` IS NOT NULL ORDER BY `deleted_at` DESC;
        WHEN 'voting_sessions' THEN
            SELECT * FROM `voting_sessions` WHERE `deleted_at` IS NOT NULL ORDER BY `deleted_at` DESC;
        WHEN 'votes' THEN
            SELECT * FROM `votes` WHERE `deleted_at` IS NOT NULL ORDER BY `deleted_at` DESC;
        WHEN 'winners' THEN
            SELECT * FROM `winners` WHERE `deleted_at` IS NOT NULL ORDER BY `deleted_at` DESC;
        WHEN 'positions' THEN
            SELECT * FROM `positions` WHERE `deleted_at` IS NOT NULL ORDER BY `deleted_at` DESC;
        WHEN 'student_groups' THEN
            SELECT * FROM `student_groups` WHERE `deleted_at` IS NOT NULL ORDER BY `deleted_at` DESC;
        ELSE
            SELECT 'Invalid table name' AS error;
    END CASE;
END$$

CREATE PROCEDURE `sp_restore_user` (IN `p_user_id` INT)   BEGIN
    UPDATE `users`
    SET `deleted_at` = NULL,
        `deleted_by` = NULL,
        `is_active` = 1
    WHERE `id` = p_user_id;
END$$

CREATE PROCEDURE `sp_soft_delete_candidate` (IN `p_candidate_id` INT, IN `p_deleted_by` INT)   BEGIN
    UPDATE `candidates`
    SET `deleted_at` = CURRENT_TIMESTAMP,
        `deleted_by` = p_deleted_by
    WHERE `id` = p_candidate_id
      AND `deleted_at` IS NULL;
END$$

CREATE PROCEDURE `sp_soft_delete_group` (IN `p_group_id` INT, IN `p_deleted_by` INT)   BEGIN
    -- Delete group members
    UPDATE `student_group_members`
    SET `deleted_at` = CURRENT_TIMESTAMP,
        `deleted_by` = p_deleted_by
    WHERE `group_id` = p_group_id
      AND `deleted_at` IS NULL;
    
    -- Delete the group
    UPDATE `student_groups`
    SET `deleted_at` = CURRENT_TIMESTAMP,
        `deleted_by` = p_deleted_by
    WHERE `id` = p_group_id
      AND `deleted_at` IS NULL;
END$$

CREATE PROCEDURE `sp_soft_delete_position` (IN `p_position_id` INT, IN `p_deleted_by` INT)   BEGIN
    UPDATE `positions`
    SET `deleted_at` = CURRENT_TIMESTAMP,
        `deleted_by` = p_deleted_by
    WHERE `id` = p_position_id
      AND `deleted_at` IS NULL;
END$$

CREATE PROCEDURE `sp_soft_delete_session` (IN `p_session_id` INT, IN `p_deleted_by` INT)   BEGIN
    -- Delete votes in this session
    UPDATE `votes`
    SET `deleted_at` = CURRENT_TIMESTAMP,
        `deleted_by` = p_deleted_by
    WHERE `session_id` = p_session_id
      AND `deleted_at` IS NULL;
    
    -- Delete winners in this session
    UPDATE `winners`
    SET `deleted_at` = CURRENT_TIMESTAMP,
        `deleted_by` = p_deleted_by
    WHERE `session_id` = p_session_id
      AND `deleted_at` IS NULL;
    
    -- Delete the session
    UPDATE `voting_sessions`
    SET `deleted_at` = CURRENT_TIMESTAMP,
        `deleted_by` = p_deleted_by
    WHERE `id` = p_session_id
      AND `deleted_at` IS NULL;
END$$

CREATE PROCEDURE `sp_soft_delete_user` (IN `p_user_id` INT, IN `p_deleted_by` INT)   BEGIN
    UPDATE `users`
    SET `deleted_at` = CURRENT_TIMESTAMP,
        `deleted_by` = p_deleted_by,
        `is_active` = 0
    WHERE `id` = p_user_id
      AND `deleted_at` IS NULL;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `snapshot_full_name` varchar(302) DEFAULT NULL,
  `snapshot_student_id` varchar(50) DEFAULT NULL,
  `snapshot_email` varchar(100) DEFAULT NULL,
  `position_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `status` enum('nominated','elected','lost','ineligible') DEFAULT 'nominated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `user_id`, `snapshot_full_name`, `snapshot_student_id`, `snapshot_email`, `position_id`, `session_id`, `status`, `created_at`, `deleted_at`, `deleted_by`) VALUES
(86, 3, 'Jane Smith', 'STU002', 'jane@student.edu', 1, 51, 'nominated', '2025-12-14 04:15:58', '2025-12-14 04:16:04', 1);

--
-- Triggers `candidates`
--
DELIMITER $$
CREATE TRIGGER `trg_candidate_snapshot` BEFORE INSERT ON `candidates` FOR EACH ROW BEGIN
    DECLARE v_first_name VARCHAR(100);
    DECLARE v_middle_name VARCHAR(100);
    DECLARE v_last_name VARCHAR(100);
    DECLARE v_student_id VARCHAR(50);
    DECLARE v_email VARCHAR(100);
    
    SELECT first_name, middle_name, last_name, student_id, email
    INTO v_first_name, v_middle_name, v_last_name, v_student_id, v_email
    FROM users
    WHERE id = NEW.user_id;
    
    SET NEW.snapshot_full_name = TRIM(CONCAT_WS(' ', v_first_name, v_middle_name, v_last_name));
    SET NEW.snapshot_student_id = v_student_id;
    SET NEW.snapshot_email = v_email;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_logs`
--

CREATE TABLE `email_verification_logs` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('vote','milestone','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`, `deleted_at`, `deleted_by`) VALUES
(1, 1, 'New Vote Cast', 'Rose-Ann  Alberto has voted for Vice President', 'vote', 1, '2025-11-16 20:33:51', NULL, NULL),
(2, 1, 'New Vote Cast', 'Rose-Ann  Alberto has voted for Vice President', 'vote', 1, '2025-11-17 02:39:54', NULL, NULL),
(3, 1, 'New Vote Cast', 'Rhaiza  Li has voted for Secretary', 'vote', 1, '2025-11-17 03:46:38', NULL, NULL),
(4, 1, 'New Vote Cast', 'Rose-Ann  Alberto has voted for President', 'vote', 1, '2025-11-23 13:38:36', NULL, NULL),
(5, 1, 'New Vote Cast', 'A vote has been recorded for Rhaiza Alberto in President', 'vote', 1, '2025-12-14 02:39:10', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_name` varchar(50) NOT NULL,
  `position_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_name`, `position_order`, `created_at`, `deleted_at`, `deleted_by`) VALUES
(1, 'President', 1, '2025-10-11 23:17:55', NULL, NULL),
(2, 'Vice President', 2, '2025-10-11 23:17:55', NULL, NULL),
(3, 'Secretary', 3, '2025-10-11 23:17:55', NULL, NULL),
(4, 'Treasurer', 4, '2025-10-11 23:17:55', NULL, NULL),
(8, 'Auditor', 5, '2025-10-26 13:19:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_groups`
--

CREATE TABLE `student_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_groups`
--

INSERT INTO `student_groups` (`id`, `group_name`, `description`, `created_at`, `deleted_at`, `deleted_by`) VALUES
(1, 'ACT-AD', '', '2025-11-30 00:31:38', '2025-12-13 19:53:11', 1),
(2, 'BSCS', '', '2025-12-13 17:27:03', '2025-12-14 01:27:10', 1),
(3, 'ACT-AD 2', '', '2025-12-14 01:27:15', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_group_members`
--

CREATE TABLE `student_group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_group_members`
--

INSERT INTO `student_group_members` (`id`, `group_id`, `user_id`, `added_at`, `deleted_at`, `deleted_by`) VALUES
(1, 1, 22, '2025-11-30 00:44:35', '2025-12-13 19:53:11', 1),
(2, 1, 13, '2025-12-13 17:45:25', '2025-12-13 19:53:11', 1),
(3, 3, 30, '2025-12-14 01:27:20', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `deactivated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `registration_method` enum('admin_added','self_registered') DEFAULT 'admin_added',
  `password` varchar(255) NOT NULL,
  `role` enum('student','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `email`, `email_verified`, `is_active`, `deactivated_at`, `deactivated_by`, `deleted_at`, `deleted_by`, `verification_token`, `token_expires_at`, `registration_method`, `password`, `role`, `created_at`) VALUES
(1, 'ADMIN001', NULL, NULL, 'System Administrator', 'admin@school.edu', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$wX.QtT2Vl1Y6NhYK.y52q.u1u0I2pcvyWc0UFWS05M/hC6knTQJ7.', 'admin', '2025-10-11 23:17:55'),
(2, 'STU001', NULL, NULL, 'John Doe', 'john@student.edu', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$tUolvebl62KJzhapNSI1Weznqog7Zfv0DSxHX.3mJJ1gx9es3bcle', 'student', '2025-10-11 23:17:55'),
(3, 'STU002', NULL, NULL, 'Jane Smith', 'jane@student.edu', 1, 0, '2025-12-13 19:04:20', 1, '2025-12-13 19:04:20', 1, NULL, NULL, 'admin_added', '$2y$10$mkTMaNoqOnkOz..XXWRaCO7eRxzSGAa2KrWiRDuueCjcep2u/839.', 'student', '2025-10-11 23:17:55'),
(4, 'STU003', NULL, NULL, 'Mike Johnson', 'mike@student.edu', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$XsbiOwCfk8FOSfCuOw5EFuVyKq4vn8s89.NJ1LaJSkGc730fZplWm', 'student', '2025-10-11 23:17:55'),
(5, 'STU004', NULL, NULL, 'Sarah Williams', 'sarah@student.edu', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$fQzx5Mp0OOu8o.E8laooM.vlvHSwNF7OxQSWqN4Yih3qwvFy..9ou', 'student', '2025-10-11 23:17:55'),
(6, 'STU005', 'David', '', 'Brown', 'david@student.edu', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$v1rWzH2pOtf9AbgVRqkLNep5FSHOy9jlL9ydX7Xg/S8EdBpyTiGXC', 'student', '2025-10-11 23:17:55'),
(8, 'STU006', NULL, NULL, 'JOHN', 'JOHN@GMAIL.COM', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$RpXfLbn3kP90dxnjticJ6u8B9LrB0xekNHx/rI3bxH3PbszL12Ehi', 'student', '2025-10-26 17:59:30'),
(9, 'STU726', NULL, NULL, 'Elena Alberto', 'alberto@gmail.com', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$lke9EpQbqHhIZAaymiyUa.hiNtvXeLZaB/YARm6jiRrCw/8a1h2dK', 'student', '2025-10-27 02:24:10'),
(13, 'STU777', 'Anna', '', 'Bell', 'anna@gmail.com', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$/PytbS4wxQXVOxTtD3BWneIagdpPiI1Uw5Mzj8CwfdeCXuLliwFvK', 'student', '2025-10-27 07:17:05'),
(14, 'STU124', 'Rose', '', 'Alberto', 'rose@gmail.com', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$nBnbZcvcVDbJvjSmCBdNmO0gk9DEjwGJ.m43yC.tsH..RhZ93nVUW', 'student', '2025-10-27 23:18:00'),
(15, 'STU111', 'Elsa', '', 'Frozen', 'else@gmail.com', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$RcWbIfDmJhZ1U7p6WlGYY.GLoWrvccRGJbFX9vwXy6dbidF5zQt.u', 'student', '2025-10-28 02:39:05'),
(16, 'STU000', 'Mario', '', 'Li', 'mario@gmail.com', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$lMc6kc6Ob3.MJsaY40bBAeKeDKqBADA/WJZSlSjCzDUfVNVzW.nZG', 'student', '2025-11-04 01:22:37'),
(22, 'STU888', 'Rose-Ann', '', 'Alberto', 'rhaizaalberto931@gmail.com', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$8teWFQd3k2jhG4ZSRC5v..ZUotAYz6slOFw/w.lBgJ/zfHRMZ4pf2', 'student', '2025-11-16 20:18:09'),
(23, 'STU234', 'Rose-Ann', '', 'Alberto', 'albertoroseann@gmail.com', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$.jFtFiQ7eOtXZlLMgqe22OQFU./jwgorCKRRdHmOCgD4aNMxeFuhG', 'student', '2025-11-17 02:35:15'),
(25, 'STU657', 'Jaymie', '', 'Tuble', 'jaymiemargaret21@gmail.com', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'admin_added', '$2y$10$C3wzyES7bzBek2uoG0GLMebxXVlIgaXowI5RiECkzFvoJrOWHbwnu', 'student', '2025-11-17 03:13:11'),
(30, '202403480', 'Rhaiza', '', 'Alberto', 'ae202403480@wmsu.edu.ph', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'self_registered', '$2y$10$tQYX4CJUhCz/Kh/KRH/qYeKQGi6BPlhMquNgnlmjHLX47CNdHP9UG', 'student', '2025-12-13 04:05:05'),
(31, '2024-04182', 'Floralyn', '', 'Bernardo', 'ae202404182@wmsu.edu.ph', 0, 1, NULL, NULL, NULL, NULL, '59ea7cade4927e6ab7ce5e684b6d2d2bfdd13b1e0f94680ad5ce3dddfac33e83', '2025-12-14 18:04:56', 'self_registered', '$2y$10$c98QETcBNGc57sPEzJNyNutiDabk0LOq58aMrJnrpQUzHYkKTwCXi', 'student', '2025-12-13 17:04:56');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `voter_id` int(11) NOT NULL,
  `snapshot_voter_name` varchar(302) DEFAULT NULL,
  `snapshot_voter_student_id` varchar(50) DEFAULT NULL,
  `candidate_id` int(11) DEFAULT NULL,
  `snapshot_candidate_name` varchar(302) DEFAULT NULL,
  `snapshot_candidate_student_id` varchar(50) DEFAULT NULL,
  `position_id` int(11) NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `session_id`, `voter_id`, `snapshot_voter_name`, `snapshot_voter_student_id`, `candidate_id`, `snapshot_candidate_name`, `snapshot_candidate_student_id`, `position_id`, `voted_at`, `deleted_at`, `deleted_by`) VALUES
(85, 43, 9, 'Elena Alberto', 'STU726', NULL, 'Rose  Alberto', 'STU124', 1, '2025-11-09 14:02:35', NULL, NULL),
(86, 44, 9, 'Elena Alberto', 'STU726', NULL, 'Lex  Al', 'STU222', 1, '2025-11-10 06:01:54', NULL, NULL),
(87, 45, 22, 'Rose-Ann  Alberto', 'STU888', NULL, 'Lex  Al', 'STU222', 1, '2025-11-16 20:25:45', NULL, NULL),
(88, 45, 22, 'Rose-Ann  Alberto', 'STU888', NULL, 'Rose-Ann  Alberto', 'STU888', 2, '2025-11-16 20:33:47', NULL, NULL),
(89, 45, 23, 'Rose-Ann  Alberto', 'STU234', NULL, 'Rose-Ann  Alberto', 'STU888', 2, '2025-11-17 02:39:48', NULL, NULL),
(91, 46, 23, 'Rose-Ann  Alberto', 'STU234', NULL, 'Rose-Ann  Alberto', 'STU888', 1, '2025-11-23 13:38:29', NULL, NULL),
(92, 50, 30, 'Rhaiza  Alberto', '202403480', NULL, 'Rose-Ann  Alberto', 'STU888', 1, '2025-12-14 02:39:03', NULL, NULL);

--
-- Triggers `votes`
--
DELIMITER $$
CREATE TRIGGER `trg_vote_snapshot` BEFORE INSERT ON `votes` FOR EACH ROW BEGIN
    DECLARE v_first_name VARCHAR(100);
    DECLARE v_middle_name VARCHAR(100);
    DECLARE v_last_name VARCHAR(100);
    DECLARE v_student_id VARCHAR(50);
    DECLARE v_candidate_first VARCHAR(100);
    DECLARE v_candidate_middle VARCHAR(100);
    DECLARE v_candidate_last VARCHAR(100);
    DECLARE v_candidate_student_id VARCHAR(50);
    
    -- Get voter data
    SELECT first_name, middle_name, last_name, student_id
    INTO v_first_name, v_middle_name, v_last_name, v_student_id
    FROM users
    WHERE id = NEW.voter_id;
    
    -- Store voter snapshot
    SET NEW.snapshot_voter_name = TRIM(CONCAT_WS(' ', v_first_name, v_middle_name, v_last_name));
    SET NEW.snapshot_voter_student_id = v_student_id;
    
    -- Get candidate data (if candidate_id is not NULL)
    IF NEW.candidate_id IS NOT NULL THEN
        SELECT u.first_name, u.middle_name, u.last_name, u.student_id
        INTO v_candidate_first, v_candidate_middle, v_candidate_last, v_candidate_student_id
        FROM candidates c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = NEW.candidate_id;
        
        -- Store candidate snapshot
        SET NEW.snapshot_candidate_name = TRIM(CONCAT_WS(' ', v_candidate_first, v_candidate_middle, v_candidate_last));
        SET NEW.snapshot_candidate_student_id = v_candidate_student_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `vote_logs`
--

CREATE TABLE `vote_logs` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `candidate_id` int(11) DEFAULT NULL,
  `vote_count` int(11) DEFAULT 0,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voting_sessions`
--

CREATE TABLE `voting_sessions` (
  `id` int(11) NOT NULL,
  `session_name` varchar(100) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending','active','paused','locked','completed') DEFAULT 'pending',
  `current_position_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `locked_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voting_sessions`
--

INSERT INTO `voting_sessions` (`id`, `session_name`, `group_id`, `description`, `start_date`, `end_date`, `status`, `current_position_id`, `created_by`, `created_at`, `locked_at`, `deleted_at`, `deleted_by`) VALUES
(43, 'Election 2025', NULL, NULL, NULL, NULL, 'locked', NULL, 1, '2025-11-09 14:02:16', NULL, NULL, NULL),
(44, 'Classroom 2025', NULL, NULL, NULL, NULL, 'locked', NULL, 1, '2025-11-10 06:01:09', NULL, NULL, NULL),
(45, 'Classroom 2025', NULL, NULL, NULL, NULL, 'locked', NULL, 1, '2025-11-10 06:19:32', NULL, NULL, NULL),
(46, 'Classroom 2014', NULL, '', NULL, NULL, 'locked', NULL, 1, '2025-11-23 13:36:54', NULL, NULL, NULL),
(47, 'Election 2025', NULL, '', NULL, NULL, 'locked', NULL, 1, '2025-11-23 14:15:41', NULL, '2025-12-13 20:10:35', 1),
(49, 'Classroom 2025', 1, '', NULL, NULL, 'locked', NULL, 1, '2025-12-14 01:57:24', '2025-12-14 02:09:03', NULL, NULL),
(50, 'Election 2025', 3, '', NULL, NULL, 'locked', NULL, 1, '2025-12-14 02:09:17', '2025-12-14 02:40:11', NULL, NULL),
(51, 'Classroom 2024', 3, '', NULL, NULL, 'locked', NULL, 1, '2025-12-14 04:15:32', '2025-12-14 04:18:58', NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_candidates_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_candidates_detailed` (
`id` int(11)
,`user_id` int(11)
,`position_id` int(11)
,`status` enum('nominated','elected','lost','ineligible')
,`created_at` timestamp
,`deleted_at` timestamp
,`candidate_name` varchar(302)
,`student_id` varchar(50)
,`email` varchar(100)
,`position_name` varchar(50)
,`position_order` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_users_with_fullname`
-- (See below for the actual view)
--
CREATE TABLE `v_users_with_fullname` (
`id` int(11)
,`student_id` varchar(50)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`full_name` varchar(302)
,`email` varchar(100)
,`password` varchar(255)
,`role` enum('student','admin')
,`created_at` timestamp
,`deleted_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_votes_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_votes_detailed` (
`id` int(11)
,`session_id` int(11)
,`voter_id` int(11)
,`candidate_id` int(11)
,`position_id` int(11)
,`voted_at` timestamp
,`deleted_at` timestamp
,`candidate_name` varchar(302)
,`candidate_student_id` varchar(50)
,`voter_name` varchar(302)
,`voter_student_id` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_winners_with_counts`
-- (See below for the actual view)
--
CREATE TABLE `v_winners_with_counts` (
`id` int(11)
,`session_id` int(11)
,`position_id` int(11)
,`user_id` int(11)
,`elected_at` timestamp
,`deleted_at` timestamp
,`position_name` varchar(50)
,`winner_name` varchar(302)
,`student_id` varchar(50)
,`vote_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `winners`
--

CREATE TABLE `winners` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `snapshot_winner_name` varchar(302) DEFAULT NULL,
  `snapshot_student_id` varchar(50) DEFAULT NULL,
  `elected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `winners`
--

INSERT INTO `winners` (`id`, `session_id`, `position_id`, `user_id`, `snapshot_winner_name`, `snapshot_student_id`, `elected_at`, `deleted_at`, `deleted_by`) VALUES
(1, 25, 1, 13, 'Anna  Bell', 'STU777', '2025-10-27 22:50:24', NULL, NULL),
(2, 26, 1, 13, 'Anna  Bell', 'STU777', '2025-10-27 23:16:51', NULL, NULL),
(3, 28, 1, 13, 'Anna  Bell', 'STU777', '2025-10-27 23:39:24', NULL, NULL),
(5, 29, 1, 13, 'Anna  Bell', 'STU777', '2025-10-28 00:11:39', NULL, NULL),
(6, 30, 1, 6, 'David Brown', 'STU005', '2025-10-28 02:50:47', NULL, NULL),
(7, 31, 1, 13, 'Anna  Bell', 'STU777', '2025-10-28 03:18:19', NULL, NULL),
(8, 31, 2, 6, 'David Brown', 'STU005', '2025-10-28 03:36:02', NULL, NULL),
(9, 33, 1, 13, 'Anna  Bell', 'STU777', '2025-10-28 05:43:51', NULL, NULL),
(10, 34, 1, 13, 'Anna  Bell', 'STU777', '2025-11-04 01:13:57', NULL, NULL),
(11, 35, 1, 13, 'Anna  Bell', 'STU777', '2025-11-04 01:34:38', NULL, NULL),
(18, 43, 1, 14, 'Rose  Alberto', 'STU124', '2025-11-09 14:02:40', NULL, NULL),
(19, 44, 1, NULL, 'Lex  Al', 'STU222', '2025-11-10 06:02:08', NULL, NULL),
(20, 45, 1, NULL, 'Lex  Al', 'STU222', '2025-11-16 20:32:37', NULL, NULL),
(21, 45, 2, 22, 'Rose-Ann  Alberto', 'STU888', '2025-11-17 03:46:19', NULL, NULL),
(23, 46, 1, 22, 'Rose-Ann  Alberto', 'STU888', '2025-11-23 13:38:49', NULL, NULL),
(24, 50, 1, 22, 'Rose-Ann  Alberto', 'STU888', '2025-12-14 02:40:01', NULL, NULL);

--
-- Triggers `winners`
--
DELIMITER $$
CREATE TRIGGER `trg_winner_snapshot` BEFORE INSERT ON `winners` FOR EACH ROW BEGIN
    DECLARE v_first_name VARCHAR(100);
    DECLARE v_middle_name VARCHAR(100);
    DECLARE v_last_name VARCHAR(100);
    DECLARE v_student_id VARCHAR(50);
    
    SELECT first_name, middle_name, last_name, student_id
    INTO v_first_name, v_middle_name, v_last_name, v_student_id
    FROM users
    WHERE id = NEW.user_id;
    
    SET NEW.snapshot_winner_name = TRIM(CONCAT_WS(' ', v_first_name, v_middle_name, v_last_name));
    SET NEW.snapshot_student_id = v_student_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure for view `v_candidates_detailed`
--
DROP TABLE IF EXISTS `v_candidates_detailed`;

CREATE VIEW `v_candidates_detailed`  AS SELECT `c`.`id` AS `id`, `c`.`user_id` AS `user_id`, `c`.`position_id` AS `position_id`, `c`.`status` AS `status`, `c`.`created_at` AS `created_at`, `c`.`deleted_at` AS `deleted_at`, trim(concat_ws(' ',`u`.`first_name`,`u`.`middle_name`,`u`.`last_name`)) AS `candidate_name`, `u`.`student_id` AS `student_id`, `u`.`email` AS `email`, `p`.`position_name` AS `position_name`, `p`.`position_order` AS `position_order` FROM ((`candidates` `c` join `users` `u` on(`c`.`user_id` = `u`.`id`)) join `positions` `p` on(`c`.`position_id` = `p`.`id`)) WHERE `c`.`deleted_at` is null AND `u`.`deleted_at` is null AND `p`.`deleted_at` is null ;

-- --------------------------------------------------------

--
-- Structure for view `v_users_with_fullname`
--
DROP TABLE IF EXISTS `v_users_with_fullname`;

CREATE  VIEW `v_users_with_fullname`  AS SELECT `users`.`id` AS `id`, `users`.`student_id` AS `student_id`, `users`.`first_name` AS `first_name`, `users`.`middle_name` AS `middle_name`, `users`.`last_name` AS `last_name`, trim(concat_ws(' ',`users`.`first_name`,`users`.`middle_name`,`users`.`last_name`)) AS `full_name`, `users`.`email` AS `email`, `users`.`password` AS `password`, `users`.`role` AS `role`, `users`.`created_at` AS `created_at`, `users`.`deleted_at` AS `deleted_at` FROM `users` WHERE `users`.`deleted_at` is null ;

-- --------------------------------------------------------

--
-- Structure for view `v_votes_detailed`
--
DROP TABLE IF EXISTS `v_votes_detailed`;

CREATE VIEW `v_votes_detailed`  AS SELECT `v`.`id` AS `id`, `v`.`session_id` AS `session_id`, `v`.`voter_id` AS `voter_id`, `v`.`candidate_id` AS `candidate_id`, `v`.`position_id` AS `position_id`, `v`.`voted_at` AS `voted_at`, `v`.`deleted_at` AS `deleted_at`, trim(concat_ws(' ',`u`.`first_name`,`u`.`middle_name`,`u`.`last_name`)) AS `candidate_name`, `u`.`student_id` AS `candidate_student_id`, trim(concat_ws(' ',`voter`.`first_name`,`voter`.`middle_name`,`voter`.`last_name`)) AS `voter_name`, `voter`.`student_id` AS `voter_student_id` FROM (((`votes` `v` left join `candidates` `c` on(`v`.`candidate_id` = `c`.`id`)) left join `users` `u` on(`c`.`user_id` = `u`.`id`)) left join `users` `voter` on(`v`.`voter_id` = `voter`.`id`)) WHERE `v`.`deleted_at` is null ;

-- --------------------------------------------------------

--
-- Structure for view `v_winners_with_counts`
--
DROP TABLE IF EXISTS `v_winners_with_counts`;

CREATE  VIEW `v_winners_with_counts`  AS SELECT `w`.`id` AS `id`, `w`.`session_id` AS `session_id`, `w`.`position_id` AS `position_id`, `w`.`user_id` AS `user_id`, `w`.`elected_at` AS `elected_at`, `w`.`deleted_at` AS `deleted_at`, `p`.`position_name` AS `position_name`, trim(concat_ws(' ',`u`.`first_name`,`u`.`middle_name`,`u`.`last_name`)) AS `winner_name`, `u`.`student_id` AS `student_id`, (select count(0) from (`votes` `v` join `candidates` `c` on(`v`.`candidate_id` = `c`.`id`)) where `v`.`session_id` = `w`.`session_id` and `v`.`position_id` = `w`.`position_id` and `c`.`user_id` = `w`.`user_id` and `v`.`deleted_at` is null and `c`.`deleted_at` is null) AS `vote_count` FROM ((`winners` `w` left join `users` `u` on(`w`.`user_id` = `u`.`id`)) left join `positions` `p` on(`w`.`position_id` = `p`.`id`)) WHERE `w`.`deleted_at` is null AND (`u`.`deleted_at` is null OR `u`.`id` is null) AND (`p`.`deleted_at` is null OR `p`.`id` is null) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_candidate` (`user_id`,`position_id`),
  ADD KEY `idx_candidate_status` (`status`),
  ADD KEY `idx_candidate_user_position` (`user_id`,`position_id`),
  ADD KEY `candidates_ibfk_2` (`position_id`),
  ADD KEY `idx_candidates_snapshot` (`snapshot_full_name`,`snapshot_student_id`),
  ADD KEY `idx_candidates_deleted` (`deleted_at`),
  ADD KEY `fk_candidates_deleted_by` (`deleted_by`),
  ADD KEY `fk_candidates_session` (`session_id`);

--
-- Indexes for table `email_verification_logs`
--
ALTER TABLE `email_verification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email_logs_deleted` (`deleted_at`),
  ADD KEY `fk_email_logs_deleted_by` (`deleted_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_notifications_deleted` (`deleted_at`),
  ADD KEY `fk_notifications_deleted_by` (`deleted_by`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `position_order` (`position_order`),
  ADD KEY `idx_positions_deleted` (`deleted_at`),
  ADD KEY `fk_positions_deleted_by` (`deleted_by`);

--
-- Indexes for table `student_groups`
--
ALTER TABLE `student_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_groups_deleted` (`deleted_at`),
  ADD KEY `fk_groups_deleted_by` (`deleted_by`);

--
-- Indexes for table `student_group_members`
--
ALTER TABLE `student_group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_group_members_deleted` (`deleted_at`),
  ADD KEY `fk_group_members_deleted_by` (`deleted_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_name` (`last_name`,`first_name`),
  ADD KEY `idx_verification_token` (`verification_token`),
  ADD KEY `idx_email_verified` (`email_verified`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `fk_users_deleted_by` (`deleted_by`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vote` (`session_id`,`voter_id`,`position_id`),
  ADD KEY `idx_votes_session_position` (`session_id`,`position_id`),
  ADD KEY `idx_votes_candidate` (`candidate_id`,`session_id`),
  ADD KEY `fk_votes_voter` (`voter_id`),
  ADD KEY `fk_votes_position` (`position_id`),
  ADD KEY `idx_votes_deleted` (`deleted_at`),
  ADD KEY `fk_votes_deleted_by` (`deleted_by`);

--
-- Indexes for table `vote_logs`
--
ALTER TABLE `vote_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vote_logs_ibfk_1` (`session_id`),
  ADD KEY `vote_logs_ibfk_2` (`position_id`),
  ADD KEY `vote_logs_ibfk_3` (`candidate_id`),
  ADD KEY `idx_vote_logs_deleted` (`deleted_at`),
  ADD KEY `fk_vote_logs_deleted_by` (`deleted_by`);

--
-- Indexes for table `voting_sessions`
--
ALTER TABLE `voting_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `current_position_id` (`current_position_id`),
  ADD KEY `idx_session_group` (`group_id`),
  ADD KEY `idx_session_status` (`status`),
  ADD KEY `idx_sessions_deleted` (`deleted_at`),
  ADD KEY `fk_sessions_deleted_by` (`deleted_by`);

--
-- Indexes for table `winners`
--
ALTER TABLE `winners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_winner` (`session_id`,`position_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_winners_deleted` (`deleted_at`),
  ADD KEY `fk_winners_deleted_by` (`deleted_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `email_verification_logs`
--
ALTER TABLE `email_verification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `student_groups`
--
ALTER TABLE `student_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_group_members`
--
ALTER TABLE `student_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `vote_logs`
--
ALTER TABLE `vote_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voting_sessions`
--
ALTER TABLE `voting_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `winners`
--
ALTER TABLE `winners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `candidates_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_candidates_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_candidates_session` FOREIGN KEY (`session_id`) REFERENCES `voting_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verification_logs`
--
ALTER TABLE `email_verification_logs`
  ADD CONSTRAINT `fk_email_logs_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `fk_positions_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_groups`
--
ALTER TABLE `student_groups`
  ADD CONSTRAINT `fk_groups_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_group_members`
--
ALTER TABLE `student_group_members`
  ADD CONSTRAINT `fk_group_members_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `fk_votes_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_votes_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_votes_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_votes_session` FOREIGN KEY (`session_id`) REFERENCES `voting_sessions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_votes_voter` FOREIGN KEY (`voter_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `votes_candidate_fk` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `vote_logs`
--
ALTER TABLE `vote_logs`
  ADD CONSTRAINT `fk_vote_logs_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vote_logs_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `voting_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vote_logs_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `vote_logs_ibfk_3` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `voting_sessions`
--
ALTER TABLE `voting_sessions`
  ADD CONSTRAINT `fk_sessions_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `voting_sessions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `voting_sessions_ibfk_2` FOREIGN KEY (`current_position_id`) REFERENCES `positions` (`id`),
  ADD CONSTRAINT `voting_sessions_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `winners`
--
ALTER TABLE `winners`
  ADD CONSTRAINT `fk_winners_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `winners_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
