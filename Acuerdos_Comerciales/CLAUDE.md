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
      abajo para el análisis completo. Pendiente confirmar con JW.
- [ ] (Del mismo replanteo) repositorio de REBATE por segmento/categoría/marca
      y repositorio de PARTICIPACIÓN de percha, ambos para autocompletar y
      bloquear esos campos en el Acta — pedido real de Michelle en la
      reunión del 2026-08-18, no construido.
- [ ] (Ídem) repositorio de CUOTAS trimestrales que Michelle subiría para
      que Meta de Compras salga ya lleno/bloqueado al elegir cliente en
      Registrar Acuerdo PDV — pieza grande, no construida.
- [ ] (Ídem) revisar si el formato `'money'` de "VISIBILIDAD (2)"
      (Distribuidor) es correcto — la reunión confirma que Distribuidor se
      paga en CAJAS, no en dólares.
- [ ] Si `superdesarrollador` debería ver TODOS los acuerdos en Historial (no
      solo los propios) o seguir la misma regla de `creado_por` que todos.
      Hoy sigue la misma regla que cualquier otro rol. **Nota:** la reunión
      del 2026-08-18 sí lo confirma (Michelle ve todo, cada asesor solo lo
      suyo) — falta implementarlo si `superdesarrollador` = Michelle.
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
