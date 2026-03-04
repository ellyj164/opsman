<?php
/**
 * OpsMan – File Uploads API
 * POST             — upload file (photo/document)
 * GET ?report_id=X — list files for a report
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Employee.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

if ($method === 'GET') {
    $reportId = isset($_GET['report_id']) ? (int) $_GET['report_id'] : null;
    if (!$reportId) {
        Response::error('report_id required', 400);
    }
    $stmt = $db->prepare(
        "SELECT d.*, e.full_name AS employee_name
           FROM documents d
           JOIN employees e ON e.id = d.employee_id
          WHERE d.task_report_id = :rid
          ORDER BY d.uploaded_at DESC"
    );
    $stmt->execute([':rid' => $reportId]);
    Response::success($stmt->fetchAll());
}

if ($method === 'POST') {
    if (empty($_FILES['file'])) {
        Response::error('No file uploaded', 400);
    }

    $reportId = isset($_POST['report_id']) ? (int) $_POST['report_id'] : null;
    if (!$reportId) {
        Response::error('report_id is required', 422);
    }

    $fileCheck = Validator::validateFile($_FILES['file'], ALLOWED_EXTENSIONS, MAX_FILE_SIZE);
    if (!$fileCheck['valid']) {
        Response::error($fileCheck['error'], 422);
    }

    // Ensure upload directory exists
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $ext      = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = UPLOAD_DIR . $safeName;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
        Response::error('Failed to save uploaded file', 500);
    }

    $empModel = new Employee($db);
    $emp      = $empModel->findByUserId($user['id']);
    if (!$emp) {
        Response::error('Employee profile not found', 404);
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $destPath);
    finfo_close($finfo);

    $stmt = $db->prepare(
        "INSERT INTO documents (task_report_id, employee_id, file_name, file_path, file_type, file_size)
         VALUES (:rid, :eid, :name, :path, :type, :size)"
    );
    $stmt->execute([
        ':rid'  => $reportId,
        ':eid'  => $emp['id'],
        ':name' => $_FILES['file']['name'],
        ':path' => 'uploads/' . $safeName,
        ':type' => $mimeType,
        ':size' => $_FILES['file']['size'],
    ]);

    $docId = (int) $db->lastInsertId();
    $doc   = $db->prepare("SELECT * FROM documents WHERE id = :id");
    $doc->execute([':id' => $docId]);
    Response::success($doc->fetch(), 201);
}

Response::error('Method not allowed', 405);
