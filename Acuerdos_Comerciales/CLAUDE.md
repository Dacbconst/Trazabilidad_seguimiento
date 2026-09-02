# CLAUDE.md — Proyecto ADN (Acuerdo de Desarrollo de Negocios)

Contexto de negocio y técnico para trabajar en este proyecto. Cliente: Jabonería
Wilson S.A. (empresa de Alicorp). Sistema para digitalizar el proceso de Acuerdos
Comerciales (Acta de Compromiso) con distribuidores/PDV del canal directo.

## ⚠️ Excepción a la regla de solo lectura — SOLO en este proyecto (2026-08-28)

El `CLAUDE.md` raíz del repositorio (fuera de esta carpeta) dice que Claude
solo puede ejecutar `SELECT`/`SHOW`/`DESCRIBE` en cualquier base de datos
del repo, sin excepción. **Esa regla raíz NO cambió y sigue aplicando tal
cual a todos los demás proyectos** (ej. Pintuco). El usuario pidió una
excepción puntual, **solo para Acuerdos Comerciales**, con este alcance
exacto — no ampliar más de lo que dice acá:

- **Permitido, y SOLO esto**: `CREATE TABLE` y `ALTER TABLE`.
- **Requisito obligatorio, sin excepción, para cada ejecución**: antes de
  correr el `CREATE`/`ALTER`, Claude tiene que mostrarle al usuario el SQL
  EXACTO que va a ejecutar y esperar una confirmación explícita ("sí" o
  equivalente claro) para ESE SQL puntual. Nunca ejecutar de entrada, nunca
  asumir que una aprobación anterior cubre una ejecución nueva o distinta.
- **Sigue absolutamente prohibido, sin ninguna excepción, igual que
  siempre**: `DROP TABLE`, `DROP DATABASE`, `DELETE`, `TRUNCATE`, `UPDATE`,
  `INSERT`, o cualquier otra operación que borre/modifique datos o
  esquema fuera de `CREATE TABLE`/`ALTER TABLE`. Esto incluye `DROP` como
  parte de un `ALTER` (ej. `ALTER TABLE ... DROP COLUMN`) — si un `ALTER`
  necesario incluye un `DROP COLUMN`/`DROP INDEX`, sigue prohibido
  ejecutarlo Claude; en ese caso, proponer el SQL para que el usuario lo
  corra él mismo (mismo criterio que ya se usaba para todo antes de esta
  excepción).

## Entorno de desarrollo en vivo — Claude puede loguearse ahí (2026-08-25)

El usuario confirmó explícitamente que Claude puede entrar de verdad al
entorno de desarrollo para probar en vivo (con Playwright u otra
herramienta), no solo con mirrors locales:

- **URL**: `https://webecuador-desarrollo.azurewebsites.net/App/XploraEcuador/Acuerdos_Comerciales/`
- **No es producción** — el usuario lo confirmó explícito ("estamos en
  desarrollo... aún no está lanzada la página, podés meterte").
- **Credenciales de prueba**: usuario `JAVIER MALDONADO`, contraseña `1234`
  (rol `superdesarrollador`, ve las 5 secciones del sidebar).
- Sigue aplicando la regla de solo lectura del inicio de este archivo:
  Claude puede loguearse y NAVEGAR (nunca escribir/modificar datos reales
  desde ahí — no subir Actas, no guardar usuarios, no eliminar nada).
- **Dato de infraestructura confirmado usándolo**: los cambios a
  `assets/css/*.css` se reflejan ahí casi al instante, pero los cambios a
  archivos `.php` (`index.php`, `components/*.php`, etc.) tardan más en
  aparecer (probablemente opcache de PHP, no confirmado). Si un cambio de
  CSS se ve reflejado pero uno de PHP no, esperar un rato — no asumir que
  el archivo local está mal.
- Usado ya para: encontrar el bug real del buscador de Historial
  (`.ac-hist-search-wrap`, ver "Responsive / mobile" más abajo), medir con
  precisión (`getBoundingClientRect`) el espaciado real del header en vez
  de solo mirar capturas, y correr un barrido sistemático (Playwright,
  login real, 25 combinaciones de pantalla×ancho) que encontró la causa
  compartida del bug de "select bonito" en las 5 pantallas a la vez — ver
  el detalle completo en "Responsive / mobile" más abajo. **Preferir este
  entorno sobre mirrors locales cuando haya dudas reales** — los mirrors
  sirven para iterar rápido, pero ya se demostró que pueden no reproducir
  bugs reales (fuentes/imágenes reales, timing real, componentes
  compartidos entre pantallas).

## Protección contra fuerza bruta en el login (2026-08-24)

Encontrada en una revisión de seguridad a pedido del usuario: el login no
tenía ningún límite de intentos — vulnerable a fuerza bruta directa contra
`getters/procesar_acceso.php`. Corregido:

```sql
ALTER TABLE repositorio_usuarios_acuerdos
  ADD COLUMN intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN bloqueado_hasta DATETIME NULL;
```

**Ya corrida en producción, confirmar con `DESCRIBE`** antes de asumir que
está activa (al momento de escribir esto el usuario todavía no la había
corrido).

- `login()` (`includes/functions.php`) ahora devuelve `true` (ok), `false`
  (usuario/contraseña incorrectos) o el string `'bloqueado'` — 5 intentos
  fallidos SEGUIDOS bloquean la cuenta 15 minutos
  (`DATE_ADD(NOW(), INTERVAL 15 MINUTE)`). Un login exitoso resetea el
  contador a 0. Mismo patrón de fallback que ya usaba esta función para la
  columna `supervisor` (probar `prepare()`, si falla por columna
  inexistente cae al login de siempre SIN bloqueo) — no rompe el acceso
  mientras el usuario corre el `ALTER`.
- `getters/procesar_acceso.php` distingue el resultado y redirige a
  `login.php?error=bloqueado` (mensaje específico) vs `?error=1` (mensaje
  genérico de siempre).
- **Tradeoff de diseño, a propósito**: el mensaje de "cuenta bloqueada" es
  distinto del genérico de "usuario o contraseña incorrectos" — esto
  técnicamente permite a alguien confirmar que un `usuario` existe (5
  intentos fallidos + mensaje de bloqueo = el usuario existe; con un
  usuario inexistente nunca se llega a ese mensaje). Se aceptó ese riesgo
  menor de enumeración a cambio de que el usuario real sepa POR QUÉ no
  puede entrar con la contraseña correcta (mejor UX) — para una app interna
  de un puñado de usuarios, se consideró proporcional.
- **No se pudo probar en vivo**: a diferencia del resto de funciones de
  este proyecto, `login()` con la protección nueva ESCRIBE en la base
  incluso con una contraseña incorrecta (incrementa `intentos_fallidos`) —
  ni siquiera un intento de prueba deliberadamente fallido se puede
  ejecutar bajo la regla de solo lectura. Validado solo por revisión de
  código + `php -l`, pendiente de que el usuario lo pruebe él mismo
  (servidor local disponible si lo pide).

## Registrar Acuerdo PDV — 4 arreglos puntuales (2026-08-24, feedback con captura real)

El usuario reportó, con una captura real del entorno de desarrollo a 100% de
zoom (no 75%, con el que se había probado antes), varios problemas —
diagnosticados y arreglados con un mirror local (`php -S` + sesión falsa,
sin escribir nada en la base) porque el fix no estaba desplegado todavía
como para probarlo en el entorno real de arriba:

- **"Meses Incluidos" quedaba pegado al borde de la tarjeta de filtros** a
  1440px de ancho (reproducido exacto con Playwright, mismo look que la
  captura del usuario). Causa: `.ac-field` es un ítem de `.ac-acuerdo-filtros`
  (CSS Grid) pero nunca tuvo `min-width: 0` — mismo bug/lección que ya
  encontró la sesión de responsive de hoy en `#hist-tabla` (ver "Responsive
  / mobile" más abajo: "cualquier contenedor flex/grid/tabla en este
  proyecto necesita min-width:0 explícito para poder encogerse"). Sin eso,
  el contenido intrínseco (el texto "ENERO-FEBRERO-MARZO") obligaba al ítem
  a desbordar su columna en vez de dejar que el `overflow:hidden;
  text-overflow:ellipsis` que YA tenía `.ac-input-readonly` hiciera su
  trabajo. Arreglado con `.ac-field { min-width: 0; }` (global, sin efecto
  en los `.ac-field` que no están dentro de un grid — la mayoría del resto
  de la app) + `.ac-field-meses { grid-column: span 2; }` nueva (le da el
  doble de ancho a ese campo específico, es el que más texto necesita:
  "JULIO-AGOSTO-SEPTIEMBRE" es el peor caso). Probado con Playwright a
  1280/1366/1440px y con las 4 combinaciones de trimestre — el peor caso
  entra cómodo, sin desbordar.
- **"Previsualización" sin ningún feedback de carga** mientras Dompdf arma
  el PDF — el usuario reportó que parecía "quedarse colgado" y terminaba
  clickeando varias veces. Reusa el componente ya existente
  `assets/js/cargando.js` (`acBotonCargando()`/`acMostrarCargando()`/
  `acOcultarCargando()`, construido hoy por otra sesión para el mismo
  síntoma en Historial) — mismo patrón exacto que `historial.js`/
  `liquidacion.js` (`.then()/.catch()/.finally()`). Bloquea TODO el
  formulario (`.ac-acuerdo`, se le agregó `position:relative` para anclar
  el overlay) mientras arma el PDF, no solo el botón.
- **Título del Acta de Directo partido en 2 líneas** ("Meta de Compras en
  Dólares" + salto de línea + "Home Care") — el usuario lo reportó
  confundido primero como un problema de Distribuidor (que en realidad
  está bien, mide en Cajas, verificado hoy contra el Excel real, sin
  tocar), y tras aclarar por chat resultó ser sobre Directo. Cambiado el
  `<br>` por `+` en `includes/acta_pdf.php`: ahora
  `'1. Meta de Compras en Dólares + Home Care'` en una sola línea, para
  Directo con Y sin visibilidad (mismo punto de código, no depende del
  switch). Distribuidor (`'1. Meta de Compras en Cajas'`) no se tocó.
- **Campo "Local" → "Distribuidor" en canal Directo únicamente** (pedido
  explícito, revierte PARCIALMENTE el rename de 2026-08-20 — Distribuidor
  sigue diciendo "Local" para no repetir la palabra "Distribuidor" con 2
  campos de esa pantalla, "el usuario eligió esa opción de 3" vía
  `AskUserQuestion`). Label + placeholder ahora condicionales por
  `$canalUsuario` en `registrar.php`; en `registrar.js` se agregó
  `etiquetaCampoLocal()` (lee `CANAL_USUARIO` global) y se usa en los 3
  lugares que generaban texto para este campo (`describirCampoCombo()`,
  el mensaje "Selecciona un ...", los placeholders "Elige un ... primero"
  de las tablas). **No se tocaron** los mensajes de error de
  `guardar_acuerdo.php` (backend, genéricos, poca visibilidad — mismo
  criterio de alcance que el rename original: "el usuario acotó el pedido
  al módulo de registro").

**Ronda 2, mismo día — repartir el ancho de los filtros a propósito**: el
usuario pidió Distribuidor más ancho, Año y Meses Incluidos más angostos.
`grid-template-columns: repeat(auto-fit, minmax(180px,1fr))` reparte el
espacio sobrante en partes IGUALES entre columnas, sin forma limpia de
pesar una más que otra — se cambió `.ac-acuerdo-filtros` de `display:grid`
a `display:flex; flex-wrap:wrap`, con `flex-grow`/`flex-basis` por campo
(`#ac-distribuidor-field`/`#ac-empresa-field` con el doble+ de peso,
`#ac-anio-field` con menos de la mitad, `.ac-field-meses` con un poco más
que el peso base — ya no `grid-column:span 2` fijo). **Cuidado si se toca
`#ac-anio-field` de nuevo**: un `flex-basis` de 90px cortaba "2026" a "2…"
— el trigger de "select bonito" (`select-bonito.js`) necesita más ancho
que el texto crudo (padding + ícono de flecha), 115px fue el mínimo que no
cortó en las 3 pruebas. Probado con Playwright a 1280/1366/1440px: a 1440px
"Meses Incluidos" queda con truncamiento parcial ("ENERO-FEBRERO-MA…") —
**es el trade-off aceptado a propósito** (el usuario pidió angostarlo para
darle el espacio a Distribuidor), no un bug — a 1280/1366px entra completo
porque ahí ya bajó a su propia fila.

**Ronda 3, mismo día — fila "Meta en Dólares" en la tabla del PDF, solo
Directo (con y sin visibilidad)**: pedido explícito con captura de
referencia (el formato real trae 2 filas de encabezado: "Categoría"/
"Rebate"/"Estimado a Ganar" con `rowspan="2"`, y una celda combinada "Meta
en Dólares" con `colspan` sobre ENE/FEB/MAR/Total Período, con los meses en
una 2da fila debajo). Implementado en `includes/acta_pdf.php`, tabla de
Meta de Compras, condicionado a `!$esDistribuidor` — Distribuidor sigue con
su encabezado de 1 sola fila de siempre, sin tocar (no pedido ahí, y ya
dice "Cajas" no "Dólares"). **Mismo patrón de ancho que ya estaba probado
en la tabla de Perchas más abajo en este archivo** (la celda combinada de
arriba NO lleva `width` propio — Dompdf igual reparte bien los anchos
usando la fila de ABAJO, que sí tiene una celda por columna con su
`ancho_style()`; no hizo falta redescubrir esto, ya estaba documentado).
**Probado**: no se pudo previsualizar el PDF real generado por Dompdf
directo (Playwright headless no renderiza `file://*.pdf`, lo trata como
descarga) — se generó el PDF real igual (bytes válidos, sin excepciones) y
además se renderizó `generar_acta_html()` (la MISMA función, antes de
pasar por Dompdf) en un navegador real para confirmar visualmente que el
`rowspan`/`colspan` alinea bien: "Meta en Dólares" cae exacto sobre
ENE/FEB/MAR/Total Período en los 2 formatos de Directo, y Distribuidor
quedó pixel-igual a antes del cambio.

**Ronda 4, mismo día — tabla de Meta de Compras "se ve muy alta" +
encabezado "Rebate" partido en 2 líneas**, reportado con captura real
(categorías largas tipo "CABELLO DE ANGEL LARGOS DON VITTORIO"). Causa
real: con nombres de categoría largos, `ancho_columna_categoria()` llegaba
a su tope de 48% y dejaba muy poco resto para repartir — Rebate (peso 8 de
74) quedaba en ~5-6% de la tabla, insuficiente para la palabra "REBATE" en
1 línea. 3 cambios en `includes/acta_pdf.php`:
- `ancho_columna_categoria(..., 22, 38)` — tope bajado de 48 a 38 (el
  mínimo de 22 no se tocó).
- Pesos re-balanceados: Rebate 8→16 (el doble), restado de Total
  Período y Estimado a Ganar (16→12 cada uno) — el denominador 74 no
  cambió, solo la distribución.
- `td, th` compartían el mismo padding vertical (7px) — separado en 2
  reglas, `th` se queda en 7px, `td` (filas de datos) baja a 4px. Aplica a
  las 4 tablas por igual (Meta de Compras/Cabeceras/Rumas/Perchas
  comparten el mismo CSS `td`/`th`) — no se acotó solo a Meta de Compras,
  a propósito, para que las tablas del documento se vean consistentes
  entre sí.
- Nueva clase `.meta-tabla-compras` (además de `.meta-tabla`, que
  comparten las 4 tablas) con `margin-bottom` extra (+14px) — **esta sí
  acotada solo a la tabla de Meta de Compras**, no a las otras 3, mismo
  criterio que el espacio extra que ya tiene el párrafo antes de las
  firmas (no es la primera vez que se pide "como un enter de Word").
- **Probado**: mismo método que la ronda 3 (`generar_acta_html()` en
  navegador real, sin pasar por Dompdf/PDF) con datos sintéticos calcando
  los nombres largos de la captura real — "REBATE" entra en 1 sola línea,
  filas visualmente más compactas. **Ojo**: el ancho real en el PDF de
  Dompdf puede repartirse distinto a un navegador (ver notas de "OJO"
  arriba en este archivo sobre `ancho_style()`/`table-layout:fixed`) —
  si en el entorno real "Rebate" todavía se ve apretado, el próximo ajuste
  es subir el peso de 16 a algo más alto, no revisar de cero la causa.

**Ronda 5, mismo día — misma fila combinada, ahora también en Distribuidor
("Meta en Cajas")**: pedido explícito para extender el patrón de la Ronda 3
(hasta entonces solo Directo) a Distribuidor, con el mismo texto que ya usa
esta tabla para la unidad (`$fmt`: "Dólares" en Directo, "Cajas" en
Distribuidor). El `<thead>` de la tabla de Meta de Compras en
`includes/acta_pdf.php` estaba ramificado en 2 estructuras distintas por
`$esDistribuidor` (una de 1 fila para Distribuidor, otra de 2 filas
rowspan/colspan para Directo) — se **unificó en una sola estructura de 2
filas para ambos canales**, cambiando solo los textos: `($esDistribuidor ?
'Meta en Cajas' : 'Meta en Dólares')` en la celda combinada, y
`($esDistribuidor ? 'Cajas Estimadas a Ganar' : 'Estimado a Ganar')` en la
columna final (ya eran textos distintos entre canales antes de este
cambio, ahora conviven en la misma estructura de HTML). Mismo patrón de
ancho ya documentado en la Ronda 3 (celda combinada sin `width` propio, la
fila de abajo reparte). **Probado**: mismo método (`generar_acta_html()` en
navegador real + PDF real de Dompdf generado sin excepciones) para los 3
casos — Distribuidor con visibilidad, Distribuidor sin visibilidad, y
Directo (para confirmar que no cambió nada ahí) — "Meta en Cajas" cae
exacto sobre ENE/FEB/MAR/Total Período en los 2 formatos de Distribuidor,
Directo quedó pixel-igual a la Ronda 3.

**Ronda 6, mismo día — textos puntuales, solo Distribuidor**: 2 ajustes de
redacción sobre lo de la Ronda 5, pedidos tras ver una captura real ya
funcionando ("me parece perfecto, solo otro cambio..."):
- "Cajas Estimadas a Ganar" → **"Valor Estimado a Ganar"** (columna final de
  la tabla de Meta de Compras, `includes/acta_pdf.php` línea del
  `<th rowspan="2">`). Directo se queda con "Estimado a Ganar", sin tocar.
- "Pago Total" → **"Pago Total Cajas"** en las 3 tablas de Visibilidad
  (2.a Cabeceras y 2.b Rumas, ambas dentro de `tabla_marca_html()`,
  condicionado a `$fmt === 'numero'`; y 2.b Perchas, condicionado
  directamente a `$esDistribuidor`). Directo se queda con "Pago Total" a
  secas en las 3, sin tocar.
- **No tocado a propósito** (fuera de lo pedido): la fila `Pago x Mes x
  Percha ($)` de la tabla de Perchas sigue con el signo "$" hardcodeado
  incluso en Distribuidor — el usuario solo mencionó "Pago Total", no ese
  otro encabezado. Si al revisar el PDF real nota que ese "($)" también
  debería decir "(Cajas)" en Distribuidor, es el mismo patrón de arriba
  (condicionar a `$esDistribuidor`), pendiente de que lo pida.
- **Probado**: mismo método (`generar_acta_html()` en navegador con líneas
  reales de las 4 tablas — antes las pruebas de Meta de Compras no traían
  datos en Cabeceras/Rumas/Perchas, esta vez sí, para ver los encabezados
  con datos reales debajo) + PDF real de Dompdf sin excepciones, para
  Distribuidor y Directo.

**Ronda 7, mismo día — mismos 2 renombres de la Ronda 6, ahora en el
formulario interactivo de Registrar (no solo el PDF)**: pedido explícito
("cambialo en sus propios formularios, no metas esa de la celda nueva,
solo cambiale los nombres" + "tanto para directa como distribuidor").
`renderTableHeaders()` en `assets/js/registrar.js` arma los encabezados de
las 4 tablas editables (Meta de Compras/Cabeceras/Rumas/Perchas) con texto
**fijo, sin condicionar por canal** (a diferencia del PDF, que sí usa
`$fmt`/`$esDistribuidor`) — por eso, a pedido explícito, el rename aplica
igual para Directo y Distribuidor, sin agregar ninguna rama nueva por
canal ni la celda combinada rowspan/colspan de la Ronda 3/5 (eso es
exclusivo del documento impreso, el formulario se queda con su estructura
de siempre):
- "Valor Estimado" → **"Valor Estimado a Ganar"** (tabla de Meta de Compras).
- "Pago Total" → **"Pago Total Cajas"** en las 3 tablas de Visibilidad
  (Cabeceras, Rumas, Perchas) — las 3 ocurrencias.
- **Corrección sobre el "incidente" documentado originalmente acá**: en su
  momento se atribuyó a una colisión de edición concurrente entre sesiones
  (el cambio "desapareció" del archivo poco después de guardarse). El
  usuario aclaró después que **no fue eso** — lo borró él a propósito
  porque el resultado se veía mal (ver Ronda 8 más abajo). Queda la nota
  corregida para no repetir la lectura errónea: cuando un cambio
  "desaparece" en este proyecto, antes de asumir colisión de sesiones,
  preguntar/considerar que puede ser una reversión deliberada del usuario.
- **Probado**: servidor local (`php -S localhost:8899`) + sesión falsa vía
  `_dev_login_temp.php` (creado y borrado en la misma verificación, sin
  tocar la base) con 2 supervisores reales de solo-lectura (`JAVIER
  MALDONADO` → canal directo, `SIXTO TRAVEZ` → canal distribuidor, vía
  `repositorio_locales_supervisores_cliente`) — se leyó el `innerHTML` de
  los 4 `<thead>` en el navegador real para los 2 canales, confirmando los
  4 encabezados nuevos en ambos.

**Ronda 8, mismo día — el rename de la Ronda 7 ensanchaba las columnas,
arreglado con `<br>` en vez de texto largo en 1 línea**: el usuario borró
el cambio de la Ronda 7 porque se veía mal, tanto en Directo como en
Distribuidor — causa real: `.ac-table-acuerdo` usa `table-layout:auto` +
`thead th { white-space:nowrap }` (`assets/css/style.css`), y con
`table-layout:auto` el ancho de columna se calcula a partir del contenido
más ancho de esa columna — "Valor Estimado a Ganar"/"Pago Total Cajas" en
una sola línea son notablemente más largos que "Valor Estimado"/"Pago
Total" de antes, así que esas columnas (y con ellas la tabla entera)
crecían para no cortar el texto.

**Fix**: mismo texto, pero partido con un `<br>` literal en el punto donde
ya se cortaba antes de agregar la palabra nueva — "Valor Estimado<br>a
Ganar", "Pago Total<br>Cajas" — en las 4 tablas de `renderTableHeaders()`
(`assets/js/registrar.js`). Un `<br>` fuerza el salto de línea SIN pelear
con `white-space:nowrap` (nowrap solo suprime el wrap automático por
espacios de más ancho que la columna, no bloquea un salto de línea
explícito) — la columna termina midiendo el ancho de la línea más larga de
las 2 ("Valor Estimado" ~14 caracteres, no la frase completa de 23), muy
parecido al ancho que tenía antes del rename. Se agregó la clase
`.ac-th-2l` (`assets/css/style.css`, `line-height:1.25` sobre
`.ac-table-acuerdo thead th.ac-th-2l`) solo para que las 2 líneas no
queden pegadas — nada más se tocó del resto del CSS de la tabla (no se
sacó `nowrap` ni se cambió `table-layout`, a propósito: esas reglas siguen
siendo correctas para el resto de encabezados de 1 línea).
**Probado**: mismo método que la Ronda 7 (servidor local + sesión falsa +
Playwright) pero esta vez midiendo el ancho real en px de cada `<th>`
(`getBoundingClientRect().width`) antes/después — "Valor Estimado a
Ganar" bajó de necesitar ~230px en 1 línea a ~138px en 2 líneas, "Pago
Total Cajas" a ~107px — y capturando screenshot de las 4 tablas para
confirmar visualmente que el texto entra en 2 líneas legibles, sin
recortarse, para los 2 canales.

**Nota de infraestructura para la próxima sesión**: estos 8 fixes están
SOLO en el filesystem local — no hay forma de que Claude los despliegue al
entorno de desarrollo de Azure (ver sección de arriba). Probados con mirror
local (`php -S localhost:8899` + sesión falsa vía script temporal que se
borra después, sin tocar la base) y con datos sintéticos para el PDF —
nunca contra el entorno real. Si el usuario ya desplegó y algo se ve
distinto a lo descrito acá, confiar en lo que él reporte, no en esta nota.

## 2 ajustes visuales más en Registrar (2026-08-24, misma vuelta)

- **Bandera de Canal (`#ac-canal-badge`) agrandada** a pedido explícito —
  `font-size:13px; padding:6px 16px` puntual por `#id`, sin tocar el
  `.ac-badge` compartido (afecta también a roles en Gestión de Usuarios,
  badges de Firma en Historial, etc. — esos quedan en su tamaño de
  siempre).
- **"Meses Incluidos" ya NO se trunca con "…"** — el ajuste de anchos por
  `flex-basis` de la ronda anterior (ver arriba, "Ronda 2 — repartir el
  ancho de los filtros") se había probado solo con Playwright a
  1280/1366/1440px, sin sidebar real ocupando espacio — en uso real
  (sidebar expandido) el campo quedaba más angosto de lo probado y sí
  truncaba. En vez de seguir afinando px a mano (ya falló una vez),
  `#ac-months-display` ahora tiene su propio override (por `#id`, le gana
  al `white-space:nowrap` compartido con `.ac-input-readonly`) que permite
  pasar a una 2da línea en vez de cortar — nunca se trunca sin importar el
  ancho real disponible. `.ac-field-meses` también subió un poco de peso
  (`1.3`→`1.5`, base `200px`→`210px`) para que necesite envolver menos
  seguido.

## Rebate % y Participación bloqueados a mano, sin lógica todavía (2026-08-24)

Primer paso de lo que se discutió en la reunión JW 2026-08-24
(`datos/24-08-2026 10.16.txt`, ver "Reunión JW 2026-08-24" en la memoria del
proyecto): JW va a subir un repositorio que autocompleta y bloquea Rebate %
y Participación en el Acta — **todavía no existe ese repositorio ni la
lógica de autorrelleno**, pero el usuario pidió dejar los campos
bloqueados desde ya ("próximamente habrá una lógica ahí, pero por ahora
hazlo así"):
- `.ac-rebate-input` (Rebate % de Meta de Compras, `addPurchaseRow()`) y
  `.v-participacion` (Participación de Perchas, `addPerchaRow()`) en
  `assets/js/registrar.js` ganaron el atributo `readonly` — mantienen su
  valor por defecto de siempre (0 y "50%" respectivamente), pero ya no se
  pueden tipear.
- CSS puntual nuevo en `style.css` (`.ac-rebate-input:read-only,
  .v-participacion:read-only`) — fondo apagado + `cursor:not-allowed`, para
  que se vea bloqueado a simple vista (sin esto se verían como campos
  editables normales, ya que no comparten la clase `.ac-combo-input` que sí
  tenía su propio estilo de solo-lectura). **Ajustado (mismo día, pedido
  explícito "que se note más")**: gris marcado `#d7d5dc` (no la lavanda
  sutil `--color-surface-container-high` de la primera versión) + borde
  `#b8b5c2`, con el número en `#1a1b22` bold encima para que siga bien
  legible — incluye `-webkit-text-fill-color` porque Safari/iOS puede
  mostrar el texto de un `input:read-only` más pálido que su `color:` real
  sin ese fix.
- **No se tocó nada de lógica de negocio** — el valor sigue siendo el
  default de siempre, no hay autorrelleno real todavía. Cuando llegue el
  repositorio de JW, esto es lo que hay que reemplazar: sacar/mantener el
  `readonly` según corresponda y cargar el valor real ahí en vez del
  default fijo.

**⚠️ Superado para Rebate % (2026-08-27) — ver sección "Rebate % conectado
al repositorio" más abajo.** Participación de Perchas sigue exactamente
como se describe arriba (fijo en 0, readonly siempre) — `repositorio_participacion_percha`
todavía no existe en producción.

## Rebate % conectado al repositorio (2026-08-27)

Objetivo final de la reunión JW 2026-08-18 implementado para Meta de
Compras: `rebate_pct` ya no es siempre un campo fijo en 0 — se busca en vivo
contra `repositorio_rebate_producto` (ya creada en producción, ver "Módulo
Repositorios") apenas la fila completa Segmento+Sector+Categoría+Marca.

- **Nuevo getter `getters/acuerdo_buscar_rebate.php`** (solo lectura,
  roles `desarrollador`/`superdesarrollador`) — recibe
  `segmento`/`sector`/`categoria`/`marca` por GET, busca match exacto
  (`UPPER(TRIM(...))` en los 2 lados — el repositorio se normaliza a
  mayúsculas al guardar, pero `repositorio_productos` no necesariamente
  coincide byte a byte) con `eliminado_en IS NULL`. Responde
  `{ok:true, encontrado:true, rebate_pct}` o `{ok:true, encontrado:false}`
  — nunca un error 500 aunque la tabla no exista todavía en algún entorno
  (mismo fallback defensivo que el resto del proyecto: `prepare()` falla en
  silencio, responde "sin match").
- **`assets/js/registrar.js`, `bindCascadaComboConSector()`**: al completar
  Marca (el último nivel de la cascada de Meta de Compras) se llama
  `buscarYAplicarRebate(tr, seg, sector, cat, marca)`:
  - **Hay match** → bloquea el input (`readOnly=true`) con el % real del
    catálogo, `title` explicando por qué.
  - **No hay match** (combinación todavía no cargada en el repositorio) →
    deja el campo **editable**, con un `title` que lo explica — decisión de
    diseño explícita: nunca bloquear el flujo de Registrar por falta de
    datos en un repositorio que se sigue poblando de a poco. Mismo criterio
    ya usado en "Actas precargadas" (Marca pre-llenada pero editable cuando
    la inferencia no es segura).
  - Cualquier cambio en Segmento/Sector/Categoría (por encima de Marca)
    invalida el % mostrado — `resetearRebate()` lo vuelve a 0/editable hasta
    que se complete la fila de nuevo, para no dejar un % de OTRA
    combinación visible por error.
  - **Restaurar un borrador o una Acta precargada (`sugerir()`) NO dispara
    esta búsqueda** — un `rebate_pct` ya guardado en una línea es un dato
    histórico congelado (mismo principio ya aplicado en el export de
    Historial: "si el rebate cambia después, NO debe alterar
    retroactivamente Actas que ya se generaron con el valor viejo"). Se
    implementó pasando un 3er parámetro `silencioso` a
    `aplicarSeg`/`aplicarSector`/`aplicarCat`/`aplicarMarca` (el 2do
    parámetro ya estaba ocupado por el `label` que manda `comboSeleccionar()`
    en la interacción real, no se podía reusar) — `sugerir()` lo pasa en
    `true`, la selección real de un combo lo deja `undefined` (falsy).
  - Guard contra carrera: si el usuario re-elige Marca mientras la consulta
    anterior sigue en vuelo, la respuesta vieja se descarta (compara
    `marca-select.value` actual contra la Marca consultada antes de aplicar).
- **No se tocó** `getters/guardar_acuerdo.php` — sigue leyendo el valor del
  input tal cual, bloqueado o no, mismo comportamiento de siempre.
- **Probado**: `node --check`/`php -l` limpios. `acuerdo_buscar_rebate.php`
  corrido directo (sesión simulada, solo lectura) contra la base real —
  `prepare()`/`execute()` confirmados OK contra la tabla real recién creada;
  como `repositorio_rebate_producto` está vacía todavía (0 filas, recién
  creada), solo se pudo confirmar el camino "sin match" (campo queda
  editable) — falta que el usuario cargue al menos un producto real en
  Repositorios > Rebate y pruebe en el navegador que ese caso sí bloquea el
  campo con el % correcto.
- Afecta solo a Meta de Compras y Perchas — Cabeceras/Rumas no tienen
  campos de este tipo, no se tocaron.

## Bug real: spinners readonly dejaban clientes inalcanzables (2026-08-24)

Encontrado por el usuario en producción real (Javier Maldonado no
encontraba "DISTRIBUIDORA SUPERALIANZA S.A.S" en el spinner de
Distribuidor/Local): el bloqueo total (`readonly`) que se puso el
2026-08-20 en todos los combos de texto (Distribuidor/Local, Empresa,
Segmento/Sector/Categoría/Marca) tenía un efecto colateral serio, no
previsto en su momento — `comboRender()` (`assets/js/registrar.js`) corta
la lista en **60 opciones** (`.slice(0, 60)`, ordenadas alfabéticamente), y
sin poder tipear para filtrar, cualquier opción más allá del puesto 60
alfabético quedaba **inalcanzable de verdad**, sin ningún camino en la UI
para llegar ahí. Confirmado contra datos reales (solo lectura): Javier
Maldonado tiene 368 clientes activos, y "SUPERALIANZA" está en el puesto
alfabético #94 — muy por fuera del límite.

**Corregido restaurando la búsqueda por texto, sin reabrir el problema
original** (el usuario había pedido explícitamente que nunca quedara un
valor tipeado sin elegir de verdad de la lista — eso se mantiene):
- `inicializarCombo()` ya no depende de `readonly` para evitar texto
  "fantasma" — ahora escucha `input` para filtrar la lista en vivo
  (`comboRender(input.value)`), y en `blur` compara el texto contra la
  opción REAL actualmente seleccionada (`hidden.value` → su `label`); si no
  coincide exacto, limpia el campo (input y hidden) solo. Mismo resultado
  de fondo que el `readonly` (nunca queda un valor sin elegir de verdad),
  sin el efecto colateral de hacer inalcanzable todo lo que no entra en los
  primeros 60.
- Se sacó el atributo `readonly` de los 6 combos afectados: `#ac-empresa-search`/
  `#ac-distribuidor-search` (`components/registrar/registrar.php`) y los 4
  de `comboCellHtml()` (Segmento/Sector/Categoría/Marca, usados en las 4
  tablas del Acta).
- `input.select()` al enfocar (restaurado) — así escribir de una vez
  reemplaza el valor anterior, sin tener que borrarlo a mano primero.
- La validación de "filas fantasma" (`describirCampoCombo()`/toast de
  "campo sin confirmar") queda como **segunda capa** de seguridad, ya no
  como la única — por si algún flujo llega a guardar sin pasar por el blur.
- **No se subió el límite de 60 ni se sacó** — con supervisores de cientos
  de clientes, ningún límite razonable resuelve esto solo; la búsqueda por
  texto es la solución real (el usuario lo eligió explícitamente entre 2
  opciones antes de implementar esto).
- Probado: sintaxis limpia (`node --check`/`php -l`), sin `readonly`
  restante en ningún `.ac-combo-input` (`grep` confirmado), lógica de
  `normalizarBusqueda()` (sin acentos, minúsculas, sin espacios) ya
  soportaba filtrar por substring — solo hacía falta reconectar el
  listener de `input` que el bloqueo total había sacado.

## Stack y entorno

- Base de datos: MySQL/MariaDB, administrada vía HeidiSQL, alojada en hosting
  compartido de la agencia (usuario de conexión: `dgarces`, base tipo
  `luckyec_jaboneria_wilson` / similar).
- **El usuario de base de datos NO tiene el privilegio `REFERENCES`** — no se
  pueden crear `FOREIGN KEY` en ninguna tabla, ni siquiera entre tablas propias
  del proyecto. Toda la integridad referencial (que un `acuerdo_id` exista, que
  un `pos_id` sea real, etc.) se debe validar **en el código del backend antes
  de cada INSERT/UPDATE**, nunca asumir que la base la va a rechazar.
- Hubo un mockup visual de referencia ("Acuerdo Pdv" en Tailwind + vanilla JS,
  llamado `code.html`/`DESIGN.md` en conversaciones previas) — **no existe como
  archivo en este repositorio**, fue solo material de referencia visual, y sus
  Segmento/Categoría/Marca (`Cuidado del Hogar`, `Lavavajillas`, etc.) eran
  **datos de ejemplo inventados, no datos reales**. La implementación real
  (`components/registrar/registrar.php`) nunca usó esos valores — siempre
  consulta `repositorio_productos` en vivo (ver sección de spinners más abajo).
  Si algún dropdown muestra valores "raros" tipo `SPAGHETTI #5` o `CABELLO DE
  ANGEL` como Segmento, es la data real de `repositorio_productos`, no un
  resabio del mockup.

## Tablas ya creadas en la base (prefijo `repositorio_` obligatorio en todo nombre nuevo)

### `repositorio_acuerdos`
Cabecera del Acta. Un registro por PDV/periodo.

| Columna | Tipo | Nota |
|---|---|---|
| `id` | INT AI PK | |
| `documento_no` | VARCHAR(30) UNIQUE | Ej: "ADN-2026-0427" |
| `pos_id` | VARCHAR(200) | El "Distribuidor" del formulario. Debe existir en `repositorio_localesddt2.pos_id` — **validar en código**, no hay FK |
| `anio` | SMALLINT | |
| `mes_inicio`, `mes_fin` | TINYINT | 0=Ene...11=Dic. Deben ser consecutivos (`mes_fin >= mes_inicio`, hay CHECK) |
| `fecha_generacion` | DATE | `NOW()` al presionar "Generar Acta", no lo tipea el usuario |
| `estado` | ENUM | `borrador→generado→enviado→firmado→liquidado→anulado` |
| `sin_visibilidad` | TINYINT(1) NOT NULL DEFAULT 0 | Agregado 2026-08-24, **pendiente que el usuario corra el `ALTER TABLE`** (ver SQL y detalle completo en "Formato de Acta 'sin visibilidad'..." más abajo). Refleja el switch "Visibilidad y Espacios" de Registrar — 1 = el usuario lo desactivó, el Acta sale sin las tablas 2.a/2.b (Cabeceras/Rumas&Perchas, numeración 2026-08-24, antes 3.a/3.b). Aplica IGUAL a Directo y Distribuidor (ver "Distribuidor con/sin visibilidad" más abajo — desde esa sesión ya NO está atado a `es_distribuidor`). |
| `creado_por` | INT UNSIGNED NULL | Agregado 2026-08-14. FK lógica a `repositorio_usuarios_acuerdos.id`, se llena UNA sola vez al INSERT (`getters/guardar_acuerdo.php`), nunca se pisa en el UPDATE. Es la base de "Historial de Acuerdos solo muestra lo que ese usuario creó" (`listar_historial_acuerdos()` en `includes/functions.php` filtra por `a.creado_por = ?`, ya no por supervisor/territorio). Los 34 acuerdos creados antes de esta columna quedaron con `creado_por = NULL` y por lo tanto invisibles en Historial para todos — mismo problema ya documentado como "huérfanos por pos_id viejo" más abajo (solo el `id=39` se pudo rescatar con un backfill puntual porque su `pos_id` sí calzaba con el maestro nuevo). |
| `pdf_documento` | LONGBLOB | El PDF generado se guarda DIRECTO en la base (decisión del cliente, ya evaluamos BLOB vs archivo externo, eligieron BLOB) |
| `pdf_generado_en`, `pdf_tamano_bytes` | | |
| `created_at`, `updated_at` | DATETIME | Automáticos, MySQL los llena solo |

**Firma: SIEMPRE física, nunca digital.** La firma del cliente, el nombre del
ejecutivo comercial y del jefe comercial se imprimen como líneas en blanco
(`Nombre: ________`) en el PDF, y se firman a mano sobre el papel impreso. **No
existe ningún campo en la base para capturar imagen de firma, trazo, ni texto de
firma digital** — el sistema no participa en ese paso, solo genera el documento
para imprimir. El campo `estado='firmado'` se actualiza manualmente después,
como confirmación de que el papel firmado ya volvió, no porque el sistema haya
capturado una firma.

### `repositorio_acuerdo_lineas`
Las 4 tablas del Acta unificadas en una sola tabla, diferenciadas por `tipo`.

| Columna | Tipo | Aplica a | Nota |
|---|---|---|---|
| `id` | INT AI PK | todos | |
| `acuerdo_id` | INT | todos | FK lógica a `repositorio_acuerdos.id` (sin constraint) |
| `tipo` | ENUM | todos | `meta_compra` / `cabecera` / `ruma` / `percha` |
| `segmento` | VARCHAR(200) | meta_compra, cabecera, ruma | De `repositorio_productos.segmento` |
| `sector` | VARCHAR(200) NULL | **solo meta_compra** | Agregado 2026-08-18. De `repositorio_productos.sector` (BARRA/CREMA/LIQUIDO/POLVO...). Antes era solo UI (se descartaba antes de mandar al backend) — se confirmó comparando contra el Excel real de JW que **Trade MKT aprueba y rastrea el rebate % justo a este nivel**, no al de `categoria` (que es más amplio: DETERGENTE/LAVAVAJILLAS/...). Actas creadas antes de este cambio quedan con `sector = NULL` — `registrar.js` sigue infiriéndolo al reabrir esas Actas viejas (mismo mecanismo de siempre, `inferirSectorDesde()`), pero ya no hace falta para Actas nuevas. |
| `categoria` | VARCHAR(200) | meta_compra, cabecera, ruma | De `repositorio_productos.categoria` |
| `marca` | VARCHAR(200) | todos | De `repositorio_productos.marca` |
| `rebate_pct` | DECIMAL(6,4) | solo meta_compra | Debe salir de una columna de rebate que se va a **agregar a `repositorio_productos`** (UPDATE pendiente del cliente, no crear catálogo propio) |
| `cantidad_max_percha` | SMALLINT | solo percha | Validación en app: máximo 5 |
| `precio_percha` | DECIMAL(10,2) | solo percha | Default $40.00, informativo |
| `valores_mensuales` | JSON | meta_compra, cabecera, percha | `{"3":700.00,"4":700.00,"5":700.00}` — valor tipeado mes por mes |
| `valor_mensual_unico` | DECIMAL(10,2) | **solo ruma** | Un valor tipeado UNA vez (mini tabla "Valor Ruma x Marca x Mes") que se repite en TODOS los meses del periodo. Siempre fue **por línea/fila**, nunca por Marca compartida a nivel de base — la UI sí lo mostraba/editaba agrupado por Marca hasta el 2026-08-20 (ver "Registrar Acuerdo PDV" más abajo), pero eso era solo de pantalla. |
| `orden` | SMALLINT | todos | Orden de filas agregadas por el usuario |

**Patrón de captura por tipo — MUY IMPORTANTE, no confundir:**

| Tipo | Segmento | Rebate | Captura mensual |
|---|---|---|---|
| `meta_compra` | Sí | Sí | Mes por mes (`valores_mensuales`) |
| `cabecera` | Sí | No | Mes por mes (`valores_mensuales`) |
| `ruma` | Sí | No | **Un valor que se repite** (`valor_mensual_unico`) |
| `percha` | No | No | Mes por mes (`valores_mensuales`) |

**Regla de oro: NUNCA se guardan totales calculados.** Ni "Total Período", ni
"Valor Estimado", ni "Pago Total", ni la fila de TOTALES del pie de tabla. Todo
se calcula al vuelo desde `valores_mensuales` / `valor_mensual_unico` en cada
consulta o al generar el PDF. Razón: evitar que un total guardado quede
desactualizado si se edita un valor mensual.

- `Valor Estimado` (meta_compra) = `SUM(valores_mensuales) * rebate_pct`
- `Pago Total` (cabecera/percha) = `SUM(valores_mensuales)`
- `Pago Total` (ruma) = `valor_mensual_unico * cantidad_de_meses_del_periodo`

### `repositorio_usuarios_acuerdos`
Login y roles de la plataforma. Los usuarios se crean directo en HeidiSQL, no
hay pantalla pública de registro (excepto lo que cree el rol `superdesarrollador`
desde su módulo).

| Columna | Tipo | Nota |
|---|---|---|
| `id` | INT AI PK | |
| `usuario` | VARCHAR(100) UNIQUE | |
| `contrasena` | VARCHAR(100) | **Texto plano, sin hash** — decisión explícita del cliente porque hoy los crean manualmente desde Heidi. ⚠️ Si en algún momento esto se conecta a un login web público, avisar para migrar a hash (bcrypt/argon2) antes de exponerlo. |
| `rol` | ENUM | `admin` / `desarrollador` / `superdesarrollador` en la base (la columna sigue permitiendo `admin` a nivel de MySQL), pero **desde 2026-08-17 la aplicación ya no ofrece ni reconoce `admin`** — no está en ningún dropdown de Gestión de Usuarios, ni en la validación de `crear_usuario.php`/`actualizar_usuario.php`, ni en ningún `rolPermitido([...])` de `secciones.php` ni de los getters. Solo quedan `desarrollador` y `superdesarrollador`. Si algún registro viejo sigue con `rol='admin'` en la base, esa cuenta no ve ningún módulo hasta que se le cambie el rol manualmente. |
| `supervisor` | VARCHAR(100) NULL | Agregado 2026-07-26. Vincula el login con un valor real de `repositorio_locales_supervisores_cliente.supervisor` — de ahí se deriva el canal (ver sección "Canal Directo vs Distribuidor" más abajo). Nullable: no todos los roles necesitan uno (ej. cuentas puramente administrativas). **1 supervisor = 1 cuenta** (confirmado 2026-07-27): un mismo supervisor no puede quedar asignado a dos logins activos a la vez — `supervisores_asignados_activos()` (`includes/functions.php`) arma el mapa supervisor→usuario y lo aplican `crear_usuario.php`/`actualizar_usuario.php` (rechazan el duplicado) y `gestion-usuarios.php` (el combo "Nuevo Usuario" ya no lista los supervisores tomados; el de "Editar Perfil" los muestra deshabilitados salvo el del propio usuario que se edita). Desactivar una cuenta (`status='inactivo'`) libera su supervisor para reasignar. Al elegir un supervisor en "Nuevo Usuario" se autocompleta el campo Nombre de Usuario con ese mismo valor (editable). |
| `status` | ENUM | `activo` / `inactivo` — así se maneja el "borrado", nunca DELETE físico |
| `created_at`, `updated_at` | DATETIME | Automáticos |

## Maestros externos de Alicorp (NO se duplican, se consultan directo)

### `repositorio_locales_dtt2` — DEPRECADA, ya no se usa (2026-07-26)
Maestro viejo de PDV/local, usado por el formulario de Registrar Acuerdo PDV
hasta el 2026-07-26. El cliente indicó que esta tabla "no sirve del todo":
solo incluye clientes visitados por mercarista y no tiene segmentación de
distribuidor. **Reemplazada por completo por `repositorio_locales_supervisores_cliente`**
(ver abajo) en `getters/acuerdo_distribuidores.php`, `getters/guardar_acuerdo.php`
y los JOIN de Historial (`includes/functions.php`). Se deja esta entrada solo
como referencia histórica — no usar en código nuevo. `pos_id` de esta tabla
tenía formato `ALI0xxxx`, **incompatible** con el formato `EPV.../EPVD...` de
la tabla nueva (no había datos reales todavía cuando se migró, así que no
hizo falta compatibilizar ambos formatos).

### `repositorio_locales_supervisores_cliente`
Maestro real de clientes/PDV de Alicorp, **reemplaza a `repositorio_locales_dtt2`**
como fuente para Registrar Acuerdo PDV (todo el formulario, no solo un
dropdown) desde 2026-07-26 — confirmado por el cliente en llamada: incluye
clientes CON y SIN mercarista (dtt2 solo tenía los primeros), ~41,640 filas
totales (`recursos/repositorio_locales_supervisores_cliente.xlsx` tiene el
export completo usado para investigar esto).

Columnas relevantes:
- `canal` — ENUM-like (texto): `DISTRIBUIDOR` / `COBERTURA` / `MAYORISTA` /
  `AUTOSERVICIO`. **`COBERTURA` = "Canal Directo"** (así le dice el cliente,
  aunque en la base diga "cobertura" — confirmado dos veces en llamada).
- `supervisor` — el nombre de la persona real que va a usar la plataforma
  (confirmado explícitamente por el cliente: "esta columna son los que van a
  usar"). Es el campo que se guarda en `repositorio_usuarios_acuerdos.supervisor`
  al crear un usuario. ~33 valores distintos hoy. Ningún supervisor mezcla
  canal DISTRIBUIDOR con COBERTURA/MAYORISTA (verificado con los datos
  reales) — sí hay supervisores de COBERTURA que también tienen algunas
  filas MAYORISTA.
- `tipo_distribuidor` — nombre de la empresa distribuidora (ej. "ASERTIA
  COMERCIAL SA"). Solo tiene valor cuando `canal = 'DISTRIBUIDOR'` (siempre
  null en los demás canales). Un supervisor de canal Distribuidor puede
  manejar varias empresas distintas (ej. Juan Cordovilla maneja 5) — por eso
  el formulario pide elegir la empresa antes que el cliente cuando el canal
  es Distribuidor (campo "Empresa Distribuidora", solo visible en ese caso).
- `pos_id` (formato `EPV12345`/`EPVD12345`, **no es UNIQUE** — hay ~1,116
  `pos_id` duplicados, hay que agrupar/limitar en las consultas), `pos_name`
  (= Razón Social / "Estimado(a)" del Acta), `cedi` (= Localidad del Acta —
  esta tabla NO tiene `province`/`city` como la vieja, solo `cedi`, que es
  más bien una zona/centro de distribución que una ciudad exacta), `asesor`
  (para canal Distribuidor = nombre de la empresa distribuidora, igual que
  `tipo_distribuidor`; para Cobertura/Mayorista = nombre de la persona
  vendedora), `codigo_asesor`, `tipo_cliente` (segmento de venta: A/B/C/D/AA/
  AAA/PLUS/MAYORISTA, no relacionado con canal).

**Canal Directo vs Distribuidor — cómo se deriva (nunca se guarda):**
El canal de un usuario de Acuerdos_Comerciales se calcula EN VIVO, nunca se
persiste, mirando qué `canal(es)` tienen los clientes de su `supervisor`:
```sql
SELECT DISTINCT canal FROM repositorio_locales_supervisores_cliente WHERE supervisor = ?
```
Si aparece `DISTRIBUIDOR` → canal Distribuidor. Si no, y aparece algo → canal
Directo. Implementado en `canalDeSupervisor()` (`includes/functions.php`),
consumido por `components/registrar/registrar.php` (badge de solo lectura
`#ac-canal-badge`, variable `CANAL_USUARIO` impresa para el JS) y por
`getters/acuerdo_distribuidores.php` (filtra los clientes que ve cada
usuario — nunca la base entera, cada quien ve solo su propia cartera).
Caso borde sin resolver: un supervisor exclusivamente MAYORISTA (no existe
hoy en la data) caería clasificado como "Directo" por este orden de checks.

### `repositorio_productos`
Maestro real de producto/marca, **compartido entre TODOS los proyectos/marcas
de la agencia** (no es exclusivo de Jabonería Wilson) — 1644 filas totales,
de las cuales solo 342 son `fabricante = 'JABONERIA WILSON'` (el resto es La
Fabril, Unilever, Colgate, Clorox, etc., usado para tracking de competencia en
otros módulos). **Regla obligatoria: todo spinner de Segmento/Categoría/Marca
de este formulario debe filtrar `WHERE fabricante = 'JABONERIA WILSON'`**, si
no se mezclan productos de la competencia en los dropdowns del Acta. Columnas
relevantes: `sku`, `categoria`, `marca`, `segmento`, `subcategoria`,
`fabricante`. **Al cliente le van a agregar una columna de rebate aquí**
(nombre exacto pendiente de confirmar) — ese es el origen real del
`rebate_pct`, no un catálogo propio nuestro. Mientras esa columna no exista,
`getters/guardar_acuerdo.php` acepta el rebate como un campo numérico editable
por fila en la tabla Meta de Compras (no autocompletado) — ver componente
`registrar.php`.

## Reglas de negocio confirmadas

1. **El "Distribuidor" del dropdown = 1 `pos_id` exacto** de
   `repositorio_locales_supervisores_cliente` (desde 2026-07-26; antes era
   `repositorio_locales_dtt2`, ver esa sección). Además, cada usuario solo ve
   los clientes de SU `supervisor` (nunca la base entera) — si el canal es
   Distribuidor, primero elige la empresa distribuidora, después el cliente.
2. **El acuerdo (meta/cuota) se llena en el formulario primero.** Después de
   firmado, se sube venta real y visibilidad real para comparar contra esa meta.
   *(Pendiente de confirmar con Mishell/Jorge si la cuota también se conecta a
   un archivo de BI — por ahora se trata como INDEPENDIENTE del formulario.)*
3. **Las 4 tablas del Acta (Meta de Compras, Cabeceras, Rumas, Perchas) SIEMPRE
   van en el PDF**, sin excepción — no hay Actas parciales.
4. **El periodo del acuerdo se maneja en trimestres fijos (2026-08-18, ya no
   rango libre de meses)**: Q1 (Ene-Mar), Q2 (Abr-Jun), Q3 (Jul-Sep), Q4
   (Oct-Dic) — un `<select>` simple (`#ac-periodo-select` en `registrar.php`,
   Q1 seleccionado por defecto) en vez del picker de calendario de antes (se
   eliminó todo ese código/CSS, ver `assets/js/registrar.js`:
   `TRIMESTRES`/`aplicarTrimestre()`). "Meses Incluidos" muestra los 3 meses
   con nombre completo separados por guion (ej. "ENERO-FEBRERO-MARZO"), no
   abreviado. La base (`mes_inicio`/`mes_fin`, `CHECK (mes_fin >= mes_inicio)`)
   no cambió — cualquier trimestre sigue siendo un rango de 3 meses
   consecutivos válido, la restricción a exactamente Q1-Q4 es solo de la UI/JS,
   no del schema.
5. **Las columnas de mes en cada tabla del formulario crecen/decrecen según el
   rango de meses elegido** — de 1 a 12 meses, sin alterar estructura de tabla
   (por eso `valores_mensuales` es JSON, no columnas ENE/FEB/MAR fijas).
6. **Todos los dropdowns de Segmento/Categoría/Marca son "spinners"** que
   consultan en vivo `repositorio_productos` (`SELECT DISTINCT ...`) — no hay
   catálogo propio, nunca hardcodear valores.
7. **Cascada de dropdowns:** al elegir Segmento aparecen las Categorías de ese
   segmento; al elegir Categoría aparecen las Marcas de esa categoría.
8. **`razon_social` y `localidad` nunca se guardan** — siempre se derivan de
   `repositorio_locales_supervisores_cliente.pos_name` / `cedi` vía el
   `pos_id` del acuerdo, en el momento de generar el PDF.
9. **La firma es 100% física, nunca digital.** El sistema solo imprime líneas
   en blanco; no captura imagen de firma ni texto de firma en ningún campo.

## Registrar Acuerdo PDV — implementado

Pantalla real construida en `components/registrar/registrar.php` +
`assets/js/registrar.js`, reemplazando el placeholder. Getters nuevos:
`getters/acuerdo_catalogo.php` (Segmento→Categoría→Marca de Wilson + marcas
para Perchas), `getters/acuerdo_distribuidores.php` (pos_id activos),
`getters/guardar_acuerdo.php` (crea/actualiza cabecera + reemplaza las líneas
en una transacción). Decisiones tomadas al implementar, que se alejan del
mockup `code.html` por choque con las reglas de este documento:

- **Regla: solo un Acta activa por Local+Período (2026-08-23)** — pedido
  explícito del usuario para que dos analistas no puedan generar la misma
  Acta al mismo tiempo. En `getters/guardar_acuerdo.php`, justo después de
  validar que el `pos_id` pertenece al supervisor de la sesión, si
  `$estado !== 'borrador'` se chequea si YA existe otro `repositorio_acuerdos`
  con el mismo `pos_id`+`anio`+`mes_inicio`+`mes_fin` y `estado NOT IN
  ('borrador', 'anulado')` (excluyendo el propio `id` si se está editando/
  regenerando el mismo acuerdo) — si existe, se corta con
  `responder(false, '{pos_name} ya tiene un Acta generada para este
  trimestre.', ['duplicado' => true])`. **Los borradores quedan exentos a
  propósito** — dos analistas pueden seguir armando cada uno su propio
  borrador para el mismo Local en paralelo, recién se bloquea cuando alguno
  de los dos intenta generarla de verdad ("el primero que llega, gana",
  pedido literal del usuario). Del lado del frontend
  (`assets/js/registrar.js`, `guardarAcuerdo()`), si `data.duplicado` es
  `true` se muestra un `Swal.fire()` (mismo componente que la confirmación
  de "Eliminar", pero sin `showCancelButton` — es solo informativo, un
  botón "Entendido") en vez del toast genérico de error. **Nota de
  honestidad sobre el alcance real de esta protección**: es un chequeo a
  nivel de aplicación (un `SELECT` antes del `INSERT`/`UPDATE`), no una
  restricción a nivel de base de datos (`UNIQUE INDEX`) — cubre bien el
  caso real que pidió el usuario (dos analistas que llegan en momentos
  distintos, uno después del otro), pero no es 100% airtight contra dos
  peticiones verdaderamente simultáneas al mismo milisegundo exacto (un
  `UNIQUE INDEX` filtrado por estado sería la única forma de cerrar eso del
  todo, y MySQL no soporta índices únicos parciales/condicionales
  directamente) — dado el volumen real de uso de este sistema (un puñado de
  analistas, no alta concurrencia), este nivel de protección es proporcional
  al riesgo real. **Probado (solo lectura) contra datos reales**: acuerdo
  real 41 (`EPVD15130`, Q1 2026, ya `firmado`) — pedir crear uno nuevo para
  el mismo Local+Q1 2026 detecta el duplicado correcto; editar ese MISMO
  acuerdo (pasando su propio `id`) no lo detecta como duplicado de sí
  mismo; el mismo Local en Q2 2026 tampoco detecta nada (período distinto).
- **Rumas**: el mockup (`code.html`) tiene un input por mes igual que
  Cabeceras/Perchas, lo cual contradice la regla de `valor_mensual_unico`
  (un solo valor que se repite). La implementación real usa **un único input
  "Valor x Mes" por fila** — el "Pago Total" se calcula como
  `valor_mensual_unico * cantidad_de_meses`, tal como pide este documento.
  La tabla lateral "Valor Ruma x Marca x Mes" del mockup es la ÚNICA fuente
  editable (los meses de la tabla grande son de solo lectura, se llenan
  desde ahí) — **ya NO es un rollup por marca** (esa fue la versión inicial;
  cambiado 2026-08-20, ver "Registrar Acuerdo PDV" más abajo, 1 fila de
  leyenda por línea de la tabla grande, valor independiente aunque compartan
  Marca).
- **Percha "% de Peso / Participación"**: existe en el mockup pero **no hay
  columna en `repositorio_acuerdo_lineas` para guardarlo** y la mecánica de
  spinners de este documento tampoco lo contempla. Se dejó como campo de
  UI solamente (referencial para el vendedor), no se envía al backend.
- **Sector (Meta de Compras)**: 4to campo, **solo en la tabla de Meta de
  Compras** (Cabeceras/Rumas/Perchas no lo tienen y no deben tocarse). Orden
  de la cascada (cambiado 2026-08-18, pedido explícito tras revisar un Acta
  real escaneada — ver `datos/WhatsApp Image 2026-07-23 at 11.09.16.jpeg`):
  **Segmento → Sector → Categoría → Marca**, porque el nombre impreso de cada
  fila en el Acta real es literalmente "Sector + Categoría + Marca" (ej.
  "Crema Lavavajillas LAVA", "Polvo detergente GOL") — elegir en ese orden es
  más intuitivo y sigue calzando limpio con los datos reales (para
  `CUIDADO DEL HOGAR` hay solo 5 Sectores, y cada Sector tiene entre 1 y 4
  Categorías). Antes el orden era Segmento→Categoría→Marca→Sector con Sector
  al final y autocompletado solo si había una única opción; ya no es así, es
  un paso obligatorio justo después de Segmento. Implementado en
  `getters/acuerdo_catalogo.php` (árbol nuevo `segmentos_sector`,
  Segmento→Sector→Categoría→[Marcas], separado del árbol `segmentos` que
  siguen usando Cabeceras/Rumas) y `assets/js/registrar.js`
  (`bindCascadaComboConSector`, distinta de `bindCascadaCombo` que usan
  Cabeceras/Rumas). **Corregido 2026-08-18: Sector SÍ se persiste ahora**
  (columna `sector` en `repositorio_acuerdo_lineas`, solo meta_compra) — se
  confirmó comparando contra el Excel real de JW que Trade MKT aprueba y
  rastrea el rebate justo a este nivel, así que dejarlo solo en pantalla
  perdía información real de negocio, no solo un dato cosmético. Actas
  creadas ANTES de este cambio quedan con `sector = NULL`; al restaurarlas
  como borrador, `registrar.js` sigue infiriéndolo con
  `inferirSectorDesde()` (mismo mecanismo de siempre, ahora solo como
  fallback de compatibilidad) — si una Marca vende en más de un Sector con
  la misma Categoría (ej. LAVA en Lavavajillas: Crema/Barra/Líquido), se
  toma el primero, limitación que solo aplica a esas Actas viejas.
  **Pendiente que el usuario corra en HeidiSQL** (Claude no puede, regla de
  solo lectura):
  ```sql
  ALTER TABLE repositorio_acuerdo_lineas ADD COLUMN sector VARCHAR(200) NULL AFTER segmento;
  ```
  Nada de lo de arriba funciona en producción hasta que exista la columna —
  `guardar_acuerdo.php` va a fallar el INSERT si se usa antes de correr esto.
  **Hallazgo aparte, sin tocar todavía**: revisando `includes/acta_pdf.php`
  para este cambio, el texto impreso de cada fila de Meta de Compras en el
  PDF usa `segmento+categoria+marca` (`tabla_marca_html`/`ancho_columna_categoria`),
  pero la nota de arriba sobre la Acta real escaneada dice que el formato
  real impreso es `Sector+Categoría+Marca` (ej. "Crema Lavavajillas LAVA").
  Si eso es así, el PDF hoy imprime el campo equivocado (Segmento en vez de
  Sector) — no se tocó porque es un cambio a lo que se imprime en el
  documento real, no algo que el usuario pidió en esta sesión. Confirmar con
  el usuario antes de tocarlo.
- **`documento_no`**: se genera como `ADN-{anio}-{secuencia de 4 dígitos}`,
  calculado como `COUNT(*)+1` de acuerdos de ese año, con reintento si choca
  con el `UNIQUE` (nadie definió el algoritmo exacto, esto es una decisión
  razonable pero no confirmada con el cliente).
- **"Ver y Generar Acta" (2026-08-17)**: al hacer click, YA NO guarda el
  acuerdo como `estado='generado'` de una — primero guarda como `'borrador'`
  en silencio (mismo estado que "Guardar Borrador"/"Mis Borradores", no
  aparece en Historial) solo para tener un `id` real con el que armar la
  previsualización del PDF real. Recién se promueve a `'generado'` (con el
  snapshot definitivo en `pdf_documento`) cuando el usuario hace click en
  "Descargar / Imprimir PDF" dentro del modal de preview. Si cierra el modal
  sin descargar, el acuerdo queda como borrador nomás — se le avisa con un
  toast de advertencia, pero no se pierde nada (sigue en "Mis Borradores").
- **Confirmaciones destructivas vs. mensajes informativos (2026-08-17)**:
  para acciones destructivas (ej. "Eliminar" en Historial) se usa un modal de
  **SweetAlert2** (CDN `sweetalert2@10`, ya cargado en `index.php`, mismo
  patrón que otros proyectos de la agencia como `mantenimiento_fotografico/Unilever`)
  — nunca `window.confirm()`. Para mensajes informativos/de validación (éxito,
  error, advertencia sin necesitar confirmación) se sigue usando el
  `mostrarToast()` de siempre (`assets/js/toast.js`) — no reemplazar esos por
  SweetAlert.
- **Eliminar (Historial)**: nunca es un `DELETE` físico — `getters/eliminar_acuerdo.php`
  marca `estado='anulado'` (mismo patrón que `repositorio_usuarios_acuerdos.status`).
  `listar_historial_acuerdos()` excluye `borrador` Y `anulado` del listado.
- **Modal "Mis Borradores" (Historial) — ancho arreglado (2026-08-19)**: se
  veía recortado porque `.ac-modal.ac-borradores-modal` tenía `max-width:640px`
  pero la tabla adentro exige `min-width:640px` (regla genérica `.ac-table`) —
  ni con el padding del modal entraba. Se sacó el `min-width:0` que forzaba
  la tabla angosta, se envolvió en `.ac-table-scroll` (mismo patrón que el
  resto de tablas de la app, scroll horizontal como red de seguridad) y el
  modal pasó a `max-width:820px`.
- **Eliminar borrador desde "Mis Borradores" (2026-08-19)**: botón de
  eliminar por fila (ícono, junto a "Continuar editando"), mismo endpoint
  `getters/eliminar_acuerdo.php` (soft-delete, `estado='anulado'`) y misma
  confirmación SweetAlert2 que ya usaba "Eliminar" en el listado principal de
  Historial — se factorizó a `confirmarYEliminarAcuerdo(id, documentoNo, onOk)`
  en `historial.js`, compartida entre los dos usos (`onOk` decide qué pasa
  después: recargar toda la lista, o sacar solo esa fila). La fila se saca
  con una animación corta (`animarYQuitarFila()`, clase CSS
  `.ac-fila-eliminando`: fade + slide + flash rojo, ~280ms) en vez de
  desaparecer de golpe; si era la última fila, vuelve el placeholder de
  "No tenés borradores guardados."
- **Verificado (2026-08-19) que "Continuar editando" restaura el borrador
  completo, sin nada a medias**: se revisó toda la cadena
  (`obtener_acuerdo_detalle()` → `getters/obtener_borrador.php` →
  `poblarTablasConLineas()`/`aplicarBorrador()` en `registrar.js`) y se
  confirmó con datos reales de la base que el Sector de Meta de Compras
  (agregado 2026-08-18) sí llega completo en el JSON y se aplica con el valor
  real guardado (`fila.sector || null`, con inferencia solo como fallback
  para acuerdos guardados antes de que existiera esa columna — se comprobó
  que el único borrador viejo con `sector=NULL` en la base no es ambiguo
  para la inferencia). Cabecera (Distribuidor/Empresa/Localidad/Periodo) y
  las 4 tablas completas restauran bien.
- **"Actualizado" de Mis Borradores no se refrescaba al editar líneas
  (2026-08-19)**: `repositorio_acuerdos.updated_at` tiene
  `ON UPDATE CURRENT_TIMESTAMP`, pero eso SOLO se dispara si alguna otra
  columna de esa fila cambia de valor — como editar un borrador normalmente
  solo toca `repositorio_acuerdo_lineas` (tabla aparte), la cabecera se
  reescribía con los mismos valores de siempre y MySQL nunca actualizaba la
  fecha. `guardar_acuerdo.php` ahora fuerza `updated_at = NOW()` explícito en
  el `UPDATE` de la cabecera, sin importar si algún otro campo cambió.
- **Eliminar es SIEMPRE lógico, en los dos lugares (confirmado 2026-08-19)**:
  tanto el "Eliminar" del listado de Historial como el de "Mis Borradores"
  pasan por el mismo `getters/eliminar_acuerdo.php` — un `UPDATE ... SET
  estado='anulado'`, nunca un `DELETE`. No hay ningún `DELETE FROM
  repositorio_acuerdos` en todo el proyecto para acuerdos ya creados.
- **Generación real del PDF**: con **Dompdf** (`composer.json`/`vendor/`).
  `includes/acta_pdf.php` (`generar_acta_html()`) arma el HTML y lo renderiza
  a PDF real vía `getters/generar_acta_pdf.php`. Prueba primero a escala 1.0
  y si Dompdf reporta más de 1 página va reduciendo fuentes/espaciados hasta
  que entra en una sola hoja A4. El PDF SÍ se persiste en `pdf_documento`
  (LONGBLOB) — el snapshot se genera y guarda en `guardar_acuerdo.php` en el
  momento en que `estado` pasa a `'generado'` (no en cada borrador);
  `generar_acta_pdf.php` sirve ese snapshot si existe, o renderiza en vivo
  como fallback (acuerdos viejos sin snapshot) y de paso lo deja guardado.
- **Flujo "Previsualización" / "Generar PDF" (rediseñado 2026-08-18, esta nota
  reemplaza la versión anterior "Ver y Generar Acta"):**
  - Botón `#ac-generar-acta` ahora dice **"Previsualización"** (antes "Ver y
    Generar Acta"/"Generar Acta"). Al hacer click, **NO guarda nada en la
    base** — versión anterior guardaba un borrador en silencio, se sacó a
    pedido explícito del usuario. En vez de eso llama a
    `getters/previsualizar_acta_pdf.php` (POST con el JSON de
    `recolectarLineas()` + cabecera, igual que `guardar_acuerdo.php` pero sin
    tocar la base — ni siquiera hace `require` de `db_connect.php`, arma el
    `$detalle` directo del POST y llama al mismo `generar_acta_pdf_binario()`
    de siempre) y muestra el PDF resultante como `blob:` URL en el iframe.
    Igual corre las mismas validaciones de `validarCabecera()` (spinners sin
    confirmar, participación) pero SIN exigir ninguna línea real — previsualizar
    algo incompleto es válido.
  - Dentro del modal hay controles de **zoom** (`#ac-acta-zoom-in/out`, 25% por
    click, 25%-300%) que recargan el iframe con `#zoom=N` en la URL — funciona
    igual sobre una `blob:` URL que sobre una del servidor.
  - Dos botones separados en vez de uno: **"Generar PDF"**
    (`#ac-acta-generar-pdf`, siempre habilitado) y **"Descargar PDF"**
    (`#ac-acta-descargar-pdf`, arranca deshabilitado vía clase `.ac-btn-disabled`
    — `pointer-events:none`, sin `href`). Recién al hacer click en "Generar
    PDF" se llama a `guardar_acuerdo.php` con `estado='generado'` (creando el
    acuerdo directo, ya que nunca existió un borrador previo — `acuerdoId` es
    `null` en este punto) y, si sale bien, se habilita "Descargar PDF"
    apuntando al PDF real ya persistido (`generar_acta_pdf.php?id=...`). Ahí sí
    corre la validación de "no acuerdo vacío" (ver Validaciones más abajo),
    porque acá `estado !== 'borrador'`.
  - "Descargar PDF" usa el atributo `download` (no `target="_blank"`) —
    descarga el archivo directo, no abre otra pestaña con el visor del
    navegador. Pedido explícito del usuario.
  - Al generar con éxito, el formulario completo se limpia
    (`limpiarFormularioParaNuevoAcuerdo()`: Distribuidor/Empresa/Localidad,
    periodo vuelve al default Ene-Mar, las 4 tablas vuelven a una fila vacía)
    — el usuario puede estar registrando ~20 Acuerdos seguidos, no tiene
    sentido que arrastre los datos del anterior.
  - Si el usuario cierra el modal sin haber hecho click en "Generar PDF", se
    le avisa con un toast de advertencia — pero como Previsualización ya no
    guarda nada, no perdió ningún dato real (el formulario sigue exactamente
    como estaba).
- **Nombre del Ejecutivo Comercial en el PDF (2026-08-18)**: la línea
  "Nombre: ________" bajo "Ejecutivo Comercial" ya no queda en blanco para
  llenar a mano — imprime el nombre real de quien creó el acuerdo
  (`repositorio_acuerdos.creado_por` → `repositorio_usuarios_acuerdos.usuario`,
  JOIN agregado en `obtener_acuerdo_detalle()`). La **firma sigue siendo
  física siempre** (la línea en blanco de arriba para firmar a mano no
  cambió) — esto solo imprime el nombre de quien correspondía firmar, no
  captura una firma. "Jefe Comercial" (columna derecha) sigue en blanco, no
  hay ningún dato en el sistema de quién es esa persona. Acuerdos huérfanos
  sin `creado_por` (ver más abajo) caen de nuevo a la línea en blanco de
  siempre.
- **Tamaño de letra del PDF, tercera vuelta (2026-08-19)**: desde que el
  periodo pasó a trimestres fijos (Q1-Q4, ver Registrar Acuerdo PDV), las
  tablas SIEMPRE tienen exactamente 3 columnas de mes — ya no hay que dejar
  margen para el caso de 12 columnas, así que sobraba espacio en blanco.
  Subida notoria (no otro +0.5px tímido) en `includes/acta_pdf.php`: body
  11.5→13px, `h1` 19→22px, `.subtitulo` 13.5→16px, `.label`/`.hint`/
  `.condiciones h3` ~10.5→11.5-12px, `td`/`th` padding 4/7.5→5/9px, tamaño
  base de la celda Categoría (auto-ajustable a 1 línea, `fuente_una_linea()`)
  12→13px para que combine con el resto. Todo sigue en negro (`#000000`,
  confirmado que no quedaba nada gris) — se agregó `color:#000000` explícito
  a `.subtitulo`/`.condiciones h3` que antes solo heredaban de `body`, por si
  algún día se sobreescribe ese heredado sin querer. El auto-ajuste a 1 hoja
  (`generar_acta_pdf_binario()`) sigue siendo la red de seguridad si algún
  Acta puntual no entra a estos tamaños.
- **Tamaño de letra, cuarta vuelta — "letra grande" de verdad (2026-08-19,
  misma fecha, sesión larga)**: el usuario pidió texto notoriamente más
  grande ("necesito lentes para leerlo"). Terminó en:
  - **Celdas de tabla (`td, th`) ahora tienen su PROPIO `font-size` explícito
    (18.5px base), en vez de heredar de `body`.** Esto es la pieza clave:
    permite subir el texto general (párrafos/etiquetas/condiciones, que van
    en `body`) sin que arrastre también los números/nombres de las tablas —
    pedido explícito del usuario ("la info que está dentro de las tablas no
    le subas el tamaño, esos ya están bien así"). Antes `td`/`th` no tenía
    `font-size` propio, así que CUALQUIER cambio a `body` afectaba todas las
    celdas de las 4 tablas a la vez — la causa real de que "igualar todo al
    tamaño de subtítulo" rompiera el límite de 1 hoja (ver más abajo).
  - Valores finales (+0.5px extra pedido dos veces más, confirmado en ambas
    que seguía entrando en 1 hoja): `body`/`.label`/`.hint`/`.condiciones
    h3`/párrafo de aceptación del cliente/`.subtitulo` = **21px** (todos
    iguales — el pedido literal era "sube el tamaño a el de los
    subtítulos"). `h1` = 28px. `.doc-no` = 15.5px/`.doc-no strong` = 22px.
    `td`/`th` (todas las celdas de datos) = **18.5px, SIN CAMBIAR en
    ninguna de las 3 vueltas** — a propósito, es contenido de tabla. Columna
    Categoría de Meta de Compras (auto-ajustable, `fuente_una_linea()`) =
    18.5px base — tampoco cambió. `.legend-box` (la cajita "Valor Ruma x
    Marca x Mes") = 16.5px, sin cambiar — también es contenido de tabla.
  - **Espacio extra antes del bloque de firmas (2026-08-20)**: el párrafo
    "Como constancia del presente convenio, firman de común acuerdo las
    partes." (justo antes de `.firmas-footer`) ahora tiene
    `margin: 3px 0 14px` en vez de `3px 0` — el pedido fue simular "un enter
    de más" en Word para que el bloque de firmas baje un poco y no quede
    pegado a ese párrafo. Solo ese párrafo cambió, los otros dos de
    "Consideraciones Generales" siguen con `margin:3px 0`. Probado con Acta
    real (3 filas) y simulada (4 filas): sigue en 1 hoja.
  - **Probado con la Acta real (3 líneas) y con una prueba de estrés
    sintética (4 líneas en las 4 tablas a la vez, duplicando una línea real
    en memoria, nunca guardada) — ambas entran en 1 sola hoja**, sin que el
    achicado automático tuviera que intervenir.

  **Dos bugs reales encontrados en el camino, ninguno de los dos era "el
  tamaño de letra en sí" — vale la pena leerlos si esto se vuelve a tocar:**
  1. **Un script de diagnóstico propio (probar muchas escalas dentro del
     MISMO proceso de PHP, en un `for`) daba conteos de página FALSOS** —
     Dompdf arrastra algo entre instancias sucesivas en el mismo proceso que
     hace que `get_page_count()` no sea confiable ahí. Esto hizo perder
     mucho tiempo (parecía que hasta el tamaño original de 13px ya no
     entraba en 1 hoja, lo cual era mentira). **Lección: para verificar
     "¿cuántas páginas da esta Acta?", SIEMPRE usar un proceso nuevo de PHP
     por cada render** (ej. `generar_acta_pdf_binario()` llamado una vez por
     invocación de `php.exe`, nunca un `for` de escalas/tamaños dentro del
     mismo script/proceso).
  2. **Usar `sed`/reemplazo global de texto para probar distintos tamaños
     candidatos (ej. cambiar todos los `px(22, $escala)` a `px(18, $escala)`
     y de vuelta) corrompió 4 valores que NO eran tamaño de letra pero
     coincidían con el mismo número**: `top`/`right` de `.doc-no` (posición
     del "Documento No"), `padding-left` de `.condiciones ul`, `margin-top`
     de `.firmas-footer`, y `margin-top` del bloque de aceptación del
     cliente. Cada uno se restauró a mano. **Lección: en este archivo, nunca
     usar `sed`/`replace_all` sobre un número suelto tipo `22` — hay
     demasiados valores de padding/margin/posición que coinciden por
     casualidad con el mismo número que un tamaño de letra. Edits puntuales,
     con el `old_string` completo de la línea, siempre.**
  - **Conclusión de diseño, por si se vuelve a pedir "igualar todo al mismo
    tamaño"**: con `td`/`th` ahora desacoplado de `body`, agrandar el texto
    general YA NO arriesga romper el límite de 1 hoja — antes sí, porque
    hint/condiciones (texto largo, aparece varias veces) heredaban el mismo
    tamaño que se le subía a `body`, y esas sumaban mucha altura. La lección
    de fondo: en un documento con tablas densas, el tamaño de las CELDAS
    pesa mucho más en el total de altura que el tamaño de las etiquetas o
    los párrafos sueltos — conviene mantenerlos como propiedades
    independientes, no todas heredando de `body`.
- **Formato de Acta para canal Distribuidor (2026-08-20)**: hasta acá el PDF
  siempre usaba el mismo layout que Canal Directo, sin importar el canal real
  del cliente. Se agregó un segundo formato, replicando
  `datos/FORMATO Distribuidor.pdf` (Excel/PDF real que mandó el cliente):
  - **Cómo se decide directo vs. distribuidor**: `es_distribuidor` es un
    booleano nuevo en el `$detalle` que arma `generar_acta_html()`. Para
    Actas reales (Historial, descarga, snapshot al generar) se deriva 100%
    en vivo, SIN tocar el esquema: `obtener_acuerdo_detalle()` en
    `functions.php` ahora trae también `d.canal` (columna que YA existía en
    `repositorio_locales_supervisores_cliente`, solo se agregó al SELECT) y
    compara `canal === 'DISTRIBUIDOR'` para ESE `pos_id` puntual — no el
    canal del supervisor de la sesión (que puede cambiar con el tiempo), el
    del cliente real guardado en el acuerdo. Para la Previsualización
    (`getters/previsualizar_acta_pdf.php`, que a propósito nunca abre
    conexión a la base) no se puede derivar así — el frontend
    (`assets/js/registrar.js`) manda `es_distribuidor` en el body del POST,
    tomado de `catalogoDistribuidor.canal` (ya cargado al entrar a
    Registrar, mismo dato que decide la cascada Empresa→Cliente). Mismo
    criterio permisivo que ya usa el resto de ese endpoint (no se valida
    contra la base porque nada se persiste).
  - **Diferencias del formato Distribuidor vs. Directo** (todo lo demás —
    logo, encabezado Estimado/Localidad/Fecha, Condiciones, Consideraciones
    Generales, firmas de Ejecutivo/Jefe Comercial — es idéntico):
    - Solo se imprime la tabla 1 (Meta de Compras) — **no** se imprimen
      3.a Extravisibilidad/Cabeceras ni 3.b Espacio en Perchas & Rumas (ni
      sus subtítulos/hints), aunque el acuerdo tenga líneas cargadas ahí.
    - Título de la sección 1: en Distribuidor dice
      "1. Meta de Compras en Dólares + Home Care Jw" (todo en la misma
      línea). En Directo (cambio pedido en la misma sesión, aplica siempre)
      ahora dice "1. Meta de Compras en Dólares" con un `<br>` y
      "Home Care" en la línea de abajo.
    - En "Firma del Cliente": **corregido 2026-08-20 (ronda 3)** — en
      Distribuidor, "Razón Social:" YA NO imprime el nombre del cliente
      (eso es la mecánica de Directo, el usuario pidió explícitamente NO
      copiarla acá) — ahora es una línea en blanco para llenar a mano,
      igual que el resto de la firma, con "C.I.:" debajo también en
      blanco (mismo estilo: etiqueta + línea subrayada en la misma fila,
      no en líneas separadas). En Directo no cambió nada: sigue imprimiendo
      el nombre real debajo de "Razón Social:", como siempre.
    - Logo: se reusa el mismo `assets/img/logo_alicorp.png` de siempre — el
      usuario pidió explícitamente NO replicar el logo viejo del PDF de
      referencia, usar "el nuestro, como lo tenemos diseñado".
    - **"Estimado(a)" (2026-08-20, ronda 4)**: en Distribuidor ya NO
      muestra el nombre del Local (`pos_name`) — muestra la **Empresa
      Distribuidora** (`repositorio_locales_supervisores_cliente.tipo_distribuidor`
      del cliente, ej. "ASERTIA COMERCIAL SA"), que en la UI de Registrar
      es el campo que se ve etiquetado **"Distribuidor"** (`ac-empresa-*`
      — el campo `ac-distribuidor-*`, el pos_id real, se ve etiquetado
      **"Local"**; ver el comentario en `components/registrar/registrar.php`
      líneas 35-37 que explica ese swap de labels, hecho en otra sesión).
      La frase "Jabonería Wilson y {nombre} celebran..." NO cambió — sigue
      usando el nombre del Local (`$detalle['distribuidor']`), que es lo
      que ya mostraba antes. En Directo, "Estimado(a)" no cambió — sigue
      mostrando el mismo nombre que la frase de celebración (no hay Empresa
      Distribuidora en ese canal). Implementado agregando
      `d.tipo_distribuidor` al SELECT de `obtener_acuerdo_detalle()`
      (mismo patrón que `d.canal`, solo lectura) → `$detalle['empresa_distribuidora']`;
      para Previsualización (sin base) se manda desde `registrar.js`
      (`empresaSearch.value`) en el POST. Probado contra el Acuerdo real
      41 (Local "ACOSTA SANTAMARIA EDGAR PATRICIO", Empresa "ASERTIA
      COMERCIAL SA") — ambos nombres distintos aparecen correctos, cada
      uno en su lugar.
  - Implementado con un solo `$esDistribuidor = !empty($detalle['es_distribuidor'])`
    al inicio de `generar_acta_html()` en `includes/acta_pdf.php`, y 3
    puntos condicionales en el HTML (título de sección 1, bloque
    3.a+3.b completo, línea de C.I.) — nada de una plantilla separada que
    mantener sincronizada. El auto-ajuste a 1 hoja (`generar_acta_pdf_binario()`)
    sigue siendo el mismo para ambos formatos; con Distribuidor, al tener
    mucho menos contenido, el PDF queda con harto espacio en blanco abajo —
    es esperable (el PDF de referencia real también lo tiene así), el
    sistema no "estira" contenido para llenar la hoja (decisión ya tomada
    antes, ver más arriba).
  - Probado (solo lectura, `SHOW`/`SELECT`) contra 5 Acuerdos reales ya
    generados de un cliente con `canal='DISTRIBUIDOR'` real
    (ADN-2026-0038/39/40/43/45) — renderiza bien: solo tabla 1, título con
    "+ Home Care Jw", C.I. debajo de Razón Social, 1 sola página. También
    se re-probó un Acta Directo real para confirmar que sigue mostrando las
    4 tablas y el nuevo salto de línea "Home Care" sin romper el límite de
    1 hoja.
  - **Corregido 2026-08-20 (ronda 2, mismo día)**: el `<h1>` (título
    principal) ahora también dice **"Canal Distribuidor"** en vez de
    siempre "Canal Directo" — antes estaba fijo en el texto aunque el
    formato ya fuera distinto. Al hacerlo, "Distribuidor" (más largo que
    "Directo") empujó el título centrado hasta chocar visualmente con el
    recuadro fijo "Documento No" de la esquina superior derecha — se
    corrigió agregando `padding-right` al `h1` (150px base) para reservarle
    ese espacio; probado que ni Distribuidor (título largo) ni Directo
    (título corto) se ven mal con ese padding.
  - **"Descargar Excel" en Historial ahora también funciona para
    Distribuidor (2026-08-20)**: antes se bloqueaba el click con un toast
    "próximamente" (canal Distribuidor no tenía Excel construido); ese
    bloqueo se sacó de `assets/js/historial.js` y de
    `components/historial/historial.php` (`$canalUsuario`/
    `CANAL_USUARIO_HISTORIAL` eran solo para ese bloqueo, se eliminaron —
    quedaban muertos sin él) porque ahora SÍ existe la hoja real, ver
    "Export CUOTA POR CAT-DISTRIBUIDORES" más abajo.
- **Campos "Empresa Distribuidora"/"Distribuidor" renombrados en pantalla
  (2026-08-20)**: pedido explícito, solo texto visible — **ningún ID ni
  nombre de variable cambió**, para no arriesgar nada tocando de más. Lo que
  se veía **"Empresa Distribuidora"** (campo `#ac-empresa-search`, la
  empresa/`tipo_distribuidor` que agrupa clientes, solo visible si el canal
  del usuario es Distribuidor) ahora dice **"Distribuidor"**. Lo que se veía
  **"Distribuidor"** (campo `#ac-distribuidor-search`, el `pos_id` real del
  cliente/PDV — el mismo de siempre) ahora dice **"Local"**. Cambiaron
  labels, placeholders, y todos los mensajes de validación/toast que
  nombraban el campo (`registrar.js`: `validarCabecera()`,
  `describirCampoCombo()`, placeholders de "Elige un ... primero" en
  Segmento/Marca; `guardar_acuerdo.php` y `previsualizar_acta_pdf.php`: los
  3 mensajes de error que mencionaban "Distribuidor"). **No se tocó**: el
  badge de canal arriba del formulario (`Distribuidor`/`Canal Directo`, es
  otro concepto — el canal completo del usuario, no este campo puntual), ni
  la columna "Distribuidor" de Historial (mismo dato, `pos_name`, pero el
  usuario acotó el pedido a "el módulo de registro" — si se quiere el mismo
  cambio ahí, confirmar antes de tocarlo).
- **Aviso "Módulo en desarrollo" en Liquidación (2026-08-20)**: SweetAlert2
  informativo (un solo botón "Entendido", mismo estilo que la confirmación
  de "Eliminar"). Primer intento lo disparaba directo al final de
  `assets/js/liquidacion.js` — **bug real, salía "de la nada" en cualquier
  módulo con el que arrancara la sesión**, porque ese script corre una sola
  vez al cargar `index.php` sin importar qué pestaña esté activa (misma
  arquitectura de "todo se renderiza una vez" de siempre). Corregido
  siguiendo el mismo patrón que `window.acHistorialRefrescar`/
  `window.acUsuariosRefrescar`: se expone `window.acLiquidacionRefrescar`,
  `index.php` lo llama recién al hacer click en la pestaña Liquidación
  (`refrescoPorSeccion['#sec-liquidacion']`), y el aviso solo se muestra la
  PRIMERA vez que se entra en la sesión (`avisoDesarrolloMostrado`), no cada
  vez que se vuelve a esa pestaña.
- **Leyenda de Rumas: 1 fila por línea de la tabla, ya no por Marca
  compartida (2026-08-20)**: hasta ahora, si dos filas de Rumas tenían la
  MISMA Marca (ej. "Canuto Chico" y "Caracol Chico", ambos "DON VITTORIO"),
  la leyenda "Valor Ruma x Marca x Mes" las fusionaba en una sola fila con un
  único valor compartido — el usuario mostró una captura con 3 filas en la
  tabla grande pero solo 2 en la leyenda y pidió el arreglo. Se le preguntó
  explícitamente si quería mantener el agrupado por Marca (como el Acta real
  que se revisó en su momento) o pasar a 1 fila por línea con valor
  independiente — **eligió independiente por línea**. Cambiado
  `updateRumaLegend()` (`assets/js/registrar.js`): ya no agrupa por
  `marca-select`, itera directo las filas con Marca elegida y cada input de
  la leyenda queda atado por closure a SU fila exacta (no por nombre de
  Marca) — dos filas con la misma Marca ahora pueden tener valores de Ruma
  distintos. **No hizo falta tocar el backend**: `guardar_acuerdo.php`/
  `repositorio_acuerdo_lineas.valor_mensual_unico` siempre guardó por línea,
  nunca agrupó por Marca a nivel de base — el agrupado visual era solo del
  frontend.
- **Spinners: ahora son readonly de verdad, no se puede tipear nada
  (2026-08-20, decisión final tras 2 vueltas)**: primer intento (mismo día)
  fue solo arreglar que un segundo click en un campo ya enfocado dejaba
  insertar texto en medio del valor elegido (`input.select()` en el `click`,
  no solo en `focus`) — el usuario probó y confirmó que **seguía pudiendo
  editar**, pidió bloqueo total: "no quiero que se pueda editar o escribir
  más en esos campos que tienen spinner". Se le preguntó explícitamente
  entre 2 opciones (bloqueo total sin poder buscar tipeando, vs. limpiar y
  dejar buscar de nuevo al hacer click) — **eligió bloqueo total**. Implementado
  agregando el atributo `readonly` a **todos** los inputs de combo (los 6
  tipos: Distribuidor y Empresa en `registrar.php`; Segmento/Sector/Categoría/Marca
  vía `comboCellHtml()` en `registrar.js`) — un input `readonly` sigue siendo
  clickeable/enfocable (así el panel se sigue abriendo normal), pero el
  navegador bloquea nativamente cualquier tipeo o pegado. Para cambiar un
  valor ya elegido: click en el campo → se abre el panel con la lista
  COMPLETA (nunca filtrada por tipeo, ya no se puede) → click en la opción
  nueva. De paso se simplificó `inicializarCombo()` (ya no hace falta el
  listener de `input` que limpiaba `hidden.value` al tipear, ni
  `input.select()` en ningún lado — nada de eso puede pasar ya) y se sacaron
  2 listeners de `input` que quedaron muertos en `empresaSearch`/
  `distribuidorSearch` (su lógica ya la cubre el `onSeleccionar` de
  `comboSeleccionar()`, que corre en cada selección real). La validación de
  "campo con texto pero sin elegir de la lista" (`validarCabecera()`) queda
  como red de seguridad — ya no debería poder dispararse nunca por esta vía,
  pero ahora el mensaje dice el campo y la tabla exactos
  (`describirCampoCombo()`) y lo resalta en rojo un momento, por si acaso.
- **Validaciones agregadas (2026-08-18)**, en `assets/js/registrar.js`
  (`validarCabecera()`) Y repetidas en `getters/guardar_acuerdo.php` (el
  guardado es por `fetch()`, no un submit nativo, así que nada obliga a pasar
  por la validación del cliente):
  - Montos en dólares (Meta de Compras, Cabeceras, Rumas, Perchas) nunca
    negativos — se clampan a 0 mientras se tipea, y el servidor también los
    floorea con `max(0, ...)` por si acaso.
  - **Rebate % deliberadamente sin validar rango** — el cliente confirmó que
    va a salir de un repositorio nuevo (columna en `repositorio_productos`,
    todavía sin datos, ver pendiente más abajo), no tiene sentido acotarlo a
    mano mientras se sigue tipeando manual.
  - "Filas fantasma": si el usuario tipea texto en cualquier spinner
    (Distribuidor/Empresa/Segmento/Sector/Categoría/Marca, en cualquiera de
    las 4 tablas) pero nunca hace click en una opción real de la lista, el
    guardado se bloquea con un toast — antes esa fila se descartaba en
    silencio en `guardar_acuerdo.php` sin avisar al usuario.
  - **Filas duplicadas SÍ se permiten a propósito** — el cliente lo pidió
    explícitamente, no validar esto.
  - Un acuerdo completamente vacío (ninguna fila real en ninguna de las 4
    tablas) solo se permite guardar como `'borrador'` — Generar Acta lo
    bloquea con un mensaje pidiendo cargar al menos un producto o guardar
    como borrador.
  - Participación de Perchas (`v-participacion`, texto libre tipo "50%")
    debe tener un número real tipeado y no puede ser negativa — se le quita
    el "-" mientras se tipea, y el guardado la rechaza si queda vacía o no
    numérica.

## Formato de Acta "sin visibilidad" para canal Directo + switch "Visibilidad y Espacios" (2026-08-24)

**Corrección de diseño, no solo una feature nueva**: hasta esta sesión, el
Acta sin las tablas 3.a (Cabeceras) y 3.b (Rumas & Perchas) solo existía
como parte del "Formato Distribuidor" (ver sección de arriba, 2026-08-20) —
una sola bandera (`es_distribuidor`, derivada del canal real del cliente)
controlaba a la vez "ocultar esas 2 tablas" Y "título/Estimado(a)/C.I./Razón
Social estilo Distribuidor". El usuario aclaró que eso estaba mal
etiquetado: ese PDF nunca fue realmente "de Distribuidor" — es un segundo
formato de **Canal Directo**, elegible por el propio analista con un
switch en el formulario, no derivado del canal. En total la idea a futuro
son 4 PDF (Directo con/sin visibilidad, Distribuidor con/sin visibilidad) —
**esta sesión solo construye los 2 de Directo**; Canal Distribuidor
queda deliberadamente sin tocar (sigue ocultando esas 2 tablas siempre,
exactamente como antes de este cambio) hasta que se retome en otra sesión.

- **Se separó en 2 banderas independientes** (`includes/acta_pdf.php`,
  `generar_acta_html()`): `$esDistribuidor` (sin cambios, sigue viniendo del
  canal real — controla el H1 "Canal Distribuidor"/"Canal Directo", el
  swap de "Estimado(a)" a Empresa Distribuidora, el título "+ Home Care Jw"
  de la sección 1, y el bloque C.I./Razón Social en blanco de la firma —
  **nada de esto se tocó**) y `$sinVisibilidad` (nueva, de
  `$detalle['sin_visibilidad']`) — solo controla si se imprimen las tablas
  3.a/3.b. La condición que las oculta pasó de `$esDistribuidor` a
  `$ocultarVisibilidad = $esDistribuidor || $sinVisibilidad` — así un Acta
  de canal Distribuidor las sigue ocultando siempre (por el `||`), tenga o
  no activado el switch nuevo (ese canal todavía no usa el switch de
  verdad, ver arriba). Para canal Directo con el switch desactivado, el
  resultado es el layout que antes solo existía bajo "Distribuidor" —
  mismas 2 tablas ocultas — pero con título/Estimado(a)/firma 100% de
  Directo (sin C.I., con Razón Social real), porque `$esDistribuidor` sigue
  en `false`. Verificado con un script aislado (`generar_acta_html()`
  directo, sin PDF ni base) comparando los 3 casos (Directo con
  visibilidad / Directo sin visibilidad / Distribuidor real) — el único que
  cambió de comportamiento es "Directo sin visibilidad", que antes no
  existía como combinación posible.

- **Nueva columna, pendiente que el usuario corra el `ALTER TABLE`** (Claude
  no puede, regla de solo lectura):
  ```sql
  ALTER TABLE repositorio_acuerdos ADD COLUMN sin_visibilidad TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;
  ```
  Nada de este flujo funciona en producción hasta que exista la columna —
  `guardar_acuerdo.php` va a fallar el INSERT/UPDATE si se usa antes de
  correr esto (mismo patrón que la columna `sector` de 2026-08-18).
  `obtener_acuerdo_detalle()` (`includes/functions.php`) ahora trae
  `a.sin_visibilidad` en el SELECT y lo devuelve como
  `$detalle['sin_visibilidad']` (bool). `previsualizar_acta_pdf.php` (que a
  propósito nunca abre conexión a la base) lo recibe del POST del cliente,
  mismo patrón que ya usaba para `es_distribuidor`.

- **Switch "Visibilidad y Espacios"** (`components/registrar/registrar.php`,
  junto al título "3. Visibilidad y Espacios"): reusa el mismo componente
  `.ac-switch`/`.ac-slider` que ya existe para el estado activo/inactivo de
  Gestión de Usuarios (`includes/functions.php`), no un componente nuevo.
  Activado por defecto (mismo comportamiento de siempre, sin cambios para
  quien no lo toque). Al desactivarlo (`assets/js/registrar.js`,
  `visibilidadToggle` change listener):
  - Se le agrega la clase `ac-zona-bloqueada` a `#ac-visibilidad-zona` (el
    `<div>` nuevo que envuelve las 3 secciones 3.a/3.b/3.c) —
    `opacity:0.5; pointer-events:none` (mismo look que un `.ac-switch`
    deshabilitado en el resto de la app), cubre también filas agregadas
    dinámicamente después sin tener que reaplicar nada por fila.
  - `resetearZonaVisibilidad()` limpia las 3 tablas a una sola fila vacía
    cada una (mismo estado inicial que `syncTables()`) — para no dejar
    datos ya tipeados "atrapados" detrás del bloqueo visual.
  - El payload de `guardarAcuerdo()` y de `mostrarPreview()` ahora manda
    `sin_visibilidad: !visibilidadActiva`.
  - `guardar_acuerdo.php` además fuerza del lado del servidor: si
    `sin_visibilidad` viene true, `$filasNormalizadas['cabecera']`/`ruma`/
    `percha` se vacían antes de guardar, sin importar qué haya mandado el
    cliente — defensa adicional (mismo criterio que `normalizarValores()`),
    por si algún estado stale del navegador manda datos igual.
  - `limpiarFormularioParaNuevoAcuerdo()` (después de Generar PDF, el
    usuario sigue con el próximo Acuerdo) resetea el switch a activado.
  - `aplicarBorrador()` (Continuar Editando desde Mis Borradores) restaura
    `visibilidadActiva` desde `a.sin_visibilidad` y aplica la clase visual
    — no llama a `resetearZonaVisibilidad()` ahí porque
    `poblarTablasConLineas()` ya reconstruye esas 3 tablas desde
    `a.lineas`, que van a venir vacías si se guardó con el switch apagado
    (por el forzado del lado del servidor de arriba).

- **Probado**: sintaxis PHP (`php -l`) de los 5 archivos tocados, sintaxis
  JS (`node --check`) de `registrar.js`, y `generar_acta_html()` con datos
  sintéticos (sin base, sin PDF) confirmando los 3 escenarios (Directo con
  visibilidad = igual que antes; Directo sin visibilidad = nueva
  combinación, título/Estimado/firma de Directo sin las 2 tablas; canal
  Distribuidor real = idéntico a antes de este cambio). **No probado en
  navegador real** (el switch, el bloqueo visual, ni el guardado real
  contra la base — falta correr el `ALTER TABLE` primero).
  **⚠️ Superado más abajo** ("Distribuidor con/sin visibilidad + renumeración
  2.a/2.b"): en esa sesión posterior, canal Distribuidor DEJÓ de ser
  "idéntico a antes" — ver esa sección para el estado real y vigente.

## Distribuidor con/sin visibilidad + renumeración 2.a/2.b (2026-08-24, misma sesión, continuación)

Con los 2 PDF de Directo ya funcionando (sección de arriba), el usuario
pasó los 2 Excel reales que usa el cliente para Distribuidor
(`datos/FORMATO DTS CON VISIBILIDAD (1).xlsx` y
`datos/FORMATO DTS SIN VISIBILIDAD (1).xlsx`, más
`datos/24-08-2026 10.16.txt`, transcripción de una llamada con
Michelle/Gabriela que confirma el concepto del switch/"ojito"). Leídos
completos vía Excel COM (no hay lector de xlsx en PHP acá, se abrió con
`New-Object -ComObject Excel.Application` en PowerShell) y comparados
celda por celda contra ambos formatos.

**Verificado con números reales (alta confianza, no es solo una corazonada
de lectura del Excel) — aplicado sin preguntar:**
- **Distribuidor mide Meta de Compras y Visibilidad en CAJAS, no en
  Dólares.** Confirma una duda que quedó abierta en la memoria del
  2026-08-23 ("REPLANTEO... duda abierta sobre el formato $"). Nueva función
  `numero($v)` en `includes/acta_pdf.php` (mismo `number_format` que
  `moneda()`, sin el signo `$`) y una variable `$fmt = $esDistribuidor ?
  'numero' : 'moneda'` calculada una vez al principio de
  `generar_acta_html()`, usada en TODAS las celdas de valor de las 4 tablas
  (Meta de Compras, Cabeceras, Rumas + su leyenda, Perchas) — antes esas
  celdas llamaban a `moneda()` directo. `tabla_marca_html()` (Cabeceras/
  Rumas) ahora recibe `$fmt` como parámetro nuevo (default `'moneda'`, no
  rompe nada si algún caller no lo pasa).
- **"Estimado a Ganar" tiene una FÓRMULA distinta para Distribuidor, no solo
  otro nombre de columna**: `Total × Rebate%` (ej. 124.37×1.5%=1.87,
  499.11×2.5%=12.48 — verificado contra 4 filas reales de ambos Excel).
  Directo sigue con `Total × (1 + Rebate%)`, sin cambios — esa fórmula
  representa el valor total del trato (compra + bono), mientras que para
  Distribuidor representa solo las cajas de bono ganadas, un concepto más
  angosto. Encabezado de columna también cambia: "Cajas Estimadas a Ganar"
  (Distribuidor) vs "Estimado a Ganar" (Directo).
- **El pago a Distribuidor se reconoce "a través de producto"**, no de nota
  de crédito (párrafo de Consideraciones Generales) — aparece igual,
  palabra por palabra, en AMBOS Excel (con y sin visibilidad), así que no es
  un detalle de una sola versión. "El plazo para entregar el producto" en
  vez de "emitir la nota de crédito".
- Condición (a): "Cumplir con la meta del período en **cajas** netas al
  100%" (Directo sigue en "dólares netos").
- Título de sección 1: `'1. Meta de Compras en Cajas'` (Distribuidor,
  reemplaza por completo el `' + Home Care Jw'` que tenía antes — ese
  Excel real no menciona "Home Care" en ningún lado) vs
  `'1. Meta de Compras en Dólares<br>Home Care'` (Directo, sin cambios).
  Hint también cambia a "Cajas compradas por categoría sin considerar cajas
  a título gratuito por bonificación/descuentos." (tomado de la versión SIN
  del Excel, más completa/consistente que la versión CON, que por algún
  motivo se quedó con el hint viejo en Dólares pese al título en Cajas —
  se corrigió esa inconsistencia del Excel del cliente, no se replicó tal
  cual).

**Se le preguntó al usuario explícitamente antes de tocar esto** (vía
`AskUserQuestion`), porque el Excel real también trae 3 diferencias de
formato frente al Acta de Distribuidor que YA estaba en producción (con
Actas reales generadas y aceptadas) — no se quiso asumir sobre un
documento ya en uso real sin confirmar. El usuario eligió aplicar los 3
cambios (**"aplicar todo el Excel"**) y además pidió extender la
renumeración al formulario y a Directo también:

- **H1**: `'Acuerdo Comercial Canal Distribuidores'` (Distribuidor,
  reemplaza `'Acuerdo de Desarrollo de Negocios Canal Distribuidor'`).
  Directo no cambió.
- **Firma izquierda**: `'Desarrollador de Mercado'` (Distribuidor, en vez de
  `'Ejecutivo Comercial'`) — el nombre de quien generó el acuerdo
  (`creado_por`) sigue imprimiéndose igual arriba de esta etiqueta, solo
  cambió el texto de la etiqueta.
- **Renumeración completa 3→2, pedida explícitamente para TODO el
  proyecto** ("cambiale eso a 2 a 2b... y en acuerdo directo cambiale
  también a 2 a 2b así" — no solo Distribuidor): tanto Directo como
  Distribuidor ahora imprimen `2. Visibilidad` (encabezado nuevo, no
  existía impreso antes, solo el `.subtitulo` genérico) seguido de
  `2.a. Extravisibilidad: Cabeceras` y `2.b. Espacio en Perchas & Rumas`
  (antes `3.a`/`3.b`, sin encabezado `3.`/`2.` propio). El formulario de
  Registrar (`components/registrar/registrar.php`) se renumeró igual, texto
  únicamente — título de sección "2. Visibilidad y Espacios" (antes "3."),
  tarjetas "2.a. Extravisibilidad: Cabeceras" / "2.b. Espacio: Rumas" /
  "2.c. Espacio: Perchas" (antes 3.a/3.b/3.c). Ningún id/variable interna
  cambió (mismo patrón que el rename "Distribuidor"→"Local" de 2026-08-20)
  — el formulario sigue teniendo 3 tarjetas separadas (Cabeceras/Rumas/
  Perchas) aunque el PDF combine Rumas+Perchas bajo un solo "2.b." impreso,
  eso ya era así antes y no se tocó.

**Encontrado pero NO aplicado a propósito** (ruido, no se confirmó, no vale
la pena arriesgar un documento real por algo ambiguo): la hoja "SIN
VISIBILIDAD" del Excel tiene una TERCERA firma ("Asesor Comercial
(distribuidor)") que no aparece en la hoja "CON VISIBILIDAD" (que solo
tiene 2, igual que Directo) — inconsistente entre las dos versiones del
mismo Excel, probablemente un artefacto de plantilla y no un pedido real.
Se dejaron las 2 firmas de siempre (izquierda/derecha) para ambos casos de
Distribuidor. Si en algún momento se confirma que la tercera firma es
real, agregarla es un cambio chico y aislado en el bloque `.firmas-footer`.

**Desacoplado del canal, ahora el switch controla la visibilidad de las
tablas 2.a/2.b para AMBOS canales por igual** — antes (sección de arriba)
`$ocultarVisibilidad = $esDistribuidor || $sinVisibilidad` forzaba
Distribuidor a ocultar esas tablas siempre; ahora es
`$ocultarVisibilidad = $sinVisibilidad`, sin el `||`. Esto habilita de
verdad "Distribuidor CON visibilidad" (antes imposible de generar). Para
no cambiarle el comportamiento a nadie que ya usaba Distribuidor sin saber
que existía este switch, **el switch arranca DESACTIVADO por defecto
cuando el canal del usuario es Distribuidor** (`registrar.js`,
`cargarDatosIniciales()`, una vez que se conoce `catalogoDistribuidor.canal`)
— preserva el comportamiento histórico (esas tablas siempre ocultas) para
quien no toque el switch. Directo sigue arrancando activado, sin cambios.
Un borrador ya guardado (Directo o Distribuidor) sigue restaurando su
propio valor real (`aplicarBorrador()`), no este default.

- **Probado**: sintaxis PHP/JS de los archivos tocados, y `generar_acta_html()`
  con datos sintéticos confirmando las 4 combinaciones completas (Directo
  con/sin visibilidad, Distribuidor con/sin visibilidad) — título, unidades
  (`$` vs sin signo), fórmula de Estimado/Cajas a Ganar, C.I., firma
  "Desarrollador de Mercado", pago "a través de producto", condición en
  cajas, y que Cabeceras/Rumas/Perchas de Distribuidor también salen sin
  signo `$`. **No probado en navegador real** — mismo pendiente que la
  sección de arriba, falta el `ALTER TABLE` de `sin_visibilidad`.

### Zona de firmas de Distribuidor + sin visibilidad: 2 firmas, sin "Obligatorio" (2026-08-24, corregido 2026-08-25)

El usuario mostró una captura del formato físico real y la zona de firmas
del PDF no calzaba. **Confirmado con el usuario, alcance exacto (no
asumido):**
- Aplica **SOLO cuando `es_distribuidor=true` Y `sin_visibilidad=true`** —
  Distribuidor CON visibilidad y Directo (con o sin visibilidad) siguen
  con las 2 firmas de siempre (Ejecutivo/Desarrollador + Jefe Comercial),
  sin cambios.
- La firma izquierda **mantiene el nombre real autocompletado** de quién
  generó el Acta (`$nombreEjecutivoHtml`, ya existía).

**Implementado en `includes/acta_pdf.php`** (bloque `firmas-footer`,
condicional `$esDistribuidor && $sinVisibilidad`): 2 firmas, misma
estructura de tabla de 2 columnas que el layout por defecto, pero con la
etiqueta derecha distinta —
1. **Desarrollador de Mercado** (nombre autocompletado, como antes).
2. **Asesor Comercial (distribuidor)** — reemplaza a "Jefe Comercial" en
   esta posición; línea en blanco, no hay de dónde autocompletarla.

**2026-08-24 → 2026-08-25, historial del ajuste**: la primera versión
(24-08) agregaba una 3ra firma ("Jefe Comercial", centrada, debajo de las
otras 2) y la etiqueta `.label` **"Obligatorio"** arriba de cada línea,
según una captura del formato físico que el usuario mostró en ese momento.
El 25-08, contra otra captura real del PDF ya generado, el usuario pidió
explícitamente sacar las 3 palabras "Obligatorio" y la 3ra firma completa
("Jefe Comercial") — quedando en 2 firmas acá + Firma del Cliente más
abajo = **3 firmas en total en todo el documento**, ninguna con
"Obligatorio". Con este ajuste, el único diferencial real de este layout
frente al de 2 firmas por defecto es la etiqueta derecha ("Asesor
Comercial (distribuidor)" en vez de "Jefe Comercial").

**Probado**: `php -l` limpio; `generar_acta_html()` con datos sintéticos
para los 3 casos relevantes (Distribuidor+sin visibilidad, Distribuidor+con
visibilidad, Directo+sin visibilidad) — solo el primero trae "Asesor
Comercial (distribuidor)" y sin "Obligatorio" en ningún lado; los otros 2
quedan sin cambios, sin regresión. Además se generó el PDF real
(`generar_acta_pdf_binario()`) para el caso Distribuidor+sin visibilidad y
se leyó visualmente — entra en 1 sola página, layout correcto, nombre
autocompletado presente.

## Historial: columna "Periodo" con formato "Qx (mes-mes)" (2026-08-23)

`periodoCorto($mesInicio, $mesFin)` (`includes/functions.php`) ahora
antepone el trimestre cuando el rango calza EXACTO con uno (`Q1
(Ene-Mar)`, mismo texto literal que ya usa el `<select>` de "Período" del
filtro) — pedido explícito para que la tabla y el filtro se lean igual.
Un Acuerdo viejo con rango irregular (de antes de que el período se
volviera trimestre fijo) cae al formato anterior (`Ene - Feb`), nunca
inventa un "Qx" que no le corresponde. Afecta 2 lugares que ya comparten
esta función: la columna "Periodo" de Historial y la columna "Periodo" del
modal "Mis Borradores" (`getters/listar_borradores.php`) — mismo cambio en
ambos, sin tocar nada aparte. Probado con los 4 trimestres (`Q1
(Ene-Mar)`...`Q4 (Oct-Dic)`) y 2 casos legacy (mes único, rango
irregular) — todos dan el formato esperado; reconfirmado contra filas
reales de Historial.

## Historial de Acuerdos — filtros por trimestre/año + botones (2026-08-20)

- **Filtro de período reemplazado**: antes había un `<select>` de "Seleccionar
  Mes" suelto (1-12) filtrando por rango (`mes_inicio <= X <= mes_fin`).
  Como el Período del Acuerdo es siempre un trimestre fijo desde 2026-08-18
  (Q1 Ene-Mar / Q2 Abr-Jun / Q3 Jul-Sep / Q4 Oct-Dic, ver "Registrar Acuerdo
  PDV"), el filtro se cambió a dos selects: **"Período"** (Q1-Q4 + "Todos
  los períodos") y **"Año"** (poblado dinámicamente con `listar_anios_disponibles()`,
  solo años que realmente tienen Acuerdos del usuario — no un rango
  inventado). La búsqueda de texto sigue siendo por nombre de Distribuidor
  (`hist-buscar`, sin cambios).
  - `trimestreABounds($trimestre)` (nueva, `includes/functions.php`) traduce
    1-4 a `[mesInicio, mesFin]` (0-11). `listar_historial_acuerdos()` ahora
    recibe `$trimestre, $anio` en vez de `$mes`, y compara
    `a.mes_inicio = ? AND a.mes_fin = ?` (exacto, ya no rango) más
    `a.anio = ?` — ambos opcionales (`0` = sin filtrar).
  - **Mismo cambio replicado en `getters/exportar_cuota_categoria.php`**
    (las 2 queries, Cuota/Categoría y Visibilidad) — el botón "Descargar
    Excel" siempre exporta lo mismo que está filtrado en pantalla
    (`assets/js/historial.js`, `cargarHistorial()` arma la URL de ambos con
    los mismos `trimestre`/`anio`).
  - Probado en solo lectura contra datos reales (usuario con 2 Acuerdos
    Q1-2026): sin filtro trae 2, `trimestre=1&anio=2026` trae los 2
    correctos, `trimestre=3` trae 0, `anio=1999` trae 0 — filtros exactos,
    sin falsos positivos/negativos.
- **Botones del header desalineados, corregido**: "Actualizar" era
  `.ac-btn-outline`, "Nuevo Acuerdo" `.ac-btn-primary` (intencional, es el
  CTA principal — mismo patrón que Liquidación), pero "Mis Borradores"
  usaba `.ac-btn-secondary` (padding y tipografía distintos a los otros
  dos, sin `.ac-btn-inline`) — se veía "uno más grande, otro más chico, otro
  con otro diseño". Se cambió "Mis Borradores" a `.ac-btn-outline
  .ac-btn-inline`, igual que "Actualizar" — ahora el grupo queda
  outline+outline+primary, consistente con el resto de la app (Liquidación
  usa el mismo esquema outline+primary).

## Subir Acta Firmada — Historial (2026-08-21)

Faltaba una forma de subir la foto/PDF del Acta ya firmada a mano (el papel
vuelve firmado y alguien lo escanea/fotografía) — decisión de diseño
explícita: **vive en Historial, no es un módulo nuevo**. Razón (lente UX,
"respetar el propósito de cada módulo"): Historial ya es por definición "el
ciclo de vida de un Acuerdo ya generado" (Ver Detalles/Descargar
PDF/Eliminar) — "firmado y subido" es el siguiente estado natural de esa
misma historia, no un dominio de datos distinto (a diferencia de
Liquidación, que sí compara contra una fuente externa — venta real — y por
eso merece módulo propio).

**Schema — reusa una columna que YA existía sin usar**: `repositorio_acuerdos.firmas`
(JSON, nunca referenciada en ningún código) se renombró y retipeó a
`acta_firmada_archivo` (LONGBLOB), + 3 columnas de auditoría nuevas. El
`estado` ya tenía `'firmado'`/`'liquidado'` en su ENUM desde el diseño
original de la tabla pero tampoco se usaban — ahora `'firmado'` sí se
conecta: se setea automáticamente al subir (decisión explícita del usuario).

```sql
ALTER TABLE repositorio_acuerdos
  CHANGE COLUMN firmas acta_firmada_archivo LONGBLOB NULL,
  ADD COLUMN acta_firmada_mime VARCHAR(100) NULL AFTER acta_firmada_archivo,
  ADD COLUMN acta_firmada_subido_en DATETIME NULL AFTER acta_firmada_mime,
  ADD COLUMN acta_firmada_subido_por INT UNSIGNED NULL AFTER acta_firmada_subido_en;
```
**Ya corrida en producción (2026-08-21), confirmado con `DESCRIBE`.**

**Piezas nuevas:**
- `getters/subir_acta_firmada.php` — POST `multipart/form-data` (`id`,
  `archivo`). Mismo criterio de propiedad que `eliminar_acuerdo.php`
  (`creado_por` = usuario de sesión). Bloquea si el acuerdo está en
  `borrador`/`anulado`. Valida el mime REAL del archivo con `finfo`
  (`FILEINFO_MIME_TYPE`, no la extensión ni el `Content-Type` que manda el
  navegador — ambos se falsean fácil): solo `image/jpeg`, `image/png`,
  `image/webp`, `application/pdf`. Límite propio de 15MB (aparte de
  `upload_max_filesize`/`post_max_size` del servidor, que aplican antes).
  Reemplaza cualquier subida anterior (no hay versionado/historial de
  archivos) y pasa `estado='firmado'`.
- `getters/descargar_acta_firmada.php` — GET `?id=`, mismo chequeo de
  propiedad, sirve el archivo con `Content-Disposition: inline` (se abre en
  el navegador, no fuerza descarga) y el nombre `Acta_Firmada_{documento_no}.{ext}`
  (extensión derivada del mime guardado).
- `listar_historial_acuerdos()` ahora trae `(a.acta_firmada_archivo IS NOT NULL) AS tiene_firma`
  — con el MISMO fallback de `prepare()` que ya usa `login()` para la
  columna `supervisor` (si todavía no se corrió el `ALTER`, cae a
  `0 AS tiene_firma` en vez de romper todo Historial).
- `renderFilaHistorial()`: nueva columna "Firma" con badge
  (`.ac-badge-ok`/`.ac-badge-revisar`, mismas clases que ya usa el Resumen
  de Pagos de Liquidación, ningún CSS nuevo) + un solo botón por fila que
  cambia de ícono/acción según el estado (`upload_file` si falta, `task_alt`
  si ya está — clic abre el archivo en pestaña nueva).
- `accept="image/jpeg,image/png,image/webp,application/pdf"` en el
  `<input type="file">`, sin `capture` (se dejó elegir cámara O galería O
  archivo, en vez de forzar cámara — más flexible para subir un PDF
  escaneado desde desktop también).

**Rediseño UX (2026-08-21, mismo día, pedido explícito): modal de 2 paneles
lado a lado, no subida directa de un solo click.** El primer intento subía
apenas se elegía el archivo — el usuario pidió poder COMPARAR visualmente el
Acta generada contra la firmada antes de guardar, con un botón explícito de
confirmación:
- **Panel izquierdo** ("Acta Generada"): siempre el mismo iframe de
  `getters/generar_acta_pdf.php?id=` que ya usa "Ver Detalles" — referencia
  fija, no cambia según lo que se esté subiendo.
- **Panel derecho** ("Acta Firmada"): si el acuerdo YA tenía firma subida,
  arranca mostrándola (`descargar_acta_firmada.php?id=` en un iframe — sirve
  tanto imagen como PDF sin distinguir tipo de antemano); si no, arranca con
  un estado vacío ("Selecciona una foto o PDF..."). Al elegir un archivo
  nuevo, se previsualiza LOCAL antes de subir nada
  (`URL.createObjectURL(archivo)` — `<img>` para imágenes, `<iframe>` para
  PDF, según `archivo.type`) — el usuario ve exactamente lo que está por
  guardar, sin gastar una subida real solo para mirar.
- **Un solo modal sirve para "ver la firma ya subida" y "subir/reemplazar"**
  — mismo componente, cambia el estado inicial del panel derecho según
  `tiene_firma`. El botón "Subir Acta Firmada" en la fila abre este modal
  siempre (ya no dispara el file picker directo).
- **"Guardar Acta Firmada"**: deshabilitado hasta elegir un archivo nuevo (si
  solo se está viendo la firma existente, sin elegir reemplazo, no hay nada
  que guardar). Al hacer click, la petición real recién se manda ahí — antes
  todo es 100% local/sin red.
- **Evita duplicados al guardar (pedido explícito)**: guard `firmaGuardando`
  + ambos botones (`Elegir`/`Guardar`) deshabilitados mientras la petición
  está en vuelo, con texto "Guardando..." — un doble click no dispara una
  segunda subida. Se re-habilitan solo si la respuesta es error; si es éxito,
  el modal se cierra y no hace falta.
- Nuevas clases CSS `.ac-firma-modal*`/`.ac-firma-panel*`/`.ac-firma-preview*`
  en `style.css`, sección aparte después de Historial — reusa
  `.ac-modal-overlay`/`.ac-modal-open` de siempre (mismo mecanismo que el
  modal de Mis Borradores), fondo `#525659` en los paneles de preview (gris
  neutro tipo visor de PDF, no blanco — para que fotos/PDFs con fondo blanco
  se distingan del borde del panel). Responsive: en pantallas angostas los 2
  paneles se apilan verticalmente en vez de lado a lado.

**Probado por el usuario end-to-end (2026-08-21): subida real desde el
navegador, servidor local `localhost:8899` — funcionó.** Acuerdo real 41
(ADN-2026-0038) quedó con `estado='firmado'`, `acta_firmada_mime='image/jpeg'`,
confirmado releyendo con `listar_historial_acuerdos()` (solo lectura).

**2 ajustes visuales de la misma vuelta (2026-08-21):**
- **Vista previa de una foto YA subida salía mal** (pegada arriba, sin
  centrar/ajustar, con espacio gris muerto abajo) — porque el panel derecho
  siempre usaba `<iframe src="descargar_acta_firmada.php?...">` para "ya
  subida", sin importar si era imagen o PDF. Un `<iframe>` mostrando una
  imagen usa el renderizado nativo del navegador (tamaño natural, sin
  centrar) — muy distinto del PDF, donde el visor nativo SÍ centra/ajusta
  solo. Corregido: ahora se distingue por el mime real guardado
  (`acta_firmada_mime`, agregado al SELECT de `listar_historial_acuerdos()`
  y pasado como `data-mime` en el botón) — imagen usa `<img>` (mismo
  `object-fit:contain` que ya usaba la vista previa LOCAL del archivo recién
  elegido, así ambos casos se ven igual de bien), PDF sigue usando
  `<iframe>`.
- **Ícono "Ver Acta Firmada" (`task_alt`) ahora es verde persistente, no
  gris** (pedido explícito: "no lo dejes gris, ponlo verde") — nueva clase
  `.ac-icon-btn-success` en `style.css` (color `#1e5c26`, mismo verde que
  `.ac-badge-ok`, para que el ícono y el badge "Firmada" se lean como la
  misma señal), aplicada solo cuando `tiene_firma` es true.

### Stat tiles = también filtro (2026-08-21, misma vuelta)

3 tiles arriba de la tabla de Historial (pedido con captura de referencia):
"Acuerdos Generados" (total, neutral), "Firmadas" (verde, cuenta + % +
barra) y "Pendientes de Firma" (ámbar, cuenta + fecha de la más antigua
pendiente + barra). **Los 3 son clickeables y funcionan como filtro** —
click en "Firmadas" o "Pendientes de Firma" filtra la tabla a ese
subconjunto; click de nuevo en el que ya está activo vuelve a "todos"
(toggle, nunca queda un estado sin salida). Colores: se reusan los mismos
verde/ámbar que ya usan `.ac-badge-ok`/`.ac-badge-revisar` (no se inventó
paleta nueva — mismo criterio que la skill `dataviz` del proyecto: colores
de estado son señal reservada, se reusan, no se generan por serie).

- `obtener_stats_historial($mysqli, $busqueda, $trimestre, $anio, $usuarioId)`
  (nueva, `functions.php`) — cuenta total/firmadas/pendientes + fecha más
  antigua pendiente, con el MISMO alcance de búsqueda/trimestre/año que ya
  filtra la tabla, pero **sin** el filtro de firma (esos números son
  justamente lo que ese filtro decide, no tendría sentido que el propio
  filtro los redujera).
- `listar_historial_acuerdos()` ganó un parámetro más, `$filtroFirma`
  ('todos'/'firmadas'/'pendientes') — condición armada directo en el SQL
  (no placeholder, ya que es NULL/NOT NULL) y agregada a `$sqlBase`.
- `getters/listar_historial.php` ahora también devuelve `stats` en el JSON
  (mismo request que ya trae las filas — no hay una segunda petición aparte
  para los tiles) y acepta `?firma=`.
- El export "Descargar Excel" **no** hereda este filtro a propósito — es de
  Cuota/Categoría, un concepto de negocio distinto de si el papel ya volvió
  firmado o no.
- **Probado (solo lectura) contra datos reales**: `obtener_stats_historial()`
  da `total=2, firmadas=1, pendientes=1, pendiente_mas_antigua=2026-08-19` —
  coincide exacto con la captura de referencia del usuario (2/1 50%/1 más
  antigua 19/08). `listar_historial_acuerdos()` con cada valor de
  `$filtroFirma` devuelve exactamente la fila esperada en cada caso.
- **Rediseño visual (2026-08-21, mismo día)**: la primera versión usaba el
  fondo lavanda uniforme de `.ac-stat-tile` (el de Liquidación) para las 3 —
  el usuario mostró la captura real y las 3 se veían idénticas/planas. Se
  rediseñaron: base blanca (`--color-surface-container-lowest`) + borde fino
  para las 3, un ícono de acento (`description`/`task_alt`/`schedule`) en
  círculo — el color de estado (verde/ámbar) vive en el ícono, el número/%
  y la barra, no en todo el fondo de la tarjeta (evita que "disponible" y
  "elegida" se confundan). Al estar ACTIVA (filtro aplicado) recién ahí se
  agrega borde de 2px + fondo tintado suave (`#f2fbf6`/`#fffaf0`), para que
  el estado seleccionado se note claramente contra las otras 2.
- **Simplificado más (2026-08-21, mismo día, pedido explícito)**: se quitó
  el `%` de "Firmadas" y el "más antigua: DD/MM" de "Pendientes de Firma" —
  ahora cada tile es solo ícono + label + número + barra, nada de texto
  secundario. `obtener_stats_historial()` sigue devolviendo
  `pendiente_mas_antigua` (no se tocó el backend/SQL, sigue siendo dato
  válido si se necesita después) — solo se dejó de RENDERIZARLO. Clases CSS
  que quedaron sin uso (`.ac-stat-value-row`, `.ac-stat-sub`) se borraron de
  `style.css`.
- **Bug real encontrado 2026-08-21 (mismo día) — el fondo blanco nunca se
  vio, aunque el CSS estaba bien escrito**: los botones tenían las 2 clases
  `ac-stat-tile ac-hist-stat` juntas — `.ac-stat-tile` (Liquidación, fondo
  lavanda `var(--color-surface-container)`) está declarada en `style.css`
  DESPUÉS de `.ac-hist-stat` (fondo blanco), y con la misma especificidad
  (una sola clase cada una) gana la que aparece último en el archivo — el
  lavanda de `.ac-stat-tile` pisaba silenciosamente el blanco. Corregido
  sacando `ac-stat-tile` del HTML (`components/historial/historial.php`):
  `.ac-hist-stat` ya traía su propio `background`/`border`/`border-radius`/
  `padding` completos, no dependía de esa clase compartida para nada.
  **Lección**: nunca asumir que una clase "extra" en un elemento es inerte
  solo porque el elemento ya tiene sus propios estilos — el orden en el
  archivo CSS decide cuál gana si hay props en conflicto entre 2 clases de
  igual especificidad.

**Pendiente (fuera de alcance de esta vuelta, "luego hablamos")**: integrar
esto con la app Android propia de Jabonería Wilson — ver sección de memoria
del proyecto, "Pendiente: subir Acta firmada".

## Vencimiento de firma: 20 días + campanita de alertas (2026-08-25)

Pedido explícito: un Acta 'generado'/'enviado' que pasa **20 días desde
`fecha_generacion`** sin volver firmada se bloquea (ya no se le puede subir
la firma) y desaparece de Historial — mismo efecto visual que "Eliminar"
(`estado='anulado'`), pero con un valor de ENUM **distinto**
(`'vencido'`), a pedido explícito, para poder diferenciar después en
reportes "el usuario canceló" vs. "se venció solo". Además se pidió una
campanita de notificaciones en el header para avisar antes de que se
cumpla el plazo.

**Decisiones confirmadas con el usuario (3 preguntas, no asumidas):**
1. Estado nuevo `'vencido'` en el ENUM (no reusar `'anulado'`) — requiere
   `ALTER TABLE`, ver `datos/vencimiento_firma_schema.sql` (Claude no lo
   corre, solo lectura — pendiente que el usuario lo ejecute).
2. Umbral de aviso de la campanita: **5 días** antes del vencimiento.
3. Alcance: "Mis Actas" (propias, por `creado_por`) para cualquier
   desarrollador/superdesarrollador, **igual que Historial** — pero
   superdesarrollador ADEMÁS ve una sección "Equipo" con **todos** los
   pendientes de **todos** los usuarios (no solo los de 5 días o menos):
   pedido textual "no darle alertas innecesarias sino que tenga seguimiento
   de los usuarios que traen pendientes" — es visibilidad de equipo, no una
   alerta urgente, por eso no lleva umbral de días ni suma al badge.

**Sin cron en este proyecto** (hosting compartido, sin job runner) — en vez
de eso, un **"barrido" lazy** (`barrer_actas_vencidas()`,
`includes/functions.php`) hace `UPDATE ... SET estado='vencido' WHERE
estado IN ('generado','enviado') AND fecha_generacion < CURDATE() -
INTERVAL 20 DAY`, y corre cada vez que se listan Actas (arranque de
`listar_historial_acuerdos()`) o se calculan las alertas de la campanita
(`listar_alertas_firma_propias()`/`listar_equipo_pendientes_firma()`, misma
función). Es un `UPDATE` sin parámetros de usuario (`query()`, no
`prepare()`) — con `MYSQLI_REPORT_OFF` (ver `db_connect.php`) simplemente
no hace nada si el ENUM todavía no tiene `'vencido'`, mismo patrón
defensivo que el resto de columnas nuevas de este archivo (confirmado
corriendo las funciones nuevas contra la base real, sin el `ALTER TABLE`
todavía aplicado: no tira excepción, solo no vence nada).
**Trade-off aceptado**: el bloqueo se aplica la próxima vez que alguien
carga Historial o abre la campanita, no al segundo exacto del día 20 — para
un flujo de firma física en papel, de sobra.

**Bloqueo real en `getters/subir_acta_firmada.php`**: además de rechazar
`estado IN ('borrador','anulado','vencido')` (ya cubría borrador/anulado,
se sumó vencido), hay una **segunda capa** de defensa en el punto más
crítico — si el estado en base todavía dice 'generado'/'enviado' pero ya
pasaron los 20 días (porque nadie visitó Historial desde entonces), este
getter calcula la fecha él mismo, actualiza el registro a 'vencido' ahí
mismo y rechaza la subida, en vez de confiar ciegamente en el barrido lazy
de arriba.

**Consultas nuevas (`includes/functions.php`)**:
- `barrer_actas_vencidas($mysqli)` — el UPDATE de arriba.
- `listar_alertas_firma_propias($mysqli, $usuarioId, $diasUmbral=5)` —
  `generado`/`enviado` de `creado_por=$usuarioId`, con `DATEDIFF(fecha_generacion
  + 20 días, CURDATE())` entre 0 y `$diasUmbral`.
- `listar_equipo_pendientes_firma($mysqli)` — mismo estado, sin filtro de
  usuario ni de días, `GROUP BY` usuario (`repositorio_usuarios_acuerdos`),
  cuenta pendientes + el más próximo a vencer de cada uno.
- Las 3 consultas existentes de Historial que ya excluían `'anulado'`
  (`listar_historial_acuerdos()` ×2, `obtener_stats_historial()`,
  `listar_anios_disponibles()`) ahora excluyen también `'vencido'`.

**Campanita (`getters/alertas_firma.php` + `assets/js/alertas-firma.js` +
`index.php` + `assets/css/style.css`)**: widget global del header (mismo
espíritu que `assets/js/lightbox.js`), ícono + badge junto al avatar del
usuario, dropdown con "Mis Actas por vencer" (todos) y, si el rol es
superdesarrollador, una sección "Equipo — pendientes de firma" debajo (sin
aportar al contador del badge). Sondeo cada 5 minutos desde JS — el plazo
se mide en días, no hace falta más seguido.
**Bug encontrado y corregido en la verificación visual real**: la clase
`.ac-alertas-badge` traía `display:flex` incondicional, que pisaba el
`display:none` que el navegador aplica por default al atributo `hidden`
(misma especificidad, la regla de autor gana) — el badge se veía con "0"
aunque el JS ya le hubiera puesto `hidden`. Se agregó
`.ac-alertas-badge[hidden] { display:none; }` para que el atributo vuelva a
mandar. **Lección repetida en este proyecto** (ya pasó con `.ac-stat-tile`
vs `.ac-hist-stat`, ver más arriba): una clase con una propiedad puesta
"a secas" puede pisar silenciosamente un estado que se controla con
atributos/otra clase — repasar visualmente el estado "oculto", no solo el
"visible".

**Probado**: `php -l`/`node --check` limpios en los 6 archivos tocados;
las 3 funciones nuevas corridas contra la base real (solo lectura) — sin
el `ALTER TABLE` todavía corrido, `barrer_actas_vencidas()` no rompe nada,
y `listar_alertas_firma_propias()`/`listar_equipo_pendientes_firma()` traen
datos reales correctos (ej. 2 usuarios reales con pendientes genuinos,
ninguno todavía dentro de los 5 días de umbral — consistente, el proyecto
recién empezó a generar Actas). Servidor local + sesión falsa (creado con
un `user_id` real de la base para que "Mis Actas" tuviera contexto
verdadero, sin escribir nada) + Playwright: badge oculto correctamente
tras el fix, panel se abre/cierra con el ícono, contenido de "Equipo" con
los 2 usuarios reales confirmado vía `innerHTML`.

**Pendiente**: correr `datos/vencimiento_firma_schema.sql` para que
`'vencido'` exista de verdad en el ENUM — hasta entonces, el barrido y el
bloqueo en tiempo real de `subir_acta_firmada.php` quedan como no-op
silencioso (no rompen nada, pero tampoco vencen nada todavía). No probado
contra el entorno real de Azure, solo mirror local.

### Panel de prueba temporal — `_dev_panel_pruebas.php` (2026-08-25)

El usuario necesitaba probar el vencimiento de 20 días sin esperar de
verdad, pero **su cuenta personal de HeidiSQL no tiene permiso de
escritura** (confirmado por él) — no podía correr él mismo el `UPDATE`
para retroceder una `fecha_generacion` de prueba, y Claude tiene prohibido
ejecutarlo directamente (regla raíz del repo, sin excepción). Solución:
2 archivos nuevos, **TEMPORALES**, para borrar cuando se termine de
probar:
- `Acuerdos_Comerciales/_dev_panel_pruebas.php` — página autónoma (no
  linkeada desde el menú/sidebar), lista los Acuerdos propios en
  `generado`/`enviado`/`vencido` con botones "Aviso (16d)" / "Vencido
  (21d)" / "Revertir".
- `Acuerdos_Comerciales/getters/_dev_simular_vencimiento.php` — el
  `UPDATE` real, con el mismo chequeo de propiedad (`creado_por` =
  sesión) que ya usan `eliminar_acuerdo.php`/`subir_acta_firmada.php`.

**Por qué esto NO viola la regla de solo-lectura de Claude**: el `UPDATE`
lo corre el backend de la app cuando EL USUARIO hace clic, exactamente
igual que cualquier otro botón de escritura que ya existe en este proyecto
(Eliminar, Subir Firma, Guardar Acuerdo) — todos escriben con las
credenciales de `config.php`. Claude nunca invoca el `UPDATE` él mismo
desde un script/consola propia; solo escribió el código que el usuario
dispara.

**Diseño descartado primero, por qué**: la primera versión ponía un botón
de prueba directo en cada fila de Historial (`renderFilaHistorial()`,
`historial.js`, `style.css`) — se revirtió porque una vez que un Acta
pasa a `'vencido'`, Historial deja de mostrarla (a propósito, mismo
criterio que `'anulado'`), así que **no habría quedado ninguna fila
donde hacer clic en "Revertir"**. La página autónoma no depende de la
vista filtrada de Historial, así que sigue mostrando (y permitiendo
revertir) las Actas ya vencidas por la prueba.

**Modo "revertir"**: no puede recuperar la `fecha_generacion` original
exacta — deja el Acuerdo en `estado='generado'` con `fecha_generacion =
CURDATE()` (hoy), suficiente para "destestearlo" y seguir usándolo con
normalidad.

**Cómo usarlo**: entrar a `_dev_panel_pruebas.php` (misma carpeta que
`index.php`) logueado, sin necesidad de tocar HeidiSQL para nada.

**El usuario ya lo usó de verdad**: backdateó el Acuerdo real `id=47`
("ADN-2026-0044", suyo) a 16 días — esto fue clave para encontrar y
verificar los 2 bugs visuales de la sección siguiente, porque generó la
primera alerta real (antes de esto, ningún Acuerdo real estaba dentro del
umbral de 5 días).

## Vencimiento de firma — 2 bugs visuales reales + aviso en la fila de Historial (2026-08-25, misma sesión)

El usuario probó la campanita con una alerta real (ver arriba) y mandó una
captura: el badge mostraba "1" pero el panel de abajo se veía como un
rectángulo prácticamente vacío. Pidió además una marca visual de
vencimiento **directamente en la fila de Historial**, no solo dentro de la
campanita.

**Bug 1 — panel invisible por color de fondo**: `.ac-alertas-panel` usaba
`background: var(--color-surface)`, el MISMO color que `body` (`#fbf8ff`)
— sin contraste real, solo un borde clarito y una sombra suave lo
separaban de la página. Corregido a `--color-surface-container-lowest`
(blanco puro).

**Bug 2 (el real culpable de que casi no se viera nada) — recortado por
`overflow:hidden`**: `.ac-header-inner` tiene `overflow:hidden` a
propósito, agregado por otra sesión como "red de seguridad" contra
superposición de texto en un reflow transitorio (ver el comentario en ese
selector). El panel era `position:absolute` dentro de esa cadena, así que
apenas se salía de los 80px de alto del header quedaba recortado —
literalmente la mayor parte del dropdown no se pintaba. **No se tocó el
`overflow:hidden`** (sigue cumpliendo su propósito original para otra
cosa) — en cambio, el panel pasó a `position:fixed`, posicionado por JS
(`posicionarPanel()` en `assets/js/alertas-firma.js`, calcula
`top`/`right` desde `getBoundingClientRect()` del botón cada vez que se
abre) — `position:fixed` escapa de cualquier `overflow:hidden` de sus
ancestros, sin importar cuántos haya en el medio.

**Aviso visual en Historial** (pedido explícito: "quiero que se marque de
alguna manera visual las que se están expirando"): `renderFilaHistorial()`
(`includes/functions.php`) ya no muestra "Pendiente" a secas siempre — si
el Acta es `generado`/`enviado` sin firmar y quedan **5 días o menos**
(mismo umbral que la campanita), el badge cambia a la cuenta regresiva
real, con 2 niveles de urgencia:
- 2-5 días: naranja, `.ac-badge-urgente` (nueva clase reusable).
- 0-1 día: rojo, `.ac-badge-critico` (nueva clase, reusa los tokens de
  error `--color-error-container`/`--color-on-error-container` que ya
  existían — no un rojo inventado aparte).
- Más de 5 días: sigue diciendo "Pendiente" (amarillo, sin cambios) — no
  hace falta alarmar con tanta anticipación.

La campanita se alineó al mismo esquema de 2 niveles (antes solo tenía 1
nivel "urgente" a partir de 1 día, todo lo demás amarillo) — mismas 2
clases (`.ac-badge-urgente`/`.ac-badge-critico`) en los 2 lugares, un solo
lenguaje visual de urgencia en toda la app.

**Probado**: `php -l`/`node --check` limpios (el bloqueo del clasificador
de la ronda anterior se había levantado). Verificación visual en 2 pasos:
1. HTML sintético (sin tocar la base) cargando el `style.css` real, para
   confirmar que las clases de badge nuevas (`ac-badge-urgente`/
   `ac-badge-critico`) se ven bien en aislamiento.
2. Servidor local + sesión falsa + Playwright contra la app real — SOLO
   lectura (abrir la campanita y Historial dispara `barrer_actas_vencidas()`,
   que es un `UPDATE` idempotente sin efecto sobre datos no vencidos, ya
   veníamos confiando en eso desde la ronda anterior) — confirmando con el
   Acuerdo real que el usuario ya había backdateado (`id=47`,
   "ADN-2026-0044"): el panel se ve completo y legible, "Vence en 4 días"
   en naranja tanto en la campanita como en la fila de Historial.
   **A propósito NO se hizo clic en los botones "Aviso"/"Vencido"/
   "Revertir" del panel de prueba durante esta verificación** — esos sí
   escriben datos reales, y ese control le corresponde al usuario, no a
   Claude en una verificación automática.

## Vencimiento de firma — "Sala de Alertas": concepto en Artifact, aprobado y aplicado entero (2026-08-25, misma sesión)

El usuario pidió explorar creativamente cómo mejorar la dinámica del aviso
de vencimiento ("que parezca como tal una alerta") y cuestionó si la
etiqueta nueva ("Vence en N días") confundía al usuario según Nielsen. Se
armó un Artifact de exploración ("Sala de Alertas", HTML autocontenido,
usando los tokens reales del proyecto — Inter, navy `#00288e`, los mismos
colores de badge ya existentes — no una identidad visual inventada) con
diagnóstico + 5 conceptos interactivos. **El usuario respondió "me parece
perfecto aplica todo"** — se implementó el concepto completo, sin recortar
nada, en esta misma vuelta:

1. **Reetiquetado** (la crítica Nielsen: "Vence" no decía QUÉ vence — se
   podía leer como que el ACUERDO comercial se caía, no que era la ventana
   para subir la foto de la firma). Nuevo texto en los 3 lugares que
   muestran esto — badge de Historial, banner de Historial, y campanita —
   **"Sube la firma — N días"** / **"Sube la firma — hoy"**. La sección
   "Equipo" de la campanita se queda con redacción descriptiva ("vence en N
   días", minúscula, sin imperativo) porque ahí se informa sobre el
   pendiente de OTRO usuario, no se le pide una acción a quien lee.
2. **Franja de color en la fila** (`includes/functions.php`
   `renderFilaHistorial()`, clases `.ac-fila-urgente`/`.ac-fila-critica`) —
   `box-shadow: inset 4px 0 0 0 <color>` en vez de `border-left`: funciona
   igual en el `<tr>` de escritorio que en la "tarjeta" de mobile (grid con
   su propio `border-radius`, ver `#hist-tabla-body tr`) sin robarle padding
   a ninguna celda ni pelear con el box model de los 2 layouts.
3. **Banner en Historial** (`components/historial/historial.php`, nuevo
   `<div id="hist-banner">` entre el header y los stat tiles; lógica en
   `assets/js/historial.js` `cargarBannerVencimiento()`) — reusa
   `getters/alertas_firma.php` (mismo endpoint que ya alimenta la
   campanita, solo la parte "mías"), oculto si no hay nada por vencer. CTA
   dinámico: 1 sola Acta por vencer → "Ver Acta" abre directo esa Acta;
   más de 1 → "Ver todas" aplica el filtro "Pendientes" que ya existía
   (mismo mecanismo que el stat tile). Se llama al entrar al módulo y en
   cada refresh manual — **a propósito NO** en cada tecla de
   búsqueda/paginación (`cargarHistorial()`), sería una consulta HTTP de
   más por cada una.
4. **Notificación al iniciar sesión** — reusa el sistema de toast global
   del proyecto (`assets/js/toast.js`, `window.mostrarToast()`, ya usado en
   Registrar/Gestión de Usuarios/etc.) en vez de construir un componente de
   notificación aparte. Se dispara UNA sola vez por sesión de navegador
   (flag `primeraCarga` en `alertas-firma.js`, no hace falta
   `sessionStorage`: este proyecto renderiza todos los módulos una sola vez
   al loguearse, no hay recargas de página repetidas dentro de la misma
   sesión). Tipo `error` (rojo) si hay algo crítico (0-1 día), `warning`
   (ámbar) si no.
   **Bug real encontrado en la verificación visual**: `.ac-toast-container`
   ya existía con `top: var(--space-lg)` (24px) — ningún toast anterior lo
   había expuesto porque todos disparaban como respuesta a una acción del
   usuario más abajo en la pantalla; este es el primer toast que dispara
   SOLO al cargar la página, con el header (`position:sticky`, 80px de
   alto) siempre en foco — quedaba superpuesto e ilegible sobre el nombre
   de usuario y la campanita. Corregido subiendo el offset a
   `calc(80px + var(--space-md))` — beneficia a CUALQUIER toast futuro que
   dispare con la página recién cargada, no es un parche puntual de esta
   función.
5. **Pulso en la campanita para lo crítico** (`.ac-alertas-badge-critico`,
   anillo `box-shadow` animado) y **respiración sutil en el badge crítico**
   de la fila (`.ac-badge-critico`, `filter:brightness` animado) — SOLO en
   el nivel 0-1 día, a propósito: la urgencia visual escala con el plazo
   real en vez de parpadear parejo desde el primer día de aviso (evita
   fatiga de alerta). Las 2 respetan `prefers-reduced-motion`.

**Bug real, 4to caso del mismo patrón de `[hidden]` (2026-08-26)**: el
usuario reportó el ícono de warning del banner flotando en Historial sin
mostrar nada — `.ac-hist-banner { display:flex; ... }` no tenía la regla
`[hidden] { display:none }`, mismo patrón exacto ya documentado 3 veces
antes en este archivo (`.ac-alertas-badge`, `.ac-stat-tile`/`.ac-hist-stat`,
y el propio comentario "Lección repetida" de la sección de Vencimiento de
Firma). Corregido con `.ac-hist-banner[hidden] { display: none; }`. **Regla
para código nuevo, de acá en más**: cualquier elemento que se oculte con el
atributo `hidden` (no con una clase `.hidden`) necesita su selector
`[hidden]` explícito al lado de la clase que le pone `display`, sin
excepción — no volver a confiar en que el navegador lo va a manejar solo.

**Probado**: `php -l`/`node --check` limpios en los 6 archivos tocados.
Verificación visual completa contra la app real (servidor local + sesión
falsa + Playwright), usando el mismo Acuerdo real ya backdateado por el
usuario (`id=47`, "ADN-2026-0044", 4 días restantes) — confirmado en las 4
superficies a la vez: toast al cargar, banner de Historial, franja +
badge de la fila, y panel de la campanita, todos con el texto y color
nuevos, sin superposición con el header. El nivel crítico (0-1 día) no
tiene ningún dato real todavía (nada bajó de 4 días) — el color/clase se
confirmó por código (mismos tokens ya verificados visualmente en la ronda
anterior para `ac-badge-critico`), no con una captura real de ese estado
puntual.

**Ajuste, mismo día — el toast se sentía "flaquito"**: pedido explícito
tras ver el toast de vencimiento en pantalla. `.ac-toast` tenía padding
angosto (`space-sm space-md`, 8px/16px) pensado para mensajes de 1 línea —
con un mensaje de 2 líneas (como el de vencimiento) quedaba con poco aire
arriba/abajo. Subido a `padding: var(--space-md)` parejo (16px), ícono de
28px a 34px, `line-height` del mensaje de 20px a 21px. **Afecta a los
toasts de TODO el proyecto** (`assets/js/toast.js` es compartido —
Registrar, Gestión de Usuarios, etc.), no solo al de vencimiento: es el
mismo componente en todos lados, a propósito no se creó una variante aparte
solo para este caso.

## Campanita rediseñada a 2 pestañas — se sacó la vista de Equipo (2026-08-25/26)

Pedido explícito: "esa campanita ya no debería existir del lado del
superdesarrollador... esa mecánica de seguimiento de equipo quitémosla" +
"tomá cómo está armado el notification en la carpeta 'diseños ideas' para
usar acá mismo diseño y funcionalidad" (**solo esa parte del mockup de
referencia — nada de cómo armaron el resto de esa página**, aclarado
explícitamente) + "no tenemos una conexión Firebase o algo así" + "por
cada cambio de módulo sea un actualizar para las noti, con un botoncito de
refrescar, para dar la sensación de que la página está siempre en vivo".

**Se sacó `listar_equipo_pendientes_firma()`** (`includes/functions.php`)
y el key `equipo` de `getters/alertas_firma.php` — era la vista agregada
de "quién trae pendientes" solo para superdesarrollador (ver sección
"Vencimiento de firma" más arriba). Reemplazada del todo por el rediseño
de abajo, no quedó ningún resto de esa mecánica.

**Referencia usada — `diseños ideas/code.html`** ("Tabbed Notification
View - Enterprise Dashboard", HTML+Tailwind standalone, con
`diseños ideas/DESIGN.md` documentando su sistema de diseño propio): un
popover de 2 pestañas — **"Activity Feed"** (ítems de alta densidad con
ícono/avatar circular de 40px, separadores finos) y **"System Alerts"**
(cajas con franja de color de 4px a la izquierda según severidad, esquinas
redondeadas solo del lado derecho). El mockup YA traía las 2 pestañas
literalmente tituladas **"Actas asigandas"** / **"Actas Por FIRMAR"** en
español — confirma que el mapeo a este proyecto era: Activity Feed =
Actas Precargadas (Fase 2 del Repositorio de Cuotas, ver sección más abajo
— "una Acta asignada" es exactamente eso, no un concepto nuevo), System
Alerts = Actas por vencer (la sección "Vencimiento de firma" de arriba).
**Se tomó SOLO ese patrón visual + la mecánica de tabs** — colores,
tipografía, sidebar, header del mockup: todo ignorado, se usan los tokens
reales de este proyecto (`--color-primary`, `--color-error-container`,
las mismas clases `ac-badge-urgente`/`ac-badge-critico` que ya existían).

**Implementado**:
- `assets/js/alertas-firma.js` — reescrito. 2 funciones de render
  (`renderAsignadas()`/`renderFirmar()`) en vez de una sola con secciones
  apiladas; `activarTab()` hace el show/hide + clase activa (mismo patrón
  simple que el `<script>` inline del mockup, sin librería). Pestaña "Por
  Firmar" arranca activa por default — mismo default que traía el mockup
  (era la "System" tab la marcada activa en el HTML original, no
  "Activity"), y tiene sentido acá también: es la más urgente de las 2.
- `.ac-activity-item` (`assets/css/style.css`) — fila de Actas Asignadas:
  ícono circular 36px + título + meta (trimestre/año/categorías), borde
  inferior fino entre filas. Sin fecha relativa tipo "hace 2h" — a
  propósito: `repositorio_cuota_cliente` no tiene un timestamp de "cuándo
  se asignó", inventar uno sería fabricar un dato falso.
- `.ac-alertbox`/`.ac-alertbox-urgente`/`.ac-alertbox-critica` — caja de
  Actas Por Firmar: `border-left: 4px` coloreado + fondo tintado suave +
  esquinas redondeadas solo a la derecha, calcado del mockup. Mismos 2
  colores que ya usan `ac-badge-urgente`/`ac-badge-critico` en el resto de
  la app (fila de Historial, banner) — no una paleta nueva para esta caja.
- **Botón de refrescar** (`#acAlertasRefrescarBtn`, ícono `refresh` junto
  al título "Notificaciones") — reusa `acBotonCargando()`
  (`assets/js/cargando.js`, ya usado por "Actualizar" en Historial) para
  el giro del ícono mientras carga, no un componente nuevo.
- **Refresco en cada cambio de módulo** (`index.php`, dentro del listener
  de clicks de `.ac-sidebar-nav`): se agregó `if
  (window.acAlertasFirmaRefrescar) window.acAlertasFirmaRefrescar();`
  **incondicional**, para TODOS los links de sidebar — a diferencia de
  `refrescoPorSeccion` (el mapa de arriba, que solo cubre Historial/
  Gestión de Usuarios/Liquidación/Repositorios), esto corre incluso al
  entrar a Registrar, porque nunca toca el formulario en pantalla, solo
  vuelve a pedir la data de la campanita.
- **Sin Firebase / tiempo real de verdad** (confirmado explícitamente por
  el usuario que no existe esa infraestructura): la "sensación de en vivo"
  la dan 3 cosas combinadas — el sondeo de 5 minutos que ya existía, el
  refresco en cada cambio de módulo (nuevo), y el botón de refrescar
  manual (nuevo) — nunca hay un push real del servidor.

**Probado**: `php -l`/`node --check` limpios en los 4 archivos tocados.
Servidor local + sesión falsa + Playwright contra la app real (solo
lectura): 2 usuarios reales distintos para cubrir los 2 estados de cada
pestaña — `uid=8` (JAVIER MALDONADO, tiene 1 Acta Precargada real de
`repositorio_cuota_cliente`, pestaña "Por Firmar" vacía) y `uid=2`
(ADRIAN VASQUEZ, tiene la Acta ya conocida `#ADN-2026-0044` por vencer,
pestaña "Asignadas" vacía) — confirmado el cambio de pestaña, el contenido
real en cada una, el ícono del botón de refrescar girando mientras carga
y deteniéndose después, y que Historial (banner + badge de fila) sigue
funcionando igual tras sacar `equipo` del getter compartido.

### Bug real: el panel no se podía cerrar + puntito de "no visto" (2026-08-26, mismo módulo)

El usuario reportó "tengo un bug que no puedo cerrar, después que ya abrí
aún sale con el número 1" — se le preguntó para desambiguar (¿el panel no
cierra, o el badge no se limpia?) y confirmó: **el panel de notificaciones
no se cerraba** al hacer clic en la campanita de nuevo ni al hacer clic
afuera.

**Causa — mismo patrón de bug que ya había aparecido una vez con
`.ac-alertas-badge` (ver sección "Vencimiento de firma" más arriba,
2026-08-25)**: `.ac-alertas-panel` tenía `display: flex` puesto "a secas"
en la clase — con la misma especificidad que la regla `[hidden] {
display:none }` que trae el navegador por default, gana la regla de autor.
`panel.hidden = true` (JS) SÍ ponía el atributo, pero el panel se quedaba
`display:flex` igual — "cerrado" en el DOM, pintado en pantalla de
todos modos. Confirmado con Playwright: `{hiddenAttr: true, display:
"flex"}` antes del fix. Corregido con `.ac-alertas-panel[hidden] {
display: none; }`, mismo arreglo de la vez anterior.
**Lección que se repite en este proyecto** (3ra vez ya, contando la del
badge y la de `.ac-stat-tile`/`.ac-hist-stat` mucho antes): cualquier
elemento que se oculta con el atributo `hidden` necesita revisarse contra
CUALQUIER `display` que su propia clase ya traiga puesto sin condición —
no alcanza con confiar en que el atributo por sí solo va a ocultar algo.

**De paso, en el mismo mensaje, pedido nuevo**: "falta que tenga el
puntito al lado de la notificación... que coincida con los numeritos, así
ya marco que la vi" — sistema de visto/no visto:
- **Sin backend real** (no hay tabla de "leídos" ni Firebase, mismo motivo
  que el resto de la campanita) — se guarda en `localStorage`
  (`ac_notif_vistas`, `assets/js/alertas-firma.js`). Es estado **por
  navegador**, no por usuario de verdad: si el mismo usuario entra desde
  otra computadora, ve todo como no visto de nuevo — aceptado, no hay
  infraestructura para más.
- Clave por ítem: "Por Firmar" usa el `id` real de la Acta; "Asignadas" no
  tiene un `id` propio (viene de un `GROUP BY` en SQL), así que se arma la
  clave con `pos_id+trimestre+año`, su identidad real.
- **El badge cuenta NO VISTOS, no el total** — un ítem visto se queda en
  su lista (no desaparece), solo pierde el puntito y deja de sumar al
  número, "para que coincida con los numeritos" tal como se pidió.
- **Se marca todo como visto al ABRIR el panel** (`abrirPanel()` →
  `marcarTodoVisto()`), las 2 pestañas de una sola vez, no ítem por ítem
  ni pestaña por pestaña — mismo criterio simple que describió el usuario
  ("ya marco que la vi" al abrir).
- Puntito (`.ac-notif-dot`, 7px, color `--color-primary`) — un solo color
  en las 2 pestañas, es señal de lectura, no de urgencia (esa la siguen
  dando los colores de `ac-alertbox-urgente`/`-critica`).

**Probado**: `node --check` limpio. Playwright contra la app real: badge
mostraba "1" con el puntito presente antes de abrir; justo al abrir, badge
se oculta y el puntito desaparece (sin volver a pedir datos al servidor,
solo re-render con el estado de `vistas` actualizado); cerrado con la
campanita confirmado con `display:none` real (ya no solo el atributo);
reabierto después, badge se mantiene oculto y sin puntito (persistido en
localStorage entre aperturas).

## Export CSV genérico de Historial — ELIMINADO (2026-08-18)

Hubo una primera versión de export en Historial (`getters/exportar_actas.php`,
botón "Exportar Actas") que sacaba un CSV con las 4 tablas de la Acta en
crudo, sin fórmulas. **El usuario pidió eliminarla** una vez que quedó listo
el export real de "Cuota/Categoría" (ver sección siguiente) — se borró el
archivo y el botón, ya no existe. Si algo en el código o en memoria vieja
todavía lo menciona, está desactualizado: hoy Historial solo tiene el botón
**"Descargar Excel"** de la sección de abajo.

## Export de Cuota/Categoría — .xlsx real con fórmulas vivas (2026-08-18)

**Único botón de export en Historial** (el CSV genérico "Exportar Actas" se
eliminó, ver sección de arriba): **"Descargar Excel"** (`hist-exportar-cuota`)
— replica la hoja "CUOTA CLIENTE - CATEGORÍA" del archivo real de JW
(`datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA 2026.xlsx`) a
partir de lo pactado en las Actas, con **fórmulas de Excel reales** (no
valores ya calculados) — para que cuando JW llene la venta real mes a mes,
todo el resto (cumplimiento, GANA/NO GANA, rebate real) se recalcule solo,
igual que en su plantilla actual. Esta sección describe la parte **canal
Directa** (`getters/exportar_cuota_categoria.php` propiamente); canal
Distribuidor tiene su propia hoja con columnas/colores distintos — ver
"Export CUOTA POR CAT-DISTRIBUIDORES (canal Distribuidor)" más abajo.

**Nombre de la hoja VISIBILIDAD, corregido 2026-08-20**: la hoja 3 se creaba
como `'VISIBILIDAD'` (sin espacio) — pero `includes/liquidacion_import.php`
busca esa hoja al reimportar por el nombre EXACTO `'VISIBILIDAD '` **con un
espacio al final**, porque así está escrito literalmente en el archivo real
de JW (confirmado abriendo `datos/LIQUIDACION ACUERDOS COMERCIALES Q2
DIRECTA 2026.xlsx` real vía Excel COM — no es un typo del importador, es el
nombre real). Sin el espacio, alguien que quisiera re-subir directo nuestro
propio export (sin pasarlo primero por el Excel maestro de JW) se
encontraba con "No se encontró la hoja VISIBILIDAD ". Se agregó el espacio
al `agregarHoja()` de la hoja 3. **Probado el round-trip completo**:
generé el .xlsx real (sesión simulada), y se lo pasé directo a
`liquidacion_parsear_cuota_categoria()`/`liquidacion_parsear_visibilidad()`
— ambas hojas se leen sin error. Mismo nombre exacto ya lo tenía bien el
export de Distribuidor (`'VISIBILIDAD (2)'`, agregado por otra sesión, ver
"Export CUOTA POR CAT-DISTRIBUIDORES" más abajo) — coincide exacto con lo
que busca el importador para ese canal, no hizo falta tocarlo.

**Piezas nuevas:**
- `includes/xlsx_writer.php` — escritor de XLSX propio (sin librería
  externa, mismo motivo que `xlsx_reader.php`). Soporta celdas de
  texto/número/fórmula, negrita, formato moneda y porcentaje, múltiples
  hojas. **Dos gotchas de OOXML reales, encontradas probando contra Excel
  real (COM), documentadas en el archivo**:
  1. Las fórmulas se escriben SIEMPRE en inglés con **coma** como separador
     (`IF`, `SUM`, `VLOOKUP`, `IFERROR`, `AND` — nunca `SI`/`SUMA`/`BUSCARV`/`;`),
     sin importar que Excel las vaya a MOSTRAR en español — el archivo en sí
     nunca lleva texto en español en una fórmula. Por eso las fórmulas que
     dio el usuario (en español, con `;`) se tradujeron a mano en
     `exportar_cuota_categoria.php`.
  2. `CONCAT()` es de Excel 2016 — necesita el prefijo interno `_xlfn.` en el
     XML crudo (`_xlfn.CONCAT(...)`) o Excel tira `#NAME?`. Confirmado
     probando: sin el prefijo fallaba, con el prefijo funciona y Excel lo
     muestra como `CONCAT()` normal. `IF`/`SUM`/`SUBTOTAL`/`VLOOKUP`/`AND`/`IFERROR`
     son de 2007 o antes, no necesitan el prefijo.
- `getters/exportar_cuota_categoria.php` — arma las 2 hojas (`CUOTA CLIENTE
  - CATEGORÍA` + `CUOTA TOTAL`, esta última necesaria para que el `BUSCARV`
  de GANA TOTAL tenga adónde apuntar) y las manda con
  `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.

**Decisiones confirmadas por el usuario:**
- **REBATE A APLICAR %**: se usa el `rebate_pct` ya guardado en la línea de
  la Acta (congelado al momento de generarse) — nunca un valor "vivo" de un
  catálogo externo. Motivo explícito del usuario: si el rebate cambia
  después (ej. de 2% en abril a 4% en mayo), NO debe alterar retroactivamente
  Actas que ya se generaron con el valor viejo — cada Acta es un acuerdo
  cerrado. Esto ya funcionaba así (cada línea guarda su propio
  `rebate_pct`), no hizo falta cambiar nada del guardado, solo confirmar que
  el export lee de ahí y no de otro lado.
- **PLAN**: investigado a fondo contra `repositorio_locales_supervisores_cliente`
  completa (las 14 columnas, incluidas `sector`/`novedad`/`promedio_venta`
  que no estaban documentadas acá — resultaron vacías/sin uso) — el texto
  real que usa JW ("AUTOSERVICIO INDEPENDIENTE", etc.) no aparece en NINGUNA
  columna, ni siquiera para el cliente exacto del ejemplo que dio el usuario
  (`DISTRIBUIDORA SUPERALIANZA S.A.S`, que en nuestra base tiene
  `canal=MAYORISTA`, `tipo_cliente=PLUS` — ninguno de los dos coincide).
  Columna vacía a propósito, no se puede derivar hoy — pendiente de
  preguntarle a JW qué es.
- **Corregido 2026-08-18: una fila por LÍNEA de Meta de Compras, nunca
  agrupada por Sector.** La primera versión sí agrupaba por (Cliente,
  Sector) sumando cuota y tomando el rebate de la primera línea del grupo,
  asumiendo que todas las marcas de un mismo Sector compartían rebate. El
  usuario lo corrigió mostrando el Acta real escaneada
  (`datos/WhatsApp Image 2026-07-23 at 11.09.16.jpeg`, tabla "1. META DE
  COMPRAS"): cada línea (Sector+Categoría+Marca) es un compromiso propio,
  con su propio rebate — aunque dos líneas compartan Sector. Se confirmó con
  un caso real: Acta `ADN-2026-0049`, dos líneas de Sector "PASTAS" con
  4% y 3% de rebate — la versión vieja las fusionaba en una sola fila
  mostrando solo uno de los dos. La columna CATEGORIAS sigue mostrando el
  Sector (así se llama en el archivo real), así que dos filas del mismo
  cliente pueden repetir el mismo texto ahí — es esperado, no error; cada
  una lleva su propia cuota y rebate.
- **Meses dinámicos, no fijos Abril/Mayo/Junio**: igual que Liquidación, las
  columnas de mes se arman según qué meses aparecen de verdad en las Actas
  incluidas — probado con datos reales que eran Enero-Febrero-Marzo y salió
  bien (encabezados "ENERO/FEBRERO/MARZO", no "ABRIL/MAYO/JUNIO" a la
  fuerza).
- **Fila TOTAL**: se leyeron las fórmulas reales de la fila 313 del archivo
  real — todas las columnas numéricas (incluida, rareza del archivo
  original, REBATE A APLICAR %) usan `SUBTOTAL(9, rango)`, no `SUMA` — se
  replicó tal cual, aunque sumar porcentajes no tenga mucho sentido de
  negocio, porque así está en la plantilla real.
- **Botón filtra igual que "Exportar Actas"** (mismos `q`/`mes` de
  Historial) — no se agregó un selector de canal aparte, el endpoint mismo
  filtra `d.canal <> 'DISTRIBUIDOR'` siempre.

**Colores y celda combinada (2026-08-18, pedido aparte del usuario, con
captura del archivo real)**: `xlsx_writer.php` ahora soporta fondo/color de
letra por celda y `combinarCeldas()` — colores tomados EXACTOS del archivo
real vía Excel COM (`Interior.Color`/`Font.Color`, convertidos de BGR a
RGB), no aproximados a ojo: encabezado general `#C0E6F5`/letra negra, bloque
de meses de venta `#61CBF3`/letra roja (con título combinado en la fila 1,
"VENTA REAL" — no "VENTA Q2 2026" fijo, porque nuestro período no siempre
calza con un trimestre calendario), CARTERA `#FFC000`/letra negra/**sin
negrita** (así está en el original, a diferencia de todo lo demás),
columnas de resultado (VENTA Qx/CUMPLIMIENTO/GANA.../PRE REBATE) `#B5E6A2`,
REBATE REAL VOL `#FFFF00`. Probado de nuevo contra producción real: mismos
valores de color exactos, merge funciona.

**Corregido 2026-08-18 (mismo día, el usuario encontró 2 problemas más
mirando el resultado):**
- **"Qx" ahora es dinámico, no fijo "Q2"**: se calcula
  `intdiv(primerMes, 3) + 1` sobre el primer mes real de las Actas
  incluidas — Enero-Marzo da "Q1", Julio-Septiembre da "Q3", etc. Antes
  el texto estaba fijo, copiado literal del archivo real, y aparecía en
  **5 lugares distintos** (se corrigieron de a poco, el usuario encontró el
  primero que se me había pasado): título combinado de venta, encabezado de
  columna "VENTA Qx", encabezado de columna "TOTAL Qx" (cuota pactada), y
  "Suma de TOTAL Qx"/"Suma de VENTA Qx" en la hoja CUOTA TOTAL. Todos usan
  las mismas 2 variables (`$tituloCuota`, `$tituloVenta`), calculadas una
  sola vez al principio — para evitar que quede un sexto lugar con el texto
  fijo si se agrega algo más a esta hoja en el futuro.
- **Ancho de columna automático ("autofit")**: `xlsx_writer.php` no ponía
  ningún ancho de columna — un archivo generado por código no se autoajusta
  solo como cuando lo escribe una persona a mano en Excel, así que todo
  salía con el ancho angosto por defecto. Se agregó `xmlCols()`: calcula el
  largo de texto más largo de cada columna (para números con formato
  moneda/porcentaje, estima el largo ya formateado — ej. "$1,800.00", no el
  número crudo) y arma el `<cols>` del XML con ese ancho + margen, clampeado
  entre 8 y 45 para que ni una columna vacía quede en cero ni un nombre de
  cliente larguísimo deje una columna gigante. Probado: CLIENTE (nombre
  largo) quedó en ~33, CARTERA (vacía) en el mínimo 8, encabezados largos
  como "GANA POR CATEGORÍA" se ensancharon solos — abre sin reparar.
- **Colores en las filas de DATOS, no solo en el encabezado** — leídos
  exactos del archivo real (varias filas, mismo valor siempre): columna
  CLIENTE `#F2CEEF` (rosa/lavanda), y el bloque Cuota mensual + TOTAL Q2 +
  REBATE % `#92D050` (verde) — el resto de columnas de datos van sin color
  (blanco puro en el original). Antes solo la fila de encabezado tenía
  color, las filas de datos salían todas blancas.

**Probado de punta a punta contra producción real** (servidor local `php -S`
+ sesión real, solo lectura del lado de la base): el `.xlsx` generado abre
sin pedir reparar, `CONCAT`/`SUM`/`IFERROR`/`IF`/`VLOOKUP`(a la hoja CUOTA
TOTAL)/`SUBTOTAL` calculan todos correcto, formato moneda/porcentaje
aplicado, fila TOTAL correcta.

### Hoja "VISIBILIDAD" agregada al mismo export (2026-08-19/20)

Mismo archivo/endpoint (`getters/exportar_cuota_categoria.php`), una hoja
nueva `VISIBILIDAD` — **sin tocar `CUOTA CLIENTE - CATEGORÍA` ni `CUOTA
TOTAL`**. Un renglón por CLIENTE (no por línea, a diferencia de la otra
hoja). Mapeo tipo de línea → columna, confirmado con el usuario: `cabecera`
→ CABECERA, `ruma` → ISLA, `percha` → PERCHA.

**Estado actual (después de 2 rondas de corrección del usuario, ver abajo
la primera versión que se descartó):**
- **Regla de "línea con data real"**: una línea cuenta (CANTIDAD) y suma
  (PAGO) si su **TOTAL** (suma de los meses del período, o el valor único
  en `ruma`) es **> 0** — no hace falta que cada mes individual tenga valor,
  basta con que haya llegado algo real a la línea en conjunto.
- **PAGO CABECERA/ISLA/PERCHA son VALORES calculados en PHP**, no fórmulas
  — se suma directo en el backend y se escribe el número. Solo **PAGO
  TOTAL** es fórmula real (`=G+H+I`, references solo celdas de la misma
  fila, igual que el archivo real).
- El grupo de columnas titulado "MARCA" (el título no cambia, así se llama
  en el archivo real) muestra la **CATEGORÍA** de la línea para
  cabecera/ruma (si tienen `categoria` guardada) — `percha` no tiene ese
  campo en el esquema, ahí se sigue mostrando `marca`.
- Encabezados (fila 1 títulos de grupo combinados + fila 2 sub-encabezados
  CABECERA/ISLA/PERCHA repetidos) van **centrados** horizontal y vertical —
  se agregó soporte de `centrado` a `celda()` en `includes/xlsx_writer.php`
  (parámetro nuevo, `<alignment horizontal="center" vertical="center"/>`
  en el `cellXf`, por default `false` así que no afecta nada existente).
- **Colores, corregido 2026-08-20 — bug real en la lectura por COM,
  encontrado y resuelto.** La primera lectura (`Interior.Color` y
  `DisplayFormat.Interior.Color` vía Excel COM, verificada 2 veces) daba
  `#F5E6F5`/`#EFCEEF` (rosa) para el encabezado — el usuario lo objetó con
  una captura real mostrando encabezado AZUL. Se investigó a fondo abriendo
  el `.xlsx` como ZIP y leyendo el XML crudo (`xl/worksheets/sheet5.xml` +
  `xl/styles.xml` + `xl/theme/theme1.xml`): el fill real es una referencia
  a color de TEMA (`theme="4" tint="0.79998..."` para el encabezado,
  `theme="8" tint="0.8"` para Nombres), no un RGB fijo — Excel COM
  aparentemente resuelve mal esta combinación específica de tema+tint en
  este archivo (no se determinó la causa exacta, pero el bug es
  reproducible: da el mismo rosa incorrecto las 2 veces). Resolviendo el
  tema a mano (fórmula de tint de OOXML sobre `accent1`/`accent5` del
  `clrScheme` del archivo) da `≈C1E5F5` (azul) y `≈F2CFEE` (rosa) — **son,
  con diferencia de redondeo, los mismos colores `$bgEncabezado` (`C0E6F5`)
  y `$bgClienteDato` (`F2CEEF`) que ya usa la hoja Cuota/Categoría** — ahora
  `VISIBILIDAD` reusa esas 2 variables en vez de declarar valores propios,
  así las 2 hojas quedan con el azul/rosa exactos y no hay 2 fuentes de
  verdad para el mismo color. **Lección para el futuro**: si hay que leer
  colores de un archivo real de nuevo, si `Interior.Color`/`DisplayFormat`
  da algo que no calza con lo que ve el usuario, no confiar ciegamente en
  COM — abrir el `.xlsx` como ZIP y resolver el tema a mano es más lento
  pero es la fuente de verdad real.
- **PLAN**: investigado contra `repositorio_locales_supervisores_cliente` Y
  `repositorio_locales_dtt2` (deprecada) — ninguna tiene "AUTOSERVICIO
  INDEPENDIENTE" ni nada parecido para los clientes reales de canal
  Directo. Sigue sin poder derivarse, vacía.
- **Fila TOTAL** (columnas CEDI en blanco, "TOTAL" en Nombres): fórmulas
  leídas EXACTAS de la fila 46 del archivo real vía Excel COM —
  CANTIDAD/PAGO usan `SUM(rango)` normal (no `SUBTOTAL`), PAGO TOTAL repite
  el patrón `=G+H+I`, MARCA/VALIDACIÓN/el bloque final Cabecera-Isla-Percha
  quedan en blanco (no se totalizan, así está en el original), y solo la
  columna TOTAL usa `SUBTOTAL(9,rango)` — mezcla inconsistente a propósito,
  replicada tal cual porque así está en la plantilla real.
- VALIDACIÓN (CABECERA/ISLA/PERCHA) siempre vacía — la llena JW a mano. El
  bloque final `IF(VALIDACIÓN="CUMPLE",PAGO,0)` + `SUM(...)` son fórmulas
  reales idénticas a las del archivo real, quedan en $0 hasta que JW valide.
  OBSERVACION vacía. Columnas sin la "KP" del archivo real (columna A rota,
  fórmula `#REF!` sin uso, se omite a propósito).

**Descartado (2026-08-20, el usuario lo objetó al ver el resultado):**
primera versión tenía una hoja auxiliar `VISIBILIDAD DETALLE` (mismo patrón
que `CUOTA TOTAL`, para sostener fórmulas `SUMIF` en vez de valores) y una
regla de "completa" mucho más estricta (TODOS los meses > 0, no solo el
total) que dejaba afuera líneas con data real de sobra (ej. percha con un
mes en 0 entre 3 — el total sí era > 0 pero la línea entera se descartaba).
El usuario corrigió ambas cosas explícitamente: no inventar hojas que no
están en el archivo real, y la regla debe mirar el total, no cada mes.

**Probado de punta a punta contra producción real** (usuario id 8, JAVIER
MALDONADO, solo lectura — corrida directa del getter real vía `include`
con sesión simulada, no una copia de su lógica): 3 hojas (ya no 4), PERCHA
sale con cantidad/pago reales para los 2 clientes de prueba (antes daba 0
por la regla vieja), categoría se muestra en vez de marca para
cabecera/isla, PAGO son valores no fórmulas, encabezados centrados
confirmado con `HorizontalAlignment` vía Excel COM.

**Bordes agregados a las tablas (2026-08-20, pedido del usuario — "también
a las de las otras hojas")**: `includes/xlsx_writer.php` antes declaraba
`<borders count="1"><border/></borders>` (un solo borde vacío, ninguna
celda tenía borde real — `borderId` nunca se emitía en `xmlCellXfs()`).
Se agregó un 2do borde (fino negro, los 4 lados, `indexed="64"`, mismo
estilo "thin" que usa el archivo real de JW) y ahora **toda celda que pasa
por `estiloId()` lo lleva siempre** (`borderId="1"` fijo en todos los `xf`,
no es opt-in por celda) — como este escritor solo arma tablas, no hacía
falta un caso "sin borde" por celda. **Efecto: aplica automáticamente a las
3 hojas** (`CUOTA CLIENTE - CATEGORÍA`, `CUOTA TOTAL`, `VISIBILIDAD`) sin
tocar `getters/exportar_cuota_categoria.php` para nada — el cambio vive
enteramente en el escritor compartido. Probado: XML crudo del archivo
generado confirma `borderId="1"` en las 16 combinaciones de estilo usadas,
y Excel COM confirma `LineStyle`/`Weight` de borde fino real en celdas de
ambas hojas (`CUOTA CLIENTE - CATEGORÍA` y `VISIBILIDAD`).

---
**Resumen para quien lea esto desde la otra sesión (2026-08-19/20, trabajo
en paralelo)** — todo lo de arriba de esta línea sobre la hoja
`VISIBILIDAD` y los bordes es nuevo desde la última vez que se mezclaron
ambas sesiones por `git merge`. En una frase cada uno: (1) se agregó la
hoja `VISIBILIDAD` completa al export de Historial; (2) se corrigió 2
veces a pedido del usuario — se sacó una hoja auxiliar que no debía existir
y se relajó la regla de qué línea "cuenta"; (3) el grupo "MARCA" en
realidad muestra categoría, no marca; (4) se encontró y corrigió un bug de
lectura de color por Excel COM (no confiar en `Interior.Color` a ciegas,
ver nota completa arriba); (5) se agregaron bordes finos a las 3 hojas del
export vía un cambio en `includes/xlsx_writer.php` (afecta a cualquier hoja
nueva que se agregue después con este escritor, ya viene con borde por
default). Si vas a seguir tocando `exportar_cuota_categoria.php` o
`xlsx_writer.php`, leer esta sección completa antes de asumir el estado.

**Bug real encontrado y corregido (2026-08-20): fila 1 del encabezado
quedaba sin pintar en columnas que no eran parte de ninguna fusión.**
`XlsxWriter` solo escribe un `<c>` en el XML para celdas donde se llamó
`celda()` explícitamente — el `borderId` fijo que agregaron los bordes (ver
arriba) solo aplica a celdas que SÍ pasan por `estiloId()`, así que una
celda nunca escrita queda sin borde ni fondo, aunque esté "adentro" de lo
que visualmente debería ser un bloque de encabezado continuo. Antes de este
fix, en la hoja `CUOTA CLIENTE - CATEGORÍA` la fila 1 solo tenía la celda
del título fusionado "VENTA Qx" — las otras ~19 columnas (CEDI...REBATE
MAXIMO 110%, CARTERA, VENTA TOTAL...REBATE REAL VOL) no tenían NINGUNA
celda en fila 1. Mismo problema en la hoja `VISIBILIDAD`: solo las 4
fusiones (CANTIDAD/PAGO/MARCA/VALIDACIÓN) tenían celda en fila 1, las
columnas CEDI/NOMBRES/PLAN, PAGO TOTAL, TOTAL, OBSERVACION y las 3
"validado" sin cabecera de grupo quedaban en blanco arriba. **Confirmado
generando el .xlsx real** (sesión simulada contra producción, solo lectura,
mismo patrón de prueba que el resto de este módulo) **e inspeccionando el
XML crudo** (no a ojo) — encontré exactamente qué columnas faltaban en cada
hoja antes de tocar nada. Arreglado agregando celdas vacías en fila 1 para
esas columnas, con el MISMO color de fondo/letra (y centrado en
VISIBILIDAD) que su celda de fila 2 correspondiente — no se movió ningún
valor de fila ni se agregaron fusiones nuevas, solo se "pintaron" las
celdas que faltaban. Reverificado después del fix: fila 1 de
`CUOTA CLIENTE - CATEGORÍA` cubre las 22 columnas (directas + las 2 que
quedan legítimamente sin `<c>` propio por estar DENTRO de la fusión de
VENTA, eso sí es comportamiento normal de OOXML), y fila 1 de `VISIBILIDAD`
cubre las 21 columnas completas. La hoja `CUOTA TOTAL` (auxiliar, sin
fusiones, encabezado en fila 3 con filas 1-2 vacías a propósito) no tenía
este problema, no se tocó.

### Hoja "RESUMEN DE PAGOS" agregada al mismo export (2026-08-23)

Cuarta hoja de `getters/exportar_cuota_categoria.php` (además de `CUOTA
CLIENTE - CATEGORÍA`, `CUOTA TOTAL`, `VISIBILIDAD `) — confirmada releyendo
el correo original de alcance, ver "⚠️ REPLANTEO 2026-08-23" en "Módulo
Liquidación" para el razonamiento completo. Un renglón por CLIENTE (unión
de los clientes vistos en Cuota y en Visibilidad — un cliente puede tener
solo uno de los dos), columnas **CEDI / CLIENTE / VOLUMEN / VISIBILIDAD /
TOTAL**, mismo nombre y orden que la hoja real de JW.

**A propósito NO replica la hoja real tal cual** — el archivo real de JW
(`datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA 2026.xlsx`) tiene esta
misma hoja pero como una tabla dinámica pegada como VALORES (no fórmulas),
con subtotales por CEDI intercalados entre las filas de datos (rangos de
`SUM` no contiguos tipo `SUM(C4:C24,C26:C31,C33:C47,C50:C69)`, confirmado
leyendo el XML crudo) — mismo criterio que otros artefactos de plantilla ya
descartados en este export (KP, el grupo mal etiquetado de Distribuidor):
acá se armó limpio, un renglón por cliente sin subtotales intercalados, con
fórmulas reales, mismo patrón que ya usa `CUOTA TOTAL`.

**Fórmulas:**
- `VOLUMEN` = `SUMIF('CUOTA CLIENTE - CATEGORÍA'!col_cliente, cliente,
  col_REBATE_REAL_VOL)` — se eligió `REBATE REAL VOL` (el monto ya
  capeado al 110% y filtrado por cumplimiento) y no `REBATE $` (el monto
  bruto sin verificar), porque "Volumen" tiene que representar lo
  realmente ganado, no un techo teórico.
- `VISIBILIDAD` = `IFERROR(VLOOKUP(cliente, 'VISIBILIDAD '!rango, offset a
  la columna TOTAL final, FALSE), 0)` — el `IFERROR` es necesario (un
  cliente puede no tener fila en Visibilidad si solo tiene Meta de
  Compras) — el archivo real usa `VLOOKUP` sin envolver y ahí se ve
  `#N/A` en esos casos.
- `TOTAL` = `VOLUMEN + VISIBILIDAD`.
- Fila final `TOTAL`: `SUM()` de cada columna sobre el rango completo
  (rango simple y contiguo, a diferencia de los subtotales intercalados
  del archivo real).

**Bug real encontrado probando (no a simple vista)**: la fórmula de
`VISIBILIDAD` al principio quedó referenciando la hoja como `'VISIBILIDAD'`
(sin el espacio final) cuando la hoja se creó como `'VISIBILIDAD '` (CON
espacio, ver sección de arriba) — la referencia rota debería dar `#REF!`,
pero el `IFERROR` que envuelve la fórmula **la disfrazaba como un "$0
legítimo"**, indistinguible a simple vista de un resultado correcto (en los
datos de prueba, el $0 esperado también era 0 por otra razón —
`VALIDACIÓN` vacía — así que mirar el resultado en pantalla no alcanzaba
para notar el bug). Encontrado leyendo el XML crudo de la celda
(`<f>IFERROR(VLOOKUP(B2,'VISIBILIDAD'!...` sin el `&apos; &apos;` del
espacio) y corregido. **Lección**: cuando una fórmula está envuelta en
`IFERROR`, no alcanza con mirar que el resultado "se vea razonable" — hay
que verificar la fórmula cruda (XML o `.Formula` de COM), porque
`IFERROR` esconde justo el tipo de bug que se busca encontrar.

**Probado de punta a punta contra producción real** (usuario id 8, JAVIER
MALDONADO, solo lectura, corrida directa del getter real): 4 hojas, abre
sin pedir reparar, fórmula de `VOLUMEN` (`SUMIF`) y de `VISIBILIDAD`
(`VLOOKUP` ya con la referencia corregida) verificadas contra el XML crudo.
Los valores salieron en $0 para los 2 clientes de prueba — **es lo
esperado**, no un bug: es un archivo recién descargado, sin venta ni
validación llenada todavía por JW, así que `REBATE REAL VOL` (depende de
`GANA`, que depende de `CUMPLIMIENTO`, que depende de venta) y el bloque
final de `VISIBILIDAD` (depende de `VALIDACIÓN`, vacía) dan 0 en ambos
casos hasta que JW complete el archivo. **Falta confirmar visualmente con
números reales de venta/validación ya cargados** — no se pudo simular
llenar esos campos desde una prueba de solo lectura.

### Hoja "RESUMEN DE PAGOS" también en el export de Distribuidor (2026-08-23/después)

Mismo concepto que la hoja de Directa (ver arriba) — pedido explícito del
usuario para paridad entre los dos exports ("ponlo para directo y
distribuidor"), aunque **el archivo real de Distribuidor NO tiene una hoja
con ese nombre exacto** (tiene "PRESUPUESTO UTILIZADO" en su lugar, que es
otra cosa — confirmado leyendo `xl/workbook.xml` del archivo real vía
`ZipArchive`, no adivinado; sigue fuera de alcance, no se construyó). Se le
preguntó explícitamente al usuario cuál de las dos quería antes de tocar
nada, para no invertir tiempo en la equivocada.

- 4ta hoja de `getters/exportar_cuota_categoria_distribuidor.php`. Encabezados
  **DISTRIBUIDOR / NOMBRE** (no CEDI/CLIENTE como en Directa) — así se
  llaman esas mismas columnas en el resto de este archivo (hojas de Cuota y
  Visibilidad de Distribuidor), para quedar consistente puertas adentro de
  este workbook, ya que no hay un renglón real que copiar.
- **VOLUMEN** = `SUMIF` contra `REBATE REAL VOL` de `CUOTAS POR
  CAT -DISTRIBUIDORES` (mismo criterio que Directa: lo realmente ganado,
  con el 110% ya capeado, no un techo teórico bruto).
- **VISIBILIDAD** = `IFERROR(VLOOKUP(...), 0)` contra la columna final de
  `VISIBILIDAD (2)` — **ojo**: en Distribuidor esa columna final se llama
  `vdFinTotal` ("PAGO (CAJAS) TOTAL", ya filtrado por
  `VALIDACIÓN="CUMPLE"`), NO `REBATE REAL VOL` — son conceptos análogos
  pero de hojas/nombres distintos entre los dos exports, no confundir si se
  vuelve a tocar.
- Reusa los mismos colores del resto del workbook de Distribuidor
  (`$bgEncD`/`$fontEncD`, gris/blanco) para el encabezado — no los azules
  de Directa, esta hoja no corresponde a ningún renglón real que copiar en
  colores.
- **Probado de punta a punta contra datos reales** (usuario id 2, ADRIAN
  VASQUEZ, canal distribuidor, solo lectura vía `include` con sesión
  simulada — mismo patrón de prueba de siempre): inspeccionado el XML crudo
  de la hoja generada, fórmulas exactas:
  `SUMIF('CUOTAS POR CAT -DISTRIBUIDORES'!$C$3:$C$7,B2,'CUOTAS POR CAT
  -DISTRIBUIDORES'!$U$3:$U$7)` para VOLUMEN,
  `IFERROR(VLOOKUP(B2,'VISIBILIDAD (2)'!$C$3:$R$3,16,FALSE),0)` para
  VISIBILIDAD (offset 16 = columna R menos columna C más 1, confirmado
  matemáticamente correcto), `C2+D2` para TOTAL, fila `TOTAL` final con
  `SUM()` sobre el rango de datos — sin errores, 4 hojas presentes en el
  `.xlsx` generado.

## Export CUOTA POR CAT-DISTRIBUIDORES (canal Distribuidor) (2026-08-20)

Equivalente del export de Cuota/Categoría (sección de arriba) pero para
canal Distribuidor — replica la hoja **"CUOTAS POR CAT -DISTRIBUIDORES"**
del archivo real de JW
(`datos/LIQUIDACION DE ACUERDO COMERCIALES DISTRIBUIDORES Q2 2026.xlsx`).
Antes esto estaba bloqueado en Historial con un toast "próximamente" —
ahora está construido y el bloqueo se sacó (ver "Historial de Acuerdos" más
arriba).

**Piezas nuevas:**
- `getters/exportar_cuota_categoria_distribuidor.php` — archivo APARTE
  (no metido dentro de `exportar_cuota_categoria.php`) para no arriesgar el
  código de Directa ya probado. `getters/exportar_cuota_categoria.php` lo
  incluye (`require` + `exit`) cuando `canalDeSupervisor($mysqli,
  $_SESSION['supervisor'])` del usuario logueado da `'distribuidor'` —
  branch agregado justo después de validar `$usuarioId`, antes de armar la
  query de Directa. Reusa `$mysqli`, `$usuarioId`, `$like`,
  `$trimestreActivo`/`$mesInicioFiltro`/`$mesFinFiltro`/`$anio` ya
  calculados por el archivo padre (mismo filtro de trimestre/año exacto que
  Historial, no un filtro de mes suelto).
- Genera un `.xlsx` de 2 hojas: `CUOTAS POR CAT -DISTRIBUIDORES` (datos) +
  `CUOTA TOTAL` (auxiliar, para el `VLOOKUP` de GANA TOTAL — mismo patrón
  que la de Directa, pero acá el `VLOOKUP` matchea por NOMBRE, no por
  CEDI/ejecutivo).

**Mapeo de columnas contra datos reales — confirmado leyendo el archivo real
vía Excel COM (colores/fórmulas/merges exactos) y cruzando fila por fila
contra la base (2026-08-20), no adivinado:**
- **DISTRIBUIDOR** = `repositorio_locales_supervisores_cliente.tipo_distribuidor`
  (el mismo campo que ya se manda como `empresa_distribuidora` al PDF/vista
  previa — ver "Registrar Acuerdo PDV", `obtener_acuerdo_detalle()`).
- **CIUDAD** = `repositorio_locales_supervisores_cliente.cedi` (confirmado:
  valores reales como SANTO DOMINGO/RIOBAMBA/GUAYAQUIL calzan con los `cedi`
  reales de esa tabla para `canal='DISTRIBUIDOR'`).
- **NOMBRE** = `d.pos_name` (el "Local" de la Acta — mismo campo que CLIENTE
  en la hoja de Directa).
- **CATEGORIA** = `l.sector` de la línea `meta_compra` (mismo campo que
  CATEGORIAS en Directa, misma regla de "una fila por línea real, sin
  agrupar" ya establecida ahí).
- **CODIGO / RUC: NO se incluyen (decisión explícita del usuario,
  2026-08-20).** El archivo real de JW sí tiene esas 2 columnas (confirmado
  leyendo D2/E2 vía Excel COM: celdas propias, no fusionadas, texto exacto
  "CODIGO"/"RUC") — pero como no hay ninguna fuente real de esos datos en la
  base (`repositorio_locales_supervisores_cliente` no tiene columnas `ruc`
  ni `codigo`), el usuario prefirió no mostrarlas en vez de mostrar 2
  columnas siempre vacías. Si se pide agregarlas después, hay que definir
  primero de dónde saldría el dato real (no reintroducir columnas vacías sin
  preguntar).
- Sin columna CARTERA (no existe en el archivo real de Distribuidor) y sin
  fila TOTAL al final (el archivo real tampoco la tiene — confirmado,
  `UsedRange` termina justo en la última fila de datos).

**Diferencias de color/estructura contra la hoja de Directa (a propósito,
así está en el archivo real, no es inconsistencia):**
- Encabezado gris `#747474`/letra blanca para el bloque
  DISTRIBUIDOR...REBATE MAXIMO 110%, negro `#000000`/letra blanca para el
  bloque VENTA...NOVEDADES (Directa usa azul/celeste ahí). Columna NOMBRE en
  rosa `#F2CEEF` (mismo color que CLIENTE en Directa, sí coincide).
- El bloque CUOTA (meses+total+rebate%+rebate$+rebate max) va **sin** color
  de fondo en las filas de datos (blanco) — Directa sí pinta ese bloque de
  verde. Confirmado en el archivo real, no es un fondo "perdido".
- El merge de fila 1 del bloque VENTA (`VENTA Qx`) cubre los meses **y** la
  columna TOTAL VENTA junta (a diferencia de Directa, donde el merge de
  `VENTA Qx` NO incluye su columna de total).
- Mismo bug de "fila 1 sin pintar" ya encontrado y corregido en la hoja de
  Directa (ver sección de arriba) — se aplicó el fix desde el primer commit
  de este archivo nuevo, no hubo que corregirlo después. Verificado
  generando el `.xlsx` real e inspeccionando el XML crudo: fila 1 cubre
  todas las columnas salvo las que son continuación de un merge (eso sí es
  normal en OOXML).
- CUMPLIMIENTO usa `IFERROR(...,0)` acá (el archivo real usa `=R/K` sin
  envoltorio) — decisión propia, mismo criterio ya aplicado en Directa: el
  valor mostrado es idéntico cuando la cuota no es 0, solo evita `#DIV/0!`
  si una Acta quedara con cuota en 0.

**Probado end-to-end contra datos reales** (sesión simulada, solo lectura,
usuario real con Acta canal Distribuidor real — `creado_por=2`,
`ABUNDACORP S A`/`ASERTIA COMERCIAL SA`): generé el `.xlsx`, lo desempaqueté
e inspeccioné el XML crudo — 5 filas de datos (una por línea de Meta de
Compras real), fórmulas y rangos dinámicos correctos (`SUM`, `VLOOKUP`,
`SUMIF` con rangos que crecen/achican según cuántas filas haya, igual que en
Directa), sin celdas de encabezado sin pintar. También reverifiqué que el
branch por canal no rompió el flujo de Directa (usuario canal directo
probado aparte, sigue generando sus 3 hojas normales).

**Fuera de alcance del cambio de arriba** (pedido explícito era solo "CUOTA
POR CAT-DISTRIBUIDORES"): la hoja `PRESUPUESTO UTILIZADO` que también
existe en el archivo real de Distribuidor — si se pide después, mismo
patrón de investigar contra el archivo real antes de construir.

### Hoja "VISIBILIDAD (2)" agregada al mismo archivo (2026-08-20)

Mismo `getters/exportar_cuota_categoria_distribuidor.php`, hoja nueva
`VISIBILIDAD (2)` (así se llama en el archivo real) — sin tocar `CUOTAS POR
CAT -DISTRIBUIDORES` ni `CUOTA TOTAL`. Mismo mapeo de línea → columna que
la hoja de Visibilidad de Directa (`cabecera`→CABECERA, `ruma`→ISLA,
`percha`→PERCHA), un renglón por cliente, misma regla de "cuenta si el
total de la línea es > 0".

**Diferencia importante con Directa — PAGO es fórmula `CANTIDAD × 6`, no
suma de lo pactado en la Acta.** Se investigó fila por fila contra el
archivo real (15+ filas de datos reales, `xl/worksheets/sheetN.xml` crudo)
y el patrón es ×6 sin excepción para las 3 columnas — a diferencia de
Directa (donde el monto pactado varía por cliente/línea, $240/$360/$450...,
y por eso ahí se suma lo real de la Acta). El usuario confirmó
explícitamente que acá el número de PAGO **no representa un monto pactado
por línea como en Directa** — es otra cosa (no se profundizó en qué
exactamente, no hizo falta para implementarlo) — y pidió replicar la
fórmula del archivo real tal cual, no inventar una equivalencia con los
`valores_mensuales` de la Acta. `PAGO_x = CANTIDAD_x * 6` (fórmula real en
la celda), `PAGO TOTAL = suma de los 3`.

**2 bugs de la plantilla real, no replicados** (mismo criterio que ya se
aplicó con la "KP" de Directa — no copiar errores obvios del archivo
original):
- La fila 2 (fila de sub-encabezados) dice "CANTIDAD" arriba del grupo que
  en realidad es VALIDACIÓN (la fila 1, el título de grupo fusionado, sí
  dice "VALIDACIÓN" correctamente — confirmado con los datos reales de esas
  celdas, son texto CUMPLE/NO CUMPLE, no números). Acá se usa "VALIDACIÓN"
  en las dos filas, sin el error de copia.
- Una columna "total" dentro de ese mismo grupo hace `SUM()` sobre celdas
  de texto (CUMPLE/NO CUMPLE) — Excel ignora texto en `SUM`, así que esa
  columna siempre da 0 y no aporta nada. Se omite.
- Además, 3 columnas finales (Cabecera/Isla/Percha, sin encabezado de
  grupo) están completamente vacías en todas las filas de ejemplo del
  archivo real — columnas muertas, se omiten.

**Sin grupo MARCA/CATEGORÍA** — el archivo real de Distribuidor no lo tiene
en esta hoja (a diferencia de Directa).

**Colores**: mismas 2 zonas que ya usa la hoja `CUOTAS POR CAT
-DISTRIBUIDORES` de este archivo — gris `#747474`/letra blanca (identidad +
CANTIDAD + PAGO) y negro `#000000`/letra blanca (VALIDACIÓN + bloque final
+ OBSERVACIONES). Resueltos por el mismo método confiable de XML crudo +
tema (no Excel COM a ciegas, ver la sección de Directa para el porqué) —
confirmado que este archivo usa el mismo `theme1.xml` (`accent`/`lt2` con
los mismos valores) que el de Directa, así que el gris `747474` calculado a
mano coincide exacto con el que ya usaba la hoja de Cuota.

**Probado de punta a punta contra datos reales** (usuario id 2, ADRIAN
VASQUEZ, canal distribuidor confirmado con `canalDeSupervisor()`, solo
lectura — corrida directa del getter real vía `include` con sesión
simulada): cliente real "ABUNDACORP S A" con 5 cabecera + 5 isla + 5 percha
completas → `CANTIDAD` 5/5/5/TOTAL 15 (fórmula `=D3+E3+F3`), `PAGO`
$30/$30/$30 (fórmula `=D3*6`, confirma el ×6), `PAGO TOTAL` $90. Abre sin
pedir reparar, 3 hojas presentes.

## Pendientes / decisiones abiertas (no asumir, preguntar antes de implementar)

- [ ] **⚠️ GRANDE, LEER PRIMERO**: si el mecanismo de subida+matching del
      módulo Liquidación (todo lo que no sea el caso `sin_acta` histórico)
      realmente hace falta, o si el ciclo trimestral normal se resuelve
      100% con "Descargar Excel" sin que JW suba nada de vuelta — ver
      sección "⚠️ REPLANTEO 2026-08-23" dentro de "Módulo Liquidación" más
      abajo para el análisis completo. **Parado, no confirmar con JW por
      ahora** — el usuario pidió explícito 2026-08-31 "Liquidación se
      deja [como está]", no seguir insistiendo en esto — ver "Decisiones
      del usuario sobre varios pendientes abiertos" más abajo. No borrar
      el análisis de arriba, solo dejarlo sin prioridad.
- [x] ~~(Del mismo replanteo) repositorio de REBATE por
      segmento/sector/categoría/marca y repositorio de PARTICIPACIÓN de
      percha — falta la parte que de verdad pidió Michelle: que estos
      valores autocompleten y BLOQUEEN los campos `rebate_pct`/
      `participacion` en Registrar Acuerdo PDV.~~ **Resuelto por completo**:
      Rebate conectado y bloqueando desde 2026-08-27 ("Rebate % conectado
      al repositorio" + rondas de matching tolerante), Participación desde
      2026-08-30 ("Participación de Percha — conectada al repositorio con
      el Excel real"). Ambos ajustados 2026-08-31 para quedar SIEMPRE
      bloqueados, incluso sin match (ver "Rebate % y Participación % —
      bloqueados SIEMPRE, sin excepción" más abajo) — ya no queda ningún
      caso donde el asesor pueda tipear un % a mano.
- [x] ~~(Ídem) repositorio de CUOTAS trimestrales que Michelle subiría para
      que Meta de Compras salga ya lleno/bloqueado al elegir cliente en
      Registrar Acuerdo PDV — pieza grande, no construida.~~ **Resuelto por
      completo** — ver "Repositorio de Cuotas trimestrales + Actas
      precargadas" más abajo (Fase 1+2, construido 2026-08-25 en adelante,
      en uso real en producción) y sus muchas rondas de fixes posteriores
      hasta 2026-08-31 (la más reciente: "Actas Asignadas: bug real —
      'Guardar Borrador' intermedio rompía el marcado de 'usada'"). La
      duda original de la Marca (esa hoja no la trae) también se resolvió:
      2026-08-28 se agregaron columnas opcionales SUBCATEGORIA/MARCA al
      Excel de Cuotas ("Cuotas: SUBCATEGORIA/MARCA opcionales en el Excel
      → autocompletan y bloquean la Acta Precargada") — cuando el Excel
      las trae y matchean, quedan bloqueadas; si no, caen al fallback de
      continuidad con Actas anteriores del cliente o quedan editables,
      exactamente el diseño que se había acordado acá.
- [ ] (Ídem) revisar si el formato `'money'` de "VISIBILIDAD (2)"
      (Distribuidor) es correcto — la reunión confirma que Distribuidor se
      paga en CAJAS, no en dólares.
- [x] ~~Si `superdesarrollador` debería ver TODOS los acuerdos en Historial~~
      **Implementado 2026-08-31** — ver sección "Historial por Canal +
      Excel por formato + 'Ver todo' del superdesarrollador" más abajo.
- [ ] **Portafolio por distribuidor**: los spinners de Segmento/Categoría/
      Marca/Sector hoy muestran TODO el catálogo Wilson (`fabricante =
      'JABONERIA WILSON'`) sin importar qué `pos_id` se eligió — un PDV
      puntual (ej. "AKI RIOBAMBA CENTRO") podría ver segmentos que ese local
      específico nunca vende. Existe una tabla pensada exactamente para esto
      — `repositorio_portafolio_prioritario` (`codigo_pdv`, `categoria`,
      `subcategoria`, `marca`, `sku`) y su variante `lvi_portafolio_prioritario`
      — pero **ambas están vacías (0 filas)** a la fecha (2026-07-23). No se
      puede filtrar por distribuidor hasta que alguien las llene. Mientras
      tanto, mostrar el catálogo completo es la opción segura (filtrar contra
      una tabla vacía mostraría cero opciones, peor problema). Preguntar al
      cliente quién/con qué frecuencia se llenaría esa tabla antes de usarla.
- [x] ~~Nombre exacto de la columna de rebate que se va a agregar a
      `repositorio_productos`.~~ **Obsoleto/superado** — ese plan original
      (agregar una columna de rebate al catálogo de productos) se
      abandonó cuando Rebate pasó a ser su propio repositorio
      (`repositorio_rebate_producto`, ver "Rebate: rediseño — Ciudad+Canal
      reemplazan a Segmento" y siguientes) — nunca se agregó ninguna
      columna a `repositorio_productos`, ni hace falta.
- [ ] Si la cuota del Acta se conecta o no a un archivo/proceso de BI (Trade
      MKT). Respuesta actual del cliente: "no estoy seguro".
- [x] ~~Columna `CARTERA` (cartera vencida) mencionada en las Condiciones
      del Acta — detectada en el Excel real, todavía sin definir dónde se
      guarda.~~ **Resuelto 2026-08-31**: JW la completa ellos mismos, no es
      un dato que este sistema calcule ni guarde — ver sección "Decisiones
      del usuario sobre varios pendientes abiertos" más abajo.
- [x] ~~Paso 5 del proceso original (envío de preliminar al área comercial
      para verificación)~~ **CANCELADO 2026-08-31**, no se construye, ver
      "Decisiones del usuario sobre varios pendientes abiertos" más abajo.
- [x] ~~Si Liquidación debe avisar cuando el rebate de una Acta ya no
      coincide con el valor actual del repositorio de Rebate~~ **Resuelto
      2026-08-31: NO hace falta avisar.** El usuario confirmó explícito que
      el comportamiento actual (congelar el % al generar la Acta, nunca
      compararlo después) es el correcto, con su propio ejemplo: si un
      producto se negoció con cierto Rebate en Q1 y el repositorio cambió
      ese % para Q2, el Q1 ya cerrado no debe verse afectado — es la lógica
      esperada, no un caso a alertar. Ver "Decisiones del usuario sobre
      varios pendientes abiertos" más abajo.
- [x] ~~Si el presupuesto se maneja por PDV individual o por distribuidor
      completo — afecta el diseño de la tabla de liquidación.~~ Resuelto al
      diseñar el schema real de Liquidación (2026-08-17/18, ver sección
      "Módulo Liquidación" abajo): no se guarda ningún "presupuesto" aparte —
      `cuota_total_excel`/`venta_total_excel`/rebate se guardan tal cual
      vienen del Excel, por cliente+categoría, sin distinguir PDV individual
      vs. distribuidor completo como un concepto propio.
- [x] ~~Identificación de PDV al subir Excel: pos_id real o solo nombre~~
      Confirmado con los 2 Excel reales de JW (2026-08-17/18): el Excel
      **nunca** trae `pos_id`, solo nombre truncado + CEDI/DISTRIBUIDOR —
      implementado el matching por `pos_name LIKE 'prefijo%'` con desempate
      por supervisor/tipo_distribuidor, ver "Estrategia de matching" en la
      sección "Módulo Liquidación".

## Módulo Repositorios (2026-08-24)

Dos catálogos self-service (**Rebate** por Segmento/Sector/Categoría/Marca,
**Participación de Percha** por Marca) que Michelle/Gabriela (JW) suben
ellas mismas — pedido real de la reunión del 2026-08-18, ver
`feedback_ask_the_council_schema` y "Pendientes" arriba ("repositorio de
REBATE...", "repositorio de PARTICIPACIÓN de percha..."). **Objetivo final
(NO construido todavía)**: que estos valores autocompleten y BLOQUEEN esos
campos en Registrar Acuerdo PDV (hoy `rebate_pct`/`participacion` se tipean
a mano y son editables) — esta sesión solo construyó el CRUD del
repositorio en sí, la integración con Registrar queda para después.

Sidebar nuevo (`includes/secciones.php`, ícono `inventory_2`), restringido
a `superdesarrollador` — mismo criterio que Liquidación/Gestión de
Usuarios (Registrar necesitaría eventualmente solo LEER estos catálogos
para autocompletar, no subir archivos).

**Empezó como mockup (Claude Design canvas) y se migró a código real en la
misma sesión** a pedido explícito del usuario ("ya no me hagas mockups,
trabaja directamente en nuestro proyecto") — el mockup sirvió para acordar
el layout (2 tabs, tabla+filtros+paginación, flujo de subida) antes de
escribir nada real; no quedó ningún artefacto de ese mockup en el código.

- **Pendiente que el usuario corra `datos/repositorios_schema.sql`**
  (Claude no puede, regla de solo lectura) — crea
  `repositorio_rebate_producto` (segmento/sector/categoria/marca NOT NULL +
  `rebate_pct` DECIMAL(6,4), UNIQUE en las 4 columnas de producto) y
  `repositorio_participacion_percha` (marca NOT NULL UNIQUE +
  `participacion_pct` DECIMAL(5,2)) — ambas con `actualizado_por` (FK
  lógica a `repositorio_usuarios_acuerdos.id`) y `updated_at`. **Nada de
  este módulo funciona en producción hasta correr ese SQL** — verificado
  (solo lectura, `SHOW COLUMNS`) que hoy ninguna de las 2 tablas existe
  todavía; `listar_repositorio_rebate()`/`listar_repositorio_participacion()`
  (`includes/functions.php`) ya devuelven `[]` sin fatal error en ese caso
  (mismo fallback que `listar_usuarios_acuerdos()` para la columna
  `supervisor`), probado contra la base real.
- **"Última Modificación" (quién y cuándo) se guarda pero NO se muestra en
  las tablas visuales** — pedido explícito del usuario ("no quiero que
  salga en las tablas visuales"). Sí sale en el export CSV
  (`getters/repositorio_exportar.php`, columnas "Actualizado por"/"Última
  Modificación") — un archivo descargado no es lo que el usuario llamó
  "tabla visual".
- **Sin lector de xlsx nuevo**: se reusó `includes/xlsx_reader.php` (ya
  existía para Liquidación) agregando un solo helper,
  `xlsx_primera_hoja()` — a diferencia de Liquidación (nombre de hoja fijo,
  conocido de antemano porque es siempre el mismo formato de JW), acá el
  archivo self-service puede traer cualquier nombre de pestaña, así que se
  lee la primera hoja y las columnas se buscan por NOMBRE
  (`includes/repositorio_import.php`, `repositorio_parsear_rebate()`/
  `repositorio_parsear_participacion()`), tolerando variantes como
  "REBATE %"/"REBATE" (mismo criterio que ya usa
  `liquidacion_import.php` para columnas con nombre inconsistente).
  Probado con 2 Excel reales generados vía Excel COM (PowerShell): detecta
  bien la hoja sin importar su nombre, salta filas vacías, y normaliza
  rebate/participación sin importar si el usuario tipeó la celda como
  fracción (0.025) o como número entero de % (2.5) — un valor >1 se asume
  ya en unidades de %, ambos casos dieron el mismo resultado final en la
  prueba.
- **Subida en 2 pasos, ninguno de los dos "valida" visualmente la tabla**
  (pedido explícito: "no le pongas validaciones dentro de las tablas") —
  las celdas de la previsualización son inputs simples editables, sin
  bordes rojos ni mensajes de error por campo:
  1. `getters/repositorio_previsualizar_excel.php` — SOLO parsea el Excel
     subido, nunca toca la base (mismo espíritu que
     `previsualizar_acta_pdf.php`). Devuelve las filas leídas.
  2. El usuario corrige lo que haga falta directo en los inputs de la
     previsualización (`assets/js/repositorios.js`, tabla con
     `.ac-preview-input`) y recién al click en "Guardar" se llama a
     `getters/repositorio_guardar.php`, que sí escribe — UPSERT (`INSERT
     ... ON DUPLICATE KEY UPDATE`) sobre la clave única de cada tabla: un
     producto/marca que ya existe se actualiza, uno nuevo se agrega, y
     **nunca se borra el resto del repositorio** al subir un archivo
     (aunque sea parcial). Filas con campos vacíos se omiten en el guardado
     (no rompen la transacción) y se informa cuántas se omitieron.
  El mismo endpoint `repositorio_guardar.php` también se usa para la
  edición inline de una fila ya guardada (ícono "editar" en la tabla
  principal — convierte la fila en inputs in-place, mismo componente
  visual que la previsualización, sin modal aparte).
- **Eliminar es DELETE físico real**, a diferencia de `repositorio_acuerdos`
  (soft-delete) — es un catálogo de referencia, no un documento de negocio;
  borrar una fila no afecta Actas ya generadas (el rebate/participación se
  copia al valor tipeado en el momento de generar el Acta, nunca queda
  enlazado en vivo al repositorio — eso es justo lo que falta construir
  para el objetivo final de arriba).
- **Búsqueda/paginación**: mismo patrón que
  `listar_historial_acuerdos()`/`listar_usuarios_acuerdos()` (LIKE +
  LIMIT/OFFSET, 10 por página). Export (`repositorio_exportar.php`)
  respeta la búsqueda activa, trae "todo" en una sola pasada (sin paginar
  la descarga).
- **Export CSV o .xlsx real** (2026-08-24, `?formato=csv|xlsx`): el `.xlsx`
  reusa `includes/xlsx_writer.php` (mismo escritor propio sin librería
  externa que ya usa "Descargar Excel" de Historial), sin fórmulas, solo
  celdas con formato `'pct'` — ojo con la unidad: `rebate_pct` ya se guarda
  como fracción (0.025) así que se pasa directo, pero
  `participacion_pct` se guarda como entero (55.00) así que hay que
  dividir /100 antes de pasarlo con formato `'pct'` (ese formato espera
  fracción, no entero). Probado generando el archivo real vía CLI y
  abriéndolo con Excel COM: abre sin pedir reparar. El botón **"Exportar"
  elige el formato SIN modal ni dropdown** — pedido explícito ("no quiero
  otra ventanita, usa animaciones") — se transforma in-place en 2 opciones
  (CSV/Excel) con una animación CSS pura (`grid-template-columns`
  `0fr`→`1fr` para el ancho, sin medir nada por JS, ver `.ac-repo-exportar`
  en `style.css`), se cierra solo al elegir una opción o al hacer click
  afuera (mismo patrón que el panel de combos de `registrar.js`).
- **Probado**: sintaxis PHP/JS de todos los archivos nuevos, `SHOW
  COLUMNS`/consultas reales contra la base (confirmando que el fallback sin
  tablas no rompe), y el parser de Excel con 2 archivos `.xlsx` reales.
  **No probado en navegador** (falta correr el SQL antes de que haya algo
  real que ver) ni el flujo completo de guardado end-to-end (requiere las
  tablas creadas).

### Rebate: el Excel real de JW no usa el vocabulario de la app (2026-08-27)

**⚠️ Superado por la sección siguiente** ("Rebate: rediseño — Ciudad+Canal
reemplazan a Segmento") — el diagnóstico de acá (vocabulario CATEGORIA/
SUBCATEGORIA) sigue siendo válido, pero la parte de "Segmento vacío, avisar
y completar a mano" quedó obsoleta: el usuario confirmó que Segmento
directamente NO EXISTE como concepto en este repositorio — no hace falta
completarlo nunca, se sacó de la tabla. Leer la sección siguiente para el
estado real y vigente.

Primer archivo real subido por el usuario (`datos/RABATE.xlsx`, 55 filas)
tiró "No se encontraron las columnas Segmento, Sector, Categoría y Marca en
el archivo" — no dejaba subir nada. Diagnosticado corriendo
`repositorio_parsear_rebate()` directo contra el archivo real (solo
lectura, sin tocar la base): sus columnas de verdad son **CIUDAD, CANAL,
CATEGORIA, SUBCATEGORIA, MARCA, REBATE** — nada de "Segmento"/"Sector".
`xlsx_encontrar_encabezado()` exigía las 4 columnas exactas de una, así que
ni siquiera llegaba a intentar mapear nada.

- **Mismo swap de vocabulario ya documentado para Meta de Compras en
  Registrar** (ver "Rename de etiquetas Sector/Categoría..." más abajo): lo
  que JW llama **"CATEGORIA" es nuestro Sector**, y lo que llama
  **"SUBCATEGORIA" es nuestra Categoría**. Corregido en
  `repositorio_parsear_rebate()` (`includes/repositorio_import.php`): el
  encabezado ahora se busca solo exigiendo **MARCA** (la única columna
  universal entre las 2 variantes vistas), y Sector/Categoría se resuelven
  cada uno con su propia lista de alias (`SECTOR`→`CATEGORIA` para Sector;
  `SUBCATEGORIA`→`CATEGORIA` para Categoría, saltando la que ya se usó como
  Sector para no tomar la misma columna dos veces).
- **La columna Segmento simplemente no existe en este archivo real** — se
  probó auto-inferirla matcheando Sector+Categoría+Marca contra
  `repositorio_productos` (mismo mecanismo que ya usa
  `resolverSectorReal()` para Cuotas) y el resultado fue malo: de 11
  combinaciones únicas probadas, **solo 1 matcheó** — los nombres del Excel
  de JW no calzan exacto con el catálogo real (`LIQUIDOS` vs `LIQUIDO`,
  `LAVAVAJILLA` vs `LAVAVAJILLAS`, etc.). Se descartó la inferencia por
  poco confiable — en vez de eso, si el archivo no trae columna de
  Segmento, `repositorio_parsear_rebate()` devuelve las filas con
  `segmento: ''` (editable en la previsualización, mismo input libre de
  siempre) más un `aviso` no bloqueante explicando que hay que completarlo
  a mano — `getters/repositorio_previsualizar_excel.php` lo pasa como
  `avisos: [...]` (mismo mecanismo que ya usaba Cuotas para sus avisos,
  `mostrarErroresPreview()` en `repositorios.js`, sin tocar el JS).
  `repositorio_guardar.php` ya rechazaba (con motivo claro) cualquier fila
  sin Segmento — eso no cambió, solo se avisa antes en vez de ser sorpresa
  recién al guardar.
- **Pendiente real, no solo de código**: si esto se repite seguido, lo más
  limpio a mediano plazo es pedirle a JW que agregue una columna Segmento
  al archivo (o unificar los nombres de Sector/Categoría con el catálogo
  real) — completar Segmento a mano en 50+ filas cada vez que suben el
  Excel no escala bien.
- **Probado**: `php -l` limpio en los 2 archivos tocados
  (`includes/repositorio_import.php`, `getters/repositorio_previsualizar_excel.php`).
  Corrido `repositorio_parsear_rebate()` directo contra `datos/RABATE.xlsx`
  (solo lectura) — las 55 filas se leen bien, Sector/Categoría mapeados
  correcto desde CATEGORIA/SUBCATEGORIA, Segmento vacío con el aviso
  esperado. **No probado en navegador** — falta que el usuario confirme que
  la previsualización se ve bien y que puede completar Segmento a mano y
  guardar.

### Rebate: rediseño — Ciudad+Canal reemplazan a Segmento (2026-08-27, mismo día)

El usuario confirmó explícitamente ("nuestro repositorio como tal es ese
Excel... eso que antes decíamos no estaba definido del todo, no debés
basarte en eso" / "nuestro Excel es el veredicto final que es lo que
quieren subir en ese repo") que `datos/RABATE.xlsx` es la fuente de verdad
completa — el diseño anterior con Segmento (2026-08-24) fue una suposición
mía, nunca confirmada con JW, y nunca tuvo filas reales en producción.

**Hallazgo real que motivó el rediseño (no solo el vocabulario)**:
revisando las 55 filas completas del archivo (no solo las primeras),
encontré que **CIUDAD y CANAL cambian el % de Rebate del mismo producto**
— confirmado con los 11 productos únicos del archivo, cada uno con
exactamente 5 filas (DISTRIBUIDOR/TODAS + DIRECTA×4 ciudades), cada una con
su propio %. Ejemplo real: CREMA/LAVAVAJILLA/LAVA da 2.5% en
Distribuidor/Todas, 3.5% en Directa/Manabí-Guayaquil-Santo Domingo, 4.0% en
Directa/Quito. Sin Ciudad+Canal en la clave única, el UPSERT hubiera
pisado 44 de las 55 filas reales entre sí — se lo advertí al usuario antes
de tocar el schema (`AskUserQuestion`, confirmó Ciudad+Canal en la clave).

**Schema — `datos/repositorios_schema.sql` actualizado, `ALTER` (no
`DROP`+`CREATE`, pedido explícito) para que el usuario lo corra**:
```sql
ALTER TABLE repositorio_rebate_producto
	DROP COLUMN segmento,
	ADD COLUMN ciudad VARCHAR(200) NOT NULL AFTER id,
	ADD COLUMN canal VARCHAR(100) NOT NULL AFTER ciudad;

DROP INDEX uq_rebate_producto ON repositorio_rebate_producto;
CREATE UNIQUE INDEX uq_rebate_producto ON repositorio_rebate_producto (ciudad, canal, sector, categoria, marca);
```
Sin riesgo de pérdida de datos — la tabla seguía en 0 filas reales
(confirmado, solo lectura) al momento del rediseño. **Las columnas
`sector`/`categoria` NO se tocaron** — siguen siendo el mismo par de
siempre (`sector`=CATEGORIA del Excel de JW, `categoria`=SUBCATEGORIA), ver
más abajo el rename de ETIQUETA (no de columna).

**Código actualizado, todos verificados con `php -l`/`node --check` y
`repositorio_parsear_rebate()` corrido directo contra `datos/RABATE.xlsx`
(solo lectura, 55 filas, `aviso: null` porque el archivo SÍ trae Ciudad y
Canal)**:
- `includes/repositorio_import.php` — `repositorio_parsear_rebate()` lee
  CIUDAD/CANAL como columnas propias (sin alias, son literales en el
  archivo real); el `aviso` de "falta completar" ahora es sobre Ciudad/
  Canal, no sobre Segmento (que ya ni se busca).
- `getters/repositorio_guardar.php` — INSERT/UPDATE con
  `(ciudad, canal, sector, categoria, marca, rebate_pct, actualizado_por)`;
  validación de campos faltantes incluye Ciudad/Canal, ya no Segmento.
- `includes/functions.php` — `listar_repositorio_rebate()` selecciona/
  busca/ordena por ciudad+canal+sector+categoria+marca.
- `getters/repositorio_eliminados.php`, `getters/repositorio_exportar.php`
  (CSV y `.xlsx`) — mismas columnas nuevas.
- `getters/acuerdo_buscar_rebate.php` (el lookup para Registrar, ver
  sección "Rebate % conectado al repositorio" más arriba) — **ya NO manda
  ni pide Segmento** (nunca existió en esta tabla).
- `assets/js/registrar.js` — `buscarYAplicarRebate(tr, sector, categoria,
  marca)` perdió el parámetro `segmento` (ya no se manda ni se usa).

**Etiquetas visibles: "Categoría"/"Subcategoría", no "Sector"/"Categoría"**
(pedido explícito del usuario, "ellos [JW] quieren ver esa columna" — si
suben un archivo con esos nombres, esperan verlos reflejados, no
traducidos en silencio a nuestro vocabulario interno). **Mismo criterio
exacto ya aplicado en Meta de Compras de Registrar** (ver "Rename de
etiquetas Sector/Categoría..." más abajo): la columna interna sigue
llamándose `sector`/`categoria` en la base y en el código — SOLO cambia el
texto que ve el usuario. Tocado en 3 lugares, todos coordinados:
`CONFIG.rebate.columnas` y `columnasEliminados()` en `repositorios.js`
(`key:'sector'` → `label:'Categoría'`, `key:'categoria'` →
`label:'Subcategoría'`), y los encabezados de export en
`repositorio_exportar.php` (CSV y `.xlsx`). Los mensajes de "falta
completar" de `repositorio_guardar.php` (`$faltantes[]`) también dicen
"Categoría"/"Subcategoría" ahora, para no contradecir lo que se ve en la
tabla.

**Probado**: `php -l` limpio en los archivos tocados. Falta que el usuario
corra el `ALTER` de arriba y suba `RABATE.xlsx` de verdad para confirmar
que las 55 filas se guardan bien y se ven con las etiquetas correctas.

**⚠️ Superado el mismo día — matching de Ciudad/Canal ya resuelto** (ver
sección siguiente, "Rebate: matching de Ciudad/Canal resuelto") — el
usuario pidió avanzar con esto ("sí hazlo") apenas se confirmó que sin
Ciudad/Canal el autocompletado quedaba "sin match" en casi todos los casos
reales. Leer esa sección para el estado real y vigente del lookup.

### Rebate: matching de Ciudad/Canal resuelto (2026-08-27, mismo día)

`getters/acuerdo_buscar_rebate.php` ahora resuelve Ciudad y Canal de
verdad, no solo Sector+Categoría+Marca — con los 5 campos completos el
match es exacto sobre la clave única de la tabla (`ciudad, canal, sector,
categoria, marca`), sin ambigüedad ni degradación a "no encontrado" por
tener 2+ valores.

- **Canal**: mismo criterio que `es_distribuidor` en el resto del proyecto
  — `catalogoDistribuidor.canal === 'distribuidor' ? 'DISTRIBUIDOR' :
  'DIRECTA'` (`assets/js/registrar.js`, `buscarYAplicarRebate()`).
- **Ciudad**: para canal Directo, la `Localidad` (CEDI) del cliente ya
  elegido (`localidadEl.textContent`, el mismo dato que ya se mostraba en
  pantalla y se manda al guardar el Acuerdo) — **confirmado con datos
  reales que esto calza exacto**: los 4 valores de CEDI reales para canal
  `COBERTURA` en `repositorio_locales_supervisores_cliente` son
  `GUAYAQUIL/QUITO/SANTO DOMINGO/MANABI`, IDÉNTICOS a las 4 ciudades del
  Excel real de Rebate — no fue necesario inventar ningún mapeo. Para canal
  Distribuidor, **siempre "TODAS" literal, nunca la ciudad real del
  distribuidor** — confirmado con los datos reales del Excel: las 11 filas
  de canal Distribuidor dicen Ciudad "TODAS" sin excepción, nunca varían
  por ciudad puntual.
- Si no hay Distribuidor/Local elegido todavía, `localidadEl.textContent`
  es `'—'` — no matchea nada, el campo queda editable como cualquier caso
  sin datos, sin necesitar un caso especial en el código.
- **Probado con datos reales de solo lectura** (5 escenarios, sesión
  simulada, cada uno en su propio proceso PHP para evitar que
  `require_once`/`$mysqli` se pisen entre `include()` repetidos del mismo
  getter en un solo proceso — limitación del harness de prueba, no del
  código real):
  - Quito/Directa/Crema/Lavavajilla/Lava → 4.0% ✓
  - Manabí/Directa/Crema/Lavavajilla/Lava → 3.5% ✓
  - Todas/Distribuidor/Crema/Lavavajilla/Lava → 2.5% ✓
  - Quito/Distribuidor/... → sin match ✓ (Distribuidor real es "Todas", no Quito)
  - Cuenca/Directa/... → sin match ✓ (ciudad no cargada en el repositorio)
  - Los 5 casos dieron exactamente el resultado esperado.
**⚠️ Ajustado el mismo día (después de subir `RABATE.xlsx` de verdad) —
match exacto era DEMASIADO estricto, ver "Rebate: matching tolerante"
más abajo.** El match exacto de arriba (UPPER/TRIM sobre los 5 campos tal
cual) verificado con datos SINTÉTICOS (mismo texto en ambos lados) daba
bien, pero contra el cascade REAL de Registrar (que sale de
`repositorio_productos`, no del Excel de JW) fallaba en la mayoría de los
casos por diferencias de texto entre las dos fuentes — mismo síntoma que
ya se había visto antes con Segmento (LIQUIDOS/LIQUIDO). Se agregó
tolerancia — ver la sección siguiente para el estado real y vigente.

### Rebate: matching tolerante — plural/singular + fallback sin Categoría (2026-08-27, mismo día)

Subido `RABATE.xlsx` real y probado en Registrar: el autocompletado seguía
sin encontrar match para productos que sí estaban cargados, porque el
texto de Sector/Categoría que arma el cascade de Meta de Compras (desde
`repositorio_productos`, el catálogo real de Wilson) no siempre coincide
letra por letra con el texto que JW tipeó en su Excel — ej. "LIQUIDOS"
(Excel) vs "LIQUIDO" (catálogo real), o "DETERGENTE" (Excel, para EL
MACHO) vs "ROPA" (catálogo real para ese mismo producto). El match exacto
`UPPER(TRIM(...))` de la versión anterior no toleraba ninguna de estas
diferencias.

**Nueva función `buscarRebateProducto($mysqli, $ciudad, $canal, $sector,
$categoria, $marca)`** en `includes/functions.php`, consumida por
`getters/acuerdo_buscar_rebate.php` (que ahora es un wrapper delgado — solo
lee los `$_GET`, valida sesión/rol, y llama a esta función). Intenta, en
orden, hasta encontrar una fila:
1. Match exacto de los 5 campos (como antes).
2. Variantes de plural/singular de Sector Y Categoría (agregar o quitar una
   "S" final — mismo criterio que ya usa `resolverSectorReal()` para
   Cuotas), probando las 4 combinaciones (sector singular/plural × categoría
   singular/plural).
3. Último recurso: Ciudad+Canal+Sector+Marca **ignorando Categoría** (el
   campo que más varía de nombre entre JW y el catálogo real, ver el caso
   EL MACHO/ROPA) — solo se acepta si da una ÚNICA fila; si hay 2+, es
   genuinamente ambiguo y no se adivina, se responde "sin match" igual que
   siempre.
- **No se implementó todavía, queda pendiente si hace falta**: normalizar
  con un diccionario de sinónimos más amplio (ej. "ROPA" ↔ "DETERGENTE")
  — la heurística de plural/singular + fallback sin Categoría resuelve los
  casos vistos hasta ahora sin necesitar mantener una lista de equivalencias
  a mano.
- **Probado**: `php -l` limpio en `includes/functions.php` y
  `getters/acuerdo_buscar_rebate.php`. **Falta confirmar en navegador** con
  el archivo real ya cargado (`RABATE.xlsx`, 55 filas, subido por el
  usuario) que el autocompletado ahora sí bloquea el campo para los
  productos reales de Registrar — pendiente de que el usuario lo pruebe con
  una fila real de Meta de Compras.

### Blindaje "el sistema se defiende solo" (2026-08-24, misma sesión)

Pedido explícito del usuario: no quiere quedar "detrás" del sistema
resolviendo a mano por qué algo falló — subir un archivo, que se caiga o
genere duplicados sin que quede claro por qué. Agregado sin tocar el
esquema ni sumar dependencias:

- **Normalización de texto** (`repositorio_normalizar_texto()` en
  `includes/repositorio_import.php`, nueva): mayúsculas + espacios de sobra
  colapsados, aplicada a Segmento/Sector/Categoría/Marca en el parser Y de
  nuevo en `repositorio_guardar.php` (defensa doble, cubre también la
  edición inline). Sin esto, "Lavavajillas" y "LAVAVAJILLAS " —mismo
  producto, tipeado distinto— generaban 2 filas separadas en vez de
  actualizar una sola, porque la clave única de la tabla es exacta. Mismo
  criterio de mayúsculas que ya usa `repositorio_productos` real.
- **Rango sano en Rebate/Participación**: `repositorio_guardar.php` ahora
  rechaza (omite + informa el motivo, con el valor recibido) cualquier
  rebate fuera de 0%-100% o participación fuera de 0%-100% — antes se
  guardaba cualquier número tal cual, sin aviso. Importante porque este
  catálogo va a autocompletar Actas reales más adelante (ver objetivo
  pendiente arriba) — un número sin sentido acá se propagaría en silencio.
- **Duplicado DENTRO del mismo archivo** (mismo producto/marca aparece 2+
  veces en la misma subida): se detecta comparando la clave normalizada de
  cada fila contra las ya vistas en esa misma pasada — **sí se guarda**
  (gana el último valor, mismo comportamiento que un upsert normal), pero
  se avisa cuál fila quedó pisada y por cuál, para que no sea sorpresa.
- **Reporte de 2 listas separadas en la respuesta de `repositorio_guardar.php`**:
  `errores` (NO se guardaron — campo vacío, rango inválido, o error real de
  MySQL vía `$stmt->error`, capturado porque `mysqli_report(MYSQLI_REPORT_OFF)`
  en `db_connect.php` hace que `execute()` devuelva bool en vez de tirar
  excepción, así una fila mala no aborta el resto) y `avisos` (SÍ se
  guardaron, pero conviene revisar — hoy solo el caso de duplicado en el
  mismo archivo). El modal de subida (`assets/js/repositorios.js`,
  `mostrarErroresPreview()`) se queda abierto mostrando ambas listas en vez
  de cerrarse solo si hay algo para revisar — reusa `.ac-alert-error` (ya
  existía en la app), no un componente nuevo.
- **Mensajes de subida específicos por causa**
  (`repositorio_previsualizar_excel.php`): antes un solo "falló la subida"
  genérico para cualquier motivo; ahora mapea los códigos reales de
  `$_FILES['archivo']['error']` (archivo muy grande, subida cortada,
  extensión de PHP bloqueándolo, etc.) a un mensaje puntual, más una
  validación de que la extensión del archivo sea `.xlsx` antes de intentar
  leerlo.
- **Probado sin tocar la base** (la lógica de validación no depende de las
  tablas — se probó replicando el mismo chequeo en un script aislado): un
  Excel real con la misma clave tipeada como `"  lava  "` y `"LAVA"`
  normalizó a una sola clave y avisó el duplicado; rebates de -500% y 250%
  se rechazaron con el motivo exacto. **El flujo completo contra la base
  real sigue sin probar** (falta el `CREATE TABLE`, mismo pendiente que
  arriba).

### Modal de subida: padding + barra de carga real (2026-08-24, otra sesión)

- **Bug real de CSS, no de PHP**: el usuario reportó el modal "Subir
  Archivo" apretado, sin padding de los lados ni espacio entre el texto de
  ayuda y el dropzone — dudaba si era "un bug con los archivos PHP". Causa:
  `.ac-modal-body`/`.ac-modal-footer` (las clases que este modal usa) **nunca
  tuvieron ninguna regla en `style.css`** — ningún otro modal de la app las
  usa (cada uno pone su padding a mano en un div/form hijo propio). Se
  agregó estilo real a ambas clases (`padding`, `gap` entre hijos en el
  body, `border-top` + `justify-content:flex-end` en el footer) —
  corrección genérica, cubre automáticamente cualquier otro modal futuro
  que use estas 2 clases. Verificado renderizando el HTML real del
  componente contra el CSS real (sin servidor ni login) y comparando contra
  la captura del usuario. Guardado como lección en memoria
  (`feedback_acuerdos_comerciales`) — sospechar primero de una clase CSS
  sin estilo ante "esto se ve apretado", no de un bug de lógica PHP.
- **Límite de 10MB: se probó agregar y se REVIRTIÓ a pedido explícito**
  ("no limites la subida"). El texto ".xlsx — máximo 10 MB" del dropzone
  prometía un límite que en realidad **nunca estuvo respaldado por ningún
  chequeo real** (solo por `upload_max_filesize`/`post_max_size` del
  servidor, que aplican de todos modos y no dependen de este código) — se
  sacó esa mención del texto para no prometer algo que no se cumple.
- **Barra de carga real agregada en su lugar** (`assets/js/repositorios.js`,
  `previsualizarArchivo()` reescrita de `fetch()` a `XMLHttpRequest` —
  `fetch()` no expone progreso de subida, hacía falta XHR para el % real
  vía `xhr.upload.addEventListener('progress', ...)`). El dropzone se
  oculta y aparece una barra con `%` en vivo mientras sube; vuelve a
  mostrarse el dropzone al terminar (éxito o error) o si se reabre el modal
  a mitad de una subida anterior (`mostrarPasoElegir()` llama
  `ocultarProgresoCarga()`). Nuevas clases `.ac-progreso-carga*` en
  `style.css`, mismo `--color-primary` que el resto de la app. **Probado**:
  sintaxis JS limpia, y visualmente renderizando el HTML real con la barra
  al 63% contra el CSS real — se ve bien. No se pudo probar una subida real
  de un archivo pesado end-to-end (requiere el módulo funcionando en
  producción, con el schema corrido).
- **Mismo arreglo replicado en el modal "Subir Excel de Liquidación"**
  (2026-08-24, pedido explícito: "meter ese arreglo acá en el módulo de
  liquidación"): ese modal no tenía el bug de padding (el `<form>` ya usa
  `style="padding:..."` inline, no las clases `.ac-modal-body`/
  `.ac-modal-footer`), pero sí le faltaba la barra de carga real — el envío
  usaba `fetch()` sin ninguna señal de avance. `components/liquidacion/
  liquidacion.php` ganó el mismo bloque `.ac-progreso-carga` (reutiliza las
  clases CSS ya creadas para Repositorios, no se duplicó nada) y `assets/js/
  liquidacion.js` reescribió el `submit` del form de `fetch()` a
  `XMLHttpRequest` con `xhr.upload.addEventListener('progress', ...)`,
  mismo patrón (`mostrarProgresoSubirLiq()`/`ocultarProgresoSubirLiq()`).
  Bug encontrado y corregido en el camino: la barra quedaba pegada al botón
  "Procesar Excel" porque `.ac-form .ac-field { margin-bottom: ... }` no
  aplica a un div que no es `.ac-field` — se agregó `.ac-form
  .ac-progreso-carga { margin-bottom: var(--space-md); }` en `style.css`.
  Verificado igual que en Repositorios: JS syntax-checked (`node -c`) y
  visualmente contra el CSS real vía Playwright.

### "71 de 71 clientes en Revisar" en Resumen de Pagos — 1 bug real + 1 falso positivo (2026-08-24)

El usuario mostró un screenshot del Resumen de Pagos (Directa, Q2 2026, 1
importación) con **71 clientes y 71 "Por revisar"** — el 100%. Se investigó
en 2 partes, verificando con datos reales vía script de solo lectura
(`php.exe` está en `C:\xampp\php\php.exe`, no en el PATH):

1. **Bug real, corregido**: `liquidacion_calcular_resumen_pagos()` en
   `includes/liquidacion_import.php` (2 queries, tablas
   `repositorio_liquidacion_cuota_categoria` y `..._visibilidad`) calculaba
   `sin_resolver` como `SUM(estado_match <> 'matcheado')` — eso cuenta como
   "sin resolver" también las filas resueltas a propósito como `'sin_acta'`
   (flujo "No tiene Acta (dato histórico)", ver `liquidacion_resolver_match.php`),
   que YA están resueltas y no deberían pedir revisión nunca más. Corregido a
   `SUM(estado_match NOT IN ('matcheado', 'sin_acta'))` en ambas queries —
   mismo criterio que ya usaba `liquidacion_pendientes.php` línea 51. Impacto
   real en la importación #4 (la del screenshot): solo 1 fila de 310 tenía
   `estado_match='sin_acta'`, así que este fix por sí solo NO explica el
   71/71 — pero sigue siendo un bug real y hay que tenerlo corregido para
   cuando haya más backfills históricos.
2. **NO es un bug — es esperado dado el estado actual de los datos**: se
   verificó, fila por fila, en qué paso fallaba el match de las 309 filas
   restantes de la importación #4 (`liquidacion_candidatos_pos_id()` /
   `liquidacion_candidatos_acuerdo_id()`, ambas de solo lectura). Resultado:
   **las 309 resolvieron su pos_id sin problema** (0 fallaron ahí) — fallaron
   las 309 en el segundo paso, `liquidacion_candidatos_acuerdo_id()`, porque
   **casi no hay Actas generadas para Q2 2026 todavía** (`SELECT COUNT(*)
   FROM repositorio_acuerdos WHERE anio=2026 AND mes_inicio<=5 AND
   mes_fin>=3` → solo 2 en estado `generado` + 1 `borrador`, para TODOS los
   clientes). Esta importación de Liquidación se subió antes de que existan
   las Actas de ese trimestre para la mayoría de clientes, así que no hay
   nada contra qué matchear — el sistema está funcionando correctamente al
   mostrarlos como "Revisar" (ninguno tiene Acta con la que vincularse
   todavía, no es que el matching esté roto). Esto es la misma preocupación
   de fondo que ya está documentada en "⚠️ REPLANTEO 2026-08-23" más abajo:
   el orden real Excel-vs-Actas en el proceso de JW sigue sin confirmarse.
   **Si vuelve a aparecer un caso así, antes de asumir que es un bug de
   matching, verificar primero cuántas Actas existen para ese
   canal+trimestre+año** — puede ser simplemente que todavía no se generaron.

### Resumen de Pagos: stats/filtros/gráfico pegados al borde de la tarjeta (2026-08-24)

Bug real de CSS, distinto del anterior: `.ac-card` no trae padding propio
(el padding vive en `.ac-card-header`, o en las celdas de `.ac-table` para
tablas) — `.ac-resumen-stats`, `.ac-resumen-filtros` y `.ac-resumen-chart-wrap`
son contenido libre (no tabla, no header) que cuelga directo de `.ac-card`,
así que sin margen horizontal propio quedaban pegados al borde de la
tarjeta. Corregido en `style.css`: los 3 ganaron `margin` horizontal
`var(--space-lg)` (antes solo tenían margen vertical). La tabla de abajo
(`.ac-table-scroll`) NO se tocó — esa ya se ve bien porque el padding vive
en las celdas (`th`/`td`), mismo patrón que el resto de la app. Verificado
visualmente contra el CSS real vía Playwright (antes/después). Mismo tipo
de bug que el de `.ac-modal-body`/`.ac-modal-footer` de Repositorios de
esta misma sesión — patrón a repetir si aparece de nuevo: contenido que NO
es tabla ni header dentro de un `.ac-card` necesita su propio padding/margen
horizontal, `.ac-card` nunca lo da gratis.

## Repositorios: previsualización estilo Excel, ancho auto-ajustado de verdad (2026-08-25, 2 vueltas)

Pedido explícito con captura real de referencia: la tabla de previsualización
del modal "Subir Archivo" (paso 2, antes de guardar) se veía amontonada —
todas las columnas al mismo ancho angosto (~90px), texto largo cortado sin
ningún indicio. **Nota: este cambio es transversal a los 3 tipos de
repositorio (Rebate, Participación, Cuotas) porque toca `CONFIG` y el CSS
compartido — pero es puramente aditivo/de layout (no cambia ninguna lógica
de guardado/match) y no toca nada de la sección "Repositorio de Cuotas
trimestrales..." (en construcción por otra sesión en paralelo, ver más
abajo) — solo sus arrays de columnas.**

**1ra vuelta (descartada): `anchoPct` — porcentaje fijo adivinado a mano
por columna, con `table-layout: fixed`.** Mejoró el amontonamiento pero el
usuario la rechazó explícitamente al ver una hoja real con más columnas de
las previstas (Mes 1/Mes 2/Mes 3 del trimestre, agregadas mientras tanto
por la otra sesión que construye Cuotas): *"esto no es nada inteligente, no
detecta el espacio que debe darle a cada celda"* — los % estaban pensados
para una cantidad fija de columnas, no se adaptaban si cambiaba cuántas
había ni si el contenido real no calzaba con lo que se supuso.

**2da vuelta (la que quedó): ancho REAL auto-ajustado, sin adivinar nada.**
- **Causa real del problema original** (esto seguía siendo cierto): cada
  celda es un `<input>` con `width:100%` — en `table-layout:auto` (default
  del navegador), la columna mide su contenido MÁS CHICO, y un input sin
  `size` pide solo su ancho mínimo, sin importar si el dato real es "BARRA"
  o 34 caracteres.
- **La solución de verdad**: sacar `table-layout: fixed` (volver al `auto`
  del navegador) y ponerle a cada `<input>` el atributo HTML `size`
  (**no** `width` de CSS) calculado en JS del largo real de SU valor
  (`tamanoInput()` en `repositorios.js`, clamp 4-40 caracteres). Con `size`,
  el motor de layout de tablas mide el ancho natural del input como
  mediría cualquier texto, y ensancha la columna entera hasta la celda más
  ancha (`<th>` incluido, que se mide solo, sin tocar nada) — es el MISMO
  mecanismo que usa Excel para autoajustar una columna, no una
  aproximación. La propiedad `anchoPct` se sacó de las 4 configuraciones
  (Rebate, Participación, `columnasPreview`/`columnas` de Cuotas) — quedó
  100% muerta tras este cambio, confirmado con un `grep` antes de borrarla.
- **Altura de fila reducida** (la 2da queja, "hay espacio en blanco que se
  puede reducir"): el padding de la celda Y el padding del input se
  sumaban — ambos se achicaron (`padding:4px var(--space-xs)` en la celda,
  `padding:5px 6px` en el input).
- **Consecuencia esperada, no un bug**: con 7 columnas reales (Cliente
  largo + CEDI + Plan + Categoría + 3 meses), el ancho natural total puede
  superar el ancho del modal (1100px) — en ese caso la tabla se desborda
  DENTRO de `.ac-preview-table-scroll` (`overflow-x:auto`, ya existía) y
  hace falta scroll horizontal para ver las últimas columnas — mismo
  patrón que ya usan las tablas anchas de Registrar Acuerdo PDV. Ningún
  dato se pierde ni se recorta, solo hay que scrollear para verlo, igual
  que en Excel real cuando hay más columnas anchas que pantalla.
- **Verificado con Playwright real** (`npx playwright screenshot` +
  `page.evaluate()` para forzar el scroll y confirmar que Mes 2/Mes 3
  siguen ahí, no se perdieron — `scrollWidth` 1372px vs `clientWidth`
  1050px en la prueba, confirma que el desborde es real y el scroll
  funciona): Cliente/CEDI/Plan/Categoría entran completos sin cortar en la
  vista inicial, Mes 2/Mes 3 aparecen correctos al scrollear a la derecha.

**3ra vuelta — orden de columnas + arrastre con mouse (2026-08-25, misma
sesión de feedback):**
- **Orden CEDI antes que Cliente**: pedido por el usuario mostrando una
  captura donde el orden era Cliente/CEDI — **resuelto por otra sesión en
  paralelo antes de que llegara a tocarlo** (`columnasPreview` de Cuotas ya
  quedó CEDI/Cliente/Plan/Categoría/meses, "mismo orden que trae el Excel
  real", confirmado leyendo el archivo actual). No hizo falta ningún cambio acá.
- **Arrastre horizontal con mouse tipo touch**: pedido explícito — con el
  ancho auto-ajustado la tabla puede quedar más ancha que el modal (ver
  arriba) y el scrollbar nativo del navegador solo se ve/alcanza pegado
  abajo del todo, no arriba. Se agregó "drag-to-scroll": mantener click y
  mover el mouse desplaza la tabla horizontalmente, sin depender de
  encontrar el scrollbar — mismo gesto que un swipe táctil.
  - `activarArrastreScroll()` nueva en `repositorios.js`, activada sobre
    `.ac-preview-table-scroll` — `mousedown` arranca el arrastre (excepto
    si el click empezó en `input/button/a/select/textarea`, para no romper
    el foco normal al editar una celda), `mousemove` mueve `scrollLeft`
    según el delta del mouse, `mouseup` **en `document`, no en el
    contenedor** (si se soltara afuera del contenedor mientras se arrastra,
    un listener solo en el contenedor no dispararía y el arrastre quedaría
    "pegado").
  - CSS: cursor `grab` en reposo, `grabbing` + `user-select:none` mientras
    arrastra (clase `.ac-arrastrando`, toggleada por el JS) — sin el
    `user-select:none`, cada arrastre seleccionaría el texto de las celdas
    en vez de mover la tabla.
  - **Verificado con Playwright real** (simulando `mouse.down()` →
    `mouse.move()` en pasos → `mouse.up()`, no a ojo): un arrastre de 150px
    movió `scrollLeft` esos mismos 150px. Reverificado aparte que hacer
    click normal en un input SIGUE enfocándolo correctamente (el guard de
    `input/button/...` no rompió la edición).
  - Alcance: solo `.ac-preview-table-scroll` (la tabla de previsualización)
    por ahora — si se pide extenderlo a otras tablas anchas de la app
    (Registrar, etc.), se puede reusar la misma función tal cual.
  - **Efecto secundario real, corregido en el momento**: el arrastre movía
    bastante el mouse, y si el `mouseup` terminaba cayendo justo sobre el
    fondo oscuro del `#repo-subir-modal-overlay`, el `click` nativo del
    navegador disparaba el listener de "cerrar modal al hacer click afuera"
    (`subirOverlay.addEventListener('click', ...)`) — el usuario perdía el
    modal (y lo que ya había corregido en la previsualización) por un click
    accidental que en realidad era el final de un arrastre. **Se sacó ese
    listener entero** — este modal específico ya no se cierra por click
    afuera, solo por la "X" (`repo-subir-modal-close`) o "Cancelar"
    (`repo-subir-cancelar`), ambos intactos.

**4ta vuelta — modal reactivo al ancho real de la tabla (2026-08-25, misma
sesión de feedback):** pedido explícito, con pregunta directa de si era
buena idea — se confirmó que sí y se explicó por qué antes de tocar código.
Antes `.ac-repo-subir-modal-ancho` era un `max-width: 1100px` fijo, así que
Participación (solo 2 columnas: Marca + Participación %) se veía con un
modal igual de ancho que Cuotas (hasta 7 columnas), con un montón de
espacio vacío al costado sin necesidad.

- **Arreglado en `style.css`, una sola regla, sin JS**: `width: fit-content`
  (el modal se ajusta al ancho real de su contenido — la tabla, que ya mide
  sus columnas por contenido real desde la vuelta anterior de este mismo
  cambio) en vez de `width: 100%` heredado de `.ac-modal` genérica.
  `min-width: 480px` para que una tabla de 2 columnas cortas no quede
  incómodamente apretada; `max-width: min(1300px, 95vw)` sigue de techo —
  nunca más ancho que la pantalla, y una tabla que lo supera cae al scroll
  horizontal interno de siempre (mismo mecanismo ya verificado antes).
- **Verificado con Playwright real** (no a ojo): 2 modales lado a lado, uno
  con la tabla de Participación (2 columnas) y otro con Cuotas (7 columnas,
  nombres reales largos) — el primero salió notablemente angosto ajustado a
  su contenido, el segundo se ensanchó solo para que las 7 columnas entraran
  cómodas, sin necesitar scroll en el viewport de prueba (1400px).

## Paginación arriba Y abajo de la tabla, en las 3 listas (2026-08-25)

Pedido explícito: "al darle click a la página 2... tengo de nuevo bajar para
poder cambiar de página" — con la paginación solo abajo, cambiar de página
más de una vez seguida obliga a bajar el scroll cada vez para volver a
encontrar los controles. **Se preguntó explícitamente al usuario si aplicar
esto en las 3 listas paginadas de la app (Historial, Repositorios, Gestión
de Usuarios) o solo en la que estaba probando — eligió las 3**, mismo
patrón en toda la app.

**Mismo cambio mecánico en los 3 pares componente+JS** (`historial.php`/
`historial.js`, `repositorios.php`/`repositorios.js`,
`gestion-usuarios.php`/`gestion-usuarios.js`):
- HTML: se duplicó el bloque `.ac-pagination` (info + botones) justo antes
  de `.ac-table-scroll`, con IDs `-top` nuevos (ej. `hist-paginacion-info-top`)
  — el bloque de abajo, con sus IDs originales, no se tocó.
- JS: los `var` que apuntaban a un solo elemento (`paginacionInfo`,
  `paginacionBtns`) pasaron a ser arrays de 2 elementos
  (`paginacionInfoEls`, `paginacionBtnsEls`); `renderPaginacion()`/
  `renderPaginacionBtns()` ahora escriben el mismo HTML y enganchan los
  mismos listeners en ambos contenedores con un `.forEach()`, en vez de
  uno solo. **El estado (`data-pagina`/`data-total-paginas`, leído en
  varios lugares para saber "en qué página estoy")** sigue viviendo
  SOLO en el elemento de ABAJO (`paginacionEl`, sin cambios) — el de
  arriba es puramente visual, nunca se lee su `dataset`.
- CSS (`style.css`): nueva clase `.ac-pagination-top` — mismo bloque que
  `.ac-pagination`, pero el borde divisor se invierte (`border-bottom` en
  vez de `border-top`) porque ahora separa de lo que sigue (la tabla), no
  de lo que ya pasó.
- **Verificado con Playwright real** (no a ojo): se armó un HTML de prueba
  con el patrón exacto (card con paginación arriba, tabla, paginación
  abajo) contra el `style.css` real — el resultado visual calza con lo
  esperado, bordes/esquinas del `.ac-card` intactos, ambas barras
  idénticas en contenido.
- Confirmado que no quedó ninguna referencia viva a los `var` viejos
  (`paginacionInfo`/`paginacionBtns` singulares) en los 3 archivos JS
  tocados — se buscó explícito antes de dar el cambio por terminado.

## Responsive / mobile (2026-08-25)

Pedido explícito del usuario: "todo el proyecto sea responsivo, que sea
para móvil y se vea super bien", con prioridad especial en que Registrar
Acuerdo PDV se pueda tipear y ver bien. Antes de esto el proyecto no tenía
ninguna estrategia sistemática — el shell global (sidebar/header) no tenía
NINGÚN breakpoint, y solo 7 `@media` sueltos cubrían componentes puntuales
(Gestión de Usuarios, Historial, modal de Firma, gráfico de Resumen de
Pagos), ninguno tocaba el shell ni Registrar. Plan completo en
`C:\Users\diego\.claude\plans\stateless-bouncing-boole.md` si hace falta el
detalle completo de la investigación previa (3 agentes Explore).

**Decisión confirmada con el usuario** (AskUserQuestion): las 4 tablas
anchas de Registrar (Meta de Compras/Cabeceras/Rumas/Perchas) mantienen el
scroll horizontal existente (ya tenían columnas sticky + `.ac-table-scroll`)
en vez de reescribirse como tarjetas apiladas — mucho menor riesgo, no se
toca la lógica de cálculo de plata de esas 4 tablas.

Breakpoint reusado: **900px** (ya era el de `.ac-users-grid` y el modal de
Firma) para todo lo nuevo del shell — no se inventó un breakpoint nuevo.
Todo el trabajo es CSS + JS de UI, cero cambios de SQL/getters/cálculos.

- **Shell global → drawer off-canvas bajo 900px** (`index.php`,
  `assets/css/style.css` sección "Shell autenticado"/"Sidebar mobile"): la
  sidebar (antes fija 280px, sin ningún tratamiento mobile — en celular
  simplemente empujaba el contenido) pasa a `position:fixed` con
  `left:-280px` → `.open { left:0 }`, más un `.ac-sidebar-backdrop` nuevo.
  Hamburguesa nueva (`.ac-header-menu-btn`, `#acHeaderMenuBtn`) a la
  izquierda del logo, visible solo bajo 900px. El toggle de colapso a
  íconos (`.collapsed`, preferencia persistida en localStorage) queda
  **solo para desktop** — el JS de `index.php` decide con
  `matchMedia('(max-width: 900px)')` cuál de los dos comportamientos usar,
  para que las 2 lógicas de clase (`.collapsed` vs `.open`) nunca choquen.
  El drawer se cierra solo al hacer click en el backdrop o en cualquier
  link de navegación (patrón estándar) y NO se persiste en localStorage
  (siempre arranca cerrado). El rol del usuario (`.ac-header-user-info
  .rol`) se oculta bajo 480px para no desbordar junto al avatar.
- **Registrar Acuerdo PDV — el fix real de "que se pueda tipear bien"**:
  `.ac-mini-input`/`.ac-mini-select` (celdas de las 4 tablas) tenían
  `font-size: 13px` — en iOS Safari, cualquier input con `font-size < 16px`
  dispara zoom automático al enfocarlo, por eso tocar cualquier celda desde
  el celular hacía saltar el zoom de toda la página. Subido a 16px + más
  padding, pero **solo bajo 900px** (no se toca la densidad ya aprobada en
  desktop). Además: (1) fix real de overflow en `assets/js/registrar.js` —
  el panel del combobox (`posicionarPanelCombo()`) se posicionaba con
  `left`/`width` sin clampear contra `window.innerWidth`, podía salirse del
  borde derecho en pantallas angostas; (2) gap real encontrado —
  `.ac-acuerdo-section-title-split` (título + switch de "2. Visibilidad y
  Espacios") no heredaba el stacking de `.ac-card-header-split` a 600px, ya
  corregido; (3) la leyenda de Rumas (`.ac-acuerdo-rumas-legend`, fija a
  300px) ahora pasa a `width:100%` bajo 900px cuando ya envolvió a su
  propia fila; (4) la barra del modal de Acta (zoom + Generar/Descargar +
  cerrar) ahora envuelve en 2 filas bajo 600px en vez de comprimirse
  ilegible (con `order` para que "cerrar" quede junto al zoom en la fila 1,
  no solo en una 3ra fila).
- **Límite real, no un bug**: el modal de Acta muestra un PDF real generado
  server-side con Dompdf (página A4 de layout fijo) — no puede reflowar
  para mobile, se ve con pinch-zoom/scroll nativo del visor de PDF del
  navegador, igual que cualquier PDF. Mismo caso para el panel de preview
  del Acta en Historial (`#ac-historial-preview`/`#hist-pdf-frame`).
- **Indicador de scroll compartido** (`.ac-table-scroll`, bajo 900px):
  receta CSS-only de "scroll shadows" (gradientes con
  `background-attachment:local`) que muestra una sombra sutil en los bordes
  cuando todavía hay contenido para el lado — beneficia a las 4 tablas de
  Registrar y a las de Historial/Gestión de Usuarios/Liquidación/
  Repositorios por igual, misma clase compartida.
- **Pulido puntual en el resto**: `.ac-acuerdo-preview-bar` (Volver +
  Descargar/Imprimir, usada en Registrar e Historial) ahora envuelve en
  mobile — antes "VOLVER AL HISTORIAL" + "DESCARGAR / IMPRIMIR PDF" no
  entraban juntos en una fila de 375px; `.ac-repo-tabs` (Repositorios) ganó
  `overflow-x:auto` — "Participación de Percha" + "Rebate" no entran juntas
  en un celular angosto, ahora se pueden deslizar en vez de recortarse.
  Historial (stat tiles a 700px, filtros a 600px), Gestión de Usuarios
  (grid a 900px) y el modal de Firma (2 paneles a 900px con
  `min-height:45vh` por panel) ya tenían tratamiento mobile de antes,
  confirmado que sigue andando bien.
- **Verificación con mirrors** (primera pasada): cada pieza se probó con
  mirrors HTML standalone (`<link>` al `style.css` real) capturados con
  Playwright a 375px/768px/1200px, antes/después. `node -c` sobre los `.js`
  tocados, `php -l` (con `C:\xampp\php\php.exe`, no está en el PATH) sobre
  los `.php` tocados, chequeo de balance de llaves en `style.css`.
- **Verificación real, en vivo (2026-08-25, mismo día)**: el usuario compartió
  capturas reales desde su celular tomadas contra el entorno de desarrollo
  (`https://webecuador-desarrollo.azurewebsites.net/App/XploraEcuador/
  Acuerdos_Comerciales/`) y dio credenciales explícitas
  (`JAVIER MALDONADO` / rol con acceso completo) para loguearse ahí de
  verdad — "estamos en desarrollo... aún no está lanzada la página". Se usó
  Playwright para loguearse en ese entorno real (vía `page.request`/
  `page.goto`, solo lectura — ningún dato se modificó) y diagnosticar
  directo contra el DOM/CSS servidos, no contra un mirror. Esto encontró un
  **bug real preexistente que los mirrors NO habían detectado**:
  - **`.ac-hist-search-wrap` se inflaba a 260px de alto en mobile** (el
    ícono de lupa del buscador de Historial quedaba flotando muy por debajo
    del input, con un hueco enorme). Causa real, confirmada con
    `getComputedStyle()`/`getBoundingClientRect()` en vivo:
    `.ac-hist-search-wrap { flex: 1 1 260px; }` fue escrito asumiendo
    layout en FILA (260px = ancho mínimo), pero el breakpoint existente
    `@media (max-width:600px) { .ac-hist-filtros { flex-direction: column;
    } }` cambia el eje principal a vertical — y ese mismo `260px` de
    flex-basis pasa a aplicarse como ALTO, no ancho. Arreglado agregando
    `.ac-hist-search-wrap { flex: none; width: 100%; }` dentro de ese mismo
    breakpoint. Revisado el resto del CSS por el mismo patrón (`flex: 1 1
    Npx` dentro de un contenedor que en algún breakpoint pasa a
    `flex-direction:column`) — el único otro caso (`.ac-repo-filtros
    .ac-input-wrap`) vive en un contenedor que nunca cambia a columna, no
    tiene el mismo riesgo.
  - **Por qué el mirror no lo agarró**: el mirror combinado
    (`test_resto.html`) SÍ reprodujo el bug visualmente, pero al aislarlo
    en un archivo mínimo sin el contenedor `.ac-hist-filtros` alrededor
    (para "confirmar"), el bug desapareció — porque el bug depende
    exactamente de ESE contenedor pasando a columna, no del
    `.ac-input-wrap` en sí. Se descartó como "artefacto del mirror" sin
    investigarlo más a fondo, y era real. **Lección**: si un mirror
    reproduce algo raro y después un mirror "aislado" no lo reproduce, la
    diferencia entre ambos ES la pista — no descartarlo sin entender por
    qué desaparece.
  - **Hallazgo de infraestructura, no un bug de código**: al loguearse en
    vivo se confirmó que `assets/css/style.css` en ese entorno YA tenía
    todos los cambios de esta sesión (contenido idéntico, comentarios
    incluidos) — pero `index.php` seguía sirviendo la versión vieja (sin
    `acHeaderMenuBtn`/drawer), varios minutos después de guardado. O sea:
    **los `.css` se reflejan casi al instante en este entorno de
    "desarrollo", pero los `.php` tardan más** (probablemente opcache de
    PHP con revalidación no inmediata, o el paso de sync/deploy trata
    archivos estáticos distinto de los `.php` — no confirmado cuál de las
    dos). Contradice la nota vieja de memoria "deploy manual por FTP/Kudu"
    — este entorno de desarrollo específico parece sincronizarse solo,
    al menos para CSS. **Si se repite este síntoma** (un cambio de CSS se
    ve reflejado pero uno de PHP no, en este mismo entorno), no asumir que
    el archivo local está mal — probablemente es este mismo retraso, hay
    que esperar o pedirle al usuario que reinicie el App Service.

### Historial: rediseño completo mobile (2026-08-25, mismo día)

El usuario, viendo las capturas reales de arriba, pidió explícitamente un
rediseño de verdad (no parches) del módulo Historial en mobile — "siento
que ese módulo muy desordenado... sé que lo puedes hacer mejor" — porque la
mayoría de las subidas de Acta firmada (la tarea principal de este módulo)
van a pasar desde el celular. Todo el cambio vive dentro de
`@media (max-width: 700px)` en `style.css` — desktop no se tocó
(verificado, screenshot antes/después idéntico). 3 piezas:

1. **Header**: "Actualizar"/"Mis Borradores" se compactan a solo ícono
   (su texto ahora vive en `<span class="ac-btn-text">`, mismo patrón que
   ya usaba `.ac-nav-label` en la sidebar colapsada, para poder ocultarlo
   por CSS) — "Nuevo Acuerdo" queda como único botón de ancho completo.
   Antes eran 3 botones de texto completo apilados.
2. **Stat tiles**: de 3 tarjetas altas apiladas (`grid-template-columns:1fr`)
   a una fila compacta de 3 "chips" (ícono chico + valor, sin la barra de
   progreso, `text-overflow:ellipsis` en la etiqueta) — mismo dato, mucho
   menos alto.
3. **La tabla de 7 columnas → lista de tarjetas**: reportado con capturas
   reales como "desordenado" — la tabla forzaba scroll horizontal para
   llegar al botón de acción, justo el que más se va a usar desde el
   celular. Cada `<tr>` se reordena visualmente con CSS Grid
   (`grid-template-areas`) SIN tocar el orden real de los `<td>` en el DOM
   — importante porque `historial.js` reusa el mismo HTML server-side
   (`renderFilaHistorial()` en `includes/functions.php`) tanto en la carga
   inicial como en cada refresco AJAX (`tbody.innerHTML = data.filas`), así
   que no hizo falta tocar el JS ni la paginación para nada. Layout de la
   tarjeta: Documento# + badge de Firma arriba, Distribuidor debajo en
   negrita (el dato que más importa para identificar la fila), Localidad +
   Período en una línea, Fecha chica y muted, y las Acciones abajo con un
   separador. El botón de Firma (`.ac-row-actions-primary`, nueva clase)
   pasa a ser el CTA principal — ancho, con texto visible ("Subir Firma"/
   "Ver Firma", el `<span class="ac-row-actions-primary-label">` vive
   oculto en desktop) — en vez de un ícono más, del mismo tamaño que
   "Eliminar". La fila especial de "Cargando.../vacío" (`<td colspan="7">`)
   se excluye del grid de tarjeta con `tr:has(td[colspan])`.
   **Bug real encontrado armando esto** (mismo patrón que ya está
   documentado en el comentario de `.ac-content`/`.ac-acuerdo-rumas-layout`
   más arriba en este archivo): la regla genérica `.ac-table { min-width:
   640px; }` seguía aplicando sobre `#hist-tabla` aun con `display:block`,
   forzando toda la tarjeta a 640px y empujando "Período"/el badge fuera de
   la pantalla — corregido con `#hist-tabla { min-width: 0; }` dentro del
   mismo breakpoint. **Lección repetida**: cualquier contenedor
   flex/grid/tabla en este proyecto necesita `min-width:0` explícito para
   poder encogerse por debajo del ancho de su contenido — el navegador
   nunca lo hace solo.

### Historial mobile, 2da vuelta: filtros + stat tiles (2026-08-25, mismo día)

El usuario confirmó que `index.php` ya se sincronizó en el entorno de
desarrollo (drawer/hamburguesa funcionando ahí) y mandó 2 capturas nuevas
del rediseño de arriba ya en vivo: le gustó cómo quedaron las tarjetas de
Acuerdo, pero marcó 2 cosas puntuales para mejorar — la caja de filtros
"no encaja bien... no lo veo bien para ser una versión móvil", y pidió que
los 3 stat tiles queden "del mismo tamaño, bien ubicados, en una sola
fila". Ambos corregidos, mismo breakpoint `@media (max-width: 600px)`:

- **Filtros (`.ac-hist-filtros`)**: antes era el formulario de desktop
  apilado tal cual (buscador + 2 selects + 2 botones, 5 filas completas).
  Rediseño real con CSS Grid: buscador arriba a lo ancho, Período+Año en
  UNA fila de 2 columnas (son selects cortos, no necesitan ancho completo),
  y el botón "Buscar" se OCULTA en mobile — es 100% redundante en
  cualquier tamaño de pantalla (el buscador ya dispara solo al tipear,
  debounce 350ms, `historial.js` línea ~154, y los selects disparan solos
  en `change`), pero en desktop se deja como estaba por las dudas /
  affordance visual. "Descargar Excel" pasa a un pill angosto alineado a
  la derecha en vez de un botón ancho — es una acción secundaria (no es un
  filtro), no debía competir visualmente con el buscador.
- **Stat tiles**: **bug real encontrado armando la 2da vuelta** — al pasar
  `.ac-hist-stat` a `flex-direction:column` (para el layout compacto de
  chip) también hacía falta `align-items:flex-start` (para que el ícono no
  se estirara a lo ancho), pero eso mismo hacía que `.ac-hist-stat-body`
  (el div que tiene la etiqueta+valor) dejara de estirarse al ancho de la
  tarjeta — tomaba el ancho NATURAL del texto de la etiqueta en vez de
  encogerse, así que "PENDIENTES DE FIRMA" se salía del borde de su propia
  tarjeta en vez de truncarse con "…" (mismo síntoma que el bug del
  buscador de la 1ra vuelta, causa distinta). Corregido con
  `.ac-hist-stat-body { width: 100%; }` dentro del breakpoint. Probado
  hasta 320px (el ancho de pantalla más chico común) — las 3 tiles quedan
  parejas y dentro de una sola fila en todos los anchos probados.

### Header global: nombre de usuario pegado al logo (2026-08-25, mismo día)

El usuario mandó una captura más: "alicorp" y "JAVIER MALDONADO" sin nada
de espacio entre sí, y el nombre partido en 2 líneas. Mismo patrón de bug
que ya se repitió varias veces esta sesión: sin `min-width:0` en toda la
cadena `.ac-header-user` → `.ac-header-user-info`, el nombre no podía
encogerse/truncarse — se pegaba directo contra el logo en vez de ceder
espacio. Corregido en el `@media (max-width: 900px)` del header
(`style.css`, cerca de `.ac-header-menu-btn`): `.ac-header-brand-group`
(logo+hamburguesa) con `flex-shrink:0` (nunca se comprime, es la marca),
`.ac-header-user`/`.ac-header-user-info` con `min-width:0`, y
`.nombre`/`.rol` con `white-space:nowrap; overflow:hidden;
text-overflow:ellipsis`. Bajo 480px el nombre además se limita a
`max-width:110px` (además de ocultar el rol, que ya se ocultaba de antes).
Probado en 375/414/600px.

### Select nativo: reemplazo por "select bonito" reusable (2026-08-25, mismo día)

El usuario mandó una captura del `<select>` de Período ABIERTO en su
celular — un dropdown enorme, desproporcionado. **Esto no es un bug de
CSS**: el dropdown ABIERTO de un `<select>` es UI del sistema operativo en
mobile (Android/iOS) — la página no puede restylearlo de ninguna forma, es
una limitación real de la plataforma web. La única solución real es
reemplazar la interacción por un componente propio.

Nuevo `assets/js/select-bonito.js` (autocontenido, sin dependencias,
reusable en cualquier módulo — agregar la clase `ac-select-bonito-auto` al
`<select>` alcanza, se auto-mejora solo al cargar la página):
- Envuelve el `<select>` en un `div.ac-select-bonito` que hereda las MISMAS
  clases que ya tenía el select (importante: así cualquier CSS de layout
  que ya apuntaba a esas clases — `grid-area`, `flex-basis`, `width`, lo
  que sea — sigue aplicando sobre el elemento que ahora es el item real del
  contenedor, no sobre el `<select>` que queda oculto adentro).
- El trigger visible es un `<button>` con las mismas clases `.ac-select`
  (se ve IDÉNTICO a un select normal, cerrado) + label del valor actual +
  chevron que rota al abrir.
- El panel de opciones reusa `.ac-combo-panel`/`.ac-combo-option` — el
  MISMO componente visual que ya usa el combobox de Distribuidor/Segmento/
  Categoría/Marca en Registrar, cero CSS nuevo para el panel en sí. Mismo
  clamp de viewport que `posicionarPanelCombo()` de `registrar.js` (no se
  sale del borde derecho en pantallas angostas).
- El `<select>` original queda oculto (`display:none`) pero sigue siendo
  la ÚNICA fuente de verdad — clickear una opción hace
  `select.selectedIndex = i` + dispara un evento `'change'` REAL
  (`bubbles:true`), así que **todo el código existente que ya escucha
  `'change'` sobre esos selects (historial.js, liquidacion.js,
  registrar.js) sigue funcionando sin tocar una sola línea de JS de
  negocio**.
- **Caso encontrado y cubierto**: algunos módulos (ej.
  `popularFiltroCedi()` en `liquidacion.js`) reasignan `select.value = ...`
  por código directo, sin pasar por `'change'` — eso dejaría el label del
  trigger desactualizado. Se intercepta el setter de `.value` de CADA
  select puntual (`Object.defineProperty` sobre la instancia, NUNCA sobre
  `HTMLSelectElement.prototype` — no afecta a ningún otro select de la
  página) para que cualquier `select.value = x` futuro, de cualquier
  módulo, re-sincronice el label solo. Probado explícitamente con
  Playwright: `FormData(form)` sigue leyendo el valor real del select
  oculto sin problema (confirmado antes de tocar el form de subida de
  Liquidación, que si se rompe implicaría escribir mal en la base).
- **Aplicado a**: Historial (`#hist-trimestre`, `#hist-anio`), Liquidación
  (los 4 filtros de Resumen de Pagos + Canal/Año del modal de subida),
  Registrar (`#ac-periodo-select`, `#ac-anio`), Gestión de Usuarios (Rol/
  Supervisor en ambos formularios). Repositorios no tiene ningún
  `<select>` nativo (revisado, no hacía falta tocar nada ahí). Reusable
  para cualquier `<select>` nuevo que se agregue a futuro — solo hace
  falta la clase `ac-select-bonito-auto`.
- Verificado interactivo con Playwright (no solo screenshot): abrir,
  clickear una opción, confirmar que `select.value` cambia y el evento
  `change` dispara con el valor correcto — y visualmente en mobile y
  desktop, ambos sin diferencia respecto a un select nativo cerrado.

### 3 pedidos más sobre Historial + un bug real de header encontrado a fondo (2026-08-25, mismo día)

**1. Feedback de carga, reusable a nivel proyecto** — "le doy Actualizar y
no pasa nada, no hay mensaje al usuario." Nuevos `assets/js/cargando.js`
(`acBotonCargando(btn, true/false)` — ícono gira con `.ac-spin` + botón
deshabilitado; `acMostrarCargando(contenedor)`/`acOcultarCargando(...)` —
overlay semitransparente con spinner centrado, ancla sobre cualquier
`.ac-card` gracias a que `.ac-card` ahora tiene `position:relative` de
base) + `@keyframes ac-spin` en `style.css`. Conectado en Historial
(`cargarHistorial()`) y Liquidación (`cargarImportaciones()`) — mismo
patrón exacto de "Actualizar" sin feedback en las dos. Reusable en
cualquier `fetch()` futuro de cualquier módulo.

**2. Lightbox de imágenes, reusable a nivel proyecto** — pedido explícito
para poder ver bien (con zoom) las fotos del Acta firmada en el modal de
Historial. Overlay único global (`#acLightboxOverlay` en `index.php`,
`assets/js/lightbox.js`, `window.acAbrirLightbox(src)`). **No reinventa el
pinch-zoom**: el `<meta viewport>` de la app nunca tuvo
`user-scalable=no`/`maximum-scale`, así que alcanza con mostrar la imagen
grande — el zoom real lo hace el navegador solo. Botón "Ampliar"
(`.ac-firma-panel-ampliar`, esquina de cada panel) en los 2 lados del
modal de Firma: el panel "Acta Generada" siempre es PDF → abre en pestaña
nueva (visor nativo, ya trae su propio zoom); el panel "Acta Firmada"
puede ser foto o PDF → foto abre el lightbox, PDF también va a pestaña
nueva. Botón oculto por defecto, se muestra recién cuando hay contenido
real que ampliar (no en el estado "vacío" antes de elegir/tener firma).

**3. Acta chica en el preview de Historial** — el simple, confirmado:
faltaba el fragmento `#toolbar=0&navpanes=0&zoom=page-width` en
`pdfFrame.src` (`historial.js`, `abrirDetalle()`) — sin eso el visor
nativo arrancaba en su zoom "automático" (chico, con el toolbar nativo de
Chrome ocupando espacio arriba, redundante con el botón "Descargar /
Imprimir PDF" que ya está en esta misma pantalla). `page-width` fuerza que
la página ocupe todo el ancho del iframe.

**Bug real de header investigado a fondo** — el usuario avisó que había
estado probando con el zoom del NAVEGADOR en 75% ("ahorita lo puse en
100 y se dañó varias partes") — dato clave, porque cambia el ancho
efectivo de CSS que se está probando. Se investigó en vivo (mismo entorno
de desarrollo, credenciales ya guardadas) con un barrido real de anchos
(320-900px) usando `getBoundingClientRect()` en vez de solo mirar
screenshots:
- El `min-width:0`/`flex-shrink` del fix anterior SÍ funciona — no hay
  overlap real de las CAJAS en ningún ancho (confirmado con mediciones
  precisas, no solo visual).
- Pero a 320-380px el gap entre el logo y el nombre quedaba en el mínimo
  técnico (8px) — sin overlap real, pero se LEE apretado/pegado en una
  pantalla chica de verdad (el reporte del usuario con captura real lo
  confirma, aunque las mediciones digan que no hay overlap — la molestia
  visual es real aunque no sea técnicamente un bug de overlap).
  Corregido de raíz: el logo (antes `flex-shrink:0` fijo siempre) ahora se
  achica a 34px de alto bajo 380px, el tope del nombre baja a 70px, y el
  gap general del header sube de 8px a 16px (`var(--space-md)`) en todo
  el rango ≤900px — barrido completo 320-900px confirmado con gap ≥16px
  en TODOS los anchos, sin ningún punto ajustado.
- **Red de seguridad agregada de todos modos**: `.ac-header-inner` ganó
  `overflow:hidden` — si algún estado transitorio real (ej. un reflow a
  mitad de un cambio de fuente en una conexión lenta) llegara a producir
  una superposición momentánea, que se vea recortado en vez de con texto
  ilegible superpuesto.
- **Lección**: si el usuario reporta algo "roto" y las mediciones de caja
  dicen que no hay overlap técnico, no descartar el reporte — puede ser
  real igual (gap visualmente insuficiente, no necesariamente overlap) o
  estar afectado por el zoom del navegador con el que está probando. Este
  entorno de desarrollo (ver [[reference-acuerdos-comerciales]]) sigue
  siendo la única forma confiable de verificar esto — los mirrors locales
  con imágenes reales (logo/avatar) ya coincidieron con las mediciones en
  vivo esta vez, así que también sirven para iterar rápido sin gastar el
  login real en cada ajuste.

### 4ta vuelta: nombre a 2 líneas + barrido sistemático en las 5 pantallas (2026-08-25, mismo día)

El usuario pidió 2 cosas más, la 2da mucho más importante que la 1ra:
1. **Nombre del header a 2 líneas** en vez de truncar con "…" (bajo 480px,
   donde el rol ya se oculta y sobra alto en la fila de 80px para 2 líneas
   de nombre). `max-width:130px` (ajustado tras probar 90px, que partía
   "MALDONADO" a la mitad con `word-break` — 130px alcanza para que el
   nombre se parta en la palabra, "JAVIER" / "MALDONADO", nunca a mitad de
   una palabra).
2. **"Dejá de parchear módulo por módulo, englobá todo el repositorio"** —
   pedido explícito de auditar TODA la app de una, no reaccionar bug por
   bug. Se armó un script de barrido (Playwright, login real en el entorno
   de desarrollo) que recorre las 5 pantallas × 5 anchos (320/360/375/390/
   412px = 25 combinaciones) buscando overflow real de caja y selects
   "bonito" desalineados — filtrando a propósito los truncados con "…" que
   son intencionales (`.ac-btn-text`/`.ac-stat-label`/
   `.ac-select-bonito-label`), que no son bugs. **Encontró 2 bugs reales**:
   - **La causa real del "Tod…" reportado con captura**: el wrapper de
     "select bonito" (`assets/js/select-bonito.js`) hereda TODAS las
     clases del `<select>` original para conservar layout (ej. `grid-area`
     en los filtros de Historial) — pero eso incluye `.ac-select`, que
     también trae padding/borde VISUALES (pensados para un select real).
     El wrapper terminaba siendo una caja completa envolviendo al trigger
     (que adentro tiene su propia caja) — el trigger quedaba angosto por
     el padding duplicado, no por el grid. Confirmado con datos: el label
     tenía 19-65px de ancho real en vez de los ~140px que el grid ya le
     había asignado. **Corregido con una sola regla**:
     `.ac-select-bonito.ac-select { padding: 0; border: none; background:
     none; }` en `style.css` — desarma el visual del wrapper, deja el
     look real del select únicamente en `.ac-select-bonito-trigger`. Como
     el wrapper es el MISMO componente en las 5 pantallas, este único fix
     arregló Historial/Liquidación/Registrar/Gestión de Usuarios de una
     sola vez — exactamente el "englobar todo el repositorio" pedido, en
     vez de 4 fixes puntuales.
   - **Repositorios, 320px**: `.ac-repo-actions` (botones Exportar+Subir
     Archivo) desbordaba 10px — le faltaba `flex-wrap:wrap` (ya lo tenía
     el contenedor padre `.ac-repo-filtros`, pero no este grupo interno).
   - **Confirmado con un 2do barrido tras desplegar**: 0 hallazgos en las
     25 combinaciones — limpio en las 5 pantallas.
   - **Lección**: cuando un bug de layout se repite en varios lugares
     (esta vez, todos los usos de `ac-select-bonito-auto`), buscar la
     causa COMPARTIDA (el componente/wrapper común) en vez de parchear
     cada instancia por separado — un fix ahí escala solo a todos los
     usos futuros también.

### Scrollbar sin flechitas nativas + Repositorios: tabla → tarjetas en mobile (2026-08-24)

**Flechitas de scrollbar (pedido explícito, "quita esas flechitas")**:
Windows + Chrome/Edge muestra por default un scrollbar clásico con botones
de flecha arriba/abajo en cada extremo si el sitio no lo customiza (la app
nunca había tocado esto — `grep` de "scrollbar" en todo el proyecto daba 0
resultados antes de este cambio). Agregado GLOBAL en `style.css` (no solo
Repositorios, cualquier área con scroll tenía el mismo problema): regla
`*` con `scrollbar-width: thin` (Firefox) + los pseudo-elementos
`::-webkit-scrollbar*` (Chrome/Edge/Safari) — thumb redondeado con los
tokens de color del proyecto, `::-webkit-scrollbar-button { display: none }`
saca los botones de flecha. **No se pudo verificar visualmente esto en
particular** — los screenshots de Playwright (ver más abajo) no capturan el
scrollbar nativo del SO en absoluto (headless Chromium no lo renderiza en
la captura), a diferencia del resto de este cambio que sí se verificó así;
queda pendiente que el usuario confirme en su Chrome/Edge real.

**Reaparecieron 2026-08-25** — el usuario las vio en el scroll VERTICAL de
la página (no en un scroll horizontal de tabla), en la pestaña Repositorios
apenas se agregó Cuotas Trimestrales (con 2 pestañas cortas la página nunca
había necesitado scroll vertical propio, por eso no se había notado antes).
Reforzado: se agregó también el selector `*::-webkit-scrollbar-button:single-button
{ display: none; ... }` — algunas versiones de Chrome/Edge en Windows solo
obedecen el `display:none` si se apunta esa variante específica (la que
dibuja el triángulo), el selector genérico `::-webkit-scrollbar-button`
puede quedar sin efecto en el scrollbar raíz de `html` en esos casos. Sigue
sin poder verificarse visualmente desde acá — confirmar con el usuario.

**Repositorios: tabla → tarjetas en mobile, mismo criterio que el rediseño
de Historial** (ver "Historial: rediseño mobile completo" más arriba) pero
con una diferencia real: Historial tiene 7 columnas SIEMPRE fijas
(`nth-child` alcanza); Repositorios tiene 2 pestañas con distinta cantidad
de columnas (Rebate: Segmento/Sector/Categoría/Marca/Rebate% = 5; Participación
de Percha: Marca/Participación% = 2, ver `CONFIG` en `repositorios.js`) —
`nth-child` no sirve para las 2 a la vez. Se resolvió por atributos en vez
de posición:
- `assets/js/repositorios.js` (`renderFilas()`): cada `<td>` ahora lleva
  `data-key="<col.key>"` y `data-label="<col.label>"`; la celda de acciones
  lleva `data-key="acciones"`, y los botones Editar/Eliminar ganaron un
  `<span class="ac-btn-text">` (oculto en desktop, visible en mobile —
  antes eran solo íconos).
- `style.css`, bajo 700px: Marca (`[data-key="marca"]`) queda prominente
  arriba-izquierda, el % (`[data-key$="_pct"]`, matchea tanto `rebate_pct`
  como `participacion_pct`) como badge arriba-derecha en la MISMA fila
  (`grid-row:1` explícito en ambos), cualquier otra columna de datos se
  acomoda debajo sola vía colocación automática de CSS Grid (columna
  explícita `1/-1`, fila automática — funciona para 0, 3, o cualquier
  cantidad de columnas "detalle" sin hardcodear cuántas hay), con el label
  de esa columna antepuesto vía `::before { content: attr(data-label) }`.
  Editar/Eliminar pasan a 2 botones de ancho completo con su nombre visible
  (mismo criterio que los botones del header de Historial en mobile).
- **Verificado con Playwright real** (`npx playwright screenshot`, headless
  Chromium, viewport 390×900, contra el `style.css` real + HTML calcado del
  que arma `repositorios.js`) — **encontró y corrigió un bug real que el
  razonamiento sobre el CSS solo no hubiera visto**: la fila especial "Sin
  registros" (`<td colspan>`, sin `data-key`) igual matcheaba el selector
  `:not([data-key="marca"])...` de las columnas "detalle", así que el
  `::before` le agregaba un `": "` suelto antes del texto ("`: Sin
  registros.`"). Corregido agregando `:not([colspan])` a esos 2 selectores.
  Reverificado tras el fix: Rebate (con las 3 columnas detalle), Participación
  de Percha (sin ninguna) y el estado vacío — los 3 casos se ven limpios,
  capturas descartadas después de confirmar (no se guardan screenshots en
  el repo).

## Módulo "Liquidación" (2026-08-17 — antes era el placeholder "Auditoría")

El ítem de sidebar que antes era `auditoria` (hoy "Próximamente") **se
renombró a "Liquidación"** — nunca fue un módulo de auditoría/log de
acciones, el nombre era solo un lugar reservado. Rename en código ya hecho
(2026-08-17): carpeta/archivo `components/liquidacion/liquidacion.php`,
`includes/secciones.php` con `id => 'liquidacion'`, ícono `payments`, y
`roles => ['superdesarrollador']` (ya no lo ve `desarrollador`). **Ya NO es
placeholder** (esto quedó desactualizado en una edición anterior de este
archivo) — los 4 pasos del roadmap de abajo están hechos, ver "Próximo paso
acordado con el usuario" más abajo para el detalle completo de cada uno.

Qué hace este módulo — proceso de liquidación periódico (pasos 3-5 del
correo original, ver `datos/propuesta_digital_acuerdos_comerciales.md`;
frecuencia real no confirmada, ver "Decisiones confirmadas" abajo): compara
lo pactado en una Acta contra la venta/visibilidad real del período, calcula
el rebate realmente ganado y arma el "Resumen de Pagos" que hoy JW arma a
mano cruzando Excels.

### ⚠️ REPLANTEO 2026-08-23 — el mecanismo de subida (import + matching) puede
### NO ser lo que el cliente pidió — pendiente de confirmar con JW antes de
### seguir invirtiendo acá. LEER ANTES DE TOCAR ESTE MÓDULO.

**Actualización 2026-08-25**: el módulo se OCULTÓ TEMPORALMENTE del sidebar
(pedido explícito del usuario, directamente ligado a esta misma duda sin
resolver) — ver `includes/secciones.php`, la entrada `liquidacion` está
comentada, no borrada. Código, datos y tablas de la base siguen intactos,
esto solo lo saca de la navegación (nadie lo ve, ningún rol) hasta que se
confirme con JW qué hace falta de verdad. Para reactivarlo: descomentar esa
línea.

Conversación larga con el usuario, disparada por escuchar
`datos/Grabación 2026-08-18 152731.txt` (transcripción de una reunión real
con Michelle/Gabriela de JW, 2026-08-18 — vale la pena releerla completa si
se va a seguir trabajando este módulo, tiene mucho más detalle del que cabe
acá). Conclusión, con el propio usuario confirmando que la idea de "ellos
suben el Excel completado y nosotros lo matcheamos automático" **salió de
esta conversación (usuario + Claude), no de un pedido explícito de JW**:

- **El correo original dice "una hoja adicional para uso del desarrollador
  denominada Resumen de Pagos"** — "desarrollador" en este sistema es el
  ROL del asesor/vendedor (`desarrollador`/`superdesarrollador` en
  `repositorio_usuarios_acuerdos`), no un programador. Leído así, el pedido
  original es: una hoja MÁS, para que la vea el asesor, **dentro del mismo
  archivo Excel** — no una pantalla nueva en la plataforma que reciba un
  Excel de vuelta.
- **La transcripción de la reunión confirma exactamente esto** (líneas
  49-57 del archivo): Gabriela pide un "archivo plano... que se llena
  automáticamente con el acuerdo comercial", con los campos de cuota ya
  puestos y fórmulas, dejando SOLO venta/cartera para que Michelle
  complete. Eso es exactamente el export **"Descargar Excel"** que ya
  existe (`getters/exportar_cuota_categoria.php`), y que YA tiene todas las
  fórmulas de cumplimiento/GANA/REBATE REAL VOL calculadas — es decir, una
  vez que Michelle llena venta+cartera en ESE MISMO archivo, el "Resumen de
  Pagos" ya sale solo, sin que nada vuelva a la plataforma.
- **La transcripción NUNCA menciona que ese archivo completado se suba de
  vuelta a la plataforma.** Después de llenarlo, Michelle dice "se lo paso
  a Scarlett" (otra persona de JW, para su propio seguimiento) — no "lo
  subo al sistema".
- **Conclusión de diseño (pendiente de confirmar con JW, no asumir como
  definitiva todavía)**: el ciclo trimestral normal se resolvería
  100% con "Descargar Excel" tal como está — sin subir nada de vuelta. El
  módulo de Liquidación (`importar_liquidacion.php`, matching por
  `pos_name`, "Pendientes de Asignar", `estado_match`, y el Resumen de
  Pagos UNIFICADO que se armó dentro de este módulo) **seguiría siendo
  válido solo para UN caso distinto y puntual: el histórico viejo (Actas
  que nunca existieron digitalmente, `estado_match='sin_acta'`)** — eso sí
  lo confirmó el usuario directamente en esta conversación (no la
  reunión), como una necesidad real de JW.
- **Por qué "cada Acta nueva → copiar/pegar a mano" no sería tan grave en
  la práctica**: las Actas de un trimestre se negocian por el trimestre
  completo (una Acta Q1 armada en febrero dejaría enero sin cubrir, no
  tendría sentido de negocio) — así que lo esperable es que las Actas de
  un período salgan casi todas de entrada, cerca del inicio del trimestre,
  no goteando durante los 3 meses. Si en la práctica sí gotean y esto se
  vuelve un problema real, la solución liviana sería un filtro "solo lo
  agregado después de tal fecha" en el export — no reconstruir el
  mecanismo de subida.
- **Actualizado el mismo día, mismo hilo**: se releyó el correo original
  UNA VEZ MÁS con lupa, específicamente la frase de "Resumen de Pagos", y
  confirma la lectura de arriba sin ambigüedad: *"incorporando una hoja
  adicional... eliminando la necesidad de... **compartir el archivo** de
  Resumen de Pagos, ya que la información estará disponible dentro de la
  misma herramienta"* — o sea, hoy JW comparte un archivo aparte de Resumen
  de Pagos entre personas, y la mejora es meterlo como una hoja más DENTRO
  del mismo Excel de Cuota/Visibilidad, no una pantalla de la plataforma.
  **Con esto, ya no queda tan abierto — se construyó la hoja "RESUMEN DE
  PAGOS" en el export (ver sección propia más abajo).** Sigue pendiente
  confirmar con JW que esta lectura es correcta, pero ya no es una decisión
  a ciegas — está fundamentada en el texto del correo.
- **Pendiente real, igual conviene confirmar**: preguntarle directo a
  Michelle/Gabriela algo como *"el Excel que arman con las ventas, ¿nos lo
  suben de vuelta a la plataforma, o se queda todo de su lado (con nosotros
  dándoles la hoja de Resumen ya calculada en el mismo archivo, como ya lo
  armamos)?"* — para confirmar, no para decidir si se construye o no (eso
  ya se hizo).
- **Otros hallazgos de la misma reunión, relevantes para el resto del
  sistema (no solo Liquidación), documentados también en
  `datos/Grabación 2026-08-18 152731.txt`**:
  - JW quiere un **repositorio de REBATE por segmento/categoría/marca** que
    autocomplete y BLOQUEE ese campo en el Acta (hoy `rebate_pct` se tipea
    a mano y es editable) — repositorio que Gabriela/Michelle subirían
    ellos mismos, sin depender de un desarrollador cada vez.
  - Mismo patrón para **PARTICIPACIÓN de percha** (repositorio, autocompletar).
  - **Repositorio de CUOTAS trimestrales** — la idea más grande, no
    construida: Michelle sube, cada trimestre, un archivo con las cuotas
    ya pactadas por cliente (Meta de Compras), y al elegir el cliente en
    Registrar Acuerdo PDV esos campos salen **ya llenos y bloqueados**
    ("Fijo, fijo, fijo... ellos no deberían mover absolutamente nada") —
    solo para cuota de venta, NO para Visibilidad (eso sigue siendo del
    asesor). Self-service: ella sube el repositorio, no necesita que
    desarrollo se lo cargue a mano cada vez.
  - **Distribuidor se paga en CAJAS, no en dólares** ("el uno se maneja en
    cajas y el otro en dólares") y su visibilidad NO se reconoce si no
    cumplen la meta en cajas (a diferencia de Directo, que sí la reconoce
    aunque no cumplan el volumen en dólares) — esto pone en duda si el
    formato `'money'` ($) que se le puso a la hoja "VISIBILIDAD (2)" de
    Distribuidor (ver sección más abajo, "PAGO = CANTIDAD × 6") es
    conceptualmente correcto, o si debería mostrarse como número de cajas
    sin signo de dólar. **No corregido todavía, queda como duda abierta.**
  - Confirma que en Historial, cada asesor ve solo sus propias Actas y
    Michelle (única superusuaria) ve todo — responde algo que seguía
    listado como "pendiente" más abajo en este archivo.
  - La función de "subir Acta firmada" (ya construida, ver más abajo) fue
    pedida exactamente así en esta misma reunión — sin cambios necesarios,
    solo queda como validación de que se construyó bien.

**Decisiones confirmadas por el cliente/usuario:**
- **Ingesta: el cliente sube un Excel cada cierto período — la frecuencia NO
  está confirmada** (corregido 2026-08-18: esto decía "cada trimestre" antes,
  pero ni el usuario ni el correo original —`datos/propuesta_digital_acuerdos_comerciales.md`,
  releído a fondo, no contiene la palabra "trimestral" ni "mensual" en
  ningún lado— confirman esa frecuencia. Los dos ejemplos en
  `datos/LIQUIDACION...xlsx` dicen "Q2" en el nombre, pero eso puede ser solo
  el ejemplo puntual que mandaron, no una regla fija. **El sistema no asume
  ninguna frecuencia**: el importador lee directamente de las columnas del
  Excel qué meses cubre esa subida en particular, ver más abajo). Mismo
  formato de hoja para Directa y Distribuidores en cuanto a QUÉ se sube,
  aunque **son dos formatos de columnas distintos**, el importador tiene que
  soportar ambos. No hay conexión en vivo al cubo BI.
- **Relación con la Acta: por Acta, no genérica por cliente.** El Excel no
  trae `documento_no`, viene agrupado por CEDI/CLIENTE con columnas
  mensuales (el nombre exacto de mes varía según qué período se suba, ver
  detección dinámica más abajo) que calzan con `valores_mensuales` de
  `repositorio_acuerdo_lineas`. El cruce se hace por `pos_id` + que el
  período de la Acta (`mes_inicio`/`mes_fin`) se solape con los meses que
  trae el Excel subido.
- **"Resumen de Pagos": pantalla web + export a Excel**, ambos (no solo
  reporte descargable).
- **El "envío de preliminar al área comercial" (paso 5 del correo) NO es la
  integración de WhatsApp** — es un paso de revisión/aprobación dentro de la
  misma plataforma web, todavía sin desarrollar, e independiente de una
  futura fase de notificaciones por WhatsApp.
- **Permisos: solo `superdesarrollador` tiene este módulo activo** (mismo
  nivel de restricción que "Gestión de Usuarios"). Motivo: solo esos deben
  poder subir el Excel trimestral, para que no haya conflicto de dos personas
  subiendo/reprocesando la misma liquidación. El rol `desarrollador`
  (analistas) sigue viendo únicamente **Registrar Acuerdo PDV** e
  **Historial de Acuerdos** — no ve Liquidación.
- **Dos "rebate" distintos, no confundir**: `repositorio_acuerdo_lineas.rebate_pct`
  (meta_compra) es el que se tipea a mano al armar la Acta — hoy es un
  placeholder manual porque `repositorio_productos` todavía no tiene el
  catálogo real de rebate (ver pendiente arriba). El rebate que de verdad
  define el pago es el que trae el Excel que sube JW (frecuencia no
  confirmada, ver arriba) (columna
  `REBATE A APLICAR %`/`REBATE $` en Directa, `REBATE`/`REBATE $` en
  Distribuidor) — ese lo calcula Trade MKT con el cubo BI, después de la
  venta real. En teoría ambos deberían coincidir (el % pactado en la Acta es
  el mismo que Trade MKT aprobó antes de redactarla, según el correo
  original), pero como hoy el de la Acta es tipeado a mano, pueden
  desincronizarse. **Decisión pendiente del usuario** (contestó "ni idea",
  no bloqueante): si Liquidación debe avisar cuando no coinciden, o
  simplemente mostrar lo que dice el Excel de JW sin comparar. Se puede
  agregar después sin tocar el esquema (se calcularía al vuelo con un JOIN a
  `repositorio_acuerdo_lineas`, nunca se guardaría duplicado).

### Conversación 2026-08-18 — de dónde sale la data que JW mete en su Excel (RESUELTA, ver nota abajo)

Charla larga con el usuario, sin escribir código, tratando de entender bien
el flujo completo antes de construir el "Resumen de Pagos".

**Nota agregada después (misma fecha, otra sesión en paralelo)**: las dos
piezas que esta conversación dejaba pendientes de construir — el export de
lo pactado hacia JW, y la pantalla Resumen de Pagos — **ya están hechas**.
Se hicieron en otra sesión en paralelo (el usuario trabaja este proyecto
desde más de una máquina) y se fusionaron por `git merge` sin que esta
sesión lo supiera hasta que el usuario preguntó "qué cambios se han hecho".
Ver "Export de Cuota/Categoría" (arriba, sección aparte, vive en Historial
no en Liquidación) para el export, y "Próximo paso acordado con el usuario"
más abajo (paso 4) para el Resumen de Pagos. El razonamiento de esta
conversación (por qué hacía falta el export, no solo "sería lindo tenerlo")
sigue siendo válido y queda como contexto de por qué existe esa pantalla.

**Releído el correo completo (`datos/propuesta_digital_acuerdos_comerciales.md`)
con el usuario, línea por línea — la propuesta real de Xplora tiene 2 partes:**
1. Digitalización del formato AC (80%) — plantilla pre-llenada, editable
   fecha/valores/PDV/mes/firma. **Hecho**, es la app actual.
2. "Integración de la información en un solo archivo" — se mantiene el mismo
   Excel que ya usa JW, con una hoja adicional (para nosotros, no para JW)
   llamada **"Resumen de Pagos"** que "obtendrá automáticamente la
   información proporcionada por la analista de Lucky, eliminando la
   necesidad de utilizar buscadores o compartir el archivo". La parte de
   "obtener automáticamente" (importar + matchear el Excel de JW) **ya está
   hecha** (ver arriba). ~~La pantalla Resumen de Pagos en sí todavía NO
   existe — es lo único grande que falta del alcance del correo.~~
   **Desactualizado — la pantalla Resumen de Pagos se construyó el
   2026-08-18 y se rearmó para ser unificada por canal el 2026-08-20** (ver
   sección "Resumen de Pagos UNIFICADO por canal" más abajo para el estado
   real actual). Lo que sí sigue sin hacer del alcance del correo:
   El paso 5 del proceso ("envío de preliminar al área comercial para
   verificación") tampoco está hecho — es un paso de revisión/aprobación
   dentro de la web, distinto del Resumen de Pagos. WhatsApp (mencionado en
   el correo) sigue siendo fase futura separada, confirmado, no es parte de
   este alcance.

**Hallazgo importante, revisando `getters/guardar_acuerdo.php` a fondo**:
tanto el `rebate_pct` como la `cuota` (`valores_mensuales`) de la línea
`meta_compra` del Acta se tipean a mano en el formulario — no hay ningún
cálculo ni catálogo detrás, es un POST directo normalizado. Esto confirma
que **quien llena el Acta recibe esos valores de Trade MKT por fuera del
sistema** (email, verbal, lo que sea) antes de tipearlos.

**Punto que el usuario corrigió, y tenía razón**: mi primer razonamiento fue
"como Trade MKT ya tiene el dato antes del Acta, JW no necesita que nosotros
les demos nada de vuelta". El usuario lo objetó bien: eso significa tipear
el mismo dato DOS VECES (una en el Acta, otra en su Excel) — exactamente lo
que el proyecto dice que quiere reducir ("reduciendo errores operativos",
primer correo), y además crea riesgo real de que el rebate de la Acta y el
del Excel se desincronicen sin que nadie lo note (ya documentado como
"decisión pendiente" arriba, en "Dos rebate distintos").

**Conclusión corregida (confirmada con el usuario, pendiente de construir)**:
una vez que un dato se tipeó en el Acta, ESE pasa a ser el dato oficial —
JW debería poder sacarlo de nuestro sistema (cliente, categoría, cuota,
rebate % pactado, filtrado por canal/período), no volver a reconstruirlo de
cero en Excel. Falta definir cómo exactamente (¿botón de exportar en
Historial? ¿parte del mismo Resumen de Pagos?) — **eso es lo que sigue
pendiente de conversar en la próxima sesión**, junto con:
- La frecuencia de subida del Excel de Liquidación (sigue sin confirmar con JW).
- Si Liquidación debe avisar cuando el rebate de la Acta no coincide con el
  del Excel de JW (decisión pendiente ya documentada arriba).
- Cómo se construye exactamente la pantalla/export de Resumen de Pagos.

### Diseño de tablas — confirmado 2026-08-17, SQL en `datos/liquidacion_schema.sql`

**Todavía no corrido en la base real** — Claude no puede ejecutar
`CREATE TABLE` (regla de solo lectura al tope de este archivo, sin
excepciones ni "por esta vez"), el usuario lo tiene que correr él mismo en
HeidiSQL cuando tenga acceso. El script ya se probó sin errores en un MySQL
local aislado.

Tres tablas, mismo criterio de "nunca guardar totales/comparaciones
calculadas" que `repositorio_acuerdo_lineas`:
- `repositorio_liquidacion_importaciones` — un registro por Excel subido (lote).
- `repositorio_liquidacion_cuota_categoria` — una fila por cliente+categoría
  de la hoja de cuota/venta/rebate, datos crudos tal cual vienen del Excel,
  con `acuerdo_id` (NULL hasta resolver el match) y `estado_match`
  (pendiente/matcheado/sin_match/sin_acta).
- `repositorio_liquidacion_visibilidad` — mismo patrón para la hoja de
  visibilidad (Cabecera/Isla/Percha).

**Agregado 2026-08-17 — estado `sin_acta` (datos históricos sin Acta digital):**
JW quiere subir su historial viejo de liquidaciones (de antes de que existiera
esta plataforma) — esas filas **nunca van a tener un Acta que matchear**,
porque el Acta correspondiente simplemente nunca se creó acá. Esto es
distinto de `pendiente`/`sin_match` (que sí esperan resolución, solo que
todavía no se encontró el `pos_id`/`acuerdo_id` correcto): `sin_acta` es un
estado **final**, marcado a mano por el superdesarrollador con el botón "No
tiene Acta (dato histórico)" en la pantalla de Pendientes de Asignar
(`assets/js/liquidacion.js`, `resolverFila(..., 'sin_acta')` →
`getters/liquidacion_resolver_match.php?accion=sin_acta`). Al marcarla:
`acuerdo_id` queda NULL, `estado_match='sin_acta'`, se registra
`matcheado_por`/`matcheado_en` igual que un match normal (para saber quién lo
confirmó y cuándo), y `filas_pendientes` de la importación baja igual.
`getters/liquidacion_pendientes.php` excluye `sin_acta` del listado/cola
(`estado_match NOT IN ('matcheado', 'sin_acta')`) — una vez marcada, no
vuelve a aparecer. Probado de punta a punta contra un MySQL local aislado
(no contra producción, ver regla de solo lectura arriba): el UPDATE deja los
valores correctos y la fila desaparece de "Pendientes" sin afectar las demás.

**Match por Año agregado (2026-08-20)**: `liquidacion_candidatos_acuerdo_id()`
antes solo filtraba por `mes_inicio`/`mes_fin` (0-11, sin año) — un mismo
cliente con Acta del mismo trimestre en dos años distintos (ej. Q1 2025 y Q1
2026) daba 2 candidatos y cualquiera de los dos caía a `pendiente`, aunque el
**Año ya se elige en el formulario de "Subir Excel"** (`liq-anio`) y se
guarda en `repositorio_liquidacion_importaciones.anio` — simplemente no se
estaba usando para descartar candidatos. Ahora `liquidacion_candidatos_acuerdo_id($mysqli,
$posId, $mesInicio, $mesFin, $anio)` y `liquidacion_matchear_fila(...,
$anio)` reciben ese Año y filtran también `repositorio_acuerdos.anio = ?`.
Actualizados los 2 puntos que llaman a estas funciones:
`getters/importar_liquidacion.php` (match automático al subir, ya tenía
`$anio` del `$_POST`) y `getters/liquidacion_resolver_match.php` (match
manual desde "Pendientes de Asignar" — ahí no se leía `anio` de
`repositorio_liquidacion_importaciones` para nada, se agregó a la consulta
existente de `mes_inicio`/`mes_fin`). Probado contra un Acta real
(`id=57`, `pos_id=EPVD12244`, `anio=2026`, Q1): con año correcto matchea
(`acuerdo_id=57`), con año incorrecto (`2025`) da `sin_match` — antes del fix
ambos casos daban el mismo resultado porque el año no se miraba.

**Resolución de ambigüedad de ACTA (no de cliente) — agregado 2026-08-20:**
Caso real: el nombre del Excel resuelve a UN SOLO `pos_id` (cliente sin
ambigüedad), pero ese cliente tiene 2+ Actas cuyo período+año se solapan
(ej. dos Actas generadas para el mismo lugar en el mismo trimestre — pasa de
verdad, no es hipotético: **verificado contra producción real, 5 casos**
de `pos_id` con Actas duplicadas en el mismo período+año, uno con hasta 13
Actas para el mismo lugar). Antes de este cambio, `liquidacion_pendientes.php`
solo recalculaba candidatos de CLIENTE — en este caso el cliente se veía
"resuelto" (1 solo candidato), pero la fila seguía en `pendiente` porque el
segundo paso del match (cliente→Acta) era el que estaba trabado, y recién al
intentar confirmarla salía un error fijo ("revisar en Historial") sin ninguna
forma de resolverlo desde la pantalla.
- `getters/liquidacion_pendientes.php`: cuando el pos_id resuelve a 1 solo,
  ahora también recalcula `liquidacion_candidatos_acuerdo_id()` — si da 2+,
  trae esas Actas (`documento_no`, `fecha_generacion`, `estado`,
  `created_at`) como `actas_candidatas` en la respuesta.
- `getters/liquidacion_resolver_match.php`: acepta `$_POST['acuerdo_id']`
  opcional — si el cliente ya resolvió a 1 pos_id pero hay 2+ Actas
  candidatas, y el `acuerdo_id` que llega **está entre los candidatos
  legítimos recalculados en el momento** (nunca se confía en el id tal cual
  venga del POST, siempre se valida contra la lista real), se guarda directo
  sin volver a chocar con el error.
- `assets/js/liquidacion.js`: `renderPendientes()` ahora muestra, para este
  caso, un selector con cada Acta candidata (documento_no + fecha + estado,
  ej. "#ADN-2026-0002 (22/07/2026 · generado)") en vez del selector de
  cliente (que ya no aplica, el cliente no es la ambigüedad) — clic ahí llama
  `resolverFila(..., 'matchear', acuerdoId)`, que ahora acepta un 6to
  parámetro opcional y lo manda como `acuerdo_id` en el POST.
- **No hizo falta ningún cambio de esquema** — verificado con `DESCRIBE`
  contra la base real que las 3 tablas de `datos/liquidacion_schema.sql`
  (`repositorio_liquidacion_importaciones`,
  `repositorio_liquidacion_cuota_categoria`,
  `repositorio_liquidacion_visibilidad`) están creadas EXACTO como el script,
  y que `repositorio_acuerdos` ya tiene `documento_no`/`fecha_generacion`/
  `estado`/`created_at` — todo lo necesario para distinguir Actas candidatas
  ya existía, la lista de candidatas se calcula al vuelo (mismo patrón que
  ya usa la de candidatos de cliente), no se guarda nada nuevo.
- Probado contra el caso real más simple de los 5 encontrados (`pos_id
  JW0764`, 2 Actas: `ADN-2026-0001`/`ADN-2026-0002`, ambas Q1 2026): la
  consulta de candidatas devuelve exactamente esas 2 con su info
  distinguible, y la validación acepta un id de la lista real y rechaza uno
  ajeno. Nota aparte (no relacionada a este fix, ya documentada más abajo):
  `JW0764` es un `pos_id` viejo, huérfano del maestro actual — no se pudo
  probar el camino completo nombre→pos_id para este caso puntual, solo el
  paso pos_id→Acta (que es el que cambió).

**Estrategia de matching (Excel → `pos_id` → `acuerdo_id`) — implementada en
`includes/liquidacion_import.php`, probada contra los dos Excel reales de
`datos/` (solo lectura, sin tocar nada):**

- El Excel NUNCA trae `pos_id` — solo nombre (truncado por ancho de columna)
  + CEDI (Directa) o DISTRIBUIDOR+CODIGO+RUC (Distribuidor, si el Excel que
  suben las trae — el export propio del sistema para Distribuidor ya NO
  genera esas 2 columnas, ver "Export CUOTA POR CAT-DISTRIBUIDORES" más
  abajo; el importador las sigue leyendo de forma tolerante por si el
  archivo que sube JW es su propio maestro con esas columnas, simplemente
  quedan `null` si no están). **`repositorio_locales_supervisores_cliente`
  no tiene columna `ruc` ni `codigo`** — el CODIGO/RUC del Excel de
  Distribuidores no se puede cruzar contra nada hoy, queda como dato
  informativo nomás.
- **El match primario es por `pos_name` (LIKE prefijo, por el truncado),
  NO por CEDI/supervisor.** Se probó primero filtrando por CEDI=supervisor
  exacto y el resultado fue malo (~50% sin match) — la causa real: el
  supervisor de un cliente cambia con el tiempo (reasignación de
  territorio) y el Excel refleja el supervisor de cuando se hizo, no el
  actual. El `pos_name` es mucho más estable. CEDI/DISTRIBUIDOR se usa
  **solo como desempate** cuando el nombre truncado matchea a más de un
  `pos_id` real (la ambigüedad genuina, ej. dos personas con el mismo
  prefijo de nombre).
- **Resultado contra un MySQL local aislado con Actas de prueba armadas para
  calzar: 100% de match automático** (62/62 clientes únicos) — ver el
  resultado real contra producción abajo, que es muy distinto.
- **Probado en producción real (2026-08-18)**: se subió este mismo archivo de
  verdad (servidor local `php -S` apuntando a la base real, usuario
  JAVIER MALDONADO, con autorización explícita del usuario — Claude no
  ejecuta la escritura directamente, el clasificador de permisos la bloquea
  incluso con autorización verbal, así que el usuario hizo la subida él mismo
  desde el navegador). Resultado: **353 filas procesadas, 0% de match
  automático** (100% a "Pendientes de Asignar"). No es un bug — las Actas que
  existen hoy en producción (45 totales, 39 válidas) tienen `pos_id` en
  formato viejo (`JW0764`, etc., ver "huérfanos por pos_id viejo" arriba) que
  no calza con el maestro actual `EPV.../EPVD...`, y además son de
  Enero-Marzo mientras este Excel de ejemplo es Abril-Junio — cero solape
  posible. El paso 1 (Excel→pos_id) sí funciona perfecto contra datos reales
  (confirmado con diagnóstico aparte). **Confirmado por el usuario**: es
  esperado, recién están implementando el sistema — las Actas "reales"
  todavía no existen para este período histórico, así que estas filas son
  candidatas a marcarse `sin_acta` cuando haya un Excel real que sí
  corresponda a Actas ya creadas en la plataforma.
- **Resumen de Pagos también probado contra estos datos reales**: 71
  clientes, Volumen $64,366.97 + Visibilidad $15,630.00 = Total $79,996.97,
  los 71 marcados "Revisar" (correcto, nada había matcheado). El cálculo
  funciona end-to-end contra la base real.
- **Nota de rendimiento**: la subida real tardó ~66 segundos para 353 filas
  (un `INSERT` por fila, ida y vuelta a Azure MySQL cada vez) — funciona pero
  es notorio, sin barra de progreso en la UI. No es urgente, pero si el
  volumen real de JW es mucho mayor a futuro, considerar batch insert.
- **Los datos de esta prueba ya se borraron** (el usuario corrió el `DELETE`
  de las 3 tablas por `importacion_id`, confirmado con `SELECT` en 0 — las 3
  tablas de Liquidación están vacías otra vez). El pipeline completo
  (parseo → matching → guardado → Resumen de Pagos) quedó validado
  end-to-end contra producción real antes de borrar.
- **Resultado real probado con `datos/LIQUIDACION DE ACUERDO COMERCIALES
  DISTRIBUIDORES Q2 2026.xlsx`: solo 19% matchea automático, 77% sin match.**
  No es un bug del matching — se confirmó caso por caso que la mayoría de
  esos clientes (sub-clientes del distribuidor, ej. "ZAVAMEGACORP CIA LTDA",
  "WILLIAN ALBERTO JARAMILLO HERRERA") **no existen en absoluto** en
  `repositorio_locales_supervisores_cliente`, ni con nombre parcial. Lectura:
  el maestro de Alicorp está bien poblado para clientes de canal
  Directo/Cobertura (los visita un mercarista), pero mucho menos para los
  sub-clientes propios de cada distribuidor. **Pendiente de decidir con el
  usuario**: cómo resolver el volumen alto de "pendientes de asignar" que
  esto genera para Distribuidores — opciones no evaluadas todavía: pedirle a
  JW/al distribuidor un maestro más completo, permitir crear el `pos_id` al
  vuelo desde la pantalla de resolución, u otra cosa.
- Función clave: `liquidacion_matchear_fila()` en `includes/liquidacion_import.php`
  combina los dos pasos (candidatos de `pos_id`, luego candidatos de
  `acuerdo_id` por solape de mes_inicio/mes_fin) y devuelve `estado_match`.

**Lector de Excel propio, sin librería externa** — `includes/xlsx_reader.php`.
Se descartó PhpSpreadsheet (Composer no está instalado en la máquina de
desarrollo, y es una dependencia pesada que complicaría el deploy manual por
FTP, mismo problema ya documentado con la carpeta de fuentes de Dompdf). Un
`.xlsx` es un ZIP con XML adentro — el lector usa `ZipArchive` (extensión
`zip` de PHP, **hay que confirmar que esté habilitada en el hosting de
producción**, no se pudo verificar desde acá) + `SimpleXML` (siempre viene
con PHP). `xlsx_encontrar_encabezado()` ubica la fila de encabezados
buscando las columnas requeridas por nombre (no por posición fija, porque el
archivo es mantenido a mano y puede correrse de columna entre subidas).
**Ojo con nombres de columna repetidos**: las hojas de cuota tienen el mismo
bloque de meses DOS VECES (cuota pactada y venta real, distinguidas solo por
una fila de rótulo de grupo arriba) — `xlsx_col($mapa, 'ABRIL', 0)` = cuota,
`xlsx_col($mapa, 'ABRIL', 1)` = venta real, confirmado con datos reales.

**Corregido 2026-08-17 — dos bugs reales encontrados generando plantillas de
prueba (`datos/PLANTILLA_LIQUIDACION_DIRECTA.xlsx` / `_DISTRIBUIDOR.xlsx`,
hechas con openpyxl para documentar el formato esperado, ver más abajo):**
- `xlsx_mapa_hojas()` armaba mal la ruta interna cuando el `.rels` del Excel
  usa un `Target` **absoluto al paquete** (`/xl/worksheets/sheet2.xml`, lo que
  escribe openpyxl y algunas exportaciones de Google Sheets/LibreOffice) en
  vez de relativo (`worksheets/sheet2.xml`, lo que escribe Excel de
  escritorio) — quedaba buscando `xl/xl/worksheets/...` (duplicado), la hoja
  "no se encontraba" aunque el nombre estuviera perfecto. Ambos formatos son
  válidos según el spec de OOXML. Corregido: normaliza el Target antes de
  concatenar.
- `xlsx_leer_hoja()` revisaba si la celda tenía `<v>` **antes** de mirar si
  era de tipo `inlineStr` — pero las celdas `inlineStr` nunca tienen `<v>`
  (el texto vive en `<is><t>`), así que esa rama quedaba inalcanzable y
  **cualquier celda de texto en ese formato volvía `null` en silencio** (no
  error, dato faltante sin avisar). Excel de escritorio casi siempre usa
  `sharedStrings` (por eso nunca se vio en los 2 Excel reales de `datos/`),
  pero es una forma válida y común (openpyxl la usa por default). Corregido:
  se revisa `inlineStr` primero, independiente de si hay `<v>`.

Los dos bugs solo se manifiestan con archivos que NO vienen de Excel de
escritorio clásico — no afectaban a los 2 Excel reales de JW (se re-probaron
después del fix, mismos 310/516 filas de cuota y 43/53 de visibilidad que
antes, sin cambios). Pero si JW (o Lucky, la analista) llega a editar el
archivo con Google Sheets, LibreOffice, o cualquier otra herramienta, antes
esto rompía con un error confuso ("no se encontró la hoja") — ahora tolera
ambos formatos.

**Plantillas de referencia — `datos/PLANTILLA_LIQUIDACION_DIRECTA.xlsx` /
`datos/PLANTILLA_LIQUIDACION_DISTRIBUIDOR.xlsx` (2026-08-17):** generadas
para mostrarle a JW (o dejar de referencia interna) el formato exacto que
espera el importador — nombre de hoja exacto, columnas obligatorias mínimas,
y una hoja "LEEME" explicando las reglas (canal se elige al subir, no se
detecta solo; los meses se detectan por nombre y pueden ser cualquier
cantidad consecutiva, no solo 3; el bloque de meses/CABECERA-ISLA-PERCHA
debe repetirse 2 veces en el mismo orden). Contienen datos de cliente
inventados (`CLIENTE EJEMPLO ...`), no reales. **Probadas de punta a punta
contra el parser real** (`liquidacion_parsear_cuota_categoria()`/
`liquidacion_parsear_visibilidad()`) — así se encontraron los 2 bugs de
arriba.

**Corregido 2026-08-18 — el período NUNCA se asume, se detecta del archivo.**
La primera versión de esto tenía "ABRIL"/"MAYO"/"JUNIO" escritos a mano en el
código (porque solo se probó con los ejemplos Q2) — un bug real: un Excel de
otro período ni siquiera hubiera reconocido el encabezado. Se corrigió antes
de que el usuario corriera el SQL (momento ideal, sin necesidad de migración):
`xlsx_detectar_columnas_mes()` en `includes/xlsx_reader.php` escanea la fila
de encabezados buscando cualquiera de los 12 nombres de mes, en el orden que
aparecen; `liquidacion_parsear_cuota_categoria()` parte esa lista a la mitad
(primera mitad = cuota, segunda = venta, mismo orden de meses en ambas,
verificado) y calcula `mes_inicio`/`mes_fin` (0-11) de ahí — nunca de un
selector que elige quien sube el archivo (el formulario de subida ya NO tiene
selector de trimestre, ver `repositorio_liquidacion_importaciones.mes_inicio`/
`mes_fin` en el schema). `cuota_total_excel`/`venta_total_excel` también se
calculan sumando esas columnas detectadas (con `dinero_sumar()`), no leyendo
una columna "TOTAL Q2"/"CUOTA Q2" fija — ese nombre también es específico de
trimestre. Motivo del cambio: el usuario aclaró que no hay ninguna frecuencia
de subida confirmada con el cliente (ver "Decisiones confirmadas" arriba).

**Dinero: BCMath, no floats nativos** (`includes/dinero.php`, `dinero_sumar()`)
— toda suma de montos en Liquidación (venta trimestral, pago total de
visibilidad) usa aritmética decimal exacta, no `+`/`array_sum` de PHP. Pedido
explícito del usuario ("como un sistema bancario"). Reusar `dinero_sumar()`
para cualquier suma de plata nueva en este módulo, incluido el futuro
Resumen de Pagos.

**Índices faltantes en el maestro externo — DESCARTADO (2026-08-18):**
`repositorio_locales_supervisores_cliente` (~41,000 filas) no tiene NINGÚN
índice más que `id` (confirmado con `EXPLAIN`: cualquier búsqueda por
nombre es escaneo completo). Se había propuesto agregar `idx_pos_name`,
`idx_supervisor`, `idx_tipo_distribuidor` a `datos/liquidacion_schema.sql` —
**el usuario lo rechazó explícitamente: no se toca el esquema de tablas
maestras externas que no son propias de este módulo, ni siquiera con un
cambio aditivo/de bajo riesgo.** Se sacó del script. El matching de
Liquidación sigue funcionando, solo que hace escaneo completo de esa tabla
por cada cliente único a matchear — más lento, pero funcional. **No
reproponer esto sin que el usuario lo pida.**

**Próximo paso acordado con el usuario, en este orden:**
1. ~~Rename en código~~ — **hecho (2026-08-17)**.
2. ~~Diseño de tablas~~ — **corrido en producción (2026-08-18), verificado
   con `SHOW CREATE TABLE` (solo lectura)**: las 3 tablas existen, columnas y
   ENUMs (incluido `sin_acta`) coinciden exacto con el código, 0 filas. El
   `CREATE TABLE` original con `KEY ...` inline dio error 1064 en HeidiSQL —
   se corrigió a mano quitando esas líneas, así que las tablas quedaron
   **sin índices secundarios** (solo `PRIMARY KEY` de `id`). Se le pasaron al
   usuario los `CREATE INDEX` sueltos para los 7 índices que faltan
   (`idx_canal_periodo`, `idx_importacion`/`idx_acuerdo`/`idx_estado_match`
   ×2) — confirmar si ya los corrió antes de asumir que existen.
   `datos/liquidacion_schema.sql` ya quedó actualizado con el formato que
   realmente funcionó (CREATE TABLE simple + CREATE INDEX aparte, sin
   `COMMENT` de columna para evitar más problemas de sintaxis). Motivo del
   índice descartado en `repositorio_locales_supervisores_cliente`: decisión
   explícita del usuario, ver más abajo.
   **Confirmado 2026-08-18 (re-verificado con `SHOW INDEX`, solo lectura):
   los 7 índices secundarios ya están creados** (`idx_canal_periodo` en
   importaciones; `idx_importacion`/`idx_acuerdo`/`idx_estado_match` en las
   otras 2 tablas) — el esquema de Liquidación está 100% completo en
   producción, listo para probar el importador con un Excel real.
3. ~~Importador de Excel~~ — **hecho y probado de punta a punta (2026-08-17)**,
   contra un MySQL local aislado con el esquema exacto de
   `datos/liquidacion_schema.sql` (nunca contra producción, Claude no puede
   correr `CREATE TABLE`/`INSERT` ahí). Piezas construidas:
   - `includes/xlsx_reader.php` — lector de XLSX propio (sin PhpSpreadsheet,
     complicaría el deploy manual por FTP) vía `ZipArchive`+`SimpleXML`.
   - `includes/liquidacion_import.php` — parseo de las 4 hojas (2 por canal)
     + matching (`liquidacion_matchear_fila()`).
   - `getters/importar_liquidacion.php` — sube y procesa el Excel, inserta en
     las 3 tablas.
   - `getters/listar_liquidacion_importaciones.php`,
     `getters/liquidacion_pendientes.php`, `getters/liquidacion_buscar_pos.php`,
     `getters/liquidacion_resolver_match.php` — listado, cola de pendientes
     (con candidatos sugeridos + búsqueda libre), y resolución manual.
   - `components/liquidacion/liquidacion.php` + `assets/js/liquidacion.js` —
     pantalla real (ya NO es el placeholder "Próximamente"): tabla de
     importaciones, modal de subida, vista de "Pendientes de Asignar" con
     botones de candidato + búsqueda. Probada visualmente end-to-end
     (screenshots) incluyendo el flujo completo de resolver una fila a mano.
   - **Nota**: como las tablas reales no existen todavía, esto no se puede
     probar contra producción — apenas el usuario corra el SQL, probar ahí
     con un Excel real antes de darlo por confirmado.
   - **Actualizado 2026-08-17**: agregado el estado `sin_acta` para datos
     históricos sin Acta (ver arriba) — el `datos/liquidacion_schema.sql`
     cambió de nuevo (ENUM de `estado_match` en las 2 tablas), así que si el
     usuario ya lo había corrido antes de esta fecha, falta un
     `ALTER TABLE ... MODIFY estado_match ENUM(...)` para agregar el nuevo
     valor — confirmar con el usuario si ya corrió el script o todavía no.
4. Pantalla Resumen de Pagos (web + export Excel) — **hecha (2026-08-18)**,
   una vez que el esquema quedó confirmado en producción (ver arriba). Piezas:
   - `liquidacion_calcular_resumen_pagos()` en `includes/liquidacion_import.php`
     — junta `repositorio_liquidacion_cuota_categoria` (Volumen =
     `SUM(rebate_dolares_excel)`) y `repositorio_liquidacion_visibilidad`
     (Visibilidad = `SUM(pago_total_excel)`) agrupando por
     `(cedi_o_distribuidor, cliente_o_nombre)` — misma clave que usa a mano
     la hoja "RESUMEN DE PAGOS" del Excel real de JW. Total =
     `dinero_sumar([volumen, visibilidad])` (BCMath, no `+` nativo, mismo
     criterio que el resto del módulo). **No filtra por `estado_match`**: se
     muestran todos los clientes de la importación, con un campo `estado`
     (`ok`/`revisar`) según si algo de ese cliente quedó sin resolver en
     cualquiera de las 2 tablas — nunca se ocultan filas solo porque el
     match no esté completo. Trae también `documento_no` de la Acta
     vinculada (si hay) para trazabilidad.
   - `getters/liquidacion_resumen_pagos.php` (JSON, para la pantalla) y
     `getters/liquidacion_resumen_pagos_export.php` (mismo cálculo, export
     descargable) — ambos solo `superdesarrollador`, mismo patrón que el
     resto de Liquidación.
   - **Export es CSV (con BOM UTF-8), no un `.xlsx` real** — misma razón que
     `xlsx_reader.php` es propio: sin Composer instalado en la máquina de
     desarrollo, PhpSpreadsheet complicaría el deploy manual por FTP. Excel
     abre un `.csv` con BOM UTF-8 directo, sin pasos extra para el usuario.
   - UI: nueva vista `#ac-liquidacion-resumen` en `components/liquidacion/liquidacion.php`
     (mismo patrón que "Pendientes de Asignar" — se muestra/oculta con
     `.hidden`, no es una ruta aparte) + botón "Resumen de Pagos" agregado
     junto al de "Resolver" en cada fila de la lista de importaciones
     (`assets/js/liquidacion.js`, `abrirResumen()`/`renderResumen()`).
     Badges nuevos `.ac-badge-ok`/`.ac-badge-revisar` en `style.css`.
   - **Probado**: sintaxis PHP (`php -l`) y JS (`node --check`) limpios: la
     función de agregación se corrió contra la base real (solo `SELECT`,
     sin ningún INSERT/UPDATE) con una importación inexistente — sin
     errores, devuelve array vacío como se espera. **Todavía no probado con
     datos reales** (0 filas en las 3 tablas al momento de escribir esto) —
     falta subir un Excel real para validar el cálculo con números
     verdaderos antes de confiar en el total mostrado.
   - **Corregido de paso**: el texto de la UI ("Subí el Excel trimestral...")
     todavía decía "trimestral" a pesar de que esa asunción ya se había
     corregido en el código — se sacó la palabra de los 2 lugares donde
     quedaba (subtítulo de la pantalla y comentario del modal).
   - **Mejora visual/funcional (2026-08-18)**, pedida explícitamente por el
     usuario tras la primera versión (solo tabla): se usó la skill de
     `dataviz` del proyecto para hacerlo bien, no a ojo.
     - **Stat tiles** arriba de todo: Volumen, Visibilidad, Total general,
       Clientes, Por revisar (se resaltar en ámbar si > 0).
     - **2 filtros** (`liq-resumen-filtro-cedi`, `liq-resumen-filtro-estado`)
       — un solo combo de CEDI/Distribuidor (la etiqueta cambia sola según
       el canal de la importación, ya que es la misma columna
       `cedi_o_distribuidor` con dos significados) + un combo de Estado
       (Todos/OK/Revisar). 100% client-side sobre el array ya cargado
       (`resumenDatos`), sin volver a pedir al servidor — filtran a la vez
       el gráfico, los stat tiles y la tabla.
     - **Gráfico de barras horizontales apiladas**: top clientes por total,
       Volumen + Visibilidad como 2 segmentos. Colores `#2a78d6`/`#eb6834`
       — par categórico tomado de `references/palette.md` de la skill
       `dataviz` y validado con su script (`validate_palette.js`, todos los
       checks PASS) antes de usarlo, en vez de elegir colores a ojo.
       Tooltip nativo (`title`) por segmento — se evaluó un tooltip HTML
       custom pero para 10 barras en una pantalla interna no se justificaba
       el esfuerzo extra; el valor exacto siempre queda disponible en la
       tabla de abajo de todas formas.
       - **Reescrito de SVG a mano → HTML/CSS puro (2026-08-20), dos rondas
         de corrección tras feedback visual real del usuario:**
         1. La versión en SVG medía el nombre del cliente por CANTIDAD DE
            CARACTERES para decidir si truncar, pero el ancho real en
            píxeles de cada letra varía — un nombre que "por caracteres"
            parecía entrar terminaba invadiendo el área de la barra, y
            como la barra se dibujaba DESPUÉS en el XML del SVG, lo tapaba
            a la mitad de una palabra, sin ningún "…" que avisara. Se
            reescribió a filas HTML/CSS (`.ac-chart-row` con `<span
            class="ac-chart-row-label">`) — el nombre vive en su propia
            columna flex, estructuralmente no puede compartir espacio con
            la barra, y el navegador trunca con `text-overflow:ellipsis`
            real (nunca a mitad de palabra), con el nombre completo en el
            atributo `title`.
         2. El `.ac-chart-track` tenía un fondo gris + `border-radius`
            propio (look de "medidor"/barra de progreso con un track que
            marca el 100%) — el usuario lo objetó de nuevo mostrando el
            resultado: una barra corta con ese fondo gris detrás parece
            "le falta llenar algo", pero esto es un RANKING de magnitud
            entre clientes (cada barra ya es el 100% de su propio valor;
            solo se ve más corta que otra porque hay una más grande en la
            lista), no una barra de progreso con capacidad fija. Se sacó el
            fondo/caja del `.ac-chart-track` — la barra ahora crece libre
            sobre el fondo de la tarjeta, con el redondeado (`border-radius`)
            aplicado solo al `:last-child` de `.ac-chart-seg` (la punta,
            nunca toda una caja). Columna de nombre ensanchada de 180px a
            220px de paso (nombres reales de JW rondan 25-33 caracteres).
       - **Confirmado con el usuario (2026-08-20): la MISMA barra puede
         representar 2 trimestres distintos de un mismo cliente** (ver
         "Resumen de Pagos unificado por canal" más abajo) — la etiqueta
         de cada fila del gráfico ahora es `cliente (período año)`, no solo
         el nombre del cliente, para no confundir dos barras del mismo
         cliente en trimestres diferentes.

**Resumen de Pagos UNIFICADO por canal (2026-08-20) — cambio de
arquitectura, no un ajuste chico:**

Hasta acá, "Resumen de Pagos" estaba atado a UNA importación puntual — cada
Excel trimestral que subía JW quedaba en su propia pantalla aislada
(`liq-btn-resumen` mandaba a `abrirResumen(importacionId)`, que pedía
`getters/liquidacion_resumen_pagos.php?importacion_id=X`). El usuario lo
marcó explícitamente como un gap real: esperaba que cada trimestre que
subiera se fuera sumando a una sola vista de seguimiento, no tener un
Resumen aislado por cada Excel que hay que ir a buscar a mano.

**Decisión de negocio, confirmada con el usuario tras preguntarle
explícitamente** (el riesgo real: los pagos se liquidan por trimestre, no
de forma acumulada — sumar montos de trimestres distintos en un solo
número podría mostrar algo que no corresponde a lo que hay que pagar
AHORA; el usuario además avisó que puede pasar que un Excel mezcle pagos
nuevos y viejos, así que no hay que asumir que cada importación es
"limpiamente" un solo trimestre): **nunca se suman montos de trimestres
distintos en un solo número.** Un mismo cliente que aparece en 2 trimestres
da 2 filas separadas, cada una con su propio período visible, nunca 1 fila
con el total de ambos sumado. Si en algún momento se pide también un total
acumulado de por vida, es un cálculo APARTE que se arma sobre esta misma
lista — no algo que este cambio deba decidir por sí solo.

**Piezas:**
- `liquidacion_resumen_pagos_unificado($mysqli, $canal, $trimestre, $anio)`
  en `includes/liquidacion_import.php` (nueva) — junta TODAS las
  importaciones `estado='completado'` de un canal (opcionalmente filtradas
  por trimestre/año, `0`=todos) y llama a
  `liquidacion_calcular_resumen_pagos()` (sin cambios) por cada una,
  etiquetando cada fila resultante con su propio
  `importacion_id`/`anio`/`mes_inicio`/`mes_fin`/`nombre_archivo`. Filtro
  por **solape, no igualdad exacta** de meses (`mes_inicio <= ? AND
  mes_fin >= ?`) — a diferencia de las Actas (siempre trimestre fijo), una
  importación de Liquidación puede cubrir cualquier rango de meses (se
  detecta del propio Excel, sin frecuencia confirmada con JW), así que un
  filtro "Q1" tiene que encontrar también, por ejemplo, una importación que
  cubra solo Febrero.
- `getters/liquidacion_resumen_pagos.php` y
  `getters/liquidacion_resumen_pagos_export.php` (CSV) reescritos para
  recibir `canal` (+ `trimestre`/`anio` opcionales) en vez de
  `importacion_id`, ambos delegando en la función de arriba — cero
  duplicación de la lógica de agregación entre pantalla y export.
- `assets/js/liquidacion.js`: `abrirResumen(canal, trimestre, anio)` (firma
  cambiada, antes `abrirResumen(importacionId)`) + `cargarResumen()` nueva
  (hace el `fetch`, la llaman tanto `abrirResumen()` como los listeners de
  los nuevos selects de período). El botón "Resumen de Pagos" de cada fila
  del listado de importaciones sigue funcionando igual para el usuario
  (abre el resumen pre-filtrado al período de ESA fila) — `trimestreDeRango()`
  nueva mapea `mes_inicio`/`mes_fin` a un índice de trimestre SOLO si calza
  EXACTO con uno; si la importación cubre un rango raro, abre sin filtro
  (todos los períodos) en vez de adivinar mal.
- `components/liquidacion/liquidacion.php`: 2 selects nuevos en
  `.ac-resumen-filtros` (`liq-resumen-filtro-trimestre`,
  `liq-resumen-filtro-anio`, mismo patrón visual que Historial) — a
  diferencia de CEDI/Estado (filtran en el cliente sobre lo ya cargado),
  estos SÍ piden datos de nuevo al servidor porque cambian qué
  importaciones se incluyen. Columna nueva **Período** en la tabla de
  resultados (`liq-resumen-tabla`) entre Cliente y Acta — necesaria porque
  ahora un mismo cliente puede aparecer más de una vez (una fila por
  trimestre), la columna es lo que evita la ambigüedad.
- Subtítulo de la pantalla (`liq-resumen-subtitulo`) ahora arma su propio
  texto en JS a partir de los filtros activos y `data.importaciones.length`
  (ej. "Directa · Q1 2026 · 1 importación" o "Directa · Todos los períodos
  · 3 importaciones") — antes venía directo de la única importación
  cargada (`data.importacion`, que ya no existe en la respuesta).
- **Probado end-to-end contra datos reales** (sesión simulada, solo
  lectura): render del componente sin errores, `GET` real a
  `liquidacion_resumen_pagos.php?canal=directa&trimestre=0&anio=0` — trae
  la única importación real que hay hoy (`id=3`, Q1 2026, 2 clientes),
  cada fila con su período. Filtros probados uno por uno: `trimestre=1&anio=2026`
  matchea igual (es la que hay), `trimestre=2&anio=2026` y `anio=2025` dan
  0 filas correctamente (no hay datos ahí), `canal=distribuidor` da 0 filas
  (no hay importaciones de ese canal todavía). Export CSV probado igual,
  trae la columna Período nueva. **No se pudo probar el caso real de "2
  trimestres del mismo cliente sin sumarse"** porque solo hay 1 importación
  cargada en producción hoy — validar visualmente en cuanto se suba un
  segundo Excel de un período distinto.
     - **Probado**: sintaxis JS (`node --check`) y PHP (`php -l`) limpias,
       y se hizo un `GET` real a `index.php` con sesión logueada
       (`JAVIER MALDONADO`, con cookie ya autorizada por el usuario, acción
       de solo lectura) contra el servidor local — renderiza sin
       `Fatal error`/`Warning`/`Notice`, el HTML nuevo aparece completo.
       **Falta verlo con datos reales cargados** (las tablas están vacías
       otra vez tras el borrado) — pendiente de confirmar visualmente
       cuando el usuario suba el próximo Excel real.
     - **Nota**: el export a Excel (CSV) sigue exportando el dataset
       COMPLETO de la importación, no respeta los filtros de pantalla — es
       una simplificación a propósito (exportar "todo" es el caso más común
       para un archivo que después se comparte), no un bug.

## Rename de etiquetas Sector/Categoría en Meta de Compras (2026-08-25)

- **Motivo**: en la reunión del 2026-08-24 (`datos/24-08-2026 10.16.txt`,
  Jorge/Gaby) JW explicó que para ellos el nivel que la app llama "Sector"
  es su "Categoría", y lo que la app llama "Categoría" es su "Subcategoría"
  (ej. su ejemplo: Segmento=Cuidado del Hogar, su-Categoría=Crema,
  su-Subcategoría=Lavavajillas, Marca=Lava). Se verificó contra
  `repositorio_productos` real (solo lectura) antes de tocar nada: el
  registro exacto existe (`sector='CREMA', categoria='LAVAVAJILLAS'`), y se
  descartó adoptar la columna `subcategoria` de esa tabla como un nivel
  nuevo porque es prácticamente inútil (duplica `categoria` en 94% de los
  productos de JW, y duplica `sector` en el 6% restante) — esto es un
  problema de **vocabulario/etiqueta**, no de datos faltantes.
- **Qué se cambió**: SOLO el texto visible en la tabla **Meta de Compras**
  de Registrar (`assets/js/registrar.js`) — encabezado de columna, el
  placeholder del combo, y el mensaje de "campo sin confirmar" (toast). No
  se tocó ningún nombre de columna, clase (`sector-input`/`sector-select`
  siguen llamándose así), variable interna, ni `getters/acuerdo_catalogo.php`
  (`catalogo.segmentosSector` sigue siendo la misma estructura de datos).
  - `renderTableHeaders()`: encabezado de Meta de Compras pasa de
    "Segmento / Sector / Categoría / Marca" a "Segmento / Categoría /
    Subcategoría / Marca".
  - `addPurchaseRow()`: placeholders `'Sector...'` → `'Categoría...'` y
    `'Categoría...'` → `'Subcategoría...'`.
  - `describirCampoCombo()`: el mapa `tipoPorClase` ahora es condicional a
    la tabla (`etiquetaTabla === 'Meta de Compras'`) — en esa tabla
    `sector-input`→"Categoría" y `cat-input`→"Subcategoría"; en las demás
    tablas (que no comparten ese `tbody`) sigue igual que antes.
- **A propósito NO se tocó** Cabeceras/Rumas/Perchas: esas tablas solo
  tienen Segmento→Categoría→Marca (nunca tuvieron el nivel Sector), así que
  ahí "Categoría" se queda como está — renombrarla a "Subcategoría" sin un
  nivel "Categoría" por encima habría sido confuso e inconsistente.
- **Tampoco se tocó** el PDF del Acta (`includes/acta_pdf.php`) ni el
  export a Excel — el usuario autorizó específicamente la opción "renombrar
  etiquetas en Registrar", no un cambio de alcance más amplio. Si JW
  también espera ver "Categoría"/"Subcategoría" en el Acta o el Excel,
  falta pedirlo explícitamente.
- **Probado**: `node --check` en `registrar.js` limpio. No se re-probó
  funcionalmente contra datos reales porque es un cambio de texto puro —
  no toca lógica de guardado ni el mapeo de columnas.

## Repositorio de Cuotas trimestrales + Actas precargadas (2026-08-25, EN CONSTRUCCIÓN — Fase 1 completa sin probar con datos reales, Fase 2 sin empezar)

**Leer esto ANTES de tocar `repositorio_cuota_cliente`, `cuotas_*.php` o
`obtener_acta_precargada.php` en cualquier sesión** (esto se está
construyendo en paralelo entre dos máquinas — esta nota es para que la otra
sesión sepa exactamente dónde quedó).

**Qué es**: JW (Michelle) sube un Excel trimestral de cuotas por cliente
(columnas reales: `CEDI, CLIENTE, PLAN, CATEGORIAS, CONCAT, <mes1>, <mes2>,
<mes3>`, un monto $ fijo repetido en los 3 meses) — mismo patrón self-service
que Rebate%/Participación (Módulo Repositorios), pero esta vez con cliente.
El sistema resuelve solo a qué `pos_id` corresponde cada fila y arma
"Actas precargadas" que el ejecutivo/asesor dueño de ese cliente puede
cargar en Registrar, con **Meta de Compras autorellenada y bloqueada por
completo** (fila + monto) y las otras 3 tablas (Cabeceras/Rumas/Perchas)
recibiendo la fila sugerida con identidad bloqueada pero precio abierto
(reusa `sugerirEnOtrasTablas()`, ya existente, sin código nuevo ahí).

**Decisiones clave, confirmadas con el usuario**:
- "CATEGORIAS" del Excel = columna `sector` en la base (mismo nivel que se
  renombró a "Categoría" en pantalla el mismo día, ver sección de arriba).
- El match cliente→pos_id y el armado de Segmento/Subcategoría/Marca de
  cada fila de Meta de Compras (que el Excel de cuotas NO trae) lo resuelve
  el sistema solo, sin pedirle a JW más columnas — decisión explícita del
  usuario. Para Subcategoría/Marca: si el mismo `pos_id`+`sector` ya tiene
  una línea de Meta de Compras en una Acta anterior, se reusa esa
  combinación (continuidad real del cliente); si no hay historial, esos 2
  campos quedan vacíos para que el asesor los complete a mano con el combo
  normal (el monto/Segmento/Sector ya vienen bloqueados igual) —
  `getters/guardar_acuerdo.php:127` ya descarta filas incompletas, así que
  esto no necesita validación nueva.
- El año NO viene en el Excel — lo elige el superdesarrollador en pantalla
  al subir; el trimestre SÍ se infiere solo de qué 3 columnas de mes trae
  el archivo (`xlsx_detectar_columnas_mes()`).
- "Actas Precargadas" es una cola PASIVA (nunca interrumpe un formulario en
  curso) — resuelve el caso "me llega una Acta precargada mientras tengo
  otra en curso, o me llegan 2". **Dónde se ve, CAMBIADO 2026-08-25**: no es
  un botón en Historial tipo "Mis Borradores" (esa primera idea se descartó
  — el usuario objetó que una Acta asignada es más urgente que un Borrador
  propio y merece más visibilidad, no menos) — se suma como 3ra categoría a
  la campanita de alertas del header (ya construida para vencimiento de
  firma), visible en toda pantalla. Ver detalle completo en el checklist de
  Fase 2 más abajo.

**Ya construido (código, sin probar contra datos reales todavía)**:
- `includes/functions.php`: `resolverPosIdCliente($mysqli, $clienteExcel,
  $cediExcel)` (match por nombre + desempate por CEDI=supervisor, calcado
  de `liquidacion_candidatos_pos_id()`) y `usuarioIdDePosId($mysqli,
  $posId)` (pos_id → usuario responsable vía supervisor, no existía ningún
  mapeo en ese sentido antes de esto).
- `includes/repositorio_import.php`: `repositorio_parsear_cuotas()` —
  parsea CEDI/CLIENTE/PLAN/CATEGORIAS + columnas de mes dinámicas, infiere
  trimestre, devuelve `mes1/mes2/mes3` con el monto REAL de cada mes (ver
  corrección de la misma fecha, más abajo — la primera versión asumía mal
  que los 3 meses siempre traían el mismo monto).
- `getters/cuotas_previsualizar_excel.php` — paso 1, no toca la base.
- `getters/cuotas_guardar.php` — paso 2, UPSERT en `repositorio_cuota_cliente`
  sobre `(pos_id, sector, trimestre, anio)`, resuelve `pos_id` fila por
  fila, `estado='pendiente_match'` si no matchea único (sin bloquear el
  resto del archivo, mismo criterio "el sistema se defiende solo").

**Esquema ya corrido en producción (2026-08-25, confirmado por Claude con
`DESCRIBE`/`SHOW INDEX` de solo lectura)**: `datos/cuota_cliente_schema.sql`
— tabla `repositorio_cuota_cliente` existe con columnas e índices correctos
(`idx_pos_sector_periodo` UNIQUE sobre las 4 columnas, `idx_estado`,
`idx_acuerdo_generado`). **Gotcha real encontrado en el camino**: la
primera vez que se corrió, HeidiSQL creó las columnas con 2 espacios
pegados al inicio del nombre (`"  pos_id"` en vez de `"pos_id"`) —
probablemente el copiar/pegar del bloque SQL (indentado con tabs) convirtió
esos tabs en espacios que quedaron pegados al identificador. Se detectó con
un `DESCRIBE` de solo lectura (el error real al correr el `CREATE INDEX`
fue "Key column 'pos_id' doesn't exist in table", porque el nombre real
tenía los espacios). Se corrigió reescribiendo el `.sql` con espacios
normales (sin tabs) + un `DROP TABLE IF EXISTS` al principio. **Lección
para cualquier `.sql` nuevo de este proyecto: evitar tabs en el archivo que
se le pasa al usuario para HeidiSQL, usar espacios simples.**

**Estado real, no repetir acá — ver el checklist completo más abajo**, que
es el que se mantiene al día. Dos correcciones importantes salidas de la
primera prueba real del usuario (2026-08-25, con un Excel de prueba propio
en `datos/`):
- **Confirmado por el usuario: CEDI en el Excel de Cuotas de canal Directa
  ES el nombre del usuario/asesor** (mismo criterio ya usado — `supervisor`
  en `repositorio_locales_supervisores_cliente` — así que
  `resolverPosIdCliente()` no necesitó cambios). Esto también simplifica la
  Fase 2: la asignación de la Acta precargada puede confiar en el
  `supervisor` real del `pos_id` ya resuelto, sin depender del texto CEDI
  crudo del Excel.
- **Corregido: los 3 meses del trimestre SÍ pueden traer montos distintos
  entre sí** (la primera versión asumía 1 solo monto repetido, error real
  encontrado al probar con datos de prueba reales) — `valor_mensual
  DECIMAL` se reemplazó por `valores_mensuales JSON`, mismo formato exacto
  que `repositorio_acuerdo_lineas.valores_mensuales` (`{"3": 600, "4": 650,
  "5": 700}`), para que la Fase 2 lo copie directo sin convertir nada.
  **Pendiente que el usuario corra este ALTER** (no hace falta recrear la
  tabla, decisión del usuario tras preguntarle DROP+CREATE vs ALTER — con
  cualquiera de las dos se pierden los datos de la columna vieja igual, un
  monto único no dice cuál de los 3 meses era):
  ```sql
  ALTER TABLE repositorio_cuota_cliente
    DROP COLUMN valor_mensual,
    ADD COLUMN valores_mensuales JSON NOT NULL AFTER anio;
  ```
- **Eliminar en Cuotas — bloqueado si ya está `usada` (2026-08-25, pedido
  explícito)**: a diferencia de Rebate/Participación (DELETE físico
  siempre, son catálogos puros sin historia), una fila de Cuotas en estado
  `usada` ya generó una Acta real (`acuerdo_id_generado`) — borrarla de
  verdad rompería la trazabilidad de con qué datos se generó esa Acta.
  `getters/repositorio_eliminar.php` ahora chequea el estado antes de
  borrar SOLO para `tipo=cuotas`: `pendiente_match`/`pendiente_uso`/
  `descartada` se borran físico igual que siempre, `usada` se rechaza con
  un mensaje claro. No requiere cambio de esquema, es solo un check en el
  getter.

**Checklist del plan aprobado (copiado acá completo porque el archivo del
plan vive en `~/.claude/plans/` de esta máquina, que la sesión de la otra
compu no puede leer — esto es la fuente de verdad de qué falta):**

- [x] `datos/cuota_cliente_schema.sql` — corrido en producción (2026-08-25).
- [x] `includes/functions.php`: `resolverPosIdCliente()`, `usuarioIdDePosId()`,
      `listar_repositorio_cuotas()`, `listar_repositorio_cuotas_pendientes_match()`.
- [x] `includes/repositorio_import.php`: `repositorio_parsear_cuotas()`.
- [x] `getters/cuotas_previsualizar_excel.php` (paso 1, no toca la base).
- [x] `getters/cuotas_guardar.php` (paso 2, UPSERT + resolución de pos_id).
- [x] `getters/cuotas_pendientes_asignar.php` (lista la cola).
- [x] `getters/cuotas_resolver_match.php` (asigna pos_id a mano o descarta).
- [x] `getters/repositorio_listar.php` y `getters/repositorio_eliminar.php`
      extendidos con tipo `cuotas`.
- [x] **Frontend Fase 1 — completo (2026-08-25)**:
  - `components/repositorios/repositorios.php`: 3ra pestaña
    `repo-tab-cuotas`, botón `repo-pendientes-abrir` (contador + toggle por
    pestaña), input `repo-preview-anio`/`repo-preview-anio-wrap` en el paso
    de previsualización, y el modal completo `repo-pendientes-modal-overlay`
    (tabla + candidatos + input de pos_id manual + descartar), reusando el
    ancho de `.ac-borradores-modal`.
  - `assets/js/repositorios.js`: `CONFIG.cuotas` (con `columnasPreview`
    aparte de `columnas` — el Excel no trae pos_id/trimestre/anio/estado, eso
    se resuelve recién al guardar) + `editable: false` (sin edición inline,
    a diferencia de Rebate/Participación) + soporte genérico `col.render()`
    en `celdaValor()`. `activarTab()` muestra/oculta el botón de Pendientes.
    `previsualizarArchivo()`/el guardado del modal ramifican por
    `tipoActivo==='cuotas'` a `cuotas_previsualizar_excel.php`/
    `cuotas_guardar.php` (payload `{filas, trimestre, anio}`, año leído del
    input nuevo). Sección completa "Pendientes de Asignar" (abrir/cerrar
    modal, listar, click en candidato o input manual + botón Asignar,
    botón Descartar) contra `cuotas_pendientes_asignar.php`/
    `cuotas_resolver_match.php`.
  - **Probado**: `node --check`/`php -l` limpios en los 2 archivos.
    **Todavía NO probado en navegador real ni con un Excel real de Cuotas**
    — falta ese ciclo completo antes de dar la Fase 1 por confirmada.
- [x] **Fase 2 — construida (2026-08-25), probada con datos reales de solo
      lectura, falta la prueba real en navegador.** DISEÑO CAMBIADO respecto al
      plan original, leer antes de construir:** el plan original tenía un
      botón "Actas Precargadas" en Historial, calco de "Mis Borradores". El
      usuario objetó eso con un argumento válido: un Borrador es algo que el
      propio usuario dejó a medias (baja prioridad, tiene sentido que sea un
      botón discreto) — una Acta Precargada es trabajo ASIGNADO que hay que
      completar, esconderla detrás de un botón le quita la urgencia real que
      tiene. Decisión (confirmada con el usuario): en vez de un botón nuevo
      en Historial, se suma como 3ra categoría a la **campanita de alertas**
      del header (`getters/alertas_firma.php` +
      `assets/js/alertas-firma.js`, ya construida para "vence en 20 días") —
      visible en TODA pantalla apenas se entra al sistema, mismo mecanismo
      que ya está probado y que el usuario ya conoce, en vez de sumar un
      lugar más donde mirar. Al hacer click en un item de esta categoría, va
      DIRECTO a Registrar con esa Acta cargada (no pasa por Historial) — por
      eso el botón/modal separado en Historial que decía el plan original
      **ya no hace falta, se saca del alcance**.
  - `getters/alertas_firma.php`: sumar una 3ra clave `precargadas` a la
    respuesta JSON (junto a `mias`/`equipo`), usando una función nueva
    `listar_actas_precargadas_pendientes($mysqli, $usuarioId)` en
    `includes/functions.php` (agrupa `repositorio_cuota_cliente` por
    `pos_id`+`trimestre`+`anio` donde `estado='pendiente_uso'` y el
    `pos_id` resuelve a este usuario vía `usuarioIdDePosId()`).
  - `assets/js/alertas-firma.js`: nueva sección "Actas Precargadas por
    completar" en `renderPanel()`, mismo estilo de lista que "Mis Actas por
    vencer". El contador del badge (`total`) pasa a ser
    `mias.length + precargadas.length`; `hayCritico` (el pulso) también se
    activa si hay al menos 1 precargada pendiente (a diferencia de
    vencimiento, una precarga no tiene una fecha que la escale sola, así
    que se trata como urgente desde que existe). Click en un item llama a
    una función nueva (no `irAHistorial()`) que cambia a la pestaña
    Registrar y dispara `window.acRegistrarCargarPrecarga(posId, trimestre,
    anio)`.
  - `getters/obtener_acta_precargada.php` — arma el JSON de la precarga
    (con el fallback de historial de Subcategoría/Marca, ver Hallazgos
    clave más arriba) — se mantiene igual que en el plan original.
  - `registrar.js`: `cargarPrecarga()`/`window.acRegistrarCargarPrecarga`
    (bloquea `.month-input` de Meta de Compras, llama a
    `sugerirEnOtrasTablas()` para las otras 3 tablas) — se mantiene igual
    que en el plan original.
  - Marcar `estado='usada'` en `repositorio_cuota_cliente` al guardar el
    Acuerdo resultante (parámetro nuevo `origen_precarga` en
    `guardar_acuerdo.php`) — igual que en el plan original.
- [x] Probado con datos reales, de solo lectura (`listar_actas_precargadas_pendientes()`
      y `obtener_precarga_detalle()` corridos directo contra la base) —
      encontró y corrigió 2 bugs reales antes de que llegaran al usuario:

  1. **Sector del Excel no siempre matchea el catálogo real** — "POLVO
     DETERGENTE" no existe como Sector; es Sector "POLVO" + Subcategoría
     "DETERGENTE" pegados en el mismo texto (confirmado: `sector='POLVO'`
     tiene una única Subcategoría real, "DETERGENTE"). Nueva función
     `resolverSectorReal($mysqli, $sectorCrudo)` en `includes/functions.php`,
     conectada en `getters/cuotas_guardar.php` (Fase 1, no Fase 2 — se
     corrige ANTES de guardar, no después): 1) ¿matchea directo un Sector
     real? úsalo tal cual; 2) ¿es "Sector Subcategoría" pegados
     (`CONCAT(sector,' ',categoria)` exacto) contra una única combinación
     real? sepáralo, usa solo el Sector; 3) si ninguno matchea (ej. "OTRAS
     CATEGORIAS" — hay 3 Subcategorías reales bajo `sector='OTROS'`,
     ninguna encaja, genuinamente ambiguo) se guarda el texto crudo IGUAL
     (nunca se inventa un Sector) pero con un aviso claro. **Las filas de
     prueba ya subidas con el bug viejo quedaron con `sector='POLVO
     DETERGENTE'` guardado tal cual** — hay que volver a subir el Excel de
     prueba (el UPSERT va a crear una fila NUEVA con `sector='POLVO'`
     correcto, porque `sector` es parte de la UNIQUE — las viejas con el
     texto sin corregir quedan huérfanas, hay que borrarlas a mano desde la
     tabla de Cuotas).
  2. **Bug real en `registrar.js` (`bloquearFilasPrecargadas()`)**: bloqueaba
     Segmento/Categoría SIEMPRE, incluso cuando el Segmento quedó ambiguo
     (ej. `sector='LIQUIDO'` tiene 2 Segmentos reales posibles para JW,
     `obtener_precarga_detalle()` correctamente lo deja `null` en vez de
     adivinar) — eso dejaba la fila trabada para siempre, sin ninguna forma
     de completarla (Segmento bloqueado y vacío a la vez). Corregido: el
     bloqueo de Segmento/Sector ahora es condicional a que
     `fila.segmento` haya venido resuelto — si quedó ambiguo, la fila usa
     el cascade normal (Sector deshabilitado hasta elegir Segmento, como
     cualquier fila nueva), con los 3 montos mensuales siempre bloqueados
     igual (eso es lo que JW pidió proteger, no cambia).

  **Todavía sin probar en navegador real** — falta que el usuario cargue
  una Acta precargada de verdad desde la campanita y confirme visualmente
  que Meta de Compras queda bien bloqueada/completa y que
  Cabeceras/Rumas/Perchas reciben la sugerencia.

## Repositorio de Cuotas — borrado lógico + Resumen visual (2026-08-25, mismo día que Fase 2)

Surgió de una tanda de preguntas operativas reales del usuario tras probar
Fase 1/2 con datos reales (re-subida, cómo dar de baja algo, cómo saber a
quién se le manda cada Acta) — 3 cambios más:

- **Mensaje de guardado distingue nuevo vs. actualizado** (2026-08-25,
  pedido explícito: "el que sube el archivo tiene que entender si está
  cargando algo nuevo o modificando algo que ya existía") — antes decía
  genérico "Se guardaron N filas", ahora usa `$stmt->affected_rows` de cada
  `INSERT...ON DUPLICATE KEY UPDATE` (1=nueva, 2=actualizada,
  0=ya existía igual, sin cambios) y arma el mensaje real, ej. "Se
  guardaron 8 fila(s) nueva(s), 3 actualizada(s), 1 sin cambios (ya existía
  igual)." Requiere que la conexión NO tenga el flag `CLIENT_FOUND_ROWS`
  (confirmado: `db_connect.php` conecta sin flags, así que aplica la
  semántica clásica 0/1/2).
  **Ronda 2, mismo pedido, REEMPLAZA lo anterior**: probamos primero una
  caja verde fija que se quedaba abierta después de guardar (mostrando el
  resultado real) — el usuario aclaró que no quería enterarse RECIÉN
  DESPUÉS de guardar, quería saber ANTES de confirmar. Se sacó esa caja del
  todo (`.ac-alert-success`/`#repo-preview-resultado`, ya no existen) y en
  su lugar la previsualización de Cuotas ahora tiene una columna extra "Al
  guardar" con un badge por fila (Nuevo / Actualiza / Ya usada — no se
  puede modificar / Cliente sin identificar), calculado ANTES de que el
  usuario confirme:
  - `getters/cuotas_verificar_estado.php` (nuevo, solo lectura, nunca
    escribe) — resuelve pos_id/sector igual que `cuotas_guardar.php`
    (`resolverPosIdCliente()`/`resolverSectorReal()`) y consulta si ya
    existe una fila para `(pos_id, sector, trimestre, año)`, sin guardar
    nada.
  - `assets/js/repositorios.js`: `verificarEstadosPreview()` — se llama
    apenas se sube el archivo (con el Año por default ya puesto) y de
    nuevo cada vez que el superdesarrollador cambia el Año (debounce
    400ms, `previewAnioInput` input listener) — el trimestre se sabe del
    Excel, pero el año recién se tipea en este mismo paso, así que el
    chequeo no se puede hacer antes de eso. `renderPreviewTabla()` agrega
    la columna "Al guardar" solo para `tipoActivo==='cuotas'`.
  - **Probado con datos reales de solo lectura**: BARRA (ya subida antes)
    dio "actualiza", "POLVO DETERGENTE" (que ahora se resuelve a Sector
    real "POLVO", nunca guardado bajo esa clave corregida todavía) dio
    "nuevo" correctamente, y un cliente inventado dio "sin_cliente" — los
    3 casos esperados, confirmados exactos.
  - **Ronda 3, mismo día, 3 pedidos más tras probar en navegador**:
    1. **Spinner en "Guardar"** — botón genérico `.ac-btn-cargando`
       (`style.css`, ícono `progress_activity` girando con `@keyframes
       ac-girar`, `prefers-reduced-motion` respetado) — se activa en
       `guardarCuotas()`/`guardarFilas()` justo antes del `fetch`, se
       apaga en `onDone` o en el `.catch()` de error (para no quedar
       trabado si falla la conexión).
    2. **Fila entera pintada, no solo el badge** — `claseFilaEstado()`
       agrega `ac-preview-fila-nueva`/`-actualiza`/`-usada` al `<tr>`;
       tuvo que pisarse también el fondo de `.ac-preview-input` (el input
       tapa casi toda la celda, pintar solo el `<tr>` no se veía).
    3. **El aviso rojo de "revisar" después de guardar confundía** —
       resultó ser la nota de interpretación de Sector
       ("POLVO DETERGENTE"->"POLVO", o "no coincide con el catálogo") que
       ya existía en `cuotas_guardar.php`, pero recién se veía DESPUÉS de
       guardar. `getters/cuotas_verificar_estado.php` ahora también
       expone `sector_interpretado`/`sector_sin_resolver` (mismo criterio
       que `resolverSectorReal()`), y `badgeEstadoPreview()` los muestra
       como una 2da línea dentro del mismo badge — la misma info, pero
       ANTES de confirmar.

**Fase 2 probada en navegador real por primera vez (2026-08-25) — 3 correcciones más, todas confirmadas contra datos reales de solo lectura:**

1. **Filas duplicadas (7 en vez de 5) explicadas**: eran las filas viejas
   guardadas ANTES de la corrección de `resolverSectorReal()` ("POLVO
   DETERGENTE" sin separar) conviviendo con las nuevas correctas ("POLVO")
   — se resolvió borrando y resubiendo la base completa del Repositorio de
   Cuotas. **Lección para cualquier corrección futura de `sector` en
   Cuotas: avisar siempre que hay que limpiar filas viejas con la clave
   vieja, porque `sector` es parte de la UNIQUE — nunca se auto-fusionan.**
2. **"Agregar Fila"/"Eliminar" en Meta de Compras ahora se BLOQUEAN
   del todo cuando el Acuerdo viene de una precarga** (pedido explícito,
   corrige el diseño anterior que sí dejaba eliminar filas) — la tabla es
   una estructura fija, el asesor solo completa Subcategoría/Marca si
   faltan, nunca la reorganiza. `bloquearFilasPrecargadas()` deshabilita
   el botón `#ac-add-purchase-row` y cada `.ac-remove-row` de las filas
   precargadas; `limpiarFormularioParaNuevoAcuerdo()`/`aplicarBorrador()`
   los reactivan para el siguiente Acuerdo (si no, quedarían bloqueados
   para toda la sesión). Nueva regla CSS genérica
   `.ac-icon-btn:disabled, .ac-btn-outline:disabled, .ac-btn-primary:disabled,
   .ac-btn-secondary:disabled { opacity:0.4; cursor:not-allowed;
   pointer-events:none; }` — sin esto un `<button disabled>` con estos
   estilos custom no se ve distinto al habilitado.
3. **Categorías en $0 ya no entran a la precarga** — como ahora Meta de
   Compras no deja eliminar filas (punto anterior), una categoría con los
   3 meses en $0 (ej. "OTRAS CATEGORIAS" sin datos reales en el Excel)
   quedaría atrapada para siempre sin poder sacarla. `obtener_precarga_detalle()`
   ahora descarta esas filas ANTES de armar la línea (`array_sum($valores)
   <= 0`, ver `includes/functions.php`). **Probado con datos reales**:
   antes de este fix daba 5 líneas (incluida OTRAS CATEGORIAS en $0),
   después da 4 — exacto lo que esperaba el usuario.
   - **Corregido 2026-08-26** (el usuario lo notó de inmediato en la
     campanita real: "5 categorías" pero solo 4 en el formulario) —
     `listar_actas_precargadas_pendientes()` ya no cuenta con `COUNT(*)`
     en SQL, trae las filas y suma/filtra `valores_mensuales` en PHP
     (mismo criterio `array_sum() <= 0` que `obtener_precarga_detalle()`).
     Probado con datos reales: daba 5, ahora da 4, coincide exacto.

**Hallazgo 2026-08-26, NO es un bug — investigado a pedido del usuario**:
probando con Javier Maldonado (cliente YUCAILLA PADILLA RENE WILFRIDO,
Sector BARRA), el PDF real que le dio JW dice "BARRA DETERGENTE EL
MACHO", pero el spinner de Subcategoría solo mostraba "LAVAVAJILLAS" y
"ROPA" — parecía que faltaba "EL MACHO". Investigado con `SELECT` de solo
lectura contra `repositorio_productos`: **"EL MACHO" sí existe**, activo,
bajo Sector "BARRA" — pero su Subcategoría real ahí es **"ROPA"**, no
"DETERGENTE". Es un desajuste de nomenclatura entre el maestro de
productos de Alicorp (tabla externa, este proyecto NUNCA la puede tocar,
ni esquema ni datos) y lo que JW imprimió a mano en el Acta de papel —
mismo tipo de caso que "POLVO DETERGENTE"/"OTRAS CATEGORIAS" pero al
revés (acá el dato SÍ está, solo con otro nombre). Recomendación dada al
usuario: elegir "ROPA" en el spinner (es la opción real que corresponde a
"EL MACHO") y, si este desajuste de nombres se repite seguido, comentárselo
directo a JW — no es algo resoluble desde el código de este proyecto.

**Resumen — Cuotas Trimestrales, rediseño 2026-08-26 (pedido explícito,
"me hace ruido... quítalo, lo veo innecesario")**: el usuario probó la
pantalla en el navegador y el tile "Sin usuario asignado: 11" le pareció
confuso — un número sin decir A QUIÉN corresponde. Cambios:
- Se sacó ese tile. `resumen_cuotas()` (`includes/functions.php`) ya no
  devuelve `sin_asignar` — la lista `por_usuario` ahora es ÚNICA (un
  `UNION ALL`: usuarios reales con cuenta activa + supervisores del
  maestro con cuotas pendientes que todavía no tienen cuenta), cada fila
  con `tiene_cuenta` (bool). El campo cambió de `usuario` a `nombre`
  (ya no es siempre un usuario real).
- `renderResumenChart()` (`assets/js/repositorios.js`) muestra esa lista
  única — la barra de un supervisor sin cuenta sale con `opacity:0.5` +
  "(sin cuenta)" al lado del nombre, marca pasiva en vez de un badge de
  alerta (no es un error, es solo informativo).
- **Probado con datos reales de solo lectura**: da 4 filas — JAVIER
  MALDONADO (con cuenta, 1 Acta), CARLOS PROAÑO (sin cuenta, 8), XAVIER
  ALVARADO (sin cuenta, 2), DANNY QUINDE (sin cuenta, 1) — coincide exacto
  con lo que ya se había visto a mano antes.
- **De paso, mismo pedido**: el botón "Pendientes de Asignar" de la
  pestaña Cuotas se ocultó (no se borró el mecanismo completo, solo se
  dejó de mostrar el botón en `activarTab()`) — el usuario pidió sacarlo
  rápido sin invertir tiempo en removerlo del todo. Si hace falta
  retomarlo, `getters/cuotas_pendientes_asignar.php`/`cuotas_resolver_match.php`
  y el modal siguen intactos, solo no hay forma de abrirlo desde la UI
  por ahora.

**Rediseño visual con Claude Design, pasado a código real (2026-08-26)**:
el usuario pidió explícitamente usar la skill `design` para mejorar la
lista "A quién le corresponden" antes de decidir si valía la pena — se
armó una maqueta estática (1 artboard, tokens reales del proyecto
tomados de `style.css`: `--color-primary` #00288e,
`.ac-avatar-initials`, etc.), publicada como Artifact para que la
revisara. Le gustó, pidió aplicarla — mismo patrón que ya se usó para el
Módulo Repositorios ("empezó como mockup... pasó a código real").
Cambio real (`assets/js/repositorios.js`: `renderResumenChart()`,
`filaResumenUsuario()`, `inicialesDe()` nuevas; `assets/css/style.css`:
bloque `.ac-resumen-*` nuevo después de `.ac-chart-row`): la lista ya no
es un solo bloque con un badge chico "(sin cuenta)" — son 2 secciones
separadas ("Con cuenta de usuario" / "Sin cuenta todavía"), cada fila con
avatar de iniciales (mismo estilo que `.ac-avatar-initials` de Gestión de
Usuarios), nombre y barra — las de "sin cuenta" en gris apagado
(`--color-outline`/`--color-outline-variant`), las de "con cuenta" en el
azul primario del proyecto, con una tarjeta sutil de fondo para
distinguirlas más. Nota informativa al final del grupo sin cuenta
explicando que se resuelve solo cuando se les crea la cuenta.

- **Bug real corregido en `cuotas_guardar.php`**: resubir el mismo
  trimestre podía "revivir" una fila ya `usada` (ya generó una Acta real) de
  vuelta a `pendiente_uso`, rompiendo el enlace con esa Acta. Ahora se
  chequea el estado ANTES del UPSERT — si ya está `usada`, esa fila puntual
  se salta por completo (aviso claro, no falla el resto del archivo).
- **"Eliminar" en Cuotas pasó a ser borrado lógico** (`estado='descartada'`,
  ya no `DELETE` físico) para `pendiente_match`/`pendiente_uso` — sigue
  bloqueado del todo para `usada`. Nuevo botón/getter
  `getters/cuotas_reactivar.php` (solo visible en filas `descartada`) la
  vuelve a `pendiente_uso` (si tenía `pos_id`) o `pendiente_match` (si no).
  `updated_at`/`actualizado_por` ya alcanzan para que el usuario pueda
  ubicar "qué se descartó y cuándo" si después hay que deshacerlo.
- **Botón "Resumen"** (solo pestaña Cuotas) — modal con 4 stat tiles
  (Actas pendientes, Ya generadas, Sin usuario asignado, Clientes sin
  identificar) + gráfico de barras horizontales por usuario (cuántas Actas
  precargadas pendientes tiene cada uno). `resumen_cuotas($mysqli)` en
  `functions.php` + `getters/cuotas_resumen.php`. Mismo patrón visual EXACTO
  ya construido y probado en Liquidación ("Resumen de Pagos") — tarjetas
  `.ac-resumen-stats`/`.ac-stat-tile` + barras HTML/CSS puro
  (`.ac-chart-rows`/`.ac-chart-track`/`.ac-chart-seg`, nunca SVG a mano, ver
  esa lección ya documentada), reusando el mismo azul `#2a78d6` ya validado
  con el script de la skill `dataviz` — un solo color porque es 1 sola
  medida por usuario (no hace falta leyenda).
- **Probado con datos reales de solo lectura**: `resumen_cuotas()` corrido
  contra la base real dio 12 Actas pendientes, 11 sin usuario asignado, 1
  asignada a JAVIER MALDONADO, 0 usadas — coincide exacto con lo que se
  había verificado a mano antes con `usuarioIdDePosId()`.

**Todavía sin probar en navegador real** — falta abrir el modal de Resumen
y ver el gráfico de verdad, y probar el ciclo completo Descartar->Reactivar
desde la tabla.

## Convenciones para código nuevo

- Todo nombre de tabla nueva empieza con `repositorio_`.
- Nunca usar `FOREIGN KEY` en `CREATE TABLE` — el usuario de BD no tiene el
  privilegio `REFERENCES`. Usar `KEY idx_...` (índice normal) para rendimiento,
  y validar relaciones en el código de la aplicación.
- Nunca guardar columnas de total/suma calculadas — calcular siempre al vuelo.
- Nunca crear catálogos propios de Segmento/Categoría/Marca/PDV — siempre
  consultar `repositorio_productos` / `repositorio_localesddt2` en vivo.
- Meses siempre se representan como `TINYINT` 0-11 (0=Enero), nunca como texto
  "ENE"/"FEB" en la base (el texto es solo para mostrar en UI/PDF).
- **Borrado lógico siempre, nunca `DELETE` físico, en tablas de
  catálogo/repositorio nuevas** (regla base agregada 2026-08-25, tras un
  caso real: "Eliminar" en Repositorios era un `DELETE` real, y si alguien
  borraba algo por error no había forma de recuperarlo, ni el dato ni
  cuándo pasó). Toda tabla nueva de este tipo lleva, además de
  `created_at`/`updated_at`/`actualizado_por` (quién la modificó por última
  vez): `eliminado_en DATETIME NULL` (NULL = activa) + `eliminado_por INT
  UNSIGNED NULL` (quién la borró). "Eliminar" en el código pasa a ser un
  `UPDATE ... SET eliminado_en = NOW(), eliminado_por = ?`, nunca un
  `DELETE`; todo `SELECT` de listado agrega `WHERE eliminado_en IS NULL`; y
  si la tabla tiene un `UNIQUE KEY` de negocio, el UPSERT de guardado tiene
  que limpiar `eliminado_en`/`eliminado_por` en su `ON DUPLICATE KEY UPDATE`
  (si no, volver a cargar una fila con la misma clave que una ya borrada
  actualiza el dato pero la deja invisible, atascada). Implementado primero
  en `repositorio_rebate_producto`/`repositorio_participacion_percha` (ver
  sección "Módulo Repositorios" — pantalla "Eliminados" con filtro de fecha
  + botón Reactivar); `repositorio_cuota_cliente` ya usa el mismo principio
  con su propio mecanismo (`estado='descartada'`, tiene su propio `estado`
  enum así que no necesitó las 2 columnas nuevas) — no fue necesario tocarla.

## Rebate — bug real corregido en el match Registrar↔repositorio (2026-08-27)

**Contexto**: la sesión paralela (misma tarde) hizo casi todo el trabajo de
conectar Rebate a Registrar con el Excel real de JW (`datos/RABATE.xlsx`,
55 filas: `CIUDAD | CANAL | CATEGORIA | SUBCATEGORIA | MARCA | REBATE`) —
`ALTER TABLE` de `repositorio_rebate_producto` (sacó `segmento`, agregó
`ciudad`/`canal`, ya corrido, 55 filas reales guardadas), parser flexible
(`repositorio_parsear_rebate()` acepta tanto `SECTOR/CATEGORIA` propio como
`CATEGORIA/SUBCATEGORIA` de JW), y `getters/acuerdo_buscar_rebate.php` +
`assets/js/registrar.js` (`buscarYAplicarRebate()`) ya conectados: al
completar Sector+Subcategoría+Marca en una fila de Meta de Compras, busca
el Rebate real (Ciudad=CEDI del cliente si es Directo, "TODAS" si es
Distribuidor) y bloquea el campo si hay match, o lo deja editable si no.

**Bug real encontrado y corregido en esta sesión, verificado con datos
reales de solo lectura**: la búsqueda comparaba texto EXACTO (`UPPER(TRIM())`)
entre lo que el asesor elige en el cascade real (que sale de
`repositorio_productos`, ej. Sector="LIQUIDO" singular, Subcategoría="ROPA"
para EL MACHO) contra lo que quedó guardado en el repositorio tal cual vino
del Excel de JW (Sector="LIQUIDOS" plural, Subcategoría="DETERGENTE" para
EL MACHO — mismos 2 desajustes de nombre ya documentados para Cuotas).
Confirmado con una prueba real: buscar exacto con los valores que de
verdad ofrece el cascade daba "SIN MATCH" para LIQUIDO+DETERGENTE+CIERTO Y
para BARRA+ROPA+EL MACHO — es decir, **todo el bloque LIQUIDO (~32 de 55
filas reales) y BARRA+EL MACHO nunca hubieran matcheado en la práctica**,
aunque el dato estuviera cargado.

Corregido con `buscarRebateProducto($mysqli, $ciudad, $canal, $sector,
$categoria, $marca)` (nueva, `includes/functions.php`, mismo espíritu que
`resolverSectorReal()` de Cuotas pero en el momento de BUSCAR, no de
guardar — a propósito, para no tener que re-guardar los datos ya subidos):
1. Match exacto (como ya hacía `acuerdo_buscar_rebate.php`).
2. Variantes de plural/singular (agregar/quitar una "S" final) de Sector Y
   de Categoría, probadas en combinación — resuelve LIQUIDOS/LIQUIDO.
3. Último recurso: Ciudad+Canal+Sector+Marca SIN Categoría — si da una
   única fila, se usa esa (nunca si hay más de una) — resuelve el caso
   EL MACHO/ROPA sin necesidad de adivinar entre varias Categorías reales.
`getters/acuerdo_buscar_rebate.php` ahora delega en esta función en vez de
tener la consulta exacta inline. **No se tocó `repositorio_rebate_producto`
(los datos ya subidos quedan tal cual, con el texto crudo del Excel) ni
`repositorio_productos`** — la corrección vive solo en la búsqueda.

**Probado con datos reales de solo lectura** — los 2 casos que antes
fallaban ahora resuelven bien (`LIQUIDO+DETERGENTE+CIERTO+QUITO+DIRECTA` ->
0.015, `BARRA+ROPA+EL MACHO+QUITO+DIRECTA` -> 0.015, coincide exacto con
las filas reales del Excel), un combo inventado sigue dando `NULL`
correctamente (no hay falsos positivos). **Todavía sin probar en
navegador real** — falta abrir Registrar, elegir un producto de la familia
LIQUIDO o EL MACHO, y confirmar visualmente que el Rebate% se autocompleta
y bloquea de verdad.

## Alcance real de Acuerdos Comerciales — Sector/Categoría restringidos a 9 combos (2026-08-27)

El usuario recordó de una reunión que JW no trabaja con todo lo que ofrecen
los spinners de Meta de Compras (ej. PASTAS) y pidió investigar antes de
tocar nada — usando `repositorio_productos` **y** los Excel reales de
Liquidación (no solo Rebate) como evidencia independiente.

**Investigación (3 fuentes reales, todas de solo lectura)**:
- `repositorio_productos` (fabricante=JABONERIA WILSON, activar=SI) tiene
  18 combinaciones Sector+Subcategoría reales: AEROSOL (AMBIENTADOR,
  INSECTICIDAS — marca SAPOLIO), BARRA (LAVAVAJILLAS, ROPA), CREMA
  (LAVAVAJILLAS), LIQUIDO (DESINFECTANTES, DETERGENTE, JABON TOCADOR,
  LAVAVAJILLAS, SUAVIZANTES), OTROS (CLORO — EL MACHO, LIMPIADOR DE
  VIDRIO, LIMPIADOR POLVO — LAVA), PASTAS (CORTOS, LARGOS — DON VITTORIO),
  POLVO (DETERGENTE), SALSAS (CLASICA, SALSA DE TOMATE). **Todos son
  productos reales de JW** (confirmado con `GROUP_CONCAT(DISTINCT
  fabricante)` por combo — ninguno mezclado con otra marca), JW simplemente
  fabrica bastante más que línea de limpieza.
- `datos/RABATE.xlsx` (Excel real de Rebate): solo 8 de esas 18
  combinaciones aparecen.
- `datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA 2026.xlsx`, hoja
  "CUOTA CLIENTE - CATEGORÍA": la columna CATEGORIAS (=nuestro Sector) solo
  trae `BARRA`, `CREMA`, `LIQUIDO`, `POLVO DETERGENTE` + un cajón genérico
  sin desglosar `OTRAS CATEGORIAS`.
- `datos/LIQUIDACION DE ACUERDO COMERCIALES DISTRIBUIDORES Q2 2026.xlsx`,
  hoja "CUOTAS POR CAT -DISTRIBUIDORES": mismo resultado, solo `BARRA`,
  `CREMA`, `LIQUIDO`, `POLVO DETERGENTE` — ni siquiera tiene el cajón
  genérico.

**Las 3 fuentes de negocio reales (Cuotas, Liquidación, Rebate) coinciden
exactas**: JW nunca trabaja `AEROSOL`, `OTROS`, `PASTAS` ni `SALSAS` dentro
de Acuerdos Comerciales — ni un solo Excel real de ningún proceso de este
módulo los menciona. Esto reduce el catálogo real a **9 de las 18
combinaciones**: `BARRA/LAVAVAJILLAS`, `BARRA/ROPA`, `CREMA/LAVAVAJILLAS`,
`LIQUIDO/DESINFECTANTES`, `LIQUIDO/DETERGENTE`, `LIQUIDO/JABON TOCADOR`,
`LIQUIDO/LAVAVAJILLAS`, `LIQUIDO/SUAVIZANTES`, `POLVO/DETERGENTE`.

**Implementado — el usuario confirmó restringir las 4 tablas del Acta, no
solo Meta de Compras** (vía `AskUserQuestion`, eligiendo el alcance más
amplio de las 3 opciones ofrecidas). Un solo punto de cambio:
`getters/acuerdo_catalogo.php` — array `$combosValidos` (las 9 parejas
Sector+Categoría de arriba) armado en un `$filtroSectorCategoria` SQL
(`(sector='X' AND categoria='Y') OR ...`, valores fijos del código, no de
usuario) y agregado como `AND` extra en las 3 queries del archivo:
- `segmentos_sector` (Meta de Compras, tiene nivel Sector): filtra directo
  por el combo Sector+Categoría — de 18 combos reales pasa a mostrar
  exactamente los 9 confirmados.
- `segmentos` (Cabeceras/Rumas, sin nivel Sector visible): mismo filtro
  aplicado sobre Sector/Categoría internos aunque esas tablas no muestren
  esas columnas — el Segmento resultante sigue siendo el mismo (`CUIDADO
  DEL HOGAR`/`CUIDADO PERSONAL`, ninguno se pierde), pero ya no ofrece
  categorías/marcas exclusivas de PASTAS/SALSAS/AEROSOL/OTROS dentro de
  esos 2 segmentos.
- `marcas_percha` (Perchas, solo Marca): mismo filtro — de 10 marcas reales
  de JW baja a 7 (`CIERTO, EL ARRANCAGRASA, EL MACHO, GOL, LAVA, MISTY,
  SAPOLIO`), quedan afuera `ALACENA`, `ALACENA LIMON` (SALSAS) y `DON
  VITTORIO` (PASTAS) — `EL MACHO`/`SAPOLIO` SÍ quedan porque también venden
  productos dentro de los 9 combos válidos (`EL MACHO` en `BARRA/ROPA`,
  confirmado en el caso ya documentado más arriba).

**No se tocó `repositorio_productos`** (maestro externo de Alicorp, regla
de siempre) — el filtro vive 100% en la consulta de este getter, reversible
con solo tocar `$combosValidos` si el alcance real cambia (ej. si JW agrega
otra línea a Acuerdos Comerciales más adelante).

**Probado con datos reales de solo lectura** (mismas queries que arma el
getter, corridas directo contra la base): `segmentos_sector` da exacto los
9 combos esperados, ni uno más ni uno menos; conteo de marcas de Percha
baja de 10 a 7 como se esperaba; los 2 Segmentos (`CUIDADO DEL
HOGAR`/`CUIDADO PERSONAL`) se mantienen, ninguno queda vacío por el filtro.
`php -l` limpio. **Todavía sin probar en navegador real** — falta abrir
Registrar y confirmar visualmente que los 4 spinners ya no ofrecen
PASTAS/SALSAS/AEROSOL/OTROS en ninguna de las 4 tablas.

## Módulo nuevo "Seguimiento de Equipo" (superdesarrollador) — SOLO DISEÑO, nada implementado todavía (2026-08-27)

Pedido explícito: un módulo nuevo, exclusivo `superdesarrollador`, "tipo
seguimiento" — dos cosas puntuales: (1) cuántas Actas se generaron en
total, (2) quiénes tienen Actas por vencer. Es, en espíritu, la vista de
"Equipo" que se sacó de la campanita (ver sección "Campanita rediseñada a
2 pestañas" más arriba, 2026-08-25/26) — pero como módulo propio del
sidebar, no metida dentro del panel de notificaciones.

**A pedido explícito del usuario, se diseñó primero con Claude Design
(canvas) antes de tocar código — nada de esto está implementado, es
100% mockup.** El link del canvas (privado, del usuario) quedó en:
`https://claude.ai/code/artifact/a941e96c-4a54-4399-9b9c-cc269505219c`
— para retomar en otra sesión, hay que leerlo con la herramienta de
Artifact (`action: "read"`) o abrirlo en el navegador, **no hace falta
rehacer el diseño de cero**.

**Historial de vueltas de diseño (por qué terminó donde terminó)**:
1. 1ra versión: calcada pixel a pixel de los componentes reales del
   proyecto (mismo sidebar, header, `.ac-hist-stat`, `.ac-table`,
   badges) — el usuario la aprobó en el fondo pero pidió explícitamente
   "no quiero que sea igual a alguno de mis repos existentes, quiero
   algo original".
2. 2da versión: dirección completamente distinta — estética "libro de
   registro/dossier" (serif Newsreader, papel kraft, sellos de tinta
   circulares en vez de badges) — el usuario la rechazó: "mantén
   nuestro diseño... eso parecen pptx" — se había ido demasiado lejos
   del lenguaje visual real de la app, se sentía como una presentación,
   no como una pantalla del sistema.
3. **3ra versión (la que quedó, la del link de arriba)**: vuelta a los
   tokens/componentes reales del proyecto (Inter, `--color-primary:
   #00288e`, mismos badges/tarjetas/tabla que Historial) — pero
   conservando lo que sí funcionó del intento 2: el **nivel de detalle**
   que pidió el usuario después ("si me decís cuántas faltan por
   firmar, quiero que después clasifique de cuáles usuarios son y qué
   actas son, a ese nivel de detalle").

**Estructura del diseño final (2 artboards en el canvas)**:
- **`Main` — Registro general**: header con "Actualizar"; 4 stat tiles
  (mismo componente que `.ac-hist-stat` de Historial, con un 4to nuevo)
  — Actas Generadas en Total / Firmadas / Pendientes de Firma /
  Vencidas; tabla "Equipo — Actas por vencer" con 1 fila por usuario
  (Usuario, Pendientes, Más Próxima a Vencer con badge de color igual
  a `ac-badge-urgente`/`ac-badge-critico`, Vencidas, y un botón **"Ver
  Actas →"**).
- **`Detalle` — Expediente individual**: se abre al clickear "Ver
  Actas" de una fila. Breadcrumb de vuelta, cabecera con avatar-
  iniciales del asesor (mismo patrón `.ac-avatar-initials` de Gestión
  de Usuarios), sus propios 4 stat tiles, y una tabla `.ac-table` real
  con las Actas PUNTUALES de ese asesor — Documento (`#ADN-2026-XXXX`),
  Cliente/PDV, Fecha Generada, Plazo (badge), Acciones (descargar/ver,
  iguales a los íconos de fila de Historial). Se armó completo solo
  para Adrian Vasquez como ejemplo (3 Actas reales de este proyecto —
  `#ADN-2026-0044/0051/0056` — usadas ya varias veces en esta sesión
  para pruebas, con números de días que cuadran entre el registro
  general y el expediente: "3 días" en la fila = Acta `#0044` con ese
  mismo plazo en el detalle).

**Para implementar de verdad cuando se retome** (nada de esto existe
en código todavía):
- Nuevo `secciones.php`: entrada `seguimiento`, roles
  `['superdesarrollador']`, ícono `monitoring`.
- Backend: la función `listar_equipo_pendientes_firma()` que se borró
  al sacar "Equipo" de la campanita (ver sección de arriba) es
  prácticamente la base de la tabla del registro general — hay que
  revivirla (o algo similar) + sumar una query de conteos globales
  (total/firmadas/pendientes/vencidas, sin filtrar por `creado_por` —
  a diferencia de TODO el resto del proyecto, que siempre filtra por el
  usuario logueado, ver `CLAUDE.md`/reglas de arriba).
- El expediente individual (`Detalle`) necesita un getter nuevo — algo
  como `listar_actas_de_usuario($mysqli, $usuarioId)` filtrando por
  `creado_por = ?` y `estado IN ('generado','enviado')`, mismas
  columnas que ya trae `listar_alertas_firma_propias()`
  (`includes/functions.php`) más `pos_name`/`cedi` (join con
  `repositorio_locales_supervisores_cliente`, mismo patrón que
  `listar_historial_acuerdos()`).
- Ojo: como esto es la ÚNICA pantalla del proyecto donde
  `superdesarrollador` ve Actas de OTROS usuarios (todo lo demás filtra
  siempre por `creado_por` del que está logueado), reforzar el chequeo
  de rol en el/los getters nuevos — no alcanza con que el módulo esté
  oculto en el sidebar para roles sin permiso.

## Módulo "Seguimiento de Equipo" — implementado de verdad (2026-08-27, mismo día, otra sesión)

Retoma el mockup de la sección anterior, pero con un cambio de diseño
explícito del usuario respecto al plan original: **en vez del artboard
"Detalle" con breadcrumb (navegar a una pantalla aparte)**, el usuario pidió
un **dropdown/acordeón inline** — click en cualquier parte de la fila de un
usuario expande, DEBAJO de esa misma fila, sus Actas puntuales (documento,
distribuidor, fecha generada — y para Pendientes de Firma, además días
restantes). Mismo patrón para las 2 tablas ("Actas Generadas" y "Pendientes
de Firma"), con filtro de trimestre + año arriba de ambas.

**Archivos nuevos**:
- `components/seguimiento/seguimiento.php` — página completa: filtros
  (trimestre/año, mismo `<select>` que Historial), 4 stat tiles (Total/
  Firmadas/Pendientes/Vencidas, informativos, no filtran nada al click) y
  las 2 tablas con SSR inicial (mismo patrón que Historial: primer render en
  PHP, refrescos posteriores por AJAX).
- `assets/js/seguimiento.js` — filtros (recarga las 2 tablas + stats),
  expand/colapso por fila (delegación de click en el `<tbody>`, no solo el
  botón chevron — toda la fila es clickeable) con caché en el DOM
  (`data-cargado`, el detalle de un usuario se pide al servidor una sola vez
  por carga de página, no en cada abrir/cerrar). Expuesto
  `window.acSeguimientoRefrescar` (hookeado en `index.php`, mismo patrón que
  Historial/Repositorios/etc.).
- `getters/seguimiento_resumen.php` / `getters/seguimiento_actas_usuario.php`
  — ambos reforzando `rolPermitido(['superdesarrollador'])` de nuevo (única
  pantalla del proyecto que expone Actas de otros usuarios).

**Backend (`includes/functions.php`)**: `resumen_seguimiento_equipo()`
(stats + desglose por usuario de ambas tablas), `listar_actas_equipo_usuario()`
(el detalle que se abre al expandir), `renderFilaSeguimientoUsuario()` +
`renderFilaDetalleSeguimientoGenerada()`/`renderFilaDetalleSeguimientoPendiente()`
(HTML de las filas, mismo patrón de "el servidor renderiza el `<tr>`, el
getter solo lo envuelve en JSON" que ya usa Historial —
`renderFilaHistorial()`), `badgeDiasRestantesSeguimiento()` (mismo criterio
de urgencia de 20 días que Historial: ≤5 días urgente, ≤1 crítico).
`listar_anios_disponibles_equipo()` — como acá `superdesarrollador` ve TODO
el equipo, es sin filtrar por `creado_por`, a diferencia de
`listar_anios_disponibles()` que ya existía para Historial.

**3 hallazgos reales corregidos, verificados con datos reales de solo
lectura (nunca con un script que escriba — regla raíz del repo) antes de
darlo por terminado**:
1. **El total global (42) no coincidía con la suma de la tabla por usuario
   (7)** — la primera versión usaba `JOIN` normal a
   `repositorio_usuarios_acuerdos`, que descarta en silencio cualquier Acta
   con `creado_por IS NULL`. Confirmado con datos reales: **35 de 42 Actas
   reales son así** (Actas viejas, de antes de que se empezara a rastrear el
   usuario que las generó — ver "El gap real" en Pendientes/decisiones
   abiertas). Corregido con `LEFT JOIN` + `COALESCE(u.id, 0)`/
   `COALESCE(u.usuario, 'Sin usuario asignado')`, un bucket sintético
   `usuario_id=0` que agrupa esas 35 — mismo criterio que ya usa
   `resumen_cuotas()` para los supervisores sin cuenta. Fila con tratamiento
   visual apagado (`.ac-seg-fila-huerfana`, opacidad + subtítulo explicando
   por qué), para no confundirla con un usuario real del equipo.
2. **El detalle de ese bucket daba vacío pese a contar 35** — el `LEFT JOIN`
   de arriba resuelve el CONTEO, pero `listar_actas_equipo_usuario()`
   todavía usaba `JOIN` normal a `repositorio_locales_supervisores_cliente`
   (para traer `pos_name`) — confirmado con datos reales: **0 de esas 35
   Actas tienen un `pos_id` que matchee el maestro actual** (`JW0618`,
   `JW0965`, etc. ya no existen ahí), así que las 35 desaparecían también
   del detalle. Mismo fix: `LEFT JOIN`, `pos_name` cae a `'—'` en el render
   si no hay match, la fila nunca desaparece.
3. **Badge "Vence hoy" engañoso en Actas viejas sin estado real** —
   `dias_restantes` se calcula en el SQL para cualquier fila con
   `fecha_generacion`, sin mirar `estado`; confirmado con datos reales que
   esas 35 Actas viejas tienen `estado` en blanco (no `generado`/`enviado`
   reales) — sin guard, el badge las mostraba como "Vence hoy" aunque nunca
   corrieron ese plazo. Corregido en
   `renderFilaDetalleSeguimientoGenerada()` con el mismo chequeo que ya usa
   `renderFilaHistorial()`: el badge de días solo aplica si
   `estado IN ('generado','enviado')`, si no cae a "Pendiente" genérico.

**Probado**: `php -l` limpio en los 6 archivos tocados/nuevos, `node --check`
limpio en `seguimiento.js`. Las 2 funciones nuevas y sus variantes de bucket
huérfano corridas directo contra la base real (solo lectura, sin llamar
`barrer_actas_vencidas()` desde el script de diagnóstico — esa función hace
un `UPDATE`, nunca se invoca a mano fuera de la app real, regla raíz del
repo): stats reales (42 total, 1 firmada, 6 pendientes, 0 vencidas), 2
usuarios reales con cuenta (JAVIER MALDONADO 4, ADRIAN VASQUEZ 3) + el
bucket de 35 sin usuario, suma exacta 42/42; detalle de ambos usuarios
reales y del bucker huérfano confirmados con filas reales. **Todavía sin
probar en navegador real** — falta que el usuario entre a "Seguimiento de
Equipo" logueado como `superdesarrollador`, confirme visualmente el
acordeón (expandir/colapsar, ambas tablas, ambos filtros) y que el mockup
del canvas se sienta reflejado en el resultado real.

## Módulo "Seguimiento de Equipo" — REDISEÑO completo, reemplaza la implementación anterior (2026-08-27, mismo día, sesión de diseño)

**⚠️ Superada la sección "Módulo 'Seguimiento de Equipo' — implementado de
verdad" de arriba** (tiles + tabla con acordeón) — el usuario la calificó
"6 de 10" y pidió explícitamente rediseñar con Claude Design. Se hizo 3
rondas de mockup (link del canvas final, aprobado:
`https://claude.ai/code/artifact/3ed37f45-9a0c-4bd0-b013-672084c70975`,
leer con Artifact `action:"read"` si hace falta retomar el diseño) antes de
tocar código real:

1. **1ra ronda de mockup**: maestro-detalle con 2 pestañas (Generadas/
   Pendientes) + franja con barra proporcional + bucket "Sin usuario
   asignado". El usuario dio 6/10: la barra proporcional era ilegible (84%
   histórico aplastaba el resto a una línea invisible), y las 2 pestañas se
   sentían redundantes con los "chips" de la franja (misma info, 2
   mecanismos distintos).
2. **2da ronda**: reemplazó las 2 pestañas + la barra por **un solo filtro
   de 4 estados** (Todas/Firmadas/Pendientes/Vencidas) que controla a la
   vez la lista de Equipo y el detalle — "Todas" muestra todo (lo que antes
   hacía la pestaña Generadas), los otros 3 filtran la MISMA tabla. Se sacó
   el bucket "Sin usuario asignado" por completo, a pedido explícito del
   usuario ("no pongas un histórico dizque sin vincular, eso no") — el
   total pasó de 42 (crudo) a 7 (solo Actas con `creado_por` real). Se
   agregó buscador de usuario (en memoria, sin red).
3. **3ra ronda (la que se implementó)**: mismos cambios funcionales,
   corrigiendo el copy — "Quién generó qué, y a quién hay que ir a buscar
   la firma primero" y "— ordenado por cantidad" sonaban poco corporativos,
   reemplazados por texto más formal (ver `VISTAS` en `seguimiento.js`).
   Confirmado también: el orden de Pendientes es ascendente por días
   restantes (arriba lo más próximo a vencer, abajo lo que tiene más
   margen) — ya estaba así, el usuario solo lo reconfirmó explícito.

**Arquitectura real, distinta al resto del proyecto a propósito**: los 2
getters (`getters/seguimiento_resumen.php`,
`getters/seguimiento_actas_usuario.php`) devuelven **JSON crudo**, no HTML
pre-armado como el resto de getters de este proyecto (ver
`renderFilaHistorial()` de Historial) — `assets/js/seguimiento.js` arma
TODO el DOM (lista, filtros, detalle) en cliente a partir de ese JSON.
Decisión deliberada: cambiar de filtro/buscar tiene que sentirse
instantáneo, sin ida y vuelta al servidor en cada click, y el dataset por
equipo es chico. Mismo espíritu que ya usa `resumen_cuotas()` +
`renderResumenChart()` de Repositorios (JSON in, JS arma el gráfico), no es
un patrón nuevo en el proyecto, solo la primera vez que se usa para una
lista completa en vez de un gráfico.

**Backend (`includes/functions.php`)** — reemplaza TODO lo de la sección
anterior:
- `resumen_seguimiento_equipo($mysqli, $trimestre, $anio)`: 1 sola query
  con `JOIN` normal (no LEFT JOIN — ya no hay bucket huérfano) a
  `repositorio_usuarios_acuerdos`, `GROUP BY u.id`, trae
  `total/firmadas/pendientes/vencidas/dias_mas_proxima` por usuario en una
  sola pasada (antes eran 2 queries separadas, "generadas" y "pendientes").
  Solo devuelve usuarios con al menos 1 Acta real en el período — no hay
  fila "0 Actas" para alguien sin actividad (se evaluó traer TODOS los
  usuarios activos con `LEFT JOIN`, se descartó: los roles reales no
  distinguen limpio "equipo comercial" de "cuenta admin" — `FRANKLIN
  SALCEDO` y `JAVIER MALDONADO` son ambos `superdesarrollador` en la base
  real, no `desarrollador` — así que filtrar por rol hubiera sido
  arbitrario; derivar el equipo de quién generó Actas de verdad evita esa
  ambigüedad sin inventar una regla de negocio nueva).
- `listar_actas_equipo_usuario($mysqli, $usuarioId, $trimestre, $anio,
  $tipo)`: `$tipo` ahora es `'todas'|'firmadas'|'pendientes'|'vencidas'`
  (antes era un booleano `$soloPendientes`) — un `switch` arma la condición
  SQL y el `ORDER BY` (`pendientes` ordena por `dias_restantes ASC`, el
  resto por fecha de generación DESC). Ya no hay rama `usuario_id=0` ni
  `LEFT JOIN` al maestro de clientes (volvió a `JOIN` normal, ya no hace
  falta tolerar `pos_id` sin match del bucket huérfano que ya no existe).
- Se borraron `badgeDiasRestantesSeguimiento()`, `renderFilaSeguimientoUsuario()`,
  `renderFilaDetalleSeguimientoGenerada()`, `renderFilaDetalleSeguimientoPendiente()`
  — quedaron sin uso porque el render ahora vive 100% en `seguimiento.js`
  (`badgeParaActa()`, `badgeParaDias()`, mismo criterio de 20 días/≤5
  urgente/≤1 crítico, reimplementado en JS).
- `listar_anios_disponibles_equipo()` se mantiene igual.

**Frontend**:
- `components/seguimiento/seguimiento.php`: solo arma el shell (header,
  pills de trimestre + `<select>` de año, los 4 botones de filtro con
  contadores en 0, buscador, contenedores vacíos con "Cargando...") — mismo
  patrón que ya usa la campanita de alertas en `index.php` (JS llena todo
  vía fetch al cargar). Los pills de trimestre/el filtro de estado son
  botones reales (no un `<select>`), calcado del mockup aprobado.
- `assets/js/seguimiento.js`: `cargarResumen()` (fetch inicial + en cada
  cambio de trimestre/año), `computeFilasBase()`/`aplicarBusqueda()`
  (arman la lista filtrada en memoria, sin red), `cargarDetalle()` (única
  llamada de red que SÍ depende de la selección — con caché simple vía
  `ultimoFetchKey` para no repetir el fetch si la selección efectiva no
  cambió). Anillo de avatar: `conic-gradient` inline calculado en JS
  (verde = % firmadas, naranja/rojo = urgencia de lo pendiente).
- CSS nuevo en `assets/css/style.css`, sección "Seguimiento de Equipo
  (2026-08-27, rediseño maestro-detalle...)" — reemplaza el bloque CSS de
  la versión anterior (acordeón) completo. Grid maestro-detalle cae a 1
  columna bajo 900px.

**Probado**: `php -l`/`node --check` limpios en los 6 archivos. Las 2
funciones nuevas de `functions.php` corridas directo contra la base real —
**⚠️ corrección honesta (encontrada por auditoría de código, 2026-08-27,
más tarde el mismo día): esa verificación NO fue solo lectura como se
afirmó acá originalmente.** `resumen_seguimiento_equipo()` y
`listar_actas_equipo_usuario()` arrancan con `barrer_actas_vencidas($mysqli)`
(un `UPDATE`) — llamarlas directo desde un script de diagnóstico ejecuta
ese `UPDATE` de verdad, violando la regla raíz del repo (Claude nunca debe
ejecutar escrituras, sin excepción). El `UPDATE` en sí es idempotente y
no destructivo (solo pasa a `'vencido'` lo que ya cumplió los 20 días),
pero eso no vuelve correcta la afirmación "solo lectura", ni justifica
haberlo corrido. **Lección para cualquier verificación futura de una
función que empiece con `barrer_actas_vencidas()`**: copiar y correr el
`SELECT` suelto (sin la función completa), exactamente el criterio que sí
se siguió para el bucket huérfano en la sección anterior — no alcanza con
"nunca invocarla a mano fuera de la app real" si después se llama la
función completa igual. Los datos verificados (total=7/firmadas=1/
pendientes=6/vencidas=0, 2 usuarios reales, orden de `pendientes` 2/13/18
días ascendente, `firmadas` exacta, `vencidas` vacío) siguen siendo
correctos — el problema es el MÉTODO de verificación, no el resultado.
**Todavía sin probar en navegador real** — falta que el usuario entre a
"Seguimiento de Equipo" logueado como `superdesarrollador` y confirme
visualmente los 4 filtros, el buscador, y que el layout ocupa bien el
espacio real de `.ac-content`.

### 2 bugs reales encontrados por el usuario probando en navegador (2026-08-27, mismo día)
1. **`.ac-avatar-initials { border-radius: var(--radius) }` (4px) nunca fue
   un círculo** — cuadrado con esquinas apenas curvas, invisible en Gestión
   de Usuarios (el avatar flota solo) pero roto/desalineado contra el aro
   perfectamente redondo nuevo de Seguimiento de Equipo
   (`.ac-seg-avatar-ring`). Corregido en el componente COMPARTIDO
   (`border-radius: 50%`) — mejora también Gestión de Usuarios de paso.
2. **Botón "Todas" del filtro de Seguimiento mostraba 0** — el backend
   manda `stats.total`, no `stats.todas`; `actualizarBotonesFiltro()`
   buscaba `statsActual['todas']` (undefined). Corregido con mapeo
   explícito `key === 'todas' ? statsActual.total : statsActual[key]`.

## Auditoría completa a pedido del usuario — 12 bugs reales corregidos (2026-08-27, mismo día)

El usuario pidió explícito "explora todo el proyecto en búsqueda de bugs
sea visual sea de lo que sea... tómate tu buen tiempo, no quiero una
revisión vaga". Se corrió `/code-review xhigh` sobre TODO el diff de
Seguimiento de Equipo (8 agentes en paralelo, distintos ángulos: diff
línea por línea, comportamiento eliminado/faltante, trazado cruzado de
archivos, patrones de lenguaje, wrapper/caché, reuso/simplificación/
eficiencia, altitud arquitectónica, convenciones de CLAUDE.md), más una
pasada manual propia de discrepancias de texto/DOM duplicado (sin
hallazgos ahí — placeholders, terminología Sector/Categoría/Subcategoría e
ids del DOM ya estaban consistentes).

**Bug propio, fuera del código, encontrado por la auditoría misma**: ver
la corrección arriba, en la sección "Módulo 'Seguimiento de Equipo' —
REDISEÑO completo" — una de las verificaciones "Probado" de esa sección
en realidad ejecutó un `UPDATE` (`barrer_actas_vencidas()`), violando la
regla raíz del repo. Corregido el texto, informado al usuario.

**12 bugs reales de código corregidos** (`includes/functions.php`,
`assets/js/seguimiento.js`, `components/seguimiento/seguimiento.php`,
`includes/secciones.php`):

1. **`<select>` de año sin "Todos los años"** — arrancaba fijo en el
   primer año real, excluyendo Actas de años anteriores desde la primera
   carga sin forma de volver a "todos". Agregado como primera opción,
   igual que `hist-anio` de Historial.
2. **`listar_actas_equipo_usuario()` no corría `barrer_actas_vencidas()`**
   — una Acta que ya debía estar vencida podía seguir mostrando "Vence
   hoy" en vez de "Vencida" si se pedía el detalle sin pasar antes por el
   resumen. Agregado, mismo criterio que el resto de funciones de listado.
3. **`JOIN` en vez de `LEFT JOIN`** al maestro de clientes en el detalle —
   reintrodujo el mismo bug de fondo ya resuelto una vez (Acta real con
   `pos_id` sin match desaparecía del detalle aunque contara en el total).
   Vuelto a `LEFT JOIN`, verificado con datos reales.
4. **Condición de carrera: respuestas de red fuera de orden** — click en
   usuario A, click rápido en usuario B; si la respuesta de A llegaba
   después que la de B, pisaba el panel ya actualizado por B. Mismo
   problema en `cargarResumen()` cambiando de trimestre/año rápido dos
   veces. Corregido con un token de request (`resumenReqId`/`detalleReqId`)
   — solo la respuesta del ÚLTIMO pedido puede pintar/actualizar el
   caché, cualquier respuesta vieja se descarta en silencio.
5. **Fallo de red en la carga inicial dejaba la pantalla trabada en
   "Cargando..." para siempre** — solo se mostraba un toast, sin tocar
   los contenedores. Agregado un estado de error explícito con ícono y
   texto en ambos paneles.
6. **`estado.selectedId` con chequeo "falsy"** (`!estado.selectedId`) en
   vez de `== null` — un id real nunca es 0 hoy, pero es el mismo
   antipatrón que ya causó el bug del bucket sintético `usuario_id=0` en
   la primera versión de este módulo. Corregido a `== null`.
7. **`formatearFecha()` sin escapar en el detalle** — inconsistente con
   el resto de la función (todos los demás campos sí usan `escapeHtml()`).
   Riesgo bajo hoy (columna DATE), corregido por consistencia/defensa.
8. **El anillo de estado no reflejaba Vencidas** — un usuario con 0
   pendientes pero Actas vencidas mostraba un aro gris "neutral", incluso
   mirando el filtro "Vencidas". Corregido: `vencidas > 0` ahora pinta el
   aro en rojo crítico, con prioridad sobre la urgencia de pendientes.
9. **Colores de badge hardcodeados en JS en vez de las clases CSS reales**
   — perdían la animación de pulso de `.ac-badge-critico` aunque el color
   coincidiera a simple vista. Corregido: los badges ahora aplican
   `ac-badge-ok`/`ac-badge-urgente`/`ac-badge-critico`/`ac-badge-revisar`
   (clases reales de `style.css`), sin colores inline.
10. **Iniciales de avatar calculadas distinto en JS que en el resto de la
    app** — la regex de `seguimiento.js` solo separaba por espacios, a
    diferencia de `inicialesUsuario()` (PHP, usada en Gestión de Usuarios)
    que también separa por punto/guión — un usuario con nombre de usuario
    con punto (ej. `javier.maldonado`) daba iniciales distintas solo acá.
    Corregido: `iniciales` ahora se calcula en el servidor
    (`resumen_seguimiento_equipo()`) con la misma función real, el
    frontend ya no tiene su propia versión.
11. **Guard defensivo por `dias_mas_proxima`/`dias_restantes` null** —
    teóricamente alcanzable si una Acta pendiente quedara sin
    `fecha_generacion` (hoy `guardar_acuerdo.php` siempre la completa, así
    que no es explotable ahora, pero es el mismo tipo de bug "Vence hoy"
    engañoso ya corregido una vez para este módulo). Agregado el guard en
    `ringDeUsuario()`/`computeFilasBase()` (JS): si `dias_mas_proxima` es
    `null`, cae a un badge "Sin fecha" y aro neutral en vez de "crítico".
12. **Comentario desactualizado**: `secciones.php` decía "superdesarrollador
    -> los 4 módulos", ya son 5 desde que se agregó Seguimiento.

**Descartado explícitamente, no son bugs**: el bucket "Sin usuario
asignado" no reaparece — se sacó por pedido explícito del usuario, no es
un olvido. Llamar `barrer_actas_vencidas()` desde 2 funciones (redundante
en la misma sesión de uso) se dejó así a propósito — es el mismo criterio
defensivo que ya usa el resto del proyecto (cada función de listado se
autocura sola), la duplicación de la llamada es más barata que el riesgo
de que alguna quede sin el barrido si se la llama de forma independiente
en el futuro.

**Probado**: `php -l`/`node --check` limpios en los 5 archivos tocados.
`inicialesUsuario()` verificado con nombres reales incluyendo un caso con
punto (función pura, sin conexión a base). El SELECT de
`resumen_seguimiento_equipo()` con el campo `iniciales` nuevo, corrido
directo contra la base real como fragmento SELECT suelto (NUNCA la función
completa, por la lección de la corrección de arriba) — coincide exacto.
La lógica de filtros/ring/badges (incluida la del anillo para Vencidas y
el guard de `dias_mas_proxima` null) simulada con datos sintéticos en
Node — los 4 filtros y los 2 casos límite nuevos (usuario con vencidas,
usuario con pendientes sin fecha) se comportan como se espera. **Todavía
sin probar en navegador real.**

## Bug real de la campanita: "Acta Asignada" no avisaba tras resubir el mismo cliente (2026-08-27, mismo día)

El usuario reportó un caso concreto: subió Cuotas para un cliente
(YUCAILLA PADILLA RENE WILFRIDO, `pos_id EPV3329`, asignado a JAVIER
MALDONADO — el mismo usuario que hizo la subida), se movió entre módulos,
y la campanita nunca marcó la Acta como nueva/sin ver.

**Investigado con datos reales, de solo lectura**: el backend estaba
devolviendo la Acta precargada correctamente (`usuarioIdDePosId()`/
`listar_actas_precargadas_pendientes()` resuelven bien, `categorias=4`,
confirmado). El bug real estaba en el frontend: `assets/js/alertas-firma.js`
guarda qué notificaciones ya se vieron en `localStorage`, con una clave
armada solo con `pos_id+trimestre+año` (`claveAsignada()`). Ese MISMO
cliente ya se había usado en una prueba de otra sesión un día antes
(2026-08-26, ver sección "Rebate: bug real..." más arriba) — si en algún
momento se abrió la campanita con una Acta Asignada de ese cliente/
trimestre pendiente, quedó marcada como "vista" en el navegador para
siempre, aunque las filas reales de `repositorio_cuota_cliente` de hoy
sean completamente nuevas (`id` 381-385, `created_at`=`updated_at` de
hoy). La clave no tenía forma de distinguir "la misma Acta de ayer, ya
vista" de "una reasignación nueva de hoy".

**Corregido**: `listar_actas_precargadas_pendientes()` ahora también
devuelve `actualizado_en` (el `updated_at` MÁS RECIENTE entre las filas
del grupo) — `claveAsignada()` en JS lo suma a la clave. Si el cliente se
resube/reasigna, `actualizado_en` cambia, la clave cambia con él, y la
Acta vuelve a marcarse como no vista — sin perder el criterio de "ya la
vi" para lo que genuinamente no cambió desde la última vez.

**De paso, mismo bug de condición de carrera que el resto de la auditoría
de hoy**: `cargarAlertas()` (la campanita se puede refrescar por 3 caminos
a la vez — botón manual, cambio de módulo, sondeo de 5 min) no tenía guard
contra una respuesta vieja pisando a una más nueva. Corregido con el mismo
patrón de token de request ya aplicado en Historial/Gestión de Usuarios/
Repositorios/Seguimiento.

**Aclaración de diseño, no un bug**: la app NO tiene notificaciones push
en tiempo real en ningún módulo (decisión explícita documentada, sin
Firebase/WebSockets) — la campanita sondea cada 5 minutos y se refresca
además al cambiar de módulo o tocar "Actualizar". Historial/Gestión de
Usuarios/Repositorios NO sondean nunca, solo se actualizan al entrar o
refrescar a mano.

**Probado**: `php -l`/`node --check` limpios. La query real de
`listar_actas_precargadas_pendientes()` (con `actualizado_en` nuevo)
corrida como SELECT suelto para `usuario_id=8` — confirma
`actualizado_en=2026-08-27 10:00:11`, distinto a cualquier clave vieja que
pudiera existir en localStorage de una prueba anterior. **Todavía sin
probar en navegador real** — falta que el usuario confirme que, tras este
fix, resubir/reasignar un cliente sí dispara el punto rojo de nuevo.

## Actas precargadas: filas vacías espejo en Cabeceras/Rumas/Perchas (2026-08-27, mismo día)

Pedido explícito: al cargar una Acta precargada (Repositorio de Cuotas
Trimestrales), Meta de Compras ya llegaba con sus filas bloqueadas y fijas
(ver "Repositorio de Cuotas trimestrales..." más arriba), pero
Cabeceras/Rumas/Perchas seguían arrancando con **1 sola fila vacía** de
siempre (el default de cualquier Acuerdo nuevo) — el asesor tenía que
clickear "Agregar Fila" a mano tantas veces como categorías trajera la
precarga para tener una fila por cada una.

**Implementado en `assets/js/registrar.js`, sin tocar backend/esquema**:
- `generarFilasVaciasOtrasTablas(cantidadLineasMeta)` (nueva) — completa
  Cabeceras/Rumas/Perchas hasta tener tantas filas como líneas trajo Meta
  de Compras (`p.lineas.meta_compra.length`), reusando
  `addCabeceraRow()`/`addRumaRow()`/`addPerchaRow()` normales (empiezan en
  1 porque `poblarTablasConLineas()` ya puso la primera). **Las filas
  quedan 100% vacías** (sin Segmento/Categoría/Marca sugeridos) — a
  propósito, ver el porqué más abajo.
- `bloquearAgregarOtrasTablas()`/`desbloquearAgregarOtrasTablas()` (nuevas)
  — deshabilitan/rehabilitan los 3 botones "Agregar Fila"
  (`ac-add-cabecera-row`/`ac-add-ruma-row`/`ac-add-percha-row`). **A
  diferencia de Meta de Compras** (que bloquea agregar Y eliminar, tabla
  100% fija): acá solo se bloquea **agregar** — "Eliminar Fila" se deja
  intacto, para que el asesor pueda sacar una fila de más si una categoría
  no lleva Cabecera/Ruma/Percha. Llamadas desde `aplicarPrecarga()` (junto
  a `bloquearFilasPrecargadas()`), y revertidas en
  `limpiarFormularioParaNuevoAcuerdo()`/`aplicarBorrador()` (mismo patrón
  ya existente para el botón de Meta de Compras — un Borrador o el
  siguiente Acuerdo nunca deben arrastrar este bloqueo).
- **Probado**: `node --check` limpio. No probado en navegador real todavía.

**Por qué las filas quedan vacías (no autocompletadas) — confirmado
investigando el flujo real, respuesta a la pregunta del usuario**: el
Excel de Cuotas que sube JW solo trae `CEDI, CLIENTE, PLAN, CATEGORIAS,
CONCAT, <mes1>, <mes2>, <mes3>` — ninguna columna de Subcategoría ni Marca.
Confirmado en `obtener_precarga_detalle()` (`includes/functions.php`): ni
siquiera el `categoria`/`marca` de las líneas de **Meta de Compras**
(donde SÍ hay bloqueo real) vienen del Excel — se resuelven con un
fallback de **continuidad con Actas anteriores del mismo cliente**
(`JOIN` a `repositorio_acuerdo_lineas` por `pos_id`+`sector`, la última
Acta con ese Sector) o quedan `null` si no hay historial. Cabeceras/Rumas/
Perchas ni siquiera tienen ese fallback armado — nunca tuvieron de dónde
sacar una sugerencia.

**Confirmado: sí, agregar 2 columnas (Subcategoría + Marca) al Excel de
Cuotas resolvería esto de raíz** — no es una suposición, es directo de
cómo está armado el resto del sistema:
- Cabeceras/Rumas usan el árbol Segmento→Categoría→Marca (sin nivel
  Sector) y Perchas usa solo Marca — **el mismo par Categoría+Marca**
  (columna `categoria`/`marca` de `repositorio_productos`, ver
  `getters/acuerdo_catalogo.php`) que ya identifica un producto en Meta de
  Compras. Si el Excel trajera esas 2 columnas por fila, el mismo
  match/resolución que hoy ya existe para Meta de Compras (o uno más
  simple, directo por texto — sin depender de historial) alcanzaría para
  las 4 tablas a la vez, no sería un desarrollo separado por tabla.
- **Ojo, esto sigue siendo trabajo de código pendiente si se agregan las
  columnas** (no "se arregla solo con que JW suba el Excel distinto") —
  haría falta: 1) que `repositorio_parsear_cuotas()`
  (`includes/repositorio_import.php`) lea las 2 columnas nuevas, 2) que
  `repositorio_cuota_cliente` las guarde (columnas nuevas o replantear el
  guardado), 3) que `obtener_precarga_detalle()` las use en vez del
  fallback de historial para Meta de Compras, y 4) armar el mismo llenado
  para Cabeceras/Rumas/Perchas (hoy ese código no existe, recién se
  agregarían filas VACÍAS con este cambio, no con datos).
- **Recomendación si el usuario decide pedirlo**: mismo texto que JW ya
  usa para Rebate (`SUBCATEGORIA`), para no introducir un 3er vocabulario
  distinto — "Subcategoría" y "Marca" como columnas nuevas, mismo criterio
  de nombres que ya maneja el resto del proyecto.

**Ronda 2, mismo día — espejar identidad (no solo generar filas vacías) +
bloquear campos igual que Meta de Compras, probado en navegador real**:
pedido explícito de subir el nivel — "así mismo como la tabla 1, estos no
podrán modificar los campos, solo los precios, y claro eliminará nomás".

- `espejarIdentidadOtrasTablas(lineasMeta)` (nueva, `assets/js/registrar.js`)
  — la fila `i` de Cabeceras/Rumas/Perchas corresponde a la línea `i` de
  Meta de Compras (mismo orden/conteo, ver arriba). Si esa línea trajo
  Segmento+Subcategoría+Marca los 3 resueltos (típicamente por
  continuidad con Actas anteriores, ver `obtener_precarga_detalle()`), se
  copia esa identidad a Cabecera/Ruma (`_combo.sugerir(...)`) y a Percha
  (`_comboMarca.sugerir(marca)`), y esos campos quedan bloqueados
  (`disabled` + clase `ac-combo-input-precargado`, mismo look que Meta de
  Compras) — **precios/montos siguen editables**, nunca se tocan acá. Si
  la línea de origen NO está completa (ej. Sector resuelto pero
  Subcategoría/Marca no, caso real más común hoy — ver arriba, el Excel no
  trae esos 2 datos), la fila espejo queda con el cascade normal, sin
  bloquear nada — nunca se llama `.sugerir()` con datos a medias (dejaría
  el texto literal "null" en el campo).
- "Eliminar Fila" se mantiene SIN bloquear en estas 3 tablas (a
  diferencia de Meta de Compras, que bloquea agregar Y eliminar) — el
  asesor puede sacar una fila de más si una categoría no lleva
  Cabecera/Ruma/Percha, tal como se pidió.

**Bug real encontrado y corregido probando en navegador (Playwright, sesión
falsa de solo lectura contra la base real — no HTTP, sesión seteada
directo por `$_SESSION` en un script temporal borrado al terminar,
usuario real `FRANKLIN SALCEDO`/uid=6, único cliente con Cuotas reales
pendientes Y usuario asignado en la base hoy)**: el orden original de
llamadas en `aplicarPrecarga()` era `generarFilasVaciasOtrasTablas()`
**después** de `bloquearFilasPrecargadas()` — pero
`generarFilasVaciasOtrasTablas()` llama a
`addCabeceraRow()`/`addRumaRow()`/`addPerchaRow()`, y cada una de esas
termina en `actualizarBloqueoPorDistribuidor()` (función preexistente,
`assets/js/registrar.js` línea ~394) que **rehabilita TODOS los
`.seg-input` de Meta de Compras/Cabeceras/Rumas de golpe** (sin distinguir
cuáles venían bloqueados por precarga) cada vez que se agrega una fila con
Distribuidor ya elegido. Resultado real observado: el campo Segmento de
Meta de Compras quedaba SIN bloquear (aunque el valor SÍ era el correcto)
después de cargar la precarga — Sector seguía bloqueado bien porque
`actualizarBloqueoPorDistribuidor()` no lo toca, pero Segmento sí.
**Corregido reordenando** — `generarFilasVaciasOtrasTablas()` ahora corre
ANTES de `bloquearFilasPrecargadas()`, para que el bloqueo de Meta de
Compras sea la última operación sobre esas filas (nada corre después que
vuelva a llamar `addPurchaseRow()`/similar). `espejarIdentidadOtrasTablas()`
y `bloquearAgregarOtrasTablas()` van después sin problema — ninguna de las
dos llama a `addXRow()`, no disparan el efecto colateral.

**Probado de punta a punta en navegador real (Playwright, servidor local
`php -S`, 2 escenarios)**:
1. **Cliente real** (`EPV3329`, Q2 2026, 4 líneas de Meta de Compras
   reales) — confirmado tras el fix: Segmento bloqueado con el valor
   correcto en las 3 líneas que lo resolvieron, línea 4 (Sector LIQUIDO)
   con Segmento sin resolver queda abierta (comportamiento ya documentado,
   sin cambios); Sector bloqueado en las 4; Subcategoría/Marca quedan
   editables en las 4 (este cliente no tenía historial, real, no un caso
   armado) — 4 filas generadas en Cabeceras/Rumas/Perchas, ninguna
   mirroreada (esperado, ninguna línea de origen estaba 100% resuelta),
   los 3 botones "Agregar Fila" bloqueados, "Eliminar Fila" habilitado en
   las 3.
2. **Datos sintéticos vía interceptación de red** (`page.route()` en
   Playwright — nunca tocó la base real, solo devolvió una respuesta JSON
   fabricada en memoria del navegador para el mismo endpoint) con 3 líneas
   de Meta de Compras, 2 de ellas con Segmento+Subcategoría+Marca 100%
   resueltos (`CREMA/LAVAVAJILLAS/LAVA`, `BARRA/ROPA/EL MACHO`) y 1 sin
   resolver (`POLVO`, categoria/marca `null`) — confirmado exacto:
   Cabeceras/Rumas fila 1 y 3 llegan con la identidad copiada y BLOQUEADA,
   fila 2 queda abierta; Perchas mismo patrón con Marca (`LAVA`/`EL
   MACHO` bloqueados, la del medio abierta).
- `node --check` limpio en ambas rondas.

**Aclaración del usuario, mismo día — ya NO hace falta resolver "OTRAS
CATEGORIAS"**: JW confirmó que van a dejar de trabajar esa categoría
(el cajón genérico sin desglosar que aparece en el Excel real de
Liquidación Directa, ver "Alcance real de Acuerdos Comerciales" más
arriba) — no hace falta construir ninguna lógica para resolverla contra
un Sector real. **Nota importante para la próxima sesión**: hoy en la base
real (`repositorio_cuota_cliente`) SÍ existen filas viejas con
`sector='OTRAS CATEGORIAS'` y montos reales (ej. $10-$20/mes, de una
subida de prueba anterior) — como no matchea ningún Sector real,
`resolverSectorReal()` la deja tal cual con aviso, y como el monto es > 0
(no $0), NO se filtra por la regla de "categoría en $0 se descarta" — si
esas filas siguen `pendiente_uso` cuando alguien cargue esa precarga, van
a aparecer igual como una línea de Meta de Compras con Sector
"OTRAS CATEGORIAS" sin resolver. No se tocó nada de esto todavía (no hay
pedido explícito de limpiarlas, y borrar filas de `repositorio_cuota_cliente`
requeriría que el usuario lo haga desde la UI de Repositorios — Descartar —
o corriendo un DELETE él mismo, Claude no puede).

## Actas Asignadas: CEDI del Excel gana sobre el maestro de Alicorp (2026-08-28)

Investigación larga, disparada porque el usuario probó como JAVIER
MALDONADO subiendo el Excel de prueba de Cuotas y no le salía su propia
Acta asignada en la campanita. Se auditó a fondo antes de tocar nada:

- **Confirmado con el Excel real de Liquidación de JW** (no solo el de
  prueba): `EPV3329`/"YUCAILLA PADILLA RENE WILFRIDO" tiene venta real
  registrada bajo CEDI="JAVIER MALDONADO", pero
  `repositorio_locales_supervisores_cliente` (el maestro de Alicorp) dice
  `supervisor=FRANKLIN SALCEDO, canal=MAYORISTA` para ese mismo pos_id — un
  choque real, no un error de lectura.
- **Auditoría más amplia** (62 clientes únicos del Excel real de
  Liquidación Directa Q2 2026, comparados 1 a 1 contra el maestro,
  ignorando acentos): **29 de 62 (47%) difieren**. De esos, solo 4
  involucran a las 2 únicas cuentas reales de canal Directo que existen
  hoy (Javier Maldonado, Franklin Salcedo) — el resto son nombres
  (`CARLOS PROANO`, `XAVIER ALVARADO`, `MARCELO ESPINOZA`) que **no tienen
  cuenta creada en absoluto** todavía.
- **El portafolio real de Javier Maldonado (312 clientes, todos
  COBERTURA) no se superpone ni un solo cliente con los que el Excel real
  le atribuye a él (6 clientes, todos canal MAYORISTA en el maestro)** —
  conclusión: el maestro parece guardar quién **reparte/visita**
  operativamente (canal MAYORISTA ahí = Franklin Salcedo/Garry Saint),
  mientras que el Excel real de JW guarda quién **negocia el Acuerdo
  Comercial** (Javier Maldonado) — dos conceptos reales distintos que el
  maestro mezcla en un solo campo `supervisor`. Para lo que le importa a
  ESTA app (quién gestiona el ADN), el Excel tiene la razón.
- **Adrián Vásquez (canal Distribuidor) sí coincide** con el Excel real de
  Distribuidores para su única empresa real (`ASERTIA COMERCIAL SA` ↔
  `ASERTIA`, 216 filas) — pero de las 10 distribuidoras reales que
  aparecen en ese Excel, **9 no tienen ningún usuario real que las
  maneje** en la app hoy (DISTRIORENSE→JAIRO FLORES, DISTRIGRANDA→RODRIGO
  CORDOVA/JHONNY CASTILLO, CAMEL→RODRIGO CORDOVA, COIMFAGI→JHONNY
  CASTILLO, PRODISPRO→LENIN HERNANDEZ, FREIRE→JULIO CABRERA, LOJANO→JHONNY
  CASTILLO — nombres reales encontrados en el maestro vía `tipo_distribuidor`,
  ninguno tiene cuenta creada). `canalDeSupervisor()` clasifica correcto a
  los 3 candidatos de Directa y a los 7 de Distribuidor apenas se les cree
  la cuenta — la lógica de canal nunca fue el problema, solo faltan las
  cuentas.

**Decisión del usuario, acotada a propósito**: para el caso puntual de
Actas Asignadas/Precargadas (este repositorio), el CEDI del Excel de
Cuotas **siempre gana** sobre el maestro — nunca se tocó
`canalDeSupervisor()`, `acuerdo_distribuidores.php` (el combo de Local en
Registrar sigue filtrando por el maestro, sin cambios) ni ningún otro
punto del proyecto.

**Implementado — 3 lugares, todos con el mismo criterio**:
- `usuarioIdDeCuota($mysqli, $posId, $trimestre, $anio)` (nueva,
  `includes/functions.php`) — busca `cedi_excel` de esa fila en
  `repositorio_cuota_cliente`, lo matchea contra `usuario` O `supervisor`
  de un usuario activo; si no matchea nada real, cae a `usuarioIdDePosId()`
  (el maestro, sin cambios) como respaldo.
- `getters/obtener_acta_precargada.php` — usa `usuarioIdDeCuota()` en vez
  de `usuarioIdDePosId()` para decidir si el usuario de sesión puede ver
  esta precarga.
- `listar_actas_precargadas_pendientes()` y `resumen_cuotas()`
  (`includes/functions.php`) — mismo criterio vía `LEFT JOIN` +
  `COALESCE(u_cedi.id, u_master.id)`, para que la campanita y el modal de
  Resumen coincidan siempre con lo que decide `obtener_acta_precargada.php`.
- **`getters/guardar_acuerdo.php` — el bug más grave, encontrado después**:
  el chequeo de propiedad al GUARDAR seguía mirando solo el maestro — un
  asesor con una Acta ya visible en su campanita (gracias al fix de
  arriba) se topaba con "no pertenece a tu cartera de clientes" al
  intentar guardar. Se agregó una segunda vía: si el guardado viene
  marcado como originado de esa precarga puntual (mismo pos_id) Y
  `usuarioIdDeCuota()` confirma que el dueño real es el usuario de la
  sesión, se permite igual — nunca abre la puerta a un `origen_precarga`
  inventado, sigue consultando el dato real de la base, no lo que mande
  el navegador.

**Probado con datos reales de solo lectura**: `usuarioIdDeCuota('EPV3329',
2, 2026)` da uid=8 (Javier Maldonado, correcto); la campanita de Franklin
Salcedo para ese mismo cliente ya no muestra nada; `resumen_cuotas()`
muestra a Javier con 1 Acta pendiente y cuenta real; la simulación del
chequeo de `guardar_acuerdo.php` pasa de "NO PASA" a "GUARDADO PERMITIDO"
para el caso real.

## Resumen — Cuotas Trimestrales: aviso de Actas que van a chocar (2026-08-28, mismo día)

Pedido explícito, mismo hilo: mientras se investigaba lo de arriba, el
usuario preguntó si "no veo mi Acta" podía deberse a la regla de "solo un
Acta activa por Local+Período" (`guardar_acuerdo.php`, 2026-08-23) —
verificado que NO era el caso ahora mismo (0 de los 12 grupos
`pendiente_uso` reales chocan hoy), pero el usuario pidió agregar un aviso
preventivo en el modal "Resumen — Cuotas Trimestrales" para cuando sí
pase: mostrar, como cuadro comparativo, qué Acta precargada no se va a
poder generar y con qué Acuerdo ya existente choca.

**Diseñado primero en Claude Design** (pedido explícito: "diseñálo primero
... quiero ver si me entendiste la idea") — mockup publicado, aprobado por
el usuario con un solo agregado: mostrar también a quién se le iba a
asignar la Acta en el lado izquierdo. Aplicado tal cual a código real.

- `resumen_cuotas()` (`includes/functions.php`) — nueva clave `chocan`:
  para cada grupo `pendiente_uso` (pos_id+trimestre+año), si ya existe un
  `repositorio_acuerdos` con ese mismo pos_id+año+mes_inicio+mes_fin y
  `estado NOT IN ('borrador','anulado')` (misma regla exacta que
  `guardar_acuerdo.php`), arma un registro con: `local` (pos_name),
  `trimestre`/`anio`, `asignado_a` (vía `usuarioIdDeCuota()`, puede ser
  `null` si nadie real lo tiene identificado todavía), y del Acuerdo
  existente: `documento_no`, `usuario` (quién lo generó), `fecha`.
- `assets/js/repositorios.js` — `renderResumenChoque()`/`filaResumenChoque()`,
  llamada desde `abrirResumen()` junto a las 2 secciones que ya existían.
  Oculta con la clase `.hidden` genérica del proyecto (no con el atributo
  `hidden` — sin conflicto de especificidad porque esta clase no declara
  ningún `display` propio en ningún selector).
- `assets/css/style.css` — bloque `.ac-choque-*` nuevo: fila con
  `border-left` naranja (mismo tono ya usado en `.ac-badge-urgente`/las
  cajas de vencimiento), 2 columnas (izquierda = precarga en fondo crema
  tenue, derecha = Acuerdo existente en fondo gris neutro) separadas por
  una flecha, badge azul (`--color-secondary-fixed`/`--color-primary`,
  mismo par que ya usa `.ac-sidebar-nav li.active`) para el número de Acta
  existente. Responsive: bajo 700px pasa a 1 columna con la flecha
  rotada 90°.
- **Probado con Playwright, datos sintéticos inyectados por red
  (`page.route()`, nunca tocó la base real)** — desktop 1280px y mobile
  390px, ambos con screenshot real revisado: sin elementos superpuestos,
  la fila entra completa en ambos anchos, el campo "Se iba a asignar a
  ..." se ve correcto cuando hay dueño identificado y "Sin usuario
  identificado todavía" cuando no. `node --check`/`php -l` limpios.

## Cuotas: SUBCATEGORIA/MARCA opcionales en el Excel → autocompletan y bloquean la Acta Precargada (2026-08-28)

El usuario había pedido en otra sesión agregar columnas SUBCATEGORIA/MARCA
al Excel de prueba de Cuotas (`datos/repositorio prueba trimestral.xlsx`,
raíz del repo, NO dentro de `Acuerdos_Comerciales/datos/`) — se agregaron
los encabezados pero las 60 filas quedaron sin valores (ver sesión de hoy,
"llenar el Excel de prueba"). Con el archivo ya lleno con combinaciones
reales del catálogo, pidió conectar esas 2 columnas al pipeline real.

**Antes de esto**: `repositorio_parsear_cuotas()` solo leía CEDI/CLIENTE/
PLAN/CATEGORIAS + meses. `obtener_precarga_detalle()` resolvía Categoría/
Marca de la línea de Meta de Compras SOLO reusando el historial del
cliente (la línea más reciente de una Acta anterior para ese mismo
pos_id+sector) — si no había historial, quedaban vacías para que el
asesor las complete a mano.

**Implementado**:
- `repositorio_parsear_cuotas()` (`includes/repositorio_import.php`): lee
  SUBCATEGORIA/MARCA si el archivo las trae — **opcional**, mismo criterio
  que CEDI/PLAN (si no están, `xlsx_col()` da null y el resto sigue
  exactamente igual que antes; el formato real de JW, hoy, NO las trae).
- `resolverProductoCuota($mysqli, $sector, $subcategoriaCruda, $marcaCruda)`
  (nueva, `includes/functions.php`) — matchea contra `repositorio_productos`
  con la MISMA tolerancia plural/singular ya usada por
  `buscarRebateProducto()` (Rebate, 2026-08-27) — mismo tipo de desajuste de
  nombres ya documentado (ej. "LIQUIDOS" del Excel vs "LIQUIDO" del
  catálogo real). Solo devuelve algo si el match es único; si no, `null`.
- `obtener_precarga_detalle()`: nueva 1ra prioridad — si la fila de Cuota
  trae subcategoria+marca Y `resolverProductoCuota()` matchea, ESO se usa
  (dato cierto, vino directo de JW). Si no matchea o el Excel no las trae,
  cae al criterio viejo (historial del cliente), sin cambios ahí. Segmento
  también se resuelve del match cuando lo hay (antes solo salía del
  historial o del "único Segmento posible para ese Sector").
- `cuotas_guardar.php`: guarda `subcategoria`/`marca` en el UPSERT — con
  fallback defensivo (si el `ALTER` de abajo no se corrió todavía, cae al
  INSERT viejo sin esas 2 columnas, no rompe la subida).
- `repositorios.js`: `CONFIG.cuotas.columnasPreview` — 2 columnas nuevas
  editables en la previsualización (mismo orden que el Excel real),
  después de "Categoría" — así el superdesarrollador puede corregir a mano
  el texto crudo de JW ANTES de guardar si algo no va a matchear bien.
- **`datos/cuota_cliente_schema.sql`** (raíz del repo) — bloque de
  migración nuevo al final del archivo (la tabla YA EXISTE en producción,
  esto es un `ALTER`, no volver a correr el `DROP TABLE`/`CREATE TABLE` de
  arriba):
  ```sql
  ALTER TABLE repositorio_cuota_cliente
    ADD COLUMN subcategoria VARCHAR(100) NULL AFTER sector,
    ADD COLUMN marca VARCHAR(100) NULL AFTER subcategoria;
  ```
  **✅ EJECUTADO por Claude 2026-08-28** — primer uso real de la excepción
  puntual a la regla de solo lectura (ver sección "⚠️ Excepción..." al
  inicio de este archivo): SQL mostrado al usuario, confirmación explícita
  recibida, `ALTER` corrido, verificado después con `DESCRIBE` de solo
  lectura (`subcategoria`/`marca`, ambas `varchar(100) NULL`, en la
  posición correcta). El pipeline ya está activo en producción.

**Probado**: `php -l`/`node --check` limpios en los 5 archivos. `resolverProductoCuota()`
corrida directo contra el catálogo real (solo lectura) con 6 combos reales
(incluida la variante plural "LIQUIDOS") — todas resuelven con el
`segmento` correcto; un combo inventado da `SIN MATCH` sin falsos
positivos. **No se pudo probar `repositorio_parsear_cuotas()` de punta a
punta contra el Excel real en esta sesión** — el CLI local de PHP
(`C:\xampp\php\php.exe`) no tiene la extensión `zip` habilitada
(`ZipArchive` no existe), a diferencia del servidor real donde la app ya
lee `.xlsx` sin problema hoy. La lectura de las 2 columnas nuevas es una
adición mecánica sobre el mismo patrón exacto que ya usan CEDI/PLAN
(`xlsx_col()`, opcional) — bajo riesgo, pero falta la confirmación real en
navegador. **Todavía sin probar en navegador real** — falta resubir el
Excel de prueba ya lleno (con el `ALTER` ya corrido) y confirmar que una
Acta Precargada nueva sale con Categoría/Marca bloqueadas directo del
Excel.

## Bug real: Actas Precargadas nunca buscaban Rebate % (siempre quedaba en 0) — corregido (2026-08-28)

Con Categoría/Marca ya resolviéndose bien (ver sección anterior), el
usuario notó que el Rebate % de esas líneas seguía en 0. Causa real:
`obtener_precarga_detalle()` tenía `'rebate_pct' => 0` **hardcodeado**,
nunca llamaba a `buscarRebateProducto()` (la misma función que ya usa la
búsqueda en vivo de Registrar desde el 2026-08-27) — quedó así desde que
se construyó Fase 2 (2026-08-25), antes de que Rebate se conectara.

**Corregido**: dentro del mismo bucle que arma cada línea de Meta de
Compras, se calcula Ciudad/Canal una sola vez (mismo criterio EXACTO que
`buscarYAplicarRebate()` en `registrar.js`: Ciudad="TODAS" si es
Distribuidor, si no el CEDI real; Canal=DISTRIBUIDOR/DIRECTA) y se llama a
`buscarRebateProducto()` con el Sector/Categoría/Marca ya resueltos
(cualquiera sea el origen: match directo del Excel o historial). Sin
match, sigue en 0 (editable), igual que la búsqueda en vivo.

**Probado con datos reales de solo lectura** (`obtener_precarga_detalle()`
no escribe nada, se puede llamar directo): cliente YUCAILLA PADILLA
(`EPV3329`, Q2 2026) — de sus 4 categorías, 3 resuelven Rebate real
(CREMA 4%, LIQUIDO 1.5%, POLVO 4%); BARRA da 0 — verificado que es
correcto: `repositorio_rebate_producto` NO tiene ninguna fila para
BARRA/LAVAVAJILLAS/LAVA (el Sector BARRA solo tiene Rebate cargado para
la marca EL MACHO) — dato genuinamente faltante en el repositorio, no un
bug de matching. **Todavía sin probar en navegador real.**

## Bug real: "Descargar Excel" con "Todos los períodos" mezclaba trimestres distintos en una sola hoja (2026-08-28)

El usuario descargó el Excel de Historial para JAVIER MALDONADO con el
filtro de período en "Todos los períodos" y el archivo salió con 6 meses
(ENERO-JUNIO) bajo un título fusionado que decía "VENTA Q1".

**Causa real, confirmada con datos reales**: Javier tenía 4 Actas viejas
de Q1 (`mes_inicio=0/mes_fin=2`) más una recién generada desde una Acta
Precargada, correctamente guardada en Q2 (`mes_inicio=3/mes_fin=5`) — la
primera vez que un cliente tiene Actas en 2 trimestres distintos a la vez
en este proyecto. `exportar_cuota_categoria.php` arma el título "VENTA Qx"
mirando SOLO el primer mes de `$mesesCols` (`intdiv($mesesCols[0], 3) + 1`)
pero dibuja una columna por cada mes distinto sin chequear que todos sean
del mismo trimestre — con "Todos los períodos" la consulta trae ambos
grupos de Actas juntos, así que el título quedó mal (decía "Q1" con datos
de 2 trimestres). Mismo mecanismo afecta a
`exportar_cuota_categoria_distribuidor.php` (hereda `$trimestreActivo` del
archivo de Directa, nunca parsea `$_GET` por su cuenta).

**No es un bug de "sumar mal"** — cada línea sigue siendo su propia fila
con su propio dato real (documentado desde el diseño original, "una fila
por línea, sin agrupar/sumar"). El problema es puramente el TÍTULO/columnas
de mes, que asumen un solo trimestre porque este Excel replica el archivo
real de JW, que SIEMPRE es de un solo trimestre — nunca se diseñó para
mezclar períodos.

**Corregido con la solución más segura, sin rediseñar el formato**: se
exige un trimestre puntual (Q1-Q4) para poder descargar —
`exportar_cuota_categoria.php` rechaza la descarga (código 400, mensaje
claro) si `trimestre=0` ("Todos"), ANTES de correr cualquier consulta —
protege también a la variante Distribuidor de yapa, sin tocar ese archivo.
Del lado del cliente, `historial.js` intercepta el click del link de
descarga y avisa con un toast si el filtro está en "Todos los períodos",
para que el usuario ni llegue a recibir el archivo de error.

**De paso, confirmado con el usuario y verificado en el código — sin
relación de causa, pero se aclaró en la misma conversación**: el Rebate %
de una Acta se guarda CONGELADO al momento de generarla
(`guardar_acuerdo.php` línea ~158, toma el valor que venía en el
formulario, nunca vuelve a consultar el repositorio de Rebate) — un cambio
posterior al % general en `repositorio_rebate_producto` NUNCA altera
retroactivamente Actas ya generadas. Esto ya estaba bien implementado
desde antes (2026-08-18) y los cambios de hoy (conectar Rebate a Actas
Precargadas) no lo tocan — solo afectan qué se PRE-LLENA en el formulario
antes de guardar, nunca el guardado en sí.

**Probado**: `php -l`/`node --check` limpios. La causa se confirmó con
datos reales de solo lectura (Actas de Javier Maldonado en Q1 y Q2
simultáneas). **Todavía sin probar en navegador real** — falta confirmar
que "Todos los períodos" ahora bloquea la descarga con el aviso, y que
eligiendo Q1 o Q2 específico el Excel sale limpio (3 meses cada uno).

**Ronda 2, mismo día — se extendió a Año, se cambió el aviso de toast a
modal, y el año se autoselecciona por defecto:**

- **El mismo bug puede ocurrir entre AÑOS del mismo trimestre** (un índice
  de mes 0-11 no tiene año) — no solo entre trimestres distintos con "Todos
  los períodos". La guarda de `exportar_cuota_categoria.php` ahora exige
  también `$anio` puntual (`if (!$trimestreActivo || !$anio)`), no solo
  trimestre — mismo mensaje de error extendido, protege igual a la variante
  Distribuidor por herencia.
- **Toast → modal SweetAlert2** (pedido explícito, "la ventanita grandecita
  que aparece a mitad de pantalla" — mismo componente ya usado en todo el
  proyecto para confirmaciones/avisos, ver `confirmarYEliminarAcuerdo()`):
  `exportarCuotaLink` en `historial.js` ahora chequea trimestre Y año, y
  si falta alguno de los dos muestra `Swal.fire({icon:'warning', ...})`
  con el mensaje explicando por qué (mismo texto que el error del
  servidor) y cuál de los dos falta puntual ("el período", "el año", o "el
  período y el año").
- **Indicación visual de qué campos corregir, sin usar una captura
  estática** (el usuario pidió "una captura o indicando qué zona es" — se
  optó por resaltar el control real en vez de una imagen, para que nunca
  quede desactualizada si el layout cambia): al cerrar el modal,
  `resaltarFiltroPeriodo()` hace scroll a la tarjeta de filtros y le
  agrega un aro pulsante (`.ac-filtro-resaltado`, `style.css`, 2 pulsos de
  1.3s, respeta `prefers-reduced-motion`) al/los `<select>` que falten —
  aplicado sobre el wrapper de "select bonito" (`.ac-select-bonito`), no
  el `<select>` nativo oculto detrás.
- **Año se autoselecciona al año en curso por defecto** (pedido explícito
  del usuario) — `components/historial/historial.php`: si no vino
  `anio` explícito por query, se usa `date('Y')` **solo si ese año
  realmente tiene Acuerdos del usuario** (`in_array` contra
  `$aniosDisponibles`, calculado ANTES ahora, se reordenó el archivo para
  poder usarlo en la decisión); si no, cae a "Todos los años" como antes,
  para no mostrar una tabla vacía por defecto a alguien sin Actas del año
  en curso todavía. No se tocó `getters/listar_historial.php` — como el
  `<select>` de año ya carga con el valor correcto desde el SSR, los
  refrescos AJAX posteriores (que leen `anioSelect.value`) heredan el
  mismo default sin cambiar ese getter.
- **Probado**: `php -l`/`node --check` limpios en los 3 archivos.
  **Todavía sin probar en navegador real** — falta confirmar que el año en
  curso queda preseleccionado al entrar a Historial, que el modal aparece
  con el texto correcto según qué falte, y que el aro pulsante resalta el
  control correcto en los 3 casos (falta trimestre, falta año, faltan
  los dos).

## Excel de Historial (canal Directo): VALIDACIÓN de Visibilidad autocompletada según CANTIDAD (2026-08-28)

Pedido explícito, con un ejemplo inicial que se contradecía a sí mismo
(cabecera=1→CUMPLE, pero isla=9→NO CUMPLE) — se confirmó con el usuario
vía `AskUserQuestion` antes de tocar código, porque VALIDACIÓN es lo que
dispara el pago real (columnas R/S/T: `IF(VALIDACIÓN="CUMPLE", PAGO, 0)`).
**Regla confirmada**: CANTIDAD > 0 → CUMPLE, CANTIDAD = 0 → NO CUMPLE (el
"9→NO CUMPLE" del ejemplo era un error de tipeo).

- `getters/exportar_cuota_categoria.php` — hoja `VISIBILIDAD `, columnas
  VALIDACIÓN (Cabecera/Isla/Percha, antes siempre vacías "las llena JW a
  mano") ahora llevan una **fórmula real**, no un valor fijo:
  `IF(<celda CANTIDAD de esa columna, misma fila>>0,"CUMPLE","NO CUMPLE")`.
  Sigue siendo editable a mano por JW (no se protege la celda) — si
  corrige CANTIDAD o el propio VALIDACIÓN después de verificar en campo,
  el pago real (R/S/T) se recalcula solo, Excel nativo.
- **Alcance: SOLO canal Directo**, tal como lo pidió el usuario
  ("el excel que generamos de canal directo"). **NO se tocó**
  `getters/exportar_cuota_categoria_distribuidor.php` — su hoja
  `VISIBILIDAD (2)` sigue con VALIDACIÓN vacía "la llena JW a mano" (línea
  ~398), mismo patrón que tenía Directo antes de este cambio. Si se pide
  extenderlo a Distribuidor, es el mismo cambio mecánico ahí (mismas
  columnas CANTIDAD/VALIDACIÓN Cab/Isla/Percha, mismo criterio).
- **Probado**: `php -l` limpio. No se pudo generar el `.xlsx` real end-to-end
  en esta sesión (el CLI local no tiene la extensión `zip`/`xml`
  habilitada, mismo límite ya documentado en otras partes de este
  archivo) — el cambio es mecánico sobre el mismo patrón de fórmulas ya
  usado y probado en las columnas R/S/T de la misma hoja (mismas comillas/
  sintaxis de `IF`). **Todavía sin probar en navegador real** — falta
  descargar el Excel real y confirmar que VALIDACIÓN sale con CUMPLE/NO
  CUMPLE correcto según CANTIDAD, y que el pago (R/S/T) ya no queda en $0
  esperando que JW llene algo a mano.

## Auditoría de tono en mensajes al usuario — sacar razonamiento interno y voseo inconsistente (2026-08-28)

El usuario objetó el modal de "Elegí el período" (sección de arriba) por
exponer razonamiento interno de implementación en un mensaje de cara al
cliente ("esto no debería decírselo al cliente... eso no es un mensaje
profesional de página, aunque a mí me lo puedas explicar") — y pidió
barrer TODO el proyecto por el mismo patrón. De paso, corrigió puntual el
uso de "Elegí" (voseo) — pidió "Elige" en su lugar.

**Regla aplicada de acá en más para mensajes al usuario (toasts, modales
SweetAlert2, respuestas de getters)**: decir QUÉ hacer, nunca explicar POR
QUÉ a nivel de implementación (nombre de archivo real de JW, "esto
generaría un archivo mezclado", nombres de columnas SQL, extensiones de
PHP, rutas de archivos `.sql`) — ese razonamiento vive en los comentarios
de código/este CLAUDE.md, no en la pantalla.

**Corregido**:
- `assets/js/historial.js` — el modal de período (agregado hoy mismo, ver
  sección de arriba) reescrito sin mencionar a JW ni "archivo mezclado":
  ahora dice solo "Elige el período antes de descargar" / "Este archivo se
  genera para un trimestre y año específicos."
- `getters/exportar_cuota_categoria.php` — mismo mensaje simplificado del
  lado del servidor (por si se llega a la URL directo sin pasar por el
  modal): "Elige un trimestre y un año específicos en el filtro de período
  antes de descargar el Excel."
- **6 mensajes que exponían rutas de archivo `.sql` o detalles de
  infraestructura de PHP** (`getters/cuotas_previsualizar_excel.php`,
  `getters/importar_liquidacion.php`, `getters/repositorio_previsualizar_excel.php`,
  `getters/cuotas_guardar.php`, `getters/repositorio_guardar.php` ×2,
  `getters/repositorio_eliminados.php`, `getters/repositorio_eliminar.php`
  ×2, `getters/repositorio_reactivar.php`) — decían cosas como "falta
  correr datos/repositorios_schema.sql" o "el servidor no tiene la
  extensión zip de PHP habilitada". Aunque estas pantallas las usa
  `superdesarrollador` (un usuario de negocio, no un desarrollador),
  exponer nombres de archivo/infraestructura interna sigue sin ser
  profesional — se simplificaron todas a "Avisa al equipo técnico." sin el
  detalle interno (el detalle real sigue en los comentarios del código
  para quien sí tenga que diagnosticarlo).
- **"Elegí" (voseo) → "Elige"**, en los 8 lugares donde aparecía de cara al
  usuario: el modal y guarda de arriba, `components/liquidacion/liquidacion.php`
  ("Elige el cliente correcto..."), `assets/js/repositorios.js`
  ("Elige un año válido."), `components/repositorios/repositorios.php`
  (hint de match ambiguo — de paso también "buscá"/"descartá" → "busca"/
  "descarta" en la misma oración, para no dejarla con 2 tiempos verbales
  mezclados), `getters/liquidacion_resolver_match.php` y
  `assets/js/liquidacion.js` ("elige cuál es..."), y
  `assets/js/registrar.js` (validación de spinner sin confirmar — también
  "hacé" → "haz" en la misma oración, mismo criterio de no mezclar). Las
  demás apariciones de voseo en el resto del proyecto (ej. "borrá",
  "corregilo", "usá", "tipeá") **no se tocaron** — fuera del alcance
  puntual que pidió el usuario (la palabra "Elegí" específicamente), no se
  reescribió el dialecto completo de la app.
- **Descartado a propósito, no son el mismo problema**: mensajes con
  instrucciones operativas para el `superdesarrollador` (ej. "Ese pos_id
  no tiene ningún Acta generada... usá 'No tiene Acta'", "Ya existe una
  cuota guardada... borrá la fila vieja") — son instrucciones de qué hacer
  para un usuario interno de negocio, no exponen razonamiento de
  implementación, se dejaron igual. Tampoco se tocaron los mensajes de
  error crudos de MySQL (`$stmt->error`) que se muestran en la subida de
  Rebate/Participación — son diagnóstico real para quien administra el
  repositorio, no la misma clase de problema que "explicarle al cliente
  cómo está armado el Excel".
- **Probado**: `php -l`/`node --check` limpios en los 12 archivos tocados.

## Excel de Historial (canal Directo): columna PLAN llenada con el "canal" del maestro (2026-08-28)

Última pieza pendiente de este formato de export. Investigado con el
usuario antes de tocar código (`AskUserQuestion`, dos columnas candidatas
del maestro tenían "MAYORISTA" como valor real): la analista de JW que
administra `repositorio_locales_supervisores_cliente` le confirmó al
usuario que PLAN se llena con la columna **`canal`** del maestro
(`COBERTURA`/`MAYORISTA`/`AUTOSERVICIO`), no con `tipo_cliente`
(`A`/`B`/`C`/`D`/`AA`/`AAA`/`PLUS`/`MAYORISTA` — esta también tiene
"MAYORISTA", pero para el cliente de ejemplo del usuario,
`DISTRIBUIDORA SUPERALIANZA S.A.S`, da `PLUS`, no "mayorista").

- **Alcance: solo la hoja "CUOTA CLIENTE - CATEGORÍA"** (la que el usuario
  llamó "hoja de cuota cliente"), no la hoja "VISIBILIDAD" del mismo
  archivo — esa tiene su propia columna PLAN, separada, que sigue vacía
  (no se pidió extender el fix ahí; documentado en el comentario del
  código que probablemente aplicaría el mismo criterio si se pide después).
- `getters/exportar_cuota_categoria.php`: el `SELECT` agregó `d.canal`
  (ya hacía `JOIN` a `repositorio_locales_supervisores_cliente`, no hizo
  falta un JOIN nuevo); `$filasFinal[]` guarda `'plan' => $f['canal'] ??
  ''`; la celda PLAN (antes literal `''`, "vacía a propósito") ahora
  escribe `$g['plan']`.
- **No es el texto exacto que usa JW en su propio Excel** ("AUTOSERVICIO
  INDEPENDIENTE", etc. — investigado a fondo en 2026-08-18, nunca existió
  tal cual en la base) — es el valor real más cercano que sí tenemos
  (`canal`), confirmado como correcto por la analista real de JW, no una
  traducción textual inventada.
- **Probado con datos reales de solo lectura** (mismo `SELECT` ya con
  `d.canal`, corrido contra la base real con las 5 empresas reales que ya
  tienen Actas Directa generadas): las 15 líneas reales dieron
  `PLAN=MAYORISTA` en todos los casos — incluido `DISTRIBUIDORA
  SUPERALIANZA S.A.S`, coincide exacto con lo que el usuario esperaba. `php
  -l` limpio. **No se pudo generar el `.xlsx` real end-to-end en esta
  sesión** (el PHP CLI local no tiene la extensión `zip` habilitada,
  necesaria tanto para leer como para escribir `.xlsx` — mismo límite ya
  documentado varias veces en este archivo) — la verificación fue sobre la
  consulta y los datos reales que alimentan la celda, no sobre el archivo
  final generado. **Todavía sin probar en navegador real** — falta
  descargar el Excel real y confirmar visualmente que la columna PLAN sale
  con el valor correcto.

**Ronda 2, mismo día — extendido a la hoja VISIBILIDAD + columna CONCAT
más ancha:**

- **PLAN también en la hoja "VISIBILIDAD "** (el usuario había pasado por
  alto que esa hoja tiene su propia columna PLAN, separada de la de "CUOTA
  CLIENTE - CATEGORÍA") — mismo criterio exacto, `d.canal` agregado al
  `SELECT` de esa hoja (`$stmtVis`, ya hacía `JOIN` a
  `repositorio_locales_supervisores_cliente`), guardado en
  `$porClienteVis[$cliente]['plan']` y escrito en la celda (antes literal
  `''`, "vacío a propósito"). **Probado con datos reales de solo lectura**:
  10 líneas reales de Cabecera/Ruma/Percha (mismo cliente de la ronda
  anterior, `ACOSTA SANTAMARIA EDGAR PATRICIO`/`DISFALEP S.A.S.`) dan
  `PLAN=MAYORISTA` en las 3 tablas — coincide con la hoja de Cuota.
- **Columna CONCAT más ancha en "CUOTA CLIENTE - CATEGORÍA"** — el usuario
  reportó que al abrir el Excel y darle "Habilitar edición", esa columna
  sale angosta. Causa real, confirmada leyendo `anchoTexto()`/`xmlCols()`
  en `includes/xlsx_writer.php`: el "autofit" de este escritor mide el
  ancho real del CONTENIDO de cada celda, pero CONCAT es una **fórmula**
  (`CONCAT(cliente, categoria)`, sin separador) — el resultado no se
  conoce hasta que Excel la recalcula al abrir, así que `anchoTexto()`
  solo puede adivinar un ancho genérico fijo (11 caracteres) para
  cualquier celda de fórmula sin formato de moneda/porcentaje — muy
  angosto para un cliente+sector concatenados (fácil 30-50+ caracteres).
  - **Arreglado con un mecanismo nuevo, reusable, no un parche puntual**:
    `XlsxWriter::anchoMinimo($hojaIdx, $col, $ancho)` (nuevo método
    público) guarda un piso de ancho por columna; `xmlCols()` lo aplica
    ANTES del clamp final, con `max()` contra lo que el autofit ya haya
    calculado — nunca angosta una columna que el autofit ya midió más
    ancha por su cuenta, solo garantiza un mínimo cuando el autofit no
    puede medir (fórmulas). Queda disponible para cualquier otra celda de
    fórmula de cualquier hoja que tenga el mismo problema en el futuro.
  - `getters/exportar_cuota_categoria.php`: `$wb->anchoMinimo($s1,
    $colConcat, 40);` justo después de crear la hoja.
  - **Probado sin necesitar generar el `.xlsx` real** (la extensión `zip`
    sigue sin estar habilitada en el PHP CLI local) — se invocó
    `xmlCols()`/`xmlHoja()` directo vía Reflection (son métodos privados,
    pero `xmlCols()` es pura generación de string XML, no necesita
    `ZipArchive`) sobre una hoja de prueba con el mismo patrón exacto
    (CONCAT como fórmula sin formato + una celda de texto real de 32
    caracteres) — confirmado en el XML generado: la columna de texto
    normal midió su ancho real (34, autofit funcionando normal), la
    columna CONCAT salió en 42 (el piso de 40 + 2 de relleno, en vez del
    13 que hubiera dado el autofit genérico sin el fix).
- **Probado**: `php -l` limpio en los 3 archivos tocados (`exportar_cuota_categoria.php`,
  `includes/xlsx_writer.php`). **Todavía sin probar en navegador real** —
  falta descargar el Excel real y confirmar visualmente PLAN en
  VISIBILIDAD y el ancho de CONCAT.

## Resaltado de filtros de Historial: pulso infinito + confirmación verde + brillo del botón (2026-08-28)

Mejora sobre la animación del aro pulsante de la sección de arriba
("Elige el período") — el usuario dijo que le gustó la animación pero
pidió 3 cosas más: (1) que el pulso azul siga hasta que el usuario de
verdad elija un valor (antes se apagaba solo a los 2.6s, aunque el select
siguiera en "Todos"), (2) que al elegir, el color pase de azul a un verde
de confirmación, y (3) que el botón "Descargar Excel" también "brille",
como si se estuviera habilitando.

- `.ac-filtro-resaltado` (`assets/css/style.css`) — animación cambiada de
  `1.3s ease-out 2` (2 pulsos y para) a `1.3s ease-out infinite`. Ya no se
  apaga con un `setTimeout` — se apaga desde JS recién cuando el `change`
  real del select dice que ya no está en "0".
- **Nueva `.ac-filtro-confirmado`** — flash verde de ~0.7s (mismo verde
  `#1e5c26` que `.ac-badge-ok`/`.ac-icon-btn-success`, no una paleta
  nueva): `box-shadow` + fondo tintado que aparecen y se desvanecen.
  `assets/js/historial.js`, nueva `actualizarEstadoFiltroPeriodo()`
  (enganchada a `change` de `hist-trimestre`/`hist-anio`): si el select que
  cambió todavía tenía `.ac-filtro-resaltado` puesto, la saca y pone
  `.ac-filtro-confirmado` en su lugar — un cambio de filtro NORMAL (que
  nunca pasó por el aviso) no dispara nada de esto, solo el que sí estaba
  parpadeando.
- **Nueva `.ac-excel-brillo`** — barrido de brillo diagonal (`::after` con
  gradiente, ~0.8s) + un pulso de sombra azul en el botón mismo (~0.5s),
  aplicado al link "Descargar Excel" (`exportarCuotaLink`) la primera vez
  que Trimestre Y Año quedan los dos elegidos a la vez (se guarda un
  booleano `exportCompletoAntes` para que sea justo en la TRANSICIÓN de
  incompleto→completo, no en cada cambio de filtro una vez que ya estaba
  completo).
- **`setTimeout` en vez de `animationend`** para apagar las clases nuevas
  (700ms/900ms, calcados a mano de la duración real del CSS) — se probó
  primero con `animationend`, pero `.ac-excel-brillo` anima 2 elementos a
  la vez (el botón y su `::after`), así que el evento se disparaba 2 veces
  y la primera en llegar cortaba la animación del otro antes de tiempo.
- Las 3 animaciones nuevas respetan `prefers-reduced-motion` (mismo
  criterio que el resto de animaciones del proyecto).
- **Probado**: `node --check` en `historial.js` limpio, llaves de
  `style.css` balanceadas (713/713). **Todavía sin probar en navegador
  real** — falta confirmar que el pulso persiste hasta elegir, que el
  flash verde se ve bien, y que el brillo del botón se nota sin ser
  molesto.

**Ronda 2, mismo día — 3 ajustes más pedidos tras ver la 1ra versión:**

1. **El pulso ya no queda "vivo" para siempre al cambiar de módulo** —
   Historial nunca se destruye al cambiar de pestaña (solo se oculta con
   CSS, arquitectura de siempre de este proyecto), así que el pulso
   `infinite` de la ronda 1 seguía animando en segundo plano aunque el
   usuario se fuera a otro módulo. `assets/js/historial.js` expone
   `window.acHistorialLimpiarResaltadoFiltro` (saca `.ac-filtro-resaltado`/
   `.ac-filtro-confirmado` de ambos selects y `.ac-excel-brillo` del botón)
   — `index.php` la llama en CUALQUIER click de navegación del sidebar,
   mismo patrón que ya usa `window.acAlertasFirmaRefrescar`.
2. **Confirmado: elegir "Todos" de nuevo NO pone verde** — ya era el
   comportamiento real (`actualizarEstadoFiltroPeriodo()` ignora un select
   en '0'), el usuario solo pidió confirmarlo explícito.
3. **El campo que YA estaba bien también se confirma en verde en el
   momento exacto en que se habilita la descarga** (pedido explícito: "para
   que dé a entender que el año también"): antes, si por ejemplo el año ya
   venía preseleccionado (con el nuevo default al año en curso) y solo
   faltaba el trimestre, al elegir el trimestre SOLO ese campo se ponía
   verde — el año (que nunca pulsó) se quedaba sin ninguna señal. Ahora
   `actualizarEstadoFiltroPeriodo()` calcula `recienCompleto` (la
   transición exacta de incompleto→completo, no solo "ya está completo") y
   confirma en verde a LOS DOS selects en ese momento, aunque uno de los
   dos nunca haya estado pulsando — junto con el brillo del botón, para que
   quede claro que ambos campos cuentan, no solo el que se acaba de tocar.
- **Probado**: `node --check`/`php -l` limpios en los 2 archivos tocados.
  **Todavía sin probar en navegador real.**

**Ronda 3, mismo día — animaciones más lentas y notorias, "pasa tan
rápido que ni lo noto":**

- **`.ac-filtro-confirmado`**: de 1 pulso de 0.7s a **2 pulsos de 0.6s
  (1.2s total)**, color más intenso (opacidad de fondo 0.14→0.28, spread
  del aro 8px→10px) — un "doble parpadeo" verde se nota mucho más que un
  solo fundido corto.
- **`.ac-excel-brillo`**: mismo criterio, de 1 pasada sutil a **2 pasadas
  de 0.7s (1.4s total)**. 2 cambios más, no solo repetir: (1) el pulso de
  sombra azul del botón ahora SUMA un tinte de fondo azul real
  (`background-color`, no solo `box-shadow` — una sombra fina es fácil de
  perder contra el resto de la página, un cambio de fondo del botón mismo
  no); (2) el barrido de brillo diagonal se hizo más ancho (40%→55% del
  ancho del botón) y más opaco (0.85→0.95).
- `assets/js/historial.js`: los `setTimeout` que sacan estas clases se
  actualizaron a juego (700→1200ms, 900→1400ms) — tienen que calzar exacto
  con la duración real del CSS (2 iteraciones × duración de cada una), si
  no la clase se saca a mitad de la 2da pasada y se corta la animación.
- **No se tocó el pulso azul de `.ac-filtro-resaltado`** (el aro que pulsa
  mientras falta elegir) — ese ya es `infinite`, sigue repitiendo solo
  hasta que el usuario elige, no tenía el problema de "pasa tan rápido".
- **Probado**: `node --check`/`php -l` limpios, llaves de `style.css`
  balanceadas (713/713). **Todavía sin probar en navegador real** — falta
  confirmar que ahora sí se nota el doble parpadeo verde y el brillo del
  botón.

## Excel de Historial (canal Directo): filas TOTAL sin pintar en 3 hojas + CARTERA sin formato de dólares (2026-08-29)

El usuario reportó, con el archivo real ya descargado, que varias hojas
tenían "espacios en blanco sin pintar los bordes" — VISIBILIDAD, RESUMEN DE
PAGOS, y CUOTA CLIENTE - CATEGORÍA. Investigado en el código (no se pudo
generar el `.xlsx` real localmente, sigue faltando la extensión `zip` en el
PHP CLI — se auditó columna por columna comparando qué columnas EXISTEN en
cada hoja contra cuáles reciben de verdad una llamada a `celda()`/
`formula()` en cada fila).

**Causa real, mismo patrón que el bug ya documentado y corregido una vez
para la FILA 1 del encabezado (2026-08-20, ver sección "Bug real
encontrado y corregido" más arriba)**: `XlsxWriter` solo escribe un `<c>`
(con su `borderId="1"` — el borde fino que ven en pantalla) para celdas
donde se llamó explícitamente a `celda()`/`formula()`. Las 3 filas TOTAL
de este export dejan varias columnas deliberadamente SIN sumar (texto,
CONCAT, columnas que no tiene sentido totalizar) — pero al no pasar por
ninguna de esas 2 funciones para nada, esas celdas quedaban sin `<c>` en
absoluto: no solo sin fórmula, sin borde ni fondo tampoco, ahí es donde el
usuario veía los "huecos". La corrección anterior (2026-08-20) solo cubrió
la fila 1 fusionada de 2 de las 3 hojas — nunca se revisaron las filas
TOTAL, que resultó tener el mismo problema en las 3 hojas.

**Columnas agregadas (celda vacía `''`, negrita, sin fill especial — mismo
estilo que el resto de la fila TOTAL) solo para que el borde se pinte,
nunca para sumar nada que no tenga sentido de negocio**:
- Hoja "CUOTA CLIENTE - CATEGORÍA", fila TOTAL: CLIENTE, PLAN, CATEGORIAS,
  CONCAT, CARTERA, GANA POR CATEGORÍA, GANA TOTAL (7 columnas).
- Hoja "VISIBILIDAD ", fila TOTAL: CEDI, PLAN, las 3 de MARCA, las 3 de
  VALIDACIÓN, las 3 de R/S/T (sin cabecera de grupo), OBSERVACION (12
  columnas).
- Hoja "RESUMEN DE PAGOS", fila TOTAL: CEDI (1 columna — la única que
  faltaba, el resto de esa fila ya estaba completa).
- **Hoja "CUOTA TOTAL" revisada, sin cambios** — las 6 columnas (CEDI...
  Gana) ya tenían celda en TODAS las filas de su tabla real (encabezado
  fila 3 + datos); las filas 1-2 en blanco arriba son a propósito (así es
  el archivo real, no forman parte de ninguna tabla) — si el usuario sigue
  viendo algo sin pintar ahí después de este fix, confirmar con el Excel
  real descargado antes de asumir que es el mismo bug.

**CARTERA — formato de número, no de dólares (mismo mensaje del usuario)**:
revisado a fondo — el código YA escribe esa columna sin ningún `numFmt`
(`$wb->celda($s1, $fila, $colCartera, '')`, tanto en encabezado como en
cada fila de datos, y ahora también en la fila TOTAL) — `numFmt=null` en
`XlsxWriter::estiloId()` resuelve a `numFmtId=0` ("General" real de
Excel), nunca a moneda (`numFmtId=44`, reservado solo para columnas que sí
pasan `'money'` explícito, como VENTA/REBATE/etc.). **No hizo falta ningún
cambio de código para esto** — ya cumplía lo pedido ("sin nada de
formato"); si en el archivo real se sigue viendo con signo `$`, no es este
código el que lo está poniendo (revisar si el archivo que se está mirando
es una versión vieja, ya deployada antes de este fix).

**Probado**: `php -l` limpio. El mecanismo de borde (`borderId="1"` fijo
en `estiloId()`, ya usado y verificado por la corrección de 2026-08-20)
se confirmó leyendo `includes/xlsx_writer.php` — cualquier celda que pase
por `celda()`/`formula()` lo recibe automático, así que agregar estas
celdas vacías es suficiente para pintar el borde, sin tocar el escritor.
**No se pudo generar el `.xlsx` real ni regenerar la verificación visual
en esta sesión** (mismo límite de siempre, falta `zip` en el PHP CLI
local) — el fix se basó en auditar código columna por columna, no en ver
el archivo. **Todavía sin probar en navegador real** — falta descargar el
Excel real y confirmar que las 3 filas TOTAL quedan completamente
pintadas y que CARTERA sigue sin signo de dólar.

## Participación de Percha — conectada al repositorio con el Excel real (2026-08-30)

JW confirmó y pasó el Excel real que van a subir para este repositorio
(`datos/PARTICIPACION PERCHA.xlsx`, 11 filas — leído vía Excel COM,
solo lectura) — **reemplaza por completo el diseño anterior** (solo Marca,
nunca tuvo filas reales en producción, tabla ni siquiera existía todavía:
confirmado con `SHOW TABLES`). Mismo patrón que le pasó a Rebate el
2026-08-27: el primer diseño fue una suposición, el Excel real manda.

**Columnas reales**: `CIUDAD | CATEGORIA | SUBCATEGORIA | MARCA | %`.

**Hallazgo clave, confirmado con el usuario vía `AskUserQuestion` antes de
tocar el esquema**: las líneas de Percha del Acta
(`repositorio_acuerdo_lineas`, tipo `percha`) **solo guardan Marca** — a
diferencia de Meta de Compras, nunca tuvieron cascada de Segmento/
Categoría/Subcategoría. El Excel real define el % por Ciudad+Categoría+
Subcategoría+Marca (4 dimensiones), pero solo hay 2 disponibles en una
línea real del Acta (Ciudad se deriva del cliente, Marca se elige) — no
hay con qué comparar Categoría/Subcategoría. **Decisión confirmada: la
clave del repositorio es solo Ciudad+Marca**, ignorando Categoría/
Subcategoría (se leen del Excel solo para detectar filas vacías, nunca se
guardan). Con los datos reales de hoy esto no genera ambigüedad práctica
(ver detalle abajo) — la alternativa (agregar esa cascada a la UI de
Perchas) se descartó por ser un cambio de UI mucho más grande para un
problema que hoy no existe de verdad.

**Ciudad SÍ importa — mismo hallazgo que tuvo Rebate**: LAVA (Crema/
Lavavajilla) tiene 50%/60%/55% según GUAYAQUIL/QUITO/**"RESTO CIUDADES"**
(valor real y literal del Excel — catch-all para cualquier CEDI que no sea
Guayaquil ni Quito; en la base real eso es CUENCA/MANABI/SANTO DOMINGO).
El resto de marcas (GOL, EL MACHO, DON VITTORIO, ALACENA) usan CIUDAD
**"TODAS"** — sin variación por ciudad. **Sin columna de Canal** (a
diferencia de Rebate) — el Excel no la trae, se asume que aplica igual
para Directo y Distribuidor.

**Verificado con los datos reales del Excel, sin ambigüedad práctica hoy**:
GOL aparece 2 veces (POLVO/DETERGENTE y LIQUIDO/SUAVIZANTE) pero con el
MISMO % (30%) las 2 veces — Ciudad+Marca solo no pierde información acá.
DON VITTORIO también se repite 2 veces con el mismo % (25%). **La única
fila con conflicto real es ALACENA** (SALSAS CLASICA=25% vs SALSAS SALSA
DE TOMATE=15%, mismo Ciudad=TODAS) — pero ALACENA (como DON VITTORIO) es
una marca de PASTAS/SALSAS, sectores **ya excluidos del alcance de
Acuerdos Comerciales** desde el 2026-08-27 (ver "Alcance real de Acuerdos
Comerciales" más arriba) — no es seleccionable en el spinner de Marca de
Perchas hoy, así que este conflicto no tiene forma de manifestarse en la
práctica (el mecanismo de "aviso de duplicado dentro del mismo archivo",
ya existente en `repositorio_guardar.php`, lo señalaría igual si algún día
se sube un archivo así — gana el último valor, con aviso, no en silencio).

**Schema — `datos/repositorios_schema.sql` reescrito (la tabla nunca
existió en producción, así que es un `CREATE TABLE` directo, no un
`ALTER`)**:
```sql
CREATE TABLE repositorio_participacion_percha (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	ciudad VARCHAR(200) NOT NULL,
	marca VARCHAR(200) NOT NULL,
	participacion_pct DECIMAL(5,2) NOT NULL,
	actualizado_por INT UNSIGNED NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	eliminado_en DATETIME NULL,
	eliminado_por INT UNSIGNED NULL
);
CREATE INDEX idx_participacion_eliminado_en ON repositorio_participacion_percha (eliminado_en);
CREATE UNIQUE INDEX uq_participacion_ciudad_marca ON repositorio_participacion_percha (ciudad, marca);
```
**✅ EJECUTADO por Claude 2026-08-30** — SQL mostrado al usuario, confirmación
explícita recibida ("ejecutalo"), corrido bajo la excepción puntual de este
proyecto (ver sección "⚠️ Excepción..." al inicio de este archivo).
Verificado después con `DESCRIBE`/`SHOW INDEX` de solo lectura: las 9
columnas exactas (incluidas `ciudad varchar(200) NOT NULL` y
`marca varchar(200) NOT NULL`), índice único `uq_participacion_ciudad_marca`
sobre `(ciudad, marca)`, índice `idx_participacion_eliminado_en`, 0 filas
(tabla recién creada). El pipeline ya está activo en producción — falta
subir el Excel real desde Repositorios para que tenga datos.

**Código actualizado, todo verificado con `php -l`/`node --check`,
mismo patrón exacto ya usado y probado para Rebate**:
- `includes/repositorio_import.php` — `repositorio_parsear_participacion()`
  reescrita: lee CIUDAD (opcional, con aviso si falta, mismo criterio que
  Rebate) + la columna de % (acepta el nombre real `"%"` a secas, además de
  los alias propios del proyecto). Categoría/Subcategoría se leen pero se
  descartan a propósito. **Probado con los 11 datos reales** (simulados en
  memoria, sin necesitar `ZipArchive` — sigue sin estar habilitado
  localmente): las 10 filas de datos parsean con Ciudad/Marca/% correctos,
  exacto contra lo leído del Excel real vía COM.
- `buscarParticipacionPercha($mysqli, $ciudad, $marca)` (nueva,
  `includes/functions.php`) — fallback en 3 pasos: Ciudad exacta → "TODAS"
  → "RESTO CIUDADES", el primer match gana. `getters/acuerdo_buscar_participacion.php`
  (nuevo, mismo patrón que `acuerdo_buscar_rebate.php`) la expone a
  Registrar.
- `assets/js/registrar.js` — `.v-participacion` (tabla de Perchas) dejó de
  ser un campo fijo en "50%" siempre-readonly: ahora arranca en "0%"
  readonly (mismo patrón que `.ac-rebate-input`), y `bindMarcaPerchaCombo()`
  llama a `buscarYAplicarParticipacion(tr, marca)` al elegir Marca de
  verdad — bloquea con el % real si hay match (Ciudad resuelta del CEDI del
  cliente para Directo, o "TODAS" para Distribuidor, mismo criterio que
  Rebate), o deja el campo editable si no hay match. `sugerir()` (usado por
  Actas Precargadas/restaurar borrador) sigue silencioso — nunca dispara la
  búsqueda, respeta el valor histórico ya guardado en la línea.
- `getters/repositorio_guardar.php` — INSERT/UPSERT con `(ciudad, marca,
  participacion_pct, actualizado_por)`; validación de campos faltantes
  incluye Ciudad; clave de duplicado-en-archivo pasó de solo `marca` a
  `ciudad|marca`.
- `includes/functions.php` (`listar_repositorio_participacion()`),
  `getters/repositorio_eliminados.php`, `getters/repositorio_exportar.php`
  (CSV y `.xlsx`), `assets/js/repositorios.js` (`CONFIG.participacion.columnas`
  y `columnasEliminados()`) — todos con la columna Ciudad agregada, mismo
  patrón que ya tenía Rebate.
- **Probado**: `php -l`/`node --check` limpios en los 8 archivos tocados +
  1 nuevo. La lógica del parser se verificó con los 11 datos reales del
  Excel simulados en memoria (sin depender de `ZipArchive`). La tabla ya
  existe en producción (ver arriba, "EJECUTADO") pero sigue en 0 filas —
  no se pudo probar `buscarParticipacionPercha()` contra datos reales
  todavía porque insertar filas requiere subir el Excel real desde la UI
  (Claude no puede correr `INSERT`, ni siquiera bajo la excepción de este
  proyecto). **Todavía sin probar en navegador real** —
  falta subir el Excel real desde Repositorios, y
  confirmar en Registrar que Participación se autocompleta y bloquea para
  Marcas reales (LAVA/GOL/EL MACHO, que sí son seleccionables hoy).

### Bug real de la primera subida — faltaba Ciudad al guardar + toast de "guardado" engañoso (2026-08-30, mismo día)

**Primer intento de subida real por el usuario** (`PARTICIPACION PERCHA.xlsx`,
10 filas detectadas) — las 10 fallaron con "Falta Ciudad" al guardar,
aunque el código de `CONFIG.participacion.columnas` (ver arriba) ya incluía
Ciudad. **Causa real, no un bug de código**: el navegador tenía la pestaña
de Repositorios cargada desde ANTES de que se terminara de editar
`repositorios.js` en esta misma sesión — el servidor (PHP, se re-evalúa en
cada request) ya corría el código nuevo (por eso "10 filas detectadas" con
la columna "%" del Excel real funcionó), pero el navegador seguía con la
versión vieja de la tabla de previsualización en memoria (sin el input de
Ciudad), así que al leer los valores editados para guardar, la fila nunca
mandaba ese campo. **Solución: recargar la página** (el cache-busting
`?v=<?= filemtime(...) ?>` de `components/repositorios/repositorios.php`
ya se encarga de traer el archivo nuevo apenas se recarga — no hizo falta
tocar código para esto).

**Bug real de código encontrado en el camino, sí corregido**: el usuario
notó que salió la notificación de "guardado" (toast verde) *a pesar* de
que las 10 filas habían fallado — antes de siquiera lograr guardar una
sola. Causa: `getters/repositorio_guardar.php` (y el mismo patrón en
`getters/cuotas_guardar.php`) siempre responde `responder(true, ...)` — el
`true` refleja que la PETICIÓN se procesó sin errores fatales (nunca que
algo se haya guardado de verdad), pero `assets/js/repositorios.js` usaba
ese mismo `data.ok` directo para decidir el color del toast
(`mostrarMensaje(data.message, data.ok)`) — con `data.guardadas = 0` y
`data.ok = true`, el toast salía verde/"éxito" igual, aunque el propio
texto del mensaje dijera "0 fila(s) guardadas... 10 NO se guardaron".

**Corregido en `assets/js/repositorios.js`**, 2 lugares (`guardarFilas()`
para Rebate/Participación, `guardarCuotas()` para Cuotas — mismo patrón
en las 2 respuestas del servidor): el toast ahora usa
`data.ok && data.guardadas > 0` en vez de `data.ok` a secas — `data.ok`
se sigue usando SIN cambios para el resto del flujo (cerrar el modal,
refrescar la lista de atrás, decidir si mostrar el detalle de errores/
avisos) — el fix es puntual al color/mensaje del toast, no toca la lógica
de qué hacer después de guardar. **No se tocó el backend** (`repositorio_guardar.php`/
`cuotas_guardar.php` siguen respondiendo `ok:true` siempre que la petición
se procese sin excepción) — el campo `guardadas` ya venía en la respuesta,
no hizo falta agregar nada nuevo, solo usarlo del lado correcto.

**Probado**: `node --check` limpio. **Todavía sin probar en navegador
real** — falta que el usuario recargue la página, resuba el Excel real, y
confirme que esta vez guarda las 10 filas y el toast refleja el resultado
real (verde si guardó algo, rojo si no guardó nada).

## Registrar: canal Distribuidor mide en Cajas, no en Dólares — pantalla interactiva nunca se ajustó (2026-08-30)

El usuario mandó 2 capturas (tabla de Cabeceras y de Meta de Compras) que
parecían mostrar signo "$" en un contexto de "Cajas" — investigado a fondo:
**el PDF y el Excel export ya diferencian Directo ($) de Distribuidor
(Cajas) desde el 2026-08-20/24** (`includes/acta_pdf.php`, `$fmt`/`$esDistribuidor`),
pero **la pantalla interactiva de Registrar nunca se ajustó** — el título
"1. Meta de Compras en Dólares" estaba fijo en HTML (nunca condicionado por
canal), y el símbolo "$" de cada input (`.ac-money-field::before`) tampoco
distinguía canal. Corregido igual, sea o no lo que motivó el reporte
puntual (ver nota de abajo):

- `components/registrar/registrar.php` — `$canalUsuario` (ya calculado,
  `canalDeSupervisor()`) agrega la clase `ac-acuerdo-distribuidor` al
  contenedor raíz cuando corresponde; el título de sección 1 ahora es
  `Meta de Compras en <?= ... ? 'Cajas' : 'Dólares' ?>`.
- `assets/css/style.css` — `.ac-acuerdo-distribuidor .ac-money-field::before { content: none; }`
  (saca el "$") + padding-left normal (8px, igual que cualquier otro input).
- `assets/js/registrar.js` — `formatCurr()` (usada para Total Período,
  Valor Estimado a Ganar, Pago Total de Cabeceras/Rumas/Perchas) ahora
  chequea `CANAL_USUARIO === 'distribuidor'` y formatea número plano en vez
  de `Intl` moneda USD. Encabezados de mes de Meta de Compras ("ENE ($)") y
  el título "Pago x Mes x Percha ($)" de Perchas pierden el "($)" para
  Distribuidor.
- **No se tocó** "Pago Total Cajas" (Cabeceras/Rumas/Perchas) — esa
  etiqueta ya está fija para los 2 canales a propósito, decisión explícita
  de otra sesión (ver "Ronda 7" en la sección de Registrar más arriba, "el
  usuario pidió que aplique igual para Directo y Distribuidor") — no es el
  mismo caso que el título/símbolo "$", que sí dependía del canal real.

**Nota importante, investigado con el usuario en la misma conversación**:
el cliente real de las 2 capturas (`ADAMARI MILLINGALLE`, pos_id
`EPV15880`) resultó ser **canal COBERTURA (Directo)**, no Distribuidor —
confirmado con `SELECT` de solo lectura contra
`repositorio_locales_supervisores_cliente` (el campo se veía etiquetado
"Distribuidor", no "Local" — la pista correcta era esa, no el símbolo "$").
Para esa cuenta puntual, "$" y "Dólares" eran lo correcto — el fix de
arriba no cambia nada en su pantalla (`CANAL_USUARIO` sigue sin ser
`'distribuidor'`), pero corrige un bug real y latente para cualquier
usuario que sí sea Distribuidor de verdad, nunca antes ajustado en la
pantalla interactiva.

**Probado**: `php -l`/`node --check` limpios en los 3 archivos. No se pudo
probar visualmente con una cuenta real de canal Distribuidor en esta
sesión. **Todavía sin probar en navegador real.**

## Rebate en 0 en una fila real (Quito/Directa/Líquido/Jabón Tocador/Misty) — investigado, dato SÍ existe (2026-08-30)

El usuario preguntó por qué el Rebate % salía en 0 para esa combinación
puntual (misma Acta de las capturas de arriba, canal Directo confirmado).
**Verificado de solo lectura, llamando `buscarRebateProducto()` directo**:
el dato SÍ existe y matchea — `QUITO / DIRECTA / LIQUIDO / JABON TOCADOR /
MISTY` → **1.50%** (fila real en `repositorio_rebate_producto`, id 55).
No es un problema de datos faltantes ni de la tolerancia plural/singular
(ya la usa: la fila real está guardada como `LIQUIDOS`, la búsqueda con
`LIQUIDO` singular la encuentra igual).

**No se pudo terminar de diagnosticar en esta sesión** — la causa más
probable, a confirmar con el usuario, es que esa fila haya llegado al
formulario vía `sugerir()` (restaurar un borrador guardado antes, o cargar
una Acta Precargada) en vez de haberse completado recién tipeando en la
cascada — `sugerir()` es **intencionalmente silencioso** (no llama a
`buscarYAplicarRebate()`, ver el parámetro `silencioso` documentado en
`bindCascadaComboConSector()`) para no pisar un `rebate_pct` histórico ya
guardado en esa línea — si la línea se guardó en algún momento anterior a
que Rebate quedara conectado (2026-08-27) o antes del fix de Actas
Precargadas (2026-08-28), se restaura con el 0 que tenía guardado,
correctamente, sin volver a buscar.

**Pendiente, próxima sesión o con el usuario en vivo**: confirmar si esa
fila se completó tipeando en la cascada en ese momento (bug real si es
así, dado que el match sí existe) o si vino de un borrador/precarga vieja
(comportamiento esperado — se resolvería solo si el usuario borra la fila
y la vuelve a completar a mano, o corrigiendo el % manualmente ya que el
campo queda editable en ese caso).

**Confirmado por el usuario: la eligió en vivo, tipeando la cascada recién
en ese momento** — no vino de un borrador/precarga. Se reprodujo el
flujo EXACTO (servidor local `php -S`, sesión real de JAVIER MALDONADO —
dueño real de este cliente, confirmado con `SELECT` — vía un script
temporal de login que se creó y se borró en la misma verificación, sin
tocar la base para nada más que leer, mismo patrón ya usado varias veces
en este proyecto) con Playwright: mismo cliente (`ADAMARI MILLINGALLE`),
misma cascada Segmento=CUIDADO PERSONAL → Sector=LIQUIDO →
Categoría=JABON TOCADOR → Marca=MISTY.

**Resultado: NO se reprodujo el bug — el código actual funciona
correctamente.** La request real capturada:
`GET acuerdo_buscar_rebate.php?ciudad=QUITO&canal=DIRECTA&sector=LIQUIDO&categoria=JABON+TOCADOR&marca=MISTY`
→ `{"ok":true,"encontrado":true,"rebate_pct":0.015}` → el campo quedó en
**1.50, readonly, con el título "Bloqueado — viene del repositorio de
Rebate."** — exactamente lo esperado.

**Conclusión**: el código en este repositorio (el mismo que corre esta
sesión) está bien — el bug que el usuario vio en su navegador tiene que
haber sido por estar corriendo una versión más vieja en ese momento (el
mismo síntoma de "página no recargada desde el último cambio" que ya pasó
2 veces antes en esta sesión — ver "Bug real de la primera subida" más
arriba) o por probar contra el entorno de desarrollo de Azure con un
posible desfase de sincronización de ese `.php` puntual (ver nota de
infraestructura, "CSS refleja casi al instante, PHP tarda más", en la
sección de Responsive/mobile). **No se tocó ningún código** — no había
nada que corregir, el mecanismo ya funciona. Si vuelve a pasar después de
recargar la página / confirmar que está en el entorno correcto, ahí sí
amerita otra vuelta de investigación.

## Auditoría completa de Rebate — las 14 combinaciones reales del spinner x 5 ciudades (2026-08-30, mismo día)

El usuario pidió no seguir probando caso por caso a mano — "hay unos que sí
sale, otros que no, revisalo vos que respondan y llenen correctamente el
Rebate". Se armó un script de solo lectura que prueba **`buscarRebateProducto()`
directo (la misma función que usa la búsqueda en vivo) para las 14
combinaciones reales que el catálogo ofrece hoy en el spinner de Meta de
Compras** (los 9 combos Sector+Categoría válidos, ver "Alcance real de
Acuerdos Comerciales") **x las 5 ciudades reales de canal Directo
(GUAYAQUIL/QUITO/MANABI/SANTO DOMINGO/CUENCA) + canal Distribuidor
(ciudad "TODAS")** — 84 casos reales en total, sin inventar ningún dato.

**Resultado: 55 de 84 casos con match correcto, 29 sin match — pero
prácticamente todos los "sin match" son GENUINAMENTE datos que faltan en
el repositorio, no un bug de código:**

1. **3 de los 14 productos no tienen NINGÚN Rebate cargado, en ninguna
   ciudad ni canal** (0 de 6 casos cada uno):
   - `BARRA / LAVAVAJILLAS / EL ARRANCAGRASA`
   - `BARRA / LAVAVAJILLAS / LAVA`
   - `POLVO / DETERGENTE / SAPOLIO`
   Estos 3 SON seleccionables hoy en el spinner (existen en
   `repositorio_productos`, dentro de los 9 combos válidos), pero
   `datos/RABATE.xlsx` nunca trajo una fila para ellos — no es que la
   búsqueda falle, es que el dato no existe. **Pendiente: pedirle a JW el
   % de Rebate de estos 3 productos.**
2. **CUENCA no tiene NINGUNA fila cargada para ningún producto** — el
   archivo real de Rebate (55 filas, confirmado en su momento) solo trae
   GUAYAQUIL/QUITO/MANABI/SANTO DOMINGO para canal Directa, nunca Cuenca.
   Es un vacío real y esperado del archivo fuente, no un bug — si hay
   clientes reales con CEDI=Cuenca (existe como valor real en el maestro,
   confirmado antes), su Rebate va a quedar siempre editable hasta que se
   agregue esa ciudad al repositorio.
3. **Los otros 11 de 14 productos matchean perfecto en las 4 ciudades
   reales restantes (Guayaquil/Quito/Manabí/Santo Domingo) y en
   Distribuidor** — 0 fallas fuera de los 2 casos de arriba. El mecanismo
   de búsqueda (match exacto → variantes plural/singular → Ciudad+Canal+
   Sector+Marca sin Categoría) funciona correctamente en el 100% de los
   casos donde el dato sí está cargado.

**Conclusión para el usuario**: el código de matching de Rebate está bien
— no hace falta ningún fix. Lo que hace falta es completar el
repositorio: el % de los 3 productos sin ningún dato, y opcionalmente
Cuenca como ciudad si hay clientes reales ahí. Ninguna de las 2 cosas se
puede resolver desde código — son datos que solo JW puede dar.

**Probado**: script de solo lectura, sin tocar la base, corrido contra
producción real — 84/84 casos ejecutados sin error.

## Bug real: búsqueda de Cuotas no cubría CEDI/Plan/Subcategoría/Marca (2026-08-30, mismo día)

El usuario reportó "no sé si me anda buscando por columna o cómo mismo me
está buscando" en el módulo Repositorios. Investigado comparando, para
cada pestaña, qué columnas dice buscar el placeholder contra qué columnas
de verdad entran en el `WHERE ... LIKE` de `includes/functions.php`:

- **Rebate y Participación ya estaban bien** — probado con 5 términos
  reales de Rebate (ciudad/canal/sector/categoría/marca) y confirmado que
  las 5 columnas filtran de verdad, coincide exacto con el placeholder.
- **Cuotas tenía el bug real**: `listar_repositorio_cuotas()` solo buscaba
  por `cliente_excel`, `pos_id` y `sector` — pero la tabla visible tiene
  además CEDI, Plan, Subcategoría y Marca, ninguna de esas 4 entraba en la
  búsqueda. Confirmado con datos reales: buscar por un CEDI real
  ("CARLOS PROAÑO") o una Marca real ("EL ARRANCAGRASA") daba **0
  resultados antes del fix**, aunque existieran filas reales con esos
  valores — de ahí la sensación de "a veces busca, a veces no".

**Corregido**: `includes/functions.php` — el `WHERE` de Cuotas ahora cubre
`cedi_excel, cliente_excel, pos_id, plan, sector, subcategoria, marca` (7
columnas, todas las visibles en la tabla + pos_id aunque no se muestre).
2 niveles de `prepare()` (con/sin Subcategoría+Marca) por si el `ALTER` de
esas 2 columnas no se corrió en algún entorno — mismo criterio defensivo
que ya usaba el `SELECT` de esta misma función (ahora sincronizados: antes
el `SELECT` tenía 3 niveles de fallback pero el `WHERE` de búsqueda
siempre usaba las mismas 3 columnas fijas sin importar cuál fallback se
haya activado). `assets/js/repositorios.js` — placeholder actualizado a
"Buscar por CEDI, cliente, plan, categoría, subcategoría o marca...".

**Probado con datos reales de solo lectura**: buscar "CARLOS PROAÑO"
(CEDI real) da 40 resultados (antes 0); buscar "EL ARRANCAGRASA" (Marca
real) da 4 (antes 0). `php -l`/`node --check` limpios.

## Participación de Percha: qué marcas van a autocompletar una vez subido el Excel (2026-08-30, mismo día)

El usuario pidió el mismo tipo de auditoría que se hizo para Rebate, pero
para Participación de Percha. **La tabla sigue en 0 filas** (el usuario
todavía no volvió a subir el Excel después del fix de "Falta Ciudad" — ver
sección de arriba), así que no se pudo probar el match en vivo contra
datos reales todavía. En su lugar, se cruzaron las 7 marcas reales del
spinner de Perchas (`marcas_percha`, ya filtrado a los 9 combos válidos)
contra las 10 filas reales de `datos/PARTICIPACION PERCHA.xlsx` para
adelantar exactamente qué va a pasar apenas se suba:

- **3 de 7 marcas SÍ van a autocompletar solas**: `LAVA` (varía por
  ciudad: 50% Guayaquil/60% Quito/55% resto), `GOL` (30% en cualquier
  ciudad), `EL MACHO` (30% en cualquier ciudad).
- **4 de 7 marcas NO tienen ningún dato en este Excel — van a quedar
  siempre editables**: `CIERTO`, `EL ARRANCAGRASA`, `MISTY`, `SAPOLIO`.
  Mismo patrón que los 3 productos de Rebate sin dato — no es un bug,
  simplemente JW no incluyó esas marcas en el archivo. **Pendiente:
  pedirle a JW el % de estas 4 si corresponde.**
- **Nota técnica menor, no bloqueante**: para canal Distribuidor + Marca
  `LAVA` específicamente, `buscarParticipacionPercha()` termina resolviendo
  por el fallback "RESTO CIUDADES" (55%) en vez de quedar sin match — el
  Excel no tiene una fila `TODAS/LAVA`, así que el 3er paso del fallback
  (`RESTO CIUDADES`) es el que responde. Funciona (da un valor usable), pero
  vale la pena que el usuario sepa que ese 55% viene del "resto de
  ciudades" de Directo, no de un dato pensado específicamente para
  Distribuidor — si JW confirma que Distribuidor debería tener su propio
  valor para LAVA, avisar para agregarlo aparte.

**Pendiente**: en cuanto el usuario suba el Excel real (con el fix de
Ciudad ya aplicado), spot-check en Registrar con LAVA/GOL/EL MACHO para
confirmar el comportamiento en vivo — mismo criterio que ya se hizo con
Rebate.

**Actualización, mismo día — ya subido, confirmado con datos reales**: el
usuario re-subió el Excel exitosamente. `repositorio_participacion_percha`
tiene 7 filas activas (TODAS/ALACENA 15%, TODAS/DON VITTORIO 25%,
TODAS/EL MACHO 30%, TODAS/GOL 30%, GUAYAQUIL/LAVA 50%, QUITO/LAVA 60%,
RESTO CIUDADES/LAVA 55% — coincide con "gana el último valor" para los 2
duplicados del archivo, GOL y DON VITTORIO, ambos con el mismo % en sus 2
filas originales así que no hay pérdida real de información). Probado
`buscarParticipacionPercha()` de solo lectura contra estos datos reales —
los 5 casos predichos arriba dieron EXACTO lo esperado: LAVA/Quito=60,
LAVA/TODAS(Distribuidor, vía fallback RESTO CIUDADES)=55, GOL/Quito=30,
EL MACHO/Quito=30, MISTY/Quito=null (sin dato, como se predijo). El
pipeline completo (Excel real subido → repositorio → matching) queda
confirmado funcionando de punta a punta.

## Modal de subida: alerta roja incluso cuando solo hay avisos (no errores) — corregido (2026-08-30, mismo día)

El usuario reportó, tras la subida real de Participación de arriba
(que generó un aviso de "duplicado en el mismo archivo" para GOL y DON
VITTORIO — ambos SÍ se guardaron bien, el aviso es solo informativo): la
caja de resultado post-guardado (`#repo-preview-errores`,
`mostrarErroresPreview()` en `assets/js/repositorios.js`) salía **roja
siempre**, sin importar si eran errores reales (no se guardó nada) o solo
avisos (sí se guardó, conviene revisar) — "da a entender que hubo error"
aunque todo se hubiera guardado bien. También reportó que quedaba **mal
ubicada** — si el usuario ya había scrolleado la tabla, la caja podía
aparecer fuera de la vista sin que se notara que algo cambió.

**Corregido**:
- Nueva clase `.ac-alert-warning` (`assets/css/style.css`) — mismo ámbar
  ya usado para "revisar" en el resto de la app (`.ac-badge-revisar`,
  `#fff2cc`/`#7a5b00`), no una paleta nueva.
- `mostrarErroresPreview()` ahora decide el color según el contenido real:
  **rojo (`.ac-alert-error`) solo si `errores.length > 0`** (algo de
  verdad no se guardó); **ámbar (`.ac-alert-warning`) si son solo
  avisos** (todo se guardó, pero conviene revisar). `components/repositorios/repositorios.php`
  ya no tiene la clase `ac-alert-error` fija en el HTML inicial — el color
  lo decide JS en cada guardado.
- `previewErrores.scrollIntoView({ behavior: 'smooth', block: 'nearest' })`
  al mostrarse — para que la caja de resultado quede visible sin importar
  qué tan scrolleada estuviera la tabla antes de guardar.

**Probado**: `node --check`/`php -l` limpios en los 3 archivos tocados,
llaves de `style.css` balanceadas (716/716). **Todavía sin probar en
navegador real** — falta confirmar que la próxima subida con avisos (sin
errores reales) sale en ámbar, no en rojo, y que la caja queda visible al
aparecer.

## Modal de subida seguía sin cerrarse con solo avisos — "parece que no guardó" (2026-08-30, mismo día, ronda 2)

El fix de arriba (rojo→ámbar) no alcanzaba — el usuario probó de nuevo y
reportó el problema de fondo: con solo avisos (sin errores reales), el
modal se quedaba abierto mostrando la MISMA tabla de antes, dando la
impresión de que no había guardado nada, aunque el toast ya dijera que sí
(mismo síntoma en Rebate, Participación y Cuotas — "los tres repos").
Causa real: `subirGuardarBtn`'s `onDone` (`assets/js/repositorios.js`)
trataba errores Y avisos igual — cualquiera de los dos dejaba el modal
abierto — pero solo los ERRORES reales ameritan quedarse (para corregir
sin perder el archivo); un aviso solo es informativo, ya se guardó todo.

**Corregido**: el modal ahora se queda abierto SOLO si `data.errores.length`
> 0. Si son solo avisos, se cierra igual que el caso "nada que revisar" —
y el detalle de los avisos se muestra en un modal SweetAlert2 aparte
(`Swal.fire({icon:'info', ...})`, mismo componente que ya usa el resto de
la app), para no perderlo sin dejar la sensación de que quedó a medias.
Aplica igual a los 3 tipos de repositorio (Rebate/Participación/Cuotas),
ya que comparten el mismo botón/handler.

**No se tocó** el aviso que sale ANTES de guardar (al leer el Excel,
`mostrarErroresPreview([], data.avisos)` justo después de subir el
archivo) — ese sigue en la caja ámbar de siempre, tiene sentido que se
quede ahí porque el usuario todavía está editando la previsualización, no
terminó de guardar nada.

**Probado**: `node --check` limpio. Confirmado que SweetAlert2 está
cargado globalmente en `index.php` (usado ya en todo el resto de la app).
**Todavía sin probar en navegador real** — falta confirmar que una subida
con avisos (sin errores) cierra el modal y muestra el SweetAlert2 con el
detalle correcto.

## "Duplicado en el mismo archivo": no debería avisar de nuevo si el archivo no cambió (2026-08-30, mismo día, ronda 3)

El usuario probó el SweetAlert2 de arriba (funcionó bien visualmente) pero
notó algo más de fondo: **re-subió el mismo archivo exacto de Participación
sin cambiar nada, y le volvió a salir "hay algo para revisar" con GOL/DON
VITTORIO/ALACENA** — "no debería haber novedad porque no hubo cambios".
Confirmó que pasa igual en los 3 repositorios.

**Causa real, no era un bug — era una clasificación incorrecta del
aviso**: el aviso "esta ciudad/marca [o producto, o cliente/categoría] se
repite más abajo en el mismo archivo" **es una propiedad fija del
ARCHIVO en sí** (2 filas del Excel apuntan al mismo producto/cliente, ej.
GOL aparece en POLVO/DETERGENTE Y en LIQUIDO/SUAVIZANTE, pero nuestra
clave real es solo Ciudad+Marca así que colisionan) — va a salir SIEMPRE
que se suba ESE archivo, sin importar si algo cambió de verdad en la base.
No es como los otros avisos (sector que no matchea el catálogo, cuota ya
usada, cliente sin resolver), que sí son información real que vale la pena
repetir cada vez. Tratarlo igual que esos —mostrándolo en un modal "algo
para revisar" cada vez que se guarda— era el error de diseño real.

**Corregido**: los 3 avisos de "se repite más abajo en el mismo archivo"
(`getters/repositorio_guardar.php` ×2 — Rebate y Participación,
`getters/cuotas_guardar.php` ×1) ahora llevan `'tipo' => 'duplicado_archivo'`.
`assets/js/repositorios.js` filtra ese tipo específico ANTES de decidir si
mostrar el SweetAlert2 — si los ÚNICOS avisos de esa subida son
`duplicado_archivo`, el modal se cierra en silencio (no hay nada que el
usuario deba revisar); si hay algún otro aviso genuino (de cualquiera de
los 3 repos), ese sí sigue mostrando el SweetAlert2 normal. El dato crudo
de `duplicado_archivo` se sigue devolviendo en la respuesta (no se pierde
información, solo se deja de usar como disparador de un modal).

**Probado**: `php -l`/`node --check` limpios en los 3 archivos. **Todavía
sin probar en navegador real** — falta confirmar que re-subir el mismo
Excel de Participación (con GOL/DON VITTORIO/ALACENA repetidos) ya NO
dispara el SweetAlert2, y que un aviso genuino (ej. Sector sin match en
Cuotas) sigue mostrándose.

## Repositorios: mensajes de error/aviso simplificados en los 3 tipos (2026-08-30, mismo día, ronda 4)

El usuario pidió, de nuevo (mismo criterio que la auditoría de tono del
2026-08-28, ver esa sección más arriba): "no siento nada profesional y
entendible ese mensaje... mensajes simples, sencillos de entender" —
explicando el mecanismo interno (upsert, cómo se interpretó un texto, por
qué se descartó algo) en vez de solo decir qué pasó. Aclaró explícitamente
que la confirmación de que SÍ se guardó tiene que seguir estando (no se
tocó esa parte — el mensaje principal "Se guardaron N fila(s)." sigue
igual en los 2 archivos).

**Simplificados, `getters/repositorio_guardar.php` (Rebate y
Participación) y `getters/cuotas_guardar.php`**:
- "Rebate inválido (X) — debe estar entre 0% y 100%" → "El Rebate debe ser
  un número entre 0% y 100%".
- "Participación inválida (X) — debe estar entre 0% y 100%" → "La
  Participación debe ser un número entre 0% y 100%".
- "Este producto/Esta ciudad-marca/Este cliente-categoría se repite más
  abajo en el mismo archivo — se guardó el último valor" → "Producto/
  Marca/Cliente repetido en el archivo — se usó el último valor" (los 3,
  uno por tipo de repositorio).
- **"Error al guardar: " + `$stmt->error`** (exponía el mensaje crudo de
  MySQL) → "No se pudo guardar esta fila" (los 3 lugares, Rebate/
  Participación/Cuotas) — revierte una decisión anterior de esta misma
  sesión (2026-08-28, "no es la misma clase de problema que explicarle al
  cliente cómo está armado el Excel") tras el pedido explícito y repetido
  del usuario de simplificar TODO mensaje técnico, sin excepción.
- "La categoría 'X' no coincide con ningún Sector real del catálogo (ni
  sola ni como Sector+Subcategoría pegados) — se guardó tal cual, revisar
  con JW" → "No se pudo identificar la categoría 'X' en el catálogo —
  revisar con JW".
- "Esta categoría ya generó una Acta real — no se modificó (un archivo
  nuevo no puede 'revivir' una cuota ya usada)" → "Esta categoría ya se
  usó en una Acta — no se modificó".
- "No se encontró un cliente único con ese nombre/CEDI — queda en
  'Pendientes de Asignar' para resolver a mano" → "No se pudo identificar
  el cliente — queda en Pendientes de Asignar".
- "Los 3 montos mensuales deben ser números iguales o mayores a 0" → "Los
  3 montos mensuales deben ser 0 o más".

**No se tocaron** los avisos de la etapa de PREVISUALIZACIÓN (antes de
guardar, ej. "Este archivo no trae columna de Ciudad y/o Canal...") — ya
estaban en un tono claro/instructivo sin exponer mecanismo interno, no
tenían el mismo problema.

**Probado**: `php -l` limpio en los 2 archivos. **Todavía sin probar en
navegador real.**

## Repositorios: guion largo "—" fuera de todo mensaje al usuario (2026-08-30, mismo día, ronda 5)

El usuario, mirando el resultado de la ronda anterior, marcó algo más de
fondo: el guion largo ("—") usado como separador dentro de una misma
oración ("Texto principal — nota aclaratoria") sigue leyéndose como un
comentario pegado al final de la frase, no como una oración normal —
"no lo veo nada profesional... creo que en varios lugares has puesto
comentarios etc etc en la página viva". Mismo espíritu que la auditoría
de tono del 2026-08-28 (sacar razonamiento interno), pero ahora apuntado
a la PUNTUACIÓN en sí, no solo al contenido.

**Barrido completo del módulo Repositorios** (`getters/repositorio_*.php`,
`getters/cuotas_*.php`, `includes/repositorio_import.php`,
`assets/js/repositorios.js`, `components/repositorios/repositorios.php`) —
grep de `—` dentro de comillas (strings reales, no comentarios de código) en
los 8 archivos. Reemplazado en cada caso por punto y aparte (dos oraciones
cortas) o, cuando el texto es muy corto para 2 oraciones (badges, tooltips,
títulos de modal), por coma o dos puntos según lo que se leyera más natural.
Ejemplos:
- "Producto repetido en el archivo — se usó el último valor" → "Producto
  repetido en el archivo. Se usó el valor más reciente." (mismo criterio
  para las variantes de Participación y Cuotas).
- "Subir Archivo — Rebate" (título del modal) → "Subir Archivo: Rebate".
- "10 fila(s) detectada(s) — Q1" → "10 fila(s) detectada(s) (Q1)".
- "Ya usada — no se puede modificar" (badge) → "Ya usada, no se puede
  modificar".
- "El archivo se subió incompleto — probá de nuevo..." → "El archivo se
  subió incompleto. Probá de nuevo..." (mismo patrón en los 5 mensajes de
  error de subida, duplicados en `repositorio_previsualizar_excel.php` y
  `cuotas_previsualizar_excel.php`).
- Título "Resumen — Cuotas Trimestrales" → "Resumen de Cuotas
  Trimestrales"; caption "A quién le corresponden — usuarios con cuenta..."
  → "Usuarios con cuenta y supervisores sin cuenta todavía" (se sacó el
  lead-in redundante, no solo el guion).
- Tooltip nativo de la barra del gráfico de Resumen ("Juan — 3 Acta(s)
  pendiente(s)") → dos puntos ("Juan: 3 Acta(s) pendiente(s)").

**No se tocaron** los guiones largos dentro de comentarios de código (ahí
son parte de la convención de documentación de este proyecto, no
user-facing) — solo strings que terminan renderizadas en pantalla.

**Probado**: `php -l`/`node --check` limpios en los 8 archivos. **Todavía
sin probar en navegador real.**

## "duplicado_archivo" seguía inflando el toast, no solo el SweetAlert2 (2026-08-30, mismo día, ronda 6)

La ronda anterior de "duplicado_archivo" (más arriba, ronda 3) solo sacó
ese tipo de aviso del SweetAlert2 post-guardado — pero el usuario probó
subiendo prácticamente el mismo archivo de nuevo y el TOAST seguía
diciendo "Se guardaron 10 fila(s). 3 fila(s) se guardaron con un aviso.
Revisá el detalle." — el conteo de avisos que arma el mensaje
(`$partesMensaje` en `getters/repositorio_guardar.php`/`cuotas_guardar.php`)
todavía contaba TODOS los avisos, incluidos los `duplicado_archivo`, así
que la sensación de "algo cambió, hay que revisar" seguía aunque nada
fuera nuevo. Pedido explícito: "no debería ni salir una alerta, solo
cuando se modificaría, sea lo obvio".

**Corregido**: en los 2 archivos, `$avisosRelevantes = array_filter($avisos,
...)` excluye los de tipo `duplicado_archivo` ANTES de decidir si el
mensaje menciona "con un aviso" — mismo criterio que ya se aplicaba en el
frontend para el SweetAlert2, ahora también en el texto del toast mismo.
El array completo de `avisos` (con `duplicado_archivo` incluido) se sigue
devolviendo en la respuesta, solo se dejó de usar para el CONTEO del
mensaje.

**Probado**: `php -l` limpio en los 2 archivos. Simulación aislada (sin
tocar la base) con 2 avisos, ambos `duplicado_archivo`: el mensaje da
"Se guardaron 10 fila(s)." limpio, sin mencionar avisos — confirma el
comportamiento esperado. **Todavía sin probar en navegador real.**

## Repositorio de Cuotas: badge "Actualiza" confuso + "guardando eterno" al resubir (2026-08-30, mismo día, ronda 7)

Dos pedidos del usuario sobre este submódulo puntual (pestaña Cuotas del
módulo Repositorios):

**1. Badge "Actualiza" se leía como una orden, no como una descripción**:
en la columna "Al guardar" de la previsualización, la fila que ya existe y
va a actualizarse mostraba el badge "Actualiza" a secas — sin sujeto, en
español eso lee como imperativo ("[vos] actualizá esto"), no como "esto
se va a actualizar". Corregido en `assets/js/repositorios.js`
(`badgeEstadoPreview()`): "Actualiza" → **"Se actualiza"**.

**2. "Guardando…" eterno al resubir prácticamente el mismo Excel** — causa
real encontrada, no era un cuelgue/bug de JS (revisado el flujo completo
de `guardarCuotas()`/`ponerGuardarCargando()`, el spinner sí se apaga en
cualquier resultado, éxito o error): era un problema de RENDIMIENTO real
en el backend. `resolverPosIdCliente()` y `resolverSectorReal()`
(`includes/functions.php`) hacen consultas contra
`repositorio_locales_supervisores_cliente` (~41.000 filas, **sin ningún
índice útil para esa búsqueda** — decisión ya documentada de no tocar el
esquema de esa tabla externa, ver "Módulo Liquidación" más arriba) — y
`getters/cuotas_guardar.php` las llamaba **una vez POR FILA**, sin cache,
aunque un Excel real de Cuotas trae muchas filas del MISMO cliente (una
por categoría) y texto de Sector repetido — cada fila volvía a hacer el
escaneo completo de la tabla entera, aunque ya se hubiera resuelto ese
mismo cliente/sector antes en la misma subida. Con un archivo de varias
decenas de filas, esto se traduce en minutos reales de espera, no un
cuelgue infinito de verdad — pero se siente igual de "eterno" sin ninguna
barra de progreso que lo explique.

**Corregido con cache dentro de la misma request** (`getters/cuotas_guardar.php`
y `getters/cuotas_verificar_estado.php`, el chequeo que corre ANTES de
guardar cada vez que se cambia el Año) — un array `$cacheSector`/
`$cachePosId` guarda el resultado la primera vez que aparece un
Sector/Cliente+CEDI puntual, y las filas siguientes con el mismo texto
reusan el resultado en vez de volver a consultar la base. Es una
optimización 100% segura (el mismo texto de entrada da siempre el mismo
resultado dentro de una sola subida, no es una aproximación) — no cambia
ningún resultado, solo evita repetir consultas idénticas. De paso, en
`cuotas_verificar_estado.php` el `$stmt` del chequeo "¿ya existe/está
usada?" pasó de prepararse de nuevo en cada vuelta del loop a prepararse
una sola vez afuera.

**No se tocó** el esquema de `repositorio_locales_supervisores_cliente`
(agregar un índice ahí sería la solución "de raíz", pero el usuario ya
rechazó explícitamente tocar esa tabla externa en otra sesión) — este fix
reduce cuántas veces se pega contra esa tabla sin índice, no arregla que
la tabla en sí sea lenta de buscar.

**Probado**: `php -l` limpio en los 2 archivos, `node --check` limpio en
`repositorios.js`. **Todavía sin probar con un archivo real grande en
navegador** — falta confirmar que la mejora de tiempo es perceptible con
el mismo Excel que causó el "guardando eterno".

## Rebate sacado por completo del Repositorio de Cuotas — nunca se pidió (2026-08-30, mismo día, ronda 8)

El usuario aclaró, molesto y explícito: **nunca pidió que Cuotas Trimestrales
tomara una columna Rebate del Excel** — esto se agregó el 2026-08-28 por
una mala interpretación de un pedido que en realidad era sobre los Excel
que se descargan en Historial (Rebate), no sobre el Excel que se sube en
Repositorios > Cuotas (ver la aclaración del propio usuario documentada en
ese momento: "nooooo ahí nooooooo era... me decía a los Excel que se
descargan en Historial"). En su momento se decidió no revertirlo porque no
parecía dañino — el usuario ahora pidió sacarlo de raíz.

**Sacado por completo**:
- `includes/repositorio_import.php` — `repositorio_parsear_cuotas()` ya no
  busca ni lee ninguna columna REBATE/REBATE %/REBATE PCT del Excel de
  Cuotas (sí se sigue usando esa misma búsqueda para
  `repositorio_parsear_rebate()`, el Repositorio de Rebate — es una
  función distinta, no se tocó).
- `getters/cuotas_guardar.php` — el INSERT/UPSERT volvió a 2 niveles de
  fallback (con/sin Subcategoría+Marca, igual que antes del 2026-08-28) en
  vez de 3 (ya no existe el nivel "con Rebate"); se sacó la variable
  `$rebatePct` y el branch `$conRebate` del `bind_param()`.
- `includes/functions.php` — `obtener_precarga_detalle()`: la 1ra
  prioridad "usar el rebate_pct que trajo el Excel" se sacó por completo
  — ahora el Rebate % de una Acta Precargada sale SIEMPRE de
  `buscarRebateProducto()` (búsqueda contra el repositorio real, mismo
  criterio que la búsqueda en vivo de Registrar), sin ninguna excepción.
  `listar_repositorio_cuotas()` (tabla principal ya guardada) también dejó
  de seleccionar `rebate_pct`, volvió a 2 niveles de fallback.
- `assets/js/repositorios.js` — se sacó la columna "Rebate %" de
  `CONFIG.cuotas.columnasPreview` (previsualización antes de guardar) y de
  `CONFIG.cuotas.columnas` (tabla principal ya guardada).
- **No se tocó** la columna `rebate_pct` de la tabla
  `repositorio_cuota_cliente` en sí (dropear una columna está prohibido
  para Claude incluso bajo la excepción de este proyecto — solo
  `CREATE`/`ALTER` con confirmación, nunca `DROP COLUMN`) — sigue
  existiendo en la base, simplemente el código ya no la lee ni la escribe.
  Si el usuario quiere limpiarla del todo, puede pedir el `ALTER TABLE ...
  DROP COLUMN rebate_pct` para correrlo él mismo o confirmarlo puntual.
- **Tampoco se tocó** el Repositorio de Rebate en sí (pestaña separada,
  `repositorio_rebate_producto`) ni su conexión con Registrar (Meta de
  Compras) — eso es una función totalmente distinta que el usuario nunca
  cuestionó, solo se sacó la mezcla indebida hacia Cuotas.

**Probado con datos reales de solo lectura** (sin escribir nada):
`obtener_precarga_detalle()` corrida contra 3 clientes reales con Cuotas
`pendiente_uso` — sin errores, el Rebate % de cada línea sale de la
búsqueda real (ej. `BARRA/LAVAVAJILLAS/EL ARRANCAGRASA` da 0 porque
genuinamente no hay dato cargado para ese producto — mismo hallazgo ya
confirmado en la auditoría completa de Rebate más arriba — el resto de
combinaciones con dato real sí resuelven bien). `php -l`/`node --check`
limpios en los 4 archivos. **Todavía sin probar en navegador real.**

## Repositorio de Cuotas: agrupado visual por color pastel + columna "Estado" sacada de la tabla (2026-08-30, mismo día)

El usuario reportó que la alternancia par/impar existente (tinte azul muy
sutil, 2026-08-25) no alcanzaba para distinguir dónde termina un cliente y
empieza el siguiente en la tabla principal de Cuotas Trimestrales. Se
exploró la idea con `design` (Claude Design canvas, 3 conceptos: color
pastel por grupo, fila de encabezado por cliente, y una combinación de
ambos) — el usuario eligió el primero ("Main"/Opción A: color pastel por
cliente, sin fila de encabezado extra).

**Implementado en `assets/js/repositorios.js` (`renderFilas()`) y
`assets/css/style.css`**: la alternancia par/impar (`.ac-repo-fila-grupo-par`,
un solo tinte azul parejo) se reemplazó por 3 tonos pastel que rotan por
GRUPO (nunca por fila) — `.ac-repo-fila-grupo-a/-b/-c`, cada uno con su
propio fondo + un borde de color de 3px a la izquierda. Colores elegidos
deliberadamente FUERA de la familia verde/ámbar que ya usan los badges de
estado (`.ac-badge-ok`/`.ac-badge-revisar`) en el resto de la app, para que
el color de "a qué cliente pertenece esta fila" nunca se confunda con el
color de "qué estado tiene esta fila". Aplica solo donde `agruparPor` está
activo en `CONFIG` (hoy, solo la pestaña Cuotas — Rebate/Participación no
tienen cliente, no agrupan). Sin cambios en la vista mobile (tarjetas por
fila, `#repo-tabla-body tr` ya trae su propio fondo blanco fijo con mayor
especificidad — el tinte de grupo no aplica ahí, cada tarjeta ya muestra su
propio Cliente en el campo correspondiente).

**Columna "Estado" sacada de la tabla (no solo un ajuste de grouping)**: el
usuario encontró genuinamente confuso el badge "Pendiente de uso" — su
razonamiento, verificado como correcto contra el código real
(`getters/guardar_acuerdo.php` línea ~397-409): una vez que el asesor
genera el Acuerdo desde una Acta Precargada, TODAS las filas
`pendiente_uso` de ese cliente+trimestre+año pasan a `usada` juntas en un
solo `UPDATE` (no hay inconsistencia real fila-por-fila) — pero aun así, el
badge no aportaba nada accionable para el usuario: la fila "Pendiente de
uso" simplemente significa "todavía nadie generó el Acuerdo para este
cliente/trimestre", algo que ya cubre la campanita de alertas ("Actas
Asignadas") y el modal de Resumen — mostrarlo también acá, por fila, solo
sumaba ruido sin dar ninguna acción nueva. Se sacó la columna `estado` del
array `CONFIG.cuotas.columnas` (`repositorios.js`) — **el campo
`fila.estado` sigue existiendo en los datos y sigue gobernando toda la
lógica real** (el botón "Descartar"/"Reactivar" ya leía `fila.estado`
directo del objeto, no de la columna renderizada — confirmado que no se
rompe nada al sacar la columna), solo se dejó de MOSTRAR en esta tabla.

**Probado**: `node --check` limpio en `repositorios.js`, llaves de
`style.css` balanceadas (720/720), sin referencias colgantes a
`ac-repo-fila-grupo-par` en todo el proyecto (`grep` confirmado). **Todavía
sin probar en navegador real.**

## Modal de avisos post-guardado (Repositorios): agrupado por motivo, no una fila por línea (2026-08-30, mismo día)

El usuario re-subió el mismo Excel de Cuotas y el SweetAlert2 de "Hay algo
para revisar" salió como una lista angosta y muy alta — un `<li>` completo
("Cliente / Categoría: motivo") POR CADA fila afectada, y con ~20 avisos
(14 clientes con Sector "OTRAS CATEGORIAS" sin match + 5 categorías de
YUCAILLA PADILLA ya usadas en una Acta) el mismo motivo se repetía casi
palabra por palabra una y otra vez. Pedido explícito: "haciendo más ancho
el cuadro puedo acomodar bien la info".

**Aclaración de los 2 avisos que vio (no son bugs, es el comportamiento
esperado)**:
- **"No se pudo identificar la categoría 'OTRAS CATEGORIAS' en el
  catálogo"** — Sector genuinamente sin match real (mismo caso ya
  documentado varias veces en este archivo — JW confirmó que van a dejar
  de usar esa categoría, ver "Actas precargadas: filas vacías espejo..."
  más arriba). Va a seguir apareciendo mientras el Excel real siga
  trayendo filas con ese Sector.
- **"Esta categoría ya se usó en una Acta. No se modificó."** — las 4-5
  categorías de YUCAILLA PADILLA ya habían generado un Acuerdo real en
  pruebas anteriores de esta misma sesión — re-subir el mismo archivo
  confirma correctamente que esas filas quedaron protegidas, sin pisar el
  dato ya usado (mismo mecanismo documentado en "Bug real en
  `cuotas_guardar.php`" — Repositorio de Cuotas: borrado lógico). A
  diferencia de `duplicado_archivo` (una propiedad fija del archivo en sí,
  ver ronda 3 más arriba), este aviso SÍ se dejó tal cual, sin suprimir —
  es información real y distinta cada vez que cambia qué está usado.

**Rediseño del modal (`assets/js/repositorios.js`, el mismo bloque
`Swal.fire` de "Hay algo para revisar")**: los avisos ahora se agrupan por
`motivo` ANTES de armar el HTML — el texto del motivo se imprime una sola
vez por grupo (con un contador, ej. "14"), seguido de las filas afectadas
como chips que envuelven en horizontal, no una lista vertical de líneas
completas repetidas. El modal en sí se ensanchó (`width: 720` en el
`Swal.fire`, SweetAlert2 lo soporta como parámetro directo) y el contenido
tiene `max-height: 55vh` con scroll propio, para que un archivo con muchos
avisos no empuje el modal fuera de la pantalla. Clases nuevas en
`style.css`: `.ac-avisos-lista`/`.ac-avisos-grupo`/`.ac-avisos-grupo-motivo`/
`.ac-avisos-count`/`.ac-avisos-grupo-filas`/`.ac-avisos-chip` — mismos
tokens de color/espaciado del resto de la app, nada inventado.

**Probado**: `node --check` limpio, llaves de `style.css` balanceadas
(727/727, 7 bloques nuevos). **Todavía sin probar en navegador real** —
falta confirmar que el modal se ve bien con un archivo real de muchos
avisos (el mismo caso que reportó el usuario) y que el ancho/scroll se
comportan bien.

## Tabla nueva: `repositorio_cumplimiento_cuota` (2026-08-31)

**Corrida en producción por Claude** bajo la excepción puntual de este
proyecto (SQL exacto mostrado al usuario, confirmación explícita "sí"
recibida antes de ejecutar — ver la sección "⚠️ Excepción..." al inicio de
este archivo). El usuario pasó el `CREATE TABLE` ya diseñado (no se
discutió el diseño en esta sesión — probablemente viene de la otra sesión
en paralelo que está trabajando este mismo archivo, ver el aviso del
usuario "otra sesión también está anotando" en esta misma vuelta). Verificado
después con `DESCRIBE`/`SHOW INDEX` de solo lectura: las 22 columnas y los
3 índices (`uq_cumplimiento_cuota` único sobre `pos_id, sector, trimestre,
anio`, más `idx_cumplimiento_cuota_eliminado_en` e
`idx_cumplimiento_cuota_periodo`) quedaron exactos al SQL confirmado, 0
filas (tabla recién creada).

```sql
CREATE TABLE repositorio_cumplimiento_cuota (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	pos_id VARCHAR(200) NOT NULL,
	cliente_excel VARCHAR(300) NOT NULL,
	cedi_excel VARCHAR(200) NULL,
	plan_excel VARCHAR(100) NULL,
	sector VARCHAR(200) NOT NULL,
	trimestre TINYINT UNSIGNED NOT NULL,
	anio SMALLINT UNSIGNED NOT NULL,
	cuota_total DECIMAL(12,2) NOT NULL DEFAULT 0,
	venta_total DECIMAL(12,2) NOT NULL DEFAULT 0,
	cumplimiento_pct DECIMAL(7,4) NOT NULL DEFAULT 0,
	gana_categoria ENUM('gana','no_gana') NOT NULL DEFAULT 'no_gana',
	gana_categoria_anterior ENUM('gana','no_gana') NULL,
	gana_total ENUM('gana','no_gana') NOT NULL DEFAULT 'no_gana',
	rebate_pct DECIMAL(6,4) NULL,
	pre_rebate DECIMAL(12,2) NULL,
	rebate_maximo_110 DECIMAL(12,2) NULL,
	rebate_real_vol DECIMAL(12,2) NOT NULL DEFAULT 0,
	actualizado_por INT UNSIGNED NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	eliminado_en DATETIME NULL,
	eliminado_por INT UNSIGNED NULL
);

CREATE UNIQUE INDEX uq_cumplimiento_cuota ON repositorio_cumplimiento_cuota (pos_id, sector, trimestre, anio);
CREATE INDEX idx_cumplimiento_cuota_eliminado_en ON repositorio_cumplimiento_cuota (eliminado_en);
CREATE INDEX idx_cumplimiento_cuota_periodo ON repositorio_cumplimiento_cuota (trimestre, anio);
```

**Lectura propia de las columnas (inferida por el nombre, NO confirmada con
el usuario ni con la otra sesión — corregir esta nota si se descubre que
está mal)**: la clave única `(pos_id, sector, trimestre, anio)` es la misma
que ya usa `repositorio_cuota_cliente`, y las columnas
`cuota_total/venta_total/cumplimiento_pct/gana_categoria/gana_total/
rebate_pct/pre_rebate/rebate_maximo_110/rebate_real_vol` calzan casi
exacto con las columnas calculadas de la hoja "CUOTA CLIENTE - CATEGORÍA"
del export de Historial (`getters/exportar_cuota_categoria.php`, ver esa
sección más arriba) — todo indica que esta tabla es para guardar en la
base el resultado de venta/cumplimiento/rebate real por cliente+categoría
(hoy ese cálculo vive solo como fórmulas dentro del `.xlsx` exportado,
nunca persistido) — probablemente para poder mostrarlo/consultarlo desde
la app en vez de depender de que JW devuelva el Excel completado. **Nada
de la lógica que llena esta tabla (importador, cálculo, pantalla) está
construido todavía** — por ahora solo existe el `CREATE TABLE`. Sin
código nuevo en este archivo de trabajo (`Claude`) para esta tabla más
allá de crearla — si la otra sesión ya tiene piezas construidas
(parser/getter/UI), releer esta sección puede quedar desactualizada apenas
se fusionen ambas sesiones.

## Cumplimiento de Cuota — 3 mejoras (2026-08-31)

Módulo construido por la otra sesión (2026-08-30, ver
`components/cumplimiento/*`, `getters/cumplimiento_*.php`,
`datos/cumplimiento_cuota_schema.sql`) — esta vuelta son 3 pedidos del
usuario sobre ese módulo ya en pie, probando con un Excel real
(`datos/CuotaCategoria_Directa_2026-08-29.xlsx`).

1. **Se quitó el banner "Gana Categoría .../ Gana Total..."** — pedido
   explícito, sin reemplazo. `components/cumplimiento/cumplimiento.php`
   (bloque `.ac-cumpl-banner` completo) + su CSS en `style.css` (clase sin
   otro uso, se borró entera).

2. **Bug real: 2 filas del mismo Sector para un mismo cliente se perdían
   una a la otra al guardar.** Encontrado con datos reales: el cliente
   "DISTRIBUIDORA NOVOA E HIJOS SOCIEDAD CIVIL" trae 2 líneas de "AEROSOL"
   en el Excel real (cuota $62 y $73, misma venta real $108 — probablemente
   2 Subcategorías de Aerosol que esta hoja no distingue por nombre) — la
   clave única `(pos_id, sector, trimestre, anio)` hacía que la 2da pisara
   a la 1ra vía `ON DUPLICATE KEY UPDATE`, mostrando solo 1 de las 2 filas
   reales. **Se le preguntó al usuario cómo resolverlo** (guardar las 2 por
   separado / sumarlas en 1 sola / dejarlo documentado nomás) — **eligió
   guardarlas por separado**, calcando el Excel real tal cual.
   - Columna nueva `linea` (1, 2, 3... según el orden en que ese mismo
     cliente+CEDI+Sector aparece en el archivo) entra a la clave única:
     `(pos_id, sector, trimestre, anio, linea)`. Asignada en
     `repositorio_parsear_cumplimiento_cuota()` (`includes/repositorio_import.php`)
     al momento del parseo — así el mismo valor le llega intacto tanto a
     `cumplimiento_verificar_estado.php` (el badge "Al guardar" de la
     previsualización) como a `cumplimiento_guardar.php` (el INSERT real),
     sin tener que recalcularlo dos veces. Se llama "línea" (línea 1 de 2,
     línea 2 de 2) — el usuario pidió explícito no llamarla "ocurrencia"
     porque suena a que algo falló, cuando es un dato normal.
   - **Pendiente correr en la base real, en 2 partes** — confirmado con
     `DESCRIBE` que la tabla ya existe en producción con 8 filas reales, sin
     esta columna todavía. Ver "Excepción a la regla de solo lectura" al
     principio de este archivo: Claude puede correr `CREATE`/`ALTER` acá
     (mostrando el SQL antes, con confirmación explícita), pero cualquier
     `DROP` —incluido `DROP INDEX`— sigue siendo solo del usuario, sin
     excepción:
     1. El usuario corre en HeidiSQL: `DROP INDEX uq_cumplimiento_cuota ON repositorio_cumplimiento_cuota;`
     2. Recién ahí, Claude corre (con el SQL mostrado y confirmado antes):
        ```sql
        ALTER TABLE repositorio_cumplimiento_cuota
          ADD COLUMN linea TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER sector;
        CREATE UNIQUE INDEX uq_cumplimiento_cuota ON repositorio_cumplimiento_cuota (pos_id, sector, trimestre, anio, linea);
        ```
     Nada de este fix funciona en producción hasta correr esto — mientras
     tanto `cumplimiento_guardar.php` va a fallar el INSERT (columna no
     existe). Las 8 filas ya guardadas siguen teniendo el problema viejo
     (una sola fila donde debería haber 2) hasta que el usuario resuba el
     Excel real después del `ALTER`.
   - Se sacó el aviso "Cliente y categoría repetidos... se usó el valor más
     reciente" (`tipo: 'duplicado_archivo'`) de `cumplimiento_guardar.php` —
     ya no aplica, ahora ambas filas se guardan de verdad, no tendría
     sentido seguir avisando que se descartó una.
   - **Probado de punta a punta, solo lectura** (nunca se guardó nada real):
     `repositorio_parsear_cumplimiento_cuota()` corrido directo contra el
     Excel real confirma `linea=1`/`linea=2` para las 2 filas de AEROSOL,
     con sus $ reales distintos preservados — antes del fix, la 2da hubiera
     pisado a la 1ra.

3. **Acordeón: cada asesor arranca cerrado, clic en su cabecera lo abre**
   (pedido explícito — "el droplist... estará cerrado... al que le doy
   clic se abre"). `assets/js/cumplimiento.js`, `renderLista()`: el bloque
   de clientes de cada asesor se envuelve en un `<div class="hidden"
   id="cumpl-grupo-N">` (clase `.hidden` genérica del proyecto, arranca
   siempre colapsado); clic en `.ac-cumpl-fila-usuario` togglea esa clase +
   rota un chevron (`.ac-cumpl-chevron`/`.ac-cumpl-chevron-abierto`, mismo
   patrón de transición ya usado en `.ac-select-bonito-chevron`). Cada
   asesor abre/cierra independiente de los demás. `style.css`: `cursor:
   pointer` + hover en la cabecera, nada más se tocó del layout existente.
   **Probado con Playwright, datos sintéticos por red (sin tocar la
   base)**: captura antes (2 asesores, ambos colapsados, chevrons "›") y
   después de un clic (solo el asesor clickeado se expande, con sus 2
   categorías reales AEROSOL/PASTAS visibles, el otro asesor se mantiene
   colapsado) — sin superposición, layout limpio en ambos estados.

## Repositorio de Cuotas — "Otras Categorías" ignorada + aviso "ya usada" con tarjeta real (2026-08-31, mismo día)

Dos pedidos más sobre Cuotas Trimestrales (no Cumplimiento):

1. **"Otras Categorías" se ignora del todo, sin aviso** — JW confirmó que
   dejaron de trabajar esa categoría (ver "Alcance real de Acuerdos
   Comerciales" más arriba). Filtrada en `repositorio_parsear_cuotas()`
   (`includes/repositorio_import.php`) — ni siquiera llega a la
   previsualización, no solo al guardado — con una red de seguridad
   duplicada en `getters/cuotas_guardar.php` por si algo se salta el
   parseo. Probado contra el Excel real de prueba: de 60 filas totales,
   las 12 de "Otras Categorías" desaparecen, quedan 48.

2. **Bug real encontrado por el usuario**: el aviso "Esta categoría ya se
   usó en una Acta. No se modificó." (protección de `cuotas_guardar.php`
   contra revivir una cuota ya consumida) no decía NADA de cuál Acta, quién
   la generó ni cuándo — el usuario preguntó "por qué no usás el
   significado real" y recordó el diseño comparativo ya aprobado en Claude
   Design para "Actas en Choque" (ver sección de arriba, mismo día). Se
   aplicó el mismo patrón acá:
   - `cuotas_guardar.php`: la consulta de la fila ya usada ahora hace
     `LEFT JOIN` a `repositorio_acuerdos`/`repositorio_usuarios_acuerdos`
     y el aviso lleva `tipo: 'ya_usada'` + `existente_documento_no`/
     `existente_usuario`/`existente_fecha`, no solo el texto genérico.
   - **Bug real de mi propio fix anterior, encontrado probando**: había
     editado primero `mostrarErroresPreview()` (`assets/js/repositorios.js`)
     pensando que era la función que renderiza esto — probé con Playwright
     y no aparecía nada. La función REAL que usa el botón "Guardar" para
     Cuotas es otra completamente distinta, más abajo en el mismo archivo
     (el handler de `subirGuardarBtn`, que arma un `Swal.fire()` agrupando
     avisos por motivo en chips, ver "Ronda 2030-08-30 — agrupado por
     motivo"). Ahí es donde se aplicó el fix real: los avisos con
     `tipo==='ya_usada'` se sacan del agrupado-por-chips (donde perderían
     el detalle real, todas las filas comparten el mismo texto de motivo) y
     se renderizan como tarjetas comparativas (`filaAvisoYaUsada()`, reusa
     las clases `.ac-choque-*` globales) dentro del mismo `Swal.fire`, con
     documento/usuario/fecha reales por fila. Los demás avisos (sin
     cliente, sector sin identificar) se quedan como chips agrupados, sin
     cambios — a esos si les alcanza el texto genérico agrupado.
   - **Probado con Playwright, datos sintéticos por red (nunca tocó la
     base)**, con la flow REAL de la UI (abrir modal, elegir archivo,
     esperar previsualización, click Guardar) para asegurarse de pasar por
     el código real y no un atajo: el `Swal.fire` real muestra las 2
     tarjetas con `ADN-2026-0057`/Javier Maldonado/28-08 y
     `ADN-2026-0058`/Carlos Proaño/31-08 — exactamente los 2 casos reales
     que el usuario había visto (confirmados antes contra la base real vía
     `SELECT`, ver hallazgo previo).
   - **Lección para la próxima vez que se toque el flujo de guardado de
     Repositorios**: hay 2 caminos de renderizado de avisos post-guardado
     que parecen la misma cosa pero no lo son — `mostrarErroresPreview()`
     (usada solo cuando hay `errores` de verdad, y para Rebate/
     Participación en general) y el `Swal.fire` agrupado por motivo dentro
     del handler de `subirGuardarBtn` (el que de verdad se usa para avisos
     de Cuotas sin errores). Verificar CUÁL de los dos corre de verdad
     antes de asumirlo — yo mismo asumí mal la primera vez.

## Excel de Historial: columnas Subcategoría/Marca en "CUOTA CLIENTE - CATEGORÍA" (2026-08-31)

Pedido explícito, con captura del encabezado esperado: agregar Categoría/
Subcategoría (ya existía como "CATEGORIAS", ver más arriba — es el
`sector`) más una columna nueva de Marca, justo a la derecha de PLAN, en
ese orden exacto: `PLAN | CATEGORIAS | SUBCATEGORIA | MARCA | ...resto
sin alterar...`. Aplicado a los 2 formatos (Directo y Distribuidor), sin
tocar ninguna otra columna existente.

- `getters/exportar_cuota_categoria.php` y
  `getters/exportar_cuota_categoria_distribuidor.php`: el `SELECT` agregó
  `l.categoria, l.marca` (ya hacían `JOIN` a `repositorio_acuerdo_lineas`
  para el Sector). Los índices de columna posteriores se corrieron un
  puesto (`$colSubcategoria`/`$colMarca` nuevas, `$colConcat`/
  `$colCuotaInicio`/etc. desplazados +2) — mismo patrón ya usado cada vez
  que se agregó una columna a esta hoja (ver "Bug real encontrado y
  corregido" 2026-08-20, fila 1 sin pintar).
- **Probado**: `php -l` limpio en ambos archivos. No se pudo generar el
  `.xlsx` real en esta sesión (falta la extensión `zip` en el PHP CLI
  local, límite ya documentado varias veces en este archivo). **Todavía
  sin probar en navegador real.**

## Cumplimiento de Cuota: columnas Cuota/Rebate ocultas, donut de Cumplimiento, tile de promedio oculto (2026-08-31)

3 pedidos puntuales sobre el módulo Cumplimiento de Cuota (construido por
la otra sesión el 2026-08-30, ver sección "Tabla nueva:
`repositorio_cumplimiento_cuota`" más arriba):

- **Columnas "Cuota" y "Rebate ganado" ocultas** (lista principal y la
  tabla de previsualización antes de guardar) — mismo criterio "invisible
  hide" ya usado en otras partes de este proyecto (los datos siguen
  existiendo en JS/PHP, solo se dejan de RENDERIZAR): `assets/js/cumplimiento.js`
  sacó esas 2 entradas del array `cols` de `renderPreviewTabla()`;
  `assets/css/style.css` redujo `.ac-cumpl-col-header`/`.ac-cumpl-fila-cat`
  de 8 a 6 columnas de grid (`grid-template-columns`) y agregó
  `display:none` a los `nth-child` que correspondían a esas 2 columnas,
  con el mismo ajuste replicado en el `@media(max-width:900px)` de mobile
  (grid-template-areas sin "cuota"/"rebate", nth-child reasignados).
- **"Cumplimiento" pasó de número plano a un mini donut** — nueva
  `donutCumplimiento(v)` en `cumplimiento.js` (conic-gradient + `::before`
  para el hueco central + texto de % superpuesto), reusando el mismo
  patrón visual que ya usa `ringDeUsuario()` en Seguimiento de Equipo
  (2026-08-27) para consistencia — no una idea nueva, un componente ya
  validado en otro módulo. `filaCategoria()` llama a esta función en vez
  de `pctTexto()` para esa columna.
- **Tile "Cumplimiento promedio" ocultado** — `style="display:none;"` en
  `components/cumplimiento/cumplimiento.php`, `<span
  id="cumpl-stat-promedio">` intacto (el JS lo sigue llenando, solo no se
  ve) — mismo criterio de "ocultar sin borrar" del resto del pedido.
- **Probado**: `node --check` limpio en `cumplimiento.js`, llaves de
  `style.css` balanceadas. **Todavía sin probar en navegador real.**

## Historial por Canal + Excel por formato + "Ver todo" del superdesarrollador (2026-08-31)

Cambio organizacional: la descarga de Excel se restringe a una sola cuenta
`superdesarrollador`, que además necesita ver las Actas de LOS 2 canales
(Directo y Distribuidor), no solo las propias — a diferencia de
`desarrollador`, que sigue viendo exactamente lo mismo de siempre (mismo
Historial, mismas Actas propias) salvo que pierde el botón "Descargar
Excel". **Diseñado primero con Claude Design** (2 opciones — pill-filter
vs. dual-card), el usuario eligió la Opción A (pastillas de filtro, mismo
patrón visual que ya usan Cumplimiento/Seguimiento de Equipo) explícitamente
por bajo impacto visual: "no habría tanto cambio visual... solo aplicás el
cambio en esas zonas sin dañar lo demás que está perfecto donde está".

**Alcance confirmado con el usuario (vía `AskUserQuestion`, con un ejemplo
concreto para desambiguar una respuesta confusa la primera vez)**: el
superdesarrollador SÍ puede abrir/ver detalles y descargar el PDF de una
Acta ajena en Historial (necesita poder revisar lo que ve) — pero Subir
Firma y Eliminar siguen restringidos SOLO al dueño real de esa Acta
(`creado_por`), sin excepción por rol.

**Piezas**:
- `includes/functions.php` — `listar_historial_acuerdos()`/
  `obtener_stats_historial()`/`listar_anios_disponibles()` ganaron un
  parámetro `$rol` (para el "ver todo") y las 2 primeras además `$canal`
  ('directo'/'distribuidor'/'total'). `$verTodos = ($rol ===
  'superdesarrollador') ? 1 : 0` se combina con `(? = 1 OR a.creado_por =
  ?)` en el SQL — mismo truco de arity fija ya documentado en varias
  partes de este archivo, para no tener que armar `bind_param` con
  cantidad variable de placeholders según el rol.
  - **Bug real encontrado y corregido en la misma sesión, antes de
    reportarlo como terminado**: el primer filtro de canal comparaba
    `d.canal <> 'DISTRIBUIDOR'`/`d.canal = 'DISTRIBUIDOR'` directo sobre
    el `JOIN` a `repositorio_locales_supervisores_cliente` — como esa
    tabla puede tener 2+ filas con el MISMO `pos_id` pero `canal`
    DISTINTO entre sí (documentado desde 2026-07-26, "~1,116 pos_id
    duplicados"), un mismo Acuerdo podía calzar en las 2 consultas
    (Directo Y Distribuidor) a la vez — confirmado con datos reales:
    Acuerdo real `#41` (`ADN-2026-0038`, pos_id `EPVD15130`) tiene una
    fila `DISTRIBUIDOR` y otra `MAYORISTA` en el maestro, y la suma de
    "directo"(6) + "distribuidor"(3) daba 9, no los 8 reales de "total".
    **Corregido con `EXISTS`/`NOT EXISTS`** (gana `DISTRIBUIDOR` si existe
    CUALQUIER fila así para ese `pos_id`, resuelto UNA sola vez por
    Acuerdo, mutuamente excluyente de verdad) en las 2 funciones, y el
    `d.canal` crudo del `SELECT` (usado por el badge de Canal de cada
    fila) se reemplazó por la misma expresión canónica
    (`CASE WHEN EXISTS(...) THEN 'DISTRIBUIDOR' ELSE 'OTRO' END`) — para
    que el badge de una fila nunca contradiga en qué pastilla de filtro
    cae ese mismo Acuerdo. **Reverificado con el mismo método (SELECT
    suelto, solo lectura, nunca llamando a las funciones reales — esas
    arrancan con `barrer_actas_vencidas()`, un `UPDATE`)**: superdev
    id=1, directo=5 + distribuidor=3 = total=8, coincide exacto.
- `renderFilaHistorial($a, $mostrarCanal=false)` — celda de Canal nueva
  (badge Directo/Distribuidor) insertada entre Localidad y Periodo,
  gateada por `$mostrarCanal` (solo `true` para el superdesarrollador —
  un desarrollador normal siempre ve un canal único, la fila queda
  IDÉNTICA a antes de este cambio). `$esPropio = creado_por ===
  session.user_id`; `$disabledAjeno = $esPropio ? '' : ' disabled'`
  aplicado al botón de Firma (ambas variantes) y al de Eliminar — usa el
  atributo HTML nativo `disabled` + una regla CSS compartida ya existente
  (`.ac-icon-btn:disabled, .ac-btn-outline:disabled, ...`), cero CSS
  nuevo necesario.
- `components/historial/historial.php` / `getters/listar_historial.php` —
  `$esSuperdev`, whitelist de `$canal` (`total`/`directo`/`distribuidor`),
  pastillas de filtro (`.ac-seg-pill`/`.ac-seg-pill-group`, reusado tal
  cual de Cumplimiento/Seguimiento de Equipo, cero CSS nuevo para el
  filtro en sí) gateadas a `$esSuperdev`, `<th>Canal</th>` condicional +
  `colspan` corregido, y el picker de formato de Excel
  (`#hist-exportar-wrap`, patrón "expand-in-place" calcado de
  `.ac-repo-exportar` de Repositorios — Directo/Distribuidor como 2
  opciones que aparecen al hacer click, no un `<select>` ni un modal).
- `assets/js/historial.js` — reestructuración del bloque de export (4
  variables nuevas: `exportarWrap`/`exportarBtn`/`exportarDirectoLink`/
  `exportarDistribuidorLink`, reemplazando el `exportarCuotaLink` único de
  antes), listeners de las pastillas de canal, `cargarHistorial()` con
  `&canal=` en la URL y ambos hrefs de export actualizados según los
  filtros activos.
- `getters/exportar_cuota_categoria.php` — restringido a
  `rolPermitido(['superdesarrollador'])` únicamente (antes cualquier rol
  con acceso a Historial). `?canal=distribuidor` en la URL delega a
  `exportar_cuota_categoria_distribuidor.php` (`require` + `exit`, mismo
  patrón que ya usaba el branch de `canalDeSupervisor()` antes de este
  cambio); sin ese parámetro, exporta Directo. Se sacó `AND
  a.creado_por = ?` de las 2 queries (Cuota y Visibilidad) — el
  superdesarrollador ahora exporta lo de CUALQUIER asesor de ese canal,
  no solo lo suyo. Mismo cambio (sacar el filtro de `creado_por`) en
  `exportar_cuota_categoria_distribuidor.php`.
- `getters/generar_acta_pdf.php` — `$puedeVerCualquiera =
  ($_SESSION['rol'] ?? '') === 'superdesarrollador'`; el chequeo de 404
  ahora es `!$cabecera || (!$puedeVerCualquiera && creado_por !==
  usuario_sesion)` — el resto de getters de escritura sobre una Acta
  (`subir_acta_firmada.php`, `descargar_acta_firmada.php`,
  `eliminar_acuerdo.php`) **NO se tocaron a propósito** — siguen
  exigiendo `creado_por` real, sin excepción de rol, tal como confirmó el
  usuario.

**Probado**: `php -l`/`node --check` limpios en los 7 archivos tocados,
llaves de `style.css` balanceadas (792/792). La corrección del bug de
canal ambiguo se verificó de punta a punta con datos reales de solo
lectura (nunca llamando a las funciones reales, que hacen un `UPDATE` vía
`barrer_actas_vencidas()` — se replicó la misma lógica SQL como SELECT
suelto, mismo criterio ya documentado en este archivo para evitar ese
error). **Todavía sin probar en navegador real** — falta que el usuario
entre como superdesarrollador y confirme visualmente las 3 pastillas de
canal, el picker de Excel con las 2 opciones, que los botones de Subir
Firma/Eliminar salen deshabilitados en una fila ajena, y que descargar
cada formato trae el contenido correcto.

**Ajuste, mismo día — "Descargar Excel" salta el picker si el canal ya no
es ambiguo**: pedido explícito ("si ya filtré por directa ahí ya no
tendría la doble opción... sino que ya saldría solo la descarga directa,
mismo caso para cuando pongo el filtro en distribuidor"). Con la pastilla
de Vista en "Directo" o "Distribuidor", el click en "Descargar Excel" ya
no abre el desplegable de 2 opciones — dispara directo la descarga de ESE
formato (simulando un click sobre el link real correspondiente, así
reusa intacta la misma validación de período/año que ya tenían los
links). Con "Total" (canal ambiguo) el botón sigue abriendo el picker de
siempre, sin cambios. `assets/js/historial.js`: `exportarBtn`'s listener
ahora chequea `canalFiltroActual` antes de abrir el picker; se agregó
`actualizarTituloExportar()` (el `title`/tooltip del botón cambia según
el canal activo, para que quede claro qué se va a descargar sin abrir
nada) llamada al cargar la página y en cada cambio de pastilla. **Bug
latente corregido de paso**: `canalFiltroActual` arrancaba hardcodeado en
`'total'` sin importar qué pastilla el servidor ya hubiera marcado activa
(ej. entrando con `?canal=directo` en la URL) — ahora se inicializa
leyendo la pastilla `.ac-seg-pill-activo` real del DOM al cargar.
**Probado**: `node --check` limpio. **Todavía sin probar en navegador
real.**

## Cumplimiento de Cuota: aclarado que solo soporta el formato Directo (aún no Distribuidor) (2026-08-31)

El usuario preguntó, antes de que se implementara nada, si el Excel que se
descarga para canal Distribuidor también lo iba a aceptar el importador de
Cumplimiento de Cuota (`repositorio_parsear_cumplimiento_cuota()`). **No**
— confirmado leyendo el código: hoy solo reconoce el formato de Directo, a
propósito (el comentario de esa función ya lo decía desde que se construyó:
"Alcance de esta primera versión: solo canal Directa"). Motivos concretos,
no solo el nombre de hoja distinto:
- Hoja `"CUOTAS POR CAT -DISTRIBUIDORES"` (Distribuidor) vs `"CUOTA CLIENTE
  - CATEGORÍA"` (Directo) — nombre distinto de pestaña.
- Columnas de identidad distintas: `DISTRIBUIDOR/CIUDAD/NOMBRE/CATEGORIA`
  (Distribuidor) vs `CEDI/CLIENTE/CATEGORIAS` (Directo).
- Sin columna `CARTERA` en Distribuidor (el parser la usa como ancla
  posicional para ubicar `VENTA TOTAL`).
- `"REBATE"` (Distribuidor) vs `"REBATE A APLICAR %"` (Directo) — nombre de
  columna distinto.
- `"GANA TOTAL Q"` (Distribuidor) vs `"GANA TOTAL"` (Directo) — nombre de
  columna distinto.
- Sin fila `TOTAL` al final.
- Unidad: Distribuidor mide en Cajas, no Dólares.
**No implementado todavía** — queda pendiente si el usuario pide construir
el branch de Distribuidor (mismo patrón, un segundo parser de hoja).

### Nombre de pestaña tolerante en `xlsx_leer_hoja()` (2026-08-31, mismo día)

Pregunta de seguimiento del usuario: ¿cómo detecta el sistema qué hoja es
la de "Cuota Categoría" — solo busca por nombre? **Confirmado que sí**, y
que la búsqueda del NOMBRE de la pestaña (a diferencia de los encabezados
de columna, que ya normalizaban mayúsculas/tildes desde el diseño
original, ver `xlsx_normalizar_encabezado()`) era una comparación EXACTA
(`isset($mapaHojas[$nombreHoja])`) — una pestaña tipeada en minúsculas, sin
tilde, o con un espacio de más rompía la búsqueda entera, aunque a simple
vista fuera "la misma hoja". El usuario pidió 2 cosas: que el sistema
tolere esas variaciones solo, y que el mensaje de error (cuando de verdad
no la encuentra) le diga al usuario que revise el nombre de la pestaña.

**Corregido en `includes/xlsx_reader.php`** (afecta a TODO lector de hoja
por nombre del proyecto, no solo Cumplimiento — mismo mecanismo compartido
que usa Liquidación):
- `xlsx_normalizar_nombre_hoja($texto)` (nueva) — mismo criterio que
  `xlsx_normalizar_encabezado()` (mayúsculas, sin tildes) más colapsar
  espacios de más a uno solo (`"Cuota  Cliente"` → `"Cuota Cliente"`) — algo
  que no hacía falta para encabezados de columna (celdas sueltas) pero sí
  es común en el nombre de una pestaña retipeada a mano.
- `xlsx_leer_hoja()` — el lookup exacto por clave del array se reemplazó
  por una comparación normalizada contra cada nombre real de pestaña del
  archivo — sigue devolviendo el path XML real de la hoja que matchea, solo
  cambió CÓMO se decide el match. Es puramente más permisivo (nunca deja de
  encontrar algo que antes sí encontraba), así que no hay riesgo de romper
  ningún importador existente.
- `repositorio_parsear_cumplimiento_cuota()` — se sacó el 2do intento
  manual "sin tilde" (ya no hace falta, la tolerancia ahora vive en
  `xlsx_leer_hoja()` mismo) y el mensaje de error se amplió: "Revisá el
  nombre de la pestaña en Excel: no importan mayúsculas, tildes ni
  espacios de más, pero el texto tiene que ser el mismo."
- **Probado**: `php -l` limpio en los 2 archivos. La normalización en sí se
  probó aislada (sin `ZipArchive`, que no está habilitado en el PHP CLI
  local — mismo límite ya documentado varias veces en este archivo): 5
  variantes del nombre de la hoja (con tilde, sin tilde, minúsculas, con
  espacio de más, con espacio al inicio) normalizan todas al mismo texto.
  **No se pudo probar `xlsx_leer_hoja()` de punta a punta contra un
  archivo `.xlsx` real** en esta sesión por el mismo límite del CLI local
  — el cambio es una generalización directa de un patrón ya usado y
  probado (`xlsx_normalizar_encabezado()`), bajo riesgo, pero falta la
  confirmación real en navegador/servidor.

**Ajuste, mismo día**: el error de "hoja no encontrada" salía como toast
(desaparece antes de leerlo) — pasó a modal (`Swal.fire`, `mostrarErrorArchivo()`
en `cumplimiento.js`), con un mini combo de texto copiable + botón (nombre
de hoja esperado, `Clipboard API`) para pegarlo directo en Excel. Backend
manda `hoja_esperada` como campo aparte del mensaje. Mensaje acortado.
CSS nuevo: `.ac-copiable*`. `php -l`/`node --check` limpios, CSS balanceado
(797/797). Sin probar en navegador.

**Ajuste, mismo día — sin voseo + modal "inteligente" por tipo de error**:
mensaje tenía "Subí"/"Revisá" (voseo, corregido a "sube"/"revisa" — ver
`feedback_sin_voseo_texto_visual` en memoria, nunca más). Además, el
usuario preguntó si reordenar columnas cerca de "REBATE A APLICAR %"/
"CARTERA" podía romper la lectura en silencio (esas 2 se ubican por
posición, no por nombre, ver más arriba) — **sí podía**, así que se agregó
una validación nueva: el encabezado real en esa posición debe empezar con
"TOTAL Q"/"VENTA Q" (dinámico por trimestre); si no, `repositorio_parsear_cumplimiento_cuota()`
devuelve `tipo: 'columnas_movidas'` en vez de seguir con datos erróneos.
El modal (`mostrarErrorArchivo()`) ahora arma su contenido según
`data.tipo` (`hoja_no_encontrada`/`columnas_movidas`/`columnas_faltantes`/
`trimestre_no_determinado`) — cada uno con su propia pista corta, no un
texto genérico. `cajaCopiable()` generalizada para soportar más de una caja
por modal. Probado aislado (regex de detección con 6 casos reales/
inventados) sin `.xlsx` real (mismo límite del CLI local de siempre).
Sin probar en navegador.

## Cumplimiento de Cuota: soporte para canal Distribuidor (2026-08-31)

`repositorio_parsear_cumplimiento_cuota()` ahora prueba las 2 hojas (Directo
"CUOTA CLIENTE - CATEGORÍA", Distribuidor "CUOTAS POR CAT -DISTRIBUIDORES")
y delega a una función propia por canal — mismo criterio de "columnas
movidas" (posición + regex de sanity-check) ya usado en Directo, con sus
propios anclas (Distribuidor no tiene CARTERA: cuota total = antes de
"REBATE", venta total = antes de "CUMPLIMIENTO"). Si no encuentra ninguna
hoja, el modal ahora muestra las 2 pestañas esperadas con botón de copiar
cada una. Probado con datos sintéticos (sin `.xlsx` real, límite de
siempre): layout normal OK, columna insertada cerca de REBATE detectada
como `columnas_movidas` con las columnas de referencia correctas.

Confirmado (solo lectura): Seguimiento de Equipo YA incluye Actas de los 2
canales sin cambios — sus queries nunca filtran por canal.

Voseo corregido en 5 lugares más de este módulo ("Subí"→"Sube", "hacé
click"→"haz click", "revisá"→"revisa" ×2, "Revisá"→"Revisa").

**Pendiente, con mockup ya publicado para revisar**: agregar la pastilla
Total/Directo/Distribuidor a la UI de Cumplimiento (como en Historial) +
badge de canal por cliente + "cajas" sin "$" en filas de Distribuidor —
2 opciones de dónde va el badge, ninguna construida todavía.

## Gestión de Usuarios — columna Canal + roles renombrados a Usuario/Administrador (2026-08-31)

Pedido explícito, 3 partes:

1. **Combo "Nuevo Usuario"/"Editar Perfil" ya no muestra los supervisores de
   prueba de Alicorp** — encontrados con datos reales:
   `repositorio_locales_supervisores_cliente` tiene `PRUEBA DISTRIBUIDOR`,
   `PRUEBA AUTOSERVICIO`, `PRUEBA MAYORISTA`, `PRUEBA COBERTURA` mezclados
   con los supervisores reales. `listar_supervisores_disponibles()`
   (`includes/functions.php`) ahora filtra `supervisor NOT LIKE 'PRUEBA %'`
   — por prefijo, no una lista fija de 4, para cubrir cualquier otra
   "PRUEBA X" que Alicorp agregue después sin tocar este código de nuevo.
2. **Columna nueva "Canal" en la tabla de Usuarios Registrados** — Directo/
   Distribuidor/"—" (sin resolver, ej. la cuenta "Admin" sin supervisor)
   por usuario, resuelto en vivo con la misma `canalDeSupervisor()` que usa
   el resto de la app, nunca guardado. Calculado en PHP dentro de
   `listar_usuarios_acuerdos()` (no en SQL — la lista pagina de a 8, no
   vale la pena una subquery por fila), mostrado en `renderFilaUsuario()`.
   Mismo cambio en la tabla inicial (`gestion-usuarios.php`) y el refresco
   AJAX (`getters/tabla_usuarios.php`) — colspan del "no encontrados"
   actualizado de 6 a 7 en los 2 lugares.
3. **Roles renombrados en pantalla: "Desarrollador" → "Usuario",
   "Superdesarrollador" → "Administrador"** — SOLO la etiqueta visible,
   mismo criterio que el rename "Local"/"Distribuidor" en Registrar (ver
   sección de arriba): el valor real de columna/ENUM (`desarrollador`/
   `superdesarrollador`) y toda la lógica de permisos (`rolPermitido()`,
   `includes/secciones.php`, roles de cada getter) siguen exactamente
   igual, sin tocar. Cambiado en un solo punto para los badges/header
   (`rolEtiqueta()`, `includes/functions.php`) — usado tanto en la tabla de
   Gestión de Usuarios como en el label de rol del header
   (`index.php`, junto al nombre de usuario) — más los 2 `<select>` de rol
   en `components/gestion-usuarios/gestion-usuarios.php` (Nuevo Usuario y
   Editar Perfil), que tenían el texto de las `<option>` hardcodeado, no
   vía `rolEtiqueta()`. **No se tocó** "Desarrollador de Mercado" en
   `includes/acta_pdf.php` — es la etiqueta de firma del Acta de
   Distribuidor, coincide la palabra pero es un concepto totalmente
   distinto (puesto real en el documento impreso, no el rol de la app).

**Pregunta aparte del usuario, respondida sin tocar código**: por qué no
hay una cuenta de Michelle (JW) si las reuniones decían que ella tendría
acceso — confirmado con datos reales que su nombre no existe en
`repositorio_locales_supervisores_cliente.supervisor` (tiene sentido, es
personal de JW, no una asesora de Alicorp con cartera). Impacto de crear
su cuenta igual: bajo — `supervisor` es NULLABLE y **la cuenta "Admin"
(id=1) ya funciona hoy exactamente así** (rol Administrador, sin
supervisor), mismo patrón que necesitaría ella. Con supervisor vacío,
`canalDeSupervisor()` devuelve `null` sin romper nada (ya manejado en
`acuerdo_distribuidores.php` con `?: 'directo'`) — el único efecto real es
que el dropdown de "Local" en Registrar le quedaría vacío, esperable
porque ella no genera Actas. Con rol Administrador vería los módulos que
las reuniones describían para ella (Repositorios, Seguimiento de Equipo,
Cumplimiento de Cuota, Gestión de Usuarios). Queda pendiente que el
usuario la cree él mismo desde "Nuevo Usuario" — Claude no puede.

**Probado**: `php -l` limpio en los 3 archivos tocados;
`listar_usuarios_acuerdos()` corrida contra la base real confirma
`rolEtiqueta()` da "Usuario"/"Administrador" y `canal` resuelve correcto
por fila (Carlos Proaño/Javier/Franklin=directo, Adrián=distribuidor,
Admin=NULL→"—"). Verificado visualmente con Playwright (servidor local +
sesión falsa) contra la tabla real: columna Canal presente con los valores
esperados, badges de rol y el label del header ya muestran "USUARIO"/
"ADMINISTRADOR".

## Cumplimiento de Cuota: pastilla Vista + "Subir Excel" con formato + Cajas (2026-08-31)

Mockup Opción A aprobado, implementado: pastilla Total/Directo/Distribuidor
(igual a Historial), sobre `listar_cumplimiento_cuota()`/
`resumen_cumplimiento_cuota()` — mismo filtro EXISTS-based canónico que ya
usa Historial (`condicionCanalCumplimiento()`, nueva, reusada en las 2).
Verificado con datos reales: directo(9)+distribuidor(3)=total(12), exacto.

- Badge de canal por cliente (`.ac-badge-canal-*`, igual que Historial)
  **solo con Vista="Total"** — con un canal ya filtrado, se oculta (pedido
  explícito, sería redundante).
- "Subir Excel" ahora es un `.ac-repo-exportar` (mismo mecanismo que
  "Descargar Excel" en Historial): con Vista="Total" se expande a 2
  opciones (Directo/Distribuidor); con un canal ya filtrado, salta el
  picker y abre directo para ese formato.
- El formato elegido (a mano o heredado de la Vista) se manda como
  `canal_esperado` junto al archivo — `cumplimiento_previsualizar_excel.php`
  rechaza el archivo si el canal real detectado no coincide (`tipo:
  'canal_no_coincide'`), antes de mostrar la previsualización.
- Filas de Distribuidor pierden el "$" (`cajas()`/`valorMonetario()` en
  `cumplimiento.js`) — número entero + tag "cajas".

**Probado**: `php -l`/`node --check` limpios, CSS balanceado (798/798),
canal-filter verificado con datos reales de solo lectura (arriba). Sin
probar en navegador real.

## Cumplimiento de Cuota: bug real de canal — se derivaba del `pos_id`, no del usuario dueño (2026-08-31)

El usuario reportó, con datos reales: filtrando la pastilla en "Directo"
Javier Maldonado (Directo confirmado) no aparecía — todo caía en
"Distribuidor". Preguntó explícito: "¿quién definía que era directo o
distribuidor no era el usuario? por qué me lo pones en las Actas?" —
razonamiento correcto, y ya había un comentario en el propio código
(`condicionCanalCumplimiento()`) documentando el criterio correcto
("CEDI del Excel gana sobre el maestro") sin aplicarlo al canal.

**Causa real, confirmada leyendo el código**: `listar_cumplimiento_cuota()`/
`resumen_cumplimiento_cuota()` ya resolvían el DUEÑO de cada fila con el
criterio correcto (CEDI del Excel → usuario real, con fallback al maestro
solo si no matchea), pero el CANAL se seguía calculando aparte, mirando
directo si el `pos_id` de esa fila tenía alguna entrada `DISTRIBUIDOR` en
`repositorio_locales_supervisores_cliente` — el mismo maestro que ya se
sabe no refleja con quién trabaja Alicorp en la práctica (mismo fenómeno
ya documentado para Actas Asignadas: los clientes reales de Javier caen
como `MAYORISTA` en el maestro aunque él sea Directo de verdad).

**Corregido en `includes/functions.php`**: `condicionCanalCumplimiento()`
ahora recibe la expresión SQL del SUPERVISOR ya resuelto
(`COALESCE(u_cedi.supervisor, u_master.supervisor)`, mismos 2 `LEFT JOIN`
que ya arma `listar_cumplimiento_cuota()`) en vez del `pos_id` crudo, y
compara canal contra ESE supervisor — mismo criterio que ya usa
`canalDeSupervisor()` en el resto de la app. `resumen_cumplimiento_cuota()`
no tenía esos 2 `LEFT JOIN` para nada (operaba directo sobre la tabla sin
resolver dueño) — se le agregaron, mismos alias `u_cedi`/`mst`/`u_master`.

**Probado con datos reales de solo lectura**: las 15 filas de Javier
Maldonado (usuario real, confirmado por CEDI) daban `canal=distribuidor`
ANTES del fix — con el fix, las 15 dan `canal=directo`, filtro "Directo"
las trae las 15, filtro "Distribuidor" trae 0 — exacto lo que el usuario
esperaba. `php -l` limpio.

## Cumplimiento de Cuota: KPI con el mismo estilo de card que Historial (2026-08-31)

Pedido explícito: "usa el mismo estilo de card para los KPI de Historial
de Acuerdos, así como están bonitos allá". Las 3 tarjetas visibles
(Clientes evaluados / Ganan la categoría / No ganan) pasaron de
`.ac-stat-tile` (el tile plano lavanda de Liquidación) a `.ac-hist-stat`
(el de Historial: base blanca + borde fino + ícono en círculo, el color
de estado vive solo en el ícono) — reusado TAL CUAL, sin duplicar CSS.

- `components/cumplimiento/cumplimiento.php`: markup calcado del de
  Historial (`.ac-hist-stat > .ac-hist-stat-icon + .ac-hist-stat-body`),
  íconos `storefront`/`trending_up`/`trending_down`. La tarjeta oculta de
  "Cumplimiento promedio" se mantiene oculta con el mismo mecanismo
  (`display:none`, el JS le sigue escribiendo el valor sin romper nada).
- Nuevas 2 clases chicas en `style.css`: `.ac-hist-stat-bad` (rojo, mismos
  tokens que `.ac-badge-critico` — `--color-error-container`/
  `--color-on-error-container` — para "No ganan", el 3er color de estado
  que Historial no necesitaba) y `.ac-hist-stat-static` (saca
  `cursor:pointer`/hover/active — estas 3 tarjetas son informativas, no
  filtran nada al click, a diferencia de las de Historial).
- **Probado**: `php -l` limpio, CSS balanceado. Sin probar en navegador
  real.

## Actas Asignadas: bug real — "Guardar Borrador" intermedio rompía el marcado de "usada" (2026-08-31)

El usuario reportó un caso real: completó y generó el PDF de una Acta
Precargada (cliente asignado a Carlos Proaño), pero los campos del
formulario no se limpiaron y el cliente siguió apareciendo en "Actas
Asignadas". **Confirmado contra la base real, con datos concretos**: el
Acuerdo SÍ se generó bien (`repositorio_acuerdos` id=64, `ADN-2026-0058`,
`estado='generado'`, `creado_por=9` = Carlos Proaño) — pero las 4 filas de
`repositorio_cuota_cliente` de ese mismo cliente (`EPV13260`, Q2 2026)
seguían en `estado='pendiente_uso'`, `acuerdo_id_generado=NULL`, como si
nunca se hubiera consumido la precarga.

**Causa real**: `guardarAcuerdo()` (`assets/js/registrar.js`) limpiaba la
variable `origenPrecarga` apenas CUALQUIER guardado exitoso, incluido un
"Guardar Borrador" intermedio (el botón no pasa `onOk`, pero el `.then()`
de éxito corre igual y nuleaba `origenPrecarga` sin que nada lo
reconstruyera después). Si el asesor guarda como borrador antes de
terminar de completar Subcategoría/Marca (flujo real y común con varias
categorías por cliente) y recién más tarde, en la misma sesión, aprieta
"Generar PDF", ese guardado final ya mandaba `origen_precarga: null` —
`guardar_acuerdo.php` nunca llega al bloque "Consumir la Acta precargada
de origen" (`if ($origenPrecarga && ...)`), así que las filas de
`repositorio_cuota_cliente` quedan huérfanas en `pendiente_uso` para
siempre, aunque el Acuerdo real ya exista. Esto también explica por qué
los campos "no se limpiaron" desde la perspectiva del usuario: el cliente
sigue en Actas Asignadas, así que un click de nuevo ahí vuelve a
precargar los mismos datos en el formulario.

**Corregido**: `origenPrecarga` ya NO se limpia después de un "Guardar
Borrador" — solo se limpia cuando el guardado que se consolidó es el
final (`estado === 'generado'`), y ni siquiera hace falta ahí porque
`limpiarFormularioParaNuevoAcuerdo()` (llamada por el `onOk` de "Generar
PDF") ya lo deja en `null` como parte del reset completo para el próximo
Acuerdo — se dejó explícito de todos modos, por claridad. Un "Guardar
Borrador" intermedio ya no rompe el enlace con la precarga de origen.

**El caso ya roto (Carlos Proaño / `EPV13260`, Acuerdo id=64) sigue sin
corregirse en la base** — el fix de código previene que esto vuelva a
pasar, pero no repara datos ya huérfanos, y Claude no puede ejecutar el
`UPDATE` necesario (prohibido incluso bajo la excepción de este proyecto,
que solo cubre `CREATE`/`ALTER`). **Camino más simple para el usuario**:
en Repositorios > Cuotas Trimestrales, buscar esas 4 filas
(`EPV13260`/ROBERT - PONCE COMPANY) y usar el botón "Descartar" — las saca
de "Actas Asignadas" de inmediato (mismo filtro `estado='pendiente_uso'`
que usa la campanita). Alternativa más precisa si el usuario prefiere que
quede enlazada al Acuerdo real en vez de "descartada": correr él mismo
`UPDATE repositorio_cuota_cliente SET estado='usada', acuerdo_id_generado=64
WHERE pos_id='EPV13260' AND trimestre=2 AND anio=2026 AND estado='pendiente_uso';`
desde HeidiSQL.

**Probado**: `node --check` limpio en `registrar.js`. La causa se confirmó
con datos reales de solo lectura (el `id=64`/`ADN-2026-0058` real, las 4
filas huérfanas reales). **Todavía sin probar en navegador real** — falta
que el usuario repita el flujo completo (precarga → guardar borrador →
completar → generar PDF) con un cliente nuevo y confirme que esta vez sí
desaparece de Actas Asignadas y el formulario se limpia.

## Rebate % y Participación % — bloqueados SIEMPRE, sin excepción (2026-08-31)

Corrige una decisión de diseño anterior (2026-08-27/30, ver "Rebate %
conectado al repositorio" y "Participación de Percha — conectada al
repositorio" más arriba): cuando la búsqueda contra el repositorio no
encuentra match, el campo se dejaba **editable** ("no bloquear el flujo
de Registrar por falta de datos en un repositorio que se sigue poblando
de a poco"). **El usuario corrigió esto de raíz**: "no dejes campos
editables, rompe eso que me pidieron que esos campos deben estar
bloqueados" — el requisito real es que el asesor NUNCA pueda tipear un %
a mano en estos 2 campos, con match o sin él.

**Corregido en `assets/js/registrar.js`, 4 lugares** (los únicos 4 puntos
del archivo que ponían `readOnly = false`): `resetearRebate()`,
`resetearParticipacion()`, y la rama "sin match" de
`buscarYAplicarRebate()`/`buscarYAplicarParticipacion()` — las 4 ahora
dejan `readOnly = true` siempre. Sin match, el campo se queda en 0
(Rebate) / "0%" (Participación), bloqueado, con un `title` que explica
que falta el dato en el repositorio (ya no dice "escribilo a mano"). El
estilo visual de campo bloqueado (`.ac-rebate-input:read-only`,
`.v-participacion:read-only`, fondo apagado + `cursor:not-allowed`) ya
existía, no hizo falta CSS nuevo.

**Consecuencia práctica**: los 3 productos de Rebate y las 4 marcas de
Participación sin porcentaje cargado (ver auditorías completas más
arriba, "Auditoría completa de Rebate" y "Participación de Percha: qué
marcas van a autocompletar") quedan con el campo en 0% BLOQUEADO hasta
que JW complete esos datos en el repositorio — el asesor ya no tiene
forma de poner un valor a mano para esos casos, tiene que esperar a que
se cargue el dato real. Esto es a propósito, confirmado explícito por el
usuario — no es un caso a "arreglar" completando el catálogo desde acá,
es responsabilidad de JW.

**Probado**: `node --check` limpio. **Todavía sin probar en navegador
real** — falta confirmar visualmente que un producto sin match (ej.
`BARRA/LAVAVAJILLAS/EL ARRANCAGRASA`) queda con el Rebate% bloqueado en 0
en vez de editable.

## Decisiones del usuario sobre varios pendientes abiertos (2026-08-31)

El usuario repasó varios ítems de la lista de "Pendientes / decisiones
abiertas" (al inicio de este archivo) y contestó cada uno:

- **Paso 5 del proceso original ("envío de preliminar al área comercial
  para verificación")**: **cancelado, no se construye.** El usuario lo
  confirmó explícito ("eso no lo haremos, ya no va"). Queda fuera de
  alcance del proyecto — si en el futuro se pregunta por este paso del
  correo original, la respuesta es que se descartó a pedido del cliente,
  no que sigue pendiente.
- **Módulo Liquidación**: **se deja como está** ("liquidación se deja") —
  sigue oculto del sidebar (ver "⚠️ REPLANTEO 2026-08-23" y la nota de
  2026-08-25 que lo ocultó), sin más trabajo por ahora. No se retoma el
  análisis de si el mecanismo de subida+matching hace falta de verdad —
  esa pregunta queda parada, no resuelta ni descartada, simplemente sin
  prioridad.
- **Columna `CARTERA`** (cartera vencida, mencionada en las Condiciones
  del Acta): **JW la completa ellos mismos** ("el dato de cartera ellos
  lo ponen") — no es un dato que este sistema calcule, guarde ni
  necesite resolver. Cierra la pregunta abierta desde el diseño del
  export de Historial (la celda sigue en blanco/sin formato en el Excel,
  eso ya estaba bien — ver "Excel de Historial: filas TOTAL sin pintar..."
  más arriba).
- **"Avisar si el Rebate de una Acta no coincide con el Excel de JW"**: el
  usuario preguntó qué significaba este pendiente — explicado en el
  chat, resumen acá para que quede documentado. Contexto: el `rebate_pct`
  de una línea de Meta de Compras se CONGELA en el momento de generar el
  Acta (nunca se vuelve a consultar el repositorio después, ver
  "Excel de Historial... rebate congelado" más arriba) — si JW sube
  después un Excel de Rebate con un % distinto para ese mismo producto
  (Trade MKT revisa/actualiza), la Acta vieja se queda con el valor
  histórico, a propósito. La pregunta sin resolver es si el sistema
  debería, en algún reporte, avisar activamente cuando el % que quedó
  congelado en una Acta ya no coincide con el valor ACTUAL del
  repositorio (ej. "esta Acta se firmó con 2.5%, pero el repositorio hoy
  dice 4% para este producto — revisar si corresponde") — o si eso no
  aporta nada y alcanza con que cada Acta sea un acuerdo cerrado, sin
  comparar contra el presente. **Resuelto 2026-08-31: NO hace falta.** El
  usuario confirmó explícito que el comportamiento actual es el
  correcto, con su propio ejemplo de negocio: si un producto se negoció
  con cierto Rebate en Q1 y para Q2 el repositorio cambió ese %,
  obviamente el Q1 ya cerrado no debe verse afectado — eso es exactamente
  lo que ya hace el sistema (congelar al generar, nunca recomparar
  después), así que no hay nada para construir acá. Cierra
  definitivamente esta pregunta (antes el cliente había contestado "ni
  idea", ver "Módulo Liquidación", "Dos rebate distintos, no confundir").
- **Qué ve `superdesarrollador` en Historial**: el usuario confirmó "esto
  ya está bien como se ve ahora" — ya implementado (ver "Historial por
  Canal + Excel por formato + 'Ver todo' del superdesarrollador",
  2026-08-31, más arriba) y ya estaba marcado como resuelto en la lista
  de Pendientes. Sin cambios, solo confirmación.
- **Completar los catálogos de Rebate/Participación (3 productos/4
  marcas sin %)**: el usuario RECHAZÓ la solución descrita ("esos campos
  quedan editables a mano") — ver sección de arriba, "Rebate % y
  Participación % — bloqueados SIEMPRE, sin excepción", ya corregido.

## Excel de Distribuidor: hoja "VISIBILIDAD (2)" corregida a Cajas, sin "$" (2026-08-31)

Cerraba el pendiente "revisar si el formato 'money' de VISIBILIDAD (2)
(Distribuidor) es correcto" — confirmado que NO era correcto: las
columnas PAGO (Cabecera/Isla/Percha/Total) y PAGO (CAJAS) (las mismas 4
después de aplicar VALIDACIÓN) tenían formato `'money'` (signo `$`) en
`getters/exportar_cuota_categoria_distribuidor.php`, contradiciendo su
propio encabezado — la celda de grupo en fila 1 literalmente dice
**"PAGO (CAJAS)"**, prueba de que el signo `$` era un descuido, no una
decisión.

**Corregido**: las 8 llamadas a `celda()`/`formula()` de esas columnas
(`$vdPagoCab/Isla/Percha/Total`, `$vdFinCab/Isla/Percha/Total`) perdieron
el 5to parámetro `'money'` — `XlsxWriter` sin ese parámetro usa formato
general (número plano), igual que ya se corrigió para la pantalla
interactiva de Registrar el 2026-08-30 ("canal Distribuidor mide en
Cajas, no en Dólares"). CANTIDAD (Cabecera/Isla/Percha/Total) no se tocó
— ya era número plano, nunca tuvo el problema.

**Hallazgo aparte, NO corregido a propósito (fuera del pedido puntual)**:
la hoja "RESUMEN DE PAGOS" de este mismo archivo sigue con formato
`'money'` en su columna "VISIBILIDAD" — que ahora, después de este fix,
referencia (via `VLOOKUP`) un número que ya no es dinero, es una cuenta
de Cajas. Peor aún: esa misma hoja SUMA esa "VISIBILIDAD" (Cajas) con
"VOLUMEN" (que sí es dinero real, viene de `REBATE REAL VOL`) en una
columna "TOTAL" — mezclando 2 unidades distintas en una sola suma, algo
que ya era cuestionable ANTES de este fix y sigue siéndolo después. No se
tocó porque el pedido puntual era solo "VISIBILIDAD (2)" y arreglar esto
bien requiere una decisión de negocio (¿tiene sentido sumar $ + Cajas en
un "Total"? ¿debería la columna VISIBILIDAD de este resumen mostrarse
aparte, sin sumarse?) que no corresponde asumir. Queda como hallazgo para
la próxima vez que se toque "RESUMEN DE PAGOS" de Distribuidor.

**Probado**: `php -l` limpio. No se pudo generar el `.xlsx` real en esta
sesión (falta la extensión `zip` en el PHP CLI local, límite ya
documentado varias veces en este archivo). **Todavía sin probar en
navegador real.**

## Aclaración: `repositorio_portafolio_prioritario` NO es una tabla de este proyecto — no tocar (2026-08-31)

El usuario preguntó si esa tabla (mencionada en el pendiente "Portafolio
por distribuidor") la creamos nosotros. **Confirmado con `grep`: no
existe ningún `CREATE TABLE repositorio_portafolio_prioritario` ni
`lvi_portafolio_prioritario` en todo el proyecto** (ni en
`datos/*.sql`, ni en ningún getter — de hecho ningún getter la consulta
tampoco, solo se menciona en este CLAUDE.md como hallazgo de una
investigación). Es un maestro externo de Alicorp que ya existía en la
base (mismo tipo de tabla que `repositorio_locales_supervisores_cliente`/
`repositorio_productos`) — Claude la encontró en algún momento
revisando qué tablas había, nunca la creó ni la usó. **Confirmado con el
usuario: no tocarla** — sigue como "no usar hasta que alguien la llene",
sin ningún plan de construir sobre ella por ahora.

## Acta Precargada: seguía notificando después de generar (2026-09-02)

Reportado: se generó el Acta de una notificación de Carlos Proaño y siguió
apareciendo como pendiente. Investigado con datos reales, de solo lectura:

- El fix del 2026-08-31 (consumir `estado='usada'` al guardar) **sí
  funciona** — Acta `ADN-2026-0059` (hoy) marcó bien sus 4 filas.
- Bug real encontrado: la campanita nunca se refrescaba después de
  "Generar PDF" — seguía mostrando la lista vieja hasta el próximo cambio
  de módulo o el sondeo de 5 min. Agregado `window.acAlertasFirmaRefrescar()`
  al final del callback de `actaGenerarBtn` en `registrar.js`.
- Encontrado de paso: `ADN-2026-0058` (ROBERT - PONCE COMPANY, EPV13260,
  generada 2026-08-31 ANTES del fix) quedó con sus 4 filas de
  `repositorio_cuota_cliente` en `pendiente_uso` para siempre — dato viejo
  huérfano, no un bug nuevo. **Pendiente que el usuario corra** (Claude no
  puede, es `UPDATE`):
  ```sql
  UPDATE repositorio_cuota_cliente
  SET estado = 'usada', acuerdo_id_generado = 64
  WHERE pos_id = 'EPV13260' AND trimestre = 2 AND anio = 2026 AND estado = 'pendiente_uso';
  ```

Voseo corregido de paso en `registrar.js` (4 mensajes: "Podés", "completá"/
"generá" ×2, con guion largo sacado de uno de ellos).

**Probado**: `node --check` limpio. Sin probar en navegador real.

## Bug real: `pos_id` duplicado en el maestro fantaseaba filas en Cumplimiento/campanita (2026-09-02)

Reportado por el usuario: al resubir el Excel de Cumplimiento, ACOSTA
SANTAMARIA EDGAR PATRICIO aparecía con cada categoría duplicada (6 filas
en vez de 3) — NOVOA y SUPERALIANZA no. Confirmado con `SELECT` real: la
tabla `repositorio_cumplimiento_cuota` solo tenía 3 filas reales para
ACOSTA (nunca se duplicó al guardar) — el problema era 100% de lectura.
Causa real: `repositorio_locales_supervisores_cliente.pos_id` **no es
único** (ya documentado antes en el proyecto, "~1,116 pos_id duplicados")
— `EPVD15130`/ACOSTA tiene 2 filas reales en ese maestro (una
`supervisor=PATRICIO PASPUEZAN/canal=MAYORISTA`, otra `supervisor=GARRY
SAINT/canal=DISTRIBUIDOR`); NOVOA/SUPERALIANZA tienen 1 sola fila cada
uno, por eso no se veían afectados. Cualquier `JOIN` directo `ON
mst.pos_id = c.pos_id` (patrón "CEDI del Excel gana, maestro de respaldo"
usado en varios lados desde el 2026-08-28) multiplica cada fila real por
la cantidad de filas que ese `pos_id` tenga en el maestro.

**Corregido en las 5 consultas de `includes/functions.php` que tenían este
patrón** — se reemplazó `LEFT JOIN repositorio_locales_supervisores_cliente
m ON m.pos_id = c.pos_id` por `LEFT JOIN (SELECT pos_id, MIN(supervisor) AS
supervisor FROM repositorio_locales_supervisores_cliente GROUP BY pos_id) m
ON m.pos_id = c.pos_id` (fuerza máximo 1 fila por `pos_id` antes de unir,
`MIN(supervisor)` como desempate arbitrario pero determinístico — solo
importa cuando el CEDI del Excel no resuelve nada, que es el único caso
donde este fallback se usa de verdad):
- `listar_actas_precargadas_pendientes()` (campanita de Actas Asignadas —
  podía mostrar la MISMA Acta Precargada 2 veces).
- `resumen_cuotas()` — 2 ocurrencias en el `por_usuario` (ya estaban
  protegidas por `COUNT(DISTINCT ...)`, corregidas igual por consistencia,
  no porque estuvieran dando mal el número).
- `listar_cumplimiento_cuota()` — el bug reportado (filas duplicadas en la
  lista).
- `resumen_cumplimiento_cuota()` — mismo bug en los stat tiles
  ("15 categoría(s)" en vez de 12, "14 ganan" en vez de 11 — `COUNT(*)`/
  `SUM(...)` sin `DISTINCT` sí se inflaban de verdad con el fan-out, a
  diferencia del caso de arriba).

**Verificado en el entorno real de Azure (login real, Playwright), no solo
local** — el usuario pidió explícito "vos mismo tenés acceso, verificalo"
tras dos rondas de "sigo viendo 6". Con sesión real de Javier Maldonado:
el resumen ya dice "12 categoría(s)" / "11 ganan, 1 no ganan", y las 3
filas de ACOSTA SANTAMARIA (AEROSOL 455.56%, AEROSOL 116.67%, PASTAS
362.50%) aparecen una sola vez cada una. Confirma que el fix está
desplegado y sirviendo bien contra la base real — si el usuario seguía
viendo 6 después de esto, era caché del navegador, no el servidor.

**No tocado a propósito**: `obtener_acuerdo_detalle()` tiene el mismo
patrón (`JOIN repositorio_locales_supervisores_cliente d ON d.pos_id =
a.pos_id`) pero con un comentario ya existente reconociendo el caso
("LIMIT 1 alcanza pese a pos_id duplicados en el maestro (misma
pos_name/cedi)") y usado para UNA sola Acta a la vez (`fetch_assoc()`, no
una lista) — no genera duplicación visible, como mucho podría mostrar un
`canal`/`supervisor` no determinístico si esos campos se llegaran a leer
de ahí (no se leen hoy). Se deja como está, fuera de alcance de este bug
puntual — si se toca de nuevo, aplicar el mismo criterio de la subquery.

## Seguimiento de Equipo: número de Acta como hipervínculo de descarga (2026-09-02)

Pedido explícito: en el panel de detalle de Seguimiento de Equipo, que
"#ADN-2026-0047" sea un link que descargue el PDF real al hacer clic —
antes era un `<span>` puramente decorativo, sin acción.

`assets/js/seguimiento.js`, `renderDetalle()`: pasa a ser un `<a href="getters/generar_acta_pdf.php?id=..." download>` —
mismo endpoint que ya sirve el PDF real en toda la app (Historial,
Registrar), con el atributo `download` para que baje el archivo directo en
un solo clic, sin pasar por el modal de previsualización que sí tiene
sentido en Historial (ahí el asesor revisa/imprime su propia Acta; acá el
superdesarrollador solo quiere el archivo). Sin backend nuevo.

**Bug real encontrado armando esto**: `.ac-seg-detalle-fila > span:nth-child(1)`
(`style.css`) fijaba el ancho de la 1ra columna — al pasar esa columna de
`<span>` a `<a>`, el selector con `span:` ya no matcheaba nada ahí (el
`<a>` sigue siendo el primer hijo, pero no es un `span`), rompiendo el
layout de la fila. Corregido ampliando el selector a `:nth-child(1)` sin
filtro de tag.

**Probado con Playwright, sesión falsa contra la base real (mirror local,
solo lectura)**: el link real trae `href="getters/generar_acta_pdf.php?id=63"`,
atributo `download` presente, texto "#ADN-2026-0057" — y la fila se ve
igual que antes visualmente (columna alineada, sin romperse).
