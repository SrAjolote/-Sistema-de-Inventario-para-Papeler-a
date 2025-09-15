<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'u331441487_spos');
define('DB_PASS', '2415691611+David');
define('DB_NAME', 'u331441487_spos');

// Configuración de la aplicación
define('APP_NAME', 'Sistema de Inventario - Papelería');
define('APP_VERSION', '1.0.0');
define('UPLOAD_PATH', 'uploads/');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en HTTPS
session_start();

// Conexión a la base de datos
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

// Funciones de utilidad
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function hasPermission($permission) {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}
?>