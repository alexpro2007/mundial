// admin.js - Lógica de la Consola de Sincronización y Mantenimiento (Mundial 2026)

let activePhase = 'grupos';
let allMatches = [];

document.addEventListener('DOMContentLoaded', () => {
    loadMatches();
    initControls();
    updateLastSyncTimeDisplay();
    
    // Auto-recargar la lista de partidos en la consola cada 10 segundos
    setInterval(loadMatches, 10000);
});

function initControls() {
    // Selector de fases
    document.getElementById('filter-phase').addEventListener('change', (e) => {
        activePhase = e.target.value;
        renderMatchesList();
    });

    // Botón forzar sincronización
    document.getElementById('btn-force-sync').addEventListener('click', forceSyncData);

    // Botón restablecer y re-sincronizar
    document.getElementById('btn-reset-clean').addEventListener('click', resetAndReSync);
}

function updateLastSyncTimeDisplay() {
    // Leer el archivo last_sync.txt (a través de una llamada o simplemente usando la fecha y hora de la última carga)
    // Para simplificar, le preguntamos a la API o mostramos la hora actual cuando sincronizamos con éxito.
    const display = document.getElementById('last-sync-time-display');
    
    // Podemos obtener la hora actual si no hay datos
    fetch('api.php?action=get_countdown')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Simplemente mostramos que el servidor está conectado
                display.innerText = "Conectado (API Activa)";
            }
        })
        .catch(() => {
            display.innerText = "Desconectado";
        });
}

// ------------------------------------------------------------
// Operaciones de Sincronización
// ------------------------------------------------------------
function loadMatches() {
    fetch('api.php?action=get_matches')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                allMatches = [
                    ...data.pendientes,
                    ...data.en_vivo,
                    ...data.finalizados
                ];
                renderMatchesList();
            }
        })
        .catch(err => console.error("Error al obtener partidos para administración:", err));
}

function forceSyncData() {
    addLog("[Sincronizador] Solicitando actualización en vivo a ESPN...", "info");
    
    fetch('sync.php?force=1')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                addLog(`[Sincronizador] Sincronización exitosa. Partidos actualizados: ${data.partidos_actualizados}. Eventos registrados: ${data.eventos_registrados}`, "success");
                
                const now = new Date();
                document.getElementById('last-sync-time-display').innerText = 
                    String(now.getHours()).padStart(2, '0') + ':' + 
                    String(now.getMinutes()).padStart(2, '0') + ':' + 
                    String(now.getSeconds()).padStart(2, '0');
                
                loadMatches();
            } else {
                addLog(`[Error Sincro] ${data.message}`, "error");
            }
        })
        .catch(err => {
            console.error("Error en sincronización:", err);
            addLog("[Error] Error de conexión con el servidor", "error");
        });
}

function resetAndReSync() {
    if (!confirm("⚠️ ¿Estás totalmente seguro de vaciar la base de datos local? Se eliminarán todos los registros y se descargará todo el mundial desde cero de ESPN.")) {
        return;
    }

    addLog("[Consola] Vaciando tablas locales de la base de datos...", "warning");

    const formData = new FormData();
    formData.append('action', 'reset_db');

    fetch('admin_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            addLog("[Consola] Base de datos vaciada con éxito. Iniciando importación limpia desde ESPN...", "success");
            
            // Forzar descarga completa
            fetch('sync.php?force=1')
                .then(r => r.json())
                .then(syncData => {
                    if (syncData.status === 'success') {
                        addLog(`[Consola] Sincronización limpia completada con éxito. Partidos: ${syncData.partidos_actualizados}.`, "success");
                        loadMatches();
                    } else {
                        addLog(`[Error Sincro] ${syncData.message}`, "error");
                    }
                })
                .catch(err => {
                    console.error("Error en re-sincronización:", err);
                    addLog("[Error] Falló la re-sincronización tras el vaciado. Ejecuta Forzar Sincronización manualmente.", "error");
                });
        } else {
            addLog(`[Error Reset] ${data.message}`, "error");
        }
    })
    .catch(err => {
        console.error("Error al resetear la base de datos:", err);
        addLog("[Error] Error al conectar con el servidor", "error");
    });
}

// ------------------------------------------------------------
// Renderizado y Utilerías
// ------------------------------------------------------------
function getFlagUrl(codigo, logoUrl = '') {
    if (logoUrl && logoUrl !== 'placeholder' && logoUrl !== '') {
        return logoUrl;
    }
    if (!codigo || codigo === 'placeholder') {
        return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIyMiIgdmlld0JveD0iMCAwIDMyIDIyIj48cmVjdCB3aWR0aD0iMzIiIGhlaWdodD0iMjIiIGZpbGw9IiMyNzI3MmEiLz48dGV4dCB4PSIxNiIgeT0iMTQiIGZpbGw9IiM3MTcxN2EiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjEyIiBmb250LXdlaWdodD0iYm9sZCIgdGV4dC1hbmNob3I9Im1pZGRsZSI+PzwvdGV4dD48L3N2Zz4=';
    }
    return `https://flagcdn.com/w40/${codigo.toLowerCase()}.png`;
}

function renderMatchesList() {
    const container = document.getElementById('admin-matches-list');
    container.innerHTML = '';

    // Filtrar partidos por la fase seleccionada
    const list = allMatches.filter(m => m.fase === activePhase);

    if (list.length === 0) {
        container.innerHTML = '<div style="color:var(--text-secondary); text-align:center; padding:20px;">No hay partidos cargados para esta fase. Haz clic en Forzar Sincronización.</div>';
        return;
    }

    // Ordenar por ID
    list.sort((a, b) => a.id - b.id);

    list.forEach(m => {
        const item = document.createElement('div');
        item.className = 'admin-match-item';

        const locName = m.equipo_local_nombre || 'Por definir';
        const visName = m.equipo_visitante_nombre || 'Por definir';
        
        let scoreStr = '';
        let statusHtml = '';

        if (m.estado === 'pendiente') {
            scoreStr = `<span style="color:var(--text-secondary); font-size:1.1rem; font-weight:700;">VS</span>`;
            
            const dateObj = new Date(m.fecha_hora.replace(/-/g, "/"));
            const dateStr = dateObj.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' }) + ' ' + 
                            dateObj.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            
            statusHtml = `<span style="font-size:0.75rem; color:var(--text-secondary); font-weight:600;">${dateStr}</span>`;
        } else if (m.estado === 'en_vivo') {
            scoreStr = `<span style="color:var(--accent-live); font-size:1.3rem; font-weight:800;">${m.goles_local} - ${m.goles_visitante}</span>`;
            statusHtml = `<span style="color:var(--accent-live); font-weight:700; font-size:0.8rem; animation:pulse 1.5s infinite;">LIVE Min ${m.minuto_actual}'</span>`;
        } else { // finalizado
            let penStr = '';
            if (m.goles_penaltis_local !== null && m.goles_penaltis_visitante !== null) {
                penStr = `<div style="font-size:0.65rem; color:var(--text-secondary);">P: ${m.goles_penaltis_local}-${m.goles_penaltis_visitante}</div>`;
            }
            scoreStr = `
                <div style="text-align:center; font-weight:800; font-size:1.2rem;">
                    ${m.goles_local} - ${m.goles_visitante}
                    ${penStr}
                </div>
            `;
            statusHtml = `<span style="font-size:0.75rem; color:var(--text-secondary); font-weight:700;">Finalizado</span>`;
        }

        item.innerHTML = `
            <div style="font-size:0.75rem; font-weight:800; color:var(--accent-blue); width:65px;">ID ${m.id}</div>
            
            <div class="admin-match-teams">
                <div class="admin-team">
                    <img src="${getFlagUrl(m.equipo_local_codigo, m.equipo_local_logo)}" class="team-flag" alt="" style="width:26px; height:18px;">
                    <span style="font-size:0.9rem; font-weight:600; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">${locName}</span>
                </div>
                
                <div style="width:70px; display:flex; flex-direction:column; align-items:center;">
                    ${scoreStr}
                </div>
                
                <div class="admin-team visitor">
                    <span style="font-size:0.9rem; font-weight:600; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; order:1;">${visName}</span>
                    <img src="${getFlagUrl(m.equipo_visitante_codigo, m.equipo_visitante_logo)}" class="team-flag" alt="" style="width:26px; height:18px; order:2;">
                </div>
            </div>
            
            <div style="width:110px; text-align:right;">
                ${statusHtml}
            </div>
        `;

        container.appendChild(item);
    });
}

function addLog(text, type = "info") {
    const logBox = document.getElementById('log-container');
    const div = document.createElement('div');
    
    if (type === "success") {
        div.style.color = "var(--accent-live)";
    } else if (type === "warning") {
        div.style.color = "var(--primary-color)";
    } else if (type === "error") {
        div.style.color = "var(--accent-red)";
    }
    
    const now = new Date();
    const timeStr = String(now.getHours()).padStart(2, '0') + ':' + 
                    String(now.getMinutes()).padStart(2, '0') + ':' + 
                    String(now.getSeconds()).padStart(2, '0');

    div.innerText = `[${timeStr}] ${text}`;
    logBox.appendChild(div);
    logBox.scrollTop = logBox.scrollHeight;
}
