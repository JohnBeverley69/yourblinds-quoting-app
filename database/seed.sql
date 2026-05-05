-- =============================================================================
-- YourBlinds — sample seed data (per-client model, UK / GBP)
-- Assumes schema.sql has been run.
--
-- Two trade clients, each with their own product catalogue, price tables,
-- vertical fabrics and markup/discount rules:
--   #1  Bristol Blinds Co.        (login: bristol  / pw: rasmuslerdorf)
--   #2  Yorkshire Window Solutions (login: yorkshire / pw: rasmuslerdorf)
--
-- Both clients sell Slimline / Nova / Vogue verticals across bands AAA/A/C.
-- Pricing is per-client; Yorkshire is ~12% more expensive than Bristol.
-- Fabrics are real Market Place names lifted from the legacy live dump.
-- All sizes are in metres. Money is GBP. VAT 20%.
--
-- Test password hash is the PHP-docs example for "rasmuslerdorf".
-- Regenerate via password_hash() before any non-test use.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE quote_items;
TRUNCATE TABLE quotes;
TRUNCATE TABLE customers;
TRUNCATE TABLE client_markups;
TRUNCATE TABLE client_discounts;
TRUNCATE TABLE price_table_rows;
TRUNCATE TABLE price_tables;
TRUNCATE TABLE vertical_fabrics;
TRUNCATE TABLE products;
TRUNCATE TABLE product_groups;
TRUNCATE TABLE client_settings;
TRUNCATE TABLE client_users;
TRUNCATE TABLE clients;

-- ---------------------------------------------------------------------------
-- Clients
-- ---------------------------------------------------------------------------
INSERT INTO clients (id, company_name, account_code, contact_name, email, phone,
                     address1, town, county, postcode,
                     order_destination, quote_destination,
                     office_order_email, office_quote_email, active) VALUES
(1, 'Bristol Blinds Co.',          'BRI001', 'Helen Carter',  'helen@bristolblinds.co.uk', '0117 555 0182',
 '24 Park Row',          'Bristol',   'Avon',           'BS1 5LJ',
 'beverley_blinds', 'customer_office', 'orders@bristolblinds.co.uk', 'quotes@bristolblinds.co.uk', 1),
(2, 'Yorkshire Window Solutions',  'YRK002', 'Andrew Pickering', 'andrew@yorkshirewindows.co.uk', '0113 555 0247',
 '7 Briggate Mews',      'Leeds',     'West Yorkshire', 'LS1 6HD',
 'both',            'both',            'office@yorkshirewindows.co.uk', 'quotes@yorkshirewindows.co.uk', 1);

-- ---------------------------------------------------------------------------
-- Client users (admin login per client)
-- ---------------------------------------------------------------------------
INSERT INTO client_users (id, client_id, username, full_name, first_name, last_name,
                          email, password_hash, role,
                          can_create_quotes, can_create_orders,
                          can_view_all_customer_jobs, can_view_costs, active) VALUES
(1, 1, 'bristol',   'Helen Carter',   'Helen',  'Carter',
 'helen@bristolblinds.co.uk',
 '$2y$10$MYUrovlB4ScyT1J6UbbxMuBo.kWp9r914ygbw9oLvzZUWAwtCRF9S', 'admin', 1, 1, 1, 1, 1),
(2, 2, 'yorkshire', 'Andrew Pickering','Andrew','Pickering',
 'andrew@yorkshirewindows.co.uk',
 '$2y$10$MYUrovlB4ScyT1J6UbbxMuBo.kWp9r914ygbw9oLvzZUWAwtCRF9S', 'admin', 1, 1, 1, 1, 1);

-- ---------------------------------------------------------------------------
-- Client settings
-- ---------------------------------------------------------------------------
INSERT INTO client_settings (id, client_id, quote_prefix, default_markup_percent,
                             vat_percent, email_from_name, reply_to_email, quote_footer) VALUES
(1, 1, 'BRI', 50.00, 20.00, 'Bristol Blinds Co.',           'quotes@bristolblinds.co.uk',
 'Bristol Blinds Co. — 24 Park Row, Bristol BS1 5LJ — VAT registered.'),
(2, 2, 'YRK', 60.00, 20.00, 'Yorkshire Window Solutions',   'quotes@yorkshirewindows.co.uk',
 'Yorkshire Window Solutions — 7 Briggate Mews, Leeds LS1 6HD — Made in Yorkshire.');

-- ---------------------------------------------------------------------------
-- Product groups (one Vertical group per client)
-- ---------------------------------------------------------------------------
INSERT INTO product_groups (id, client_id, name, sort_order, active) VALUES
(1, 1, 'Vertical', 1, 1),
(2, 2, 'Vertical', 1, 1);

-- ---------------------------------------------------------------------------
-- Products (Slimline, Nova, Vogue per client)
-- ---------------------------------------------------------------------------
INSERT INTO products (id, client_id, product_group_id, name, code, active) VALUES
(1, 1, 1, 'Slimline', 'BRI-SLM', 1),
(2, 1, 1, 'Nova',     'BRI-NOV', 1),
(3, 1, 1, 'Vogue',    'BRI-VOG', 1),
(4, 2, 2, 'Slimline', 'YRK-SLM', 1),
(5, 2, 2, 'Nova',     'YRK-NOV', 1),
(6, 2, 2, 'Vogue',    'YRK-VOG', 1);

-- ---------------------------------------------------------------------------
-- Price tables — 9 per client (3 products × bands AAA / A / C)
-- IDs:
--   Bristol:    1=Slim AAA, 2=Slim A, 3=Slim C,
--               4=Nova AAA, 5=Nova A, 6=Nova C,
--               7=Vogue AAA, 8=Vogue A, 9=Vogue C
--   Yorkshire: 10=Slim AAA, 11=Slim A, 12=Slim C,
--              13=Nova AAA, 14=Nova A, 15=Nova C,
--              16=Vogue AAA, 17=Vogue A, 18=Vogue C
-- ---------------------------------------------------------------------------
INSERT INTO price_tables (id, client_id, product_id, table_name, band_code, notes, active) VALUES
( 1, 1, 1, 'Slimline - Band AAA', 'AAA', 'Bristol Slimline AAA pricing',  1),
( 2, 1, 1, 'Slimline - Band A',   'A',   'Bristol Slimline A pricing',    1),
( 3, 1, 1, 'Slimline - Band C',   'C',   'Bristol Slimline C pricing',    1),
( 4, 1, 2, 'Nova - Band AAA',     'AAA', 'Bristol Nova AAA pricing',      1),
( 5, 1, 2, 'Nova - Band A',       'A',   'Bristol Nova A pricing',        1),
( 6, 1, 2, 'Nova - Band C',       'C',   'Bristol Nova C pricing',        1),
( 7, 1, 3, 'Vogue - Band AAA',    'AAA', 'Bristol Vogue AAA pricing',     1),
( 8, 1, 3, 'Vogue - Band A',      'A',   'Bristol Vogue A pricing',       1),
( 9, 1, 3, 'Vogue - Band C',      'C',   'Bristol Vogue C pricing',       1),
(10, 2, 4, 'Slimline - Band AAA', 'AAA', 'Yorkshire Slimline AAA pricing',1),
(11, 2, 4, 'Slimline - Band A',   'A',   'Yorkshire Slimline A pricing',  1),
(12, 2, 4, 'Slimline - Band C',   'C',   'Yorkshire Slimline C pricing',  1),
(13, 2, 5, 'Nova - Band AAA',     'AAA', 'Yorkshire Nova AAA pricing',    1),
(14, 2, 5, 'Nova - Band A',       'A',   'Yorkshire Nova A pricing',      1),
(15, 2, 5, 'Nova - Band C',       'C',   'Yorkshire Nova C pricing',      1),
(16, 2, 6, 'Vogue - Band AAA',    'AAA', 'Yorkshire Vogue AAA pricing',   1),
(17, 2, 6, 'Vogue - Band A',      'A',   'Yorkshire Vogue A pricing',     1),
(18, 2, 6, 'Vogue - Band C',      'C',   'Yorkshire Vogue C pricing',     1);

-- ---------------------------------------------------------------------------
-- Price table rows — 16 cells per table (4 widths × 4 drops, in metres)
-- Rows are laid out: width-major order. Within a table, IDs run 1..16.
-- Bristol uses ids 1-144 (tables 1-9). Yorkshire uses ids 145-288 (tables 10-18).
-- ---------------------------------------------------------------------------

-- Bristol Slimline AAA (table 1)
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
( 1, 1, 1.000, 1.000,  16.00),( 2, 1, 1.000, 2.000,  22.00),( 3, 1, 1.000, 3.000,  30.00),( 4, 1, 1.000, 4.000,  40.00),
( 5, 1, 2.000, 1.000,  25.00),( 6, 1, 2.000, 2.000,  38.00),( 7, 1, 2.000, 3.000,  55.00),( 8, 1, 2.000, 4.000,  75.00),
( 9, 1, 3.000, 1.000,  34.00),(10, 1, 3.000, 2.000,  55.00),(11, 1, 3.000, 3.000,  80.00),(12, 1, 3.000, 4.000, 110.00),
(13, 1, 4.000, 1.000,  45.00),(14, 1, 4.000, 2.000,  75.00),(15, 1, 4.000, 3.000, 110.00),(16, 1, 4.000, 4.000, 150.00);

-- Bristol Slimline A (table 2) — Slimline AAA × 1.30
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(17, 2, 1.000, 1.000,  20.80),(18, 2, 1.000, 2.000,  28.60),(19, 2, 1.000, 3.000,  39.00),(20, 2, 1.000, 4.000,  52.00),
(21, 2, 2.000, 1.000,  32.50),(22, 2, 2.000, 2.000,  49.40),(23, 2, 2.000, 3.000,  71.50),(24, 2, 2.000, 4.000,  97.50),
(25, 2, 3.000, 1.000,  44.20),(26, 2, 3.000, 2.000,  71.50),(27, 2, 3.000, 3.000, 104.00),(28, 2, 3.000, 4.000, 143.00),
(29, 2, 4.000, 1.000,  58.50),(30, 2, 4.000, 2.000,  97.50),(31, 2, 4.000, 3.000, 143.00),(32, 2, 4.000, 4.000, 195.00);

-- Bristol Slimline C (table 3) — Slimline AAA × 1.60
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(33, 3, 1.000, 1.000,  25.60),(34, 3, 1.000, 2.000,  35.20),(35, 3, 1.000, 3.000,  48.00),(36, 3, 1.000, 4.000,  64.00),
(37, 3, 2.000, 1.000,  40.00),(38, 3, 2.000, 2.000,  60.80),(39, 3, 2.000, 3.000,  88.00),(40, 3, 2.000, 4.000, 120.00),
(41, 3, 3.000, 1.000,  54.40),(42, 3, 3.000, 2.000,  88.00),(43, 3, 3.000, 3.000, 128.00),(44, 3, 3.000, 4.000, 176.00),
(45, 3, 4.000, 1.000,  72.00),(46, 3, 4.000, 2.000, 120.00),(47, 3, 4.000, 3.000, 176.00),(48, 3, 4.000, 4.000, 240.00);

-- Bristol Nova AAA (table 4) — Slimline AAA × 1.25
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(49, 4, 1.000, 1.000,  20.00),(50, 4, 1.000, 2.000,  27.50),(51, 4, 1.000, 3.000,  37.50),(52, 4, 1.000, 4.000,  50.00),
(53, 4, 2.000, 1.000,  31.25),(54, 4, 2.000, 2.000,  47.50),(55, 4, 2.000, 3.000,  68.75),(56, 4, 2.000, 4.000,  93.75),
(57, 4, 3.000, 1.000,  42.50),(58, 4, 3.000, 2.000,  68.75),(59, 4, 3.000, 3.000, 100.00),(60, 4, 3.000, 4.000, 137.50),
(61, 4, 4.000, 1.000,  56.25),(62, 4, 4.000, 2.000,  93.75),(63, 4, 4.000, 3.000, 137.50),(64, 4, 4.000, 4.000, 187.50);

-- Bristol Nova A (table 5) — Slimline AAA × 1.625
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(65, 5, 1.000, 1.000,  26.00),(66, 5, 1.000, 2.000,  35.75),(67, 5, 1.000, 3.000,  48.75),(68, 5, 1.000, 4.000,  65.00),
(69, 5, 2.000, 1.000,  40.63),(70, 5, 2.000, 2.000,  61.75),(71, 5, 2.000, 3.000,  89.38),(72, 5, 2.000, 4.000, 121.88),
(73, 5, 3.000, 1.000,  55.25),(74, 5, 3.000, 2.000,  89.38),(75, 5, 3.000, 3.000, 130.00),(76, 5, 3.000, 4.000, 178.75),
(77, 5, 4.000, 1.000,  73.13),(78, 5, 4.000, 2.000, 121.88),(79, 5, 4.000, 3.000, 178.75),(80, 5, 4.000, 4.000, 243.75);

-- Bristol Nova C (table 6) — Slimline AAA × 2.00
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(81, 6, 1.000, 1.000,  32.00),(82, 6, 1.000, 2.000,  44.00),(83, 6, 1.000, 3.000,  60.00),(84, 6, 1.000, 4.000,  80.00),
(85, 6, 2.000, 1.000,  50.00),(86, 6, 2.000, 2.000,  76.00),(87, 6, 2.000, 3.000, 110.00),(88, 6, 2.000, 4.000, 150.00),
(89, 6, 3.000, 1.000,  68.00),(90, 6, 3.000, 2.000, 110.00),(91, 6, 3.000, 3.000, 160.00),(92, 6, 3.000, 4.000, 220.00),
(93, 6, 4.000, 1.000,  90.00),(94, 6, 4.000, 2.000, 150.00),(95, 6, 4.000, 3.000, 220.00),(96, 6, 4.000, 4.000, 300.00);

-- Bristol Vogue AAA (table 7) — Slimline AAA × 1.60
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
( 97, 7, 1.000, 1.000,  25.60),( 98, 7, 1.000, 2.000,  35.20),( 99, 7, 1.000, 3.000,  48.00),(100, 7, 1.000, 4.000,  64.00),
(101, 7, 2.000, 1.000,  40.00),(102, 7, 2.000, 2.000,  60.80),(103, 7, 2.000, 3.000,  88.00),(104, 7, 2.000, 4.000, 120.00),
(105, 7, 3.000, 1.000,  54.40),(106, 7, 3.000, 2.000,  88.00),(107, 7, 3.000, 3.000, 128.00),(108, 7, 3.000, 4.000, 176.00),
(109, 7, 4.000, 1.000,  72.00),(110, 7, 4.000, 2.000, 120.00),(111, 7, 4.000, 3.000, 176.00),(112, 7, 4.000, 4.000, 240.00);

-- Bristol Vogue A (table 8) — Slimline AAA × 2.08
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(113, 8, 1.000, 1.000,  33.28),(114, 8, 1.000, 2.000,  45.76),(115, 8, 1.000, 3.000,  62.40),(116, 8, 1.000, 4.000,  83.20),
(117, 8, 2.000, 1.000,  52.00),(118, 8, 2.000, 2.000,  79.04),(119, 8, 2.000, 3.000, 114.40),(120, 8, 2.000, 4.000, 156.00),
(121, 8, 3.000, 1.000,  70.72),(122, 8, 3.000, 2.000, 114.00),(123, 8, 3.000, 3.000, 166.40),(124, 8, 3.000, 4.000, 228.80),
(125, 8, 4.000, 1.000,  93.60),(126, 8, 4.000, 2.000, 156.00),(127, 8, 4.000, 3.000, 228.80),(128, 8, 4.000, 4.000, 312.00);

-- Bristol Vogue C (table 9) — Slimline AAA × 2.56
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(129, 9, 1.000, 1.000,  40.96),(130, 9, 1.000, 2.000,  56.32),(131, 9, 1.000, 3.000,  76.80),(132, 9, 1.000, 4.000, 102.40),
(133, 9, 2.000, 1.000,  64.00),(134, 9, 2.000, 2.000,  97.28),(135, 9, 2.000, 3.000, 140.80),(136, 9, 2.000, 4.000, 192.00),
(137, 9, 3.000, 1.000,  87.04),(138, 9, 3.000, 2.000, 140.80),(139, 9, 3.000, 3.000, 204.80),(140, 9, 3.000, 4.000, 281.60),
(141, 9, 4.000, 1.000, 115.20),(142, 9, 4.000, 2.000, 192.00),(143, 9, 4.000, 3.000, 281.60),(144, 9, 4.000, 4.000, 384.00);

-- Yorkshire Slimline AAA (table 10) — base
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(145, 10, 1.000, 1.000,  18.00),(146, 10, 1.000, 2.000,  25.00),(147, 10, 1.000, 3.000,  34.00),(148, 10, 1.000, 4.000,  45.00),
(149, 10, 2.000, 1.000,  28.00),(150, 10, 2.000, 2.000,  43.00),(151, 10, 2.000, 3.000,  62.00),(152, 10, 2.000, 4.000,  84.00),
(153, 10, 3.000, 1.000,  38.00),(154, 10, 3.000, 2.000,  62.00),(155, 10, 3.000, 3.000,  90.00),(156, 10, 3.000, 4.000, 124.00),
(157, 10, 4.000, 1.000,  51.00),(158, 10, 4.000, 2.000,  84.00),(159, 10, 4.000, 3.000, 124.00),(160, 10, 4.000, 4.000, 168.00);

-- Yorkshire Slimline A (table 11) — × 1.30
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(161, 11, 1.000, 1.000,  23.40),(162, 11, 1.000, 2.000,  32.50),(163, 11, 1.000, 3.000,  44.20),(164, 11, 1.000, 4.000,  58.50),
(165, 11, 2.000, 1.000,  36.40),(166, 11, 2.000, 2.000,  55.90),(167, 11, 2.000, 3.000,  80.60),(168, 11, 2.000, 4.000, 109.20),
(169, 11, 3.000, 1.000,  49.40),(170, 11, 3.000, 2.000,  80.60),(171, 11, 3.000, 3.000, 117.00),(172, 11, 3.000, 4.000, 161.20),
(173, 11, 4.000, 1.000,  66.30),(174, 11, 4.000, 2.000, 109.20),(175, 11, 4.000, 3.000, 161.20),(176, 11, 4.000, 4.000, 218.40);

-- Yorkshire Slimline C (table 12) — × 1.60
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(177, 12, 1.000, 1.000,  28.80),(178, 12, 1.000, 2.000,  40.00),(179, 12, 1.000, 3.000,  54.40),(180, 12, 1.000, 4.000,  72.00),
(181, 12, 2.000, 1.000,  44.80),(182, 12, 2.000, 2.000,  68.80),(183, 12, 2.000, 3.000,  99.20),(184, 12, 2.000, 4.000, 134.40),
(185, 12, 3.000, 1.000,  60.80),(186, 12, 3.000, 2.000,  99.20),(187, 12, 3.000, 3.000, 144.00),(188, 12, 3.000, 4.000, 198.40),
(189, 12, 4.000, 1.000,  81.60),(190, 12, 4.000, 2.000, 134.40),(191, 12, 4.000, 3.000, 198.40),(192, 12, 4.000, 4.000, 268.80);

-- Yorkshire Nova AAA (table 13) — × 1.25
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(193, 13, 1.000, 1.000,  22.50),(194, 13, 1.000, 2.000,  31.25),(195, 13, 1.000, 3.000,  42.50),(196, 13, 1.000, 4.000,  56.25),
(197, 13, 2.000, 1.000,  35.00),(198, 13, 2.000, 2.000,  53.75),(199, 13, 2.000, 3.000,  77.50),(200, 13, 2.000, 4.000, 105.00),
(201, 13, 3.000, 1.000,  47.50),(202, 13, 3.000, 2.000,  77.50),(203, 13, 3.000, 3.000, 112.50),(204, 13, 3.000, 4.000, 155.00),
(205, 13, 4.000, 1.000,  63.75),(206, 13, 4.000, 2.000, 105.00),(207, 13, 4.000, 3.000, 155.00),(208, 13, 4.000, 4.000, 210.00);

-- Yorkshire Nova A (table 14) — × 1.625
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(209, 14, 1.000, 1.000,  29.25),(210, 14, 1.000, 2.000,  40.63),(211, 14, 1.000, 3.000,  55.25),(212, 14, 1.000, 4.000,  73.13),
(213, 14, 2.000, 1.000,  45.50),(214, 14, 2.000, 2.000,  69.88),(215, 14, 2.000, 3.000, 100.75),(216, 14, 2.000, 4.000, 136.50),
(217, 14, 3.000, 1.000,  61.75),(218, 14, 3.000, 2.000, 101.00),(219, 14, 3.000, 3.000, 146.25),(220, 14, 3.000, 4.000, 201.50),
(221, 14, 4.000, 1.000,  82.88),(222, 14, 4.000, 2.000, 136.50),(223, 14, 4.000, 3.000, 201.50),(224, 14, 4.000, 4.000, 273.00);

-- Yorkshire Nova C (table 15) — × 2.00
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(225, 15, 1.000, 1.000,  36.00),(226, 15, 1.000, 2.000,  50.00),(227, 15, 1.000, 3.000,  68.00),(228, 15, 1.000, 4.000,  90.00),
(229, 15, 2.000, 1.000,  56.00),(230, 15, 2.000, 2.000,  86.00),(231, 15, 2.000, 3.000, 124.00),(232, 15, 2.000, 4.000, 168.00),
(233, 15, 3.000, 1.000,  76.00),(234, 15, 3.000, 2.000, 124.00),(235, 15, 3.000, 3.000, 180.00),(236, 15, 3.000, 4.000, 248.00),
(237, 15, 4.000, 1.000, 102.00),(238, 15, 4.000, 2.000, 168.00),(239, 15, 4.000, 3.000, 248.00),(240, 15, 4.000, 4.000, 336.00);

-- Yorkshire Vogue AAA (table 16) — × 1.65
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(241, 16, 1.000, 1.000,  29.70),(242, 16, 1.000, 2.000,  41.25),(243, 16, 1.000, 3.000,  56.10),(244, 16, 1.000, 4.000,  74.25),
(245, 16, 2.000, 1.000,  46.20),(246, 16, 2.000, 2.000,  70.95),(247, 16, 2.000, 3.000, 102.30),(248, 16, 2.000, 4.000, 138.60),
(249, 16, 3.000, 1.000,  62.70),(250, 16, 3.000, 2.000, 102.30),(251, 16, 3.000, 3.000, 148.50),(252, 16, 3.000, 4.000, 204.60),
(253, 16, 4.000, 1.000,  84.15),(254, 16, 4.000, 2.000, 138.60),(255, 16, 4.000, 3.000, 204.60),(256, 16, 4.000, 4.000, 277.20);

-- Yorkshire Vogue A (table 17) — × 2.15
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(257, 17, 1.000, 1.000,  38.70),(258, 17, 1.000, 2.000,  53.75),(259, 17, 1.000, 3.000,  73.10),(260, 17, 1.000, 4.000,  96.75),
(261, 17, 2.000, 1.000,  60.20),(262, 17, 2.000, 2.000,  92.45),(263, 17, 2.000, 3.000, 133.30),(264, 17, 2.000, 4.000, 180.60),
(265, 17, 3.000, 1.000,  81.70),(266, 17, 3.000, 2.000, 133.30),(267, 17, 3.000, 3.000, 193.50),(268, 17, 3.000, 4.000, 266.60),
(269, 17, 4.000, 1.000, 109.65),(270, 17, 4.000, 2.000, 180.60),(271, 17, 4.000, 3.000, 266.60),(272, 17, 4.000, 4.000, 361.20);

-- Yorkshire Vogue C (table 18) — × 2.65
INSERT INTO price_table_rows (id, price_table_id, width_value, drop_value_exact, base_price) VALUES
(273, 18, 1.000, 1.000,  47.70),(274, 18, 1.000, 2.000,  66.25),(275, 18, 1.000, 3.000,  90.10),(276, 18, 1.000, 4.000, 119.25),
(277, 18, 2.000, 1.000,  74.20),(278, 18, 2.000, 2.000, 113.95),(279, 18, 2.000, 3.000, 164.30),(280, 18, 2.000, 4.000, 222.60),
(281, 18, 3.000, 1.000, 100.70),(282, 18, 3.000, 2.000, 164.30),(283, 18, 3.000, 3.000, 238.50),(284, 18, 3.000, 4.000, 328.60),
(285, 18, 4.000, 1.000, 135.15),(286, 18, 4.000, 2.000, 222.60),(287, 18, 4.000, 3.000, 328.60),(288, 18, 4.000, 4.000, 445.20);

-- ---------------------------------------------------------------------------
-- Vertical fabrics — Market Place range (real names lifted from live dump)
-- IDs 1-23 Bristol, 24-42 Yorkshire.
-- ---------------------------------------------------------------------------
INSERT INTO vertical_fabrics (id, client_id, supplier_name, band_code, fabric_name, colour_name, active) VALUES
-- Bristol — Band AAA
( 1, 1, 'Market Place', 'AAA', 'Astratto',  'Mid Grey',  1),
( 2, 1, 'Market Place', 'AAA', 'Astratto',  'Almond',    1),
( 3, 1, 'Market Place', 'AAA', 'Astratto',  'Magnolia',  1),
( 4, 1, 'Market Place', 'AAA', 'Oakley',    'Cotton',    1),
( 5, 1, 'Market Place', 'AAA', 'Oakley',    'Aqua',      1),
( 6, 1, 'Market Place', 'AAA', 'Oakley',    'Natural',   1),
( 7, 1, 'Market Place', 'AAA', 'Ada',       'White',     1),
( 8, 1, 'Market Place', 'AAA', 'Ada',       'Blue',      1),
( 9, 1, 'Market Place', 'AAA', 'Marea',     'Cornsilk',  1),
-- Bristol — Band A
(10, 1, 'Market Place', 'A',   'Florence',  'Green',     1),
(11, 1, 'Market Place', 'A',   'Florence',  'Peach',     1),
(12, 1, 'Market Place', 'A',   'Florence',  'Stone',     1),
(13, 1, 'Market Place', 'A',   'Florence',  'White',     1),
(14, 1, 'Market Place', 'A',   'Evita',     'Linen',     1),
(15, 1, 'Market Place', 'A',   'Evita',     'White',     1),
(16, 1, 'Market Place', 'A',   'Corsica',   'Grey',      1),
(17, 1, 'Market Place', 'A',   'Corsica',   'Cream',     1),
(18, 1, 'Market Place', 'A',   'Corsica',   'Ivory',     1),
-- Bristol — Band C
(19, 1, 'Market Place', 'C',   'Stratford', 'Cream',     1),
(20, 1, 'Market Place', 'C',   'Stratford', 'Crimson',   1),
(21, 1, 'Market Place', 'C',   'Stratford', 'Taupe',     1),
(22, 1, 'Market Place', 'C',   'Marea',     'Black',     1),
(23, 1, 'Market Place', 'C',   'Marea',     'Latte',     1),
-- Yorkshire — Band AAA
(24, 2, 'Market Place', 'AAA', 'Astratto',  'Mid Grey',  1),
(25, 2, 'Market Place', 'AAA', 'Astratto',  'Slate',     1),
(26, 2, 'Market Place', 'AAA', 'Oakley',    'Cotton',    1),
(27, 2, 'Market Place', 'AAA', 'Oakley',    'Lilac',     1),
(28, 2, 'Market Place', 'AAA', 'Oakley',    'Pistacio',  1),
(29, 2, 'Market Place', 'AAA', 'Oakley',    'Steel',     1),
(30, 2, 'Market Place', 'AAA', 'Ada',       'White',     1),
(31, 2, 'Market Place', 'AAA', 'Ada',       'Cherry',    1),
(32, 2, 'Market Place', 'AAA', 'Ada',       'Grass',     1),
-- Yorkshire — Band A
(33, 2, 'Market Place', 'A',   'Florence',  'Green',     1),
(34, 2, 'Market Place', 'A',   'Florence',  'Stone',     1),
(35, 2, 'Market Place', 'A',   'Florence',  'White',     1),
(36, 2, 'Market Place', 'A',   'Evita',     'Linen',     1),
(37, 2, 'Market Place', 'A',   'Corsica',   'White',     1),
(38, 2, 'Market Place', 'A',   'Corsica',   'Ivory',     1),
-- Yorkshire — Band C
(39, 2, 'Market Place', 'C',   'Stratford', 'Cream',     1),
(40, 2, 'Market Place', 'C',   'Stratford', 'Crimson',   1),
(41, 2, 'Market Place', 'C',   'Marea',     'Black',     1),
(42, 2, 'Market Place', 'C',   'Marea',     'Barley',    1);

-- ---------------------------------------------------------------------------
-- Client markup overrides (default markups live in client_settings)
--   Bristol default = 50%; overrides: Slimline 60%, Vogue 45%
--   Yorkshire default = 60%; overrides: Slimline 70%, Vogue 55%
-- ---------------------------------------------------------------------------
INSERT INTO client_markups (id, client_id, product_id, markup_percent) VALUES
(1, 1, 1, 60.00),  -- Bristol Slimline
(2, 1, 3, 45.00),  -- Bristol Vogue
(3, 2, 4, 70.00),  -- Yorkshire Slimline
(4, 2, 6, 55.00);  -- Yorkshire Vogue

-- ---------------------------------------------------------------------------
-- Client discounts
--   Bristol: 5% off Vogue (premium push)
--   Yorkshire: 8% off Slimline, 5% off Nova (volume customer perks)
-- ---------------------------------------------------------------------------
INSERT INTO client_discounts (id, client_id, product_id, discount_percent) VALUES
(1, 1, 3, 5.00),  -- Bristol Vogue
(2, 2, 4, 8.00),  -- Yorkshire Slimline
(3, 2, 5, 5.00);  -- Yorkshire Nova

-- ---------------------------------------------------------------------------
-- End-customers (3 per client) — UK addresses
-- ---------------------------------------------------------------------------
INSERT INTO customers (id, client_id, name, email, phone, address1, address2, town, county, postcode) VALUES
-- Bristol
(1, 1, 'Mrs. Sarah Davies',     'sarah.davies@example.co.uk',  '07700 900142', '14 Westbury Park',     NULL,           'Bristol',  'Avon',           'BS9 3AB'),
(2, 1, 'Mr. James Carter',      'jcarter@example.co.uk',       '07700 900318', '8 Royal Crescent',     'Apt 2',        'Bath',     'Somerset',       'BA1 2LR'),
(3, 1, 'The Old Vicarage Ltd',  'admin@oldvicarage.co.uk',     '01749 555 902','The Old Vicarage',     'Cathedral Sq', 'Wells',    'Somerset',       'BA5 2UE'),
-- Yorkshire
(4, 2, 'Mrs. Margaret Hughes',  'm.hughes@example.co.uk',      '07700 900441', '47 Alwoodley Lane',    NULL,           'Leeds',    'West Yorkshire', 'LS17 7PR'),
(5, 2, 'Mr. Daniel Patel',      'd.patel@example.co.uk',       '07700 900587', '22 Cornwall Road',     NULL,           'Harrogate','North Yorkshire','HG1 2NF'),
(6, 2, 'Pennine Construction',  'office@penninebuild.co.uk',   '01904 555 718','Unit 5 Hazelwood Park','Stamford Bridge Rd','York', 'North Yorkshire','YO19 4QH');

-- ---------------------------------------------------------------------------
-- Quotes
--
-- Quote BRI-2026-0042 (Bristol -> Mrs. Sarah Davies)
--   Line 1 (Living Room): Vogue Band A, 3.0 x 2.0 m, Florence Stone
--           base 114.00 * 1.45 markup * 0.95 discount = 157.04
--   Line 2 (Bedroom):     Slimline Band AAA, 2.0 x 2.0 m, Astratto Almond
--           base 38.00 * 1.60 markup = 60.80
--   Subtotal 217.84, VAT 43.57, Total 261.41
--
-- Quote YRK-2026-0118 (Yorkshire -> Mr. Daniel Patel)
--   Line 1 (Master Bedroom): Vogue Band AAA, 2.0 x 3.0 m, Astratto Mid Grey
--           base 102.30 * 1.55 markup = 158.57
--   Line 2 (Lounge):         Nova Band A, 3.0 x 2.0 m, Florence Stone
--           base 101.00 * 1.60 markup * 0.95 discount = 153.52
--   Line 3 (Office):         Slimline Band AAA, 1.0 x 2.0 m, Oakley Cotton
--           base 25.00 * 1.70 markup * 0.92 discount = 39.10
--   Subtotal 351.19, VAT 70.24, Total 421.43
-- ---------------------------------------------------------------------------
INSERT INTO quotes (id, client_id, client_user_id, customer_id, quote_number,
                    end_customer_name, end_customer_email, end_customer_phone,
                    end_customer_address1, end_customer_address2,
                    end_customer_town, end_customer_county, end_customer_postcode,
                    status, subtotal, vat, total, notes,
                    public_token, valid_until, quote_date) VALUES
(1, 1, 1, 1, 'BRI-2026-0042',
 'Mrs. Sarah Davies', 'sarah.davies@example.co.uk', '07700 900142',
 '14 Westbury Park', NULL, 'Bristol', 'Avon', 'BS9 3AB',
 'sent',     217.84, 43.57, 261.41,
 'Living room verticals + master bedroom. Survey complete.',
 'a8b1c2d3e4f5061728394a5b6c7d8e9f0a1b2c3d4e5f60718293a4b5c6d7e8f9', '2026-06-04', '2026-05-05 10:15:00'),
(2, 2, 2, 5, 'YRK-2026-0118',
 'Mr. Daniel Patel', 'd.patel@example.co.uk', '07700 900587',
 '22 Cornwall Road', NULL, 'Harrogate', 'North Yorkshire', 'HG1 2NF',
 'accepted', 351.19, 70.24, 421.43,
 'Whole upper floor. Customer accepted via portal.',
 'b7c8d9e0f1a2b3c4d5e6f70819203a4b5c6d7e8f90a1b2c3d4e5f60718293a4b', '2026-05-30', '2026-05-04 16:42:00');

-- Update Quote 2 to set accepted_at after the fact (status already accepted)
UPDATE quotes SET accepted_at = '2026-05-05 09:18:00' WHERE id = 2;

-- ---------------------------------------------------------------------------
-- Quote items
-- ---------------------------------------------------------------------------
INSERT INTO quote_items (id, quote_id, product_id, line_no, room_name, description_text,
                         width, drop_value, unit, operation_type, fitting_type, quantity,
                         base_cost, discount_percent, markup_percent, sell_price, line_total,
                         price_table_id, price_table_row_id, vertical_fabric_id, notes) VALUES
-- Quote 1, line 1: Bristol Vogue A, 3.0x2.0m, Florence Stone
(1, 1, 3, 1, 'Living Room',
 'Type: Vogue\nFabric: Florence\nColour: Stone\nBand: A\nWidth: 3.0m\nDrop: 2.0m',
 3.000, 2.000, 'm', 'Wand', 'Face Fix', 1,
 114.00, 5.00, 45.00, 157.04, 157.04,
 8, 122, 12, 'Bay window — three sections, single track.'),

-- Quote 1, line 2: Bristol Slimline AAA, 2.0x2.0m, Astratto Almond
(2, 1, 1, 2, 'Bedroom',
 'Type: Slimline\nFabric: Astratto\nColour: Almond\nBand: AAA\nWidth: 2.0m\nDrop: 2.0m',
 2.000, 2.000, 'm', 'Cord', 'Recess Fix', 1,
 38.00, 0.00, 60.00, 60.80, 60.80,
 1, 6, 2, NULL),

-- Quote 2, line 1: Yorkshire Vogue AAA, 2.0x3.0m, Astratto Mid Grey
(3, 2, 6, 1, 'Master Bedroom',
 'Type: Vogue\nFabric: Astratto\nColour: Mid Grey\nBand: AAA\nWidth: 2.0m\nDrop: 3.0m',
 2.000, 3.000, 'm', 'Wand', 'Top Fix', 1,
 102.30, 0.00, 55.00, 158.57, 158.57,
 16, 247, 24, 'Standard headrail.'),

-- Quote 2, line 2: Yorkshire Nova A, 3.0x2.0m, Florence Stone
(4, 2, 5, 2, 'Lounge',
 'Type: Nova\nFabric: Florence\nColour: Stone\nBand: A\nWidth: 3.0m\nDrop: 2.0m',
 3.000, 2.000, 'm', 'Wand', 'Face Fix', 1,
 101.00, 5.00, 60.00, 153.52, 153.52,
 14, 218, 34, 'Stack to right of patio door.'),

-- Quote 2, line 3: Yorkshire Slimline AAA, 1.0x2.0m, Oakley Cotton
(5, 2, 4, 3, 'Office',
 'Type: Slimline\nFabric: Oakley\nColour: Cotton\nBand: AAA\nWidth: 1.0m\nDrop: 2.0m',
 1.000, 2.000, 'm', 'Cord', 'Recess Fix', 1,
 25.00, 8.00, 70.00, 39.10, 39.10,
 10, 146, 26, NULL);

SET FOREIGN_KEY_CHECKS = 1;
