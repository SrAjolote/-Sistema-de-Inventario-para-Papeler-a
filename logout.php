<?php
require_once 'config.php';

// Registrar actividad de logout si hay sesión activa
if (isLoggedIn()) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'logout', 'Usuario cerró sesión', $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (PDOException $e) {
        // Error silencioso en el log
    }
}

// Destruir sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit;
?>