-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2025 at 04:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cbt_db1`
--

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE `options` (
  `option_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_key` varchar(1) NOT NULL COMMENT 'e.g., A, B, C, D',
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 for correct, 0 for incorrect',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`option_id`, `question_id`, `option_key`, `option_text`, `is_correct`, `created_at`) VALUES
(1, 2, 'A', 'singing', 0, '2025-10-14 06:34:16'),
(2, 2, 'B', 'dancing', 0, '2025-10-14 06:34:16'),
(3, 2, 'C', 'giving instructions', 1, '2025-10-14 06:34:16'),
(4, 2, 'D', 'nothing', 0, '2025-10-14 06:34:16'),
(5, 3, 'A', 'creating foold', 0, '2025-10-14 06:35:40'),
(6, 3, 'B', 'dancing and moving', 0, '2025-10-14 06:35:40'),
(7, 3, 'C', 'creating story and animation', 1, '2025-10-14 06:35:40'),
(8, 3, 'D', 'none of the above', 0, '2025-10-14 06:35:40'),
(9, 4, 'A', 'creating foold', 0, '2025-10-14 06:43:20'),
(10, 4, 'B', 'dancing and moving', 0, '2025-10-14 06:43:20'),
(11, 4, 'C', 'creating story and animation', 1, '2025-10-14 06:43:20'),
(12, 4, 'D', 'none of the above', 0, '2025-10-14 06:43:20'),
(13, 5, 'A', 'creating foold', 0, '2025-10-14 06:43:44'),
(69, 19, 'A', 'instruction', 1, '2025-10-17 09:17:55'),
(70, 19, 'B', 'writing', 0, '2025-10-17 09:17:55'),
(71, 19, 'C', 'working', 0, '2025-10-17 09:17:55'),
(72, 19, 'D', 'game', 0, '2025-10-17 09:17:55'),
(73, 20, 'A', 'true', 1, '2025-10-17 09:20:20'),
(74, 20, 'B', 'false', 0, '2025-10-17 09:20:20'),
(75, 20, 'C', 'all', 0, '2025-10-17 09:20:20'),
(76, 20, 'D', 'none', 0, '2025-10-17 09:20:20'),
(77, 21, 'A', 'coding', 1, '2025-10-17 09:21:09'),
(78, 21, 'B', 'writing', 0, '2025-10-17 09:21:09'),
(79, 21, 'C', 'gaming', 0, '2025-10-17 09:21:09'),
(80, 21, 'D', 'none', 0, '2025-10-17 09:21:09'),
(81, 22, 'A', '5a', 0, '2025-10-17 09:22:04'),
(82, 22, 'B', '6b', 0, '2025-10-17 09:22:04'),
(83, 22, 'C', '5b', 1, '2025-10-17 09:22:04'),
(84, 22, 'D', '5d', 0, '2025-10-17 09:22:04'),
(85, 23, 'A', 'invisible object', 0, '2025-10-17 09:23:17'),
(86, 23, 'B', 'visible and physical object', 1, '2025-10-17 09:23:17'),
(87, 23, 'C', 'object not visible', 0, '2025-10-17 09:23:17'),
(88, 23, 'D', 'software', 0, '2025-10-17 09:23:17'),
(89, 24, 'A', 'Charle Baile', 0, '2025-10-17 09:24:44'),
(90, 24, 'B', 'charles Babbage', 1, '2025-10-17 09:24:44'),
(91, 24, 'C', 'charlies Christ', 0, '2025-10-17 09:24:44'),
(92, 24, 'D', 'bill gate', 0, '2025-10-17 09:24:44'),
(93, 25, 'A', 'instruction', 1, '2025-10-17 14:54:37'),
(94, 25, 'B', 'writing', 0, '2025-10-17 14:54:37'),
(95, 25, 'C', 'game', 0, '2025-10-17 14:54:37'),
(96, 25, 'D', 'eventing', 0, '2025-10-17 14:54:37'),
(97, 26, 'A', 'true', 1, '2025-10-17 15:09:39'),
(98, 26, 'B', 'false', 0, '2025-10-17 15:09:39'),
(99, 26, 'C', 'all', 0, '2025-10-17 15:09:39'),
(100, 26, 'D', 'none', 0, '2025-10-17 15:09:39'),
(101, 27, 'A', 'instruction', 0, '2025-10-17 15:49:25'),
(102, 27, 'B', 'writing', 0, '2025-10-17 15:49:25'),
(103, 27, 'C', 'singing', 1, '2025-10-17 15:49:25'),
(104, 27, 'D', 'taking', 0, '2025-10-17 15:49:25'),
(105, 28, 'A', '4', 0, '2025-10-18 17:33:13'),
(106, 28, 'B', '9', 1, '2025-10-18 17:33:13'),
(107, 28, 'C', '10', 0, '2025-10-18 17:33:13'),
(108, 28, 'D', '22', 0, '2025-10-18 17:33:13'),
(109, 29, 'A', '-24', 1, '2025-10-18 17:34:13'),
(110, 29, 'B', '45', 0, '2025-10-18 17:34:13'),
(111, 29, 'C', '99', 0, '2025-10-18 17:34:13'),
(112, 29, 'D', '32', 0, '2025-10-18 17:34:13'),
(113, 30, 'A', '10', 0, '2025-10-18 17:35:03'),
(114, 30, 'B', '11', 0, '2025-10-18 17:35:03'),
(115, 30, 'C', '56', 1, '2025-10-18 17:35:03'),
(116, 30, 'D', '43', 0, '2025-10-18 17:35:03'),
(205, 53, 'A', 'dfdsfds', 0, '2025-10-20 16:31:20'),
(206, 53, 'B', 'writing', 0, '2025-10-20 16:31:20'),
(207, 53, 'C', 'sfd', 0, '2025-10-20 16:31:20'),
(208, 53, 'D', 'none', 1, '2025-10-20 16:31:20'),
(209, 54, 'A', 'instruction', 0, '2025-10-20 16:31:42'),
(210, 54, 'B', 'writing', 0, '2025-10-20 16:31:42'),
(211, 54, 'C', 'dfdfd', 1, '2025-10-20 16:31:42'),
(212, 54, 'D', 'eventing', 0, '2025-10-20 16:31:42'),
(213, 55, 'A', '8', 1, '2025-10-21 09:19:13'),
(214, 55, 'B', '4', 0, '2025-10-21 09:19:13'),
(215, 55, 'C', '9', 0, '2025-10-21 09:19:13'),
(216, 55, 'D', '2', 0, '2025-10-21 09:19:13'),
(217, 56, 'A', '7', 0, '2025-10-21 09:19:50'),
(218, 56, 'B', '10', 1, '2025-10-21 09:19:50'),
(219, 56, 'C', '90', 0, '2025-10-21 09:19:50'),
(220, 56, 'D', '20', 0, '2025-10-21 09:19:50'),
(221, 57, 'A', '12', 0, '2025-10-21 09:20:35'),
(222, 57, 'B', '30', 0, '2025-10-21 09:20:35'),
(223, 57, 'C', '20', 0, '2025-10-21 09:20:35'),
(224, 57, 'D', '13', 1, '2025-10-21 09:20:35'),
(225, 58, 'A', '67', 0, '2025-10-21 09:21:14'),
(226, 58, 'B', '23', 0, '2025-10-21 09:21:14'),
(227, 58, 'C', '8', 1, '2025-10-21 09:21:14'),
(228, 58, 'D', '6', 0, '2025-10-21 09:21:14');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `question_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` text DEFAULT NULL,
  `option_b` text DEFAULT NULL,
  `option_c` text DEFAULT NULL,
  `option_d` text DEFAULT NULL,
  `correct_option` char(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`question_id`, `test_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`) VALUES
(25, 4, 'what is computer?', NULL, NULL, NULL, NULL, ''),
(26, 6, 'what is noun?', NULL, NULL, NULL, NULL, ''),
(27, 7, 'define music', NULL, NULL, NULL, NULL, ''),
(28, 5, '3+6 = ___', NULL, NULL, NULL, NULL, ''),
(29, 5, '34-60', NULL, NULL, NULL, NULL, ''),
(30, 5, '3+7', NULL, NULL, NULL, NULL, ''),
(53, 16, 'what is geograpty?', NULL, NULL, NULL, NULL, ''),
(54, 16, 'dnegifg', NULL, NULL, NULL, NULL, ''),
(55, 15, '2+6', NULL, NULL, NULL, NULL, ''),
(56, 15, 'what is the product of 2 and 5', NULL, NULL, NULL, NULL, ''),
(57, 15, 'what is the difference between 20 and 7?', NULL, NULL, NULL, NULL, ''),
(58, 15, '40 divided by 5', NULL, NULL, NULL, NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `result_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `test_id` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `correct_answers` int(11) NOT NULL DEFAULT 0,
  `wrong_answers` int(11) NOT NULL DEFAULT 0,
  `unanswered` int(11) NOT NULL DEFAULT 0,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `score` int(5) DEFAULT 0,
  `submission_time` datetime DEFAULT NULL,
  `is_submitted` tinyint(1) DEFAULT 0,
  `secure_answer_map` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`secure_answer_map`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`result_id`, `user_id`, `attempt_number`, `test_id`, `total_questions`, `correct_answers`, `wrong_answers`, `unanswered`, `start_time`, `end_time`, `score`, `submission_time`, `is_submitted`, `secure_answer_map`) VALUES
(82, 31, 1, 16, 0, 0, 0, 0, '2025-10-20 17:54:24', '2025-10-21 11:20:35', 0, NULL, 1, NULL),
(83, 27, 1, 16, 0, 0, 0, 0, '2025-10-21 08:38:18', '2025-10-21 09:16:27', 0, NULL, 1, NULL),
(84, 29, 1, 16, 0, 0, 0, 0, '2025-10-21 09:18:45', '2025-10-21 09:57:52', 0, NULL, 1, NULL),
(85, 30, 1, 16, 0, 0, 2, 0, '2025-10-21 09:58:58', NULL, 0, '2025-10-21 10:13:06', 1, NULL),
(86, 28, 1, 16, 0, 0, 2, 0, '2025-10-21 10:14:51', NULL, 0, '2025-10-21 10:15:22', 1, NULL),
(87, 32, 1, 16, 0, 0, 2, 0, '2025-10-21 10:16:47', NULL, 0, '2025-10-21 10:17:05', 1, NULL),
(88, 28, 1, 15, 0, 0, 4, 0, '2025-10-21 10:21:49', NULL, 0, '2025-10-21 10:22:28', 1, NULL),
(89, 30, 1, 15, 4, 0, 4, 0, '2025-10-21 10:27:59', NULL, 0, '2025-10-21 10:28:37', 1, NULL),
(90, 32, 1, 15, 4, 0, 4, 0, '2025-10-21 10:38:35', NULL, 0, '2025-10-21 10:39:03', 1, NULL),
(91, 31, 1, 15, 0, 0, 0, 0, '2025-10-21 10:50:25', '2025-10-21 11:20:33', 0, NULL, 1, NULL),
(92, 29, 1, 15, 4, 4, 0, 0, '2025-10-21 11:21:33', '2025-10-21 11:21:56', 100, '2025-10-21 11:21:56', 1, NULL),
(93, 33, 1, 15, 4, 4, 0, 0, '2025-10-21 11:33:24', '2025-10-21 11:33:45', 100, '2025-10-21 11:33:45', 1, NULL),
(94, 33, 1, 16, 2, 0, 2, 0, '2025-10-21 11:42:55', '2025-10-21 11:44:26', 0, '2025-10-21 11:44:26', 1, '{\"53\":{\"998895ab4e7249f0\":\"B\",\"58142e2d86443259\":\"A\",\"324e8376a25e9d07\":\"C\",\"66c7609dad5bbd20\":\"D\"},\"54\":{\"f86030cded4466fe\":\"B\",\"46a6423c9add5510\":\"C\",\"0bf83d2d8cecd4cb\":\"D\",\"c0068549fc79cc00\":\"A\"}}'),
(95, 34, 1, 15, 4, 4, 0, 0, '2025-10-21 11:55:39', '2025-10-21 11:56:01', 100, '2025-10-21 11:56:01', 1, '{\"55\":{\"51cc9c27c72028f6\":\"B\",\"8aea211d94e51e17\":\"A\",\"cd5bea8d8df9a842\":\"D\",\"e9072a9a36638805\":\"C\"},\"56\":{\"904a927d53160918\":\"D\",\"2df5d529e7c7550d\":\"A\",\"318d0036c541cffc\":\"B\",\"20e22bae7f6b9303\":\"C\"},\"57\":{\"2cd916766b8af503\":\"A\",\"fd4ee8e2c3f51987\":\"C\",\"8c16fff571ef4828\":\"D\",\"451940d706aa0bdb\":\"B\"},\"58\":{\"3d6d473f07641ab2\":\"C\",\"ae2656d60b212d1c\":\"D\",\"3b1aa823228f44ce\":\"B\",\"acfc2cdc2aecd18d\":\"A\"}}'),
(96, 34, 1, 16, 2, 0, 2, 0, '2025-10-21 11:59:36', '2025-10-21 11:59:47', 0, '2025-10-21 11:59:47', 1, '{\"53\":{\"16221f0c0333191e\":\"A\",\"527a919d27416278\":\"C\",\"5387910fe6a1f78d\":\"B\",\"95ba786334e05b77\":\"D\"},\"54\":{\"877c2c01502e702d\":\"A\",\"cccb423d7aecae68\":\"D\",\"0c160587b8529ed5\":\"B\",\"02ac2db495885cd4\":\"C\"}}'),
(97, 35, 1, 15, 4, 4, 0, 0, '2025-10-21 12:09:53', '2025-10-21 12:10:23', 100, '2025-10-21 12:10:23', 1, '{\"55\":{\"23dd145c3c9e53e6\":\"A\",\"b644947ed1375795\":\"D\",\"21289e891b2d6387\":\"C\",\"b2b858fe8b385a44\":\"B\"},\"56\":{\"2b46d86ae498cd86\":\"C\",\"9ab32201611a168b\":\"A\",\"b3a052c7874ca62e\":\"D\",\"ef8e526d030ba4e6\":\"B\"},\"57\":{\"d116b8154b573586\":\"B\",\"9bd2c49b2804ac1d\":\"D\",\"85dfb1f670ec551f\":\"A\",\"6dd6504d1747fa77\":\"C\"},\"58\":{\"b95a3d4f18773299\":\"B\",\"1bffc77340ed099b\":\"C\",\"4ce902f5288fb821\":\"A\",\"631ebde1fe9d9375\":\"D\"}}'),
(98, 36, 1, 15, 4, 4, 0, 0, '2025-10-21 12:19:05', '2025-10-21 12:19:32', 100, '2025-10-21 12:19:32', 1, '{\"55\":{\"b66ce2e30b47d37c\":\"C\",\"b41c9c8bade43332\":\"D\",\"1ddb897bccf83cde\":\"A\",\"1ac0cafa0b9dab7b\":\"B\"},\"56\":{\"0842cdd1009b567c\":\"A\",\"3060176359f25086\":\"D\",\"e9e93218375218dd\":\"C\",\"5e984eb735f55dad\":\"B\"},\"57\":{\"9f51c1fb61de8b5d\":\"B\",\"a3a98aa78f778c32\":\"C\",\"08ebc2db2e09fa92\":\"A\",\"571153e3eb38a6fc\":\"D\"},\"58\":{\"89e9654ed057a748\":\"D\",\"fcdbbf0c4ee0f9b5\":\"A\",\"e32248a9defd85c4\":\"B\",\"6cb22c7b1c3684e1\":\"C\"}}'),
(99, 36, 1, 16, 2, 0, 2, 0, '2025-10-21 12:26:27', '2025-10-21 12:26:37', 0, '2025-10-21 12:26:37', 1, '{\"53\":{\"899f726ba9ebee9e\":\"D\",\"d3026ba118020cfe\":\"C\",\"211dd605092c1b03\":\"B\",\"d19af0c32f201c82\":\"A\"},\"54\":{\"d01762e8fdeb339b\":\"D\",\"cd3f3c2484f4478a\":\"C\",\"d6300e9d93944d8e\":\"B\",\"72401cb15ff3ce6e\":\"A\"}}'),
(100, 37, 1, 15, 4, 3, 1, 0, '2025-10-21 12:31:00', '2025-10-21 12:31:25', 75, '2025-10-21 12:31:25', 1, '{\"55\":{\"c0a0f943fca75c3e\":\"D\",\"6bfaa5a34ff51b2e\":\"A\",\"8268ff64b785dfe0\":\"C\",\"8e97d9e9d962fab9\":\"B\"},\"56\":{\"b77a43b31323aa9f\":\"A\",\"b2243090bb1c5a35\":\"B\",\"0019c011ea219a06\":\"D\",\"a31176c804d393f6\":\"C\"},\"57\":{\"c735317192230fc7\":\"A\",\"47994f631cb85445\":\"C\",\"f7094a1e05c772b1\":\"B\",\"9d4e6edab18413b0\":\"D\"},\"58\":{\"73cb9530f8ad7bcd\":\"D\",\"0940b48dc927acbe\":\"B\",\"4e7873fc1007a892\":\"A\",\"522847f4d880c423\":\"C\"}}'),
(101, 37, 1, 16, 2, 0, 2, 0, '2025-10-21 12:35:04', '2025-10-21 12:35:12', 0, '2025-10-21 12:35:12', 1, '{\"53\":{\"ab5e569af410975b\":\"B\",\"46c033496261f5f9\":\"C\",\"f6598195909381ba\":\"A\",\"456453711073275f\":\"D\"},\"54\":{\"29c89bb38e62b602\":\"A\",\"cb8dc6625948a232\":\"D\",\"a5609640c50d3197\":\"C\",\"9f1c89ad219d59b3\":\"B\"}}'),
(102, 35, 1, 6, 1, 0, 1, 0, '2025-10-21 12:48:41', '2025-10-21 12:48:49', 0, '2025-10-21 12:48:49', 1, '{\"26\":{\"61a2d520053bad8f\":\"B\",\"05e214f2b238c8d1\":\"D\",\"c514f1bae41abaf7\":\"C\",\"d7d89e7a042af2f5\":\"A\"}}'),
(103, 35, 1, 16, 2, 0, 2, 0, '2025-10-21 12:48:59', '2025-10-21 12:49:11', 0, '2025-10-21 12:49:11', 1, '{\"53\":{\"3da2f0514374b447\":\"A\",\"9ad49d8050700377\":\"D\",\"aea26da904b6a449\":\"C\",\"9a6f6cff3e2b851d\":\"B\"},\"54\":{\"1ea4f67170233cfe\":\"D\",\"e417e100ffb325c5\":\"A\",\"9cb35956c159282a\":\"B\",\"bb22c1a6ed0614e0\":\"C\"}}'),
(104, 28, 1, 6, 1, 0, 1, 0, '2025-10-21 12:50:06', '2025-10-21 12:50:17', 0, '2025-10-21 12:50:17', 1, '{\"26\":{\"72574fed1e32be5a\":\"B\",\"d398da2e3df84816\":\"A\",\"4621f76f13647a33\":\"D\",\"6891b9bea44b6d67\":\"C\"}}'),
(105, 37, 1, 6, 1, 1, 0, 0, '2025-10-21 12:50:43', '2025-10-21 12:50:51', 100, '2025-10-21 12:50:51', 1, '{\"26\":{\"cda36ac48ec54260\":\"B\",\"b97364839bdfd2f0\":\"A\",\"794b238b48df0bc2\":\"C\",\"54e5010e2731fe13\":\"D\"}}');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `answer_id` int(11) NOT NULL,
  `result_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` enum('A','B','C','D') DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_answers`
--

INSERT INTO `student_answers` (`answer_id`, `result_id`, `question_id`, `selected_option`, `is_correct`) VALUES
(73, 83, 53, '', 0),
(74, 83, 54, '', 0),
(76, 84, 53, '', 0),
(77, 84, 54, '', 0),
(86, 85, 53, '', 0),
(87, 85, 54, '', 0),
(98, 86, 53, '', 0),
(99, 86, 54, '', 0),
(101, 87, 53, '', 0),
(102, 87, 54, '', 0),
(104, 88, 55, '', 0),
(105, 88, 56, '', 0),
(106, 88, 57, '', 0),
(107, 88, 58, '', 0),
(109, 89, 55, '', 0),
(110, 89, 56, '', 0),
(111, 89, 57, '', 0),
(112, 89, 58, '', 0),
(114, 90, 55, '', 0),
(115, 90, 56, '', 0),
(116, 90, 57, '', 0),
(117, 90, 58, '', 0),
(118, 91, 55, '', 0),
(119, 91, 56, '', 0),
(120, 91, 57, '', 1),
(121, 91, 58, '', 0),
(146, 92, 55, '', 1),
(147, 92, 56, '', 1),
(148, 92, 57, '', 1),
(149, 92, 58, '', 1),
(154, 93, 55, '', 1),
(155, 93, 56, '', 1),
(156, 93, 57, '', 1),
(157, 93, 58, '', 1),
(162, 94, 53, '', 0),
(163, 94, 54, '', 0),
(166, 95, 55, '', 1),
(167, 95, 56, '', 1),
(168, 95, 57, '', 1),
(169, 95, 58, '', 1),
(174, 96, 53, '', 0),
(175, 96, 54, '', 0),
(178, 97, 55, '', 1),
(179, 97, 56, '', 1),
(180, 97, 57, '', 1),
(181, 97, 58, '', 1),
(186, 98, 55, '', 1),
(187, 98, 56, '', 1),
(188, 98, 57, '', 1),
(189, 98, 58, '', 1),
(194, 99, 53, '', 0),
(195, 99, 54, '', 0),
(198, 100, 55, '', 1),
(199, 100, 56, '', 1),
(200, 100, 57, '', 0),
(201, 100, 58, '', 1),
(206, 101, 53, '', 0),
(207, 101, 54, '', 0),
(210, 102, 26, '', 0),
(212, 103, 53, '', 0),
(213, 103, 54, '', 0),
(216, 104, 26, '', 0),
(218, 105, 26, '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `test_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `class_year` varchar(10) DEFAULT NULL COMMENT 'The target class year (e.g., SSS1), or NULL for all classes.',
  `duration_minutes` int(5) NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 1 COMMENT 'Maximum number of times a student can take the test',
  `last_modified` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tests`
--

INSERT INTO `tests` (`test_id`, `created_by`, `title`, `class_year`, `duration_minutes`, `creation_date`, `is_active`, `total_questions`, `max_attempts`, `last_modified`) VALUES
(4, NULL, 'Computer Studies', 'SSS3', 30, '2025-10-17 13:19:43', 1, 0, 1, '2025-10-21 15:11:19'),
(5, NULL, 'Mathematics', 'JSS2', 20, '2025-10-17 13:50:13', 1, 0, 1, '2025-10-21 10:16:15'),
(6, NULL, 'English Language', 'JSS1', 40, '2025-10-17 15:08:30', 1, 0, 1, '2025-10-21 12:48:18'),
(7, 14, 'Music', 'JSS2', 10, '2025-10-17 15:48:51', 1, 0, 1, '2025-10-21 15:11:13'),
(15, 1, 'Mathematics', 'JSS1', 30, '2025-10-20 15:12:20', 1, 0, 1, '2025-10-21 10:21:32'),
(16, 1, 'Geography', 'JSS1', 30, '2025-10-20 15:13:21', 0, 0, 1, '2025-10-21 15:11:23');

-- --------------------------------------------------------

--
-- Table structure for table `test_questions`
--

CREATE TABLE `test_questions` (
  `test_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `full_name` varchar(150) DEFAULT NULL,
  `class_year` varchar(10) DEFAULT NULL,
  `reg_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `user_type`, `full_name`, `class_year`, `reg_date`, `email`) VALUES
(1, 'admin', '$2y$10$D7Ux/A1OnwZmkLlRCF04M.QguikWLd44n29Sk2K9LhHMngc7E5EUS', 'admin', 'System Administrator', NULL, '2025-10-13 11:01:56', ''),
(2, 'newadmin', '$2y$10$tC9Y0PjY0bL7yN9kS2mF9O3v1R5q4g4h2i3j4k5l6m7n8o9p0qR', 'admin', 'Secondary Administrator', NULL, '2025-10-13 14:33:40', ''),
(5, 'don', '$2y$10$Ke3YoGBCwkXKyI86lZl9Tu75x2ubeoUT2z5/frKrslkuXrbjuExfa', 'teacher', 'Chima kalu', NULL, '2025-10-14 09:55:05', 'kdonchima@gmail.com'),
(14, 'ode', '$2y$10$UO3hcgV05ImoWK3oTKFmieh87tURS2Vh2ZHggFXMDf/0rep6SuV4O', 'teacher', 'Friday Ode', NULL, '2025-10-17 09:12:49', 'kaludonchima6000@gmail.com'),
(27, 'gate', '$2y$10$5pwwcqb9SHLgkvW7tuVO4.pfF/UUHuXGQ9WC0NrEgmCAm.6whApWS', 'student', 'lion gare', 'JSS1', '2025-10-20 12:14:11', 'liongate@gmail.com'),
(28, 'wood', '$2y$10$z46exLTHukW9NwzmaRTtzeiIJLerOFejmPOmHwN0IrLnTgmREKBLy', 'student', 'Tiger wood', 'JSS1', '2025-10-20 12:16:14', 'tigherwood@gmail.com'),
(29, 'game', '$2y$10$5LU3FRIOGKJRqexthMqEnObA23GDfvLfncmOkdT.GPuaqjYyoqr/a', 'student', 'leopard game', 'JSS1', '2025-10-20 12:16:54', 'leopard@gmail.com'),
(30, 'zoo', '$2y$10$n4Qzs8GhhTvqgm5NPMqcAO0vaJAz.5GjCTCLsD//ajPlv7dpxSK9i', 'student', 'Monkey Zoo', 'JSS1', '2025-10-20 12:17:34', 'monkeyzoo@gmail.com'),
(31, 'goat', '$2y$10$AoSGWPd4fv/lan3Eyt4K9OCkyZmUN/CdiLhzEQf/7cMGZoF4cyb6u', 'student', 'Elephant goat', 'JSS1', '2025-10-20 12:18:25', 'elephantgoat@yahoo.com'),
(32, 'yemi', '$2y$10$jULFOROhBjiGYRA3opM3J.B.F5s5gO73VQ/QQMulS0IUfF3X38ffG', 'student', 'Yemi Hope Kalu', 'JSS1', '2025-10-20 15:52:32', ''),
(33, 'john', '$2y$10$yodHhiYtFsf8wqz5LmjaSORQQv8ZWfPMhhV6K6fK0xeg7.jKCT9JS', 'student', 'Henry Okafor John', 'JSS1', '2025-10-21 10:33:03', 'okaofakdk@gmail.com'),
(34, 'jos', '$2y$10$fzmvxhPXlwDUDE41vpZM7OEBIOB67K7bWpuZf.ssb4IRqI26XQ962', 'student', 'Joshua James', 'JSS1', '2025-10-21 10:55:24', 'dkdskak@gmaiil.com'),
(35, 'oby', '$2y$10$2peM/E1rPY3Hn0YZM8KpI.P/Lu33Lp/K0wA3KYTwuuQcAZqQEFFdW', 'student', 'Obinna Onyemaechi', 'JSS1', '2025-10-21 11:09:37', 'onyemaech@yahoo.com'),
(36, 'ike', '$2y$10$MhW2RAxURvPpME9LWrVL6e67HaxE7J2Sa09Vctk2HsQA4fJ55cDVW', 'student', 'Chukwu Ike', 'JSS1', '2025-10-21 11:18:43', '232kdkd@gmail.com'),
(37, 'fox', '$2y$10$JzDihKEPaIaJN2DU1E8njOntCd1l.teAoXFRpKxUcu3pV52lHKX5.', 'student', 'Fox Elephant', 'JSS1', '2025-10-21 11:30:40', 'elesiertkk@yahoo.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`option_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`result_id`),
  ADD UNIQUE KEY `unique_attempt` (`user_id`,`test_id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD UNIQUE KEY `unique_answer` (`result_id`,`question_id`),
  ADD UNIQUE KEY `unique_attempt` (`result_id`,`question_id`),
  ADD UNIQUE KEY `unique_result_question` (`result_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`test_id`),
  ADD KEY `fk_teacher_creator` (`created_by`);

--
-- Indexes for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD PRIMARY KEY (`test_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `options`
--
ALTER TABLE `options`
  MODIFY `option_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=229;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=220;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `test_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `fk_result_id` FOREIGN KEY (`result_id`) REFERENCES `results` (`result_id`) ON DELETE CASCADE;

--
-- Constraints for table `tests`
--
ALTER TABLE `tests`
  ADD CONSTRAINT `fk_teacher_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD CONSTRAINT `test_questions_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
