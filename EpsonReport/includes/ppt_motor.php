<?php
// Motor común de los PPTX de Epson: cada actividad solo aporta su especificación y la función que llena sus estadísticas.

require_once __DIR__.'/ppt_base.php';

const EP_PPT_TIPO_IMAGEN = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
const EP_PPT_TIPO_SLIDE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide';

// "26 – SEPTIEMBRE – 2026" a partir de una fecha Y-m-d.
function ep_ppt_fecha_texto(string $iso): string {
	if ($iso === '') {
		return '';
	}
	$meses = ep_ppt_meses();
	return ((int) substr($iso, 8, 2)).' – '.$meses[(int) substr($iso, 5, 2)].' – '.substr($iso, 0, 4);
}

// Texto de la actividad en la barra: "PREFIJO TIPO", sin repetir el prefijo si el promotor ya lo escribió.
function ep_ppt_etiqueta_actividad(array $reg, string $prefijo): string {
	$tipo = (string) ($reg['tipo_actividad'] ?? '');
	if ($tipo === '') {
		return $prefijo;
	}
	return strpos($tipo, $prefijo) === 0 ? $tipo : $prefijo.' '.$tipo;
}

// Barra azul del promotor, igual en todas las plantillas; $n son los nombres de forma (Epson Day usa otros que las demás).
function ep_ppt_barra_promotor(array &$ctx, array &$slide, array $reg, string $etiquetaActividad, ?array $n = null): void {
	$dom = $slide['dom'];
	$xp = $slide['xp'];
	$n = $n ?? ['nombre' => 'CuadroTexto 16', 'correo' => 'CuadroTexto 17', 'punto' => 'CuadroTexto 20', 'actividad' => 'CuadroTexto 21', 'fecha' => 'CuadroTexto 22', 'ciudad' => 'CuadroTexto 23', 'foto' => 'Gráfico 19'];
	$punto = ep_ppt_mayus((string) ($reg['punto_venta'] ?? ''));
	$horario = !empty($reg['hora_inicio']) ? $reg['hora_inicio'].' – '.$reg['hora_fin'] : (string) ($reg['hora'] ?? '');
	// Con foto de perfil del promotor se inserta en el cuadro de la plantilla; sin ella, se quita (queda vacío, como hasta ahora).
	$fotoUrl = $reg['promotor_foto_url'] ?? '';
	if ($fotoUrl !== '' && isset($ctx['fotos'][$fotoUrl])) {
		ep_ppt_slide_imagen($ctx, $slide, $n['foto'], $ctx['fotos'][$fotoUrl], false, true);
	} else {
		ep_ppt_quitar($xp, $n['foto']);
	}
	$nombrePromotor = ep_ppt_mayus((string) ($reg['promotor'] ?? ''));
	ep_ppt_texto($dom, $xp, $n['nombre'], [$nombrePromotor]);
	// El nombre es el legal completo (2 apellidos + 2 nombres, de Xplora): la mayoría no entra en una línea y pasa a 2, montándose con el correo justo debajo. Se corre todo ese bloque, nunca se recorta un nombre de persona.
	$medNombre = ep_ppt_medidas($xp, $n['nombre']);
	if ($medNombre && !ep_ppt_cabe_una_linea($nombrePromotor, $medNombre[2], 16)) {
		foreach ([$n['correo'], $n['punto'], $n['ciudad']] as $campo) {
			ep_ppt_mover($xp, $campo, 290000);
		}
	}
	ep_ppt_texto($dom, $xp, $n['correo'], [strtolower((string) ($reg['promotor_correo'] ?? ''))]);
	ep_ppt_texto($dom, $xp, $n['punto'], [$punto]);
	ep_ppt_texto($dom, $xp, $n['actividad'], [$etiquetaActividad]);
	ep_ppt_texto($dom, $xp, $n['fecha'], [ep_ppt_fecha_texto((string) ($reg['fecha_actividad'] ?? ($reg['fecha_iso'] ?? ''))), $horario]);
	ep_ppt_texto($dom, $xp, $n['ciudad'], [ep_ppt_mayus((string) ($reg['ciudad'] ?? '')).' - ECUADOR']);
	// Si el punto de venta se parte en varias líneas, la ciudad baja para no montarse (además del corrimiento de arriba, si aplicó).
	ep_ppt_mover($xp, $n['ciudad'], min(3, max(0, (int) ceil(mb_strlen($punto) / 15) - 1)) * 190000);
}

// x, y, ancho y alto de una forma; null si no existe.
function ep_ppt_medidas(DOMXPath $xp, string $nombre): ?array {
	$forma = ep_ppt_forma($xp, $nombre);
	$off = $forma ? $xp->query('.//a:xfrm/a:off', $forma)->item(0) : null;
	$ext = $forma ? $xp->query('.//a:xfrm/a:ext', $forma)->item(0) : null;
	if (!$off || !$ext) {
		return null;
	}
	return [(int) $off->getAttribute('x'), (int) $off->getAttribute('y'), (int) $ext->getAttribute('cx'), (int) $ext->getAttribute('cy')];
}

// Abre la plantilla y deja el contexto listo: copia lo que no cambia y descarga las fotos de todos los registros.
function ep_ppt_abrir(array $spec, array $registros, array $opciones): array {
	$tpl = new ZipArchive();
	if ($tpl->open($spec['plantilla']) !== true) {
		throw new RuntimeException('No se pudo abrir la plantilla.');
	}
	$urls = [];
	foreach ($registros as $reg) {
		foreach ($reg['fotos'] ?? [] as $f) {
			if (!empty($f['url'])) {
				$urls[] = $f['url'];
			}
		}
		if (!empty($reg['promotor_foto_url'])) {
			$urls[] = $reg['promotor_foto_url'];
		}
	}
	$solo = !empty($opciones['solo_registro']);
	$out = new ZipArchive();
	$destino = tempnam(sys_get_temp_dir(), 'epppt');
	$out->open($destino, ZipArchive::OVERWRITE);
	// Se copia todo salvo lo que se reescribe: las diapositivas desde la 3 (se rehacen por registro), el título del mes y los índices.
	for ($i = 0; $i < $tpl->numFiles; $i++) {
		$nombre = $tpl->getNameIndex($i);
		if (substr($nombre, -1) === '/' || in_array($nombre, ['[Content_Types].xml', 'ppt/presentation.xml', 'ppt/_rels/presentation.xml.rels'], true)) {
			continue;
		}
		if (preg_match('#^ppt/slides/(?:_rels/)?slide(\d+)\.xml(\.rels)?$#', $nombre, $m)) {
			$numero = (int) $m[1];
			$esRels = !empty($m[2]);
			if ($numero >= 3 || ($solo && $numero <= 2) || (!$solo && $numero === 2 && !$esRels)) {
				continue;
			}
		}
		$out->addFromString($nombre, $tpl->getFromIndex($i));
	}
	return ['tpl' => $tpl, 'out' => $out, 'destino' => $destino, 'solo' => $solo, 'fotos' => ep_ppt_descargar_fotos($urls), 'media' => [], 'nuevas' => [], 'siguiente' => 4];
}

// Diapositiva nueva clonada de una de la plantilla; $numero fijo solo para las que van una vez por reporte (el calendario).
function ep_ppt_slide_nueva(array &$ctx, int $plantillaN, ?int $numero = null): array {
	[$dom, $xp] = ep_ppt_cargar((string) $ctx['tpl']->getFromName('ppt/slides/slide'.$plantillaN.'.xml'));
	return [
		'dom' => $dom,
		'xp' => $xp,
		'rels' => (string) $ctx['tpl']->getFromName('ppt/slides/_rels/slide'.$plantillaN.'.xml.rels'),
		'extra' => '',
		'numero' => $numero ?? $ctx['siguiente']++,
	];
}

function ep_ppt_slide_guardar(array &$ctx, array $slide): void {
	$n = $slide['numero'];
	$ctx['out']->addFromString('ppt/slides/slide'.$n.'.xml', $slide['dom']->saveXML());
	$ctx['out']->addFromString('ppt/slides/_rels/slide'.$n.'.xml.rels', str_replace('</Relationships>', $slide['extra'].'</Relationships>', $slide['rels']));
	$ctx['nuevas'][] = $n;
}

// Pone una imagen descargada ([bytes, ancho, alto, extensión]) en el cuadro marcador de la diapositiva.
function ep_ppt_slide_imagen(array &$ctx, array &$slide, string $rect, array $imagen, bool $completa = false, bool $circular = false): void {
	[$bytes, $ancho, $alto, $extension] = $imagen;
	$indice = count($ctx['media']) + 1;
	$nombre = 'img'.$indice.'.'.$extension;
	$ctx['media'][$nombre] = $bytes;
	$rId = 'rIdImg'.$indice;
	$slide['extra'] .= '<Relationship Id="'.$rId.'" Type="'.EP_PPT_TIPO_IMAGEN.'" Target="../media/'.$nombre.'"/>';
	ep_ppt_foto($slide['dom'], $slide['xp'], $rect, $rId, $ancho, $alto, $completa, $circular);
}

// Pie de cada foto ('pies' en el spec, paralelo a 'rects'): sigue el ancho y la posición de su foto; sin foto, se quita.
function ep_ppt_pies_fotos(array $slide, array $spec, array $reg, array $colocacion): void {
	$pies = $spec['fotos']['pies'] ?? [];
	$fotos = array_column($reg['fotos'] ?? [], null, 'id');
	foreach ($spec['fotos']['rects'] as $i => $rect) {
		$pie = $pies[$i] ?? null;
		if ($pie === null) {
			continue;
		}
		if (!isset($colocacion[$rect])) {
			ep_ppt_quitar($slide['xp'], $pie);
			continue;
		}
		$foto = ep_ppt_medidas($slide['xp'], $rect);
		$caja = ep_ppt_medidas($slide['xp'], $pie);
		if ($foto && $caja) {
			ep_ppt_geometria($slide['xp'], $pie, $foto[0], $caja[1], $foto[2], $caja[3]);
		}
		ep_ppt_texto($slide['dom'], $slide['xp'], $pie, $spec['fotos']['pie_texto']($reg, $fotos[$colocacion[$rect]] ?? []));
	}
}

// Diapositiva de fotos: título (el punto de venta, o el que arme el spec) y hasta 3 fotos. Con 2 ocupan mitad y mitad y con 1 va centrada.
function ep_ppt_slide_fotos(array &$ctx, array $spec, array $reg, array $grupo, array $mapaFotos): void {
	$slide = ep_ppt_slide_nueva($ctx, $spec['fotos']['n']);
	$titulo = isset($spec['fotos']['titulo_texto']) ? $spec['fotos']['titulo_texto']($reg) : strtoupper((string) ($reg['punto_venta'] ?? ''));
	ep_ppt_texto($slide['dom'], $slide['xp'], $spec['fotos']['titulo'], [$titulo]);
	$rects = $spec['fotos']['rects'];
	if (count($grupo) === 3) {
		$colocacion = array_combine($rects, $grupo);
	} elseif (count($grupo) === 2) {
		$izquierda = ep_ppt_medidas($slide['xp'], $rects[0]);
		$derecha = ep_ppt_medidas($slide['xp'], $rects[2]);
		$centro = ep_ppt_medidas($slide['xp'], $rects[1]);
		if ($izquierda && $derecha && $centro) {
			$hueco = $centro[0] - ($izquierda[0] + $izquierda[2]);
			$ancho = intdiv(($derecha[0] + $derecha[2]) - $izquierda[0] - $hueco, 2);
			ep_ppt_geometria($slide['xp'], $rects[0], $izquierda[0], $izquierda[1], $ancho, $izquierda[3]);
			ep_ppt_geometria($slide['xp'], $rects[2], $izquierda[0] + $ancho + $hueco, $izquierda[1], $ancho, $izquierda[3]);
		}
		$colocacion = [$rects[0] => $grupo[0], $rects[2] => $grupo[1]];
	} else {
		$colocacion = [$rects[1] => $grupo[0]];
	}
	// Los pies se acomodan antes de poner las fotos: al ponerla, el cuadro marcador se reemplaza y pierde su nombre.
	ep_ppt_pies_fotos($slide, $spec, $reg, $colocacion);
	foreach ($rects as $rect) {
		if (!isset($colocacion[$rect])) {
			ep_ppt_quitar($slide['xp'], $rect); // sin foto para ese lugar: se quita el cuadro de ejemplo
		}
	}
	foreach ($colocacion as $rect => $idFoto) {
		ep_ppt_slide_imagen($ctx, $slide, $rect, $mapaFotos[$idFoto]);
	}
	ep_ppt_slide_guardar($ctx, $slide);
}

// Un registro = una diapositiva de estadísticas + las de fotos (3 por diapositiva, las obligatorias primero).
function ep_ppt_registro(array &$ctx, array $spec, array $reg): void {
	$mapaFotos = [];
	foreach ($reg['fotos'] ?? [] as $f) {
		if (!empty($f['url']) && isset($ctx['fotos'][$f['url']])) {
			$mapaFotos[$f['id']] = $ctx['fotos'][$f['url']];
		}
	}
	$antes = count($ctx['nuevas']);
	// Actividades sin dato cuantitativo (informe fotográfico simple) no llevan diapositiva de estadísticas, solo fotos.
	if (!empty($spec['stats'])) {
		$slide = ep_ppt_slide_nueva($ctx, $spec['stats']['n']);
		ep_ppt_barra_promotor($ctx, $slide, $reg, ep_ppt_etiqueta_actividad($reg, $spec['prefijo_actividad']), $spec['promotor'] ?? null);
		$spec['stats']['llenar']($slide['dom'], $slide['xp'], $reg);
		if (!empty($spec['stats']['ensanchar'])) {
			ep_ppt_ensanchar($slide['xp'], (float) $spec['stats']['ensanchar']);
		}
		ep_ppt_slide_guardar($ctx, $slide);
	}
	// Sin 'orden' fijo en el spec, se usan las fotos reales del registro (para actividades sin tope de fotos, como el informe fotográfico simple).
	$ordenFotos = $spec['fotos']['orden'] ?? array_column($reg['fotos'] ?? [], 'id');
	// Las fotos sumadas con "Agregar otra foto" van después de las fijas del spec.
	if (isset($spec['fotos']['orden'])) {
		$ordenFotos = array_merge($ordenFotos, array_values(array_diff(array_column($reg['fotos'] ?? [], 'id'), $ordenFotos)));
	}
	$ids = array_values(array_filter($ordenFotos, fn($id) => isset($mapaFotos[$id])));
	foreach (array_chunk($ids, 3) as $grupo) {
		ep_ppt_slide_fotos($ctx, $spec, $reg, $grupo, $mapaFotos);
	}
	$ctx['secciones'][] = ['nombre' => ep_ppt_mayus(trim((string) ($reg['promotor'] ?? '')) ?: 'SIN NOMBRE'), 'desde' => $antes, 'hasta' => count($ctx['nuevas'])];
}

// Secciones de PowerPoint: una por persona (nombre completo), en orden; la portada y el título del mes quedan en la primera.
function ep_ppt_secciones(array $ctx): string {
	$grupos = [];
	foreach ($ctx['secciones'] ?? [] as $s) {
		$ultimo = count($grupos) - 1;
		if ($ultimo >= 0 && $grupos[$ultimo]['nombre'] === $s['nombre']) {
			$grupos[$ultimo]['hasta'] = $s['hasta'];
		} else {
			$grupos[] = $s;
		}
	}
	$xml = '<p14:sectionLst xmlns:p14="http://schemas.microsoft.com/office/powerpoint/2010/main">';
	foreach ($grupos as $i => $g) {
		if ($i === 0) {
			$g['desde'] = 0; // también la diapositiva fija del reporte (calendario), que no es de nadie
		}
		$ids = $i === 0 && !$ctx['solo'] ? '<p14:sldId id="256"/><p14:sldId id="257"/>' : '';
		for ($n = $g['desde']; $n < $g['hasta']; $n++) {
			$ids .= '<p14:sldId id="'.(1000 + $n).'"/>';
		}
		$guid = strtoupper(sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)));
		$xml .= '<p14:section name="'.htmlspecialchars($g['nombre'], ENT_XML1).'" id="{'.$guid.'}"><p14:sldIdLst>'.$ids.'</p14:sldIdLst></p14:section>';
	}
	return $xml.'</p14:sectionLst>';
}

// Escribe los índices (presentación, relaciones y tipos) con las diapositivas nuevas y cierra el archivo.
function ep_ppt_cerrar(array &$ctx): string {
	$out = $ctx['out'];
	$tpl = $ctx['tpl'];
	$solo = $ctx['solo'];
	foreach ($ctx['media'] as $nombre => $bytes) {
		$out->addFromString('ppt/media/'.$nombre, $bytes);
	}
	$lista = $solo ? '<p:sldIdLst>' : '<p:sldIdLst><p:sldId id="256" r:id="rId2"/><p:sldId id="257" r:id="rId3"/>';
	$relsPres = (string) $tpl->getFromName('ppt/_rels/presentation.xml.rels');
	$relsPres = preg_replace('#<Relationship [^>]*Target="slides/slide(?:[3-9]|[1-9][0-9])\.xml"[^>]*/>#', '', $relsPres);
	if ($solo) {
		$relsPres = preg_replace('#<Relationship [^>]*Target="slides/slide[12]\.xml"[^>]*/>#', '', $relsPres);
	}
	$nuevasRel = '';
	foreach ($ctx['nuevas'] as $i => $n) {
		$lista .= '<p:sldId id="'.(1000 + $i).'" r:id="rIdS'.$n.'"/>';
		$nuevasRel .= '<Relationship Id="rIdS'.$n.'" Type="'.EP_PPT_TIPO_SLIDE.'" Target="slides/slide'.$n.'.xml"/>';
	}
	$lista .= '</p:sldIdLst>';
	$pres = preg_replace('#<p:sldIdLst>.*?</p:sldIdLst>#s', $lista, (string) $tpl->getFromName('ppt/presentation.xml'));
	$pres = preg_replace('#<p14:sectionLst .*?</p14:sectionLst>#s', ep_ppt_secciones($ctx), $pres);
	$out->addFromString('ppt/presentation.xml', $pres);
	$out->addFromString('ppt/_rels/presentation.xml.rels', str_replace('</Relationships>', $nuevasRel.'</Relationships>', $relsPres));

	$tipos = (string) $tpl->getFromName('[Content_Types].xml');
	$tipos = preg_replace('#<Override PartName="/ppt/slides/slide(?:[3-9]|[1-9][0-9])\.xml"[^>]*/>#', '', $tipos);
	if ($solo) {
		$tipos = preg_replace('#<Override PartName="/ppt/slides/slide[12]\.xml"[^>]*/>#', '', $tipos);
	}
	$agregado = '';
	foreach (['png' => 'image/png', 'jpeg' => 'image/jpeg'] as $extension => $tipo) {
		if (strpos($tipos, 'Extension="'.$extension.'"') === false) {
			$agregado .= '<Default Extension="'.$extension.'" ContentType="'.$tipo.'"/>';
		}
	}
	foreach ($ctx['nuevas'] as $n) {
		$agregado .= '<Override PartName="/ppt/slides/slide'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
	}
	$out->addFromString('[Content_Types].xml', str_replace('</Types>', $agregado.'</Types>', $tipos));
	$out->close();
	$tpl->close();
	return $ctx['destino'];
}

// Genera el PPTX de una actividad ($spec: plantilla/titulo/stats/fotos, opcional fija); devuelve la ruta de un temporal que el llamador borra.
function ep_ppt_generar(array $spec, array $registros, string $tituloMes, array $opciones = []): string {
	$ctx = ep_ppt_abrir($spec, $registros, $opciones);
	if (!$ctx['solo']) {
		[$dom, $xp] = ep_ppt_cargar((string) $ctx['tpl']->getFromName('ppt/slides/slide2.xml'));
		// Un botón nuevo que replica esta lógica lleva su propio nombre en el título, salvo que el formato de Epson lo fije ('titulo_fijo').
		$nombre = empty($spec['titulo_fijo']) ? (trim((string) ($opciones['nombre_actividad'] ?? '')) ?: $spec['titulo']) : $spec['titulo'];
		ep_ppt_texto($dom, $xp, $spec['portada_titulo'] ?? 'Subtítulo 2', [ep_ppt_mayus($nombre), $tituloMes]);
		$ctx['out']->addFromString('ppt/slides/slide2.xml', $dom->saveXML());
		if (!empty($spec['fija'])) {
			$spec['fija']($ctx, $registros, $opciones);
		}
	}
	foreach ($registros as $reg) {
		ep_ppt_registro($ctx, $spec, $reg);
	}
	return ep_ppt_cerrar($ctx);
}

// Orden de los registros antes de armar el PPTX. Por defecto, por promotor y fecha (una sección de PowerPoint por persona).
// Exhibiciones Regulares (pedido explícito 28-09): por ciudad, Guayaquil primero; dentro de cada ciudad el orden es indiferente, se usa el punto de venta para que quede estable.
function ep_ppt_ordenar_registros(array $registros, string $tipo): array {
	if ($tipo === 'informe-fotografico') {
		usort($registros, function ($a, $b) {
			$ciudadA = ep_ppt_mayus(trim((string) ($a['ciudad'] ?? '')));
			$ciudadB = ep_ppt_mayus(trim((string) ($b['ciudad'] ?? '')));
			$clave = fn($c) => ($c === 'GUAYAQUIL' ? '0' : '1').$c;
			return strcmp($clave($ciudadA).($a['punto_venta'] ?? ''), $clave($ciudadB).($b['punto_venta'] ?? ''));
		});
		return $registros;
	}
	usort($registros, fn($a, $b) => strcmp(($a['promotor'] ?? '').($a['fecha_actividad'] ?? $a['fecha_iso'] ?? '').($a['hora_inicio'] ?? $a['hora'] ?? ''), ($b['promotor'] ?? '').($b['fecha_actividad'] ?? $b['fecha_iso'] ?? '').($b['hora_inicio'] ?? $b['hora'] ?? '')));
	return $registros;
}

// Función que arma el PPTX de cada tipo de actividad; null si todavía no existe su plantilla.
function ep_ppt_generador(string $tipo): ?string {
	$generadores = [
		'activaciones' => ['ppt_activaciones.php', 'ep_ppt_activaciones'],
		'capacitaciones' => ['ppt_capacitaciones.php', 'ep_ppt_capacitaciones'],
		'epson-day' => ['ppt_epson_day.php', 'ep_ppt_epson_day'],
		'evento-ferias' => ['ppt_evento_ferias.php', 'ep_ppt_evento_ferias'],
		'exhibiciones' => ['ppt_exhibiciones.php', 'ep_ppt_exhibiciones'],
		'informe-fotografico' => ['ppt_informe_fotografico.php', 'ep_ppt_informe_fotografico'],
		'competencia' => ['ppt_competencia.php', 'ep_ppt_competencia'],
		'colocacion-pop' => ['ppt_colocacion_pop.php', 'ep_ppt_colocacion_pop'],
	];
	if (!isset($generadores[$tipo])) {
		return null;
	}
	require_once __DIR__.'/'.$generadores[$tipo][0];
	return $generadores[$tipo][1];
}
