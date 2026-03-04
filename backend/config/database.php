<?php
/**
 * OpsMan – Database Configuration
 * PDO connection factory using mysql:charset=utf8mb4
 */

class Database {
    private string $host     = 'localhost';
    private string $db_name  = 'opsman';
    private string $username = 'root';
    private string $password = '';
    private ?PDO   $conn     = null;

    public function getConnection(): ?PDO {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);
        } catch (PDOException $e) {
            // Intentionally swallowed; callers check for null
            error_log('DB connection failed: ' . $e->getMessage());
        }
        return $this->conn;
    }
}
