<?php
// Calendario de Activaciones (solo admin/supervisor): cruza solo registros reales; sin borrador, crea y activa de una vez.
require_once __DIR__.'/../../includes/functions.php';

if (!ep_es_gestor()) {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

require_once __DIR__.'/../../includes/calendario_datos.php';

// Esta pantalla puede demorar (lvi_rutero es pesada); se suelta la sesión ya para no bloquear otras pestañas o el ping de sesion-watch.js mientras carga.
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

// Primero se cruzan registros ya enviados, así un calendario que vence cierra con lo cumplido.
ep_calendario_cruzar_pendientes();
ep_calendario_verificar_vencidos($usuarioId);
ep_calendario_cerrar_completos();
// Editable según la grabación 28-09; el servidor lo vuelve a validar al guardar.
$hoy = date('Y-m-d');
$calendarios = array_map(function ($c) use ($hoy) {
	$c['filas'] = array_map(fn($f) => ['fecha' => $f['fecha'], 'ciudad' => $f['ciudad'], 'pdv' => $f['punto_venta'], 'pos_id' => $f['pos_id'], 'promotor' => $f['promotor_nombre'], 'promotor_id' => (int) $f['promotor_usuario_id'], 'supervisor' => $f['supervisor_nombre'], 'estado' => $f['estado'], 'cumplido_en' => $f['cumplido_en'], 'fila_id' => (int) $f['id'], 'editable' => $c['estado'] === 'activo' && $f['estado'] === 'pendiente' && $f['fecha'] >= $hoy], $c['filas']);
	return $c;
}, ep_calendario_listar());

// Rutero, ciudades y PDV del modal ya no viajan en el HTML: calendario.js los pide a getters/calendario_catalogo.php.
$canales = ep_calendario_canales();

$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$fechaCorta = fn(string $iso): string => (int) substr($iso, 8, 2).' '.$mesesCortos[(int) substr($iso, 5, 2)];
$rango = fn(string $d, string $h): string => $d === $h ? $fechaCorta($d) : (substr($d, 0, 7) === substr($h, 0, 7) ? (int) substr($d, 8, 2).'–'.$fechaCorta($h) : $fechaCorta($d).' – '.$fechaCorta($h));
$filasTxt = fn(int $n): string => $n.($n === 1 ? ' fila' : ' filas');
$canalTxt = fn(string $c): string => ucfirst(strtolower($c));

function ep_cal_dias_restantes(?string $venceEn): ?int {
	if (!$venceEn) {
		return null;
	}
	return (int) ceil((strtotime($venceEn) - time()) / 86400);
}

// Estado que ve el usuario: activo, o cerrado completo/incompleto según si se cumplieron todas sus filas.
function ep_cal_estado_vista(array $c, int $cumplidas, int $total): string {
	if ($c['estado'] === 'activo') {
		return 'activo';
	}
	return $total > 0 && $cumplidas === $total ? 'completo' : 'incompleto';
}

$canalesLista = array_values(array_unique(array_map(fn($c) => $c['canal'], $calendarios)));
sort($canalesLista);
$iconoReactivar = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>';
$iconoMas = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>';
$etiqueta = fn(string $larga, string $corta): string => '<span class="ep-cl-etq" aria-hidden="true"><span class="ep-cl-larga">'.$larga.'</span><span class="ep-cl-corta">'.$corta.'</span></span>';
?>
<link rel="stylesheet" href="assets/css/calendario-lista.css?v=<?= filemtime(__DIR__.'/../../assets/css/calendario-lista.css') ?>">
<main class="ep-content ep-cal" id="epCal">

	<header class="ep-cl-head">
		<div>
			<h1>Calendario <span class="ep-vivo" id="epClVivo" title="Se actualiza sola cada pocos segundos"><i></i><span>En vivo</span></span></h1>
			<p id="epClResumen"><?= count($calendarios) ?> <?= count($calendarios) === 1 ? 'calendario' : 'calendarios' ?></p>
			<?php $pendientesRevision = count(array_filter($calendarios, 'ep_calendario_por_revisar')); ?>
			<?php if ($pendientesRevision > 0): ?><button type="button" class="ep-cl-pill-revisar" id="epClRevisar" aria-pressed="false"><?= $pendientesRevision ?> por revisar</button><?php endif; ?>
		</div>
		<button type="button" class="ep-rp-nuevo" id="epCalNuevo"><?= ep_icon('plus', 16) ?> <span>Crear calendario</span></button>
	</header>

	<div class="ep-cl-tabs" role="tablist" aria-label="Tipo de programación">
		<button type="button" class="ep-cl-tab activo" role="tab" aria-selected="true" data-tab="act">Activaciones</button>
		<button type="button" class="ep-cl-tab" role="tab" aria-selected="false" data-tab="pop">Colocación de POP</button>
	</div>

	<div id="epCalPanelAct">

	<?php if (empty($calendarios)): ?>
		<?= ep_estado_vacio('calendar', 'Todavía no hay calendarios', 'Crea el primero con "Crear calendario".') ?>
	<?php else: ?>
	<section class="ep-cl-kpis" aria-label="Calendarios por estado">
		<button type="button" class="ep-cl-kpi ep-cl-kpi-activo" data-kpi="activo" aria-pressed="false"><span class="ep-cl-kpi-n">0</span><span class="ep-cl-kpi-t">Activos</span></button>
		<button type="button" class="ep-cl-kpi ep-cl-kpi-completo" data-kpi="completo" aria-pressed="false"><span class="ep-cl-kpi-n">0</span><span class="ep-cl-kpi-t">Cerrado completo</span></button>
		<button type="button" class="ep-cl-kpi ep-cl-kpi-incompleto" data-kpi="incompleto" aria-pressed="false"><span class="ep-cl-kpi-n">0</span><span class="ep-cl-kpi-t">Cerrado incompleto</span></button>
	</section>

	<div class="ep-cl-barra">
		<div class="ep-cl-pop ep-cl-pop-periodo">
			<button type="button" class="ep-cl-filtro" id="epClPeriodoBtn" aria-haspopup="dialog" aria-expanded="false" aria-controls="epClPeriodo">
				<?= ep_icon('calendar', 16) ?> <span class="ep-cl-filtro-et">Período:</span> <strong id="epClPeriodoTxt">Todas las fechas</strong> <?= ep_icon('chevron', 14) ?>
			</button>
			<div class="ep-cl-panel" id="epClPeriodo" role="dialog" aria-label="Elegir período" hidden>
				<div class="ep-cl-atajos">
					<button type="button" class="ep-cl-chip" data-atajo="mes">Este mes</button>
					<button type="button" class="ep-cl-chip" data-atajo="anterior">Mes anterior</button>
					<button type="button" class="ep-cl-chip" data-atajo="30">Últimos 30 días</button>
				</div>
				<span class="ep-cl-panel-t">Elegir mes</span>
				<div class="ep-cl-meses" id="epClMeses"></div>
				<span class="ep-cl-panel-t">Rango personalizado</span>
				<div class="ep-cl-rango">
					<label>Desde <input type="date" id="epClDesde"></label>
					<label>Hasta <input type="date" id="epClHasta"></label>
				</div>
				<div class="ep-cl-panel-pie">
					<button type="button" class="ep-cl-limpiar" id="epClLimpiar">Limpiar</button>
					<button type="button" class="ep-cl-aplicar" id="epClAplicar">Aplicar</button>
				</div>
			</div>
		</div>
		<label class="ep-cl-buscar">
			<?= ep_icon('search', 16) ?>
			<input type="search" id="epClBuscar" placeholder="Buscar calendario, punto o promotor" aria-label="Buscar calendario, punto o promotor" autocomplete="off">
		</label>
		<div class="ep-cl-pop ep-cl-pop-canal">
			<button type="button" class="ep-cl-filtro" id="epClCanalBtn" aria-haspopup="listbox" aria-expanded="false" aria-controls="epClCanal">
				<span class="ep-cl-filtro-et">Canal:</span> <strong id="epClCanalTxt">Todos</strong> <?= ep_icon('chevron', 14) ?>
			</button>
			<div class="ep-cl-panel ep-cl-panel-lista" id="epClCanal" role="listbox" aria-label="Canal" hidden>
				<button type="button" role="option" data-canal="" aria-selected="true">Todos</button>
				<?php foreach ($canalesLista as $canal): ?>
					<button type="button" role="option" data-canal="<?= $h($canal) ?>" aria-selected="false"><?= $h($canalTxt($canal)) ?></button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<div class="ep-cl-tabla" role="table" aria-label="Calendarios">
		<div class="ep-cl-cab" role="row">
			<span></span><span role="columnheader">Calendario</span><span role="columnheader">Canal</span><span role="columnheader">Fechas</span><span role="columnheader">Avance</span><span role="columnheader">Estado</span><span role="columnheader" class="ep-cl-der">Acciones</span>
		</div>
		<?php foreach ($calendarios as $c):
			$total = count($c['filas']);
			$cumplidas = count(array_filter($c['filas'], fn($f) => $f['estado'] === 'cumplido'));
			$vista = ep_cal_estado_vista($c, $cumplidas, $total);
			$dias = ep_cal_dias_restantes($c['vence_en']);
			$nombre = $c['nombre'] ?: 'Activaciones '.$canalTxt($c['canal']);
			$reporteUrl = !empty($c['reporte_mensual_id']) ? 'getters/reporte_descargar.php?id='.(int) $c['reporte_mensual_id'] : '';
			// Completó todas sus filas pero no tiene reporte: sus registros quedaron atrapados en otro reporte mensual activo. No es un cierre normal, se avisa aparte.
			$sinReporte = $vista === 'completo' && !$reporteUrl;
			// Cerró con reporte y sus comentarios aún no se revisaron: se finaliza antes de descargar el PPT.
			$porRevisar = ep_calendario_por_revisar($c);
			$id = (int) $c['id'];
			if ($vista === 'activo') {
				$estadoTxt = $dias === null ? 'Activo' : ($dias > 0 ? 'Cierra en '.$dias.' '.($dias === 1 ? 'día' : 'días') : 'Cierra hoy');
				$sub = $filasTxt($total).' · plazo '.(int) $c['plazo_dias'].' días';
			} else {
				$estadoTxt = $porRevisar ? 'Por revisar' : ($vista === 'completo' ? ($sinReporte ? 'Cerrado sin reporte' : 'Cerrado completo') : 'Cerrado incompleto');
				$sub = $filasTxt($total).' · cerró el '.$fechaCorta(substr((string) ($c['cerrado_en'] ?: $c['hasta']), 0, 10));
			}
			$urgente = $vista === 'activo' && $dias !== null && $dias <= 3;
		?>
		<article class="ep-cl-cal" data-id="<?= $id ?>" data-estado="<?= $vista ?>"<?= $porRevisar ? ' data-revisar="1"' : '' ?> data-canal="<?= $h($c['canal']) ?>" data-total="<?= $total ?>" data-nombre="<?= $h(mb_strtolower($nombre, 'UTF-8')) ?>">
			<div class="ep-cl-fila" role="row">
				<button type="button" class="ep-cl-toggle" aria-expanded="false" aria-label="Ver filas de <?= $h($nombre) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
				<div class="ep-cl-nombre"><strong title="<?= $h($nombre) ?>"><?= $h($nombre) ?></strong><span><?= $h($sub) ?></span></div>
				<span class="ep-cl-tag ep-cl-tag-canal"><?= $h($canalTxt($c['canal'])) ?></span>
				<span class="ep-cl-fechas"><?= $h($rango($c['desde'], $c['hasta'])) ?></span>
				<div class="ep-cl-avance"><span class="ep-cl-barra-av"><span style="transform: scaleX(<?= $total > 0 ? round($cumplidas / $total, 4) : 0 ?>)"></span></span><span class="ep-cl-av-txt"><?= $cumplidas ?> de <?= $total ?><?= $vista === 'incompleto' ? ' · <em>faltaron '.($total - $cumplidas).'</em>' : '' ?></span></div>
				<span class="ep-cl-tag ep-cl-estado ep-cl-estado-<?= $vista ?><?= $sinReporte ? ' ep-cl-estado-sin-reporte' : '' ?><?= $porRevisar ? ' ep-cl-estado-revisar' : '' ?><?= $urgente ? ' ep-cl-urgente' : '' ?>" title="<?= $sinReporte ? 'No se pudo generar el reporte: sus registros ya estaban en otro reporte mensual activo' : ($porRevisar ? 'Revisa los comentarios para poder descargar el PPT' : '') ?>"><?= $h($estadoTxt) ?></span>
				<div class="ep-cl-acciones">
					<?php if ($vista === 'activo'): ?>
						<button type="button" class="ep-cl-pri ep-cl-pri-suave ep-cal-generar-ahora" data-id="<?= $id ?>"><?= ep_icon('presentation', 15) ?> Cerrar y generar</button>
						<button type="button" class="ep-cl-accion ep-cal-editar" data-id="<?= $id ?>" aria-label="Editar <?= $h($nombre) ?>"><?= ep_icon('pencil', 16) ?><?= $etiqueta('Editar', 'Editar') ?></button>
					<?php elseif ($porRevisar): ?>
						<button type="button" class="ep-cl-pri ep-cal-finalizar" data-id="<?= $id ?>" data-comentarios="<?= $h($c['comentarios'] ?? '') ?>"><?= ep_icon('pencil', 15) ?> Revisar y finalizar</button>
						<button type="button" class="ep-cl-accion" disabled aria-label="Editar: solo en calendarios activos"><?= ep_icon('pencil', 16) ?><?= $etiqueta('Solo en activos', 'Editar') ?></button>
					<?php elseif ($vista === 'incompleto' || $sinReporte): ?>
						<button type="button" class="ep-cl-pri ep-cal-reactivar" data-id="<?= $id ?>"><?= $iconoReactivar ?> Reactivar</button>
						<button type="button" class="ep-cl-accion" disabled aria-label="Editar: solo en calendarios activos"><?= ep_icon('pencil', 16) ?><?= $etiqueta('Solo en activos', 'Editar') ?></button>
					<?php else: ?>
						<a class="ep-cl-pri" href="<?= $h($reporteUrl) ?>"><?= ep_icon('download', 15) ?> Descargar PPT</a>
						<button type="button" class="ep-cl-accion" disabled aria-label="Editar: solo en calendarios activos"><?= ep_icon('pencil', 16) ?><?= $etiqueta('Solo en activos', 'Editar') ?></button>
					<?php endif; ?>
					<?php if ($vista === 'incompleto' && !$porRevisar): ?>
						<?php if ($reporteUrl): ?>
							<a class="ep-cl-accion" href="<?= $h($reporteUrl) ?>" aria-label="Descargar PPT de <?= $h($nombre) ?>"><?= ep_icon('presentation', 16) ?><?= $etiqueta('Descargar PPT', 'PPT') ?></a>
						<?php else: ?>
							<button type="button" class="ep-cl-accion" disabled aria-label="Sin reporte: no llegó ningún registro"><?= ep_icon('presentation', 16) ?><?= $etiqueta('Sin reporte', 'PPT') ?></button>
						<?php endif; ?>
					<?php endif; ?>
					<div class="ep-cl-pop ep-cl-pop-mas">
						<button type="button" class="ep-cl-accion ep-cl-mas" aria-haspopup="menu" aria-expanded="false" aria-label="Más acciones de <?= $h($nombre) ?>"><?= $iconoMas ?><?= $etiqueta('Más acciones', 'Más') ?></button>
						<div class="ep-cl-menu" role="menu" hidden>
							<a role="menuitem" href="getters/calendario_excel.php?id=<?= $id ?>"><?= ep_icon('file', 15) ?> Descargar Excel</a>
							<?php if (($vista === 'completo' && !$sinReporte) || $porRevisar): ?>
								<button type="button" role="menuitem" class="ep-cal-reactivar" data-id="<?= $id ?>"><?= $iconoReactivar ?> Reactivar</button>
							<?php endif; ?>
							<button type="button" role="menuitem" class="ep-cal-eliminar ep-cl-peligro" data-id="<?= $id ?>"><?= ep_icon('trash', 15) ?> Eliminar calendario</button>
						</div>
					</div>
				</div>
			</div>

			<div class="ep-cl-detalle" hidden>
				<div class="ep-cl-chips" role="group" aria-label="Filtrar filas">
					<button type="button" class="ep-cl-chip" data-f="todas" aria-pressed="false">Todas <b></b></button>
					<button type="button" class="ep-cl-chip" data-f="cumplido" aria-pressed="false">Cumplidas <b></b></button>
					<button type="button" class="ep-cl-chip ep-cl-chip-falta" data-f="pendiente" aria-pressed="false"><?= $vista === 'activo' ? 'Pendientes' : 'No cumplidas' ?> <b></b></button>
				</div>
				<div class="ep-cl-f ep-cl-f-cab"><span>Fecha</span><span>Punto de venta</span><span>Ciudad</span><span>Promotor</span><span>Supervisor</span></div>
				<?php foreach ($c['filas'] as $f): ?>
				<div class="ep-cl-f" data-fila-id="<?= (int) $f['fila_id'] ?>" data-fecha="<?= $h($f['fecha']) ?>" data-estado="<?= $h($f['estado']) ?>" data-busca="<?= $h(mb_strtolower($f['pdv'].' '.$f['ciudad'].' '.$f['promotor'].' '.$f['supervisor'], 'UTF-8')) ?>">
					<span class="ep-cl-f-fecha"><b><?= (int) substr($f['fecha'], 8, 2) ?></b> <?= $h($mesesCortos[(int) substr($f['fecha'], 5, 2)]) ?></span>
					<span class="ep-cl-f-pdv"><?= $h($f['pdv']) ?></span>
					<span class="ep-cl-f-ciudad"><?= $h($f['ciudad'] ?: '—') ?></span>
					<span class="ep-cl-f-promotor"><?= $h($f['promotor']) ?></span>
					<span class="ep-cl-f-sup"><?= $h($f['supervisor'] ?: '—') ?></span>
				</div>
				<?php endforeach; ?>
				<p class="ep-cl-f-vacio" hidden>No hay filas con este filtro.</p>
			</div>
		</article>
		<?php endforeach; ?>
		<div class="ep-cl-sin" id="epClSin" hidden><?= ep_estado_vacio('search', 'Ningún calendario coincide', 'Cambia el período, el canal o la búsqueda, o toca de nuevo el indicador activo.') ?></div>
	</div>
	<?php endif; ?>

	</div>

	<div id="epCalPanelPop" class="hidden">
		<?php require __DIR__.'/pop.php'; ?>
	</div>

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
				<p class="ep-cal-aviso-edicion hidden" id="epCalAvisoEdicion"><?= ep_icon('lock', 14) ?> Se pueden cambiar los comentarios, y ciudad, promotor y punto de venta de las filas pendientes cuya fecha sea hoy o posterior. Las filas ya cumplidas o con fecha pasada quedan bloqueadas.</p>
				<div class="ep-cal-form-fila">
					<label class="ep-cal-campo"><span>Nombre del calendario (título del reporte generado)</span><input type="text" class="ep-input" id="epCalNombre" placeholder="Activaciones Retail · Noviembre"></label>
				</div>
				<div class="ep-cal-form-fila ep-cal-form-fila-3">
					<div class="ep-cal-campo"><span>Canal</span>
						<div class="ep-combo ep-cal-combo-canal" id="epCalCanalCombo" data-opciones="<?= $h(json_encode(array_map(fn($c) => ['texto' => ucfirst(strtolower($c)), 'valor' => $c], $canales), JSON_UNESCAPED_UNICODE)) ?>">
							<button type="button" class="ep-input ep-combo-trigger" data-valor=""><span class="ep-combo-trigger-texto">Canal</span><?= ep_icon('chevron', 14) ?></button>
							<div class="ep-combo-panel hidden">
								<input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar canal..." autocomplete="off">
								<div class="ep-combo-opciones"></div>
							</div>
						</div>
						<input type="hidden" id="epCalCanal" value="<?= $h(in_array('RETAIL', $canales, true) ? 'RETAIL' : (in_array('CANALES', $canales, true) ? 'CANALES' : ($canales[0] ?? ''))) ?>">
					</div>
					<label class="ep-cal-campo"><span>Plazo máximo (días)</span><input type="number" min="1" max="30" class="ep-input" id="epCalPlazo" value="5"></label>
					<div class="ep-cal-campo"><span>Rango (automático)</span><div class="ep-cal-rango-auto" id="epCalRangoAuto">Se calcula al agregar filas</div></div>
				</div>
				<div class="ep-cal-campo ep-cal-campo-comentarios">
					<span>Comentarios del reporte (opcional, hasta 5)</span>
					<textarea class="ep-input" id="epCal-comentarios" rows="2"></textarea>
				</div>

				<div class="ep-cal-filas-editor">
					<div class="ep-cal-filas-editor-head">
						<strong>Filas del calendario</strong>
						<button type="button" class="ep-btn-subtle-compact" id="epCalAgregarFila"><?= ep_icon('plus', 13) ?> Agregar fila</button>
					</div>
					<div class="ep-cal-fila-editor-head">
						<span>Fecha</span><span>Promotor</span><span>Ciudad</span><span>Punto de venta</span><span></span>
					</div>
					<div class="ep-cal-filas-editor-tabla" id="epCalFilasEditor">
						<div class="ep-cal-fila-editor">
							<input type="date" class="ep-input ep-cal-fecha-input">
							<div class="ep-combo ep-cal-combo-promotor">
								<button type="button" class="ep-input ep-combo-trigger" data-valor=""><span class="ep-combo-trigger-texto">Promotor</span><?= ep_icon('chevron', 14) ?></button>
								<div class="ep-combo-panel hidden">
									<input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar promotor..." autocomplete="off">
									<div class="ep-combo-opciones"></div>
								</div>
							</div>
							<div class="ep-combo ep-cal-combo-ciudad">
								<button type="button" class="ep-input ep-combo-trigger" data-valor=""><span class="ep-combo-trigger-texto">Ciudad</span><?= ep_icon('chevron', 14) ?></button>
								<div class="ep-combo-panel hidden">
									<input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar ciudad..." autocomplete="off">
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
							<div class="ep-cal-supervisor-wrap hidden">
								<span class="ep-cal-supervisor-auto">—</span>
								<select class="ep-input ep-cal-supervisor-select hidden"></select>
							</div>
							<div class="ep-cal-fila-accion">
								<button type="button" class="ep-modelo-quitar" aria-label="Quitar fila"><?= ep_icon('trash', 14) ?></button>
								<span class="ep-cal-fila-candado hidden"><?= ep_icon('lock', 14) ?></span>
							</div>
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

	<script type="application/json" id="epCalDatosCalendarios">
		<?= json_encode(array_values(array_map(fn($c) => ['id' => (int) $c['id'], 'nombre' => $c['nombre'], 'canal' => $c['canal'], 'plazo_dias' => (int) $c['plazo_dias'], 'comentarios' => (string) ($c['comentarios'] ?? ''), 'filas' => $c['filas']], array_filter($calendarios, fn($c) => $c['estado'] === 'activo'))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
	</script>
	<script src="assets/js/calendario-lista.js?v=<?= filemtime(__DIR__.'/../../assets/js/calendario-lista.js') ?>"></script>
</main>
