# Fichajes Automatizados y Tarjetas de Fichaje Premium - Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

## Global Constraints
- Vanilla PHP, Vanilla CSS, and Vanilla JavaScript only.
- Strict security: Use PDO prepared statements (SQLi prevention) and `htmlspecialchars()` (XSS prevention).
- CSRF validation required for all POST actions in `admin_api.php`.
- Maintain clean, premium dark theme glow style matching `style.css` variables.

## File Map
- [MODIFY] [sync.php](file:///c:/xampp/htdocs/mundial/sync.php): Implement ESPN news integration, duplicate checking, and auto-mapping of transfer categories/assets.
- [MODIFY] [admin.php](file:///c:/xampp/htdocs/mundial/admin.php): Add input fields to the news form for Player Photo, Former/New Club Names & Logos, and Contract Details.
- [MODIFY] [admin.js](file:///c:/xampp/htdocs/mundial/admin.js): Handle UI toggles for transfer fields and populate them when editing/cancelling.
- [MODIFY] [admin_api.php](file:///c:/xampp/htdocs/mundial/admin_api.php): Save/update transfer fields in DB under `save_news`.
- [MODIFY] [style.css](file:///c:/xampp/htdocs/mundial/style.css): Add layout and glow animation classes for the visual transfer cards.
- [MODIFY] [fichajes.php](file:///c:/xampp/htdocs/mundial/fichajes.php): Render visual transfer cards for transfer entries with player photos, logos, and green arrow.
- [MODIFY] [index.php](file:///c:/xampp/htdocs/mundial/index.php): Render visual transfer cards on the dashboard for transfer news.
- [MODIFY] [script.js](file:///c:/xampp/htdocs/mundial/script.js): Trigger asynchronous `sync.php` background fetch on page load for auto-updates.

## Tasks

### Task 1: Sincronizador Automático de Noticias Reales
**Goal:** Automate news synchronization from ESPN for each league, checking duplicates and extracting transfer assets (athlete picture, club logos, status).
**Files:** [sync.php](file:///c:/xampp/htdocs/mundial/sync.php)
**Steps:**
- [ ] Add the cURL news requests loop for the five European leagues (`esp.1`, `eng.1`, `ita.1`, `ger.1`, `fra.1`) inside `sync.php`.
- [ ] Extract athlete tag and team tags from article categories to identify transfers.
- [ ] Construct player headshot URLs (`/headshots/soccer/players/full/{id}.png`) and team logos (`/teamlogos/soccer/500/{id}.png`) from ESPN IDs.
- [ ] Determine Spanish contract details ("Fichaje Confirmado", "Préstamo / Cesión", "Rumor / Interés") from keywords.
- [ ] Validate non-duplicate titles/slugs and execute insertion into `noticias` table.
- [ ] Run the sync CLI command to verify articles are populated in the database.
- [ ] Commit: `feat(sync): automate real espn transfer news ingestion`

### Task 2: Actualizar Backend Admin API para Nuevos Campos
**Goal:** Modify the Admin API backend to handle saving/updating the new visual transfer fields.
**Files:** [admin_api.php](file:///c:/xampp/htdocs/mundial/admin_api.php)
**Steps:**
- [ ] Extract POST variables for `foto_jugador`, `equipo_origen_nombre`, `equipo_origen_logo`, `equipo_destino_nombre`, `equipo_destino_logo`, `detalles_contrato`.
- [ ] Update the `UPDATE` query under `save_news` case to include all 6 new fields.
- [ ] Update the `INSERT` query under `save_news` case to include all 6 new fields.
- [ ] Verify that no PHP errors occur when saving news.
- [ ] Commit: `feat(admin-api): add support for transfer card fields in save_news`

### Task 3: Modificar Formulario de Panel de Administración
**Goal:** Add the new input fields to `admin.php` and handle them dynamically in `admin.js`.
**Files:** [admin.php](file:///c:/xampp/htdocs/mundial/admin.php), [admin.js](file:///c:/xampp/htdocs/mundial/admin.js)
**Steps:**
- [ ] In `admin.php`, wrap new input fields for transfer cards in a container `#transfer-fields-container` styled with `display: none`.
- [ ] In `admin.js`, listen to changes on `#news-tipo` select to show/hide the container.
- [ ] In `admin.js`, update `window.editArticle()` to populate the new fields when editing.
- [ ] In `admin.js`, update form reset/cancel logic to clear the new fields and hide the container.
- [ ] Log in as admin and verify the form displays/hides the extra inputs and successfully edits articles.
- [ ] Commit: `feat(admin-ui): integrate visual transfer inputs in admin form`

### Task 4: Agregar Estilos CSS para Tarjeta de Fichaje Premium
**Goal:** Add CSS styles for rendering visual transfer cards with glowing effects, responsive flexbox layout, and image sizing.
**Files:** [style.css](file:///c:/xampp/htdocs/mundial/style.css)
**Steps:**
- [ ] Define the `.fichaje-visual-card` class with gradient background, glow drop shadow, and flex container.
- [ ] Define styling for player avatar, club logos wrapper, green arrow animation, and bottom details badge.
- [ ] Add responsive grid layout styles to adapt these card sizes on desktop vs mobile.
- [ ] Commit: `style: add classes for visual transfer card layout and glow effects`

### Task 5: Implementar Renderizador en Fichajes e Inicio
**Goal:** Render the premium visual cards in `fichajes.php` and `index.php` for articles that have transfer details.
**Files:** [fichajes.php](file:///c:/xampp/htdocs/mundial/fichajes.php), [index.php](file:///c:/xampp/htdocs/mundial/index.php)
**Steps:**
- [ ] In `fichajes.php`, inspect news rows. If `foto_jugador` and `equipo_destino_logo` are present, render the visual card template instead of the regular post.
- [ ] In `index.php`, render the visual card template for featured fichajes in the grid.
- [ ] Test the display by visiting the website pages and checking the rendering of both normal news and visual cards.
- [ ] Commit: `feat(frontend): render visual transfer cards on home and news pages`

### Task 6: Sincronización Automática en Segundo Plano (Asíncrona)
**Goal:** Trigger the synchronization automatically in the background when users load the site, without causing page latency.
**Files:** [script.js](file:///c:/xampp/htdocs/mundial/script.js)
**Steps:**
- [ ] In `script.js`, perform a non-blocking `fetch('sync.php?key=5ligas_sync_secret')` inside the `DOMContentLoaded` event listener.
- [ ] Confirm in the network panel that the request executes, and completes instantly using cache or runs in the background.
- [ ] Commit: `feat(sync): trigger background auto-sync on frontend page load`

## Verification Plan

### Manual Verification
1. **ESPN News Import:** Run `sync.php` via command line (`c:\xampp\php\php.exe sync.php?key=5ligas_sync_secret`) or visit in the browser. Confirm that the `noticias` table gets populated with real-world news articles.
2. **Visual Layout:** Open the browser and check that the Fichajes page displays the visual cards with player photos, team logos, and the contract details exactly as shown in the mockup/screenshot.
3. **Admin Controls:** Log in to `admin.php` and test creating/editing/deleting a manual transfer news card. Ensure all fields populate correctly.
4. **Auto-Update:** Visit `index.php` or `liga.php`, open developer tools (Network panel), and verify `sync.php` is requested in the background asynchronously.
