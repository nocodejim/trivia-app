<?php

class GameLogic {
    private PDO $pdo;
    private int $total_questions; // Assuming a fixed number of questions for now

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // In a real application, you might fetch this from a config or count questions in the DB
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM trivia_questions");
        $this->total_questions = (int)$stmt->fetchColumn();
    }

    public function startGame(string $sessionCode): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE game_sessions
             SET status = 'active', current_question = 1, question_start_time = NOW()
             WHERE session_code = :session_code AND status = 'waiting'"
        );
        $stmt->bindParam(':session_code', $sessionCode);
        return $stmt->execute();
    }

    public function advanceToNextQuestion(string $sessionCode): bool {
        $stmt = $this->pdo->prepare("SELECT current_question FROM game_sessions WHERE session_code = :session_code");
        $stmt->bindParam(':session_code', $sessionCode);
        $stmt->execute();
        $currentQuestionNum = $stmt->fetchColumn();

        if ($currentQuestionNum === false) {
            return false; // Session not found or error
        }

        $nextQuestionNum = (int)$currentQuestionNum + 1;

        if ($nextQuestionNum > $this->total_questions) {
            // Last question was just finished, mark game as completed
            $updateStmt = $this->pdo->prepare(
                "UPDATE game_sessions
                 SET status = 'completed', question_start_time = NULL
                 WHERE session_code = :session_code"
            );
            $updateStmt->bindParam(':session_code', $sessionCode);
            return $updateStmt->execute();
        } else {
            // Advance to the next question
            $updateStmt = $this->pdo->prepare(
                "UPDATE game_sessions
                 SET current_question = :next_question, question_start_time = NOW()
                 WHERE session_code = :session_code"
            );
            $updateStmt->bindParam(':next_question', $nextQuestionNum, PDO::PARAM_INT);
            $updateStmt->bindParam(':session_code', $sessionCode);
            return $updateStmt->execute();
        }
    }

    public function submitAnswer(string $sessionCode, string $playerName, int $questionNumber, string $playerAnswer): array {
        // First, check if the player has already answered this question
        $checkStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM question_responses
             WHERE session_code = :session_code AND player_name = :player_name AND question_id = (SELECT id FROM trivia_questions WHERE question_number = :question_number)"
        );
        $checkStmt->bindParam(':session_code', $sessionCode);
        $checkStmt->bindParam(':player_name', $playerName);
        $checkStmt->bindParam(':question_number', $questionNumber, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() > 0) {
            return ['status' => 'already_submitted'];
        }

        $question = $this->getQuestion($questionNumber);
        if (!$question) {
            return ['status' => 'question_not_found'];
        }
        $correctAnswer = $this->getCorrectAnswer($questionNumber);
        if ($correctAnswer === null) {
             return ['status' => 'question_not_found']; // Should not happen if getQuestion succeeded
        }

        $isCorrect = ($playerAnswer === $correctAnswer);

        // Update player's current_answer
        $updatePlayerStmt = $this->pdo->prepare(
            "UPDATE players
             SET current_answer = :current_answer
             WHERE session_code = :session_code AND player_name = :player_name"
        );
        $updatePlayerStmt->bindParam(':current_answer', $playerAnswer);
        $updatePlayerStmt->bindParam(':session_code', $sessionCode);
        $updatePlayerStmt->bindParam(':player_name', $playerName);
        $updatePlayerStmt->execute();

        // Insert into question_responses
        $stmt = $this->pdo->prepare(
            "INSERT INTO question_responses (session_code, player_name, question_id, submitted_answer, is_correct, submission_time)
             VALUES (:session_code, :player_name, (SELECT id FROM trivia_questions WHERE question_number = :question_number), :submitted_answer, :is_correct, NOW())"
        );
        $stmt->bindParam(':session_code', $sessionCode);
        $stmt->bindParam(':player_name', $playerName);
        $stmt->bindParam(':question_number', $questionNumber, PDO::PARAM_INT);
        $stmt->bindParam(':submitted_answer', $playerAnswer);
        $stmt->bindParam(':is_correct', $isCorrect, PDO::PARAM_BOOL);
        $insertSuccess = $stmt->execute();

        if (!$insertSuccess) {
            return ['status' => 'db_error'];
        }

        if ($isCorrect) {
            // Fetch time_per_question and question_start_time
            $sessionStmt = $this->pdo->prepare("SELECT gs.question_start_time, s.time_per_question
                                               FROM game_sessions gs
                                               JOIN settings s ON 1=1
                                               WHERE gs.session_code = :session_code");
            $sessionStmt->bindParam(':session_code', $sessionCode);
            $sessionStmt->execute();
            $sessionTimes = $sessionStmt->fetch(PDO::FETCH_ASSOC);

            if ($sessionTimes) {
                $questionStartTime = new DateTime($sessionTimes['question_start_time']);
                $submissionTime = new DateTime(); // Current time
                $timeTaken = $submissionTime->getTimestamp() - $questionStartTime->getTimestamp();
                $timePerQuestion = (int)$sessionTimes['time_per_question'];

                // Calculate score (e.g., 10 points for correct, bonus for speed)
                // Basic score: 10 for correct.
                // Bonus: Max 5 points, decreasing linearly with time taken.
                // score = base_score + bonus_score
                // bonus_score = max_bonus * (1 - (time_taken / time_allowed_for_bonus))
                // For simplicity, let's say time_allowed_for_bonus is time_per_question
                // And max_bonus is 5 points.
                $points = 10; // Base score for correct answer
                if ($timeTaken < $timePerQuestion) {
                    $bonusPoints = 5 * (1 - ($timeTaken / $timePerQuestion));
                    $points += round($bonusPoints);
                }


                $updateScoreStmt = $this->pdo->prepare(
                    "UPDATE players SET score = score + :points
                     WHERE session_code = :session_code AND player_name = :player_name"
                );
                $updateScoreStmt->bindParam(':points', $points, PDO::PARAM_INT);
                $updateScoreStmt->bindParam(':session_code', $sessionCode);
                $updateScoreStmt->bindParam(':player_name', $playerName);
                $updateScoreStmt->execute();
            }
        }
        return ['status' => 'success', 'correct' => $isCorrect];
    }

    public function getQuestion(int $questionNumber): ?array {
        // Ensure question_number is within bounds
        if ($questionNumber <= 0 || $questionNumber > $this->total_questions) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, question_number, question_text, answers_json
             FROM trivia_questions
             WHERE question_number = :question_number"
        );
        $stmt->bindParam(':question_number', $questionNumber, PDO::PARAM_INT);
        $stmt->execute();
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($question) {
            // The 'answers' field in the spec is an object/associative array.
            // The DB stores it as answers_json (string).
            $question['answers'] = json_decode($question['answers_json'], true);
            unset($question['answers_json']); // Remove the JSON string version
            return $question;
        }
        return null;
    }

    public function getCorrectAnswer(int $questionNumber): ?string {
        if ($questionNumber <= 0 || $questionNumber > $this->total_questions) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT correct_answer FROM trivia_questions WHERE question_number = :question_number"
        );
        $stmt->bindParam(':question_number', $questionNumber, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['correct_answer'] : null;
    }

    public function getLeaderboard(string $sessionCode): array {
        $stmt = $this->pdo->prepare(
            "SELECT player_name, score FROM players WHERE session_code = :session_code ORDER BY score DESC"
        );
        $stmt->bindParam(':session_code', $sessionCode);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalQuestions(): int {
        return $this->total_questions;
    }
}

?>
