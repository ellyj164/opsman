<?php
/**
 * OpsMan – Transit Record Model
 */
class TransitRecord {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO transit_records
             (shipment_id, vehicle_no, driver_name, driver_phone, origin_border, destination_border,
              departure_time, expected_arrival, status, supervisor_id, latitude, longitude, notes)
             VALUES (:shipment_id,:vehicle_no,:driver_name,:driver_phone,:origin_border,:destination_border,
                     :departure_time,:expected_arrival,:status,:supervisor_id,:latitude,:longitude,:notes)"
        );
        $stmt->execute([
            ':shipment_id'        => $data['shipment_id']        ?? null,
            ':vehicle_no'         => $data['vehicle_no'],
            ':driver_name'        => $data['driver_name']        ?? null,
            ':driver_phone'       => $data['driver_phone']       ?? null,
            ':origin_border'      => $data['origin_border']      ?? null,
            ':destination_border' => $data['destination_border'] ?? null,
            ':departure_time'     => $data['departure_time']     ?? null,
            ':expected_arrival'   => $data['expected_arrival']   ?? null,
            ':status'             => $data['status']             ?? 'scheduled',
            ':supervisor_id'      => $data['supervisor_id']      ?? null,
            ':latitude'           => $data['latitude']           ?? null,
            ':longitude'          => $data['longitude']          ?? null,
            ':notes'              => $data['notes']              ?? null,
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT tr.*, s.ref_number AS shipment_ref, e.full_name AS supervisor_name
               FROM transit_records tr
          LEFT JOIN shipments  s ON s.id = tr.shipment_id
          LEFT JOIN employees  e ON e.id = tr.supervisor_id
              WHERE tr.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function list(int $page, int $perPage, array $filters = []): array {
        $offset = ($page - 1) * $perPage;
        $where  = ['1=1']; $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'tr.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(tr.vehicle_no LIKE :search OR tr.driver_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $sql = "SELECT tr.*, s.ref_number AS shipment_ref, e.full_name AS supervisor_name
                  FROM transit_records tr
             LEFT JOIN shipments  s ON s.id = tr.shipment_id
             LEFT JOIN employees  e ON e.id = tr.supervisor_id
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY tr.created_at DESC LIMIT :limit OFFSET :offset";
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
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM transit_records WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool {
        $allowed = ['shipment_id','vehicle_no','driver_name','driver_phone','origin_border',
                    'destination_border','departure_time','expected_arrival','actual_arrival',
                    'border_entry_time','border_exit_time','status','delay_reason',
                    'supervisor_id','latitude','longitude','notes'];
        $fields = []; $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) return false;
        return $this->db->prepare('UPDATE transit_records SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
    }

    public function delete(int $id): bool {
        return $this->db->prepare("DELETE FROM transit_records WHERE id = :id")->execute([':id' => $id]);
    }
}
