<?php

/**
 * Representa el la estructura de las metas
 * almacenadas en la base de datos
 */
require 'Database.php';

class Funciones
{
    function __construct()
    {
    }
    
    /*
    * REPOSITORIOS
    */
    
    
    public static function getUsuarios($ciudad)
    {
        $consulta = "SELECT DISTINCT promotor FROM samsung_puntos WHERE status='1' AND ciudad=? AND NOT `promotor`='BALLADARES ANDREA' ORDER BY promotor ASC";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $ciudad
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function getCiudadesNew()
    {
        $consulta = "SELECT DISTINCT city FROM repositorio_locales_dtt WHERE activar='SI' ORDER BY city ASC";
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
    
    public static function getUsuariosnew($ciudad)
    {
        $consulta = "SELECT DISTINCT supervisor FROM repositorio_locales_dtt WHERE activar='SI' AND city=? ORDER BY supervisor ASC";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $ciudad
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function getUsnew($ciudad,$ciudades)
    {
        $consulta = "SELECT DISTINCT user FROM repositorio_locales_dtt WHERE activar='SI' AND supervisor=? AND city=? ORDER BY user ASC";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $ciudad,
                $ciudades
                
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function get_coordenadas($promotor, $fecha,$desde,$hasta)
    {
        $consulta = "SELECT * FROM insert_rastreo WHERE usuario= ? AND fecha= ? AND hora BETWEEN (?) AND (?) GROUP BY latitude,longitude";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $promotor, 
                $fecha,
                $desde,
                $hasta
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
    
    
    public static function get_registros($mercaderista, $fecha)
    {
        
                $consulta = "SELECT * FROM insert_registro  WHERE usuario = ? AND fecha = ? GROUP by tipo, fecha,id_pdv";
        try {
            // Preparar sentencia
            $comando = Database::getInstance()->getDb()->prepare($consulta);
            // Ejecutar sentencia preparada
            $comando->execute(array(
                $mercaderista, 
                $fecha
            ));

            return $comando->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return false;
        }
    }
    
}
?>