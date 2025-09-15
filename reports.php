<?php
require_once 'config.php';
requireLogin();

$message = '';
$messageType = '';

// Obtener parámetros de filtros
$report_type = $_GET['type'] ?? 'inventory';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$category_filter = $_GET['category'] ?? '';
$export = $_GET['export'] ?? '';

// Función para exportar CSV
function exportCSV($data, $filename, $headers) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escribir encabezados
    fputcsv($output, $headers, ';');
    
    // Escribir datos
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

// Procesar exportación
if ($export === 'csv') {
    switch ($report_type) {
        case 'inventory':
            $sql = "
                SELECT p.code, p.name, c.name as category, p.stock_current, p.stock_min, 
                       p.purchase_price, p.sale_price, (p.stock_current * p.sale_price) as value
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.status = 'active'
            ";
            
            if ($category_filter) {
                $sql .= " AND p.category_id = " . intval($category_filter);
            }
            
            $sql .= " ORDER BY p.name";
            
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_NUM);
            
            $headers = ['Código', 'Producto', 'Categoría', 'Stock Actual', 'Stock Mín.', 'Precio Compra', 'Precio Venta', 'Valor Total'];
            exportCSV($data, 'reporte_inventario_' . date('Y-m-d') . '.csv', $headers);
            break;
            
        case 'movements':
            $sql = "
                SELECT im.created_at, p.code, p.name, im.type, im.quantity, 
                       im.previous_stock, im.new_stock, im.reason, u.username
                FROM inventory_movements im
                JOIN products p ON im.product_id = p.id
                LEFT JOIN users u ON im.user_id = u.id
                WHERE DATE(im.created_at) BETWEEN ? AND ?
            ";
            
            $params = [$date_from, $date_to];
            
            if ($category_filter) {
                $sql .= " AND p.category_id = ?";
                $params[] = $category_filter;
            }
            
            $sql .= " ORDER BY im.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_NUM);
            
            $headers = ['Fecha', 'Código', 'Producto', 'Tipo', 'Cantidad', 'Stock Anterior', 'Stock Nuevo', 'Motivo', 'Usuario'];
            exportCSV($data, 'reporte_movimientos_' . date('Y-m-d') . '.csv', $headers);
            break;
            
        case 'low_stock':
            $sql = "
                SELECT p.code, p.name, c.name as category, p.stock_current, p.stock_min,
                       (p.stock_min - p.stock_current) as deficit
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.status = 'active' AND p.stock_current <= p.stock_min
            ";
            
            if ($category_filter) {
                $sql .= " AND p.category_id = " . intval($category_filter);
            }
            
            $sql .= " ORDER BY (p.stock_min - p.stock_current) DESC";
            
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_NUM);
            
            $headers = ['Código', 'Producto', 'Categoría', 'Stock Actual', 'Stock Mín.', 'Déficit'];
            exportCSV($data, 'reporte_stock_bajo_' . date('Y-m-d') . '.csv', $headers);
            break;
    }
}

// Obtener datos según el tipo de reporte
$reportData = [];
$reportTitle = '';
$reportColumns = [];

switch ($report_type) {
    case 'inventory':
        $reportTitle = 'Reporte de Inventario';
        $reportColumns = ['Código', 'Producto', 'Categoría', 'Stock Actual', 'Stock Mín.', 'Precio Compra', 'Precio Venta', 'Valor Total'];
        
        $sql = "
            SELECT p.*, c.name as category_name, (p.stock_current * p.sale_price) as total_value
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
        ";
        
        if ($category_filter) {
            $sql .= " AND p.category_id = " . intval($category_filter);
        }
        
        $sql .= " ORDER BY p.name";
        
        $reportData = $pdo->query($sql)->fetchAll();
        break;
        
    case 'movements':
        $reportTitle = 'Reporte de Movimientos';
        $reportColumns = ['Fecha', 'Producto', 'Tipo', 'Cantidad', 'Stock Anterior', 'Stock Nuevo', 'Motivo', 'Usuario'];
        
        $sql = "
            SELECT im.*, p.code, p.name as product_name, u.username
            FROM inventory_movements im
            JOIN products p ON im.product_id = p.id
            LEFT JOIN users u ON im.user_id = u.id
            WHERE DATE(im.created_at) BETWEEN ? AND ?
        ";
        
        $params = [$date_from, $date_to];
        
        if ($category_filter) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category_filter;
        }
        
        $sql .= " ORDER BY im.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;
        
    case 'low_stock':
        $reportTitle = 'Reporte de Stock Bajo';
        $reportColumns = ['Código', 'Producto', 'Categoría', 'Stock Actual', 'Stock Mín.', 'Déficit'];
        
        $sql = "
            SELECT p.*, c.name as category_name, (p.stock_min - p.stock_current) as deficit
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' AND p.stock_current <= p.stock_min
        ";
        
        if ($category_filter) {
            $sql .= " AND p.category_id = " . intval($category_filter);
        }
        
        $sql .= " ORDER BY (p.stock_min - p.stock_current) DESC";
        
        $reportData = $pdo->query($sql)->fetchAll();
        break;
        
    case 'value':
        $reportTitle = 'Reporte de Valorización';
        $reportColumns = ['Categoría', 'Productos', 'Stock Total', 'Valor Compra', 'Valor Venta', 'Margen'];
        
        $sql = "
            SELECT 
                COALESCE(c.name, 'Sin categoría') as category_name,
                COUNT(p.id) as product_count,
                SUM(p.stock_current) as total_stock,
                SUM(p.stock_current * p.purchase_price) as purchase_value,
                SUM(p.stock_current * p.sale_price) as sale_value,
                (SUM(p.stock_current * p.sale_price) - SUM(p.stock_current * p.purchase_price)) as margin
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
        ";
        
        if ($category_filter) {
            $sql .= " AND p.category_id = " . intval($category_filter);
        }
        
        $sql .= " GROUP BY c.id, c.name ORDER BY sale_value DESC";
        
        $reportData = $pdo->query($sql)->fetchAll();
        break;
}

// Obtener categorías para filtros
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Calcular estadísticas generales
$stats = [
    'total_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
    'low_stock_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_current <= stock_min")->fetchColumn(),
    'total_inventory_value' => $pdo->query("SELECT SUM(stock_current * sale_price) FROM products WHERE status = 'active'")->fetchColumn() ?: 0,
    'total_movements_today' => $pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE DATE(created_at) = CURDATE()")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, #6366f1 100%);
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            border: none;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
        }
        
        .report-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-radius: 8px;
            margin-right: 0.5rem;
        }
        
        .report-tabs .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header text-white">
            <h4 class="mb-0">Reportes</h4>
            <small class="opacity-75">Papelería</small>
        </div>
        
        <div class="sidebar-nav">
            <a href="index.php" class="nav-link">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="products.php" class="nav-link">
                <i class="bi bi-box-seam me-2"></i> Productos
            </a>
            <a href="categories.php" class="nav-link">
                <i class="bi bi-tags me-2"></i> Categorías
            </a>
            <a href="suppliers.php" class="nav-link">
                <i class="bi bi-truck me-2"></i> Proveedores
            </a>
            <a href="inventory.php" class="nav-link">
                <i class="bi bi-clipboard-data me-2"></i> Inventario
            </a>
            <a href="reports.php" class="nav-link active">
                <i class="bi bi-graph-up me-2"></i> Reportes
            </a>
            <?php if (hasPermission('admin')): ?>
            <a href="users.php" class="nav-link">
                <i class="bi bi-people me-2"></i> Usuarios
            </a>
            <?php endif; ?>
            <hr class="my-3" style="border-color: rgba(255, 255, 255, 0.1);">
            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
            </a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Reportes</h1>
                <p class="text-muted mb-0">Análisis y estadísticas del inventario</p>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-0">Total Productos</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total_products']); ?></h3>
                            </div>
                            <i class="bi bi-box-seam fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-0">Stock Bajo</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['low_stock_products']); ?></h3>
                            </div>
                            <i class="bi bi-exclamation-triangle fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-0">Valor Inventario</h6>
                                <h3 class="mb-0"><?php echo formatCurrency($stats['total_inventory_value']); ?></h3>
                            </div>
                            <i class="bi bi-currency-dollar fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-0">Movimientos Hoy</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total_movements_today']); ?></h3>
                            </div>
                            <i class="bi bi-arrow-left-right fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Report Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs report-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type === 'inventory' ? 'active' : ''; ?>" 
                           href="?type=inventory&category=<?php echo urlencode($category_filter); ?>">
                            <i class="bi bi-list-ul me-2"></i>Inventario
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type === 'movements' ? 'active' : ''; ?>" 
                           href="?type=movements&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&category=<?php echo urlencode($category_filter); ?>">
                            <i class="bi bi-arrow-left-right me-2"></i>Movimientos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type === 'low_stock' ? 'active' : ''; ?>" 
                           href="?type=low_stock&category=<?php echo urlencode($category_filter); ?>">
                            <i class="bi bi-exclamation-triangle me-2"></i>Stock Bajo
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $report_type === 'value' ? 'active' : ''; ?>" 
                           href="?type=value&category=<?php echo urlencode($category_filter); ?>">
                            <i class="bi bi-currency-dollar me-2"></i>Valorización
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <!-- Filters -->
                <form method="GET" class="row g-3 mb-4">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                    
                    <?php if ($report_type === 'movements'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Desde</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Hasta</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3">
                        <label class="form-label">Categoría</label>
                        <select class="form-select" name="category">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel me-2"></i>Filtrar
                                </button>
                                <button type="button" class="btn btn-success" onclick="exportReport()">
                                    <i class="bi bi-download me-2"></i>CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Report Content -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <?php foreach ($reportColumns as $column): ?>
                                <th><?php echo $column; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="<?php echo count($reportColumns); ?>" class="text-center py-4 text-muted">
                                    No se encontraron datos para mostrar
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <?php if ($report_type === 'inventory'): ?>
                                        <td><?php echo htmlspecialchars($row['code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'Sin categoría'); ?></td>
                                        <td class="text-center"><?php echo number_format($row['stock_current']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['stock_min']); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($row['purchase_price']); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($row['sale_price']); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($row['total_value']); ?></td>
                                    <?php elseif ($report_type === 'movements'): ?>
                                        <td><?php echo formatDate($row['created_at']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['product_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($row['code']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = match($row['type']) {
                                                'entry' => 'bg-success',
                                                'exit' => 'bg-danger',
                                                'adjustment' => 'bg-warning'
                                            };
                                            $typeText = match($row['type']) {
                                                'entry' => 'Entrada',
                                                'exit' => 'Salida',
                                                'adjustment' => 'Ajuste'
                                            };
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $typeText; ?></span>
                                        </td>
                                        <td class="text-center"><?php echo number_format($row['quantity']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['previous_stock']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['new_stock']); ?></td>
                                        <td><?php echo htmlspecialchars($row['reason'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['username'] ?? 'Sistema'); ?></td>
                                    <?php elseif ($report_type === 'low_stock'): ?>
                                        <td><?php echo htmlspecialchars($row['code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'Sin categoría'); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?php echo number_format($row['stock_current']); ?></span>
                                        </td>
                                        <td class="text-center"><?php echo number_format($row['stock_min']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark"><?php echo number_format($row['deficit']); ?></span>
                                        </td>
                                    <?php elseif ($report_type === 'value'): ?>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['product_count']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['total_stock']); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($row['purchase_value']); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($row['sale_value']); ?></td>
                                        <td class="text-end">
                                            <?php
                                            $margin = $row['margin'];
                                            $marginClass = $margin >= 0 ? 'text-success' : 'text-danger';
                                            ?>
                                            <span class="<?php echo $marginClass; ?>"><?php echo formatCurrency($margin); ?></span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportReport() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('export', 'csv');
            window.location.href = '?' + urlParams.toString();
        }
    </script>
</body>
</html>