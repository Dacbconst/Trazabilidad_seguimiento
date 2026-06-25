---
name: feedback-detalle-ux-formato
description: Cuidar detalles finos de formato/UX (AM-PM, unidades, textos secundarios) antes de dar una tarea por terminada
metadata:
  type: feedback
---

Antes de reportar una mejora visual/UX como completa, revisar los detalles finos de formato (AM/PM, unidades, textos "(aprox)", truncamientos, etc.), no solo la estructura general del componente.

**Por qué:** En la agenda de Pintuco ([agendamientos.php](Proyectos/Pintuco/components/agendamientos.php)) configuré `eventTimeFormat`/`slotLabelFormat` con `meridiem: 'short'` pero olvidé `hour12: true`. Con `locale: 'es'`, FullCalendar/Intl asumía formato 24h y el AM/PM nunca se renderizaba a pesar de que el código de limpieza de texto (`formatoHora12`) ya estaba — el usuario tuvo que señalarlo explícitamente ("te faltan cosas tan básicas como poner un PM"). Pidió explícitamente actuar "como un experto UX y desarrollador web" prestando atención a este nivel de detalle.

**Cómo aplicar:** Cuando se configuren librerías de fecha/hora (FullCalendar, Intl, date-fns, etc.) con locale no-inglés, verificar explícitamente las opciones que controlan el ciclo horario (`hour12`, `hourCycle`) en vez de asumir que `meridiem`/`hour` por sí solos bastan. En general, para cambios de UI, hacer un repaso mental de "¿qué vería un usuario real pixel por pixel" antes de decir que algo quedó listo, no solo verificar que la lógica/estructura compile.

Relacionado: [[feedback_arquitectura_sin_sobreingenieria]]
