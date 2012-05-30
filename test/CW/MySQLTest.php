<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * @version 0.1
 * Date: 21/05/2012
 * Time: 09:23
 */
require_once('../bootstrap.php');
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
       // error_reporting(E_ALL);
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
        $people = CW_MySQL::getInstance()->select(array('id','firstname', 'lastname'), 'people');

        $this->assertEquals(3, sizeof($people), 'Incorrect number of rows returned');

        $fred = $people[0];
        $this->assertInstanceOf('stdClass', $fred, 'Rows should be objects');
        $this->assertObjectHasAttribute('firstname', $fred, 'First row should have property firstname');
        $this->assertEquals($this->fred['firstname'], $fred->firstname, 'First name is incorrect');
        $this->assertObjectHasAttribute('lastname', $fred, 'All-column select should contain column lastname');

    }
    /**
     * Tests for field and value selects that contain DB keywords such as
     * DATABASE
     */
    public function testKeywordSelect() {
        $this->markTestIncomplete('This test is not yet implemented');
    }

    public function testWithSelectLimit() {
        $this->markTestIncomplete('This test is not yet implemented');
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
        $fred = $people[0];
        $this->assertEquals($this->fred['firstname'], $fred->firstname, 'Firstname value incorrect');

        // SELECT id, lastname FROM people
        $people = CW_MySQL::getInstance()->select(array('id', 'lastname'), 'people');

        $this->assertObjectNotHasAttribute('firstname', $people[0], 'Firstname should not be present, just: id, lastname');
    }

    public function testSelectRow() {
        $this->markTestIncomplete('This test is not yet implemented');
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
        $this->assertEquals('Barney', $barneyOnly[0]->firstname, 'Barney should be the only result returned');

        // SELECT firstname FROM people WHERE age < 20 AND createdDate > 2000-01-01
        $youngerUsers = CW_MySQL::getInstance()->select(array('firstname'), 'people',
            array('age < ' => 20, 'createdDate >' => new DateTime('2001-01-01')) // Implicit AND
        );

        //TODO: Add a selectOr() method?

        $this->assertEquals(2, sizeof($youngerUsers), 'There should be 2 users who fit the criteria');

    }

    /**
     * Tests the where clause processing to ensure that bad operators are picked up
     */
    public function testBadWhere() {
        try {
            CW_MySQL::getInstance()->select(array('firstname'), 'people', array('firstname X' => 'Bob'));
            $this->fail('Expected exception due to an invalid operator "X"');
        } catch(Exception $ex) { /* Expected */ }

        try {
            CW_MySQL::getInstance()->select(array('firstname'), 'people', array('firstname > 5' => 'Bob'));
            $this->fail('Expected exception due to bad WHERE clause: "firstname > 5"');
        } catch(Exception $ex) { /* Expected */ }
    }

    /**
     * Tests retrieving data when the data type of the data does not match the DB data type.
     *
     * Example: DateTime() to on a DATETIME column as opposed to a TIMESTAMP. TIMESTAMP needs a different
     * format.
     */
    public function testSelectWithTypes() {
        $this->markTestIncomplete('This test is not yet implemented');
    }

    public function testSelectWithSort() {
        $this->markTestIncomplete('This test is not yet implemented');
        // SELECT firstname FROM people ORDER BY age DESC
        $aliceFirst = CW_MySQL::getInstance()->select(array('firstname'), 'people', null, array('age' => 'DESC'));

        $alice = $aliceFirst[0];
        $this->assertEquals($this->alice['firstname'], $alice->firstname);
        $barney = $aliceFirst [1];
        $this->assertEquals('Barney', $barney['firstname']);
    }

    public function testSelectWithDates() {
        $this->markTestIncomplete('Test not yet complete');
    }

    public function testInsert() {
        //$this->markTestIncomplete('This test is not yet implemented');
        $firstname = 'testInsert_' . time();

        // INSERT INTO people (firstname, lastname, age, createdDate, )
        CW_MySQL::getInstance()->insert('people',
            array('firstname' => $firstname, 'lastname' => 'Test user', 'age' => 99, 'createdDate' => time()));

        $testUserOnly = CW_MySQL::getInstance()->select(array('firstname', 'age'), 'people', array('firstname' => $firstname));
        $this->assertEquals(1, sizeof($testUserOnly), '1 result should be returned: just the recently-inserted test user');

        $testUser = $testUserOnly[0];

        $this->assertEquals($firstname, $testUser->firstname, 'First name does not match');
        $this->assertEquals(99, $testUser->age, 'Age does not match');
    }



    public function testDelete() {
        $this->markTestIncomplete('This test is not yet implemented');
    }

    public function testUpdateNormal() {
        // UPDATE people SET firstname = 'Fred' WHERE firstname = 'fred'
        $fredsNewName = 'Fred';
        $numRowsChanged = CW_MySQL::getInstance()->update(
            'people', array('firstname' => $fredsNewName), array('age' => 11)//, array('firstname' => 'fred')
        );

        $this->assertEquals(1, $numRowsChanged, '1 row should have been modified ("fred")');

        $updatedRows = CW_MySQL::getInstance()->select(
            array('firstname'), 'people', array('age'=>11)
        );
        $this->assertEquals(1, sizeof($updatedRows));

        $fred = $updatedRows[0];

        $this->assertEquals($fredsNewName, $fred->firstname);

    }

    public function testUpdate_badParameters() {
        $this->markTestIncomplete('This test is not yet implemented');
        try {
            CW_MySQL::getInstance()->update('people', array('firstname' => 'Fred'), null);
            $this->fail('Expected exception when using null where clause');
        } catch(Exception $ex) { /* Expected */ }

        try {
            CW_MySQL::getInstance()->update('people', array('firstname' => 'Fred'), array());
            $this->fail('Expected exception when using an empty array for where');
        } catch(Exception $ex) { /* Expected */ }
    }

    public function testQuery() {
        $this->markTestIncomplete('This test is not yet implemented');
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
//
//    public function testVegan() {
//        $a = array('one', 22, 'ddd', 33);
//        $b = array();
//
//
//        foreach($a as $i) {
//            $b[] = &$i;
//        }
//        print_r($b);
//    }
}