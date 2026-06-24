<?php
// admin_api.php - API Segura de Administración y Mantenimiento

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'No autorizado. Por favor inicia sesión.'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar CSRF token para acciones de modificación (POST/PUT/DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || $client_csrf !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Error de seguridad (CSRF bloqueado).'
        ]);
        exit;
    }
}

try {
    switch ($action) {
        case 'get_config':
            $stmt = $pdo->query("SELECT * FROM configuracion");
            $config = [];
            foreach ($stmt->fetchAll() as $row) {
                $config[$row['clave']] = $row['valor'];
            }
            echo json_encode([
                'status' => 'success',
                'config' => $config
            ]);
            break;

        case 'save_config':
            $banner_header = $_POST['banner_header'] ?? '';
            $banner_sidebar = $_POST['banner_sidebar'] ?? '';
            $afiliado_apuestas_url = $_POST['afiliado_apuestas_url'] ?? '';
            $afiliado_camisetas_url = $_POST['afiliado_camisetas_url'] ?? '';

            $configs = [
                'banner_header' => $banner_header,
                'banner_sidebar' => $banner_sidebar,
                'afiliado_apuestas_url' => $afiliado_apuestas_url,
                'afiliado_camisetas_url' => $afiliado_camisetas_url
            ];

            $upd = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            foreach ($configs as $clave => $valor) {
                $upd->execute([$clave, $valor, $valor]);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Configuración guardada correctamente.'
            ]);
            break;

        case 'list_news':
            $stmt = $pdo->query("SELECT * FROM noticias ORDER BY fecha_creacion DESC");
            echo json_encode([
                'status' => 'success',
                'news' => $stmt->fetchAll()
            ]);
            break;

        case 'save_news':
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
            $tipo = $_POST['tipo'] ?? 'fichaje';
            $titulo = trim($_POST['titulo'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $contenido = $_POST['contenido'] ?? '';
            $enlace_afiliado = trim($_POST['enlace_afiliado'] ?? '');

            if ($titulo === '' || $contenido === '') {
                throw new Exception('El título y contenido son obligatorios.');
            }

            if ($slug === '') {
                // Generar slug básico
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $titulo)));
            }

            // Verificar si el slug ya existe (para evitar errores en la base de datos)
            $check = $pdo->prepare("SELECT id FROM noticias WHERE slug = ? AND id != ?");
            $check->execute([$slug, $id ?? 0]);
            if ($check->fetch()) {
                $slug .= '-' . rand(100, 999);
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE noticias 
                    SET tipo = ?, titulo = ?, slug = ?, contenido = ?, enlace_afiliado = ?
                    WHERE id = ?
                ");
                $stmt->execute([$tipo, $titulo, $slug, $contenido, $enlace_afiliado, $id]);
                $message = 'Artículo actualizado con éxito.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO noticias (tipo, titulo, slug, contenido, enlace_afiliado)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$tipo, $titulo, $slug, $contenido, $enlace_afiliado]);
                $message = 'Artículo creado con éxito.';
            }

            echo json_encode([
                'status' => 'success',
                'message' => $message
            ]);
            break;

        case 'delete_news':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de artículo no válido.');
            }

            $stmt = $pdo->prepare("DELETE FROM noticias WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Artículo eliminado correctamente.'
            ]);
            break;

        case 'force_sync':
            // Definir DISABLE_SYNC_OUTPUT como true para evitar la salida JSON de sync.php y poder capturar el éxito
            define('DISABLE_SYNC_OUTPUT', true);
            $_GET['force'] = 1;
            
            // Requerir sync.php para ejecutar la sincronización de fondo
            require_once __DIR__ . '/sync.php';
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Sincronización manual ejecutada con éxito de fondo.'
            ]);
            break;

        default:
            throw new Exception('Acción no reconocida.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
