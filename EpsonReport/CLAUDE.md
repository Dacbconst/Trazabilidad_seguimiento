# CLAUDE.md — EpsonReport (Trazabilidad y Seguimiento de Trabajo de Campo)

Guía técnica, de diseño y arquitectura para el desarrollo y mantenimiento del proyecto **EpsonReport**.

---

## 1. Propósito General del Producto

**EpsonReport** es la plataforma corporativa desarrollada para la agencia **Lucky** con el fin de digitalizar, estructurar y auditar ante **Epson** todo el trabajo operativo realizado por los equipos de campo (promotores, capacitadores y mercaderistas) en los diferentes canales y puntos de venta (retail y mayoristas) a nivel nacional en Ecuador.

### Objetivos Clave:
1. **Eliminar el reporte manual en papel/Excel no consolidado**: Capturar directamente desde el punto de venta los datos cuantitativos y cualitativos de cada actividad.
2. **Trazabilidad y justificación ante la marca Epson**: Proporcionar evidencia fotográfica verificable, cálculo automático de KPIs y geolocalización o identificación precisa de tiendas y promotores.
3. **Flexibilidad taxonómica (El Constructor)**: Permitir a los administradores crear nuevos tipos de actividades en cualquier momento, reutilizando la lógica, formularios, cálculos matemáticos y requerimientos fotográficos de actividades base existentes, sin necesidad de tocar código.
4. **Auditoría ejecutiva para supervisores y admins**: Ofrecer una vista consolidada en tiempo real de todos los reportes generados en campo, organizados cronológicamente por día, con filtros por actividad, fecha, tienda y promotor.

---

## 2. Usuarios y Roles del Sistema

El sistema implementa dos roles principales definidos en sesión (`$_SESSION['rol']`):

- **Promotores / Mercaderistas en Campo (`usuario`)**:
  - Acceden primordialmente desde dispositivos móviles (diseño *mobile-first*).
  - Seleccionan la actividad correspondiente y completan el formulario guiado paso a paso con riel numerado.
  - Suben evidencia fotográfica obligatoria mediante el asistente guiado o slots directos con Drag & Drop.
  - Visualizan las estadísticas de su gestión calculadas en tiempo real.
- **Supervisores / Administradores (`admin`)**:
  - Cuentan con todas las facultades del promotor.
  - Tienen acceso exclusivo al panel del **Constructor de Actividades** (`#ep-panel-constructor`), donde pueden crear nuevos botones de actividad, definir su lógica a replicar, y activar/desactivar o eliminar actividades.
  - Monitorean y auditan el **Módulo de Registros de Actividades** (`index.php?vista=historial`), filtrando por promotores, estados de validación y KPIs de cumplimiento.
  - Generan y previsualizan reportes en PowerPoint (`.pptx`) con 7 plantillas oficiales.

---

## 3. Arquitectura Técnica y Stack Tecnológico

- **Backend**: PHP 8.x nativo, estructurado en módulos, sin frameworks pesados, garantizando máxima velocidad de respuesta y bajo consumo de recursos en Azure App Service.
- **Base de Datos & Persistencia**:
  - Base de Datos: Azure Database for MySQL (`luckyec_epson_nuevo`), conectada mediante PDO en [db_connect.php](file:///c:/Users/DiegoAntonioConstant/Desktop/TrazabilidadSeguimiento/EpsonReport/db_connect.php).
  - Almacenamiento Dinámico de Reportes: Endpoint [getters/guardar_registro.php](file:///c:/Users/DiegoAntonioConstant/Desktop/TrazabilidadSeguimiento/EpsonReport/getters/guardar_registro.php) y archivo JSON estructurado [data/registros_guardados.json](file:///c:/Users/DiegoAntonioConstant/Desktop/TrazabilidadSeguimiento/EpsonReport/data/registros_guardados.json).
  - Semilla / Fallback Azure: Función `ep_registros_datos_semilla()` en [includes/registros_datos.php](file:///c:/Users/DiegoAntonioConstant/Desktop/TrazabilidadSeguimiento/EpsonReport/includes/registros_datos.php) para asegurar carga resiliente de registros reales ante reinicios o despliegues.
- **Estilos**: Vanilla CSS modular en [assets/css/style.css](file:///c:/Users/DiegoAntonioConstant/Desktop/TrazabilidadSeguimiento/EpsonReport/assets/css/style.css). Se prohíbe el uso de TailwindCSS u otros frameworks de utilidad ad-hoc para preservar el control exacto de la identidad corporativa.
- **JavaScript**: Vanilla JS en [assets/js/app.js](file:///c:/Users/DiegoAntonioConstant/Desktop/TrazabilidadSeguimiento/EpsonReport/assets/js/app.js), modularizado mediante delegación de eventos y llamadas `fetch()` asíncronas a endpoints ligeros en `getters/`.
- **Tipografía**: Google Fonts corporativas:
  - **Sora** (titulares, marcas, números y badges clave).
  - **IBM Plex Sans** (cuerpo de texto, etiquetas de formulario y tablas).

---

## 4. Módulos del Sistema

### Módulo 1: Actividades (`index.php?vista=actividades`)
- **Propósito**: Formulario de captura y cálculo en vivo del trabajo de campo.
- **Lógicas y Plantillas Activas**:
  1. `activaciones`: Cobertura nacional/coberturadas, embudo de clientes (visitaron → interactuaron → compraron), modelos EcoTank exhibidos y cumplimiento de visitas.
  2. `capacitaciones`: Conteo de asistentes clasificados por cargo (Jefe de Tienda, Asistente, Vendedores), interacciones y comentarios pedagógicos.
  3. `epson-day`: Activaciones de alto impacto de un día con cobertura, embudo y modelos.
  4. `colocacion-pop`: Matriz de inventario de material POP (Bodega, Canales, Retail, Disponible) y desglose de entrega a tiendas físicas.
  5. `exhibiciones`: Auditoría de espacios físicos ganados (Muebles, Rumas, Cabeceras).
  6. `evento-ferias`: Gestión de stands en ferias y eventos tecnológicos especiales.
  7. `generico`: Plantilla fallback para actividades dinámicas creadas por el admin sin código especializado.
  8. `pendiente`: Estado temporal para actividades en definición.

- **Arquitectura de 3 Tarjetas Visualmente Separadas (`.ep-actividad-layout`)**:
  1. **Card Izquierda: Formulario de Datos (`#ep-panel-formulario`)**:
     - Tarjeta blanca independiente (`.ep-card`).
     - Aloja exclusivamente los pasos cuantitativos de la actividad seleccionada (Paso 1..N: Cobertura, Embudo, Modelos, etc.).
     - En móvil (< 900px) incluye el botón *"Continuar a Fotos de Evidencia"* para navegación fluida.
  2. **Card Derecha: Estadísticas en Vivo (`#ep-panel-estadisticas`)**:
     - Tarjeta blanca independiente (`.ep-card`).
     - Renderiza los gráficos, KPIs y cálculos automáticos reactivos al tipeo del usuario en tiempo real.
  3. **Card Inferior: Evidencia Fotográfica Obligatoria (`#ep-panel-evidencia`)**:
     - Tarjeta blanca independiente (`.ep-card.ep-panel-evidencia-card`), ubicada debajo de ambas columnas y abarcando todo el ancho (`grid-column: 1 / -1`).
     - **No está unida visualmente al formulario**, garantizando una clara separación estética entre datos cuantitativos y auditoría fotográfica.
     - **Cabecera**: Título oficial, subtítulo de auditoría ante Epson, badge de estado reactivo (`Pendiente` / `✓ Completa`), barra de progreso interactiva (`0 de X listas`) y botón principal *"Subir con Asistente"*.
     - **Grilla Panorámica (`.ep-evidencia-grid-panoramica`)**: Rejilla responsiva de 3 a 7 columnas que distribuye las casillas fotográficas a todo lo ancho de la tarjeta sin barras de scroll horizontal forzado.
     - **Cierre del Proceso**: Fila de botones de envío (`Guardar borrador` y `Enviar registro`) ubicada al pie de esta tarjeta de evidencia, respetando la cronología natural del proceso operativo:
       $$\text{1. Datos Cuantitativos} \longrightarrow \text{2. Evidencia Fotográfica} \longrightarrow \text{3. Enviar Registro}$$

- **Estudio Asistente Fotográfico Desktop (`.ep-wizard-overlay` > 900px)**:
  - *Columna Izquierda*: Checklist vertical de requerimientos obligatorios con estado interactivo (`Pendiente` / `✓ Cargada`) y navegación instantánea.
  - *Columna Derecha*: Visor amplio con esquinas HUD fotográficas Epson y **soporte Drag & Drop nativo desde Windows o WhatsApp Web**.
  - *Auto-Avance Guiado*: Al cargar cada foto, el sistema confirma visualmente con check verde y salta de forma automática al siguiente requerimiento pendiente.

- **Navegación Móvil (< 900px)**:
  - 3 pestañas ordenadas: `[ Formulario ] [ Fotos ] [ Métricas ]`.
  - Cada pestaña activa de manera limpia e independiente su tarjeta correspondiente.

- **Constructor de Actividades (Solo Admin)**:
  - Permite nombrar una nueva actividad y vincularla a una de las lógicas base.
  - Ofrece vista previa idéntica al formulario original en producción.
  - Permite el encendido/apagado de botones mediante switch de gestión.

---

### Módulo 2: Registros de Actividades (`index.php?vista=historial`)
- **Propósito**: Módulo de auditoría, reportería y visibilidad ejecutiva adaptado por rol operativo.
- **Versiones por Rol**:
  - **Versión Usuario / Promotor (`rol=usuario`)**:
    - Vista personal (*"Mis Registros de Actividades"*). Solo lista los formularios recolectados por dicho promotor.
    - Se ocultan selectores irrelevantes como el filtro de usuarios.
    - Agrupación cronológica directa de sus formularios diarios.
  - **Versión Administrador (`rol=admin`)**:
    - Supervisión consolidada de los 70+ promotores a nivel nacional.
    - **Agrupado por Usuarios**: Bloques compactos y ligeros por promotor con avatar, conteo de formularios y tiendas intervenidas.
    - Selector dual de agrupación: `[ Por Usuario | Por Fecha ]`.
    - Selector desplegable de 70+ promotores y selector de vistas (Fichas vs Tabla Data Grid).
- **Diseño Ultra Compacto & Ligero**:
  - Reemplazo de franjas estadísticas masivas por una barra de resumen ejecutiva en una sola línea (~28px).
  - Filas de registro reducidas a ~38px de altura con insignia de fecha de calendario (`24 OCT`).
  - Indicador circular minimalista para divulgación progresiva (acordeón).
- **Campos Auténticos en Detalle de Formulario ([detalle_formulario.php](file:///c:/Users/DiegoAntonioConstant/Desktop/TrazabilidadSeguimiento/EpsonReport/components/historial/detalle_formulario.php))**:
  - Sin tags inventados como *"Auditoría Regular"*, *"Aprobado"*, *"En revisión"* ni píldoras de canales (*"Departamental"*, *"Retail"*).
  - Muestra exclusivamente los campos reales capturados en las plantillas oficiales: Cobertura nacional vs. coberturadas, Embudo de clientes (*Visitaron → Interactuaron → Compraron*), desglose de Modelos EcoTank con unidades exactas, Cumplimiento de visitas, Asistentes por cargo en Capacitaciones, Matriz de inventario POP y galería de Evidencias fotográficas con visualizador.
- **Mecánica de Descarga PowerPoint (.pptx) (Solo Administrador)**:
  - **Ubicación Estratégica de Botones**:
    1. *Barra Principal de Herramientas*: Botón primario azul con icono de presentación (`#epBtnAbrirExportadorPPT`). Abre el configurador global.
    2. *Cabecera de Grupo de Promotor*: Botón compacto `PPT Diario` (`.ep-btn-user-ppt`). Pre-selecciona automáticamente a ese promotor específico y la fecha de sus reportes.
    3. *Fila Individual de Registro*: Botón `Slide` (`.ep-btn-record-ppt`). Pre-selecciona la actividad puntual, promotor y fecha exacta.
  - **Modal Interactivo de Exportación ([modal_exportar_ppt.php](file:///c:/Users/DiegoAntonioConstant/Desktop/TrazabilidadSeguimiento/EpsonReport/components/historial/modal_exportar_ppt.php))**:
    - **Panel de Control (Izquierda)**:
      - Selector de Promotor: "Todos los promotores (Consolidado)" o selección de cualquiera de los 70+ usuarios individuales.
      - Selector de Día: Campo de fecha con accesos directos rápidos (`24 Oct`, `23 Oct`, `Ayer`).
      - Selector Visual de Plantilla PPT con badges corporativos: `Activaciones` (ACT), `Capacitaciones` (CAP), `Epson Day` (EPD), `Colocación POP` (POP), `Exhibiciones` (EXH), `Eventos y Ferias` (EVT), y `Consolidado Multi-Slide` (ALL).
    - **Lienzo de Previsualización en Vivo 16:9 (`#epPptSlideCanvas`)**:
      - Emula una diapositiva panorámica real de PowerPoint con cabecera oficial Epson, barra de datos dinámicos (tienda, promotor, fecha) y layout reactivo que cambia según la plantilla seleccionada (embudo/cobertura, lista de asistentes, matriz de inventario POP, o slots fotográficos de auditoría).
    - **Mecánica de Descarga / Simulación**:
      - Botón de generación con spinner de progreso y confirmación de archivo con nomenclatura estándar corporativa: `Reporte_Epson_[TPL]_[Usuario]_[Fecha].pptx`.

---

## 5. Lineamientos de Diseño y Paleta Epson

- **Azul Primario Corporativo Epson**: `#0B1863` / `#10218B`
- **Azul Acento / Enlaces**: `#1F3BB3` / `#2548D0`
- **Fondos de Tarjeta y Superficies**: `#FFFFFF` (superficie blanca), `#F4F6FB` (superficie atenuada/gris suave)
- **Bordes y Separadores**: `#D8DEE9` / `#E2E8F4`
- **Estados**:
  - *Éxito / Aprobado*: `#137A3E` (fondo `#E7F7ED`)
  - *Alerta / En revisión*: `#B25E00` (fondo `#FFF4E5`)
  - *Peligro / Rechazado*: `#C5221F` (fondo `#FCE8E6`)
  - *Informativo / Epson*: `#10218B` (fondo `#EBF1FD`)
- **Responsividad**:
  - Escritorio: Distribución multipanel en cuadrícula de alta densidad y respiración visual.
  - Móvil (< 900px): Navegación mediante Drawer lateral, pestañas rápidas y adaptación fluida de tarjetas sin scroll horizontal no deseado.

---

## 6. Reglas de Trabajo del Asistente

1. **Control de Versiones (Git)**:
   - **NUNCA realizar `git commit` ni `git push` por iniciativa propia.** Los commits y subidas a ramas remotas deben ser solicitados o confirmados expresamente por el usuario.
2. **Resumen Obligatorio**:
   - **SIEMPRE presentar un resumen claro, conciso y ordenado** de cada acción realizada y los archivos intervenidos al responder.
3. **Principio de Responsabilidad Única (SRP)**:
   - Cada archivo, función o endpoint debe tener una sola razón para cambiar. Un `getters/*.php` hace una sola consulta o acción; una plantilla en `formularios/` solo arma el formulario de UN tipo de actividad; su par en `estadisticas/` solo arma las métricas de esa misma actividad.
   - Antes de agregar lógica a un archivo existente, preguntarse si esa lógica es "la misma responsabilidad" del archivo o si merece su propio archivo/función. Ante la duda, separar en vez de amontonar.
   - No mezclar consulta a base de datos, cálculo de negocio y armado de HTML/JSON en el mismo bloque cuando ya existe (o conviene crear) una función/archivo separado para cada cosa.
4. **Postura general: Desarrollador Senior pragmático** (código limpio, mantenible y eficiente; aplicar antes de escribir cualquier línea):
   - **YAGNI (You Aren't Gonna Need It)**: implementar únicamente lo necesario para el requerimiento actual. Prohibido código "por si acaso", configuraciones futuras, ganchos o abstracciones especulativas no pedidas explícitamente.
   - **KISS (Keep It Simple, Stupid)**: elegir siempre la solución más directa, legible y sencilla. Evitar over-engineering y patrones complejos (Factories, Abstract Factories, Singletons, etc.) salvo que sean estrictamente indispensables y justificados.
   - **Estándares de código y estructura**: archivos en un rango ideal de 100 a 300 líneas (evitar monolitos de más de 500, aplicando SRP). Evitar anidaciones profundas de `if/else`/`switch`, preferir guard clauses o retorno temprano. Funciones y métodos cortos, enfocados en hacer una sola cosa bien.
   - **Mantenibilidad**: comentarios únicamente donde la lógica de negocio sea compleja o no evidente a primera vista (el código debe ser autoexplicativo) — recordar que en este repo todo comentario va en una sola línea. Manejar errores de forma pragmática para los casos de fallo esperados, sin redundancias.

### Excepción documentada: `assets/js/app.js` (2026-09-23)

- Este archivo (~1850 líneas) queda como excepción justificada al límite de 500 líneas. Al auditarlo para dividirlo se encontró que casi todas sus funciones se llaman entre sí (selección de actividad, tabs móvil, asistente de fotos, cálculo de estadísticas por tipo de actividad, constructor, envío del formulario) — no son módulos independientes concatenados, es un solo controlador de la página de Actividades con estado compartido real.
- Dividirlo en archivos separados obligaría a pasar casi cada función por un espacio de nombres global (`window.EP.*`) para seguir siendo visible entre archivos, agregando indirección real solo para cumplir un número de líneas — eso va en contra de KISS, no a favor.
- `reportes.js`, `historial.js` y `sesion-watch.js` ya están correctamente separados porque sí son independientes (otras páginas/funciones). Si en el futuro se agregan secciones a `app.js` que sean genuinamente independientes del resto (no se llaman entre sí), esas sí deben salir a su propio archivo.

## Subida real de fotos a Azure (2026-09-23)

- Cada foto de evidencia se sube apenas se elige (drag/drop, input o asistente móvil): `app.js` la comprime a 1280px JPEG y la manda a `getters/subir_foto.php`, que valida el tipo real con `finfo` y la sube a Azure Blob con `includes/azure_storage.php` (REST + Shared Key, sin SDK).
- Destino: cuenta `luckyecuadorweb`, contenedor `app`, carpeta `AppEpson/EpsonReport/<tipo>/<año-mes>/` (lectura pública, igual que Jabonería/Pintuco). No existe contenedor propio de Epson; la carpeta `AppEpson/` solo tenía fotos históricas de la app vieja.
- La clave de Azure NO se duplica: `azure_storage.php` la toma de `Acuerdos_Comerciales/includes/azure_storage.php` (si EpsonReport se despliega solo, hay que definir `AZURE_STORAGE_ACCOUNT`/`AZURE_STORAGE_KEY` en `config.php`).
- El slot guarda la ruta en `data-foto-ruta`; `enviarFormularioActivo()` manda `fotos: {id: ruta}` y bloquea el envío mientras haya fotos subiendo. `guardar_registro.php` guarda `ruta` y `url` en cada foto del registro (solo acepta rutas bajo `AppEpson/EpsonReport/`).
- Sin probar contra Azure real (no se hicieron subidas de prueba para no escribir basura en el storage compartido).

## CSS dividido por módulo (2026-09-23)

- `assets/css/style.css` (4969 líneas, ~1500 muertas del viejo Historial fichas/tabla/expediente/lightbox ya reemplazado por `.ep-h2-*`) se eliminó. Ahora son 9 archivos: `base.css` (tokens), `login.css`, `shell.css` (sidebar), `actividades.css` (pasos, embudo, modelos, evidencia, POP, stats, constructor), `wizard-fotos.css` (tabs móvil + asistente de fotos móvil), `ppt-export.css` (botones y modal de exportación PPT), `wizard-fotos-desktop.css` (estudio de fotos escritorio), `historial.css` (rediseño `.ep-h2-*`), `reportes.css`.
- `index.php` carga todas menos `login.css`; `login.php` carga solo `base.css` + `login.css`. Cada uno con su propio `filemtime()` para cache-busting.
- Antes de borrar cualquier regla CSS por "no usada", cruzarla contra TODO el PHP/JS del proyecto (no solo el archivo que se está editando) — SweetAlert2 y otras libs de terceros generan sus propias clases (`swal2-*`) que nunca aparecen literalmente en nuestro código; no son código muerto.

## Estructura de carpetas (2026-09-23)

- `components/actividades/formularios/` (antes `plantillas/`): formulario de cada tipo de actividad. `estadisticas/` (antes `plantillas-stats/`): métricas en vivo de cada tipo. `compartidos/` (antes `partials/`): piezas comunes, hoy el paso de evidencia fotográfica.
- `layout/` (antes `partials/` de la raíz): sidebar y estructura común.
- `docs/`: material de referencia que no se despliega como código: `docs/diseno/` (mockup), `docs/grabaciones/` (transcripciones de reuniones). `epson/` (PPTX/XLSX originales) sigue en la raíz porque Windows no dejó moverla (archivos abiertos); pasarla a `docs/formatos-epson/` cuando se pueda.
- Se eliminó `scratch/` (scripts de prueba) y `db_connect.php` (reemplazado por `includes/db.php`).
- La clave interna `plantilla` de `ep_actividades()` no cambió de nombre (solo las carpetas).

## Registros en base de datos (2026-09-23)

- `data/registros_guardados.json` y los registros de ejemplo (semilla) ya no existen. Los reportes se guardan en `insert_reporte_registro` vía `includes/registros_datos.php`.
- `guardar_registro.php` arma el registro y lo inserta con `usuario_id` de la sesión; columnas para filtrar/contar (`tipo`, `fecha`, `pos_id`, `total_fotos`...) y el detalle completo en `valores` (JSON), `fotos` y `comentarios`. Código del registro: `REG-YYYYMMDD-HHMMSS-NNN` (único).
- `ep_registros_datos()` reconstruye cada registro con la misma forma que ya consumía Historial; el usuario `promotor` solo ve los suyos, el admin ve todos.
- Pendiente: `pos_id`/punto de venta siguen fijos ('SUKASA - MALL DEL SOL') hasta conectar `repositorio_locales_dtt2`.

## Sesión única con ventanas (2026-09-23), mismo patrón que Acuerdos_Comerciales

- Login por fetch (`getters/procesar_login.php` responde JSON `{ok, motivo, redirect}`): si la cuenta ya tiene sesión en otro dispositivo (`sesion_token` no vacío), se muestra una ventana (SweetAlert2) "¿cerrar esa sesión y entrar aquí?"; al confirmar se reenvía con `forzar=1`.
- `assets/js/sesion-watch.js` + `getters/sesion_verificar.php`: ping cada 15s; tras 2 fallos seguidos muestra la ventana "Tu sesión se cerró" y manda al login. De paso refresca `ultima_actividad`.
- Cerrar sesión (`logout.php`) limpia el token; si solo se cierra el navegador, el token queda y el siguiente login preguntará.

## Exportación PPT de Activaciones (2026-09-23)

- Plantilla oficial: `recursos/ppt/activaciones.pptx` (copia del formato de Epson). `includes/ppt_activaciones.php` la usa como base con `ZipArchive`: deja portada y título del mes ("ACTIVACIONES / JUNIO 2026") una sola vez y por cada registro clona las diapositivas 3-6 (calendario + cumplimiento, estadísticas, fotos 1-3, fotos 4-6), reemplazando textos, ancho de barras y los cuadros de foto por las fotos reales (recorte tipo "cover", descarga en paralelo desde Azure).
- Endpoint `getters/exportar_ppt.php?tipo=activaciones&mes=YYYY-MM&usuario=all|nombre`: el admin elige usuario, el promotor solo exporta lo suyo. Modal de Historial conectado a este endpoint (los demás formatos muestran "aún no disponible").
- Probado abriendo el archivo generado en PowerPoint (sin pedir reparación, textos/barras/fotos correctos). Para probar con PHP CLI local: `php -d extension=zip`.
- Se quita de la diapositiva de estadísticas la foto de ejemplo del promotor (no existe foto de perfil aún) y los cuadros de foto sin foto. El correo del promotor queda vacío (no hay dato).
- Para agregar otro formato (capacitaciones, etc.): copiar su PPTX a `recursos/ppt/`, mapear nombres de forma a datos como en `ppt_activaciones.php`.

## Historial rediseñado (2026-09-23)

- `components/historial/historial.php`: lista compacta (una fila por registro) + panel de detalle a la derecha; en móvil el detalle abre a pantalla completa. Filtros: actividad, texto, promotor (admin) y fechas; "Mostrar más" de 40 en 40.
- `components/historial/detalle_registro.php`: estadísticas con las mismas tarjetas del formulario (`ep-stat-*`, cobertura/interacciones/ventas, embudo, detalle de ventas, cumplimiento, comentarios) según el tipo, y fotos reales de Azure como miniaturas; clic abre el visor con flechas (`assets/js/historial.js`).
- Botón "Slide PPT" por registro y "Descargar PPT" (admin) siguen abriendo el modal de exportación.
- Quedó sin uso el controlador viejo del historial en `app.js` (fichas/tabla/agrupaciones) y su CSS; solo corre si existe `.ep-registros-main`, que ya no se renderiza. Limpiar cuando se quiera.
- Fix de fotos: al comprimir en el navegador se rellena el fondo en blanco (un PNG con transparencia salía negro en JPEG).

## Inactividad de 20 minutos y limpieza de sesiones (2026-09-23)

- `ep_login_check()` cierra la sesión si `ultima_actividad` supera 20 min (`EP_MINUTOS_INACTIVIDAD`) y en ese momento limpia `sesion_token` en la base.
- El ping de `sesion-watch.js` (cada 15s) ya no cuenta como actividad: solo lo hace una interacción real (mouse, teclado, toque, scroll), informada con `?activo=1`.
- El aviso "ya tienes una sesión activa" del login solo sale si esa sesión tuvo actividad en los últimos 20 min; una sesión abandonada (navegador cerrado, pestaña olvidada) ya no genera el aviso.
- Mensajes distintos: cierre por otro dispositivo vs. inactividad (`login.php?error=inactividad`).

### Ajuste (mismo día): cierre de sesión igual que Acuerdos_Comerciales

- Sesión "activa" para otro login = token guardado + latido en `ultima_actividad` de menos de 3 min (el ping de 15s es el latido). Ya no depende de la inactividad.
- La inactividad de 20 min se mide con la última interacción real, guardada en la sesión de PHP (`ult_interaccion`), no en la base.
- `logout.php` libera el token solo si coincide con el de la sesión, aunque esa sesión ya esté vencida, y destruye la sesión.

## Reportes mensuales (2026-09-23)

- Sección `Reportes mensuales` (solo admin; `secciones.php` la oculta al rol usuario): lista de reportes guardados + asistente de 3 pasos (tipo y mes → foto del calendario y programadas → selección de registros con casillas).
- Tabla `insert_reporte_mensual`: guarda solo la selección (ids de `insert_reporte_registro` en `registros`, ruta relativa del calendario en Azure `Reportes/...`, `programadas`); el PPTX NO se guarda, se arma en cada descarga (`getters/reporte_descargar.php`).
- Generador (`includes/ppt_activaciones.php`): 1 diapositiva de calendario y cumplimiento por reporte (programadas = escritas por el admin o iguales a las ejecutadas; ejecutadas = registros elegidos) + 3 diapositivas por registro (estadísticas, fotos 1-3, fotos 4-6).
- Getters: `reportes_registros.php` (registros elegibles), `reporte_guardar.php`, `reporte_descargar.php`, `reporte_eliminar.php` (borrado lógico). Todos exigen admin.
- Pendiente: quitar del formulario de Activaciones la foto del calendario y los campos programadas/realizadas; decidir qué pasa con el "Descargar PPT" viejo de Historial; otros tipos de actividad (solo Activaciones tiene plantilla).
