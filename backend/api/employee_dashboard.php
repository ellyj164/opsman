<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
/**
 * OpsMan – Employee Dashboard API
 * GET  — returns aggregated dashboard data for the current employee:
 *        assigned tasks, task steps, completed tasks, notifications,
 *        points, ranking, and AI insights.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/TaskStep.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/EmployeePoints.php';

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
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$empModel    = new Employee($db);
$taskModel   = new Task($db);
$stepModel   = new TaskStep($db);
$notifModel  = new Notification($db);
$pointsModel = new EmployeePoints($db);

$emp = $empModel->findByUserId($user['id']);
if (!$emp) {
    Response::error('Employee profile not found', 404);
}

$empId = (int) $emp['id'];

// Assigned tasks (not completed)
$assignedTasks = $taskModel->list(1, 50, ['assigned_to' => $empId]);
$activeTasks   = array_filter($assignedTasks, fn($t) => !in_array($t['status'], ['completed']));
$completedTasks = array_filter($assignedTasks, fn($t) => $t['status'] === 'completed');

// Also get completed tasks from DB
$allTasks = $taskModel->list(1, 100, ['assigned_to' => $empId]);
$completed = array_values(array_filter($allTasks, fn($t) => $t['status'] === 'completed'));
$pending   = array_values(array_filter($allTasks, fn($t) => in_array($t['status'], ['pending', 'assigned'])));
$active    = array_values(array_filter($allTasks, fn($t) => in_array($t['status'], ['active', 'in_progress'])));

// Task steps by status
$myActiveSteps    = $stepModel->getByEmployee($empId, 'active');
$myCompletedSteps = $stepModel->getByEmployee($empId, 'completed');
$myPendingSteps   = $stepModel->getByEmployee($empId, 'pending');

// Notifications
$notifications    = $notifModel->listByUser($user['id'], 1, 10);
$unreadCount      = $notifModel->countUnreadForUser($user['id']);

// Points & ranking
$totalPoints = $pointsModel->getTotalPoints($empId);
$rank        = $pointsModel->getRank($empId);
$pointsBreakdown = $pointsModel->getByEmployee($empId, 1, 10);

// AI insights (try to fetch from AI service, gracefully degrade)
$aiInsights = null;
try {
    $aiUrl = AI_SERVICE_URL . '/api/score_employee?employee_id=' . $empId;
    $ctx   = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $aiRaw = @file_get_contents($aiUrl, false, $ctx);
    if ($aiRaw) {
        $aiData = json_decode($aiRaw, true);
        if ($aiData && isset($aiData['success']) && $aiData['success']) {
            $aiInsights = $aiData['data'];
        }
    }
} catch (\Throwable $e) {
    // AI service unavailable – not critical
}

Response::success([
    'employee'    => [
        'id'               => $emp['id'],
        'full_name'        => $emp['full_name'],
        'employee_code'    => $emp['employee_code'],
        'department'       => $emp['department'],
        'performance_score'=> $emp['performance_score'],
    ],
    'tasks'       => [
        'active'    => array_values($active),
        'pending'   => array_values($pending),
        'completed' => $completed,
        'total'     => count($allTasks),
    ],
    'steps'       => [
        'active'    => $myActiveSteps,
        'completed' => $myCompletedSteps,
        'pending'   => $myPendingSteps,
    ],
    'notifications' => [
        'items'        => $notifications,
        'unread_count' => $unreadCount,
    ],
    'points'      => [
        'total'     => $totalPoints,
        'rank'      => $rank,
        'breakdown' => $pointsBreakdown,
    ],
    'ai_insights' => $aiInsights,
]);
