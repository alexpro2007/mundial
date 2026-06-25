<?php
// fichajes.php - Portal del Mercado de Fichajes de las 5 Grandes Ligas (SEO y Rumores)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

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
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(__('fichajes_meta_title')); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(__('fichajes_meta_desc')); ?>">
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
                <a href="index.php" class="nav-btn" style="text-decoration:none;"><?php echo htmlspecialchars(__('nav_inicio')); ?></a>
                <a href="liga.php?id=esp.1" class="nav-btn" style="text-decoration:none;">LaLiga</a>
                <a href="liga.php?id=eng.1" class="nav-btn" style="text-decoration:none;">Premier</a>
                <a href="liga.php?id=ita.1" class="nav-btn" style="text-decoration:none;">Serie A</a>
                <a href="liga.php?id=ger.1" class="nav-btn" style="text-decoration:none;">Bundesliga</a>
                <a href="liga.php?id=fra.1" class="nav-btn" style="text-decoration:none;">Ligue 1</a>
                <a href="liga.php?id=fifa.world" class="nav-btn" style="text-decoration:none;"><?php echo htmlspecialchars(__('nav_mundial')); ?></a>
                <a href="fichajes.php" class="nav-btn active" style="text-decoration:none;"><?php echo htmlspecialchars(__('nav_fichajes')); ?></a>
            </nav>
            <?php renderLanguageSelector(); ?>
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
                <h1 style="font-size:1.8rem; font-weight:800; color:#ffffff; margin-bottom:10px;"><?php echo htmlspecialchars(__('fichajes_h1')); ?></h1>
                <p style="color:var(--text-secondary); margin-bottom:30px;"><?php echo htmlspecialchars(__('fichajes_subtitle')); ?></p>
                
                <div style="display:flex; flex-direction:column; gap:25px;">
                    <?php if (empty($fichajes)): ?>
                        <!-- Plantilla mock inicial de fichaje si la base de datos está vacía -->
                        <div class="fichaje-card" id="ejemplo-fichaje" style="background:var(--bg-card); border:1px solid var(--border-glass); border-radius:16px; padding:25px; box-shadow:var(--shadow-premium);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                <span class="news-badge badge-fichaje" style="margin-bottom:0;"><?php echo htmlspecialchars(__('market_live')); ?></span>
                                <span style="font-size:0.75rem; color:var(--text-secondary);"><?php echo htmlspecialchars(__('today_few_hours')); ?></span>
                            </div>
                            <h3 style="font-size:1.3rem; font-weight:700; color:#ffffff; margin-bottom:12px;"><?php echo htmlspecialchars(__('mock_fichaje_title')); ?></h3>
                            <p style="font-size:0.95rem; line-height:1.7; color:#cbd5e1; margin-bottom:20px;">
                                <?php echo htmlspecialchars(__('mock_fichaje_desc')); ?>
                            </p>
                            <div style="border-top:1px solid rgba(255,255,255,0.05); padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:0.8rem; color:#64748b;"><?php echo htmlspecialchars(__('mock_fichaje_league')); ?></span>
                                <a href="<?php echo htmlspecialchars($afiliado_camisetas); ?>" target="_blank" rel="nofollow noopener" style="color:var(--primary-color); font-weight:700; text-decoration:none; font-size:0.85rem;"><?php echo htmlspecialchars(__('buy_shirt')); ?> →</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($fichajes as $f): ?>
                            <?php 
                            $is_visual_card = !empty($f['foto_jugador']) && !empty($f['equipo_destino_nombre']);
                            if ($is_visual_card): 
                            ?>
                                <div class="fichaje-visual-card" id="<?php echo htmlspecialchars($f['slug']); ?>">
                                    <span class="visual-badge"><?php echo htmlspecialchars(__('market_real')); ?></span>
                                    
                                    <!-- Player Name/Headline -->
                                    <h3 class="visual-player-name"><?php echo htmlspecialchars($f['titulo']); ?></h3>
                                    
                                    <!-- Player Photo -->
                                    <div class="visual-player-photo-wrapper">
                                        <img src="<?php echo htmlspecialchars($f['foto_jugador']); ?>" class="visual-player-photo" alt="<?php echo htmlspecialchars($f['titulo']); ?>" onerror="this.style.display='none';">
                                    </div>
                                    
                                    <!-- Transfer Details -->
                                    <div class="visual-transfer-row">
                                        <!-- Former Club -->
                                        <div class="visual-team-column">
                                            <div style="height: 55px; display: flex; align-items: center; justify-content: center;">
                                                <?php if (!empty($f['equipo_origen_logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($f['equipo_origen_logo']); ?>" class="visual-team-logo-large" alt="" onerror="this.style.display='none';">
                                                <?php else: ?>
                                                    <div style="font-size:1.5rem;">⚽</div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="visual-team-name-label"><?php echo htmlspecialchars($f['equipo_origen_nombre'] ?: 'S/E'); ?></span>
                                        </div>
                                        
                                        <!-- Arrow -->
                                        <div class="visual-arrow-wrapper">
                                            <svg class="visual-arrow-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                                <polyline points="12 5 19 12 12 19"></polyline>
                                            </svg>
                                        </div>
                                        
                                        <!-- New Club -->
                                        <div class="visual-team-column destination">
                                            <div style="height: 55px; display: flex; align-items: center; justify-content: center;">
                                                <img src="<?php echo htmlspecialchars($f['equipo_destino_logo']); ?>" class="visual-team-logo-large" alt="" onerror="this.style.display='none';">
                                            </div>
                                            <span class="visual-team-name-label"><?php echo htmlspecialchars($f['equipo_destino_nombre']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Contract duration -->
                                    <div class="visual-contract-details">
                                        <?php 
                                        $detalles = $f['detalles_contrato'];
                                        if (empty($detalles) || strtoupper($detalles) === 'OFICIAL') {
                                            echo htmlspecialchars(__('official')); 
                                        } else {
                                            echo htmlspecialchars($detalles);
                                        }
                                        ?>
                                    </div>
                                    
                                    <?php if (!empty($f['contenido']) && trim($f['contenido']) !== trim($f['titulo'])): ?>
                                        <div class="visual-card-description">
                                            <?php echo nl2br(htmlspecialchars($f['contenido'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Afiliados -->
                                    <div style="margin-top: 15px; text-align: right; border-top:1px solid rgba(255,255,255,0.05); padding-top:10px;">
                                        <?php if (!empty($f['enlace_afiliado'])): ?>
                                            <a href="<?php echo htmlspecialchars($f['enlace_afiliado']); ?>" target="_blank" rel="nofollow noopener" style="color:var(--primary-color); font-weight:700; text-decoration:none; font-size:0.8rem;"><?php echo htmlspecialchars(__('read_original')); ?></a>
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($afiliado_camisetas); ?>" target="_blank" rel="nofollow noopener" style="color:var(--primary-color); font-weight:700; text-decoration:none; font-size:0.8rem;"><?php echo htmlspecialchars(__('official_shirt')); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Tarjeta Normal (Texto/Imagen simple) -->
                                <div class="fichaje-card" id="<?php echo htmlspecialchars($f['slug']); ?>" style="background:var(--bg-card); border:1px solid var(--border-glass); border-radius:16px; padding:25px; box-shadow:var(--shadow-premium);">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                        <span class="news-badge badge-fichaje" style="margin-bottom:0;"><?php echo htmlspecialchars(__('market_live')); ?></span>
                                        <span style="font-size:0.75rem; color:var(--text-secondary);"><?php echo date('d/m/Y H:i', strtotime($f['fecha_creacion'])); ?></span>
                                    </div>
                                    <?php if (!empty($f['foto_jugador'])): ?>
                                        <!-- Foto de Noticia a lo ancho si tiene imagen general -->
                                        <div style="border-radius:12px; overflow:hidden; margin-bottom:15px; max-height:220px;">
                                            <img src="<?php echo htmlspecialchars($f['foto_jugador']); ?>" style="width:100%; height:100%; object-fit:cover;" alt="">
                                        </div>
                                    <?php endif; ?>
                                    <h3 style="font-size:1.3rem; font-weight:700; color:#ffffff; margin-bottom:12px;"><?php echo htmlspecialchars($f['titulo']); ?></h3>
                                    <div style="font-size:0.95rem; line-height:1.7; color:#cbd5e1; margin-bottom:20px;">
                                        <?php echo nl2br($f['contenido']); ?>
                                    </div>
                                    <div style="border-top:1px solid rgba(255,255,255,0.05); padding-top:15px; display:flex; justify-content:space-between; align-items:center;">
                                        <span style="font-size:0.8rem; color:#64748b;"><?php echo htmlspecialchars(__('tag_rumors')); ?></span>
                                        <?php if (!empty($f['enlace_afiliado'])): ?>
                                            <a href="<?php echo htmlspecialchars($f['enlace_afiliado']); ?>" target="_blank" rel="nofollow noopener" style="color:var(--primary-color); font-weight:700; text-decoration:none; font-size:0.85rem;"><?php echo htmlspecialchars(__('special_transfer_link')); ?></a>
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($afiliado_camisetas); ?>" target="_blank" rel="nofollow noopener" style="color:var(--primary-color); font-weight:700; text-decoration:none; font-size:0.85rem;"><?php echo htmlspecialchars(__('shirt_on_sale')); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
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
                        <span class="betting-widget-title"><?php echo htmlspecialchars(__('sidebar_betting_title')); ?></span>
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:15px;"><?php echo htmlspecialchars(__('sidebar_betting_desc')); ?></p>
                        <a href="<?php echo htmlspecialchars($afiliado_apuestas); ?>" target="_blank" rel="nofollow noopener" class="btn-bet-now"><?php echo htmlspecialchars(__('sidebar_betting_button')); ?></a>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align:center; padding:40px 20px; border-top:1px solid var(--border-glass); margin-top:60px; color:#64748b; font-size:0.85rem;">
        <p>&copy; <?php echo date('Y'); ?> 5 Ligas Europa. <?php echo htmlspecialchars(__('footer_rights')); ?></p>
        <p style="margin-top:10px; font-size:0.75rem;"><?php echo htmlspecialchars(__('footer_fichajes_disclaimer')); ?></p>
    </footer>

    <?php renderJSTranslations(); ?>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
