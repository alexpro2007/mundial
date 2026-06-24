<?php
// api.php - API Pública de Consulta para el Mundial 2026 (ESPN Real-Time)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

// Helper para consultas sql con JOIN de equipos
function getMatchesQuery($pdo, $whereClause = "1") {
    $sql = "SELECT 
                p.id,
                p.equipo_local_id,
                el.nombre AS equipo_local_nombre,
                el.logo_url AS equipo_local_logo,
                el.codigo_pais AS equipo_local_codigo,
                el.grupo AS equipo_local_grupo,
                p.equipo_visitante_id,
                ev.nombre AS equipo_visitante_nombre,
                ev.logo_url AS equipo_visitante_logo,
                ev.codigo_pais AS equipo_visitante_codigo,
                ev.grupo AS equipo_visitante_grupo,
                p.goles_local,
                p.goles_visitante,
                p.goles_penaltis_local,
                p.goles_penaltis_visitante,
                p.estado,
                p.fecha_hora,
                p.minuto_actual,
                p.fase,
                p.posicion_bracket,
                p.alt_game_note,
                -- Incluir el nombre del partido de ESPN para parsear placeholders
                p.nombre AS match_name
            FROM partidos p
            LEFT JOIN equipos el ON p.equipo_local_id = el.id
            LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
            WHERE $whereClause
            ORDER BY p.fecha_hora ASC, p.id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Función para parsear nombres de marcadores de posición cuando los equipos aún no se conocen
function formatMatchPlaceholders(&$m) {
    if (empty($m['equipo_local_id']) || empty($m['equipo_visitante_id'])) {
        $name = $m['match_name'] ?? '';
        // En ESPN, el formato es "Visitante at Local" (Away at Home)
        $teamsSplit = explode(' at ', $name);
        
        if (empty($m['equipo_local_id'])) {
            $m['equipo_local_nombre'] = isset($teamsSplit[1]) ? trim($teamsSplit[1]) : 'Por definir';
            $m['equipo_local_codigo'] = 'placeholder';
            $m['equipo_local_logo'] = 'placeholder';
        }
        if (empty($m['equipo_visitante_id'])) {
            $m['equipo_visitante_nombre'] = isset($teamsSplit[0]) ? trim($teamsSplit[0]) : 'Por definir';
            $m['equipo_visitante_codigo'] = 'placeholder';
            $m['equipo_visitante_logo'] = 'placeholder';
        }
    }
}

switch ($action) {
    case 'get_countdown':
        // Obtener la gran final (fase = 'final')
        $stmt = $pdo->prepare("SELECT fecha_hora FROM partidos WHERE fase = 'final' ORDER BY fecha_hora DESC LIMIT 1");
        $stmt->execute();
        $final = $stmt->fetch();
        
        echo json_encode([
            'status' => 'success',
            'final_datetime' => $final ? $final['fecha_hora'] : '2026-07-19 19:00:00',
            'server_datetime' => date('Y-m-d H:i:s')
        ]);
        break;

    case 'get_matches':
        $all_matches = getMatchesQuery($pdo);
        
        $pendientes = [];
        $en_vivo = [];
        $finalizados = [];
        
        foreach ($all_matches as &$m) {
            formatMatchPlaceholders($m);
            
            // Obtener eventos (goles, tarjetas) para partidos en vivo o finalizados
            if ($m['estado'] !== 'pendiente') {
                $evStmt = $pdo->prepare("
                    SELECT e.tipo, e.minuto, j.nombre AS jugador_nombre, eq.nombre AS equipo_nombre
                    FROM eventos_partido e
                    JOIN jugadores j ON e.jugador_id = j.id
                    JOIN equipos eq ON e.equipo_id = eq.id
                    WHERE e.partido_id = ?
                    ORDER BY e.minuto ASC, e.id ASC
                ");
                $evStmt->execute([$m['id']]);
                $m['eventos'] = $evStmt->fetchAll();
            } else {
                $m['eventos'] = [];
            }
            
            if ($m['estado'] === 'pendiente') {
                $pendientes[] = $m;
            } elseif ($m['estado'] === 'en_vivo') {
                $en_vivo[] = $m;
            } else {
                $finalizados[] = $m;
            }
        }
        
        // Revertir los partidos finalizados para mostrar los más recientes primero
        $finalizados = array_reverse($finalizados);
        
        echo json_encode([
            'status' => 'success',
            'pendientes' => $pendientes,
            'en_vivo' => $en_vivo,
            'finalizados' => $finalizados
        ]);
        break;

    case 'get_groups':
        // Obtener todos los equipos ordenados por grupo
        $teamsStmt = $pdo->query("SELECT id, nombre, codigo_pais, logo_url, grupo FROM equipos ORDER BY grupo ASC, nombre ASC");
        $teams = $teamsStmt->fetchAll();
        
        // Obtener todos los partidos de grupos que han finalizado
        $matchesStmt = $pdo->query("
            SELECT equipo_local_id, equipo_visitante_id, goles_local, goles_visitante, 
                   el.grupo 
            FROM partidos p
            JOIN equipos el ON p.equipo_local_id = el.id
            WHERE p.fase = 'grupos' AND p.estado = 'finalizado'
        ");
        $finished_matches = $matchesStmt->fetchAll();
        
        // Inicializar tabla de clasificaciones (Grupos A a L)
        $standings = [];
        $groups = ['A','B','C','D','E','F','G','H','I','J','K','L'];
        foreach ($groups as $g) {
            $standings[$g] = [];
        }
        
        $team_stats = [];
        foreach ($teams as $t) {
            $team_stats[$t['id']] = [
                'id' => $t['id'],
                'nombre' => $t['nombre'],
                'codigo_pais' => $t['codigo_pais'],
                'logo_url' => $t['logo_url'],
                'grupo' => $t['grupo'],
                'pj' => 0,
                'pg' => 0,
                'pe' => 0,
                'pp' => 0,
                'gf' => 0,
                'gc' => 0,
                'dg' => 0,
                'puntos' => 0
            ];
        }
        
        // Calcular estadísticas
        foreach ($finished_matches as $m) {
            $locId = $m['equipo_local_id'];
            $visId = $m['equipo_visitante_id'];
            $gl = intval($m['goles_local']);
            $gv = intval($m['goles_visitante']);
            
            if (isset($team_stats[$locId]) && isset($team_stats[$visId])) {
                $team_stats[$locId]['pj']++;
                $team_stats[$visId]['pj']++;
                
                $team_stats[$locId]['gf'] += $gl;
                $team_stats[$locId]['gc'] += $gv;
                $team_stats[$visId]['gf'] += $gv;
                $team_stats[$visId]['gc'] += $gl;
                
                if ($gl > $gv) {
                    $team_stats[$locId]['pg']++;
                    $team_stats[$locId]['puntos'] += 3;
                    $team_stats[$visId]['pp']++;
                } elseif ($gl < $gv) {
                    $team_stats[$visId]['pg']++;
                    $team_stats[$visId]['puntos'] += 3;
                    $team_stats[$locId]['pp']++;
                } else {
                    $team_stats[$locId]['pe']++;
                    $team_stats[$locId]['puntos'] += 1;
                    $team_stats[$visId]['pe']++;
                    $team_stats[$visId]['puntos'] += 1;
                }
            }
        }
        
        // Distribuir en sus grupos y ordenar
        foreach ($team_stats as $tid => $stat) {
            $grp = $stat['grupo'];
            if (isset($standings[$grp])) {
                $stat['dg'] = $stat['gf'] - $stat['gc'];
                $standings[$grp][] = $stat;
            }
        }
        
        // Ordenar cada grupo
        foreach ($standings as $grp => &$groupTeams) {
            usort($groupTeams, function($a, $b) {
                if ($a['puntos'] !== $b['puntos']) {
                    return $b['puntos'] <=> $a['puntos'];
                }
                if ($a['dg'] !== $b['dg']) {
                    return $b['dg'] <=> $a['dg'];
                }
                if ($a['gf'] !== $b['gf']) {
                    return $b['gf'] <=> $a['gf'];
                }
                return strcmp($a['nombre'], $b['nombre']);
            });
        }
        
        echo json_encode([
            'status' => 'success',
            'grupos' => $standings
        ]);
        break;

    case 'get_stats':
        // Goleadores reales
        $goleadores = $pdo->query("
            SELECT j.nombre, e.nombre AS equipo_nombre, e.logo_url AS equipo_logo, e.codigo_pais, j.goles 
            FROM jugadores j
            JOIN equipos e ON j.equipo_id = e.id
            WHERE j.goles > 0
            ORDER BY j.goles DESC, j.nombre ASC
            LIMIT 5
        ")->fetchAll();
        
        // Asistentes reales
        $asistentes = $pdo->query("
            SELECT j.nombre, e.nombre AS equipo_nombre, e.logo_url AS equipo_logo, e.codigo_pais, j.asistencias 
            FROM jugadores j
            JOIN equipos e ON j.equipo_id = e.id
            WHERE j.asistencias > 0
            ORDER BY j.asistencias DESC, j.nombre ASC
            LIMIT 5
        ")->fetchAll();
        
        // Amarillas reales
        $amarillas = $pdo->query("
            SELECT j.nombre, e.nombre AS equipo_nombre, e.logo_url AS equipo_logo, e.codigo_pais, j.tarjetas_amarillas 
            FROM jugadores j
            JOIN equipos e ON j.equipo_id = e.id
            WHERE j.tarjetas_amarillas > 0
            ORDER BY j.tarjetas_amarillas DESC, j.nombre ASC
            LIMIT 5
        ")->fetchAll();
        
        // Rojas reales
        $rojas = $pdo->query("
            SELECT j.nombre, e.nombre AS equipo_nombre, e.logo_url AS equipo_logo, e.codigo_pais, j.tarjetas_rojas 
            FROM jugadores j
            JOIN equipos e ON j.equipo_id = e.id
            WHERE j.tarjetas_rojas > 0
            ORDER BY j.tarjetas_rojas DESC, j.nombre ASC
            LIMIT 5
        ")->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'goleadores' => $goleadores,
            'asistentes' => $asistentes,
            'amarillas' => $amarillas,
            'rojas' => $rojas
        ]);
        break;

    case 'get_bracket':
        $bracket_matches = getMatchesQuery($pdo, "p.fase != 'grupos'");
        
        $bracket = [
            'dieciseisavos' => [],
            'octavos' => [],
            'cuartos' => [],
            'semifinal' => [],
            'tercer_puesto' => [],
            'final' => []
        ];
        
        foreach ($bracket_matches as &$m) {
            formatMatchPlaceholders($m);
            $bracket[$m['fase']][] = $m;
        }
        
        // Ordenar por posicion_bracket
        foreach ($bracket as $fase => &$partidos_fase) {
            usort($partidos_fase, function($a, $b) {
                return $a['posicion_bracket'] <=> $b['posicion_bracket'];
            });
        }
        
        echo json_encode([
            'status' => 'success',
            'bracket' => $bracket
        ]);
        break;

    case 'get_lineups':
        $match_id = intval($_GET['match_id'] ?? 0);
        if ($match_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de partido no válido']);
            exit;
        }

        // Obtener el partido de la BD local
        $stmt = $pdo->prepare("
            SELECT p.id, p.estado, p.equipo_local_id, p.equipo_visitante_id, p.goles_local, p.goles_visitante,
                   el.nombre AS equipo_local_nombre, el.logo_url AS equipo_local_logo, el.codigo_pais AS equipo_local_codigo,
                   ev.nombre AS equipo_visitante_nombre, ev.logo_url AS equipo_visitante_logo, ev.codigo_pais AS equipo_visitante_codigo
            FROM partidos p
            LEFT JOIN equipos el ON p.equipo_local_id = el.id
            LEFT JOIN equipos ev ON p.equipo_visitante_id = ev.id
            WHERE p.id = ?
        ");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch();

        if (!$match) {
            echo json_encode(['status' => 'error', 'message' => 'Partido no encontrado']);
            exit;
        }

        // Intentar obtener alineaciones desde la API de ESPN
        $rosters = [];
        $is_official = false;

        $url = "https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/summary?event=" . $match_id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $json = curl_exec($ch);
        curl_close($ch);

        if ($json) {
            $data = json_decode($json, true);
            if (isset($data['rosters']) && !empty($data['rosters'])) {
                // Verificar si hay alineaciones reales
                $has_starters = false;
                foreach ($data['rosters'] as $side => $teamRoster) {
                    $team_id = $side == 0 ? 'local' : 'visitante';
                    $rosters[$team_id] = [
                        'starters' => [],
                        'bench' => []
                    ];
                    
                    if (isset($teamRoster['roster'])) {
                        foreach ($teamRoster['roster'] as $player) {
                            $p_info = [
                                'nombre' => $player['athlete']['displayName'] ?? $player['athlete']['fullName'] ?? 'Jugador',
                                'dorsal' => $player['jersey'] ?? '',
                                'posicion' => $player['position']['displayName'] ?? $player['position']['name'] ?? 'Centrocampista',
                                'pos_abbr' => $player['position']['abbreviation'] ?? 'M'
                            ];
                            if (!empty($player['starter'])) {
                                $rosters[$team_id]['starters'][] = $p_info;
                                $has_starters = true;
                            } else {
                                $rosters[$team_id]['bench'][] = $p_info;
                            }
                        }
                    }
                }
                if ($has_starters) {
                    $is_official = ($match['estado'] !== 'pendiente');
                }
            }
        }

        // Si no se obtuvieron rosters reales de ESPN, los generamos de forma probable
        if (empty($rosters)) {
            $rosters = [
                'local' => apiGenerateProbableRoster($pdo, $match['equipo_local_id'], $match['equipo_local_nombre']),
                'visitante' => apiGenerateProbableRoster($pdo, $match['equipo_visitante_id'], $match['equipo_visitante_nombre'])
            ];
            $is_official = false;
        }

        echo json_encode([
            'status' => 'success',
            'match' => [
                'id' => $match['id'],
                'estado' => $match['estado'],
                'goles_local' => $match['goles_local'],
                'goles_visitante' => $match['goles_visitante'],
                'local' => [
                    'nombre' => $match['equipo_local_nombre'] ?? 'Por definir',
                    'logo' => $match['equipo_local_logo'] ?? '',
                    'codigo' => $match['equipo_local_codigo'] ?? 'placeholder'
                ],
                'visitante' => [
                    'nombre' => $match['equipo_visitante_nombre'] ?? 'Por definir',
                    'logo' => $match['equipo_visitante_logo'] ?? '',
                    'codigo' => $match['equipo_visitante_codigo'] ?? 'placeholder'
                ]
            ],
            'is_official' => $is_official,
            'rosters' => $rosters
        ]);
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Acción no válida'
        ]);
        break;
}

// ------------------------------------------------------------
// Funciones Auxiliares para Generación de Alineaciones Probables
// ------------------------------------------------------------
function apiGenerateProbableRoster($pdo, $equipo_id, $equipo_nombre) {
    $starters = [];
    $bench = [];

    if ($equipo_id) {
        $stmt = $pdo->prepare("SELECT nombre FROM jugadores WHERE equipo_id = ? LIMIT 18");
        $stmt->execute([$equipo_id]);
        $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($players) >= 11) {
            for ($i = 0; $i < 11; $i++) {
                $starters[] = [
                    'nombre' => $players[$i],
                    'dorsal' => $i + 1,
                    'posicion' => apiGetPositionNameByNumber($i + 1),
                    'pos_abbr' => apiGetPositionAbbrByNumber($i + 1)
                ];
            }
            for ($i = 11; $i < count($players); $i++) {
                $bench[] = [
                    'nombre' => $players[$i],
                    'dorsal' => $i + 1,
                    'posicion' => 'Suplente',
                    'pos_abbr' => 'S'
                ];
            }
            return ['starters' => $starters, 'bench' => $bench];
        }
    }

    $common_names = apiGetProbableNamesForCountry($equipo_nombre);
    for ($i = 0; $i < 11; $i++) {
        $starters[] = [
            'nombre' => $common_names[$i] ?? ("Jugador " . ($i + 1)),
            'dorsal' => $i + 1,
            'posicion' => apiGetPositionNameByNumber($i + 1),
            'pos_abbr' => apiGetPositionAbbrByNumber($i + 1)
        ];
    }
    for ($i = 11; $i < 18; $i++) {
        $bench[] = [
            'nombre' => $common_names[$i] ?? ("Suplente " . ($i - 10)),
            'dorsal' => $i + 1,
            'posicion' => 'Suplente',
            'pos_abbr' => 'S'
        ];
    }

    return ['starters' => $starters, 'bench' => $bench];
}

function apiGetPositionNameByNumber($num) {
    if ($num == 1) return 'Guardameta';
    if ($num >= 2 && $num <= 5) return 'Defensa';
    if ($num >= 6 && $num <= 8) return 'Centrocampista';
    return 'Delantero';
}

function apiGetPositionAbbrByNumber($num) {
    if ($num == 1) return 'POR';
    if ($num >= 2 && $num <= 5) return 'DEF';
    if ($num >= 6 && $num <= 8) return 'MED';
    return 'DEL';
}

function apiGetProbableNamesForCountry($country) {
    $country = strtolower($country);
    
    // Diccionario de planteles reales de Fútbol Fantasy / Alineaciones Seguras
    $rosters = [
        'spain' => ['Unai Simón', 'Dani Carvajal', 'Robin Le Normand', 'Aymeric Laporte', 'Marc Cucurella', 'Rodri', 'Fabián Ruiz', 'Dani Olmo', 'Lamine Yamal', 'Álvaro Morata', 'Nico Williams', 'David Raya', 'Alex Grimaldo', 'Dani Vivian', 'Martín Zubimendi', 'Mikel Merino', 'Pedri', 'Ferran Torres'],
        'españ' => ['Unai Simón', 'Dani Carvajal', 'Robin Le Normand', 'Aymeric Laporte', 'Marc Cucurella', 'Rodri', 'Fabián Ruiz', 'Dani Olmo', 'Lamine Yamal', 'Álvaro Morata', 'Nico Williams', 'David Raya', 'Alex Grimaldo', 'Dani Vivian', 'Martín Zubimendi', 'Mikel Merino', 'Pedri', 'Ferran Torres'],
        'argentin' => ['Emiliano Martínez', 'Nahuel Molina', 'Cristian Romero', 'Nicolás Otamendi', 'Nicolás Tagliafico', 'Rodrigo De Paul', 'Enzo Fernández', 'Alexis Mac Allister', 'Lionel Messi', 'Julián Álvarez', 'Lautaro Martínez', 'Gerónimo Rulli', 'Gonzalo Montiel', 'Germán Pezzella', 'Leandro Paredes', 'Giovani Lo Celso', 'Alejandro Garnacho', 'Nicolás González'],
        'brasil' => ['Alisson Becker', 'Danilo', 'Éder Militão', 'Marquinhos', 'Guilherme Arana', 'Bruno Guimarães', 'João Gomes', 'Lucas Paquetá', 'Raphinha', 'Rodrygo', 'Vinícius Júnior', 'Bento', 'Lucas Beraldo', 'Andreas Pereira', 'Douglas Luiz', 'Gabriel Martinelli', 'Endrick', 'Evanilson'],
        'brazil' => ['Alisson Becker', 'Danilo', 'Éder Militão', 'Marquinhos', 'Guilherme Arana', 'Bruno Guimarães', 'João Gomes', 'Lucas Paquetá', 'Raphinha', 'Rodrygo', 'Vinícius Júnior', 'Bento', 'Lucas Beraldo', 'Andreas Pereira', 'Douglas Luiz', 'Gabriel Martinelli', 'Endrick', 'Evanilson'],
        'franc' => ['Mike Maignan', 'Jules Koundé', 'Dayot Upamecano', 'William Saliba', 'Theo Hernández', 'N\'Golo Kanté', 'Aurélien Tchouaméni', 'Adrien Rabiot', 'Ousmane Dembélé', 'Marcus Thuram', 'Kylian Mbappé', 'Brice Samba', 'Benjamin Pavard', 'Ibrahima Konaté', 'Eduardo Camavinga', 'Antoine Griezmann', 'Olivier Giroud', 'Kingsley Coman'],
        'alem' => ['Manuel Neuer', 'Joshua Kimmich', 'Antonio Rüdiger', 'Jonathan Tah', 'Maximilian Mittelstädt', 'Robert Andrich', 'Toni Kroos', 'Ilkay Gündogan', 'Jamal Musiala', 'Florian Wirtz', 'Kai Havertz', 'Marc-André ter Stegen', 'David Raum', 'Nico Schlotterbeck', 'Pascal Gross', 'Thomas Müller', 'Leroy Sané', 'Niclas Füllkrug'],
        'germany' => ['Manuel Neuer', 'Joshua Kimmich', 'Antonio Rüdiger', 'Jonathan Tah', 'Maximilian Mittelstädt', 'Robert Andrich', 'Toni Kroos', 'Ilkay Gündogan', 'Jamal Musiala', 'Florian Wirtz', 'Kai Havertz', 'Marc-André ter Stegen', 'David Raum', 'Nico Schlotterbeck', 'Pascal Gross', 'Thomas Müller', 'Leroy Sané', 'Niclas Füllkrug'],
        'inglat' => ['Jordan Pickford', 'Kyle Walker', 'John Stones', 'Marc Guéhi', 'Kieran Trippier', 'Kobbie Mainoo', 'Declan Rice', 'Jude Bellingham', 'Bukayo Saka', 'Harry Kane', 'Phil Foden', 'Aaron Ramsdale', 'Joe Gomez', 'Lewis Dunk', 'Conor Gallagher', 'Cole Palmer', 'Jarrod Bowen', 'Ollie Watkins'],
        'england' => ['Jordan Pickford', 'Kyle Walker', 'John Stones', 'Marc Guéhi', 'Kieran Trippier', 'Kobbie Mainoo', 'Declan Rice', 'Jude Bellingham', 'Bukayo Saka', 'Harry Kane', 'Phil Foden', 'Aaron Ramsdale', 'Joe Gomez', 'Lewis Dunk', 'Conor Gallagher', 'Cole Palmer', 'Jarrod Bowen', 'Ollie Watkins'],
        'portug' => ['Diogo Costa', 'João Cancelo', 'Rúben Dias', 'Pepe', 'Nuno Mendes', 'João Palhinha', 'Vitinha', 'Bruno Fernandes', 'Bernardo Silva', 'Cristiano Ronaldo', 'Rafael Leão', 'Rui Patrício', 'Diogo Dalot', 'António Silva', 'João Neves', 'Ruben Neves', 'Diogo Jota', 'João Félix'],
        'nether' => ['Bart Verbruggen', 'Denzel Dumfries', 'Stefan de Vrij', 'Virgil van Dijk', 'Nathan Aké', 'Jerdy Schouten', 'Tijjani Reijnders', 'Xavi Simons', 'Jeremie Frimpong', 'Memphis Depay', 'Cody Gakpo', 'Mark Flekken', 'Lutsharel Geertruida', 'Micky van de Ven', 'Georginio Wijnaldum', 'Joey Veerman', 'Donyell Malen', 'Wout Weghorst'],
        'países bajos' => ['Bart Verbruggen', 'Denzel Dumfries', 'Stefan de Vrij', 'Virgil van Dijk', 'Nathan Aké', 'Jerdy Schouten', 'Tijjani Reijnders', 'Xavi Simons', 'Jeremie Frimpong', 'Memphis Depay', 'Cody Gakpo', 'Mark Flekken', 'Lutsharel Geertruida', 'Micky van de Ven', 'Georginio Wijnaldum', 'Joey Veerman', 'Donyell Malen', 'Wout Weghorst'],
        'uruguay' => ['Sergio Rochet', 'Nahitan Nández', 'Ronald Araújo', 'Mathías Olivera', 'Matías Viña', 'Federico Valverde', 'Manuel Ugarte', 'Nicolás de la Cruz', 'Facundo Pellistri', 'Darwin Núñez', 'Maximiliano Araújo', 'Santiago Mele', 'José María Giménez', 'Guillermo Varela', 'Rodrigo Bentancur', 'Giorgian de Arrascaeta', 'Luis Suárez', 'Facundo Torres'],
        'united states' => ['Matt Turner', 'Joe Scally', 'Chris Richards', 'Tim Ream', 'Antonee Robinson', 'Weston McKennie', 'Tyler Adams', 'Yunis Musah', 'Timothy Weah', 'Folarin Balogun', 'Christian Pulisic', 'Ethan Horvath', 'Miles Robinson', 'Cameron Carter-Vickers', 'Johnny Cardoso', 'Luca de la Torre', 'Brenden Aaronson', 'Ricardo Pepi'],
        'estados unidos' => ['Matt Turner', 'Joe Scally', 'Chris Richards', 'Tim Ream', 'Antonee Robinson', 'Weston McKennie', 'Tyler Adams', 'Yunis Musah', 'Timothy Weah', 'Folarin Balogun', 'Christian Pulisic', 'Ethan Horvath', 'Miles Robinson', 'Cameron Carter-Vickers', 'Johnny Cardoso', 'Luca de la Torre', 'Brenden Aaronson', 'Ricardo Pepi'],
        'mexic' => ['Julio González', 'Jorge Sánchez', 'César Montes', 'Johan Vásquez', 'Gerardo Arteaga', 'Luis Romo', 'Edson Álvarez', 'Luis Chávez', 'Uriel Antuna', 'Santiago Giménez', 'Julián Quiñones', 'Carlos Acevedo', 'Israel Reyes', 'Bryan González', 'Erick Sánchez', 'Orbelín Pineda', 'Alexis Vega', 'Guillermo Martínez'],
        'colomb' => ['Camilo Vargas', 'Daniel Muñoz', 'Davinson Sánchez', 'Carlos Cuesta', 'Johan Mojica', 'Richard Ríos', 'Jefferson Lerma', 'Jhon Arias', 'James Rodríguez', 'Luis Díaz', 'Jhon Córdoba', 'David Ospina', 'Santiago Arias', 'Yerry Mina', 'Mateus Uribe', 'Juan Fernando Quintero', 'Jhon Durán', 'Rafael Santos Borré'],
        'croat' => ['Dominik Livakovic', 'Josip Stanisic', 'Josip Sutalo', 'Marin Pongracic', 'Josko Gvardiol', 'Luka Modric', 'Marcelo Brozovic', 'Mateo Kovacic', 'Lovro Majer', 'Bruno Petkovic', 'Andrej Kramaric', 'Nediljko Labrovic', 'Domagoj Vida', 'Borna Sosa', 'Luka Sucic', 'Mario Pasalic', 'Ivan Perisic', 'Ante Budimir'],
        'croacia' => ['Dominik Livakovic', 'Josip Stanisic', 'Josip Sutalo', 'Marin Pongracic', 'Josko Gvardiol', 'Luka Modric', 'Marcelo Brozovic', 'Mateo Kovacic', 'Lovro Majer', 'Bruno Petkovic', 'Andrej Kramaric', 'Nediljko Labrovic', 'Domagoj Vida', 'Borna Sosa', 'Luka Sucic', 'Mario Pasalic', 'Ivan Perisic', 'Ante Budimir'],
        'belgi' => ['Koen Casteels', 'Timothy Castagne', 'Wout Faes', 'Jan Vertonghen', 'Arthur Theate', 'Orel Mangala', 'Amadou Onana', 'Kevin De Bruyne', 'Jeremy Doku', 'Romelu Lukaku', 'Leandro Trossard', 'Thomas Kaminski', 'Zeno Debast', 'Maxim De Cuyper', 'Youri Tielemans', 'Charles De Ketelaere', 'Johan Bakayoko', 'Dodi Lukebakio'],
        'bélgica' => ['Koen Casteels', 'Timothy Castagne', 'Wout Faes', 'Jan Vertonghen', 'Arthur Theate', 'Orel Mangala', 'Amadou Onana', 'Kevin De Bruyne', 'Jeremy Doku', 'Romelu Lukaku', 'Leandro Trossard', 'Thomas Kaminski', 'Zeno Debast', 'Maxim De Cuyper', 'Youri Tielemans', 'Charles De Ketelaere', 'Johan Bakayoko', 'Dodi Lukebakio'],
        'moroc' => ['Yassine Bounou', 'Achraf Hakimi', 'Nayef Aguerd', 'Romain Saïss', 'Yahia Attiyat Allah', 'Sofyan Amrabat', 'Azzedine Ounahi', 'Selim Amallah', 'Hakim Ziyech', 'Youssef En-Nesyri', 'Sofiane Boufal', 'Munir Mohamedi', 'Abde Ezzalzouli', 'Bilal El Khannouss', 'Amine Harit', 'Ismael Saibari', 'Tarik Tissoudali', 'Ayoub El Kaabi'],
        'marruecos' => ['Yassine Bounou', 'Achraf Hakimi', 'Nayef Aguerd', 'Romain Saïss', 'Yahia Attiyat Allah', 'Sofyan Amrabat', 'Azzedine Ounahi', 'Selim Amallah', 'Hakim Ziyech', 'Youssef En-Nesyri', 'Sofiane Boufal', 'Munir Mohamedi', 'Abde Ezzalzouli', 'Bilal El Khannouss', 'Amine Harit', 'Ismael Saibari', 'Tarik Tissoudali', 'Ayoub El Kaabi'],
        'alger' => ['Anthony Mandrea', 'Youcef Atal', 'Aïssa Mandi', 'Ramy Bensebaini', 'Rayan Aït-Nouri', 'Nabil Bentaleb', 'Ismaël Bennacer', 'Ramiz Zerrouki', 'Riyad Mahrez', 'Baghdad Bounedjah', 'Amine Gouiri', 'Moustafa Zeghba', 'Kevin Guitoun', 'Zineddine Belaïd', 'Houssem Aouar', 'Fares Chaïbi', 'Saïd Benrahma', 'Islam Slimani'],
        'argelia' => ['Anthony Mandrea', 'Youcef Atal', 'Aïssa Mandi', 'Ramy Bensebaini', 'Rayan Aït-Nouri', 'Nabil Bentaleb', 'Ismaël Bennacer', 'Ramiz Zerrouki', 'Riyad Mahrez', 'Baghdad Bounedjah', 'Amine Gouiri', 'Moustafa Zeghba', 'Kevin Guitoun', 'Zineddine Belaïd', 'Houssem Aouar', 'Fares Chaïbi', 'Saïd Benrahma', 'Islam Slimani'],
        'tunis' => ['Bechir Ben Saïd', 'Wajdi Kechrida', 'Yassine Meriah', 'Montassar Talbi', 'Ali Abdi', 'Ellyes Skhiri', 'Aïssa Laïdouni', 'Anis Ben Slimane', 'Hamza Rafia', 'Youssef Msakni', 'Elias Achouri', 'Mouez Hassen', 'Yan Valery', 'Dylan Bronn', 'Mohamed Ali Ben Romdhane', 'Sayfallah Ltaief', 'Bassem Srarfi', 'Haythem Jouini'],
        'túnez' => ['Bechir Ben Saïd', 'Wajdi Kechrida', 'Yassine Meriah', 'Montassar Talbi', 'Ali Abdi', 'Ellyes Skhiri', 'Aïssa Laïdouni', 'Anis Ben Slimane', 'Hamza Rafia', 'Youssef Msakni', 'Elias Achouri', 'Mouez Hassen', 'Yan Valery', 'Dylan Bronn', 'Mohamed Ali Ben Romdhane', 'Sayfallah Ltaief', 'Bassem Srarfi', 'Haythem Jouini'],
        'senegal' => ['Édouard Mendy', 'Youssouf Sabaly', 'Kalidou Koulibaly', 'Abdou Diallo', 'Ismail Jakobs', 'Pape Gueye', 'Lamine Camara', 'Pape Matar Sarr', 'Ismaïla Sarr', 'Nicolas Jackson', 'Sadio Mané', 'Mory Diaw', 'Formose Mendy', 'Abdoulaye Seck', 'Nampalys Mendy', 'Idrissa Gueye', 'Iliman Ndiaye', 'Habib Diallo'],
        'ivory coast' => ['Yahia Fofana', 'Wilfried Singo', 'Willy Boly', 'Evan Ndicka', 'Ghislain Konan', 'Franck Kessié', 'Jean Michaël Seri', 'Seko Fofana', 'Max Gradel', 'Sébastien Haller', 'Simon Adingra', 'Badra Ali Sangaré', 'Ousmane Diomande', 'Odilon Kossounou', 'Ibrahim Sangaré', 'Jeremie Boga', 'Christian Kouamé', 'Karim Konaté'],
        'costa de marfil' => ['Yahia Fofana', 'Wilfried Singo', 'Willy Boly', 'Evan Ndicka', 'Ghislain Konan', 'Franck Kessié', 'Jean Michaël Seri', 'Seko Fofana', 'Max Gradel', 'Sébastien Haller', 'Simon Adingra', 'Badra Ali Sangaré', 'Ousmane Diomande', 'Odilon Kossounou', 'Ibrahim Sangaré', 'Jeremie Boga', 'Christian Kouamé', 'Karim Konaté'],
        'south africa' => ['Ronwen Williams', 'Khuliso Mudau', 'Grant Kekana', 'Mothobi Mvala', 'Aubrey Modiba', 'Teboho Mokoena', 'Sphephelo Sithole', 'Thapelo Morena', 'Themba Zwane', 'Percy Tau', 'Evidence Makgopa', 'Veli Mothwa', 'Nkosinathi Sibisi', 'Terrence Mashego', 'Thabang Monare', 'Zakhele Lepasa', 'Mihlali Mayambela', 'Oswin Appollis'],
        'sudáfrica' => ['Ronwen Williams', 'Khuliso Mudau', 'Grant Kekana', 'Mothobi Mvala', 'Aubrey Modiba', 'Teboho Mokoena', 'Sphephelo Sithole', 'Thapelo Morena', 'Themba Zwane', 'Percy Tau', 'Evidence Makgopa', 'Veli Mothwa', 'Nkosinathi Sibisi', 'Terrence Mashego', 'Thabang Monare', 'Zakhele Lepasa', 'Mihlali Mayambela', 'Oswin Appollis'],
        'south korea' => ['Jo Hyeon-woo', 'Kim Moon-hwan', 'Kim Min-jae', 'Kim Young-gwon', 'Kim Jin-su', 'Hwang In-beom', 'Park Yong-woo', 'Lee Jae-sung', 'Lee Kang-in', 'Son Heung-min', 'Hwang Hee-chan', 'Song Bum-keun', 'Jung Seung-hyun', 'Seol Young-woo', 'Hong Hyun-seok', 'Jeong Woo-yeong', 'Cho Gue-sung', 'Oh Hyeon-gyu'],
        'corea del sur' => ['Jo Hyeon-woo', 'Kim Moon-hwan', 'Kim Min-jae', 'Kim Young-gwon', 'Kim Jin-su', 'Hwang In-beom', 'Park Yong-woo', 'Lee Jae-sung', 'Lee Kang-in', 'Son Heung-min', 'Hwang Hee-chan', 'Song Bum-keun', 'Jung Seung-hyun', 'Seol Young-woo', 'Hong Hyun-seok', 'Jeong Woo-yeong', 'Cho Gue-sung', 'Oh Hyeon-gyu'],
        'japan' => ['Zion Suzuki', 'Yukinari Sugawara', 'Ko Itakura', 'Shogo Taniguchi', 'Hiroki Ito', 'Wataru Endo', 'Hidemasa Morita', 'Ritsu Doan', 'Takefusa Kubo', 'Takumi Minamino', 'Ayase Ueda', 'Keisuke Osako', 'Koki Machida', 'Seiya Maikuma', 'Reo Hatate', 'Keito Nakamura', 'Daizen Maeda', 'Mao Hosoya'],
        'japón' => ['Zion Suzuki', 'Yukinari Sugawara', 'Ko Itakura', 'Shogo Taniguchi', 'Hiroki Ito', 'Wataru Endo', 'Hidemasa Morita', 'Ritsu Doan', 'Takefusa Kubo', 'Takumi Minamino', 'Ayase Ueda', 'Keisuke Osako', 'Koki Machida', 'Seiya Maikuma', 'Reo Hatate', 'Keito Nakamura', 'Daizen Maeda', 'Mao Hosoya'],
        'saudi arabia' => ['Mohammed Al-Owais', 'Saud Abdulhamid', 'Ali Lajami', 'Awn Al-Saluli', 'Ali Al-Bulaihi', 'Faisal Al-Ghamdi', 'Mukhtar Ali', 'Abdulelah Al-Malki', 'Salem Al-Dawsari', 'Firas Al-Buraikan', 'Saleh Al-Shehri', 'Ahmed Al-Kassar', 'Hassan Kadesh', 'Yasser Al-Shahrani', 'Mohamed Kanno', 'Sami Al-Najei', 'Abdulrahman Ghareeb', 'Abdullah Radif'],
        'arabia saudí' => ['Mohammed Al-Owais', 'Saud Abdulhamid', 'Ali Lajami', 'Awn Al-Saluli', 'Ali Al-Bulaihi', 'Faisal Al-Ghamdi', 'Mukhtar Ali', 'Abdulelah Al-Malki', 'Salem Al-Dawsari', 'Firas Al-Buraikan', 'Saleh Al-Shehri', 'Ahmed Al-Kassar', 'Hassan Kadesh', 'Yasser Al-Shahrani', 'Mohamed Kanno', 'Sami Al-Najei', 'Abdulrahman Ghareeb', 'Abdullah Radif'],
        'australia' => ['Mathew Ryan', 'Gethin Jones', 'Harry Souttar', 'Kye Rowles', 'Jordan Bos', 'Jackson Irvine', 'Keanu Baccus', 'Connor Metcalfe', 'Craig Goodwin', 'Mitchell Duke', 'Martin Boyle', 'Joe Gauci', 'Cameron Burgess', 'Lewis Miller', 'Riley McGree', 'Aiden O\'Neill', 'Samuel Silvera', 'Bruno Fornaroli'],
        'sweden' => ['Robin Olsen', 'Emil Holm', 'Victor Lindelöf', 'Isak Hien', 'Ludwig Augustinsson', 'Jens Cajuste', 'Anton Salétros', 'Dejan Kulusevski', 'Alexander Isak', 'Emil Forsberg', 'Viktor Gyökeres', 'Viktor Johansson', 'Carl Starfelt', 'Samuel Dahl', 'Mattias Svanberg', 'Hugo Larsson', 'Anthony Elanga', 'Gustaf Nilsson'],
        'suecia' => ['Robin Olsen', 'Emil Holm', 'Victor Lindelöf', 'Isak Hien', 'Ludwig Augustinsson', 'Jens Cajuste', 'Anton Salétros', 'Dejan Kulusevski', 'Alexander Isak', 'Emil Forsberg', 'Viktor Gyökeres', 'Viktor Johansson', 'Carl Starfelt', 'Samuel Dahl', 'Mattias Svanberg', 'Hugo Larsson', 'Anthony Elanga', 'Gustaf Nilsson'],
        'switzer' => ['Yann Sommer', 'Silvan Widmer', 'Manuel Akanji', 'Ricardo Rodríguez', 'Dan Ndoye', 'Remo Freuler', 'Granit Xhaka', 'Michel Aebischer', 'Fabian Rieder', 'Ruben Vargas', 'Breel Embolo', 'Gregor Kobel', 'Nico Elvedi', 'Leonidas Stergiou', 'Vincent Sierro', 'Denis Zakaria', 'Xherdan Shaqiri', 'Zeki Amdouni'],
        'suiza' => ['Yann Sommer', 'Silvan Widmer', 'Manuel Akanji', 'Ricardo Rodríguez', 'Dan Ndoye', 'Remo Freuler', 'Granit Xhaka', 'Michel Aebischer', 'Fabian Rieder', 'Ruben Vargas', 'Breel Embolo', 'Gregor Kobel', 'Nico Elvedi', 'Leonidas Stergiou', 'Vincent Sierro', 'Denis Zakaria', 'Xherdan Shaqiri', 'Zeki Amdouni'],
        'türkiy' => ['Mert Günok', 'Zeki Çelik', 'Samet Akaydin', 'Abdülkerim Bardakcı', 'Ferdi Kadıoğlu', 'Hakan Çalhanoğlu', 'Kaan Ayhan', 'Arda Güler', 'Orkun Kökçü', 'Kenan Yıldız', 'Barış Alper Yılmaz', 'Uğurcan Çakır', 'Merih Demiral', 'Mert Müldür', 'Okay Yokuşlu', 'Salih Özcan', 'Kerem Aktürkoğlu', 'Cenk Tosun'],
        'turquía' => ['Mert Günok', 'Zeki Çelik', 'Samet Akaydin', 'Abdülkerim Bardakcı', 'Ferdi Kadıoğlu', 'Hakan Çalhanoğlu', 'Kaan Ayhan', 'Arda Güler', 'Orkun Kökçü', 'Kenan Yıldız', 'Barış Alper Yılmaz', 'Uğurcan Çakır', 'Merih Demiral', 'Mert Müldür', 'Okay Yokuşlu', 'Salih Özcan', 'Kerem Aktürkoğlu', 'Cenk Tosun'],
        'czechia' => ['Jindrich Stanek', 'Tomas Holes', 'Robin Hranac', 'Ladislav Krejci', 'Vladimir Coufal', 'Tomas Soucek', 'Lukas Provod', 'David Doudera', 'Antonin Barak', 'Jan Kuchta', 'Patrik Schick', 'Matej Kovar', 'David Zima', 'Tomas Vlcek', 'David Jurasek', 'Antonin Barak', 'Vaclav Cerny', 'Adam Hlozek'],
        'chequia' => ['Jindrich Stanek', 'Tomas Holes', 'Robin Hranac', 'Ladislav Krejci', 'Vladimir Coufal', 'Tomas Soucek', 'Lukas Provod', 'David Doudera', 'Antonin Barak', 'Jan Kuchta', 'Patrik Schick', 'Matej Kovar', 'David Zima', 'Tomas Vlcek', 'David Jurasek', 'Antonin Barak', 'Vaclav Cerny', 'Adam Hlozek'],
        'ecuador' => ['Alexander Domínguez', 'Angelo Preciado', 'Félix Torres', 'Willian Pacho', 'Piero Hincapié', 'Alan Franco', 'Carlos Gruezo', 'Moises Caicedo', 'Kendry Páez', 'Enner Valencia', 'Jeremy Sarmiento', 'Hernán Galíndez', 'Joel Ordóñez', 'Layan Loor', 'Joao Ortiz', 'Jose Cifuentes', 'Angel Mena', 'Kevin Rodríguez'],
        'egypt' => ['Mohamed El Shenawy', 'Mohamed Hany', 'Ramy Rabia', 'Mohamed Abdelmonem', 'Mohamed Hamdy', 'Marwan Attia', 'Hamdi Fathi', 'Eman Ashour', 'Mohamed Salah', 'Mostafa Mohamed', 'Trézéguet', 'Mohamed Sobhy', 'Yasser Ibrahim', 'Akram Tawfik', 'Zizo', 'Mustafa Fathi', 'Sherif', 'Koka'],
        'egipto' => ['Mohamed El Shenawy', 'Mohamed Hany', 'Ramy Rabia', 'Mohamed Abdelmonem', 'Mohamed Hamdy', 'Marwan Attia', 'Hamdi Fathi', 'Eman Ashour', 'Mohamed Salah', 'Mostafa Mohamed', 'Trézéguet', 'Mohamed Sobhy', 'Yasser Ibrahim', 'Akram Tawfik', 'Zizo', 'Mustafa Fathi', 'Sherif', 'Koka'],
        'canada' => ['Maxime Crépeau', 'Alistair Johnston', 'Moïse Bombito', 'Derek Cornelius', 'Alphonso Davies', 'Ismaël Koné', 'Stephen Eustáquio', 'Tajon Buchanan', 'Jonathan David', 'Cyle Larin', 'Liam Millar', 'Dayne St. Clair', 'Kamal Miller', 'Richie Laryea', 'Samuel Piette', 'Jacob Shaffelburg', 'Tani Oluwaseyi', 'Theo Bair'],
        'canadá' => ['Maxime Crépeau', 'Alistair Johnston', 'Moïse Bombito', 'Derek Cornelius', 'Alphonso Davies', 'Ismaël Koné', 'Stephen Eustáquio', 'Tajon Buchanan', 'Jonathan David', 'Cyle Larin', 'Liam Millar', 'Dayne St. Clair', 'Kamal Miller', 'Richie Laryea', 'Samuel Piette', 'Jacob Shaffelburg', 'Tani Oluwaseyi', 'Theo Bair'],
        'qatar' => ['Meshaal Barsham', 'Pedro Miguel', 'Al-Mahdi Ali Mukhtar', 'Lucas Mendes', 'Homam Ahmed', 'Jassem Gaber', 'Ahmed Fathy', 'Mohammed Waad', 'Almoez Ali', 'Akram Afif', 'Yusuf Abdurisag', 'Saad Al-Sheeb', 'Boualem Khoukhi', 'Tarek Salman', 'Mostafa Meshaal', 'Assim Madibo', 'Ali Asad', 'Khalid Muneer'],
        'scotla' => ['Angus Gunn', 'Anthony Ralston', 'Jack Hendry', 'Grant Hanley', 'Scott McKenna', 'Andrew Robertson', 'Billy Gilmour', 'Callum McGregor', 'Scott McTominay', 'John McGinn', 'Che Adams', 'Zander Clark', 'Liam Cooper', 'Greg Taylor', 'Kenny McLean', 'Ryan Christie', 'James Forrest', 'Lawrence Shankland'],
        'escocia' => ['Angus Gunn', 'Anthony Ralston', 'Jack Hendry', 'Grant Hanley', 'Scott McKenna', 'Andrew Robertson', 'Billy Gilmour', 'Callum McGregor', 'Scott McTominay', 'John McGinn', 'Che Adams', 'Zander Clark', 'Liam Cooper', 'Greg Taylor', 'Kenny McLean', 'Ryan Christie', 'James Forrest', 'Lawrence Shankland'],
        'uzbekistan' => ['Utkir Yusupov', 'Rustam Ashurmatov', 'Abdukodir Khusanov', 'Husniddin Aliqulov', 'Sherzod Nasrullaev', 'Otabek Shukurov', 'Odiljon Hamrobekov', 'Farrukh Sayfiev', 'Jaloliddin Masharipov', 'Abbosbek Fayzullaev', 'Eldor Shomurodov', 'Abduvohid Nematov', 'Abdulla Abdullaev', 'Jakhongir Urunov', 'Azizbek Turgunboev', 'Oston Urunov', 'Igor Sergeev', 'Khozhimat Erkinov'],
        'uzbekistán' => ['Utkir Yusupov', 'Rustam Ashurmatov', 'Abdukodir Khusanov', 'Husniddin Aliqulov', 'Sherzod Nasrullaev', 'Otabek Shukurov', 'Odiljon Hamrobekov', 'Farrukh Sayfiev', 'Jaloliddin Masharipov', 'Abbosbek Fayzullaev', 'Eldor Shomurodov', 'Abduvohid Nematov', 'Abdulla Abdullaev', 'Jakhongir Urunov', 'Azizbek Turgunboev', 'Oston Urunov', 'Igor Sergeev', 'Khozhimat Erkinov'],
        'jordan' => ['Yazeed Abulaila', 'Ihsan Haddad', 'Yazan Al-Arab', 'Abdallah Nasib', 'Salem Al-Ajalin', 'Nizar Al-Rashdan', 'Noor Al-Rawabdeh', 'Mahmoud Al-Mardi', 'Ali Olwan', 'Musa Al-Taamari', 'Yazan Al-Naimat', 'Abdallah Al-Fakhouri', 'Feras Shelbaieh', 'Bara\' Marie', 'Rajaei Ayed', 'Ibrahim Sadeh', 'Saleh Rateb', 'Anas Al-Awadat'],
        'jordania' => ['Yazeed Abulaila', 'Ihsan Haddad', 'Yazan Al-Arab', 'Abdallah Nasib', 'Salem Al-Ajalin', 'Nizar Al-Rashdan', 'Noor Al-Rawabdeh', 'Mahmoud Al-Mardi', 'Ali Olwan', 'Musa Al-Taamari', 'Yazan Al-Naimat', 'Abdallah Al-Fakhouri', 'Feras Shelbaieh', 'Bara\' Marie', 'Rajaei Ayed', 'Ibrahim Sadeh', 'Saleh Rateb', 'Anas Al-Awadat'],
        'iraq' => ['Jalal Hassan', 'Hussein Ali', 'Saad Natiq', 'Rebin Sulaka', 'Merchas Doski', 'Osama Rashid', 'Amir Al-Ammari', 'Ibrahim Bayesh', 'Ali Jasim', 'Youssef Amyn', 'Aymen Hussein', 'Ahmed Basil', 'Zaid Tahseen', 'Frans Putros', 'Bashar Resan', 'Zidane Iqbal', 'Montader Madjed', 'Mohanad Ali'],
        'irak' => ['Jalal Hassan', 'Hussein Ali', 'Saad Natiq', 'Rebin Sulaka', 'Merchas Doski', 'Osama Rashid', 'Amir Al-Ammari', 'Ibrahim Bayesh', 'Ali Jasim', 'Youssef Amyn', 'Aymen Hussein', 'Ahmed Basil', 'Zaid Tahseen', 'Frans Putros', 'Bashar Resan', 'Zidane Iqbal', 'Montader Madjed', 'Mohanad Ali'],
        'ghana' => ['Lawrence Ati-Zigi', 'Alidu Seidu', 'Alexander Djiku', 'Mohammed Salisu', 'Gideon Mensah', 'Salis Abdul Samed', 'Elisha Owusu', 'Jordan Ayew', 'Mohammed Kudus', 'Ernest Nuamah', 'Inaki Williams', 'Richard Ofori', 'Denis Odoi', 'Daniel Amartey', 'Iddrisu Baba', 'Majid Ashimeru', 'Antoine Semenyo', 'Jordan Ayew'],
        'congo' => ['Lionel Mpasi', 'Gédéon Kalulu', 'Chancel Mbemba', 'Dylan Batubinsika', 'Arthur Masuaku', 'Charles Pickel', 'Samuel Moutoussamy', 'Théo Bongonda', 'Gaël Kakuta', 'Yoane Wissa', 'Cédric Bakambu', 'Dimitry Bertaud', 'Inonga Baka', 'Joris Kayembe', 'Aaron Tshibola', 'Meschack Elia', 'Grady Diangana', 'Fiston Mayele'],
        'haiti' => ['Johny Placide', 'Carlens Arcus', 'Garven Metusala', 'Ricardo Adé', 'Alex Christian', 'Danley Jean Jacques', 'Carl Sainte', 'Fafà Picault', 'Duckens Nazon', 'Frantzdy Pierrot', 'Derrick Etienne Jr.', 'Alexandre Pierre', 'Jean-Kevin Duverne', 'Leverton Pierre', 'Bryan Alceus', 'Mondy Prunier', 'Louicius Don Deedson', 'Shanyder Borgelin'],
        'haití' => ['Johny Placide', 'Carlens Arcus', 'Garven Metusala', 'Ricardo Adé', 'Alex Christian', 'Danley Jean Jacques', 'Carl Sainte', 'Fafà Picault', 'Duckens Nazon', 'Frantzdy Pierrot', 'Derrick Etienne Jr.', 'Alexandre Pierre', 'Jean-Kevin Duverne', 'Leverton Pierre', 'Bryan Alceus', 'Mondy Prunier', 'Louicius Don Deedson', 'Shanyder Borgelin'],
        'paraguay' => ['Carlos Coronel', 'Gustavo Velázquez', 'Fabian Balbuena', 'Omar Alderete', 'Matías Espinoza', 'Andrés Cubas', 'Mathías Villasanti', 'Hernesto Caballero', 'Miguel Almirón', 'Julio Enciso', 'Alex Arce', 'Alfredo Aguilar', 'Gustavo Gómez', 'Junior Alonso', 'Kaku', 'Ramón Sosa', 'Adam Bareiro', 'Derlis González'],
        'panama' => ['Orlando Mosquera', 'Michael Murillo', 'José Córdoba', 'Edgardo Fariña', 'Eric Davis', 'Cristian Martínez', 'Adalberto Carrasquilla', 'Edgar Yoel Bárcenas', 'Jovani Welch', 'José Luis Rodríguez', 'José Fajardo', 'César Samudio', 'Roderick Miller', 'César Blackman', 'Aníbal Godoy', 'Abdiel Ayarza', 'Kahiser Lenis', 'Eduardo Guerrero'],
        'panamá' => ['Orlando Mosquera', 'Michael Murillo', 'José Córdoba', 'Edgardo Fariña', 'Eric Davis', 'Cristian Martínez', 'Adalberto Carrasquilla', 'Edgar Yoel Bárcenas', 'Jovani Welch', 'José Luis Rodríguez', 'José Fajardo', 'César Samudio', 'Roderick Miller', 'César Blackman', 'Aníbal Godoy', 'Abdiel Ayarza', 'Kahiser Lenis', 'Eduardo Guerrero'],
        'austria' => ['Patrick Pentz', 'Stefan Posch', 'Kevin Danso', 'Maximilian Wöber', 'Phillipp Mwene', 'Nicolas Seiwald', 'Florian Grillitsch', 'Konrad Laimer', 'Christoph Baumgartner', 'Marcel Sabitzer', 'Marko Arnautovic', 'Heinz Lindner', 'Gernot Trauner', 'Leopold Querfeld', 'Patrick Wimmer', 'Romano Schmid', 'Michael Gregoritsch', 'Andreas Weimann'],
        'bosnia' => ['Kenan Piric', 'Jusuf Gazibegovic', 'Anel Ahmedhodzic', 'Dennis Hadzikadunic', 'Sead Kolasinac', 'Rade Krunic', 'Benjamin Tahirovic', 'Haris Hajradinovic', 'Edin Visca', 'Edin Dzeko', 'Ermedin Demirovic', 'Nikola Vasilj', 'Ermin Bicakcic', 'Bojan Nastic', 'Gojko Cimirot', 'Miralem Pjanic', 'Luka Menalo', 'Kenan Kodro'],
        'cura' => ['Eloy Room', 'Jurich Carolina', 'Roshon van Eijma', 'Sherel Floranus', 'Juriën Gaari', 'Vurnon Anita', 'Leandro Bacuna', 'Juninho Bacuna', 'Brandley Kuwas', 'Rangelo Janga', 'Kenji Gorré', 'Tyrick Bodak', 'Kevin Felida', 'Godfried Roemeratoe', 'Gervane Kastaneer', 'Jearl Margaritha', 'Jeremy Antonisse'],
        'cape verde' => ['Vozinha', 'Steven Moreira', 'Logan Costa', 'Roberto Lopes', 'João Paulo', 'Kevin Pina', 'Jamiro Monteiro', 'Deroy Duarte', 'Ryan Mendes', 'Jovane Cabral', 'Garry Rodrigues', 'Dylan Silva', 'Stopira', 'Diney', 'Patrick Andrade', 'Kenny Rocha', 'Bebé', 'Gilson Tavares'],
        'cabo verde' => ['Vozinha', 'Steven Moreira', 'Logan Costa', 'Roberto Lopes', 'João Paulo', 'Kevin Pina', 'Jamiro Monteiro', 'Deroy Duarte', 'Ryan Mendes', 'Jovane Cabral', 'Garry Rodrigues', 'Dylan Silva', 'Stopira', 'Diney', 'Patrick Andrade', 'Kenny Rocha', 'Bebé', 'Gilson Tavares'],
        'norway' => ['Ørjan Nyland', 'Julian Ryerson', 'Andreas Hanche-Olsen', 'Leo Østigård', 'David Møller Wolfe', 'Martin Ødegaard', 'Patrick Berg', 'Sander Berge', 'Oscar Bobb', 'Erling Haaland', 'Alexander Sørloth', 'Mathias Dyngeland', 'Kristoffer Ajer', 'Marcus Holmgren Pedersen', 'Kristian Thorstvedt', 'Aron Dønnum', 'Jørgen Strand Larsen', 'Antonio Nusa'],
        'noruega' => ['Ørjan Nyland', 'Julian Ryerson', 'Andreas Hanche-Olsen', 'Leo Østigård', 'David Møller Wolfe', 'Martin Ødegaard', 'Patrick Berg', 'Sander Berge', 'Oscar Bobb', 'Erling Haaland', 'Alexander Sørloth', 'Mathias Dyngeland', 'Kristoffer Ajer', 'Marcus Holmgren Pedersen', 'Kristian Thorstvedt', 'Aron Dønnum', 'Jørgen Strand Larsen', 'Antonio Nusa'],
        'new zealand' => ['Stefan Marinovic', 'Bill Tuiloma', 'Michael Boxall', 'Nando Pijnaker', 'Liberato Cacace', 'Joe Bell', 'Marko Stamenic', 'Matthew Garbett', 'Sarpreet Singh', 'Chris Wood', 'Kosta Barbarouses', 'Oliver Sail', 'Tommy Smith', 'Tim Payne', 'Clayton Lewis', 'Ben Waine', 'Alex Greive', 'Ben Old'],
        'nueva zelanda' => ['Stefan Marinovic', 'Bill Tuiloma', 'Michael Boxall', 'Nando Pijnaker', 'Liberato Cacace', 'Joe Bell', 'Marko Stamenic', 'Matthew Garbett', 'Sarpreet Singh', 'Chris Wood', 'Kosta Barbarouses', 'Oliver Sail', 'Tommy Smith', 'Tim Payne', 'Clayton Lewis', 'Ben Waine', 'Alex Greive', 'Ben Old'],
        'iran' => ['Alireza Beiranvand', 'Ramin Rezaeian', 'Hossein Kanaanizadegan', 'Shojae Khalilzadeh', 'Milad Mohammadi', 'Saman Ghoddos', 'Saeid Ezatolahi', 'Alireza Jahanbakhsh', 'Mehdi Taremi', 'Mehdi Ghayedi', 'Sardar Azmoun', 'Payam Niazmand', 'Ehsan Hajsafi', 'Rouzbeh Cheshmi', 'Ali Gholizadeh', 'Karim Ansarifard', 'Shahriyar Moghanlou'],
        'irán' => ['Alireza Beiranvand', 'Ramin Rezaeian', 'Hossein Kanaanizadegan', 'Shojae Khalilzadeh', 'Milad Mohammadi', 'Saman Ghoddos', 'Saeid Ezatolahi', 'Alireza Jahanbakhsh', 'Mehdi Taremi', 'Mehdi Ghayedi', 'Sardar Azmoun', 'Payam Niazmand', 'Ehsan Hajsafi', 'Rouzbeh Cheshmi', 'Ali Gholizadeh', 'Karim Ansarifard', 'Shahriyar Moghanlou'],
    ];

    // Buscar coincidencia parcial
    foreach ($rosters as $key => $players) {
        if (strpos($country, $key) !== false) {
            return $players;
        }
    }

    return [
        'Carlos Sánchez', 'Luis Rodríguez', 'Juan Gómez', 'Diego Martínez', 'Andrés Fernández',
        'Mateo López', 'Sebastián Díaz', 'Javier Hernández', 'Gabriel Castro', 'Lucas Ruiz', 'Nicolás Silva',
        'Marcos Torres', 'Felipe Morales', 'Hugo Herrera', 'Enzo Romero', 'Daniel Ortiz', 'Martín Flores', 'Álvaro Gutiérrez'
    ];
}
