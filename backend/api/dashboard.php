<?php
/**
 * OpsMan – Dashboard API
 * GET              — dashboard summary
 * GET ?action=stats — detailed statistics
 * GET ?action=employee-locations — employee map data
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
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
requireManager($user);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'stats':
        getStats($db);
        break;

    case 'employee-locations':
        getEmployeeLocations($db);
        break;

    default:
        getDashboard($db);
}

// ── Handlers ──────────────────────────────────────────────────────────

function getDashboard(PDO $db): never {
    $stats     = buildStats($db);
    $recentTasks = $db->query(
        "SELECT t.*, ea.full_name AS assigned_to_name
           FROM tasks t
           LEFT JOIN employees ea ON ea.id = t.assigned_to
          ORDER BY t.created_at DESC LIMIT 10"
    )->fetchAll();

    $alerts = $db->query(
        "SELECT * FROM alerts WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5"
    )->fetchAll();

    Response::success([
        'stats'        => $stats,
        'recent_tasks' => $recentTasks,
        'alerts'       => $alerts,
    ]);
}

function getStats(PDO $db): never {
    Response::success(buildStats($db));
}

function buildStats(PDO $db): array {
    $taskStatusCounts = $db->query(
        "SELECT status, COUNT(*) AS cnt FROM tasks GROUP BY status"
    )->fetchAll();

    $statusMap = [];
    foreach ($taskStatusCounts as $row) {
        $statusMap[$row['status']] = (int) $row['cnt'];
    }

    $completedToday = (int) $db->query(
        "SELECT COUNT(*) FROM tasks
          WHERE status = 'completed'
            AND DATE(updated_at) = CURDATE()"
    )->fetchColumn();

    $totalEmployees = (int) $db->query(
        "SELECT COUNT(*) FROM employees e JOIN users u ON u.id = e.user_id WHERE u.is_active = 1"
    )->fetchColumn();

    $pendingAlerts = (int) $db->query(
        "SELECT COUNT(*) FROM alerts WHERE is_read = 0"
    )->fetchColumn();

    $overdueTasks = (int) $db->query(
        "SELECT COUNT(*) FROM tasks WHERE deadline < NOW() AND status NOT IN ('completed','overdue')"
    )->fetchColumn();

    // Auto-mark overdue
    if ($overdueTasks > 0) {
        $db->exec(
            "UPDATE tasks SET status = 'overdue' WHERE deadline < NOW() AND status NOT IN ('completed','overdue')"
        );
    }

    return [
        'active_tasks'      => ($statusMap['in_progress'] ?? 0) + ($statusMap['assigned'] ?? 0),
        'pending_tasks'     => $statusMap['pending']     ?? 0,
        'completed_today'   => $completedToday,
        'overdue_tasks'     => $statusMap['overdue']     ?? 0,
        'total_employees'   => $totalEmployees,
        'pending_alerts'    => $pendingAlerts,
        'task_status_chart' => $statusMap,
        'active_shipments'  => (int) $db->query("SELECT COUNT(*) FROM shipments WHERE status IN ('pending','in_transit','arrived')")->fetchColumn(),
        'customs_pending'   => (int) $db->query("SELECT COUNT(*) FROM customs_declarations WHERE status IN ('draft','submitted','under_review')")->fetchColumn(),
        'warehouses'        => (int) $db->query("SELECT COUNT(*) FROM warehouses")->fetchColumn(),
        'active_transits'   => (int) $db->query("SELECT COUNT(*) FROM transit_records WHERE status IN ('in_transit','border_entry','border_exit')")->fetchColumn(),
    ];
}

function getEmployeeLocations(PDO $db): never {
    $stmt = $db->prepare(
        "SELECT g.employee_id, g.latitude, g.longitude, g.logged_at,
                e.full_name, e.employee_code,
                t.title AS current_task
           FROM gps_logs g
           JOIN employees e ON e.id = g.employee_id
           JOIN users     u ON u.id = e.user_id AND u.is_active = 1
           LEFT JOIN tasks t ON t.id = g.task_id
          WHERE g.id = (
                SELECT id FROM gps_logs g2
                 WHERE g2.employee_id = g.employee_id
                 ORDER BY logged_at DESC LIMIT 1
          )
            AND g.logged_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)"
    );
    $stmt->execute();
    Response::success($stmt->fetchAll());
}
