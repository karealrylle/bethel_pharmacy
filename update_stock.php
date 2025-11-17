<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
date_default_timezone_set('Asia/Manila');
$current_date = date('l, F j, Y');
$current_time = date('h:i A');

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_batch_count') {
    $conn = new mysqli("localhost", "root", "", "bethel_pharmacy");
    $product_id = intval($_GET['product_id']);
    $result = $conn->query("SELECT COUNT(*) as count FROM product_batches WHERE product_id = $product_id");
    $row = $result->fetch_assoc();
    echo json_encode(['count' => $row['count']]);
    $conn->close();
    exit();
}

$conn = new mysqli("localhost", "root", "", "bethel_pharmacy");
if ($conn->connect_error) die("Connection failed");

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$stock_level = isset($_GET['stock_level']) ? $_GET['stock_level'] : '';
$expiry_status = isset($_GET['expiry_status']) ? $_GET['expiry_status'] : '';

$sql = "SELECT p.product_id, p.product_name, p.category, p.current_stock, p.price, p.expiry_date, p.manufactured_date, p.how_to_use, p.side_effects, p.reorder_level, COALESCE(SUM(CASE WHEN pb.status = 'available' AND pb.expiry_date > CURDATE() AND DATEDIFF(pb.expiry_date, CURDATE()) > 365 THEN pb.quantity ELSE 0 END), 0) AS total_stock, COUNT(CASE WHEN pb.status = 'available' AND DATEDIFF(pb.expiry_date, CURDATE()) > 365 THEN pb.batch_id END) AS batch_count, MIN(CASE WHEN pb.status = 'available' AND DATEDIFF(pb.expiry_date, CURDATE()) > 365 THEN pb.expiry_date END) AS nearest_expiry FROM products p LEFT JOIN product_batches pb ON p.product_id = pb.product_id WHERE 1=1";

if ($search) $sql .= " AND (p.product_name LIKE '%$search%' OR p.category LIKE '%$search%')";
if ($category) $sql .= " AND p.category = '$category'";
$sql .= " GROUP BY p.product_id";

if ($stock_level == 'good') $sql .= " HAVING total_stock > 50";
if ($stock_level == 'low') $sql .= " HAVING total_stock BETWEEN 1 AND 50";
if ($stock_level == 'out') $sql .= " HAVING total_stock = 0";

$result = $conn->query($sql);
$today = new DateTime();
$filtered_results = [];

while($row = $result->fetch_assoc()) {
    $expiry_date = $row['nearest_expiry'] ? $row['nearest_expiry'] : $row['expiry_date'];
    if ($expiry_date) {
        $expiry = new DateTime($expiry_date);
        $diff = $today->diff($expiry)->days;
        $is_expired = $today > $expiry;
        $status = $is_expired ? 'expired' : ($diff <= 180 ? 'expiring' : 'fresh');
    } else {
        $status = 'fresh';
    }
    
    if (!$expiry_status || $status == $expiry_status) {
        $row['status'] = $status;
        $filtered_results[] = $row;
    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bethel Pharmacy - Add Batch</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/management.css">
    <link rel="stylesheet" href="css/tables.css">
    <link rel="stylesheet" href="css/modals.css">
    <link rel="stylesheet" href="css/batch_modal.css">
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
            <a href="admin_dashboard.php" class="nav-button"><img src="assets/dashboard.png" alt="">Dashboard</a>
            <a href="inventory.php" class="nav-button"><img src="assets/inventory.png" alt="">Inventory</a>
            <a href="medicine_management.php" class="nav-button"><img src="assets/management.png" alt="">Medicine Management</a>
            <a href="reports.php" class="nav-button"><img src="assets/reports.png" alt="">Reports</a>
            <a href="staff_management.php" class="nav-button"><img src="assets/staff.png" alt="">Staff Management</a>
            <a href="notifications.php" class="nav-button"><img src="assets/notifs.png" alt="">Notifications</a>
            <a href="settings.php" class="nav-button"><img src="assets/settings.png" alt="">Settings</a>
            <a href="help.php" class="nav-button"><img src="assets/help.png" alt="">Get Technical Help</a>
        </div>
        <div class="sidebar-credits">Made by ACT-2C G5 © 2025-2026</div>
    </nav>
    
    <div class="content">
        <h2>Inventory >> Update Stock</h2>
        
        <div class="management-panel">
            <div class="panel-controls">
                <form method="GET" class="search-form">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="search-input" placeholder="Search products...">
                    <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer;">
                        <img src="assets/search.png" alt="Search" class="search-icon">
                    </button>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <input type="hidden" name="stock_level" value="<?php echo htmlspecialchars($stock_level); ?>">
                    <input type="hidden" name="expiry_status" value="<?php echo htmlspecialchars($expiry_status); ?>">
                </form>

                <form method="GET" class="filter-form">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category" class="filter-dropdown">
                        <option value="">All Categories</option>
                        <option value="Prescription Medicines" <?php echo ($category == 'Prescription Medicines') ? 'selected' : ''; ?>>Prescription Medicines</option>
                        <option value="Over-the-Counter (OTC) Products" <?php echo ($category == 'Over-the-Counter (OTC) Products') ? 'selected' : ''; ?>>OTC Products</option>
                        <option value="Health & Personal Care" <?php echo ($category == 'Health & Personal Care') ? 'selected' : ''; ?>>Health & Personal Care</option>
                        <option value="Medical Supplies & Equipment" <?php echo ($category == 'Medical Supplies & Equipment') ? 'selected' : ''; ?>>Medical Supplies</option>
                    </select>
                    <select name="stock_level" class="filter-dropdown">
                        <option value="">All Stock Levels</option>
                        <option value="high" <?php echo ($stock_level == 'high') ? 'selected' : ''; ?>>High Stock (>200)</option>
                        <option value="medium" <?php echo ($stock_level == 'medium') ? 'selected' : ''; ?>>Medium Stock (50-200)</option>
                        <option value="low" <?php echo ($stock_level == 'low') ? 'selected' : ''; ?>>Low Stock (<=50)</option>
                        <option value="out" <?php echo ($stock_level == 'out') ? 'selected' : ''; ?>>Out of Stock (0)</option>
                    </select>
                    <select name="expiry_status" class="filter-dropdown">
                        <option value="">All Expiry Status</option>
                        <option value="fresh" <?php echo ($expiry_status == 'fresh') ? 'selected' : ''; ?>>Fresh (>6 months)</option>
                        <option value="expiring" <?php echo ($expiry_status == 'expiring') ? 'selected' : ''; ?>>Expiring Soon (≤6 months)</option>
                        <option value="expired" <?php echo ($expiry_status == 'expired') ? 'selected' : ''; ?>>Expired</option>
                    </select>
                    <button type="submit" class="apply-filter-btn">Apply Filters</button>
                    <a href="update_stock.php" class="reset-filter-btn">Reset Filters</a>
                </form>
                
            </div>
            
            <div class="products-table-container">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Total Stock</th>
                            <th>View Batches</th>
                            <th>Next Expiry</th>
                            <th>Add Batch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($filtered_results as $row): 
                            $stock = $row['total_stock'];
                            $stock_class = 'status-red';
                            if ($stock > $row['reorder_level']) {
                                $stock_class = 'status-green';
                            } else if ($stock > 0) {
                                $stock_class = 'status-orange';
                            }

                            $batch_count = $row['batch_count'];
                            $expiry_display = $row['nearest_expiry'] ? date('M d, Y', strtotime($row['nearest_expiry'])) : 'N/A';

                            // Check the earliest expiry across ALL batches (not just fresh ones)
                            $batch_class = 'fresh';
                            $all_batch_query = $conn->query("SELECT MIN(expiry_date) as earliest_expiry FROM product_batches WHERE product_id = {$row['product_id']} AND quantity > 0");
                            $earliest = $all_batch_query->fetch_assoc()['earliest_expiry'];

                            if ($earliest) {
                                $expiry = new DateTime($earliest);
                                $diff = $today->diff($expiry);
                                $months_left = ($diff->y * 12) + $diff->m + ($diff->d / 30);
                                
                                if ($today > $expiry) {
                                    $batch_class = 'expired';
                                } else if ($months_left < 12) {
                                    $batch_class = 'near-expiry';
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo $row['product_id']; ?></td>
                            <td><?php echo $row['product_name']; ?></td>
                            <td><?php echo $row['category']; ?></td>
                            <td><span class="status-badge <?php echo $stock_class; ?>"><?php echo $stock; ?></span></td>
                            <td>
                                <?php 
                                $all_batches_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM product_batches WHERE product_id = {$row['product_id']} AND quantity > 0");
                                $all_batches = mysqli_fetch_assoc($all_batches_query)['total'];
                                
                                if ($all_batches > 0): ?>
                                    <button class="view-batches-btn <?php echo $batch_class; ?>" onclick="viewBatches(<?php echo $row['product_id']; ?>, '<?php echo htmlspecialchars($row['product_name']); ?>')">
                                        <?php echo $all_batches . ' Batch' . ($all_batches > 1 ? 'es' : ''); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="no-batches-text">No Batches</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $expiry_display; ?></td>
                            <td>
                                <img src="assets/pencil.png" alt="Add Batch" class="edit-icon" onclick="openAddBatchModal(<?php echo $row['product_id']; ?>, '<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES); ?>')">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php $conn->close(); ?>
    
    <!-- View Batches Modal -->
    
    <!-- View Batches Modal -->
    <div class="modal-overlay" id="batchModalOverlay"></div>
    <div class="batch-modal" id="batchViewModal">
        <div class="modal-header">
            <span id="batchModalTitle">Product Batches</span>
            <button class="modal-close" onclick="closeBatchModal()">&times;</button>
        </div>
        <div class="batch-modal-content">
            <div class="batch-summary" id="batchSummaryContainer"></div>
            <div class="batch-list" id="batchList">
                <div class="loading-spinner">Loading batches...</div>
            </div>
        </div>
    </div>

    <!-- Add Batch Modal -->
    <div class="modal-overlay" id="addBatchModalOverlay"></div>
    <div class="add-batch-modal" id="addBatchModal">
        <div class="modal-header">
            Add New Batch
            <button class="modal-close" onclick="closeAddBatchModal()">&times;</button>
        </div>
        <form class="modal-form" method="POST" action="includes/add_batch.php">
            <input type="hidden" name="product_id" id="batch_product_id">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" id="batch_product_name" readonly>
            </div>
            <div class="form-group">
                <label>Product ID</label>
                <input type="text" id="batch_display_product_id" readonly>
            </div>
            <div class="form-group">
                <label>Batch Number</label>
                <input type="text" name="batch_number" id="batch_number" readonly>
            </div>
            <div class="form-group">
                <label>Batch Quantity</label>
                <input type="number" name="quantity" id="batch_quantity" required>
            </div>
            <div class="form-group">
                <label>Manufactured Date</label>
                <input type="date" name="manufactured_date" id="batch_manufactured" required>
            </div>
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" id="batch_expiry" required>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="modal-save">Save</button>
                <button type="button" class="modal-cancel" onclick="closeAddBatchModal()">Cancel</button>
            </div>
        </form>
    </div>

    <script>
    function viewBatches(productId, productName) {
        document.getElementById('batchModalTitle').textContent = productName;
        document.getElementById('batchViewModal').classList.add('show');
        document.getElementById('batchModalOverlay').classList.add('show');
        
        const batchList = document.getElementById('batchList');
        batchList.innerHTML = '<div class="loading-spinner">Loading batches...</div>';
        
        fetch('includes/get_batches.php?product_id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.success) displayBatches(data.batches, data.total_stock, data.active_count);
                else batchList.innerHTML = '<div class="no-batches">No batches found</div>';
            })
            .catch(error => batchList.innerHTML = '<div class="error-message">Error loading batches</div>');
    }

    function closeBatchModal() {
        document.getElementById('batchViewModal').classList.remove('show');
        document.getElementById('batchModalOverlay').classList.remove('show');
    }

    function displayBatches(batches, totalStock, activeCount) {
        let stockStatus = totalStock > 200 ? 'Good Stock' : (totalStock >= 50 ? 'Low in Stock' : 'Critical Stock');
        let stockClass = totalStock > 200 ? 'status-good' : (totalStock >= 50 ? 'status-low' : 'status-critical');
        
        document.getElementById('batchSummaryContainer').innerHTML = `
            <div class="batch-summary-item ${stockClass}">
                <span class="batch-summary-label ${stockClass}">${stockStatus}</span>
                <span class="batch-summary-value">${totalStock}</span>
            </div>
            <div class="batch-summary-item status-available">
                <span class="batch-summary-label status-available">Total Batches</span>
                <span class="batch-summary-value">${activeCount}</span>
            </div>`;
        
        const batchList = document.getElementById('batchList');
        if (batches.length === 0) {
            batchList.innerHTML = '<div class="no-batches">No batches available</div>';
            return;
        }
        
        // Find the closest-to-expiry FRESH batch for "In Use"
        let inUseBatchId = null;
        let closestDays = Infinity;
        
        batches.forEach((batch) => {
            const today = new Date();
            const expiryDate = new Date(batch.expiry_date);
            const diffTime = expiryDate - today;
            const daysLeft = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const monthsLeft = daysLeft / 30;
            
            // Only consider fresh batches for "In Use"
            if (daysLeft > 0 && monthsLeft >= 12 && daysLeft < closestDays) {
                closestDays = daysLeft;
                inUseBatchId = batch.batch_id;
            }
        });
        
        let html = '';
        batches.forEach((batch) => {
            const today = new Date();
            const expiryDate = new Date(batch.expiry_date);
            const diffTime = expiryDate - today;
            const daysLeft = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const monthsLeft = daysLeft / 30;
            
            const cardClass = daysLeft < 0 ? 'expired' : (monthsLeft < 12 ? 'near-expiry' : 'fresh');
            let batchStatus = 'Available';
            let statusClass = 'status-available';

            if (daysLeft < 0) {
                batchStatus = 'Expired';
                statusClass = 'status-expired';
            } else if (monthsLeft < 12) {
                batchStatus = 'Near Expiry';
                statusClass = 'status-near-expiry';
            } else if (batch.batch_id === inUseBatchId) {
                batchStatus = 'In Use';
                statusClass = 'status-in-use';
            }
            
            let disposeBtn = '';
            if (daysLeft < 0 || monthsLeft < 12) {
                disposeBtn = `<button class="dispose-btn" onclick="disposeBatch(${batch.batch_id}, '${batch.batch_number}')">
                    <img src="assets/bin.png" alt="Dispose">
                </button>`;
            }
            
            html += `
                <div class="batch-card ${cardClass}">
                    <div class="batch-card-header">
                        <span class="batch-number">${batch.batch_number}</span>
                        <div class="batch-status-container">
                            <span class="batch-status ${statusClass}">${batchStatus}</span>
                            ${disposeBtn}
                        </div>
                    </div>
                    <div class="batch-card-body">
                        <div class="batch-info-item">
                            <span class="batch-label">Quantity:</span>
                            <span class="batch-value">${batch.quantity} units</span>
                        </div>
                        <div class="batch-info-item">
                            <span class="batch-label">Expiry:</span>
                            <span class="batch-value">${new Date(batch.expiry_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</span>
                        </div>
                        <div class="batch-info-item">
                            <span class="batch-label">Days Left:</span>
                            <span class="batch-value">${daysLeft} days</span>
                        </div>
                    </div>
                </div>`;
        });
        batchList.innerHTML = html;
    }

    function disposeBatch(batchId, batchNumber) {
        if (confirm(`Are you sure you want to dispose of batch ${batchNumber}? This action cannot be undone.`)) {
            fetch('includes/dispose_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ batch_id: batchId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Batch disposed successfully');
                    closeBatchModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to dispose batch'));
                }
            })
            .catch(error => alert('Error disposing batch'));
        }
    }

    function openAddBatchModal(productId, productName) {
        document.getElementById('batch_quantity').value = '';
        document.getElementById('batch_manufactured').value = '';
        document.getElementById('batch_expiry').value = '';
        
        document.getElementById('batch_product_id').value = productId;
        document.getElementById('batch_product_name').value = productName;
        document.getElementById('batch_display_product_id').value = 'PRD-' + String(productId).padStart(3, '0');
        
        fetch('update_stock.php?ajax=get_batch_count&product_id=' + productId)
            .then(response => response.json())
            .then(data => {
                const nextBatch = (data.count || 0) + 1;
                const batchNumber = 'BTH-' + productId + String(nextBatch).padStart(3, '0');
                document.getElementById('batch_number').value = batchNumber;
            })
            .catch(() => {
                document.getElementById('batch_number').value = 'BTH-' + productId + '001';
            });
        
        document.getElementById('addBatchModal').classList.add('show');
        document.getElementById('addBatchModalOverlay').classList.add('show');
    }

    function closeAddBatchModal() {
        document.getElementById('addBatchModal').classList.remove('show');
        document.getElementById('addBatchModalOverlay').classList.remove('show');
        document.querySelector('#addBatchModal form').reset();
    }

    document.getElementById('batchModalOverlay').onclick = closeBatchModal;
    document.getElementById('addBatchModalOverlay').onclick = closeAddBatchModal;
    </script>
</body>
</html>