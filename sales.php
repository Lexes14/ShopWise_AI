<?php
require_once 'config.php';
$conn = getDBConnection();

// Handle sale recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_sale'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $sale_date = $_POST['sale_date'];

    // Use stored procedure to record sale
    $sale_query = "CALL sp_record_sale($product_id, $quantity, '$sale_date')";

    if ($conn->query($sale_query)) {
        $success_message = "Sale recorded successfully!";
        // Reconnect after stored procedure
        $conn->close();
        $conn = getDBConnection();
    } else {
        $error_message = "Error: " . $conn->error;
    }
}

// Get products for dropdown
$products_query = "SELECT product_id, product_name, category, unit_price, stock_quantity 
                   FROM products 
                   WHERE stock_quantity > 0
                   ORDER BY product_name";
$products_result = $conn->query($products_query);

// Get recent sales (last 20)
$recent_sales_query = "
    SELECT 
        s.sale_id,
        p.product_name,
        p.category,
        s.quantity_sold,
        s.unit_price,
        s.total_amount,
        s.sale_date,
        s.sale_time
    FROM sales s
    INNER JOIN products p ON s.product_id = p.product_id
    ORDER BY s.sale_date DESC, s.sale_time DESC
    LIMIT 20
";
$recent_sales_result = $conn->query($recent_sales_query);

// Get today's summary
$today_summary_query = "
    SELECT 
        COUNT(DISTINCT sale_id) as total_transactions,
        SUM(quantity_sold) as total_items,
        SUM(total_amount) as total_revenue
    FROM sales
    WHERE sale_date = CURDATE()
";
$today_summary = $conn->query($today_summary_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Sale - ShopWise AI</title>
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #27ae60;
        }

        .stat-card h3 {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h2 {
            font-size: 18px;
            color: #2c3e50;
        }

        .card-body {
            padding: 20px;
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

        .product-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            display: none;
        }

        .product-info.active {
            display: block;
        }

        .product-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .product-info-item {
            text-align: center;
        }

        .product-info-item label {
            display: block;
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-info-item .value {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-primary:disabled {
            background: #95a5a6;
            cursor: not-allowed;
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

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        #productSearch {
            width: 100%;
            padding: 10px;
            border: 1px solid #dce1e6;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🏪 ShopWise AI</h1>
        <p>Next-Generation Inventory Platform for Convenience Store Networks</p>

    </div>

    <div class="nav">
        <a href="index.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="sales.php" class="active">Record Sale</a>
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

        <!-- Today's Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Today's Transactions</h3>
                <div class="value"><?php echo number_format($today_summary['total_transactions'] ?? 0); ?></div>
            </div>

            <div class="stat-card">
                <h3>Items Sold Today</h3>
                <div class="value"><?php echo number_format($today_summary['total_items'] ?? 0); ?></div>
            </div>

            <div class="stat-card">
                <h3>Today's Revenue</h3>
                <div class="value">₱<?php echo number_format($today_summary['total_revenue'] ?? 0, 2); ?></div>
            </div>

           
        </div>

        <!-- Record Sale Form -->
        <div class="card">
            <div class="card-header">
                <h2>💰 Record New Sale</h2>
            </div>
            <div class="card-body">
                <form method="POST" id="saleForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Search Product</label>
                            <input
                                type="text"
                                id="productSearch"
                                placeholder="Type product name..."
                                onkeyup="filterProducts()">
                        </div>

                        <div class="form-group">
                            <label>Select Product *</label>

                            <select name="product_id" id="productSelect" required onchange="updateProductInfo()">
                                <option value="">-- Choose a product --</option>
                                <?php while ($row = $products_result->fetch_assoc()): ?>
                                    <option
                                        value="<?php echo $row['product_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($row['product_name']); ?>"
                                        data-price="<?php echo $row['unit_price']; ?>"
                                        data-stock="<?php echo $row['stock_quantity']; ?>">
                                        <?php echo htmlspecialchars($row['product_name']); ?>
                                        (Stock: <?php echo $row['stock_quantity']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Quantity *</label>
                            <input
                                type="number"
                                name="quantity"
                                id="quantityInput"
                                min="1"
                                required
                                onchange="updateTotal()">
                        </div>

                        <div class="form-group">
                            <label>Sale Date *</label>
                            <input
                                type="date"
                                name="sale_date"
                                value="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>
                    </div>

                    <div id="productInfo" class="product-info">
                        <div class="product-info-grid">
                            <div class="product-info-item">
                                <label>Unit Price</label>
                                <div class="value" id="displayPrice">₱0.00</div>
                            </div>
                            <div class="product-info-item">
                                <label>Available Stock</label>
                                <div class="value" id="displayStock">0</div>
                            </div>
                            <div class="product-info-item">
                                <label>Total Amount</label>
                                <div class="value" id="displayTotal">₱0.00</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="record_sale" class="btn btn-primary" id="submitBtn" disabled>
                        Record Sale
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="card">
            <div class="card-header">
                <h2>📜 Recent Sales</h2>
            </div>
            <div class="card-body">
                <?php if ($recent_sales_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_sales_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($row['sale_date'])); ?><br>
                                        <small style="color: #7f8c8d;"><?php echo date('h:i A', strtotime($row['sale_time'])); ?></small>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><?php echo $row['quantity_sold']; ?></td>
                                    <td>₱<?php echo number_format($row['unit_price'], 2); ?></td>
                                    <td><strong>₱<?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        No sales recorded yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateProductInfo() {
            const select = document.getElementById('productSelect');
            const option = select.options[select.selectedIndex];
            const productInfo = document.getElementById('productInfo');
            const submitBtn = document.getElementById('submitBtn');
            const quantityInput = document.getElementById('quantityInput');

            if (option.value) {
                const price = parseFloat(option.dataset.price);
                const stock = parseInt(option.dataset.stock);

                document.getElementById('displayPrice').textContent = '₱' + price.toFixed(2);
                document.getElementById('displayStock').textContent = stock;

                quantityInput.max = stock;
                quantityInput.value = 1;

                productInfo.classList.add('active');
                submitBtn.disabled = false;

                updateTotal();
            } else {
                productInfo.classList.remove('active');
                submitBtn.disabled = true;
                quantityInput.value = '';
            }
        }

        function updateTotal() {
            const select = document.getElementById('productSelect');
            const option = select.options[select.selectedIndex];
            const quantityInput = document.getElementById('quantityInput');

            if (option.value && quantityInput.value) {
                const price = parseFloat(option.dataset.price);
                const quantity = parseInt(quantityInput.value);
                const stock = parseInt(option.dataset.stock);

                if (quantity > stock) {
                    alert('Quantity cannot exceed available stock (' + stock + ')');
                    quantityInput.value = stock;
                    return;
                }

                const total = price * quantity;
                document.getElementById('displayTotal').textContent = '₱' + total.toFixed(2);
            }
        }

        function filterProducts() {
            const input = document.getElementById("productSearch");
            const filter = input.value.toLowerCase();
            const select = document.getElementById("productSelect");
            const options = select.options;

            for (let i = 0; i < options.length; i++) {
                const text = options[i].text.toLowerCase();

                if (text.includes(filter) || options[i].value === "") {
                    options[i].style.display = "";
                } else {
                    options[i].style.display = "none";
                }
            }
        }
    </script>
</body>

</html>

<?php $conn->close(); ?>