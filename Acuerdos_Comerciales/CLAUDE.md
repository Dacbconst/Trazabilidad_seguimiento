# CLAUDE.md — Proyecto ADN (Acuerdo de Desarrollo de Negocios)

Contexto de negocio y técnico para trabajar en este proyecto. Cliente: Jabonería
Wilson S.A. (empresa de Alicorp). Sistema para digitalizar el proceso de Acuerdos
Comerciales (Acta de Compromiso) con distribuidores/PDV del canal directo.

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
| `valor_mensual_unico` | DECIMAL(10,2) | **solo ruma** | Un valor tipeado UNA vez (mini tabla "Valor Ruma x Marca x Mes") que se repite en TODOS los meses del periodo |
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

- **Rumas**: el mockup (`code.html`) tiene un input por mes igual que
  Cabeceras/Perchas, lo cual contradice la regla de `valor_mensual_unico`
  (un solo valor que se repite). La implementación real usa **un único input
  "Valor x Mes" por fila** — el "Pago Total" se calcula como
  `valor_mensual_unico * cantidad_de_meses`, tal como pide este documento.
  La tabla lateral "Valor Ruma x Marca x Mes" del mockup se mantiene como
  resumen de solo lectura (rollup por marca), no como campo editable aparte.
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
igual que en su plantilla actual. Solo Actas **canal Directa** (Distribuidor
tiene otro formato de columnas en el archivo real, "CUOTAS POR
CAT-DISTRIBUIDORES" — no construido, export aparte a futuro).

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

## Pendientes / decisiones abiertas (no asumir, preguntar antes de implementar)

- [ ] Si `superdesarrollador` debería ver TODOS los acuerdos en Historial (no
      solo los propios) o seguir la misma regla de `creado_por` que todos.
      Hoy sigue la misma regla que cualquier otro rol.
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
- [ ] Nombre exacto de la columna de rebate que se va a agregar a
      `repositorio_productos`.
- [ ] Si la cuota del Acta se conecta o no a un archivo/proceso de BI (Trade
      MKT). Respuesta actual del cliente: "no estoy seguro".
- [ ] Columna `CARTERA` (cartera vencida) mencionada en las Condiciones del
      Acta — detectada en el Excel real, todavía sin definir dónde se guarda.
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

## Módulo "Liquidación" (2026-08-17 — antes era el placeholder "Auditoría")

El ítem de sidebar que antes era `auditoria` (hoy "Próximamente") **se
renombró a "Liquidación"** — nunca fue un módulo de auditoría/log de
acciones, el nombre era solo un lugar reservado. Rename en código ya hecho
(2026-08-17): carpeta/archivo `components/liquidacion/liquidacion.php`,
`includes/secciones.php` con `id => 'liquidacion'`, ícono `payments`, y
`roles => ['superdesarrollador']` (ya no lo ve `desarrollador`). Sigue siendo
un placeholder de "Próximamente" — falta todo lo demás (pasos 2-4 abajo).

Qué hace este módulo — proceso de liquidación periódico (pasos 3-5 del
correo original, ver `datos/propuesta_digital_acuerdos_comerciales.md`;
frecuencia real no confirmada, ver "Decisiones confirmadas" abajo): compara
lo pactado en una Acta contra la venta/visibilidad real del período, calcula
el rebate realmente ganado y arma el "Resumen de Pagos" que hoy JW arma a
mano cruzando Excels.

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

### Conversación 2026-08-18 — de dónde sale la data que JW mete en su Excel (EN CURSO, sin código todavía)

Charla larga con el usuario, sin escribir código, tratando de entender bien
el flujo completo antes de construir el "Resumen de Pagos". Queda pendiente
seguir esta conversación en otra sesión — anotado acá para no perder el hilo.

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
   hecha** (ver arriba). **La pantalla Resumen de Pagos en sí todavía NO
   existe — es lo único grande que falta del alcance del correo.**
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

**Estrategia de matching (Excel → `pos_id` → `acuerdo_id`) — implementada en
`includes/liquidacion_import.php`, probada contra los dos Excel reales de
`datos/` (solo lectura, sin tocar nada):**

- El Excel NUNCA trae `pos_id` — solo nombre (truncado por ancho de columna)
  + CEDI (Directa) o DISTRIBUIDOR+CODIGO+RUC (Distribuidor). **`repositorio_locales_supervisores_cliente`
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
     - **Gráfico de barras horizontales apiladas** (SVG a mano, sin librería
       — mismo criterio que `xlsx_reader.php` de no sumar dependencias):
       top 10 clientes por total, Volumen + Visibilidad como 2 segmentos.
       Colores `#2a78d6`/`#eb6834` — par categórico tomado de
       `references/palette.md` de la skill `dataviz` y validado con su
       script (`validate_palette.js`, todos los checks PASS) antes de
       usarlo, en vez de elegir colores a ojo. Tooltip nativo (`<title>` de
       SVG) por segmento — se evaluó un tooltip HTML custom pero para 10
       barras en una pantalla interna no se justificaba el esfuerzo extra;
       el valor exacto siempre queda disponible en la tabla de abajo de
       todas formas.
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
