<?php
// pronosticos.php - Previas de Partidos y Pronósticos de Apuestas (Monetización y SEO)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

$slug = trim($_GET['slug'] ?? '');

$articulo = null;
if ($slug !== '') {
    // Buscar artículo específico
    $stmt = $pdo->prepare("SELECT * FROM noticias WHERE slug = ? AND tipo = 'pronostico'");
    $stmt->execute([$slug]);
    $articulo = $stmt->fetch();
}

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

// Si se encontró un artículo, usar su link de afiliado específico si lo tiene, si no el global
if ($articulo && !empty($articulo['enlace_afiliado'])) {
    $afiliado_apuestas = $articulo['enlace_afiliado'];
}

// Si no hay slug, listar todos los pronósticos disponibles para SEO crawling
$pronosticos_recientes = [];
if (!$articulo) {
    try {
        $pronosticos_recientes = $pdo->query("SELECT * FROM noticias WHERE tipo = 'pronostico' ORDER BY fecha_creacion DESC")->fetchAll();
    } catch (Exception $e) {
        // Silencioso
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($articulo): ?>
        <title><?php echo htmlspecialchars($articulo['titulo']); ?> - <?php echo htmlspecialchars(__('predictions')); ?></title>
        <meta name="description" content="<?php echo htmlspecialchars(mb_substr(strip_tags($articulo['contenido']), 0, 150) . '...'); ?>">
    <?php else: ?>
        <title><?php echo htmlspecialchars(__('pronosticos_meta_title')); ?></title>
        <meta name="description" content="<?php echo htmlspecialchars(__('pronosticos_meta_desc')); ?>">
    <?php endif; ?>
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
                <a href="fichajes.php" class="nav-btn" style="text-decoration:none;"><?php echo htmlspecialchars(__('nav_fichajes')); ?></a>
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
            
            <!-- Columna Izquierda (Artículo o Directorio de Pronósticos) -->
            <div class="main-content">
                
                <?php if ($articulo): ?>
                    <!-- VISTA: LECTURA DE UN PRONÓSTICO ESPECÍFICO -->
                    <article class="news-detail-card" style="background:var(--bg-card); border:1px solid var(--border-glass); border-radius:16px; padding:30px; box-shadow:var(--shadow-premium);">
                        <span class="news-badge badge-pronostico" style="margin-bottom:15px;"><?php echo htmlspecialchars(__('prediction_badge_long')); ?></span>
                        
                        <h1 style="font-size:2rem; font-weight:800; color:#ffffff; margin-bottom:15px; line-height:1.3;"><?php echo htmlspecialchars($articulo['titulo']); ?></h1>
                        
                        <div style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:25px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px; display:flex; gap:15px;">
                            <span><?php echo htmlspecialchars(__('pub_date')); ?> <?php echo date('d/m/Y H:i', strtotime($articulo['fecha_creacion'])); ?></span>
                            <span><?php echo htmlspecialchars(__('author_staff')); ?></span>
                        </div>
                        
                        <!-- Contenido enriquecido -->
                        <div class="article-body-content" style="font-size:1.05rem; line-height:1.8; color:#e2e8f0; margin-bottom:30px;">
                            <?php echo nl2br($articulo['contenido']); ?>
                        </div>

                        <!-- Widget de Apuestas integrado dentro de la lectura para máxima conversión -->
                        <div class="betting-widget" style="margin-top: 40px; border-color: var(--primary-color);">
                            <span class="betting-widget-title"><?php echo htmlspecialchars(__('odds_recommended_title')); ?></span>
                            <div class="betting-teams" style="margin-bottom:15px;">
                                <span style="font-weight:700; font-size:1.1rem;"><?php echo htmlspecialchars($articulo['titulo']); ?></span>
                            </div>
                            <div class="betting-odds-row">
                                <div class="bet-option">
                                    <span class="bet-label"><?php echo htmlspecialchars(__('home_win')); ?></span>
                                    <span class="bet-value">2.10</span>
                                </div>
                                <div class="bet-option">
                                    <span class="bet-label"><?php echo htmlspecialchars(__('draw')); ?></span>
                                    <span class="bet-value">3.40</span>
                                </div>
                                <div class="bet-option">
                                    <span class="bet-label"><?php echo htmlspecialchars(__('away_win')); ?></span>
                                    <span class="bet-value">3.20</span>
                                </div>
                            </div>
                            <a href="<?php echo htmlspecialchars($afiliado_apuestas); ?>" target="_blank" rel="nofollow noopener" class="btn-bet-now"><?php echo htmlspecialchars(__('bet_affiliate_link')); ?></a>
                        </div>
                    </article>

                    <div style="margin-top:30px;">
                        <a href="pronosticos.php" style="color:var(--primary-color); text-decoration:none; font-weight:700; display:flex; align-items:center; gap:6px;">
                            <?php echo htmlspecialchars(__('back_to_predictions')); ?>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- VISTA: DIRECTORIO DE PRONÓSTICOS Y ANÁLISIS (Para SEO) -->
                    <h1 style="font-size:1.8rem; font-weight:800; color:#ffffff; margin-bottom:10px;"><?php echo htmlspecialchars(__('pronosticos_h1')); ?></h1>
                    <p style="color:var(--text-secondary); margin-bottom:30px;"><?php echo htmlspecialchars(__('pronosticos_subtitle')); ?></p>
                    
                    <div class="news-grid">
                        <?php if (empty($pronosticos_recientes)): ?>
                            <div class="news-card">
                                <span class="news-badge badge-pronostico"><?php echo htmlspecialchars(__('prediction_badge')); ?></span>
                                <h3 class="news-card-title"><?php echo htmlspecialchars(__('mock_prediction_title')); ?></h3>
                                <p class="news-card-excerpt"><?php echo htmlspecialchars(__('mock_prediction_desc')); ?></p>
                                <div class="news-card-footer">
                                    <span><?php echo htmlspecialchars(__('mock_prediction_date')); ?></span>
                                    <a href="pronosticos.php?slug=ejemplo-real-madrid-vs-barcelona" class="btn-read-more"><?php echo htmlspecialchars(__('previa_and_odds')); ?></a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pronosticos_recientes as $art): ?>
                                <div class="news-card">
                                    <span class="news-badge badge-pronostico"><?php echo htmlspecialchars(__('prediction_badge')); ?></span>
                                    <h3 class="news-card-title"><?php echo htmlspecialchars($art['titulo']); ?></h3>
                                    <p class="news-card-excerpt"><?php echo strip_tags($art['contenido']); ?></p>
                                    <div class="news-card-footer">
                                        <span><?php echo date('d/m/Y', strtotime($art['fecha_creacion'])); ?></span>
                                        <a href="pronosticos.php?slug=<?php echo $art['slug']; ?>" class="btn-read-more"><?php echo htmlspecialchars(__('previa_and_odds')); ?></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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
                        <span class="betting-widget-title"><?php echo htmlspecialchars(__('sidebar_promo_title')); ?></span>
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:15px;"><?php echo htmlspecialchars(__('sidebar_promo_desc')); ?></p>
                        <a href="<?php echo htmlspecialchars($afiliado_apuestas); ?>" target="_blank" rel="nofollow noopener" class="btn-bet-now"><?php echo htmlspecialchars(__('sidebar_promo_button')); ?></a>
                        <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center; justify-content: center;">
                            <span style="display:inline-block; border: 1px solid var(--text-secondary); border-radius: 50%; width: 24px; height: 24px; line-height: 22px; text-align: center; font-weight: bold; color: var(--text-secondary); font-size: 0.6rem;">+18</span>
                            <a href="https://www.jugarbien.es" target="_blank" rel="noopener nofollow" style="color: var(--text-secondary); font-weight: bold; border: 1px solid var(--text-secondary); padding: 2px 6px; border-radius: 4px; text-decoration: none; font-size: 0.65rem;">JugarBien</a>
                        </div>
                    </div>
                    
                    <!-- Juego Responsable -->
                    <div style="font-size:0.7rem; color:#64748b; text-align:center; padding:15px; border-radius:10px; background:rgba(255,255,255,0.01); border:1px solid rgba(255,255,255,0.03);">
                        <?php echo __('sidebar_responsible_gaming'); ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer Centralizado -->
    <?php include __DIR__ . '/footer.php'; ?>

    <?php renderJSTranslations(); ?>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
