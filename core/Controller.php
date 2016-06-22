<?php

namespace Core;

/**
 * Controller class
 * 
 * @package  Controller
 * @author   Richard J Brum <RichardJBrum@gmail.com>
 * @version  0.1
 */
class Controller
{
	/**
	 * Instantiate and return Model instance
	 * 
	 * @param  string $class Model class name
	 * @return Model         Instance of model
	 */
	public function loadModel($class)
	{
		$file = MODEL_DIR . $class . '.php';
		if (!file_exists($file)) {
			throw new Exception("Model $class not a valid model!");
		}
		
		$model = new $class();
		return $model;
	}
	
	/**
	 * Instantiate and return View instance
	 * 
	 * @param  string $class View class name
	 * @return View          Instance of view
	 */
	public function loadView($class)
	{
		$file = VIEW_DIR . $class . '.php';
		if (!file_exists($file)) {
			throw new Exception("View $class is not a valid view!");
		}
		
		$view = new $class();
		return $view;
	}
	
	/**
	 * Instantiate and return Plugin instance
	 * 
	 * @param  string $class Plugin class name
	 * @return Plugin        Instnace of Plugin
	 */
	public function loadPlugin($class)
	{
		$file = PLUGIN_DIR . $class . '.php';
		if (!file_exists($file)) {
			throw new Exception("Plugin $class is not a valid plugin!");
		}
		
		$plugin = new $class();
		return $plugin;
	}
	
	/**
	 * Instantiate and return Helper instance
	 * 
	 * @param  string $class Helper class name
	 * @return Helper        Instance of Helper
	 */
	public function loadHelper($class)
	{
		$file = HELPER_DIR . $class . '.php';
		if (!file_exists($file)) {
			throw new Exception("Helper $class is not a valid helper!");
		}
		
		$helper = new $class();
		return $helper;
	}
	
	/**
	 * Redirect to URI under base URL
	 * 
	 * @param  string $uri Where to go
	 * @return void
	 */
	public function redirect($uri)
	{
		global $config;
		header('Location: ' . BASE_URL . $uri);
	}
}
