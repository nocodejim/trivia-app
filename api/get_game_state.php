<?php
/**
 * API: Get Game State
 *
 * Phase 1: Database Foundation (implements 'waiting' state)
 * The most critical endpoint for real-time updates. Clients will poll this
 * endpoint every few seconds to get the latest state of the game.
 *
 * --- REQUEST ---
 * Method: GET
 * URL: /api/get_game_state.php?session_code=XYZ123
 *
 * --- RESPONSE (Waiting State) ---
 * {
 * "success": true,
 * "game_state": {
 * "status": "waiting",
 * "session_name": "My Awesome Trivia Night",
 * "player_count": 3,
 * "players": ["Alice", "Bob", "Charlie"]
 * },
 * "timestamp": 1678886400
 * }
 *
 * --- ERROR RESPONSES ---
 * 400 Bad Request: Missing session_code.
 * 404 Not Found: Session code does not exist.
 * 500 Internal Server Error: Database issue.
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonError('METHOD_NOT_ALLOWED', 'This endpoint only accepts GET requests.', 405);
}

// --- 1. Validate Input ---
$sessionCode = strtoupper(trim($_GET['session_code'] ?? ''));

if (empty($sessionCode)) {
    sendJsonError('MISSING_SESSION_CODE', 'The session_code parameter is required.', 400);
}
if (!isValidSessionCodeFormat($sessionCode)) {
    sendJsonError('INVALID_SESSION_CODE_FORMAT', 'Session code format is invalid.', 400);
}

try {
    // --- 2. Fetch Core Game Session Info ---
    $stmt = $pdo->prepare("SELECT session_name, status, updated_at FROM game_sessions WHERE session_code = ?");
    $stmt->execute([$sessionCode]);
    $session = $stmt->fetch();

    if (!$session) {
        sendJsonError('SESSION_NOT_FOUND', 'The requested game session does not exist.', 404);
    }

    // Prepare the base response structure
    $response = [
        'success' => true,
        'game_state' => [
            'status' => $session['status'],
        ],
        // The timestamp allows the client to check if data has actually changed
        'timestamp' => strtotime($session['updated_at']) 
    ];

    // --- 3. Build State-Specific Response ---
    // This switch will be expanded in later phases. For Phase 1, we only need 'waiting'.
    switch ($session['status']) {
        case 'waiting':
            // For the waiting state, we need the list of joined players.
            $playerStmt = $pdo->prepare("SELECT player_name FROM players WHERE session_code = ? ORDER BY joined_at ASC");
            $playerStmt->execute([$sessionCode]);
            $players = $playerStmt->fetchAll(PDO::FETCH_COLUMN, 0); // Fetch all names into a simple array

            $response['game_state']['session_name'] = $session['session_name'];
            $response['game_state']['player_count'] = count($players);
            $response['game_state']['players'] = $players;
            $response['game_state']['message'] = "Waiting for the host to start the game...";
            break;

        case 'active':
            // To be implemented in Phase 2
            $response['game_state']['message'] = "Game is active.";
            break;

        case 'completed':
            // To be implemented in Phase 2
            $response['game_state']['message'] = "Game has been completed.";
            break;
        
        default:
            // Handle other potential states like 'paused'
             $response['game_state']['message'] = "Game is in an intermediate state.";
            break;
    }

    // --- 4. Send the final response ---
    sendJsonResponse($response, 200);

} catch (PDOException $e) {
    sendJsonError(
        'DATABASE_ERROR',
        'A database error occurred while fetching the game state.',
        500,
        $e->getMessage()
    );
}

