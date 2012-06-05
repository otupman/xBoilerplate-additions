<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * Date: 01/06/2012
 * Time: 13:07
 */
class CW_AbstractDbTest extends PHPUnit_Framework_TestCase
{

    private $config;

    const DB_HOST = 'localhost';
    const DB_USER = 'root';
    const DB_PASS = '';
    const DB_SCHEMA = 'xBoilerplate_additions';

    protected $fred;
    protected $barney;
    protected $alice;

    /**
     * @var mysqli
     */
    protected $_db;

    public function setup() {

        // error_reporting(E_ALL);
        $this->config = array();
        //TODO: these settings should come from the containing phpunit
        $this->config['db'] = array(
            'host' => self::DB_HOST
        , 'username' => self::DB_USER
        , 'password' => self::DB_PASS
        , 'schema' => self::DB_SCHEMA
        ,  CW_SQL::CONFIG_DEBUG => CW_SQL::DEBUG_STORELAST
        );
        PvtTestXBoilerplate::overrideStandardXBoilerplate();
        PvtTestXBoilerplate::$config = (object)$this->config;

        $this->initialiseDatabase();

    }

    /**
     * Simple function to insert data into the database in preparation
     * @param mysqli $db
     * @param $firstname
     * @param $lastname
     * @param $age
     * @param $createdDate
     * @param $balance
     * @throws Exception
     */
    protected function rawRowInsert(mysqli $db, $firstname, $lastname, $age, DateTime $createdDate, $balance, $realBalance) {
        $formattedDate = $createdDate->format('Y-m-d H:i:s');
        if(!$statement = $db->stmt_init()) {
            throw new Exception('Error creating prepared statement: ' . $db->error);
        }
        if(!$statement->prepare('INSERT INTO people (firstname, lastname, age, createdDate, balance, realBalance) VALUES (?, ?, ?, ?, ?, ?)')) {
            throw new Exception('Error preparing insert query: ' . $statement->error);
        }
        if(!$statement->bind_param('ssisdi', $firstname, $lastname, $age, $formattedDate, $balance, $realBalance)) {
            throw new Exception('Error binding parameters: ' . $statement->error);
        }
        if(!$statement->execute()) {
            throw new Exception('Error executing parameters');
        }
    }

    /**
     * Initialises the database ready for the tests.
     *
     * DROPs the database if it exists, (re-)creates it and then inserts 3 rows of test data
     */
    protected function initialiseDatabase() {
        $fred = array('firstname' => 'fred', 'lastname' => 'flintstone', 'age' => 11, 'createdDate' => new DateTime('2001-01-01 11:11:11'), 'balance' => 1.11, 'realBalance' => 0.11);
        $barney = array('firstname' => 'Barney', 'lastname' => 'Rubble', 'age' => 12, 'createdDate' => new DateTime('2012-12-12 12:12:12'), 'balance' => 12.12, 'realBalance' => 0.12);
        $alice = array('firstname' => 'Alice', 'lastname' => 'Jones', 'age' => 21, 'createdDate' => new DateTime('1990-04-21 21:21:21'), 'balance' => 0.21, 'realBalance' => 2.1);

        $db = new mysqli(self::DB_HOST, self::DB_USER, self::DB_PASS, self::DB_SCHEMA);

        $db->query('DROP TABLE IF EXISTS people');

        $createQuery = 'CREATE TABLE people (
           id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
           firstname VARCHAR(255) NOT NULL,
           lastname VARCHAR(255) NOT NULL,
           age INT,
           createdDate DATETIME,
           balance FLOAT,
           realBalance DECIMAL,
           `from` DATETIME
          );';
        $db->query($createQuery);

        $this->rawRowInsert($db, $fred['firstname'], $fred['lastname'], $fred['age'], $fred['createdDate'], $fred['balance'], $fred['realBalance']);
        $this->rawRowInsert($db, $barney['firstname'], $barney['lastname'], $barney['age'], $barney['createdDate'], $barney['balance'], $barney['realBalance']);
        $this->rawRowInsert($db, $alice['firstname'], $alice['lastname'], $alice['age'], $alice['createdDate'], $alice['balance'], $alice['realBalance']);

        $this->fred = $fred;
        $this->barney = $barney;
        $this->alice = $alice;

        $this->_db = $db;
    }

}


/**
 * Private class that allows the test to override the configuration storage and set it's own without having to load
 * it from a configuration file.
 */
class PvtTestXBoilerplate extends xBoilerplate {
    public static $config;

    /**
     * Triggers the overriding of the standard xBoilerplate singleton with this version.
     * @static
     *
     */
    public static function overrideStandardXBoilerplate() {
        xBoilerplate::$_instance = new PvtTestXBoilerplate();
    }

    public function getConfig() {
        return self::$config;
    }
}