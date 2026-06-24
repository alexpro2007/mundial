CREATE DATABASE IF NOT EXISTS mundial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mundial;

-- Desactivar llaves foráneas para poder hacer drop limpio
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS eventos_partido;
DROP TABLE IF EXISTS jugadores;
DROP TABLE IF EXISTS partidos;
DROP TABLE IF EXISTS equipos;
DROP TABLE IF EXISTS ligas;
DROP TABLE IF EXISTS noticias;
DROP TABLE IF EXISTS configuracion;
DROP TABLE IF EXISTS admin_users;
SET FOREIGN_KEY_CHECKS = 1;

-- Crear tabla ligas
CREATE TABLE ligas (
    id VARCHAR(20) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    pais VARCHAR(50) NOT NULL,
    logo_url TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla equipos
CREATE TABLE equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    codigo_pais VARCHAR(10) NOT NULL,
    logo_url VARCHAR(255) NULL,
    liga_id VARCHAR(20) NOT NULL,
    espn_id INT NULL UNIQUE,
    FOREIGN KEY (liga_id) REFERENCES ligas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla partidos
CREATE TABLE partidos (
    id INT PRIMARY KEY,               -- gameId de ESPN
    equipo_local_id INT NULL,
    equipo_visitante_id INT NULL,
    goles_local INT DEFAULT 0,
    goles_visitante INT DEFAULT 0,
    goles_penaltis_local INT DEFAULT NULL,
    goles_penaltis_visitante INT DEFAULT NULL,
    estado ENUM('pendiente', 'en_vivo', 'finalizado') DEFAULT 'pendiente',
    fecha_hora DATETIME NOT NULL,
    minuto_actual INT DEFAULT 0,
    fase VARCHAR(50) DEFAULT 'temporada_regular',
    nombre VARCHAR(150) NULL,
    posicion_bracket INT DEFAULT NULL,
    alt_game_note VARCHAR(100) NULL,
    liga_id VARCHAR(20) NOT NULL,
    FOREIGN KEY (equipo_local_id) REFERENCES equipos(id) ON DELETE SET NULL,
    FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id) ON DELETE SET NULL,
    FOREIGN KEY (liga_id) REFERENCES ligas(id) ON DELETE CASCADE
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
    asistente_id INT NULL,
    FOREIGN KEY (partido_id) REFERENCES partidos(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla noticias (fichajes y pronósticos)
CREATE TABLE noticias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('fichaje', 'pronostico') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    contenido LONGTEXT NOT NULL,
    enlace_afiliado TEXT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla configuracion (anuncios y enlaces)
CREATE TABLE configuracion (
    clave VARCHAR(100) PRIMARY KEY,
    valor LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla admin_users
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar ligas iniciales
INSERT INTO ligas (id, nombre, pais, logo_url) VALUES 
('eng.1', 'Premier League', 'Inglaterra', 'https://a.espncdn.com/i/leaguelogos/soccer/500/23.png'),
('esp.1', 'LaLiga', 'España', 'https://a.espncdn.com/i/leaguelogos/soccer/500/15.png'),
('ita.1', 'Serie A', 'Italia', 'https://a.espncdn.com/i/leaguelogos/soccer/500/12.png'),
('ger.1', 'Bundesliga', 'Alemania', 'https://a.espncdn.com/i/leaguelogos/soccer/500/10.png'),
('fra.1', 'Ligue 1', 'Francia', 'https://a.espncdn.com/i/leaguelogos/soccer/500/9.png');

-- Insertar configuraciones iniciales
INSERT INTO configuracion (clave, valor) VALUES 
('banner_header', '<div class="banner-placeholder">PUBLICIDAD SUPERIOR (728x90)</div>'),
('banner_sidebar', '<div class="banner-placeholder">PUBLICIDAD LATERAL (300x250)</div>'),
('afiliado_apuestas_url', 'https://www.google.com'),
('afiliado_camisetas_url', 'https://www.amazon.com');

-- Insertar administrador por defecto (usuario: admin, clave: admin2026)
INSERT INTO admin_users (username, password) VALUES 
('admin', '$2y$10$q1QYSRwVM3lbyI92diR7xus5Gxh7IEJkoq8iG7d/L.bkca0ZYlpRq');
