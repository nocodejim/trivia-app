<?php
// api/submit_answer.php

require_once '../includes/session_manager.php';
require_once '../includes/game_logic.php';
require_once '../config/database.php'; // For $pdo
require_once '../config/settings.php'; // For default_time_per_question
require_once '../includes/functions.php'; // For sendJsonError and sendJsonResponse

header('Content-Type: application/json');

// Get PDO instance
try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    sendJsonError('DATABASE_ERROR', 'Database connection failed: ' . $e->getMessage(), 500);
    exit;
}

$sessionManager = new SessionManager($pdo);
$gameLogic = new GameLogic($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('INVALID_METHOD', 'Only POST requests are allowed.', 405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['session_code'], $input['player_name'], $input['question_number'], $input['answer'])) {
    sendJsonError('MISSING_PARAMETERS', 'Required parameters: session_code, player_name, question_number, answer.', 400);
    exit;
}

$sessionCode = trim($input['session_code']);
$playerName = trim($input['player_name']);
$questionNumber = filter_var($input['question_number'], FILTER_VALIDATE_INT);
$playerAnswer = trim($input['answer']); // Answer can be 'A', 'B', 'C', etc.

if (empty($sessionCode) || empty($playerName) || $questionNumber === false || $questionNumber <= 0 || empty($playerAnswer)) {
    sendJsonError('INVALID_INPUT', 'Invalid or missing input parameters.', 400);
    exit;
}

// Validate session
$session = $sessionManager->getSession($sessionCode);
if (!$session) {
    sendJsonError('SESSION_NOT_FOUND', 'Session not found.', 404);
    exit;
}

// Check if game is active
if ($session['status'] !== 'active') {
    sendJsonError('GAME_NOT_ACTIVE', 'Game is not currently active. Cannot submit answers.', 403);
    exit;
}

// Check if the submitted question_number matches the session's current_question
if ((int)$session['current_question'] !== $questionNumber) {
    sendJsonError('QUESTION_MISMATCH', 'Submitted question number does not match current active question.', 409);
    exit;
}

// Validate player
$player = $sessionManager->getPlayer($sessionCode, $playerName);
if (!$player) {
    sendJsonError('PLAYER_NOT_FOUND', 'Player not found in this session.', 404);
    exit;
}

// Check for answer timeout
// Fetch time_per_question from settings table (or use a default if not found)
$settingsStmt = $pdo->query("SELECT time_per_question FROM settings LIMIT 1");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$timePerQuestion = $settings ? (int)$settings['time_per_question'] : DEFAULT_TIME_PER_QUESTION; // DEFAULT_TIME_PER_QUESTION from config/settings.php

if (empty($session['question_start_time'])) {
    sendJsonError('INTERNAL_ERROR', 'Question start time not set for the session.', 500);
    exit;
}

$questionStartTime = new DateTime($session['question_start_time']);
$currentTime = new DateTime();
$timeElapsed = $currentTime->getTimestamp() - $questionStartTime->getTimestamp();

if ($timeElapsed > $timePerQuestion) {
    sendJsonError('ANSWER_TIMEOUT', 'Answer submitted after time expired.', 403);
    exit;
}

// Call GameLogic to submit the answer
$result = $gameLogic->submitAnswer($sessionCode, $playerName, $questionNumber, $playerAnswer);

switch ($result['status']) {
    case 'success':
        sendJsonResponse(['success' => true, 'message' => 'Answer received.']);
        break;
    case 'already_submitted':
        sendJsonError('ANSWER_ALREADY_SUBMITTED', 'You have already answered this question.', 409);
        break;
    case 'question_not_found':
        // This case should ideally be caught by the session's current_question check earlier
        sendJsonError('QUESTION_NOT_FOUND', 'The question does not exist.', 404);
        break;
    case 'db_error':
        sendJsonError('DATABASE_ERROR', 'Could not record answer due to a database error.', 500);
        break;
    default:
        sendJsonError('UNKNOWN_ERROR', 'An unknown error occurred while submitting the answer.', 500);
        break;
}

?>
