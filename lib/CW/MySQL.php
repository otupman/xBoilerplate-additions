<?php
/**
 * CW_MySQL - implementation of CW_SQL supporting multiple databases (via PDO).
 *
 * If you are looking to use SQL, please refer to the documentation on CW_SQL (SQL.php)
 *
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * @version 0.4
 *
 */

class CW_MySQL extends CW_SQL
{
    /**
     * @static
     * @return CW_SQL
     * @deprecated
     */
    public static function getInstance() {
        return CW_SQL::getInstance();
    }

    /** Optionally used to signal an empty where clause
     * @deprecated use the value on CW_SQL
     */
    const NO_WHERE = CW_SQL::NO_WHERE;
    /** Optionally used to signal an empty order instruction
     * @deprecated use the value on CW_SQL  */
    const NO_ORDER = CW_SQL::NO_ORDER;
    /** Optionally used to signal that there is no limit set on the query
     * @deprecated use the value on CW_SQL */
    const NO_LIMIT = CW_SQL::NO_LIMIT;
    /** The default class instantiated to return results with
     * @deprecated use the value on CW_SQL */
    const STANDARD_CLASS = CW_SQL::STANDARD_CLASS;

    /** Sort operator: ascending
     * @deprecated use the value on CW_SQL */
    const OP_ASC = CW_SQL::OP_ASC;
    /** Sort operator: descending
     * @deprecated use the value on CW_SQL */
    const OP_DESC = CW_SQL::OP_DESC;


    /** Configuration key: database driver */
    const CONFIG_DRIVER = 'driver';

    /** Configuration value: MySQL driver */
    const DRIVER_MYSQL = 'mysql';


    /** The escape character to surround field names with */
    const STRING_ESCAPE = '`';

    private $config = array();

    protected function __construct() {
        $this->config = $this->loadConfig(xBoilerplate::getInstance());
        $this->openConnection($this->config);
    }

    /**
     * Opens the connection the MySQL server using the supplied configuration
     *
     * @param object $config the database configuration
     * @throws Exception in the event that there is an issue connecting to the database
     */
    protected function openConnection($config)
    {
        $dsn = 'mysql:dbname=' . $config['schema'] . ';host=' . $config['host'];
        $pdoOptions = array(PDO::ATTR_PERSISTENT => true);
        try {
            self::$_db = new PDO($dsn, $config['username'], $config['password'], $pdoOptions);
            self::$_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $ex) {
            throw new CW_SQLException('Error connecting to database: ' . $ex->getMessage(), 0, $ex);
        }
    }

    /**
     * Loads the configuration from xBoilerplate
     *
     * @param xBoilerplate $xBoilerplate the xBoilerplate instance
     * @return array|associative configuration entry for database details
     * @throws RuntimeException in the event that there is no 'db' property on the xBoilerplate configuration
     */
    protected function loadConfig(xBoilerplate $xBoilerplate)
    {
        $xConfig = $xBoilerplate->getConfig();

        if (!array_key_exists('db', $xConfig)) {
            throw new RuntimeException('Missing "db" property on configuration, have you setup it up correctly?');
        }

        $config = $xConfig->db;
        $this->checkConfig($config);

        $config = $this->setConfigOption((array)$config, CW_SQL::CONFIG_DEBUG, CW_SQL::DEBUG_NONE);
        $config = $this->setConfigOption((array)$config, self::CONFIG_DRIVER, self::DRIVER_MYSQL);

        return $config;
    }


    private function setConfigOption(array $config, $keyName, $default = null) {
        $config[$keyName] = (array_key_exists($keyName, $config)) ? $config[$keyName] : $default;
        return $config;
    }

    /**
     * Checks the supplied configuration for any missing config settings and throws an exception if any are found
     *
     * @param array|associative $dbConfig db configuration array
     * @throws RuntimeException if any required config key is not found
     */
    private function checkConfig($dbConfig) {
        $requiredKeys = array('username', 'password', 'host', 'schema');
        foreach($requiredKeys as $configKey) {
            if(!array_key_exists($configKey, $dbConfig)) {
                throw new RuntimeException('Required configuration key ' . $configKey . ' is not present on your db configuration');
            }
        }
    }

    /**
     * See CW_SQL::query()
     */
    public function query($query) {
        try {
            $statement = self::$_db->prepare($query);
        } catch(PDOException $ex) {
            throw self::createPdoException('Error preparing query ', $ex);
        }

        try {
            $statement->execute();
        } catch(PDOException $ex) {
            throw self::createPdoException('Error executing query', $ex);
        }

        return $statement;
    }

    private static function createPdoException($message, PDOException $ex) {
        return new CW_SQLException($message . $ex->getMessage(), 0, $ex);
    }


    /**
     * Builds the PrivateQueryParameters for the supplied associative array
     *
     * This takes a query parameter array and converts it into the set of 3
     * arrays.
     *
     * Incoming data is an associative array with key: column name, value: field value
     *
     * This method also determines the target type of the field based on the type of the
     * value passed in for each field.
     *
     * @param array|\associative $parameterArray associative array of parameters
     * @return PrivateQueryParameters
     */
    protected function createParameters(array $parameterArray) {
        $whereClause = new PrivateQueryParameters();
        if($parameterArray == self::NO_WHERE) {
            return $whereClause; // Early exit if no where clause
        }

        foreach($parameterArray as $columnName => $columnValue) {
            $name = $this->buildCondition($columnName);
            $targetType = $this->getType($columnValue);
            $value = $this->convertValue($columnValue, $targetType);
            $whereClause->addClause($name, $targetType, $value);
        }

        return $whereClause;
    }

    /**
     * Takes a raw condition passed in from the client and converts it to a valid conditional to go into the SQL
     * clause.
     *
     * Examples:
     * array('firstname' => 'someValue') - turns into `firstname` = ?
     * array('firstname >' => 'otherValue') - `firstname` > ?
     *
     * @param string $rawCondition string the raw condition passed in by the client
     * @return string the fully-built condition ready for SQL
     */
    protected function buildCondition($rawCondition) {
        $rawCondition = trim($rawCondition);
        $operator = '=';
        $columnName = $rawCondition;
        if(stripos($rawCondition, ' ') !== false) {
            $splitCondition = mb_split(' ', $rawCondition);
            $columnName = $splitCondition[0];
            $operator = $splitCondition[1];
        }
        else {
            // Will use defaults for $columnName and $operator
        }
        return self::escapeFieldName($columnName) . ' ' . $operator . ' ?';
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
     * See CW_SQL::select()
     */
    public function select(array $columns, $table, $where = self::NO_WHERE, $order = self::NO_ORDER, $limit = self::NO_LIMIT,
                           $className = self::STANDARD_CLASS)
    {
        $columnFragment = $this->sqlImplode($columns);
        $containsSelectAsterisk = stripos($columnFragment, '*') !== false;
        if($containsSelectAsterisk) {
            throw new CW_SQLException('Select * is not allowed');
        }

        $whereClause = $this->createParameters($where != null ? $where : array());
        $orderClauses = $this->buildOrderClauses($order != null ? $order : array());

        $query = 'SELECT ' . $columnFragment . ' ';
        $query.= 'FROM ' . $table . ' ';
        $query.= $this->generateWhere($whereClause, $query);

        if(sizeof($orderClauses) > 0) {
            $query.= ' ORDER BY ' . implode(', ', $orderClauses);
        }
        if($limit != null) {
            $query.= ' LIMIT ' . $this->createLimitSql($limit);
        }

        try {
            $statement = self::$_db->prepare($query);
        } catch(PDOException $ex) {
            throw self::createPdoException('Could not prepare query', $ex);
        }


        if(!$whereClause->isEmpty()) {
            $this->bindQueryParameters($whereClause->getValues(), $statement);
        }
//        $statement->debugDumpParams();
//        echo "Query parameters: ";
//        var_dump($where);
//        echo "\n";
        return $this->executeSelectStatement($statement, $query, $className);
    }

    protected function bindQueryParameters(array $whereValues, PDOStatement $statement)
    {
        $index = 1;
        foreach($whereValues as &$value) {
            $valueType = $this->getType($value);
            $value = $this->convertValue($value, $valueType);

            $statement->bindParam($index, $value, $valueType);
            $index++;
        }
        return $statement;
    }

    /**
     * Escapes a field name with the appropriate escape characters in order to prevent keyword issues
     *
     * @static
     * @param string $fieldName the field name to escape
     * @return string the properly escaped fieldname
     */
    public static function escapeFieldName($fieldName) {
        return self::STRING_ESCAPE . $fieldName . self::STRING_ESCAPE;
    }

    /**
     * Implodes a series of column names in an SQL-compatible fashion, preventing any issues with reserved words
     *
     * @param array $items an array of column names
     * @param string $glue optional; glue to stick the elements together
     * @return string the imploded array
     */
    private function sqlImplode(array $items, $glue = ', ') {
        $implodedData = '';
        foreach($items as $item) {
            if(strlen($implodedData) > 0) {
                $implodedData.= $glue;
            }
            $implodedData .= self::escapeFieldName($item);
        }
        return $implodedData;
    }

    /**
     * Builds the order clauses from the incoming data, if any are present
     *
     * @param array $order the order to build from
     * @return array containing the build SQL order clauses
     * @throws InvalidArgumentException in the event that any of the incoming data is invalid (i.e. ASK instead of ASC)
     */
    private function buildOrderClauses($order)
    {
        $orderClauses = array();

        if ($order != null) {
            array_walk($order, function($val, $key) use (&$orderClauses)
            {
                $val = strtoupper(trim($val));
                if ($val != CW_MySQL::OP_ASC && $val != CW_MySQL::OP_DESC) {
                    throw new InvalidArgumentException('Order must either be ASC or DESC. Order column: ' . $key);
                }
                $orderClauses[] = CW_MySQL::escapeFieldName($key) . ' ' . $val;
            });
            return $orderClauses;
        }
        return $orderClauses;
    }

    /**
     * See CW_SQL::selectRow()
     */
    public function selectRow(array $columns, $table, array $where, $className = self::STANDARD_CLASS) {
        if(sizeof($where) == 0) {
            throw new InvalidArgumentException('Where clause cannot be an empty array');
        }

        $resultArray = $this->select($columns, $table, $where, self::NO_ORDER, 1);
        if(sizeof($resultArray) > 0) {
            return $resultArray[0];
        }
        else {
            return null;
        }
    }

    /**
     * Creates the SQL for the parameters to a LIMIT clause
     *
     * @param mixed $limit the incoming limit arguments
     * @return string the string ready to be appended to a LIMIT clause
     * @throws InvalidArgumentException in the event that the incoming limit arguments are not correct
     */
    private function createLimitSql($limit)
    {
        if (is_numeric($limit)) {
            $limitValue = $limit;
        }
        else if (is_array($limit) && sizeof($limit) == 2) {
            $limitValue = $limit[0] . ',' . $limit[1];
        }
        else {
            throw new InvalidArgumentException('Limit must either be a single numeric, or a 2-element array of integers');
        }
        return $limitValue;
    }

    /**
     * Generates a 'WHERE x,y,z' clause
     *
     * @param PrivateQueryParameters $whereClause there where parameters
     * @return string the string of the where clause, if parameters are present; otherwise, a empty string
     */
    private function generateWhere($whereClause)
    {
        $query = '';
        if (!$whereClause->isEmpty()) {
            $query .= 'WHERE ';
            $query .= implode(' AND ', $whereClause->getConditions());
            return $query;
        }
        return $query;
    }

    /**
     * Gets the appropriate type string for the supplied value that is an instance of an object.
     *
     * This method can be overridden by deriving classes to provide custom-type handling
     *
     * @param object $value the value to get the type of; must be an object
     * @param mixed $typeHint the hint TODO: really needed?
     * @return string the single-character type value for the object
     * @throws Exception if the type of the value is not supported
     */
    protected function getObjectType($value, $typeHint) {
        if($value instanceof DateTime) {
            return PDO::PARAM_STR;
        }
        else {
            throw new CW_SQLException('Unsupported type of ' . get_class($value));
        }
    }

    /**
     * Obtains the mysqli type character for the supplied value, using any hinting provided
     *
     * @param mixed $value the value to get the type of
     * @param string $typeHint name of the type that the value is
     * @return string the single character representation of the type
     * @throws Exception in the event the type of the value is unsupported
     */
    protected function getType($value, $typeHint = null) {
        switch(gettype($value)) {
            case 'integer':
                return PDO::PARAM_INT;
            case 'double':
                return PDO::PARAM_INT;
            case 'string':
                return PDO::PARAM_STR;
            case 'object':
                return $this->getObjectType($value, $typeHint);
            default:
                throw new CW_SQLException('Unsupported type: ' . gettype($value));
        }
    }

    /**
     * executes the statement of the supplied query, retriving rows with the class name
     *
     * @param PDOStatement $statement
     * @param string $query
     * @param string $className
     * @return array
     * @throws Exception
     */
    private function executeSelectStatement(PDOStatement $statement, $query, $className)
    {
        $this->executeStatement($statement);
        $results = $this->_buildResults($statement, $className);
        return $results;
    }

    private function logQuery(PDOStatement $statement) {
        $debugOption = $this->config[CW_SQL::CONFIG_DEBUG];
        if($debugOption == CW_SQL::DEBUG_NONE) {
            return;
        }

        ob_start();
        $statement->debugDumpParams();
        $statementDebugInfo = ob_get_clean();

        switch($debugOption) {
            case CW_SQL::DEBUG_LOG:
                user_error('Query executed; info: ' . $statementDebugInfo, E_USER_NOTICE);
                break;
            case CW_SQL::DEBUG_STORELAST:
                $this->lastQuery = $statementDebugInfo;
                break;
        }
    }

    private function executeStatement(PDOStatement $statement) {
        try {
            $this->logQuery($statement);
            $statement->execute();
        } catch(PDOException $ex) {
            throw self::createPdoException('Error executing query', $ex);
        }
    }

    const TYPECODE_DATETIME = 12;

    /** MySQL-specific DATETIME type name */
    const _MYSQL_TYPE_DATETIME = 'DATETIME';

    /**
     * Determines if the type code supplied is for a datetime or not
     *
     * @param integer $fieldTypeCode the code from a fetch_field_direct call
     * @return bool true if the code is for a DATETIME; otherwise false
     */
    private function isDateField($nativeTypename, $config) {
        if($config[self::CONFIG_DRIVER] == self::DRIVER_MYSQL) {
            return $nativeTypename == self::_MYSQL_TYPE_DATETIME;
        }
    }

    /**
     * Builds the result array from a result, performing any required output type conversion (i.e. DATETIME=>DateTime)
     *
     * @param mysqli_result $result the result
     * @param string $className the name of the class to instantiate when fetching
     * @return array array of fetched results
     * @throws Exception in the event there is a problem converting a type
     */
    private function _buildResults(PDOStatement $result, $className)
    {
        $dateFieldNames = array();
        for($i = 0; $i < $result->columnCount(); $i++) {
            //$fieldMetadata = $result->fetch_field_direct($i);
            $fieldMetadata = $result->getColumnMeta($i);
            $columnNativeTypeName = $fieldMetadata['native_type'];
            if($this->isDateField($columnNativeTypeName, $this->config)) {
                $dateFieldNames[] = $fieldMetadata['name'];
            }
        }
        $results = array();
        while (($row = $result->fetchObject($className)) !== false) {
            foreach($dateFieldNames as $fieldName) {
                try {
                    $row->$fieldName = new DateTime($row->$fieldName);
                } catch(Exception $ex) {
                    throw new CW_SQLException('Error parsing DateTime - ' . $ex->getMessage() . ' - and the value was: [' . $row->$fieldName . '] on column [' . $fieldName . ']');
                }
            }

            $results[] = $row;
        }
        return $results;
    }


    /**
     * Simple function that creates an instance of an exception with a message and adds the relevant mysqli error to it
     *
     *
     * @static
     * @param string $message the message to be reported
     * @param mysqli $dbObject the mysqli object that experienced the error
     * @param string $query the query that was/should be executed
     * @return Exception the exception, ready for throwing
     */
    protected static function createQueryException($message, $dbObject, $query) {
        return new Exception($message . ', error: '
            . $dbObject->error
            . ' for query: '
            . $query);
    }
    private function _createPlaceholders($numberOfValues) {
        $values = '';

        for($i=1; $i <= $numberOfValues; $i++) {
            $values .= '?, ';
        }
        $values = substr($values, 0, -2);
        return $values;
    }

    private function _checkTypeOfValues(array $data) {
        foreach ($data as $item) {
            $result[] = $item;
        }
        foreach($result as $type) {
            $dataType[] = gettype($type);
        }

        return $dataType;
    }



    public function insert($table, array $data) {
        //create prepare statement, etc. INSERT INTO `people` (`firstname`, `lastname`, `age`, `createdDate`) VALUES (?, ?, ?, ?)
        $columnNames = $this->buildInsertColumns($data);
        $parameterPlaceholders = $this->_createPlaceholders(count($data));

        $query = 'INSERT INTO `'.$table.'` ' .$columnNames;
        $query.= ' VALUES ('.$parameterPlaceholders.')';

        $statement = $this->prepareQuery($query);

        $whereClause = $this->createParameters($data);

        $this->bindQueryParameters($whereClause->getValues(), $statement);

        $this->executeStatement($statement);
        return self::$_db->lastInsertId();
    }

    private function prepareQuery($query)
    {
        try {
            $statement = self::$_db->prepare($query);
            return $statement;
        } catch (PDOException $ex) {
            throw self::createPdoException('Could not prepare query', $ex);
        }
    }

    private function buildInsertColumns($data)
    {
        $keys = array_keys($data);
        $dbColumnName = '(';
        foreach ($keys as $key) {
            $dbColumnName .= '`' . $key . '`, ';
        }
        $dbColumnName = substr($dbColumnName, 0, -2);
        $dbColumnName .= ')';
        return $dbColumnName;
    }

    public function delete($table, $where) {
        throw new  Exception("Unsupported operation: please consider implementing soft-delete.");
    }

    public function update($table, array $data, array $where) {
        $columnNames = array_keys($data);
        $variablesSet = '';
        foreach($columnNames as $item) {
            $variablesSet .= $item .'=?, ';
        }
        $variablesSet = substr($variablesSet, 0, -2);

        $whereNames = array_keys($where);
        $variableWhere = '';
        foreach($whereNames as $item) {
            $variableWhere .=$item .'=? ';
        }
        $variableWhere = substr($variableWhere, 0, -1);

        $update = 'UPDATE '.$table;
        $set = ' SET ' .$variablesSet;
        $whereP = ' WHERE ' .$variableWhere;
        $sql = $update.$set.$whereP;

        try {
            $stmt = self::$_db->prepare($sql);
        } catch(PDOException $ex) {
            throw self::createPdoException('Error preparing update query', $ex);
        }


        $whereType = $this->_checkTypeOfValues($where);
        $dataType = $this->_checkTypeOfValues($data);
        $t = array_merge($dataType, $whereType);

        $type = '';
        //getting first letter from each of value type
        foreach($t as $word) {
            $letter = substr($word, 0, 1);
            $type .= $letter;
        }

        $clause = array_merge($data, $where);
        $this->bindQueryParameters($clause, $stmt);

        $this->executeStatement($stmt);

        return $stmt->rowCount();
    }

    public function getLastInsertId() {
        return self::$_db->lastInsertId();
    }
}

/**
 * Private class; this should never be used outside of the MySQL class.
 *
 * It encapsulates the 3 axises relevant to a successful query:
 *  1. type(list) - mysqli-compatible type list (i.e. sddsssb)
 *  2. values - an array of values
 *  3. conditions - an array of the conditions (i.e. firstname = ?)
 *
 * This class should NEVER be used outside of the MySQL class.
 */
class PrivateQueryParameters {
    private $_typeList;
    private $_values;
    private $_conditions;

    public function __construct() {
        $this->_typeList = '';
        $this->_conditions = array();
        $this->_values = array();
    }

    /**
     * Adds an additional query clause to the parameters
     *
     * @param string $condition the condition; must be in the format [fieldname] [operator] ?
     * @param integer $type the mysqli type of the value
     * @param mixed $value the value
     * @return PrivateQueryParameters this for further building
     */
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
