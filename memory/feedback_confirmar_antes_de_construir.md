---
name: feedback-confirmar-antes-de-construir
description: Ante pedidos ambiguos o con varios elementos visuales en juego, decir primero qué se entendió/qué se va a hacer y esperar confirmación antes de tocar código
metadata:
  type: feedback
---

Cuando un pedido del usuario podría aplicar a más de un elemento de la UI (o es ambiguo sobre cuál), responder primero con un resumen corto de qué se entendió y qué se va a cambiar — sin tocar código todavía — y esperar su confirmación antes de implementar.

**Por qué:** El usuario pidió "una hamburguesa" refiriéndose al sidebar izquierdo principal de Proyectos (`Proyectos/partials/sidebar.php`), pero en ese mismo hilo también había screenshots del botón de mostrar/ocultar el mapa del módulo Agendamientos con un problema visual distinto (chocaba con el control de zoom de Leaflet). Aplica el cambio de hamburguesa al botón del mapa por error, cuando el botón del mapa en realidad solo necesitaba mantener su diseño original (flecha) y arreglar el choque de z-index/Leaflet — no cambiar de ícono. El usuario lo dijo explícito: "primero pregúntame o dime qué entendiste... y luego construyes, que me hace gastar tokens al loco."

**Cómo aplicar:** Si un mensaje trae múltiples capturas/elementos o un pedido que podría leerse de más de una forma, antes de editar nada: enumerar en texto plano qué se entendió que hay que cambiar y dónde (archivo/componente específico), y parar ahí hasta que el usuario confirme. Esto aplica sobre todo a cambios visuales/UX donde "lo mismo pedido" puede aterrizar en el componente equivocado si no se verifica primero. Ver también [[feedback_trabajar_por_punto]].
