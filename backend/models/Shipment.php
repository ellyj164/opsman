<?php
/**
 * OpsMan – Shipment Model (full CRUD)
 */
class Shipment {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO shipments (ref_number, shipper_name, consignee_name, origin, destination,
             cargo_type, cargo_weight, status, client_name, client_email, client_phone,
             assigned_to, created_by, notes)
             VALUES (:ref_number,:shipper_name,:consignee_name,:origin,:destination,
                     :cargo_type,:cargo_weight,:status,:client_name,:client_email,:client_phone,
                     :assigned_to,:created_by,:notes)"
        );
        $stmt->execute([
            ':ref_number'     => $data['ref_number'],
            ':shipper_name'   => $data['shipper_name'],
            ':consignee_name' => $data['consignee_name'],
            ':origin'         => $data['origin'],
            ':destination'    => $data['destination'],
            ':cargo_type'     => $data['cargo_type'],
            ':cargo_weight'   => $data['cargo_weight']  ?? null,
            ':status'         => $data['status']        ?? 'pending',
            ':client_name'    => $data['client_name']   ?? null,
            ':client_email'   => $data['client_email']  ?? null,
            ':client_phone'   => $data['client_phone']  ?? null,
            ':assigned_to'    => $data['assigned_to']   ?? null,
            ':created_by'     => $data['created_by']    ?? null,
            ':notes'          => $data['notes']         ?? null,
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT s.*,
                    ea.full_name AS assigned_name,
                    ec.full_name AS created_by_name
               FROM shipments s
          LEFT JOIN employees ea ON ea.id = s.assigned_to
          LEFT JOIN employees ec ON ec.id = s.created_by
              WHERE s.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function findByRef(string $ref): array|false {
        $stmt = $this->db->prepare("SELECT * FROM shipments WHERE ref_number = :ref LIMIT 1");
        $stmt->execute([':ref' => $ref]);
        return $stmt->fetch();
    }

    public function list(int $page = 1, int $perPage = 20, array $filters = []): array {
        $offset = ($page - 1) * $perPage;
        $where  = ['1=1']; $params = [];
        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(s.ref_number LIKE :search OR s.shipper_name LIKE :search OR s.consignee_name LIKE :search OR s.client_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $sql = "SELECT s.*, ea.full_name AS assigned_name
                  FROM shipments s
             LEFT JOIN employees ea ON ea.id = s.assigned_to
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int {
        $where = ['1=1']; $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(ref_number LIKE :search OR shipper_name LIKE :search OR consignee_name LIKE :search OR client_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM shipments WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool {
        $allowed = ['ref_number','shipper_name','consignee_name','origin','destination',
                    'cargo_type','cargo_weight','status','client_name','client_email',
                    'client_phone','assigned_to','notes'];
        $fields = []; $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) return false;
        return $this->db->prepare('UPDATE shipments SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
    }

    public function delete(int $id): bool {
        return $this->db->prepare("DELETE FROM shipments WHERE id = :id")->execute([':id' => $id]);
    }
}
