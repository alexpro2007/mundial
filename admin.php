<?php
// admin.php - Consola de Sincronización y Mantenimiento del Mundial 2026 (ESPN)
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consola de Datos del Mundial 2026 - Control de Sincronización</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-select {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-glass);
            color: #ffffff;
            font-family: var(--font-main);
            font-size: 1rem;
            padding: 10px 15px;
            border-radius: 10px;
            cursor: pointer;
        }
        .admin-controls-card {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .log-container {
            background: rgba(0,0,0,0.5);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 12px;
            height: 250px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.8rem;
            color: var(--accent-blue);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .back-link {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .back-link:hover {
            color: var(--primary-color);
        }
        .sync-info {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 15px;
            font-size: 0.85rem;
        }
        .sync-info-title {
            font-weight: 700;
            margin-bottom: 8px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sync-info-time {
            font-size: 1.1rem;
            color: var(--accent-live);
            font-weight: 800;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <div class="nav-container">
            <div class="logo-section">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                    <path d="M2 12h20"></path>
                </svg>
                <h1>CONSOLA DE SINCRONIZACIÓN</h1>
            </div>
            
            <a href="index.php" class="back-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Volver a la Web Principal
            </a>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main>
        <div class="admin-grid">
            
            <!-- Columna Izquierda: Acciones de Sincronización y Mantenimiento -->
            <div class="admin-controls">
                
                <div class="glass-card admin-controls-card">
                    <h3 class="section-title" style="margin-bottom:10px;">Control de <span>Datos</span></h3>
                    
                    <div class="sync-info">
                        <div class="sync-info-title">
                            <span class="pulse-dot active" style="background-color: var(--accent-live);"></span>
                            Última Sincronización de ESPN:
                        </div>
                        <div class="sync-info-time" id="last-sync-time-display">Cargando...</div>
                        <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:8px;">
                            La base de datos local almacena en caché los partidos. Se actualiza automáticamente cada 30 segundos cuando los usuarios cargan la página principal.
                        </p>
                    </div>

                    <button class="btn btn-primary" id="btn-force-sync">
                        🔄 Forzar Sincronización Ahora
                    </button>
                    
                    <button class="btn btn-danger" id="btn-reset-clean">
                        ⚠️ Vaciar BD y Re-sincronizar Todo
                    </button>
                </div>

                <div class="glass-card">
                    <h4 style="font-size:1rem; font-weight:800; margin-bottom:12px; color:#ffffff;">Registro de Sincronización</h4>
                    <div class="log-container" id="log-container">
                        <div>[Consola] Sincronizador listo.</div>
                    </div>
                </div>

            </div>

            <!-- Columna Derecha: Partidos Almacenados en la BD local -->
            <div>
                <div class="glass-card" style="margin-bottom: 20px;">
                    <div class="filter-section">
                        <h3 class="section-title" style="margin-bottom:0;">Partidos <span>Sincronizados</span></h3>
                        
                        <select class="filter-select" id="filter-phase">
                            <option value="grupos">Fase de Grupos</option>
                            <option value="dieciseisavos">Dieciseisavos de Final</option>
                            <option value="octavos">Octavos de Final</option>
                            <option value="cuartos">Cuartos de Final</option>
                            <option value="semifinal">Semifinales</option>
                            <option value="tercer_puesto">Tercer Puesto</option>
                            <option value="final">Gran Final</option>
                        </select>
                    </div>
                </div>

                <div class="admin-matches-list" id="admin-matches-list">
                    <!-- Se rellena con JS -->
                </div>
            </div>

        </div>
    </main>

    <!-- Scripts -->
    <script src="admin.js"></script>
</body>
</html>
