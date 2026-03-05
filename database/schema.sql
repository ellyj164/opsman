-- OpsMan Database Schema
-- Compatible with MySQL 8.0 / MariaDB 10.5+
--
-- Host: localhost    Database: opsman
-- ------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- -----------------------------------------------------------
-- Table: users
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','operations_manager','field_employee','customs_officer','warehouse_officer','field_agent','accountant') NOT NULL DEFAULT 'field_employee',
  `token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_token` (`token`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: employees
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `employee_code` varchar(20) NOT NULL,
  `department` varchar(80) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `performance_score` decimal(5,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employees_code` (`employee_code`),
  KEY `idx_employees_user_id` (`user_id`),
  CONSTRAINT `fk_employees_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: shipments
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `shipments`;
CREATE TABLE `shipments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ref_number` varchar(30) NOT NULL,
  `shipper_name` varchar(120) NOT NULL,
  `consignee_name` varchar(120) NOT NULL,
  `origin` varchar(180) DEFAULT NULL,
  `destination` varchar(180) DEFAULT NULL,
  `cargo_type` varchar(80) DEFAULT NULL,
  `cargo_weight` decimal(12,2) DEFAULT NULL,
  `status` enum('pending','in_transit','arrived','cleared','held','delivered') NOT NULL DEFAULT 'pending',
  `estimated_departure` datetime DEFAULT NULL,
  `estimated_arrival` datetime DEFAULT NULL,
  `actual_departure` datetime DEFAULT NULL,
  `actual_arrival` datetime DEFAULT NULL,
  `carrier` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipments_ref` (`ref_number`),
  KEY `idx_shipments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: tasks
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `task_type` enum('customs_declaration','warehouse_inspection','cargo_inspection','border_transit_supervision') NOT NULL DEFAULT 'cargo_inspection',
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `location` varchar(180) DEFAULT NULL,
  `shipment_ref` varchar(30) DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('pending','assigned','in_progress','completed','overdue') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_assigned_to` (`assigned_to`),
  KEY `idx_tasks_assigned_by` (`assigned_by`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_priority` (`priority`),
  CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: task_reports
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `task_reports`;
CREATE TABLE `task_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `employee_id` int(10) unsigned NOT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `check_in_lat` decimal(10,7) DEFAULT NULL,
  `check_in_lng` decimal(10,7) DEFAULT NULL,
  `check_out_lat` decimal(10,7) DEFAULT NULL,
  `check_out_lng` decimal(10,7) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `status` enum('draft','submitted','reviewed') NOT NULL DEFAULT 'draft',
  `photos` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_reports_task_id` (`task_id`),
  KEY `idx_task_reports_employee_id` (`employee_id`),
  CONSTRAINT `fk_task_reports_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`),
  CONSTRAINT `fk_task_reports_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: gps_logs
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `gps_logs`;
CREATE TABLE `gps_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `task_id` int(10) unsigned DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy` decimal(6,1) DEFAULT NULL,
  `logged_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gps_logs_employee_id` (`employee_id`),
  KEY `idx_gps_logs_task_id` (`task_id`),
  KEY `idx_gps_logs_logged_at` (`logged_at`),
  CONSTRAINT `fk_gps_logs_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_gps_logs_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: activity_logs
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_user_id` (`user_id`),
  KEY `idx_activity_logs_created_at` (`created_at`),
  CONSTRAINT `fk_activity_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: alerts
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `related_to` enum('task','employee','shipment') DEFAULT NULL,
  `related_id` int(10) unsigned DEFAULT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alerts_severity` (`severity`),
  KEY `idx_alerts_is_read` (`is_read`),
  KEY `idx_alerts_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: documents
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `related_to` enum('shipment','task','employee','customs') DEFAULT NULL,
  `related_id` int(10) unsigned DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `file_type` varchar(80) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_documents_related` (`related_to`, `related_id`),
  KEY `idx_documents_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: warehouses
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `capacity_sqm` decimal(10,2) DEFAULT NULL,
  `manager_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_warehouses_code` (`code`),
  KEY `idx_warehouses_manager_id` (`manager_id`),
  KEY `idx_warehouses_status` (`status`),
  CONSTRAINT `fk_warehouses_manager_id` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: customs_declarations
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `customs_declarations`;
CREATE TABLE `customs_declarations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` int(10) unsigned NOT NULL,
  `declaration_no` varchar(100) DEFAULT NULL,
  `declarant_name` varchar(120) DEFAULT NULL,
  `hs_codes` text DEFAULT NULL,
  `invoice_value` decimal(14,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `country_of_origin` varchar(100) DEFAULT NULL,
  `port_of_entry` varchar(120) DEFAULT NULL,
  `submission_date` date DEFAULT NULL,
  `clearance_date` date DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected') NOT NULL DEFAULT 'draft',
  `officer_id` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customs_declaration_no` (`declaration_no`),
  KEY `idx_customs_shipment_id` (`shipment_id`),
  KEY `idx_customs_officer_id` (`officer_id`),
  KEY `idx_customs_status` (`status`),
  CONSTRAINT `fk_customs_shipment_id` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`),
  CONSTRAINT `fk_customs_officer_id` FOREIGN KEY (`officer_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customs_created_by` FOREIGN KEY (`created_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: warehouse_records
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `warehouse_records`;
CREATE TABLE `warehouse_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(10) unsigned NOT NULL,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `record_type` enum('arrival','departure','inspection','inventory') NOT NULL DEFAULT 'arrival',
  `cargo_description` text DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `weight_kg` decimal(12,2) DEFAULT NULL,
  `condition_status` enum('good','damaged','pending') NOT NULL DEFAULT 'pending',
  `inspector_id` int(10) unsigned DEFAULT NULL,
  `inspection_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_warehouse_records_warehouse_id` (`warehouse_id`),
  KEY `idx_warehouse_records_shipment_id` (`shipment_id`),
  KEY `idx_warehouse_records_inspector_id` (`inspector_id`),
  CONSTRAINT `fk_warehouse_records_warehouse_id` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_warehouse_records_shipment_id` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_warehouse_records_inspector_id` FOREIGN KEY (`inspector_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: transit_records
-- -----------------------------------------------------------

DROP TABLE IF EXISTS `transit_records`;
CREATE TABLE `transit_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` int(10) unsigned DEFAULT NULL,
  `vehicle_no` varchar(30) DEFAULT NULL,
  `driver_name` varchar(120) DEFAULT NULL,
  `driver_phone` varchar(30) DEFAULT NULL,
  `origin_border` varchar(180) DEFAULT NULL,
  `destination_border` varchar(180) DEFAULT NULL,
  `departure_time` datetime DEFAULT NULL,
  `expected_arrival` datetime DEFAULT NULL,
  `actual_arrival` datetime DEFAULT NULL,
  `border_entry_time` datetime DEFAULT NULL,
  `border_exit_time` datetime DEFAULT NULL,
  `status` enum('scheduled','in_transit','border_entry','border_exit','completed','delayed') NOT NULL DEFAULT 'scheduled',
  `delay_reason` text DEFAULT NULL,
  `supervisor_id` int(10) unsigned DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transit_records_shipment_id` (`shipment_id`),
  KEY `idx_transit_records_supervisor_id` (`supervisor_id`),
  KEY `idx_transit_records_status` (`status`),
  CONSTRAINT `fk_transit_records_shipment_id` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transit_records_supervisor_id` FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
