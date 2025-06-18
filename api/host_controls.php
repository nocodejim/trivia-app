<?php
// api/host_controls.php

require_once '../includes/session_manager.php';
require_once '../includes/game_logic.php';
require_once '../config/database.php'; // For $pdo
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

if (!isset($input['session_code'], $input['host_email'], $input['action'])) {
    sendJsonError('MISSING_PARAMETERS', 'Required parameters: session_code, host_email, action.', 400);
    exit;
}

$sessionCode = trim($input['session_code']);
$hostEmail = trim($input['host_email']);
$action = trim($input['action']);

if (empty($sessionCode) || empty($hostEmail) || empty($action)) {
    sendJsonError('INVALID_INPUT', 'Parameters cannot be empty.', 400);
    exit;
}

// Validate session and host
$session = $sessionManager->getSession($sessionCode);
if (!$session) {
    sendJsonError('SESSION_NOT_FOUND', 'Session not found.', 404);
    exit;
}

if (!$sessionManager->isHost($sessionCode, $hostEmail)) {
    sendJsonError('INVALID_HOST', 'The provided email does not match the session host.', 403);
    exit;
}

$responseMessage = "Action '{$action}' successful.";
$success = true;

switch ($action) {
    case 'start_game':
        if ($session['status'] !== 'waiting') {
            sendJsonError('GAME_ALREADY_STARTED', 'Game has already started or is completed.', 409);
            exit;
        }
        if (!$gameLogic->startGame($sessionCode)) {
            sendJsonError('ACTION_FAILED', 'Failed to start game.', 500);
            exit;
        }
        break;

    case 'next_question':
        if ($session['status'] !== 'active' && $session['status'] !== 'results') {
            sendJsonError('GAME_NOT_ACTIVE', 'Game is not active or not in results phase.', 409);
            exit;
        }
        // If current status is 'results', we first need to change it to 'active' for advancing.
        // Or, more simply, advanceToNextQuestion handles going from 'active' to 'completed' or next q.
        // If in 'results', advancing implicitly means moving from showing previous question's results to the next question.
        // The game_logic's advanceToNextQuestion will set the question_start_time for the new question.
        // It also handles changing state to 'completed' if it's the last question.

        // If 'results', first update status to 'active' to signify we are moving on
        if ($session['status'] === 'results') {
            $stmt = $pdo->prepare("UPDATE game_sessions SET status = 'active', show_results_for_question = NULL WHERE session_code = :session_code");
            $stmt->bindParam(':session_code', $sessionCode);
            if (!$stmt->execute()) {
                sendJsonError('ACTION_FAILED', 'Failed to update session status before advancing.', 500);
                exit;
            }
        }


        if (!$gameLogic->advanceToNextQuestion($sessionCode)) {
            sendJsonError('ACTION_FAILED', 'Failed to advance to next question.', 500);
            exit;
        }
        // Check if game is now completed
        $updatedSession = $sessionManager->getSession($sessionCode);
        if ($updatedSession && $updatedSession['status'] === 'completed') {
            $responseMessage = "Game advanced to completion.";
        } else {
            $responseMessage = "Advanced to the next question.";
        }
        break;

    case 'show_results':
        // This action transitions the game state to 'results' for the current question.
        // It doesn't advance the question itself.
        if ($session['status'] !== 'active') {
            sendJsonError('GAME_NOT_ACTIVE', 'Game must be active to show results.', 409);
            exit;
        }
        if ($session['current_question'] == 0) {
             sendJsonError('NO_QUESTION_ACTIVE', 'No question is currently active to show results for.', 409);
            exit;
        }
        // Update session status to 'results' and store which question's results are being shown
        $stmt = $pdo->prepare("UPDATE game_sessions SET status = 'results', show_results_for_question = :current_question WHERE session_code = :session_code");
        $stmt->bindParam(':current_question', $session['current_question'], PDO::PARAM_INT);
        $stmt->bindParam(':session_code', $sessionCode);
        if (!$stmt->execute()) {
            sendJsonError('ACTION_FAILED', 'Failed to set game to results mode.', 500);
            exit;
        }
        $responseMessage = "Game set to show results for question " . $session['current_question'] . ".";
        break;

    case 'end_game':
        // This action immediately ends the game and sets its status to 'completed'.
        if ($session['status'] === 'completed') {
            sendJsonError('GAME_ALREADY_COMPLETED', 'Game is already completed.', 409);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE game_sessions SET status = 'completed', current_question = NULL, question_start_time = NULL WHERE session_code = :session_code");
        $stmt->bindParam(':session_code', $sessionCode);
        if (!$stmt->execute()) {
            sendJsonError('ACTION_FAILED', 'Failed to end game.', 500);
            exit;
        }
        $responseMessage = "Game has been ended and set to completed state.";
        break;

    default:
        sendJsonError('INVALID_ACTION', 'Invalid action specified.', 400);
        exit;
}

sendJsonResponse(['success' => $success, 'message' => $responseMessage]);

?>
