<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Check which form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // REGISTER PROCESS
    if (isset($_POST['register'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords match
        if ($password !== $confirm_password) {
            header("Location: ../register.php?error=password_mismatch");
            exit();
        }
        
        // Validate password strength (minimum 8 characters)
        if (strlen($password) < 8) {
            header("Location: ../register.php?error=weak_password");
            exit();
        }
        
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            header("Location: ../register.php?error=exists");
            exit();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user (default role is 'staff')
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'staff')");
        $stmt->bind_param("sss", $username, $email, $hashed_password);
        
        if ($stmt->execute()) {
            header("Location: ../index.php?success=registered");
            exit();
        } else {
            header("Location: ../register.php?error=failed");
            exit();
        }
        
        $stmt->close();
        $check_stmt->close();
    }
    
    // LOGIN PROCESS
    elseif (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, email, password, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    header("Location: ../index.php?error=inactive");
                    exit();
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                // Update last login
                $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../admin_dashboard.php");
                } else {
                    header("Location: ../staff_dashboard.php");
                }
                exit();
                
            } else {
                // Invalid password
                header("Location: ../index.php?error=invalid");
                exit();
            }
        } else {
            // User not found
            header("Location: ../index.php?error=invalid");
            exit();
        }
        
        $stmt->close();
    }
}

$conn->close();
?>