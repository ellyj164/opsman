-- OpsMan Field Operations Management System
-- Database Schema
-- MySQL 5.7+

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS `opsman` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `opsman`;

-- -------------------------------------------------------
-- Table: users
-- -------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`         VARCHAR(50)     NOT NULL,
    `email`            VARCHAR(120)    NOT NULL,
    `password_hash`    VARCHAR(255)    NOT NULL,
    `role`             ENUM('admin','operations_manager','field_employee','customs_officer','warehouse_officer','field_agent','accountant') NOT NULL DEFAULT 'field_employee',
    `token`            VARCHAR(64)     NULL,
    `token_expires_at` DATETIME        NULL,
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email`    (`email`),
    KEY `idx_users_token`          (`token`),
    KEY `idx_users_role`           (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: employees
-- -------------------------------------------------------
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED    NOT NULL,
    `full_name`         VARCHAR(120)    NOT NULL,
    `employee_code`     VARCHAR(20)     NOT NULL,
    `department`        VARCHAR(80)     NOT NULL,
    `phone`             VARCHAR(20)     NULL,
    `address`           TEXT            NULL,
    `profile_photo`     VARCHAR(255)    NULL,
    `performance_score` DECIMAL(5,2)    NOT NULL DEFAULT 100.00,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_employees_code`    (`employee_code`),
    UNIQUE KEY `uq_employees_user_id` (`user_id`),
    KEY `idx_employees_department`    (`department`),
    CONSTRAINT `fk_employees_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: shipments
-- -------------------------------------------------------
DROP TABLE IF EXISTS `shipments`;
CREATE TABLE `shipments` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `ref_number`     VARCHAR(30)   NOT NULL,
    `shipper_name`   VARCHAR(120)  NOT NULL,
    `consignee_name` VARCHAR(120)  NOT NULL,
    `origin`         VARCHAR(120)  NOT NULL,
    `destination`    VARCHAR(120)  NOT NULL,
    `cargo_type`     VARCHAR(80)   NOT NULL,
    `cargo_weight`   DECIMAL(10,2) NULL,
    `status`         ENUM('pending','in_transit','arrived','cleared','held') NOT NULL DEFAULT 'pending',
    `client_name`    VARCHAR(120) NULL,
    `client_email`   VARCHAR(120) NULL,
    `client_phone`   VARCHAR(30)  NULL,
    `assigned_to`    INT UNSIGNED NULL,
    `created_by`     INT UNSIGNED NULL,
    `notes`          TEXT         NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_shipments_ref` (`ref_number`),
    KEY `idx_shipments_status`    (`status`),
    KEY `idx_shipments_assigned_to` (`assigned_to`),
    KEY `idx_shipments_created_by`  (`created_by`),
    CONSTRAINT `fk_shipments_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_shipments_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: tasks
-- -------------------------------------------------------
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(180) NOT NULL,
    `description`  TEXT         NULL,
    `task_type`    ENUM('customs_declaration','warehouse_inspection','border_transit_supervision','cargo_inspection') NOT NULL,
    `assigned_to`  INT UNSIGNED NULL,
    `assigned_by`  INT UNSIGNED NULL,
    `location`     VARCHAR(255) NULL,
    `shipment_ref` VARCHAR(30)  NULL,
    `deadline`     DATETIME     NULL,
    `priority`     ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `status`       ENUM('pending','assigned','in_progress','completed','overdue') NOT NULL DEFAULT 'pending',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tasks_assigned_to` (`assigned_to`),
    KEY `idx_tasks_assigned_by` (`assigned_by`),
    KEY `idx_tasks_status`      (`status`),
    KEY `idx_tasks_priority`    (`priority`),
    KEY `idx_tasks_task_type`   (`task_type`),
    CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: task_reports
-- -------------------------------------------------------
DROP TABLE IF EXISTS `task_reports`;
CREATE TABLE `task_reports` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `task_id`        INT UNSIGNED   NOT NULL,
    `employee_id`    INT UNSIGNED   NOT NULL,
    `check_in_time`  DATETIME       NULL,
    `check_out_time` DATETIME       NULL,
    `check_in_lat`   DECIMAL(10,7)  NULL,
    `check_in_lng`   DECIMAL(10,7)  NULL,
    `check_out_lat`  DECIMAL(10,7)  NULL,
    `check_out_lng`  DECIMAL(10,7)  NULL,
    `notes`          TEXT           NULL,
    `observations`   TEXT           NULL,
    `status`         ENUM('draft','submitted','reviewed') NOT NULL DEFAULT 'draft',
    `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_task_reports_task_id`     (`task_id`),
    KEY `idx_task_reports_employee_id` (`employee_id`),
    CONSTRAINT `fk_task_reports_task_id`     FOREIGN KEY (`task_id`)     REFERENCES `tasks`     (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_task_reports_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: gps_logs
-- -------------------------------------------------------
DROP TABLE IF EXISTS `gps_logs`;
CREATE TABLE `gps_logs` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `employee_id` INT UNSIGNED  NOT NULL,
    `task_id`     INT UNSIGNED  NULL,
    `latitude`    DECIMAL(10,7) NOT NULL,
    `longitude`   DECIMAL(10,7) NOT NULL,
    `accuracy`    DECIMAL(8,2)  NULL,
    `logged_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gps_logs_employee_id` (`employee_id`),
    KEY `idx_gps_logs_task_id`     (`task_id`),
    KEY `idx_gps_logs_logged_at`   (`logged_at`),
    CONSTRAINT `fk_gps_logs_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gps_logs_task_id`     FOREIGN KEY (`task_id`)     REFERENCES `tasks`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: activity_logs
-- -------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(80)  NOT NULL,
    `details`    TEXT         NULL,
    `ip_address` VARCHAR(45)  NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_user_id`    (`user_id`),
    KEY `idx_activity_logs_created_at` (`created_at`),
    CONSTRAINT `fk_activity_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: alerts
-- -------------------------------------------------------
DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`       VARCHAR(50)  NOT NULL,
    `title`      VARCHAR(180) NOT NULL,
    `message`    TEXT         NOT NULL,
    `related_to` ENUM('task','employee','shipment') NULL,
    `related_id` INT UNSIGNED NULL,
    `severity`   ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alerts_severity`   (`severity`),
    KEY `idx_alerts_is_read`    (`is_read`),
    KEY `idx_alerts_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: documents
-- -------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `task_report_id` INT UNSIGNED  NULL,
    `customs_id`     INT UNSIGNED  NULL,
    `shipment_id`    INT UNSIGNED  NULL,
    `warehouse_record_id` INT UNSIGNED NULL,
    `transit_id`     INT UNSIGNED  NULL,
    `employee_id`    INT UNSIGNED  NOT NULL,
    `file_name`      VARCHAR(255)  NOT NULL,
    `file_path`      VARCHAR(500)  NOT NULL,
    `file_type`      VARCHAR(50)   NOT NULL,
    `file_size`      INT UNSIGNED  NOT NULL,
    `uploaded_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_documents_task_report_id`      (`task_report_id`),
    KEY `idx_documents_customs_id`          (`customs_id`),
    KEY `idx_documents_shipment_id`         (`shipment_id`),
    KEY `idx_documents_warehouse_record_id` (`warehouse_record_id`),
    KEY `idx_documents_transit_id`          (`transit_id`),
    KEY `idx_documents_employee_id`         (`employee_id`),
    CONSTRAINT `fk_documents_task_report_id`      FOREIGN KEY (`task_report_id`)      REFERENCES `task_reports`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documents_customs_id`          FOREIGN KEY (`customs_id`)          REFERENCES `customs_declarations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documents_shipment_id`         FOREIGN KEY (`shipment_id`)         REFERENCES `shipments`         (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documents_warehouse_record_id` FOREIGN KEY (`warehouse_record_id`) REFERENCES `warehouse_records` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documents_transit_id`          FOREIGN KEY (`transit_id`)          REFERENCES `transit_records`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documents_employee_id`         FOREIGN KEY (`employee_id`)         REFERENCES `employees`         (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: customs_declarations
-- -------------------------------------------------------
DROP TABLE IF EXISTS `customs_declarations`;
CREATE TABLE `customs_declarations` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `shipment_id`     INT UNSIGNED  NULL,
    `declaration_no`  VARCHAR(50)   NOT NULL,
    `declarant_name`  VARCHAR(120)  NOT NULL,
    `hs_codes`        TEXT          NULL COMMENT 'JSON array of HS codes',
    `invoice_value`   DECIMAL(15,2) NULL,
    `currency`        VARCHAR(10)   NOT NULL DEFAULT 'USD',
    `country_of_origin` VARCHAR(80) NULL,
    `port_of_entry`   VARCHAR(120)  NULL,
    `submission_date` DATE          NULL,
    `clearance_date`  DATE          NULL,
    `status`          ENUM('draft','submitted','under_review','approved','rejected','cleared') NOT NULL DEFAULT 'draft',
    `officer_id`      INT UNSIGNED  NULL COMMENT 'Customs officer handling this',
    `notes`           TEXT          NULL,
    `created_by`      INT UNSIGNED  NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customs_declaration_no` (`declaration_no`),
    KEY `idx_customs_shipment_id`  (`shipment_id`),
    KEY `idx_customs_status`       (`status`),
    KEY `idx_customs_officer_id`   (`officer_id`),
    CONSTRAINT `fk_customs_shipment_id` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_customs_officer_id`  FOREIGN KEY (`officer_id`)  REFERENCES `employees` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_customs_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: warehouses
-- -------------------------------------------------------
DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(120) NOT NULL,
    `code`         VARCHAR(20)  NOT NULL,
    `address`      TEXT         NULL,
    `city`         VARCHAR(80)  NULL,
    `country`      VARCHAR(80)  NULL,
    `latitude`     DECIMAL(10,7) NULL,
    `longitude`    DECIMAL(10,7) NULL,
    `capacity_sqm` DECIMAL(10,2) NULL,
    `manager_id`   INT UNSIGNED NULL COMMENT 'Employee who manages warehouse',
    `status`       ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_warehouses_code` (`code`),
    KEY `idx_warehouses_status` (`status`),
    CONSTRAINT `fk_warehouses_manager_id` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: warehouse_records
-- -------------------------------------------------------
DROP TABLE IF EXISTS `warehouse_records`;
CREATE TABLE `warehouse_records` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `warehouse_id`    INT UNSIGNED NOT NULL,
    `shipment_id`     INT UNSIGNED NULL,
    `record_type`     ENUM('arrival','inspection','storage','dispatch') NOT NULL DEFAULT 'arrival',
    `cargo_description` TEXT       NULL,
    `quantity`        INT          NULL,
    `unit`            VARCHAR(20)  NULL,
    `weight_kg`       DECIMAL(10,2) NULL,
    `condition_status` ENUM('good','damaged','partial','pending') NOT NULL DEFAULT 'pending',
    `inspector_id`    INT UNSIGNED NULL,
    `inspection_date` DATETIME     NULL,
    `notes`           TEXT         NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wh_records_warehouse_id` (`warehouse_id`),
    KEY `idx_wh_records_shipment_id`  (`shipment_id`),
    KEY `idx_wh_records_type`         (`record_type`),
    CONSTRAINT `fk_wh_records_warehouse_id` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wh_records_shipment_id`  FOREIGN KEY (`shipment_id`)  REFERENCES `shipments`   (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_wh_records_inspector_id` FOREIGN KEY (`inspector_id`) REFERENCES `employees`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Table: transit_records
-- -------------------------------------------------------
DROP TABLE IF EXISTS `transit_records`;
CREATE TABLE `transit_records` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `shipment_id`      INT UNSIGNED  NULL,
    `vehicle_no`       VARCHAR(30)   NOT NULL,
    `driver_name`      VARCHAR(120)  NULL,
    `driver_phone`     VARCHAR(30)   NULL,
    `origin_border`    VARCHAR(120)  NULL,
    `destination_border` VARCHAR(120) NULL,
    `departure_time`   DATETIME      NULL,
    `expected_arrival` DATETIME      NULL,
    `actual_arrival`   DATETIME      NULL,
    `border_entry_time`  DATETIME    NULL,
    `border_exit_time`   DATETIME    NULL,
    `status`           ENUM('scheduled','in_transit','border_entry','border_clearance','completed','delayed','stopped') NOT NULL DEFAULT 'scheduled',
    `delay_reason`     TEXT          NULL,
    `supervisor_id`    INT UNSIGNED  NULL COMMENT 'Field agent supervising this transit',
    `latitude`         DECIMAL(10,7) NULL COMMENT 'Last known location',
    `longitude`        DECIMAL(10,7) NULL,
    `notes`            TEXT          NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_transit_shipment_id`   (`shipment_id`),
    KEY `idx_transit_status`        (`status`),
    KEY `idx_transit_supervisor_id` (`supervisor_id`),
    CONSTRAINT `fk_transit_shipment_id`   FOREIGN KEY (`shipment_id`)   REFERENCES `shipments`  (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_transit_supervisor_id` FOREIGN KEY (`supervisor_id`) REFERENCES `employees`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
