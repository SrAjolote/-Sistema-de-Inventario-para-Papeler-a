<?php
require_once 'config.php';
requireLogin();

// Obtener estadísticas del dashboard
try {
    // Total de productos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
    $totalProducts = $stmt->fetch()['total'];
    
    // Productos con stock bajo
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock_current <= stock_min AND status = 'active'");
    $lowStockProducts = $stmt->fetch()['total'];
    
    // Total de categorías
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $totalCategories = $stmt->fetch()['total'];
    
    // Total de proveedores
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM suppliers");
    $totalSuppliers = $stmt->fetch()['total'];
    
    // Valor total del inventario
    $stmt = $pdo->query("SELECT SUM(stock_current * sale_price) as total FROM products WHERE status = 'active'");
    $inventoryValue = $stmt->fetch()['total'] ?? 0;
    
    // Productos más vendidos (basado en movimientos de salida)
    $stmt = $pdo->query("
        SELECT p.name, p.code, SUM(im.quantity) as total_sold
        FROM products p
        JOIN inventory_movements im ON p.id = im.product_id
        WHERE im.type = 'exit' AND im.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $topProducts = $stmt->fetchAll();
    
    // Movimientos recientes
    $stmt = $pdo->query("
        SELECT im.*, p.name as product_name, p.code as product_code, u.username
        FROM inventory_movements im
        JOIN products p ON im.product_id = p.id
        LEFT JOIN users u ON im.user_id = u.id
        ORDER BY im.created_at DESC
        LIMIT 10
    ");
    $recentMovements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error al cargar estadísticas: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --danger-color: #ef4444;
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
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        
        .badge-movement {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
            <h4 class="mb-0">Inventario</h4>
            <small class="opacity-75">Papelería</small>
        </div>
        
        <div class="sidebar-nav">
            <a href="index.php" class="nav-link active">
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
            <a href="reports.php" class="nav-link">
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
                <h1 class="h3 mb-0">Dashboard</h1>
                <p class="text-muted mb-0">Bienvenido, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
            <button class="btn btn-primary d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <i class="bi bi-list"></i>
            </button>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($totalProducts); ?></h3>
                            <p class="text-muted mb-0">Total Productos</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($lowStockProducts); ?></h3>
                            <p class="text-muted mb-0">Stock Bajo</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-tags"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($totalCategories); ?></h3>
                            <p class="text-muted mb-0">Categorías</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo formatCurrency($inventoryValue); ?></h3>
                            <p class="text-muted mb-0">Valor Inventario</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Tables -->
        <div class="row g-4">
            <!-- Top Products -->
            <div class="col-lg-6">
                <div class="table-card">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h5 class="card-title mb-0">Productos Más Vendidos (30 días)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topProducts)): ?>
                            <p class="text-muted text-center py-3">No hay datos de ventas disponibles</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Código</th>
                                            <th class="text-end">Vendido</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topProducts as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($product['code']); ?></code></td>
                                            <td class="text-end"><?php echo number_format($product['total_sold']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Movements -->
            <div class="col-lg-6">
                <div class="table-card">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h5 class="card-title mb-0">Movimientos Recientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentMovements)): ?>
                            <p class="text-muted text-center py-3">No hay movimientos recientes</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Tipo</th>
                                            <th class="text-end">Cantidad</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentMovements as $movement): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo htmlspecialchars($movement['product_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = match($movement['type']) {
                                                    'entry' => 'bg-success',
                                                    'exit' => 'bg-danger',
                                                    'adjustment' => 'bg-warning'
                                                };
                                                $typeText = match($movement['type']) {
                                                    'entry' => 'Entrada',
                                                    'exit' => 'Salida',
                                                    'adjustment' => 'Ajuste'
                                                };
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?> badge-movement">
                                                    <?php echo $typeText; ?>
                                                </span>
                                            </td>
                                            <td class="text-end"><?php echo number_format($movement['quantity']); ?></td>
                                            <td><small><?php echo formatDate($movement['created_at']); ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.querySelector('[data-bs-toggle="offcanvas"]');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggleBtn?.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>