<?php
// Paso "Datos de la actividad" (tipo, fecha y horario), igual para todos los formularios.
// Variables que define quien lo incluye: $epPrefijo (act, cap...: da los ids), $epNumero (número del paso), $epEtiquetaTipo y $epEjemploTipo.
$epId = 'ep-'.$epPrefijo;
?>
	<!-- Paso <?= (int) $epNumero ?>: Datos de la actividad -->
	<div class="ep-step">
		<div class="ep-step-rail">
			<div class="ep-step-num"><?= (int) $epNumero ?></div>
			<div class="ep-step-line"></div>
		</div>
		<div class="ep-step-body">
			<h3 class="ep-step-title">Datos de la actividad</h3>
			<p class="ep-step-hint">Qué hiciste, cuándo y en qué horario.</p>
			<div class="ep-form-grid-2">
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="<?= $epId ?>-tipo"><?= htmlspecialchars($epEtiquetaTipo) ?></label>
					<textarea class="ep-input ep-input-mayusculas" id="<?= $epId ?>-tipo" rows="1" maxlength="40" autocomplete="off" placeholder="<?= htmlspecialchars($epEjemploTipo) ?>"></textarea>
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="<?= $epId ?>-fecha">Fecha de la actividad</label>
					<input type="date" class="ep-input" id="<?= $epId ?>-fecha" value="<?= date('Y-m-d') ?>">
				</div>
			</div>
			<div class="ep-form-grid-2" style="margin-top:12px;">
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="<?= $epId ?>-hora-inicio">Hora de inicio</label>
					<input type="time" class="ep-input" id="<?= $epId ?>-hora-inicio">
				</div>
				<div style="display:flex;flex-direction:column;gap:6px;">
					<label class="ep-label" for="<?= $epId ?>-hora-fin">Hora de fin</label>
					<input type="time" class="ep-input" id="<?= $epId ?>-hora-fin">
				</div>
			</div>
		</div>
	</div>
