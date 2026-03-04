<?php
/**
 * OpsMan – Task Report Model
 */

class Report {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO task_reports (task_id, employee_id, notes, observations, status)
             VALUES (:task_id, :employee_id, :notes, :observations, :status)"
        );
        $stmt->execute([
            ':task_id'     => $data['task_id'],
            ':employee_id' => $data['employee_id'],
            ':notes'       => $data['notes']        ?? null,
            ':observations'=> $data['observations'] ?? null,
            ':status'      => $data['status']       ?? 'draft',
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT r.*, e.full_name AS employee_name, t.title AS task_title
               FROM task_reports r
               JOIN employees e ON e.id = r.employee_id
               JOIN tasks     t ON t.id = r.task_id
              WHERE r.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function findByTaskId(int $taskId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, e.full_name AS employee_name
               FROM task_reports r
               JOIN employees e ON e.id = r.employee_id
              WHERE r.task_id = :task_id
              ORDER BY r.created_at DESC"
        );
        $stmt->execute([':task_id' => $taskId]);
        return $stmt->fetchAll();
    }

    public function findByEmployeeId(int $employeeId, int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->db->prepare(
            "SELECT r.*, t.title AS task_title
               FROM task_reports r
               JOIN tasks t ON t.id = r.task_id
              WHERE r.employee_id = :employee_id
              ORDER BY r.created_at DESC
              LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',       $perPage,    PDO::PARAM_INT);
        $stmt->bindValue(':offset',      $offset,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function list(int $page = 1, int $perPage = 20, array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[]               = 'r.employee_id = :employee_id';
            $params[':employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['task_id'])) {
            $where[]           = 'r.task_id = :task_id';
            $params[':task_id'] = $filters['task_id'];
        }
        if (!empty($filters['status'])) {
            $where[]          = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]             = 'r.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]           = 'r.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT r.*, e.full_name AS employee_name, t.title AS task_title
                     FROM task_reports r
                     JOIN employees e ON e.id = r.employee_id
                     JOIN tasks     t ON t.id = r.task_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY r.created_at DESC
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
        if (!empty($filters['employee_id'])) {
            $where[]               = 'r.employee_id = :employee_id';
            $params[':employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['task_id'])) {
            $where[]           = 'r.task_id = :task_id';
            $params[':task_id'] = $filters['task_id'];
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM task_reports r WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        foreach (['notes','observations','status'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]        = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE task_reports SET ' . implode(', ', $fields) . ' WHERE id = :id'
        );
        return $stmt->execute($params);
    }

    public function checkIn(int $id, float $lat, float $lng): bool {
        $stmt = $this->db->prepare(
            "UPDATE task_reports
                SET check_in_time = NOW(),
                    check_in_lat  = :lat,
                    check_in_lng  = :lng
              WHERE id = :id"
        );
        return $stmt->execute([':lat' => $lat, ':lng' => $lng, ':id' => $id]);
    }

    public function checkOut(int $id, float $lat, float $lng): bool {
        $stmt = $this->db->prepare(
            "UPDATE task_reports
                SET check_out_time = NOW(),
                    check_out_lat  = :lat,
                    check_out_lng  = :lng
              WHERE id = :id"
        );
        return $stmt->execute([':lat' => $lat, ':lng' => $lng, ':id' => $id]);
    }
}
