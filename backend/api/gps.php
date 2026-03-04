<?php
/**
 * OpsMan – GPS Tracking API
 * POST             — log GPS coordinates
 * GET ?employee_id — location history for employee
 * GET ?task_id     — GPS trail for task
 * GET ?action=current — current locations of active employees
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Employee.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$db = (new Database())->getConnection();
if (!$db) {
    Response::error('Database connection failed', 503);
}

$user   = authenticate();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
    logGps($db, $body, $user);
}

if ($method === 'GET') {
    if ($action === 'current') {
        requireManager($user);
        getCurrentLocations($db);
    }
    if (isset($_GET['employee_id'])) {
        getEmployeeHistory($db, (int) $_GET['employee_id'], $user);
    }
    if (isset($_GET['task_id'])) {
        getTaskTrail($db, (int) $_GET['task_id']);
    }
}

Response::error('Invalid request', 400);

// ── Handlers ──────────────────────────────────────────────────────────

function logGps(PDO $db, array $body, array $user): never {
    $v = new Validator();
    $v->required('latitude',  $body['latitude']  ?? '')
      ->required('longitude', $body['longitude'] ?? '')
      ->numeric('latitude',   $body['latitude']  ?? '')
      ->numeric('longitude',  $body['longitude'] ?? '');
    if ($v->fails()) {
        Response::error(implode('; ', $v->errors()), 422);
    }

    $empModel = new Employee($db);
    $emp      = $empModel->findByUserId($user['id']);
    if (!$emp) {
        Response::error('Employee profile not found', 404);
    }

    $stmt = $db->prepare(
        "INSERT INTO gps_logs (employee_id, task_id, latitude, longitude, accuracy, logged_at)
         VALUES (:eid, :tid, :lat, :lng, :acc, NOW())"
    );
    $stmt->execute([
        ':eid' => $emp['id'],
        ':tid' => !empty($body['task_id']) ? (int) $body['task_id'] : null,
        ':lat' => (float) $body['latitude'],
        ':lng' => (float) $body['longitude'],
        ':acc' => !empty($body['accuracy']) ? (float) $body['accuracy'] : null,
    ]);

    Response::success(['message' => 'Location logged', 'id' => (int) $db->lastInsertId()]);
}

function getEmployeeHistory(PDO $db, int $employeeId, array $user): never {
    // Field employees can only view their own history
    if ($user['role'] === 'field_employee') {
        $emp = (new Employee($db))->findByUserId($user['id']);
        if (!$emp || (int) $emp['id'] !== $employeeId) {
            Response::error('Access denied', 403);
        }
    }

    $limit = min((int) ($_GET['limit'] ?? 100), 500);
    $stmt  = $db->prepare(
        "SELECT g.*, t.title AS task_title
           FROM gps_logs g
           LEFT JOIN tasks t ON t.id = g.task_id
          WHERE g.employee_id = :eid
          ORDER BY g.logged_at DESC
          LIMIT :lim"
    );
    $stmt->bindValue(':eid', $employeeId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,      PDO::PARAM_INT);
    $stmt->execute();
    Response::success($stmt->fetchAll());
}

function getTaskTrail(PDO $db, int $taskId): never {
    $stmt = $db->prepare(
        "SELECT g.*, e.full_name AS employee_name
           FROM gps_logs g
           JOIN employees e ON e.id = g.employee_id
          WHERE g.task_id = :tid
          ORDER BY g.logged_at ASC"
    );
    $stmt->execute([':tid' => $taskId]);
    Response::success($stmt->fetchAll());
}

function getCurrentLocations(PDO $db): never {
    // Get the most recent GPS ping per active employee (within last 2 hours)
    $stmt = $db->prepare(
        "SELECT g.employee_id, g.latitude, g.longitude, g.logged_at,
                e.full_name, e.employee_code, t.title AS task_title
           FROM gps_logs g
           JOIN employees e ON e.id = g.employee_id
           JOIN users     u ON u.id = e.user_id AND u.is_active = 1
           LEFT JOIN tasks t ON t.id = g.task_id
          WHERE g.id = (
                SELECT id FROM gps_logs g2
                 WHERE g2.employee_id = g.employee_id
                 ORDER BY g2.logged_at DESC LIMIT 1
          )
            AND g.logged_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    );
    $stmt->execute();
    Response::success($stmt->fetchAll());
}
