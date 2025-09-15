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
                $description = sanitize($_POST['description']);
                $parent_id = $_POST['parent_id'] ?: null;
                
                try {
                    if ($action === 'create') {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, description, parent_id) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $description, $parent_id]);
                        $message = 'Categoría creada exitosamente.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, parent_id = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $parent_id, $id]);
                        $message = 'Categoría actualizada exitosamente.';
                    }
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error al guardar la categoría: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                try {
                    // Verificar si tiene productos asociados
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                    $stmt->execute([$id]);
                    $productCount = $stmt->fetchColumn();
                    
                    // Verificar si tiene subcategorías
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
                    $stmt->execute([$id]);
                    $subcategoryCount = $stmt->fetchColumn();
                    
                    if ($productCount > 0) {
                        $message = 'No se puede eliminar la categoría porque tiene productos asociados.';
                        $messageType = 'warning';
                    } elseif ($subcategoryCount > 0) {
                        $message = 'No se puede eliminar la categoría porque tiene subcategorías.';
                        $messageType = 'warning';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = 'Categoría eliminada exitosamente.';
                        $messageType = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error al eliminar la categoría.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Obtener categorías con información de jerarquía
$sql = "
    SELECT c.*, 
           p.name as parent_name,
           (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategory_count,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY COALESCE(c.parent_id, c.id), c.name
";

$categories = $pdo->query($sql)->fetchAll();

// Obtener categorías principales para el select
$parentCategories = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - <?php echo APP_NAME; ?></title>
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
        
        .subcategory-row {
            background-color: #f8f9fa;
        }
        
        .category-indent {
            padding-left: 2rem;
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
            <a href="categories.php" class="nav-link active">
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
                <h1 class="h3 mb-0">Gestión de Categorías</h1>
                <p class="text-muted mb-0">Organiza los productos por categorías</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCategoryModal()">
                <i class="bi bi-plus-lg me-2"></i> Nueva Categoría
            </button>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Categories Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Categoría Padre</th>
                                <th class="text-center">Subcategorías</th>
                                <th class="text-center">Productos</th>
                                <th>Fecha Creación</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No hay categorías registradas
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                <tr class="<?php echo $category['parent_id'] ? 'subcategory-row' : ''; ?>">
                                    <td class="<?php echo $category['parent_id'] ? 'category-indent' : ''; ?>">
                                        <?php if ($category['parent_id']): ?>
                                            <i class="bi bi-arrow-return-right me-2 text-muted"></i>
                                        <?php endif; ?>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($category['parent_name']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($category['parent_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Categoría principal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($category['subcategory_count'] > 0): ?>
                                            <span class="badge bg-info"><?php echo $category['subcategory_count']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($category['product_count'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $category['product_count']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($category['created_at']); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
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
            </div>
        </div>
    </main>
    
    <!-- Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="categoryForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="categoryId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nueva Categoría</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" name="name" id="categoryName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="description" id="categoryDescription" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Categoría Padre</label>
                            <select class="form-select" name="parent_id" id="categoryParent">
                                <option value="">Categoría principal</option>
                                <?php foreach ($parentCategories as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecciona una categoría padre para crear una subcategoría</small>
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
        function openCategoryModal() {
            document.getElementById('modalTitle').textContent = 'Nueva Categoría';
            document.getElementById('formAction').value = 'create';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
        }
        
        function editCategory(category) {
            document.getElementById('modalTitle').textContent = 'Editar Categoría';
            document.getElementById('formAction').value = 'update';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('categoryName').value = category.name;
            document.getElementById('categoryDescription').value = category.description || '';
            document.getElementById('categoryParent').value = category.parent_id || '';
        }
        
        function deleteCategory(id, name) {
            if (confirm(`¿Está seguro de que desea eliminar la categoría "${name}"?\n\nEsta acción no se puede deshacer.`)) {
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