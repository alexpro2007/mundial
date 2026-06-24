<?php
// sync.php - Motor de Sincronización en Tiempo Real con ESPN (5 Grandes Ligas de Europa)

// 1. Control de acceso y cabeceras
$is_cli = (PHP_SAPI === 'cli');
$is_internal = defined('DISABLE_SYNC_OUTPUT') && DISABLE_SYNC_OUTPUT;
$has_secret_key = isset($_GET['key']) && $_GET['key'] === '5ligas_sync_secret';

if (!$is_cli && !$is_internal && !$has_secret_key) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Acceso denegado. Se requiere clave de sincronización válida.'
    ]);
    exit;
}

if (!$is_internal) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
}

// Evitar bloqueos de tiempo de ejecución
set_time_limit(300);

require_once __DIR__ . '/db.php';

$force = isset($_GET['force']) || isset($_POST['force']) || $is_cli;

// Control de frecuencia para evitar sobrecargar a ESPN (máximo una petición completa cada 30 segundos)
$last_sync_file = __DIR__ . '/last_sync.txt';
$now = time();
if (!$force && file_exists($last_sync_file)) {
    $last_sync_time = intval(file_get_contents($last_sync_file));
    if ($now - $last_sync_time < 30) {
        if (!$is_internal) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Sincronizado recientemente (hace menos de 30s). Omitiendo.',
                'cached' => true
            ]);
            exit;
        }
        return;
    }
}

// Ligas a sincronizar
$ligas = [
    'eng.1' => 'Premier League',
    'esp.1' => 'LaLiga',
    'ita.1' => 'Serie A',
    'ger.1' => 'Bundesliga',
    'fra.1' => 'Ligue 1',
    'fifa.world' => 'Copa del Mundo'
];

$partidos_actualizados = 0;
$eventos_registrados = 0;
$ligas_sincronizadas = [];

// Funciones auxiliares
if (!function_exists('getOrCreateTeam')) {
    function getOrCreateTeam($pdo, $espn_team_id, $name, $code, $logo, $liga_id) {
        $stmt = $pdo->prepare("SELECT id FROM equipos WHERE espn_id = ? OR nombre = ?");
        $stmt->execute([$espn_team_id, $name]);
        $team = $stmt->fetch();
        
        if ($team) {
            $upd = $pdo->prepare("UPDATE equipos SET espn_id = ?, logo_url = ?, liga_id = ? WHERE id = ?");
            $upd->execute([$espn_team_id, $logo, $liga_id, $team['id']]);
            return $team['id'];
        } else {
            $ins = $pdo->prepare("INSERT INTO equipos (nombre, codigo_pais, logo_url, liga_id, espn_id) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$name, strtolower($code), $logo, $liga_id, $espn_team_id]);
            return $pdo->lastInsertId();
        }
    }
}

try {
    foreach ($ligas as $liga_id => $liga_nombre) {
        // A. Sincronizar Scoreboard (Partidos)
        $scoreboard_url = "https://site.api.espn.com/apis/site/v2/sports/soccer/{$liga_id}/scoreboard?limit=100";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $scoreboard_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $json_scoreboard = curl_exec($ch);
        curl_close($ch);
        
        if ($json_scoreboard) {
            $data = json_decode($json_scoreboard, true);
            if (isset($data['events'])) {
                foreach ($data['events'] as $e) {
                    $espn_match_id = intval($e['id']);
                    $comp = $e['competitions'][0];
                    $matchName = $e['name'] ?? '';
                    $fase = $comp['altGameNote'] ?? 'Temporada Regular';
                    
                    // Obtener equipos
                    $local = null;
                    $visitor = null;
                    foreach ($comp['competitors'] as $c) {
                        if ($c['homeAway'] === 'home') $local = $c;
                        else $visitor = $c;
                    }
                    
                    $local_team_id = null;
                    $visitor_team_id = null;
                    
                    if ($local) {
                        $logoL = $local['team']['logo'] ?? '';
                        $local_team_id = getOrCreateTeam(
                            $pdo, 
                            intval($local['team']['id']), 
                            $local['team']['displayName'], 
                            $local['team']['abbreviation'] ?? substr($local['team']['displayName'], 0, 3), 
                            $logoL, 
                            $liga_id
                        );
                    }
                    
                    if ($visitor) {
                        $logoV = $visitor['team']['logo'] ?? '';
                        $visitor_team_id = getOrCreateTeam(
                            $pdo, 
                            intval($visitor['team']['id']), 
                            $visitor['team']['displayName'], 
                            $visitor['team']['abbreviation'] ?? substr($visitor['team']['displayName'], 0, 3), 
                            $logoV, 
                            $liga_id
                        );
                    }
                    
                    // Marcador
                    $goles_local = intval($local['score'] ?? 0);
                    $goles_visitante = intval($visitor['score'] ?? 0);
                    
                    $goles_pen_local = null;
                    $goles_pen_visitor = null;
                    if (isset($local['shootoutScore'])) $goles_pen_local = intval($local['shootoutScore']);
                    if (isset($visitor['shootoutScore'])) $goles_pen_visitor = intval($visitor['shootoutScore']);
                    
                    // Estado
                    $state = $e['status']['type']['state']; // 'pre', 'in', 'post'
                    $estado = 'pendiente';
                    if ($state === 'in') $estado = 'en_vivo';
                    elseif ($state === 'post') $estado = 'finalizado';
                    
                    $minuto_actual = intval($e['status']['clock'] ?? 0);
                    $fecha_hora = date('Y-m-d H:i:s', strtotime($e['date']));
                    
                    // Guardar en la BD
                    $mStmt = $pdo->prepare("SELECT id FROM partidos WHERE id = ?");
                    $mStmt->execute([$espn_match_id]);
                    $partido_existente = $mStmt->fetch();
                    
                    if ($partido_existente) {
                        $updPart = $pdo->prepare("
                            UPDATE partidos 
                            SET equipo_local_id = ?, equipo_visitante_id = ?, goles_local = ?, goles_visitante = ?, 
                                goles_penaltis_local = ?, goles_penaltis_visitante = ?, estado = ?, 
                                fecha_hora = ?, minuto_actual = ?, fase = ?, alt_game_note = ?, nombre = ?, liga_id = ?
                            WHERE id = ?
                        ");
                        $updPart->execute([
                            $local_team_id, $visitor_team_id, $goles_local, $goles_visitante,
                            $goles_pen_local, $goles_pen_visitor, $estado,
                            $fecha_hora, $minuto_actual, $fase, $fase, $matchName, $liga_id,
                            $espn_match_id
                        ]);
                    } else {
                        $insPart = $pdo->prepare("
                            INSERT INTO partidos (id, equipo_local_id, equipo_visitante_id, goles_local, goles_visitante, 
                                                  goles_penaltis_local, goles_penaltis_visitante, estado, fecha_hora, 
                                                  minuto_actual, fase, alt_game_note, nombre, liga_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $insPart->execute([
                            $espn_match_id, $local_team_id, $visitor_team_id, $goles_local, $goles_visitante,
                            $goles_pen_local, $goles_pen_visitor, $estado, $fecha_hora,
                            $minuto_actual, $fase, $fase, $matchName, $liga_id
                        ]);
                    }
                    $partidos_actualizados++;
                    
                    // Procesar eventos si el partido está en vivo o finalizado
                    if ($estado !== 'pendiente' && isset($comp['details'])) {
                        $delEv = $pdo->prepare("DELETE FROM eventos_partido WHERE partido_id = ?");
                        $delEv->execute([$espn_match_id]);
                        
                        foreach ($comp['details'] as $detail) {
                            $txt = $detail['type']['text'] ?? '';
                            $tipo = null;
                            
                            if (stripos($txt, 'Goal') !== false || stripos($txt, 'ownGoal') !== false) {
                                $tipo = 'gol';
                            } elseif (stripos($txt, 'Yellow Card') !== false) {
                                $tipo = 'tarjeta_amarilla';
                            } elseif (stripos($txt, 'Red Card') !== false) {
                                $tipo = 'tarjeta_roja';
                            }
                            
                            if (!$tipo) continue;
                            
                            $min = intval($detail['clock']['displayValue'] ?? 0);
                            $espn_ev_team_id = intval($detail['team']['id'] ?? 0);
                            
                            $ev_team_db_id = null;
                            if ($local && intval($local['team']['id']) === $espn_ev_team_id) {
                                $ev_team_db_id = $local_team_id;
                            } elseif ($visitor && intval($visitor['team']['id']) === $espn_ev_team_id) {
                                $ev_team_db_id = $visitor_team_id;
                            }
                            
                            if (!$ev_team_db_id) continue;
                            
                            if (isset($detail['athletesInvolved'][0])) {
                                $athlete = $detail['athletesInvolved'][0];
                                $athlete_id = intval($athlete['id']);
                                $athlete_name = $athlete['displayName'];
                                
                                $jStmt = $pdo->prepare("SELECT id FROM jugadores WHERE id = ?");
                                $jStmt->execute([$athlete_id]);
                                if (!$jStmt->fetch()) {
                                    $insJ = $pdo->prepare("INSERT INTO jugadores (id, nombre, equipo_id) VALUES (?, ?, ?)");
                                    $insJ->execute([$athlete_id, $athlete_name, $ev_team_db_id]);
                                }
                                
                                $insEv = $pdo->prepare("
                                    INSERT INTO eventos_partido (partido_id, tipo, minuto, equipo_id, jugador_id)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $insEv->execute([$espn_match_id, $tipo, $min, $ev_team_db_id, $athlete_id]);
                                $eventos_registrados++;
                            }
                        }
                    }
                }
            }
        }
        
        // B. Sincronizar Standings (Tabla de posiciones)
        $standings_url = "https://site.api.espn.com/apis/v2/sports/soccer/{$liga_id}/standings";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $standings_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $json_standings = curl_exec($ch);
        curl_close($ch);
        
        if ($json_standings) {
            $test_data = json_decode($json_standings, true);
            if (isset($test_data['children'][0]['standings']['entries'])) {
                // Guardar JSON en la tabla de configuraciones
                $config_key = "standings_{$liga_id}";
                $updConf = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
                $updConf->execute([$config_key, $json_standings, $json_standings]);
                $ligas_sincronizadas[] = $liga_nombre;
            }
        }
    }
    
    // Recalcular estadísticas agregadas de jugadores
    $pdo->query("
        UPDATE jugadores j 
        SET 
            j.goles = (SELECT COUNT(*) FROM eventos_partido WHERE jugador_id = j.id AND tipo = 'gol'),
            j.tarjetas_amarillas = (SELECT COUNT(*) FROM eventos_partido WHERE jugador_id = j.id AND tipo = 'tarjeta_amarilla'),
            j.tarjetas_rojas = (SELECT COUNT(*) FROM eventos_partido WHERE jugador_id = j.id AND tipo = 'tarjeta_roja')
    ");
    
    // Guardar marca de última sincronización
    file_put_contents($last_sync_file, $now);
    
    if (!$is_internal) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Sincronización con ESPN completada.',
            'ligas_sincronizadas' => $ligas_sincronizadas,
            'partidos_actualizados' => $partidos_actualizados,
            'eventos_registrados' => $eventos_registrados
        ]);
    }
    
} catch (Exception $e) {
    if (!$is_internal) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
