# Documentación Técnica — Acuerdos Comerciales (ADN)

> Sistema de digitalización de Acuerdos Comerciales (Acta de Compromiso) entre
> Jabonería Wilson S.A. y sus distribuidores/PDV. Reemplaza el proceso manual
> en Excel/papel: registro del acuerdo, generación del PDF, seguimiento del
> ciclo de vida (firma física) y catálogos de referencia (rebate,
> participación de percha, cuotas trimestrales).
>
> Este documento describe el **estado actual** del sistema — arquitectura,
> base de datos, módulos y convenciones — para cualquier programador que se
> sume al proyecto. No es un historial de cambios (eso vive en `CLAUDE.md`,
> uso interno, no se distribuye con el repositorio).

Última verificación contra la base de datos real: 2026-08-27.

---

## 1. Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP puro (sin framework), `mysqli` con prepared statements |
| Frontend | JavaScript vanilla (ES5), sin build step, sin framework |
| Base de datos | MySQL/MariaDB (Azure Database for MySQL) |
| PDF | [Dompdf](https://github.com/dompdf/dompdf) vía Composer (`vendor/`) |
| Excel (leer/escribir) | Lectores/escritores **propios**, sin librería externa (ver §7) |
| Sesión | Sesiones nativas de PHP (`$_SESSION`) |
| Hosting | Compartido, deploy manual (FTP/Kudu). Existe un entorno de desarrollo en Azure App Service (ver §9) |

**No hay ORM.** No hay `FOREIGN KEY` en ninguna tabla — el usuario de base de
datos no tiene el privilegio `REFERENCES`. Toda integridad referencial se
valida en el código de la aplicación antes de cada `INSERT`/`UPDATE`.

`db_connect.php` fuerza `mysqli_report(MYSQLI_REPORT_OFF)` — desde PHP 8.1
`mysqli` lanza excepciones por default en errores de `prepare()`/`execute()`;
todo el código de este proyecto asume el comportamiento clásico
(`if (!$stmt)`, `$stmt->error`, `execute()` devolviendo `bool`), así que esto
es obligatorio, no opcional.

---

## 2. Estructura de carpetas

```
Acuerdos_Comerciales/
├── index.php                  Shell autenticado: header, sidebar, carga de secciones
├── login.php                  Formulario de login
├── config.php                 Credenciales de BD (HOST/USER/PASS/DB)
├── db_connect.php             Conexión mysqli compartida ($mysqli)
├── components/                Un subdirectorio por pantalla del sidebar
│   ├── registrar/registrar.php
│   ├── historial/historial.php
│   ├── repositorios/repositorios.php
│   ├── seguimiento/seguimiento.php
│   ├── liquidacion/liquidacion.php    (oculto del sidebar, ver §6.7)
│   └── gestion-usuarios/gestion-usuarios.php
├── includes/                  Lógica compartida (funciones de negocio, no HTTP)
│   ├── functions.php          Autenticación, listados, cálculos — el núcleo
│   ├── secciones.php          Definición del sidebar (id/label/ícono/roles)
│   ├── acta_pdf.php           Generación del HTML/PDF del Acta (Dompdf)
│   ├── liquidacion_import.php Parseo + matching del módulo Liquidación
│   ├── repositorio_import.php Parseo de Excel para Rebate/Participación/Cuotas
│   ├── xlsx_reader.php        Lector de .xlsx propio (ZipArchive + SimpleXML)
│   ├── xlsx_writer.php        Escritor de .xlsx propio (con fórmulas, colores, merges)
│   └── dinero.php             Suma de montos con BCMath (aritmética exacta)
├── getters/                   Endpoints HTTP (JSON o binario) — el "API" interno
├── assets/
│   ├── css/style.css          Un solo archivo, toda la app
│   └── js/                    Un archivo JS por módulo + utilidades compartidas
├── datos/                     Scripts .sql pendientes/ya corridos + archivos de referencia
└── vendor/                    Dompdf (Composer)
```

**No hay router.** `index.php` es el único punto de entrada autenticado:
carga `includes/secciones.php`, valida el rol contra `roles` de cada sección,
e incluye (`require`) el `.php` del componente activo. La navegación entre
módulos es 100% del lado del cliente (JS muestra/oculta secciones ya
renderizadas en el mismo HTML) — **todos los módulos se renderizan una sola
vez al cargar la página**, no hay recargas de sección. Esto es relevante
para cualquier script que dispare algo "al cargar" (ver `window.ac*Refrescar`
en §5).

**Patrón de cada getter**: `iniciar_sesion()` → `login_check()` +
`rolPermitido([...])` → lógica → responde JSON (`echo json_encode(...)`) o
un binario (PDF/XLSX/CSV) con el `Content-Type` correspondiente. Ningún
getter delega la autorización al frontend.

---

## 3. Autenticación y roles

- Login: `login.php` → `getters/procesar_acceso.php` → `login()` en
  `functions.php`. Contraseña en **texto plano** en
  `repositorio_usuarios_acuerdos.contrasena` (decisión explícita del
  cliente — los usuarios se crean a mano en HeidiSQL; no exponer esto a un
  login público sin migrar a hash primero).
- Roles reales (la app ya no ofrece `admin`, aunque el `ENUM` de la columna
  todavía lo permite a nivel de MySQL):
  - `desarrollador` → ve **Registrar Acuerdo PDV** e **Historial de Acuerdos**.
  - `superdesarrollador` → ve los 5 módulos activos (agrega **Repositorios**,
    **Seguimiento de Equipo** y **Gestión de Usuarios**; Liquidación existe
    pero está oculta, ver §6.7).
- `rolPermitido(array $roles)` (`functions.php`) es el único gate de
  autorización — se llama igual en cada componente y en cada getter.
- **Canal del usuario** (Directo vs. Distribuidor) nunca se guarda — se
  calcula en vivo con `canalDeSupervisor($mysqli, $supervisor)`, mirando qué
  `canal` tienen los clientes de `repositorio_usuarios_acuerdos.supervisor`
  en el maestro externo `repositorio_locales_supervisores_cliente`.
- **Protección de fuerza bruta (código escrito, `ALTER` pendiente)**:
  `login()` soporta bloqueo tras 5 intentos fallidos seguidos (15 min), pero
  las columnas `intentos_fallidos`/`bloqueado_hasta` **todavía no existen en
  producción** (verificado, no aparecen en la tabla real) — mientras no
  existan, `login()` cae a su fallback sin bloqueo (mismo patrón defensivo
  que el resto del proyecto para columnas nuevas). SQL pendiente:
  ```sql
  ALTER TABLE repositorio_usuarios_acuerdos
    ADD COLUMN intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN bloqueado_hasta DATETIME NULL;
  ```

---

## 4. Base de datos

### 4.1 Tablas propias del proyecto (prefijo `repositorio_` obligatorio)

**Verificado en producción (2026-08-27)** — la columna "Estado" refleja si
la tabla existe hoy en la base real, no el código.

| Tabla | Estado | Propósito |
|---|---|---|
| `repositorio_acuerdos` | ✅ 56 filas | Cabecera de cada Acta (1 por PDV/período) |
| `repositorio_acuerdo_lineas` | ✅ 285 filas | Las 4 tablas del Acta (meta_compra/cabecera/ruma/percha), unificadas por `tipo` |
| `repositorio_usuarios_acuerdos` | ✅ 4 filas | Login y roles |
| `repositorio_cuota_cliente` | ✅ 60 filas | Cuotas trimestrales subidas por JW (repositorio self-service) |
| `repositorio_liquidacion_importaciones` | ✅ 2 filas | Lotes de Excel subidos al módulo Liquidación |
| `repositorio_liquidacion_cuota_categoria` | ✅ 316 filas | Filas de cuota/venta/rebate del Excel de Liquidación |
| `repositorio_liquidacion_visibilidad` | ✅ 45 filas | Filas de Cabecera/Isla/Percha del Excel de Liquidación |
| `repositorio_rebate_producto` | ✅ 55 filas (`ALTER` corrido 2026-08-27) | Catálogo Rebate % por Ciudad/Canal/Categoría/Subcategoría/Marca — ver §6.3.1 |
| `repositorio_participacion_percha` | ❌ **NO existe** | Catálogo Participación % por Marca — ver §6.3.2 |

SQL pendiente de correr: solo el `CREATE TABLE` de Participación (Rebate ya
migrada — ver `datos/repositorios_schema.sql` para el `ALTER` ya corrido,
rediseño 2026-08-27 que reemplaza Segmento por Ciudad+Canal, §6.3.1).

#### `repositorio_acuerdos`

| Columna | Tipo | Nota |
|---|---|---|
| `id` | INT AI PK | |
| `documento_no` | VARCHAR(30) UNIQUE | `ADN-{año}-{secuencia 4 dígitos}` |
| `pos_id` | VARCHAR(200) | FK lógica a `repositorio_locales_supervisores_cliente.pos_id` |
| `anio`, `mes_inicio`, `mes_fin` | SMALLINT / TINYINT | Meses 0-11. Trimestre fijo (Q1-Q4) forzado por la UI, no por constraint |
| `fecha_generacion` | DATE | `NOW()` al generar, nunca tipeado |
| `estado` | ENUM | `borrador → generado → enviado → firmado → liquidado → anulado → vencido` |
| `sin_visibilidad` | TINYINT(1) | Switch "Visibilidad y Espacios" del formulario — oculta las tablas 2.a/2.b en el PDF |
| `creado_por` | INT UNSIGNED NULL | FK lógica a `repositorio_usuarios_acuerdos.id`. Base de "cada usuario ve solo lo suyo" en Historial |
| `acta_firmada_archivo` / `_mime` / `_subido_en` / `_subido_por` | LONGBLOB + auditoría | Foto/PDF del papel ya firmado |
| `pdf_documento` / `pdf_generado_en` / `pdf_tamano_bytes` | LONGBLOB + meta | Snapshot del PDF generado, servido directo desde la base |
| `created_at`, `updated_at` | DATETIME | Automáticos |

**La firma es siempre física.** No existe ningún campo de firma digital — el
PDF imprime líneas en blanco para firmar a mano; `estado='firmado'` se marca
manualmente al subir la foto del papel ya firmado.

#### `repositorio_acuerdo_lineas`

Las 4 tablas del Acta unificadas, diferenciadas por `tipo`:

| `tipo` | Campos propios | Captura mensual |
|---|---|---|
| `meta_compra` | `segmento`, `sector`, `categoria`, `marca`, `rebate_pct` | `valores_mensuales` (JSON, mes→monto) |
| `cabecera` | `segmento`, `categoria`, `marca` | `valores_mensuales` |
| `ruma` | `segmento`, `categoria`, `marca` | `valor_mensual_unico` (1 valor que se repite en todos los meses del período) |
| `percha` | `marca`, `cantidad_max_percha`, `precio_percha`, `participacion_pct` | `valores_mensuales` |

**Regla de oro: nunca se guardan totales calculados** ("Valor Estimado",
"Pago Total", filas de TOTALES). Todo se calcula al vuelo desde
`valores_mensuales`/`valor_mensual_unico` en cada consulta o al generar el PDF.

#### `repositorio_usuarios_acuerdos`

| Columna | Nota |
|---|---|
| `usuario` UNIQUE | |
| `contrasena` | Texto plano (ver §3) |
| `rol` | `desarrollador` / `superdesarrollador` (la app ya no usa `admin`) |
| `supervisor` | Vincula el login con `repositorio_locales_supervisores_cliente.supervisor` — de ahí se deriva el canal. 1 supervisor = 1 cuenta activa |
| `status` | `activo`/`inactivo` — así se "borra" un usuario, nunca `DELETE` |

#### `repositorio_cuota_cliente`

Repositorio self-service de cuotas trimestrales (Fase 1+2 de "Actas
precargadas", ver §6.3.3). Clave única `(pos_id, sector, trimestre, anio)`.

| Columna | Nota |
|---|---|
| `pos_id` NULL | NULL hasta que se resuelva el match cliente→PDV |
| `cliente_excel`, `cedi_excel`, `plan`, `sector` | Texto crudo del Excel subido |
| `valores_mensuales` | JSON, mismo formato que `repositorio_acuerdo_lineas` |
| `estado` | `pendiente_match` (no se resolvió el pos_id) → `pendiente_uso` (lista para precargar) → `usada` (ya generó una Acta real) / `descartada` (borrado lógico) |
| `acuerdo_id_generado` | Se llena cuando `estado` pasa a `usada` |

#### Tablas de Liquidación (`repositorio_liquidacion_*`)

Ver detalle completo del flujo en §6.7 — el módulo está funcional pero
**oculto del sidebar** desde 2026-08-25.

### 4.2 Maestros externos (solo lectura, no son de este proyecto)

| Tabla | Uso |
|---|---|
| `repositorio_locales_supervisores_cliente` | Maestro real de clientes/PDV de Alicorp (~41,640 filas). Columnas clave: `canal` (`DISTRIBUIDOR`/`COBERTURA`=Directo/`MAYORISTA`/`AUTOSERVICIO`), `supervisor`, `tipo_distribuidor`, `pos_id` (no único, ~1,116 duplicados), `pos_name`, `cedi`. Sin índices más que `id` — cualquier búsqueda por nombre es escaneo completo (decisión explícita del cliente: no tocar el esquema de esta tabla) |
| `repositorio_productos` | Maestro de producto/marca, **compartido entre todos los proyectos de la agencia** (1644 filas, solo 342 con `fabricante='JABONERIA WILSON'` — todo spinner de Segmento/Categoría/Marca debe filtrar por ese fabricante) |
| `repositorio_locales_dtt2` | **Deprecada**, no usar en código nuevo — reemplazada por `..._supervisores_cliente` desde 2026-07-26 |

### 4.3 Reglas de diseño de BD (aplican a toda tabla nueva)

1. Prefijo `repositorio_` obligatorio.
2. Nunca `FOREIGN KEY` — el usuario de BD no tiene `REFERENCES`. Usar índices
   normales + validar en código.
3. Nunca guardar columnas de total/suma — calcular siempre al vuelo.
4. Nunca crear catálogos propios de Segmento/Categoría/Marca/PDV — consultar
   los maestros externos en vivo.
5. Meses siempre `TINYINT` 0-11, nunca texto.
6. **Borrado lógico siempre, nunca `DELETE` físico**, en catálogos nuevos:
   `eliminado_en DATETIME NULL` (NULL=activo) + `eliminado_por INT UNSIGNED
   NULL`, además de `created_at`/`updated_at`/`actualizado_por`. El UPSERT de
   guardado debe limpiar esas 2 columnas al re-guardar (si no, una fila
   borrada y luego vuelta a subir queda "atascada" invisible). Excepción:
   `repositorio_acuerdos` usa su propio `estado` ENUM (`anulado`/`vencido`)
   en vez de estas 2 columnas — llegó primero, mismo principio distinto
   mecanismo.

---

## 5. Frontend — convenciones compartidas

- **Sin build step.** JS ES5 plano, un archivo por módulo en `assets/js/`,
  cargados directo en `index.php`.
- **`window.ac*Refrescar`**: como todos los módulos se renderizan una sola
  vez, cualquier lógica que deba dispararse "al entrar a la pestaña X" (no
  solo al cargar la página) se expone como `window.acHistorialRefrescar`,
  `window.acLiquidacionRefrescar`, `window.acUsuariosRefrescar`, etc., y
  `index.php` la invoca en el listener de click del sidebar.
- **`select-bonito.js`**: reemplazo de `<select>` nativo — el dropdown
  nativo abierto en mobile es UI del sistema operativo, no se puede
  restylear. Cualquier `<select>` con la clase `ac-select-bonito-auto` se
  auto-mejora: se envuelve en un trigger + panel propio
  (`.ac-combo-panel`/`.ac-combo-option`, mismo componente que los combos de
  Registrar), pero el `<select>` original sigue siendo la única fuente de
  verdad (dispara `change` real, `FormData` lo lee normal).
- **`toast.js`** (`window.mostrarToast()`) — notificación global, único
  mecanismo de mensajes efímeros de toda la app.
- **`cargando.js`** — `acBotonCargando(btn, bool)` (spinner en un botón) y
  `acMostrarCargando()`/`acOcultarCargando()` (overlay sobre un `.ac-card`).
- **`lightbox.js`** — `window.acAbrirLightbox(src)`, overlay global para ver
  imágenes ampliadas (fotos de Acta firmada).
- **`alertas-firma.js`** — campanita del header, sondeo cada 5 min contra
  `getters/alertas_firma.php`. Ver §6.6.
- **Confirmaciones destructivas** usan SweetAlert2 (CDN); mensajes
  informativos usan `toast.js` — no mezclar los dos componentes.
- **Responsive**: breakpoint compartido en 900px para el shell (drawer
  off-canvas), 700px/600px para tablas→tarjetas por módulo. Cualquier
  contenedor flex/grid/tabla necesita `min-width:0` explícito para poder
  encogerse — el navegador nunca lo hace solo (bug recurrente en este
  proyecto, ya corregido en varios lugares).
- **Elemento oculto con el atributo `hidden`**: si ese mismo elemento tiene
  una clase con `display` propio (`display:flex`, etc.), hace falta la
  regla `.clase[hidden] { display: none; }` explícita al lado — una regla de
  autor con la misma especificidad siempre le gana a la hoja de estilos del
  navegador. Bug recurrente en este proyecto (4 casos ya corregidos:
  `.ac-alertas-badge`, `.ac-stat-tile`, `.ac-hist-banner`, etc.) — repasarlo
  ante cualquier `hidden` nuevo.

---

## 6. Módulos

### 6.1 Registrar Acuerdo PDV

**Roles**: `desarrollador`, `superdesarrollador`. Archivos:
`components/registrar/registrar.php`, `assets/js/registrar.js`.

Formulario para armar un Acuerdo: cabecera (Distribuidor/Local, Empresa si
el canal es Distribuidor, Período por trimestre fijo) + 4 tablas editables
(Meta de Compras, Cabeceras, Rumas, Perchas).

**Reglas clave**:
- Distribuidor/Local = 1 `pos_id` real, cada usuario ve solo los clientes de
  su `supervisor`. Canal Distribuidor elige primero Empresa (agrupador), Directo no.
- Cascada de combos: Segmento → Sector → Categoría → Marca (solo Meta de
  Compras; las otras 3 tablas no tienen nivel Sector). Combos son
  `readonly` de verdad (no se puede tipear texto libre) pero sí filtrables
  por búsqueda al abrir el panel — mismo componente que `select-bonito.js`.
- **Catálogo restringido a 9 combos reales de JW (2026-08-27)**: aunque
  `repositorio_productos` (maestro compartido de la agencia) tiene 18
  combinaciones Sector+Categoría para `fabricante='JABONERIA WILSON'`, JW
  solo trabaja 9 dentro de Acuerdos Comerciales (confirmado cruzando 3
  fuentes reales: el Excel de Rebate, y las 2 hojas "CUOTA
  CLIENTE-CATEGORÍA" de Liquidación Directa/Distribuidor — ninguna
  menciona PASTAS/SALSAS/AEROSOL/OTROS). `getters/acuerdo_catalogo.php`
  filtra las 3 queries (`segmentos_sector`, `segmentos`, `marcas_percha`)
  con un `$combosValidos` fijo en código — aplica a las 4 tablas del Acta,
  no solo a Meta de Compras. Si JW agrega una línea nueva a este módulo
  más adelante, el único punto a tocar es ese array.
- Período: trimestre fijo Q1-Q4 (`#ac-periodo-select`), no rango libre de meses.
- Switch "2. Visibilidad y Espacios": si se desactiva, se manda
  `sin_visibilidad=true` y el backend fuerza vacías las 3 tablas de
  visibilidad sin importar qué haya en el formulario.
- Solo una Acta activa (`estado NOT IN ('borrador','anulado')`) por
  PDV+período — los borradores no cuentan, "el primero que la genera, gana".
- Flujo de guardado: "Previsualización" no toca la base (arma el PDF con
  `getters/previsualizar_acta_pdf.php`); "Generar PDF" recién ahí llama a
  `getters/guardar_acuerdo.php` con `estado='generado'`.
- **Actas precargadas** (repositorio de Cuotas, ver §6.3.3): al hacer click
  en un item de la campanita, `window.acRegistrarCargarPrecarga(posId,
  trimestre, anio)` carga Meta de Compras bloqueada + sugiere filas en las
  otras 3 tablas.

Getters propios: `acuerdo_catalogo.php`, `acuerdo_distribuidores.php`,
`guardar_acuerdo.php`, `previsualizar_acta_pdf.php`, `obtener_acta_precargada.php`.

### 6.2 Historial de Acuerdos

**Roles**: `desarrollador`, `superdesarrollador`. Archivos:
`components/historial/historial.php`, `assets/js/historial.js`.

Ciclo de vida de las Actas ya generadas: ver/descargar PDF, subir la foto del
Acta firmada, eliminar (soft-delete, `estado='anulado'`).

- Cada `desarrollador` ve solo sus propias Actas (`creado_por`);
  `superdesarrollador` sigue la misma regla hoy (no hay vista "ver todo" —
  pendiente de confirmar si debería tenerla, ver §10).
- Filtros: texto (Distribuidor) + Período (Q1-Q4/Todos) + Año.
- **Vencimiento de firma**: barrido lazy (`barrer_actas_vencidas()`, sin
  cron) marca `estado='vencido'` a los 20 días de `fecha_generacion` sin
  firmar — desaparece de Historial igual que `anulado`. Banner + badges de
  urgencia (2-5 días naranja, 0-1 día rojo) en la fila y en la tabla.
- Subida de Acta firmada: modal de 2 paneles (Acta Generada vs. Acta
  Firmada), valida el mime real con `finfo` (no la extensión ni el
  `Content-Type` del navegador), reemplaza cualquier subida anterior.
- Export "Descargar Excel" (`exportar_cuota_categoria.php` /
  `_distribuidor.php`) — `.xlsx` real con fórmulas vivas, ver §7.

Getters propios: `listar_historial.php`, `listar_borradores.php`,
`obtener_borrador.php`, `eliminar_acuerdo.php`, `generar_acta_pdf.php`,
`subir_acta_firmada.php`, `descargar_acta_firmada.php`,
`exportar_cuota_categoria*.php`.

### 6.3 Repositorios

**Roles**: `superdesarrollador`. Archivos:
`components/repositorios/repositorios.php`, `assets/js/repositorios.js`.

3 catálogos self-service, cada uno con su propio flujo de subida en 2 pasos
(previsualizar → corregir en los inputs → guardar) — nunca se valida
visualmente dentro de la tabla (celdas simples, sin bordes rojos).

#### 6.3.1 Rebate por Ciudad/Canal/Categoría/Subcategoría/Marca

✅ **Rediseñada y en producción (2026-08-27, `ALTER` ya corrido, 55 filas
reales cargadas de `datos/RABATE.xlsx`)** — el diseño original (columna
`segmento`) fue una suposición sin confirmar, nunca tuvo filas reales. El
Excel real que sube JW no tiene Segmento, pero SÍ tiene **Ciudad** y
**Canal** — y ambos cambian el % de Rebate del mismo producto (confirmado
con datos reales: hasta 5 valores distintos por Sector+Categoría+Marca).
Clave única real: `(ciudad, canal, sector, categoria, marca)`.

**Etiquetas visibles en pantalla ≠ nombre de columna interno** (mismo
patrón que Meta de Compras, ver §6.1): la columna `sector` se muestra como
**"Categoría"**, la columna `categoria` se muestra como **"Subcategoría"**
— así coincide con el vocabulario real del Excel de JW (su "CATEGORIA" =
nuestro Sector, su "SUBCATEGORIA" = nuestra Categoría). Ver
`CONFIG.rebate` en `assets/js/repositorios.js`.

**Conectado a Registrar Acuerdo PDV** (`getters/acuerdo_buscar_rebate.php`
→ `buscarRebateProducto()` en `includes/functions.php`): al completar
Sector+Categoría+Marca en una fila de Meta de Compras, busca por
Ciudad+Canal+Sector+Categoría+Marca — si hay match, `rebate_pct` se
autocompleta y se bloquea (`readonly`); si no, el campo queda editable.
Canal se deriva del canal real del cliente (`DISTRIBUIDOR`/`DIRECTA`,
mismo criterio que `es_distribuidor`); Ciudad se deriva de la Localidad
(CEDI) del cliente elegido para canal Directo, o el literal `"TODAS"` para
canal Distribuidor (el repositorio nunca varía por ciudad en ese canal).
El match no es solo exacto — el texto de Sector/Categoría que arma el
cascade real (`repositorio_productos`) no siempre coincide letra por letra
con el Excel de JW (ej. "LIQUIDOS" vs "LIQUIDO"), así que
`buscarRebateProducto()` también prueba variantes de plural/singular y,
como último recurso, Ciudad+Canal+Sector+Marca ignorando Categoría (solo
si da una única fila). Ver
`bindCascadaComboConSector()`/`buscarYAplicarRebate()` en
`assets/js/registrar.js`.

#### 6.3.2 Participación de Percha por Marca

⚠️ **Tabla `repositorio_participacion_percha` no existe en producción** —
la pestaña funciona en el código pero no tiene dónde guardar. Ver SQL
pendiente en §4.1. Integración con Registrar (autocompletar/bloquear
`participacion_pct`) no construida — mismo patrón que Rebate (§6.3.1),
pendiente de que exista la tabla.

#### 6.3.3 Cuotas Trimestrales ("Actas precargadas")

La pieza más grande del módulo. JW sube un Excel trimestral (`CEDI, CLIENTE,
PLAN, CATEGORIAS, <mes1>, <mes2>, <mes3>`) → el sistema resuelve
`pos_id`/Sector, guarda en `repositorio_cuota_cliente`, y arma "Actas
precargadas" que el ejecutivo dueño de ese cliente completa desde Registrar
(Meta de Compras ya bloqueada, otras 3 tablas con sugerencia).

- `resolverPosIdCliente()` (match nombre + desempate por CEDI=supervisor) y
  `resolverSectorReal()` (normaliza "POLVO DETERGENTE" del Excel → Sector
  real "POLVO" + Subcategoría, contra el catálogo real de
  `repositorio_productos`) en `functions.php`.
- Chequeo de estado ANTES de guardar
  (`getters/cuotas_verificar_estado.php`, solo lectura) — la previsualización
  ya muestra una columna "Al guardar" (Nuevo/Actualiza/Ya usada/Cliente sin
  identificar) por fila, antes de confirmar.
- Cola de pendientes de asignar manualmente (`cuotas_pendientes_asignar.php`
  / `cuotas_resolver_match.php`) cuando el nombre no resuelve a un único
  `pos_id` — el botón que abre esta cola está **oculto de la UI** desde
  2026-08-26 (pedido explícito, "quítalo, lo veo innecesario"); el
  mecanismo (getters + modal) sigue intacto, solo no hay forma de abrirlo
  desde pantalla por ahora.
- Borrado lógico (`estado='descartada'`) para `pendiente_match`/
  `pendiente_uso`; `usada` no se puede tocar (ya generó una Acta real) —
  `cuotas_reactivar.php` revierte un descarte.
- Panel "Resumen" (`cuotas_resumen.php`): stat tiles + lista de "a quién le
  corresponden" las Actas pendientes, agrupada en 2 secciones — usuarios
  con cuenta activa (barra azul primaria) y supervisores del maestro que
  todavía no tienen cuenta creada en Gestión de Usuarios (barra gris,
  marca pasiva "sin cuenta", nota explicando que se resuelve solo al
  crearles la cuenta) — reemplaza un tile suelto de conteo ("Sin usuario
  asignado") que no decía a quién correspondía.
- **Notificación**: la campanita del header (§6.6) tiene una pestaña "Actas
  Asignadas" — click ahí va directo a Registrar con esa Acta cargada.

Getters: `cuotas_previsualizar_excel.php`, `cuotas_guardar.php`,
`cuotas_verificar_estado.php`, `cuotas_pendientes_asignar.php`,
`cuotas_resolver_match.php`, `cuotas_reactivar.php`, `cuotas_resumen.php`.

### 6.4 Seguimiento de Equipo

**Roles**: `superdesarrollador`. Archivos:
`components/seguimiento/seguimiento.php`, `assets/js/seguimiento.js`.

Única pantalla del proyecto que muestra Actas de **todos** los usuarios, no
solo las propias (resuelve para `superdesarrollador` lo que Historial nunca
hizo — ver §6.2) — vista maestro-detalle: lista de "Equipo" (un usuario por
fila, con cuántas Actas generó y su estado de firma) + panel de detalle con
las Actas puntuales de quien se selecciona. Filtro único de 4 estados
(Todas/Firmadas/Pendientes/Vencidas) controla lista y detalle a la vez, más
pills de trimestre + `<select>` de año.

- **Arquitectura distinta al resto del proyecto, a propósito**: los 2
  getters devuelven JSON crudo (no HTML pre-armado como
  `renderFilaHistorial()` de Historial) — `seguimiento.js` arma todo el DOM
  en cliente, para que cambiar de filtro/buscar se sienta instantáneo, sin
  ida y vuelta al servidor por cada click.
- `getters/seguimiento_resumen.php` → `resumen_seguimiento_equipo($mysqli,
  $trimestre, $anio)` — una sola query con `JOIN` (no `LEFT JOIN`) a
  `repositorio_usuarios_acuerdos`, `GROUP BY` usuario: trae
  total/firmadas/pendientes/vencidas/días-a-la-más-próxima por usuario en
  una pasada. Solo incluye usuarios con al menos 1 Acta real en el
  período — no hay fila "0 Actas" para alguien sin actividad (evita
  inventar una regla de "quién es del equipo comercial": los roles reales
  no distinguen limpio cuenta admin de vendedor).
- `getters/seguimiento_actas_usuario.php` → `listar_actas_equipo_usuario($mysqli,
  $usuarioId, $trimestre, $anio, $tipo)` — Actas puntuales de un usuario
  para el filtro activo (`todas`/`firmadas`/`pendientes`/`vencidas`).
- Mismos umbrales de urgencia (20 días para vencer, ≤5 "urgente", ≤1
  "crítico") que la campanita de alertas (§6.6) y el badge de Historial
  (§6.2) — reimplementados en `seguimiento.js` porque acá el render es
  100% cliente.

### 6.5 Gestión de Usuarios

**Roles**: `superdesarrollador`. Archivos:
`components/gestion-usuarios/gestion-usuarios.php`,
`assets/js/gestion-usuarios.js`.

CRUD de `repositorio_usuarios_acuerdos` — crear/editar usuario, asignar
rol + supervisor (1 supervisor = 1 cuenta activa, el combo oculta los ya
tomados), activar/desactivar (soft-delete vía `status`).

Getters: `tabla_usuarios.php`, `crear_usuario.php`, `actualizar_usuario.php`,
`supervisores_disponibles.php`.

### 6.6 Sistema de alertas (campanita, transversal)

**Roles**: cualquier usuario logueado. Archivos: `assets/js/alertas-firma.js`
+ `getters/alertas_firma.php`, widget en el header de `index.php`.

Sondeo cada 5 minutos, 2 categorías (JSON `{mias, precargadas}`):
- **"Actas Por Firmar"**: Actas propias `generado`/`enviado` a 5 días o
  menos de vencer (ver §6.2) — `listar_alertas_firma_propias()`.
- **"Actas Asignadas"**: Cuotas precargadas pendientes de completar, del
  cliente asignado a este usuario — `listar_actas_precargadas_pendientes()`.

El badge suma ambas categorías; el pulso/animación crítica se activa si hay
algo a 0-1 día de vencer o cualquier precarga pendiente. Diseño de panel
(tabs + feed) inspirado en `diseños ideas/code.html` (mockup de referencia,
no forma parte del runtime de la app).

### 6.7 Liquidación (oculto del sidebar)

**Roles**: `superdesarrollador` (código funcional, pero **la entrada del
sidebar está comentada** en `includes/secciones.php` desde 2026-08-25 —
duda de negocio sin resolver sobre si el mecanismo de subir Excel +
matching automático es lo que JW realmente pidió, ver comentario en ese
mismo archivo). Datos y tablas siguen intactos.

Cuando esté activo: compara lo pactado en el Acta contra venta/visibilidad
real subida por JW en Excel, calcula rebate ganado, arma "Resumen de Pagos".

- Excel → `pos_id`: match por `pos_name` (prefijo, por texto truncado en el
  Excel), CEDI/supervisor solo como desempate (el supervisor de un cliente
  cambia con el tiempo, el nombre es más estable).
- `pos_id` → `acuerdo_id`: por solape de `mes_inicio`/`mes_fin` + `anio`
  exacto. Si un cliente tiene 2+ Actas del mismo período (pasa en
  producción real), la pantalla de "Pendientes de Asignar" deja elegir cuál.
- Estado `sin_acta`: para liquidaciones históricas que nunca van a tener
  Acta digital — estado final, no cuenta como pendiente.
- "Resumen de Pagos" es **unificado por canal** (no por importación
  puntual) — nunca suma montos de trimestres distintos en una sola fila
  (los pagos se liquidan por trimestre).
- Lector/escritor de Excel propios (`includes/xlsx_reader.php`), dinero con
  BCMath (`includes/dinero.php`).

Getters: `importar_liquidacion.php`, `listar_liquidacion_importaciones.php`,
`liquidacion_pendientes.php`, `liquidacion_buscar_pos.php`,
`liquidacion_resolver_match.php`, `liquidacion_resumen_pagos.php`,
`liquidacion_resumen_pagos_export.php`.

---

## 7. Excel — lectores/escritores propios

Sin PhpSpreadsheet ni ninguna librería externa (Composer no está disponible
en la máquina de desarrollo original; complicaría el deploy manual por FTP).
Un `.xlsx` es un ZIP con XML — se usa `ZipArchive` + `SimpleXML`.

- **`includes/xlsx_reader.php`** — lee hojas por nombre (tolera Target
  absoluto o relativo en `.rels`, celdas `inlineStr` y `sharedStrings`),
  detecta columnas de mes por nombre (no por posición fija).
- **`includes/xlsx_writer.php`** — escribe celdas de texto/número/fórmula,
  negrita, formato moneda/porcentaje, merges, colores, bordes (borde fino
  aplicado por default a toda celda que pasa por `estiloId()`), ancho de
  columna autofit real (calculado del contenido, con clamp 8-45), soporte
  de fórmulas en inglés con coma (`IF`, `SUM`, `VLOOKUP`, nunca `SI`/`SUMA`
  en español) — **gotcha real de OOXML**: `CONCAT()` necesita el prefijo
  `_xlfn.CONCAT(...)` en el XML crudo o Excel tira `#NAME?`.
- Usado por: exports de Historial (`exportar_cuota_categoria*.php`),
  export de Repositorios (`.xlsx`/CSV), y el lector de Liquidación/Repositorios.

---

## 8. Generación de PDF (Acta)

`includes/acta_pdf.php` (`generar_acta_html()`) arma el HTML, Dompdf lo
renderiza. Auto-ajuste a 1 sola hoja: prueba escala 1.0 y va reduciendo
fuentes/espaciados si Dompdf reporta más de 1 página.

4 combinaciones de formato, controladas por 2 flags independientes:

| | `es_distribuidor=false` (Directo) | `es_distribuidor=true` (Distribuidor) |
|---|---|---|
| `sin_visibilidad=false` | 4 tablas, $ | Solo Meta de Compras, cajas, 3 firmas si aplica |
| `sin_visibilidad=true` | Solo Meta de Compras, $ | Solo Meta de Compras, cajas |

- `es_distribuidor` se deriva del `canal` real del cliente en el acuerdo
  (nunca del canal del supervisor de sesión, que puede cambiar).
- `sin_visibilidad` es el switch del formulario — independiente del canal.
- Distribuidor mide en **cajas**, no dólares (`numero()` vs `moneda()`), y
  su fórmula de "Cajas Estimadas a Ganar" es `Total × Rebate%` (Directo es
  `Total × (1 + Rebate%)`).
- El PDF se guarda como snapshot (`pdf_documento` LONGBLOB) al pasar a
  `estado='generado'` — se sirve ese snapshot, nunca se regenera en cada
  descarga (salvo fallback para Actas viejas sin snapshot).

---

## 9. Entornos

- **Producción / desarrollo**: mismo hosting compartido con deploy manual
  (FTP/Kudu). Hay un entorno de desarrollo real en
  `https://webecuador-desarrollo.azurewebsites.net/App/XploraEcuador/Acuerdos_Comerciales/`
  (no es producción del cliente).
- **Dato de infraestructura**: en ese entorno, cambios a `assets/css/*.css`
  se reflejan casi al instante; cambios a `.php` tardan más (probable
  opcache sin revalidación inmediata) — si un CSS se ve pero un PHP no,
  esperar, no asumir que el archivo está mal.
- **Local**: `php -S localhost:8899` desde la carpeta del proyecto +
  `Acuerdos_Comerciales/config.php` apunta siempre a la base real de Azure —
  no hay base de desarrollo separada. `php.exe` en Windows normalmente vive
  en `C:\xampp\php\php.exe`, no está en el `PATH`.

---

## 10. Deuda técnica / pendientes conocidos

- `repositorio_participacion_percha` no existe en producción todavía
  (`repositorio_rebate_producto` ya se creó el 2026-08-27) — falta definir
  su estructura y correr el `CREATE TABLE`.
- Protección de fuerza bruta en login escrita pero sin las 2 columnas en
  producción — correr el `ALTER` de §3.
- Módulo Liquidación funcional pero oculto — pendiente confirmar con JW si
  el mecanismo de subir Excel es realmente lo que pidieron, o si el ciclo
  normal se resuelve solo con el export "Descargar Excel" de Historial.
- Integración Participación → Registrar (autocompletar y bloquear
  `participacion_pct`) no construida — mismo patrón ya hecho para Rebate
  (§6.3.1), pendiente de que exista la tabla.
- `desarrollador` sigue viendo solo sus propias Actas en Historial (por
  diseño) — `superdesarrollador` ya tiene visibilidad de todo el equipo,
  pero en una pantalla dedicada (**Seguimiento de Equipo**, §6.4), no
  dentro de Historial mismo.
- El plan original de agregar una columna de rebate a `repositorio_productos`
  quedó obsoleto — se resolvió con un repositorio propio
  (`repositorio_rebate_producto`, self-service, §6.3.1) en vez de tocar el
  catálogo compartido de la agencia.
- Columna `CARTERA` (cartera vencida) mencionada en las Condiciones del
  Acta, sin definir de dónde sale el dato real.
- `_dev_panel_pruebas.php` / `getters/_dev_simular_vencimiento.php` —
  panel temporal para simular vencimiento de firma sin esperar 20 días
  reales. Borrar cuando ya no haga falta probar eso.
