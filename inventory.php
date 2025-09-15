<?php
require_once 'config.php';
requireLogin();

$message = '';
$messageType = '';

// Procesar movimientos de inventario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Token de seguridad inválido.';
        $messageType = 'danger';
    } else {
        switch ($action) {
            case 'movement':
                $product_id = $_POST['product_id'];
                $type = $_POST['type'];
                $quantity = intval($_POST['quantity']);
                $reason = sanitize($_POST['reason']);
                
                if ($quantity <= 0) {
                    $message = 'La cantidad debe ser mayor a cero.';
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Obtener stock actual
                    $stmt = $pdo->prepare("SELECT stock_current FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    
                    if (!$product) {
                        throw new Exception('Producto no encontrado.');
                    }
                    
                    $previousStock = $product['stock_current'];
                    $newStock = $previousStock;
                    
                    // Calcular nuevo stock según el tipo de movimiento
                    switch ($type) {
                        case 'entry':
                            $newStock = $previousStock + $quantity;
                            break;
                        case 'exit':
                            if ($previousStock < $quantity) {
                                throw new Exception('Stock insuficiente para realizar la salida.');
                            }
                            $newStock = $previousStock - $quantity;
                            break;
                        case 'adjustment':
                            $newStock = $quantity;
                            $quantity = abs($newStock - $previousStock);
                            break;
                    }
                    
                    // Actualizar stock del producto
                    $stmt = $pdo->prepare("UPDATE products SET stock_current = ? WHERE id = ?");
                    $stmt->execute([$newStock, $product_id]);
                    
                    // Registrar movimiento
                    $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, type, quantity, previous_stock, new_stock, reason, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$product_id, $type, $quantity, $previousStock, $newStock, $reason, $_SESSION['user_id']]);
                    
                    $pdo->commit();
                    
                    $typeText = match($type) {
                        'entry' => 'entrada',
                        'exit' => 'salida',
                        'adjustment' => 'ajuste'
                    };
                    
                    $message = "Movimiento de $typeText registrado exitosamente.";
                    $messageType = 'success';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Obtener filtros
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir consulta con filtros
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(p.name LIKE ? OR p.code LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category_filter) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($stock_filter === 'low') {
    $whereConditions[] = "p.stock_current <= p.stock_min";
} elseif ($stock_filter === 'zero') {
    $whereConditions[] = "p.stock_current = 0";
}

$whereConditions[] = "p.status = 'active'";
$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Obtener productos con información de inventario
$sql = "
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereClause
    ORDER BY p.name
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Contar total para paginación
$countSql = "SELECT COUNT(*) FROM products p $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalProducts = $stmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);

// Obtener categorías para filtros
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Obtener movimientos recientes
$recentMovements = $pdo->query("
    SELECT im.*, p.name as product_name, p.code as product_code, u.username
    FROM inventory_movements im
    JOIN products p ON im.product_id = p.id
    LEFT JOIN users u ON im.user_id = u.id
    ORDER BY im.created_at DESC
    LIMIT 10
")->fetchAll();

// Obtener productos para el modal
$allProducts = $pdo->query("SELECT id, code, name, stock_current FROM products WHERE status = 'active' ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Inventario - <?php echo APP_NAME; ?></title>
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
        
        .stock-badge {
            font-size: 0.75rem;
        }
        
        .movement-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .quick-action-btn {
            border: none;
            background: none;
            color: #6c757d;
            transition: color 0.2s;
        }
        
        .quick-action-btn:hover {
            color: var(--primary-color);
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
            <a href="inventory.php" class="nav-link active">
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
                <h1 class="h3 mb-0">Control de Inventario</h1>
                <p class="text-muted mb-0">Gestiona entradas, salidas y ajustes de stock</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#movementModal">
                <i class="bi bi-plus-lg me-2"></i> Nuevo Movimiento
            </button>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- Inventory Table -->
            <div class="col-lg-8">
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre o código...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" name="category">
                                    <option value="">Todas</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Stock</label>
                                <select class="form-select" name="stock_filter">
                                    <option value="" <?php echo $stock_filter === '' ? 'selected' : ''; ?>>Todos</option>
                                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Stock Bajo</option>
                                    <option value="zero" <?php echo $stock_filter === 'zero' ? 'selected' : ''; ?>>Sin Stock</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Products Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th class="text-center">Stock Actual</th>
                                        <th class="text-center">Stock Mín.</th>
                                        <th class="text-end">Valor</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No se encontraron productos
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($product['code']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></td>
                                            <td class="text-center">
                                                <?php
                                                $stockClass = $product['stock_current'] <= $product['stock_min'] ? 'bg-danger' : 'bg-success';
                                                if ($product['stock_current'] == 0) $stockClass = 'bg-dark';
                                                ?>
                                                <span class="badge <?php echo $stockClass; ?> stock-badge">
                                                    <?php echo number_format($product['stock_current']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?php echo number_format($product['stock_min']); ?></td>
                                            <td class="text-end"><?php echo formatCurrency($product['stock_current'] * $product['sale_price']); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="quick-action-btn" onclick="quickMovement(<?php echo $product['id']; ?>, 'entry')" title="Entrada">
                                                        <i class="bi bi-plus-circle text-success"></i>
                                                    </button>
                                                    <button class="quick-action-btn" onclick="quickMovement(<?php echo $product['id']; ?>, 'exit')" title="Salida">
                                                        <i class="bi bi-dash-circle text-danger"></i>
                                                    </button>
                                                    <button class="quick-action-btn" onclick="quickMovement(<?php echo $product['id']; ?>, 'adjustment')" title="Ajuste">
                                                        <i class="bi bi-gear text-warning"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&stock_filter=<?php echo urlencode($stock_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Movements -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Movimientos Recientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentMovements)): ?>
                            <p class="text-muted text-center py-3">No hay movimientos recientes</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentMovements as $movement): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($movement['product_name']); ?></h6>
                                            <p class="mb-1">
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
                                                <span class="badge <?php echo $badgeClass; ?> movement-badge">
                                                    <?php echo $typeText; ?>
                                                </span>
                                                <span class="ms-2"><?php echo number_format($movement['quantity']); ?></span>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo formatDate($movement['created_at']); ?>
                                                <?php if ($movement['username']): ?>
                                                    - <?php echo htmlspecialchars($movement['username']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php if ($movement['reason']): ?>
                                    <small class="text-muted d-block mt-1">
                                        <?php echo htmlspecialchars($movement['reason']); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Movement Modal -->
    <div class="modal fade" id="movementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="movementForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="movement">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar Movimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Producto *</label>
                            <select class="form-select" name="product_id" id="productSelect" required onchange="updateStockInfo()">
                                <option value="">Seleccionar producto</option>
                                <?php foreach ($allProducts as $product): ?>
                                <option value="<?php echo $product['id']; ?>" data-stock="<?php echo $product['stock_current']; ?>">
                                    <?php echo htmlspecialchars($product['code'] . ' - ' . $product['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" id="stockInfo"></small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Movimiento *</label>
                            <select class="form-select" name="type" id="movementType" required onchange="updateQuantityLabel()">
                                <option value="">Seleccionar tipo</option>
                                <option value="entry">Entrada (Agregar stock)</option>
                                <option value="exit">Salida (Reducir stock)</option>
                                <option value="adjustment">Ajuste (Establecer stock)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" id="quantityLabel">Cantidad *</label>
                            <input type="number" class="form-control" name="quantity" id="quantityInput" min="1" required>
                            <small class="text-muted" id="quantityHelp"></small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea class="form-control" name="reason" rows="2" placeholder="Descripción del movimiento..."></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar Movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStockInfo() {
            const select = document.getElementById('productSelect');
            const stockInfo = document.getElementById('stockInfo');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                const stock = selectedOption.dataset.stock;
                stockInfo.textContent = `Stock actual: ${stock} unidades`;
            } else {
                stockInfo.textContent = '';
            }
        }
        
        function updateQuantityLabel() {
            const type = document.getElementById('movementType').value;
            const label = document.getElementById('quantityLabel');
            const help = document.getElementById('quantityHelp');
            
            switch (type) {
                case 'entry':
                    label.textContent = 'Cantidad a agregar *';
                    help.textContent = 'Cantidad que se agregará al stock actual';
                    break;
                case 'exit':
                    label.textContent = 'Cantidad a reducir *';
                    help.textContent = 'Cantidad que se reducirá del stock actual';
                    break;
                case 'adjustment':
                    label.textContent = 'Nuevo stock *';
                    help.textContent = 'Stock final después del ajuste';
                    break;
                default:
                    label.textContent = 'Cantidad *';
                    help.textContent = '';
            }
        }
        
        function quickMovement(productId, type) {
            const modal = new bootstrap.Modal(document.getElementById('movementModal'));
            document.getElementById('productSelect').value = productId;
            document.getElementById('movementType').value = type;
            updateStockInfo();
            updateQuantityLabel();
            modal.show();
        }
        
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>