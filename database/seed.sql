-- OpsMan Field Operations Management System
-- Seed / Test Data
-- Run AFTER schema.sql

USE `opsman`;

-- -------------------------------------------------------
-- Users  (passwords hashed with bcrypt cost=12)
-- admin      → Admin@123
-- manager1   → Manager@123
-- employee1  → Employee@123
-- -------------------------------------------------------
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `is_active`) VALUES
('admin',
 'admin@opsman.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin',
 1),
('manager1',
 'manager@opsman.com',
 '$2y$12$T5xMFD5hm1u4OVxGCkpj5.OHxh63a/9dG0fkUYFP4SNOPkPdq7Qbi',
 'operations_manager',
 1),
('employee1',
 'employee@opsman.com',
 '$2y$12$gFXBpV8NVnpPbAVPi2pRkO1q9PxYFKtnH0hFmWwGUJaX1b5oHgaHS',
 'field_employee',
 1);

-- -------------------------------------------------------
-- Employees
-- -------------------------------------------------------
INSERT INTO `employees` (`user_id`, `full_name`, `employee_code`, `department`, `phone`, `address`, `performance_score`) VALUES
(1, 'System Administrator', 'EMP-001', 'IT Administration',   '+1-555-0100', '100 Admin Street, HQ',       100.00),
(2, 'John Manager',         'EMP-002', 'Operations',          '+1-555-0101', '200 Operations Ave, HQ',     95.50),
(3, 'Jane Field',           'EMP-003', 'Field Operations',    '+1-555-0102', '300 Field Road, Downtown',   88.00);

-- -------------------------------------------------------
-- Shipments
-- -------------------------------------------------------
INSERT INTO `shipments` (`ref_number`, `shipper_name`, `consignee_name`, `origin`, `destination`, `cargo_type`, `cargo_weight`, `status`) VALUES
('SHP-2024-001', 'Global Imports Ltd',   'Local Distributors Inc', 'Shanghai, China',     'Los Angeles, USA',  'Electronics',        12500.00, 'in_transit'),
('SHP-2024-002', 'Euro Exports GmbH',    'American Retail Corp',   'Hamburg, Germany',    'New York, USA',     'Automotive Parts',    8200.00, 'arrived'),
('SHP-2024-003', 'Pacific Trade Co',     'Midwest Warehousing LLC','Tokyo, Japan',        'Chicago, USA',      'Consumer Goods',     15300.00, 'pending'),
('SHP-2024-004', 'South American Goods', 'Eastern Imports Ltd',    'Buenos Aires, Brazil','Miami, USA',        'Agricultural Products',5800.00,'cleared'),
('SHP-2024-005', 'Asian Manufacturing',  'Tech Solutions Inc',     'Seoul, South Korea',  'Seattle, USA',      'Machinery',          22100.00, 'held');

-- -------------------------------------------------------
-- Tasks
-- -------------------------------------------------------
INSERT INTO `tasks` (`title`, `description`, `task_type`, `assigned_to`, `assigned_by`, `location`, `shipment_ref`, `deadline`, `priority`, `status`) VALUES
('Customs Declaration - SHP-2024-001',
 'Process customs declaration for electronics shipment from Shanghai. Verify all documentation and duties.',
 'customs_declaration', 3, 2,
 'Los Angeles Port - Terminal B',  'SHP-2024-001',
 DATE_ADD(NOW(), INTERVAL 2 DAY),  'high', 'in_progress'),

('Warehouse Inspection - SHP-2024-002',
 'Conduct full warehouse inspection for automotive parts received from Hamburg.',
 'warehouse_inspection', 3, 2,
 'New York Warehouse District',    'SHP-2024-002',
 DATE_ADD(NOW(), INTERVAL 1 DAY),  'urgent', 'assigned'),

('Border Transit Supervision - SHP-2024-003',
 'Supervise border transit for consumer goods convoy from Japan.',
 'border_transit_supervision', 3, 2,
 'Chicago International Border',   'SHP-2024-003',
 DATE_ADD(NOW(), INTERVAL 5 DAY),  'medium', 'pending'),

('Cargo Inspection - SHP-2024-004',
 'Inspect cleared agricultural products before final delivery.',
 'cargo_inspection', 3, 2,
 'Miami Cargo Terminal',           'SHP-2024-004',
 DATE_SUB(NOW(), INTERVAL 1 DAY),  'high', 'completed'),

('Cargo Inspection - SHP-2024-005',
 'Inspect held machinery shipment from Seoul. Resolve customs hold.',
 'cargo_inspection', 3, 2,
 'Seattle Port Authority',         'SHP-2024-005',
 DATE_SUB(NOW(), INTERVAL 2 DAY),  'urgent', 'overdue'),

('Routine Warehouse Audit',
 'Monthly routine audit of main warehouse facility.',
 'warehouse_inspection', 3, 2,
 'HQ Warehouse',                   NULL,
 DATE_ADD(NOW(), INTERVAL 7 DAY),  'low', 'pending');

-- -------------------------------------------------------
-- Task Reports
-- -------------------------------------------------------
INSERT INTO `task_reports` (`task_id`, `employee_id`, `check_in_time`, `check_out_time`,
    `check_in_lat`, `check_in_lng`, `check_out_lat`, `check_out_lng`,
    `notes`, `observations`, `status`) VALUES
(1, 3,
 DATE_SUB(NOW(), INTERVAL 3 HOUR), NULL,
 33.7395145, -118.2595234, NULL, NULL,
 'Started customs declaration process. All initial documents verified.',
 'Shipment contents match manifest. No discrepancies found so far.',
 'submitted'),

(4, 3,
 DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY + 20 HOUR),
 25.7617296, -80.1918069, 25.7620000, -80.1920000,
 'Completed full cargo inspection for agricultural products.',
 'All items passed inspection. Temperature-sensitive goods within acceptable range. Cleared for delivery.',
 'reviewed');

-- -------------------------------------------------------
-- GPS Logs
-- -------------------------------------------------------
INSERT INTO `gps_logs` (`employee_id`, `task_id`, `latitude`, `longitude`, `accuracy`, `logged_at`) VALUES
(3, 1, 33.7395145, -118.2595234, 5.0,  DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 1, 33.7397000, -118.2597000, 4.5,  DATE_SUB(NOW(), INTERVAL 2 HOUR + 45 MINUTE)),
(3, 1, 33.7399000, -118.2599000, 6.0,  DATE_SUB(NOW(), INTERVAL 2 HOUR + 30 MINUTE)),
(3, 4, 25.7617296, -80.1918069,  5.0,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 4, 25.7620000, -80.1920000,  4.0,  DATE_SUB(NOW(), INTERVAL 1 DAY + 22 HOUR));

-- -------------------------------------------------------
-- Activity Logs
-- -------------------------------------------------------
INSERT INTO `activity_logs` (`user_id`, `action`, `details`, `ip_address`) VALUES
(1, 'system_init',  'System initialized and seed data loaded',  '127.0.0.1'),
(2, 'task_created', 'Created task: Customs Declaration SHP-2024-001', '127.0.0.1'),
(2, 'task_created', 'Created task: Warehouse Inspection SHP-2024-002', '127.0.0.1'),
(3, 'task_checkin', 'Checked in to task ID 1 at Los Angeles Port', '127.0.0.1'),
(3, 'task_complete','Completed task ID 4 - Cargo Inspection Miami', '127.0.0.1');

-- -------------------------------------------------------
-- Alerts
-- -------------------------------------------------------
INSERT INTO `alerts` (`type`, `title`, `message`, `related_to`, `related_id`, `severity`, `is_read`) VALUES
('task_overdue',    'Task Overdue: Cargo Inspection SHP-2024-005',
 'Cargo inspection task for shipment SHP-2024-005 has passed its deadline and is now overdue.',
 'task', 5, 'critical', 0),

('task_overdue',    'Task Overdue: Cargo Inspection Miami',
 'Cargo inspection for SHP-2024-004 was completed but check-out not recorded before deadline.',
 'task', 4, 'warning', 1),

('shipment_held',   'Shipment On Hold: SHP-2024-005',
 'Shipment SHP-2024-005 from Seoul has been placed on hold by customs. Immediate action required.',
 'shipment', 5, 'critical', 0),

('performance',     'Low Performance Score Warning',
 'Employee Jane Field performance score has dropped below 90. Review recommended.',
 'employee', 3, 'warning', 0),

('system',          'New Shipment Arrived: SHP-2024-002',
 'Shipment SHP-2024-002 (Automotive Parts) has arrived at New York warehouse.',
 'shipment', 2, 'info', 1);
