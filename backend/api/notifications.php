<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
/**
 * OpsMan – Notifications API
 * GET                         — list notifications for current user
 * GET    ?id=X                — get single notification
 * PUT    ?id=X&action=read    — mark notification as read
 * PUT    ?action=read-all     — mark all as read
 * DELETE ?id=X                — delete notification
 * GET    ?action=unread-count — get unread count
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Notification.php';

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

$notifModel = new Notification($db);

switch ($method) {
    case 'GET':
        if ($action === 'unread-count') {
            getUnreadCount($notifModel, $user);
        }
        if ($id) {
            getNotification($notifModel, $id, $user);
        }
        listNotifications($notifModel, $user);
        break;

    case 'PUT':
        if ($action === 'read-all') {
            markAllRead($notifModel, $user);
        }
        if ($id && $action === 'read') {
            markRead($notifModel, $id, $user);
        }
        Response::error('Invalid parameters', 400);
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('Notification id required', 400);
        }
        deleteNotification($notifModel, $id, $user);
        break;

    default:
        Response::error('Method not allowed', 405);
}

// ── Handlers ──────────────────────────────────────────────────────────

function listNotifications(Notification $model, array $user): never {
    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min((int) ($_GET['per_page']  ?? DEFAULT_PAGE_SIZE), MAX_PAGE_SIZE);
    $unread  = isset($_GET['unread']) ? true : null;

    $items = $model->listByUser($user['id'], $page, $perPage, $unread);
    $total = $model->countByUser($user['id'], $unread);
    Response::paginated($items, $total, $page, $perPage);
}

function getNotification(Notification $model, int $id, array $user): never {
    $notif = $model->findById($id);
    if (!$notif || (int) $notif['user_id'] !== (int) $user['id']) {
        Response::error('Notification not found', 404);
    }
    Response::success($notif);
}

function markRead(Notification $model, int $id, array $user): never {
    $notif = $model->findById($id);
    if (!$notif || (int) $notif['user_id'] !== (int) $user['id']) {
        Response::error('Notification not found', 404);
    }
    $model->markRead($id);
    Response::success(['message' => 'Notification marked as read']);
}

function markAllRead(Notification $model, array $user): never {
    $model->markAllReadForUser($user['id']);
    Response::success(['message' => 'All notifications marked as read']);
}

function deleteNotification(Notification $model, int $id, array $user): never {
    $notif = $model->findById($id);
    if (!$notif || (int) $notif['user_id'] !== (int) $user['id']) {
        Response::error('Notification not found', 404);
    }
    $model->delete($id);
    Response::success(['message' => 'Notification deleted']);
}

function getUnreadCount(Notification $model, array $user): never {
    $count = $model->countUnreadForUser($user['id']);
    Response::success(['unread_count' => $count]);
}
