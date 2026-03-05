<?php
/**
 * OpsMan – TaskStep Model
 * Manages workflow steps for import/export tasks.
 */

class TaskStep {
    private PDO $db;

    /** IMPORT IM4 workflow steps */
    public const IMPORT_STEPS = [
        'Arrival Notice correction',
        'Declaration',
        'Pre-Physical Verification',
        'Physical Verification',
        'Release Request',
        'Warehouse Payment Processes',
        'Release Request',
        'Warehouse Payment Procedures',
        'Telling/Exit',
    ];

    /** EXPORT EX1 workflow steps */
    public const EXPORT_STEPS = [
        'Driver application / Badge request',
        'Pallets correction',
        'Offloading',
        'Declaration',
        'Attachments',
        'Documents submission',
        'Exiting',
    ];

    /** Points per step name */
    public const STEP_POINTS = [
        'Arrival Notice correction'        => 5,
        'Declaration'                       => 5,
        'Pre-Physical Verification'         => 8,
        'Physical Verification'             => 15,
        'Release Request'                   => 10,
        'Warehouse Payment Processes'       => 8,
        'Warehouse Payment Procedures'      => 8,
        'Telling/Exit'                      => 5,
        'Driver application / Badge request'=> 5,
        'Pallets correction'                => 5,
        'Offloading'                        => 8,
        'Attachments'                       => 5,
        'Documents submission'              => 5,
        'Exiting'                           => 5,
    ];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Create a single step.
     */
    public function create(array $data): int|false {
        $stmt = $this->db->prepare(
            "INSERT INTO task_steps (task_id, step_number, step_name, assigned_to, status)
             VALUES (:task_id, :step_number, :step_name, :assigned_to, :status)"
        );
        $stmt->execute([
            ':task_id'     => $data['task_id'],
            ':step_number' => $data['step_number'],
            ':step_name'   => $data['step_name'],
            ':assigned_to' => $data['assigned_to'] ?? null,
            ':status'      => $data['status']      ?? 'pending',
        ]);
        return (int) $this->db->lastInsertId() ?: false;
    }

    /**
     * Create all workflow steps for a task.
     */
    public function createWorkflowSteps(int $taskId, string $taskType, ?int $assignedTo = null): array {
        $steps = $taskType === 'import_im4' ? self::IMPORT_STEPS : self::EXPORT_STEPS;
        $created = [];

        foreach ($steps as $i => $stepName) {
            $stepNum = $i + 1;
            $status  = $stepNum === 1 ? 'active' : 'pending';
            $assign  = $stepNum === 1 ? $assignedTo : null;

            $id = $this->create([
                'task_id'     => $taskId,
                'step_number' => $stepNum,
                'step_name'   => $stepName,
                'assigned_to' => $assign,
                'status'      => $status,
            ]);
            $created[] = $id;
        }
        return $created;
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT ts.*, e.full_name AS assigned_to_name
               FROM task_steps ts
               LEFT JOIN employees e ON e.id = ts.assigned_to
              WHERE ts.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get all steps for a task, ordered by step_number.
     */
    public function getByTaskId(int $taskId): array {
        $stmt = $this->db->prepare(
            "SELECT ts.*, e.full_name AS assigned_to_name
               FROM task_steps ts
               LEFT JOIN employees e ON e.id = ts.assigned_to
              WHERE ts.task_id = :task_id
              ORDER BY ts.step_number ASC"
        );
        $stmt->execute([':task_id' => $taskId]);
        return $stmt->fetchAll();
    }

    /**
     * Complete a step and activate the next one.
     */
    public function completeStep(int $stepId): bool {
        $step = $this->findById($stepId);
        if (!$step || $step['status'] === 'completed') {
            return false;
        }

        // Mark current step as completed
        $stmt = $this->db->prepare(
            "UPDATE task_steps SET status = 'completed', completed_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $stepId]);

        // Activate next step
        $nextStmt = $this->db->prepare(
            "SELECT id FROM task_steps
              WHERE task_id = :task_id AND step_number = :next_num
              LIMIT 1"
        );
        $nextStmt->execute([
            ':task_id'  => $step['task_id'],
            ':next_num' => $step['step_number'] + 1,
        ]);
        $next = $nextStmt->fetch();

        if ($next) {
            $activateStmt = $this->db->prepare(
                "UPDATE task_steps SET status = 'active' WHERE id = :id"
            );
            $activateStmt->execute([':id' => $next['id']]);
        }

        return true;
    }

    /**
     * Assign a step to an employee.
     */
    public function assignStep(int $stepId, int $employeeId): bool {
        $stmt = $this->db->prepare(
            "UPDATE task_steps SET assigned_to = :emp_id WHERE id = :id"
        );
        return $stmt->execute([':emp_id' => $employeeId, ':id' => $stepId]);
    }

    /**
     * Transfer a step to another employee (only if current step is completed).
     * Assigns the next pending/active step to the new employee.
     */
    public function transferStep(int $taskId, int $fromStepId, int $toEmployeeId): array|false {
        $fromStep = $this->findById($fromStepId);
        if (!$fromStep || $fromStep['status'] !== 'completed') {
            return false;
        }

        // Find the next active/pending step
        $stmt = $this->db->prepare(
            "SELECT id FROM task_steps
              WHERE task_id = :task_id AND step_number > :step_num AND status != 'completed'
              ORDER BY step_number ASC LIMIT 1"
        );
        $stmt->execute([
            ':task_id'  => $taskId,
            ':step_num' => $fromStep['step_number'],
        ]);
        $nextStep = $stmt->fetch();

        if (!$nextStep) {
            return false;
        }

        $this->assignStep($nextStep['id'], $toEmployeeId);
        return $this->findById($nextStep['id']);
    }

    /**
     * Check if all steps of a task are completed.
     */
    public function allStepsCompleted(int $taskId): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM task_steps
              WHERE task_id = :task_id AND status != 'completed'"
        );
        $stmt->execute([':task_id' => $taskId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * Get steps assigned to an employee.
     */
    public function getByEmployee(int $employeeId, ?string $status = null): array {
        $where = 'ts.assigned_to = :emp_id';
        $params = [':emp_id' => $employeeId];

        if ($status) {
            $where .= ' AND ts.status = :status';
            $params[':status'] = $status;
        }

        $stmt = $this->db->prepare(
            "SELECT ts.*, t.title AS task_title, t.ref AS task_ref, t.task_type,
                    e.full_name AS assigned_to_name
               FROM task_steps ts
               JOIN tasks t ON t.id = ts.task_id
               LEFT JOIN employees e ON e.id = ts.assigned_to
              WHERE {$where}
              ORDER BY ts.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get points value for a step name.
     */
    public static function getStepPoints(string $stepName): int {
        return self::STEP_POINTS[$stepName] ?? 5;
    }
}
