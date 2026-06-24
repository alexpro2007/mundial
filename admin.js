// admin.js - Lógica de Control del Panel de Administración de las 5 Grandes Ligas

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initSync();
    initNews();
    initMonetization();
});

// CSRF Helper
function getCsrfToken() {
    return document.getElementById('csrf_token').value;
}

// ------------------------------------------------------------
// 1. Control de Pestañas
// ------------------------------------------------------------
function initTabs() {
    const navButtons = document.querySelectorAll('.admin-nav-btn');
    const sections = document.querySelectorAll('.panel-section');

    navButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-target');
            
            navButtons.forEach(b => b.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));

            btn.classList.add('active');
            document.getElementById(target).classList.add('active');

            // Cargar datos según pestaña
            if (target === 'sec-news') {
                loadArticles();
            } else if (target === 'sec-monetization') {
                loadConfig();
            }
        });
    });
}

// ------------------------------------------------------------
// 2. Control de Sincronización
// ------------------------------------------------------------
function initSync() {
    const btnSync = document.getElementById('btn-sync');
    const syncLog = document.getElementById('sync-log');

    if (!btnSync) return;

    btnSync.addEventListener('click', () => {
        btnSync.disabled = true;
        btnSync.innerText = 'Sincronizando...';
        syncLog.innerHTML = '[Consola] Iniciando sincronización de ESPN...\n';

        const formData = new FormData();
        formData.append('action', 'force_sync');
        formData.append('csrf_token', getCsrfToken());

        fetch('admin_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                syncLog.innerHTML += `[Consola] ¡Éxito! ${data.message}\n`;
            } else {
                syncLog.innerHTML += `[Consola Error] ${data.message}\n`;
            }
        })
        .catch(err => {
            syncLog.innerHTML += `[Error de Red] No se pudo conectar al servidor.\n`;
            console.error(err);
        })
        .finally(() => {
            btnSync.disabled = false;
            btnSync.innerText = 'Forzar Sincronización';
        });
    });
}

// ------------------------------------------------------------
// 3. Control de Noticias y Previas
// ------------------------------------------------------------
let newsArticles = [];

function initNews() {
    const formNews = document.getElementById('form-news');
    const btnCancel = document.getElementById('btn-cancel-edit');

    if (!formNews) return;

    formNews.addEventListener('submit', (e) => {
        e.preventDefault();

        const formData = new FormData(formNews);
        formData.append('action', 'save_news');
        formData.append('csrf_token', getCsrfToken());

        fetch('admin_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                formNews.reset();
                document.getElementById('news-id').value = '';
                btnCancel.style.display = 'none';
                loadArticles();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error de red al guardar el artículo.');
            console.error(err);
        });
    });

    btnCancel.addEventListener('click', () => {
        formNews.reset();
        document.getElementById('news-id').value = '';
        btnCancel.style.display = 'none';
    });
}

function loadArticles() {
    const container = document.getElementById('articles-container');
    if (!container) return;

    container.innerHTML = 'Cargando artículos...';

    fetch('admin_api.php?action=list_news')
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            newsArticles = data.news;
            renderArticles();
        } else {
            container.innerHTML = 'Error al cargar los artículos.';
        }
    })
    .catch(err => {
        container.innerHTML = 'Error de conexión.';
        console.error(err);
    });
}

function renderArticles() {
    const container = document.getElementById('articles-container');
    if (newsArticles.length === 0) {
        container.innerHTML = '<div style="color:#64748b; text-align:center;">No hay artículos creados todavía.</div>';
        return;
    }

    container.innerHTML = '';
    newsArticles.forEach(art => {
        const div = document.createElement('div');
        div.className = 'article-item';

        const label = art.tipo === 'pronostico' ? 'Pronóstico' : 'Fichaje';
        const date = new Date(art.fecha_creacion).toLocaleDateString('es-ES');

        div.innerHTML = `
            <div class="article-info">
                <h4>${escapeHtml(art.titulo)}</h4>
                <div style="margin-top:5px;">
                    <span style="background:${art.tipo === 'pronostico' ? 'rgba(234, 179, 8, 0.15)' : 'rgba(0, 242, 254, 0.15)'}; color:${art.tipo === 'pronostico' ? '#eab308' : '#00f2fe'};">${label}</span>
                    <span style="font-size:0.75rem; color:#64748b;">${date}</span>
                    <span style="font-size:0.75rem; color:#475569; font-family:monospace;">/${escapeHtml(art.slug)}</span>
                </div>
            </div>
            <div class="article-actions">
                <button class="btn-sm btn-edit" onclick="editArticle(${art.id})">Editar</button>
                <button class="btn-sm btn-delete" onclick="deleteArticle(${art.id})">Eliminar</button>
            </div>
        `;
        container.appendChild(div);
    });
}

window.editArticle = function(id) {
    const art = newsArticles.find(a => a.id === id);
    if (!art) return;

    document.getElementById('news-id').value = art.id;
    document.getElementById('news-tipo').value = art.tipo;
    document.getElementById('news-titulo').value = art.titulo;
    document.getElementById('news-slug').value = art.slug;
    document.getElementById('news-contenido').value = art.contenido;
    document.getElementById('news-afiliado').value = art.enlace_afiliado || '';

    document.getElementById('btn-cancel-edit').style.display = 'inline-block';
    
    // Hacer scroll suave hacia arriba (al formulario)
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.deleteArticle = function(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este artículo? Esta acción no se puede deshacer.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_news');
    formData.append('id', id);
    formData.append('csrf_token', getCsrfToken());

    fetch('admin_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            loadArticles();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error de red al eliminar el artículo.');
        console.error(err);
    });
};

// Helper simple para escapar HTML en cadenas para renderizado seguro
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
// 4. Control de Configuración (Monetización)
// ------------------------------------------------------------
function initMonetization() {
    const formConfig = document.getElementById('form-config');
    if (!formConfig) return;

    formConfig.addEventListener('submit', (e) => {
        e.preventDefault();

        const formData = new FormData(formConfig);
        formData.append('action', 'save_config');
        formData.append('csrf_token', getCsrfToken());

        fetch('admin_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error de red al guardar la configuración.');
            console.error(err);
        });
    });
}

function loadConfig() {
    const formConfig = document.getElementById('form-config');
    if (!formConfig) return;

    fetch('admin_api.php?action=get_config')
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const config = data.config;
            document.getElementById('conf-banner-header').value = config.banner_header || '';
            document.getElementById('conf-banner-sidebar').value = config.banner_sidebar || '';
            document.getElementById('conf-afiliado-apuestas').value = config.afiliado_apuestas_url || '';
            document.getElementById('conf-afiliado-camisetas').value = config.afiliado_camisetas_url || '';
        }
    })
    .catch(err => {
        console.error('Error al cargar configuraciones:', err);
    });
}
