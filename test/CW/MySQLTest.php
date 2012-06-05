<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * @version 0.1
 * Date: 21/05/2012
 * Time: 09:23
 */
require_once('../bootstrap.php');
class CW_SQLTest extends PHPUnit_Framework_TestCase
{
    private $config;

    const DB_HOST = 'localhost';
    const DB_USER = 'root';
    const DB_PASS = '';
    const DB_SCHEMA = 'xBoilerplate_additions';

    private $fred;
    private $barney;
    private $alice;

    /**
     * @var mysqli
     */
    private $_db;

    public function setup() {

       // error_reporting(E_ALL);
        $this->config = array();
        //TODO: these settings should come from the containing phpunit
        $this->config['db'] = array(
            'host' => self::DB_HOST
            , 'username' => self::DB_USER
            , 'password' => self::DB_PASS
            , 'schema' => self::DB_SCHEMA
        );
        CW_TestXBoilerplate::overrideStandardXBoilerplate();
        CW_TestXBoilerplate::$config = (object)$this->config;

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
    private function rawRowInsert(mysqli $db, $firstname, $lastname, $age, DateTime $createdDate, $balance, $realBalance) {
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
    private function initialiseDatabase() {
        $fred = array('firstname' => 'fred', 'lastname' => 'flintstone', 'age' => 11, 'createdDate' => new DateTime('2001-01-01 11:11:11'), 'balance' => 1.11, 'realBalance' => 0.11);
        $barney = array('firstname' => 'Barney', 'lastname' => 'Rubble', 'age' => 12, 'createdDate' => new DateTime('2012-12-12 12:12:12'), 'balance' => 12.12, 'realBalance' => 0.12);
        $alice = array('firstname' => 'Alice', 'lastname' => 'Jones', 'age' => 21, 'createdDate' => new DateTime('1990-04-21 21:21:21'), 'balance' => 0.21, 'realBalance' => 2.1);

        $db = new mysqli(self::DB_HOST, self::DB_USER, self::DB_PASS, self::DB_SCHEMA);
//        $db = new PDO('mysql:dbname=' . self::DB_SCHEMA . ';host=' . self::DB_HOST, self::DB_USER, self::DB_PASS);

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

    /**
     * Tests the most simple select statement: SELECT * FROM people
     */
    public function testSimplestSelect() {
        // Simple SELECT firstname, lastname FROM people
        $people = CW_SQL::getInstance()->select(array('id','firstname', 'lastname'), 'people');

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

    public function testSelect_withLimit() {
//        $this->markTestIncomplete('This test is not yet implemented');
        // SELECT firstname FROM people LIMIT 1
        $onlyOneRow = CW_SQL::getInstance()->select(array('firstname'), 'people', null, null, 1);

        $this->assertEquals(1, sizeof($onlyOneRow));

        $this->assertEquals($this->fred['firstname'], $onlyOneRow[0]->firstname, 'Only row returned should be fred');

        // SELECT firstname FROM people LIMIT 1,3
        $lastTwoRows = CW_SQL::getInstance()->select(array('firstname'), 'people', null, null, array(1,3));

        $this->assertEquals(2, sizeof($lastTwoRows));
        $this->assertEquals($this->barney['firstname'], $lastTwoRows[0]->firstname, 'First row (2nd in DB) should be Barney');
    }

    /**
     * Tests for error handling; any problem with the call should throw an exception.
     */
    public function testBadSelect() {
        //TODO: use phpunit's expected exception testing
        try {
            CW_SQL::getInstance()->select(array('someField'), 'non_existent_table');
            $this->fail('Exception expected: attempting to perform a SELECT from a non-existent table');
        } catch(Exception $ex) { /* Exception expected */ }

        try {
            CW_SQL::getInstance()->select(array('nonexistentfield'), 'people');
            $this->fail('Exception expected: attempting to perform a SELECT on a non-existent field');
        } catch(Exception $ex) { /* Exception expected */ }

        try {
            CW_SQL::getInstance()->select(array('*'), 'people');
            $this->fail('Exception expected: attempting to use SELECT * syntax');
        } catch(Exception $ex) { /* Exception expected */ }

        try {
            CW_SQL::getInstance()->select('somefield', 'people');
            $this->fail('Exception expected: passed a string for the field selection (array expected)');
        } catch(Exception $ex) { /* Exception expected */ }

    }


    /*
     *
     */
    public function testSelect_withColumns() {

        // SELECT id, firstname FROM people
        $people = CW_SQL::getInstance()->select(array('id', 'firstname'), 'people');

        $this->assertObjectNotHasAttribute('lastname', $people[0], 'Lastname should not be present but is');
        $this->assertObjectHasAttribute('firstname', $people[0], 'First name should be present but is not');
        $fred = $people[0];
        $this->assertEquals($this->fred['firstname'], $fred->firstname, 'Firstname value incorrect');

        // SELECT id, lastname FROM people
        $people = CW_SQL::getInstance()->select(array('id', 'lastname'), 'people');

        $this->assertObjectNotHasAttribute('firstname', $people[0], 'Firstname should not be present, just: id, lastname');
    }

    public function testSelectRow_successfulCase() {
        $this->markTestIncomplete('This test is not yet implemented');
        //TODO Test the method selectRow() that will return only one row (object)

    }

    public function testSelect_withObject() {
        $people = CW_SQL::getInstance()->select(
            array('firstname', 'lastname', 'age'), 'people', CW_SQL::NO_WHERE, CW_SQL::NO_LIMIT, CW_SQL::NO_ORDER, 'Person'
        );

        $this->assertEquals(3, sizeof($people));

        $fred = $people[0];

        $this->assertInstanceOf('Person', $fred);
        $this->assertEquals($this->fred['firstname'], $fred->firstname);
        $this->assertEquals($this->fred['lastname'], $fred->getLastname()); // Lastname is a private property with a getter!
    }

    /**
     * Tests to ensure that reserved words do not cause any issues.
     *
     * Each call should succeed, therefore no assertions are required.
     */
    public function testSelect_reservedWords() {
        // Test using a reserved word in the column list
        try {
            CW_SQL::getInstance()->select(array('from'), 'people');
        } catch(Exception $ex) { $this->fail('Reserved word in field list caused exception: ' . $ex->getMessage());  }

        // Test using a reserved word in the WHERE clause
        try {
            CW_SQL::getInstance()->select(array('firstname', 'lastname'), 'people', array('age' => 11));
        } catch(Exception $ex) { $this->fail('Reserved word in where clause caused exception: ' . $ex->getMessage());  }

        // Test using a reserved word in the SORT BY clause
        try {
            CW_SQL::getInstance()->select(
                    array('firstname', 'lastname'),
                    'people',
                    array('firstname' => 'Fred'),
                    array('from' => 'ASC')
                );
        } catch(Exception $ex) { $this->fail('Reserved word in sort clause caused exception: ' . $ex->getMessage());  }
    }

    public function testSelect_withWhere() {
        // SELECT firstname FROM people WHERE firstname = 'Bob' /* 0 results - no user with name Bob */
        $noPeople = CW_SQL::getInstance()->select(array('firstname'), 'people', array('firstname' => 'Bob'));

        $this->assertEquals(0, sizeof($noPeople));

        // SELECT firstname FROM people WHERE firstname = 'fred' /* 1 result: Fred */
        $fredOnly = CW_SQL::getInstance()->select(array('firstname'), 'people', array('firstname' => $this->fred['firstname']));

        $this->assertEquals(1, sizeof($fredOnly));

        // Numeric equality test SELECT * FROM people WHERE age > 11 /* 2 results: fred & Barney */
        $barneyOnly = CW_SQL::getInstance()->select(array('firstname'), 'people', array('age > ' => 11));

        $this->assertEquals(2, sizeof($barneyOnly));
        $this->assertEquals('Barney', $barneyOnly[0]->firstname, 'Barney should be the only result returned');

        // SELECT firstname FROM people WHERE age < 20 AND createdDate > 2000-01-01
        $youngerUsers = CW_SQL::getInstance()->select(array('firstname'), 'people',
            array('age < ' => 20, 'createdDate >' => new DateTime('2001-01-01')) // Implicit AND
        );

        //TODO: Add a selectOr() method?

        $this->assertEquals(2, sizeof($youngerUsers), 'There should be 2 users who fit the criteria');

    }

    public function testSelect_everyColumn() {
        $allColumns = array('firstname', 'lastname', 'age', 'createdDate', 'balance', 'realBalance');
        $people = CW_SQL::getInstance()->select($allColumns, 'people', array('firstname' => 'Barney'));
        $this->assertEquals(1, sizeof($people), 'Firstname where of Barney failed');

        $barney = $people[0];
        $this->assertEquals($this->barney['firstname'], $barney->firstname, 'Firstname search for Barney failed');

        $people = CW_SQL::getInstance()->select($allColumns, 'people', array('lastname' => 'Jones'));
        $this->assertEquals(1, sizeof($people), 'Lastname where of Jones failed');

        $alice = $people[0];
        $this->assertEquals($this->alice['lastname'], $alice->lastname, 'Lastname search of Alice failed');

        $people = CW_SQL::getInstance()->select($allColumns, 'people', array('age' => 11));
        $this->assertEquals(1, sizeof($people), 'Age search of 11 (Fred) failed');

        $fred = $people[0];
        $this->assertEquals($this->fred['age'], $fred->age, 'Age search of 11 (for Fred) failed');

        $people = CW_SQL::getInstance()->select($allColumns, 'people', array('createdDate' => new Datetime('2012-12-12 12:12:12')));
        $this->assertEquals(1, sizeof($people), 'Date search (for Barney) failed');

        $barneyAgain = $people[0];
//        $this->assertInstanceOf('DateTime', $barneyAgain->createdDate, 'createddate is NOT an instance of DateTime');
        $this->assertEquals($this->barney['createdDate']->format('Y-m-d H:i:s'), $barneyAgain->createdDate, 'createddate search (for Barney) failed');

        $people = CW_SQL::getInstance()->select($allColumns, 'people', array('balance' => $this->fred['balance']));
        $this->assertEquals(1, sizeof($people), 'Balance search of 1.11 (for Fred) failed');

        $fredAgain = $people[0];
        $this->assertEquals($this->fred['balance'], $fredAgain->balance, 'Balance search of 1.11 failed (for Fred)');

        $people = CW_SQL::getInstance()->select($allColumns, 'people', array('realBalance' => $this->alice['realBalance']));
        $this->assertEquals(1, sizeof($people));

        $aliceAgain = $people[0];
        $this->assertEquals($this->alice['firstname'], $aliceAgain->firstname);
    }

    /**
     * Tests the where clause processing to ensure that bad operators are picked up
     */
    public function testSelect_withBadWhere() {
        try {
            CW_SQL::getInstance()->select(array('firstname'), 'people', array('firstname X' => 'Bob'));
            $this->fail('Expected exception due to an invalid operator "X"');
        } catch(Exception $ex) { /* Expected */ }

        try {
            CW_SQL::getInstance()->select(array('firstname'), 'people', array('firstname > 5' => 'Bob'));
            $this->fail('Expected exception due to bad WHERE clause: "firstname > 5"');
        } catch(Exception $ex) { /* Expected */ }
    }

    /**
     * Tests retrieving data when the data type of the data does not match the DB data type.
     *
     *t
     * Example: DateTime() to on a DATETIME column as opposed to a TIMESTAMP. TIMESTAMP needs a different
     * format.
     */
    public function testSelect_withTypes() {
        $this->markTestIncomplete('This test is not yet implemented');
    }

    public function testSelect_withSort() {
        // SELECT firstname FROM people ORDER BY age DESC
        $aliceFirst = CW_SQL::getInstance()->select(array('firstname'), 'people', null, array('age' => 'DESC'));

        $alice = $aliceFirst[0];
        $this->assertEquals($this->alice['firstname'], $alice->firstname);
        $barney = $aliceFirst [1];
        $this->assertEquals('Barney', $barney->firstname);
    }

    public function testSelect_withDates() {
        $this->markTestIncomplete('Test not yet complete');
    }

    public function testInsert_simple() {
        $firstname = 'testInsert_' . time();
        $lastname = 'Test user';
        $age = 99;
        $createdDate = new DateTime();

        // INSERT INTO people (firstname, lastname, age, createdDate, )
        $newId = CW_SQL::getInstance()->insert('people',
            array('firstname' => $firstname, 'lastname' => $lastname, 'age' => $age, 'createdDate' => $createdDate));

        echo 'New ID: ' . $newId . "\n";
        $result = $this->_db->query('SELECT firstname, lastname, age, createdDate, age FROM people WHERE firstname = "' . $firstname . '"');
        $this->assertEquals(1, $result->num_rows, '1 result should be returned: just the recently-inserted test user');

        $testUser = $result->fetch_object();

        $this->assertEquals($firstname, $testUser->firstname, 'First name does not match');
        $this->assertEquals($age, $testUser->age, 'Age does not match');
        $this->assertEquals($lastname, $testUser->lastname, 'Lastname does not match');
        $this->assertEquals($createdDate, new DateTime($testUser->createdDate), 'Created Date does not match');

        $testUser = CW_SQL::getInstance()->selectRow(array('firstname', 'lastname', 'age', 'createdDate'), 'people', array('age' => $age));

        $this->assertEquals($firstname, $testUser->firstname, 'From selectRow(): First name does not match');
        $this->assertEquals($age, $testUser->age, 'From selectRow(): Age does not match');
        $this->assertEquals($lastname, $testUser->lastname, 'From selectRow(): Lastname does not match');
        $this->assertEquals($createdDate, new DateTime($testUser->createdDate), 'From selectRow(): Created date does not match');

    }

    public function testInsert_withHardDataTypes() {
        $balance = 0.22;
        $id = CW_SQL::getInstance()->insert('people',
            array('firstname' => 'balanceTest1', 'lastname' => 'surnamebt1', 'age' => 23, 'balance' => $balance,
                    'realBalance' => $balance));

        $results = CW_SQL::getInstance()->query('SELECT balance, realBalance FROM people WHERE id = ' . $id);
        $addedPerson = $results->fetchObject();
        $tolerance = 0.001;
        $difference = abs($balance - $addedPerson->balance);
        $this->assertTrue($difference < $tolerance);


        $this->assertEquals($balance, $addedPerson->realBalance);


    }


    public function testDelete() {
        $this->markTestIncomplete('This test is not yet implemented');
    }

    public function testUpdateNormal() {
        // UPDATE people SET firstname = 'Fred' WHERE firstname = 'fred'
        $fredsNewName = 'LaLinea';
        $numRowsChanged = CW_SQL::getInstance()->update(
            'people', array('firstname' => $fredsNewName), array('age' => 11)
        );

        $this->assertEquals(1, $numRowsChanged, '1 row should have been modified ("fred")');

        $result = $this->_db->query('SELECT firstname FROM people WHERE age = 11');

        $this->assertEquals(1, $result->num_rows);

        $fred = $result->fetch_object();

        $this->assertEquals($fredsNewName, $fred->firstname);

    }



    public function testUpdate_badParameters() {
        try {
            CW_SQL::getInstance()->update('people', array('firstname' => 'Fred'), null);
            $this->fail('Expected exception when using null where clause');
        } catch(Exception $ex) { /* Expected */ }

        try {
            CW_SQL::getInstance()->update('people', array('firstname' => 'Fred'), array());
            $this->fail('Expected exception when using an empty array for where');
        } catch(Exception $ex) { /* Expected */ }
    }

    public function testQuery() {
        $this->markTestIncomplete('This test is not yet implemented');
    }

    public function testLastInsertId() {
        CW_SQL::getInstance()->insert('people',
            array('firstname' => 'testLastInsertId', 'lastname' => 'User', 'age' => 50, 'createdDate' => time()));
        $lastInsertId = CW_SQL::getInstance()->getLastInsertId();

        //$lastInsertOnly = CW_SQL::getInstance()->select('people', array('firstname' => 'testLastInsertId'));
        $results = CW_SQL::getInstance()->query('SELECT id, firstname FROM people WHERE id=' . $lastInsertId);
        $this->assertEquals(1, $results->rowCount(), 'No row found by ID ' . $lastInsertId);
        $singleRow = $results->fetchObject();

        $this->assertEquals($singleRow->id, $lastInsertId, 'Insert ID from class does not match actual from database.');
        $this->assertEquals('testLastInsertId', $singleRow->firstname, 'Inserted name does not match row retrieved via inserted ID');
    }

}
/**
 * This class is here to test retrieving data with a custom class rather than stdClass.
 * See testSelect_withObject
 */
class Person {
    // Public properties just for speed and simplicity of testing.
    public $firstname;
    public $age;

    // Private property to test & demonstrate that mysqli will set private properties
    private $lastname;

    public function getLastname() {
        return $this->lastname;
    }
}
