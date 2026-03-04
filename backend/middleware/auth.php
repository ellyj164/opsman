<?php
/**
 * OpsMan – Authentication Middleware
 * Validates Bearer tokens against the users table.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Extract and validate the Bearer token from the Authorization header.
 * Returns the user row on success, or sends a 401 and exits on failure.
 *
 * @return array  Authenticated user row
 */
function authenticate(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        Response::error('Missing or malformed Authorization header', 401);
    }

    $token = $m[1];

    $db   = (new Database())->getConnection();
    if (!$db) {
        Response::error('Database unavailable', 503);
    }

    $stmt = $db->prepare(
        "SELECT id, username, email, role, is_active, token_expires_at
           FROM users
          WHERE token = :token
            AND is_active = 1
          LIMIT 1"
    );
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::error('Invalid or expired token', 401);
    }

    if (strtotime($user['token_expires_at']) < time()) {
        Response::error('Token has expired', 401);
    }

    return $user;
}

/**
 * Require the authenticated user to have the 'admin' role.
 */
function requireAdmin(array $user): void {
    if ($user['role'] !== 'admin') {
        Response::error('Admin access required', 403);
    }
}

/**
 * Require the authenticated user to be admin or operations_manager.
 */
function requireManager(array $user): void {
    if (!in_array($user['role'], ['admin', 'operations_manager'], true)) {
        Response::error('Manager or Admin access required', 403);
    }
}

/**
 * Require the authenticated user to be a field_employee (or higher).
 * All roles are allowed; this mainly serves as a reminder that the endpoint
 * is accessible to field employees.
 */
function requireFieldEmployee(array $user): void {
    // All authenticated users satisfy this requirement
    if (!isset($user['id'])) {
        Response::error('Authentication required', 401);
    }
}
