// script.js - Lógica de Interacciones, Modals y Actualizaciones en Vivo

let countdownInterval = null;
let liveScoresInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    initCountdown();
    initMatchModal();
    initLiveScores();
    triggerBackgroundSync();
});

function triggerBackgroundSync() {
    // Sincronización automática asíncrona de fondo al cargar la página
    fetch('sync.php?key=5ligas_sync_secret')
        .then(res => res.json())
        .then(data => {
            console.log("Sincronización automática de fondo completada:", data);
        })
        .catch(err => {
            console.warn("Fallo silencioso en sincronización automática:", err);
        });
}

// ------------------------------------------------------------
// 1. Cuenta Regresiva de Partidos Importantes
// ------------------------------------------------------------
function initCountdown() {
    const timerContainer = document.querySelector('.timer-large');
    if (!timerContainer) return;

    fetch('api.php?action=get_countdown')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const targetDate = new Date(data.final_datetime.replace(/-/g, "/")).getTime();
                
                // Actualizar el título del countdown
                const titleNode = document.querySelector('.countdown-title-large');
                if (titleNode && data.match_name) {
                    titleNode.innerHTML = `<span class="live-dot-indicator"></span> CUENTA ATRÁS: ${data.match_name.toUpperCase()}`;
                }

                if (countdownInterval) clearInterval(countdownInterval);
                
                countdownInterval = setInterval(() => {
                    const now = new Date().getTime();
                    const distance = targetDate - now;

                    if (distance < 0) {
                        clearInterval(countdownInterval);
                        document.getElementById('cd-days').innerText = '00';
                        document.getElementById('cd-hours').innerText = '00';
                        document.getElementById('cd-mins').innerText = '00';
                        document.getElementById('cd-secs').innerText = '00';
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
                }, 1000);
            }
        })
        .catch(err => console.error("Error al inicializar cuenta atrás:", err));
}

// ------------------------------------------------------------
// 2. Modals de Detalles de Partidos (Goles y Tarjetas)
// ------------------------------------------------------------
function initMatchModal() {
    // Crear el HTML del modal e insertarlo en el body si no existe
    let modal = document.getElementById('match-details-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'match-details-modal';
        modal.className = 'modal';
        const titleText = (window.AppTranslations && window.AppTranslations.details_title) ? window.AppTranslations.details_title : 'Detalles del Partido';
        const loadingText = (window.AppTranslations && window.AppTranslations.loading) ? window.AppTranslations.loading : 'Cargando detalles...';
        modal.innerHTML = `
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <div class="modal-body" id="modal-match-content">
                    ${loadingText}
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    // Evento cerrar modal
    modal.querySelector('.close-modal').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Delegar click a los elementos de tarjeta de partidos
    document.addEventListener('click', (e) => {
        const matchCard = e.target.closest('.match-card-clickable, .admin-match-item');
        if (matchCard) {
            const matchId = matchCard.getAttribute('data-match-id');
            if (matchId) {
                openMatchDetails(matchId);
            }
        }
    });
}

function openMatchDetails(matchId) {
    const modal = document.getElementById('match-details-modal');
    const content = document.getElementById('modal-match-content');
    
    modal.classList.add('active');
    const statsLoadingText = (window.AppTranslations && window.AppTranslations.loading_live_stats) ? window.AppTranslations.loading_live_stats : 'Cargando estadísticas en tiempo real...';
    content.innerHTML = `<div style="text-align:center; padding:30px; color:var(--text-secondary);">${statsLoadingText}</div>`;

    fetch(`api.php?action=get_match_details&id=${matchId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const p = data.partido;
                const evLocal = data.eventos_local;
                const evVisitor = data.eventos_visitante;

                let scoreText = `${p.goles_local} - ${p.goles_visitante}`;
                if (p.goles_penaltis_local !== null && p.goles_penaltis_visitante !== null) {
                    scoreText += ` <span style="font-size:0.8rem; display:block; color:var(--text-secondary);">(Pen: ${p.goles_penaltis_local}-${p.goles_penaltis_visitante})</span>`;
                }

                let statusText = '';
                if (p.estado === 'en_vivo') {
                    const liveMinText = (window.AppTranslations && window.AppTranslations.live_min) ? window.AppTranslations.live_min : 'LIVE Min';
                    statusText = `<span class="badge-live-pulse">${liveMinText} ${p.minuto_actual}'</span>`;
                } else if (p.estado === 'finalizado') {
                    const finishedText = (window.AppTranslations && window.AppTranslations.finished) ? window.AppTranslations.finished : 'Finalizado';
                    statusText = `<span style="color:var(--text-secondary); font-weight:700;">${finishedText}</span>`;
                } else {
                    const d = new Date(p.fecha_hora.replace(/-/g, "/"));
                    const currentLangCode = (window.AppTranslations && window.AppTranslations.lang_code) || 'es';
                    const localeStr = currentLangCode === 'en' ? 'en-US' : (currentLangCode === 'it' ? 'it-IT' : (currentLangCode === 'de' ? 'de-DE' : (currentLangCode === 'fr' ? 'fr-FR' : 'es-ES')));
                    statusText = `<span style="color:var(--accent-blue); font-weight:600;">${d.toLocaleDateString(localeStr)} ${d.toLocaleTimeString(localeStr, {hour:'2-digit', minute:'2-digit'})}</span>`;
                }

                // Generar HTML de eventos
                let localEventsHtml = '';
                evLocal.forEach(ev => {
                    localEventsHtml += `
                        <div class="event-row">
                            <span class="event-icon">${getEventIcon(ev.tipo)}</span>
                            <span class="event-time">${ev.minuto}'</span>
                            <span class="event-player">${escapeHtml(ev.jugador_nombre)}</span>
                        </div>
                    `;
                });

                let visitorEventsHtml = '';
                evVisitor.forEach(ev => {
                    visitorEventsHtml += `
                        <div class="event-row visitor-event">
                            <span class="event-player">${escapeHtml(ev.jugador_nombre)}</span>
                            <span class="event-time">${ev.minuto}'</span>
                            <span class="event-icon">${getEventIcon(ev.tipo)}</span>
                        </div>
                    `;
                });

                const noIncidentsText = (window.AppTranslations && window.AppTranslations.no_incidents) ? window.AppTranslations.no_incidents : 'Sin incidencias';
                content.innerHTML = `
                    <div class="modal-header-match">
                        <div class="modal-match-meta">${statusText}</div>
                        <div class="modal-teams-row">
                            <div class="modal-team-box">
                                <img src="${p.equipo_local_logo}" alt="">
                                <span>${escapeHtml(p.equipo_local_nombre)}</span>
                            </div>
                            <div class="modal-score-box">${scoreText}</div>
                            <div class="modal-team-box">
                                <img src="${p.equipo_visitante_logo}" alt="">
                                <span>${escapeHtml(p.equipo_visitante_nombre)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-events-container">
                        <div class="modal-events-column">
                            ${localEventsHtml || `<div style="color:var(--text-secondary); font-size:0.8rem; text-align:center; padding:10px;">${noIncidentsText}</div>`}
                        </div>
                        <div class="modal-separator"></div>
                        <div class="modal-events-column">
                            ${visitorEventsHtml || `<div style="color:var(--text-secondary); font-size:0.8rem; text-align:center; padding:10px;">${noIncidentsText}</div>`}
                        </div>
                    </div>
                `;
            } else {
                const errorLabel = (window.AppTranslations && window.AppTranslations.error) ? window.AppTranslations.error : 'Error';
                content.innerHTML = `<div style="color:var(--accent-red); text-align:center; padding:30px;">${errorLabel}: ${data.message}</div>`;
            }
        })
        .catch(err => {
            const apiErrorText = (window.AppTranslations && window.AppTranslations.api_error) ? window.AppTranslations.api_error : 'Error al conectar con la API de datos.';
            content.innerHTML = `<div style="color:var(--accent-red); text-align:center; padding:30px;">${apiErrorText}</div>`;
            console.error(err);
        });
}

function closeModal() {
    const modal = document.getElementById('match-details-modal');
    if (modal) modal.classList.remove('active');
}

function getEventIcon(type) {
    if (type === 'gol') return '⚽';
    if (type === 'tarjeta_amarilla') return '🟨';
    if (type === 'tarjeta_roja') return '🟥';
    return '⏱️';
}

function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ------------------------------------------------------------
// 3. Polling de Partidos en Vivo (LIVE Scores)
// ------------------------------------------------------------
function initLiveScores() {
    // Si la página contiene marcadores que puedan cambiar a "En Vivo", activamos el polling cada 15 segundos
    const liveContainer = document.querySelector('.live-dot-indicator');
    if (!liveContainer) return;

    if (liveScoresInterval) clearInterval(liveScoresInterval);
    liveScoresInterval = setInterval(updateLiveScores, 15000);
}

function updateLiveScores() {
    fetch('api.php?action=get_live_scores')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.en_vivo.length > 0) {
                data.en_vivo.forEach(match => {
                    const matchCard = document.querySelector(`[data-match-id="${match.id}"]`);
                    if (matchCard) {
                        // Actualizar goles
                        const scoreEl = matchCard.querySelector('.match-score-live, .match-score, .score-display');
                        if (scoreEl) {
                            scoreEl.innerHTML = `${match.goles_local} - ${match.goles_visitante}`;
                            scoreEl.className = 'match-score-live'; // Agregar clase en vivo
                        }
                        
                        // Actualizar minuto
                        const timeEl = matchCard.querySelector('.match-time, .match-status-label');
                        if (timeEl) {
                            timeEl.innerHTML = `<span class="badge-live-pulse">En Vivo Min ${match.minuto_actual}'</span>`;
                        }
                    }
                });
            }
        })
        .catch(err => console.error("Error al actualizar marcadores en vivo:", err));
}
