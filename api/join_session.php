<?php
/**
 * API: Join Session
 *
 * Phase 1: Database Foundation
 * Endpoint for a player to join an existing game session.
 *
 * --- REQUEST ---
 * Method: POST
 * Content-Type: application/json
 * Body:
 * {
 * "session_code": "XYZ123",
 * "player_name": "JohnDoe"
 * }
 *
 * --- SUCCESS RESPONSE (200 OK) ---
 * {
 * "success": true,
 * "message": "Joined session successfully.",
 * "session_status": "waiting"
 * }
 *
 * --- ERROR RESPONSES ---
 * 400 Bad Request: Invalid input (code format, name length).
 * 404 Not Found: Session code does not exist.
 * 409 Conflict: Player name is already taken in this session.
 * 403 Forbidden: Game has already started and is not accepting new players.
 * 500 Internal Server Error: Database issue.
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('METHOD_NOT_ALLOWED', 'This endpoint only accepts POST requests.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonError('INVALID_JSON', 'The provided JSON is malformed.', 400);
}

// --- 1. Validate Input ---
$sessionCode = strtoupper(trim($input['session_code'] ?? ''));
$playerName = trim($input['player_name'] ?? '');

if (!isValidSessionCodeFormat($sessionCode)) {
    sendJsonError('INVALID_SESSION_CODE_FORMAT', 'Session code format is invalid.', 400);
}
if (!isValidPlayerName($playerName)) {
    sendJsonError('INVALID_PLAYER_NAME', 'Player name must be 1-50 characters and contain valid characters.', 400);
}

try {
    // --- 2. Check Session Existence and Status ---
    $stmt = $pdo->prepare("SELECT status FROM game_sessions WHERE session_code = ?");
    $stmt->execute([$sessionCode]);
    $session = $stmt->fetch();

    if (!$session) {
        sendJsonError('SESSION_NOT_FOUND', 'The requested game session does not exist.', 404);
    }

    if ($session['status'] !== 'waiting') {
        sendJsonError('GAME_ALREADY_STARTED', 'This game has already started and is not accepting new players.', 403);
    }

    // --- 3. Add Player to the Session ---
    // The database has a UNIQUE constraint on (session_code, player_name),
    // so an attempt to insert a duplicate name will throw a PDOException.
    $sql = "INSERT INTO players (session_code, player_name) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([$sessionCode, $playerName]);
    } catch (PDOException $e) {
        // The most likely cause for an exception here is a duplicate entry (error code 23000)
        if ($e->getCode() == '23000') {
            sendJsonError('DUPLICATE_PLAYER_NAME', 'This name is already taken in this session. Please choose another.', 409);
        } else {
            // For other unexpected database errors
            throw $e;
        }
    }

    // --- 4. Send Success Response ---
    sendJsonResponse([
        'success' => true,
        'message' => 'Joined session successfully.',
        'session_status' => 'waiting' // Confirm the status to the client
    ]);

} catch (PDOException $e) {
    sendJsonError(
        'DATABASE_ERROR',
        'A database error occurred while trying to join the session.',
        500,
        $e->getMessage()
    );
}
