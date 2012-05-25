<?php
/**
 * Initial blank version, ready for update.
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
    /**
     * @param $table
     * @param $attrs
     * @param array $where
     * @return mixed
     */
    public function select($columns, $table, $where = self::NO_WHERE, $order =self::NO_ORDER, $limit = self::NO_LIMIT,
                           $className = self::NO_OBJECT)
    {
        $columnFragment = implode(', ', $columns);
        $containsSelectAsterisk = stripos($columnFragment, '*') !== false;
        if($containsSelectAsterisk) {
            throw new Exception('Select * is not allowed');
        }

        $whereClauses = array();

        if($where != self::NO_WHERE) {
            if(!is_array($where)) {
                throw new Exception('Where clause must be an array');
            }
            foreach($where as $column => $columnValue) {
                $column = trim($column);
                $hasOperator = stripos($column, ' ') != -1;
            }
        }

        $query = 'SELECT ' . $columnFragment . ' ';
        $query.= 'FROM ' . $table . ' ';
//        user_error('Preparing query '. $query);

        $statement = self::$_db->prepare($query);
        if($statement === false) {
            throw self::createQueryException('Could not prepare query', self::$_db, $query);
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
                    return 'd';
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
