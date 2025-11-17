<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "bethel_pharmacy");
if ($conn->connect_error) die("Connection failed");

$product_id = $_POST['product_id'];
$batch_id = $_POST['batch_id'] ?? null;
$current_stock = (int)$_POST['current_stock'];
$added_stock = (int)$_POST['added_stock'];
$new_stock = $current_stock + $added_stock;
$user_id = $_SESSION['id'];

// Update product legacy stock
$sql = "UPDATE products SET current_stock = ? WHERE product_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $new_stock, $product_id);
$stmt->execute();

// If batch_id is provided, update the batch and record movement
if ($batch_id) {
    // Get current batch quantity
    $batch_query = "SELECT quantity FROM product_batches WHERE batch_id = ?";
    $batch_stmt = $conn->prepare($batch_query);
    $batch_stmt->bind_param("i", $batch_id);
    $batch_stmt->execute();
    $batch_result = $batch_stmt->get_result();
    $batch_data = $batch_result->fetch_assoc();
    $old_batch_qty = $batch_data['quantity'];
    $new_batch_qty = $old_batch_qty + $added_stock;
    
    // Update batch quantity
    $update_batch = "UPDATE product_batches SET quantity = ? WHERE batch_id = ?";
    $update_stmt = $conn->prepare($update_batch);
    $update_stmt->bind_param("ii", $new_batch_qty, $batch_id);
    $update_stmt->execute();
    
    // Record the movement
    $movement_sql = "INSERT INTO batch_movements (batch_id, movement_type, quantity, remaining_quantity, performed_by, notes) 
                     VALUES (?, 'restock', ?, ?, ?, 'Stock updated via inventory management')";
    $movement_stmt = $conn->prepare($movement_sql);
    $movement_stmt->bind_param("iiii", $batch_id, $added_stock, $new_batch_qty, $user_id);
    $movement_stmt->execute();
}

$conn->close();
header("Location: update_stock.php?success=1");
exit();
?>