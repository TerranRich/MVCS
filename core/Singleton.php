<?php

namespace Core;

class Singleton
{
    /**
     * Reference to Singleton instance of this class
     * @var Core
     */
    private static $instance;
    
    /**
     * Return the Singleton instance of this class
     * 
     * @return Core  Singleton instance of Core
     */
    public static function init()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }
    
    /**
     * Protected constructor to prevent creating a new instance of the Singleton
     * via the `new` operator from outside this class
     */
    protected function __construct()
    {
        // This space intentionally left blank
    }
    
    /**
     * Private clone method to prevent cloning of the Singleton instance
     * 
     * @return void
     */
    private function __clone()
    {
        // This space intentionally left blank
    }
    
    /**
     * Private wake method to prevent unserializing of the Singleton instance
     * 
     * @return void
     */
    private function __wake()
    {
        // This space intentionally left blank
    }
}
