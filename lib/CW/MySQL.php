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
 * More information can be found on github's wiki page here:
 *  https://github.com/centralway/xBoilerplate-additions/wiki/MySQL
 *
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * @version 0.2
 *
 */

class CW_MySQL
{
    //TODO: currently all exceptions are Exception; need to create a MySQLException class once all error scenarios are found
    /**
     * the static instance of the class
     * @var CW_MySQL
     */
    protected static $_object = null;
    /**
     *
     * The static mysqli instance that the class uses
     *
     * @var mysqli
     */
    protected static $_db = null;


    /**
     * Obtains the static instance of the CW_MySQL class.
     *
     * @return CW_MySQL
     */
    public static function getInstance() {
        if (!self::$_object) {
            self::$_object = new self();
        }
        return self::$_object;
    }


    /** Optionally used to signal an empty where clause */
    const NO_WHERE = null;
    /** Optionally used to signal an empty order instruction  */
    const NO_ORDER = null;
    /** Optionally used to signal that there is no limit set on the query */
    const NO_LIMIT = null;
    /** The default class instantiated to return results with */
    const STANDARD_CLASS = 'stdClass';

    /** The escape character to surround field names with */
    const STRING_ESCAPE = '`';


    /** Sort operator: ascending */
    const OP_ASC = 'ASC';
    /** Sort operator: descending */
    const OP_DESC = 'DESC';

    private static $VALID_OPERATORS = array(
        '>', '<', '=', '!='
    );

    protected function __construct() {
        $config = $this->loadConfig(xBoilerplate::getInstance());
        $this->openConnection($config);
    }

    /**
     * Opens the connection the MySQL server using the supplied configuration
     *
     * @param $config the database configuration
     * @throws Exception in the event that there is an issue connecting to the database
     */
    protected function openConnection($config)
    {
        self::$_db = new mysqli($config->host, $config->username, $config->password, $config->schema);
        if (self::$_db === false) {
            throw new Exception('Error connecting to database: ' . mysqli_error(self::$_db));
        }
    }

    /**
     * Loads the configuration from xBoilerplate
     *
     * @param $xBoilerplate the xBoilerplate instance
     * @return configuration entry for database details
     * @throws RuntimeException in the event that there is no 'db' property on the xBoilerplate configuration
     */
    protected function loadConfig($xBoilerplate)
    {
        $xConfig = $xBoilerplate->getConfig();

        if (!array_key_exists('db', $xConfig)) {
            throw new RuntimeException('Missing "db" property on configuration, have you setup it up correctly?');
        }

        $config = (object)$xConfig->db;
        $this->checkConfig($config);
        return $config;
    }

    /**
     * Checks the supplied configuration for any missing config settings and throws an exception if any are found
     *
     * @param $dbConfig db configuration array
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
     * Performs a raw-query upon the database.
     *
     * This method gives you full control over your access to the database, however it is unprotected and not
     * recommended for 80% of applications and queries.
     *
     * It is left to the calling code to ensure that the data in the query is safe to execute. In addition, this
     * method does not return an array of results but the raw mysqli_result object.
     *
     * If you are a Centralwayer, prepare to justify your use of this method.
     *
     * @param string $query the query to execute
     * @throws Exception in the event of any SQL exceptions occur
     * @return mysqli_result
     */
    public function query($query) {
        $statement = self::$_db->prepare($query);
        if($statement === false) {
            throw self::createQueryException('Error preparing query for execution', self::$_db, $query);
        }

        if(!$statement->execute()) {
            throw self::createQueryException('Error executing query', self::$_db, $query);
        }

        return $statement->get_result();
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
     * @param $rawCondition string the raw condition passed in by the client
     * @returns string the fully-built condition ready for SQL
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
     * Selects data from the specified table, always returning an array of the retrieve results.
     *
     * By default this method will return an array of objects of type stdClass; each object will
     * have a property based on the column names returned as part of the column list.
     *
     * All results are retrieved in one call, therefore it is the responsibility of the caller to
     * perform any necessary limiting (either by limiting or by filtering with a where clause).
     *
     * Simple call - no where, order or limit:
     * SELECT firstname, lastname FROM people;
     * ->  select(array('firstname', 'lastname'), 'people');
     *
     * Note: SELECT * is not permitted; it will thrown an exception. You *must* specify the columns
     * you wish to retrieve.
     *
     * Filtering call - where with simple implicit 'equals' operators
     *
     * If you are performing a simple where with just equals operators (i.e. ID = 2) then you can
     * simply pass in the column name as the key for each part of the where clause:
     *
     * SELECT firstname, lastname FROM people WHERE firstname = 'fred';
     * ->  select(array('firstname', 'lastname'), 'people', array('firstname' => 'fred'));
     *
     * Filtering call - where with operators
     *
     * To perform a more complex query with operators (greater than >, not equal to !=) you must pass
     * them in as part of the key of the where clause.
     *
     * SELECT firstname, age FROM people WHERE age > 11;
     * -> select(array('firstname', 'lastname'), 'people', array('age >' => 11));
     *
     * Ordered call - SORT BY firstname
     *
     * SELECT firstname, lastname FROM people SORT BY firstname ASC
     * -> select(array('firstname', 'lastname'), 'people', null, array('firstname' => 'ASC'));
     *
     * Limited call - LIMIT 1, 10
     *
     * SELECT firstname FROM people LIMIT 1;
     * -> select(array('firstname'), 'people', null, null, 1);
     *
     * SELECT firstname FROM people LIMIT 10, 20;
     * -> select(array('firstname'), 'people', null, null, array(10, 20));
     *
     * Custom objects - have the select() function return custom objects instead of stdClass
     *
     * By default the select() method returns an array of stdClass instances. Pass in a class name
     * and the method will instantiate that class and assign the values to that instead.
     *
     * $people = select(array('firstname', 'lastname'), 'people', null, null, null, 'person');
     *
     *
     * @param $columns the columns to retrieve data from
     * @param $table the table to retrieve the data from
     * @param array $where optional; where clause with which to filter data, key: column name, value: value
     * @param array $order optional; associative array of fields to order by and in which direction
     * @param array $limit optional; pass in a integer to limit TO, pass in an 2-element array to limit FROM and TO
     * @param string $className; optional, pass in a class name for the function to instantiate
     * @throws Exception in the event of an issue TODO: issue-specific exceptions
     * @return array of objects found; each object will be of stdClass unless $className is passed
     */
    public function select($columns, $table, $where = self::NO_WHERE, $order = self::NO_ORDER, $limit = self::NO_LIMIT,
                           $className = self::STANDARD_CLASS)
    {
        //$columnFragment = implode(', ', $columns);
        $columnFragment = $this->sqlImplode($columns);
        $containsSelectAsterisk = stripos($columnFragment, '*') !== false;
        if($containsSelectAsterisk) {
            throw new Exception('Select * is not allowed');
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

            call_user_func_array(array($statement, 'bind_param'), $functionParams);
        }

        return $this->executeSelectStatement($statement, $query, $className);
    }

    public static function escapeFieldName($fieldName) {
        return self::STRING_ESCAPE . $fieldName . self::STRING_ESCAPE;
    }

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
     * @param $order the order to build from
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
     * Selects one single row based on the clause supplied.
     *
     * Essentially a simplification of calling SELECT [columns] FROM [table] WHERE [where] LIMIT 1
     *
     * Best used with ID-based to retrieve one column. This method MUST be called with a where clause.
     *
     * See select() for more information about each parameter
     *
     * @param array $columns the columns to retrieve
     * @param $table the table to retrieve from
     * @param array $where the where clause to filter the results by
     * @param string $className optional; the name of the class to instantiate, if not passed, stdClass is used
     *
     * @throws InvalidArgumentException if any of the supplied arguments are incorrect
     *
     * @return object if found, the object from the database; otherwise, null
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
     * @param $limit the incoming limit arguments
     * @return string the string ready to be appended to a LIMIT clause
     * @throws InvalidArgumentException in the event that the incoming limit arguments are not correct
     */
    private function createLimitSql($limit)
    {
        $limitValue = '';
        if (is_numeric($limit)) {
            $limitValue = $limit;
            return $limitValue;
        }
        else if (is_array($limit) && sizeof($limit) == 2) {
            $limitValue = $limit[0] . ',' . $limit[1];
            return $limitValue;
        }
        else {
            throw new InvalidArgumentException('Limit must either be a single numeric, or a 2-element array of integers');
        }
    }

    /**
     * Generates a 'WHERE x,y,z' clause
     *
     * @param $whereClause there where parameters
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
     * @param $value the value to get the type of; must be an object
     * @param $typeHint the hint TODO: really needed?
     * @return string the single-character type value for the object
     * @throws Exception if the type of the value is not supported
     */
    protected function getObjectType($value, $typeHint) {
        if($value instanceof DateTime) {
            return 's';
        }
        else {
            throw new Exception('Unsupported type of ' . get_class($value));
        }
    }

    /**
     * Obtains the mysqli type character for the supplied value, using any hinting provided
     *
     * @param $value the value to get the type of
     * @param string $typeHint name of the type that the value is
     * @return string the single character representation of the type
     * @throws Exception in the event the type of the value is unsupported
     */
    protected function getType($value, $typeHint = null) {
        switch(gettype($value)) {
            case 'integer':
                return 'i';
            case 'double':
                return 'd';
            case 'string':
                return 's';
            case 'object':
                return $this->getObjectType($value, $typeHint);
            default:
                throw new Exception('Unsupported type: ' . gettype($value));
        }
    }

    /**
     * executes the statement of the supplied query, retriving rows with the class name
     *
     * @param mysqli_stmt $statement
     * @param $query
     * @param $className
     * @return array
     * @throws Exception
     */
    private function executeSelectStatement(mysqli_stmt $statement, $query, $className)
    {
        if (!$statement->execute()) {
            throw self::createQueryException('Error executing query', $statement, $query);
        }

        $result = $statement->get_result();
        if ($result === false) {
            throw self::createQueryException('Error getting results', $statement, $query);
        }
        $results = $this->_buildResults($result, $className);
        $statement->close();

        return $results;
    }

    private function isDateField($fieldTypeCode) {
        $mysqlFieldTypeMap = array(
            1=>'tinyint',
            2=>'smallint',
            3=>'int',
            4=>'float',
            5=>'double',
            7=>'timestamp',
            8=>'bigint',
            9=>'mediumint',
            10=>'date',
            11=>'time',
            12=>'datetime',
            13=>'year',
            16=>'bit',
            //252 is currently mapped to all text and blob types (MySQL 5.0.51a)
            253=>'varchar',
            254=>'char',
            246=>'decimal'
        );
        return $mysqlFieldTypeMap[$fieldTypeCode] == 'datetime';
    }

    private function _buildResults(mysqli_result $result, $className)
    {
        $dateFieldNames = array();
        for($i = 0; $i < $result->field_count; $i++) {
            $fieldMetadata = $result->fetch_field_direct($i);
            if($fieldMetadata !== false) {
                if($this->isDateField($fieldMetadata->type)) {
                    $dateFieldNames[] = $fieldMetadata->name;
                }
            }
        }

        $results = array();
        while (($row = $result->fetch_object($className)) != null) {
//            $row = (array)$row;
            foreach($dateFieldNames as $fieldName) {
                try {
                    $row->$fieldName = new DateTime($row->$fieldName);
                } catch(Exception $ex) {
                    throw new Exception('Error parsing DateTime - ' . $ex->getMessage() . ' - and the value was: [' . $row->$fieldName . '] on column [' . $fieldName . ']');
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
     * @param $message the message to be reported
     * @param $dbObject the mysqli object that experienced the error
     * @param $query the query that was/should be executed
     * @return Exception the exception, ready for throwing
     */
    protected static function createQueryException($message, $dbObject, $query) {
        return new Exception($message . ', error: '
            . $dbObject->error
            . ' for query: '
            . $query);
    }
    private function _createValues($numberOfValues) {
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
        $keys = array_keys($data);
        $dbColumnName = '(';
        foreach($keys as $key) {
            $dbColumnName .= '`'.$key.'`, ';
        }
        $dbColumnName = substr($dbColumnName, 0, -2);
        $dbColumnName .= ')';

        $table = 'INSERT INTO `'.$table.'` ' .$dbColumnName;

        $numberOfValues = count($data);
        $values = $this->_createValues($numberOfValues);

        $dataType = $this->_checkTypeOfValues($data);
        $type = '';
        //getting first letter from each of value type
        foreach($dataType as $word) {
            $letter = substr($word, 0, 1);
            $type .= $letter;
        }

        $dataValues = (count($data) >= 1) ? ' VALUES ('.$values.')' : '';
        $sql= $table.$dataValues;

        //$stmt initialization
        $stmt = self::$_db->stmt_init();

        //prepare statement
        if($sqlPrepare = $stmt->prepare($sql)) {
            $whereClause = $this->createParameters($data);

            $values = array();
            $v = $whereClause->getValues();
            foreach($v as &$value) {
                array_push($values, &$value);
            }
            $typeList = $whereClause->getTypeList();
            $functionParams = array_merge(array(&$typeList), $values);
            call_user_func_array(array($stmt, 'bind_param'), $functionParams);

            $result = $stmt->execute();
            if (true === $result) {
                return ($stmt->insert_id);
            }
            else {
                throw new Exception('Error: ' .$stmt->error);
            }
            $stmt->close();
        }
        else
            throw new  Exception("Error: " .$stmt->error);
    }

    public function delete($table, $where) {
        throw new  Exception("database successfully deleted");
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

        if($stmt = self::$_db->prepare($sql)) {
            $whereClause = $this->createParameters($where);
            $values = array();
            $dataType = $this->_checkTypeOfValues($data);
            $type = '';
            //getting first letter from each of value type
            foreach($dataType as $word) {
                $letter = substr($word, 0, 1);
                $type .= $letter;
            }

            //bind param
            $q = array();
            foreach($data as $key=>$value) {
                $q[] = &$value;
            }

            //call_user_func_array(array($stmt, 'bind_param'), array_merge(array(&$type), $q));

            $stmt->execute();
        }
        else {
            throw new Exception('Error preparing: ' . self::$_db->error);
        }
    }

    public function getLastInsertId() {
        return self::$_db->insert_id;
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
     * @param $condition the condition; must be in the format [fieldname] [operator] ?
     * @param $type the mysqli type of the value
     * @param $value the value
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
