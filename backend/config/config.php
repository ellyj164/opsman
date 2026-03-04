<?php
/**
 * OpsMan – Application Configuration
 */

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
