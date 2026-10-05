<?php
// Descarga en .xlsx los registros filtrados por tipo, fecha y (admin) supervisor/promotor. Primera columna: Supervisor.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

if (!ep_login_check() || !ep_es_gestor()) {
	http_response_code(403);
	echo 'No tienes permiso para esto.';
	exit;
}

require_once __DIR__.'/../includes/registros_datos.php';
require_once __DIR__.'/../includes/actividades_datos.php';
require_once __DIR__.'/../includes/xlsx_motor.php';

$tipo = trim((string) ($_GET['tipo'] ?? ''));
$fechaDesde = trim((string) ($_GET['desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['hasta'] ?? '')) ?: $fechaDesde;
$promotor = trim((string) ($_GET['promotor'] ?? ''));
// El filtro de supervisor solo lo puede elegir el admin; un supervisor ya recibe solo lo suyo por ep_registros_datos().
$supervisorId = ep_es_admin() ? (int) ($_GET['supervisor_id'] ?? 0) : 0;

$registros = ep_registros_datos(5000);
$filtrados = array_values(array_filter($registros, function ($r) use ($tipo, $fechaDesde, $fechaHasta, $promotor, $supervisorId) {
	if ($tipo !== '' && ($r['tipo'] ?? '') !== $tipo) {
		return false;
	}
	$fecha = substr((string) ($r['fecha_actividad'] ?? ($r['fecha_iso'] ?? '')), 0, 10);
	if ($fechaDesde !== '' && $fecha < $fechaDesde) {
		return false;
	}
	if ($fechaHasta !== '' && $fecha > $fechaHasta) {
		return false;
	}
	if ($promotor !== '' && ($r['promotor'] ?? '') !== $promotor) {
		return false;
	}
	if ($supervisorId > 0) {
		$extra = array_map('intval', $r['supervisores_extra'] ?? []);
		if ((int) ($r['supervisor_id'] ?? 0) !== $supervisorId && !in_array($supervisorId, $extra, true)) {
			return false;
		}
	}
	return true;
}));

// Nombre del supervisor por id, para la primera columna del Excel.
$db = ep_db();
$nombresSupervisor = [];
if ($db && $res = $db->query("SELECT id, nombre FROM repositorio_usuarios_reporte WHERE rol = 'supervisor'")) {
	foreach ($res->fetch_all(MYSQLI_ASSOC) as $f) {
		$nombresSupervisor[(int) $f['id']] = $f['nombre'];
	}
}
$nombreSup = function (array $r) use ($nombresSupervisor): string {
	$id = (int) ($r['supervisor_id'] ?? 0);
	return $id > 0 ? ($nombresSupervisor[$id] ?? 'Sin asignar') : 'Sin asignar';
};

// Competencia puede traer varios puntos de venta en un mismo registro; se listan todos separados por coma (no solo el primero).
$listaPuntos = function (array $r, string $campo): string {
	if (empty($r['puntos']) || !is_array($r['puntos'])) {
		return (string) ($r[$campo] ?? '');
	}
	$valores = array_map(fn($p) => trim((string) ($p[$campo] ?? '')), $r['puntos']);
	return implode(', ', array_values(array_filter($valores, fn($v) => $v !== '')));
};

$filasXlsx = array_map(fn($r) => [
	$nombreSup($r),
	$r['actividad_label'] ?? '',
	$r['fecha_actividad'] ?? ($r['fecha_iso'] ?? ''),
	$listaPuntos($r, 'punto_venta'),
	$listaPuntos($r, 'ciudad'),
	$r['promotor'] ?? '',
	$r['estado'] ?? 'Aprobado',
], $filtrados);

// Nombre del archivo: el de la actividad elegida, no "historial" genérico (sin actividad elegida, sí queda genérico).
$nombreActividad = 'Historial';
foreach (ep_logicas() as $l) {
	if ($l['plantilla'] === $tipo) { $nombreActividad = $l['label']; break; }
}
// iconv//TRANSLIT mete apóstrofes raros en palabras con tilde (ej. "fotogr'afico"); mejor mapear las tildes a mano.
$sinTildes = strtr($nombreActividad, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U']);
$slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($sinTildes)), '_');

$archivo = ep_xlsx_generar(['Supervisor', 'Actividad', 'Fecha', 'Punto de venta', 'Ciudad', 'Promotor', 'Estado'], $filasXlsx);
ep_xlsx_descargar($archivo, $slug.'_'.date('Y-m-d').'.xlsx');
