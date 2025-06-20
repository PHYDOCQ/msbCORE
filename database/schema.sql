-- =====================================================
-- msbCORE BENGKEL MANAGEMENT SYSTEM DATABASE SCHEMA
-- Version: 3.2.0 - Enhanced Body Repair & Paint Workshop
-- Created: 2025-06-19
-- Updated: 2025-06-20
-- Compatible with: PHP 8.2.6, MySQL 5.7.34
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- =====================================================
-- DATABASE CREATION
-- =====================================================
CREATE DATABASE IF NOT EXISTS `msbcore_bengkel` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `msbcore_bengkel`;

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','manager','technician','staff') NOT NULL DEFAULT 'staff',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CUSTOMERS TABLE
-- =====================================================
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(20) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `id_number` varchar(20) DEFAULT NULL,
  `customer_type` enum('individual','company') NOT NULL DEFAULT 'individual',
  `company_name` varchar(100) DEFAULT NULL,
  `tax_number` varchar(30) DEFAULT NULL,
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `payment_terms` int(11) DEFAULT 0,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_phone` (`phone`),
  KEY `idx_email` (`email`),
  KEY `idx_customer_type` (`customer_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VEHICLES TABLE
-- =====================================================
CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `license_plate` varchar(20) NOT NULL UNIQUE,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` int(4) NOT NULL,
  `color` varchar(30) DEFAULT NULL,
  `engine_number` varchar(50) DEFAULT NULL,
  `chassis_number` varchar(50) DEFAULT NULL,
  `fuel_type` enum('gasoline','diesel','electric','hybrid') DEFAULT 'gasoline',
  `transmission` enum('manual','automatic','cvt') DEFAULT 'manual',
  `mileage` int(11) DEFAULT 0,
  `insurance_company` varchar(100) DEFAULT NULL,
  `insurance_policy` varchar(50) DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_brand_model` (`brand`, `model`),
  KEY `idx_year` (`year`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_vehicles_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REMEMBER TOKENS TABLE
-- =====================================================
CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- LOGIN ATTEMPTS TABLE
-- =====================================================
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_attempted_at` (`attempted_at`),
  CONSTRAINT `fk_login_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- USER ACTIVITIES TABLE
-- =====================================================
CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_user_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- WORK ORDERS TABLE
-- =====================================================
CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_number` varchar(20) NOT NULL UNIQUE,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `estimated_amount` decimal(15,2) DEFAULT 0.00,
  `final_amount` decimal(15,2) DEFAULT 0.00,
  `estimated_completion_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `assigned_technician_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_vehicle_id` (`vehicle_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_assigned_technician` (`assigned_technician_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_work_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_work_orders_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_work_orders_technician` FOREIGN KEY (`assigned_technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INVENTORY CATEGORIES TABLE
-- =====================================================
CREATE TABLE `inventory_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INVENTORY TABLE
-- =====================================================
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(20) NOT NULL UNIQUE,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `purchase_price` decimal(15,2) DEFAULT 0.00,
  `selling_price` decimal(15,2) DEFAULT 0.00,
  `current_stock` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 0,
  `maximum_stock` int(11) DEFAULT 0,
  `location` varchar(100) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_name` (`name`),
  KEY `idx_current_stock` (`current_stock`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_inventory_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ENHANCED BODY REPAIR & PAINT WORKSHOP TABLES
-- =====================================================

-- DAMAGE ASSESSMENT TABLE
CREATE TABLE `damage_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `assessment_date` date NOT NULL,
  `assessor_id` int(11) NOT NULL,
  `damage_type` enum('minor_scratch','deep_scratch','dent','major_damage','paint_fade','rust','collision') NOT NULL,
  `damage_location` varchar(100) NOT NULL,
  `damage_severity` enum('light','moderate','severe','total') NOT NULL DEFAULT 'moderate',
  `estimated_repair_time` int(11) DEFAULT NULL COMMENT 'in hours',
  `estimated_cost` decimal(15,2) DEFAULT 0.00,
  `insurance_claim` tinyint(1) DEFAULT 0,
  `insurance_company` varchar(100) DEFAULT NULL,
  `claim_number` varchar(50) DEFAULT NULL,
  `photos_before` json DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_work_order_id` (`work_order_id`),
  KEY `idx_vehicle_id` (`vehicle_id`),
  KEY `idx_assessor_id` (`assessor_id`),
  KEY `idx_damage_type` (`damage_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_damage_assessments_work_order` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_damage_assessments_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_damage_assessments_assessor` FOREIGN KEY (`assessor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PAINT JOBS TABLE
CREATE TABLE `paint_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `paint_type` enum('base_coat','primer','clear_coat','full_paint','touch_up','custom') NOT NULL,
  `paint_brand` varchar(50) DEFAULT NULL,
  `paint_code` varchar(20) DEFAULT NULL,
  `color_name` varchar(100) DEFAULT NULL,
  `color_hex` varchar(7) DEFAULT NULL,
  `surface_area` decimal(8,2) DEFAULT NULL COMMENT 'in square meters',
  `layers_count` int(11) DEFAULT 1,
  `drying_time` int(11) DEFAULT NULL COMMENT 'in hours',
  `temperature_required` int(11) DEFAULT NULL COMMENT 'in celsius',
  `humidity_level` int(11) DEFAULT NULL COMMENT 'percentage',
  `booth_number` varchar(10) DEFAULT NULL,
  `painter_id` int(11) NOT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `quality_check` enum('pending','passed','failed','rework') DEFAULT 'pending',
  `quality_checker_id` int(11) DEFAULT NULL,
  `photos_progress` json DEFAULT NULL,
  `photos_final` json DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','drying','quality_check','completed','rework') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_work_order_id` (`work_order_id`),
  KEY `idx_vehicle_id` (`vehicle_id`),
  KEY `idx_painter_id` (`painter_id`),
  KEY `idx_quality_checker_id` (`quality_checker_id`),
  KEY `idx_paint_type` (`paint_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_paint_jobs_work_order` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_paint_jobs_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_paint_jobs_painter` FOREIGN KEY (`painter_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_paint_jobs_quality_checker` FOREIGN KEY (`quality_checker_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BODY REPAIR TASKS TABLE
CREATE TABLE `body_repair_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` int(11) NOT NULL,
  `damage_assessment_id` int(11) NOT NULL,
  `task_type` enum('dent_removal','panel_replacement','welding','sanding','filling','polishing','buffing') NOT NULL,
  `task_description` text NOT NULL,
  `technician_id` int(11) NOT NULL,
  `estimated_hours` decimal(5,2) DEFAULT NULL,
  `actual_hours` decimal(5,2) DEFAULT NULL,
  `tools_required` json DEFAULT NULL,
  `materials_used` json DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `quality_rating` enum('excellent','good','satisfactory','needs_improvement') DEFAULT NULL,
  `photos_before` json DEFAULT NULL,
  `photos_after` json DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','on_hold','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_work_order_id` (`work_order_id`),
  KEY `idx_damage_assessment_id` (`damage_assessment_id`),
  KEY `idx_technician_id` (`technician_id`),
  KEY `idx_task_type` (`task_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_body_repair_tasks_work_order` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_body_repair_tasks_damage_assessment` FOREIGN KEY (`damage_assessment_id`) REFERENCES `damage_assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_body_repair_tasks_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PAINT MATERIALS TABLE
CREATE TABLE `paint_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(20) NOT NULL UNIQUE,
  `material_type` enum('primer','base_coat','clear_coat','thinner','hardener','additive','sandpaper','masking_tape') NOT NULL,
  `brand` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `color_code` varchar(20) DEFAULT NULL,
  `color_name` varchar(100) DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'liter',
  `unit_price` decimal(15,2) NOT NULL,
  `current_stock` decimal(10,3) DEFAULT 0.000,
  `minimum_stock` decimal(10,3) DEFAULT 0.000,
  `shelf_life_days` int(11) DEFAULT NULL,
  `storage_temperature` varchar(50) DEFAULT NULL,
  `safety_notes` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_material_type` (`material_type`),
  KEY `idx_brand` (`brand`),
  KEY `idx_color_code` (`color_code`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WORKSHOP BAYS TABLE
CREATE TABLE `workshop_bays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bay_number` varchar(10) NOT NULL UNIQUE,
  `bay_type` enum('body_repair','paint_booth','drying_bay','preparation','quality_check') NOT NULL,
  `bay_name` varchar(100) NOT NULL,
  `capacity` int(11) DEFAULT 1,
  `equipment` json DEFAULT NULL,
  `temperature_control` tinyint(1) DEFAULT 0,
  `humidity_control` tinyint(1) DEFAULT 0,
  `ventilation_system` tinyint(1) DEFAULT 0,
  `current_work_order_id` int(11) DEFAULT NULL,
  `status` enum('available','occupied','maintenance','out_of_service') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bay_type` (`bay_type`),
  KEY `idx_status` (`status`),
  KEY `idx_current_work_order` (`current_work_order_id`),
  CONSTRAINT `fk_workshop_bays_work_order` FOREIGN KEY (`current_work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- QUALITY INSPECTIONS TABLE
CREATE TABLE `quality_inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` int(11) NOT NULL,
  `inspection_type` enum('initial','progress','final','customer_review') NOT NULL,
  `inspector_id` int(11) NOT NULL,
  `inspection_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paint_quality` enum('excellent','good','satisfactory','poor') DEFAULT NULL,
  `body_work_quality` enum('excellent','good','satisfactory','poor') DEFAULT NULL,
  `color_match` enum('perfect','good','acceptable','poor') DEFAULT NULL,
  `finish_quality` enum('excellent','good','satisfactory','poor') DEFAULT NULL,
  `overall_rating` enum('excellent','good','satisfactory','poor') NOT NULL,
  `defects_found` json DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `customer_signature` varchar(255) DEFAULT NULL,
  `inspector_notes` text DEFAULT NULL,
  `customer_feedback` text DEFAULT NULL,
  `rework_required` tinyint(1) DEFAULT 0,
  `rework_notes` text DEFAULT NULL,
  `status` enum('passed','failed','conditional','pending_rework') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_work_order_id` (`work_order_id`),
  KEY `idx_inspector_id` (`inspector_id`),
  KEY `idx_inspection_type` (`inspection_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_quality_inspections_work_order` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quality_inspections_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MATERIAL USAGE TRACKING TABLE
CREATE TABLE `material_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_order_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_used` decimal(10,3) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `used_by` int(11) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `task_type` enum('body_repair','paint_job','preparation','finishing') NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_work_order_id` (`work_order_id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_used_by` (`used_by`),
  KEY `idx_task_type` (`task_type`),
  CONSTRAINT `fk_material_usage_work_order` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_material_usage_material` FOREIGN KEY (`material_id`) REFERENCES `paint_materials` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_usage_user` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE DATA FOR WORKSHOP BAYS
-- =====================================================
INSERT INTO `workshop_bays` (`bay_number`, `bay_type`, `bay_name`, `capacity`, `equipment`, `temperature_control`, `humidity_control`, `ventilation_system`, `status`) VALUES
('BR01', 'body_repair', 'Body Repair Bay 1', 1, '["hydraulic_lift", "welding_station", "air_compressor", "dent_removal_tools"]', 0, 0, 1, 'available'),
('BR02', 'body_repair', 'Body Repair Bay 2', 1, '["hydraulic_lift", "panel_beating_tools", "sanding_equipment"]', 0, 0, 1, 'available'),
('PB01', 'paint_booth', 'Paint Booth 1', 1, '["spray_guns", "air_filtration", "lighting_system", "temperature_control"]', 1, 1, 1, 'available'),
('PB02', 'paint_booth', 'Paint Booth 2', 1, '["spray_guns", "air_filtration", "lighting_system", "temperature_control"]', 1, 1, 1, 'available'),
('DB01', 'drying_bay', 'Drying Bay 1', 2, '["infrared_lamps", "ventilation_fans"]', 1, 0, 1, 'available'),
('PREP01', 'preparation', 'Preparation Bay 1', 1, '["sanding_tools", "masking_materials", "cleaning_station"]', 0, 0, 1, 'available'),
('QC01', 'quality_check', 'Quality Control Bay', 1, '["inspection_lights", "color_matching_booth", "measurement_tools"]', 0, 0, 0, 'available');

-- =====================================================
-- SAMPLE PAINT MATERIALS DATA
-- =====================================================
INSERT INTO `paint_materials` (`item_code`, `material_type`, `brand`, `product_name`, `color_code`, `color_name`, `unit`, `unit_price`, `current_stock`, `minimum_stock`) VALUES
('PRM001', 'primer', 'PPG', 'Universal Primer Gray', 'PRM-GRY', 'Gray Primer', 'liter', 85000.00, 25.500, 5.000),
('BC001', 'base_coat', 'Sikkens', 'Autobase Plus', 'BC-WHT', 'Pure White', 'liter', 125000.00, 15.750, 3.000),
('BC002', 'base_coat', 'Sikkens', 'Autobase Plus', 'BC-BLK', 'Jet Black', 'liter', 125000.00, 12.250, 3.000),
('BC003', 'base_coat', 'Sikkens', 'Autobase Plus', 'BC-SLV', 'Silver Metallic', 'liter', 135000.00, 8.500, 2.000),
('CC001', 'clear_coat', 'PPG', 'Deltron Clear', 'CC-STD', 'Standard Clear', 'liter', 95000.00, 20.000, 4.000),
('THN001', 'thinner', 'Sikkens', 'Standard Thinner', 'THN-STD', 'Standard Thinner', 'liter', 45000.00, 35.000, 8.000),
('HRD001', 'hardener', 'PPG', 'Fast Hardener', 'HRD-FST', 'Fast Hardener', 'liter', 75000.00, 18.500, 4.000);

-- =====================================================
-- COMMIT TRANSACTION
-- =====================================================
COMMIT;
