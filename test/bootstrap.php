<?php

defined('BASE_PATH') || define('BASE_PATH', realpath(dirname(__FILE__)));

set_include_path(
    get_include_path() . PATH_SEPARATOR .
        BASE_PATH . '/../lib' . PATH_SEPARATOR .
        BASE_PATH . '/lib'
);

function xBoilerplate_additions_autoload($class) {
    $file = str_replace('_', '/', $class) . '.php';
    require_once $file;
}


spl_autoload_register('xBoilerplate_additions_autoload');
