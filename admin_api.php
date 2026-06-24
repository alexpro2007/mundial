<?php
// admin_api.php - API de Simulación y Administración del Mundial
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Función auxiliar para determinar y procesar la finalización de un partido y sus avances
function finalizarPartido($pdo, $partido_id) {
    // 1. Obtener detalles del partido terminado
    $stmt = $pdo->prepare("SELECT * FROM partidos WHERE id = ?");
    $stmt->execute([$partido_id]);
    $partido = $stmt->fetch();
    
    if (!$partido || $partido['estado'] !== 'finalizado') {
        return;
    }
    
    $fase = $partido['fase'];
    
    if ($fase === 'grupos') {
        // Obtener el grupo de los equipos para comprobar si ya se terminaron todos los partidos del grupo
        $eqStmt = $pdo->prepare("SELECT grupo FROM equipos WHERE id = ?");
        $eqStmt->execute([$partido['equipo_local_id']]);
        $eq = $eqStmt->fetch();
        $grupo = $eq['grupo'];
        
        // Verificar cuántos partidos quedan pendientes en este grupo
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) AS pendientes 
            FROM partidos p
            JOIN equipos e ON p.equipo_local_id = e.id
            WHERE e.grupo = ? AND p.fase = 'grupos' AND p.estado != 'finalizado'
        ");
        $checkStmt->execute([$grupo]);
        $res = $checkStmt->fetch();
        
        // Si no quedan partidos pendientes en este grupo, clasificar a octavos
        if (intval($res['pendientes']) === 0) {
            promocionarGrupoAOctavos($pdo, $grupo);
        }
    } else {
        // Fase eliminatoria. Determinar ganador.
        $ganador_id = NULL;
        $perdedor_id = NULL;
        
        $gl = intval($partido['goles_local']);
        $gv = intval($partido['goles_visitante']);
        
        if ($gl > $gv) {
            $ganador_id = $partido['equipo_local_id'];
            $perdedor_id = $partido['equipo_visitante_id'];
        } elseif ($gl < $gv) {
            $ganador_id = $partido['equipo_visitante_id'];
            $perdedor_id = $partido['equipo_local_id'];
        } else {
            // Empate en fase eliminatoria: verificar penaltis
            $pl = intval($partido['goles_penaltis_local'] ?? 0);
            $pv = intval($partido['goles_penaltis_visitante'] ?? 0);
            
            if ($pl === 0 && $pv === 0) {
                // Si por alguna razón no se simularon penaltis todavía
                $pl = rand(4, 5);
                $pv = ($pl === 5) ? rand(3, 4) : 5;
                $updPen = $pdo->prepare("UPDATE partidos SET goles_penaltis_local = ?, goles_penaltis_visitante = ? WHERE id = ?");
                $updPen->execute([$pl, $pv, $partido_id]);
            }
            
            if ($pl > $pv) {
                $ganador_id = $partido['equipo_local_id'];
                $perdedor_id = $partido['equipo_visitante_id'];
            } else {
                $ganador_id = $partido['equipo_visitante_id'];
                $perdedor_id = $partido['equipo_local_id'];
            }
        }
        
        promocionarEliminatoria($pdo, $partido_id, $ganador_id, $perdedor_id);
    }
}

// Clasificación automática de grupo a octavos
function promocionarGrupoAOctavos($pdo, $grupo) {
    // Calcular clasificación exacta del grupo
    // Obtener todos los equipos del grupo
    $teamsStmt = $pdo->prepare("SELECT id, nombre, codigo_pais, grupo FROM equipos WHERE grupo = ?");
    $teamsStmt->execute([$grupo]);
    $teams = $teamsStmt->fetchAll();
    
    // Obtener partidos del grupo finalizados
    $matchesStmt = $pdo->prepare("
        SELECT equipo_local_id, equipo_visitante_id, goles_local, goles_visitante 
        FROM partidos p
        JOIN equipos el ON p.equipo_local_id = el.id
        WHERE p.fase = 'grupos' AND p.estado = 'finalizado' AND el.grupo = ?
    ");
    $matchesStmt->execute([$grupo]);
    $finished_matches = $matchesStmt->fetchAll();
    
    $standings = [];
    foreach ($teams as $t) {
        $standings[$t['id']] = [
            'id' => $t['id'],
            'nombre' => $t['nombre'],
            'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0, 'gf' => 0, 'gc' => 0, 'dg' => 0, 'puntos' => 0
        ];
    }
    
    foreach ($finished_matches as $m) {
        $locId = $m['equipo_local_id'];
        $visId = $m['equipo_visitante_id'];
        $gl = intval($m['goles_local']);
        $gv = intval($m['goles_visitante']);
        
        $standings[$locId]['pj']++;
        $standings[$visId]['pj']++;
        $standings[$locId]['gf'] += $gl;
        $standings[$locId]['gc'] += $gv;
        $standings[$visId]['gf'] += $gv;
        $standings[$visId]['gc'] += $gl;
        
        if ($gl > $gv) {
            $standings[$locId]['pg']++;
            $standings[$locId]['puntos'] += 3;
            $standings[$visId]['pp']++;
        } elseif ($gl < $gv) {
            $standings[$visId]['pg']++;
            $standings[$visId]['puntos'] += 3;
            $standings[$locId]['pp']++;
        } else {
            $standings[$locId]['pe']++;
            $standings[$locId]['puntos'] += 1;
            $standings[$visId]['pe']++;
            $standings[$visId]['puntos'] += 1;
        }
    }
    
    foreach ($standings as &$t) {
        $t['dg'] = $t['gf'] - $t['gc'];
    }
    
    uasort($standings, function($a, $b) {
        if ($a['puntos'] !== $b['puntos']) return $b['puntos'] <=> $a['puntos'];
        if ($a['dg'] !== $b['dg']) return $b['dg'] <=> $a['dg'];
        if ($a['gf'] !== $b['gf']) return $b['gf'] <=> $a['gf'];
        return strcmp($a['nombre'], $b['nombre']);
    });
    
    $sorted_ids = array_keys($standings);
    if (count($sorted_ids) < 2) return;
    
    $primero_id = $sorted_ids[0];
    $segundo_id = $sorted_ids[1];
    
    // Mapeo grupo -> partidos de octavos
    // Formato: Grupo => [ID_partido_donde_va_1º, posicion_local/visitante, ID_partido_donde_va_2º, posicion_local/visitante]
    $mapping = [
        'A' => [49, 'local', 53, 'visitante'],
        'B' => [53, 'local', 49, 'visitante'],
        'C' => [50, 'local', 54, 'visitante'],
        'D' => [54, 'local', 50, 'visitante'],
        'E' => [51, 'local', 55, 'visitante'],
        'F' => [55, 'local', 51, 'visitante'],
        'G' => [52, 'local', 56, 'visitante'],
        'H' => [56, 'local', 52, 'visitante']
    ];
    
    if (isset($mapping[$grupo])) {
        $map = $mapping[$grupo];
        
        // Asignar 1º
        $col1 = ($map[1] === 'local') ? 'equipo_local_id' : 'equipo_visitante_id';
        $upd1 = $pdo->prepare("UPDATE partidos SET $col1 = ? WHERE id = ?");
        $upd1->execute([$primero_id, $map[0]]);
        
        // Asignar 2º
        $col2 = ($map[3] === 'local') ? 'equipo_local_id' : 'equipo_visitante_id';
        $upd2 = $pdo->prepare("UPDATE partidos SET $col2 = ? WHERE id = ?");
        $upd2->execute([$segundo_id, $map[2]]);
    }
}

// Avance automático en fases eliminatorias
function promocionarEliminatoria($pdo, $partido_id, $ganador_id, $perdedor_id) {
    // Mapeo partido_id -> [partido_destino_id, lado_destino]
    $mapping = [
        49 => [57, 'local'],
        50 => [57, 'visitante'],
        51 => [58, 'local'],
        52 => [58, 'visitante'],
        53 => [59, 'local'],
        54 => [59, 'visitante'],
        55 => [60, 'local'],
        56 => [60, 'visitante'],
        
        57 => [61, 'local'],
        58 => [61, 'visitante'],
        59 => [62, 'local'],
        60 => [62, 'visitante']
    ];
    
    if (isset($mapping[$partido_id])) {
        $dest = $mapping[$partido_id];
        $col = ($dest[1] === 'local') ? 'equipo_local_id' : 'equipo_visitante_id';
        $upd = $pdo->prepare("UPDATE partidos SET $col = ? WHERE id = ?");
        $upd->execute([$ganador_id, $dest[0]]);
    } elseif ($partido_id == 61) {
        // Semifinal 1: ganador va a la final (64, local), perdedor al tercer puesto (63, local)
        $updWin = $pdo->prepare("UPDATE partidos SET equipo_local_id = ? WHERE id = 64");
        $updWin->execute([$ganador_id]);
        $updLose = $pdo->prepare("UPDATE partidos SET equipo_local_id = ? WHERE id = 63");
        $updLose->execute([$perdedor_id]);
    } elseif ($partido_id == 62) {
        // Semifinal 2: ganador va a la final (64, visitante), perdedor al tercer puesto (63, visitante)
        $updWin = $pdo->prepare("UPDATE partidos SET equipo_visitante_id = ? WHERE id = 64");
        $updWin->execute([$ganador_id]);
        $updLose = $pdo->prepare("UPDATE partidos SET equipo_visitante_id = ? WHERE id = 63");
        $updLose->execute([$perdedor_id]);
    }
}

switch ($action) {
    case 'reset_db':
        // Reiniciar base de datos corriendo el schema
        try {
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            $pdo->exec($sql);
            echo json_encode(['status' => 'success', 'message' => 'Base de datos reiniciada con éxito']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al reiniciar la base de datos: ' . $e->getMessage()]);
        }
        break;

    case 'start_match':
        $partido_id = intval($_POST['partido_id'] ?? 0);
        
        // Verificar que el partido tenga ambos equipos definidos
        $stmt = $pdo->prepare("SELECT equipo_local_id, equipo_visitante_id FROM partidos WHERE id = ?");
        $stmt->execute([$partido_id]);
        $p = $stmt->fetch();
        
        if (!$p || empty($p['equipo_local_id']) || empty($p['equipo_visitante_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'No se puede iniciar un partido sin equipos definidos']);
            exit;
        }
        
        $upd = $pdo->prepare("
            UPDATE partidos 
            SET estado = 'en_vivo', minuto_actual = 0, goles_local = 0, goles_visitante = 0, 
                goles_penaltis_local = NULL, goles_penaltis_visitante = NULL
            WHERE id = ?
        ");
        $upd->execute([$partido_id]);
        
        // Borrar eventos previos del partido si los hubiera
        $delEv = $pdo->prepare("DELETE FROM eventos_partido WHERE partido_id = ?");
        $delEv->execute([$partido_id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Partido iniciado en vivo']);
        break;

    case 'manual_update':
        $partido_id = intval($_POST['partido_id'] ?? 0);
        $goles_local = intval($_POST['goles_local'] ?? 0);
        $goles_visitante = intval($_POST['goles_visitante'] ?? 0);
        $estado = $_POST['estado'] ?? ''; // 'pendiente', 'en_vivo', 'finalizado'
        
        // Si el estado es finalizado y en una fase eliminatoria empatado, registrar penaltis manuales
        $goles_pen_local = isset($_POST['goles_penaltis_local']) && $_POST['goles_penaltis_local'] !== '' ? intval($_POST['goles_penaltis_local']) : NULL;
        $goles_pen_visitor = isset($_POST['goles_penaltis_visitante']) && $_POST['goles_penaltis_visitante'] !== '' ? intval($_POST['goles_penaltis_visitante']) : NULL;

        $upd = $pdo->prepare("
            UPDATE partidos 
            SET goles_local = ?, goles_visitante = ?, estado = ?, goles_penaltis_local = ?, goles_penaltis_visitante = ?
            WHERE id = ?
        ");
        $upd->execute([$goles_local, $goles_visitante, $estado, $goles_pen_local, $goles_pen_visitor, $partido_id]);
        
        if ($estado === 'finalizado') {
            finalizarPartido($pdo, $partido_id);
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Partido actualizado con éxito']);
        break;

    case 'tick_live':
        // Simular un paso de tiempo (ej: 5 minutos) en todos los partidos en vivo
        $liveStmt = $pdo->query("SELECT * FROM partidos WHERE estado = 'en_vivo'");
        $liveMatches = $liveStmt->fetchAll();
        
        $minutos_por_tick = 5;
        $cambios = [];
        
        foreach ($liveMatches as $m) {
            $partido_id = $m['id'];
            $minuto = intval($m['minuto_actual']) + $minutos_por_tick;
            
            if ($minuto > 90) {
                $minuto = 90;
            }
            
            // Probabilidad aleatoria de eventos:
            $prob = rand(1, 100);
            $evento_tipo = NULL;
            $equipo_id = NULL;
            $jugador_id = NULL;
            $asistente_id = NULL;
            
            $gl = intval($m['goles_local']);
            $gv = intval($m['goles_visitante']);
            
            if ($prob <= 15) { // 15% Gol
                $evento_tipo = 'gol';
                // Seleccionar equipo goleador aleatorio
                $esLocal = (rand(1, 2) === 1);
                $equipo_id = $esLocal ? $m['equipo_local_id'] : $m['equipo_visitante_id'];
                
                if ($esLocal) {
                    $gl++;
                } else {
                    $gv++;
                }
                
                // Obtener un jugador aleatorio de ese equipo
                $jugStmt = $pdo->prepare("SELECT id FROM jugadores WHERE equipo_id = ?");
                $jugStmt->execute([$equipo_id]);
                $jugs = $jugStmt->fetchAll();
                
                if (count($jugs) > 0) {
                    $jugador_id = $jugs[array_rand($jugs)]['id'];
                    // Actualizar goles del jugador
                    $updJ = $pdo->prepare("UPDATE jugadores SET goles = goles + 1 WHERE id = ?");
                    $updJ->execute([$jugador_id]);
                    
                    // Asistente aleatorio (50% probabilidad)
                    if (rand(1, 2) === 1 && count($jugs) > 1) {
                        do {
                            $asistente_id = $jugs[array_rand($jugs)]['id'];
                        } while ($asistente_id == $jugador_id);
                        
                        $updAsist = $pdo->prepare("UPDATE jugadores SET asistencias = asistencias + 1 WHERE id = ?");
                        $updAsist->execute([$asistente_id]);
                    }
                }
            } elseif ($prob > 15 && $prob <= 23) { // 8% Tarjeta Amarilla
                $evento_tipo = 'tarjeta_amarilla';
                $esLocal = (rand(1, 2) === 1);
                $equipo_id = $esLocal ? $m['equipo_local_id'] : $m['equipo_visitante_id'];
                
                $jugStmt = $pdo->prepare("SELECT id FROM jugadores WHERE equipo_id = ?");
                $jugStmt->execute([$equipo_id]);
                $jugs = $jugStmt->fetchAll();
                
                if (count($jugs) > 0) {
                    $jugador_id = $jugs[array_rand($jugs)]['id'];
                    
                    // Verificar si ya tiene amarilla en este partido
                    $chkCard = $pdo->prepare("
                        SELECT COUNT(*) AS amarillas 
                        FROM eventos_partido 
                        WHERE partido_id = ? AND jugador_id = ? AND tipo = 'tarjeta_amarilla'
                    ");
                    $chkCard->execute([$partido_id, $jugador_id]);
                    $cards = $chkCard->fetch();
                    
                    if (intval($cards['amarillas']) > 0) {
                        // Doble amarilla = Roja
                        $evento_tipo = 'tarjeta_roja';
                        $updJ = $pdo->prepare("UPDATE jugadores SET tarjetas_rojas = tarjetas_rojas + 1 WHERE id = ?");
                        $updJ->execute([$jugador_id]);
                    } else {
                        $updJ = $pdo->prepare("UPDATE jugadores SET tarjetas_amarillas = tarjetas_amarillas + 1 WHERE id = ?");
                        $updJ->execute([$jugador_id]);
                    }
                }
            } elseif ($prob > 23 && $prob <= 25) { // 2% Tarjeta Roja Directa
                $evento_tipo = 'tarjeta_roja';
                $esLocal = (rand(1, 2) === 1);
                $equipo_id = $esLocal ? $m['equipo_local_id'] : $m['equipo_visitante_id'];
                
                $jugStmt = $pdo->prepare("SELECT id FROM jugadores WHERE equipo_id = ?");
                $jugStmt->execute([$equipo_id]);
                $jugs = $jugStmt->fetchAll();
                
                if (count($jugs) > 0) {
                    $jugador_id = $jugs[array_rand($jugs)]['id'];
                    $updJ = $pdo->prepare("UPDATE jugadores SET tarjetas_rojas = tarjetas_rojas + 1 WHERE id = ?");
                    $updJ->execute([$jugador_id]);
                }
            }
            
            // Insertar evento en la DB
            if ($evento_tipo && $jugador_id) {
                $insEv = $pdo->prepare("
                    INSERT INTO eventos_partido (partido_id, tipo, minuto, equipo_id, jugador_id, asistente_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insEv->execute([$partido_id, $evento_tipo, $minuto, $equipo_id, $jugador_id, $asistente_id]);
            }
            
            // Actualizar partido
            $estado = ($minuto >= 90) ? 'finalizado' : 'en_vivo';
            $pen_local = NULL;
            $pen_visitor = NULL;
            
            // Si finaliza y es eliminatoria y están empatados, simular tanda de penaltis al instante
            if ($estado === 'finalizado' && $m['fase'] !== 'grupos' && $gl === $gv) {
                $pen_local = rand(4, 5);
                $pen_visitor = ($pen_local === 5) ? rand(3, 4) : 5;
            }
            
            $updPart = $pdo->prepare("
                UPDATE partidos 
                SET minuto_actual = ?, goles_local = ?, goles_visitante = ?, estado = ?, 
                    goles_penaltis_local = ?, goles_penaltis_visitante = ?
                WHERE id = ?
            ");
            $updPart->execute([$minuto, $gl, $gv, $estado, $pen_local, $pen_visitor, $partido_id]);
            
            $cambios[] = [
                'partido_id' => $partido_id,
                'minuto' => $minuto,
                'goles_local' => $gl,
                'goles_visitante' => $gv,
                'estado' => $estado,
                'pen_local' => $pen_local,
                'pen_visitor' => $pen_visitor,
                'evento' => $evento_tipo ? [
                    'tipo' => $evento_tipo,
                    'minuto' => $minuto,
                    'equipo_id' => $equipo_id,
                    'jugador_id' => $jugador_id
                ] : null
            ];
            
            // Si el partido se finalizó, procesar avance en el torneo
            if ($estado === 'finalizado') {
                finalizarPartido($pdo, $partido_id);
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'cambios' => $cambios
        ]);
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Acción administrativa no válida'
        ]);
        break;
}
