<?php

/**
 * EXHIBIT A. Common Public Attribution License Version 1.0
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the “License”);
 * you may not use this file except in compliance with the License. You may obtain a copy of the License at
 * http://www.oxwall.org/license. The License is based on the Mozilla Public License Version 1.1
 * but Sections 14 and 15 have been added to cover use of software over a computer network and provide for
 * limited attribution for the Original Developer. In addition, Exhibit A has been modified to be consistent
 * with Exhibit B. Software distributed under the License is distributed on an “AS IS” basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for the specific language
 * governing rights and limitations under the License. The Original Code is Oxwall software.
 * The Initial Developer of the Original Code is Oxwall Foundation (http://www.oxwall.org/foundation).
 * All portions of the code written by Oxwall Foundation are Copyright (c) 2011. All Rights Reserved.

 * EXHIBIT B. Attribution Information
 * Attribution Copyright Notice: Copyright 2011 Oxwall Foundation. All rights reserved.
 * Attribution Phrase (not exceeding 10 words): Powered by Oxwall community software
 * Attribution URL: http://www.oxwall.org/
 * Graphic Image as provided in the Covered Code.
 * Display of Attribution Information is required in Larger Works which are defined in the CPAL as a work
 * which combines Covered Code or portions thereof with code not governed by the terms of the CPAL.
 */

/**
 * Core database connection class.
 *
 * @author Sardar Madumarov <madumarov@gmail.com>, Nurlan Dzhumakaliev <nurlanj@live.com>
 * @package ow_core
 * @since 1.0
 */
class OW_Database
{
    /**
     * @var array
     */
    private static $classInstances;
    /**
     * Mysql connection object
     *
     * @var PDO
     */
    private $connection;
    /**
     * Number of rows affected by the last SQL statement
     *
     * @var int
     */
    private $affectedRows;
    /**
     * Logger data
     *
     * @var array
     */
    private $queryLog;
    /**
     * Debug option
     *
     * @var boolean
     */
    private $debugMode;
    /**
     * Enter description here...
     *
     * @var boolean
     */
    private $isProfilerEnabled;
    /**
     * Enter description here...
     *
     * @var UTIL_Profiler
     */
    private $profiler;
    /**
     * Last executed query
     *
     * @var int
     */
    private $queryExecTime;
    /**
     * Enter description here...
     *
     * @var int
     */
    private $totalQueryExecTime;
    /**
     *
     * @var int
     */
    private $queryCount;

    /**
     * Getter for $log property
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Getter for $queryExecTime property
     *
     * @return int
     */
    public function getQueryExecTime()
    {
        return $this->queryExecTime;
    }

    public function getTotalQueryExecTime()
    {
        return $this->totalQueryExecTime;
    }

    /**
     * @return int
     */
    public function getQueryCount()
    {
        return $this->queryCount;
    }

    /**
     * @return string
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * Constructor.
     *
     * @param array $params
     */
    private function __construct( $params )
    {
        $port = isset($params['port']) ? (int) $params['port'] : null;
        $socket = isset($params['socket']) ? $params['socket'] : null;

        try
        {
            if ( $socket === null )
            {
                $dsn = "mysql:host={$params['host']};";
                if ( $port !== null )
                {
                    $dsn .= "port={$params['port']};";
                }
            }
            else
            {
                $dsn = "mysql:unix_socket={$socket};";
            }
            $dsn .= "dbname={$params['dbname']}";

            $this->connection = new PDO($dsn, $params['username'], $params['password'],
                    array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8',
                        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true));

            if ( !$this->isMysqlValidVersion() )
            {
                throw new InvalidArgumentException("Cant connect to database. Connection needs MySQL version 5.0 + !");
            }

            if ( !empty($params['profilerEnable']) )
            {
                $this->isProfilerEnabled = true;
                $this->profiler = UTIL_Profiler::getInstance('db');
                $this->queryCount = 0;
                $this->queryExecTime = 0;
                $this->totalQueryExecTime = 0;
                $this->queryLog = array();
            }

            if ( !empty($params['debugMode']) )
            {
                $this->debugMode = true;
            }

            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch ( PDOException $e )
        {
            throw new InvalidArgumentException($e->getMessage());
        }
    }

    /**
     * Returns the instance of class for $params
     *
     * @param array $params ( host, username, password, dbname, [socket], [port] )
     *
     * @return OW_Database
     *
     */
    public static function getInstance( $params )
    {
        if ( !isset(self::$classInstances) )
        {
            self::$classInstances = array();
        }

        ksort($params);

        $connectionKey = serialize($params);

        if ( empty(self::$classInstances[$connectionKey]) )
        {
            if ( !isset($params['host']) || !isset($params['username']) || !isset($params['password']) || !isset($params['dbname']) )
            {
                throw new InvalidArgumentException("Can't connect to database. Please provide valid connection attributes.");
            }

            self::$classInstances[$connectionKey] = new self($params);
        }

        return self::$classInstances[$connectionKey];
    }

    /**
     * Check if MySQL version is 5+
     *
     * @return boolean
     */
    private function isMysqlValidVersion()
    {
        $verArray = explode('.', $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION));
        return $verArray[0] == 5;
    }

    /**
     * Escapes SQL string
     *
     * @param string $string
     * @return string
     */
    public function escapeString( $string )
    {
        $quotedString = $this->connection->quote($string); // real_escape_string( $string );
        return mb_substr($quotedString, 1, mb_strlen($quotedString) - 2); //dirty hack to delete quotes
    }

    /**
     * Returns affected rows
     *
     * @return integer
     */
    public function getAffectedRows()
    {
        return $this->affectedRows;
    }

    /**
     * Returns last insert id
     *
     * @return integer
     */
    public function getInsertId( $seqname = null )
    {
        return $this->connection->lastInsertId($seqname);
        ;
    }

    /**
     * Class destruct actions
     */
    public function __destruct()
    {
        if ( isset($this->connection) )
        {
            $this->connection = null;
        }
    }

    /**
     * Returns current PDOStatement
     *
     * @return PDOStatement
     */
    protected function execute( $sql, array $params = null )
    {
        if ( $this->isProfilerEnabled )
        {
            $this->profiler->reset();
        }

        /* @var $stmt PDOStatement */
        $stmt = $this->connection->prepare($sql);
        if ( $params !== null )
        {
            foreach ( $params as $key => $value )
            {
                $paramType = PDO::PARAM_STR;
                if ( is_int($value) )
                    $paramType = PDO::PARAM_INT;
                elseif ( is_bool($value) )
                    $paramType = PDO::PARAM_BOOL;

                $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $paramType);
            }
        }
        $stmt->execute(); //TODO setup profiler
        $this->affectedRows = $stmt->rowCount();

        if ( $this->isProfilerEnabled )
        {
            $this->queryExecTime = $this->profiler->getTotalTime();
            $this->totalQueryExecTime += $this->queryExecTime;

            $this->queryCount++;
            $this->queryLog[] = array('query' => $sql, 'execTime' => $this->queryExecTime, 'params' => $params);
        }

        return $stmt;
    }

    public function queryForColumn( $sql, array $params = null )
    {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetchColumn(); // (PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        return ($result === false) ? null : $result;
    }

    /**
     * Enter description here...
     *
     * @param string $sql
     * @param unknown_type $className
     * @param array $params
     * @return mixed
     */
    public function queryForObject( $sql, $className, array $params = null )
    {
        $stmt = $this->execute($sql, $params);
        $stmt->setFetchMode(PDO::FETCH_CLASS, $className);
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return ($result === false) ? null : $result;
    }

    public function queryForObjectList( $sql, $className, array $params = null )
    {
        $stmt = $this->execute($sql, $params);

        return $stmt->fetchAll(PDO::FETCH_CLASS, $className);
    }

    public function queryForRow( $sql, array $params = null )
    {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return ($result === false) ? array() : $result;
    }

    /**
     * Enter description here...
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function queryForList( $sql, array $params = null )
    {
        $stmt = $this->execute($sql, $params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function queryForColumnList( $sql, array $params = null )
    {
        $stmt = $this->execute($sql, $params);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Enter description here...
     *
     * @param string $sql
     * @param array $params
     * @return int
     */
    public function query( $sql, array $params = null )
    {
        $stmt = $this->execute($sql, $params);
        $rowCount = $stmt->rowCount();
        $stmt->closeCursor();

        return $rowCount;
    }

    public function delete( $sql, array $params = null )
    {
        return $this->query($sql, $params);
    }

    /**
     * insert data and return last insert id
     *
     * @param string $sql
     * @param array $params
     * @return int last_insert_id
     */
    public function insert( $sql, array $params = null )
    {
        $stmt = $this->execute($sql, $params);
        $lastInsertId = $this->connection->lastInsertId();
        $stmt->closeCursor();

        return $lastInsertId;
    }

    /**
     * Enter description here...
     *
     * @param unknown_type $sql
     * @param array $params
     * @return unknown
     */
    public function update( $sql, array $params = null )
    {
        return $this->query($sql, $params);
    }

    /**
     * Insert object $obj to table $tableName. Returns last_insert_id
     * throws InvalidArgumentException
     *
     * @param string $tableName
     * @param object $obj
     * @return int
     */
    public function insertObject( $tableName, $obj )
    {
        if ( $obj != null && is_object($obj) )
        {
            $params = get_object_vars($obj);
            $paramNames = array_keys($params);
            $columns = UTIL_String::arrayToDelimitedString($paramNames, ',', '`', '`');
            $values = UTIL_String::arrayToDelimitedString($paramNames, ',', ':');
            $sql = "INSERT INTO `{$tableName}` ({$columns}) VALUES ({$values})";

            return $this->insert($sql, $params);
        }
        else
        {
            throw new InvalidArgumentException('object expected');
        }
    }

    public function updateObject( $tableName, $obj, $primaryKeyName = 'id' )
    {
        if ( $obj != null && is_object($obj) )
        {
            $params = get_object_vars($obj);
            if ( !array_key_exists($primaryKeyName, $params) )
            {
                throw new InvalidArgumentException('object property not found');
            }

            $updateArray = array();
            foreach ( $params as $key => $value )
            {
                if ( $key !== $primaryKeyName )
                {
                    $updateArray[] = '`' . $key . '`=:' . $key;
                }
            }
            $updateStmt = UTIL_String::arrayToDelimitedString($updateArray);
            $sql = "UPDATE `{$tableName}` SET {$updateStmt} WHERE {$primaryKeyName}=:{$primaryKeyName}";
            return $this->update($sql, $params);
        }
        else
        {
            throw new InvalidArgumentException('object expected');
        }
    }

    public function mergeInClause( array $valueList )
    {
        if ( $valueList === null )
        {
            return '';
        }

        $result = '';
        foreach ( $valueList as $value )
        {
            $result .= ( '\'' . $this->escapeString($value) . '\',' ); //"'$value',"
        }
        $result = mb_substr($result, 0, mb_strlen($result) - 1);

        return $result;
    }

    public function batchInsertOrUpdateObjectList( $tableName, $objects, $batchSize = 50 )
    {
        if ( $objects != null && is_array($objects) )
        {
            if ( count($objects) > 0 )
            {
                $columns = '';
                $paramNames = array();

                if ( is_object($objects[0]) )
                {
                    $params = get_object_vars($objects[0]);
                    $paramNames = array_keys($params);
                    $columns = UTIL_String::arrayToDelimitedString($paramNames, ',', '`', '`');
                }
                else
                {
                    throw new InvalidArgumentException('Array of objects expected');
                }

                $i = 0;
                $totalInsertsCount = 0;
                $objectsCount = count($objects);
                $batchSize = (int) $batchSize;
                $inserts = array();

                foreach ( $objects as $obj )
                {
                    $values = '(';
                    foreach ( $paramNames as $property )
                    {
                        if ( $obj->$property !== null )
                        {
                            $values .= ( '\'' . $this->escapeString($obj->$property) . '\',' );
                        }
                        else
                        {
                            $values .= 'NULL,';
                        }
                    }
                    $values = mb_substr($values, 0, mb_strlen($values) - 1);
                    $values .= ')';
                    $inserts[] = $values;

                    $i++;
                    $totalInsertsCount++;

                    if ( $i === $batchSize || $totalInsertsCount === $objectsCount )
                    {
                        $sql = "REPLACE INTO `{$tableName}` ({$columns}) VALUES" . implode(',', $inserts);
                        $inserts = array();
                        $i = 0;
                        $this->connection->query($sql)->closeCursor();
                    }
                }
            }
        }
        else
        {
            throw new InvalidArgumentException('Array expected');
        }
    }
}
