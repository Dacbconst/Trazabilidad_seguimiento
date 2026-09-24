<?php
// Piezas comunes de los PPTX de Epson: lectura y edición de diapositivas, barra azul del promotor, barras, embudo y fotos.

const EP_PPT_NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
const EP_PPT_NS_P = 'http://schemas.openxmlformats.org/presentationml/2006/main';
const EP_PPT_NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

function ep_ppt_meses(): array {
	return ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
}

function ep_ppt_cargar(string $xml): array {
	$dom = new DOMDocument();
	$dom->loadXML($xml);
	$xp = new DOMXPath($dom);
	$xp->registerNamespace('a', EP_PPT_NS_A);
	$xp->registerNamespace('p', EP_PPT_NS_P);
	$xp->registerNamespace('r', EP_PPT_NS_R);
	return [$dom, $xp];
}

// Elemento (sp/pic) de la diapositiva por su nombre, también si está dentro de un grupo.
function ep_ppt_forma(DOMXPath $xp, string $nombre): ?DOMElement {
	$nodos = $xp->query("//p:cSld//*[p:nvSpPr/p:cNvPr[@name='".$nombre."'] or p:nvPicPr/p:cNvPr[@name='".$nombre."']]");
	return $nodos->length > 0 ? $nodos->item(0) : null;
}

// Quita una forma de la diapositiva (por ejemplo la foto de ejemplo del promotor).
function ep_ppt_quitar(DOMXPath $xp, string $nombre): void {
	$forma = ep_ppt_forma($xp, $nombre);
	if ($forma) {
		$forma->parentNode->removeChild($forma);
	}
}

function ep_ppt_poner_texto(DOMDocument $dom, DOMElement $p, string $texto): void {
	$runs = [];
	foreach ($p->childNodes as $h) {
		if ($h instanceof DOMElement && $h->localName === 'r') {
			$runs[] = $h;
		}
	}
	if (empty($runs)) {
		return;
	}
	foreach (array_slice($runs, 1) as $extra) {
		$p->removeChild($extra);
	}
	$t = $runs[0]->getElementsByTagNameNS(EP_PPT_NS_A, 't')->item(0);
	while ($t->firstChild) {
		$t->removeChild($t->firstChild);
	}
	$t->appendChild($dom->createTextNode($texto));
}

// Reemplaza el texto de una forma, párrafo a párrafo (los que sobran se quitan, dejando siempre uno).
function ep_ppt_texto(DOMDocument $dom, DOMXPath $xp, string $nombre, array $parrafos): void {
	$forma = ep_ppt_forma($xp, $nombre);
	if (!$forma) {
		return;
	}
	$ps = [];
	foreach ($xp->query('.//a:p', $forma) as $p) {
		$ps[] = $p;
	}
	foreach ($ps as $i => $p) {
		if (isset($parrafos[$i])) {
			ep_ppt_poner_texto($dom, $p, (string) $parrafos[$i]);
		} elseif ($i > 0) {
			$p->parentNode->removeChild($p);
		} else {
			ep_ppt_poner_texto($dom, $p, '');
		}
	}
}

// Convierte una imagen WebP en JPEG (PowerPoint no admite WebP); null si el servidor no puede.
function ep_ppt_webp_a_jpeg(string $bytes): ?string {
	if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
		return null;
	}
	$origen = @imagecreatefromstring($bytes);
	if (!$origen) {
		return null;
	}
	$ancho = imagesx($origen);
	$alto = imagesy($origen);
	$lienzo = imagecreatetruecolor($ancho, $alto);
	imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
	imagecopy($lienzo, $origen, 0, 0, 0, 0, $ancho, $alto);
	ob_start();
	imagejpeg($lienzo, null, 85);
	$jpeg = (string) ob_get_clean();
	imagedestroy($origen);
	imagedestroy($lienzo);
	return $jpeg !== '' ? $jpeg : null;
}

// Baja una forma dy unidades (para que el texto que se parte en dos líneas no pise al de abajo).
function ep_ppt_mover(DOMXPath $xp, string $nombre, int $dy): void {
	$forma = ep_ppt_forma($xp, $nombre);
	$off = $forma ? $xp->query('.//a:xfrm/a:off', $forma)->item(0) : null;
	if ($off) {
		$off->setAttribute('y', (string) ((int) $off->getAttribute('y') + $dy));
	}
}

// Posición y tamaño de una forma.
function ep_ppt_geometria(DOMXPath $xp, string $nombre, int $x, int $y, int $cx, int $cy): void {
	$forma = ep_ppt_forma($xp, $nombre);
	if (!$forma) {
		return;
	}
	$off = $xp->query('.//a:xfrm/a:off', $forma)->item(0);
	$ext = $xp->query('.//a:xfrm/a:ext', $forma)->item(0);
	if ($off && $ext) {
		$off->setAttribute('x', (string) $x);
		$off->setAttribute('y', (string) $y);
		$ext->setAttribute('cx', (string) $cx);
		$ext->setAttribute('cy', (string) $cy);
	}
}

// Filas "ETIQUETA ........ valor" con el valor alineado a la derecha del cuadro (tabulador derecho).
function ep_ppt_texto_tabulado(DOMDocument $dom, DOMXPath $xp, string $nombre, array $filas): void {
	$forma = ep_ppt_forma($xp, $nombre);
	if (!$forma) {
		return;
	}
	ep_ppt_texto($dom, $xp, $nombre, array_map(fn($fila) => $fila[0]."	".$fila[1], $filas));
	$ext = $xp->query('.//a:xfrm/a:ext', $forma)->item(0);
	$posicion = max(0, (int) $ext->getAttribute('cx') - 182880);
	foreach ($xp->query('.//a:p', $forma) as $p) {
		$pPr = null;
		foreach ($p->childNodes as $h) {
			if ($h instanceof DOMElement && $h->localName === 'pPr') {
				$pPr = $h;
			}
		}
		if (!$pPr) {
			$pPr = $dom->createElementNS(EP_PPT_NS_A, 'a:pPr');
			$p->insertBefore($pPr, $p->firstChild);
		}
		$lista = $dom->createElementNS(EP_PPT_NS_A, 'a:tabLst');
		$tab = $dom->createElementNS(EP_PPT_NS_A, 'a:tab');
		$tab->setAttribute('pos', (string) $posicion);
		$tab->setAttribute('algn', 'r');
		$lista->appendChild($tab);
		$pPr->appendChild($lista);
	}
}

// Mueve una forma en vertical sin tocar su tamaño.
function ep_ppt_posicion_y(DOMXPath $xp, string $nombre, int $y): void {
	$forma = ep_ppt_forma($xp, $nombre);
	$off = $forma ? $xp->query('.//a:xfrm/a:off', $forma)->item(0) : null;
	if ($off) {
		$off->setAttribute('y', (string) $y);
	}
}

// Mueve una forma en horizontal sin tocar su tamaño.
function ep_ppt_posicion_x(DOMXPath $xp, string $nombre, int $x): void {
	$forma = ep_ppt_forma($xp, $nombre);
	$off = $forma ? $xp->query('.//a:xfrm/a:off', $forma)->item(0) : null;
	if ($off) {
		$off->setAttribute('x', (string) $x);
	}
}

// Mismo diseño de barra que las estadísticas de la web: esquinas redondeadas (solo las de arriba si es vertical) y, si es la barra de datos, el azul de Epson.
function ep_ppt_estilo_barra(DOMXPath $xp, string $nombre, bool $colorear, bool $soloArriba = false): void {
	$forma = ep_ppt_forma($xp, $nombre);
	$geom = $forma ? $xp->query('./p:spPr/a:prstGeom', $forma)->item(0) : null;
	if (!$geom) {
		return;
	}
	$doc = $geom->ownerDocument;
	$geom->setAttribute('prst', $soloArriba ? 'round2SameRect' : 'roundRect');
	while ($geom->firstChild) {
		$geom->removeChild($geom->firstChild);
	}
	$lista = $doc->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'a:avLst');
	$ajustes = $soloArriba ? ['adj1' => 8000, 'adj2' => 0] : ['adj' => 15000];
	foreach ($ajustes as $clave => $valor) {
		$ajuste = $doc->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'a:gd');
		$ajuste->setAttribute('name', $clave);
		$ajuste->setAttribute('fmla', 'val '.$valor);
		$lista->appendChild($ajuste);
	}
	$geom->appendChild($lista);
	if (!$colorear) {
		return;
	}
	foreach ($xp->query('./p:spPr/a:solidFill', $forma) as $relleno) {
		$relleno->parentNode->removeChild($relleno);
	}
	$relleno = $doc->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'a:solidFill');
	$color = $doc->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'a:srgbClr');
	$color->setAttribute('val', '10218B');
	$relleno->appendChild($color);
	$geom->parentNode->insertBefore($relleno, $geom->nextSibling);
}

// Barra vertical: la plantilla la dibuja como rectángulo girado 270 grados; aquí queda sin giro, de $largo de alto, centrada en su columna y apoyada en $base.
// Su ancho baja a $ancho veces el de la plantilla y toma el diseño de barra de las estadísticas (solo esquinas de arriba redondeadas).
function ep_ppt_barra_vertical(DOMXPath $xp, string $nombre, int $largo, int $base, float $ancho = 0.5): void {
	$forma = ep_ppt_forma($xp, $nombre);
	if (!$forma) {
		return;
	}
	$xfrm = $xp->query('./p:spPr/a:xfrm', $forma)->item(0);
	$off = $xp->query('./p:spPr/a:xfrm/a:off', $forma)->item(0);
	$ext = $xp->query('./p:spPr/a:xfrm/a:ext', $forma)->item(0);
	if (!$xfrm || !$off || !$ext) {
		return;
	}
	$centroX = (int) $off->getAttribute('x') + intdiv((int) $ext->getAttribute('cx'), 2);
	$grosor = (int) round((int) $ext->getAttribute('cy') * $ancho);
	$xfrm->removeAttribute('rot');
	$ext->setAttribute('cx', (string) $grosor);
	$ext->setAttribute('cy', (string) $largo);
	$off->setAttribute('x', (string) ($centroX - intdiv($grosor, 2)));
	$off->setAttribute('y', (string) ($base - $largo));
	ep_ppt_estilo_barra($xp, $nombre, true, true);
}

// Ensancha todo lo que queda a la derecha de $x0 (las estadísticas, no el panel del promotor ni el título) $factor veces, manteniendo las proporciones entre formas.
function ep_ppt_ensanchar(DOMXPath $xp, float $factor, int $x0 = 2618686): void {
	foreach ($xp->query('/p:sld/p:cSld/p:spTree/*') as $forma) {
		$off = $xp->query('./p:spPr/a:xfrm/a:off | ./p:grpSpPr/a:xfrm/a:off | ./p:xfrm/a:off', $forma)->item(0);
		$ext = $xp->query('./p:spPr/a:xfrm/a:ext | ./p:grpSpPr/a:xfrm/a:ext | ./p:xfrm/a:ext', $forma)->item(0);
		if (!$off || !$ext || (int) $off->getAttribute('x') < $x0 - 50000) {
			continue;
		}
		$off->setAttribute('x', (string) (int) round($x0 + ((int) $off->getAttribute('x') - $x0) * $factor));
		$ext->setAttribute('cx', (string) (int) round((int) $ext->getAttribute('cx') * $factor));
	}
}

// Mayúsculas también con tildes (strtoupper deja las tildes en minúscula).
function ep_ppt_mayus(string $texto): string {
	return function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
}

// Comentarios en un solo cuadro (uno por párrafo, ancho $ancho) y se quitan los demás cuadros: así ningún comentario largo pisa al siguiente.
function ep_ppt_comentarios(DOMDocument $dom, DOMXPath $xp, array $cuadros, array $comentarios, int $ancho): void {
	$forma = ep_ppt_forma($xp, $cuadros[0]);
	if (!$forma) {
		return;
	}
	foreach (array_slice($cuadros, 1) as $sobrante) {
		ep_ppt_quitar($xp, $sobrante);
	}
	$parrafos = [];
	foreach ($xp->query('.//a:p', $forma) as $p) {
		$parrafos[] = $p;
	}
	$primero = array_shift($parrafos);
	foreach ($parrafos as $p) {
		$p->parentNode->removeChild($p);
	}
	$lineas = array_values(array_filter(array_map('ep_ppt_mayus', $comentarios), fn($c) => trim($c) !== ''));
	$previo = $primero;
	foreach ($lineas ?: [''] as $i => $linea) {
		$p = $i === 0 ? $primero : $primero->cloneNode(true);
		if ($i > 0) {
			$previo->parentNode->insertBefore($p, $previo->nextSibling);
		}
		ep_ppt_poner_texto($dom, $p, $linea);
		$previo = $p;
	}
	ep_ppt_ancho($xp, $cuadros[0], $ancho);
}

function ep_ppt_ancho(DOMXPath $xp, string $nombre, int $cx): void {
	$forma = ep_ppt_forma($xp, $nombre);
	if (!$forma) {
		return;
	}
	$ext = $xp->query('.//a:xfrm/a:ext', $forma)->item(0);
	if ($ext) {
		$ext->setAttribute('cx', (string) max(0, $cx));
	}
}

// Cambia el rectángulo marcador por la foto, recortada para llenar el cuadro sin deformarse.
function ep_ppt_foto(DOMDocument $dom, DOMXPath $xp, string $nombre, string $rId, int $anchoImg, int $altoImg): void {
	$forma = ep_ppt_forma($xp, $nombre);
	if (!$forma || $anchoImg <= 0 || $altoImg <= 0) {
		return;
	}
	$off = $xp->query('.//a:xfrm/a:off', $forma)->item(0);
	$ext = $xp->query('.//a:xfrm/a:ext', $forma)->item(0);
	$id = $xp->query('*/p:cNvPr', $forma)->item(0)->getAttribute('id');
	$cx = (int) $ext->getAttribute('cx');
	$cy = (int) $ext->getAttribute('cy');
	$objetivo = $cx / $cy;
	$imagen = $anchoImg / $altoImg;
	$l = $r = $t = $b = 0;
	if ($imagen > $objetivo) {
		$l = $r = (int) round((1 - $objetivo / $imagen) / 2 * 100000);
	} else {
		$t = $b = (int) round((1 - $imagen / $objetivo) / 2 * 100000);
	}
	$xml = '<p:pic xmlns:a="'.EP_PPT_NS_A.'" xmlns:p="'.EP_PPT_NS_P.'" xmlns:r="'.EP_PPT_NS_R.'">'
		.'<p:nvPicPr><p:cNvPr id="'.$id.'" name="Foto '.$id.'"/><p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr>'
		.'<p:blipFill><a:blip r:embed="'.$rId.'"/><a:srcRect l="'.$l.'" t="'.$t.'" r="'.$r.'" b="'.$b.'"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>'
		.'<p:spPr><a:xfrm><a:off x="'.$off->getAttribute('x').'" y="'.$off->getAttribute('y').'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic>';
	$frag = new DOMDocument();
	$frag->loadXML($xml);
	$nuevo = $dom->importNode($frag->documentElement, true);
	$forma->parentNode->replaceChild($nuevo, $forma);
}

// Descarga en paralelo las fotos (URL pública de Azure). Devuelve url => [bytes, ancho, alto] solo de las que respondieron bien.
function ep_ppt_descargar_fotos(array $urls): array {
	$urls = array_values(array_unique(array_filter($urls)));
	$resultado = [];
	if (empty($urls)) {
		return $resultado;
	}
	$multi = curl_multi_init();
	$manijas = [];
	foreach ($urls as $u) {
		$ch = curl_init($u);
		curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_FOLLOWLOCATION => true]);
		curl_multi_add_handle($multi, $ch);
		$manijas[$u] = $ch;
	}
	do {
		$estado = curl_multi_exec($multi, $activos);
		if ($activos) {
			curl_multi_select($multi, 1.0);
		}
	} while ($activos && $estado === CURLM_OK);
	foreach ($manijas as $u => $ch) {
		$bytes = curl_multi_getcontent($ch);
		$codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_multi_remove_handle($multi, $ch);
		curl_close($ch);
		if ($codigo === 200 && $bytes !== '' && ($dim = @getimagesizefromstring($bytes))) {
			if ($dim['mime'] === 'image/webp' && ($jpeg = ep_ppt_webp_a_jpeg($bytes))) {
				$bytes = $jpeg;
				$dim = @getimagesizefromstring($bytes);
			}
			// PowerPoint solo admite JPEG y PNG (WEBP se omite).
			$ext = $dim['mime'] === 'image/png' ? 'png' : ($dim['mime'] === 'image/jpeg' ? 'jpeg' : '');
			if ($ext !== '') {
				$resultado[$u] = [$bytes, (int) $dim[0], (int) $dim[1], $ext];
			}
		}
	}
	curl_multi_close($multi);
	return $resultado;
}

function ep_ppt_pct($v): string {
	return (int) round((float) $v).'%';
}
