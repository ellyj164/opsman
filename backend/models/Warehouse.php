<?php
/**
 * OpsMan – Warehouse Model
 */
class Warehouse {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO warehouses (name, code, address, city, country, latitude, longitude, capacity_sqm, manager_id, status)
             VALUES (:name,:code,:address,:city,:country,:latitude,:longitude,:capacity_sqm,:manager_id,:status)"
        );
        $stmt->execute([
            ':name'         => $data['name'],
            ':code'         => $data['code'],
            ':address'      => $data['address']      ?? null,
            ':city'         => $data['city']         ?? null,
            ':country'      => $data['country']      ?? null,
            ':latitude'     => $data['latitude']     ?? null,
            ':longitude'    => $data['longitude']    ?? null,
            ':capacity_sqm' => $data['capacity_sqm'] ?? null,
            ':manager_id'   => $data['manager_id']   ?? null,
            ':status'       => $data['status']       ?? 'active',
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT w.*, e.full_name AS manager_name
               FROM warehouses w
          LEFT JOIN employees e ON e.id = w.manager_id
              WHERE w.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function list(int $page = 1, int $perPage = 20, array $filters = []): array {
        $offset = ($page - 1) * $perPage;
        $where  = ['1=1']; $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'w.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(w.name LIKE :search OR w.code LIKE :search OR w.city LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $sql = "SELECT w.*, e.full_name AS manager_name
                  FROM warehouses w
             LEFT JOIN employees e ON e.id = w.manager_id
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY w.name ASC LIMIT :limit OFFSET :offset";
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
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM warehouses WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool {
        $allowed = ['name','code','address','city','country','latitude','longitude','capacity_sqm','manager_id','status'];
        $fields = []; $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) return false;
        return $this->db->prepare('UPDATE warehouses SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
    }

    public function delete(int $id): bool {
        return $this->db->prepare("DELETE FROM warehouses WHERE id = :id")->execute([':id' => $id]);
    }

    public function createRecord(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_records
             (warehouse_id, shipment_id, record_type, cargo_description, quantity, unit,
              weight_kg, condition_status, inspector_id, inspection_date, notes)
             VALUES (:warehouse_id,:shipment_id,:record_type,:cargo_description,:quantity,:unit,
                     :weight_kg,:condition_status,:inspector_id,:inspection_date,:notes)"
        );
        $stmt->execute([
            ':warehouse_id'      => $data['warehouse_id'],
            ':shipment_id'       => $data['shipment_id']       ?? null,
            ':record_type'       => $data['record_type']       ?? 'arrival',
            ':cargo_description' => $data['cargo_description'] ?? null,
            ':quantity'          => $data['quantity']          ?? null,
            ':unit'              => $data['unit']              ?? null,
            ':weight_kg'         => $data['weight_kg']         ?? null,
            ':condition_status'  => $data['condition_status']  ?? 'pending',
            ':inspector_id'      => $data['inspector_id']      ?? null,
            ':inspection_date'   => $data['inspection_date']   ?? null,
            ':notes'             => $data['notes']             ?? null,
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function listRecords(int $warehouseId, int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT wr.*, s.ref_number AS shipment_ref, e.full_name AS inspector_name
               FROM warehouse_records wr
          LEFT JOIN shipments  s ON s.id = wr.shipment_id
          LEFT JOIN employees  e ON e.id = wr.inspector_id
              WHERE wr.warehouse_id = :wid
           ORDER BY wr.created_at DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':wid',    $warehouseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $perPage,     PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,      PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
