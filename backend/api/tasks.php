<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
/**
 * OpsMan – Tasks API
 * GET              — list tasks
 * GET   ?id=X      — get task
 * POST             — create task
 * PUT   ?id=X      — update task
 * PUT   ?id=X&action=update-status — update status
 * DELETE ?id=X     — delete task (admin)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/Alert.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

$user      = authenticate();
$method    = $_SERVER['REQUEST_METHOD'];
$id        = isset($_GET['id'])     ? (int) $_GET['id']  : null;
$action    = $_GET['action']        ?? '';
$body      = json_decode(file_get_contents('php://input'), true) ?? [];

$taskModel = new Task($db);
$empModel  = new Employee($db);

switch ($method) {
    case 'GET':
        if ($id) {
            getTask($taskModel, $id, $user);
        }
        listTasks($taskModel, $empModel, $user);
        break;

    case 'POST':
        createTask($taskModel, $empModel, $db, $body, $user);
        break;

    case 'PUT':
        if (!$id) {
            Response::error('Task id required', 400);
        }
        if ($action === 'update-status') {
            updateTaskStatus($taskModel, $db, $id, $body, $user);
        }
        requireManager($user);
        updateTask($taskModel, $id, $body);
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('Task id required', 400);
        }
        requireAdmin($user);
        deleteTask($taskModel, $id);
        break;

    default:
        Response::error('Method not allowed', 405);
}

// ── Handlers ──────────────────────────────────────────────────────────

function listTasks(Task $taskModel, Employee $empModel, array $user): never {
    $page    = max(1, (int) ($_GET['page']    ?? 1));
    $perPage = min((int) ($_GET['per_page'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    $filters = [
        'status'      => $_GET['status']    ?? '',
        'priority'    => $_GET['priority']  ?? '',
        'task_type'   => $_GET['task_type'] ?? '',
        'search'      => $_GET['search']    ?? '',
    ];

    // Field employees only see their own tasks
    if ($user['role'] === 'field_employee') {
        $emp = $empModel->findByUserId($user['id']);
        if ($emp) {
            $filters['assigned_to'] = $emp['id'];
        }
    }

    $items = $taskModel->list($page, $perPage, $filters);
    $total = $taskModel->countAll($filters);
    Response::paginated($items, $total, $page, $perPage);
}

function getTask(Task $taskModel, int $id, array $user): never {
    $task = $taskModel->findById($id);
    if (!$task) {
        Response::error('Task not found', 404);
    }
    Response::success($task);
}

function createTask(Task $taskModel, Employee $empModel, PDO $db, array $body, array $user): never {
    $v = new Validator();
    $v->required('title',     $body['title']     ?? '')
      ->required('task_type', $body['task_type'] ?? '')
      ->in('task_type', $body['task_type'] ?? '', [
          'customs_declaration','warehouse_inspection',
          'border_transit_supervision','cargo_inspection',
          'import_im4','export_ex1',
      ])
      ->in('priority', $body['priority'] ?? 'medium', ['low','medium','high','urgent']);
    if ($v->fails()) {
        Response::error(implode('; ', $v->errors()), 422);
    }

    // Validate unique ref if provided
    if (!empty($body['ref'])) {
        $checkRef = $db->prepare("SELECT id FROM tasks WHERE ref = :ref LIMIT 1");
        $checkRef->execute([':ref' => $body['ref']]);
        if ($checkRef->fetch()) {
            Response::error('Task ref must be unique', 422);
        }
    }

    // Resolve assigned_by from current user's employee profile
    $assignerEmp = $empModel->findByUserId($user['id']);

    // Employee-created tasks start as "pending", managers/admin can set "active"
    $isManager = in_array($user['role'], ['admin', 'operations_manager'], true);
    $status = $body['status'] ?? ($body['assigned_to'] ? 'assigned' : 'pending');
    if (!$isManager && !in_array($status, ['pending'], true)) {
        $status = 'pending';
    }

    $taskId = $taskModel->create([
        'ref'             => $body['ref']            ?? null,
        'title'           => $body['title'],
        'description'     => $body['description']    ?? null,
        'task_type'       => $body['task_type'],
        'assigned_to'     => $body['assigned_to']    ?? null,
        'assigned_by'     => $assignerEmp ? $assignerEmp['id'] : null,
        'created_by_user' => $user['id'],
        'location'        => $body['location']       ?? null,
        'shipment_ref'    => $body['shipment_ref']   ?? null,
        'deadline'        => $body['deadline']        ?? null,
        'priority'        => $body['priority']        ?? 'medium',
        'status'          => $status,
    ]);

    // If assigned to someone create an alert
    if (!empty($body['assigned_to'])) {
        (new Alert($db))->create([
            'type'       => 'task_assigned',
            'title'      => 'New Task Assigned',
            'message'    => "Task '{$body['title']}' has been assigned to you.",
            'related_to' => 'task',
            'related_id' => $taskId,
            'severity'   => 'info',
        ]);

        // Also create a notification for the assigned employee
        $assignedEmp = $empModel->findById((int) $body['assigned_to']);
        if ($assignedEmp) {
            require_once __DIR__ . '/../models/Notification.php';
            (new Notification($db))->create([
                'user_id' => $assignedEmp['user_id'],
                'message' => "You have been assigned task: {$body['title']}",
                'type'    => 'task_assigned',
            ]);
        }
    }

    // Auto-create workflow steps for import/export tasks
    if (in_array($body['task_type'], ['import_im4', 'export_ex1'], true)) {
        require_once __DIR__ . '/../models/TaskStep.php';
        $stepModel = new TaskStep($db);
        $stepModel->createWorkflowSteps($taskId, $body['task_type'], $body['assigned_to'] ?? null);
    }

    Response::success($taskModel->findById($taskId), 201);
}

function updateTask(Task $taskModel, int $id, array $body): never {
    $task = $taskModel->findById($id);
    if (!$task) {
        Response::error('Task not found', 404);
    }

    $allowed = ['title','description','task_type','assigned_to','location',
                'shipment_ref','deadline','priority','status'];
    $taskModel->update($id, array_intersect_key($body, array_flip($allowed)));
    Response::success($taskModel->findById($id));
}

function updateTaskStatus(Task $taskModel, PDO $db, int $id, array $body, array $user): never {
    $allowed = ['pending','assigned','in_progress','completed','overdue','active'];
    $status  = $body['status'] ?? '';

    if (!in_array($status, $allowed, true)) {
        Response::error('Invalid status value', 422);
    }

    $task = $taskModel->findById($id);
    if (!$task) {
        Response::error('Task not found', 404);
    }

    // Field employees can only update tasks assigned to them
    if ($user['role'] === 'field_employee') {
        $emp = (new Employee($db))->findByUserId($user['id']);
        if (!$emp || (int) $task['assigned_to'] !== (int) $emp['id']) {
            Response::error('Access denied', 403);
        }
    }

    $taskModel->updateStatus($id, $status);

    if ($status === 'completed') {
        (new Alert($db))->create([
            'type'       => 'task_completed',
            'title'      => 'Task Completed',
            'message'    => "Task '{$task['title']}' has been marked as completed.",
            'related_to' => 'task',
            'related_id' => $id,
            'severity'   => 'info',
        ]);
    }

    Response::success($taskModel->findById($id));
}

function deleteTask(Task $taskModel, int $id): never {
    $task = $taskModel->findById($id);
    if (!$task) {
        Response::error('Task not found', 404);
    }
    $taskModel->delete($id);
    Response::success(['message' => 'Task deleted']);
}
