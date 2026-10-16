
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `provider` varchar(32) NOT NULL,
  `environment` varchar(16) NOT NULL DEFAULT 'sandbox',
  `realm_id` varchar(64) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `access_expires_at` datetime DEFAULT NULL,
  `refresh_expires_at` datetime DEFAULT NULL,
  `mapping_json` text DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'disconnected',
  `last_error` text DEFAULT NULL,
  `connected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_provider` (`client_id`,`provider`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_customer_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `provider` varchar(20) NOT NULL,
  `realm_id` varchar(40) DEFAULT NULL,
  `account_client_id` int(11) NOT NULL,
  `external_id` varchar(40) NOT NULL,
  `external_name` varchar(190) NOT NULL DEFAULT '',
  `linked_by` int(11) DEFAULT NULL,
  `linked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aclink` (`client_id`,`provider`,`realm_id`,`account_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `provider` varchar(20) NOT NULL,
  `action` varchar(60) NOT NULL DEFAULT '',
  `result` varchar(20) NOT NULL DEFAULT '',
  `message` text DEFAULT NULL,
  `http_status` smallint(6) DEFAULT NULL,
  `provider_tid` varchar(80) DEFAULT NULL,
  `ref` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aclog_client_time` (`client_id`,`provider`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounting_sync` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `provider` varchar(20) NOT NULL,
  `realm_id` varchar(40) DEFAULT NULL,
  `entity` varchar(20) NOT NULL,
  `local_id` int(11) NOT NULL,
  `external_id` varchar(40) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `provider_tid` varchar(80) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fingerprint` varchar(64) DEFAULT NULL,
  `ref` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_acsync` (`client_id`,`provider`,`realm_id`,`entity`,`local_id`),
  KEY `idx_acsync_status` (`client_id`,`provider`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `allowance_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL DEFAULT 3,
  `table_name` varchar(64) NOT NULL,
  `key_norm` varchar(255) NOT NULL,
  `keys_display` varchar(500) NOT NULL,
  `value` double NOT NULL,
  `seq` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_allowance_client` (`client_id`,`table_name`,`key_norm`),
  KEY `idx_allowance_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `client_user_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `quote_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time NOT NULL,
  `duration_minutes` smallint(5) unsigned NOT NULL DEFAULT 60,
  `installation_address1` varchar(150) DEFAULT NULL,
  `installation_address2` varchar(150) DEFAULT NULL,
  `installation_town` varchar(100) DEFAULT NULL,
  `installation_county` varchar(100) DEFAULT NULL,
  `installation_postcode` varchar(20) DEFAULT NULL,
  `different_billing_address` tinyint(1) NOT NULL DEFAULT 0,
  `billing_address1` varchar(150) DEFAULT NULL,
  `billing_address2` varchar(150) DEFAULT NULL,
  `billing_town` varchar(100) DEFAULT NULL,
  `billing_county` varchar(100) DEFAULT NULL,
  `billing_postcode` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('booked','completed','cancelled','no_show') NOT NULL DEFAULT 'booked',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `access_note` varchar(280) DEFAULT NULL,
  `appt_kind` varchar(20) NOT NULL DEFAULT 'measure',
  `has_issue` tinyint(1) NOT NULL DEFAULT 0,
  `issue_note` varchar(280) DEFAULT NULL,
  `slot_window` varchar(2) DEFAULT NULL,
  `online_request` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_appointments_client_date` (`client_id`,`appointment_date`),
  KEY `idx_appointments_client_status` (`client_id`,`status`),
  KEY `idx_appointments_client_user` (`client_user_id`),
  KEY `idx_appointments_customer` (`customer_id`),
  KEY `idx_appointments_quote` (`quote_id`),
  CONSTRAINT `fk_appointments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_client_user` FOREIGN KEY (`client_user_id`) REFERENCES `client_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `build_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variable` varchar(64) NOT NULL,
  `formula` text NOT NULL,
  `seq` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_build_rules_product` (`product_id`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `build_variables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `seq` int(11) NOT NULL DEFAULT 0,
  `columns_json` text NOT NULL,
  `rows_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `label` varchar(80) DEFAULT NULL,
  `plumbing` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_build_var` (`product_id`,`name`),
  KEY `idx_build_var_product` (`product_id`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `catalogue_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `user_name` varchar(150) DEFAULT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `parent_product_id` int(10) unsigned DEFAULT NULL,
  `entity_label` varchar(200) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ca_client_created` (`client_id`,`created_at`),
  KEY `idx_ca_product_created` (`parent_product_id`,`created_at`),
  KEY `idx_ca_entity` (`entity_type`,`entity_id`),
  KEY `idx_ca_actor` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_discounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `system_id_key` int(10) unsigned GENERATED ALWAYS AS (ifnull(`system_id`,0)) STORED,
  `discount_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_client_product_system` (`client_id`,`product_id`,`system_id_key`),
  KEY `fk_client_discounts_product` (`product_id`),
  KEY `fk_client_discounts_system` (`system_id`),
  CONSTRAINT `fk_client_discounts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_client_discounts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_client_discounts_system` FOREIGN KEY (`system_id`) REFERENCES `product_systems` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_library_suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `supplier_key` varchar(64) NOT NULL,
  `enabled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_imported_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_client_supplier` (`client_id`,`supplier_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_markups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `system_id_key` int(10) unsigned GENERATED ALWAYS AS (ifnull(`system_id`,0)) STORED,
  `markup_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_client_product_system` (`client_id`,`product_id`,`system_id_key`),
  KEY `fk_client_markups_product` (`product_id`),
  KEY `fk_client_markups_system` (`system_id`),
  CONSTRAINT `fk_client_markups_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_client_markups_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_client_markups_system` FOREIGN KEY (`system_id`) REFERENCES `product_systems` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_plan_overrides` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `plan_code` varchar(32) NOT NULL,
  `override_type` enum('comp','trial') NOT NULL DEFAULT 'comp',
  `expires_at` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_override` (`client_id`,`plan_code`),
  CONSTRAINT `fk_cpo_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `quote_prefix` varchar(20) DEFAULT NULL,
  `factory_order_colours` text DEFAULT NULL,
  `default_markup_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `vat_percent` decimal(5,2) NOT NULL DEFAULT 20.00,
  `email_from_name` varchar(150) DEFAULT NULL,
  `reply_to_email` varchar(150) DEFAULT NULL,
  `quote_footer` text DEFAULT NULL,
  `feature_maps` tinyint(1) NOT NULL DEFAULT 0,
  `feature_postcode_lookup` tinyint(1) NOT NULL DEFAULT 0,
  `feature_accounts` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `default_deposit_percent` decimal(5,2) NOT NULL DEFAULT 50.00,
  `default_deposit_mode` enum('percent','flat') NOT NULL DEFAULT 'percent',
  `default_deposit_flat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `default_price_table_markup_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `default_options_markup_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `default_measurement_unit` varchar(4) NOT NULL DEFAULT 'mm',
  `terms_conditions` longtext DEFAULT NULL,
  `trade_terms_conditions` text DEFAULT NULL,
  `privacy_policy` longtext DEFAULT NULL,
  `accept_email_body` longtext DEFAULT NULL,
  `job_status_colours` text DEFAULT NULL,
  `feature_joke_of_day` tinyint(1) NOT NULL DEFAULT 1,
  `calendar_show_money` tinyint(1) NOT NULL DEFAULT 0,
  `supplier_delivery_address` text DEFAULT NULL,
  `feature_price_library` tinyint(1) NOT NULL DEFAULT 0,
  `map_provider` varchar(10) NOT NULL DEFAULT 'google',
  `show_line_prices` tinyint(1) NOT NULL DEFAULT 1,
  `bank_account_name` varchar(120) DEFAULT NULL,
  `bank_sort_code` varchar(12) DEFAULT NULL,
  `bank_account_number` varchar(40) DEFAULT NULL,
  `payment_instructions` varchar(500) DEFAULT NULL,
  `pricing_basis` varchar(10) NOT NULL DEFAULT 'markup',
  `feature_wt` tinyint(1) NOT NULL DEFAULT 0,
  `feature_auto_receipt` tinyint(1) NOT NULL DEFAULT 1,
  `feature_ampm_slots` tinyint(1) NOT NULL DEFAULT 0,
  `ampm_slot_capacity` int(11) NOT NULL DEFAULT 4,
  `ampm_am_start` time NOT NULL DEFAULT '09:00:00',
  `ampm_am_end` time NOT NULL DEFAULT '13:00:00',
  `ampm_pm_start` time NOT NULL DEFAULT '13:00:00',
  `ampm_pm_end` time NOT NULL DEFAULT '17:00:00',
  `ampm_am_capacity` int(11) NOT NULL DEFAULT 4,
  `ampm_pm_capacity` int(11) NOT NULL DEFAULT 4,
  `auto_place_inhouse` tinyint(1) NOT NULL DEFAULT 1,
  `default_sale_type` varchar(8) DEFAULT NULL,
  `order_notify_email` varchar(190) DEFAULT NULL,
  `factory_notify_email` varchar(190) DEFAULT NULL,
  `auto_send_suppliers` tinyint(1) NOT NULL DEFAULT 0,
  `show_line_sizes` tinyint(1) NOT NULL DEFAULT 1,
  `ampm_windows_json` text DEFAULT NULL,
  `feature_sms` tinyint(1) NOT NULL DEFAULT 0,
  `sms_sender_name` varchar(11) DEFAULT NULL,
  `sms_monthly_limit` int(11) DEFAULT NULL,
  `sms_booking_confirm` tinyint(1) NOT NULL DEFAULT 0,
  `sms_day_before` tinyint(1) NOT NULL DEFAULT 0,
  `sms_on_the_way` tinyint(1) NOT NULL DEFAULT 0,
  `sms_templates` text DEFAULT NULL,
  `qfu_on` tinyint(1) NOT NULL DEFAULT 0,
  `qfu_days` int(11) NOT NULL DEFAULT 7,
  `qfu_method` varchar(10) NOT NULL DEFAULT 'email',
  `process_skip_summary` tinyint(1) NOT NULL DEFAULT 0,
  `floor_sees_lanes` tinyint(1) NOT NULL DEFAULT 0,
  `feature_online_booking` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_client_settings_client` (`client_id`),
  CONSTRAINT `fk_client_settings_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_user_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `role` varchar(32) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_role` (`user_id`,`role`),
  KEY `idx_cur_user` (`user_id`),
  CONSTRAINT `fk_cur_user` FOREIGN KEY (`user_id`) REFERENCES `client_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `username` varchar(60) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `first_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','owner','office','sales','agent','readonly') NOT NULL DEFAULT 'sales',
  `can_create_quotes` tinyint(1) NOT NULL DEFAULT 1,
  `can_create_orders` tinyint(1) NOT NULL DEFAULT 0,
  `can_factory_office` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_all_customer_jobs` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_costs` tinyint(1) NOT NULL DEFAULT 0,
  `dash_view_revenue` tinyint(1) NOT NULL DEFAULT 0,
  `dash_view_team` tinyint(1) NOT NULL DEFAULT 0,
  `dash_view_products` tinyint(1) NOT NULL DEFAULT 0,
  `dash_view_profit` tinyint(1) NOT NULL DEFAULT 0,
  `dash_view_recent` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `home_address1` varchar(150) DEFAULT NULL,
  `home_address2` varchar(150) DEFAULT NULL,
  `home_town` varchar(100) DEFAULT NULL,
  `home_county` varchar(100) DEFAULT NULL,
  `home_postcode` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `can_view_fittings_only` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` datetime DEFAULT NULL,
  `factory_station_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_client_user_email` (`email`),
  UNIQUE KEY `uniq_client_user_username` (`username`),
  KEY `idx_client_users_client` (`client_id`),
  KEY `idx_client_users_role` (`role`),
  CONSTRAINT `fk_client_users_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) NOT NULL,
  `account_code` varchar(50) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `vat_number` varchar(50) DEFAULT NULL,
  `address1` varchar(150) DEFAULT NULL,
  `address2` varchar(150) DEFAULT NULL,
  `town` varchar(100) DEFAULT NULL,
  `county` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `order_destination` enum('beverley_blinds','customer_office','both') NOT NULL DEFAULT 'beverley_blinds',
  `quote_destination` enum('customer_office','both','none') NOT NULL DEFAULT 'customer_office',
  `office_order_email` varchar(150) DEFAULT NULL,
  `office_quote_email` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_backup_at` datetime DEFAULT NULL,
  `is_factory` tinyint(1) NOT NULL DEFAULT 0,
  `notify_consultant_orders` tinyint(4) NOT NULL DEFAULT 0,
  `account_ref` varchar(32) DEFAULT NULL,
  `mobile` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_clients_active` (`active`),
  KEY `idx_clients_company` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_discounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customer_product_discount` (`customer_id`,`product_id`),
  KEY `idx_discounts_product` (`product_id`),
  KEY `fk_discounts_client` (`client_id`),
  CONSTRAINT `fk_discounts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_discounts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_discounts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_markups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `markup_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customer_product_markup` (`customer_id`,`product_id`),
  KEY `idx_markups_product` (`product_id`),
  KEY `fk_markups_client` (`client_id`),
  CONSTRAINT `fk_markups_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_markups_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_markups_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `has_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `address1` varchar(150) DEFAULT NULL,
  `address2` varchar(150) DEFAULT NULL,
  `town` varchar(100) DEFAULT NULL,
  `county` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `default_markup_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `default_discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mobile` varchar(40) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `sms_opt_out` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_customers_client` (`client_id`),
  KEY `idx_customers_client_name` (`client_id`,`name`),
  KEY `idx_customers_client_email` (`client_id`,`email`),
  KEY `idx_customers_client_creator` (`client_id`,`created_by_user_id`),
  CONSTRAINT `fk_customers_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_verifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ev_token` (`token_hash`),
  KEY `idx_ev_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `extra_choice_price_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_extra_choice_id` int(10) unsigned NOT NULL,
  `width_mm` int(10) unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_choice_width` (`product_extra_choice_id`,`width_mm`),
  KEY `idx_lookup` (`product_extra_choice_id`,`width_mm`),
  CONSTRAINT `fk_extra_choice_price_rows_choice` FOREIGN KEY (`product_extra_choice_id`) REFERENCES `product_extra_choices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fabric_supplier_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fabric_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `group_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_account_delivery` (
  `account_client_id` int(11) NOT NULL,
  `delivery_method` varchar(12) NOT NULL DEFAULT 'carrier',
  `no_delivery_charge` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_credit_note_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `credit_note_id` int(11) NOT NULL,
  `source_invoice_line_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `width_mm` int(11) DEFAULT NULL,
  `drop_mm` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_net` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_net` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cnl_cn` (`credit_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_credit_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `account_client_id` int(11) NOT NULL,
  `cn_number` varchar(32) NOT NULL,
  `against_invoice_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'issued',
  `issue_date` date DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `vat_percent` decimal(5,2) NOT NULL DEFAULT 20.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `settle_mode` varchar(16) NOT NULL DEFAULT 'credit',
  `refund_payment_id` int(11) DEFAULT NULL,
  `bill_to_snapshot` text DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cn_number` (`factory_client_id`,`cn_number`),
  KEY `idx_cn_account` (`account_client_id`,`status`),
  KEY `idx_cn_invoice` (`against_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_delivery_note_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_note_id` int(11) NOT NULL,
  `source_quote_item_id` int(11) DEFAULT NULL,
  `product_name` varchar(190) DEFAULT NULL,
  `system_name` varchar(190) DEFAULT NULL,
  `fabric` varchar(190) DEFAULT NULL,
  `band_code` varchar(64) DEFAULT NULL,
  `width_mm` int(11) DEFAULT NULL,
  `drop_mm` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `room` varchar(190) DEFAULT NULL,
  `options_snapshot` text DEFAULT NULL,
  `line_notes` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_dnl_dn` (`delivery_note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_delivery_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `account_client_id` int(11) NOT NULL,
  `dn_number` varchar(32) NOT NULL,
  `source_quote_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `dispatched_at` datetime DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `delivery_method` varchar(12) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `carriage_net` decimal(10,2) NOT NULL DEFAULT 0.00,
  `carriage_override` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dn_number` (`factory_client_id`,`dn_number`),
  KEY `idx_dn_account` (`account_client_id`,`status`),
  KEY `idx_dn_quote` (`source_quote_id`),
  KEY `idx_dn_delivery` (`account_client_id`,`delivery_date`,`delivery_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_invoice_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `source_quote_id` int(11) DEFAULT NULL,
  `source_quote_item_id` int(11) DEFAULT NULL,
  `line_type` varchar(16) NOT NULL DEFAULT 'blind',
  `description` varchar(255) NOT NULL,
  `width_mm` int(11) DEFAULT NULL,
  `drop_mm` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_net` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_net` decimal(10,2) NOT NULL DEFAULT 0.00,
  `list_trade_unit` decimal(10,2) DEFAULT NULL,
  `discount_percent` decimal(6,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_invl_invoice` (`invoice_id`),
  KEY `idx_invl_srcitem` (`source_quote_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_invoice_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `quote_id` int(11) NOT NULL,
  `factory_client_id` int(11) DEFAULT NULL,
  `live_quote_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_order` (`invoice_id`,`quote_id`),
  UNIQUE KEY `uq_io_live` (`live_quote_id`,`factory_client_id`),
  KEY `idx_io_quote` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `account_client_id` int(11) NOT NULL,
  `inv_number` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `vat_percent` decimal(5,2) NOT NULL DEFAULT 20.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bill_to_snapshot` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `replaced_by_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_number` (`factory_client_id`,`inv_number`),
  KEY `idx_inv_account` (`account_client_id`,`status`),
  KEY `idx_inv_status` (`status`),
  KEY `idx_inv_issue` (`issue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_payment_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payment` (`payment_id`),
  KEY `idx_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `account_client_id` int(11) NOT NULL,
  `pay_number` varchar(32) NOT NULL,
  `payment_date` date NOT NULL,
  `method` varchar(32) NOT NULL DEFAULT 'bank',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_number` (`factory_client_id`,`pay_number`),
  KEY `idx_acc` (`factory_client_id`,`account_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_ar_statement_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `account_client_id` int(11) NOT NULL,
  `period_to` date NOT NULL,
  `to_email` varchar(255) DEFAULT NULL,
  `closing` decimal(10,2) DEFAULT NULL,
  `status` varchar(16) NOT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_run` (`factory_client_id`,`period_to`),
  KEY `idx_acct` (`factory_client_id`,`account_client_id`,`period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_bank_payer_aliases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `payer_key` varchar(64) NOT NULL,
  `payer_label` varchar(64) DEFAULT NULL,
  `account_client_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payer` (`factory_client_id`,`payer_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `provider` varchar(20) NOT NULL DEFAULT 'lunchflow',
  `provider_txn_id` varchar(191) NOT NULL,
  `bank_account_id` varchar(64) DEFAULT NULL,
  `txn_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(8) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `merchant` varchar(255) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'new',
  `payment_id` int(11) DEFAULT NULL,
  `actioned_by` int(11) DEFAULT NULL,
  `actioned_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_txn` (`factory_client_id`,`provider`,`provider_txn_id`),
  KEY `idx_bank_date` (`factory_client_id`,`txn_date`),
  KEY `idx_bank_payment` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_blind_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `quote_item_id` int(11) NOT NULL,
  `unit_no` int(11) NOT NULL DEFAULT 1,
  `product_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `route_step_id` int(11) DEFAULT NULL,
  `station_id` int(11) DEFAULT NULL,
  `seq` int(11) NOT NULL DEFAULT 0,
  `status` varchar(16) NOT NULL DEFAULT 'queued',
  `started_at` datetime DEFAULT NULL,
  `step_started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `marshalled_at` datetime DEFAULT NULL,
  `marshalled_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_unit` (`quote_item_id`,`unit_no`),
  KEY `idx_station` (`station_id`,`status`),
  KEY `idx_quote` (`quote_id`),
  KEY `idx_fbj_area` (`area_id`),
  KEY `idx_fbj_marshalled` (`marshalled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_blind_streams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blind_job_id` int(11) NOT NULL,
  `stream` varchar(40) NOT NULL DEFAULT 'main',
  `route_step_id` int(11) DEFAULT NULL,
  `station_id` int(11) DEFAULT NULL,
  `seq` int(11) NOT NULL DEFAULT 0,
  `status` varchar(16) NOT NULL DEFAULT 'queued',
  `started_at` datetime DEFAULT NULL,
  `step_started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `area_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_stream` (`blind_job_id`,`stream`),
  KEY `idx_stream_station` (`station_id`,`status`),
  KEY `idx_stream_job` (`blind_job_id`),
  KEY `idx_fbs_area` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'received',
  `received_at` datetime DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status_at` datetime DEFAULT NULL,
  `status_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_factory_jobs_quote` (`quote_id`),
  KEY `idx_factory_jobs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_kv` (
  `k` varchar(64) NOT NULL,
  `v` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_office_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `kind` varchar(10) NOT NULL DEFAULT 'note',
  `entry_date` date NOT NULL,
  `entry_time` time DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `detail` text DEFAULT NULL,
  `account_client_id` int(11) DEFAULT NULL,
  `order_ref` varchar(60) DEFAULT NULL,
  `quote_id` int(11) DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `done_by_user_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_oe_day` (`factory_client_id`,`entry_date`),
  KEY `idx_oe_open` (`factory_client_id`,`done_at`,`kind`),
  KEY `idx_oe_account` (`account_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_remake_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remake_id` int(11) NOT NULL,
  `source_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_rmi_remake` (`remake_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_remake_reasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `label` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rr_factory` (`factory_client_id`,`active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_remakes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factory_client_id` int(11) NOT NULL,
  `account_client_id` int(11) NOT NULL,
  `source_quote_id` int(11) NOT NULL,
  `remake_quote_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'requested',
  `reason_id` int(11) DEFAULT NULL,
  `reason_label` varchar(120) NOT NULL DEFAULT '',
  `note` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `raised_by` varchar(10) NOT NULL DEFAULT 'factory',
  `raised_by_user_id` int(11) DEFAULT NULL,
  `decided_by_user_id` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decline_reason` varchar(255) DEFAULT NULL,
  `charge_mode` varchar(10) DEFAULT NULL,
  `charge_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `supplier_name` varchar(120) DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_by_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rm_factory` (`factory_client_id`,`status`,`created_at`),
  KEY `idx_rm_source` (`source_quote_id`),
  KEY `idx_rm_remake` (`remake_quote_id`),
  KEY `idx_rm_account` (`account_client_id`),
  KEY `idx_rm_factory_status` (`factory_client_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_scan_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `quote_item_id` int(11) DEFAULT NULL,
  `unit_no` int(11) DEFAULT NULL,
  `stream_digit` int(11) DEFAULT NULL,
  `result` varchar(16) NOT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `source` varchar(60) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scan_recent` (`quote_item_id`,`unit_no`,`created_at`),
  KEY `idx_scan_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `help_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(160) NOT NULL,
  `url` varchar(500) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `library_fabric_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fabric_supplier_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sup_name` (`fabric_supplier_id`,`name`),
  KEY `idx_supplier` (`fabric_supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `library_fabrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fabric_supplier_id` int(11) NOT NULL,
  `name` varchar(160) NOT NULL,
  `colour` varchar(120) DEFAULT NULL,
  `code` varchar(80) DEFAULT NULL,
  `suggested_band` varchar(60) DEFAULT NULL,
  `blind_type` varchar(60) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_supplier` (`fabric_supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `library_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_key` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `prefix` varchar(40) NOT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `blurb` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_supplier_key` (`supplier_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `identifier` varchar(190) DEFAULT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip_time` (`ip_address`,`created_at`),
  KEY `idx_login_attempts_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `channel` varchar(10) NOT NULL DEFAULT 'sms',
  `direction` varchar(3) NOT NULL DEFAULT 'out',
  `kind` varchar(40) NOT NULL DEFAULT '',
  `ref_key` varchar(40) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `quote_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `to_addr` varchar(190) NOT NULL DEFAULT '',
  `from_addr` varchar(190) NOT NULL DEFAULT '',
  `body` text DEFAULT NULL,
  `segments` smallint(5) unsigned NOT NULL DEFAULT 1,
  `from_bundle` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `status_detail` varchar(255) DEFAULT NULL,
  `provider` varchar(20) DEFAULT NULL,
  `provider_ref` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_msg_client_time` (`client_id`,`created_at`),
  KEY `idx_msg_provider_ref` (`provider_ref`),
  KEY `idx_msg_appt_kind` (`appointment_id`,`kind`),
  KEY `idx_msg_customer` (`customer_id`),
  KEY `idx_msg_appt_kind_ref` (`appointment_id`,`kind`,`ref_key`),
  KEY `idx_msg_quote_kind` (`quote_id`,`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `offline_refs` (
  `client_id` int(10) unsigned NOT NULL,
  `kind` varchar(16) NOT NULL,
  `ref` varchar(64) NOT NULL,
  `target_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`client_id`,`kind`,`ref`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `online_address_lookups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `postcode` varchar(8) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_oal_client_time` (`client_id`,`created_at`),
  KEY `idx_oal_ip_time` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `online_booking_settings` (
  `client_id` int(10) unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `slug` varchar(60) DEFAULT NULL,
  `mode` varchar(10) NOT NULL DEFAULT 'request',
  `min_notice_days` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `max_days_ahead` smallint(5) unsigned NOT NULL DEFAULT 28,
  `weekdays` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  `closed_dates` text DEFAULT NULL,
  `base_postcode` varchar(10) DEFAULT NULL,
  `radius_miles` smallint(5) unsigned DEFAULT NULL,
  `districts` text DEFAULT NULL,
  `intro_text` text DEFAULT NULL,
  `notify_email` varchar(190) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`client_id`),
  UNIQUE KEY `uq_obs_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `online_bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `appointment_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `matched_existing` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(12) NOT NULL DEFAULT 'requested',
  `area_check` varchar(12) NOT NULL DEFAULT 'none',
  `distance_miles` decimal(6,1) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) DEFAULT NULL,
  `submitted` text DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ob_client_time` (`client_id`,`created_at`),
  KEY `idx_ob_ip_time` (`ip_address`,`created_at`),
  KEY `idx_ob_appt` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_password_resets_token` (`token_hash`),
  KEY `idx_password_resets_user` (`user_id`),
  KEY `idx_password_resets_expires` (`expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `client_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `quote_id` int(10) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `received_at` date NOT NULL,
  `method` varchar(32) NOT NULL DEFAULT 'bank_transfer',
  `is_deposit` tinyint(1) NOT NULL DEFAULT 0,
  `reference` varchar(200) DEFAULT NULL,
  `payer_name` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payments_client` (`client_id`),
  KEY `idx_payments_quote` (`quote_id`),
  KEY `idx_payments_customer` (`customer_id`),
  KEY `idx_payments_date` (`received_at`),
  KEY `idx_pay_quote` (`quote_id`),
  CONSTRAINT `fk_payments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paypal_webhook_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(80) NOT NULL,
  `event_id` varchar(80) DEFAULT NULL,
  `subscription_id` varchar(80) DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `plan_code` varchar(32) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `outcome` varchar(40) DEFAULT NULL,
  `payload_excerpt` text DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pwl_received` (`received_at`),
  KEY `idx_pwl_event` (`event_type`,`received_at`),
  KEY `idx_pwl_client` (`client_id`,`received_at`),
  KEY `idx_pwl_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_pricing` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_code` varchar(32) NOT NULL,
  `price_gbp_monthly` decimal(10,2) NOT NULL,
  `paypal_plan_id` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plan_pricing` (`plan_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_config` (
  `name` varchar(64) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `postcode_geo` (
  `postcode` varchar(8) NOT NULL,
  `found` tinyint(1) NOT NULL DEFAULT 1,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `fetched_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`postcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_bump_undo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `scope` varchar(80) NOT NULL,
  `label` varchar(200) NOT NULL,
  `table_ids` mediumtext NOT NULL,
  `prices_json` mediumtext NOT NULL,
  `after_hash` char(40) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scope` (`client_id`,`scope`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_change_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `scope` varchar(20) NOT NULL,
  `target` varchar(160) NOT NULL,
  `pct` decimal(7,2) NOT NULL,
  `cells_changed` int(11) NOT NULL DEFAULT 0,
  `changed_by` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_table_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `price_table_id` int(10) unsigned NOT NULL,
  `width_mm` int(10) unsigned NOT NULL,
  `drop_mm` int(10) unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cell` (`price_table_id`,`width_mm`,`drop_mm`),
  KEY `idx_rows_lookup` (`price_table_id`,`width_mm`,`drop_mm`),
  KEY `idx_ptr_table_dims` (`price_table_id`,`width_mm`,`drop_mm`),
  CONSTRAINT `fk_rows_table` FOREIGN KEY (`price_table_id`) REFERENCES `price_tables` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_table_undo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `price_table_id` int(10) unsigned NOT NULL,
  `label` varchar(120) NOT NULL,
  `prices_json` mediumtext NOT NULL,
  `after_hash` char(40) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_table` (`price_table_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_tables` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `system_id` int(10) unsigned NOT NULL,
  `band_code` varchar(60) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_table_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_price_table_product_system_band` (`client_id`,`product_id`,`system_id`,`band_code`),
  KEY `idx_price_tables_product` (`product_id`),
  KEY `fk_price_tables_system` (`system_id`),
  KEY `idx_source_table` (`source_table_id`),
  CONSTRAINT `fk_price_tables_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_price_tables_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_price_tables_system` FOREIGN KEY (`system_id`) REFERENCES `product_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_undo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `scope` varchar(80) NOT NULL,
  `label` varchar(200) NOT NULL,
  `before_ids` mediumtext NOT NULL,
  `rows_json` mediumtext NOT NULL,
  `after_ids` mediumtext DEFAULT NULL,
  `after_hash` char(40) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scope` (`client_id`,`scope`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_area_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stream` varchar(40) NOT NULL DEFAULT 'main',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pam_product_stream` (`product_id`,`stream`),
  KEY `idx_pam_area` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_extra_choice_bands` (
  `choice_id` int(10) unsigned NOT NULL,
  `band_code` varchar(60) NOT NULL,
  PRIMARY KEY (`choice_id`,`band_code`),
  KEY `ix_pecb_band` (`band_code`),
  CONSTRAINT `fk_pecb_choice` FOREIGN KEY (`choice_id`) REFERENCES `product_extra_choices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_extra_choice_options` (
  `choice_id` int(10) unsigned NOT NULL,
  `option_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`choice_id`,`option_id`),
  KEY `idx_option` (`option_id`),
  CONSTRAINT `fk_peco_choice` FOREIGN KEY (`choice_id`) REFERENCES `product_extra_choices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_peco_option` FOREIGN KEY (`option_id`) REFERENCES `product_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_extra_choice_systems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_extra_choice_id` int(10) unsigned NOT NULL,
  `product_system_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pecs_choice_system` (`product_extra_choice_id`,`product_system_id`),
  KEY `idx_pecs_choice` (`product_extra_choice_id`),
  KEY `idx_pecs_system` (`product_system_id`),
  CONSTRAINT `fk_pecs_choice` FOREIGN KEY (`product_extra_choice_id`) REFERENCES `product_extra_choices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pecs_system` FOREIGN KEY (`product_system_id`) REFERENCES `product_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_extra_choices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_extra_id` int(10) unsigned NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `label` varchar(150) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `length_input_label` varchar(60) DEFAULT NULL,
  `price_delta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `price_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `price_per_metre` decimal(10,2) NOT NULL DEFAULT 0.00,
  `face_value` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `markup_pct_override` decimal(5,2) DEFAULT NULL,
  `per_metre_basis` varchar(20) NOT NULL DEFAULT 'width',
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `source_choice_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_choices_extra` (`product_extra_id`),
  KEY `idx_choices_system` (`system_id`),
  KEY `idx_pec_extra_active` (`product_extra_id`,`active`),
  KEY `idx_source_choice` (`source_choice_id`),
  CONSTRAINT `fk_choices_extra` FOREIGN KEY (`product_extra_id`) REFERENCES `product_extras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_choices_system` FOREIGN KEY (`system_id`) REFERENCES `product_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_extra_parent_choices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_extra_id` int(10) unsigned NOT NULL,
  `product_extra_choice_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pepc_pair` (`product_extra_id`,`product_extra_choice_id`),
  KEY `idx_pepc_extra` (`product_extra_id`),
  KEY `idx_pepc_choice` (`product_extra_choice_id`),
  CONSTRAINT `fk_pepc_choice` FOREIGN KEY (`product_extra_choice_id`) REFERENCES `product_extra_choices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pepc_extra` FOREIGN KEY (`product_extra_id`) REFERENCES `product_extras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_extra_systems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_extra_id` int(10) unsigned NOT NULL,
  `product_system_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pes_extra_system` (`product_extra_id`,`product_system_id`),
  KEY `idx_pes_extra` (`product_extra_id`),
  KEY `idx_pes_system` (`product_system_id`),
  CONSTRAINT `fk_pes_extra` FOREIGN KEY (`product_extra_id`) REFERENCES `product_extras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pes_system` FOREIGN KEY (`product_system_id`) REFERENCES `product_systems` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_extras` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `parent_choice_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `allow_multi` tinyint(1) NOT NULL DEFAULT 0,
  `collapse_multi` tinyint(1) NOT NULL DEFAULT 0,
  `before_size` tinyint(1) NOT NULL DEFAULT 0,
  `length_input_label` varchar(60) DEFAULT NULL,
  `is_width_source` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parent_match_all` tinyint(1) NOT NULL DEFAULT 0,
  `source_extra_id` int(10) unsigned DEFAULT NULL,
  `joinable` tinyint(1) NOT NULL DEFAULT 0,
  `splits_panels` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_extras_product` (`product_id`),
  KEY `idx_extras_parent_choice` (`parent_choice_id`),
  KEY `idx_extras_client` (`client_id`),
  KEY `idx_source_extra` (`source_extra_id`),
  CONSTRAINT `fk_extras_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_extras_parent_choice` FOREIGN KEY (`parent_choice_id`) REFERENCES `product_extra_choices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_extras_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_lead_times` (
  `product_id` int(11) NOT NULL,
  `lead_days` int(11) NOT NULL DEFAULT 10,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `band_code` varchar(60) NOT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `colour` varchar(150) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost_price` decimal(10,2) DEFAULT NULL,
  `fabric_group` varchar(120) DEFAULT NULL,
  `source_option_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_option_per_product` (`client_id`,`product_id`,`band_code`,`supplier_name`,`name`,`colour`),
  KEY `idx_options_product_band` (`product_id`,`band_code`),
  KEY `idx_product_options_system_id` (`system_id`),
  KEY `idx_po_product_active` (`product_id`,`active`),
  KEY `idx_source_option` (`source_option_id`),
  CONSTRAINT `fk_options_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_options_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_options_system` FOREIGN KEY (`system_id`) REFERENCES `product_systems` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_route_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `stream` varchar(40) DEFAULT NULL,
  `seq` int(11) NOT NULL DEFAULT 0,
  `station_id` int(11) DEFAULT NULL,
  `label` varchar(80) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_route_product` (`product_id`,`seq`),
  KEY `idx_route_station` (`station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_systems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_system_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_system_per_product` (`client_id`,`product_id`,`name`),
  KEY `idx_systems_product` (`product_id`),
  KEY `idx_source_system` (`source_system_id`),
  CONSTRAINT `fk_systems_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_systems_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `production_areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `name` varchar(60) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `scan_key` varchar(64) DEFAULT NULL,
  `scanner_name` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pa_client` (`client_id`),
  KEY `idx_pa_scankey` (`scan_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `option_label` varchar(50) NOT NULL DEFAULT 'Fabric',
  `requires_option` tinyint(1) NOT NULL DEFAULT 1,
  `width_only` tinyint(1) NOT NULL DEFAULT 0,
  `price_per_slat` tinyint(1) NOT NULL DEFAULT 0,
  `price_per_sqm` tinyint(1) NOT NULL DEFAULT 0,
  `min_area_m2` decimal(6,3) NOT NULL DEFAULT 0.000,
  `band_label` varchar(60) DEFAULT NULL,
  `band_start_first` tinyint(1) NOT NULL DEFAULT 1,
  `show_colour_field` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost_price` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `line_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
  `source_client_id` int(11) DEFAULT NULL,
  `source_product_id` int(11) DEFAULT NULL,
  `price_source` varchar(10) NOT NULL DEFAULT 'own',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_client_name` (`client_id`,`name`),
  KEY `idx_products_client_active` (`client_id`,`active`),
  KEY `idx_products_source_client` (`source_client_id`),
  KEY `idx_products_source` (`source_client_id`),
  CONSTRAINT `fk_products_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quote_item_extras` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quote_item_id` int(10) unsigned NOT NULL,
  `product_extra_id` int(10) unsigned DEFAULT NULL,
  `extra_name_snapshot` varchar(150) NOT NULL,
  `product_extra_choice_id` int(10) unsigned DEFAULT NULL,
  `choice_label_snapshot` varchar(150) DEFAULT NULL,
  `mode` enum('flat','percent','per_metre','width_table') NOT NULL,
  `amount_applied` decimal(10,2) NOT NULL DEFAULT 0.00,
  `trade_amount` decimal(10,2) DEFAULT NULL,
  `promo_discount_percent` decimal(6,2) DEFAULT NULL,
  `promo_discount_amount` decimal(10,2) DEFAULT NULL,
  `user_value` decimal(10,2) DEFAULT NULL,
  `cost_snapshot` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quote_item_extras_item` (`quote_item_id`),
  KEY `fk_qie_extra` (`product_extra_id`),
  KEY `fk_qie_choice` (`product_extra_choice_id`),
  CONSTRAINT `fk_qie_choice` FOREIGN KEY (`product_extra_choice_id`) REFERENCES `product_extra_choices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_qie_extra` FOREIGN KEY (`product_extra_id`) REFERENCES `product_extras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_qie_item` FOREIGN KEY (`quote_item_id`) REFERENCES `quote_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quote_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quote_id` int(10) unsigned NOT NULL,
  `line_no` int(10) unsigned NOT NULL DEFAULT 1,
  `product_id` int(10) unsigned DEFAULT NULL,
  `product_name_snapshot` varchar(150) NOT NULL,
  `system_id` int(10) unsigned DEFAULT NULL,
  `system_name_snapshot` varchar(150) DEFAULT NULL,
  `option_id` int(10) unsigned DEFAULT NULL,
  `fabric_band_snapshot` varchar(20) DEFAULT NULL,
  `fabric_supplier_snapshot` varchar(150) DEFAULT NULL,
  `fabric_name_snapshot` varchar(150) DEFAULT NULL,
  `fabric_colour_snapshot` varchar(150) DEFAULT NULL,
  `fabric_code_snapshot` varchar(50) DEFAULT NULL,
  `room_name` varchar(80) DEFAULT NULL,
  `fascia_group` varchar(8) DEFAULT NULL,
  `width_mm` int(10) unsigned NOT NULL,
  `drop_mm` int(10) unsigned NOT NULL,
  `width_matrix_mm` int(10) unsigned DEFAULT NULL,
  `drop_matrix_mm` int(10) unsigned DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `price_table_id` int(10) unsigned DEFAULT NULL,
  `price_table_row_id` int(10) unsigned DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `trade_price_per_blind` decimal(10,2) DEFAULT NULL,
  `trade_discount_percent` decimal(6,2) DEFAULT NULL,
  `trade_discount_amount` decimal(10,2) DEFAULT NULL,
  `cost_price_snapshot` decimal(10,2) DEFAULT NULL,
  `extras_cost_snapshot` decimal(10,2) DEFAULT NULL,
  `extras_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal_per_blind` decimal(10,2) NOT NULL DEFAULT 0.00,
  `markup_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sell_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quote_items_quote_line` (`quote_id`,`line_no`),
  KEY `fk_quote_items_product` (`product_id`),
  KEY `fk_quote_items_system` (`system_id`),
  KEY `fk_quote_items_option` (`option_id`),
  KEY `fk_quote_items_price_table` (`price_table_id`),
  KEY `fk_quote_items_price_row` (`price_table_row_id`),
  KEY `idx_qi_product` (`product_id`),
  KEY `idx_qi_quote` (`quote_id`),
  CONSTRAINT `fk_quote_items_option` FOREIGN KEY (`option_id`) REFERENCES `product_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_quote_items_price_row` FOREIGN KEY (`price_table_row_id`) REFERENCES `price_table_rows` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_quote_items_price_table` FOREIGN KEY (`price_table_id`) REFERENCES `price_tables` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_quote_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_quote_items_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_quote_items_system` FOREIGN KEY (`system_id`) REFERENCES `product_systems` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `account_client_id` int(11) DEFAULT NULL,
  `quote_number` varchar(40) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `end_customer_name` varchar(150) NOT NULL,
  `end_customer_email` varchar(150) DEFAULT NULL,
  `end_customer_phone` varchar(50) DEFAULT NULL,
  `has_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `end_customer_address1` varchar(150) DEFAULT NULL,
  `end_customer_address2` varchar(150) DEFAULT NULL,
  `end_customer_town` varchar(100) DEFAULT NULL,
  `end_customer_county` varchar(100) DEFAULT NULL,
  `end_customer_postcode` varchar(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `fulfilment_stage` varchar(20) DEFAULT NULL,
  `pre_paid_status` varchar(20) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(10,2) DEFAULT NULL,
  `deposit_paid_at` datetime DEFAULT NULL,
  `vat_percent` decimal(5,2) NOT NULL DEFAULT 20.00,
  `notes` text DEFAULT NULL,
  `public_token` varchar(64) NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `acceptance_signature_name` varchar(150) DEFAULT NULL,
  `acceptance_ip` varchar(45) DEFAULT NULL,
  `created_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `measurement_unit` varchar(4) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `wt_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `receipt_sent_at` datetime DEFAULT NULL,
  `customer_reference` varchar(100) DEFAULT NULL,
  `additional_reference` varchar(100) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `price_override` decimal(10,2) DEFAULT NULL,
  `end_customer_mobile` varchar(40) DEFAULT NULL,
  `sale_type` varchar(8) DEFAULT NULL,
  `factory_notified_at` datetime DEFAULT NULL,
  `supplier_ordered_at` datetime DEFAULT NULL,
  `supplier_received_at` datetime DEFAULT NULL,
  `remake_of_quote_id` int(11) DEFAULT NULL,
  `direct_order` tinyint(1) NOT NULL DEFAULT 0,
  `sold_for_amount` decimal(10,2) DEFAULT NULL,
  `sold_for_inc_vat` tinyint(1) DEFAULT NULL,
  `sold_for_net` decimal(10,2) DEFAULT NULL,
  `sold_for_gross` decimal(10,2) DEFAULT NULL,
  `acceptance_signature_png` mediumtext DEFAULT NULL,
  `acceptance_method` varchar(20) DEFAULT NULL,
  `acceptance_by_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_quote_number_per_client` (`client_id`,`quote_number`),
  UNIQUE KEY `uniq_quote_public_token` (`public_token`),
  KEY `idx_quotes_client_status_created` (`client_id`,`status`,`created_at`),
  KEY `idx_quotes_client_customer` (`client_id`,`customer_id`),
  KEY `fk_quotes_customer` (`customer_id`),
  KEY `fk_quotes_user` (`created_by_user_id`),
  KEY `idx_quotes_account` (`account_client_id`),
  KEY `idx_quotes_client_status` (`client_id`,`status`),
  KEY `idx_quotes_status` (`status`),
  KEY `idx_quotes_remake_of` (`remake_of_quote_id`),
  CONSTRAINT `fk_quotes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_quotes_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_quotes_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `client_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_consultants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sc_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_bundles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `texts` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'GBP',
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `paypal_order_id` varchar(64) DEFAULT NULL,
  `paypal_capture_id` varchar(64) DEFAULT NULL,
  `bought_by_user_id` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sms_bundle_order` (`paypal_order_id`),
  KEY `idx_sms_bundle_client` (`client_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `quote_id` int(11) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `item_count` int(11) NOT NULL DEFAULT 0,
  `sent_by_user_id` int(11) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ordered_by_factory_id` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_quote` (`client_id`,`quote_id`),
  KEY `quote_supplier` (`quote_id`,`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `supplier_name` varchar(160) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_supplier_per_client` (`client_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_ai_usage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` varchar(40) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_user_turn` tinyint(1) NOT NULL DEFAULT 0,
  `input_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `output_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `cache_write_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `cache_read_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `cost_usd` decimal(10,6) NOT NULL DEFAULT 0.000000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(190) DEFAULT NULL,
  `company_name` varchar(190) DEFAULT NULL,
  `user_email` varchar(190) DEFAULT NULL,
  `category` varchar(20) NOT NULL DEFAULT 'problem',
  `source` varchar(10) NOT NULL DEFAULT 'form',
  `message` text NOT NULL,
  `page_url` varchar(1000) DEFAULT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `app_version` varchar(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `viewport` varchar(40) DEFAULT NULL,
  `js_errors` mediumtext DEFAULT NULL,
  `breadcrumbs` mediumtext DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fix_requested_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_send_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `kind` varchar(30) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tsl_client_time` (`client_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `plan_code` varchar(32) NOT NULL DEFAULT 'free',
  `status` enum('trial','active','past_due','cancelled','expired') NOT NULL DEFAULT 'active',
  `trial_ends_at` datetime DEFAULT NULL,
  `current_period_start` date DEFAULT NULL,
  `current_period_end` date DEFAULT NULL,
  `external_provider` varchar(32) DEFAULT NULL,
  `external_subscription_id` varchar(200) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `commitment_end_at` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tenant_plan` (`client_id`,`plan_code`),
  CONSTRAINT `fk_sub_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trade_commissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `consultant_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `commission_percent` double NOT NULL DEFAULT 0,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tc_client` (`client_id`),
  KEY `idx_tc_consultant` (`consultant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trade_discount_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) DEFAULT NULL,
  `system_id` int(11) DEFAULT NULL,
  `system_name` varchar(150) DEFAULT NULL,
  `band_code` varchar(64) DEFAULT NULL,
  `extra_id` int(11) DEFAULT NULL,
  `extra_name` varchar(150) DEFAULT NULL,
  `choice_id` int(11) DEFAULT NULL,
  `choice_label` varchar(150) DEFAULT NULL,
  `old_pct` double DEFAULT NULL,
  `new_pct` double DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_by_name` varchar(150) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tda_client` (`client_id`,`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trade_discounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `system_id` int(11) DEFAULT NULL,
  `band_code` varchar(64) DEFAULT NULL,
  `extra_id` int(11) DEFAULT NULL,
  `choice_id` int(11) DEFAULT NULL,
  `discount_percent` double NOT NULL DEFAULT 0,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_td_client` (`client_id`),
  KEY `idx_td_client_product` (`client_id`,`product_id`),
  KEY `idx_td_extra` (`extra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trade_promotions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `system_id` int(11) DEFAULT NULL,
  `band_code` varchar(64) DEFAULT NULL,
  `extra_id` int(11) DEFAULT NULL,
  `choice_id` int(11) DEFAULT NULL,
  `discount_percent` double NOT NULL DEFAULT 0,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tp_product` (`product_id`),
  KEY `idx_tp_client` (`client_id`),
  KEY `idx_tp_window` (`active`,`starts_on`,`ends_on`),
  KEY `idx_tp_extra` (`extra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trial_grants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email_key` char(64) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `client_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tg_email` (`email_key`),
  KEY `idx_tg_ip_time` (`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_calendar_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token` (`token`),
  UNIQUE KEY `uniq_user` (`user_id`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_production_areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_upa` (`user_id`,`area_id`),
  KEY `idx_upa_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worksheet_template_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `name` varchar(120) DEFAULT NULL,
  `layout_json` mediumtext NOT NULL,
  `field_count` int(11) NOT NULL DEFAULT 0,
  `saved_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wtv_template` (`template_id`,`created_at`),
  KEY `idx_wtv_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worksheet_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `layout_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ws_tpl_product` (`product_id`,`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workstation_streams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stream` varchar(40) NOT NULL DEFAULT 'main',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ws` (`user_id`,`product_id`,`stream`),
  KEY `idx_ws_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

