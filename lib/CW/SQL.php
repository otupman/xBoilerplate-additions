<?php
/**
 * CW_SQL - Simplified database access class to provide quick, easy and secure queries.
 *
 * The SQL class is intended to provide clients with a simplified set of SQL queries that should cover 80% of use
 * cases. It also provides more power via the query() method, where a custom query can be supplied - however, it is
 * expected that this method is only used as a last resort. For Centralwayers, using it will mean you're asked to
 * justify *why* you are using it!
 *
 * This class in particular - SQL.php - defines the API for database access; another class will implement this on
 * CW_SQL's behalf. The default implementation is database-independent.
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
 *  https://github.com/centralway/xBoilerplate-additions/wiki/SQL
 *
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * @version 0.4
 *
 */
abstract class CW_SQL
{
    /** Optionally used to signal an empty where clause */
    const NO_WHERE = null;
    /** Optionally used to signal an empty order instruction  */
    const NO_ORDER = null;
    /** Optionally used to signal that there is no limit set on the query */
    const NO_LIMIT = null;
    /** The default class instantiated to return results with */
    const STANDARD_CLASS = 'stdClass';

    /** Sort operator: ascending */
    const OP_ASC = 'ASC';
    /** Sort operator: descending */
    const OP_DESC = 'DESC';

    /** Configuration key: debug, to configure debugging options */
    const CONFIG_DEBUG = 'debug';

    /** Debug: do no debugging actions (default) */
    const DEBUG_NONE = 'none';

    /** Debug: log queries (using user_error) */
    const DEBUG_LOG = 'log';
    /** Debug: store the last query executed (getInstance()->lastQuery) */
    const DEBUG_STORELAST = 'storelast';

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
     * @return PDOStatement
     */
    abstract public function query($query);
    abstract public function delete($table, $where);
    abstract public function update($table, array $data, array $where);
    abstract public function getLastInsertId();

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
     * @param array|associative $columns the columns to retrieve data from
     * @param string $table the table to retrieve the data from
     * @param array $where optional; where clause with which to filter data, key: column name, value: value
     * @param array $order optional; associative array of fields to order by and in which direction
     * @param array $limit optional; pass in a integer to limit TO, pass in an 2-element array to limit FROM and TO
     * @param string $className; optional, pass in a class name for the function to instantiate
     * @throws Exception in the event of an issue TODO: issue-specific exceptions
     * @return array of objects found; each object will be of stdClass unless $className is passed
     */
    abstract public function select(array $columns, $table, $where = self::NO_WHERE, $order = self::NO_ORDER, $limit = self::NO_LIMIT,
                                    $className = self::STANDARD_CLASS);
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
     * @param string $table the table to retrieve from
     * @param array $where the where clause to filter the results by
     * @param string $className optional; the name of the class to instantiate, if not passed, stdClass is used
     *
     * @throws InvalidArgumentException if any of the supplied arguments are incorrect
     *
     * @return object if found, the object from the database; otherwise, null
     */
    abstract public function selectRow(array $columns, $table, array $where, $className = self::STANDARD_CLASS);


    /**
     * the static instance of the class
     * @var CW_MySQL
     */
    protected static $_object = null;
    /**
     *
     * The static mysqli instance that the class uses
     *
     * @var PDO
     */
    protected static $_db = null;


    /**
     * Obtains the static instance of the CW_MySQL class.
     *
     * @return CW_MySQL
     */
    public static function getInstance() {

        if (!self::$_object) {
            self::$_object = new CW_MySQL();
        }
        return self::$_object;
    }
}
