-- Trivia Web Application Database Setup
-- Hostinger Compatible: MySQL 8.x
-- Phase 1: Database Foundation

--
-- Table structure for table `game_sessions`
-- Stores information about each created game session.
--

CREATE TABLE `game_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_code` varchar(6) NOT NULL,
  `session_name` varchar(100) NOT NULL,
  `host_email` varchar(100) NOT NULL,
  `status` enum('waiting','active','paused','completed') DEFAULT 'waiting',
  `current_question` int DEFAULT 0,
  `question_start_time` timestamp NULL DEFAULT NULL,
  `total_questions` int DEFAULT 10,
  `time_per_question` int DEFAULT 15,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_code` (`session_code`),
  KEY `idx_status_created` (`status`,`created_at`) COMMENT 'For cleaning up old sessions'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `players`
-- Stores player information for each game session.
--

CREATE TABLE `players` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_code` varchar(6) NOT NULL,
  `player_name` varchar(50) NOT NULL,
  `score` int DEFAULT 0,
  `current_answer` enum('A','B','C','None') DEFAULT 'None',
  `last_answer_time` timestamp NULL DEFAULT NULL,
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_player_session` (`session_code`,`player_name`),
  KEY `fk_players_game_sessions` (`session_code`),
  CONSTRAINT `fk_players_game_sessions` FOREIGN KEY (`session_code`) REFERENCES `game_sessions` (`session_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `question_responses`
-- Logs every answer from every player for scoring and review.
--

CREATE TABLE `question_responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_code` varchar(6) NOT NULL,
  `player_name` varchar(50) NOT NULL,
  `question_number` int NOT NULL,
  `player_answer` enum('A','B','C','None') DEFAULT 'None',
  `correct_answer` enum('A','B','C') NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `response_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_question` (`session_code`,`question_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Table structure for table `trivia_questions`
-- A repository of all available trivia questions for the games.
--

CREATE TABLE `trivia_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `answer_a` varchar(255) NOT NULL,
  `answer_b` varchar(255) NOT NULL,
  `answer_c` varchar(255) NOT NULL,
  `correct_answer` enum('A','B','C') NOT NULL,
  `category` varchar(50) DEFAULT 'General',
  `difficulty` enum('Easy','Medium','Hard') DEFAULT 'Medium',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping sample data for `trivia_questions`
--

INSERT INTO `trivia_questions` (`id`, `question`, `answer_a`, `answer_b`, `answer_c`, `correct_answer`, `category`, `difficulty`, `active`, `created_at`) VALUES
(1, 'What is the capital of France?', 'Berlin', 'Madrid', 'Paris', 'C', 'Geography', 'Easy', 1, NOW()),
(2, 'Which planet is known as the Red Planet?', 'Earth', 'Mars', 'Jupiter', 'B', 'Science', 'Easy', 1, NOW()),
(3, 'Who wrote the play \"Romeo and Juliet\"?', 'William Shakespeare', 'Charles Dickens', 'Mark Twain', 'A', 'Literature', 'Easy', 1, NOW()),
(4, 'What is the largest mammal in the world?', 'Elephant', 'Blue Whale', 'Great White Shark', 'B', 'Animals', 'Medium', 1, NOW()),
(5, 'What element does \"O\" represent on the periodic table?', 'Oxygen', 'Gold', 'Osmium', 'A', 'Chemistry', 'Medium', 1, NOW()),
(6, 'In what year did the Titanic sink?', '1905', '1912', '1918', 'B', 'History', 'Medium', 1, NOW()),
(7, 'Who painted the Mona Lisa?', 'Vincent van Gogh', 'Pablo Picasso', 'Leonardo da Vinci', 'C', 'Art', 'Hard', 1, NOW()),
(8, 'What is the powerhouse of the cell?', 'Nucleus', 'Mitochondrion', 'Ribosome', 'B', 'Biology', 'Hard', 1, NOW()),
(9, 'Which country is the largest by land area?', 'Canada', 'China', 'Russia', 'C', 'Geography', 'Medium', 1, NOW()),
(10, 'What is the main ingredient in guacamole?', 'Tomato', 'Avocado', 'Onion', 'B', 'Food', 'Easy', 1, NOW()),
(11, 'How many continents are there?', '5', '6', '7', 'C', 'Geography', 'Easy', 1, NOW()),
(12, 'Who was the first person to step on the moon?', 'Buzz Aldrin', 'Yuri Gagarin', 'Neil Armstrong', 'C', 'History', 'Medium', 1, NOW()),
(13, 'What is the currency of Japan?', 'Yuan', 'Yen', 'Won', 'B', 'Finance', 'Easy', 1, NOW()),
(14, 'Which artist is known for co-founding the Cubist movement?', 'Pablo Picasso', 'Claude Monet', 'Salvador Dalí', 'A', 'Art', 'Hard', 1, NOW()),
(15, 'What is the hardest natural substance on Earth?', 'Gold', 'Iron', 'Diamond', 'C', 'Science', 'Medium', 1, NOW());

--
-- Finalizing setup
--
COMMIT;
