<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * Date: 01/06/2012
 * Time: 13:02
 */
require_once('../bootstrap.php');
class CW_SimpleDaoTest extends CW_AbstractDbTest {
    /**
     * @var CW_SimpleDao
     */
    private $_dao;

    public function setup() {
        parent::setup();
        $this->_dao = new CW_SimpleDao('Person', array('firstname', 'lastname', 'age', 'balance', 'id'), 'people');
    }

    public function testLoad_Simple() {
        $fred = $this->_dao->loadById(array('id' => 1));
        $this->assertEquals($this->fred['firstname'], $fred->getFirstname());
    }

    public function testInsert_Simple() {
        $newPerson = new Person();
        $newPerson->setFirstname('TestInsert')
                 ->setLastname('Simple')
                 ->setAge(21)
                 ->setBalance(11.11);

        $newPersonId = $this->_dao->insert($newPerson);

        $this->assertEquals('integer', gettype($newPersonId));

        $dbResults = $this->getPersonById($newPersonId);

        $this->assertEquals(1, $dbResults->num_rows);

        $retrievedPerson = $dbResults->fetch_object();
        $this->assertEquals('TestInsert', $retrievedPerson->firstname);
    }

    public function testRoundTrip_InsertSelect() {
        $newPerson = $this->createTestPerson('testRoundTrip', 'InsertSelect');

        $newPersonId = $this->_dao->insert($newPerson);

        $retrievedPerson = $this->_dao->loadById(array('id' => $newPersonId));

        $this->assertEquals($newPerson->getFirstname(), $retrievedPerson->getFirstName());
        $this->assertEquals($newPerson->getLastname(), $retrievedPerson->getLastname());
        $this->assertEquals($newPerson->getAge(), $retrievedPerson->getAge());
        $this->assertEquals($newPerson->getLastname(), $retrievedPerson->getLastname());
    }

    public function createTestPerson($firstname, $lastname)
    {
        $newPerson = new Person();
        $newPerson->setFirstname($firstname)
            ->setLastname($lastname)
            ->setAge(21)
            ->setBalance(11.11);
        return $newPerson;
    }

    public function getPersonById($newPersonId)
    {
        $dbResults = $this->_db->query('SELECT firstname, lastname, age, balance FROM people WHERE id = ' . $newPersonId);
        return $dbResults;
    }

    public function testUpdate_Simple() {
        $fred = $this->_dao->loadById(array('id' => 1));

        $fred->setFirstname('TestFRED');
        $this->_dao->update($fred, array('id' => $fred->getId()));

        $dbResult = $this->getPersonById($fred->getId());
        $newFred = $dbResult->fetch_object();

        $this->assertEquals('TestFRED', $newFred->firstname);

    }

    public function testFind_Simple() {

    }
}

abstract class AbstractThing {
    private $age;
    private $id;

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function getAge() {
        return $this->age;
    }
    public function setAge($age) {
        $this->age = $age;
        return $this;
    }
}

class Person extends AbstractThing {
    private $firstname;
    private $lastname;
    private $balance;

    public function getFirstname() {
        return $this->firstname;
    }

    public function getLastname() {
        return $this->lastname;
    }


    public function getBalance() {
        return $this->balance;
    }

    public function setFirstname($firstname) {
        $this->firstname = $firstname;
        return $this;
    }

    public function setLastname($lastname) {
        $this->lastname = $lastname;
        return $this;
    }

    public function setBalance($balance) {
        $this->balance = $balance;
        return $this;
    }
}