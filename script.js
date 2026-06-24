// script.js - Lógica Frontend SPA y Actualizaciones Automáticas en Tiempo Real (Mundial Real 2026)

// Estado Global de la App
let currentView = 'home';
let currentMatchesSubtab = 'live';
let currentActiveGroup = 'A';

let finalDatetime = null;
let serverTimeOffset = 0; // Diferencia de tiempo entre el servidor y el cliente

let matchesData = { pendientes: [], en_vivo: [], finalizados: [] };
let groupsData = {};

// Al cargar el documento
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initCountdown();
    initMatchModal();
    
    // Carga inicial
    refreshAllData();
    
    // Intervalos de actualización automática en vivo (polling)
    setInterval(refreshMatches, 5000);   // Partidos cada 5 segundos
    setInterval(refreshGroups, 8000);    // Grupos cada 8 segundos
    setInterval(refreshStats, 10000);    // Estadísticas cada 10 segundos
    setInterval(refreshBracket, 8000);   // Bracket cada 8 segundos
});

// Navegación entre Pestañas
function initNavigation() {
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const view = btn.getAttribute('data-view');
            switchView(view);
        });
    });

    // Subpestañas de partidos (En Vivo, Próximos, Resultados)
    document.querySelectorAll('.subtab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentMatchesSubtab = btn.getAttribute('data-subtab');
            renderMatches();
        });
    });

    // Selector de Grupos A-L (12 grupos)
    document.querySelectorAll('.group-select-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.group-select-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentActiveGroup = btn.getAttribute('data-group');
            renderGroupStandings();
        });
    });
}

function switchView(viewName) {
    currentView = viewName;
    
    // Cambiar clases en botones de navegación
    document.querySelectorAll('.nav-btn').forEach(btn => {
        if (btn.getAttribute('data-view') === viewName) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // Cambiar visibilidad de secciones
    document.querySelectorAll('.view-section').forEach(section => {
        if (section.id === `${viewName}-view`) {
            section.classList.add('active');
        } else {
            section.classList.remove('active');
        }
    });

    // Cargar datos al cambiar de vista para respuesta inmediata
    if (viewName === 'matches') refreshMatches();
    if (viewName === 'groups') refreshGroups();
    if (viewName === 'stats') refreshStats();
    if (viewName === 'bracket') refreshBracket();
}

// Carga Inicial Completa
function refreshAllData() {
    refreshMatches();
    refreshGroups();
    refreshStats();
    refreshBracket();
}

// ------------------------------------------------------------
// 1. Cuenta atrás animada
// ------------------------------------------------------------
function initCountdown() {
    fetch('api.php?action=get_countdown')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                finalDatetime = new Date(data.final_datetime.replace(/-/g, "/")).getTime();
                const serverTime = new Date(data.server_datetime.replace(/-/g, "/")).getTime();
                const clientTime = new Date().getTime();
                serverTimeOffset = serverTime - clientTime;
                
                // Arrancar el contador tick
                updateCountdown();
                setInterval(updateCountdown, 1000);
            }
        })
        .catch(err => console.error("Error al inicializar la cuenta atrás:", err));
}

function updateCountdown() {
    if (!finalDatetime) return;

    const now = new Date().getTime() + serverTimeOffset;
    const distance = finalDatetime - now;

    if (distance < 0) {
        document.getElementById('cd-days').innerText = "00";
        document.getElementById('cd-hours').innerText = "00";
        document.getElementById('cd-mins').innerText = "00";
        document.getElementById('cd-secs').innerText = "00";
        return;
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    document.getElementById('cd-days').innerText = String(days).padStart(2, '0');
    document.getElementById('cd-hours').innerText = String(hours).padStart(2, '0');
    document.getElementById('cd-mins').innerText = String(minutes).padStart(2, '0');
    document.getElementById('cd-secs').innerText = String(seconds).padStart(2, '0');
}

// ------------------------------------------------------------
// 2. Partidos (Consulta e Impresión)
// ------------------------------------------------------------
function refreshMatches() {
    fetch('api.php?action=get_matches')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                matchesData = data;
                
                // Actualizar el globo rojo indicador de partidos en vivo en la cabecera
                const liveCount = data.en_vivo.length;
                const liveBadge = document.getElementById('live-count-badge');
                if (liveCount > 0) {
                    liveBadge.innerText = liveCount;
                    liveBadge.style.display = 'inline-block';
                } else {
                    liveBadge.style.display = 'none';
                }
                
                if (currentView === 'matches') {
                    renderMatches();
                }
            }
        })
        .catch(err => console.error("Error al obtener partidos:", err));
}

function getFlagUrl(codigo, logoUrl = '') {
    if (logoUrl && logoUrl !== 'placeholder' && logoUrl !== '') {
        return logoUrl;
    }
    if (!codigo || codigo === 'placeholder') {
        // Bandera gris SVG placeholder en base64 para evitar errores de conexión y markup HTML inválido
        return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIyMiIgdmlld0JveD0iMCAwIDMyIDIyIj48cmVjdCB3aWR0aD0iMzIiIGhlaWdodD0iMjIiIGZpbGw9IiMyNzI3MmEiLz48dGV4dCB4PSIxNiIgeT0iMTQiIGZpbGw9IiM3MTcxN2EiIGZvbnQtZmFtaWx5PSJzYW5zLXNlcmlmIiBmb250LXNpemU9IjEyIiBmb250LXdlaWdodD0iYm9sZCIgdGV4dC1hbmNob3I9Im1pZGRsZSI+PzwvdGV4dD48L3N2Zz4=';
    }
    return `https://flagcdn.com/w40/${codigo.toLowerCase()}.png`;
}

function renderMatches() {
    const container = document.getElementById('matches-container');
    container.innerHTML = '';
    
    let list = [];
    if (currentMatchesSubtab === 'live') {
        list = matchesData.en_vivo;
    } else if (currentMatchesSubtab === 'upcoming') {
        list = matchesData.pendientes;
    } else {
        list = matchesData.finalizados;
    }

    if (list.length === 0) {
        document.getElementById('no-matches-msg').style.display = 'block';
        return;
    } else {
        document.getElementById('no-matches-msg').style.display = 'none';
    }

    list.forEach(match => {
        const card = document.createElement('div');
        card.className = `match-card ${match.estado === 'en_vivo' ? 'live' : ''}`;
        card.setAttribute('data-id', match.id);
        card.style.cursor = 'pointer';
        
        // Formatear la fecha
        const dateObj = new Date(match.fecha_hora.replace(/-/g, "/"));
        const dateStr = dateObj.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' }) + ' ' + 
                        dateObj.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        
        let headerHtml = '';
        if (match.estado === 'en_vivo') {
            headerHtml = `
                <div class="match-header">
                    <span class="match-phase">${match.fase}</span>
                    <span class="live-badge">● EN VIVO</span>
                    <span class="live-minute">${match.minuto_actual}'</span>
                </div>
            `;
        } else if (match.estado === 'finalizado') {
            headerHtml = `
                <div class="match-header">
                    <span class="match-phase">${match.fase}</span>
                    <span>FINALIZADO</span>
                </div>
            `;
        } else {
            headerHtml = `
                <div class="match-header">
                    <span class="match-phase">${match.fase}</span>
                    <span class="match-date">${dateStr}</span>
                </div>
            `;
        }

        // Tanda de Penaltis
        let penaltiesHtml = '';
        if (match.goles_penaltis_local !== null && match.goles_penaltis_visitante !== null) {
            penaltiesHtml = `<div class="penalties-score">Penaltis: ${match.goles_penaltis_local} - ${match.goles_penaltis_visitante}</div>`;
        }

        // Eventos del partido (Goles y tarjetas)
        let eventsHtml = '';
        if (match.eventos && match.eventos.length > 0) {
            eventsHtml = `<div class="match-events">`;
            match.eventos.forEach(ev => {
                let icon = '';
                if (ev.tipo === 'gol') {
                    icon = '⚽';
                } else if (ev.tipo === 'tarjeta_amarilla') {
                    icon = '🟨';
                } else {
                    icon = '🟥';
                }
                eventsHtml += `
                    <div class="event-row">
                        <span class="event-icon">${icon}</span>
                        <span>${ev.minuto}' ${ev.jugador_nombre} (${ev.equipo_nombre})</span>
                    </div>
                `;
            });
            eventsHtml += `</div>`;
        }

        card.innerHTML = `
            ${headerHtml}
            <div class="match-teams">
                <div class="team-row">
                    <div class="team-info">
                        <img src="${getFlagUrl(match.equipo_local_codigo, match.equipo_local_logo)}" class="team-flag" alt="${match.equipo_local_nombre}">
                        <span class="team-name">${match.equipo_local_nombre}</span>
                    </div>
                    <span class="team-score">${match.estado !== 'pendiente' ? match.goles_local : '-'}</span>
                </div>
                <div class="team-row">
                    <div class="team-info">
                        <img src="${getFlagUrl(match.equipo_visitante_codigo, match.equipo_visitante_logo)}" class="team-flag" alt="${match.equipo_visitante_nombre}">
                        <span class="team-name">${match.equipo_visitante_nombre}</span>
                    </div>
                    <span class="team-score">${match.estado !== 'pendiente' ? match.goles_visitante : '-'}</span>
                </div>
            </div>
            ${penaltiesHtml}
            ${eventsHtml}
        `;
        
        container.appendChild(card);
    });
}

// ------------------------------------------------------------
// 3. Clasificación Grupos
// ------------------------------------------------------------
function refreshGroups() {
    fetch('api.php?action=get_groups')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                groupsData = data.grupos;
                if (currentView === 'groups') {
                    renderGroupStandings();
                }
            }
        })
        .catch(err => console.error("Error al obtener grupos:", err));
}

function renderGroupStandings() {
    const tbody = document.getElementById('standings-tbody');
    tbody.innerHTML = '';
    
    const teams = groupsData[currentActiveGroup];
    if (!teams) return;

    teams.forEach((t, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="standings-team">
                    <span class="rank-num">${index + 1}</span>
                    <img src="${getFlagUrl(t.codigo_pais, t.logo_url)}" class="team-flag mini-flag" alt="${t.nombre}" style="width:24px; height:16px;">
                    <span>${t.nombre}</span>
                </div>
            </td>
            <td class="num-col">${t.pj}</td>
            <td class="num-col">${t.pg}</td>
            <td class="num-col">${t.pe}</td>
            <td class="num-col">${t.pp}</td>
            <td class="num-col">${t.gf}</td>
            <td class="num-col">${t.gc}</td>
            <td class="num-col">${t.dg > 0 ? '+' + t.dg : t.dg}</td>
            <td class="pts-col">${t.puntos}</td>
        `;
        tbody.appendChild(tr);
    });
}

// ------------------------------------------------------------
// 4. Estadísticas
// ------------------------------------------------------------
function refreshStats() {
    fetch('api.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                renderStatList('stats-goleadores', data.goleadores, 'goles', '⚽');
                renderStatList('stats-asistentes', data.asistentes, 'asistencias', '🎯');
                renderStatList('stats-amarillas', data.amarillas, 'tarjetas_amarillas', '🟨');
                renderStatList('stats-rojas', data.rojas, 'tarjetas_rojas', '🟥');
            }
        })
        .catch(err => console.error("Error al obtener estadísticas:", err));
}

function renderStatList(containerId, list, statName, emoji) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    if (list.length === 0) {
        container.innerHTML = '<div style="color:var(--text-secondary); font-size:0.85rem; text-align:center; padding:10px;">Sin registros</div>';
        return;
    }

    list.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'stats-item';
        
        // Determinar si mostrar un emoji de texto o una tarjeta CSS personalizada
        let displayIcon = emoji;
        if (statName === 'tarjetas_rojas') {
            displayIcon = `<span class="stat-card-badge red-card"></span>`;
        } else if (statName === 'tarjetas_amarillas') {
            displayIcon = `<span class="stat-card-badge yellow-card"></span>`;
        }

        row.innerHTML = `
            <div class="player-info-row">
                <span class="player-rank">#${index + 1}</span>
                <div class="player-details">
                    <span class="player-name">${item.nombre}</span>
                    <span class="player-team">
                        <img src="${getFlagUrl(item.codigo_pais, item.equipo_logo)}" class="mini-flag" alt=""> 
                        ${item.equipo_nombre}
                    </span>
                </div>
            </div>
            <span class="stat-value"><span class="stat-number">${item[statName]}</span>${displayIcon}</span>
        `;
        container.appendChild(row);
    });
}

// ------------------------------------------------------------
// 5. Bracket (Fases Finales - Dieciseisavos)
// ------------------------------------------------------------
function refreshBracket() {
    fetch('api.php?action=get_bracket')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                renderBracket(data.bracket);
            }
        })
        .catch(err => console.error("Error al obtener cuadro de playoffs:", err));
}

function renderBracket(bracket) {
    // Rellenar Dieciseisavos (Matches 1 a 16)
    if (bracket.dieciseisavos) {
        bracket.dieciseisavos.forEach(m => {
            const containerId = `bracket-dieciseisavos-${m.posicion_bracket}`;
            const div = document.getElementById(containerId);
            if (div) div.innerHTML = createBracketMatchHtml(m);
        });
    }

    // Rellenar Octavos
    if (bracket.octavos) {
        bracket.octavos.forEach(m => {
            const containerId = `bracket-octavos-${m.posicion_bracket}`;
            const div = document.getElementById(containerId);
            if (div) div.innerHTML = createBracketMatchHtml(m);
        });
    }

    // Rellenar Cuartos
    if (bracket.cuartos) {
        bracket.cuartos.forEach(m => {
            const containerId = `bracket-cuartos-${m.posicion_bracket}`;
            const div = document.getElementById(containerId);
            if (div) div.innerHTML = createBracketMatchHtml(m);
        });
    }

    // Rellenar Semis
    if (bracket.semis) {
        bracket.semis.forEach(m => {
            const containerId = `bracket-semis-${m.posicion_bracket}`;
            const div = document.getElementById(containerId);
            if (div) div.innerHTML = createBracketMatchHtml(m);
        });
    }

    // Rellenar Tercer Puesto
    if (bracket.tercer_puesto && bracket.tercer_puesto[0]) {
        const div = document.getElementById('bracket-tercero');
        if (div) div.innerHTML = createBracketMatchHtml(bracket.tercer_puesto[0]);
    }

    // Rellenar Final
    if (bracket.final && bracket.final[0]) {
        const m = bracket.final[0];
        const div = document.getElementById('bracket-final');
        if (div) div.innerHTML = createBracketMatchHtml(m);
        
        // Mostrar Campeón si ya terminó
        const championBox = document.getElementById('champion-box');
        const championName = document.getElementById('champion-name');
        
        if (m.estado === 'finalizado') {
            let winName = '';
            let winGl = intval(m.goles_local);
            let winGv = intval(m.goles_visitante);
            
            if (winGl > winGv) {
                winName = m.equipo_local_nombre;
            } else if (winGl < winGv) {
                winName = m.equipo_visitante_nombre;
            } else {
                // Penaltis
                const pl = intval(m.goles_penaltis_local);
                const pv = intval(m.goles_penaltis_visitante);
                winName = pl > pv ? m.equipo_local_nombre : m.equipo_visitante_nombre;
            }
            
            championName.innerText = winName;
            championBox.style.display = 'block';
        } else {
            championBox.style.display = 'none';
        }
    }
}

function intval(val) {
    return parseInt(val || 0);
}

function createBracketMatchHtml(m) {
    let localClass = '';
    let visitorClass = '';
    
    if (m.estado === 'finalizado') {
        const gl = intval(m.goles_local);
        const gv = intval(m.goles_visitante);
        if (gl > gv) {
            localClass = 'winner';
            visitorClass = 'loser';
        } else if (gl < gv) {
            localClass = 'loser';
            visitorClass = 'winner';
        } else {
            const pl = intval(m.goles_penaltis_local);
            const pv = intval(m.goles_penaltis_visitante);
            if (pl > pv) {
                localClass = 'winner';
                visitorClass = 'loser';
            } else {
                localClass = 'loser';
                visitorClass = 'winner';
            }
        }
    }

    let liveClass = m.estado === 'en_vivo' ? 'live-card' : '';
    let finClass = m.estado === 'finalizado' ? 'finished-card' : '';
    
    let infoStr = '';
    if (m.estado === 'en_vivo') {
        infoStr = `<span style="color:var(--accent-live)">● LIVE ${m.minuto_actual}'</span>`;
    } else {
        infoStr = `<span>Partido ${m.id}</span>`;
    }

    // Agregar penaltis si los hay
    let scoreL = m.estado !== 'pendiente' ? m.goles_local : '-';
    let scoreV = m.estado !== 'pendiente' ? m.goles_visitante : '-';
    
    if (m.goles_penaltis_local !== null && m.goles_penaltis_visitante !== null) {
        scoreL += ` <span style="font-size:0.7rem; color:var(--text-secondary);">(${m.goles_penaltis_local})</span>`;
        scoreV += ` <span style="font-size:0.7rem; color:var(--text-secondary);">(${m.goles_penaltis_visitante})</span>`;
    }

    return `
        <div class="bracket-card ${liveClass} ${finClass}" data-id="${m.id}" style="cursor:pointer;">
            <div class="bracket-match-info">
                ${infoStr}
            </div>
            <div class="bracket-team-row ${localClass}">
                <div class="bracket-team-info">
                    <img src="${getFlagUrl(m.equipo_local_codigo, m.equipo_local_logo)}" class="bracket-flag" alt="">
                    <span>${m.equipo_local_nombre}</span>
                </div>
                <span class="bracket-score">${scoreL}</span>
            </div>
            <div class="bracket-team-row ${visitorClass}">
                <div class="bracket-team-info">
                    <img src="${getFlagUrl(m.equipo_visitante_codigo, m.equipo_visitante_logo)}" class="bracket-flag" alt="">
                    <span>${m.equipo_visitante_nombre}</span>
                </div>
                <span class="bracket-score">${scoreV}</span>
            </div>
        </div>
    `;
}

// ------------------------------------------------------------
// 6. Alineaciones y Modal de Detalles del Partido
// ------------------------------------------------------------
let currentRosterData = null;
let currentSelectedRosterSide = 'local';

function initMatchModal() {
    const modal = document.getElementById('match-modal');
    const closeBtn = document.querySelector('.close-modal');
    if (closeBtn) {
        closeBtn.onclick = () => {
            modal.style.display = 'none';
        };
    }
    
    // Cerrar modal al hacer clic fuera
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Manejar cambio de pestañas de alineación (local vs visitante)
    document.querySelectorAll('.lineup-tab-btn').forEach(btn => {
        btn.onclick = () => {
            document.querySelectorAll('.lineup-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const side = btn.getAttribute('data-side');
            currentSelectedRosterSide = side;
            renderModalRosterSide(side);
        };
    });
    
    // Delegación de clics en tarjetas de partidos (en calendario o eliminatorias)
    document.addEventListener('click', (e) => {
        const matchCard = e.target.closest('.match-card, .bracket-card');
        if (matchCard) {
            const matchId = matchCard.getAttribute('data-id');
            if (matchId) {
                openMatchLineups(matchId);
            }
        }
    });
}

function openMatchLineups(matchId) {
    const modal = document.getElementById('match-modal');
    const detailsContainer = document.getElementById('modal-match-details');
    const startersContainer = document.getElementById('lineup-starters');
    const benchContainer = document.getElementById('lineup-bench');
    const statusBadge = document.getElementById('lineup-status-badge');
    const localTab = document.getElementById('tab-team-local');
    const visitanteTab = document.getElementById('tab-team-visitante');
    
    // Resetear vistas a cargando
    detailsContainer.innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-secondary); font-size: 1.1rem;">⚽ Cargando alineaciones...</div>';
    startersContainer.innerHTML = '';
    benchContainer.innerHTML = '';
    modal.style.display = 'block';
    
    fetch(`api.php?action=get_lineups&match_id=${matchId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                currentRosterData = data;
                const match = data.match;
                
                // Formatear estado y goles
                let scoreText = 'VS';
                if (match.estado !== 'pendiente') {
                    scoreText = `${match.goles_local} - ${match.goles_visitante}`;
                }
                
                let stateText = 'Próximamente';
                if (match.estado === 'en_vivo') stateText = '🔴 En Vivo';
                else if (match.estado === 'finalizado') stateText = 'Finalizado';
                
                detailsContainer.innerHTML = `
                    <div class="modal-header-match">
                        <div class="modal-match-meta">${stateText}</div>
                        <div class="modal-teams-row">
                            <div class="modal-team-box">
                                <img src="${getFlagUrl(match.local.codigo, match.local.logo)}" alt="">
                                <span>${match.local.nombre}</span>
                            </div>
                            <div class="modal-score-box">
                                ${scoreText}
                            </div>
                            <div class="modal-team-box">
                                <img src="${getFlagUrl(match.visitante.codigo, match.visitante.logo)}" alt="">
                                <span>${match.visitante.nombre}</span>
                            </div>
                        </div>
                    </div>
                `;
                
                // Configurar textos en pestañas
                localTab.innerText = match.local.nombre;
                visitanteTab.innerText = match.visitante.nombre;
                
                // Estado Oficial vs Probable
                if (data.is_official) {
                    statusBadge.innerText = 'Alineación Oficial';
                    statusBadge.className = 'lineup-status-badge official';
                } else {
                    statusBadge.innerText = 'Alineación Probable';
                    statusBadge.className = 'lineup-status-badge';
                }
                
                // Renderizar local por defecto
                currentSelectedRosterSide = 'local';
                document.querySelectorAll('.lineup-tab-btn').forEach(b => b.classList.remove('active'));
                localTab.classList.add('active');
                
                renderModalRosterSide('local');
            } else {
                detailsContainer.innerHTML = `<div style="text-align:center; padding:30px; color:var(--accent-red); font-weight:700;">${data.message}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            detailsContainer.innerHTML = '<div style="text-align:center; padding:30px; color:var(--accent-red); font-weight:700;">Error de red al cargar alineaciones</div>';
        });
}

function renderModalRosterSide(side) {
    if (!currentRosterData || !currentRosterData.rosters[side]) return;
    
    const startersContainer = document.getElementById('lineup-starters');
    const benchContainer = document.getElementById('lineup-bench');
    
    startersContainer.innerHTML = '';
    benchContainer.innerHTML = '';
    
    const starters = currentRosterData.rosters[side].starters;
    const bench = currentRosterData.rosters[side].bench;
    
    if (!starters || starters.length === 0) {
        startersContainer.innerHTML = '<div style="color:var(--text-secondary); font-size:0.85rem; padding:15px; text-align:center;">No hay titulares disponibles</div>';
    } else {
        starters.forEach(p => {
            const div = document.createElement('div');
            div.className = 'player-item-row';
            div.innerHTML = `
                <span class="player-jersey">${p.dorsal || '#'}</span>
                <span class="player-name-modal">${p.nombre}</span>
                <span class="player-position-modal">${p.pos_abbr || 'MED'}</span>
            `;
            startersContainer.appendChild(div);
        });
    }
    
    if (!bench || bench.length === 0) {
        benchContainer.innerHTML = '<div style="color:var(--text-secondary); font-size:0.85rem; padding:15px; text-align:center;">No hay suplentes disponibles</div>';
    } else {
        bench.forEach(p => {
            const div = document.createElement('div');
            div.className = 'player-item-row';
            div.innerHTML = `
                <span class="player-jersey">${p.dorsal || '#'}</span>
                <span class="player-name-modal">${p.nombre}</span>
                <span class="player-position-modal" style="background:rgba(255,255,255,0.05); color:var(--text-secondary);">${p.pos_abbr || 'S'}</span>
            `;
            benchContainer.appendChild(div);
        });
    }
}
