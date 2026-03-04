<?php
/**
 * OpsMan – Analytics API
 * Calls the Python AI service for ML-based insights; falls back to raw SQL.
 *
 * GET ?action=performance    — task completion stats per employee
 * GET ?action=delays         — delay frequency / duration
 * GET ?action=bottlenecks    — AI bottleneck analysis
 * GET ?action=predict-delay  — AI delay prediction for a task
 * GET ?action=employee-score — AI employee performance scores
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

$action = $_GET['action'] ?? 'performance';

switch ($action) {
    case 'performance':
        getPerformance($db);
        break;

    case 'delays':
        getDelays($db);
        break;

    case 'bottlenecks':
        $ai = callAiService('/api/bottlenecks');
        Response::success($ai);
        break;

    case 'predict-delay':
        $params = http_build_query([
            'task_type'                 => $_GET['task_type']                 ?? '',
            'priority'                  => $_GET['priority']                  ?? 'medium',
            'days_until_deadline'       => $_GET['days_until_deadline']       ?? 3,
            'employee_performance_score'=> $_GET['employee_performance_score'] ?? 80,
        ]);
        $ai = callAiService('/api/predict-delay?' . $params);
        Response::success($ai);
        break;

    case 'employee-score':
        $ai = callAiService('/api/employee-score');
        Response::success($ai);
        break;

    default:
        Response::error('Unknown analytics action', 400);
}

// ── Handlers ──────────────────────────────────────────────────────────

function getPerformance(PDO $db): never {
    $rows = $db->query(
        "SELECT e.id, e.full_name, e.employee_code, e.performance_score,
                COUNT(t.id)                                              AS total_tasks,
                SUM(t.status = 'completed')                             AS completed,
                SUM(t.status = 'overdue')                               AS overdue,
                SUM(t.status = 'in_progress')                           AS in_progress,
                AVG(TIMESTAMPDIFF(HOUR, tr.check_in_time, tr.check_out_time)) AS avg_hours
           FROM employees e
           LEFT JOIN tasks       t  ON t.assigned_to  = e.id
           LEFT JOIN task_reports tr ON tr.task_id = t.id AND tr.employee_id = e.id
          GROUP BY e.id, e.full_name, e.employee_code, e.performance_score
          ORDER BY completed DESC"
    )->fetchAll();

    Response::success($rows);
}

function getDelays(PDO $db): never {
    $rows = $db->query(
        "SELECT task_type,
                COUNT(*)                        AS total,
                SUM(status = 'overdue')         AS overdue_count,
                SUM(status = 'completed')       AS completed_count,
                ROUND(SUM(status='overdue') / COUNT(*) * 100, 1) AS delay_rate_pct,
                AVG(TIMESTAMPDIFF(HOUR, deadline, updated_at))   AS avg_delay_hours
           FROM tasks
          WHERE deadline IS NOT NULL
          GROUP BY task_type
          ORDER BY delay_rate_pct DESC"
    )->fetchAll();

    Response::success($rows);
}

/**
 * HTTP GET to the Python AI micro-service.
 * Returns the decoded JSON payload, or mock data on failure.
 */
function callAiService(string $path): array {
    $url = rtrim(AI_SERVICE_URL, '/') . $path;
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        // AI service not reachable – return sensible defaults
        return ['status' => 'unavailable', 'message' => 'AI service is not reachable', 'data' => []];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['raw' => $raw];
}
