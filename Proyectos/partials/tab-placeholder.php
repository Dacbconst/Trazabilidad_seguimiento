<?php
/**
 * COMPONENTE: tab-placeholder.php
 * Pestaña/sección en desarrollo, o estado vacío (sin cuenta elegida / cuenta
 * no implementada). Variable requerida: $placeholder_label.
 * Variable opcional: $placeholder_message (texto completo, sustituye el
 * mensaje por defecto "Sección "{label}" en desarrollo").
 */
$placeholder_message = $placeholder_message ?? 'Sección "'.$placeholder_label.'" en desarrollo';
?>
<div class="placeholder-section">
    <i class="glyphicon glyphicon-time" style="font-size:28px; display:block; margin-bottom:10px;"></i>
    <?= htmlspecialchars($placeholder_message) ?>
</div>
