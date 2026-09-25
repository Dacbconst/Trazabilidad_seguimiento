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
