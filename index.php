<?php
require_once 'config.php';
$conn = getDBConnection();

// Get dashboard statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level) as low_stock_count,
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE sale_date = CURDATE()) as today_sales,
        (SELECT COALESCE(SUM(quantity_sold), 0) FROM sales WHERE sale_date = CURDATE()) as today_items_sold
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get low stock products
$low_stock_query = "SELECT * FROM vw_low_stock LIMIT 10";
$low_stock_result = $conn->query($low_stock_query);

// Get top selling products (last 7 days)
$top_products_query = "
    SELECT 
        p.product_name,
        p.category,
        SUM(s.quantity_sold) as total_sold,
        SUM(s.total_amount) as revenue
    FROM products p
    INNER JOIN sales s ON p.product_id = s.product_id
    WHERE s.sale_date >= CURDATE() - INTERVAL 7 DAY
    GROUP BY p.product_id
    ORDER BY total_sold DESC
    LIMIT 5
";
$top_products_result = $conn->query($top_products_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopWise AI - Dashboard</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        
        .stat-card.warning {
            border-left-color: #f39c12;
        }
        
        .stat-card.success {
            border-left-color: #27ae60;
        }
        
        .stat-card.info {
            border-left-color: #3498db;
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
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
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
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏪 ShopWise AI</h1>
        <p>Intelligent Inventory Management for Sari-Sari Stores</p>
    </div>
    
    <div class="nav">
        <a href="index.php" class="active">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="sales.php">Record Sale</a>
        <a href="forecast.php">Forecast & Restock</a> 
        <a href="reports.php">Reports</a>
    </div>
    
    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card info">
                <h3>Total Products</h3>
                <div class="value"><?php echo number_format($stats['total_products']); ?></div>
            </div>
            
            <div class="stat-card warning">
                <h3>Low Stock Alerts</h3>
                <div class="value"><?php echo number_format($stats['low_stock_count']); ?></div>
            </div>
            
            <div class="stat-card success">
                <h3>Today's Sales</h3>
                <div class="value">₱<?php echo number_format($stats['today_sales'], 2); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Items Sold Today</h3>
                <div class="value"><?php echo number_format($stats['today_items_sold']); ?></div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Low Stock Products -->
            <div class="card">
                <div class="card-header">
                    <h2>⚠️ Low Stock Alert</h2>
                </div>
                <div class="card-body">
                    <?php if ($low_stock_result->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $low_stock_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['product_name']); ?></strong><br>
                                            <small style="color: #7f8c8d;"><?php echo htmlspecialchars($row['category']); ?></small>
                                        </td>
                                        <td><?php echo $row['stock_quantity']; ?></td>
                                        <td>
                                            <?php if ($row['stock_quantity'] == 0): ?>
                                                <span class="badge badge-danger">Out of Stock</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Low Stock</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            ✅ All products have sufficient stock!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Selling Products -->
            <div class="card">
                <div class="card-header">
                    <h2>🔥 Top Sellers (Last 7 Days)</h2>
                </div>
                <div class="card-body">
                    <?php if ($top_products_result->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $top_products_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['product_name']); ?></strong><br>
                                            <small style="color: #7f8c8d;"><?php echo htmlspecialchars($row['category']); ?></small>
                                        </td>
                                        <td><?php echo number_format($row['total_sold']); ?></td>
                                        <td>₱<?php echo number_format($row['revenue'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            No sales data available for the last 7 days.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>