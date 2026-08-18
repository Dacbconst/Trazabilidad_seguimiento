<?php
// Export a Excel (CSV, mismo criterio que Liquidación) de TODA la información
// pactada en las Actas — para que JW deje de re-tipear en su propio Excel lo
// que el ejecutivo ya cargó acá (ver CLAUDE.md, conversación 2026-08-18: "una
// vez que un dato se tipeó en el Acta, ESE pasa a ser el dato oficial").
// Mismos filtros que Historial (búsqueda + mes) y mismo scoping por
// creado_por — exporta lo que está filtrado en pantalla, nada más.
// A propósito NO es un resumen: van las 4 tablas del Acta (Meta de Compras,
// Cabecera, Ruma, Percha) tal cual se tipearon, no solo la parte de
// cuota/rebate — el usuario pidió explícitamente la info completa, no un
// resultado ya interpretado (2026-08-18, corrigiendo un alcance más angosto
// de la primera versión de este archivo).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo 'No autorizado.';
	exit;
}

$usuarioId = $_SESSION['user_id'] ?? null;
$busqueda  = trim($_GET['q'] ?? '');
$mes       = (int) ($_GET['mes'] ?? 0);
$mesIdx    = ($mes >= 1 && $mes <= 12) ? ($mes - 1) : -1;
$like      = '%'.$busqueda.'%';

if (!$usuarioId) {
	http_response_code(403);
	echo 'Sesión inválida.';
	exit;
}

// Mismo patrón que listar_historial_acuerdos() (includes/functions.php):
// GROUP BY a.id, l.id para colapsar los duplicados de pos_id en
// repositorio_locales_supervisores_cliente (~1,116 filas repetidas por
// distinto supervisor) sin perder las líneas reales de la Acta — acá se
// traen las 4 tablas juntas (sin filtrar por `tipo`), cada una con su propio
// patrón de captura (ver CLAUDE.md, "Patrón de captura por tipo").
$stmt = $mysqli->prepare(
	"SELECT a.documento_no, a.estado, a.mes_inicio, a.mes_fin,
	        d.pos_name, d.cedi, d.tipo_distribuidor, d.canal,
	        l.id AS linea_id, l.tipo, l.segmento, l.sector, l.categoria, l.marca,
	        l.rebate_pct, l.valores_mensuales, l.valor_mensual_unico,
	        l.cantidad_max_percha, l.precio_percha
	 FROM repositorio_acuerdos a
	 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
	 JOIN repositorio_acuerdo_lineas l ON l.acuerdo_id = a.id
	 WHERE a.estado NOT IN ('borrador', 'anulado')
	   AND a.creado_por = ?
	   AND d.pos_name LIKE ?
	   AND (? = -1 OR (a.mes_inicio <= ? AND a.mes_fin >= ?))
	 GROUP BY a.id, l.id
	 ORDER BY d.pos_name, a.documento_no, FIELD(l.tipo, 'meta_compra', 'cabecera', 'ruma', 'percha'), l.categoria, l.marca"
);
if (!$stmt) {
	http_response_code(500);
	echo 'Error preparando la consulta.';
	exit;
}
$stmt->bind_param('isiii', $usuarioId, $like, $mesIdx, $mesIdx, $mesIdx);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$mesesLargos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$etiquetaTipo = ['meta_compra' => 'Meta de Compras', 'cabecera' => 'Cabecera', 'ruma' => 'Ruma', 'percha' => 'Percha'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Actas_Completo_'.date('Y-m-d').'.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [
	'Documento', 'Estado', 'Canal', 'CEDI / Distribuidor', 'Cliente', 'Tipo',
	'Segmento', 'Sector', 'Categoría', 'Marca', 'Mes', 'Valor',
	'Cantidad Percha', 'Precio Percha', 'Rebate %', 'Rebate $',
], ';');

foreach ($filas as $f) {
	$canalTexto = $f['canal'] === 'DISTRIBUIDOR' ? 'Distribuidor' : 'Directa';
	$cediODistribuidor = $f['canal'] === 'DISTRIBUIDOR' ? $f['tipo_distribuidor'] : $f['cedi'];
	$tipo = $f['tipo'];
	$rebatePct = $tipo === 'meta_compra' ? (float) $f['rebate_pct'] : null;

	// meta_compra/cabecera/percha: un valor tipeado por mes (valores_mensuales
	// JSON). ruma: UN solo valor que se repite en todos los meses del período
	// de la Acta (valor_mensual_unico, ver "regla de oro" del schema) — acá se
	// expande igual a "una fila por mes" para que el CSV tenga la misma forma
	// siempre, repitiendo el mismo valor, en vez de una fila especial sin mes.
	if ($tipo === 'ruma') {
		$valoresPorMes = [];
		for ($m = (int) $f['mes_inicio']; $m <= (int) $f['mes_fin']; $m++) {
			$valoresPorMes[$m] = (float) $f['valor_mensual_unico'];
		}
	} else {
		$decodificado = json_decode($f['valores_mensuales'] ?? '{}', true) ?: [];
		$valoresPorMes = [];
		foreach ($decodificado as $mesIdxFila => $valor) {
			$valoresPorMes[(int) $mesIdxFila] = (float) $valor;
		}
		ksort($valoresPorMes, SORT_NUMERIC);
	}

	foreach ($valoresPorMes as $mesIdxFila => $valor) {
		fputcsv($out, [
			$f['documento_no'],
			$f['estado'],
			$canalTexto,
			$cediODistribuidor,
			$f['pos_name'],
			$etiquetaTipo[$tipo] ?? $tipo,
			$tipo === 'percha' ? '' : $f['segmento'],
			$tipo === 'meta_compra' ? $f['sector'] : '',
			$tipo === 'percha' ? '' : $f['categoria'],
			$f['marca'],
			$mesesLargos[$mesIdxFila] ?? $mesIdxFila,
			number_format($valor, 2, '.', ''),
			$tipo === 'percha' ? $f['cantidad_max_percha'] : '',
			$tipo === 'percha' ? number_format((float) $f['precio_percha'], 2, '.', '') : '',
			$rebatePct !== null ? number_format($rebatePct * 100, 2, '.', '') : '',
			$rebatePct !== null ? number_format($valor * $rebatePct, 2, '.', '') : '',
		], ';');
	}
}
fclose($out);
exit;
?>
