<?php
session_start();

// Check authentication and authorization
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../medicine_management.php?error=invalid_request");
    exit();
}

// Sanitize and validate inputs
$product_name = $conn->real_escape_string(trim($_POST['product_name']));
$category = $conn->real_escape_string(trim($_POST['category']));
$price = floatval($_POST['price']);
$current_stock = 0; // Initial stock is 0
$manufactured_date = $_POST['manufactured_date'];
$expiry_date = $_POST['expiry_date'];
$how_to_use = $conn->real_escape_string(trim($_POST['how_to_use']));
$side_effects = $conn->real_escape_string(trim($_POST['side_effects']));

// Validate required fields
if (empty($product_name) || empty($category) || $price <= 0) {
    header("Location: ../medicine_management.php?error=invalid_data");
    exit();
}

// Prepare SQL statement
$sql = "INSERT INTO products (
            product_name, 
            category, 
            price, 
            current_stock, 
            manufactured_date, 
            expiry_date, 
            how_to_use, 
            side_effects
        ) VALUES (
            '$product_name', 
            '$category', 
            $price, 
            $current_stock, 
            '$manufactured_date', 
            '$expiry_date', 
            '$how_to_use', 
            '$side_effects'
        )";

// Execute query
if ($conn->query($sql)) {
    header("Location: ../medicine_management.php?success=added");
} else {
    error_log("Database error: " . $conn->error);
    header("Location: ../medicine_management.php?error=failed");
}

// Close connection
closeConnection();
?>
