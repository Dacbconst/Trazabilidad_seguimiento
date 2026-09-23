<?php
// Genera el PPTX de Activaciones a partir de la plantilla oficial (recursos/ppt/activaciones.pptx): portada + título del mes una vez,
// y 4 diapositivas por registro (calendario, estadísticas, fotos 1-3, fotos 4-6) clonadas de las diapositivas 3 a 6 de la plantilla.

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
	return (int) round((float) $v).' %';
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_activaciones(array $registros, string $tituloMes): string {
	$plantilla = __DIR__.'/../recursos/ppt/activaciones.pptx';
	$tpl = new ZipArchive();
	if ($tpl->open($plantilla) !== true) {
		throw new RuntimeException('No se pudo abrir la plantilla de Activaciones.');
	}
	$leer = fn($nombre) => $tpl->getFromName($nombre);

	$urls = [];
	foreach ($registros as $reg) {
		foreach ($reg['fotos'] ?? [] as $f) {
			if (!empty($f['url'])) {
				$urls[] = $f['url'];
			}
		}
	}
	$fotos = ep_ppt_descargar_fotos($urls);

	$destino = tempnam(sys_get_temp_dir(), 'epppt');
	$out = new ZipArchive();
	$out->open($destino, ZipArchive::OVERWRITE);

	// Copiar tal cual todo lo que no cambia.
	$saltar = ['[Content_Types].xml', 'ppt/presentation.xml', 'ppt/_rels/presentation.xml.rels', 'ppt/slides/slide2.xml'];
	for ($n = 3; $n <= 6; $n++) {
		$saltar[] = 'ppt/slides/slide'.$n.'.xml';
		$saltar[] = 'ppt/slides/_rels/slide'.$n.'.xml.rels';
	}
	for ($i = 0; $i < $tpl->numFiles; $i++) {
		$nombre = $tpl->getNameIndex($i);
		if (!in_array($nombre, $saltar, true) && substr($nombre, -1) !== '/') {
			$out->addFromString($nombre, $tpl->getFromIndex($i));
		}
	}

	// Diapositiva 2: título del mes.
	[$dom2, $xp2] = ep_ppt_cargar($leer('ppt/slides/slide2.xml'));
	ep_ppt_texto($dom2, $xp2, 'Subtítulo 2', ['ACTIVACIONES', $tituloMes]);
	$out->addFromString('ppt/slides/slide2.xml', $dom2->saveXML());

	$slidesNuevas = [];
	$contador = 3;
	$relFotos = [];

	foreach ($registros as $k => $reg) {
		$mapaFotos = [];
		foreach ($reg['fotos'] ?? [] as $f) {
			if (!empty($f['url']) && isset($fotos[$f['url']])) {
				$mapaFotos[$f['id']] = $fotos[$f['url']];
			}
		}
		$numeroSlide = [];
		for ($t = 3; $t <= 6; $t++) {
			$numeroSlide[$t] = $contador++;
		}
		$emb = $reg['embudo'] ?? [];
		$cob = $reg['cobertura'] ?? [];
		$cum = $reg['cumplimiento'] ?? [];
		$modelos = $reg['modelos'] ?? [];
		usort($modelos, fn($a, $b) => ($b['cantidad'] ?? 0) <=> ($a['cantidad'] ?? 0));
		$comentarios = array_values($reg['comentarios'] ?? []);
		$meses = ep_ppt_meses();
		$fechaIso = $reg['fecha_iso'] ?? '';
		$fechaTxt = $fechaIso ? ((int) substr($fechaIso, 8, 2)).' – '.$meses[(int) substr($fechaIso, 5, 2)].' – '.substr($fechaIso, 0, 4) : '';
		$punto = strtoupper($reg['punto_venta'] ?? '');

		// Fotos de esta diapositiva: [nombre del rectángulo => id de foto]
		$plan = [
			3 => ['Rectángulo 14' => 'calendario'],
			5 => ['Rectángulo 4' => 'stand', 'Rectángulo 33' => 'interaccion-1', 'Rectángulo 34' => 'interaccion-2'],
			6 => ['Rectángulo 4' => 'venta-1', 'Rectángulo 33' => 'venta-2', 'Rectángulo 34' => 'venta-3'],
		];

		for ($t = 3; $t <= 6; $t++) {
			[$dom, $xp] = ep_ppt_cargar($leer('ppt/slides/slide'.$t.'.xml'));
			$rels = $leer('ppt/slides/_rels/slide'.$t.'.xml.rels');
			$relsExtra = '';

			if ($t === 3) {
				ep_ppt_texto($dom, $xp, 'CuadroTexto 12', [ep_ppt_pct($cum['pct'] ?? 0)]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 13', [($cum['programadas'] ?? 0).' ACTIVACIONES PROGRAMADAS', ($cum['realizadas'] ?? 0).' EJECUTADAS']);
				foreach (['CuadroTexto 27', 'CuadroTexto 28', 'CuadroTexto 33'] as $i => $nom) {
					ep_ppt_texto($dom, $xp, $nom, [strtoupper($comentarios[$i] ?? '')]);
				}
			} elseif ($t === 4) {
				ep_ppt_quitar($xp, 'Gráfico 19'); // foto de ejemplo del promotor de la plantilla
				ep_ppt_texto($dom, $xp, 'CuadroTexto 16', [strtoupper($reg['promotor'] ?? '')]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 17', ['']);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 20', [$punto]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 21', [strtoupper($reg['actividad_badge'] ?? '')]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 22', [$fechaTxt, $reg['hora'] ?? '']);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 23', [strtoupper($reg['ciudad'] ?? '').' - ECUADOR']);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 94', [ep_ppt_pct($cob['pct'] ?? 0)]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 95', [($cob['nacional'] ?? 0).' TIENDAS A NIVEL NACIONAL', ($cob['coberturadas'] ?? 0).' TIENDAS COBERTURADAS']);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 96', [ep_ppt_pct($emb['tasa_interaccion_pct'] ?? 0)]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 97', [($emb['visitaron'] ?? 0).' CLIENTES VISITARON LA TIENDA', ($emb['interactuaron'] ?? 0).' CLIENTES INTERACTUARON']);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 101', [ep_ppt_pct($emb['tasa_conversion_pct'] ?? 0)]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 102', [($emb['compraron'] ?? 0).' VENTAS EFECTIVAS']);

				// Detalle de ventas: hasta 5 modelos con su barra proporcional al más vendido.
				$filas = [
					['CuadroTexto 113', 'CuadroTexto 141', 'Rectángulo 115'],
					['CuadroTexto 124', 'CuadroTexto 145', 'Rectángulo 131'],
					['CuadroTexto 125', 'CuadroTexto 144', 'Rectángulo 134'],
					['CuadroTexto 126', 'CuadroTexto 143', 'Rectángulo 137'],
					['CuadroTexto 127', 'CuadroTexto 142', 'Rectángulo 140'],
				];
				$maxCant = max(1, (int) ($modelos[0]['cantidad'] ?? 1));
				foreach ($filas as $i => [$nomTxt, $nomNum, $nomBarra]) {
					$m = $modelos[$i] ?? null;
					ep_ppt_texto($dom, $xp, $nomTxt, [$m ? strtoupper($m['modelo']) : '']);
					ep_ppt_texto($dom, $xp, $nomNum, [$m ? (string) $m['cantidad'] : '']);
					ep_ppt_ancho($xp, $nomBarra, $m ? (int) round(2431998 * ((int) $m['cantidad']) / $maxCant) : 0);
				}
				$mayor = $modelos[0] ?? null;
				$menor = !empty($modelos) ? $modelos[count($modelos) - 1] : null;
				ep_ppt_texto($dom, $xp, 'CuadroTexto 146', [$mayor ? strtoupper($mayor['modelo']) : '']);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 150', [$mayor ? ep_ppt_pct(rtrim($mayor['pct'], '%')) : '']);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 148', [$menor ? strtoupper($menor['modelo']) : '']);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 151', [$menor ? ep_ppt_pct(rtrim($menor['pct'], '%')) : '']);

				// Embudo: clientes / interacciones / ventas, barras proporcionales a los clientes en tienda.
				$vis = max(1, (int) ($emb['visitaron'] ?? 0));
				ep_ppt_texto($dom, $xp, 'CuadroTexto 165', [(string) ($emb['visitaron'] ?? 0)]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 166', [(string) ($emb['interactuaron'] ?? 0)]);
				ep_ppt_texto($dom, $xp, 'CuadroTexto 167', [(string) ($emb['compraron'] ?? 0)]);
				ep_ppt_ancho($xp, 'Rectángulo 158', (int) round(905791 * ($emb['interactuaron'] ?? 0) / $vis));
				ep_ppt_ancho($xp, 'Rectángulo 163', (int) round(905791 * ($emb['compraron'] ?? 0) / $vis));
				foreach (['CuadroTexto 5', 'CuadroTexto 6', 'CuadroTexto 7', 'CuadroTexto 9', 'CuadroTexto 10'] as $i => $nom) {
					ep_ppt_texto($dom, $xp, $nom, [strtoupper($comentarios[$i] ?? '')]);
				}
			} else {
				ep_ppt_texto($dom, $xp, 'CuadroTexto 2', [$punto]);
			}

			// Fotos de la diapositiva.
			foreach ($plan[$t] ?? [] as $rect => $idFoto) {
				if (!isset($mapaFotos[$idFoto])) {
					ep_ppt_quitar($xp, $rect); // sin foto: se quita el cuadro de ejemplo de la plantilla
					continue;
				}
				[$bytes, $an, $al, $extFoto] = $mapaFotos[$idFoto];
				$nombreMedia = 'foto_'.$k.'_'.$t.'_'.preg_replace('/W/', '', $idFoto).'.'.$extFoto;
				$relFotos[$nombreMedia] = $bytes;
				$rId = 'rIdF'.$k.$t.preg_replace('/\W/', '', $idFoto);
				$relsExtra .= '<Relationship Id="'.$rId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'.$nombreMedia.'"/>';
				ep_ppt_foto($dom, $xp, $rect, $rId, $an, $al);
			}

			$out->addFromString('ppt/slides/slide'.$numeroSlide[$t].'.xml', $dom->saveXML());
			$out->addFromString('ppt/slides/_rels/slide'.$numeroSlide[$t].'.xml.rels', str_replace('</Relationships>', $relsExtra.'</Relationships>', $rels));
			$slidesNuevas[] = $numeroSlide[$t];
		}
	}

	foreach ($relFotos as $nombre => $bytes) {
		$out->addFromString('ppt/media/'.$nombre, $bytes);
	}

	// presentation.xml: lista de diapositivas = portada, título del mes y las nuevas.
	$pres = $leer('ppt/presentation.xml');
	$lista = '<p:sldIdLst><p:sldId id="256" r:id="rId2"/><p:sldId id="257" r:id="rId3"/>';
	$relsPres = $leer('ppt/_rels/presentation.xml.rels');
	$relsPres = preg_replace('#<Relationship Id="rId[4-7]"[^>]*slides/slide[3-6]\.xml"/>#', '', $relsPres);
	$nuevasRel = '';
	foreach ($slidesNuevas as $i => $n) {
		$lista .= '<p:sldId id="'.(1000 + $i).'" r:id="rIdS'.$n.'"/>';
		$nuevasRel .= '<Relationship Id="rIdS'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide'.$n.'.xml"/>';
	}
	$lista .= '</p:sldIdLst>';
	$pres = preg_replace('#<p:sldIdLst>.*?</p:sldIdLst>#s', $lista, $pres);
	$out->addFromString('ppt/presentation.xml', $pres);
	$out->addFromString('ppt/_rels/presentation.xml.rels', str_replace('</Relationships>', $nuevasRel.'</Relationships>', $relsPres));

	// [Content_Types].xml: una entrada por cada diapositiva nueva.
	$tipos = $leer('[Content_Types].xml');
	$tipos = preg_replace('#<Override PartName="/ppt/slides/slide[3-6]\.xml"[^>]*/>#', '', $tipos);
	$overrides = '';
	foreach ($slidesNuevas as $n) {
		$overrides .= '<Override PartName="/ppt/slides/slide'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
	}
	if (strpos($tipos, 'Extension="png"') === false) {
		$overrides = '<Default Extension="png" ContentType="image/png"/>'.$overrides;
	}
	$out->addFromString('[Content_Types].xml', str_replace('</Types>', $overrides.'</Types>', $tipos));

	$out->close();
	$tpl->close();
	return $destino;
}
