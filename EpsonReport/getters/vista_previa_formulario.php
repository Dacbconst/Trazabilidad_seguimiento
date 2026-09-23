<?php
// Devuelve el HTML real de una plantilla, sin ids (evita chocar con el formulario real) y sin poder tipear — solo para la vista previa del constructor.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	exit;
}

$plantillasValidas = ['activaciones', 'capacitaciones', 'epson-day', 'evento-ferias', 'exhibiciones', 'colocacion-pop', 'generico', 'pendiente'];
$plantilla = $_GET['plantilla'] ?? '';
if (!in_array($plantilla, $plantillasValidas, true)) {
	http_response_code(400);
	exit;
}

ob_start();
include __DIR__.'/../components/actividades/plantillas/'.$plantilla.'.php';
$html = ob_get_clean();

// Si la plantilla incluye un contenedor de modelos vacío, inyectamos una fila inicial igual a la que crea app.js en producción
$filaModeloEjemplo = '<div class="ep-modelo-fila-nueva">'
	. '<div class="ep-combo"><button type="button" class="ep-input ep-combo-trigger" disabled style="pointer-events:none;"><span class="ep-combo-trigger-texto">Elegir modelo</span>' . ep_icon('chevron', 14) . '</button></div>'
	. '<input type="number" min="0" inputmode="numeric" class="ep-input ep-modelo-cantidad" placeholder="Cant." disabled style="pointer-events:none;">'
	. '<button type="button" class="ep-modelo-quitar" disabled aria-label="Quitar modelo" style="pointer-events:none;">' . ep_icon('trash', 14) . '</button>'
	. '</div>';

$html = preg_replace('/(<div\s+id="[^"]*modelo-filas"[^>]*>)\s*(<\/div>)/i', '$1' . $filaModeloEjemplo . '$2', $html);

// Botones de acción al pie del formulario, idénticos al panel real (#ep-panel-formulario)
if ($plantilla !== 'pendiente') {
	$html .= '<div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:18px;border-top:1px solid var(--color-border);">'
		. '<button type="button" class="ep-btn-outline" disabled style="pointer-events:none;opacity:0.85;">Guardar borrador</button>'
		. '<button type="button" class="ep-btn-primary" disabled style="pointer-events:none;opacity:0.95;">Enviar registro</button>'
		. '</div>';
}

$html = preg_replace('/\sid="[^"]*"/', '', $html);
$html = preg_replace('/\sfor="[^"]*"/', '', $html);
$html = preg_replace('/<(input|textarea|select|button)\b(?![^>]*\bdisabled\b)/', '<$1 disabled', $html);

header('Content-Type: text/html; charset=utf-8');
echo $html;
