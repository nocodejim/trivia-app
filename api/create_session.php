<?php
/**
 * API: Create Session
 *
 * Endpoint for a host to create a new game session.
 */

// --- Temporary Debugging ---
// This will force any errors to be displayed.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Headers ---
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- Includes ---
// These files MUST exist and have the correct code.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/functions.php';

// --- 1. Validate Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // The sendJsonError function lives in includes/functions.php
    sendJsonError('METHOD_NOT_ALLOWED', 'This endpoint only accepts POST requests.', 405);
}

// --- 2. Get and Decode Input ---
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonError('INVALID_JSON', 'The provided JSON is malformed.', 400);
}

// --- 3. Validate Input Data ---
$sessionName = $input['session_name'] ?? '';
$hostEmail = $input['host_email'] ?? '';
$totalQuestions = filter_var($input['total_questions'] ?? DEFAULT_TOTAL_QUESTIONS, FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 50]]);
$timePerQuestion = filter_var($input['time_per_question'] ?? DEFAULT_TIME_PER_QUESTION, FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 60]]);

$sessionName = trim(htmlspecialchars(strip_tags($sessionName)));
$hostEmail = filter_var(trim($hostEmail), FILTER_SANITIZE_EMAIL);

if (empty($sessionName) || strlen($sessionName) > 100) {
    sendJsonError('INVALID_SESSION_NAME', 'Session name must be between 1 and 100 characters.', 400);
}
if (empty($hostEmail) || !filter_var($hostEmail, FILTER_VALIDATE_EMAIL)) {
    sendJsonError('INVALID_HOST_EMAIL', 'A valid host email is required.', 400);
}
if ($totalQuestions === false) {
    sendJsonError('INVALID_QUESTION_COUNT', 'Total questions must be an integer between 5 and 50.', 400);
}
if ($timePerQuestion === false) {
    sendJsonError('INVALID_TIME_LIMIT', 'Time per question must be an integer between 5 and 60.', 400);
}

// --- 4. Business Logic: Create Session ---
try {
    // This function lives in includes/functions.php
    $sessionCode = generateUniqueSessionCode($pdo);

    $sql = "INSERT INTO game_sessions (session_code, session_name, host_email, total_questions, time_per_question) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([$sessionCode, $sessionName, $hostEmail, $totalQuestions, $timePerQuestion]);

    // --- 5. Send Success Response ---
    // This function also lives in includes/functions.php
    sendJsonResponse(
        [
            'success' => true,
            'session_code' => $sessionCode,
            'message' => 'Session created successfully.'
        ],
        201
    );

} catch (PDOException $e) {
    sendJsonError('DATABASE_ERROR', 'Could not create the session due to a database error.', 500, $e->getMessage());
} catch (Exception $e) {
    sendJsonError('CODE_GENERATION_FAILED', 'Could not create the session due to an internal error.', 500, $e->getMessage());
}
