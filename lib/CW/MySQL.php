<?php
/**
 * CW_MySQL - Simplified database access class to provide quick, easy and secure queries.
 *
 * The MySQL class is intended to provide clients with a simplified set of SQL queries that should cover 80% of use
 * cases. It also provides more power via the query() method, where a custom query can be supplied - however, it is
 * expected that this method is only used as a last resort. For Centralwayers, using it will mean you're asked to
 * justify *why* you are using it!
 *
 * The standard SQL operations are available:
 *  select()
 *  update()
 *  insert()
 *
 * The following additional operations are available:
 *  selectRow() - selects a single row, returning the first result
 *
 * The following operations are *not* available:
 *  delete() - hard-deleting is not recommended, consider using a soft delete (i.e. a boolean column called isDeleted)
 *
 * More information can be found on github's wiki
 *
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * @version 0.1
 *
 */

class CW_MySQL
{
    //TODO: currently all exceptions are Exception; need to create a MySQLException class once all error scenarios are found

    protected static $_object = null;
    protected static $_db = null;

    protected function __construct() {
        //$config = xBoilerplate::getConfig()->db;
        //self::$_db = new mysqli($config['host'], $config['username'], $config['password'], $config['db']);
        self::$_db = new mysqli('localhost', 'root', '', 'xBoilerplate_additions');
    }

    /**
     * @param string $query
     * @return mysqli_result
     */
    public function query($query) {
        return self::$_db->query($query);
    }

    /**
     * @return CW_MySQL
     */
    public static function getInstance() {
        if (!self::$_object) {
            self::$_object = new self();
        }
        return self::$_object;
    }
    const NO_WHERE = null;
    const NO_ORDER = null;
    const NO_LIMIT = null;
    const NO_OBJECT = 'stdClass';

    private static $VALID_OPERATORS = array(
        '>', '<', '=', '!='
    );

    private function createWhere($where) {
        $whereClause = new WhereClause();
        if($where == self::NO_WHERE) {
            return $whereClause; // Early exit if no where clause
        }

        foreach($where as $columnName => $columnValue) {
            $name = trim($columnName);
            $needsEquals = stripos($name, ' ') === false;
            if($needsEquals) {
                $name .= ' =';
            }
            $name .= ' ? ';
            $targetType = $this->getType($columnValue);
            $value = $this->convertValue($columnValue, $targetType);
            $whereClause->addClause($name, $targetType, $value);
        }

        return $whereClause;
    }

    //TODO: This needs to handle type hinting
    private function convertValue($value, $targetType) {
        if($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        else {
            return $value; // No conversion necessary
        }
    }

    /**
     *
     *
     * @param $columns
     * @param $table
     * @param array $where
     * @param order
     * @param limit
     * @return mixed
     */
    public function select($columns, $table, $where = self::NO_WHERE, $order = self::NO_ORDER, $limit = self::NO_LIMIT,
                           $className = self::NO_OBJECT)
    {
        $columnFragment = implode(', ', $columns);
        $containsSelectAsterisk = stripos($columnFragment, '*') !== false;
        if($containsSelectAsterisk) {
            throw new Exception('Select * is not allowed');
        }

        $whereClause = $this->createWhere($where);

        $query = 'SELECT ' . $columnFragment . ' ';
        $query.= 'FROM ' . $table . ' ';
        if(!$whereClause->isEmpty()) {
            $query.= 'WHERE ';
            $query.= implode(' AND ', $whereClause->getConditions());
        }
//        user_error('Preparing query '. $query);
        echo 'select() - Preparing this query: ' . $query;
        $statement = self::$_db->prepare($query);
        if($statement === false) {
            throw self::createQueryException('Could not prepare query', self::$_db, $query);
        }

        if(!$whereClause->isEmpty()) {
            $values = array();
            foreach($whereClause->getValues() as $value) {
                $values[] = &$value;
            }
            $typeList = $whereClause->getTypeList();
            $functionParams = array_merge(array(&$typeList), $values);
            echo 'Calling function ';
            var_dump($functionParams);
            call_user_func_array(array($statement, 'bind_param'), $functionParams);
        }

        return $this->executeStatement($statement, $query, $className);
    }

    private function getType($value, $typeHint = null) {
        switch(gettype($value)) {
            case 'integer':
                return 'i';
            case 'double':
                return 'd';
            case 'string':
                return 's';
            case 'object':
                if($value instanceof DateTime) {
                    return 's';
                }
                else {
                    // Unsupported object type
                    //TODO: Add ability for custom type conversion?
                }
            default:
                throw new Exception('Unsupported type: ' . gettype($value));

        }
    }

    public function executeStatement(mysqli_stmt $statement, $query, $className)
    {
        if (!$statement->execute()) {
            throw self::createQueryException('Error executing query', $statement, $query);
        }

        $result = $statement->get_result();
        if ($result === false) {
            throw self::createQueryException('Error getting results', $statement, $query);
        }
        $results = array();
        while(($row = $result->fetch_object($className)) != null) {
            $results[] = $row;
        }
        $statement->close();

        return $results;
    }

    private static function createQueryException($message, $dbObject, $query) {
        return new Exception($message . ', error: '
            . $dbObject->error
            . ' for query: '
            . $query);
    }


    public function insert($table, array $data) {

    }

    public function delete($table, $where) {

    }

    public function update($table, array $data, $where) {

    }

    public function getLastInsertId() {
        return self::$_db->insert_id;
    }
}


class WhereClause {
    private $_typeList;
    private $_values;
    private $_conditions;

    public function __construct() {
        $this->_typeList = '';
        $this->_conditions = array();
        $this->_values = array();
    }

    public function addClause($condition, $type, $value) {
        $this->_conditions[] = $condition;
        $this->_values[] = $value;
        $this->_typeList .= $type;
        return $this;
    }

    public function getTypeList() {
        return $this->_typeList;
    }

    public function getValues() {
        return $this->_values;
    }

    public function getConditions() {
        return $this->_conditions;
    }

    public function isEmpty() {
        return sizeof($this->_conditions) == 0;
    }
}