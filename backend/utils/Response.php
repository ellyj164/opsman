<?php
/**
 * OpsMan – JSON Response Helper
 */

class Response {

    /**
     * Send a successful JSON response.
     *
     * @param mixed  $data    Payload to encode
     * @param int    $status  HTTP status code (default 200)
     */
    public static function success(mixed $data = null, int $status = 200): never {
        self::send(['success' => true, 'data' => $data], $status);
    }

    /**
     * Send an error JSON response.
     *
     * @param string $message Human-readable error message
     * @param int    $status  HTTP status code (default 400)
     */
    public static function error(string $message, int $status = 400): never {
        self::send(['success' => false, 'error' => $message], $status);
    }

    /**
     * Send a paginated JSON response.
     *
     * @param array $items   Page items
     * @param int   $total   Total record count
     * @param int   $page    Current page (1-based)
     * @param int   $perPage Items per page
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): never {
        self::send([
            'success'     => true,
            'data'        => $items,
            'pagination'  => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => (int) ceil($total / max($perPage, 1)),
            ],
        ], 200);
    }

    // ------------------------------------------------------------------

    private static function send(array $body, int $status): never {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');

            // CORS
            $origin = defined('CORS_ORIGINS') ? CORS_ORIGINS : '*';
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
