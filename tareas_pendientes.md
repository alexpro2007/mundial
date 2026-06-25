# 📋 Lista de Tareas Pendientes - Proyecto 5 Ligas

Este documento detalla el estado actual de las tareas de desarrollo e integración del portal de las **5 Grandes Ligas Europeas**. Permite llevar un seguimiento del progreso hacia el lanzamiento final.

---

## 🛑 Tareas Bloqueantes (Humano)

_Esta sección contiene tareas que requieren intervención, decisiones o acciones manuales por parte del usuario. Deben ser añadidas y completadas a medida que sea necesario._

- [ ] (Añadir tarea bloqueante aquí si es necesario)

---

## 🚀 Tareas Críticas Pendientes

### 1. Maquetación y Estilos CSS de las Tarjetas Visuales de Fichajes

El HTML y la integración de datos para mostrar las tarjetas de fichaje premium ya están implementados, pero **falta definir los estilos visuales** en `style.css` para que se rendericen correctamente.

- [ ] Definir estilos premium para la clase `.fichaje-visual-card` (gradientes de fondo oscuros, bordes de cristal reflectantes y sombras con efecto resplandor/glow).
- [ ] Diseñar el contenedor y la máscara para la foto del jugador (`.visual-player-photo-wrapper` y `.visual-player-photo`).
- [ ] Maquetar la fila de transferencia (`.visual-transfer-row`), alineando los nombres y logos de los clubes de origen y destino de forma responsiva.
- [ ] Añadir animación/estilo para el icono de la flecha verde indicadora del traspaso (`.visual-arrow-wrapper` y `.visual-arrow-icon`).
- [ ] Estilizar la etiqueta de detalles del contrato (`.visual-contract-details`) con un badge destacado según el tipo (Ej. _Fichaje Confirmado_, _Préstamo_, _Rumor_).
- [ ] Ajustar el diseño responsivo en móvil mediante `@media` queries para asegurar que las tarjetas de fichajes se vean perfectas en pantallas verticales.

### 3. Cambiar Idioma de las noticias y fichajes

Modificar la parte de fichajes, para que las notias esten en los idiomas elegidos por el ususario.

- [ ] Se han adaptado al idioma selecionado cada una de las noticias/fichajes.

---

## ✅ Tareas Completadas

### 2. Plan de Investigación Legal (Afiliación de Apuestas en España)

- [x] Analizar el impacto de la regulación estatal del juego (Ley 13/2011 y Real Decreto 958/2020 de comunicaciones comerciales de las actividades de juego).
- [x] Identificar restricciones de publicidad en medios digitales, perfiles de edad permitidos y etiquetados obligatorios de juego responsable.
- [x] Redactar una propuesta de descargo de responsabilidad (Disclaimer) y políticas de cookies/privacidad adaptadas a la legislación local para evitar sanciones.
- [x] (Implementado) Incorporar el disclaimer en el `footer.php`, logos de +18 y banner de cookies.

### 2. Sincronizador Automático de Noticias Reales (ESPN)

- [x] Implementar bucle de peticiones cURL en `sync.php` para descargar las noticias oficiales de las 5 grandes ligas europeas y del Mundial.
- [x] Extraer etiquetas de atletas y equipos de las categorías provistas por ESPN.
- [x] Construir las URLs dinámicas para fotos de jugadores (`/headshots/...`) y logos de clubes (`/teamlogos/...`).
- [x] Clasificar el estado del traspaso ("Fichaje Confirmado", "Préstamo / Cesión", "Rumor / Interés") analizando palabras clave en el titular.
- [x] Controlar la inserción para evitar artículos duplicados basándose en títulos y slugs únicos.

### 3. Backend e Interfaz de Administración

- [x] Habilitar la recepción y guardado en `admin_api.php` de los 6 nuevos campos visuales de fichajes (`foto_jugador`, logos/nombres de clubes y detalles).
- [x] Crear el formulario desplegable en `admin.php` (`#transfer-fields-container`) para poder añadir y editar fichajes manualmente de forma visual.
- [x] Integrar control por JavaScript en `admin.js` para mostrar u ocultar estos campos adicionales según el tipo de artículo seleccionado (_Fichaje_ o _Pronóstico_).
- [x] Asegurar la compatibilidad al editar y resetear el formulario.

### 4. Renderizado en el Frontend (PHP)

- [x] Modificar `fichajes.php` para comprobar si un artículo tiene datos visuales y pintar la estructura de tarjeta premium.
- [x] Adaptar `index.php` para incorporar la tarjeta de fichaje visual en la rejilla principal de noticias del Dashboard.
- [x] Incluir el script de sincronización asíncrona de fondo en `script.js` para actualizar las noticias al cargar la web sin ralentizar al usuario.

### 5. Motor de Datos y Corrección de Fechas (Temporada 2026)

- [x] Solucionar el problema de visualización de datos de años anteriores.
- [x] Adaptar `sync.php` para calcular dinámicamente el año de la temporada europea actual.
- [x] Configurar los rangos de fechas correctos para la temporada en curso en las peticiones a la API de ESPN.
- [x] Optimizar la ordenación del bracket y grupos en la clasificación de ligas (`liga.php`).

### 6. Sistema de Localización (Multi-idioma)

- [x] Diseñar e implementar el selector de idiomas en el menú superior.
- [x] Configurar traducciones localizadas para español, inglés, portugués, italiano y francés en `lang.php`.
- [x] Traducir la estructura de la web, títulos SEO, pies de página y ventanas modales de partidos.
