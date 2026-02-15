<?php
require_once 'config.php';
$conn = getDBConnection();

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = $conn->real_escape_string($_POST['product_name']);
    $category = $conn->real_escape_string($_POST['category']);
    $price = floatval($_POST['unit_price']);
    $stock = intval($_POST['stock_quantity']);
    $min_stock = intval($_POST['min_stock_level']);
    $max_stock = intval($_POST['max_stock_level']);
    
    $insert_query = "INSERT INTO products (product_name, category, unit_price, stock_quantity, min_stock_level, max_stock_level) 
                     VALUES ('$name', '$category', $price, $stock, $min_stock, $max_stock)";
    
    if ($conn->query($insert_query)) {
        $success_message = "Product added successfully!";
    } else {
        $error_message = "Error: " . $conn->error;
    }
}

// Handle product restock
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restock_product'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
    $restock_query = "CALL sp_restock_product($product_id, $quantity, '$notes')";
    
    if ($conn->query($restock_query)) {
        $success_message = "Product restocked successfully!";
    } else {
        $error_message = "Error: " . $conn->error;
    }
}

// Get all products
$products_query = "SELECT * FROM products ORDER BY category, product_name";
$products_result = $conn->query($products_query);

// Get categories for dropdown
$categories_query = "SELECT DISTINCT category FROM products ORDER BY category";
$categories_result = $conn->query($categories_query);
$categories = [];
while($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - ShopWise AI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .nav {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .nav a {
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .nav a:hover {
            background: #667eea;
            color: white;
        }
        
        .nav a.active {
            background: #667eea;
            color: white;
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 18px;
            color: #2c3e50;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #dce1e6;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            font-size: 13px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            font-size: 20px;
            color: #2c3e50;
        }
        
        .modal-footer {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏪 ShopWise AI</h1>
        <p>Intelligent Inventory Management for Sari-Sari Stores</p>
    </div>
    
    <div class="nav">
        <a href="index.php">Dashboard</a>
        <a href="products.php" class="active">Products</a>
        <a href="sales.php">Record Sale</a>
        <a href="forecast.php">Forecast & Restock</a> 
     <a href="reports.php">Reports</a> 
    </div>
    
    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Add Product Form -->
        <div class="card">
            <div class="card-header">
                <h2>➕ Add New Product</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Product Name *</label>
                            <input type="text" name="product_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Category *</label>
                            <input type="text" name="category" list="category-list" required>
                            <datalist id="category-list">
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label>Unit Price (₱) *</label>
                            <input type="number" step="0.01" name="unit_price" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Initial Stock *</label>
                            <input type="number" name="stock_quantity" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Minimum Stock Level *</label>
                            <input type="number" name="min_stock_level" value="10" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Maximum Stock Level *</label>
                            <input type="number" name="max_stock_level" value="100" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </form>
            </div>
        </div>
        
        <!-- Products List -->
        <div class="card">
            <div class="card-header">
                <h2>📦 All Products</h2>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Min/Max</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $products_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['product_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td>₱<?php echo number_format($row['unit_price'], 2); ?></td>
                                <td><strong><?php echo $row['stock_quantity']; ?></strong></td>
                                <td><?php echo $row['min_stock_level']; ?> / <?php echo $row['max_stock_level']; ?></td>
                                <td>
                                    <?php if ($row['stock_quantity'] == 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php elseif ($row['stock_quantity'] <= $row['min_stock_level']): ?>
                                        <span class="badge badge-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-success" onclick="openRestockModal(<?php echo $row['product_id']; ?>, '<?php echo htmlspecialchars($row['product_name']); ?>')">
                                        Restock
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Restock Modal -->
    <div id="restockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Restock Product</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="product_id" id="restock_product_id">
                
                <div class="form-group">
                    <label>Product</label>
                    <input type="text" id="restock_product_name" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label>Quantity to Add *</label>
                    <input type="number" name="quantity" min="1" required>
                </div>
                
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <input type="text" name="notes" placeholder="e.g., Supplier: Juan Store">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRestockModal()">Cancel</button>
                    <button type="submit" name="restock_product" class="btn btn-success">Restock</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openRestockModal(productId, productName) {
            document.getElementById('restock_product_id').value = productId;
            document.getElementById('restock_product_name').value = productName;
            document.getElementById('restockModal').classList.add('active');
        }
        
        function closeRestockModal() {
            document.getElementById('restockModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('restockModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRestockModal();
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>