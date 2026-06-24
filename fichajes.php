<?php
// fichajes.php - Portal del Mercado de Fichajes de las 5 Grandes Ligas (SEO y Rumores)
require_once __DIR__ . '/db.php';

// Cargar configuración de publicidad y afiliados
$banner_header = '<div class="banner-placeholder">PUBLICIDAD SUPERIOR (728x90)</div>';
$banner_sidebar = '<div class="banner-placeholder">PUBLICIDAD LATERAL (300x250)</div>';
$afiliado_apuestas = 'https://www.google.com';
$afiliado_camisetas = 'https://www.amazon.com';

try {
    $stmt = $pdo->query("SELECT * FROM configuracion");
    foreach ($stmt->fetchAll() as $row) {
        if ($row['clave'] === 'banner_header' && !empty($row['valor'])) $banner_header = $row['valor'];
        if ($row['clave'] === 'banner_sidebar' && !empty($row['valor'])) $banner_sidebar = $row['valor'];
        if ($row['clave'] === 'afiliado_apuestas_url' && !empty($row['valor'])) $afiliado_apuestas = $row['valor'];
        if ($row['clave'] === 'afiliado_camisetas_url' && !empty($row['valor'])) $afiliado_camisetas = $row['valor'];
    }
} catch (Exception $e) {
    // Silencioso
}

// Obtener todos los rumores y fichajes recientes
$fichajes = [];
try {
    $fichajes = $pdo->query("SELECT * FROM noticias WHERE tipo = 'fichaje' ORDER BY fecha_creacion DESC")->fetchAll();
} catch (Exception $e) {
    // Silencioso
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercado de Fichajes en Vivo - Rumores y Traspasos de Fútbol</title>
    <meta name="description" content="Sigue el mercado de fichajes de fútbol en tiempo real. Rumores, cesiones, acuerdos y confirmaciones de traspaso de las 5 grandes ligas de Europa.">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Header & Navegación -->
    <header>
        <div class="nav-container">
            <a href="index.php" class="logo-section" style="text-decoration: none;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                    <path d="M2 12h20"></path>
                </svg>
                <h1 style="color:#ffffff; font-weight:800; margin:0;">5 LIGAS</h1>
            </a>
            
            <nav class="main-nav">
                <a href="index.php" class="nav-btn" style="text-decoration:none;">Inicio</a>
                <a href="liga.php?id=esp.1" class="nav-btn" style="text-decoration:none;">LaLiga</a>
                <a href="liga.php?id=eng.1" class="nav-btn" style="text-decoration:none;">Premier</a>
                <a href="liga.php?id=ita.1" class="nav-btn" style="text-decoration:none;">Serie A</a>
                <a href="liga.php?id=ger.1" class="nav-btn" style="text-decoration:none;">Bundesliga</a>
                <a href="liga.php?id=fra.1" class="nav-btn" style="text-decoration:none;">Ligue 1</a>
                <a href="fichajes.php" class="nav-btn active" style="text-decoration:none;">Fichajes</a>
            </nav>
        </div>
    </header>

    <main>
        <!-- Banner Publicitario Superior -->
        <div class="banner-wrapper">
            <?php echo $banner_header; ?>
        </div>

        <div class="main-layout">
            
            <!-- Columna Izquierda (Lista de rumores y traspasos) -->
            <div class="main-content">
                <h1 style="font-size:1.8rem; font-weight:800; color:#ffffff; margin-bottom:10px;">Mercado de Fichajes y Rumores en Vivo</h1>
                <p style="color:var(--text-secondary); margin-bottom:30px;">Entérate antes que nadie de las altas, bajas, renovaciones y cotilleos más calientes del mercado europeo de fútbol en directo.</p>
                
                <div style="display:flex; flex-direction:column; gap:25px;">
                    <?php if (empty($fichajes)): ?>
                        <!-- Plantilla mock inicial de fichaje si la base de datos está vacía -->
                        <div class="fichaje-card" id="ejemplo-fichaje" style="background:var(--bg-card); border:1px solid var(--border-glass); border-radius:16px; padding:25px; box-shadow:var(--shadow-premium);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                <span class="news-badge badge-fichaje" style="margin-bottom:0;">MERCADO EN VIVO</span>
                                <span style="font-size:0.75rem; color:var(--text-secondary);">Hoy, hace pocas horas</span>
                            </div>
                            <h3 style="font-size:1.3rem; font-weight:700; color:#ffffff; margin-bottom:12px;">El Chelsea negocia el traspaso de una estrella de la Bundesliga</h3>
                            <p style="font-size:0.95rem; line-height:1.7; color:#cbd5e1; margin-bottom:20px;">
                                Diversas fuentes informan que el club londinense ha iniciado contactos formales con los agentes del jugador para cerrar el acuerdo antes del cierre del periodo de inscripciones. Las negociaciones están bien encaminadas y se espera una oferta oficial en los próximos días.
                            </p>
                            <div style="border-top:1px solid rgba(255,255,255,0.05); padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:0.8rem; color:#64748b;">Liga de procedencia: Alemania</span>
                                <a href="<?php echo htmlspecialchars($afiliado_camisetas); ?>" target="_blank" rel="nofollow noopener" style="color:var(--primary-color); font-weight:700; text-decoration:none; font-size:0.85rem;">Comprar equipamiento oficial →</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($fichajes as $f): ?>
                            <div class="fichaje-card" id="<?php echo htmlspecialchars($f['slug']); ?>" style="background:var(--bg-card); border:1px solid var(--border-glass); border-radius:16px; padding:25px; box-shadow:var(--shadow-premium);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                    <span class="news-badge badge-fichaje" style="margin-bottom:0;">MERCADO EN VIVO</span>
                                    <span style="font-size:0.75rem; color:var(--text-secondary);"><?php echo date('d/m/Y H:i', strtotime($f['fecha_creacion'])); ?></span>
                                </div>
                                <h3 style="font-size:1.3rem; font-weight:700; color:#ffffff; margin-bottom:12px;"><?php echo htmlspecialchars($f['titulo']); ?></h3>
                                <div style="font-size:0.95rem; line-height:1.7; color:#cbd5e1; margin-bottom:20px;">
                                    <?php echo nl2br($f['contenido']); ?>
                                </div>
                                <div style="border-top:1px solid rgba(255,255,255,0.05); padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:0.8rem; color:#64748b;">Etiqueta: Rumores de Europa</span>
                                    <?php if (!empty($f['enlace_afiliado'])): ?>
                                        <a href="<?php echo htmlspecialchars($f['enlace_afiliado']); ?>" target="_blank" rel="nofollow noopener" style="color:var(--primary-color); font-weight:700; text-decoration:none; font-size:0.85rem;">Enlace Especial de Fichajes →</a>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($afiliado_camisetas); ?>" target="_blank" rel="nofollow noopener" style="color:var(--primary-color); font-weight:700; text-decoration:none; font-size:0.85rem;">Equipación en Oferta →</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Columna Barra Lateral (Derecha) -->
            <div class="sidebar-layout">
                <div class="sticky-sidebar">
                    
                    <!-- Banner Publicitario Sidebar -->
                    <div class="banner-sidebar-wrapper">
                        <?php echo $banner_sidebar; ?>
                    </div>

                    <!-- Enlace Afiliado Apuestas Widget -->
                    <div class="betting-widget">
                        <span class="betting-widget-title">🔥 APUESTAS DE FICHAJES</span>
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:15px;">¿Dónde jugará el próximo Balón de Oro? Apuesta por sus posibles destinos en el mercado de fichajes con las cuotas especiales de nuestro socio de afiliados.</p>
                        <a href="<?php echo htmlspecialchars($afiliado_apuestas); ?>" target="_blank" rel="nofollow noopener" class="btn-bet-now">Apuestas Especiales Fichajes</a>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align:center; padding:40px 20px; border-top:1px solid var(--border-glass); margin-top:60px; color:#64748b; font-size:0.85rem;">
        <p>&copy; <?php echo date('Y'); ?> 5 Ligas Europa. Todos los derechos reservados.</p>
        <p style="margin-top:10px; font-size:0.75rem;">🔞 +18 Jugar con responsabilidad. La información de rumores recopila especulaciones de prensa especializada.</p>
    </footer>

    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
