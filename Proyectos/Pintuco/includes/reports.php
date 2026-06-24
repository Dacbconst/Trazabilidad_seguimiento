<?php
// RQFOTOGRAFICODACB - reports.php adaptado para Unilever: solo exhibiciones desde vi_evidencias
require_once __DIR__ . '/config.php';
require $_SERVER["DOCUMENT_ROOT"] . '/App/XploraEcuador/assets/pluginsV3/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function exhibiciones_reporte($fechaInicio, $fechaFin, $mysqli) {
    $documento = new Spreadsheet();
    $documento->getProperties()
        ->setCreator("Lucky Ecuador")
        ->setLastModifiedBy("Lucky Ecuador")
        ->setTitle("Reporte Exhibiciones Unilever")
        ->setDescription("Reporte");

    $hoja = $documento->getActiveSheet();
    $hoja->setTitle("Exhibiciones");

    $encabezado = [
        "ID", "FECHA", "HORA", "SUPERVISOR", "MERCADERISTA",
        "CANAL", "CADENA", "LOCAL", "CIUDAD", "DIRECCIÓN",
        "CATEGORÍA", "COMENTARIO", "FOTO ANTES", "FOTO DESPUÉS"
    ];

    $col = 'A';
    foreach ($encabezado as $titulo) {
        $hoja->setCellValue($col . '1', $titulo);
        $col++;
    }

    $query = "SELECT id, fecha, hora, supervisor, mercaderista, channel, customer_owner,
                     pos_name, city, address, categoria, comentario, foto_antes, foto_despues
              FROM vi_evidencias
              WHERE STR_TO_DATE(fecha, '%d/%m/%Y') BETWEEN ? AND ?
              ORDER BY fecha, hora";

    if ($stmt = $mysqli->prepare($query)) {
        $stmt->bind_param('ss', $fechaInicio, $fechaFin);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $fecha, $hora, $supervisor, $mercaderista, $channel,
                           $customer_owner, $pos_name, $city, $address,
                           $categoria, $comentario, $foto_antes, $foto_despues);

        $fila = 2;
        while ($stmt->fetch()) {
            $hoja->setCellValue('A' . $fila, $id);
            $hoja->setCellValue('B' . $fila, $fecha);
            $hoja->setCellValue('C' . $fila, $hora);
            $hoja->setCellValue('D' . $fila, $supervisor);
            $hoja->setCellValue('E' . $fila, $mercaderista);
            $hoja->setCellValue('F' . $fila, $channel);
            $hoja->setCellValue('G' . $fila, $customer_owner);
            $hoja->setCellValue('H' . $fila, $pos_name);
            $hoja->setCellValue('I' . $fila, $city);
            $hoja->setCellValue('J' . $fila, $address);
            $hoja->setCellValue('K' . $fila, $categoria);
            $hoja->setCellValue('L' . $fila, $comentario);
            $hoja->setCellValue('M' . $fila, $foto_antes);
            $hoja->setCellValue('N' . $fila, $foto_despues);
            $fila++;
        }
        $stmt->close();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="ReporteExhibicionesUnilever.xlsx"');
    $writer = new Xlsx($documento);
    $writer->save('php://output');
    exit;
}
