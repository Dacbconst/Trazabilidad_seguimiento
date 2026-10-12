<?php
// Años con al menos un Acuerdo real de cualquier usuario, a nivel de todo el equipo.
function listar_anios_disponibles_equipo($mysqli) {
	$anios = [];
	$r = $mysqli->query(
		"SELECT DISTINCT anio FROM repositorio_acuerdos
		 WHERE estado NOT IN ('borrador', 'anulado') ORDER BY anio DESC"
	);
	if ($r) $anios = array_map('intval', array_column($r->fetch_all(MYSQLI_ASSOC), 'anio'));
	return $anios;
}

// Stats globales + array por usuario: el frontend deriva las 4 vistas filtradas sin pedir más al servidor.
function resumen_seguimiento_equipo($mysqli, $trimestre = 0, $anio = 0) {
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	$vacio = ['stats' => ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'vencidas' => 0], 'equipo' => []];

	// Pendientes: cualquier Acta sin firma real y sin vencer. Depender de si HAY un archivo real, no del texto del estado.
	$limite = sqlFechaLimiteFirma('a.fecha_generacion');
	$stmt = $mysqli->prepare(
		"SELECT u.id AS usuario_id, u.usuario AS nombre,
		        COUNT(*) AS total,
		        COUNT(CASE WHEN a.acta_firmada_azure_path IS NOT NULL THEN 1 END) AS firmadas,
		        COUNT(CASE WHEN a.acta_firmada_azure_path IS NULL AND a.estado <> 'vencido' THEN 1 END) AS pendientes,
		        COUNT(CASE WHEN a.estado = 'vencido' THEN 1 END) AS vencidas,
		        MIN(CASE WHEN a.acta_firmada_azure_path IS NULL AND a.estado <> 'vencido'
		                 THEN DATEDIFF($limite, CURDATE()) END) AS dias_mas_proxima
		 FROM repositorio_acuerdos a
		 JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE a.estado NOT IN ('borrador', 'anulado')
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		 GROUP BY u.id, u.usuario
		 ORDER BY total DESC"
	);
	if (!$stmt) return $vacio;
	$stmt->bind_param('iiiii', $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$equipo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$stats = ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'vencidas' => 0];
	foreach ($equipo as &$u) {
		$u['usuario_id']       = (int) $u['usuario_id'];
		$u['total']            = (int) $u['total'];
		$u['firmadas']         = (int) $u['firmadas'];
		$u['pendientes']       = (int) $u['pendientes'];
		$u['vencidas']         = (int) $u['vencidas'];
		$u['dias_mas_proxima'] = $u['dias_mas_proxima'] !== null ? diasHabilesRestantes($u['dias_mas_proxima']) : null;
		// Calculado acá para que el frontend nunca tenga su propia versión divergente.
		$u['iniciales']        = inicialesUsuario($u['nombre']);
		$stats['total']      += $u['total'];
		$stats['firmadas']   += $u['firmadas'];
		$stats['pendientes'] += $u['pendientes'];
		$stats['vencidas']   += $u['vencidas'];
	}
	unset($u);

	return ['stats' => $stats, 'equipo' => $equipo];
}

// $tipo validado con whitelist en el getter. 'pendientes' ordena por urgencia, el resto por fecha de generación.
function listar_actas_equipo_usuario($mysqli, $usuarioId, $trimestre = 0, $anio = 0, $tipo = 'todas') {
	$usuarioId = (int) $usuarioId;
	if (!$usuarioId) return [];

	// Sin esto, un Acta vencida hace rato pero sin visita a Historial seguía como 'generado' con días negativos.
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	switch ($tipo) {
		case 'firmadas':   $condicionEstado = "a.acta_firmada_azure_path IS NOT NULL"; $orden = 'a.fecha_generacion DESC'; break;
		// Sin firma real y sin vencer, sin importar el estado exacto. Excluye borrador/anulado: un borrador no es "pendiente" real.
		case 'pendientes': $condicionEstado = "a.estado NOT IN ('vencido', 'borrador', 'anulado') AND a.acta_firmada_azure_path IS NULL"; $orden = 'dias_restantes ASC'; break;
		case 'vencidas':   $condicionEstado = "a.estado = 'vencido'"; $orden = 'a.fecha_generacion DESC'; break;
		default:           $condicionEstado = "a.estado NOT IN ('borrador', 'anulado')"; $orden = 'a.fecha_generacion DESC';
	}

	// pos_name: SIEMPRE lo que ya se guardó al subir el Excel (Cuotas/Acuerdo Completo) — nunca el maestro, ni como respaldo (pedido explícito).
	$limite = sqlFechaLimiteFirma('a.fecha_generacion');
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.pos_id, a.documento_no, a.fecha_generacion, a.estado,
		        (a.acta_firmada_azure_path IS NOT NULL) AS tiene_firma,
		        a.acta_firmada_subido_en, a.acta_firmada_mime,
		        a.firma_validada_en, a.firma_rechazada_en, a.firma_rechazada_motivo,
		        COALESCE(
		          (SELECT cc.cliente_excel FROM repositorio_cuota_cliente cc WHERE cc.acuerdo_id_generado = a.id LIMIT 1),
		          (SELECT acl.cliente_excel FROM repositorio_acuerdo_completo_linea acl WHERE acl.acuerdo_id_generado = a.id LIMIT 1)
		        ) AS pos_name,
		        DATEDIFF($limite, CURDATE()) AS dias_restantes
		 FROM repositorio_acuerdos a
		 WHERE a.creado_por = ?
		   AND $condicionEstado
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		 ORDER BY $orden"
	);
	if (!$stmt) return [];
	$stmt->bind_param('iiiiii', $usuarioId, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	rellenarClienteDeActas($mysqli, $filas, 'pos_name');
	foreach ($filas as &$f) {
		$f['tiene_firma']    = (bool) $f['tiene_firma'];
		$f['dias_restantes'] = $f['dias_restantes'] !== null ? diasHabilesRestantes($f['dias_restantes']) : null;
	}
	unset($f);
	return $filas;
}
