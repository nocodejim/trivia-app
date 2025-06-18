<?php
/**
 * Core Utility Functions
 *
 * Contains essential helper functions used throughout the application.
 */

// Since settings.php is not yet used by Phase 1 APIs, we can omit it for now
// to reduce potential file-not-found errors during this debug phase.
// require_once __DIR__ . '/../config/settings.php';

/**
 * Generates a unique, random alphanumeric code for a game session.
 *
 * It checks the database to ensure the generated code is not already in use.
 *
 * @param PDO $pdo The database connection object.
 * @return string The unique 6-character session code.
 * @throws Exception If a unique code cannot be generated after several attempts.
 */
function generateUniqueSessionCode(PDO $pdo): string {
    $max_attempts = 10;
    for ($i = 0; $i < $max_attempts; $i++) {
        $code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);

        $stmt = $pdo->prepare("SELECT id FROM game_sessions WHERE session_code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch() === false) {
            return $code; // Code is unique
        }
    }
    // This is highly unlikely but important to handle.
    throw new Exception("Failed to generate a unique session code after {$max_attempts} attempts.");
}

/**
 * Sends a standardized JSON response to the client and terminates the script.
 *
 * @param array $data The payload to send.
 * @param int $statusCode The HTTP status code to set (e.g., 200, 201, 400).
 */
function sendJsonResponse(array $data, int $statusCode = 200): void {
    // Ensure no other output has been sent
    if (headers_sent()) {
        return;
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit; // Stop script execution after sending the response
}

/**
 * Sends a standardized JSON error response.
 *
 * @param string $errorCode A defined error code from the requirements doc.
 * @param string $message A user-friendly error message.
 * @param int $statusCode The HTTP status code (e.g., 400 for bad request, 404 for not found).
 * @param string|null $details Optional technical details for logging/debugging.
 */
function sendJsonError(string $errorCode, string $message, int $statusCode, ?string $details = null): void {
    $response = [
        'success' => false,
        'error_code' => $errorCode,
        'message' => $message,
    ];

    if ($details !== null) {
        $response['details'] = $details;
        error_log("API Error [{$errorCode}]: {$details}");
    }

    sendJsonResponse($response, $statusCode);
}

/**
 * Validates a session code format.
 *
 * @param string $code The session code to validate.
 * @return bool True if the format is valid, false otherwise.
 */
function isValidSessionCodeFormat(string $code): bool {
    return (bool) preg_match('/^[A-Z0-9]{6}$/', $code);
}

/**
 * Validates a player name.
 *
 * @param string $name The player name to validate.
 * @return bool True if the name is valid, false otherwise.
 */
function isValidPlayerName(string $name): bool {
    $name = trim($name);
    $length = mb_strlen($name);

    if ($length < 1 || $length > 50) {
        return false;
    }
    // Allows letters, numbers, spaces, hyphens, and underscores.
    return preg_match('/^[a-zA-Z0-9\s\-_]+$/u', $name);
}
