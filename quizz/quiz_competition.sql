-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 28, 2025 at 03:07 PM
-- Server version: 8.4.3
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quiz_competition`
--

-- --------------------------------------------------------

--
-- Table structure for table `attempts`
--

CREATE TABLE `attempts` (
  `attempt_id` int NOT NULL,
  `user_id` int NOT NULL,
  `quiz_id` int NOT NULL,
  `score` int DEFAULT '0',
  `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `contestant_number` varchar(10) DEFAULT 'N/A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `attempts`
--

INSERT INTO `attempts` (`attempt_id`, `user_id`, `quiz_id`, `score`, `start_time`, `end_time`, `contestant_number`) VALUES
(1, 6, 1, 1, '2025-10-28 07:27:08', '2025-10-28 07:27:33', 'N/A'),
(2, 7, 1, 2, '2025-10-28 07:59:51', '2025-10-28 08:00:13', '2'),
(3, 6, 2, 0, '2025-10-28 13:09:16', NULL, 'N/A'),
(4, 8, 2, 0, '2025-10-28 13:15:45', NULL, 'N/A'),
(5, 8, 1, 0, '2025-10-28 13:16:05', '2025-10-28 13:16:25', 'N/A'),
(6, 9, 2, 0, '2025-10-28 13:20:59', NULL, 'N/A'),
(7, 9, 1, 0, '2025-10-28 13:23:58', NULL, 'N/A'),
(8, 10, 2, 0, '2025-10-28 13:53:53', NULL, 'N/A'),
(9, 10, 1, 0, '2025-10-28 14:26:03', NULL, '2'),
(10, 11, 2, 0, '2025-10-28 14:45:28', NULL, '1'),
(11, 11, 1, 0, '2025-10-28 14:53:51', NULL, '1'),
(12, 13, 1, 0, '2025-10-28 14:56:11', NULL, '1'),
(13, 13, 2, 0, '2025-10-28 15:00:38', NULL, '1');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `question_id` int NOT NULL,
  `quiz_id` int NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`question_id`, `quiz_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `image_path`) VALUES
(1, 1, 'what is a noun?', 'Name', 'Action', 'Added', 'none', 'A', NULL),
(2, 1, 'Verb is a doing word', 'True', 'False', 'Both', 'None', 'A', NULL),
(3, 2, '23 - 10', '10', '23', '20', '13', 'D', NULL),
(4, 2, 'mention one input device', 'speaker', 'mouse', 'monitor', 'work', 'B', 'images/questions/img_6900c015d07e51.17718650.png'),
(5, 2, 'mention one input device', 'speaker', 'mouse', 'monitor', 'work', 'B', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quiz_id` int NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `total_questions` int NOT NULL,
  `time_limit_minutes` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`quiz_id`, `title`, `description`, `is_active`, `total_questions`, `time_limit_minutes`, `created_at`) VALUES
(1, 'YEAR FOUR QUIZ', '0', 1, 10, 5, '2025-10-28 06:37:51'),
(2, 'MATHEMATICS QUIZ', '0', 1, 10, 10, '2025-10-28 11:24:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('admin','participant') NOT NULL DEFAULT 'participant',
  `registration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `user_type`, `registration_date`) VALUES
(1, 'admin', 'kaludonchima@gmail.com', '$2y$10$wDQVFNRari5W0TYH/pY4iefDqD6Wj7DBER79NwmAEfGBeITFZQd6u', 'admin', '2025-10-28 05:54:16'),
(6, 'Don', 'kaludonchima444@gmail.com', '$2y$10$8lyA5rtCyv9wUnY3gBY7SOjDPJbqtm2h/ADR38Jdv2EmNWUgTLR4W', 'participant', '2025-10-28 07:26:34'),
(7, 'James', 'james12300@gmail.com', '$2y$10$zqmc.J6JrL82DkTEDrhmBeleOtigdL9qr/5jrUcwhCM4ZDk7.I88y', 'participant', '2025-10-28 07:59:27'),
(8, 'jon', 'jonnyjames@gmail.com', '$2y$10$0PbCZ.HD85Ez8CM1cdcA/.op8QhPZgjwPUBe7AOtUNzAHlskG8QWS', 'participant', '2025-10-28 13:14:25'),
(9, 'lion', 'linongate@yahoo.com', '$2y$10$km0NgC9l/ZheByWJkhlMzOX5VwbSktJ75NlbSXgBZtIJk.Oj8U1VK', 'participant', '2025-10-28 13:19:54'),
(10, 'wood', 'woodgo@gmail.com', '$2y$10$i2L8WcvS2wYTbA8IvdcKQ.qbHu0SG6DNSdS3AdQUPn4a/NecdSH6u', 'participant', '2025-10-28 13:53:26'),
(11, 'hope', 'dfdfssaa@gmail.com', '$2y$10$p4gaN1FSnl0FeQZTisgGmOUtqcq2kT/EDugD0mpmqU0VXgQ74Iolm', 'participant', '2025-10-28 14:45:09'),
(12, 'buxh', 'wiutksks@gmail.com', '$2y$10$OjdRmvwSM8vm0R4vJuQwSuqBrMmBBk/A2TUdpadZCzUy65oKgERu.', 'participant', '2025-10-28 14:55:03'),
(13, 'games', 'gamesisi@gmail.com', '$2y$10$rNm5jdEymCvQkuNF547Te.0b230gn.uJXmlGD35yyhpTvC9iZ5csK', 'participant', '2025-10-28 14:55:51');

-- --------------------------------------------------------

--
-- Table structure for table `user_answers`
--

CREATE TABLE `user_answers` (
  `id` int NOT NULL,
  `attempt_id` int NOT NULL,
  `question_id` int NOT NULL,
  `selected_option` enum('A','B','C','D') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_answers`
--

INSERT INTO `user_answers` (`id`, `attempt_id`, `question_id`, `selected_option`, `is_correct`) VALUES
(1, 1, 1, 'B', 0),
(2, 1, 2, 'A', 1),
(3, 2, 1, 'A', 1),
(4, 2, 2, 'A', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attempts`
--
ALTER TABLE `attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD UNIQUE KEY `unique_attempt` (`user_id`,`quiz_id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quiz_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_answers`
--
ALTER TABLE `user_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attempts`
--
ALTER TABLE `attempts`
  MODIFY `attempt_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `question_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quiz_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_answers`
--
ALTER TABLE `user_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attempts`
--
ALTER TABLE `attempts`
  ADD CONSTRAINT `attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attempts_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_answers`
--
ALTER TABLE `user_answers`
  ADD CONSTRAINT `user_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`attempt_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
