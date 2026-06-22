<?php

/**
 * Representa el la estructura de las metas
 * almacenadas en la base de datos
 */
require 'Database.php';

class FuncionesSamsung
{
    function __construct(){}
    
    /*
    * REPOSITORIOS
    */
	//INICIO PRUEBA
	
	public static function getIdExhibidor(){
	        $consulta = "SELECT id_fotografico FROM insert_fotografico ORDER BY id_fotografico ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}

    public static function updateDeviceId($user, $device_id){
        // $consulta = "UPDATE repositorio_usuario SET device_id = ? WHERE user = ? AND device_id IS NULL";
        // try {
        //     // Preparar sentencia
        //     $comando = Database::getInstance()->getDb()->prepare($consulta);
        //     // Ejecutar sentencia preparada
        //     $comando->execute(array(
        //         $device_id,
        //         $user
        //     ));

        //     return true;

        // } catch (PDOException $e) {
        //     return false;
        // }

        return true;
    }

   

    public static function getTiempoAlmuerzo() {
        $consulta = "SELECT * FROM repositorio_tiempo_almuerzo";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();
            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { 
            return false;
        }
    }

    public static function existeDeviceId($device_id){
        $consulta = "SELECT * FROM repositorio_usuario WHERE device_id = ? AND device_id IS NOT NULL";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(
                array(
                    $device_id
                )
            );

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getLocales($operator){
        $consulta = "SELECT * FROM repositorio_locales_dtt WHERE user=? AND activar='SI'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $operator
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }


    public static function getCausalesProdMalEst($operator)
    {
        $consulta = "SELECT * FROM repositorio_causales_prod_mal_est";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getCausalesAsistencia()
    {
        $consulta = "SELECT * FROM repositorio_causales_asistencia";
        try {
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            $comando->execute();
            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function getCausalesAsistenciaAtraso()
    
    {
        $consulta = "SELECT * FROM repositorio_causales_asistencia_atraso";
        try {
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            $comando->execute();
            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }


    public static function getLocalesVentas($operator){
        $consulta = "SELECT * FROM repositorio_locales_ventas WHERE user=? AND activar='SI'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $operator
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getLocales072021($operator){
        $consulta = "SELECT id, region, customer_owner, tipo, pos_id, channel_segment, pos_name, pos_name_dpsm, zone, province, city, channel, subchannel, address, latitud, longitud, user, supervisor, format, segmentacion, compras FROM repositorio_locales_dtt WHERE user=? AND activar='SI'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $operator
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getLocalesRutero($operator)
    {
        $consulta = "SELECT * FROM lvi_ruta_semanal WHERE user=? AND activar='SI';";

        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $operator
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getPromociones($operator){
        $consulta = "SELECT * FROM repositorio_promociones";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getVersiones($operator){
        $consulta = "SELECT * FROM repositorio_versiones";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getRotacion($operator){
        $consulta = "SELECT * FROM repositorio_rotacion";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getEvidencias($id_pdv, $operator){
        $consulta = "SELECT * FROM repositorio_evidencias WHERE id_pdv = ? AND usuario = ?;";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $id_pdv,
                $operator
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getPopSugerido($operator){
        $consulta = "SELECT * FROM repositorio_pop";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

	public static function getJustificacion(){
        $consulta = "SELECT * FROM repositorio_justificacion";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    // public static function getProductosPVC($user, $categoria, $subcategoria, $marca){
    //     $consulta = "SELECT * FROM vi_productos_pvc WHERE usuario=? AND categoria=? AND subcategoria=? AND marca=?";
    //     try {
    //         // Preparar sentencia
    //         $comando = Database::getInstance()->getDb()->prepare($consulta);
    //         // Ejecutar sentencia preparada
    //         $comando->execute(array(
    //             $user,
    //             $categoria,
    //             $subcategoria,
    //             $marca
    //         ));

    //         return $comando->fetchAll(PDO::FETCH_ASSOC);

    //     } catch (PDOException $e) {
    //         return false;
    //     }
    // }

    public static function getProductosPVC($user){
        // $consulta = "SELECT * FROM vi_productos_pvc WHERE usuario=?";
        $consulta = "SELECT * FROM repositorio_precios_pvc  /* WHERE usuario=? */";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(
               /* array($user) */);

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getUltimoPvcPorUsuario($usuario, $codigo) {
    $consulta = "SELECT sku_code, promotional_price as pvc
                 FROM insert_precios
                 WHERE id_precios IN (
                     SELECT MAX(id_precios)
                     FROM insert_precios
                     WHERE usuario = ? AND codigo = ?
                     GROUP BY sku_code
                 )";
    try {
        $comando = Database::getInstance()->getDb()->prepare($consulta);
        $comando->execute(array($usuario, $codigo));
        return $comando->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
       
        return false;
    }
    }


    public static function getUltimoPrecioVentasPorUsuario($usuario, $codigo) {
    $consulta = "SELECT sku_code, regular_price as precio_unitario
                 FROM insert_ventas
                 WHERE id_ventas IN (
                     SELECT MAX(id_ventas)
                     FROM insert_ventas
                     WHERE usuario = ? AND codigo = ?
                     GROUP BY sku_code
                 )";
    try {
        $comando = Database::getInstance()->getDb()->prepare($consulta);
        $comando->execute(array($usuario, $codigo));
        return $comando->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
    }

    public static function getUltimoPvcAnteriorPromocionPorUsuario($usuario, $codigo) {
    $consulta = "SELECT sku, pvc_anterior
                 FROM insert_promociones
                 WHERE id_promociones IN (
                     SELECT MAX(id_promociones)
                     FROM insert_promociones
                     WHERE usuario = ? AND codigo = ?
                     GROUP BY sku
                 )";
    try {
        $comando = Database::getInstance()->getDb()->prepare($consulta);
        $comando->execute(array($usuario, $codigo));
        return $comando->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
    }

	
	public static function getComboCanjes($operator){
        $consulta = "SELECT * FROM repositorio_combos_canjes";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getCausalesMCI($operator){
        $consulta = "SELECT * FROM repositorio_causales_mci_pdv";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getMarcasBlancas($operator){
        $consulta = "SELECT * FROM repositorio_marcas_blancas";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getTipoPrecios($operator){
        $consulta = "SELECT * FROM repositorio_tipo_precios";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getTipoExhibiciones(){
        $consulta = "SELECT * FROM repositorio_tipo_exhibicion";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

	public static function getCausalesOSA($operator){
        $consulta = "SELECT * FROM repositorio_causales_osa";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getRangoPrecios($operator){
        $consulta = "SELECT * FROM repositorio_rango_precios";

        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getRangosPreciosSku($operator){
    $consulta = "SELECT * FROM repositorio_rango_precios_sku";

    try {
        $comando = Database::getInstance()->getDb()->prepare($consulta);
        $comando->execute();

        return $comando->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        return false;
    }
    }

	
	public static function getMaterialesAlertas($operator){
        $consulta = "SELECT * FROM repositorio_alertas";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    	
	public static function getPDI($operator){
        $consulta = "SELECT * FROM repositorio_pdi";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getPrioritarios($operator){
        $consulta = "(SELECT * FROM lvi_portafolio_prioritario WHERE user=?) UNION (SELECT 999999999, 'PRUEBA', 'PRUEBA', 'PRUEBA', 'PRUEBA', 'PRUEBA', 'PRUEBA', 'PRUEBA', 'PRUEBA' FROM lvi_portafolio_prioritario)";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            //$comando->execute();
            $comando->execute(array(
                $operator
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
		public static function getTareas($operator){
        $consulta = "SELECT * FROM repositorio_tareas";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getPreguntas($operator){
        $consulta = "SELECT * FROM repositorio_preguntas ORDER BY id ASC";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getPreguntasVentas($operator){
        $consulta = "SELECT * FROM repositorio_preguntas_ventas ORDER BY id ASC";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
    public static function getTests($operator)
    {
        $consulta = "SELECT * FROM repositorio_test";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getTestsVentas($operator)
    {
        $consulta = "SELECT * FROM repositorio_test_ventas";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

	public static function getProductos($operator){
        $consulta = "SELECT id, sector, categoria, subcategoria, segmento, presentacion, variante1, variante2, contenido, sku, marca, fabricante FROM repositorio_productos WHERE activar='SI'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
			$comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getProductos2019($operator){
        $consulta = "SELECT id, sector, categoria, subcategoria, segmento, presentacion, variante1, variante2, contenido, sku, marca, fabricante, pvp FROM repositorio_productos WHERE activar='SI'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
			$comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getProductos2020($operator){
        $consulta = "SELECT id, sector, categoria, subcategoria, segmento, presentacion, variante1, variante2, contenido, sku, marca, fabricante, pvp, cadenas FROM repositorio_productos WHERE activar='SI'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
			$comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
    public static function getProductos2021($operator){
        $consulta = "SELECT id, sector, categoria, subcategoria, segmento, presentacion, variante1, variante2, contenido, sku, marca, fabricante, pvp, cadenas, CONCAT('https://webecuador.azurewebsites.net/App/AppAlicorp/Web/', foto) AS foto, plataforma, locales, dolar FROM repositorio_productos WHERE activar='SI'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
			$comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getProductosVentas($operator){
        $consulta = "SELECT id, sector, categoria, subcategoria, segmento, presentacion, variante1, variante2, contenido, sku, marca, fabricante, pvp, cadenas, CONCAT('https://webecuador.azurewebsites.net/App/AppAlicorp/Web/', foto) AS foto FROM repositorio_productos_ventas";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
			$comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getProductosAASS($operator){
        $consulta = "SELECT * FROM repositorio_productos_aass";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
			$comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
	
	public static function getProductosMAYO($operator){
        $consulta = "SELECT * FROM repositorio_productos_mayo WHERE usuario=?";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
			$comando->execute(array(
                $operator
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * TEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEE
     */

    // public static function getLogisticoDiaAnterior($usuario, $categoria, $codigo_pdv, $fecha, $tipo_logistico = ''){
        
    //     // DEPURACIÓN: Log de parámetros
    //     error_log("getLogisticoDiaAnterior - Parámetros:");
    //     error_log("Usuario: " . $usuario);
    //     error_log("Categoría: " . $categoria);
    //     error_log("Código PDV: " . $codigo_pdv);
    //     error_log("Fecha: " . $fecha);
    //     error_log("Tipo Logístico: " . $tipo_logistico);
        
    //     // Construir consulta base
    //     $consulta = "SELECT 
    //                     sku_code, 
    //                     regular_price, 
    //                     causal, 
    //                     tipo_logistico, 
    //                     fecha_prod_caducado, 
    //                     fecha_prod_propenso,
    //                     fecha,
    //                     categoria,
    //                     subcategoria,
    //                     brand
    //                 FROM insert_logistico 
    //                 WHERE usuario = ? 
    //                 AND categoria = ? 
    //                 AND fecha = ? 
    //                 AND codigo = ?";
        
    //     // Preparar parámetros
    //     $parametros = [$usuario, $categoria, $fecha, $codigo_pdv];
        
    //     // Agregar filtro por tipo_logistico si se proporciona
    //     if (!empty($tipo_logistico) && $tipo_logistico != '') {
    //         $consulta .= " AND tipo_logistico = ?";
    //         $parametros[] = $tipo_logistico;
    //     }
        
    //     $consulta .= " ORDER BY sku_code";
        
    //     // DEPURACIÓN: Log de consulta SQL
    //     error_log("Consulta SQL: " . $consulta);
    //     error_log("Parámetros SQL: " . print_r($parametros, true));
        
    //     try {
    //         // Preparar sentencia
    //         $comando = Database::getInstance()->getDb()->prepare($consulta);
            
    //         // Ejecutar sentencia preparada
    //         $resultado = $comando->execute($parametros);
            
    //         if (!$resultado) {
    //             error_log("Error en execute: " . print_r($comando->errorInfo(), true));
    //             return false;
    //         }
            
    //         $filas = $comando->fetchAll(PDO::FETCH_ASSOC);
            
    //         // DEPURACIÓN: Log de resultados
    //         error_log("Número de registros encontrados: " . count($filas));
    //         if (count($filas) > 0) {
    //             error_log("Primer registro: " . print_r($filas[0], true));
    //         }
            
    //         return $filas;

    //     } catch (PDOException $e) {
    //         error_log("Error en getLogisticoDiaAnterior: " . $e->getMessage());
    //         error_log("Trace: " . $e->getTraceAsString());
    //         return false;
    //     }
    // }
	
    
    #region RESPALDO ANTES DE ULTIMO RELEVO (CAMBIO DE DIA ACTUAL A ULTIMO RELEVO)
    // public static function getUltimoRelevoLogistico($usuario, $categoria, $codigo_pdv, $fecha, $tipo_logistico = ''){
    

    //     // error_log("=== getUltimoRelevoLogistico - Parámetros ===");
    //     // error_log("Usuario: " . $usuario);
    //     // error_log("Categoría: " . $categoria);
    //     // error_log("Código PDV: " . $codigo_pdv);
    //     // error_log("Fecha: " . $fecha);
    //     // error_log("Tipo Logístico: " . $tipo_logistico);
        
    //     $subconsulta = "SELECT MAX(fecha) as ultima_fecha
    //                     FROM insert_logistico 
    //                     WHERE usuario = ? 
    //                     AND categoria = ?
    //                     AND fecha = ? 
    //                     AND codigo = ?";
        
    //     $subparametros = [$usuario, $categoria, $fecha, $codigo_pdv];
        

    //     if (!empty($tipo_logistico) && $tipo_logistico != '') {
    //         $subconsulta .= " AND tipo_logistico = ?";
    //         $subparametros[] = $tipo_logistico;
    //     }
        
    //     try {
    //         $subcomando = Database::getInstance()->getDb()->prepare($subconsulta);
    //         $subcomando->execute($subparametros);
    //         $resultado_sub = $subcomando->fetch(PDO::FETCH_ASSOC);
            
    //         $ultima_fecha = $resultado_sub['ultima_fecha'];
            
    //         error_log("Fecha del último relevo encontrada: " . ($ultima_fecha ? $ultima_fecha : 'SIN REGISTROS'));
            
    //         if (!$ultima_fecha) {
    //             error_log("No se encontró ningún relevo previo para estos criterios");
    //             return [];
    //         }
  
    //         $consulta = "SELECT 
    //                         sku_code, 
    //                         regular_price, 
    //                         causal, 
    //                         tipo_logistico, 
    //                         fecha_prod_caducado, 
    //                         fecha_prod_propenso,
    //                         fecha,
    //                         categoria,
    //                         subcategoria,
    //                         brand
    //                     FROM insert_logistico 
    //                     WHERE usuario = ? 
    //                     AND categoria = ? 
    //                     AND fecha = ? 
    //                     AND codigo = ?";
            

    //         $parametros = [$usuario, $categoria, $ultima_fecha, $codigo_pdv];
            

    //         if (!empty($tipo_logistico) && $tipo_logistico != '') {
    //             $consulta .= " AND tipo_logistico = ?";
    //             $parametros[] = $tipo_logistico;
    //         }
            
    //         $consulta .= " ORDER BY sku_code";
            

    //         // error_log("=== Consulta Principal ===");
    //         // error_log("Consulta SQL: " . $consulta);
    //         // error_log("Parámetros SQL: " . print_r($parametros, true));
            

    //         $comando = Database::getInstance()->getDb()->prepare($consulta);
    //         $resultado = $comando->execute($parametros);
            
    //         if (!$resultado) {
    //             error_log("Error en execute: " . print_r($comando->errorInfo(), true));
    //             return false;
    //         }
            
    //         $filas = $comando->fetchAll(PDO::FETCH_ASSOC);
            
          
    //         error_log("Número de registros encontrados en último relevo: " . count($filas));
    //         if (count($filas) > 0) {
    //             error_log("Fecha del relevo: " . $filas[0]['fecha']);
    //             error_log("Primer registro: " . print_r($filas[0], true));
    //         }
            
    //         return $filas;

    //     } catch (PDOException $e) {
    //         error_log("Error en getUltimoRelevoLogistico: " . $e->getMessage());
    //         error_log("Trace: " . $e->getTraceAsString());
    //         return false;
    //     }
    // }

    #endregion

    // public static function getFechaUltimoRelevo($usuario, $codigo_pdv, $tipo_logistico = '') {
    //     $hoy = date('d/m/Y');
    //     $ayer = date('Y-m-d', strtotime('-1 day'));
        
    //     $consulta = "SELECT MAX(fecha) as ultima_fecha
    //                 FROM insert_logistico 
    //                 WHERE usuario = ? 
    //                 AND codigo = ?
    //                 AND STR_TO_DATE(fecha, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')";
        

    //     // $consulta = "SELECT fecha as ultima_fecha
    //     //     FROM insert_logistico 
    //     //     WHERE usuario = ? 
    //     //     AND codigo = ?
    //     //     AND STR_TO_DATE(fecha, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')
    //     //     ORDER BY STR_TO_DATE(fecha, '%d/%m/%Y') DESC
    //     //     LIMIT 1";  
        
    //     $parametros = [$usuario, $codigo_pdv, $hoy];
        
    //     if (!empty($tipo_logistico) && $tipo_logistico != '') {
    //         $consulta .= " AND tipo_logistico = ?";
    //         $parametros[] = $tipo_logistico;
    //     }
        
    //     try {
    //         $comando = Database::getInstance()->getDb()->prepare($consulta);
    //         $comando->execute($parametros);
    //         $resultado = $comando->fetch(PDO::FETCH_ASSOC);
    //         if ($resultado && $resultado['ultima_fecha']) {
    //             return $resultado['ultima_fecha'];
    //         }


    //     } catch (PDOException $e) {
    //         error_log("Error: " . $e->getMessage());
    //         return null;
    //     }
    // }

    public static function getFechaUltimoRelevo($usuario, $codigo_pdv, $tipo_logistico = '') {
        $hoy = date('d/m/Y');
        
        $consulta = "SELECT fecha as ultima_fecha
                    FROM insert_logistico 
                    WHERE usuario = ? 
                    AND codigo = ?
                    AND STR_TO_DATE(fecha, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')";
        
        $parametros = [$usuario, $codigo_pdv, $hoy];
        
        if (!empty($tipo_logistico)) {
            $consulta .= " AND tipo_logistico = ?";
            $parametros[] = $tipo_logistico;
        }
        
        $consulta .= " ORDER BY STR_TO_DATE(fecha, '%d/%m/%Y') DESC LIMIT 1";
        
        try {
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            $comando->execute($parametros);
            $resultado = $comando->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado && !empty($resultado['ultima_fecha'])) {
                return $resultado['ultima_fecha'];
            }
            return null;
            
        } catch (PDOException $e) {
            error_log("Error en getFechaUltimoRelevo: " . $e->getMessage());
            return null;
        }
    }

    public static function getRegistrosPorFecha($usuario, $categoria, $codigo_pdv, $fecha, $tipo_logistico = '') {
        $consulta = "SELECT * FROM insert_logistico 
                    WHERE usuario = ? 
                    AND categoria = ? 
                    AND codigo = ? 
                    AND fecha = ?";
        
        $parametros = [$usuario, $categoria, $codigo_pdv, $fecha];
        
        if (!empty($tipo_logistico) && $tipo_logistico != '') {
            $consulta .= " AND tipo_logistico = ?";
            $parametros[] = $tipo_logistico;
        }
        
        $consulta .= " ORDER BY sku_code";
        
        try {
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            $comando->execute($parametros);
            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error: " . $e->getMessage());
            return false;
        }
    }


	//FIN PRUEBA
    
    public static function getPunto($operator){
        $consulta = "SELECT * FROM base_pharma_value WHERE usuario=? and status='1'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $operator
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function getFlooring($operator){
        $consulta = "SELECT * FROM base_portafolio_productos WHERE status='1'";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
          $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function getPrecios(){
        $consulta = "SELECT * FROM epson_precios_actuales";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute();

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function insertAgotados(
		$pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $tiempo_inicio,
        $tiempo_fin,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_tiempo_gestion( " .
        "pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "tiempo_inicio," .
        "tiempo_fin," .
        "fecha," .
        "hora)" .
            " VALUES( ?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
	      $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $tiempo_inicio,
        $tiempo_fin,
        $fecha,
        $hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertFotografico(
		$pharma_id,
        $codigo,
        $usuario,
        $categoria,
        $subcategoria,
        $marca,
        $logro,
		$path,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_fotografico( " .
            "pharma_id," .
			" codigo,".
            " usuario," .
            " categoria," .
            " subcategoria," .
            " marca," .
            " logro," .
			" foto," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$pharma_id,
			$codigo,
			$usuario,
			$categoria,
			$subcategoria,
			$marca,
			$logro,
			$path,
			$fecha,
			$hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertAgotados2018(
		$pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $tiempo_inicio,
        $tiempo_fin,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_tiempo_gestion( " .
        "pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "tiempo_inicio," .
        "tiempo_fin," .
        "fecha," .
        "hora)" .
            " VALUES( ?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
	      $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $tiempo_inicio,
        $tiempo_fin,
        $fecha,
        $hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function insertExhibicion2018(
       $pharma_id,
	   $codigo,
	   $usuario,
	   $supervisor,
	   $fecha,
	   $hora,
	   $sector,
	   $categoria,
	   $subcategoria,
	   $segmento,
	   $brand,
	   $tipo_exh,
	   $zona_exh,
	   $contratada,
	   $condicion,
	   $foto
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_exhibiciones( " .
		" pharma_id," .
	    " codigo," .
	    " usuario," .
	    " supervisor," .
	    " fecha," .
	    " hora," .
	    " sector," .
	    " categoria," .
		" subcategoria," .
		" segmento," .
	    " brand," .
	    " tipo_exh," .
	    " zona_exh," .
	    " contratada," .
	    " condicion," .
	    " foto)" .
		" VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array( 
			$pharma_id,
			$codigo,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$subcategoria,
			$segmento,
			$brand,
			$tipo_exh,
			$zona_exh,
			$contratada,
			$condicion,
			$foto
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function insertExhibicion022023(
       $pharma_id,
	   $codigo,
	   $usuario,
	   $supervisor,
	   $fecha,
	   $hora,
	   $sector,
	   $categoria,
	   $subcategoria,
	   $segmento,
	   $brand,
	   $tipo_exh,
	   $zona_exh,
	   $contratada,
	   $condicion,
	   $foto,
       $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_exhibiciones( " .
		" pharma_id," .
	    " codigo," .
	    " usuario," .
	    " supervisor," .
	    " fecha," .
	    " hora," .
	    " sector," .
	    " categoria," .
		" subcategoria," .
		" segmento," .
	    " brand," .
	    " tipo_exh," .
	    " zona_exh," .
	    " contratada," .
	    " condicion," .
	    " foto," .
	    " plataforma)" .
		" VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array( 
			$pharma_id,
			$codigo,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$subcategoria,
			$segmento,
			$brand,
			$tipo_exh,
			$zona_exh,
			$contratada,
			$condicion,
			$foto,
            $plataforma
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertExhibicion032023(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $segmento,
        $brand,
        $tipo_exh,
        $zona_exh,
        $nivel,
        $tipo,
        $contratada,
        $condicion,
        $foto,
        $plataforma
     )
     {
     $conexion= Database::getInstance()->getDb();
         // Sentencia INSERT
         $comando = "INSERT INTO insert_exhibiciones( " .
         " pharma_id," .
         " codigo," .
         " usuario," .
         " supervisor," .
         " fecha," .
         " hora," .
         " sector," .
         " categoria," .
         " subcategoria," .
         " segmento," .
         " brand," .
         " tipo_exh," .
         " zona_exh," .
         " nivel," .
         " tipo," .
         " contratada," .
         " condicion," .
         " foto," .
         " plataforma)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
 
         // Preparar la sentencia
         $sentencia = $conexion->prepare($comando);
 
         $sentencia->execute(
             array( 
             $pharma_id,
             $codigo,
             $usuario,
             $supervisor,
             $fecha,
             $hora,
             $sector,
             $categoria,
             $subcategoria,
             $segmento,
             $brand,
             $tipo_exh,
             $zona_exh,
             $nivel,
             $tipo,
             $contratada,
             $condicion,
             $foto,
             $plataforma
         )
         );
         
         return $pdo = $conexion->lastInsertId();
     }
	
	public static function insertExhibicion(
       $id,
	   $pos_id,
	   $usuario,
	   $supervisor,
	   $fecha,
	   $hora,
	   $sector,
	   $categoria,
	   $brand,
	   $tipo_exh,
	   $zona_exh,
	   $contratada,
	   $condicion,
	   $foto
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_exhibiciones( " .
		" pharma_id," .
	    " codigo," .
	    " usuario," .
	    " supervisor," .
	    " fecha," .
	    " hora," .
	    " sector," .
	    " categoria," .
	    " brand," .
	    " tipo_exh," .
	    " zona_exh," .
	    " contratada," .
	    " condicion," .
	    " foto)" .
		" VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array( 
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$brand,
			$tipo_exh,
			$zona_exh,
			$contratada,
			$condicion,
			$foto
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	

    


    public static function insertAlmuerzo04042025(
        $usuario,
        $jornada_laboral,
        $tiempo_almuerzo,
        $tiempo_fuera,
        $foto,
        $latitud,
        $longitud,
        $fecha,
        $hora_ini_almuerzo,
        $hora_fin_almuerzo
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_almuerzo( " .
            "usuario," .
            "jornada_laboral," .
            "tiempo_almuerzo," .
            "tiempo_fuera," .
            "foto," .
            "latitud," .
            "longitud," .
            "fecha," .
            "hora_ini_almuerzo," .
            "hora_fin_almuerzo)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $usuario,
                $jornada_laboral,
                $tiempo_almuerzo,
                $tiempo_fuera,
                $foto,
                $latitud,
                $longitud,
                $fecha,
                $hora_ini_almuerzo,
                $hora_fin_almuerzo
            )
        );

        return $pdo = $conexion->lastInsertId();
    }







	/*PREGUNTAS*/
	public static function insertPreguntas(
        $usuario,
        $p1,
		$p2,
		$p3,
		$p4,
		$p5,
		$p6,
		$p7,
		$p8,
		$p9,
		$p10,
		$p11,
		$p12,
		$p13,
		$p14,
		$p15,
        $correctas,
        $incorrectas,
        $calificacion,
        $observacion,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_preguntas( " .
            "usuario," .
			" p1," .
			" p2," .
			" p3," .
			" p4," .
			" p5," .
			" p6," .
			" p7," .
			" p8," .
			" p9," .
			" p10," .
			" p11," .
			" p12," .
			" p13," .
			" p14," .
			" p15," .
			" correctas," .
			" incorrectas," .
			" calificacion," .
			" observacion," .
			" fecha," .
			" hora)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
	        $usuario,
			$p1,
			$p2,
			$p3,
			$p4,
			$p5,
			$p6,
			$p7,
			$p8,
			$p9,
			$p10,
			$p11,
			$p12,
			$p13,
			$p14,
			$p15,
			$correctas,
			$incorrectas,
			$calificacion,
			$observacion,
			$fecha,
			$hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
        
    public static function insertImplementacion(
        $usuario,
        $fecha,
        $hora,
        $ciudad,
        $canal,
        $cliente,
        $formato,
        $zona,
        $pdv,
        $direccion,
		$local,
        $latitud,
        $longitud,
        $foto
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_nuevo_pdv( " .
		"usuario," .
        "fecha," .
        "hora," .
        "ciudad," .
        "canal," .
        "cliente," .
        "formato," .
        "zona," .
        "pdv," .
        "direccion," .
		"local," .
        "latitud," .
        "longitud," .
        "foto)" .
        " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array( 
       $usuario,
        $fecha,
        $hora,
        $ciudad,
        $canal,
        $cliente,
        $formato,
        $zona,
        $pdv,
        $direccion,
		$local,
        $latitud,
        $longitud,
        $foto
		
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function insertInicial(
        $id_pdv,
        $codigo,
        $tipo,
        $establecimiento,
        $telefono,
        $direccion,
        $latitude,
        $longitude,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_inicial( " .
            " id_pdv," .
            "codigo," .
            "tipo," .
            "establecimiento," .
            " telefono," .
            " direccion," .
            " latitude," .
            " longitude," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
              $id_pdv,
        $codigo,
        $tipo,
        $establecimiento,
        $telefono,
        $direccion,
        $latitude,
        $longitude,
        $fecha,
        $hora)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function insertInicial2018(
        $id_pdv,
        $codigo,
        $tipo,
        $establecimiento,
        $telefono,
        $direccion,
        $latitude,
        $longitude,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_inicial( " .
            " id_pdv," .
            " codigo," .
            " tipo," .
            " establecimiento," .
            " telefono," .
            " direccion," .
            " latitude," .
            " longitude," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
              $id_pdv,
        $codigo,
        $tipo,
        $establecimiento,
        $telefono,
        $direccion,
        $latitude,
        $longitude,
        $fecha,
        $hora)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
        
    public static function insertInventario(
	    $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $segment2,
        $brand,
        $tamano,
        $cantidad,
        $sku_code,
        $inventarios
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_inventario( " .
		" pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
        "segment1," .
        "segment2," .
        "brand," .
        "tamano," .
        "cantidad," .
        "sku_code," .
        " inventarios)" .
        " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $segment2,
        $brand,
        $tamano,
        $cantidad,
        $sku_code,
        $inventarios)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function insertInventario2018(
	    $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $presentacion,
        $brand,
        $contenido,
        $sku_code,
        $inventarios,
		$sugeridos,
		$causal,
		$otros,
		$caducidad
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_inventario( " .
		" pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
        "subcategoria," .
        "presentacion," .
        "brand," .
        "contenido," .
        "sku_code," .
        "inventarios," .
        "sugeridos," .
        "causal," .
        "otros," .
        " caducidad)" .
        " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$contenido,
			$sku_code,
			$inventarios,
			$sugeridos,
			$causal,
			$otros,
			$caducidad
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }




    public static function insertPropensosYProductosMalEst012025(
        $pharma_id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $presentacion,
        $brand,
        $contenido,
        $sku_code,
        $inventarios,
        $souvenirs,
        $tipo_conteo_total,
        $total,
        $tipo_conteo_defectuosas,
        $cantidad_defectuosas,
        $fecha_caducidad_total,
        $fecha_caducidad,
        $causal,
        $path,
        $plataforma,
        $modulo
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_propensos_y_prod_mal_est( " .
            " pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "sector," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "contenido," .
            "sku_code," .
            "inventarios," .
            "souvenirs," .
            "tipo_conteo_total," .
            "total," .
            "tipo_conteo_defectuosas," .
            "cantidad_defectuosas," .
            "fecha_caducidad_total," .
            "fecha_caducidad," .
            "causal," .
            "foto," .
            "plataforma," .
            "modulo)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $pharma_id,
                $pos_id,
                $usuario,
                $supervisor,
                $fecha,
                $hora,
                $sector,
                $categoria,
                $subcategoria,
                $presentacion,
                $brand,
                $contenido,
                $sku_code,
                $inventarios,
                $souvenirs,
                $tipo_conteo_total,
                $total,
                $tipo_conteo_defectuosas,
                $cantidad_defectuosas,
                $fecha_caducidad_total,
                $fecha_caducidad,
                $causal,
                $path,
                $plataforma,
                $modulo
            )
        );

        return $pdo = $conexion->lastInsertId();
    }





     public static function insertPropensosYProductosMalEst072025(
        $pharma_id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $presentacion,
        $brand,
        $contenido,
        $sku_code,
        $sku_cliente,
        $inventarios,
        $souvenirs,
        $tipo_conteo_total,
        $total,
        $tipo_conteo_defectuosas,
        $cantidad_defectuosas,
        $fecha_caducidad_total,
        $fecha_caducidad,
        $causal,
        $path,
        $plataforma,
        $modulo,
        $litros,
        $dias_restantes,
        $valorizado
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_propensos_y_prod_mal_est( " .
            " pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "sector," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "contenido," .
            "sku_code," .
            "sku_cliente," .
            "inventarios," .
            "souvenirs," .
            "tipo_conteo_total," .
            "total," .
            "tipo_conteo_defectuosas," .
            "cantidad_defectuosas," .
            "fecha_caducidad_total," .
            "fecha_caducidad," .
            "causal," .
            "foto," .
            "plataforma," .
            "modulo," .
            "litros," .
            "dias_restantes," .
            "valorizado)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $pharma_id,
                $pos_id,
                $usuario,
                $supervisor,
                $fecha,
                $hora,
                $sector,
                $categoria,
                $subcategoria,
                $presentacion,
                $brand,
                $contenido,
                $sku_code,
                $sku_cliente,
                $inventarios,
                $souvenirs,
                $tipo_conteo_total,
                $total,
                $tipo_conteo_defectuosas,
                $cantidad_defectuosas,
                $fecha_caducidad_total,
                $fecha_caducidad,
                $causal,
                $path,
                $plataforma,
                $modulo,
                $litros,
                $dias_restantes,
                $valorizado
            )
        );

        return $pdo = $conexion->lastInsertId();
    }






    public static function insertVentas012025(
        $id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $fecha_venta,
        $categoria,
        $subcategoria,
        $presentacion,
        $marca,
        $sku_code,
        $tipo_venta,
        $stock_inicial,
        $cantidad,
        $regular_price,
        $promotional_price,
        $ofert_price,
        $stock_final,
        $manufacturer,
        $pos_name,
        $foto
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_ventas( " .
            "pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "fecha_venta," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "marca," .
            "sku_code," .
            "tipo_venta," .
            "stock_inicial," .
            "cantidad," .
            "regular_price," .
            "promotional_price," .
            "ofert_price," .
            "stock_final," .
            "manufacturer," .
            "pos_name," .
            "foto)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $id,
                $pos_id,
                $user,
                $supervisor,
                $fecha,
                $hora,
                $fecha_venta,
                $categoria,
                $subcategoria,
                $presentacion,
                $marca,
                $sku_code,
                $tipo_venta,
                $stock_inicial,
                $cantidad,
                $regular_price,
                $promotional_price,
                $ofert_price,
                $stock_final,
                $manufacturer,
                $pos_name,
                $foto
            )
        );

        return $pdo = $conexion->lastInsertId();
    }









    
    public static function insertInventario022023(
	    $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $presentacion,
        $brand,
        $contenido,
        $sku_code,
        $inventarios,
		$sugeridos,
		$causal,
		$otros,
		$caducidad,
		$plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_inventario( " .
		" pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
        "subcategoria," .
        "presentacion," .
        "brand," .
        "contenido," .
        "sku_code," .
        "inventarios," .
        "sugeridos," .
        "causal," .
        "otros," .
        "caducidad," .
        "plataforma)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$contenido,
			$sku_code,
			$inventarios,
			$sugeridos,
			$causal,
			$otros,
			$caducidad,
            $plataforma
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertInventario042023(
	    $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $presentacion,
        $brand,
        $contenido,
        $sku_code,
        $inventarios,
		$sugeridos,
		$semana,
		$causal,
		$otros,
		$caducidad,
		$plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_inventario( " .
		" pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
        "subcategoria," .
        "presentacion," .
        "brand," .
        "contenido," .
        "sku_code," .
        "inventarios," .
        "sugeridos," .
        "semana," .
        "causal," .
        "otros," .
        "caducidad," .
        "plataforma)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$contenido,
			$sku_code,
			$inventarios,
			$sugeridos,
			$semana,
			$causal,
			$otros,
			$caducidad,
            $plataforma
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertInventario012025(
	    $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $presentacion,
        $brand,
        $contenido,
        $sku_code,
        $inventarios,
        $semana,
		$sugeridos,
        $tipo_unidades,
        $entrega,
		$causal,
		$otros,
		$caducidad,
		$plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_inventario( " .
		" pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
        "subcategoria," .
        "presentacion," .
        "brand," .
        "contenido," .
        "sku_code," .
        "inventarios," .
        "semana," .
        "sugeridos," .
        "tipo_unidades," .
        "entrega," .
        "causal," .
        "otros," .
        "caducidad," .
        "plataforma)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$contenido,
			$sku_code,
			$inventarios,
            $semana,
			$sugeridos,
            $tipo_unidades,
            $entrega,
			$causal,
			$otros,
			$caducidad,
            $plataforma
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function insertPrecios(
        $id_pdv,
        $marca,
        $categoria,
        $subcategoria,
        $producto,
        $cantidad,
        $precio_contado,
        $precio_credito,
        $cuota,
        $valor_cuota,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_precios( " .
            " id_pdv," .
            " marca," .
            " categoria," .
            " subcategoria," .
            " producto," .
            " cantidad," .
            " precio_contado," .
            " precio_credito," .
            " cuota," .
            " valor_cuota," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
            $id_pdv,
        $marca,
        $categoria,
        $subcategoria,
        $producto,
        $cantidad,
        $precio_contado,
        $precio_credito,
        $cuota,
        $valor_cuota,
        $fecha,
        $hora)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
	public static function insertCodificados(
		$pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $segment2,
        $brand,
        $tamano,
        $cantidad,
        $sku_code,
        $codifica,
        $ausencia,
        $responsable,
        $razones
	)
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_codificados_osa( " .
		" pharma_id," .
        " codigo," .
        " usuario," .
        " supervisor," .
        " fecha," .
        " hora," .
        " sector," .
        " categoria," .
        " segment1," .
        " segment2," .
        " brand," .
        " tamano," .
        " cantidad," .
        " sku_code," .
        " codifica," .
        " ausencia," .
        " responsable," .
        " razones)" .
       " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
           $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $segment2,
        $brand,
        $tamano,
        $cantidad,
        $sku_code,
        $codifica,
        $ausencia,
        $responsable,
        $razones
         )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertCodificados2018(
		$id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $segment2,
        $brand,
        $tamano,
        $cantidad,
        $sku_code,
        $codifica,
        $ausencia,
        $responsable,
        $razones
	)
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_codificados_osa( " .
		" pharma_id," .
        " codigo," .
        " usuario," .
        " supervisor," .
        " fecha," .
        " hora," .
        " sector," .
        " categoria," .
        " segment1," .
        " segment2," .
        " brand," .
        " tamano," .
        " cantidad," .
        " sku_code," .
        " codifica," .
        " ausencia," .
        " responsable," .
        " razones)" .
       " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$user,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segment1,
			$segment2,
			$brand,
			$tamano,
			$cantidad,
			$sku_code,
			$codifica,
			$ausencia,
			$responsable,
			$razones
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertPromocion(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $brand,
        $sku,
        $tipo_promocion,
        $vigencia,
        $mecanica,
        $precio_promocional,
        $foto,
        $observaciones
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_promociones( " .
		
		" pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
         "fecha," .
         "hora," .
         "sector," .
         "categoria," .
         "brand," .
         "sku," .
         "tipo_promocion," .
         "vigencia," .
         "mecanica," .
         "precio_promocional," .
         "foto," .
         "observaciones)" .
	
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
       $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $brand,
        $sku,
        $tipo_promocion,
        $vigencia,
        $mecanica,
        $precio_promocional,
        $foto,
        $observaciones
		
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
	public static function insertPromocion2018(
        $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $brand,
        $sku,
        $tipo_promocion,
        $vigencia,
        $mecanica,
        $mecanica_generalizada,
        $foto,
        $observaciones
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_promociones( " .
		
		" pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
         "fecha," .
         "hora," .
         "sector," .
         "categoria," .
         "brand," .
         "sku," .
         "tipo_promocion," .
         "vigencia," .
         "mecanica," .
         "mecanica_generalizada," .
         "foto," .
         "observaciones)" .
	
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$brand,
			$sku,
			$tipo_promocion,
			$vigencia,
			$mecanica,
			$mecanica_generalizada,
			$foto,
			$observaciones

		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
	public static function insertRegistro(
        $id_pdv,
        $usuario,
        $tipo,
        $latitude,
        $longitude,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_registro( " .
            "id_pdv," .
            " usuario,".
            " tipo,".
            " latitude," .
            " longitude," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id_pdv,
			$usuario,
			$tipo,
			$latitude,
			$longitude,
			$fecha,
			$hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertRegistro2019(
        $id_pdv,
        $usuario,
        $tipo,
		$causal,
        $latitude,
        $longitude,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_registro( " .
            "id_pdv," .
            " usuario,".
            " tipo,".
			" causal,".
            " latitude," .
            " longitude," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id_pdv,
			$usuario,
			$tipo,
			$causal,
			$latitude,
			$longitude,
			$fecha,
			$hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertRegistro06_2019(
        $id_pdv,
        $usuario,
        $tipo,
        $version,
		$causal,
        $latitude,
        $longitude,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_registro( " .
            "id_pdv," .
            " usuario,".
            " tipo,".
            " version,".
			" causal,".
            " latitude," .
            " longitude," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id_pdv,
			$usuario,
			$tipo,
			$version,
			$causal,
			$latitude,
			$longitude,
			$fecha,
			$hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertRegistroVentas12_2022(
        $id_pdv,
        $usuario,
        $tipo,
        $version,
		$causal,
        $foto,
        $latitude,
        $longitude,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_registro_ventas( " .
            "id_pdv," .
            " usuario,".
            " tipo,".
            " version,".
			" causal,".
			" foto,".
            " latitude," .
            " longitude," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id_pdv,
			$usuario,
			$tipo,
			$version,
			$causal,
            $foto,
			$latitude,
			$longitude,
			$fecha,
			$hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertRegistro05_2022(
        $id_pdv,
        $idRemotaRuta,
        $usuario,
        $tipo,
        $version,
		$causal,
        $foto,
        $latitude,
        $longitude,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_registro( " .
            "id_pdv," .
            "id_ruta," .
            " usuario,".
            " tipo,".
            " version,".
			" causal,".
			" foto,".
            " latitude," .
            " longitude," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id_pdv,
            $idRemotaRuta,
			$usuario,
			$tipo,
			$version,
			$causal,
            $foto,
			$latitude,
			$longitude,
			$fecha,
			$hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertRegistroAsistencia(
        $id_pdv, 
        $usuario, 
        $version, 
        $path, 
        $latitude, 
        $longitude, 
        $fecha, 
        $hora, 
        $distancia, 
        $pos_name, 
        $bateria, 
        $estado_asistencia,
        $supervisor
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_registro_asistencia( " .
            "id_pdv, " .  
            "usuario, " .  
            "version, " .  
            "foto, " .  
            "latitude, " .  
            "longitude, " .  
            "fecha, " .  
            "hora, " .  
            "distancia, " .  
            "pos_name, " .  
            "bateria, " .  
            "estado_asistencia, " . 
            "supervisor) " . 
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $id_pdv, 
                $usuario, 
                $version, 
                $path, 
                $latitude, 
                $longitude, 
                $fecha, 
                $hora, 
                $distancia, 
                $pos_name, 
                $bateria, 
                $estado_asistencia,
                $supervisor
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function updateRuta(
        $id_ruta,
        $tipo,
        $hora, 
        $distancia, 
        $tipo_relevo,
        $id_sustento,
        $causal
    )
    {
        $comando = "";
        $conexion= Database::getInstance()->getDb();
        
        if(stristr($causal, "VERIFICAR ENTRADA:") !== false){
            $tipo = "ENTRADA";
        } 
        if (stristr($causal, "VERIFICAR SALIDA:") !== false){
            $tipo = "SALIDA";
        }
        
        // Sentencia INSERT
        if ($tipo==='ENTRADA' || $tipo==='ENTRADA TARDIA') {
            $comando = "UPDATE rutero_pdv SET " .
            " hora_inicio_visita=?, " .
            " distancia=?, " .
            " tipo_relevo=?, " .
            " id_estado=(CASE WHEN id_estado=1 THEN 2 ELSE id_estado END) " .
            " WHERE id=?";
            
            // Preparar la sentencia
            $sentencia = $conexion->prepare($comando);
            $sentencia->execute(
                array(
                    $hora,
                    $distancia, 
                    $tipo_relevo,
                    $id_ruta
                )
            );
        } else if ($tipo==='SALIDA') {
            $comando = "UPDATE rutero_pdv SET " .
            " hora_fin_visita=?, " .
            " distancia_salida=?, " .
            " id_estado=3 " .
            " WHERE id=?";

			// Preparar la sentencia
			$sentencia = $conexion->prepare($comando);
			$sentencia->execute(
				array(
					$hora,
					$distancia,
					$id_ruta
				)
			);
        } else if ($tipo==='JUSTIFICACION') {
            $comando = "UPDATE rutero_pdv SET " .
            " hora_inicio_visita=?, " .
            " hora_fin_visita=?, " .
            " distancia=?, " .
            " distancia_salida=?, " .
            //" id_sustento=?, " .
            " id_estado=5 " .
            " WHERE id=?";

            // Preparar la sentencia
            $sentencia = $conexion->prepare($comando);
            $sentencia->execute(
                array(
                    $hora,
                    $hora,
                    $distancia,
                    $distancia,
                    //$id_sustento,
                    $id_ruta
                )
            );
        }
        
        return $id_ruta;
    }

    public static function insertSustento(
        $newId,
        $id_ruta, 
        $fecha, 
        $causal, 
        $path
    )
    {
        $newDate = date('Y-m-d', strtotime(str_replace('/', '-', $fecha)));
        $newPath = 'AppAlicorp/Inserts/' . $path;

        $comando = "";
        $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        
        $comando = "INSERT INTO sustentacion_validacion " . 
            " (id_sustento, id_rutero, gestor, pdv, ciudad, fecha_incidencia, sustentador, fecha_sustentacion, motivo, canal, observacion_gestor, visita, efectivo, ruta_archivo, validado, comentario_validador) " .
            " SELECT ?, id, user, pos_name, city, ?, user, ?, 'JUSTIFICACION DESDE APP', channel, ?, 'NO', 'NO', ?, 'NO', '' FROM lvi_rutero WHERE id=?";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
                $newId,
                $newDate,
                $newDate,
                $causal,
                $newPath,
                $id_ruta
            )
        );
        
        return $newId;
    }

    public static function codAleatorio($length = 5) {
        return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
    }
    
    public static function insertShare(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_share_shelf( " .
		
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
        "marca_seleccionada," .
        "brand," .
        "ctms_percha," .
        "ctms_marca," .
        "otros)" .
        " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
		
	public static function insertShare2018(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_share_shelf( " .
		
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
        "marca_seleccionada," .
        "brand," .
        "ctms_percha," .
        "ctms_marca," .
        "otros)" .
        " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertVenta(
        $pharma_id,
		$codigo,
        $usuario,
        $supervisor,
		$tipo_factura,
		$num_factura,
		$monto_factura,
		$fecha_venta,
		$foto,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_venta( " .
		 " pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
		 "tipo_factura," .
         "num_factura," .
         "monto_factura," .
		 "fecha_venta," .
		 "foto," .
         "fecha," .
         "hora)" .
         
         " VALUES(?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
		$pharma_id,
		$codigo,
        $usuario,
        $supervisor,
		$tipo_factura,
		$num_factura,
		$monto_factura,
		$fecha_venta,
		$foto,
        $fecha,
        $hora
		
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function insertVentas(
        $id_pdv,
        $fecha_actividad,
        $categoria,
        $subcategoria,
        $marca,
        $producto,
        $cantidad,
        $precio_contado,
        $precio_credito,
        $cuota,
        $valor_cuota,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_ventas( " .
            " id_pdv," .
            "fecha_actividad," .
            "categoria," .
            "subcategoria," .
            " marca," .
            " producto," .
            " cantidad," .
            " precio_contado," .
            " precio_credito,".
            " cuota,".
            " valor_cuota,".
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $id_pdv,
        $fecha_actividad,
        $categoria,
        $subcategoria,
        $marca,
        $producto,
        $cantidad,
        $precio_contado,
        $precio_credito,
        $cuota,
        $valor_cuota,
        $fecha,
        $hora )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
	//
	public static function insertprecioskc(
    	    $pharma_id,
            $codigo,
            $usuario,
            $supervisor,
            $fecha,
            $hora,
            $sector,
            $categoria,
            $segment1,
            $segment2,
            $brand,
            $tamano,
            $cantidad,
            $sku_code,
            $regular_price,
            $promotional_price
 
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_precios( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "sector," .
            "categoria," .
            "segment1," .
            "segment2," .
            "brand," .
            "tamano," .
            "cantidad," .
            "sku_code," .
            "regular_price," .
            "promotional_price)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			
            $pharma_id,
            $codigo,
            $usuario,
            $supervisor,
            $fecha,
            $hora,
            $sector,
            $categoria,
            $segment1,
            $segment2,
            $brand,
            $tamano,
            $cantidad,
            $sku_code,
            $regular_price,
            $promotional_price
		
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	
	public static function insertprecioskc2018(
		    $id,
            $pos_id,
            $usuario,
            $supervisor,
            $fecha,
            $hora,
            $sector,
            $categoria,
            $segment1,
            $segment2,
            $brand,
            $tamano,
            $cantidad,
            $sku_code,
            $regular_price,
            $promotional_price
 
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_precios( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "sector," .
            "categoria," .
            "segment1," .
            "segment2," .
            "brand," .
            "tamano," .
            "cantidad," .
            "sku_code," .
            "regular_price," .
            "promotional_price)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			
            $id,
            $pos_id,
            $usuario,
            $supervisor,
            $fecha,
            $hora,
            $sector,
            $categoria,
            $segment1,
            $segment2,
            $brand,
            $tamano,
            $cantidad,
            $sku_code,
            $regular_price,
            $promotional_price
		
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertprecioskc2019(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$sku_code,
		$regular_price,
		$promotional_price,
		$ofert_price,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_precios( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "sku_code," .
            "regular_price," .
            "promotional_price," .
			"ofert_price," .
			"manufacturer)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$sku_code,
			$regular_price,
			$promotional_price,
			$ofert_price,
			$manufacturer
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertprecios022023(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$sku_code,
		$regular_price,
		$promotional_price,
		$ofert_price,
		$manufacturer,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_precios( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "sku_code," .
            "regular_price," .
            "promotional_price," .
			"ofert_price," .
			"manufacturer," .
			"plataforma)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$sku_code,
			$regular_price,
			$promotional_price,
			$ofert_price,
			$manufacturer,
            $plataforma
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertprecios072023(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$sku_code,
		$regular_price,
		$promotional_price,
		$ofert_price,
		$tipo,
		$pvm,
		$manufacturer,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_precios( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "sku_code," .
            "regular_price," .
            "promotional_price," .
			"ofert_price," .
			"tipo," .
			"pvm," .
			"manufacturer," .
			"plataforma)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$sku_code,
			$regular_price,
			$promotional_price,
			$ofert_price,
			$tipo,
			$pvm,
			$manufacturer,
            $plataforma
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertLogisticoRelevo(
		$id,
		$codigo,
		$usuario,

		$supervisor,
		$fecha,
		$hora,

		$categoria,
		$brand,
		$sku_code,

		$regular_price,
		$causal,
		$tipo_logistico,

		$foto,
		$comentario,
		$fecha_prod_caducado,

        $fecha_prod_propenso
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_logistico( " .
			"pharma_id," .
            "codigo," .
            "usuario," .

            "supervisor," .
            "fecha," .
            "hora," .

            "categoria," .
            "brand," .
            "sku_code," .

            "regular_price," .
			"causal," .
			"tipo_logistico," .

			"foto," .
			"comentario," .
			"fecha_prod_caducado," .

			"fecha_prod_propenso)" .
            "VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
            $codigo,
            $usuario,

            $supervisor,
            $fecha,
            $hora,

            $categoria,
            $brand,
            $sku_code,

            $regular_price,
            $causal,
            $tipo_logistico,

            $foto,
            $comentario,
            $fecha_prod_caducado,

            $fecha_prod_propenso
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertPreciosVentas(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$sku_code,
		$regular_price,
		$promotional_price,
		$ofert_price,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_precios_ventas( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "sku_code," .
            "regular_price," .
            "promotional_price," .
			"ofert_price," .
			"manufacturer)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$sku_code,
			$regular_price,
			$promotional_price,
			$ofert_price,
			$manufacturer
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertPreciosVentas022023(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$sku_code,
		$regular_price,
		$promotional_price,
		$ofert_price,
		$manufacturer,
        $mpt,
        $margen,
        $comentario
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_precios_ventas( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "sku_code," .
            "regular_price," .
            "promotional_price," .
			"ofert_price," .
			"manufacturer," .
			"mpt," .
			"margen," .
			"comentario)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$sku_code,
			$regular_price,
			$promotional_price,
			$ofert_price,
			$manufacturer,
            $mpt,
            $margen,
            $comentario
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertClientesImpactados(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$sku_code,
		$regular_price,
		$promotional_price,
		$ofert_price,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_clientes_impactados( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "tienda," .
            "impactado," .
            "promotional_price," .
			"ofert_price," .
			"manufacturer)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$sku_code,
			$regular_price,
			$promotional_price,
			$ofert_price,
			$manufacturer
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertPacks(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$presentacion,
		$brand,
		$sku_code,
		$observacion,
		$manufacturer,
		$foto
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_packs( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "subcategoria," .
            "presentacion," .
            "brand," .
            "sku_code," .
            "observacion," .
            "manufacturer," .
			"foto)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$presentacion,
			$brand,
			$sku_code,
			$observacion,
			$manufacturer,
			$foto
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertImpulso(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$brand,
		$sku_code,
		$asignada,
		$vendida,
		$adicional,
		$cumplimiento,
		$impulsadora,
		$observacion,
		$foto
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_impulso( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "brand," .
            "sku_code," .
            "asignada," .
            "vendida," .
            "adicional," .
            "cumplimiento," .
			"impulsadora," .
			"observacion," .
			"foto)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$brand,
			$sku_code,
			$asignada,
			$vendida,
			$adicional,
			$cumplimiento,
			$impulsadora,
			$observacion,
			$foto
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertImpulso022023(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$brand,
		$sku_code,
		$asignada,
		$vendida,
		$adicional,
		$cumplimiento,
		$impulsadora,
		$observacion,
		$foto,
		$precio_venta,
		$alerta_stock,
		$plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_impulso( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "brand," .
            "sku_code," .
            "asignada," .
            "vendida," .
            "adicional," .
            "cumplimiento," .
			"impulsadora," .
			"observacion," .
			"foto," .
			"precio_venta," .
			"alerta_stock," .
			"plataforma)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$brand,
			$sku_code,
			$asignada,
			$vendida,
			$adicional,
			$cumplimiento,
			$impulsadora,
			$observacion,
			$foto,
            $precio_venta,
            $alerta_stock,
            $plataforma
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertProdCad(
		$id,
		$pos_id,
		$usuario,
		$supervisor,
		$fecha,
		$hora,
		$categoria,
		$subcategoria,
		$brand,
		$sku_code,
		$fecha_prod,
		$cantidad_prod,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_prod_caducar( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "categoria," .
            "subcategoria," .
            "brand," .
            "sku_code," .
            "fecha_prod," .
            "cantidad_prod," .
			"manufacturer)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$brand,
			$sku_code,
			$fecha_prod,
			$cantidad_prod,
			$manufacturer
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	//
    public static function insertRastreo(
        $usuario,
        $latitude,
        $longitude,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
    // $coma=',';
    //$coordenadas=$latitude.$coma.$longitude;
        // Sentencia INSERT
        $comando = "INSERT INTO insert_rastreo( " .
            " usuario," .
            " latitude," .
            " longitude," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array( $usuario,
			$latitude,
			$longitude,
			$fecha,
			$hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function getIdImplementacion(){
	        $consulta = "SELECT id_nuevo FROM insert_nuevo_pdv ORDER BY id_nuevo ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}
    
  
    public static function getIdExhibicion(){
	        $consulta = "SELECT id_exhibiciones FROM insert_exhibiciones ORDER BY id_exhibiciones ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}
	
	public static function getIdPacks(){
	        $consulta = "SELECT id_packs FROM insert_packs ORDER BY id_packs ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}
	
	public static function getIdImpulso(){
	        $consulta = "SELECT id_impulso FROM insert_impulso ORDER BY id_impulso ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}
	
	public static function getIdPromocion(){
	        $consulta = "SELECT id_promociones FROM insert_promociones ORDER BY id_promociones ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}
		
	public static function getIdVenta(){
	        $consulta = "SELECT id_venta FROM insert_venta ORDER BY id_venta ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}
	
	public static function getIdNotificacion(){
	        $consulta = "SELECT id FROM insert_notificacion ORDER BY id ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}
       
	public static function getIdNotificacionPrueba(){
	        $consulta = "SELECT id FROM insert_notificacion_prueba ORDER BY id ASC";
	        try {
	            // Preparar sentencia
	            $comando = Database::getInstance()->getDb()->prepare($consulta);
	            // Ejecutar sentencia preparada
	            $comando->execute();
	
	            return $comando->fetchAll(PDO::FETCH_ASSOC);
	
	        } catch (PDOException $e) {
	            return false;
	        }
	}
    
    public static function insertNotificacion(
        $id_pdv,
        $estado_visita,
        $novedades,
        $foto,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_notificacion( " .
            "id_pdv," .
            "estado_visita," .
            "novedades," .
            "foto," .
            "fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
	        $id_pdv,
        $estado_visita,
        $novedades,
        $foto,
        $fecha,
        $hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    public static function insertNotificacionPrueba(
        $id_pdv,
        $estado_visita,
        $novedades,
        $foto,
        $latitud,
        $longitud,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_notificacion_prueba( " .
            "id_pdv," .
            "estado_visita," .
            "novedades," .
            "foto," .
            "latitud," .
            "longitud," .
            "fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
	        $id_pdv,
        $estado_visita,
        $novedades,
        $foto,
        $latitud,
        $longitud,
        $fecha,
        $hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
    /**
     * Insertar Coordenadas y Recuperar LastId()
     * @return PDOStatement
     */
    public static function insertNot(
        $tipo,
        $user,
        $imei,
        $autotime,
        $fecha,
        $hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO samsung_notificaciones( " .
            "tipo," .
            "usuario," .
            "imei," .
            "autotime," .
            "fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
        $tipo,
        $user,
        $imei,
        $autotime,
        $fecha,
        $hora
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertNot0622(
        $user,
        $supervisor,
        $descripcion,
        $fecha,
        $hora
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_notificaciones( " .
            "usuario," .
            "supervisor," .
            "descripcion," .
            "fecha," .
            " hora)" .
            " VALUES(?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $user,
                $supervisor,
                $descripcion,
                $fecha,
                $hora
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertLog(
        $usuario,
        $fecha,
        $hora,
        $accion
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_log( " .
            "usuario," .
            "fecha," .
            "hora," .
            "accion)" .
            " VALUES(?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $usuario,
                $fecha,
                $hora,
                $accion
            )
        );

        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertShare2019(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
		$segmento,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_share_shelf( " .
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
		"segmento," .
        "marca_seleccionada," .
        "brand," .
        "ctms_percha," .
        "ctms_marca," .
        "otros,".
		"manufacturer)" .
        " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$pharma_id,
			$codigo,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segmento,
			$marca_seleccionada,
			$brand,
			$ctms_percha,
			$ctms_marca,
			$otros,
			$manufacturer
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertShare2020(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
		$segmento,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_share_shelf( " .
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
		"segmento," .
        "marca_seleccionada," .
        "brand," .
        "ctms_percha," .
        "ctms_marca," .
        "otros,".
        "manufacturer)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$pharma_id,
			$codigo,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segmento,
			$marca_seleccionada,
			$brand,
			$ctms_percha,
			$ctms_marca,
			$otros,
			$manufacturer
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertShare112020(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
		$segmento,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros,
		$manufacturer,
		$path
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_share_shelf( " .
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
		"segmento," .
        "marca_seleccionada," .
        "brand," .
        "ctms_percha," .
        "ctms_marca," .
        "otros,".
        "manufacturer," .
        "image)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$pharma_id,
			$codigo,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segmento,
			$marca_seleccionada,
			$brand,
			$ctms_percha,
			$ctms_marca,
			$otros,
			$manufacturer,
			$path
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertShare052021(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
		$segmento,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros,
		$manufacturer,
		$razones,
		$path
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_share_shelf( " .
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
		"segmento," .
        "marca_seleccionada," .
        "brand," .
        "ctms_percha," .
        "ctms_marca," .
        "otros,".
        "manufacturer," .
        "razones," .
        "image)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$pharma_id,
			$codigo,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segmento,
			$marca_seleccionada,
			$brand,
			$ctms_percha,
			$ctms_marca,
			$otros,
			$manufacturer,
			$razones,
			$path
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	public static function insertShare022023(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
		$segmento,
        $marca_seleccionada,
        $brand,
        $ctms_percha,
        $ctms_marca,
        $otros,
		$manufacturer,
		$razones,
		$path,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_share_shelf( " .
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "sector," .
        "categoria," .
		"segmento," .
        "marca_seleccionada," .
        "brand," .
        "ctms_percha," .
        "ctms_marca," .
        "otros,".
        "manufacturer," .
        "razones," .
        "image," .
        "plataforma)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$pharma_id,
			$codigo,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segmento,
			$marca_seleccionada,
			$brand,
			$ctms_percha,
			$ctms_marca,
			$otros,
			$manufacturer,
			$razones,
			$path,
            $plataforma
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertPDI(
        $id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$marca_seleccionada,
        $cumplimiento,
        $universo,
        $caras,
        $otros,
		$obj_categoria,
		$part_categoria,
		$path,
        $canal
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_pdi( " .
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "categoria," .
		"marca_seleccionada," .
        "cumplimiento," .
        "universo," .
        "caras," .
        "otros,".
        "obj_categoria," .
        "part_categoria," .
        "image," .
        "canal)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $id,
                $pos_id,
                $user,
                $supervisor,
                $fecha,
                $hora,
                $categoria,
                $marca_seleccionada,
                $cumplimiento,
                $universo,
                $caras,
                $otros,
                $obj_categoria,
                $part_categoria,
                $path,
                $canal
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	

    public static function insertPDI022023(
        $id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$marca_seleccionada,
        $cumplimiento,
        $universo,
        $caras,
        $otros,
		$obj_categoria,
		$part_categoria,
		$path,
        $canal,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_pdi( " .
		"pharma_id," .
        "codigo," .
        "usuario," .
        "supervisor," .
        "fecha," .
        "hora," .
        "categoria," .
		"marca_seleccionada," .
        "cumplimiento," .
        "universo," .
        "caras," .
        "otros,".
        "obj_categoria," .
        "part_categoria," .
        "image," .
        "canal," .
        "plataforma)" .
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $id,
                $pos_id,
                $user,
                $supervisor,
                $fecha,
                $hora,
                $categoria,
                $marca_seleccionada,
                $cumplimiento,
                $universo,
                $caras,
                $otros,
                $obj_categoria,
                $part_categoria,
                $path,
                $canal,
                $plataforma
            )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertCodificados2019(
		$id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $brand,
        $sku_code,
        $codifica,
        $ausencia,
        $responsable,
        $razones,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_codificados_osa( " .
		" pharma_id," .
        " codigo," .
        " usuario," .
        " supervisor," .
        " fecha," .
        " hora," .
        " sector," .
        " categoria," .
        " segment1," .
        " brand," .
        " sku_code," .
        " codifica," .
        " ausencia," .
        " responsable," .
        " razones, " .
		" manufacturer)" .
       " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$user,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segment1,
			$brand,
			$sku_code,
			$codifica,
			$ausencia,
			$responsable,
			$razones, 
			$manufacturer
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertCodificados2020(
		$id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $brand,
        $sku_code,
        $codifica,
        $ausencia,
        $disponible,
        $responsable,
        $razones,
        $pvp,
        $pvc,
        $poferta,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_codificados_osa( " .
		" pharma_id," .
        " codigo," .
        " usuario," .
        " supervisor," .
        " fecha," .
        " hora," .
        " sector," .
        " categoria," .
        " segment1," .
        " brand," .
        " sku_code," .
        " codifica," .
        " ausencia," .
        " disponible," .
        " responsable," .
        " razones, " .
        " pvp, " .
        " pvc, " .
        " precio_oferta, " .
		" manufacturer)" .
       " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$user,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segment1,
			$brand,
			$sku_code,
			$codifica,
			$ausencia,
			$disponible,
			$responsable,
			$razones,
			$pvp,
			$pvc,
			$poferta,
			$manufacturer)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertCodificados2022(
		$id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $brand,
        $sku_code,
        $codifica,
        $ausencia,
        $disponible,
        $responsable,
        $razones,
        $sugerido,
        $tipo_sugerido,
        $pvp,
        $pvc,
        $poferta,
		$manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_codificados_osa( " .
		" pharma_id," .
        " codigo," .
        " usuario," .
        " supervisor," .
        " fecha," .
        " hora," .
        " sector," .
        " categoria," .
        " segment1," .
        " brand," .
        " sku_code," .
        " codifica," .
        " ausencia," .
        " disponible," .
        " responsable," .
        " razones, " .
        " sugerido, " .
        " tipo_sugerido, " .
        " pvp, " .
        " pvc, " .
        " precio_oferta, " .
		" manufacturer)" .
       " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$user,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segment1,
			$brand,
			$sku_code,
			$codifica,
			$ausencia,
			$disponible,
			$responsable,
			$razones,
            $sugerido,
            $tipo_sugerido,
			$pvp,
			$pvc,
			$poferta,
			$manufacturer)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertCodificados092022(
		$id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $brand,
        $sku_code,
        $codifica,
        $ausencia,
        $disponible,
        $responsable,
        $razones,
        $sugerido,
        $tipo_sugerido,
        $pvp,
        $pvc,
        $poferta,
		$manufacturer,
		$quiebre_percha,
		$quiebre_bodega
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_codificados_osa( " .
		" pharma_id," .
        " codigo," .
        " usuario," .
        " supervisor," .
        " fecha," .
        " hora," .
        " sector," .
        " categoria," .
        " segment1," .
        " brand," .
        " sku_code," .
        " codifica," .
        " ausencia," .
        " disponible," .
        " responsable," .
        " razones, " .
        " sugerido, " .
        " tipo_sugerido, " .
        " pvp, " .
        " pvc, " .
        " precio_oferta, " .
		" manufacturer," .
		" quiebre_percha," .
		" quiebre_bodega)" .
       " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$user,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segment1,
			$brand,
			$sku_code,
			$codifica,
			$ausencia,
			$disponible,
			$responsable,
			$razones,
            $sugerido,
            $tipo_sugerido,
			$pvp,
			$pvc,
			$poferta,
			$manufacturer,
            $quiebre_percha,
            $quiebre_bodega)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertCodificados022023(
		$id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $segment1,
        $brand,
        $sku_code,
        $codifica,
        $ausencia,
        $disponible,
        $responsable,
        $razones,
        $sugerido,
        $tipo_sugerido,
        $pvp,
        $pvc,
        $poferta,
		$manufacturer,
		$quiebre_percha,
		$quiebre_bodega,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_codificados_osa( " .
		" pharma_id," .
        " codigo," .
        " usuario," .
        " supervisor," .
        " fecha," .
        " hora," .
        " sector," .
        " categoria," .
        " segment1," .
        " brand," .
        " sku_code," .
        " codifica," .
        " ausencia," .
        " disponible," .
        " responsable," .
        " razones, " .
        " sugerido, " .
        " tipo_sugerido, " .
        " pvp, " .
        " pvc, " .
        " precio_oferta, " .
		" manufacturer," .
		" quiebre_percha," .
		" quiebre_bodega," .
		" plataforma)" .
       " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
            $id,
			$pos_id,
			$user,
			$supervisor,
			$fecha,
			$hora,
			$sector,
			$categoria,
			$segment1,
			$brand,
			$sku_code,
			$codifica,
			$ausencia,
			$disponible,
			$responsable,
			$razones,
            $sugerido,
            $tipo_sugerido,
			$pvp,
			$pvc,
			$poferta,
			$manufacturer,
            $quiebre_percha,
            $quiebre_bodega,
            $plataforma)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertPromocion2019(
        $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$subcategoria,
		$brand,
		$canal,
        $tipo_promocion,
        $descripcion_promocion,
        $mecanica,
		$inicio_promocion,
		$fin_promocion,
		$agotar_stock, 
		$pvc_anterior, 
		$pvc_actual, 
        $foto,
        $manufacturer
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_promociones( " .
		" pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
         "fecha," .
         "hora," .
         "categoria," .
		 "subcategoria," .
		 "marca," .
		 "canal," .
         "tipo_promocion," .
         "descripcion_promocion," .
         "mecanica," .
		 "inicio_promocion," .
		 "fin_promocion," .
		 "agotar_stock, " .
		 "pvc_anterior, " .
		 "pvc_actual, " .
         "foto," .
         "manufacturer)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$brand,
			$canal,
			$tipo_promocion,
			$descripcion_promocion,
			$mecanica,
			$inicio_promocion,
			$fin_promocion,
			$agotar_stock, 
			$pvc_anterior, 
			$pvc_actual, 
			$foto,
			$manufacturer
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertPromocion2020(
        $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$subcategoria,
		$brand,
		$canal,
        $tipo_promocion,
        $descripcion_promocion,
        $mecanica,
		$inicio_promocion,
		$fin_promocion,
		$agotar_stock, 
		$pvc_anterior, 
		$pvc_actual, 
        $foto,
        $manufacturer,
        $sku
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_promociones( " .
		" pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
         "fecha," .
         "hora," .
         "categoria," .
		 "subcategoria," .
		 "marca," .
		 "canal," .
         "tipo_promocion," .
         "descripcion_promocion," .
         "mecanica," .
		 "inicio_promocion," .
		 "fin_promocion," .
		 "agotar_stock, " .
		 "pvc_anterior, " .
		 "pvc_actual, " .
         "foto," .
         "manufacturer," .
         "sku)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$brand,
			$canal,
			$tipo_promocion,
			$descripcion_promocion,
			$mecanica,
			$inicio_promocion,
			$fin_promocion,
			$agotar_stock, 
			$pvc_anterior, 
			$pvc_actual, 
			$foto,
			$manufacturer,
			$sku
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	
	public static function insertPromocion022023(
        $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$subcategoria,
		$brand,
		$canal,
        $tipo_promocion,
        $descripcion_promocion,
        $mecanica,
		$inicio_promocion,
		$fin_promocion,
		$agotar_stock, 
		$pvc_anterior, 
		$pvc_actual, 
        $foto,
        $manufacturer,
        $sku,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_promociones( " .
		" pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
         "fecha," .
         "hora," .
         "categoria," .
		 "subcategoria," .
		 "marca," .
		 "canal," .
         "tipo_promocion," .
         "descripcion_promocion," .
         "mecanica," .
		 "inicio_promocion," .
		 "fin_promocion," .
		 "agotar_stock, " .
		 "pvc_anterior, " .
		 "pvc_actual, " .
         "foto," .
         "manufacturer," .
         "sku," .
         "plataforma)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$brand,
			$canal,
			$tipo_promocion,
			$descripcion_promocion,
			$mecanica,
			$inicio_promocion,
			$fin_promocion,
			$agotar_stock, 
			$pvc_anterior, 
			$pvc_actual, 
			$foto,
			$manufacturer,
			$sku,
			$plataforma
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertPromocion052023(
        $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$subcategoria,
		$brand,
		$canal,
        $tipo_promocion,
        $descripcion_promocion,
        $mecanica,
		$inicio_promocion,
		$fin_promocion,
		$agotar_stock, 
		$pvc_anterior, 
		$pvc_actual, 
		$margen_dscto, 
        $foto,
        $manufacturer,
        $sku,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_promociones( " .
		" pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
         "fecha," .
         "hora," .
         "categoria," .
		 "subcategoria," .
		 "marca," .
		 "canal," .
         "tipo_promocion," .
         "descripcion_promocion," .
         "mecanica," .
		 "inicio_promocion," .
		 "fin_promocion," .
		 "agotar_stock, " .
		 "pvc_anterior, " .
		 "pvc_actual, " .
		 "margen_dscto, " .
         "foto," .
         "manufacturer," .
         "sku," .
         "plataforma)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$brand,
			$canal,
			$tipo_promocion,
			$descripcion_promocion,
			$mecanica,
			$inicio_promocion,
			$fin_promocion,
			$agotar_stock, 
			$pvc_anterior, 
			$pvc_actual, 
			$margen_dscto, 
			$foto,
			$manufacturer,
			$sku,
			$plataforma
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertPromocion062023(
        $id,
        $pos_id,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$subcategoria,
		$brand,
		$canal,
        $tipo_promocion,
        $descripcion_promocion,
        $producto,
        $mecanica,
		$inicio_promocion,
		$fin_promocion,
		$agotar_stock, 
		$pvc_anterior, 
		$pvc_actual, 
		$margen_dscto, 
        $foto,
        $manufacturer,
        $sku,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_promociones( " .
		" pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
         "fecha," .
         "hora," .
         "categoria," .
		 "subcategoria," .
		 "marca," .
		 "canal," .
         "tipo_promocion," .
         "descripcion_promocion," .
         "producto," .
         "mecanica," .
		 "inicio_promocion," .
		 "fin_promocion," .
		 "agotar_stock, " .
		 "pvc_anterior, " .
		 "pvc_actual, " .
		 "margen_dscto, " .
         "foto," .
         "manufacturer," .
         "sku," .
         "plataforma)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
			$id,
			$pos_id,
			$usuario,
			$supervisor,
			$fecha,
			$hora,
			$categoria,
			$subcategoria,
			$brand,
			$canal,
			$tipo_promocion,
			$descripcion_promocion,
			$producto,
			$mecanica,
			$inicio_promocion,
			$fin_promocion,
			$agotar_stock, 
			$pvc_anterior, 
			$pvc_actual, 
			$margen_dscto, 
			$foto,
			$manufacturer,
			$sku,
			$plataforma
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertCanjes(
        $pharma_id,
		$codigo,
		$canal,
		$nombre_comercial,
		$local,
		$region,
		$provincia,
		$ciudad,
		$zona,
		$direccion,
		$supervisor,
		$mercaderista,
		$usuario,
		$latitud,
		$longitud,
		$territorio,
		$zona_territorio,
		$categoria,
		$subcategoria,
		$marca,
		$producto,
		$tipo_combo,
		$mecanica,
		$combos_armados,
		$stock,
		$pvc_combo,
		$pvc_unitario,
		$visita,
		$observaciones,
		$path1,
		$path2,
		$fecha,
		$hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_canjes (" .
			"pharma_id, " .
			"codigo, " .
			"canal, " .
			"nombre_comercial, " .
			"local, " .
			"region, " .
			"provincia, " .
			"ciudad, " .
			"zona, " .
			"direccion, " .
			"supervisor, " .
			"mercaderista, " .
			"usuario, " .
			"latitud, " .
			"longitud, " .
			"territorio, " .
			"zona_territorio, " .
			"categoria, " .
			"subcategoria, " .
			"marca, " .
			"producto, " .
			"tipo_combo, " .
			"mecanica, " .
			"combos_armados, " .
			"stock, " .
			"pvc_combo, " .
			"pvc_unitario, " .
			"visita, " .
			"observaciones, " .
			"foto, " .
			"foto_guia, " .
			"fecha, " .
			"hora) " .
			"VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";


        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
			array(
				$pharma_id,
				$codigo,
				$canal,
				$nombre_comercial,
				$local,
				$region,
				$provincia,
				$ciudad,
				$zona,
				$direccion,
				$supervisor,
				$mercaderista,
				$usuario,
				$latitud,
				$longitud,
				$territorio,
				$zona_territorio,
				$categoria,
				$subcategoria,
				$marca,
				$producto,
				$tipo_combo,
				$mecanica,
				$combos_armados,
				$stock,
				$pvc_combo,
				$pvc_unitario,
				$visita,
				$observaciones,
				$path1,
				$path2,
				$fecha,
				$hora
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertCanjes2022(
        $pharma_id,
		$codigo,
		$canal,
		$nombre_comercial,
		$local,
		$region,
		$provincia,
		$ciudad,
		$zona,
		$direccion,
		$supervisor,
		$mercaderista,
		$usuario,
		$latitud,
		$longitud,
		$territorio,
		$zona_territorio,
		$categoria,
		$subcategoria,
		$marca,
		$producto,
		$tipo_combo,
		$mecanica,
		$combos_armados,
		$stock,
		$pvc_combo,
		$pvc_unitario,
		$visita,
		$mes,
		$observaciones,
		$path1,
		$path2,
		$fecha,
		$hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_canjes (" .
			"pharma_id, " .
			"codigo, " .
			"canal, " .
			"nombre_comercial, " .
			"local, " .
			"region, " .
			"provincia, " .
			"ciudad, " .
			"zona, " .
			"direccion, " .
			"supervisor, " .
			"mercaderista, " .
			"usuario, " .
			"latitud, " .
			"longitud, " .
			"territorio, " .
			"zona_territorio, " .
			"categoria, " .
			"subcategoria, " .
			"marca, " .
			"producto, " .
			"tipo_combo, " .
			"mecanica, " .
			"combos_armados, " .
			"stock, " .
			"pvc_combo, " .
			"pvc_unitario, " .
			"visita, " .
			"mes, " .
			"observaciones, " .
			"foto, " .
			"foto_guia, " .
			"fecha, " .
			"hora) " .
			"VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";


        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
			array(
				$pharma_id,
				$codigo,
				$canal,
				$nombre_comercial,
				$local,
				$region,
				$provincia,
				$ciudad,
				$zona,
				$direccion,
				$supervisor,
				$mercaderista,
				$usuario,
				$latitud,
				$longitud,
				$territorio,
				$zona_territorio,
				$categoria,
				$subcategoria,
				$marca,
				$producto,
				$tipo_combo,
				$mecanica,
				$combos_armados,
				$stock,
				$pvc_combo,
				$pvc_unitario,
				$visita,
				$mes,
				$observaciones,
				$path1,
				$path2,
				$fecha,
				$hora
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
    public static function insertCanjes022023(
        $pharma_id,
		$codigo,
		$canal,
		$nombre_comercial,
		$local,
		$region,
		$provincia,
		$ciudad,
		$zona,
		$direccion,
		$supervisor,
		$mercaderista,
		$usuario,
		$latitud,
		$longitud,
		$territorio,
		$zona_territorio,
		$categoria,
		$subcategoria,
		$marca,
		$producto,
		$tipo_combo,
		$mecanica,
		$combos_armados,
		$stock,
		$pvc_combo,
		$pvc_unitario,
		$visita,
		$mes,
		$observaciones,
		$path1,
		$path2,
		$fecha,
		$hora,
        $plataforma
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_canjes (" .
			"pharma_id, " .
			"codigo, " .
			"canal, " .
			"nombre_comercial, " .
			"local, " .
			"region, " .
			"provincia, " .
			"ciudad, " .
			"zona, " .
			"direccion, " .
			"supervisor, " .
			"mercaderista, " .
			"usuario, " .
			"latitud, " .
			"longitud, " .
			"territorio, " .
			"zona_territorio, " .
			"categoria, " .
			"subcategoria, " .
			"marca, " .
			"producto, " .
			"tipo_combo, " .
			"mecanica, " .
			"combos_armados, " .
			"stock, " .
			"pvc_combo, " .
			"pvc_unitario, " .
			"visita, " .
			"mes, " .
			"observaciones, " .
			"foto, " .
			"foto_guia, " .
			"fecha, " .
			"hora, " .
			"plataforma) " .
			"VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";


        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
			array(
				$pharma_id,
				$codigo,
				$canal,
				$nombre_comercial,
				$local,
				$region,
				$provincia,
				$ciudad,
				$zona,
				$direccion,
				$supervisor,
				$mercaderista,
				$usuario,
				$latitud,
				$longitud,
				$territorio,
				$zona_territorio,
				$categoria,
				$subcategoria,
				$marca,
				$producto,
				$tipo_combo,
				$mecanica,
				$combos_armados,
				$stock,
				$pvc_combo,
				$pvc_unitario,
				$visita,
				$mes,
				$observaciones,
				$path1,
				$path2,
				$fecha,
				$hora,
				$plataforma
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertMaterialesRecibidos(
        $pharma_id,
		$codigo,
		$canal,
		$nombre_comercial,
		$local,
		$region,
		$provincia,
		$ciudad,
		$zona,
		$direccion,
		$supervisor,
		$mercaderista,
		$usuario,
		$latitud,
		$longitud,
		$territorio,
		$zona_territorio,
		$alerta,
		$tipo,
		$material,
		$cantidad,
		$estado_material,
		$prioridad,
		$observaciones,
		$path_foto,
		$fecha,
		$hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_materiales_recibidos (" .
			"pharma_id, " .
			"codigo, " .
			"canal, " .
			"nombre_comercial, " .
			"local, " .
			"region, " .
			"provincia, " .
			"ciudad, " .
			"zona, " .
			"direccion, " .
			"supervisor, " .
			"mercaderista, " .
			"usuario, " .
			"latitud, " .
			"longitud, " .
			"territorio, " .
			"zona_territorio, " .
			"alerta, " .
			"tipo, " .
			"material, " .
			"cantidad, " .
			"estado_material, " .
			"prioridad, " .
			"observaciones, " .
			"foto, " .
			"fecha, " .
			"hora) " .
			"VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";


        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
			array(
				$pharma_id,
				$codigo,
				$canal,
				$nombre_comercial,
				$local,
				$region,
				$provincia,
				$ciudad,
				$zona,
				$direccion,
				$supervisor,
				$mercaderista,
				$usuario,
				$latitud,
				$longitud,
				$territorio,
				$zona_territorio,
				$alerta,
				$tipo,
				$material,
				$cantidad,
				$estado_material,
				$prioridad,
				$observaciones,
				$path_foto,
				$fecha,
				$hora
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertMallaCodificados(
        $id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $segment1,
        $brand,
        $sku_code,
        $codifica,
        $observacion,
        $manufacturer
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_malla_codificados( " .
            " pharma_id," .
            " codigo," .
            " usuario," .
            " supervisor," .
            " fecha," .
            " hora," .
            " sector," .
            " categoria," .
            " subcategoria," .
            " segment1," .
            " brand," .
            " sku_code," .
            " codifica," .
            " observacion, " .
            " manufacturer)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
                $id,
                $pos_id,
                $user,
                $supervisor,
                $fecha,
                $hora,
                $sector,
                $categoria,
                $subcategoria,
                $segment1,
                $brand,
                $sku_code,
                $codifica,
                $observacion,
                $manufacturer
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertMallaCodificadosVentas(
        $id,
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $sector,
        $categoria,
        $subcategoria,
        $segment1,
        $brand,
        $sku_code,
        $codifica,
        $observacion,
        $manufacturer
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_malla_codificados_ventas( " .
            " pharma_id," .
            " codigo," .
            " usuario," .
            " supervisor," .
            " fecha," .
            " hora," .
            " sector," .
            " categoria," .
            " subcategoria," .
            " segment1," .
            " brand," .
            " sku_code," .
            " codifica," .
            " observacion, " .
            " manufacturer)" .
            " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);
        $sentencia->execute(
            array(
                $id,
                $pos_id,
                $user,
                $supervisor,
                $fecha,
                $hora,
                $sector,
                $categoria,
                $subcategoria,
                $segment1,
                $brand,
                $sku_code,
                $codifica,
                $observacion,
                $manufacturer
            )
        );

        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertEjecucionMateriales(
        $pharma_id,
		$codigo,
		$canal,
		$nombre_comercial,
		$local,
		$region,
		$provincia,
		$ciudad,
		$zona,
		$direccion,
		$supervisor,
		$mercaderista,
		$usuario,
		$latitud,
		$longitud,
		$territorio,
		$zona_territorio,
		$tipo,
		$material,
		$estado_material,
		$prioridad,
		$observaciones,
		$path_foto,
		$fecha,
		$hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_ejecucion_materiales (" .
			"pharma_id, " .
			"codigo, " .
			"canal, " .
			"nombre_comercial, " .
			"local, " .
			"region, " .
			"provincia, " .
			"ciudad, " .
			"zona, " .
			"direccion, " .
			"supervisor, " .
			"mercaderista, " .
			"usuario, " .
			"latitud, " .
			"longitud, " .
			"territorio, " .
			"zona_territorio, " .
			"tipo, " .
			"material, " .
			"estado_material, " .
			"prioridad, " .
			"observaciones, " .
			"foto, " .
			"fecha, " .
			"hora) " .
			"VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";


        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
			array(
				$pharma_id,
				$codigo,
				$canal,
				$nombre_comercial,
				$local,
				$region,
				$provincia,
				$ciudad,
				$zona,
				$direccion,
				$supervisor,
				$mercaderista,
				$usuario,
				$latitud,
				$longitud,
				$territorio,
				$zona_territorio,
				$tipo,
				$material,
				$estado_material,
				$prioridad,
				$observaciones,
				$path_foto,
				$fecha,
				$hora
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertMCI(
        $pharma_id,
		$codigo,
		$canal,
		$nombre_comercial,
		$local,
		$region,
		$provincia,
		$ciudad,
		$zona,
		$direccion,
		$supervisor,
		$mercaderista,
		$usuario,
		$latitud,
		$longitud,
		$territorio,
		$zona_territorio,
		$causal,
		$observaciones,
		$path,
		$fecha,
		$hora
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_mci (" .
			"pharma_id, " .
			"codigo, " .
			"canal, " .
			"nombre_comercial, " .
			"local, " .
			"region, " .
			"provincia, " .
			"ciudad, " .
			"zona, " .
			"direccion, " .
			"supervisor, " .
			"mercaderista, " .
			"usuario, " .
			"latitud, " .
			"longitud, " .
			"territorio, " .
			"zona_territorio, " .
			"causal, " .
			"observaciones, " .
			"foto, " .
			"fecha, " .
			"hora) " .
			"VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";


        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
			array(
				$pharma_id,
				$codigo,
				$canal,
				$nombre_comercial,
				$local,
				$region,
				$provincia,
				$ciudad,
				$zona,
				$direccion,
				$supervisor,
				$mercaderista,
				$usuario,
				$latitud,
				$longitud,
				$territorio,
				$zona_territorio,
				$causal,
				$observaciones,
				$path,
				$fecha,
				$hora
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertEvaluaciones(
        $id,
		$evaluador,
		$gestor,
		$id_pdv,
		$pdv,
		$tipo,
		$impletacion_rotacion_descripcion,
		$impletacion_rotacion_puntaje,
		$impletacion_rotacion_meta,
		$exhibicion_visibilidad_descripcion,
		$exhibicion_visibilidad_puntaje,
		$exhibicion_visibilidad_meta,
		$evaluacion_gestor_descripcion,
		$evaluacion_gestor_puntaje,
		$evaluacion_gestor_meta,
		$evaluacion_pdv_descripcion,
		$evaluacion_pdv_puntaje,
		$evaluacion_pdv_meta,
		$path_foto,
		$fecha,
		$hora,
        $comentario
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_evaluaciones (" .
			"id, " .
			"evaluador, " .
			"gestor, " .
			"id_pdv, " .
			"pdv, " .
			"tipo, " .
			"impletacion_rotacion_descripcion, " .
			"impletacion_rotacion_puntaje, " .
			"impletacion_rotacion_meta, " .
			"exhibicion_visibilidad_descripcion, " .
			"exhibicion_visibilidad_puntaje, " .
			"exhibicion_visibilidad_meta, " .
			"evaluacion_gestor_descripcion, " .
			"evaluacion_gestor_puntaje, " .
			"evaluacion_gestor_meta, " .
			"evaluacion_pdv_descripcion, " .
			"evaluacion_pdv_puntaje, " .
			"evaluacion_pdv_meta, " .
			"foto, " .
			"fecha, " .
			"hora, " .
			"comentario) " .
			"VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";


        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
			array(
				$id,
                $evaluador,
                $gestor,
                $id_pdv,
                $pdv,
                $tipo,
                $impletacion_rotacion_descripcion,
                $impletacion_rotacion_puntaje,
                $impletacion_rotacion_meta,
                $exhibicion_visibilidad_descripcion,
                $exhibicion_visibilidad_puntaje,
                $exhibicion_visibilidad_meta,
                $evaluacion_gestor_descripcion,
                $evaluacion_gestor_puntaje,
                $evaluacion_gestor_meta,
                $evaluacion_pdv_descripcion,
                $evaluacion_pdv_puntaje,
                $evaluacion_pdv_meta,
                $path_foto,
                $fecha,
                $hora,
                $comentario
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertRotacion(
        $pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$producto,
		$promocional,
		$mecanica,
        $peso,
        $cantidad,
        $fecha_rot,
        $path,
        $observaciones
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_rotacion( " .
		" pharma_id," .
         "codigo," .
         "usuario," .
         "supervisor," .
         "fecha," .
         "hora," .
         "categoria," .
		 "producto," .
		 "promocional," .
		 "mecanica," .
		 "peso," .
		 "cantidad," .
		 "fecha_rot," .
		 "foto_guia," .
		 "observaciones)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
          array(
		$pharma_id,
        $codigo,
        $usuario,
        $supervisor,
        $fecha,
        $hora,
        $categoria,
		$producto,
		$promocional,
		$mecanica,
		$peso,
		$cantidad,
        $fecha_rot,
        $path,
        $observaciones
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
	
	public static function insertTareas(
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $channel,
		$codigo_pdv,
		$mercaderista,
		$tareas,
        $realizado,
        $path
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_tareas( " .
		" pos_id," .
         "user," .
         "supervisor," .
         "fecha," .
         "hora," .
         "channel," .
		 "codigo_pdv," .
		 "mercaderista," .
		 "tareas," .
		 "realizado," .
		 "foto)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
          array(
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $channel,
		$codigo_pdv,
		$mercaderista,
		$tareas,
        $realizado,
        $path
		)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertEvidenciaFotografica(
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $channel,
		$codigo_pdv,
		$mercaderista,
		$tareas,
        $realizado,
        $path
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_evidencia_fotografica( " .
		" pos_id," .
         "user," .
         "supervisor," .
         "fecha," .
         "hora," .
         "channel," .
		 "codigo_pdv," .
		 "mercaderista," .
		 "tareas," .
		 "realizado," .
		 "foto)" .
         " VALUES( ?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $pos_id,
                $user,
                $supervisor,
                $fecha,
                $hora,
                $channel,
                $codigo_pdv,
                $mercaderista,
                $tareas,
                $realizado,
                $path
		    )
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertPdvFotografico(
        $pos_id,
        $user,
        $supervisor,
        $fecha,
        $hora,
        $channel,
		$codigo_pdv,
		$mercaderista,
        $path
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_pdv_fotografico( " .
		" pos_id," .
         "user," .
         "supervisor," .
         "fecha," .
         "hora," .
         "channel," .
		 "codigo_pdv," .
		 "mercaderista," .
		 "foto)" .
         " VALUES( ?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $pos_id,
                $user,
                $supervisor,
                $fecha,
                $hora,
                $channel,
                $codigo_pdv,
                $mercaderista,
                $path
		    )
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
	public static function insertSugeridos(
            $id,
            $pos_id,
            $user,
            $supervisor,
            $fecha,
            $hora,
            $local,
            $codigo_fabril,
            $vendedor_fabril,
            $categoria,
            $subcategoria,
            $brand,
            $sku_code,
            $quiebre,
			$unidad_disponible,
			$sugerido,
			$cantidad,
			$observaciones
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_sugeridos( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "local," .
            "codigo_fabril," .
            "vendedor_asignado," .
            "categoria," .
            "subcategoria," .
            "brand," .
            "sku_code," .
            "quiebre," .
            "unidad_disponible," .
            "sugerido," .
            "cantidad," .
			"observaciones)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
            $pos_id,
            $user,
            $supervisor,
            $fecha,
            $hora,
            $local,
            $codigo_fabril,
            $vendedor_fabril,
            $categoria,
            $subcategoria,
            $brand,
            $sku_code,
            $quiebre,
			$unidad_disponible,
			$sugerido,
			$cantidad,
			$observaciones
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }
    
	public static function insertSugeridos2022(
            $id,
            $pos_id,
            $user,
            $supervisor,
            $fecha,
            $hora,
            $local,
            $codigo_fabril,
            $vendedor_fabril,
            $categoria,
            $subcategoria,
            $brand,
            $sku_code,
            $quiebre,
			$unidad_disponible,
			$sugerido,
			$cantidad,
			$observaciones,
            $entrega
    )
    {
    $conexion= Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_sugeridos( " .
			" pharma_id," .
            "codigo," .
            "usuario," .
            "supervisor," .
            "fecha," .
            "hora," .
            "local," .
            "codigo_fabril," .
            "vendedor_asignado," .
            "categoria," .
            "subcategoria," .
            "brand," .
            "sku_code," .
            "quiebre," .
            "unidad_disponible," .
            "sugerido," .
            "cantidad," .
			"observaciones," .
			"entrega)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
            $id,
            $pos_id,
            $user,
            $supervisor,
            $fecha,
            $hora,
            $local,
            $codigo_fabril,
            $vendedor_fabril,
            $categoria,
            $subcategoria,
            $brand,
            $sku_code,
            $quiebre,
			$unidad_disponible,
			$sugerido,
			$cantidad,
			$observaciones,
			$entrega
			)
        );
        
        return $pdo = $conexion->lastInsertId();
    }

    public static function insertEvidencias(
        $pharma_id,
        $codigo,
        $usuario,
        $comentario,
        $path1,
        $path2,
        $fecha,
        $hora
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_evidencias( " .
            " pharma_id," .
            " codigo," .
            " usuario," .
            " comentario," .
            " foto_antes," .
            " foto_despues," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $pharma_id,
                $codigo,
                $usuario,
                $comentario,
                $path1,
                $path2,
                $fecha,
                $hora
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertEvidencias032023(
        $pharma_id,
        $codigo,
        $usuario,
        $comentario,
        $path1,
        $path2,
        $categoria,
        $fecha,
        $hora
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_evidencias( " .
            " pharma_id," .
            " codigo," .
            " usuario," .
            " comentario," .
            " foto_antes," .
            " foto_despues," .
            " categoria," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $pharma_id,
                $codigo,
                $usuario,
                $comentario,
                $path1,
                $path2,
                $categoria,
                $fecha,
                $hora
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertEvidenciasVentas(
        $pharma_id,
        $codigo,
        $usuario,
        $comentario,
        $path1,
        $path2,
        $categoria,
        $fecha,
        $hora
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_evidencias_ventas( " .
            " pharma_id," .
            " codigo," .
            " usuario," .
            " comentario," .
            " foto_antes," .
            " foto_despues," .
            " categoria," .
            " fecha," .
            " hora)" .
            " VALUES( ?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $pharma_id,
                $codigo,
                $usuario,
                $comentario,
                $path1,
                $path2,
                $categoria,
                $fecha,
                $hora
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertResultadoPreguntas(
        $user,
    	$test_id,
    	$test,
        $question,
        $opta,
		$optb,
		$optc,
		$answer,
		$answer_user,
		$result,
		$fecha,
		$hora
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_resultado_preguntas( " .
            " user," .
            " test_id," .
            " test," .
            " question," .
            " opta," .
            " optb," .
            " optc," .
            " answer," .
            " answer_user," .
            " result," .
            " fecha," .
            " hora)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $user,
                $test_id,
                $test,
                $question,
                $opta,
                $optb,
                $optc,
                $answer,
                $answer_user,
                $result,
                $fecha,
                $hora
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertResultadoPreguntasVentas(
        $user,
    	$test_id,
    	$test,
        $question,
        $opta,
		$optb,
		$optc,
		$answer,
		$answer_user,
		$result,
		$fecha,
		$hora
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_resultado_preguntas_ventas( " .
            " user," .
            " test_id," .
            " test," .
            " question," .
            " opta," .
            " optb," .
            " optc," .
            " answer," .
            " answer_user," .
            " result," .
            " fecha," .
            " hora)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $user,
                $test_id,
                $test,
                $question,
                $opta,
                $optb,
                $optc,
                $answer,
                $answer_user,
                $result,
                $fecha,
                $hora
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertPreguntas112022(
        $usuario,
        $test_id,
        $test,
        $p1,
        $p2,
        $p3,
        $p4,
        $p5,
        $p6,
        $p7,
        $p8,
        $p9,
        $p10,
        $p11,
        $p12,
        $p13,
        $p14,
        $p15,
        $correctas,
        $incorrectas,
        $calificacion,
        $observacion,
        $fecha,
        $hora,
        $cronometro
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_preguntas( " .
            "usuario," .
            " test," .
            " test_id," .
            " p1," .
            " p2," .
            " p3," .
            " p4," .
            " p5," .
            " p6," .
            " p7," .
            " p8," .
            " p9," .
            " p10," .
            " p11," .
            " p12," .
            " p13," .
            " p14," .
            " p15," .
            " correctas," .
            " incorrectas," .
            " calificacion," .
            " observacion," .
            " fecha," .
            " hora," .
            " cronometro)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $usuario,
                $test_id,
                $test,
                $p1,
                $p2,
                $p3,
                $p4,
                $p5,
                $p6,
                $p7,
                $p8,
                $p9,
                $p10,
                $p11,
                $p12,
                $p13,
                $p14,
                $p15,
                $correctas,
                $incorrectas,
                $calificacion,
                $observacion,
                $fecha,
                $hora,
                $cronometro
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertPreguntas092023(
        $usuario,
        $test_id,
        $test,
        $p1,
        $p2,
        $p3,
        $p4,
        $p5,
        $p6,
        $p7,
        $p8,
        $p9,
        $p10,
        $p11,
        $p12,
        $p13,
        $p14,
        $p15,
        $correctas,
        $incorrectas,
        $calificacion,
        $observacion,
        $fecha,
        $hora,
        $cronometro
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_preguntas( " .
            "usuario," .
            " test_id," .
            " test," .
            " p1," .
            " p2," .
            " p3," .
            " p4," .
            " p5," .
            " p6," .
            " p7," .
            " p8," .
            " p9," .
            " p10," .
            " p11," .
            " p12," .
            " p13," .
            " p14," .
            " p15," .
            " correctas," .
            " incorrectas," .
            " calificacion," .
            " observacion," .
            " fecha," .
            " hora," .
            " cronometro)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $usuario,
                $test_id,
                $test,
                $p1,
                $p2,
                $p3,
                $p4,
                $p5,
                $p6,
                $p7,
                $p8,
                $p9,
                $p10,
                $p11,
                $p12,
                $p13,
                $p14,
                $p15,
                $correctas,
                $incorrectas,
                $calificacion,
                $observacion,
                $fecha,
                $hora,
                $cronometro
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function insertPreguntasVentas(
        $usuario,
        $test_id,
        $test,
        $p1,
        $p2,
        $p3,
        $p4,
        $p5,
        $p6,
        $p7,
        $p8,
        $p9,
        $p10,
        $p11,
        $p12,
        $p13,
        $p14,
        $p15,
        $correctas,
        $incorrectas,
        $calificacion,
        $observacion,
        $fecha,
        $hora,
        $cronometro
    ) {
        $conexion = Database::getInstance()->getDb();
        // Sentencia INSERT
        $comando = "INSERT INTO insert_preguntas_ventas( " .
            "usuario," .
            " test," .
            " test_id," .
            " p1," .
            " p2," .
            " p3," .
            " p4," .
            " p5," .
            " p6," .
            " p7," .
            " p8," .
            " p9," .
            " p10," .
            " p11," .
            " p12," .
            " p13," .
            " p14," .
            " p15," .
            " correctas," .
            " incorrectas," .
            " calificacion," .
            " observacion," .
            " fecha," .
            " hora," .
            " cronometro)" .
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        // Preparar la sentencia
        $sentencia = $conexion->prepare($comando);

        $sentencia->execute(
            array(
                $usuario,
                $test_id,
                $test,
                $p1,
                $p2,
                $p3,
                $p4,
                $p5,
                $p6,
                $p7,
                $p8,
                $p9,
                $p10,
                $p11,
                $p12,
                $p13,
                $p14,
                $p15,
                $correctas,
                $incorrectas,
                $calificacion,
                $observacion,
                $fecha,
                $hora,
                $cronometro
            )
        );

        return $pdo = $conexion->lastInsertId();
    }

    public static function getRutero($user, $fecha) {
        $consulta = "SELECT estado, fecha_visita, hora_inicio, hora_fin,
                            hora_inicio_visita, hora_fin_visita,
                            pos_id, pos_name, estado_visitado, user, supervisor
                     FROM lvi_rutero
                     WHERE user = ? AND fecha_visita = ?
                     ORDER BY hora_inicio ASC";
        try {
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            $comando->execute(array($user, $fecha));
            return $comando->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

}


?>