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
- **Prueba de Administración:** Guardar enlaces de afiliado y banners en el panel administrativo y verificar su correcta visualización e inserción en el frontend.
