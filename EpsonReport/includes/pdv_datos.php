<?php
// Puntos de venta que puede reportar cada usuario, según el canal de su rutero (todo solo lectura).
require_once __DIR__.'/db.php';

const EP_CANALES_PDV = ['RETAIL', 'CANALES'];
// Si el canal menor pesa al menos esto en su rutero, se le muestran ambos canales.
const EP_PDV_MINORIA_MIXTO = 0.2;

// Canales de PDV que ve el usuario: el admin ve ambos; el promotor, el dominante de su rutero (ambos si es mixto o no tiene puntos).
function ep_canales_usuario(): array {
	if (ep_rol_actual() === 'admin') {
		return EP_CANALES_PDV;
	}
	if (isset($_SESSION['pdv_canales']) && is_array($_SESSION['pdv_canales'])) {
		return $_SESSION['pdv_canales'];
	}
	$canales = EP_CANALES_PDV;
	$db = ep_db();
	if ($db) {
		$stmt = $db->prepare("SELECT d.channel, COUNT(DISTINCT d.pos_id) AS n FROM lvi_rutero r JOIN repositorio_locales_dtt2 d ON d.pos_id = r.pos_id AND d.activar = 'SI' WHERE r.user = ? AND d.channel IN ('RETAIL', 'CANALES') GROUP BY d.channel");
		if ($stmt) {
			$stmt->bind_param('s', $_SESSION['usuario']);
			$stmt->execute();
			$conteo = [];
			foreach ($stmt->get_result() as $f) {
				$conteo[$f['channel']] = (int) $f['n'];
			}
			$stmt->close();
			if (count($conteo) === 1) {
				$canales = array_keys($conteo);
			} elseif (count($conteo) === 2) {
				arsort($conteo);
				$total = array_sum($conteo);
				$menor = min($conteo);
				$canales = ($menor / $total) >= EP_PDV_MINORIA_MIXTO ? EP_CANALES_PDV : [array_key_first($conteo)];
			}
		}
	}
	return $_SESSION['pdv_canales'] = $canales;
}

// Puntos activos de esos canales, listos para el spinner.
function ep_pdv_listar(array $canales): array {
	$db = ep_db();
	if (!$db || !$canales) {
		return [];
	}
	$marcas = implode(',', array_fill(0, count($canales), '?'));
	$stmt = $db->prepare("SELECT pos_id, pos_name, city, channel, customer_owner FROM repositorio_locales_dtt2 WHERE activar = 'SI' AND channel IN ($marcas) ORDER BY pos_name");
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param(str_repeat('s', count($canales)), ...$canales);
	$stmt->execute();
	$puntos = [];
	foreach ($stmt->get_result() as $f) {
		$puntos[] = [
			'pos_id' => $f['pos_id'],
			'nombre' => $f['pos_name'],
			'ciudad' => $f['city'],
			'canal' => $f['channel'],
			'cadena' => $f['customer_owner'] !== '-' ? $f['customer_owner'] : '',
		];
	}
	$stmt->close();
	return $puntos;
}

// Un punto concreto, solo si es activo y de un canal permitido para el usuario (el servidor nunca confía en lo que manda el navegador).
function ep_pdv_obtener(string $posId, array $canales): ?array {
	foreach (ep_pdv_listar($canales) as $p) {
		if ($p['pos_id'] === $posId) {
			return $p;
		}
	}
	return null;
}
