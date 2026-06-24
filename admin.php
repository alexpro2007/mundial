<?php
// admin.php - Panel de Administración y Monetización del Portal de Fútbol
session_start();
require_once __DIR__ . '/db.php';

// Cerrar sesión
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['csrf_token']);
    session_destroy();
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}

// Generar CSRF token si está logueado
if (isset($_SESSION['admin_logged_in']) && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - 5 Grandes Ligas</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #0b0f19;
            color: #f1f5f9;
            margin: 0;
            padding: 0;
        }
        .admin-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        .login-card {
            max-width: 400px;
            margin: 100px auto;
            background: rgba(17, 24, 39, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 0 15px rgba(0, 242, 254, 0.1);
            backdrop-filter: blur(10px);
        }
        .login-title {
            text-align: center;
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #00f2fe, #4facfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #94a3b8;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #ffffff;
            font-family: inherit;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #00f2fe;
            outline: none;
            box-shadow: 0 0 10px rgba(0, 242, 254, 0.2);
        }
        .btn-premium {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #00f2fe, #4facfe);
            border: none;
            border-radius: 8px;
            color: #0b0f19;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(0, 242, 254, 0.4);
        }
        .error-message {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        /* Panel Styles */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 20px;
        }
        .admin-nav {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        .admin-nav-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: #94a3b8;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .admin-nav-btn.active, .admin-nav-btn:hover {
            background: rgba(0, 242, 254, 0.1);
            border-color: #00f2fe;
            color: #ffffff;
        }
        .admin-card {
            background: rgba(17, 24, 39, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }
        .admin-card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 20px;
            color: #00f2fe;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-section {
            display: none;
        }
        .panel-section.active {
            display: block;
        }
        .log-container {
            background: #000;
            color: #00f2fe;
            font-family: monospace;
            padding: 15px;
            border-radius: 8px;
            height: 200px;
            overflow-y: auto;
            font-size: 0.85rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-top: 15px;
        }
        .articles-list {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .article-item {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .article-info h4 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
            color: #ffffff;
        }
        .article-info span {
            font-size: 0.8rem;
            color: #64748b;
            background: rgba(255, 255, 255, 0.05);
            padding: 3px 8px;
            border-radius: 4px;
            margin-right: 8px;
        }
        .article-actions {
            display: flex;
            gap: 10px;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .btn-edit {
            background: #e2e8f0;
            color: #0f172a;
        }
        .btn-delete {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>

    <div class="admin-container">
        <?php if (!isset($_SESSION['admin_logged_in'])): ?>
            <!-- Vista: Formulario de Login -->
            <div class="login-card">
                <div class="login-title">Administrador 5 Ligas</div>
                
                <?php if ($error !== ''): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form action="admin.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="username">Usuario</label>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Ej: admin" required autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn-premium">Entrar</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Vista: Panel de Control -->
            <div class="admin-header">
                <div>
                    <h1 style="margin:0; font-size:1.8rem; font-weight:800;">Panel de Administración</h1>
                    <span style="color:#64748b; font-size:0.9rem;">Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
                <div style="display:flex; gap:10px;">
                    <a href="index.php" class="btn-premium" style="text-decoration:none; padding:10px 15px; font-size:0.9rem;">Ver Web</a>
                    <a href="admin.php?action=logout" class="btn-sm btn-delete" style="text-decoration:none; display:flex; align-items:center; padding:10px 15px;">Cerrar Sesión</a>
                </div>
            </div>

            <!-- Navegación del Panel -->
            <div class="admin-nav">
                <button class="admin-nav-btn active" data-target="sec-sync">Sincronización</button>
                <button class="admin-nav-btn" data-target="sec-news">Noticias y Previas</button>
                <button class="admin-nav-btn" data-target="sec-monetization">Anuncios y Afiliación</button>
            </div>

            <!-- Token CSRF oculto para JS -->
            <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- SECCIÓN: SINCRONIZACIÓN -->
            <div id="sec-sync" class="panel-section active">
                <div class="admin-card">
                    <h3 class="admin-card-title">Sincronizar ESPN</h3>
                    <p style="color:#94a3b8; font-size:0.9rem;">Ejecuta el importador automático para descargar los últimos partidos, clasificaciones y marcadores en vivo de las 5 grandes ligas de Europa.</p>
                    <button id="btn-sync" class="btn-premium" style="max-width:250px;">Forzar Sincronización</button>
                    <div id="sync-log" class="log-container">Consola de logs lista. Esperando comando...</div>
                </div>
            </div>

            <!-- SECCIÓN: NOTICIAS Y PREVIAS -->
            <div id="sec-news" class="panel-section">
                <div class="admin-card">
                    <h3 class="admin-card-title">Crear / Editar Artículo</h3>
                    
                    <form id="form-news">
                        <input type="hidden" name="id" id="news-id" value="">
                        
                        <div class="form-group" style="display:flex; gap:15px;">
                            <div style="flex:1;">
                                <label for="news-tipo">Tipo de Artículo</label>
                                <select name="tipo" id="news-tipo" class="form-control">
                                    <option value="fichaje">Fichaje / Rumores</option>
                                    <option value="pronostico">Pronóstico / Análisis de Apuesta</option>
                                </select>
                            </div>
                            <div style="flex:2;">
                                <label for="news-titulo">Título del Artículo</label>
                                <input type="text" name="titulo" id="news-titulo" class="form-control" placeholder="Ej: Rumor: Mbappé al Real Madrid" required>
                            </div>
                        </div>

                        <!-- Campos exclusivos para Fichaje (Tarjeta Visual) -->
                        <div id="transfer-fields-container" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                            <h4 style="margin-top: 0; margin-bottom: 15px; color: #00f2fe; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">Detalles de Fichaje (Tarjeta Visual)</h4>
                            
                            <div class="form-group">
                                <label for="news-foto-jugador">URL Foto del Jugador</label>
                                <input type="url" name="foto_jugador" id="news-foto-jugador" class="form-control" placeholder="https://a.espncdn.com/i/headshots/soccer/players/full/259910.png">
                            </div>

                            <div style="display: flex; gap: 15px;" class="form-group">
                                <div style="flex: 1;">
                                    <label for="news-origen-nombre">Nombre Club de Origen</label>
                                    <input type="text" name="equipo_origen_nombre" id="news-origen-nombre" class="form-control" placeholder="Ej: Chelsea">
                                </div>
                                <div style="flex: 1;">
                                    <label for="news-origen-logo">URL Logo Club Origen</label>
                                    <input type="url" name="equipo_origen_logo" id="news-origen-logo" class="form-control" placeholder="https://a.espncdn.com/.../Chelsea.png">
                                </div>
                            </div>

                            <div style="display: flex; gap: 15px;" class="form-group">
                                <div style="flex: 1;">
                                    <label for="news-destino-nombre">Nombre Club de Destino</label>
                                    <input type="text" name="equipo_destino_nombre" id="news-destino-nombre" class="form-control" placeholder="Ej: Real Madrid">
                                </div>
                                <div style="flex: 1;">
                                    <label for="news-destino-logo">URL Logo Club Destino</label>
                                    <input type="url" name="equipo_destino_logo" id="news-destino-logo" class="form-control" placeholder="https://a.espncdn.com/.../RealMadrid.png">
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="news-contrato-detalles">Detalles de Contrato / Traspaso</label>
                                <input type="text" name="detalles_contrato" id="news-contrato-detalles" class="form-control" placeholder="Ej: Fichaje Confirmado | 5 AÑOS | Cesión | 12 MESES">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="news-slug">Slug SEO (URL amigable - se autogenera si se deja vacío)</label>
                            <input type="text" name="slug" id="news-slug" class="form-control" placeholder="Ej: fichaje-mbappe-real-madrid">
                        </div>

                        <div class="form-group">
                            <label for="news-contenido">Contenido del Artículo (Soporta etiquetas HTML)</label>
                            <textarea name="contenido" id="news-contenido" class="form-control" rows="8" placeholder="Escribe aquí el análisis o noticia..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="news-afiliado">Enlace de Afiliado Específico (Opcional - sobrescribe el global)</label>
                            <input type="url" name="enlace_afiliado" id="news-afiliado" class="form-control" placeholder="https://ejemplo.com/tu-link-de-afiliado">
                        </div>

                        <div style="display:flex; gap:10px;">
                            <button type="submit" class="btn-premium" style="max-width:200px;">Guardar Artículo</button>
                            <button type="button" id="btn-cancel-edit" class="btn-sm btn-delete" style="display:none; padding:12px 20px;">Cancelar Edición</button>
                        </div>
                    </form>
                </div>

                <div class="admin-card">
                    <h3 class="admin-card-title">Artículos Registrados</h3>
                    <div id="articles-container" class="articles-list">
                        Cargando artículos...
                    </div>
                </div>
            </div>

            <!-- SECCIÓN: MONETIZACIÓN -->
            <div id="sec-monetization" class="panel-section">
                <div class="admin-card">
                    <h3 class="admin-card-title">Configuración de Publicidad y Afiliados</h3>
                    <p style="color:#94a3b8; font-size:0.9rem;">Inserta los códigos HTML de tus banners publicitarios y tus enlaces de afiliados a nivel global.</p>
                    
                    <form id="form-config">
                        <div class="form-group">
                            <label for="conf-banner-header">Código Publicidad Superior (Banner Header - 728x90)</label>
                            <textarea name="banner_header" id="conf-banner-header" class="form-control" rows="3" placeholder="Inserta aquí tu código de Google AdSense o banner"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="conf-banner-sidebar">Código Publicidad Lateral (Banner Sidebar - 300x250 o 300x600)</label>
                            <textarea name="banner_sidebar" id="conf-banner-sidebar" class="form-control" rows="3" placeholder="Inserta aquí tu código de Google AdSense o banner lateral"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="conf-afiliado-apuestas">Enlace de Afiliado Global para Apuestas</label>
                            <input type="url" name="afiliado_apuestas_url" id="conf-afiliado-apuestas" class="form-control" placeholder="https://ejemplo.com/afiliado-apuestas">
                        </div>

                        <div class="form-group">
                            <label for="conf-afiliado-camisetas">Enlace de Afiliado Global para Merchandising / Camisetas</label>
                            <input type="url" name="afiliado_camisetas_url" id="conf-afiliado-camisetas" class="form-control" placeholder="https://ejemplo.com/afiliado-camisetas">
                        </div>

                        <button type="submit" class="btn-premium" style="max-width:250px;">Guardar Configuración</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cargar JS de administración -->
    <?php if (isset($_SESSION['admin_logged_in'])): ?>
        <script src="admin.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>
</body>
</html>
