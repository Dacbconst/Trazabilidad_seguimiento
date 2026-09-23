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
  - Suben evidencia fotográfica obligatoria mediante el asistente guiado o slots directos.
  - Visualizan las estadísticas de su gestión calculadas en tiempo real.
- **Supervisores / Administradores (`admin`)**:
  - Cuentan con todas las facultades del promotor.
  - Tienen acceso exclusivo al panel del **Constructor de Actividades** (`#ep-panel-constructor`), donde pueden crear nuevos botones de actividad, definir su lógica a replicar, y activar/desactivar o eliminar actividades.
  - Monitorean y auditan el **Módulo de Registros**, filtrando por promotores, estados de validación y KPIs de cumplimiento.

---

## 3. Arquitectura Técnica y Stack Tecnológico

- **Backend**: PHP 8.x nativo, estructurado en módulos, sin frameworks pesados, garantizando máxima velocidad de respuesta y bajo consumo de recursos en Azure App Service.
- **Base de Datos**: Azure Database for MySQL (`luckyec_epson_nuevo`), conectada mediante PDO en [db_connect.php](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/db_connect.php).
- **Estilos**: Vanilla CSS modular en [assets/css/style.css](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/assets/css/style.css). Se prohíbe el uso de TailwindCSS u otros frameworks de utilidad ad-hoc para preservar el control exacto de la identidad corporativa.
- **JavaScript**: Vanilla JS en [assets/js/app.js](file:///c:/Users/diego/OneDrive/Desktop/trabajo/Trazabilidad_seguimiento/EpsonReport/assets/js/app.js), modularizado mediante delegación de eventos y llamadas `fetch()` asíncronas a endpoints ligeros en `getters/`.
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
- **Constructor de Actividades (Solo Admin)**:
  - Permite nombrar una nueva actividad y vincularla a una de las lógicas base.
  - Ofrece vista previa idéntica al formulario original en producción.
  - Permite el encendido/apagado de botones mediante switch de gestión.
- **Flujo de Evidencia Fotográfica en Desktop**:
  - **Tarjeta de Transición de Paso en Formulario (`.ep-desktop-foto-flow-card`)**: Conector explícito entre el Paso 1 (Datos de campo) y el Paso 2 (Fotos requeridas) con contador en vivo (`0 de X listas`), barra de progreso animada e indicador de obligatoriedad.
  - **Estudio Fotográfico Desktop en 2 Columnas (`.ep-wizard-overlay` > 900px)**:
    - *Columna Izquierda*: Checklist vertical de requerimientos obligatorios con estado interactivo (`Pendiente` / `✓ Cargada`) y navegación instantánea.
    - *Columna Derecha*: Visor amplio con esquinas HUD fotográficas Epson y **Soporte Drag & Drop nativo desde Windows** (permite arrastrar fotos directamente desde carpetas locales o WhatsApp Web al visor).
    - *Auto-Avance Guiado*: Al cargar cada fotografía, el sistema confirma visualmente con check verde y avanza automáticamente al siguiente requerimiento pendiente.

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
- **Campos Auténticos (Cero Invenciones)**:
  - Sin tags inventados como *"Auditoría Regular"*, *"Aprobado"*, *"En revisión"* ni píldoras de canales (*"Departamental"*, *"Retail"*).
  - Muestra exclusivamente los campos reales capturados en las plantillas oficiales: Cobertura, Embudo, Modelos, Cumplimiento, Asistentes por cargo, Inventario POP y Evidencias fotográficas.
- **Mecánica de Descarga PowerPoint (.pptx) (Solo Administrador)**:
  - **Ubicación Estratégica de Botones**:
    1. *Barra Principal de Herramientas*: Botón primario azul con icono de presentación (`#epBtnAbrirExportadorPPT`). Abre el configurador global.
    2. *Cabecera de Grupo de Promotor*: Botón compacto `PPT Diario` (`.ep-btn-user-ppt`). Pre-selecciona automáticamente a ese promotor específico y la fecha de sus reportes.
    3. *Fila Individual de Registro*: Botón `Slide` (`.ep-btn-record-ppt`). Pre-selecciona la actividad puntual, promotor y fecha exacta.
  - **Modal Interactivo de Exportación (`modal_exportar_ppt.php`)**:
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

## 7. Reglas de Trabajo del Asistente

1. **Control de Versiones (Git)**:
   - **NUNCA realizar `git commit` ni `git push` por iniciativa propia.** Los commits y subidas a ramas remotas deben ser solicitados o confirmados expresamente por el usuario.
2. **Resumen Obligatorio**:
   - **SIEMPRE presentar un resumen claro, conciso y ordenado** de cada acción realizada y los archivos intervenidos al responder.
