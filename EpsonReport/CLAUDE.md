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
  - El Historial ya no tiene modal ni botón global de exportación: solo el botón `Slide` (`.ep-btn-record-ppt`) por registro.

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
- Probado abriendo el archivo generado en PowerPoint (sin pedir reparación, textos/barras/fotos correctos). Para probar con PHP CLI local: `php -d extension=zip`.
- Se quita de la diapositiva de estadísticas la foto de ejemplo del promotor (no existe foto de perfil aún) y los cuadros de foto sin foto. El correo del promotor queda vacío (no hay dato).
- Para agregar otro formato (capacitaciones, etc.): copiar su PPTX a `recursos/ppt/`, mapear nombres de forma a datos como en `ppt_activaciones.php`.

## Historial rediseñado (2026-09-23)

- `components/historial/historial.php`: lista compacta (una fila por registro) + panel de detalle a la derecha; en móvil el detalle abre a pantalla completa. Filtros: actividad, texto, promotor (admin) y fechas; "Mostrar más" de 40 en 40.
- `components/historial/detalle_registro.php`: estadísticas con las mismas tarjetas del formulario (`ep-stat-*`, cobertura/interacciones/ventas, embudo, detalle de ventas, cumplimiento, comentarios) según el tipo, y fotos reales de Azure como miniaturas; clic abre el visor con flechas (`assets/js/historial.js`).
- El modal viejo de exportación de Historial y `getters/exportar_ppt.php` se eliminaron; el botón "Slide" por registro descarga directo (`getters/registro_ppt.php`) y avisa si el tipo aún no tiene formato.
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
- Pendiente: quitar del formulario de Activaciones la foto del calendario y los campos programadas/realizadas; generadores PPT de Epson Day, Ferias, Exhibiciones y POP (hoy solo Activaciones y Capacitaciones).

## Login de mercaderistas y punto de venta (2026-09-24, SIN PROBAR en navegador)

- **Quién entra**: los promotores son los mercaderistas de `repositorio_usuarios` (Xplora, solo lectura, nunca se modifica). El admin sigue con su clave propia en `repositorio_usuarios_reporte`.
- **Registro del primer ingreso**: si el usuario existe y está activo en Xplora pero no tiene clave propia, `procesar_login.php` responde `registrar` y `login.php` cambia al formulario "Crea tu contraseña" (`components/login/form_registro.php`, lógica en `assets/js/login.js`). `procesar_registro.php` comprueba la cédula una sola vez (no es la clave), guarda la contraseña en texto plano como pidió el usuario (mínimo 6 caracteres) y el JS entra directo. La cédula mal escrita suma intentos y bloquea 15 min como el login.
- **Sin cédula en Xplora** (hoy solo JULIO PENA, id 79): no puede registrarse hasta que el admin lo habilite; ese flujo de habilitación no está construido.
- **Archivos**: `includes/login_datos.php` (perfil, lectura Xplora, bloqueos, log), `getters/procesar_login.php`, `getters/procesar_registro.php`. Un promotor desactivado en Xplora deja de entrar aunque conserve perfil.
- **Punto de venta**: `includes/pdv_datos.php` + `components/actividades/compartidos/paso_pdv.php`. Lista de `repositorio_locales_dtt2` con `activar='SI'` del canal del usuario; el canal sale del rutero (`lvi_rutero`) contando solo puntos activos: dominante, o ambos si el menor pesa 20% o más (`EP_PDV_MINORIA_MIXTO`), o ambos si no hay rutero. El admin ve ambos. `guardar_registro.php` resuelve pos_id, punto, ciudad, canal y cadena desde la base; Colocación de POP no lo exige.
- **Pendiente**: correo del promotor (inicial del primer nombre + primer apellido de `mercaderista`, `@xplora.net`), fotos de Activaciones con mínimo 3 y máximo 6 (listón, interacción, venta), mover la lógica del PDV de `app.js` a `assets/js/pdv.js`, habilitación de admin y restablecer clave.
- **Reglas de Activaciones acordadas (grabación 23/09)**: el calendario lo sube el supervisor o admin, no el promotor; programadas/realizadas ya no van en el formulario.

## PPTX por registro y fotos obligatorias de Activaciones (2026-09-24, SIN PROBAR)

- **PPTX de un registro**: `getters/registro_ppt.php?id=<código del registro>` (solo admin) arma el archivo al descargar y lo borra; nada se guarda. Reusa `ep_ppt_activaciones()` con la opción `solo_registro` (sin portada, título del mes ni calendario): diapositiva de estadísticas (la foto del promotor queda vacía a propósito) y diapositiva de fotos con el punto de venta como título. El botón "Slide PPT" de cada registro en Historial descarga este archivo para Activaciones; los demás tipos siguen abriendo el modal.
- **Fotos de Activaciones**: obligatorias 3 (`stand` = promotor con listón y materiales, `interaccion-1`, `venta-1`); opcionales hasta 3 más (`interaccion-2`, `venta-2`, `venta-3`, marcadas `'opcional' => true` en `includes/fotos_datos.php`). Primera diapositiva de fotos: las 3 obligatorias; la segunda solo sale si hay opcionales. `guardar_registro.php`, `paso_evidencia.php` y `app.js` cuentan y exigen solo las obligatorias.
- **Datos de la actividad (Activaciones)**: el promotor escribe tipo de actividad (mayúsculas; solo letras, números, espacios y guion; máx. 40), fecha (cualquiera; viene con hoy por defecto y solo se exige que sea una fecha real) y horario de inicio y fin en el paso 1 del formulario; se validan en `includes/actividad_datos.php` y se guardan como `tipo_actividad`, `fecha_actividad`, `hora_inicio`, `hora_fin`. El PPT usa esos datos ("ACTIVIDAD IMPULSO", fecha y "10:00 – 18:00").
- **Correo del promotor**: se pide una vez en el registro del primer ingreso y se guarda en la columna `correo` de `repositorio_usuarios_reporte` (el ALTER lo corre el usuario: `ALTER TABLE repositorio_usuarios_reporte ADD COLUMN correo VARCHAR(150) NULL AFTER nombre;`). Sin la columna, el registro guarda solo la contraseña y el PPT sale sin correo. Cada registro guarda `promotor_correo` al enviarse.
- **Selector de PDV**: `assets/js/pdv.js` + `assets/css/pdv.css`, componente propio con hoja inferior en celular; la lógica ya no vive en `app.js`.
- **Reportes mensuales** siguen usando el generador completo (portada, calendario, tres diapositivas por registro; la segunda de fotos solo si hay opcionales).

- **Ajustes tras la primera prueba (2026-09-24)**: las fotos guardadas como WebP no salían en el PPTX (PowerPoint no las admite); ahora se convierten a JPEG con GD al armar el archivo y la compresión del navegador siempre deja JPEG. Fotos en el PPT: de 3 en 3 por diapositiva, con 2 a mitad y mitad y con 1 centrada. Los números de cobertura, interacciones y ventas van a la derecha (tabulador derecho), sin espacio antes del %, y la ciudad baja si el punto de venta se parte en varias líneas. El asistente de fotos solo exige las 3 obligatorias ("Finalizar y revisar" aparece al completarlas) y el detalle del historial siempre muestra las tarjetas de SKU con mayor y menor venta ("Sin datos" si no hay modelos).
- **Login móvil**: las reglas móviles del login habían quedado en `wizard-fotos.css` al dividir el CSS por módulo y la página de login no la carga; se movieron a `login.css`.
- **Sidebar**: en escritorio se retrae solo al hacer clic fuera (sin cambiar la preferencia guardada) y un clic en su zona vacía lo alterna. **Formulario**: en escritorio va más compacto (menos alto, mismo ancho) con reglas al final de `actividades.css`.

## Registros: columnas propias y tablas hijas (2026-09-24, SIN PROBAR)

- `insert_reporte_registro` guarda en columnas lo que se filtra o suma (`tipo_actividad`, `fecha_actividad`, `hora_inicio`, `hora_fin`, `tiendas_nacional`, `tiendas_coberturadas`, `visitaron`, `interactuaron`, `compraron`); `valores` (JSON) queda solo con el resto. No se guardan porcentajes: se calculan al leer.
- Tablas hijas (una fila por elemento, sin claves foráneas): `insert_reporte_registro_modelo`, `insert_reporte_registro_foto` (una por casilla), `insert_reporte_registro_comentario`. Código en `includes/registros_hijos.php`; `registros_datos.php` guarda dentro de una transacción y `ep_registro_armar()` reconstruye la misma forma que consumen Historial y el PPT.
- Los registros viejos (JSON completo, columnas nuevas en NULL) se siguen leyendo igual. Las columnas `fotos`, `comentarios` y `total_fotos` ya no se escriben; borrarlas queda pendiente hasta confirmar que todo funciona.
- Tablas propias del proyecto: `insert_reporte_registro` (+ 3 hijas), `insert_reporte_login`, `insert_reporte_mensual`, `repositorio_usuarios_reporte`. Codificación utf8mb4 en las de registro.
- **PPT, gráficas (2026-09-24)**: las barras del embudo son rectángulos girados 270° (su largo es `cx`); `ep_ppt_barra_vertical()` las recoloca para que todas apoyen en la misma base y los números queden encima. En el detalle de ventas el fondo y las barras se acortan (`$largoMax`) para que el número quede afuera, a la derecha.
- **Historial**: la lista muestra la fecha de la actividad (con su horario) y, debajo del nombre de la actividad, cuándo se registró; el código ya no se muestra en la lista. Los códigos nuevos son cortos: prefijo del tipo, siempre R de "Registro" + abreviatura (RAC Activaciones, RCAP Capacitaciones, RPOP Colocación de POP, RDAY Epson Day, REXH Exhibiciones, RFER Evento o Ferias; otro tipo: REG) + usuario + número por usuario y tipo (`RACPABLOCASTELO-001`); los anteriores (`REG-...`) se siguen leyendo.

## PPTX por actividad: motor común y Capacitaciones (2026-09-24, SIN PROBAR)

- **Motor común** (`includes/ppt_motor.php`, sobre `ppt_base.php`): abre la plantilla, clona la diapositiva de estadísticas y la de fotos por registro, pone la barra azul del promotor (igual en todas las plantillas: `CuadroTexto 16/17/20/21/22/23` y `Gráfico 19`), reparte las fotos de 3 en 3 (con 2 mitad y mitad, con 1 centrada) y reescribe los índices. Cada actividad solo aporta una especificación (`plantilla`, `titulo`, `prefijo_actividad`, `stats`, `fotos`, y opcional `fija` para una diapositiva por reporte) y la función que llena sus estadísticas. `ep_ppt_generador($tipo)` elige el generador; `getters/registro_ppt.php` lo usa para cualquier tipo que tenga uno.
- **Activaciones** (`ppt_activaciones.php`) se migró al motor sin cambiar su contenido (calendario una vez, estadísticas, detalle de ventas, embudo, comentarios). **Capacitaciones** (`ppt_capacitaciones.php`, plantilla `recursos/ppt/capacitaciones.pptx`): detalle de asistentes por cargo, asistentes contra interacciones y comentarios; las 3 fotos (equipo, capacitación, entrega) son obligatorias.
- **Formulario**: el paso "Datos de la actividad" es una pieza compartida (`compartidos/paso_datos_actividad.php`, ids `ep-<prefijo>-tipo|fecha|hora-inicio|hora-fin`; `act` y `cap`). Capacitaciones guardaba con ids viejos que ya no existían; ahora envía `vendedores`, `jefe_tienda`, `asistente_jefe` e `interacciones` (tope: no superan el total de asistentes) y se guardan en `valores.capacitacion`; el total se calcula al leer.
- **Pendiente con este mismo patrón**: Epson Day, Evento o Ferias, Exhibiciones que inspiran y Colocación de POP (plantillas en `epson/FORMATOS FOTOGRAFICOS 2026/`); el reporte mensual solo genera Activaciones todavía.

## Login por verificación, Historial en vivo y visor de fotos (2026-09-24, SIN PROBAR)

- **Primer ingreso**: "¿Primera vez aquí?" abre una ventana que pide el usuario; `getters/verificar_usuario.php` lo busca activo en Xplora (si no existe: "Usuario no autorizado"; si ya tiene clave: "inicia sesión") y solo entonces pasa a "Crea tu contraseña".
- **Historial en vivo**: `assets/js/historial.js` consulta cada 3 s `getters/historial_firma.php` (total y último id) y solo si cambió pide `getters/historial_filas.php` (HTML de `components/historial/filas.php`), conservando filtros y selección. Se pausa con la pestaña oculta o el detalle móvil abierto.
- **Estado vacío**: `ep_estado_vacio(icono, título, texto)` en `functions.php` + `.ep-vacio` en `base.css`, en tonos grises; usado en Historial y Reportes.
- **Visor de fotos antes de enviar**: al tocar una foto ya cargada en Actividades se abre un carrusel (`compartidos/visor_fotos.php`, `assets/js/visor-fotos.js`, `assets/css/visor-fotos.css`) con "Cambiar foto" y "Quitar". `app.js` expone `window.epFotos` (`slots`, `quitar`) para que el visor deje la casilla como pendiente.
- **Barras del PPT (2026-09-24)**: todas las barras de datos (horizontales y verticales, Activaciones y Capacitaciones) usan el diseño de las estadísticas de la web: esquinas redondeadas y azul Epson `#10218B` (`ep_ppt_estilo_barra()` en `ppt_base.php`; el fondo gris de las horizontales solo se redondea). Las verticales bajan a la mitad de su grosor original (`ep_ppt_barra_vertical`, parámetro `$ancho`).
- **Comentarios como lista (2026-09-24)**: `assets/js/comentarios.js` + `assets/css/comentarios.css` convierten cada `textarea[id$="-comentarios"]` en líneas numeradas (máx. 5, 160 caracteres); el textarea original queda oculto con un comentario por línea, así el guardado y las estadísticas no cambian.
- **PPT, ajustes de diseño (2026-09-24)**: las barras verticales ya no van giradas (rectángulo normal, solo esquinas de arriba redondeadas, `round2SameRect`); `ep_ppt_ensanchar()` estira las estadísticas a la derecha del panel del promotor (`'stats' => [..., 'ensanchar' => 1.5]` en la especificación; Capacitaciones); los comentarios van en un solo cuadro ancho, uno por párrafo (`ep_ppt_comentarios()`), y las mayúsculas usan `ep_ppt_mayus()` para respetar tildes.

## PPTX de Epson Day, Evento o Ferias y Exhibiciones (2026-09-24, SIN PROBAR en producción)

- **Motor**: `ep_ppt_generador()` ya incluye `epson-day` (`ppt_epson_day.php`), `evento-ferias` (`ppt_evento_ferias.php`) y `exhibiciones` (`ppt_exhibiciones.php`); plantillas en `recursos/ppt/`. La barra del promotor acepta nombres de forma propios por plantilla (`'promotor'` en la especificación; Epson Day usa otros).
- **Estadísticas comunes** (`includes/ppt_embudo.php`): Activaciones, Epson Day y Evento o Ferias comparten la misma diapositiva (cobertura, interacciones, ventas, detalle por modelo, SKU mayor/menor, embudo, comentarios); cada una pasa su mapa de nombres de forma. Evento o Ferias no lleva la tarjeta de cobertura.
- **Exhibiciones**: cinco tipos en el orden de la plantilla (cabeceras, rumas, muebles, exhibición regular, otras) con barras y comentarios; se ensancha 1.5 como Capacitaciones y la tarjeta de comentarios se iguala a la del detalle.
- **Formularios**: los tres tienen ahora el paso compartido "Datos de la actividad" (prefijos `eday`, `evento`, `exh`) y el envío ya usa sus ids reales (antes leía `ep-eps-*` y `ep-fer-*`, que no existían). Epson Day y Evento envían también los modelos. Exhibiciones suma dos campos (`ep-exh-regular`, `ep-exh-otras`) para cubrir las cinco filas de la plantilla.
- **Datos**: Evento o Ferias guarda `embudo` (ya no `feria`) y modelos como Activaciones, sin cobertura; Exhibiciones guarda `exhibiciones` con los cinco conteos y sus porcentajes.
- **Pendiente**: reportes mensuales solo generan Activaciones; Colocación de POP (plantilla de 5 diapositivas) sin PPT ni datos de actividad.

## Reportes Mensuales: Espacio de Trabajo de Activaciones (2026-09-24)

- **Eliminación del stepper antiguo**: Se quitó el asistente de 3 pasos numerados (`1 Tipo de actividad`, `2 Calendario`, `3 Registros`) para dar paso a vistas especializadas por lógica de actividad (`tipoLogica === 'activaciones'`).
- **Selector de actividades (Vista 1)**: Buscador interactivo en la parte superior, grilla responsiva de 6 botones en 2 columnas (con badge 'Activo' o 'Nuevo', íconos y subtítulos de plantilla), input opcional de título personalizado y botón "Siguiente" a la derecha.
- **Espacio de trabajo de Activaciones (Vista 2, modal ancho `ep-rp-dialog-wide`)**:
  - **Columna Izquierda**:
    - Encabezado con título dinámico de la actividad en mayúsculas y viñeta púrpura.
    - Dropzone de calendario con instrucciones directas y concisas ("Haz clic o arrastra la foto del calendario aquí", "Formatos JPG o PNG (máx. 10MB)").
    - KPIs interactivos: `Programados` (campo numérico abierto, sin mínimo) y `Ejecutado` (contador automático en vivo de los registros seleccionados a la derecha, con porcentaje de cumplimiento calculado en tiempo real).
    - Regla de límite estricto: Si `Programados` es $N$, el usuario no puede seleccionar más de $N$ registros en la lista derecha.
    - Caja de `Seleccionados`: lista en vivo de chips con código, punto de venta, hora y botón `(X)` para desmarcar individualmente; incluye contador de ítems y botón "Limpiar".
  - **Columna Derecha**:
    - Barra de filtros: Selector de modo Fecha (`Rango` con dos campos o `Única` con un campo); selector de Promotor tipo combobox con búsqueda por tipeo en tiempo real; selector de Canal dinámico.
    - Tabla dual (`Registros` | `Previsualización`):
      - Columna izquierda: tarjeta con checkbox, código (`REG-2024-XXX`), badge de estado (`Activo` / `Pendiente`), descripción de la actividad y punto de venta / ciudad.
      - Columna derecha: tarjeta miniatura de la evidencia con miniatura de la foto y botón flotante "Ampliar".
  - **Modal Lightbox de Previsualización**:
    - Renderiza con fidelidad total la **primera diapositiva del PPTX** del registro: barra superior azul oficial de Epson (promotor, correo, punto de venta, actividad, fecha/horario, ciudad), métricas de Cobertura, Interacciones (embudo) y Ventas, y la fotografía principal del punto de venta en alta resolución.
  - **Pie del Modal**:
    - Botón "Atrás" ubicado en la esquina izquierda para regresar al selector de actividades y contraer el modal.
    - Nota de validación "Todos los cambios se validarán antes de consolidarse."
    - Botones "Cancelar" y "Guardar Reporte" a la derecha.
  - **Archivos creados/modificados**:
    - [components/reportes/mecanica_activaciones.php](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/components/reportes/mecanica_activaciones.php)
    - [components/reportes/reportes.php](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/components/reportes/reportes.php)
    - [assets/css/reportes.css](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/assets/css/reportes.css)
    - [assets/js/reportes.js](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/assets/js/reportes.js)
    - [getters/reportes_registros.php](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/getters/reportes_registros.php)
    - [getters/reporte_guardar.php](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/getters/reporte_guardar.php)

## Paleta morada (prueba, 2026-09-24)

- La interfaz web pasó del azul Epson a morado: `--color-primary` `#6242A5`, `--color-primary-dark` `#4A3080`, `--color-primary-mid` `#9573DF`, `--color-primary-light` `#B39CE8`, `--color-primary-soft` `#F1EBFC` (en `base.css`). Sidebar y panel del login usan el degradado `#9573DF → #6242A5 → #4A3080`. Los demás azules de los CSS/JS/PHP de la interfaz se convirtieron al mismo matiz (misma luminosidad); los PPTX conservan el azul de Epson.
- Login sin foto ni marca en la esquina (escritorio y celular; `assets/img/login.png` ya no se usa). Pie "© PromoLucky 2026" en el login y bajo "Cerrar sesión" en el sidebar.

## Selector de actividades en Reportes mensuales (2026-09-24)

- **Modal de Nuevo Reporte (`components/reportes/reportes.php`)**: el `<select id="epRpTipo">` se reemplazó por un selector en dos columnas (`.ep-rp-act-grid`, 6 botones base) con buscador en tiempo real (`#epRpBuscarActividad`) en la cabecera del paso 1.
- **Actividades activas**: `ep_actividades_activas()` en `includes/actividades_datos.php` filtra las actividades con `'activo' => false` o marcadas en `$_SESSION['ep_actividades_desactivadas']`.
- **Persistencia del switch del constructor**: `getters/toggle_actividad.php` recibe el cambio del switch en el constructor (`app.js`) y actualiza `$_SESSION['ep_actividades_desactivadas']`, reflejando la desactivación o activación inmediatamente en el modal de reportes.
- **Visualización escalable**: la grilla tiene límite de altura (`max-height: 250px`) y scrollbar dedicada en CSS (`assets/css/reportes.css`), adaptándose a una sola columna en pantallas móviles (`< 560px`).
- **Interacción y búsqueda (`assets/js/reportes.js`)**: filtrado insensible a tildes y mayúsculas, contador reactivo (`#epRpActCount`), botón para limpiar búsqueda y mensaje de estado vacío (`#epRpActVacio`).
- **Ajustes de pulido**:
  - Se eliminó el campo visible de "Mes del reporte" y la píldora informativa del pie (`#epRpResumenPie`); el mes queda interno como campo oculto con el mes en curso.
  - Corrección del borde morado al escribir: la regla global `input:focus` de `base.css` pintaba un contorno rectangular sobre el `<input>`; se neutralizó en `reportes.css` con `outline: none !important; border: none !important;`.
  - Navegación hacia atrás: botón "Atrás" posicionado en la esquina izquierda del pie (`.ep-rp-btn-atras`) separado de "Siguiente" (alineado a la derecha), con navegación directa en los pasos completados del stepper superior.
  - Título opcional conservado: se mantiene el campo de entrada "Título personalizado (opcional)" bajo la grilla de actividades.



## Reportes mensuales: guardado final y copia congelada (2026-09-25, SIN PROBAR en navegador)

- **Flujo del modal**: actividad → workspace (Activaciones = vista 2 con calendario, programados y comentarios; las demás lógicas = vista 3) → "Continuar" → paso final (`components/reportes/paso_final.php`: nombre con el que se descarga, mes, rango de fechas y total) → "Guardar Reporte". El modal ya no se cierra al hacer clic afuera.
- **Por lógica**: el modal y el PPTX se resuelven por la lógica (plantilla) del botón; solo `activaciones` lleva calendario. El tipo de cada registro sale de `data-plantilla` del botón (`app.js`), no del nombre.
- **Guardado** (`reporte_guardar.php` + `ep_reporte_crear`): columnas `comentarios` y `snapshot` en `insert_reporte_mensual` (ALTER ya corrido). `snapshot` = JSON `{desde, hasta, registros[]}` con los registros armados: el reporte queda congelado. Se exige nombre y mes; los registros deben ser de esa lógica y no estar en otro reporte activo.
- **Registros libres**: `ep_registros_ocupados()` excluye del modal (`reportes_registros.php`) los registros de reportes activos; al eliminar un reporte (borrado lógico) vuelven a aparecer.
- **Descarga** (`reporte_descargar.php`): usa `ep_ppt_generador($tipo)` y la copia congelada (los reportes viejos sin copia usan los registros actuales); registros ordenados por persona y fecha, cada persona es una sección del PPTX. La diapositiva del calendario (`ppt_activaciones_calendario`) usa los comentarios del reporte (uno por línea, máx. 5).

## Historial y Reportes mensuales rediseñados (2026-09-25, SIN PROBAR en producción)

- **Filtros compartidos**: `assets/css/filtros.css` + `assets/js/filtros.js` (`epFiltros.crearCombo`) dan la barra de búsqueda y los selectores con buscador (`.ep-fl-*`) y el icono redondeado de actividad (`.ep-fl-ico`) a Historial y Reportes; escalan a muchas actividades porque las opciones salen de los datos, no de pestañas fijas.
- **Historial**: búsqueda (código, promotor, punto de venta, actividad), selectores de actividad (por nombre del botón) y promotor (admin), periodos rápidos Todo/Hoy/Semana/Mes, rango de fechas y chips de filtros activos. La lista (`components/historial/filas.php`) va agrupada por día de la actividad ("Hoy", "Ayer", día) en tarjetas; el detalle conserva las estadísticas de antes y estrena cabecera con icono, punto de venta y botón "Descargar Slide". `historial.css` se reescribió sin las capas de reglas superpuestas ni restos del modal PPT viejo.
- **Reportes mensuales** (`components/reportes/reportes.php` + `assets/js/reportes-lista.js`): lista agrupada por mes en tarjetas (icono, nombre, actividad, registros y rango de fechas leído del inicio del `snapshot`, creador, cumplimiento = registros/programados o "Sin meta"), con búsqueda y filtros de actividad y mes. Quitar un reporte libera sus registros. Se podaron de `reportes.css` las reglas del stepper y de la tabla vieja.
- **Constructor y lógicas (2026-09-25)**: la vista previa de fotos de "Nueva actividad" usa el carrusel compartido (`assets/js/carrusel.js` + `.ep-car-*` en `actividades.css`; antes usaba una clase sin estilos). Un botón nuevo hereda la `plantilla` (lógica) de su origen: el registro se guarda con `tipo` = lógica y `actividad_label` = nombre del botón; el modal de reportes, el PPTX y el Historial resuelven por lógica, y el reporte guarda el nombre del botón elegido (`snapshot.actividad`) para el título de la introducción. **Limitación**: las actividades nuevas viven en la sesión del admin (`$_SESSION['ep_actividades_extra']`, mock sin tabla), por lo que los promotores aún no las ven.

## Actividades en base de datos y documentacion viva (2026-09-25)

- Los botones de actividad viven en insert_reporte_actividad (nombre, logica que replica, activo, orden, badge, borrado logico). includes/actividades_datos.php tiene ep_logicas() (las 6 logicas base con sus campos), ep_actividades() (lee la tabla), ep_actividades_activas(), ep_actividades_visibles() (admin: todas; promotor: activas), ep_actividad_crear() y ep_actividad_activar(). crear_actividad.php y toggle_actividad.php escriben en la tabla (ya no en la sesion); el nombre no puede repetirse. Cada logica se renderiza una sola vez aunque varios botones la usen.
- Regla de documentacion: docs/base-de-datos/diagrama.mmd (solo codigo Mermaid, sin texto ni bloques markdown, para copiar y pegar en https://mermaid.live) se actualiza en el mismo trabajo que cambie el esquema. El SQL se da en el chat, no se guarda en el repo. Buenas normas de tablas: utf8mb4, created_at, borrado logico con eliminado_en, indice para la consulta habitual, sin claves foraneas (convencion del proyecto).
- **Historial en vivo solo para admin (2026-09-25)**: el chequeo cada 3 s (`historial_firma.php`) corre solo si `data-admin="1"`; el promotor se actualiza al volver a la pestaña. Solo importan los usuarios admin para lo que sea en vivo o pesado.

## Precio por modelo e ingresos (2026-09-29, requiere ALTER en insert_reporte_registro_modelo, SIN PROBAR)

- `insert_reporte_registro_modelo` suma la columna `precio` (`ALTER TABLE insert_reporte_registro_modelo ADD COLUMN precio DECIMAL(10,2) NULL AFTER cantidad;`). `repositorio_productos` (Xplora) tiene columnas `dolar`/`pvp` pero **ninguno de los 132 productos de IMPRESORAS trae precio cargado** (verificado, solo lectura): el precio se sigue pidiendo a mano por modelo, no se autocompleta.
- **Solo Activaciones y Epson Day** piden precio (`crearGestorModelos(..., conPrecio=true)`); Evento o Ferias sigue igual, sin precio, tal como pidió el cliente en la reunión del 28-09 (ver `docs/grabaciones/28-09-2026.txt`).
- Nueva gráfica "Ingresos por Modelo" (barras en dólares, cantidad × precio) en las estadísticas en vivo (`pintarIngresosPorModelo()` en `app.js`) y en el detalle del Historial; se oculta sola si ningún modelo trae precio.
- **Pendiente, sin resolver todavía**: el PPTX no lleva esta gráfica. Las plantillas de Epson son un diseño corporativo fijo (formas ya definidas); agregar un cuadro nuevo requeriría que Epson actualice el formato oficial con espacio para eso, no se puede improvisar sobre la plantilla actual.

## Calendario de Activaciones (2026-09-29, SIN PROBAR en navegador)

- Módulo nuevo `index.php?vista=calendario` (solo admin), diseñado primero con `impeccable` a partir de un boceto del usuario, luego construido contra datos reales. Tablas: `insert_reporte_calendario` (cabecera: canal, plazo_dias, desde/hasta calculados solos, estado activo/cerrado, vence_en, reporte_mensual_id) e `insert_reporte_calendario_fila` (fecha, pos_id, promotor_usuario_id, supervisor_nombre, estado, registro_id). Sin llaves foráneas, como el resto del proyecto.
- **Sin borrador**: crear un calendario lo activa de inmediato (`getters/calendario_crear.php` + `ep_calendario_crear()`).
- **Cascada real de Xplora** (`ep_calendario_rutero()` en `includes/calendario_datos.php`, join `lvi_rutero.user = repositorio_usuarios_reporte.usuario`, confirmado contra datos reales): Ciudad → Promotor (filtrado por ciudad) → Punto de venta (filtrado por promotor), con Supervisor y Canal ya resueltos, nunca a mano. El Canal se elige una vez por calendario (decide qué rutero cargar); el resto sale de ahí.
- **Cruce automático**: al guardar un registro de Activaciones (`ep_guardar_nuevo_registro()` en `registros_datos.php`), se llama a `ep_calendario_cruzar_registro()` con `pos_id` y `fecha_actividad` (la fecha real de ejecución, no la de envío) para marcar la fila `cumplido`.
- **Plazo configurable por calendario** (ya no fijo en 5 días): al vencer (`ep_calendario_verificar_vencidos()`, se llama al cargar la pantalla) o al tocar "Generar ahora", el calendario se cierra y genera un reporte mensual automático (`ep_reporte_crear()`) con los registros cumplidos — no un botón de descarga aparte, queda dentro de Reportes mensuales.
- **Edición de fila**: solo punto de venta y promotor (nunca la fecha), solo si el calendario sigue activo y esa fecha todavía no pasó; se revalida siempre contra el rutero real, nunca se confía en lo que manda el navegador.
- **Reactivar** un calendario cerrado da un plazo nuevo desde ese momento y deja `reactivado_por`/`reactivado_en` (auditoría completa de esto queda pendiente como módulo aparte, según lo habló el usuario).
- Filas y seguimiento por promotor van en `<details>` colapsados por defecto (pensado para 100+ filas por calendario).
- **Diseño del modal (2026-09-29)**: rediseñado con la herramienta de Diseño de Claude a pedido explícito del usuario (no con `impeccable`, que es la opción por defecto del repo, porque aquí se pidió por nombre). Supervisor quedó como columna propia en la fila (con truncado "…" y tooltip nativo si el nombre no entra), "Rango (automático)" bajó de expandirse a todo el ancho a un tamaño fijo de 200px, y el campo "Nombre del calendario" aclara que es el título con el que se genera el reporte PPTX automático. Versión móvil pensada como hoja inferior (mismo patrón que el selector de punto de venta), con las filas apiladas en una sola columna. Se limpiaron 3 reglas CSS que quedaron sin uso al sacar los estados "borrador" y "bloqueado" (`ep-cal-badge-gris`, `ep-cal-badge-alerta`, `ep-cal-pill-alerta`).
- **Rendimiento (2026-09-29)**: `lvi_rutero` es una VISTA de Xplora (no una tabla, no se le puede poner índice) armada sobre `rutero_pdv` (237.000+ filas) con un `GROUP BY fecha_visita, id_pdv, id_usuario` — como es el calendario histórico de visitas, agrupa por día, y MySQL tiene que materializar eso completo antes de poder aplicar cualquier filtro nuestro, sin importar los índices de las tablas de abajo (comprobado: agregar índices en `rutero_pdv.status/habilitado/id_pdv/id_usuario/id_supervisor` casi no cambió el tiempo, ~4s). La solución real fue que `ep_calendario_rutero()` consulte directo `rutero_pdv` + `repositorio_locales_dtt2` + `repositorio_usuarios` + `repositorio_supervisores` (sin pasar por la vista ni por su agrupación por fecha, que no nos interesa) — de ~3.5-4s a ~1.7s por canal. Encima se cachea 5 minutos en `data/cache/rutero_<CANAL>.json` (dato compartido entre admins). `calendario.php` también llama `session_write_close()` apenas termina de leer la sesión, para no bloquear otras pestañas mientras esta pantalla carga.

## Calendario: editar, eliminar y cruce por nombre (2026-09-29, SIN PROBAR en navegador)

- **Editar** (botón en calendarios activos): reusa el modal de Crear con todo bloqueado salvo ciudad, promotor y punto de venta de las filas **pendientes con fecha de hoy o posterior** (grabación 28-09, líneas 259-333: "antes de o en el mismo día"; una fila con registro "queda quemada"). La fecha nunca se edita, no se agregan ni quitan filas. `getters/calendario_editar.php` revalida todo en el servidor y guarda en una transacción; solo viajan las filas que cambiaron, así `editado_por`/`editado_en` no marcan filas intactas. Reemplazó a la edición inline (`calendario_fila_editar.php`, eliminado).
- **Eliminar** calendario y **eliminar registro** (Historial): solo admin, borrado lógico con `eliminado_en`. Un registro eliminado devuelve a "pendiente" la fila de calendario que había cumplido.
- **Cruce registro ↔ fila**: la fila guarda el id de Xplora (`repositorio_usuarios.id`) y la sesión el de `repositorio_usuarios_reporte`; se traducen por el nombre de usuario (`x.user = u.usuario`), que es igual en ambas porque el login valida contra Xplora por nombre.
- **Canales**: salen de `repositorio_locales_dtt2` (RETAIL, CANALES, OFICINA, BODEGA, EVENTOS, FERIAS); `insert_reporte_calendario.canal` pasó de ENUM a `VARCHAR(30)`.
- **Cruce de registros ya enviados**: el cruce al guardar solo ve calendarios que ya existen; `ep_calendario_cruzar_pendientes()` corre al abrir la pantalla de Calendario (antes de cerrar vencidos) y empareja filas pendientes con registros enviados antes de crear, editar o reactivar el calendario. `cumplido_en` = hora de envío del registro.
- **Reporte del calendario = reporte manual de Activaciones**: `ep_calendario_generar_ahora()` usa las mismas reglas que `reporte_guardar.php` (solo registros de Activaciones no ocupados por otro reporte activo, copia congelada completa en `snapshot`), con programadas = filas del calendario, ejecutadas = registros incluidos y los comentarios del calendario (columna `comentarios`, hasta 5, editable mientras esté activo). Sale el mismo PPTX; en la diapositiva de cumplimiento, el recuadro de la antigua foto del calendario (`Rectángulo 14`) ahora lo ocupa una tabla nativa de PowerPoint con las filas del calendario (`includes/ppt_calendario_tabla.php`: fecha, ciudad, punto de venta, promotor, estado; el canal va en el título). Siempre cabe en ese recuadro: una tabla hasta ~35 filas, dos lado a lado hasta ~70, y después "Y N FILAS MÁS". La tabla queda congelada en `snapshot.calendario`. La foto del calendario ya no existe en ningún flujo. El botón manual de Activaciones se ocultó en Reportes mensuales. Los primeros reportes generados por calendario guardaron solo ids en `snapshot`; `reporte_descargar.php` los rearma desde la base.
- **Collations mezcladas**: `insert_reporte_registro.pos_id` es `utf8mb4_unicode_ci` y `insert_reporte_calendario_fila.pos_id` es `utf8mb4_0900_ai_ci`; compararlas columna contra columna da "Illegal mix of collations" (hay que poner `COLLATE utf8mb4_unicode_ci`). Con un parámetro `?` no pasa.
- **Zona horaria**: `config.php` fija `America/Guayaquil`; antes PHP corría en UTC (5 h adelantado) y la base en hora local, lo que corría vencimientos y bloqueaba filas del día desde las 19:00.

## Optimización de carga (2026-09-29, medida con Playwright y CPU 4x más lenta)

- **Diagnóstico**: el servidor responde en 110–220 ms; lo pesado era el navegador (24 CSS/JS en cada pantalla, ~385 KB sin comprimir en la primera entrada) y, para promotores, la consulta a la vista `lvi_rutero` (~3,2 s) en su primera carga tras el login: esa era la "pantalla en blanco" después de la animación de bienvenida.
- **CSS/JS por módulo** (`index.php`, `$porVista`): cada módulo carga solo lo suyo, en el orden de siempre; `base`, `shell` y `wizard-fotos` (tiene `.hidden`, encabezado móvil y fondo del sidebar) van siempre. Un módulo que no esté en la lista carga todo. Al agregar un módulo nuevo, sumarlo a `$porVista`. Dependencias ocultas: Historial necesita `app.js` (botón "Slide") y `actividades.css` (tarjetas `ep-stat-*`); el modal del Calendario usa `actividades.css` (combos), `reportes.css` (`ep-rp-*`), `ppt-export.css` y `comentarios`. Los estilos de SweetAlert se movieron a `base.css`; `.ep-hist-badge` a `actividades.css`.
- **Calendario**: rutero, ciudades y PDV del modal ya no van en el HTML (701 KB → 30 KB); `calendario.js` los pide a `getters/calendario_catalogo.php` (gzip, caché 5 min) en segundo plano y los espera antes de abrir el modal.
- **Precarga**: `<script type="speculationrules">` en `index.php` pide el módulo al detener el mouse sobre `.ep-sidebar-nav a` (Chrome/Edge); nunca "Cerrar sesión".
- **Canales del promotor** (`ep_canales_usuario()` en `pdv_datos.php`): usa las tablas base del rutero en vez de `lvi_rutero` (mismo resultado verificado para los 3 promotores; 3,2 s → 0,24 s). No quedan consultas a `lvi_rutero` en el código.
- **Pendiente fuera del código**: nginx de Azure no comprime CSS/JS (solo HTML) ni les pone `Cache-Control`; activarlo bajaría ~70 % la primera descarga.

## Lista del Calendario rediseñada (2026-09-29, aprobada en Claude Design)

- Diseño aprobado: tableros "B+ · Tabla con indicadores" y "B+ · Celular" de https://claude.ai/artifact/K3j1JQCksXk8vZpB3jFqix (hecho con `impeccable`, estructura elegida por sorteo entre 3).
- `components/calendario/calendario.php` arma la lista; estilos en `assets/css/calendario-lista.css` y lógica en `assets/js/calendario-lista.js`, ambos cargados desde el propio componente (no desde `index.php`). `calendario.css` quedó solo para el modal.
- 3 indicadores de CALENDARIOS (Activos, Cerrado completo, Cerrado incompleto; completo = todas sus filas cumplidas). Cada uno es un interruptor que filtra; se recalculan con Período, Canal y búsqueda. Período por defecto: todas las fechas; filtra por la fecha de cada fila y el avance muestra el período ("x de y (de N)").
- Acción principal rellena según estado: Activo → Generar reporte; Cerrado incompleto → Reactivar; Cerrado completo → Descargar PPT (reactivar va en "Más"). Íconos secundarios se expanden con su nombre al pasar el mouse o con Tab; en celular llevan el nombre visible debajo. Las acciones reusan los manejadores de `calendario.js` (`.ep-cal-editar`, `.ep-cal-generar-ahora`, `.ep-cal-reactivar`, `.ep-cal-eliminar`).
- No existe "atrasado": una fila con fecha pasada en calendario abierto es pendiente.

## PPTX de Activaciones: comentarios a la derecha (2026-09-29)

- Solo Activaciones (spec `comentarios_derecha` en `ppt_activaciones.php`; Epson Day sigue igual): la tarjeta de comentarios (`Gráfico 3`, `CuadroTexto 4`, `CuadroTexto 5`) pasa a la columna derecha y ocupa desde la fila de tarjetas hasta el pie del embudo (`EP_PPT_COM_DER` en `ppt_embudo.php`). "Ingresos por Modelo" baja al lugar que dejó, debajo de "Detalle de Ventas" y alineada con ella (sin el corrimiento de -160000 que usa Epson Day). Diseño pedido por el usuario con una imagen; verificado exportando la diapositiva desde PowerPoint.

## Cerrar un calendario incompleto a mano: botón discreto y aviso con cifras (2026-10-02, SIN PROBAR en navegador)

- Un calendario activo siempre está incompleto (al cumplir todas sus filas se cierra solo), así que el botón "Generar reporte" siempre cerraba algo a medias y era el botón lleno y más llamativo de la fila. Ahora se llama "Cerrar y generar" y es secundario (`.ep-cl-pri-suave`: fondo blanco, borde lila); el botón lleno queda para lo que sí es la acción natural (Reactivar, Descargar PPT).
- La confirmación (`calendario.js`, `accionTarjeta()` ahora acepta una función que arma el cuadro al hacer clic) dice en una sola línea "X de Y filas cumplidas, faltan N" (leído del DOM en vivo) y qué entra en el reporte (solo las cumplidas; si son 0, que no se generará). Icono de advertencia, "Seguir esperando" es el botón con foco por defecto y el de confirmar es naranja ("Sí, cerrar incompleto").
- **Regla de textos para el usuario (pedida por el cliente, 02-10)**: los mensajes y avisos van cortos y directos. Nunca mencionar "Auditoría" en lo que ve un supervisor o promotor: no saben que existe (solo la ve el admin). El aviso de reactivar quedó en "Volverá a aceptar registros con un plazo nuevo desde hoy."

## PPT: portada única con logo, línea y título (2026-10-02, SIN ABRIR en PowerPoint)
- Pedido del cliente: ya no va la diapositiva de rombos ("EVENTO SIGLO XXI / AGOSTO 2026"); el formato nuevo es el logo Epson centrado con su línea turquesa y el título debajo.
- Plantillas cambiadas: activaciones, capacitaciones, colocacion-pop, epson-day, evento-ferias, exhibiciones, informe-fotografico. La diapositiva 2 ahora tiene fondo blanco, el mismo gráfico del logo (`image2.svg`) y el título centrado debajo; se quitó el fondo `image3.jpeg`. Copias originales en el scratchpad de la sesión (`ppt_backup`).
- `includes/ppt_motor.php`: con `unica` (por defecto) se omite la diapositiva 1 (solo logo) en presentación, relaciones, tipos y secciones, así no quedan dos portadas seguidas. Competencia lleva `portada_doble => true` y conserva sus dos diapositivas (su diseño es otro).
- Verificado: los 8 tipos generan con estructura válida (relaciones, XML y diapositivas); falta abrir uno en PowerPoint para ver el resultado.

## Supervisores no envían registros (2026-10-02)
- `guardar_registro.php` y `subir_foto.php` responden 403 a un supervisor; antes pasaba y el registro quedaba sin supervisor (solo lo veía el admin).
- Prueba de aislamiento (solo lectura, 3 supervisores): ningún registro lo ven dos supervisores, nadie aprueba/devuelve registros ajenos, nadie toca ni lista calendarios ajenos, y el calendario solo deja programar su equipo, sus canales y las ciudades del promotor. Todo OK.
- Hallazgo conocido: RACTATIANAMOROCHO-001 y -003 (Retail) sin supervisor porque Tatiana no tiene supervisor de retail (ver hallazgo de Tatiana).

## Calendario del supervisor: solo lo suyo (2026-10-02, SIN PROBAR en navegador)
- Canales: solo donde tiene promotores a su cargo (`ep_calendario_canales`); promotores: los de su equipo en ese canal. Ciudades y puntos son los del canal completo (ver "Sin filtro de ciudades").
- `ep_calendario_fila_validar` rechaza un promotor que no es de su equipo en ese canal (probado: Fabricio con un promotor ajeno). El admin no tiene estos límites.

## Calendario: el supervisor mostrado es el que aprueba en la app (2026-10-02, SIN PROBAR en navegador)
- Antes salía del rutero de Xplora (`repositorio_supervisores`) y podía no coincidir con quien aprueba (ej. Karina en Canales: Xplora decía Cristhian, la app Fabricio).
- `ep_calendario_supervisores_app($canal)` (`includes/calendario_datos.php`) da, por promotor, el supervisor de `ep_supervisor_de_fila`; lo usan `calendario_catalogo.php` (modal) y `ep_calendario_fila_validar` (lo que se guarda en la fila).
- El del rutero queda solo de respaldo si el promotor no tiene supervisor asignado en la app.

## Usuarios carga más rápido (2026-10-02, SIN PROBAR en navegador)
- La demora venía de `ep_usuarios_rutero()` (`includes/usuarios_datos.php`): recorre todo `rutero_pdv` con GROUP BY en cada carga (~2 s).
- Ahora guarda el resultado 5 min en `data/cache/usuarios_rutero.json` (~0,01 s con caché). Ciudad y canal del listado son solo informativos, así que hasta 5 min de retraso no afecta.

## Foto de perfil del menú: sin salto al pasar el mouse y zoom del mismo tamaño (2026-10-02, SIN PROBAR)
- `assets/css/shell.css`: el hover usaba `background:` y borraba la foto (ahora `background-color`), y el cursor ya no es la lupa (`pointer`); el avatar con foto (`[data-zoom]`) ya no sube 1px en hover ni se encoge al presionar.
- La vista ampliada (`.ep-zoom-foto img`) es un cuadrado fijo de 320px (máx. 80vw) con `object-fit: cover`, así todas las fotos se ven igual sin importar su proporción original.

## Sin filtro de ciudades: solo se limita por canal (2026-10-02, reunión con el cliente, SIN PROBAR en navegador)
- Decisión del cliente (`docs/grabaciones/02-10-2026 12.05.txt`): todo sale de `repositorio_locales_dtt2`; lo único limitante es el canal (Canales solo Canales, Retail solo Retail, los que ven ambas ven todo). El rutero cambia seguido (cobertura, vacaciones, permisos) y un filtro por ciudades bloquearía a quien cubre a otro.
- Se quitó el filtro por ciudades de la ruta que había hoy: `ep_pdv_listar()` ya no lo aplica (spinner de Actividades y `guardar_registro.php`), el modal del Calendario ofrece todas las ciudades y todos los puntos del canal, y `ep_calendario_fila_validar` ya no revisa la ciudad.
- Se mantiene: el supervisor solo ve sus canales y sus promotores (equipo en ese canal), y el supervisor que se muestra es el que aprueba en la app.
- Calendario: el Promotor se busca directo (lista sin ciudades); elegirlo solo calcula el supervisor, no fija ni limita la ciudad.

## Avisos del promotor: enviado o devuelto ya no es "pendiente" (2026-10-02, SIN PROBAR)
- La fila del calendario solo pasa a cumplida al APROBARSE el registro, así que antes el aviso seguía hasta entonces.
- `ep_avisos_promotor()` ahora excluye las filas que ya tienen un registro de Activaciones (mismo punto y día) en estado Pendiente o Devuelto.
- Enviado: el aviso desaparece. Devuelto: sale el aviso "Devuelto" con "Corregir y reenviar" (no se duplica con la fila). Reenviado: vuelve a Pendiente y no avisa.
- El panel se arma al cargar la página: el aviso se va en la siguiente carga, no en vivo.

## Calendario: no repetir promotor + punto de venta + día (2026-10-02, SIN PROBAR)
- Misma regla que ya tienen los promotores en Activaciones (`ep_registro_duplicado`): un promotor no puede tener el mismo punto el mismo día dos veces.
- `ep_calendario_fila_repetida()` revisa los calendarios activos (otros o el mismo, incluso entre las filas del envío); `getters/calendario_crear.php` y `calendario_editar.php` rechazan con el nombre del calendario que ya lo pide.
- Solo el servidor la valida; el modal muestra su mensaje. Calendarios cerrados o eliminados no cuentan.

## Calendario: buscar al promotor directo (2026-10-02, SIN PROBAR en navegador)

- La fila va **Fecha, Promotor, Ciudad, Punto de venta** (`calendario.php`); el combo de Promotor arranca activo con los del canal (solo el nombre, sin ciudades) y se puede buscar directo.
- Elegir promotor solo calcula el supervisor que aprueba. Ya no fija ni limita la ciudad (la versión que la ataba a su rutero se quitó tras la reunión del 02-10; ver "Sin filtro de ciudades"). Al editar un calendario guardado (`fila.cargar`) se aplica lo mismo.

## Reunión 28-09-2026: pendientes grandes (sin construir)

- Registrado en `docs/grabaciones/28-09-2026.txt`. Quedan pendientes: descarga consolidada de varios reportes, y el bloque grande de rol Supervisor como perfil de sesión propio (hoy el Calendario asume que el admin hace todo lo que haría un supervisor). El "informe fotográfico simple" ya se construyó, ver sección siguiente.

## Lógica "Informe Fotográfico Simple" (2026-09-30, SIN PROBAR en navegador)

- Séptima lógica base (`ep_logicas()` en `includes/actividades_datos.php`, id 7, plantilla `informe-fotografico`, `sin_estadisticas => true`): solo punto de venta (ya universal para toda actividad, vía `paso_pdv.php`) y fotos, sin ningún campo cuantitativo ni paso de "Datos de la actividad" (no pide tipo/fecha/hora). Pensada para que el admin cree con ella el botón "Exhibiciones Regulares" (Retail y Canales no llevan botones separados: el canal ya sale del punto de venta elegido, igual que en las demás actividades). **Competencia terminó siendo su propia lógica aparte** (id 8, plantilla `competencia`) porque necesita descripción por foto — ver la sección "Competencia" más abajo.
- **Fotos** (`includes/fotos_datos.php`): 1 obligatoria + 5 opcionales (`foto-1`..`foto-6`). El cliente pidió "sin mínimo, pueden subir 50.000 fotos"; se usó el mecanismo de casillas fijas que ya tiene todo el proyecto (obligatorias + opcionales) en vez de construir un uploader de galería abierta — es una simplificación consciente, más barata y sin tocar el motor de fotos compartido por todas las actividades. Si el cliente de verdad necesita un número no acotado de fotos, eso es una pieza nueva de UI a construir aparte.
- **Formulario** (`components/actividades/formularios/informe-fotografico.php`) y **estadísticas** (`components/actividades/estadisticas/informe-fotografico.php`): placeholders mínimos, mismo patrón que `colocacion-pop.php` (que tampoco tiene panel de estadísticas).
- **Sin cambios en `guardar_registro.php` ni en el detalle del Historial**: ambos ya eran genéricos por diseño (el guardado no exige campos cuantitativos si el tipo no los pide, y el detalle muestra fotos y comentarios sin importar el tipo) — la nueva lógica encaja sin tocar ninguno de los dos.
- Código de registro con prefijo propio `RFOT` (`ep_codigo_registro()`) e ícono `camera` (`ep_icono_tipo()`).
- **Bug encontrado y corregido de paso**: `tipoActividadActiva()` en `app.js` (usada al subir cada foto a Azure) adivinaba el tipo buscando palabras en el NOMBRE del botón ("exhibi", "pop", etc.) en vez de leer `data-plantilla` (que el botón ya trae). Con un botón llamado "Exhibiciones Regulares" esa función habría subido las fotos a la carpeta de Azure de `exhibiciones` en vez de `informe-fotografico`. Ahora lee `data-plantilla` directo.
- **Falta que el usuario haga, desde la app** (no requiere tocar la base, es uso normal del Constructor): crear el botón "Exhibiciones Regulares" desde "+ Nueva actividad" eligiendo la lógica "Informe Fotográfico Simple" (verificado el 01-10 con `SELECT` en `insert_reporte_actividad`: todavía no existe ningún botón con `logica = 'informe-fotografico'`). El botón "Competencia" ya está creado (id 7, activo).
- **Constructor corregido (2026-09-30, SIN PROBAR en navegador)**: "Lógica a replicar" listaba los BOTONES existentes y copiaba su lógica, así que una lógica nueva sin botón (Informe Fotográfico) no se podía elegir. Ahora el `<select>` y `window.EP_LOGICAS` salen de `ep_logicas()` (valor = plantilla), `app.js` envía `logica` (ya no `logica_id`) y `crear_actividad.php` valida contra `ep_logicas()`. `vista_previa_formulario.php` tenía una lista fija de plantillas sin `informe-fotografico`; ahora usa `array_keys(ep_logicas())`. Una lógica base nueva aparece sola en el Constructor.
- **Fotos extensibles (2026-09-30)**: además de las 6 casillas fijas (1 obligatoria + 5 opcionales), esta actividad muestra un botón "+ Agregar foto" que suma casillas opcionales sin límite (`ep_fotos_extensible()` en `fotos_datos.php`). Funciona sola porque el manejo de fotos ya es 100% por delegación de eventos a nivel `document` (`change`/`drag`/`drop` en `app.js`) — una casilla nueva no necesita re-inicializarse. `guardar_registro.php` valida cualquier `foto-N` extra que no esté en la lista fija, con la misma regla de ruta que las fijas.
- **PPT (2026-09-30)**: `recursos/ppt/informe-fotografico.pptx` es una copia de `exhibiciones.pptx` (mismo diseño corporativo, sin plantilla propia de Epson para esto todavía). Sin diapositiva de estadísticas: `ep_ppt_registro()` en `ppt_motor.php` ahora vuelve OPCIONAL la clave `'stats'` del spec (si no está, no se clona esa diapositiva ni se llama la barra del promotor; el resto de actividades no cambia, todas siguen trayendo `'stats'`). `includes/ppt_informe_fotografico.php` solo trae `'fotos'` (sin `'orden'` fijo a propósito): cada diapositiva de fotos pone el punto de venta como título solo (mecanismo compartido de `ep_ppt_slide_fotos()`), y `ep_ppt_registro()` ahora usa las fotos reales del registro cuando el spec no trae `'orden'`, así que **sí entran todas las fotos que el promotor haya subido**, incluidas las agregadas con "+ Agregar foto" — sin el límite de 6 que tuvo al principio.

## Correcciones de "Ingresos por Modelo" (2026-09-29)

- **Desborde de montos grandes**: la fila de dinero usa una columna de valor flexible (`.ep-venta-fila-dinero`, ancho `auto`) en vez de los 30px fijos de unidades.
- **No era en vivo**: el campo de precio (`.ep-modelo-precio`) no disparaba `actualizarTotal()`; ahora sí, en cada tecla.
- **Precio del campo**: es texto (no `number`, para no chocar con el restrictor global de 3 dígitos enteros); `epLimpiarPrecio()` permite hasta 3 enteros y 2 decimales, con un signo "$" visual aparte del valor (`.ep-modelo-precio-wrap`).
- **PPTX**: "Ingresos por Modelo" ya sale en Activaciones y Epson Day. No se tocó la plantilla oficial de Epson: se clona el título y las filas de "Detalle de Ventas" (`ep_ppt_clonar_y_mover()` en `ppt_base.php`) y se reubican 3055801 EMU a la derecha, en la franja que la plantilla deja libre. El clon del grupo fondo+barra debe insertarse ANTES que el del número (si no, el fondo —siempre a ancho completo— tapa el monto); el número además pierde el autoajuste (`ep_ppt_sin_autoajuste()`) y se ensancha hacia la izquierda porque su caja original solo cabía "5" o "100", no un monto en dólares.

## Tres detalles de "Ingresos por Modelo" (2026-09-29)

- **Doble aro al enfocar el precio**: el campo de precio tenía su propio `outline` (regla global de `.ep-input:focus`) además del anillo del contenedor `.ep-modelo-precio-wrap`; se anuló el del campo (`.ep-modelo-precio:focus { outline: none; }`).
- **Búsqueda de modelo lenta**: `getters/repositorio_productos_buscar.php` ya no filtra por `q` en el servidor (esa consulta de por sí solo tardaba ~100ms, con índice en `sku`; la demora percibida era la ida y vuelta al servidor por cada letra). Ahora trae el catálogo completo una sola vez (hoy 20 SKU activos) y `app.js` (`epObtenerCatalogoModelos()`, caché compartida entre Activaciones/Epson Day/Evento o Ferias) filtra en el navegador; el debounce bajó de 250ms a 80ms porque ya no depende de la red.
- **Sin tarjeta en el PPTX**: al panel "Ingresos por Modelo" le faltaba el fondo con borde/sombra que sí tiene "Detalle de Ventas" (en la plantilla ese fondo es una imagen, `Gráfico 112` en Activaciones / `Gráfico 46` en Epson Day, no una forma nativa). Se agregó `'card' => 'Gráfico 112'` (o 46) a la especificación y `ep_ppt_ingresos_modelo()` la clona primero (antes del título y las filas) para que quede de fondo.

## Ajuste final de espacio "Ingresos por Modelo" (2026-09-29)

- **Tarjetas pegadas**: con las dos tarjetas a ancho completo (3209982 EMU) no cabía un espacio real entre "Detalle de Ventas" e "Ingresos por Modelo". Se corrió la tarjeta original de "Detalle de Ventas" (imagen de fondo, título y cada fila) 90000 EMU a la izquierda con `ep_ppt_mover_x()` (nueva, en `ppt_base.php`) y la de "Ingresos" se angostó un poco (`ep_ppt_ancho()`), dejando 60000 EMU de espacio real entre ambas sin salirse del borde de la diapositiva.
- **Monto encima de la barra**: en vez de ensanchar la caja del número clonado hacia la izquierda (que fallaba en barras casi al 100%), ahora se reposiciona desde cero igual que hace "Detalle de Ventas": la barra de ingresos usa un `$largoMax` más corto (1400000 EMU, contra 2051998 de Detalle de Ventas) y el número se coloca siempre justo después del final de esa barra corta, nunca según cuánto esté rellena. Verificado en PowerPoint (render de la diapositiva de estadísticas): sin superposición en L3210 al 100%, y con espacio visible entre ambas tarjetas.
- **Filas vacías (2026-09-29)**: si un modelo no trae dato (menos de 5 modelos, o menos de 5 con precio), esa fila ya no se clona ni queda visible como barra vacía; en "Detalle de Ventas" también se quita la fila completa (texto, número, barra y fondo) en vez de dejarla en blanco.
- **"Ingresos por Modelo" fijo, "Detalle de Ventas" se corre solo (2026-09-29)**: `$deltaX` (desplazamiento del clon de "Ingresos") ya no se calcula sumando `$corrimiento`, se calcula RESTÁNDOLO a partir de un destino fijo (`$ingresosLeftFijo`), así "Ingresos por Modelo" siempre queda en el mismo lugar sin importar cuánto se mueva "Detalle de Ventas" a la izquierda con `$corrimiento`. Ojo: la posición del número clonado usa `6415448 + $ingresosLeftFijo + ...` (el `6415448` es la base original del grupo de barra en la plantilla); quitarlo por error deja el monto flotando sobre la tarjeta de SKU mayor/menor.
- **Segundo ajuste de espacio (2026-09-29)**: `$corrimiento` pasó de -120000 a -160000 (Detalle de Ventas un poco más a la izquierda) e `$ingresosLeftFijo` de 3089982 a 3129982 (Ingresos por Modelo se corrió 40000 EMU a la derecha para abrir más separación entre ambas tarjetas, no solo lo que ganaba por el corrimiento de la otra). Ambos valores se tocan juntos: si se sube uno sin el otro el hueco entre tarjetas se cierra o se abre de más.

## Foto de perfil del promotor en el PPT (2026-09-30, SIN PROBAR en navegador)

- Ya existía toda la infraestructura de foto de perfil (módulo "Usuarios", columna `foto` en `repositorio_usuarios_reporte`, subida a Azure vía `getters/usuario_foto.php`, `ep_usuario_foto_url()` en `includes/usuarios_datos.php`) — solo faltaba conectarla al armado del PPT.
- `ep_registros_datos()`/`ep_registro_armar()` en `registros_datos.php` ahora traen `u.foto` (con el mismo chequeo defensivo `ep_usuarios_tiene_foto()` que ya usa el módulo Usuarios) y arman `$reg['promotor_foto_url']` por registro.
- `ep_ppt_abrir()` descarga esa URL junto con las demás fotos del registro (mismo mecanismo de descarga en paralelo, sin duplicar código). `ep_ppt_barra_promotor()` cambió de firma (`&$ctx, &$slide` en vez de `$dom, $xp` sueltos, único call site, dentro de `ep_ppt_registro()`) para poder insertar la imagen: si hay foto descargada la pone en el cuadro `Gráfico 19` de la plantilla (recortada tipo "cover", como las demás fotos — no "contenida" con márgenes); si no hay, se comporta exactamente igual que antes (`ep_ppt_quitar()`, cuadro vacío). El espacio/nombre de forma para la foto ya estaba reservado en el mapa `$n['foto']` desde el principio, solo faltaba la lógica de inserción.
- No rompe nada existente: ningún generador cambia su comportamiento hoy salvo que el promotor de ese registro ya tenga foto de perfil subida (confirmado que "admin" ya tiene una).

## Pendiente a seguir: bloque grande de la reunión 28-09-2026

Del módulo **Calendario de Activaciones** (ver sección arriba) ya está construido y con su diseño real aplicado (escritorio y móvil, mockup aprobado por el usuario en la herramienta de Diseño de Claude): crear/listar calendarios, cascada Ciudad→Promotor→Punto de venta→Supervisor contra el rutero real, cruce automático de registros, cierre y generación de reporte (manual o al vencer el plazo), reactivación. Falta que el usuario lo pruebe en navegador real.

Del resto de lo hablado en `docs/grabaciones/28-09-2026.txt` (ver también "Reunión 28-09-2026: pendientes grandes"):
1. ~~"Informe fotográfico simple" y "Competencia"~~ → ambas lógicas construidas (ver "Lógica 'Competencia'" más abajo, con prueba en vivo), con plantilla PPT propia cada una (Competencia con la oficial de Epson). El botón "Competencia" ya existe y está activo; falta que el admin cree "Exhibiciones Regulares" desde el Constructor eligiendo la lógica "Informe Fotográfico Simple" (ver "PENDIENTES PARA TERMINAR" más abajo para el detalle actualizado).
2. ~~Descarga consolidada de varios registros en uno solo~~ → construida, ver "Descarga consolidada de registros (2026-09-30)".
3. ~~Rol Supervisor~~ → construido de punta a punta, ver "Roles, categorías y aprobación de registros (2026-10-02)" más abajo (incluye un supervisor-only approval workflow que no se había pedido originalmente, decidido en conversación posterior con el cliente).
4. ~~Auditoría de reactivaciones~~ → construida como módulo propio, ver "Auditoría (2026-09-29)".
5. ~~Módulo de gestión de usuarios~~ → construido, ver "Usuarios (2026-09-30)".

## Descarga consolidada de registros (2026-09-30, SIN PROBAR en navegador)

- **Alcance real**: no es "varios reportes mensuales en uno" (eso implicaría fusionar plantillas .pptx distintas, inviable con el motor actual de clonado de diapositivas), sino varios **registros individuales del mismo tipo de actividad** seleccionados a mano en Historial, descargados como un solo PPTX — el caso de uso real que se pidió ("check a un lado de cada registro").
- **Historial** (`components/historial/filas.php`): checkbox por fila, solo admin (`ep-h2-check`, con `data-codigo`/`data-tipo`). Barra flotante (`components/historial/historial.php`, `#epH2Multi`) con contador y botón "Descargar consolidado"; se deshabilita sola si la selección mezcla tipos de actividad distintos. `assets/js/historial.js` mantiene la selección en memoria y la reaplica después de cada refresco en vivo (cada 3s) para no perderla.
- **Backend** (`getters/registros_ppt_multiple.php`, solo admin): recibe códigos de registro, exige que todos sean del mismo tipo y que ese tipo tenga generador (`ep_ppt_generador()`), y llama al generador normal con `'solo_registro' => true` — el mismo mecanismo que ya soporta varios registros a la vez (usado hoy por Reportes mensuales), solo que sin guardar nada ni pedir mes. Tope de 100 registros por descarga.
- **No toca nada existente**: reusa `ep_ppt_generador()` y los generadores tal cual están; no se tocó ningún archivo de `ppt_*.php` para esto.

## Auditoría (2026-09-29, tabla ya creada, SIN PROBAR en navegador)

- **Alcance**: la aprobación de registros por supervisor que se oye en la grabación 28-09 fue conversación interna del cliente, NO un requisito de la app. Lo pedido era poder responder "quién autorizó, cuándo y con qué usuario".
- **Tabla** `insert_reporte_auditoria` (solo se agrega, sin borrado lógico a propósito): `usuario_id` (NULL = Sistema), `usuario_nombre` congelado, `accion`, `entidad` + `entidad_id`, `resumen` legible, `detalle` JSON (`[{campo, antes, despues}]` o `[{campo, valor}]`), `ip`, `created_at`.
- **Código**: `includes/auditoria_datos.php` (`ep_auditar()`, `ep_auditoria_cambio()`, `ep_auditoria_dato()`, `ep_auditoria_listar()`, `ep_auditoria_acciones()`). `ep_auditar()` nunca corta la acción: si la tabla no existe o falla, solo deja `error_log`.
- **Qué se registra** (desde cada getter, después de que la acción salió bien): calendario crear / editar fila (antes→después de ciudad, punto de venta, promotor, supervisor; solo campos que cambiaron) / comentarios / generar ahora / reactivar / eliminar; cierre automático al vencer (usuario Sistema, en `ep_calendario_verificar_vencidos()`); eliminar registro; crear y eliminar reporte mensual; crear, activar y desactivar actividad. En `calendario_editar.php` la auditoría va dentro de la misma transacción.
- **Pantalla** `index.php?vista=auditoria` (solo admin, rediseñada 2026-09-30, SIN PROBAR en navegador; mockup en `docs/diseno/auditoria-rediseno.html`): `components/auditoria/auditoria.php` + `assets/css/auditoria.css` + `assets/js/auditoria.js`. Una línea por movimiento (hora, icono por tipo crea/cambio/elimina/sistema, resumen, usuario · acción, badge "N cambios"), días con encabezado fijo, y panel de detalle a la derecha (quién, fecha completa, antes/después lado a lado, sección, número, IP); en móvil el detalle es pantalla completa con botón Movimientos. Filtros: buscador, combo nativo de Usuario, chips por tipo con conteo y periodo Todo/Hoy/Semana/Mes (se quitó el rango de fechas y el combo de Acción). El tipo se deriva de `accion` (`_crear`, `_eliminar`, sin usuario = Sistema, resto = Cambio). Muestra los últimos `EP_AUDITORIA_LIMITE` (1000); el filtro corre en el navegador. Ya no carga `filtros`/`historial` (css y js) en esa vista.
- Las columnas sueltas `editado_por/en` y `reactivado_por/en` del Calendario siguen escribiéndose igual; la auditoría las complementa con el historial completo.
- **Por qué una tabla nueva y no solo consultar las existentes**: las tablas guardan el estado actual y sobrescriben al cambiar. Con ellas se sabe quién creó algo y quién hizo el ÚLTIMO cambio, pero no qué valor había antes, ni el historial de varios cambios, ni QUIÉN eliminó (solo `eliminado_en`, sin usuario), ni quién activó/desactivó una actividad, ni si un cierre fue automático o manual. Eso solo existe si se anota en el momento, por eso la bitácora.

### Pendiente para probar (2026-09-30)

1. ~~Crear la tabla~~ → confirmado el 30-09 con `SHOW TABLES` que `insert_reporte_auditoria` ya existe en la base.
2. Como admin, hacer y revisar que cada acción aparezca bajo "Hoy" con nombre, hora y detalle:
   - Calendario: crear uno; editar una fila (cambiar punto de venta o promotor → debe salir antes tachado → después); cambiar comentarios; "Generar ahora"; reactivar; eliminar.
   - Historial: eliminar un registro (datos del registro en el detalle, icono rojo).
   - Reportes mensuales: crear uno y eliminarlo ("Registros liberados").
   - Constructor: crear una actividad, desactivarla y activarla.
   - Cierre automático: un calendario con plazo vencido, al abrir la pantalla de Calendario, debe dejar un movimiento del usuario "Sistema" (icono gris).
3. **Pantalla**: probar búsqueda, combo Usuario, chips de tipo y Hoy/Semana/Mes; que el contador de cada día, los conteos de los chips y el resumen de arriba se vean bien; abrir el detalle de cada tipo de movimiento (con antes/después y sin él) y la vista en celular (detalle a pantalla completa, sin desborde lateral).
4. **Editar sin cambios reales**: guardar una edición de calendario donde la fila queda igual no debe crear movimiento (solo se anotan campos que cambiaron).
5. Si todo pasa, quitar "SIN PROBAR" del título de esta sección.

## Usuarios (2026-09-30, SIN PROBAR contra la base real, solo con datos de prueba)

- **Pantalla** `index.php?vista=usuarios` (solo admin, rediseño aprobado en Claude Design; mockups en `docs/diseno/gestion-usuarios.html`): `components/usuarios/usuarios.php` + `assets/css/usuarios.css` + `assets/js/usuarios.js`. Lista con buscador, filtros Rol/Estado y panel lateral (en móvil pantalla completa). La lista se pinta en el navegador desde un JSON.
- **Datos**: `includes/usuarios_datos.php` sobre `repositorio_usuarios_reporte`. Ciudad y canal son solo informativos y salen del rutero activo (`rutero_pdv` + `repositorio_locales_dtt2`, mismo criterio del 20 % de `pdv_datos.php`): ciudad dominante, canal Retail, Canales o "Retail y Canales"; admin = "No aplica", promotor sin rutero = "Sin rutero".
- **Qué se edita**: correo y rol. Nombre y ciudad/canal bloqueados porque el nombre viene de Xplora y el Calendario cruza por el usuario de Xplora (el correo es solo de contacto, el login es `usuario` y no cambia). Nadie puede cambiar su propio rol ni desactivarse.
- **Clave**: "Cambiar clave" pide nueva y confirmación (ojito para verlas, botón "Generar una"), mínimo `EP_CLAVE_MINIMA` (6), texto plano como el resto. Cambiar la clave de otro cierra su sesión abierta y libera el bloqueo por intentos.
- **Desactivar/Reactivar**: `status` activo/inactivo; al desactivar se borra `sesion_token` (lo expulsa). Reactivar lo deja entrar con su clave de siempre.
- **Nuevo usuario**: pide usuario de ingreso, nombre, correo, rol y clave. Si el rol es Promotor, el usuario debe existir activo en Xplora y el nombre se toma de ahí; si es Admin, se escribe el nombre.
- **Foto**: requiere la columna nueva `foto` (SQL abajo, lo corre el usuario en HeidiSQL). Se sube a Azure en `Usuarios/<usuario>.jpg (una por usuario, subir otra la reemplaza)` reducida a 400 px (`getters/usuario_foto.php`) y se muestra en la lista y en el avatar del menú lateral (`ep_usuario_foto_actual()`). Sin la columna, todo lo demás funciona y subir foto responde "Falta la columna foto". La foto elegida solo se previsualiza en el panel y se sube al pulsar Guardar cambios (Cancelar o cerrar la descarta); tocar la foto abre una vista ampliada compartida (`assets/js/zoom-foto.js` + estilos `.ep-zoom-foto` en `shell.css`, cargada en todas las vistas; `window.epZoomFoto(url, nombre)`). También se abre al tocar el avatar con foto del menú lateral (en vez de plegar el menú). Esc, clic en el fondo o la X la cierran.
- **Getters** (POST, solo admin, responden JSON): `usuario_guardar.php` (crear sin id, editar correo/rol con id), `usuario_clave.php`, `usuario_estado.php`, `usuario_foto.php`.
- **Auditoría**: cada acción queda anotada (`usuario_crear/editar/clave/foto/activar/desactivar`); la clave nunca se guarda en el detalle.
- ~~SQL pendiente~~: la columna `foto` ya existe en `repositorio_usuarios_reporte` (verificado el 30-09 con SHOW COLUMNS).

## Lógica "Competencia" (2026-09-30, probada en vivo en local contra la base real)

- **Por qué es lógica propia y no Informe Fotográfico**: el PPTX oficial de Epson (`epson datos/.../RETAIL/INFORME FOTOGRAFICO DE COMPETENCIA RETAIL - AGOSTO 2026.pptx`) tiene encabezado fijo "REPORTE COMPETENCIA MES - CANAL" y **un texto por foto** describiendo el hallazgo. Exhibiciones regulares sí calza con Informe Fotográfico Simple (solo punto de venta + fotos).
- **Lógica** id 8, plantilla `competencia`, `sin_estadisticas` (`ep_logicas()`). Fotos (`fotos_datos.php`): `foto-1` obligatoria + `foto-2`/`foto-3` opcionales + "Agregar foto" sin límite (`ep_fotos_extensible()`); `ep_fotos_con_descripcion()` y `EP_FOTO_DESCRIPCION_MAX` (200); `ep_foto_extra_label()` da la etiqueta de las casillas extra.
- **Descripción por foto**: cuadro `.ep-foto-descripcion` en cada casilla (`paso_evidencia.php`, también en las que crea "Agregar foto"). En el asistente (móvil y escritorio, mismo overlay en `actividades.php`) va bajo el visor (`#epWizardDescripcion`) y se copia a la casilla; al cargar una foto el asistente NO avanza solo, enfoca la descripción; "Siguiente" no avanza con foto sin descripción; la lista lateral de escritorio dice "Falta descripción". Botón nuevo "Agregar otra foto" dentro del asistente (hacía falta: en celular el asistente es la única vía para subir fotos). Al quitar una foto se borra su descripción; al cambiarla se conserva.
- **Validación**: al enviar, cada foto subida necesita descripción (`validarRegistroActivo()`); si solo faltan descripciones se abre el asistente en la primera foto sin texto. El servidor (`guardar_registro.php`) lo vuelve a exigir (422) y guarda `descripciones` `{casilla: texto}` dentro del JSON `valores`, **sin cambio de esquema**. `ep_registro_armar()` pega `descripcion` a cada foto.
- **Historial**: la miniatura y el visor muestran la descripción en vez de la etiqueta de la casilla.
- **PPT**: plantilla `recursos/ppt/competencia.pptx` (421 KB) armada desde el archivo real con un script: portada, título en 2 párrafos (título / mes) y 1 diapositiva de contenido con encabezado, `Foto 1-3` (marcadores) y `Pie 1-3` (descripción en negrita + "PUNTO DE VENTA - CIUDAD"). Se quitó el logo de la esquina porque en el original cambia según la marca de competencia (HP, Canon, Brother). `includes/ppt_competencia.php`; el motor ganó opciones de spec que las demás actividades no usan: `portada_titulo`, `titulo_fijo`, `fotos.titulo_texto`, `fotos.pies` + `fotos.pie_texto` (`ep_ppt_pies_fotos()`: el pie sigue la posición y el ancho de su foto, también con 2 o 1 foto).
- **Arreglos de paso**: `ep_fotos_desde_json()` solo rearmaba las casillas fijas, así que las fotos de "Agregar foto" (informe fotográfico) se guardaban pero no aparecían en Historial ni en el PPT; ahora se agregan. `subir_foto.php` mandaba `informe-fotografico` a la carpeta `General` de Azure; ahora `InformeFotografico` y `Competencia`. `reportes.php` conoce los nombres de ambas lógicas.
- **Prueba en vivo (30-09)**: admin creó el botón "Competencia" desde el Constructor; Pablo Castelo envió `RCOMPABLOCASTELO-001` (2 fotos con descripción, punto ADVANCE - 9 DE OCTUBRE); se vio en Historial y su PPT se abrió en PowerPoint sin reparación. Ese registro es de prueba: eliminarlo desde Historial.
- **Pendiente posible (no pedido)**: agrupar por marca de competencia con su logo, como el PPT original; requeriría elegir la marca en cada foto.

## Ajustes del asistente de fotos y nombres de Activaciones (2026-09-30, probado en local)

- **Nombres de las fotos = texto oficial de Epson, textual**: cada casilla dice lo mismo que el cuadro de foto de la plantilla oficial ("DENTRO DE ESTE CUADRADO LA FOTO N QUE CORRESPONDE A ..."), sin resumir ni parafrasear (pedido explícito del usuario). Solo se agrega "(1)", "(2)"... cuando varias casillas piden lo mismo. Corregidos: Activaciones y Evento o Ferias ("Promotor junto a su stand...", "Promotor ejecutando una atención o interacción con los clientes") y Epson Day ("...en las redes sociales"). Capacitaciones, Exhibiciones y Colocación de POP ya coincidían. Se mantienen 3 obligatorias + 3 opcionales en Activaciones. Al agregar una actividad o casilla nueva, copiar el texto del cuadro de su plantilla en `recursos/ppt/` o en `epson datos/.../FORMATOS FOTOGRAFICOS 2026/`.
- **Etiqueta "Nuevo"** de los botones de actividad: oculta solo en pantalla (`base.css`); el dato sigue en `insert_reporte_actividad.badge`.
- **Modal del asistente** ya no se recorta: `.ep-wizard-main-grid` y `.ep-wizard-body` con `min-height: 0` (el cuerpo hace scroll dentro de la ventana), los hijos del cuerpo no se encogen, el visor se achica en pantallas bajas (escritorio `min(300px, 34vh)`, celular `42dvh`) y al cambiar de foto el cuerpo vuelve arriba.
- **Agregar / quitar casillas** (actividades extensibles: Informe Fotográfico y Competencia): "Agregar otra foto" en la lista de requerimientos (escritorio) y bajo la tira (celular), más el botón de la página. Las casillas sumadas llevan `data-extra` y se pueden quitar: con la X en la esquina de la casilla o con "Quitar esta casilla" en el asistente (`quitarCasillaExtra()` en `app.js`). Las fijas no se quitan. Una casilla vacía nunca se envía ni deja hueco en el PPTX.

## "Descargar Slide" del Historial para lógicas nuevas (2026-09-30, probado en local)

- `app.js` tenía una lista fija de tipos con PPT (activaciones, capacitaciones, epson-day, evento-ferias, exhibiciones) y para cualquier otro mostraba "El formato de presentación de esta actividad todavía no está disponible" sin consultar al servidor; por eso Informe Fotográfico y Competencia no descargaban aunque su generador existe. Ahora el botón siempre llama a `getters/registro_ppt.php` y el servidor decide (`ep_ppt_generador()`); si el tipo no tiene formato, responde ese mismo aviso. Probado: el registro de Competencia descarga su PPTX desde Historial.

## PENDIENTES PARA TERMINAR (verificado el 2026-10-02 contra la base, solo lectura, y contra Azure)

**Ya resuelto (comprobado):** botón "Exhibiciones Regulares" creado (id 8, lógica `informe-fotografico`); datos de prueba de Competencia y POP ya no existen en la base; columnas de Avisos, foto, categorías y aprobación existen; los 23 promotores activos tienen supervisor asignado; Azure (desarrollo) tiene la última versión (archivos idénticos a los locales); rol Supervisor y aprobación construidos y ya usados (16 aprobaciones, 2 devoluciones con reenvío).

**Por construir:**
1. ~~Exhibiciones regulares agrupado por ciudad, Guayaquil primero~~ → construido 2026-10-02, ver "Orden por ciudad en Exhibiciones Regulares" más abajo.
2. (Opcional, no pedido) Competencia agrupada por marca con su logo (HP/Canon/Brother).

**Decisión pendiente del cliente (hallazgo del 02-10, solo lectura):** Tatiana Morocho tiene `categorias = 'todas'` (el correo del cliente la lista como "canales y retail") y `supervisor_canales_id = 5` (Fabricio), pero `supervisor_retail_id` vacío: el correo no la incluye ni en la lista de Cristhian ni en la de Andrea. Verificados los otros 22 promotores contra el correo (categorías y supervisores coinciden uno por uno; Karina y Gabriela con Fabricio+Cristhian, Jonathan con Fabricio+Andrea; Fabricio `todas`, Cristhian y Andrea `retail`, admins ven todo). Efecto: si Tatiana envía un registro en un punto RETAIL, `ep_supervisor_asignado()` devuelve NULL y solo el admin lo ve en Aprobaciones. Falta que el cliente diga si su supervisor de retail es Cristhian o Andrea (o si su categoría debería ser `canales`); el cambio lo hace el usuario desde Usuarios → "Ruta del promotor".

**Fuera del código:**
3. nginx de Azure: sigue sin compresión ni `Cache-Control` para CSS/JS (comprobado con los encabezados de `app.js`, 94 KB sin comprimir).
4. (Cosmético) los botones Competencia (id 7) y Exhibiciones Regulares (id 8) guardan la etiqueta "Nuevo"; está oculta en pantalla. Para limpiarla: `UPDATE insert_reporte_actividad SET badge = NULL WHERE id IN (7, 8);` (lo corre el usuario).

**Sin uso real todavía (según Auditoría y tablas al 02-10, conviene probarlo en navegador):** registros reales de Competencia, Exhibiciones Regulares, POP, Capacitaciones, Epson Day, Evento o Ferias y Exhibiciones que Inspiran (solo hay Activaciones); reportes mensuales armados a mano y su borrado; descarga consolidada desde Historial; editar filas y "Generar ahora" del Calendario, y su cierre al vencer; eliminar registros; activar/desactivar actividades; crear usuarios, editar correo/rol, cambiar clave y ruta del promotor. Ya usado de verdad: aprobar/devolver/corregir, crear/reactivar/eliminar calendario, cierre al completarse, fotos de usuarios, Avisos.

## Colocación de POP rehecha (2026-10-01, probada de punta a punta en local contra la base real)

- **Por qué**: el formulario viejo tenía 6 materiales fijos y campaña "Mundial" (el informe real de agosto es BTS con otros materiales), sus entregas a puntos de venta **nunca se enviaban** y el envío buscaba clases que no existían (`.ep-pop-nombre`...), así que POP no guardaba nada de sus tablas. No había registros ni reportes de POP en la base, así que no hubo que migrar nada.
- **Modelo (decidido con el usuario)**: **un registro de POP = un punto de venta** (se elige arriba, de la base, como todas; se quitó la excepción que dejaba POP sin punto en `app.js` y `guardar_registro.php`). El registro guarda `campana` (texto libre en mayúsculas) y `pop_entregas` `[{material, cantidad}]` (material libre en mayúsculas), en el JSON `valores`, sin cambio de esquema. Fotos: las 3 oficiales de "Correcta implementación del material POP".
- **Reporte mensual**: en el paso final, la tabla "POP recibido en bodega" (`components/reportes/paso_final.php` + `assets/js/reportes-pop.js`) agrupa por campaña + material lo entregado en los registros elegidos; **Canales** y **Retail** se suman solos según el canal del punto de venta (RETAIL = Retail, cualquier otro = Canales) y el admin escribe solo **Bodega** (obligatoria por fila); Disponible = Bodega − Canales − Retail. Se congela en `snapshot.pop_bodega` (clave `CAMPAÑA|MATERIAL`) y `reporte_descargar.php` la pasa al generador.
- **PPT** (`includes/ppt_colocacion_pop.php`, plantilla `recursos/ppt/colocacion-pop.pptx` = formato oficial con la imagen de ejemplo de las tablas cambiada por el recuadro "Area tabla"): diapositivas fijas "POP RECIBIDO" (material, campaña, tipo UNIDADES, bodega, canales, retail, disponible) y "DETALLE POP RECIBIDO" (material, campaña, PDV, ciudad, cantidad, subtotal en negrita por material), repartidas en 1 o 2 columnas por diapositiva y en varias diapositivas si hace falta (como el informe de Canales); un material partido repite su nombre. Luego una diapositiva de fotos por registro con el punto de venta de título. La descarga de un solo registro (Historial) solo trae sus fotos (sin bodega no hay tabla).
- **Tablas nativas genéricas**: `includes/ppt_tabla.php` (franja de título, cabecera azul, cuerpo sin bordes). Toda celda lleva `endParaRPr` con tamaño: sin eso PowerPoint usa 18 pt en celdas vacías y las filas crecen y se salen de la diapositiva.
- **Historial y modal de Reportes** muestran campaña + material/cantidad (antes leían `pop_materiales`, que ya no existe).
- **Bug arreglado de paso (todas las actividades)**: el validador del envío exigía las líneas de comentario que arma `comentarios.js` (no tienen id), aunque el aviso dice "Solo los comentarios son opcionales"; ahora se saltan (`.ep-coment-item`).
- **Prueba en vivo (01-10)**: Pablo Castelo envió `RPOPPABLOCASTELO-001` (punto ADVANCE - 9 DE OCTUBRE, Retail; campaña BTS; 2 materiales; 3 fotos). Como admin se armó el reporte mensual "PRUEBA CLAUDE POP": la tabla de bodega sumó Retail sola, no dejó guardar sin bodega, calculó Disponible y el PPTX descargado (5 diapositivas) abrió bien en PowerPoint. Ambos son datos de prueba (ver pendientes).

## Foto de perfil circular en los PPTX (2026-10-01, SIN PROBAR en PowerPoint)

- La foto del promotor en la barra azul sale redonda en todas las plantillas: `ep_ppt_barra_promotor()` (`ppt_motor.php`) es el único camino y llama a `ep_ppt_slide_imagen(..., false, true)`; `ep_ppt_foto()` (`ppt_base.php`) con `$circular` deja un cuadrado centrado del tamaño del lado menor del marcador, recorta la imagen al centro y usa `prstGeom ellipse` con filo negro fino de 0,75 pt. Las fotos de evidencia siguen rectangulares.

## Avisos al promotor por el Calendario (2026-10-01, SIN PROBAR contra la base real)

- **Dinámica**: el promotor ve una campana ("Avisos", con contador) en el menú lateral y en el encabezado móvil; el admin no la ve. Los avisos no se guardan, se calculan al abrir cada página (`ep_avisos_promotor()` en `includes/avisos_datos.php`): una entrada por calendario activo no vencido con las filas `pendiente` del promotor (la fila guarda el id de Xplora; se cruza por `repositorio_usuarios.user = $_SESSION['usuario']`). Al enviar el registro la fila pasa a cumplida y desaparece sola; si el admin elimina el calendario o cambia el promotor, desaparece o aparece como nueva para quien corresponda. Sin nombre de calendario se usa "Activaciones <canal>", igual que `ep_calendario_nombre()`.
- **"Último día"** = fecha de `vence_en` del calendario (activado_en + plazo_dias que puso el admin). Con 1 día o menos (hoy o mañana) el calendario es urgente: sube al principio, se pinta en rojo y el contador de la campana también.
- **"Nueva"**: fila creada o reasignada (`GREATEST(created_at, editado_en)`) después de `avisos_visto_en` del usuario. Abrir el panel o el cartel marca visto (`getters/avisos_visto.php`).
- **Campana que suena**: mientras haya avisos nuevos, o un último día cerca que no abrió hoy (localStorage `ep_aviso_abierto_<id>`), el botón de Avisos se mueve (campana que repica cada 2,6 s), el contador pulsa con un anillo y el botón se resalta; se apaga solo al abrir el panel (`.llamando` en `avisos.css`, respeta `prefers-reduced-motion`). **Cartel al entrar** (`assets/js/avisos.js`, SweetAlert): "Revisa la campana de Avisos..." una vez por novedad y lo urgente una vez al día por sesión (sessionStorage `ep_aviso_nuevos`/`ep_aviso_urg`); el cartel NO marca visto, solo abrir el panel o el botón "Ver avisos". Sin envío de correo (decisión del usuario).
- **Panel**: junto al botón en escritorio, hoja inferior en móvil (`layout/avisos.php`, `assets/css/avisos.css`); botón "Registrar una activación" lleva a Actividades. `avisos` carga en todas las vistas (hoja y script en `index.php`).
- **SQL pendiente (lo corre el usuario en HeidiSQL, Claude no puede)**: `ALTER TABLE repositorio_usuarios_reporte ADD COLUMN avisos_visto_en DATETIME NULL AFTER ultima_actividad;` Sin la columna no hay "Nueva" ni cartel de nuevas, pero el panel, el contador y el aviso de último día funcionan.

## Calendario y Auditoría en vivo, cierre al completarse (2026-10-01, SIN PROBAR contra la base real)

- **En vivo (solo admin)**: `assets/js/en-vivo.js` (`window.epVivo`) consulta cada 4 s una firma liviana, se pausa con la pestaña oculta y marca "En vivo"/"Sin conexión" en el rótulo verde junto al título (`.ep-vivo` en `shell.css`). Como `historial_firma.php`, estas consultas mantienen viva la sesión del admin.
- **Auditoría**: `getters/auditoria_vivo.php` (total y último id de la bitácora). Si cambia, `auditoria.js` pide `index.php?vista=auditoria`, reemplaza la lista y el JSON conservando filtros, el movimiento abierto (por `data-id`) y los conteos de chips y usuarios; las filas nuevas hacen un destello.
- **Calendario**: `getters/calendario_vivo.php` (cruza registros pendientes, cierra vencidos y completos, y devuelve estado de calendarios y filas, `includes/en_vivo_datos.php`). Si solo cambian filas de pendiente a cumplida, `calendario-lista.js` las actualiza en el sitio (avance, chips, indicadores, destello verde); si cambia la estructura (calendario nuevo, cerrado, reactivado o eliminado) recarga la página, salvo que el modal esté abierto. Las filas llevan `data-fila-id`.
- **Cierre al completarse**: `ep_calendario_cerrar_completos()` (`calendario_datos.php`) cierra y genera el reporte de todo calendario activo con todas sus filas cumplidas, sin esperar el plazo; se llama al cruzar un registro, al cruzar pendientes, al abrir el Calendario y en cada consulta en vivo. Queda en Auditoría como Sistema (`calendario_cierre_completo`) y el reporte lo firma quien creó el calendario.
- **Registro duplicado (bloqueado, 2026-10-01)**: un promotor no puede enviar dos Activaciones del mismo punto y día. `ep_registro_duplicado()` (`registros_datos.php`) lo detecta; `getters/registro_duplicado.php` avisa antes de subir fotos (`app.js`, "Ya enviaste este punto") y `guardar_registro.php` lo vuelve a validar (409). El mensaje manda al promotor a comunicarse con el administrador para que elimine el registro (Historial); al eliminarlo puede enviarlo de nuevo y la fila del calendario vuelve a pendiente. Solo Activaciones; las demás actividades no se tocan.
- **Reactivar un calendario pide motivo (2026-10-01)**: el botón abre un cuadro con el motivo obligatorio (mínimo 8 caracteres, máx. 300), `calendario_reactivar.php` lo valida y queda como "Motivo" en el detalle del movimiento de Auditoría.

## "Agregar otra foto" en todas las actividades (2026-10-01, SIN PROBAR en navegador)

- `ep_fotos_extensible()` (`fotos_datos.php`) ahora es verdadero para toda plantilla con lista de fotos, no solo Informe fotográfico y Competencia: el botón "Agregar otra foto" sale en la página y en el asistente (modal) de todas las actividades, sin tope de fotos hasta nuevo aviso. Las extras se llaman `foto-N` y se guardan y validan igual que antes (`guardar_registro.php`, `registros_datos.php`).
- PPTX: en los specs con `orden` fijo (Activaciones, Capacitaciones, Epson Day, Evento o Ferias, Exhibiciones, POP), `ep_ppt_registro()` agrega las fotos extra después de las fijas y siguen de 3 en 3 por diapositiva (probado con 8 fotos: 3 + 3 + 2).

## Aviso de registro enviado y calendario sin reporte (2026-10-01, SIN PROBAR en navegador)

- El modal "Registro enviado" tiene X (y Esc): solo el botón "Ver mis registros" va al Historial; cerrarlo deja Actividades con el formulario limpio (`app.js`, `epAviso(..., extra)`). `guardar_registro.php` redirige a `index.php?vista=historial&nuevo=<código>`; `historial.js` selecciona esa fila, la lleva a la vista y la resalta unos segundos (pulso morado y chip "Nuevo", `.ep-h2-nueva`) y limpia la dirección para no repetirlo al recargar.
- Un calendario cerrado puede quedar sin reporte si sus registros ya estaban en otro reporte mensual activo (`ep_registros_ocupados()`): en ese caso no hay `reporte_mensual_id` y la lista muestra un botón deshabilitado "Sin reporte" en vez de "Descargar PPT". Para generarlo: eliminar el otro reporte (libera los registros), reactivar el calendario (pide motivo) y generar de nuevo.

- **Auditoría de calendarios con más contexto (2026-10-01)**: `ep_auditar()` antepone a todo movimiento de Calendario una línea "Calendario" con nombre, número, canal y fechas (`ep_auditoria_calendario()`), para no confundir dos con el mismo nombre. El detalle de cierre (`ep_calendario_detalle_cierre()`) agrega el último registro que cumplió una fila (código, promotor, punto) y el título del reporte generado; si no hubo reporte explica por qué.
- **Auditoría puesta al día (2026-10-01)**: `ep_auditoria_listar()` agrega la línea "Calendario" también a los movimientos viejos de calendario al mostrarlos. Nuevo tipo **Alertas** (chip naranja): `registro_duplicado` se anota cuando un promotor intenta reenviar una Activación del mismo punto y día (`getters/registro_duplicado.php`), con el código del registro existente, para que el admin entienda por qué le piden eliminar uno.
- **Orden del Historial (2026-10-01)**: los días van del más reciente al más antiguo (por fecha de la actividad) y dentro de cada día por orden de llegada, el último enviado primero (`db_id` desc en `components/historial/filas.php`), ya no por la hora de la actividad.

## Roles, categorías y aprobación de registros (2026-10-02, SIN PROBAR contra la base real)

- **Origen**: correo del cliente (equipo Epson) con roles, categorías por persona y ruta de aprobación; decisiones en la conversación del 02-10. Diseño aprobado en Claude Design (tablero "Aprobación de registros EpsonReport").
- **Roles** (`repositorio_usuarios_reporte.rol`): `admin` (Carlos Moreira, Belén Manzano y `admin`: ven todo y solo ellos tienen Auditoría, Usuarios y el constructor de actividades), `supervisor` y `promotor`. Sesión: `$_SESSION['rol']` = admin | supervisor | usuario. Ayudas en `functions.php`: `ep_es_admin()`, `ep_es_supervisor()`, `ep_es_gestor()` (admin o supervisor). Admin y supervisor entran con clave propia (sin registro de Xplora).
- **Alcance del supervisor**: solo lo suyo. Calendarios y reportes mensuales: los que él creó (`creado_por`; `ep_calendario_permitido()`, `ep_calendario_listar()`, `ep_reportes_listar()`/`ep_reporte_obtener()`, `ep_vivo_calendario()`). Registros: los de su ruta (`insert_reporte_registro.supervisor_id`; `ep_aprobacion_filtro_alcance()` dentro de `ep_registros_datos()`, que tiene `$conAlcance=false` para procesos internos como el reporte del calendario). Al crear o editar calendarios solo puede programar a promotores de su equipo (`ep_promotores_de_supervisor()`, validado en `ep_calendario_fila_validar()`). Puede eliminar registros, calendarios y reportes dentro de ese alcance.
- **Ruta del promotor** (Usuarios, sección "Ruta del promotor"): `categorias` (todas, retail = todas menos canales, canales = todas menos retail; vacío = deducir por su ruta de Xplora como antes) y dos supervisores, `supervisor_canales_id` y `supervisor_retail_id`. Un registro de punto RETAIL va al supervisor de retail; los demás al de canales; sin supervisor asignado lo ve solo el admin. `ep_canales_usuario()` (`pdv_datos.php`) usa `categorias`; el gestor ve todas las categorías de PDV (`ep_todos_los_canales()`: Retail, Canales, Oficina, Eventos, Ferias, Bodega).
- **Estados** (`insert_reporte_registro.estado`): Pendiente (nace así), Aprobado, Devuelto y Reemplazado (un devuelto que se corrigió y reenvió; no se muestra). `includes/aprobacion_datos.php`: `ep_aprobar_registro()`, `ep_devolver_registro()` (motivo obligatorio de 8 a 300 caracteres), `ep_aprobaciones_contar()`, `ep_devueltos_usuario()`. Getters `aprobacion_aprobar.php` y `aprobacion_devolver.php`. Todo queda en Auditoría (`registro_aprobar`, `registro_devolver`, `usuario_ruta`). Sin las columnas nuevas el flujo anterior sigue igual (todo nace Aprobado).
- **Pantallas**: "Aprobaciones" (`index.php?vista=aprobaciones`, solo gestores) reutiliza el Historial en modo aprobación (`$modoAprobacion`, `data-modo`): pestañas Pendientes y Devueltos con contador, detalle con botones Aprobar y Devolver, refresco en vivo con `historial_filas.php?modo=aprobacion`. El menú muestra el contador de pendientes. El **Historial** del gestor muestra solo lo aprobado; el del promotor muestra lo suyo con chip de estado y, en un devuelto, el motivo y "Corregir y reenviar".
- **Corregir y reenviar**: `index.php?vista=actividades&corregir=<código>` (`assets/js/corregir.js`) preelige actividad, punto de venta (`epPdv.elegirPorId`) y fecha, rellena todo lo que envió (campos, modelos, entregas POP, comentarios y fotos ya subidas, con `window.epRelleno` de app.js y `datos` de actividades.php; SIN PROBAR en navegador) y muestra el motivo; al enviar, el devuelto pasa a Reemplazado. El promotor se entera en la campana (Avisos): los devueltos cuentan como avisos nuevos y urgentes.
- **Calendario y reportes**: una fila solo se cumple con un registro **aprobado** (se cruza al aprobar, `ep_calendario_cruzar_pendientes()` solo toma aprobados); los reportes mensuales, el consolidado y el reporte del calendario incluyen solo registros aprobados. El duplicado por punto y día ignora los devueltos y reemplazados.
- **SQL ya corrido** (confirmado el 01-10 con `SHOW COLUMNS`, solo lectura): `rol` ya es `ENUM('admin','supervisor','promotor')`, y existen `categorias`, `supervisor_canales_id`, `supervisor_retail_id` en usuarios, y `supervisor_id`, `motivo_devolucion`, `revisado_por`, `revisado_en` en registros. Los registros anteriores quedan Aprobados y sin supervisor (solo el admin los ve). Falta probar el flujo completo contra la base real en navegador.
- **Etiqueta de rol en el menú lateral (2026-10-02)**: cápsula translúcida bajo el nombre con un punto de color (Administrador dorado, Supervisor azul, Promotor lila), en `layout/sidebar.php` y `.ep-rol-etiqueta` de `shell.css`; se oculta con el menú plegado. Los supervisores también llevan categoría de puntos de venta (Fabricio todas, Cristhian y Andrea retail), según el correo.
- **Referencia de Auditoría con sentido (2026-10-02)**: el panel ya no muestra "Sección" ni "Número #6"; muestra "Afectado" con el nombre real (usuario, código de registro, título de reporte, actividad; `ep_auditoria_afectados()`) y "Desde la IP" solo si es válida. La IP se toma de `X-Forwarded-For` (`ep_ip_cliente()`): detrás de Azure `REMOTE_ADDR` era 169.254.x, una dirección interna que no sirve y que ya no se muestra (los movimientos viejos con esa IP la ocultan; los nuevos guardan la real).
- **Foto de usuario reducida antes de subir (2026-10-02)**: el servidor (nginx de Azure) rechaza cuerpos de más de ~1 MB con un 413 en HTML, y el navegador lo mostraba como "No se pudo conectar con el servidor" (comprobado: 0,5 MB pasa, 1,2 MB no). `usuarios.js` (`reducirFoto()`) redimensiona a 800 px y JPEG bajo 700 KB antes de subir; una foto de 5,8 MB queda en ~165 KB. La campana de Avisos ya no repica (se quitó la rotación); quedan el resalte y el anillo del contador.

## Modal del Calendario para supervisores (2026-10-01, SIN PROBAR en navegador)

- **Canal**: un supervisor solo ve las categorías de su canal en el combo (`ep_calendario_canales()` + `ep_supervisor_canales_gestion()`: Fabricio, todas menos Retail; Cristhian y Andrea, todas menos Canales). Canal por defecto: Retail, si no Canales, si no la primera; con una sola opción el combo queda bloqueado.
- **Equipo por canal**: `ep_promotores_de_supervisor($id, $canal)` filtra con la misma regla de la aprobación (`ep_supervisor_de_fila()`), así el catálogo (`calendario_catalogo.php`) y la validación del servidor (`ep_calendario_fila_validar()`) solo dejan ciudades y promotores de su equipo en ese canal.
- **Columna Supervisor**: oculta en el modal (cabecera y celda con `hidden`; la lógica del rutero sigue calculándola y el servidor la guarda igual). No se envía desde el navegador.

## Orden por ciudad en Exhibiciones Regulares (2026-10-02, probado con datos de ejemplo; falta un reporte real en navegador)

- Pedido explícito en la grabación 28-09 (líneas 77-89): solo para este reporte, Guayaquil primero y el resto "indiferente" pero agrupado por ciudad. Ningún otro tipo lo pidió.
- `ep_ppt_ordenar_registros($registros, $tipo)` (`includes/ppt_motor.php`): si `$tipo === 'informe-fotografico'`, ordena por ciudad (Guayaquil primero, luego alfabético) y dentro de la ciudad por punto de venta (estable, agrupa las fotos de un mismo punto); cualquier otro tipo sigue igual que siempre, por promotor y fecha. La usan `getters/reporte_descargar.php` (reporte mensual) y `getters/registros_ppt_multiple.php` (descarga consolidada); `registro_ppt.php` no la necesita (un solo registro).
- No se tocó la estructura de diapositivas: cada registro ya es un punto de venta (comparte `paso_pdv.php` con el resto de actividades) y ya sale como una sola diapositiva con el punto de título, así que no hacía falta nada más para calzar con el formato real de Epson.

## Bug corregido: calendario "Cerrado completo" sin reporte, sin avisar (2026-10-02, probado en vivo)

- **Lo que reportó el usuario**: un calendario con todas sus filas cumplidas se veía "Cerrado completo" (verde) igual que uno exitoso, pero el botón de descarga estaba gris con un tooltip escondido. Pidió explícitamente arreglarlo ("eso es un bug... no debería pasar algo así").
- **Causa 1 — el cierre mentía**: `ep_calendario_generar_ahora()` devolvía `bool` según si el `UPDATE` a la base funcionó, sin importar si el reporte realmente se generó (si `$validos` quedaba vacío, `reporte_mensual_id` se guardaba `NULL` pero la función igual decía éxito). Pasaba en los 3 llamadores: cierre automático al vencer, cierre automático al completarse, y el botón manual "Generar reporte" (el más grave: el admin lo aprieta y el sistema responde `ok:true` sin haber generado nada).
- **Causa 2 — la causa de fondo, por qué había un registro "ya ocupado"**: `ep_registros_ocupados()` solo contaba como ocupado un registro ya guardado DENTRO de un reporte mensual. Mientras un registro solo cumple la fila de un calendario activo (pero ese calendario todavía no se cierra, lo que puede tardar días), **no estaba reservado para nadie**: cualquiera podía agarrarlo a mano desde "Nuevo reporte" en Reportes mensuales, dejando al calendario sin nada que reclamar cuando por fin intentaba cerrar. Así se originó el caso real "PRUEBA E2E CRISTHIAN" (id 2, intacto, no se tocó): su único registro fue tomado por un reporte manual del mismo nombre antes de que el calendario alcanzara a cerrarse.
- **Arreglo 1 (síntoma)**: `ep_calendario_generar_ahora()` ahora devuelve `?int` (null = no se pudo cerrar, 0 = cerró SIN reporte, >0 = id del reporte). Los 3 llamadores distinguen los casos: auditoría con acción `calendario_cierre_sin_reporte` / `calendario_generar_sin_reporte` (clasificadas como alerta naranja, no como "Sistema" genérico — `$tipoDe()` en `auditoria.php` las chequea antes que el filtro por `usuario_id === null`). El botón manual (`getters/calendario_generar.php`) responde `{ok:true, aviso: '...'}` y `calendario.js` muestra una ventana de advertencia antes de recargar. La lista (`calendario.php`) ya no confunde "Cerrado completo" con éxito: agrega `$sinReporte` (vista `completo` sin `reporte_mensual_id`) → badge naranja "Cerrado sin reporte" y la acción principal pasa a ser "Reactivar" en vez de un botón muerto.
- **Arreglo 2 (causa de fondo)**: `ep_registros_ocupados(?int $exceptoCalendarioId = null)` ahora también reserva los registros que ya cumplieron la fila de CUALQUIER calendario activo (no solo los que ya están en un reporte guardado); el propio calendario se excluye de su propia reserva al intentar cerrar (`$exceptoCalendarioId`). Solo afecta a `tipo='activaciones'` (lo único que cruza calendarios); cero impacto en las demás lógicas. Se usa sin excepción en `getters/reportes_registros.php` (la lista de "Nuevo reporte") y `getters/reporte_guardar.php` (revalidación al guardar), así que ahora es imposible repetir el escenario de "PRUEBA E2E CRISTHIAN": ese registro ya no aparece como elegible ni se puede forzar el guardado directo al servidor.
- **Probado en vivo (02-10)**: Pablo envió una Activación real para un punto con calendario de 2 filas; tras aprobarla (admin), la fila quedó cumplida y el calendario siguió activo (1 de 2) — en ese momento el registro **no apareció** en la lista de "Nuevo reporte", y un intento forzado de `reporte_guardar.php` con su id lo rechazó ("ya están en otro reporte"). Datos de prueba limpiados al terminar. El caso real "PRUEBA E2E CRISTHIAN" se dejó intacto a propósito; con este arreglo ya se ve "Cerrado sin reporte" + "Reactivar" en vez del badge verde engañoso.

## Mecánica de reportes con supervisor: probada de punta a punta en los 7 tipos (2026-10-02)

- **Qué se probó**: para cada tipo que se arma a mano en Reportes mensuales (Capacitaciones, Epson Day, Evento o Ferias, Exhibiciones que Inspiran, Colocación de POP, Informe Fotográfico, Competencia — Activaciones queda aparte, solo por Calendario), Pablo Castelo envió un registro real (punto RETAIL), se aprobó, y CRISTHIAN VELASTEGUI (su supervisor de retail) armó y descargó el reporte mensual.
- **Resultado: los 7 pasaron sin ningún fallo.** En cada uno: el registro aprobado apareció en "Nuevo reporte" solo para el supervisor correcto (`supervisor_id` se asigna al enviar según el canal del punto — Retail va al `supervisor_retail_id`, el resto según categoría); el reporte se guardó y descargó con el PPTX correcto (`200`, `presentationml`); otro supervisor sin relación (FABRICIO LUZARRAGA, canales) no vio ni el registro ni el reporte; el admin vio los 7 reportes de Cristhian sin problema.
- **No se encontró ningún bug** en esta mecánica — el diseño de alcance (`ep_aprobacion_filtro_alcance()` + `ep_reportes_listar()`/`ep_reporte_obtener()` filtrando por `creado_por` para supervisores) funciona como debería en los 7 tipos.
- **Detalle cosmético aparte, sin tocar**: en el PPT de Epson Day, "Detalle de Ventas" monta un poco el nombre del modelo con la barra cuando el nombre es largo (ej. "ECOTANK L3250") — preexistente, no relacionado con esta prueba.
- Datos de prueba (7 registros + 7 reportes "PRUEBA CLAUDE SUPERVISOR ...") creados y eliminados en la misma sesión, no quedó nada en la base.

## Textos largos montados con las barras en "Detalle de Ventas"/"Ingresos por Modelo"/"SKU mayor-menor" (2026-10-02, corregido y probado)

- **Reportado por el usuario** en el PPT real de Epson Day: con un nombre de modelo largo ("ECOTANK L3250"), el texto se montaba con la barra en "Detalle de Ventas" y pasaba a 2 líneas en "SKU con mayor venta", chocando con el subtítulo. Afecta a Activaciones, Epson Day y Evento o Ferias (comparten `ep_ppt_estadisticas_embudo()`), y a "Ingresos por Modelo" (clon de las mismas filas).
- **`ep_ppt_recortar_ancho()`** nueva en `ppt_base.php` (mismo cálculo que `ep_ppt_cal_recortar()` de la tabla del calendario, generalizado): recorta con "..." si el texto no entra en el ancho disponible a ese tamaño de fuente; `$anchoPorCaracter` configurable (0.55 para texto normal de 8pt, 0.75 para el texto grande en negrita de SKU mayor/menor, calibrado contra la plantilla real).
- **Detalle de Ventas**: cada fila recorta su nombre de modelo antes de llegar a donde empieza la barra (`ep_ppt_medidas()` del label y del fondo).
- **SKU mayor/menor**: las dos tarjetas traían ancho distinto en la plantilla (una se quedaba corta); se igualan a la más ancha y además se recorta si aun así no entra, en vez de pasar a una 2da línea que se montaba con el subtítulo.
- **Ingresos por Modelo** (el más delicado): su fila vive dentro de un grupo (fondo+barra) que se **clona y se desplaza** (`ep_ppt_clonar_y_mover`) a la franja libre de la derecha; clonar un grupo solo mueve el ancla del grupo (`off.x += deltaX`), nunca la posición interna de sus hijos — leer la geometría de la barra clonada sin sumarle ese mismo `$deltaX` da un hueco negativo y el recorte se queda en apenas "..." sin información. Se corrigió sumando `$deltaX` al leer. Confirmado que "Detalle de Ventas" NO tenía este problema (se arma antes de que `ep_ppt_ingresos_modelo()` mueva nada).
- **Probado con un nombre extremo** ("WORKFORCE PRO WF-C5790 MULTIFUNCIONAL") y con el caso real ("ECOTANK L3250") en las dos variantes de diseño (Epson Day/Evento Ferias con Ingresos al costado, y Activaciones con comentarios a la derecha e Ingresos debajo) — las 4 diapositivas quedan sin ningún texto montado.

## Fuga de alcance en el catálogo del Calendario: PDV sin filtrar para supervisores (2026-10-02, corregido y probado en vivo)

- **Bug encontrado al auditar "qué ve un supervisor que no debería"**: `getters/calendario_catalogo.php` filtraba `rutero` y `ciudades` por el equipo del supervisor, pero el tercer bloque del mismo catálogo, `pdv` (puntos de venta para el selector del modal), quedaba fuera del `if ($esSupervisor)` y se devolvía completo, de todas las ciudades del canal. Un supervisor de Retail (ej. Cristhian) recibía los puntos de venta de las 24 ciudades de Retail a nivel nacional, no solo las ~13 de su propio equipo.
- **Arreglo**: dentro del mismo `if ($esSupervisor)`, se filtra `pdv[$canal]` contra `$ciudadesEquipo` (ya calculado ahí mismo para `ciudades`), usando la clave `ciudad` que `ep_calendario_pdv()` ya devuelve por fila.
- **Probado en vivo (02-10)**: con dos sesiones reales simultáneas, CRISTHIAN VELASTEGUI (retail) vio `pdv.RETAIL` reducido a sus ciudades de equipo (antes 24, ahora 13-14) y `pdv.BODEGA`/`pdv.EVENTOS` vacíos (sin equipo ahí); FABRICIO LUZARRAGA (canales) igual en `pdv.CANALES` (antes 26, ahora 21-23); el admin siguió viendo el catálogo completo sin cambios. Confirmado además que ningún supervisor ve el canal que no le toca (Cristhian no recibe `CANALES`, Fabricio no recibe `RETAIL` — filtro preexistente de `ep_calendario_canales()`, sigue intacto).
- **Nota aparte, no es un bug**: `pdv` trae algunas ciudades duplicadas por espacio final ("CHONE" y "CHONE ") que no aparecen en `rutero`/`ciudades`; es una inconsistencia de datos de origen (`repositorio_locales_dtt2.city` vs el rutero), preexistente y presente también para el admin — no relacionada con este fix de alcance.

## Auditoría de "supervisor viendo lo que no le toca" (2026-10-02): catálogo del Calendario corregido, resto sin bugs

- **Repasado y confirmado sin problemas**: cada llamada a `ep_registros_datos()` usa el flag `$conAlcance` correcto (los dos únicos `false` son para procesos internos ya scoped de otra forma: `reporte_descargar.php` y dentro de `ep_calendario_generar_ahora()`); `ep_reportes_listar()`/`ep_reporte_obtener()` y `ep_calendario_listar()`/`ep_calendario_permitido()` filtran por `creado_por`; `ep_promotores_de_supervisor()` (equipo de Calendario) usa las mismas columnas `supervisor_canales_id`/`supervisor_retail_id` que la ruta de aprobación, sin divergencia entre los dos sistemas.
- **Único bug encontrado**: el catálogo `pdv` del modal de Calendario (ver sección arriba), ya corregido y probado con dos sesiones reales simultáneas.
- **Intentos directos de un supervisor sobre un calendario de otro** (editar/eliminar/generar/reactivar, sin pasar por la UI): los 4 getters (`calendario_editar.php`, `calendario_eliminar.php`, `calendario_generar.php`, `calendario_reactivar.php`) llaman `ep_calendario_permitido($id)` y hacen `exit` antes de tocar cualquier dato si no es dueño. Probado la función misma contra la base real con las sesiones de Cristhian (id 8, dueño del calendario #2) y Fabricio (id 5, dueño del #3): cada uno solo pasa sobre el suyo, el admin pasa sobre cualquiera, y una sesión de supervisor sin `usuario_id` queda bloqueada. (Se probó la función directamente en vez de disparar los getters reales contra calendarios de producción, para no arriesgar una mutación real — eliminar/reactivar/editar — si hubiera existido el bug.)
- `registro_eliminar.php` y `reporte_eliminar.php` usan `ep_es_gestor()` (admin O supervisor) a propósito, no `ep_es_admin()`: el alcance real lo da el contenido (`ep_registro_por_codigo()`/`ep_reporte_obtener()` ya filtran por `ep_aprobacion_filtro_alcance()`/`creado_por`), así que un supervisor solo puede eliminar lo que ya podía ver.
- **Historial en vivo con dos sesiones reales simultáneas (02-10)**: Cristhian vio 5 registros (todos suyos, de su equipo retail), Fabricio 7 (todos del suyo, canales), sin ningún código en común entre ambos; el admin vio 16 (la unión más registros sin supervisor asignado, que solo él ve). Aprobaciones pendientes/devueltas: vacío para ambos en este momento (no hay nada pendiente real), sin error en el caso vacío.
- **Categoría 1 cerrada**: "supervisor viendo lo que no le toca" — un bug real encontrado y corregido (catálogo `pdv` del Calendario), todo lo demás (Historial, Aprobaciones, acciones de Calendario, Reportes, registros) probado en vivo o por código y sin fugas.

## Bug corregido: eliminar un registro Aprobado que ya cerró un calendario con reporte dejaba el reporte huérfano con datos fantasma (2026-10-02, probado en vivo)

- **Cómo se encontró**: probando a propósito la categoría "estados de registro en los bordes" — un calendario de 1 fila, el promotor envía y se aprueba el único registro, el calendario cierra solo (`estado='cerrado'`, badge "Cerrado completo") y genera su reporte mensual (congelado en `snapshot`). Luego, como admin, se elimina ese registro desde Historial (acción normal y ya documentada: "un registro eliminado devuelve a 'pendiente' la fila de calendario que había cumplido").
- **Lo que pasaba**: `ep_calendario_liberar_registro()` no distinguía si el calendario de esa fila seguía `activo` o ya estaba `cerrado` — en cualquier caso ponía la fila en `pendiente` y limpiaba `registro_id`. Como la vista de la lista (`ep_cal_estado_vista()` en `components/calendario/calendario.php`) recalcula "completo/incompleto" en cada carga a partir de las filas (no de una columna congelada), el calendario pasaba a mostrarse como "Cerrado incompleto" con botón "Reactivar", aunque en la base seguía `estado='cerrado'` y **seguía teniendo un `reporte_mensual_id` válido y descargable**: el reporte ya generado no se invalidaba ni avisaba a nadie, y su `snapshot` quedaba con los datos congelados del registro que ya no existía (fotos, estadísticas, punto de venta). Mismo problema de fondo si el reporte se armó a mano (sin Calendario): el snapshot congelado tampoco reacciona a un borrado posterior, solo que ahí no hay badge que se desincronice.
- **Decisión del usuario, de tres opciones planteadas**: bloquear directamente la eliminación de un registro que ya está dentro de un reporte mensual guardado, hasta que se elimine primero ese reporte (mismo patrón que ya existía para "no puedes guardar un registro en 2 reportes a la vez").
- **Arreglo**: nueva `ep_registro_en_reporte(int $registroId): ?array` (`includes/reportes_datos.php`), recorre los reportes mensuales no eliminados y devuelve el primero cuyo `registros` (JSON) incluya ese id. `getters/registro_eliminar.php` la consulta antes de llamar a `ep_registro_eliminar()`; si el registro ya está en un reporte, responde `{ok:false, message:'Este registro ya está en el reporte mensual «<título>». Elimina ese reporte primero si de verdad quieres borrar el registro.'}` y no toca nada. Aplica tanto si el reporte vino de un Calendario como si se armó a mano.
- **Probado en vivo (02-10)**: mismo escenario que destapó el bug — el intento de eliminar el registro ahora se rechaza con ese mensaje exacto y el calendario sigue mostrando "Cerrado completo" sin desincronizarse; eliminando primero el reporte el registro se puede eliminar después sin problema. Datos de prueba limpiados, no quedó nada en la base.

## Bug corregido: el correo del promotor salía montado sobre su nombre en el PPT (2026-10-02, probado en vivo, afectaba casi todos los reportes generados)

- **Reportado por el usuario** con una captura real (Jonathan Celin). El nombre del promotor en el PPT (`$reg['promotor']`) es el nombre legal completo de Xplora (2 apellidos + 2 nombres, ej. "CELIN TOLEDO JONATHAN ANTHONY"), no el usuario corto de login. La caja de nombre en la plantilla (`CuadroTexto 16`/`12` según el tipo) mide 1810139 EMU de ancho a 16pt — un nombre de ese tamaño no entra en una línea, pasa a 2 o 3 (`wrap="square"` + `spAutoFit`, la caja crece sola), y la caja de correo (`CuadroTexto 17`/`14`) está casi pegada justo debajo (gap negativo ya en la plantilla original), así que la 2da/3era línea del nombre se monta con el correo.
- **Alcance real, medido contra los 23 promotores activos**: 22 de 23 (96%) tienen un nombre demasiado largo para una línea — esto no era un caso raro, pasaba en casi todos los PPT generados hasta ahora (Activaciones, Capacitaciones, Epson Day, Evento o Ferias, Exhibiciones — todo lo que usa `ep_ppt_barra_promotor()`; Informe Fotográfico, Competencia y POP no llevan esta diapositiva).
- **Por qué no se truncó el nombre** (a diferencia del fix de SKU/Detalle de Ventas de este mismo día): es el nombre legal de una persona, no un código de producto; truncarlo a ~16-19 caracteres habría cortado la mayoría de los nombres reales a la mitad. En cambio, midiendo la plantilla se encontró ~1.49M EMU de espacio completamente libre más abajo en la barra (entre "Ciudad" y "Actividad"), suficiente para correr el bloque de abajo sin chocar con nada.
- **Arreglo**: `ep_ppt_cabe_una_linea()` nueva en `ppt_base.php` (mismo cálculo que `ep_ppt_recortar_ancho()` pero solo devuelve si el texto entraría en una línea, sin recortar). `ep_ppt_barra_promotor()` (`ppt_motor.php`) mide la caja de nombre después de escribirlo; si el nombre no entra en una línea, corre correo + punto de venta + ciudad 290000 EMU hacia abajo (`ep_ppt_mover()`, ya existente) antes de escribirles su texto — el nombre completo se deja intacto, nunca se recorta. El corrimiento existente de "ciudad" por punto de venta largo se suma encima si aplica, sin conflicto.
- **Probado en vivo (02-10)**: se volvió a descargar el PPT real ya existente de Jonathan Celin (`RACJONATHANCELIN-003`, aprobado) y se exportó la diapositiva a PNG con PowerPoint — el nombre ahora se ve en 3 líneas limpias, sin tocar el correo, el punto de venta ni la ciudad debajo. Un nombre corto ("PABLO CASTELO", 13 caracteres) se confirmó que NO dispara el corrimiento (sigue como antes, sin espacio de más).

## Fila de numeritos del asistente de fotos (móvil): ahora se desliza sola, sin arrastrar "Agregar otra foto" (2026-10-02, probado en vivo)

- **Pedido del usuario**: en el asistente de fotos en celular, la fila de numeritos (1, 2, 3...) debía poder deslizarse para ver todas las fotos, pero el botón "+ Agregar otra foto" de abajo debía quedarse fijo, sin moverse con la fila.
- **Causa**: `.ep-wizard-reel-scroll` (`assets/css/wizard-fotos.css`) era el único contenedor con `overflow-x: auto`, y contenía DENTRO tanto la fila de numeritos (`.ep-wizard-reel`) como el botón "Agregar otra foto" como hermanos (apilados en columna cuando el botón está visible, que ahora es casi siempre). Al deslizar, todo el bloque —fila y botón— se movía junto, en vez de solo la fila.
- **Arreglo**: el `overflow-x: auto` (+ el ocultado de la barra de scroll) se movió del contenedor exterior (`.ep-wizard-reel-scroll`, que ahora es solo el envoltorio vertical fijo) a la fila misma (`.ep-wizard-reel`), con `width: 100%; min-width: 0;` para que no crezca a su ancho natural dentro del contenedor en columna (si no, nunca desborda DENTRO de su propia caja y el scroll no sirve de nada). `justify-content` pasó de `center` a `safe center`: sigue centrada cuando caben pocas fotos, pero nunca deja la primera miniatura inalcanzable cuando hay que deslizar (gotcha conocido de flexbox: centrar + overflow sin `safe` puede dejar contenido del lado izquierdo fuera de alcance del scroll).
- **Probado en vivo (02-10)**: con 12 casillas de foto (3 fijas + 9 agregadas con "+ Agregar otra foto"), la fila mide 521px de contenido en un contenedor de 326px visibles (desborda de verdad); deslizarla hasta el final cambia qué números se ven (de "1...7" a "6...12") y la posición del botón "Agregar otra foto" queda exactamente igual, pixel por pixel, antes y después de deslizar.

## Competencia: varios puntos de venta en una sola sesión, sin salir del formulario (2026-10-03, diseñado con Claude Design y probado en vivo)

- **Pedido del usuario**: en Competencia, el promotor debe poder registrar varios puntos de venta seguidos sin reiniciar el flujo — terminar las fotos de uno, tocar "Añadir otro punto de venta", y que se abra el mismo formulario en blanco para el siguiente, sin perder lo ya hecho. Diseñado primero con la herramienta de Diseño de Claude (pedido por nombre, no con `impeccable`): mockup interactivo en https://claude.ai/artifact/JUYgZNGLR16Y7wyunm6rKu (escritorio y celular), aprobado antes de construir.
- **Decisión de implementación (distinta del mockup, más robusta)**: el mockup mostraba un "Enviar todo" final en bloque; en el código real, cada punto de venta se **guarda de inmediato** al tocar "Añadir otro punto de venta" (sube sus fotos y llama a `guardar_registro.php` ahí mismo, como cualquier registro normal) — así no se pierden las fotos de los puntos anteriores si el promotor cierra la pestaña a medias, y no hay que cargar en memoria las fotos de varios puntos a la vez. El último punto se envía con el botón normal "Enviar registro" de siempre.
- **Frontend** (`assets/js/app.js`): `epCompetenciaCompletados` (array en memoria) + `renderizarCompetenciaCompletados()` pintan la fila colapsada con ✓ verde por cada punto ya guardado (nombre, cantidad de fotos, código real `RCOMP...`). El botón `#epBtnCompetenciaOtroPunto` reusa `validarRegistroActivo()` tal cual (ya validaba el punto de venta activo, por eso no hizo falta duplicar nada), sube las fotos con `subirFotosPendientes()` (ya existente) y llama a `guardar_registro.php` con el mismo payload que arma el envío normal para `tipo:'competencia'`. Al terminar, `epCompetenciaReiniciarFormulario()` limpia el selector de punto de venta (`window.epPdv.limpiar()`, nuevo en `pdv.js`) y las casillas de fotos (`quitarFotoDeSlot()`/`quitarCasillaExtra()`, ya existentes) para dejar el formulario igual que al entrar. Cambiar de actividad abandona la lista en pantalla (`epCompetenciaReiniciarCompletados()`, enganchado en `mostrarFormularioDeActividad()`) sin afectar lo ya guardado en la base. El mensaje final de éxito cuenta el total de puntos de la sesión ("Enviaste 3 puntos de venta en esta sesión...") cuando hubo más de uno.
- **Backend**: sin cambios — cada punto de venta sigue siendo un registro `RCOMP...` normal (mismo patrón que Colocación de POP: un registro = un punto de venta), así que Aprobaciones, Historial, Calendario y los reportes mensuales no necesitaron tocarse.
- **Carrusel de fotos en celular** (pedido en el mismo mensaje): la grilla de fotos (`.ep-evidencia-grid-panoramica`) pasa de grid de 2 columnas (que envuelve en varias filas) a una sola fila horizontal deslizable (`display:flex; overflow-x:auto; scroll-snap`), **solo para Competencia** (`[data-plantilla="competencia"]`, atributo nuevo en `paso_evidencia.php`) — las demás actividades (fotos fijas, pocas) conservan la grilla de 2 columnas de siempre. Es un carril aparte del resto de la pantalla: nada más se mueve al deslizarlo.
- **Probado en vivo (03-10)**: con Pablo Castelo, dos puntos de venta reales en una sola sesión (ADVANCE y ARTEFACTA) — al tocar "Añadir otro punto de venta" se guardó `RCOMPABLOCASTELO-002` en el acto, la fila colapsada apareció, el selector de punto volvió a "Selecciona el punto de venta" y la casilla de foto volvió a quedar vacía (confirmado en el DOM, no solo visualmente); el segundo punto se envió con el botón normal y generó `RCOMPABLOCASTELO-003`, con el mensaje "Enviaste 2 puntos de venta en esta sesión". Confirmado en la base (solo lectura) que son dos registros reales e independientes, cada uno con su propio `pos_id`. El carrusel móvil se probó completando la foto obligatoria y agregando 4 fotos extra: la fila quedó con 1096px de contenido en 328px visibles y se pudo deslizar hasta el final sin mover el resto de la pantalla. Datos de prueba eliminados al terminar.
- **El PPT no necesitó cambios**: cada punto de venta sigue siendo un registro `RCOMP...` normal, así que `includes/ppt_competencia.php` (que arma una diapositiva por registro) los procesa igual sin importar si vinieron de una sesión con varios puntos o de un envío suelto. Para bajar varios puntos de una sesión juntos en un solo PPTX ya existe la descarga consolidada de Historial (checkbox + "Descargar consolidado"), sin tocar nada nuevo.

## Excel del Calendario: fecha, punto de venta, promotor, supervisor y si se ejecutó (2026-10-03, probado en vivo)

- **Pedido del usuario**, retomando el ítem de la cola de mejoras de la reunión 02-10 ("un Excel con lo ejecutado del mes, sin bajar cada PPT"): para Activaciones específicamente, exportar **la misma tabla que ya se ve en el detalle de cada calendario** (fecha, ciudad, punto de venta, promotor, supervisor), sumando una columna de si esa fila se ejecutó o no. Sin fotos ("eso no les interesa").
- **Dónde quedó**: no es un módulo nuevo — un botón "Descargar Excel" más en el menú "Más acciones" de cada calendario (`components/calendario/calendario.php`), junto a Reactivar/Eliminar, disponible en cualquier estado (activo, cerrado completo o incompleto) para que un supervisor pueda bajar el avance a mitad de mes sin esperar a que cierre.
- **Backend**: `getters/calendario_excel.php` (mismo patrón de permiso que el resto del Calendario: `ep_calendario_permitido($id)`, un supervisor solo baja el suyo) arma un **CSV** (no un .xlsx real — Excel lo abre nativo, sin librerías nuevas, mismo criterio de simplicidad que el resto del proyecto) con BOM UTF-8 para que tildes y "ñ" no se rompan. Columnas: Fecha, Ciudad, Punto de venta, Promotor, Supervisor, Ejecutado (Sí/No según `estado === 'cumplido'`). `ep_calendario_filas_tabla()` (`includes/calendario_datos.php`, ya usada por el PPT del calendario) ganó la columna `supervisor` que le faltaba; no afecta al PPT porque ese código lee por nombre de clave, no por posición.
- **Probado en vivo (03-10)**: admin descargó el CSV de un calendario real con una fila correcta; Cristhian pudo descargar el suyo (200) pero no uno de Fabricio (403, "Ese calendario no es tuyo") — mismo alcance que ya tiene todo el módulo. Solo lectura, no se creó ni modificó ningún dato.

## Competencia: un solo registro con varios puntos de venta adentro (2026-10-03, rehecho y probado en vivo)

- **Pedido del usuario**: lo construido antes ese mismo día (cada punto de venta como su propio registro, ver sección "Competencia: múltiples puntos de venta" más arriba) no era lo que pidió el cliente — debía ser **un solo registro** por sesión del promotor, con el PPT saliendo segmentado por punto de venta (cada uno con sus fotos), como ya hace el PPT hoy por registro.
- **Decisión tomada con el usuario**: sin tablas nuevas (el usuario lo pidió explícitamente, "¿no tiene ya demasiadas tablas este proyecto?") — todo vive dentro de la columna `valores` (JSON) que ya existe, mismo patrón que `pop_entregas` de Colocación de POP o `modelos` de Activaciones. Aprobación: **todo o nada** (un solo registro, una sola aprobación — no hizo falta construir nada extra, sale gratis de que ya es un registro). Si la sesión mezcla puntos de Retail y Canales (dos supervisores distintos): **se manda a los dos**, el principal en la columna `supervisor_id` de siempre y los extra dentro del JSON (`valores.supervisores_extra`, lista de ids).
- **`getters/guardar_registro.php`**: rama nueva al principio para `tipo === 'competencia'` (si no, sigue el flujo de siempre para los otros 7 tipos, intacto). Recibe `valores.puntos` (array), valida cada punto contra la base igual que el flujo de un solo punto (nunca confía en lo que manda el navegador), calcula el supervisor de cada uno con `ep_supervisor_asignado()`, arma `puntos` (cada uno con sus `fotos` y `descripciones`) y guarda **un solo** `insert_reporte_registro` con `pos_id`/`punto_venta`/`ciudad` del primer punto (para todo lo que lee esas columnas genéricamente) y `valores.puntos` con el resto.
- **`ep_aprobacion_filtro_alcance()`** (`includes/aprobacion_datos.php`): ahora es `r.supervisor_id = <id> OR (JSON_VALID(r.valores) AND JSON_CONTAINS(r.valores, ..., '$.supervisores_extra'))`. Cambio seguro: para los otros 7 tipos esa clave JSON nunca existe, así que el `OR` siempre da falso — cero riesgo de que esto les cambie el alcance.
- **`ep_registro_armar()`** (`includes/registros_datos.php`): si el registro trae `puntos`, les pega la `descripcion` a cada foto igual que ya hacía con el registro plano. **Bug encontrado y corregido de paso**: `foreach ($punto['fotos'] ?? [] as &$fotoPunto)` no modificaba nada — el operador `??` copia a un valor temporal, así que la referencia `&$fotoPunto` apuntaba a la copia, no al array real (gotcha clásico de PHP). Se aisló la variable antes del `foreach` para que la referencia sí pegue en el array real.
- **`includes/ppt_competencia.php`**: `ep_ppt_competencia_expandir()` nueva — antes de generar, convierte cada registro con `puntos` en N entradas (una por punto, con su propio `punto_venta`/`ciudad`/`fotos`, heredando `promotor`/`fecha_iso` del registro real) para que el motor compartido (`ep_ppt_generar()`, sin tocar) las trate como siempre: una diapositiva por entrada. Un registro sin `puntos` (cualquier otro tipo) sigue exactamente igual.
- **`components/historial/detalle_registro.php`**: si el registro trae `puntos`, junta las fotos de todos en una sola galería (con el punto de venta como prefijo de la etiqueta) en vez de mostrar solo las del primero — si no se tocaba esto, Historial ocultaba silenciosamente los puntos de venta extra y el conteo de fotos salía mal (confirmado el bug antes del fix: mostraba "0/3" en vez de "2/6" con 2 puntos). El título del detalle suma "+ N puntos más" cuando hay más de uno.
- **Probado en vivo (03-10)**: con Pablo Castelo, un registro con 2 puntos de venta (ADVANCE y ARTEFACTA) terminó como **un solo** `RCOMPABLOCASTELO-004` (confirmado en la base: `valores.puntos` con los 2 puntos completos, cada uno con sus fotos y descripción, `supervisor_id` correcto). El PPT descargado trajo 2 diapositivas, una por punto, cada una con "PROMOCION 2X1, PUNTO 1" / "EXHIBICION NUEVA, PUNTO 2" en negrita sobre el punto de venta — exactamente el formato oficial. Historial mostró el detalle completo tras el fix (2/6 fotos, ambos puntos en la galería). Dato de prueba eliminado al terminar.
- **Caso "se manda a los dos supervisores" probado en vivo (03-10)**: con Karina Palas (`categorias='todas'`, supervisor Retail = Cristhian, supervisor Canales = Fabricio), un registro con un punto Retail (ADVANCE) y uno de Canales (ARTECOMP) quedó como `RCOMKARINAPALAS-001` con `supervisor_id=8` (Cristhian) y `valores.supervisores_extra=[5]` (Fabricio). Confirmado con tres sesiones reales: Cristhian y Fabricio lo vieron en Aprobaciones → Pendientes, Andrea (otra supervisora de retail, sin relación) no lo vio. Cristhian lo aprobó (una sola acción, todo el registro con sus 2 puntos) y de inmediato Fabricio dejó de verlo como pendiente — confirma que la aprobación "todo o nada" funciona para ambos supervisores a la vez, sin código extra. Dato de prueba eliminado al terminar.
- **Único botón con esta lógica**: solo existe el botón "Competencia" (id 7) usando `logica='competencia'` — nadie copió esa lógica para crear otro botón desde el Constructor. El arreglo está atado a la lógica (`plantilla === 'competencia'`), no al botón, así que un futuro botón copiado de Competencia heredaría el mismo comportamiento de varios puntos sin tocar nada.

## Exportar a Excel desde Historial, para todas las actividades (2026-10-03, probado en vivo)

- **Pedido del usuario**, retomando el ítem de la cola de mejoras de la reunión 02-10 ("un Excel con fecha, punto de venta y actividad, para cualquier tipo, sin fotos"), generalizado a los 7 tipos (ya se había hecho uno específico para Activaciones vía el Calendario, ver sección de arriba "Excel del Calendario").
- **Dónde quedó**: botón "Exportar a Excel" en el encabezado de Historial (`components/historial/historial.php`, solo admin/supervisor, oculto en el modo Aprobaciones). Exporta un **CSV** (mismo criterio de siempre: Excel lo abre nativo, sin librerías nuevas) con las columnas Fecha, Punto de venta, Actividad, Promotor, Estado, Código.
- **Respeta los filtros aplicados en pantalla** (actividad, promotor, periodo, búsqueda, estado): el filtrado de Historial es 100% en el navegador (sobre filas ya renderizadas por el servidor, `assets/js/historial.js`), así que el botón arma el CSV directo del mismo array `filas.filter(coincide)` que ya usa el filtro — sin pedir nada al servidor ni duplicar la lógica de filtrado. Usa `coincide()` (el predicado real) y no las filas `:not(.hidden)`, porque esas último respetan además la paginación de "Mostrar más" (40 en 40): exportar debía traer TODO lo filtrado, no solo lo cargado en pantalla.
- El alcance por supervisor sale gratis: las filas que llegan al navegador ya vienen scopeadas por `ep_registros_datos()` (igual que el resto de Historial), así que un supervisor solo puede exportar lo que ya podía ver.
- `components/historial/filas.php` ganó el atributo `data-punto` en cada fila (no existía, solo hacía falta para esto).
- **Probado en vivo (03-10)**: sin filtro exportó los 24 registros reales de la base; filtrado por "Activaciones" exportó 19, todos correctamente de ese tipo. Solo lectura, nada se creó ni modificó.

## Supervisores pueden enviar registros de nuevo, hasta nuevo aviso (2026-10-03)

- El bloqueo del 02-10 ("Supervisores no envían registros") contradecía la grabación: el cliente dijo explícitamente que Andrea y Cristhian sí envían Capacitaciones. Mientras se define la regla exacta (¿cualquier supervisor con cualquier tipo, o solo Andrea/Cristhian con Capacitaciones?), el cliente pidió dejarlo libre para cualquier supervisor y cualquier tipo.
- `getters/guardar_registro.php` y `getters/subir_foto.php`: se quitó el bloqueo `if (ep_es_supervisor()) { 403 }`, con un comentario de una línea marcando dónde iba por si hay que restringirlo de nuevo. Probado en vivo: Cristhian (supervisor) pudo subir una foto sin problema.

## Modal "Exportar a Excel" en Historial: fecha + supervisor (admin) + promotor en cascada + tipo de actividad (2026-10-03, diseñado con Claude Design y probado en vivo)

- **Pedido del usuario**: reemplazar el botón simple de Historial por un modal (mismo patrón visual que "Nuevo reporte mensual"), con los filtros en una sola fila: Fecha (Única/Rango) + Supervisor (solo admin, opcional) + Promotor (opcional, se acota solo a los promotores de ese supervisor al elegirlo) — y debajo, la grilla de tipo de actividad. Diseñado primero en Claude Design (4 pantallas: Main, Admin, Supervisor, Mobile) en https://claude.ai/artifact/5mP6JxoFuwt9UKFwhZqF8A, aprobado antes de construir.
- **Reemplaza** el botón+descarga-directa-con-filtros-de-pantalla construido horas antes ese mismo día (ver sección de arriba): ya no depende de lo que esté filtrado en la pantalla de Historial, tiene su propio estado independiente.
- **Backend nuevo**: `getters/historial_excel.php` (admin/supervisor). Lee con `ep_registros_datos(5000)` (el alcance por supervisor ya aplica solo con eso) y filtra en PHP por tipo, fecha (desde/hasta) y promotor; el filtro de `supervisor_id` solo lo puede mandar un admin real (`ep_es_admin()`), y además de `r.supervisor_id` revisa `r.supervisores_extra` (el caso de Competencia con dos supervisores) para no dejar fuera esos registros. CSV con **Supervisor como primera columna** (nombre real, "Sin asignar" si no tiene), luego Fecha, Punto de venta, Actividad, Promotor, Estado, Código.
- **Datos para los combos**: `components/historial/historial.php` arma, en PHP, la lista de supervisores activos (solo si `ep_es_admin()`) y un mapa `supervisor_id → [promotores]` (un promotor puede aparecer bajo dos supervisores si tiene `categorias='todas'`); para un supervisor normal, el mismo mapa pero ya acotado a su propio id. Se embebe como JSON en la página (mismo patrón que `ep-pdv-datos` de `paso_pdv.php`), sin ninguna llamada aparte al servidor.
- **Frontend nuevo**: `assets/js/historial-excel.js` (abrir/cerrar modal, pills Única/Rango, combos de Supervisor/Promotor con cascada 100% en el navegador a partir del JSON ya cargado, grilla de actividad de selección única) + `assets/css/historial-excel.css` (estilos propios, sin depender de `reportes.css` para no cargar CSS de más en esta vista). Registrados en `index.php` (`$hojasTodas`/`$scriptsTodos`/`$porVista['historial']`).
- **Probado en vivo (03-10)**: como admin, elegir supervisor Cristhian acotó el combo de Promotor a sus 9 promotores (antes mostraba todos); elegir a Pablo Castelo y descargar dio un CSV con exactamente sus 4 registros, los 7 con "VELASTEGUI AZUERO CRISTHIAN ADRIAN" en la primera columna. Como Cristhian (supervisor), la columna Supervisor del modal no aparece, el combo de Promotor ya viene acotado a su equipo sin elegir nada, y descargar sin filtros trajo exactamente sus 5 registros reales (confirmado con una lectura directa del endpoint). Solo lectura, no se creó ni modificó ningún dato de prueba.

## PPT: faltaba la portada de Grupo Lucky, solo salía la de Epson (2026-10-03, corregido y probado en vivo)

- **Reportado por el usuario** con una captura de la plantilla real: debían salir dos portadas en orden — 1) logo de **Grupo Lucky**, 2) logo Epson + línea + título del mes (tal como están armadas las plantillas en `recursos/ppt/`) — pero el PPT generado solo traía la de Epson.
- **Causa**: el fix del 02-10 ("PPT: portada única con logo, línea y título") asumió que la diapositiva 1 de las plantillas era la vieja diapositiva de rombos que había que quitar, y la ocultaba para todos los tipos salvo Competencia (`portada_doble => true`, su único caso con dos diapositivas). Las plantillas cambiaron desde entonces: la diapositiva 1 ya no es la de rombos, es el logo de Grupo Lucky — y el código la seguía ocultando por error, dejando solo la de Epson.
- **Arreglo**: `includes/ppt_motor.php`, `ep_ppt_abrir()` — `$unica` (la bandera que oculta la diapositiva 1) pasa de `empty($spec['portada_doble'])` a `false` fijo: las dos portadas se quedan siempre, para los 8 tipos. Se quitó `'portada_doble' => true` de `includes/ppt_competencia.php` (ya no hace nada, todos se comportan igual ahora).
- **Probado en vivo (03-10)**: se armó un PPTX real completo (no "solo_registro", que nunca lleva portadas) llamando directo a `ep_ppt_activaciones()` con el registro real `RACPABLOCASTELO-005` — salieron 4 diapositivas: Grupo Lucky, Epson + "ACTIVACIONES / OCTUBRE 2026", estadísticas y fotos, en ese orden, confirmado exportando cada una a imagen con PowerPoint. No se guardó ningún reporte ni se tocó la base.

## Excel de Calendario e Historial: .xlsx real, no CSV (2026-10-04)

- **Corrección del usuario**: los dos exportables de hoy (Calendario de Activaciones e Historial) habían quedado en CSV ("Excel lo abre nativo, sin librerías nuevas" — razonamiento propio, no lo que pidió el cliente). El cliente pidió explícitamente un `.xlsx` real.
- **`includes/xlsx_motor.php`** (nuevo): arma un `.xlsx` real con `ZipArchive`, igual que los PPTX de todo el proyecto — sin ninguna librería externa (PhpSpreadsheet, etc.), mismo criterio de "sin dependencias nuevas" que ya tiene el repo, solo que ahora resuelto escribiendo el OOXML mínimo a mano en vez de delegar a CSV. Una sola hoja, encabezado en negrita (`ep_xlsx_generar($encabezados, $filas)`), celdas de texto embebido (`t="inlineStr"`, sin tabla de `sharedStrings` para no complicarlo). `ep_xlsx_descargar()` manda las cabeceras y borra el temporal.
- **`getters/calendario_excel.php`** y **`getters/historial_excel.php`**: cambian de `fputcsv`/`text/csv` a `ep_xlsx_generar()`/`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, mismo contenido y columnas de antes. El JS del modal de Historial no necesitó ningún cambio (ya apuntaba al mismo endpoint, solo cambió qué devuelve).
- **Probado en vivo (04-10)**: se abrieron los dos `.xlsx` descargados con Excel real (COM), sin ningún aviso de reparación — Historial con sus 24 filas y columnas correctas, Calendario con "CHRISTIAN VELASTEGUI" y "Sí" en Ejecutado tal como debía.

## Aprobaciones, Reportes mensuales, Calendario y Auditoría de verdad en vivo, rápido (2026-10-04, probado en vivo)

- **Pedido del usuario**: solo 3 supervisores y 3 admins van a usar esto, así que no hay problema de carga en sondear seguido — quería que estas 4 pantallas se actualizaran solas, al instante, al enviarse un registro o guardarse algo, sin tener que recargar a mano.
- **Lo que había**: Calendario y Auditoría ya tenían el mecanismo "en vivo" (`assets/js/en-vivo.js`, `window.epVivo`) con el rótulo verde, pero cada 4 segundos. Historial/Aprobaciones tenían su propio refresco casero cada 3 segundos (sin rótulo visible). **Reportes mensuales no tenía ningún refresco en vivo.**
- **Aprobaciones**: se reemplazó el refresco casero de `assets/js/historial.js` por el mismo `window.epVivo()` que ya usan Calendario/Auditoría (más consistente, con pausa automática si la pestaña está oculta y redirección al login si la sesión se cerró), y se agregó el rótulo "En vivo" junto al título (`components/historial/historial.php`, solo admin/supervisor) — aplica tanto a Aprobaciones como al Historial normal.
- **Reportes mensuales**: se armó desde cero, con el mismo patrón que ya usa Historial. `components/reportes/filas.php` (nuevo) saca las tarjetas a un parcial reusable (antes vivían inline en `reportes.php`); `getters/reportes_filas.php` (nuevo) lo re-renderiza para el refresco; `getters/reportes_vivo.php` + `ep_vivo_reportes()` (`includes/en_vivo_datos.php`, nueva) dan la firma liviana (total + último id, con el mismo alcance por supervisor que `ep_reportes_listar()`); `assets/js/reportes-lista.js` ganó `refrescar()` + `window.epVivo()`, y el rótulo "En vivo" se sumó al título.
- **Todos a 1.2 segundos**: Calendario y Auditoría bajaron de 4000ms a 1200ms; Aprobaciones/Historial y Reportes mensuales arrancan ya en 1200ms. Confirmado que el intervalo real del navegador es 1200ms en punto (medido con las peticiones reales a `historial_firma.php`, no solo de oídas).
- **Probado en vivo (04-10)**: con Pablo Castelo enviando un registro mientras el admin estaba parado en Aprobaciones, apareció solo sin recargar (confirmado por el conteo de filas, no solo visualmente). Con el mismo registro aprobado y armado en un reporte mensual mientras el admin estaba parado en Reportes mensuales, la tarjeta nueva apareció sola en 1.56 segundos. Datos de prueba eliminados al terminar.

## Cierre de sesión por inactividad: 21 minutos y ya no depende del servidor (2026-10-04)

- **Reportado por el usuario**: la sesión se cerraba más rápido de lo esperado, incluso antes de cumplirse los 20 minutos configurados.
- **Causa probable**: `EP_MINUTOS_INACTIVIDAD` (nuestra propia regla, en `includes/functions.php`) nunca es lo único que decide esto — PHP tiene su propio `session.gc_maxlifetime` que, si el `php.ini` del servidor trae un valor más corto, puede borrar el archivo de sesión por su cuenta antes de que nuestra lógica llegue a evaluar nada. Nunca se había fijado explícito, así que dependía de lo que Azure trajera por defecto.
- **Arreglo**: `config.php` (que corre antes de cualquier `session_start()`, en cada entrada) ahora fija `ini_set('session.gc_maxlifetime', 3600)` — una hora, con margen de sobra — para que nuestra propia regla de inactividad sea la única que realmente cierra la sesión, sin importar la configuración del servidor. `EP_MINUTOS_INACTIVIDAD` pasó de 20 a 21 (pedido explícito del usuario).
- Esto se suma a lo que ya evita cerrar la sesión de los admins/supervisores sin querer: ahora que Aprobaciones, Reportes mensuales, Calendario y Auditoría sondean cada 1.2s con `ep_login_check()` (que de por sí refresca `ult_interaccion` salvo que se le pida lo contrario, como hace `sesion_verificar.php`), con cualquiera de esas 4 pantallas abierta la sesión no debería cerrarse sola mientras siga viva la pestaña — antes Aprobaciones y Reportes mensuales no sondeaban nada, así que mirarlas sin tocar el mouse por más de 20 minutos sí cerraba la sesión de verdad.

## Columnas del Excel de Historial: solo las pedidas, en ese orden (2026-10-04, probado en vivo)

- **Pedido del usuario**: exactamente Supervisor, Actividad, Fecha, Punto de venta, Ciudad, Promotor, Estado — ni una más (se quitó Código, que estaba antes) ni en otro orden.
- `getters/historial_excel.php`: cambió el orden del `array_map` y el encabezado de `ep_xlsx_generar()` para que calcen exactamente; se sumó `Ciudad` (ya vivía en cada registro armado, `$r['ciudad']`, no hacía falta nada nuevo) y se quitó `Código`.
- **Probado en vivo (04-10)**: descargado y abierto con Excel real (COM) — encabezado y primera fila de datos confirmados columna por columna, 7 columnas en total, en el orden pedido.

## Cookie de sesión persistente 30 días, no "hasta cerrar el navegador" (2026-10-04)

- **Pregunta del usuario**: "como hacemos que ya no se cierre automáticamente mi página, como lo hacen las páginas profesionales?"
- **Causa real encontrada**: `session_set_cookie_params(0, ...)` en los ~40 archivos de entrada hacía que la cookie `PHPSESSID` fuera de "sesión" (vive solo mientras el proceso del navegador sigue abierto). En celular el sistema operativo mata el proceso del navegador en segundo plano todo el tiempo — eso borra la cookie aunque el usuario solo haya minimizado la app, mucho antes de que se cumplieran los 21 minutos de inactividad reales.
- **Cómo lo hacen las páginas profesionales**: la cookie dura semanas/meses (persistente, sobrevive a cerrar el navegador), y es la lógica del SERVIDOR (última interacción, expiración por inactividad) la que de verdad decide cuándo cerrar la sesión — no la vida de la cookie.
- **Arreglo**: nueva constante `EP_COOKIE_VIDA` en `config.php` (30 días = `60*60*24*30`), usada en los ~40 `session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true)` en vez del `0` anterior. Quien sigue cerrando la sesión de verdad es `EP_MINUTOS_INACTIVIDAD` (21 min, `includes/functions.php`), sin cambios ahí.
- **Probado en vivo (04-10)**: login real contra el servidor local — `Set-Cookie` confirmado con `Max-Age=2592000` (30 días) en vez de cookie de sesión sin Max-Age.

## Modal de Excel: fecha obligatoria y nombre de archivo por actividad (2026-10-04)

- **Fecha obligatoria**: antes se podía descargar sin tocar el campo Fecha (se exportaban los 5000 registros sin filtro de fecha). Ahora `assets/js/historial-excel.js` valida antes de descargar: en modo Única hace falta "Desde", en Rango hacen falta "Desde" y "Hasta"; si falta, no navega y marca el recuadro de fecha en rojo (`.ep-hex-fecha-caja.error`, `assets/css/historial-excel.css`). Se quitó la nota vieja del modal que decía el orden de columnas (`.ep-hex-nota`, en `components/historial/historial.php`) porque ya no calzaba con el orden real.
- **Nombre del archivo = la actividad elegida**, no "historial" genérico: `getters/historial_excel.php` ahora busca el label de `ep_logicas()` para el `tipo` pedido (ej. `activaciones_2026-10-04.xlsx`, `colocacion_de_pop_2026-10-04.xlsx`); si no se eligió actividad (Todas), sigue siendo `historial_AAAA-MM-DD.xlsx`.
- **Ojo con tildes en nombres de archivo**: `iconv('UTF-8','ASCII//TRANSLIT', ...)` en este entorno mete apóstrofes raros en palabras con tilde (p.ej. "fotográfico" → "fotogr'afico", quedando partido tras el slug). Se resolvió mapeando a mano las tildes españolas (`strtr` con á/é/í/ó/ú/ñ/ü) antes de limpiar el string — si se necesita quitar tildes para un nombre de archivo en otro lado del proyecto, usar el mismo mapeo manual, no `iconv//TRANSLIT`.
- **Probado en vivo (04-10)**: descarga real por `curl` para activaciones, competencia, informe-fotografico, capacitaciones y colocacion-pop — nombres de archivo confirmados uno por uno en el header `Content-Disposition`, todos limpios.

## Excel de Competencia: todos los puntos de venta en la misma fila, separados por coma (2026-10-04)

- **El problema**: Competencia guarda un solo registro con varios puntos de venta dentro de `puntos` (JSON) — el nivel superior del registro (`punto_venta`, `ciudad`) solo trae el PRIMER punto (mismo patrón que ya se ve en el detalle del Historial: "ANDYCOMP - SOLANDA + 2 puntos más"). Sin arreglo, el Excel hubiera mostrado solo el primer punto por fila, perdiendo los demás.
- **Decisión del usuario**: una sola fila por registro (no una fila por punto, como sí hace el PPT), pero las columnas "Punto de venta" y "Ciudad" listan TODOS los puntos separados por coma.
- `getters/historial_excel.php`: nueva función `$listaPuntos($r, $campo)` — si el registro tiene `puntos` (array), junta ese campo de cada uno con `', '`; si no (cualquier otro tipo de actividad), usa el valor normal de siempre. Se usa para `Punto de venta` y `Ciudad`.
- **Probado en vivo (04-10)**: registro real de Competencia con 2 puntos (ADVANCE - 9 de Octubre en Guayaquil + ARTEFACTA - 25 de Junio en Machala) enviado por Pablo Castelo vía Playwright; Excel descargado y verificado abriendo el XML interno del .xlsx — la fila trae `"ADVANCE - 9 DE OCTUBRE, ARTEFACTA - 25 DE JUNIO"` y `"GUAYAQUIL, MACHALA"`. Registro de prueba eliminado al terminar.

## Colocación de POP: cómo trabaja realmente el cliente vs. lo que hace la app (2026-10-05, análisis; superado en parte por lo construido el 06-10, ver más abajo)

- **Fuente**: correos de Epson/Lucky (campaña HOME ECUADOR, sep-2026) y `epson/POP/ECUADOR CAMPAÑA HOME ACT- 11-09-2026.xlsx` (hojas MARCOM-ECUADOR, retail, canal, DIRECCIONES ENTREGA PROMOTORES). Actores: Epson (Julián Tobar, Carolina Riofrío, Danny García), Fabricio Luzarraga (distribución en canal), Lucky Quito (bodega), Marco Salazar (entregas).
- **Cómo trabajan ellos**:
  - Cada campaña tiene un catálogo fijo de materiales: tendcard, afiche, roll-up, dangler/escudo, banderines (líneas de 10), cuadríptico, volante cotizador, hablador, glorificador, ruma sola nueva, ruma rebrandeo, kit de ruma con arco, solo arco rebrandeo y stand desmontable.
  - Epson asigna cantidades por **canal**: QTY Retail, QTY Canal Distry y QTY Bodega (reserva mínima para reponer material roto o desgastado). Total Ecuador = suma de las tres.
  - Cada cantidad se reparte por **ciudad/provincia** (Quito, Guayaquil, Cuenca, Santo Domingo, Manabí, Machala): es un plan de distribución, no una lista por punto de venta (ej. 245 tendcards Retail = 88 + 101 + 14×4).
  - Cada línea lleva un **comentario de estado** ("ok", "PENDIENTE POR ENTREGAR", "en bodega esperando direcciones", "se rebrandea al terminar BTS").
  - Los promotores de provincia reciben el material en direcciones concretas (logística fuera de la app); el material llega y se devuelve por tandas (banderines llegaron después, volvieron a bodega tras eventos y se recontaron).
  - Dos tipos de material mezclados: de **conteo** (tendcard, afiche, volantes) y de **proceso** (rumas, kits con arco, stands: rebrandeo, entrega con calendario).
- **Lo que la app hace distinto o no cubre** (hoy: un registro de POP = un punto de venta con campaña y materiales en texto libre; el reporte mensual suma Canales/Retail desde esos registros y el admin escribe solo Bodega):
  1. No hay **campaña como catálogo**: "Tendcard", "TENDCARD" y "Tend card" son materiales distintos y no se puede comparar contra lo asignado por Epson.
  2. No se guarda el **plan por canal** (Retail / Distry / Bodega como cantidades asignadas); la app los calcula sumando lo colocado por punto de venta, y para el cliente son cantidades planificadas, no sumas de lo ejecutado.
  3. No existe el **plan por ciudad**; el modelo por punto de venta no sirve para materiales masivos (nadie registra 10.000 volantes ni 2.850 cuadrípticos punto por punto).
  4. No hay **estado por material** (ok, pendiente, en bodega, en rebrandeo).
  5. "Recibido" es un solo número del admin: no hay recepción por tandas ni devoluciones.
  6. Materiales de conteo y de proceso comparten una sola cantidad.
- **Dirección propuesta (pendiente de confirmar con el cliente)**: maestro de campaña (material, tipo conteo/proceso, cantidad por canal y por ciudad, estado por línea) y registros de promotores para medir cumplimiento (planificado vs. colocado).
- **Preguntas abiertas antes de diseñar**: quién carga el plan (¿admin o Fabricio pegando el Excel?); si el promotor registra por punto de venta todos los materiales o solo algunos (tendcard, dangler, stand, ruma) y cómo se tratan los volantes a granel; qué espera ver Epson en el PPT (asignado por canal y ciudad con estado, colocado por punto de venta, o ambos); frecuencia del reporte (por campaña o mensual con tandas); si rumas, kits y stands necesitan seguimiento propio con estados.
- **Ojo con el Excel**: trae artefactos (`#VALUE!`, 0.8235 de roll-up proporcional en Guayaquil) y "VOLANTE COTIZADOR" Retail con 10000 en un resumen y 1000 en la hoja retail; no copiar sus fórmulas sin validar.

## Colocación de POP: decisiones tomadas con el usuario (2026-10-06, diseño; la reunión del mismo día lo simplificó, ver la sección construida más abajo)

- **Dueño del flujo: Fabricio Luzarraga** (supervisor de canales) más el admin. Según los correos, es quien trata con Epson (Julián, Carolina, Danny) y con Marco (entregas), envió la distribución de Retail **y** Distry y las direcciones de los promotores de provincia; Cristhian y Andrea no aparecen en ningún correo de POP. POP es un módulo transversal: no sigue la regla "cada supervisor ve solo su canal".
- **Quién crea la campaña**: el supervisor (Fabricio), no el admin por defecto. El admin ve y puede hacer todo. Carga el plan pegando la tabla del Excel (material × canal × ciudad, con reserva de Bodega y estado por línea).
- **Quién registra**: los promotores que Fabricio designe para esa campaña (ejemplo real: la hoja DIRECCIONES ENTREGA PROMOTORES, con Karina Palas/Machala, Tatiana Morocho/Cuenca, Gabriela Orrala/Manta y Jonathan Celin/Santo Domingo). Quito y Guayaquil los maneja Lucky desde oficina, no promotores de provincia (supuesto, por confirmar).
- **Quién aprueba**: Fabricio aprueba todos los registros de POP, aunque el promotor reporte a otro supervisor en retail (Karina y Gabriela reportan a Cristhian y Jonathan a Andrea para sus registros de Retail). Implica que un registro de POP lleva como `supervisor_id` al dueño de la campaña y no el que da `ep_supervisor_de_fila()`.
- **Flujo propuesto**: (1) Fabricio crea la campaña con catálogo de materiales, plan por canal/ciudad, reserva de Bodega, estados y fecha límite; (2) designa promotores y la app calcula lo que le toca a cada uno; (3) el promotor ve el aviso en la campana (mecanismo de Activaciones); (4) registra por tienda eligiendo el material del catálogo, con cantidad y 3 fotos; (5) Fabricio aprueba o devuelve; (6) cada aprobado descuenta de lo asignado y se ve el avance por promotor, ciudad y material; (7) al cerrar, se arma el reporte para Epson (recibido, detalle por tienda, fotos) con Bodega/Canales/Retail/Disponible calculados.
- **Fases**: 1) campaña, catálogo y plan; 2) asignación y avisos; 3) seguimiento y avance; 4) reporte automático. Cada una sirve por sí sola.
- **Sin confirmar con el cliente**: que Epson quiera esto (la evidencia solo prueba el formato del PPT y la hoja de control interno de Fabricio); cómo tratar rumas, kits y stands (por ahora, una línea con comentario); si los volantes a granel se registran o solo se descuentan del plan.

## Colocación de POP rehecha según la reunión 06-10-2026 (SIN PROBAR en navegador)

- **Origen**: `docs/grabaciones/06-10-2026 16.44.txt`. El modelo viejo (campaña y material como texto libre del promotor, y Bodega escrita a mano por el admin en el paso final del reporte) no era lo que el cliente quería. Ahora son dos lados: el gestor carga el inventario antes, el promotor solo da de baja.
- **Quién lo maneja**: una sola persona arma el reporte mensual (Fabricio). No hay programación por promotor ni por punto como en Activaciones: el cliente fue explícito en que **no es un calendario** — él carga valores, avisa a los suyos que reporten, y durante el mes las cantidades se van sumando solas.
- **Tablas nuevas** (`insert_reporte_pop` + `insert_reporte_pop_fila`, creadas el 06-10): mismo patrón que `insert_reporte_calendario` — entidad propia con estado activo/cerrado que al cerrarse genera un reporte mensual y lo apunta con `reporte_mensual_id`. Se evaluó reusar `insert_reporte_mensual` con una bandera dentro de `snapshot` y **se descartó**: pondría estado de negocio en un JSON no indexable, escribiría sobre `snapshot` (que existe para NO cambiar) y mezclaría dos ciclos de vida en una tabla.
- **Qué carga el gestor** (tab "Colocación de POP" dentro de Calendario, `components/calendario/pop.php` + `assets/css/calendario-pop.css` + `assets/js/calendario-pop.js`): material + campaña + **Bodega** (lo que llegó). Nada más: en el Excel oficial (`epson datos/.../COLOCACION DE POP/ESTADISTICOS COLOCACION DE POP.xlsx`) las columnas Canales y Retail son **lo que reportan los promotores** y Disponible = Bodega − reportado, así que se calculan en vivo y no se escriben.
- **Un solo mes abierto a la vez** (`ep_pop_mes_ocupado()`): con dos vivos el promotor vería materiales de dos inventarios y el match quedaría ambiguo. Se puede corregir el mes abierto, pero **no quitar un material que ya tenga reportes** (dejaría ese registro sin bodega en el PPT).
- **Formulario del promotor** (`components/actividades/formularios/colocacion-pop.php`): el paso de Campaña desapareció. El material sale del mes abierto en el combo propio del proyecto (`.ep-combo`, con la campaña como nota a la derecha), más la cantidad. Sin límite ni filtro por canal: **POP trabaja con todos los canales** (el cliente lo confirmó), y si un promotor envía varios reportes **todo se suma, nada se bloquea**. Las 3 fotos de "correcta implementación" y el asistente quedan igual que siempre.
- **La campaña va por material**, no por registro (`pop_entregas[] = {material, cantidad, campana}`): un mes puede tener dos campañas vivas y un registro mezclar materiales de ambas. `ppt_colocacion_pop.php` y la lista usan `$e['campana'] ?? $reg['campana']`, así los registros anteriores se siguen leyendo.
- **Aviso en la campana** (`ep_avisos_pop()` en `avisos_datos.php`): mientras haya un mes abierto en el que ese promotor no haya enviado **ningún** registro de POP, le sale el aviso. El plazo es el fin de mes; urgente a 3 días o menos.
- **Cierre**: `ep_pop_cerrar()` devuelve `?int` con la misma convención que `ep_calendario_generar_ahora()` (null = no cerró, 0 = cerró sin reporte, >0 = id del reporte) y congela la bodega en `snapshot.pop_bodega`, la clave que `reporte_descargar.php` ya le pasaba al generador. **El PPTX no se tocó**: las dos tablas (POP RECIBIDO y DETALLE) ya estaban construidas, solo cambió de dónde sale la bodega.
- **Fuera del armado manual**: `reportes.php` saca Colocación de POP de `$actividadesManual` (igual que Activaciones). Con eso quedó muerto y **se eliminó** el paso de bodega del reporte manual: `assets/js/reportes-pop.js` completo, la tarjeta `#epRpPopBodega` de `paso_final.php`, sus 4 referencias en `reportes.js`, la rama `pop_bodega` de `reporte_guardar.php`, las 8 reglas `.ep-rp-pop*` de `reportes.css` y el script en `index.php`.
- **Getters**: `pop_crear.php`, `pop_editar.php`, `pop_cerrar.php`, `pop_eliminar.php` (POST, admin o supervisor, cada uno con `ep_pop_permitido()`; un supervisor solo toca los meses que él creó). Todo queda en Auditoría (`pop_crear`, `pop_editar`, `pop_cerrar`, `pop_cerrar_sin_reporte`, `pop_eliminar`); `_sin_reporte` ya se clasificaba como alerta naranja.
- **Rendimiento**: `ep_pop_entregado_todos()` recorre los registros **una sola vez** y agrupa por mes; la primera versión leía por mes y hacía una pasada completa por cada uno de la lista.
- **Qué quedó fuera a propósito, contra las dos secciones de análisis de arriba**: el cliente simplificó el alcance en la reunión del 06-10, así que NO se construyó el plan por ciudad (dijo textual que él carga el bruto y reparte por ciudad por su cuenta; el Excel oficial de POP RECIBIDO tampoco tiene ciudades), ni el estado por línea (ok / pendiente / en bodega / rebrandeo), ni la designación de promotores por campaña, ni la distinción entre material de conteo y de proceso (rumas, kits, stands). El usuario fue explícito: "no hacemos un calendario así como activaciones", sin columna de promotor, sin límites y sin filtro de canales.
- **Hueco conocido, sin resolver**: la sección de diseño del 06-10 decidió que **Fabricio aprueba todos los registros de POP** aunque el promotor reporte a otro supervisor en Retail (Karina y Gabriela a Cristhian, Jonathan a Andrea). Eso NO está construido: un registro de POP sigue la ruta normal de `ep_supervisor_de_fila()`, así que un POP en punto Retail de Karina le llega a Cristhian, no a Fabricio. Hay que decidir con el usuario si se fuerza el `supervisor_id` al dueño del mes de POP.
- **Probado**: capa de datos y render de las dos pantallas contra la base real en solo lectura (mes abierto, materiales, validación de filas repetidas y vacías, estados vacíos). **Falta probar el flujo completo en navegador**: cargar un mes, que el promotor lo vea en el combo, enviar, aprobar, ver subir Canales/Retail y bajar Disponible, cerrar el mes y abrir el PPTX.
