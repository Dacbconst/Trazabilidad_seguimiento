<?php
require_once __DIR__ . '/conexion/DataSource.php';
require '../../AppJaboneriaWilson/Inserts/upload_azure.php';
use Phppot\DataSource;

$database = new DataSource();
$conn = $database->getConnection();

header("Content-Type: application/json");

// Leer el cuerpo de la solicitud
$input = json_decode(file_get_contents("php://input"), true);

// Validar los datos recibidos
if (!isset($input['id_packs'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID del registro no proporcionado"]);
    exit;
}

// Obtener los datos del cuerpo de la solicitud
$idPacks = $input['id_packs'];
$categoriasec = $input['categoriasec'] ?? null;
$subcategoriasec = $input['subcategoriasec'] ?? null;
$brandsec = $input['brandsec'] ?? null;
$skuCodesec = $input['sku_codesec'] ?? null;
$pvc = $input['pvc'] ?? null;
$cantidad = $input['cantidad'] ?? null;
$cantidadEncontrada = $input['cantidad_encontrada'] ?? null;
$observacion = $input['observacion'] ?? null;
$fotoArmado = $input['foto_armado'] ?? null;
$fotoGuia = $input['foto_guia'] ?? null;
$usuario = $input['user'] ?? null;
$pharma_id = $input['id_pdv'] ?? null;

$fecha = date('Y-m-d');

$hora = date('H:i:s');

$fecha_numeros = str_replace('/', '', $fecha);
$hora_numeros = str_replace(':', '', $hora);

$unique = $fecha_numeros . $hora_numeros;

$name = "$unique$usuario$pharma_id";
$name_final = str_replace(str_split('\\/:*?"<>|%+#'), '', $name);
$photo_name = str_replace(' ', '', $name_final);
$path = "Packs/$photo_name.png";

if ($foto_guia != 'NA') {
    $name_fg = "$unique$usuario$pharma_id" . "_FG";
    $name_final_fg = str_replace(str_split('\\/:*?"<>|%+#'), '', $name_fg);
    $photo_name_fg = str_replace(' ', '', $name_final_fg);
    $path_fg = "Packs/$photo_name_fg.png";
} else {
    $path_fg = 'Packs/NO_FOTO.png';
}

try {
    // Consulta para actualizar el registro
    $query = "
        UPDATE insert_packs
        SET 
            categoriasec = ?, 
            subcategoriasec = ?, 
            brandsec = ?, 
            pvc = ?, 
            cantidad = ?, 
            cantidad_encontrada = ?, 
            observacion = ?, 
            foto = ?, 
            foto_guia = ?, 
            estado = 1
        WHERE 
            id_packs = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "ssssdssssi",
        $categoriasec,
        $subcategoriasec,
        $brandsec,
        $pvc,
        $cantidad,
        $cantidadEncontrada,
        $observacion,
        $path,
        $path_fg,
        $idPacks
    );

    if ($stmt->execute()) {
        //subir fotos
        $container = 'app/AppJaboneriaWilson/Inserts/Packs';
        uploadBlobSample($blobClient, $container, $fotoArmado, $photo_name.'.png');
        if ($foto_guia != 'NA') {
            uploadBlobSample($blobClient, $container, $fotoGuia, $photo_name_fg.'.png');
        }        

        echo json_encode(["success" => true, "message" => "Registro actualizado correctamente"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al actualizar el registro"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al procesar la solicitud", "details" => $e->getMessage()]);
}
?>