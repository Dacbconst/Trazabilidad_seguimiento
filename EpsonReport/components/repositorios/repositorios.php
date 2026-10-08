<?php
// Repositorios (admin y Fabricio): un solo repositorio de POP con dos listas independientes, Material y Campaña, de las que se elige al cargar el mes.
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/pop_reparto.php';
require_once __DIR__.'/../../includes/pop_catalogo.php';

if (!ep_pop_es_dueno()) {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$catalogo = ep_pop_catalogo();
?>
<main class="ep-content ep-repo" id="epRepo">

	<header class="ep-repo-head">
		<div>
			<h1>Repositorios</h1>
			<p>Listas de las que se elige al cargar el mes de Colocación de POP.</p>
		</div>
	</header>

	<div class="ep-repo-tabs" role="tablist" aria-label="Repositorios">
		<button type="button" class="ep-repo-tab activo" role="tab" aria-selected="true"><?= ep_icon('tag', 16) ?>Material y Campaña<span class="ep-repo-count" id="epRepoCuenta"><?= count($catalogo['materiales']) + count($catalogo['campanas']) ?></span></button>
		<div class="ep-repo-indicador" id="epRepoIndicador"></div>
	</div>

	<section class="ep-repo-card">
		<div class="ep-repo-filtros">
			<label class="ep-repo-buscar">
				<?= ep_icon('search', 18) ?>
				<input type="search" id="epRepoBuscar" placeholder="Buscar..." autocomplete="off" aria-label="Buscar en el repositorio">
			</label>
			<div class="ep-repo-acciones">
				<a class="ep-repo-btn" href="getters/repositorio_plantilla.php"><?= ep_icon('download', 16) ?>Descargar Formato</a>
				<button type="button" class="ep-repo-btn ep-repo-btn-p" id="epRepoAgregar"><?= ep_icon('upload', 16) ?>Subir Archivo</button>
			</div>
		</div>
		<div class="ep-repo-columnas">
			<div class="ep-repo-col">
				<div class="ep-repo-fila ep-repo-th"><span id="epRepoTituloMaterial">Material</span><span></span></div>
				<div id="epRepoListaMaterial"></div>
			</div>
			<div class="ep-repo-col">
				<div class="ep-repo-fila ep-repo-th"><span id="epRepoTituloCampana">Campaña</span><span></span></div>
				<div id="epRepoListaCampana"></div>
			</div>
		</div>
	</section>

</main>

<div class="ep-modal-ppt hidden" id="epRepoModal" role="dialog" aria-modal="true" aria-labelledby="epRepoModalTitulo">
	<div class="ep-modal-ppt-backdrop" id="epRepoModalFondo"></div>
	<div class="ep-modal-ppt-dialog ep-repo-dialog">
		<div class="ep-modal-ppt-head">
			<div class="ep-modal-ppt-head-left">
				<div class="ep-modal-ppt-icon"><?= ep_icon('tag', 20) ?></div>
				<div>
					<h2 id="epRepoModalTitulo">Subir Material y Campaña</h2>
					<p>Sube el formato lleno o agrega uno por uno.</p>
				</div>
			</div>
			<button type="button" class="ep-modal-close-btn" id="epRepoModalCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
		</div>
		<div class="ep-modal-ppt-body">
			<label class="ep-repo-archivo" for="epRepoArchivo"><?= ep_icon('upload', 16) ?><span id="epRepoArchivoTxt">Subir archivo .xlsx, .csv o .txt</span></label>
			<input type="file" id="epRepoArchivo" accept=".xlsx,.csv,.txt" hidden>
			<div class="ep-repo-o"><span>o agrega uno</span></div>
			<form class="ep-repo-uno" id="epRepoUno" autocomplete="off">
				<input type="text" class="ep-input" id="epRepoUnoMaterial" maxlength="60" placeholder="Material" aria-label="Material">
				<input type="text" class="ep-input" id="epRepoUnoCampana" maxlength="40" placeholder="Campaña" aria-label="Campaña">
				<button type="submit" class="ep-repo-btn ep-repo-uno-btn" aria-label="Añadir a la lista"><?= ep_icon('plus', 16) ?></button>
			</form>
			<div class="ep-repo-prev hidden" id="epRepoPrev">
				<div class="ep-repo-prev-head"><span id="epRepoPrevResumen"></span><button type="button" class="ep-repo-limpiar" id="epRepoLimpiar">Limpiar</button></div>
				<div class="ep-repo-prev-tabla" id="epRepoPrevTabla"></div>
			</div>
			<small class="ep-repo-nota">Revisa la lista antes de guardar: solo se guarda al pulsar Agregar. Todo va en mayúsculas y los repetidos se ignoran.</small>
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
