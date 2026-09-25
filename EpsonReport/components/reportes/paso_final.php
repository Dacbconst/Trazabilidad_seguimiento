<?php
// Último paso del reporte mensual: nombre con el que se descarga, mes y resumen del rango; recién aquí se guarda.
?>
<div class="ep-rp-final hidden" id="epRpFinal">
	<div class="ep-rp-final-card">
		<h3 class="ep-act-sec-title">
			<span class="ep-act-dot"></span>
			<span>Datos del reporte</span>
		</h3>
		<p class="ep-act-sec-sub">Ponle nombre y confirma el mes antes de guardar.</p>
		<div class="ep-rp-final-grid">
			<div class="ep-ppt-form-group ep-rp-final-nombre">
				<label class="ep-label-compact" for="epRpTituloTxt"><?= ep_icon('file', 13) ?> <span>Nombre del reporte (así se descarga)</span></label>
				<input type="text" id="epRpTituloTxt" class="ep-input" maxlength="150" autocomplete="off" placeholder="Ej. Activaciones Septiembre 2026">
			</div>
			<div class="ep-ppt-form-group">
				<label class="ep-label-compact" for="epRpMes"><?= ep_icon('calendar', 13) ?> <span>Mes del reporte</span></label>
				<input type="month" id="epRpMes" class="ep-input">
			</div>
			<div class="ep-ppt-form-group">
				<span class="ep-label-compact"><?= ep_icon('clock', 13) ?> <span>Rango de fechas</span></span>
				<div class="ep-rp-final-dato" id="epRpFinalRango">-</div>
			</div>
			<div class="ep-ppt-form-group">
				<span class="ep-label-compact"><?= ep_icon('list', 13) ?> <span>Registros incluidos</span></span>
				<div class="ep-rp-final-dato" id="epRpFinalTotal">0</div>
			</div>
		</div>
	</div>
</div>
