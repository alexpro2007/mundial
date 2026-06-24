<?php
// index.php - Vista Principal (Dashboard) del Portal de las 5 Grandes Ligas
require_once __DIR__ . '/db.php';

// Cargar configuración de monetización
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

// Obtener partidos en vivo o recientes para el dashboard
$partidos_live = [];
$partidos_upcoming = [];
try {
    $partidos_live = $pdo->query("
        SELECT p.*, el.nombre AS local_nombre, el.logo_url AS local_logo, ev.nombre AS visitante_nombre, ev.logo_url AS visitante_logo, l.nombre AS liga_nombre
        FROM partidos p
        JOIN equipos el ON p.equipo_local_id = el.id
        JOIN equipos ev ON p.equipo_visitante_id = ev.id
        JOIN ligas l ON p.liga_id = l.id
        WHERE p.estado = 'en_vivo'
        ORDER BY p.fecha_hora ASC
    ")->fetchAll();

    $partidos_upcoming = $pdo->query("
        SELECT p.*, el.nombre AS local_nombre, el.logo_url AS local_logo, ev.nombre AS visitante_nombre, ev.logo_url AS visitante_logo, l.nombre AS liga_nombre
        FROM partidos p
        JOIN equipos el ON p.equipo_local_id = el.id
        JOIN equipos ev ON p.equipo_visitante_id = ev.id
        JOIN ligas l ON p.liga_id = l.id
        WHERE p.estado = 'pendiente' AND p.fecha_hora >= NOW()
        ORDER BY p.fecha_hora ASC
        LIMIT 6
    ")->fetchAll();

    if (empty($partidos_upcoming)) {
        // Fallback si no hay partidos futuros cargados: listar últimos finalizados o pendientes generales
        $partidos_upcoming = $pdo->query("
            SELECT p.*, el.nombre AS local_nombre, el.logo_url AS local_logo, ev.nombre AS visitante_nombre, ev.logo_url AS visitante_logo, l.nombre AS liga_nombre
            FROM partidos p
            JOIN equipos el ON p.equipo_local_id = el.id
            JOIN equipos ev ON p.equipo_visitante_id = ev.id
            JOIN ligas l ON p.liga_id = l.id
            ORDER BY p.fecha_hora DESC
            LIMIT 6
        ")->fetchAll();
    }
} catch (Exception $e) {
    // Silencioso
}

// Obtener artículos de noticias recientes (fichajes y pronósticos)
$articulos = [];
try {
    $articulos = $pdo->query("SELECT * FROM noticias ORDER BY fecha_creacion DESC LIMIT 6")->fetchAll();
} catch (Exception $e) {
    // Silencioso
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>5 Grandes Ligas - Marcadores en Vivo, Apuestas y Fichajes</title>
    <meta name="description" content="Sigue los marcadores en vivo de la Premier League, LaLiga, Serie A, Bundesliga y Ligue 1. Previas de apuestas optimizadas y rumores de fichajes actualizados.">
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
                <a href="index.php" class="nav-btn active" style="text-decoration:none;">Inicio</a>
                <a href="liga.php?id=esp.1" class="nav-btn" style="text-decoration:none;">LaLiga</a>
                <a href="liga.php?id=eng.1" class="nav-btn" style="text-decoration:none;">Premier</a>
                <a href="liga.php?id=ita.1" class="nav-btn" style="text-decoration:none;">Serie A</a>
                <a href="liga.php?id=ger.1" class="nav-btn" style="text-decoration:none;">Bundesliga</a>
                <a href="liga.php?id=fra.1" class="nav-btn" style="text-decoration:none;">Ligue 1</a>
                <a href="liga.php?id=fifa.world" class="nav-btn" style="text-decoration:none;">Mundial</a>
                <a href="fichajes.php" class="nav-btn" style="text-decoration:none;">Fichajes</a>
            </nav>
        </div>
    </header>

    <main>
        <!-- Banner de Publicidad Adaptativo Superior -->
        <div class="banner-wrapper">
            <?php echo $banner_header; ?>
        </div>

        <!-- Layout de dos columnas -->
        <div class="main-layout">
            
            <!-- Contenido Principal (Izquierda) -->
            <div class="main-content">
                
                <!-- Cuenta Regresiva de Próximo Partido de Interés -->
                <div class="countdown-glow-wrapper" style="margin-bottom: 30px;">
                    <div class="countdown-container-large">
                        <div class="countdown-title-large">
                            <span class="live-dot-indicator"></span> CUENTA ATRÁS PARA EL PRÓXIMO PARTIDO
                        </div>
                        <div class="timer-large">
                            <div class="time-block-large">
                                <span class="time-num" id="cd-days">00</span>
                                <span class="time-label">Días</span>
                            </div>
                            <div class="timer-separator">:</div>
                            <div class="time-block-large">
                                <span class="time-num" id="cd-hours">00</span>
                                <span class="time-label">Horas</span>
                            </div>
                            <div class="timer-separator">:</div>
                            <div class="time-block-large">
                                <span class="time-num" id="cd-mins">00</span>
                                <span class="time-label">Minutos</span>
                            </div>
                            <div class="timer-separator">:</div>
                            <div class="time-block-large">
                                <span class="time-num" id="cd-secs">00</span>
                                <span class="time-label">Segundos</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Partidos en Vivo (Si los hay) -->
                <?php if (!empty($partidos_live)): ?>
                    <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 20px; border-left: 4px solid var(--accent-live); padding-left: 10px;">PARTIDOS EN VIVO</h2>
                    <div class="matches-grid" style="display:flex; flex-direction:column; gap:15px; margin-bottom: 40px;">
                        <?php foreach ($partidos_live as $p): ?>
                            <div class="match-card-clickable" data-match-id="<?php echo $p['id']; ?>" style="cursor:pointer; background:rgba(16, 24, 40, 0.7); border:1px solid rgba(16, 185, 129, 0.3); border-radius:12px; padding:15px; display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:0.75rem; font-weight:800; color:var(--accent-live);"><?php echo htmlspecialchars($p['liga_nombre']); ?></div>
                                <div style="display:flex; align-items:center; gap:15px; flex:1; justify-content:center;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="font-weight:600;"><?php echo htmlspecialchars($p['local_nombre']); ?></span>
                                        <img src="<?php echo htmlspecialchars($p['local_logo']); ?>" style="width:24px; height:24px; object-fit:contain;" alt="">
                                    </div>
                                    <span class="match-score-live" style="font-weight:800; font-size:1.2rem; color:var(--accent-live);"><?php echo $p['goles_local']; ?> - <?php echo $p['goles_visitante']; ?></span>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <img src="<?php echo htmlspecialchars($p['visitante_logo']); ?>" style="width:24px; height:24px; object-fit:contain;" alt="">
                                        <span style="font-weight:600;"><?php echo htmlspecialchars($p['visitante_nombre']); ?></span>
                                    </div>
                                </div>
                                <span class="badge-live-pulse" style="font-size:0.75rem; background:rgba(16, 185, 129, 0.1); color:var(--accent-live); padding:4px 8px; border-radius:6px; font-weight:700;">LIVE <?php echo $p['minuto_actual']; ?>'</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Agenda de Partidos y Resultados Recientes -->
                <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 20px; border-left: 4px solid var(--accent-blue); padding-left: 10px;">PARTIDOS DESTACADOS</h2>
                <div class="matches-list-container" style="display:flex; flex-direction:column; gap:15px; margin-bottom: 40px;">
                    <?php if (empty($partidos_upcoming)): ?>
                        <div style="color:var(--text-secondary); text-align:center; padding:20px;">No hay partidos cargados actualmente. Por favor sincroniza desde el panel administrativo.</div>
                    <?php else: ?>
                        <?php foreach ($partidos_upcoming as $p): ?>
                            <?php 
                            $dateObj = new DateTIme($p['fecha_hora']);
                            $state_label = '';
                            if ($p['estado'] === 'pendiente') {
                                $state_label = $dateObj->format('d/m H:i');
                            } elseif ($p['estado'] === 'finalizado') {
                                $state_label = 'Finalizado';
                            }
                            ?>
                            <div class="match-card-clickable" data-match-id="<?php echo $p['id']; ?>" style="cursor:pointer; background:rgba(255, 255, 255, 0.02); border:1px solid var(--border-glass); border-radius:12px; padding:15px; display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:0.75rem; font-weight:700; color:var(--text-secondary); width:120px;"><?php echo htmlspecialchars($p['liga_nombre']); ?></div>
                                <div style="display:flex; align-items:center; gap:15px; flex:1; justify-content:center;">
                                    <div style="display:flex; align-items:center; gap:8px; width:40%; justify-content:flex-end;">
                                        <span style="font-weight:600; font-size:0.9rem; text-align:right;"><?php echo htmlspecialchars($p['local_nombre']); ?></span>
                                        <img src="<?php echo htmlspecialchars($p['local_logo']); ?>" style="width:24px; height:24px; object-fit:contain;" alt="">
                                    </div>
                                    <span style="font-weight:800; font-size:1.1rem; min-width:60px; text-align:center;">
                                        <?php if ($p['estado'] === 'pendiente'): ?>
                                            VS
                                        <?php else: ?>
                                            <?php echo $p['goles_local']; ?> - <?php echo $p['goles_visitante']; ?>
                                        <?php endif; ?>
                                    </span>
                                    <div style="display:flex; align-items:center; gap:8px; width:40%; justify-content:flex-start;">
                                        <img src="<?php echo htmlspecialchars($p['visitante_logo']); ?>" style="width:24px; height:24px; object-fit:contain;" alt="">
                                        <span style="font-weight:600; font-size:0.9rem; text-align:left;"><?php echo htmlspecialchars($p['visitante_nombre']); ?></span>
                                    </div>
                                </div>
                                <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; width:80px; text-align:right;"><?php echo $state_label; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Sección de Noticias / Previa y Pronósticos -->
                <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 20px; border-left: 4px solid var(--primary-color); padding-left: 10px;">ANÁLISIS Y PRONÓSTICOS DESTACADOS</h2>
                <div class="news-grid">
                    <?php if (empty($articulos)): ?>
                        <!-- Plantillas mock de SEO y afiliados si no hay noticias insertadas todavía -->
                        <div class="news-card">
                            <span class="news-badge badge-pronostico">Pronóstico</span>
                            <h3 class="news-card-title">Real Madrid vs Barcelona: Pronóstico, apuestas y previa de El Clásico</h3>
                            <p class="news-card-excerpt">Analizamos el gran duelo de LaLiga en el Santiago Bernabéu. Claves tácticas, bajas de última hora y las mejores cuotas de afiliado para apostar seguro.</p>
                            <div class="news-card-footer">
                                <span>Hace 1 día</span>
                                <a href="pronosticos.php" class="btn-read-more">Previa y Cuotas →</a>
                            </div>
                        </div>
                        <div class="news-card">
                            <span class="news-badge badge-fichaje">Fichaje</span>
                            <h3 class="news-card-title">Mercado en vivo: Rumores de traspasos, altas y bajas de las grandes ligas</h3>
                            <p class="news-card-excerpt">Entérate de las últimas novedades sobre los fichajes más sonados de la Premier League, LaLiga y Serie A. Última hora directo del mercado europeo.</p>
                            <div class="news-card-footer">
                                <span>Hace 2 horas</span>
                                <a href="fichajes.php" class="btn-read-more">Ver Fichajes →</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($articulos as $art): ?>
                            <?php 
                            $is_visual_card = $art['tipo'] === 'fichaje' && !empty($art['foto_jugador']) && !empty($art['equipo_destino_nombre']);
                            if ($is_visual_card): 
                            ?>
                                <div class="fichaje-visual-card" id="<?php echo htmlspecialchars($art['slug']); ?>" style="margin-bottom: 20px;">
                                    <span class="visual-badge">MERCADO REAL</span>
                                    <h3 class="visual-player-name" style="font-size:1.15rem; margin-bottom:12px;"><?php echo htmlspecialchars($art['titulo']); ?></h3>
                                    
                                    <div class="visual-player-photo-wrapper" style="width:80px; height:80px; margin-bottom:15px;">
                                        <img src="<?php echo htmlspecialchars($art['foto_jugador']); ?>" class="visual-player-photo" alt="" onerror="this.style.display='none';">
                                    </div>
                                    
                                    <div class="visual-transfer-row" style="padding:10px 6px; margin-bottom:15px; gap:10px;">
                                        <div class="visual-team-column">
                                            <div style="height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <?php if (!empty($art['equipo_origen_logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($art['equipo_origen_logo']); ?>" class="visual-team-logo-large" style="height:35px; width:35px;" alt="" onerror="this.style.display='none';">
                                                <?php else: ?>
                                                    <div style="font-size:1rem;">⚽</div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="visual-team-name-label" style="font-size:0.65rem;"><?php echo htmlspecialchars($art['equipo_origen_nombre'] ?: 'S/E'); ?></span>
                                        </div>
                                        
                                        <div class="visual-arrow-wrapper" style="width:20px; height:20px;">
                                            <svg class="visual-arrow-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                                <polyline points="12 5 19 12 12 19"></polyline>
                                            </svg>
                                        </div>
                                        
                                        <div class="visual-team-column destination">
                                            <div style="height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <img src="<?php echo htmlspecialchars($art['equipo_destino_logo']); ?>" class="visual-team-logo-large" style="height:35px; width:35px;" alt="" onerror="this.style.display='none';">
                                            </div>
                                            <span class="visual-team-name-label" style="font-size:0.65rem;"><?php echo htmlspecialchars($art['equipo_destino_nombre']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="visual-contract-details" style="font-size:0.8rem; padding-top:10px; margin-top:10px;">
                                        <?php echo htmlspecialchars($art['detalles_contrato'] ?: 'OFICIAL'); ?>
                                    </div>
                                    
                                    <div style="margin-top: 12px; text-align: right; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px;">
                                        <a href="fichajes.php#<?php echo $art['slug']; ?>" class="btn-read-more" style="font-size:0.75rem;">Detalles →</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php 
                                $badge_class = $art['tipo'] === 'pronostico' ? 'badge-pronostico' : 'badge-fichaje';
                                $label = $art['tipo'] === 'pronostico' ? 'Pronóstico' : 'Fichaje';
                                $link = $art['tipo'] === 'pronostico' ? "pronosticos.php?slug={$art['slug']}" : "fichajes.php#{$art['slug']}";
                                ?>
                                <div class="news-card">
                                    <span class="news-badge <?php echo $badge_class; ?>"><?php echo $label; ?></span>
                                    <?php if ($art['tipo'] === 'fichaje' && !empty($art['foto_jugador'])): ?>
                                        <div style="border-radius:8px; overflow:hidden; margin-bottom:12px; max-height:140px; background:#000;">
                                            <img src="<?php echo htmlspecialchars($art['foto_jugador']); ?>" style="width:100%; height:100%; object-fit:cover; opacity:0.85;" alt="">
                                        </div>
                                    <?php endif; ?>
                                    <h3 class="news-card-title"><?php echo htmlspecialchars($art['titulo']); ?></h3>
                                    <p class="news-card-excerpt"><?php echo strip_tags($art['contenido']); ?></p>
                                    <div class="news-card-footer">
                                        <span><?php echo date('d/m/Y', strtotime($art['fecha_creacion'])); ?></span>
                                        <a href="<?php echo $link; ?>" class="btn-read-more">Leer Más →</a>
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
                    
                    <!-- Banner de Publicidad Lateral -->
                    <div class="banner-sidebar-wrapper">
                        <?php echo $banner_sidebar; ?>
                    </div>

                    <!-- Caja de Afiliado para apostar destacado -->
                    <div class="betting-widget">
                        <span class="betting-widget-title">🔥 APUESTA DE LA JORNADA</span>
                        <div class="betting-teams">
                            <span style="font-weight:700;">Top Partido Europeo</span>
                            <span style="color:var(--primary-color); font-weight:800; font-size:0.75rem; background:rgba(255,215,0,0.1); padding:2px 6px; border-radius:4px;">100% SEGURO</span>
                        </div>
                        <div class="betting-odds-row">
                            <div class="bet-option">
                                <span class="bet-label">Local</span>
                                <span class="bet-value">2.15</span>
                            </div>
                            <div class="bet-option">
                                <span class="bet-label">Empate</span>
                                <span class="bet-value">3.40</span>
                            </div>
                            <div class="bet-option">
                                <span class="bet-label">Visita</span>
                                <span class="bet-value">3.10</span>
                            </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($afiliado_apuestas); ?>" target="_blank" rel="nofollow noopener" class="btn-bet-now">Apostar con Bono →</a>
                    </div>

                    <!-- Enlace a Tienda / Camisetas -->
                    <div class="betting-widget" style="border-color: rgba(14, 165, 233, 0.2);">
                        <span class="betting-widget-title" style="color: var(--accent-blue);">🛒 TIENDA DE FÚTBOL</span>
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom: 15px;">Consigue las camisetas oficiales de tus equipos favoritos de las 5 grandes ligas de Europa con descuentos increíbles de hasta el 30%.</p>
                        <a href="<?php echo htmlspecialchars($afiliado_camisetas); ?>" target="_blank" rel="nofollow noopener" class="btn-bet-now" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color:#ffffff; box-shadow: 0 4px 15px rgba(14, 165, 233, 0.2);">Ver Camisetas en Oferta</a>
                    </div>

                    <!-- Juego Responsable -->
                    <div style="font-size:0.7rem; color:#64748b; text-align:center; padding:15px; border-radius:10px; background:rgba(255,255,255,0.01); border:1px solid rgba(255,255,255,0.03);">
                        🔞 <strong>+18 JUEGO RESPONSABLE</strong><br>
                        Las apuestas deportivas conllevan riesgos financieros. Juega con moderación y bajo tu propia responsabilidad.
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align:center; padding:40px 20px; border-top:1px solid var(--border-glass); margin-top:60px; color:#64748b; font-size:0.85rem;">
        <p>&copy; <?php echo date('Y'); ?> 5 Ligas Europa. Todos los derechos reservados.</p>
        <p style="margin-top:10px; font-size:0.75rem;">Diseñado con fines informativos y optimización SEO. Datos en vivo provistos por ESPN API. Todos los enlaces comerciales contienen etiquetas de afiliado.</p>
    </footer>

    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
