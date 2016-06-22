<?php

namespace Core;

/**
 * View class
 * 
 * The way I want to implement Views is like this: there are different layouts
 * for various pages. For members, there will be one layout; for users not
 * logged in, there's another layout; help pages may have a completely different
 * look; and so forth.
 * 
 * Each child class that extends View will handle each type of layout. So there
 * could be a MemberView, a LoggedOutView, a HelpPageView, etc. Each type of
 * View will then accept a specific template file (.tpl), the contents of which
 * will be placed in the child View's HTML header, footer & surrounding content.
 * 
 * @author  Richard J Brum <RichardJBrum@gmail.com>
 * @version 0.1
 */
abstract class View
{
	/**
	 * Page variables
	 * 
	 * @access private
	 * @var array
	 */
	private $page_vars = [];
	
	/**
	 * Main template; set by child Views
	 * 
	 * @access protected
	 * @var string
	 */
	protected $main_template;
	
	/**
	 * Sub-template; set by individual constructor of child Views
	 * 
	 * @access private
	 * @var string
	 */
	private $sub_template;
	
	/**
	 * View constructor
	 * 
	 * Stores given template filename passed into class variable
	 * 
	 * @access protected
	 * @param string $template Template filename
	 */
	protected function __construct($template)
	{
		$this->main_template = TPL_MAIN_DIR . $this->main_template . '.tpl';
		$this->sub_template  = TPL_DIR      . $template            . '.tpl';
	}
	
	/**
	 * Store a key/val set into the page variables array
	 * 
	 * @access protected
	 * @param  string $key Key
	 * @param  mixed  $val Value
	 * @return void
	 */
	protected function set($key, $val)
	{
		$this->page_vars[$key] = $val;
	}
	
	/**
	 * Render contents of sub-template, placing them into main template
	 * 
	 * @todo  Implement Handlebars instead of this amateur stuff
	 * 
	 * @access protected
	 * @return void
	 */
	protected function render()
	{
		extract($this->page_vars);
		
		// Render sub-template contents
		ob_start();
		require($this->sub_template);
		$page_contents = ob_get_clean();
		ob_flush();
		
		// Place sub-template contents into main template
		ob_start();
		require($this->main_template);
		echo ob_get_clean();
	}
}
