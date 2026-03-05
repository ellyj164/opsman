<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
/**
 * OpsMan – Customs Declarations API
 * GET                         — list
 * GET ?id=X                   — get single
 * POST                        — create (customs_officer/manager/admin)
 * PUT ?id=X                   — update
 * PUT ?id=X&action=update-status — status only
 * DELETE ?id=X                — admin only
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/CustomsDeclaration.php';

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
$model  = new CustomsDeclaration($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($id) {
            $item = $model->findById($id);
            if (!$item) Response::error('Declaration not found', 404);
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
        requireCustomsOfficer($user);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['declaration_no'])) Response::error("Field 'declaration_no' is required", 422);
        if (empty($data['declarant_name'])) Response::error("Field 'declarant_name' is required", 422);
        $newId = $model->create($data);
        if (!$newId) Response::error('Failed to create declaration', 500);
        Response::success(['id' => $newId], 'Declaration created', 201);
        break;

    case 'PUT':
        if (!$id) Response::error('Declaration ID required', 400);
        if (!$model->findById($id)) Response::error('Declaration not found', 404);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'update-status') {
            if (empty($data['status'])) Response::error("Field 'status' is required", 422);
            $model->update($id, ['status' => $data['status']]);
        } else {
            $model->update($id, $data);
        }
        Response::success(null, 'Declaration updated');
        break;

    case 'DELETE':
        requireAdmin($user);
        if (!$id) Response::error('Declaration ID required', 400);
        if (!$model->findById($id)) Response::error('Declaration not found', 404);
        $model->delete($id);
        Response::success(null, 'Declaration deleted');
        break;

    default:
        Response::error('Method not allowed', 405);
}
