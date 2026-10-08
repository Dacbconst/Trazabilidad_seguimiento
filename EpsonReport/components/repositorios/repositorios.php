<?php
// Repositorios (admin y Fabricio): listas de materiales y campañas de POP, independientes entre sí, de las que se elige al cargar el mes.
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/pop_reparto.php';
require_once __DIR__.'/../../includes/pop_catalogo.php';

if (!ep_pop_es_dueno()) {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$catalogo = ep_pop_catalogo();
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<main class="ep-content ep-repo" id="epRepo">

	<header class="ep-repo-head">
		<div>
			<h1>Repositorios</h1>
			<p id="epRepoSubtitulo">Listas de las que se elige al cargar el mes de Colocación de POP.</p>
		</div>
	</header>

	<div class="ep-repo-tabs" role="tablist" aria-label="Repositorios">
		<button type="button" class="ep-repo-tab activo" role="tab" aria-selected="true" data-tipo="material"><?= ep_icon('tag', 16) ?>Materiales<span class="ep-repo-count" id="epRepoCuentaMaterial"><?= count($catalogo['materiales']) ?></span></button>
		<button type="button" class="ep-repo-tab" role="tab" aria-selected="false" data-tipo="campana"><?= ep_icon('megaphone', 16) ?>Campañas<span class="ep-repo-count" id="epRepoCuentaCampana"><?= count($catalogo['campanas']) ?></span></button>
		<div class="ep-repo-indicador" id="epRepoIndicador"></div>
	</div>

	<section class="ep-repo-card">
		<div class="ep-repo-filtros">
			<label class="ep-repo-buscar">
				<?= ep_icon('search', 18) ?>
				<input type="search" id="epRepoBuscar" placeholder="Buscar..." autocomplete="off" aria-label="Buscar en el repositorio">
			</label>
			<div class="ep-repo-acciones">
				<a class="ep-repo-btn" id="epRepoFormato" href="getters/repositorio_plantilla.php?tipo=material"><?= ep_icon('download', 16) ?>Descargar Formato</a>
				<button type="button" class="ep-repo-btn ep-repo-btn-p" id="epRepoAgregar"><?= ep_icon('upload', 16) ?>Subir Archivo</button>
			</div>
		</div>
		<div class="ep-repo-fila ep-repo-th"><span>Nombre</span><span>En uso</span><span></span></div>
		<div id="epRepoLista"></div>
	</section>

</main>

<div class="ep-modal-ppt hidden" id="epRepoModal" role="dialog" aria-modal="true" aria-labelledby="epRepoModalTitulo">
	<div class="ep-modal-ppt-backdrop" id="epRepoModalFondo"></div>
	<div class="ep-modal-ppt-dialog ep-repo-dialog">
		<div class="ep-modal-ppt-head">
			<div class="ep-modal-ppt-head-left">
				<div class="ep-modal-ppt-icon"><?= ep_icon('tag', 20) ?></div>
				<div>
					<h2 id="epRepoModalTitulo">Subir materiales</h2>
					<p>Sube el formato lleno o escribe un nombre por línea.</p>
				</div>
			</div>
			<button type="button" class="ep-modal-close-btn" id="epRepoModalCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
		</div>
		<div class="ep-modal-ppt-body">
			<label class="ep-repo-archivo" for="epRepoArchivo"><?= ep_icon('upload', 16) ?><span id="epRepoArchivoTxt">Elegir archivo .xlsx, .csv o .txt</span></label>
			<input type="file" id="epRepoArchivo" accept=".xlsx,.csv,.txt" hidden>
			<textarea rows="8" class="ep-input" id="epRepoTexto" placeholder="Un nombre por línea..." aria-label="Nombres a agregar"></textarea>
			<small class="ep-repo-nota">El archivo solo llena esta lista para que la revises; se guarda al pulsar Agregar. Todo va en mayúsculas y los repetidos se ignoran.</small>
		</div>
		<div class="ep-modal-ppt-foot ep-rp-foot">
			<div class="ep-rp-foot-actions" style="margin-left:auto;">
				<button type="button" class="ep-btn-subtle-compact" id="epRepoCancelar">Cancelar</button>
				<button type="button" class="ep-btn-ppt-cta" id="epRepoGuardar">Agregar</button>
			</div>
		</div>
	</div>
</div>
<script type="application/json" id="epRepoDatos"><?= json_encode(['material' => $catalogo['materiales'], 'campana' => $catalogo['campanas']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
