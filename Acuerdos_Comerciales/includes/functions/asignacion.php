<?php
// Supervisor de campo sin cuenta propia que reporta a otro supervisor que sí tiene cuenta.
function supervisorRealDeJerarquia($mysqli, $supervisorCampo) {
	if (!$supervisorCampo) return null;
	$stmt = $mysqli->prepare(
		'SELECT supervisor_real FROM repositorio_jerarquia_supervisores WHERE UPPER(TRIM(supervisor_campo)) = UPPER(TRIM(?)) LIMIT 1'
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $supervisorCampo);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ? $fila['supervisor_real'] : null;
}

// Dado un pos_id resuelto, encuentra el usuario responsable vía su supervisor real. Null si no hay cuenta activa. $posName (opcional) desempata cuando el pos_id está duplicado en el maestro entre clientes distintos (~1,110 casos confirmados) — sin él, cae en el criterio viejo (primera fila que encuentre).
function usuarioIdDePosId($mysqli, $posId, $posName = null) {
	$nombreComparable = $posName !== null ? repositorio_texto_comparable($posName) : null;
	$condicionNombre = $nombreComparable !== null ? ' AND '.repositorio_sql_comparable('c.pos_name').' = ?' : '';

	$stmt = $mysqli->prepare(
		"SELECT u.id FROM repositorio_locales_supervisores_cliente c
		 JOIN repositorio_usuarios_acuerdos u ON u.supervisor = c.supervisor AND u.status = 'activo'
		 WHERE c.pos_id = ?$condicionNombre LIMIT 1"
	);
	if ($stmt) {
		if ($nombreComparable !== null) $stmt->bind_param('ss', $posId, $nombreComparable);
		else $stmt->bind_param('s', $posId);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($fila) return (int) $fila['id'];
	}

	$stmtSup = $mysqli->prepare("SELECT supervisor FROM repositorio_locales_supervisores_cliente WHERE pos_id = ?$condicionNombre LIMIT 1");
	if (!$stmtSup) return null;
	if ($nombreComparable !== null) $stmtSup->bind_param('ss', $posId, $nombreComparable);
	else $stmtSup->bind_param('s', $posId);
	$stmtSup->execute();
	$filaSup = $stmtSup->get_result()->fetch_assoc();
	$stmtSup->close();
	$real = $filaSup ? supervisorRealDeJerarquia($mysqli, $filaSup['supervisor']) : null;
	if (!$real) return null;

	$stmtReal = $mysqli->prepare("SELECT id FROM repositorio_usuarios_acuerdos WHERE supervisor = ? AND status = 'activo' LIMIT 1");
	if (!$stmtReal) return null;
	$stmtReal->bind_param('s', $real);
	$stmtReal->execute();
	$filaReal = $stmtReal->get_result()->fetch_assoc();
	$stmtReal->close();
	return $filaReal ? (int) $filaReal['id'] : null;
}

// Columna USUARIO del Excel de Cuotas (2026-09-28, pedido explícito): el Excel manda directo, sin adivinar por CEDI/supervisor. Exacto salvo mayúsculas/espacios — un usuario mal tipeado NO cae a otro criterio, se reporta como error (ver resolverNombreAsignadoCuota()).
function resolverUsuarioExacto($mysqli, $usuarioExcel) {
	$usuarioExcel = trim((string) $usuarioExcel);
	if ($usuarioExcel === '') return null;
	$stmt = $mysqli->prepare(
		"SELECT id, usuario FROM repositorio_usuarios_acuerdos WHERE UPPER(TRIM(usuario)) = UPPER(TRIM(?)) AND status = 'activo' LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $usuarioExcel);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ?: null;
}

// Dueño real de una fila de Cuotas: la columna USUARIO del Excel manda si vino tipeada; si no, CEDI del Excel; el maestro de Alicorp es el último respaldo.
function usuarioIdDeCuota($mysqli, $posId, $trimestre, $anio) {
	$posName = null;
	$stmt = $mysqli->prepare(
		"SELECT cedi_excel, cliente_excel, usuario_excel FROM repositorio_cuota_cliente WHERE pos_id = ? AND trimestre = ? AND anio = ? LIMIT 1"
	);
	if (!$stmt) $stmt = $mysqli->prepare(
		"SELECT cedi_excel, cliente_excel FROM repositorio_cuota_cliente WHERE pos_id = ? AND trimestre = ? AND anio = ? LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('sii', $posId, $trimestre, $anio);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		$posName = $fila['cliente_excel'] ?? null;
		$usuarioExacto = resolverUsuarioExacto($mysqli, $fila['usuario_excel'] ?? '');
		if ($usuarioExacto) return (int) $usuarioExacto['id'];
		$cedi = $fila ? trim((string) $fila['cedi_excel']) : '';
		if ($cedi !== '') {
			$stmtCedi = $mysqli->prepare(
				"SELECT id FROM repositorio_usuarios_acuerdos
				 WHERE status = 'activo'
				   AND (UPPER(TRIM(usuario)) = UPPER(TRIM(?)) OR UPPER(TRIM(supervisor)) = UPPER(TRIM(?)))
				 LIMIT 1"
			);
			if ($stmtCedi) {
				$stmtCedi->bind_param('ss', $cedi, $cedi);
				$stmtCedi->execute();
				$filaCedi = $stmtCedi->get_result()->fetch_assoc();
				$stmtCedi->close();
				if ($filaCedi) return (int) $filaCedi['id'];
			}
		}
	}
	return usuarioIdDePosId($mysqli, $posId, $posName);
}

// Mismo criterio de arriba (CEDI del Excel gana, maestro como respaldo), pero ANTES de
// guardar — usada por la previsualización de Cuotas (2026-09-17) para mostrar "Se asigna
// a" antes de confirmar. Recibe $cediExcel directo de la fila recién parseada (todavía no
// existe en repositorio_cuota_cliente, por eso no puede reusar usuarioIdDeCuota() tal
// cual) — es la MISMA fila que, una vez guardada, usuarioIdDeCuota() volvería a leer con
// este mismo cedi_excel, así que el resultado coincide con lo que pasaría de verdad.
// Devuelve ['nombre'=>string|null, 'tiene_cuenta'=>bool] — 2026-09-17, ampliado a pedido
// explícito del usuario: un cliente cuyo `pos_id` SÍ se identificó pero cuyo supervisor
// real (del maestro de Alicorp) todavía no tiene cuenta de usuario NO es lo mismo que un
// cliente que ni siquiera se pudo identificar — antes ambos casos caían en el mismo balde
// "sin identificar", perdiendo la distinción. Mismo criterio que ya usaba el modal
// "Resumen" viejo (`resumen_cuotas()`, sección "Con cuenta"/"Sin cuenta todavía").
function resolverNombreAsignadoCuota($mysqli, $posId, $cediExcel, $posName = null, $usuarioExcel = null) {
	// Columna USUARIO manda directo — si vino tipeada pero no matchea ninguna cuenta activa, ESO es el error a mostrar (no cae a CEDI/maestro, sería tapar el typo).
	if ($usuarioExcel !== null && trim((string) $usuarioExcel) !== '') {
		$usuarioExacto = resolverUsuarioExacto($mysqli, $usuarioExcel);
		if ($usuarioExacto) return ['nombre' => $usuarioExacto['usuario'], 'tiene_cuenta' => true];
		return ['nombre' => trim((string) $usuarioExcel), 'tiene_cuenta' => false];
	}
	$cedi = trim((string) $cediExcel);
	if ($cedi !== '') {
		$stmt = $mysqli->prepare(
			"SELECT usuario FROM repositorio_usuarios_acuerdos
			 WHERE status = 'activo'
			   AND (UPPER(TRIM(usuario)) = UPPER(TRIM(?)) OR UPPER(TRIM(supervisor)) = UPPER(TRIM(?)))
			 LIMIT 1"
		);
		if ($stmt) {
			$stmt->bind_param('ss', $cedi, $cedi);
			$stmt->execute();
			$fila = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			if ($fila) return ['nombre' => $fila['usuario'], 'tiene_cuenta' => true];
		}
	}
	// Respaldo del maestro: trae el supervisor real de ESE pos_id, con o sin cuenta activa
	// (LEFT JOIN, no JOIN — antes un JOIN normal descartaba en silencio el caso "supervisor
	// real pero sin cuenta todavía", indistinguible de "no se encontró nada").
	// $posName (opcional) desempata cuando el pos_id está duplicado en el maestro entre clientes distintos (~1,110 casos confirmados).
	$nombreComparable = $posName !== null ? repositorio_texto_comparable($posName) : null;
	$condicionNombre = $nombreComparable !== null ? ' AND '.repositorio_sql_comparable('c.pos_name').' = ?' : '';
	$stmt = $mysqli->prepare(
		"SELECT c.supervisor, u.usuario, (u.id IS NOT NULL) AS tiene_cuenta
		 FROM repositorio_locales_supervisores_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.supervisor = c.supervisor AND u.status = 'activo'
		 WHERE c.pos_id = ?$condicionNombre LIMIT 1"
	);
	if (!$stmt) return ['nombre' => null, 'tiene_cuenta' => false];
	if ($nombreComparable !== null) $stmt->bind_param('ss', $posId, $nombreComparable);
	else $stmt->bind_param('s', $posId);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$fila) return ['nombre' => null, 'tiene_cuenta' => false];
	if ($fila['tiene_cuenta']) return ['nombre' => $fila['usuario'], 'tiene_cuenta' => true];

	// Jerarquía manual: el supervisor de campo no tiene cuenta propia, pero reporta a alguien que sí.
	$real = supervisorRealDeJerarquia($mysqli, $fila['supervisor']);
	if ($real) {
		$stmtReal = $mysqli->prepare("SELECT usuario FROM repositorio_usuarios_acuerdos WHERE supervisor = ? AND status = 'activo' LIMIT 1");
		if ($stmtReal) {
			$stmtReal->bind_param('s', $real);
			$stmtReal->execute();
			$filaReal = $stmtReal->get_result()->fetch_assoc();
			$stmtReal->close();
			if ($filaReal) return ['nombre' => $filaReal['usuario'], 'tiene_cuenta' => true];
		}
	}

	// Cliente identificado, supervisor real conocido, pero sin cuenta creada todavía.
	return ['nombre' => $fila['supervisor'], 'tiene_cuenta' => false];
}
