# Especificación de Diseño: Portal de Fútbol 5 Grandes Ligas de Europa

Documento de diseño y arquitectura para el portal de fútbol dinámico de las 5 grandes ligas de Europa (Premier League, LaLiga, Serie A, Bundesliga, Ligue 1) con sincronización en tiempo real de ESPN, optimización de SEO avanzada y monetización integrada.

## 1. Declaración del Problema y Metas
- **Meta:** Transformar el portal actual del Mundial 2026 en un portal multiligas de fútbol europeo de primer nivel.
- **Prioridad 1: SEO:** La web debe estructurarse utilizando URLs dedicadas para cada liga, noticia y análisis de apuestas, con etiquetas semánticas y metadatos dinámicos optimizados.
- **Prioridad 2: Monetización:** Integración fluida de espacios publicitarios estándar (banners) y módulos de afiliación (casas de apuestas y venta de merchandising/camisetas) controlados desde el panel de administración.
- **Prioridad 3: Estética y Datos Dinámicos:** Diseño oscuro con efectos "glow", tipografía premium y actualizaciones en tiempo real del calendario, marcadores y clasificaciones vía API de ESPN.

## 2. Enfoque Elegido: Aplicación Multilistas PHP (MPA)
Se opta por una arquitectura de múltiples archivos PHP (`index.php`, `liga.php`, `pronosticos.php`, `fichajes.php`) compartiendo un archivo de conexión a base de datos (`db.php`) y un motor de sincronización (`sync.php`).
- **Index.php:** Dashboard general con partidos en vivo de la jornada de las 5 ligas, últimas noticias de fichajes y previas destacadas de apuestas.
- **Liga.php:** Vista específica para cada una de las 5 ligas, filtrando partidos y mostrando su tabla de clasificación local.
- **Pronosticos.php:** Páginas detalladas de previa de partidos importantes para indexar en Google, con enlaces de afiliado a casas de apuestas y cuotas de mercado.
- **Fichajes.php:** Últimas noticias y rumores de traspasos del fútbol europeo.
- **Admin.php:** Panel para que el administrador actualice enlaces de afiliado, códigos de publicidad de banners e inserte noticias o previas sin modificar código.

## 3. Modelo de Datos y API
### Cambios en Base de Datos (Tablas):
- **ligas:** `id` (VARCHAR primary key e.g., 'eng.1', 'esp.1'), `nombre`, `pais`, `logo_url`.
- **equipos:** Agregar `liga_id` que relaciona con `ligas.id`.
- **partidos:** Agregar `liga_id`.
- **noticias:** `id` (INT auto_increment), `tipo` ('fichaje', 'pronostico'), `titulo`, `slug` (para SEO), `contenido`, `enlace_afiliado`, `fecha_creacion`.
- **configuracion:** `clave` (VARCHAR primary key e.g., 'banner_header', 'link_afiliado_apuestas'), `valor` (TEXT).

### Integración de ESPN API:
Sincronización secuencial de marcadores y clasificaciones utilizando las siguientes llamadas de ESPN:
- **Premier League:** `eng.1`
- **LaLiga:** `esp.1`
- **Serie A:** `ita.1`
- **Bundesliga:** `ger.1`
- **Ligue 1:** `fra.1`

## 4. Monetización e Integración de Anuncios
- **Banner Superior (Header Banner):** Espacio de publicidad adaptable de 728x90 px (móvil 320x50 px) en la parte superior.
- **Sidebar Banner:** Espacio lateral derecho de 300x250 px o 300x600 px en vistas de artículos y clasificaciones.
- **Enlaces de Afiliado Deportivos:** Botones llamativos ("Apostar Ahora") en la sección de pronósticos con cuotas simuladas y widgets de recomendación de compra de camisetas de los equipos.

## 5. SEO Técnico
- **Etiquetas Semánticas HTML5:** Uso consistente de `<main>`, `<article>`, `<aside>` y cabeceras estructuradas (`<h1>`, `<h2>`).
- **Metadatos dinámicos:** Título, meta description y canonical URL dinámicas según la ruta.
- **JSON-LD Schema:**
  - Esquema `NewsArticle` para fichajes y previas.
  - Esquema `SportsEvent` para partidos con marcadores activos de ESPN.

## 6. Plan de Verificación
- **Pruebas de API:** Validar que `sync.php` puede recorrer las 5 ligas y poblar correctamente las tablas sin errores ni duplicaciones.
- **Prueba de SEO y Metadatos:** Cargar las páginas y verificar que las etiquetas `<title>` y `<meta name="description">` cambian dinámicamente según la página.
- **Pruebas de Administración:** Guardar enlaces de afiliado y banners en el panel administrativo y verificar su correcta visualización e inserción en el frontend.

## 7. Seguridad y Protección
Para garantizar que el portal sea seguro frente a ataques comunes, implementaremos las siguientes medidas:
- **Protección contra Inyecciones SQL (SQLi):** Todas las consultas a la base de datos se realizarán mediante sentencias preparadas de PDO (`prepare` y `execute`), evitando la concatenación directa de parámetros del usuario.
- **Protección contra Scripting en Sitios Cruzados (XSS):** Todo el contenido que se imprima en el navegador y provenga de la base de datos o de inputs del usuario será escapado usando `htmlspecialchars()`. Para el contenido enriquecido redactado en el panel de administración, usaremos un sanitizador selectivo.
- **Seguridad en el Panel de Administración (`admin.php`):**
  - Implementación de un sistema de login con sesión segura y almacenamiento de contraseñas utilizando `password_hash()` de PHP (algoritmo bcrypt).
  - Uso de tokens anti-CSRF (Cross-Site Request Forgery) en todos los formularios de configuración y creación de artículos.
- **Seguridad del Script de Sincronización (`sync.php`):**
  - Para evitar que usuarios malintencionados ralenticen el servidor ejecutando la sincronización repetidamente, el archivo `sync.php` requerirá una clave secreta (`sync.php?key=CLAVE_SECRETA`) para ejecutarse vía web, o bien solo podrá ejecutarse de forma interna/CLI.

## 8. Automatización e Integración de Rumores Reales y Fichajes Visuales (v1.3)
Para cumplir con los nuevos requisitos de automatización total de noticias reales y la representación visual de fichajes de alto impacto (estilo cromo/tarjeta premium), se implementan las siguientes especificaciones:

### 8.1 Sincronización de Noticias Reales (ESPN News API)
Durante el proceso de sincronización en `sync.php`, se consumirá la API de noticias de ESPN:
`https://site.api.espn.com/apis/site/v2/sports/soccer/{liga_id}/news`
- **Evitar duplicados:** Se validará que el titular no exista en la base de datos antes de guardarlo.
- **Categorización:** Se analizarán los metadatos de la noticia (`categories`). Si se detectan tags de atletas (`type => 'athlete'`) y equipos (`type => 'team'`), se catalogará de forma inteligente:
  - Se obtendrá la foto del jugador desde el CDN oficial de ESPN: `https://a.espncdn.com/i/headshots/soccer/players/full/{athleteId}.png`.
  - Se asignarán los logos de los equipos desde el CDN de ESPN: `https://a.espncdn.com/i/teamlogos/soccer/500/{teamId}.png`.
  - Se inferirá el tipo de contrato ("Fichaje Confirmado", "Préstamo", "Rumor / Interés") a partir de palabras clave en el titular.

### 8.2 Tarjeta Visual Premium de Fichajes (Fichajes Hechos y Rumores)
Si un artículo cuenta con el campo `foto_jugador` y logos de equipos (origen/destino), se renderizará una tarjeta premium con el siguiente diseño responsivo (inspirado en la imagen de referencia):
- **Parte Superior:** Foto recortada del jugador con un resplandor (glow) neón dorado/verde de fondo y su nombre en letras grandes.
- **Cuerpo Central:** Contenedor con el logo del equipo origen (izquierda), una flecha verde vibrante apuntando al destino, y el logo del equipo de destino (derecha). Debajo de cada escudo se muestra el nombre del equipo correspondiente.
- **Parte Inferior:** Detalles del contrato (por ejemplo: "12 MESES" o "FICHAJE CONFIRMADO") impreso en negrita sobre un borde difuminado.
- **Transición suave:** Efecto hover con escalado e incremento de resplandor para una sensación premium e interactiva.

### 8.3 Sincronización Automática al Navegar (Background Auto-Sync)
Para que los partidos jugados y clasificaciones se actualicen solos sin intervención manual:
- Se incluirá una llamada asíncrona de `fetch('sync.php?key=5ligas_sync_secret')` en el JS global (`script.js`) al cargar la página.
- El script de sincronización `sync.php` responderá en milisegundos si la sincronización se ejecutó hace menos de 30 segundos, evitando saturar la API de ESPN y garantizando una velocidad de carga óptima. Si ha pasado más de 30 segundos, se actualizarán los datos de forma silenciosa de fondo.


