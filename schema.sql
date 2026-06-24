CREATE DATABASE IF NOT EXISTS mundial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mundial;

-- Desactivar llaves foráneas para poder hacer drop limpio
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS eventos_partido;
DROP TABLE IF EXISTS jugadores;
DROP TABLE IF EXISTS partidos;
DROP TABLE IF EXISTS equipos;
SET FOREIGN_KEY_CHECKS = 1;

-- Crear tabla equipos
CREATE TABLE equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    codigo_pais VARCHAR(10) NOT NULL, -- Código de país (ej: 'arg', 'fra', 'esp')
    logo_url VARCHAR(255) NULL,       -- URL del logo/escudo proporcionado por ESPN
    grupo CHAR(1) NOT NULL,           -- Grupo 'A' a 'L' (12 grupos)
    espn_id INT NULL UNIQUE           -- ID de equipo en la API de ESPN
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla partidos
CREATE TABLE partidos (
    id INT PRIMARY KEY,               -- Usamos directamente el gameId de ESPN
    equipo_local_id INT NULL,
    equipo_visitante_id INT NULL,
    goles_local INT DEFAULT 0,
    goles_visitante INT DEFAULT 0,
    goles_penaltis_local INT DEFAULT NULL,
    goles_penaltis_visitante INT DEFAULT NULL,
    estado ENUM('pendiente', 'en_vivo', 'finalizado') DEFAULT 'pendiente',
    fecha_hora DATETIME NOT NULL,
    minuto_actual INT DEFAULT 0,
    fase ENUM('grupos', 'dieciseisavos', 'octavos', 'cuartos', 'semifinal', 'tercer_puesto', 'final') DEFAULT 'grupos',
    nombre VARCHAR(150) NULL,          -- Nombre del partido (ej: "Austria at Argentina")
    posicion_bracket INT DEFAULT NULL, -- Posición en el árbol visual de playoffs
    alt_game_note VARCHAR(100) NULL,   -- Nota de ESPN (ej: "Group J" o "Round of 32")
    FOREIGN KEY (equipo_local_id) REFERENCES equipos(id) ON DELETE SET NULL,
    FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla jugadores
CREATE TABLE jugadores (
    id INT PRIMARY KEY,                -- ID de atleta de ESPN
    nombre VARCHAR(100) NOT NULL,
    equipo_id INT NOT NULL,
    goles INT DEFAULT 0,
    asistencias INT DEFAULT 0,
    tarjetas_amarillas INT DEFAULT 0,
    tarjetas_rojas INT DEFAULT 0,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla eventos_partido
CREATE TABLE eventos_partido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partido_id INT NOT NULL,
    tipo ENUM('gol', 'tarjeta_amarilla', 'tarjeta_roja') NOT NULL,
    minuto INT NOT NULL,
    equipo_id INT NOT NULL,
    jugador_id INT NOT NULL,
    asistente_id INT NULL,            -- ID del jugador asistente si lo hay
    FOREIGN KEY (partido_id) REFERENCES partidos(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
