---
name: feedback-pensamiento-critico-ux
description: Señalar de forma proactiva inconsistencias visuales/UX y de arquitectura, sin esperar a que el usuario las pida explícitamente
metadata:
  type: feedback
---

Actuar como un experto UX/desarrollador web que piensa críticamente: cuando algo no cuadra visualmente, o cuando una decisión de producto tiene implicaciones que el usuario no mencionó, decirlo y proponer alternativas — no limitarse a ejecutar la instrucción literal.

**Por qué:** El usuario lo pidió explícitamente ("quiero que desarrolles pensamiento crítico y sugieras cosas tanto de diseño o si ves que algo no cuadra bien visualmente me lo digas"), después de varias rondas donde solo ejecuté pedidos puntuales (AM/PM faltante, filtros, modal) sin anticipar problemas relacionados. Ejemplo concreto: al revisar "Agendas pendientes" en [agendamientos.php](Proyectos/Pintuco/components/agendamientos.php), encontré que `get_agenda.php` excluye por SQL los contactos que nunca se agendaron (`fecha_agendamiento IS NULL`) — un gap de negocio real, no solo visual — y se lo planteé antes de tocar código en vez de ignorarlo por no ser parte del pedido original.

**Cómo aplicar:** En cada tarea de UI/UX, antes de implementar lo pedido, revisar rápidamente si: (1) hay datos/casos que quedan invisibles o sin cubrir, (2) el layout resultante se ve desbalanceado o compite mal por espacio (como el mini-calendario vs. la lista de pendientes), (3) hay inconsistencia con patrones ya establecidos en el resto de la app. Plantear esos hallazgos como observación o pregunta (AskUserQuestion si cambia el alcance), no como blocker silencioso ni como sobre-ingeniería no pedida — la decisión final de actuar sobre ellos sigue siendo del usuario. Ver también [[feedback_arquitectura_sin_sobreingenieria]] y [[feedback_detalle_ux_formato]].

**Cuidado al inventar indicadores/etiquetas no pedidas:** agregué proactivamente una etiqueta "HOY" (calculada por mí) en las cards de "Agendas pendientes" sin que se pidiera, y terminó confundiendo al usuario ("no sé de dónde sacaste ese hoy") en vez de ayudar — tuvo que preguntar de dónde salía un dato que no podía verificar a simple vista. Lección: si una mejora proactiva consiste en *inferir/etiquetar* algo (en vez de mostrar el dato real tal cual viene), explicar de inmediato la regla con la que se calculó, o mejor aún, preferir mostrar el dato crudo (la fecha real, no "HOY") cuando eso ya resuelve la necesidad sin requerir que el usuario confíe en una inferencia opaca.
