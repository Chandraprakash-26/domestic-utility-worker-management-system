-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 29, 2025 at 07:13 AM
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
-- Database: `task_manager`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `check_worker_availability` (IN `p_worker_id` INT, IN `p_service_date` DATE, IN `p_service_time` TIME, OUT `p_is_available` BOOLEAN)   BEGIN
    DECLARE v_day_of_week VARCHAR(20);
    DECLARE v_default_available INT;
    DECLARE v_is_unavailable INT;
    DECLARE v_is_booked INT;

    -- Get day of week
    SET v_day_of_week = DAYNAME(p_service_date);

    -- Check if worker has default availability for this day/time
    SELECT COUNT(*) INTO v_default_available
    FROM worker_default_availability
    WHERE worker_id = p_worker_id
      AND day_of_week = v_day_of_week
      AND is_available = TRUE
      AND p_service_time BETWEEN start_time AND end_time;

    -- Check if worker marked this specific date/time as unavailable
    SELECT COUNT(*) INTO v_is_unavailable
    FROM worker_unavailability
    WHERE worker_id = p_worker_id
      AND unavailable_date = p_service_date
      AND p_service_time BETWEEN unavailable_start_time AND unavailable_end_time;

    -- Check if already booked
    SELECT COUNT(*) INTO v_is_booked
    FROM booking
    WHERE worker_id = p_worker_id
      AND service_date = p_service_date
      AND service_time = p_service_time
      AND status NOT IN ('cancelled');

    -- Worker is available if they have default availability AND not marked unavailable AND not booked
    IF v_default_available > 0 AND v_is_unavailable = 0 AND v_is_booked = 0 THEN
        SET p_is_available = TRUE;
    ELSE
        SET p_is_available = FALSE;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `complete_booking` (IN `p_booking_id` INT)   BEGIN
    DECLARE v_worker_id INT;

    UPDATE booking
    SET status = 'completed'
    WHERE booking_id = p_booking_id;

    SELECT worker_id INTO v_worker_id
    FROM booking
    WHERE booking_id = p_booking_id;

    IF v_worker_id IS NOT NULL THEN
        CALL update_worker_rating(v_worker_id);
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_worker_rating` (IN `p_worker_id` INT)   BEGIN
    DECLARE avg_rating DECIMAL(3,2);

    SELECT AVG(f.rating) INTO avg_rating
    FROM feedback f
    JOIN booking b ON f.booking_id = b.booking_id
    WHERE b.worker_id = p_worker_id;

    IF avg_rating IS NOT NULL THEN
        UPDATE worker
        SET rating = avg_rating
        WHERE worker_id = p_worker_id;
    END IF;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `get_worker_availability_status` (`p_worker_id` INT, `p_service_date` DATE, `p_service_time` TIME) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_day_of_week VARCHAR(20);
    DECLARE v_default_available INT;
    DECLARE v_is_unavailable INT;
    DECLARE v_is_booked INT;

    -- Get day of week
    SET v_day_of_week = DAYNAME(p_service_date);

    -- Check if worker has default availability for this day/time
    SELECT COUNT(*) INTO v_default_available
    FROM worker_default_availability
    WHERE worker_id = p_worker_id
      AND day_of_week = v_day_of_week
      AND is_available = TRUE
      AND p_service_time BETWEEN start_time AND end_time;

    -- Check if worker marked this specific date/time as unavailable
    SELECT COUNT(*) INTO v_is_unavailable
    FROM worker_unavailability
    WHERE worker_id = p_worker_id
      AND unavailable_date = p_service_date
      AND p_service_time BETWEEN unavailable_start_time AND unavailable_end_time;

    -- Check if already booked
    SELECT COUNT(*) INTO v_is_booked
    FROM booking
    WHERE worker_id = p_worker_id
      AND service_date = p_service_date
      AND service_time = p_service_time
      AND status NOT IN ('cancelled');

    -- Determine status
    IF v_is_booked > 0 THEN
        RETURN 'booked';
    ELSEIF v_is_unavailable > 0 THEN
        RETURN 'unavailable';
    ELSEIF v_default_available > 0 THEN
        RETURN 'available';
    ELSE
        RETURN 'unavailable';
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_workers_view`
-- (See below for the actual view)
--
CREATE TABLE `active_workers_view` (
`worker_id` int(11)
,`worker_name` varchar(100)
,`email` varchar(100)
,`phone_no` varchar(20)
,`skill_type` varchar(100)
,`experience_years` decimal(4,1)
,`availability_status` enum('available','busy','offline')
,`rating` decimal(3,2)
,`category_name` varchar(100)
,`category_description` text
,`full_address` varchar(369)
);

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `name`, `email`, `password`, `phone`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin@gmail.com', '123456', '1234567890', '2025-12-01 03:51:53', '2025-12-29 05:35:19'),
(2, 'Manager One', 'manager1@gmail.com', '123456', '1234567891', '2025-12-01 03:51:53', '2025-12-29 05:35:34'),
(3, 'Manager Two', 'manager2@gmail.com', '123456', '1234567892', '2025-12-01 03:51:53', '2025-12-29 05:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `availability`
--

CREATE TABLE `availability` (
  `slot_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `available_time` time NOT NULL,
  `status` enum('available','booked','unavailable') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `availability`
--

INSERT INTO `availability` (`slot_id`, `worker_id`, `available_date`, `available_time`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-12-01', '09:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(2, 1, '2025-12-01', '14:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(3, 1, '2025-12-02', '10:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(4, 2, '2025-12-01', '08:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(5, 2, '2025-12-01', '15:00:00', 'booked', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(6, 2, '2025-12-02', '09:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(7, 3, '2025-12-01', '10:00:00', 'unavailable', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(8, 4, '2025-12-01', '11:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(9, 4, '2025-12-02', '14:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(10, 5, '2025-12-01', '13:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(11, 5, '2025-12-03', '10:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(12, 6, '2025-12-01', '09:00:00', 'available', '2025-12-01 03:51:53', '2025-12-01 03:51:53');

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `booking_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `worker_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `slot_id` int(11) DEFAULT NULL,
  `service_description` text DEFAULT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `service_date` date DEFAULT NULL,
  `service_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`booking_id`, `customer_id`, `worker_id`, `category_id`, `slot_id`, `service_description`, `booking_date`, `service_date`, `service_time`, `status`, `total_amount`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, 1, 'Fix leaking pipe in bathroom', '2025-12-01 03:51:53', '2025-12-01', '09:00:00', 'confirmed', 150.00, '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(5, 1, 2, NULL, 6, 'Repair electrical outlet', '2025-12-01 03:51:53', '2025-12-02', '09:00:00', 'pending', 100.00, '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(7, 1, 3, 3, NULL, 'carpenter', '2025-12-01 04:19:31', '2025-12-01', '09:49:00', 'completed', 3000.00, '2025-12-01 04:19:31', '2025-12-01 04:25:30'),
(8, 1, 4, 4, NULL, '', '2025-12-01 04:37:28', '2025-12-01', '10:08:00', 'confirmed', 10000.00, '2025-12-01 04:37:28', '2025-12-01 04:38:06'),
(9, 1, 1, 1, NULL, '', '2025-12-01 04:39:00', '2025-12-02', '10:08:00', 'completed', 10000.00, '2025-12-01 04:39:00', '2025-12-01 04:41:24'),
(10, 1, NULL, 3, NULL, '', '2025-12-01 05:14:31', '2025-12-01', '10:44:00', 'pending', 0.00, '2025-12-01 05:14:31', '2025-12-01 05:14:31'),
(13, 1, 3, 3, NULL, 'door work', '2025-12-28 16:44:38', '2025-12-29', '10:00:00', 'confirmed', 1000.00, '2025-12-28 16:44:38', '2025-12-28 16:46:22'),
(14, 1, NULL, 4, NULL, 'clean the house', '2025-12-29 04:37:18', '2025-12-30', '10:30:00', 'completed', 2000.00, '2025-12-29 04:37:18', '2025-12-29 04:47:56'),
(15, 1, NULL, 5, NULL, 'painting house', '2025-12-29 05:16:18', '2025-12-31', '10:00:00', 'completed', 20000.00, '2025-12-29 05:16:18', '2025-12-29 05:17:31'),
(16, 1, NULL, 3, NULL, '', '2025-12-29 05:28:47', '2025-12-30', '12:30:00', 'pending', 0.00, '2025-12-29 05:28:47', '2025-12-29 05:28:47'),
(17, 13, 19, 2, NULL, 'tv repair', '2025-12-29 05:58:55', '2025-12-30', '10:00:00', 'completed', 2500.00, '2025-12-29 05:58:55', '2025-12-29 05:59:43');

--
-- Triggers `booking`
--
DELIMITER $$
CREATE TRIGGER `after_booking_cancel` AFTER UPDATE ON `booking` FOR EACH ROW BEGIN
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' AND NEW.slot_id IS NOT NULL THEN
        UPDATE availability
        SET status = 'available'
        WHERE slot_id = NEW.slot_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_booking_insert` AFTER INSERT ON `booking` FOR EACH ROW BEGIN
    IF NEW.slot_id IS NOT NULL THEN
        UPDATE availability
        SET status = 'booked'
        WHERE slot_id = NEW.slot_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `booking_details_view`
-- (See below for the actual view)
--
CREATE TABLE `booking_details_view` (
`booking_id` int(11)
,`customer_name` varchar(100)
,`customer_email` varchar(100)
,`customer_phone` varchar(20)
,`worker_name` varchar(100)
,`worker_phone` varchar(20)
,`category_name` varchar(100)
,`service_description` text
,`service_date` date
,`service_time` time
,`booking_status` enum('pending','confirmed','in_progress','completed','cancelled')
,`total_amount` decimal(10,2)
,`payment_method` enum('cash','credit_card','debit_card','upi','net_banking')
,`payment_status` enum('pending','completed','failed','refunded')
,`feedback_rating` decimal(3,2)
,`feedback_comments` text
);

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `street` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `name`, `email`, `phone`, `password`, `street`, `city`, `pincode`, `created_at`, `updated_at`) VALUES
(1, 'John Doe', 'john.doe@gmail.com', '9876543210', '123456', '123 Main Street', 'New York', '560871', '2025-12-01 03:51:53', '2025-12-29 05:44:15'),
(11, 'chintu', 'chintu@gmail.com', '1357913579', '123456', '456 nandacolony', 'nalgonda', '567789', '2025-12-29 05:51:46', '2025-12-29 05:51:46'),
(12, 'ruthvik', 'ruthvik@gmail.com', '3456734567', '123456', '146 gokulnagar', 'nalgonda', '508004', '2025-12-29 05:52:27', '2025-12-29 05:52:27'),
(13, 'chandra', 'chandra@gmail.com', '3456789012', '123456', '248 dvk road', 'hyderabad', '560987', '2025-12-29 05:53:18', '2025-12-29 05:53:18'),
(14, 'bhanu', 'bhanu@gmail.com', '7890789078', '123456', '134 xyz', 'nalgonda', '789078', '2025-12-29 05:53:58', '2025-12-29 05:53:58'),
(15, 'charan', 'charan@gmail.com', '3456776543', '123456', '123 np road', 'karimnagar', '654321', '2025-12-29 05:54:54', '2025-12-29 05:54:54');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `rating` decimal(3,2) NOT NULL,
  `comments` text DEFAULT NULL,
  `feedback_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `booking_id`, `rating`, `comments`, `feedback_date`, `created_at`, `updated_at`) VALUES
(1, 1, 4.50, 'Great service! Fixed the leak quickly and professionally.', '2025-12-01 03:51:54', '2025-12-01 03:51:54', '2025-12-01 03:51:54'),
(5, 7, 3.00, '', '2025-12-01 04:26:15', '2025-12-01 04:26:15', '2025-12-01 04:26:15'),
(6, 9, 5.00, '', '2025-12-01 04:41:57', '2025-12-01 04:41:57', '2025-12-01 04:41:57'),
(9, 14, 4.00, '', '2025-12-29 04:50:00', '2025-12-29 04:50:00', '2025-12-29 04:50:00'),
(10, 17, 5.00, 'good', '2025-12-29 06:00:11', '2025-12-29 06:00:11', '2025-12-29 06:00:11');

--
-- Triggers `feedback`
--
DELIMITER $$
CREATE TRIGGER `after_feedback_insert` AFTER INSERT ON `feedback` FOR EACH ROW BEGIN
    DECLARE v_worker_id INT;

    SELECT worker_id INTO v_worker_id
    FROM booking
    WHERE booking_id = NEW.booking_id;

    IF v_worker_id IS NOT NULL THEN
        CALL update_worker_rating(v_worker_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','upi','net_banking') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`payment_id`, `booking_id`, `payment_method`, `amount`, `status`, `payment_date`, `transaction_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'upi', 150.00, 'completed', '2025-12-01 03:51:54', 'TXN001234567890', '2025-12-01 03:51:54', '2025-12-01 03:51:54'),
(5, 5, 'cash', 100.00, 'completed', '2025-12-01 03:51:54', '', '2025-12-01 03:51:54', '2025-12-29 04:40:40'),
(7, 7, 'cash', 3000.00, 'completed', '2025-12-01 04:24:44', '', '2025-12-01 04:24:44', '2025-12-01 04:24:44'),
(8, 9, 'upi', 10000.00, 'completed', '2025-12-01 04:40:47', '', '2025-12-01 04:40:47', '2025-12-01 04:40:47'),
(11, 13, 'cash', 1000.00, 'completed', '2025-12-29 04:37:34', '', '2025-12-29 04:37:34', '2025-12-29 04:37:34'),
(12, 14, 'cash', 2000.00, 'completed', '2025-12-29 05:16:31', '', '2025-12-29 05:16:31', '2025-12-29 05:16:31'),
(13, 17, 'cash', 2500.00, 'completed', '2025-12-29 06:01:16', '', '2025-12-29 06:01:16', '2025-12-29 06:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `service_category`
--

CREATE TABLE `service_category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `managed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_category`
--

INSERT INTO `service_category` (`category_id`, `category_name`, `description`, `managed_by`, `created_at`, `updated_at`) VALUES
(1, 'Plumbing', 'All plumbing related services including pipe repair, installation, and maintenance', 1, '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(2, 'Electrical', 'Electrical services including wiring, repair, and installation', 1, '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(3, 'Carpentry', 'Woodwork, furniture repair, and custom carpentry services', 2, '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(4, 'Cleaning', 'Home and office cleaning services', 2, '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(5, 'Painting', 'Interior and exterior painting services', 1, '2025-12-01 03:51:53', '2025-12-01 03:51:53'),
(6, 'HVAC', 'Heating, ventilation, and air conditioning services', 3, '2025-12-01 03:51:53', '2025-12-01 03:51:53');

-- --------------------------------------------------------

--
-- Table structure for table `worker`
--

CREATE TABLE `worker` (
  `worker_id` int(11) NOT NULL,
  `worker_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_no` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `skill_type` varchar(100) DEFAULT NULL,
  `experience_years` decimal(4,1) DEFAULT 0.0,
  `availability_status` enum('available','busy','offline') DEFAULT 'available',
  `rating` decimal(3,2) DEFAULT 0.00,
  `street` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `worker`
--

INSERT INTO `worker` (`worker_id`, `worker_name`, `email`, `phone_no`, `password`, `skill_type`, `experience_years`, `availability_status`, `rating`, `street`, `city`, `pincode`, `category_id`, `assigned_by`, `created_at`, `updated_at`) VALUES
(1, 'Robert Plumber', 'robert.plumber@gmail.com', '9384762451', '123456', 'Licensed Plumber', 5.0, 'available', 4.83, '111 Worker Lane', 'New York', '508004', 1, 1, '2025-12-01 03:51:53', '2025-12-29 05:40:07'),
(2, 'Tom Electric', 'tom.electric@gmail.com', '9849958785', '123456', 'Certified Electrician', 7.5, 'available', 4.80, '222 Service Road', 'New York', '508008', 2, 1, '2025-12-01 03:51:53', '2025-12-29 05:40:51'),
(3, 'Chris Carpenter', 'chris.carpenter@gmail.com', '9395305527', '123456', 'Master Carpenter', 10.0, 'busy', 3.00, '333 Craft Street', 'Los Angeles', '504024', 3, 2, '2025-12-01 03:51:53', '2025-12-29 05:41:34'),
(4, 'Lisa Cleaner', 'lisa.cleaner@gmail.com', '8309327098', '123456', 'Professional Cleaner', 3.0, 'available', 3.00, '444 Clean Ave', 'Chicago', '507034', 4, 2, '2025-12-01 03:51:53', '2025-12-29 05:42:39'),
(5, 'Paul Painter', 'paul.painter@gmail.com', '9392962991', '123456', 'Professional Painter', 6.0, 'available', 4.40, '555 Color Street', 'Houston', '580561', 5, 1, '2025-12-01 03:51:53', '2025-12-29 05:43:14'),
(6, 'Mark HVAC', 'mark.hvac@gmail.com', '8679845231', '123456', 'HVAC Technician', 8.0, 'offline', 4.70, '666 Climate Road', 'Phoenix', '850876', 6, 3, '2025-12-01 03:51:53', '2025-12-29 05:43:48'),
(14, 'saikumar', 'saikumar@gmail.com', '9876598765', '123456', 'plumber', 3.0, 'available', 0.00, '123 housing board', 'nalgonda', '508001', 1, NULL, '2025-12-29 05:46:00', '2025-12-29 05:46:00'),
(15, 'ravikishore', 'ravikishore@gmail.com', '7896778967', '123456', 'cleaner', 7.0, 'available', 0.00, '345 rtc colony', 'nalgobda', '580987', 4, NULL, '2025-12-29 05:46:56', '2025-12-29 05:46:56'),
(16, 'nandan', 'nandan@gmail.com', '5678956789', '123456', 'carpenter', 5.0, 'available', 0.00, '234 dvk road', 'hyderabad', '580981', 3, NULL, '2025-12-29 05:47:58', '2025-12-29 05:47:58'),
(17, 'anil', 'anil@gmail.com', '4567845678', '123456', 'hvac', 2.0, 'available', 0.00, '123 vidhyanagar', 'miryalaguda', '670890', 6, NULL, '2025-12-29 05:48:56', '2025-12-29 05:48:56'),
(18, 'vignesh', 'vignesh@gmail.com', '3456734567', '123456', 'paint', 3.0, 'available', 0.00, 'nalgonda', 'nalgonda', '590345', 5, NULL, '2025-12-29 05:49:48', '2025-12-29 05:49:48'),
(19, 'anish', 'anish@gmail.com', '1234512345', '123456', 'electrical', 1.0, 'available', 5.00, '678 sreenagar', 'devarakonda', '678905', 2, NULL, '2025-12-29 05:50:47', '2025-12-29 06:00:11');

-- --------------------------------------------------------

--
-- Table structure for table `worker_default_availability`
--

CREATE TABLE `worker_default_availability` (
  `default_availability_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `worker_default_availability`
--

INSERT INTO `worker_default_availability` (`default_availability_id`, `worker_id`, `day_of_week`, `start_time`, `end_time`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 1, 'Monday', '09:00:00', '17:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(2, 1, 'Tuesday', '09:00:00', '17:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(3, 1, 'Wednesday', '09:00:00', '17:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(4, 1, 'Thursday', '09:00:00', '17:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(5, 1, 'Friday', '09:00:00', '17:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(6, 2, 'Monday', '08:00:00', '18:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(7, 2, 'Tuesday', '08:00:00', '18:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(8, 2, 'Wednesday', '08:00:00', '18:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(9, 2, 'Thursday', '08:00:00', '18:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(10, 2, 'Friday', '08:00:00', '18:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(11, 2, 'Saturday', '08:00:00', '14:00:00', 1, '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(12, 3, 'Monday', '09:00:00', '17:00:00', 1, '2025-12-01 04:21:57', '2025-12-01 04:21:57'),
(13, 3, 'Tuesday', '09:00:00', '17:00:00', 1, '2025-12-01 04:21:57', '2025-12-01 04:21:57'),
(14, 3, 'Wednesday', '09:00:00', '17:00:00', 1, '2025-12-01 04:21:57', '2025-12-01 04:21:57'),
(15, 3, 'Thursday', '09:00:00', '17:00:00', 1, '2025-12-01 04:21:57', '2025-12-01 04:21:57'),
(16, 3, 'Friday', '09:00:00', '17:00:00', 1, '2025-12-01 04:21:57', '2025-12-01 04:21:57'),
(17, 3, 'Saturday', '09:00:00', '17:00:00', 1, '2025-12-01 04:21:57', '2025-12-01 04:21:57'),
(18, 3, 'Sunday', '09:00:00', '17:00:00', 1, '2025-12-01 04:21:57', '2025-12-01 04:21:57'),
(19, 4, 'Monday', '09:00:00', '17:00:00', 1, '2025-12-01 04:27:30', '2025-12-01 04:27:30'),
(20, 4, 'Tuesday', '09:00:00', '17:00:00', 1, '2025-12-01 04:27:30', '2025-12-01 04:27:30'),
(21, 4, 'Wednesday', '09:00:00', '17:00:00', 1, '2025-12-01 04:27:30', '2025-12-01 04:27:30'),
(22, 4, 'Thursday', '09:00:00', '17:00:00', 1, '2025-12-01 04:27:30', '2025-12-01 04:27:30'),
(23, 4, 'Friday', '09:00:00', '17:00:00', 1, '2025-12-01 04:27:30', '2025-12-01 04:27:30'),
(24, 4, 'Saturday', '09:00:00', '17:00:00', 1, '2025-12-01 04:27:30', '2025-12-01 04:27:30'),
(25, 4, 'Sunday', '09:00:00', '17:00:00', 1, '2025-12-01 04:27:30', '2025-12-01 04:27:30'),
(73, 14, 'Monday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:15', '2025-12-29 05:56:15'),
(74, 14, 'Tuesday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:15', '2025-12-29 05:56:15'),
(75, 14, 'Wednesday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:15', '2025-12-29 05:56:15'),
(76, 14, 'Thursday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:15', '2025-12-29 05:56:15'),
(77, 14, 'Friday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:15', '2025-12-29 05:56:15'),
(78, 14, 'Saturday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:15', '2025-12-29 05:56:15'),
(79, 14, 'Sunday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:15', '2025-12-29 05:56:15'),
(80, 17, 'Monday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:38', '2025-12-29 05:56:38'),
(81, 17, 'Tuesday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:38', '2025-12-29 05:56:38'),
(82, 17, 'Wednesday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:38', '2025-12-29 05:56:38'),
(83, 17, 'Thursday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:38', '2025-12-29 05:56:38'),
(84, 17, 'Friday', '09:00:00', '17:00:00', 1, '2025-12-29 05:56:38', '2025-12-29 05:56:38'),
(85, 19, 'Monday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:03', '2025-12-29 05:57:03'),
(86, 19, 'Tuesday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:03', '2025-12-29 05:57:03'),
(87, 19, 'Wednesday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:03', '2025-12-29 05:57:03'),
(88, 19, 'Thursday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:03', '2025-12-29 05:57:03'),
(89, 19, 'Friday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:03', '2025-12-29 05:57:03'),
(90, 19, 'Saturday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:03', '2025-12-29 05:57:03'),
(91, 19, 'Sunday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:03', '2025-12-29 05:57:03'),
(92, 16, 'Monday', '09:00:00', '17:00:00', 1, '2025-12-29 05:57:27', '2025-12-29 05:57:27'),
(93, 16, 'Tuesday', '09:00:00', '17:00:00', 1, '2025-12-29 05:57:27', '2025-12-29 05:57:27'),
(94, 16, 'Wednesday', '09:00:00', '17:00:00', 1, '2025-12-29 05:57:27', '2025-12-29 05:57:27'),
(95, 16, 'Thursday', '09:00:00', '17:00:00', 1, '2025-12-29 05:57:27', '2025-12-29 05:57:27'),
(96, 16, 'Friday', '09:00:00', '17:00:00', 1, '2025-12-29 05:57:27', '2025-12-29 05:57:27'),
(97, 16, 'Saturday', '09:00:00', '17:00:00', 1, '2025-12-29 05:57:27', '2025-12-29 05:57:27'),
(98, 18, 'Monday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:50', '2025-12-29 05:57:50'),
(99, 18, 'Tuesday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:50', '2025-12-29 05:57:50'),
(100, 18, 'Wednesday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:50', '2025-12-29 05:57:50'),
(101, 18, 'Thursday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:50', '2025-12-29 05:57:50'),
(102, 18, 'Friday', '08:00:00', '18:00:00', 1, '2025-12-29 05:57:50', '2025-12-29 05:57:50'),
(103, 15, 'Monday', '09:00:00', '17:00:00', 1, '2025-12-29 05:58:21', '2025-12-29 05:58:21'),
(104, 15, 'Tuesday', '09:00:00', '17:00:00', 1, '2025-12-29 05:58:21', '2025-12-29 05:58:21'),
(105, 15, 'Wednesday', '09:00:00', '17:00:00', 1, '2025-12-29 05:58:21', '2025-12-29 05:58:21'),
(106, 15, 'Thursday', '09:00:00', '17:00:00', 1, '2025-12-29 05:58:21', '2025-12-29 05:58:21'),
(107, 15, 'Friday', '09:00:00', '17:00:00', 1, '2025-12-29 05:58:21', '2025-12-29 05:58:21'),
(108, 15, 'Saturday', '09:00:00', '17:00:00', 1, '2025-12-29 05:58:21', '2025-12-29 05:58:21'),
(109, 15, 'Sunday', '09:00:00', '17:00:00', 1, '2025-12-29 05:58:21', '2025-12-29 05:58:21');

-- --------------------------------------------------------

--
-- Stand-in structure for view `worker_performance_view`
-- (See below for the actual view)
--
CREATE TABLE `worker_performance_view` (
`worker_id` int(11)
,`worker_name` varchar(100)
,`email` varchar(100)
,`category_name` varchar(100)
,`worker_rating` decimal(3,2)
,`total_bookings` bigint(21)
,`completed_bookings` bigint(21)
,`average_feedback_rating` decimal(7,6)
,`total_revenue` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `worker_unavailability`
--

CREATE TABLE `worker_unavailability` (
  `unavailability_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `unavailable_date` date NOT NULL,
  `unavailable_start_time` time NOT NULL,
  `unavailable_end_time` time NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `worker_unavailability`
--

INSERT INTO `worker_unavailability` (`unavailability_id`, `worker_id`, `unavailable_date`, `unavailable_start_time`, `unavailable_end_time`, `reason`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-12-15', '09:00:00', '17:00:00', 'Personal appointment', '2025-12-01 03:52:22', '2025-12-01 03:52:22'),
(2, 2, '2025-12-20', '08:00:00', '12:00:00', 'Training session', '2025-12-01 03:52:22', '2025-12-01 03:52:22');

-- --------------------------------------------------------

--
-- Structure for view `active_workers_view`
--
DROP TABLE IF EXISTS `active_workers_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_workers_view`  AS SELECT `w`.`worker_id` AS `worker_id`, `w`.`worker_name` AS `worker_name`, `w`.`email` AS `email`, `w`.`phone_no` AS `phone_no`, `w`.`skill_type` AS `skill_type`, `w`.`experience_years` AS `experience_years`, `w`.`availability_status` AS `availability_status`, `w`.`rating` AS `rating`, `sc`.`category_name` AS `category_name`, `sc`.`description` AS `category_description`, concat(`w`.`street`,', ',`w`.`city`,', ',`w`.`pincode`) AS `full_address` FROM (`worker` `w` left join `service_category` `sc` on(`w`.`category_id` = `sc`.`category_id`)) WHERE `w`.`availability_status` <> 'offline' ;

-- --------------------------------------------------------

--
-- Structure for view `booking_details_view`
--
DROP TABLE IF EXISTS `booking_details_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `booking_details_view`  AS SELECT `b`.`booking_id` AS `booking_id`, `c`.`name` AS `customer_name`, `c`.`email` AS `customer_email`, `c`.`phone` AS `customer_phone`, `w`.`worker_name` AS `worker_name`, `w`.`phone_no` AS `worker_phone`, `sc`.`category_name` AS `category_name`, `b`.`service_description` AS `service_description`, `b`.`service_date` AS `service_date`, `b`.`service_time` AS `service_time`, `b`.`status` AS `booking_status`, `b`.`total_amount` AS `total_amount`, `p`.`payment_method` AS `payment_method`, `p`.`status` AS `payment_status`, `f`.`rating` AS `feedback_rating`, `f`.`comments` AS `feedback_comments` FROM (((((`booking` `b` join `customer` `c` on(`b`.`customer_id` = `c`.`customer_id`)) join `worker` `w` on(`b`.`worker_id` = `w`.`worker_id`)) left join `service_category` `sc` on(`w`.`category_id` = `sc`.`category_id`)) left join `payment` `p` on(`b`.`booking_id` = `p`.`booking_id`)) left join `feedback` `f` on(`b`.`booking_id` = `f`.`feedback_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `worker_performance_view`
--
DROP TABLE IF EXISTS `worker_performance_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `worker_performance_view`  AS SELECT `w`.`worker_id` AS `worker_id`, `w`.`worker_name` AS `worker_name`, `w`.`email` AS `email`, `sc`.`category_name` AS `category_name`, `w`.`rating` AS `worker_rating`, count(distinct `b`.`booking_id`) AS `total_bookings`, count(distinct case when `b`.`status` = 'completed' then `b`.`booking_id` end) AS `completed_bookings`, avg(`f`.`rating`) AS `average_feedback_rating`, sum(`b`.`total_amount`) AS `total_revenue` FROM (((`worker` `w` left join `service_category` `sc` on(`w`.`category_id` = `sc`.`category_id`)) left join `booking` `b` on(`w`.`worker_id` = `b`.`worker_id`)) left join `feedback` `f` on(`b`.`booking_id` = `f`.`booking_id`)) GROUP BY `w`.`worker_id`, `w`.`worker_name`, `w`.`email`, `sc`.`category_name`, `w`.`rating` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_admin_email` (`email`);

--
-- Indexes for table `availability`
--
ALTER TABLE `availability`
  ADD PRIMARY KEY (`slot_id`),
  ADD UNIQUE KEY `unique_worker_slot` (`worker_id`,`available_date`,`available_time`),
  ADD KEY `idx_availability_worker` (`worker_id`),
  ADD KEY `idx_availability_date` (`available_date`),
  ADD KEY `idx_availability_status` (`status`),
  ADD KEY `idx_availability_date_status` (`available_date`,`status`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `slot_id` (`slot_id`),
  ADD KEY `idx_booking_customer` (`customer_id`),
  ADD KEY `idx_booking_worker` (`worker_id`),
  ADD KEY `idx_booking_category` (`category_id`),
  ADD KEY `idx_booking_status` (`status`),
  ADD KEY `idx_booking_service_date` (`service_date`),
  ADD KEY `idx_booking_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_booking_worker_status` (`worker_id`,`status`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_customer_email` (`email`),
  ADD KEY `idx_customer_phone` (`phone`),
  ADD KEY `idx_customer_city` (`city`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `idx_feedback_booking` (`booking_id`),
  ADD KEY `idx_feedback_rating` (`rating`),
  ADD KEY `idx_feedback_date` (`feedback_date`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `idx_payment_booking` (`booking_id`),
  ADD KEY `idx_payment_status` (`status`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `service_category`
--
ALTER TABLE `service_category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`),
  ADD KEY `managed_by` (`managed_by`),
  ADD KEY `idx_category_name` (`category_name`);

--
-- Indexes for table `worker`
--
ALTER TABLE `worker`
  ADD PRIMARY KEY (`worker_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_worker_email` (`email`),
  ADD KEY `idx_worker_category` (`category_id`),
  ADD KEY `idx_worker_status` (`availability_status`),
  ADD KEY `idx_worker_rating` (`rating`),
  ADD KEY `idx_worker_category_status` (`category_id`,`availability_status`);

--
-- Indexes for table `worker_default_availability`
--
ALTER TABLE `worker_default_availability`
  ADD PRIMARY KEY (`default_availability_id`),
  ADD UNIQUE KEY `unique_worker_day_time` (`worker_id`,`day_of_week`,`start_time`,`end_time`),
  ADD KEY `idx_worker_day` (`worker_id`,`day_of_week`);

--
-- Indexes for table `worker_unavailability`
--
ALTER TABLE `worker_unavailability`
  ADD PRIMARY KEY (`unavailability_id`),
  ADD KEY `idx_worker_unavailable_date` (`worker_id`,`unavailable_date`),
  ADD KEY `idx_unavailable_date` (`unavailable_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `availability`
--
ALTER TABLE `availability`
  MODIFY `slot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_category`
--
ALTER TABLE `service_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `worker`
--
ALTER TABLE `worker`
  MODIFY `worker_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worker_default_availability`
--
ALTER TABLE `worker_default_availability`
  MODIFY `default_availability_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `worker_unavailability`
--
ALTER TABLE `worker_unavailability`
  MODIFY `unavailability_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `availability`
--
ALTER TABLE `availability`
  ADD CONSTRAINT `availability_ibfk_1` FOREIGN KEY (`worker_id`) REFERENCES `worker` (`worker_id`) ON DELETE CASCADE;

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`worker_id`) REFERENCES `worker` (`worker_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `service_category` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `booking_ibfk_4` FOREIGN KEY (`slot_id`) REFERENCES `availability` (`slot_id`) ON DELETE SET NULL;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `service_category`
--
ALTER TABLE `service_category`
  ADD CONSTRAINT `service_category_ibfk_1` FOREIGN KEY (`managed_by`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `worker`
--
ALTER TABLE `worker`
  ADD CONSTRAINT `worker_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_category` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `worker_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `worker_default_availability`
--
ALTER TABLE `worker_default_availability`
  ADD CONSTRAINT `worker_default_availability_ibfk_1` FOREIGN KEY (`worker_id`) REFERENCES `worker` (`worker_id`) ON DELETE CASCADE;

--
-- Constraints for table `worker_unavailability`
--
ALTER TABLE `worker_unavailability`
  ADD CONSTRAINT `worker_unavailability_ibfk_1` FOREIGN KEY (`worker_id`) REFERENCES `worker` (`worker_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
