-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 02:00 PM
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
-- Database: `oc-gst-invoice`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_admin`
--

CREATE TABLE `tbl_admin` (
  `id` bigint(20) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` text NOT NULL,
  `is_active` char(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tbl_admin`
--

INSERT INTO `tbl_admin` (`id`, `username`, `email`, `password`, `is_active`, `created_at`, `modified_at`) VALUES
(1, 'admin', 'admin@gmail.com', '21232f297a57a5a743894a0e4a801fc3', '1', '2026-08-07 07:01:35', '2026-08-07 07:01:35');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_city`
--

CREATE TABLE `tbl_city` (
  `id` bigint(20) NOT NULL,
  `state_id` bigint(20) NOT NULL,
  `city_name` varchar(255) NOT NULL,
  `order_no` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','deactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_city`
--

INSERT INTO `tbl_city` (`id`, `state_id`, `city_name`, `order_no`, `status`, `created_at`, `modified_at`) VALUES
(1, 1, 'Visakhapatnam', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(2, 1, 'Vijayawada', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(3, 1, 'Guntur', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(4, 1, 'Tirupati', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(5, 1, 'Nellore', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(6, 1, 'Kurnool', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(7, 1, 'Rajahmundry', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(8, 1, 'Kakinada', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(9, 1, 'Kadapa', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(10, 1, 'Anantapur', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(11, 2, 'Itanagar', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(12, 2, 'Naharlagun', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(13, 2, 'Tawang', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(14, 2, 'Pasighat', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(15, 2, 'Ziro', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(16, 2, 'Bomdila', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(17, 2, 'Tezu', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(18, 2, 'Namsai', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(19, 2, 'Aalo', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(20, 2, 'Roing', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(21, 3, 'Guwahati', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(22, 3, 'Dibrugarh', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(23, 3, 'Silchar', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(24, 3, 'Jorhat', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(25, 3, 'Tezpur', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(26, 3, 'Nagaon', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(27, 3, 'Tinsukia', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(28, 3, 'Sivasagar', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(29, 3, 'Diphu', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(30, 3, 'Goalpara', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(31, 4, 'Patna', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(32, 4, 'Gaya', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(33, 4, 'Muzaffarpur', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(34, 4, 'Bhagalpur', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(35, 4, 'Darbhanga', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(36, 4, 'Purnia', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(37, 4, 'Ara', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(38, 4, 'Begusarai', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(39, 4, 'Katihar', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(40, 4, 'Bihar Sharif', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(41, 5, 'Raipur', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(42, 5, 'Bilaspur', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(43, 5, 'Durg', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(44, 5, 'Bhilai', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(45, 5, 'Korba', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(46, 5, 'Rajnandgaon', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(47, 5, 'Jagdalpur', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(48, 5, 'Ambikapur', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(49, 5, 'Raigarh', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(50, 5, 'Dhamtari', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(51, 6, 'Panaji', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(52, 6, 'Vasco da Gama', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(53, 6, 'Margao', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(54, 6, 'Mapusa', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(55, 6, 'Ponda', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(56, 6, 'Bicholim', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(57, 6, 'Canacona', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(58, 6, 'Quepem', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(59, 6, 'Cuncolim', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(60, 6, 'Valpoi', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(61, 7, 'Ahmedabad', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(62, 7, 'Surat', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(63, 7, 'Vadodara', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(64, 7, 'Rajkot', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(65, 7, 'Gandhinagar', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(66, 7, 'Bhavnagar', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(67, 7, 'Jamnagar', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(68, 7, 'Junagadh', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(69, 7, 'Anand', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(70, 7, 'Bharuch', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(71, 8, 'Gurugram', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(72, 8, 'Faridabad', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(73, 8, 'Panipat', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(74, 8, 'Ambala', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(75, 8, 'Hisar', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(76, 8, 'Karnal', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(77, 8, 'Rohtak', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(78, 8, 'Sonipat', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(79, 8, 'Yamunanagar', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(80, 8, 'Panchkula', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(81, 9, 'Shimla', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(82, 9, 'Dharamshala', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(83, 9, 'Solan', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(84, 9, 'Mandi', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(85, 9, 'Kullu', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(86, 9, 'Manali', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(87, 9, 'Chamba', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(88, 9, 'Hamirpur', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(89, 9, 'Una', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(90, 9, 'Bilaspur', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(91, 10, 'Ranchi', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(92, 10, 'Jamshedpur', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(93, 10, 'Dhanbad', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(94, 10, 'Bokaro', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(95, 10, 'Deoghar', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(96, 10, 'Hazaribagh', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(97, 10, 'Giridih', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(98, 10, 'Ramgarh', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(99, 10, 'Dumka', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(100, 10, 'Chaibasa', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(101, 11, 'Bengaluru', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(102, 11, 'Mysuru', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(103, 11, 'Mangaluru', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(104, 11, 'Hubballi', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(105, 11, 'Dharwad', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(106, 11, 'Belagavi', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(107, 11, 'Shivamogga', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(108, 11, 'Tumakuru', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(109, 11, 'Ballari', 9, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(110, 11, 'Davanagere', 10, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(111, 12, 'Thiruvananthapuram', 1, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(112, 12, 'Kochi', 2, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(113, 12, 'Kozhikode', 3, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(114, 12, 'Thrissur', 4, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(115, 12, 'Kollam', 5, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(116, 12, 'Kannur', 6, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(117, 12, 'Alappuzha', 7, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(118, 12, 'Palakkad', 8, 'active', '2026-09-17 11:39:24', '2026-09-17 11:39:24'),
(119, 12, 'Kottayam', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(120, 12, 'Malappuram', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(121, 13, 'Bhopal', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(122, 13, 'Indore', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(123, 13, 'Jabalpur', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(124, 13, 'Gwalior', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(125, 13, 'Ujjain', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(126, 13, 'Sagar', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(127, 13, 'Rewa', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(128, 13, 'Satna', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(129, 13, 'Ratlam', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(130, 13, 'Dewas', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(131, 14, 'Mumbai', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(132, 14, 'Pune', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(133, 14, 'Nagpur', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(134, 14, 'Nashik', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(135, 14, 'Thane', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(136, 14, 'Aurangabad', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(137, 14, 'Kolhapur', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(138, 14, 'Solapur', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(139, 14, 'Amravati', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(140, 14, 'Nanded', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(141, 15, 'Imphal', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(142, 15, 'Thoubal', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(143, 15, 'Churachandpur', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(144, 15, 'Ukhrul', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(145, 15, 'Senapati', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(146, 15, 'Tamenglong', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(147, 15, 'Bishnupur', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(148, 15, 'Kakching', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(149, 15, 'Jiribam', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(150, 15, 'Moreh', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(151, 16, 'Shillong', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(152, 16, 'Tura', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(153, 16, 'Jowai', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(154, 16, 'Nongpoh', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(155, 16, 'Williamnagar', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(156, 16, 'Baghmara', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(157, 16, 'Nongstoin', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(158, 16, 'Mairang', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(159, 16, 'Khliehriat', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(160, 16, 'Resubelpara', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(161, 17, 'Aizawl', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(162, 17, 'Lunglei', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(163, 17, 'Champhai', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(164, 17, 'Kolasib', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(165, 17, 'Serchhip', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(166, 17, 'Lawngtlai', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(167, 17, 'Mamit', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(168, 17, 'Saiha', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(169, 17, 'Hnahthial', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(170, 17, 'Khawzawl', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(171, 18, 'Kohima', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(172, 18, 'Dimapur', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(173, 18, 'Mokokchung', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(174, 18, 'Tuensang', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(175, 18, 'Wokha', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(176, 18, 'Mon', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(177, 18, 'Zunheboto', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(178, 18, 'Phek', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(179, 18, 'Kiphire', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(180, 18, 'Chümoukedima', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(181, 19, 'Bhubaneswar', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(182, 19, 'Cuttack', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(183, 19, 'Rourkela', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(184, 19, 'Berhampur', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(185, 19, 'Sambalpur', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(186, 19, 'Puri', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(187, 19, 'Balasore', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(188, 19, 'Baripada', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(189, 19, 'Jharsuguda', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(190, 19, 'Bhadrak', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(191, 20, 'Ludhiana', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(192, 20, 'Amritsar', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(193, 20, 'Jalandhar', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(194, 20, 'Patiala', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(195, 20, 'Bathinda', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(196, 20, 'Mohali', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(197, 20, 'Hoshiarpur', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(198, 20, 'Pathankot', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(199, 20, 'Moga', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(200, 20, 'Batala', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(201, 21, 'Jaipur', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(202, 21, 'Jodhpur', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(203, 21, 'Udaipur', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(204, 21, 'Kota', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(205, 21, 'Ajmer', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(206, 21, 'Bikaner', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(207, 21, 'Alwar', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(208, 21, 'Bharatpur', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(209, 21, 'Sikar', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(210, 21, 'Bhilwara', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(211, 22, 'Gangtok', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(212, 22, 'Namchi', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(213, 22, 'Gyalshing', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(214, 22, 'Mangan', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(215, 22, 'Singtam', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(216, 22, 'Rangpo', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(217, 22, 'Jorethang', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(218, 22, 'Soreng', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(219, 22, 'Pakyong', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(220, 22, 'Ravangla', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(221, 23, 'Chennai', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(222, 23, 'Coimbatore', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(223, 23, 'Madurai', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(224, 23, 'Tiruchirappalli', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(225, 23, 'Salem', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(226, 23, 'Tiruppur', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(227, 23, 'Erode', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(228, 23, 'Vellore', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(229, 23, 'Thanjavur', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(230, 23, 'Tirunelveli', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(231, 24, 'Hyderabad', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(232, 24, 'Warangal', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(233, 24, 'Nizamabad', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(234, 24, 'Karimnagar', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(235, 24, 'Khammam', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(236, 24, 'Ramagundam', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(237, 24, 'Mahbubnagar', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(238, 24, 'Nalgonda', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(239, 24, 'Adilabad', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(240, 24, 'Suryapet', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(241, 25, 'Agartala', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(242, 25, 'Udaipur', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(243, 25, 'Dharmanagar', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(244, 25, 'Kailasahar', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(245, 25, 'Belonia', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(246, 25, 'Ambassa', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(247, 25, 'Khowai', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(248, 25, 'Sabroom', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(249, 25, 'Teliamura', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(250, 25, 'Kumarghat', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(251, 26, 'Lucknow', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(252, 26, 'Kanpur', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(253, 26, 'Agra', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(254, 26, 'Varanasi', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(255, 26, 'Prayagraj', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(256, 26, 'Ghaziabad', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(257, 26, 'Noida', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(258, 26, 'Meerut', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(259, 26, 'Gorakhpur', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(260, 26, 'Bareilly', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(261, 27, 'Dehradun', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(262, 27, 'Haridwar', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(263, 27, 'Rishikesh', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(264, 27, 'Haldwani', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(265, 27, 'Nainital', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(266, 27, 'Roorkee', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(267, 27, 'Rudrapur', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(268, 27, 'Kashipur', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(269, 27, 'Almora', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(270, 27, 'Pithoragarh', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(271, 28, 'Kolkata', 1, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(272, 28, 'Howrah', 2, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(273, 28, 'Siliguri', 3, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(274, 28, 'Durgapur', 4, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(275, 28, 'Asansol', 5, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(276, 28, 'Darjeeling', 6, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(277, 28, 'Kharagpur', 7, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(278, 28, 'Malda', 8, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(279, 28, 'Bardhaman', 9, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25'),
(280, 28, 'Haldia', 10, 'active', '2026-09-17 11:39:25', '2026-09-17 11:39:25');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_company`
--

CREATE TABLE `tbl_company` (
  `id` bigint(20) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `gst_no` varchar(50) DEFAULT NULL,
  `state_id` bigint(20) DEFAULT 0,
  `city_id` bigint(20) DEFAULT 0,
  `address` text DEFAULT NULL,
  `owner_name` varchar(255) DEFAULT NULL,
  `mobile_no` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `status` enum('active','deactive') NOT NULL DEFAULT 'active',
  `order_no` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_company`
--

INSERT INTO `tbl_company` (`id`, `company_name`, `gst_no`, `state_id`, `city_id`, `address`, `owner_name`, `mobile_no`, `email`, `username`, `password`, `status`, `order_no`, `created_at`, `modified_at`) VALUES
(1, 'Ocean Infotech', '', 7, 64, 'rajkot', 'Sandip Gajera', '7418529630', 'riddhibutaniocean@gmail.com', 'oceanadmin', '0e7517141fb53f21ee439b355b5a1d0a', 'active', 0, '2026-09-17 12:03:51', '2026-09-17 12:07:56');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_party`
--

CREATE TABLE `tbl_party` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL DEFAULT 0,
  `party_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `state_id` int(11) NOT NULL DEFAULT 0,
  `city_id` int(11) NOT NULL DEFAULT 0,
  `pincode` varchar(20) DEFAULT '',
  `mobile_no` varchar(20) DEFAULT '',
  `gst_no` varchar(50) DEFAULT '',
  `party_status` varchar(50) DEFAULT 'Registered',
  `status` enum('active','deactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_party`
--

INSERT INTO `tbl_party` (`id`, `company_id`, `party_name`, `address`, `state_id`, `city_id`, `pincode`, `mobile_no`, `gst_no`, `party_status`, `status`, `created_at`) VALUES
(1, 1, 'Riddhi Butani', 'rajkot', 7, 64, '360004', '9984035620', '', 'Sales', 'active', '2026-09-17 12:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_product`
--

CREATE TABLE `tbl_product` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL DEFAULT 0,
  `product_name` varchar(255) NOT NULL,
  `hsn_code` varchar(50) DEFAULT '',
  `purchase_price` decimal(12,2) DEFAULT 0.00,
  `sales_price` decimal(12,2) DEFAULT 0.00,
  `status` enum('active','deactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_product`
--

INSERT INTO `tbl_product` (`id`, `company_id`, `product_name`, `hsn_code`, `purchase_price`, `sales_price`, `status`, `created_at`) VALUES
(1, 1, 'sdsad', 'sad', 2321.00, 0.00, 'active', '2026-09-17 12:57:51');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quotation`
--

CREATE TABLE `tbl_quotation` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT 0,
  `party_id` int(11) DEFAULT 0,
  `party_name` varchar(255) DEFAULT '',
  `gst_no` varchar(50) DEFAULT '',
  `quotation_no` varchar(50) DEFAULT '',
  `rca` varchar(10) DEFAULT 'No',
  `address` text DEFAULT NULL,
  `state_id` int(11) DEFAULT 0,
  `state_name` varchar(100) DEFAULT '',
  `city_id` int(11) DEFAULT 0,
  `city_name` varchar(100) DEFAULT '',
  `quotation_date` date DEFAULT NULL,
  `gst_type` varchar(20) DEFAULT 'with_gst',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `cgst_amount` decimal(12,2) DEFAULT 0.00,
  `sgst_amount` decimal(12,2) DEFAULT 0.00,
  `igst_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `grand_total` decimal(12,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_quotation`
--

INSERT INTO `tbl_quotation` (`id`, `company_id`, `party_id`, `party_name`, `gst_no`, `quotation_no`, `rca`, `address`, `state_id`, `state_name`, `city_id`, `city_name`, `quotation_date`, `gst_type`, `total_amount`, `cgst_amount`, `sgst_amount`, `igst_amount`, `tax_amount`, `grand_total`, `status`, `created_at`) VALUES
(1, 1, 1, 'Riddhi Butani', '', 'OQ/001/26-27', 'No', 'rajkot', 7, 'Gujarat', 64, '', '2026-09-18', 'without_gst', 500.00, 0.00, 0.00, 0.00, 0.00, 500.00, 'active', '2026-09-18 10:33:28'),
(2, 1, 1, 'Riddhi Butani', '', 'OQ/001/26-27', 'No', 'rajkot', 7, 'Gujarat', 64, '', '2026-09-18', 'without_gst', 500.00, 0.00, 0.00, 0.00, 0.00, 500.00, 'active', '2026-09-18 10:35:41');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quotation_items`
--

CREATE TABLE `tbl_quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT '',
  `rate` decimal(12,2) DEFAULT 0.00,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `qty` decimal(10,2) DEFAULT 1.00,
  `net_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_quotation_items`
--

INSERT INTO `tbl_quotation_items` (`id`, `quotation_id`, `product_id`, `description`, `hsn_code`, `rate`, `gst_percent`, `qty`, `net_amount`, `tax_amount`, `total_amount`) VALUES
(1, 2, 0, 'sdsad', 'sad', 500.00, 18.00, 1.00, 500.00, 0.00, 500.00);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_state`
--

CREATE TABLE `tbl_state` (
  `id` bigint(20) NOT NULL,
  `state_name` varchar(255) NOT NULL,
  `state_code` varchar(50) NOT NULL,
  `status` enum('active','deactive') NOT NULL DEFAULT 'active',
  `order_no` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_state`
--

INSERT INTO `tbl_state` (`id`, `state_name`, `state_code`, `status`, `order_no`, `created_at`, `modified_at`) VALUES
(1, 'Andhra Pradesh', '37', 'active', 1, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(2, 'Arunachal Pradesh', '12', 'active', 2, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(3, 'Assam', '18', 'active', 3, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(4, 'Bihar', '10', 'active', 4, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(5, 'Chhattisgarh', '22', 'active', 5, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(6, 'Goa', '30', 'active', 6, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(7, 'Gujarat', '24', 'active', 7, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(8, 'Haryana', '06', 'active', 8, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(9, 'Himachal Pradesh', '02', 'active', 9, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(10, 'Jharkhand', '20', 'active', 10, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(11, 'Karnataka', '29', 'active', 11, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(12, 'Kerala', '32', 'active', 12, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(13, 'Madhya Pradesh', '23', 'active', 13, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(14, 'Maharashtra', '27', 'active', 14, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(15, 'Manipur', '14', 'active', 15, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(16, 'Meghalaya', '17', 'active', 16, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(17, 'Mizoram', '15', 'active', 17, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(18, 'Nagaland', '13', 'active', 18, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(19, 'Odisha', '21', 'active', 19, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(20, 'Punjab', '03', 'active', 20, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(21, 'Rajasthan', '08', 'active', 21, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(22, 'Sikkim', '11', 'active', 22, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(23, 'Tamil Nadu', '33', 'active', 23, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(24, 'Telangana', '36', 'active', 24, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(25, 'Tripura', '16', 'active', 25, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(26, 'Uttar Pradesh', '09', 'active', 26, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(27, 'Uttarakhand', '05', 'active', 27, '2026-09-17 11:28:40', '2026-09-17 11:28:40'),
(28, 'West Bengal', '19', 'active', 28, '2026-09-17 11:28:40', '2026-09-17 11:28:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_admin`
--
ALTER TABLE `tbl_admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_city`
--
ALTER TABLE `tbl_city`
  ADD PRIMARY KEY (`id`),
  ADD KEY `state_id` (`state_id`);

--
-- Indexes for table `tbl_company`
--
ALTER TABLE `tbl_company`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_party`
--
ALTER TABLE `tbl_party`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_product`
--
ALTER TABLE `tbl_product`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_quotation`
--
ALTER TABLE `tbl_quotation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_quotation_items`
--
ALTER TABLE `tbl_quotation_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_state`
--
ALTER TABLE `tbl_state`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_admin`
--
ALTER TABLE `tbl_admin`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_city`
--
ALTER TABLE `tbl_city`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=281;

--
-- AUTO_INCREMENT for table `tbl_company`
--
ALTER TABLE `tbl_company`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_party`
--
ALTER TABLE `tbl_party`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_product`
--
ALTER TABLE `tbl_product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_quotation`
--
ALTER TABLE `tbl_quotation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_quotation_items`
--
ALTER TABLE `tbl_quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_state`
--
ALTER TABLE `tbl_state`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
