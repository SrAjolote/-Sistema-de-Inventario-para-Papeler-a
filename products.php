<?php
require_once 'config.php';
requireLogin();

$message = '';
$messageType = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Token de seguridad inválido.';
        $messageType = 'danger';
    } else {
        switch ($action) {
            case 'create':
            case 'update':
                $id = $_POST['id'] ?? null;
                $code = sanitize($_POST['code']);
                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $category_id = $_POST['category_id'] ?: null;
                $supplier_id = $_POST['supplier_id'] ?: null;
                $purchase_price = floatval($_POST['purchase_price']);
                $sale_price = floatval($_POST['sale_price']);
                $stock_min = intval($_POST['stock_min']);
                $stock_current = intval($_POST['stock_current']);
                $status = $_POST['status'];
                
                // Manejar subida de imagen
                $image = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/';
                    $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $fileName = uniqid() . '.' . $fileExtension;
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                            $image = $fileName;
                        }
                    }
                }
                
                try {
                    if ($action === 'create') {
                        $sql = "INSERT INTO products (code, name, description, category_id, supplier_id, purchase_price, sale_price, stock_min, stock_current, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$code, $name, $description, $category_id, $supplier_id, $purchase_price, $sale_price, $stock_min, $stock_current, $image, $status]);
                        
                        $productId = $pdo->lastInsertId();
                        
                        // Registrar movimiento inicial de inventario
                        if ($stock_current > 0) {
                            $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, type, quantity, previous_stock, new_stock, reason, user_id) VALUES (?, 'entry', ?, 0, ?, 'Stock inicial', ?)");
                            $stmt->execute([$productId, $stock_current, $stock_current, $_SESSION['user_id']]);
                        }
                        
                        $message = 'Producto creado exitosamente.';
                        $messageType = 'success';
                    } else {
                        // Obtener stock actual para comparar
                        $stmt = $pdo->prepare("SELECT stock_current FROM products WHERE id = ?");
                        $stmt->execute([$id]);
                        $currentProduct = $stmt->fetch();
                        $previousStock = $currentProduct['stock_current'];
                        
                        $sql = "UPDATE products SET code = ?, name = ?, description = ?, category_id = ?, supplier_id = ?, purchase_price = ?, sale_price = ?, stock_min = ?, stock_current = ?, status = ?";
                        $params = [$code, $name, $description, $category_id, $supplier_id, $purchase_price, $sale_price, $stock_min, $stock_current, $status];
                        
                        if ($image) {
                            $sql .= ", image = ?";
                            $params[] = $image;
                        }
                        
                        $sql .= " WHERE id = ?";
                        $params[] = $id;
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        
                        // Registrar movimiento si cambió el stock
                        if ($stock_current != $previousStock) {
                            $movementType = $stock_current > $previousStock ? 'entry' : 'exit';
                            $quantity = abs($stock_current - $previousStock);
                            $reason = 'Ajuste manual';
                            
                            $stmt = $pdo->prepare("INSERT INTO inventory_movements (product_id, type, quantity, previous_stock, new_stock, reason, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$id, $movementType, $quantity, $previousStock, $stock_current, $reason, $_SESSION['user_id']]);
                        }
                        
                        $message = 'Producto actualizado exitosamente.';
                        $messageType = 'success';
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = 'El código del producto ya existe.';
                    } else {
                        $message = 'Error al guardar el producto: ' . $e->getMessage();
                    }
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Producto eliminado exitosamente.';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error al eliminar el producto.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Obtener filtros
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';
$status_filter = $_GET['status'] ?? 'active';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir consulta con filtros
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category_filter) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($supplier_filter) {
    $whereConditions[] = "p.supplier_id = ?";
    $params[] = $supplier_filter;
}

if ($status_filter) {
    $whereConditions[] = "p.status = ?";
    $params[] = $status_filter;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Obtener productos
$sql = "
    SELECT p.*, c.name as category_name, s.name as supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    $whereClause
    ORDER BY p.created_at DESC
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

// Obtener categorías y proveedores para filtros
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - <?php echo APP_NAME; ?></title>
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
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .stock-badge {
            font-size: 0.75rem;
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
            <a href="products.php" class="nav-link active">
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
                <h1 class="h3 mb-0">Gestión de Productos</h1>
                <p class="text-muted mb-0">Administra el catálogo de productos</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openProductModal()">
                <i class="bi bi-plus-lg me-2"></i> Nuevo Producto
            </button>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre, código o descripción...">
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-2">
                        <label class="form-label">Proveedor</label>
                        <select class="form-select" name="supplier">
                            <option value="">Todos</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_filter == $supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                            <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>Todos</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i> Filtrar
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
                                <th>Imagen</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Proveedor</th>
                                <th class="text-end">Precio Venta</th>
                                <th class="text-center">Stock</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    No se encontraron productos
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" class="product-image" alt="Producto">
                                        <?php else: ?>
                                            <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($product['code']); ?></code></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <?php if ($product['description']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></td>
                                    <td><?php echo htmlspecialchars($product['supplier_name'] ?? 'Sin proveedor'); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($product['sale_price']); ?></td>
                                    <td class="text-center">
                                        <?php
                                        $stockClass = $product['stock_current'] <= $product['stock_min'] ? 'bg-danger' : 'bg-success';
                                        ?>
                                        <span class="badge <?php echo $stockClass; ?> stock-badge">
                                            <?php echo number_format($product['stock_current']); ?>
                                        </span>
                                        <br><small class="text-muted">Min: <?php echo $product['stock_min']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $product['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $product['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" data-bs-toggle="modal" data-bs-target="#productModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                <i class="bi bi-trash"></i>
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
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&supplier=<?php echo urlencode($supplier_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="productForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="productId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nuevo Producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Código *</label>
                                <input type="text" class="form-control" name="code" id="productCode" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="status" id="productStatus">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nombre *</label>
                                <input type="text" class="form-control" name="name" id="productName" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="description" id="productDescription" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" name="category_id" id="productCategory">
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Proveedor</label>
                                <select class="form-select" name="supplier_id" id="productSupplier">
                                    <option value="">Seleccionar proveedor</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Precio Compra</label>
                                <input type="number" class="form-control" name="purchase_price" id="productPurchasePrice" step="0.01" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Precio Venta *</label>
                                <input type="number" class="form-control" name="sale_price" id="productSalePrice" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Stock Mínimo</label>
                                <input type="number" class="form-control" name="stock_min" id="productStockMin" min="0" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Stock Actual</label>
                                <input type="number" class="form-control" name="stock_current" id="productStockCurrent" min="0" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Imagen</label>
                                <input type="file" class="form-control" name="image" id="productImage" accept="image/*">
                                <small class="text-muted">Formatos permitidos: JPG, PNG, GIF, WebP</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openProductModal() {
            document.getElementById('modalTitle').textContent = 'Nuevo Producto';
            document.getElementById('formAction').value = 'create';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
        }
        
        function editProduct(product) {
            document.getElementById('modalTitle').textContent = 'Editar Producto';
            document.getElementById('formAction').value = 'update';
            document.getElementById('productId').value = product.id;
            document.getElementById('productCode').value = product.code;
            document.getElementById('productName').value = product.name;
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productCategory').value = product.category_id || '';
            document.getElementById('productSupplier').value = product.supplier_id || '';
            document.getElementById('productPurchasePrice').value = product.purchase_price;
            document.getElementById('productSalePrice').value = product.sale_price;
            document.getElementById('productStockMin').value = product.stock_min;
            document.getElementById('productStockCurrent').value = product.stock_current;
            document.getElementById('productStatus').value = product.status;
        }
        
        function deleteProduct(id, name) {
            if (confirm(`¿Está seguro de que desea eliminar el producto "${name}"?`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
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