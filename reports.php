<?php
require_once 'config.php';
$conn = getDBConnection();

// Date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Sales by Product Report
$sales_by_product_query = "
    SELECT 
        p.product_name,
        p.category,
        COUNT(s.sale_id) as transaction_count,
        SUM(s.quantity_sold) as total_quantity,
        SUM(s.total_amount) as total_revenue,
        AVG(s.total_amount) as avg_transaction,
        p.unit_price
    FROM products p
    LEFT JOIN sales s ON p.product_id = s.product_id
        AND s.sale_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY p.product_id
    HAVING total_quantity > 0
    ORDER BY total_revenue DESC
";
$sales_by_product = $conn->query($sales_by_product_query);

// Sales by Category Report
$sales_by_category_query = "
    SELECT 
        p.category,
        COUNT(s.sale_id) as transaction_count,
        SUM(s.quantity_sold) as total_quantity,
        SUM(s.total_amount) as total_revenue
    FROM products p
    LEFT JOIN sales s ON p.product_id = s.product_id
        AND s.sale_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY p.category
    HAVING total_quantity > 0
    ORDER BY total_revenue DESC
";
$sales_by_category = $conn->query($sales_by_category_query);

// Daily Sales Trend
$daily_sales_query = "
    SELECT 
        sale_date,
        COUNT(sale_id) as transactions,
        SUM(quantity_sold) as items_sold,
        SUM(total_amount) as revenue
    FROM sales
    WHERE sale_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY sale_date
    ORDER BY sale_date DESC
    LIMIT 30
";
$daily_sales = $conn->query($daily_sales_query);

// Summary Statistics
$summary_query = "
    SELECT 
        COUNT(DISTINCT sale_id) as total_transactions,
        SUM(quantity_sold) as total_items_sold,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_transaction
    FROM sales
    WHERE sale_date BETWEEN '$date_from' AND '$date_to'
";
$summary = $conn->query($summary_query)->fetch_assoc();

// Product Classification (Fast vs Slow Moving)
$classification_query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.category,
        COALESCE(SUM(s.quantity_sold), 0) as total_sold,
        COALESCE(COUNT(DISTINCT s.sale_date), 0) as sales_days,
        CASE 
            WHEN COALESCE(SUM(s.quantity_sold), 0) >= 50 THEN 'Fast-Moving'
            WHEN COALESCE(SUM(s.quantity_sold), 0) >= 20 THEN 'Medium-Moving'
            ELSE 'Slow-Moving'
        END as classification
    FROM products p
    LEFT JOIN sales s ON p.product_id = s.product_id
        AND s.sale_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY p.product_id
    ORDER BY total_sold DESC
";
$classification = $conn->query($classification_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ShopWise AI</title>
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
        
        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
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
        
        .form-group input {
            padding: 10px;
            border: 1px solid #dce1e6;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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
        
        .badge-fast {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-slow {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        @media print {
            .nav, .filter-box, .btn {
                display: none;
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
        <a href="index.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="sales.php">Record Sale</a>
        <a href="forecast.php">Forecast & Restock</a>
        <a href="reports.php" class="active">Reports</a>
    </div>
    
    <div class="container">
        <!-- Date Filter -->
        <div class="filter-box">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Generate Report</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Print Report</button>
            </form>
        </div>
        
        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Transactions</h3>
                <div class="value"><?php echo number_format($summary['total_transactions'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Items Sold</h3>
                <div class="value"><?php echo number_format($summary['total_items_sold'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value">₱<?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Avg Transaction</h3>
                <div class="value">₱<?php echo number_format($summary['avg_transaction'] ?? 0, 2); ?></div>
            </div>
        </div>
        
        <!-- Sales by Product -->
        <div class="card">
            <div class="card-header">
                <h2>📊 Sales by Product</h2>
            </div>
            <div class="card-body">
                <?php if ($sales_by_product->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Transactions</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                                <th>Avg Sale</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            while($row = $sales_by_product->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><strong><?php echo $rank++; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><?php echo number_format($row['transaction_count']); ?></td>
                                    <td><?php echo number_format($row['total_quantity']); ?></td>
                                    <td><strong>₱<?php echo number_format($row['total_revenue'], 2); ?></strong></td>
                                    <td>₱<?php echo number_format($row['avg_transaction'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No sales data for the selected period.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sales by Category -->
        <div class="card">
            <div class="card-header">
                <h2>📦 Sales by Category</h2>
            </div>
            <div class="card-body">
                <?php if ($sales_by_category->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Transactions</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_revenue = $summary['total_revenue'];
                            while($row = $sales_by_category->fetch_assoc()): 
                                $percentage = $total_revenue > 0 ? ($row['total_revenue'] / $total_revenue) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['category']); ?></strong></td>
                                    <td><?php echo number_format($row['transaction_count']); ?></td>
                                    <td><?php echo number_format($row['total_quantity']); ?></td>
                                    <td><strong>₱<?php echo number_format($row['total_revenue'], 2); ?></strong></td>
                                    <td><?php echo number_format($percentage, 1); ?>%</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No category data for the selected period.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Product Classification -->
        <div class="card">
            <div class="card-header">
                <h2>🏷️ Product Classification (Fast vs Slow Moving)</h2>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Qty Sold</th>
                            <th>Sales Days</th>
                            <th>Classification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $classification->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo number_format($row['total_sold']); ?></td>
                                <td><?php echo $row['sales_days']; ?> days</td>
                                <td>
                                    <?php 
                                    $badge_class = 'badge-medium';
                                    if ($row['classification'] == 'Fast-Moving') $badge_class = 'badge-fast';
                                    elseif ($row['classification'] == 'Slow-Moving') $badge_class = 'badge-slow';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $row['classification']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Daily Sales Trend -->
        <div class="card">
            <div class="card-header">
                <h2>📈 Daily Sales Trend (Last 30 Days)</h2>
            </div>
            <div class="card-body">
                <?php if ($daily_sales->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transactions</th>
                                <th>Items Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $daily_sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y (D)', strtotime($row['sale_date'])); ?></td>
                                    <td><?php echo number_format($row['transactions']); ?></td>
                                    <td><?php echo number_format($row['items_sold']); ?></td>
                                    <td><strong>₱<?php echo number_format($row['revenue'], 2); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No daily sales data available.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>