<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * Date: 29/05/2012
 * Time: 15:22
 */
class CW_MySQLTestListener implements PHPUnit_Framework_TestListener
{
    const ROW_FORMAT = '%10.10s';

    private function dumpPeopleTable() {
        $allRows = CW_MySQL::getInstance()->query('SELECT * FROM people');
        $isFirstRow = true;
        $row = $allRows->fetch_assoc();
        do {
            if($isFirstRow) {
                foreach($row AS $columnName => $columnValue) {
                    printf(self::ROW_FORMAT, $columnName);
                    echo ' ';
                }
                echo "\n";
            }
            foreach($row AS $columnName => $columnValue) {
                printf(self::ROW_FORMAT, $columnValue);
            }
            echo "\n";
        }while($row = $allRows->fetch_assoc());

    }

    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time) {
        $this->dumpPeopleTable();
    }

    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time) {
        $this->dumpPeopleTable();
    }

    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time) { }

    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time) { }

    public function startTest(PHPUnit_Framework_Test $test) { }

    public function endTest(PHPUnit_Framework_Test $test, $time) { }

    public function startTestSuite(PHPUnit_Framework_TestSuite $suite) { }

    public function endTestSuite(PHPUnit_Framework_TestSuite $suite) { }
}
