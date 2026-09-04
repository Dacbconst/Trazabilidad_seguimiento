<?php
/**
 * Copyright (C) 2019 Phppot
 *
 * Distributed under MIT license with an exception that,
 * you don’t have to include the full MIT License in your code.
 * In essense, you can use it on commercial software, modify and distribute free.
 * Though not mandatory, you are requested to attribute this URL in your code or website.
 */
namespace Phppot;

/**
 * Generic datasource class for handling DB operations.
 * Uses MySqli and PreparedStatements.
 *
 * @version 2.5 - recordCount function added
 */
class DataSource
{

    // PHP 7.1.0 visibility modifiers are allowed for class constants.
    // when using above 7.1.0, declare the below constants as private
    const HOST = 'mysqlecuadorsf.mysql.database.azure.com';

    const USERNAME = 'xplora_mysql';

    const PASSWORD = 'XpL0r@Ec8Ad0R..';

    const DATABASENAME = 'luckyec_jaboneria_wilson';

    private $conn;

    /**
     * PHP implicitly takes care of cleanup for default connection types.
     * So no need to worry about closing the connection.
     *
     * Singletons not required in PHP as there is no
     * concept of shared memory.
     * Every object lives only for a request.
     *
     * Keeping things simple and that works!
     */
    function __construct()
    {
        $this->conn = $this->getConnection();
    }

    /**
     * If connection object is needed use this method and get access to it.
     * Otherwise, use the below methods for insert / update / etc.
     *
     * @return \mysqli
     */
    public function getConnection()
    {
        $conn = new \mysqli(self::HOST, self::USERNAME, self::PASSWORD, self::DATABASENAME);

        if (mysqli_connect_errno()) {
            trigger_error("Problem with connecting to database.");
        }

        $conn->set_charset("latin1");
        return $conn;
    }

    public function getLastError()
    {
        return $this->conn->error;
    }


    /**
     * To get database results
     *
     * @param string $query
     * @param string $paramType
     * @param array $paramArray
     * @return array
     */
    public function select($query, $params = [])
{
    $stmt = $this->conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Error al preparar: " . $this->conn->error);
    }

    if (!empty($params)) {
        if (substr_count($query, '?') !== count($params)) {
            throw new Exception("Número de parámetros no coincide con los placeholders: " . count($params) . " enviados vs " . substr_count($query, '?') . " esperados");
        }

        $types = str_repeat('i', count($params)); // todos enteros (int)
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Falló execute(): " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception("Falló get_result(): " . $stmt->error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}


	
	/**
     * To truncate table
     *
     * @param string $query
     * @param string $paramType
     * @param array $paramArray
     * @return array
     */
    public function truncate($query)
    {
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
    }

    /**
     * To insert
     *
     * @param string $query
     * @param string $paramType
     * @param array $paramArray
     * @return int
     */
    public function insert($query, $paramType, $paramArray)
    {


        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            return "Error al preparar la consulta: " . $this->conn->error;
        }
        
        $this->bindQueryParams($stmt, $paramType, $paramArray);
    
        if ($stmt->execute() === false) {
            return "Error al ejecutar la consulta: " . $stmt->error;
        }
    
        
        $insertId = $stmt->insert_id;
        return $insertId;

        // $stmt = $this->conn->prepare($query);
        // if ($stmt === false) {
        //     return "Error al preparar la consulta: " . $this->conn->error;
        // }
        // $this->bindQueryParams($stmt, $paramType, $paramArray);

        // if ($stmt->execute() === false) {
        //     return "Error al ejecutar la consulta: " . $stmt->error;
        // }

        // $stmt->execute();
        // $insertId = $stmt->insert_id;
        // return $insertId;
    }

    /**
     * To update
     *
     * @param string $query
     * @param string $paramType
     * @param array $paramArray
     */
    public function update($query, $paramType = "", $paramArray = array())
    {
        $stmt = $this->conn->prepare($query);
        $update = false;
        if (! empty($paramType) && ! empty($paramArray)) {
            $this->bindQueryParams($stmt, $paramType, $paramArray);
        }
        if ($stmt->execute()) {
            $update = true;
        }
        return $update;
    }

    /**
     * To execute query
     *
     * @param string $query
     * @param string $paramType
     * @param array $paramArray
     */
    public function execute($query, $paramType = "", $paramArray = array())
    {
        $stmt = $this->conn->prepare($query);

        if (! empty($paramType) && ! empty($paramArray)) {
            $this->bindQueryParams($stmt, $paramType, $paramArray);
        }
        $stmt->execute();
    }

    /**
     * 1.
     * Prepares parameter binding
     * 2. Bind prameters to the sql statement
     *
     * @param string $stmt
     * @param string $paramType
     * @param array $paramArray
     */
    public function bindQueryParams($stmt, $paramType, $paramArray = array())
    {
        $paramValueReference[] = & $paramType;
        for ($i = 0; $i < count($paramArray); $i ++) {
            $paramValueReference[] = & $paramArray[$i];
        }
        call_user_func_array(array(
            $stmt,
            'bind_param'
        ), $paramValueReference);
    }

    /**
     * To get database results
     *
     * @param string $query
     * @param string $paramType
     * @param array $paramArray
     * @return array
     */
    public function getRecordCount($query, $paramType = "", $paramArray = array())
    {
        $stmt = $this->conn->prepare($query);
        if (! empty($paramType) && ! empty($paramArray)) {

            $this->bindQueryParams($stmt, $paramType, $paramArray);
        }
        $stmt->execute();
        $stmt->store_result();
        $recordCount = $stmt->num_rows;

        return $recordCount;
    }
}