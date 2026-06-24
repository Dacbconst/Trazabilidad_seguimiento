<?php
include_once '../db_connect.php';
include_once '../includes/filtros.php';

$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin    = $_GET['fecha_fin']    ?? '';
$supervisor   = $_GET['supervisor']   ?? '.';
$gestor       = $_GET['gestor']       ?? '.';

$filtroSupervisor = ($supervisor === '.' || $supervisor === '') ? '1=1' : "l.supervisor REGEXP '$supervisor'";
$filtroGestor     = ($gestor === '.'    || $gestor === '')    ? '1=1' : "l.mercaderista REGEXP '$gestor'";

// Detalle por PDV (se agrupa luego en PHP por fecha+supervisor+gestor)
// "ex" verifica si ESE MISMO gestor relevó exhibiciones en ese PDV+fecha.
// insert_exhibiciones.usuario guarda el nombre como "NOMBRE1 APELLIDO1" (formato corto),
// mientras que lvi_rutero.mercaderista lo guarda como el nombre completo
// (ej. "APELLIDO1 APELLIDO2 NOMBRE1 NOMBRE2"). En vez de asumir una posición fija de
// palabras (lo que rompería con nombres de 3 o 5 palabras), se valida que la primera y
// la última palabra de "usuario" existan como palabras completas dentro de "mercaderista".
// Esto evita falsos positivos (PDV+fecha cubierto por otro gestor) sin bloquear casos
// con formatos de nombre atípicos.
$query = "
    SELECT
        DATE_FORMAT(l.fecha_visita, '%d/%m/%Y') AS fecha,
        l.supervisor,
        l.mercaderista AS gestor,
        l.pos_name,
        MAX(CASE WHEN r.tipo = 'ENTRADA' AND r.foto_antes IS NOT NULL AND r.foto_antes != ''
                      AND r.foto_antes != 'N/A' THEN 1 ELSE 0 END)   AS tiene_antes,
        MAX(CASE WHEN r.tipo = 'SALIDA' AND r.foto_despues IS NOT NULL AND r.foto_despues != ''
                      AND r.foto_despues != 'N/A' THEN 1 ELSE 0 END) AS tiene_despues,
        MAX(CASE WHEN ex.cnt > 0 THEN 1 ELSE 0 END) AS tiene_exh
    FROM lvi_rutero l
    LEFT JOIN vi_registro r
        ON  l.pos_id = r.pos_id
        AND l.mercaderista = r.mercaderista
        AND l.fecha_visita = STR_TO_DATE(r.fecha, '%d/%m/%Y')
    LEFT JOIN (
        SELECT codigo, usuario, fecha, COUNT(*) AS cnt
        FROM insert_exhibiciones
        GROUP BY codigo, usuario, fecha
    ) ex
        ON  l.pos_id = ex.codigo
        AND l.fecha_visita = STR_TO_DATE(ex.fecha, '%d/%m/%Y')
        AND CONCAT(' ', UPPER(l.mercaderista), ' ')
            LIKE CONCAT('% ', UPPER(SUBSTRING_INDEX(TRIM(ex.usuario), ' ', 1)), ' %')
        AND CONCAT(' ', UPPER(l.mercaderista), ' ')
            LIKE CONCAT('% ', UPPER(SUBSTRING_INDEX(TRIM(ex.usuario), ' ', -1)), ' %')
    WHERE l.fecha_visita BETWEEN '$fecha_inicio' AND '$fecha_fin'
    AND   l.mercaderista NOT LIKE '%PRUEBA%'
    AND   $filtroSupervisor
    AND   $filtroGestor
    GROUP BY l.pos_id, l.mercaderista, l.fecha_visita, l.supervisor, l.pos_name
    ORDER BY l.fecha_visita DESC, LOWER(l.mercaderista) ASC, LOWER(l.pos_name) ASC
";

if (!($sql = $mysqli->prepare($query))) {
    echo "<script>alert('Error al generar el Excel.');window.close();</script>";
    die();
}

$sql->execute();
$sql->store_result();

if ($sql->num_rows === 0) {
    echo "<script>alert('No hay datos para exportar.');window.close();</script>";
    die();
}

$sql->bind_result($fecha, $supervisor_r, $gestor_r, $pos_name, $tiene_antes, $tiene_despues, $tiene_exh)
    or die($sql->error);

// Agrupar por fecha + supervisor + gestor, manteniendo el orden de llegada
$grupos = [];
while ($sql->fetch()) {
    $clave = $fecha . '|' . $supervisor_r . '|' . $gestor_r;
    if (!isset($grupos[$clave])) {
        $grupos[$clave] = [
            'fecha'      => $fecha,
            'supervisor' => $supervisor_r,
            'gestor'     => $gestor_r,
            'pdvs'       => [],
            'asignado'   => 0,
            'relevado'   => 0,
            'tiene_exh'  => 0,
        ];
    }
    $relevado = ($tiene_antes || $tiene_despues) ? 1 : 0;
    $grupos[$clave]['pdvs'][]  = ['nombre' => $pos_name, 'relevado' => $relevado];
    $grupos[$clave]['asignado']++;
    $grupos[$clave]['relevado'] += $relevado;
    if ($tiene_exh) $grupos[$clave]['tiene_exh'] = 1;
}
$sql->close();

// --- Generación de XLSX real (evita el aviso de "formato y extensión no coinciden") ---

function xlsxText($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

$headers = ['FECHA', 'SUPERVISOR', 'GESTOR', 'PDVS PLANIFICADOS', 'RELEVADOS', '% CUMPLIMIENTO', 'ANTES Y DESPUES', 'EXHIBICIONES', 'DETALLE PDV NO RELEVADOS'];

$rowsXml  = '<row r="1">';
foreach ($headers as $i => $h) {
    $col = chr(65 + $i); // A..I
    $rowsXml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xlsxText($h) . '</t></is></c>';
}
$rowsXml .= '</row>';

$rowNum = 2;
foreach ($grupos as $g) {
    $pct = $g['asignado'] > 0 ? round($g['relevado'] / $g['asignado'] * 100) : 0;
    $antesDespues = $g['relevado'] > 0 ? 1 : 0;
    $exhibiciones = $g['tiene_exh'] ? 1 : 0;

    // Columna DETALLE PDV NO RELEVADO: solo los locales sin relevo, en rojo y negrita
    $noRelevados = array_values(array_filter($g['pdvs'], function($p) { return !$p['relevado']; }));
    $runs  = '';
    $total = count($noRelevados);
    foreach ($noRelevados as $i => $p) {
        $texto    = $p['nombre'] . ($i < $total - 1 ? ', ' : '');
        $textoEsc = xlsxText($texto);
        $runs .= '<r><rPr><b/><color rgb="FFC0392B"/></rPr><t xml:space="preserve">' . $textoEsc . '</t></r>';
    }

    $rowsXml .= '<row r="' . $rowNum . '">'
        . '<c r="A' . $rowNum . '" t="inlineStr"><is><t>' . xlsxText($g['fecha']) . '</t></is></c>'
        . '<c r="B' . $rowNum . '" t="inlineStr"><is><t>' . xlsxText($g['supervisor']) . '</t></is></c>'
        . '<c r="C' . $rowNum . '" t="inlineStr"><is><t>' . xlsxText($g['gestor']) . '</t></is></c>'
        . '<c r="D' . $rowNum . '" t="n"><v>' . (int)$g['asignado'] . '</v></c>'
        . '<c r="E' . $rowNum . '" t="n"><v>' . (int)$g['relevado'] . '</v></c>'
        . '<c r="F' . $rowNum . '" t="inlineStr"><is><t>' . $pct . '%</t></is></c>'
        . '<c r="G' . $rowNum . '" t="n"><v>' . $antesDespues . '</v></c>'
        . '<c r="H' . $rowNum . '" t="n"><v>' . $exhibiciones . '</v></c>'
        . '<c r="I' . $rowNum . '" t="inlineStr"><is>' . $runs . '</is></c>'
        . '</row>';

    $rowNum++;
}

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '</Types>';

$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="Seguimiento" sheetId="1" r:id="rId1"/></sheets>'
    . '</workbook>';

$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
    . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
    . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="2">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
    . '</cellXfs>'
    . '</styleSheet>';

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<cols>'
    . '<col min="1" max="1" width="12" customWidth="1"/>'
    . '<col min="2" max="2" width="22" customWidth="1"/>'
    . '<col min="3" max="3" width="30" customWidth="1"/>'
    . '<col min="4" max="4" width="16" customWidth="1"/>'
    . '<col min="5" max="5" width="12" customWidth="1"/>'
    . '<col min="6" max="6" width="16" customWidth="1"/>'
    . '<col min="7" max="7" width="14" customWidth="1"/>'
    . '<col min="8" max="8" width="15" customWidth="1"/>'
    . '<col min="9" max="9" width="80" customWidth="1"/>'
    . '</cols>'
    . '<sheetData>' . $rowsXml . '</sheetData>'
    . '</worksheet>';

$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
$zip->addFromString('xl/styles.xml', $styles);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Seguimiento_' . $fecha_inicio . '_' . $fecha_fin . '.xlsx"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
unlink($tmpFile);
