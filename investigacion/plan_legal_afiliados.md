# Plan de Investigación Legal: Afiliación de Apuestas en España

Este documento resume el marco legal, restricciones y requisitos necesarios para implementar un sistema de enlaces de afiliados de casas de apuestas dirigido al mercado español en el portal de las 5 Grandes Ligas, asegurando el cumplimiento normativo.

## 1. Impacto de la Regulación Estatal del Juego

El marco legal principal en España está compuesto por la **Ley 13/2011 de Regulación del Juego** y el **Real Decreto 958/2020 de comunicaciones comerciales de las actividades de juego**.

*   **Afiliados como sujetos obligados**: El RD 958/2020 considera a los afiliados como prestadores de servicios de la sociedad de la información y, por tanto, sujetos obligados a cumplir las mismas normas que los operadores oficiales de juego.
*   **Prohibición de Bonos de Captación**: Queda terminantemente prohibida la publicidad de bonos de bienvenida o de captación. Las promociones solo pueden ofrecerse a usuarios que lleven registrados en la casa de apuestas al menos 30 días y hayan verificado documentalmente su identidad.
*   **Identificación Publicitaria**: Cualquier enlace, banner o reseña debe ser claramente identificable como publicidad (comunicación comercial).
*   **Veracidad y Responsabilidad**: La información no puede incitar a la adicción, ni distorsionar las probabilidades de éxito. Además, solo se pueden promocionar operadores que cuenten con licencia de la Dirección General de Ordenación del Juego (DGOJ).

## 2. Restricciones de Publicidad en Medios Digitales

Como portal web deportivo, la plataforma debe adherirse a las siguientes restricciones:

*   **Protección de Menores**: Ninguna comunicación comercial puede estar dirigida directa o indirectamente a menores de edad. El diseño y el tono no deben apelar al público infantil.
*   **Uso de Famosos**: Está prohibida la aparición de personas de relevancia pública o notoriedad (como jugadores de fútbol en activo, exjugadores famosos o influencers) en los banners o materiales promocionales de las apuestas.
*   **Etiquetado Obligatorio de Juego Responsable**: Todos los espacios publicitarios deben incluir de forma visible las advertencias estándar requeridas por la DGOJ:
    *   Logotipos de **"+18"**, **"JugarBien"** y **"Juego Seguro"**.
    *   Mensajes de advertencia sobre los riesgos del juego ("Sin diversión no hay juego", etc.).

## 3. Propuesta de Textos Legales (Privacy, Cookies y Disclaimer)

Para evitar sanciones y cumplir con el RGPD, la LOPDGDD y la LSSI, se deben implementar los siguientes elementos en la web:

### A. Política de Cookies (Específica para Afiliación)
Es indispensable contar con un banner de cookies que bloquee su instalación hasta obtener el **consentimiento expreso** del usuario.
*   **Cookies de Afiliado**: Se debe explicar claramente que se utilizan cookies de terceros (de las casas de apuestas o redes de afiliación) para rastrear el origen de los registros y contabilizar comisiones.
*   **Finalidad**: Identificar el tráfico saliente con fines comerciales.

### B. Descargo de Responsabilidad (Disclaimer)
Se debe incluir un texto visible (por ejemplo, en el footer de la web y cerca de los enlaces/cuotas de apuestas) con la siguiente estructura:

> **Descargo de Responsabilidad Comercial y Juego Responsable**
> Este sitio web contiene enlaces de afiliados de operadores de juego online. Si haces clic en estos enlaces y te registras en la plataforma, podríamos recibir una comisión económica sin coste adicional para ti.
> 
> **Atención**: La información y los enlaces de apuestas mostrados están dirigidos estrictamente a personas **mayores de 18 años**. El juego online puede generar adicción. Por favor, juega de forma responsable y solo si puedes permitírtelo. Para más información, visita [JugarBien.es](https://www.jugarbien.es).

### C. Política de Privacidad
*   Debe identificar al responsable de la web.
*   Si se recogen emails de usuarios (newsletter), no se podrán usar para enviar ofertas de apuestas a menos que el usuario marque una casilla separada consintiendo expresamente el envío de "comunicaciones comerciales sobre juego y apuestas", verificando que es mayor de edad.

## Siguientes Pasos
- [ ] Incorporar el disclaimer en el `footer.php` de la web.
- [ ] Añadir etiquetas de "+18" y "Jugar Bien" junto a los widgets de cuotas/pronósticos (si los hubiera).
- [ ] Actualizar o implementar un banner de gestión de cookies compatible con el RGPD.
