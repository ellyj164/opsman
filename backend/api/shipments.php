<?php
/**
 * OpsMan – Shipments API
 * GET              — list with pagination and filters
 * GET ?id=X        — get single shipment
 * POST             — create (manager/admin)
 * PUT ?id=X        — update (manager/admin)
 * DELETE ?id=X     — delete (admin)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Shipment.php';

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
$model  = new Shipment($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $shipment = $model->findById($id);
            if (!$shipment) Response::error('Shipment not found', 404);
            // Attach related customs declarations and transit records
            $customs = $db->prepare("SELECT cd.id, cd.declaration_no, cd.status, cd.submission_date FROM customs_declarations cd WHERE cd.shipment_id = :sid ORDER BY cd.created_at DESC");
            $customs->execute([':sid' => $id]);
            $shipment['customs_declarations'] = $customs->fetchAll();

            $transits = $db->prepare("SELECT tr.id, tr.vehicle_no, tr.driver_name, tr.status, tr.departure_time, tr.expected_arrival FROM transit_records tr WHERE tr.shipment_id = :sid ORDER BY tr.created_at DESC");
            $transits->execute([':sid' => $id]);
            $shipment['transit_records'] = $transits->fetchAll();

            Response::success($shipment);
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
            'data'       => $items,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int) ceil($total / $perPage),
        ]);
        break;

    case 'POST':
        requireManager($user);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $required = ['ref_number','shipper_name','consignee_name','origin','destination','cargo_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) Response::error("Field '{$field}' is required", 422);
        }
        $newId = $model->create($data);
        if (!$newId) Response::error('Failed to create shipment', 500);
        Response::success(['id' => $newId], 'Shipment created', 201);
        break;

    case 'PUT':
        requireManager($user);
        if (!$id) Response::error('Shipment ID required', 400);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!$model->findById($id)) Response::error('Shipment not found', 404);
        $model->update($id, $data);
        Response::success(null, 'Shipment updated');
        break;

    case 'DELETE':
        requireAdmin($user);
        if (!$id) Response::error('Shipment ID required', 400);
        if (!$model->findById($id)) Response::error('Shipment not found', 404);
        $model->delete($id);
        Response::success(null, 'Shipment deleted');
        break;

    default:
        Response::error('Method not allowed', 405);
}
