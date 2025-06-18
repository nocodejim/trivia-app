<?php
// api/get_leaderboard.php

require_once '../includes/session_manager.php'; // To validate session if needed, though GameLogic does it too
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

$sessionManager = new SessionManager($pdo); // Good for checking session existence first
$gameLogic = new GameLogic($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonError('INVALID_METHOD', 'Only GET requests are allowed.', 405);
    exit;
}

if (!isset($_GET['session_code'])) {
    sendJsonError('MISSING_PARAMETERS', 'Required parameter: session_code.', 400);
    exit;
}

$sessionCode = trim($_GET['session_code']);

if (empty($sessionCode)) {
    sendJsonError('INVALID_INPUT', 'session_code cannot be empty.', 400);
    exit;
}

// Validate session
$session = $sessionManager->getSession($sessionCode);
if (!$session) {
    sendJsonError('SESSION_NOT_FOUND', 'Session not found.', 404);
    exit;
}

// Any player can view the leaderboard, so no specific player validation needed here.
// Game can be in any state ('waiting', 'active', 'results', 'completed') to view leaderboard.

$leaderboard = $gameLogic->getLeaderboard($sessionCode);

if ($leaderboard === null) {
    // This might happen if GameLogic::getLeaderboard had an issue, though it's typed to return array.
    // More likely, if the session code was invalid, but getSession already checks that.
    // For robustness, let's consider it.
    sendJsonError('LEADERBOARD_UNAVAILABLE', 'Leaderboard could not be retrieved.', 500);
    exit;
}

sendJsonResponse(['success' => true, 'leaderboard' => $leaderboard]);

?>
