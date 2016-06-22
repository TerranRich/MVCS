<?php

/**
 * MOVICE: Model/Oauth/View/apI/Controller/Extendible
 * 
 * It's a working title. Nothing is finalized. Chill out.
 * 
 * @author  Richard J Brum <RichardJBrum@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License 3.0
 * @version 0.1
 * @link    https://github.com/TerranRich/MVCS
 */

// Define some constants
define('ROOT_DIR',       realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('APP_DIR',        ROOT_DIR . 'app' .        DIRECTORY_SEPARATOR); 
define('MODEL_DIR',      APP_DIR  . 'Model' .      DIRECTORY_SEPARATOR);
define('VIEW_DIR',       APP_DIR  . 'View' .       DIRECTORY_SEPARATOR);
define('CONTROLLER_DIR', APP_DIR  . 'Controller' . DIRECTORY_SEPARATOR);
define('SERVICE_DIR',    APP_DIR  . 'Service' .    DIRECTORY_SEPARATOR);
define('PLUGIN_DIR',     APP_DIR  . 'Plugin' .     DIRECTORY_SEPARATOR);
define('HELPER_DIR',     APP_DIR  . 'Helper' .     DIRECTORY_SEPARATOR);
define('TPL_DIR',        APP_DIR  . 'templates' .  DIRECTORY_SEPARATOR);
define('TPL_MAIN_DIR',   TPL_DIR  . 'main' .       DIRECTORY_SEPARATOR);

// Class autoloader
function __autoload($class_name)
{
    // Convert namespace to full file path (e.g. app/Full/Namespace/Here/Class.php)
    $class_path = str_replace('\\', '/', $class_name);
    $class_file = APP_DIR . DIRECTORY_SEPARATOR . $class_path . '.php';
    require_once($class_file);
}

// Start the session
session_start();

// Requires
require(APP_DIR  . 'config/config.php');
require(ROOT_DIR . 'core/DB.php');
require(ROOT_DIR . 'core/Table.php');
require(ROOT_DIR . 'core/Model.php');
require(ROOT_DIR . 'core/View.php');
require(ROOT_DIR . 'core/Controller.php');

// Define base URL
global($config);
define('BASE_URL', $config['base_url']);

// Initialize all the things!
require(ROOT_DIR . 'core/Core.php');
Core::run($config);
