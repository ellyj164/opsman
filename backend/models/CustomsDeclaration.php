<?php
/**
 * OpsMan – Customs Declaration Model
 */
class CustomsDeclaration {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO customs_declarations
             (shipment_id, declaration_no, declarant_name, hs_codes, invoice_value, currency,
              country_of_origin, port_of_entry, submission_date, status, officer_id, notes, created_by)
             VALUES (:shipment_id,:declaration_no,:declarant_name,:hs_codes,:invoice_value,:currency,
                     :country_of_origin,:port_of_entry,:submission_date,:status,:officer_id,:notes,:created_by)"
        );
        $stmt->execute([
            ':shipment_id'       => $data['shipment_id']       ?? null,
            ':declaration_no'    => $data['declaration_no'],
            ':declarant_name'    => $data['declarant_name'],
            ':hs_codes'          => $data['hs_codes']          ?? null,
            ':invoice_value'     => $data['invoice_value']     ?? null,
            ':currency'          => $data['currency']          ?? 'USD',
            ':country_of_origin' => $data['country_of_origin'] ?? null,
            ':port_of_entry'     => $data['port_of_entry']     ?? null,
            ':submission_date'   => $data['submission_date']   ?? null,
            ':status'            => $data['status']            ?? 'draft',
            ':officer_id'        => $data['officer_id']        ?? null,
            ':notes'             => $data['notes']             ?? null,
            ':created_by'        => $data['created_by']        ?? null,
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT cd.*, s.ref_number AS shipment_ref,
                    CONCAT(e.full_name) AS officer_name
               FROM customs_declarations cd
          LEFT JOIN shipments  s ON s.id = cd.shipment_id
          LEFT JOIN employees  e ON e.id = cd.officer_id
              WHERE cd.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function list(int $page, int $perPage, array $filters = []): array {
        $offset = ($page - 1) * $perPage;
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'cd.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['officer_id'])) {
            $where[] = 'cd.officer_id = :officer_id';
            $params[':officer_id'] = $filters['officer_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(cd.declaration_no LIKE :search OR cd.declarant_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $sql = "SELECT cd.*, s.ref_number AS shipment_ref, e.full_name AS officer_name
                  FROM customs_declarations cd
             LEFT JOIN shipments s ON s.id = cd.shipment_id
             LEFT JOIN employees e ON e.id = cd.officer_id
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY cd.created_at DESC
                 LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM customs_declarations WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool {
        $allowed = ['shipment_id','declarant_name','hs_codes','invoice_value','currency',
                    'country_of_origin','port_of_entry','submission_date','clearance_date',
                    'status','officer_id','notes'];
        $fields = []; $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($fields)) return false;
        $stmt = $this->db->prepare('UPDATE customs_declarations SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM customs_declarations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
