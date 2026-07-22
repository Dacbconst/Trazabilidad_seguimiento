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
| `rol` | ENUM | `admin` / `desarrollador` / `superdesarrollador` |
| `status` | ENUM | `activo` / `inactivo` — así se maneja el "borrado", nunca DELETE físico |
| `created_at`, `updated_at` | DATETIME | Automáticos |

## Maestros externos de Alicorp (NO se duplican, se consultan directo)

### `repositorio_locales_dtt2`
Maestro real de PDV/local. **Ojo: el nombre real de la tabla es
`repositorio_locales_dtt2`, NO `repositorio_localesddt2`** (error de tipeo
detectado y corregido al implementar el formulario de Registrar Acuerdo PDV).
Columnas relevantes: `pos_id` (llave UNIQUE, un "Distribuidor" del formulario =
un `pos_id`, relación 1:1 confirmada), `pos_name` (= Razón Social /
"Estimado(a)" del Acta), `channel`, `subchannel`, `format`, `zone`, `region`,
`province`, `city` (= Localidad del Acta), `sales_executive`, `activar`
(`SI`/`NO` — el spinner de Distribuidor filtra `WHERE activar = 'SI'`).
1348 filas totales. Existe también `repositorio_locales_dtt` (sin el `2`,
estructura ligeramente distinta) — no usar esa, es otra tabla.

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

1. **El "Distribuidor" del dropdown = 1 `pos_id` exacto** de `repositorio_localesddt2`
   (confirmado con ejemplo real: "Tia - Centro" → `ALI01A0008` "TIA PORTOVIEJO II").
2. **El acuerdo (meta/cuota) se llena en el formulario primero.** Después de
   firmado, se sube venta real y visibilidad real para comparar contra esa meta.
   *(Pendiente de confirmar con Mishell/Jorge si la cuota también se conecta a
   un archivo de BI — por ahora se trata como INDEPENDIENTE del formulario.)*
3. **Las 4 tablas del Acta (Meta de Compras, Cabeceras, Rumas, Perchas) SIEMPRE
   van en el PDF**, sin excepción — no hay Actas parciales.
4. **El periodo del acuerdo debe ser de meses consecutivos** (Ene-Mar válido,
   Ene-Jul no). Validado con `CHECK (mes_fin >= mes_inicio)` + lógica de app.
5. **Las columnas de mes en cada tabla del formulario crecen/decrecen según el
   rango de meses elegido** — de 1 a 12 meses, sin alterar estructura de tabla
   (por eso `valores_mensuales` es JSON, no columnas ENE/FEB/MAR fijas).
6. **Todos los dropdowns de Segmento/Categoría/Marca son "spinners"** que
   consultan en vivo `repositorio_productos` (`SELECT DISTINCT ...`) — no hay
   catálogo propio, nunca hardcodear valores.
7. **Cascada de dropdowns:** al elegir Segmento aparecen las Categorías de ese
   segmento; al elegir Categoría aparecen las Marcas de esa categoría.
8. **`razon_social` y `localidad` nunca se guardan** — siempre se derivan de
   `repositorio_localesddt2.pos_name` / `province`/`city` vía el `pos_id` del
   acuerdo, en el momento de generar el PDF.
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
- **Sector (Meta de Compras)**: se agregó un 4to spinner encadenado
  Segmento→Categoría→Marca→**Sector** (`repositorio_productos.sector`, ej.
  `CREMA`/`BARRA`/`LIQUIDO` para distinguir "Crema Lavavajillas LAVA" de
  "Barra Lavavajillas LAVA") **solo en la tabla de Meta de Compras**, pedido
  explícito del usuario — Cabeceras/Rumas/Perchas no lo tienen y no deben
  tocarse. Igual que Participación de Perchas, **no hay columna en
  `repositorio_acuerdo_lineas` para Sector**, así que tampoco se envía al
  backend (`getters/guardar_acuerdo.php`) — es solo para que el vendedor arme
  el nombre completo del producto al llenar el Acta. Si la combinación
  Segmento+Categoría+Marca solo tiene un Sector posible en la base, se
  autocompleta solo (no tiene sentido obligar a elegir entre una opción).
- **`documento_no`**: se genera como `ADN-{anio}-{secuencia de 4 dígitos}`,
  calculado como `COUNT(*)+1` de acuerdos de ese año, con reintento si choca
  con el `UNIQUE` (nadie definió el algoritmo exacto, esto es una decisión
  razonable pero no confirmada con el cliente).
- **Generación real del PDF (`pdf_documento` LONGBLOB)**: NO implementada
  todavía. "Generar Acta" hoy solo arma una vista previa en HTML imprimible
  (`window.print()`), igual que hacía `code.html`. Falta decidir con qué
  librería se genera el PDF en servidor para guardarlo en la base (no hay
  ninguna instalada en el proyecto todavía).

## Pendientes / decisiones abiertas (no asumir, preguntar antes de implementar)

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
- [ ] Módulo de liquidación/seguimiento (venta real, visibilidad real,
      cumplimiento, "Resumen de Pagos") — identificado pero **NO construido
      aún en la base**. Se diseñó una propuesta (`cuotas_ventas_pos_producto`,
      `visibilidad_pos_mensual`, `liquidaciones`, `staging_filas`) pero se
      pausó antes de confirmar y crear. Retomar con el mismo nivel de detalle
      que se usó para `repositorio_acuerdo_lineas` antes de escribir el SQL.
- [ ] Si el presupuesto (visto en Excel real: PPTO 2026, CAJAS, Q1-Q4) se
      maneja por PDV individual o por distribuidor completo — afecta el diseño
      de la tabla de liquidación.
- [ ] Identificación de PDV al subir Excel: confirmar si las plantillas de
      carga van a incluir `pos_id` real o solo nombre (`CEDI`/`CLIENTE`), lo
      segundo es más propenso a duplicados por variación de escritura.

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
