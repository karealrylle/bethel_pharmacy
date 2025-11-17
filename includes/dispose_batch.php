<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$batch_id = intval($data['batch_id']);
$disposal_reason = isset($data['reason']) ? $data['reason'] : 'expiry'; // 'expiry' or 'damage'
$user_id = $_SESSION['id'];

// Get batch info before disposing
$query = "SELECT pb.quantity, pb.batch_number, pb.product_id, p.product_name 
          FROM product_batches pb 
          JOIN products p ON pb.product_id = p.product_id 
          WHERE pb.batch_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();
$batch = $result->fetch_assoc();
$stmt->close();

if (!$batch) {
    echo json_encode(['success' => false, 'message' => 'Batch not found']);
    exit();
}

$quantity_disposed = $batch['quantity'];
$product_id = $batch['product_id'];

// Record the disposal movement BEFORE deleting
$movement_type = $disposal_reason; // 'expiry' or 'damage'
$negative_qty = -$quantity_disposed;
$notes = "Batch disposed: " . $batch['batch_number'];

$movement_sql = "INSERT INTO batch_movements (batch_id, movement_type, quantity, remaining_quantity, performed_by, notes) 
                 VALUES (?, ?, ?, 0, ?, ?)";
$movement_stmt = $conn->prepare($movement_sql);
$movement_stmt->bind_param("isiss", $batch_id, $movement_type, $negative_qty, $user_id, $notes);
$movement_stmt->execute();
$movement_stmt->close();

// Update batch status to depleted (don't delete, for tracking)
$update_sql = "UPDATE product_batches SET quantity = 0, status = 'depleted' WHERE batch_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $batch_id);
$update_success = $update_stmt->execute();
$update_stmt->close();

// Update product legacy stock
$product_sql = "UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE product_id = ?";
$product_stmt = $conn->prepare($product_sql);
$product_stmt->bind_param("ii", $quantity_disposed, $product_id);
$product_stmt->execute();
$product_stmt->close();

if ($update_success) {
    echo json_encode(['success' => true, 'message' => 'Batch disposed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>