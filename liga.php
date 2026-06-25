<?php
// liga.php - Vista Detallada de una Liga Europea
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

$liga_id = $_GET['id'] ?? 'esp.1';

// Cargar información de la liga
$stmt = $pdo->prepare("SELECT * FROM ligas WHERE id = ?");
$stmt->execute([$liga_id]);
$liga = $stmt->fetch();

if (!$liga) {
    // Redirigir a LaLiga si no existe
    header('Location: liga.php?id=esp.1');
    exit;
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

// Obtener partidos de la liga
$partidos = [];
try {
    $partidos = $pdo->prepare("
        SELECT p.*, el.nombre AS local_nombre, el.logo_url AS local_logo, ev.nombre AS visitante_nombre, ev.logo_url AS visitante_logo
        FROM partidos p
        JOIN equipos el ON p.equipo_local_id = el.id
        JOIN equipos ev ON p.equipo_visitante_id = ev.id
        WHERE p.liga_id = ?
        ORDER BY p.fecha_hora ASC, p.id ASC
    ");
    $partidos->execute([$liga_id]);
    $partidos = $partidos->fetchAll();
} catch (Exception $e) {
    // Silencioso
}

// Obtener standings cached de la tabla de configuración
$standings = null;
try {
    $confStmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = ?");
    $confStmt->execute(["standings_{$liga_id}"]);
    $conf = $confStmt->fetch();
    if ($conf && !empty($conf['valor'])) {
        $standings = json_decode($conf['valor'], true);
    }
} catch (Exception $e) {
    // Silencioso
}

$season_displayName = $liga['nombre'];
if ($standings && isset($standings['season']['displayName'])) {
    $season_displayName = $standings['season']['displayName'];
} elseif ($standings && isset($standings['name'])) {
    $season_displayName = $standings['name'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clasificación y Resultados de <?php echo htmlspecialchars($liga['nombre']); ?> - Fútbol Europeo</title>
    <meta name="description" content="Sigue la clasificación actualizada, partidos en vivo, fixture y resultados de la <?php echo htmlspecialchars($liga['nombre']); ?> de <?php echo htmlspecialchars($liga['pais']); ?>.">
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
                <a href="liga.php?id=esp.1" class="nav-btn <?php echo $liga_id === 'esp.1' ? 'active' : ''; ?>" style="text-decoration:none;">LaLiga</a>
                <a href="liga.php?id=eng.1" class="nav-btn <?php echo $liga_id === 'eng.1' ? 'active' : ''; ?>" style="text-decoration:none;">Premier</a>
                <a href="liga.php?id=ita.1" class="nav-btn <?php echo $liga_id === 'ita.1' ? 'active' : ''; ?>" style="text-decoration:none;">Serie A</a>
                <a href="liga.php?id=ger.1" class="nav-btn <?php echo $liga_id === 'ger.1' ? 'active' : ''; ?>" style="text-decoration:none;">Bundesliga</a>
                <a href="liga.php?id=fra.1" class="nav-btn <?php echo $liga_id === 'fra.1' ? 'active' : ''; ?>" style="text-decoration:none;">Ligue 1</a>
                <a href="liga.php?id=fifa.world" class="nav-btn <?php echo $liga_id === 'fifa.world' ? 'active' : ''; ?>" style="text-decoration:none;"><?php echo htmlspecialchars(__('nav_mundial')); ?></a>
                <a href="fichajes.php" class="nav-btn" style="text-decoration:none;"><?php echo htmlspecialchars(__('nav_fichajes')); ?></a>
            </nav>
            <?php renderLanguageSelector(); ?>
        </div>
    </header>

    <main>
        <!-- Banner Publicidad Header -->
        <div class="banner-wrapper">
            <?php echo $banner_header; ?>
        </div>

        <div style="display:flex; align-items:center; gap:20px; margin-bottom:30px;">
            <img src="<?php echo htmlspecialchars($liga['logo_url']); ?>" style="height:80px; object-fit:contain;" alt="">
            <div>
                <h1 style="margin:0; font-size:2rem; font-weight:800; color:#ffffff;"><?php echo htmlspecialchars($season_displayName); ?></h1>
                <span style="font-size:1rem; color:var(--text-secondary);"><?php echo htmlspecialchars($liga['pais']); ?> | <?php echo htmlspecialchars(__('official_data')); ?></span>
            </div>
        </div>

        <div class="main-layout">
            
            <!-- Columna Izquierda (Tabla de clasificación y partidos) -->
            <div class="main-content">
                
                <!-- Tabla de Posiciones Cached -->
                <h2 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 20px; border-left: 4px solid var(--primary-color); padding-left: 10px;"><?php echo htmlspecialchars(__('standings_title')); ?></h2>
                <div class="standings-table-wrapper" style="margin-bottom: 40px;">
                    <?php if (!$standings || !isset($standings['children'])): ?>
                        <div style="color:var(--text-secondary); text-align:center; padding:20px;"><?php echo htmlspecialchars(__('no_data')); ?></div>
                    <?php else: ?>
                        <table class="standings-table">
                            <thead>
                                <tr>
                                    <th class="standings-rank"><?php echo htmlspecialchars(__('rank')); ?></th>
                                    <th><?php echo htmlspecialchars(__('team')); ?></th>
                                    <th style="text-align:center;"><?php echo htmlspecialchars(__('played')); ?></th>
                                    <th style="text-align:center;"><?php echo htmlspecialchars(__('wins')); ?></th>
                                    <th style="text-align:center;"><?php echo htmlspecialchars(__('draws')); ?></th>
                                    <th style="text-align:center;"><?php echo htmlspecialchars(__('losses')); ?></th>
                                    <th style="text-align:center;"><?php echo htmlspecialchars(__('goals_for')); ?></th>
                                    <th style="text-align:center;"><?php echo htmlspecialchars(__('goals_against')); ?></th>
                                    <th style="text-align:center;"><?php echo htmlspecialchars(__('goal_diff')); ?></th>
                                    <th style="text-align:center; color:var(--primary-color);"><?php echo htmlspecialchars(__('points')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $children = $standings['children'] ?? [];
                                $is_groups_tournament = count($children) > 1;
                                
                                foreach ($children as $child):
                                    $group_title = $child['name'] ?? '';
                                    $entries = $child['standings']['entries'] ?? [];
                                    if (empty($entries)) continue;

                                    // Sort entries by rank to prevent unsorted standings
                                    usort($entries, function($a, $b) {
                                        $rankA = 999;
                                        $rankB = 999;
                                        if (isset($a['stats'])) {
                                            foreach ($a['stats'] as $st) {
                                                if ($st['name'] === 'rank') {
                                                    $rankA = intval($st['value']);
                                                    break;
                                                }
                                            }
                                        }
                                        if (isset($b['stats'])) {
                                            foreach ($b['stats'] as $st) {
                                                if ($st['name'] === 'rank') {
                                                    $rankB = intval($st['value']);
                                                    break;
                                                }
                                            }
                                        }
                                        return $rankA <=> $rankB;
                                    });
                                ?>
                                    <?php if ($is_groups_tournament): ?>
                                        <tr class="group-header-row" style="background:rgba(255,215,0,0.04);">
                                            <td colspan="10" style="font-weight:800; color:var(--primary-color); padding: 12px 10px; font-size:1rem; border-bottom: 1px solid var(--border-glass);">
                                                <?php echo htmlspecialchars($group_title); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    foreach ($entries as $entry): 
                                        $teamName = $entry['team']['displayName'] ?? 'N/A';
                                        $teamLogo = $entry['team']['logos'][0]['href'] ?? '';
                                        $rank = 0; $gp = 0; $w = 0; $d = 0; $l = 0; $f = 0; $a = 0; $gd = 0; $pts = 0;
                                        
                                        foreach ($entry['stats'] as $st) {
                                            if ($st['name'] === 'rank') $rank = $st['value'];
                                            if ($st['name'] === 'gamesPlayed') $gp = $st['value'];
                                            if ($st['name'] === 'wins') $w = $st['value'];
                                            if ($st['name'] === 'ties') $d = $st['value'];
                                            if ($st['name'] === 'losses') $l = $st['value'];
                                            if ($st['name'] === 'pointsFor') $f = $st['value'];
                                            if ($st['name'] === 'pointsAgainst') $a = $st['value'];
                                            if ($st['name'] === 'pointDifferential') $gd = $st['displayValue'];
                                            if ($st['name'] === 'points') $pts = $st['value'];
                                        }
                                    ?>
                                        <tr>
                                            <td class="standings-rank"><?php echo $rank; ?></td>
                                            <td>
                                                <div class="standings-team-cell">
                                                    <?php if (!empty($teamLogo)): ?>
                                                        <img src="<?php echo htmlspecialchars($teamLogo); ?>" class="standings-team-logo" alt="">
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($teamName); ?></span>
                                                </div>
                                            </td>
                                            <td style="text-align:center;"><?php echo $gp; ?></td>
                                            <td style="text-align:center;"><?php echo $w; ?></td>
                                            <td style="text-align:center;"><?php echo $d; ?></td>
                                            <td style="text-align:center;"><?php echo $l; ?></td>
                                            <td style="text-align:center;"><?php echo $f; ?></td>
                                            <td style="text-align:center;"><?php echo $a; ?></td>
                                            <td style="text-align:center; color:<?php echo floatval($gd) >= 0 ? 'var(--accent-live)' : 'var(--accent-red)'; ?>;"><?php echo $gd; ?></td>
                                            <td style="text-align:center; font-weight:800; color:var(--primary-color);"><?php echo $pts; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Partidos de la Liga -->
                <h2 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 20px; border-left: 4px solid var(--accent-blue); padding-left: 10px;"><?php echo htmlspecialchars(__('fixtures_title')); ?></h2>
                <div class="matches-list-container" style="display:flex; flex-direction:column; gap:15px; margin-bottom: 40px;">
                    <?php if (empty($partidos)): ?>
                        <div style="color:var(--text-secondary); text-align:center; padding:20px;"><?php echo htmlspecialchars(__('no_data')); ?></div>
                    <?php else: ?>
                        <?php foreach ($partidos as $p): ?>
                            <?php 
                            $dateObj = new DateTime($p['fecha_hora']);
                            $state_label = '';
                            if ($p['estado'] === 'pendiente') {
                                $state_label = $dateObj->format('d/m H:i');
                            } elseif ($p['estado'] === 'en_vivo') {
                                $state_label = "<span class=\"badge-live-pulse\">LIVE Min {$p['minuto_actual']}'</span>";
                            } elseif ($p['estado'] === 'finalizado') {
                                $state_label = __('finished');
                            }
                            ?>
                            <div class="match-card-clickable" data-match-id="<?php echo $p['id']; ?>" style="cursor:pointer; background:rgba(255, 255, 255, 0.02); border:1px solid var(--border-glass); border-radius:12px; padding:15px; display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:0.75rem; font-weight:700; color:var(--text-secondary); width:120px;"><?php echo htmlspecialchars($p['fase']); ?></div>
                                <div style="display:flex; align-items:center; gap:15px; flex:1; justify-content:center;">
                                    <div style="display:flex; align-items:center; gap:8px; width:40%; justify-content:flex-end;">
                                        <span style="font-weight:600; font-size:0.9rem; text-align:right;"><?php echo htmlspecialchars($p['local_nombre']); ?></span>
                                        <img src="<?php echo htmlspecialchars($p['local_logo']); ?>" style="width:24px; height:24px; object-fit:contain;" alt="">
                                    </div>
                                    <span style="font-weight:800; font-size:1.1rem; min-width:60px; text-align:center; color:<?php echo $p['estado'] === 'en_vivo' ? 'var(--accent-live)' : '#ffffff'; ?>;">
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
                                <div style="font-size:0.75rem; color:var(--text-secondary); font-weight:600; width:120px; text-align:right;"><?php echo $state_label; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Columna Derecha (Banners y apuestas) -->
            <div class="sidebar-layout">
                <div class="sticky-sidebar">
                    
                    <!-- Banner de Publicidad Lateral -->
                    <div class="banner-sidebar-wrapper">
                        <?php echo $banner_sidebar; ?>
                    </div>

                    <!-- Caja de Afiliado para apostar destacado -->
                    <div class="betting-widget">
                        <span class="betting-widget-title">🔥 <?php echo htmlspecialchars(strtoupper(__('predictions'))); ?></span>
                        <div class="betting-teams">
                            <span style="font-weight:700;"><?php echo htmlspecialchars(__('winner_competition')); ?></span>
                        </div>
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:15px;"><?php echo htmlspecialchars(__('winner_competition_desc')); ?></p>
                        <a href="<?php echo htmlspecialchars($afiliado_apuestas); ?>" target="_blank" rel="nofollow noopener" class="btn-bet-now"><?php echo htmlspecialchars(__('bet_now')); ?></a>
                    </div>

                    <!-- Enlace a Tienda / Camisetas -->
                    <div class="betting-widget" style="border-color: rgba(14, 165, 233, 0.2);">
                        <span class="betting-widget-title" style="color: var(--accent-blue);">🛒 <?php echo htmlspecialchars(__('official_gear')); ?></span>
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom: 15px;"><?php echo htmlspecialchars(__('official_gear_desc')); ?> <?php echo htmlspecialchars($liga['nombre']); ?>.</p>
                        <a href="<?php echo htmlspecialchars($afiliado_camisetas); ?>" target="_blank" rel="nofollow noopener" class="btn-bet-now" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color:#ffffff;"><?php echo htmlspecialchars(__('buy_shirt')); ?></a>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align:center; padding:40px 20px; border-top:1px solid var(--border-glass); margin-top:60px; color:#64748b; font-size:0.85rem;">
        <p>&copy; <?php echo date('Y'); ?> 5 Ligas Europa. <?php echo htmlspecialchars(__('footer_rights')); ?></p>
        <p style="margin-top:10px; font-size:0.75rem;"><?php echo __('footer_note_liga'); ?></p>
    </footer>

    <?php renderJSTranslations(); ?>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
