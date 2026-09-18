<?php
// Plantilla de respaldo: un input por campo, sin agrupar. Se usa mientras una actividad no tenga su propia plantilla.
foreach ($actividad['campos'] as $campo): ?>
	<div style="display:flex;flex-direction:column;gap:6px;">
		<label class="ep-label"><?= htmlspecialchars($campo['label']) ?></label>
		<?php if ($campo['tipo'] === 'auto'): ?>
			<div class="ep-input ep-input-auto">
				<span data-valor="<?= (int) ($campo['ejemplo'] ?? 0) ?>"><?= (int) ($campo['ejemplo'] ?? 0) ?></span>
			</div>
		<?php else: ?>
			<input class="ep-input" type="number" min="0" placeholder="Ej. <?= (int) ($campo['ejemplo'] ?? 0) ?>">
		<?php endif; ?>
	</div>
<?php endforeach; ?>
