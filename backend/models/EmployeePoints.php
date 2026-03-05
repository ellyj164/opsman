<?php
/**
 * OpsMan – EmployeePoints Model
 */

class EmployeePoints {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO employee_points (employee_id, task_id, step_name, points, reason)
             VALUES (:employee_id, :task_id, :step_name, :points, :reason)"
        );
        $stmt->execute([
            ':employee_id' => $data['employee_id'],
            ':task_id'     => $data['task_id']  ?? null,
            ':step_name'   => $data['step_name'] ?? null,
            ':points'      => $data['points'],
            ':reason'      => $data['reason']    ?? null,
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    /**
     * Get total points for an employee.
     */
    public function getTotalPoints(int $employeeId): int {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM employee_points WHERE employee_id = :emp_id"
        );
        $stmt->execute([':emp_id' => $employeeId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get points breakdown for an employee.
     */
    public function getByEmployee(int $employeeId, int $page = 1, int $perPage = 50): array {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT ep.*, t.title AS task_title, t.ref AS task_ref
               FROM employee_points ep
               LEFT JOIN tasks t ON t.id = ep.task_id
              WHERE ep.employee_id = :emp_id
              ORDER BY ep.created_at DESC
              LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':emp_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get leaderboard (all employees ranked by total points).
     */
    public function getLeaderboard(?string $month = null): array {
        $where  = '';
        $params = [];

        if ($month) {
            $where = 'WHERE DATE_FORMAT(ep.created_at, "%Y-%m") = :month';
            $params[':month'] = $month;
        }

        $sql = "SELECT e.id AS employee_id, e.full_name, e.employee_code, e.department,
                       COALESCE(SUM(ep.points), 0) AS total_points,
                       COUNT(ep.id) AS tasks_scored
                  FROM employees e
                  LEFT JOIN employee_points ep ON ep.employee_id = e.id
                  {$where}
                  GROUP BY e.id, e.full_name, e.employee_code, e.department
                  ORDER BY total_points DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get step contribution breakdown for an employee.
     */
    public function getStepContribution(int $employeeId): array {
        $stmt = $this->db->prepare(
            "SELECT step_name, SUM(points) AS total_points, COUNT(*) AS times_completed
               FROM employee_points
              WHERE employee_id = :emp_id AND step_name IS NOT NULL
              GROUP BY step_name
              ORDER BY total_points DESC"
        );
        $stmt->execute([':emp_id' => $employeeId]);
        return $stmt->fetchAll();
    }

    /**
     * Get rank for an employee.
     */
    public function getRank(int $employeeId): int {
        $leaderboard = $this->getLeaderboard();
        foreach ($leaderboard as $i => $row) {
            if ((int) $row['employee_id'] === $employeeId) {
                return $i + 1;
            }
        }
        return 0;
    }
}
