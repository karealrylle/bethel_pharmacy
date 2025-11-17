-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 07:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bethel_pharmacy`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_batch` (IN `p_product_id` INT, IN `p_batch_number` VARCHAR(50), IN `p_quantity` INT, IN `p_manufactured_date` DATE, IN `p_expiry_date` DATE, IN `p_supplier_name` VARCHAR(255), IN `p_purchase_price` DECIMAL(10,2), IN `p_user_id` INT)   BEGIN
    DECLARE v_batch_id INT;
    
    -- Insert the new batch
    INSERT INTO product_batches (
        product_id,
        batch_number,
        quantity,
        original_quantity,
        manufactured_date,
        expiry_date,
        supplier_name,
        purchase_price,
        received_date,
        status
    )
    VALUES (
        p_product_id,
        p_batch_number,
        p_quantity,
        p_quantity,
        p_manufactured_date,
        p_expiry_date,
        p_supplier_name,
        p_purchase_price,
        CURDATE(),
        'available'
    );
    
    SET v_batch_id = LAST_INSERT_ID();
    
    -- Record the movement
    INSERT INTO batch_movements (
        batch_id,
        sale_item_id,
        movement_type,
        quantity,
        remaining_quantity,
        performed_by,
        notes
    )
    VALUES (
        v_batch_id,
        NULL,
        'restock',
        p_quantity,
        p_quantity,
        p_user_id,
        CONCAT('New batch added: ', p_batch_number)
    );
    
    -- Update legacy current_stock for backward compatibility
    UPDATE products
    SET current_stock = current_stock + p_quantity
    WHERE product_id = p_product_id;
    
    SELECT v_batch_id AS batch_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_sale_fifo` (IN `p_sale_id` INT, IN `p_product_id` INT, IN `p_quantity` INT, IN `p_user_id` INT)   BEGIN
    DECLARE v_remaining_qty INT DEFAULT p_quantity;
    DECLARE v_batch_id INT;
    DECLARE v_batch_qty INT;
    DECLARE v_qty_to_deduct INT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_sale_item_id INT;
    
    -- Cursor to get batches in FIFO order (earliest expiry first)
    DECLARE batch_cursor CURSOR FOR
        SELECT batch_id, quantity
        FROM product_batches
        WHERE product_id = p_product_id
            AND quantity > 0
            AND status IN ('available', 'low_stock')
            AND expiry_date > CURDATE()
        ORDER BY expiry_date ASC, received_date ASC, batch_id ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Get sale_item_id for tracking
    SELECT sale_item_id INTO v_sale_item_id
    FROM sale_items
    WHERE sale_id = p_sale_id AND product_id = p_product_id
    ORDER BY sale_item_id DESC
    LIMIT 1;
    
    OPEN batch_cursor;
    
    batch_loop: LOOP
        FETCH batch_cursor INTO v_batch_id, v_batch_qty;
        
        IF done OR v_remaining_qty <= 0 THEN
            LEAVE batch_loop;
        END IF;
        
        -- Determine how much to deduct from this batch
        SET v_qty_to_deduct = LEAST(v_batch_qty, v_remaining_qty);
        
        -- Update batch quantity
        UPDATE product_batches
        SET quantity = quantity - v_qty_to_deduct,
            status = CASE 
                WHEN quantity - v_qty_to_deduct = 0 THEN 'depleted'
                WHEN quantity - v_qty_to_deduct <= 10 THEN 'low_stock'
                ELSE 'available'
            END,
            updated_at = CURRENT_TIMESTAMP
        WHERE batch_id = v_batch_id;
        
        -- Record the movement
        INSERT INTO batch_movements (
            batch_id, 
            sale_item_id, 
            movement_type, 
            quantity, 
            remaining_quantity,
            performed_by,
            movement_date
        )
        VALUES (
            v_batch_id,
            v_sale_item_id,
            'sale',
            -v_qty_to_deduct,
            v_batch_qty - v_qty_to_deduct,
            p_user_id,
            CURRENT_TIMESTAMP
        );
        
        SET v_remaining_qty = v_remaining_qty - v_qty_to_deduct;
    END LOOP;
    
    CLOSE batch_cursor;
    
    -- Check if we couldn't fulfill the entire order
    IF v_remaining_qty > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock to complete sale';
    END IF;
    
    -- Also update legacy current_stock for backward compatibility
    UPDATE products
    SET current_stock = current_stock - p_quantity
    WHERE product_id = p_product_id;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_get_available_stock` (`p_product_id` INT) RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_total_stock INT;
    
    SELECT COALESCE(SUM(quantity), 0) INTO v_total_stock
    FROM product_batches
    WHERE product_id = p_product_id
        AND status IN ('available', 'low_stock')
        AND expiry_date > CURDATE();
    
    RETURN v_total_stock;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `batch_movements`
--

CREATE TABLE `batch_movements` (
  `movement_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `sale_item_id` int(11) DEFAULT NULL COMMENT 'NULL for non-sale movements',
  `movement_type` enum('sale','restock','adjustment','return','expiry','damage') NOT NULL,
  `quantity` int(11) NOT NULL COMMENT 'Negative for outgoing, positive for incoming',
  `remaining_quantity` int(11) NOT NULL COMMENT 'Batch quantity after this movement',
  `movement_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_movements`
--

INSERT INTO `batch_movements` (`movement_id`, `batch_id`, `sale_item_id`, `movement_type`, `quantity`, `remaining_quantity`, `movement_date`, `notes`, `performed_by`) VALUES
(1, 1, NULL, 'restock', 20, 20, '2025-11-15 10:15:17', 'New batch added: BTH-202511-214', NULL),
(2, 2, NULL, 'restock', 150, 150, '2025-11-15 11:52:23', 'New batch added: BTH-202511-161', NULL),
(3, 3, NULL, 'restock', 100, 100, '2025-11-15 11:52:50', 'New batch added: BTH-202511-415', NULL),
(4, 4, NULL, 'restock', 100, 100, '2025-11-15 11:54:05', 'New batch added: BTH-202511-483', NULL),
(5, 5, NULL, 'restock', 200, 200, '2025-11-15 15:26:24', 'New batch added: BTH-1763220364620', NULL),
(15, 15, NULL, 'restock', 100, 100, '2025-11-16 09:13:43', 'New batch added: BTH-6001', NULL),
(16, 16, NULL, 'restock', 100, 100, '2025-11-16 09:13:49', 'New batch added: BTH-7001', NULL),
(17, 17, NULL, 'restock', 100, 100, '2025-11-16 09:13:53', 'New batch added: BTH-8001', NULL),
(18, 18, NULL, 'restock', 100, 100, '2025-11-16 09:13:58', 'New batch added: BTH-9001', NULL),
(19, 19, NULL, 'restock', 100, 100, '2025-11-16 09:14:04', 'New batch added: BTH-10001', NULL),
(20, 20, NULL, 'restock', 100, 100, '2025-11-16 09:14:09', 'New batch added: BTH-11001', NULL),
(21, 21, NULL, 'restock', 100, 100, '2025-11-16 09:14:13', 'New batch added: BTH-12001', NULL),
(22, 22, NULL, 'restock', 100, 100, '2025-11-16 09:14:18', 'New batch added: BTH-13001', NULL),
(23, 23, NULL, 'restock', 100, 100, '2025-11-16 09:14:26', 'New batch added: BTH-14001', NULL),
(24, 24, NULL, 'restock', 100, 100, '2025-11-16 09:14:32', 'New batch added: BTH-15001', NULL),
(26, 26, NULL, 'restock', 100, 100, '2025-11-16 09:16:07', 'New batch added: BTH-46001', NULL),
(27, 27, NULL, 'restock', 100, 100, '2025-11-16 09:16:13', 'New batch added: BTH-16001', NULL),
(29, 29, NULL, 'restock', 100, 100, '2025-11-16 09:16:27', 'New batch added: BTH-18001', NULL),
(30, 30, NULL, 'restock', 100, 100, '2025-11-16 09:17:03', 'New batch added: BTH-40001', NULL),
(31, 31, NULL, 'restock', 100, 100, '2025-11-16 09:17:08', 'New batch added: BTH-39001', NULL),
(32, 32, NULL, 'restock', 100, 100, '2025-11-16 09:17:26', 'New batch added: BTH-19001', NULL),
(33, 33, NULL, 'restock', 100, 100, '2025-11-16 09:17:32', 'New batch added: BTH-20001', NULL),
(34, 34, NULL, 'restock', 150, 150, '2025-11-16 09:17:55', 'New batch added: BTH-23001', NULL),
(35, 35, NULL, 'restock', 150, 150, '2025-11-16 09:18:01', 'New batch added: BTH-21001', NULL),
(36, 36, NULL, 'restock', 150, 150, '2025-11-16 09:18:10', 'New batch added: BTH-25001', NULL),
(37, 37, NULL, 'restock', 150, 150, '2025-11-16 09:18:15', 'New batch added: BTH-22001', NULL),
(38, 38, NULL, 'restock', 150, 150, '2025-11-16 09:18:20', 'New batch added: BTH-24001', NULL),
(39, 39, NULL, 'restock', 150, 150, '2025-11-16 09:18:26', 'New batch added: BTH-27001', NULL),
(40, 40, NULL, 'restock', 150, 150, '2025-11-16 09:18:33', 'New batch added: BTH-38001', NULL),
(41, 41, NULL, 'restock', 150, 150, '2025-11-16 09:18:47', 'New batch added: BTH-37001', NULL),
(42, 42, NULL, 'restock', 150, 150, '2025-11-16 09:18:51', 'New batch added: BTH-36001', NULL),
(43, 43, NULL, 'restock', 150, 150, '2025-11-16 09:18:57', 'New batch added: BTH-31001', NULL),
(44, 44, NULL, 'restock', 150, 150, '2025-11-16 09:19:02', 'New batch added: BTH-28001', NULL),
(45, 45, NULL, 'restock', 150, 150, '2025-11-16 09:19:20', 'New batch added: BTH-29001', NULL),
(46, 46, NULL, 'restock', 150, 150, '2025-11-16 09:19:39', 'New batch added: BTH-30001', NULL),
(47, 47, NULL, 'restock', 120, 120, '2025-11-16 09:19:52', 'New batch added: BTH-33001', NULL),
(48, 48, NULL, 'restock', 50, 50, '2025-11-16 09:20:29', 'New batch added: BTH-32001', NULL),
(49, 49, NULL, 'restock', 50, 50, '2025-11-16 09:20:36', 'New batch added: BTH-34001', NULL),
(50, 50, NULL, 'restock', 50, 50, '2025-11-16 09:20:41', 'New batch added: BTH-35001', NULL),
(52, 52, NULL, 'restock', 100, 100, '2025-11-16 16:18:07', 'New batch added: BTH-4011', NULL),
(55, 15, NULL, 'expiry', -100, 0, '2025-11-16 17:05:05', 'Batch disposed: BTH-6001', NULL),
(56, 27, NULL, 'expiry', -100, 0, '2025-11-16 17:17:17', 'Batch disposed: BTH-16001', NULL),
(57, 27, NULL, 'expiry', 0, 0, '2025-11-16 17:17:20', 'Batch disposed: BTH-16001', NULL),
(58, 54, NULL, 'restock', 100, 100, '2025-11-16 17:29:45', 'New batch added: BTH-6011', NULL),
(60, 56, NULL, 'restock', 100, 100, '2025-11-17 05:33:28', 'New batch added: BTH-16011', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `category` enum('Prescription Medicines','Over-the-Counter (OTC) Products','Health & Personal Care','Medical Supplies & Equipment') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `current_stock` int(11) NOT NULL DEFAULT 0,
  `manufactured_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `how_to_use` text DEFAULT NULL,
  `side_effects` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reorder_level` int(11) DEFAULT 50,
  `reorder_quantity` int(11) DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `category`, `price`, `current_stock`, `manufactured_date`, `expiry_date`, `how_to_use`, `side_effects`, `created_at`, `updated_at`, `reorder_level`, `reorder_quantity`) VALUES
(1, 'Amoxicillin 500mg', 'Prescription Medicines', 19.00, 0, '2024-01-15', '2022-01-15', 'adults 21 yrs old above - Take 1 capsule every 8 hours with or without food\r\nchildren 12 to 20 yrs old- Take 1 capsule every 12 hours with or without food', 'Nausea, diarrhea, allergic reactions, skin rash', '2025-11-12 18:40:48', '2025-11-17 05:52:49', 30, 100),
(2, 'Metformin 500mg', 'Prescription Medicines', 8.75, 310, '2024-02-10', '2026-02-10', 'Take 1 tablet twice daily with meals', 'Stomach upset, diarrhea, metallic taste, vitamin B12 deficiency', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 30, 100),
(3, 'Losartan 50mg', 'Prescription Medicines', 12.00, 400, '2024-03-05', '2026-03-05', 'Take 1 tablet once daily, with or without food', 'Dizziness, fatigue, low blood pressure, elevated potassium', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 30, 100),
(4, 'Omeprazole 20mg', 'Prescription Medicines', 10.25, 513, '2024-01-20', '2026-01-20', 'Take 1 capsule 30 minutes before breakfast', 'Headache, stomach pain, nausea, diarrhea, constipation', '2025-11-12 18:40:48', '2025-11-16 16:54:31', 30, 100),
(6, 'Simvastatin 20mg', 'Prescription Medicines', 14.00, 240, '2024-03-10', '2026-03-10', 'Take 1 tablet in the evening with or without food', 'Muscle pain, headache, nausea, constipation, liver enzyme elevation', '2025-11-12 18:40:48', '2025-11-16 17:29:45', 30, 100),
(7, 'Cetirizine 10mg', 'Prescription Medicines', 6.50, 320, '2024-01-25', '2026-01-25', 'Take 1 tablet once daily, preferably in the evening', 'Drowsiness, dry mouth, fatigue, headache', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 30, 100),
(8, 'Levothyroxine 50mcg', 'Prescription Medicines', 11.75, 100, '2024-02-20', '2026-02-20', 'Take 1 tablet in the morning 30-60 minutes before breakfast', 'Hair loss, weight changes, increased appetite, nervousness', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 30, 100),
(9, 'Atorvastatin 10mg', 'Prescription Medicines', 16.50, 265, '2024-03-15', '2026-03-15', 'Take 1 tablet once daily at any time of day', 'Muscle pain, joint pain, diarrhea, cold-like symptoms', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 30, 100),
(10, 'Lisinopril 10mg', 'Prescription Medicines', 13.25, 255, '2024-01-30', '2026-01-30', 'Take 1 tablet once daily at the same time', 'Dry cough, dizziness, headache, fatigue, low blood pressure', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 30, 100),
(11, 'Paracetamol 500mg', 'Over-the-Counter (OTC) Products', 5.00, 400, '2024-04-01', '2026-04-01', 'Take 1-2 tablets every 4-6 hours as needed, maximum 8 tablets per day', 'Rare: liver damage with overdose, allergic reactions', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 50, 150),
(12, 'Ibuprofen 200mg', 'Over-the-Counter (OTC) Products', 7.25, 350, '2024-04-05', '2026-04-05', 'Take 1-2 tablets every 4-6 hours with food, maximum 6 tablets per day', 'Stomach upset, heartburn, nausea, dizziness', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 50, 150),
(13, 'Vitamin C 500mg', 'Over-the-Counter (OTC) Products', 3.50, 522, '2024-05-01', '2027-05-01', 'Take 1 tablet daily with or without food', 'Stomach cramps, nausea, diarrhea with high doses', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 50, 150),
(14, 'Antacid Tablets', 'Over-the-Counter (OTC) Products', 4.75, 380, '2024-04-10', '2026-04-10', 'Chew 1-2 tablets when experiencing heartburn, maximum 8 tablets per day', 'Constipation, diarrhea, chalky taste', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 50, 150),
(15, 'Cough Syrup 120ml', 'Over-the-Counter (OTC) Products', 12.00, 280, '2024-03-20', '2025-09-20', 'Take 10ml every 4-6 hours, do not exceed 60ml per day', 'Drowsiness, dizziness, nausea, constipation', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 50, 150),
(16, 'Loperamide 2mg', 'Over-the-Counter (OTC) Products', 6.00, 320, '2024-04-15', '2026-04-15', 'Take 2 capsules initially, then 1 after each loose stool, maximum 8 per day', 'Constipation, dizziness, drowsiness, nausea', '2025-11-12 18:40:48', '2025-11-17 05:33:28', 50, 150),
(18, 'Antihistamine Tablets', 'Over-the-Counter (OTC) Products', 5.50, 118, '2024-04-20', '2026-04-20', 'Take 1 tablet every 4-6 hours as needed, maximum 6 tablets per day', 'Drowsiness, dry mouth, blurred vision, dizziness', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 50, 150),
(19, 'Multivitamins', 'Over-the-Counter (OTC) Products', 9.00, 450, '2024-05-05', '2027-05-05', 'Take 1 tablet daily with food', 'Stomach upset, headache, unusual taste in mouth', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 50, 150),
(20, 'Throat Lozenges', 'Over-the-Counter (OTC) Products', 3.25, 520, '2024-04-25', '2026-04-25', 'Dissolve 1 lozenge in mouth every 2-3 hours as needed', 'Mouth irritation, allergic reactions (rare)', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 50, 150),
(21, 'Alcohol 70% 500ml', 'Health & Personal Care', 45.00, 350, '2024-06-01', '2026-06-01', 'Apply to hands and rub until dry, use as needed', 'Skin dryness, irritation with excessive use', '2025-11-12 18:40:48', '2025-11-16 12:29:44', 10, 50),
(22, 'Hand Sanitizer 60ml', 'Health & Personal Care', 25.00, 500, '2024-06-05', '2026-06-05', 'Apply small amount to hands and rub thoroughly', 'Dry skin, mild irritation', '2025-11-12 18:40:48', '2025-11-16 12:29:44', 10, 50),
(23, 'Betadine Solution 120ml', 'Health & Personal Care', 85.00, 300, '2024-05-10', '2026-05-10', 'Apply to affected area 1-3 times daily', 'Skin irritation, allergic reactions, staining', '2025-11-12 18:40:48', '2025-11-16 12:28:26', 10, 50),
(24, 'Hydrogen Peroxide 120ml', 'Health & Personal Care', 35.00, 151, '2024-06-10', '2026-06-10', 'Apply to minor cuts and wounds, let bubble then rinse', 'Mild stinging, skin whitening (temporary)', '2025-11-12 18:40:48', '2025-11-16 12:28:26', 10, 50),
(25, 'Cotton Balls 100pcs', 'Health & Personal Care', 28.00, 400, '2024-07-01', '2028-07-01', 'Use for applying medications or cleaning wounds', 'None', '2025-11-12 18:40:48', '2025-11-16 12:28:26', 10, 50),
(26, 'Adhesive Bandages 100pcs', 'Health & Personal Care', 65.00, 320, '2024-07-05', '2028-07-05', 'Apply to small cuts and wounds after cleaning', 'Skin irritation, allergic reactions (rare)', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 40, 120),
(27, 'Medical Tape 1inch', 'Health & Personal Care', 40.00, 350, '2024-06-15', '2027-06-15', 'Use to secure bandages and dressings', 'Skin irritation, adhesive residue', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 40, 120),
(28, 'Surgical Mask 50pcs', 'Health & Personal Care', 120.00, 550, '2024-08-01', '2029-08-01', 'Wear over nose and mouth, replace when soiled', 'Skin irritation, breathing discomfort with prolonged use', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 40, 120),
(29, 'Face Shield', 'Health & Personal Care', 35.00, 163, '2024-07-10', '2029-07-10', 'Wear over face, clean after each use', 'None', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 40, 120),
(30, 'Thermometer Digital', 'Health & Personal Care', 150.00, 270, '2024-06-20', '2029-06-20', 'Place under tongue or armpit, wait for beep', 'None', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 40, 120),
(31, 'Syringe 3ml 100pcs', 'Medical Supplies & Equipment', 250.00, 300, '2024-08-05', '2029-08-05', 'For medical use only, dispose after single use', 'None', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(32, 'Gauze Pads 4x4 100pcs', 'Medical Supplies & Equipment', 180.00, 250, '2024-08-10', '2028-08-10', 'Apply to wounds, change regularly', 'None', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(33, 'Elastic Bandage 3inch', 'Medical Supplies & Equipment', 55.00, 340, '2024-07-15', '2027-07-15', 'Wrap around injured area, secure with clips', 'Circulation problems if wrapped too tight', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(34, 'Blood Pressure Monitor', 'Medical Supplies & Equipment', 1800.00, 95, '2024-09-01', '2029-09-01', 'Wrap cuff around arm, press start button', 'None', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(35, 'Nebulizer Machine', 'Medical Supplies & Equipment', 2500.00, 80, '2024-09-05', '2029-09-05', 'Add medication to chamber, inhale mist for 10-15 minutes', 'None', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(36, 'Glucometer Kit', 'Medical Supplies & Equipment', 950.00, 210, '2024-08-15', '2029-08-15', 'Insert test strip, prick finger, apply blood to strip', 'Finger soreness from pricking', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(37, 'Wheelchair Standard', 'Medical Supplies & Equipment', 5500.00, 165, '2024-10-01', '2034-10-01', 'Adjust to patient comfort, lock wheels when stationary', 'None', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(38, 'Crutches Pair', 'Medical Supplies & Equipment', 850.00, 185, '2024-09-10', '2034-09-10', 'Adjust height to patient, use for support when walking', 'Underarm discomfort, hand fatigue', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(39, 'Oxygen Tank Portable', 'Medical Supplies & Equipment', 3200.00, 125, '2024-10-05', '2034-10-05', 'Connect nasal cannula, turn valve to prescribed flow rate', 'Nasal dryness, nosebleeds', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(40, 'IV Stand Stainless', 'Medical Supplies & Equipment', 1200.00, 140, '2024-09-15', '2034-09-15', 'Hang IV bags, adjust height as needed', 'None', '2025-11-12 18:40:48', '2025-11-16 10:40:49', 20, 50),
(46, 'Biogesic', 'Over-the-Counter (OTC) Products', 53.00, 150, '2025-11-14', '2025-11-05', 'asffafsa', 'fsdagdj', '2025-11-14 11:11:03', '2025-11-16 10:40:49', 50, 150);

-- --------------------------------------------------------

--
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `batch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'Links to products table',
  `batch_number` varchar(50) NOT NULL COMMENT 'Unique batch identifier (e.g., BTH2025001)',
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Current quantity in this batch',
  `original_quantity` int(11) NOT NULL COMMENT 'Initial quantity when received',
  `manufactured_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL COMMENT 'Cost per unit from supplier',
  `received_date` date NOT NULL DEFAULT curdate(),
  `status` enum('available','low_stock','expired','depleted') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_batches`
--

INSERT INTO `product_batches` (`batch_id`, `product_id`, `batch_number`, `quantity`, `original_quantity`, `manufactured_date`, `expiry_date`, `supplier_name`, `purchase_price`, `received_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 26, 'BTH-202511-214', 20, 20, '0000-00-00', '2027-11-15', 'n/a', 5000.00, '2025-11-15', 'available', NULL, '2025-11-15 10:15:17', '2025-11-15 10:15:17'),
(2, 1, 'BTH-202511-161', 150, 150, '0000-00-00', '2027-11-15', 'asjhfkadsjfs', 8400.00, '2025-11-15', 'available', NULL, '2025-11-15 11:52:23', '2025-11-15 11:52:23'),
(3, 1, 'BTH-202511-415', 100, 100, '0000-00-00', '2027-11-15', 'asjhfkadsjfs', 8000.00, '2025-11-15', 'available', NULL, '2025-11-15 11:52:50', '2025-11-15 11:52:50'),
(4, 2, 'BTH-202511-483', 100, 100, '0000-00-00', '2027-10-15', 'asjhfkadsjfs', 8000.00, '2025-11-15', 'available', NULL, '2025-11-15 11:54:05', '2025-11-15 11:54:05'),
(5, 3, 'BTH-1763220364620', 200, 200, '0000-00-00', '2027-07-15', NULL, NULL, '2025-11-15', 'available', NULL, '2025-11-15 15:26:24', '2025-11-15 15:26:24'),
(15, 6, 'BTH-6001', 0, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'depleted', NULL, '2025-11-16 09:13:43', '2025-11-16 17:05:05'),
(16, 7, 'BTH-7001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:13:49', '2025-11-16 09:13:49'),
(17, 8, 'BTH-8001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:13:53', '2025-11-16 09:13:53'),
(18, 9, 'BTH-9001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:13:58', '2025-11-16 09:13:58'),
(19, 10, 'BTH-10001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:14:04', '2025-11-16 09:14:04'),
(20, 11, 'BTH-11001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:14:09', '2025-11-16 09:14:09'),
(21, 12, 'BTH-12001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:14:13', '2025-11-16 09:14:13'),
(22, 13, 'BTH-13001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:14:18', '2025-11-16 09:14:18'),
(23, 14, 'BTH-14001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:14:26', '2025-11-16 09:14:26'),
(24, 15, 'BTH-15001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:14:32', '2025-11-16 09:14:32'),
(26, 46, 'BTH-46001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:16:07', '2025-11-16 09:16:07'),
(27, 16, 'BTH-16001', 0, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'depleted', NULL, '2025-11-16 09:16:13', '2025-11-16 17:17:17'),
(29, 18, 'BTH-18001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:16:27', '2025-11-16 09:16:27'),
(30, 40, 'BTH-40001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:17:03', '2025-11-16 09:17:03'),
(31, 39, 'BTH-39001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:17:08', '2025-11-16 09:17:08'),
(32, 19, 'BTH-19001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:17:26', '2025-11-16 09:17:26'),
(33, 20, 'BTH-20001', 100, 100, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:17:32', '2025-11-16 09:17:32'),
(34, 23, 'BTH-23001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:17:55', '2025-11-16 09:17:55'),
(35, 21, 'BTH-21001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:01', '2025-11-16 09:18:01'),
(36, 25, 'BTH-25001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:10', '2025-11-16 09:18:10'),
(37, 22, 'BTH-22001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:15', '2025-11-16 09:18:15'),
(38, 24, 'BTH-24001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:20', '2025-11-16 09:18:20'),
(39, 27, 'BTH-27001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:26', '2025-11-16 09:18:26'),
(40, 38, 'BTH-38001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:33', '2025-11-16 09:18:33'),
(41, 37, 'BTH-37001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:47', '2025-11-16 09:18:47'),
(42, 36, 'BTH-36001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:51', '2025-11-16 09:18:51'),
(43, 31, 'BTH-31001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:18:57', '2025-11-16 09:18:57'),
(44, 28, 'BTH-28001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:19:02', '2025-11-16 09:19:02'),
(45, 29, 'BTH-29001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:19:20', '2025-11-16 09:19:20'),
(46, 30, 'BTH-30001', 150, 150, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:19:38', '2025-11-16 09:19:38'),
(47, 33, 'BTH-33001', 120, 120, '0000-00-00', '2026-03-14', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:19:52', '2025-11-16 09:19:52'),
(48, 32, 'BTH-32001', 50, 50, '0000-00-00', '2027-01-11', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:20:29', '2025-11-16 09:20:29'),
(49, 34, 'BTH-34001', 50, 50, '0000-00-00', '2027-01-11', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:20:36', '2025-11-16 09:20:36'),
(50, 35, 'BTH-35001', 50, 50, '0000-00-00', '2027-01-11', NULL, NULL, '2025-11-16', 'available', NULL, '2025-11-16 09:20:41', '2025-11-16 09:20:41'),
(52, 4, 'BTH-4011', 100, 100, '0000-00-00', '2027-11-17', NULL, NULL, '2025-11-17', 'available', NULL, '2025-11-16 16:18:07', '2025-11-16 16:18:07'),
(54, 6, 'BTH-6011', 100, 100, '0000-00-00', '0000-00-00', NULL, NULL, '2025-11-17', 'available', NULL, '2025-11-16 17:29:45', '2025-11-16 17:29:45'),
(56, 16, 'BTH-16011', 100, 100, '0000-00-00', '0000-00-00', NULL, NULL, '2025-11-17', 'available', NULL, '2025-11-17 05:33:28', '2025-11-17 05:33:28');

--
-- Triggers `product_batches`
--
DELIMITER $$
CREATE TRIGGER `trg_batch_status_update` BEFORE UPDATE ON `product_batches` FOR EACH ROW BEGIN
    IF NEW.quantity = 0 THEN
        SET NEW.status = 'depleted';
    ELSEIF NEW.expiry_date < CURDATE() THEN
        SET NEW.status = 'expired';
    ELSEIF NEW.quantity <= 10 THEN
        SET NEW.status = 'low_stock';
    ELSE
        SET NEW.status = 'available';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Staff who processed the sale',
  `customer_name` varchar(255) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','gcash') DEFAULT 'cash',
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `role`, `password`, `created_at`, `last_login`, `status`) VALUES
(1, 'Karylle', 'karylleviray111@gmail.com', 'admin', '$2y$10$PSzzkdu2xBXG7CoH8vGGwum8G31SnbtbrqKgqVVnM2oFK9MI6Wz9.', '2025-11-10 19:03:35', '2025-11-17 05:59:52', 'active'),
(2, 'Kellychen', 'kellychensicat@gmail.com', 'staff', '$2y$10$kJ8A2.Gwu3JvvjCc/B/xV.f8C8if.CYoJJSQG6MzYfEVaR936xkMq', '2025-11-10 19:09:41', '2025-11-14 10:03:26', 'active'),
(3, 'Avril', 'avril@gmail.com', 'staff', '$2y$10$JSD3zp3YAyvQsrFn0j7vuuQ1JbDihPsTFCeSwrHDvc8EI0M3CYExS', '2025-11-17 05:41:21', '2025-11-17 05:41:41', 'active');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_expiring_batches`
-- (See below for the actual view)
--
CREATE TABLE `v_expiring_batches` (
`batch_id` int(11)
,`batch_number` varchar(50)
,`product_id` int(11)
,`product_name` varchar(255)
,`category` enum('Prescription Medicines','Over-the-Counter (OTC) Products','Health & Personal Care','Medical Supplies & Equipment')
,`quantity` int(11)
,`expiry_date` date
,`supplier_name` varchar(255)
,`days_until_expiry` int(7)
,`urgency_level` varchar(8)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_product_total_stock`
-- (See below for the actual view)
--
CREATE TABLE `v_product_total_stock` (
`product_id` int(11)
,`product_name` varchar(255)
,`category` enum('Prescription Medicines','Over-the-Counter (OTC) Products','Health & Personal Care','Medical Supplies & Equipment')
,`price` decimal(10,2)
,`legacy_stock` int(11)
,`batch_total_stock` decimal(32,0)
,`active_batches` bigint(21)
,`nearest_expiry` date
,`reorder_level` int(11)
,`reorder_quantity` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_reorder_list`
-- (See below for the actual view)
--
CREATE TABLE `v_reorder_list` (
`product_id` int(11)
,`product_name` varchar(255)
,`category` enum('Prescription Medicines','Over-the-Counter (OTC) Products','Health & Personal Care','Medical Supplies & Equipment')
,`current_stock` decimal(32,0)
,`reorder_level` int(11)
,`reorder_quantity` int(11)
,`quantity_needed` decimal(33,0)
);

-- --------------------------------------------------------

--
-- Structure for view `v_expiring_batches`
--
DROP TABLE IF EXISTS `v_expiring_batches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_expiring_batches`  AS SELECT `pb`.`batch_id` AS `batch_id`, `pb`.`batch_number` AS `batch_number`, `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `p`.`category` AS `category`, `pb`.`quantity` AS `quantity`, `pb`.`expiry_date` AS `expiry_date`, `pb`.`supplier_name` AS `supplier_name`, to_days(`pb`.`expiry_date`) - to_days(curdate()) AS `days_until_expiry`, CASE WHEN `pb`.`expiry_date` < curdate() THEN 'EXPIRED' WHEN to_days(`pb`.`expiry_date`) - to_days(curdate()) <= 7 THEN 'CRITICAL' WHEN to_days(`pb`.`expiry_date`) - to_days(curdate()) <= 30 THEN 'URGENT' WHEN to_days(`pb`.`expiry_date`) - to_days(curdate()) <= 90 THEN 'WARNING' ELSE 'OK' END AS `urgency_level` FROM (`product_batches` `pb` join `products` `p` on(`pb`.`product_id` = `p`.`product_id`)) WHERE `pb`.`quantity` > 0 AND `pb`.`expiry_date` <= curdate() + interval 90 day AND `pb`.`status` <> 'depleted' ORDER BY `pb`.`expiry_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_product_total_stock`
--
DROP TABLE IF EXISTS `v_product_total_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_product_total_stock`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `p`.`category` AS `category`, `p`.`price` AS `price`, `p`.`current_stock` AS `legacy_stock`, coalesce(sum(case when `pb`.`status` = 'available' and `pb`.`expiry_date` > curdate() then `pb`.`quantity` else 0 end),0) AS `batch_total_stock`, count(case when `pb`.`status` = 'available' then `pb`.`batch_id` end) AS `active_batches`, min(case when `pb`.`status` = 'available' then `pb`.`expiry_date` end) AS `nearest_expiry`, `p`.`reorder_level` AS `reorder_level`, `p`.`reorder_quantity` AS `reorder_quantity` FROM (`products` `p` left join `product_batches` `pb` on(`p`.`product_id` = `pb`.`product_id`)) GROUP BY `p`.`product_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_reorder_list`
--
DROP TABLE IF EXISTS `v_reorder_list`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_reorder_list`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `p`.`category` AS `category`, coalesce(sum(case when `pb`.`status` = 'available' and `pb`.`expiry_date` > curdate() then `pb`.`quantity` else 0 end),0) AS `current_stock`, `p`.`reorder_level` AS `reorder_level`, `p`.`reorder_quantity` AS `reorder_quantity`, `p`.`reorder_quantity`- coalesce(sum(case when `pb`.`status` = 'available' and `pb`.`expiry_date` > curdate() then `pb`.`quantity` else 0 end),0) AS `quantity_needed` FROM (`products` `p` left join `product_batches` `pb` on(`p`.`product_id` = `pb`.`product_id`)) GROUP BY `p`.`product_id` HAVING `current_stock` <= `p`.`reorder_level` ORDER BY coalesce(sum(case when `pb`.`status` = 'available' and `pb`.`expiry_date` > curdate() then `pb`.`quantity` else 0 end),0) ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `batch_movements`
--
ALTER TABLE `batch_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_sale_item` (`sale_item_id`),
  ADD KEY `idx_movement_date` (`movement_date`),
  ADD KEY `idx_movement_type` (`movement_type`),
  ADD KEY `fk_movement_user` (`performed_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `unique_batch` (`product_id`,`batch_number`),
  ADD KEY `idx_product_expiry` (`product_id`,`expiry_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry_date` (`expiry_date`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_sale_date` (`sale_date`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`sale_item_id`),
  ADD KEY `idx_sale` (`sale_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `batch_movements`
--
ALTER TABLE `batch_movements`
  MODIFY `movement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `sale_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `batch_movements`
--
ALTER TABLE `batch_movements`
  ADD CONSTRAINT `fk_movement_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_movement_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`sale_item_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_movement_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sale_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_sale_item_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
