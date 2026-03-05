<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
/**
 * OpsMan – Points API
 * GET                              — leaderboard
 * GET  ?action=employee&id=X       — single employee points
 * GET  ?action=step-contribution&id=X — step contribution for employee
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/EmployeePoints.php';
require_once __DIR__ . '/../models/Employee.php';

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
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$pointsModel = new EmployeePoints($db);
$empModel    = new Employee($db);

switch ($action) {
    case 'employee':
        if (!$id) Response::error('Employee id required', 400);
        getEmployeePoints($pointsModel, $id);
        break;

    case 'step-contribution':
        if (!$id) Response::error('Employee id required', 400);
        getStepContribution($pointsModel, $id);
        break;

    default:
        getLeaderboard($pointsModel);
        break;
}

// ── Handlers ──────────────────────────────────────────────────────────

function getLeaderboard(EmployeePoints $model): never {
    $month = $_GET['month'] ?? null; // format: 2024-01
    $leaderboard = $model->getLeaderboard($month);

    // Add rank number
    foreach ($leaderboard as $i => &$row) {
        $row['rank'] = $i + 1;
    }

    Response::success($leaderboard);
}

function getEmployeePoints(EmployeePoints $model, int $empId): never {
    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min((int) ($_GET['per_page']  ?? 50), 100);

    $total  = $model->getTotalPoints($empId);
    $items  = $model->getByEmployee($empId, $page, $perPage);
    $rank   = $model->getRank($empId);

    Response::success([
        'total_points' => $total,
        'rank'         => $rank,
        'history'      => $items,
    ]);
}

function getStepContribution(EmployeePoints $model, int $empId): never {
    $contribution = $model->getStepContribution($empId);
    Response::success($contribution);
}
