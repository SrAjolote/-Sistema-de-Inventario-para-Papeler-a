<?php
require_once 'config.php';

// Verificar si ya está instalado
if (file_exists('installed.lock')) {
    die('El sistema ya está instalado. Elimine el archivo "installed.lock" para reinstalar.');
}

$success = true;
$messages = [];

try {
    // Crear tablas
    $sql = "
    -- Tabla de usuarios
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Tabla de categorías
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        parent_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
    );

    -- Tabla de proveedores
    CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        city VARCHAR(50),
        country VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Tabla de productos
    CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        category_id INT,
        supplier_id INT,
        purchase_price DECIMAL(10,2) DEFAULT 0.00,
        sale_price DECIMAL(10,2) DEFAULT 0.00,
        stock_min INT DEFAULT 0,
        stock_current INT DEFAULT 0,
        image VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
        INDEX idx_code (code),
        INDEX idx_name (name),
        INDEX idx_category (category_id)
    );

    -- Tabla de movimientos de inventario
    CREATE TABLE IF NOT EXISTS inventory_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        type ENUM('entry', 'exit', 'adjustment') NOT NULL,
        quantity INT NOT NULL,
        previous_stock INT NOT NULL,
        new_stock INT NOT NULL,
        reason VARCHAR(255),
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_product (product_id),
        INDEX idx_type (type),
        INDEX idx_date (created_at)
    );

    -- Tabla de compras
    CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT,
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        notes TEXT,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );

    -- Tabla de detalles de compras
    CREATE TABLE IF NOT EXISTS purchase_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    -- Tabla de actividades del sistema
    CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user (user_id),
        INDEX idx_date (created_at)
    );
    ";

    // Ejecutar las consultas
    $pdo->exec($sql);
    $messages[] = 'Tablas creadas exitosamente.';

    // Insertar categorías predefinidas
    $categories = [
        ['Material de Escritura', 'Lápices, bolígrafos, marcadores, etc.'],
        ['Papelería', 'Hojas, cuadernos, carpetas, etc.'],
        ['Arte', 'Materiales para dibujo y pintura'],
        ['Oficina', 'Suministros para oficina'],
        ['Escolares', 'Materiales para estudiantes'],
        ['Suministros de Impresión', 'Tintas, cartuchos, papel especial']
    ];

    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    foreach ($categories as $category) {
        $stmt->execute($category);
    }
    $messages[] = 'Categorías predefinidas insertadas.';

    // Crear usuario administrador
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@papeleria.com', $adminPassword, 'admin']);
    $messages[] = 'Usuario administrador creado (usuario: admin, contraseña: admin123).';

    // Crear directorio de uploads
    if (!file_exists('uploads')) {
        mkdir('uploads', 0755, true);
        $messages[] = 'Directorio de uploads creado.';
    }

    // Crear archivo de bloqueo
    file_put_contents('installed.lock', date('Y-m-d H:i:s'));
    $messages[] = 'Instalación completada exitosamente.';

} catch (Exception $e) {
    $success = false;
    $messages[] = 'Error durante la instalación: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Instalación del Sistema</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h4>¡Instalación Exitosa!</h4>
                                <p>El sistema se ha instalado correctamente.</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h4>Error en la Instalación</h4>
                                <p>Ocurrió un error durante la instalación.</p>
                            </div>
                        <?php endif; ?>

                        <h5>Detalles de la instalación:</h5>
                        <ul class="list-group">
                            <?php foreach ($messages as $message): ?>
                                <li class="list-group-item"><?php echo htmlspecialchars($message); ?></li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($success): ?>
                            <div class="mt-4">
                                <h5>Credenciales de Acceso:</h5>
                                <div class="alert alert-info">
                                    <strong>Usuario:</strong> admin<br>
                                    <strong>Contraseña:</strong> admin123
                                </div>
                                <a href="index.php" class="btn btn-primary">Ir al Sistema</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>