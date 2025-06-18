<?php
// api/get_game_state.php

require_once '../includes/session_manager.php';
require_once '../includes/game_logic.php'; // Needed for getQuestion, getCorrectAnswer, getLeaderboard, getTotalQuestions
require_once '../config/database.php';    // For $pdo
require_once '../config/settings.php';    // For default_time_per_question
require_once '../includes/functions.php';   // For sendJsonError and sendJsonResponse

header('Content-Type: application/json');
date_default_timezone_set('UTC'); // Ensure consistent time handling

// Get PDO instance
try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    sendJsonError('DATABASE_ERROR', 'Database connection failed: ' . $e->getMessage(), 500);
    exit;
}

$sessionManager = new SessionManager($pdo);
$gameLogic = new GameLogic($pdo); // Instantiate GameLogic

if (!isset($_GET['session_code'])) {
    sendJsonError('MISSING_PARAMETERS', 'Required parameter: session_code.', 400);
    exit;
}

$sessionCode = trim($_GET['session_code']);
$playerName = isset($_GET['player_name']) ? trim($_GET['player_name']) : null; // Optional, for player-specific state

if (empty($sessionCode)) {
    sendJsonError('INVALID_INPUT', 'session_code cannot be empty.', 400);
    exit;
}

$session = $sessionManager->getSession($sessionCode);
if (!$session) {
    sendJsonError('SESSION_NOT_FOUND', 'Session not found.', 404);
    exit;
}

$gameState = [
    'status' => $session['status'],
];
$response = ['success' => true, 'game_state' => $gameState, 'timestamp' => time()];

// Fetch player-specific data if player_name is provided
$playerData = null;
if ($playerName) {
    $playerData = $sessionManager->getPlayer($sessionCode, $playerName);
    if (!$playerData) {
        // Optional: could error here, or just proceed without player-specific info
        // For now, let's allow it, player might be joining or mis-typed name
    }
}

// Fetch time_per_question from settings
$settingsStmt = $pdo->query("SELECT time_per_question FROM settings LIMIT 1");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$timePerQuestion = $settings ? (int)$settings['time_per_question'] : DEFAULT_TIME_PER_QUESTION;

switch ($session['status']) {
    case 'waiting':
        $response['game_state']['players'] = $sessionManager->getPlayers($sessionCode);
        $response['game_state']['host_email'] = $session['host_email']; // Useful for host UI
        $response['game_state']['game_title'] = $session['game_title']; // Useful for display
        break;

    case 'active':
        $currentQuestionNumber = (int)$session['current_question'];
        $questionData = $gameLogic->getQuestion($currentQuestionNumber);

        if (!$questionData) {
            sendJsonError('QUESTION_NOT_FOUND', "Active question {$currentQuestionNumber} not found.", 500);
            exit;
        }

        $timeRemaining = 0;
        if (!empty($session['question_start_time'])) {
            $questionStartTime = new DateTime($session['question_start_time']);
            $currentTime = new DateTime();
            $elapsed = $currentTime->getTimestamp() - $questionStartTime->getTimestamp();
            $timeRemaining = max(0, $timePerQuestion - $elapsed);
        }
        
        $playerHasAnswered = false;
        if ($playerData && !empty($playerData['current_answer'])) {
            // Check if current_answer corresponds to the current question.
            // This requires knowing which question current_answer was for.
            // Let's assume 'current_answer' is reset or managed by submitAnswer logic
            // to only be relevant for the current question.
            // A more robust way would be to check question_responses table for current question.
            $answeredStmt = $pdo->prepare("SELECT COUNT(*) FROM question_responses WHERE session_code = :sc AND player_name = :pn AND question_id = :qid");
            $answeredStmt->execute([':sc' => $sessionCode, ':pn' => $playerName, ':qid' => $questionData['id']]);
            if ($answeredStmt->fetchColumn() > 0) {
                $playerHasAnswered = true;
            }
        }


        $response['game_state']['current_question'] = $currentQuestionNumber;
        $response['game_state']['total_questions'] = $gameLogic->getTotalQuestions();
        $response['game_state']['time_remaining'] = $timeRemaining;
        $response['game_state']['question'] = [
            'id' => $questionData['id'],
            'text' => $questionData['question_text'],
            'answers' => $questionData['answers'] // Already an array from getQuestion
        ];
        $response['game_state']['player_answered'] = $playerHasAnswered; // Player specific
        $response['game_state']['show_results'] = false; // Not showing results in 'active' state
        break;

    case 'results':
        // This state means we are showing results for a specific question.
        // The host_controls.php sets `show_results_for_question` in game_sessions.
        $resultsQuestionNumber = (int)$session['show_results_for_question'];
        if ($resultsQuestionNumber == 0) {
             sendJsonError('INVALID_STATE', "Results state active but no question specified for results.", 500);
            exit;
        }

        $questionData = $gameLogic->getQuestion($resultsQuestionNumber);
        if (!$questionData) {
            sendJsonError('QUESTION_NOT_FOUND', "Question {$resultsQuestionNumber} for results not found.", 500);
            exit;
        }
        $correctAnswer = $gameLogic->getCorrectAnswer($resultsQuestionNumber);

        // Time remaining is not strictly relevant here, but question_start_time might be from when it was active.
        // For simplicity, let's set time_remaining to 0 or not include it.
        // Or, it could be the time left when the question ended. For now, 0.
        $timeRemaining = 0; // Or calculate how much time was left if that's desired.

        $playerAnswerData = null;
        if ($playerName) {
             $qrStmt = $pdo->prepare("SELECT submitted_answer FROM question_responses WHERE session_code = :sc AND player_name = :pn AND question_id = :qid");
             $qrStmt->execute([':sc' => $sessionCode, ':pn' => $playerName, ':qid' => $questionData['id']]);
             $playerAnswerData = $qrStmt->fetch(PDO::FETCH_ASSOC);
        }


        $response['game_state']['current_question'] = $resultsQuestionNumber; // The question whose results are being shown
        $response['game_state']['total_questions'] = $gameLogic->getTotalQuestions();
        $response['game_state']['time_remaining'] = $timeRemaining; // Typically 0 in results phase
        $response['game_state']['question'] = [
            'id' => $questionData['id'],
            'text' => $questionData['question_text'],
            'answers' => $questionData['answers'],
            'correct_answer' => $correctAnswer // Key addition for 'results' state
        ];
        // Player specific details for results state:
        if ($playerData) {
            $response['game_state']['player_submitted_answer'] = $playerAnswerData ? $playerAnswerData['submitted_answer'] : null;
        }
        $response['game_state']['player_answered'] = true; // In results phase, effectively everyone has "answered" or time is up
        $response['game_state']['show_results'] = true;

        // Optionally, include all responses for this question for all players (for host or full transparency)
        // $allResponsesStmt = $pdo->prepare("SELECT player_name, submitted_answer, is_correct FROM question_responses WHERE session_code = :sc AND question_id = :qid");
        // $allResponsesStmt->execute([':sc' => $sessionCode, ':qid' => $questionData['id']]);
        // $response['game_state']['all_player_responses'] = $allResponsesStmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'completed':
        $response['game_state']['message'] = "The game has ended. Here is the final leaderboard.";
        $response['game_state']['leaderboard'] = $gameLogic->getLeaderboard($sessionCode);
        break;

    default:
        sendJsonError('UNKNOWN_GAME_STATE', "Unknown game state: {$session['status']}", 500);
        exit;
}

sendJsonResponse($response);

?>
