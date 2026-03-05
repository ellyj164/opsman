<?php
/**
 * OpsMan – Notification Model
 */

class Notification {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, message, type, read_status)
             VALUES (:user_id, :message, :type, :read_status)"
        );
        $stmt->execute([
            ':user_id'     => $data['user_id'],
            ':message'     => $data['message'],
            ':type'        => $data['type']        ?? 'info',
            ':read_status' => $data['read_status'] ?? 0,
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function listByUser(int $userId, int $page = 1, int $perPage = 20, ?bool $unreadOnly = null): array {
        $where  = ['user_id = :user_id'];
        $params = [':user_id' => $userId];

        if ($unreadOnly === true) {
            $where[] = 'read_status = 0';
        }

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM notifications WHERE " . implode(' AND ', $where)
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

    public function countByUser(int $userId, ?bool $unreadOnly = null): int {
        $where  = ['user_id = :user_id'];
        $params = [':user_id' => $userId];

        if ($unreadOnly === true) {
            $where[] = 'read_status = 0';
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM notifications WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $id): bool {
        $stmt = $this->db->prepare("UPDATE notifications SET read_status = 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function markAllReadForUser(int $userId): bool {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET read_status = 1 WHERE user_id = :user_id AND read_status = 0"
        );
        return $stmt->execute([':user_id' => $userId]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function countUnreadForUser(int $userId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_status = 0"
        );
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
