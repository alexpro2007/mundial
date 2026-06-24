<?php
// sync.php - Motor de Sincronización en Tiempo Real con ESPN (Copa del Mundo 2026)
if (!defined('DISABLE_SYNC_OUTPUT') || !DISABLE_SYNC_OUTPUT) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
}

// Evitar bloqueos de tiempo de ejecución
set_time_limit(180);

require_once __DIR__ . '/db.php';

$force = isset($_GET['force']) || isset($_POST['force']);

// Control de frecuencia para evitar sobrecargar a ESPN (máximo una petición cada 10 segundos)
$last_sync_file = __DIR__ . '/last_sync.txt';
$now = time();
if (!$force && file_exists($last_sync_file)) {
    $last_sync_time = intval(file_get_contents($last_sync_file));
    if ($now - $last_sync_time < 10) {
        if (!defined('DISABLE_SYNC_OUTPUT') || !DISABLE_SYNC_OUTPUT) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Sincronizado recientemente (hace menos de 10s). Omitiendo.',
                'cached' => true
            ]);
            exit;
        }
        return;
    }
}

try {
    // 1. Descargar datos de la Copa del Mundo 2026 de ESPN (Rango del 11 de Junio al 19 de Julio de 2026)
    $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/scoreboard?dates=20260611-20260719&limit=1000';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $json = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception("Error de cURL al conectar con ESPN: " . curl_error($ch));
    }
    curl_close($ch);
    
    $data = json_decode($json, true);
    if (!isset($data['events'])) {
        throw new Exception("Formato de datos no válido recibido de ESPN.");
    }
    
    $partidos_actualizados = 0;
    $equipos_creados = 0;
    $eventos_registrados = 0;
    
    // Funciones auxiliares
    function getOrCreateTeam($pdo, $espn_team_id, $name, $code, $logo, $grupoParsed) {
        // Buscar por espn_id o por nombre en caso de homónimos
        $stmt = $pdo->prepare("SELECT id, grupo FROM equipos WHERE espn_id = ? OR nombre = ?");
        $stmt->execute([$espn_team_id, $name]);
        $team = $stmt->fetch();
        
        if ($team) {
            // Sincronizar el espn_id si no estaba seteado
            $upd = $pdo->prepare("UPDATE equipos SET espn_id = ?, logo_url = ? WHERE id = ?");
            $upd->execute([$espn_team_id, $logo, $team['id']]);
            
            // Actualizar grupo si se parseó correctamente
            if ($grupoParsed !== 'A' && $team['grupo'] === 'A') {
                $updG = $pdo->prepare("UPDATE equipos SET grupo = ? WHERE id = ?");
                $updG->execute([$grupoParsed, $team['id']]);
            }
            return $team['id'];
        } else {
            // Crear el equipo
            $ins = $pdo->prepare("INSERT INTO equipos (nombre, codigo_pais, logo_url, grupo, espn_id) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$name, strtolower($code), $logo, $grupoParsed, $espn_team_id]);
            return $pdo->lastInsertId();
        }
    }
    
    // Procesar todos los eventos
    foreach ($data['events'] as $e) {
        $espn_match_id = intval($e['id']);
        $comp = $e['competitions'][0];
        
        // Fase / Grupo
        $altGameNote = $comp['altGameNote'] ?? '';
        $matchName = $e['name'] ?? '';
        $fase = 'grupos';
        $grupoParsed = 'A';
        
        // Buscar grupo (A-L)
        if (preg_match('/Group\s+([A-L])/i', $altGameNote, $grM)) {
            $grupoParsed = strtoupper($grM[1]);
        }
        
        // Determinar fase
        if (stripos($altGameNote, 'Group') !== false || stripos($matchName, 'Group') !== false) {
            $fase = 'grupos';
        } elseif (stripos($altGameNote, 'Round of 32') !== false || stripos($matchName, 'Round of 32') !== false) {
            $fase = 'dieciseisavos';
        } elseif (stripos($altGameNote, 'Round of 16') !== false || stripos($matchName, 'Round of 16') !== false) {
            $fase = 'octavos';
        } elseif (stripos($altGameNote, 'Quarter') !== false || stripos($matchName, 'Quarter') !== false) {
            $fase = 'cuartos';
        } elseif (stripos($altGameNote, 'Semi') !== false || stripos($matchName, 'Semi') !== false) {
            $fase = 'semifinal';
        } elseif (stripos($altGameNote, 'Third Place') !== false || stripos($matchName, 'Third Place') !== false || stripos($matchName, '3rd Place') !== false) {
            $fase = 'tercer_puesto';
        } elseif (stripos($altGameNote, 'Final') !== false || stripos($matchName, 'Final') !== false) {
            $fase = 'final';
        }
        
        // Procesar Equipos y verificar que no sean placeholders (ej: "Group A Winner")
        $local = null;
        $visitor = null;
        foreach ($comp['competitors'] as $c) {
            if ($c['homeAway'] === 'home') $local = $c;
            else $visitor = $c;
        }
        
        $local_team_id = null;
        $visitor_team_id = null;
        
        if ($local && !preg_match('/Winner|Runner|Place|Group/i', $local['team']['displayName'])) {
            $logoL = $local['team']['logo'] ?? '';
            // Si el logo no viene, podemos usar uno genérico de flagcdn o de ESPN CDN
            $local_team_id = getOrCreateTeam(
                $pdo, 
                intval($local['team']['id']), 
                $local['team']['displayName'], 
                $local['team']['abbreviation'] ?? substr($local['team']['displayName'],0,3), 
                $logoL, 
                $grupoParsed
            );
        }
        
        if ($visitor && !preg_match('/Winner|Runner|Place|Group/i', $visitor['team']['displayName'])) {
            $logoV = $visitor['team']['logo'] ?? '';
            $visitor_team_id = getOrCreateTeam(
                $pdo, 
                intval($visitor['team']['id']), 
                $visitor['team']['displayName'], 
                $visitor['team']['abbreviation'] ?? substr($visitor['team']['displayName'],0,3), 
                $logoV, 
                $grupoParsed
            );
        }
        
        // Detalle del marcador
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
        
        // Guardar o actualizar partido
        $mStmt = $pdo->prepare("SELECT id FROM partidos WHERE id = ?");
        $mStmt->execute([$espn_match_id]);
        $partido_existente = $mStmt->fetch();
        
        if ($partido_existente) {
            $updPart = $pdo->prepare("
                UPDATE partidos 
                SET equipo_local_id = ?, equipo_visitante_id = ?, goles_local = ?, goles_visitante = ?, 
                    goles_penaltis_local = ?, goles_penaltis_visitante = ?, estado = ?, 
                    fecha_hora = ?, minuto_actual = ?, fase = ?, alt_game_note = ?, nombre = ?
                WHERE id = ?
            ");
            $updPart->execute([
                $local_team_id, $visitor_team_id, $goles_local, $goles_visitante,
                $goles_pen_local, $goles_pen_visitor, $estado,
                $fecha_hora, $minuto_actual, $fase, $altGameNote, $matchName,
                $espn_match_id
            ]);
        } else {
            $insPart = $pdo->prepare("
                INSERT INTO partidos (id, equipo_local_id, equipo_visitante_id, goles_local, goles_visitante, 
                                      goles_penaltis_local, goles_penaltis_visitante, estado, fecha_hora, 
                                      minuto_actual, fase, alt_game_note, nombre)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insPart->execute([
                $espn_match_id, $local_team_id, $visitor_team_id, $goles_local, $goles_visitante,
                $goles_pen_local, $goles_pen_visitor, $estado, $fecha_hora,
                $minuto_actual, $fase, $altGameNote, $matchName
            ]);
        }
        $partidos_actualizados++;
        
        // ------------------------------------------------------------
        // Procesar Detalles de Goles y Tarjetas (Eventos)
        // ------------------------------------------------------------
        if ($estado !== 'pendiente' && isset($comp['details'])) {
            // Limpiar eventos previos del partido
            $delEv = $pdo->prepare("DELETE FROM eventos_partido WHERE partido_id = ?");
            $delEv->execute([$espn_match_id]);
            
            foreach ($comp['details'] as $detail) {
                // Tipo de evento
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
                
                // Minuto
                $min = intval($detail['clock']['displayValue'] ?? 0);
                
                // Equipo involucrado
                $espn_ev_team_id = intval($detail['team']['id'] ?? 0);
                $teamStmt = $pdo->prepare("SELECT id FROM equipos WHERE espn_id = ?");
                $teamStmt->execute([$espn_ev_team_id]);
                $tObj = $teamStmt->fetch();
                $ev_team_db_id = $tObj ? $tObj['id'] : null;
                
                if (!$ev_team_db_id) {
                    // Si no está registrado por ID, ver cuál de los dos equipos es el que coincide
                    if ($local && intval($local['team']['id']) === $espn_ev_team_id) {
                        $ev_team_db_id = $local_team_id;
                    } elseif ($visitor && intval($visitor['team']['id']) === $espn_ev_team_id) {
                        $ev_team_db_id = $visitor_team_id;
                    }
                }
                
                if (!$ev_team_db_id) continue;
                
                // Atletas involucrados
                if (isset($detail['athletesInvolved'][0])) {
                    $athlete = $detail['athletesInvolved'][0];
                    $athlete_id = intval($athlete['id']);
                    $athlete_name = $athlete['displayName'];
                    
                    // Crear o verificar jugador
                    $jStmt = $pdo->prepare("SELECT id FROM jugadores WHERE id = ?");
                    $jStmt->execute([$athlete_id]);
                    if (!$jStmt->fetch()) {
                        $insJ = $pdo->prepare("INSERT INTO jugadores (id, nombre, equipo_id) VALUES (?, ?, ?)");
                        $insJ->execute([$athlete_id, $athlete_name, $ev_team_db_id]);
                    }
                    
                    // Registrar el evento
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
    
    // ------------------------------------------------------------
    // Actualizar Posiciones en el Bracket (Cronológico)
    // ------------------------------------------------------------
    $fases_eliminatorias = ['dieciseisavos', 'octavos', 'cuartos', 'semifinal', 'tercer_puesto', 'final'];
    foreach ($fases_eliminatorias as $f) {
        $stmt = $pdo->prepare("SELECT id FROM partidos WHERE fase = ? ORDER BY fecha_hora ASC, id ASC");
        $stmt->execute([$f]);
        $matches_in_phase = $stmt->fetchAll();
        $pos = 1;
        foreach ($matches_in_phase as $m) {
            $upd = $pdo->prepare("UPDATE partidos SET posicion_bracket = ? WHERE id = ?");
            $upd->execute([$pos, $m['id']]);
            $pos++;
        }
    }
    
    // ------------------------------------------------------------
    // Recalcular Estadísticas de Jugadores de Forma Agregada
    // ------------------------------------------------------------
    $pdo->query("
        UPDATE jugadores j 
        SET 
            j.goles = (SELECT COUNT(*) FROM eventos_partido WHERE jugador_id = j.id AND tipo = 'gol'),
            j.tarjetas_amarillas = (SELECT COUNT(*) FROM eventos_partido WHERE jugador_id = j.id AND tipo = 'tarjeta_amarilla'),
            j.tarjetas_rojas = (SELECT COUNT(*) FROM eventos_partido WHERE jugador_id = j.id AND tipo = 'tarjeta_roja')
    ");
    
    // Guardar marca de última sincronización
    file_put_contents($last_sync_file, $now);
    
    if (!defined('DISABLE_SYNC_OUTPUT') || !DISABLE_SYNC_OUTPUT) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Sincronización con ESPN completada.',
            'partidos_actualizados' => $partidos_actualizados,
            'eventos_registrados' => $eventos_registrados
        ]);
    }
    
} catch (Exception $e) {
    if (!defined('DISABLE_SYNC_OUTPUT') || !DISABLE_SYNC_OUTPUT) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
