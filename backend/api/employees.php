<?php
/**
 * OpsMan – Employees API
 * GET              — list all employees
 * GET   ?id=X      — get employee
 * POST             — create employee + user account
 * PUT   ?id=X      — update employee
 * DELETE ?id=X     — delete employee (admin)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Employee.php';

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
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$empModel  = new Employee($db);
$userModel = new User($db);

switch ($method) {
    case 'GET':
        if ($id) {
            getEmployee($empModel, $id, $user);
        }
        listEmployees($empModel, $user);
        break;

    case 'POST':
        requireManager($user);
        createEmployee($db, $empModel, $userModel, $body);
        break;

    case 'PUT':
        if (!$id) {
            Response::error('Employee id required', 400);
        }
        requireManager($user);
        updateEmployee($empModel, $userModel, $id, $body);
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('Employee id required', 400);
        }
        requireAdmin($user);
        deleteEmployee($empModel, $userModel, $id);
        break;

    default:
        Response::error('Method not allowed', 405);
}

// ── Handlers ──────────────────────────────────────────────────────────

function listEmployees(Employee $empModel, array $user): never {
    $page    = max(1, (int) ($_GET['page']    ?? 1));
    $perPage = min((int) ($_GET['per_page'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    $filters = [
        'department' => $_GET['department'] ?? '',
        'search'     => $_GET['search']     ?? '',
    ];

    // Field employees can only see their own profile
    if ($user['role'] === 'field_employee') {
        $emp = $empModel->findByUserId($user['id']);
        Response::success($emp ?: null);
    }

    $items = $empModel->list($page, $perPage, $filters);
    $total = $empModel->countAll($filters);
    Response::paginated($items, $total, $page, $perPage);
}

function getEmployee(Employee $empModel, int $id, array $user): never {
    // Field employees may only view their own profile
    if ($user['role'] === 'field_employee') {
        $own = $empModel->findByUserId($user['id']);
        if (!$own || (int) $own['id'] !== $id) {
            Response::error('Access denied', 403);
        }
    }
    $emp = $empModel->findById($id);
    if (!$emp) {
        Response::error('Employee not found', 404);
    }
    Response::success($emp);
}

function createEmployee(PDO $db, Employee $empModel, User $userModel, array $body): never {
    $v = new Validator();
    $v->required('full_name',      $body['full_name']      ?? '')
      ->required('employee_code',  $body['employee_code']  ?? '')
      ->required('department',     $body['department']     ?? '')
      ->required('username',       $body['username']       ?? '')
      ->required('email',          $body['email']          ?? '')
      ->email('email',             $body['email']          ?? '')
      ->required('password',       $body['password']       ?? '')
      ->minLength('password',      $body['password']       ?? '', 8);
    if ($v->fails()) {
        Response::error(implode('; ', $v->errors()), 422);
    }

    // Check uniqueness
    if ($userModel->findByUsername($body['username'])) {
        Response::error('Username already exists', 409);
    }
    if ($userModel->findByEmail($body['email'])) {
        Response::error('Email already in use', 409);
    }

    $db->beginTransaction();
    try {
        $userId = $userModel->create([
            'username' => $body['username'],
            'email'    => $body['email'],
            'password' => $body['password'],
            'role'     => $body['role'] ?? 'field_employee',
        ]);

        $empId = $empModel->create([
            'user_id'       => $userId,
            'full_name'     => $body['full_name'],
            'employee_code' => $body['employee_code'],
            'department'    => $body['department'],
            'phone'         => $body['phone']   ?? null,
            'address'       => $body['address'] ?? null,
        ]);

        $db->commit();
        Response::success($empModel->findById($empId), 201);
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to create employee: ' . $e->getMessage(), 500);
    }
}

function updateEmployee(Employee $empModel, User $userModel, int $id, array $body): never {
    $emp = $empModel->findById($id);
    if (!$emp) {
        Response::error('Employee not found', 404);
    }

    $empModel->update($id, array_intersect_key($body, array_flip(
        ['full_name','department','phone','address','profile_photo']
    )));

    // Also allow updating user role / is_active
    if (isset($body['role']) || isset($body['is_active'])) {
        $userModel->update($emp['user_id'], array_intersect_key($body, array_flip(['role','is_active'])));
    }

    Response::success($empModel->findById($id));
}

function deleteEmployee(Employee $empModel, User $userModel, int $id): never {
    $emp = $empModel->findById($id);
    if (!$emp) {
        Response::error('Employee not found', 404);
    }
    // Deleting the user cascades to employee
    $userModel->delete($emp['user_id']);
    Response::success(['message' => 'Employee deleted']);
}
