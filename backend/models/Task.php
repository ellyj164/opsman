<?php
/**
 * OpsMan – Task Model
 */

class Task {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO tasks
                (title, description, task_type, assigned_to, assigned_by,
                 location, shipment_ref, deadline, priority, status)
             VALUES
                (:title, :description, :task_type, :assigned_to, :assigned_by,
                 :location, :shipment_ref, :deadline, :priority, :status)"
        );
        $stmt->execute([
            ':title'        => $data['title'],
            ':description'  => $data['description']  ?? null,
            ':task_type'    => $data['task_type'],
            ':assigned_to'  => $data['assigned_to']  ?? null,
            ':assigned_by'  => $data['assigned_by']  ?? null,
            ':location'     => $data['location']     ?? null,
            ':shipment_ref' => $data['shipment_ref'] ?? null,
            ':deadline'     => $data['deadline']     ?? null,
            ':priority'     => $data['priority']     ?? 'medium',
            ':status'       => $data['status']       ?? 'pending',
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT t.*,
                    ea.full_name AS assigned_to_name,
                    eb.full_name AS assigned_by_name
               FROM tasks t
               LEFT JOIN employees ea ON ea.id = t.assigned_to
               LEFT JOIN employees eb ON eb.id = t.assigned_by
              WHERE t.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function list(int $page = 1, int $perPage = 20, array $filters = []): array {
        [$where, $params] = $this->buildFilters($filters);

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT t.*,
                          ea.full_name AS assigned_to_name,
                          eb.full_name AS assigned_by_name
                     FROM tasks t
                     LEFT JOIN employees ea ON ea.id = t.assigned_to
                     LEFT JOIN employees eb ON eb.id = t.assigned_by
                    WHERE {$where}
                    ORDER BY t.created_at DESC
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
        [$where, $params] = $this->buildFilters($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tasks t WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listByEmployee(int $employeeId, int $page = 1, int $perPage = 20): array {
        $filters = ['assigned_to' => $employeeId];
        return $this->list($page, $perPage, $filters);
    }

    public function update(int $id, array $data): bool {
        $allowed = ['title','description','task_type','assigned_to','assigned_by',
                    'location','shipment_ref','deadline','priority','status'];
        $fields  = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]        = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql  = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare(
            "UPDATE tasks SET status = :status WHERE id = :id"
        );
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ------------------------------------------------------------------

    private function buildFilters(array $f): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($f['status'])) {
            $where[]          = 't.status = :status';
            $params[':status'] = $f['status'];
        }
        if (!empty($f['priority'])) {
            $where[]            = 't.priority = :priority';
            $params[':priority'] = $f['priority'];
        }
        if (!empty($f['task_type'])) {
            $where[]              = 't.task_type = :task_type';
            $params[':task_type'] = $f['task_type'];
        }
        if (!empty($f['assigned_to'])) {
            $where[]               = 't.assigned_to = :assigned_to';
            $params[':assigned_to'] = $f['assigned_to'];
        }
        if (!empty($f['search'])) {
            $where[]           = '(t.title LIKE :search OR t.shipment_ref LIKE :search)';
            $params[':search'] = '%' . $f['search'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
