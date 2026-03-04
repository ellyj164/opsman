<?php
/**
 * OpsMan – Alerts API
 * GET              — list alerts
 * GET ?id=X        — get alert
 * PUT ?id=X&action=read — mark as read
 * PUT ?action=read-all  — mark all as read
 * DELETE ?id=X     — delete (admin)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Alert.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
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

$user       = authenticate();
$method     = $_SERVER['REQUEST_METHOD'];
$id         = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action     = $_GET['action']    ?? '';
$alertModel = new Alert($db);

switch ($method) {
    case 'GET':
        if ($id) {
            $alert = $alertModel->findById($id);
            if (!$alert) {
                Response::error('Alert not found', 404);
            }
            Response::success($alert);
        }
        $page    = max(1, (int) ($_GET['page']    ?? 1));
        $perPage = min((int) ($_GET['per_page'] ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
        $filters = [
            'severity'   => $_GET['severity']   ?? '',
            'related_to' => $_GET['related_to'] ?? '',
        ];
        if (isset($_GET['is_read'])) {
            $filters['is_read'] = (int) $_GET['is_read'];
        }
        $items = $alertModel->list($page, $perPage, $filters);
        $total = $alertModel->countAll($filters);
        Response::paginated($items, $total, $page, $perPage);
        break;

    case 'PUT':
        if ($action === 'read-all') {
            $alertModel->markAllRead();
            Response::success(['message' => 'All alerts marked as read']);
        }
        if ($id && $action === 'read') {
            $alertModel->markRead($id);
            Response::success($alertModel->findById($id));
        }
        Response::error('Invalid PUT action', 400);
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('Alert id required', 400);
        }
        requireAdmin($user);
        $alert = $alertModel->findById($id);
        if (!$alert) {
            Response::error('Alert not found', 404);
        }
        $alertModel->delete($id);
        Response::success(['message' => 'Alert deleted']);
        break;

    default:
        Response::error('Method not allowed', 405);
}
