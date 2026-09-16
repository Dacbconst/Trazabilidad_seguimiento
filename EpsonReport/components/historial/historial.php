<?php
// Mockup — agrupado por día, basado en el diseño de referencia de diseños/code.html
// (misma estructura/interacción), pero con paleta y tipografía de Epson, no la del mockup original.
$tipos = [
	['value' => 'all', 'label' => 'Todas las actividades', 'count' => 14, 'selected' => true],
	['value' => 'visibilidad', 'label' => 'Actividad 1 — Visibilidad', 'count' => 6],
	['value' => 'mantenimiento', 'label' => 'Actividad 2 — Mantenimiento', 'count' => 4],
	['value' => 'auditoria', 'label' => 'Actividad 3 — Auditoría', 'count' => 4],
];

$grupos = [
	[
		'titulo' => 'Hoy — Jueves, 24 de Octubre',
		'total' => '1h 30m',
		'registros' => [
			[
				'tipo' => 'visibilidad', 'hora' => '15:30',
				'titulo' => 'Activación en punto de venta — Mall del Sol',
				'subtitulo' => 'Demostración de impresión en vivo con impresoras EcoTank',
				'duracion' => '45 min', 'estado' => 'Completado', 'abierto' => true,
				'descripcion' => 'Se realizó la activación con demostración de impresión continua, entrega de material POP y registro de la interacción de 32 clientes en el punto.',
				'meta' => [
					['label' => 'Punto de venta', 'valor' => 'Mall del Sol — Guayaquil'],
					['label' => 'Categoría', 'valor' => 'Impresoras EcoTank'],
					['label' => 'Nivel de impacto', 'valor' => 'Alto (evento programado)'],
					['label' => 'Registrado por', 'valor' => 'Carlos Proaño'],
				],
				'checklist' => [
					'Instalación de banner y material POP',
					'Demostración de impresión continua',
					'Registro de clientes interesados',
					'Entrega de material promocional',
				],
				'fotos' => 2,
			],
			[
				'tipo' => 'mantenimiento', 'hora' => '11:00',
				'titulo' => 'Capacitación a mercaderista nuevo',
				'subtitulo' => 'Repaso del proceso de reporte y evidencia fotográfica',
				'duracion' => '45 min', 'chips' => ['3 tareas', '1 adjunto'], 'abierto' => false,
			],
		],
	],
	[
		'titulo' => 'Ayer — Miércoles, 23 de Octubre',
		'total' => '2h 15m',
		'registros' => [
			[
				'tipo' => 'auditoria', 'hora' => '16:00',
				'titulo' => 'Colocación de material POP — Comercial Norte',
				'subtitulo' => 'Verificación de exhibición y reposición de material',
				'duracion' => '1h 30m', 'chips' => ['Reporte de respaldo'], 'abierto' => false,
			],
			[
				'tipo' => 'mantenimiento', 'hora' => '10:00',
				'titulo' => 'Revisión de inventario de demos en bodega',
				'subtitulo' => 'Verificación de números de serie y estado físico',
				'duracion' => '45 min', 'abierto' => false,
			],
		],
	],
	[
		'titulo' => 'Lunes, 21 de Octubre',
		'total' => '1h 00m',
		'registros' => [
			[
				'tipo' => 'auditoria', 'hora' => '14:15',
				'titulo' => 'Auditoría de exhibición — Distribuidora Central',
				'subtitulo' => 'Checklist de cumplimiento de espacio y visibilidad',
				'duracion' => '30 min', 'chips' => ['Checklist completo'], 'abierto' => false,
			],
			[
				'tipo' => 'visibilidad', 'hora' => '09:30',
				'titulo' => 'Revisión de reportes pendientes de la semana',
				'subtitulo' => 'Verificación de registros sin evidencia fotográfica',
				'duracion' => '30 min', 'abierto' => false,
			],
		],
	],
];

$totalRegistros = 0;
foreach ($grupos as $g) $totalRegistros += count($g['registros']);
$uid = 0;
?>
<main class="ep-content" style="display:flex;flex-direction:column;gap:24px;">
	<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
		<div>
			<div class="ep-eyebrow">Visibilidad</div>
			<h1 style="font-size:24px;margin-top:4px;">Historial de registros</h1>
		</div>
		<div class="ep-hist-rango">
			<button type="button" class="ep-hist-rango-btn ep-hist-rango-activo">Hoy</button>
			<button type="button" class="ep-hist-rango-btn">Últimos 7 días</button>
			<button type="button" class="ep-hist-rango-btn">Este mes</button>
		</div>
	</div>

	<div class="ep-hist-filtro-bar">
		<div class="ep-hist-filtro-select-wrap">
			<label class="ep-label" for="ep-hist-tipo"><?= ep_icon('filter', 15) ?> Tipo de actividad</label>
			<select id="ep-hist-tipo" class="ep-input">
				<?php foreach ($tipos as $t): ?>
					<option value="<?= htmlspecialchars($t['value']) ?>" <?= !empty($t['selected']) ? 'selected' : '' ?>>
						<?= htmlspecialchars($t['label']) ?> (<?= (int) $t['count'] ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div id="ep-hist-chip" class="ep-hist-chip hidden">
			<span class="ep-hist-chip-dot"></span>
			<span id="ep-hist-chip-texto"></span>
			<button type="button" id="ep-hist-chip-cerrar" aria-label="Limpiar filtro"><?= ep_icon('close', 12) ?></button>
		</div>
		<button type="button" id="ep-hist-restablecer" class="ep-hist-restablecer hidden">Restablecer</button>
	</div>

	<div style="display:flex;align-items:center;justify-content:space-between;">
		<span id="ep-hist-resumen" style="font-size:13px;color:var(--color-text-muted);"><?= $totalRegistros ?> registros</span>
		<button type="button" id="ep-hist-expandir-todos" class="ep-hist-expandir-todos" data-estado="cerrar">
			<?= ep_icon('chevron-up', 15) ?> Plegar todos
		</button>
	</div>

	<div id="ep-hist-grupos" style="display:flex;flex-direction:column;gap:20px;">
		<?php foreach ($grupos as $g): ?>
			<section class="ep-hist-day">
				<div class="ep-hist-day-header">
					<div style="display:flex;align-items:center;gap:10px;">
						<span class="ep-hist-day-dot"></span>
						<h2 style="font-size:15px;"><?= htmlspecialchars($g['titulo']) ?></h2>
						<span class="ep-hist-day-count"><?= count($g['registros']) ?> registros</span>
					</div>
					<span style="font-size:12px;color:var(--color-text-muted);font-weight:600;"><?= htmlspecialchars($g['total']) ?> total</span>
				</div>

				<div style="display:flex;flex-direction:column;gap:12px;margin-top:14px;">
					<?php foreach ($g['registros'] as $r): $uid++; $detalleId = 'ep-hist-detalle-'.$uid; ?>
						<article class="ep-hist-record<?= !empty($r['abierto']) ? ' ep-hist-record-abierto' : '' ?>" data-tipo="<?= htmlspecialchars($r['tipo']) ?>">
							<div class="ep-hist-record-cabecera">
								<div style="display:flex;align-items:flex-start;gap:12px;min-width:0;">
									<span class="ep-hist-hora"><?= htmlspecialchars($r['hora']) ?></span>
									<div style="min-width:0;">
										<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
											<h3 style="font-size:14px;font-weight:600;color:var(--color-ink);"><?= htmlspecialchars($r['titulo']) ?></h3>
											<?php if (!empty($r['estado'])): ?>
												<span class="ep-hist-badge ep-hist-badge-ok"><?= htmlspecialchars($r['estado']) ?></span>
											<?php endif; ?>
											<?php foreach ($r['chips'] ?? [] as $chip): ?>
												<span class="ep-hist-badge"><?= htmlspecialchars($chip) ?></span>
											<?php endforeach; ?>
										</div>
										<p style="margin:4px 0 0;font-size:12px;color:var(--color-text-muted);"><?= htmlspecialchars($r['subtitulo']) ?></p>
									</div>
								</div>
								<div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
									<span class="ep-hist-duracion"><?= htmlspecialchars($r['duracion']) ?></span>
									<button type="button" class="ep-hist-toggle" data-target="<?= $detalleId ?>" aria-expanded="<?= !empty($r['abierto']) ? 'true' : 'false' ?>">
										<span class="ep-hist-toggle-text"><?= !empty($r['abierto']) ? 'Plegar' : 'Detalles' ?></span>
										<span class="ep-hist-toggle-icon-down<?= !empty($r['abierto']) ? ' hidden' : '' ?>"><?= ep_icon('chevron', 15) ?></span>
										<span class="ep-hist-toggle-icon-up<?= !empty($r['abierto']) ? '' : ' hidden' ?>"><?= ep_icon('chevron-up', 15) ?></span>
									</button>
								</div>
							</div>

							<div id="<?= $detalleId ?>" class="ep-hist-detalle<?= !empty($r['abierto']) ? '' : ' hidden' ?>">
								<?php if (!empty($r['descripcion'])): ?>
									<div>
										<div class="ep-eyebrow" style="font-size:11px;">Descripción de la intervención</div>
										<p style="font-size:12px;color:var(--color-text);line-height:1.6;background:var(--color-surface);border:1px solid var(--color-border);border-radius:8px;padding:10px 12px;margin-top:6px;"><?= htmlspecialchars($r['descripcion']) ?></p>
									</div>
								<?php endif; ?>

								<?php if (!empty($r['meta'])): ?>
									<div>
										<div class="ep-eyebrow" style="font-size:11px;">Parámetros y contexto</div>
										<div class="ep-hist-meta-grid">
											<?php foreach ($r['meta'] as $m): ?>
												<div class="ep-hist-meta-item">
													<span style="font-size:11px;color:var(--color-text-muted);"><?= htmlspecialchars($m['label']) ?></span>
													<span style="font-size:13px;font-weight:600;color:var(--color-text);"><?= htmlspecialchars($m['valor']) ?></span>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>

								<?php if (!empty($r['checklist'])): ?>
									<div class="ep-hist-checklist">
										<div style="display:flex;align-items:center;gap:6px;">
											<?= ep_icon('check', 15) ?>
											<span style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;">Pasos verificados (<?= count($r['checklist']) ?>/<?= count($r['checklist']) ?>)</span>
										</div>
										<ul style="list-style:none;margin:8px 0 0;padding:0;display:flex;flex-direction:column;gap:6px;">
											<?php foreach ($r['checklist'] as $paso): ?>
												<li style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--color-text);"><?= ep_icon('check', 14) ?><?= htmlspecialchars($paso) ?></li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>

								<?php if (!empty($r['fotos'])): ?>
									<div style="display:flex;flex-direction:column;gap:8px;">
										<span class="ep-label" style="display:flex;align-items:center;gap:6px;"><?= ep_icon('file', 14) ?> Evidencia fotográfica (<?= (int) $r['fotos'] ?>)</span>
										<div style="display:flex;gap:12px;flex-wrap:wrap;">
											<?php for ($f = 1; $f <= $r['fotos']; $f++): ?>
												<div class="ep-photo-slot" style="width:140px;height:100px;"><?= ep_icon('camera', 20) ?><span style="font-size:11px;">Foto <?= $f ?></span></div>
											<?php endfor; ?>
										</div>
									</div>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<div class="ep-hist-add-mas">
		<?= ep_icon('plus', 18) ?>
		<span style="font-size:13px;font-weight:600;color:var(--color-text);">¿Hiciste algo más hoy?</span>
		<span style="font-size:11px;color:var(--color-text-muted);">Ve a Actividades para registrarlo</span>
	</div>
</main>
