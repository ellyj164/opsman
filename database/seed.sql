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
INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `role`, `is_active`) VALUES
('admin',
 'admin@opsman.com',
 '$2y$12$u/NkR/djrp5eVTR7iUubD.gQav0f5/FuHy9Dtmb/8hespfF3Jjdbi',
 'admin',
 1),
('manager1',
 'manager@opsman.com',
 '$2y$12$WC8T3LwVX39txCMSOF8yOOeavCw96CfN3onYi/Pg3y7e9r4TyiYsq',
 'operations_manager',
 1),
('employee1',
 'employee@opsman.com',
 '$2y$12$yyPzMQI0ndOnTPlrb2JtUuG1.ADnvceM2I453yCfAtf/DOwxhcusW',
 'field_employee',
 1);

-- -------------------------------------------------------
-- Employees
-- -------------------------------------------------------
INSERT IGNORE INTO `employees` (`user_id`, `full_name`, `employee_code`, `department`, `phone`, `address`, `performance_score`) VALUES
(1, 'System Administrator', 'EMP-001', 'IT Administration',   '+1-555-0100', '100 Admin Street, HQ',       100.00),
(2, 'John Manager',         'EMP-002', 'Operations',          '+1-555-0101', '200 Operations Ave, HQ',     95.50),
(3, 'Jane Field',           'EMP-003', 'Field Operations',    '+1-555-0102', '300 Field Road, Downtown',   88.00);

-- -------------------------------------------------------
-- Shipments
-- -------------------------------------------------------
INSERT IGNORE INTO `shipments` (`ref_number`, `shipper_name`, `consignee_name`, `origin`, `destination`, `cargo_type`, `cargo_weight`, `status`) VALUES
('SHP-2024-001', 'Global Imports Ltd',   'Local Distributors Inc', 'Shanghai, China',     'Los Angeles, USA',  'Electronics',        12500.00, 'in_transit'),
('SHP-2024-002', 'Euro Exports GmbH',    'American Retail Corp',   'Hamburg, Germany',    'New York, USA',     'Automotive Parts',    8200.00, 'arrived'),
('SHP-2024-003', 'Pacific Trade Co',     'Midwest Warehousing LLC','Tokyo, Japan',        'Chicago, USA',      'Consumer Goods',     15300.00, 'pending'),
('SHP-2024-004', 'South American Goods', 'Eastern Imports Ltd',    'Buenos Aires, Brazil','Miami, USA',        'Agricultural Products',5800.00,'cleared'),
('SHP-2024-005', 'Asian Manufacturing',  'Tech Solutions Inc',     'Seoul, South Korea',  'Seattle, USA',      'Machinery',          22100.00, 'held');

-- -------------------------------------------------------
-- Tasks
-- -------------------------------------------------------
INSERT IGNORE INTO `tasks` (`ref`, `title`, `description`, `task_type`, `assigned_to`, `assigned_by`, `created_by_user`, `location`, `shipment_ref`, `deadline`, `priority`, `status`) VALUES
('TSK-2024-001', 'Customs Declaration - SHP-2024-001',
 'Process customs declaration for electronics shipment from Shanghai. Verify all documentation and duties.',
 'customs_declaration', 3, 2, 2,
 'Los Angeles Port - Terminal B',  'SHP-2024-001',
 DATE_ADD(NOW(), INTERVAL 2 DAY),  'high', 'in_progress'),

('TSK-2024-002', 'Warehouse Inspection - SHP-2024-002',
 'Conduct full warehouse inspection for automotive parts received from Hamburg.',
 'warehouse_inspection', 3, 2, 2,
 'New York Warehouse District',    'SHP-2024-002',
 DATE_ADD(NOW(), INTERVAL 1 DAY),  'urgent', 'assigned'),

('TSK-2024-003', 'Border Transit Supervision - SHP-2024-003',
 'Supervise border transit for consumer goods convoy from Japan.',
 'border_transit_supervision', 3, 2, 2,
 'Chicago International Border',   'SHP-2024-003',
 DATE_ADD(NOW(), INTERVAL 5 DAY),  'medium', 'pending'),

('TSK-2024-004', 'Cargo Inspection - SHP-2024-004',
 'Inspect cleared agricultural products before final delivery.',
 'cargo_inspection', 3, 2, 2,
 'Miami Cargo Terminal',           'SHP-2024-004',
 DATE_SUB(NOW(), INTERVAL 1 DAY),  'high', 'completed'),

('TSK-2024-005', 'Cargo Inspection - SHP-2024-005',
 'Inspect held machinery shipment from Seoul. Resolve customs hold.',
 'cargo_inspection', 3, 2, 2,
 'Seattle Port Authority',         'SHP-2024-005',
 DATE_SUB(NOW(), INTERVAL 2 DAY),  'urgent', 'overdue'),

('TSK-2024-006', 'Routine Warehouse Audit',
 'Monthly routine audit of main warehouse facility.',
 'warehouse_inspection', 3, 2, 2,
 'HQ Warehouse',                   NULL,
 DATE_ADD(NOW(), INTERVAL 7 DAY),  'low', 'pending'),

('IM4-2024-001', 'Import Clearance - Electronics Batch',
 'Full IM4 import clearance process for electronics batch from China.',
 'import_im4', 3, 2, 2,
 'Kigali Dry Port',               'SHP-2024-001',
 DATE_ADD(NOW(), INTERVAL 5 DAY),  'high', 'active'),

('EX1-2024-001', 'Export Processing - Coffee Shipment',
 'Full EX1 export clearance for coffee shipment to Belgium.',
 'export_ex1', 4, 2, 2,
 'Magerwa Warehouse',             'SHP-2024-003',
 DATE_ADD(NOW(), INTERVAL 3 DAY),  'medium', 'active');

-- -------------------------------------------------------
-- Task Reports
-- -------------------------------------------------------
INSERT IGNORE INTO `task_reports` (`task_id`, `employee_id`, `check_in_time`, `check_out_time`,
    `check_in_lat`, `check_in_lng`, `check_out_lat`, `check_out_lng`,
    `notes`, `observations`, `status`) VALUES
(1, 3,
 DATE_SUB(NOW(), INTERVAL 3 HOUR), NULL,
 33.7395145, -118.2595234, NULL, NULL,
 'Started customs declaration process. All initial documents verified.',
 'Shipment contents match manifest. No discrepancies found so far.',
 'submitted'),

(4, 3,
 DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 44 HOUR),
 25.7617296, -80.1918069, 25.7620000, -80.1920000,
 'Completed full cargo inspection for agricultural products.',
 'All items passed inspection. Temperature-sensitive goods within acceptable range. Cleared for delivery.',
 'reviewed');

-- -------------------------------------------------------
-- GPS Logs
-- -------------------------------------------------------
INSERT IGNORE INTO `gps_logs` (`employee_id`, `task_id`, `latitude`, `longitude`, `accuracy`, `logged_at`) VALUES
(3, 1, 33.7395145, -118.2595234, 5.0,  DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 1, 33.7397000, -118.2597000, 4.5,  DATE_SUB(NOW(), INTERVAL 165 MINUTE)),
(3, 1, 33.7399000, -118.2599000, 6.0,  DATE_SUB(NOW(), INTERVAL 150 MINUTE)),
(3, 4, 25.7617296, -80.1918069,  5.0,  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 4, 25.7620000, -80.1920000,  4.0,  DATE_SUB(NOW(), INTERVAL 46 HOUR));

-- -------------------------------------------------------
-- Activity Logs
-- -------------------------------------------------------
INSERT IGNORE INTO `activity_logs` (`user_id`, `action`, `details`, `ip_address`) VALUES
(1, 'system_init',  'System initialized and seed data loaded',  '127.0.0.1'),
(2, 'task_created', 'Created task: Customs Declaration SHP-2024-001', '127.0.0.1'),
(2, 'task_created', 'Created task: Warehouse Inspection SHP-2024-002', '127.0.0.1'),
(3, 'task_checkin', 'Checked in to task ID 1 at Los Angeles Port', '127.0.0.1'),
(3, 'task_complete','Completed task ID 4 - Cargo Inspection Miami', '127.0.0.1');

-- -------------------------------------------------------
-- Alerts
-- -------------------------------------------------------
INSERT IGNORE INTO `alerts` (`type`, `title`, `message`, `related_to`, `related_id`, `severity`, `is_read`) VALUES
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

-- -------------------------------------------------------
-- New role users
-- -------------------------------------------------------
INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `role`, `is_active`) VALUES
('customs1',
 'customs@opsman.com',
 '$2y$12$u/NkR/djrp5eVTR7iUubD.gQav0f5/FuHy9Dtmb/8hespfF3Jjdbi',
 'customs_officer', 1),
('warehouse1',
 'warehouse@opsman.com',
 '$2y$12$u/NkR/djrp5eVTR7iUubD.gQav0f5/FuHy9Dtmb/8hespfF3Jjdbi',
 'warehouse_officer', 1),
('accountant1',
 'accountant@opsman.com',
 '$2y$12$u/NkR/djrp5eVTR7iUubD.gQav0f5/FuHy9Dtmb/8hespfF3Jjdbi',
 'accountant', 1),
('agent1',
 'agent@opsman.com',
 '$2y$12$u/NkR/djrp5eVTR7iUubD.gQav0f5/FuHy9Dtmb/8hespfF3Jjdbi',
 'field_agent', 1);

INSERT IGNORE INTO `employees` (`user_id`, `full_name`, `employee_code`, `department`, `phone`, `performance_score`) VALUES
(4, 'Carlos Customs',   'EMP-004', 'Customs Department',   '+1-555-0103', 92.00),
(5, 'Wendy Warehouse',  'EMP-005', 'Warehouse Operations', '+1-555-0104', 89.50),
(6, 'Alice Accountant', 'EMP-006', 'Finance & Accounting', '+1-555-0105', 97.00),
(7, 'Felix Agent',      'EMP-007', 'Field Operations',     '+1-555-0106', 85.00);

-- -------------------------------------------------------
-- Warehouses
-- -------------------------------------------------------
INSERT IGNORE INTO `warehouses` (`name`, `code`, `address`, `city`, `country`, `latitude`, `longitude`, `capacity_sqm`, `manager_id`, `status`) VALUES
('Los Angeles Main Warehouse', 'WH-LA-01', '1200 Harbor Blvd', 'Los Angeles', 'USA', 33.7395, -118.2595, 5000.00, 5, 'active'),
('New York Distribution Center', 'WH-NY-01', '450 Port Avenue', 'New York', 'USA', 40.7128, -74.0060, 3500.00, 5, 'active'),
('Chicago Logistics Hub', 'WH-CH-01', '800 Industrial Pkwy', 'Chicago', 'USA', 41.8781, -87.6298, 4200.00, 5, 'active');

-- -------------------------------------------------------
-- Customs Declarations
-- -------------------------------------------------------
INSERT IGNORE INTO `customs_declarations` (`shipment_id`, `declaration_no`, `declarant_name`, `hs_codes`, `invoice_value`, `currency`, `country_of_origin`, `port_of_entry`, `submission_date`, `status`, `officer_id`, `created_by`) VALUES
(1, 'CD-2024-001', 'Global Imports Ltd', '["8471.30","8517.12"]', 185000.00, 'USD', 'China', 'Los Angeles Port', CURDATE(), 'under_review', 4, 4),
(2, 'CD-2024-002', 'Euro Exports GmbH',  '["8708.99","8714.91"]',  97500.00, 'USD', 'Germany', 'New York Port', DATE_SUB(CURDATE(),INTERVAL 3 DAY), 'approved', 4, 4),
(3, 'CD-2024-003', 'Pacific Trade Co',   '["6217.10","6217.90"]',  45000.00, 'USD', 'Japan', 'Chicago O\'Hare',  CURDATE(), 'draft', 4, 4);

-- -------------------------------------------------------
-- Warehouse Records
-- -------------------------------------------------------
INSERT IGNORE INTO `warehouse_records` (`warehouse_id`, `shipment_id`, `record_type`, `cargo_description`, `quantity`, `unit`, `weight_kg`, `condition_status`, `inspector_id`, `inspection_date`, `notes`) VALUES
(2, 2, 'arrival',     'Automotive Parts – Pallets 1-20', 20, 'pallet', 8200.00, 'good',    5, DATE_SUB(NOW(), INTERVAL 1 DAY), 'All pallets intact, no damage observed'),
(2, 2, 'inspection',  'Automotive Parts – Detailed Inspection', 20, 'pallet', 8200.00, 'good', 5, DATE_SUB(NOW(), INTERVAL 12 HOUR), 'Parts match manifest. Cleared for storage.'),
(1, 1, 'arrival',     'Electronics – Boxes 1-50', 50, 'box', 12500.00, 'pending', 5, NULL, 'Awaiting customs clearance before inspection');

-- -------------------------------------------------------
-- Transit Records
-- -------------------------------------------------------
INSERT IGNORE INTO `transit_records` (`shipment_id`, `vehicle_no`, `driver_name`, `driver_phone`, `origin_border`, `destination_border`, `departure_time`, `expected_arrival`, `status`, `supervisor_id`, `latitude`, `longitude`) VALUES
(3, 'TRK-IL-4821', 'Mike Johnson', '+1-555-9001', 'Canadian Border – Niagara', 'Chicago Distribution', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_ADD(NOW(), INTERVAL 2 HOUR), 'in_transit', 7, 41.8500, -87.6500),
(5, 'TRK-WA-1122', 'Sarah Lee',   '+1-555-9002', 'Seattle Port of Entry',    'Seattle Warehouse',    DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 1 HOUR), 'border_entry', 7, 47.6062, -122.3321);

-- -------------------------------------------------------
-- Task Steps (for IM4 import task id=7)
-- -------------------------------------------------------
INSERT IGNORE INTO `task_steps` (`task_id`, `step_number`, `step_name`, `assigned_to`, `status`, `completed_at`) VALUES
(7, 1, 'Arrival Notice correction',        3, 'completed', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 2, 'Declaration',                       3, 'completed', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(7, 3, 'Pre-Physical Verification',         3, 'active',    NULL),
(7, 4, 'Physical Verification',             NULL, 'pending',  NULL),
(7, 5, 'Release Request',                   NULL, 'pending',  NULL),
(7, 6, 'Warehouse Payment Processes',       NULL, 'pending',  NULL),
(7, 7, 'Release Request',                   NULL, 'pending',  NULL),
(7, 8, 'Warehouse Payment Procedures',      NULL, 'pending',  NULL),
(7, 9, 'Telling/Exit',                      NULL, 'pending',  NULL);

-- Task Steps (for EX1 export task id=8)
INSERT IGNORE INTO `task_steps` (`task_id`, `step_number`, `step_name`, `assigned_to`, `status`) VALUES
(8, 1, 'Driver application / Badge request', 4, 'completed'),
(8, 2, 'Pallets correction',                 4, 'active'),
(8, 3, 'Offloading',                         NULL, 'pending'),
(8, 4, 'Declaration',                         NULL, 'pending'),
(8, 5, 'Attachments',                         NULL, 'pending'),
(8, 6, 'Documents submission',                NULL, 'pending'),
(8, 7, 'Exiting',                             NULL, 'pending');

-- -------------------------------------------------------
-- Notifications
-- -------------------------------------------------------
INSERT IGNORE INTO `notifications` (`user_id`, `message`, `type`, `read_status`) VALUES
(3, 'You have been assigned task: Import Clearance - Electronics Batch', 'task_assigned', 0),
(3, 'Step "Arrival Notice correction" has been completed for IM4-2024-001', 'step_completed', 1),
(3, 'Step "Declaration" has been completed for IM4-2024-001', 'step_completed', 0),
(1, 'New import task IM4-2024-001 created and awaiting approval', 'task_created', 0),
(2, 'Employee Jane Field completed Declaration step on IM4-2024-001', 'step_completed', 0);

-- -------------------------------------------------------
-- Employee Points
-- -------------------------------------------------------
INSERT IGNORE INTO `employee_points` (`employee_id`, `task_id`, `step_name`, `points`, `reason`) VALUES
(3, 7, 'Arrival Notice correction', 5,  'Completed arrival notice correction'),
(3, 7, 'Declaration',              5,  'Completed declaration step'),
(4, 8, 'Driver application / Badge request', 5, 'Completed driver application step'),
(3, 4, 'Cargo Inspection',        10, 'Completed full cargo inspection task'),
(3, 1, 'Customs Declaration',      5, 'Completed customs declaration task');
