<?php
/**
 * Application Configuration Settings
 *
 * Phase 1: Database Foundation
 * Centralized settings for the trivia application.
 */

// --- General Application Settings ---
define('APP_NAME', 'Real-Time Trivia');
define('APP_VERSION', '1.0.0');

// --- Game Session Defaults ---
// These values are used when a host creates a new session
// but can be overridden by the host's choices.
define('DEFAULT_TOTAL_QUESTIONS', 10);
define('DEFAULT_TIME_PER_QUESTION', 15); // in seconds

// --- Session Code Generation ---
define('SESSION_CODE_LENGTH', 6);
define('SESSION_CODE_CHARS', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');

// --- Security & Rate Limiting (Conceptual) ---
// These are placeholders for where you might define rate limiting rules.
// Actual implementation would require a more complex mechanism (e.g., using Redis, Memcached, or a database table).
define('API_REQUEST_LIMIT', 100); // Max requests per minute per IP
define('API_REQUEST_WINDOW', 60); // Time window in seconds

// --- Timezone ---
// It's crucial to set a default timezone to ensure consistency with
// timestamps in the database and application logic.
date_default_timezone_set('UTC');

