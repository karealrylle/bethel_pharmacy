<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}
date_default_timezone_set('Asia/Manila');
$current_date = date('l, F j, Y');
$current_time = date('h:i A');

$hour = (int)date('G');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bethel Pharmacy - Staff Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <meta http-equiv="refresh" content="60">
</head>
<body>
    <header class="header">
        <img src="assets/bethel_logo.png" alt="Bethel Pharmacy" class="logo">
        <div class="datetime">
            <div><?php echo $current_date; ?></div>
            <div><?php echo $current_time; ?></div>
        </div>
    </header>
    
    <nav class="sidebar">
        <div class="profile-container">
            <div class="profile-image"></div>
            <div class="profile-info">
                <div class="profile-username"><?php echo $_SESSION['username']; ?></div>
                <div class="profile-role"><?php echo ucfirst($_SESSION['role']); ?></div>
            </div>

            <div class="profile-menu" onclick="this.nextElementSibling.classList.toggle('show')">&vellip;</div>
            <div class="profile-dropdown">
                <button class="dropdown-button">View Profile</button>
                <a href="index.php" style="text-decoration: none; display: contents;">
                    <button class="dropdown-button">Log out</button>
                </a>
            </div>
        </div>

        <div class="nav-buttons">
            <a href="staff_dashboard.php" class="nav-button active"><img src="assets/dashboard.png" alt="">Dashboard</a>
            <a href="pos.php" class="nav-button"><img src="assets/dashboard.png" alt="">POS</a>
            <a href="inventory.php" class="nav-button"><img src="assets/inventory.png" alt="">Inventory</a>
            <a href="shift_report.php" class="nav-button"><img src="assets/reports.png" alt="">Shift Report</a>
            <a href="notifications.php" class="nav-button"><img src="assets/notifs.png" alt="">Notifications</a>
            <a href="settings.php" class="nav-button"><img src="assets/settings.png" alt="">Settings</a>
            <a href="help.php" class="nav-button"><img src="assets/help.png" alt="">Get Technical Help</a>
        </div>

        <div class="sidebar-credits">Made by ACT-2C G5 © 2025-2026</div>
    </nav>

    <div class="content">
        <div class="search-container">
            <form method="GET" class="search-form">
                <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" class="search-input" placeholder="Search here">
                <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer;">
                    <img src="assets/search.png" alt="Search" class="search-icon">
                </button>
            </form>
        </div>
        <h2><?php echo $greeting; ?>, <?php echo $_SESSION['username']; ?></h2>
        <div class="content-container">
            <div class="box green"></div>
            <div class="box yellow"></div>
            <div class="box blue"></div>
            <div class="box red"></div>
        </div>
    </div>
</body>
</html>