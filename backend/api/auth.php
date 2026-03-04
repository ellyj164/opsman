<?php
/**
 * OpsMan – Auth API
 * POST  action=login            — authenticate (action via POST body or query string)
 * POST  ?action=logout         — invalidate token
 * GET   ?action=me             — current user info
 * PUT   ?action=change-password — change password
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Employee.php';

// ── CORS pre-flight ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$db     = (new Database())->getConnection();
if (!$db) {
    Response::error('Database connection failed', 503);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$rawInput = file_get_contents('php://input');
$body   = json_decode($rawInput, true) ?? $_POST;

// ── Routes ────────────────────────────────────────────────────────────

if ($method === 'POST' && $action === 'login') {
    handleLogin($db, $body);
}

if ($method === 'POST' && $action === 'logout') {
    $user = authenticate();
    handleLogout($db, $user);
}

if ($method === 'GET' && $action === 'me') {
    $user = authenticate();
    handleMe($db, $user);
}

if ($method === 'PUT' && $action === 'change-password') {
    $user = authenticate();
    handleChangePassword($db, $user, $body);
}

Response::error('Invalid action or method', 400);

// ── Handlers ──────────────────────────────────────────────────────────

function handleLogin(PDO $db, array $body): never {
    $v = new Validator();
    $v->required('username', $body['username'] ?? '')
      ->required('password', $body['password'] ?? '');
    if ($v->fails()) {
        Response::error(implode('; ', $v->errors()), 422);
    }

    $userModel = new User($db);
    $user      = $userModel->findByUsername($body['username']);

    if (!$user || !password_verify($body['password'], $user['password_hash'])) {
        Response::error('Invalid credentials', 401);
    }

    // Generate token
    $token     = bin2hex(random_bytes(TOKEN_LENGTH));
    $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY_HRS * 3600);
    $userModel->updateToken($user['id'], $token, $expiresAt);

    // Log activity
    $db->prepare(
        "INSERT INTO activity_logs (user_id, action, details, ip_address)
         VALUES (:uid, 'login', 'User logged in', :ip)"
    )->execute([':uid' => $user['id'], ':ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

    // Fetch employee profile
    $empModel = new Employee($db);
    $employee = $empModel->findByUserId($user['id']);

    Response::success([
        'token'      => $token,
        'expires_at' => $expiresAt,
        'user'       => [
            'id'       => $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role'],
        ],
        'employee' => $employee ?: null,
    ]);
}

function handleLogout(PDO $db, array $user): never {
    (new User($db))->clearToken($user['id']);

    $db->prepare(
        "INSERT INTO activity_logs (user_id, action, details, ip_address)
         VALUES (:uid, 'logout', 'User logged out', :ip)"
    )->execute([':uid' => $user['id'], ':ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

    Response::success(['message' => 'Logged out successfully']);
}

function handleMe(PDO $db, array $user): never {
    $userModel = new User($db);
    $full      = $userModel->findById($user['id']);

    $empModel = new Employee($db);
    $employee = $empModel->findByUserId($user['id']);

    Response::success([
        'user'     => $full,
        'employee' => $employee ?: null,
    ]);
}

function handleChangePassword(PDO $db, array $user, array $body): never {
    $v = new Validator();
    $v->required('current_password', $body['current_password'] ?? '')
      ->required('new_password',     $body['new_password']     ?? '')
      ->minLength('new_password',    $body['new_password']     ?? '', 8);
    if ($v->fails()) {
        Response::error(implode('; ', $v->errors()), 422);
    }

    // Verify current password
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute([':id' => $user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($body['current_password'], $row['password_hash'])) {
        Response::error('Current password is incorrect', 401);
    }

    (new User($db))->updatePassword($user['id'], $body['new_password']);
    Response::success(['message' => 'Password updated successfully']);
}
