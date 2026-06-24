<?php
// db.php - Conexión de base de datos auto-inicializable

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'mundial';

try {
    // Conectar a MySQL/MariaDB (sin base de datos primero para poder crearla)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Verificar si la base de datos existe y tiene tablas
    $db_exists = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'")->fetch();
    
    $run_schema = false;
    if (!$db_exists) {
        $run_schema = true;
    } else {
        // Seleccionar base de datos
        $pdo->query("USE `$dbname`");
        // Verificar si la tabla 'equipos' existe para saber si está vacía
        $table_exists = $pdo->query("SHOW TABLES LIKE 'equipos'")->fetch();
        if (!$table_exists) {
            $run_schema = true;
        }
    }

    if ($run_schema) {
        // Cargar y ejecutar schema.sql
        $schema_path = __DIR__ . '/schema.sql';
        if (file_exists($schema_path)) {
            $sql = file_get_contents($schema_path);
            // Ejecutar el script SQL completo
            $pdo->exec($sql);
            // Asegurarnos de usar la base de datos creada
            $pdo->query("USE `$dbname`");
        } else {
            throw new Exception("El archivo schema.sql no se encuentra en " . $schema_path);
        }
    } else {
        $pdo->query("USE `$dbname`");
    }

    // Auto-sincronización periódica con ESPN (cada 30 segundos)
    if (!defined('DISABLE_SYNC_OUTPUT')) {
        $last_sync_file = __DIR__ . '/last_sync.txt';
        $now = time();
        $should_sync = false;
        
        if (!file_exists($last_sync_file)) {
            $should_sync = true;
        } else {
            $last_sync_time = intval(file_get_contents($last_sync_file));
            if ($now - $last_sync_time >= 30) {
                $should_sync = true;
            }
        }
        
        if ($should_sync) {
            define('DISABLE_SYNC_OUTPUT', true);
            // Sincronizar silenciosamente
            require_once __DIR__ . '/sync.php';
        }
    }

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
    exit;
}
