<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");

include_once 'db_connect.php';
?>
<!DOCTYPE html>
<html>
<head>
    <?php
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Sat, 1 Jul 2000 05:00:00 GMT");
    ?>
    <meta http-equiv="cache-control" content="no-cache, must-revalidate, post-check=0, pre-check=0" />
    <meta http-equiv="cache-control" content="max-age=0" />
    <meta http-equiv="expires" content="0" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="HandheldFriendly" content="true" />
    <title>Xplora - Unilever</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://webecuador.azurewebsites.net/App/XploraEcuador/assets/js/jquery-3.2.1.min.js"></script>
    <link rel="stylesheet" href="https://webecuador.azurewebsites.net/App/XploraEcuador/assets/css/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <script src="https://cdn.jsdelivr.net/npm/pptxgenjs@3.11.0/dist/pptxgen.bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pptxgenjs@3.11.0/dist/pptxgen.min.js"></script>
    <style>
        /* Layout fijo: solo #cards-container hace scroll, todo lo demás queda quieto */
        html, body   { height: 100%; overflow: hidden; margin: 0; }
        .wrapper     { height: 100vh; }
        #sidebar     { height: 100vh; overflow-y: auto; }

        #content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            padding: 14px 20px 0;
        }

        /* Solo el bloque de cards se desplaza */
        #cards-container {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            padding-bottom: 10px;
        }
        #cards-container::-webkit-scrollbar { width: 5px; }
        #cards-container::-webkit-scrollbar-thumb {
            background: #bbb; border-radius: 3px;
        }

        /* Anular el margin:40px de .line en style.css */
        .line { margin: 8px 0; }

        #content-header {
            flex-shrink: 0;
            background: #fafafa;
            padding-bottom: 0;
        }

        /* Pestañas — neutralizar el chevron que style.css agrega a a[aria-expanded] */
        #main-tabs a::before,
        #main-tabs a[aria-expanded]::before { display: none !important; content: none !important; }

        #main-tabs { border-bottom: 2px solid #ddd; margin-top: 8px; }
        #main-tabs > li > a { color: #555; font-size: 13px; padding: 8px 16px; }
        #main-tabs > li.active > a,
        #main-tabs > li.active > a:focus,
        #main-tabs > li.active > a:hover { color: #337ab7; border-bottom-color: #fff; font-weight: 600; }

        /* Tab content como columna flex — Bootstrap maneja show/hide con display:none */
        .tab-content       { flex: 1; overflow: hidden; display: flex; flex-direction: column; }
        #tab-fotografico   { flex: 1; overflow: hidden; display: none; flex-direction: column; }
        #tab-fotografico.active { display: flex !important; }
        #tab-seguimiento   { flex: 1; overflow-y: auto; display: none; padding: 16px; }
        #tab-seguimiento.active { display: block !important; }

        /* Badge contador de selecciones */
        #selection-count {
            display: none;
            background: #337ab7;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 12px;
            white-space: nowrap;
        }

        /* Highlight de card seleccionada (misma dinámica que Exhibiciones) */
        .evid-card {
            transition: box-shadow .25s ease, transform .2s ease;
        }
        .evid-card.card-selected {
            box-shadow: 0 0 0 3px #337ab7, 0 6px 20px rgba(51,122,183,.2) !important;
            transform: translateY(-2px);
        }
        .evid-card.card-selected .evid-card-header {
            background: #2a6496 !important;
        }
        .evid-card.card-selected .evid-card-header strong,
        .evid-card.card-selected .evid-card-header span {
            color: #fff !important;
        }
        .evid-main-cb {
            width: 20px; height: 20px;
            cursor: pointer; flex-shrink: 0; margin-top: 2px;
            accent-color: #27ae60;
            transition: transform .15s ease;
        }
        .evid-main-cb:hover { transform: scale(1.2); }

        .info-msg {
            font-size: 13px;
            color: #555;
            margin: 0;
            padding: 1px 0;
            min-height: 0;
            background-color: transparent;
            transition: background-color 0.8s ease;
        }
        .info-highlight { background-color: rgba(230,230,230,0.7); }
    </style>
</head>

<body>
    <form method="post" action="">
        <div class="wrapper">

            <?php include 'partials/sidebar.php'; ?>

            <div id="content">

                <!-- Header sticky -->
                <div id="content-header">
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <button type="button" id="sidebarCollapse" class="btn btn-info navbar-btn"
                                style="flex-shrink:0;">
                            <i class="glyphicon glyphicon-filter"></i>
                        </button>
                        <div style="flex:1; min-width:0;">
                            <h2 style="margin:0; font-size:1.4rem; line-height:1.3;">
                                Reporte Fotográfico Unilever
                            </h2>
                            <div id="info_resultados" class="info-msg"></div>
                        </div>
                        <div id="action-buttons"
                             style="display:flex; gap:8px; flex-shrink:0; align-items:center; flex-wrap:wrap;">
                            <span id="selection-count"></span>
                            <button type="button" id="download2" name="download" value="download"
                                    class="btn btn-primary" disabled>
                                <i class="glyphicon glyphicon-download-alt"></i> Descargar
                            </button>
                            <!-- Un solo "Seleccionar todo" se inserta aquí por JS -->
                        </div>
                    </div>
                    <div class="line"></div>

<?php
// ================================================================
// SECCIONES DEL MÓDULO
// Para agregar una pestaña nueva:
//   1. Crear partials/tab-nueva.php
//   2. Agregar una entrada al array $tabs abajo
// Para quitar una pestaña: comentar o borrar su entrada.
// Si solo hay 1 sección, la barra de pestañas no aparece.
// ================================================================
$tabs = [
    [
        'id'      => 'tab-fotografico',
        'label'   => 'Fotográfico',
        'icon'    => 'camera',
        'partial' => 'partials/tab-fotografico.php',
        'enabled' => true,
    ],
    [
        'id'      => 'tab-seguimiento',
        'label'   => 'Seguimiento',
        'icon'    => 'time',
        'partial' => 'partials/tab-seguimiento.php',
        'enabled' => true,
    ],
];
$tabs = array_filter($tabs, fn($t) => $t['enabled']);
?>

<?php if (count($tabs) > 1): ?>
                    <ul class="nav nav-tabs" id="main-tabs" style="margin-bottom:0; border-bottom:none;">
                        <?php foreach ($tabs as $i => $tab): ?>
                        <li class="<?= $i === 0 ? 'active' : '' ?>">
                            <a href="#<?= $tab['id'] ?>" data-toggle="tab">
                                <i class="glyphicon glyphicon-<?= $tab['icon'] ?>"></i>
                                <?= $tab['label'] ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
<?php endif; ?>
                </div>

                <!-- Contenido de secciones — generado desde $tabs -->
                <div class="tab-content">
                    <?php foreach ($tabs as $i => $tab): ?>
                    <div class="tab-pane <?= $i === 0 ? 'active' : '' ?>" id="<?= $tab['id'] ?>">
                        <?php include $tab['partial']; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>

        </div>

        <script src="https://code.jquery.com/jquery-1.12.0.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

        <script type="text/javascript">
            $(document).ready(function() {
                $('#sidebarCollapse').on('click', function() {
                    $('#sidebar').toggleClass('active');
                });
            });

            // Evitar que el wheel event burbujee al iframe padre cuando el
            // mouse está sobre el contenido de Univeler (sidebar, header, etc.).
            // Solo se permite scroll natural dentro de #cards-container y #sidebar.
            document.addEventListener('wheel', function(e) {
                var enZonaScrollable = e.composedPath().some(function(el) {
                    return el.id === 'cards-container' || el.id === 'sidebar' || el.id === 'tab-seguimiento';
                });
                if (!enZonaScrollable) {
                    e.preventDefault();
                }
            }, { passive: false });
        </script>

        <?php include 'partials/filters-ajax.php'; ?>
        <?php include 'partials/pptx-export.php'; ?>

    </form>

    <?php include 'components/gallery-multiphoto.php'; ?>

</body>
</html>
