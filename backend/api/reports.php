<?php
/**
 * OpsMan – Reports API
 * GET              — list reports
 * GET   ?id=X      — get report
 * POST             — create report
 * PUT   ?id=X      — update report
 * POST  ?action=checkin  — check in
 * POST  ?action=checkout — check out
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/Task.php';

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

$db = (new Database())->getConnection();
if (!$db) {
    Response::error('Database connection failed', 503);
}

$user        = authenticate();
$method      = $_SERVER['REQUEST_METHOD'];
$id          = isset($_GET['id'])     ? (int) $_GET['id'] : null;
$action      = $_GET['action']        ?? '';
$body        = json_decode(file_get_contents('php://input'), true) ?? [];

$reportModel = new Report($db);
$empModel    = new Employee($db);

if ($method === 'POST' && in_array($action, ['checkin','checkout'], true)) {
    $action === 'checkin'
        ? handleCheckIn($reportModel, $empModel, $db, $body, $user)
        : handleCheckOut($reportModel, $empModel, $body, $user);
}

switch ($method) {
    case 'GET':
        if ($id) {
            getReport($reportModel, $id, $user, $empModel);
        }
        listReports($reportModel, $empModel, $user);
        break;

    case 'POST':
        createReport($reportModel, $empModel, $body, $user);
        break;

    case 'PUT':
        if (!$id) {
            Response::error('Report id required', 400);
        }
        updateReport($reportModel, $id, $body, $user, $empModel);
        break;

    default:
        Response::error('Method not allowed', 405);
}

// ── Handlers ──────────────────────────────────────────────────────────

function listReports(Report $reportModel, Employee $empModel, array $user): never {
    $page    = max(1, (int) ($_GET['page']    ?? 1));
    $perPage = min((int) ($_GET['per_page'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    $filters = [
        'status'      => $_GET['status']    ?? '',
        'date_from'   => $_GET['date_from'] ?? '',
        'date_to'     => $_GET['date_to']   ?? '',
    ];

    if ($user['role'] === 'field_employee') {
        $emp = $empModel->findByUserId($user['id']);
        $filters['employee_id'] = $emp ? $emp['id'] : 0;
    } else {
        $filters['employee_id'] = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : '';
        $filters['task_id']     = isset($_GET['task_id'])     ? (int) $_GET['task_id']     : '';
    }

    $items = $reportModel->list($page, $perPage, $filters);
    $total = $reportModel->countAll($filters);
    Response::paginated($items, $total, $page, $perPage);
}

function getReport(Report $reportModel, int $id, array $user, Employee $empModel): never {
    $report = $reportModel->findById($id);
    if (!$report) {
        Response::error('Report not found', 404);
    }
    // Field employees can only view their own reports
    if ($user['role'] === 'field_employee') {
        $emp = $empModel->findByUserId($user['id']);
        if (!$emp || (int) $report['employee_id'] !== (int) $emp['id']) {
            Response::error('Access denied', 403);
        }
    }
    Response::success($report);
}

function createReport(Report $reportModel, Employee $empModel, array $body, array $user): never {
    $v = new Validator();
    $v->required('task_id', $body['task_id'] ?? '');
    if ($v->fails()) {
        Response::error(implode('; ', $v->errors()), 422);
    }

    $emp = $empModel->findByUserId($user['id']);
    if (!$emp) {
        Response::error('Employee profile not found', 404);
    }

    $reportId = $reportModel->create([
        'task_id'      => (int) $body['task_id'],
        'employee_id'  => $emp['id'],
        'notes'        => $body['notes']        ?? null,
        'observations' => $body['observations'] ?? null,
        'status'       => $body['status']       ?? 'draft',
    ]);

    Response::success($reportModel->findById($reportId), '', 201);
}

function updateReport(Report $reportModel, int $id, array $body, array $user, Employee $empModel): never {
    $report = $reportModel->findById($id);
    if (!$report) {
        Response::error('Report not found', 404);
    }
    if ($user['role'] === 'field_employee') {
        $emp = $empModel->findByUserId($user['id']);
        if (!$emp || (int) $report['employee_id'] !== (int) $emp['id']) {
            Response::error('Access denied', 403);
        }
    }
    $reportModel->update($id, array_intersect_key($body, array_flip(['notes','observations','status'])));
    Response::success($reportModel->findById($id));
}

function handleCheckIn(Report $reportModel, Employee $empModel, PDO $db, array $body, array $user): never {
    $v = new Validator();
    $v->required('task_id',  $body['task_id']  ?? '')
      ->required('latitude', $body['latitude'] ?? '')
      ->required('longitude',$body['longitude'] ?? '')
      ->numeric('latitude',  $body['latitude']  ?? '')
      ->numeric('longitude', $body['longitude'] ?? '');
    if ($v->fails()) {
        Response::error(implode('; ', $v->errors()), 422);
    }

    $emp = $empModel->findByUserId($user['id']);
    if (!$emp) {
        Response::error('Employee profile not found', 404);
    }

    // Create or find existing draft report for this task+employee
    $existing = $db->prepare(
        "SELECT id FROM task_reports
          WHERE task_id = :tid AND employee_id = :eid AND check_in_time IS NULL
          LIMIT 1"
    );
    $existing->execute([':tid' => (int) $body['task_id'], ':eid' => $emp['id']]);
    $row = $existing->fetch();

    if ($row) {
        $reportId = $row['id'];
    } else {
        $reportId = $reportModel->create([
            'task_id'     => (int) $body['task_id'],
            'employee_id' => $emp['id'],
        ]);
    }

    $reportModel->checkIn($reportId, (float) $body['latitude'], (float) $body['longitude']);

    // Update task status to in_progress
    $db->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = :id")
       ->execute([':id' => (int) $body['task_id']]);

    // Log GPS
    $db->prepare(
        "INSERT INTO gps_logs (employee_id, task_id, latitude, longitude, accuracy)
         VALUES (:eid, :tid, :lat, :lng, :acc)"
    )->execute([
        ':eid' => $emp['id'],
        ':tid' => (int) $body['task_id'],
        ':lat' => (float) $body['latitude'],
        ':lng' => (float) $body['longitude'],
        ':acc' => $body['accuracy'] ?? null,
    ]);

    Response::success($reportModel->findById($reportId));
}

function handleCheckOut(Report $reportModel, Employee $empModel, array $body, array $user): never {
    $v = new Validator();
    $v->required('report_id', $body['report_id'] ?? '')
      ->required('latitude',  $body['latitude']  ?? '')
      ->required('longitude', $body['longitude'] ?? '')
      ->numeric('latitude',   $body['latitude']  ?? '')
      ->numeric('longitude',  $body['longitude'] ?? '');
    if ($v->fails()) {
        Response::error(implode('; ', $v->errors()), 422);
    }

    $emp    = $empModel->findByUserId($user['id']);
    $report = $reportModel->findById((int) $body['report_id']);
    if (!$report) {
        Response::error('Report not found', 404);
    }
    if ($user['role'] === 'field_employee' && $emp && (int) $report['employee_id'] !== (int) $emp['id']) {
        Response::error('Access denied', 403);
    }

    $reportModel->checkOut((int) $body['report_id'], (float) $body['latitude'], (float) $body['longitude']);
    $reportModel->update((int) $body['report_id'], ['status' => 'submitted']);

    Response::success($reportModel->findById((int) $body['report_id']));
}
