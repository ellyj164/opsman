<?php
/**
 * OpsMan – Warehouses API
 * GET                              — list warehouses
 * GET ?id=X                        — get single warehouse
 * GET ?action=records&warehouse_id=X — get records for warehouse
 * POST                             — create warehouse (manager/admin)
 * POST ?action=record              — add warehouse record
 * PUT ?id=X                        — update warehouse
 * DELETE ?id=X                     — delete (admin)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Warehouse.php';

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
$model  = new Warehouse($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'records') {
            $wid = isset($_GET['warehouse_id']) ? (int) $_GET['warehouse_id'] : 0;
            if (!$wid) Response::error('warehouse_id required', 400);
            $page    = max(1, (int) ($_GET['page']    ?? 1));
            $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
            Response::success($model->listRecords($wid, $page, $perPage));
        }
        if ($id) {
            $warehouse = $model->findById($id);
            if (!$warehouse) Response::error('Warehouse not found', 404);
            Response::success($warehouse);
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
        if ($action === 'record') {
            requireWarehouseOfficer($user);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($data['warehouse_id'])) Response::error("Field 'warehouse_id' is required", 422);
            $newId = $model->createRecord($data);
            if (!$newId) Response::error('Failed to create record', 500);
            Response::success(['id' => $newId], 201);
        }
        requireManager($user);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['name'])) Response::error("Field 'name' is required", 422);
        if (empty($data['code'])) Response::error("Field 'code' is required", 422);
        $newId = $model->create($data);
        if (!$newId) Response::error('Failed to create warehouse', 500);
        Response::success(['id' => $newId], 201);
        break;

    case 'PUT':
        requireManager($user);
        if (!$id) Response::error('Warehouse ID required', 400);
        if (!$model->findById($id)) Response::error('Warehouse not found', 404);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $model->update($id, $data);
        Response::success(null);
        break;

    case 'DELETE':
        requireAdmin($user);
        if (!$id) Response::error('Warehouse ID required', 400);
        if (!$model->findById($id)) Response::error('Warehouse not found', 404);
        $model->delete($id);
        Response::success(null);
        break;

    default:
        Response::error('Method not allowed', 405);
}
