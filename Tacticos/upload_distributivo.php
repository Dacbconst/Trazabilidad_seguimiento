<?php
use Phppot\DataSource;

require_once 'conexion/DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES["file"]) && $_FILES["file"]["error"] === UPLOAD_ERR_OK) {
        $fileName = $_FILES["file"]["tmp_name"];

        if ($_FILES["file"]["size"] > 0) {
            $file = fopen($fileName, "r");

            // Vaciar la tabla
            $db->truncate("TRUNCATE table repositorio_distributivo");

            $data = [];
            while (($column = fgetcsv($file, 10000, ";")) !== FALSE) {
                $sqlInsert = "INSERT INTO repositorio_distributivo 
                    (mercaderista, regional,jefatura, ejecutivo,supervisor, distribuidor, ciudad, tactico, cantidad_asignada, fecha_asignacion, observaciones) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,?,?)";
                $paramType = "sssssssssss";
                $paramArray = array_map(fn($value) => mysqli_real_escape_string($conn, $value ?? ""), $column);

                $insertId = $db->insert($sqlInsert, $paramType, $paramArray);
                if (!$insertId) {
                    $response = ["success" => false, "message" => "Error al insertar los datos."];
                    echo json_encode($response);
                    exit;
                }
            }
            
            fclose($file);
            
            $uploadDateTime = date("Y-m-d H:i:s");
            $filePath = "last_upload_time.txt";

            if (file_put_contents($filePath, $uploadDateTime) !== false) {
                $response = ["success" => true, "message" => "Datos insertados exitosamente."];
            } else {
                $response = ["success" => true, "message" => "Datos insertados, pero no se pudo actualizar la fecha y hora de la última carga."];
            }

        } else {
            $response = ["success" => false, "message" => "El archivo está vacío."];
        }
    } else {
        $response = ["success" => false, "message" => "No se recibió un archivo válido."];
    }
} else {
    $response = ["success" => false, "message" => "Método no permitido. Use POST."];
}

header('Content-Type: application/json');
echo json_encode($response);
