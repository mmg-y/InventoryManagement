<?php
    defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);
    defined('SITE_ROOT') ? null : define('SITE_ROOT', realpath(__DIR__ . '/../../'));
    defined('INC_PATH') ? null : define('INC_PATH', SITE_ROOT . DS . 'api_files\includes');
    defined('CORE_PATH') ? null : define('CORE_PATH', SITE_ROOT . DS . 'api_files\core');

    //Load the config file first
    require_once(INC_PATH . DS . 'config.php');

    //include classes
    require_once(CORE_PATH . DS . 'post.php');
    