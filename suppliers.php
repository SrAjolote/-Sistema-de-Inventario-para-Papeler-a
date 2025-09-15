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
                $name = sanitize($_POST['name']);
                $contact_person = sanitize($_POST['contact_person']);
                $email = sanitize($_POST['email']);
                $phone = sanitize($_POST['phone']);
                $address = sanitize($_POST['address']);
                $city = sanitize($_POST['city']);
                $country = sanitize($_POST['country']);
                
                try {
                    if ($action === 'create') {
                        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address, city, country) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $contact_person, $email, $phone, $address, $city, $country]);
                        $message = 'Proveedor creado exitosamente.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, city = ?, country = ? WHERE id = ?");
                        $stmt->execute([$name, $contact_person, $email, $phone, $address, $city, $country, $id]);
                        $message = 'Proveedor actualizado exitosamente.';
                    }
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error al guardar el proveedor: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                try {
                    // Verificar si tiene productos asociados
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
                    $stmt->execute([$id]);
                    $productCount = $stmt->fetchColumn();
                    
                    if ($productCount > 0) {
                        $message = 'No se puede eliminar el proveedor porque tiene productos asociados.';
                        $messageType = 'warning';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = 'Proveedor eliminado exitosamente.';
                        $messageType = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error al eliminar el proveedor.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Obtener filtros
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir consulta con filtros
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Obtener proveedores con estadísticas
$sql = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM products WHERE supplier_id = s.id) as product_count,
           (SELECT COUNT(*) FROM purchases WHERE supplier_id = s.id) as purchase_count
    FROM suppliers s
    $whereClause
    ORDER BY s.name
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

// Contar total para paginación
$countSql = "SELECT COUNT(*) FROM suppliers s $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalSuppliers = $stmt->fetchColumn();
$totalPages = ceil($totalSuppliers / $limit);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - <?php echo APP_NAME; ?></title>
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
        
        .supplier-card {
            transition: transform 0.2s ease;
        }
        
        .supplier-card:hover {
            transform: translateY(-2px);
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
            <a href="suppliers.php" class="nav-link active">
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
                <h1 class="h3 mb-0">Gestión de Proveedores</h1>
                <p class="text-muted mb-0">Administra la información de proveedores</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openSupplierModal()">
                <i class="bi bi-plus-lg me-2"></i> Nuevo Proveedor
            </button>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Buscar</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre, contacto, email o teléfono...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Suppliers Grid -->
        <div class="row g-4">
            <?php if (empty($suppliers)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-truck display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No se encontraron proveedores</h5>
                        <p class="text-muted">Comienza agregando un nuevo proveedor</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($suppliers as $supplier): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card supplier-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($supplier['name']); ?></h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="editSupplier(<?php echo htmlspecialchars(json_encode($supplier)); ?>)" data-bs-toggle="modal" data-bs-target="#supplierModal">
                                                <i class="bi bi-pencil me-2"></i> Editar
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="deleteSupplier(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['name']); ?>')">
                                                <i class="bi bi-trash me-2"></i> Eliminar
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if ($supplier['contact_person']): ?>
                            <p class="text-muted mb-2">
                                <i class="bi bi-person me-2"></i>
                                <?php echo htmlspecialchars($supplier['contact_person']); ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if ($supplier['email']): ?>
                            <p class="text-muted mb-2">
                                <i class="bi bi-envelope me-2"></i>
                                <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($supplier['email']); ?>
                                </a>
                            </p>
                            <?php endif; ?>
                            
                            <?php if ($supplier['phone']): ?>
                            <p class="text-muted mb-2">
                                <i class="bi bi-telephone me-2"></i>
                                <a href="tel:<?php echo htmlspecialchars($supplier['phone']); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($supplier['phone']); ?>
                                </a>
                            </p>
                            <?php endif; ?>
                            
                            <?php if ($supplier['address']): ?>
                            <p class="text-muted mb-3">
                                <i class="bi bi-geo-alt me-2"></i>
                                <?php echo htmlspecialchars($supplier['address']); ?>
                                <?php if ($supplier['city']): ?>
                                    <br><small><?php echo htmlspecialchars($supplier['city']); ?></small>
                                <?php endif; ?>
                                <?php if ($supplier['country']): ?>
                                    <small>, <?php echo htmlspecialchars($supplier['country']); ?></small>
                                <?php endif; ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h6 class="mb-0"><?php echo number_format($supplier['product_count']); ?></h6>
                                        <small class="text-muted">Productos</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h6 class="mb-0"><?php echo number_format($supplier['purchase_count']); ?></h6>
                                    <small class="text-muted">Compras</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-transparent">
                            <small class="text-muted">
                                Registrado: <?php echo formatDate($supplier['created_at']); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </main>
    
    <!-- Supplier Modal -->
    <div class="modal fade" id="supplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="supplierForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="supplierId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nuevo Proveedor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre de la Empresa *</label>
                                <input type="text" class="form-control" name="name" id="supplierName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Persona de Contacto</label>
                                <input type="text" class="form-control" name="contact_person" id="supplierContactPerson">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="supplierEmail">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="phone" id="supplierPhone">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="address" id="supplierAddress" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="city" id="supplierCity">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">País</label>
                                <input type="text" class="form-control" name="country" id="supplierCountry">
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
        function openSupplierModal() {
            document.getElementById('modalTitle').textContent = 'Nuevo Proveedor';
            document.getElementById('formAction').value = 'create';
            document.getElementById('supplierForm').reset();
            document.getElementById('supplierId').value = '';
        }
        
        function editSupplier(supplier) {
            document.getElementById('modalTitle').textContent = 'Editar Proveedor';
            document.getElementById('formAction').value = 'update';
            document.getElementById('supplierId').value = supplier.id;
            document.getElementById('supplierName').value = supplier.name;
            document.getElementById('supplierContactPerson').value = supplier.contact_person || '';
            document.getElementById('supplierEmail').value = supplier.email || '';
            document.getElementById('supplierPhone').value = supplier.phone || '';
            document.getElementById('supplierAddress').value = supplier.address || '';
            document.getElementById('supplierCity').value = supplier.city || '';
            document.getElementById('supplierCountry').value = supplier.country || '';
        }
        
        function deleteSupplier(id, name) {
            if (confirm(`¿Está seguro de que desea eliminar el proveedor "${name}"?\n\nEsta acción no se puede deshacer.`)) {
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