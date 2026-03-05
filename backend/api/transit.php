<?php
/**
 * OpsMan – Transit Records API
 * GET                              — list
 * GET ?id=X                        — get single
 * POST                             — create (manager/admin/field_agent)
 * PUT ?id=X                        — update
 * PUT ?id=X&action=update-status   — update status
 * DELETE ?id=X                     — admin only
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/TransitRecord.php';

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
if (!$db) Response::error('Database connection failed', 503);

$user   = authenticate();
$model  = new TransitRecord($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($id) {
            $item = $model->findById($id);
            if (!$item) Response::error('Transit record not found', 404);
            Response::success($item);
        }
        $page    = max(1, (int) ($_GET['page']    ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $filters = [
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];
        $items = $model->list($page, $perPage, $filters);
        $total = $model->countAll($filters);
        Response::success([
            'data'      => $items,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ]);
        break;

    case 'POST':
        if (!in_array($user['role'], ['admin', 'operations_manager', 'field_agent'], true)) {
            Response::error('Insufficient permissions', 403);
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['vehicle_no'])) Response::error("Field 'vehicle_no' is required", 422);
        $newId = $model->create($data);
        if (!$newId) Response::error('Failed to create transit record', 500);
        Response::success(['id' => $newId], 201);
        break;

    case 'PUT':
        if (!$id) Response::error('Transit ID required', 400);
        if (!$model->findById($id)) Response::error('Transit record not found', 404);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'update-status') {
            if (empty($data['status'])) Response::error("Field 'status' is required", 422);
            $model->update($id, ['status' => $data['status'], 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null]);
        } else {
            $model->update($id, $data);
        }
        Response::success(null);
        break;

    case 'DELETE':
        requireAdmin($user);
        if (!$id) Response::error('Transit ID required', 400);
        if (!$model->findById($id)) Response::error('Transit record not found', 404);
        $model->delete($id);
        Response::success(null);
        break;

    default:
        Response::error('Method not allowed', 405);
}
