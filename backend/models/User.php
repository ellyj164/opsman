<?php
/**
 * OpsMan – User Model
 */

class User {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO users (username, email, password_hash, role)
             VALUES (:username, :email, :password_hash, :role)"
        );
        $stmt->execute([
            ':username'      => $data['username'],
            ':email'         => $data['email'],
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'          => $data['role'] ?? 'field_employee',
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findByUsername(string $username): array|false {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([':username' => $username]);
        return $stmt->fetch();
    }

    public function findByEmail(string $email): array|false {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT id, username, email, role, is_active, created_at, updated_at
               FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function updateToken(int $id, string $token, string $expiresAt): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET token = :token, token_expires_at = :expires WHERE id = :id"
        );
        return $stmt->execute([':token' => $token, ':expires' => $expiresAt, ':id' => $id]);
    }

    public function clearToken(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET token = NULL, token_expires_at = NULL WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    public function updatePassword(int $id, string $newPassword): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET password_hash = :hash WHERE id = :id"
        );
        return $stmt->execute([
            ':hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':id'   => $id,
        ]);
    }

    public function list(int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->db->prepare(
            "SELECT id, username, email, role, is_active, created_at
               FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(): int {
        return (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        foreach (['username', 'email', 'role', 'is_active'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]      = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql  = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
