<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * Date: 01/06/2012
 * Time: 15:10
 */
class CW_TestAssumptions extends PHPUnit_Framework_TestCase
{
    const MINIMUM_PHPVERSION = '5.3.9';

    public function testVersion() {
        $this->assertEquals(1, version_compare(phpversion(), self::MINIMUM_PHPVERSION),
            'PHP version is not correct. Expected: ' . self::MINIMUM_PHPVERSION . '-[stuff], got: ' . phpversion());
    }

}
