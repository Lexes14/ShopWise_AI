<?php
require_once 'config.php';
$conn = getDBConnection();

// Forecasting logic
function calculateForecast($product_id, $conn) {
    // Get sales data for last 30 days
    $sales_query = "
        SELECT 
            sale_date,
            SUM(quantity_sold) as daily_quantity
        FROM sales
        WHERE product_id = $product_id
        AND sale_date >= CURDATE() - INTERVAL 30 DAY
        GROUP BY sale_date
        ORDER BY sale_date ASC
    ";
    
    $sales_result = $conn->query($sales_query);
    $sales_data = [];
    
    while($row = $sales_result->fetch_assoc()) {
        $sales_data[] = intval($row['daily_quantity']);
    }
    
    if (empty($sales_data)) {
        return [
            'forecast' => 0,
            'confidence' => 'No Data',
            'trend' => 'Unknown',
            'explanation' => 'No sales data available for the last 30 days.'
        ];
    }
    
    // Calculate average daily sales
    $avg_daily_sales = array_sum($sales_data) / count($sales_data);
    
    // Calculate trend (comparing first half vs second half)
    $half = floor(count($sales_data) / 2);
    $first_half_avg = $half > 0 ? array_sum(array_slice($sales_data, 0, $half)) / $half : 0;
    $second_half_avg = $half > 0 ? array_sum(array_slice($sales_data, $half)) / (count($sales_data) - $half) : 0;
    
    // Determine trend
    if ($second_half_avg > $first_half_avg * 1.1) {
        $trend = 'Increasing';
        $trend_multiplier = 1.2; // 20% increase for growing demand
    } elseif ($second_half_avg < $first_half_avg * 0.9) {
        $trend = 'Decreasing';
        $trend_multiplier = 0.8; // 20% decrease for declining demand
    } else {
        $trend = 'Stable';
        $trend_multiplier = 1.0;
    }
    
    // Calculate 7-day forecast with trend adjustment
    $forecast_7days = ceil($avg_daily_sales * 7 * $trend_multiplier);
    
    // Confidence level based on data consistency
    $std_dev = 0;
    foreach($sales_data as $value) {
        $std_dev += pow($value - $avg_daily_sales, 2);
    }
    $std_dev = sqrt($std_dev / count($sales_data));
    $cv = $avg_daily_sales > 0 ? ($std_dev / $avg_daily_sales) : 0;
    
    if ($cv < 0.3) {
        $confidence = 'High';
    } elseif ($cv < 0.6) {
        $confidence = 'Medium';
    } else {
        $confidence = 'Low';
    }
    
    // Generate explanation
    $explanation = sprintf(
        "Based on %d days of sales data: Average daily sales = %.1f units. " .
        "Sales trend is %s (first half avg: %.1f, second half avg: %.1f). " .
        "Recommended 7-day restock quantity adjusted for trend.",
        count($sales_data),
        $avg_daily_sales,
        strtolower($trend),
        $first_half_avg,
        $second_half_avg
    );
    
    return [
        'forecast' => $forecast_7days,
        'confidence' => $confidence,
        'trend' => $trend,
        'explanation' => $explanation,
        'avg_daily_sales' => round($avg_daily_sales, 1)
    ];
}

// Get all products with forecast
$products_query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.category,
        p.stock_quantity,
        p.min_stock_level,
        p.unit_price,
        COALESCE(SUM(s.quantity_sold), 0) as total_sold_30days
    FROM products p
    LEFT JOIN sales s ON p.product_id = s.product_id 
        AND s.sale_date >= CURDATE() - INTERVAL 30 DAY
    GROUP BY p.product_id
    ORDER BY total_sold_30days DESC
";

$products_result = $conn->query($products_query);
$forecast_data = [];

while($row = $products_result->fetch_assoc()) {
    $forecast = calculateForecast($row['product_id'], $conn);
    
    $row['forecast'] = $forecast['forecast'];
    $row['confidence'] = $forecast['confidence'];
    $row['trend'] = $forecast['trend'];
    $row['explanation'] = $forecast['explanation'];
    $row['avg_daily_sales'] = $forecast['avg_daily_sales'] ?? 0;
    
    // Determine if restock is needed
    $needed = max(0, $forecast['forecast'] - $row['stock_quantity']);
    $row['restock_needed'] = $needed;
    
    // Calculate investment
    $row['investment'] = $needed * $row['unit_price'];
    
    // Priority (High if stock below min level OR restock needed > 10)
    if ($row['stock_quantity'] <= $row['min_stock_level'] || $needed > 10) {
        $row['priority'] = 'High';
    } elseif ($needed > 0) {
        $row['priority'] = 'Medium';
    } else {
        $row['priority'] = 'Low';
    }
    
    $forecast_data[] = $row;
}

// Separate into categories
$high_priority = array_filter($forecast_data, fn($p) => $p['priority'] == 'High');
$medium_priority = array_filter($forecast_data, fn($p) => $p['priority'] == 'Medium');
$low_priority = array_filter($forecast_data, fn($p) => $p['priority'] == 'Low');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecast & Restock - ShopWise AI</title>
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
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        
        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-box p {
            color: #0d47a1;
            line-height: 1.6;
            font-size: 14px;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
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
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-low {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-increasing {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-decreasing {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-stable {
            background: #e3f2fd;
            color: #0d47a1;
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
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .explanation {
            font-size: 12px;
            color: #7f8c8d;
            font-style: italic;
            margin-top: 5px;
        }
        
        .summary-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .summary-box h4 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-item:last-child {
            border-bottom: none;
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
        <a href="sales.php">Record Sale</a>
        <a href="forecast.php" class="active">Forecast & Restock</a>
        <a href="reports.php">Reports</a>
    </div>
    
    <div class="container">
        <div class="info-box">
            <h3>📊 How Forecasting Works</h3>
            <p>
                <strong>Simple & Transparent Logic:</strong> We analyze your sales from the last 30 days to predict 
                what you'll need for the next 7 days. We look at your average daily sales and check if sales are 
                increasing, decreasing, or stable. If sales are growing, we recommend more stock. If declining, we're 
                more conservative. The forecast is automatically adjusted based on this trend.
            </p>
        </div>
        
        <!-- High Priority Restocks -->
        <?php if (count($high_priority) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2>🚨 HIGH PRIORITY - Restock Immediately</h2>
                    <span class="badge badge-high"><?php echo count($high_priority); ?> Products</span>
                </div>
                <div class="card-body">
                    <?php
                    $total_investment = array_sum(array_column($high_priority, 'investment'));
                    ?>
                    <div class="summary-box">
                        <h4>Investment Required</h4>
                        <div class="summary-item">
                            <span>Total Items to Restock:</span>
                            <strong><?php echo number_format(array_sum(array_column($high_priority, 'restock_needed'))); ?> units</strong>
                        </div>
                        <div class="summary-item">
                            <span>Total Investment:</span>
                            <strong>₱<?php echo number_format($total_investment, 2); ?></strong>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Avg Daily Sales</th>
                                <th>Trend</th>
                                <th>7-Day Forecast</th>
                                <th>Restock Qty</th>
                                <th>Investment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($high_priority as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['product_name']); ?></strong><br>
                                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars($row['category']); ?></small>
                                        <div class="explanation"><?php echo $row['explanation']; ?></div>
                                    </td>
                                    <td>
                                        <strong><?php echo $row['stock_quantity']; ?></strong>
                                        <?php if ($row['stock_quantity'] <= $row['min_stock_level']): ?>
                                            <span class="badge badge-danger">Low</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['avg_daily_sales']; ?> units</td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['trend']); ?>">
                                            <?php echo $row['trend']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $row['forecast']; ?></strong> units</td>
                                    <td>
                                        <strong style="color: #e74c3c; font-size: 16px;">
                                            <?php echo $row['restock_needed']; ?>
                                        </strong>
                                    </td>
                                    <td><strong>₱<?php echo number_format($row['investment'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Medium Priority Restocks -->
        <?php if (count($medium_priority) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2>⚠️ MEDIUM PRIORITY - Plan Restock Soon</h2>
                    <span class="badge badge-medium"><?php echo count($medium_priority); ?> Products</span>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Avg Daily Sales</th>
                                <th>Trend</th>
                                <th>7-Day Forecast</th>
                                <th>Restock Qty</th>
                                <th>Investment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($medium_priority as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['product_name']); ?></strong><br>
                                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars($row['category']); ?></small>
                                        <div class="explanation"><?php echo $row['explanation']; ?></div>
                                    </td>
                                    <td><strong><?php echo $row['stock_quantity']; ?></strong></td>
                                    <td><?php echo $row['avg_daily_sales']; ?> units</td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['trend']); ?>">
                                            <?php echo $row['trend']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $row['forecast']; ?></strong> units</td>
                                    <td>
                                        <strong style="color: #f39c12; font-size: 16px;">
                                            <?php echo $row['restock_needed']; ?>
                                        </strong>
                                    </td>
                                    <td><strong>₱<?php echo number_format($row['investment'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Low Priority / Well Stocked -->
        <?php if (count($low_priority) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2>✅ WELL STOCKED - No Action Needed</h2>
                    <span class="badge badge-low"><?php echo count($low_priority); ?> Products</span>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Avg Daily Sales</th>
                                <th>Trend</th>
                                <th>7-Day Forecast</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($low_priority as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['product_name']); ?></strong><br>
                                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars($row['category']); ?></small>
                                    </td>
                                    <td><strong><?php echo $row['stock_quantity']; ?></strong></td>
                                    <td><?php echo $row['avg_daily_sales']; ?> units</td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['trend']); ?>">
                                            <?php echo $row['trend']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $row['forecast']; ?></strong> units</td>
                                    <td>
                                        <span class="badge badge-success">Sufficient Stock</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php $conn->close(); ?>