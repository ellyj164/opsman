<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
/**
 * OpsMan – Task Steps (Workflow) API
 * GET    ?task_id=X              — list steps for a task
 * GET    ?id=X                   — get single step
 * POST   ?action=complete&id=X   — complete a step
 * POST   ?action=assign          — assign step to employee
 * POST   ?action=transfer        — transfer to another employee
 * POST   ?action=create-workflow — create workflow steps for a task
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/TaskStep.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/EmployeePoints.php';

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

$user   = authenticate();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$stepModel    = new TaskStep($db);
$taskModel    = new Task($db);
$empModel     = new Employee($db);
$notifModel   = new Notification($db);
$pointsModel  = new EmployeePoints($db);

switch ($method) {
    case 'GET':
        if ($id) {
            getStep($stepModel, $id);
        }
        $taskId = isset($_GET['task_id']) ? (int) $_GET['task_id'] : null;
        if ($taskId) {
            listSteps($stepModel, $taskId);
        }
        // Get steps for current employee
        $empSteps = $_GET['my_steps'] ?? '';
        if ($empSteps) {
            getMySteps($stepModel, $empModel, $user);
        }
        Response::error('task_id or id parameter required', 400);
        break;

    case 'POST':
        switch ($action) {
            case 'complete':
                if (!$id) Response::error('Step id required', 400);
                completeStepAction($stepModel, $taskModel, $empModel, $notifModel, $pointsModel, $db, $id, $user);
                break;

            case 'assign':
                requireManager($user);
                assignStepAction($stepModel, $notifModel, $body, $user);
                break;

            case 'transfer':
                transferStepAction($stepModel, $empModel, $notifModel, $body, $user);
                break;

            case 'create-workflow':
                requireManager($user);
                createWorkflow($stepModel, $taskModel, $body);
                break;

            default:
                Response::error('Invalid action', 400);
        }
        break;

    default:
        Response::error('Method not allowed', 405);
}

// ── Handlers ──────────────────────────────────────────────────────────

function listSteps(TaskStep $stepModel, int $taskId): never {
    $steps = $stepModel->getByTaskId($taskId);
    Response::success($steps);
}

function getStep(TaskStep $stepModel, int $id): never {
    $step = $stepModel->findById($id);
    if (!$step) {
        Response::error('Step not found', 404);
    }
    Response::success($step);
}

function getMySteps(TaskStep $stepModel, Employee $empModel, array $user): never {
    $emp = $empModel->findByUserId($user['id']);
    if (!$emp) {
        Response::error('Employee profile not found', 404);
    }
    $status = $_GET['status'] ?? null;
    $steps  = $stepModel->getByEmployee($emp['id'], $status ?: null);
    Response::success($steps);
}

function completeStepAction(
    TaskStep $stepModel, Task $taskModel, Employee $empModel,
    Notification $notifModel, EmployeePoints $pointsModel, PDO $db,
    int $stepId, array $user
): never {
    $step = $stepModel->findById($stepId);
    if (!$step) {
        Response::error('Step not found', 404);
    }

    // Verify user is assigned to this step or is manager
    $emp = $empModel->findByUserId($user['id']);
    if (!in_array($user['role'], ['admin', 'operations_manager'], true)) {
        if (!$emp || (int) $step['assigned_to'] !== (int) $emp['id']) {
            Response::error('You are not assigned to this step', 403);
        }
    }

    // Ensure previous steps are completed (enforce workflow order)
    if ($step['step_number'] > 1) {
        $prevStmt = $db->prepare(
            "SELECT status FROM task_steps
              WHERE task_id = :task_id AND step_number = :prev_num LIMIT 1"
        );
        $prevStmt->execute([
            ':task_id'  => $step['task_id'],
            ':prev_num' => $step['step_number'] - 1,
        ]);
        $prev = $prevStmt->fetch();
        if ($prev && $prev['status'] !== 'completed') {
            Response::error('Previous step must be completed first', 422);
        }
    }

    $result = $stepModel->completeStep($stepId);
    if (!$result) {
        Response::error('Could not complete step', 500);
    }

    // Award points
    if ($emp) {
        $points = TaskStep::getStepPoints($step['step_name']);
        $pointsModel->create([
            'employee_id' => $emp['id'],
            'task_id'     => $step['task_id'],
            'step_name'   => $step['step_name'],
            'points'      => $points,
            'reason'      => "Completed step: {$step['step_name']}",
        ]);
    }

    // Check if all steps are done → mark task as completed
    if ($stepModel->allStepsCompleted($step['task_id'])) {
        $taskModel->updateStatus($step['task_id'], 'completed');
    }

    // Create notification for step completion
    $task = $taskModel->findById($step['task_id']);
    if ($task && $task['assigned_by']) {
        $assignerEmp = $empModel->findById($task['assigned_by']);
        if ($assignerEmp) {
            $notifModel->create([
                'user_id' => $assignerEmp['user_id'],
                'message' => "Step \"{$step['step_name']}\" completed on task {$task['ref']}",
                'type'    => 'step_completed',
            ]);
        }
    }

    Response::success($stepModel->findById($stepId));
}

function assignStepAction(TaskStep $stepModel, Notification $notifModel, array $body, array $user): never {
    $stepId = (int) ($body['step_id'] ?? 0);
    $empId  = (int) ($body['employee_id'] ?? 0);

    if (!$stepId || !$empId) {
        Response::error('step_id and employee_id required', 400);
    }

    $step = $stepModel->findById($stepId);
    if (!$step) {
        Response::error('Step not found', 404);
    }

    $stepModel->assignStep($stepId, $empId);

    // Notify the assigned employee
    $notifModel->create([
        'user_id' => $empId,
        'message' => "You have been assigned step \"{$step['step_name']}\"",
        'type'    => 'task_assigned',
    ]);

    Response::success($stepModel->findById($stepId));
}

function transferStepAction(TaskStep $stepModel, Employee $empModel, Notification $notifModel, array $body, array $user): never {
    $fromStepId  = (int) ($body['from_step_id'] ?? 0);
    $toEmpId     = (int) ($body['to_employee_id'] ?? 0);

    if (!$fromStepId || !$toEmpId) {
        Response::error('from_step_id and to_employee_id required', 400);
    }

    $fromStep = $stepModel->findById($fromStepId);
    if (!$fromStep) {
        Response::error('Step not found', 404);
    }

    // Verify the current user completed this step (or is manager)
    if (!in_array($user['role'], ['admin', 'operations_manager'], true)) {
        $emp = $empModel->findByUserId($user['id']);
        if (!$emp || (int) $fromStep['assigned_to'] !== (int) $emp['id']) {
            Response::error('You can only transfer from steps assigned to you', 403);
        }
        if ($fromStep['status'] !== 'completed') {
            Response::error('You must complete your step before transferring', 422);
        }
    }

    $result = $stepModel->transferStep($fromStep['task_id'], $fromStepId, $toEmpId);
    if (!$result) {
        Response::error('No next step available for transfer', 422);
    }

    // Notify the new employee
    $toEmp = $empModel->findById($toEmpId);
    if ($toEmp) {
        $notifModel->create([
            'user_id' => $toEmp['user_id'],
            'message' => "Task step \"{$result['step_name']}\" has been transferred to you",
            'type'    => 'task_transferred',
        ]);
    }

    Response::success($result);
}

function createWorkflow(TaskStep $stepModel, Task $taskModel, array $body): never {
    $taskId = (int) ($body['task_id'] ?? 0);
    if (!$taskId) {
        Response::error('task_id required', 400);
    }

    $task = $taskModel->findById($taskId);
    if (!$task) {
        Response::error('Task not found', 404);
    }

    if (!in_array($task['task_type'], ['import_im4', 'export_ex1'], true)) {
        Response::error('Workflow steps only apply to import_im4 or export_ex1 tasks', 422);
    }

    // Check if steps already exist
    $existing = $stepModel->getByTaskId($taskId);
    if (!empty($existing)) {
        Response::error('Workflow steps already exist for this task', 422);
    }

    $stepModel->createWorkflowSteps($taskId, $task['task_type'], $task['assigned_to']);
    $steps = $stepModel->getByTaskId($taskId);
    Response::success($steps, 201);
}
