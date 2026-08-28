<?php
// Todo el manejo de sesión y roles vive en un solo archivo a propósito:
// este proyecto es independiente del login de Xplora y no necesita la
// separación Session.php/Auth.php que usan Pintuco/Unilever.

function iniciar_sesion() {
	if (session_status() === PHP_SESSION_NONE) {
		session_set_cookie_params([
			'httponly' => true,
			'secure'   => SECURE,
			'samesite' => 'Lax',
		]);
		session_start();
	}
}

// Decisión explícita: login simple, sin password_hash/password_verify.
// El prepared statement solo protege la consulta contra inyección SQL,
// la contraseña se compara tal cual está guardada en la tabla.
//
// Devuelve TRUE (login ok), FALSE (usuario/contraseña incorrectos), o el
// string 'bloqueado' (demasiados intentos fallidos seguidos, cuenta
// bloqueada temporalmente) — procesar_acceso.php distingue los 3 casos
// para mostrar el mensaje correcto en login.php.
//
// Fuerza bruta (2026-08-24, pedido explícito tras revisión de seguridad):
// 5 intentos fallidos seguidos bloquean la cuenta 15 minutos
// (`intentos_fallidos`/`bloqueado_hasta`, columnas nuevas — ver CLAUDE.md).
// Si todavía no se corrió ese ALTER, cae al login de siempre SIN bloqueo
// (mismo criterio de fallback que ya usaba esta función para `supervisor`)
// — nunca se rompe el acceso mientras el usuario corre el SQL.
function login($usuario, $password, $mysqli) {
	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, contrasena, intentos_fallidos, bloqueado_hasta
		 FROM repositorio_usuarios_acuerdos WHERE usuario = ? AND status = 'activo' LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('s', $usuario);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if (!$row) {
			return false;
		}

		if ($row['bloqueado_hasta'] !== null && strtotime($row['bloqueado_hasta']) > time()) {
			return 'bloqueado';
		}

		if ($row['contrasena'] !== $password) {
			$intentos = (int) $row['intentos_fallidos'] + 1;
			if ($intentos >= 5) {
				$stmtUpd = $mysqli->prepare(
					'UPDATE repositorio_usuarios_acuerdos SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?'
				);
				$stmtUpd->bind_param('i', $row['id']);
				$stmtUpd->execute();
				$stmtUpd->close();
				return 'bloqueado';
			}
			$stmtUpd = $mysqli->prepare('UPDATE repositorio_usuarios_acuerdos SET intentos_fallidos = ? WHERE id = ?');
			$stmtUpd->bind_param('ii', $intentos, $row['id']);
			$stmtUpd->execute();
			$stmtUpd->close();
			return false;
		}

		// Contraseña correcta: si venía con intentos fallidos previos, se
		// resetea el contador — un login exitoso limpia la pizarra.
		if ((int) $row['intentos_fallidos'] > 0) {
			$stmtReset = $mysqli->prepare('UPDATE repositorio_usuarios_acuerdos SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
			$stmtReset->bind_param('i', $row['id']);
			$stmtReset->execute();
			$stmtReset->close();
		}

		session_regenerate_id();
		$_SESSION['user_id']    = $row['id'];
		$_SESSION['username']   = $row['usuario'];
		$_SESSION['rol']        = $row['rol'];
		$_SESSION['supervisor'] = $row['supervisor'] ?? null;
		return true;
	}

	// ---------- Fallback: columnas de fuerza bruta todavía no existen ----------
	// Mismo login de siempre, sin bloqueo — y dentro de este fallback, el
	// fallback ORIGINAL para `supervisor` (por si tampoco existe esa).
	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor FROM repositorio_usuarios_acuerdos
		 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
	);
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, usuario, rol FROM repositorio_usuarios_acuerdos
			 WHERE usuario = ? AND contrasena = ? AND status = 'activo' LIMIT 1"
		);
	}
	$stmt->bind_param('ss', $usuario, $password);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$row) {
		return false;
	}

	session_regenerate_id();
	$_SESSION['user_id']    = $row['id'];
	$_SESSION['username']   = $row['usuario'];
	$_SESSION['rol']        = $row['rol'];
	$_SESSION['supervisor'] = $row['supervisor'] ?? null;
	return true;
}

function login_check() {
	return isset($_SESSION['user_id'], $_SESSION['rol']);
}

// El acceso por módulo NO es una jerarquía (admin tiene menos acceso que
// desarrollador: no ve Historial). Cada sección define su lista explícita de
// roles permitidos en includes/secciones.php y se valida por pertenencia simple.
function rolPermitido(array $rolesPermitidos) {
	return isset($_SESSION['rol']) && in_array($_SESSION['rol'], $rolesPermitidos, true);
}

function rolEtiqueta($rol) {
	$etiquetas = [
		'desarrollador'      => 'Desarrollador',
		'superdesarrollador' => 'Superdesarrollador',
	];
	return isset($etiquetas[$rol]) ? $etiquetas[$rol] : $rol;
}

// ---------- Canal (Directo / Distribuidor) vía supervisor ----------
// repositorio_locales_supervisores_cliente es un maestro externo de Alicorp
// (NO se modifica su esquema, solo se consulta) con una columna `canal` que
// solo puede ser DISTRIBUIDOR/COBERTURA/MAYORISTA/AUTOSERVICIO, y una columna
// `supervisor` que ES la lista real de personas que usan la plataforma
// (confirmado por el cliente en llamada, 2026-07-26). El canal de un usuario
// de Acuerdos_Comerciales NUNCA se guarda: se deriva en vivo mirando qué
// canal(es) tienen los clientes de SU supervisor, cada vez que hace falta.

function listar_supervisores_disponibles($mysqli) {
	$supervisores = [];
	$res = $mysqli->query(
		"SELECT DISTINCT supervisor FROM repositorio_locales_supervisores_cliente
		 WHERE supervisor IS NOT NULL AND supervisor <> '' ORDER BY supervisor"
	);
	// $res puede venir en false si la tabla no existe/no es accesible — no
	// asumir que siempre hay resultado (esto fue justamente lo que rompió el
	// login el 2026-07-26: un nombre de tabla mal escrito hizo que las
	// consultas a este maestro fallaran silenciosamente en cascada).
	if (!$res) return $supervisores;
	while ($row = $res->fetch_assoc()) {
		$supervisores[] = $row['supervisor'];
	}
	return $supervisores;
}

// Un supervisor = la persona real que usa la cuenta, así que no tiene sentido
// que dos logins compartan el mismo supervisor (ver canalDeSupervisor()) —
// esto arma el mapa [supervisor => usuario] de quién ya lo tiene, para que
// gestion-usuarios.php pueda ocultarlo del combo "Nuevo Usuario" y
// crear_usuario.php/actualizar_usuario.php lo puedan rechazar si igual llega
// por API directa. Solo cuenta usuarios 'activo': si se desactiva una cuenta,
// su supervisor queda libre para reasignar. $excluirId permite que, al editar
// un usuario, su propio supervisor actual no cuente como "ya tomado por otro".
function supervisores_asignados_activos($mysqli, $excluirId = 0) {
	$asignados = [];
	$stmt = $mysqli->prepare(
		"SELECT usuario, supervisor FROM repositorio_usuarios_acuerdos
		 WHERE supervisor IS NOT NULL AND supervisor <> '' AND status = 'activo' AND id <> ?"
	);
	if (!$stmt) return $asignados;
	$stmt->bind_param('i', $excluirId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	foreach ($filas as $f) {
		$asignados[$f['supervisor']] = $f['usuario'];
	}
	return $asignados;
}

// Ningún supervisor real mezcla DISTRIBUIDOR con COBERTURA/MAYORISTA (ver
// investigación de datos) — por eso alcanza con mirar si DISTRIBUIDOR está
// presente. Caso borde sin resolver: un supervisor exclusivamente MAYORISTA
// (no existe hoy en la data) caería como 'directo' por este orden de checks.
//
// Esta función se llama SIN CONDICIÓN en cada carga de Registrar (el tab por
// defecto tras el login) — por eso nunca debe poder tirar un fatal error acá,
// pase lo que pase con la tabla externa: devuelve null (-> 'directo' por
// defecto donde se usa) en vez de romper el login para todo el mundo.
function canalDeSupervisor($mysqli, $supervisor) {
	if (!$supervisor) return null;
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT canal FROM repositorio_locales_supervisores_cliente WHERE supervisor = ?"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $supervisor);
	$stmt->execute();
	$canales = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'canal');
	$stmt->close();

	if (in_array('DISTRIBUIDOR', $canales, true)) return 'distribuidor';
	if ($canales) return 'directo';
	return null;
}

// ---------- Repositorio de Cuotas trimestrales (2026-08-25) ----------
// Mismo criterio de matching ya probado en Liquidación
// (liquidacion_candidatos_pos_id(), includes/liquidacion_import.php): match
// primario por nombre (pos_name LIKE 'cliente%'), desempate por CEDI del
// Excel = supervisor (canal directa, único canal visto hasta ahora en los
// Excel de Cuotas — si algún día llega un Excel de canal Distribuidor,
// ajustar el campo de desempate a tipo_distribuidor igual que Liquidación).
// A diferencia de Liquidación (que puede dejar varios candidatos para que
// el usuario elija a mano en "Pendientes de Asignar" viendo más contexto),
// acá se necesita UN solo pos_id para poder guardar la fila — 0 o más de 1
// candidato después del desempate se resuelve como "sin match" (pos_id
// NULL, estado pendiente_match en el repositorio).
function resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel) {
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%')"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $clienteExcel);
	$stmt->execute();
	$posIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
	$stmt->close();

	if (count($posIds) === 1) return $posIds[0];
	if (count($posIds) === 0 || !$cediExcel) return null;

	$stmt = $mysqli->prepare(
		"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%') AND supervisor = ?"
	);
	if (!$stmt) return null;
	$stmt->bind_param('ss', $clienteExcel, $cediExcel);
	$stmt->execute();
	$desempatados = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
	$stmt->close();

	return count($desempatados) === 1 ? $desempatados[0] : null;
}

// Corrige el texto de "CATEGORIAS" del Excel de Cuotas contra el catálogo
// real de productos (2026-08-25, hallazgo real probando con datos reales):
// "POLVO DETERGENTE" no existe como Sector — es Sector "POLVO" + Subcategoría
// "DETERGENTE" pegados en el mismo texto (confirmado: sector=POLVO tiene una
// única categoria real, "DETERGENTE"). Se busca en 2 pasos:
//   1. ¿El texto matchea directo con un Sector real? -> se usa tal cual.
//   2. ¿Es "Sector Subcategoría" pegados (CONCAT(sector,' ',categoria) exacto)
//      contra una única combinación real? -> se separa, se usa solo el Sector.
// Si ninguno de los dos matchea (ej. "OTRAS CATEGORIAS" — hay 3 Subcategorías
// reales bajo sector=OTROS, ninguna "encaja" en el texto, genuinamente
// ambiguo), se devuelve null — el valor crudo se guarda igual (nunca se
// inventa un Sector), pero queda para que cuotas_guardar.php avise.
function resolverSectorReal($mysqli, $sectorCrudo) {
	$stmt = $mysqli->prepare(
		"SELECT 1 FROM repositorio_productos WHERE fabricante = 'JABONERIA WILSON' AND sector = ? AND activar = 'SI' LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('s', $sectorCrudo);
		$stmt->execute();
		$existe = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($existe) return $sectorCrudo;
	}

	$stmt = $mysqli->prepare(
		"SELECT DISTINCT sector FROM repositorio_productos
		 WHERE fabricante = 'JABONERIA WILSON' AND activar = 'SI' AND CONCAT(sector, ' ', categoria) = ?"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $sectorCrudo);
	$stmt->execute();
	$sectores = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'sector');
	$stmt->close();

	return count($sectores) === 1 ? $sectores[0] : null;
}

// Busca un Rebate% en repositorio_rebate_producto tolerando el mismo tipo de
// desajuste de nombres que resolverSectorReal() (2026-08-27, bug real
// encontrado probando con datos reales): el Excel de JW guarda "LIQUIDOS"
// (plural) y "DETERGENTE" para EL MACHO, pero el cascade real de Registrar
// (repositorio_productos) ofrece "LIQUIDO" (singular) y "ROPA" — una
// búsqueda exacta contra lo que el asesor elige de verdad NUNCA matchea esas
// filas (confirmado: ~32 de 55 filas reales, todo el bloque LIQUIDO, más
// BARRA+EL MACHO). En vez de re-guardar los datos ya subidos, se prueban
// variantes ACÁ, en el momento de buscar — no se toca
// repositorio_rebate_producto ni repositorio_productos.
function buscarRebateProducto($mysqli, $ciudad, $canal, $sector, $categoria, $marca) {
	$stmtBase = $mysqli->prepare(
		"SELECT rebate_pct FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL
		   AND UPPER(TRIM(ciudad)) = UPPER(TRIM(?)) AND UPPER(TRIM(canal)) = UPPER(TRIM(?))
		   AND UPPER(TRIM(sector)) = UPPER(TRIM(?)) AND UPPER(TRIM(categoria)) = UPPER(TRIM(?))
		   AND UPPER(TRIM(marca)) = UPPER(TRIM(?))
		 LIMIT 1"
	);
	if (!$stmtBase) return null;

	$intentar = function ($sectorProbar, $categoriaProbar) use ($mysqli, $stmtBase, $ciudad, $canal, $marca) {
		$stmtBase->bind_param('sssss', $ciudad, $canal, $sectorProbar, $categoriaProbar, $marca);
		$stmtBase->execute();
		$fila = $stmtBase->get_result()->fetch_assoc();
		return $fila ? (float) $fila['rebate_pct'] : null;
	};

	// Variantes de plural/singular (agregar o quitar una "S" final) — mismo
	// criterio que resolverSectorReal(), esta vez sobre Sector Y Categoría.
	$variantesTexto = function ($texto) {
		$variantes = [$texto];
		if (substr($texto, -1) === 'S') $variantes[] = substr($texto, 0, -1);
		else $variantes[] = $texto.'S';
		return $variantes;
	};

	foreach ($variantesTexto($sector) as $sectorProbar) {
		foreach ($variantesTexto($categoria) as $categoriaProbar) {
			$valor = $intentar($sectorProbar, $categoriaProbar);
			if ($valor !== null) { $stmtBase->close(); return $valor; }
		}
	}
	$stmtBase->close();

	// Última opción: ¿Ciudad+Canal+Sector+Marca (ignorando Categoría, que es
	// el campo que más varía de nombre entre JW y el catálogo real, ver caso
	// EL MACHO/ROPA) determinan una única fila? Si hay más de una, no se
	// adivina — se devuelve null como cualquier "sin match" real.
	$stmt = $mysqli->prepare(
		"SELECT rebate_pct FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL
		   AND UPPER(TRIM(ciudad)) = UPPER(TRIM(?)) AND UPPER(TRIM(canal)) = UPPER(TRIM(?))
		   AND UPPER(TRIM(sector)) = UPPER(TRIM(?)) AND UPPER(TRIM(marca)) = UPPER(TRIM(?))"
	);
	if (!$stmt) return null;
	$stmt->bind_param('ssss', $ciudad, $canal, $sector, $marca);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return count($filas) === 1 ? (float) $filas[0]['rebate_pct'] : null;
}

// Dado un pos_id ya resuelto, encuentra qué usuario de la app (cuenta
// 'activo') es su responsable — vía el mismo supervisor real del cliente
// (ver supervisores_asignados_activos()). Null si el cliente no tiene
// supervisor asignado en el maestro, o si ese supervisor todavía no tiene
// una cuenta activa creada en Gestión de Usuarios (caso real: JW asigna
// supervisores en su base antes de que Diego cree el usuario acá).
function usuarioIdDePosId($mysqli, $posId) {
	$stmt = $mysqli->prepare(
		"SELECT u.id FROM repositorio_locales_supervisores_cliente c
		 JOIN repositorio_usuarios_acuerdos u ON u.supervisor = c.supervisor AND u.status = 'activo'
		 WHERE c.pos_id = ? LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('s', $posId);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $fila ? (int) $fila['id'] : null;
}

// Dueño real de UNA fila de Cuotas — CEDI del Excel manda SIEMPRE sobre el
// maestro de Alicorp (2026-08-28, pedido explícito del usuario, con
// evidencia real: el cliente EPV3329/"YUCAILLA PADILLA RENE WILFRIDO"
// aparece con venta REAL bajo JAVIER MALDONADO en el Excel real de
// Liquidación de JW — datos/LIQUIDACION ACUERDOS COMERCIALES Q2 DIRECTA
// 2026.xlsx —, pero el maestro `repositorio_locales_supervisores_cliente`
// dice `supervisor=FRANKLIN SALCEDO, canal=MAYORISTA` para ese mismo
// pos_id — un choque real entre las 2 fuentes, no un bug de lectura. El
// usuario decidió: para el caso puntual de Actas Precargadas/Asignadas
// (este repositorio), confiar en el CEDI que trae el Excel de JW — es el
// documento de negocio real que ya usan para pagos, más confiable acá que
// el campo `supervisor` del maestro de Alicorp (que puede representar otra
// cosa, ej. ruta de reparto, o estar desactualizado). **Alcance acotado a
// propósito**: NO se tocó `usuarioIdDePosId()` (arriba, sigue siendo
// puramente maestro) ni `canalDeSupervisor()`/el resto del proyecto — esta
// función es SOLO para resolver a quién le corresponde una Acta Precargada
// de Cuotas. Si el CEDI no matchea ningún usuario activo real (typo, o el
// supervisor todavía no tiene cuenta creada), cae al maestro como
// respaldo — nunca deja sin dueño una fila que el maestro sí puede resolver.
function usuarioIdDeCuota($mysqli, $posId, $trimestre, $anio) {
	$stmt = $mysqli->prepare(
		"SELECT cedi_excel FROM repositorio_cuota_cliente WHERE pos_id = ? AND trimestre = ? AND anio = ? LIMIT 1"
	);
	if ($stmt) {
		$stmt->bind_param('sii', $posId, $trimestre, $anio);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
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
	return usuarioIdDePosId($mysqli, $posId);
}

// ---------- Actas Precargadas (Fase 2 del Repositorio de Cuotas, 2026-08-25) ----------
// Resolución EN VIVO, nunca guardada — a propósito (ver CLAUDE.md, "Repositorio
// de Cuotas trimestrales + Actas precargadas"): si a un supervisor le crean la
// cuenta de usuario DESPUÉS de que sus cuotas ya estaban subidas, las ve solas
// en la próxima consulta (cada 5 min, cuando la campanita de alertas hace
// polling), sin que nadie tenga que resubir el Excel. Agrupa por
// (pos_id, trimestre, anio) — varias filas de sector de un mismo cliente son
// UNA sola Acta precargada, no una por sector.
// Mismo criterio "CEDI del Excel gana" que usuarioIdDeCuota() (arriba,
// 2026-08-28) — acá como JOIN porque se necesita para TODAS las filas
// pendientes a la vez, no una consulta puntual. `u_cedi` matchea directo
// contra `c.cedi_excel`; si no hay match real ahí, `u_master` cae al
// mismo camino de siempre (maestro de Alicorp) — `COALESCE` se queda con
// el primero que exista.
function listar_actas_precargadas_pendientes($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	$stmt = $mysqli->prepare(
		"SELECT c.pos_id, c.cliente_excel, c.trimestre, c.anio, c.valores_mensuales, c.updated_at
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_locales_supervisores_cliente m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.estado = 'pendiente_uso' AND COALESCE(u_cedi.id, u_master.id) = ?
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filasCrudas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// Se cuenta en PHP, no en SQL (2026-08-26, corregido: el contador decía
	// "5 categorías" pero la Acta armaba 4 — categorías en $0 se descartan
	// en obtener_precarga_detalle(), este conteo tiene que coincidir exacto
	// con lo que el asesor va a ver de verdad, sumar JSON en SQL directo es
	// más frágil que traer las filas y sumar acá).
	// `actualizado_en` = el `updated_at` MÁS RECIENTE entre las filas del
	// grupo (2026-08-27, bug real reportado por el usuario: subió Cuotas
	// para un cliente, se movió entre módulos, y la campanita NO marcó la
	// Acta como nueva/sin ver). Causa real: el "visto" de la campanita
	// (assets/js/alertas-firma.js) se guarda en localStorage con clave
	// `pos_id+trimestre+año` únicamente — si ESE mismo cliente+trimestre ya
	// se había visto alguna vez antes (confirmado con datos reales: este
	// cliente puntual ya se usó en una prueba de otra sesión, un día antes),
	// el navegador lo recuerda como "visto" para siempre, aunque las filas
	// de la base sean completamente nuevas. Se manda `actualizado_en` para
	// que la clave de "visto" en JS incluya esta marca de tiempo — si el
	// cliente se resube/reasigna, la clave cambia y vuelve a marcarse como
	// no visto, sin perder el criterio de "ya lo vi" para lo que de verdad
	// no cambió desde la última vez.
	$grupos = [];
	foreach ($filasCrudas as $f) {
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		if (!is_array($valores) || array_sum($valores) <= 0) continue;
		$clave = $f['pos_id'].'|'.$f['trimestre'].'|'.$f['anio'];
		if (!isset($grupos[$clave])) {
			$grupos[$clave] = ['pos_id' => $f['pos_id'], 'cliente_excel' => $f['cliente_excel'], 'trimestre' => $f['trimestre'], 'anio' => $f['anio'], 'categorias' => 0, 'actualizado_en' => $f['updated_at']];
		}
		$grupos[$clave]['categorias']++;
		if ($f['updated_at'] > $grupos[$clave]['actualizado_en']) $grupos[$clave]['actualizado_en'] = $f['updated_at'];
	}
	return array_values($grupos);
}

// Arma el detalle de una Acta precargada para poblar Registrar — misma forma
// que obtener_acuerdo_detalle() pero sin id/documento_no/estado (todavía no
// existe el Acuerdo real, se crea recién cuando el asesor guarda). Cada fila
// de repositorio_cuota_cliente (una por sector) se convierte en una línea de
// Meta de Compras:
//   - Segmento/Subcategoría/Marca: se reusa la línea de Meta de Compras MÁS
//     RECIENTE de ese mismo pos_id+sector en cualquier Acta anterior
//     (continuidad real del cliente) — si no hay historial, Categoría queda
//     vacía y Subcategoría/Marca también, para que el asesor los complete a
//     mano con el combo normal (guardar_acuerdo.php ya descarta filas
//     incompletas, no hace falta validación nueva acá).
//   - valores_mensuales: viene fijo del repositorio, se marca `bloqueado` para
//     que registrar.js deje esos inputs de mes en readonly.
function obtener_precarga_detalle($mysqli, $posId, $trimestre, $anio) {
	$stmt = $mysqli->prepare(
		"SELECT id, sector, valores_mensuales FROM repositorio_cuota_cliente
		 WHERE pos_id = ? AND trimestre = ? AND anio = ? AND estado = 'pendiente_uso'
		 ORDER BY sector"
	);
	if (!$stmt) return null;
	$stmt->bind_param('sii', $posId, $trimestre, $anio);
	$stmt->execute();
	$filasCuota = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	if (!$filasCuota) return null;

	$stmtCliente = $mysqli->prepare(
		"SELECT pos_name, cedi, canal, tipo_distribuidor FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? LIMIT 1"
	);
	$stmtCliente->bind_param('s', $posId);
	$stmtCliente->execute();
	$cliente = $stmtCliente->get_result()->fetch_assoc();
	$stmtCliente->close();
	if (!$cliente) return null;

	$stmtHistorial = $mysqli->prepare(
		"SELECT l.segmento, l.categoria, l.marca
		 FROM repositorio_acuerdo_lineas l
		 JOIN repositorio_acuerdos a ON a.id = l.acuerdo_id
		 WHERE a.pos_id = ? AND l.tipo = 'meta_compra' AND l.sector = ?
		 ORDER BY a.created_at DESC LIMIT 1"
	);
	$stmtSegmentoPorSector = $mysqli->prepare(
		"SELECT DISTINCT segmento FROM repositorio_productos
		 WHERE fabricante = 'JABONERIA WILSON' AND sector = ? AND activar = 'SI'"
	);

	$lineasMeta = [];
	foreach ($filasCuota as $fc) {
		$segmento = null; $categoria = null; $marca = null;
		if ($stmtHistorial) {
			$stmtHistorial->bind_param('ss', $posId, $fc['sector']);
			$stmtHistorial->execute();
			$prev = $stmtHistorial->get_result()->fetch_assoc();
			if ($prev) { $segmento = $prev['segmento']; $categoria = $prev['categoria']; $marca = $prev['marca']; }
		}
		if ($segmento === null && $stmtSegmentoPorSector) {
			$stmtSegmentoPorSector->bind_param('s', $fc['sector']);
			$stmtSegmentoPorSector->execute();
			$segmentos = array_column($stmtSegmentoPorSector->get_result()->fetch_all(MYSQLI_ASSOC), 'segmento');
			if (count($segmentos) === 1) $segmento = $segmentos[0];
		}
		$valores = $fc['valores_mensuales'] !== null ? json_decode($fc['valores_mensuales'], true) : [];
		$valores = is_array($valores) ? $valores : [];
		// Categoría con $0 en los 3 meses (2026-08-25, pedido explícito: "llegó
		// vacía, ni para qué incluirla") — no tiene sentido meterla en la
		// Acta: como Meta de Compras ya no deja eliminar filas de una
		// precarga, una fila en $0 quedaría atrapada ahí para siempre sin
		// poder sacarla. Se descarta acá, antes de armar la línea, en vez de
		// dejar que llegue al formulario.
		if (array_sum($valores) <= 0) continue;
		$lineasMeta[] = [
			'cuota_id'          => (int) $fc['id'],
			'segmento'          => $segmento,
			'sector'            => $fc['sector'],
			'categoria'         => $categoria,
			'marca'             => $marca,
			'rebate_pct'        => 0,
			'valores_mensuales' => $valores,
			'bloqueado'         => true,
		];
	}
	if ($stmtHistorial) $stmtHistorial->close();
	if ($stmtSegmentoPorSector) $stmtSegmentoPorSector->close();

	$mesInicio = ($trimestre - 1) * 3;

	return [
		'pos_id'          => $posId,
		'distribuidor'    => $cliente['pos_name'],
		'localidad'       => $cliente['cedi'] ?: '—',
		'anio'            => (int) $anio,
		'mes_inicio'      => $mesInicio,
		'mes_fin'         => $mesInicio + 2,
		'es_distribuidor' => ($cliente['canal'] ?? null) === 'DISTRIBUIDOR',
		'empresa_distribuidora' => $cliente['tipo_distribuidor'] ?: '',
		'lineas'          => ['meta_compra' => $lineasMeta, 'cabecera' => [], 'ruma' => [], 'percha' => []],
	];
}

// Resumen para el superdesarrollador (2026-08-25, pedido explícito: "¿a
// quién le estoy mandando qué?") — 4 números de panorama general +
// desglose por usuario para el gráfico. "Actas" acá siempre significa un
// grupo (pos_id, trimestre, anio), nunca una fila suelta de sector — el
// mismo criterio de agrupación que listar_actas_precargadas_pendientes().
function resumen_cuotas($mysqli) {
	$agrupador = "CONCAT(c.pos_id, '|', c.trimestre, '|', c.anio)";

	$pendientes = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT $agrupador) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'pendiente_uso'");
	if ($r) $pendientes = (int) $r->fetch_assoc()['n'];

	$usadas = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT $agrupador) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'usada'");
	if ($r) $usadas = (int) $r->fetch_assoc()['n'];

	$pendientesMatch = 0;
	$r = $mysqli->query("SELECT COUNT(DISTINCT c.cliente_excel, c.trimestre, c.anio) AS n FROM repositorio_cuota_cliente c WHERE c.estado = 'pendiente_match'");
	if ($r) $pendientesMatch = (int) $r->fetch_assoc()['n'];

	// Lista ÚNICA (2026-08-26, reemplaza el número suelto "Sin usuario
	// asignado" — el usuario lo encontró confuso sin poder ver A QUIÉN
	// correspondía) — usuarios reales CON cuenta activa, más los
	// supervisores del maestro que tienen cuotas pendientes pero todavía no
	// tienen una cuenta creada en Gestión de Usuarios (`tiene_cuenta: false`,
	// el frontend los muestra con una marca pasiva en vez de ocultarlos).
	// Mismo criterio "CEDI del Excel gana" (2026-08-28, ver usuarioIdDeCuota()
	// más arriba) — `dueno_id`/`dueno_nombre` se resuelven con CEDI primero,
	// maestro de Alicorp como respaldo si el CEDI no matchea ningún usuario
	// real. Sin esto, este resumen podía mostrar un nombre distinto al que
	// realmente le llega la Acta por la campanita (listar_actas_precargadas_pendientes()) —
	// las 2 consultas ahora usan la misma lógica de dueño.
	$stmt = $mysqli->prepare(
		"SELECT COALESCE(u_cedi.usuario, u_master.usuario) AS nombre,
		        COUNT(DISTINCT $agrupador) AS actas_pendientes,
		        (COALESCE(u_cedi.id, u_master.id) IS NOT NULL) AS tiene_cuenta
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_locales_supervisores_cliente m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.estado = 'pendiente_uso' AND COALESCE(u_cedi.id, u_master.id) IS NOT NULL
		 GROUP BY COALESCE(u_cedi.id, u_master.id), nombre
		 UNION ALL
		 SELECT COALESCE(m.supervisor, c.cedi_excel) AS nombre, COUNT(DISTINCT $agrupador) AS actas_pendientes, 0 AS tiene_cuenta
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u_cedi
		   ON u_cedi.status = 'activo'
		  AND (UPPER(TRIM(u_cedi.usuario)) = UPPER(TRIM(c.cedi_excel)) OR UPPER(TRIM(u_cedi.supervisor)) = UPPER(TRIM(c.cedi_excel)))
		 LEFT JOIN repositorio_locales_supervisores_cliente m ON m.pos_id = c.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u_master ON u_master.supervisor = m.supervisor AND u_master.status = 'activo'
		 WHERE c.estado = 'pendiente_uso' AND u_cedi.id IS NULL AND u_master.id IS NULL
		 GROUP BY COALESCE(m.supervisor, c.cedi_excel)
		 ORDER BY actas_pendientes DESC"
	);
	$porUsuario = [];
	if ($stmt) {
		$stmt->execute();
		$porUsuario = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
		foreach ($porUsuario as &$fila) { $fila['tiene_cuenta'] = (bool) $fila['tiene_cuenta']; }
		unset($fila);
	}

	// Actas precargadas que YA NO se van a poder generar — el Local ya tiene
	// un Acuerdo activo en el mismo Período (misma regla de
	// getters/guardar_acuerdo.php, "solo un Acta activa por Local+Período",
	// 2026-08-23). Sin este aviso el superdesarrollador solo se enteraba
	// cuando el asesor intentaba generar y el guardado se rechazaba en
	// silencio para él — acá se detecta ANTES, con los mismos datos que
	// hacen falta para decidir qué hacer (2026-08-28, pedido explícito:
	// "que se vea como un cuadro comparativo" en el modal de Resumen).
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT c.pos_id, c.trimestre, c.anio FROM repositorio_cuota_cliente c WHERE c.estado = 'pendiente_uso'"
	);
	$grupos = [];
	if ($stmt) {
		$stmt->execute();
		$grupos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
	}
	$chocan = [];
	if ($grupos) {
		$stmtExistente = $mysqli->prepare(
			"SELECT a.documento_no, a.created_at, u.usuario
			 FROM repositorio_acuerdos a
			 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
			 WHERE a.pos_id = ? AND a.anio = ? AND a.mes_inicio = ? AND a.mes_fin = ?
			   AND a.estado NOT IN ('borrador', 'anulado')
			 LIMIT 1"
		);
		$stmtCliente = $mysqli->prepare(
			"SELECT pos_name FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? LIMIT 1"
		);
		foreach ($grupos as $g) {
			$mesInicio = ($g['trimestre'] - 1) * 3;
			$mesFin = $mesInicio + 2;
			$stmtExistente->bind_param('siii', $g['pos_id'], $g['anio'], $mesInicio, $mesFin);
			$stmtExistente->execute();
			$existente = $stmtExistente->get_result()->fetch_assoc();
			if (!$existente) continue;

			$posName = $g['pos_id'];
			if ($stmtCliente) {
				$stmtCliente->bind_param('s', $g['pos_id']);
				$stmtCliente->execute();
				$fc = $stmtCliente->get_result()->fetch_assoc();
				if ($fc && $fc['pos_name']) $posName = $fc['pos_name'];
			}
			$duenoId = usuarioIdDeCuota($mysqli, $g['pos_id'], $g['trimestre'], $g['anio']);
			$duenoNombre = null;
			if ($duenoId) {
				$ru = $mysqli->query('SELECT usuario FROM repositorio_usuarios_acuerdos WHERE id = '.(int) $duenoId);
				$fu = $ru ? $ru->fetch_assoc() : null;
				$duenoNombre = $fu ? $fu['usuario'] : null;
			}

			$chocan[] = [
				'pos_id'             => $g['pos_id'],
				'local'              => $posName,
				'trimestre'          => (int) $g['trimestre'],
				'anio'               => (int) $g['anio'],
				'asignado_a'         => $duenoNombre,
				'existente_documento_no' => $existente['documento_no'],
				'existente_usuario'  => $existente['usuario'],
				'existente_fecha'    => $existente['created_at'],
			];
		}
		$stmtExistente->close();
		if ($stmtCliente) $stmtCliente->close();
	}

	return [
		'pendientes'        => $pendientes,
		'usadas'            => $usadas,
		'pendientes_match'  => $pendientesMatch,
		'por_usuario'       => $porUsuario,
		'chocan'            => $chocan,
	];
}

// ---------- Gestión de Usuarios (repositorio_usuarios_acuerdos) ----------
// Centralizado aquí porque tanto la carga inicial (gestion-usuarios.php) como
// el refresco por AJAX (getters/tabla_usuarios.php) necesitan la misma consulta
// y el mismo render de fila, para no duplicar el SQL ni el HTML.

function listar_usuarios_acuerdos($mysqli, $busqueda = '', $pagina = 1, $porPagina = 8) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_usuarios_acuerdos WHERE usuario LIKE ?"
	);
	$stmtTotal->bind_param('s', $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) {
		$pagina = $totalPaginas;
		$offset = ($pagina - 1) * $porPagina;
	}

	$stmt = $mysqli->prepare(
		"SELECT id, usuario, rol, supervisor, status, created_at FROM repositorio_usuarios_acuerdos
		 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
	);
	// Mismo fallback que login() — si `supervisor` todavía no existe en la
	// base, no reventar Gestión de Usuarios con un fatal error.
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT id, usuario, rol, NULL AS supervisor, status, created_at FROM repositorio_usuarios_acuerdos
			 WHERE usuario LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
		);
	}
	$stmt->bind_param('sii', $like, $porPagina, $offset);
	$stmt->execute();
	$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return [
		'usuarios'      => $usuarios,
		'total'         => $total,
		'pagina'        => $pagina,
		'total_paginas' => $totalPaginas,
	];
}

function inicialesUsuario($usuario) {
	$partes = preg_split('/[._\s-]+/', $usuario, -1, PREG_SPLIT_NO_EMPTY);
	if (count($partes) >= 2) {
		return strtoupper(substr($partes[0], 0, 1).substr($partes[1], 0, 1));
	}
	return strtoupper(substr($usuario, 0, 2));
}

function renderFilaUsuario(array $u, $sessionUserId) {
	$iniciales   = inicialesUsuario($u['usuario']);
	$rolClase    = 'ac-badge-'.$u['rol'];
	$rolLabel    = rolEtiqueta($u['rol']);
	$fecha       = date('Y-m-d', strtotime($u['created_at']));
	$checked     = $u['status'] === 'activo' ? 'checked' : '';
	$esActual    = ((int) $u['id'] === (int) $sessionUserId);
	$disabled    = $esActual ? 'disabled title="No puedes desactivar tu propia cuenta"' : '';
	$claseFila   = $u['status'] === 'inactivo' ? 'ac-row-inactivo' : '';
	$usuarioAttr = htmlspecialchars($u['usuario'], ENT_QUOTES);
	$rolAttr     = htmlspecialchars($u['rol'], ENT_QUOTES);
	$supervisorAttr = htmlspecialchars($u['supervisor'] ?? '', ENT_QUOTES);

	return '
	<tr data-id="'.(int) $u['id'].'" class="'.$claseFila.'">
		<td>
			<div class="ac-user-cell">
				<div class="ac-avatar-initials">'.htmlspecialchars($iniciales).'</div>
				<p class="ac-user-name">'.htmlspecialchars($u['usuario']).'</p>
			</div>
		</td>
		<td><span class="ac-badge '.$rolClase.'">'.htmlspecialchars($rolLabel).'</span></td>
		<td>'.htmlspecialchars($u['supervisor'] ?: '—').'</td>
		<td class="ac-mono">'.htmlspecialchars($fecha).'</td>
		<td>
			<label class="ac-switch">
				<input type="checkbox" class="ac-toggle-estado" data-id="'.(int) $u['id'].'" '.$checked.' '.$disabled.'>
				<span class="ac-slider"></span>
			</label>
		</td>
		<td class="ac-text-right">
			<div class="ac-row-actions">
				<button type="button" class="ac-icon-btn ac-btn-clave" data-id="'.(int) $u['id'].'" data-usuario="'.$usuarioAttr.'" title="Modificar Clave">
					<span class="material-symbols-outlined">key</span>
				</button>
				<button type="button" class="ac-icon-btn ac-btn-editar" data-id="'.(int) $u['id'].'" data-usuario="'.$usuarioAttr.'" data-rol="'.$rolAttr.'" data-supervisor="'.$supervisorAttr.'" title="Editar Perfil">
					<span class="material-symbols-outlined">edit</span>
				</button>
			</div>
		</td>
	</tr>';
}

// ---------- Historial de Acuerdos (repositorio_acuerdos) ----------
// Mismo patrón que arriba: la carga inicial (historial.php) y el refresco
// por AJAX (getters/listar_historial.php) comparten la misma consulta y el
// mismo render de fila para no duplicar SQL ni HTML.

function mesCorto($mes) {
	$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	return isset($meses[$mes]) ? $meses[$mes] : '';
}

// "Q1 (Ene-Mar)" para períodos que son un trimestre exacto (mismo texto que
// ya usa el filtro de Historial, ver historial.php) — 2026-08-23, pedido
// explícito para que la columna "Periodo" de la tabla combine con el
// filtro. Un Acuerdo viejo con un rango que no calza con ningún trimestre
// (de antes de que el período se volviera fijo, ver CLAUDE.md) cae al
// formato anterior sin "Qx", nunca inventa un trimestre que no le
// corresponde.
function periodoCorto($mesInicio, $mesFin) {
	if ($mesInicio % 3 === 0 && $mesFin === $mesInicio + 2) {
		$trimestre = intdiv($mesInicio, 3) + 1;
		return 'Q'.$trimestre.' ('.mesCorto($mesInicio).'-'.mesCorto($mesFin).')';
	}
	if ($mesInicio === $mesFin) return mesCorto($mesInicio);
	return mesCorto($mesInicio).' - '.mesCorto($mesFin);
}

// Devuelve [mesInicio, mesFin] (0-11) del trimestre 1-4, o null si no es un
// trimestre válido — el Período del Acuerdo es siempre un trimestre fijo
// desde el 2026-08-18 (ver CLAUDE.md), así que filtrar por trimestre exacto
// alcanza (ya no hace falta un filtro de rango "se solapa con").
function trimestreABounds($trimestre) {
	$trimestre = (int) $trimestre;
	if ($trimestre < 1 || $trimestre > 4) return null;
	$inicio = ($trimestre - 1) * 3;
	return [$inicio, $inicio + 2];
}

// Vencimiento de firma (2026-08-25): un Acta 'generado'/'enviado' que pasa
// 20 días desde fecha_generacion sin volver firmada pasa a 'vencido' — deja
// de poder subírsele la firma (ver subir_acta_firmada.php) y desaparece de
// Historial, mismo criterio que 'anulado' (ver listar_historial_acuerdos()
// y las demás consultas de abajo que ya excluían 'anulado'). Sin cron en
// este proyecto (hosting compartido, sin job runner) — en vez de eso, este
// "barrido" corre cada vez que se listan Actas o se calculan las alertas de
// la campanita, así los datos quedan consistentes sin depender de un
// proceso en segundo plano. `query()` (no `prepare()`) porque no hay
// parámetros de usuario — con MYSQLI_REPORT_OFF (ver db_connect.php) esto
// simplemente devuelve false y no hace nada si el ENUM todavía no tiene
// 'vencido' (falta correr el ALTER TABLE, ver CLAUDE.md), mismo patrón
// defensivo que el resto de columnas nuevas de este archivo.
function barrer_actas_vencidas($mysqli) {
	$mysqli->query(
		"UPDATE repositorio_acuerdos
		 SET estado = 'vencido'
		 WHERE estado IN ('generado', 'enviado')
		   AND fecha_generacion IS NOT NULL
		   AND fecha_generacion < DATE_SUB(CURDATE(), INTERVAL 20 DAY)"
	);
}

// Actas propias por vencer (2026-08-25): alimenta "Mis Actas" de la
// campanita del header — 'generado'/'enviado' sin firmar, con
// $diasUmbral días o menos para cumplirse el plazo de 20 días. Corre el
// barrido primero para no mostrar como "por vencer" algo que en realidad
// ya venció (y para que la próxima carga de Historial no lo vuelva a ver).
function listar_alertas_firma_propias($mysqli, $usuarioId, $diasUmbral = 5) {
	if (!$usuarioId) return [];
	barrer_actas_vencidas($mysqli);
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.fecha_generacion,
		        DATEDIFF(DATE_ADD(a.fecha_generacion, INTERVAL 20 DAY), CURDATE()) AS dias_restantes
		 FROM repositorio_acuerdos a
		 WHERE a.creado_por = ?
		   AND a.estado IN ('generado', 'enviado')
		   AND a.fecha_generacion IS NOT NULL
		 HAVING dias_restantes BETWEEN 0 AND ?
		 ORDER BY dias_restantes ASC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('ii', $usuarioId, $diasUmbral);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $filas;
}

// $usuarioId filtra a "solo los acuerdos que ESTE usuario creó"
// (repositorio_acuerdos.creado_por, guardado por guardar_acuerdo.php al
// insertar). Antes se inferÃ­a indirectamente vÃ­a supervisor/territorio; ahora
// es el dato real, así que sigue siendo correcto aunque un supervisor cambie
// de territorio o dos usuarios lo compartan.
// $trimestre: 1-4, o 0/inválido = "Todos los períodos". $anio: 0 = "Todos los años".
// $filtroFirma: 'todos' (default) | 'firmadas' | 'pendientes' — activado
// desde los stat tiles de arriba de la tabla (2026-08-21), no un <select>.
function listar_historial_acuerdos($mysqli, $busqueda = '', $trimestre = 0, $anio = 0, $filtroFirma = 'todos', $pagina = 1, $usuarioId = null, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';
	$anio   = (int) $anio;

	$bounds           = trimestreABounds($trimestre);
	$trimestreActivo  = $bounds ? 1 : 0;
	$mesInicioFiltro  = $bounds ? $bounds[0] : -1;
	$mesFinFiltro     = $bounds ? $bounds[1] : -1;

	// Sin user_id (no debería pasar si ya hizo login, pero por las dudas) no
	// hay forma de saber qué acuerdos "son suyos" — vacío, no mostrar los de
	// todo el mundo.
	if (!$usuarioId) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}

	// Barrido de vencimiento de firma (2026-08-25) — corre acá para que
	// Historial nunca muestre un Acta que ya debería estar vencida solo
	// porque nadie visitó esta pantalla desde que se cumplieron los 20 días.
	barrer_actas_vencidas($mysqli);

	// El JOIN es solo para mostrar pos_name/cedi — repositorio_locales_
	// supervisores_cliente puede tener varias filas con el mismo pos_id bajo
	// distintos supervisores (~1,116 duplicados detectados), por eso el
	// GROUP BY a.id de abajo, para que un mismo Acuerdo no se duplique en el
	// listado por esas filas repetidas.
	// Condición de firma directo en el texto del SQL (no placeholder): si
	// `acta_firmada_archivo` todavía no existiera, esto rompería prepare()
	// igual que el resto de columnas nuevas de esta función — mismo
	// fallback de abajo ya cubre ese caso (cae a la query sin firma en
	// absoluto, que ignora este filtro; aceptable porque esa columna ya
	// está corrida en producción, ver CLAUDE.md).
	$condicionFirma = '';
	if ($filtroFirma === 'firmadas') $condicionFirma = ' AND a.acta_firmada_archivo IS NOT NULL';
	elseif ($filtroFirma === 'pendientes') $condicionFirma = ' AND a.acta_firmada_archivo IS NULL';

	$sqlBase = "FROM repositorio_acuerdos a
		JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		  AND a.creado_por = ?
		  AND d.pos_name LIKE ?
		  AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		  AND (? = 0 OR a.anio = ?)
		  $condicionFirma";

	// Este componente se renderiza SIEMPRE (visible u oculto) en cada login de
	// desarrollador/superdesarrollador, sea cual sea la pestaña activa (ver
	// index.php: incluye el PHP de todas las secciones visibles, no solo la
	// activa) — por eso, igual que canalDeSupervisor(), esta función nunca
	// debe poder tirar un fatal error si el JOIN externo falla por lo que sea.
	$stmtTotal = $mysqli->prepare("SELECT COUNT(DISTINCT a.id) AS total $sqlBase");
	if (!$stmtTotal) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}
	$stmtTotal->bind_param('isiiiii', $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) {
		$pagina = $totalPaginas;
		$offset = ($pagina - 1) * $porPagina;
	}

	// (a.acta_firmada_archivo IS NOT NULL): si todavía no se corrió el ALTER
	// que agrega esa columna (ver CLAUDE.md, "Subir Acta firmada"), prepare()
	// da false acá — mismo fallback que ya usa login() para `supervisor`, sin
	// esto Historial entero se rompería mientras el usuario corre el SQL.
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado,
		        (a.acta_firmada_archivo IS NOT NULL) AS tiene_firma, a.acta_firmada_mime,
		        d.pos_name, d.cedi
		 $sqlBase
		 GROUP BY a.id
		 ORDER BY a.fecha_generacion DESC, a.id DESC
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) {
		$stmt = $mysqli->prepare(
			"SELECT a.id, a.documento_no, a.mes_inicio, a.mes_fin, a.fecha_generacion, a.estado,
			        0 AS tiene_firma, NULL AS acta_firmada_mime,
			        d.pos_name, d.cedi
			 $sqlBase
			 GROUP BY a.id
			 ORDER BY a.fecha_generacion DESC, a.id DESC
			 LIMIT ? OFFSET ?"
		);
	}
	if (!$stmt) {
		return ['acuerdos' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	}
	$stmt->bind_param('isiiiiiii', $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio, $porPagina, $offset);
	$stmt->execute();
	$acuerdos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return [
		'acuerdos'      => $acuerdos,
		'total'         => $total,
		'pagina'        => $pagina,
		'total_paginas' => $totalPaginas,
	];
}

// Stat tiles de arriba de la tabla de Historial (2026-08-21) — respetan
// búsqueda/trimestre/año (el mismo alcance que ya filtra la tabla), pero NO
// el filtro de firma (esos 3 números son justo lo que decide ese filtro).
// 'pendiente_mas_antigua' es la fecha_generacion más vieja entre las
// pendientes, para el "más antigua: DD/MM" del tile.
function obtener_stats_historial($mysqli, $busqueda, $trimestre, $anio, $usuarioId) {
	$vacio = ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'pendiente_mas_antigua' => null];
	if (!$usuarioId) return $vacio;

	$like = '%'.$busqueda.'%';
	$anio = (int) $anio;
	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;

	$stmt = $mysqli->prepare(
		"SELECT COUNT(DISTINCT a.id) AS total,
		        COUNT(DISTINCT CASE WHEN a.acta_firmada_archivo IS NOT NULL THEN a.id END) AS firmadas,
		        MIN(CASE WHEN a.acta_firmada_archivo IS NULL THEN a.fecha_generacion END) AS pendiente_mas_antigua
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.estado NOT IN ('borrador', 'anulado', 'vencido')
		   AND a.creado_por = ?
		   AND d.pos_name LIKE ?
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)"
	);
	if (!$stmt) return $vacio; // acta_firmada_archivo todavía no existe, ver CLAUDE.md.
	$stmt->bind_param('isiiiii', $usuarioId, $like, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$fila = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	$total    = (int) $fila['total'];
	$firmadas = (int) $fila['firmadas'];
	return [
		'total'                 => $total,
		'firmadas'              => $firmadas,
		'pendientes'            => $total - $firmadas,
		'pendiente_mas_antigua' => $fila['pendiente_mas_antigua'],
	];
}

// Años con al menos un Acuerdo real (no borrador/anulado) de este usuario —
// para poblar el filtro "Año" de Historial sin inventar un rango fijo.
function listar_anios_disponibles($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT anio FROM repositorio_acuerdos
		 WHERE creado_por = ? AND estado NOT IN ('borrador', 'anulado', 'vencido')
		 ORDER BY anio DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$anios = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'anio');
	$stmt->close();
	return array_map('intval', $anios);
}

function renderFilaHistorial(array $a) {
	$fecha = $a['fecha_generacion'] ? date('d/m/Y', strtotime($a['fecha_generacion'])) : '—';

	// Firma subida (2026-08-21): reusa `acta_firmada_archivo` (antes `firmas`,
	// JSON sin usar — ver CLAUDE.md) para saber si ya volvió el Acta firmada a
	// mano. Un solo botón por fila que cambia de ícono/acción según el
	// estado: subir si falta, ver el archivo si ya está.
	$tieneFirma = !empty($a['tiene_firma']);
	// Aviso visual de vencimiento (2026-08-25) — el badge "Pendiente" de
	// siempre pasa a mostrar la cuenta regresiva cuando quedan 5 días o
	// menos (mismo umbral que la campanita del header, ver
	// listar_alertas_firma_propias() más abajo), con 2 niveles de urgencia.
	// Texto rediseñado tras revisar la etiqueta contra las heurísticas de
	// Nielsen (ver concepto "Sala de Alertas", 2026-08-25, aprobado por el
	// usuario tal cual): "Vence en N días" no decía QUÉ vence — se podía
	// leer como que el ACUERDO comercial se cae, no que es la ventana para
	// subir la foto de la firma (heurística 2, coincidencia con el mundo
	// real). "Subí la firma — N días" nombra la acción pendiente en vez de
	// solo el dato (heurística 5, prevención de errores) y usa el
	// vocabulario del usuario ("subí la firma") en vez del sistema
	// ("vence"). $filaUrgencia además marca el `<tr>` para la franja de
	// color lateral (ver ac-fila-urgente/ac-fila-critica en style.css).
	$filaUrgencia = '';
	if ($tieneFirma) {
		$firmaBadge = '<span class="ac-badge ac-badge-ok">Firmada</span>';
	} else {
		$diasRestantes = null;
		if (!empty($a['fecha_generacion']) && in_array($a['estado'] ?? '', ['generado', 'enviado'], true)) {
			$limite = (new DateTime($a['fecha_generacion']))->modify('+20 days');
			$diasRestantes = (int) (new DateTime('today'))->diff($limite)->format('%r%a');
		}
		if ($diasRestantes !== null && $diasRestantes <= 5) {
			$texto = $diasRestantes <= 0 ? 'Subí la firma — hoy' : ($diasRestantes === 1 ? 'Subí la firma — 1 día' : 'Subí la firma — '.$diasRestantes.' días');
			$esCritico = $diasRestantes <= 1;
			$clase = $esCritico ? 'ac-badge-critico' : 'ac-badge-urgente';
			$filaUrgencia = $esCritico ? ' ac-fila-critica' : ' ac-fila-urgente';
			$firmaBadge = '<span class="ac-badge '.$clase.'" title="Plazo de firma: 20 días desde la generación del Acta">'.$texto.'</span>';
		} else {
			$firmaBadge = '<span class="ac-badge ac-badge-revisar">Pendiente</span>';
		}
	}
	// .ac-row-actions-primary + el <span> de texto (oculto en desktop, ver
	// style.css): en mobile este es el botón que más importa de toda la fila
	// — la mayoría de las subidas de Acta firmada van a pasar por celular,
	// así que necesita texto visible y buen tamaño táctil, no un ícono
	// pelado igual de chico que "Eliminar" (2026-08-25, pedido explícito).
	$firmaBtn = $tieneFirma
		? '<button type="button" class="ac-icon-btn ac-icon-btn-success ac-row-actions-primary hist-btn-firma" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" data-tiene-firma="1" data-mime="'.htmlspecialchars($a['acta_firmada_mime'] ?? '').'" title="Ver Acta Firmada"><span class="material-symbols-outlined">task_alt</span><span class="ac-row-actions-primary-label">Ver Firma</span></button>'
		: '<button type="button" class="ac-icon-btn ac-row-actions-primary hist-btn-firma" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" data-tiene-firma="0" title="Subir Acta Firmada"><span class="material-symbols-outlined">upload_file</span><span class="ac-row-actions-primary-label">Subir Firma</span></button>';

	return '
	<tr data-id="'.(int) $a['id'].'" class="hist-fila'.$filaUrgencia.'">
		<td><button type="button" class="ac-link-id hist-btn-ver" data-id="'.(int) $a['id'].'">#'.htmlspecialchars($a['documento_no']).'</button></td>
		<td class="ac-hist-distribuidor">'.htmlspecialchars($a['pos_name']).'</td>
		<td>'.htmlspecialchars($a['cedi'] ?: '—').'</td>
		<td class="ac-text-center">'.htmlspecialchars(periodoCorto((int) $a['mes_inicio'], (int) $a['mes_fin'])).'</td>
		<td class="ac-text-center">'.$firmaBadge.'</td>
		<td class="ac-text-right ac-tabular">'.$fecha.'</td>
		<td class="ac-text-right">
			<div class="ac-row-actions">
				'.$firmaBtn.'
				<button type="button" class="ac-icon-btn hist-btn-descargar" data-id="'.(int) $a['id'].'" title="Descargar PDF">
					<span class="material-symbols-outlined">download</span>
				</button>
				<button type="button" class="ac-icon-btn hist-btn-ver" data-id="'.(int) $a['id'].'" title="Ver Detalles">
					<span class="material-symbols-outlined">visibility</span>
				</button>
				<button type="button" class="ac-icon-btn ac-icon-btn-danger hist-btn-eliminar" data-id="'.(int) $a['id'].'" data-doc="'.htmlspecialchars($a['documento_no']).'" title="Eliminar">
					<span class="material-symbols-outlined">delete</span>
				</button>
			</div>
		</td>
	</tr>';
}

// Cabecera + las 4 tablas de líneas de un acuerdo puntual, para el detalle/
// Acta imprimible que se abre desde Historial (Ver Detalles / Descargar PDF).
function obtener_acuerdo_detalle($mysqli, $acuerdoId) {
	// LIMIT 1 alcanza aunque repositorio_locales_supervisores_cliente tenga
	// pos_id duplicados (~1,116 detectados) — cualquiera de las filas
	// duplicadas tiene el mismo pos_name/cedi para ese pos_id.
	// LEFT JOIN (no JOIN normal) a usuarios_acuerdos: acuerdos viejos con
	// creado_por=NULL (huérfanos, ver CLAUDE.md) no deben tirar todo el
	// detalle abajo — simplemente el Acta sale sin nombre de Ejecutivo
	// Comercial (vuelve a la línea en blanco de siempre, ver acta_pdf.php).
	// d.canal: mismo maestro externo de arriba, esta vez para saber si ESTE
	// pos_id puntual es DISTRIBUIDOR — decide qué formato de Acta usar (ver
	// generar_acta_html()). Se lee del cliente real, no del supervisor de la
	// sesión que lo generó (que puede cambiar con el tiempo).
	// d.tipo_distribuidor: la "Empresa Distribuidora" del cliente (columna que
	// YA existía en el maestro, usada también por acuerdo_distribuidores.php
	// para la cascada Empresa->Cliente) — en el Acta de canal Distribuidor va
	// en "Estimado(a)", separado del nombre del Local (`pos_name`), que sigue
	// yendo en la frase "Jabonería Wilson y ..." (ver acta_pdf.php, 2026-08-20).
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.pos_id, a.anio, a.mes_inicio, a.mes_fin, a.estado, a.fecha_generacion, a.creado_por, a.sin_visibilidad,
		        d.pos_name, d.cedi, d.canal, d.tipo_distribuidor, u.usuario AS ejecutivo_comercial
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = a.creado_por
		 WHERE a.id = ? LIMIT 1"
	);
	if (!$stmt) return null;
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$cabecera = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$cabecera) return null;

	$stmt = $mysqli->prepare(
		"SELECT tipo, segmento, sector, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, precio_percha,
		        valores_mensuales, valor_mensual_unico, orden
		 FROM repositorio_acuerdo_lineas WHERE acuerdo_id = ? ORDER BY tipo, orden"
	);
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$lineas = ['meta_compra' => [], 'cabecera' => [], 'ruma' => [], 'percha' => []];
	foreach ($filas as $f) {
		$valores = $f['valores_mensuales'] !== null ? json_decode($f['valores_mensuales'], true) : [];
		$lineas[$f['tipo']][] = [
			'segmento'            => $f['segmento'],
			'sector'              => $f['sector'],
			'categoria'           => $f['categoria'],
			'marca'               => $f['marca'],
			'rebate_pct'          => $f['rebate_pct'] !== null ? (float) $f['rebate_pct'] : 0,
			'cantidad_max_percha' => (int) $f['cantidad_max_percha'],
			'participacion'       => $f['participacion_pct'] ?? '',
			'precio_percha'       => $f['precio_percha'] !== null ? (float) $f['precio_percha'] : 0,
			'valores_mensuales'   => is_array($valores) ? $valores : [],
			'valor_mensual_unico' => $f['valor_mensual_unico'] !== null ? (float) $f['valor_mensual_unico'] : 0,
		];
	}

	return [
		'id'                => (int) $cabecera['id'],
		'documento_no'      => $cabecera['documento_no'],
		'pos_id'            => $cabecera['pos_id'],
		'anio'              => (int) $cabecera['anio'],
		'mes_inicio'        => (int) $cabecera['mes_inicio'],
		'mes_fin'           => (int) $cabecera['mes_fin'],
		'estado'            => $cabecera['estado'],
		'fecha_generacion'  => $cabecera['fecha_generacion'],
		'creado_por'        => $cabecera['creado_por'] !== null ? (int) $cabecera['creado_por'] : null,
		'distribuidor'      => $cabecera['pos_name'],
		'localidad'         => $cabecera['cedi'] ?: '—',
		'es_distribuidor'   => ($cabecera['canal'] ?? null) === 'DISTRIBUIDOR',
		'sin_visibilidad'   => !empty($cabecera['sin_visibilidad']),
		'empresa_distribuidora' => $cabecera['tipo_distribuidor'] ?: '',
		'ejecutivo_comercial' => $cabecera['ejecutivo_comercial'] ?: '',
		'lineas'            => $lineas,
	];
}

// Borradores propios del usuario logueado (estado='borrador' AND creado_por),
// para la lista "Mis Borradores" de Registrar Acuerdo PDV — mismo criterio de
// scoping por creador que listar_historial_acuerdos(), nunca los de otro
// usuario aunque comparta supervisor/territorio.
function listar_borradores_usuario($mysqli, $usuarioId) {
	if (!$usuarioId) return [];
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.anio, a.mes_inicio, a.mes_fin, a.updated_at,
		        d.pos_name, d.cedi
		 FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.estado = 'borrador' AND a.creado_por = ?
		 GROUP BY a.id
		 ORDER BY a.updated_at DESC"
	);
	if (!$stmt) return [];
	$stmt->bind_param('i', $usuarioId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return $filas;
}

// ---------- Módulo Repositorios (2026-08-24) ----------
// Dos catálogos self-service (Rebate, Participación de Percha) que autocompletan
// y bloquean esos campos en el Acta — ver CLAUDE.md "Módulo Repositorios".
// Mismo patrón de paginación/búsqueda que listar_historial_acuerdos()/
// listar_usuarios_acuerdos(). $stmt puede venir null si todavía no se corrió
// datos/repositorios_schema.sql — se devuelve vacío en vez de un fatal error,
// mismo criterio que el fallback de listar_usuarios_acuerdos() para `supervisor`.
function listar_repositorio_rebate($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	// eliminado_en IS NULL (2026-08-25, borrado lógico — regla base, ver
	// datos/repositorios_schema.sql y repositorio_eliminar.php): el listado
	// normal nunca muestra filas borradas, esas viven en "Eliminados" (ver
	// listar_repositorio_rebate_eliminados() más abajo).
	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_rebate_producto
		 WHERE eliminado_en IS NULL AND (ciudad LIKE ? OR canal LIKE ? OR sector LIKE ? OR categoria LIKE ? OR marca LIKE ?)"
	);
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmtTotal->bind_param('sssss', $like, $like, $like, $like, $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		"SELECT r.id, r.ciudad, r.canal, r.sector, r.categoria, r.marca, r.rebate_pct, r.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_rebate_producto r
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = r.actualizado_por
		 WHERE r.eliminado_en IS NULL AND (r.ciudad LIKE ? OR r.canal LIKE ? OR r.sector LIKE ? OR r.categoria LIKE ? OR r.marca LIKE ?)
		 ORDER BY r.ciudad, r.canal, r.sector, r.categoria, r.marca
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmt->bind_param('sssssii', $like, $like, $like, $like, $like, $porPagina, $offset);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

function listar_repositorio_participacion($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	// eliminado_en IS NULL (2026-08-25, borrado lógico — ver nota en
	// listar_repositorio_rebate() de arriba, mismo criterio acá).
	$stmtTotal = $mysqli->prepare('SELECT COUNT(*) AS total FROM repositorio_participacion_percha WHERE eliminado_en IS NULL AND marca LIKE ?');
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmtTotal->bind_param('s', $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		"SELECT p.id, p.marca, p.participacion_pct, p.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_participacion_percha p
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = p.actualizado_por
		 WHERE p.eliminado_en IS NULL AND p.marca LIKE ?
		 ORDER BY p.marca
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmt->bind_param('sii', $like, $porPagina, $offset);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

// Cuotas resueltas (pos_id encontrado) — 'pendiente_match' vive aparte en
// listar_repositorio_cuotas_pendientes_match(), mismo concepto visual que
// "Pendientes de Asignar" de Liquidación, para no mezclar "cliente
// identificado, cuota lista para usarse" con "todavía no sabemos de quién
// es esta fila" en la misma tabla.
function listar_repositorio_cuotas($mysqli, $busqueda = '', $pagina = 1, $porPagina = 10) {
	$pagina = max(1, (int) $pagina);
	$offset = ($pagina - 1) * $porPagina;
	$like   = '%'.$busqueda.'%';

	$stmtTotal = $mysqli->prepare(
		"SELECT COUNT(*) AS total FROM repositorio_cuota_cliente
		 WHERE estado <> 'pendiente_match' AND (cliente_excel LIKE ? OR pos_id LIKE ? OR sector LIKE ?)"
	);
	if (!$stmtTotal) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmtTotal->bind_param('sss', $like, $like, $like);
	$stmtTotal->execute();
	$total = (int) $stmtTotal->get_result()->fetch_assoc()['total'];
	$stmtTotal->close();

	$totalPaginas = max(1, (int) ceil($total / $porPagina));
	if ($pagina > $totalPaginas) { $pagina = $totalPaginas; $offset = ($pagina - 1) * $porPagina; }

	$stmt = $mysqli->prepare(
		"SELECT c.id, c.pos_id, c.cliente_excel, c.cedi_excel, c.plan, c.sector, c.trimestre, c.anio, c.valores_mensuales, c.estado, c.updated_at, u.usuario AS actualizado_por_usuario
		 FROM repositorio_cuota_cliente c
		 LEFT JOIN repositorio_usuarios_acuerdos u ON u.id = c.actualizado_por
		 WHERE c.estado <> 'pendiente_match' AND (c.cliente_excel LIKE ? OR c.pos_id LIKE ? OR c.sector LIKE ?)
		 ORDER BY c.anio DESC, c.trimestre DESC, c.cliente_excel, c.sector
		 LIMIT ? OFFSET ?"
	);
	if (!$stmt) return ['filas' => [], 'total' => 0, 'pagina' => 1, 'total_paginas' => 1];
	$stmt->bind_param('sssii', $like, $like, $like, $porPagina, $offset);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	// mysqli no decodifica columnas JSON solo — sin esto, json_encode() de la
	// respuesta completa lo manda como STRING escapado en vez de objeto (ver
	// mismo criterio en obtener_acuerdo_detalle()/valores_mensuales).
	foreach ($filas as &$fila) {
		$fila['valores_mensuales'] = $fila['valores_mensuales'] !== null ? json_decode($fila['valores_mensuales'], true) : [];
	}
	unset($fila);

	return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => $totalPaginas];
}

// Cola de resolución manual — filas donde resolverPosIdCliente() no encontró
// exactamente un cliente (0 o más de 1 candidato). Se muestran junto con los
// candidatos posibles (mismo nombre, sin filtrar por CEDI) para que el
// superdesarrollador elija a mano, igual que liquidacion_pendientes.php.
function listar_repositorio_cuotas_pendientes_match($mysqli) {
	$stmt = $mysqli->prepare(
		"SELECT id, cliente_excel, cedi_excel, plan, sector, trimestre, anio, valores_mensuales
		 FROM repositorio_cuota_cliente
		 WHERE estado = 'pendiente_match'
		 ORDER BY cliente_excel, sector"
	);
	if (!$stmt) return [];
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	foreach ($filas as &$fila) {
		$fila['valores_mensuales'] = $fila['valores_mensuales'] !== null ? json_decode($fila['valores_mensuales'], true) : [];
	}
	unset($fila);

	$stmtCand = $mysqli->prepare(
		"SELECT pos_id, pos_name, cedi, supervisor FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%') ORDER BY pos_name LIMIT 10"
	);
	foreach ($filas as &$fila) {
		$fila['candidatos'] = [];
		if ($stmtCand) {
			$stmtCand->bind_param('s', $fila['cliente_excel']);
			$stmtCand->execute();
			$fila['candidatos'] = $stmtCand->get_result()->fetch_all(MYSQLI_ASSOC);
		}
	}
	unset($fila);
	if ($stmtCand) $stmtCand->close();

	return $filas;
}

// ---------- Seguimiento de Equipo (repositorio_acuerdos, TODOS los usuarios) ----------
// Rediseño 2026-08-27 (misma fecha, sesión de diseño con Claude Design):
// reemplaza el primer intento (tiles + tabla con acordeón) por un
// maestro-detalle con UN SOLO filtro de estado (Todas/Firmadas/Pendientes/
// Vencidas) que controla a la vez la lista de "Equipo" y el detalle — ver el
// mockup aprobado, link en CLAUDE.md. Sin bucket de "Sin usuario asignado"
// (pedido explícito del usuario: no mostrarlo) — este módulo solo cuenta
// Actas con un usuario real vinculado (`creado_por`), por diseño, no es un
// bug si el total no coincide con el total crudo de `repositorio_acuerdos`.
//
// Arquitectura: los getters devuelven JSON crudo (no HTML pre-armado como el
// resto del proyecto) — mismo patrón ya usado en resumen_cuotas()/
// cuotas_resumen.php (el gráfico de barras de Repositorios también arma su
// DOM en JS a partir de JSON). Se eligió acá porque cambiar de filtro/
// buscar tiene que sentirse instantáneo (sin ida y vuelta al servidor por
// cada click), y el dataset por equipo es chico.
//
// Única pantalla del proyecto donde superdesarrollador ve Actas de OTROS
// usuarios (todo lo demás filtra siempre por creado_por de la sesión) —
// reforzar el chequeo de rol en los getters, no alcanza con que el módulo
// esté oculto del sidebar para los demás roles.

// Años con al menos un Acuerdo real de CUALQUIER usuario — a diferencia de
// listar_anios_disponibles() (que filtra por creado_por), este filtro de
// Seguimiento es a nivel de todo el equipo.
function listar_anios_disponibles_equipo($mysqli) {
	$anios = [];
	$r = $mysqli->query(
		"SELECT DISTINCT anio FROM repositorio_acuerdos
		 WHERE estado NOT IN ('borrador', 'anulado') ORDER BY anio DESC"
	);
	if ($r) $anios = array_map('intval', array_column($r->fetch_all(MYSQLI_ASSOC), 'anio'));
	return $anios;
}

// Stats globales (4 números) + un array por usuario con sus 4 conteos
// (total/firmadas/pendientes/vencidas) más `dias_mas_proxima` (la Acta
// pendiente más urgente de ese usuario, null si no tiene ninguna) — de acá
// el frontend deriva las 4 vistas filtradas sin pedir nada más al servidor.
// $trimestre 1-4 (0 = todos), $anio 0 = todos.
function resumen_seguimiento_equipo($mysqli, $trimestre = 0, $anio = 0) {
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	$vacio = ['stats' => ['total' => 0, 'firmadas' => 0, 'pendientes' => 0, 'vencidas' => 0], 'equipo' => []];

	$stmt = $mysqli->prepare(
		"SELECT u.id AS usuario_id, u.usuario AS nombre,
		        COUNT(*) AS total,
		        COUNT(CASE WHEN a.acta_firmada_archivo IS NOT NULL THEN 1 END) AS firmadas,
		        COUNT(CASE WHEN a.acta_firmada_archivo IS NULL AND a.estado IN ('generado', 'enviado') THEN 1 END) AS pendientes,
		        COUNT(CASE WHEN a.estado = 'vencido' THEN 1 END) AS vencidas,
		        MIN(CASE WHEN a.acta_firmada_archivo IS NULL AND a.estado IN ('generado', 'enviado')
		                 THEN DATEDIFF(DATE_ADD(a.fecha_generacion, INTERVAL 20 DAY), CURDATE()) END) AS dias_mas_proxima
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
		$u['dias_mas_proxima'] = $u['dias_mas_proxima'] !== null ? (int) $u['dias_mas_proxima'] : null;
		// Calculado acá (misma función que usa Gestión de Usuarios) para que
		// el frontend nunca tenga su propia versión — antes seguimiento.js
		// reimplementaba esto con una regex más simple (solo espacios) que
		// daba mal las iniciales de un usuario con punto en el nombre
		// (ej. "javier.maldonado"), divergiendo del resto de la app.
		$u['iniciales']        = inicialesUsuario($u['nombre']);
		$stats['total']      += $u['total'];
		$stats['firmadas']   += $u['firmadas'];
		$stats['pendientes'] += $u['pendientes'];
		$stats['vencidas']   += $u['vencidas'];
	}
	unset($u);

	return ['stats' => $stats, 'equipo' => $equipo];
}

// Detalle de un usuario para el filtro de estado activo — $tipo:
// 'todas' | 'firmadas' | 'pendientes' | 'vencidas' (validado con whitelist
// en el getter, acá se usa directo en el SQL). 'pendientes' ordena por
// urgencia (menos días primero); el resto por fecha de generación. Mismo
// GROUP BY a.id que listar_historial_acuerdos() por los ~1,116 pos_id
// duplicados de repositorio_locales_supervisores_cliente.
function listar_actas_equipo_usuario($mysqli, $usuarioId, $trimestre = 0, $anio = 0, $tipo = 'todas') {
	$usuarioId = (int) $usuarioId;
	if (!$usuarioId) return [];

	// Sin esto, un Acta que ya pasó los 20 días pero nadie visitó Historial/
	// este módulo desde entonces seguía apareciendo como 'generado'/
	// 'enviado' con días negativos en vez de 'vencido' — mismo criterio que
	// listar_historial_acuerdos()/resumen_seguimiento_equipo() (esta función
	// se llama sola sin pasar antes por esa, ej. si el detalle se pide
	// directo contra el getter).
	barrer_actas_vencidas($mysqli);

	$bounds          = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro    = $bounds ? $bounds[1] : -1;
	$anio            = (int) $anio;

	switch ($tipo) {
		case 'firmadas':   $condicionEstado = "a.acta_firmada_archivo IS NOT NULL"; $orden = 'a.fecha_generacion DESC'; break;
		case 'pendientes': $condicionEstado = "a.estado IN ('generado', 'enviado') AND a.acta_firmada_archivo IS NULL"; $orden = 'dias_restantes ASC'; break;
		case 'vencidas':   $condicionEstado = "a.estado = 'vencido'"; $orden = 'a.fecha_generacion DESC'; break;
		default:           $condicionEstado = "a.estado NOT IN ('borrador', 'anulado')"; $orden = 'a.fecha_generacion DESC';
	}

	// LEFT JOIN (no JOIN normal) a propósito: el conteo por usuario de
	// resumen_seguimiento_equipo() no depende del maestro de clientes, así
	// que si el `pos_id` de un Acta real ya no matchea ese maestro (mismo
	// fenómeno ya documentado para las Actas huérfanas viejas — puede
	// pasarle a una Acta con usuario real también), con JOIN normal esa
	// fila desaparecía del detalle aunque SÍ contara en el total de la
	// lista de Equipo. pos_name cae a NULL -> '—' en seguimiento.js.
	$stmt = $mysqli->prepare(
		"SELECT a.id, a.documento_no, a.fecha_generacion, a.estado,
		        (a.acta_firmada_archivo IS NOT NULL) AS tiene_firma,
		        d.pos_name,
		        DATEDIFF(DATE_ADD(a.fecha_generacion, INTERVAL 20 DAY), CURDATE()) AS dias_restantes
		 FROM repositorio_acuerdos a
		 LEFT JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.creado_por = ?
		   AND $condicionEstado
		   AND (? = 0 OR (a.mes_inicio = ? AND a.mes_fin = ?))
		   AND (? = 0 OR a.anio = ?)
		 GROUP BY a.id
		 ORDER BY $orden"
	);
	if (!$stmt) return [];
	$stmt->bind_param('iiiiii', $usuarioId, $trimestreActivo, $mesInicioFiltro, $mesFinFiltro, $anio, $anio);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	foreach ($filas as &$f) {
		$f['tiene_firma']    = (bool) $f['tiene_firma'];
		$f['dias_restantes'] = $f['dias_restantes'] !== null ? (int) $f['dias_restantes'] : null;
	}
	unset($f);
	return $filas;
}
?>
