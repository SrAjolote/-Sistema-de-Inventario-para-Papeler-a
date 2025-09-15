<?php
require_once 'config.php';
requireLogin();

// Verificar permisos de administrador
if (!hasPermission('admin')) {
    header('Location: index.php');
    exit;
}

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
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                $status = $_POST['status'];
                
                // Validaciones
                if (empty($username) || empty($email) || empty($password)) {
                    $message = 'Todos los campos son obligatorios.';
                    $messageType = 'danger';
                    break;
                }
                
                if (strlen($password) < 6) {
                    $message = 'La contraseña debe tener al menos 6 caracteres.';
                    $messageType = 'danger';
                    break;
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'El email no es válido.';
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    // Verificar si el usuario ya existe
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    
                    if ($stmt->fetch()) {
                        $message = 'El usuario o email ya existe.';
                        $messageType = 'danger';
                        break;
                    }
                    
                    // Crear usuario
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashedPassword, $role, $status]);
                    
                    // Registrar actividad
                    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'user_created', ?)");
                    $stmt->execute([$_SESSION['user_id'], "Usuario creado: $username"]);
                    
                    $message = 'Usuario creado exitosamente.';
                    $messageType = 'success';
                    
                } catch (Exception $e) {
                    $message = 'Error al crear el usuario: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'update':
                $user_id = intval($_POST['user_id']);
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $role = $_POST['role'];
                $status = $_POST['status'];
                $password = $_POST['password'] ?? '';
                
                // Validaciones
                if (empty($username) || empty($email)) {
                    $message = 'El nombre de usuario y email son obligatorios.';
                    $messageType = 'danger';
                    break;
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'El email no es válido.';
                    $messageType = 'danger';
                    break;
                }
                
                // No permitir que el usuario se desactive a sí mismo
                if ($user_id == $_SESSION['user_id'] && $status === 'inactive') {
                    $message = 'No puedes desactivar tu propia cuenta.';
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    // Verificar si el usuario/email ya existe (excluyendo el actual)
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                    $stmt->execute([$username, $email, $user_id]);
                    
                    if ($stmt->fetch()) {
                        $message = 'El usuario o email ya existe.';
                        $messageType = 'danger';
                        break;
                    }
                    
                    // Actualizar usuario
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $message = 'La contraseña debe tener al menos 6 caracteres.';
                            $messageType = 'danger';
                            break;
                        }
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $hashedPassword, $role, $status, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $role, $status, $user_id]);
                    }
                    
                    // Registrar actividad
                    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'user_updated', ?)");
                    $stmt->execute([$_SESSION['user_id'], "Usuario actualizado: $username"]);
                    
                    $message = 'Usuario actualizado exitosamente.';
                    $messageType = 'success';
                    
                } catch (Exception $e) {
                    $message = 'Error al actualizar el usuario: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $user_id = intval($_POST['user_id']);
                
                // No permitir que el usuario se elimine a sí mismo
                if ($user_id == $_SESSION['user_id']) {
                    $message = 'No puedes eliminar tu propia cuenta.';
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    // Obtener información del usuario antes de eliminarlo
                    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        $message = 'Usuario no encontrado.';
                        $messageType = 'danger';
                        break;
                    }
                    
                    // Eliminar usuario
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Registrar actividad
                    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'user_deleted', ?)");
                    $stmt->execute([$_SESSION['user_id'], "Usuario eliminado: " . $user['username']]);
                    
                    $message = 'Usuario eliminado exitosamente.';
                    $messageType = 'success';
                    
                } catch (Exception $e) {
                    $message = 'Error al eliminar el usuario: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Obtener filtros
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir consulta con filtros
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($role_filter) {
    $whereConditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    $whereConditions[] = "status = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Obtener usuarios
$sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Contar total para paginación
$countSql = "SELECT COUNT(*) FROM users $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Obtener actividad reciente
$recentActivity = $pdo->query("
    SELECT al.*, u.username
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.action IN ('user_created', 'user_updated', 'user_deleted', 'login', 'logout')
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?php echo APP_NAME; ?></title>
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
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .status-badge {
            font-size: 0.75rem;
        }
        
        .role-badge {
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
            <h4 class="mb-0">Usuarios</h4>
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
            <a href="reports.php" class="nav-link">
                <i class="bi bi-graph-up me-2"></i> Reportes
            </a>
            <a href="users.php" class="nav-link active">
                <i class="bi bi-people me-2"></i> Usuarios
            </a>
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
                <h1 class="h3 mb-0">Gestión de Usuarios</h1>
                <p class="text-muted mb-0">Administra los usuarios del sistema</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
                <i class="bi bi-plus-lg me-2"></i> Nuevo Usuario
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
            <!-- Users Table -->
            <div class="col-lg-8">
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Usuario o email...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Rol</label>
                                <select class="form-select" name="role">
                                    <option value="">Todos</option>
                                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Usuario</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="status">
                                    <option value="">Todos</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
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
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Último Acceso</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No se encontraron usuarios
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                        <br><small class="text-muted">Creado: <?php echo formatDate($user['created_at']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php
                                                $roleClass = $user['role'] === 'admin' ? 'bg-danger' : 'bg-primary';
                                                $roleText = $user['role'] === 'admin' ? 'Administrador' : 'Usuario';
                                                ?>
                                                <span class="badge <?php echo $roleClass; ?> role-badge">
                                                    <?php echo $roleText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = $user['status'] === 'active' ? 'bg-success' : 'bg-secondary';
                                                $statusText = $user['status'] === 'active' ? 'Activo' : 'Inactivo';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?> status-badge">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <?php echo formatDate($user['last_login']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Nunca</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
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
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
            
            <!-- Activity Log -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Actividad Reciente</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivity)): ?>
                            <p class="text-muted text-center py-3">No hay actividad reciente</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentActivity as $activity): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php
                                                $actionText = match($activity['action']) {
                                                    'user_created' => 'Usuario creado',
                                                    'user_updated' => 'Usuario actualizado',
                                                    'user_deleted' => 'Usuario eliminado',
                                                    'login' => 'Inicio de sesión',
                                                    'logout' => 'Cierre de sesión',
                                                    default => $activity['action']
                                                };
                                                echo $actionText;
                                                ?>
                                            </h6>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($activity['details']); ?></p>
                                            <small class="text-muted">
                                                <?php echo formatDate($activity['created_at']); ?>
                                                <?php if ($activity['username']): ?>
                                                    - <?php echo htmlspecialchars($activity['username']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="userForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="userAction" value="create">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="userModalTitle">Nuevo Usuario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" id="passwordLabel">Contraseña *</label>
                            <input type="password" class="form-control" name="password" id="password">
                            <small class="text-muted" id="passwordHelp">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rol *</label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="user">Usuario</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Estado *</label>
                            <select class="form-select" name="status" id="status" required>
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="userSubmitBtn">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmar Eliminación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas eliminar al usuario <strong id="deleteUserName"></strong>?</p>
                        <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetUserForm() {
            document.getElementById('userForm').reset();
            document.getElementById('userAction').value = 'create';
            document.getElementById('userId').value = '';
            document.getElementById('userModalTitle').textContent = 'Nuevo Usuario';
            document.getElementById('userSubmitBtn').textContent = 'Crear Usuario';
            document.getElementById('passwordLabel').textContent = 'Contraseña *';
            document.getElementById('passwordHelp').textContent = 'Mínimo 6 caracteres';
            document.getElementById('password').required = true;
        }
        
        function editUser(user) {
            document.getElementById('userAction').value = 'update';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('role').value = user.role;
            document.getElementById('status').value = user.status;
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            
            document.getElementById('userModalTitle').textContent = 'Editar Usuario';
            document.getElementById('userSubmitBtn').textContent = 'Actualizar Usuario';
            document.getElementById('passwordLabel').textContent = 'Nueva Contraseña';
            document.getElementById('passwordHelp').textContent = 'Dejar en blanco para mantener la actual';
            
            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        }
        
        function deleteUser(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = username;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
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