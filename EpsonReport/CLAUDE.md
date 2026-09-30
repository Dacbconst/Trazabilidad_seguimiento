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

## Reunión 28-09-2026: pendientes grandes (sin construir)

- Registrado en `docs/grabaciones/28-09-2026.txt`. Quedan pendientes: descarga consolidada de varios reportes, y el bloque grande de rol Supervisor como perfil de sesión propio (hoy el Calendario asume que el admin hace todo lo que haría un supervisor). El "informe fotográfico simple" ya se construyó, ver sección siguiente.

## Lógica "Informe Fotográfico Simple" (2026-09-30, SIN PROBAR en navegador)

- Séptima lógica base (`ep_logicas()` en `includes/actividades_datos.php`, id 7, plantilla `informe-fotografico`, `sin_estadisticas => true`): solo punto de venta (ya universal para toda actividad, vía `paso_pdv.php`) y fotos, sin ningún campo cuantitativo ni paso de "Datos de la actividad" (no pide tipo/fecha/hora). Pensada para que el admin cree con ella los botones "Exhibiciones Regulares" y "Competencia" que pidió el cliente (Retail y Canales no llevan botones separados: el canal ya sale del punto de venta elegido, igual que en las demás actividades).
- **Fotos** (`includes/fotos_datos.php`): 1 obligatoria + 5 opcionales (`foto-1`..`foto-6`). El cliente pidió "sin mínimo, pueden subir 50.000 fotos"; se usó el mecanismo de casillas fijas que ya tiene todo el proyecto (obligatorias + opcionales) en vez de construir un uploader de galería abierta — es una simplificación consciente, más barata y sin tocar el motor de fotos compartido por todas las actividades. Si el cliente de verdad necesita un número no acotado de fotos, eso es una pieza nueva de UI a construir aparte.
- **Formulario** (`components/actividades/formularios/informe-fotografico.php`) y **estadísticas** (`components/actividades/estadisticas/informe-fotografico.php`): placeholders mínimos, mismo patrón que `colocacion-pop.php` (que tampoco tiene panel de estadísticas).
- **Sin cambios en `guardar_registro.php` ni en el detalle del Historial**: ambos ya eran genéricos por diseño (el guardado no exige campos cuantitativos si el tipo no los pide, y el detalle muestra fotos y comentarios sin importar el tipo) — la nueva lógica encaja sin tocar ninguno de los dos.
- Código de registro con prefijo propio `RFOT` (`ep_codigo_registro()`) e ícono `camera` (`ep_icono_tipo()`).
- **Bug encontrado y corregido de paso**: `tipoActividadActiva()` en `app.js` (usada al subir cada foto a Azure) adivinaba el tipo buscando palabras en el NOMBRE del botón ("exhibi", "pop", etc.) en vez de leer `data-plantilla` (que el botón ya trae). Con un botón llamado "Exhibiciones Regulares" esa función habría subido las fotos a la carpeta de Azure de `exhibiciones` en vez de `informe-fotografico`. Ahora lee `data-plantilla` directo.
- **Falta que el usuario haga, desde la app** (no requiere tocar la base, es uso normal del Constructor): crear los botones "Exhibiciones Regulares" y "Competencia" desde "+ Nueva actividad" eligiendo la lógica "Informe Fotográfico Simple".
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
1. ~~4 botones de "informe fotográfico simple"~~ → lógica construida, ver "Lógica 'Informe Fotográfico Simple'"; falta que el admin cree los botones "Exhibiciones Regulares" y "Competencia" desde el Constructor (revisado el 30-09: todavía no existen en `insert_reporte_actividad`).
2. ~~Descarga consolidada de varios registros en uno solo~~ → construida, ver "Descarga consolidada de registros (2026-09-30)".
3. **Pendiente real**: Rol Supervisor como perfil de sesión propio (hoy no existe: solo `usuario`/`admin` en `$_SESSION['rol']`) — el Calendario ya construido asume que el admin hace todo lo que haría un supervisor; falta decidir si el supervisor es un rol de acceso distinto o solo una etiqueta dentro de admin.
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
- **Foto**: requiere la columna nueva `foto` (SQL abajo, lo corre el usuario en HeidiSQL). Se sube a Azure en `Usuarios/<id>_<fecha>.jpg` reducida a 400 px (`getters/usuario_foto.php`) y se muestra en la lista y en el avatar del menú lateral (`ep_usuario_foto_actual()`). Sin la columna, todo lo demás funciona y subir foto responde "Falta la columna foto". La foto elegida solo se previsualiza en el panel y se sube al pulsar Guardar cambios (Cancelar o cerrar la descarta); tocar la foto abre una vista ampliada compartida (`assets/js/zoom-foto.js` + estilos `.ep-zoom-foto` en `shell.css`, cargada en todas las vistas; `window.epZoomFoto(url, nombre)`). También se abre al tocar el avatar con foto del menú lateral (en vez de plegar el menú). Esc, clic en el fondo o la X la cierran.
- **Getters** (POST, solo admin, responden JSON): `usuario_guardar.php` (crear sin id, editar correo/rol con id), `usuario_clave.php`, `usuario_estado.php`, `usuario_foto.php`.
- **Auditoría**: cada acción queda anotada (`usuario_crear/editar/clave/foto/activar/desactivar`); la clave nunca se guarda en el detalle.
- **SQL pendiente**: `ALTER TABLE repositorio_usuarios_reporte ADD COLUMN foto VARCHAR(255) NULL AFTER correo;`
