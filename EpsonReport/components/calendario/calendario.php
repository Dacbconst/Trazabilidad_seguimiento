<?php
// Calendario de Activaciones (solo admin/supervisor): cruza solo registros reales; sin borrador, crea y activa de una vez.
require_once __DIR__.'/../../includes/functions.php';

if (ep_rol_actual() !== 'admin') {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

require_once __DIR__.'/../../includes/calendario_datos.php';

// Esta pantalla puede demorar (lvi_rutero es pesada); se suelta la sesión ya para no bloquear otras pestañas o el ping de sesion-watch.js mientras carga.
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

// Antes de listar, se cierran solos los que ya vencieron (mismo camino que el botón "Generar ahora").
ep_calendario_verificar_vencidos($usuarioId);
$calendarios = array_map(function ($c) {
	$c['filas'] = array_map(fn($f) => ['fecha' => $f['fecha'], 'ciudad' => $f['ciudad'], 'pdv' => $f['punto_venta'], 'pos_id' => $f['pos_id'], 'promotor' => $f['promotor_nombre'], 'promotor_id' => (int) $f['promotor_usuario_id'], 'supervisor' => $f['supervisor_nombre'], 'estado' => $f['estado'], 'cumplido_en' => $f['cumplido_en'], 'fila_id' => (int) $f['id']], $c['filas']);
	return $c;
}, ep_calendario_listar());

$ruteroRetail = ep_calendario_rutero('RETAIL');
$ruteroCanales = ep_calendario_rutero('CANALES');

$estadoInfo = [
	'activo'  => ['label' => 'Activo',  'clase' => 'azul'],
	'cerrado' => ['label' => 'Cerrado', 'clase' => 'verde'],
];
$filaEstadoInfo = [
	'pendiente' => ['label' => 'Pendiente', 'clase' => 'gris'],
	'cumplido'  => ['label' => 'Cumplido',  'clase' => 'verde'],
];

function ep_cal_dias_restantes(?string $venceEn): ?int {
	if (!$venceEn) {
		return null;
	}
	return (int) ceil((strtotime($venceEn) - time()) / 86400);
}
$hoy = date('Y-m-d');
?>
<main class="ep-content ep-cal" id="epCal">

	<header class="ep-cal-head">
		<div>
			<h1>Calendario de Activaciones</h1>
			<p><?= count($calendarios) ?> <?= count($calendarios) === 1 ? 'calendario' : 'calendarios' ?> · el sistema cruza solo los registros que envíen los promotores</p>
		</div>
		<button type="button" class="ep-rp-nuevo" id="epCalNuevo"><?= ep_icon('plus', 16) ?> <span>Crear Calendario</span></button>
	</header>

	<?php if (empty($calendarios)): ?>
		<?= ep_estado_vacio('calendar', 'Todavía no hay calendarios', 'Crea el primero con "Crear Calendario".') ?>
	<?php else: ?>
	<section class="ep-fl-barra">
		<div class="ep-fl-combo" id="epCalComboCanal">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="listbox" aria-expanded="false">Canal <span class="ep-fl-combo-valor">Todos</span><?= ep_icon('chevron', 16) ?></button>
		</div>
		<div class="ep-fl-combo" id="epCalComboEstado">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="listbox" aria-expanded="false">Estado <span class="ep-fl-combo-valor">Todos</span><?= ep_icon('chevron', 16) ?></button>
		</div>
	</section>

	<section class="ep-cal-lista">
		<?php foreach ($calendarios as $c):
			$cumplidas = count(array_filter($c['filas'], fn($f) => $f['estado'] === 'cumplido'));
			$total = count($c['filas']);
			$pct = $total > 0 ? round($cumplidas / $total * 100) : 0;
			$dias = ep_cal_dias_restantes($c['vence_en']);
			$ei = $estadoInfo[$c['estado']];
		?>
		<article class="ep-cal-card" data-id="<?= (int) $c['id'] ?>">
			<div class="ep-cal-card-head">
				<div class="ep-cal-card-titulo">
					<strong><?= $h($c['nombre'] ?: 'Activaciones '.ucfirst(strtolower($c['canal']))) ?></strong>
					<span><?= $h(ucfirst(strtolower($c['canal']))) ?> · <?= $h(date('d/m', strtotime($c['desde']))) ?> al <?= $h(date('d/m/Y', strtotime($c['hasta']))) ?> · plazo <?= (int) $c['plazo_dias'] ?> días</span>
				</div>
				<div class="ep-cal-card-acciones">
					<span class="ep-cal-badge ep-cal-badge-<?= $ei['clase'] ?>">
						<?= $h($ei['label']) ?><?= $c['estado'] === 'activo' && $dias !== null ? ' · vence en '.($dias > 0 ? $dias.' '.($dias === 1 ? 'día' : 'días') : 'menos de 1 día') : '' ?>
					</span>
					<?php if ($c['estado'] === 'activo'): ?>
						<button type="button" class="ep-btn-subtle-compact ep-cal-generar-ahora" data-id="<?= (int) $c['id'] ?>"><?= ep_icon('presentation', 13) ?> Generar ahora</button>
					<?php endif; ?>
					<?php if ($c['estado'] === 'cerrado'): ?>
						<a href="index.php?vista=reportes" class="ep-btn-subtle-compact ep-cal-ver-reporte"><?= ep_icon('presentation', 13) ?> Ver en Reportes mensuales</a>
						<button type="button" class="ep-cal-reactivar" data-id="<?= (int) $c['id'] ?>">Reactivar</button>
					<?php endif; ?>
				</div>
			</div>

			<div class="ep-cal-progreso">
				<div class="ep-cal-progreso-barra"><div class="ep-cal-progreso-fill" style="width:<?= $pct ?>%;"></div></div>
				<span><?= $cumplidas ?> de <?= $total ?> cumplidas</span>
			</div>

			<!-- Desplegable: puede haber 100+ filas, por defecto la tarjeta queda compacta. -->
			<details class="ep-cal-desplegable">
				<summary><?= ep_icon('table', 14) ?> Ver filas (<?= $total ?>) <?= ep_icon('chevron', 14) ?></summary>
				<div class="ep-cal-tabla">
					<div class="ep-cal-tabla-head">
						<span>Fecha</span><span>Ciudad</span><span>Punto de venta</span><span>Promotor</span><span>Supervisor</span><span>Estado</span>
					</div>
					<?php foreach ($c['filas'] as $f):
						$fi = $filaEstadoInfo[$f['estado']];
						$editable = $c['estado'] === 'activo' && $f['fecha'] >= $hoy;
					?>
					<div class="ep-cal-fila<?= $editable ? ' es-editable' : '' ?>" data-fila-id="<?= (int) $f['fila_id'] ?>" data-canal="<?= $h($c['canal']) ?>">
						<span><?= $h(date('d/m/Y', strtotime($f['fecha']))) ?></span>
						<span><?= $h($f['ciudad']) ?></span>
						<span class="ep-cal-valor-pdv" data-pos-id="<?= $h($f['pos_id']) ?>"><?= $h($f['pdv']) ?><?php if ($editable): ?><button type="button" class="ep-cal-editar-campo" data-campo="pdv" aria-label="Editar punto de venta"><?= ep_icon('chevron-right', 12) ?></button><?php endif; ?></span>
						<span class="ep-cal-valor-promotor" data-promotor-id="<?= (int) $f['promotor_id'] ?>"><?= $h($f['promotor']) ?><?php if ($editable): ?><button type="button" class="ep-cal-editar-campo" data-campo="promotor" aria-label="Editar promotor"><?= ep_icon('chevron-right', 12) ?></button><?php endif; ?></span>
						<span class="ep-cal-truncado" title="<?= $h($f['supervisor'] ?: '') ?>"><?= $h($f['supervisor'] ?: '—') ?></span>
						<em class="ep-cal-pill ep-cal-pill-<?= $fi['clase'] ?>"><?= $h($fi['label']) ?><?= $f['estado'] === 'cumplido' ? ' · '.$h(date('d/m', strtotime($f['cumplido_en']))) : '' ?></em>
					</div>
					<?php endforeach; ?>
				</div>
			</details>

			<?php
				$porPromotor = [];
				foreach ($c['filas'] as $f) {
					$porPromotor[$f['promotor']] ??= ['ultima' => null];
					if ($f['estado'] === 'cumplido' && (!$porPromotor[$f['promotor']]['ultima'] || $f['cumplido_en'] > $porPromotor[$f['promotor']]['ultima'])) {
						$porPromotor[$f['promotor']]['ultima'] = $f['cumplido_en'];
					}
				}
			?>
			<details class="ep-cal-desplegable">
				<summary><?= ep_icon('users', 14) ?> Registros y Seguimiento (<?= count($porPromotor) ?>) <?= ep_icon('chevron', 14) ?></summary>
				<div class="ep-cal-seguimiento-lista">
					<?php foreach ($porPromotor as $nombre => $info): ?>
					<div class="ep-cal-seguimiento-fila">
						<span><?= $h($nombre) ?></span>
						<?php if ($info['ultima']): ?>
							<em class="ep-cal-pill ep-cal-pill-verde"><?= $h(date('d/m/Y', strtotime($info['ultima']))) ?></em>
						<?php else: ?>
							<em class="ep-cal-pill ep-cal-pill-gris">Pendiente</em>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
			</details>
		</article>
		<?php endforeach; ?>
	</section>
	<?php endif; ?>

	<!-- Modal "Crear Calendario": un solo guardado (crea y activa a la vez, no hay borrador). -->
	<div class="ep-modal-ppt hidden" id="epCalModal" role="dialog" aria-modal="true" aria-labelledby="epCalModalTitulo">
		<div class="ep-modal-ppt-backdrop" id="epCalModalFondo"></div>
		<div class="ep-modal-ppt-dialog ep-rp-dialog ep-rp-dialog-wide">
			<div class="ep-modal-ppt-head">
				<div style="display:flex;align-items:center;gap:10px;">
					<div class="ep-modal-ppt-icon"><?= ep_icon('calendar', 20) ?></div>
					<div>
						<h3 id="epCalModalTitulo" style="margin:0;font-size:16px;font-weight:700;color:var(--color-ink);">Crear Calendario de Activaciones</h3>
					</div>
				</div>
				<button type="button" class="ep-modal-close-btn" id="epCalModalCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
			</div>
			<div class="ep-modal-ppt-body">
				<div class="ep-cal-form-fila">
					<label class="ep-cal-campo"><span>Nombre del calendario (título del reporte generado)</span><input type="text" class="ep-input" id="epCalNombre" placeholder="Activaciones Retail · Noviembre"></label>
				</div>
				<div class="ep-cal-form-fila ep-cal-form-fila-3">
					<label class="ep-cal-campo"><span>Canal</span>
						<select class="ep-input" id="epCalCanal"><option value="RETAIL">Retail</option><option value="CANALES">Canales</option></select>
					</label>
					<label class="ep-cal-campo"><span>Plazo máximo (días)</span><input type="number" min="1" max="30" class="ep-input" id="epCalPlazo" value="5"></label>
					<div class="ep-cal-campo"><span>Rango (automático)</span><div class="ep-cal-rango-auto" id="epCalRangoAuto">Se calcula al agregar filas</div></div>
				</div>

				<div class="ep-cal-filas-editor">
					<div class="ep-cal-filas-editor-head">
						<strong>Filas del calendario</strong>
						<button type="button" class="ep-btn-subtle-compact" id="epCalAgregarFila"><?= ep_icon('plus', 13) ?> Agregar fila</button>
					</div>
					<div class="ep-cal-fila-editor-head">
						<span>Fecha</span><span>Ciudad</span><span>Promotor</span><span>Punto de venta</span><span>Supervisor</span><span></span>
					</div>
					<div class="ep-cal-filas-editor-tabla" id="epCalFilasEditor">
						<div class="ep-cal-fila-editor">
							<input type="date" class="ep-input ep-cal-fecha-input">
							<div class="ep-combo ep-cal-combo-ciudad">
								<button type="button" class="ep-input ep-combo-trigger" data-valor=""><span class="ep-combo-trigger-texto">Ciudad</span><?= ep_icon('chevron', 14) ?></button>
								<div class="ep-combo-panel hidden">
									<input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar ciudad..." autocomplete="off">
									<div class="ep-combo-opciones"></div>
								</div>
							</div>
							<div class="ep-combo ep-cal-combo-promotor">
								<button type="button" class="ep-input ep-combo-trigger ep-combo-desactivado" data-valor="" disabled><span class="ep-combo-trigger-texto">Elige ciudad</span><?= ep_icon('chevron', 14) ?></button>
								<div class="ep-combo-panel hidden">
									<input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar promotor..." autocomplete="off">
									<div class="ep-combo-opciones"></div>
								</div>
							</div>
							<div class="ep-combo ep-cal-combo-pdv">
								<button type="button" class="ep-input ep-combo-trigger ep-combo-desactivado" data-valor="" disabled><span class="ep-combo-trigger-texto">Elige promotor</span><?= ep_icon('chevron', 14) ?></button>
								<div class="ep-combo-panel hidden">
									<input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar punto de venta..." autocomplete="off">
									<div class="ep-combo-opciones"></div>
								</div>
							</div>
							<div class="ep-cal-supervisor-wrap">
								<span class="ep-cal-supervisor-auto">—</span>
								<select class="ep-input ep-cal-supervisor-select hidden"></select>
							</div>
							<button type="button" class="ep-modelo-quitar" aria-label="Quitar fila"><?= ep_icon('trash', 14) ?></button>
						</div>
					</div>
				</div>
			</div>
			<div class="ep-modal-ppt-foot ep-rp-foot">
				<div class="ep-rp-foot-actions" style="margin-left:auto;">
					<button type="button" class="ep-btn-subtle-compact" id="epCalCancelar">Cancelar</button>
					<button type="button" class="ep-btn-ppt-cta" id="epCalCrear">Crear y Activar Calendario</button>
				</div>
			</div>
		</div>
	</div>

	<script type="application/json" id="epCalDatosRutero">
		<?= json_encode(['RETAIL' => $ruteroRetail, 'CANALES' => $ruteroCanales], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
	</script>
</main>
