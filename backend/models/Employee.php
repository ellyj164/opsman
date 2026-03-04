<?php
/**
 * OpsMan – Employee Model
 */

class Employee {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO employees (user_id, full_name, employee_code, department, phone, address, profile_photo)
             VALUES (:user_id, :full_name, :employee_code, :department, :phone, :address, :profile_photo)"
        );
        $stmt->execute([
            ':user_id'       => $data['user_id'],
            ':full_name'     => $data['full_name'],
            ':employee_code' => $data['employee_code'],
            ':department'    => $data['department'],
            ':phone'         => $data['phone']         ?? null,
            ':address'       => $data['address']       ?? null,
            ':profile_photo' => $data['profile_photo'] ?? null,
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.username, u.email, u.role, u.is_active
               FROM employees e
               JOIN users u ON u.id = e.user_id
              WHERE e.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function findByUserId(int $userId): array|false {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.username, u.email, u.role
               FROM employees e
               JOIN users u ON u.id = e.user_id
              WHERE e.user_id = :user_id LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch();
    }

    public function list(int $page = 1, int $perPage = 20, array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['department'])) {
            $where[]              = 'e.department = :department';
            $params[':department'] = $filters['department'];
        }
        if (!empty($filters['search'])) {
            $where[]           = '(e.full_name LIKE :search OR e.employee_code LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT e.*, u.username, u.email, u.role, u.is_active
                     FROM employees e
                     JOIN users u ON u.id = e.user_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY e.full_name ASC
                    LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['department'])) {
            $where[]              = 'e.department = :department';
            $params[':department'] = $filters['department'];
        }
        if (!empty($filters['search'])) {
            $where[]           = '(e.full_name LIKE :search OR e.employee_code LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM employees e WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        foreach (['full_name','department','phone','address','profile_photo'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]        = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql  = 'UPDATE employees SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM employees WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function updatePerformanceScore(int $id, float $score): bool {
        $stmt = $this->db->prepare(
            "UPDATE employees SET performance_score = :score WHERE id = :id"
        );
        return $stmt->execute([':score' => $score, ':id' => $id]);
    }
}
