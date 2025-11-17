<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
date_default_timezone_set('Asia/Manila');
$current_date = date('l, F j, Y');
$current_time = date('h:i A');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bethel Pharmacy - Medicine Management</title>
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
            <a href="medicine_management.php" class="nav-button active"><img src="assets/management.png" alt="">Medicine Management</a>
            <a href="reports.php" class="nav-button"><img src="assets/reports.png" alt="">Reports</a>
            <a href="staff_management.php" class="nav-button"><img src="assets/staff.png" alt="">Staff Management</a>
            <a href="notifications.php" class="nav-button"><img src="assets/notifs.png" alt="">Notifications</a>
            <a href="settings.php" class="nav-button"><img src="assets/settings.png" alt="">Settings</a>
            <a href="help.php" class="nav-button"><img src="assets/help.png" alt="">Get Technical Help</a>
        </div>
        <div class="sidebar-credits">Made by ACT-2C G5 © 2025-2026</div>
    </nav>
    
    <div class="content">
        <h2>Medicine Management</h2>
        <button class="add-product-btn">+ Add Product</button>
        
        <div class="management-panel">
            <div class="panel-controls">
                <form method="GET" class="search-form">
                    <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" class="search-input" placeholder="Search products...">
                    <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer;">
                        <img src="assets/search.png" alt="Search" class="search-icon">
                    </button>
                    <input type="hidden" name="category" value="<?php echo isset($_GET['category']) ? htmlspecialchars($_GET['category']) : ''; ?>">
                    <input type="hidden" name="stock_level" value="<?php echo isset($_GET['stock_level']) ? htmlspecialchars($_GET['stock_level']) : ''; ?>">
                    <input type="hidden" name="expiry_status" value="<?php echo isset($_GET['expiry_status']) ? htmlspecialchars($_GET['expiry_status']) : ''; ?>">
                </form>

                <form method="GET" class="filter-form">
                    <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <select name="category" class="filter-dropdown">
                        <option value="">All Categories</option>
                        <option value="Prescription Medicines" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Prescription Medicines') ? 'selected' : ''; ?>>Prescription Medicines</option>
                        <option value="Over-the-Counter (OTC) Products" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Over-the-Counter (OTC) Products') ? 'selected' : ''; ?>>OTC Products</option>
                        <option value="Health & Personal Care" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Health & Personal Care') ? 'selected' : ''; ?>>Health & Personal Care</option>
                        <option value="Medical Supplies & Equipment" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Medical Supplies & Equipment') ? 'selected' : ''; ?>>Medical Supplies</option>
                    </select>
                    <select name="stock_level" class="filter-dropdown">
                        <option value="">All Stock Levels</option>
                        <option value="good" <?php echo (isset($_GET['stock_level']) && $_GET['stock_level'] == 'good') ? 'selected' : ''; ?>>Good Stock</option>
                        <option value="low" <?php echo (isset($_GET['stock_level']) && $_GET['stock_level'] == 'low') ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out" <?php echo (isset($_GET['stock_level']) && $_GET['stock_level'] == 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                    <select name="expiry_status" class="filter-dropdown">
                        <option value="">All Expiry Status</option>
                        <option value="fresh" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'fresh') ? 'selected' : ''; ?>>Fresh (>6 months)</option>
                        <option value="expiring" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'expiring') ? 'selected' : ''; ?>>Expiring Soon (≤6 months)</option>
                        <option value="expired" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'expired') ? 'selected' : ''; ?>>Expired</option>
                    </select>
                    <button type="submit" class="apply-filter-btn">Apply Filters</button>
                    <a href="medicine_management.php" class="reset-filter-btn">Reset Filters</a>
                </form>
            </div>
            
            <div class="products-table-container">
                <?php
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
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Batches</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($filtered_results as $row): 
                            $stock = $row['total_stock'];
                            $stock_class = 'status-red'; // Default out of stock
                            if ($stock > $row['reorder_level']) {
                                $stock_class = 'status-green'; // Good stock
                            } else if ($stock > 0) {
                                $stock_class = 'status-orange'; // Low stock
                            }
                            $status_html = $row['status'] == 'expired' ? '<span class="status-badge status-red">Expired</span>' : 
                                         ($row['status'] == 'expiring' ? '<span class="status-badge status-orange">Expiring Soon</span>' : 
                                         '<span class="status-badge status-green">Fresh</span>');
                            
                            $batch_count = $row['batch_count'];
                            $batch_class = 'fresh';

                            // Check the earliest expiry across ALL batches
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
                            <td>₱ <?php echo number_format($row['price'], 2); ?></td>
                            <td><?php echo $status_html; ?></td>
                            <td>
                                <?php 
                                // Get total batch count (including near-expiry/expired)
                                $all_batches_query = $conn->query("SELECT COUNT(*) as total FROM product_batches WHERE product_id = {$row['product_id']} AND quantity > 0");
                                $all_batches = $all_batches_query->fetch_assoc()['total'];
                                
                                if ($all_batches > 0): ?>
                                    <button class="view-batches-btn <?php echo $batch_class; ?>" onclick="viewBatches(<?php echo $row['product_id']; ?>, '<?php echo htmlspecialchars($row['product_name']); ?>')">
                                        <?php echo $all_batches . ' Batch' . ($all_batches > 1 ? 'es' : ''); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="no-batches-text">No Batches</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <img src="assets/pencil.png" alt="Edit" class="edit-icon" onclick="editProduct(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                <a href="includes/delete_product.php?id=<?php echo $row['product_id']; ?>" onclick="return confirm('Are you sure you want to delete this product?')">
                                    <img src="assets/bin.png" alt="Delete" class="delete-icon">
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php $conn->close(); ?>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Product Modal -->
    <div class="modal-overlay" id="modalOverlay"></div>
    <div class="add-product-modal" id="productModal">
        <div class="modal-header">
            <span id="modalTitle">Add New Product</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="productForm" class="modal-form">
            <input type="hidden" name="product_id" id="productId">
            <div class="form-group">
                <label>PRODUCT NAME</label>
                <input type="text" name="product_name" id="productName" required>
            </div>
            <div class="form-group">
                <label>PRODUCT ID</label>
                <input type="text" id="displayProductId" readonly>
            </div>
            <div class="form-group">
                <label>CATEGORY</label>
                <select name="category" id="productCategory" required>
                    <option value="">Select Category</option>
                    <option value="Prescription Medicines">Prescription Medicines</option>
                    <option value="Over-the-Counter (OTC) Products">Over-the-Counter (OTC) Products</option>
                    <option value="Health & Personal Care">Health & Personal Care</option>
                    <option value="Medical Supplies & Equipment">Medical Supplies & Equipment</option>
                </select>
            </div>
            <div class="form-group">
                <label>PRICE (₱)</label>
                <input type="number" step="0.01" name="price" id="productPrice" required>
            </div>
            <div class="form-group">
                <label>MANUFACTURED DATE</label>
                <input type="date" name="manufactured_date" id="productManufactured" required>
            </div>
            <div class="form-group">
                <label>EXPIRATION DATE</label>
                <input type="date" name="expiry_date" id="productExpiry" required>
            </div>
            <div class="form-group full-width">
                <label>How to Use</label>
                <textarea name="how_to_use" id="productHowToUse"></textarea>
            </div>
            <div class="form-group full-width">
                <label>Side Effects</label>
                <textarea name="side_effects" id="productSideEffects"></textarea>
            </div>
            <div class="modal-buttons">
                <button type="button" class="modal-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="modal-save">Save Details</button>
            </div>
        </form>
    </div>

    <!-- View Batches Modal -->
    <div class="modal-overlay" id="batchModalOverlay"></div>
    <div class="batch-modal" id="batchModal">
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

    <script>
    function closeModal() {
        document.getElementById('productModal').classList.remove('show');
        document.getElementById('modalOverlay').classList.remove('show');
        document.getElementById('productForm').reset();
        document.getElementById('productForm').action = 'includes/add_product.php';
        document.getElementById('modalTitle').textContent = 'Add New Product';
    }

    document.querySelector('.add-product-btn').onclick = function() {
        document.getElementById('productForm').action = 'includes/add_product.php';
        document.getElementById('modalTitle').textContent = 'Add New Product';
        document.getElementById('displayProductId').value = 'PRD-' + String(Math.floor(Math.random() * 900) + 100).padStart(3, '0');
        document.getElementById('productModal').classList.add('show');
        document.getElementById('modalOverlay').classList.add('show');
    };

    function editProduct(product) {
        document.getElementById('productForm').action = 'includes/update_product.php';
        document.getElementById('modalTitle').textContent = 'Edit Product';
        document.getElementById('productId').value = product.product_id;
        document.getElementById('displayProductId').value = 'PRD-' + String(product.product_id).padStart(3, '0');
        document.getElementById('productName').value = product.product_name;
        document.getElementById('productCategory').value = product.category;
        document.getElementById('productPrice').value = product.price;
        document.getElementById('productManufactured').value = product.manufactured_date;
        document.getElementById('productExpiry').value = product.expiry_date;
        document.getElementById('productHowToUse').value = product.how_to_use || '';
        document.getElementById('productSideEffects').value = product.side_effects || '';
        document.getElementById('productModal').classList.add('show');
        document.getElementById('modalOverlay').classList.add('show');
    }

    function viewBatches(productId, productName) {
        document.getElementById('batchModalTitle').textContent = productName;
        document.getElementById('batchModal').classList.add('show');
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
        document.getElementById('batchModal').classList.remove('show');
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

    document.getElementById('modalOverlay').onclick = closeModal;
    document.getElementById('batchModalOverlay').onclick = closeBatchModal;
    </script>
</body>
</html>