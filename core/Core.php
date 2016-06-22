<?php

namespace Core;

/**
 * Core class
 * 
 * This runs all the things.
 * 
 * Yes, all the things.
 * 
 * @package  Core
 * @version  1.0
 */
class Core
{
    /**
     * The one and only method in this class. Determines the controller to use,
     * and the action to pass to it (prepending it with "run_").
     * 
     * @param  array  $config Configuration array
     * @return void
     */
    public static function run(array $config = [])
    {
        // Set all our defaults
        $controller = $config['default_controller'];
        $action = 'index';
        $url = '';
        
        // Get route URL
        $route_url = $_GET['route'] ?: '';
        
        // Trim the / from the left & right of the URL
        $url = trim($route_url, '/');
        
        // Split the URL into segments
        $segments = explode('/', $url);
        
        // Default checks
        $controller = $segments[0] ?: $controller;
        $action     = $segments[1] ?: $action;
        
        // Determine the Controller we're using
        $controller_dir = APP_DIR . 'Controller' . DIRECTORY_SEPARATOR;
        $path = $controller_dir . $controller . '.php';
        if (file_exists($path)) {
            require_once($path);
        } else {
            $controller = $config['error_controller'];
            require_once($controller_dir . $controller . '.php');
        }
        
        // Check that the action exists
        if (!method_exists($controller, $action)) {
            $controller = $config['error_controller'];
            require_once($controller_dir . $controller . '.php');
            $action = 'index';
        }
        
        // Prepend action with "run_" (e.g. "run_edit", "run_view", "run_index")
        $action = 'run_' . $action;
        
        // Create object and call the method
        $obj = new $controller;
        die(
            call_user_func_array(
                [
                    $obj,
                    $action,
                ],
                array_slice($segments, 2)
            )
        );
    }
}
