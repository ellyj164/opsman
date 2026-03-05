<?php
/**
 * OpsMan – Application Configuration
 */

// Ensure PHP errors never break JSON output
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Global exception handler – always return JSON
set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
    }
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// Convert PHP errors to exceptions so they are caught by the handler above
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    // Don't throw for suppressed errors (@operator)
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Catch fatal errors on shutdown
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Fatal error: ' . $error['message'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
});

// Timezone
date_default_timezone_set('UTC');

// Token settings
define('TOKEN_LENGTH',     32);            // bytes → 64 hex chars
define('TOKEN_EXPIRY_HRS', 24);            // hours until token expires

// File upload
define('UPLOAD_DIR',       __DIR__ . '/../../uploads/');
define('MAX_FILE_SIZE',    10 * 1024 * 1024);  // 10 MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg','jpeg','png','gif','pdf','doc','docx']);
define('ALLOWED_MIME_TYPES',  [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

// AI micro-service
define('AI_SERVICE_URL', 'http://localhost:5001');

// CORS – allowed origins (comma-separated list or '*')
define('CORS_ORIGINS', '*');

// App meta
define('APP_NAME',    'OpsMan');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     'development');  // production | development

// Pagination defaults
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE',     100);
