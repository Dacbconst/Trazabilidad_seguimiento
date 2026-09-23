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

### Módulo 2: Registros de Actividades (`index.php?vista=historial`)
- **Propósito**: Módulo de auditoría, reportería y visibilidad ejecutiva.
- **Estructura y Características**:
  - **Filtros Dinámicos**: Por tipo de actividad (con contadores en vivo), rangos de fecha rápidos (*Hoy*, *7 días*, *Este mes*, *Todo*), fecha puntual y barra de búsqueda predictiva en tiempo real.
  - **Agrupación Cronológica**: Reportes ordenados por fecha y hora de intervención.
  - **Cabeceras de Registro**: Identificación clara del Punto de Venta, Cadena (Mall del Sol, Sukasa, Marcimex, etc.), Promotor responsable, Ciudad, y Badges de estado (`Aprobado`, `En revisión`, `Completado`).
  - **KPIs Inmediatos**: Chips destacados con porcentajes de cobertura, totales de asistentes, modelos activados o balances POP.
  - **Detalle Desplegable**: Desglose campo por campo de la información reportada, métricas calculadas y comentarios de campo.
  - **Galería Fotográfica y Visor Lightbox**: Muestra las evidencias fotográficas requeridas con sus respectivas etiquetas de verificación (*Fachada*, *Exhibición*, *POP*, etc.) y permite ampliar cualquier imagen en un modal interactivo para inspección detallada.

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
