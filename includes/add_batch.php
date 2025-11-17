<?php
// includes/add_batch.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../update_stock.php?error=invalid");
    exit();
}

$conn = new mysqli("localhost", "root", "", "bethel_pharmacy");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID from session
$user_id = $_SESSION['id'];

// Get and validate inputs
$product_id = intval($_POST['product_id']);
$batch_number = $conn->real_escape_string(trim($_POST['batch_number']));
$quantity = intval($_POST['quantity']);
$manufactured_date = $_POST['manufactured_date'];
$expiry_date = $_POST['expiry_date'];
$supplier_name = isset($_POST['supplier_name']) ? $conn->real_escape_string(trim($_POST['supplier_name'])) : null;
$purchase_price = isset($_POST['purchase_price']) && !empty($_POST['purchase_price']) ? floatval($_POST['purchase_price']) : null;

// Validation
if ($product_id <= 0 || $quantity <= 0 || empty($batch_number)) {
    header("Location: ../update_stock.php?error=invalid_data");
    exit();
}

// Check if batch number already exists for this product
$check = $conn->prepare("SELECT batch_id FROM product_batches WHERE product_id = ? AND batch_number = ?");
$check->bind_param("is", $product_id, $batch_number);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header("Location: ../update_stock.php?error=duplicate_batch");
    exit();
}
$check->close();

// Use stored procedure to add batch
$stmt = $conn->prepare("CALL sp_add_batch(?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issidsdi", $product_id, $batch_number, $quantity, $manufactured_date, $expiry_date, $supplier_name, $purchase_price, $user_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: ../update_stock.php?success=batch_added");
    exit();
} else {
    $error = $conn->error;
    $stmt->close();
    $conn->close();
    header("Location: ../update_stock.php?error=failed&details=" . urlencode($error));
    exit();
}
?>