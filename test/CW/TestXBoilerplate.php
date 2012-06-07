<?php

/**
 * Private class that allows the test to override the configuration storage and set it's own without having to load
 * it from a configuration file.
 */
class CW_TestXBoilerplate extends xBoilerplate {
    public static $config;

    /**
     * Triggers the overriding of the standard xBoilerplate singleton with this version.
     * @static
     *
     */
    public static function overrideStandardXBoilerplate() {
        xBoilerplate::$_instance = new CW_TestXBoilerplate();
    }

    public function getConfig() {
        return self::$config;
    }
}
