---
name: feedback-trabajar-por-punto
description: Responder cada petición punto por punto, sin fusionar ni ignorar ninguna, y verificar causa raíz real antes de reportar algo como hecho
metadata:
  type: feedback
---

Cuando el usuario manda varias peticiones en un mismo mensaje (numeradas o no), responder y confirmar **cada una por separado** — nunca fusionar dos pedidos en una sola explicación ni dar por hecho que "ya quedó" sin verificarlo contra el código real.

**Por qué:** El usuario lo dijo explícitamente y molesto: "Siempre trabajarás por punto sin ignorar mis peticiones", después de que varias rondas seguidas reportara como completados cambios (botón del mapa, mini-card de conflicto) que el usuario no veía reflejados en el sitio en vivo. La causa real era una mezcla de: (a) cambios reales que sí estaban bien en el código pero nunca llegaban desplegados, y (b) `style.css`/`agenda.css`/`agenda.js` sin cache-busting en sus URLs (`<link href="style.css">` sin `?v=`), así que el navegador seguía sirviendo versiones viejas en caché incluso después de subir archivos nuevos al servidor — eso explicaba simultáneamente el sidebar izquierdo "roto" (HTML nuevo + CSS viejo cacheado = botón sin estilo) y la card de conflicto apareciendo sin estilo al final de la página.

**Cómo aplicar:**
1. Antes de decir "ya está" sobre algo que el usuario va a probar en un entorno real (no local), preguntarse si hay alguna capa de caché/build/deploy entre mi edición y lo que el usuario verá — no asumir que "guardé el archivo" equivale a "ya se ve".
2. Si el usuario reporta que algo "no se ve" después de subirlo, antes de re-implementar o sospechar de mi propio código, revisar primero: ¿hay cache-busting en los assets estáticos (CSS/JS)? ¿está todo el set de archivos relacionados realmente actualizado?
3. Al responder un mensaje con múltiples quejas/pedidos, estructurar la respuesta para que se note que cada punto fue atendido individualmente (aunque la causa raíz sea una sola), no agruparlos en un párrafo genérico.

Ver también [[project_agendamientos_pintuco]] (sección de estado de despliegue) y [[feedback_pensamiento_analitico_casos_reales]].
