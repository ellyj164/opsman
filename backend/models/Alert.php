<?php
/**
 * OpsMan – Alert Model
 */

class Alert {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO alerts (type, title, message, related_to, related_id, severity)
             VALUES (:type, :title, :message, :related_to, :related_id, :severity)"
        );
        $stmt->execute([
            ':type'       => $data['type'],
            ':title'      => $data['title'],
            ':message'    => $data['message'],
            ':related_to' => $data['related_to'] ?? null,
            ':related_id' => $data['related_id'] ?? null,
            ':severity'   => $data['severity']   ?? 'info',
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM alerts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function list(int $page = 1, int $perPage = 20, array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (isset($filters['is_read'])) {
            $where[]          = 'is_read = :is_read';
            $params[':is_read'] = (int) $filters['is_read'];
        }
        if (!empty($filters['severity'])) {
            $where[]            = 'severity = :severity';
            $params[':severity'] = $filters['severity'];
        }
        if (!empty($filters['related_to'])) {
            $where[]              = 'related_to = :related_to';
            $params[':related_to'] = $filters['related_to'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT * FROM alerts WHERE " . implode(' AND ', $where)
                . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

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
        if (isset($filters['is_read'])) {
            $where[]          = 'is_read = :is_read';
            $params[':is_read'] = (int) $filters['is_read'];
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM alerts WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $id): bool {
        $stmt = $this->db->prepare("UPDATE alerts SET is_read = 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function markAllRead(): bool {
        return (bool) $this->db->exec("UPDATE alerts SET is_read = 1 WHERE is_read = 0");
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM alerts WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function countUnread(): int {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM alerts WHERE is_read = 0"
        )->fetchColumn();
    }
}
