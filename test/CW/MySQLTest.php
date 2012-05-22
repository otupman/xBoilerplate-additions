<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * @version 0.1
 * Date: 21/05/2012
 * Time: 09:23
 */

class CW_MySQLTest extends PHPUnit_Framework_TestCase
{
    private $config;

    const DB_HOST = 'localhost';
    const DB_USER = 'root';
    const DB_PASS = '';
    const DB_SCHEMA = 'xBoilerplate_additions';

    private $fred;
    private $barney;
    private $alice;

    public function setup() {
        $this->config = array();
        //TODO: these settings should come from the containing phpunit
        $this->config['db'] = array(
            'host' => self::DB_HOST
            , 'username' => self::DB_USER
            , 'password' => self::DB_PASS
            , 'db' => self::DB_SCHEMA
        );

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
    private function rawRowInsert(mysqli $db, $firstname, $lastname, $age, $createdDate, $balance) {
        if(!$statement = $db->stmt_init()) {
            throw new Exception('Error creating prepared statement: ' . $db->error);
        }
        if(!$statement->prepare('INSERT INTO people (firstname, lastname, age, createdDate, balance) VALUES (?, ?, ?, ?, ?)')) {
            throw new Exception('Error preparing insert query: ' . $statement->error);
        }
        if(!$statement->bind_param('ssisd', $firstname, $lastname, $age, $createdDate, $balance)) {
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
    private function initialiseDatabase() {
        $fred = array('firstname' => 'fred', 'lastname' => 'flintstone', 'age' => 11, 'createdDate' => '2001-01-01 11:11:11', 'balance' => 1.11);
        $barney = array('firstname' => 'Barney', 'lastname' => 'Rubble', 'age' => 12, 'createdDate' => '2012-12-12 12:12:12', 'balance' => 12.12);
        $alice = array('firstname' => 'Alice', 'lastname' => 'Jones', 'age' => 21, 'createdDate' => '1990-04-21 21:21:21', 'balance' => 0.21);

        $db = new mysqli(self::DB_HOST, self::DB_USER, self::DB_PASS, self::DB_SCHEMA);

        $db->query('DROP TABLE IF EXISTS people');

        $createQuery = 'CREATE TABLE people (
           id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
           firstname VARCHAR(255) NOT NULL,
           lastname VARCHAR(255) NOT NULL,
           age INT,
           createdDate DATETIME NOT NULL,
           balance FLOAT NOT NULL
          );';
        $db->query($createQuery);

        $this->rawRowInsert($db, $fred['firstname'], $fred['lastname'], $fred['age'], $fred['createdDate'], $fred['balance']);
        $this->rawRowInsert($db, $barney['firstname'], $barney['lastname'], $barney['age'], $barney['createdDate'], $fred['balance']);
        $this->rawRowInsert($db, $alice['firstname'], $alice['lastname'], $alice['age'], $alice['createdDate'], $fred['balance']);

        $this->fred = $fred;
        $this->barney = $barney;
        $this->alice = $alice;
    }

    /**
     * Tests the most simple select statement: SELECT * FROM people
     */
    public function testSimplestSelect() {
        // Simple SELECT firstname, lastname FROM people
        $people = CW_MySQL::getInstance()->select(array('firstname', 'lastname'), 'people');

        $this->assertEquals(2, sizeof($people), 'Incorrect number of rows returned');

        $barney = $people[0];
        $this->assertEquals($this->barney['firstname'], $barney->firstname, 'First name is incorrect');
        $this->assertObjectHasAttribute('lastname', $barney, 'All-column select should contain column lastname');

    }

    public function testSelectLimit() {
        // SELECT firstname FROM people LIMIT 1
        $onlyOneRow = CW_MySQL::getInstance()->select(array('firstname'), 'people', null, null, 1);

        $this->assertEquals(1, sizeof($onlyOneRow));
        //TODO: Add test here for the value of the first row (should be Fred)

        // SELECT firstname FROM people LIMIT 1,3
        $secondRowOnly = CW_MySQL::getInstance()->select(array('firstname'), 'people', null, null, array(1,3));
        //TODO: Add test here for the values of the 2nd & 3rd row (Barney & Alice)
    }

    /**
     * Tests for error handling; any problem with the call should throw an exception.
     */
    public function testBadSelect() {
        //TODO: use phpunit's expected exception testing
        try {
            CW_MySQL::getInstance()->select(array('someField'), 'non_existent_table');
            $this->fail('Exception expected: attempting to perform a SELECT from a non-existent table');
        } catch(Exception $ex) { /* Exception expected */ }

        try {
            CW_MySQL::getInstance()->select(array('nonexistentfield'), 'people');
            $this->fail('Exception expected: attempting to perform a SELECT on a non-existent field');
        } catch(Exception $ex) { /* Exception expected */ }

        try {
            CW_MySQL::getInstance()->select(array('*'), 'people');
            $this->fail('Exception expected: attempting to use SELECT * syntax');
        } catch(Exception $ex) { /* Exception expected */ }

        try {
            CW_MySQL::getInstance()->select('somefield', 'people');
            $this->fail('Exception expected: passed a string for the field selection (array expected)');
        } catch(Exception $ex) { /* Exception expected */ }

    }


    /*
     *
     */
    public function testSelectWithColumns() {

        // SELECT id, firstname FROM people
        $people = CW_MySQL::getInstance()->select(array('id', 'firstname'), 'people');

        $this->assertObjectNotHasAttribute('lastname', $people[0], 'Lastname should not be present but is');
        $this->assertObjectHasAttribute('firstname', $people[0], 'First name should be present but is not');
        $fred = $people[1];
        $this->assertEquals($this->fred['lastname'], $fred['lastname'], 'Lastname value incorrect');

        // SELECT id, lastname FROM people
        $people = CW_MySQL::getInstance()->select(array('id', 'lastname'), 'people');

        $this->assertObjectHasAttribute('firstname', $people[0], 'Firstname should not be present, just: id, lastname');
    }

    public function testSelectRow() {
        $this->fail('Not yet implemented');
        //TODO Test the method selectRow() that will return only one row (object)

    }

    public function testSelectWithWhere() {
        // SELECT firstname FROM people WHERE firstname = 'Bob' /* 0 results - no user with name Bob */
        $noPeople = CW_MySQL::getInstance()->select(array('firstname'), 'people', array('firstname' => 'Bob'));

        $this->assertEquals(0, sizeof($noPeople));

        // SELECT firstname FROM people WHERE firstname = 'fred' /* 1 result: Fred */
        $fredOnly = CW_MySQL::getInstance()->select(array('firstname'), 'people', array('firstname' => $this->fred['firstname']));

        $this->assertEquals(1, sizeof($fredOnly));

        // Numeric equality test SELECT * FROM people WHERE age > 11 /* 2 results: fred & Barney */
        $barneyOnly = CW_MySQL::getInstance()->select(array('firstname'), 'people', array('age > ' => 11));

        $this->assertEquals(2, sizeof($barneyOnly));
        $this->assertEquals('Barney', $barneyOnly[0]['firstname'], 'Barney should be the only result returned');

        // SELECT firstname FROM people WHERE age < 20 AND createdDate > 2000-01-01
        $youngerUsers = CW_MySQL::getInstance()->select(array('firstname'), 'people',
            array('age < ' => 20, 'createdDate >' => date_create('2001-01-01')) // Implicit AND
        );

        //TODO: Add a selectOr() method?

        $this->assertEquals(2, sizeof($youngerUsers), 'There should be 2 users who fit the criteria');

    }

    public function testSelectWithSort() {
        // SELECT firstname FROM people ORDER BY age DESC
        $aliceFirst = CW_MySQL::getInstance()->select(array('firstname'), 'people', null, array('age' => 'DESC'));

        $alice = $aliceFirst[0];
        $this->assertEquals($this->alice['firstname'], $alice['firstname']);
        $barney = $aliceFirst [1];
        $this->assertEquals('Barney', $barney['firstname']);
    }

    public function testInsert() {
        $firstname = 'testInsert_' . time();

        // INSERT INTO people (firstname, lastname, age, createdDate, )
        CW_MySQL::getInstance()->insert('people',
            array('firstname' => $firstname, 'lastname' => 'Test user', 'age' => 99, 'createdDate' => time()));

        $testUserOnly = CW_MySQL::getInstance()->select(array('firstname'), 'people', array('firstname' => $firstname));
        $this->assertEquals(1, sizeof($testUserOnly), '1 result should be returned: just the recently-inserted test user');

        $testUser = $testUserOnly[0];

        $this->assertEquals($firstname, $testUserOnly['firstname'], 'First name does not match');
        $this->assertEquals(99, $testUserOnly['age'], 'Age does not match');
    }


    public function testDelete() {
        $this->fail('Test not implemented yet');
    }

    public function testUpdate() {
        $this->fail('Test not implemented yet');
    }

    public function testQuery() {
        $this->fail('Test not implemented yet');
    }

    public function testLastInsertId() {
        $this->fail('Test not implemented yet');

        CW_MySQL::getInstance()->insert('people',
            array('firstname' => 'testLastInsertId', 'lastname' => 'User', 'age' => 50, 'createdDate' => time()));
        $lastInsertId = CW_MySQL::getInstance()->getLastInsertId();

        $lastInsertOnly = CW_MySQL::getInstance()->select('people', array('firstname' => 'testLastInsertId'));
        $singleRow = $lastInsertOnly[0];

        $this->assertEquals($singleRow['id'], $lastInsertId, 'Insert ID from class does not match actual from database.');
    }
}