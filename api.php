<?php
// api.php - API Pública de Consulta para las 5 Grandes Ligas (ESPN)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

// Helper para consultas sql con JOIN de equipos y ligas
function getMatchesQuery($pdo, $whereClause = "1", $params = []) {
    $sql = "SELECT 
                p.id,
                p.equipo_local_id,
                el.nombre AS equipo_local_nombre,
                el.logo_url AS equipo_local_logo,
                el.codigo_pais AS equipo_local_codigo,
                p.equipo_visitante_id,
                ev.nombre AS equipo_visitante_nombre,
                ev.logo_url AS equipo_visitante_logo,
                ev.codigo_pais AS equipo_visitante_codigo,
                p.goles_local,
                p.goles_visitante,
                p.goles_penaltis_local,
                p.goles_penaltis_visitante,
                p.estado,
                p.fecha_hora,
                p.minuto_actual,
                p.fase,
                p.alt_game_note,
                l.nombre AS liga_nombre,
                p.liga_id
            FROM partidos p
            LEFT JOIN equipos el ON p.equipo_local_id = el.id
            LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
            LEFT JOIN ligas l ON p.liga_id = l.id
            WHERE $whereClause
            ORDER BY p.fecha_hora ASC, p.id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

try {
    switch ($action) {
        case 'get_countdown':
            // Obtener el próximo partido pendiente más cercano
            $stmt = $pdo->prepare("SELECT fecha_hora, nombre FROM partidos WHERE estado = 'pendiente' ORDER BY fecha_hora ASC LIMIT 1");
            $stmt->execute();
            $next = $stmt->fetch();
            
            echo json_encode([
                'status' => 'success',
                'final_datetime' => $next ? $next['fecha_hora'] : date('Y-m-d H:i:s', time() + 86400 * 3), // Si no hay, poner 3 días después
                'match_name' => $next ? $next['nombre'] : 'Próxima Jornada',
                'server_datetime' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'get_live_scores':
            // Obtener partidos en vivo de todas las ligas
            $live_matches = getMatchesQuery($pdo, "p.estado = 'en_vivo'");
            echo json_encode([
                'status' => 'success',
                'en_vivo' => $live_matches
            ]);
            break;

        case 'get_match_details':
            $partido_id = intval($_GET['id'] ?? 0);
            if ($partido_id <= 0) {
                throw new Exception('ID de partido no válido.');
            }

            // Consultar datos del partido
            $matches = getMatchesQuery($pdo, "p.id = ?", [$partido_id]);
            if (empty($matches)) {
                throw new Exception('Partido no encontrado.');
            }
            $m = $matches[0];

            // Consultar eventos (goles, tarjetas)
            $evStmt = $pdo->prepare("
                SELECT e.tipo, e.minuto, j.nombre AS jugador_nombre, eq.nombre AS equipo_nombre, e.equipo_id
                FROM eventos_partido e
                JOIN jugadores j ON e.jugador_id = j.id
                JOIN equipos eq ON e.equipo_id = eq.id
                WHERE e.partido_id = ?
                ORDER BY e.minuto ASC, e.id ASC
            ");
            $evStmt->execute([$partido_id]);
            $eventos = $evStmt->fetchAll();

            // Clasificar eventos por equipo local y visitante
            $eventos_local = [];
            $eventos_visitante = [];
            foreach ($eventos as $ev) {
                if (intval($ev['equipo_id']) === intval($m['equipo_local_id'])) {
                    $eventos_local[] = $ev;
                } else {
                    $eventos_visitante[] = $ev;
                }
            }

            echo json_encode([
                'status' => 'success',
                'partido' => $m,
                'eventos_local' => $eventos_local,
                'eventos_visitante' => $eventos_visitante
            ]);
            break;

        case 'get_league_data':
            $liga_id = $_GET['liga_id'] ?? '';
            if ($liga_id === '') {
                throw new Exception('ID de liga no proporcionado.');
            }

            // Obtener partidos de la liga
            $partidos = getMatchesQuery($pdo, "p.liga_id = ?", [$liga_id]);

            // Obtener standings cached de la tabla configuracion
            $config_key = "standings_{$liga_id}";
            $confStmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = ?");
            $confStmt->execute([$config_key]);
            $conf = $confStmt->fetch();
            $standings = $conf ? json_decode($conf['valor'], true) : null;

            echo json_encode([
                'status' => 'success',
                'partidos' => $partidos,
                'standings' => $standings
            ]);
            break;

        default:
            throw new Exception('Acción de API no reconocida.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
