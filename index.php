<?php
// index.php - Vista Principal del Torneo Mundial 2026 (ESPN Live)
require_once __DIR__ . '/db.php';

// Obtener estadísticas rápidas para la sección decorativa
$total_teams = 48; // El mundial tiene 48 equipos por defecto
try {
    $teams_count = intval($pdo->query("SELECT COUNT(*) FROM equipos")->fetchColumn());
    if ($teams_count > 0) {
        $total_teams = $teams_count;
    }
    $matches_played = intval($pdo->query("SELECT COUNT(*) FROM partidos WHERE estado = 'finalizado'")->fetchColumn());
    $goals_query = $pdo->query("SELECT SUM(goles_local + goles_visitante) as total_goals FROM partidos WHERE estado != 'pendiente'")->fetch();
    $total_goals = intval($goals_query['total_goals'] ?? 0);
    $live_matches = intval($pdo->query("SELECT COUNT(*) FROM partidos WHERE estado = 'en_vivo'")->fetchColumn());
} catch (Exception $e) {
    $matches_played = 0;
    $total_goals = 0;
    $live_matches = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Copa del Mundo 2026 - Tablero de Resultados en Vivo</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Elementos Decorativos de Fondo (Efecto de luces) -->
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>
    <div class="bg-glow bg-glow-3"></div>

    <!-- Header & Navegación Principal -->
    <header>
        <div class="nav-container">
            <div class="logo-section">
                <!-- Icono de Trofeo SVG -->
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                    <path d="M4 22h16"></path>
                    <path d="M10 14.66V17c0 .55-.45 1-1 1H4v2h16v-2h-5c-.55 0-1-.45-1-1v-2.34"></path>
                    <path d="M12 2a6 6 0 0 1 6 6v5a6 6 0 0 1-6 6 6 6 0 0 1-6-6V8a6 6 0 0 1 6-6z"></path>
                </svg>
                <h1>MUNDIAL 2026</h1>
            </div>
            
            <nav class="main-nav">
                <button class="nav-btn active" data-view="home">Inicio</button>
                <button class="nav-btn" data-view="matches">Partidos</button>
                <button class="nav-btn" data-view="groups">Grupos</button>
                <button class="nav-btn" data-view="stats">Estadísticas</button>
                <button class="nav-btn" data-view="bracket">Fase Final</button>
            </nav>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main>

        <!-- SECCIÓN: INICIO (HOME) -->
        <section id="home-view" class="view-section active">
            <div class="home-hero centered-hero">
                <div class="hero-text text-center">
                    <div class="badge-world-cup">COPA MUNDIAL DE LA FIFA 2026</div>
                    <h2>Resultados Reales en Tiempo Real</h2>
                    <p class="hero-subtitle">Sigue la emoción en vivo, estadísticas oficiales y la evolución de los grupos y eliminatorias directo desde la API de ESPN.</p>
                </div>
                
                <div class="countdown-glow-wrapper">
                    <div class="countdown-container-large">
                        <div class="countdown-title-large">
                            <span class="live-dot-indicator"></span> CUENTA REGRESIVA PARA LA GRAN FINAL
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

                <div class="hero-actions">
                    <button class="btn btn-primary btn-large btn-glowing" onclick="switchView('matches')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        Explorar Partidos
                    </button>
                    <button class="btn btn-outline btn-large" onclick="switchView('bracket')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;">
                            <path d="M12 22V12"></path>
                            <path d="M12 12H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2z"></path>
                            <path d="M12 12h8a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2z"></path>
                        </svg>
                        Fase Eliminatoria
                    </button>
                </div>
            </div>

            <!-- Panel Decorativo de Métricas del Torneo -->
            <div class="tournament-stats-grid">
                <div class="stat-card decor-card">
                    <div class="stat-icon">⚽</div>
                    <div class="stat-info">
                        <span class="stat-val-big"><?php echo $total_goals; ?></span>
                        <span class="stat-lbl-sub">Goles Anotados</span>
                    </div>
                    <div class="card-glow-green"></div>
                </div>
                <div class="stat-card decor-card">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-info">
                        <span class="stat-val-big"><?php echo $total_teams; ?></span>
                        <span class="stat-lbl-sub">Selecciones Unidas</span>
                    </div>
                    <div class="card-glow-gold"></div>
                </div>
                <div class="stat-card decor-card">
                    <div class="stat-icon">🏁</div>
                    <div class="stat-info">
                        <span class="stat-val-big"><?php echo $matches_played; ?></span>
                        <span class="stat-lbl-sub">Partidos Finalizados</span>
                    </div>
                    <div class="card-glow-blue"></div>
                </div>
                <div class="stat-card decor-card <?php echo $live_matches > 0 ? 'live-pulse' : ''; ?>">
                    <div class="stat-icon"><?php echo $live_matches > 0 ? '🔴' : '🕒'; ?></div>
                    <div class="stat-info">
                        <span class="stat-val-big"><?php echo $live_matches; ?></span>
                        <span class="stat-lbl-sub">En Vivo Ahora</span>
                    </div>
                    <div class="card-glow-red"></div>
                </div>
            </div>
        </section>

        <!-- SECCIÓN: PARTIDOS -->
        <section id="matches-view" class="view-section">
            <h2 class="section-title">Calendario y <span>Resultados Reales</span></h2>
            
            <div class="matches-subtabs">
                <button class="subtab-btn active" data-subtab="live">En Vivo <span id="live-count-badge" style="display:none; background:var(--accent-red); color:#fff; padding:2px 6px; border-radius:10px; font-size:0.7rem; margin-left:5px;">0</span></button>
                <button class="subtab-btn" data-subtab="upcoming">Próximos Partidos</button>
                <button class="subtab-btn" data-subtab="finished">Resultados</button>
            </div>

            <!-- Contenedor dinámico de partidos -->
            <div id="matches-container" class="matches-grid">
                <!-- Se rellena con JS -->
            </div>
            
            <div id="no-matches-msg" style="display:none; text-align:center; padding:40px; color:var(--text-secondary); font-size:1.1rem;">
                No hay partidos para mostrar en esta categoría.
            </div>
        </section>

        <!-- SECCIÓN: GRUPOS -->
        <section id="groups-view" class="view-section">
            <h2 class="section-title">Clasificación de <span>Grupos</span></h2>
            
            <!-- Botonera de Grupos A-L -->
            <div class="groups-selector">
                <button class="group-select-btn active" data-group="A">Grupo A</button>
                <button class="group-select-btn" data-group="B">Grupo B</button>
                <button class="group-select-btn" data-group="C">Grupo C</button>
                <button class="group-select-btn" data-group="D">Grupo D</button>
                <button class="group-select-btn" data-group="E">Grupo E</button>
                <button class="group-select-btn" data-group="F">Grupo F</button>
                <button class="group-select-btn" data-group="G">Grupo G</button>
                <button class="group-select-btn" data-group="H">Grupo H</button>
                <button class="group-select-btn" data-group="I">Grupo I</button>
                <button class="group-select-btn" data-group="J">Grupo J</button>
                <button class="group-select-btn" data-group="K">Grupo K</button>
                <button class="group-select-btn" data-group="L">Grupo L</button>
            </div>

            <!-- Tabla de Clasificación -->
            <div class="glass-card" style="padding:0;">
                <div class="table-responsive">
                    <table class="standings-table">
                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th class="num-col" title="Partidos Jugados">PJ</th>
                                <th class="num-col" title="Partidos Ganados">PG</th>
                                <th class="num-col" title="Partidos Empatados">PE</th>
                                <th class="num-col" title="Partidos Perdidos">PP</th>
                                <th class="num-col" title="Goles a Favor">GF</th>
                                <th class="num-col" title="Goles en Contra">GC</th>
                                <th class="num-col" title="Diferencia de Goles">DG</th>
                                <th class="pts-col" title="Puntos Acumulados">PTS</th>
                            </tr>
                        </thead>
                        <tbody id="standings-tbody">
                            <!-- Se rellena con JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:10px; display:flex; align-items:center; gap:8px;">
                <span style="display:inline-block; width:12px; height:12px; background:rgba(16, 185, 129, 0.15); border-left:3px solid var(--accent-live);"></span>
                <span>Los dos primeros de cada grupo y los 8 mejores terceros avanzan a Dieciseisavos de Final.</span>
            </div>
        </section>

        <!-- SECCIÓN: ESTADÍSTICAS -->
        <section id="stats-view" class="view-section">
            <h2 class="section-title">Líderes de <span>Estadísticas Reales</span></h2>
            
            <div class="stats-grid">
                <!-- Máximos Goleadores -->
                <div class="stats-card">
                    <div class="stats-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                            <path d="M2 12h20"></path>
                        </svg>
                        Máximos Goleadores
                    </div>
                    <div class="stats-list" id="stats-goleadores">
                        <!-- Se rellena con JS -->
                    </div>
                </div>

                <!-- Máximos Asistentes -->
                <div class="stats-card">
                    <div class="stats-card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 6.1H3"></path>
                            <path d="M21 12H3"></path>
                            <path d="M17 17.9H3"></path>
                        </svg>
                        Máximos Asistentes
                    </div>
                    <div class="stats-list" id="stats-asistentes">
                        <!-- Se rellena con JS -->
                    </div>
                </div>

                <!-- Tarjetas Amarillas -->
                <div class="stats-card">
                    <div class="stats-card-title">
                        <rect x="5" y="3" width="14" height="18" rx="2" ry="2" fill="var(--primary-color)" style="stroke:none;"></rect>
                        <span style="margin-left: 10px;">Tarjetas Amarillas</span>
                    </div>
                    <div class="stats-list" id="stats-amarillas">
                        <!-- Se rellena con JS -->
                    </div>
                </div>

                <!-- Tarjetas Rojas -->
                <div class="stats-card">
                    <div class="stats-card-title">
                        <rect x="5" y="3" width="14" height="18" rx="2" ry="2" fill="var(--accent-red)" style="stroke:none;"></rect>
                        <span style="margin-left: 10px;">Tarjetas Rojas</span>
                    </div>
                    <div class="stats-list" id="stats-rojas">
                        <!-- Se rellena con JS -->
                    </div>
                </div>
            </div>
        </section>

        <!-- SECCIÓN: FASE FINAL (BRACKET) -->
        <section id="bracket-view" class="view-section">
            <h2 class="section-title">Cuadro de la <span>Fase Eliminatoria (32 Selecciones)</span></h2>
            
            <div class="bracket-wrapper">
                <div class="bracket-container">
                    
                    <!-- LADO IZQUIERDO -->
                    <!-- Dieciseisavos (Izquierda 1-8) -->
                    <div class="bracket-column">
                        <div class="bracket-header">Dieciseisavos (I)</div>
                        <div id="bracket-dieciseisavos-1" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-2" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-3" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-4" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-5" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-6" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-7" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-8" class="bracket-card-container"></div>
                    </div>

                    <!-- Octavos (Izquierda 1-4) -->
                    <div class="bracket-column">
                        <div class="bracket-header">Octavos de Final</div>
                        <div id="bracket-octavos-1" class="bracket-card-container"></div>
                        <div id="bracket-octavos-2" class="bracket-card-container"></div>
                        <div id="bracket-octavos-3" class="bracket-card-container"></div>
                        <div id="bracket-octavos-4" class="bracket-card-container"></div>
                    </div>

                    <!-- Cuartos (Izquierda 1-2) -->
                    <div class="bracket-column">
                        <div class="bracket-header">Cuartos de Final</div>
                        <div id="bracket-cuartos-1" class="bracket-card-container"></div>
                        <div id="bracket-cuartos-2" class="bracket-card-container"></div>
                    </div>

                    <!-- Semifinales (Izquierda 1) -->
                    <div class="bracket-column">
                        <div class="bracket-header">Semifinales</div>
                        <div id="bracket-semis-1" class="bracket-card-container"></div>
                    </div>

                    <!-- CENTRO -->
                    <!-- Gran Final / Campeón / Tercer Puesto -->
                    <div class="bracket-column final-col">
                        
                        <!-- Campeón -->
                        <div class="bracket-champion-box" id="champion-box" style="display:none;">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                                <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                                <path d="M4 22h16"></path>
                                <path d="M10 14.66V17c0 .55-.45 1-1 1H4v2h16v-2h-5c-.55 0-1-.45-1-1v-2.34"></path>
                                <path d="M12 2a6 6 0 0 1 6 6v5a6 6 0 0 1-6 6 6 6 0 0 1-6-6V8a6 6 0 0 1 6-6z"></path>
                            </svg>
                            <div class="bracket-champion-title">Campeón Mundial</div>
                            <div class="bracket-champion-name" id="champion-name">Por Definir</div>
                        </div>

                        <!-- Final -->
                        <div>
                            <div class="bracket-header">Gran Final</div>
                            <div id="bracket-final" class="bracket-card-container"></div>
                        </div>

                        <!-- Tercer Puesto -->
                        <div>
                            <div class="bracket-header">Tercer Puesto</div>
                            <div id="bracket-tercero" class="bracket-card-container"></div>
                        </div>
                    </div>

                    <!-- LADO DERECHO -->
                    <!-- Semifinales (Derecha 2) -->
                    <div class="bracket-column">
                        <div class="bracket-header">Semifinales</div>
                        <div id="bracket-semis-2" class="bracket-card-container"></div>
                    </div>

                    <!-- Cuartos (Derecha 3-4) -->
                    <div class="bracket-column">
                        <div class="bracket-header">Cuartos de Final</div>
                        <div id="bracket-cuartos-3" class="bracket-card-container"></div>
                        <div id="bracket-cuartos-4" class="bracket-card-container"></div>
                    </div>

                    <!-- Octavos (Derecha 5-8) -->
                    <div class="bracket-column">
                        <div class="bracket-header">Octavos de Final</div>
                        <div id="bracket-octavos-5" class="bracket-card-container"></div>
                        <div id="bracket-octavos-6" class="bracket-card-container"></div>
                        <div id="bracket-octavos-7" class="bracket-card-container"></div>
                        <div id="bracket-octavos-8" class="bracket-card-container"></div>
                    </div>

                    <!-- Dieciseisavos (Derecha 9-16) -->
                    <div class="bracket-column">
                        <div class="bracket-header">Dieciseisavos (D)</div>
                        <div id="bracket-dieciseisavos-9" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-10" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-11" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-12" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-13" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-14" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-15" class="bracket-card-container"></div>
                        <div id="bracket-dieciseisavos-16" class="bracket-card-container"></div>
                    </div>

                </div>
            </div>
        </section>

    </main>

    <!-- Modal de Alineaciones de Partido -->
    <div id="match-modal" class="modal">
        <div class="modal-content glass-card">
            <span class="close-modal">&times;</span>
            <div id="modal-match-details">
                <!-- Se rellena con JS -->
            </div>
            
            <div class="lineups-section">
                <div class="lineup-tabs">
                    <button class="lineup-tab-btn active" data-side="local" id="tab-team-local">Equipo Local</button>
                    <button class="lineup-tab-btn" data-side="visitante" id="tab-team-visitante">Equipo Visitante</button>
                </div>
                
                <div id="lineup-status-badge" class="lineup-status-badge">Alineación Probable</div>
                
                <div class="lineup-content">
                    <div class="lineup-column">
                        <h4>Titulares</h4>
                        <div id="lineup-starters" class="players-list">
                            <!-- Se rellena con JS -->
                        </div>
                    </div>
                    <div class="lineup-column">
                        <h4>Suplentes</h4>
                        <div id="lineup-bench" class="players-list">
                            <!-- Se rellena con JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
