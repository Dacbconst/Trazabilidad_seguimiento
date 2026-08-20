<?php
/**
 * Insertar una nueva meta en la base de datos
 */

require '../Data/Funciones.php';
require 'upload_azure.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $body = json_decode(file_get_contents("php://input"), true);
    
    // Determinar si es un array de evaluaciones
    $isBatch = is_array($body) && array_keys($body) === range(0, count($body) - 1);
    $evaluations = $isBatch ? $body : array($body);
    $results = array();

    foreach ($evaluations as $eval) {
        // Extraer parámetros de cada evaluación
        $codigo = isset($eval['codigo']) ? $eval['codigo'] : '';
        $rol = isset($eval['rol']) ? $eval['rol'] : '';
        $cedi = isset($eval['cedi']) ? $eval['cedi'] : '';
        $codigo_asesor = isset($eval['codigo_asesor']) ? $eval['codigo_asesor'] : '';
        $asesor = isset($eval['asesor']) ? $eval['asesor'] : '';
        $tipo_cliente = isset($eval['tipo_cliente']) ? $eval['tipo_cliente'] : '';
        $promedio_venta = isset($eval['promedio_venta']) ? $eval['promedio_venta'] : '';
        $unidades_minimas = isset($eval['unidades_minimas']) ? $eval['unidades_minimas'] : '';
        $usuario = isset($eval['usuario']) ? $eval['usuario'] : '';
        $ciudad = isset($eval['ciudad']) ? $eval['ciudad'] : '';
        $cliente = isset($eval['cliente']) ? $eval['cliente'] : '';
        $gestor_asignado = isset($eval['gestor_asignado']) ? $eval['gestor_asignado'] : '';
        $categoria = isset($eval['categoria']) ? $eval['categoria'] : '';
        $pregunta = isset($eval['pregunta']) ? $eval['pregunta'] : '';
        $variante = isset($eval['variante']) ? $eval['variante'] : '';
        $respuesta = isset($eval['respuesta']) ? $eval['respuesta'] : '';
        $foto_pregunta = isset($eval['foto_pregunta']) ? $eval['foto_pregunta'] : 'NO_FOTO';
        $comentario_pregunta = isset($eval['comentario_pregunta']) ? $eval['comentario_pregunta'] : '';
        $foto_fachada = isset($eval['foto_fachada']) ? $eval['foto_fachada'] : 'NO_FOTO';
        $calificacion = isset($eval['calificacion']) ? $eval['calificacion'] : '';
        $satisfaccion = isset($eval['satisfaccion']) ? $eval['satisfaccion'] : '';
        $comentario = isset($eval['comentario']) ? $eval['comentario'] : '';
        $precio = isset($eval['precio']) ? $eval['precio'] : '';
        $fecha = isset($eval['fecha']) ? $eval['fecha'] : '';
        $hora = isset($eval['hora']) ? $eval['hora'] : '';
        $canal = isset($eval['canal']) ? $eval['canal'] : '';

        // Generar identificadores únicos
        $unique_pregunta = round(microtime(true) * 1000);
        $fecha_numeros = str_replace('/', '', $fecha);
        $hora_numeros = str_replace(':', '', $hora);
        $unique = $fecha_numeros . $hora_numeros;

        // Procesar imágenes
        $path_foto_pregunta = "EvaluacionVisita/Respuestas/NO_FOTO.png";
        $path_foto_fachada = "EvaluacionVisita/Fachada/NO_FOTO.png";

        if ($foto_pregunta != 'NO_FOTO') {
            $name_pregunta = "$unique_pregunta$usuario";
            $full_name_pregunta = str_replace(str_split('\\/:*?"<>|%+#'), '', $name_pregunta);
            $photo_name_pregunta = str_replace(' ', '', $full_name_pregunta);
            $path_foto_pregunta = "EvaluacionVisita/Respuestas/$photo_name_pregunta.png";
        }

        if ($foto_fachada != 'NO_FOTO') {
            $name_fachada = "$unique$usuario";
            $full_name_fachada = str_replace(str_split('\\/:*?"<>|%+#'), '', $name_fachada);
            $photo_name_fachada = str_replace(' ', '', $full_name_fachada);
            $path_foto_fachada = "EvaluacionVisita/Fachada/$photo_name_fachada.png";
        }

        // Insertar en base de datos
        $retorno = Funciones::insertEvaluacionVisita052025(
            $codigo,
            $rol,
            $cedi,
            $codigo_asesor,
            $asesor,
            $tipo_cliente,
            $promedio_venta,
            $unidades_minimas,
            $usuario,
            $ciudad,
            $cliente,
            $gestor_asignado,
            $categoria,
            $pregunta,
            $variante,
            $respuesta,
            $path_foto_pregunta,
            $comentario_pregunta,
            $path_foto_fachada,
            $calificacion,
            $satisfaccion,
            $comentario,
            $precio,
            $fecha,
            $hora,
            $canal
        );

        if ($retorno) {
            // Subir imágenes si es necesario
            if ($foto_pregunta != 'NO_FOTO') {
                $container = 'app/AppAlicorpSupervision/Inserts/EvaluacionVisita/Respuestas';
                uploadBlobSample($blobClient, $container, $foto_pregunta, $photo_name_pregunta.'.png');
            }
            if ($foto_fachada != 'NO_FOTO') {
                $container = 'app/AppAlicorpSupervision/Inserts/EvaluacionVisita/Fachada';
                uploadBlobSample($blobClient, $container, $foto_fachada, $photo_name_fachada.'.png');
            }
            array_push($results, array(
                'estado' => '1',
                'mensaje' => 'Creación exitosa',
                'ultimoId' => $retorno
            ));
        } else {
            array_push($results, array(
                'estado' => '2',
                'mensaje' => 'Creación fallida'
            ));
        }
    }

    // Enviar respuesta
    header('Content-Type: application/json');
    echo $isBatch ? json_encode($results) : json_encode($results[0]);
}
?>