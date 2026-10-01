<?php
// Puntos de venta que puede reportar cada usuario, según el canal de su rutero (todo solo lectura).
require_once __DIR__.'/db.php';

const EP_CANALES_PDV = ['RETAIL', 'CANALES'];
// Si el canal menor pesa al menos esto en su rutero, se le muestran ambos canales.
const EP_PDV_MINORIA_MIXTO = 0.2;

// Todas las categorías (canales) de punto de venta activas: Retail, Canales, Oficina, Eventos, Ferias, Bodega...
function ep_todos_los_canales(): array {
	static $todos = null;
	if ($todos === null) {
		$todos = [];
		$db = ep_db();
		$res = $db ? $db->query("SELECT DISTINCT channel FROM repositorio_locales_dtt2 WHERE activar = 'SI' AND channel <> '' ORDER BY channel") : false;
		foreach ($res ?: [] as $f) {
			$todos[] = $f['channel'];
		}
		$todos = $todos ?: EP_CANALES_PDV;
	}
	return $todos;
}

// Categoría de punto de venta configurada para el usuario en sesión (todas, retail, canales); vacío si no la tiene o la columna no existe.
function ep_categoria_pdv_usuario(): string {
	$db = ep_db();
	if (!$db) {
		return '';
	}
	require_once __DIR__.'/aprobacion_datos.php';
	if (!ep_usuarios_tiene_ruta($db)) {
		return '';
	}
	$stmt = $db->prepare('SELECT categorias FROM repositorio_usuarios_reporte WHERE id = ? LIMIT 1');
	$stmt->bind_param('i', $_SESSION['usuario_id']);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return (string) ($fila['categorias'] ?? '');
}

// Canales de PDV que ve el usuario: el gestor, todos; el promotor, los de su categoría configurada (retail = todas menos canales, canales = todas menos retail) o, sin ella, el dominante de su rutero.
function ep_canales_usuario(): array {
	if (ep_es_admin()) {
		return ep_todos_los_canales();
	}
	$categoria = ep_categoria_pdv_usuario();
	// El supervisor sin categoría configurada ve todas; con ella (por ejemplo retail) se aplica igual que a un promotor.
	if (ep_es_supervisor() && $categoria === '') {
		return ep_todos_los_canales();
	}
	if ($categoria === 'todas') {
		return ep_todos_los_canales();
	}
	if ($categoria === 'retail') {
		return array_values(array_diff(ep_todos_los_canales(), ['CANALES']));
	}
	if ($categoria === 'canales') {
		return array_values(array_diff(ep_todos_los_canales(), ['RETAIL']));
	}
	if (isset($_SESSION['pdv_canales']) && is_array($_SESSION['pdv_canales'])) {
		return $_SESSION['pdv_canales'];
	}
	$canales = EP_CANALES_PDV;
	$db = ep_db();
	if ($db) {
		// Tablas base del rutero, no la vista lvi_rutero (agrupa por fecha y tardaba ~3 s; mismo resultado en ~0,2 s).
		$stmt = $db->prepare("SELECT d.channel, COUNT(DISTINCT d.pos_id) AS n FROM rutero_pdv rp JOIN repositorio_usuarios usu ON usu.id = rp.id_usuario JOIN repositorio_locales_dtt2 d ON d.id = rp.id_pdv AND d.activar = 'SI' WHERE usu.user = ? AND rp.status = 1 AND rp.habilitado = 1 AND d.channel IN ('RETAIL', 'CANALES') GROUP BY d.channel");
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
